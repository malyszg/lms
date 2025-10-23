# Multi-stage build for both development and production
ARG TARGET_STAGE=production
FROM php:8.3-fpm AS base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    librabbitmq-dev \
    libssl-dev \
    libicu-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        sockets \
        intl \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install RabbitMQ extension
RUN pecl install amqp \
    && docker-php-ext-enable amqp

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Development stage
FROM base AS development

WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock* ./

# Install all dependencies (including dev) without running scripts
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy application code
COPY . .

# Fix git ownership issue and configure git
RUN git config --global --add safe.directory /var/www/html || true

# Set permissions before running scripts
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create necessary directories with proper permissions
RUN mkdir -p var/cache var/log var/sessions \
    && chown -R www-data:www-data var \
    && chmod -R 777 var/cache var/log

# Skip composer scripts during build (they need runtime environment)
# Scripts will be run by the entrypoint script when the container starts
RUN echo "Skipping composer scripts during build - will run at runtime"

# Copy entrypoint script
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port 9000
EXPOSE 9000

# Use custom entrypoint
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

# Start PHP-FPM
CMD ["php-fpm"]

# Production stage
FROM base AS production

# Install only production dependencies
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock* ./

# Install production dependencies only without running scripts
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy application code
COPY . .

# Fix git ownership issue and configure git
RUN git config --global --add safe.directory /var/www/html || true

# Set permissions before running scripts
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create necessary directories with proper permissions
RUN mkdir -p var/cache var/log var/sessions \
    && chown -R www-data:www-data var \
    && chmod -R 755 var/cache var/log

# Skip composer scripts during build (they need runtime environment)
# Scripts will be run by the entrypoint script when the container starts
RUN echo "Skipping composer scripts during build - will run at runtime"

# Remove development files
RUN rm -rf tests/ \
    && rm -rf .git/ \
    && rm -rf .github/ \
    && rm -rf docs/ \
    && rm -rf old/ \
    && rm -rf .ai/ \
    && rm -rf .cursor/ \
    && rm -rf .idea/ \
    && rm -rf .phpunit.cache/ \
    && rm -rf var/cache/* \
    && rm -rf var/log/* \
    && rm -rf var/sessions/*

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create necessary directories with proper permissions
RUN mkdir -p var/cache var/log var/sessions \
    && chown -R www-data:www-data var \
    && chmod -R 755 var/cache var/log

# Configure PHP for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.save_comments=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/opcache.ini

# Configure PHP-FPM for production
RUN echo "pm = dynamic" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_children = 50" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.start_servers = 5" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.min_spare_servers = 5" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_spare_servers = 35" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_requests = 500" >> /usr/local/etc/php-fpm.d/www.conf

# Copy entrypoint script
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:9000/health || exit 1

# Expose port 9000
EXPOSE 9000

# Use custom entrypoint
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

# Start PHP-FPM
CMD ["php-fpm"]

# Default target
FROM ${TARGET_STAGE} AS final
