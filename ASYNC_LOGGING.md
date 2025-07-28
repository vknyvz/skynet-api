# Skynet API Async Logging System
## Overview

API implements a high-performance async logging system that sends structured JSON logs to the filesystem for Splunk ingestion. The system uses RabbitMQ message queues to ensure zero impact on API performance.

## Architecture

```
API Request → AsyncLoggingService → RabbitMQ → LogMessageHandler → JSON Log Files
```

### Components

- **AsyncLoggingService**: Generates unique Thread Key and queues log messages
  - Point of this Thread key is that, it would be easy to find the entire thread stream in a Splunk like
    log service, by just searching its thread_key.
- **LogMessage**: Value object for structured log data
- **LogMessageHandler**: Async handler that writes JSON logs to filesystem
- **RabbitMQ**: Message queue for async processing
- **Monolog**: JSON formatter for Splunk-compatible output

## Log Format

### API_REQUEST_INGEST
```json
{
  "thread_key": "1753548641-123456-abc123def4",
  "name": "API_REQUEST_INGEST",
  "method": "POST",
  "endpoint": "/api/v1/lead/process",
  "ip": "192.168.1.100",
  "headers": {
    "Authorization": "[FILTERED]",
    "Content-Type": "application/json",
    "User-Agent": "PostmanRuntime/7.32.0"
  },
  "query_params": {},
  "payload": {
    "firstName": "John",
    "lastName": "Doe", 
    "email": "john.doe@example.com"
  },
  "user_agent": "PostmanRuntime/7.32.0",
  "content_type": "application/json",
  "timestamp": "2025-07-26T16:30:41.123Z"
}
```

### API_REQUEST_RESPOND
```json
{
  "thread_key": "1753548641-123456-abc123def4",
  "name": "API_REQUEST_RESPOND",
  "status": 201,
  "payload": {
    "success": true,
    "data": {"id": 2025},
    "request_id": "1753548641-123456-abc123def4"
  },
  "headers": {
    "Content-Type": "application/json"
  },
  "content_type": "application/json",
  "duration_ms": 185.42,
  "timestamp": "2025-07-26T16:30:41.308Z"
}
```

## Thread Key Format

Unique SHA1-style request IDs: `{timestamp}-{random}-{hash}`

Example: `1753548641-123456-abc123def4`

## Log Files

- **Development**: `var/log/dev.log` (main app), `var/log/api.log` (structured logs)
- **Test**: `var/log/test.log` (main app), `var/log/api_test.log` (structured logs)

## Docker Services

### RabbitMQ
- **URL**: `amqp://guest:guest@rabbitmq:5672/%2f`
- **Management UI**: http://localhost:15672 (guest/guest)
- **Queues**: `loads_queue`
- **Exchange**: `logs` (direct)

### Worker Processes
- **2 replicas** processing both lead messages and log messages
- **Command**: `app:messenger:worker -vv`

## Clean Implementation

The async logging system provides a clean, database-free logging solution:

1. **Pure filesystem logging** - No database tables for logs
2. **Async JSON logging** - High-performance message queue processing
3. **Zero performance impact** - Non-blocking async processing
4. **Unique request IDs** - SHA1-style correlation across request/response pairs

## Security Features

- **Sensitive headers filtered**: Authorization, Cookie, X-API-Key, etc.
- **Client IP detection**: Supports Cloudflare, X-Forwarded-For, etc.
- **Input validation**: JSON payload validation and sanitization
- **Error isolation**: Logging failures don't affect API operations

## Performance

- **Async processing**: No blocking operations in API requests
- **RabbitMQ queuing**: High-throughput message processing
- **Worker processes**: Dedicated processes for log writing
- **Memory efficient**: 128M limit per worker process
- **Auto-recovery**: Failed messages retry with exponential backoff

## Splunk Configuration

The JSON logs are designed for direct Splunk ingestion:

1. **Monitor**: `var/log/api.log`
2. **Source type**: `_json`
3. **Index fields**: `thread_key`, `name`, `endpoint`, `status`
4. **Search examples**:
   - `name="API_REQUEST_INGEST" endpoint="/api/v1/lead/process"`
   - `thread_key="1753548641-123456-abc123def4"`
   - `name="API_REQUEST_RESPOND" status>=400`