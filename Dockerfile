# Build stage for assets
FROM node:20-alpine AS builder

WORKDIR /tmp

COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build

# Production stage
FROM richarvey/nginx-php-fpm:latest

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
