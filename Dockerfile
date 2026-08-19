# Stage 1: Install Composer dependencies
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-req=ext-gd \
    --ignore-platform-req=ext-imagick

# Stage 2: Build frontend assets
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package*.json ./
RUN npm ci

# Copy full application and vendor so Tailwind can scan MaryUI and Blade components
COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN npm run build

# Stage 3: Production runtime
FROM php:8.4-fpm-alpine

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
    icu-libs \
    imagemagick \
    imagemagick-libs \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
        postgresql-dev \
        icu-dev \
        imagemagick-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip \
    && printf "\n" | pecl install imagick \
    && docker-php-ext-enable imagick \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# Copy composer binary
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application source code
COPY . /var/www/html

# Copy vendor dependencies from vendor stage
COPY --from=vendor /app/vendor /var/www/html/vendor

# Copy compiled frontend assets from frontend stage
COPY --from=frontend /app/public/build /var/www/html/public/build

# Generate optimized autoload with app classes
RUN composer dump-autoload --optimize --no-dev --working-dir=/var/www/html

# Setup configuration
COPY Docker/nginx.conf /etc/nginx/http.d/default.conf
COPY Docker/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY Docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Adjust permissions for Laravel storage and cache
RUN chown -R nginx:nginx /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod +x /var/www/html/Docker/start.sh

ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr
ENV COMPOSER_ALLOW_SUPERUSER=1

EXPOSE 80

CMD ["/bin/bash", "/var/www/html/Docker/start.sh"]
