import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Counter, Trend } from 'k6/metrics';

// Custom metrics for batch processing performance
const batchProcessingSuccessRate = new Rate('batch_processing_success_rate');
const batchProcessingTime = new Trend('batch_processing_time');
const leadsPerMinute = new Rate('leads_per_minute');
const memoryEfficiency = new Trend('memory_efficiency');
const totalLeadsProcessed = new Counter('total_leads_processed');

export const options = {
  scenarios: {
    // Test batch processing with optimized persist/flush/clear pattern
    bulk_processing_test: {
      executor: 'constant-arrival-rate',
      rate: 20, // 20 requests per second
      timeUnit: '1s',
      duration: '3m',
      preAllocatedVUs: 5,
      maxVUs: 10,
    },
    // High-throughput test to validate 1000+ leads/minute
    high_throughput_test: {
      executor: 'constant-arrival-rate',
      rate: 50, // 50 bulk requests per second (500 leads/sec = 30k leads/min)
      timeUnit: '1s', 
      duration: '2m',
      preAllocatedVUs: 10,
      maxVUs: 20,
      startTime: '4m',
    },
  },
  thresholds: {
    http_req_duration: ['p(95)<10000'],
    batch_processing_success_rate: ['rate>0.95'],
    http_req_failed: ['rate<0.05'],
    batch_processing_time: ['p(90)<8000'],
    leads_per_minute: ['rate>16.67'],
  },
};

const BASE_URL = 'http://localhost:8001';

// Generate test lead data
function generateLeadData(index) {
  return {
    firstName: `TestFirst${index}`,
    lastName: `TestLast${index}`,
    email: `test.lead.${index}.${Date.now()}@example.com`,
    phone: `+1555${String(index).padStart(7, '0')}`,
    dateOfBirth: '1990-05-15',
    status: 'active',
    // Dynamic fields to test EAV pattern
    company: `Test Company ${index % 100}`,
    source: ['website', 'facebook', 'google', 'linkedin'][index % 4],
    budget: Math.floor(Math.random() * 100000) + 10000,
    interests: ['product-a', 'product-b', 'service-x'][index % 3],
    customField1: `custom-value-${index}`,
    customField2: Math.random() > 0.5 ? 'true' : 'false',
    notes: `Test notes for lead ${index} with batch processing optimization`
  };
}

// Generate bulk lead data
function generateBulkLeadData(count, startIndex = 0) {
  const leads = [];
  for (let i = 0; i < count; i++) {
    leads.push(generateLeadData(startIndex + i));
  }
  return leads;
}

export function setup() {
  console.log('Starting batch processing performance test...');
  
  // Login to get JWT token
  const loginResponse = http.post(`${BASE_URL}/api/v1/login`, JSON.stringify({
    username: 'admin', 
    password: 'password'
  }), {
    headers: { 'Content-Type': 'application/json' }
  });
  
  if (loginResponse.status === 200) {
    const token = loginResponse.json('token');
    console.log('Successfully obtained JWT token for batch testing');
    return { token: token };
  }
  
  console.log('Failed to obtain token, using fallback');
  return { token: 'fallback-token' };
}

export default function(data) {
  const token = data.token;
  const headers = {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  };

  // Determine batch size based on current scenario
  const currentScenario = __ENV.K6_SCENARIO_NAME || 'bulk_processing_test';
  let batchSize;
  let leadCount;
  
  if (currentScenario === 'high_throughput_test') {
    // Larger batches for high throughput test
    batchSize = Math.floor(Math.random() * 20) + 10; // 10-30 leads per batch
    leadCount = batchSize;
  } else {
    // Smaller batches for detailed performance analysis
    batchSize = Math.floor(Math.random() * 10) + 5; // 5-15 leads per batch
    leadCount = batchSize;
  }

  const startIndex = Math.floor(Math.random() * 10000);
  const bulkLeadData = generateBulkLeadData(leadCount, startIndex);

  const requestPayload = {
    leads: bulkLeadData,
    batchSize: Math.min(batchSize, 10), // Internal batch size for persist/flush/clear
    async: true // Use async processing to test message handler
  };

  const batchStart = Date.now();
  
  const response = http.post(
    `${BASE_URL}/api/v1/leads/process-bulk`,
    JSON.stringify(requestPayload),
    { 
      headers,
      timeout: '30s' // Longer timeout for bulk operations
    }
  );
  
  const batchEnd = Date.now();
  const processingTime = batchEnd - batchStart;

  // Track metrics
  const isSuccess = check(response, {
    'bulk processing status is 200 or 202': (r) => r.status === 200 || r.status === 202,
    'bulk processing response time < 15s': (r) => r.timings.duration < 15000,
    'response contains success indicator': (r) => {
      try {
        const body = r.json();
        return body.success === true || body.message.includes('processing');
      } catch (e) {
        return false;
      }
    },
  });

  batchProcessingSuccessRate.add(isSuccess ? 1 : 0);
  batchProcessingTime.add(processingTime);
  
  if (isSuccess) {
    totalLeadsProcessed.add(leadCount);
    
    // Calculate leads per second for this batch
    const leadsPerSecond = leadCount / (processingTime / 1000);
    leadsPerMinute.add(leadsPerSecond);
    
    // Estimate memory efficiency (lower processing time per lead indicates better efficiency)
    const memoryEfficiencyScore = leadCount / (processingTime / 1000);
    memoryEfficiency.add(memoryEfficiencyScore);
  }

  // Log detailed performance info for analysis
  if (isSuccess && Math.random() < 0.1) { // Log 10% of successful requests
    console.log(`Batch processed: ${leadCount} leads in ${processingTime}ms (${(leadCount / (processingTime / 1000)).toFixed(2)} leads/sec)`);
  }

  // Test async processing status if we get a processing ID
  if (response.status === 202) {
    try {
      const responseBody = response.json();
      if (responseBody.processing_id) {
        // Wait a bit then check status
        sleep(2);
        
        const statusResponse = http.get(
          `${BASE_URL}/api/v1/leads/process-bulk/status/${responseBody.processing_id}`,
          { headers }
        );
        
        check(statusResponse, {
          'status check successful': (r) => r.status === 200,
        });
      }
    } catch (e) {
      // Handle JSON parsing errors gracefully
    }
  }

  // Add some variation in timing
  sleep(Math.random() * 1 + 0.5); // Sleep between 0.5-1.5 seconds
}

export function teardown() {
  console.log('Batch processing performance test completed');
  console.log(`Total leads processed: ${totalLeadsProcessed.value}`);
  
  // Check if we met the 1000+ leads/minute requirement
  const estimatedLeadsPerMinute = (totalLeadsProcessed.value / 5) * 60; // Estimate based on 5-minute test
  console.log(`Estimated throughput: ${estimatedLeadsPerMinute.toFixed(0)} leads/minute`);
  
  if (estimatedLeadsPerMinute >= 1000) {
    console.log('✅ Successfully maintained 1000+ leads/minute throughput requirement');
  } else {
    console.log('❌ Failed to maintain 1000+ leads/minute throughput requirement');
  }
}