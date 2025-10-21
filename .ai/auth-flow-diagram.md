# Diagram Przepływu Autentykacji - LMS

> **Źródła:** PRD (US-002, US-008), auth-spec.md, istniejący codebase  
> **Data:** 2025-10-15

---

## 1. Proces Logowania Użytkownika

```mermaid
flowchart TD
    Start([Użytkownik otwiera /leads]) --> CheckAuth{Czy użytkownik<br/>zalogowany?}
    
    CheckAuth -->|Tak| ShowLeads[Wyświetl dashboard leadów]
    CheckAuth -->|Nie| Redirect[Przekieruj na /login]
    
    Redirect --> LoginForm[Wyświetl formularz logowania<br/>auth/login.html.twig]
    
    LoginForm --> EnterCreds[Użytkownik wprowadza:<br/>- Email<br/>- Hasło<br/>- Opcja: Zapamiętaj mnie]
    
    EnterCreds --> SubmitForm[POST /login<br/>_username, _password, _csrf_token]
    
    SubmitForm --> SymfonySecurity[Symfony Security<br/>form_login interceptor]
    
    SymfonySecurity --> ValidateCSRF{Walidacja<br/>tokenu CSRF}
    ValidateCSRF -->|Nieprawidłowy| ErrorCSRF[Błąd: Sesja wygasła]
    ErrorCSRF --> LoginForm
    
    ValidateCSRF -->|Prawidłowy| FindUser[UserProvider:<br/>Znajdź użytkownika<br/>po email]
    
    FindUser --> UserExists{Użytkownik<br/>istnieje?}
    UserExists -->|Nie| ErrorAuth[Błąd autentykacji]
    
    UserExists -->|Tak| CheckPassword[PasswordHasher:<br/>Weryfikuj hasło]
    
    CheckPassword --> PasswordValid{Hasło<br/>prawidłowe?}
    PasswordValid -->|Nie| ErrorAuth
    
    ErrorAuth --> LogFailure[EventService:<br/>logLoginAttempt<br/>success: false]
    LogFailure --> ShowError[Wyświetl błąd:<br/>Nieprawidłowy email lub hasło]
    ShowError --> LoginForm
    
    PasswordValid -->|Tak| CheckActive{Konto<br/>aktywne?}
    CheckActive -->|Nie| ErrorInactive[Błąd: Konto dezaktywowane]
    ErrorInactive --> ShowError
    
    CheckActive -->|Tak| CreateSession[Utwórz sesję użytkownika]
    
    CreateSession --> RememberMe{Zapamiętaj<br/>mnie?}
    RememberMe -->|Tak| CreateToken[Utwórz remember_me token<br/>ważny 7 dni]
    RememberMe -->|Nie| LogSuccess
    CreateToken --> LogSuccess
    
    LogSuccess[LoginSuccessListener:<br/>- Aktualizuj last_login_at<br/>- EventService.logLoginAttempt<br/>  success: true, IP, user agent]
    
    LogSuccess --> RedirectDash[Przekieruj na /leads]
    RedirectDash --> ShowLeads
    
    ShowLeads --> End([Dashboard])
    
    style Start fill:#e1f5ff
    style End fill:#c8e6c9
    style ErrorAuth fill:#ffcdd2
    style ErrorCSRF fill:#ffcdd2
    style ErrorInactive fill:#ffcdd2
    style SymfonySecurity fill:#fff9c4
    style CreateSession fill:#c8e6c9
    style LogSuccess fill:#c8e6c9
```

---

## 2. Kontrola Dostępu do Zasobów (Access Control)

```mermaid
flowchart TD
    Request([Żądanie HTTP]) --> Firewall[Symfony Security<br/>Main Firewall]
    
    Firewall --> CheckSession{Sesja<br/>istnieje?}
    
    CheckSession -->|Nie| CheckPublic{Publiczna<br/>ścieżka?}
    CheckPublic -->|Tak /login, /password/*| AllowAccess[Zezwól na dostęp]
    CheckPublic -->|Nie| RedirectLogin[Redirect na /login]
    
    CheckSession -->|Tak| LoadUser[Załaduj użytkownika<br/>z sesji]
    
    LoadUser --> CheckRole{Sprawdź<br/>wymaganą rolę}
    
    CheckRole -->|PUBLIC_ACCESS| AllowAccess
    
    CheckRole -->|ROLE_USER| HasRoleUser{Użytkownik<br/>ma ROLE_USER?}
    HasRoleUser -->|Tak| AllowAccess
    HasRoleUser -->|Nie| DenyAccess[403 Forbidden]
    
    CheckRole -->|ROLE_CALL_CENTER| HasRoleCC{Użytkownik ma<br/>ROLE_CALL_CENTER<br/>lub ROLE_ADMIN?}
    HasRoleCC -->|Tak| AllowAccess
    HasRoleCC -->|Nie| DenyAccess
    
    CheckRole -->|ROLE_BOK| HasRoleBOK{Użytkownik ma<br/>ROLE_BOK<br/>lub ROLE_ADMIN?}
    HasRoleBOK -->|Tak| AllowAccess
    HasRoleBOK -->|Nie| DenyAccess
    
    CheckRole -->|ROLE_ADMIN| HasRoleAdmin{Użytkownik<br/>ma ROLE_ADMIN?}
    HasRoleAdmin -->|Tak| AllowAccess
    HasRoleAdmin -->|Nie| DenyAccess
    
    AllowAccess --> Controller[Wykonaj kontroler]
    Controller --> Response([Odpowiedź HTTP])
    
    DenyAccess --> ErrorPage([403 Forbidden])
    RedirectLogin --> LoginPage([Strona logowania])
    
    style Request fill:#e1f5ff
    style Response fill:#c8e6c9
    style AllowAccess fill:#c8e6c9
    style DenyAccess fill:#ffcdd2
    style RedirectLogin fill:#fff9c4
    style Firewall fill:#fff9c4
```

---

## 3. Hierarchia Ról i Dziedziczenie

```mermaid
graph TD
    ADMIN[ROLE_ADMIN<br/>Administrator] --> CC[ROLE_CALL_CENTER<br/>Call Center]
    ADMIN --> BOK[ROLE_BOK<br/>BOK]
    ADMIN --> USER[ROLE_USER<br/>Użytkownik]
    
    CC --> USER
    BOK --> USER
    
    USER --> Permissions[Podstawowe uprawnienia:<br/>- Logowanie<br/>- Przeglądanie leadów<br/>- Przeglądanie eventów]
    
    CC --> CCPermissions[Dodatkowe uprawnienia:<br/>- Edycja preferencji klienta<br/>- Zmiana statusu leada<br/>- Usuwanie leadów]
    
    BOK --> BOKPermissions[Uprawnienia:<br/>- Tylko odczyt<br/>- Brak edycji]
    
    ADMIN --> AdminPermissions[Pełne uprawnienia:<br/>- Wszystko z CC i BOK<br/>- Konfiguracja systemu<br/>- Zarządzanie użytkownikami<br/>- Ponowne wysyłanie do CDP]
    
    style ADMIN fill:#ff6b6b
    style CC fill:#4ecdc4
    style BOK fill:#95e1d3
    style USER fill:#f38181
    style Permissions fill:#e8f5e9
    style CCPermissions fill:#e3f2fd
    style BOKPermissions fill:#fff9c4
    style AdminPermissions fill:#fce4ec
```

---

## 4. Proces Resetowania Hasła

```mermaid
sequenceDiagram
    actor User as Użytkownik
    participant Login as Login Page
    participant PC as PasswordResetController
    participant DB as Database
    participant Email as Email Service
    participant Token as PasswordResetToken
    
    User->>Login: Klika "Nie pamiętasz hasła?"
    Login->>PC: GET /password/request
    PC->>User: Wyświetl formularz
    
    User->>PC: POST /password/request<br/>(email)
    
    PC->>DB: Znajdź użytkownika po email
    
    alt Email istnieje
        DB-->>PC: User found
        PC->>Token: Generuj token (64 znaki)
        Token-->>PC: Token string
        PC->>DB: Zapisz token<br/>(expires_at = +1h)
        PC->>Email: Wyślij email z linkiem<br/>/password/reset/{token}
        Email-->>User: Email z linkiem
        PC->>User: "Jeśli konto istnieje,<br/>wysłaliśmy instrukcje"
    else Email nie istnieje
        DB-->>PC: User not found
        PC->>User: "Jeśli konto istnieje,<br/>wysłaliśmy instrukcje"<br/>(dla bezpieczeństwa)
    end
    
    Note over User,Token: Użytkownik klika link w emailu
    
    User->>PC: GET /password/reset/{token}
    PC->>DB: Waliduj token
    
    alt Token prawidłowy i nie wygasł
        DB-->>PC: Token valid
        PC->>User: Wyświetl formularz<br/>nowego hasła
        User->>PC: POST /password/reset/{token}<br/>(new_password)
        PC->>DB: Zahashuj i zapisz<br/>nowe hasło
        PC->>DB: Oznacz token jako użyty
        PC->>User: Przekieruj na /login<br/>z sukcesem
    else Token nieprawidłowy lub wygasły
        DB-->>PC: Token invalid
        PC->>User: Błąd: Link wygasł<br/>Poproś o nowy
    end
```

---

## 5. Proces Zmiany Hasła (Zalogowany Użytkownik)

```mermaid
sequenceDiagram
    actor User as Zalogowany<br/>Użytkownik
    participant Header as Header Menu
    participant PC as ProfileController
    participant PCS as PasswordChangeService
    participant Hasher as PasswordHasher
    participant DB as Database
    participant ES as EventService
    
    User->>Header: Klika "Zmień hasło"
    Header->>PC: GET /profile/change-password
    PC->>User: Wyświetl formularz
    
    User->>PC: POST /profile/change-password<br/>(current_password,<br/>new_password)
    
    PC->>PCS: changePassword(user,<br/>current, new)
    
    PCS->>Hasher: Zweryfikuj obecne hasło
    
    alt Obecne hasło nieprawidłowe
        Hasher-->>PCS: Invalid
        PCS-->>PC: InvalidPasswordException
        PC->>User: Błąd: Obecne hasło<br/>jest nieprawidłowe
    else Obecne hasło prawidłowe
        Hasher-->>PCS: Valid
        
        PCS->>PCS: Sprawdź czy nowe<br/>różni się od obecnego
        
        alt Nowe = Stare
            PCS-->>PC: Exception
            PC->>User: Błąd: Nowe hasło musi<br/>różnić się od obecnego
        else Nowe ≠ Stare
            PCS->>Hasher: Zahashuj nowe hasło
            Hasher-->>PCS: Hashed password
            PCS->>DB: Zapisz nowe hasło
            DB-->>PCS: Success
            
            PCS->>ES: logPasswordChange<br/>(user_id, email, IP)
            ES->>DB: Zapisz event
            
            PCS-->>PC: Success
            PC->>User: Sukces: Hasło zostało<br/>zmienione
        end
    end
```

---

## 6. Proces Wylogowania

```mermaid
sequenceDiagram
    actor User as Użytkownik
    participant Header as Header Menu
    participant Security as Symfony Security
    participant Listener as LogoutSuccessListener
    participant ES as EventService
    participant DB as Database
    
    User->>Header: Klika "Wyloguj"
    Header->>Security: GET /logout
    
    Security->>Listener: LogoutEvent(token)
    Listener->>Listener: Pobierz użytkownika<br/>z tokenu
    
    Listener->>ES: logLogout(user_id,<br/>email, IP, user_agent)
    ES->>DB: Zapisz event
    DB-->>ES: Success
    
    Listener-->>Security: Continue
    Security->>Security: Usuń sesję<br/>Usuń remember_me cookie
    
    Security->>User: Przekieruj na /login<br/>z komunikatem:<br/>"Zostałeś wylogowany"
```

---

## 7. Architektura Komponentów Autentykacji

```mermaid
graph TB
    subgraph "Frontend Layer"
        LoginForm[Formularz Logowania<br/>auth/login.html.twig]
        ResetForm[Formularz Resetu Hasła<br/>auth/password_request.html.twig]
        ChangeForm[Formularz Zmiany Hasła<br/>auth/change_password.html.twig]
        Header[Header z Menu<br/>components/header.html.twig]
    end
    
    subgraph "Controller Layer"
        AuthController[AuthController<br/>- login GET/POST<br/>- logout GET]
        PasswordResetController[PasswordResetController<br/>- request GET/POST<br/>- reset GET/POST]
        ProfileController[ProfileController<br/>- changePassword GET/POST]
    end
    
    subgraph "Service Layer"
        UserService[UserService<br/>- createUser<br/>- findByEmail<br/>- updateLastLogin]
        PasswordResetService[PasswordResetService<br/>- requestPasswordReset<br/>- validateResetToken<br/>- resetPassword]
        PasswordChangeService[PasswordChangeService<br/>- changePassword]
        EmailService[EmailService<br/>- sendPasswordResetEmail<br/>- sendPasswordChangedEmail]
        EventService[EventService<br/>- logLoginAttempt<br/>- logLogout<br/>- logPasswordChange<br/>- logPasswordReset]
    end
    
    subgraph "Security Layer"
        SymfonySecurity[Symfony Security Bundle<br/>- UserProvider<br/>- PasswordHasher<br/>- Firewall<br/>- Access Control]
        LoginListener[LoginSuccessListener<br/>- Aktualizacja last_login<br/>- Logowanie sukcesu]
        LogoutListener[LogoutSuccessListener<br/>- Logowanie wylogowania]
    end
    
    subgraph "Data Layer"
        UserModel[User Model<br/>- email<br/>- username<br/>- password<br/>- roles<br/>- isActive]
        TokenModel[PasswordResetToken Model<br/>- token<br/>- user<br/>- expiresAt<br/>- isUsed]
        EventModel[Event Model<br/>- eventType<br/>- userId<br/>- details<br/>- ipAddress]
        UserORM[User.orm.xml]
        TokenORM[PasswordResetToken.orm.xml]
    end
    
    subgraph "Database"
        UsersTable[(users table)]
        TokensTable[(password_reset_tokens)]
        EventsTable[(events table)]
    end
    
    LoginForm --> AuthController
    ResetForm --> PasswordResetController
    ChangeForm --> ProfileController
    Header --> AuthController
    Header --> ProfileController
    
    AuthController --> SymfonySecurity
    AuthController --> EventService
    
    PasswordResetController --> PasswordResetService
    PasswordResetController --> EventService
    
    ProfileController --> PasswordChangeService
    ProfileController --> EventService
    
    SymfonySecurity --> UserModel
    SymfonySecurity --> LoginListener
    SymfonySecurity --> LogoutListener
    
    LoginListener --> UserService
    LoginListener --> EventService
    
    LogoutListener --> EventService
    
    UserService --> UserModel
    PasswordResetService --> TokenModel
    PasswordResetService --> UserModel
    PasswordResetService --> EmailService
    
    PasswordChangeService --> UserModel
    PasswordChangeService --> SymfonySecurity
    
    EventService --> EventModel
    
    UserModel --> UserORM
    TokenModel --> TokenORM
    
    UserORM --> UsersTable
    TokenORM --> TokensTable
    EventModel --> EventsTable
    
    style LoginForm fill:#e3f2fd
    style ResetForm fill:#e3f2fd
    style ChangeForm fill:#e3f2fd
    style Header fill:#e3f2fd
    
    style AuthController fill:#fff9c4
    style PasswordResetController fill:#fff9c4
    style ProfileController fill:#fff9c4
    
    style UserService fill:#c8e6c9
    style PasswordResetService fill:#c8e6c9
    style PasswordChangeService fill:#c8e6c9
    style EmailService fill:#c8e6c9
    style EventService fill:#c8e6c9
    
    style SymfonySecurity fill:#ffccbc
    style LoginListener fill:#ffccbc
    style LogoutListener fill:#ffccbc
    
    style UserModel fill:#f8bbd0
    style TokenModel fill:#f8bbd0
    style EventModel fill:#f8bbd0
    
    style UsersTable fill:#b2dfdb
    style TokensTable fill:#b2dfdb
    style EventsTable fill:#b2dfdb
```

---

## 8. Macierz Dostępu - Mapowanie Ścieżek na Role

```mermaid
graph LR
    subgraph "Publiczne (PUBLIC_ACCESS)"
        P1[/login]
        P2[/password/request]
        P3[/password/reset/*]
        P4[/api/*]
    end
    
    subgraph "Wymagana ROLE_USER"
        U1[/profile/*]
        U2[/leads]
        U3[/customers]
        U4[/events]
        U5[/failed-deliveries]
    end
    
    subgraph "Wymagana ROLE_CALL_CENTER"
        CC1[/leads/*/edit]
        CC2[/leads/*/delete]
        CC3[/customers/*/preferences]
    end
    
    subgraph "Wymagana ROLE_ADMIN"
        A1[/config]
        A2[/failed-deliveries/*/retry]
        A3[/users/manage]
    end
    
    style P1 fill:#c8e6c9
    style P2 fill:#c8e6c9
    style P3 fill:#c8e6c9
    style P4 fill:#c8e6c9
    
    style U1 fill:#fff9c4
    style U2 fill:#fff9c4
    style U3 fill:#fff9c4
    style U4 fill:#fff9c4
    style U5 fill:#fff9c4
    
    style CC1 fill:#e3f2fd
    style CC2 fill:#e3f2fd
    style CC3 fill:#e3f2fd
    
    style A1 fill:#ffcdd2
    style A2 fill:#ffcdd2
    style A3 fill:#ffcdd2
```

---

## 9. Logowanie Eventów Autentykacji

```mermaid
flowchart TD
    subgraph "Zdarzenia Autentykacji"
        E1[login_success]
        E2[login_failure]
        E3[logout]
        E4[password_reset_request]
        E5[password_reset]
        E6[password_change]
    end
    
    E1 --> Details1["details: {<br/>user_id, email,<br/>username, ip_address,<br/>user_agent}"]
    
    E2 --> Details2["details: {<br/>email, ip_address,<br/>user_agent, reason}"]
    
    E3 --> Details3["details: {<br/>user_id, email,<br/>username, ip_address,<br/>user_agent}"]
    
    E4 --> Details4["details: {<br/>email, ip_address}"]
    
    E5 --> Details5["details: {<br/>user_id, email,<br/>username, ip_address}"]
    
    E6 --> Details6["details: {<br/>user_id, email,<br/>username, ip_address}"]
    
    Details1 --> DB[(events table)]
    Details2 --> DB
    Details3 --> DB
    Details4 --> DB
    Details5 --> DB
    Details6 --> DB
    
    DB --> Audit[Audyt i Monitoring]
    DB --> Analytics[Analiza Bezpieczeństwa]
    
    style E1 fill:#c8e6c9
    style E2 fill:#ffcdd2
    style E3 fill:#fff9c4
    style E4 fill:#e3f2fd
    style E5 fill:#e3f2fd
    style E6 fill:#e3f2fd
    style DB fill:#b2dfdb
    style Audit fill:#f8bbd0
    style Analytics fill:#f8bbd0
```

---

## 10. Podsumowanie Kluczowych Decyzji

### Identyfikator Logowania
- **Email** jako główny identyfikator (property: email)
- Użytkownik loguje się przez: **email + hasło**
- Username pozostaje jako nazwa wyświetlana

### System Ról (Role Hierarchy)
```
ROLE_ADMIN
├── ROLE_CALL_CENTER
│   └── ROLE_USER
└── ROLE_BOK
    └── ROLE_USER
```

### Bezpieczeństwo
- CSRF Protection: włączona
- Password Hashing: bcrypt (cost: 12)
- Remember Me: 7 dni
- Password Reset Token: 1 godzina
- Session Security: cookie_secure: auto, cookie_samesite: lax

### Logowanie
- Wszystkie operacje autentykacji logowane w tabeli `events`
- Zawiera: user_id, email, username, IP, user_agent, timestamp
- Cele: audyt, analiza bezpieczeństwa, debugging

---

**Koniec diagramu**

