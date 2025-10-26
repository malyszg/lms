# Plan implementacji widoku Klientów

## 1. Przegląd
Widok klientów to główny interfejs do zarządzania bazą klientów w systemie LMS. Umożliwia przeglądanie listy klientów, filtrowanie, sortowanie oraz edycję preferencji klientów. Widok jest przeznaczony głównie dla użytkowników z rolami Call Center i BOK, którzy potrzebują szybkiego dostępu do informacji o klientach i możliwości aktualizacji ich preferencji nieruchomościowych.

## 2. Routing widoku
- **Główny widok:** `GET /customers` - lista klientów z filtrami i paginacją
- **Szczegóły klienta:** `GET /customers/{id}` - szczegóły klienta w sliderze
- **Aktualizacja preferencji:** `PUT /customers/{id}/preferences` - edycja preferencji
- **Statystyki:** `GET /customers/stats` - statystyki klientów (HTMX)
- **Tabela:** `GET /customers/table` - tabela klientów (HTMX)
- **Leady klienta:** `GET /customers/{id}/leads` - leady powiązane z klientem

## 3. Struktura komponentów
```
CustomersViewController
├── index() - lista klientów
├── show() - szczegóły klienta
├── updatePreferences() - aktualizacja preferencji
├── stats() - statystyki (HTMX)
├── table() - tabela (HTMX)
└── leads() - leady klienta (HTMX)

CustomerViewService
├── getCustomersList() - pobieranie listy
├── getCustomerDetails() - szczegóły klienta
├── updateCustomerPreferences() - aktualizacja preferencji
└── getCustomerLeads() - leady klienta

Templates
├── customers/index.html.twig - główny widok
├── customers/_table.html.twig - tabela klientów
├── customers/_filters.html.twig - filtry
├── customers/_stats.html.twig - statystyki
├── customers/_details_slider.html.twig - slider szczegółów
├── customers/_preferences_form.html.twig - formularz preferencji
└── customers/_leads_section.html.twig - sekcja leadów
```

## 4. Szczegóły komponentów

### CustomersViewController
- **Opis komponentu:** Główny kontroler zarządzający widokiem klientów
- **Główne elementy:** 
  - Lista klientów z filtrami i paginacją
  - Slider szczegółów klienta
  - Formularz edycji preferencji
  - Statystyki dashboardu
- **Obsługiwane interakcje:**
  - Filtrowanie i sortowanie listy
  - Otwieranie szczegółów klienta
  - Edycja preferencji przez HTMX
  - Nawigacja między stronami
- **Obsługiwana walidacja:**
  - Walidacja uprawnień użytkownika (RBAC)
  - Walidacja parametrów żądań
  - Walidacja danych preferencji
  - Sprawdzanie istnienia klienta
- **Typy:** `CustomerDetailDto`, `UpdatePreferencesRequest`, `PreferencesDto`, `FiltersDto`
- **Propsy:** `Request $request`, `int $id`, `UpdatePreferencesRequest $preferencesRequest`

### CustomerViewService
- **Opis komponentu:** Serwis biznesowy zarządzający logiką widoku klientów
- **Główne elementy:**
  - Pobieranie listy klientów z filtrami
  - Szczegóły klienta z powiązanymi leadami
  - Aktualizacja preferencji klienta
  - Statystyki klientów
- **Obsługiwane interakcje:**
  - Filtrowanie po email, telefon, dacie utworzenia
  - Sortowanie po różnych kryteriach
  - Paginacja wyników
  - Aktualizacja preferencji w czasie rzeczywistym
- **Obsługiwana walidacja:**
  - Walidacja filtrów wyszukiwania
  - Walidacja danych preferencji (cena min/max, lokalizacja)
  - Sprawdzanie uprawnień do edycji
  - Walidacja formatów danych
- **Typy:** `CustomersListApiResponse`, `CustomerDetailDto`, `UpdatePreferencesRequest`, `PreferencesDto`
- **Propsy:** `FiltersDto $filters`, `int $page`, `int $limit`, `int $customerId`

### CustomerStatsComponent
- **Opis komponentu:** Komponent wyświetlający statystyki klientów
- **Główne elementy:**
  - Łączna liczba klientów
  - Liczba nowych klientów dzisiaj
  - Liczba klientów z leadami
  - Liczba klientów z preferencjami
- **Obsługiwane interakcje:**
  - Automatyczne odświeżanie co 30 sekund
  - Aktualizacja przez HTMX
- **Obsługiwana walidacja:**
  - Sprawdzanie uprawnień do wyświetlania statystyk
- **Typy:** `CustomerStatsDto`
- **Propsy:** Brak

### CustomerTableComponent
- **Opis komponentu:** Tabela wyświetlająca listę klientów
- **Główne elementy:**
  - Kolumny: ID, Email, Telefon, Imię/Nazwisko, Liczba leadów, Data utworzenia, Akcje
  - Sortowanie po kolumnach
  - Paginacja
  - Przyciski akcji (szczegóły, edycja preferencji)
- **Obsługiwane interakcje:**
  - Sortowanie po kliknięciu w nagłówek
  - Otwieranie szczegółów klienta
  - Nawigacja między stronami
  - Filtrowanie wyników
- **Obsługiwana walidacja:**
  - Walidacja parametrów sortowania
  - Sprawdzanie uprawnień do akcji
- **Typy:** `CustomerDto[]`, `PaginationDto`
- **Propsy:** `array $customers`, `PaginationDto $pagination`, `FiltersDto $filters`

### CustomerDetailsSlider
- **Opis komponentu:** Slider wyświetlający szczegóły klienta po prawej stronie
- **Główne elementy:**
  - Informacje podstawowe klienta
  - Formularz preferencji (edytowalny)
  - Lista powiązanych leadów
  - Przyciski akcji
- **Obsługiwane interakcje:**
  - Otwieranie/zamykanie slidera
  - Edycja preferencji w czasie rzeczywistym
  - Nawigacja do szczegółów leadów
  - Zapisywanie zmian przez HTMX
- **Obsługiwana walidacja:**
  - Walidacja formularza preferencji
  - Sprawdzanie uprawnień do edycji
  - Walidacja danych wejściowych
- **Typy:** `CustomerDetailDto`, `UpdatePreferencesRequest`
- **Propsy:** `CustomerDetailDto $customer`, `bool $isEditable`

### CustomerPreferencesForm
- **Opis komponentu:** Formularz edycji preferencji klienta
- **Główne elementy:**
  - Pole ceny minimalnej
  - Pole ceny maksymalnej
  - Pole lokalizacji
  - Pole miasta
  - Przycisk zapisu
- **Obsługiwane interakcje:**
  - Wpisywanie danych
  - Walidacja w czasie rzeczywistym
  - Zapisywanie przez HTMX
  - Wyświetlanie komunikatów o błędach
- **Obsługiwana walidacja:**
  - Cena minimalna >= 0
  - Cena maksymalna >= cena minimalna
  - Długość tekstu (lokalizacja: 255 znaków, miasto: 100 znaków)
  - Format danych numerycznych
- **Typy:** `PreferencesDto`, `UpdatePreferencesRequest`
- **Propsy:** `PreferencesDto $preferences`, `int $customerId`

## 5. Typy

### CustomerDetailDto
```php
class CustomerDetailDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $phone,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly DateTimeInterface $createdAt,
        public readonly DateTimeInterface $updatedAt,
        public readonly PreferencesDto $preferences,
        public readonly array $leads, // LeadItemDto[]
        public readonly int $totalLeads,
        public readonly int $newLeads,
        public readonly int $contactedLeads,
        public readonly int $qualifiedLeads,
        public readonly int $convertedLeads
    ) {}
}
```

### CustomerStatsDto
```php
class CustomerStatsDto
{
    public function __construct(
        public readonly int $totalCustomers,
        public readonly int $customersWithLeads,
        public readonly int $customersWithPreferences,
        public readonly int $newCustomersToday
    ) {}
}
```

### CustomersListApiResponse
```php
class CustomersListApiResponse
{
    public function __construct(
        public readonly array $customers, // CustomerDto[]
        public readonly PaginationDto $pagination
    ) {}
}
```

### CustomerFiltersDto
```php
class CustomerFiltersDto
{
    public function __construct(
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?DateTimeInterface $createdFrom = null,
        public readonly ?DateTimeInterface $createdTo = null,
        public readonly ?int $minLeads = null,
        public readonly ?int $maxLeads = null,
        public readonly string $sort = 'created_at',
        public readonly string $order = 'desc'
    ) {}
}
```

## 6. Zarządzanie stanem
Stan widoku klientów jest zarządzany przez:
- **Filtry wyszukiwania** - przechowywane w URL i formularzu
- **Stan paginacji** - aktualna strona i liczba elementów
- **Stan slidera** - otwarty/zamknięty, ID aktualnego klienta
- **Stan formularza preferencji** - dane wejściowe i walidacja
- **Stan statystyk** - automatyczne odświeżanie co 30 sekund

Stan jest synchronizowany między komponentami przez HTMX i URL parameters.

## 7. Integracja API

### Endpoint: GET /customers
- **Typ żądania:** Query parameters (filtry, paginacja)
- **Typ odpowiedzi:** `CustomersListApiResponse`
- **Parametry:** `page`, `limit`, `email`, `phone`, `created_from`, `created_to`, `sort`, `order`

### Endpoint: GET /customers/{id}
- **Typ żądania:** Path parameter (ID klienta)
- **Typ odpowiedzi:** `CustomerDetailDto`
- **Parametry:** `id` (int)

### Endpoint: PUT /customers/{id}/preferences
- **Typ żądania:** `UpdatePreferencesRequest` (JSON)
- **Typ odpowiedzi:** `PreferencesDto`
- **Parametry:** `id` (int), `price_min`, `price_max`, `location`, `city`

### Endpoint: GET /customers/stats
- **Typ żądania:** Brak parametrów
- **Typ odpowiedzi:** `CustomerStatsDto`
- **Parametry:** Brak

### Endpoint: GET /customers/{id}/leads
- **Typ żądania:** Path parameter + query parameters
- **Typ odpowiedzi:** `LeadsListApiResponse`
- **Parametry:** `id` (int), `page`, `limit`

## 8. Interakcje użytkownika

### Przeglądanie listy klientów
1. Użytkownik wchodzi na `/customers`
2. System ładuje statystyki i tabelę klientów
3. Użytkownik może filtrować po email, telefon, dacie
4. Użytkownik może sortować po różnych kolumnach
5. Użytkownik może zmieniać liczbę elementów na stronę

### Otwieranie szczegółów klienta
1. Użytkownik klika przycisk "Szczegóły" w tabeli
2. System otwiera slider po prawej stronie
3. Slider wyświetla informacje o kliencie
4. Użytkownik może edytować preferencje
5. Użytkownik może przeglądać powiązane leady

### Edycja preferencji
1. Użytkownik otwiera szczegóły klienta
2. Użytkownik edytuje pola w formularzu preferencji
3. System waliduje dane w czasie rzeczywistym
4. Użytkownik klika "Zapisz preferencje"
5. System zapisuje zmiany przez HTMX
6. System wyświetla komunikat o sukcesie/błędzie

### Nawigacja między stronami
1. Użytkownik klika numer strony w paginacji
2. System ładuje nową stronę przez HTMX
3. Tabela jest aktualizowana bez przeładowania strony
4. URL jest aktualizowany dla możliwości udostępnienia

## 9. Warunki i walidacja

### Warunki dostępu
- **ROLE_USER** - podstawowy dostęp do listy klientów
- **ROLE_CALL_CENTER** - pełny dostęp, edycja preferencji
- **ROLE_BOK** - tylko do odczytu
- **ROLE_ADMIN** - pełny dostęp

### Walidacja filtrów
- **Email:** format email, maksymalnie 255 znaków
- **Telefon:** format telefonu, maksymalnie 20 znaków
- **Data od/do:** format YYYY-MM-DD, data "od" <= data "do"
- **Sortowanie:** tylko dozwolone kolumny (created_at, email, phone, leads_count)
- **Kolejność:** tylko "asc" lub "desc"

### Walidacja preferencji
- **Cena minimalna:** >= 0, maksymalnie 2 miejsca po przecinku
- **Cena maksymalna:** >= cena minimalna, maksymalnie 2 miejsca po przecinku
- **Lokalizacja:** maksymalnie 255 znaków, opcjonalne
- **Miasto:** maksymalnie 100 znaków, opcjonalne

### Walidacja biznesowa
- Klient musi istnieć w systemie
- Użytkownik musi mieć uprawnienia do edycji
- Preferencje muszą być logiczne (cena min <= cena max)
- Wszystkie operacje są logowane w systemie eventów

## 10. Obsługa błędów

### Błędy walidacji
- **400 Bad Request** - nieprawidłowe dane wejściowe
- Wyświetlanie komunikatów błędów w formularzu
- Podświetlanie pól z błędami
- Blokowanie wysyłania formularza do poprawienia błędów

### Błędy autoryzacji
- **403 Forbidden** - brak uprawnień
- Przekierowanie do strony logowania
- Wyświetlanie komunikatu o braku uprawnień

### Błędy serwera
- **500 Internal Server Error** - błąd serwera
- Wyświetlanie ogólnego komunikatu o błędzie
- Logowanie szczegółów błędu
- Możliwość ponowienia operacji

### Błędy sieci
- **Timeout** - przekroczenie czasu oczekiwania
- Wyświetlanie komunikatu o problemie z połączeniem
- Możliwość ponowienia żądania
- Automatyczne ponowienie dla statystyk

### Błędy HTMX
- **422 Unprocessable Entity** - błąd walidacji HTMX
- Wyświetlanie komunikatów błędów w odpowiednim komponencie
- Zachowanie stanu formularza
- Toast notifications dla błędów

## 11. Kroki implementacji

1. **Rozszerzenie CustomerService** - dodanie metod getCustomersList(), getCustomerDetails(), updateCustomerPreferences()
2. **Stworzenie nowych DTO** - CustomerDetailDto, CustomerStatsDto, CustomersListApiResponse, CustomerFiltersDto
3. **Implementacja CustomerViewService** - logika biznesowa dla widoku klientów
4. **Rozszerzenie CustomersViewController** - dodanie endpointów index(), show(), updatePreferences(), stats(), table(), leads()
5. **Dodanie routingu** - konfiguracja ścieżek w config/routes.yaml
6. **Stworzenie szablonu głównego** - templates/customers/index.html.twig z podstawową strukturą
7. **Implementacja tabeli klientów** - templates/customers/_table.html.twig z sortowaniem i paginacją
8. **Stworzenie filtrów** - templates/customers/_filters.html.twig z HTMX
9. **Implementacja statystyk** - templates/customers/_stats.html.twig z automatycznym odświeżaniem
10. **Stworzenie slidera szczegółów** - templates/customers/_details_slider.html.twig
11. **Implementacja formularza preferencji** - templates/customers/_preferences_form.html.twig z walidacją
12. **Dodanie sekcji leadów** - templates/customers/_leads_section.html.twig
13. **Implementacja JavaScript** - obsługa slidera, toast notifications, error handling
14. **Dodanie testów jednostkowych** - CustomerViewServiceTest, CustomersViewControllerTest
15. **Stworzenie testów funkcjonalnych** - testy endpointów API
16. **Implementacja testów E2E** - scenariusze użytkownika
17. **Optymalizacja wydajności** - indeksy bazy danych, caching, lazy loading
18. **Dokumentacja** - komentarze w kodzie, README, instrukcje użytkowania
