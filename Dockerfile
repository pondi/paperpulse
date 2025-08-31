# Multi-stage Dockerfile for PaperPulse
# Builds: web, worker, and scheduler containers

# Base stage with PHP 8.2 and common extensions
FROM dunglas/frankenphp:1-php8.2 AS base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libzip-dev \
    libmagickwand-dev \
    supervisor \
    cron \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd zip

# Install ImageMagick
RUN pecl install imagick && docker-php-ext-enable imagick

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application code
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Create directories for logs
RUN mkdir -p /var/log/supervisor

# Web server stage
FROM base AS web

# Install Node.js for asset building
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Copy package files
COPY package.json package-lock.json ./

# Install Node dependencies and build assets
RUN npm ci --only=production && npm run build

# Copy FrankenPHP configuration
COPY docker/frankenphp.ini /usr/local/etc/php/conf.d/frankenphp.ini
COPY docker/Caddyfile /etc/caddy/Caddyfile

# Copy startup script
COPY docker/scripts/start-web.sh /usr/local/bin/start-web
RUN chmod +x /usr/local/bin/start-web

# Expose port
EXPOSE 8000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8000/up || exit 1

# Start Octane
CMD ["/usr/local/bin/start-web"]

# Worker stage
FROM base AS worker

# Copy supervisor configuration
COPY docker/supervisor/horizon.conf /etc/supervisor/conf.d/horizon.conf

# Copy startup script
COPY docker/scripts/start-worker.sh /usr/local/bin/start-worker
RUN chmod +x /usr/local/bin/start-worker

# Health check for Horizon
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php artisan horizon:status | grep -q "Horizon is running" || exit 1

# Start supervisor
CMD ["/usr/local/bin/start-worker"]

# Scheduler stage
FROM base AS scheduler

# Copy cron configuration
COPY docker/cron/laravel-cron /etc/cron.d/laravel-cron

# Give execution rights on the cron job
RUN chmod 0644 /etc/cron.d/laravel-cron

# Apply cron job
RUN crontab /etc/cron.d/laravel-cron

# Copy startup script
COPY docker/scripts/start-scheduler.sh /usr/local/bin/start-scheduler
RUN chmod +x /usr/local/bin/start-scheduler

# Create the log file
RUN touch /var/log/cron.log

# Health check
HEALTHCHECK --interval=60s --timeout=3s --start-period=5s --retries=3 \
    CMD ps aux | grep -v grep | grep cron || exit 1

# Start cron
CMD ["/usr/local/bin/start-scheduler"]