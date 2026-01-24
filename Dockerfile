# syntax=docker/dockerfile:1

#Deriving the latest base image
FROM node:16.17.0-bullseye-slim

# Any working directory can be chosen as per choice like '/' or '/home' etc
WORKDIR /app

COPY .env.example .env

COPY . .

RUN apt-get update -y && \
    apt-get install -y --no-install-recommends software-properties-common gnupg2 wget && \
    echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/sury-php.list && \
    wget -qO - https://packages.sury.org/php/apt.gpg | apt-key add - && \
    apt-get update -y && \
    apt-get install -y --no-install-recommends php8.2 php8.2-curl php8.2-xml php8.2-zip php8.2-gd php8.2-mbstring php8.2-mysql && \
    apt-get update -y && \
    apt-get install -y composer && \
    composer update && \
    composer install && \
    npm install && \
    php artisan key:generate && \
    rm -rf /var/lib/apt/lists/*

CMD [ "bash", "./run.sh"]
