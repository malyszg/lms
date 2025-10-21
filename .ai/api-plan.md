# REST API Plan

## 1. Resources
- **customers** - Customer data with deduplication (email, phone unique)
- **leads** - Lead records with application source tracking
- **lead-properties** - Property details associated with leads
- **customer-preferences** - Customer preferences (price, location, etc.)
- **users** - System users with role-based access
- **permissions** - System permissions
- **user-sessions** - Active user sessions
- **failed-deliveries** - Failed CDP delivery attempts
- **retry-queue** - Retry queue for RabbitMQ
- **events** - System event logging
- **system-config** - System configuration settings

## 2. Endpoints

### Authentication

#### POST /api/auth/login
- **Description**: User login to LMS panel
- **Request Body**:
  ```json
  {
    "username": "string",
    "password": "string"
  }
  ```
- **Response**:
  ```json
  {
    "token": "string",
    "user": {
      "id": "integer",
      "username": "string",
      "email": "string",
      "role": "call_center|bok|admin",
      "permissions": ["string"]
    },
    "expires_at": "datetime"
  }
  ```
- **Success**: 200 OK
- **Errors**: 401 Unauthorized, 400 Bad Request

#### POST /api/auth/logout
- **Description**: User logout
- **Headers**: Authorization: Bearer {token}
- **Response**: 204 No Content
- **Success**: 204 No Content
- **Errors**: 401 Unauthorized

### Leads Management

#### POST /api/leads
- **Description**: Create new lead from source applications
- **Request Body (Morizon/Gratka)**:
  ```json
  {
    "lead_uuid": "string (UUID)",
    "application_name": "morizon|gratka",
    "customer": {
      "email": "string",
      "phone": "string",
      "first_name": "string",
      "last_name": "string"
    },
    "property": {
      "property_id": "string",
      "development_id": "string",
      "partner_id": "string",
      "property_type": "string",
      "price": "decimal",
      "location": "string",
      "city": "string"
    }
  }
  ```
- **Request Body (Homsters)**:
  ```json
  {
    "lead_uuid": "string (UUID)",
    "application_name": "homsters",
    "customer": {
      "email": "string",
      "phone": "string",
      "first_name": "string",
      "last_name": "string"
    },
    "property": {
      "hms_property_id": "string",
      "hms_project_id": "string",
      "hms_partner_id": "string",
      "property_type": "string",
      "price": "decimal",
      "location": "string",
      "city": "string"
    }
  }
  ```
  **Note**: System automatically maps Homsters fields: `hms_project_id` → `development_id`, `hms_property_id` → `property_id`, `hms_partner_id` → `partner_id`
- **Response**:
  ```json
  {
    "id": "integer",
    "lead_uuid": "string",
    "status": "new",
    "customer_id": "integer",
    "application_name": "string",
    "created_at": "datetime",
    "cdp_delivery_status": "pending|success|failed"
  }
  ```
- **Success**: 201 Created
- **Errors**: 400 Bad Request (validation), 409 Conflict (duplicate), 422 Unprocessable Entity

#### GET /api/leads
- **Description**: Get list of leads with filtering and sorting
- **Query Parameters**:
  - `page` (integer, default: 1)
  - `limit` (integer, default: 20, max: 100)
  - `status` (string: new|contacted|qualified|converted|rejected)
  - `application_name` (string)
  - `customer_email` (string)
  - `customer_phone` (string)
  - `created_from` (datetime)
  - `created_to` (datetime)
  - `sort` (string: created_at|status|application_name)
  - `order` (string: asc|desc, default: desc)
- **Response**:
  ```json
  {
    "data": [
      {
        "id": "integer",
        "lead_uuid": "string",
        "status": "string",
        "created_at": "datetime",
        "customer": {
          "id": "integer",
          "email": "string",
          "phone": "string",
          "first_name": "string",
          "last_name": "string"
        },
        "application_name": "string",
        "property": {
          "property_id": "string",
          "development_id": "string",
          "price": "decimal",
          "location": "string"
        }
      }
    ],
    "pagination": {
      "current_page": "integer",
      "per_page": "integer",
      "total": "integer",
      "last_page": "integer"
    }
  }
  ```
- **Success**: 200 OK
- **Errors**: 400 Bad Request, 401 Unauthorized

#### GET /api/leads/{id}
- **Description**: Get specific lead details
- **Response**:
  ```json
  {
    "id": "integer",
    "lead_uuid": "string",
    "status": "string",
    "created_at": "datetime",
    "updated_at": "datetime",
    "customer": {
      "id": "integer",
      "email": "string",
      "phone": "string",
      "first_name": "string",
      "last_name": "string",
      "preferences": {
        "price_min": "decimal",
        "price_max": "decimal",
        "location": "string",
        "city": "string"
      }
    },
    "application_name": "string",
    "property": {
      "property_id": "string",
      "development_id": "string",
      "partner_id": "string",
      "property_type": "string",
      "price": "decimal",
      "location": "string",
      "city": "string"
    },
    "events": [
      {
        "event_type": "string",
        "created_at": "datetime",
        "details": "object"
      }
    ]
  }
  ```
- **Success**: 200 OK
- **Errors**: 404 Not Found, 401 Unauthorized

#### PUT /api/leads/{id}
- **Description**: Update lead status
- **Request Body**:
  ```json
  {
    "status": "contacted|qualified|converted|rejected"
  }
  ```
- **Response**: Same as GET /api/leads/{id}
- **Success**: 200 OK
- **Errors**: 400 Bad Request, 404 Not Found, 401 Unauthorized, 403 Forbidden

#### DELETE /api/leads/{id}
- **Description**: Delete lead from system
- **Response**: 204 No Content
- **Success**: 204 No Content
- **Errors**: 404 Not Found, 401 Unauthorized, 403 Forbidden

### Customer Management

#### GET /api/customers
- **Description**: Get list of customers with search
- **Query Parameters**:
  - `page` (integer, default: 1)
  - `limit` (integer, default: 20, max: 100)
  - `search` (string: search in name, email, phone)
  - `sort` (string: created_at|email|phone)
  - `order` (string: asc|desc, default: desc)
- **Response**:
  ```json
  {
    "data": [
      {
        "id": "integer",
        "email": "string",
        "phone": "string",
        "first_name": "string",
        "last_name": "string",
        "created_at": "datetime",
        "leads_count": "integer",
        "last_lead_at": "datetime"
      }
    ],
    "pagination": {
      "current_page": "integer",
      "per_page": "integer",
      "total": "integer",
      "last_page": "integer"
    }
  }
  ```
- **Success**: 200 OK
- **Errors**: 400 Bad Request, 401 Unauthorized

#### GET /api/customers/{id}
- **Description**: Get customer details with all leads
- **Response**:
  ```json
  {
    "id": "integer",
    "email": "string",
    "phone": "string",
    "first_name": "string",
    "last_name": "string",
    "created_at": "datetime",
    "preferences": {
      "price_min": "decimal",
      "price_max": "decimal",
      "location": "string",
      "city": "string"
    },
    "leads": [
      {
        "id": "integer",
        "lead_uuid": "string",
        "status": "string",
        "application_name": "string",
        "created_at": "datetime"
      }
    ]
  }
  ```
- **Success**: 200 OK
- **Errors**: 404 Not Found, 401 Unauthorized

#### PUT /api/customers/{id}/preferences
- **Description**: Update customer preferences
- **Request Body**:
  ```json
  {
    "price_min": "decimal",
    "price_max": "decimal",
    "location": "string",
    "city": "string"
  }
  ```
- **Response**:
  ```json
  {
    "id": "integer",
    "price_min": "decimal",
    "price_max": "decimal",
    "location": "string",
    "city": "string",
    "updated_at": "datetime"
  }
  ```
- **Success**: 200 OK
- **Errors**: 400 Bad Request, 404 Not Found, 401 Unauthorized, 403 Forbidden

### Failed Deliveries Management

#### GET /api/failed-deliveries
- **Description**: Get list of failed CDP deliveries
- **Query Parameters**:
  - `page` (integer, default: 1)
  - `limit` (integer, default: 20, max: 100)
  - `status` (string: pending|retrying|failed|resolved)
  - `cdp_system_name` (string)
  - `created_from` (datetime)
  - `created_to` (datetime)
- **Response**:
  ```json
  {
    "data": [
      {
        "id": "integer",
        "lead_id": "integer",
        "cdp_system_name": "string",
        "error_code": "string",
        "error_message": "string",
        "retry_count": "integer",
        "max_retries": "integer",
        "next_retry_at": "datetime",
        "status": "string",
        "created_at": "datetime",
        "lead": {
          "lead_uuid": "string",
          "customer": {
            "email": "string",
            "phone": "string"
          }
        }
      }
    ],
    "pagination": {
      "current_page": "integer",
      "per_page": "integer",
      "total": "integer",
      "last_page": "integer"
    }
  }
  ```
- **Success**: 200 OK
- **Errors**: 400 Bad Request, 401 Unauthorized

#### POST /api/failed-deliveries/{id}/retry
- **Description**: Manually retry failed delivery
- **Response**:
  ```json
  {
    "id": "integer",
    "status": "retrying",
    "retry_count": "integer",
    "next_retry_at": "datetime",
    "message": "Retry initiated"
  }
  ```
- **Success**: 200 OK
- **Errors**: 404 Not Found, 400 Bad Request, 401 Unauthorized, 403 Forbidden

### Events Management

#### GET /api/events
- **Description**: Get system events for monitoring
- **Query Parameters**:
  - `page` (integer, default: 1)
  - `limit` (integer, default: 50, max: 200)
  - `event_type` (string)
  - `entity_type` (string)
  - `entity_id` (integer)
  - `user_id` (integer)
  - `created_from` (datetime)
  - `created_to` (datetime)
  - `lead_uuid` (string)
- **Response**:
  ```json
  {
    "data": [
      {
        "id": "integer",
        "event_type": "string",
        "entity_type": "string",
        "entity_id": "integer",
        "user_id": "integer",
        "details": "object",
        "ip_address": "string",
        "created_at": "datetime"
      }
    ],
    "pagination": {
      "current_page": "integer",
      "per_page": "integer",
      "total": "integer",
      "last_page": "integer"
    }
  }
  ```
- **Success**: 200 OK
- **Errors**: 400 Bad Request, 401 Unauthorized

### System Configuration

#### GET /api/system-config
- **Description**: Get system configuration
- **Response**:
  ```json
  {
    "data": [
      {
        "config_key": "string",
        "config_value": "object",
        "description": "string",
        "is_active": "boolean"
      }
    ]
  }
  ```
- **Success**: 200 OK
- **Errors**: 401 Unauthorized, 403 Forbidden

#### PUT /api/system-config/{key}
- **Description**: Update system configuration
- **Request Body**:
  ```json
  {
    "config_value": "object",
    "description": "string"
  }
  ```
- **Response**:
  ```json
  {
    "config_key": "string",
    "config_value": "object",
    "description": "string",
    "updated_at": "datetime"
  }
  ```
- **Success**: 200 OK
- **Errors**: 400 Bad Request, 401 Unauthorized, 403 Forbidden

## 3. Authentication and Authorization

### Authentication Mechanism
- **JWT Token-based authentication**
- Token issued on successful login with 24-hour expiration
- Token stored in `user_sessions` table for session management
- Token includes user ID, role, and permissions

### Authorization
- **Role-based access control (RBAC)**
- **Call Center Role**: Full access to all lead operations, customer preferences editing
- **BOK Role**: Read-only access to leads and customers
- **Admin Role**: Full system access including configuration management

### Security Headers
- All requests require `Authorization: Bearer {token}` header
- Rate limiting: 1000 requests per minute per user
- IP address logging for audit trail
- CSRF protection for state-changing operations

## 4. Validation and Business Logic

### Validation Rules

#### Leads
- `lead_uuid`: Required, valid UUID format, unique
- `application_name`: Required, must be one of: morizon, gratka, homsters
- `customer.email`: Required, valid email format
- `customer.phone`: Required, valid phone format

#### Customers
- `email`: Required, valid email format, unique
- `phone`: Required, valid phone format
- Email and phone combination must be unique

#### User Sessions
- Token validation on every request
- Session expiration check
- Automatic cleanup of expired sessions

### Business Logic Implementation

#### Lead Creation (POST /api/leads)
1. Validate input data
2. Check for existing customer by email/phone (deduplication)
3. Create or update customer record
4. Create lead record
5. Create lead_properties record
6. Log event: 'lead_created'
7. Trigger CDP delivery process asynchronously
8. Return lead with delivery status

#### CDP Delivery Process
1. Attempt delivery to configured CDP systems
2. On failure, create failed_deliveries record
3. Implement exponential backoff retry mechanism
4. Log all delivery attempts in events table

#### Customer Deduplication
1. Search existing customers by email and phone
2. If found, link new lead to existing customer
3. If not found, create new customer record
4. Log deduplication decision in events

#### Lead Status Updates
1. Validate status transition rules
2. Update lead status
3. Log event: 'lead_updated'
4. Trigger notifications if required

#### Failed Delivery Retry
1. Validate retry eligibility (not exceeded max_retries)
2. Update retry_count and next_retry_at
3. Queue retry job for background processing
4. Log retry attempt in events

### Error Handling
- Consistent error response format:
  ```json
  {
    "error": {
      "code": "string",
      "message": "string",
      "details": "object"
    }
  }
  ```
- HTTP status codes: 200, 201, 204, 400, 401, 403, 404, 409, 422, 500
- Validation errors return 422 with field-specific error details
- Business logic errors return appropriate 4xx codes with descriptive messages
