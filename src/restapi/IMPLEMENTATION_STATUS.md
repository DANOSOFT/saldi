# API Implementation Status

## ✅ Implementerede Endpoints

### Authentication & User Management
- ✅ `POST /auth/login` - OAuth2/JWT login med access_token og refresh_token
- ✅ `POST /auth/refresh` - Forny access token med refresh token
- ✅ `GET /user/tenants` - Hent liste over tilgængelige regnskaber (tenants)

### Bilag (Vouchers)
- ✅ `POST /vouchers` - Upload bilag med metadata (multipart/form-data)
- ✅ `GET /vouchers` - Liste over bilag med pagination
- ✅ `GET /vouchers/{id}` - Detaljer for specifikt bilag
- ✅ `GET /vouchers/{id}/image` - Hent originalt billede
- ✅ `GET /vouchers/{id}/thumbnail` - Hent thumbnail (for performance)

### Fakturering (Invoices)
- ✅ `GET /invoices` - Liste over fakturaer med filtering (status=draft|sent|overdue) og pagination
- ✅ `GET /invoices/{id}` - Detaljer for specifik faktura inkl. fakturalinjer
- ✅ `POST /invoices` - Opret ny fakturakladde
- ⚠️ `PUT /invoices/{id}` - Opdater fakturakladde (delvist implementeret)
- ✅ `POST /invoices/{id}/send` - Trigger afsendelse (markerer som sendt)
- ⚠️ `GET /invoices/{id}/pdf` - Hent PDF (placeholder - skal integreres med eksisterende PDF-generering)
- ✅ `GET /vat-codes` - Liste over momskoder

### Dashboard
- ✅ `GET /dashboard/stats` - Returnerer revenue_ytd, overdue_count, overdue_amount

### Kunder (Customers)
- ✅ `GET /customers` - Liste over kunder med søgning (?search=...)
- ✅ `POST /customers` - Opret ny kunde

### Notifikationer
- ✅ `POST /notifications/register` - Registrer device token for push notifikationer
- ✅ `DELETE /notifications/register` - Fjern device token

## 🔧 Tekniske Forbedringer

- ✅ OAuth2/JWT authentication implementeret
- ✅ X-Tenant-ID header support tilføjet
- ✅ CORS konfiguration tilføjet
- ✅ Backward compatibility med eksisterende API key authentication

## 📝 Noter

### Manglende Features (kræver yderligere arbejde)

1. **PDF Generering for Fakturaer**
   - `GET /invoices/{id}/pdf` returnerer kun placeholder
   - Skal integreres med eksisterende PDF-genereringssystem (formfunk.php)

2. **Email Afsendelse**
   - `POST /invoices/{id}/send` markerer kun fakturaen som sendt
   - Skal integreres med eksisterende email-system

3. **Faktura Opdatering**
   - `PUT /invoices/{id}` er delvist implementeret
   - Skal færdiggøres med fuld opdateringslogik

## 🚀 Brug af API

### Authentication Flow

1. **Login:**
```bash
POST /restapi/endpoints/v1/auth/login
Content-Type: application/json

{
  "username": "brugernavn",
  "password": "password"
}

Response:
{
  "success": true,
  "data": {
    "access_token": "jwt_token",
    "refresh_token": "refresh_token",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {...}
  }
}
```

2. **Brug Access Token:**
```bash
GET /restapi/endpoints/v1/user/tenants
Authorization: Bearer {access_token}
X-Tenant-ID: 1
```

3. **Refresh Token:**
```bash
POST /restapi/endpoints/v1/auth/refresh
Content-Type: application/json

{
  "refresh_token": "refresh_token"
}
```

### Eksempel: Upload Bilag

```bash
POST /restapi/endpoints/v1/vouchers
Authorization: Bearer {access_token}
X-Tenant-ID: 1
Content-Type: multipart/form-data

file: [billedfil.jpg]
belob: 1250.00
dato: 2025-11-20
beskrivelse: "Udgift til kontor"
kategori: "kladde"
```

## 📚 Swagger Dokumentation

Swagger/OpenAPI dokumentation findes i `swagger.yaml` filen. Den skal opdateres med de nye endpoints.

