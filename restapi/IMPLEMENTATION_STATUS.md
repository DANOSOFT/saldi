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
  fallback secret of any kind - a missing file is provisioned automatically
  (see SD-634 below), but if that provisioning fails, or the file is
  otherwise unreadable or invalid, every REST endpoint fails closed with a
  500 ("REST API is not configured") rather than signing with a guessable
  value.
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
    (`_jwtProvisionSecret()`) if it's missing, instead of just failing.
    Published via a same-directory 0600 temp file plus `link()` rather than
    a direct `fopen($path, 'xb')`, so the mode is never left to umask and no
    reader can ever observe a partial write at the final path. An existing
    secret is never overwritten (`link()` fails atomically if `$path`
    already exists), and a directory the web server user can't write to
    still fails closed, now with an actionable log line naming the exact
    path.
- ✅ (2026-11-05) An endpoint that names its module's permission key
  (`$permissionKey`) refuses a user without that module's right with 403,
  as the module's pages do: `accounts/` needs Kontoplan, `creditor/orders/`
  and `creditor/orderlines/` Kreditorordre, and `debitor/orders/`,
  `debitor/orderlines/`, `debitor/invoices/` and
  `debitor/external-paid-invoices/` Debitorordre.
  `BaseEndpoint::checkModuleRight()` applies the web's two gates. The
  module's position in `brugere.rettigheder` must be open (any character
  from `1` up; `2` is read); a closed position, or no rights string at
  all, is refused every method. The level is then the user's effective one,
  `perm_user_levels()`: the assigned role's when the user has a role, the
  rights string's otherwise (a role with Kassesystem and no Debitorordre
  opens position 5 for the till, so the string alone would read as
  Debitorordre read). A GET needs read; POST, PUT, PATCH and DELETE need
  write. A lower level is refused once the company's enforcement mode
  (`settings` permissions/enforce) is `deny`, as on the web; in `log` mode
  the request goes through and is logged as one `deny` would refuse.
  `checkAuthorization()` selects the account database and sets global `$db`
  to it, as `includes/online.php` does on the web, so on MySQL/MariaDB
  `tbl_exists()` finds the role tables and the settings table in that schema
  (`tests/restapi/ModuleRightsMysqlTest.php`).
- REST on MySQL/MariaDB: `auth/login.php`, `auth/refresh.php` and
  `JWTAuth::getTenantDatabase()` query the master database, and
  `vat-codes/`, `user/tenants.php`, `notifications/register.php` and
  `AttachmentModel` reconnect, all through `db_connect()`, which selects no
  database on those engines (`db_select_database()` does). So no token is
  issued there yet; the order endpoints are Postgres-only as well (CURRVAL,
  `pg_escape_string()`).
- ✅ (2026-11-05) An endpoint serves only its own module's orders. The
  order endpoints create the type they serve (`debitor/orders/` and
  `debitor/invoices/` DO, `creditor/orders/` KO) whatever `type` or `art`
  the body sends. `debitor/orderlines/` reads and adds lines only on DO, DK
  and PO orders and `creditor/orderlines/` only on KO and KK
  (`OrderLineService::orderHasArt()`); another order, or a line on one, is
  not found. Order and line ids are cast to int in the models. Creating an
  order or a line casts every number and escapes every text it puts in SQL
  (`OrderModel::save()`, `OrderService`, `OrderLineService`), and the
  endpoints answer 400 to a number field that is not a number
  (`OrderService::DECIMAL_FIELDS`/`INTEGER_FIELDS`,
  `OrderLineService::DECIMAL_FIELDS`/`INTEGER_FIELDS`): a quote in `status`
  or `positionNumber` replaced the INSERT's later values, the order type
  included, or added a second row on another order.
- The master-data and reference endpoints name no module yet: customers,
  creditors, products and product groups, inventory, warehouses, labels,
  VAT, currencies, accounting years, dashboard stats and attachments answer
  every valid token. The web shows that data to several modules through
  lookups, so which rights open them is still to be decided.
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
