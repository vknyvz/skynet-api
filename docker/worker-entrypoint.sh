#!/bin/bash
set -e

# Wait for RabbitMQ to be ready
echo "Waiting for RabbitMQ to be ready..."
until nc -z rabbitmq 5672; do
  echo "RabbitMQ is unavailable - sleeping"
  sleep 2
done
echo "RabbitMQ is ready!"

# Wait a bit more to ensure AMQP is fully initialized
sleep 5

# Start consuming messages
echo "Starting messenger consumer..."
exec php /var/www/html/bin/console messenger:consume rabbitmq_leads rabbitmq_logs -vv --time-limit=3600 --memory-limit=128M