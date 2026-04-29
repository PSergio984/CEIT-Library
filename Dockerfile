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

RUN printf '[www]\nuser = nginx\ngroup = nginx\npm = dynamic\npm.max_children = 10\npm.start_servers = 2\npm.min_spare_servers = 1\npm.max_spare_servers = 3\npm.process_idle_timeout = 10s\nlisten = /var/run/php-fpm.sock\nlisten.owner = nginx\nlisten.group = nginx\nlisten.mode = 0660\n' > /usr/local/etc/php-fpm.d/www.conf

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
