# syntax=docker/dockerfile:1
FROM node:22-bookworm-slim AS node
FROM composer:2 AS composer
FROM getmeili/meilisearch:v1.15 AS meilisearch
FROM php:8.5-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor curl ca-certificates git unzip gosu musl \
    libpq-dev libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libmagickwand-dev imagemagick ghostscript chromium chromium-driver \
    libreoffice-writer libreoffice-calc libreoffice-impress bubblewrap \
    fonts-dejavu fonts-liberation \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_pgsql bcmath intl gd zip pcntl \
    && pecl install imagick-3.8.1 && docker-php-ext-enable imagick \
    && sed -i 's/rights="none" pattern="PDF"/rights="read" pattern="PDF"/' /etc/ImageMagick-6/policy.xml \
    && rm -rf /var/lib/apt/lists/*
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=meilisearch /bin/meilisearch /usr/local/bin/meilisearch
COPY --from=meilisearch /usr/lib/libgcc_s.so.1 /usr/lib/libgcc_s.so.1
RUN for path in /etc/ld-musl-*.path; do echo /usr/lib >> "$path"; done
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY deploy/docker/nginx.conf /etc/nginx/nginx.conf
COPY deploy/docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY deploy/docker/php.ini /usr/local/etc/php/conf.d/paperpulse.ini
COPY deploy/docker/entrypoint.sh deploy/docker/test.sh deploy/docker/bootstrap.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/test.sh /usr/local/bin/bootstrap.sh
COPY --chown=www-data:www-data . .
RUN composer dump-autoload --no-interaction --no-scripts \
    && VITE_REVERB_APP_KEY=paperpulse-local VITE_REVERB_HOST=localhost VITE_REVERB_PORT=8081 VITE_REVERB_SCHEME=http npm run build \
    && mkdir -p /data/meilisearch /data/runtime storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data /data storage bootstrap/cache public/build
EXPOSE 80 5173 7700 8081 3900
ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
