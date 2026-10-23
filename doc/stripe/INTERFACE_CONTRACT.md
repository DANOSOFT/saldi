<!-- // 20261022 CDX/LH Align the master port contract for switches, recurrence reads, payment states, repair and private PDFs. -->
<!-- // 20261022 CDX/LH Finalize notices, consent/readiness, webhook retry limits, job summaries and launch verification. -->

# Stripe integration — canonical interface contract

**Updated 2026-10-22 for the master port of #821.** Sections 1–7 describe the existing
subscription integration; section 8 records invoice collection and supersedes conflicting
legacy rules. Amend this contract alongside any behavior change.

Full grounded plan (external): `Saldi payment/stripe-native-grounded-implementation-plan-2026-08-07.md`.

---

## 1. Settings — `settings` table, `var_grp='stripe'`

| var_name | Written by | Read by | Notes |
|---|---|---|---|
| `enabled` | stripe_valg page (checkbox) | subscribe, link printer, mailer | `on` \| `off` (default off). Master switch: off = no new subscription signup or invoice collection; subscription signup also requires `signup_enabled=on`. The webhook is deliberately NOT gated — events for already-running subscriptions must never be dropped. |
| `mode` | stripe_valg page | subscribe, webhook, mailer, seed | `test` \| `live` |
| `secret_key` | stripe_valg page | stripeHttp, subscribe, webhook | one key; prefix must match mode; NO mode-suffixed key names |
| `webhook_secret` | stripe_valg page | webhook | `whsec_...` |
| `link_secret` | auto-generated on first save; explicit regen only | subscribe (verify), mailer (sign via stripeLink) | `bin2hex(random_bytes(32))` |
| `target_db` | mirrored from resolver, never from POST | webhook (cross-check) | must equal `stripeConfigDb()` output |
| `vat_rate_id` | stripe_valg page | subscribe | `txr_...`, the 25% DK tax rate; unset => subscribe parks |
| `public_base_url` | stripe_valg page | subscribe (link mint), mailer | e.g. `https://<pilot-host>`; unset => mailer token renders `''` |
| `bookkeeper_email` | stripe_valg page | subscribe + webhook alerts | fallback: `adresser.email where art='S'` |

All reads go through **`stripe_setting($name, $default)`** in
`includes/stripeIncludes/stripeSettings.php`, which loads the group in one query into a
per-request static. Nobody queries `settings` for stripe values directly.

## 2. Tenant resolver

**`stripeConfigDb()`** in `stripeSettings.php`:
`getenv('SALDI_STRIPE_DB')` -> else gitignored `includes/stripeIncludes/.ht_stripe_db`
(same `.ht*` convention as `restapi/.ht_jwt_secret.bin`) -> regex-gate `^[a-z0-9_]{1,40}$`
-> validate against the global locator (`db_select("select db from regnskab where db='...'"`,
precedent `includes/online.php:176`) -> else **fail closed**.

No committed default anywhere (`includes/connect.php` is untouched — it deploys to every
install including customer installs on the same host). `?db=`/POST `db` on any stripe
endpoint -> hard 400, never silently ignored.

## 3. DDL

Tables live in `admin/opret.php` (fresh installs) **and** `includes/betweenUpdates.php`
(existing installs, idempotent, existence-checked — runs on regnskab open via
`admin/aaben_regnskab.php:146-147`). **Never in `opdat_4.3.php`** — its
`opdat_to('4.3.0')` gate has already run everywhere (burn note `betweenUpdates.php:31-37`).
Pilot indexes are in `betweenUpdates.php`. Invoice-payment indexes are also self-healed
by the lazy initializer (§8); PostgreSQL-only DDL is skipped on MySQL/mysqli.

```sql
CREATE TABLE stripe_catalog (id SERIAL PRIMARY KEY, varenr text, stripe_price_id varchar(255),
  stripe_product_id varchar(255), unit_ore integer,
  billing_interval varchar(10) NOT NULL DEFAULT 'month', interval_count integer NOT NULL DEFAULT 1,
  currency varchar(3) NOT NULL DEFAULT 'DKK',
  active boolean NOT NULL DEFAULT true, created_at timestamp DEFAULT CURRENT_TIMESTAMP);
-- NB: the column is billing_interval, not "interval" - INTERVAL is a reserved word in
-- PostgreSQL (and MySQL) and would fail the CREATE TABLE. It still maps to Stripe's
-- recurring[interval] value.

CREATE TABLE stripe_events (id SERIAL PRIMARY KEY, event_id varchar(255) NOT NULL,
  event_type varchar(100), payload text, status varchar(30) NOT NULL DEFAULT 'received',
  saldi_order_id integer, invoice_number varchar(30), error text,
  received_at timestamp DEFAULT CURRENT_TIMESTAMP, processed_at timestamp);

CREATE TABLE stripe_customers (id SERIAL PRIMARY KEY, stripe_customer_id varchar(255) NOT NULL,
  stripe_subscription_id varchar(255), konto_id integer, kontonr varchar(30), order_id integer,
  status varchar(30) NOT NULL DEFAULT 'active',
  created_at timestamp DEFAULT CURRENT_TIMESTAMP, updated_at timestamp);

CREATE TABLE stripe_import_failures (id SERIAL PRIMARY KEY, event_id varchar(255),
  stripe_invoice_id varchar(255), reason varchar(50), http_code integer, message text,
  payload_json text, created_at timestamp DEFAULT CURRENT_TIMESTAMP, resolved_at timestamp);

CREATE UNIQUE INDEX stripe_events_event_id_uidx ON stripe_events (event_id);
CREATE UNIQUE INDEX stripe_catalog_varenr_active_uidx ON stripe_catalog (varenr) WHERE active;
CREATE INDEX idx_stripe_customers_customer_id ON stripe_customers (stripe_customer_id);
CREATE INDEX idx_stripe_customers_konto_id ON stripe_customers (konto_id);
```

`kontonr` is `varchar(30)` matching `ordrer.kontonr` (`admin/opret.php:396`) — NOT integer.
`stripe_events.payload` stores **base64 of the raw verified body** (replay/recovery
path); decode before interpreting it as JSON. The #795 schema also retains
`claim_token`, `claimed_at`, `attempts` and `event_created`. Their presence does not
mean the inherited endpoint uses them; see the retry boundary in §8.
`stripe_customers.status` lifecycle: `'active'` on `checkout.session.completed`,
`'canceled'` on `customer.subscription.deleted`. Subscription events never change
`ordrer.nextfakt`. The invoice loader and enrolment may **read/filter** `nextfakt` to
identify a recurring invoice/current series template. Stripe code never writes the
`nextfakt` date or advances recurrence; Saldi's recurring invoice engine owns it (§8).

## 4. Link signature

Payload = **`v2:<purpose>:<order_id>`**, HMAC-SHA256 with `link_secret`, compared via
`hash_equals()`. `purpose` is exactly `subscribe`, `invoice`, or `remove-card`; unknown
purposes and invalid ids fail closed. `invoice` authorizes viewing, PDF download, payment
and payment-state polling for that invoice. It never authorizes card removal or subscription
signup. `remove-card` is reserved; this port has no public removal endpoint. Staff Afmeld
continues to require its authenticated POST and session CSRF token.

`stripe_link_sign($order_id, $purpose = 'subscribe')` and
`stripe_link_verify($order_id, $sig, $purpose = 'subscribe')` live in
`includes/stripeIncludes/stripeLink.php`. Subscription callers retain the default purpose;
`stripe_invoice_url()` explicitly signs `invoice`, and `faktura_common.php` verifies it.
`stripe_link_url($order_id)` creates a subscription URL only when both signup switches
are enabled. No caller reimplements signing.

Old unscoped `v1:<order_id>` signatures are rejected. Reissue outstanding invoice/signup
links after deployment; do not add an unscoped compatibility fallback. Links have no
expiry; rotating `link_secret` revokes all issued links and changes opaque PDF cache names.
Line edits do not change link identity: endpoints load and validate current invoice data.

## 5. Checkout <-> webhook metadata

The subscribe endpoint sends `client_reference_id=<order_id>` **plus both** metadata blocks:
- `metadata[saldi_order_id|saldi_konto_id]` (on the session — debugging), and
- `subscription_data[metadata][saldi_order_id|saldi_konto_id|saldi_kontonr]` — Stripe
  copies subscription metadata onto every invoice it generates; this is what makes
  RENEWAL invoices resolvable. Non-optional.

The webhook resolves identity in this order: (1) the Basil/Dahlia invoice subscription
metadata (`invoice.parent.subscription_details.metadata`, with legacy
`invoice.subscription_details.metadata` fallback), (2) the local `stripe_customers` row,
then (3) `GET /v1/subscriptions/<id>` as the final fallback. `stripe_customers` is
upserted from **both**
`checkout.session.completed` and `invoice.paid`, so webhook ordering races cannot orphan
the first payment. Truly unresolvable customer -> `unmapped_customer` -> **5xx** (the one
documented exception to 200-always) so Stripe's retry ladder absorbs ordering races.

## 6. Deploy guard (one mechanism)

`SALDI_CALLER_OWNED_POSTING` constant defined atop `bogfor()` in `includes/ordrefunc.php`
(P0 branch, next week). The import service asserts it as its first statement. The webhook
boot-asserts, before touching any event: (a) `stripe_events` exists (`information_schema`);
(b) `ordrer_stripe_paid_invoice_uidx` exists (`pg_indexes`) *when import-enabled*;
(c) the constant is defined *when import-enabled*; (d)
`(new ReflectionFunction('get_next_order_number'))->getNumberOfParameters() >= 2`
*when import-enabled*. Any failure -> 503 + alert, never process.

Rationale: PHP silently discards extra args to user-defined functions, so a partial deploy
(e.g. `restapi/` without `includes/`) produces an import that posts non-atomically and
returns HTTP 200. Single-file "live update" pushes are an established habit in this repo —
the guard turns that habit into a loud 503 instead of silent corruption.

## 7. Mode of record for the pilot

The webhook ships in **record-only mode**: verify + dedupe + PDF attach + alert; booking is
manual. Import mode is fully built and tested in sandbox but gated behind
`stripe_setting('import_enabled', 'off')` + the boot asserts in §6, and is flipped ON only
after the P0 atomicity work (bogfor savepoint refactor) is merged, its regression test
(T-9: forced post-bogfor failure -> zero surviving rows) is green, and the refactor has had
human review.

## 8. Master port 2026-10-22 — “Saldi invoices, Stripe collects”

Saldi issues invoices, including recurring invoices; Stripe collects each eligible invoice
using PaymentIntents. Existing subscriptions continue through the original
`checkout.session.completed`, `invoice.paid`, and subscription lifecycle branches. The
invoice flow excludes customers with active/trialing/past_due subscription ids and never
changes their default card or collects their Saldi invoices.

**Switches and settings.** `invoice_payments`, `enrolment`, and `signup_enabled` are
independent `stripe` rows, all default **off** on a missing/empty setting. `enabled` remains
the shared master switch. `signup_enabled=off` refuses GET/POST to `subscribe.php`, including
outstanding links, and removes `$abonnementslink` from classic mail through
`subscriptionLinkUrl()` returning empty. Existing subscription webhook processing is not
gated by these switches. Switching signup off cannot expire a Checkout Session already
created at Stripe: expire open subscription sessions operationally before go-live.

`invoice_payments=off` stops new invoice PaymentIntents and link delivery. `enrolment=off`
stops new save-card consent and saved-card collection. Neither switch prevents processing
verified events/sync, repairing captured payments, or retrying queued Afmeld invoice mail.
`publishable_key` configures Elements. `charge_on` is `due` (default) or `invoice_date`;
`auto_post_journal` is reserved/off, and no invoice journal is posted automatically.
Master-compatible controls live in `systemdata/diverseIncludes/stripeValg.php`; no redesign
settings registry is required. `email_settings.invoice_format` is `pdf` (default) or `link`;
`email_settings.invoice_link_text` is optional tenant-wide text. Collection/link generation
also require the current `$db` to equal `stripeConfigDb()`.

**Eligibility and amounts.** The shared guard excludes subscriptions, PBS mandates/BS
orders, non-DKK invoices, cash/card payment terms, opt-outs, and the wrong tenant.
Collection requires authoritative openpost rows for invoice number + debtor whose open
balance equals the booked total within one øre. Partial/ambiguous balances go to a manual
panel without card controls. The amount charged remains booked `ordrer.sum + moms`;
display line totals apply `rabatart='amount'` as a per-unit reduction, otherwise percentage
`rabat`, then the form engine's rounding and optional `procent` multiplier (including 0).

**Storage and DDL.** `stripe_payments` has one row per intent and unique intent id; its
partial unique `(ordre_id) WHERE status='succeeded'` index prevents a second normal success
row. `stripe_payment_indexes_ensure()` checks each of the intent, succeeded and order
indexes independently in `pg_indexes` and recreates a missing index with `IF NOT EXISTS`.
It runs on tenant update and lazy invoice initialization even when the table already exists;
PostgreSQL provisioning is skipped on MySQL/mysqli. Existing MySQL tables are not dropped.
`stripe_collection_attempts` persists request parameters, identity_no, primary_no, status
and charge date before API calls. `stripe_charge_errors` tracks technical failures;
`stripe_unenrol_mail` is the durable delivery outbox. The card-on-file columns remain on
`adresser` (`stripe`, `stripe_customer_id`, `stripe_pm_id`, `stripe_date`).

**States and collection identities.** Page states are `open`, `open_recurring`, `enrolled`,
`overdue`, `failed`, `requires_action`, `pending`, `paid`, `paidbank`, `nocard`, `credited`,
and `manual`. Payment rows distinguish `pending`, `processing`, `requires_action`,
`succeeded`, `failed`, `technical_error`, `canceled`, `duplicate_paid`, `refunded`,
`disputed`, and `unmapped`. Authentication is not a bank decline. Captured adjustments
and identity mismatches require manual reconciliation, never an automatic second charge.

Every page/manual/auto/backup caller holds the same per-invoice collection lock, reconciles
unresolved intents, and rechecks current eligibility/open balance. An old unresolved intent
is never forgotten merely because it is 15 minutes old. A durable attempt freezes parameters
and idempotency key (`pi:` or `auto:` + tenant, invoice, persisted identity_no, save flag).
These prefixes do not authorize independent simultaneous collections. Confirmed terminal
cancellation gets a new identity; technical retries retain the existing reservation.
After an ambiguous create, recovery lists the customer's intents and matches tenant,
invoice and attempt metadata. Once the safe idempotency window has elapsed, absence from
that lookup blocks collection for manual reconciliation rather than re-POSTing blindly.

**Public endpoints.** `faktura.php`, `faktura_pay.php`, `faktura_state.php`, and
`faktura_pdf.php` require `?o=<ordre_id>&s=<invoice-purpose-hmac>`, reject `db`, pin the
tenant, and rate-limit IP/invoice. Payment POSTs require an Origin matching the configured
`public_base_url` scheme, hostname and port, independently of the Host header. The enrolled
“Betal nu” button is offered and accepted only on or after the due date, also checked under
the invoice lock for source `manual`. Ordinary on-session invoice payment remains possible
before due. Authentication recovery resumes the existing intent; effective
`setup_future_usage` stays consistent between server and Elements.

**Webhook/bookkeeping and repair.** Unrelated intents without Saldi order metadata are
ignored, including subscription intents without a legacy `invoice` field. Saldi-tagged
intents must match `metadata.saldi_db`, numeric `saldi_ordre_id`, currency and received
amount against the stored payment and current invoice total. `saldi_fakturanr` is
informational; it is not a separately validated capture predicate.

Enable `payment_intent.succeeded`, `payment_intent.payment_failed`,
`payment_intent.canceled`, `payment_intent.requires_action`, `charge.refunded` and
`charge.dispute.created`, retaining the existing pilot event set. Snapshot payloads
must match the integration's pinned `2026-06-24.dahlia` API version.

The #795 schema fields (`claim_token`, `claimed_at`, `attempts`, `event_created`) stay
intact. The inherited runtime instead uses an error-column token, processes a newly
inserted `received` row, and reclaims only a `received` row older than 15 minutes into
`retrying`. Other existing statuses are acknowledged as duplicate. Caught invoice
DB/delivery failures return 500 and reset a claimed `retrying` row to `received`;
original `received` rows remain received. Failed invoice work is not deliberately
finished as a terminal event. A hard process death in `retrying`, and the inherited
subscription `failed` case, still require operator reconciliation. This port does
not implement a general lease/replay rewrite. Do not treat a duplicate HTTP response
as proof that the payment's accounting is complete.

A verified capture atomically records success, `ordrer.betalt='on'`,
one journal line in “Stripe-betalinger” and its journal reference. The line carries the
invoice/project dimensions and actual fiscal year. A missing DIV/3 box10 clearing account
blocks collection. Receipts and enrolment follow accounting commit. A second captured
intent is retained as `duplicate_paid` with an alert naming both intents; refund is manual.

`tools/stripe_charge_due.php --repair-only` never creates or confirms a PaymentIntent. It repairs
captured rows missing bookkeeping or receipt/enrolment completion, retries duplicate-capture
alerts and queued Afmeld deliveries, even when collection switches are off. `--dry-run`
reports without those mutations/API creates. Failed work stays discoverable; summary errors
produce exit 1; a successful job exits 0. A whole-job lock prevents overlapping
repair/charge runs; an internal overlap prints `skipped=overlap` and exits 0. The CLI
uses `stripe_charge_job()` for all tenant work. Its JSON summary contains collection
counters (`due`, `charged`, `succeeded`, `declined`, `requires_action`, `pending`,
`skipped`, `errors`) on a full run, plus `repair_found`, `repaired`, `repair_failed`
and `mail_failed`. Repair-only omits collection counters except the combined `errors`.
Errors add repair and mail failures; a captured success and a bank decline are distinct
outcomes. Schema provisioning is still possible under `--dry-run`, so it is not a
read-only preflight. Repair can update Stripe customer/card defaults for captured
consent and deliver mail; disabled switches suppress enrolment, not accounting/receipts.

**Enrolment and nextfakt.** Saving a recurring invoice's card, or replacing an enrolled
card, uses the verified intent's customer and checks that Stripe accepts the default payment
method before marking the debtor enrolled. The original enrolment date survives replacement.
Only the current source row of the same debtor/ordrenr series with `nextfakt >= today` gets
`udskriv_til='Stripe'`; historical issued invoices and other series remain unchanged.
`nextfakt` itself is read/filtered, never written. Automatic collection excludes invoices
issued before the enrolment date. Afmeld clears enrolment, returns unpaid Stripe deliveries
to email and queues their invoice mail for retry; it does not cancel Saldi recurrence.

**Charge job.** With both invoice/enrolment switches on, the job collects eligible issued
`Stripe` invoices dated on or after the debtor's enrolment date. Primary declines retry after three then
four calendar days (day 0, +3, +7 when runs are on schedule), max three primary declines.
An eligible backup card is tried once after a decline, including async failures. Technical
failures consume no decline slot and are alerted after two calendar days. Authentication
pauses auto/backup and notifies the customer to resume the same intent on-session.
Dates and `--today` use Copenhagen calendar arithmetic, including DST boundaries.

**Delivery and PDFs.** The form engine records an explicit order id beside each mail slot;
PDF/email mixed batches use that id for cache, subject, attachments and delivery.
Link/notice-mail failure falls back to classic PDF mail for the same order and surfaces
the failure even if fallback succeeds. The classic sender's legacy acceptance string
is not proof of SMTP delivery. Recipient lists support comma/semicolon To+CC and preserve
archive/employee BCC. `Stripe` delivery caches the invoice and sends a **pre-charge
notice**, containing the amount, saved-card label (or “dit gemte kort”), planned date,
contact route and signed invoice/PDF link, with no PDF attachment. There is no silent
suppression of all issuance mail. The notice checks full-open payable amount, routing,
enrolment date and automatic eligibility; if the job cannot collect it, fallback sends
the PDF and the batch reports the notice failure.

The planned date uses the next primary retry date/configured due-or-invoice date,
clamped to today. It is a calendar date, not an execution-time guarantee: an invoice
issued after the 06:00 job with today's planned date is collected on the next run.
Launch uses `charge_on=due` and invoices delivered before that run. Consent names
forfaldsdatoen or fakturadatoen according to `charge_on`, and card removal via the
company contact, not an unimplemented self-service page.

Failure mails use the same persisted attempt-date/retry calendar as the job; only the
third primary decline gets the final no-more-automatic-attempts/rykker paragraph.
A failed backup mail names both cards. Authentication has its own approval mail with
invoice/amount/due date and no promise of another automatic attempt. Customer-facing
codes map to Danish sentences; bookkeeping alerts retain raw codes. Greetings use the
contact's first name or “Hej,”. The Saldi settings hint is shown only when the invoicing
brand resolves to Saldi; its menu destination still needs a live customer-UI check.
Customer copy currently lives in `stripe_txt()`; migration to numeric translation ids
is deferred, while master settings labels use the reserved CSV block.

The page renders the card-submit button disabled, waits for the Payment Element
`ready` event and displays SDK/load failures. It permits one submit at a time, freezes
consent during that submit, and awaits Elements usage changes before validation and
confirmation. Server `setup_future_usage` is authoritative on reservation recovery;
selected replacement consent is rendered only where the shared save-card guard allows it.

`stripePdf.php` owns the cache and render workspaces. Storage prefers
`SALDI_STRIPE_PDF_DIR` or `stripe.pdf_private_dir` (an absolute private parent outside
the repository/document root, with an installation-specific child), then an installation-specific directory in the OS temporary
area. The last resort is `temp/.stripe-private-<install hash>/` with an Apache deny-all
`.htaccess` installed before any artifacts. Any server ignoring `.htaccess` requires the
equivalent deny rule. Existing `temp/<db>/stripe_pdf` and `99999` directories are sealed
with the same rule when PDF storage is accessed; check/purge old artifacts at deployment.
Cache names are HMAC-SHA256 of a separate PDF domain + tenant + order id; they do not expose
sequential ids or usable link signatures. Directories use 0750, published PDFs/locks 0640.
Web and cron must use the same artifact owner (or explicit filesystem ACLs); group-read
permissions alone do not permit writing a 0750 directory.

The renderer holds a per-invoice lock for the whole render, creates a cryptographically
random workspace, and removes that workspace at shutdown (including error exits). The
form engine receives that workspace explicitly instead of `99999`. Publishing writes a
temporary file next to the destination, validates the PDF, then atomically renames it.
Existing readers keep their complete opened file; the signed download streams one opened
file with its matching length. `faktura_pdf.php` remains the public download endpoint.
The CLI uses the same protected destination; `--out` cannot publish arbitrary public paths.
PHP CLI discovery uses PHP_BINARY when it is a CLI interpreter, otherwise `stripe.php_cli`,
PHP_BINDIR/php or PATH; it never hardcodes `/usr/bin/php` or executes php-fpm as a CLI.

Before deployment acceptance, externally request an existing cache and renderer artifact
(including old paths) and require 403/404; then confirm the signed endpoint downloads the
correct PDF. The tenant-only PHPUnit suite cannot prove the production vhost deny rules or
exercise installed PostScript/HTML/PDF executables.


## 9. Acceptance and operations

[GO-LIVE-821.md](GO-LIVE-821.md) supplies the saldi_1091 settings, 06:00 charge and
06:30 repair-only cron entries, immediate read-only SQL, first controlled live payment,
the four pilot recurrence stops before 15 November, and rollback. The complete test
matrix and its HTTP/browser/SMTP/server boundaries are in
[tests/stripe/README.md](../../tests/stripe/README.md). PHPUnit executes real PostgreSQL
fixture queries and production payment/delivery/panel/dispatch logic with fake
Stripe/mail transports; it cannot certify real Stripe.js or production server policy.

Account for three manual outcomes explicitly: second captures (`duplicate_paid`),
refunds/disputes, and ambiguous creates older than the safe 23-hour retry window.
None triggers an automatic refund or automatic reposting. SMTP acceptance and its DB
marker cannot be atomic; a process death in between can resend a receipt/notice on
repair. Keep identifiers and reconcile manually instead of dropping accounting history.
