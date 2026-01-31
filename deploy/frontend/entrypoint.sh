#!/bin/bash

set -e

echo "=== PaperPulse Frontend Starting ==="
echo "Environment: ${APP_ENV:-unknown}"
echo "Debug mode: ${APP_DEBUG:-unknown}"
echo "Log channel: ${LOG_CHANNEL:-unknown}"

# Ensure no stale Laravel caches (e.g. dev-only providers)
rm -f /var/www/html/bootstrap/cache/*.php || true

# Check critical environment variables
if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY is not set!"
    exit 1
fi

echo "Testing Laravel bootstrap..."
# Test if Laravel can bootstrap (will show errors if it fails)
/usr/bin/php /var/www/html/artisan --version 2>&1 || {
    echo "ERROR: Laravel failed to bootstrap!"
    echo "Checking for common issues..."
    echo "APP_KEY set: $([ -n "$APP_KEY" ] && echo "yes" || echo "NO")"
    echo "Storage writable: $([ -w /var/www/html/storage ] && echo "yes" || echo "NO")"
    exit 1
}

echo "Preparing Laravel framework..."
/usr/bin/php /var/www/html/artisan optimize 2>&1
/usr/bin/php /var/www/html/artisan config:cache 2>&1
# Don't cache views in production when using Vite - it can cause hydration issues
# /usr/bin/php /var/www/html/artisan view:cache

echo "=== Starting supervisord ==="
# Starting supervisord
exec supervisord -c /etc/supervisor.d/supervisord.ini
