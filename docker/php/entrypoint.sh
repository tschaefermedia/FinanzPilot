#!/bin/sh

# If public/index.php is missing, this is an empty volume — sync app files
if [ ! -f /var/www/html/public/index.php ]; then
    echo "Initializing application files..."
    cp -r /opt/app-source/. /var/www/html/
fi

# Always sync built frontend assets (they live in the image, not the volume)
mkdir -p /var/www/html/public/build
cp -r /opt/app-source/public/build/. /var/www/html/public/build/

# Create .env from example if it doesn't exist and no bind mount provided
if [ ! -f /var/www/html/.env ]; then
    echo "Creating .env from .env.example..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Apply environment variable overrides to .env
# Any Docker env var starting with APP_, DB_, SESSION_, CACHE_, or LOG_ overrides the .env value
for var in APP_NAME APP_ENV APP_DEBUG APP_URL APP_KEY DB_CONNECTION SESSION_DRIVER CACHE_STORE QUEUE_CONNECTION LOG_CHANNEL LOG_LEVEL; do
    eval val=\$$var
    if [ -n "$val" ]; then
        if grep -q "^${var}=" /var/www/html/.env; then
            sed -i "s|^${var}=.*|${var}=${val}|" /var/www/html/.env
        else
            echo "${var}=${val}" >> /var/www/html/.env
        fi
    fi
done

# Ensure storage and cache directories exist and are writable
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

# Fix permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database /var/www/html/.env

# Generate app key if not set
if grep -q "^APP_KEY=$" /var/www/html/.env; then
    php /var/www/html/artisan key:generate --force
fi

# Run migrations automatically
php /var/www/html/artisan migrate --force 2>/dev/null

exec "$@"
