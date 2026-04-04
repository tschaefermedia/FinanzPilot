# Stage 1: Build frontend assets
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources/ ./resources/
RUN npm run build

# Stage 2: Install PHP dependencies
FROM composer:latest AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize

# Stage 3: PHP application
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libsqlite3-dev libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev unzip git cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_sqlite mbstring intl zip gd bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy application code
COPY --from=vendor /app /var/www/html

# Copy built frontend assets
COPY --from=frontend /app/public/build/ /var/www/html/public/build/

# Store a copy of the app for the entrypoint to sync into volumes
RUN cp -r /var/www/html /opt/app-source

# Entrypoint
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php-fpm -t 2>&1 || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
