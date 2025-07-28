#!/bin/bash

echo "🚀 Starting Skynet API..."

# Stop any existing containers
echo "📋 Stopping existing containers..."
docker-compose -f docker-compose.yml down

# Start containers
echo "🐳 Starting Docker containers..."
docker-compose -f docker-compose.yml up -d --build

# Wait for services to be ready
echo "⏳ Waiting for services to start..."
sleep 20

# Check if app container is running
if ! docker-compose -f docker-compose.yml ps app | grep -q "Up"; then
    echo "❌ App container failed to start. Check logs with: docker-compose logs app"
    exit 1
fi

# Verify composer dependencies
echo "🔍 Checking composer dependencies..."
if ! docker-compose -f docker-compose.yml exec -T app composer install --no-interaction; then
    echo "❌ Composer install failed. Check logs with: docker-compose logs app"
    exit 1
fi

# Wait for database to be ready
echo "⏳ Waiting for database connection..."
until docker-compose -f docker-compose.yml exec -T app php -r "
try {
    new PDO('mysql:host=skynet_api_db;dbname=leads_api', 'app', 'password');
    echo 'Database ready';
    exit(0);
} catch (Exception \$e) {
    exit(1);
}
" > /dev/null 2>&1; do
    echo "   Database not ready yet, waiting..."
    sleep 5
done

# Create JWT keys directory
echo "🔑 Checking for JWT keys..."
docker-compose -f docker-compose.yml exec -T app mkdir -p config/jwt

if docker-compose -f docker-compose.yml exec -T app [ -f .env ] && \
   ! docker-compose -f docker-compose.yml exec -T app [ -f config/jwt/private.pem ]; then
    echo "   Generating JWT keys..."
    docker-compose -f docker-compose.yml exec -T app bash -c '
        JWT_PASSPHRASE=$(grep JWT_PASSPHRASE .env | cut -d "=" -f2)
        openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:"$JWT_PASSPHRASE"
        openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:"$JWT_PASSPHRASE"
    '
else
    echo "   JWT keys already exist or .env not found."
fi

echo "🔄 Running database migrations..."
docker-compose -f docker-compose.yml exec app php bin/console doctrine:migrations:migrate --no-interaction

echo "👤 Creating test users..."
docker-compose -f docker-compose.yml exec app php bin/console doctrine:fixtures:load --no-interaction

echo "✅ Skynet API is ready!"
echo ""
echo "🌐 Services:"
echo "   API: http://localhost:8001/api/v1/"
echo "   phpMyAdmin: http://localhost:8080 (root/password)"
echo ""
echo "🔑 Test credentials:"
echo "   Username: admin"
echo "   Password: password"
echo ""
echo "🧪 Test login:"
echo "   curl -X POST http://localhost:8001/api/v1/login -H \"Content-Type: application/json\" -d '{\"username\":\"admin\",\"password\":\"password\"}'"