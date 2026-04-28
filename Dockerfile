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

# Copy ALL app files first
COPY . /var/www/html

# Then overwrite with built assets from builder
COPY --from=builder /tmp/public/build /var/www/html/public/build

# Now composer install has everything it needs
RUN composer install --no-dev --no-interaction --optimize-autoloader --working-dir=/var/www/html

# Copy startup script
COPY Docker/start.sh /start.sh
RUN chmod +x /start.sh

ENV SKIP_COMPOSER 1
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1
ENV APP_ENV production
ENV APP_DEBUG false
ENV LOG_CHANNEL stderr
ENV COMPOSER_ALLOW_SUPERUSER 1

CMD ["/start.sh"]