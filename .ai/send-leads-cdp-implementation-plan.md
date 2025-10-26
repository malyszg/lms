# Plan wdrożenia wysyłki leadów do systemów CDP

## 1. Przegląd

Plan wdrożenia automatycznego przekazywania leadów do systemów CDP (SalesManago, Murapol, DomDevelopment) zgodnie z wymaganiami z PRD punkt 3.8.

**Cel:** Implementacja mechanizmu, który:
- Automatycznie przekazuje leady do systemów CDP po ich utworzeniu w LMS
- Zapisuje leada w LMS najpierw, a następnie próbuje wysłać do CDP
- Implementuje retry mechanism z exponential backoff w przypadku błędów
- Umożliwia administratorom ręczne ponowienie wysłania nieudanych dostaw

**Obecny stan:**
- `CDPDeliveryService` istnieje jako mock implementation
- `FailedDelivery` model istnieje (tabela w bazie)
- EventService loguje CDP delivery success/failed
- `LeadService` wywołuje CDP delivery po commit transaction
- Brak: prawdziwej implementacji HTTP, retry mechanism, RabbitMQ integracji, zapisu FailedDelivery do bazy, API endpointów dla administrators

## 2. Architektura rozwiązania

### 2.1 Proces wysyłki leadów do CDP

```
┌─────────────────────────────────────────────────────────────┐
│ 1. API Request: POST /api/leads                             │
│    └─> LeadController::create()                             │
│        └─> LeadService::createLead()                       │
└─────────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. Tworzenie leada w LMS (transakcja)                       │
│    - Walidacja danych                                        │
│    - Deduplikacja klienta (CustomerService)                 │
│    - Utworzenie Lead + LeadProperty                         │
│    - Logowanie event (lead_created)                         │
│    - COMMIT transakcji                                       │
└─────────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. Wysyłka do systemów CDP (ASYNC - po commit)               │
│    - Wywołanie CDPDeliveryService::sendLeadToCDP()          │
│    - Dla każdego systemu CDP:                               │
│      a. Przygotowanie payload (transformacja danych)       │
│      b. HTTP request do CDP API                             │
│      c. Obsługa odpowiedzi:                                 │
│         - Sukces → loguj 'cdp_delivery_success'             │
│         - Błąd → zapisz w FailedDelivery + loguj 'cdp_delivery_failed' │
└─────────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. Mechanizm Retry (dla nieudanych dostaw)                   │
│    - Background job (consumer RabbitMQ lub cron)           │
│    - Wykrywanie FailedDelivery z status='pending'           │
│    - Obliczanie next_retry_at (exponential backoff)        │
│    - Ponowna próba wysłania                                 │
│    - Po max_retries → status='failed', eksport dla admina    │
└─────────────────────────────────────────────────────────────┘
```

### 2.2 Flow diagram - Tworzenie leada i wysyłka do CDP

```
POST /api/leads
    ↓
LeadController::create()
    ↓
LeadService::createLead()
    ├─> Transaction START
    ├─> CustomerService::findOrCreateCustomer() [deduplikacja]
    ├─> new Lead()
    ├─> LeadPropertyService::createProperty()
    ├─> EventService::logLeadCreated()
    └─> Transaction COMMIT
           ↓
    CDPDeliveryService::sendLeadToCDP()
        ↓
    foreach (cdpSystems as cdpSystem) {
        try {
            payload = CDPPayloadTransformer::transform(lead, cdpSystem)
            response = GuzzleClient::post(cdpSystem.apiUrl, payload)
            EventService::logCdpDeliverySuccess()
        } catch (Exception e) {
            FailedDelivery::create(lead, cdpSystem, e.message)
            EventService::logCdpDeliveryFailed()
        }
    }
```

### 2.3 Flow diagram - Retry mechanism

```
Background Job (RabbitMQ Consumer lub Cron)
    ↓
Query: FailedDelivery WHERE status='pending' AND next_retry_at <= NOW()
    ↓
foreach (failedDelivery in pendingDeliveries) {
    try {
        CDPDeliveryService::retrySend(failedDelivery)
        failedDelivery.setStatus('resolved')
        failedDelivery.setResolvedAt(NOW())
        EventService::logCdpRetrySuccess()
    } catch (Exception e) {
        failedDelivery.incrementRetryCount()
        if (retryCount >= maxRetries) {
            failedDelivery.setStatus('failed')
        } else {
            nextRetryAt = calculateNextRetryAt(retryCount) // exponential backoff
            failedDelivery.setNextRetryAt(nextRetryAt)
        }
        FailedDelivery.update()
        EventService::logCdpRetryFailed()
    }
}
```

## 3. Struktura komponentów

### 3.1 CDPDeliveryService - główny serwis

**Lokalizacja:** `src/ApiClient/CDPDeliveryService.php`

**Zmiany wymagane:**
- Zastąpienie mock implementation prawdziwymi HTTP requests (Guzzle)
- Obsługa retry mechanism wewnątrz serwisu
- Zapisywanie FailedDelivery do bazy danych
- Przygotowanie payload dla każdego systemu CDP

**Metody wymagane:**
```php
public function sendLeadToCDP(Lead $lead): void
public function retryFailedDelivery(FailedDelivery $failedDelivery): void
public function sendToSingleCDP(Lead $lead, string $cdpSystemName): void
private function buildPayload(Lead $lead, string $cdpSystemName): array
private function calculateNextRetryAt(int $retryCount): DateTimeInterface
```

### 3.2 CDPPayloadTransformer - transformacja danych

**Lokalizacja:** `src/ApiClient/CDPPayloadTransformer.php` (nowy)

**Przeznaczenie:** Przygotowanie danych leada w formacie wymaganym przez poszczególne systemy CDP

**Metody wymagane:**
```php
public function transformForSalesManago(Lead $lead): array
public function transformForMurapol(Lead $lead): array
public function transformForDomDevelopment(Lead $lead): array
```

**Różnice między systemami CDP:**
- SalesManago: email, phone, custom fields (preferences)
- Murapol: JSON z polami development_id, property_id
- DomDevelopment: XML lub JSON, określony format

### 3.3 FailedDeliveryService - zarządzanie nieudanymi dostawami

**Lokalizacja:** `src/Leads/FailedDeliveryService.php` (nowy)

**Przeznaczenie:** Serwis do zarządzania FailedDelivery entities (CRUD, retry, queries)

**Metody wymagane:**
```php
public function createFailedDelivery(Lead $lead, string $cdpSystem, string $errorMessage, ?string $errorCode = null): FailedDelivery
public function getPendingDeliveries(int $limit = 100): array
public function retryDelivery(FailedDelivery $failedDelivery): void
public function markAsResolved(FailedDelivery $failedDelivery): void
public function markAsFailed(FailedDelivery $failedDelivery): void
```

### 3.4 CDPSystemConfig - konfiguracja systemów CDP

**Lokalizacja:** `src/Infrastructure/Config/CDPSystemConfig.php` (nowy)

**Przeznaczenie:** Centralna konfiguracja dla każdego systemu CDP (URL, API key, enabled/disabled)

**Struktura:**
```php
class CDPSystemConfig {
    public function getConfig(string $systemName): array
    public function getApiUrl(string $systemName): string
    public function getApiKey(string $systemName): string
    public function isEnabled(string $systemName): bool
    public function getRetryConfig(string $systemName): array // max_retries, initial_delay, backoff_multiplier
}
```

### 3.5 Console Command - Retry Consumer

**Lokalizacja:** `src/Command/RetryCDPDeliveriesCommand.php` (nowy)

**Przeznaczenie:** Background job do przetwarzania pending deliveries i retry

**Usage:**
```bash
php bin/console app:retry-cdp-deliveries
# Lub w cron co 5 minut:
*/5 * * * * cd /path/to/project && php bin/console app:retry-cdp-deliveries
```

## 4. Szczegóły komponentów

### Komponent: CDPDeliveryService

**Opis:** Główny serwis odpowiedzialny za wysyłkę leadów do systemów CDP oraz obsługę retry dla nieudanych dostaw.

**Główne elementy:**
- HTTP Client (Guzzle) do komunikacji z CDP APIs
- Exponential backoff algorithm dla retry delays
- Transformacja danych (CDPPayloadTransformer)
- Zapisywanie FailedDelivery do bazy
- Event logging przez EventService

**Obsługiwane interakcje:**
- `sendLeadToCDP()` - automatyczna wysyłka po utworzeniu leada
- `retryFailedDelivery()` - ponowna wysyłka nieudanej dostawy
- `sendToSingleCDP()` - wysyłka do pojedynczego systemu CDP

**Obsługiwana walidacja:**
- Sprawdzanie czy system CDP jest enabled (CDPSystemConfig)
- Walidacja formatu danych przed wysłaniem
- Retry limit (maxRetries)
- Timeout dla HTTP requests (30 sekund)

**Typy:**
- `Lead` - lead do wysłania
- `FailedDelivery` - zapis nieudanej próby
- `array` - payload dla CDP API
- `DateTimeInterface` - next retry time

**Props/zależności:**
- `CDPPayloadTransformer`
- `FailedDeliveryService`
- `EventService`
- `CDPSystemConfig`
- `EntityManagerInterface`
- `GuzzleHttp\Client`

### Komponent: CDPPayloadTransformer

**Opis:** Transformuje dane leada do formatu wymaganego przez każdy system CDP.

**Główne elementy:**
- Metody transformacji dla każdego systemu CDP
- Mapowanie pól z LMS na CDP format
- Walidacja wymaganych pól przed transformacją

**Obsługiwane interakcje:**
- `transformForSalesManago()` - transformacja dla SalesManago
- `transformForMurapol()` - transformacja dla Murapol
- `transformForDomDevelopment()` - transformacja dla DomDevelopment

**Obsługiwana walidacja:**
- Sprawdzanie czy wszystkie wymagane pola są dostępne
- Format email i telefon
- Obecność development_id, property_id

**Typy:**
- `Lead` - input lead
- `array` - output payload
- `Customer`, `LeadProperty` - dane do transformacji

**Różnice między systemami:**

| System CDP | Format | Wymagane pola | Przykład |
|------------|--------|---------------|----------|
| SalesManago | JSON | email, phone | `{"email": "user@example.com", "phone": "+48123456789", "tags": ["lead"], "customFields": {"preferences": {...}}}` |
| Murapol | JSON | development_id, property_id, phone | `{"project_id": "123", "property_id": "456", "client": {"phone": "+48123456789"}}` |
| DomDevelopment | JSON | development_id, phone, email | `{"development": "123", "contact": {"email": "...", "phone": "..."}}` |

### Komponent: FailedDeliveryService

**Opis:** Zarządzanie nieudanymi dostawami - tworzenie, aktualizacja, retry.

**Główne elementy:**
- CRUD operations dla FailedDelivery
- Queries dla pending deliveries
- Retry count management
- Status transitions

**Obsługiwane interakcje:**
- `createFailedDelivery()` - utworzenie zapisu nieudanej dostawy
- `getPendingDeliveries()` - lista nieudanych dostaw do retry
- `retryDelivery()` - wykonanie retry
- `markAsResolved()` - oznaczenie jako rozwiązane
- `markAsFailed()` - oznaczenie jako finalnie nieudane (po max retries)

**Obsługiwana walidacja:**
- Retry count < maxRetries przed retry
- nextRetryAt <= NOW() przed retry
- Status transitions tylko dla dozwolonych stanów

**Typy:**
- `FailedDelivery` - entity
- `Lead` - powiązany lead
- `string` - status, errorCode, errorMessage
- `int` - retryCount, maxRetries
- `DateTimeInterface` - nextRetryAt, resolvedAt

**Statusy:**
- `pending` - oczekuje na retry
- `retrying` - obecnie w trakcie retry
- `failed` - nieudane po max retries
- `resolved` - pomyślnie wysłane po retry

### Komponent: CDPSystemConfig

**Opis:** Centralna konfiguracja systemów CDP (URL, API keys, enabled/disabled, retry settings).

**Główne elementy:**
- Konfiguracja dla SalesManago, Murapol, DomDevelopment
- Pobieranie z environment variables lub system_config tabeli
- Enabled/disabled flag dla każdego systemu

**Obsługiwane interakcje:**
- `getConfig()` - pełna konfiguracja dla systemu
- `getApiUrl()` - URL do API
- `getApiKey()` - API key do autoryzacji
- `isEnabled()` - czy system jest aktywny
- `getRetryConfig()` - konfiguracja retry

**Obsługiwana walidacja:**
- URL musi być prawidłowym URLem
- API key nie może być pusty dla enabled systems
- Retry config (max_retries, initial_delay, backoff_multiplier)

**Typy:**
- `string` - systemName, apiUrl, apiKey
- `array` - retryConfig
- `bool` - isEnabled

**Konfiguracja (environment variables):**
```env
# SalesManago
CDP_SALESMANAGO_ENABLED=true
CDP_SALESMANAGO_URL=https://api.salesmanago.com/v3/lead
CDP_SALESMANAGO_API_KEY=xxx
CDP_SALESMANAGO_API_SECRET=yyy

# Murapol
CDP_MURAPOL_ENABLED=true
CDP_MURAPOL_URL=https://api.murapol.pl/lead
CDP_MURAPOL_API_KEY=xxx

# DomDevelopment
CDP_DOMDEVELOPMENT_ENABLED=true
CDP_DOMDEVELOPMENT_URL=https://api.domdevelopment.pl/lead
CDP_DOMDEVELOPMENT_API_KEY=xxx
```

**Retry configuration:**
```php
'retry' => [
    'max_retries' => 3,
    'initial_delay_seconds' => 60,  // 1 minuta
    'backoff_multiplier' => 2,      // exponential: 1min, 2min, 4min
]
```

### Komponent: ExponentialBackoffCalculator

**Opis:** Oblicza next retry time używając exponential backoff algorithm.

**Główne elementy:**
- `calculate(int $retryCount, int $initialDelaySeconds, float $multiplier): DateTimeInterface`

**Obsługiwane interakcje:**
- Obliczanie nextRetryAt na podstawie retryCount

**Obsługiwana walidacja:**
- retryCount >= 0
- initialDelaySeconds > 0
- multiplier >= 1.0

**Przykładowe obliczenia:**
```
Retry 1: initialDelay (60s) = now + 1 minuta
Retry 2: initialDelay * multiplier^1 (60 * 2 = 120s) = now + 2 minuty
Retry 3: initialDelay * multiplier^2 (60 * 4 = 240s) = now + 4 minuty
```

## 5. Typy

### FailedDelivery entity

**Lokalizacja:** `src/Model/FailedDelivery.php` (już istnieje, wymaga aktualizacji)

**Pola:**
- `id` - int (primary key)
- `lead` - Lead (many-to-one)
- `cdpSystemName` - string(50) - nazwa systemu CDP
- `errorCode` - string(50) - kod błędu HTTP (404, 500, etc.)
- `errorMessage` - text - treść błędu
- `retryCount` - int (default: 0)
- `maxRetries` - int (default: 3)
- `nextRetryAt` - datetime - kiedy następna próba
- `status` - string(20) (pending, retrying, failed, resolved)
- `createdAt` - datetime
- `resolvedAt` - datetime (nullable)

**Metody helper:**
```php
public function canRetry(): bool // retryCount < maxRetries && now >= nextRetryAt
public function shouldRetryNow(): bool // status == 'pending' && now >= nextRetryAt
public function isFinalFailure(): bool // status == 'failed'
public function isResolved(): bool // status == 'resolved'
```

### DTO: RetryDeliveryResponse

**Lokalizacja:** `src/DTO/RetryDeliveryResponse.php` (nowy)

```php
class RetryDeliveryResponse {
    public function __construct(
        public readonly int $id,
        public readonly string $status,
        public readonly int $retryCount,
        public readonly ?DateTimeInterface $nextRetryAt,
        public readonly string $message
    ) {}
}
```

### DTO: FailedDeliveriesListResponse

**Lokalizacja:** `src/DTO/FailedDeliveriesListResponse.php` (nowy)

```php
class FailedDeliveriesListResponse {
    public function __construct(
        public readonly array $data, // FailedDeliveryDto[]
        public readonly PaginationDto $pagination
    ) {}
}
```

### DTO: FailedDeliveryDto

**Lokalizacja:** `src/DTO/FailedDeliveryDto.php` (nowy)

```php
class FailedDeliveryDto {
    public function __construct(
        public readonly int $id,
        public readonly string $leadUuid,
        public readonly string $cdpSystemName,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly int $retryCount,
        public readonly int $maxRetries,
        public readonly ?DateTimeInterface $nextRetryAt,
        public readonly string $status,
        public readonly DateTimeInterface $createdAt,
        public readonly LeadSummaryDto $lead
    ) {}
}
```

## 6. Zarządzanie stanem

### Stan delivery dla leada

Każdy lead może mieć wiele FailedDelivery records (jeden dla każdego systemu CDP):

```php
Lead
├─ failedDeliveries[] (one-to-many)
   ├─ FailedDelivery (SalesManago)
   ├─ FailedDelivery (Murapol)
   └─ FailedDelivery (DomDevelopment)
```

### Lifecycle statusów FailedDelivery

```
created (status: 'pending', retryCount: 0, nextRetryAt: NOW + 1min)
    ↓
[Background job próbuje wysłać]
    ↓
SUCCESS → resolved (status: 'resolved', resolvedAt: NOW)
    ↓
FAILURE → retry (status: 'pending', retryCount: 1, nextRetryAt: NOW + 2min)
    ↓
[Background job próbuje ponownie]
    ↓
SUCCESS → resolved
    ↓
FAILURE → retry (status: 'pending', retryCount: 2, nextRetryAt: NOW + 4min)
    ↓
[Background job próbuje ponownie]
    ↓
SUCCESS → resolved
    ↓
FAILURE → failed (status: 'failed', retryCount: 3)
```

### Persystencja stanu

- Wszystkie FailedDelivery w bazie danych (tabela `failed_deliveries`)
- Eventy w tabeli `events` (cdp_delivery_success, cdp_delivery_failed, cdp_retry_attempt)
- Konfiguracja systemów CDP w `system_config` tabeli lub environment variables

## 7. Integracja API

### Endpoint: GET /api/failed-deliveries

**Controller:** `src/Controller/FailedDeliveriesController.php` (nowy)

**Przeznaczenie:** Lista nieudanych dostaw dla administratorów

**Request:**
```http
GET /api/failed-deliveries?page=1&limit=20&status=pending&cdp_system_name=SalesManago
```

**Response:**
```json
{
    "data": [
        {
            "id": 123,
            "lead_uuid": "550e8400-e29b-41d4-a716-446655440000",
            "cdp_system_name": "SalesManago",
            "error_code": "500",
            "error_message": "Internal server error",
            "retry_count": 2,
            "max_retries": 3,
            "next_retry_at": "2025-01-15T14:30:00+00:00",
            "status": "pending",
            "created_at": "2025-01-15T14:00:00+00:00",
            "lead": {
                "id": 456,
                "lead_uuid": "550e8400-e29b-41d4-a716-446655440000",
                "customer": {
                    "email": "customer@example.com",
                    "phone": "+48123456789"
                }
            }
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 20,
        "total": 45,
        "last_page": 3
    }
}
```

### Endpoint: POST /api/failed-deliveries/{id}/retry

**Przeznaczenie:** Ręczne ponowienie wysłania nieudanej dostawy (dla administratorów)

**Request:**
```http
POST /api/failed-deliveries/123/retry
Authorization: Bearer {token}
```

**Response:**
```json
{
    "id": 123,
    "status": "retrying",
    "retry_count": 2,
    "next_retry_at": "2025-01-15T14:35:00+00:00",
    "message": "Retry initiated"
}
```

**Error responses:**
- `404 Not Found` - FailedDelivery nie istnieje
- `400 Bad Request` - retry limit exceeded lub status nie pozwala na retry
- `401 Unauthorized` - brak autoryzacji
- `403 Forbidden` - brak uprawnień ROLE_ADMIN

### Webhook/Payload format dla każdego systemu CDP

**SalesManago:**
```json
{
    "contact": {
        "email": "customer@example.com",
        "phone": "+48123456789",
        "name": "Jan Kowalski"
    },
    "tags": ["lead", "lms"],
    "customFields": {
        "lead_uuid": "550e8400-e29b-41d4-a716-446655440000",
        "application_name": "morizon",
        "preferences": {
            "price_min": 300000,
            "price_max": 600000,
            "location": "Warszawa",
            "city": "Warszawa"
        }
    }
}
```

**Murapol:**
```json
{
    "project_id": "123",
    "property_id": "456",
    "client": {
        "phone": "+48123456789",
        "email": "customer@example.com",
        "first_name": "Jan",
        "last_name": "Kowalski"
    },
    "metadata": {
        "lead_uuid": "550e8400-e29b-41d4-a716-446655440000"
    }
}
```

**DomDevelopment:**
```json
{
    "development_id": "123",
    "client": {
        "email": "customer@example.com",
        "phone": "+48123456789",
        "name": "Jan Kowalski"
    },
    "lead_uuid": "550e8400-e29b-41d4-a716-446655440000",
    "application_name": "gratka"
}
```

## 8. Interakcje użytkownika

### Przepływ: Administrator przegląda nieudane dostawy

```
1. Admin loguje się → Dashboard
    ↓
2. Admin klika "Nieudane dostawy" (sidebar)
    ↓
3. GET /api/failed-deliveries
    Response: lista nieudanych dostaw
    ↓
4. UI wyświetla tabelę:
   - Lead UUID
   - Klient (email + telefon)
   - System CDP
   - Kod błędu
   - Liczba prób / Max prób
   - Następna próba (datetime)
   - Status (color-coded badge)
   - Akcja: "Retry" button
    ↓
5. Admin klika "Retry" przy konkretnej dostawie
    ↓
6. POST /api/failed-deliveries/{id}/retry
    Response: {"status": "retrying", ...}
    ↓
7. UI aktualizuje status w tabeli: "pending" → "retrying"
    Toast: "Retry zainicjowany"
    ↓
8. Auto-refresh co 30s pokazuje progress
    Status zmienia się: "retrying" → "resolved" (sukces) lub "pending" (kolejna próba)
```

### Przepływ: Automatyczne retry przez background job

```
1. Background job (cron lub RabbitMQ consumer) uruchamiany co 5 minut
    ↓
2. Query: FailedDelivery WHERE status='pending' AND next_retry_at <= NOW()
    ↓
3. Dla każdego pending delivery:
    ↓
    CDPDeliveryService::retryFailedDelivery(failedDelivery)
        ↓
    a. Pobierz lead
    b. Pobierz konfigurację CDP
    c. Przygotuj payload (CDPPayloadTransformer)
    d. HTTP request (Guzzle)
    e. Obsługa odpowiedzi:
        ↓
        SUCCESS:
            - failedDelivery.setStatus('resolved')
            - failedDelivery.setResolvedAt(NOW())
            - EventService::logCdpRetrySuccess()
            - Logger::info('CDP retry successful')
        ↓
        FAILURE:
            - failedDelivery.incrementRetryCount()
            - if (retryCount >= maxRetries):
                failedDelivery.setStatus('failed')
            - else:
                nextRetryAt = calculateNextRetryAt(retryCount)
                failedDelivery.setNextRetryAt(nextRetryAt)
            - FailedDelivery.update()
            - EventService::logCdpRetryFailed()
    ↓
4. Background job zakończony
    Następne uruchomienie za 5 minut
```

## 9. Warunki i walidacja

### Warunek: Wysyłka leada do CDP

**Trigger:** Po utworzeniu leada w LMS (po commit transaction)

**Warunek:**
- Lead został pomyślnie zapisany w LMS (transakcja committed)
- Customer deduplication completed
- LeadProperty utworzone (jeśli data dostępna)

**Walidacja przed wysłaniem:**
- Sprawdź czy system CDP jest enabled (CDPSystemConfig)
- Sprawdź czy wszystkie wymagane pola są dostępne
- Waliduj format email i telefon

### Warunek: Retry mechanism

**Trigger:** Background job (cron lub RabbitMQ consumer)

**Warunek:**
- FailedDelivery.status == 'pending'
- FailedDelivery.nextRetryAt <= NOW()
- FailedDelivery.retryCount < maxRetries

**Walidacja przed retry:**
- Lead nadal istnieje (sprawdź czy nie został usunięty)
- System CDP nadal jest enabled
- Retry count < max retries

**Exponential backoff:**
```
Delay = initialDelaySeconds * (backoffMultiplier ^ retryCount)
Przykład (initial: 60s, multiplier: 2):
- Retry 1: 60 * 2^0 = 60s (1 min)
- Retry 2: 60 * 2^1 = 120s (2 min)
- Retry 3: 60 * 2^2 = 240s (4 min)
```

### Warunek: Ręczne ponowienie wysłania (admin)

**Trigger:** Admin klika "Retry" button w UI

**Warunek:**
- User ma role ROLE_ADMIN
- FailedDelivery istnieje
- FailedDelivery nie jest w status 'resolved' ani 'retrying'

**Walidacja:**
- Sprawdź uprawnienia (ROLE_ADMIN)
- Sprawdź status FailedDelivery (nie może być już 'resolved' lub 'retrying')
- Opcjonalnie: sprawdź czy nie przekroczono max retries

## 10. Obsługa błędów

### Scenariusz: Błąd podczas wysyłki do CDP

**Obsługa:**
1. Złap Exception w try-catch
2. Utwórz FailedDelivery record w bazie:
   ```php
   FailedDelivery::create($lead, $cdpSystem, $errorMessage, $errorCode)
   ```
3. Ustaw status='pending', retryCount=0, nextRetryAt=NOW() + initialDelay
4. Zaloguj event: 'cdp_delivery_failed'
5. **NIE przerywaj procesu tworzenia leada** - błąd CDP nie powinien fail-ować lead creation

**Error codes:**
- `400 Bad Request` - nieprawidłowe dane
- `401 Unauthorized` - nieprawidłowy API key
- `404 Not Found` - endpoint nie istnieje
- `500 Internal Server Error` - błąd po stronie CDP
- `503 Service Unavailable` - CDP temporary unavailable
- `TIMEOUT` - timeout po 30s

### Scenariusz: Retry limit exceeded

**Obsługa:**
1. Po 3 (maxRetries) nieudanych próbach
2. Ustaw FailedDelivery.status='failed'
3. Zaloguj event: 'cdp_delivery_final_failure'
4. Wyslij notification do admina (opcjonalnie)
5. FailedDelivery pozostaje w bazie do manual retry lub usunięcia

### Scenariusz: CDP system disabled

**Obsługa:**
1. Przed wysłaniem sprawdź CDPSystemConfig::isEnabled()
2. Jeśli disabled - pomiń wysyłkę do tego systemu
3. Zaloguj event: 'cdp_delivery_skipped' (system disabled)

### Scenariusz: Timeout podczas HTTP request

**Obsługa:**
1. Ustaw timeout w Guzzle (30 sekund)
2. Złap GuzzleException z timeout
3. Utwórz FailedDelivery z errorMessage='Timeout after 30s'
4. Ustaw errorCode='TIMEOUT'
5. Zaplanuj retry (nextRetryAt)

### Scenariusz: Invalid response format from CDP

**Obsługa:**
1. Po HTTP request sprawdź response body format
2. Jeśli nieprawidłowy - utwórz FailedDelivery z errorMessage='Invalid response format'
3. Zaloguj event z details zawierającymi otrzymaną odpowiedź

### Error handling strategy

**Poziomy obsługi błędów:**

1. **Logging** - wszystkie błędy logowane w `events` tabeli
2. **FailedDelivery storage** - nieudane dostawy w bazie danych
3. **Automatic retry** - exponential backoff
4. **Manual retry** - przez admina (POST /api/failed-deliveries/{id}/retry)
5. **Notifications** (opcjonalnie) - email alert do admina po 3 failures

## 11. Kroki implementacji

### Krok 1: Aktualizacja FailedDelivery entity

**Zadania:**
- [ ] Dodać metody helper do FailedDelivery: `canRetry()`, `shouldRetryNow()`, `isFinalFailure()`
- [ ] Dodać relację one-to-many w Lead entity: `$failedDeliveries`
- [ ] Migracja bazy danych (jeśli potrzebne zmiany w schema)

**Pliki:**
- `src/Model/FailedDelivery.php`
- `src/Model/Lead.php`
- `config/doctrine/FailedDelivery.orm.xml`
- `migrations/VersionYYYYMMDDHHMMSS.php`

### Krok 2: Utworzenie FailedDeliveryService

**Zadania:**
- [ ] Stwórz interface `FailedDeliveryServiceInterface`
- [ ] Implementacja `FailedDeliveryService`
- [ ] Metody: `createFailedDelivery()`, `getPendingDeliveries()`, `retryDelivery()`, `markAsResolved()`, `markAsFailed()`

**Pliki:**
- `src/Leads/FailedDeliveryService.php` (nowy)
- `src/Leads/FailedDeliveryServiceInterface.php` (nowy)

### Krok 3: Utworzenie CDPPayloadTransformer

**Zadania:**
- [ ] Stwórz interface `CDPPayloadTransformerInterface`
- [ ] Implementacja `CDPPayloadTransformer`
- [ ] Metody transformacji dla SalesManago, Murapol, DomDevelopment
- [ ] Testy jednostkowe dla każdej metody transformacji

**Pliki:**
- `src/ApiClient/CDPPayloadTransformer.php` (nowy)
- `src/ApiClient/CDPPayloadTransformerInterface.php` (nowy)
- `tests/Unit/ApiClient/CDPPayloadTransformerTest.php`

### Krok 4: Utworzenie CDPSystemConfig

**Zadania:**
- [ ] Stwórz klasę `CDPSystemConfig`
- [ ] Pobieranie config z environment variables lub `system_config` tabeli
- [ ] Metody: `getConfig()`, `getApiUrl()`, `getApiKey()`, `isEnabled()`, `getRetryConfig()`

**Pliki:**
- `src/Infrastructure/Config/CDPSystemConfig.php` (nowy)

### Krok 5: Utworzenie ExponentialBackoffCalculator

**Zadania:**
- [ ] Stwórz klasę `ExponentialBackoffCalculator`
- [ ] Metoda `calculate(int $retryCount, int $initialDelaySeconds, float $multiplier): DateTimeInterface`
- [ ] Testy jednostkowe

**Pliki:**
- `src/Infrastructure/ExponentialBackoffCalculator.php` (nowy)
- `tests/Unit/Infrastructure/ExponentialBackoffCalculatorTest.php`

### Krok 6: Aktualizacja CDPDeliveryService

**Zadania:**
- [ ] Zastąp mock implementation prawdziwymi HTTP requests (Guzzle)
- [ ] Dodaj dependency injection: CDPPayloadTransformer, FailedDeliveryService, CDPSystemConfig, ExponentialBackoffCalculator
- [ ] Implementacja `sendLeadToCDP()` - prawdziwa wysyłka do CDP
- [ ] Implementacja `retryFailedDelivery()` - retry dla FailedDelivery
- [ ] Implementacja `sendToSingleCDP()` - wysyłka do pojedynczego systemu
- [ ] Obsługa errors i zapisywanie FailedDelivery
- [ ] Testy jednostkowe

**Pliki:**
- `src/ApiClient/CDPDeliveryService.php` (aktualizacja)
- `src/ApiClient/CDPDeliveryServiceInterface.php` (aktualizacja jeśli potrzebne)
- `tests/Unit/ApiClient/CDPDeliveryServiceTest.php`

### Krok 7: Utworzenie FailedDeliveriesController

**Zadania:**
- [ ] Stwórz `FailedDeliveriesController`
- [ ] Endpoint GET /api/failed-deliveries (lista)
- [ ] Endpoint POST /api/failed-deliveries/{id}/retry (ręczne retry)
- [ ] Walidacja uprawnień (ROLE_ADMIN)
- [ ] Response DTOs: FailedDeliveriesListResponse, RetryDeliveryResponse
- [ ] Testy funkcjonalne

**Pliki:**
- `src/Controller/FailedDeliveriesController.php` (nowy)
- `src/DTO/FailedDeliveriesListResponse.php` (nowy)
- `src/DTO/FailedDeliveryDto.php` (nowy)
- `src/DTO/RetryDeliveryResponse.php` (nowy)
- `tests/Functional/Controller/FailedDeliveriesControllerTest.php`

### Krok 8: Utworzenie Background Job Command

**Zadania:**
- [ ] Stwórz `RetryCDPDeliveriesCommand`
- [ ] Query: FailedDelivery WHERE status='pending' AND next_retry_at <= NOW()
- [ ] Dla każdego: wywołaj CDPDeliveryService::retryFailedDelivery()
- [ ] Logowanie wyników
- [ ] Testy jednostkowe

**Pliki:**
- `src/Command/RetryCDPDeliveriesCommand.php` (nowy)
- `tests/Unit/Command/RetryCDPDeliveriesCommandTest.php`

### Krok 9: Aktualizacja EventService

**Zadania:**
- [ ] Dodaj metody do EventServiceInterface:
  - `logCdpRetrySuccess(FailedDelivery $failedDelivery): Event`
  - `logCdpRetryFailed(FailedDelivery $failedDelivery, string $errorMessage): Event`
  - `logCdpDeliverySkipped(Lead $lead, string $cdpSystem, string $reason): Event`
- [ ] Implementacja w EventService
- [ ] Testy jednostkowe

**Pliki:**
- `src/Leads/EventService.php` (aktualizacja)
- `src/Leads/EventServiceInterface.php` (aktualizacja)

### Krok 10: Konfiguracja i environment variables

**Zadania:**
- [ ] Dodać environment variables do `.env.example`:
  - CDP_SALESMANAGO_*
  - CDP_MURAPOL_*
  - CDP_DOMDEVELOPMENT_*
- [ ] Dokumentacja konfiguracji w README.md
- [ ] Przygotować przykładowe wartości dla development

**Pliki:**
- `.env.example` (aktualizacja)
- `README.md` (aktualizacja)

### Krok 11: RabbitMQ Integration (opcjonalnie, future)

**Zadania:**
- [ ] Setup RabbitMQ in docker-compose.yml
- [ ] Stwórz Queue: 'cdp_deliveries'
- [ ] Stwórz Consumer: CDPDeliveryConsumer
- [ ] Zmień CDPDeliveryService aby publish do RabbitMQ zamiast bezpośredniej wysyłki
- [ ] Background worker (consumer) przetwarza wiadomości
- [ ] Testy integracyjne

**Pliki:**
- `src/Message/CDPLeadMessage.php` (nowy)
- `src/MessageHandler/CDPLeadMessageHandler.php` (nowy)
- `docker/docker-compose.yml` (aktualizacja)

### Krok 12: Testy

**Zadania:**
- [ ] Testy jednostkowe:
  - CDPPayloadTransformer (dla każdego systemu)
  - FailedDeliveryService
  - ExponentialBackoffCalculator
  - CDPDeliveryService (mock HTTP client)
- [ ] Testy integracyjne:
  - Przepływ utworzenia leada + wysyłka do CDP
  - Retry mechanism
  - FailedDelivery CRUD
- [ ] Testy E2E:
  - API endpointów dla administratorów
  - Background job retry
- [ ] Testy z mock HTTP responses (OK, 500, timeout)

**Pliki:**
- `tests/Unit/ApiClient/CDPDeliveryServiceTest.php`
- `tests/Unit/ApiClient/CDPPayloadTransformerTest.php`
- `tests/Unit/Leads/FailedDeliveryServiceTest.php`
- `tests/Unit/Infrastructure/ExponentialBackoffCalculatorTest.php`
- `tests/Functional/Controller/FailedDeliveriesControllerTest.php`
- `tests/Integration/CDPDeliveryFlowTest.php`
- `tests/E2E/CDPDeliveryE2ETest.php`

### Krok 13: UI dla administratorów (opcjonalnie)

**Zadania:**
- [ ] Widok listy failed deliveries (`/failed-deliveries`)
- [ ] Tabela z filtrami: status, CDP system, data
- [ ] Przycisk "Retry" przy każdym failed delivery
- [ ] Badge notyfikacji z liczbą failed deliveries
- [ ] Auto-refresh co 60s

**Pliki:**
- `templates/failed_deliveries/index.html.twig`
- `src/Controller/FailedDeliveriesViewController.php`

### Krok 14: Monitoring i alerting (opcjonalnie, future)

**Zadania:**
- [ ] Dashboard z metrykami: liczba failed deliveries, success rate
- [ ] Email notifications dla administratorów gdy duża liczba failures
- [ ] Logging CDP delivery metrics

## 12. Konfiguracja cron dla background job

Dodać do crontab:

```bash
# Retry failed CDP deliveries co 5 minut
*/5 * * * * cd /path/to/project && php bin/console app:retry-cdp-deliveries >> /dev/null 2>&1
```

## 13. Dokumentacja dla administratorów

### Konfiguracja systemów CDP

Każdy system CDP wymaga konfiguracji w environment variables:

1. **SalesManago:**
   ```env
   CDP_SALESMANAGO_ENABLED=true
   CDP_SALESMANAGO_URL=https://api.salesmanago.com/v3/lead
   CDP_SALESMANAGO_API_KEY=your-api-key
   CDP_SALESMANAGO_API_SECRET=your-api-secret
   ```

2. **Murapol:**
   ```env
   CDP_MURAPOL_ENABLED=true
   CDP_MURAPOL_URL=https://api.murapol.pl/lead
   CDP_MURAPOL_API_KEY=your-api-key
   ```

3. **DomDevelopment:**
   ```env
   CDP_DOMDEVELOPMENT_ENABLED=true
   CDP_DOMDEVELOPMENT_URL=https://api.domdevelopment.pl/lead
   CDP_DOMDEVELOPMENT_API_KEY=your-api-key
   ```

### Retry configuration

Konfiguracja retry mechanism (domyślnie w kodzie, możliwe do zmiany w system_config):

```php
'retry' => [
    'max_retries' => 3,              // maksymalna liczba prób
    'initial_delay_seconds' => 60,  // opóźnienie przed pierwszą próbą (1 min)
    'backoff_multiplier' => 2,       // mnożnik exponential backoff
]
```

Przykład:
- Próba 1: po 1 minucie
- Próba 2: po 2 minutach
- Próba 3: po 4 minutach
- Po 3 próbach → status='failed'

### Monitoring nieudanych dostaw

Administrator może przeglądać listę nieudanych dostaw:

1. Przejdź do `/failed-deliveries` w panelu
2. Filtruj według:
   - Status (pending, failed, resolved)
   - System CDP
   - Zakres dat
3. W razie potrzeby kliknij "Retry" aby ręcznie ponownie wysłać

### Manual retry

Jeśli automatyczny retry nie zadziałał, administrator może ręcznie ponowić wysłanie:

```bash
# Przez UI
POST /api/failed-deliveries/{id}/retry

# Lub przez command line
php bin/console app:retry-cdp-deliveries
```

### Logi i debugging

Wszystkie CDP delivery events logowane w tabeli `events`:

- `cdp_delivery_success` - pomyślna wysyłka
- `cdp_delivery_failed` - nieudana wysyłka
- `cdp_retry_attempt` - próba retry
- `cdp_retry_success` - sukces po retry
- `cdp_retry_failed` - nieudana retry
- `cdp_delivery_final_failure` - final failure po max retries
- `cdp_delivery_skipped` - pominięta wysyłka (system disabled)

## 14. Zgodność z wymaganiami PRD

### PRD 3.8 - Wysyłka do systemów CDP

**Wymagania:**
- ✅ Automatyczne przekazywanie leadów do systemów CDP
- ✅ Proces: najpierw zapisanie leada w LMS, potem próba wysłania do CDP
- ✅ Mechanizm retry z exponential backoff w przypadku błędów
- ✅ Możliwość ręcznego ponownego wysłania przez administratorów

**Implementacja:**
- ✅ CDPDeliveryService wysyła leady automatycznie po utworzeniu
- ✅ LeadService tworzy leada w transakcji, CDP delivery po commit
- ✅ FailedDeliveryService implementuje exponential backoff
- ✅ API endpoint POST /api/failed-deliveries/{id}/retry dla adminów

### Metryki sukcesu (PRD 6.2)

- **Skuteczność dostarczenia do CDP:** 98% leadów wysłanych pomyślnie (po retry)
- **Czas odpowiedzi API:** maksymalnie 3 sekundy (asynchroniczna wysyłka do CDP nie blokuje API response)
- **Monitorowanie:** Tabela failed_deliveries + events dla audytu

