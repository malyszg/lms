FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    librabbitmq-dev \
    libssl-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip sockets

# Install RabbitMQ extension
RUN pecl install amqp \
    && docker-php-ext-enable amqp

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock* ./

# Install PHP dependencies (only if composer.lock exists)
RUN if [ -f composer.lock ]; then composer install --no-dev --optimize-autoloader --no-interaction; else composer install --no-interaction; fi || true

# Copy application code
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create necessary directories with proper permissions
RUN mkdir -p var/cache var/log var/sessions \
    && chown -R www-data:www-data var \
    && chmod -R 777 var/cache var/log

# Copy entrypoint script
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port 9000
EXPOSE 9000

# Use custom entrypoint
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

# Start PHP-FPM
CMD ["php-fpm"]
