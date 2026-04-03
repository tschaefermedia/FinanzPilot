# Stage 1: Build frontend assets
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm install
COPY vite.config.js ./
COPY resources/ ./resources/
RUN npm run build

# Stage 2: PHP application
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libsqlite3-dev libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_sqlite mbstring intl zip gd bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Store built assets in a safe location (volume mount overwrites /var/www/html)
COPY --from=frontend /app/public/build/ /opt/build-assets/

# Entrypoint: copy built assets into the volume-mounted public dir, then start php-fpm
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
