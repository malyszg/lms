# Plan Testów - Lead Management System (LMS)

## 1. Wprowadzenie i cele testowania

### 1.1 Cel dokumentu

Niniejszy dokument określa kompleksowy plan testowania systemu Lead Management System (LMS) - centralnego systemu zarządzania leadami nieruchomościowymi. Plan testów ma na celu zapewnienie wysokiej jakości, bezpieczeństwa oraz niezawodności systemu przed wdrożeniem produkcyjnym.

### 1.2 Cele testowania

**Cele biznesowe:**
- Weryfikacja osiągnięcia głównego celu: **70% zwiększenia dostarczalności leadów**
- Potwierdzenie skuteczności deduplikacji klientów: **95% wykrywania duplikatów**
- Zapewnienie skuteczności dostarczenia do CDP: **98% leadów wysłanych pomyślnie**
- Potwierdzenie skrócenia czasu obsługi leada przez call center o **50%**

**Cele techniczne:**
- Weryfikacja poprawności działania wszystkich 10 User Stories z PRD
- Zapewnienie czasu odpowiedzi API: **maksymalnie 3 sekundy**
- Potwierdzenie dostępności systemu: **99.9%**
- Weryfikacja obsługi **~1000 leadów dziennie** z możliwością skalowania do **10,000**
- Potwierdzenie rate limiting: **1000 requestów na minutę**

**Cele jakościowe:**
- Zapewnienie bezpieczeństwa danych i autentykacji (US-008)
- Weryfikacja poprawności logowania wszystkich operacji (audit trail)
- Potwierdzenie integralności danych w bazie MySQL
- Weryfikacja odporności na błędy i mechanizmów retry

### 1.3 Zakres systemu

System składa się z:
- **Backend API** (Symfony 7.3 + PHP 8.3) - przyjmowanie i zarządzanie leadami
- **Panel webowy** (HTMX + Fluent Design) - interfejs dla call center i BOK
- **Baza danych** (MySQL 9.4) - przechowywanie leadów, klientów, eventów
- **Kolejka wiadomości** (RabbitMQ) - asynchroniczna komunikacja
- **Integracja AI** (Google Gemini) - scoring leadów
- **Integracje CDP** (SalesManago, Murapol, DomDevelopment) - dostarczanie leadów

---

## 2. Zakres testów

### 2.1 Funkcjonalności w zakresie testów

#### 2.1.1 Funkcjonalności o wysokim priorytecie (MUST TEST)

| ID | Funkcjonalność | User Story | Uzasadnienie |
|---|---|---|---|
| F-01 | Przyjmowanie leadów przez API | US-001 | Główna funkcjonalność systemu |
| F-02 | Deduplikacja klientów | US-007 | Kluczowy cel biznesowy (90% redukcja) |
| F-03 | Walidacja danych wejściowych | US-001 | Ochrona przed złymi danymi |
| F-04 | Autentykacja użytkowników | US-002 | Bezpieczeństwo dostępu |
| F-05 | Autoryzacja i role | US-008 | Ochrona danych i compliance |
| F-06 | Logowanie eventów | US-008, US-010 | Audit trail, wymagania prawne |
| F-07 | Transformacja danych Homsters | US-001 | Mapowanie hms_* → standardowe pola |
| F-08 | Transakcje bazodanowe | - | Integralność danych |
| F-09 | Obsługa błędów API | US-009 | Resilience systemu |
| F-10 | Wysyłka do systemów CDP | US-001 | Dostarczalność leadów (KPI) |

#### 2.1.2 Funkcjonalności o średnim priorytecie

| ID | Funkcjonalność | User Story | Uzasadnienie |
|---|---|---|---|
| F-11 | Przeglądanie listy leadów | US-003 | UX dla call center |
| F-12 | Filtrowanie i sortowanie | US-003 | Efektywność pracy |
| F-13 | Edycja preferencji klienta | US-004 | Funkcjonalność call center |
| F-14 | Usuwanie leadów | US-005 | Zarządzanie danymi |
| F-15 | Monitoring nieudanych dostarczeń | US-006 | Obsługa błędów CDP |
| F-16 | Ręczne ponowne wysyłanie do CDP | US-006 | Recovery mechanism |
| F-17 | AI Scoring leadów | - | Wartość dodana (nie MVP critical) |
| F-18 | Wyświetlanie szczegółów leada | US-003 | UX enhancement |
| F-19 | Zmiana hasła użytkownika | US-002 | Bezpieczeństwo |

#### 2.1.3 Funkcjonalności poza zakresem testów MVP

- Naliczanie leadów do faktury (poza zakresem MVP)
- Automatyczna integracja z nowymi aplikacjami (poza zakresem)
- Zaawansowane statystyki leadów (poza zakresem)
- Backup i disaster recovery (poza zakresem)
- Automatyczne skalowanie w chmurze (poza zakresem)
- Autoryzacja API przez tokeny (TODO - poza MVP autentykacji)

### 2.2 Obszary ryzyka wymagające szczególnej uwagi

#### 2.2.1 Wysokie ryzyko

| Obszar | Ryzyko | Plan mitigacji testowej |
|---|---|---|
| Deduplikacja | Race conditions przy równoczesnych requestach | Testy współbieżności, testy transakcji DB |
| Performance | Degradacja przy 10,000 leadów/dzień | Testy obciążeniowe, testy wydajności |
| CDP Delivery | Brak mechanizmu retry (mock w MVP) | Testy jednostkowe mock, plan testów dla produkcji |
| Bezpieczeństwo API | Brak autoryzacji tokenami (TODO) | Testy penetracyjne, security audit |
| Transakcje DB | Rollback może zostawić inconsistent state | Testy transakcji, testy integralności |

#### 2.2.2 Średnie ryzyko

| Obszar | Ryzyko | Plan mitigacji testowej |
|---|---|---|
| AI API limits | Rate limiting Gemini API | Testy z mock'ami, testy fallback'ów |
| Memory leaks | Batch processing dużej liczby leadów | Testy pamięci, profiling |
| Session security | "Remember me" może mieć luki | Testy bezpieczeństwa sesji |
| Migracje DB | Doctrine migrations bez testów | Testy migracji w gore/w dół |

---

## 3. Typy testów do przeprowadzenia

### 3.1 Testy jednostkowe (Unit Tests)

**Cel:** Weryfikacja poprawności działania pojedynczych klas i metod w izolacji.

**Narzędzie:** PHPUnit 10.5

**Pokrycie:** Minimum 80% code coverage dla warstwy biznesowej (Services, Validators, Transformers)

**Komponenty do przetestowania:**

#### 3.1.1 Serwisy biznesowe (src/Leads/)

| Klasa | Metody do przetestowania | Scenariusze |
|---|---|---|
| `ValidationService` | - `isValidUuid()`<br>- `isValidEmail()`<br>- `isValidPhone()`<br>- `validateCreateLeadRequest()` | - Poprawne/niepoprawne UUID v4<br>- Różne formaty email<br>- Różne formaty telefonu (+48, 0048, 48)<br>- Brakujące wymagane pola |
| `LeadRequestTransformer` | - `transformRequestData()`<br>- `mapHomstersFields()` | - Transformacja Homsters → standard<br>- Transformacja Morizon/Gratka (bez zmian)<br>- Brakujące pola opcjonalne |
| `CustomerService` | - `findOrCreateCustomer()`<br>- `findByEmailAndPhone()`<br>- Deduplikacja logic | - Nowy klient (create)<br>- Istniejący klient (find)<br>- Race condition scenarios<br>- Case sensitivity email |
| `LeadService` | - `createLead()`<br>- `leadExists()`<br>- `findByUuid()` | - Pełny przepływ tworzenia<br>- Duplikacja UUID<br>- Rollback przy błędzie<br>- CDP delivery failure handling |
| `LeadPropertyService` | - `createProperty()`<br>- `shouldCreateProperty()` | - Tworzenie z pełnymi danymi<br>- Puste property data<br>- Częściowe dane |
| `EventService` | - `logLeadCreated()`<br>- `logApiRequest()`<br>- `logCdpDeliverySuccess/Failed()` | - Poprawne logowanie<br>- Serializacja szczegółów<br>- Handling null values |
| `GeminiLeadScoringService` | - `score()`<br>- `scoreBatch()`<br>- Fallback mechanism | - Udane scorowanie<br>- API failure → fallback<br>- Rate limiting<br>- Batch processing |

#### 3.1.2 DTO (Data Transfer Objects)

| Klasa | Scenariusze |
|---|---|
| `CreateLeadRequest` | - Poprawne dane<br>- Walidacja constraints<br>- Serializacja/deserializacja |
| `FiltersDto` | - Parsowanie query params<br>- Default values<br>- Walidacja zakresów dat |
| `LeadScoreResult` | - Wszystkie kategorie (hot/warm/cold)<br>- Walidacja score 0-100 |

### 3.2 Testy integracyjne (Integration Tests)

**Cel:** Weryfikacja współpracy między komponentami systemu.

**Scope:** Interakcje Service ↔ Repository ↔ Database, Service ↔ External API

**Komponenty do przetestowania:**

#### 3.2.1 Integracja z bazą danych

| Obszar | Scenariusze testowe |
|---|---|
| **Doctrine ORM** | - CRUD operations dla wszystkich encji<br>- Relacje (OneToOne, ManyToOne)<br>- Cascade delete<br>- Lazy loading |
| **Transakcje** | - Commit przy sukcesie<br>- Rollback przy błędzie<br>- Nested transactions<br>- Deadlock handling |
| **Unique constraints** | - email + phone w customers<br>- lead_uuid w leads<br>- Handling duplicate key errors |
| **Indeksy** | - Query performance z indeksami<br>- Covering indexes<br>- Fulltext search |

#### 3.2.2 Integracja z Google Gemini AI

| Scenariusz | Opis | Oczekiwany wynik |
|---|---|---|
| Udane scorowanie | Lead z pełnymi danymi → Gemini API | Score 0-100, category, reasoning, suggestions |
| API failure | Gemini zwraca 500/timeout | Fallback score calculation, log error |
| Rate limiting | Przekroczenie quota | Delay + retry lub fallback |
| Invalid response | Gemini zwraca niepoprawny JSON | Parse error handling, fallback |

#### 3.2.3 Integracja z CDP Systems (Mock w MVP)

| Scenariusz | Opis | Oczekiwany wynik |
|---|---|---|
| Udane wysłanie | Lead → mock CDP delivery | Event log "CDP delivery success" |
| CDP failure | Mock rzuca exception | Event log "CDP delivery failed", lead creation succeeds |
| Retry mechanism | TODO: Test exponential backoff | (Dla produkcji) |

### 3.3 Testy funkcjonalne (Functional Tests)

**Cel:** Weryfikacja działania API endpoints i kontrolerów z perspektywy użytkownika końcowego.

**Narzędzie:** Symfony WebTestCase + PHPUnit

**Scope:** Pełne żądania HTTP → kontrolery → serwisy → baza danych → response

#### 3.3.1 API Endpoints - LeadController

| Endpoint | Method | Scenariusze testowe |
|---|---|---|
| `/api/leads` | POST | **Pozytywne:**<br>- Utworzenie leada z pełnymi danymi (201)<br>- Utworzenie bez property data (201)<br>- Deduplikacja klienta (201, ten sam customer_id)<br>- Transformacja Homsters fields (201)<br><br>**Negatywne:**<br>- Duplikacja UUID (409 Conflict)<br>- Brak wymaganych pól (400)<br>- Invalid UUID format (400)<br>- Invalid email/phone (400)<br>- Invalid JSON (400)<br>- Wrong Content-Type (400) |
| `/api/leads` | GET | **Pozytywne:**<br>- Lista z domyślną paginacją (200)<br>- Filtrowanie po status (200)<br>- Filtrowanie po application_name (200)<br>- Sortowanie (created_at asc/desc) (200)<br>- Paginacja (page, limit) (200)<br><br>**Negatywne:**<br>- Invalid filter values (400)<br>- Page out of range (200, empty results) |
| `/api/leads/{uuid}` | GET | **Pozytywne:**<br>- Szczegóły istniejącego leada (200)<br>- Include customer + property (200)<br><br>**Negatywne:**<br>- Nieistniejący UUID (404)<br>- Invalid UUID format (400) |

#### 3.3.2 View Controllers - Panel webowy

| Controller | Route | Scenariusze testowe |
|---|---|---|
| `LeadsViewController` | `/leads` | - Wyświetlenie listy (wymaga auth)<br>- Redirect do login gdy nie zalogowany<br>- Filtrowanie przez formularz<br>- HTMX partial updates |
| `LeadDetailsController` | `/leads/{id}` | - Wyświetlenie szczegółów<br>- Edycja preferencji (tylko CALL_CENTER)<br>- BOK ma read-only<br>- Update preferences (POST) |
| `AuthController` | `/login` | - Formularz logowania (GET)<br>- Login success → redirect /leads (POST)<br>- Login failure → error message (POST)<br>- Already logged in → redirect /leads |
| `ProfileController` | `/profile/change-password` | - Formularz zmiany hasła (GET, wymaga auth)<br>- Zmiana hasła success (POST)<br>- Invalid current password (POST)<br>- New password validation (POST) |

#### 3.3.3 Autentykacja i autoryzacja

| Scenariusz | Opis | Oczekiwany wynik |
|---|---|---|
| Login success | Email + hasło poprawne | Redirect /leads, session created, event logged |
| Login failure | Złe hasło | Error message, event logged (failed attempt) |
| Logout | Zalogowany user → /logout | Redirect /login, session destroyed, event logged |
| Remember me | Checkbox zaznaczony | Cookie ważny 1 tydzień |
| Role CALL_CENTER | Dostęp do edycji | 200 OK dla /leads/{id}/edit |
| Role BOK | Próba edycji | 403 Forbidden dla /leads/{id}/edit |
| Role ADMIN | Dostęp do /config | 200 OK |
| Unauthenticated | Próba /leads bez login | Redirect /login |

### 3.4 Testy wydajnościowe (Performance Tests)

**Cel:** Weryfikacja spełnienia wymagań wydajnościowych z PRD.

**Narzędzia:** Apache JMeter, k6.io

**Metryki:**

| Metryka | Wymaganie | Warunek sukcesu |
|---|---|---|
| Czas odpowiedzi API | ≤ 3 sekundy | 95 percentyl ≤ 3s |
| Throughput | 1000 leadów/dzień | ~12 req/min sustained |
| Throughput docelowy | 10,000 leadów/dzień | ~120 req/min peak |
| Rate limiting | 1000 req/min | Brak errors przy 1000 req/min |
| Concurrent users | 50 równoczesnych | Response time stable |

**Scenariusze testowe:**

#### 3.4.1 Load Testing

| Test | Opis | Parametry | Sukces jeśli |
|---|---|---|---|
| Baseline | Normalne obciążenie | 12 req/min przez 1h | Avg response ≤ 1s |
| Peak load | Szczyt dzienny | 120 req/min przez 15min | 95th percentile ≤ 3s |
| Stress test | Przekroczenie limitu | 2000 req/min przez 5min | System nie crashuje, rate limiting działa |
| Endurance | Długotrwałe obciążenie | 50 req/min przez 8h | Brak memory leaks, stable performance |

### 3.5 Testy bezpieczeństwa (Security Tests)

**Cel:** Weryfikacja odporności systemu na ataki i zgodności z wymaganiami bezpieczeństwa.

**Narzędzia:** OWASP ZAP, Burp Suite, Manual testing

#### 3.5.1 Autentykacja i autoryzacja

| Obszar | Scenariusze testowe |
|---|---|
| **Password hashing** | - Hasła hashowane bcrypt cost=12<br>- Niemożność odzyskania plaintext<br>- Salt unique per user |
| **Session management** | - Session timeout<br>- Session fixation prevention<br>- Secure cookie flags (HttpOnly, Secure, SameSite) |
| **CSRF protection** | - Tokeny w formularzach<br>- Walidacja tokenów<br>- Token regeneration |
| **Access control** | - BOK nie może edytować<br>- CALL_CENTER może edytować<br>- ADMIN ma pełny dostęp<br>- Horizontal privilege escalation prevention |
| **Brute force protection** | - TODO: Rate limiting login attempts<br>- Account lockout after X failures |

#### 3.5.2 API Security

| Obszar | Scenariusze testowe |
|---|---|
| **Input validation** | - SQL injection attempts<br>- XSS payloads<br>- XXE attacks<br>- Command injection |
| **Mass assignment** | - DTO validation prevents extra fields<br>- Cannot set ID/internal fields |
| **Rate limiting** | - TODO: 1000 req/min enforced<br>- 429 Too Many Requests response |
| **Authorization** | - TODO: API token validation (poza MVP)<br>- Public access controlled |

### 3.6 Testy akceptacyjne użytkownika (UAT)

**Cel:** Weryfikacja spełnienia wymagań biznesowych przez użytkowników końcowych.

**Uczestnicy:** 
- 2-3 pracowników call center
- 1-2 pracowników BOK
- 1 administrator systemu

**Scenariusze UAT:**

| ID | User Story | Scenariusz testowy | Akceptacja |
|---|---|---|---|
| UAT-01 | US-001 | Przyjęcie leada z Morizon | Lead widoczny w panelu ≤ 5 sekund |
| UAT-02 | US-001 | Przyjęcie leada z Homsters | Pola hms_* poprawnie zmapowane |
| UAT-03 | US-002 | Logowanie do panelu | Czas logowania ≤ 2 sekundy |
| UAT-04 | US-003 | Przeglądanie listy 100 leadów | Ładowanie ≤ 3 sekundy, czytelna lista |
| UAT-05 | US-003 | Filtrowanie po statusie | Wyniki zgodne z filtrem |
| UAT-06 | US-004 | Edycja preferencji klienta | Zmiany zapisane i widoczne natychmiast |
| UAT-07 | US-005 | Usunięcie leada | Confirmation prompt, lead usunięty |
| UAT-08 | US-006 | Lista nieudanych dostarczeń | Widoczne błędy CDP, możliwość retry |
| UAT-09 | US-007 | Deduplikacja | Ten sam email+telefon → 1 klient |
| UAT-10 | US-008 | BOK read-only | Brak przycisków edycji/usuwania |

---

## 4. Scenariusze testowe dla kluczowych funkcjonalności

### 4.1 US-001: Przyjmowanie leada z aplikacji źródłowej

#### Scenariusz 1: Utworzenie nowego leada z aplikacji Morizon - Happy Path

**Prekondycje:**
- API endpoint `/api/leads` dostępny
- Baza danych MySQL online
- CDP systems (mock) dostępne

**Kroki:**
1. Wyślij POST request `/api/leads` z danymi:
   ```json
   {
     "lead_uuid": "123e4567-e89b-12d3-a456-426614174000",
     "application_name": "morizon",
     "customer": {
       "email": "jan.kowalski@example.com",
       "phone": "+48123456789",
       "first_name": "Jan",
       "last_name": "Kowalski"
     },
     "property": {
       "property_id": "PROP-001",
       "development_id": "DEV-001",
       "partner_id": "PART-001",
       "price": 500000.00,
       "city": "Warszawa"
     }
   }
   ```

**Oczekiwany rezultat:**
- Status: `201 Created`
- Header `Location: /api/leads/123e4567-e89b-12d3-a456-426614174000`
- Response body zawiera: `id`, `leadUuid`, `status` = "new", `customerId`, `applicationName`, `cdpDeliveryStatus`, `createdAt`

**Weryfikacja w bazie:**
- Lead utworzony w tabeli `leads` z UUID
- Customer utworzony w tabeli `customers`
- Property utworzone w tabeli `lead_properties`
- Event "lead_created" w tabeli `events`
- Events "cdp_delivery_success" dla każdego CDP system

**Czas wykonania:** ≤ 2 sekundy

---

#### Scenariusz 2: Deduplikacja klienta - istniejący email+telefon

**Prekondycje:**
- W bazie istnieje customer: email="anna.nowak@example.com", phone="+48987654321"

**Kroki:**
1. Wyślij POST `/api/leads` z tym samym email i telefon

**Oczekiwany rezultat:**
- Status: `201 Created`
- `customerId` w response = ID istniejącego customera (nie utworzono nowego)

**Weryfikacja w bazie:**
- Tylko 1 customer z tym email+phone (deduplikacja zadziałała)
- Nowy lead powiązany z istniejącym customerem
- Dane customera NIE zostały nadpisane

---

#### Scenariusz 3: Transformacja pól Homsters

**Kroki:**
1. Wyślij POST `/api/leads` z polami Homsters (hms_project_id, hms_property_id, hms_partner_id)

**Oczekiwany rezultat:**
- Status: `201 Created`
- W bazie property ma zmapowane: `development_id` = "HMS-PROJ-001" (z hms_project_id)

---

#### Scenariusz 4: Duplikacja UUID - błąd 409

**Prekondycje:**
- W bazie istnieje lead z UUID "423e4567-..."

**Kroki:**
1. Wyślij POST `/api/leads` z tym samym UUID

**Oczekiwany rezultat:**
- Status: `409 Conflict`
- Response: `{"error": "Lead already exists", "message": "Lead with UUID ... already exists (ID: 123)"}`
- Event "lead_already_exists" zalogowany

---

#### Scenariusz 5: Walidacja - brak wymaganych pól

**Kroki:**
1. Wyślij POST `/api/leads` bez pola `email`

**Oczekiwany rezultat:**
- Status: `400 Bad Request`
- Response: `{"error": "Validation failed", "errors": {"customer.email": "Email is required"}}`

---

### 4.2 US-007: Deduplikacja klientów - testy race condition

#### Scenariusz 6: Równoczesne requesty z tym samym email+phone

**Cel:** Weryfikacja, że mechanizm deduplikacji działa przy współbieżności.

**Kroki:**
1. Symultanicznie wyślij 10 requestów POST `/api/leads` z tym samym email/phone, różne UUID

**Oczekiwany rezultat:**
- Wszystkie 10 requestów: Status `201 Created`
- W bazie: **Tylko 1 customer** z tym email+phone
- **10 leadów** wszystkie powiązane z tym samym `customer_id`

**Weryfikacja:** Brak deadlocków, brak duplikatów

---

### 4.3 US-002 & US-008: Autentykacja i autoryzacja

#### Scenariusz 7: Login success - Call Center user

**Kroki:**
1. GET `/login` → formularz
2. POST `/login` z email, password, CSRF token

**Oczekiwany rezultat:**
- Redirect `302` → `/leads`
- Session cookie set
- Event "login_success" w `events`

---

#### Scenariusz 8: Autoryzacja - BOK próbuje edytować

**Kroki:**
1. Zalogowany user z rolą `ROLE_BOK`
2. POST `/leads/123/preferences`

**Oczekiwany rezultat:**
- Status: `403 Forbidden`
- Event "access_denied"

---

#### Scenariusz 9: Autoryzacja - CALL_CENTER może edytować

**Kroki:**
1. Zalogowany user z rolą `ROLE_CALL_CENTER`
2. POST `/leads/123/preferences` z danymi

**Oczekiwany rezultat:**
- Status: `200 OK`
- Preferencje zapisane
- Event "preferences_updated"

---

### 4.4 US-006: Monitoring nieudanych dostarczeń

#### Scenariusz 10: CDP delivery failure handling

**Kroki:**
1. POST `/api/leads` → Mock CDP rzuca exception dla "Murapol"

**Oczekiwany rezultat:**
- Lead creation: `201 Created` (sukces mimo błędu CDP)
- Event "cdp_delivery_failed" dla Murapol
- Record w `failed_deliveries`

---

#### Scenariusz 11: Ręczne ponowne wysłanie do CDP

**Kroki:**
1. Admin: GET `/failed-deliveries` → lista
2. POST `/failed-deliveries/456/retry`

**Oczekiwany rezultat:**
- Próba ponownego wysłania
- Event "cdp_delivery_retry_success" lub "retry_failed"

---

## 5. Środowisko testowe

### 5.1 Architektura środowiska testowego

```
┌─────────────────────────────────────────┐
│         Test Environment                │
│                                         │
│  ┌──────────────────────────────────┐  │
│  │   Docker Compose Stack           │  │
│  │                                  │  │
│  │  - App (PHP 8.3, Symfony 7.3)   │  │
│  │  - Nginx (Alpine)                │  │
│  │  - MySQL 9.4 (Test DB)           │  │
│  │  - RabbitMQ (Mock)               │  │
│  │  - Redis (TODO)                  │  │
│  └──────────────────────────────────┘  │
│                                         │
│  ┌──────────────────────────────────┐  │
│  │   External Services (Mock)       │  │
│  │  - Google Gemini API (Sandbox)   │  │
│  │  - CDP Systems (Mock)            │  │
│  └──────────────────────────────────┘  │
│                                         │
│  ┌──────────────────────────────────┐  │
│  │   Testing Tools                  │  │
│  │  - PHPUnit 10.5                  │  │
│  │  - Symfony WebTestCase           │  │
│  │  - Apache JMeter / k6.io         │  │
│  │  - OWASP ZAP                     │  │
│  └──────────────────────────────────┘  │
└─────────────────────────────────────────┘
```

### 5.2 Konfiguracja środowiska testowego

#### 5.2.1 Parametry techniczne

| Komponent | Specyfikacja | Konfiguracja testowa |
|---|---|---|
| **App Container** | PHP 8.3-fpm, Symfony 7.3 | APP_ENV=test, Memory: 512M |
| **MySQL** | MySQL 9.4 | Database: lms_db_test, Isolated schemas |
| **RabbitMQ** | rabbitmq:3-management | Mock dla MVP, Vhost: /test |
| **Redis** | redis:alpine | TODO: Cache layer, Test DB index |
| **Nginx** | nginx:alpine | Port 8082, Test subdomain |

#### 5.2.2 Zmienne środowiskowe (phpunit.xml.dist)

```xml
<php>
    <server name="APP_ENV" value="test"/>
    <server name="APP_SECRET" value="test-secret-key"/>
    <server name="DATABASE_URL" value="mysql://${TEST_MYSQL_USER:-test_user}:${TEST_MYSQL_PASSWORD:-test_password}@mysql:3306/lms_db_test"/>
    <env name="GEMINI_API_KEY" value="test-api-key-or-sandbox"/>
    <env name="GEMINI_MODEL" value="gemini-2.0-flash"/>
</php>
```

### 5.3 Zarządzanie danymi testowymi

#### 5.3.1 Strategia fixtures

**Fixtures dla testów funkcjonalnych:**
- Minimalne zestawy danych (nie pełne dumps)
- Użycie Doctrine DataFixtures
- Fixtures per test case (izolacja)

**Przykładowe fixtures:**
- **UserFixture:** Admin, Call Center, BOK users
- **CustomerFixture:** 5 customers z różnymi email/phone
- **LeadFixture:** 20 leadów w różnych statusach

#### 5.3.2 Czyszczenie danych między testami

**Strategia:**
- **Testy jednostkowe:** Mock'i, bez DB
- **Testy integracyjne:** Transakcje z rollback
- **Testy funkcjonalne:** Database reset między test cases

### 5.4 CI/CD Integration - GitHub Actions

**Pipeline stages:**

```yaml
name: Test Suite

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:9.4
        env:
          MYSQL_DATABASE: lms_db_test
        ports:
          - 3306:3306
    
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP 8.3
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - name: Install dependencies
        run: composer install
      - name: Run migrations
        run: php bin/console doctrine:migrations:migrate
      - name: Run Unit Tests
        run: vendor/bin/phpunit --testsuite=Unit
      - name: Run Functional Tests
        run: vendor/bin/phpunit --testsuite=Functional
      - name: Coverage report
        run: vendor/bin/phpunit --coverage-html coverage
```

---

## 6. Narzędzia do testowania

### 6.1 Narzędzia testowe - zestawienie

| Kategoria | Narzędzie | Wersja | Cel użycia |
|---|---|---|---|
| **Unit & Integration** | PHPUnit | 10.5 | Główne narzędzie testowe PHP |
| | Symfony PHPUnit Bridge | 7.3 | Integracja z Symfony |
| | Doctrine DataFixtures | Latest | Fixtures dla testów z DB |
| **Functional/E2E** | Symfony WebTestCase | 7.3 | Testy kontrolerów i API |
| | BrowserKit | 7.3 | Symulacja przeglądarki |
| | Panther (opcja) | Latest | Headless Chrome dla HTMX |
| **Performance** | Apache JMeter | 5.6+ | Load testing, stress testing |
| | k6.io | Latest | Modern load testing |
| | Blackfire.io | - | PHP profiling, performance |
| **Security** | OWASP ZAP | Latest | Security scanning |
| | Burp Suite | Community | Manual penetration testing |
| **Code Quality** | PHPStan | Level 8 | Static analysis |
| | PHP-CS-Fixer | Latest | Code style |
| **Coverage** | Xdebug | 3.x | Code coverage |
| | Codecov | - | Coverage reporting |
| **CI/CD** | GitHub Actions | - | Automated pipeline |
| | Docker Compose | Latest | Environment isolation |

### 6.2 Konfiguracja narzędzi kluczowych

#### PHPUnit - phpunit.xml.dist

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true">
    <php>
        <server name="APP_ENV" value="test"/>
        <server name="DATABASE_URL" value="mysql://${TEST_MYSQL_USER:-test_user}:${TEST_MYSQL_PASSWORD:-test_password}@mysql:3306/lms_db_test"/>
    </php>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Functional">
            <directory>tests/Functional</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </source>
</phpunit>
```

---

## 7. Harmonogram testów

### 7.1 Fazy testowania

| Sprint/Week | Faza testowania | Typy testów | Odpowiedzialny |
|---|---|---|---|
| Week 1-2 | Unit Testing (Continuous) | Services, Validators, Transformers | QA Engineer + Developers |
| Week 2-3 | Integration Testing | DB interactions, Service cooperation, Gemini AI | QA Engineer |
| Week 3-4 | Functional Testing | API endpoints, View controllers, Auth flows | QA Engineer + QA Team |
| Week 4 | Performance Testing | Load tests, Stress tests, DB performance | Performance Engineer |
| Week 5 | Security Testing | Penetration tests, Vulnerability scan, Auth/authz | Security Specialist |
| Week 5-6 | UAT (User Acceptance) | User scenarios, Real data, E2E workflows | Business Users + QA Lead |
| Week 6 | Regression Testing (Pre-release) | Full test suite, Smoke tests | QA Team (Automated) |
| Post-release | Monitoring & Validation | Production metrics, Error tracking | DevOps + QA Lead |

### 7.2 Kamienie milowe testowania

| Milestone | Data (przykład) | Kryteria | Deliverable |
|---|---|---|---|
| **M1: Unit Tests Complete** | Week 2 | - 80% code coverage<br>- 0 failing tests<br>- All services tested | Unit test report |
| **M2: Integration Tests Complete** | Week 3 | - DB interactions verified<br>- External API mocked<br>- Transaction tests passed | Integration test report |
| **M3: API Tests Complete** | Week 4 | - All 10 US validated<br>- API documentation updated<br>- Postman collection | Functional test report |
| **M4: Performance Baseline** | Week 4 | - Response time < 3s (95th)<br>- Throughput >= 12 req/min<br>- No memory leaks | Performance report |
| **M5: Security Clearance** | Week 5 | - No HIGH vulnerabilities<br>- Auth/authz tests passed<br>- OWASP Top 10 verified | Security audit report |
| **M6: UAT Sign-off** | Week 6 | - All UAT scenarios passed<br>- Business acceptance<br>- Training completed | UAT acceptance document |
| **M7: Go-Live Approval** | Week 6 | - Regression tests passed<br>- Deployment checklist OK<br>- Rollback plan ready | Release notes |

### 7.3 Aktywności cykliczne

**Codziennie (CI):**
- Uruchomienie testów jednostkowych przy każdym commit
- Static analysis (PHPStan) w pre-commit hook
- Code style check (PHP-CS-Fixer)

**Cotygodniowo:**
- Pełna suita testów funkcjonalnych (piątki)
- Code review z perspektywy testability
- Aktualizacja test coverage metrics
- Triage nowych bugów

**Co 2 tygodnie:**
- Performance testing (baseline)
- Security scan (OWASP ZAP automated)
- Test plan review i update

---

## 8. Kryteria akceptacji testów

### 8.1 Kryteria wyjścia (Exit Criteria) - Gotowość do produkcji

System jest gotowy do wdrożenia produkcyjnego, gdy spełnione są WSZYSTKIE poniższe kryteria:

#### 8.1.1 Pokrycie testowe (Test Coverage)

| Metryka | Minimalny próg | Docelowy | Status |
|---|---|---|---|
| **Unit test coverage** | 75% | 85% | ☐ |
| **Critical paths coverage** | 100% | 100% | ☐ |
| **API endpoints covered** | 100% | 100% | ☐ |
| **User Stories validated** | 10/10 (100%) | 10/10 | ☐ |

**Critical paths:**
- Lead creation flow (US-001)
- Customer deduplication (US-007)
- Authentication/Authorization (US-002, US-008)
- CDP delivery (US-001)
- Event logging (US-010)

#### 8.1.2 Jakość testów (Test Quality)

| Kryterium | Wymaganie | Status |
|---|---|---|
| Failing tests | 0 | ☐ |
| Flaky tests | ≤ 2% | ☐ |
| Test execution time | ≤ 5 min (Unit + Functional) | ☐ |
| Code review | 100% testów zreviewowanych | ☐ |

#### 8.1.3 Defekty (Defect Metrics)

| Severity | Maksymalna liczba otwartych defektów |
|---|---|
| **Critical** (P1) | 0 |
| **High** (P2) | 0 |
| **Medium** (P3) | ≤ 3 (z planem naprawy) |
| **Low** (P4) | ≤ 10 (backlog) |

**Definicje severity:**
- **P1 (Critical):** System nie działa, brak workaround, blokuje produkcję
- **P2 (High):** Major funkcjonalność nie działa, istnieje trudny workaround
- **P3 (Medium):** Minor funkcjonalność nie działa, łatwy workaround
- **P4 (Low):** Kosmetyczne, dokumentacja, nice-to-have

#### 8.1.4 Wymagania funkcjonalne

| User Story | Kryteria akceptacji | Status |
|---|---|---|
| US-001 | API przyjmuje leady z 3 źródeł, walidacja działa, lead w DB, event logged | ☐ |
| US-002 | Logowanie działa, session management OK, event logged | ☐ |
| US-003 | Lista leadów widoczna, filtrowanie działa, paginacja OK | ☐ |
| US-004 | Edycja preferencji (CALL_CENTER), walidacja, event logged | ☐ |
| US-005 | Usuwanie leadów, confirmation prompt, event logged | ☐ |
| US-006 | Lista failed deliveries, retry mechanism, admin access only | ☐ |
| US-007 | Deduplikacja email+phone, 95% accuracy, race condition safe | ☐ |
| US-008 | Role-based access, BOK read-only, CALL_CENTER full, ADMIN all | ☐ |
| US-009 | Error handling API, error handling CDP, graceful degradation | ☐ |
| US-010 | Event logging, event viewing, retention 1 year | ☐ |

#### 8.1.5 Wymagania niefunkcjonalne (NFR)

| Metryka | Wymaganie | Warunek sukcesu | Status |
|---|---|---|---|
| **Response time (API)** | 95th percentile ≤ 3s | Load test passed | ☐ |
| **Throughput** | ≥ 1000 leads/day | 12 req/min sustained | ☐ |
| **Scalability** | Handle 10,000 leads/day | Stress test passed | ☐ |
| **Availability** | 99.9% uptime | (Production metric) | - |
| **Deduplikacja accuracy** | ≥ 95% | Functional tests + UAT | ☐ |
| **CDP delivery success** | ≥ 98% | (Production metric) | - |
| **Security** | No HIGH vulnerabilities | OWASP scan clean | ☐ |
| **Browser compatibility** | Chrome, Firefox, Safari, Edge | Manual testing passed | ☐ |

#### 8.1.6 Dokumentacja i procedury

| Dokument | Wymaganie | Status |
|---|---|---|
| Test Summary Report | Kompletny, zaakceptowany | ☐ |
| Known Issues List | Udokumentowane, priorytetyzowane | ☐ |
| Deployment Guide | Aktualna, zweryfikowana | ☐ |
| Rollback Plan | Przygotowany, przetestowany | ☐ |
| User Manual/Training | Dla call center i BOK | ☐ |
| API Documentation | Swagger/OpenAPI aktualne | ☐ |
| Runbook (operations) | Procedures dla DevOps | ☐ |

### 8.2 Kryteria wejścia (Entry Criteria)

**Dla rozpoczęcia każdej fazy testowania:**

#### Unit Testing:
- ✓ Kod zmergowany do develop branch
- ✓ Build passes (composer install, no errors)
- ✓ PHPStan level 8 passes

#### Integration Testing:
- ✓ Unit tests pass (≥75% coverage)
- ✓ Test database prepared
- ✓ Docker environment up

#### Functional Testing:
- ✓ Integration tests pass
- ✓ API documentation complete
- ✓ Test data fixtures prepared

#### Performance Testing:
- ✓ Functional tests pass
- ✓ Production-like environment ready
- ✓ Monitoring tools configured

#### UAT:
- ✓ All technical tests passed
- ✓ UAT environment deployed
- ✓ Test users created
- ✓ Training materials ready

---

## 9. Role i odpowiedzialności w procesie testowania

### 9.1 Zespół testowy - struktura

```
           ┌─────────────────┐
           │   QA Lead       │
           │   (1 osoba)     │
           └────────┬────────┘
                    │
     ┌──────────────┼──────────────┐
     │              │              │
┌────▼───────┐ ┌───▼──────┐ ┌────▼────────┐
│ QA         │ │Performance│ │  Security   │
│ Engineers  │ │ Engineer  │ │  Specialist │
│ (2-3)      │ │ (1)       │ │  (0.5 FTE)  │
└────────────┘ └───────────┘ └─────────────┘
```

### 9.2 Role i odpowiedzialności szczegółowe

#### 9.2.1 QA Lead

**Odpowiedzialności:**
- ✅ Tworzenie i utrzymanie planu testów
- ✅ Nadzór nad wykonaniem wszystkich faz testowania
- ✅ Koordynacja zespołu testowego
- ✅ Raportowanie statusu testów do Project Manager
- ✅ Decyzje go/no-go dla release
- ✅ Zarządzanie ryzykiem testowym
- ✅ Review krytycznych test cases
- ✅ Koordynacja UAT z użytkownikami biznesowymi
- ✅ Mentoring QA Engineers

**Deliverables:**
- Plan testów (ten dokument)
- Test Summary Report
- Release Readiness Report
- Metrics dashboard

---

#### 9.2.2 QA Engineer #1 (Backend Focus)

**Odpowiedzialności:**
- ✅ Pisanie testów jednostkowych dla Services
- ✅ Pisanie testów integracyjnych (DB interactions)
- ✅ Testowanie API endpoints (LeadController)
- ✅ Testowanie deduplikacji (US-007 - critical)
- ✅ Testowanie transformacji danych (Homsters mapping)
- ✅ Mockowanie zależności (CDP, Gemini AI)
- ✅ Code coverage monitoring (target 80%+)

**Obszary odpowiedzialności:**
- src/Leads/ (wszystkie serwisy)
- src/Controller/LeadController.php
- src/DTO/ (walidacja)
- tests/Unit/Leads/
- tests/Functional/Controller/LeadControllerTest.php

---

#### 9.2.3 QA Engineer #2 (Frontend & Auth Focus)

**Odpowiedzialności:**
- ✅ Testowanie autentykacji i autoryzacji (US-002, US-008)
- ✅ Testowanie kontrolerów widoków
- ✅ Testowanie ról (CALL_CENTER, BOK, ADMIN)
- ✅ Testowanie panelu webowego (HTMX interactions)
- ✅ Testowanie formularzy
- ✅ Browser compatibility testing
- ✅ Security testing - auth flows
- ✅ UAT coordination z call center users

**Obszary odpowiedzialności:**
- src/Controller/AuthController.php, ProfileController.php
- src/Controller/*ViewController.php
- templates/
- Security aspects (auth/authz)

---

#### 9.2.4 Performance Engineer

**Odpowiedzialności:**
- ✅ Projektowanie testów wydajnościowych
- ✅ Wykonanie load testing (Apache JMeter / k6)
- ✅ Wykonanie stress testing
- ✅ Database performance tuning recommendations
- ✅ Query optimization analysis
- ✅ Memory leak detection
- ✅ Bottleneck identification
- ✅ Performance baseline establishment

**Tools:** Apache JMeter, k6, Blackfire.io, MySQL EXPLAIN, Symfony Profiler

---

#### 9.2.5 Security Specialist (0.5 FTE)

**Odpowiedzialności:**
- ✅ Security requirements review
- ✅ Threat modeling (STRIDE)
- ✅ OWASP Top 10 vulnerability testing
- ✅ Penetration testing (manual)
- ✅ Automated security scanning (OWASP ZAP)
- ✅ Authentication/authorization audit
- ✅ Compliance check (GDPR, data protection)

**Tools:** OWASP ZAP, Burp Suite, Symfony Security Checker

---

#### 9.2.6 Business Users (UAT)

**Role:** Call Center Workers, BOK Staff, Admin

**Odpowiedzialności:**
- ✅ Wykonanie scenariuszy UAT
- ✅ Weryfikacja zgodności z procesami biznesowymi
- ✅ Zgłaszanie defektów z perspektywy użytkownika
- ✅ Akceptacja systemu przed go-live
- ✅ Feedback na temat UX/UI

---

#### 9.2.7 Developers (wsparcie testowania)

**Odpowiedzialności:**
- ✅ Unit tests dla nowego kodu (TDD approach)
- ✅ Bug fixes dla defektów znalezionych przez QA
- ✅ Code review z perspektywy testability
- ✅ Wsparcie QA w setupie środowiska testowego
- ✅ Mockowanie zależności zewnętrznych

---

### 9.3 Macierz RACI - kluczowe aktywności

| Aktywność | QA Lead | QA Eng | Perf Eng | Sec Spec | Devs | PM |
|---|---|---|---|---|---|---|
| **Test Plan Creation** | R/A | C | C | C | I | A |
| **Unit Test Writing** | A | R | - | - | R/C | I |
| **Integration Test Writing** | A | R | I | - | C | I |
| **Functional Test Execution** | A | R | - | I | C | I |
| **Performance Testing** | C | I | R/A | - | C | I |
| **Security Testing** | C | C | - | R/A | I | I |
| **UAT Coordination** | R | C | - | - | I | A |
| **Defect Triage** | R/A | C | C | C | C | I |
| **Test Reporting** | R/A | C | C | C | I | I |
| **Go/No-Go Decision** | R | C | C | C | I | A |

**Legenda:** R=Responsible, A=Accountable, C=Consulted, I=Informed

---

## 10. Procedury raportowania błędów

### 10.1 Narzędzie do zarządzania defektami

**Wybrane narzędzie:** GitHub Issues

**Konfiguracja:**
- Labels dla severity (P1-Critical, P2-High, P3-Medium, P4-Low)
- Labels dla typu (bug, enhancement, test-failure)
- Labels dla komponentu (API, UI, Auth, DB, Performance)
- Milestone dla release

### 10.2 Szablon zgłoszenia defektu

```markdown
## Tytuł defektu
[Komponent] Krótki opis (np. "[API] Lead creation fails with invalid UUID")

## Severity/Priority
- [ ] P1 - Critical
- [ ] P2 - High
- [x] P3 - Medium
- [ ] P4 - Low

## Typ defektu
- [x] Functional bug
- [ ] Performance issue
- [ ] Security vulnerability
- [ ] UI/UX issue

## Środowisko
- **Branch/Version:** develop / v1.0.0
- **Environment:** Test / Staging / Production
- **OS:** Ubuntu 22.04
- **Browser:** Chrome 120 (jeśli UI)

## Opis problemu
Jasny opis, co nie działa

## Kroki do reprodukcji
1. Krok 1
2. Krok 2
3. Zaobserwuj błąd

## Oczekiwany rezultat
Co powinno się wydarzyć

## Aktualny (błędny) rezultat
Co się faktycznie dzieje

## Logi/Screenshot
```
Error logs lub screenshot
```

## Wpływ biznesowy
Jak wpływa na biznes/użytkowników

## Test case ID
TC-API-003 (jeśli dotyczy)

## Zgłoszone przez
- **Name:** Jan Kowalski
- **Role:** QA Engineer
- **Date:** 2025-10-16
```

### 10.3 Workflow defektu - stany

```
[NEW] → (Triage) → [OPEN] → [IN PROGRESS] → [RESOLVED] → (QA testing) → [CLOSED]
                       ↓                            ↓
                  [INVALID]                    [REOPENED]
                  [DUPLICATE]                       ↓
                                              (back to IN PROGRESS)
```

### 10.4 Severity guidelines (SLA)

| Severity | Opis | Response Time | Resolution Target |
|---|---|---|---|
| **P1 - Critical** | System nie działa, brak workaround | **< 1h** | **24h** |
| **P2 - High** | Major funkcjonalność nie działa | **4h** | **3 dni** |
| **P3 - Medium** | Minor funkcjonalność, łatwy workaround | **1 dzień** | **1 tydzień** |
| **P4 - Low** | Kosmetyczne, enhancements | **1 tydzień** | **Backlog** |

### 10.5 Triage process

**Cotygodniowe spotkanie**

**Uczestnicy:** QA Lead, Tech Lead, Product Owner

**Agenda:**
1. Review nowych defektów (NEW)
2. Przypisanie severity i priority
3. Przypisanie do developera
4. Identyfikacja blockerów
5. Planning hotfixes dla P1/P2

### 10.6 Metryki defektów - raportowanie

**Cotygodniowy raport (QA Lead → PM):**

| Metryka | Wartość | Trend | Komentarz |
|---|---|---|---|
| Total open defects | 15 | ↓ | Good progress |
| P1 (Critical) | 0 | → | Target met |
| P2 (High) | 1 | ↓ | 1 pending fix |
| P3 (Medium) | 8 | ↑ | New from UAT |
| P4 (Low) | 6 | → | Backlog |
| Avg resolution time (P1-P2) | 2.5 days | ↓ | Improving |
| Reopen rate | 5% | → | Acceptable |

### 10.7 Eskalacja defektów

**Kiedy eskalować:**
- P1 defekt nie rozwiązany w 24h
- P2 defekt nie rozwiązany w 3 dni
- Defekt reopened więcej niż 2 razy
- Blocker dla go-live

**Ścieżka eskalacji:**
1. **Level 1:** QA Engineer → Tech Lead
2. **Level 2:** QA Lead → Engineering Manager
3. **Level 3:** QA Lead + Eng Manager → CTO/VP Engineering

---

## 11. Kryteria sukcesu projektu testowego

### 11.1 Wskaźniki sukcesu (KPIs)

| KPI | Target | Measurement | Status |
|---|---|---|---|
| **Test coverage (critical paths)** | 100% | Code coverage tools | ☐ |
| **Test coverage (overall)** | ≥ 80% | PHPUnit coverage report | ☐ |
| **Defect detection rate** | ≥ 90% defects found pre-UAT | Defect tracking | ☐ |
| **Test execution efficiency** | ≥ 95% tests executed on time | Test management | ☐ |
| **Automation rate** | ≥ 70% regression tests automated | Test suite analysis | ☐ |
| **Defect escape rate** | ≤ 5% defects in production (1st month) | Production monitoring | ☐ |
| **UAT acceptance** | 100% UAT scenarios passed | UAT sign-off | ☐ |
| **Performance targets** | All NFR metrics met | Performance report | ☐ |
| **Security compliance** | 0 HIGH vulnerabilities | Security scan | ☐ |

### 11.2 Definicja "Done" dla testowania

Testowanie jest uznane za zakończone, gdy:

1. ✅ **Wszystkie planned testy wykonane:**
   - Unit tests: ≥ 80% coverage
   - Integration tests: wszystkie krytyczne przepływy
   - Functional tests: wszystkie API endpoints + UI journeys
   - Performance tests: load + stress passed
   - Security tests: OWASP Top 10 validated
   - UAT: wszystkie biznesowe scenariusze approved

2. ✅ **Kryteria exit spełnione:**
   - 0 P1 defects
   - 0 P2 defects
   - ≤ 3 P3 defects (z akceptacją PO)

3. ✅ **Dokumentacja complete:**
   - Test Summary Report
   - Known Issues List
   - Release Notes
   - User Manual

4. ✅ **Sign-offs otrzymane:**
   - QA Lead: Test completion
   - Product Owner: UAT acceptance
   - Engineering Manager: Technical readiness
   - Security Specialist: Security clearance

5. ✅ **Production readiness:**
   - Deployment plan reviewed
   - Rollback plan tested
   - Monitoring configured
   - Support team trained

---

## 12. Ryzyka testowe i plan mitigacji

### 12.1 Zidentyfikowane ryzyka testowe

| ID | Ryzyko | Prawdopodobieństwo | Wpływ | Score | Plan mitigacji |
|---|---|---|---|---|---|
| R-01 | Deduplikacja race conditions nie wykryte | Średnie | Wysokie | 6 | Dedykowane testy współbieżności, load testing z concurrent writes |
| R-02 | Performance degradation nie wykryta w test env | Średnie | Wysokie | 6 | Production-like environment, realistic volumes, endurance tests |
| R-03 | AI API (Gemini) niedostępny podczas testów | Wysokie | Średnie | 6 | Comprehensive mocking, sandbox environment, fallback testing |
| R-04 | Niewystarczająca dostępność Business Users dla UAT | Średnie | Średnie | 4 | Early UAT planning, flexible scheduling, recorded demos |
| R-05 | Flaky tests spowalniają CI/CD | Średnie | Średnie | 4 | Strict policy (≤2%), root cause analysis, test isolation |
| R-06 | Database migrations breaking data | Niskie | Wysokie | 3 | Migration testing (up/down), backup/restore, rollback plan |
| R-07 | Security vulnerabilities discovered late | Niskie | Wysokie | 3 | Early security review, continuous OWASP scanning |
| R-08 | Test environment instability (Docker) | Średnie | Niskie | 2 | Health checks, automated rebuild, redundant environments |
| R-09 | Insufficient test coverage edge cases | Średnie | Niskie | 2 | Exploratory testing, boundary value analysis |
| R-10 | HTMX interactions not properly tested | Niskie | Niskie | 1 | Manual testing, consider Panther, DevTools validation |

**Legenda:** Prawdopodobieństwo/Wpływ: Niskie=1, Średnie=2, Wysokie=3, Score = P × I

### 12.2 Monitoring ryzyk

**Proces:**
1. Weekly risk review meeting (QA Lead + Tech Lead)
2. Update risk register
3. New risks identification
4. Mitigation effectiveness assessment
5. Escalate unmitigated high risks

---

## 13. Załączniki

### 13.1 Linki do dokumentacji

- **PRD (Product Requirements Document):** `.ai/prd.md`
- **Database Schema:** `.ai/db-plan.md`
- **API Specification:** `.ai/api-plan.md`
- **Authentication Spec:** `.ai/auth-spec.md`
- **Tech Stack:** `.ai/tech-stack.md`
- **PHPUnit Configuration:** `phpunit.xml.dist`
- **Docker Compose:** `docker/docker-compose.yml`

### 13.2 Narzędzia i dostępy

| Narzędzie | URL | Dostęp |
|---|---|---|
| GitHub Repository | https://github.com/company/lms | Team members |
| Test Environment | http://lms-test.company.local:8082 | QA Team |
| phpMyAdmin (Test DB) | http://localhost:8081 | QA Team |
| RabbitMQ Management | http://localhost:15672 | QA + DevOps |
| CI/CD Pipeline | GitHub Actions | Automatic |

### 13.3 Słownik terminów

| Termin | Definicja |
|---|---|
| **Lead** | Zainteresowanie klienta ofertą nieruchomości, główny obiekt systemu |
| **Deduplikacja** | Proces wykrywania i łączenia duplikatów klientów (email+phone) |
| **CDP** | Customer Data Platform - systemy docelowe (SalesManago, Murapol, DomDevelopment) |
| **MVP** | Minimum Viable Product - minimalna wersja produktu |
| **UAT** | User Acceptance Testing - testy akceptacyjne użytkownika |
| **SLA** | Service Level Agreement - umowa o poziomie usług |
| **RACI** | Responsible, Accountable, Consulted, Informed - macierz odpowiedzialności |
| **NFR** | Non-Functional Requirements - wymagania niefunkcjonalne |
| **E2E** | End-to-End - testy całego przepływu od początku do końca |
| **Mock** | Sztuczna implementacja komponentu do celów testowych |
| **Fixture** | Przygotowane dane testowe |
| **Flaky test** | Test, który przechodzi/pada losowo bez zmian w kodzie |
| **Race condition** | Sytuacja, gdy wynik zależy od kolejności wykonania operacji współbieżnych |

### 13.4 Historia zmian dokumentu

| Wersja | Data | Autor | Zmiany |
|---|---|---|---|
| 1.0 | 2025-10-16 | QA Lead | Pierwsza wersja planu testów |

---

**Koniec dokumentu**

**Przygotował:** QA Team  
**Data:** 2025-10-16  
**Status:** Draft / In Review / Approved  
**Wersja:** 1.0

