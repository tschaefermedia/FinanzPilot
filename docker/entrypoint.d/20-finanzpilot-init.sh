#!/bin/sh
set -e

if ! touch /data/.write-test 2>/dev/null; then
    echo "❌ /data is not writable for uid $(id -u). On the host run: sudo chown -R $(id -u):$(id -g) ./data"
    exit 1
fi
rm /data/.write-test

mkdir -p /data/storage/app/private/imports /data/storage/framework/cache /data/storage/framework/sessions \
    /data/storage/framework/views /data/storage/logs /data/backups

if [ -z "$APP_KEY" ] && ! grep -qs '^APP_KEY=base64:' /data/.env; then
    echo "APP_KEY=base64:$(php -r 'echo base64_encode(random_bytes(32));')" >> /data/.env
    echo "🔑 Generated APP_KEY in /data/.env. Keep it with your backups: it decrypts the stored AI API key."
fi

touch /data/database.sqlite

php "$APP_BASE_DIR/artisan" migrate --force --no-interaction
php "$APP_BASE_DIR/artisan" db:seed --force --no-interaction
php "$APP_BASE_DIR/artisan" optimize --no-interaction
