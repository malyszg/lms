# Plan implementacji widoku Nieudane dostawy

## 1. Przegląd

Widok "Nieudane dostawy" (`/failed-deliveries`) umożliwia administratorom systemu monitorowanie i zarządzanie leadami, które nie zostały pomyślnie wysłane do systemów CDP (Customer Data Platform). Widok wyświetla listę failed deliveries z możliwością filtrowania, przeglądania szczegółów oraz ręcznego ponowienia wysłania.

**Główny cel:** Zgodnie z US-007, administrator musi mieć możliwość zobaczenia listy leadów z błędami dostarczenia i ręcznego ponownego wysłania ich do systemów CDP.

**Dostęp:** Tylko dla roli `ROLE_ADMIN` (zgodnie z PRD US-007 "Jako administrator")

## 2. Routing widoku

### Ścieżki routingu

**View Controller (`FailedDeliveriesViewController`):**
- `GET /failed-deliveries` - główny widok listy nieudanych dostaw
  - Name: `failed_deliveries_index`
  - Wymaga: `ROLE_ADMIN`

**API Controller (`FailedDeliveriesController`):**
- `GET /api/failed-deliveries` - lista failed deliveries z paginacją i filtrowaniem
  - Name: `api_failed_deliveries_list`
  - Wymaga: `ROLE_ADMIN`
- `POST /api/failed-deliveries/{id}/retry` - ręczne ponowienie wysłania
  - Name: `api_failed_deliveries_retry`
  - Wymaga: `ROLE_ADMIN`
- `GET /api/failed-deliveries/count` - liczba nieudanych dostaw (dla badge)
  - Name: `api_failed_deliveries_count`
  - Wymaga: `ROLE_USER` (dostępne dla wszystkich zalogowanych do wyświetlenia badge)

### Struktura URL

```
/failed-deliveries                    - widok HTML
/api/failed-deliveries?page=1&limit=20&status=pending&cdp_system_name=SalesManago
/api/failed-deliveries/123/retry     - POST retry
/api/failed-deliveries/count          - GET liczba nieudanych dostaw
```

## 3. Struktura komponentów

### Główny layout
```
failed-deliveries/
├── index.html.twig              # Główny widok (rozszerza base.html.twig)
├── _list.html.twig               # Tabela failed deliveries (partial, HTMX target)
├── _filters.html.twig            # Sekcja filtrów (partial)
└── _details.html.twig            # Slider szczegółów failed delivery (partial)
```

### Relacje komponentów

```
index.html.twig (base template)
├── Komponent filtrów (_filters.html.twig)
├── Komponent listy (_list.html.twig) ← HTMX target
├── Slider overlay (dla szczegółów)
└── Toast notifications (sukces/błąd)
```

## 4. Szczegóły komponentów

### Komponent: Index (Failed Deliveries View)

**Lokalizacja:** `templates/failed_deliveries/index.html.twig`

**Opis:** Główny widok listy nieudanych dostaw. Składa się z sekcji filtrów, tabeli oraz obsługi slidera szczegółów.

**Główne elementy:**
- **Sidebar navigation** - link "Nieudane dostawy" z badge licznika
- **Header** - tytuł strony "Nieudane dostawy"
- **Sekcja filtrów** - rozwijalna sekcja z polami: status, CDP system, zakres dat
- **Tabela failed deliveries** - kolumny: Lead UUID, Klient, System CDP, Kod błędu, Liczba prób, Następna próba, Status, Akcja
- **Paginacja** - klasyczna paginacja z linkami poprzednia/następna oraz liczby stron
- **Button "Retry"** - tylko dla statusu 'pending' lub 'failed', bez przekroczenia max retries
- **Badge notyfikacji** - liczba nieudanych dostaw w sidebar menu (polling co 60s)
- **Slider szczegółów** - off-canvas panel z prawej strony (otwiera się przy kliknięciu w wiersz)

**Obsługiwane interakcje:**
- Filtrowanie po statusie (dropdown: pending, retrying, failed, resolved)
- Filtrowanie po CDP system (dropdown: SalesManago, Murapol, DomDevelopment)
- Filtrowanie po zakresie dat (date range picker)
- Sortowanie po dacie utworzenia (domyślnie DESC - najnowsze na górze)
- Paginacja - zmiana strony i liczby elementów na stronę (20/50/100)
- Kliknięcie w wiersz - otwarcie slidera szczegółów
- Kliknięcie "Retry" - ponowienie wysłania z confirmation modal
- Auto-refresh co 60 sekund - aktualizacja badge w sidebar + opcjonalnie odświeżenie listy

**Obsługiwana walidacja:**
- Walidacja filtrów:
  - Status musi być jednym z: pending, retrying, failed, resolved
  - CDP system musi być jednym z konfigurowanych systemów
  - Data "od" musi być wcześniejsza niż data "do"
- Walidacja retry:
  - Tylko dla statusu 'pending' lub 'failed'
  - Retry count < max retries (backend sprawdza przez `canRetry()`)
  - Użytkownik musi mieć rolę ROLE_ADMIN

**Typy:**
- `FailedDeliveryDto` - DTO reprezentujące pojedynczą failed delivery
- `FailedDeliveriesListResponse` - response zawierający array DTOs + pagination
- `RetryDeliveryResponse` - response z retry operation
- `ErrorResponseDto` - response dla błędów

**Propsy:**
- `failedDeliveries` - array FailedDeliveryDto
- `pagination` - PaginationDto
- `filters` - obiekt z aktualnymi wartościami filtrów (status, cdpSystemName, dateFrom, dateTo)
- `app.user` - zalogowany użytkownik (dla sprawdzenia uprawnień)

### Komponent: List (_list.html.twig)

**Opis:** Partial z tabelą failed deliveries. Target dla HTMX update.

**Główne elementy:**
- Tabela z kolumnami:
  1. Lead UUID (link do szczegółów leada) - `lead.leadUuid`
  2. Klient (email + telefon) - `lead.customer.email`, `lead.customer.phone`
  3. System CDP - `cdpSystemName`
  4. Kod błędu - `errorCode` lub "-"
  5. Liczba prób / Max prób - `retryCount / maxRetries`
  6. Następna próba - `nextRetryAt` (sformatowany) lub "-"
  7. Status - badge z kolorami (pending=yellow, retrying=blue, failed=red, resolved=green)
  8. Akcja - button "Retry" (tylko jeśli `canRetry()`)

**Obsługiwane interakcje:**
- Kliknięcie w wiersz - trigger `hx-get` do szczegółów failed delivery
- Kliknięcie "Retry" - trigger `hx-post` z confirmation modal
- HTMX swap: `hx-swap="outerHTML"` - zamiana całego wiersza po retry
- HTMX indicator: loading spinner podczas operacji

**HTMX attributes:**
```html
<tr hx-get="/failed-deliveries/details/{{ id }}" 
    hx-target="#slider-container" 
    hx-swap="innerHTML"
    style="cursor: pointer;">
```

### Komponent: Filters (_filters.html.twig)

**Opis:** Rozwijalna sekcja filtrów z możliwością filtrowania listy.

**Główne elementy:**
- Input: Status (dropdown)
  - Opcje: pending, retrying, failed, resolved, all
- Input: System CDP (dropdown)
  - Opcje: SalesManago, Murapol, DomDevelopment, all
- Input: Data od (date picker)
- Input: Data do (date picker)
- Button: "Zastosuj filtry" - trigger HTMX get do API
- Button: "Wyczyść filtry" - reset filtrów do domyślnych wartości

**Obsługiwane interakcje:**
- Zmiana wartości filtrów - URL query params są aktualizowane przez HTMX
- Kliknięcie "Zastosuj" - `hx-get` z aktualnymi wartościami filtrów
- Kliknięcie "Wyczyść" - `hx-get` bez query params
- Submit formy - automatic HTMX request

**HTMX attributes:**
```html
<form hx-get="/api/failed-deliveries" 
      hx-target="#failed-deliveries-list" 
      hx-swap="innerHTML"
      hx-push-url="true">
```

### Komponent: Details Slider (_details.html.twig)

**Opis:** Off-canvas panel z prawej strony wyświetlający szczegóły failed delivery.

**Główne elementy:**
- Header: "Szczegóły nieudanej dostawy #{{ id }}" + przycisk zamknij
- Sekcja: Informacje o leadzie
  - Lead UUID (link do leada)
  - Klient (email, telefon, imię, nazwisko)
  - Aplikacja źródłowa
  - Status leada
- Sekcja: Informacje o dostawie
  - System CDP
  - Status dostawy
  - Kod błędu
  - Komunikat błędu
  - Liczba prób / Max prób
  - Data utworzenia
  - Następna próba retry
  - Data rozwiązania (jeśli resolved)
- Sekcja: Historia prób
  - Timeline eventów związanych z tą failed delivery
  - Pokazuje wszystkie retry attempts z czasem i statusem
- Button: "Retry teraz" (jeśli `canRetry()`)
- Button: "Zobacz szczegóły leada" - otwiera slider leada
- Button: "Zamknij"

**Obsługiwane interakcje:**
- Zamknięcie slidera - kliknięcie X lub przycisk "Zamknij"
- Kliknięcie "Retry teraz" - confirmation modal → POST retry
- Kliknięcie "Zobacz szczegóły leada" - zamiana zawartości slidera na szczegóły leada
- Kliknięcie w event w timeline - rozwijanie accordion ze szczegółami

**HTMX attributes:**
```html
<div class="offcanvas offcanvas-end show">
  <!-- Slider content -->
  
  <button hx-post="/api/failed-deliveries/{{ id }}/retry"
          hx-target="#delivery-{{ id }}"
          hx-swap="outerHTML"
          hx-confirm="Czy na pewno chcesz ponowić dostawę?">
    Retry teraz
  </button>
</div>
```

## 5. Typy

### Typy danych (DTO)

#### FailedDeliveryDto

**Lokalizacja:** `src/DTO/FailedDeliveryDto.php`

**Struktura:**
```php
class FailedDeliveryDto {
    public readonly int $id;
    public readonly string $leadUuid;
    public readonly string $cdpSystemName;
    public readonly ?string $errorCode;
    public readonly ?string $errorMessage;
    public readonly int $retryCount;
    public readonly int $maxRetries;
    public readonly ?DateTimeInterface $nextRetryAt;
    public readonly string $status;
    public readonly DateTimeInterface $createdAt;
    public readonly LeadSummaryDto $lead;
}
```

**Opis:** DTO reprezentujące pojedynczą failed delivery z informacjami o leadzie.

#### FailedDeliveriesListResponse

**Lokalizacja:** `src/DTO/FailedDeliveriesListResponse.php`

**Struktura:**
```php
class FailedDeliveriesListResponse {
    public readonly array $data; // FailedDeliveryDto[]
    public readonly PaginationDto $pagination;
}
```

**Opis:** Response zawierający listę failed deliveries oraz informacje o paginacji.

#### RetryDeliveryResponse

**Lokalizacja:** `src/DTO/RetryDeliveryResponse.php`

**Struktura:**
```php
class RetryDeliveryResponse {
    public readonly int $id;
    public readonly string $status;
    public readonly int $retryCount;
    public readonly ?DateTimeInterface $nextRetryAt;
    public readonly string $message;
}
```

**Opis:** Response z operacji retry zawierający zaktualizowany status i retry count.

#### ErrorResponseDto

**Lokalizacja:** `src/DTO/ErrorResponseDto.php`

**Struktura:**
```php
class ErrorResponseDto {
    public readonly string $error;
    public readonly string $message;
    public readonly ?array $details;
}
```

**Opis:** Standardowy response dla błędów API.

#### PaginationDto

**Lokalizacja:** `src/DTO/PaginationDto.php`

**Struktura:**
```php
class PaginationDto {
    public readonly int $currentPage;
    public readonly int $perPage;
    public readonly int $total;
    public readonly int $lastPage;
    public readonly int $from;
    public readonly int $to;
    public readonly bool $hasNext;
    public readonly bool $hasPrevious;
}
```

**Opis:** Informacje o paginacji dla listy.

### Typy statusów

**Dostępne statusy failed delivery:**
- `pending` - oczekuje na retry
- `retrying` - w trakcie ponownego wysłania
- `failed` - przekroczony limit retry (finalne niepowodzenie)
- `resolved` - pomyślnie wysłane (po retry)

**Badge kolory (Bootstrap):**
- `pending`: `badge bg-warning`
- `retrying`: `badge bg-info`
- `failed`: `badge bg-danger`
- `resolved`: `badge bg-success`

## 6. Zarządzanie stanem

### URL Query Parameters

**Stan przechowywany w URL:**
- `page` - bieżąca strona (default: 1)
- `limit` - liczba elementów na stronę (default: 20, max: 100)
- `status` - filtr po statusie (pending, retrying, failed, resolved)
- `cdp_system_name` - filtr po systemie CDP
- `created_from` - data od (format: Y-m-d)
- `created_to` - data do (format: Y-m-d)

**Zalety:**
- Bookmarkowanie filtrowanych widoków
- Sharing linków z filtrami
- Browser back/forward działa poprawnie
- HTMX push-url automatycznie aktualizuje URL

**Przykład:**
```
/failed-deliveries?page=2&limit=50&status=failed&cdp_system_name=SalesManago
```

### Session Storage

**Cachowanie w `sessionStorage`:**
- Opcje dropdownów (CDP systems, statuses) - ładowane raz i cachowane
- User info (role) - do sprawdzania uprawnień UI

### Polling mechanism

**Auto-refresh badge:**
```html
<span id="failed-deliveries-badge"
      hx-get="/api/failed-deliveries/count"
      hx-trigger="every 60s"
      hx-swap="innerHTML">
</span>
```

**Interval:** 60 sekund (co minutę)

**Wydajność:** Endpoint `/api/failed-deliveries/count` wykonuje prosty COUNT query, więc jest szybki.

## 7. Integracja API

### GET /api/failed-deliveries

**Method:** GET  
**Endpoint:** `/api/failed-deliveries`  
**Authentication:** Required (JWT token)  
**Authorization:** `ROLE_ADMIN` only

**Query Parameters:**
```
?page=1&limit=20&status=pending&cdp_system_name=SalesManago&created_from=2025-01-01&created_to=2025-01-31
```

**Response:**
```json
{
  "data": [
    {
      "id": 123,
      "leadUuid": "550e8400-e29b-41d4-a716-446655440000",
      "cdpSystemName": "SalesManago",
      "errorCode": "500",
      "errorMessage": "Internal Server Error",
      "retryCount": 2,
      "maxRetries": 3,
      "nextRetryAt": "2025-01-20T10:30:00+00:00",
      "status": "pending",
      "createdAt": "2025-01-20T09:00:00+00:00",
      "lead": {
        "id": 456,
        "leadUuid": "550e8400-e29b-41d4-a716-446655440000",
        "status": "new",
        "applicationName": "morizon",
        "createdAt": "2025-01-20T08:30:00+00:00"
      }
    }
  ],
  "pagination": {
    "currentPage": 1,
    "perPage": 20,
    "total": 45,
    "lastPage": 3,
    "from": 1,
    "to": 20,
    "hasNext": true,
    "hasPrevious": false
  }
}
```

**Walidacja:**
- `page` >= 1
- `limit` >= 1 && <= 100
- `status` in ['pending', 'retrying', 'failed', 'resolved']
- `cdp_system_name` - mus być jednym z configured CDP systems
- `created_from`, `created_to` - format daty Y-m-d

**Obsługa błędów:**
- `400 Bad Request` - nieprawidłowe parametry
- `401 Unauthorized` - brak autoryzacji
- `403 Forbidden` - brak uprawnień ROLE_ADMIN
- `500 Internal Server Error` - błąd serwera

### POST /api/failed-deliveries/{id}/retry

**Method:** POST  
**Endpoint:** `/api/failed-deliveries/{id}/retry`  
**Authentication:** Required (JWT token)  
**Authorization:** `ROLE_ADMIN` only

**Response (success):**
```json
{
  "id": 123,
  "status": "retrying",
  "retryCount": 3,
  "nextRetryAt": null,
  "message": "Retry initiated"
}
```

**Response (error):**
```json
{
  "error": "already_resolved",
  "message": "This delivery is already resolved"
}
```

**Walidacja:**
- FailedDelivery musi istnieć (404 jeśli nie)
- Status nie może być 'resolved' lub 'retrying' (400 jeśli jest)
- `canRetry()` musi zwrócić true (400 jeśli nie - exceeded max retries)

**Obsługa błędów:**
- `400 Bad Request` - cannot retry (already resolved, max retries exceeded)
- `401 Unauthorized` - brak autoryzacji
- `403 Forbidden` - brak uprawnień ROLE_ADMIN
- `404 Not Found` - failed delivery not found
- `500 Internal Server Error` - błąd podczas retry (network error, CDP API error)

### GET /api/failed-deliveries/count

**Method:** GET  
**Endpoint:** `/api/failed-deliveries/count`  
**Authentication:** Required (JWT token)  
**Authorization:** `ROLE_USER` (wszyscy zalogowani - dla badge)

**Response:**
```html
<span class="badge bg-danger ms-2">15</span>
```

**Albo pusty string jeśli count = 0**

**Obsługa błędów:**
- `401 Unauthorized` - brak autoryzacji
- `500 Internal Server Error` - błąd serwera

### Używane istniejące klasy

**Już zaimplementowane w kodzie:**
- `FailedDeliveriesController` - API endpoints (już gotowy)
- `FailedDeliveryService` - business logic (już gotowy)
- `CDPDeliveryService::retryFailedDelivery()` - retry logic (już gotowy)
- `FailedDelivery` model - entity (już gotowy)
- Wszystkie DTOs (już gotowe)

**Wymagane rozszerzenia:**
- `FailedDeliveriesViewController::count()` - implementacja faktycznego liczenia
- Template `templates/failed_deliveries/index.html.twig` - implementacja UI

## 8. Interakcje użytkownika

### Flow 1: Przeglądanie nieudanych dostaw

```
1. Użytkownik (Admin) klika "Nieudane dostawy" w sidebar
   ↓
2. GET /failed-deliveries
   ↓
3. Wyświetlenie strony index.html.twig z pustą tabelą
   ↓
4. Auto HTMX request: GET /api/failed-deliveries?page=1&limit=20
   ↓
5. Renderowanie tabeli z danymi
```

### Flow 2: Filtrowanie po statusie i CDP systemie

```
1. User wybiera status="failed" z dropdown
   ↓
2. User wybiera CDP system="SalesManago" z dropdown
   ↓
3. User klika "Zastosuj filtry"
   ↓
4. HTMX: GET /api/failed-deliveries?status=failed&cdp_system_name=SalesManago
   ↓
5. URL jest aktualizowany (push-url: true)
   ↓
6. Tabela jest odświeżona z nowymi filtrami
```

### Flow 3: Ręczne ponowienie wysłania (Retry)

```
1. User klika button "Retry" przy failed delivery
   ↓
2. Modal potwierdzenia:
   "Czy na pewno chcesz ponowić dostawę do SalesManago dla leada {UUID}?"
   Przyciski: Anuluj | Potwierdź
   ↓
3a. User klika "Anuluj"
     → Modal zamknięty, brak akcji

3b. User klika "Potwierdź"
     ↓
     4. HTMX: POST /api/failed-deliveries/{id}/retry
     ↓
     5. Backend: CDPDeliveryService::retryFailedDelivery()
     ↓
     6a. Sukces
         - Status zmieniony na 'retrying'
         - Response: {id, status: 'retrying', retryCount, message}
         - HTMX swap wiersza w tabeli (nowy status)
         - Toast: "Retry zainicjowany pomyślnie"
         - Badge licznika aktualizowany
     
     6b. Błąd
         - Response: ErrorResponseDto
         - Toast: "Błąd podczas retry: {message}"
```

### Flow 4: Szczegóły failed delivery (slider)

```
1. User klika w wiersz tabeli
   ↓
2. HTMX: GET /failed-deliveries/details/{id}
   ↓
3. Render slidera _details.html.twig
   ↓
4. Wyświetlenie szczegółów:
   - Informacje o leadzie
   - Status dostawy
   - Kod i komunikat błędu
   - Historia prób (retry count, next retry at)
   - Timeline eventów
```

### Flow 5: Auto-refresh badge

```
1. Strona załadowana
   ↓
2. HTMX polling every 60s: GET /api/failed-deliveries/count
   ↓
3. Response: HTML badge z liczbą (np. "<span class='badge'>15</span>")
   ↓
4. HTMX swap: Zastąpienie badge w sidebar
   ↓
5. Repeat co 60s
```

### Flow 6: Paginacja

```
1. User klika "Następna" lub numer strony
   ↓
2. HTMX: GET /api/failed-deliveries?page=2&limit=20
   ↓
3. Renderowanie nowej strony tabeli
   ↓
4. URL zaktualizowany (push-url: true)
   ↓
5. User może użyć browser back/forward
```

## 9. Warunki i walidacja

### Walidacja po stronie serwera (backend)

#### List endpoint (GET /api/failed-deliveries)

**Query params validation:**
```php
// page >= 1
if ($page < 1) {
    throw new BadRequestHttpException('Page must be >= 1');
}

// limit >= 1 && <= 100
if ($limit < 1 || $limit > 100) {
    throw new BadRequestHttpException('Limit must be between 1 and 100');
}

// status in allowed values
if ($status && !in_array($status, ['pending', 'retrying', 'failed', 'resolved'])) {
    throw new BadRequestHttpException('Invalid status');
}

// cdp_system_name must be configured
if ($cdpSystemName && !$this->systemConfig->isConfigured($cdpSystemName)) {
    throw new BadRequestHttpException('Unknown CDP system');
}

// date format validation
if ($createdFrom && !\DateTime::createFromFormat('Y-m-d', $createdFrom)) {
    throw new BadRequestHttpException('Invalid date format (created_from)');
}
```

#### Retry endpoint (POST /api/failed-deliveries/{id}/retry)

**Business logic validation:**
```php
// FailedDelivery must exist
if (!$failedDelivery) {
    throw new NotFoundHttpException('Failed delivery not found');
}

// Status validation
if ($failedDelivery->isResolved()) {
    throw new BadRequestHttpException('This delivery is already resolved');
}

// Retry eligibility
if (!$failedDelivery->canRetry()) {
    throw new BadRequestHttpException('Cannot retry: retry limit exceeded or status invalid');
}
```

### Walidacja po stronie klienta (frontend)

#### Formularz filtrów

**HTML5 validation:**
```html
<select name="status" required>
  <option value="">Wszystkie</option>
  <option value="pending">Pending</option>
  <option value="retrying">Retrying</option>
  <option value="failed">Failed</option>
  <option value="resolved">Resolved</option>
</select>

<input type="date" name="created_from" id="created_from">
<input type="date" name="created_to" id="created_to" min="">
```

**JavaScript validation (app.js):**
```javascript
// Ensure "to" date is after "from" date
const fromDate = document.getElementById('created_from');
const toDate = document.getElementById('created_to');

fromDate.addEventListener('change', function() {
    toDate.min = fromDate.value;
});

toDate.addEventListener('change', function() {
    if (toDate.value < fromDate.value) {
        alert('Data "do" musi być późniejsza niż data "od"');
        toDate.value = '';
    }
});
```

#### Retry button state

**Wyświetlanie przycisku tylko gdy:**
```twig
{% if failedDelivery.canRetry() and is_granted('ROLE_ADMIN') %}
    <button class="btn btn-warning btn-sm" 
            hx-post="{{ path('api_failed_deliveries_retry', {id: failedDelivery.id}) }}"
            hx-target="#delivery-{{ failedDelivery.id }}"
            hx-confirm="Czy na pewno chcesz ponowić dostawę?">
        Retry
    </button>
{% endif %}
```

### Warunki biznesowe

#### Retry eligibility

**Można retry'ować gdy:**
- Status = 'pending' LUB 'failed'
- Retry count < max retries (default: 3)
- FailedDelivery nie jest resolved
- Użytkownik ma rolę ROLE_ADMIN

**NIE można retry'ować gdy:**
- Status = 'retrying' (już w trakcie retry)
- Status = 'resolved' (już pomyślnie wysłane)
- Retry count >= max retries (przekroczony limit)

#### Status transitions

**Dozwolone przejścia statusów:**
```
pending → retrying → (success) resolved
                   → (failure, retries < max) pending
                   → (failure, retries >= max) failed

failed → retrying (manual retry by admin)
```

## 10. Obsługa błędów

### Frontend error handling

#### API Error Response

**Struktura:**
```json
{
  "error": "error_code",
  "message": "Human-readable error message",
  "details": {
    // Optional additional details
  }
}
```

#### Typy błędów i obsługa

**400 Bad Request** - Błędne parametry request:
```javascript
// HTMX on error event
htmx.on('htmx:responseError', function(event) {
    if (event.detail.xhr.status === 400) {
        showToast('Nieprawidłowe parametry żądania', 'error');
    }
});
```

**401 Unauthorized** - Brak autoryzacji:
```javascript
htmx.on('htmx:responseError', function(event) {
    if (event.detail.xhr.status === 401) {
        window.location.href = '/login?redirect=/failed-deliveries';
    }
});
```

**403 Forbidden** - Brak uprawnień (nie powinno się zdarzyć dla admina):
```javascript
htmx.on('htmx:responseError', function(event) {
    if (event.detail.xhr.status === 403) {
        showToast('Nie masz uprawnień do tej operacji', 'error');
    }
});
```

**404 Not Found** - Failed delivery nie istnieje:
```javascript
htmx.on('htmx:responseError', function(event) {
    if (event.detail.xhr.status === 404) {
        showToast('Nie znaleziono nieudanej dostawy', 'error');
        // Refresh list
        htmx.ajax('GET', '/api/failed-deliveries', {target: '#failed-deliveries-list'});
    }
});
```

**500 Internal Server Error** - Błąd serwera:
```javascript
htmx.on('htmx:responseError', function(event) {
    if (event.detail.xhr.status === 500) {
        const error = JSON.parse(event.detail.xhr.responseText);
        showToast(`Błąd serwera: ${error.message}`, 'error');
    }
});
```

#### Toast notifications

**Sukces:**
- "Retry zainicjowany pomyślnie" (green)
- "Lista odświeżona" (info)
- "Filtry zastosowane" (info)

**Błędy:**
- "Nie można retry'ować: limit przekroczony" (red)
- "Nie można retry'ować: już resolved" (warning)
- "Błąd podczas retry: {error message}" (red)
- "Nie udało się załadować listy" (red)

#### Network errors

**Brak połączenia z serwerem:**
```javascript
htmx.on('htmx:sseError', function(event) {
    showToast('Brak połączenia z serwerem. Sprawdź połączenie internetowe.', 'error');
});

// Add retry button
document.getElementById('retry-connection').style.display = 'block';
```

**Timeout:**
```javascript
htmx.config.timeout = 10000; // 10 seconds default

htmx.on('htmx:timeout', function(event) {
    showToast('Timeout: żądanie trwa zbyt długo', 'error');
});
```

### Backend error logging

**Wszystkie błędy logowane w:**
- Symfony logger (app.log)
- Tabela events (jeśli wymagane)

**Przykład:**
```php
$this->logger->error('Failed to retry delivery', [
    'failed_delivery_id' => $id,
    'error' => $e->getMessage(),
    'user_id' => $this->getUser()->getId(),
    'ip_address' => $request->getClientIp(),
]);
```

## 11. Kroki implementacji

### Krok 1: Implementacja FailedDeliveriesViewController (View)

**Plik:** `src/Controller/FailedDeliveriesViewController.php`

**Aktualizacje:**
1. Usuń stub z `count()` method
2. Implementuj faktyczne liczenie z bazy danych:
```php
public function count(): Response
{
    $repository = $this->entityManager->getRepository(FailedDelivery::class);
    $count = (int)$repository->createQueryBuilder('fd')
        ->select('COUNT(fd.id)')
        ->where('fd.status IN (:statuses)')
        ->setParameter('statuses', ['pending', 'retrying', 'failed'])
        ->getQuery()
        ->getSingleScalarResult();
    
    if ($count === 0) {
        return new Response('');
    }
    
    return new Response("<span class='badge bg-danger ms-2'>{$count}</span>");
}
```

### Krok 2: Stworzenie szablonu index.html.twig

**Plik:** `templates/failed_deliveries/index.html.twig`

**Implementacja:**
1. Rozszerz `base.html.twig`
2. Dodaj strukturę z sekcją filtrów
3. Dodaj placeholder dla tabeli (`_list.html.twig` jako partial)
4. Dodaj HTMX polling dla badge
5. Dodaj slider container

### Krok 3: Stworzenie partiala _list.html.twig

**Plik:** `templates/failed_deliveries/_list.html.twig`

**Implementacja:**
1. Tabela z kolumnami zgodnie z specyfikacją
2. HTMX attributes na wierszach (kliknięcie = szczegóły)
3. HTMX attributes na przycisku Retry
4. Conditional rendering przycisku Retry (tylko dla `canRetry()`)
5. Kolorowe badge dla statusów

### Krok 4: Stworzenie partiala _filters.html.twig

**Plik:** `templates/failed_deliveries/_filters.html.twig`

**Implementacja:**
1. Formularz z inputami dla filtrów
2. Dropdowny dla status i CDP system
3. Date pickery dla zakresu dat
4. HTMX attributes na formularzu
5. Button "Wyczyść" do resetu filtrów

### Krok 5: Stworzenie partiala _details.html.twig

**Plik:** `templates/failed_deliveries/_details.html.twig`

**Implementacja:**
1. Off-canvas panel z prawej
2. Sekcje z informacjami o failed delivery
3. Historia prób (retry count, next retry)
4. Timeline eventów związanych z delivery
5. Przyciski: Retry teraz, Zobacz leada, Zamknij

### Krok 6: Integracja z istniejącymi API

**Weryfikacja:**
1. `FailedDeliveriesController::list()` - działa poprawnie ✓
2. `FailedDeliveriesController::retry()` - działa poprawnie ✓
3. `FailedDeliveriesViewController::count()` - wymaga implementacji (krok 1)

### Krok 7: Dodanie routingu

**Plik:** `config/routes.yaml`

**Weryfikacja istniejących routów:**
- `GET /failed-deliveries` → `FailedDeliveriesViewController::index()` ✓
- `GET /api/failed-deliveries` → `FailedDeliveriesController::list()` ✓
- `POST /api/failed-deliveries/{id}/retry` → `FailedDeliveriesController::retry()` ✓
- `GET /failed-deliveries/count` → `FailedDeliveriesViewController::count()` ✓

**Dodatkowy routing dla szczegółów (opcjonalnie):**
```yaml
failed_deliveries_details:
    path: /failed-deliveries/details/{id}
    controller: App\Controller\FailedDeliveriesViewController::details
    methods: GET
```

### Krok 8: Dodanie metody details() do FailedDeliveriesViewController

**Plik:** `src/Controller/FailedDeliveriesViewController.php`

**Implementacja:**
```php
#[Route('/failed-deliveries/details/{id}', name: 'failed_deliveries_details', methods: ['GET'])]
public function details(int $id): Response
{
    $failedDelivery = $this->failedDeliveryService->findById($id);
    
    if (!$failedDelivery) {
        return new Response('Failed delivery not found', Response::HTTP_NOT_FOUND);
    }
    
    return $this->render('failed_deliveries/_details.html.twig', [
        'failedDelivery' => $failedDelivery,
    ]);
}
```

### Krok 9: Aktualizacja sidebar navigation

**Plik:** `templates/components/sidebar.html.twig`

**Dodaj link z badge:**
```twig
<li class="nav-item">
    <a class="nav-link text-white" href="{{ path('failed_deliveries_index') }}">
        <i class="bi bi-exclamation-triangle"></i> Nieudane dostawy
        <span id="failed-deliveries-badge"
              hx-get="{{ path('api_failed_deliveries_count') }}"
              hx-trigger="every 60s"
              hx-swap="innerHTML">
            <!-- Badge dynamically updated -->
        </span>
    </a>
</li>
```

### Krok 10: Integracja z istniejącym systemem

**Controller authorization:**
- Odkomentować `#[IsGranted('ROLE_ADMIN')]` w `FailedDeliveriesViewController::index()`
- Odkomentować w `FailedDeliveriesController` (już jest ustawiony)

**Sidebar visibility:**
- Link "Nieudane dostawy" wyświetlany dla wszystkich zalogowanych
- Badge wyświetlany dla wszystkich
- Dostęp do szczegółów wymaga ROLE_ADMIN

### Krok 11: Styling i UX

**Bootstrap classes:**
- Użyj istniejących klas Bootstrap 5
- Kolorowe badge dla statusów
- Button styling dla akcji
- Responsive table

**HTMX indicators:**
- Loading spinner podczas retry
- Disable button podczas request
- Visual feedback (toast notifications)

### Krok 12: Testowanie

**Manual testing:**
1. Zaloguj się jako admin
2. Przejdź do /failed-deliveries
3. Sprawdź wyświetlanie tabeli
4. Przetestuj filtry
5. Przetestuj retry functionality
6. Sprawdź badge polling
7. Sprawdź szczegóły (slider)

**Automated testing (opcjonalnie):**
1. Unit test dla `FailedDeliveriesViewController::count()`
2. Functional test dla retry flow
3. E2E test dla pełnego flow (otwórz widok, retry, weryfikuj sukces)

---

## Podsumowanie

Plan implementacji widoku "Nieudane dostawy" wykorzystuje istniejące komponenty i API, minimalizując ilość nowego kodu. Główne zadania to implementacja UI (Twig templates) oraz rozszerzenie view controllera o metodę `count()`. Wszystkie pozostałe komponenty (API controller, service, model, DTOs) są już zaimplementowane i gotowe do użycia.

