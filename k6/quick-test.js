import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

export let errorRate = new Rate('errors');

// Quick test configuration - 30 seconds
export let options = {
  scenarios: {
    quick_validation: {
      executor: 'constant-arrival-rate',
      rate: 20, // 20 requests per second = 1200 per minute
      timeUnit: '1s',
      duration: '30s',
      preAllocatedVUs: 10,
      maxVUs: 25,
    },
  },
  
  thresholds: {
    http_req_duration: ['p(95)<1000'],
    http_req_failed: ['rate<0.1'], // Allow 10% error rate for quick test
    errors: ['rate<0.1'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8001';

export function setup() {
  console.log('🚀 Quick Performance Validation Test');
  console.log(`Target: ${BASE_URL}`);
  console.log('Duration: 30 seconds at 1200 requests/minute');
  
  // Get JWT token
  const loginResponse = http.post(`${BASE_URL}/api/v1/login`, JSON.stringify({
    username: 'admin',
    password: 'password'
  }), {
    headers: { 'Content-Type': 'application/json' },
  });
  
  if (loginResponse.status === 200) {
    const token = JSON.parse(loginResponse.body).user.token;
    console.log('✅ Authentication successful');
    return { token: token };
  } else {
    console.error('❌ Authentication failed:', loginResponse.status);
    return { token: null };
  }
}

export default function (data) {
  if (!data.token) {
    console.error('No JWT token - skipping request');
    return;
  }
  
  const payload = {
    firstName: 'QuickTest',
    lastName: `User${__VU}_${__ITER}`,
    email: `quicktest.${__VU}.${__ITER}.${Date.now()}@loadtest.com`,
    phone: `+1555${String(__VU).padStart(3, '0')}${String(__ITER).padStart(4, '0')}`,
    dateOfBirth: '1990-01-01',
    source: 'k6_quick_test'
  };
  
  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${data.token}`,
    },
  };
  
  // Test async endpoint for better performance
  const response = http.post(`${BASE_URL}/api/v1/lead/process-async`, JSON.stringify(payload), params);
  
  const success = check(response, {
    'status is 202 (accepted)': (r) => r.status === 202,
    'response time < 500ms': (r) => r.timings.duration < 500,
    'has success response': (r) => {
      try {
        const body = JSON.parse(r.body || '{}');
        return body.success === true;
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
    console.error(`Request failed: ${response.status} - ${response.body?.substring(0, 100)}`);
  } else {
    errorRate.add(0);
  }
  
  sleep(0.05); // Small delay
}

export function teardown(data) {
  console.log('\n📊 Quick Test Results Summary:');
  console.log('If test passed with <10% errors and <1s response times,');
  console.log('the API is ready for full 1000+ submissions/minute load.');
  console.log('\nRun the full test: k6 run k6/load-test.js');
}