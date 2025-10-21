```mermaid
flowchart TD
    subgraph "Layout Bazowy"
        BaseLayout["base.html.twig<br/>Layout główny"]
        AuthBlock["Block: body<br/>Treść autoryzowana"]
        UnauthBlock["Block: unauthenticated_body<br/>Treść publiczna"]
        
        BaseLayout --> AuthBlock
        BaseLayout --> UnauthBlock
    end
    
    subgraph "Strony Publiczne - Autentykacja"
        LoginPage["auth/login.html.twig<br/>★ AKTUALIZACJA<br/>Strona logowania"]
        PasswordReqPage["auth/password_request.html.twig<br/>★ NOWA<br/>Żądanie resetu hasła"]
        PasswordResetPage["auth/password_reset.html.twig<br/>★ NOWA<br/>Ustawienie nowego hasła"]
        
        LoginForm["Formularz logowania<br/>- Email<br/>- Hasło<br/>- Zapamiętaj mnie<br/>- CSRF token"]
        PasswordReqForm["Formularz resetu<br/>- Email<br/>- CSRF token"]
        PasswordResetForm["Formularz nowego hasła<br/>- Nowe hasło<br/>- Potwierdzenie<br/>- Token w URL"]
        
        LoginPage --> LoginForm
        PasswordReqPage --> PasswordReqForm
        PasswordResetPage --> PasswordResetForm
        
        LoginForm -.->|Link| PasswordReqPage
        PasswordReqForm -.->|Email z linkiem| PasswordResetPage
        PasswordResetForm -.->|Po sukcesie| LoginPage
    end
    
    subgraph "Komponenty Współdzielone"
        Header["components/header.html.twig<br/>Nagłówek aplikacji"]
        Sidebar["components/sidebar.html.twig<br/>Menu boczne"]
        ErrorMsg["components/_error_message.html.twig<br/>Komunikaty błędów"]
        
        UserMenu["Menu użytkownika<br/>- Avatar z inicjałami<br/>- Nazwa i rola<br/>- Zmień hasło<br/>- Wyloguj"]
        SidebarNav["Nawigacja<br/>- Leady<br/>- Klienci<br/>- Eventy<br/>- Nieudane dostawy<br/>- Konfiguracja"]
        
        Header --> UserMenu
        Sidebar --> SidebarNav
    end
    
    subgraph "Strony Autoryzowane - Profil"
        ChangePasswordPage["auth/change_password.html.twig<br/>★ AKTUALIZACJA<br/>Zmiana hasła"]
        
        ChangePasswordForm["Formularz zmiany hasła<br/>- Obecne hasło<br/>- Nowe hasło<br/>- Potwierdzenie<br/>- CSRF token"]
        
        ChangePasswordPage --> ChangePasswordForm
    end
    
    subgraph "Strony Autoryzowane - Dashboard"
        LeadsPage["leads/index.html.twig<br/>★ Wymaga: ROLE_USER<br/>Dashboard leadów"]
        CustomersPage["customers/index.html.twig<br/>★ Wymaga: ROLE_USER<br/>Lista klientów"]
        EventsPage["events/index.html.twig<br/>★ Wymaga: ROLE_USER<br/>Historia eventów"]
        FailedPage["failed_deliveries/index.html.twig<br/>★ Wymaga: ROLE_USER<br/>Nieudane dostawy"]
        ConfigPage["config/index.html.twig<br/>★ Wymaga: ROLE_ADMIN<br/>Konfiguracja"]
    end
    
    subgraph "Przepływ Użytkownika"
        User([Użytkownik])
        CheckAuth{Sprawdź<br/>autentykację}
        
        User --> CheckAuth
        CheckAuth -->|Niezalogowany| UnauthBlock
        CheckAuth -->|Zalogowany| AuthBlock
    end
    
    UnauthBlock --> LoginPage
    UnauthBlock --> PasswordReqPage
    UnauthBlock --> PasswordResetPage
    
    AuthBlock --> Header
    AuthBlock --> Sidebar
    AuthBlock --> ChangePasswordPage
    AuthBlock --> LeadsPage
    AuthBlock --> CustomersPage
    AuthBlock --> EventsPage
    AuthBlock --> FailedPage
    AuthBlock --> ConfigPage
    
    UserMenu -->|Akcja| ChangePasswordPage
    UserMenu -->|Akcja| LogoutAction[Wylogowanie]
    
    SidebarNav -->|Nawigacja| LeadsPage
    SidebarNav -->|Nawigacja| CustomersPage
    SidebarNav -->|Nawigacja| EventsPage
    SidebarNav -->|Nawigacja| FailedPage
    SidebarNav -->|Nawigacja warunkowa<br/>ROLE_ADMIN| ConfigPage
    
    LogoutAction -.->|Przekierowanie| LoginPage
    
    LoginForm -->|POST /login<br/>Sukces| LeadsPage
    LoginForm -->|Błąd| ErrorMsg
    PasswordReqForm -->|Błąd| ErrorMsg
    PasswordResetForm -->|Błąd| ErrorMsg
    ChangePasswordForm -->|Błąd| ErrorMsg
    
    classDef newComponent fill:#c8e6c9,stroke:#2e7d32,stroke-width:3px
    classDef updatedComponent fill:#fff9c4,stroke:#f57c00,stroke-width:3px
    classDef protectedComponent fill:#e3f2fd,stroke:#1976d2,stroke-width:2px
    classDef sharedComponent fill:#f3e5f5,stroke:#7b1fa2,stroke-width:2px
    classDef publicComponent fill:#fce4ec,stroke:#c2185b,stroke-width:2px
    
    class PasswordReqPage,PasswordResetPage newComponent
    class LoginPage,ChangePasswordPage updatedComponent
    class LeadsPage,CustomersPage,EventsPage,FailedPage,ConfigPage protectedComponent
    class Header,Sidebar,ErrorMsg,UserMenu,SidebarNav sharedComponent
    class LoginForm,PasswordReqForm,PasswordResetForm publicComponent
```

---

# Diagram Architektury UI - Moduł Autentykacji LMS

> **Źródło:** PRD (US-002, US-008), auth-spec.md, istniejący codebase  
> **Data utworzenia:** 2025-10-15  
> **Status:** Specyfikacja wdrożenia

---

## Legenda Kolorów

- 🟢 **Zielony** - Nowe komponenty do utworzenia
- 🟡 **Żółty** - Istniejące komponenty wymagające aktualizacji
- 🔵 **Niebieski** - Strony chronione autentykacją
- 🟣 **Fioletowy** - Komponenty współdzielone (header, sidebar)
- 🔴 **Różowy** - Formularze publiczne

---

## Kluczowe Decyzje Architektoniczne UI

### 1. Podział na Bloki Layout

**base.html.twig** wykorzystuje dwa bloki:
- `{% block body %}` - dla treści wymagającej autentykacji (dashboard, profil)
- `{% block unauthenticated_body %}` - dla stron publicznych (logowanie, reset hasła)

**Zalety:**
- Wyraźne rozdzielenie stron publicznych od chronionych
- Strony publiczne nie ładują niepotrzebnych komponentów (sidebar, header z menu)
- Łatwe utrzymanie i testowanie

### 2. Komponenty Współdzielone

**components/header.html.twig:**
- Wyświetla się tylko dla zalogowanych użytkowników
- Menu użytkownika z warunkami: `{% if is_granted('ROLE_ADMIN') %}`
- Avatar z inicjałami dynamicznie generowany z nazwy użytkownika

**components/sidebar.html.twig:**
- Nawigacja warunkowa według ról
- Link "Konfiguracja" widoczny tylko dla `ROLE_ADMIN`
- Aktywna pozycja menu podświetlona

### 3. Formularze Autentykacji

Wszystkie formularze zawierają:
- **CSRF token** - ochrona przed atakami CSRF
- **Walidacja HTML5** - podstawowa walidacja po stronie przeglądarki
- **Fluent Design System** - spójny wygląd z resztą aplikacji
- **Komunikaty błędów** - jasne informacje dla użytkownika

### 4. Przepływ Nawigacji

**Scenariusz 1: Niezalogowany użytkownik**
```
Wejście na /leads → Sprawdzenie auth → Przekierowanie na /login → Logowanie → Dashboard /leads
```

**Scenariusz 2: Zalogowany użytkownik**
```
Header menu → Zmień hasło → /profile/change-password → Zmiana hasła → Powrót do dashboard
```

**Scenariusz 3: Reset hasła**
```
/login → Link "Nie pamiętasz hasła?" → /password/request → Email → Link w emailu → /password/reset/{token} → /login
```

### 5. Kontrola Dostępu w UI

**Warunkowe wyświetlanie elementów:**

```twig
{# Przycisk edycji - tylko dla CALL_CENTER i ADMIN #}
{% if is_granted('ROLE_CALL_CENTER') %}
    <fluent-button>Edytuj</fluent-button>
{% endif %}

{# Link konfiguracji - tylko dla ADMIN #}
{% if is_granted('ROLE_ADMIN') %}
    <a href="{{ path('config_index') }}">Konfiguracja</a>
{% endif %}
```

### 6. Responsywność i UX

- **Fluent Design System** - Microsoft Fluent UI Web Components
- **HTMX** - dynamiczne ładowanie bez pełnego odświeżania strony
- **Fokus na dostępność** - labels dla wszystkich pól, autofocus na pierwszym polu
- **Mobile-first** - responsywny layout dostosowany do urządzeń mobilnych

### 7. Walidacja i Obsługa Błędów

**Walidacja kaskadowa:**
1. **HTML5** - podstawowa walidacja (required, email, minlength)
2. **JavaScript** - porównanie haseł przed wysłaniem
3. **Backend** - pełna walidacja Symfony Validator
4. **UI Feedback** - wyświetlenie błędów w komponencie `_error_message.html.twig`

---

## Mapowanie Komponentów na Kontrolery

| Komponent UI | Kontroler | Metoda | Rola wymagana |
|--------------|-----------|--------|---------------|
| `auth/login.html.twig` | `AuthController` | `login()` | PUBLIC |
| `auth/password_request.html.twig` | `PasswordResetController` | `request()` | PUBLIC |
| `auth/password_reset.html.twig` | `PasswordResetController` | `reset()` | PUBLIC |
| `auth/change_password.html.twig` | `ProfileController` | `changePassword()` | ROLE_USER |
| `leads/index.html.twig` | `LeadsViewController` | `index()` | ROLE_USER |
| `customers/index.html.twig` | `CustomersViewController` | `index()` | ROLE_USER |
| `events/index.html.twig` | `EventsViewController` | `index()` | ROLE_USER |
| `failed_deliveries/index.html.twig` | `FailedDeliveriesController` | `index()` | ROLE_USER |
| `config/index.html.twig` | `ConfigViewController` | `index()` | ROLE_ADMIN |

---

## Checklist Implementacji UI

### Strony do Utworzenia
- [ ] `templates/auth/password_request.html.twig`
- [ ] `templates/auth/password_reset.html.twig`
- [ ] `templates/emails/password_reset.html.twig`

### Strony do Aktualizacji
- [ ] `templates/auth/login.html.twig` - dodać obsługę błędów, CSRF, remember me, link do resetu
- [ ] `templates/auth/change_password.html.twig` - zmienić z stub na funkcjonalny formularz

### Komponenty do Weryfikacji
- [ ] `templates/components/header.html.twig` - sprawdzić czy poprawnie obsługuje `app.user`
- [ ] `templates/components/sidebar.html.twig` - dodać warunkowe wyświetlanie dla ról
- [ ] `templates/components/_error_message.html.twig` - używany w formularzach auth

### Chronione Strony
- [ ] Odkomentować `#[IsGranted('ROLE_USER')]` w kontrolerach:
  - `LeadsViewController`
  - `CustomersViewController`
  - `EventsViewController`
  - `FailedDeliveriesController`
- [ ] Odkomentować `#[IsGranted('ROLE_ADMIN')]` w `ConfigViewController`

---

**Koniec dokumentacji UI**

