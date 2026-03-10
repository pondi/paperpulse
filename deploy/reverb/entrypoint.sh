#!/bin/bash

set -e

echo "=== PaperPulse Reverb (WebSocket) Starting ==="
echo "Environment: ${APP_ENV:-unknown}"

# Ensure no stale Laravel caches
rm -f /var/www/html/bootstrap/cache/*.php || true

# Check critical environment variables
if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY is not set!"
    exit 1
fi

if [ -z "$REVERB_APP_KEY" ]; then
    echo "ERROR: REVERB_APP_KEY is not set!"
    exit 1
fi

echo "Testing Laravel bootstrap..."
/usr/bin/php /var/www/html/artisan --version 2>&1 || {
    echo "ERROR: Laravel failed to bootstrap!"
    exit 1
}

echo "Preparing Laravel framework..."
/usr/bin/php /var/www/html/artisan optimize 2>&1
/usr/bin/php /var/www/html/artisan config:cache 2>&1

# Setup signal handlers for graceful shutdown
_term_handler() {
    echo "Received SIGTERM - shutting down Reverb"
    if [ -f /tmp/supervisord.pid ]; then
        kill -TERM "$(cat /tmp/supervisord.pid)" 2>/dev/null || true
        wait "$(cat /tmp/supervisord.pid)" 2>/dev/null || true
    fi
    exit 0
}

trap _term_handler SIGTERM SIGINT

echo "=== Starting Reverb via supervisord ==="
supervisord -c /etc/supervisor.d/supervisord.ini &

# Wait for supervisord and handle signals
wait $!
