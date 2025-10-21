# Schemat bazy danych MySQL - Lead Management System (LMS)

## 1. Lista tabel z kolumnami, typami danych i ograniczeniami

### 1.1 Tabela `customers`
```sql
CREATE TABLE customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_email_phone (email, phone),
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    FULLTEXT idx_fulltext_search (first_name, last_name, email, phone)
);
```

### 1.2 Tabela `leads`
```sql
CREATE TABLE leads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_uuid CHAR(36) NOT NULL UNIQUE,
    customer_id INT UNSIGNED NOT NULL,
    application_name VARCHAR(50) NOT NULL,
    status ENUM('new', 'contacted', 'qualified', 'converted', 'rejected') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    
    INDEX idx_customer_created (customer_id, created_at),
    INDEX idx_application_created (application_name, created_at),
    INDEX idx_status_created (status, created_at),
    INDEX idx_lead_uuid (lead_uuid)
);
```

### 1.3 Tabela `lead_properties`
```sql
CREATE TABLE lead_properties (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_id INT UNSIGNED NOT NULL,
    property_id VARCHAR(100),
    development_id VARCHAR(100),
    partner_id VARCHAR(100),
    property_type VARCHAR(50),
    price DECIMAL(15,2),
    location VARCHAR(255),
    city VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    INDEX idx_lead_id (lead_id),
    INDEX idx_property_id (property_id),
    INDEX idx_development_id (development_id)
);
```

### 1.4 Tabela `customer_preferences`
```sql
CREATE TABLE customer_preferences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    price_min DECIMAL(15,2),
    price_max DECIMAL(15,2),
    location VARCHAR(255),
    city VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_customer_id (customer_id)
);
```

### 1.5 Tabela `users`
```sql
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('call_center', 'bok', 'admin') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
);
```

### 1.6 Tabela `permissions`
```sql
CREATE TABLE permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    resource VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_resource_action (resource, action)
);
```

### 1.7 Tabela `user_permissions` (many-to-many)
```sql
CREATE TABLE user_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_permission (user_id, permission_id)
);
```

### 1.8 Tabela `user_sessions`
```sql
CREATE TABLE user_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_token (session_token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);
```

### 1.9 Tabela `failed_deliveries`
```sql
CREATE TABLE failed_deliveries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_id INT UNSIGNED NOT NULL,
    cdp_system_name VARCHAR(50) NOT NULL,
    error_code VARCHAR(50),
    error_message TEXT,
    retry_count INT UNSIGNED DEFAULT 0,
    max_retries INT UNSIGNED DEFAULT 3,
    next_retry_at TIMESTAMP,
    status ENUM('pending', 'retrying', 'failed', 'resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    INDEX idx_lead_id (lead_id),
    INDEX idx_status_next_retry (status, next_retry_at),
    INDEX idx_created_at (created_at)
);
```

### 1.10 Tabela `retry_queue`
```sql
CREATE TABLE retry_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    attempts INT UNSIGNED DEFAULT 0,
    max_attempts INT UNSIGNED DEFAULT 3,
    next_retry_at TIMESTAMP NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    
    INDEX idx_status_next_retry (status, next_retry_at),
    INDEX idx_message_type (message_type),
    INDEX idx_created_at (created_at)
);
```

### 1.11 Tabela `system_config`
```sql
CREATE TABLE system_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value JSON NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_config_key (config_key),
    INDEX idx_is_active (is_active)
);
```

### 1.12 Tabela `events` (partycjonowana)
```sql
CREATE TABLE events (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    event_type ENUM(
        'lead_created', 'lead_updated', 'lead_deleted',
        'cdp_delivery_success', 'cdp_delivery_failed',
        'api_request', 'user_login', 'user_logout',
        'preference_updated', 'customer_created', 'customer_updated'
    ) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT UNSIGNED,
    user_id INT UNSIGNED,
    details JSON,
    retry_count INT UNSIGNED DEFAULT 0,
    error_message TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_event_type_created (event_type, created_at),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) PARTITION BY RANGE (UNIX_TIMESTAMP(created_at)) (
    PARTITION p202401 VALUES LESS THAN (UNIX_TIMESTAMP('2024-02-01')),
    PARTITION p202402 VALUES LESS THAN (UNIX_TIMESTAMP('2024-03-01')),
    PARTITION p202403 VALUES LESS THAN (UNIX_TIMESTAMP('2024-04-01')),
    PARTITION p202404 VALUES LESS THAN (UNIX_TIMESTAMP('2024-05-01')),
    PARTITION p202405 VALUES LESS THAN (UNIX_TIMESTAMP('2024-06-01')),
    PARTITION p202406 VALUES LESS THAN (UNIX_TIMESTAMP('2024-07-01')),
    PARTITION p202407 VALUES LESS THAN (UNIX_TIMESTAMP('2024-08-01')),
    PARTITION p202408 VALUES LESS THAN (UNIX_TIMESTAMP('2024-09-01')),
    PARTITION p202409 VALUES LESS THAN (UNIX_TIMESTAMP('2024-10-01')),
    PARTITION p202410 VALUES LESS THAN (UNIX_TIMESTAMP('2024-11-01')),
    PARTITION p202411 VALUES LESS THAN (UNIX_TIMESTAMP('2024-12-01')),
    PARTITION p202412 VALUES LESS THAN (UNIX_TIMESTAMP('2025-01-01')),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

## 2. Relacje między tabelami

### 2.1 Relacje jeden-do-wielu (1:N)
- `customers` → `leads` (jeden klient może mieć wiele leadów)
- `customers` → `customer_preferences` (jeden klient ma jedną tabelę preferencji)
- `leads` → `lead_properties` (jeden lead ma jedną tabelę właściwości)
- `users` → `user_permissions` (jeden użytkownik może mieć wiele uprawnień)
- `permissions` → `user_permissions` (jedno uprawnienie może być przypisane do wielu użytkowników)
- `users` → `user_sessions` (jeden użytkownik może mieć wiele sesji)
- `users` → `events` (jeden użytkownik może generować wiele eventów)
- `leads` → `failed_deliveries` (jeden lead może mieć wiele nieudanych dostaw)

### 2.2 Relacje wiele-do-wielu (M:N)
- `users` ↔ `permissions` (przez tabelę `user_permissions`)

### 2.3 Relacje jeden-do-jednego (1:1)
- `customers` ↔ `customer_preferences` (jeden klient ma jedną tabelę preferencji)

## 3. Indeksy

### 3.1 Indeksy podstawowe
- Wszystkie klucze główne (PRIMARY KEY)
- Wszystkie klucze obce (FOREIGN KEY)

### 3.2 Indeksy wydajnościowe
- `customers`: `unique_email_phone`, `idx_email`, `idx_phone`, `idx_fulltext_search`
- `leads`: `idx_customer_created`, `idx_application_created`, `idx_status_created`, `idx_lead_uuid`
- `lead_properties`: `idx_lead_id`, `idx_property_id`, `idx_development_id`
- `customer_preferences`: `idx_customer_id`
- `users`: `idx_username`, `idx_email`, `idx_role`
- `permissions`: `idx_resource_action`
- `user_sessions`: `idx_session_token`, `idx_user_id`, `idx_expires_at`
- `failed_deliveries`: `idx_lead_id`, `idx_status_next_retry`, `idx_created_at`
- `retry_queue`: `idx_status_next_retry`, `idx_message_type`, `idx_created_at`
- `system_config`: `idx_config_key`, `idx_is_active`
- `events`: `idx_event_type_created`, `idx_entity`, `idx_user_id`, `idx_created_at`

### 3.3 Indeksy pełnotekstowe
- `customers`: `idx_fulltext_search` na polach `first_name`, `last_name`, `email`, `phone`

## 4. Zasady MySQL (RLS - Row Level Security)

### 4.1 Widoki bezpieczeństwa dla ról użytkowników

```sql
-- Widok dla Call Center (pełny dostęp)
CREATE VIEW leads_call_center AS
SELECT l.*, c.*, lp.*, l.application_name
FROM leads l
JOIN customers c ON l.customer_id = c.id
LEFT JOIN lead_properties lp ON l.id = lp.lead_id;

-- Widok dla BOK (read-only)
CREATE VIEW leads_bok AS
SELECT l.id, l.lead_uuid, l.status, l.created_at, l.application_name,
       c.email, c.phone, c.first_name, c.last_name
FROM leads l
JOIN customers c ON l.customer_id = c.id;
```

### 4.2 Procedury bezpieczeństwa

```sql
-- Procedura sprawdzania uprawnień użytkownika
DELIMITER //
CREATE PROCEDURE CheckUserPermission(
    IN p_user_id INT UNSIGNED,
    IN p_resource VARCHAR(100),
    IN p_action VARCHAR(50),
    OUT p_has_permission BOOLEAN
)
BEGIN
    DECLARE permission_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO permission_count
    FROM user_permissions up
    JOIN permissions p ON up.permission_id = p.id
    WHERE up.user_id = p_user_id
    AND p.resource = p_resource
    AND p.action = p_action;
    
    SET p_has_permission = (permission_count > 0);
END //
DELIMITER ;
```

## 5. Dodatkowe uwagi i wyjaśnienia

### 5.1 Decyzje projektowe
- **Deduplikacja**: Implementowana przez unikalny indeks na kombinacji email+phone w tabeli `customers`
- **Logowanie**: Centralizacja w tabeli `events` z partycjonowaniem miesięcznym dla wydajności
- **Retencja danych**: Eventy automatycznie usuwane po 1 roku dzięki partycjonowaniu
- **Skalowalność**: Przygotowanie na 10,000 leadów dziennie z odpowiednimi indeksami
- **Bezpieczeństwo**: Role-based access control z tabelami `users`, `permissions`, `user_permissions`
- **API Connections**: Logika połączeń API przeniesiona do kodu aplikacji, tabela `applications` usunięta
- **Lead Sources**: Logika źródeł leadów przeniesiona do kodu aplikacji, tabela `lead_sources` usunięta
- **Lead Tags**: System tagowania leadów usunięty - tylko nowe leady są "hot"
- **CDP Systems**: Konfiguracja systemów CDP przeniesiona do kodu aplikacji, tabela `cdp_systems` usunięta

### 5.2 Optymalizacje wydajności
- Partycjonowanie tabeli `events` po dacie dla szybkiego dostępu do najnowszych danych
- Indeksy złożone na często używanych kombinacjach pól
- Indeksy pełnotekstowe dla wyszukiwania klientów
- JSON dla elastycznego przechowywania danych konfiguracyjnych i szczegółów

### 5.3 Zgodność z wymaganiami PRD
- Obsługa leadów z aplikacji Morizon, Gratka, Homsters
- Automatyczna deduplikacja klientów
- Integracja z systemami CDP (SalesManago, Murapol, DomDevelopment)
- Role Call Center i BOK z odpowiednimi uprawnieniami
- Kompleksowe logowanie wszystkich operacji
- Mechanizm retry dla nieudanych dostaw

### 5.4 Zgodność ze stosem technologicznym
- Optymalizacja pod MySQL 9.4
- Wsparcie dla Doctrine ORM (Symfony)
- Przygotowanie na integrację z RabbitMQ
- Struktura gotowa na cache'owanie przez Redis
