#!/usr/bin/env bash
set -e

echo "Running composer"
composer install --no-dev --working-dir=/var/www/html

echo "Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "Caching config..."
php artisan config:cache

echo "Caching routes..."
php artisan route:cache
echo "Caching views..."
# Ensure storage link exists, including replacing broken symlinks
php artisan storage:link --force
	php artisan storage:link
fi

echo "Running migrations..."
php artisan migrate --force
