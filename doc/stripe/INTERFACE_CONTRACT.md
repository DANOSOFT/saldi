# Stripe integration — canonical interface contract

**Frozen 2026-08-07.** Every component on `feature/stripe-subscriptions` references this
document; any deviation from it is a defect. Amendments require editing THIS file in the
same commit as the deviating code.

Full grounded plan (external): `Saldi payment/stripe-native-grounded-implementation-plan-2026-08-07.md`.

---

## 1. Settings — `settings` table, `var_grp='stripe'`, exactly NINE rows

| var_name | Written by | Read by | Notes |
|---|---|---|---|
| `enabled` | stripe_valg page (checkbox) | subscribe, link printer, mailer | `on` \| `off` (default off). Master switch: off = no NEW signups (subscribe renders a friendly not-active page, no links are minted). The webhook is deliberately NOT gated — events for already-running subscriptions must never be dropped. |
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
Indexes in `betweenUpdates.php` only (partial indexes are PostgreSQL-only; MySQL branch
explicitly skipped).

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
`stripe_events.payload` stores the raw verified body (replay/recovery path).
`stripe_customers.status` lifecycle: `'active'` on `checkout.session.completed`,
`'canceled'` on `customer.subscription.deleted`. A status-column update is NOT nextfakt
automation — the alert-only rule applies to `ordrer.nextfakt`, which **no stripe code may
ever read or write** (`grep -rn nextfakt api/stripe/ includes/stripeIncludes/` must return
zero matches, enforced by test).

## 4. Link signature

Payload = **`v1:<order_id>`**, nothing else. Line-derived signatures are forbidden (they
would break every outstanding link on any order edit; the mandatory re-match on GET/POST
already provides the protection — an edited order's old link simply renders the CURRENT
lines, which is correct).

Functions `stripe_link_sign($order_id)` / `stripe_link_verify($order_id, $sig)` /
`stripe_link_url($order_id)` live in **`includes/stripeIncludes/stripeLink.php`** — one
file, one owner (the subscribe component), consumed verbatim by the mailer component.
HMAC-SHA256 with `link_secret`; compare via `hash_equals()`.

## 5. Checkout <-> webhook metadata

The subscribe endpoint sends `client_reference_id=<order_id>` **plus both** metadata blocks:
- `metadata[saldi_order_id|saldi_konto_id]` (on the session — debugging), and
- `subscription_data[metadata][saldi_order_id|saldi_konto_id|saldi_kontonr]` — Stripe
  copies subscription metadata onto every invoice it generates; this is what makes
  RENEWAL invoices resolvable. Non-optional.

The webhook resolves identity in this order: (1) the invoice's subscription metadata
(`invoice.subscription_details.metadata`, else `GET /v1/subscriptions/<id>`),
(2) the `stripe_customers` row as cache. `stripe_customers` is upserted from **both**
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
