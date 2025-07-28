# K6 Load Testing for Skynet API

## Overview
This directory contains K6 load testing scripts to validate the Skynet API's capability to handle **1,000+ lead submissions per minute** as specified in the requirements.

## Test Scenarios

### 1. Sync Processing Test (`sync_processing`)
- **Target**: 17 requests/second (≈1,020 requests/minute)
- **Duration**: 2 minutes
- **Endpoint**: `POST /api/v1/lead/process`
- **Purpose**: Validate synchronous lead processing meets 1000+/minute requirement

### 2. Async Processing Test (`async_processing`)
- **Target**: 50 requests/second (3,000 requests/minute)
- **Duration**: 2 minutes
- **Endpoint**: `POST /api/v1/lead/process-async`
- **Purpose**: Test higher throughput with asynchronous processing

### 3. Bulk Processing Test (`bulk_processing`)
- **Target**: 5 bulk requests/second (250+ leads/minute per batch)
- **Duration**: 1 minute
- **Endpoint**: `POST /api/v1/leads/process-bulk`
- **Purpose**: Test batch processing capabilities

## Prerequisites

### 1. Install K6
```bash
# macOS (using Homebrew)
brew install k6
```

### 2. Start the Application
```bash
# Make sure the Skynet API is running
./start.sh
# or
docker-compose up -d
```

### 3. Verify Services
```bash
# Check if API is accessible
curl http://localhost:8001/api/v1/login

# Verify database and Redis are running
docker-compose ps
```

## Running Tests

### Load Tests
```bash
# tests
k6 run k6/load-test.js
k6 run k6/batch-processing-performance-test.js
```

### Quick Performance Validation
```bash
# Quick 30-second test
k6 run k6/quick-test.js
```

## Performance Targets

### Success Criteria
- ✅ **Throughput**: 1,000+ successful submissions per minute
- ✅ **Response Time**: 90% under 500ms, 95% under 1s
- ✅ **Error Rate**: Less than 5%
- ✅ **Async Performance**: Response time under 200ms
- ✅ **System Stability**: No memory leaks or crashes

### Expected Results
Based on the architecture:
- **Sync Processing**: ~1,020 requests/minute
- **Async Processing**: ~3,000 requests/minute (queued)
- **Bulk Processing**: ~15,000 leads/minute (via batching)

## Test Data
The load tests use realistic, randomized lead data:
- **Names**: Rotated from common first/last names
- **Emails**: Unique with timestamps to prevent duplicates
- **Phones**: Random valid US phone numbers
- **Dynamic Fields**: Company, source, campaign information

## Monitoring During Tests

### Application Metrics
```bash
# Monitor container resources
docker stats

# Check worker processes
docker-compose exec app php bin/console messenger:stats -vv
```

### Database Performance
```bash
# Connect to MySQL and monitor
docker-compose exec database mysql -uroot -ppassword -e "SHOW PROCESSLIST;"

# Check table sizes
docker-compose exec database mysql -uroot -ppassword leads_api -e "SELECT COUNT(*) FROM leads;"
```

## Interpreting Results

### K6 Output
- **http_reqs**: Total requests made
- **http_req_duration**: Response time percentiles
- **http_req_failed**: Error rate percentage
- **iterations**: Successful test completions

### Success Indicators
✅ **1000+ submissions/minute achieved** if:
- Sync test shows >17 req/s with <5% errors
- Response times stay under thresholds
- No significant performance degradation over time

### Troubleshooting
- **High error rates**: Check authentication, database connections
- **Slow responses**: Monitor MySQL/Redis performance
- **Memory issues**: Check worker memory limits
- **Connection errors**: Verify network and Docker setup

## Example Output
```
scenarios: (100.00%) 3 scenarios, 120 max VUs, 6m30s max duration

✓ sync processing status is 201 or 409
✓ sync processing response time < 1s
✓ async processing status is 202
✓ bulk processing status is 202

http_reqs......................: 15000  125.0/s
http_req_duration..............: avg=245ms min=45ms med=200ms max=980ms p(90)=350ms p(95)=450ms
http_req_failed................: 1.2%   ✓ 180 ✗ 14820
lead_processing_duration.......: avg=180ms min=20ms med=150ms max=800ms p(90)=280ms p(95)=400ms

RESULT: ✅ 1000+ submissions/minute capability VALIDATED
```