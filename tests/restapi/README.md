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
  **closed account → 403**, missing fields → 400.
- `OrdersEndpointTest` — `debitor/orders/`: 401 without/with garbage token,
  authorized list, create → read-back (+ debtor auto-provisioned in the
  tenant db), 404 for unknown id; `debitor/invoices/` list shape.
- `AdminOpretAuthorizationTest` — `admin/opret.php`: an anonymous form POST
  must be refused and leave no `regnskab` row and no tenant database.
- `AuthRefreshEndpointTest` — `POST /auth/refresh.php`: refresh token →
  working access token, access/garbage token → 401, missing field → 400,
  GET → 405, closed account → 403, unknown account/user → 401, no account
  claim → 400 unless the legacy `X-Tenant-ID` header supplies it.
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
  posted order refuses lines, PUT/DELETE → 405.
- `ReferenceDataEndpointsTest` — `currencies/`, `vat/`, `vat-codes/`,
  `accountingYear/`, `debitor/groups/`, `products/groups/`,
  `dashboard/stats.php`: all require a token, answer with the JSON envelope
  and documented shape, read-only ones refuse POST with 405; `products/groups/`
  lists the groups of the fiscal year covering today, finds one by `codeNo`,
  and 404s on a `grupper` row that is not a product group;
  `inventory/warehouses/` lists the current fiscal year's warehouses with
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

Tests that need a token the API would never issue (expired, wrong type,
foreign account id) sign it with the install's own secret via
`RestApiEnv::signToken()` and skip when `restapi/.ht_jwt_secret.bin` is not
readable from the test process.

There is no voucher REST endpoint in the repo (the old VoucherEndpointTest
targeted a `/vouchers` path that doesn't exist); voucher/kassekladde posting
is covered at engine level by `tests/characterization/` (SD-601).

## Seeded API user

The HTTP suites log in as `apitest`, a user `RestApiEnv::bootstrapTenant()`
seeds into the throwaway tenant with a password generated per process by
`tests/TestCredentials.php` (see [tests/README.md](../README.md)). Nothing to
set up; to log in by hand while `SALDI_REST_KEEP_TENANT=1` keeps the tenant,
pass `SALDI_TEST_PASSWORD_RESTAPI=...` on the same run.

## Running

```bash
docker compose up -d          # installed stack with a tenant (see tests/characterization/README.md)
docker compose exec -T -w /var/www/html/saldi web php vendor/bin/phpunit --testsuite restapi
```

Against a plain local Apache + Postgres install instead of docker, point the env at it (credentials come from your gitignored `includes/connect.php`; never put them in a tracked file):

```bash
SALDI_REST_BASE_URL=http://localhost/... \
SALDI_CHAR_PGHOST=localhost \
SALDI_CHAR_PGUSER=... \
SALDI_CHAR_PGPASS=... \
SALDI_CHAR_MASTER_DB=... \
SALDI_CHAR_TEMPLATE_DB=... \
php vendor/bin/phpunit --testsuite restapi
```

The template tenant must have a fiscal year (`grupper` art `RA`) covering today; a small one clones in about a second.

(Remember to replace  ...'s before running.)

On a machine without the stack, the HTTP/DB tests skip with a reason and the
JWT unit tests still run — `composer test` stays green on a bare checkout.

`support/RestApiEnv.php` provisions a throwaway tenant per test class
(`CREATE DATABASE saldi_apitest_<suffix> TEMPLATE saldi_2`), registers an
open (`apitest_<suffix>`) and a closed (`apitestclosed_<suffix>`) master
`regnskab` row for it, and seeds the API user with a per-process random
password (`tests/TestCredentials.php`). `<suffix>` is eight random hex chars
drawn once per PHP process, so two runs against the same postgres never
share a tenant or an account name. The env remembers the database and the
`regnskab` ids it created, and `tearDownAfterClass()` drops exactly those
again, so the seeded login never outlives a class and nothing is ever
matched by name (a stale tenant from a crashed run, a parallel run's tenant
or a real account called `apitest` is never touched). Set
`SALDI_REST_KEEP_TENANT=1` to keep the last class's tenant for inspecting a
failure; find it with `SELECT regnskab, db FROM regnskab WHERE regnskab
LIKE 'apitest%'` and drop it by hand when done, since later runs will not.
Config via env: `SALDI_REST_BASE_URL` (default `http://localhost/saldi`, use
`http://localhost:5000/saldi` from the host), `SALDI_CHAR_PGHOST/PGUSER/
PGPASS/MASTER_DB/TEMPLATE_DB`, `SALDI_REST_TEST_DB` (prefix of the tenant
db name, default `saldi_apitest`; prefix plus suffix must fit
`regnskab.db`'s 25 chars). The suite inserts and deletes `regnskab` rows in
whatever master `SALDI_CHAR_MASTER_DB` names and creates databases on that
server, so point it at a disposable or test master, never a shared
production registry.

<!-- 20260723 CL/LH SD-602: created. -->
<!-- 20260904 CL/NTR Added the refresh/bearer/customers/products/accounts/orderlines/reference-data suites, the non-docker run recipe, the per-process random password from tests/TestCredentials.php, and the per-class tenant teardown. -->
<!-- 20260928 CL/NTR Tenant db and account names carry a per-process suffix; teardown removes only the db and regnskab ids that bootstrap recorded. -->
<!-- 20261008 CL/LH Products paging/field-name/filtered-paging, product-group list and warehouse list cases. -->
<!-- 20261105 CL/LH InventoryStatusEndpointTest. -->
<!-- 20261105 CL/LH AccountsEndpointTest: a duplicate or fractional account number is refused. -->
<!-- 20261105 CL/LH AccountsEndpointTest: a PUT without vat keeps the account's VAT code. -->
