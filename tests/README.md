# Tests

- `characterization/` - PHPUnit characterization suites for legacy engine
  code (`*.test.php`, see the READMEs inside).
- `restapi/` - PHPUnit suite for the REST API (`*Test.php`, see
  [restapi/README.md](restapi/README.md)).
- `test_*.php` - older stand-alone scripts run directly with `php`.

Run everything with `composer test` (PHPUnit discovery via `phpunit.xml`).

## Passwords for seeded accounts: `tests/TestCredentials.php`

Suites that create the account they log in with take its password from
`TestCredentials::password('<key>')`. That file is tracked and contains no
secret: each password is generated at random once per PHP process and only
exists in the throwaway data the suite seeds and drops again. There is
nothing to set up on a checkout, and a run that dies half-way leaves behind a
password nobody knows.

To log in by hand while a suite keeps its data (`SALDI_REST_KEEP_TENANT=1`
for `restapi/`), set the override in the environment for that run:

```bash
SALDI_TEST_PASSWORD_RESTAPI=... \
SALDI_REST_KEEP_TENANT=1 \
php vendor/bin/phpunit --testsuite restapi
```

A suite that seeds an account must remove it again in `tearDownAfterClass()`
(see `RestApiEnv::teardownTenant()`).

Anything a test needs that grants access to something real - a third-party
API key, a live account - is read from an environment variable and the test
skips when it is unset. Never write such a value into a tracked file (see
`doc/ai/convention_no_hardcoded_secrets.md`). Database connection details
for the local stack come from env vars or the gitignored
`includes/connect.php`, as documented per suite.

<!-- 20260904 CL/NTR created. -->


## SST-876 document stock synchronization

<!-- 20261023 CDX/MJ Track the fixes requested in SST-876 comment 22605 and their regression checks. -->

The document sender prepares current quantities, prioritizes the document's lines,
and sends up to ten payloads across the configured shops per round. Parent sets
follow the lines. Old backlog gets at most five additional seconds within the
shared request budget. Budget skips remain queued without increasing attempts;
answered application refusals are reported instead of retried indefinitely.
Document warnings exclude sets and old backlog and identify deferred lines as
pending an automatic retry on the next stock update. The unrelated empty
`includes/documents_master.php` was removed.

Run these scripts separately (they share fixture directory names):

```bash
php tests/test_sst876_flush.php
php tests/test_sst876_sync_queue.php
php tests/test_sst876_item_fanout.php
php tests/test_sst876_document_http.php
php tests/test_shop_stock_sync.php
```

The HTTP test needs PHP cURL, Python 3, and permission to bind/connect to loopback.
It starts a concurrent mock returning a number after 0.25 seconds. Database calls
use a synthetic six-line fixture with disjoint parent sets; no customer endpoint
or accounting entity is contacted. The regression verifies all six lines,
including vare_id 3580, receive three successful replies in the same request,
with no warning or remaining queue markers. The controlled HTTP run on 2026-10-23
sent 423 requests in 4.03 seconds. This is not a new invoice test on ssl12.

Before deployment, repeat the saved six-line order against delayed test shops,
then receive a three-line supplier order while a backlog exists. Also check a
refusal, a transport outage followed by recovery, and a budget-exhausted request:
only affected document lines should be named, untried markers must keep their
attempt counts, and old backlog must not add thirty seconds to the receipt.
Do not point the test entity at customer shops. Background delivery and atomic
queue/database transactions are separate outstanding reliability work.
