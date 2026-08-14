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

- ✅ (SD-587) The JWT signing secret is no longer derived from a fixed
  source-code string and directory path. It is a random 256-bit value stored
  outside the repo at `restapi/.ht_jwt_secret.bin` (git-ignored), loaded by
  `_jwtLoadSecret()` in `restapi/core/JwtSecretProvisioning.php`. There is no
  fallback secret of any kind - if the file is missing or unreadable, every
  REST endpoint fails closed with a 500 ("REST API is not configured") rather
  than signing with a guessable value.
  - **JWT signing secret is per install, not per tenant** (confirmed SD-634):
    `JWT::secretPath()` is a fixed path relative to the codebase, not a
    per-tenant path, so every tenant database served by one codebase install
    shares the same signing secret. This is intentional - per-tenant scoping
    of a request is enforced by the `tenant_id`/account-id claim inside the
    token payload (checked against the selected account at request time), not
    by using a different signing key per tenant. A multi-tenant host running
    several tenant databases behind one codebase checkout needs exactly one
    `restapi/.ht_jwt_secret.bin`, not one per tenant.
  - (SD-634) `index/install.php:351` was the only code path that created that
    file, and it only ran on a fresh install; since the file is git-ignored, an
    existing install that upgraded in place got a permanently dead REST API.
    `_jwtLoadSecret()` now self-heals by provisioning the file on first use
    (`_jwtProvisionSecret()`, race-safe via `fopen(..., 'xb')`) if it's
    missing, instead of just failing. An existing secret is never overwritten,
    and a directory the web server user can't write to still fails closed,
    now with an actionable log line naming the exact path.
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
