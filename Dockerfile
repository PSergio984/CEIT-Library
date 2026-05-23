# Build stage for assets
FROM node:20-alpine AS builder

WORKDIR /tmp

COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build

# Production stage
FROM php:8.5-fpm-alpine

# Install nginx, supervisor, and system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    bash \
    libpq \
    libzip \
    freetype \
    libjpeg-turbo \
    libpng \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
        postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo_pgsql \
        pgsql \
        zip \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# Copy composer from the official composer image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . /var/www/html
COPY --from=builder /tmp/public/build /var/www/html/public/build

# Setup configuration
COPY Docker/nginx.conf /etc/nginx/http.d/default.conf
COPY Docker/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY Docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Adjust permissions for Laravel storage and cache
RUN chown -R nginx:nginx /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod +x /var/www/html/Docker/start.sh

RUN composer install --no-dev --no-interaction --optimize-autoloader --working-dir=/var/www/html

ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1
ENV APP_ENV production
ENV APP_DEBUG false
ENV LOG_CHANNEL stderr
ENV COMPOSER_ALLOW_SUPERUSER 1

EXPOSE 80

CMD ["/bin/bash", "/var/www/html/Docker/start.sh"]
