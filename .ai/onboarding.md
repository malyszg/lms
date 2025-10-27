# Project Onboarding: Lead Management System (LMS)

## Welcome

Welcome to the Lead Management System (LMS) project! This is a centralized system for managing real estate leads from various applications (Morizon, Gratka, Homsters), designed to solve data fragmentation and improve lead deliverability by 70%. The system eliminates the need for call center agents to log into multiple systems, significantly reducing lead processing time and improving customer service efficiency.

> **📋 Document Enhancement Summary (Oct 2025):** This onboarding document has been enhanced based on comprehensive git history analysis and file-level examination of the 10 most active files. Key additions include: specific commit dates and patterns, detailed file-level insights (including line references), module relationships not previously documented, recent development focus areas (Oct 21-26, 2025), and code analysis-based complexity warnings. See highlighted sections marked with **bold text** for new insights.

## Project Overview & Structure

The core functionality revolves around managing real estate leads with intelligent deduplication, CDP integration, and role-based access control. The project is organized as a single application built on Symfony 7.3, with the following key components/modules:

## Core Modules

### `src/DTO` (Data Transfer Objects)

- **Role:** Defines data structures for API requests and responses, ensuring type safety and data consistency between API and business logic layers
- **Key Files/Areas:**
  - API Request DTOs: `CreateLeadRequest.php`, `UpdateLeadRequest.php`, `FiltersDto.php`
  - API Response DTOs: `LeadsListApiResponse.php`, `CreateLeadResponse.php`, `UpdateLeadResponse.php`, `DeleteLeadResponse.php`
  - Data DTOs: `LeadDto.php`, `LeadItemDto.php`, `LeadDetailDto.php`, `CustomerDto.php`, `PropertyDto.php`
  - Statistics DTOs: `StatsDto.php`, `PaginationDto.php`
  - Config DTOs: `SystemConfigDto.php`, `UpdateConfigRequest.php`, `UpdatePreferencesRequest.php`
- **Top Contributed Files:** Most frequently modified with 43 changes - core to API functionality
- **Recent Focus:** Extensive DTO layer development for API standardization, filtering/sorting support, and comprehensive response structures with proper type hints and validation

### `src/Leads` (Business Logic)

- **Role:** Core business logic for lead processing, customer management, event logging, validation, and CDP integration. **This is the most critical module for understanding the system's core functionality.**
- **Key Files/Areas:**
  - Lead Services: `LeadService.php`, `CustomerService.php`, `LeadPropertyService.php`
  - Event & Delivery: `EventService.php`, `FailedDeliveryService.php`
  - Transformation: `LeadRequestTransformer.php`, `CDPPayloadTransformer.php`
  - Validation: `ValidationService.php`
  - AI Integration: `GeminiLeadScoringService.php`
- **Top Contributed Files:** `LeadService.php` (6 changes), `EventService.php` (5 changes), `CustomerService.php` (5 changes)
- **Recent Focus:** Recent commits show intensive activity (Oct 21-26, 2025) including:
  - Asynchronous CDP delivery with RabbitMQ message queuing (Oct 26, multiple commits)
  - Customer preferences management functionality (Oct 25-26)
  - Lead status change and deletion functionality (Oct 25)
  - Event-driven architecture with comprehensive logging
- **Key Relationships:** Works closely with `src/Controller` for API endpoints, `src/Service` for view aggregation, and `src/ApiClient` for CDP integration. `LeadService` coordinates with `CustomerService` (deduplication), `EventService` (audit trail), and CDP services (async delivery). Note: All 5 recent commits involve changes to `EventService`, indicating it's critical infrastructure.

### `.github/workflows` (CI/CD)

- **Role:** Automated testing, building, and deployment pipeline
- **Key Files/Areas:**
  - CI Pipeline: `.github/workflows/ci.yml` (28 changes)
- **Top Contributed Files:** `ci.yml` - comprehensive CI/CD workflow
- **Recent Focus:** Complete CI/CD pipeline with automated testing (unit and functional tests with MySQL 9.4), security scanning, Docker build and push for production deployments, test coverage reporting, and multi-stage workflow (test → build → security scan)

### `.ai` (AI Planning)

- **Role:** Documentation of AI-assisted development plans and implementation summaries
- **Key Files/Areas:**
  - Implementation Plans: Various feature implementation plans and PRD documents
  - Diagrams: Architecture diagrams in markdown and mermaid format
- **Top Contributed Files:** `lead_score_description.md`, `ui-plan.md` (recently modified)
- **Recent Focus:** Active AI-assisted development with 24 changes, covering lead scoring, UI plans, CDP delivery implementation, and various feature implementations documented

### `tests/Unit/Leads` (Unit Tests for Business Logic)

- **Role:** Unit tests for lead-related business logic services
- **Key Files/Areas:**
  - Service Tests: `LeadServiceTest.php`, `CustomerServiceTest.php`, `ValidationServiceTest.php`
  - Integration Component Tests: `LeadRequestTransformerTest.php`, various event service tests
- **Top Contributed Files:** 20 changes across test files
- **Recent Focus:** Comprehensive unit test coverage with 34 passing tests, 154 assertions, following strict testing rules including real data usage (avoiding Argument::any() in tests), proper test isolation, and covering all business logic scenarios including deduplication, validation, and event logging

### `src/Controller` (API & View Controllers)

- **Role:** HTTP request handling for both REST API endpoints and Twig template rendering. Two distinct controller types: API controllers (JSON responses) and View controllers (HTML rendering with HTMX).
- **Key Files/Areas:**
  - API Controllers: `LeadController.php` (REST API for leads with request transformation for different sources like Homsters)
  - View Controllers: `LeadsViewController.php` (dashboard), `LeadDetailsController.php` (lead details slider with CDP status determination), `CustomersViewController.php`
  - Specialized Views: `FailedDeliveriesViewController.php`, `EventsViewController.php`, `ConfigViewController.php`
  - Auth: `AuthController.php`, `ProfileController.php`
- **Top Contributed Files:** `LeadDetailsController.php` (6 changes), `LeadsViewController.php` (4 changes), `LeadController.php`
- **Recent Focus:** Based on git history analysis:
  - Oct 26, 2025: CDP delivery integration (async RabbitMQ messaging)
  - Oct 26: Comprehensive customer management interface
  - Oct 25: Customer preferences management
  - Oct 25: Lead status change functionality with HTMX support
  - Oct 25: Lead deletion functionality
- **Key Architectural Notes:** `LeadDetailsController` handles dual response modes (JSON for API, HTML for HTMX). The controller includes `determineCdpDeliveryStatus()` method which queries both `FailedDelivery` and `Event` tables. Note: All commits show coordinated changes across controllers and services, indicating feature-driven development approach.

### `docker` (Containerization)

- **Role:** Docker configuration for local development and production deployment
- **Key Files/Areas:**
  - Docker Compose: `docker-compose.yml`, `docker-compose.prod.yml`
  - Docker Image: `Dockerfile`, `docker-entrypoint.sh`, `fix-permissions.sh`
  - Nginx Config: `nginx.conf`, `nginx.prod.conf`
  - MySQL Init: `mysql-init/` directory
- **Top Contributed Files:** Recently updated with 20 changes
- **Recent Focus:** Complete containerization with PHP 8.3, MySQL 9.4, RabbitMQ, Nginx, test database setup, environment configuration, and production-ready Docker setup with proper volume management

### `templates/leads` (Frontend Templates)

- **Role:** Twig templates for lead management UI using Fluent Design principles combined with Bootstrap 5
- **Key Files/Areas:**
  - Main Views: `index.html.twig` (dashboard), `details.html.twig`
  - Components: `_details_slider.html.twig`, `_table.html.twig`, `_filters.html.twig`, `_advanced_filters.html.twig`
  - Utility Components: `_stats.html.twig`, `_pagination.html.twig`, `_new_leads_notification.html.twig`
- **Top Contributed Files:** `_details_slider.html.twig` (5 changes) - most actively developed template
- **Recent Focus:** Based on git history (Oct 25-26, 2025):
  - Customer management interface enhancements
  - Customer preferences management UI
  - Lead status change interface
  - Lead deletion UI
  - Implemented Fluent Design Web Components with Microsoft's Fluent UI library
- **Architectural Notes:** `_details_slider.html.twig` is a complex 512-line component implementing offcanvas details view with customer info, property details, preferences form (editable via HTMX), and action buttons. Uses Fluent UI badges, buttons, and icons. The slider includes multiple sections that can be shown/hidden based on data availability.

### `src/Service` (Application Services)

- **Role:** Services aggregating data for views and handling user operations
- **Key Files/Areas:**
  - View Services: `LeadViewService.php` (aggregates data for lead views), `CustomerViewService.php`
  - Statistics: `StatsService.php` (dashboard statistics)
  - User: `UserService.php`, `PasswordChangeService.php`
- **Top Contributed Files:** Recently extended with view-specific services
- **Recent Focus:** Service layer for view aggregation, separating API services from view concerns, implementing statistics aggregation, and preparing for user management features

### `src/ApiClient` (External System Integration)

- **Role:** HTTP clients and payload transformers for CDP system integration (SalesManago, Murapol, DomDevelopment)
- **Key Files/Areas:**
  - Client: `CDPDeliveryService.php` (asynchronous delivery with retry via RabbitMQ)
  - Transformation: `CDPPayloadTransformer.php` (converts leads to CDP-specific formats)
  - Configuration: `CDPSystemConfig.php` (manages CDP system settings from environment variables)
- **Recent Focus:** Based on git history, heavy activity on Oct 26, 2025 implementing:
  - Asynchronous CDP delivery using Symfony Messenger and RabbitMQ
  - Exponential backoff retry mechanisms for failed deliveries
  - Failed delivery tracking with `FailedDelivery` model
  - Support for multiple CDP systems with enable/disable flags
- **Architectural Notes:** Uses `MessageBusInterface` to dispatch `CDPLeadMessage` to RabbitMQ. The `CDPLeadMessageHandler` processes messages asynchronously. Includes `ExponentialBackoffCalculator` for retry timing. Critical for data delivery to external systems.

### Root Configuration

- **Role:** Project-level configuration, scripts, and documentation
- **Key Files/Areas:**
  - Config: `composer.json`, `phpunit.xml.dist`, `config/` directory
  - Scripts: `bootstrap.sh`, `protect-production-db.sh`, `verify-test-database.sh`
  - Documentation: `README.md`, `APP_STATUS.md`, various `docs/` files
- **Top Contributed Files:** 15 changes to root-level files
- **Recent Focus:** Complete project setup with Docker-based development environment, comprehensive documentation, test database separation for safety, and environment variable management

## Key Contributors

**Based on Git History Analysis (Oct 2025 - Recent Activity):**

- **grzegorz.tesmer@morizon.pl:** 46 commits overall, actively working on all aspects of the project
  - **Recent Activity (Oct 21-26, 2025):** Very active with commits almost daily
  - **Areas of Focus:**
    - CDP delivery system (4 commits on Oct 26)
    - Customer management and preferences (Oct 25-26)
    - Lead lifecycle management: status updates, deletion (Oct 25)
    - CI/CD pipeline enhancements (Oct 22-23)
    - Docker and containerization improvements (Oct 22-23)
    - Test infrastructure (Oct 22)
  - **Development Style:** Feature-driven development with coordinated changes across multiple files in single commits. For example, a "feat: add asynchronous CDP lead delivery" commit touches 6+ files across service, controller, and API layers.
  - **Commit Messages:** Follow consistent conventional commit format with prefixes like `feat:`, `fix:`, `ci:`, `docs:`

## Overall Takeaways & Recent Focus

**Based on comprehensive git history and file analysis (Oct 2025):**

1. **API-First Architecture with Transformation Layer:** Comprehensive RESTful API with robust DTO layer (43 changes) for type-safe communication. **Key Discovery:** `LeadRequestTransformer` handles different data formats from multiple applications (Homsters uses `hms_*` prefix fields that get mapped to standard fields). Extensive filtering/sorting capabilities with well-documented curl examples.

2. **Asynchronous CDP Integration (Current Priority):** Recent development focus (4 commits on Oct 26, 2025) on implementing RabbitMQ-based async CDP delivery. Pattern observed: `LeadService::createLead()` publishes `CDPLeadMessage` to RabbitMQ, which is processed by `CDPLeadMessageHandler` asynchronously. This decouples lead creation from CDP delivery, preventing API timeouts.

3. **Event-Driven Architecture:** `EventService` touched in **every recent business logic commit** (5 out of 5 commits). Pattern: All critical operations (lead create/update/delete, customer changes, CDP deliveries) are logged with IP address and user agent tracking for audit compliance.

4. **Dual Response Architecture:** Controllers support both JSON (API) and HTML (HTMX) responses. `LeadDetailsController` explicitly checks `HX-Request` header and renders different formats. This allows the same endpoints to serve both traditional API consumers and modern HTMX frontend.

5. **Fluent Design + Bootstrap 5 Hybrid:** UI uses Microsoft Fluent UI Web Components combined with Bootstrap 5 utilities. `base.html.twig` loads both libraries. `_details_slider.html.twig` uses Fluent UI badges, buttons, icons while Bootstrap handles grid layout.

6. **Test Database Separation:** Heavy investment in test infrastructure (Oct 22-23). `phpunit.xml.dist` explicitly uses `lms_db_test` (line 26) with pessimistic locking disabled. Pattern: All test setup commits include both test database creation and CI/CD configuration updates.

7. **Feature-Driven Development Pattern:** Commits show coordinated changes across multiple layers. Example "feat(customers): add preferences management" touches: DTO layer, Controller, Service, Event logging, and Tests. This indicates well-coordinated development rather than single-file changes.

8. **CI/CD Pipeline Excellence:** 28 changes to `.github/workflows/ci.yml` showing iterative refinement. Pattern: includes MySQL 9.4 service, test database setup, coverage reporting, Docker builds, and security scanning. Recent focus on fixing test database configuration and Docker build optimizations.

## Potential Complexity/Areas to Note

**Based on file-level analysis and git history:**

- **CDP Integration & Retry Logic (High Activity Area):** The CDP delivery system is the most actively developed area (4 commits on Oct 26, 2025). Involves: RabbitMQ message queuing, exponential backoff retry mechanisms, failed delivery tracking via `FailedDelivery` model, integration with multiple external systems (SalesManago, Murapol, DomDevelopment). **Watch out for:** Timeouts, network failures, message queue backpressure, and ensure proper error logging in `FailedDeliveryService` and `CDPDeliveryService`. The async nature means CDP delivery happens in background - check RabbitMQ queue status if deliveries seem delayed.

- **Deduplication Logic with Pessimistic Locking:** The customer deduplication in `CustomerService::findOrCreateCustomer()` uses pessimistic write locks to prevent race conditions. Critical for data integrity. Pattern: uses `LockMode::PESSIMISTIC_WRITE` when checking for existing customers. **Watch out for:** Deadlocks in high-concurrency scenarios, database-level performance implications of locking.

- **Database Separation (Critical for Safety):** Strict separation between `lms_db` (production) and `lms_db_test` (tests) is enforced. `phpunit.xml.dist` line 26 explicitly configures test database. Recent commits (Oct 22) show active work on test database setup scripts. **Always verify** you're running tests against `lms_db_test` to prevent production data corruption. Run `./verify-test-database.sh` script before running tests.

- **HTMX Dual Response Pattern:** Controllers like `LeadDetailsController` check for `HX-Request` header to determine response format (HTML vs JSON). This creates dual code paths. **Watch out for:** Consistent error handling in both response modes (HTMX uses HTML snippets, API uses JSON objects). The `updatePreferences()` and `updateStatus()` methods show this pattern clearly.

- **Event Service as Infrastructure:** `EventService` is modified in virtually every business logic commit (5 out of 5 recent commits). This indicates it's critical infrastructure. Changes to event logging patterns affect audit trails. **Watch out for:** All customer, lead, and CDP operations automatically log events. Any change to event structure requires data migration considerations.

- **Symfony Security Implementation:** Authentication is currently in stub form. Controllers use `#[IsGranted('ROLE_CALL_CENTER')]` but security is not fully implemented. **Watch out for:** Upcoming Symfony Security implementation will require careful migration of all protected routes and test setup.

- **Lead Details Slider Complexity:** `_details_slider.html.twig` is a 512-line complex component with multiple sections, conditional rendering, HTMX interactions, and Fluent UI styling. **Watch out for:** Modifications require understanding of Fluent Design tokens, HTMX response swapping, and JavaScript interaction for slider open/close functionality.

## Questions for the Team

**Based on code analysis, the following questions would help new developers understand unclear aspects:**

1. **RabbitMQ Message Processing:** I see `CDPLeadMessage` is dispatched to RabbitMQ in `LeadService::createLead()` (line 95), and `CDPLeadMessageHandler` processes it. What's the message flow when the queue backs up? How do we monitor queue depth? Is there a dead letter queue configured?

2. **CDP Delivery Status Logic:** The `LeadDetailsController::determineCdpDeliveryStatus()` method (line 429) checks both `FailedDelivery` and `Event` tables. What's the reason for querying both? When would we have a success event but no failed delivery record? Is this handling legacy data?

3. **HTMX vs JSON Dual Response:** Multiple controllers check `HX-Request` header to provide different response formats (HTML vs JSON). While this works, it means maintaining two code paths. Was this a conscious architectural decision? Are there plans to split API and View controllers more distinctly?

4. **Customer Deduplication Locking:** `CustomerService::findOrCreateCustomer()` uses pessimistic write locks. What happens in high-concurrency scenarios with many simultaneous lead creations? Have we observed deadlocks in production? Is there a plan to move to optimistic locking or unique constraint-based deduplication?

5. **Event Service as Core Infrastructure:** `EventService` is touched in every business logic commit. This suggests it's critical. What's the performance impact of logging every operation? Are there plans for event data archival or aggregation for long-running systems?

6. **Docker Production Deployment:** The CI/CD pipeline builds production Docker images (line 214-225 in ci.yml). What's the deployment strategy? Rolling updates? Blue-green? How are migrations handled during container restart? What's the downtime tolerance?

7. **Test Database vs Production Conflicts:** Recent commits (Oct 22) show active work on test database separation. Has there been a previous incident where tests ran against production database? How is this prevention now enforced?

8. **CDP System Rate Limiting:** The `CDPDeliveryService` has exponential backoff for retries, but I don't see rate limiting logic. If we send 1000 leads and all hit the same CDP system, could we overwhelm their API? Is there per-CDP rate limiting configured at infrastructure level (nginx/reverse proxy)?

## Next Steps

**Based on file-level analysis, recommended learning path:**

1. **Set up local development environment** - Use Docker setup as documented in `README.md` (sections "Quick Start with Docker" and "Running Tests"). Run `cd docker && docker-compose up -d`, then `docker exec lms_app composer install` and `docker exec lms_app php bin/console doctrine:migrations:migrate --no-interaction`. **CRITICAL:** Run `./verify-test-database.sh` to ensure `lms_db_test` exists before running tests. Verify services are running at http://localhost:8082

2. **Start with the Core Business Logic Flow (Highest Priority):**
   - Read `src/Leads/LeadService.php` (lines 48-124) to understand the main `createLead()` flow
   - Read `src/Leads/CustomerService.php` (lines 43-64) to understand `findOrCreateCustomer()` deduplication logic
   - Read `src/Leads/EventService.php` to see how events are logged (this is touched in every commit)
   - Follow the flow from `LeadController::create()` (line 52) → `LeadService::createLead()` → Customer deduplication → Property creation → Event logging → CDP message dispatch

3. **Understand the Async CDP Delivery Pattern (Recently Implemented):**
   - Read `src/Leads/LeadService.php` line 94-100 to see how `CDPLeadMessage` is dispatched
   - Read `src/MessageHandler/CDPLeadMessageHandler.php` to understand async processing
   - Check `src/ApiClient/CDPDeliveryService.php` to understand the actual delivery mechanism
   - Look at `src/Model/FailedDelivery.php` to understand retry tracking

4. **Study the Dual Response Pattern:**
   - Read `src/Controller/LeadDetailsController.php` lines 124-269 to see how `updatePreferences()` handles both HTMX and JSON responses
   - Check how the `HX-Request` header determines response format (line 187)
   - This pattern appears in: `updatePreferences()`, `updateStatus()` methods

5. **Run the test suite with understanding:**
   - Execute `docker exec lms_app vendor/bin/phpunit tests/Unit/Leads/LeadServiceTest.php` 
   - **Read BEFORE running:** `tests/Unit/Leads/LeadServiceTest.php` - this 840-line file tests the core business logic
   - Notice how tests use real data (not `Argument::any()`) as per project standards

6. **Explore the UI Architecture:**
   - Read `templates/base.html.twig` to understand Fluent UI + Bootstrap 5 hybrid
   - Read `templates/leads/_details_slider.html.twig` (lines 1-80 to start) to understand the complex 512-line component
   - Check `public/js/app.js` to see how HTMX error handling and slider JavaScript works

7. **Review Recent Git History for Context:**
   - Run `git log --oneline --graph -10 --all` to see recent commit patterns
   - Focus on commits from Oct 21-26, 2025 to understand current development priorities:
     - Oct 26: CDP async delivery (4 commits)
     - Oct 25: Customer management, lead deletion, status updates
     - Oct 22-23: Docker and test infrastructure

8. **Specific Files to Review First** (in order of importance based on analysis):
   - `src/Leads/LeadService.php` - Core orchestration (6 changes)
   - `src/Controller/LeadDetailsController.php` - Dual response pattern (6 changes)
   - `src/Leads/EventService.php` - Critical infrastructure (5 changes)
   - `templates/leads/_details_slider.html.twig` - Complex UI component (5 changes)
   - `src/ApiClient/CDPDeliveryService.php` - Recently implemented async delivery

## Development Environment Setup

1. **Prerequisites:** PHP 8.3+, Docker & Docker Compose, Git. MySQL 9.4, RabbitMQ, Nginx, Composer are bundled in Docker containers.
2. **Dependency Installation:** `docker exec lms_app composer install` (installs PHP dependencies via Composer in Docker container)
3. **Building the Project (if applicable):** N/A - PHP application, no compilation needed. Docker images built automatically with `docker-compose up`.
4. **Running the Application/Service:** `cd docker && docker-compose up -d` starts all services (PHP app, Nginx, MySQL, RabbitMQ, phpMyAdmin). Access at http://localhost:8082. Alternative without Docker: `symfony server:start` or `php -S localhost:8000 -t public/`.
5. **Running Tests:** `docker exec lms_app vendor/bin/phpunit` (all tests), `docker exec lms_app vendor/bin/phpunit tests/Unit` (unit only), `docker exec lms_app vendor/bin/phpunit tests/Functional` (functional only). Ensure test database `lms_db_test` exists first (see `TEST_DATABASE_SETUP.md`).
6. **Common Issues:** Verify test database separation - tests MUST use `lms_db_test`, never `lms_db`. Run `./verify-test-database.sh` to check setup. If services don't start, check logs: `docker logs lms_app`, `docker logs lms_mysql`. Ensure `.env` file exists (copy from `env.example`). Run `docker exec lms_app php bin/console cache:clear` if encountering cache issues.

## Helpful Resources

- **Documentation:** Multiple guides in `/docs` directory - `SETUP.md` (installation), `DEVELOPMENT.md` (coding conventions), `API.md` (API documentation with curl examples), `IMPLEMENTATION_SUMMARY.md` (implementation details)
- **Issue Tracker:** Link not found in checked files - likely in GitHub repository issues section
- **Contribution Guide:** Coding conventions detailed in `docs/DEVELOPMENT.md` covering type declarations, DDD patterns, Doctrine XML mapping, testing requirements, Git workflow, and coding best practices
- **Communication Channels:** Contact information not found in checked files - likely via email (support@example.com mentioned in API.md) or GitHub issues
- **Learning Resources:** Project-specific documentation in `docs/` directory. For Symfony 7.3: https://symfony.com/doc/7.3/index.html. For HTMX: https://htmx.org/. For PHPUnit: https://phpunit.de/documentation.html. For Docker Compose: https://docs.docker.com/compose/
