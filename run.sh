#!/bin/bash
set -e

cat >/tmp/safa-php-memory.ini <<'EOF'
memory_limit=512M
upload_max_filesize=25M
post_max_size=30M
EOF
export PHPRC=/tmp/safa-php-memory.ini

mkdir -p bootstrap/cache storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chmod -R ugo+rwX bootstrap/cache storage
php artisan storage:link || true

php artisan queue:work &
php artisan migrate
php artisan db:seed
npm run build
php artisan optimize:clear
php -d memory_limit=512M -d upload_max_filesize=25M -d post_max_size=30M artisan serve --host 0.0.0.0
