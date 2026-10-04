#!/bin/sh
set -eu
cd /app
attempt=0
until curl --fail --silent --max-time 2 http://127.0.0.1:7700/health >/dev/null && curl --fail --silent --max-time 2 http://garage:3903/health >/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 120 ]; then
        echo 'Search or Garage did not become healthy; inspect docker compose logs.' >&2
        exit 1
    fi
    sleep 1
done
php artisan package:discover --no-interaction
php deploy/docker/create-buckets.php
php artisan migrate:safe --no-interaction
php artisan meilisearch:configure --no-interaction
php artisan scout:reindex-all --no-interaction
touch /data/runtime/ready
