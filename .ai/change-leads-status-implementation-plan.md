# Plan implementacji zmiany statusu leada

## 1. Przegląd
Implementacja funkcjonalności zmiany statusu leada zgodnie z User Story US-005. Funkcjonalność umożliwi pracownikom call center zmianę statusu leada po kontakcie z klientem, z pełnym logowaniem operacji i kontrolą uprawnień.

## 2. Routing widoku
- **Endpoint API**: `PUT /api/leads/{id}` - zgodnie z API Plan
- **Endpoint UI**: `PUT /leads/{id}/update-status` - istniejący stub w LeadDetailsController
- **Route name**: `lead_update_status` (istniejący)

## 3. Struktura komponentów

### 3.1 Backend Components
- **LeadController** - API endpoint dla zewnętrznych aplikacji
- **LeadDetailsController** - UI endpoint dla panelu webowego  
- **LeadService** - logika biznesowa zmiany statusu
- **EventService** - logowanie operacji zmiany statusu
- **UpdateLeadRequest** - DTO dla walidacji danych (istniejący)

### 3.2 Frontend Components
- **Dropdown statusu** - w sliderze szczegółów leada (istniejący)
- **Toast notifications** - potwierdzenie operacji
- **HTMX integration** - automatyczna aktualizacja UI

## 4. Szczegóły komponentów

### 4.1 LeadController (API)
- **Opis**: Endpoint API dla zewnętrznych aplikacji
- **Główne elementy**: Walidacja UUID, autoryzacja, logowanie
- **Obsługiwane interakcje**: PUT request z JSON body
- **Obsługiwana walidacja**: UUID format, status enum, uprawnienia użytkownika
- **Typy**: UpdateLeadRequest (input), LeadDetailDto (output)
- **Propsy**: Request body, UUID path parameter

### 4.2 LeadDetailsController (UI)
- **Opis**: Endpoint dla panelu webowego z HTMX
- **Główne elementy**: Walidacja ID, autoryzacja, HTMX response
- **Obsługiwane interakcje**: PUT request z form data
- **Obsługiwana walidacja**: Integer ID, status enum, uprawnienia ROLE_CALL_CENTER/ROLE_ADMIN
- **Typy**: UpdateLeadRequest (input), HTMX partial response
- **Propsy**: Request body, ID path parameter

### 4.3 LeadService
- **Opis**: Logika biznesowa zmiany statusu leada
- **Główne elementy**: Walidacja statusu, transakcja, logowanie
- **Obsługiwane interakcje**: updateLeadStatus() method
- **Obsługiwana walidacja**: Status transitions, lead existence
- **Typy**: Lead (entity), UpdateLeadRequest (input)
- **Propsy**: Lead ID/UUID, nowy status, IP address, user agent

### 4.4 EventService
- **Opis**: Logowanie operacji zmiany statusu
- **Główne elementy**: logLeadStatusChanged() method
- **Obsługiwane interakcje**: Tworzenie eventu w bazie danych
- **Obsługiwana walidacja**: Lead existence, user context
- **Typy**: Lead (entity), Event (output)
- **Propsy**: Lead, stary status, nowy status, user ID, IP address

### 4.5 Status Dropdown (UI)
- **Opis**: Dropdown do wyboru statusu w sliderze szczegółów
- **Główne elementy**: Select element z opcjami statusów
- **Obsługiwane interakcje**: Change event, HTMX PUT request
- **Obsługiwana walidacja**: Client-side validation, role-based visibility
- **Typy**: HTML select element
- **Propsy**: Lead ID, current status, user role

## 5. Typy

### 5.1 Status Enum
```php
enum LeadStatus: string
{
    case NEW = 'new';
    case CONTACTED = 'contacted';
    case QUALIFIED = 'qualified';
    case CONVERTED = 'converted';
    case REJECTED = 'rejected';
}
```

### 5.2 UpdateLeadRequest (istniejący)
```php
class UpdateLeadRequest
{
    public function __construct(
        public readonly string $status
    ) {}
}
```

### 5.3 UpdateLeadResponse (nowy)
```php
class UpdateLeadResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $leadUuid,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly DateTimeInterface $updatedAt,
        public readonly string $message
    ) {}
}
```

## 6. Zarządzanie stanem

### 6.1 Backend State
- **Lead entity**: Status field w bazie danych
- **Event logging**: Wszystkie zmiany statusu w tabeli events
- **Transaction safety**: Wszystkie operacje w transakcji

### 6.2 Frontend State
- **HTMX updates**: Automatyczna aktualizacja UI po zmianie
- **Optimistic updates**: Natychmiastowa zmiana w UI, rollback przy błędzie
- **Toast notifications**: Potwierdzenie sukcesu/błędu

## 7. Integracja API

### 7.1 API Endpoint (PUT /api/leads/{id})
- **Request**: JSON z polem `status`
- **Response**: Pełne dane leada (LeadDetailDto)
- **Authorization**: JWT token (przyszłość) / Session (MVP)
- **Validation**: UUID format, status enum, uprawnienia

### 7.2 UI Endpoint (PUT /leads/{id}/update-status)
- **Request**: Form data z polem `status`
- **Response**: HTMX partial response
- **Authorization**: Symfony Security (ROLE_CALL_CENTER/ROLE_ADMIN)
- **Validation**: Integer ID, status enum, uprawnienia

## 8. Interakcje użytkownika

### 8.1 Przepływ zmiany statusu
1. Użytkownik otwiera szczegóły leada w sliderze
2. Widzi dropdown z aktualnym statusem
3. Wybiera nowy status z dropdown
4. HTMX automatycznie wysyła PUT request
5. System waliduje uprawnienia i dane
6. Status zostaje zaktualizowany w bazie
7. Event zostaje zalogowany
8. UI zostaje zaktualizowany
9. Toast notification potwierdza operację

### 8.2 Obsługa błędów
- **Brak uprawnień**: Komunikat "Nie masz uprawnień do tej operacji"
- **Nieprawidłowy status**: Walidacja po stronie klienta i serwera
- **Lead nie istnieje**: Komunikat "Lead nie został znaleziony"
- **Błąd bazy danych**: Rollback transakcji, komunikat błędu

## 9. Warunki i walidacja

### 9.1 Walidacja po stronie serwera
- **Status enum**: Tylko dozwolone wartości (new, contacted, qualified, converted, rejected)
- **Lead existence**: Sprawdzenie czy lead istnieje
- **Uprawnienia**: ROLE_CALL_CENTER lub ROLE_ADMIN
- **Status transitions**: Walidacja czy przejście jest dozwolone

### 9.2 Walidacja po stronie klienta
- **HTML5 validation**: Required field, enum values
- **Role-based visibility**: Dropdown widoczny tylko dla uprawnionych ról
- **HTMX validation**: Client-side validation przed wysłaniem

### 9.3 Business Rules
- **Status transitions**: Wszystkie przejścia są dozwolone (brak ograniczeń)
- **Audit trail**: Każda zmiana statusu jest logowana
- **User context**: Logowanie z informacją o użytkowniku

## 10. Obsługa błędów

### 10.1 Backend Errors
- **400 Bad Request**: Nieprawidłowy format danych
- **401 Unauthorized**: Brak autoryzacji
- **403 Forbidden**: Brak uprawnień do operacji
- **404 Not Found**: Lead nie istnieje
- **422 Unprocessable Entity**: Błąd walidacji
- **500 Internal Server Error**: Błąd serwera

### 10.2 Frontend Errors
- **Toast notifications**: Wyświetlanie błędów użytkownikowi
- **HTMX error handling**: Obsługa błędów HTMX
- **Rollback UI**: Przywrócenie poprzedniego stanu przy błędzie

## 11. Kroki implementacji

### 11.1 Faza 1: Backend API
1. **Rozszerzenie LeadServiceInterface** o metodę `updateLeadStatus()`
2. **Implementacja w LeadService** z transakcją i logowaniem
3. **Rozszerzenie EventServiceInterface** o metodę `logLeadStatusChanged()`
4. **Implementacja w EventService** z pełnym kontekstem
5. **Utworzenie UpdateLeadResponse DTO**
6. **Implementacja endpointu w LeadController** (PUT /api/leads/{id})

### 11.2 Faza 2: UI Integration
7. **Implementacja endpointu w LeadDetailsController** (PUT /leads/{id}/update-status)
8. **Aktualizacja szablonu _details_slider.html.twig** z poprawionym HTMX
9. **Dodanie walidacji po stronie klienta**
10. **Implementacja toast notifications**

### 11.3 Faza 3: Testing
11. **Testy jednostkowe LeadService::updateLeadStatus()**
12. **Testy jednostkowe EventService::logLeadStatusChanged()**
13. **Testy funkcjonalne kontrolerów**
14. **Testy integracyjne przepływu zmiany statusu**

### 11.4 Faza 4: Documentation & Polish
15. **Aktualizacja dokumentacji API**
16. **Testy E2E zmiany statusu**
17. **Optymalizacja wydajności**
18. **Code review i refactoring**

## 12. Szczegóły techniczne

### 12.1 Database Schema
- **Tabela leads**: Pole `status` (ENUM) - istniejące
- **Tabela events**: Nowy typ eventu `lead_status_changed` - istniejąca struktura

### 12.2 Security
- **Authorization**: Symfony Security z rolami ROLE_CALL_CENTER/ROLE_ADMIN
- **CSRF Protection**: Token CSRF w formularzach
- **Input Validation**: Walidacja wszystkich danych wejściowych
- **Audit Logging**: Pełne logowanie operacji z kontekstem użytkownika

### 12.3 Performance
- **Database Indexes**: Istniejące indeksy na status i updated_at
- **Caching**: Brak cache'owania dla operacji zmiany statusu (krytyczne dane)
- **HTMX**: Minimalne obciążenie sieci dzięki partial updates

## 13. Zgodność z istniejącym kodem

### 13.1 Wykorzystanie istniejących komponentów
- **UpdateLeadRequest DTO**: Istniejący, gotowy do użycia
- **Lead entity**: Istniejące metody getStatus()/setStatus()
- **EventService**: Rozszerzenie o nową metodę logowania
- **LeadDetailsController**: Implementacja istniejącego stub'a

### 13.2 Zachowanie spójności
- **Naming conventions**: Zgodne z istniejącymi konwencjami
- **Error handling**: Zgodne z istniejącymi wzorcami
- **Response format**: Zgodne z istniejącymi DTO
- **Security patterns**: Zgodne z istniejącymi kontrolerami

## 14. Metryki sukcesu

### 14.1 Funkcjonalne
- ✅ Użytkownik może zmienić status leada
- ✅ Wszystkie zmiany są logowane w events
- ✅ Uprawnienia są prawidłowo weryfikowane
- ✅ UI aktualizuje się automatycznie

### 14.2 Techniczne
- ✅ Response time < 500ms dla operacji zmiany statusu
- ✅ 100% pokrycie testami krytycznych ścieżek
- ✅ Brak błędów w logach po implementacji
- ✅ Zgodność z istniejącymi wzorcami kodu

## 15. Ryzyka i mitgacja

### 15.1 Ryzyka techniczne
- **Concurrent updates**: Rozwiązane przez transakcje bazy danych
- **HTMX errors**: Obsługa błędów i rollback UI
- **Performance**: Minimalne obciążenie dzięki HTMX

### 15.2 Ryzyka biznesowe
- **Data integrity**: Transakcje zapewniają spójność danych
- **Audit compliance**: Pełne logowanie wszystkich operacji
- **User experience**: Intuicyjny interfejs z natychmiastową informacją zwrotną

## 16. Podsumowanie

Implementacja zmiany statusu leada będzie wykorzystywać istniejące komponenty systemu, rozszerzając je o nową funkcjonalność. Kluczowe elementy to:

1. **Backend**: Rozszerzenie LeadService i EventService o nowe metody
2. **API**: Implementacja endpointów zgodnie z API Plan
3. **UI**: Wykorzystanie istniejącego dropdown'a z HTMX
4. **Security**: Kontrola uprawnień przez Symfony Security
5. **Logging**: Pełne logowanie operacji w tabeli events

Implementacja będzie zgodna z istniejącymi wzorcami kodu i zapewni pełną funkcjonalność zgodną z User Story US-005.
