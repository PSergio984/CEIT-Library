#!/usr/bin/env bash
set -e

echo "Publishing Livewire assets..."
php artisan livewire:publish --assets

echo "Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "Caching config..."
php artisan config:cache

echo "Caching routes..."
php artisan route:cache

echo "Caching views..."
php artisan view:cache

echo "Linking storage..."
php artisan storage:link --force

echo "Running migrations..."
php artisan migrate --force

if [[ "${RUN_DB_SEED:-false}" = "true" ]]; then
  echo "Running seeders..."
  php artisan db:seed --force
else
  echo "Skipping seeders. Set RUN_DB_SEED=true to enable deploy-time seeding."
fi