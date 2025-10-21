# UI Architecture Planning Summary - Lead Management System MVP

## Decisions

### 1. Uproszczony formularz logowania
Formularz logowania będzie zawierał tylko:
- Pole "Nazwa użytkownika" (text input)
- Pole "Hasło" (password input z ikoną show/hide)
- Przycisk "Zaloguj się" (primary action)
- Komunikaty błędów nad formularzem

**Usunięto**: Checkbox "Zapamiętaj mnie" oraz link "Nie pamiętasz hasła?" - administrator resetuje hasła manualnie.

### 2. Akceptacja wszystkich pozostałych rekomendacji
Wszystkie pozostałe 84 pytania zostały zaakceptowane zgodnie z przedstawionymi rekomendacjami.

---

## Matched Recommendations

### Główne widoki i struktura aplikacji

#### 1. Dashboard jako lista leadów
Lista leadów jest głównym widokiem po zalogowaniu z prostym podsumowaniem na górze: liczba leadów dzisiaj, liczba nieudanych dostaw, liczba klientów.

#### 2. Szczegóły leada jako slider
Slider z prawej strony (off-canvas panel) dynamicznie wczytuje szczegóły leada przez HTMX, zachowując kontekst listy.

#### 3. Nawigacja boczna (sidebar)
Menu boczne z ikonami i tekstem dla: Leady, Klienci, Nieudane dostawy, Eventy, Konfiguracja (admin).

#### 4. Oddzielny widok listy klientów
Dedykowana lista klientów z wyszukiwaniem po telefonie/emailu, niezależnie od leadów.

### Interaktywność i HTMX

#### 5. HTMX dla częściowych odświeżeń
Użycie HTMX dla: filtrowanie/sortowanie list, otwieranie szczegółów, edycja preferencji, retry dostaw. Pełne przeładowanie tylko dla logowania/wylogowania i krytycznych błędów.

#### 6. Auto-odświeżanie z polling
Polling co 30 sekund dla nowych leadów z subtelną notyfikacją (badge) i przyciskiem "Załaduj nowe leady".

#### 7. Custom modals przez HTMX
Potwierdzenia akcji (usunięcie leada) realizowane przez HTMX modals z wyraźnymi przyciskami i szczegółami.

### Responsywność i urządzenia

#### 8. Priorytet desktop
Optymalizacja dla desktop (1920x1080 i 1366x768), podstawowe wsparcie dla tabletów (landscape). Minimum 1366x768.

#### 9. Brak dedykowanej wersji mobilnej w MVP
Mobile nie jest wymagane - call center pracuje na stacjonarnych stanowiskach.

### Bezpieczeństwo i autoryzacja

#### 10. Role-based access control (RBAC) w UI
- BOK: wszystko w trybie read-only (disabled buttons, brak formularzy edycji)
- Call Center: pełny dostęp do leadów i edycji preferencji
- Admin: dodatkowo Konfiguracja i Eventy

#### 11. Sesja z timeout 30 minut
Wygaśnięcie po 30 minutach nieaktywności. Ostrzeżenie 2 minuty przed wygaśnięciem z możliwością przedłużenia.

#### 12. Przekierowanie po wygaśnięciu sesji
Przekierowanie na login z komunikatem "Sesja wygasła" i parametrem `?redirect=` do powrotu po zalogowaniu.

#### 13. Rate limiting po 5 nieudanych próbach
Disabled przycisk logowania na 5 minut po 5 nieudanych próbach + timer odliczający + backend blocking.

### Tabele, listy i paginacja

#### 14. Klasyczna paginacja
Przyciski poprzednia/następna + numery stron + opcja wyboru liczby elementów (20/50/100).

#### 15. Brak bulk operations w MVP
Operacje tylko pojedyncze - bulk actions zwiększają złożoność.

#### 16. Wyszukiwanie osobne dla każdej sekcji
Nie globalne - osobne dla leadów i klientów z wyszukiwaniem w konkretnych polach.

#### 17. Ustalone kolumny tabel
Brak konfigurowalności kolumn w MVP - ustalony zestaw najważniejszych kolumn.

### Formularze i walidacja

#### 18. Hybrydowa walidacja
Podstawowa walidacja (format email, telefon) na blur pola. Pełna walidacja biznesowa przy submit.

#### 19. Błędy inline + zbiorczy komunikat
Komunikat zbiorczy na górze formularza + inline przy konkretnych polach (czerwona ramka + ikona + tekst).

#### 20. Manualny zapis
Brak auto-save w MVP. Jasny przycisk "Zapisz" daje użytkownikowi kontrolę.

#### 21. Toasts dla komunikatów
Success toast: 3 sekundy z możliwością zamknięcia. Error toast: nie znika automatycznie, wymaga zamknięcia.

### Monitoring i failed deliveries

#### 22. Lista z najnowszym statusem
Lista pokazuje najnowszy status. Szczegóły pokazują pełną historię prób z timeline.

#### 23. Pojedyncze retry w MVP
Przycisk retry przy każdym rekordzie. Batch retry w przyszłości jeśli będzie potrzeba.

#### 24. Badge notyfikacji
Badge z licznikiem przy menu "Nieudane dostawy" aktualizowany przez polling HTMX co 60 sekund.

### Panel eventów

#### 25. Ograniczony dostęp dla Call Center i BOK
Admin ma pełny dostęp. Call Center/BOK widzą tylko eventy związane z ich akcjami lub konkretnym leadem.

#### 26. Accordion dla szczegółów
Lista kompaktowa z podstawowymi info. Rozwinięcie szczegółów JSON w accordion po kliknięciu.

#### 27. System kolorów i ikon
Sukces (zielony, ✓), Błąd (czerwony, ✗), Info (niebieski, ℹ), Warning (żółty, ⚠).

### Konfiguracja systemu

#### 28. Pogrupowane karty tematyczne
Karty: Integracje CDP, Limity i retry, Bezpieczeństwo. Każda karta to osobny formularz.

#### 29. Potwierdzenie zmian konfiguracji
Modal potwierdzenia z opisem konsekwencji + logowanie zmian w events z poprzednią i nową wartością.

#### 30. Tylko "live" konfiguracja
Brak draft configuration w MVP - zmiany działają natychmiast.

### Design system

#### 31. Bootstrap 5
Szybka implementacja, gotowe komponenty (modals, forms, tables), dobra integracja z Symfony/Twig.

#### 32. Neutralna paleta kolorów
Profesjonalna (szarości, błękit, biel). Akcenty kolorowe tylko dla statusów i akcji.

#### 33. Brak dark mode w MVP
Call center pracuje w oświetlonych biurach. Można dodać później na żądanie użytkowników.

#### 34. Systemowe fonty
(system-ui, -apple-system, BlinkMacSystemFont, Segoe UI) - szybsze ładowanie, natywny wygląd.

### Zarządzanie stanem

#### 35. Filtry w URL query parameters
Umożliwia bookmarking, sharing, browser back/forward, przeładowanie bez utraty filtrów.

#### 36. Cache głównie w backend (Redis)
Frontend cachuje tylko statyczne dane (dropdown options) w sessionStorage.

#### 37. Loading spinners dla operacji >500ms
HTMX klasy `htmx-request`. Overlay spinner dla długich operacji.

#### 38. Paginacja zamiast virtual scrolling
Limit 100 elementów na stronie wystarczy. Virtual scrolling zbyt złożony dla HTMX.

### Obsługa błędów API

#### 39. User-friendly messages z opcją szczegółów
Dla użytkowników: przyjazny komunikat + rozwijalne szczegóły. Dla admina: od razu pełne szczegóły.

#### 40. Rozróżnienie 401 vs 403
401 → przekierowanie na login. 403 → komunikat "Nie masz uprawnień" bez przekierowania.

#### 41. Brak offline functionality
Aplikacja backoffice wymaga połączenia. Komunikat "Brak połączenia" + przycisk "Spróbuj ponownie".

#### 42. Globalny error handler dla 500
Generic error page z komunikatem + request ID + przycisk "Wróć do listy leadów".

### Funkcje użytkowe

#### 43. Eksport do CSV
Prosty eksport aktualnie widocznej listy leadów/klientów do CSV. Przycisk nad listą.

#### 44. Brak keyboard shortcuts w MVP
Call center używa głównie myszy. Można dodać później na żądanie.

#### 45. Tylko dane z API dla nieruchomości
Bez integracji z zewnętrznymi źródłami w MVP. Wystarczą: property_id, price, location, city.

#### 46. Brak notatek/komentarzy w MVP
Nie ma w API Plan ani PRD. Skupić się na core functionality.

### Workflow i UX

#### 47. Dropdown statusu inline w szczegółach
Zmiana statusu w szczegółach leada z automatycznym zapisem + toast potwierdzenia.

#### 48. Brak quick actions w liście
Tylko "Otwórz szczegóły". Inne akcje w widoku szczegółów - minimalizuje ryzyko pomyłki.

#### 49. Automatyczna deduplikacja w backend
UI pokazuje liczbę powiązanych leadów. Nie pokazywać procesu deduplikacji.

#### 50. Edycja preferencji z dwóch miejsc
Ze szczegółów leada (najczęściej) i ze szczegółów klienta. Ten sam komponent w obu miejscach.

### Accessibility i i18n

#### 51. Tylko język polski w MVP
System dla polskiego call center. Internacjonalizacja później jeśli będzie ekspansja.

#### 52. Podstawowe accessibility
Semantyczny HTML, labels dla inputs, keyboard navigation, alt texts. Nie pełny WCAG 2.1 AA.

#### 53. Podstawowa obsługa screen readers
ARIA labels, proper heading hierarchy, skip links. Semantic HTML zapewni minimum.

### Testowanie i feedback

#### 54. Link email zamiast feedback widget
Prosty link "Zgłoś problem" w footer → email do supportu. Widget to dodatkowy system.

#### 55. Prosta strona pomocy
Static HTML z FAQ i opisem funkcji. Link w footer. Szczegółowa dokumentacja w zewnętrznym wiki.

#### 56. Minimalne tooltips
Tylko dla ikon bez tekstu i nie-oczywistych funkcji. Brak guided tour.

### Wydajność

#### 57. Target czasu ładowania
Initial page load: <2s. HTMX updates: <500ms. API: <3s. Lazy loading, minified assets.

#### 58. Minimalne JS - głównie HTMX
Dodatkowo: Bootstrap interactions, date pickers, potwierdzenia. Unikać React/Vue.

#### 59. Cache busting przez versioned assets
style.css?v=1.2.3 przy deployment. Local/session storage czyścić przy logout.

### Autoryzacja - szczegóły

#### 60. Centralny box na neutralnym tle
Logo + formularz + footer. Szerokość 400-450px na desktop.

#### 61. Uniwersalne pole "Login"
Akceptuje username lub email. Backend sprawdza oba.

#### 62. Generyczne błędy logowania
"Nieprawidłowy login lub hasło" bez ujawniania czy user istnieje.

#### 63. Toggle "Pokaż hasło"
Ikona oka przy polu hasła. Domyślnie ukryte.

#### 64. Brak funkcji resetowania hasła w MVP
Administrator resetuje hasła manualnie.

#### 65. Brak CAPTCHA w MVP
Rate limiting + monitoring. CAPTCHA tylko jeśli będą ataki.

#### 66. Przekierowanie po logowaniu
Domyślnie → Dashboard. Z parametrem `?redirect=` → na zapisany URL (walidowany).

#### 67. Wylogowanie z dropdown menu
Link "Wyloguj" w górnym prawym rogu → POST /api/auth/logout → przekierowanie na login.

#### 68. Brak dostępu po wylogowaniu
Cache-Control: no-store, no-cache. Browser back nie pozwala na dostęp do danych.

#### 69. Auto-przekierowanie niezalogowanych
Na login z `?redirect=` - powrót po zalogowaniu.

#### 70. Informacje o użytkowniku w górnym prawym rogu
Avatar/ikona + nazwa + rola + dropdown menu (Mój profil, Wyloguj).

#### 71. Możliwość zmiany hasła
Formularz: Stare hasło, Nowe hasło, Potwierdź. Walidacja: min 8 znaków, 1 cyfra, 1 wielka litera. Po zmianie wylogowanie. API: PUT /api/users/me/password.

#### 72. Brak komunikatu "Zalogowano pomyślnie"
Przekierowanie na Dashboard jest wystarczające.

#### 73. Brak wymuszenia zmiany hasła przy pierwszym logowaniu
Administrator informuje o potrzebie zmiany. Można dodać później.

#### 74. Footer na stronie logowania
Nazwa + wersja + kontakt do supportu + copyright. Minimalne informacje.

#### 75. Responsywny layout logowania
Desktop: 400-450px box. Tablet: padding po bokach. Mobile: full-width (jeśli w ogóle).

#### 76. Loading indicator podczas logowania
Disabled przycisk + spinner + tekst "Logowanie...". Timeout 10 sekund.

---

## UI Architecture Planning Summary

### 1. Główne wymagania dotyczące architektury UI

#### 1.1 Tech Stack
- **Frontend**: PHP Symfony + Twig templates
- **CSS Framework**: Bootstrap 5
- **JavaScript**: HTMX (głównie) + minimalne vanilla JS
- **Fonty**: Systemowe (system-ui, Segoe UI)
- **Kolory**: Neutralna paleta profesjonalna (szarości, błękit, biel)

#### 1.2 Priorytety MVP
- Szybka implementacja funkcjonalności core
- Optymalizacja dla desktop (call center)
- Prostota i czytelność UI
- Spójność z API endpoints
- Bezpieczeństwo i RBAC

#### 1.3 Metryki wydajności
- Initial page load: <2s
- HTMX partial updates: <500ms
- API response: <3s (zgodnie z PRD)
- Dostępność: 99.9%

### 2. Kluczowe widoki, ekrany i przepływy użytkownika

#### 2.1 Widok logowania
**Lokalizacja**: `/login`

**Layout**:
- Centralny box (400-450px) na neutralnym tle
- Logo systemu na górze
- Formularz logowania (uproszczony)
- Footer z informacjami

**Formularz**:
- Input: "Nazwa użytkownika" (akceptuje username lub email)
- Input: "Hasło" (z ikoną show/hide)
- Button: "Zaloguj się"
- Komunikaty błędów nad formularzem (generyczne ze względów bezpieczeństwa)

**Bezpieczeństwo**:
- Rate limiting: 5 prób → block na 5 minut z timerem
- Brak CAPTCHA w MVP
- Backend walidacja + IP logging

**Po zalogowaniu**:
- Przekierowanie na Dashboard (lub URL z parametru `?redirect=`)
- Token JWT (24h) w session storage
- Session timeout: 30 minut nieaktywności

#### 2.2 Dashboard (Lista leadów)
**Lokalizacja**: `/` lub `/leads` (domyślny po zalogowaniu)

**Layout**:
- Sidebar navigation (po lewej)
- Header z informacjami użytkownika (góra, prawo)
- Podsumowanie statystyk (top): Leady dzisiaj | Nieudane dostawy | Klienci
- Sekcja filtrów (zawsze widoczna)
- Tabela leadów z paginacją
- Slider szczegółów leada (prawo, dynamiczny)

**Filtry podstawowe** (zawsze widoczne):
- Status (dropdown)
- Aplikacja źródłowa (dropdown)
- Zakres dat (date range picker)
- Button: "Zastosuj filtry"
- Link: "Więcej filtrów" (rozwijany panel)

**Filtry zaawansowane** (w panelu rozwijalnym):
- Email klienta
- Telefon klienta
- Sortowanie (created_at, status, application_name)
- Kolejność (asc/desc)

**Tabela leadów** - kolumny:
1. ID / Lead UUID (skrócone)
2. Data utworzenia
3. Klient (email + telefon)
4. Aplikacja źródłowa
5. Status (color-coded badge)
6. CDP Status (ikona + tooltip)
7. Akcja: "Zobacz szczegóły" (button/link)

**Paginacja**:
- Klasyczna: poprzednia | 1 2 3 ... | następna
- Dropdown: elementy na stronie (20/50/100)
- Info: "Pokazuję X-Y z Z wyników"

**Auto-odświeżanie**:
- Polling co 30 sekund
- Badge notyfikacji z liczbą nowych leadów
- Button: "Załaduj X nowych leadów"

**State management**:
- Filtry w URL query parameters
- Zachowanie stanu przy back/forward
- Możliwość bookmarkingu/sharingu

#### 2.3 Slider szczegółów leada
**Trigger**: Kliknięcie "Zobacz szczegóły" w tabeli

**Lokalizacja**: Off-canvas panel z prawej (overlay + slider)

**Layout**:
- Header: "Szczegóły leada" + przycisk zamknij (X)
- Nawigacja: poprzedni/następny lead (w kontekście listy)
- Tabs: Informacje | Historia | Nieruchomość

**Tab: Informacje**:
- Sekcja: Dane klienta
  - Imię i nazwisko
  - Email (link mailto:)
  - Telefon (link tel:)
  - Link: "Zobacz wszystkie leady klienta"
- Sekcja: Preferencje klienta (edytowalne)
  - Cena min/max
  - Lokalizacja
  - Miasto
  - Button: "Edytuj preferencje" → tryb edycji inline
- Sekcja: Status leada
  - Dropdown ze statusem (inline edit)
  - Automatyczny zapis przy zmianie + toast
- Sekcja: CDP Delivery
  - Status dostawy do każdego CDP systemu
  - Informacje o błędach (jeśli failed)
  - Button: "Retry" (jeśli failed)

**Tab: Historia**:
- Timeline eventów związanych z leadem
- Każdy event: typ (ikona + kolor), czas, użytkownik, szczegóły (accordion)

**Tab: Nieruchomość**:
- Property ID
- Development ID
- Partner ID
- Typ nieruchomości
- Cena
- Lokalizacja
- Miasto

**Footer slidera**:
- Button: "Usuń lead" (danger, modal potwierdzenia)
- Button: "Zamknij"

**HTMX**:
- Dynamiczne ładowanie zawartości slidera
- Partial updates dla edycji preferencji
- Partial updates dla zmiany statusu

#### 2.4 Lista klientów
**Lokalizacja**: `/customers`

**Layout**:
- Sidebar navigation (po lewej)
- Header z informacjami użytkownika
- Sekcja wyszukiwania
- Tabela klientów z paginacją

**Wyszukiwanie**:
- Input: wyszukiwanie (email, telefon, imię, nazwisko)
- Button: "Szukaj"
- Sortowanie: data utworzenia, email, telefon

**Tabela klientów** - kolumny:
1. ID
2. Imię i nazwisko
3. Email
4. Telefon
5. Data utworzenia
6. Liczba leadów
7. Ostatni lead (data)
8. Akcja: "Zobacz szczegóły"

**Slider szczegółów klienta**:
- Podobny do slidera leada
- Sekcja: Dane podstawowe
- Sekcja: Preferencje (edytowalne)
- Sekcja: Lista wszystkich leadów klienta (kompaktowa)
  - Każdy lead klikalny → otwiera slider szczegółów leada

#### 2.5 Nieudane dostawy
**Lokalizacja**: `/failed-deliveries`

**Layout**:
- Sidebar navigation z badge licznika
- Header z informacjami użytkownika
- Sekcja filtrów
- Tabela nieudanych dostaw z paginacją

**Filtry**:
- Status (pending, retrying, failed, resolved)
- System CDP
- Zakres dat

**Tabela** - kolumny:
1. Lead UUID (link do szczegółów leada)
2. Klient (email + telefon)
3. System CDP
4. Kod błędu
5. Liczba prób / Max prób
6. Następna próba (datetime)
7. Status (color-coded)
8. Akcja: "Retry" (button)

**Auto-odświeżanie**:
- Polling co 60 sekund
- Badge w sidebar navigation aktualizowany

**Slider szczegółów failed delivery**:
- Informacje o leadzie
- Pełna historia prób dostawy (timeline)
- Szczegóły błędów (error message, error code)
- Button: "Retry teraz"

#### 2.6 Panel eventów (Admin)
**Lokalizacja**: `/events` (tylko Admin)

**Layout**:
- Sidebar navigation
- Header z informacjami użytkownika
- Sekcja filtrów zaawansowanych
- Tabela eventów z paginacją

**Filtry**:
- Typ eventu (dropdown)
- Typ encji (lead, customer, delivery, itp.)
- ID encji
- ID użytkownika
- Lead UUID
- Zakres dat

**Tabela** - kolumny:
1. ID
2. Typ eventu (ikona + kolor + tekst)
3. Encja (typ + ID)
4. Użytkownik
5. IP address
6. Data i czas
7. Akcja: "Szczegóły" (accordion inline)

**Accordion szczegółów**:
- Pełny JSON details w czytelnym formacie
- Syntax highlighting (opcjonalnie)

**Limit**: 200 elementów na stronie max (większy niż standardowe listy)

#### 2.7 Konfiguracja systemu (Admin)
**Lokalizacja**: `/system-config` (tylko Admin)

**Layout**:
- Sidebar navigation
- Header z informacjami użytkownika
- Karty tematyczne (tabs lub cards)

**Karty**:
1. **Integracje CDP**
   - Lista systemów CDP
   - Każdy system: nazwa, URL, API key, enabled/disabled
   - Formularze edycji z walidacją
   - Button: "Zapisz zmiany" → modal potwierdzenia

2. **Limity i retry**
   - Max retries dla CDP delivery
   - Exponential backoff parameters
   - Rate limiting settings
   - Timeout values

3. **Bezpieczeństwo**
   - Session timeout (minutes)
   - Max failed login attempts
   - Login block duration (minutes)
   - Token expiration (hours)

4. **System**
   - Environment info (read-only)
   - Event retention (days)
   - Polling intervals
   - Logging levels

**Każda zmiana**:
- Modal potwierdzenia z opisem konsekwencji
- Logowanie w events (previous value + new value)
- Toast sukcesu po zapisie

#### 2.8 Sidebar Navigation

**Struktura**:
```
┌─────────────────────────┐
│ [Logo LMS]              │
├─────────────────────────┤
│ ◉ Leady                 │ (aktywna strona bold)
│ ◎ Klienci               │
│ ◎ Nieudane dostawy  [3] │ (badge z liczbą)
│ ◎ Eventy           ⚠️   │ (tylko Admin, ikona roli)
│ ◎ Konfiguracja     ⚙️   │ (tylko Admin)
├─────────────────────────┤
│ [Pomoc]                 │ (footer link)
│ [Zgłoś problem]         │ (footer link)
└─────────────────────────┘
```

**Funkcjonalność**:
- Aktywna strona highlighted
- Icons + text labels
- Badge notifications dynamiczne (HTMX polling)
- Collapse na mobilnych (hamburger menu)
- Role-based visibility

#### 2.9 Header (Top Navigation)

**Layout**:
```
[Breadcrumbs lub tytuł strony]          [User info ▼]
```

**User info dropdown**:
- Avatar/inicjały
- Nazwa użytkownika
- Rola (mniejszy font)
- Dropdown menu:
  - "Zmień hasło"
  - "Wyloguj"

**Wylogowanie**:
- Kliknięcie → POST /api/auth/logout
- Przekierowanie → `/login?message=logged_out`
- Komunikat: "Zostałeś wylogowany"

**Zmiana hasła**:
- Kliknięcie → modal/strona formularza
- Pola: Stare hasło, Nowe hasło, Potwierdź nowe
- Walidacja: min 8 znaków, 1 cyfra, 1 wielka litera
- Po zmianie → wylogowanie + przekierowanie na login

### 3. Strategia integracji z API i zarządzania stanem

#### 3.1 Integracja z API

**Authentication flow**:
1. POST `/api/auth/login` → otrzymanie JWT token
2. Token zapisywany w session storage
3. Każdy request: header `Authorization: Bearer {token}`
4. Token refresh przy 401 (jeśli możliwe) lub przekierowanie na login
5. POST `/api/auth/logout` → invalidacja tokenu + clear session

**Endpointy mapping**:

| Widok UI | Endpoint API | Metoda | Częstotliwość |
|----------|--------------|--------|---------------|
| Dashboard (lista leadów) | GET /api/leads | Auto (polling 30s) + Manual | Ciągła |
| Szczegóły leada | GET /api/leads/{id} | On demand | Przy otwarciu slidera |
| Zmiana statusu leada | PUT /api/leads/{id} | On action | Przy zmianie dropdown |
| Usunięcie leada | DELETE /api/leads/{id} | On action | Po potwierdzeniu modal |
| Lista klientów | GET /api/customers | Manual | Przy wyszukiwaniu |
| Szczegóły klienta | GET /api/customers/{id} | On demand | Przy otwarciu slidera |
| Edycja preferencji | PUT /api/customers/{id}/preferences | On action | Po kliknięciu "Zapisz" |
| Nieudane dostawy | GET /api/failed-deliveries | Auto (polling 60s) + Manual | Ciągła |
| Retry dostawy | POST /api/failed-deliveries/{id}/retry | On action | Przy kliknięciu "Retry" |
| Eventy | GET /api/events | Manual | Przy filtracji/paginacji |
| Konfiguracja | GET/PUT /api/system-config | Manual | Admin on demand |
| Tworzenie leada | POST /api/leads | External | Aplikacje źródłowe |

**Error handling**:
- **400 Bad Request**: Wyświetlić szczegóły walidacji (inline przy polach formularza)
- **401 Unauthorized**: Przekierowanie na login z `?redirect=`
- **403 Forbidden**: Toast "Nie masz uprawnień do tej operacji"
- **404 Not Found**: Toast "Nie znaleziono zasobu" lub redirect do listy
- **409 Conflict**: Toast z informacją o duplikacie (np. lead już istnieje)
- **422 Unprocessable Entity**: Wyświetlić szczegóły błędów walidacji
- **500 Internal Server Error**: Generic error page + request ID + support contact

**Request/Response flow**:
```
1. User action (click, form submit) → HTMX trigger
2. HTMX → API request (auto-add Authorization header)
3. Loading indicator (spinner)
4. API response → HTMX swap/update DOM
5. Success: Toast notification (opcjonalnie) + update UI
6. Error: Error handler → display error message
```

#### 3.2 Zarządzanie stanem

**State persistence**:
- **URL query parameters**: Filtry, sortowanie, paginacja, search queries
  - Umożliwia bookmarking, sharing, browser navigation
  - HTMX: `hx-push-url="true"` dla operacji filtracji
- **Session storage**: Token JWT, user info, temporary UI preferences
  - Clear przy logout
- **Local storage**: NIE używane w MVP (nie potrzebne długoterminowe persystowanie)

**State synchronization**:
- **Polling**: Regularne pobieranie aktualnych danych (leady co 30s, failed deliveries co 60s)
- **Optimistic updates**: Natychmiastowa aktualizacja UI, rollback przy błędzie
  - Przykład: zmiana statusu leada → update UI → API call → success toast lub rollback + error
- **Eventual consistency**: Akceptowalne opóźnienie do 1 minuty dla non-critical updates

**HTMX state management**:
- `hx-target`: Określa który fragment DOM zaktualizować
- `hx-swap`: Strategia zamiany (innerHTML, outerHTML, beforeend, afterend)
- `hx-trigger`: Event triggers (click, change, every 30s, load)
- `hx-vals`: Dodatkowe parametry do requestu
- `hx-indicator`: Loading indicators

**Examples**:
```html
<!-- Auto-refresh leadów co 30s -->
<div hx-get="/leads" hx-trigger="every 30s" hx-target="#leads-table" hx-swap="outerHTML">
  <!-- Tabela leadów -->
</div>

<!-- Slider szczegółów leada -->
<button hx-get="/leads/123" hx-target="#lead-details-slider" hx-swap="innerHTML">
  Zobacz szczegóły
</button>

<!-- Zmiana statusu inline -->
<select hx-put="/api/leads/123" hx-target="#lead-123" hx-swap="outerHTML">
  <option value="new">Nowy</option>
  <option value="contacted">Skontaktowano</option>
</select>

<!-- Retry failed delivery -->
<button hx-post="/api/failed-deliveries/456/retry" 
        hx-target="#delivery-456" 
        hx-swap="outerHTML"
        hx-confirm="Czy na pewno chcesz ponowić dostawę?">
  Retry
</button>
```

**Cache strategy**:
- **Backend (Redis)**: Cache dla ciężkich queries, lista klientów, statistics
- **Frontend (sessionStorage)**: Cache dla dropdown options (statuses, application names, CDP systems)
- **No cache**: Listy leadów, failed deliveries (zawsze fresh data)

#### 3.3 Data flow diagram

```
┌─────────────────────────────────────────────────────┐
│                  Browser (Client)                   │
│                                                     │
│  ┌──────────────┐  HTMX   ┌─────────────────────┐ │
│  │  UI Layer    │◄───────►│   State Layer       │ │
│  │  (Twig HTML) │         │   (URL params,      │ │
│  │              │         │    sessionStorage)  │ │
│  └──────────────┘         └─────────────────────┘ │
│         │                           │              │
│         │ HTMX requests             │              │
│         ▼                           ▼              │
└─────────┼───────────────────────────┼──────────────┘
          │                           │
          │ HTTP + JWT                │
          ▼                           │
┌─────────────────────────────────────┼──────────────┐
│              Symfony Backend        │              │
│                                     │              │
│  ┌──────────────────┐      ┌───────▼────────────┐ │
│  │   Controllers    │──────►│  Session Manager  │ │
│  │   (Twig render   │      │  (30min timeout)  │ │
│  │    + API proxy)  │      └───────────────────┘ │
│  └────────┬─────────┘                            │
│           │                                       │
│           │ Calls API                             │
│           ▼                                       │
│  ┌──────────────────┐      ┌──────────────────┐  │
│  │  API Controllers │◄────►│  Redis Cache     │  │
│  │  (REST endpoints)│      └──────────────────┘  │
│  └────────┬─────────┘                            │
│           │                                       │
│           │ Service layer                         │
│           ▼                                       │
│  ┌──────────────────┐      ┌──────────────────┐  │
│  │ Business Services│◄────►│  RabbitMQ        │  │
│  │ (Leads, Customer,│      │  (CDP delivery   │  │
│  │  Delivery, etc.) │      │   queue)         │  │
│  └────────┬─────────┘      └──────────────────┘  │
│           │                                       │
│           │ Doctrine ORM                          │
│           ▼                                       │
│  ┌──────────────────┐                            │
│  │  MySQL Database  │                            │
│  │  (leads,         │                            │
│  │   customers,     │                            │
│  │   events, etc.)  │                            │
│  └──────────────────┘                            │
│                                                   │
└───────────────────────────────────────────────────┘
          │
          │ HTTP to external systems
          ▼
┌───────────────────────────────────────────────────┐
│            External CDP Systems                   │
│   (SalesManago, Murapol, DomDevelopment)         │
└───────────────────────────────────────────────────┘
```

### 4. Responsywność, dostępność i bezpieczeństwo

#### 4.1 Responsywność

**Breakpoints** (Bootstrap 5):
- **XL (≥1920px)**: Optymalne wyświetlanie, 3-kolumnowy layout (sidebar + content + slider)
- **LG (1366px - 1919px)**: Główny target, 2-kolumnowy layout (sidebar + content), slider overlay
- **MD (768px - 1365px)**: Tablet landscape, sidebar collapsed do ikon, content full width
- **SM (<768px)**: Nie priorytet w MVP, hamburger menu, single column

**Responsive components**:
- **Tables**: Horizontal scroll na małych ekranach (nie responsive design tables)
- **Forms**: Stack inputs vertically na małych ekranach
- **Sidebar**: Collapse na MD i mniejsze
- **Sliders**: Full screen overlay na małych ekranach zamiast side panel
- **Modals**: Adapt do viewport size

**Testing targets**:
- Primary: 1920x1080 (desktop)
- Secondary: 1366x768 (laptop)
- Tertiary: 1024x768 (tablet landscape)

#### 4.2 Dostępność (Accessibility)

**Poziom implementacji**: Podstawowy (nie pełny WCAG 2.1 AA)

**Semantic HTML**:
- Proper heading hierarchy (h1 → h2 → h3)
- `<nav>` dla navigation
- `<main>` dla głównej zawartości
- `<article>` dla lead/customer cards
- `<button>` dla akcji, `<a>` dla linków

**Forms accessibility**:
- `<label>` dla wszystkich inputs (z atrybutem `for`)
- `placeholder` jako hint, NIE jako replacement dla label
- `aria-describedby` dla error messages
- `aria-invalid="true"` dla invalid inputs
- `required` attribute gdzie wymagane

**Keyboard navigation**:
- Tab order logiczny i intuicyjny
- Focus styles wyraźnie widoczne (outline)
- Enter/Space dla buttons i links
- Escape dla zamykania modals/sliders
- Arrow keys dla dropdowns (native select)

**Screen readers**:
- `aria-label` dla ikon bez tekstu
- `aria-labelledby` dla złożonych komponentów
- `aria-live` dla dynamicznie aktualizowanej zawartości (toasts, notifications)
- `role` attributes gdzie potrzebne (role="alert", role="dialog")
- Skip links na początku strony: "Przejdź do treści głównej"

**Color contrast**:
- Minimum 4.5:1 dla normalnego tekstu
- Minimum 3:1 dla dużego tekstu (≥18px)
- Nie polegać tylko na kolorze dla przekazywania informacji (użyć także ikon/tekstu)

**Icons**:
- Zawsze z text label lub `aria-label`
- Nie używać tylko ikony bez tekstowej alternatywy
- `aria-hidden="true"` dla dekoracyjnych ikon (gdy jest text label)

**Testing**:
- Manual keyboard navigation testing
- Browser dev tools accessibility audit (Lighthouse)
- Nie wymagane: pełny screen reader testing w MVP

#### 4.3 Bezpieczeństwo

**Authentication & Authorization**:
- **JWT token**: 24h expiration, stored in sessionStorage (nie localStorage dla security)
- **Session timeout**: 30 minut nieaktywności → auto logout
- **Role-based access**: UI components renderowane/ukrywane based on user role
- **Backend enforcement**: ZAWSZE sprawdzać uprawnienia w API, nie polegać na frontend

**Input validation**:
- **Frontend**: Podstawowa walidacja (format, length, required) dla UX
- **Backend**: Pełna walidacja + sanitization - NIGDY nie ufać frontend validation
- **XSS prevention**: Twig auto-escaping, sanitize wszystkie user inputs
- **SQL injection prevention**: Doctrine ORM parametryzowane queries

**CSRF protection**:
- Symfony CSRF tokens dla state-changing operations
- Token w hidden input lub header
- Walidacja w backend przed przetworzeniem

**Rate limiting**:
- **Login**: 5 prób → block 5 minut (frontend + backend)
- **API**: 1000 requests/minute per user (zgodnie z API Plan)
- **Polling**: Ograniczone do sensownych interwałów (30s, 60s)

**Security headers** (backend Symfony config):
```
Content-Security-Policy: default-src 'self'
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Cache-Control: no-store, no-cache (dla chronionych stron)
```

**Password policies**:
- Minimum 8 znaków
- Przynajmniej 1 cyfra
- Przynajmniej 1 wielka litera
- Hashing: bcrypt lub Argon2 (Symfony PasswordHasher)
- Nie wysyłać hasła w plain text NIGDY

**Sensitive data**:
- HTTPS only (wymuszać w production)
- Nie logować sensitive data (hasła, tokeny, pełne numery kart)
- Maskować częściowo dane w UI gdzie potrzeba (np. telefon: +48 *** *** 123)
- Logging z redakcją sensitive fields

**Session management**:
- Invalidacja tokenu przy logout (backend)
- Limit jednej sesji per user (opcjonalnie)
- Token rotation przy krytycznych operacjach (opcjonalnie w przyszłości)

**Audit logging**:
- Wszystkie login attempts (success + failure) z IP
- Wszystkie operacje modyfikujące dane z user ID + IP
- Wszystkie zmiany konfiguracji systemu
- Retention: 1 rok (zgodnie z PRD)

**Error messages**:
- Nie ujawniać szczegółów technicznych użytkownikom
- Generic messages dla security-sensitive operacji (login failures)
- Detailed logs w backend dla debugging (access restricted to admins)

### 5. Przepływy użytkownika (User Flows)

#### Flow 1: Call Center - Obsługa nowego leada

```
1. User loguje się → Dashboard (lista leadów)
   ├─ Auto-sort: najnowsze na górze
   └─ Badge notyfikacji: "5 nowych leadów"

2. User klika "Załaduj nowe leady"
   ├─ HTMX request: GET /api/leads?created_from={last_check}
   └─ Tabela odświeżona, nowe leady highlighted

3. User klika "Zobacz szczegóły" przy leadzie
   ├─ Slider otwiera się z prawej
   └─ HTMX request: GET /api/leads/{id}

4. User przegląda dane klienta w sliderze
   ├─ Tab "Informacje" aktywny domyślnie
   └─ Widzi: dane kontaktowe, preferencje, status

5. User dzwoni do klienta (poza systemem)

6. Po rozmowie: User aktualizuje preferencje
   ├─ Klik "Edytuj preferencje" → inline edit mode
   ├─ Zmienia: cena min/max, lokalizacja, miasto
   ├─ Klik "Zapisz"
   ├─ HTMX request: PUT /api/customers/{id}/preferences
   └─ Toast: "Preferencje zapisane"

7. User zmienia status leada
   ├─ Dropdown status: "Nowy" → "Skontaktowano"
   ├─ Auto-save przy zmianie
   ├─ HTMX request: PUT /api/leads/{id}
   └─ Toast: "Status zaktualizowany"

8. User zamyka slider
   └─ Wraca do listy leadów (status zaktualizowany w tabeli)

9. User kontynuuje z kolejnym leadem
   └─ Repeat flow od kroku 3
```

#### Flow 2: BOK - Przeglądanie historii klienta (Read-only)

```
1. User (BOK role) loguje się → Dashboard

2. User przechodzi do "Klienci" (sidebar navigation)
   └─ Lista klientów z wyszukiwaniem

3. User wpisuje telefon klienta w wyszukiwaniu
   ├─ HTMX request: GET /api/customers?search={phone}
   └─ Filtrowana lista

4. User klika "Zobacz szczegóły" przy kliencie
   ├─ Slider otwiera się z prawej
   └─ HTMX request: GET /api/customers/{id}

5. User przegląda dane
   ├─ Dane podstawowe (read-only, inputs disabled)
   ├─ Preferencje (read-only, BRAK przycisku "Edytuj")
   └─ Lista wszystkich leadów klienta

6. User klika na konkretny lead z listy
   ├─ Slider zmienia zawartość → szczegóły leada
   └─ HTMX request: GET /api/leads/{id}

7. User przegląda tab "Historia"
   └─ Timeline eventów (read-only)

8. User próbuje zmienić status (attempt)
   └─ Dropdown disabled (role: BOK nie ma uprawnień)

9. User zamyka slider
   └─ Wraca do listy klientów
```

#### Flow 3: Admin - Retry nieudanej dostawy do CDP

```
1. User (Admin role) loguje się → Dashboard

2. User zauważa badge notyfikacji: "Nieudane dostawy [3]"

3. User klika "Nieudane dostawy" (sidebar navigation)
   └─ Lista failed deliveries

4. User filtruje: Status = "failed", CDP System = "SalesManago"
   ├─ HTMX request: GET /api/failed-deliveries?status=failed&cdp_system_name=SalesManago
   └─ Filtrowana lista

5. User klika "Retry" przy konkretnej dostawie
   ├─ Modal potwierdzenia: "Czy na pewno chcesz ponowić dostawę do SalesManago dla leada {UUID}?"
   ├─ User klika "Potwierdź"
   ├─ HTMX request: POST /api/failed-deliveries/{id}/retry
   └─ Toast: "Retry zainicjowany"

6. Status w tabeli zmienia się: "failed" → "retrying"
   └─ Auto-refresh co 60s pokazuje progress

7. Po minucie: status zmienia się na "success" (zielony)
   ├─ Badge notyfikacji aktualizowany: [3] → [2]
   └─ User widzi sukces

8. User klika "Zobacz szczegóły" (accordion) przy dostawie
   └─ Timeline prób dostawy z error details

9. User przechodzi do "Eventy" (sidebar)
   ├─ Filtruje: Entity Type = "failed_delivery", Entity ID = {id}
   └─ Widzi pełną historię: failed → retry initiated → success
```

#### Flow 4: Admin - Zmiana konfiguracji systemu

```
1. User (Admin role) loguje się → Dashboard

2. User klika "Konfiguracja" (sidebar navigation)
   └─ Widok pogrupowanych kart tematycznych

3. User wybiera kartę "Limity i retry"
   └─ Formularz z obecnymi wartościami

4. User zmienia "Max retries dla CDP delivery": 5 → 7
   └─ Input value aktualizowany (controlled)

5. User zmienia "Exponential backoff multiplier": 2 → 3

6. User klika "Zapisz zmiany"
   ├─ Modal potwierdzenia:
   │   "Czy na pewno chcesz zmienić konfigurację?
   │   Max retries: 5 → 7
   │   Backoff multiplier: 2 → 3
   │   To wpłynie na wszystkie przyszłe dostawy do CDP."
   ├─ Przyciski: "Anuluj" | "Potwierdź"
   └─ User klika "Potwierdź"

7. Backend processing:
   ├─ PUT /api/system-config/max_retries
   ├─ PUT /api/system-config/backoff_multiplier
   ├─ Event logged z old/new values
   └─ Response: success

8. Toast: "Konfiguracja zaktualizowana"
   └─ Formularz pokazuje nowe wartości

9. User przechodzi do "Eventy"
   ├─ Filtruje: Event Type = "config_updated"
   └─ Widzi wpisy z poprzednimi i nowymi wartościami dla audytu
```

#### Flow 5: Session timeout handling

```
1. User pracuje w systemie (Dashboard, przegląda leady)

2. 28 minut bez aktywności (user na przerwie)

3. System wykrywa inactivity (JS timer)
   └─ Modal warning:
       "Twoja sesja wygaśnie za 2 minuty z powodu braku aktywności.
       Kliknij 'Kontynuuj' aby pozostać zalogowanym."
       Przyciski: "Wyloguj" | "Kontynuuj"

4a. User klika "Kontynuuj"
    ├─ API request: refresh session (backend)
    ├─ Timer reset
    └─ Modal zamknięty, user kontynuuje pracę

4b. User nie reaguje przez 2 minuty
    ├─ Auto logout
    ├─ POST /api/auth/logout
    ├─ SessionStorage cleared
    ├─ Redirect: /login?message=session_expired&redirect=/leads
    └─ Komunikat na stronie login: "Sesja wygasła. Zaloguj się ponownie."

5. User loguje się ponownie
   └─ Przekierowanie na /leads (zachowany context z parametru redirect)
```

### 6. Component Breakdown (Twig Templates)

#### 6.1 Template Structure

```
templates/
├── base.html.twig                    # Base layout (skeleton)
├── components/
│   ├── sidebar.html.twig            # Sidebar navigation
│   ├── header.html.twig             # Top header with user info
│   ├── toast.html.twig              # Toast notification component
│   ├── modal.html.twig              # Modal component (generic)
│   ├── pagination.html.twig         # Pagination component
│   ├── loading_spinner.html.twig    # Loading indicator
│   └── badge.html.twig              # Badge component (notifications)
├── auth/
│   ├── login.html.twig              # Login page
│   └── change_password.html.twig    # Change password page/modal
├── leads/
│   ├── index.html.twig              # Dashboard with leads list
│   ├── _list.html.twig              # Leads table (partial, HTMX target)
│   ├── _filters.html.twig           # Filters section (partial)
│   ├── _details.html.twig           # Lead details slider (partial)
│   └── _status_dropdown.html.twig   # Status dropdown (partial)
├── customers/
│   ├── index.html.twig              # Customers list
│   ├── _list.html.twig              # Customers table (partial)
│   ├── _details.html.twig           # Customer details slider (partial)
│   └── _preferences_form.html.twig  # Preferences edit form (partial)
├── failed_deliveries/
│   ├── index.html.twig              # Failed deliveries list
│   ├── _list.html.twig              # Failed deliveries table (partial)
│   └── _details.html.twig           # Failed delivery details (partial)
├── events/
│   ├── index.html.twig              # Events list (Admin only)
│   ├── _list.html.twig              # Events table (partial)
│   └── _details.html.twig           # Event details accordion (partial)
├── config/
│   ├── index.html.twig              # System config (Admin only)
│   ├── _integrations.html.twig      # CDP integrations card (partial)
│   ├── _limits.html.twig            # Limits and retry card (partial)
│   ├── _security.html.twig          # Security settings card (partial)
│   └── _system.html.twig            # System info card (partial)
└── help/
    └── index.html.twig              # Help/FAQ page (static)
```

#### 6.2 Key Components Design

**base.html.twig** (main layout):
```twig
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{% block title %}LMS - Lead Management System{% endblock %}</title>
    
    {# Bootstrap 5 CSS #}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    {# Custom CSS #}
    <link href="{{ asset('css/app.css?v=' ~ app_version) }}" rel="stylesheet">
    
    {# HTMX #}
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    
    {% block stylesheets %}{% endblock %}
</head>
<body>
    {% if app.user %}
        {# Authenticated layout #}
        <div class="d-flex">
            {% include 'components/sidebar.html.twig' %}
            
            <main class="flex-grow-1">
                {% include 'components/header.html.twig' %}
                
                <div class="container-fluid p-4">
                    {% block body %}{% endblock %}
                </div>
            </main>
        </div>
        
        {# Slider overlay container (for lead/customer details) #}
        <div id="slider-container"></div>
        
        {# Toast container (for notifications) #}
        <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999"></div>
        
        {# Session timeout warning modal #}
        {% include 'components/session_timeout_modal.html.twig' %}
    {% else %}
        {# Unauthenticated layout #}
        {% block unauthenticated_body %}{% endblock %}
    {% endif %}
    
    {# Bootstrap JS #}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    {# Custom JS #}
    <script src="{{ asset('js/app.js?v=' ~ app_version) }}"></script>
    
    {% block javascripts %}{% endblock %}
</body>
</html>
```

**components/sidebar.html.twig**:
```twig
<nav class="sidebar bg-dark text-white" style="width: 250px; min-height: 100vh;">
    <div class="p-3">
        <h4 class="text-center mb-4">
            <i class="bi bi-building"></i> LMS
        </h4>
    </div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link text-white {{ app.request.attributes.get('_route') starts with 'leads' ? 'active fw-bold' : '' }}" 
               href="{{ path('leads_index') }}">
                <i class="bi bi-list-ul"></i> Leady
                <span id="leads-badge" 
                      hx-get="{{ path('api_leads_count_new') }}" 
                      hx-trigger="every 30s"
                      hx-swap="innerHTML">
                    {# Badge dynamically updated #}
                </span>
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link text-white {{ app.request.attributes.get('_route') starts with 'customers' ? 'active fw-bold' : '' }}" 
               href="{{ path('customers_index') }}">
                <i class="bi bi-people"></i> Klienci
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link text-white {{ app.request.attributes.get('_route') starts with 'failed_deliveries' ? 'active fw-bold' : '' }}" 
               href="{{ path('failed_deliveries_index') }}">
                <i class="bi bi-exclamation-triangle"></i> Nieudane dostawy
                <span id="failed-deliveries-badge"
                      hx-get="{{ path('api_failed_deliveries_count') }}"
                      hx-trigger="every 60s"
                      hx-swap="innerHTML">
                    {# Badge dynamically updated #}
                </span>
            </a>
        </li>
        
        {% if is_granted('ROLE_ADMIN') %}
            <li class="nav-item">
                <a class="nav-link text-white {{ app.request.attributes.get('_route') starts with 'events' ? 'active fw-bold' : '' }}" 
                   href="{{ path('events_index') }}">
                    <i class="bi bi-journal-text"></i> Eventy
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link text-white {{ app.request.attributes.get('_route') starts with 'config' ? 'active fw-bold' : '' }}" 
                   href="{{ path('config_index') }}">
                    <i class="bi bi-gear"></i> Konfiguracja
                </a>
            </li>
        {% endif %}
    </ul>
    
    <div class="mt-auto p-3 border-top border-secondary">
        <a href="{{ path('help') }}" class="nav-link text-white small">
            <i class="bi bi-question-circle"></i> Pomoc
        </a>
        <a href="mailto:support@example.com" class="nav-link text-white small">
            <i class="bi bi-envelope"></i> Zgłoś problem
        </a>
    </div>
</nav>
```

**leads/_details.html.twig** (slider content):
```twig
<div class="offcanvas offcanvas-end show" style="width: 600px;" id="lead-details-slider">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Szczegóły leada #{{ lead.id }}</h5>
        <button type="button" class="btn-close" hx-get="{{ path('leads_close_slider') }}" hx-target="#slider-container"></button>
    </div>
    
    <div class="offcanvas-body">
        {# Tabs #}
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#info-tab">Informacje</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#history-tab">Historia</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#property-tab">Nieruchomość</button>
            </li>
        </ul>
        
        <div class="tab-content">
            {# Tab: Informacje #}
            <div class="tab-pane fade show active" id="info-tab">
                {# Dane klienta #}
                <div class="card mb-3">
                    <div class="card-header">Dane klienta</div>
                    <div class="card-body">
                        <p><strong>Imię i nazwisko:</strong> {{ lead.customer.firstName }} {{ lead.customer.lastName }}</p>
                        <p><strong>Email:</strong> <a href="mailto:{{ lead.customer.email }}">{{ lead.customer.email }}</a></p>
                        <p><strong>Telefon:</strong> <a href="tel:{{ lead.customer.phone }}">{{ lead.customer.phone }}</a></p>
                        <a href="{{ path('customers_show', {id: lead.customer.id}) }}" class="btn btn-sm btn-outline-primary">
                            Zobacz wszystkie leady klienta ({{ lead.customer.leads|length }})
                        </a>
                    </div>
                </div>
                
                {# Preferencje klienta #}
                <div class="card mb-3">
                    <div class="card-header">
                        Preferencje klienta
                        {% if is_granted('ROLE_CALL_CENTER') or is_granted('ROLE_ADMIN') %}
                            <button class="btn btn-sm btn-link float-end" id="edit-preferences-btn">Edytuj</button>
                        {% endif %}
                    </div>
                    <div class="card-body" id="preferences-container">
                        {% include 'customers/_preferences_form.html.twig' with {
                            'customer': lead.customer,
                            'editable': false
                        } %}
                    </div>
                </div>
                
                {# Status leada #}
                <div class="card mb-3">
                    <div class="card-header">Status leada</div>
                    <div class="card-body">
                        {% if is_granted('ROLE_CALL_CENTER') or is_granted('ROLE_ADMIN') %}
                            <select class="form-select"
                                    hx-put="{{ path('api_leads_update_status', {id: lead.id}) }}"
                                    hx-target="#lead-{{ lead.id }}"
                                    hx-swap="outerHTML"
                                    name="status">
                                <option value="new" {{ lead.status == 'new' ? 'selected' : '' }}>Nowy</option>
                                <option value="contacted" {{ lead.status == 'contacted' ? 'selected' : '' }}>Skontaktowano</option>
                                <option value="qualified" {{ lead.status == 'qualified' ? 'selected' : '' }}>Zakwalifikowano</option>
                                <option value="converted" {{ lead.status == 'converted' ? 'selected' : '' }}>Przekonwertowano</option>
                                <option value="rejected" {{ lead.status == 'rejected' ? 'selected' : '' }}>Odrzucono</option>
                            </select>
                        {% else %}
                            <p class="mb-0">{{ lead.statusLabel }}</p>
                        {% endif %}
                    </div>
                </div>
                
                {# CDP Delivery Status #}
                <div class="card mb-3">
                    <div class="card-header">Status dostawy do CDP</div>
                    <div class="card-body">
                        {# Loop through CDP systems and show delivery status #}
                        {% for delivery in lead.failedDeliveries %}
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>{{ delivery.cdpSystemName }}</span>
                                <span class="badge bg-{{ delivery.statusColor }}">{{ delivery.statusLabel }}</span>
                                {% if delivery.status == 'failed' and is_granted('ROLE_ADMIN') %}
                                    <button class="btn btn-sm btn-warning"
                                            hx-post="{{ path('api_failed_deliveries_retry', {id: delivery.id}) }}"
                                            hx-target="#delivery-{{ delivery.id }}"
                                            hx-confirm="Czy na pewno chcesz ponowić dostawę?">
                                        Retry
                                    </button>
                                {% endif %}
                            </div>
                        {% endfor %}
                    </div>
                </div>
            </div>
            
            {# Tab: Historia #}
            <div class="tab-pane fade" id="history-tab">
                <div class="timeline">
                    {% for event in lead.events %}
                        <div class="timeline-item mb-3">
                            <div class="d-flex">
                                <div class="timeline-icon me-3">
                                    <i class="bi bi-{{ event.icon }} text-{{ event.colorClass }}"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <strong>{{ event.eventType }}</strong>
                                    <small class="text-muted d-block">{{ event.createdAt|date('Y-m-d H:i:s') }}</small>
                                    {% if event.user %}
                                        <small class="text-muted">przez {{ event.user.username }}</small>
                                    {% endif %}
                                    
                                    {% if event.details %}
                                        <button class="btn btn-sm btn-link p-0" data-bs-toggle="collapse" data-bs-target="#event-details-{{ event.id }}">
                                            Szczegóły
                                        </button>
                                        <div class="collapse" id="event-details-{{ event.id }}">
                                            <pre class="bg-light p-2 mt-2"><code>{{ event.details|json_encode(constant('JSON_PRETTY_PRINT')) }}</code></pre>
                                        </div>
                                    {% endif %}
                                </div>
                            </div>
                        </div>
                    {% endfor %}
                </div>
            </div>
            
            {# Tab: Nieruchomość #}
            <div class="tab-pane fade" id="property-tab">
                <div class="card">
                    <div class="card-body">
                        <p><strong>Property ID:</strong> {{ lead.leadProperty.propertyId }}</p>
                        <p><strong>Development ID:</strong> {{ lead.leadProperty.developmentId }}</p>
                        <p><strong>Partner ID:</strong> {{ lead.leadProperty.partnerId }}</p>
                        <p><strong>Typ:</strong> {{ lead.leadProperty.propertyType }}</p>
                        <p><strong>Cena:</strong> {{ lead.leadProperty.price|number_format(2, ',', ' ') }} PLN</p>
                        <p><strong>Lokalizacja:</strong> {{ lead.leadProperty.location }}</p>
                        <p><strong>Miasto:</strong> {{ lead.leadProperty.city }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="offcanvas-footer border-top p-3">
        {% if is_granted('ROLE_CALL_CENTER') or is_granted('ROLE_ADMIN') %}
            <button class="btn btn-danger"
                    hx-delete="{{ path('api_leads_delete', {id: lead.id}) }}"
                    hx-target="#lead-{{ lead.id }}"
                    hx-confirm="Czy na pewno chcesz usunąć tego leada? Operacja jest nieodwracalna.">
                <i class="bi bi-trash"></i> Usuń lead
            </button>
        {% endif %}
        <button class="btn btn-secondary" hx-get="{{ path('leads_close_slider') }}" hx-target="#slider-container">
            Zamknij
        </button>
    </div>
</div>
```

### 7. Visual Design Specifications

#### 7.1 Color Palette

**Primary colors**:
- `#0d6efd` - Primary blue (Bootstrap default) - buttons, links, active states
- `#6c757d` - Secondary gray - secondary buttons, disabled states
- `#ffffff` - White - backgrounds, cards
- `#f8f9fa` - Light gray - page background, table headers

**Status colors**:
- `#198754` - Success green - completed actions, successful deliveries
- `#dc3545` - Danger red - errors, failed deliveries, delete actions
- `#ffc107` - Warning yellow - pending actions, warnings
- `#0dcaf0` - Info cyan - informational messages

**Text colors**:
- `#212529` - Primary text (dark gray-black)
- `#6c757d` - Secondary text (muted)
- `#ffffff` - Text on dark backgrounds

**Status badges**:
- **Lead status**:
  - new: `badge bg-info`
  - contacted: `badge bg-primary`
  - qualified: `badge bg-success`
  - converted: `badge bg-success`
  - rejected: `badge bg-secondary`
- **CDP delivery status**:
  - pending: `badge bg-warning`
  - success: `badge bg-success`
  - failed: `badge bg-danger`
  - retrying: `badge bg-info`

#### 7.2 Typography

**Font family**: Systemowe fonty
```css
font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
```

**Font sizes**:
- H1: 2.5rem (40px) - Page titles
- H2: 2rem (32px) - Section headers
- H3: 1.75rem (28px) - Card headers
- H4: 1.5rem (24px) - Subsection headers
- Body: 1rem (16px) - Normal text
- Small: 0.875rem (14px) - Secondary text, captions
- Tiny: 0.75rem (12px) - Footer text, badges

**Font weights**:
- Regular: 400 - Body text
- Medium: 500 - Table headers, emphasis
- Bold: 700 - Headings, active states

#### 7.3 Spacing

**Padding/Margin scale** (Bootstrap):
- 0: 0
- 1: 0.25rem (4px)
- 2: 0.5rem (8px)
- 3: 1rem (16px)
- 4: 1.5rem (24px)
- 5: 3rem (48px)

**Component spacing**:
- Between sections: 24px (p-4 or mb-4)
- Between form fields: 16px (mb-3)
- Between table rows: 8px padding
- Card padding: 16px (card-body)

#### 7.4 Icons

**Icon library**: Bootstrap Icons (https://icons.getbootstrap.com/)

**Common icons**:
- Leady: `bi-list-ul`
- Klienci: `bi-people`
- Nieudane dostawy: `bi-exclamation-triangle`
- Eventy: `bi-journal-text`
- Konfiguracja: `bi-gear`
- Pomoc: `bi-question-circle`
- Zgłoś problem: `bi-envelope`
- Wyloguj: `bi-box-arrow-right`
- Edytuj: `bi-pencil`
- Usuń: `bi-trash`
- Zobacz: `bi-eye`
- Zamknij: `bi-x`
- Sukces: `bi-check-circle`
- Błąd: `bi-x-circle`
- Ostrzeżenie: `bi-exclamation-triangle`
- Info: `bi-info-circle`

**Icon sizes**:
- Default: 1rem (16px)
- Large: 1.5rem (24px)
- XLarge: 2rem (32px)

#### 7.5 Components Styling

**Buttons**:
```css
.btn-primary {
  background-color: #0d6efd;
  border-color: #0d6efd;
  padding: 0.5rem 1rem;
  border-radius: 0.25rem;
}

.btn-secondary {
  background-color: #6c757d;
  border-color: #6c757d;
}

.btn-danger {
  background-color: #dc3545;
  border-color: #dc3545;
}
```

**Cards**:
```css
.card {
  border: 1px solid #dee2e6;
  border-radius: 0.25rem;
  box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
  margin-bottom: 1rem;
}

.card-header {
  background-color: #f8f9fa;
  border-bottom: 1px solid #dee2e6;
  padding: 0.75rem 1rem;
  font-weight: 500;
}

.card-body {
  padding: 1rem;
}
```

**Tables**:
```css
.table {
  width: 100%;
  margin-bottom: 1rem;
  color: #212529;
}

.table thead th {
  background-color: #f8f9fa;
  border-bottom: 2px solid #dee2e6;
  font-weight: 500;
  padding: 0.75rem;
}

.table tbody tr:hover {
  background-color: #f8f9fa;
}

.table tbody td {
  padding: 0.75rem;
  border-bottom: 1px solid #dee2e6;
}
```

**Forms**:
```css
.form-label {
  font-weight: 500;
  margin-bottom: 0.5rem;
}

.form-control {
  border: 1px solid #ced4da;
  border-radius: 0.25rem;
  padding: 0.5rem 0.75rem;
}

.form-control:focus {
  border-color: #0d6efd;
  box-shadow: 0 0 0 0.25rem rgba(13,110,253,0.25);
}

.form-control.is-invalid {
  border-color: #dc3545;
}

.invalid-feedback {
  color: #dc3545;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}
```

**Toasts**:
```css
.toast {
  min-width: 300px;
  background-color: #fff;
  border: 1px solid #dee2e6;
  border-radius: 0.25rem;
  box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
}

.toast-header {
  background-color: #f8f9fa;
  border-bottom: 1px solid #dee2e6;
  padding: 0.5rem 0.75rem;
}

.toast-body {
  padding: 0.75rem;
}

/* Success toast */
.toast.bg-success {
  background-color: #d1e7dd !important;
  border-color: #badbcc;
}

/* Error toast */
.toast.bg-danger {
  background-color: #f8d7da !important;
  border-color: #f5c2c7;
}
```

---

## Unresolved Issues

### 1. User Management and Admin Panel
**Issue**: Brak szczegółów dotyczących zarządzania użytkownikami (tworzenie, edycja, usuwanie, reset haseł) w API Plan i PRD.

**Questions**:
- Czy w MVP potrzebny jest UI do zarządzania użytkownikami przez Admina, czy użytkownicy są zarządzani tylko przez bezpośredni dostęp do bazy?
- Jeśli potrzebny, jakie funkcje powinien zawierać panel zarządzania użytkownikami?
- Czy należy dodać endpointy do API Plan: GET/POST/PUT/DELETE /api/users?

**Impact**: Średni - można rozpocząć pracę nad core functionality bez tego, dodać później.

### 2. Endpoint dla zmiany hasła
**Issue**: Rekomendacja zawiera funkcjonalność zmiany hasła (PUT /api/users/me/password), ale endpoint nie istnieje w API Plan.

**Questions**:
- Czy należy dodać endpoint PUT /api/users/me/password do API Plan?
- Jakie powinny być wymagania walidacji hasła (obecnie: min 8 znaków, 1 cyfra, 1 wielka litera)?
- Czy po zmianie hasła należy wylogować użytkownika ze wszystkich sesji (force re-login)?

**Impact**: Niski - można dodać w późniejszej fazie MVP.

### 3. Notifications i alerting dla Adminów
**Issue**: PRD wspomina "Alerty dla administratorów o nieudanych operacjach", ale brak szczegółów implementacji.

**Questions**:
- Czy alerting w MVP to tylko badge notifications w UI, czy też email notifications?
- Jeśli email - czy potrzebne jest API do konfiguracji email settings i templates?
- Jakie progi triggerują alerty (np. >10 failed deliveries w ciągu godziny)?

**Impact**: Niski - badge notifications wystarczają dla MVP, email można dodać później.

### 4. Audit log access i filtering
**Issue**: Eventy są logowane, ale brak szczegółów o retention policy enforcement i archiving.

**Questions**:
- Czy automatyczne usuwanie eventów starszych niż 1 rok jest background job czy manual action?
- Czy należy dodać endpoint do exportu audit logs dla compliance?
- Czy eventy powinny mieć opcję "soft delete" przed permanent deletion?

**Impact**: Niski - można implementować prosty cron job do usuwania starych eventów.

### 5. Property data enrichment
**Issue**: Obecnie tylko podstawowe dane nieruchomości zapisywane przy tworzeniu leada. Brak integracji z systemami źródłowymi dla aktualnych danych.

**Questions**:
- Czy dane nieruchomości w przyszłości powinny być dynamicznie pobierane z Morizon/Gratka API?
- Czy potrzebna jest funkcjonalność "odśwież dane nieruchomości" w UI?
- Jak obsłużyć sytuację gdy property_id nie istnieje już w systemie źródłowym?

**Impact**: Niski - dla MVP wystarczają snapshot dane przy tworzeniu leada.

### 6. Lead assignment i ownership
**Issue**: Brak informacji o przypisywaniu leadów do konkretnych pracowników call center.

**Questions**:
- Czy leady powinny być przypisane do konkretnych użytkowników (ownership)?
- Czy potrzebna jest funkcjonalność "weź lead" / "zwolnij lead" aby uniknąć konfliktu gdy wielu pracowników pracuje na tym samym leadzie?
- Czy call center widzą wszystkie leady czy tylko przypisane do nich?

**Impact**: Średni - w MVP wszystkie leady widoczne dla wszystkich, ownership można dodać później.

### 7. Customer preferences validation
**Issue**: Brak szczegółów o walidacji preferencji (czy cena min < cena max, format lokalizacji, lista dozwolonych miast).

**Questions**:
- Jakie są reguły walidacji dla preferencji (np. price_min <= price_max)?
- Czy lokalizacja i miasto to free text czy dropdown z predefiniowanymi wartościami?
- Jeśli dropdown - skąd pochodzi lista miast/lokalizacji (API endpoint, static config)?

**Impact**: Niski - można zacząć od basic validation i free text, uściślić później.

### 8. Rate limiting implementation details
**Issue**: API Plan wspomina rate limiting (1000 requests/minute), ale brak szczegółów implementacji w UI context.

**Questions**:
- Czy rate limiting jest per user czy global dla całej aplikacji?
- Jak UI powinien reagować gdy rate limit jest exceeded (429 Too Many Requests)?
- Czy należy pokazać użytkownikowi ile requestów pozostało (rate limit quota)?

**Impact**: Niski - podstawowa obsługa 429 error wystarczy dla MVP.

### 9. Multi-language support preparation
**Issue**: MVP tylko polski, ale brak informacji czy architektura powinna być przygotowana na przyszłą internacjonalizację.

**Questions**:
- Czy teksty w Twig templates powinny być już teraz w translation files ({% trans %}) mimo że jest tylko polski?
- Czy API powinno zwracać translated strings czy frontend robi translację?
- Czy przygotowanie i18n teraz nie spowolni znacząco development MVP?

**Impact**: Niski - można zacząć bez i18n i dodać później (refactoring potrzebny).

### 10. Performance monitoring i metrics
**Issue**: PRD definiuje metryki (response time <3s, 99.9% uptime), ale brak szczegółów o monitoring tools w UI.

**Questions**:
- Czy Admin powinien mieć dostęp do performance metrics dashboard w UI?
- Czy metrics są tylko w external monitoring tools (Grafana, etc.) czy także w aplikacji?
- Czy potrzebne są API endpointy do pobierania metrics (GET /api/metrics)?

**Impact**: Niski - monitoring może być external tool, nie wymaga UI w MVP.

---

## Next Steps

### Immediate Actions (przed rozpoczęciem implementacji):

1. **Potwierdzenie z Product Owner**:
   - Przejrzeć i zaakceptować wszystkie rekomendacje
   - Rozwiązać Unresolved Issues (priorytety 1, 6, 7)
   - Potwierdzić zakres MVP vs. future enhancements

2. **API Plan updates**:
   - Dodać endpoint PUT /api/users/me/password (jeśli funkcja zmiany hasła w MVP)
   - Dodać endpoint GET /api/leads/count/new (dla badge notifications)
   - Dodać endpoint GET /api/failed-deliveries/count (dla badge notifications)
   - Uzupełnić szczegóły walidacji dla customer preferences

3. **Design Assets**:
   - Przygotować logo LMS
   - Przygotować favicon
   - Przygotować color palette variables w CSS
   - Przygotować custom CSS dla aplikacji (na bazie Bootstrap)

4. **Development Setup**:
   - Setup Bootstrap 5 w Symfony projekcie
   - Setup HTMX
   - Setup Twig template structure (zgodnie z Component Breakdown)
   - Setup Webpack Encore dla asset compilation

5. **Database Schema Review**:
   - Upewnić się że User, Role, Permission entities istnieją zgodnie z RBAC requirements
   - Sprawdzić czy wszystkie needed relationships są w Doctrine mapping

### Implementation Order (rekomendowany):

**Phase 1: Authentication & Base Layout** (Tydzień 1)
- Login page + authentication flow
- Base layout (sidebar, header, footer)
- Role-based access control implementation
- Session management z timeout

**Phase 2: Core Leads Functionality** (Tydzień 2-3)
- Dashboard z listą leadów
- Filtry i sortowanie
- Paginacja
- Slider szczegółów leada (read-only)
- HTMX integration dla partial updates

**Phase 3: Lead Management** (Tydzień 4)
- Edycja statusu leada
- Usuwanie leada
- Eventy timeline w sliderze
- Toast notifications

**Phase 4: Customer Management** (Tydzień 5)
- Lista klientów
- Wyszukiwanie klientów
- Slider szczegółów klienta
- Edycja preferencji klienta

**Phase 5: Failed Deliveries** (Tydzień 6)
- Lista nieudanych dostaw
- Retry functionality
- Badge notifications z polling
- Filtrowanie i szczegóły

**Phase 6: Admin Features** (Tydzień 7)
- Panel eventów (full access)
- Konfiguracja systemu (karty tematyczne)
- Zmiana hasła
- Eksport do CSV

**Phase 7: Polish & Testing** (Tydzień 8)
- Responsiveness tweaks
- Accessibility improvements
- Cross-browser testing
- Bug fixes i refinements
- Strona pomocy/FAQ

---

## Summary

Dokument ten stanowi kompleksowe podsumowanie planowania architektury UI dla MVP Lead Management System. Zawiera:

- **85 pytań i rekomendacji** dotyczących wszystkich aspektów UI/UX
- **Szczegółowe specyfikacje** dla każdego głównego widoku
- **Przepływy użytkownika** dla kluczowych scenariuszy
- **Strategię integracji z API** i zarządzania stanem
- **Design system** oparty na Bootstrap 5
- **Component breakdown** z przykładami Twig templates
- **10 unresolved issues** wymagających claryfikacji
- **Plan implementacji** w 8 fazach

Architektura UI jest zaprojektowana z priorytetem na:
- Szybką implementację MVP (8 tygodni)
- Prostotę i czytelność dla użytkowników call center
- Pełną integrację z dostępnymi API endpoints
- Bezpieczeństwo i role-based access control
- Możliwość łatwego rozszerzenia w przyszłości

Dokument gotowy do przekazania zespołowi developerów do rozpoczęcia implementacji po rozwiązaniu Unresolved Issues.



























