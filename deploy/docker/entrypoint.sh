#!/bin/sh
set -eu
mkdir -p /data/runtime /data/meilisearch /app/storage/app/private /app/storage/framework/cache/data /app/storage/framework/sessions /app/storage/framework/views /app/storage/logs /app/bootstrap/cache
chown -R www-data:www-data /data /app/storage /app/bootstrap/cache
if [ ! -f /app/.env ]; then
    cp /app/.env.example /app/.env
fi
if [ ! -s /data/runtime/app-key ]; then
    php -r 'echo "base64:".base64_encode(random_bytes(32));' > /data/runtime/app-key
    chmod 600 /data/runtime/app-key
fi
rm -f /data/runtime/ready
export APP_KEY="${APP_KEY:-$(cat /data/runtime/app-key)}"
sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" /app/.env
printf '[www]\nlisten = 127.0.0.1:9002\n' > /usr/local/etc/php-fpm.d/zz-paperpulse.conf
if [ "$#" -gt 0 ]; then
    exec "$@"
fi
