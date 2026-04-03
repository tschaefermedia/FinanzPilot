#!/bin/sh
# Copy built frontend assets into the volume-mounted public directory
mkdir -p /var/www/html/public/build
cp -r /opt/build-assets/* /var/www/html/public/build/

exec "$@"
