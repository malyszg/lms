# Plan implementacji widoku Eventów

## 1. Przegląd

Panel eventów to narzędzie administracyjne służące do monitorowania i audytowania działania systemu LMS. Widok pozwala na przeglądanie historii zdarzeń systemowych, filtrowanie po różnych kryteriach oraz wyświetlanie szczegółów każdego eventu w czytelnej formie.

**Główny cel:** Umożliwienie administratorom przeglądania historii wszystkich operacji w systemie w celu monitorowania działania, diagnozowania problemów i zapewnienia zgodności z wymaganiami audytu.

**User Story:** US-011 - Zarządzanie eventami

## 2. Routing widoku

- **URL:** `/events`
- **Nazwa route:** `events_index`
- **Metoda:** GET
- **Dostęp:** Tylko ROLE_ADMIN
- **Kontroler:** `EventsViewController::index()`

## 3. Struktura komponentów

```
events/index.html.twig (main template)
├── _filters.html.twig (partial)
│   └── Zaawansowane filtry (typ, encja, lead UUID, zakres dat)
├── _table.html.twig (partial, HTMX target)
│   ├── Tabela z listą eventów
│   └── Accordion ze szczegółami przy każdym wierszu
└── Paginacja (components/pagination.html.twig)
```

**Zależność:** Wykorzystanie istniejących komponentów:
- `base.html.twig` - layout główny z sidebar i header
- `components/pagination.html.twig` - paginacja
- `components/sidebar.html.twig` - nawigacja z linkiem "Eventy"

## 4. Szczegóły komponentów

### events/index.html.twig
- **Opis:** Główny template widoku eventów
- **Główne elementy:**
  - Include filters partial
  - Include table partial
  - Include pagination
  - HTMX polling dla auto-odświeżania (opcjonalnie)
- **Obsługiwane interakcje:**
  - Renderowanie pełnej strony po wczytaniu
  - Obsługa HTMX request dla częściowych update'ów (filtry, paginacja)
- **Obsługiwana walidacja:** Brak - widok tylko do odczytu
- **Propsy:**
  - `events` (array<EventDto>) - lista eventów do wyświetlenia
  - `pagination` (array) - dane paginacji
  - `filters` (array) - obecne filtry

### events/_filters.html.twig
- **Opis:** Zaawansowane filtry dla eventów
- **Główne elementy:**
  - Dropdown: Typ eventu (lead_created, lead_updated, lead_deleted, cdp_delivery_success, cdp_delivery_failed, customer_preferences_changed, login_attempt, logout, password_change)
  - Dropdown: Typ encji (lead, customer, failed_delivery, user, api)
  - Input number: ID encji
  - Input number: ID użytkownika
  - Input text: Lead UUID
  - Date picker: Data od
  - Date picker: Data do
  - Button: "Zastosuj filtry"
  - Button/link: "Wyczyść"
- **Obsługiwane interakcje:**
  - Zmiana wartości w polach
  - Kliknięcie "Zastosuj" → HTMX GET /events?{params}
  - Kliknięcie "Wyczyść" → HTMX GET /events
- **Obsługiwana walidacja:**
  - Data od ≤ Data do
  - Format daty (ISO 8601 lub YYYY-MM-DD)
  - Lead UUID format (UUID v4)
- **Propsy:**
  - `filters` (array) - obecne wartości filtrów

### events/_table.html.twig
- **Opis:** Tabela z listą eventów
- **Główne elementy:**
  - Table headers: ID | Typ eventu | Encja | Użytkownik | IP | Data i czas | Szczegóły
  - Table rows z accordion na szczegóły
  - Bootstrap Icons dla typów eventów (✓ sukces, ✗ błąd, ℹ info, ⚠ warning)
  - Badge kolorystyczne dla typów eventów
- **Obsługiwane interakcje:**
  - Click na przycisk "Szczegóły" → rozwinięcie accordion
  - Accordion pokazuje JSON details w `精神<pre>` z syntax highlighting
- **Obsługiwana walidacja:** Brak
- **Propsy:**
  - `events` (array<EventDto>) - lista eventów
  - `currentUser` (User|null) - zalogowany użytkownik (do RBAC)

### events/_details.html.twig (accordion content)
- **Opis:** Szczegóły eventu w accordion
- **Główne elementy:**
  - Pełny JSON z pola `details` w czytelnym formacie
  - Preformatted text z `white-space: pre-wrap`
  - Syntax highlighting (optional - light background)
  - Lista wszystkich pól eventu (includes user agent, error message if any)
- **Obsługiwane interakcje:**
  - Wyświetlanie/ukrywanie na click
- **Obsługiwana walidacja:** Brak
- **Propsy:**
  - `event` (EventDto) - event do wyświetlenia

## 5. Typy

### EventDto (już istnieje)
```php
class EventDto {
    public readonly int $id;
    public readonly string $eventType;
    public readonly ?string $entityType;
    public readonly ?int $entityId;
    public readonly ?int $userId;
    public readonly ?array $details;
    public readonly ?string $ipAddress;
    public readonly DateTimeInterface $createdAt;
}
```

### Filtry (DTO do utworzenia)
```php
class EventFiltersDto {
    public readonly ?string $eventType;
    public readonly ?string $entityType;
    public readonly ?int $entityId;
    public readonly ?int $userId;
    public readonly ?string $leadUuid;
    public readonly ?string $createdFrom;  // ISO 8601
    public readonly ?string $createdTo;     // ISO 8601
}
```

### Response dla widoku
```php
// W EventsViewController - zwraca array dla Twig
return [
    'events' => array<EventDto>,
    'pagination' => [
        'currentPage' => int,
        'perPage' => int,
        'total' => int,
        'lastPage' => int,
        'from' => int,
        'to' => int,
        'hasNext' => bool,
        'hasPrevious' => bool
    ],
    'filters' => array
];
```

## 6. Zarządzanie stanem

### URL Query Parameters
Stan widoku (filtry, paginacja) jest trzymany w URL parameters:
- `page` - numer strony (default: 1)
- `limit` - liczba elementów na stronę (default: 50, max: 200)
- `event_type` - typ eventu
- `entity_type` - typ encji
- `entity_id` - ID encji
- `user_id` - ID użytkownika
- `lead_uuid` - UUID leada
- `created_from` - data od (ISO 8601)
- `created_to` - data do (ISO 8601)

### Umożliwia to:
- Bookmarking konkretnych widoków
- Sharing linków z filtrami
- Browser back/forward
- Reload strony bez utraty stanu

### HTMX State Management
```html
<!-- Filtry -->
<form hx-get="/events" hx-target="#events-table" hx-swap="outerHTML">
  <!-- inputs -->
</form>

<!-- Paginacja -->
<a href="?page={{ next_page }}" 
   hx-get="/events?page={{ next_page }}&{{ params }}"
   hx-target="#events-table"
   hx-swap="outerHTML"
   hx-push-url="true">
  Następna
</a>
```

## 7. Integracja API

### Endpoint: GET /api/events

**Query Parameters:** (zgodnie z api-plan.md)
- `page` (integer, default: 1)
- `limit` (integer, default: 50, max: 200)
- `event_type` (string) - typ eventu do filtrowania
- `entity_type` (string) - typ encji
- `entity_id` (integer) - ID encji
- `user_id` (integer) - ID użytkownika
- `created_from` (datetime) - data od
- `created_to` (datetime) - data do
- `lead_uuid` (string) - UUID leada

**Response:**
```json
{
  "data": [
    {
      "id": "integer",
      "event_type": "string",
      "entity_type": "string",
      "entity_id": "integer",
      "user_id": "integer",
      "details": "object",
      "ip_address": "string",
      "created_at": "datetime"
    }
  ],
  "pagination": {
    "current_page": "integer",
    "per_page": "integer",
    "total": "integer",
    "last_page": "integer"
  }
}
```

**Auth:** Wymaga zalogowanego użytkownika (ROLE_ADMIN)

### Implementacja w EventsViewController

```php
public function index(Request $request): Response
{
    // Parse filters from query
    $filters = EventFiltersDto::fromRequest($request);
    
    // Get pagination params
    $page = max(1, (int)$request->query->get('page', 1));
    $limit = min(200, max(1, (int)$request->query->get('limit', 50)));
    
    // Query events from database
    $response = $this->eventViewService->getEventsList($filters, $page, $limit);
    
    // HTMX partial update
    if ($request->headers->get('HX-Request')) {
        return $this->render('events/_table.html.twig', [
            'events' => $response->events,
            'pagination' => $response->pagination
        ]);
    }
    
    // Full page render
    return $this->render('events/index.html.twig', [
        'events' => $response->events,
        'pagination' => $response->pagination,
        'filters' => $filters->toArray()
    ]);
}
```

## 8. Interakcje użytkownika

### 8.1 Ładowanie widoku
1. Użytkownik klika "Eventy" w sidebar
2. GET /events
3. Controller pobiera eventy z bazy (strona 1, limit 50)
4. Render pełnej strony z tabelą

### 8.2 Filtrowanie
1. Użytkownik wybiera "Typ eventu" z dropdown → np. "lead_created"
2. Użytkownik wybiera "Typ encji" → np. "lead"
3. Użytkownik wprowadza "Lead UUID" → np. "123e4567-e89b-12d3-a456-426614174000"
4. Użytkownik wprowadza "Data od" → "2025-01-01"
5. Użytkownik kliknie "Zastosuj filtry"
6. HTMX: GET /events?event_type=lead_created&entity_type=lead&lead_uuid=...&created_from=...
7. Partial swap: tylko tabela się aktualizuje
8. URL się zmienia (hx-push-url)

### 8.3 Wyświetlanie szczegółów
1. Użytkownik klika "Szczegóły" przy konkretnym evencie
2. Accordion się rozwija (Bootstrap collapse)
3. Wyświetla się JSON z pola `details` w `<pre>` z syntax highlighting
4. Użytkownik klika ponownie "Szczegóły"
5. Accordion się zwija

### 8.4 Paginacja
1. Użytkownik klika "Następna" lub numer strony
2. HTMX: GET /events?page=2&{filters}
3. Partial swap: tabela się aktualizuje
4. URL się zmienia (hx-push-url)

### 8.5 Wyczyść filtry
1. Użytkownik klika "Wyczyść"
2. HTMX: GET /events
3. Wszystkie filtry resetowane, tabela pokazuje wszystkie eventy

## 9. Warunki i walidacja

### 9.1 Warunki dostępu
- **ROLE_ADMIN:** Pełny dostęp do wszystkich eventów
- **ROLE_CALL_CENTER / ROLE_BOK:** W przyszłości - tylko swoje eventy (nie w MVP)

### 9.2 Walidacja filtrów
- **Typ eventu:** Musi być jednym z: lead_created, lead_updated, lead_deleted, cdp_delivery_success, cdp_delivery_failed, customer_preferences_changed, login_attempt, logout, password_change
- **Typ encji:** Musi być jednym z: lead, customer, failed_delivery, user, api
- **ID encji:** Musi być dodatnią liczbą całkowitą
- **ID użytkownika:** Musi być dodatnią liczbą całkowitą
- **Lead UUID:** Musi być poprawnym UUID v4 (regex)
- **Data od:** Musi być poprawnym ISO 8601 lub YYYY-MM-DD
- **Data do:** Musi być poprawnym ISO 8601 lub YYYY-MM-DD
- **Data od ≤ Data do:** Walidacja po stronie backend

### 9.3 Limit paginacji
- **Minimum:** 1
- **Maximum:** 200 (zgodnie z UI Plan - większy niż standardowe listy)
- **Default:** 50

## 10. Obsługa błędów

### 10.1 Brak uprawnień (403)
```php
// W EventsViewController
#[Route('/events', name: 'events_index', methods: ['GET'])]
#[IsGranted('ROLE_ADMIN')]
public function index(Request $request): Response
{
    // Symfony automatycznie zwróci 403 jeśli brak uprawnień
}
```
**UI:** Przekierowanie na 403 page z komunikatem "Nie masz uprawnień do przeglądania eventów"

### 10.2 Nieprawidłowe parametry
- **Nieprawidłowy format daty:** Message: "Nieprawidłowy format daty. Użyj YYYY-MM-DD"
- **Nieprawidłowy UUID:** Message: "Nieprawidłowy format UUID"
- **Data od > Data do:** Message: "Data 'od' musi być wcześniejsza lub równa dacie 'do'"
- **ID < 0:** Message: "ID musi być dodatnią liczbą"

### 10.3 Błędy bazodanowe
- **Timeout query:** Message: "Trwa zbyt długo. Spróbuj zawęzić filtry"
- **Connection error:** Message: "Błąd połączenia z bazą danych. Spróbuj ponownie"
- **Logowanie:** Logger::error() w controller

### 10.4 Empty state
```twig
{% if events is empty %}
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i>
        Brak eventów spełniających kryteria filtrowania.
        <a href="{{ path('events_index') }}" class="alert-link">
            Pokaż wszystkie eventy
        </a>
    </div>
{% endif %}
```

## 11. Kroki implementacji

### Krok 1: Utworzenie EventViewService
- Utworzyć `src/Service/EventViewService.php`
- Utworzyć `src/Service/EventViewServiceInterface.php`
- Implementować metodę `getEventsList(EventFiltersDto, int, int): EventsListResponse`
- Query builder z Doctrine dla filtrowania
- Mapowanie Event entity → EventDto
- Paginacja (Doctrine Paginator)

### Krok 2: Utworzenie EventFiltersDto
- Utworzyć `src/DTO/EventFiltersDto.php`
- Metoda statyczna `fromRequest(Request): EventFiltersDto`
- Walidacja parametrów

### Krok 3: Aktualizacja EventsViewController
- Rozbudować `EventsViewController::index()` 
- Obsługa filtrów z query params
- Obsługa paginacji
- Obsługa HTMX requests (partial updates)
- Error handling
- Dodaj `#IsGranted('ROLE_ADMIN')`

### Krok 4: Utworzenie templates
- `templates/events/index.html.twig` - main template
- `templates/events/_filters.html.twig` - filters partial
- `templates/events/_table.html.twig` - table partial (HTMX target)
- Wykorzystać `base.html.twig` jako parent

### Krok 5: Stylowanie i ikony
- Bootstrap Icons dla typów eventów:
  - ✓ (sukces) - text-success
  - ✗ (błąd) - text-danger  
  - ℹ (info) - text-info
  - ⚠ (warning) - text-warning
- Badge kolorystyczne dla typów eventów
- Accordion styling dla szczegółów

### Krok 6: HTMX integration
- Filtry: hx-get, hx-target="#events-table"
- Paginacja: hx-get, hx-push-url="true"
- Loading indicators (htmx-indicator)
- Error handling (htmx:responseError)

### Krok 7: Testowanie
- Testy Unit: EventViewService
- Testy Functional: EventsViewController
- Testy E2E: Przepływ filtrowania, paginacji, szczegółów
- Walidacja odpowiedzi przy różnych rolach

### Krok 8: Dokumentacja
- Zaktualizować APP_STATUS.md
- Dodać screenshot widoku eventów
- Dodać do sidenav link w sidebar.html.twig
- Update README.md jeśli potrzebne

### Krok 9: Polish & Optimization
- Sprawdź wydajność query przy dużej liczbie eventów
- Dodaj indexy jeśli potrzeba (already istnieją: idx_event_type_created, idx_entity, idx_user_id, idx_created_at)
- Responsive design dla mobile (opcjonalnie - nie priorytet)
- Accessibility improvements

### Krok 10: Automatyczne usuwanie starych eventów (cron job)
- Utworzyć Command: `app:cleanup-old-events`
- Usuwanie eventów starszych niż 1 rok (zgodnie z PRD 3.9)
- Dodaj do crontab lub Symfony scheduler
- Dodać testy dla command

