#!/bin/bash
set -e

echo "Starting Railway build process..."

# Install PHP extensions
echo "Installing PHP extensions..."
install-php-extensions gd || echo "GD extension installation failed, continuing..."

# Install Composer dependencies ignoring platform requirements
echo "Installing Composer dependencies..."
composer install --optimize-autoloader --no-scripts --no-interaction --ignore-platform-reqs

# Install Node dependencies
echo "Installing Node dependencies..."
npm install

# Build frontend assets
echo "Building frontend assets..."
npm run build

# Create Laravel directories
echo "Setting up Laravel directories..."
mkdir -p storage/framework/{sessions,views,cache,testing} storage/logs bootstrap/cache
chmod -R a+rw storage

# Cache Laravel configuration
echo "Caching Laravel configuration..."
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

echo "Build process completed successfully!"