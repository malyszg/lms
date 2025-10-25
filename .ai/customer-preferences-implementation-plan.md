# Plan implementacji zapisu preferencji klienta

## 1. Przegląd
Implementacja funkcjonalności zapisu preferencji klienta zgodnie z User Story US-004. Funkcjonalność umożliwi pracownikom call center edycję preferencji klientów po rozmowie telefonicznej, z pełnym logowaniem operacji i kontrolą uprawnień. Preferencje obejmują cenę min/max, lokalizację, miasto oraz dodatkowe informacje kontaktowe.

## 2. Routing widoku
- **Endpoint API**: `PUT /api/customers/{id}/preferences` - zgodnie z API Plan
- **Endpoint UI**: `PUT /leads/{id}/update-preferences` - istniejący stub w LeadDetailsController
- **Route name**: `lead_update_preferences` (istniejący)

## 3. Struktura komponentów

### 3.1 Backend Components
- **LeadDetailsController** - UI endpoint dla panelu webowego
- **CustomerService** - rozszerzenie o metodę updateCustomerPreferences()
- **EventService** - logowanie operacji zmiany preferencji
- **UpdatePreferencesRequest** - DTO dla walidacji danych (istniejący)
- **PreferencesDto** - DTO dla odpowiedzi (istniejący)

### 3.2 Frontend Components
- **PreferencesForm** - formularz edycji preferencji w sliderze szczegółów leada
- **FormFields** - pola formularza: preferowany czas kontaktu, sposób kontaktu, notatki
- **SubmitButton** - przycisk zapisu z HTMX integration
- **ToastNotifications** - powiadomienia o sukcesie/błędach

## 4. Szczegóły komponentów

### PreferencesForm
- Opis komponentu: Formularz edycji preferencji klienta w sliderze szczegółów leada
- Główne elementy: 
  - Select "Preferowany czas kontaktu" (morning, afternoon, evening)
  - Select "Preferowany sposób kontaktu" (phone, email, sms)
  - Textarea "Notatki" (dodatkowe informacje o kliencie)
  - Przycisk "Zapisz preferencje"
- Obsługiwane interakcje: 
  - HTMX PUT request do `/leads/{id}/update-preferences`
  - Target: `#preferences-form` z `outerHTML` swap
  - Toast notifications po zapisie
- Obsługiwana walidacja: 
  - Walidacja po stronie serwera (cena min/max, długość tekstu)
  - Walidacja uprawnień (ROLE_CALL_CENTER, ROLE_ADMIN)
- Typy: HTML form z HTMX attributes
- Propsy: lead.id, customer data, current preferences

### CustomerService
- Opis komponentu: Serwis do zarządzania preferencjami klientów
- Główne elementy: 
  - Metoda `updateCustomerPreferences()`
  - Walidacja danych preferencji
  - Transakcje bazodanowe
- Obsługiwane interakcje: 
  - Tworzenie/aktualizacja preferencji w tabeli customer_preferences
  - Logowanie zmian przez EventService
- Obsługiwana walidacja: 
  - Walidacja cen (min < max)
  - Walidacja długości tekstu
  - Walidacja istnienia klienta
- Typy: PHP Service class
- Propsy: customerId, UpdatePreferencesRequest, userId, ipAddress, userAgent

### EventService
- Opis komponentu: Serwis do logowania operacji na preferencjach
- Główne elementy: 
  - Metoda `logCustomerPreferencesChanged()`
  - Szczegółowe informacje o zmianach
- Obsługiwane interakcje: 
  - Zapis eventu w tabeli events
  - Tracking starych i nowych wartości
- Obsługiwana walidacja: 
  - Walidacja danych eventu
  - Walidacja użytkownika
- Typy: PHP Service class
- Propsy: customer, oldPreferences, newPreferences, userId, ipAddress, userAgent

## 5. Typy

### UpdatePreferencesRequest (istniejący)
```php
class UpdatePreferencesRequest {
    public readonly ?float $priceMin;
    public readonly ?float $priceMax;
    public readonly ?string $location;
    public readonly ?string $city;
}
```

### PreferencesDto (istniejący)
```php
class PreferencesDto {
    public readonly ?float $priceMin;
    public readonly ?float $priceMax;
    public readonly ?string $location;
    public readonly ?string $city;
}
```

### UpdatePreferencesResponse (nowy)
```php
class UpdatePreferencesResponse {
    public readonly int $id;
    public readonly int $customerId;
    public readonly ?float $priceMin;
    public readonly ?float $priceMax;
    public readonly ?string $location;
    public readonly ?string $city;
    public readonly DateTimeInterface $updatedAt;
    public readonly string $message;
}
```

## 6. Zarządzanie stanem
- **Stan formularza**: Lokalny stan HTML form z wartościami domyślnymi
- **Stan walidacji**: Walidacja po stronie serwera z zwracaniem błędów
- **Stan uprawnień**: Sprawdzanie ROLE_CALL_CENTER/ROLE_ADMIN przez Symfony Security
- **Stan powiadomień**: Toast notifications przez JavaScript po HTMX response

## 7. Integracja API
- **Endpoint**: `PUT /leads/{id}/update-preferences`
- **Request**: Form data z polami preferencji
- **Response**: HTML form (dla HTMX) lub JSON (dla API)
- **Walidacja**: Walidacja po stronie serwera z zwracaniem błędów
- **Autoryzacja**: Sprawdzanie uprawnień przez `#[IsGranted('ROLE_CALL_CENTER')]`

## 8. Interakcje użytkownika
1. **Otwarcie formularza**: Użytkownik widzi formularz preferencji w sliderze szczegółów leada
2. **Edycja pól**: Użytkownik wypełnia pola formularza (czas kontaktu, sposób kontaktu, notatki)
3. **Wysłanie formularza**: Kliknięcie "Zapisz preferencje" wywołuje HTMX PUT request
4. **Walidacja**: Serwer waliduje dane i uprawnienia
5. **Zapis**: Preferencje są zapisywane w bazie danych
6. **Logowanie**: Event jest logowany w tabeli events
7. **Odpowiedź**: Formularz jest aktualizowany przez HTMX
8. **Powiadomienie**: Toast notification informuje o sukcesie/błędzie

## 9. Warunki i walidacja

### Walidacja uprawnień
- Tylko ROLE_CALL_CENTER i ROLE_ADMIN mogą edytować preferencje
- BOK ma dostęp tylko do odczytu

### Walidacja danych
- **Cena min/max**: Opcjonalne, jeśli podane to min < max
- **Lokalizacja**: Maksymalnie 255 znaków
- **Miasto**: Maksymalnie 100 znaków
- **Notatki**: Maksymalnie 1000 znaków
- **Czas kontaktu**: Tylko dozwolone wartości (morning, afternoon, evening)
- **Sposób kontaktu**: Tylko dozwolone wartości (phone, email, sms)

### Walidacja biznesowa
- Klient musi istnieć w bazie danych
- Preferencje są powiązane z klientem przez customer_id
- Wszystkie zmiany są logowane z kontekstem użytkownika

## 10. Obsługa błędów

### Błędy walidacji
- **400 Bad Request**: Nieprawidłowe dane formularza
- **403 Forbidden**: Brak uprawnień do edycji
- **404 Not Found**: Lead lub klient nie istnieje

### Błędy serwera
- **500 Internal Server Error**: Błąd bazy danych lub systemu
- **Rollback transakcji**: W przypadku błędów bazy danych

### Obsługa w UI
- **Toast notifications**: Informowanie o błędach
- **Walidacja inline**: Wyświetlanie błędów w formularzu
- **Graceful degradation**: Formularz pozostaje funkcjonalny przy błędach

## 11. Kroki implementacji

### Faza 1: Backend API (3 kroki)
1. **Rozszerzenie CustomerServiceInterface** o metodę `updateCustomerPreferences()`
2. **Implementacja w CustomerService** z transakcjami i walidacją
3. **Rozszerzenie EventService** o metodę `logCustomerPreferencesChanged()`

### Faza 2: Endpoint UI (2 kroki)
4. **Implementacja endpointu** w LeadDetailsController::updatePreferences()
5. **Utworzenie UpdatePreferencesResponse DTO** dla odpowiedzi

### Faza 3: Frontend Integration (2 kroki)
6. **Aktualizacja formularza** w _details_slider.html.twig
7. **Dodanie JavaScript** do obsługi toast notifications

### Faza 4: Testy i dokumentacja (2 kroki)
8. **Testy jednostkowe** dla CustomerService i EventService
9. **Testy funkcjonalne** dla endpointu i UI

### Faza 5: Optymalizacja (1 krok)
10. **Dokumentacja API** i aktualizacja README

## 12. Szczegóły techniczne

### Baza danych
- Tabela `customer_preferences` z polami: price_min, price_max, location, city
- Powiązanie z `customers` przez customer_id
- Indeksy na customer_id dla wydajności

### Bezpieczeństwo
- Autoryzacja przez Symfony Security
- Walidacja CSRF token
- Logowanie wszystkich operacji z kontekstem użytkownika

### Wydajność
- Transakcje bazodanowe dla spójności
- Optymalizacja zapytań z JOIN
- Caching preferencji jeśli potrzebne

### Monitoring
- Logowanie wszystkich zmian preferencji
- Metryki użycia funkcjonalności
- Alerty przy błędach systemowych
