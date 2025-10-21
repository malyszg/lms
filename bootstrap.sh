#!/bin/bash

# Bootstrap script for LMS project
# This script sets up the initial project structure and installs dependencies

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
check_directory() {
    if [ ! -d "src" ]; then
        print_error "This script must be run from the project root directory"
        exit 1
    fi
    print_success "Running from correct directory"
}

# Create necessary directories
create_directories() {
    print_status "Creating project directories..."
    
    # Create Symfony standard directories
    mkdir -p public
    mkdir -p var/cache
    mkdir -p var/log
    mkdir -p var/sessions
    mkdir -p config
    mkdir -p migrations
    mkdir -p tests
    
    # Create additional directories for LMS
    mkdir -p src/ApiClient
    mkdir -p src/Leads
    mkdir -p src/Model
    mkdir -p src/Webhooks
    mkdir -p src/Command
    
    print_success "Directories created"
}

# Create composer.json if it doesn't exist
create_composer_json() {
    if [ ! -f "composer.json" ]; then
        print_status "Creating composer.json..."
        
        cat > composer.json << 'EOF'
{
    "name": "lms/lead-management-system",
    "type": "project",
    "description": "Lead Management System for real estate applications",
    "license": "proprietary",
    "minimum-stability": "stable",
    "prefer-stable": true,
    "require": {
        "php": ">=8.3",
        "symfony/console": "^7.3",
        "symfony/framework-bundle": "^7.3",
        "symfony/http-foundation": "^7.3",
        "symfony/http-kernel": "^7.3",
        "symfony/routing": "^7.3",
        "symfony/security-bundle": "^7.3",
        "symfony/validator": "^7.3",
        "symfony/serializer": "^7.3",
        "symfony/property-access": "^7.3",
        "symfony/property-info": "^7.3",
        "doctrine/orm": "^3.0",
        "doctrine/doctrine-bundle": "^2.12",
        "doctrine/doctrine-migrations-bundle": "^3.3",
        "doctrine/dbal": "^4.0",
        "ramsey/uuid": "^4.7",
        "guzzlehttp/guzzle": "^7.8",
        "php-amqplib/php-amqplib": "^3.2",
        "monolog/monolog": "^3.5"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5",
        "symfony/phpunit-bridge": "^7.3"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "App\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "post-install-cmd": [
            "@auto-scripts"
        ],
        "post-update-cmd": [
            "@auto-scripts"
        ],
        "auto-scripts": {
            "cache:clear": "symfony-cmd",
            "assets:install %PUBLIC_DIR%": "symfony-cmd"
        }
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": {
            "*": "dist"
        },
        "sort-packages": true,
        "allow-plugins": {
            "php-http/discovery": true,
            "symfony/runtime": true,
            "symfony/flex": true
        }
    }
}
EOF
        
        print_success "composer.json created"
    else
        print_success "composer.json already exists"
    fi
}

# Install Composer dependencies
install_dependencies() {
    print_status "Installing Composer dependencies..."
    
    if command -v composer &> /dev/null; then
        composer install --no-interaction --optimize-autoloader
        print_success "Dependencies installed with Composer"
    else
        print_warning "Composer not found. Please install Composer first."
        print_status "You can install dependencies later with: composer install"
    fi
}

# Create basic Symfony configuration files
create_symfony_config() {
    print_status "Creating Symfony configuration files..."
    
    # Create config/services.yaml
    if [ ! -f "config/services.yaml" ]; then
        cat > config/services.yaml << 'EOF'
services:
    _defaults:
        autowire: true
        autoconfigure: true

    App\:
        resource: '../src/'
        exclude:
            - '../src/DependencyInjection/'
            - '../src/Entity/'
            - '../src/Kernel.php'

    App\Controller\:
        resource: '../src/Controller/'
        tags: ['controller.service_arguments']

    App\Command\:
        resource: '../src/Command/'
        tags: ['console.command']
EOF
        print_success "config/services.yaml created"
    fi
    
    # Create config/packages/doctrine.yaml
    mkdir -p config/packages
    if [ ! -f "config/packages/doctrine.yaml" ]; then
        cat > config/packages/doctrine.yaml << 'EOF'
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
        charset: utf8mb4
        default_table_options:
            charset: utf8mb4
            collate: utf8mb4_unicode_ci

    orm:
        auto_generate_proxy_classes: true
        enable_lazy_ghost_objects: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        mappings:
            App:
                is_bundle: false
                dir: '%kernel.project_dir%/src/Model'
                prefix: 'App\Model'
                alias: App
EOF
        print_success "config/packages/doctrine.yaml created"
    fi
}

# Create .env file
create_env_file() {
    if [ ! -f ".env" ]; then
        print_status "Creating .env file..."
        
        cat > .env << 'EOF'
# Environment
APP_ENV=dev
APP_SECRET=your-secret-key-change-this-in-production

# Database Configuration
MYSQL_ROOT_PASSWORD=your-mysql-root-password
MYSQL_DATABASE=lms_db
MYSQL_USER=lms_user
MYSQL_PASSWORD=your-mysql-password
DATABASE_URL="mysql://${MYSQL_USER}:${MYSQL_PASSWORD}@localhost:3306/${MYSQL_DATABASE}?serverVersion=9.4&charset=utf8mb4"

# RabbitMQ Configuration
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=your-rabbitmq-password
RABBITMQ_URL="amqp://${RABBITMQ_USER}:${RABBITMQ_PASSWORD}@localhost:5672/%2f"

# Logging
LOG_LEVEL=info

# Security
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your-jwt-passphrase

# CDP Systems
SALESMANAGO_API_URL=https://api.salesmanago.com
SALESMANAGO_API_KEY=your-salesmanago-api-key
SALESMANAGO_SECRET=your-salesmanago-secret

MURAPOL_API_URL=https://api.murapol.pl
MURAPOL_API_KEY=your-murapol-api-key

DOMDEVELOPMENT_API_URL=https://api.domdevelopment.pl
DOMDEVELOPMENT_API_KEY=your-domdevelopment-api-key
EOF
        
        print_success ".env file created"
    else
        print_success ".env file already exists"
    fi
}

# Create public/index.php
create_index_php() {
    if [ ! -f "public/index.php" ]; then
        print_status "Creating public/index.php..."
        
        cat > public/index.php << 'EOF'
<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
EOF
        
        print_success "public/index.php created"
    else
        print_success "public/index.php already exists"
    fi
}

# Create basic Kernel.php
create_kernel() {
    if [ ! -f "src/Kernel.php" ]; then
        print_status "Creating src/Kernel.php..."
        
        cat > src/Kernel.php << 'EOF'
<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
EOF
        
        print_success "src/Kernel.php created"
    else
        print_success "src/Kernel.php already exists"
    fi
}

# Set proper permissions
set_permissions() {
    print_status "Setting proper permissions..."
    
    chmod -R 755 var/
    chmod -R 755 public/
    
    print_success "Permissions set"
}

# Main execution
main() {
    echo "🚀 Starting LMS Bootstrap Setup..."
    echo ""
    
    check_directory
    create_directories
    create_composer_json
    create_symfony_config
    create_env_file
    create_index_php
    create_kernel
    set_permissions
    install_dependencies
    
    echo ""
    print_success "Bootstrap setup completed! 🎉"
    echo ""
    echo "Next steps:"
    echo "  1. Update .env file with your actual configuration"
    echo "  2. Run database migrations: php bin/console doctrine:migrations:migrate"
    echo "  3. Start the development server: symfony serve"
    echo "  4. Or use Docker: ./docker/run.sh"
    echo ""
}

# Run main function
main "$@"
