# Build stage for assets
FROM node:20-alpine AS builder

WORKDIR /tmp

COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build

# Production stage
FROM richarvey/nginx-php-fpm:latest

# Ensure we use PHP 8.5
RUN apk add --no-cache php85 php85-fpm php85-mysqli php85-json php85-openssl php85-curl php85-zlib php85-xml php85-phar php85-intl php85-dom php85-xmlreader php85-ctype php85-session php85-mbstring php85-gd php85-xmlwriter php85-tokenizer php85-fileinfo php85-simplexml php85-xmlrpc php85-soap php85-zip php85-iconv php85-sqlite3 php85-pdo_sqlite php85-pdo_pgsql php85-pgsql


WORKDIR /var/www/html

COPY . /var/www/html
COPY --from=builder /tmp/public/build /var/www/html/public/build

COPY Docker/nginx.conf /etc/nginx/sites-available/default.conf

RUN composer install --no-dev --no-interaction --optimize-autoloader --working-dir=/var/www/html

ENV SKIP_COMPOSER 1
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1
ENV APP_ENV production
ENV APP_DEBUG false
ENV LOG_CHANNEL stderr
ENV COMPOSER_ALLOW_SUPERUSER 1
