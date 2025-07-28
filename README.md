# Skynet API - High-Performance Lead Ingestion System

A high-performance lead ingestion API built on Symfony 7.3.

## 🏗️ Architecture Implementation

```
                            ┌─────────────────────┐
                            │ Login via JWT token │
                            │                     │
                            └─────────────────────┘
┌─────────────────────┐    ┌────────────────────┐    ┌─────────────────────┐
│   API Request       │───▶│   Redis Cache      │───▶│   Fast Response     │
│   (Read Data)       │    │   (Query Results)  │    │   (Cached Data)     │
└─────────────────────┘    └────────────────────┘    └─────────────────────┘

┌─────────────────────┐    ┌────────────────────┐    ┌─────────────────────┐    ┌───────────────────┐
│   API Request       │───▶│   RabbitMQ Queue   │───▶│   Worker Process    │───▶│     Database      │
│   (Submit Leads)    │    │   (Async Messages) │    │   (Batch Insert)    │    │   (Batch Write)   │
└─────────────────────┘    └────────────────────┘    └─────────────────────┘    └───────────────────┘
```

## 🚀 Key Features

- **High-Throughput Processing**: Optimized batch processing with persist/flush/clear pattern achieving 9,000+ leads/minute
- **Advanced Caching**: Redis-based caching for query results and API responses  
- **Async Processing**: Symfony Messenger with background workers for scalable processing
- **JWT Authentication**: Secure token-based API authentication
- **Dynamic Data Storage**: Entity-Attribute-Value (EAV) pattern for flexible lead schemas
- **Comprehensive Monitoring**: Request/response logging with performance metrics
- **Memory Optimization**: Configurable batch sizes with automatic memory management
- **Docker Environment**: Complete containerized development and production setup

## 🏗️ Architecture

### Technology Stack
- **Framework**: Symfony 7.3 with advanced optimizations
- **Database**: MySQL 8.0 with EAV schema design
- **Caching**: Redis 7 for query results and application cache (read-only operations)
- **Authentication**: JWT (lexik/jwt-authentication-bundle)  
- **ORM**: Doctrine with optimized batch processing patterns
- **Message Queue**: Symfony Messenger with RabbitMQ AMQP transport (zero database queuing)
- **Web Server**: Nginx + PHP-FPM 8.3
- **Monitoring**: Built-in performance metrics and logging

## 🚀 Quick Start

### 1. Install
```bash
# Build Docker containers and Start up all services
./start.sh
```

### 2. Access Services
- **API**: http://localhost:8001/api/v1/ (Symfony app)
- **phpMyAdmin**: http://localhost:8080 (root/password)
- **RabbitMQ Management**: http://localhost:15672 (guest/guest)

### 3. Authentication Credentials
- **Admin**: username: `admin`, password: `password`
- **User**: username: `user`, password: `password123`
- **API Test**: username: `api_user`, password: `api123`

### 4. Assumptions & Requirements 

- **Admins** can do everything
- **Users** can only submit leads but can't view them
- **Required fields** for any lead submission are; `first_name`, `last_name`, `email`

## 📡 API Endpoints

### Authentication
```
# Login to get JWT token
POST /api/v1/login

# Get User Profile
POST /api/v1/user/profile
```

### Lead Processing
```
# High-Performance Bulk Processing
POST /api/v1/leads/process-bulk

# Single Async Lead Processing
POST /api/v1/lead/process-async

# Single Sync Lead Processing
POST /api/v1/lead/process
```

### Lead Management
```
# Get leads with pagination
GET /api/v1/leads?page=1&limit=10&status=active

# Get single lead
GET /api/v1/leads/{id}
```

## 🚀 Performance Testing & Load Validation

### K6 Load Testing
Check [K6 Readme](k6/README.md)

### Performance Results

| Metric | Result | Requirement   | Status |
|--------|---------|---------------|---------|
| **Bulk Processing** | **6,250+ leads/minute** | 1,000+ leads/min | ✅ **+525%** |
| **Peak Throughput** | **37,726 leads in 6 minutes** | 1,000+ leads/min | ✅ **+525%** |
| **Response Time** | P95: 70ms, P90: 49ms | <1000ms       | ✅ |
| **Memory Efficiency** | 305.67 leads/sec per ms | Stable        | ✅ |
| **Success Rate** | 100% (0 errors) | >95%          | ✅ |
| **Queue Performance** | RabbitMQ AMQP transport | -      | ✅ |

### 🔧 Performance Optimizations

#### Batch Processing Optimizations
- **Persist/Flush/Clear Pattern**: Prevents memory exhaustion during large batch operations
- **Configurable Flush Size**: Automatically adjusts based on batch size (1-10 entities per flush)
- **Memory Management**: Regular EntityManager clearing prevents memory leaks
- **Transaction Optimization**: Batch transactions reduce database overhead

#### Message Queue Architecture (RabbitMQ AMQP)
- **Pure RabbitMQ Queuing**: All messages processed through RabbitMQ AMQP transport
- **Lead Processing Queue**: Dedicated `rabbitmq_leads` transport for lead ingestion and bulk processing
- **Logging Queue**: Separate `rabbitmq_logs` transport for async log writing
- **Redis Caching**: Read-only caching for database queries and API responses (no queuing)

## 🔧 Development & Maintenance

### Cache Management
```bash
# Clear application cache
make cache-clear

# Clear Redis cache
docker-compose exec redis redis-cli FLUSHALL

# Restart message workers
docker-compose restart worker
```

### Database Operations
```bash
# Check database status
docker-compose exec app php bin/console doctrine:query:sql "SELECT COUNT(*) FROM leads"
```

### Message Queue Management
```bash
# Start message consumer for lead processing
docker-compose exec app php bin/console messenger:consume rabbitmq_leads rabbitmq_logs

# Start message consumer for logging
docker-compose exec app php bin/console messenger:consume rabbitmq_logs

# Check queue status
docker-compose exec app php bin/console messenger:stats

# Clear failed messages
docker-compose exec app php bin/console messenger:failed:show
```

## 📊 Monitoring & Observability

### Async Logging

Please read [ASYNC_LOGGING](ASYNC_LOGGING.md)

### Application Metrics
- **Request/Response Logging**: All API calls logged with unique request IDs
- **Performance Metrics**: Response times, throughput, and error rates

### Health Checks
```bash
# API health check
curl http://localhost:8001/api/v1/health

# Database connectivity
docker-compose exec app php bin/console doctrine:query:sql "SELECT 1"

# Redis connectivity  
docker-compose exec redis redis-cli ping
```

## 🔄 Background Processing

### Message Workers
The application uses Symfony Messenger for async processing:

```bash
# Start workers (automatically started by ./start.sh)
# Lead processing worker
docker-compose exec app php bin/console messenger:consume rabbitmq_leads rabbitmq_logs --time-limit=3600

# Logging worker  
docker-compose exec app php bin/console messenger:consume rabbitmq_logs --time-limit=3600

# Monitor worker status
docker-compose logs worker
```

### Queue Management
- **Automatic Retry**: Failed messages retry with exponential backoff
- **Dead Letter Queue**: Persistent failure handling
- **Batch Processing**: Optimized for high-volume operations

## 🛡️ Security Features
- **JWT Authentication**: Secure token-based API access
- **Request Validation**: Input sanitization and validation
- **CORS Configuration**: Proper cross-origin request handling
- **Rate Limiting**: Built-in protection against abuse
- **Error Handling**: Secure error responses without sensitive data exposure

### Performance Tuning
- **OPcache**: Enable PHP OPcache for production
- **Database Indexes**: Optimized for high-volume queries
- **Connection Pooling**: MySQL connection optimization
- **Worker Scaling**: Multiple message workers for parallel processing

### Postman Collection

- [Postman Collection](Skynet_API.postman_collection.json)
  - Set the Global variable DEV_JWT_TOKEN with the token you get back from `api/v1/login`