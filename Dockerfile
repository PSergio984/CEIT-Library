FROM node:20-alpine AS builder

WORKDIR /tmp

COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build

# ===== Second stage: PHP =====
FROM richarvey/nginx-php-fpm:1.7.2

COPY --from=builder /tmp/public/build /var/www/html/public/build
COPY . .

ENV SKIP_COMPOSER 1
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1

RUN chmod +x /start.sh

CMD ["/start.sh"]
