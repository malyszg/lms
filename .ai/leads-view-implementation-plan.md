# Plan implementacji widoku Dashboard (Lista leadów)

## 1. Przegląd

Widok Dashboard (Lista leadów) jest głównym widokiem aplikacji LMS po zalogowaniu użytkownika. Jego celem jest prezentacja wszystkich leadów w czytelnej formie tabelarycznej z możliwością filtrowania, sortowania i paginacji. Widok umożliwia pracownikom call center szybki dostęp do informacji o klientach oraz efektywne zarządzanie leadami.

Widok składa się z następujących głównych elementów:
- Sidebar navigation (nawigacja boczna)
- Header z informacjami użytkownika
- Sekcja statystyk (leady dzisiaj, nieudane dostawy, liczba klientów)
- Sekcja filtrów (podstawowe i zaawansowane)
- Tabela leadów z danymi klientów i nieruchomości
- Paginacja
- Dynamiczny slider szczegółów leada (ładowany przez HTMX)
- Auto-odświeżanie z polling co 30 sekund

## 2. Routing widoku

### Route Symfony:
```php
#[Route('/', name: 'leads_index', methods: ['GET'])]
#[Route('/leads', name: 'leads_list', methods: ['GET'])]
```

### URL z parametrami filtrowania:
```
/?page=1&limit=20&status=new&application_name=morizon&created_from=2025-01-01&created_to=2025-12-31&sort=created_at&order=desc
```

### Obsługiwane query parameters:
- `page` (integer, default: 1) - numer strony
- `limit` (integer, default: 20, max: 100) - liczba elementów na stronie
- `status` (string: new|contacted|qualified|converted|rejected) - filtr statusu
- `application_name` (string) - filtr aplikacji źródłowej
- `customer_email` (string) - filtr po emailu klienta
- `customer_phone` (string) - filtr po telefonie klienta
- `created_from` (datetime) - data początkowa
- `created_to` (datetime) - data końcowa
- `sort` (string: created_at|status|application_name) - pole sortowania
- `order` (string: asc|desc, default: desc) - kierunek sortowania

## 3. Struktura komponentów

```
templates/
├── base.html.twig                          # Layout bazowy
├── components/
│   ├── sidebar.html.twig                   # Nawigacja boczna (współdzielona)
│   ├── header.html.twig                    # Header z info użytkownika (współdzielony)
│   ├── pagination.html.twig                # Komponent paginacji (reusable)
│   └── toast.html.twig                     # Powiadomienia toast (reusable)
├── leads/
│   ├── index.html.twig                     # Główny widok Dashboard
│   ├── _stats.html.twig                    # Partial: statystyki (HTMX target)
│   ├── _filters.html.twig                  # Partial: sekcja filtrów
│   ├── _advanced_filters.html.twig         # Partial: filtry zaawansowane (rozwijane)
│   ├── _table.html.twig                    # Partial: tabela leadów (HTMX target)
│   ├── _table_row.html.twig                # Partial: pojedynczy wiersz tabeli
│   ├── _new_leads_notification.html.twig   # Partial: badge nowych leadów (HTMX target)
│   └── _details_slider.html.twig           # Partial: slider szczegółów leada (HTMX target)
```

### Hierarchia komponentów:
```
base.html.twig
├── components/sidebar.html.twig
├── components/header.html.twig
└── leads/index.html.twig
    ├── leads/_stats.html.twig
    ├── leads/_filters.html.twig
    │   └── leads/_advanced_filters.html.twig
    ├── leads/_new_leads_notification.html.twig
    ├── leads/_table.html.twig
    │   └── leads/_table_row.html.twig (loop)
    ├── components/pagination.html.twig
    └── leads/_details_slider.html.twig (dynamiczny)
```

## 4. Szczegóły komponentów

### 4.1 `leads/index.html.twig` - Główny widok Dashboard

**Opis komponentu:**
Główny template widoku Dashboard, który rozszerza `base.html.twig` i orchestruje wszystkie komponenty częściowe. Odpowiada za layout strony i inicjalizację HTMX polling dla auto-odświeżania.

**Główne elementy HTML:**
- `<div class="container-fluid">` - główny kontener
- `<div id="stats-container">` - kontener statystyk (HTMX target)
- `<div id="new-leads-notification">` - kontener notyfikacji (HTMX target)
- `<form id="filters-form">` - formularz filtrów
- `<div id="leads-table-container">` - kontener tabeli (HTMX target)
- `<div id="pagination-container">` - kontener paginacji (HTMX target)
- `<div id="slider-container">` - kontener slidera szczegółów (HTMX target)

**Komponenty dzieci:**
- `{% include 'leads/_stats.html.twig' %}`
- `{% include 'leads/_filters.html.twig' %}`
- `{% include 'leads/_new_leads_notification.html.twig' %}`
- `{% include 'leads/_table.html.twig' %}`
- `{% include 'components/pagination.html.twig' %}`

**Obsługiwane zdarzenia:**
- Inicjalizacja HTMX polling dla statystyk (co 60s)
- Inicjalizacja HTMX polling dla nowych leadów (co 30s)
- Obsługa submit formularza filtrów (HTMX)
- Obsługa kliknięć w wiersze tabeli (HTMX)

**Warunki walidacji:**
- Sprawdzenie czy użytkownik jest zalogowany (`app.user` exists)
- Sprawdzenie uprawnień do widoku (minimum role: BOK, CALL_CENTER, ADMIN)

**Typy:**
- `LeadsListViewModel` - model widoku z danymi leadów, filtrami, paginacją
- `LeadItemDto` - pojedynczy lead w liście
- `PaginationDto` - dane paginacji

**Propsy od kontrolera:**
```php
[
    'leads' => LeadItemDto[],
    'pagination' => PaginationDto,
    'filters' => FiltersDto,
    'stats' => StatsDto,
    'newLeadsCount' => int,
]
```

### 4.2 `leads/_stats.html.twig` - Statystyki

**Opis komponentu:**
Komponent wyświetlający trzy kluczowe statystyki w formie kart: liczba leadów dzisiaj, liczba nieudanych dostaw, liczba klientów. Aktualizowany przez HTMX co 60 sekund.

**Główne elementy HTML:**
```html
<div class="row mb-4" id="stats-container" 
     hx-get="/leads/stats" 
     hx-trigger="every 60s" 
     hx-swap="innerHTML">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5>Leady dzisiaj</h5>
                <p class="h2">{{ stats.leadsToday }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5>Nieudane dostawy</h5>
                <p class="h2">{{ stats.failedDeliveries }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5>Klienci</h5>
                <p class="h2">{{ stats.totalCustomers }}</p>
            </div>
        </div>
    </div>
</div>
```

**Komponenty dzieci:**
Brak (terminal component)

**Obsługiwane zdarzenia:**
- HTMX polling co 60 sekund: `hx-trigger="every 60s"`

**Warunki walidacji:**
- Sprawdzenie czy `stats` nie jest null
- Wyświetlenie "0" dla wartości null

**Typy:**
- `StatsDto` - dane statystyk

**Propsy:**
```php
[
    'stats' => StatsDto {
        leadsToday: int,
        failedDeliveries: int,
        totalCustomers: int
    }
]
```

### 4.3 `leads/_filters.html.twig` - Sekcja filtrów

**Opis komponentu:**
Formularz filtrowania leadów zawierający podstawowe filtry (status, aplikacja źródłowa, zakres dat) oraz link do rozwinięcia filtrów zaawansowanych. Po submit formularz wysyła żądanie HTMX, które aktualizuje tabelę leadów bez przeładowania strony.

**Główne elementy HTML:**
```html
<form id="filters-form" 
      hx-get="/leads" 
      hx-target="#leads-table-container" 
      hx-push-url="true"
      hx-indicator="#loading-spinner">
    
    <!-- Filtry podstawowe -->
    <div class="row mb-3">
        <div class="col-md-3">
            <label for="status" class="form-label">Status</label>
            <select name="status" id="status" class="form-select">
                <option value="">Wszystkie</option>
                <option value="new" {{ filters.status == 'new' ? 'selected' : '' }}>Nowy</option>
                <option value="contacted" {{ filters.status == 'contacted' ? 'selected' : '' }}>Skontaktowano</option>
                <option value="qualified" {{ filters.status == 'qualified' ? 'selected' : '' }}>Zakwalifikowano</option>
                <option value="converted" {{ filters.status == 'converted' ? 'selected' : '' }}>Przekonwertowano</option>
                <option value="rejected" {{ filters.status == 'rejected' ? 'selected' : '' }}>Odrzucono</option>
            </select>
        </div>
        
        <div class="col-md-3">
            <label for="application_name" class="form-label">Aplikacja źródłowa</label>
            <select name="application_name" id="application_name" class="form-select">
                <option value="">Wszystkie</option>
                <option value="morizon" {{ filters.applicationName == 'morizon' ? 'selected' : '' }}>Morizon</option>
                <option value="gratka" {{ filters.applicationName == 'gratka' ? 'selected' : '' }}>Gratka</option>
                <option value="homsters" {{ filters.applicationName == 'homsters' ? 'selected' : '' }}>Homsters</option>
            </select>
        </div>
        
        <div class="col-md-3">
            <label for="created_from" class="form-label">Data od</label>
            <input type="date" name="created_from" id="created_from" 
                   class="form-control" 
                   value="{{ filters.createdFrom|date('Y-m-d') }}">
        </div>
        
        <div class="col-md-3">
            <label for="created_to" class="form-label">Data do</label>
            <input type="date" name="created_to" id="created_to" 
                   class="form-control" 
                   value="{{ filters.createdTo|date('Y-m-d') }}">
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-12">
            <button type="submit" class="btn btn-primary">
                <span id="loading-spinner" class="htmx-indicator spinner-border spinner-border-sm" role="status"></span>
                Zastosuj filtry
            </button>
            <button type="button" class="btn btn-link" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#advanced-filters">
                Więcej filtrów
            </button>
            <a href="/leads" class="btn btn-link">Wyczyść filtry</a>
        </div>
    </div>
    
    <!-- Filtry zaawansowane (rozwijane) -->
    {% include 'leads/_advanced_filters.html.twig' %}
</form>
```

**Komponenty dzieci:**
- `{% include 'leads/_advanced_filters.html.twig' %}`

**Obsługiwane zdarzenia:**
- Submit formularza: HTMX GET request z aktualizacją URL
- Toggle filtrów zaawansowanych: Bootstrap collapse
- Change na input: auto-submit (opcjonalnie, jeśli chcemy live filtering)

**Warunki walidacji:**
- `created_from` <= `created_to` (walidacja po stronie kontrolera)
- `status` musi być jednym z dozwolonych wartości (new, contacted, qualified, converted, rejected)
- `application_name` musi być jednym z dozwolonych wartości (morizon, gratka, homsters)
- Daty w formacie ISO 8601 (Y-m-d)

**Typy:**
- `FiltersDto` - dane filtrów

**Propsy:**
```php
[
    'filters' => FiltersDto {
        status: ?string,
        applicationName: ?string,
        customerEmail: ?string,
        customerPhone: ?string,
        createdFrom: ?\DateTimeInterface,
        createdTo: ?\DateTimeInterface,
        sort: string,
        order: string
    }
]
```

### 4.4 `leads/_advanced_filters.html.twig` - Filtry zaawansowane

**Opis komponentu:**
Rozwijana sekcja z dodatkowymi filtrami (email klienta, telefon klienta, sortowanie, kolejność). Używa Bootstrap collapse do pokazywania/ukrywania.

**Główne elementy HTML:**
```html
<div class="collapse" id="advanced-filters">
    <div class="card card-body mb-3">
        <div class="row">
            <div class="col-md-4">
                <label for="customer_email" class="form-label">Email klienta</label>
                <input type="email" name="customer_email" id="customer_email" 
                       class="form-control" 
                       value="{{ filters.customerEmail }}"
                       placeholder="np. jan@example.com">
            </div>
            
            <div class="col-md-4">
                <label for="customer_phone" class="form-label">Telefon klienta</label>
                <input type="tel" name="customer_phone" id="customer_phone" 
                       class="form-control" 
                       value="{{ filters.customerPhone }}"
                       placeholder="np. +48 123 456 789">
            </div>
            
            <div class="col-md-2">
                <label for="sort" class="form-label">Sortuj według</label>
                <select name="sort" id="sort" class="form-select">
                    <option value="created_at" {{ filters.sort == 'created_at' ? 'selected' : '' }}>Data utworzenia</option>
                    <option value="status" {{ filters.sort == 'status' ? 'selected' : '' }}>Status</option>
                    <option value="application_name" {{ filters.sort == 'application_name' ? 'selected' : '' }}>Aplikacja</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="order" class="form-label">Kolejność</label>
                <select name="order" id="order" class="form-select">
                    <option value="desc" {{ filters.order == 'desc' ? 'selected' : '' }}>Malejąco</option>
                    <option value="asc" {{ filters.order == 'asc' ? 'selected' : '' }}>Rosnąco</option>
                </select>
            </div>
        </div>
    </div>
</div>
```

**Komponenty dzieci:**
Brak (terminal component)

**Obsługiwane zdarzenia:**
- Bootstrap collapse toggle
- Input change (część formularza filtrów)

**Warunki walidacji:**
- `customer_email` - format email (validation HTML5 + backend)
- `customer_phone` - format telefonu (backend validation)
- `sort` - jedna z wartości: created_at, status, application_name
- `order` - jedna z wartości: asc, desc

**Typy:**
- `FiltersDto` (dziedziczy z komponentu rodzica)

**Propsy:**
```php
[
    'filters' => FiltersDto
]
```

### 4.5 `leads/_new_leads_notification.html.twig` - Notyfikacja nowych leadów

**Opis komponentu:**
Badge z informacją o liczbie nowych leadów i przycisk do załadowania ich do tabeli. Aktualizowany przez HTMX polling co 30 sekund. Widoczny tylko gdy są nowe leady.

**Główne elementy HTML:**
```html
<div id="new-leads-notification" 
     hx-get="/leads/new-count?since={{ lastCheckTimestamp }}" 
     hx-trigger="every 30s" 
     hx-swap="innerHTML">
    
    {% if newLeadsCount > 0 %}
        <div class="alert alert-info d-flex justify-content-between align-items-center mb-3">
            <span>
                <i class="bi bi-info-circle"></i>
                Pojawiły się <strong>{{ newLeadsCount }}</strong> nowe leady
            </span>
            <button type="button" 
                    class="btn btn-primary btn-sm"
                    hx-get="/leads?created_from={{ lastCheckTimestamp }}"
                    hx-target="#leads-table-container"
                    hx-push-url="true">
                Załaduj nowe leady
            </button>
        </div>
    {% endif %}
</div>
```

**Komponenty dzieci:**
Brak (terminal component)

**Obsługiwane zdarzenia:**
- HTMX polling co 30 sekund
- Kliknięcie przycisku "Załaduj nowe leady"

**Warunki walidacji:**
- `newLeadsCount` > 0 (warunek wyświetlenia)
- `lastCheckTimestamp` musi być valid datetime

**Typy:**
- `int newLeadsCount`
- `string lastCheckTimestamp`

**Propsy:**
```php
[
    'newLeadsCount' => int,
    'lastCheckTimestamp' => string (ISO 8601 datetime)
]
```

### 4.6 `leads/_table.html.twig` - Tabela leadów

**Opis komponentu:**
Główna tabela wyświetlająca listę leadów z danymi klientów, nieruchomości i statusami. Jest celem (target) dla HTMX updates przy filtracji i sortowaniu. Wykorzystuje loop do renderowania wierszy tabeli.

**Główne elementy HTML:**
```html
<div id="leads-table-container">
    {% if leads is empty %}
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            Nie znaleziono leadów spełniających kryteria wyszukiwania.
        </div>
    {% else %}
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>UUID</th>
                        <th>Data utworzenia</th>
                        <th>Klient</th>
                        <th>Aplikacja</th>
                        <th>Status</th>
                        <th>CDP Status</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    {% for lead in leads %}
                        {% include 'leads/_table_row.html.twig' with {'lead': lead} %}
                    {% endfor %}
                </tbody>
            </table>
        </div>
    {% endif %}
</div>
```

**Komponenty dzieci:**
- `{% include 'leads/_table_row.html.twig' %}` (loop)

**Obsługiwane zdarzenia:**
- Hover na wierszu (Bootstrap table-hover)
- Kliknięcie w przycisk "Zobacz szczegóły" (delegowane do _table_row)

**Warunki walidacji:**
- Sprawdzenie czy array `leads` nie jest pusty
- Fallback do komunikatu "Nie znaleziono leadów"

**Typy:**
- `LeadItemDto[]` - array leadów

**Propsy:**
```php
[
    'leads' => LeadItemDto[]
]
```

### 4.7 `leads/_table_row.html.twig` - Wiersz tabeli

**Opis komponentu:**
Pojedynczy wiersz tabeli reprezentujący jeden lead. Zawiera dane UUID, daty, klienta, aplikacji, statusu i przycisk do otwarcia szczegółów w sliderze.

**Główne elementy HTML:**
```html
<tr id="lead-row-{{ lead.id }}">
    <td>
        <code class="text-muted small">{{ lead.leadUuid|slice(0, 8) }}...</code>
    </td>
    <td>
        {{ lead.createdAt|date('Y-m-d H:i') }}
    </td>
    <td>
        <div>{{ lead.customer.firstName }} {{ lead.customer.lastName }}</div>
        <div class="small text-muted">
            <a href="mailto:{{ lead.customer.email }}">{{ lead.customer.email }}</a><br>
            <a href="tel:{{ lead.customer.phone }}">{{ lead.customer.phone }}</a>
        </div>
    </td>
    <td>
        <span class="badge bg-secondary">{{ lead.applicationName }}</span>
    </td>
    <td>
        {% set statusColors = {
            'new': 'info',
            'contacted': 'primary',
            'qualified': 'success',
            'converted': 'success',
            'rejected': 'secondary'
        } %}
        <span class="badge bg-{{ statusColors[lead.status] ?? 'secondary' }}">
            {{ lead.statusLabel }}
        </span>
    </td>
    <td>
        {% if lead.cdpDeliveryStatus == 'success' %}
            <i class="bi bi-check-circle-fill text-success" 
               data-bs-toggle="tooltip" 
               title="Wysłano do CDP"></i>
        {% elseif lead.cdpDeliveryStatus == 'failed' %}
            <i class="bi bi-x-circle-fill text-danger" 
               data-bs-toggle="tooltip" 
               title="Błąd wysyłki do CDP"></i>
        {% else %}
            <i class="bi bi-clock-fill text-warning" 
               data-bs-toggle="tooltip" 
               title="Oczekuje na wysłanie"></i>
        {% endif %}
    </td>
    <td>
        <button type="button" 
                class="btn btn-sm btn-primary"
                hx-get="/leads/{{ lead.id }}/details"
                hx-target="#slider-container"
                hx-swap="innerHTML">
            <i class="bi bi-eye"></i> Zobacz szczegóły
        </button>
    </td>
</tr>
```

**Komponenty dzieci:**
Brak (terminal component)

**Obsługiwane zdarzenia:**
- Kliknięcie "Zobacz szczegóły": HTMX GET request do załadowania slidera
- Hover tooltip na ikonie CDP status (Bootstrap tooltip)

**Warunki walidacji:**
- `lead` nie może być null
- `lead.id` musi być valid integer
- `lead.status` musi być jednym z dozwolonych wartości

**Typy:**
- `LeadItemDto` - pojedynczy lead

**Propsy:**
```php
[
    'lead' => LeadItemDto {
        id: int,
        leadUuid: string,
        status: string,
        statusLabel: string,
        createdAt: \DateTimeInterface,
        customer: CustomerDto {
            id: int,
            email: string,
            phone: string,
            firstName: ?string,
            lastName: ?string
        },
        applicationName: string,
        property: PropertyDto {
            propertyId: ?string,
            developmentId: ?string,
            price: ?float,
            location: ?string
        },
        cdpDeliveryStatus: string
    }
]
```

### 4.8 `leads/_details_slider.html.twig` - Slider szczegółów leada

**Opis komponentu:**
Off-canvas slider wysuwający się z prawej strony ekranu, zawierający szczegółowe informacje o wybranym leadzie. Ładowany dynamicznie przez HTMX po kliknięciu "Zobacz szczegóły". Ten komponent nie jest częścią bezpośredniej implementacji tego widoku (będzie w osobnym planie), ale musi być uwzględniony jako target dla HTMX.

**Główne elementy HTML:**
```html
<div class="offcanvas offcanvas-end show" 
     style="width: 600px;" 
     id="lead-details-slider"
     tabindex="-1">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Szczegóły leada #{{ lead.id }}</h5>
        <button type="button" 
                class="btn-close" 
                hx-get="/leads/close-slider" 
                hx-target="#slider-container"
                hx-swap="innerHTML"></button>
    </div>
    <div class="offcanvas-body">
        <!-- Szczegółowe informacje o leadzie - implementacja w osobnym planie -->
        <p>Lead UUID: {{ lead.leadUuid }}</p>
        <p>Status: {{ lead.statusLabel }}</p>
        <!-- ... więcej szczegółów -->
    </div>
</div>
```

**Komponenty dzieci:**
Szczegółowa implementacja w osobnym planie implementacji widoku szczegółów leada.

**Obsługiwane zdarzenia:**
- Zamknięcie slidera: HTMX request czyszczący kontener

**Warunki walidacji:**
- `lead` musi być kompletny obiekt LeadDetailDto

**Typy:**
- `LeadDetailDto` (rozszerzony DTO z pełnymi danymi)

**Propsy:**
```php
[
    'lead' => LeadDetailDto (pełne szczegóły leada)
]
```

### 4.9 `components/pagination.html.twig` - Komponent paginacji

**Opis komponentu:**
Reusable komponent paginacji wyświetlający przyciski nawigacji między stronami, informację o zakresie wyświetlanych wyników oraz dropdown do zmiany liczby elementów na stronie.

**Główne elementy HTML:**
```html
<div class="d-flex justify-content-between align-items-center mt-4">
    <!-- Informacja o wynikach -->
    <div class="text-muted">
        Pokazuję {{ pagination.from }}-{{ pagination.to }} z {{ pagination.total }} wyników
    </div>
    
    <!-- Nawigacja stron -->
    <nav aria-label="Paginacja leadów">
        <ul class="pagination mb-0">
            <!-- Poprzednia strona -->
            <li class="page-item {{ pagination.currentPage == 1 ? 'disabled' : '' }}">
                <a class="page-link" 
                   href="{{ path('leads_index', app.request.query.all|merge({page: pagination.currentPage - 1})) }}"
                   hx-get="{{ path('leads_index', app.request.query.all|merge({page: pagination.currentPage - 1})) }}"
                   hx-target="#leads-table-container"
                   hx-push-url="true">
                    Poprzednia
                </a>
            </li>
            
            <!-- Numery stron -->
            {% set startPage = max(1, pagination.currentPage - 2) %}
            {% set endPage = min(pagination.lastPage, pagination.currentPage + 2) %}
            
            {% if startPage > 1 %}
                <li class="page-item">
                    <a class="page-link" 
                       href="{{ path('leads_index', app.request.query.all|merge({page: 1})) }}"
                       hx-get="{{ path('leads_index', app.request.query.all|merge({page: 1})) }}"
                       hx-target="#leads-table-container"
                       hx-push-url="true">1</a>
                </li>
                {% if startPage > 2 %}
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                {% endif %}
            {% endif %}
            
            {% for page in startPage..endPage %}
                <li class="page-item {{ page == pagination.currentPage ? 'active' : '' }}">
                    <a class="page-link" 
                       href="{{ path('leads_index', app.request.query.all|merge({page: page})) }}"
                       hx-get="{{ path('leads_index', app.request.query.all|merge({page: page})) }}"
                       hx-target="#leads-table-container"
                       hx-push-url="true">{{ page }}</a>
                </li>
            {% endfor %}
            
            {% if endPage < pagination.lastPage %}
                {% if endPage < pagination.lastPage - 1 %}
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                {% endif %}
                <li class="page-item">
                    <a class="page-link" 
                       href="{{ path('leads_index', app.request.query.all|merge({page: pagination.lastPage})) }}"
                       hx-get="{{ path('leads_index', app.request.query.all|merge({page: pagination.lastPage})) }}"
                       hx-target="#leads-table-container"
                       hx-push-url="true">{{ pagination.lastPage }}</a>
                </li>
            {% endif %}
            
            <!-- Następna strona -->
            <li class="page-item {{ pagination.currentPage == pagination.lastPage ? 'disabled' : '' }}">
                <a class="page-link" 
                   href="{{ path('leads_index', app.request.query.all|merge({page: pagination.currentPage + 1})) }}"
                   hx-get="{{ path('leads_index', app.request.query.all|merge({page: pagination.currentPage + 1})) }}"
                   hx-target="#leads-table-container"
                   hx-push-url="true">
                    Następna
                </a>
            </li>
        </ul>
    </nav>
    
    <!-- Dropdown liczby elementów na stronie -->
    <div class="dropdown">
        <button class="btn btn-secondary btn-sm dropdown-toggle" 
                type="button" 
                data-bs-toggle="dropdown" 
                aria-expanded="false">
            {{ pagination.perPage }} na stronę
        </button>
        <ul class="dropdown-menu">
            {% for limit in [20, 50, 100] %}
                <li>
                    <a class="dropdown-item {{ limit == pagination.perPage ? 'active' : '' }}" 
                       href="{{ path('leads_index', app.request.query.all|merge({limit: limit, page: 1})) }}"
                       hx-get="{{ path('leads_index', app.request.query.all|merge({limit: limit, page: 1})) }}"
                       hx-target="#leads-table-container"
                       hx-push-url="true">
                        {{ limit }}
                    </a>
                </li>
            {% endfor %}
        </ul>
    </div>
</div>
```

**Komponenty dzieci:**
Brak (terminal component)

**Obsługiwane zdarzenia:**
- Kliknięcie linku strony: HTMX GET z aktualizacją URL
- Kliknięcie dropdown limit: HTMX GET z aktualizacją URL

**Warunki walidacji:**
- `pagination.currentPage` >= 1 && <= `pagination.lastPage`
- `pagination.perPage` musi być jedną z wartości: 20, 50, 100
- `pagination.total` >= 0

**Typy:**
- `PaginationDto`

**Propsy:**
```php
[
    'pagination' => PaginationDto {
        currentPage: int,
        perPage: int,
        total: int,
        lastPage: int,
        from: int,
        to: int
    }
]
```

## 5. Typy

### 5.1 DTO (Data Transfer Objects) - Backend

#### `LeadItemDto`
```php
namespace App\DTO;

class LeadItemDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $leadUuid,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly \DateTimeInterface $createdAt,
        public readonly CustomerDto $customer,
        public readonly string $applicationName,
        public readonly PropertySummaryDto $property,
        public readonly string $cdpDeliveryStatus
    ) {}
}
```

#### `CustomerDto`
```php
namespace App\DTO;

class CustomerDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $phone,
        public readonly ?string $firstName,
        public readonly ?string $lastName
    ) {}
}
```

#### `PropertySummaryDto`
```php
namespace App\DTO;

class PropertySummaryDto
{
    public function __construct(
        public readonly ?string $propertyId,
        public readonly ?string $developmentId,
        public readonly ?float $price,
        public readonly ?string $location
    ) {}
}
```

#### `PaginationDto`
```php
namespace App\DTO;

class PaginationDto
{
    public function __construct(
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $lastPage,
        public readonly int $from,
        public readonly int $to
    ) {}
    
    public static function fromArray(array $data): self
    {
        return new self(
            currentPage: $data['current_page'],
            perPage: $data['per_page'],
            total: $data['total'],
            lastPage: $data['last_page'],
            from: ($data['current_page'] - 1) * $data['per_page'] + 1,
            to: min($data['current_page'] * $data['per_page'], $data['total'])
        );
    }
}
```

#### `FiltersDto`
```php
namespace App\DTO;

class FiltersDto
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?string $applicationName = null,
        public readonly ?string $customerEmail = null,
        public readonly ?string $customerPhone = null,
        public readonly ?\DateTimeInterface $createdFrom = null,
        public readonly ?\DateTimeInterface $createdTo = null,
        public readonly string $sort = 'created_at',
        public readonly string $order = 'desc'
    ) {}
    
    public static function fromRequest(\Symfony\Component\HttpFoundation\Request $request): self
    {
        return new self(
            status: $request->query->get('status'),
            applicationName: $request->query->get('application_name'),
            customerEmail: $request->query->get('customer_email'),
            customerPhone: $request->query->get('customer_phone'),
            createdFrom: $request->query->get('created_from') 
                ? new \DateTime($request->query->get('created_from')) 
                : null,
            createdTo: $request->query->get('created_to') 
                ? new \DateTime($request->query->get('created_to')) 
                : null,
            sort: $request->query->get('sort', 'created_at'),
            order: $request->query->get('order', 'desc')
        );
    }
    
    public function toQueryParams(): array
    {
        $params = [];
        
        if ($this->status) $params['status'] = $this->status;
        if ($this->applicationName) $params['application_name'] = $this->applicationName;
        if ($this->customerEmail) $params['customer_email'] = $this->customerEmail;
        if ($this->customerPhone) $params['customer_phone'] = $this->customerPhone;
        if ($this->createdFrom) $params['created_from'] = $this->createdFrom->format('Y-m-d');
        if ($this->createdTo) $params['created_to'] = $this->createdTo->format('Y-m-d');
        if ($this->sort !== 'created_at') $params['sort'] = $this->sort;
        if ($this->order !== 'desc') $params['order'] = $this->order;
        
        return $params;
    }
}
```

#### `StatsDto`
```php
namespace App\DTO;

class StatsDto
{
    public function __construct(
        public readonly int $leadsToday,
        public readonly int $failedDeliveries,
        public readonly int $totalCustomers
    ) {}
}
```

### 5.2 API Response Types

#### `LeadsListApiResponse`
```php
namespace App\DTO;

class LeadsListApiResponse
{
    public function __construct(
        /** @var LeadItemDto[] */
        public readonly array $data,
        public readonly PaginationDto $pagination
    ) {}
}
```

### 5.3 View Models (dodatkowe typy dla widoku)

#### `LeadsListViewModel`
```php
namespace App\ViewModel;

use App\DTO\LeadItemDto;
use App\DTO\PaginationDto;
use App\DTO\FiltersDto;
use App\DTO\StatsDto;

class LeadsListViewModel
{
    public function __construct(
        /** @var LeadItemDto[] */
        public readonly array $leads,
        public readonly PaginationDto $pagination,
        public readonly FiltersDto $filters,
        public readonly StatsDto $stats,
        public readonly int $newLeadsCount,
        public readonly string $lastCheckTimestamp
    ) {}
}
```

## 6. Zarządzanie stanem

### 6.1 Stan w URL (Query Parameters)

Główny stan widoku jest persystowany w URL poprzez query parameters. To umożliwia:
- Bookmarking specyficznych widoków z filtrami
- Sharing URL między użytkownikami
- Browser back/forward navigation
- Page refresh bez utraty filtrów

**Parametry URL:**
```
/?page=1&limit=20&status=new&application_name=morizon&created_from=2025-01-01&sort=created_at&order=desc
```

**Zarządzanie przez HTMX:**
- Atrybut `hx-push-url="true"` na formularzach i linkach zapewnia aktualizację URL
- Symfony `Request` object automatycznie parsuje query params
- Twig helper `app.request.query.all` dostarcza wszystkie query params do templates

### 6.2 Stan w Session Storage

Dla lepszego UX niektóre dane są cache'owane w session storage:

**Timestamp ostatniego sprawdzenia nowych leadów:**
```javascript
// JavaScript helper w assets/js/leads.js
function updateLastCheckTimestamp() {
    sessionStorage.setItem('leads_last_check', new Date().toISOString());
}

function getLastCheckTimestamp() {
    return sessionStorage.getItem('leads_last_check') || new Date().toISOString();
}

// Aktualizacja przy każdym polling update
document.addEventListener('htmx:afterRequest', function(event) {
    if (event.detail.pathInfo.requestPath.includes('/leads/new-count')) {
        updateLastCheckTimestamp();
    }
});
```

### 6.3 Stan w Backend (Session)

**User session (Symfony):**
- Token JWT w session
- User info (id, username, role, permissions)
- Session timeout (30 minut)

**Nie używamy:**
- Local Storage (nie potrzebujemy długoterminowego persisting)
- Cookies (oprócz session cookie)
- Global JavaScript state (HTMX zarządza stanem przez DOM)

### 6.4 HTMX State Management

HTMX zarządza stanem poprzez atrybuty HTML:

**Polling state:**
```html
<div hx-get="/leads/new-count" 
     hx-trigger="every 30s" 
     hx-swap="innerHTML">
    <!-- Content aktualizowany co 30s -->
</div>
```

**Form state:**
```html
<form hx-get="/leads" 
      hx-target="#leads-table-container" 
      hx-push-url="true"
      hx-indicator="#loading-spinner">
    <!-- Form controls -->
</form>
```

**Loading indicators:**
```html
<span id="loading-spinner" class="htmx-indicator spinner-border"></span>
```

HTMX automatycznie dodaje klasy CSS podczas requestów:
- `.htmx-request` - podczas aktywnego requestu
- `.htmx-swapping` - podczas swap operacji
- `.htmx-settling` - podczas settle operacji

## 7. Integracja API

### 7.1 Główny endpoint: GET /api/leads

**Controller action:**
```php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LeadsViewController extends AbstractController
{
    public function __construct(
        private readonly LeadServiceInterface $leadService,
        private readonly StatsServiceInterface $statsService
    ) {}
    
    #[Route('/', name: 'leads_index', methods: ['GET'])]
    #[Route('/leads', name: 'leads_list', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Parse filters from query params
        $filters = FiltersDto::fromRequest($request);
        
        // Get pagination params
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        
        // Call API service to get leads
        $response = $this->leadService->getLeadsList(
            filters: $filters,
            page: $page,
            limit: $limit
        );
        
        // Get stats
        $stats = $this->statsService->getDashboardStats();
        
        // Get new leads count (since last check)
        $lastCheck = $request->query->get('last_check', (new \DateTime('-30 seconds'))->format('c'));
        $newLeadsCount = $this->leadService->countNewLeadsSince($lastCheck);
        
        // Check if this is HTMX request (partial update)
        if ($request->headers->get('HX-Request')) {
            // Return only the table partial
            return $this->render('leads/_table.html.twig', [
                'leads' => $response->data,
                'pagination' => $response->pagination
            ]);
        }
        
        // Full page render
        return $this->render('leads/index.html.twig', [
            'leads' => $response->data,
            'pagination' => $response->pagination,
            'filters' => $filters,
            'stats' => $stats,
            'newLeadsCount' => $newLeadsCount,
            'lastCheckTimestamp' => (new \DateTime())->format('c')
        ]);
    }
}
```

### 7.2 Endpoint dla statystyk: GET /leads/stats

```php
#[Route('/leads/stats', name: 'leads_stats', methods: ['GET'])]
public function stats(): Response
{
    $stats = $this->statsService->getDashboardStats();
    
    return $this->render('leads/_stats.html.twig', [
        'stats' => $stats
    ]);
}
```

### 7.3 Endpoint dla nowych leadów: GET /leads/new-count

```php
#[Route('/leads/new-count', name: 'leads_new_count', methods: ['GET'])]
public function newCount(Request $request): Response
{
    $since = $request->query->get('since', (new \DateTime('-30 seconds'))->format('c'));
    $newLeadsCount = $this->leadService->countNewLeadsSince($since);
    
    return $this->render('leads/_new_leads_notification.html.twig', [
        'newLeadsCount' => $newLeadsCount,
        'lastCheckTimestamp' => (new \DateTime())->format('c')
    ]);
}
```

### 7.4 Service Layer: LeadService

```php
namespace App\Leads;

use App\DTO\FiltersDto;
use App\DTO\LeadsListApiResponse;
use GuzzleHttp\ClientInterface;

class LeadService implements LeadServiceInterface
{
    public function __construct(
        private readonly ClientInterface $apiClient,
        private readonly string $apiBaseUrl
    ) {}
    
    public function getLeadsList(
        FiltersDto $filters,
        int $page,
        int $limit
    ): LeadsListApiResponse {
        // Build query parameters
        $queryParams = array_merge(
            $filters->toQueryParams(),
            [
                'page' => $page,
                'limit' => $limit
            ]
        );
        
        // Call internal API endpoint
        $response = $this->apiClient->get(
            $this->apiBaseUrl . '/api/leads',
            ['query' => $queryParams]
        );
        
        $data = json_decode($response->getBody()->getContents(), true);
        
        // Transform API response to DTOs
        $leads = array_map(
            fn($lead) => LeadItemDto::fromArray($lead),
            $data['data']
        );
        
        $pagination = PaginationDto::fromArray($data['pagination']);
        
        return new LeadsListApiResponse($leads, $pagination);
    }
    
    public function countNewLeadsSince(string $since): int
    {
        $response = $this->apiClient->get(
            $this->apiBaseUrl . '/api/leads',
            [
                'query' => [
                    'created_from' => $since,
                    'limit' => 1 // Only count, don't fetch all
                ]
            ]
        );
        
        $data = json_decode($response->getBody()->getContents(), true);
        
        return $data['pagination']['total'] ?? 0;
    }
}
```

### 7.5 Typy żądań i odpowiedzi

**Request (GET /api/leads):**
```
Query Parameters: {
    page?: number,
    limit?: number,
    status?: 'new' | 'contacted' | 'qualified' | 'converted' | 'rejected',
    application_name?: string,
    customer_email?: string,
    customer_phone?: string,
    created_from?: string (ISO 8601 datetime),
    created_to?: string (ISO 8601 datetime),
    sort?: 'created_at' | 'status' | 'application_name',
    order?: 'asc' | 'desc'
}
```

**Response (200 OK):**
```json
{
    "data": [
        {
            "id": 123,
            "lead_uuid": "550e8400-e29b-41d4-a716-446655440000",
            "status": "new",
            "created_at": "2025-01-15T10:30:00+00:00",
            "customer": {
                "id": 456,
                "email": "jan.kowalski@example.com",
                "phone": "+48123456789",
                "first_name": "Jan",
                "last_name": "Kowalski"
            },
            "application_name": "morizon",
            "property": {
                "property_id": "prop_123",
                "development_id": "dev_456",
                "price": 450000.00,
                "location": "Warszawa, Mokotów"
            }
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 20,
        "total": 150,
        "last_page": 8
    }
}
```

## 8. Interakcje użytkownika

### 8.1 Filtrowanie leadów

**Flow:**
1. User wybiera wartość z dropdown "Status" (np. "Nowy")
2. User wybiera "Aplikacja źródłowa" (np. "Morizon")
3. User wybiera zakres dat (created_from, created_to)
4. User klika "Zastosuj filtry"
5. HTMX wysyła GET request z query params
6. Backend zwraca przefiltrowaną tabelę
7. HTMX zamienia zawartość `#leads-table-container`
8. URL jest aktualizowany (hx-push-url)
9. Paginacja jest resetowana do strony 1

**Implementacja:**
```html
<form hx-get="/leads" 
      hx-target="#leads-table-container" 
      hx-push-url="true">
    <select name="status">...</select>
    <select name="application_name">...</select>
    <input type="date" name="created_from">
    <input type="date" name="created_to">
    <button type="submit">Zastosuj filtry</button>
</form>
```

### 8.2 Rozwijanie filtrów zaawansowanych

**Flow:**
1. User klika "Więcej filtrów"
2. Bootstrap collapse pokazuje panel z dodatkowymi filtrami
3. User wprowadza email/telefon klienta
4. User wybiera sortowanie i kolejność
5. User klika "Zastosuj filtry" (ten sam submit co filtry podstawowe)
6. HTMX aktualizuje tabelę

**Implementacja:**
```html
<button data-bs-toggle="collapse" data-bs-target="#advanced-filters">
    Więcej filtrów
</button>
<div class="collapse" id="advanced-filters">
    <!-- Dodatkowe filtry -->
</div>
```

### 8.3 Paginacja

**Flow:**
1. User klika link strony (np. "2")
2. HTMX wysyła GET request z `?page=2` (+ wszystkie inne query params)
3. Backend zwraca leady dla strony 2
4. HTMX zamienia `#leads-table-container`
5. URL jest aktualizowany
6. Scroll do góry tabeli (opcjonalnie)

**Implementacja:**
```html
<a href="/leads?page=2&status=new" 
   hx-get="/leads?page=2&status=new"
   hx-target="#leads-table-container"
   hx-push-url="true">2</a>
```

### 8.4 Zmiana liczby elementów na stronie

**Flow:**
1. User klika dropdown "20 na stronę"
2. User wybiera "50"
3. HTMX wysyła GET request z `?limit=50&page=1` (reset do strony 1)
4. Backend zwraca 50 leadów
5. HTMX aktualizuje tabelę
6. URL jest aktualizowany

**Implementacja:**
```html
<a href="/leads?limit=50&page=1" 
   hx-get="/leads?limit=50&page=1"
   hx-target="#leads-table-container"
   hx-push-url="true">50</a>
```

### 8.5 Załadowanie nowych leadów

**Flow:**
1. HTMX polling (co 30s) sprawdza `/leads/new-count`
2. Jeśli są nowe leady, wyświetla badge "Pojawiły się 5 nowych leadów"
3. User klika "Załaduj nowe leady"
4. HTMX wysyła GET request z `?created_from={lastCheckTimestamp}`
5. Backend zwraca nowe leady
6. HTMX aktualizuje tabelę
7. Badge znika (nowy timestamp)

**Implementacja:**
```html
<div hx-get="/leads/new-count?since={{ lastCheckTimestamp }}" 
     hx-trigger="every 30s">
    {% if newLeadsCount > 0 %}
        <button hx-get="/leads?created_from={{ lastCheckTimestamp }}">
            Załaduj {{ newLeadsCount }} nowych leadów
        </button>
    {% endif %}
</div>
```

### 8.6 Otwarcie szczegółów leada

**Flow:**
1. User klika "Zobacz szczegóły" przy leadzie
2. HTMX wysyła GET request do `/leads/{id}/details`
3. Backend zwraca HTML slidera z szczegółami
4. HTMX wstawia HTML do `#slider-container`
5. Bootstrap offcanvas automatycznie pokazuje slider (klasa `.show`)
6. User może przeglądać szczegóły, edytować status, itp. (implementacja w osobnym planie)

**Implementacja:**
```html
<button hx-get="/leads/{{ lead.id }}/details"
        hx-target="#slider-container">
    Zobacz szczegóły
</button>
```

### 8.7 Czyszczenie filtrów

**Flow:**
1. User klika "Wyczyść filtry"
2. Przekierowanie do `/leads` bez query params
3. Full page reload z domyślnymi wartościami filtrów

**Implementacja:**
```html
<a href="/leads" class="btn btn-link">Wyczyść filtry</a>
```

## 9. Warunki i walidacja

### 9.1 Walidacja filtrów (Backend)

**Status:**
- Dozwolone wartości: `'new'`, `'contacted'`, `'qualified'`, `'converted'`, `'rejected'`, lub pusty string (wszystkie)
- Walidacja: `in_array($status, ['new', 'contacted', 'qualified', 'converted', 'rejected', ''])`
- Jeśli invalid: ignoruj filtr, użyj default (wszystkie statusy)

**Application Name:**
- Dozwolone wartości: `'morizon'`, `'gratka'`, `'homsters'`, lub pusty string
- Walidacja: `in_array($applicationName, ['morizon', 'gratka', 'homsters', ''])`
- Jeśli invalid: ignoruj filtr

**Email:**
- Format email: `filter_var($email, FILTER_VALIDATE_EMAIL)`
- Jeśli invalid: zwróć błąd 400 Bad Request

**Telefon:**
- Format: regex `/^\+?[0-9\s\-()]+$/`
- Jeśli invalid: zwróć błąd 400 Bad Request

**Daty:**
- Format: ISO 8601 date (`Y-m-d`)
- created_from <= created_to
- Walidacja: `DateTime::createFromFormat('Y-m-d', $date)`
- Jeśli invalid: zwróć błąd 400 Bad Request
- Jeśli created_from > created_to: zwróć błąd 422 Unprocessable Entity z message "Data od musi być wcześniejsza niż data do"

**Sortowanie:**
- Dozwolone pola: `'created_at'`, `'status'`, `'application_name'`
- Jeśli invalid: użyj default `'created_at'`

**Kolejność:**
- Dozwolone wartości: `'asc'`, `'desc'`
- Jeśli invalid: użyj default `'desc'`

**Paginacja:**
- page >= 1
- limit >= 1 && <= 100
- Jeśli invalid: użyj defaults (page=1, limit=20)

### 9.2 Walidacja w UI (Frontend)

**HTML5 validation:**
```html
<!-- Email -->
<input type="email" name="customer_email" required>

<!-- Telefon -->
<input type="tel" name="customer_phone" pattern="^\+?[0-9\s\-()]+$">

<!-- Daty -->
<input type="date" name="created_from">
<input type="date" name="created_to">
```

**JavaScript validation (opcjonalnie):**
```javascript
// Walidacja dat przed submit
document.getElementById('filters-form').addEventListener('submit', function(e) {
    const fromDate = new Date(document.getElementById('created_from').value);
    const toDate = new Date(document.getElementById('created_to').value);
    
    if (fromDate > toDate) {
        e.preventDefault();
        alert('Data od musi być wcześniejsza niż data do');
        return false;
    }
});
```

### 9.3 Warunki wyświetlania w UI

**Pusty stan (brak leadów):**
```twig
{% if leads is empty %}
    <div class="alert alert-info">
        Nie znaleziono leadów spełniających kryteria wyszukiwania.
    </div>
{% endif %}
```

**Badge nowych leadów:**
```twig
{% if newLeadsCount > 0 %}
    <div class="alert alert-info">
        Pojawiły się {{ newLeadsCount }} nowe leady
    </div>
{% endif %}
```

**Status badge color:**
```twig
{% set statusColors = {
    'new': 'info',
    'contacted': 'primary',
    'qualified': 'success',
    'converted': 'success',
    'rejected': 'secondary'
} %}
<span class="badge bg-{{ statusColors[lead.status] ?? 'secondary' }}">
    {{ lead.statusLabel }}
</span>
```

**CDP Status icon:**
```twig
{% if lead.cdpDeliveryStatus == 'success' %}
    <i class="bi bi-check-circle-fill text-success"></i>
{% elseif lead.cdpDeliveryStatus == 'failed' %}
    <i class="bi bi-x-circle-fill text-danger"></i>
{% else %}
    <i class="bi bi-clock-fill text-warning"></i>
{% endif %}
```

**Paginacja - disabled states:**
```twig
<li class="page-item {{ pagination.currentPage == 1 ? 'disabled' : '' }}">
    <a class="page-link">Poprzednia</a>
</li>
```

**Role-based visibility:**
```twig
{% if is_granted('ROLE_CALL_CENTER') or is_granted('ROLE_ADMIN') %}
    <!-- Przycisk akcji dostępny tylko dla call center i admin -->
{% endif %}
```

### 9.4 Walidacja uprawnień (Authorization)

**Sprawdzenie autoryzacji w kontrolerze:**
```php
#[Route('/leads', name: 'leads_index')]
#[IsGranted('ROLE_USER')] // Minimum BOK
public function index(Request $request): Response
{
    // Tylko zalogowani użytkownicy z role BOK, CALL_CENTER lub ADMIN
}
```

**Sprawdzenie w Twig:**
```twig
{% if not app.user %}
    {# Redirect to login #}
{% endif %}
```

## 10. Obsługa błędów

### 10.1 Błędy API (Backend)

**400 Bad Request - Invalid query parameters:**
```php
if (!$this->isValidEmail($filters->customerEmail)) {
    return $this->json([
        'error' => [
            'code' => 'INVALID_EMAIL',
            'message' => 'Podany email jest nieprawidłowy',
            'field' => 'customer_email'
        ]
    ], Response::HTTP_BAD_REQUEST);
}
```

**401 Unauthorized - User not authenticated:**
```php
// Symfony automatycznie przekierowuje na login
// Jeśli HTMX request, zwróć 401 i HTMX przekieruje
if ($request->headers->get('HX-Request') && !$this->getUser()) {
    return new Response('', Response::HTTP_UNAUTHORIZED, [
        'HX-Redirect' => '/login'
    ]);
}
```

**404 Not Found - Lead nie istnieje:**
```php
$lead = $this->leadService->findById($id);
if (!$lead) {
    throw $this->createNotFoundException('Lead not found');
}
```

**422 Unprocessable Entity - Validation failed:**
```php
if ($filters->createdFrom > $filters->createdTo) {
    return $this->json([
        'error' => [
            'code' => 'INVALID_DATE_RANGE',
            'message' => 'Data od musi być wcześniejsza niż data do',
            'fields' => ['created_from', 'created_to']
        ]
    ], Response::HTTP_UNPROCESSABLE_ENTITY);
}
```

**500 Internal Server Error:**
```php
try {
    $response = $this->leadService->getLeadsList($filters, $page, $limit);
} catch (\Exception $e) {
    $this->logger->error('Failed to fetch leads', [
        'error' => $e->getMessage(),
        'filters' => $filters
    ]);
    
    if ($request->headers->get('HX-Request')) {
        return $this->render('components/_error_message.html.twig', [
            'message' => 'Wystąpił błąd podczas pobierania leadów. Spróbuj ponownie.'
        ]);
    }
    
    throw $e;
}
```

### 10.2 Obsługa błędów w UI (Frontend)

**HTMX error handling:**
```javascript
// assets/js/app.js
document.addEventListener('htmx:responseError', function(event) {
    const status = event.detail.xhr.status;
    
    if (status === 401) {
        // Redirect to login
        window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
    } else if (status === 403) {
        showToast('Nie masz uprawnień do wykonania tej operacji', 'danger');
    } else if (status >= 500) {
        showToast('Wystąpił błąd serwera. Spróbuj ponownie później.', 'danger');
    } else {
        showToast('Wystąpił błąd. Spróbuj ponownie.', 'warning');
    }
});

function showToast(message, type) {
    const toastContainer = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    toastContainer.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
}
```

**Network errors (brak połączenia):**
```javascript
document.addEventListener('htmx:sendError', function(event) {
    showToast('Brak połączenia z serwerem. Sprawdź połączenie internetowe.', 'danger');
});
```

**Timeout errors:**
```javascript
document.addEventListener('htmx:timeout', function(event) {
    showToast('Przekroczono czas oczekiwania. Spróbuj ponownie.', 'warning');
});
```

### 10.3 Przypadki brzegowe

**Pusta lista leadów:**
```twig
{% if leads is empty %}
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i>
        Nie znaleziono leadów spełniających kryteria wyszukiwania.
        {% if filters.status or filters.applicationName %}
            <a href="/leads">Wyczyść filtry</a>
        {% endif %}
    </div>
{% endif %}
```

**Brak nowych leadów (polling zwraca 0):**
```twig
{# Notification div pozostaje pusty - nic nie renderujemy #}
{% if newLeadsCount > 0 %}
    <!-- Show notification -->
{% endif %}
```

**Bardzo długi UUID:**
```twig
<code class="text-muted small">{{ lead.leadUuid|slice(0, 8) }}...</code>
```

**Brak danych klienta (firstName, lastName null):**
```twig
<div>
    {% if lead.customer.firstName or lead.customer.lastName %}
        {{ lead.customer.firstName }} {{ lead.customer.lastName }}
    {% else %}
        <span class="text-muted">Brak danych</span>
    {% endif %}
</div>
```

**Brak danych nieruchomości (wszystkie pola null):**
```twig
{% if lead.property.location %}
    {{ lead.property.location }}
{% else %}
    <span class="text-muted">-</span>
{% endif %}
```

**Strona poza zakresem (page > lastPage):**
```php
// W kontrolerze
if ($page > $pagination->lastPage && $pagination->lastPage > 0) {
    return $this->redirectToRoute('leads_index', array_merge(
        $request->query->all(),
        ['page' => $pagination->lastPage]
    ));
}
```

## 11. Kroki implementacji

### Krok 1: Przygotowanie struktury projektu

1.1. **Utworzenie katalogów dla templates:**
```bash
mkdir -p templates/leads
mkdir -p templates/components
```

1.2. **Utworzenie katalogów dla DTO i Services:**
```bash
mkdir -p src/DTO
mkdir -p src/ViewModel
mkdir -p src/Service
```

1.3. **Instalacja zależności:**
```bash
composer require symfony/twig-bundle
composer require guzzlehttp/guzzle
composer require symfony/security-bundle
```

1.4. **Setup HTMX i Bootstrap w assets:**
```bash
# W templates/base.html.twig dodać:
# <script src="https://unpkg.com/htmx.org@1.9.10"></script>
# <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
```

### Krok 2: Implementacja DTO i typów

2.1. **Utworzyć `src/DTO/LeadItemDto.php`**
- Implementować konstruktor z readonly properties
- Dodać static method `fromArray()` do konwersji z API response

2.2. **Utworzyć `src/DTO/CustomerDto.php`**
- Implementować podstawowe properties (id, email, phone, firstName, lastName)

2.3. **Utworzyć `src/DTO/PropertySummaryDto.php`**
- Implementować properties dla skróconych danych nieruchomości

2.4. **Utworzyć `src/DTO/PaginationDto.php`**
- Implementować properties dla paginacji
- Dodać static method `fromArray()`
- Dodać gettery `from` i `to` dla zakresu wyników

2.5. **Utworzyć `src/DTO/FiltersDto.php`**
- Implementować properties dla wszystkich filtrów
- Dodać static method `fromRequest(Request $request)`
- Dodać method `toQueryParams()` do konwersji na query params

2.6. **Utworzyć `src/DTO/StatsDto.php`**
- Implementować properties dla statystyk dashboardu

2.7. **Utworzyć `src/ViewModel/LeadsListViewModel.php`**
- Agregować wszystkie dane potrzebne dla widoku

### Krok 3: Implementacja Service Layer

3.1. **Utworzyć interface `src/Service/LeadServiceInterface.php`**
```php
interface LeadServiceInterface
{
    public function getLeadsList(FiltersDto $filters, int $page, int $limit): LeadsListApiResponse;
    public function countNewLeadsSince(string $since): int;
}
```

3.2. **Implementować `src/Service/LeadService.php`**
- Wstrzyknąć Guzzle HTTP Client
- Implementować `getLeadsList()` - wywołanie GET /api/leads
- Implementować `countNewLeadsSince()` - count nowych leadów
- Konwertować API responses na DTOs

3.3. **Utworzyć interface `src/Service/StatsServiceInterface.php`**
```php
interface StatsServiceInterface
{
    public function getDashboardStats(): StatsDto;
}
```

3.4. **Implementować `src/Service/StatsService.php`**
- Implementować `getDashboardStats()` - pobieranie statystyk
- Można wykorzystać cache (Redis) dla optymalizacji

3.5. **Konfiguracja services w `config/services.yaml`:**
```yaml
services:
    App\Service\LeadService:
        arguments:
            $apiClient: '@eight_points_guzzle.client.api'
            $apiBaseUrl: '%env(API_BASE_URL)%'
```

### Krok 4: Implementacja kontrolera widoku

4.1. **Utworzyć `src/Controller/LeadsViewController.php`**
- Dodać route annotations: `/` i `/leads`
- Wstrzyknąć `LeadServiceInterface` i `StatsServiceInterface`

4.2. **Implementować główną akcję `index(Request $request)`:**
- Parsować filtry z query params używając `FiltersDto::fromRequest()`
- Pobrać page i limit z query params
- Wywołać `leadService->getLeadsList()`
- Wywołać `statsService->getDashboardStats()`
- Wywołać `leadService->countNewLeadsSince()`
- Sprawdzić czy to HTMX request (header `HX-Request`)
- Jeśli HTMX: zwrócić tylko partial template `_table.html.twig`
- Jeśli nie HTMX: zwrócić pełny template `index.html.twig`

4.3. **Implementować akcję `stats()`:**
- Route: `/leads/stats`
- Pobrać statystyki
- Zwrócić `_stats.html.twig`

4.4. **Implementować akcję `newCount(Request $request)`:**
- Route: `/leads/new-count`
- Pobrać `since` timestamp z query params
- Wywołać `leadService->countNewLeadsSince()`
- Zwrócić `_new_leads_notification.html.twig`

4.5. **Dodać error handling:**
- Try-catch dla service calls
- Logowanie błędów
- Zwracanie error templates dla HTMX requests

### Krok 5: Implementacja templates - Base layout

5.1. **Zmodyfikować `templates/base.html.twig`:**
- Dodać Bootstrap 5 CSS
- Dodać HTMX script
- Dodać Bootstrap Icons
- Dodać custom CSS
- Struktura: sidebar + main content area
- Dodać kontener dla toast notifications
- Dodać kontener dla slidera

5.2. **Utworzyć `templates/components/sidebar.html.twig`:**
- Menu nawigacji z linkami
- Aktywna strona highlighted
- Badge notifications (dla failed deliveries)
- Footer z linkami pomocy

5.3. **Utworzyć `templates/components/header.html.twig`:**
- User info dropdown (nazwa, rola)
- Link wylogowania
- Breadcrumbs (opcjonalnie)

### Krok 6: Implementacja templates - Główny widok

6.1. **Utworzyć `templates/leads/index.html.twig`:**
- Extend `base.html.twig`
- Block `title`: "Dashboard - Leady"
- Block `body`: główna zawartość
- Include statystyk: `{% include 'leads/_stats.html.twig' %}`
- Include notyfikacji: `{% include 'leads/_new_leads_notification.html.twig' %}`
- Include filtrów: `{% include 'leads/_filters.html.twig' %}`
- Include tabeli: `{% include 'leads/_table.html.twig' %}`
- Include paginacji: `{% include 'components/pagination.html.twig' %}`
- Dodać `<div id="slider-container"></div>` dla slidera

### Krok 7: Implementacja templates - Statystyki

7.1. **Utworzyć `templates/leads/_stats.html.twig`:**
- Div container z id `stats-container`
- HTMX attributes: `hx-get="/leads/stats"`, `hx-trigger="every 60s"`
- Row z 3 kolumnami (col-md-4)
- Każda kolumna: card z tytułem i wartością
- Karta 1: Leady dzisiaj (`stats.leadsToday`)
- Karta 2: Nieudane dostawy (`stats.failedDeliveries`)
- Karta 3: Klienci (`stats.totalCustomers`)

### Krok 8: Implementacja templates - Filtry

8.1. **Utworzyć `templates/leads/_filters.html.twig`:**
- Form z id `filters-form`
- HTMX attributes: `hx-get="/leads"`, `hx-target="#leads-table-container"`, `hx-push-url="true"`
- Row z 4 kolumnami (filtry podstawowe)
- Select "Status" z options
- Select "Aplikacja źródłowa" z options
- Input type="date" "Data od"
- Input type="date" "Data do"
- Button submit "Zastosuj filtry"
- Button toggle "Więcej filtrów" (Bootstrap collapse)
- Link "Wyczyść filtry"
- Include advanced filters

8.2. **Utworzyć `templates/leads/_advanced_filters.html.twig`:**
- Div z class "collapse" i id "advanced-filters"
- Card body z dodatkowymi filtrami
- Input "Email klienta"
- Input "Telefon klienta"
- Select "Sortuj według" (created_at, status, application_name)
- Select "Kolejność" (asc, desc)

### Krok 9: Implementacja templates - Notyfikacja nowych leadów

9.1. **Utworzyć `templates/leads/_new_leads_notification.html.twig`:**
- Div z id `new-leads-notification`
- HTMX attributes: `hx-get="/leads/new-count?since={{ lastCheckTimestamp }}"`, `hx-trigger="every 30s"`
- If condition: `{% if newLeadsCount > 0 %}`
- Alert info z ikoną i tekstem "Pojawiły się X nowych leadów"
- Button "Załaduj nowe leady" z HTMX attributes
- HTMX: `hx-get="/leads?created_from={{ lastCheckTimestamp }}"`, `hx-target="#leads-table-container"`

### Krok 10: Implementacja templates - Tabela leadów

10.1. **Utworzyć `templates/leads/_table.html.twig`:**
- Div container z id `leads-table-container`
- If condition: `{% if leads is empty %}`
- Empty state: alert info "Nie znaleziono leadów"
- Else: table-responsive div
- Table z class "table table-hover"
- Thead z kolumnami: UUID, Data, Klient, Aplikacja, Status, CDP Status, Akcje
- Tbody z loop: `{% for lead in leads %}`
- Include row: `{% include 'leads/_table_row.html.twig' with {'lead': lead} %}`

10.2. **Utworzyć `templates/leads/_table_row.html.twig`:**
- Tr z id `lead-row-{{ lead.id }}`
- Td: UUID (skrócony, 8 pierwszych znaków)
- Td: Data utworzenia (formatted)
- Td: Dane klienta (imię, nazwisko, email, telefon)
- Td: Aplikacja źródłowa (badge)
- Td: Status (badge z kolorem zależnym od statusu)
- Td: CDP Status (ikona z tooltip)
- Td: Button "Zobacz szczegóły" z HTMX attributes
- HTMX: `hx-get="/leads/{{ lead.id }}/details"`, `hx-target="#slider-container"`

### Krok 11: Implementacja templates - Paginacja

11.1. **Utworzyć `templates/components/pagination.html.twig`:**
- Div flex container (d-flex justify-content-between)
- Div 1: Informacja o zakresie wyników ("Pokazuję X-Y z Z")
- Div 2: Nav z ul.pagination
- Li "Poprzednia" (disabled jeśli currentPage == 1)
- Loop numerów stron (z logika ... dla dużych zakresów)
- Li "Następna" (disabled jeśli currentPage == lastPage)
- Div 3: Dropdown wyboru liczby elementów na stronie
- Options: 20, 50, 100
- Wszystkie linki z HTMX attributes: `hx-get`, `hx-target`, `hx-push-url`

### Krok 12: Implementacja JavaScript helpers

12.1. **Utworzyć `assets/js/leads.js`:**
- Function `updateLastCheckTimestamp()` - zapisuje timestamp w sessionStorage
- Function `getLastCheckTimestamp()` - pobiera timestamp z sessionStorage
- Event listener `htmx:afterRequest` - aktualizuje timestamp po polling
- Function `showToast(message, type)` - wyświetla Bootstrap toast
- Event listeners dla HTMX errors: `htmx:responseError`, `htmx:sendError`, `htmx:timeout`

12.2. **Dodać script do `templates/base.html.twig`:**
```html
<script src="{{ asset('js/leads.js') }}"></script>
```

### Krok 13: Stylowanie CSS

13.1. **Utworzyć `assets/css/leads.css`:**
- Style dla tabeli (row hover effects)
- Style dla badges (status colors)
- Style dla loading indicators
- Style dla empty states
- Style dla toast notifications
- Style dla slidera (off-canvas)
- Responsive breakpoints

13.2. **Dodać link do `templates/base.html.twig`:**
```html
<link href="{{ asset('css/leads.css') }}" rel="stylesheet">
```

### Krok 14: Testy jednostkowe

14.1. **Utworzyć `tests/Unit/DTO/FiltersDto.php`:**
- Test `fromRequest()` method
- Test `toQueryParams()` method
- Test default values

14.2. **Utworzyć `tests/Unit/DTO/PaginationDtoTest.php`:**
- Test `fromArray()` method
- Test calculation `from` and `to`

14.3. **Utworzyć `tests/Unit/Service/LeadServiceTest.php`:**
- Mock Guzzle client
- Test `getLeadsList()` method
- Test `countNewLeadsSince()` method
- Test error handling

### Krok 15: Testy funkcjonalne

15.1. **Utworzyć `tests/Functional/Controller/LeadsViewControllerTest.php`:**
- Test GET `/` returns 200
- Test GET `/leads` returns 200
- Test filtering by status
- Test pagination
- Test HTMX partial updates (header `HX-Request`)
- Test authorization (tylko zalogowani użytkownicy)

15.2. **Utworzyć `tests/Functional/LeadsViewTest.php`:**
- Test full page flow
- Test filtrowanie + paginacja
- Test empty state
- Test error states

### Krok 16: Dokumentacja

16.1. **Utworzyć `docs/views/leads-dashboard.md`:**
- Opis widoku
- Screenshots (opcjonalnie)
- User flows
- Technical details

16.2. **Zaktualizować README.md:**
- Dodać informacje o nowym widoku
- Instrukcje uruchomienia
- Informacje o dependencies

### Krok 17: Finalizacja i review

17.1. **Code review checklist:**
- [ ] Wszystkie komponenty Twig utworzone
- [ ] Wszystkie DTOs zaimplementowane
- [ ] Service layer kompletny
- [ ] Kontroler z error handling
- [ ] HTMX polling działa
- [ ] Filtry działają poprawnie
- [ ] Paginacja działa
- [ ] Authorization sprawdzany
- [ ] Testy jednostkowe passed
- [ ] Testy funkcjonalne passed
- [ ] CSS responsywny
- [ ] JavaScript bez błędów
- [ ] Dokumentacja kompletna

17.2. **Manual testing:**
- Testować na różnych rozdzielczościach ekranu
- Testować wszystkie filtry
- Testować paginację
- Testować polling (sprawdzić DevTools Network)
- Testować z różnymi rolami użytkowników (BOK, CALL_CENTER, ADMIN)
- Testować error states (disconnect network, invalid filters)

17.3. **Performance check:**
- Sprawdzić czas ładowania strony (target: <2s)
- Sprawdzić czas HTMX partial updates (target: <500ms)
- Sprawdzić liczba requestów (minimize)
- Sprawdzić rozmiar assets (optimize)

17.4. **Accessibility check:**
- Sprawdzić keyboard navigation
- Sprawdzić focus indicators
- Sprawdzić ARIA labels
- Sprawdzić color contrast
- Użyć Lighthouse audit

17.5. **Final deployment:**
- Merge do main branch
- Deploy na staging environment
- Smoke tests
- Deploy na production
- Monitor errors i performance

---

## Podsumowanie

Ten plan implementacji dostarcza kompleksowego przewodnika do stworzenia widoku Dashboard (Lista leadów) w aplikacji LMS. Kluczowe punkty:

- **Tech stack**: PHP Symfony + Twig + HTMX + Bootstrap 5
- **Architektura**: Server-side rendering z partial updates przez HTMX
- **State management**: URL query params + sessionStorage
- **Auto-refresh**: HTMX polling co 30s (nowe leady) i 60s (statystyki)
- **Responsywność**: Desktop-first, Bootstrap responsive grid
- **Error handling**: Comprehensive error handling na wszystkich poziomach
- **Testing**: Unit tests + functional tests
- **Performance**: Optymalizacja przez cache (Redis) i efficient queries

Implementacja powinna zająć około 5-7 dni dla doświadczonego programisty Symfony.

