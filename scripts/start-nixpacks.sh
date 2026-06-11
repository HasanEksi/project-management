#!/usr/bin/env bash
set -euo pipefail

mkdir -p /tmp/safa-php-conf
cat > /tmp/safa-php-conf/limits.ini <<'EOF'
memory_limit = 512M
post_max_size = 30M
upload_max_filesize = 25M
EOF
export PHPRC=/tmp/safa-php-conf/limits.ini

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

php-fpm -y /assets/php-fpm.conf &
nginx -c /nginx.conf
