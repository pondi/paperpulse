#!/bin/bash
set -e

echo "Starting web server..."

# Wait for database
until php artisan db:monitor > /dev/null 2>&1; do
    echo "Waiting for database..."
    sleep 3
done

# Run migrations (with lock to prevent concurrent execution)
echo "Running migrations..."
php artisan migrate --force --isolated

# Clear and cache configs
echo "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ensure storage permissions
chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Start Octane with FrankenPHP
echo "Starting Laravel Octane with FrankenPHP..."
exec php artisan octane:frankenphp --host=0.0.0.0 --port=8000 --workers=auto --max-requests=500