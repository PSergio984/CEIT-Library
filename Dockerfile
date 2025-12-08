# Build stage for assets
FROM node:20-alpine AS builder

WORKDIR /tmp

COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build

# Production stage
FROM richarvey/nginx-php-fpm:latest

# Copy built assets from builder stage
COPY --from=builder /tmp/public/build /var/www/html/public/build

# Copy application files
COPY . .

# Image config
ENV SKIP_COMPOSER 1
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1

# Laravel config
ENV APP_ENV production
ENV APP_DEBUG false
ENV LOG_CHANNEL stderr

# Allow composer to run as root
ENV COMPOSER_ALLOW_SUPERUSER 1

CMD ["/start.sh"]
