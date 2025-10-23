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

# Run composer scripts at runtime (when environment variables are available)
echo "🔧 Running Composer scripts..."
if [ -n "$DATABASE_URL" ]; then
    echo "🗄️  Database URL found, running cache:clear..."
    php bin/console cache:clear --no-interaction || echo "⚠️  Cache clear failed, continuing..."
else
    echo "⚠️  No DATABASE_URL found, skipping cache:clear"
fi

# Install assets
echo "📦 Installing assets..."
php bin/console assets:install public --no-interaction || echo "⚠️  Assets install failed, continuing..."

echo "✅ Setup complete. Starting PHP-FPM..."

# Execute the main command (php-fpm)
exec "$@"

