#!/bin/bash
# Same environment and setup as scripts/start-nixpacks.sh, then supervisor
# runs nginx, php-fpm and Reverb.
set -euo pipefail

export BROADCAST_DRIVER=reverb
export BROADCAST_CONNECTION=reverb
export REVERB_APP_ID="${REVERB_APP_ID:-${PUSHER_APP_ID:-talep}}"
export REVERB_APP_KEY="${REVERB_APP_KEY:-talep-reverb-key}"
export REVERB_APP_SECRET="${REVERB_APP_SECRET:-${APP_KEY:-talep-local-secret}}"
export REVERB_HOST="${REVERB_HOST:-${APP_URL:-localhost}}"
export REVERB_HOST="${REVERB_HOST#*://}"
export REVERB_PORT="${REVERB_PORT:-443}"
export REVERB_SCHEME="${REVERB_SCHEME:-https}"
export REVERB_SERVER_HOST="${REVERB_SERVER_HOST:-0.0.0.0}"
export REVERB_SERVER_PORT="${REVERB_SERVER_PORT:-8080}"

cd /app
mkdir -p /run/php /var/log/nginx /var/lib/nginx \
    bootstrap/cache storage/app/public storage/framework/cache \
    storage/framework/sessions storage/framework/views storage/logs
chmod -R a+rwX bootstrap/cache storage

if [ -e public/storage ] && [ ! -L public/storage ]; then
    if [ -z "$(find public/storage -mindepth 1 -maxdepth 1 -print -quit 2>/dev/null)" ]; then
        rm -rf public/storage
    else
        mv public/storage "public/storage.backup.$(date +%s)"
    fi
fi
ln -sfn /app/storage/app/public public/storage

php artisan optimize:clear || true

php-fpm -t
nginx -t

exec supervisord -c /etc/supervisor/supervisord.conf
