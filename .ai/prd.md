
# Dokument wymagań produktu (PRD) - Lead Management System

## 1. Przegląd produktu

Lead Management System (LMS) to centralny system zarządzania leadami nieruchomościowymi, który rozwiązuje problem rozproszenia danych o potencjalnych klientach pochodzących z różnych aplikacji. System zapewnia deduplikację danych klientów, centralizację informacji o preferencjach oraz automatyczne przekazywanie leadów do systemów CDP (Customer Data Platform).

Głównym celem biznesowym jest zwiększenie dostarczalności leadów o 70% poprzez centralizację danych, eliminację duplikatów i usprawnienie procesów obsługi klientów w call center.

## 2. Problem użytkownika

Jeden z największych w Polsce dostawców ofert nieruchomościowych posiada wiele aplikacji zarówno webowych jak i mobilnych, z których każda generuje leady (zainteresowania do ofert). Obecnie jednak brak jest jednego, centralnego źródła ich przechowywania.

Główne problemy:
- Problemy z prawidłowym naliczaniem leadów dla agencji i deweloperów
- Utrudniona praca call center z ograniczonymi możliwościami szybkiego kontaktu z potencjalnymi klientami
- Brak możliwości poznania preferencji klientów i przedstawienia dopasowanych ofert
- Duplikacja danych klientów w różnych systemach
- Brak centralizacji informacji o leadach

Obecny proces wymaga od call center logowania się do wielu systemów i wyszukiwania potencjalnych klientów w każdym z nich osobno, co znacznie spowalnia proces obsługi i zmniejsza efektywność.

## 3. Wymagania funkcjonalne

### 3.1 Przyjmowanie leadów z aplikacji
- System przyjmuje leady z aplikacji wewnętrznych (Morizon, Gratka) w formacie: {partner_id, property_id, development_id, client_phone, client_email, lead_id}
- System przyjmuje leady z aplikacji zewnętrznej (Homsters) w formacie: {hms_project_id, hms_property_id, hms_partner_id, phone, email, lead_id}
- Mapowanie danych: hms_project_id = development_id
- Walidacja: lead musi zawierać zarówno telefon jak i email
- Automatyczne zapisywanie leadów w bazie danych

### 3.2 Panel LMS z autoryzacją
- Dostęp chroniony nazwą użytkownika i hasłem
- Różne role użytkowników: Call Center (pełny dostęp) i BOK (read-only)
- Autoryzacja API przez tokeny
- Logowanie wszystkich operacji dla audytu

### 3.3 Przeglądanie leadów
- Wyświetlanie listy wszystkich leadów w czytelnym formacie
- Możliwość filtrowania i sortowania leadów
- Wyświetlanie podstawowych danych klienta
- Informacja o statusie wysłania do systemów CDP

### 3.4 Edycja preferencji klienta
- Możliwość edycji preferencji klientów przez call center
- Struktura preferencji: cena min/max, lokalizacja, miasto, telefon, email
- Preferencje zapisywane w osobnej tabeli powiązanej z tabelą klientów
- Logowanie wszystkich zmian preferencji

### 3.5 Usuwanie leadów
- Możliwość usunięcia leada z systemu
- Potwierdzenie operacji usunięcia
- Logowanie operacji usunięcia

### 3.6 Deduplikacja danych klientów
- Email i telefon muszą być unikatowe w bazie danych
- LEAD_ID (UUID) zapisywany w tabeli eventów dla identyfikacji duplikatów
- Automatyczne wykrywanie i łączenie duplikatów klientów

### 3.7 Wysyłka do systemów CDP
- Automatyczne przekazywanie leadów do systemów CDP (SalesManago, Murapol, DomDevelopment)
- Proces: najpierw zapisanie leada w LMS, potem próba wysłania do CDP
- Mechanizm retry z exponential backoff w przypadku błędów
- Możliwość ręcznego ponownego wysłania przez administratorów

### 3.8 System eventów
- Logowanie wszystkich operacji na leadach
- Informacje o problemach z importem i dostarczeniem
- Monitoring nieudanych dostarczeń
- Czas retencji eventów: 1 rok

## 4. Granice produktu

### 4.1 Poza zakresem MVP
- Naliczanie leadów do faktury
- Zaawansowane statystyki leadów
- Backup i disaster recovery
- Automatyczna integracja z nowymi aplikacjami

### 4.2 Ograniczenia techniczne MVP
- Brak automatycznego skalowania w chmurze
- Podstawowy monitoring bez zaawansowanych alertów
- Brak integracji z systemami CRM poza CDP

## 5. Historyjki użytkowników

### US-001: Przyjmowanie leada z aplikacji źródłowej
Jako aplikacja źródłowa chcę wysłać lead do systemu LMS, aby został zapisany i przekazany do call center.

Kryteria akceptacji:
- API przyjmuje lead w standardowym formacie JSON
- System waliduje obecność telefonu i emaila
- Lead zostaje zapisany w bazie danych
- LEAD_ID jest zapisany w tabeli eventów
- System próbuje wysłać lead do systemów CDP

### US-002: Logowanie do panelu LMS
Jako pracownik call center chcę się zalogować do panelu LMS, aby mieć dostęp do listy leadów i danych klientów.

Kryteria akceptacji:
- Formularz logowania z nazwą użytkownika i hasłem
- Po zalogowaniu dostęp do panelu głównego
- Logowanie próby logowania (IP, czas, rezultat)
- Różne uprawnienia dla ról (call center vs BOK)

### US-003: Przeglądanie listy leadów
Jako pracownik call center chcę zobaczyć listę wszystkich leadów, aby móc wybrać klienta do kontaktu.

Kryteria akceptacji:
- Lista leadów wyświetlana w czytelnym formacie
- Możliwość filtrowania i sortowania
- Wyświetlanie podstawowych danych klienta
- Informacja o statusie wysłania do CDP

### US-004: Edycja preferencji klienta
Jako pracownik call center chcę edytować preferencje klienta po rozmowie telefonicznej, aby zaktualizować jego potrzeby.

Kryteria akceptacji:
- Formularz edycji preferencji (cena min/max, lokalizacja, miasto)
- Walidacja wprowadzonych danych
- Zapisanie zmian w osobnej tabeli preferencji
- Logowanie wszystkich modyfikacji

### US-005: Usuwanie leada
Jako pracownik call center chcę usunąć lead z systemu, gdy nie jest już potrzebny.

Kryteria akceptacji:
- Opcja usunięcia przy każdym leadzie
- Potwierdzenie operacji usunięcia
- Lead zostaje trwale usunięty z bazy danych
- Logowanie operacji usunięcia

### US-006: Monitoring nieudanych dostarczeń
Jako administrator chcę zobaczyć listę leadów, które nie zostały wysłane do systemów CDP, aby móc je ponownie wysłać.

Kryteria akceptacji:
- Zakładka "Nie wysłane leady" w panelu
- Lista leadów z błędami dostarczenia
- Możliwość ręcznego ponownego wysłania
- Informacje o przyczynie błędu

### US-007: Deduplikacja klientów
Jako system chcę automatycznie wykrywać duplikaty klientów na podstawie emaila i telefonu, aby uniknąć wielokrotnego kontaktu z tym samym klientem.

Kryteria akceptacji:
- Sprawdzanie unikalności emaila i telefonu w bazie
- Łączenie leadów tego samego klienta
- Wyświetlanie historii wszystkich leadów klienta
- Możliwość ręcznego rozłączenia profili w przypadku błędu

### US-008: Bezpieczny dostęp i autoryzacja
Jako zalogowany użytkownik chcę mieć pewność, że mam dostęp tylko do danych odpowiednich dla mojej roli.

Kryteria akceptacji:
- Call center ma pełny dostęp do listy leadów, danych klientów i edycji preferencji
- BOK ma tylko dostęp do wyświetlania danych (read-only)
- Wszystkie operacje są logowane z informacją o użytkowniku
- Brak dostępu do danych innych użytkowników

### US-009: Obsługa błędów integracji
Jako system chcę obsługiwać błędy podczas integracji z aplikacjami źródłowymi i systemami CDP.

Kryteria akceptacji:
- W przypadku błędu podczas przyjmowania leada - wpis w tabeli eventów
- W przypadku błędu podczas wysyłania do CDP - mechanizm retry z exponential backoff
- Alerty dla administratorów o nieudanych operacjach
- Możliwość ręcznego ponownego wysłania

### US-010: Zarządzanie eventami
Jako administrator chcę przeglądać historię eventów, aby monitorować działanie systemu i rozwiązywać problemy.

Kryteria akceptacji:
- Lista wszystkich eventów z możliwością filtrowania
- Wyświetlanie szczegółów każdego eventu
- Możliwość wyszukiwania eventów po LEAD_ID
- Automatyczne usuwanie eventów starszych niż 1 rok

## 6. Metryki sukcesu

### 6.1 Główny cel biznesowy
- 70% zwiększenia dostarczalności leadów (w porównaniu do stanu przed wdrożeniem)

### 6.2 Metryki operacyjne
- Czas odpowiedzi API: maksymalnie 3 sekundy
- Dostępność systemu: 99.9%
- Skuteczność deduplikacji: 95% wykrywania duplikatów
- Skuteczność dostarczenia do CDP: 98% leadów wysłanych pomyślnie

### 6.3 Metryki jakości
- Czas obsługi leada przez call center: skrócenie o 50%
- Liczba duplikatów: redukcja o 90%
- Satisfaction score call center: >4.0/5.0

### 6.4 Metryki techniczne
- Wolumen: obsługa ~1000 leadów dziennie
- Próg skalowania: przygotowanie na 10,000 leadów dziennie
- Rate limiting: 1000 requestów na minutę