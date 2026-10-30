# Tests

- `characterization/` - PHPUnit characterization suites for legacy engine
  code (`*.test.php`, see the READMEs inside).
- `restapi/` - PHPUnit suite for the REST API (`*Test.php`, see
  [restapi/README.md](restapi/README.md)).
- `test_*.php`, `integration/` - older stand-alone scripts run directly with `php`.
  CI never runs them, so add no new ones: the guardrails `test-discovery` check
  rejects a new test file outside the suites `phpunit.xml` discovers. Helpers go
  in a `support/` directory inside a suite.

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
<!-- 20261027 CL/LH New tests only where CI runs them (guardrails test-discovery). -->


## SST-876 document stock synchronization

<!-- 20261023 CDX/MJ Track the fixes requested in SST-876 comment 22605 and their regression checks. -->
<!-- 20261026 CDX/MJ Document outage recovery for sets in non-stock-managed groups. -->

The document sender refreshes quantities before each send round, prioritizes the document's lines,
and sends up to ten payloads across the configured shops per round. Parent sets
follow the lines. Old backlog gets at most five additional seconds within the
shared request budget. Budget skips remain queued without increasing attempts;
answered application refusals are reported instead of retried indefinitely.
Document warnings exclude sets and old backlog and identify deferred lines as
pending an automatic retry on the next stock update. The unrelated empty
`includes/documents_master.php` was removed.

The PHPUnit-discovered `ShopSyncRegressionScriptsTest` runs the isolated scripts in
separate child processes, including real loopback HTTP and set recovery. CI installs
PHP cURL, the cURL executable, and Python for these checks. Run the suite with:

```bash
php vendor/bin/phpunit --filter ShopSyncRegressionScriptsTest
```

For individual diagnosis, run the scripts separately (they share fixture directory names):

```bash
php tests/characterization/includes/support/shopSync/test_sst876_configuration.php
php tests/characterization/includes/support/shopSync/test_sst876_delivery_transaction.php
php tests/characterization/includes/support/shopSync/test_sst876_transaction_recovery.php
php tests/characterization/includes/support/shopSync/test_sst876_transport_http.php
php tests/characterization/includes/support/shopSync/test_sst876_flush.php
php tests/characterization/includes/support/shopSync/test_sst876_sync_queue.php
php tests/characterization/includes/support/shopSync/test_sst876_item_fanout.php
php tests/characterization/includes/support/shopSync/test_sst876_document_http.php
php tests/characterization/includes/support/shopSync/test_sst876_set_recovery.php
SALDI_SHOP_SET_RECOVERY_HTTP=1 php tests/characterization/includes/support/shopSync/test_sst876_set_recovery.php
php tests/test_shop_stock_sync.php
```

The configuration regression verifies that tenants without a webshop perform no queue
filesystem work, and that document sends and old backlog wait until the stock transaction commits.
The transaction recovery regression covers rollback stock and low-level sends with parent sets.
The transport HTTP regression rejects truncated and timed-out HTTP 200 responses in both
parallel cURL and shell cURL, and retains the failed item until a complete retry succeeds.
The queue regression also checks that claiming an old marker starts a fresh lease.

The HTTP test needs PHP cURL, Python 3, and permission to bind/connect to loopback.
It starts a concurrent mock returning a number after 0.25 seconds. Database calls
use a synthetic six-line fixture with disjoint parent sets; no customer endpoint
or accounting entity is contacted. The regression verifies all six lines,
including vare_id 3580, receive three successful replies in the same request,
with no warning or remaining queue markers. The controlled HTTP run on 2026-10-23
sent 423 requests in 4.03 seconds. This is not a new invoice test on ssl12.

The set-recovery regression uses six lines and 130 sets whose own group is not
stock-managed. Shop 2 is unavailable during the sale, leaving 136 queue entries.
After recovery it must receive all 136 items, with newly calculated set availability,
before the queue is empty. Availability preparation is deliberately delayed so
preparing all 130 sets at once would exceed the five-second queue allowance;
recovery must prepare and send small groups to make progress. Component retries
leave dependent sets on independent markers instead of calculating their entire
fan-out again. The HTTP mode uses actual cURL, a closed loopback port
for the outage, and the concurrent delayed mock for recovery. It also verifies
that a known set without a payload stays pending and that full-mode set retries
retain the original stock and cost-price payloads. Existing queue files work
without migration: set membership is read from `styklister` when draining.

Before deployment, repeat the saved six-line order against delayed test shops,
then receive a three-line supplier order while a backlog exists. Also check a
refusal, a transport outage followed by recovery, and a budget-exhausted request:
only affected document lines should be named, untried markers must keep their
attempt counts, and old backlog must not add thirty seconds to the receipt.
For the outage acceptance test, make shop 2 unreachable before the six-line sale,
restore it, then perform further stock updates. Check that all 136 items reach
shop 2 with current stock before their markers disappear, especially sets in
non-stock-managed group 12. This application-level acceptance test still needs
to be repeated by the ticket tester.

Do not point the test entity at customer shops. Background delivery and atomic
queue/database transactions are separate outstanding reliability work.

### SST-876 review follow-ups (2026-10-28)

SOAP invoicing, remote booking, MySale, recurring invoicing, and the legacy debtor
posting page now drain deferred updates after their outer commit succeeds. The
screen callers also report document failures. The caller regression executes their
post-commit code with transaction doubles, including write-error outcomes.

Fresh queue work is processed before entries at the retry threshold. Exhausted
markers are retained and rotate by attempt count; reaching the threshold is logged
once. No failed update is removed merely because its attempt count is high. Backlog
preparation stops when the shared HTTP budget cannot send another call.

Document stock and set availability are re-read before each send batch. This narrows
the concurrent-sale window; it does not serialize independent requests or provide
cross-request delivery ordering. Marker-write failures now produce an error log.

Receipts retain full price/SKU synchronization for adapter compatibility. Sales and
stock adjustments keep stock-only sends. The deployed adapter implementations are
outside this repository, so their cost-price behavior has not been independently
verified here.

Before deployment, repeat the saved six-line order and three-shop outage recovery
in the application on this revision, and exercise POS, recurring invoicing, SOAP,
remote booking, MySale, and the external paid-invoice service. A committed sale must
send during the same request; a rollback or database write failure must not send.
Confirm that an old backlog adds at most five seconds and cannot starve new items.

### SST-883 posting warehouse regression

`php /path/to/phpunit.phar --no-configuration --do-not-cache-result tests/characterization/includes/PostingWarehouseTest.test.php`
checks warehouse validation, department mapping priority, fallback, and SQL input handling.

`PostingWarehousePgTest` runs `tests/characterization/includes/support/posting_warehouse_pg.php` with
`SALDI_CHAR_PG_DSN` (CI's fixture database) or `SALDI_TEST_PG_DSN`, and the script executes the shipped `linjeopdat()` and `lagerstatus()`
functions against temporary PostgreSQL tables. It checks sales, credits, variants, existing
and absent target rows, primary/legacy department mappings, unchanged valid warehouses,
and companies without warehouses. All fixture writes are rolled back. No webshop is configured.
To reproduce against an earlier checkout, set `SALDI_POSTING_WAREHOUSE_SOURCE_ROOT` to that checkout.
