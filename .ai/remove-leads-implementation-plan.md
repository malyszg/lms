# Plan implementacji usuwania leadów

## 1. Przegląd

Funkcjonalność usuwania leadów umożliwi pracownikom call center i administratorom trwałe usunięcie leadów z systemu. Operacja będzie wymagała potwierdzenia użytkownika i zostanie zalogowana w systemie eventów dla celów audytowych.

**Kryteria akceptacji:**
- Opcja usunięcia przy każdym leadzie (tylko dla ROLE_CALL_CENTER i ROLE_ADMIN)
- Potwierdzenie operacji usunięcia
- Lead zostaje trwale usunięty z bazy danych
- Logowanie operacji usunięcia

## 2. Routing widoku

**Endpoint API:**
- `DELETE /api/leads/{uuid}` - usunięcie leada przez UUID

**Endpoint widoku:**
- `DELETE /leads/{id}` - usunięcie leada przez ID (dla HTMX)

## 3. Struktura komponentów

### 3.1 Backend Components
- **LeadController** - nowy endpoint `delete()`
- **LeadService** - nowa metoda `deleteLead()`
- **EventService** - nowa metoda `logLeadDeleted()`
- **DeleteLeadDto** - nowy DTO dla odpowiedzi

### 3.2 Frontend Components
- **Przycisk usuwania** - w kolumnie "Akcje" tabeli leadów
- **Modal potwierdzenia** - dialog potwierdzenia usunięcia
- **Toast notification** - powiadomienie o sukcesie/błędzie

## 4. Szczegóły komponentów

### 4.1 LeadController::delete()
- **Opis**: Endpoint API do usuwania leada
- **Główne elementy**: 
  - Walidacja UUID
  - Sprawdzenie uprawnień (ROLE_CALL_CENTER lub ROLE_ADMIN)
  - Wywołanie LeadService::deleteLead()
  - Logowanie eventu
- **Obsługiwane interakcje**: DELETE request z UUID leada
- **Obsługiwana walidacja**: 
  - UUID format validation
  - Lead existence check
  - User permissions check
- **Typy**: 
  - Request: `DELETE /api/leads/{uuid}`
  - Response: `DeleteLeadResponse` (200) lub `ErrorResponse` (400/403/404/500)
- **Propsy**: 
  - `uuid` (string) - UUID leada do usunięcia
  - `ipAddress` (string|null) - IP użytkownika
  - `userAgent` (string|null) - User agent

### 4.2 LeadService::deleteLead()
- **Opis**: Logika biznesowa usuwania leada
- **Główne elementy**:
  - Znalezienie leada po UUID
  - Sprawdzenie czy lead istnieje
  - Usunięcie z bazy danych (cascade delete)
  - Logowanie operacji
- **Obsługiwane interakcje**: Wywołanie przez kontroler
- **Obsługiwana walidacja**:
  - Lead existence validation
  - Database transaction safety
- **Typy**:
  - Input: `string $leadUuid`, `string|null $ipAddress`, `string|null $userAgent`
  - Output: `void` (throws exceptions on error)
- **Propsy**:
  - `leadUuid` (string) - UUID leada
  - `ipAddress` (string|null) - IP dla logowania
  - `userAgent` (string|null) - User agent dla logowania

### 4.3 EventService::logLeadDeleted()
- **Opis**: Logowanie operacji usunięcia leada
- **Główne elementy**:
  - Tworzenie eventu typu 'lead_deleted'
  - Zapisanie szczegółów operacji
  - Powiązanie z użytkownikiem
- **Obsługiwane interakcje**: Wywołanie przez LeadService
- **Obsługiwana walidacja**: Brak (internal service)
- **Typy**:
  - Input: `Lead $lead`, `string|null $ipAddress`, `string|null $userAgent`
  - Output: `Event`
- **Propsy**:
  - `lead` (Lead) - obiekt leada przed usunięciem
  - `ipAddress` (string|null) - IP użytkownika
  - `userAgent` (string|null) - User agent

### 4.4 DeleteLeadResponse DTO
- **Opis**: DTO dla odpowiedzi po usunięciu leada
- **Główne elementy**:
  - UUID usuniętego leada
  - Timestamp operacji
  - Komunikat sukcesu
- **Obsługiwane interakcje**: Serializacja do JSON
- **Obsługiwana walidacja**: Brak (read-only response)
- **Typy**:
  - `leadUuid` (string)
  - `deletedAt` (DateTimeInterface)
  - `message` (string)
- **Propsy**: Wszystkie readonly

### 4.5 Przycisk usuwania w tabeli
- **Opis**: Przycisk w kolumnie "Akcje" każdego wiersza tabeli
- **Główne elementy**:
  - Ikona kosza
  - HTMX trigger do modal potwierdzenia
  - Warunkowe wyświetlanie (tylko dla uprawnionych ról)
- **Obsługiwane interakcje**: 
  - Click → otwarcie modal potwierdzenia
  - HTMX request do endpoint usuwania
- **Obsługiwana walidacja**: 
  - Sprawdzenie uprawnień użytkownika
  - Potwierdzenie przed usunięciem
- **Typy**: HTML button z HTMX attributes
- **Propsy**:
  - `leadId` (int) - ID leada
  - `leadUuid` (string) - UUID leada
  - `userRole` (string) - rola użytkownika

### 4.6 Modal potwierdzenia
- **Opis**: Dialog potwierdzenia przed usunięciem leada
- **Główne elementy**:
  - Tytuł z ostrzeżeniem
  - Szczegóły leada (UUID, klient, data utworzenia)
  - Przyciski "Anuluj" i "Usuń"
  - HTMX integration
- **Obsługiwane interakcje**:
  - Cancel → zamknięcie modala
  - Confirm → wysłanie DELETE request
- **Obsługiwana walidacja**: 
  - Potwierdzenie użytkownika
  - Sprawdzenie uprawnień
- **Typy**: Bootstrap modal z HTMX
- **Propsy**:
  - `lead` (LeadDto) - dane leada
  - `deleteUrl` (string) - URL do usunięcia

### 4.7 Toast notification
- **Opis**: Powiadomienie o rezultacie operacji
- **Główne elementy**:
  - Ikona sukcesu/błędu
  - Komunikat
  - Auto-hide po 5 sekundach
- **Obsługiwane interakcje**: 
  - Wyświetlenie po operacji
  - Auto-hide timer
- **Obsługiwana walidacja**: Brak
- **Typy**: Bootstrap toast component
- **Propsy**:
  - `type` (string) - 'success' lub 'error'
  - `message` (string) - komunikat
  - `duration` (int) - czas wyświetlania w ms

## 5. Typy

### 5.1 DeleteLeadResponse
```php
class DeleteLeadResponse
{
    public function __construct(
        public readonly string $leadUuid,
        public readonly DateTimeInterface $deletedAt,
        public readonly string $message
    ) {}
}
```

### 5.2 ErrorResponse (istniejący)
```php
class ErrorResponseDto
{
    public function __construct(
        public readonly string $error,
        public readonly string $message,
        public readonly array $errors = []
    ) {}
}
```

### 5.3 LeadDto (istniejący - używany w modal)
```php
class LeadDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $leadUuid,
        public readonly string $status,
        public readonly DateTimeInterface $createdAt,
        public readonly CustomerDto $customer,
        public readonly string $applicationName,
        public readonly PropertyDto $property
    ) {}
}
```

## 6. Zarządzanie stanem

### 6.1 Stan w URL
- **Brak zmian** - usuwanie nie wpływa na URL (operacja nie zmienia widoku)

### 6.2 Stan w Session Storage
- **Brak zmian** - usuwanie nie wymaga cache'owania

### 6.3 Stan w Backend (Session)
- **User session** - sprawdzenie uprawnień użytkownika
- **Event logging** - zapisanie operacji w tabeli events

### 6.4 HTMX State Management
- **Optimistic update** - usunięcie wiersza z tabeli po potwierdzeniu
- **Error handling** - rollback + wyświetlenie błędu przy niepowodzeniu
- **Loading state** - wyłączenie przycisku podczas operacji

## 7. Integracja API

### 7.1 Endpoint DELETE /api/leads/{uuid}

**Request:**
```http
DELETE /api/leads/550e8400-e29b-41d4-a716-446655440000
Authorization: Bearer {jwt_token}
Content-Type: application/json
```

**Response (200 OK):**
```json
{
  "lead_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "deleted_at": "2025-01-15T14:30:00+00:00",
  "message": "Lead został pomyślnie usunięty"
}
```

**Response (403 Forbidden):**
```json
{
  "error": "Forbidden",
  "message": "Nie masz uprawnień do usunięcia tego leada"
}
```

**Response (404 Not Found):**
```json
{
  "error": "Not Found",
  "message": "Lead nie został znaleziony"
}
```

### 7.2 Endpoint DELETE /leads/{id} (HTMX)

**Request:**
```http
DELETE /leads/123
HX-Request: true
Content-Type: application/json
```

**Response (200 OK):**
```html
<!-- Usunięty wiersz tabeli -->
<tr id="lead-row-123" style="display: none;"></tr>
```

## 8. Interakcje użytkownika

### 8.1 Przepływ usuwania leada

**Flow:**
1. Użytkownik klika przycisk "Usuń" w wierszu leada
2. System sprawdza uprawnienia użytkownika
3. Jeśli brak uprawnień → wyświetlenie błędu 403
4. Jeśli ma uprawnienia → otwarcie modal potwierdzenia
5. Modal wyświetla szczegóły leada (UUID, klient, data)
6. Użytkownik klika "Usuń" w modalu
7. HTMX wysyła DELETE request
8. Backend usuwa lead z bazy danych
9. System loguje operację w events
10. Frontend usuwa wiersz z tabeli (optimistic update)
11. Wyświetlenie toast notification o sukcesie

**Implementacja:**
```html
<!-- Przycisk usuwania -->
<fluent-button appearance="subtle" 
               size="small"
               hx-get="/leads/{{ lead.id }}/delete-modal"
               hx-target="#delete-modal-container"
               hx-swap="innerHTML"
               data-bs-toggle="modal" 
               data-bs-target="#deleteModal">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
        <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
    </svg>
    Usuń
</fluent-button>

<!-- Modal potwierdzenia -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Potwierdź usunięcie leada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Czy na pewno chcesz usunąć ten lead?</p>
                <div class="alert alert-warning">
                    <strong>UUID:</strong> {{ lead.leadUuid }}<br>
                    <strong>Klient:</strong> {{ lead.customer.email }}<br>
                    <strong>Data utworzenia:</strong> {{ lead.createdAt|date('Y-m-d H:i') }}
                </div>
                <p><strong>Uwaga:</strong> Ta operacja jest nieodwracalna!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                <button type="button" 
                        class="btn btn-danger"
                        hx-delete="/api/leads/{{ lead.leadUuid }}"
                        hx-target="#lead-row-{{ lead.id }}"
                        hx-swap="outerHTML"
                        hx-confirm="Czy na pewno chcesz usunąć ten lead?"
                        data-bs-dismiss="modal">
                    Usuń
                </button>
            </div>
        </div>
    </div>
</div>
```

### 8.2 Obsługa błędów

**Flow błędów:**
1. Błąd 403 (brak uprawnień) → toast "Nie masz uprawnień do tej operacji"
2. Błąd 404 (lead nie istnieje) → toast "Lead nie został znaleziony"
3. Błąd 500 (błąd serwera) → toast "Wystąpił błąd serwera. Spróbuj ponownie"
4. Błąd sieci → toast "Błąd połączenia. Sprawdź internet"

**Implementacja:**
```javascript
// HTMX error handling
document.addEventListener('htmx:responseError', function(event) {
    const status = event.detail.xhr.status;
    let message = 'Wystąpił nieoczekiwany błąd';
    
    switch(status) {
        case 403:
            message = 'Nie masz uprawnień do tej operacji';
            break;
        case 404:
            message = 'Lead nie został znaleziony';
            break;
        case 500:
            message = 'Wystąpił błąd serwera. Spróbuj ponownie';
            break;
    }
    
    showToast('error', message);
});
```

## 9. Warunki i walidacja

### 9.1 Uprawnienia użytkownika
- **ROLE_CALL_CENTER**: Może usuwać leady
- **ROLE_ADMIN**: Może usuwać leady
- **ROLE_USER**: Nie może usuwać leadów
- **ROLE_BOK**: Nie może usuwać leadów (read-only)

### 9.2 Walidacja danych
- **UUID format**: Sprawdzenie czy UUID jest prawidłowy
- **Lead existence**: Sprawdzenie czy lead istnieje w bazie
- **User authentication**: Sprawdzenie czy użytkownik jest zalogowany
- **User authorization**: Sprawdzenie czy użytkownik ma odpowiednie uprawnienia

### 9.3 Walidacja biznesowa
- **Cascade delete**: Usunięcie leada usuwa również powiązane dane (property, events)
- **Event logging**: Każda operacja usunięcia musi być zalogowana
- **Transaction safety**: Operacja musi być atomowa

## 10. Obsługa błędów

### 10.1 Błędy walidacji
- **400 Bad Request**: Nieprawidłowy format UUID
- **401 Unauthorized**: Użytkownik nie jest zalogowany
- **403 Forbidden**: Użytkownik nie ma uprawnień do usunięcia

### 10.2 Błędy biznesowe
- **404 Not Found**: Lead nie istnieje w systemie
- **409 Conflict**: Lead jest już w trakcie przetwarzania (rzadki przypadek)

### 10.3 Błędy techniczne
- **500 Internal Server Error**: Błąd bazy danych, błąd serwera
- **503 Service Unavailable**: Serwis tymczasowo niedostępny

### 10.4 Frontend error handling
- **HTMX responseError**: Obsługa błędów HTTP
- **HTMX sendError**: Obsługa błędów sieci
- **JavaScript errors**: Obsługa błędów JavaScript
- **Toast notifications**: Wyświetlanie komunikatów błędów

## 11. Kroki implementacji

1. **Utworzenie DeleteLeadResponse DTO**
   - Nowy plik `src/DTO/DeleteLeadResponse.php`
   - Implementacja konstruktora i właściwości readonly

2. **Rozszerzenie EventServiceInterface i EventService**
   - Dodanie metody `logLeadDeleted()` do interfejsu
   - Implementacja w `EventService.php`

3. **Rozszerzenie LeadServiceInterface i LeadService**
   - Dodanie metody `deleteLead()` do interfejsu
   - Implementacja w `LeadService.php` z transakcją

4. **Rozszerzenie LeadController**
   - Dodanie endpoint `DELETE /api/leads/{uuid}`
   - Implementacja metody `delete()` z walidacją i autoryzacją

5. **Utworzenie szablonu modal potwierdzenia**
   - Nowy plik `templates/leads/_delete_modal.html.twig`
   - Bootstrap modal z HTMX integration

6. **Modyfikacja szablonu tabeli leadów**
   - Dodanie przycisku usuwania w `_table_row.html.twig`
   - Warunkowe wyświetlanie na podstawie uprawnień

7. **Rozszerzenie JavaScript error handling**
   - Dodanie obsługi błędów usuwania w `app.js`
   - Toast notifications dla różnych typów błędów

8. **Utworzenie testów jednostkowych**
   - Testy dla `DeleteLeadResponse`
   - Testy dla `EventService::logLeadDeleted()`
   - Testy dla `LeadService::deleteLead()`

9. **Utworzenie testów funkcjonalnych**
   - Testy endpoint `DELETE /api/leads/{uuid}`
   - Testy autoryzacji i walidacji
   - Testy obsługi błędów

10. **Aktualizacja dokumentacji API**
    - Dodanie endpoint usuwania do `docs/API.md`
    - Przykłady request/response
    - Opis błędów i kodów odpowiedzi

11. **Testowanie integracyjne**
    - Testy end-to-end przepływu usuwania
    - Testy różnych ról użytkowników
    - Testy obsługi błędów

12. **Optymalizacja i refaktoring**
    - Przegląd kodu pod kątem wydajności
    - Usunięcie nieużywanego kodu
    - Aktualizacja komentarzy i dokumentacji
