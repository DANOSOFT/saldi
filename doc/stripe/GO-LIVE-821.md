<!-- // 20261022 CDX/LH Document full invoice-payment activation, production checks, subscriber cutover and rollback. -->
<!-- // 20261022 CDX/LH Add shared PDF settings SQL, live-customer and credit preflights, invoicing freeze, gate evidence and ambiguous-create recovery. -->

# Go-live #821 — saldi_1091, Tuesday 27 October 2026

Lui operates the rollout; Nicolai reviews the master port and handles any revert.
A merge to master automatically deploys `/var/www/html/master` on ssl3 within
minutes. Prepare the settings, private storage and disabled cron entries before
merge. Activation includes card saving **and** due-date collection; the temporary
switch-off during deployment is only the verification window.

The commands below are operator instructions, not evidence that production has
been changed. The canonical URL from the production context is
`https://ssl3.saldi.dk/master`; verify that host resolves to this installation
before saving it. Keep the existing four Stripe subscriptions running.

## Release evidence before merge

Attach `.codex-run/gate-final-tests.log` to the PR: the pre-review baseline is
**150 tests, 1308 assertions, 0 skipped**. Ordinary CI skips the Stripe database
suite because it lacks `SALDI_TEST_*`; a green CI result is insufficient evidence.
After M1–M6, attach the new external gate's PHP lint and PostgreSQL `stripe` suite
log as well, with every test executed, zero failures and **zero skipped tests**.
The suite now includes additional regressions, so its total must exceed 150.
Run with `--display-warnings --display-phpunit-deprecations`; keep `failOnWarning`
unchanged. Also attach the output of `node tests/stripe/payment-page.test.cjs`.

Use a disposable tenant/Stripe sandbox for the acceptance matrix in
[tests/stripe/README.md](../../tests/stripe/README.md). Retain screenshots,
PaymentIntent/event/order ids and read-only payment/journal extracts on the PR.
In particular, complete and abandon 3DS, replace a declining saved card with
consent selected, exercise off-session authentication on its original intent,
run concurrent collection/repair, send a real mixed batch, and exercise cold PDF
rendering and all existing pilot events. Unit tests do not establish real
Stripe.js, SMTP, converters, the full signed HTTP bootstrap or server deny rules.
Refresh/recheck text id allocation 7100–7299 against other pending PRs before merge.

## Settings in saldi_1091

Open `/master/systemdata/diverse.php?sektion=stripe_valg` while logged into
**saldi_1091**. Save the entire form (unchecked checkboxes are written off).
The names below are database keys, not alternate mode-suffixed names.

| Group / key | During merge verification | Activated value |
| --- | --- | --- |
| `stripe.enabled` (Aktiv) | `on` | `on` |
| `stripe.mode` | `live` | `live` |
| `stripe.invoice_payments` | `off` | `on` |
| `stripe.enrolment` | `off` | `on` |
| `stripe.signup_enabled` | `off` | **`off`** |
| `stripe.charge_on` | `due` | **`due`** |
| `stripe.auto_post_journal` | `off` | `off` (reserved; no automatic posting) |
| `stripe.secret_key` | Retain existing live account key | Same account/mode as publishable key; blank input retains it |
| `stripe.publishable_key` | Correct account's `pk_live_…` | Same verified value |
| `stripe.webhook_secret` | Retain signing secret for the existing live endpoint | Same; enter a replacement only if endpoint identity actually changes |
| `stripe.link_secret` | Retain existing generated value; do **not** select regenerate | Same, at least 32 characters (normally 64 hex) |
| `stripe.public_base_url` | `https://ssl3.saldi.dk/master` | Same, no trailing slash |
| `stripe.bookkeeper_email` | `faktura@danosoft.dk` | Same; verify a real alert reaches the monitored billing inbox |
| `stripe.vat_rate_id` | Retain existing pilot `txr_…` | Same; invoice PIs use Saldi's booked VAT, not subscription prices |
| `stripe.import_enabled` | `off` (pilot record-only mode) | `off`; this release does not enable subscription invoice import |
| `stripe.target_db` | `saldi_1091` (read-only mirror) | Same; resolver configuration below is authoritative |
| `email_settings.invoice_format` | `pdf` | `link` |
| `email_settings.invoice_link_text` | Empty | Empty uses the reviewed Danish standard mail |

`import_enabled` is not an editable stripeValg control; verify its existing
value separately. Configure the authoritative web resolver with
`SALDI_STRIPE_DB=saldi_1091` or the gitignored
`/var/www/html/master/includes/stripeIncludes/.ht_stripe_db` containing
`saldi_1091`. `--db=saldi_1091` on cron does not configure the web resolver.

Set Ordrerelaterede valg → **Kontonummer for salg på kreditkort** (DIV/3 box10)
to the **existing, accountant-approved Stripe clearing account**. Do not copy
a test account number. Confirm the account is open in the current fiscal chart.
This setting also changes existing Kontant/Kreditkort posting: inspect those
payment terms first. Stripe journal lines remain unposted until the accountant
posts them. Post/reconcile the journal before a reminder run; card-paid and
in-flight items are also protected by the new reminder predicate.

New defaults are all off on other installs. Do not activate these switches on
customer tenants or a copied production ledger.

## Storage, PHP, mail and cron

Confirm the Apache/PHP worker owner is `www-data` on ssl3; the exact cron entries
below use that same user. If the verified worker owner differs, substitute that
owner in **both** entries and storage ownership. Verify the CLI interpreter
`/usr/bin/php`, pgsql/cURL/OpenSSL, Composer PHPMailer and the configured
PostScript/HTML/PDF converters under that user. Test SMTP and operational
`mail()` alerts separately.

Use persistent private PDF storage outside the web root. Set the tenant's
`stripe.pdf_private_dir` to `/var/lib/saldi/stripe-pdf` through the approved
settings administration, or supply `SALDI_STRIPE_PDF_DIR` consistently to both
web and cron. This setting has no stripeValg field. Run this exact insert/update
SQL against **saldi_1091** to configure the shared directory:

```sql
BEGIN;
UPDATE settings SET var_value='/var/lib/saldi/stripe-pdf'
WHERE var_grp='stripe' AND var_name='pdf_private_dir';
INSERT INTO settings (var_grp,var_name,var_value,user_id,group_id)
SELECT 'stripe','pdf_private_dir','/var/lib/saldi/stripe-pdf',NULL,0
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE var_grp='stripe' AND var_name='pdf_private_dir');
COMMIT;
```

Verify `SALDI_STRIPE_PDF_DIR` does not override this with a different path in
either runtime. Without shared configuration, web and cron can fall back to
different `/tmp` directories under systemd `PrivateTmp`; a PDF cached by web
would then be invisible to cron. Create the private parent and log/lock
directories as administrator:

```sh
install -d -o www-data -g www-data -m 0750 /var/lib/saldi/stripe-pdf /var/log/saldi /var/lock/saldi
install -o www-data -g www-data -m 0640 /dev/null /var/log/saldi/stripe-charge-saldi_1091.log
install -o www-data -g www-data -m 0640 /dev/null /var/log/saldi/stripe-repair-saldi_1091.log
```

Use `install /dev/null` only when first creating a log; it truncates an existing
log. Configure log rotation with `create 0640 www-data www-data`. Web and CLI
must see the same private storage (including PrivateTmp/service isolation).
The application creates its own installation/tenant children, opaque HMAC names,
0750 directories and 0640 PDFs. Seal or purge legacy public
`temp/saldi_1091/stripe_pdf/` and `temp/saldi_1091/99999/` artifacts before
activation. Add server-level deny rules covering these paths and any
`temp/.stripe-private-*/` fallback, including aliases. Do not rely solely on
`.htaccess` without testing AllowOverride behavior.

Install these **system crontab** entries in `/etc/cron.d/saldi-stripe-charge`;
include the user column. Confirm the cron daemon/host schedules in
**Europe/Copenhagen** (`timedatectl`), including the 25 October DST change.
`TZ` in a command's environment alone does not establish the daemon's timezone.
If using a daemon supporting `CRON_TZ`, it may also be set explicitly to
`Europe/Copenhagen`; otherwise configure the host timezone before activation.

```cron
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
0 6 * * * www-data /bin/sh -c 'cd /var/www/html/master && /usr/bin/flock -n -E 75 /var/lock/saldi/stripe-charge-saldi_1091.lock /usr/bin/php -d date.timezone=Europe/Copenhagen tools/stripe_charge_due.php --db=saldi_1091' >> /var/log/saldi/stripe-charge-saldi_1091.log 2>&1
30 6 * * * www-data /bin/sh -c 'cd /var/www/html/master && /usr/bin/flock -n -E 75 /var/lock/saldi/stripe-charge-saldi_1091.lock /usr/bin/php -d date.timezone=Europe/Copenhagen tools/stripe_charge_due.php --db=saldi_1091 --repair-only' >> /var/log/saldi/stripe-repair-saldi_1091.log 2>&1
```

Initially keep both schedule lines commented. After the first controlled payment,
enable both: full collection at **06:00**, repair-only at **06:30**, Copenhagen.
The same external lock covers manual, normal and repair-only runs. The tool also
holds an internal installation/ledger lock for its entire lifetime. Do not delete
lock files while a process may hold them. An external overlap returns 75; treat
it as an overlap to investigate, not a payment failure. Internal overlap is
reported as `skipped=overlap` and exits 0. Monitor missing summaries as well as
nonzero exits: plain cron logging alone is not an alerting service.

Manual repair command under the same user, cwd and external lock:

```sh
sudo -u www-data /bin/sh -c 'cd /var/www/html/master && /usr/bin/flock -n -E 75 /var/lock/saldi/stripe-charge-saldi_1091.lock /usr/bin/php -d date.timezone=Europe/Copenhagen tools/stripe_charge_due.php --db=saldi_1091 --repair-only' >> /var/log/saldi/stripe-repair-saldi_1091.log 2>&1
```

`--repair-only` never creates/confirms a PaymentIntent. It can write journal
entries, update Stripe customer/card defaults for captured consent, send receipts
and retry Afmeld invoice mail. It runs with rollout switches off; enrolment
side effects themselves still require those switches. `--dry-run` suppresses
repair/mail/collection work but **may provision schema**. Use SQL for strictly
read-only preflight. Never use `--today` to simulate future collection in live mode.

## Stripe dashboard before activation

In the correct **live** Stripe account, open Workbench → Webhooks, select the
existing destination `https://ssl3.saldi.dk/master/api/stripe/webhook.php`, and
edit its event selection. Retain a snapshot of its original event set and
endpoint id. Use **snapshot** payloads from **your account**, with the code's
pinned API version `2026-06-24.dahlia`; do not switch this handler to thin events
or silently upgrade its version. The handler expects `data.object`.
[Stripe's webhook setup](https://docs.stripe.com/webhooks) describes these fields.

Enable the union below (charge.refunded is already in the pilot set):

```text
payment_intent.succeeded
payment_intent.payment_failed
payment_intent.canceled
payment_intent.requires_action
charge.refunded
charge.dispute.created
checkout.session.completed
invoice.paid
invoice.payment_failed
customer.subscription.deleted
```

Keep any additional existing event selections until their consumers are reviewed.
Confirm the signing secret still matches the saved value; do not expose it in
screenshots or SQL output. Open Event deliveries to inspect response codes for
an actual Saldi sandbox event first, and later the approved live payment. A generic
Dashboard test event with no Saldi metadata does not prove invoice mapping.
[Stripe's delivery view](https://docs.stripe.com/workbench/event-destinations)
also provides individual event retries.

The integration enforces cards in every invoice PaymentIntent and in Elements;
no account-wide payment-method setting is needed. Apple Pay and Google Pay
produce card PaymentMethods. Register the actual hostname and test each wallet
before enabling it. Keep the pilot Checkout account configuration intact.

With `signup_enabled=off`, remove `$abonnementslink` from saldi_1091's classic
mail templates. Deactivate the legacy `stripe_catalog` entries using the approved
catalogue administration procedure (retain a list of original active rows).
Expire each **open, subscription-mode Checkout Session belonging to this Saldi
integration**. The switch cannot revoke a session already created at Stripe.
Use the session's expiry operation, or `POST /v1/checkout/sessions/<id>/expire`
through the existing approved Stripe operator tooling. Check `mode=subscription`,
status `open`, customer/order metadata before expiry; do not cancel the four
active subscriptions. [Stripe's expiry API](https://docs.stripe.com/api/checkout/sessions/expire)
permits expiry of open sessions.

## Read-only checks immediately after /master deploys

Verify deployed SHA/opcache matches the reviewed port, while collection schedules
remain commented and invoice/enrolment switches are off. Log into saldi_1091
once to run `betweenUpdates.php`, then run the following against **saldi_1091**
using the normal approved PostgreSQL connection. Do not put credentials on the
command line or export raw event payloads/attempt request bodies into the PR.

First block is safe even if migration has not run:

```sql
BEGIN READ ONLY;
SELECT current_database(); -- must be saldi_1091
SELECT var_grp, var_name,
       CASE WHEN var_name IN ('secret_key','webhook_secret','link_secret')
            THEN CASE WHEN coalesce(var_value,'')='' THEN 'MISSING' ELSE 'configured' END
            ELSE var_value END AS configured_value,
       user_id, group_id
FROM settings
WHERE var_grp='stripe'
   OR (var_grp='email_settings' AND var_name IN ('invoice_format','invoice_link_text','sender_email','sender_name'))
ORDER BY var_grp, var_name, user_id NULLS FIRST, group_id;
SELECT table_name, column_name, data_type
FROM information_schema.columns
WHERE table_schema=current_schema()
  AND (table_name IN ('stripe_payments','stripe_collection_attempts','stripe_charge_errors','stripe_unenrol_mail')
       OR (table_name='stripe_events' AND column_name IN ('claim_token','claimed_at','attempts','event_created'))
       OR (table_name='adresser' AND column_name IN ('stripe','stripe_customer_id','stripe_pm_id','stripe_date','stripe_fravalg')))
ORDER BY table_name, ordinal_position;
SELECT tablename, indexname, indexdef FROM pg_indexes
WHERE schemaname=current_schema()
  AND tablename IN ('stripe_payments','stripe_events','stripe_collection_attempts')
ORDER BY tablename,indexname;
SELECT id,kodenr,box1,box2,box3,box4 FROM grupper WHERE art='RA' ORDER BY kodenr;
SELECT id,fiscal_year,box10 FROM grupper WHERE art='DIV' AND kodenr=3 ORDER BY fiscal_year DESC;
SELECT k.kontonr,k.beskrivelse,k.kontotype,k.lukket,k.regnskabsaar
FROM kontoplan k JOIN grupper g ON trim(g.box10)=k.kontonr::text
WHERE g.art='DIV' AND g.kodenr=3;
SELECT id,kladdenote,bogfort,kladdedate,hvem,tidspkt
FROM kladdeliste WHERE kladdenote='Stripe-betalinger' ORDER BY id DESC;
SELECT box1 FROM grupper WHERE art='GF'; -- retained-history configuration
SELECT betalingsbet,count(*) FROM ordrer WHERE art='DO' AND nextfakt IS NOT NULL GROUP BY betalingsbet;
SELECT valuta,count(*) FROM ordrer WHERE art='DO' AND fakturadate>=DATE '2025-10-27' GROUP BY valuta;
SELECT count(*) AS project_invoices FROM ordrer
WHERE art='DO' AND coalesce(projekt,'')<>'' AND fakturadate>=DATE '2025-10-27';
SELECT a.id AS debtor_id,a.kontonr,o.id,o.ordrenr,o.nextfakt,o.udskriv_til
FROM adresser a JOIN ordrer o ON o.konto_id=a.id
WHERE coalesce(nullif(trim(a.pbs_nr),''),'0')<>'0' AND o.art='DO' AND o.nextfakt IS NOT NULL;
SELECT stripe_customer_id,stripe_subscription_id,konto_id,order_id,status
FROM stripe_customers WHERE coalesce(stripe_subscription_id,'')<>'' ORDER BY konto_id;
SELECT id,konto_id,ordrenr,fakturanr,nextfakt,udskriv_til,betalt
FROM ordrer WHERE id IN (17093,17282,17158,17241) ORDER BY id;
COMMIT;
```

Require all four invoice tables, five debtor columns, the existing lease columns,
and the three named payment indexes: `stripe_payments_pi_uidx`,
`stripe_payments_succeeded_uidx` (unique partial predicate `status='succeeded'`),
`stripe_payments_ordre_idx`. Resolve any missing objects before the next block.
Missing switches read as off, but save explicit off values for the deployment window.

Before creating any live intent, verify **every distinct customer id** in both
`adresser.stripe_customer_id` and `stripe_customers.stripe_customer_id`, including
the pilot mappings, by `GET /v1/customers/<id>` using the configured **live key**.
A leftover test-mode `cus_` id causes a 400 on every create; the prefix alone
does not distinguish modes. Use the approved PostgreSQL connection and load
`STRIPE_API_KEY` from the approved secret store without echoing it or enabling
shell tracing. This Bash preflight performs the complete union of GETs; it
requires `psql`, `curl` and `jq` and prints only verified customer ids:

```bash
(
set -euo pipefail
: "${STRIPE_API_KEY:?Load the configured live Stripe key securely first}"
customer_ids=$(psql --no-psqlrc --dbname=saldi_1091 -v ON_ERROR_STOP=1 -Atc "
  SELECT DISTINCT trim(stripe_customer_id) FROM (
    SELECT stripe_customer_id FROM adresser
    UNION ALL SELECT stripe_customer_id FROM stripe_customers
  ) customers WHERE coalesce(trim(stripe_customer_id),'')<>'' ORDER BY 1")
while IFS= read -r customer_id; do
  [ -n "$customer_id" ] || continue
  [[ "$customer_id" =~ ^cus_[A-Za-z0-9]+$ ]] || exit 1
  customer_json=$(curl --silent --show-error --fail-with-body --config - \
    "https://api.stripe.com/v1/customers/$customer_id" <<EOF
header = "Authorization: Bearer ${STRIPE_API_KEY}"
header = "Stripe-Version: 2026-06-24.dahlia"
EOF
  )
  jq -e --arg id "$customer_id" '.id == $id and .livemode == true and (.deleted != true)' \
    <<< "$customer_json" >/dev/null
  printf '%s OK\n' "$customer_id"
done <<< "$customer_ids"
)
```

Stop activation on any failure or deleted customer. Reconcile the affected
debtor's mapping in the correct live account before proceeding; do not blindly
replace or clear pilot customer/subscription references. Retain the success
list with the rollout evidence, without raw customer responses or credentials.

Then inspect existing payment state and **all** potential automatic invoices:

```sql
BEGIN READ ONLY;
SELECT status,count(*) FROM stripe_payments GROUP BY status;
SELECT id,ordre_id,fakturanr,payment_intent_id,status,journal_line_id,confirmation_sent_at
FROM stripe_payments
WHERE status IN ('duplicate_paid','refunded','disputed','unmapped')
   OR (status='succeeded' AND (journal_line_id IS NULL OR confirmation_sent_at IS NULL))
ORDER BY id;
SELECT ordre_id,identity_no,primary_no,source,status,payment_intent_id,charge_date,request_started_at
FROM stripe_collection_attempts WHERE status<>'recorded' ORDER BY id;
SELECT ordre_id,first_date,last_error FROM stripe_charge_errors ORDER BY first_date,ordre_id;
SELECT ordre_id,sent_at FROM stripe_unenrol_mail WHERE sent_at IS NULL ORDER BY ordre_id;
-- Open credits/payments on debtors enrolled or targeted for link mail.
SELECT a.id AS debtor_id,a.kontonr,p.id AS openpost_id,p.faktnr,p.amount,p.transdate
FROM adresser a JOIN openpost p ON p.konto_id=a.id
WHERE a.art='D' AND coalesce(p.udlignet,'0')<>'1' AND p.amount<0
  AND (a.stripe='on' OR a.udskriv_til='email' OR EXISTS (
      SELECT 1 FROM ordrer o WHERE o.konto_id=a.id AND o.art='DO'
      AND o.udskriv_til IN ('email','Stripe')))
ORDER BY a.id,p.id;
-- Credit-note links also block collection even if their negative post was settled.
SELECT o.konto_id,o.id AS invoice_id,o.fakturanr,c.id AS credit_order_id,c.fakturanr AS credit_number
FROM ordrer o JOIN ordrer c ON c.kred_ord_id=o.id AND c.art='DK'
WHERE o.art='DO' AND coalesce(o.betalt,'')<>'on' AND o.udskriv_til IN ('email','Stripe')
ORDER BY o.konto_id,o.id,c.id;
SELECT id,event_id,event_type,status,received_at,processed_at
FROM stripe_events
WHERE received_at>=CURRENT_TIMESTAMP-INTERVAL '3 days'
  AND status IN ('received','retrying','failed','unmapped') ORDER BY id;
SELECT o.id,o.fakturanr,o.konto_id,o.fakturadate,o.betalingsbet,o.betalingsdage,
       o.sum,o.moms,o.valuta,o.pbs,o.nextfakt,a.stripe_date,a.stripe_fravalg,a.pbs_nr,
       coalesce(a.stripe_pm_id,'')<>'' AS has_saved_card,
       EXISTS (SELECT 1 FROM stripe_customers c WHERE c.konto_id=a.id
               AND coalesce(c.stripe_subscription_id,'')<>''
               AND c.status IN ('active','trialing','past_due')) AS excluded_subscriber,
       op.n AS open_item_count,op.rest AS open_rest,op.due_dates
FROM ordrer o JOIN adresser a ON a.id=o.konto_id
LEFT JOIN LATERAL (
    SELECT count(*) AS n,
           sum(CASE WHEN coalesce(udlignet,'0')<>'1' THEN amount ELSE 0 END) AS rest,
           array_agg(DISTINCT forfaldsdate) AS due_dates
    FROM openpost WHERE konto_id=o.konto_id AND faktnr=o.fakturanr
) op ON true
WHERE o.art='DO' AND o.udskriv_til='Stripe' AND coalesce(o.betalt,'')<>'on'
ORDER BY o.fakturadate,o.id;
COMMIT;
```

Review the backlog invoice by invoice; the job is not restricted to invoices
created after deployment. Require intended current invoices, enrolment no later
than invoice date, DKK, full authoritative open balance, and no subscription/PBS/
opt-out. Stop on unexpected history. Inspect multi-recipient invoice fields and
settle/reconcile every listed credit or unmatched account payment with the
accountant. These invoices show `manual` and cannot be collected by page,
Betal nu or cron while the credit guard applies. Inspect
sender/archive configuration. Run an actual warm/cold signed PDF download and
an unauthenticated GET of a known existing old cache/work file: require 403/404
for direct artifacts and the correct invoice from the signed endpoint.

The inherited webhook runtime still reclaims only stale `received` rows using
its error-column ownership token. The #795 lease columns are preserved but are
not used by that inherited runtime. Caught invoice DB/mail failures reset a
`retrying` invoice event to `received` and return 500. A hard crash during
`retrying`, or a legacy subscription event marked `failed`, still needs explicit
operator reconciliation. Do not infer successful bookkeeping from HTTP 200
`duplicate` alone; inspect event and payment/journal rows. Do not mass-reset events.

## First controlled live payment and first due-date collection

Freeze all other manual, recurring and batch invoicing in saldi_1091 from step 3
until the controlled payment has reconciled and normal delivery is released in
step 8. The `invoice_format=link` setting is tenant-wide: any ordinary eligible
email invoice issued during this window is sent as a link mail. If a freeze is
impossible, Lui must explicitly accept that delivery for every invoice issued
in the window and record the affected invoices before activation.

1. Complete the checks above, snapshot settings/candidate rows and ensure alerts,
   SMTP and private PDF downloads work. Keep both cron schedules commented.
2. Choose one legitimate, approved small **DKK Saldi invoice** for a consenting
   customer. Use an ordinary non-PBS, non-subscriber debtor, full unsettled
   openpost and approved clearing account. For saved-card acceptance choose a
   recurring invoice with a future due date. Use the customer's real card in
   live mode; test card numbers belong only in the sandbox.
3. Save `invoice_payments=on`, `enrolment=on`, `signup_enabled=off`,
   `invoice_format=link`, `charge_on=due`. These are the final release values.
   Send that invoice only. Inspect greeting, amount, booked due date, company
   identity and signed page/PDF; the link mail has no PDF attachment.
4. Customer pays with explicit save-card consent. Verify one live PaymentIntent
   in Stripe with the exact received amount/currency and tenant/order metadata;
   one succeeded payment row, one “Stripe-betalinger” line and one receipt;
   `ordrer.betalt=on`; saved PM/customer/date and the current recurring template
   delivery changed to Stripe. Replay/reload must not add a line or receipt.
   Save identifiers and redacted evidence, never card/client secrets.
5. Post that journal through the usual accountant workflow and verify the correct
   debtor/invoice/project open item is settled. Reconcile the Stripe clearing
   movement and fee separately. Do not roll back a captured payment by deleting rows.
6. Issue the next approved recurring invoice with `udskriv_til=Stripe`. The
   customer must receive the pre-charge notice with amount/card/collection date
   and working signed PDF. Nothing should charge before due. Verify no saved-card
   “Betal nu” before due and no early automatic collection in the sandbox evidence.
7. Enable both cron schedules. At the first due date, observe the 06:00 summary,
   Stripe capture, one journal line/receipt and 06:30 repair summary. If an approved
   due invoice must be collected after today's 06:00 run, execute the full command
   under the same lock/user/cwd by removing `--repair-only` from the manual command;
   review **every due candidate first** because there is no single-invoice CLI flag.
8. Expand normal link delivery only after the controlled payment reconciles.
   Monitor summaries/errors, duplicate/unmapped captures, pending authentication,
   repair failures and the billing mailbox daily during the initial rollout.
   Authentication means one approval mail and no repeated auto/backup charge;
   primary bank declines alone use day 0/+3/+7, at most one backup per invoice.

For the controlled invoice, use its **numeric order id** in the following read-only
queries (replace `123456789` with the observed id):

```sql
BEGIN READ ONLY;
SELECT id,ordre_id,payment_intent_id,amount_ore,currency,status,source,save_card,
       journal_line_id,confirmation_sent_at,last_event_id
FROM stripe_payments WHERE ordre_id=123456789 ORDER BY id;
SELECT k.id,k.bilag,k.transdate,k.debet,k.kredit,k.amount,k.faktura,k.ordre_id,k.projekt,k.afd,l.bogfort
FROM kassekladde k JOIN kladdeliste l ON l.id=k.kladde_id WHERE k.ordre_id=123456789;
SELECT o.id,o.betalt,o.udskriv_til,o.nextfakt,a.stripe,a.stripe_date,
       coalesce(a.stripe_pm_id,'')<>'' AS has_saved_card
FROM ordrer o JOIN adresser a ON a.id=o.konto_id WHERE o.id=123456789;
SELECT p.id,p.faktnr,p.amount,p.udlignet,p.projekt,p.forfaldsdate
FROM openpost p JOIN ordrer o ON o.konto_id=p.konto_id AND o.fakturanr=p.faktnr
WHERE o.id=123456789;
COMMIT;
```

## Clear an ambiguous create only after operator reconciliation

`ambiguous_create` means a request was sent, its response was lost and no intent
was found before the safe idempotency retry window ended. A different caller
(changed card, source or save consent) is blocked immediately with `in_progress`
or `reconcile_failed` when lookup cannot establish an intent. Never unblock this
by editing the stored body, reusing its key for a different body or deleting it.

1. Stop the collection cron and pause payments for the affected invoice while
   reconciling. In the correct live Dashboard, search the customer, request logs
   and PaymentIntents for the attempt's `saldi_db`, `saldi_ordre_id` and
   `saldi_attempt_identity` metadata. Check every page/date of results. Review
   `stripe_payments`, webhook events and the journal too.
2. If an intent exists, reconcile its actual status first. Captured money must
   be booked/repaired, never retried as a new charge. Cancel any unconfirmed
   intent that is being abandoned and verify Stripe reports `canceled`. Leave
   processing blocked until resolved; resolve or cancel pending authentication
   explicitly. A retry through the invoice page looks up the original attempt:
   it records/reconciles a found intent, canceling it first when unconfirmed and
   the caller's source/card/consent has changed. Use that normal recovery path
   rather than clearing a reservation with a known intent. Verify both the local
   payment and attempt rows and the journal before reopening payment.
3. Only after confirming **no intent/capture exists**, stop concurrent page/job
   activity and mark the single checked reservation `abandoned`. Replace the
   three sample numbers below with the inspected request id, order id and
   identity. Acquire the same PostgreSQL invoice lock used by collection and
   recheck the row within the transaction. Require exactly one updated row:

   ```sql
   BEGIN;
   SELECT pg_advisory_xact_lock(82102,123456789);
   SELECT id,ordre_id,identity_no,source,status,request_started_at,payment_intent_id
   FROM stripe_collection_attempts
   WHERE id=987654321 AND ordre_id=123456789 AND identity_no=7 FOR UPDATE;
   UPDATE stripe_collection_attempts SET status='abandoned'
   WHERE id=987654321 AND ordre_id=123456789 AND identity_no=7
     AND status IN ('requesting','technical','retryable')
     AND coalesce(payment_intent_id,'')='';
   COMMIT;
   ```

4. Record the operator, Dashboard checks and request/order/identity ids in the
   incident log. Restore normal collection only after confirming eligibility
   again. The next caller allocates a new identity and idempotency key. Keep the
   old reservation as audit evidence; if certainty is impossible, leave it blocked.

## End the four pilot subscribers' Saldi recurrence before 15 November

Do this before the November batch is prepared, preferably during this rollout;
**deadline 14 November 2026**, before any run producing the 15 November invoices.
The new exclusion guard prevents invoice-PaymentIntent charging but does not
stop Saldi from issuing a second subscription invoice.

1. Map orders **17093, 17282, 17158, 17241** to debtor/ordrenr and the four
   active Stripe subscriptions using the first SQL block. Confirm the subscription
   covers the next period and check for newer current source rows in each series:

   ```sql
   BEGIN READ ONLY;
   SELECT o.id,o.konto_id,o.ordrenr,o.fakturanr,o.fakturadate,o.nextfakt,o.status,o.udskriv_til
   FROM ordrer o
   WHERE o.art='DO' AND EXISTS (
       SELECT 1 FROM ordrer seed WHERE seed.id IN (17093,17282,17158,17241)
       AND seed.konto_id=o.konto_id AND seed.ordrenr=o.ordrenr)
   ORDER BY o.konto_id,o.ordrenr,o.id;
   COMMIT;
   ```

2. In each current source order's **Genfakturer** field, enter **`-`**, then
   **Gem/opdatér**. `debitor/ordre.php` explicitly interprets `-` as
   `nextfakt=NULL`; an empty field alone is not the stop action. Stop all retained
   rows in those same series that can still be selected for a future batch.
   Reopen each order and rerun the read-only series query: no source in these
   four series may retain a future recurrence date. Preserve invoices, lines,
   previous payments and subscription/customer mappings.
3. Inspect the November Genfakturer selection before releasing it; none of those
   four series may be included. Review any already-generated Saldi invoices for
   overlapping periods with the accountant and credit/cancel by the normal
   accounting procedure. Do not delete issued/posted invoices or change paid flags.
4. Record who verified each of the four series and subscription periods. Keep
   Stripe subscriptions active and continue their record-only `invoice.paid`
   reconciliation. Do not enrol these debtors in the new invoice-payment model.

## Rollback

1. Comment out the **06:00 full collection** cron immediately. Set
   `stripe.invoice_payments=off`, `stripe.enrolment=off`, `signup_enabled=off`,
   `email_settings.invoice_format=pdf`. Keep `stripe.enabled=on`, live mode,
   keys, resolver and pilot settings intact. The new switches stop collection;
   valid incoming captures must still reconcile. Pausing cron does not cancel
   an in-flight PaymentIntent. Identify those intents before further action.
2. Run/retain `--repair-only` while the fixed code is deployed, monitor its
   results, and reconcile captured money. For affected enrolled debtors, use
   authenticated Afmeld to reset unpaid deliveries/queue invoice mail if needed;
   verify PDF fallback while the switches are off. Disabling a switch alone
   does not retroactively deliver already-issued invoices.
3. If code must be withdrawn, Nicolai reverts the reviewed port via the normal
   master process; that merge auto-deploys. Disable **both** cron entries before
   reverting: the old tool may not understand `--repair-only` and must never be
   invoked with that option accidentally performing collection. Keep an operator
   reconciliation list and a fixed-code repair environment for outstanding captures.
4. In Workbench → Webhooks, edit the existing destination's event selection back
   to its original pilot set when withdrawing invoice handlers: remove the four
   new PaymentIntent selections and `charge.dispute.created` only if it was newly
   added. **Keep `charge.refunded` and all five pilot event types.** Reconcile
   in-flight invoice events before this toggle or record their ids for recovery.
   Temporarily disable the entire destination only for an incident requiring that
   action, because that also stops the four subscribers' events. Stripe does not
   automatically resend events generated while a destination is disabled; inventory
   those events and reconcile/replay them explicitly after recovery.
   [Stripe documents this toggle behavior](https://docs.stripe.com/workbench/event-destinations).
5. Preserve all Stripe tables, debtor references, event payloads and journal rows.
   A revert does not undo a capture, enrolment, refund or posted movement. Handle
   duplicate captures/refunds/disputes manually using the PI/event/order ids and
   normal credit-note/correction procedures; do not truncate tables or restore an
   old whole-tenant backup over later legitimate accounting. Retain the subscriber
   recurrence stops unless the subscription itself is deliberately ended and
   ordinary Saldi billing is explicitly reinstated.
