# REST API tests

Replaces the old `restapi/tests/` scripts (18 files, zero assertions, no
PHPUnit, hardcoded live hosts `ssl12/ssl3.saldi.dk` with committed
credentials) with a real PHPUnit suite. **No production hosts, no committed
production credentials** — everything runs against the repo's own
docker-compose stack.

## Suites

- `JwtTest` — pure unit tests of `restapi/core/JWT.php` (round-trip, tamper,
  wrong secret, expiry, malformed). Runs everywhere. Always sets an explicit
  secret, so it holds before and after the SD-587 deterministic-secret fix.
- `AuthLoginEndpointTest` — `POST /auth/login.php`: success shape (tokens,
  tenant), wrong password/unknown user → 401, unknown account → 404,
  **closed account → 403**, a user whose IP limit does not list the client's
  address → 403 without a token, missing fields → 400.
- `OrdersEndpointTest` — `debitor/orders/`: 401 without/with garbage token,
  authorized list, create → read-back (+ debtor auto-provisioned in the
  tenant db), 404 for unknown id; `debitor/invoices/` list shape.
- `AdminOpretAuthorizationTest` — `admin/opret.php`: an anonymous form POST
  must be refused and leave no `regnskab` row and no tenant database.
- `AuthRefreshEndpointTest` — `POST /auth/refresh.php`: refresh token →
  working access token, access/garbage token → 401, missing field → 400,
  GET → 405, closed account → 403, a user whose IP limit does not list the
  client's address → 403, unknown account/user → 401, no account claim → 400
  unless the legacy `X-Tenant-ID` header supplies it.
- `BearerAuthEnforcementTest` — the shared `BaseEndpoint` gate: no header,
  Basic scheme, refresh-typed, foreign-secret and expired tokens → 401,
  unknown account claim → 400, `X-Tenant-ID` fallback, malformed JSON → 400.
- `CustomersEndpointTest` — `debitor/customers/` + `creditor/creditors/`:
  create (English field names) → read-back, contact e-mails, missing
  phone/duplicate e-mail → 400, search, update, delete, 404s, and that a
  creditor (art K) is invisible to the debtor endpoint.
- `ProductsEndpointTest` — `products/`: create → read-back, duplicate SKU
  and missing description → 400, `field=varenr` search, whitelist on
  `field`, update, delete, 404s, `limit` up to 200, `offset`/`page` paging
  that returns every product exactly once (also of a `field=group` filtered
  list, including `value=0`), English `orderBy`/`field` names.
- `AccountsEndpointTest` — `accounts/` (kontoplan): list, create → read-back
  stamped with the latest fiscal year, missing description → 400, a number
  the fiscal year already has → 400 on POST and on a renumbering PUT, a
  number that is not a whole number from 1 → 400, update, a PUT without `vat`
  keeps the VAT code and one with `vat` stores it, 404, DELETE → 405.
- `OrderLinesEndpointTest` — `debitor/orderlines/`: parameter validation,
  free-text line on a fresh order → list/read-back with position numbers,
  a product line stores the order's sum, VAT and cost price, which GET
  `debitor/orders/?id` and SQL read back, posted order refuses lines,
  PUT/DELETE → 405.
- `ReferenceDataEndpointsTest` — `currencies/`, `vat/`, `vat-codes/`,
  `accountingYear/`, `debitor/groups/`, `products/groups/`,
  `dashboard/stats.php`: all require a token, answer with the JSON envelope
  and documented shape, read-only ones refuse POST with 405; `products/groups/`
  lists the groups of the fiscal year covering today, finds one by `codeNo`,
  and 404s on a `grupper` row that is not a product group; `vat-codes/`
  lists that year's VAT codes (`grupper` art SM/KM/EM/YM, rate from box2);
  `inventory/warehouses/` lists warehouses across fiscal years, once per number, with
  their names, numbers and per-warehouse stock of a product.
- `InventoryStatusEndpointTest` — `inventory/status/`: the quantity a POST,
  PUT or PATCH sets is booked as bought/sold batches, so Lagerstatus, the
  item total and a variant's total over all warehouses agree with it; a
  partly used batch keeps the right remainder; concurrent requests for one
  row leave its batches equal to its quantity; a row the API set before
  without batches is booked when written again; a row's warehouse, product
  and variant cannot be changed; stock taken out of warehouse 1 comes out
  of batches saved without a warehouse or variant; a second row for one
  warehouse and variant is refused without booking; a quantity with a
  fourth decimal is booked as the row stores it.
- `ModuleRightsTest` — module rights on the endpoints that name one: a
  user with Debitorordre alone (`0000010000000000`) gets 403 and no data
  from `accounts/` and from `creditor/orders/` and `creditor/orderlines/`,
  a refused POST stores nothing, and the same user still reads
  `debitor/orders/` and `debitor/invoices/`; `1`, `2` (read) or `9` at the
  module's position opens it; a user without a rights string (NULL, `''`,
  `'0'`) is refused every module. Read-only (`2`) is refused POST and PUT
  once enforcement is `deny` and may write while it is `log`. `type` or
  `art` in the body cannot change the order type `debitor/orders/`,
  `debitor/invoices/` and `creditor/orders/` create, and each order lines
  endpoint reads and adds lines only on its own side's orders, also with
  SQL in the id. An assigned role decides the level: a role with
  Kassesystem and no Debitorordre (`0000020000000000`) is refused
  Debitorordre once enforcement is `deny`. SQL in an order's `status` or a
  line's `positionNumber` is a 400 that stores nothing, and quotes in an
  order's text are stored as written.
- `ModuleRightsMysqlTest` — the same role and enforcement checks on
  MySQL/MariaDB (`$db_type` `mysqli` and `mysql`), without HTTP:
  `BaseEndpoint::checkAuthorization()` with a signed token, then
  `checkModuleRight()`. CI has no MySQL, so
  `support/mysql_account_database_stand_in.php` answers as a MySQL account
  database (no database selected until `db_select_database()`,
  `information_schema` per schema); `tbl_exists()` and the permission
  functions are the real ones. Read-only Kontoplan is refused POST and PUT
  under `deny` and may POST under `log`, the till role is refused
  Debitorordre under `deny`, and the checks run on the account database the
  token names.

Tests that need a token the API would never issue (expired, wrong type,
foreign account id) sign it with the install's own secret via
`RestApiEnv::signToken()` and skip when `restapi/.ht_jwt_secret.bin` is not
readable from the test process.

There is no voucher REST endpoint in the repo (the old VoucherEndpointTest
targeted a `/vouchers` path that doesn't exist); voucher/kassekladde posting
is covered at engine level by `tests/characterization/` (SD-601).

## Seeded API user

The HTTP suites log in as `apitest`, a user `RestApiEnv::bootstrapTenant()`
seeds into the throwaway tenant with every module right (rights string
`11111111111111111111`) and a password generated per process by
`tests/TestCredentials.php` (see [tests/README.md](../README.md)). Nothing to
set up; to log in by hand while `SALDI_TEST_KEEP_DATABASES=1` keeps the tenant,
pass `SALDI_TEST_PASSWORD_RESTAPI=...` on the same run.

## Running

```bash
export COMPOSE_FILE=docker-compose.yml:tools/ci/compose.ci.yml
export SALDI_CHAR_PGPASS=...  # password for the local test PostgreSQL role
docker compose up -d          # installed test master (see tests/README.md)
docker compose exec -T -e SALDI_CHAR_PGPASS -w /var/www/html/saldi web php vendor/bin/phpunit --testsuite restapi
```

Against a plain local Apache + Postgres install instead of docker, point the env at it (credentials come from your gitignored `includes/connect.php`; never put them in a tracked file):

```bash
SALDI_REST_BASE_URL=http://localhost/... \
SALDI_CHAR_PGHOST=localhost \
SALDI_CHAR_PGUSER=... \
SALDI_CHAR_PGPASS=... \
SALDI_CHAR_MASTER_DB=... \
php vendor/bin/phpunit --testsuite restapi
```

(Remember to replace  ...'s before running.)

On a machine without the stack, the HTTP/DB tests skip with a reason and the
JWT unit tests still run — `composer test` stays green on a bare checkout.

`support/RestApiEnv.php` installs a fresh UUID-owned tenant per test class using
[the common helper](../characterization/support/database/TestDatabase.php).
It registers generated open/closed account names and seeds the API user with a
per-process random password. Teardown drops only that owned database and the
exact registry/session rows it inserted. `SALDI_TEST_KEEP_DATABASES=1` retains
all groups; each printed UUID/database line identifies its data. Later runs
never reuse retained names. See [database ownership](../README.md#database-ownership).

Config: `SALDI_REST_BASE_URL` (default `http://localhost/saldi`, or
`http://localhost:5000/saldi` on the host), and
`SALDI_CHAR_PGHOST/PGUSER/PGPASS/MASTER_DB`. Point these at the installed test
master used by the HTTP server. There is no tenant/template dependency.

<!-- 20260723 CL/LH SD-602: created. -->
<!-- 20260904 CL/NTR Added the refresh/bearer/customers/products/accounts/orderlines/reference-data suites, the non-docker run recipe, the per-process random password from tests/TestCredentials.php, and the per-class tenant teardown. -->
<!-- 20260928 CL/NTR Tenant db and account names carry a per-process suffix; teardown removes only the db and regnskab ids that bootstrap recorded. -->
<!-- 20261008 CL/LH Products paging/field-name/filtered-paging, product-group list and warehouse list cases. -->
<!-- 20261105 CL/LH InventoryStatusEndpointTest. -->
<!-- 20261105 CL/LH AccountsEndpointTest: a duplicate or fractional account number is refused. -->
<!-- 20261105 CL/LH AccountsEndpointTest: a PUT without vat keeps the account's VAT code. -->
<!-- 20261105 CL/LH ReferenceDataEndpointsTest: vat-codes/ lists the current fiscal year's VAT codes. -->
<!-- 20261105 CL/LH ModuleRightsTest (read-only writes under deny, order types per endpoint); the seeded API user holds every module right. Review: roles, SQL in body fields. -->
<!-- 20261105 CL/LH AuthLoginEndpointTest, AuthRefreshEndpointTest: the user's IP limit. -->

<!-- 20261111 CDX/NTR Document fresh per-class tenants and common retention. -->
