#!/bin/sh
set -eu
cd /app
export DB_CONNECTION=pgsql DB_HOST=postgres DB_PORT=5432 DB_URL=""
mode="${1:-backend}"
if [ "$#" -gt 0 ]; then shift; fi
case "$mode" in
    backend)
        export APP_ENV=testing SCOUT_DRIVER=collection CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync
        export DB_DATABASE=paperpulse_test DB_USERNAME=paperpulse_test DB_PASSWORD=paperpulse_test
        export GEMINI_API_KEY=isolated-test OPENAI_API_KEY=isolated-test TEXTRACT_KEY=isolated-test TEXTRACT_SECRET=isolated-test
        export RUN_GEMINI_INTEGRATION_TESTS=false
        exec gosu www-data php vendor/bin/pest "$@"
        ;;
    browser)
        export APP_ENV=testing APP_URL=http://127.0.0.1:8082 DB_DATABASE=paperpulse_browser_test DB_USERNAME=paperpulse_test DB_PASSWORD=paperpulse_test
        export SCOUT_DRIVER=collection SCOUT_PREFIX=browser_ MAIL_MAILER=array BROADCAST_CONNECTION=log
        export GEMINI_API_KEY=isolated-test OPENAI_API_KEY=isolated-test TEXTRACT_KEY=isolated-test TEXTRACT_SECRET=isolated-test
        gosu www-data php artisan migrate --force --no-interaction
        exec gosu www-data php vendor/bin/pest --configuration=phpunit.dusk.xml --exclude-group=processing "$@"
        ;;
    *)
        echo 'Usage: test.sh backend [Pest options] | browser [Dusk options]' >&2
        exit 2
        ;;
esac
