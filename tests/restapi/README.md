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

There is no voucher REST endpoint in the repo (the old VoucherEndpointTest
targeted a `/vouchers` path that doesn't exist); voucher/kassekladde posting
is covered at engine level by `tests/characterization/` (SD-601).

## Running

```bash
docker compose up -d          # installed stack with a tenant (see tests/characterization/README.md)
docker compose exec -T -w /var/www/html/saldi web php vendor/bin/phpunit --testsuite restapi
```

On a machine without the stack, the HTTP/DB tests skip with a reason and the
JWT unit tests still run — `composer test` stays green on a bare checkout.

`support/RestApiEnv.php` provisions a throwaway tenant per run
(`CREATE DATABASE saldi_apitest TEMPLATE saldi_2`), registers an open
(`apitest`) and a closed (`apitestclosed`) master `regnskab` row for it, and
seeds a local-only API user. Config via env:
`SALDI_REST_BASE_URL` (default `http://localhost/saldi`, use
`http://localhost:5000/saldi` from the host), `SALDI_CHAR_PGHOST/PGUSER/
PGPASS/MASTER_DB/TEMPLATE_DB`, `SALDI_REST_TEST_DB`.

<!-- 20260723 CL/LH SD-602: created. -->
