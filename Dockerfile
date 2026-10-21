# PHP from packages.sury.org: security releases land there on release day,
# unlike the pinned nixpkgs snapshot the previous Nixpacks build used.
FROM debian:bookworm-slim AS php-base

ARG PHP_VERSION=8.2
ARG PHP_MIN_PATCH=8.2.35

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl lsb-release \
    && curl -fsSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ bookworm main" > /etc/apt/sources.list.d/sury-php.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx supervisor unzip git \
        php${PHP_VERSION}-fpm php${PHP_VERSION}-cli php${PHP_VERSION}-common \
        php${PHP_VERSION}-bcmath php${PHP_VERSION}-curl php${PHP_VERSION}-gd php${PHP_VERSION}-gmp \
        php${PHP_VERSION}-imap php${PHP_VERSION}-intl php${PHP_VERSION}-ldap php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-mysql php${PHP_VERSION}-odbc php${PHP_VERSION}-pgsql php${PHP_VERSION}-soap \
        php${PHP_VERSION}-sqlite3 php${PHP_VERSION}-xml php${PHP_VERSION}-zip php${PHP_VERSION}-opcache \
        php${PHP_VERSION}-readline \
    && rm -rf /var/lib/apt/lists/* /tmp/debsuryorg-archive-keyring.deb \
    && php -r "exit(version_compare(PHP_VERSION, '${PHP_MIN_PATCH}', '<') ? 1 : 0);" \
    && ln -s /usr/sbin/php-fpm${PHP_VERSION} /usr/local/sbin/php-fpm

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php.ini /etc/php/${PHP_VERSION}/fpm/conf.d/99-app.ini
COPY docker/php.ini /etc/php/${PHP_VERSION}/cli/conf.d/99-app.ini
COPY docker/php-fpm-pool.conf /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf

WORKDIR /app


FROM php-base AS app

COPY composer.json composer.lock ./
RUN composer install --ignore-platform-reqs --no-interaction --no-progress --no-scripts --no-autoloader

COPY . .
RUN composer install --ignore-platform-reqs --no-interaction --no-progress \
    && mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R nobody:nogroup bootstrap/cache storage


FROM node:22-bookworm-slim AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY --from=app /app ./
RUN npm run build


FROM app

COPY --from=assets /app/public/build ./public/build
COPY docker/start.sh /usr/local/bin/start-app
RUN chmod +x /usr/local/bin/start-app

EXPOSE 80
# A bare 127.0.0.1 request may not match the app host and get
# Laravel's 404 page. Any answer below 500 proves nginx, php-fpm and the app
# are serving; 502 (php-fpm down) and 500 (app error) mark the task unhealthy.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD code=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/) && [ "$code" -ge 200 ] && [ "$code" -lt 500 ]
CMD ["start-app"]
