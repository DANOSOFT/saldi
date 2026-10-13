# Invoice payment by card ("Se faktura og betal")

<!-- 20261012 CL/LH Document Slice B event leasing, identity/time facts, and audited refund/dispute holds. -->
<!-- 20261012 CL/LH Document Slice C durable requests/receipts, reconciliation, recovery and the notification outbox. -->
<!-- 20261012 CL/LH Correct Slice B child-process request initialization and document method-guard coverage. -->
<!-- 20261012 CL/LH Document payment fixture connection cleanup and the PostgreSQL gate regression. -->
<!-- 20261012 CL/LH Document sticky dispatch ambiguity, Stripe failure-time recovery and signed-only event ordering (N-01/N-02). -->
<!-- 20261013 CL/LH Document Q-01–Q-06 fair recovery, settings authorization, decline evidence, journal totals, local refusals and due-date parity. -->
<!-- 20261013 CL/LH (CodeRabbit) Document C-1–C-4 generic database errors, missing mail recipients, normalized currency and unnumbered orders. -->

Adam's 2026-10-07 mockup, built as an invoice-first flow: Saldi stays the
invoicing system and Stripe is only the payment rail. The customer opens a
signed link from the ordinary invoice mail, sees the invoice and pays it with
a card. They can tick "Betal fremtidige fakturaer automatisk", which saves
the card. Saldi then charges that card for later invoices on their due date.
Bank transfer remains the alternative and is shown on the page.

This replaces the period/subscription model on
`feature/stripe-manual-period-billing` for Danosoft's own invoices. The
`api/stripe/subscribe.php` signup flow remains available when invoice payment is off.
When `invoice_pay_enabled=on`, invoice mails suppress its subscription CTA,
even if subscription signup (`enabled`) is also on. Existing subscription
webhooks and renewals keep running.

## Pieces

| File | Role |
|---|---|
| `includes/stripeIncludes/InvoicePaymentService.php` | Hosted-page facts, PaymentIntents, webhook handling, autopay, card removal |
| `includes/stripeIncludes/InvoicePaymentReadModel.php` | Read-only, batched REST customer and invoice projections |
| `restapi/core/InvoicePaymentReadContext.php` | Lazy authenticated tenant PDO connection and request-scoped page hydration |
| `includes/stripeIncludes/InvoicePaymentOperations.php` | Immutable creating requests, tenant/mode idempotency keys and replay expiry |
| `includes/stripeIncludes/InvoicePaymentOutbox.php` | Transactional notification queue and independent delivery retries |
| `includes/stripeIncludes/InvoicePaymentLedger.php` | Optional automatic posting of the receipt (transaktioner, kontoplan, openpost) |
| `includes/stripeIncludes/InvoicePaymentEligibility.php` | Shared read-only collection and autopay reasons, including batched debtor reads |
| `includes/stripeIncludes/InvoicePaymentStore.php`, `InvoicePaymentSchema.php`, `InvoicePaymentMath.php` | PDO store, DDL, øre arithmetic |
| `includes/stripeIncludes/invoicePaymentRuntime.php` | Wiring: pinned tenant, Stripe transport, alerts to `bookkeeper_email` |
| `api/stripe/faktura.php` | Hosted invoice page with the Stripe Payment Element |
| `api/stripe/kort.php` | "Fjern kort" page (signed link) |
| `api/stripe/webhook.php` | Leased dispatch for subscriptions, invoice PaymentIntents and refund/dispute holds |
| `includes/stripeIncludes/StripeEventLedger.php` | Race-safe event dedupe, five-minute ownership leases and retry/requeue |
| `includes/stripeIncludes/stripeWebhookRouting.php` | Pure kind/tenant routing guard before invoice runtime loading |
| `tools/stripe_event_requeue.php` | Operator requeue; resend the event from Stripe Dashboard afterwards |
| `tools/stripe_invoice_autopay.php` | Cron worker: retries unposted receipts and charges saved cards |
| `includes/formFuncIncludes/invoicePaymentLink.php` | `$fakturalink` for invoice mails |
| `debitor/debitorkort.php` | "Gemt kort" row and the staff "Fjern kort" action |
| `systemdata/diverseIncludes/stripeValg.php` | Settings UI |

Tables (`betweenUpdates.php`, with parity in `admin/opret.php`):
`stripe_invoice_customers`, `stripe_invoice_cards`, `stripe_invoice_payments`,
`stripe_invoice_operations`, `stripe_invoice_notifications` and `stripe_invoice_audit`. The column
`adresser.stripe_fravalg` stores a debtor's opt-out.

The new payment CREATE definitions also include nullable `received_at`,
`failed_at`, `failed_charge`, `event_created`, `consent_granted_at`, `payment_method`, `card_generation` and
`open_ore_at_receipt`, plus `collection_hold` (default 0), `hold_reason`, `advice_code` and nullable `error_type`.
`settlement` is VARCHAR(16), including `posted_manual`. Operations retain the
immutable `request_path`, `request_json`, `idempotency_key`,
`first_dispatch_at`, `ambiguous_dispatch` (default 0), `card_generation`, `payment_id`, `status`, `result_id`,
`last_error` and `updated_at`, alongside the existing operation facts. Client
secrets are never stored. The notification table holds `id`, `kind`, unique
`dedupe_key`, `subject`, `body`, `created_at`, nullable `sent_at`, `attempts`
and `last_error`.
Cards include `generation` (default 1), `terms_version` and
`consent_text_sha256`. Times are Unix seconds stored as BIGINT. Receipt and
failure timestamps are distinct from mutable `updated_at` and settlement times.
The webhook populates `received_at` once at first success and updates `failed_at`
on an accepted failure, using Stripe event `created`. `event_created` is the
last applied **signed webhook** snapshot time; API snapshots never advance it.
API recovery retrieves the intent with `expand[]=latest_charge` and uses the
charge's `created` for the receipt, falling back to the intent's `created`.
For declines it uses the failed charge's `created`, including a charge supplied
by `last_payment_error` (retrieved by id when needed). Only when Stripe gives
no failure time does it use the clock; intent creation is not a failure time.
`failed_charge` records that identity. Repeated API observations with the same
error code and charge preserve `failed_at`, including a signed event time or
the initial clock fallback. Discovery time stays in `updated_at`, separate
from receipt/failure facts. Historical unknown facts are not backfilled from `updated_at`. The existing `stripe_events` table gains `claim_token`,
`claimed_at` (TIMESTAMP), `attempts` (default 0), and `event_created` (BIGINT)
through a serialized, idempotent column upgrade.

Tenant login and fresh-tenant creation share the invoice CREATE statements.
Provisioning uses a dedicated PDO connection and a transaction/advisory lock,
and logs failures without terminating ordinary login. The login guard checks
the newest `stripe_invoice_notifications` table first, then every declared
table, column and index, repairing missing tables/indexes even when
`stripe_invoice_audit` already exists. The runtime checks the complete
invoice schema and fails closed; payment requests never run DDL. The tables
have never been deployed, so their definitions change directly, without ALTER
migrations for `stripe_invoice_*`.

## Deployment order

A merge to master deploys `/master` immediately through the GitHub runner.
Deploying code does not run `betweenUpdates`; tenant login does. Follow this
order before the webhook starts requiring event leases, even with every autopay
switch off:

1. Merge a separate migration-first change that **only adds the four
   `stripe_events` columns**, with idempotent check-then-ALTER in
   `includes/betweenUpdates.php` and matching definitions in `admin/opret.php`.
2. Log into **saldi_1091 on /master**, so `betweenUpdates` runs.
3. In saldi_1091, verify all four columns with this read-only query:

   ```sql
   SELECT column_name, data_type, character_maximum_length,
          is_nullable, column_default
   FROM information_schema.columns
   WHERE table_schema = 'public'
     AND table_name = 'stripe_events'
     AND column_name IN ('claim_token', 'claimed_at', 'attempts', 'event_created')
   ORDER BY column_name;
   ```

   Expect four rows: nullable `claim_token` (varchar 64), nullable `claimed_at`
   (timestamp), `attempts` (integer, NOT NULL, default 0), and nullable
   `event_created` (bigint). Investigate missing rows or wrong definitions before
   proceeding.
4. Only then merge the autopay change. Provision the remaining invoice tables
   and complete acceptance before enabling any collection switch.

If storing or claiming an event fails, the webhook returns **503** so Stripe
retries, and sends at most one successfully accepted operator mail per hour:
"Stripe-webhook kan ikke gemme events - log ind i regnskabet, så databasen opdateres".
No request handler runs DDL. Failed mail does not consume the alert throttle.
This retry path is a recovery measure; migration-first deployment remains required.

## Setup

The flow only runs in the tenant pinned by `stripeConfigDb()`, which is
Danosoft's own regnskab. Requests for any other tenant are refused.
Invoice payment requires PostgreSQL; MySQL installations produce no invoice
payment links, disable the invoice-payment switches in the settings UI, and
force those switches off when saving.

Keep `invoice_pay_enabled`, `autopay_enabled` and `posting_enabled` off until
provisioning and acceptance checks succeed; absent switches default to off.
Use a separate pinned test tenant/install, test credentials and its own webhook
endpoint for acceptance. Never change the live billing tenant's shared mode or
keys: the four retained subscribers need their live events to keep processing.

1. With Systemdata write permission (`rettigheder` index 1 equals `1`),
   go to Systemdata → Diverse → Stripe and fill in:
   - `secret_key`: `sk_`/`rk_` test or live.
   - `publishable_key`: `pk_`, same mode.
   - `webhook_secret`.
   - `public_base_url`: https, no trailing slash.
   - `bookkeeper_email`: receives every alert.
   - `link_secret`: created automatically on the first save.

   If `bookkeeper_email` is empty, alerts use the tenant's first seller
   (`adresser.art='S'`) email when valid. With no valid recipient, record-only
   subscription payments are completed with `error='no_alert_recipient'`;
   configure an address and follow up on those recorded payments manually.

2. Turn on **Fakturabetaling** (`invoice_pay_enabled`).
3. In the Stripe dashboard, point the webhook endpoint at
   `<public_base_url>/api/stripe/webhook.php`. Retain the subscription events:
   - `checkout.session.completed`
   - `invoice.paid`
   - `invoice.payment_failed`
   - `customer.subscription.deleted`
   - `charge.refunded`
   - `charge.dispute.created`
   - `charge.dispute.closed`

   Add the invoice-payment events:
   - `payment_intent.succeeded`
   - `payment_intent.processing`
   - `payment_intent.payment_failed`
   - `payment_intent.canceled`
4. Add `$fakturalink` to the invoice mail text (Systemdata → Formularer →
   mail text for invoices). It expands to the signed page URL. It expands to
   an empty string in any of these cases:
   - the feature is off or the settings are incomplete;
   - the invoice is not DKK, or is a credit note or zero invoice;
   - the debtor is blocked by PBS, a retained subscription or opt-out;
   - the tenant is not the pinned one.

   **Mails show no link until the template has the token.**
5. To use automatic charging:
   - turn on **Automatisk træk** (`autopay_enabled`);
   - install the cron job:
     ```
     17 7,13 * * * php /var/www/saldi/tools/stripe_invoice_autopay.php
     ```
     Install it even while collection is off: it expires ambiguous requests,
     retrieves aged intents, retries card enrichment and posting, reconciles
     manual receipts, and flushes notifications independently of both
     collection switches. It runs one instance at a time (flock). Exit codes: 0 means ok, 1 means
     one or more declines, 2 means a setup error.
6. Inspect delivery failures in `stripe_invoice_notifications` (`sent_at IS NULL`,
   `attempts`, `last_error`) and ambiguous creating requests in
   `stripe_invoice_operations` (`status='needs_reconcile'`). Restore mail/configuration
   and keep the worker running. Do not repeat a payment to resend its notification.
   For `needs_reconcile`, locate the request by its tenant/debtor/invoice metadata
   and idempotency key in Stripe, then reconcile the actual outcome before any
   new collection. `ambiguous_dispatch=1` records an unknown dispatch outcome;
   repairing credentials or receiving a later 4xx does not clear it or authorize
   a fresh key. A local status-0 refusal (`config_mode`, `config_key`,
   `config_mode_mismatch`, `config_method`) before any ambiguous dispatch
   is rejected safely: repair the configuration and the next attempt uses a
   fresh operation/key, even after 20 hours. A later definitive rejection parks
   it immediately in
   `needs_reconcile`. Reconcile by the stored identity/metadata, including a
   delayed signed event for the original intent. The worker never invents a
   replacement for an unknown result.
7. Automatic posting is optional:
   - set `clearing_account` to an open D/S account (the Stripe clearing
     account);
   - turn on **Bogfør automatisk** (`posting_enabled`).

   In **live mode**, when posting is off, every card payment sends the bookkeeper the alert
   "Kortbetaling modtaget - bogfør i kassekladden".
   In **test mode**, posting is always refused, including direct ledger calls,
   regardless of `posting_enabled`. Receipts are recorded as `manual` with
   `settle_error='test_mode'`; one "Testbetaling modtaget - ingen bogføring"
   alert is sent. `ordrer`, `openpost`, `transaktioner` and `kontoplan` are never
   changed by a test receipt or a settlement retry.

REST reads require the customer and payment tables/columns and a configured
`mode`, but require no Stripe keys, pin file (`.ht_stripe_db`), or payment
runtime. The PDO connection uses the database selected by REST authentication
and the installation credentials from `includes/connect.php`. MySQL exposes
null payment blocks. Existing authentication and authorization apply.
No additional webhook events are needed for recovery or the REST producer.
Retain `payment_intent.payment_failed`: delayed signed failure facts still apply
according to signed event ordering, even after API recovery polls. REST receipt
and decline times come from signed events or the Stripe charge facts recorded
by API recovery.

## Behaviour

- **Stripe settings authorization.** Every settings mutation, including keys,
  collection switches, clearing account and posting, requires explicit
  Systemdata write permission (`rettigheder` index 1 equals `1`) and valid
  session CSRF. Read permission `2`, absent rights and other permissions
  cannot save even with a valid CSRF token. All mutation controls are disabled
  for read-only sessions while still displaying the saved values.
- **Webhook ownership and retry.** Verify the signature, receive/claim the
  event, check mode, dispatch, then finish. The unique `event_id` and a
  conditional UPDATE protect claims on PostgreSQL and SQLite. A fresh foreign
  lease gets HTTP 503; a crashed lease can be reclaimed after five minutes.
  Only `processed`, `recorded`, `ignored`, `ignored_mode` and `failed_permanent`
  are terminal duplicates (HTTP 200). Explicit errors become `retry`, clear
  the lease immediately and increment `attempts`. Legacy `failed` and
  `retrying` rows recover on their next delivery. Stale tokens cannot finish
  or fail another delivery's claim. Mode mismatches are terminal `ignored_mode`.
  Database, unmapped subscription identity and invoice runtime failures return
  5xx and remain retryable. Retried subscription PDFs overwrite their existing
  pool entry; the manual-booking alert is throttled by Stripe invoice id.
  The throttle is recorded only after mail acceptance. With a valid recipient,
  failed or throwing booking mail returns 503 and releases the event for retry;
  already accepted mail counts as success during a forced event replay. With
  no valid recipient, the event finishes `recorded` (HTTP 200), stores
  `error='no_alert_recipient'` and logs that reason instead of retrying forever.
- **Routing and switches.** PaymentIntents with another tenant's `saldi_db`,
  missing metadata or a kind other than `invoice_manual`/`invoice_auto` finish
  `ignored` before the invoice runtime is loaded. Runtime loading and
  construction errors on tagged events are retryable. Subscription
  `import_enabled` prerequisites apply only to the subscription `invoice.paid`
  import branch; import remains unwired and retryable on this installation.
  It cannot block invoice receipts or activate their `posting_enabled` path.
  Turning collection switches off still permits outstanding receipts and
  correction events to be recorded. The subscription handlers retain their
  order and existing behavior.
- **Payment facts and identity.** A signed event older than the payment row's
  `event_created` is ignored; success always wins and stays terminal. API recovery
  never advances that signed-event watermark. It preserves `failed_at` for an
  unchanged error code/charge, uses the failed charge's Stripe `created` for a
  newly discovered failure, and falls back to the clock only without a Stripe
  failure time. A delayed signed failure can still add facts after recovery.
  Validate tenant, invoice/debtor, local Stripe customer mapping, expected
  amount, DKK currency and manual/automatic kind before recording a receipt
  or changing a card. An identity conflict returns 503 with its diagnostic
  retained in `stripe_events`; the bookkeeper must reconcile it before replay.
  Amount validation uses the stored attempt amount, so a bank balance change
  after dispatch does not discard real money.
  Card save/removal/failure decisions share a mode/debtor lock. Save and removal
  advance `generation`; automatic attempts remember their method/generation.
  A delayed hard decline can break only that matching, unrevoked generation,
  even when a synchronous decline already recorded `auto_failed` without
  sufficient method identity. Later accepted issuer advice is applied too.
  Card-state changes are separate from notification dedupe: each attempt
  still sends one decline alert, and an already broken card keeps its first
  break time without another card audit.
  Saved-card consent is ordered by actual consent-grant time, then the local payment id
  for ties, so an older success cannot replace a newer choice. Detach rechecks
  that the method is no longer the current active choice under the same lock.
- **Refunds and disputes.** Resolve `charge.payment_intent` to a configured-mode
  invoice payment (manual or automatic). For disputes supplied as a charge id,
  retrieve the charge first. All delivered `charge.dispute.*` types use this
  hold path, including updates and fund movements. If a creation response was lost and the intent id
  is not yet bound, retrieve its PaymentIntent and validate `saldi_kind`,
  `saldi_db`, `saldi_order_id`, `saldi_konto_id`, `saldi_payment_row`, invoice
  identity/amount/customer and the durable creating request. Bind that payment
  and apply the hold before recording success. Missing/conflicting durable facts
  keep the correction retryable with 503; it cannot fall through to subscription
  handling or become terminal. Record the event, refund identities/cumulative
  refund amount or dispute id/status/deadline in the payment audit. Set
  `collection_hold=1` with reason `refund` or `dispute`, and alert with debtor,
  invoice, amount and Stripe references. A repeated event does not repeat the
  audit/alert or reapply a cleared hold. Neither a refund nor a closed/won
  dispute reverses the original successful receipt or posts a ledger correction.
  Held invoices show "Opkrævning sat på hold" and cannot be paid on the hosted
  page or charged automatically, even if the ledger is reopened manually.
  Staff reconcile/post the correction manually, then press **Frigiv opkrævning**
  next to the saved-card controls on debitorkort. The selected payment must
  belong to that debtor/mode; the POST requires the session-bound `stripe_hold_csrf`
  token and explicit write permission (`rettigheder` position 6 equals `1`).
  Read-only permission `2` is refused before service construction. The action
  is audited and redirects; its control is hidden for sessions without write permission.
  Holds stay accessible without a saved card. Clearing a hold preserves payment
  and settlement history and does not itself make a received receipt payable.
  Subscription refunds keep their existing manual-accounting behavior.
- **Operator replay.** Run
  `php tools/stripe_event_requeue.php --event=evt_...` to return a stuck row
  to `retry` and clear its lease. Then resend that event from Stripe Dashboard.
  Terminal rows require explicit `--force`; the CLI does not contact Stripe
  or perform the business processing itself.


- **Collection exclusivity.** `InvoicePaymentEligibility` returns all applicable
  reasons in this stable order: `pbs`, `subscription`, `optout`, `disabled`.
  Empty means eligible. PBS is set when its trimmed, case-insensitive value is
  `on`, `1`, `t`, `true` or `yes`. A row for the debtor in `stripe_customers`
  with status `active`, `trialing` or `past_due` blocks invoice collection,
  regardless of invoice-payment mode; canceled subscribers do not block it.
  A missing subscription table is allowed. Non-empty trimmed `stripe_fravalg`
  blocks collection, and the invoice switch must be exactly `on`.
  Batched methods `collectionReasonsFor()` and `autopayReasonsFor()` return
  reason lists keyed by debtor id, using the caller's PDO tenant connection.
  They do no writes, DDL, Stripe calls or runtime construction.
  `autopayReasons()` adds `autopay_disabled`, `no_card`, `card_broken` and
  `card_revoked` where applicable. The saved card must match the configured
  mode, be enabled, have consent and a payment method. Service wrappers
  `collectionBlock()` and `autopayBlock()` return the first reason or null.
- **Old links and enrollment.** Collection eligibility is checked when minting
  a mail link, showing the payable page and starting a payment. An old signed
  link cannot bypass a new collection block. `startPayment` returns a Danish
  explanation for each reason. A successful payment is still recorded if the
  debtor changes collection channel during checkout, but it never activates
  autopay for a blocked debtor. Consent can be saved while the global autopay
  worker switch is off, provided collection itself is eligible.
- **Mail CTA.** When `invoice_pay_enabled=on`, `$abonnementslink` is empty.
  HTML and plain-text templates containing both tokens therefore offer at
  most one collection button/link.
- **Worker eligibility.** Competing collection channels and invoice-payment
  switch changes override existing saved consent. The worker skips a blocked
  debtor, checks eligibility again when preparing a charge, and alerts at
  most once per debtor per 24 hours. The throttle is persisted in audit rows,
  so repeated runs and separate worker instances share it.

- **Payable invoice.** An invoice can be paid on the page when all of these
  hold:
  - it is an `ordrer` row with `art='DO'` and `status>=3`;
  - its currency is DKK (or empty);
  - the debtor has no collection blocker (PBS, subscription, opt-out or switch);
  - it has open `openpost` items above zero;
  - no other payment for it is in flight or received-but-unposted;
  - no configured-mode payment for the invoice is on a collection hold.
- **Amount.** The amount charged is the sum of the invoice's open `openpost`
  items, so a partial bank payment lowers it.
- **Due date.** Use the first valid calendar date on an outstanding positive
  item tied to the order, in id order. Settled items and invalid dates are skipped.
  Fall back to a valid `ordrer.due_date`, then a valid `fakturadate` plus the
  payment terms (`lb` means end of month first). With no valid base date, the
  worker skips collection.
- **The page itself.**
  - An ordinary GET makes no Stripe calls and no writes. A signed return GET
    carrying `payment_intent` retrieves Stripe and commits reconciliation.
  - The PaymentIntent is created or reused only when the customer presses
    Pay. Card only.
  - Database failures while starting a payment are logged privately and return
    HTTP 500 with "Betalingen kunne ikke startes. Prøv igen om lidt."; SQL
    diagnostics are never included in that public error.
  - Ticking "save card" sets `setup_future_usage=off_session` on the same
    intent.
- **Saved card.** On `payment_intent.succeeded`, a card the customer chose to
  save is enrolled through a separate retryable `card_enrichment` operation,
  after the validated receipt commits with `amount_ore`, `received_at` and
  `open_ore_at_receipt`. Only successful enrichment of the newest valid choice
  activates the card. A timeout or missing PaymentMethod keeps the money
  recorded and enrollment pending; it does not enable autopay. Consent records
  the placeholder terms version `v1-2026-10`, pending Adam's approval.
  `startPayment` persists `consent_granted_at` when recording `save_card=1`
  with the terms version and text hash, including reuse of an existing intent.
  It is the actual grant time, not the intent's earlier creation time. Card
  `consent_at`, choice ordering, revocation comparisons and the worker's invoice
  cutoff use that persisted time. Reuse records the current card generation;
  an older grant cannot undo a later revocation. An unchecked choice clears
  the grant. An intent created on day 1 but opted in on day 12 covers only
  invoices dated day 12 onward.
  Autopay then only covers invoices dated on or after the consent date; older
  invoices are never charged automatically.
- **Autopay.** Each run charges invoices that are due today or earlier, with
  an off-session PaymentIntent.
  - A soft decline is retried after 3 days, up to 3 attempts in total.
  - A hard decline marks the card broken and stops charging it. Hard
    declines are: `authentication_required`, `expired_card`,
    `card_not_supported`, `lost_card`, `stolen_card`, `pickup_card`.
  - Every decline mails the bookkeeper. The customer gets no mail from
    Saldi; the next invoice mail still carries the pay link.
  - A configuration, transport, throttling or amount error keeps the creating
    operation recoverable, sends a technical-error notification, and never
    marks a card broken or spends another card-decline attempt. Issuer advice
    `do_not_try_again`/`confirm_card_data` also stops the matching card generation.
  - Every creating POST (customer and intent) has a committed immutable request
    before dispatch. Keys include the tenant and mode. Unknown responses replay
    exactly the stored payload/key after ten minutes for automatic attempts.
    At **20 hours from first dispatch**, replay stops, the operation becomes
    `needs_reconcile`, and the bookkeeper gets one alert. Expiry runs even with
    collection disabled or a revoked card. Known object ids use GET recovery.
    A Stripe error object with definitive HTTP 4xx (including 400/401/402/403/404,
    excluding 409/429) marks the operation `rejected` **only if no earlier
    dispatch was ambiguous**. The explicit local status-0 no-dispatch codes
    from `stripeHttpRequest` (`config_mode`, `config_key`,
    `config_mode_mismatch`, `config_method`) follow the same rule; an unknown
    status-0 code remains ambiguous. A later retry prepares a fresh operation/key
    for
    the same durable payment, even after 20 hours. The rejected request and key
    remain immutable and auditable. Dispatch persists `ambiguous_dispatch=1`
    before the POST so interrupted requests are also protected. A definitive
    first-dispatch rejection clears that provisional flag; an earlier unknown
    outcome stays sticky. A later definitive rejection immediately becomes
    `needs_reconcile`, alerts the bookkeeper and stops POSTs even after repair.
    A validated original outcome can reconcile the operation; a later rejection
    cannot authorize another charge. Transport failures, 409/429 and server
    errors retain the original key and cutoff.
  - Page and worker use the same prior-intent helper. A replacement requires
    confirmed cancellation; a success is recorded, processing blocks collection,
    and failed cancellation/retrieval blocks and alerts. Missing webhooks never
    justify replacement.
  - A PostgreSQL session lock serializes each debtor across preparation, commits,
    dispatch, card replacement and removal. Each invoice reloads current flags,
    consent, card method/generation and eligibility. A hard decline stops the
    remaining debtor loop. Removal cancels undispatched automatic requests and
    parks ambiguous dispatched ones for reconciliation.
  - A synchronous API decline applies side effects only if an atomic
    `created -> failed` UPDATE succeeds. A webhook success always stays terminal.
  - Known `created`, `processing`, `requires_payment_method`,
    `requires_confirmation` and `requires_action` intents older than one hour are retrieved and
    transitioned through the same receipt logic, including when both collection
    switches are off. Recovery pages by id in batches of 100 through all
    eligible rows up to the run's initial maximum id, using one age cutoff.
    Each candidate is checked once in that run, including failed retrievals.
    Thus 100 older abandoned intents cannot prevent a later successful
    intent from being recorded in the same run. New rows wait for a later run
    and the one-hour age threshold. GET failure stays pending and is alerted,
    while recovery continues to later candidates.
- **Manual receipts.** In live mode, reconciliation runs before the worker's
  eligibility and credit guard, and on payment initiation. A `manual` receipt
  becomes `posted_manual` when the matching debtor/invoice has a positive
  `openpost` with `udlignet='1'`, or its open amount falls by at least the receipt
  amount from `open_ore_at_receipt`. The transition is audited and creates no
  ledger entries. Partial reductions below that amount stay unresolved; an
  unallocated receipt under another invoice number does not count. Enabling
  posting afterward never reposts either `manual` or `posted_manual` receipts.
  An invoice with an unresolved receipt is skipped and alerted once. Test-mode
  receipts retain their manual/test state and never become real ledger evidence.
- **Confirmed amount.** All money calculations use integer øre. Recheck the
  current payable amount before creating dispatch and return `amount`/`currency`
  with the secret. If a bank payment changed the displayed amount, the browser
  updates its total, button and Elements amount and asks the customer to press
  Pay again. Processing/success are structured committed results, not exceptions
  inside a business transaction. Balances below 250 øre use bank transfer
  ([Stripe's DKK settlement minimum](https://docs.stripe.com/currencies#minimum-and-maximum-charge-amounts));
  API configuration/amount errors remain technical errors if settlement uses a
  different currency. Mixed-currency open items block collection for manual review.
- **Notifications.** Receipt, hold, decline, credit and recovery notifications
  are enqueued with business facts in the unique-key outbox. Delivery runs after
  commit and from the worker; `false`/exceptions retain the job and diagnostic
  for a later request/run. Filesystem mail throttling is bypassed for queued mail.
  Card declines have kind `card_decline`; technical errors use `configuration`
  or `transport`. Ordinary receipt/hold keys are permanent, while daily/hourly
  operational notices retain their throttle. At most 100 jobs are attempted per
  flush, with lower-attempt jobs first. A failed job is attempted once per request.
- **Credit guard.** Autopay skips the charge and alerts if the debtor's total
  open balance is below the invoice amount, for example because of an
  unallocated credit note. The balance excludes card receipts that are not
  yet reconciled; both `posted` and `posted_manual` receipts are excluded.
- **Removing the card.**
  - Customer: the "Fjern kort" link on the invoice page opens
    `api/stripe/kort.php`.
  - Staff: press **Fjern kort** on debitorkort. A dedicated POST uses the
    same session CSRF token and module write check as hold release and redirects
    before any address writes. Missing/invalid tokens and read-only direct POSTs
    are refused; the button is hidden without write permission.

  Either way autopay stops at once and the card is detached at Stripe. If the
  detach fails, the bookkeeper is alerted; autopay stays off regardless.
- **Posting** (live mode only, when `posting_enabled` is on). The service:
  - debits the clearing account;
  - credits the debtor group's collective account (grupper `DG`, box2);
  - adds a negative `openpost`, and settles the open items and sets
    `ordrer.betalt` when they sum to zero.

  Invoice currency is trimmed and uppercased before the DKK posting check,
  matching collection eligibility; a stored value such as ` dkk` posts normally.

  Posting is refused, and the bookkeeper alerted, if any of these hold:
  - the fiscal year is closed or missing;
  - the VAT period is locked;
  - an account is missing.

  The payment is kept and the cron worker retries the posting. Its date is the
  Europe/Copenhagen date of `received_at`, including delayed delivery and later
  retries. A closed original period remains an error; the retry does not silently
  change the accounting date. Checks, postings and allocation share one PDO
  transaction/store lock. PostgreSQL table locks coordinate legacy allocation
  writers using `MAX(udlign_id)` and protect accounting balances. Following
  fiscal-year openings use the journal's transfer rules on that same connection.
  Successor-year balances follow `includes/genberegn.php`: only D/S accounts
  receive their own transaction movements; non-S opening balances are zero.
  In account-number order, Z subtotals sum the preceding balances from
  `fra_kto` through their account number, excluding H and Z but including R;
  R accounts copy a preceding Z referenced by `fra_kto`. These recalculations
  share the receipt's PDO transaction, so posting cannot erase successor totals.

## REST: autopay and payment blocks

The administration panel in **PR #785 consumes this contract**. The producer
ships independently: customer detail and list/search responses add `autopay`;
`/debitor/invoices` detail and list responses add `payment`. All existing fields,
including the raw invoice `paid` field, remain present. Keep #785's `pbs` and
subscription `stripe` blocks separate when merging either PR first.

Both blocks are **null** when the required read schema is absent/incomplete,
the opt-out column is missing, the configured mode is neither `test` nor
`live`, or the tenant cannot provide a supported PDO connection. This means
payment information is unavailable. With a provisioned schema and valid mode,
a debtor without a card or attempts has an object with null facts and zero
counts. Missing optional facts use null; absence of an attempt is not failure.
An unknown order id has a null block, and non-debtor orders have no payment facts.

Customer `autopay` has exactly these fields:

| Field | Type / meaning |
|---|---|
| `mode` | `test` or `live`; only rows from this configured mode are read |
| `invoicePayEnabled` | boolean; `invoice_pay_enabled` is exactly `on` |
| `autopayEnabled` | boolean; `autopay_enabled` is exactly `on` |
| `eligible` | boolean; no shared autopay eligibility blockers |
| `blockedReasons` | string array, in order: `pbs`, `subscription`, `optout`, `disabled`, `autopay_disabled`, `no_card`, `card_broken`, `card_revoked` (only applicable reasons) |
| `card` | null or `{brand, last4, expMonth, expYear, consentAt, consentOrderId, termsVersion, brokenAt, brokenReason, revokedAt}` |
| `lastPayment` | null or `{orderId, invoiceNo, kind, amountOre, receivedAt, settlement}` |
| `lastDecline` | null or `{orderId, invoiceNo, kind, failedAt, code, hard}` |
| `unresolvedSettlementCount` | integer count of successful receipts still `pending`, `manual`, or `error` after read-only reconciliation |

The card is returned even when broken or revoked. `brand` and `last4` are
strings; expiry month/year are integers. `consentOrderId` is an integer or null;
`termsVersion` is a string or null. `brokenReason` and decline `code` are safe
code strings or null; free-form diagnostics and payloads are never returned.
Receipt/decline `orderId` is an integer, `invoiceNo` is a string or null, and
`kind` is `manual` or `auto`. `amountOre` is integer øre. `settlement` is
`pending`, `manual`, `posted`, `posted_manual`, or `error`. `hard` is a boolean
using the same hard-decline codes as the worker. Cancellation and transport or
configuration failures do not count as card declines. Hosted manual declines
remain `requires_payment_method` (or another hosted retry state); a recorded
`failed_at` with Stripe `error_type=card_error` (or a recognised legacy
card-decline code) still populates `lastDecline`.
Technical/configuration codes are excluded.

All `*At` fields, including attempt `at`, are ISO 8601 strings in
**Europe/Copenhagen**, with the appropriate `+01:00`/`+02:00` offset, or null.
Historical receipt/decline times stay null when unknown; neither `updated_at`
nor `settled_at` is substituted. Latest receipts/declines sort by immutable
`received_at`/`failed_at`, with highest row id breaking ties and unknown times
last. The card is the unique configured-mode row for the debtor.

Invoice `payment` has exactly these fields:

| Field | Type / meaning |
|---|---|
| `ledgerPaid` | boolean; at least one positive invoice `openpost` exists and all positive items are `udlignet='1'`; independent of `ordrer.betalt` |
| `ledgerOpenOre` | integer øre, sum of matching unsettled `openpost` items, including credits/partial bank receipts |
| `dueDate` | `YYYY-MM-DD` string or null; outstanding positive invoice item's `forfaldsdate` (settled `udlignet='1'` items are excluded) by order `refnr`, else `ordrer.due_date`, else invoice date plus payment terms (`lb` starts at month end); invalid dates are skipped |
| `receivedUnpostedOre` | integer øre of successful card receipts still unreconciled |
| `processing` | boolean; any configured-mode attempt is `created` or `processing` |
| `collectionHold` | boolean; any configured-mode payment has a collection hold |
| `latestAttempt` | null or `{kind, status, at, code}`; newest `created_at`, then highest row id |

Attempt `kind` is `manual` or `auto`, `status` is the stored status string and
`code` is a safe code string or null. Attempt `at` is immutable failure time
for a failed attempt, immutable receipt time for success, otherwise creation
time. The ledger match uses both debtor id and invoice number. Orders whose
invoice number is empty after trimming or NULL skip the ledger join and report
`ledgerPaid=false` and `ledgerOpenOre=0`, even when the debtor has blank-number
open items. Payment facts also require that the order still belongs to the
payment's debtor.

A manual receipt is projected as `posted_manual` when the positive invoice
posting is settled, or the open balance has fallen by at least the receipt's
amount compared with `open_ore_at_receipt`. Already `posted`/`posted_manual`
receipts are excluded from unposted totals. This read-only projection works
before the worker persists reconciliation and **never writes** the settlement.
A smaller reduction still leaves the full receipt unresolved.

REST GETs use a standalone PDO read model, shared eligibility, and bounded
batches of ids. Customer and invoice lists prefetch payment facts per page;
constructing each legacy model adds no individual payment lookup. The existing
address/contact/order hydration queries remain. The read model never constructs
the charging service/runtime, calls Stripe, runs DDL, posts receipts, or sends
mail. No client secrets, Stripe customer/payment-method identifiers, signed
links, credentials, or raw payloads are exposed.

## Decisions taken without Adam's answer

Adam had not answered his questions when this was built. These are the
defaults; each is a setting or a small change if he disagrees.

1. Danosoft is the only tenant (`stripeConfigDb`). Other Saldi tenants
   cannot use this flow.
2. The save-card box starts **unticked**. The mockup shows it pre-ticked, but
   consent should be an active choice. To pre-tick it, add `checked` to the
   `id='save'` input in `faktura.php`. The page script reads its starting
   state.
3. Card only. No MobilePay or bank debit through Stripe.
4. `posting_enabled` is off by default, so the bookkeeper posts each receipt
   from the alert until the clearing account is agreed.
5. A declined automatic charge alerts only the bookkeeper.
6. The page has no PDF download. The PDF is already attached to the invoice
   mail.
7. "Fjern kort i Saldi" is read as two things: a signed link for the customer
   and a debitorkort action for staff. There is no customer login.

Added 2026-10-12, when the integration with the admin panel (PR #785) was
planned. These are also defaults, not Adam's decisions:

8. One collection channel per debtor. Debtors on Betalingsservice
   (`adresser.pbs`) and debtors with a live Stripe subscription are excluded
   from card payment and autopay. The bookkeeper moves a debtor over by
   stopping PBS or the subscription first.
9. The consent text in `InvoicePaymentConsent` (version `v1-2026-10`) is a
   draft. It waits for Adam's approval: `APPROVED` stays false and the
   settings page shows "udkast, ikke godkendt endnu". Any wording change after
   release needs a new version, because stored hashes refer to the exact text.
10. Test mode never posts to the ledger, whatever `posting_enabled` says. Test
    runs belong on a separate tenant with test keys and its own webhook
    endpoint; never switch the live tenant's `mode`, because that would mark
    the live subscribers' events `ignored_mode`.
11. Refunds and disputes put the invoice on a collection hold. Nothing is
    reopened or charged again until staff release the hold on debitorkort.
12. Webhook events are leased for five minutes, and an ambiguous Stripe
    request is replayed for at most 20 hours before it needs reconciliation.
13. Signed links do not expire. Changing `link_secret` invalidates every link
    at once (pay, card removal and subscription). A normal GET of the payment
    page makes no Stripe calls and no writes; only the signed return GET after
    Stripe's redirect records the outcome.

## Known risks

- **REST installation skew.** #785 currently calls `/finans`, which is copied
  separately from `/master`. Deploy this producer to the API install the panel
  actually uses, or point the panel at the updated `/master` API. Missing/null
  blocks mean unavailable information; the consumer must not infer a collection
  channel or no saved card from them. PDO read failures must be investigated,
  not treated as proof of settlement.
- **Unknown dispatch outcomes.** A later rejection or configuration repair
  cannot prove that an earlier charge failed. Sticky ambiguity requires the
  original Stripe outcome to be reconciled by stored identity/metadata; never
  reset its status/key merely to resume charging. The 20-hour cutoff still
  applies to unknown requests without a later definitive rejection.
- **Recovery run duration.** All aged candidates are checked per run in pages
  of 100. Large abandoned backlogs and slow/failed Stripe GETs can lengthen
  a run or trigger rate limiting; failed retrievals remain pending and retry
  on a later run, without starving later ids in the current run.
- **Recovery failure times.** Stripe can omit a failed charge/time. The first
  clock fallback then remains stable for unchanged polls, but is an observation
  estimate until signed facts arrive. API polls do not make delayed signed
  events stale; events older than an actual signed watermark still are stale.
- **Historical event facts.** Older rows can still have null
  `received_at`/`failed_at`; new accepted transitions populate them. REST does not backfill them or perform reconciliation
  writes; the worker owns persisted reconciliation. PostgreSQL payment storage
  must accommodate the `posted_manual` settlement value.

- **Changing collection channels needs bookkeeping.** Clearing `adresser.pbs`
  or canceling a subscription is not proof that earlier PBS deliveries or
  overlapping subscriptions/invoices have been reconciled. The bookkeeper
  must stop and reconcile the old collection channel before admitting the
  debtor to invoice payment/autopay. Existing subscriptions are not canceled
  by turning off signup or by these eligibility guards.
- **Schema readiness.** Follow **Deployment order**: deploy and verify the existing
  event-table migration before the autopay webhook. Missing event columns affect
  retained subscription events even with all invoice switches off. HTTP 503 and
  the hourly alert allow recovery but do not replace that deployment order.
  Verify provisioning in the pinned tenant before enabling collection or
  adding invoice events. DDL failures are logged and retried at a later login;
  incomplete schemas fail closed in the payment runtime. These tables have
  never shipped: CREATE definitions are authoritative, without ALTER migrations
  for old development table shapes. Recreate an obsolete disposable test schema
  before testing the new definitions.

- **Concurrent bank/PBS/manual bookkeeping.** Page/worker/card mutations share
  debtor serialization and confirmed intent cancellation, so cancellation
  uncertainty cannot start a second intent. A legacy bank or channel writer
  does not hold that authorization lock; a change after the final balance check
  or browser confirmation can still create an overpayment. Receipt identity uses
  the durable requested amount so real money is recorded; staff reconcile any
  overpayment/refund manually. Table locking may briefly block accounting writers
  and deadlocks become posting errors for retry. A legacy allocator that reads
  `MAX(udlign_id)` outside its write transaction can retain a stale candidate
  across the lock; verify those transaction boundaries before enabling posting.
  A shared allocator for every legacy writer remains a separate accounting change.
  Concurrent production acceptance remains required.
- **Manual reconciliation evidence.** The agreed default uses invoice settlement
  or a sufficient decrease in its open amount. An unrelated bank receipt on
  the same invoice can satisfy that heuristic; it is not a unique Stripe
  posting reference. The bookkeeper must confirm the receipt and avoid booking
  it twice. A smaller partial decrease remains unresolved.
- **Manual settlement blocks repayment.** After a received-but-unposted
  payment, the page shows "Betaling modtaget" until the bookkeeper posts it.
  That is intended. A receipt posted in the kassekladde without being
  allocated to the invoice still leaves it in that state.
- **Signed links never expire.** Regenerating `link_secret` invalidates every
  link: pay, card removal and subscribe.
- **3DS on off-session charges.** A charge that needs authentication
  (`authentication_required`) marks the card broken. The customer must pay
  the next invoice on the page to re-save it.
- **Refund/dispute accounting stays manual.** Holds prevent recollection but
  do not reverse ledger entries or subtract refunded money from the original
  receipt. Refund alerts show cumulative refunded money, not a new posting
  amount. Reconcile individual refund ids before booking, especially after
  unordered partial refunds. Only created/closed dispute events are subscribed
  by default; any other delivered `charge.dispute.*` correction uses the same
  audited manual hold. Fund movements still require manual accounting.
- **Lease and notification recovery.** A crashed handler can be reclaimed after
  five minutes. The subscription invoice lock serializes PDF/mail side effects,
  but production acceptance still needs concurrent deliveries. The outbox retains
  invoice receipt/hold mail failures. Subscription booking mail failure with a
  valid recipient keeps its event retryable; throttles represent only accepted
  deliveries. Missing recipients instead leave a terminal recorded event with
  `no_alert_recipient`; those payments require manual bookkeeping follow-up.
  Other subscription informational/park alerts remain best effort, while technical
  alerts accompany an already retrying request. Mail acceptance does not prove
  inbox delivery. A process crash after mail acceptance and
  before `sent_at` can deliver that mail twice; delivery is at least once. This
  never repeats the receipt or charge. Investigate pending jobs and audit holds; an
  HTTP 200 alone does not prove mail delivery. Equal-second non-success
  signed snapshots retain delivery order; only strictly older signed snapshots are discarded.

`invoice_pay_enabled` and `autopay_enabled` stop new collection; they do not
stop receipt/enrollment/settlement/notification maintenance. Subscription signup
`enabled` still affects only subscription signup. `stripe_fravalg` pauses invoice
collection while retaining saved consent; clearing it may resume an otherwise
valid card. Use **Fjern kort** to revoke consent and prevent reuse of that generation.

## Tests

2026-10-13 CodeRabbit regressions (C-1–C-4):

- C-1 — `InvoicePaymentPageTest::testDatabaseErrorsReturnGenericMessageBeforeRuntimeErrors`
  checks PDOException catch ordering, private logging and the generic HTTP 500 response.
- C-2 — `StripeWebhookTest::testSubscriptionWithoutAlertRecipientRecordsInsteadOfRetrying`
  covers empty bookkeeper email and absent/NULL/empty/invalid seller emails, terminal
  recording, diagnostics and duplicate delivery without transport attempts.
  `testSubscriptionBookingMailUsesSellerWhenBookkeeperEmailIsEmpty` retains the
  valid seller fallback. Existing
  `testFailedSubscriptionBookingMailRetriesWithoutConsumingItsPermanentThrottle`
  retains 503/retry behavior for failed and throwing mail transports with a recipient.
- C-3 — `InvoicePaymentTest::testPostingEnabledAcceptsTrimmedLowercaseDkkInvoiceCurrency`
  posts a live receipt for an invoice stored with `valuta=' dkk'` and verifies
  balanced postings and ledger settlement.
- C-4 — `InvoicePaymentReadModelTest::testOrdersWithoutInvoiceNumbersHaveNoLedgerFacts`
  covers empty, whitespace-only and NULL invoice numbers against unrelated
  open and settled blank-number items; numbered invoices in the batch retain their facts.

2026-10-13 review regressions (Q-01–Q-06):

- Q-01 — `InvoicePaymentTest.php`: 100 abandoned hosted intents followed by
  a remotely successful intent recover the receipt in one worker run with
  collection off, both when the first 100 GETs succeed and when they fail;
  no replacement POSTs occur.
- Q-02 — `InvoicePaymentLinkTest.php`: direct read-only Systemdata POSTs with
  valid CSRF change no setting/cache, even with debtor write permission;
  all mutation controls are disabled and saved values remain visible;
  authorized writes retain the existing supported/unsupported-driver checks.
- Q-03 — `InvoicePaymentTest.php`: a synchronous decline without method
  identity sends one alert; matching signed failure evidence or stronger
  issuer advice then breaks the current method/generation, without another
  decline alert or duplicate card audit on repeated evidence.
  `StripeWebhookTest.php` also sends actual signed webhook deliveries in a
  fresh process after an earlier API decline was already notified, checking
  the real signature, lease, card break and alert dedupe path.
- Q-04 — `InvoicePaymentTest.php`: an automatic live posting into an earlier
  year preserves/rebuilds successor D/S balances, Z ranges (including R,
  excluding H/Z) and R references exactly as the journal; non-S openings
  are reset and direct H/Z/X transactions do not supply balances.
- Q-05 — `InvoicePaymentOperationsTest.php`: all four explicit local refusal
  codes permit a fresh key after 20 hours only without earlier ambiguity;
  unknown status-0 codes remain ambiguous, and a lost success followed by
  a local refusal stays `needs_reconcile` after repair. `InvoicePaymentTest.php`
  also verifies collection resumes after a 21-hour configuration repair for
  `config_key`/`config_mode_mismatch`, preserving the rejected operation.
- Q-06 — `InvoicePaymentReadModelTest.php`: an earlier settled positive item
  cannot override a later outstanding item's due date; REST and the worker
  agree on that date and on the order fallback once both items are settled.

PHP lint and the full suite on SQLite and PostgreSQL remain for the separate
verification runner; the implementation sandbox does not execute PHP/tests.

2026-10-12 follow-up regressions (N-01/N-02):

- `InvoicePaymentOperationsTest.php`: timeout after Stripe success followed by
  definitive 400/401/402/403/404, reconstructed adapters, immediate and post-cutoff
  repair without another POST/key; interrupted dispatch remains ambiguous.
  Existing first-rejection cases still allow a fresh key after repair.
- `InvoicePaymentTest.php`: actual off-session success with lost response,
  then 401 or card 402, repaired configuration before/after cutoff, one durable
  payment/key/Stripe intent and delayed original success reconciliation;
  repeated hosted-decline recovery with expanded latest charge, error charge
  object/id or no-time fallback keeps `failed_at` and REST stable; direct API
  snapshots retrieve an unexpanded latest charge; delayed/sparse signed facts
  still apply and preserve charge identity; a new charge with the same code changes the failure
  time; processing/success API snapshots preserve the signed watermark.
- `InvoicePaymentSchemaTest.php`: failed-charge identity is nullable and
  dispatch ambiguity defaults to 0 in the shared SQLite/PostgreSQL CREATEs.

The implementation sandbox does not run PHP or tests; these regressions and
all existing tests require the separate runner on SQLite and PostgreSQL.

2026-10-12 review regressions (F1–F10):

- `StripeWebhookTest.php`: exactly the ten-column production event ledger with
  switches off, 503 without DDL/crash, hourly alert acceptance/failure and
  successful redelivery after the same `upgradeEvents` used by `betweenUpdates`;
  failed/throwing subscription booking mail followed by acceptance and forced
  replay; refunds and all dispute correction types before success after a lost creating
  response; missing durable binding remains retryable.
- `InvoicePaymentStaffActionsTest.php`: real saved-card/hold state stays intact
  and no service or Stripe call occurs for absent/invalid CSRF, or read-only
  direct POSTs with valid CSRF. Authorized POSTs release/remove correctly.
- `InvoicePaymentTest.php`: ordinary hosted success with no webhook or return
  is recovered with collection off; every relevant known nonterminal state;
  day-1 intent reused with day-12 consent excludes invoices dated days 1–11;
  regrant after revocation and card ordering use the actual grant/generation;
  real manual failure projected through REST with technical errors excluded;
  API return/prior-intent/worker/snapshot recovery across Copenhagen New Year
  posts on the December charge date, with intent-created fallback; 401 repair
  after 21 hours resumes collection with one payment and a fresh operation key;
  a fresh decline preserves the old rejection, and removal cancels a rejected attempt.
- `InvoicePaymentOperationsTest.php`: definitive errors with Stripe error
  objects create fresh keys after the cutoff while preserving rejected requests;
  transport/no error object, 409/429 and 5xx still become `needs_reconcile`.


The stripe suite uses isolated PDO fixtures and a fake Stripe transport;
there are no network calls and no mail. `InvoicePaymentTest.php` retains the
existing payment, posting, retry and card-removal scenarios. Real posting
scenarios use fake **live-mode** receipts; test-mode receipts exercise isolation.
The payment fixture rolls back unfinished transactions, drops its PostgreSQL
schema and releases its service, transport and store references after every
test, even while the test object is retained. Dropping a schema alone leaves
its PDO client connected; the bound clock and alert callbacks can keep it alive
and exhaust PostgreSQL's client limit during a full suite run. Cleanup also
accepts an uninitialized fixture without masking a setup failure.

Slice A adds:

- `InvoicePaymentEligibilityTest.php`: PBS truthy variants, subscriber states,
  missing subscription table, opt-out/default-off switches, all reasons and
  stable ordering, read-only batch parity, card state/consent/mode and large
  batches.
- `InvoicePaymentLinkTest.php`: real signed invoice links and HTML suppression,
  both mail tokens across all switch combinations (HTML and plain text),
  MySQL/missing-schema refusal, and forced-off settings saves on MySQL.
- `InvoicePaymentSchemaTest.php`: empty/idempotent provisioning, partial
  table/index repair with the audit table present, non-fatal DDL failure and
  retry, preserved event rows under repeated lease-column upgrades,
  missing-table/column detection, and unsupported-driver refusal.
- `InvoicePaymentTest.php`: per-reason Danish start refusal with no Stripe
  calls, channel changes during card saving, worker skips with persistent
  daily throttling, stale direct-charge requests, card/switch blocker wrappers,
  test-mode isolation with either posting switch value and retry/duplicates,
  direct ledger refusal, and preserved live/manual bookkeeping alerts.

Slice B adds:

- `StripeEventLedgerTest.php`: first delivery and event payload/time; every
  terminal status; fresh foreign lease, expiry/reclaim, repeated failures and
  immediate recovery, legacy status recovery, stale finish/fail ownership,
  operator requeue/force and unknown-event refusal.
- `StripeWebhookRoutingTest.php`: both invoice kinds, foreign/missing/malformed
  metadata, actual `saldi_db` key and an unresolved tenant; staff CSRF rejection
  before runtime construction.
- `StripeWebhookTest.php` with `support/WebhookFixture.php`: actual signed
  webhook dispatch in child processes with fake I/O adapters; unmapped
  subscription recovery/store-failure retry, one PDF pool entry/booking alert across forced replay,
  foreign-intent ignore without invoice schema/runtime/import prerequisites,
  repeated runtime failures, HTTP 503 busy leases, terminal mode mismatch,
  subscription-only import readiness, retained subscription refunds/checkout/
  decline/deletion, invoice receipt with import on and collection off, and
  refund/dispute charge resolution and holds. Fixtures replace external I/O
  only, use random per-test signature secrets, and send no network requests/mail.
  The child process preloads its request context with `auto_prepend_file`
  before the webhook's POST guard, matching the HTTP context provided by PHP
  in production. `testNonPostRequestIsRejectedBeforeEventClaim` checks GET/PUT
  rejection (HTTP 405) without event claims or alerts. PHP warnings remain
  visible and any child-process stderr fails the test.
- `InvoicePaymentTest.php`: event times/last failure/terminal success, stale
  canceled/failed snapshots and later progress, identity mismatches, stored
  amount after bank balance changes, delayed declines for matching/different
  methods/generations and synchronous failures with foreign/missing methods,
  pending-row ownership, reverse/equal-time saved-card
  ordering and removal/reconsent, audit/alert-deduped refund holds, page/worker
  exclusions, closed-dispute holds, debtor/mode-scoped audited staff release
  without a card, and unchanged foreign subscription charge behavior.

Slice C adds:

- `InvoicePaymentOperationsTest.php`: immutable payload/generation, tenant/mode
  keys, committed dispatch, lost-response replay, 20-hour expiry and refusal to
  dispatch an uncommitted preparation.
- `InvoicePaymentOutboxTest.php`: transactional enqueue/rollback, permanent
  dedupe, post-commit sending, and false/throw delivery failures followed by recovery.
- `InvoicePaymentPageTest.php` with `support/InvoicePaymentPageFixture.js`: the
  actual page controller under fake browser/Stripe I/O; changed amounts need a
  new click, processing blocks confirmation, success redirects, and unchanged
  amounts confirm. Uses Node.js when available; absence skips these PHP wrappers.
- `InvoicePaymentTest.php`: discovered success/processing commits, cancellation
  refusal and a confirmation race, final balance recheck, all six hard declines
  stopping a two-invoice loop, stale/revoked generations, customer/manual/automatic
  response-loss replay, expiry/revocation with collection off, receipt/enrichment
  separation and recovery, aged-intent success/cancellation/retrieval failures,
  settled/partial/unposted manual reconciliation and later credit guards,
  Copenhagen posting dates across year boundaries, due-date validation, integer
  rounding/mixed currencies/tiny balances, technical errors versus declines,
  issuer advice, failed receipt/refund mails, mid-posting rollback/retry and
  following-year openings. A
  PostgreSQL-only two-connection test commits webhook success before a late
  synchronous error and verifies that no failure/card side effect overwrites it.
  `testFixtureTearDownReleasesConnectionWhileTestCaseIsRetained`,
  `testFixtureTearDownRollsBackUnfinishedTransactionAndReleasesConnection` and
  `testFixtureTearDownAcceptsUninitializedFixture` cover prompt PDO release,
  rollback/schema removal and partial setup. Weak references verify release
  without forcing garbage collection or changing payment assertions.
- `InvoicePaymentSchemaTest.php`: newest outbox repair, durable-operation columns
  and `posted_manual` storage. The HTTP webhook receipt fixture also supplies
  `openpost` and checks the new receipt baseline; handler behavior is unchanged.

Slice D adds:

- `InvoicePaymentReadModelTest.php`: unavailable/partial schema, missing mode
  and default-off switches; every shared blocker; saved/broken/revoked cards;
  current-mode and order/debtor isolation; immutable latest receipt/decline
  ordering and id ties; historical null times and safe diagnostics; positive
  openpost settlement and partial balances; read-only manual reconciliation,
  pending/error totals, processing/holds; valid due-date parity with the service
  and invalid-date fallback; exact field sets/types and Copenhagen timestamps;
  constant query counts at 1/50/100 debtors/invoices with and without schema,
  and large batch/single parity.
- `InvoicePaymentReadContextTest.php`: unavailable unauthenticated context,
  one lazy connection and cached nulls, and reset after tenant authentication.
- `InvoicePaymentRestContractTest.php`: actual authenticated HTTP customer
  detail/list/search and invoice detail/list serialization; missing-schema
  nulls with no DDL, existing `paid` compatibility, exact nested keys/types,
  test/live isolation, broken/revoked cards, settled receipts and no business
  writes. It creates/drops a disposable REST tenant via `RestApiEnv`; requires
  a local REST stack and `SALDI_CHAR_PGPASS` (plus the REST host/user/master/
  template variables in `tests/restapi/README.md`). It skips without that stack.
  A skipped HTTP contract is **not** deployment verification.

The workflow forbids running PHP, PHPUnit or Docker in the implementation
sandbox. Lint and the full suite on **both SQLite and PostgreSQL** must be run
by the separate verification runner before deployment.

```sh
# SQLite (default)
php includes/vendor/phpunit.phar --testsuite stripe

# PostgreSQL: each test creates and drops its own schema. The ordrer,
# ordrelinjer, openpost, transaktioner, kontoplan and grupper tables are
# created from admin/opret.php's real DDL.
SALDI_STRIPE_TEST_DSN='pgsql:host=...;dbname=saldi_stripe_test' \
SALDI_STRIPE_TEST_USER=... SALDI_STRIPE_TEST_PASSWORD=... \
php includes/vendor/phpunit.phar --testsuite stripe
```

Manual acceptance should also cover a PBS debtor, a retained subscriber,
an ordinary debtor and an old signed link after a collection-channel change;
verify no blocked intent is created and both-token mails contain at most one
CTA. Test schema provisioning twice and from a partial schema, then verify a
DDL permission failure leaves login usable and collection unavailable.

Manual REST acceptance should query the authenticated customer detail and
list/search endpoints for an ordinary, PBS, subscribed, broken-card and
revoked-card debtor. Compare the blocks and verify no Stripe pin file is needed
on that API install. Query invoice detail/list for an open invoice, a partial
bank receipt and a manual card receipt; after the bookkeeper settles its
positive openpost, confirm `ledgerPaid=true` and `receivedUnpostedOre=0` while
`paid` remains the legacy field. Repeat with an old tenant and a second tenant
sharing the same debtor id to verify nulls and isolation.

These need a test-mode Stripe account, an isolated test tenant and a browser,
so they are not automated:

- The save-card toggle updates the intent (Stripe dashboard: the intent's
  `setup_future_usage`).
- 3DS test card `4000 0025 0000 3155` completes the challenge and returns to
  the page. Saved, it then charges off-session without a challenge.
- Saved card `4000 0027 6000 3184` (always requires authentication): the
  off-session charge is declined with `authentication_required` and marks
  the card broken.
- `kort.php` link removes the card. The Stripe customer shows no payment
  method.
- The debitorkort "Fjern kort" action does the same, and the audit row shows
  the staff user.

<!-- 20261012 CL/LH Document the independent REST autopay/payment producer, nulls, batching and verification. -->

<!-- 20261012 CL/LH Document review fixes F1-F10, migration-first deployment and regression coverage. -->
