#!/usr/bin/env bash
set -euo pipefail

mkdir -p /tmp/safa-php-conf
cat > /tmp/safa-php-conf/limits.ini <<'EOF'
memory_limit = 512M
post_max_size = 30M
upload_max_filesize = 25M
EOF
export PHPRC=/tmp/safa-php-conf/limits.ini

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

mkdir -p \
    bootstrap/cache \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

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

if ! grep -q 'memory_limit' /assets/php-fpm.conf; then
    cat >> /assets/php-fpm.conf <<'EOF'

php_admin_value[memory_limit] = 512M
EOF
fi

if ! grep -q 'upload_max_filesize' /assets/php-fpm.conf; then
    cat >> /assets/php-fpm.conf <<'EOF'
php_admin_value[upload_max_filesize] = 25M
php_admin_value[post_max_size] = 30M
EOF
fi

node /assets/scripts/prestart.mjs /assets/nginx.template.conf /nginx.conf

if ! grep -q 'client_max_body_size' /nginx.conf; then
    sed -i '/server_name localhost;/a \        client_max_body_size 25m;' /nginx.conf
fi

if ! grep -q "proxy_pass http://127.0.0.1:${REVERB_SERVER_PORT};" /nginx.conf; then
    cat > /tmp/reverb-nginx-location.conf <<'NGINX'

        location ~ ^/(app|apps)/ {
            proxy_http_version 1.1;
            proxy_set_header Host $host;
            proxy_set_header X-Forwarded-Host $host;
            proxy_set_header X-Forwarded-Proto $scheme;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "Upgrade";
            proxy_pass http://127.0.0.1:__REVERB_SERVER_PORT__;
        }
NGINX
    sed -i "s/__REVERB_SERVER_PORT__/${REVERB_SERVER_PORT}/g" /tmp/reverb-nginx-location.conf
    sed -i "/server_name localhost;/r /tmp/reverb-nginx-location.conf" /nginx.conf
fi

php artisan reverb:start --host="${REVERB_SERVER_HOST:-0.0.0.0}" --port="${REVERB_SERVER_PORT:-8080}" &
php-fpm -y /assets/php-fpm.conf &
nginx -c /nginx.conf
