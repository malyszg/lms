# Diagram Autentykacji - Lead Management System

> **Źródło:** PRD (US-002, US-008), auth-spec.md  
> **Typ:** Sequence Diagram (Mermaid)  
> **Data:** 2025-10-15  
> **Język:** Polski

---

## Przepływ 1: Logowanie Użytkownika

```mermaid
sequenceDiagram
    autonumber
    
    participant U as Użytkownik
    participant B as Przeglądarka
    participant F as Symfony<br/>Firewall
    participant AC as AuthController
    participant S as Security<br/>FormLogin
    participant UP as UserProvider
    participant PH as PasswordHasher
    participant SM as SessionManager
    participant LS as LoginSuccess<br/>Listener
    participant US as UserService
    participant ES as EventService
    participant DB as Database
    
    U->>B: Wchodzi na /leads
    B->>F: GET /leads
    F->>SM: Sprawdź sesję
    SM-->>F: Brak sesji
    F-->>B: Redirect → /login
    
    B->>AC: GET /login
    AC->>AC: Sprawdź getUser()
    AC-->>B: Render formularz
    B-->>U: Wyświetl login
    
    U->>B: Wypełnia email+hasło<br/>Zaznacza "Zapamiętaj"<br/>Klika "Zaloguj"
    B->>F: POST /login<br/>_username=email<br/>_password=***<br/>_csrf_token<br/>_remember_me
    
    F->>S: Przechwytuje POST
    
    S->>S: Waliduj CSRF
    
    alt CSRF nieprawidłowy
        S-->>B: Error: Sesja wygasła
        B-->>U: Komunikat błędu
    else CSRF prawidłowy
        S->>UP: loadUserByIdentifier(email)
        UP->>DB: SELECT * FROM users<br/>WHERE email = ?
        
        alt Użytkownik nie istnieje
            DB-->>UP: Empty
            UP-->>S: UserNotFoundException
            S->>ES: logLoginAttempt<br/>success=false
            ES->>DB: INSERT INTO events
            S-->>B: Błąd logowania
            B-->>U: Nieprawidłowy<br/>email lub hasło
        else Użytkownik istnieje
            DB-->>UP: User object
            UP-->>S: User
            
            S->>S: Sprawdź isActive()
            
            alt Konto nieaktywne
                S-->>B: Konto dezaktywowane
                B-->>U: Komunikat błędu
            else Konto aktywne
                S->>PH: verify(password, hash)
                PH->>PH: bcrypt verify
                
                alt Hasło błędne
                    PH-->>S: false
                    S->>ES: logLoginAttempt<br/>success=false
                    ES->>DB: INSERT events
                    S-->>B: Błąd logowania
                    B-->>U: Nieprawidłowy<br/>email lub hasło
                else Hasło prawidłowe
                    PH-->>S: true
                    
                    S->>SM: Utwórz sesję
                    SM->>SM: Generuj ID
                    SM-->>S: Sesja utworzona
                    
                    alt Remember Me
                        S->>SM: Utwórz token<br/>ważny 7 dni
                        SM-->>S: Token OK
                    end
                    
                    Note over S,LS: LoginSuccessEvent
                    
                    S->>LS: onLoginSuccess()
                    
                    par Operacje równoległe
                        LS->>US: updateLastLogin()
                        US->>DB: UPDATE users<br/>SET last_login_at
                    and
                        LS->>ES: logLoginAttempt<br/>success=true
                        ES->>DB: INSERT events
                    end
                    
                    LS-->>S: OK
                    S-->>B: Redirect → /leads
                    
                    B->>F: GET /leads
                    F->>SM: Sprawdź sesję
                    SM-->>F: Sesja aktywna
                    F->>F: Sprawdź ROLE_USER
                    F-->>B: Render dashboard
                    B-->>U: Dashboard leadów
                end
            end
        end
    end
```

---

## Przepływ 2: Resetowanie Hasła - Żądanie

```mermaid
sequenceDiagram
    autonumber
    
    participant U as Użytkownik
    participant B as Przeglądarka
    participant PC as PasswordReset<br/>Controller
    participant PS as PasswordReset<br/>Service
    participant UR as UserRepository
    participant TG as TokenGenerator
    participant EM as EmailService
    participant ML as Mailer
    participant DB as Database
    participant ES as EventService
    
    U->>B: Klika<br/>"Nie pamiętasz hasła?"
    B->>PC: GET /password/request
    PC-->>B: Render formularz
    B-->>U: Pole email
    
    U->>B: Wprowadza email<br/>Klika "Wyślij"
    B->>PC: POST /password/request<br/>email+_csrf_token
    
    PC->>PC: Waliduj CSRF
    
    PC->>PS: requestPasswordReset(email)
    
    PS->>UR: findOneBy email
    UR->>DB: SELECT * FROM users<br/>WHERE email = ?
    
    alt Email nie istnieje
        DB-->>UR: Empty
        UR-->>PS: null
        PS->>ES: logPasswordReset<br/>emailExists=false
        ES->>DB: INSERT events
        PS-->>PC: true
        Note over PS,PC: Dla bezpieczeństwa<br/>zawsze sukces
    else Email istnieje
        DB-->>UR: User
        UR-->>PS: User
        
        par Operacje równoległe
            PS->>TG: Generuj token
            TG->>TG: bin2hex<br/>random_bytes(32)
            TG-->>PS: Token 64 znaki
            PS->>DB: INSERT<br/>password_reset_tokens<br/>expires_at=NOW()+1h
        and
            PS->>EM: sendPasswordReset<br/>Email(url+token)
            EM->>EM: Render template
            EM->>ML: send()
            ML-->>EM: Email wysłany
        end
        
        PS->>ES: logPasswordReset<br/>emailExists=true
        ES->>DB: INSERT events
        PS-->>PC: true
    end
    
    PC-->>B: Komunikat: Jeśli konto<br/>istnieje, wysłano email
    B-->>U: Ten sam komunikat
```

---

## Przepływ 3: Resetowanie Hasła - Ustawienie Nowego

```mermaid
sequenceDiagram
    autonumber
    
    participant U as Użytkownik
    participant B as Przeglądarka
    participant PC as PasswordReset<br/>Controller
    participant PS as PasswordReset<br/>Service
    participant PH as PasswordHasher
    participant DB as Database
    participant ES as EventService
    
    U->>B: Klika link z email
    B->>PC: GET /password/reset/token
    
    PC->>PS: validateResetToken(token)
    PS->>DB: SELECT FROM<br/>password_reset_tokens<br/>WHERE token=?<br/>AND is_used=false
    
    alt Token nie istnieje
        DB-->>PS: Empty
        PS-->>PC: InvalidToken<br/>Exception
        PC-->>B: Błąd: Token<br/>nieprawidłowy
        B-->>U: Link do nowego<br/>żądania
    else Token istnieje
        DB-->>PS: Token object
        PS->>PS: Sprawdź expires_at
        
        alt Token wygasł
            PS-->>PC: TokenExpired<br/>Exception
            PC-->>B: Błąd: Token wygasł
            B-->>U: Link do nowego<br/>żądania
        else Token ważny
            PS-->>PC: User
            PC-->>B: Render formularz<br/>nowego hasła
            B-->>U: Formularz: hasło+<br/>potwierdzenie
            
            U->>B: Wypełnia i wysyła
            B->>PC: POST /password/reset<br/>password+confirm+<br/>_csrf_token
            
            PC->>PC: Waliduj CSRF<br/>password==confirm<br/>length>=8
            
            alt Walidacja błąd
                PC-->>B: Komunikaty błędów
                B-->>U: Popraw dane
            else Walidacja OK
                PC->>PS: resetPassword<br/>(token, newPassword)
                PS->>PS: Ponowna walidacja<br/>tokenu
                PS->>PH: hashPassword()
                PH->>PH: bcrypt cost=12
                PH-->>PS: Hash
                
                par Operacje równoległe
                    PS->>DB: UPDATE users<br/>SET password=?
                and
                    PS->>DB: UPDATE<br/>password_reset_tokens<br/>SET is_used=true
                and
                    PS->>ES: logPasswordReset
                    ES->>DB: INSERT events
                end
                
                PS-->>PC: Success
                PC-->>B: Redirect → /login<br/>message=success
                B-->>U: Hasło zmienione.<br/>Możesz się<br/>zalogować
            end
        end
    end
```

---

## Przepływ 4: Zmiana Hasła (Zalogowany)

```mermaid
sequenceDiagram
    autonumber
    
    participant U as Zalogowany<br/>Użytkownik
    participant B as Przeglądarka
    participant H as Header Menu
    participant PC as Profile<br/>Controller
    participant PS as PasswordChange<br/>Service
    participant PH as PasswordHasher
    participant UR as UserRepository
    participant DB as Database
    participant ES as EventService
    
    U->>H: Klika avatar
    H-->>U: Dropdown menu
    U->>H: Klika "Zmień hasło"
    
    H->>PC: GET /profile/<br/>change-password
    PC->>PC: Sprawdź<br/>@IsGranted<br/>ROLE_USER
    PC-->>B: Render formularz
    B-->>U: Formularz: obecne+<br/>nowe+potwierdzenie
    
    U->>B: Wypełnia i wysyła
    B->>PC: POST<br/>current_password<br/>new_password<br/>confirm+_csrf_token
    
    PC->>PC: Waliduj CSRF
    PC->>PC: Pobierz getUser()
    
    PC->>PS: changePassword<br/>(User, current, new)
    
    PS->>PH: verify(current,<br/>User.password)
    PH->>PH: bcrypt verify
    
    alt Obecne hasło błędne
        PH-->>PS: false
        PS-->>PC: InvalidPassword<br/>Exception
        PC-->>B: Błąd: Obecne<br/>hasło nieprawidłowe
        B-->>U: Komunikat błędu
    else Obecne hasło OK
        PH-->>PS: true
        PS->>PS: Sprawdź czy<br/>new!=current
        
        alt Takie same hasła
            PS-->>PC: InvalidPassword<br/>Exception
            PC-->>B: Błąd: Nowe musi<br/>różnić się
            B-->>U: Komunikat błędu
        else Nowe różne
            PS->>PH: hashPassword(new)
            PH->>PH: bcrypt cost=12
            PH-->>PS: Hash
            
            par Operacje równoległe
                PS->>UR: flush(User)
                UR->>DB: UPDATE users<br/>SET password=?<br/>updated_at=NOW()
            and
                PS->>ES: logPasswordChange
                ES->>DB: INSERT events
            end
            
            PS-->>PC: Success
            PC-->>B: Komunikat: Hasło<br/>zostało zmienione
            B-->>U: Sukces + powrót<br/>do dashboardu
        end
    end
```

---

## Przepływ 5: Wylogowanie

```mermaid
sequenceDiagram
    autonumber
    
    participant U as Zalogowany<br/>Użytkownik
    participant B as Przeglądarka
    participant H as Header Menu
    participant F as Symfony<br/>Firewall
    participant LH as LogoutHandler
    participant SM as SessionManager
    participant LL as LogoutSuccess<br/>Listener
    participant ES as EventService
    participant DB as Database
    
    U->>H: Klika avatar
    H-->>U: Dropdown
    U->>H: Klika "Wyloguj"
    
    H->>F: GET /logout
    
    Note over F,LH: Symfony Security<br/>przechwytuje
    
    F->>LH: Przechwyt żądania
    LH->>SM: Pobierz Token
    SM-->>LH: SecurityToken(User)
    LH->>LH: Pobierz User
    
    Note over LH,LL: LogoutEvent
    
    LH->>LL: onLogout()
    LL->>LL: Pobierz User
    LL->>ES: logLogout<br/>(userId, email,<br/>ip, userAgent)
    ES->>DB: INSERT events
    ES-->>LL: OK
    LL-->>LH: Continue
    
    LH->>SM: Usuń sesję
    SM->>SM: session_destroy()
    SM->>SM: Usuń PHPSESSID
    
    alt Remember Me aktywny
        SM->>SM: Usuń REMEMBERME
    end
    
    SM-->>LH: Sesja usunięta
    LH-->>B: Redirect → /login
    
    B->>F: GET /login
    F->>SM: Sprawdź sesję
    SM-->>F: Brak sesji
    F-->>B: Render login<br/>message=Wylogowano
    B-->>U: Formularz logowania
```

---

## Architektura Komponentów Autentykacji

### Warstwy systemu:

```
┌─────────────────────────────────────┐
│  Warstwa Prezentacji (Templates)   │
│  - login.html.twig                  │
│  - password_request.html.twig       │
│  - password_reset.html.twig         │
│  - change_password.html.twig        │
└────────────┬────────────────────────┘
             ↓
┌─────────────────────────────────────┐
│  Warstwa Kontrolerów                │
│  - AuthController                   │
│  - PasswordResetController          │
│  - ProfileController                │
└────────────┬────────────────────────┘
             ↓
┌─────────────────────────────────────┐
│  Warstwa Serwisów                   │
│  - UserService                      │
│  - PasswordResetService             │
│  - PasswordChangeService            │
│  - EventService                     │
│  - EmailService                     │
└────────────┬────────────────────────┘
             ↓
┌─────────────────────────────────────┐
│  Symfony Security Bundle            │
│  - Firewall                         │
│  - UserProvider                     │
│  - PasswordHasher                   │
│  - SessionManager                   │
│  - LoginSuccessListener             │
│  - LogoutSuccessListener            │
└────────────┬────────────────────────┘
             ↓
┌─────────────────────────────────────┐
│  Warstwa Danych (Doctrine ORM)      │
│  - UserRepository                   │
│  - User Entity                      │
│  - PasswordResetToken Entity        │
│  - Event Entity                     │
└────────────┬────────────────────────┘
             ↓
┌─────────────────────────────────────┐
│  Baza Danych (MySQL 9.4)            │
│  - users                            │
│  - password_reset_tokens            │
│  - events                           │
└─────────────────────────────────────┘
```

### Kluczowe mechanizmy bezpieczeństwa:

1. **CSRF Protection**
   - Token w każdym formularzu
   - Walidacja przez Symfony Security

2. **Password Hashing**
   - Algorithm: bcrypt
   - Cost: 12

3. **Token Reset Hasła**
   - Długość: 64 znaki
   - Ważność: 1 godzina
   - Jednorazowy użytek

4. **Session Management**
   - Cookie secure (HTTPS)
   - Remember Me: 7 dni
   - Auto-destroy po wylogowaniu

5. **Event Logging**
   - Wszystkie operacje auth
   - IP + User-Agent
   - Timestamp

6. **Role-Based Access Control**
   - ROLE_USER (podstawowy)
   - ROLE_CALL_CENTER (pełny dostęp)
   - ROLE_BOK (read-only)
   - ROLE_ADMIN (administracja)

---

## Operacje na Bazie Danych

| Operacja | Query | Tabela |
|----------|-------|--------|
| Znajdź użytkownika | `SELECT * FROM users WHERE email = ?` | users |
| Update last_login | `UPDATE users SET last_login_at = NOW()` | users |
| Log event | `INSERT INTO events (type, user_id, details)` | events |
| Generuj token reset | `INSERT INTO password_reset_tokens` | password_reset_tokens |
| Waliduj token | `SELECT * WHERE token = ? AND is_used = false` | password_reset_tokens |
| Oznacz token użyty | `UPDATE SET is_used = true` | password_reset_tokens |
| Zmień hasło | `UPDATE users SET password = ?` | users |

---

## Komunikaty Błędów (Przyjazne UX)

| Sytuacja | Komunikat użytkownika |
|----------|----------------------|
| Nieprawidłowy email/hasło | "Nieprawidłowy email lub hasło" |
| CSRF token invalid | "Twoja sesja wygasła. Spróbuj ponownie" |
| Konto nieaktywne | "Twoje konto zostało dezaktywowane" |
| Token reset wygasł | "Link wygasł. Poproś o nowy" |
| Token reset nieprawidłowy | "Link jest nieprawidłowy lub został już użyty" |
| Hasło za krótkie | "Hasło musi mieć co najmniej 8 znaków" |
| Hasła nie pasują | "Hasła muszą być identyczne" |
| Obecne hasło błędne | "Obecne hasło jest nieprawidłowe" |
| Nowe = stare | "Nowe hasło musi różnić się od obecnego" |

---

**Koniec dokumentacji diagramów autentykacji**

