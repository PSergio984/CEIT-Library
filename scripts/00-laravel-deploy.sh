#!/bin/bash
set -e

echo "Deployment started ..."

cd /var/www/html

# Install dependencies
echo "Installing composer dependencies ..."
composer install --no-dev --no-interaction --prefer-dist

# Install Node dependencies
echo "Installing npm dependencies ..."
npm ci

# Build frontend assets
echo "Building frontend assets ..."
npm run build

# Clear caches
echo "Clearing caches ..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Cache configuration
echo "Caching configuration ..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create app key if not exists
if [ -z "$APP_KEY" ]; then
    echo "Generating application key ..."
    php artisan key:generate --show
fi

# Run database migrations
echo "Running database migrations ..."
php artisan migrate --force

# Set permissions
echo "Setting permissions ..."
chmod -R 755 /var/www/html/storage
chmod -R 755 /var/www/html/bootstrap/cache

echo "Deployment finished!"
