#!/bin/bash
set -e

echo "Starting scheduler..."

# Wait for database
until php artisan db:monitor > /dev/null 2>&1; do
    echo "Waiting for database..."
    sleep 3
done

# Ensure cron log exists
touch /var/log/cron.log

# Start cron in foreground
echo "Starting cron daemon..."
cron && tail -f /var/log/cron.log