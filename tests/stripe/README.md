<!-- // 20261022 CDX/LH Document security regression coverage and signup default-off behavior. -->
<!-- // 20261022 CDX/LH Map M1–M6 regression coverage and require a fresh gate after the 150-test baseline. -->

# Stripe PostgreSQL suite

Run against a disposable restore of a full Saldi tenant, with the gate's PHP 8.3
and PHPUnit 10/11:

```sh
php /phpunit.phar --testsuite stripe
```

Set `SALDI_TEST_PGHOST`, `SALDI_TEST_PGUSER`, `SALDI_TEST_PGPASSWORD` and
`SALDI_TEST_DB`. PostgreSQL can also use its normal `PGPORT` environment setting.
The gate supplies database `s821_unittest`, restored from the scrubbed demo
tenant. The suite expects the existing Saldi tables and fiscal years (RA
kodenr 5 is 2026). It does not create a master `regnskab` database or run
`online.php` or `connect.php`.

Each test requires `bootstrap.php`. It installs guarded replacements for the
Stripe HTTP transport, customer mail, operational alerts, logging and the
tenant resolver before loading production Stripe helpers. HTTP requests consume
queued responses and record method, path, parameters, options and idempotency
key. An unexpected request throws; none can reach Stripe. Mail is captured in
memory, including the actual production link-mail template. Credentials seeded
in settings are generated for the test process and grant access to no account.
The fake also rejects a reused idempotency key with a different method/path/body;
`StripeTransportTest` verifies that contract without needing a database.

The connection is established lazily in `setUp()`. Missing connection
configuration skips tests for ordinary local test discovery. With configuration
present, missing extensions, bad connections, schema errors and SQL failures
fail visibly. The external gate should run with all database tests executed.

The bootstrap provisions only Stripe's schema once, before test transactions:
the pilot customer table, `stripe_fravalg`, and the real
`stripe_payments_ensure()` DDL. Each test then starts a transaction, seeds settings
and fixture rows, and rolls back in `tearDown()`. Do not use this base class for
code that commits the outer transaction; such tests need separate cleanup or
connections. Fixture ids are explicitly allocated above existing ids so old
tenant sequences need no reset. The suite runs sequentially against its own DB.

Fixtures provide debtors, uninvoiced orders, order lines and issued invoices
with status 4, invoice date/number, terms, optional `nextfakt`, and an openpost
row. State assertions pass a fixed date to `stripe_invoice_load()`; the smoke
invoice uses January dates so the known DST finding remains the S3 regression.

`signup_enabled` defaults off independently of invoice/enrolment switches. S4b enforces
it at subscription signup and classic-mail link generation, while existing subscriber
webhooks remain available. `StripeSecurityTest` covers wrong-purpose and legacy signature
rejection, Origin, early saved-card refusal, signup refusal using the endpoint guard,
private/opaque PDF paths, permissions, atomic replacement with an existing reader,
workspace isolation/cleanup, lock contention, deny-all fallback, individual index repair,
and amount/percentage/zero-percent discount totals. The delivery/charge fixtures now use
valid PDF headers and opaque cache destinations. The external gate still needs to run
PHP syntax checks and this suite; source/helper assertions do not substitute for a vhost
HTTP deny test or an end-to-end CLI form-engine render with the installed PDF tools.

S1 adds `StripeBookingTest`, which executes real booking transactions against
connection-local temporary copies of the tenant tables. Each temporary table
has an independent temporary sequence; teardown drops the copies even after a
production COMMIT. This tests the actual transaction boundaries without
persisting fixture rows or resetting tenant sequences. An intentionally broad
fiscal fixture keeps capture tests independent of the machine's current date;
year-boundary tests use explicit capture timestamps and fiscal bounds.

The booking tests cover both sync/webhook orders, a caller paused after reading
pending while another caller wins, receipt/enrolment after COMMIT, guarded late
failure/cancellation, journal INSERT/reference failure and rollback, repair and
repair dry run, receipt retry, missing clearing configuration, identity/amount/
currency validation (including webhook backfill), unrelated subscription PIs,
refund/dispute flags, subscription-refund fallback, and fiscal voucher allocation
across posted/owned journals and year boundaries. Trigger fixtures return no row
to exercise unsuccessful writes without concealing PHP or PostgreSQL warnings.
The repair tests call the same `stripe_payment_repair()` used by the CLI's
`--repair-only` branch; the CLI's master locator/bootstrap is not executed by the
tenant-only suite.

<!-- // 20261022 CDX/LH Map release scenarios to regression tests and distinguish runtime/browser evidence. -->

## S6 coverage and execution boundary

There are 150 plain PHPUnit test methods in this suite after S6 (19 added to
S5's 131). That is a source count, **not an S6 passing result**. The raw S5
gate log reports 131 tests / 1084 assertions / one warning; its lint log is empty.
The warning position maps to the switch-off smoke test, whose synthetic pending
row omitted the required status read by the state helper. S6 fixes that fixture
without changing error reporting; the next gate must confirm the warning is gone.
The external gate must lint all
changed PHP and run the suite against its fresh restored PostgreSQL tenant:

```sh
php /phpunit.phar --testsuite stripe --display-warnings --display-phpunit-deprecations
```

Also run the actual invoice-page JavaScript with deterministic DOM/Stripe.js
doubles (Node's built-in test module; no npm dependency):

```sh
node tests/stripe/payment-page.test.cjs
```

The seven script tests cover N5 readiness/first click/load failure, duplicate
submit exclusion, validation/network feedback, selected/unchecked/recovered
R12 consent parity, and original-intent authentication through both Betal nu
and the resume button. They do not contact Stripe or instantiate real Elements.
`StripePageTest` separately executes the production PHP panel/config code with
DB fixtures, checking checkbox/default parity and absence of payment controls
on manual/credit/bank-paid/pending states. Its small presentation harness avoids
the master DB/HTTP bootstrap and real invoice renderer.

`StripePilotTest` executes the preserved subscription branches from webhook.php,
using real checked tenant SQL, actual identity/upsert helpers and captured alerts.
Only checked DB wrapper names and the terminating HTTP finish boundary are
substituted. Subscription PDF fixtures deliberately omit invoice_pdf, preventing
external I/O. These tests verify record-only behavior, signup-off compatibility,
invoice-before-checkout identity, cache-based renewals, failure/refund/deletion,
and unchanged Saldi recurrence. Signature validation, HTTP event claims and
actual subscription PDF downloads still require the sandbox/operator run.

### Review section 2 item 14 and the full phase B gate

| Finding / release case | Executable coverage in tests/stripe |
| --- | --- |
| R01 / T1–T2, repeated success and sync/webhook interleaving | `StripeBookingTest`: sync first, webhook first, pending read overtaken by success, guarded late failure; one line/receipt/enrolment. New one-off capture test also checks no saved PM/date. |
| R02, R14 / T3, second capture and identity | `StripeBookingTest`: duplicate_paid plus both IDs, failed alert retry/repair, adjustment replay, tenant/order/currency/amount mismatch and backfill. |
| R03, R32 / T7, mixed PDF/email/email | `StripeDeliveryTest`: execute enqueue assignment and actual delivery helper, own PDF bytes/URL/recipient/subject; classic overrides and original mail slot forwarded. |
| R04 / T8–T9, fully open only | `StripeCollectionTest`: ±1 øre, partial/stale snapshot, absent/split openpost, cash/card terms. `StripePageTest`: manual/credit/bank-paid/pending panels contain no collection controls. |
| R06, R26, R28, R30 / T10, central eligibility | `StripeCollectionTest`: active/trialing/past_due, PBS debtor/order, EUR, copied tenant, stale eligibility. `StripeChargeTest`: actual runner refuses subscription/PBS/EUR/opt-out/partial/credit/cash candidates. |
| R08, R25 invoice branch / T13, DB failure and repair | `StripeBookingTest`: trigger-induced journal/paid/reference rollback, received retry state, lost line adoption, mail retry, missing clearing, dry-run and switches-off repair. New full-job test checks post-commit enrolment failure/repair counts/exit code without collecting. |
| R09, R39, persisted identity | `StripeChargeTest`: pre-POST reservation, lost-response lookup, old ambiguity, changed amount, technical retry, fresh canceled identity. `StripeCollectionTest`: every unresolved PI, DB-age clock, confirmed retirement before replacement. |
| R05 / T24, retained recurrence | `StripeChargeTest::testEnrolmentUsesPiCustomerAndChangesOnlyCurrentTemplate`, `testChargeSelectionExcludesInvoicesBeforeEnrolment`; nextfakt unchanged. |
| R12 and recording D, selected replacement | `StripeChargeTest`: selected and unchecked usage; new real-decline-then-replacement case updates default PM. `StripePageTest`: failed one-off/recurring/replacement/enrolment-off controls/defaults. JS tests await usage updates. |
| R13 / T20, card-only off-session | `StripeChargeTest`: off_session+confirm+card, successful capture and second-run skip; network/429/503 retry technically, while a 400 without an intent is rejected and the next create uses a new identity/key. None counts as a bank decline. |
| R40, authentication_required / corrected T21 | `StripeChargeTest`: auto and manual authentication, original PI resume/confirm, no backup/decline/retries, late alert and action-mail retry; new completion/replay test gives one journal/receipt. Abandoned 3DS stays unpaid; after cancellation automatic retry stays stopped and page retry is possible. JS executes handleNextAction. Old T21's failed/decline expectation is superseded by requires_action. |
| R24 / T22, locking, continuation and exit status | `StripeChargeTest`: exclusive/reacquired process lock, one-invoice error does not stop next, two-day alert; new full-job dry-run, technical nonzero exit, failed/successful repair-only outbox and page-versus-job single collection. CLI source wiring asserts the tested result controls exit. |
| R23 / T23, primary versus backup schedule | `StripeChargeTest::testDeclineBackupAndThreePrimaryAttemptsUseDayZeroPlusThreePlusSeven`: 1/backup1/2/3, dates, no fourth, no early alert, explicit backup wording and separate final paragraph. |
| R18, notice and consent | `StripeDeliveryTest`: pre-charge amount/card/date/link, no API lookup or attachment, failed notice fallback; new invoice-date/due-date DST notice+consent test and refusal/PDF fallback for partial/pre-enrolment invoices. |
| R20 / T25, Afmeld outstanding invoices | `StripeChargeTest`: issued null-nextfakt invoice reset/delivery, no subsequent charge, durable failed mail, partial-balance PDF; new repair-only outbox test works with rollout off. |
| R35, actual PI customer | `StripeChargeTest`: PI customer, default failure, stable customer-create key. `StripeBookingTest`: stale debtor customer through captured-payment completion now asserts the actual customer's default is updated. |
| R34, purpose/Origin/due date | `StripeSecurityTest`: every purpose substitution, v1 refusal, Origin matching, before/on/after due, direct forged manual create. |
| R10, R42 / T14, private PDF cache | `StripeSecurityTest`: opaque/private paths, 0750/0640, deny fallback/legacy sealing, symlink rejection, stable locks, isolated workspace cleanup, atomic publication with old open reader. Actual HTTP 403/404 remains external. |
| R11 / T12, old signup links | `StripeSecurityTest`: off/missing signup switch executes endpoint refusal and removes classic CTA; active subscribers preserved. |
| R15 / T16, DST and stored due date | `StripeChargeTest`: October 22+8=30, October 20+7=27, spring, stored due-date override, Honolulu process timezone, actual runner selection. New notice case covers October 20+8=28. |
| R16, R17, R22 | `StripeBookingTest`: reminder protection while unposted, failed/canceled release, fiscal voucher allocation/owned or posted journal exclusion, refund/dispute manual flags/late events. |
| R19 / T15, recipient lists/fallback | `StripeDeliveryTest`: To/CC/BCC parsing/application, missing URL/secret, cache/SMTP exception fallback, later success retains batch error. |
| R07 / T17, master controls and switches | `StripeSettingsTest`, `StripeSmokeTest`: CSRF, free text, saved values, defaults, independent switches, one-off consent forced off, classic fallback, retained reconciliation. |
| R21 / T11, unrelated/subscription PI | `StripeBookingTest`: renewal PI without legacy invoice field ignored without alert. |
| Pilot / T18 | `StripePilotTest`: existing checkout/paid/failed/refund/deleted dispatch, record-only, order/recurrence unchanged and signup off; invoice PI tests are independent. |
| R27, project-tagged settlement | New `StripeBookingTest::testJournalRetainsInvoiceProjectAndDepartmentForOpenItemSettlement` verifies project/department/order/invoice stored on the actual inserted journal line. Actual posting/settlement still needs the normal accounting acceptance. |
| R29, N1, N4 | `StripeStaffTest`: missing/wrong/malformed CSRF, Stripe select/display/save precedence, saved-card debtor/PM/customer identity and escaped render. |
| R33, R41 | `StripeSecurityTest`: each index independently repaired with table present/MySQL guard; amount/percent/procent/rounding line totals. |
| R36, N2, N3 | `StripeDeliveryTest`: Danish codes, greetings/company-name contact, custom no-name message, absence of the unavailable billing-menu hint/contact consent; `StripeChargeTest` asserts actual backup/last mail and authentication text. |
| N5, N6 | `StripePageTest` and eight JS tests address readiness and card methods without hiding errors; CLI source fixes cwd before bootstrap and sets matching REQUEST_URI. The runbook supplies exact cwd/user/logs; real CLI/SMTP warning visibility remains external. |
| R31 / T19, execution evidence | External PHP lint+PostgreSQL gate required; eight JS checks have been executed locally. No claim of full line/branch coverage or completed sandbox acceptance. |

Review fixes M1–M6 add 13 PHPUnit test methods: `StripeCollectionTest` covers
caller/consent matching, prepared abandonment, ambiguous lookup/cancel/processing,
terminal 4xx identities, page/manual card methods, partial credits and unmatched
payments under a stale snapshot. `StripeChargeTest` covers a technical auto create
followed by Afmeld and page payment, including a recovered capture without another
create. `StripeBookingTest` checks aged/fresh reminder
selection and saved-card authentication for both initial and escalating reminders.
`StripeDeliveryTest` checks the receipt and `StripePageTest` renders paid/credited
Saldi panels without the absent menu hint. `StripeTransportTest` checks changed-body
key rejection. The eighth Node check asserts actual Elements options. M6's runbook
preflights and operator SQL require deployment verification, not a synthetic test.
Attach the previous `gate-final-tests.log` (150 tests, 0 skipped) and the new gate
logs to the PR; the old gate is baseline evidence only.

### Required manual evidence after the gate

1. Real HTTP bootstraps: valid/invalid/foreign-purpose signatures, injected db,
   Origin, rate limits and mode mismatch. Record received/retry event state and
   force a DB failure/replay; the isolated dispatcher tests do not execute leases.
2. Real Payment Element: first ready click, insufficient-funds retry, complete
   and abandoned 3DS, replacement checkbox checked/unchecked, and saved-card
   authentication using the original PI. Race page return and signed webhook
   in separate processes; sequential/interleaving doubles are not process tests.
3. Full form-engine mixed batch with mail_fakt on and off: print A, mail B/C,
   inspect each PDF/attachment/subject/CTA and CC/BCC in captured SMTP. Force
   missing link/SMTP config and verify visible failure plus classic fallback.
4. Normal due-date run twice and overlapping, plus --repair-only, under the
   actual web user with warnings visible. Assert counters/exit/logs and one
   capture/journal/receipt; include real Stripe 400/429/network failure recovery
   in the sandbox where feasible. Do not simulate future dates against live cards.
5. Actual PostScript/HTML/multipage/background cold/warm PDFs, concurrent cold
   renders for same/different orders, forced renderer failure, direct artifact
   HTTP denial and signed download identity.
6. Pilot signature/lease dispatch and real PDF attachment for existing checkout,
   paid, failed, deletion and refund events; no invoice collection or recurrence
   edits. Check the customer Saldi menu named by the optional receipt hint.
7. Post a project-tagged Stripe journal and inspect its debtor/invoice open-item
   settlement; then run all-customer, single-customer and prepared reminders.
   Afmeld a real outstanding invoice with valid CSRF and inspect its delivered mail.

Production activation and rollback are in
[GO-LIVE-821.md](../../doc/stripe/GO-LIVE-821.md).
