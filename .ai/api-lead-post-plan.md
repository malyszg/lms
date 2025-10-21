# API Endpoint Implementation Plan: POST /api/leads

## 1. Przegląd punktu końcowego

Endpoint służy do tworzenia nowych leadów w systemie z aplikacji źródłowych (Morizon, Gratka, Homsters). Głównym celem jest przyjęcie danych leada, deduplikacja klienta, zapisanie w bazie danych i asynchroniczne przekazanie do systemów CDP.

**Kluczowe funkcjonalności:**
- Przyjmowanie leadów z zewnętrznych źródeł
- Automatyczna deduplikacja klientów na podstawie email+phone
- Zapis leada wraz z danymi klienta i właściwości
- Asynchroniczne wysyłanie do systemów CDP
- Kompleksowe logowanie zdarzeń
- Mechanizm retry dla nieudanych dostaw

## 2. Szczegóły żądania

### 2.1 Metoda i struktura
- **Metoda HTTP**: POST
- **Struktura URL**: `/api/leads`
- **Content-Type**: `application/json`
- **Autoryzacja**: API Key w nagłówku `X-API-Key` (lub Bearer token)

### 2.2 Parametry żądania

**Wymagane:**
```json
{
  "lead_uuid": "string (UUID v4)",
  "application_name": "morizon|gratka|homsters",
  "customer": {
    "email": "string (valid email format)",
    "phone": "string (E.164 format preferred)"
  }
}
```

**Opcjonalne (Morizon/Gratka):**
```json
{
  "customer": {
    "first_name": "string (max 100 chars)",
    "last_name": "string (max 100 chars)"
  },
  "property": {
    "property_id": "string (max 100 chars)",
    "development_id": "string (max 100 chars)",
    "partner_id": "string (max 100 chars)",
    "property_type": "string (max 50 chars)",
    "price": "decimal (15,2)",
    "location": "string (max 255 chars)",
    "city": "string (max 100 chars)"
  }
}
```

**Opcjonalne (Homsters - różna struktura):**
```json
{
  "customer": {
    "first_name": "string (max 100 chars)",
    "last_name": "string (max 100 chars)"
  },
  "property": {
    "hms_property_id": "string (max 100 chars)",
    "hms_project_id": "string (max 100 chars)",
    "hms_partner_id": "string (max 100 chars)",
    "property_type": "string (max 50 chars)",
    "price": "decimal (15,2)",
    "location": "string (max 255 chars)",
    "city": "string (max 100 chars)"
  }
}
```

**Mapowanie Homsters → Standard:**
- `hms_property_id` → `property_id`
- `hms_project_id` → `development_id`
- `hms_partner_id` → `partner_id`

### 2.3 Walidacja parametrów

| Parametr | Typ | Walidacja |
|----------|-----|-----------|
| `lead_uuid` | string | UUID v4 format, unikalność w bazie |
| `application_name` | string | Wartość z enuma: morizon, gratka, homsters |
| `customer.email` | string | Format RFC 5322, max 255 znaków |
| `customer.phone` | string | Format E.164 preferowany, max 20 znaków |
| `customer.first_name` | string | Max 100 znaków (opcjonalny) |
| `customer.last_name` | string | Max 100 znaków (opcjonalny) |
| `property.price` | decimal | Dodatnia wartość, max 15 cyfr (2 po przecinku) |
| `property.*` | string | Zgodność z limitami długości w bazie |

## 3. Wykorzystywane typy

### 3.1 DTOs (Request/Response)

**Istniejące (do wykorzystania):**
```php
// src/DTO/CreateLeadRequest.php
class CreateLeadRequest {
    public readonly string $leadUuid;
    public readonly string $applicationName;
    public readonly CreateCustomerDto $customer;
    public readonly PropertyDto $property;
}

// src/DTO/PropertyDto.php (już istnieje)
class PropertyDto {
    public readonly ?string $propertyId;
    public readonly ?string $developmentId;
    public readonly ?string $partnerId;
    public readonly ?string $propertyType;
    public readonly ?float $price;
    public readonly ?string $location;
    public readonly ?string $city;
}

// src/DTO/CreateLeadResponse.php (już istnieje)
class CreateLeadResponse {
    public readonly int $id;
    public readonly string $leadUuid;
    public readonly string $status;
    public readonly int $customerId;
    public readonly string $applicationName;
    public readonly DateTimeInterface $createdAt;
    public readonly string $cdpDeliveryStatus;
}

// src/DTO/ErrorResponseDto.php (już istnieje)
class ErrorResponseDto {
    public readonly string $error;
    public readonly ?string $message;
    public readonly ?array $details;
}
```

**Do stworzenia:**
```php
// src/DTO/CreateCustomerDto.php
class CreateCustomerDto {
    public readonly string $email;
    public readonly string $phone;
    public readonly ?string $firstName;
    public readonly ?string $lastName;
}
```

### 3.2 Encje Doctrine (XML mapping)

**Do stworzenia w src/Model/:**
```php
// src/Model/Customer.php
class Customer {
    private int $id;
    private string $email;
    private string $phone;
    private ?string $firstName;
    private ?string $lastName;
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;
}

// src/Model/Lead.php
class Lead {
    private int $id;
    private string $leadUuid;
    private Customer $customer;
    private string $applicationName;
    private string $status; // enum: new, contacted, qualified, converted, rejected
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;
    private ?LeadProperty $property;
}

// src/Model/LeadProperty.php
class LeadProperty {
    private int $id;
    private Lead $lead;
    private ?string $propertyId;
    private ?string $developmentId;
    private ?string $partnerId;
    private ?string $propertyType;
    private ?float $price;
    private ?string $location;
    private ?string $city;
    private DateTimeInterface $createdAt;
}

// src/Model/Event.php
class Event {
    private int $id;
    private string $eventType;
    private ?string $entityType;
    private ?int $entityId;
    private ?int $userId;
    private ?array $details; // JSON
    private int $retryCount;
    private ?string $errorMessage;
    private ?string $ipAddress;
    private ?string $userAgent;
    private DateTimeInterface $createdAt;
}

// src/Model/FailedDelivery.php
class FailedDelivery {
    private int $id;
    private Lead $lead;
    private string $cdpSystemName;
    private ?string $errorCode;
    private ?string $errorMessage;
    private int $retryCount;
    private int $maxRetries;
    private ?DateTimeInterface $nextRetryAt;
    private string $status; // enum: pending, retrying, failed, resolved
    private DateTimeInterface $createdAt;
    private ?DateTimeInterface $resolvedAt;
}
```

### 3.3 Interfaces

```php
// src/Leads/LeadServiceInterface.php
interface LeadServiceInterface {
    public function createLead(CreateLeadRequest $request): CreateLeadResponse;
}

// src/Leads/CustomerServiceInterface.php
interface CustomerServiceInterface {
    public function findOrCreateCustomer(CreateCustomerDto $customerDto): Customer;
    public function findByEmailAndPhone(string $email, string $phone): ?Customer;
}

// src/Leads/ValidationServiceInterface.php
interface ValidationServiceInterface {
    public function validateCreateLeadRequest(CreateLeadRequest $request): array;
}

// src/ApiClient/CDPDeliveryServiceInterface.php
interface CDPDeliveryServiceInterface {
    public function sendLeadToCDP(Lead $lead): void;
}

// src/Leads/LeadRequestTransformerInterface.php
interface LeadRequestTransformerInterface {
    public function transformRequestData(array $data, string $applicationName): array;
}
```

## 4. Szczegóły odpowiedzi

### 4.1 Sukces (201 Created)
```json
{
  "id": 12345,
  "lead_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "status": "new",
  "customer_id": 678,
  "application_name": "morizon",
  "created_at": "2025-10-11T10:30:00+00:00",
  "cdp_delivery_status": "pending"
}
```

**Nagłówki:**
```
HTTP/1.1 201 Created
Content-Type: application/json
Location: /api/leads/550e8400-e29b-41d4-a716-446655440000
```

### 4.2 Błędy

**400 Bad Request** (walidacja):
```json
{
  "error": "Validation Error",
  "message": "Invalid request data",
  "details": {
    "lead_uuid": "Invalid UUID format",
    "customer.email": "Invalid email format"
  }
}
```

**409 Conflict** (duplikat):
```json
{
  "error": "Conflict",
  "message": "Lead with this UUID already exists",
  "details": {
    "lead_uuid": "550e8400-e29b-41d4-a716-446655440000",
    "existing_lead_id": 12345
  }
}
```

**422 Unprocessable Entity** (logika biznesowa):
```json
{
  "error": "Unprocessable Entity",
  "message": "Customer data inconsistency detected",
  "details": {
    "reason": "Email and phone combination belongs to different customer"
  }
}
```

**500 Internal Server Error**:
```json
{
  "error": "Internal Server Error",
  "message": "An unexpected error occurred",
  "details": null
}
```

## 5. Przepływ danych

### 5.1 Główny przepływ
```
1. Request → Controller (LeadController)
   ↓
2. Transformacja danych → LeadRequestTransformer
   ├─ Jeśli Homsters: hms_* → standard
   └─ Jeśli Morizon/Gratka: bez zmian
   ↓
3. Deserializacja → CreateLeadRequest DTO
   ↓
4. Walidacja → ValidationService
   ↓
5. Sprawdzenie duplikatu lead_uuid
   ↓
6. Deduplikacja klienta → CustomerService
   ├─ Szukaj po email+phone
   ├─ Jeśli istnieje → zwróć istniejącego
   └─ Jeśli nie → utwórz nowego
   ↓
7. Utworzenie Lead → LeadService
   ↓
8. Utworzenie LeadProperty → LeadPropertyService
   ↓
9. Logowanie zdarzenia → EventService
   ↓
10. Asynchroniczne wysłanie do CDP → RabbitMQ → CDPDeliveryService
   ↓
11. Response → CreateLeadResponse (201 Created)
```

### 5.2 Przepływ błędów
```
Błąd walidacji
   ↓
EventService (api_request, error)
   ↓
Response 400/422

Błąd duplikatu
   ↓
EventService (api_request, duplicate)
   ↓
Response 409

Błąd bazy danych/systemu
   ↓
EventService (api_request, error)
   ↓
Response 500
```

### 5.3 Asynchroniczne przetwarzanie CDP
```
Lead utworzony
   ↓
Publikacja wiadomości do RabbitMQ (lead.created)
   ↓
CDPDeliveryService (consumer)
   ├─ Próba wysłania do CDP (SalesManago, Murapol, DomDevelopment)
   ├─ Sukces → EventService (cdp_delivery_success)
   └─ Błąd → FailedDelivery + retry_queue
       ↓
       RetryCommand (cron)
       └─ Ponowne próby (max 3)
```

### 5.4 Interakcje z bazą danych

**Transakcje:**
1. Rozpoczęcie transakcji
2. INSERT/SELECT Customer (z lock FOR UPDATE dla deduplikacji)
3. INSERT Lead
4. INSERT LeadProperty (jeśli dane property są podane)
5. INSERT Event
6. Commit transakcji
7. Publikacja do RabbitMQ (po commit)

**Indeksy wykorzystywane:**
- `customers.unique_email_phone` - deduplikacja
- `leads.idx_lead_uuid` - sprawdzenie duplikatu
- `leads.idx_customer_created` - zapytania o leady klienta

## 6. Względy bezpieczeństwa

### 6.1 Uwierzytelnianie
- **API Key**: Nagłówek `X-API-Key` weryfikowany przez middleware
- **Bearer Token**: Alternatywnie JWT token w nagłówku `Authorization`
- Konfiguracja w `config/packages/security.yaml`

### 6.2 Autoryzacja
- Endpoint dostępny dla:
  - Zewnętrznych systemów (Morizon, Gratka, Homsters) - API Key
  - Admins - Bearer Token
- Role: `ROLE_API_CLIENT`, `ROLE_ADMIN`

### 6.3 Walidacja danych wejściowych
```php
// ValidationService
- UUID format (regex)
- Email format (Symfony Email validator)
- Phone format (libphonenumber-for-php)
- Application name enum check
- String length limits
- SQL injection prevention (Doctrine ORM parameterization)
- XSS prevention (sanityzacja przed zapisem)
```

### 6.4 Rate Limiting
```php
// Symfony Rate Limiter
- 100 żądań na minutę na IP
- 1000 żądań na godzinę na API Key
- Konfiguracja w middleware
```

### 6.5 CORS
```yaml
# config/packages/nelmio_cors.yaml
paths:
  '^/api':
    allow_origin: ['https://morizon.pl', 'https://gratka.pl', 'https://homsters.pl']
    allow_methods: ['POST']
    allow_headers: ['X-API-Key', 'Content-Type']
```

### 6.6 Dodatkowe zabezpieczenia
- **HTTPS only**: Wymuszenie SSL w production
- **Input sanitization**: Trim, strip tags dla stringów
- **Mass assignment protection**: Użycie DTO
- **Prepared statements**: Doctrine ORM automatycznie
- **Error handling**: Nie ujawnianie szczegółów wewnętrznych w production

## 7. Obsługa błędów

### 7.1 Kategorie błędów

#### 7.1.1 Błędy walidacji (400 Bad Request)
```php
Scenariusze:
- Nieprawidłowy format UUID
- Nieprawidłowy format email
- Nieprawidłowy format telefonu
- Nieznana wartość application_name
- Przekroczenie limitów długości pól
- Brak wymaganych pól

Obsługa:
- ValidationService zwraca tablicę błędów
- Controller formatuje do ErrorResponseDto
- EventService loguje (event_type: 'api_request', details: errors)
- Response 400 z szczegółami
```

#### 7.1.2 Konflikt duplikatu (409 Conflict)
```php
Scenariusz:
- Lead z danym UUID już istnieje

Obsługa:
- Sprawdzenie przed utworzeniem (LeadService)
- EventService loguje (event_type: 'api_request', details: duplicate_uuid)
- Response 409 z ID istniejącego leada
```

#### 7.1.3 Błędy logiki biznesowej (422 Unprocessable Entity)
```php
Scenariusze:
- Niespójność danych klienta (email+phone należą do różnych klientów)
- Nieprawidłowa kombinacja property data

Obsługa:
- CustomerService wykrywa niespójność
- EventService loguje (event_type: 'api_request', details: business_error)
- Response 422 z wyjaśnieniem
```

#### 7.1.4 Błędy wewnętrzne (500 Internal Server Error)
```php
Scenariusze:
- Błąd połączenia z bazą danych
- Błąd Doctrine ORM
- Nieoczekiwane wyjątki

Obsługa:
- Global exception handler
- EventService loguje (event_type: 'api_request', error_message: exception)
- Monolog loguje szczegóły (dla devs)
- Response 500 bez szczegółów wewnętrznych (w production)
```

### 7.2 Logowanie błędów

**Tabela events:**
```php
event_type: 'api_request'
entity_type: 'lead'
entity_id: null (jeśli błąd przed utworzeniem)
user_id: null (API request)
details: [
    'endpoint' => '/api/leads',
    'method' => 'POST',
    'status_code' => 400/409/422/500,
    'error' => 'error details',
    'request_data' => 'sanitized request'
]
ip_address: client IP
user_agent: client user agent
```

**Tabela failed_deliveries:**
```php
// Tylko dla błędów CDP (asynchroniczne)
lead_id: ID utworzonego leada
cdp_system_name: 'SalesManago'/'Murapol'/'DomDevelopment'
error_code: HTTP status code lub error code z CDP
error_message: Szczegóły błędu
retry_count: 0
status: 'pending'
next_retry_at: now() + 5 minutes (exponential backoff)
```

### 7.3 Monitoring i alerty
```php
// Metryki do monitorowania
- Liczba żądań na minutę/godzinę
- Wskaźnik błędów (error rate)
- Czas odpowiedzi (response time)
- Liczba duplikatów
- Wskaźnik sukcesu dostaw CDP
- Długość kolejki retry

// Alerty
- Error rate > 5% → alert do team
- Response time > 1s → warning
- Failed deliveries > 100 → critical alert
- RabbitMQ queue length > 1000 → alert
```

## 8. Rozważania dotyczące wydajności

### 8.1 Potencjalne wąskie gardła

1. **Deduplikacja klienta**
   - Problem: Współbieżne żądania z tym samym email+phone
   - Rozwiązanie: Pessimistic locking (SELECT FOR UPDATE)

2. **Wstawianie do bazy**
   - Problem: 10,000 leadów dziennie
   - Rozwiązanie: Connection pooling, indeksy, optymalizacja zapytań

3. **Asynchroniczne przetwarzanie**
   - Problem: Opóźnienia w dostawie do CDP
   - Rozwiązanie: RabbitMQ jako kolejka, multiple consumers

4. **Event logging**
   - Problem: Duża liczba eventów
   - Rozwiązanie: Partycjonowanie tabeli events, async logging

### 8.2 Strategie optymalizacji

#### 8.2.1 Baza danych
```sql
-- Indeksy (już w db-plan.md)
- unique_email_phone na customers
- idx_lead_uuid na leads
- idx_customer_created na leads

-- Optymalizacje
- Connection pooling: max 20 połączeń
- Query cache w MySQL
- Prepared statements (automatycznie przez Doctrine)
```

#### 8.2.2 Cache
```php
// Redis cache dla często używanych danych
- System config (TTL: 1 godzina)
- API keys validation (TTL: 5 minut)

// Nie cache'ować:
- Lead data (zawsze aktualne)
- Customer data (deduplikacja wymaga real-time)
```

#### 8.2.3 Asynchroniczne przetwarzanie
```php
// RabbitMQ
- Exchange: 'leads'
- Queue: 'lead.created' → CDPDeliveryService
- Prefetch count: 10 (consumer pobiera 10 wiadomości na raz)
- Multiple consumers: 3-5 workerów dla CDP delivery

// Message format
{
  "lead_id": 12345,
  "lead_uuid": "550e8400...",
  "customer_id": 678,
  "application_name": "morizon"
}
```

#### 8.2.4 Response time optimization
```php
Target: < 200ms dla 95% żądań

Strategie:
- Minimalizacja zapytań do bazy (1-2 queries per request)
- Batch insert dla Event logging (async)
- Early return po walidacji
- Lazy loading dla relacji Doctrine (nie ładuj niepotrzebnych danych)
```

### 8.3 Skalowanie
```php
Horizontal scaling:
- Stateless API (można uruchomić wiele instancji)
- Load balancer przed aplikacją
- RabbitMQ cluster dla high availability

Vertical scaling:
- Zwiększenie PHP memory_limit: 256M → 512M
- Zwiększenie MySQL buffers
- SSD dla bazy danych
```

### 8.4 Metryki wydajności
```php
// Docelowe metryki
- Response time (p95): < 200ms
- Response time (p99): < 500ms
- Throughput: 100 RPS (requests per second)
- Database query time: < 50ms
- CDP delivery time: < 5s (async)
- Error rate: < 1%
```

## 9. Etapy wdrożenia

### Krok 1: Przygotowanie infrastruktury
```bash
1.1. Utworzenie migracji bazy danych
    - customers, leads, lead_properties
    - events, failed_deliveries, retry_queue
    
1.2. Konfiguracja Doctrine mapping (XML)
    - config/doctrine/Customer.orm.xml
    - config/doctrine/Lead.orm.xml
    - config/doctrine/LeadProperty.orm.xml
    - config/doctrine/Event.orm.xml
    - config/doctrine/FailedDelivery.orm.xml

1.3. Utworzenie encji w src/Model/
    - Customer.php
    - Lead.php
    - LeadProperty.php
    - Event.php
    - FailedDelivery.php

1.4. Konfiguracja RabbitMQ
    - Exchange: leads
    - Queue: lead.created
    - Binding key: lead.created
```

### Krok 2: DTOs, Transformation i Validation
```bash
2.1. Utworzenie/modyfikacja DTOs
    - src/DTO/CreateCustomerDto.php (nowy)
    - Weryfikacja src/DTO/CreateLeadRequest.php
    - Weryfikacja src/DTO/CreateLeadResponse.php
    - Weryfikacja src/DTO/PropertyDto.php
    - Weryfikacja src/DTO/ErrorResponseDto.php

2.2. Utworzenie LeadRequestTransformer
    - src/Leads/LeadRequestTransformer.php
    - src/Leads/LeadRequestTransformerInterface.php
    - transformRequestData() - mapowanie Homsters → standard
    - Logika: hms_property_id → property_id, hms_project_id → development_id, hms_partner_id → partner_id

2.3. Utworzenie ValidationService
    - src/Leads/ValidationService.php
    - src/Leads/ValidationServiceInterface.php
    - Walidacja UUID, email, phone, application_name
    - Walidacja długości stringów
    
2.4. Testy jednostkowe
    - tests/Unit/Leads/LeadRequestTransformerTest.php
    - tests/Unit/Leads/ValidationServiceTest.php
```

### Krok 3: Services (Business Logic)
```bash
3.1. CustomerService
    - src/Leads/CustomerService.php
    - src/Leads/CustomerServiceInterface.php
    - findOrCreateCustomer()
    - findByEmailAndPhone()
    - Obsługa deduplikacji z pessimistic locking
    
3.2. LeadService
    - src/Leads/LeadService.php
    - src/Leads/LeadServiceInterface.php
    - createLead()
    - checkDuplicateUuid()
    - Transakcja dla całego procesu
    
3.3. LeadPropertyService
    - src/Leads/LeadPropertyService.php
    - src/Leads/LeadPropertyServiceInterface.php
    - createProperty()
    
3.4. EventService
    - src/Leads/EventService.php
    - src/Leads/EventServiceInterface.php
    - logApiRequest()
    - logLeadCreated()
    
3.5. Testy jednostkowe dla Services
    - tests/Unit/Leads/CustomerServiceTest.php
    - tests/Unit/Leads/LeadServiceTest.php
    - tests/Unit/Leads/LeadPropertyServiceTest.php
    - tests/Unit/Leads/EventServiceTest.php
```

### Krok 4: Controller i Routing
```bash
4.1. LeadController
    - src/Controller/LeadController.php
    - Endpoint: POST /api/leads
    - Deserializacja request → CreateLeadRequest
    - Wywołanie ValidationService
    - Wywołanie LeadService
    - Obsługa błędów
    - Formatowanie response
    
4.2. Konfiguracja routingu
    - config/routes.yaml lub atrybuty w kontrolerze
    
4.3. Testy funkcjonalne Controller
    - tests/Functional/Controller/LeadControllerTest.php
```

### Krok 5: Security i API Authentication
```bash
5.1. Konfiguracja Security
    - config/packages/security.yaml
    - API Key authenticator
    - Firewall dla /api/*
    
5.2. API Key Authenticator
    - src/Security/ApiKeyAuthenticator.php
    - Weryfikacja X-API-Key header
    - Ładowanie użytkownika z bazy (system_config)
    
5.3. Rate Limiter
    - config/packages/rate_limiter.yaml
    - Middleware dla rate limiting
    
5.4. CORS Configuration
    - config/packages/nelmio_cors.yaml (jeśli używany)
```

### Krok 6: Asynchroniczne przetwarzanie CDP
```bash
6.1. CDPDeliveryService
    - src/ApiClient/CDPDeliveryService.php
    - src/ApiClient/CDPDeliveryServiceInterface.php
    - sendLeadToCDP()
    - Konfiguracja CDP systems z environment variables
    
6.2. RabbitMQ Publisher
    - src/Leads/MessagePublisher.php
    - publishLeadCreated()
    
6.3. RabbitMQ Consumer
    - src/Command/ConsumeLeadCreatedCommand.php
    - Konsumpcja wiadomości lead.created
    - Wywołanie CDPDeliveryService
    
6.4. Retry Mechanism
    - src/Command/RetryFailedDeliveryCommand.php
    - Cron job dla ponownych prób
    - Exponential backoff strategy
    
6.5. Konfiguracja RabbitMQ w Symfony
    - config/packages/messenger.yaml
```

### Krok 7: Obsługa błędów i Logging
```bash
7.1. Exception Handler
    - src/EventListener/ExceptionListener.php
    - Global error handling
    - Formatowanie ErrorResponseDto
    
7.2. Monolog Configuration
    - config/packages/monolog.yaml
    - Channels: app, api, cdp
    - Handlers: file, syslog
    
7.3. Event Logging
    - Integracja EventService w całym flow
    - Logowanie sukcesu i błędów
```

### Krok 8: Testy
```bash
8.1. Testy jednostkowe
    - ValidationServiceTest
    - CustomerServiceTest
    - LeadServiceTest
    - LeadPropertyServiceTest
    - EventServiceTest
    - CDPDeliveryServiceTest
    
8.2. Testy integracyjne
    - LeadControllerTest (functional)
    - DatabaseIntegrationTest (transakcje)
    - RabbitMQIntegrationTest
    
8.3. Testy end-to-end
    - Full flow test (API → Database → RabbitMQ → CDP)
    - Error scenarios tests
    
8.4. PHPUnit configuration
    - phpunit.xml.dist
    - Test database setup
```

### Krok 9: Dokumentacja
```bash
9.1. OpenAPI/Swagger dokumentacja
    - Specyfikacja endpoint w YAML
    - Przykłady request/response
    
9.2. README aktualizacja
    - Instrukcje uruchomienia
    - Konfiguracja environment variables
    - Przykłady użycia API
    
9.3. Komentarze w kodzie
    - PHPDoc dla wszystkich publicznych metod
    - Wyjaśnienia logiki biznesowej
```

### Krok 10: Deployment i Monitoring
```bash
10.1. Environment variables
     - .env.example aktualizacja
     - Wymagane zmienne: DATABASE_URL, RABBITMQ_DSN, CDP_API_KEYS
     
10.2. Docker configuration
     - docker-compose.yml aktualizacja
     - RabbitMQ service
     
10.3. CI/CD Pipeline
     - GitHub Actions workflow
     - Automated tests
     - Code quality checks (PHPStan, PHP CS Fixer)
     
10.4. Monitoring setup
     - Prometheus metrics (opcjonalnie)
     - Grafana dashboards (opcjonalnie)
     - Log aggregation (ELK stack lub podobne)
```

### Krok 11: Code Review i Optymalizacja
```bash
11.1. Code review checklist
     - Zgodność z coding standards (PSR-12)
     - Zgodność z rules (.cursor/rules/shared.mdc)
     - Security check
     - Performance review
     
11.2. Performance testing
     - Load testing (Apache Bench, JMeter)
     - Profiling (Blackfire, Xdebug)
     - Query optimization
     
11.3. Security audit
     - Dependency vulnerability scan (composer audit)
     - OWASP Top 10 check
     - Penetration testing (opcjonalnie)
```

### Krok 12: Production Deployment
```bash
12.1. Pre-deployment checklist
     - Backup bazy danych
     - Migracje przetestowane
     - Environment variables ustawione
     - RabbitMQ skonfigurowane
     
12.2. Deployment
     - Pull latest code
     - composer install --no-dev --optimize-autoloader
     - php bin/console doctrine:migrations:migrate --no-interaction
     - php bin/console cache:clear --env=prod
     - Restart PHP-FPM
     - Start RabbitMQ consumers (supervisor)
     
12.3. Post-deployment verification
     - Health check endpoint
     - Smoke tests
     - Monitoring dashboards
     - Error logs check
     
12.4. Rollback plan
     - Database migration rollback
     - Code rollback procedure
     - Communication plan
```

## 10. Checklist implementacji

### Must Have (MVP)
- [x] Encje i migracje bazy danych
- [x] DTOs dla request/response (CreateCustomerDto, CreateLeadRequest, CreateLeadResponse, PropertyDto, ErrorResponseDto)
- [x] ValidationService (walidacja UUID, email, phone, application_name)
- [x] CustomerService (deduplikacja z pessimistic locking)
- [x] LeadService (tworzenie leadów z transakcjami)
- [x] LeadPropertyService (tworzenie właściwości)
- [x] EventService (logowanie API requests)
- [x] LeadController (endpoint POST /api/leads)
- [x] LeadRequestTransformer (mapowanie Homsters → standard)
- [x] ExceptionListener (global error handling)
- [x] LeadAlreadyExistsException (409 Conflict)
- [x] Podstawowa obsługa błędów (ValidationException)
- [x] Logowanie do events (api_request)
- [x] Testy jednostkowe Services (częściowo)
- [x] Testy funkcjonalne Controller

### Should Have
- [ ] API Authentication (API Key) - wymaga implementacji Security
- [ ] Rate Limiting - wymaga konfiguracji
- [x] RabbitMQ integration - CDPDeliveryService gotowy (mock mode)
- [x] CDPDeliveryService - podstawowa implementacja
- [ ] Retry mechanism - FailedDelivery model gotowy, command wymaga implementacji
- [ ] Monitoring i metryki
- [ ] OpenAPI documentation

### Nice to Have
- [ ] CORS configuration
- [ ] Redis cache
- [ ] Grafana dashboards
- [ ] ELK stack integration
- [ ] Load testing reports
- [ ] Security audit report

### Refaktoryzacje wykonane (2025-10-11)
- [x] Dodać logowanie lead_created event w LeadService
- [x] Przekazać IP address i user agent do logowania leadów
- [x] Ulepszyć obsługę błędów w CustomerService
- [x] Dodać automatyczne logowanie wszystkich błędów API w ExceptionListener
- [x] Poprawić Location header w response

### Refaktoryzacje do zrobienia
- [ ] Dodać logowanie CDP delivery events (success/failed)
- [ ] Poprawić testy jednostkowe (CustomerServiceTest ma błędy)
- [ ] Dodać testy dla nowych funkcjonalności logowania
- [ ] Implementacja API Authentication
- [ ] Implementacja Rate Limiting
- [ ] Implementacja RetryFailedDeliveryCommand

## 11. Zmienne środowiskowe

```env
# Database
DATABASE_URL="mysql://user:password@localhost:3306/lms?serverVersion=9.4"

# RabbitMQ
RABBITMQ_DSN="amqp://user:password@localhost:5672"

# Redis (opcjonalnie)
REDIS_URL="redis://localhost:6379"

# API Keys (dla testów)
API_KEY_MORIZON="secret-key-morizon"
API_KEY_GRATKA="secret-key-gratka"
API_KEY_HOMSTERS="secret-key-homsters"

# CDP Systems
CDP_SALESMANAGO_API_URL="https://api.salesmanago.pl"
CDP_SALESMANAGO_API_KEY="salesmanago-key"
CDP_MURAPOL_API_URL="https://api.murapol.pl"
CDP_MURAPOL_API_KEY="murapol-key"
CDP_DOMDEVELOPMENT_API_URL="https://api.domdevelopment.pl"
CDP_DOMDEVELOPMENT_API_KEY="domdevelopment-key"

# App
APP_ENV="dev"
APP_SECRET="app-secret-change-in-production"
```

## 12. Przykłady użycia

### cURL Examples

**Sukces (201):**
```bash
curl -X POST https://api.lms.example.com/api/leads \
  -H "Content-Type: application/json" \
  -H "X-API-Key: secret-key-morizon" \
  -d '{
    "lead_uuid": "550e8400-e29b-41d4-a716-446655440000",
    "application_name": "morizon",
    "customer": {
      "email": "john.doe@example.com",
      "phone": "+48123456789",
      "first_name": "John",
      "last_name": "Doe"
    },
    "property": {
      "property_id": "PROP-12345",
      "development_id": "DEV-001",
      "partner_id": "PARTNER-100",
      "property_type": "apartment",
      "price": 450000.00,
      "location": "Mokotów, Warszawa",
      "city": "Warszawa"
    }
  }'
```

**Błąd walidacji (400):**
```bash
curl -X POST https://api.lms.example.com/api/leads \
  -H "Content-Type: application/json" \
  -H "X-API-Key: secret-key-morizon" \
  -d '{
    "lead_uuid": "invalid-uuid",
    "application_name": "unknown-app",
    "customer": {
      "email": "invalid-email",
      "phone": "123"
    }
  }'
```

---

**Autor planu**: AI Assistant  
**Data utworzenia**: 2025-10-11  
**Wersja**: 1.0  
**Status**: Draft - Gotowy do review i implementacji
