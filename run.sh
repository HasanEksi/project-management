#!/bin/bash
set -e

cat >/tmp/safa-php-memory.ini <<'EOF'
memory_limit=512M
upload_max_filesize=25M
post_max_size=30M
EOF
export PHPRC=/tmp/safa-php-memory.ini

export BROADCAST_DRIVER=reverb
export BROADCAST_CONNECTION=reverb
export REVERB_APP_ID="${REVERB_APP_ID:-${PUSHER_APP_ID:-talep}}"
export REVERB_APP_KEY="${REVERB_APP_KEY:-${PUSHER_APP_KEY:-${APP_KEY:-talep-local-key}}}"
export REVERB_APP_SECRET="${REVERB_APP_SECRET:-${PUSHER_APP_SECRET:-${APP_KEY:-talep-local-secret}}}"
export REVERB_HOST="${REVERB_HOST:-${APP_URL:-localhost}}"
export REVERB_HOST="${REVERB_HOST#*://}"
export REVERB_PORT="${REVERB_PORT:-443}"
export REVERB_SCHEME="${REVERB_SCHEME:-https}"
export REVERB_SERVER_HOST="${REVERB_SERVER_HOST:-0.0.0.0}"
export REVERB_SERVER_PORT="${REVERB_SERVER_PORT:-8080}"

mkdir -p bootstrap/cache storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chmod -R ugo+rwX bootstrap/cache storage
php artisan storage:link || true
php artisan optimize:clear || true

php artisan queue:work &
php artisan reverb:start --host="${REVERB_SERVER_HOST:-0.0.0.0}" --port="${REVERB_SERVER_PORT:-8080}" &
php artisan migrate
php artisan db:seed
npm run build
php artisan optimize:clear
php -d memory_limit=512M -d upload_max_filesize=25M -d post_max_size=30M artisan serve --host 0.0.0.0
