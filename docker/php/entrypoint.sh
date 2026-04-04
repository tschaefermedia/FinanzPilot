#!/bin/sh

# If public/index.php is missing, this is an empty volume — sync app files
if [ ! -f /var/www/html/public/index.php ]; then
    echo "Initializing application files..."
    cp -r /opt/app-source/. /var/www/html/
fi

# Always sync built frontend assets (they live in the image, not the volume)
mkdir -p /var/www/html/public/build
cp -r /opt/app-source/public/build/. /var/www/html/public/build/

# Create .env from example if it doesn't exist
if [ ! -f /var/www/html/.env ]; then
    echo "Creating .env from .env.example..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Generate app key if not set
if grep -q "^APP_KEY=" /var/www/html/.env && ! grep -q "^APP_KEY=base64:" /var/www/html/.env; then
    KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")
    sed -i "s|^APP_KEY=.*|APP_KEY=${KEY}|" /var/www/html/.env
    echo "Application key generated: ${KEY}"
fi

# Apply environment variable overrides to .env
for var in APP_NAME APP_ENV APP_DEBUG APP_URL DB_CONNECTION SESSION_DRIVER CACHE_STORE QUEUE_CONNECTION LOG_CHANNEL LOG_LEVEL; do
    eval val=\$$var
    if [ -n "$val" ]; then
        if grep -q "^${var}=" /var/www/html/.env; then
            sed -i "s|^${var}=.*|${var}=${val}|" /var/www/html/.env
        else
            echo "${var}=${val}" >> /var/www/html/.env
        fi
    fi
done

# Ensure storage and cache directories exist
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/app/exports
mkdir -p /var/www/html/storage/app/imports
mkdir -p /var/www/html/bootstrap/cache
mkdir -p /var/www/html/database

# Create SQLite database if it doesn't exist
touch /var/www/html/database/database.sqlite

# Fix ALL permissions before running any PHP
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database /var/www/html/.env

# Run migrations
echo "Running database migrations..."
php /var/www/html/artisan migrate --force --no-interaction
if [ $? -ne 0 ]; then
    echo "WARNING: Database migrations failed. The application may not work correctly."
fi

# Seed only on fresh install (no categories exist yet)
CATEGORY_COUNT=$(php /var/www/html/artisan tinker --execute="echo \App\Models\Category::count();" 2>/dev/null | tr -d '[:space:]')
if [ "$CATEGORY_COUNT" = "0" ] || [ -z "$CATEGORY_COUNT" ]; then
    echo "Fresh install detected — seeding default categories..."
    php /var/www/html/artisan db:seed --no-interaction --force
fi

# Set up Laravel scheduler cron job
echo "* * * * * cd /var/www/html && php artisan schedule:run >> /var/www/html/storage/logs/scheduler.log 2>&1" | crontab -u www-data -
echo "Starting cron daemon for Laravel scheduler..."
cron

exec "$@"
