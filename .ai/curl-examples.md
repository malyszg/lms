# cURL Examples - POST /api/leads

> **⚠️ UUID Patterns:**
> - **Production/Manual Testing**: Use UUIDs like `11111111-2222-4333-8444-5555555500XX` (valid UUID v4 format)
> - **Automated Tests**: Use UUIDs starting with `a1b2c3d4-e5f6-41d4-a716-44665544XXXX` (reserved for PHPUnit tests)
> - **Never use test UUIDs** for production data to avoid conflicts!
> - **Note**: The `4` in position 15 and `8` in position 20 are required for valid UUID v4 format

## 🎯 Morizon Examples

### Example 1: Full lead data from Morizon (apartment)
```bash
curl -X POST http://localhost:8082/api/leads \
  -H "Content-Type: application/json" \
  -d '{
    "lead_uuid": "11111111-2222-4333-8444-555555550001",
    "application_name": "morizon",
    "customer": {
      "email": "jan.kowalski@gmail.com",
      "phone": "+48601234567",
      "first_name": "Jan",
      "last_name": "Kowalski"
    },
    "property": {
      "property_id": "MRZ-2024-001234",
      "development_id": "DEV-MOKOTOW-01",
      "partner_id": "PARTNER-DEVELOPER-123",
      "property_type": "apartment",
      "price": 850000.00,
      "location": "ul. Bukowińska 24A, Mokotów",
      "city": "Warszawa"
    }
  }'
```

### Example 2: Minimal lead from Morizon
```bash
curl -X POST http://localhost:8082/api/leads \
  -H "Content-Type: application/json" \
  -d '{
    "lead_uuid": "11111111-2222-4333-8444-555555550002",
    "application_name": "morizon",
    "customer": {
      "email": "klient@example.com",
      "phone": "+48602345678"
    },
    "property": {}
  }'
```

---

## 🏡 Gratka Examples

### Example 1: Full lead data from Gratka
```bash
curl -X POST http://localhost:8082/api/leads \
  -H "Content-Type: application/json" \
  -d '{
    "lead_uuid": "11111111-2222-4333-8444-555555550003",
    "application_name": "gratka",
    "customer": {
      "email": "anna.nowak@outlook.com",
      "phone": "+48603456789",
      "first_name": "Anna",
      "last_name": "Nowak"
    },
    "property": {
      "property_id": "GRT-2024-005678",
      "development_id": "DEV-WILANOW-03",
      "partner_id": "PARTNER-456",
      "property_type": "house",
      "price": 1250000.00,
      "location": "Wilanów, ul. Sarmacka 15",
      "city": "Warszawa"
    }
  }'
```

---

## 🏘️ Homsters Examples (Different Structure)

### Example 1: Full lead data from Homsters (uses hms_* fields)
```bash
curl -X POST http://localhost:8082/api/leads \
  -H "Content-Type: application/json" \
  -d '{
    "lead_uuid": "11111111-2222-4333-8444-555555550004",
    "application_name": "homsters",
    "customer": {
      "email": "adam.kowalczyk@gmail.com",
      "phone": "+48701234567",
      "first_name": "Adam",
      "last_name": "Kowalczyk"
    },
    "property": {
      "hms_property_id": "HMS-PROP-001234",
      "hms_project_id": "HMS-PROJ-CENTRUM-01",
      "hms_partner_id": "HMS-PARTNER-789",
      "property_type": "apartment",
      "price": 920000.00,
      "location": "ul. Marszałkowska 140, Śródmieście",
      "city": "Warszawa"
    }
  }'
```

### Example 2: Homsters house lead
```bash
curl -X POST http://localhost:8082/api/leads \
  -H "Content-Type: application/json" \
  -d '{
    "lead_uuid": "11111111-2222-4333-8444-555555550005",
    "application_name": "homsters",
    "customer": {
      "email": "karolina.wojcik@outlook.com",
      "phone": "+48702345678",
      "first_name": "Karolina",
      "last_name": "Wójcik"
    },
    "property": {
      "hms_property_id": "HMS-PROP-005678",
      "hms_project_id": "HMS-PROJ-PIASECZNO-02",
      "hms_partner_id": "HMS-PARTNER-456",
      "property_type": "house",
      "price": 1450000.00,
      "location": "Piaseczno, ul. Leśna 12",
      "city": "Piaseczno"
    }
  }'
```

### Example 3: Minimal Homsters lead
```bash
curl -X POST http://localhost:8082/api/leads \
  -H "Content-Type: application/json" \
  -d '{
    "lead_uuid": "11111111-2222-4333-8444-555555550006",
    "application_name": "homsters",
    "customer": {
      "email": "nowy.klient@homsters.pl",
      "phone": "+48703456789"
    },
    "property": {}
  }'
```

### Example 4: Homsters penthouse (luxury)
```bash
curl -X POST http://localhost:8082/api/leads \
  -H "Content-Type: application/json" \
  -d '{
    "lead_uuid": "11111111-2222-4333-8444-555555550007",
    "application_name": "homsters",
    "customer": {
      "email": "lukasz.zielinski@icloud.com",
      "phone": "+48704567890",
      "first_name": "Łukasz",
      "last_name": "Zieliński"
    },
    "property": {
      "hms_property_id": "HMS-PROP-009999",
      "hms_project_id": "HMS-PROJ-ZOLIBORZ-04",
      "hms_partner_id": "HMS-PARTNER-PREMIUM-555",
      "property_type": "penthouse",
      "price": 2150000.00,
      "location": "Żoliborz, ul. Krasińskiego 88",
      "city": "Warszawa"
    }
  }'
```

---

## 📊 Field Mapping Reference

| Application | Property ID | Development/Project | Partner ID |
|-------------|-------------|---------------------|------------|
| **Morizon** | `property_id` | `development_id` | `partner_id` |
| **Gratka** | `property_id` | `development_id` | `partner_id` |
| **Homsters** | `hms_property_id` | `hms_project_id` | `hms_partner_id` |

### Automatic Transformation

The system **automatically transforms** Homsters fields to standard format:
- `hms_property_id` → `property_id`
- `hms_project_id` → `development_id` 
- `hms_partner_id` → `partner_id`

This happens transparently in `LeadRequestTransformer` before validation.

---

## ✅ Expected Response (201 Created)

```json
{
  "id": 12,
  "leadUuid": "11111111-2222-4333-8444-555555550001",
  "status": "new",
  "customerId": 8,
  "applicationName": "homsters",
  "createdAt": "2025-10-11T14:35:00+02:00",
  "cdpDeliveryStatus": "pending"
}
```

---

## 🚀 Quick Test Scripts

### Test Morizon Lead
```bash
#!/bin/bash
UUID=$(uuidgen | tr '[:upper:]' '[:lower:]')
TIMESTAMP=$(date +%s)

curl -X POST http://localhost:8082/api/leads \
  -H "Content-Type: application/json" \
  -d "{
    \"lead_uuid\": \"${UUID}\",
    \"application_name\": \"morizon\",
    \"customer\": {
      \"email\": \"test${TIMESTAMP}@morizon.pl\",
      \"phone\": \"+48${TIMESTAMP: -9}\",
      \"first_name\": \"TestMorizon\",
      \"last_name\": \"User\"
    },
    \"property\": {
      \"property_id\": \"MRZ-${TIMESTAMP}\",
      \"development_id\": \"DEV-${TIMESTAMP}\",
      \"partner_id\": \"PARTNER-${TIMESTAMP}\",
      \"property_type\": \"apartment\",
      \"price\": 750000.00,
      \"city\": \"Warszawa\"
    }
  }" | python3 -m json.tool
```

### Test Homsters Lead
```bash
#!/bin/bash
UUID=$(uuidgen | tr '[:upper:]' '[:lower:]')
TIMESTAMP=$(date +%s)

curl -X POST http://localhost:8082/api/leads \
  -H "Content-Type: application/json" \
  -d "{
    \"lead_uuid\": \"${UUID}\",
    \"application_name\": \"homsters\",
    \"customer\": {
      \"email\": \"test${TIMESTAMP}@homsters.pl\",
      \"phone\": \"+48${TIMESTAMP: -9}\",
      \"first_name\": \"TestHomsters\",
      \"last_name\": \"User\"
    },
    \"property\": {
      \"hms_property_id\": \"HMS-PROP-${TIMESTAMP}\",
      \"hms_project_id\": \"HMS-PROJ-${TIMESTAMP}\",
      \"hms_partner_id\": \"HMS-PARTNER-${TIMESTAMP}\",
      \"property_type\": \"apartment\",
      \"price\": 825000.00,
      \"location\": \"Test Location\",
      \"city\": \"Warszawa\"
    }
  }" | python3 -m json.tool
```

---

## 🔍 Testing the Transformation

You can verify that Homsters data is correctly transformed by checking the database after sending a lead:

```bash
# Send Homsters lead
curl -X POST http://localhost:8082/api/leads \
  -H "Content-Type: application/json" \
  -d '{...homsters data with hms_* fields...}'

# Check database - should show standard fields
docker exec lms_mysql mysql -u lms -plms_password lms \
  -e "SELECT property_id, development_id, partner_id FROM lead_properties ORDER BY id DESC LIMIT 1;"
```

The `property_id`, `development_id`, and `partner_id` columns should contain the values from the Homsters `hms_*` fields.

