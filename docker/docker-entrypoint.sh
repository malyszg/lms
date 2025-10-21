#!/bin/bash
set -e

# Docker entrypoint script for LMS
# Ensures proper permissions and setup on container startup

echo "🚀 Starting LMS Application..."

# Fix permissions for cache and log directories
echo "📁 Setting up permissions..."
if [ -d "/var/www/html/var" ]; then
    chown -R www-data:www-data /var/www/html/var
    chmod -R 777 /var/www/html/var
fi

# Ensure vendor directory exists
if [ ! -d "/var/www/html/vendor" ]; then
    echo "📦 Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

echo "✅ Setup complete. Starting PHP-FPM..."

# Execute the main command (php-fpm)
exec "$@"

