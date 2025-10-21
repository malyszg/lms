# Specyfikacja Architektury Modułu Autentykacji

> **Status dokumentu:** Zaktualizowano - USUNIĘTO funkcjonalność resetowania hasła  
> **Data aktualizacji:** 2025-10-16  
> **Ostatnia zmiana:** Usunięto funkcjonalność "Nie pamiętasz hasła?" - administrator resetuje hasło przez CLI  
> **Zmiany:** 
> - Identyfikator logowania: email
> - **USUNIĘTO:** Password reset przez email, PasswordResetToken, PasswordResetService, EmailService
> - **DODANO:** Komenda CLI `app:reset-user-password` dla administratorów
> - Rozwiązano sprzeczności z PRD dotyczące autoryzacji API
> - Usunięto nadmiarowe założenia o rejestracji użytkowników  
> **Patrz:** Sekcja 8 "Analiza zgodności z PRD i rozwiązane sprzeczności"

> ⚠️ **UWAGA:** Wszystkie sekcje dotyczące password reset (strony, serwisy, modele) są nieaktualne i zostały usunięte z kodu.  
> **Nowa funkcjonalność:** Admin resetuje hasło użytkownika przez komendę: `php bin/console app:reset-user-password email@example.com NewPassword`

---

## 1. ARCHITEKTURA INTERFEJSU UŻYTKOWNIKA

### 1.1 Struktura Szablonów i Layoutów

#### 1.1.1 Layout bazowy (base.html.twig)

**Istniejący stan:**
- Szablon `base.html.twig` posiada dwa bloki: `{% block body %}` (dla treści autoryzowanych) oraz `{% block unauthenticated_body %}` (dla stron publicznych)
- Struktura autoryzowana zawiera sidebar, header z informacjami o użytkowniku oraz główną przestrzeń treści
- Header wykorzystuje `app.user` i funkcje `is_granted()` do wyświetlania informacji o użytkowniku i roli

**Zmiany wymagane:**
- Brak zmian w strukturze layoutu - istniejący podział jest wystarczający
- Należy zapewnić, że wszystkie chronione strony korzystają z bloku `{% block body %}`
- Strony logowania i resetowania hasła korzystają z `{% block unauthenticated_body %}`

#### 1.1.2 Komponenty nagłówka (header.html.twig)

**Istniejący stan:**
- Header wyświetla inicjały użytkownika, nazwę i rolę
- Menu użytkownika zawiera opcje "Zmień hasło" i "Wyloguj"
- Wykorzystuje warunki Twig `is_granted()` do określenia wyświetlanej roli

**Rozszerzenia:**
- Dodać wyświetlanie pełnego imienia i nazwiska użytkownika (jeśli dostępne) zamiast samej nazwy użytkownika
- Zachować istniejącą strukturę dropdownu menu użytkownika
- Dodać wizualne oznaczenie statusu sesji (czas do wygaśnięcia)

### 1.2 Strony i Formularze Autentykacji

#### 1.2.1 Strona logowania (auth/login.html.twig)

**Istniejący stan:**
- Formularz z polami `_username` i `_password`
- Wykorzystuje Fluent Design System
- Brak obsługi komunikatów błędów i przekierowań po udanym logowaniu

**Wymagane rozszerzenia:**

**A. Wyświetlanie błędów walidacji i autentykacji:**
```twig
{% if error %}
    <div class="fluent-message-bar" appearance="error">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
            <path d="M8 1c3.86 0 7 3.14 7 7s-3.14 7-7 7-7-3.14-7-7 3.14-7 7-7z"/>
        </svg>
        <span>{{ error.messageKey|trans(error.messageData, 'security') }}</span>
    </div>
{% endif %}
```

**Uwaga:** Pole `_username` w formularzu to standardowa nazwa parametru Symfony Security, ale użytkownik wprowadza w nim **adres email**.

**B. Pole "Zapamiętaj mnie":**
```twig
<div class="fluent-checkbox-group">
    <input type="checkbox" 
           id="remember_me" 
           name="_remember_me" 
           class="fluent-checkbox">
    <label for="remember_me">Zapamiętaj mnie</label>
</div>
```

**C. Link do resetowania hasła:**
```twig
<div style="text-align: right; margin-top: var(--fluent-spacing-m);">
    <a href="{{ path('auth_password_request') }}" 
       class="fluent-link">
        Nie pamiętasz hasła?
    </a>
</div>
```

**D. Ukryte pole CSRF:**
```twig
<input type="hidden" name="_csrf_token" value="{{ csrf_token('authenticate') }}">
```

**E. Pole przekierowania (target path):**
```twig
{% if app.request.query.get('redirect') %}
    <input type="hidden" name="_target_path" value="{{ app.request.query.get('redirect') }}">
{% endif %}
```

#### 1.2.2 Strona resetowania hasła (auth/password_request.html.twig)

**Nowy szablon do utworzenia:**

**Przeznaczenie:**
- Formularz do żądania resetu hasła przez podanie adresu email
- Wykorzystuje layout `unauthenticated_body`

**Struktura:**
```twig
{% extends 'base.html.twig' %}

{% block title %}Resetowanie hasła - LMS{% endblock %}

{% block unauthenticated_body %}
<div class="auth-container">
    <div class="fluent-card">
        <h1>Resetowanie hasła</h1>
        <p>Podaj adres email powiązany z Twoim kontem. Wyślemy Ci instrukcje resetowania hasła.</p>
        
        {% if success_message %}
            <div class="fluent-message-bar" appearance="success">
                {{ success_message }}
            </div>
        {% endif %}
        
        <form method="post" action="{{ path('auth_password_request') }}">
            <div class="fluent-form-group">
                <label for="email" class="fluent-label">Email</label>
                <input type="email" 
                       class="fluent-input" 
                       id="email" 
                       name="_username" 
                       placeholder="twoj.email@example.com"
                       required 
                       autofocus>
            </div>
            
            <input type="hidden" name="_csrf_token" value="{{ csrf_token('password_reset') }}">
            
            <fluent-button type="submit" appearance="accent">
                Wyślij link resetujący
            </fluent-button>
            
            <div style="text-align: center; margin-top: var(--fluent-spacing-l);">
                <a href="{{ path('auth_login') }}" class="fluent-link">
                    Powrót do logowania
                </a>
            </div>
        </form>
    </div>
</div>
{% endblock %}
```

#### 1.2.3 Strona potwierdzenia resetu hasła (auth/password_reset.html.twig)

**Nowy szablon do utworzenia:**

**Przeznaczenie:**
- Formularz do wprowadzenia nowego hasła po kliknięciu w link z emaila
- Wymaga tokenu resetującego przekazanego w URLu

**Struktura:**
```twig
{% extends 'base.html.twig' %}

{% block title %}Nowe hasło - LMS{% endblock %}

{% block unauthenticated_body %}
<div class="auth-container">
    <div class="fluent-card">
        <h1>Ustaw nowe hasło</h1>
        
        {% if error %}
            <div class="fluent-message-bar" appearance="error">
                {{ error }}
            </div>
        {% endif %}
        
        <form method="post" action="{{ path('auth_password_reset', {token: token}) }}">
            <div class="fluent-form-group">
                <label for="password" class="fluent-label">Nowe hasło</label>
                <input type="password" 
                       class="fluent-input" 
                       id="password" 
                       name="password" 
                       minlength="8"
                       required>
                <small class="fluent-hint">Minimum 8 znaków</small>
            </div>
            
            <div class="fluent-form-group">
                <label for="password_confirm" class="fluent-label">Potwierdź hasło</label>
                <input type="password" 
                       class="fluent-input" 
                       id="password_confirm" 
                       name="password_confirm" 
                       minlength="8"
                       required>
            </div>
            
            <input type="hidden" name="_csrf_token" value="{{ csrf_token('password_reset') }}">
            
            <fluent-button type="submit" appearance="accent">
                Ustaw hasło
            </fluent-button>
        </form>
    </div>
</div>
{% endblock %}
```

#### 1.2.4 Strona zmiany hasła (auth/change_password.html.twig)

**Istniejący stan:**
- Prosty komunikat informacyjny
- Wykorzystuje layout autoryzowany (`{% block body %}`)

**Wymagane rozszerzenia:**

**Funkcjonalny formularz zmiany hasła:**
```twig
{% extends 'base.html.twig' %}

{% block title %}Zmiana hasła - LMS{% endblock %}

{% block page_title %}Zmiana hasła{% endblock %}

{% block body %}
<div style="max-width: 800px; margin: 0 auto;">
    <div class="fluent-card">
        {% if success_message %}
            <div class="fluent-message-bar" appearance="success">
                {{ success_message }}
            </div>
        {% endif %}
        
        {% if error %}
            <div class="fluent-message-bar" appearance="error">
                {{ error }}
            </div>
        {% endif %}
        
        <form method="post" action="{{ path('profile_change_password') }}">
            <div class="fluent-form-group">
                <label for="current_password" class="fluent-label">Obecne hasło</label>
                <input type="password" 
                       class="fluent-input" 
                       id="current_password" 
                       name="current_password" 
                       required 
                       autofocus>
            </div>
            
            <div class="fluent-form-group">
                <label for="new_password" class="fluent-label">Nowe hasło</label>
                <input type="password" 
                       class="fluent-input" 
                       id="new_password" 
                       name="new_password" 
                       minlength="8"
                       required>
                <small class="fluent-hint">Minimum 8 znaków</small>
            </div>
            
            <div class="fluent-form-group">
                <label for="new_password_confirm" class="fluent-label">Potwierdź nowe hasło</label>
                <input type="password" 
                       class="fluent-input" 
                       id="new_password_confirm" 
                       name="new_password_confirm" 
                       minlength="8"
                       required>
            </div>
            
            <input type="hidden" name="_csrf_token" value="{{ csrf_token('change_password') }}">
            
            <div style="display: flex; gap: var(--fluent-spacing-m);">
                <fluent-button type="submit" appearance="accent">
                    Zmień hasło
                </fluent-button>
                <fluent-button type="button" appearance="neutral" onclick="history.back()">
                    Anuluj
                </fluent-button>
            </div>
        </form>
    </div>
</div>
{% endblock %}
```

### 1.3 Walidacja i Komunikaty Błędów

#### 1.3.1 Scenariusze walidacji po stronie przeglądarki

**Walidacja HTML5:**
- Pole email: `type="email"` + `required`
- Pole hasło: `type="password"` + `required` + `minlength="8"`
- Potwierdzenie hasła: porównanie z głównym polem hasła przez JavaScript

**JavaScript do walidacji zgodności haseł:**
```javascript
// Dodać do public/js/app.js
document.addEventListener('DOMContentLoaded', function() {
    const passwordFields = document.querySelectorAll('input[type="password"][name$="_confirm"]');
    
    passwordFields.forEach(confirmField => {
        const form = confirmField.closest('form');
        const passwordField = form.querySelector('input[type="password"]:not([name$="_confirm"])');
        
        form.addEventListener('submit', function(e) {
            if (passwordField.value !== confirmField.value) {
                e.preventDefault();
                showToast('Hasła nie są identyczne', 'error');
                confirmField.focus();
            }
        });
    });
});
```

#### 1.3.2 Komunikaty błędów po stronie serwera

**Typy błędów do obsługi:**

| Scenariusz | Komunikat | Status HTTP |
|------------|-----------|-------------|
| Nieprawidłowe dane logowania | "Nieprawidłowa nazwa użytkownika lub hasło" | - (redirect) |
| Konto nieaktywne | "Twoje konto zostało dezaktywowane. Skontaktuj się z administratorem." | - (redirect) |
| Token CSRF nieprawidłowy | "Sesja wygasła. Spróbuj ponownie." | - (redirect) |
| Email nie istnieje (reset hasła) | "Jeśli konto istnieje, wysłaliśmy instrukcje resetowania hasła." | 200 OK |
| Token resetujący wygasł | "Link resetujący hasło wygasł. Poproś o nowy." | - (redirect) |
| Hasło za słabe | "Hasło musi mieć minimum 8 znaków i zawierać przynajmniej jedną cyfrę." | - (redirect) |
| Obecne hasło nieprawidłowe | "Obecne hasło jest nieprawidłowe." | - (redirect) |
| Nowe hasło takie samo jak stare | "Nowe hasło musi różnić się od obecnego." | - (redirect) |

**Uwaga:** Dla bezpieczeństwa, przy resetowaniu hasła zawsze zwracamy tę samą odpowiedź niezależnie od tego, czy email istnieje w systemie.

### 1.4 Przepływ Użytkownika (User Flows)

#### 1.4.1 Przepływ logowania

```
1. Użytkownik wchodzi na chronioną stronę (np. /leads)
   ↓
2. System sprawdza autentykację
   ↓
3a. Użytkownik NIE jest zalogowany
    ↓
    Przekierowanie na /login?redirect=/leads
    ↓
    Wypełnienie formularza logowania
    ↓
    POST /login z _username, _password, _csrf_token
    ↓
    3a1. Logowanie poprawne
         ↓
         Przekierowanie na /leads (lub ścieżkę z parametru redirect)
         ↓
         Użytkownik ma dostęp do panelu
    
    3a2. Logowanie niepoprawne
         ↓
         Przekierowanie na /login z parametrem error
         ↓
         Wyświetlenie komunikatu błędu
         ↓
         Logowanie próby logowania w tabeli events

3b. Użytkownik JEST zalogowany
    ↓
    Wyświetlenie strony /leads
```

#### 1.4.2 Przepływ resetowania hasła

```
1. Użytkownik klika "Nie pamiętasz hasła?" na stronie logowania
   ↓
2. Przejście na /password/request
   ↓
3. Wprowadzenie adresu email
   ↓
4. POST /password/request z email, _csrf_token
   ↓
5. System sprawdza czy email istnieje
   ↓
6a. Email istnieje
    ↓
    Generowanie tokenu resetującego (ważny 1 godzinę)
    ↓
    Zapisanie tokenu w tabeli password_reset_tokens
    ↓
    Wysłanie emaila z linkiem: /password/reset/{token}
    ↓
    Wyświetlenie komunikatu: "Jeśli konto istnieje, wysłaliśmy instrukcje"

6b. Email NIE istnieje
    ↓
    Wyświetlenie tego samego komunikatu: "Jeśli konto istnieje, wysłaliśmy instrukcje"
    ↓
    (dla bezpieczeństwa nie ujawniamy, czy email jest w systemie)

7. Użytkownik klika link w emailu
   ↓
8. Przejście na /password/reset/{token}
   ↓
9. System sprawdza token
   ↓
10a. Token prawidłowy i ważny
     ↓
     Wyświetlenie formularza nowego hasła
     ↓
     POST /password/reset/{token} z password, password_confirm, _csrf_token
     ↓
     Zmiana hasła w bazie
     ↓
     Usunięcie tokenu z tabeli
     ↓
     Przekierowanie na /login z komunikatem sukcesu

10b. Token nieprawidłowy lub wygasły
     ↓
     Wyświetlenie błędu
     ↓
     Link do ponownego żądania resetu
```

#### 1.4.3 Przepływ zmiany hasła (dla zalogowanego użytkownika)

```
1. Zalogowany użytkownik klika "Zmień hasło" w menu
   ↓
2. Przejście na /profile/change-password
   ↓
3. Wypełnienie formularza:
   - Obecne hasło
   - Nowe hasło
   - Potwierdzenie nowego hasła
   ↓
4. POST /profile/change-password z danymi
   ↓
5. Walidacja po stronie serwera
   ↓
6a. Walidacja pomyślna
    ↓
    Zapisanie nowego hasła
    ↓
    Logowanie zdarzenia w events
    ↓
    Przekierowanie z komunikatem sukcesu

6b. Walidacja niepomyślna
    ↓
    Ponowne wyświetlenie formularza z błędami
```

#### 1.4.4 Przepływ wylogowania

```
1. Użytkownik klika "Wyloguj" w menu użytkownika
   ↓
2. GET /logout
   ↓
3. Symfony Security przechwytuje żądanie
   ↓
4. Usunięcie sesji użytkownika
   ↓
5. Logowanie zdarzenia wylogowania w events
   ↓
6. Przekierowanie na /login
   ↓
7. Wyświetlenie komunikatu: "Zostałeś wylogowany"
```

---

## 2. LOGIKA BACKENDOWA

### 2.1 Model Danych - Encja User

**Lokalizacja:** `src/Model/User.php`

**Implementacja interfejsów:**
- `Symfony\Component\Security\Core\User\UserInterface` - podstawowa funkcjonalność użytkownika Symfony
- `Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface` - obsługa haseł

**Struktura klasy:**

```php
<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * User entity
 * Represents a system user with authentication capabilities
 */
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    private ?int $id = null;
    private string $email;
    private string $username;
    private string $password; // hashed
    private array $roles = [];
    private bool $isActive = true;
    private ?string $firstName = null;
    private ?string $lastName = null;
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;
    private ?DateTimeInterface $lastLoginAt = null;

    public function getId(): ?int;
    public function getEmail(): string;
    public function setEmail(string $email): self;
    public function getUsername(): string;
    public function setUsername(string $username): self;
    public function getPassword(): string;
    public function setPassword(string $password): self;
    public function getRoles(): array;
    public function setRoles(array $roles): self;
    public function addRole(string $role): self;
    public function removeRole(string $role): self;
    public function isActive(): bool;
    public function setIsActive(bool $isActive): self;
    public function getFirstName(): ?string;
    public function setFirstName(?string $firstName): self;
    public function getLastName(): ?string;
    public function setLastName(?string $lastName): self;
    public function getFullName(): string;
    public function getCreatedAt(): DateTimeInterface;
    public function getUpdatedAt(): DateTimeInterface;
    public function getLastLoginAt(): ?DateTimeInterface;
    public function setLastLoginAt(DateTimeInterface $lastLoginAt): self;
    
    // UserInterface methods
    public function getUserIdentifier(): string; // returns email
    public function eraseCredentials(): void;
}
```

**Pola i ich przeznaczenie:**

| Pole | Typ | Wymagane | Opis |
|------|-----|----------|------|
| `id` | int | Auto | Klucz główny |
| `email` | string(255) | Tak | Email użytkownika (unikalny) - używany do logowania |
| `username` | string(100) | Tak | Nazwa użytkownika (unikalna) - nazwa wyświetlana |
| `password` | string(255) | Tak | Hash hasła (bcrypt) |
| `roles` | json | Tak | Tablica ról (default: ['ROLE_USER']) |
| `is_active` | boolean | Tak | Status aktywności konta (default: true) |
| `first_name` | string(100) | Nie | Imię użytkownika |
| `last_name` | string(100) | Nie | Nazwisko użytkownika |
| `created_at` | datetime | Tak | Data utworzenia |
| `updated_at` | datetime | Tak | Data ostatniej modyfikacji |
| `last_login_at` | datetime | Nie | Data ostatniego logowania |

### 2.2 Mapowanie Doctrine (XML)

**Lokalizacja:** `config/doctrine/User.orm.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
                  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                  https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd">

    <entity name="App\Model\User" table="users">
        <id name="id" type="integer" column="id">
            <generator strategy="AUTO"/>
        </id>

        <field name="email" type="string" length="255" nullable="false" unique="true"/>
        <field name="username" type="string" length="100" nullable="false" unique="true"/>
        <field name="password" type="string" length="255" nullable="false"/>
        <field name="roles" type="json" nullable="false"/>
        <field name="isActive" type="boolean" nullable="false" column="is_active">
            <options>
                <option name="default">1</option>
            </options>
        </field>
        <field name="firstName" type="string" length="100" nullable="true" column="first_name"/>
        <field name="lastName" type="string" length="100" nullable="true" column="last_name"/>
        <field name="createdAt" type="datetime" nullable="false" column="created_at">
            <options>
                <option name="default">CURRENT_TIMESTAMP</option>
            </options>
        </field>
        <field name="updatedAt" type="datetime" nullable="false" column="updated_at">
            <options>
                <option name="default">CURRENT_TIMESTAMP</option>
            </options>
        </field>
        <field name="lastLoginAt" type="datetime" nullable="true" column="last_login_at"/>

        <indexes>
            <index name="idx_email" columns="email"/>
            <index name="idx_username" columns="username"/>
            <index name="idx_is_active" columns="is_active"/>
        </indexes>

        <lifecycle-callbacks>
            <lifecycle-callback type="prePersist" method="onPrePersist"/>
            <lifecycle-callback type="preUpdate" method="onPreUpdate"/>
        </lifecycle-callbacks>
    </entity>
</doctrine-mapping>
```

### 2.3 Model Danych - Encja PasswordResetToken

**Lokalizacja:** `src/Model/PasswordResetToken.php`

**Przeznaczenie:** Przechowywanie tokenów do resetowania haseł

```php
<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeInterface;

/**
 * Password reset token entity
 * Stores temporary tokens for password reset functionality
 */
class PasswordResetToken
{
    private ?int $id = null;
    private User $user;
    private string $token; // unique, indexed
    private DateTimeInterface $expiresAt;
    private DateTimeInterface $createdAt;
    private bool $isUsed = false;

    public function __construct(User $user, string $token, DateTimeInterface $expiresAt);
    public function getId(): ?int;
    public function getUser(): User;
    public function getToken(): string;
    public function getExpiresAt(): DateTimeInterface;
    public function isExpired(): bool;
    public function isUsed(): bool;
    public function markAsUsed(): self;
    public function getCreatedAt(): DateTimeInterface;
}
```

**Mapowanie Doctrine:** `config/doctrine/PasswordResetToken.orm.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
                  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                  https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd">

    <entity name="App\Model\PasswordResetToken" table="password_reset_tokens">
        <id name="id" type="integer" column="id">
            <generator strategy="AUTO"/>
        </id>

        <many-to-one field="user" target-entity="App\Model\User">
            <join-column name="user_id" referenced-column-name="id" nullable="false" on-delete="CASCADE"/>
        </many-to-one>

        <field name="token" type="string" length="64" nullable="false" unique="true"/>
        <field name="expiresAt" type="datetime" nullable="false" column="expires_at"/>
        <field name="createdAt" type="datetime" nullable="false" column="created_at">
            <options>
                <option name="default">CURRENT_TIMESTAMP</option>
            </options>
        </field>
        <field name="isUsed" type="boolean" nullable="false" column="is_used">
            <options>
                <option name="default">0</option>
            </options>
        </field>

        <indexes>
            <index name="idx_token" columns="token"/>
            <index name="idx_expires_at" columns="expires_at"/>
            <index name="idx_user_id" columns="user_id"/>
        </indexes>

        <lifecycle-callbacks>
            <lifecycle-callback type="prePersist" method="onPrePersist"/>
        </lifecycle-callbacks>
    </entity>
</doctrine-mapping>
```

### 2.4 Rozszerzenie modelu Event

**Cel:** Logowanie zdarzeń związanych z autentykacją

**Nowe typy zdarzeń do dodania w EventService:**

```php
// W klasie EventService, rozszerzyć metody o obsługę authentication events

public function logLoginAttempt(
    string $username,
    bool $success,
    ?string $ipAddress = null,
    ?string $userAgent = null,
    ?string $failureReason = null
): void;

public function logLogout(
    int $userId,
    string $username,
    ?string $ipAddress = null,
    ?string $userAgent = null
): void;

public function logPasswordChange(
    int $userId,
    string $username,
    ?string $ipAddress = null
): void;

public function logPasswordResetRequest(
    string $email,
    bool $emailExists,
    ?string $ipAddress = null
): void;

public function logPasswordReset(
    int $userId,
    string $username,
    ?string $ipAddress = null
): void;
```

**Struktura eventu w bazie:**

| Pole | Wartość przykładowa |
|------|---------------------|
| event_type | 'login_attempt', 'login_success', 'login_failure', 'logout', 'password_change', 'password_reset_request', 'password_reset' |
| details | JSON z dodatkowymi informacjami (user_id, username, ip_address, user_agent, reason) |

### 2.5 Kontrolery

#### 2.5.1 AuthController - Rozszerzenie

**Lokalizacja:** `src/Controller/AuthController.php`

**Aktualna implementacja:**
- Prosty stub z metodami `login()` i `logout()`

**Rozszerzona implementacja:**

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Leads\EventServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Authentication Controller
 * Handles user login, logout, and authentication-related operations
 */
class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthenticationUtils $authenticationUtils,
        private readonly EventServiceInterface $eventService
    ) {}

    /**
     * Display login form and handle authentication
     * Security component intercepts POST requests
     */
    #[Route('/login', name: 'auth_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        // If already logged in, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('leads_index');
        }

        // Get authentication error (if any)
        $error = $this->authenticationUtils->getLastAuthenticationError();
        
        // Get last username entered
        $lastUsername = $this->authenticationUtils->getLastUsername();

        // Log failed login attempt
        if ($error && $request->isMethod('POST')) {
            $this->eventService->logLoginAttempt(
                username: $lastUsername,
                success: false,
                ipAddress: $request->getClientIp(),
                userAgent: $request->headers->get('User-Agent'),
                failureReason: $error->getMessageKey()
            );
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }
    
    /**
     * Logout handler
     * This method will be intercepted by security component
     */
    #[Route('/logout', name: 'auth_logout', methods: ['GET'])]
    public function logout(): never
    {
        // This method can be blank - it will be intercepted by the logout key on security firewall
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
```

#### 2.5.2 PasswordResetController - Nowy kontroler

**Lokalizacja:** `src/Controller/PasswordResetController.php`

**Odpowiedzialność:**
- Żądanie resetu hasła (formularz z emailem)
- Wysłanie emaila z linkiem resetującym
- Formularz ustawienia nowego hasła
- Walidacja tokenu i zapisanie nowego hasła

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\PasswordResetServiceInterface;
use App\DTO\PasswordResetRequestDto;
use App\DTO\PasswordResetDto;
use App\Exception\InvalidTokenException;
use App\Exception\TokenExpiredException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Password Reset Controller
 * Handles password reset request and reset operations
 */
class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly PasswordResetServiceInterface $passwordResetService
    ) {}

    /**
     * Display password reset request form
     * POST: Send reset email
     */
    #[Route('/password/request', name: 'auth_password_request', methods: ['GET', 'POST'])]
    public function request(Request $request): Response;

    /**
     * Display password reset form (with token)
     * POST: Reset password
     */
    #[Route('/password/reset/{token}', name: 'auth_password_reset', methods: ['GET', 'POST'])]
    public function reset(string $token, Request $request): Response;
}
```

#### 2.5.3 ProfileController - Nowy kontroler

**Lokalizacja:** `src/Controller/ProfileController.php`

**Odpowiedzialność:**
- Zmiana hasła dla zalogowanego użytkownika
- Ewentualnie inne operacje profilowe w przyszłości

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\PasswordChangeServiceInterface;
use App\DTO\ChangePasswordDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * User Profile Controller
 * Handles user profile operations (password change, etc.)
 */
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private readonly PasswordChangeServiceInterface $passwordChangeService
    ) {}

    /**
     * Change password for logged-in user
     */
    #[Route('/profile/change-password', name: 'profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request): Response;
}
```

### 2.6 Serwisy i Interfejsy

#### 2.6.1 PasswordResetServiceInterface

**Lokalizacja:** `src/Auth/PasswordResetServiceInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Auth;

use App\Model\User;

/**
 * Password Reset Service Interface
 * Handles password reset token generation and validation
 */
interface PasswordResetServiceInterface
{
    /**
     * Request password reset - generate token and send email
     * 
     * @param string $email User email
     * @param string|null $ipAddress Request IP address for logging
     * @return bool Always returns true (for security - don't reveal if email exists)
     */
    public function requestPasswordReset(string $email, ?string $ipAddress = null): bool;

    /**
     * Validate reset token
     * 
     * @param string $token Reset token
     * @return User User associated with token
     * @throws InvalidTokenException If token doesn't exist or already used
     * @throws TokenExpiredException If token has expired
     */
    public function validateResetToken(string $token): User;

    /**
     * Reset password using token
     * 
     * @param string $token Reset token
     * @param string $newPassword New password (plain text, will be hashed)
     * @param string|null $ipAddress Request IP address for logging
     * @return void
     * @throws InvalidTokenException If token doesn't exist or already used
     * @throws TokenExpiredException If token has expired
     */
    public function resetPassword(string $token, string $newPassword, ?string $ipAddress = null): void;

    /**
     * Clean up expired tokens (should be called periodically)
     * 
     * @return int Number of tokens removed
     */
    public function cleanupExpiredTokens(): int;
}
```

**Implementacja:** `src/Auth/PasswordResetService.php`

**Logika biznesowa:**
1. `requestPasswordReset()`:
   - Sprawdź czy email istnieje w bazie
   - Jeśli tak: generuj losowy token (64 znaki), zapisz w bazie z czasem wygaśnięcia (1 godzina), wyślij email
   - Jeśli nie: nic nie rób (ale zwróć true dla bezpieczeństwa)
   - Zaloguj próbę w EventService

2. `validateResetToken()`:
   - Znajdź token w bazie
   - Sprawdź czy nie wygasł
   - Sprawdź czy nie został już użyty
   - Zwróć powiązanego użytkownika

3. `resetPassword()`:
   - Waliduj token
   - Zahashuj nowe hasło
   - Zapisz nowe hasło użytkownika
   - Oznacz token jako użyty
   - Zaloguj w EventService

4. `cleanupExpiredTokens()`:
   - Usuń wszystkie tokeny starsze niż expires_at
   - Zwróć liczbę usuniętych

#### 2.6.2 PasswordChangeServiceInterface

**Lokalizacja:** `src/Auth/PasswordChangeServiceInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Auth;

use App\Model\User;
use App\Exception\InvalidPasswordException;

/**
 * Password Change Service Interface
 * Handles password changes for authenticated users
 */
interface PasswordChangeServiceInterface
{
    /**
     * Change password for authenticated user
     * 
     * @param User $user Current user
     * @param string $currentPassword Current password (plain text)
     * @param string $newPassword New password (plain text)
     * @param string|null $ipAddress Request IP address for logging
     * @return void
     * @throws InvalidPasswordException If current password is incorrect
     */
    public function changePassword(
        User $user, 
        string $currentPassword, 
        string $newPassword,
        ?string $ipAddress = null
    ): void;
}
```

**Implementacja:** `src/Auth/PasswordChangeService.php`

**Logika biznesowa:**
1. Zweryfikuj obecne hasło używając `PasswordHasherInterface`
2. Sprawdź czy nowe hasło różni się od obecnego
3. Zahashuj nowe hasło
4. Zapisz w bazie
5. Zaloguj w EventService

#### 2.6.3 UserServiceInterface

**Lokalizacja:** `src/Auth/UserServiceInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Auth;

use App\Model\User;

/**
 * User Service Interface
 * Handles user management operations
 */
interface UserServiceInterface
{
    /**
     * Create a new user
     * 
     * @param string $email User email
     * @param string $username Username
     * @param string $plainPassword Plain text password (will be hashed)
     * @param array $roles User roles
     * @param string|null $firstName First name
     * @param string|null $lastName Last name
     * @return User Created user
     */
    public function createUser(
        string $email,
        string $username,
        string $plainPassword,
        array $roles = ['ROLE_USER'],
        ?string $firstName = null,
        ?string $lastName = null
    ): User;

    /**
     * Find user by email
     * 
     * @param string $email User email
     * @return User|null User or null if not found
     */
    public function findByEmail(string $email): ?User;

    /**
     * Find user by username
     * 
     * @param string $username Username
     * @return User|null User or null if not found
     */
    public function findByUsername(string $username): ?User;

    /**
     * Update last login timestamp
     * 
     * @param User $user User to update
     * @return void
     */
    public function updateLastLogin(User $user): void;
}
```

### 2.7 DTO (Data Transfer Objects)

#### 2.7.1 PasswordResetRequestDto

**Lokalizacja:** `src/DTO/PasswordResetRequestDto.php`

```php
<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Password Reset Request DTO
 * Used for requesting password reset
 */
class PasswordResetRequestDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email jest wymagany')]
        #[Assert\Email(message: 'Podaj prawidłowy adres email')]
        public readonly string $email
    ) {}
}
```

#### 2.7.2 PasswordResetDto

**Lokalizacja:** `src/DTO/PasswordResetDto.php`

```php
<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Password Reset DTO
 * Used for resetting password with token
 */
class PasswordResetDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Token jest wymagany')]
        public readonly string $token,
        
        #[Assert\NotBlank(message: 'Hasło jest wymagane')]
        #[Assert\Length(
            min: 8,
            minMessage: 'Hasło musi mieć minimum {{ limit }} znaków'
        )]
        public readonly string $password,
        
        #[Assert\NotBlank(message: 'Potwierdzenie hasła jest wymagane')]
        #[Assert\EqualTo(
            propertyPath: 'password',
            message: 'Hasła nie są identyczne'
        )]
        public readonly string $passwordConfirm
    ) {}
}
```

#### 2.7.3 ChangePasswordDto

**Lokalizacja:** `src/DTO/ChangePasswordDto.php`

```php
<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Change Password DTO
 * Used for changing password of authenticated user
 */
class ChangePasswordDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Obecne hasło jest wymagane')]
        public readonly string $currentPassword,
        
        #[Assert\NotBlank(message: 'Nowe hasło jest wymagane')]
        #[Assert\Length(
            min: 8,
            minMessage: 'Hasło musi mieć minimum {{ limit }} znaków'
        )]
        public readonly string $newPassword,
        
        #[Assert\NotBlank(message: 'Potwierdzenie hasła jest wymagane')]
        #[Assert\EqualTo(
            propertyPath: 'newPassword',
            message: 'Hasła nie są identyczne'
        )]
        public readonly string $newPasswordConfirm
    ) {}
}
```

### 2.8 Wyjątki

#### 2.8.1 InvalidTokenException

**Lokalizacja:** `src/Exception/InvalidTokenException.php`

```php
<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Invalid Token Exception
 * Thrown when password reset token is invalid or already used
 */
class InvalidTokenException extends \Exception
{
    public function __construct(string $message = 'Token resetujący hasło jest nieprawidłowy lub został już użyty')
    {
        parent::__construct($message);
    }
}
```

#### 2.8.2 TokenExpiredException

**Lokalizacja:** `src/Exception/TokenExpiredException.php`

```php
<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Token Expired Exception
 * Thrown when password reset token has expired
 */
class TokenExpiredException extends \Exception
{
    public function __construct(string $message = 'Link resetujący hasło wygasł')
    {
        parent::__construct($message);
    }
}
```

#### 2.8.3 InvalidPasswordException

**Lokalizacja:** `src/Exception/InvalidPasswordException.php`

```php
<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Invalid Password Exception
 * Thrown when current password is incorrect during password change
 */
class InvalidPasswordException extends \Exception
{
    public function __construct(string $message = 'Obecne hasło jest nieprawidłowe')
    {
        parent::__construct($message);
    }
}
```

---

## 3. SYSTEM AUTENTYKACJI

### 3.1 Konfiguracja symfony/security-bundle

#### 3.1.1 Plik security.yaml

**Lokalizacja:** `config/packages/security.yaml`

**Rozszerzona konfiguracja:**

```yaml
security:
    # Password hasher configuration
    password_hashers:
        App\Model\User:
            algorithm: auto
            cost: 12  # bcrypt cost parameter

    # User provider - load users from database
    providers:
        app_user_provider:
            entity:
                class: App\Model\User
                property: email  # Use email as login identifier

    # Firewalls configuration
    firewalls:
        # Dev firewall - allow access to profiler and debug tools
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        # Main firewall - handles authentication
        main:
            lazy: true
            provider: app_user_provider
            
            # Login form configuration
            form_login:
                login_path: auth_login
                check_path: auth_login
                enable_csrf: true
                default_target_path: leads_index
                always_use_default_target_path: false
                username_parameter: _username
                password_parameter: _password
                
            # Remember me functionality
            remember_me:
                secret: '%kernel.secret%'
                lifetime: 604800  # 1 week in seconds
                path: /
                always_remember_me: false
                remember_me_parameter: _remember_me
            
            # Logout configuration
            logout:
                path: auth_logout
                target: auth_login
                invalidate_session: true
                
            # Entry point - where to redirect unauthenticated users
            entry_point: form_login

    # Access control rules
    access_control:
        # Public routes (login, password reset)
        - { path: ^/login, roles: PUBLIC_ACCESS }
        - { path: ^/password, roles: PUBLIC_ACCESS }
        
        # Authenticated routes - require at least ROLE_USER
        - { path: ^/profile, roles: ROLE_USER }
        - { path: ^/leads, roles: ROLE_USER }
        - { path: ^/customers, roles: ROLE_USER }
        - { path: ^/events, roles: ROLE_USER }
        - { path: ^/failed-deliveries, roles: ROLE_USER }
        
        # Admin routes - require ROLE_ADMIN
        - { path: ^/config, roles: ROLE_ADMIN }
        
        # API routes - zgodnie z PRD 3.2 "Autoryzacja API przez tokeny"
        # W MVP pozostają publicznie dostępne (tokeny będą dodane w przyszłości)
        # TODO: Implementacja autoryzacji API przez tokeny (poza zakresem MVP autentykacji panelu)
        - { path: ^/api, roles: PUBLIC_ACCESS }

    # Role hierarchy
    role_hierarchy:
        ROLE_CALL_CENTER: ROLE_USER
        ROLE_BOK: ROLE_USER
        ROLE_ADMIN: [ROLE_CALL_CENTER, ROLE_BOK, ROLE_USER]
```

#### 3.1.2 Objaśnienie konfiguracji

**Password Hashers:**
- Używa algorytmu `auto` (domyślnie bcrypt dla PHP 8.3)
- Parametr `cost: 12` określa poziom złożoności hashowania (wyższy = bezpieczniejszy, ale wolniejszy)

**Providers:**
- `app_user_provider` ładuje użytkowników z bazy danych przez Doctrine
- Używa `email` jako identyfikatora logowania (nowoczesne podejście, łatwiejsze do zapamiętania)

**Firewall `main`:**
- `lazy: true` - nie ładuje użytkownika z sesji, dopóki nie jest potrzebny
- `form_login` - konfiguracja formularza logowania:
  - `enable_csrf: true` - ochrona przed atakami CSRF
  - `default_target_path` - gdzie przekierować po zalogowaniu
  - `always_use_default_target_path: false` - pozwala na przekierowanie do pierwotnie żądanej strony
- `remember_me` - funkcjonalność "Zapamiętaj mnie":
  - Token ważny przez 1 tydzień
  - Wymaga checkboxa w formularzu
- `logout` - konfiguracja wylogowania:
  - Przekierowanie na stronę logowania
  - Unieważnienie sesji

**Access Control:**
- Definiuje które ścieżki wymagają jakich ról
- Strony publiczne: login, reset hasła
- Strony dla zalogowanych: leady, klienci, eventy
- Strony dla adminów: konfiguracja

**Role Hierarchy:**
- `ROLE_CALL_CENTER` dziedziczy `ROLE_USER`
- `ROLE_BOK` dziedziczy `ROLE_USER`
- `ROLE_ADMIN` dziedziczy wszystkie role

### 3.2 System Ról i Uprawnień

#### 3.2.1 Definicja Ról

| Rola | Kod | Uprawnienia | Przeznaczenie |
|------|-----|-------------|---------------|
| Użytkownik | `ROLE_USER` | Podstawowy dostęp do systemu | Bazowa rola dla wszystkich zalogowanych użytkowników |
| Call Center | `ROLE_CALL_CENTER` | Pełny dostęp do leadów: przeglądanie, edycja preferencji, zmiana statusu, usuwanie | Pracownicy call center obsługujący leady |
| BOK | `ROLE_BOK` | Tylko odczyt: przeglądanie leadów i danych klientów bez możliwości edycji | Biuro Obsługi Klienta z dostępem read-only |
| Administrator | `ROLE_ADMIN` | Pełny dostęp + konfiguracja systemu, zarządzanie użytkownikami | Administratorzy systemu |

#### 3.2.2 Macierz Uprawnień

| Funkcjonalność | ROLE_USER | ROLE_CALL_CENTER | ROLE_BOK | ROLE_ADMIN |
|----------------|-----------|------------------|----------|------------|
| Logowanie | ✓ | ✓ | ✓ | ✓ |
| Przeglądanie leadów | ✓ | ✓ | ✓ | ✓ |
| Filtrowanie/sortowanie leadów | ✓ | ✓ | ✓ | ✓ |
| Edycja preferencji klienta | ✗ | ✓ | ✗ | ✓ |
| Zmiana statusu leada | ✗ | ✓ | ✗ | ✓ |
| Usuwanie leadów | ✗ | ✓ | ✗ | ✓ |
| Przeglądanie eventów | ✓ | ✓ | ✓ | ✓ |
| Ponowne wysyłanie do CDP | ✗ | ✗ | ✗ | ✓ |
| Konfiguracja systemu | ✗ | ✗ | ✗ | ✓ |
| Zarządzanie użytkownikami | ✗ | ✗ | ✗ | ✓ |

#### 3.2.3 Implementacja Kontroli Dostępu w Kontrolerach

**Kontroler leadów - wymaga roli USER:**

```php
#[IsGranted('ROLE_USER')]
class LeadsViewController extends AbstractController
{
    // Przeglądanie - dostępne dla wszystkich zalogowanych
    #[Route('/leads', name: 'leads_index')]
    public function index(): Response {}
    
    // Edycja - tylko CALL_CENTER i ADMIN
    #[Route('/leads/{id}/edit', name: 'leads_edit')]
    #[IsGranted('ROLE_CALL_CENTER')]
    public function edit(int $id): Response {}
    
    // Usuwanie - tylko CALL_CENTER i ADMIN
    #[Route('/leads/{id}/delete', name: 'leads_delete')]
    #[IsGranted('ROLE_CALL_CENTER')]
    public function delete(int $id): Response {}
}
```

**Kontroler konfiguracji - wymaga roli ADMIN:**

```php
#[IsGranted('ROLE_ADMIN')]
class ConfigViewController extends AbstractController
{
    // Wszystkie metody wymagają ROLE_ADMIN
}
```

**Warunkowe wyświetlanie elementów UI w Twig:**

```twig
{# Przycisk edycji - tylko dla CALL_CENTER #}
{% if is_granted('ROLE_CALL_CENTER') %}
    <fluent-button href="{{ path('leads_edit', {id: lead.id}) }}">
        Edytuj
    </fluent-button>
{% endif %}

{# Link do konfiguracji - tylko dla ADMIN #}
{% if is_granted('ROLE_ADMIN') %}
    <a href="{{ path('config_index') }}">Konfiguracja</a>
{% endif %}
```

### 3.3 Event Listener dla Autentykacji

#### 3.3.1 LoginSuccessListener

**Lokalizacja:** `src/EventListener/LoginSuccessListener.php`

**Przeznaczenie:**
- Logowanie udanych prób logowania
- Aktualizacja `last_login_at` w tabeli users
- Ewentualne inne operacje po zalogowaniu

```php
<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Auth\UserServiceInterface;
use App\Leads\EventServiceInterface;
use App\Model\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Login Success Listener
 * Handles operations after successful login
 */
#[AsEventListener(event: LoginSuccessEvent::class)]
class LoginSuccessListener
{
    public function __construct(
        private readonly EventServiceInterface $eventService,
        private readonly UserServiceInterface $userService,
        private readonly RequestStack $requestStack
    ) {}

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        
        if (!$user instanceof User) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        
        // Update last login timestamp
        $this->userService->updateLastLogin($user);
        
        // Log successful login
        $this->eventService->logLoginAttempt(
            username: $user->getUserIdentifier(),
            success: true,
            ipAddress: $request?->getClientIp(),
            userAgent: $request?->headers->get('User-Agent')
        );
    }
}
```

#### 3.3.2 LogoutSuccessListener

**Lokalizacja:** `src/EventListener/LogoutSuccessListener.php`

**Przeznaczenie:**
- Logowanie wylogowań użytkowników

```php
<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Leads\EventServiceInterface;
use App\Model\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Logout Success Listener
 * Handles operations after logout
 */
#[AsEventListener(event: LogoutEvent::class)]
class LogoutSuccessListener
{
    public function __construct(
        private readonly EventServiceInterface $eventService,
        private readonly RequestStack $requestStack
    ) {}

    public function __invoke(LogoutEvent $event): void
    {
        $token = $event->getToken();
        
        if (!$token) {
            return;
        }
        
        $user = $token->getUser();
        
        if (!$user instanceof User) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        
        // Log logout
        $this->eventService->logLogout(
            userId: $user->getId(),
            username: $user->getUserIdentifier(),
            ipAddress: $request?->getClientIp(),
            userAgent: $request?->headers->get('User-Agent')
        );
    }
}
```

### 3.4 Mechanizm Wysyłki Emaili

#### 3.4.1 EmailServiceInterface

**Lokalizacja:** `src/Service/EmailServiceInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Email Service Interface
 * Handles sending emails
 */
interface EmailServiceInterface
{
    /**
     * Send password reset email
     * 
     * @param string $recipientEmail Recipient email address
     * @param string $recipientName Recipient name
     * @param string $resetUrl Reset URL with token
     * @return void
     */
    public function sendPasswordResetEmail(
        string $recipientEmail,
        string $recipientName,
        string $resetUrl
    ): void;

    /**
     * Send password changed notification email
     * 
     * @param string $recipientEmail Recipient email address
     * @param string $recipientName Recipient name
     * @return void
     */
    public function sendPasswordChangedEmail(
        string $recipientEmail,
        string $recipientName
    ): void;
}
```

**Implementacja:** `src/Service/EmailService.php`

**Technologia:** Symfony Mailer (już dostępny w Symfony 7.3)

**Konfiguracja:** `config/packages/mailer.yaml`

```yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'
        
when@dev:
    framework:
        mailer:
            # W dev - wyślij do pliku zamiast SMTP
            dsn: 'null://null'
```

**Zmienne środowiskowe (.env):**

```bash
# Produkcja - SMTP
MAILER_DSN=smtp://user:pass@smtp.example.com:465

# Development - zapisz do pliku
MAILER_DSN=null://null
```

#### 3.4.2 Szablon Emaila - Resetowanie Hasła

**Lokalizacja:** `templates/emails/password_reset.html.twig`

```twig
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Resetowanie hasła - LMS</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #0078d4 0%, #5a67d8 100%); padding: 30px; text-align: center; color: white; border-radius: 8px 8px 0 0;">
        <h1 style="margin: 0;">Resetowanie hasła</h1>
    </div>
    
    <div style="background: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 8px 8px;">
        <p>Witaj {{ name }},</p>
        
        <p>Otrzymaliśmy prośbę o zresetowanie hasła do Twojego konta w systemie LMS.</p>
        
        <p>Aby ustawić nowe hasło, kliknij poniższy przycisk:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ resetUrl }}" 
               style="display: inline-block; padding: 12px 32px; background-color: #0078d4; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">
                Zresetuj hasło
            </a>
        </div>
        
        <p style="color: #666; font-size: 14px;">Lub skopiuj poniższy link do przeglądarki:</p>
        <p style="color: #0078d4; word-break: break-all; font-size: 14px;">{{ resetUrl }}</p>
        
        <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;">
        
        <p style="color: #999; font-size: 12px;">
            Link jest ważny przez 1 godzinę.<br>
            Jeśli nie prosiłeś o reset hasła, zignoruj tę wiadomość.
        </p>
        
        <p style="color: #999; font-size: 12px;">
            Pozdrawiamy,<br>
            Zespół LMS
        </p>
    </div>
</body>
</html>
```

### 3.5 Komendy Konsolowe

#### 3.5.1 CreateUserCommand

**Lokalizacja:** `src/Command/CreateUserCommand.php`

**Przeznaczenie:** Tworzenie użytkowników przez CLI (przydatne do inicjalnego setupu)

```php
<?php

declare(strict_types=1);

namespace App\Command;

use App\Auth\UserServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-user',
    description: 'Create a new user',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserServiceInterface $userService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addArgument('username', InputArgument::REQUIRED, 'Username')
            ->addArgument('password', InputArgument::REQUIRED, 'Password')
            ->addOption('role', 'r', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'User role(s)', ['ROLE_USER'])
            ->addOption('first-name', null, InputOption::VALUE_OPTIONAL, 'First name')
            ->addOption('last-name', null, InputOption::VALUE_OPTIONAL, 'Last name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $user = $this->userService->createUser(
                email: $input->getArgument('email'),
                username: $input->getArgument('username'),
                plainPassword: $input->getArgument('password'),
                roles: $input->getOption('role'),
                firstName: $input->getOption('first-name'),
                lastName: $input->getOption('last-name')
            );

            $io->success(sprintf('User "%s" created successfully with ID: %d', $user->getEmail(), $user->getId()));
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
```

**Przykładowe użycie:**

```bash
# Utworzenie podstawowego użytkownika
# Parametry: email username password
php bin/console app:create-user admin@example.com admin password123

# Utworzenie administratora
# Email używany do logowania, username do wyświetlania
php bin/console app:create-user admin@firma.pl "Jan Kowalski" password123 --role=ROLE_ADMIN --first-name=Jan --last-name=Kowalski

# Utworzenie pracownika call center
php bin/console app:create-user callcenter@firma.pl "Call Center" password123 --role=ROLE_CALL_CENTER
```

#### 3.5.2 CleanupPasswordTokensCommand

**Lokalizacja:** `src/Command/CleanupPasswordTokensCommand.php`

**Przeznaczenie:** Usuwanie wygasłych tokenów resetowania hasła (należy uruchamiać cron-em)

```php
<?php

declare(strict_types=1);

namespace App\Command;

use App\Auth\PasswordResetServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-password-tokens',
    description: 'Remove expired password reset tokens',
)]
class CleanupPasswordTokensCommand extends Command
{
    public function __construct(
        private readonly PasswordResetServiceInterface $passwordResetService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $removed = $this->passwordResetService->cleanupExpiredTokens();
            
            $io->success(sprintf('Removed %d expired token(s)', $removed));
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
```

**Konfiguracja cron (przykład):**

```bash
# Uruchamiaj co godzinę
0 * * * * cd /path/to/project && php bin/console app:cleanup-password-tokens
```

### 3.6 Migracje Bazy Danych

#### 3.6.1 Migracja - Tabela users

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class VersionXXXXXXXXXXXXXX extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table for authentication';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE users (
                id INT AUTO_INCREMENT NOT NULL,
                email VARCHAR(255) NOT NULL,
                username VARCHAR(100) NOT NULL,
                password VARCHAR(255) NOT NULL,
                roles JSON NOT NULL,
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                first_name VARCHAR(100) DEFAULT NULL,
                last_name VARCHAR(100) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                last_login_at DATETIME DEFAULT NULL,
                UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email),
                UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username),
                INDEX idx_email (email),
                INDEX idx_username (username),
                INDEX idx_is_active (is_active),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
```

#### 3.6.2 Migracja - Tabela password_reset_tokens

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class VersionYYYYYYYYYYYYYY extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create password_reset_tokens table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE password_reset_tokens (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                is_used TINYINT(1) DEFAULT 0 NOT NULL,
                UNIQUE INDEX UNIQ_238F5F355F37A13B (token),
                INDEX idx_token (token),
                INDEX idx_expires_at (expires_at),
                INDEX idx_user_id (user_id),
                INDEX IDX_238F5F35A76ED395 (user_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');
        
        $this->addSql('
            ALTER TABLE password_reset_tokens 
            ADD CONSTRAINT FK_238F5F35A76ED395 
            FOREIGN KEY (user_id) REFERENCES users (id) 
            ON DELETE CASCADE
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_reset_tokens DROP FOREIGN KEY FK_238F5F35A76ED395');
        $this->addSql('DROP TABLE password_reset_tokens');
    }
}
```

### 3.7 Rejestracja Serwisów w services.yaml

**Lokalizacja:** `config/services.yaml`

```yaml
services:
    # ... existing services ...
    
    # Auth services
    App\Auth\PasswordResetServiceInterface:
        class: App\Auth\PasswordResetService
        
    App\Auth\PasswordChangeServiceInterface:
        class: App\Auth\PasswordChangeService
        
    App\Auth\UserServiceInterface:
        class: App\Auth\UserService
        
    # Email service
    App\Service\EmailServiceInterface:
        class: App\Service\EmailService
```

---

## 4. BEZPIECZEŃSTWO I NAJLEPSZE PRAKTYKI

### 4.1 Bezpieczeństwo Haseł

1. **Hashowanie:**
   - Używaj bcrypt z cost parameter = 12
   - Symfony automatycznie zarządza hashowaniem przez PasswordHasherInterface

2. **Wymagania dotyczące haseł:**
   - Minimum 8 znaków
   - Walidacja po stronie klienta (HTML5) i serwera (Symfony Validator)

3. **Reset hasła:**
   - Token ważny tylko 1 godzinę
   - Token używany tylko raz (flaga `is_used`)
   - Nie ujawniaj, czy email istnieje w systemie

### 4.2 Ochrona przed Atakami

1. **CSRF Protection:**
   - Włączona w konfiguracji security.yaml
   - Tokeny CSRF w każdym formularzu
   - Walidacja po stronie Symfony

2. **Rate Limiting (do rozważenia w przyszłości):**
   - Ogranicz liczbę prób logowania (np. 5 w ciągu 15 minut)
   - Ogranicz liczbę żądań resetu hasła

3. **Session Security:**
   - `cookie_secure: auto` - HTTPS w produkcji
   - `cookie_samesite: lax` - ochrona przed CSRF
   - `invalidate_session: true` przy wylogowaniu

### 4.3 Audyt i Logowanie

**Wszystkie operacje autentykacji logowane w tabeli events:**

| Zdarzenie | event_type | Dane w details |
|-----------|------------|----------------|
| Próba logowania (sukces) | `login_success` | user_id, email, username, ip_address, user_agent |
| Próba logowania (błąd) | `login_failure` | email, ip_address, user_agent, reason |
| Wylogowanie | `logout` | user_id, email, username, ip_address, user_agent |
| Żądanie resetu hasła | `password_reset_request` | email, ip_address |
| Reset hasła | `password_reset` | user_id, email, username, ip_address |
| Zmiana hasła | `password_change` | user_id, email, username, ip_address |

---

## 5. ZGODNOŚĆ Z ISTNIEJĄCYM SYSTEMEM

### 5.1 Brak Wpływu na Działające Funkcjonalności

**Istniejące kontrolery:**
- Obecnie mają zakomentowane atrybuty `#[IsGranted]`
- Po implementacji autentykacji należy je odkomentować
- Wszystkie chronione endpointy będą wymagać logowania

**API endpoints (`/api/*`):**
- Pozostają publicznie dostępne w MVP
- W przyszłości dodać autoryzację przez tokeny (US-002 wspomina "Autoryzacja API przez tokeny")

**Istniejące szablony:**
- `base.html.twig` już obsługuje autentykację (`app.user`, `is_granted()`)
- `header.html.twig` już wyświetla informacje o użytkowniku
- Brak zmian w strukturze - tylko uruchomienie mechanizmu

### 5.2 Rozszerzenia EventService

**Nowe metody do dodania:**

```php
// W interface EventServiceInterface
public function logLoginAttempt(
    string $username,
    bool $success,
    ?string $ipAddress = null,
    ?string $userAgent = null,
    ?string $failureReason = null
): void;

public function logLogout(
    int $userId,
    string $username,
    ?string $ipAddress = null,
    ?string $userAgent = null
): void;

public function logPasswordChange(
    int $userId,
    string $username,
    ?string $ipAddress = null
): void;

public function logPasswordResetRequest(
    string $email,
    bool $emailExists,
    ?string $ipAddress = null
): void;

public function logPasswordReset(
    int $userId,
    string $username,
    ?string $ipAddress = null
): void;
```

**Implementacja w EventService:**
- Wykorzystaj istniejącą metodę `createEvent()`
- Nowe typy eventów w tabeli `events`
- Format `details` jako JSON z dodatkowymi informacjami

### 5.3 Aktualizacja Istniejących Kontrolerów

**Po wdrożeniu autentykacji, odkomentuj atrybuty:**

```php
// LeadsViewController.php
#[IsGranted('ROLE_USER')]  // <- odkomentuj
class LeadsViewController extends AbstractController

// CustomersViewController.php
#[IsGranted('ROLE_USER')]  // <- odkomentuj
class CustomersViewController extends AbstractController

// EventsViewController.php
#[IsGranted('ROLE_USER')]  // <- odkomentuj
class EventsViewController extends AbstractController

// ConfigViewController.php
#[IsGranted('ROLE_ADMIN')]  // <- odkomentuj
class ConfigViewController extends AbstractController
```

---

## 6. PLAN TESTOWANIA

### 6.1 Testy Jednostkowe

**Serwisy do przetestowania:**

1. **PasswordResetService:**
   - `requestPasswordReset()` - generowanie tokenu, wysyłka emaila
   - `validateResetToken()` - walidacja tokenu (prawidłowy, wygasły, użyty)
   - `resetPassword()` - zmiana hasła, oznaczenie tokenu jako użytego
   - `cleanupExpiredTokens()` - usuwanie wygasłych tokenów

2. **PasswordChangeService:**
   - `changePassword()` - walidacja obecnego hasła, zmiana na nowe
   - Obsługa błędów (nieprawidłowe obecne hasło, takie samo nowe hasło)

3. **UserService:**
   - `createUser()` - tworzenie użytkownika, hashowanie hasła
   - `findByEmail()`, `findByUsername()` - wyszukiwanie
   - `updateLastLogin()` - aktualizacja timestampu

**Lokalizacja:** `tests/Unit/Auth/`

### 6.2 Testy Funkcjonalne

**Scenariusze do przetestowania:**

1. **Logowanie:**
   - Poprawne logowanie
   - Niepoprawne hasło
   - Nieistniejący użytkownik
   - Konto nieaktywne
   - Funkcjonalność "Zapamiętaj mnie"

2. **Wylogowanie:**
   - Poprawne wylogowanie
   - Próba dostępu do chronionej strony po wylogowaniu

3. **Reset hasła:**
   - Żądanie resetu (email istnieje)
   - Żądanie resetu (email nie istnieje)
   - Użycie tokenu do zmiany hasła
   - Próba użycia wygasłego tokenu
   - Próba ponownego użycia tokenu

4. **Zmiana hasła:**
   - Poprawna zmiana hasła
   - Nieprawidłowe obecne hasło
   - Nowe hasło takie samo jak stare

5. **Kontrola dostępu:**
   - Dostęp do strony wymagającej ROLE_USER
   - Dostęp do strony wymagającej ROLE_CALL_CENTER
   - Dostęp do strony wymagającej ROLE_ADMIN
   - Próba dostępu bez odpowiedniej roli

**Lokalizacja:** `tests/Functional/Controller/`

### 6.3 Testy Integracyjne

**Scenariusze:**

1. Pełny przepływ rejestracji → logowania → zmiany hasła → wylogowania
2. Pełny przepływ resetu hasła: żądanie → email → zmiana hasła → logowanie
3. Logowanie różnych ról i weryfikacja dostępu do zasobów

---

## 7. CHECKLIST IMPLEMENTACJI

### 7.1 Faza 1: Podstawy

- [ ] Utworzenie encji `User` (`src/Model/User.php`)
- [ ] Utworzenie mapowania Doctrine (`config/doctrine/User.orm.xml`)
- [ ] Utworzenie encji `PasswordResetToken` (`src/Model/PasswordResetToken.php`)
- [ ] Utworzenie mapowania Doctrine (`config/doctrine/PasswordResetToken.orm.xml`)
- [ ] Utworzenie migracji dla tabeli `users`
- [ ] Utworzenie migracji dla tabeli `password_reset_tokens`
- [ ] Uruchomienie migracji w bazie danych

### 7.2 Faza 2: Konfiguracja Symfony Security

- [ ] Aktualizacja `config/packages/security.yaml`
- [ ] Konfiguracja password hashers
- [ ] Konfiguracja user providers
- [ ] Konfiguracja firewall (form_login, remember_me, logout)
- [ ] Konfiguracja access control
- [ ] Konfiguracja role hierarchy

### 7.3 Faza 3: Serwisy Autentykacji

- [ ] Utworzenie `UserServiceInterface` i implementacji
- [ ] Utworzenie `PasswordResetServiceInterface` i implementacji
- [ ] Utworzenie `PasswordChangeServiceInterface` i implementacji
- [ ] Utworzenie `EmailServiceInterface` i implementacji
- [ ] Rejestracja serwisów w `services.yaml`

### 7.4 Faza 4: Kontrolery

- [ ] Rozszerzenie `AuthController` (obsługa logowania)
- [ ] Utworzenie `PasswordResetController`
- [ ] Utworzenie `ProfileController` (zmiana hasła)
- [ ] Utworzenie DTO (`PasswordResetRequestDto`, `PasswordResetDto`, `ChangePasswordDto`)

### 7.5 Faza 5: Widoki (Templates)

- [ ] Aktualizacja `auth/login.html.twig` (błędy, CSRF, remember me)
- [ ] Utworzenie `auth/password_request.html.twig`
- [ ] Utworzenie `auth/password_reset.html.twig`
- [ ] Aktualizacja `auth/change_password.html.twig` (funkcjonalny formularz)
- [ ] Utworzenie `emails/password_reset.html.twig`

### 7.6 Faza 6: Event Listeners

- [ ] Utworzenie `LoginSuccessListener`
- [ ] Utworzenie `LogoutSuccessListener`
- [ ] Rozszerzenie `EventService` o metody logowania autentykacji

### 7.7 Faza 7: Komendy CLI

- [ ] Utworzenie `CreateUserCommand`
- [ ] Utworzenie `CleanupPasswordTokensCommand`
- [ ] Testowanie komend

### 7.8 Faza 8: Wyjątki

- [ ] Utworzenie `InvalidTokenException`
- [ ] Utworzenie `TokenExpiredException`
- [ ] Utworzenie `InvalidPasswordException`

### 7.9 Faza 9: Integracja z Istniejącym Systemem

- [ ] Odkomentowanie atrybutów `#[IsGranted]` w kontrolerach
- [ ] Weryfikacja, że `base.html.twig` i `header.html.twig` działają poprawnie
- [ ] Testowanie przepływu użytkownika (przekierowania na login, itp.)

### 7.10 Faza 10: Testy

- [ ] Testy jednostkowe serwisów autentykacji
- [ ] Testy funkcjonalne kontrolerów
- [ ] Testy integracyjne przepływów użytkownika
- [ ] Testy kontroli dostępu (role)

### 7.11 Faza 11: Dokumentacja i Wdrożenie

- [ ] Utworzenie dokumentacji dla administratorów (jak tworzyć użytkowników)
- [ ] Utworzenie początkowego użytkownika admin
- [ ] Aktualizacja README.md
- [ ] Przygotowanie migracji produkcyjnej

---

## 8. ANALIZA ZGODNOŚCI Z PRD I ROZWIĄZANE SPRZECZNOŚCI

### 8.1 Zidentyfikowane i Rozwiązane Sprzeczności

#### 8.1.1 Identyfikator Logowania - ZMIENIONO NA EMAIL ✅

**Decyzja projektowa:**
- PRD 3.2 wymaga: "Dostęp chroniony nazwą użytkownika i hasłem"
- Interpretujemy "nazwę użytkownika" jako identyfikator użytkownika

**Rozwiązanie:**
- Konfiguracja: `property: email` w `security.yaml`
- Metoda `getUserIdentifier()` zwraca `email`
- **Użytkownik loguje się wprowadzając email i hasło**
- Pole `username` pozostaje w systemie jako nazwa wyświetlana (display name)

**Uzasadnienie:**
1. **Łatwiejsze do zapamiętania** - użytkownicy znają swój email
2. **Nowoczesny standard** - większość systemów używa email do logowania
3. **Unikalność** - email jest zawsze unikalny
4. **Spójność** - ten sam email używany do logowania i resetu hasła
5. **User Experience** - pracownicy nie muszą pamiętać dodatkowego username
6. **Zgodność** - email spełnia funkcję "nazwy użytkownika" z PRD 3.2

#### 8.1.2 Autoryzacja API - WYJAŚNIONE ✅

**Niejasność:**
- PRD 3.2 wymienia: "Autoryzacja API przez tokeny"
- US-002 i US-008 skupiają się wyłącznie na autoryzacji panelu webowego
- Nie ma dedykowanego User Story dla autoryzacji API

**Rozwiązanie:**
- Autoryzacja API przez tokeny jest wymieniona w wymaganiach ogólnych (3.2), ale nie ma własnego User Story
- W zakresie **MVP modułu autentykacji panelu** (US-002, US-008): API pozostaje publicznie dostępne
- Dodano TODO w konfiguracji security.yaml z wyraźnym oznaczeniem że autoryzacja API jest poza zakresem bieżącego MVP
- Autoryzacja API przez tokeny powinna być zaimplementowana w osobnym module w przyszłości

**Uzasadnienie:**
US-002 dotyczy "Logowanie do panelu LMS" - skupia się na autoryzacji panelu webowego dla pracowników. Autoryzacja API dla aplikacji zewnętrznych to oddzielna funkcjonalność wymagająca osobnego projektu (API tokens, rate limiting, etc.).

#### 8.1.3 Funkcjonalność Rejestracji - USUNIĘTE ✅

**Nadmiarowe założenie:**
- Wstępna wersja wspominała o "rejestracji użytkowników"
- PRD nie zawiera żadnego User Story o publicznej rejestracji

**Rozwiązanie:**
- Usunięto wszystkie odniesienia do publicznej rejestracji użytkowników
- Użytkownicy są tworzeni przez administratorów za pomocą:
  - Komendy CLI: `php bin/console app:create-user`
  - Ewentualnie panelu administracyjnego (w przyszłości)

**Uzasadnienie:**
LMS to system wewnętrzny dla pracowników firmy. Nie ma potrzeby publicznej rejestracji - konta tworzy administrator dla nowych pracowników.

#### 8.1.4 Rola ADMIN - POPRAWNIE DODANA ✅

**Wyjaśnienie:**
- PRD 3.2 wymienia tylko dwie role: "Call Center (pełny dostęp) i BOK (read-only)"
- Jednak US-006 i 3.7 wspominają o "administratorach" z dodatkowymi uprawnieniami

**Rozwiązanie:**
- Dodano rolę `ROLE_ADMIN` z najwyższymi uprawnieniami
- ADMIN dziedziczy uprawnienia CALL_CENTER i BOK
- ADMIN ma dodatkowe uprawnienia:
  - Dostęp do konfiguracji systemu
  - Ręczne ponowne wysyłanie leadów do CDP (US-006)
  - Zarządzanie użytkownikami (tworzenie, edycja, dezaktywacja)

**Uzasadnienie:**
Każdy system wymaga roli administracyjnej. PRD explicite wspomina o administratorach w US-006, więc rola ADMIN jest zgodna z wymaganiami.

### 8.2 Weryfikacja User Stories

#### US-002: Logowanie do panelu LMS ✅ PEŁNA ZGODNOŚĆ

**Kryteria akceptacji:**
- ✅ Formularz logowania z nazwą użytkownika i hasłem - zaimplementowany w `auth/login.html.twig` (email jako identyfikator + hasło)
- ✅ Po zalogowaniu dostęp do panelu głównego - przekierowanie na `/leads` (dashboard)
- ✅ Logowanie próby logowania (IP, czas, rezultat) - `LoginSuccessListener` i `AuthController` logują do tabeli events
- ✅ Różne uprawnienia dla ról (call center vs BOK) - system ról z hierarchią w `security.yaml`

**Status:** Wszystkie kryteria spełnione. Email używany jako identyfikator logowania (interpretacja "nazwy użytkownika" z PRD). Dodatkowe funkcjonalności (reset hasła, zmiana hasła) wykraczają poza minimum ale są standardem dla systemów autentykacji.

#### US-008: Bezpieczny dostęp i autoryzacja ✅ PEŁNA ZGODNOŚĆ

**Kryteria akceptacji:**
- ✅ Call center ma pełny dostęp do listy leadów, danych klientów i edycji preferencji - `ROLE_CALL_CENTER` z odpowiednimi `#[IsGranted]` atrybutami
- ✅ BOK ma tylko dostęp do wyświetlania danych (read-only) - `ROLE_BOK` bez uprawnień do edycji
- ✅ Wszystkie operacje są logowane z informacją o użytkowniku - rozszerzenie `EventService` o metody logowania z `user_id` i `email`
- ✅ Brak dostępu do danych innych użytkowników - kontrolowane przez Symfony Security i role

**Status:** Wszystkie kryteria spełnione. System ról jest bardziej rozbudowany niż minimum (dodano ADMIN), ale to zwiększa bezpieczeństwo.

### 8.3 Zgodność z Innymi User Stories

#### US-003, US-004, US-005 (Operacje na leadach) ✅

Specyfikacja autentykacji zapewnia:
- Kontrolę dostępu przez atrybuty `#[IsGranted]` w kontrolerach
- Logowanie wszystkich operacji przez rozszerzony `EventService`
- Warunkowe wyświetlanie elementów UI w zależności od roli

#### US-006 (Monitoring nieudanych dostarczeń) ✅

Specyfikacja autentykacji zapewnia:
- Rolę `ROLE_ADMIN` z uprawnieniami do ponownego wysyłania
- Kontrolę dostępu do funkcjonalności administracyjnych

#### US-007 (Deduplikacja klientów) ✅

Brak konfliktu - deduplikacja działa niezależnie od systemu autentykacji.

#### US-009, US-010 (Obsługa błędów i eventy) ✅

Specyfikacja rozszerza `EventService` o nowe typy eventów związanych z autentykacją, zachowując spójność z istniejącym systemem logowania.

### 8.4 Dodatkowe Decyzje Projektowe

#### 8.4.1 Reset Hasła - Funkcjonalność Dodatkowa

**Dlaczego dodano:**
- Nie wymagana przez PRD, ale standardowa funkcjonalność każdego systemu autentykacji
- Zmniejsza obciążenie administratorów (nie muszą ręcznie resetować haseł)
- Zwiększa bezpieczeństwo (użytkownicy mogą natychmiast zmienić hasło jeśli podejrzewają kompromitację)

#### 8.4.2 Funkcjonalność "Zapamiętaj mnie"

**Dlaczego dodano:**
- Poprawia user experience dla pracowników call center
- Standardowa funkcjonalność w systemach biznesowych
- Bezpieczna implementacja przez Symfony (tokeny w cookies)

#### 8.4.3 Dwuetapowe Zarządzanie Użytkownikami

**Decyzja:**
- W MVP: użytkownicy tworzeni przez CLI command
- W przyszłości: panel administracyjny do zarządzania użytkownikami

**Uzasadnienie:**
Panel administracyjny nie jest wymagany w MVP US-002/US-008. CLI command jest wystarczający dla początkowego setupu i sporadycznego dodawania użytkowników.

---

## 9. PODSUMOWANIE

### 9.1 Kluczowe Decyzje Architektoniczne

1. **Wykorzystanie Symfony Security Bundle:**
   - Sprawdzone, bezpieczne rozwiązanie
   - Wbudowana obsługa hashowania haseł
   - Łatwa konfiguracja ról i uprawnień

2. **Doctrine ORM z mapowaniem XML:**
   - Zgodne z wytycznymi projektu
   - Separacja definicji mapowania od kodu

3. **Interfejsy dla wszystkich serwisów:**
   - Zgodne z DDD
   - Łatwe mockowanie w testach
   - Możliwość zmiany implementacji

4. **Logowanie wszystkich operacji:**
   - Spełnienie wymagań audytu (US-008)
   - Wykorzystanie istniejącego EventService

5. **Bezpieczeństwo priorytetem:**
   - CSRF protection
   - Bcrypt do hashowania
   - Tokeny reset hasła ważne 1 godzinę
   - Nie ujawnianie informacji o istnieniu emaili

### 9.2 Wymagania Spełnione

**US-002: Logowanie do panelu LMS**
- ✓ Formularz logowania z email (jako identyfikator) i hasłem
- ✓ Po zalogowaniu dostęp do panelu głównego
- ✓ Logowanie prób logowania (IP, czas, rezultat)
- ✓ Różne uprawnienia dla ról (call center vs BOK)

**US-008: Bezpieczny dostęp i autoryzacja**
- ✓ Call center ma pełny dostęp
- ✓ BOK ma dostęp read-only
- ✓ Wszystkie operacje logowane z informacją o użytkowniku (email, username, user_id)
- ✓ Brak dostępu do danych innych użytkowników

### 9.3 Zgodność z Tech Stack

- ✓ PHP 8.3 + Symfony 7.3
- ✓ Doctrine ORM
- ✓ MySQL
- ✓ HTMX + Fluent UI (istniejące szablony)
- ✓ Brak naruszenia istniejącego działania aplikacji

---

**Koniec specyfikacji**

