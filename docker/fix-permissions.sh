#!/bin/bash
#
# Fix permissions for Symfony var/ directory
# This should be run when encountering permission errors
#

set -e

echo "🔧 Fixing Symfony permissions..."

# Fix ownership
docker-compose -f docker/docker-compose.yml exec -T app chown -R www-data:www-data \
    /var/www/html/var/cache \
    /var/www/html/var/log \
    /var/www/html/var/sessions

echo "🧹 Clearing cache as www-data..."

# Clear cache as www-data user
docker-compose -f docker/docker-compose.yml exec -T app su -s /bin/bash www-data -c "php bin/console cache:clear"

echo "✅ Permissions fixed successfully!"
echo ""
echo "💡 Tip: Always run 'cache:clear' as www-data user:"
echo "   docker-compose -f docker/docker-compose.yml exec app su -s /bin/bash www-data -c 'php bin/console cache:clear'"

