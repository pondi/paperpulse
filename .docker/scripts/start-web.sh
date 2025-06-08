#!/bin/bash
set -e

echo "Starting web server..."

# Run migrations with proper locking
/app/docker/scripts/run-migrations.sh

# Ensure storage permissions
chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Start Octane with FrankenPHP
echo "Starting Laravel Octane with FrankenPHP..."
exec php artisan octane:frankenphp --host=0.0.0.0 --port=8000 --workers=auto --max-requests=500