# Lead Management System (LMS)

A centralized system for managing real estate leads from various applications, designed to solve data fragmentation and improve lead deliverability by 70%.

## Project Description

The Lead Management System (LMS) addresses the critical problem of scattered real estate lead data across multiple applications. The system provides:

- **Centralized Lead Storage**: Unified repository for all real estate leads from internal (Morizon, Gratka) and external (Homsters) applications
- **Intelligent Deduplication**: Automatic detection and merging of duplicate customer records based on email and phone
- **CDP Integration**: Seamless data transfer to Customer Data Platforms (SalesManago, Murapol, DomDevelopment)
- **Role-Based Access**: Secure authentication with different permission levels for Call Center and BOK users
- **Real-time Monitoring**: Comprehensive event logging and failure tracking for system operations

The system eliminates the need for call center agents to log into multiple systems, significantly reducing lead processing time and improving customer service efficiency.

## Tech Stack

### Backend
- **PHP 8.3** with **Symfony 7.3** framework
- **MySQL 9.4** database with Doctrine ORM
- **RabbitMQ** for lead ingestion and distribution
- **Redis** for caching and session management
- **Guzzle** for external API communication

### Frontend
- **Symfony Twig** templates for UI components
- **HTMX** for dynamic interactions

### AI Integration
- **Google Gemini** for AI Google Gemini

### DevOps & Hosting
- **GitHub Actions** for CI/CD pipelines
- **DigitalOcean** hosting with Docker containers
- **Docker** for containerization

### Testing
- **PHPUnit 10.5** for unit and integration tests
- **Symfony PHPUnit Bridge** for functional tests
- **Symfony WebTestCase** for E2E API testing
- **BrowserKit** for browser simulation
- **Doctrine DataFixtures** for test data management
- **Apache JMeter / k6.io** for performance testing
- **OWASP ZAP** for security testing
- **Xdebug** for code coverage analysis

## 🚀 Quick Start with Docker (Recommended)

### Prerequisites
- Docker & Docker Compose
- Git

### Installation Steps

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd lms
   ```

2. **Configure environment**
   ```bash
   cp env.example .env
   # Edit .env with your configuration (optional for local dev)
   ```

3. **Start Docker containers**
   ```bash
   cd docker
   docker-compose up -d
   ```

4. **Install dependencies**
   ```bash
   docker exec lms_app composer install
   ```

5. **Run database migrations**
   ```bash
   docker exec lms_app php bin/console doctrine:migrations:migrate --no-interaction
   ```

6. **Access the application**
   - 🌐 **Frontend**: http://localhost:8082
   - 🗄️ **phpMyAdmin**: http://localhost:8081 (Check your .env file for credentials)
   - 🐰 **RabbitMQ Management**: http://localhost:15672 (Check your .env file for credentials)

### Running Tests

```bash
# All tests
docker exec lms_app vendor/bin/phpunit

# Unit tests only
docker exec lms_app vendor/bin/phpunit tests/Unit

# Functional tests only
docker exec lms_app vendor/bin/phpunit tests/Functional

# With test coverage (testdox)
docker exec lms_app vendor/bin/phpunit --testdox
```

**Current Test Status**: ✅ 51 tests, 154 assertions, 34 passing, 10 skipped, 7 errors (need test config)

### Stopping the Application

```bash
cd docker
docker-compose down
```

## 📦 Local Development (Without Docker)

### Prerequisites
- PHP 8.3 or higher
- MySQL 9.4
- Composer
- RabbitMQ server

### Installation

1. **Clone and install**
   ```bash
   git clone <repository-url>
   cd lms
   composer install
   cp env.example .env
   ```

2. **Configure `.env` file**
   ```env
   DATABASE_URL=mysql://${MYSQL_USER}:${MYSQL_PASSWORD}@localhost:3306/lms_db
   RABBITMQ_URL=amqp://${RABBITMQ_USER}:${RABBITMQ_PASSWORD}@localhost:5672
   API_BASE_URL=http://localhost:8000
   ```

3. **Set up database**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

4. **Start the server**
   ```bash
   symfony server:start
   # or
   php -S localhost:8000 -t public/
   ```

## 📚 Documentation

Detailed guides are available in the `docs/` directory:

- **[SETUP.md](docs/SETUP.md)** - Complete setup and installation guide
- **[DEVELOPMENT.md](docs/DEVELOPMENT.md)** - Development conventions and best practices
- **[API.md](docs/API.md)** - Complete API documentation with curl examples
- **[IMPLEMENTATION_SUMMARY.md](docs/IMPLEMENTATION_SUMMARY.md)** - Full implementation summary

## 🛠️ Available Commands

### Symfony Console Commands (in Docker)

```bash
# Database operations
docker exec lms_app php bin/console doctrine:migrations:migrate
docker exec lms_app php bin/console doctrine:schema:validate

# Cache management
docker exec lms_app php bin/console cache:clear
docker exec lms_app php bin/console cache:warmup

# View logs
docker exec lms_app tail -f var/log/dev.log
```

### Docker Management

```bash
# Start all containers
cd docker && docker-compose up -d

# Stop all containers
cd docker && docker-compose down

# View logs
docker logs lms_app
docker logs lms_mysql
docker logs lms_rabbitmq

# Restart a service
docker-compose restart app
```

### Testing Commands

```bash
# Run all tests
docker exec lms_app vendor/bin/phpunit

# Run with coverage
docker exec lms_app vendor/bin/phpunit --coverage-text

# Run specific test
docker exec lms_app vendor/bin/phpunit tests/Unit/DTO/FiltersDtoTest.php
```

## Project Scope

### MVP Features (Included)
- ✅ Lead ingestion from multiple applications
- ✅ User authentication and role-based access control
- ✅ Lead browsing with filtering and sorting
- ✅ Customer preference editing
- ✅ Lead deletion with audit logging
- ✅ Automatic deduplication based on email/phone
- ✅ CDP system integration with retry mechanisms
- ✅ Comprehensive event logging and monitoring
- ✅ Failed delivery management and retry

### Out of Scope (Future Releases)
- ❌ Lead billing and invoicing
- ❌ Advanced analytics and reporting
- ❌ Automated backup and disaster recovery
- ❌ Automatic integration with new applications
- ❌ Advanced monitoring and alerting
- ❌ CRM integrations beyond CDP systems

### Technical Limitations
- Basic monitoring without advanced alerting
- No automatic cloud scaling
- Manual integration setup for new applications

## 📊 Project Status

🎉 **MVP Development - Dashboard View Implementation (88% Complete)** ✅

### ✅ Completed

#### Core Infrastructure
- ✅ Symfony 7.3 project structure
- ✅ Docker containerization (PHP 8.3, MySQL 9.4, RabbitMQ, Nginx)
- ✅ Database schema with Doctrine XML mappings
- ✅ Testing framework (PHPUnit 10.5)

#### API Layer
- ✅ POST `/api/leads` - Lead creation endpoint
- ✅ DTO layer for API requests/responses
- ✅ Validation service with comprehensive rules
- ✅ Lead transformer for different data sources

#### Business Logic
- ✅ Lead service with deduplication logic
- ✅ Customer service with find-or-create
- ✅ Event logging system
- ✅ CDP delivery service interfaces

#### Frontend - Dashboard View
- ✅ Base layout with sidebar and header
- ✅ Leads list view with HTMX integration
- ✅ Filtering system (basic + advanced)
- ✅ Pagination component
- ✅ Statistics cards with auto-refresh (every 60s)
- ✅ New leads notification (polling every 30s)
- ✅ Lead table with sorting
- ✅ Lead details slider (complete with 5 sections)
- ✅ Customer preferences form (UI complete)
- ✅ ViewModels and service layer for views
- ✅ Responsive design with mobile sidebar toggle
- ✅ Error handling and toast notifications

#### Testing
- ✅ 51 tests total (154 assertions)
- ✅ 34 passing unit tests
- ✅ FiltersDto test suite (5 tests)
- ✅ Validation service tests (9 tests)
- ✅ Customer service tests (4 tests)
- ✅ Lead transformer tests (7 tests)
- ✅ LeadController unit tests (5 tests)
- ⚠️ 7 functional tests (need test config)
- ↩️ 10 integration tests (skipped - require DB)

#### API Endpoints
- ✅ POST `/api/leads` - Create lead
- ✅ GET `/api/leads` - List leads with filtering
- ✅ GET `/api/leads/{uuid}` - Get lead details
- ⏳ PUT `/api/leads/{id}/update-preferences` - (stub)
- ⏳ PUT `/api/leads/{id}/update-status` - (stub)
- ⏳ DELETE `/api/leads/{id}` - (stub)

#### Documentation
- ✅ README.md with quick start & architecture
- ✅ docs/SETUP.md - Installation guide
- ✅ docs/DEVELOPMENT.md - Coding conventions
- ✅ docs/API.md - Complete API documentation
- ✅ docs/IMPLEMENTATION_SUMMARY.md - Project summary
- ✅ env.example - Environment variables

### 🚧 In Progress
- 🔨 Authentication system (Symfony Security)
- 🔨 Customer preferences backend (UI complete)
- 🔨 Lead status update backend (UI complete)
- 🔨 Lead deletion with audit (UI complete)

### 📋 Next Steps (Priority Order)
1. **Fix test environment config** (30 min) - Enable 7 functional tests
2. **Implement authentication** (2-3 days) - Symfony Security component
3. **Complete preferences backend** (1 day) - Save customer preferences
4. **Lead status update** (1 day) - Update lead status with event logging
5. **Lead deletion with audit** (1 day) - Soft delete + audit trail
6. **Add integration tests** (2 days) - Tests with test database
7. **CDP delivery tracking** (1 day) - Track CDP status from events
8. **Failed deliveries view** (2 days) - Management interface

### Success Metrics Targets
- **Availability**: 99.9% uptime
- **Performance**: <3 second API response time
- **Deduplication**: 95% accuracy in duplicate detection
- **Delivery**: 98% successful CDP delivery rate
- **Volume**: Support for ~1,000 leads daily (scalable to 10,000)

## 🏗️ Architecture Overview

```
┌─────────────────┐
│  Applications   │ (Morizon, Gratka, Homsters)
│   (External)    │
└────────┬────────┘
         │ POST /api/leads
         ▼
┌─────────────────────────────────────────┐
│         LMS Application (Symfony)       │
│                                         │
│  ┌──────────────┐   ┌───────────────┐  │
│  │ Controllers  │   │   Services    │  │
│  │  (API+View)  │──▶│  (Business)   │  │
│  └──────────────┘   └───────┬───────┘  │
│         │                    │          │
│  ┌──────▼────────────────────▼───────┐  │
│  │      Doctrine ORM (MySQL 9.4)    │  │
│  └──────────────────────────────────┘  │
│         │                    │          │
│  ┌──────▼──────┐    ┌───────▼───────┐  │
│  │  RabbitMQ   │    │  CDP Clients  │  │
│  │   (Queue)   │    │   (Guzzle)    │  │
│  └─────────────┘    └───────┬───────┘  │
└──────────────────────────────┼──────────┘
                               │
                               ▼
                    ┌─────────────────┐
                    │  CDP Systems    │
                    │  (External)     │
                    └─────────────────┘
```

### Key Components

1. **Controllers**: Handle HTTP requests (API + View)
2. **Services**: Business logic layer (DDD approach with interfaces)
3. **DTOs**: Data transfer objects for API communication
4. **ViewModels**: Aggregate data for frontend views
5. **Doctrine ORM**: Database abstraction with XML mappings
6. **RabbitMQ**: Asynchronous lead processing
7. **HTMX**: Dynamic frontend interactions

## License

[License to be determined - pending legal review]

---

For detailed technical documentation and API specifications, refer to the `/docs` directory (to be created).

For support and questions, please contact the development team or create an issue in this repository.
