#!/bin/bash
set -e

echo "Starting queue worker..."

# Wait for database
until php artisan db:monitor > /dev/null 2>&1; do
    echo "Waiting for database..."
    sleep 3
done

# Wait for Redis
until redis-cli -h "${REDIS_HOST:-redis}" ping > /dev/null 2>&1; do
    echo "Waiting for Redis..."
    sleep 3
done

# Clear horizon config
php artisan horizon:clear

# Start supervisor
echo "Starting Horizon via Supervisor..."
exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf