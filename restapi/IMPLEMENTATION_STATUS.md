# REST API Implementation Status

Audit updated 2026-07-15. The source code is authoritative where this file and
`swagger.yaml` differ.

## Authentication

- ✅ `POST /auth/login.php` resolves the selected account through the registry
  database, authenticates its user in the account database, and returns a
  one-hour JWT access token plus a 30-day refresh token.
- ✅ The local `POST /auth/refresh.php` implementation renews an access token and
  preserves the account ID in the legacy `tenant_id` claim. The ssl12 `/ntr`
  deployment passed login, refresh, and authenticated account requests on
  2026-07-15. The ssl3 deployment still runs the older implementation and returned
  `401 User not found` during the audit.
- ❌ **Unsupported: `GET /user/tenants`. Do not use it.** It takes a user ID from
  an account database and incorrectly looks up the same numeric ID in the registry
  database. Those user-ID namespaces are unrelated. The endpoint is not included
  in Swagger and needs an explicit cross-account identity design before use.
- ❌ The old `x-db`, `x-saldiuser`, and static API-key headers are not accepted by
  endpoints that extend `BaseEndpoint`.
- ⚠️ `X-Tenant-ID` retains its legacy name. It is only a compatibility fallback
  for older tokens; its value is the numeric account ID (`regnskab.id`). New
  access and refresh tokens include that ID in the `tenant_id` claim.

## Endpoint observations

- The core order, order-line, customer, creditor, product, inventory, account,
  currency, accounting-year, attachment, and VAT endpoints are present under the
  paths documented in `swagger.yaml`.
- Product-group deletion is disabled in the implementation and is no longer
  advertised in Swagger.
- Attachment upload accepts base64-encoded JSON at `POST /attachment`, not
  `multipart/form-data`.
- Invoice code is located at `/debitor/invoices`; update and PDF operations return
  HTTP 501 and are incomplete.
- `/vouchers` is referenced by the v1 router and tests, but
  `endpoints/v1/vouchers/index.php` is absent.
- The v1 router also refers to top-level `/invoices` and `/customers` files that
  are absent; the implementations live below `/debitor`.
- Notification registration creates its table at request time and requires more
  work before it should be exposed as a supported endpoint.

## Security follow-up

- `JWT::getDefaultSecret()` derives a predictable signing secret from a fixed
  source-code string and directory path. Move the secret to deployment-managed
  configuration and rotate it; rotation will invalidate existing tokens.
- JWT authentication verifies identity and the selected account, but the shared endpoint layer
  does not enforce per-user Saldi permissions for individual REST operations.
- The legacy API-key IP allowlist is not applied to JWT requests.
- Login has no endpoint-level rate limiting or lockout.

## Authentication flow

1. Obtain tokens:

```http
POST ./endpoints/v1/auth/login.php
Content-Type: application/json

{
  "username": "api",
  "password": "the password configured for the api user",
  "account_name": "the account name shown in Saldi"
}
```

2. Send `data.access_token` on protected requests:

```http
GET ./endpoints/v1/accounts/
Authorization: Bearer <access_token>
```

3. Refresh before or after the one-hour access-token expiry:

```http
POST ./endpoints/v1/auth/refresh.php
Content-Type: application/json

{
  "refresh_token": "<refresh_token>"
}
```

## Attachment example

```http
POST ./endpoints/v1/attachment
Authorization: Bearer <access_token>
Content-Type: application/json

{
  "image_base64": "<base64-data>",
  "filename": "invoice.jpg",
  "extracted_data": {
    "total_amount": 1250.00,
    "invoice_date": "2026-07-15",
    "invoice_description": "Office expense",
    "currency": "DKK"
  }
}
```

The interactive OpenAPI documentation is generated from `swagger.yaml`.
