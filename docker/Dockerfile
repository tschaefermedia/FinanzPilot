# Stage 1: Build frontend assets (always on native platform, not emulated)
FROM --platform=$BUILDPLATFORM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources/ ./resources/
RUN npm run build

# Stage 2: Install PHP dependencies
FROM --platform=$BUILDPLATFORM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev

# Stage 3: nginx + PHP-FPM + scheduler in one container
FROM serversideup/php:8.4-fpm-nginx-v5.0.5

USER root

RUN install-php-extensions intl gd bcmath

COPY --chmod=755 docker/entrypoint.d/ /etc/entrypoint.d/
RUN docker-php-serversideup-s6-init
COPY --chmod=755 docker/s6/scheduler/ /etc/s6-overlay/s6-rc.d/scheduler/
RUN touch /etc/s6-overlay/user-bundles.d/user/contents.d/scheduler

COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=frontend --chown=www-data:www-data /app/public/build/ /var/www/html/public/build/

# All user data lives in /data. The optional /data/.env holds the generated APP_KEY
# and any overrides; real environment variables take precedence over it.
RUN rm -rf /var/www/html/storage \
    && ln -s /data/.env /var/www/html/.env \
    && mkdir /data && chown www-data:www-data /data

ENV APP_NAME=FinanzPilot \
    APP_ENV=production \
    APP_DEBUG=false \
    APP_URL=http://localhost \
    APP_LOCALE=de \
    APP_FALLBACK_LOCALE=de \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    LARAVEL_STORAGE_PATH=/data/storage \
    BACKUP_PATH=/data/backups \
    SESSION_DRIVER=file \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync \
    LOG_LEVEL=error \
    HEALTHCHECK_PATH=/up \
    PHP_OPCACHE_ENABLE=1

USER www-data
