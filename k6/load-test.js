import http from 'k6/http';
import {check, sleep} from 'k6';
import {Rate, Trend} from 'k6/metrics';

// Custom metrics
export let errorRate = new Rate('errors');
export let loginDuration = new Trend('login_duration');
export let leadProcessingDuration = new Trend('lead_processing_duration');

// Test configuration
export let options = {
  scenarios: {
    // Sync lead processing test - target 1000 submissions/minute
    sync_processing: {
      executor: 'constant-arrival-rate',
      rate: 17, // 17 requests per second = ~1020 per minute
      timeUnit: '1s',
      duration: '2m',
      preAllocatedVUs: 20,
      maxVUs: 50,
      exec: 'syncLeadTest',
      tags: {test_type: 'sync_processing'},
    },

    // Async lead processing test - higher throughput test
    async_processing: {
      executor: 'constant-arrival-rate',
      rate: 50, // 50 requests per second = 3000 per minute
      timeUnit: '1s',
      duration: '2m',
      preAllocatedVUs: 30,
      maxVUs: 100,
      exec: 'asyncLeadTest',
      startTime: '2m30s', // Start after sync test
      tags: {test_type: 'async_processing'},
    },

    // Bulk processing test
    bulk_processing: {
      executor: 'constant-arrival-rate',
      rate: 5, // 5 bulk requests per second (each with 50+ leads)
      timeUnit: '1s',
      duration: '1m',
      preAllocatedVUs: 10,
      maxVUs: 20,
      exec: 'bulkLeadTest',
      startTime: '5m', // Start after other tests
      tags: {test_type: 'bulk_processing'},
    },
  },

  thresholds: {
    http_req_duration: ['p(90)<500', 'p(95)<1000'], // 90% under 500ms, 95% under 1s
    http_req_failed: ['rate<0.05'], // Error rate under 5%
    errors: ['rate<0.05'],
    login_duration: ['p(95)<200'], // Login should be fast
    lead_processing_duration: ['p(90)<300'], // Lead processing under 300ms
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8001';
let jwtToken = '';

// Setup function - runs once per VU
export function setup() {
  console.log('Starting K6 Load Test for Skynet API');
  console.log(`Target URL: ${BASE_URL}`);
  console.log('Test will validate 1000+ submissions per minute capability');

  // Get JWT token for authentication
  const loginResponse = http.post(`${BASE_URL}/api/v1/login`, JSON.stringify({
    username: 'admin',
    password: 'password'
  }), {
    headers: {'Content-Type': 'application/json'},
  });

  if (loginResponse.status === 200) {
    const token = JSON.parse(loginResponse.body).user.token;
    console.log('Authentication successful - JWT token obtained');
    return {token: token};
  } else {
    console.error('Authentication failed:', loginResponse.status, loginResponse.body);
    return {token: null};
  }
}

// Sync lead processing test
export function syncLeadTest(data) {
  if (!data.token) {
    console.error('No JWT token available for sync test');
    return;
  }

  const payload = generateLeadData();
  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${data.token}`,
    },
  };

  const startTime = Date.now();
  const response = http.post(`${BASE_URL}/api/v1/lead/process`, JSON.stringify(payload), params);
  const duration = Date.now() - startTime;

  leadProcessingDuration.add(duration);

  const success = check(response, {
    'sync processing status is 201 or 409': (r) => r.status === 201 || r.status === 409, // 409 for duplicates is OK
    'sync processing response time < 1s': (r) => r.timings.duration < 1000,
    'sync processing has response body': (r) => r.body && r.body.length > 0,
  });

  // Only count as error if the response is genuinely bad (not 201/409 success responses)
  const isGenuineError = !(response.status === 201 || response.status === 409) || (() => {
    try {
      const body = JSON.parse(response.body || '{}');
      return body.success !== true;
    } catch (e) {
      return response.status < 200 || response.status >= 500;
    }
  })();

  if (isGenuineError) {
    errorRate.add(1);
    console.error(`Sync processing failed: ${response.status} - ${response.body}`);
  } else {
    errorRate.add(0);
    // Log success details only occasionally for monitoring
    if (Math.random() < 0.005) { // Log 0.5% of successful requests
      console.log(`Sync processing success: ${response.status} - response_time: ${response.timings.duration.toFixed(2)}ms`);
    }
  }

  sleep(0.1); // Small delay between requests
}

// Async lead processing test
export function asyncLeadTest(data) {
  if (!data.token) {
    console.error('No JWT token available for async test');
    return;
  }

  const payload = generateLeadData();
  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${data.token}`,
    },
  };

  const startTime = Date.now();
  const response = http.post(`${BASE_URL}/api/v1/lead/process-async`, JSON.stringify(payload), params);
  const duration = Date.now() - startTime;

  leadProcessingDuration.add(duration);

  const success = check(response, {
    'async processing status is 202': (r) => r.status === 202, // Should be 202 Accepted for async
    'async processing response time < 500ms': (r) => r.timings.duration < 500, // Should be faster
    'async processing has queued status': (r) => {
      try {
        const body = JSON.parse(r.body || '{}');
        return body.status === 'queued' && body.success === true;
      } catch (e) {
        return false;
      }
    },
  });

  // Only count as error if the response is genuinely bad (not 202 with success=true)
  const isGenuineError = response.status !== 202 || (() => {
    try {
      const body = JSON.parse(response.body || '{}');
      return body.success !== true;
    } catch (e) {
      return true;
    }
  })();

  if (isGenuineError) {
    errorRate.add(1);
    console.error(`Async processing failed: ${response.status} - ${response.body}`);
  } else {
    errorRate.add(0);
    // Log success details only in verbose mode
    if (Math.random() < 0.01) { // Log 1% of successful requests for monitoring
      console.log(`Async processing success: ${response.status} - response_time: ${response.timings.duration.toFixed(2)}ms`);
    }
  }

  sleep(0.05); // Smaller delay for async processing
}

// Bulk lead processing test
export function bulkLeadTest(data) {
  if (!data.token) {
    console.error('No JWT token available for bulk test');
    return;
  }

  const leads = [];
  const batchSize = 50; // Test with 50 leads per batch

  for (let i = 0; i < batchSize; i++) {
    leads.push(generateLeadData());
  }

  const payload = {
    leads: leads,
    batch_size: 50
  };

  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${data.token}`,
    },
  };

  const startTime = Date.now();
  const response = http.post(`${BASE_URL}/api/v1/leads/process-bulk`, JSON.stringify(payload), params);
  const duration = Date.now() - startTime;

  leadProcessingDuration.add(duration);

  const success = check(response, {
    'bulk processing status is 202': (r) => r.status === 202,
    'bulk processing response time < 2s': (r) => r.timings.duration < 2000,
    'bulk processing has batch info': (r) => {
      try {
        const body = JSON.parse(r.body || '{}');
        return body.details && body.details.total_leads === batchSize && body.success === true;
      } catch (e) {
        return false;
      }
    },
  });

  // Only count as error if the response is genuinely bad (not 202 with success=true)
  const isGenuineError = response.status !== 202 || (() => {
    try {
      const body = JSON.parse(response.body || '{}');
      return body.success !== true;
    } catch (e) {
      return true;
    }
  })();

  if (isGenuineError) {
    errorRate.add(1);
    console.error(`Bulk processing failed: ${response.status} - ${response.body}`);
  } else {
    errorRate.add(0);
    // Log success details only occasionally for monitoring
    if (Math.random() < 0.02) { // Log 2% of successful bulk requests
      console.log(`Bulk processing success: ${response.status} - ${batchSize} leads queued - response_time: ${response.timings.duration.toFixed(2)}ms`);
    }
  }

  sleep(1); // Longer delay for bulk processing
}

// Generate realistic lead data
function generateLeadData() {
  const firstNames = ['Volkan', 'Jane', 'Mike', 'Sarah', 'David', 'Lisa', 'Chris', 'Emma', 'Alex', 'Maria'];
  const lastNames = ['Yavuz', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
  const domains = ['example.com', 'test.com', 'sample.org', 'demo.net', 'mock.com'];

  const firstName = firstNames[Math.floor(Math.random() * firstNames.length)];
  const lastName = lastNames[Math.floor(Math.random() * lastNames.length)];
  const domain = domains[Math.floor(Math.random() * domains.length)];

  const timestamp = Date.now();
  const randomId = Math.floor(Math.random() * 10000);

  return {
    firstName: firstName,
    lastName: lastName,
    email: `${firstName.toLowerCase()}.${lastName.toLowerCase()}.${timestamp}.${randomId}@${domain}`,
    phone: `+1${Math.floor(Math.random() * 9000000000) + 1000000000}`,
    dateOfBirth: '1990-01-01',
    // Add some dynamic fields
    company: 'Test Company',
    source: 'load_test',
    campaign: 'k6_performance_test'
  };
}

export function teardown(data) {
  console.log('K6 Load Test completed!');
  console.log('Check the results to verify 1000+ submissions/minute capability');
}

export default function () {
  // This is not used since I'm using scenarios
}