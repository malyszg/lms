#!/bin/bash

# LMS Docker Setup Script
# This script sets up and runs the LMS project in Docker

set -e

# Store the script directory at the beginning
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

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

# Check if Docker is installed
check_docker() {
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed. Please install Docker first."
        exit 1
    fi
    
    if ! command -v docker-compose &> /dev/null; then
        print_error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi
    
    print_success "Docker and Docker Compose are installed"
}

# Run bootstrap if needed
run_bootstrap() {
    if [ ! -f "$PROJECT_ROOT/composer.json" ] || [ ! -d "$PROJECT_ROOT/vendor" ] || [ ! -f "$PROJECT_ROOT/.env" ]; then
        print_status "Running bootstrap to set up project structure..."
        
        if [ -f "$PROJECT_ROOT/bootstrap.sh" ]; then
            cd "$PROJECT_ROOT"
            chmod +x bootstrap.sh
            ./bootstrap.sh
            # Return to docker directory
            cd "$SCRIPT_DIR"
            print_success "Bootstrap completed"
        else
            print_error "bootstrap.sh not found in project root: $PROJECT_ROOT"
            exit 1
        fi
    else
        print_success "Project structure already exists"
    fi
}

# Check if .env file exists
check_env_file() {
    if [ ! -f "$PROJECT_ROOT/.env" ]; then
        print_warning ".env file not found. Creating from .env.example..."
        if [ -f "$PROJECT_ROOT/.env.example" ]; then
            cp "$PROJECT_ROOT/.env.example" "$PROJECT_ROOT/.env"
            print_success ".env file created from .env.example"
        else
            print_error ".env.example file not found. Please create .env file manually."
            exit 1
        fi
    else
        print_success ".env file exists"
    fi
}

# Create necessary directories
create_directories() {
    print_status "Creating necessary directories..."
    
    mkdir -p "$PROJECT_ROOT/var/cache"
    mkdir -p "$PROJECT_ROOT/var/log"
    mkdir -p "$PROJECT_ROOT/var/sessions"
    mkdir -p "$PROJECT_ROOT/public"
    
    print_success "Directories created"
}

# Build and start containers
start_containers() {
    print_status "Building and starting Docker containers..."
    
    # Run docker-compose from project root to properly read .env file
    cd "$PROJECT_ROOT"
    
    # Stop existing containers
    print_status "Stopping existing containers..."
    docker-compose -f docker/docker-compose.yml down --remove-orphans
    
    # Build and start containers
    print_status "Building containers..."
    docker-compose -f docker/docker-compose.yml build --no-cache
    
    print_status "Starting containers..."
    docker-compose -f docker/docker-compose.yml up -d
    
    print_success "Containers started successfully"
}

# Wait for services to be ready
wait_for_services() {
    print_status "Waiting for services to be ready..."
    
    # Wait for MySQL
    print_status "Waiting for MySQL..."
    timeout=60
    while ! docker-compose -f docker/docker-compose.yml exec mysql mysqladmin ping -h localhost --silent; do
        sleep 2
        timeout=$((timeout - 2))
        if [ $timeout -le 0 ]; then
            print_error "MySQL failed to start within 60 seconds"
            exit 1
        fi
    done
    print_success "MySQL is ready"
    
    # Verify test database exists (created by MySQL init script)
    verify_test_database() {
        print_status "Verifying test database setup..."
        
        # Wait longer for MySQL to be fully ready
        sleep 5
        
        # Check if test database exists and is accessible (suppress all output)
        if docker-compose -f docker/docker-compose.yml exec mysql mysql -u lms_user -plms_password -e "USE lms_db_test; SELECT 'Test database ready' AS status;" >/dev/null 2>&1; then
            print_success "Test database lms_db_test is ready"
        else
            print_warning "Test database not accessible - will be created during migration"
        fi
    }
    
    verify_test_database
    
    # Wait for RabbitMQ
    print_status "Waiting for RabbitMQ..."
    timeout=60
    while ! docker-compose -f docker/docker-compose.yml exec rabbitmq rabbitmq-diagnostics -q ping; do
        sleep 2
        timeout=$((timeout - 2))
        if [ $timeout -le 0 ]; then
            print_error "RabbitMQ failed to start within 60 seconds"
            exit 1
        fi
    done
    print_success "RabbitMQ is ready"
}

# Run database migrations
run_migrations() {
    print_status "Running database migrations..."
    
    # Wait a bit more for MySQL to be fully ready
    sleep 5
    
    # Run migrations for production database
    if docker-compose -f docker/docker-compose.yml exec app php bin/console doctrine:migrations:migrate --no-interaction; then
        print_success "Production database migrations completed"
    else
        print_warning "No migrations found or migration failed for production database"
    fi
    
    # Ensure test database exists before running test migrations
    print_status "Ensuring test database is ready for migrations..."
    
    # Verify test database is accessible (created by MySQL init script) - suppress all output
    if docker-compose -f docker/docker-compose.yml exec mysql mysql -u lms_user -plms_password -e "USE lms_db_test; SELECT 'Ready for migrations' AS status;" >/dev/null 2>&1; then
        print_success "Test database ready for migrations"
    else
        print_warning "Test database not accessible - migrations may fail"
    fi
    
    # Run migrations for test database
    print_status "Running test database migrations..."
    if docker-compose -f docker/docker-compose.yml exec -e DATABASE_URL="mysql://lms_user:lms_password@mysql:3306/lms_db_test" app php bin/console doctrine:migrations:migrate --env=test --no-interaction; then
        print_success "Test database migrations completed"
    else
        print_warning "No migrations found or migration failed for test database"
    fi
}

# Install dependencies
install_dependencies() {
    print_status "Checking PHP dependencies..."
    
    # Check if vendor directory exists and has content
    if [ -d "$PROJECT_ROOT/vendor" ] && [ "$(ls -A "$PROJECT_ROOT/vendor" 2>/dev/null)" ]; then
        print_success "Dependencies already installed"
    else
        print_status "Installing PHP dependencies..."
        
        if docker-compose -f docker/docker-compose.yml exec app composer install --no-interaction --optimize-autoloader; then
            print_success "Dependencies installed"
        else
            print_error "Failed to install dependencies"
            exit 1
        fi
    fi
}

# Fix cache and log permissions
fix_permissions() {
    print_status "Fixing cache and log permissions..."
    
    docker-compose -f docker/docker-compose.yml exec app chmod -R 777 /var/www/html/var/cache
    docker-compose -f docker/docker-compose.yml exec app chmod -R 777 /var/www/html/var/log
    
    print_success "Permissions fixed"
}

# Clear cache
clear_cache() {
    print_status "Clearing application cache..."
    
    docker-compose -f docker/docker-compose.yml exec app php bin/console cache:clear --env=dev
    docker-compose -f docker/docker-compose.yml exec app php bin/console cache:warmup --env=dev
    
    # Fix permissions again after cache operations
    docker-compose -f docker/docker-compose.yml exec app chmod -R 777 /var/www/html/var/cache /var/www/html/var/log
    
    print_success "Cache cleared and warmed up"
}

# Show service URLs
show_urls() {
    echo ""
    print_success "LMS Project is now running!"
    echo ""
    echo "Service URLs:"
    echo "  🌐 Application:     http://localhost:8082"
    echo "  🗄️  phpMyAdmin:     http://localhost:8081"
    echo "  🐰 RabbitMQ Admin:  http://localhost:15672"
    echo ""
    echo "Credentials:"
    echo "  MySQL:"
    echo "    Host: localhost"
    echo "    Port: 3306"
    echo "    Production Database: lms_db"
    echo "    Test Database: lms_db_test"
    echo "    Username: lms_user"
    echo "    Password: [Check your .env file for MYSQL_PASSWORD]"
    echo ""
    echo "  RabbitMQ:"
    echo "    Username: guest"
    echo "    Password: [Check your .env file for RABBITMQ_PASSWORD]"
    echo ""
    echo "Useful commands:"
    echo "  📋 View logs:       docker-compose -f docker/docker-compose.yml logs -f"
    echo "  🛑 Stop services:   docker-compose -f docker/docker-compose.yml down"
    echo "  🔄 Restart:         docker-compose -f docker/docker-compose.yml restart"
    echo "  🧹 Clean up:        docker-compose -f docker/docker-compose.yml down -v --remove-orphans"
    echo ""
}

# Main execution
main() {
    echo "🚀 Starting LMS Docker Setup..."
    echo ""
    
    check_docker
    run_bootstrap
    check_env_file
    create_directories
    start_containers
    wait_for_services
    install_dependencies
    fix_permissions
    run_migrations
    clear_cache
    show_urls
    
    print_success "Setup completed successfully! 🎉"
}

# Run main function
main "$@"
