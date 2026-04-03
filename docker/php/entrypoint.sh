#!/bin/sh
# Copy built frontend assets into the volume-mounted public directory
mkdir -p /var/www/html/public/build
cp -r /opt/build-assets/* /var/www/html/public/build/

# Ensure storage and cache directories are writable
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/{cache,sessions,views}
mkdir -p /var/www/html/storage/app/{exports,imports}
mkdir -p /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Create SQLite database if it doesn't exist
touch /var/www/html/database/database.sqlite
chown www-data:www-data /var/www/html/database/database.sqlite

exec "$@"
