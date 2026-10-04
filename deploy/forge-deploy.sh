#!/bin/sh
set -eu

php8.5 artisan config:clear --no-interaction
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php8.5 artisan forge:preflight --no-interaction
php8.5 artisan migrate:safe --force --no-interaction
php8.5 artisan optimize --no-interaction
php8.5 artisan forge:preflight --after-migrations --no-interaction
php8.5 artisan queue:restart --no-interaction
php8.5 artisan schedule:interrupt --no-interaction
