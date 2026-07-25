# Findings from the end-to-end testing push

Defects and risks surfaced while building the regression suite (July 2026).
Every entry has a reproduction that runs against the repo's own
docker-compose stack. Entries marked **pinned** have a test in
`tests/characterization/` that asserts the current (wrong) behavior, so the
test fails the moment the behavior changes.

Severity is about money and data, not about how hard it is to hit:

- **P1** — the general ledger or a customer's money can end up wrong.
- **P2** — data loss, corruption, or a failure the user is not told about.
- **P3** — dead code, wrong-but-harmless behavior, maintenance hazards.

---

## P1-1 — A voucher that balances on entry can post an unbalanced ledger entry

**Where:** `finans/bogfor.php:751`, `:757` (balance accumulation), `:962`
(difference handling), against the 2-decimal rounding applied when each leg is
written.

**Pinned by:** `tests/characterization/finans/DoubleEntryIntegrityCharacterizationTest.test.php`

`kassekladde.amount` is `numeric(15,3)`. The balance guard sums the amounts as
entered, at full precision. Each ledger leg is then rounded to two decimals on
its way into `transaktioner`. When a voucher's lines cancel out at three
decimals but round differently at two, `$b_diff` is zero, the whole
difference-handling block at `bogfor.php:962` is skipped, and the ledger takes
an entry whose debits and credits do not match.

Observed, posting through the engine's own dispatch:

| voucher lines | ledger debit | ledger credit | out by |
|---|---|---|---|
| debit 0.005, debit 0.005, credit 0.010 | 0.02 | 0.01 | 0.01 |
| debit 3.333, debit 3.333, debit 3.334, credit 10.000 | 9.99 | 10.00 | -0.01 |
| 5 × debit 100.005, credit 500.025 | 500.05 | 500.03 | 0.02 |

The discrepancy is bounded by the line count, not by half an øre: a 100-line
voucher can be 50 øre out.

Nothing tells the user. `transtjek()` (`includes/std_func.php:703`) does
compare total debit against total credit, but only fires above `0.1` kr and
only emails `fejl@saldi.dk` — the person who posted the voucher sees a normal
success message either way.

**Reachability:** anywhere sub-øre amounts enter a journal. Currency
conversion (`valutaopslag`, used at `bogfor.php:723`) produces them by
construction, and bank/CSV import writes `amount` straight into the
3-decimal column. Whether the kassekladde UI itself accepts a third decimal is
covered by the e2e suite.

**Suggested fix:** round each leg to 2 decimals *before* the balance guard
sums it, so the guard sees the same numbers the ledger will.

---

## P1-2 — Every posting recomputes the entire chart of accounts three times

**Where:** `finans/bogfor.php:74`, `:224`, `:561` — each calls
`genberegn($regnaar)` (`includes/genberegn.php:35`).

`genberegn()` zeroes every account balance, then walks every `D` and `S`
account in the chart and issues one `SELECT debet, kredit FROM transaktioner
WHERE ... AND kontonr = ...` per account, summing the rows in PHP, followed by
one `UPDATE kontoplan` per account. It then re-reads the whole chart to roll up
the heading and total accounts.

The call at `bogfor.php:74` is at the top of the page, before any dispatch: it
runs on *every* load of the posting page, including the one that only renders
the form.

Measured on the docker stack, posting one two-line voucher into a tenant with
**8 transactions total**:

```
bogfor child process: 13.5 s
postgres statements:  2389 (915 of them the per-account transaction scan)
```

305 postable accounts × 3 `genberegn()` calls = the 915 scans. The tenant is
empty; on a real book each of those scans returns every transaction ever posted
to that account and sums it in PHP. The cost grows with the size of the
ledger — every posting, forever.

**Suggested fix:** the two things `genberegn()` is needed for after a posting
(account balances, the `RA` transaction counter) can be derived with a single
grouped query; the call at `:74` looks like it can be dropped entirely.

---

## P2-1 — `kladdeliste.id` is allocated with `MAX(id)+1` by three writers

**Where:** `finans/kassekladde.php:1045`, `admin/bankfordeling.php:516`,
`bank_integration/aiia_import.php:69`.

All three read `SELECT MAX(id) FROM kladdeliste`, add one, and `INSERT` the id
explicitly. There is no lock and no `RETURNING`. Two users creating a journal
at the same moment get the same id: one insert fails on `kladdeliste_pkey`, or
— if the first has not committed — both proceed and their lines end up in the
same journal, to be posted together by whoever presses the button first.

The table has a perfectly good `kladdeliste_id_seq` behind it. Because nothing
ever uses it, it stays at its initial value forever; any code that *does* rely
on the column default collides immediately with existing rows. That is not
hypothetical — it is how this was found, when the test harness inserted a
journal using the default.

**Suggested fix:** drop the explicit id and use the sequence
(`INSERT ... RETURNING id`) in all three writers.

---

## P2-2 — Ledger imbalance is reported to the vendor, never to the user

**Where:** `includes/std_func.php:703` (`transtjek()`).

When total debit and total credit disagree by more than `0.1` kr, the function
emails `fejl@saldi.dk` and returns the difference. The user posting the
voucher is shown nothing. A book can drift out of balance for months with only
Saldi's inbox knowing about it — and only if outbound mail happens to be
working on that installation.

The `0.1` kr threshold also means the rounding gap in **P1-1** is invisible
until roughly ten vouchers have accumulated it.

---

## P1-3 — The "days before first dunning" setting has never had any effect

**Where:** `debitor/ny_rykker.php:75`.

**Pinned by:** `tests/characterization/debitor/DunningCharacterizationTest.test.php`
(`test_the_first_dunning_grace_period_is_ignored`)

```php
$rykkerfrist1 = usdate(forfaldsdag($dd, 'netto', $ffdage1));   // line 75
...
if ($konto_id[0] == "alle") {
    $dd = date("Y-m-d");                                        // line 83
```

`$dd` does not exist yet on line 75. `forfaldsdag()` returns `NULL` for an
empty date (`includes/forfaldsdag.php:33`), and `usdate(NULL)` falls back to
today (`includes/std_func.php`). So `$rykkerfrist1` is *today*, the open-post
query at `:121` selects everything with `forfaldsdate <= today`, and
`$ffdage1` — the grace period the customer configured under Div_valg (Rykker)
— is discarded.

Customers are dunned the day after an invoice falls due, regardless of the
setting. Verified against the live behaviour: with `ffdage1 = 8`, an invoice
one day overdue is still dunned; an invoice not yet due is not.

This is the same family as the `ffdage2`/`ffdage3` semicolon fixed in
b09ccd49 (PR #422) — a third site that fix did not reach. The two escalation
grace periods are now correct; this first one is not.

**Suggested fix:** move the `$rykkerfrist1` assignment below the `$dd`
assignment, or compute it from `date("Y-m-d")` directly. The regression
guards for the already-fixed halves are in the same test file and pass.

---

## P2-3 — A customer's email is discarded when created through the REST API

**Where:** `restapi/models/customers/CustomerModel.php:250` calling `:439`.

**Pinned by:** `tests/restapi/CustomersEndpointTest.php`
(`test_the_email_supplied_on_create_is_discarded`)

`save()` INSERTs the new customer with its email, then calls
`saveKontaktEmails()` unconditionally on the create path. That method ends
with a "sync primary email back for backward compatibility" step:

```php
$r = db_fetch_array(db_select("SELECT email FROM kontakt_emails
                               WHERE konto_id = '$this->id' ORDER BY id LIMIT 1"));
$sync_email = $r ? db_escape_string($r['email']) : '';
db_modify("UPDATE adresser SET email = '$sync_email' WHERE id = '$this->id'");
```

A caller who sent `email` but not `contactEmails` has no rows in
`kontakt_emails`, so the sync writes an empty string over the address that was
just stored. The customer ends up with no email anywhere. The create response
still echoes the submitted email back, which is what makes it hard to notice.

Two consequences:

1. invoices cannot be emailed to any customer created through the API;
2. the duplicate-email guard (`CustomerService::createCustomer:113`) can never
   fire, because no customer ever has an email to match. The
   identically-shaped duplicate-**phone** guard does fire — that asymmetry is
   what led here.

The update path is unaffected: there `saveKontaktEmails()` only runs
`if (!empty($this->kontakt_emails))` (`:212`).

**Suggested fix:** only run the sync-back when `kontakt_emails` is non-empty,
matching the update path.

---

## P2-4 — `limit` reaches the SQL as a raw string on three endpoints

**Where:** `restapi/endpoints/v1/products/index.php:30`,
`accounts/index.php:54`, `currencies/index.php:30` — into
`VareModel::getAllItems:132`, `AccountModel::getAllItems:213`,
`CurrencyModel::getAllItems:89`.

**Pinned by:** `tests/restapi/ProductsEndpointTest.php`
(`test_the_limit_parameter_accepts_a_sql_fragment`) and
`tests/restapi/ReferenceDataEndpointTest.php`

The models interpolate the value straight into the query:

```php
$query = "SELECT * FROM varer ORDER BY $orderBy $orderDirection LIMIT $limit";
```

The only guard is products' numeric comparison against what is actually a
string:

```php
$limit = $_GET['limit'] ?? 20;
if ($limit > 100 || $limit < 1) { $limit = 20; }
```

PHP 8 compares a non-numeric string with an int **as strings**. `"1 OFFSET 5"`
sorts below `"100"` (space < `'0'`) and above `"1"`, so both guards pass and
the fragment lands in the query. Verified: `?limit=1 OFFSET 4` returns exactly
the fifth row. `accounts` and `currencies` have no guard at all.

Bounded, but real: stacked statements do **not** execute through this driver
(verified — a `1; CREATE TABLE ...` payload creates nothing), so this is not
arbitrary SQL execution. What an authenticated caller *can* do is page past
the 100-row cap and walk an entire table, and any change to the query's shape
widens it.

The sibling endpoints already do the right thing — `debitor/customers`,
`debitor/orders`, `debitor/invoices`, `creditor/orders` and `labels` all cast
with `(int)`.

**Suggested fix:** `(int)` cast at all three call sites, then clamp.

---

## P2-5 — Two REST endpoints cannot execute at all

**Where:** `restapi/endpoints/v1/dashboard/stats.php:16` and
`restapi/endpoints/v1/vat-codes/index.php:15`.

**Pinned by:** `tests/restapi/EndpointAuthorizationTest.php`
(`test_endpoint_is_dead_for_every_caller`)

Both declare `private $db;` while `restapi/core/BaseEndpoint.php` declares the
same property `protected`. PHP refuses to compile a subclass that narrows an
inherited property's visibility:

```
PHP Fatal error: Access level to DashboardStatsEndpoint::$db must be
protected (as in class BaseEndpoint) or weaker
```

The fatal happens at class-declaration time, before routing or auth, so both
endpoints answer **500 with an empty body to every caller**, authenticated or
not. They have never worked on this codebase.

**Suggested fix:** delete the redundant `private $db;` from both — the base
class already provides it.

---

## P2-6 — The product-groups endpoint always returns an empty list

**Where:** `restapi/models/lager/VareGruppeModel.php:233`.

**Pinned by:** `tests/restapi/ReferenceDataEndpointTest.php`
(`test_the_product_groups_list_is_always_empty`)

```php
global $regnaar;
$qtxt = "SELECT id FROM grupper WHERE art = 'VG' AND fiscal_year = '$regnaar' ...";
```

`$regnaar` is a legacy page-script global that a REST request never sets, so
the filter becomes `fiscal_year = ''` and matches nothing. The neighbouring
models already stopped trusting it — `AccountModel:170` and `VatModel:55` both
call `self::getFiscalYear()` — which is why `/accounts` and `/vat` return real
rows while this one returns none.

Item groups decide an item's posting accounts and whether it is stock-tracked,
so an API client cannot build a working create-a-product form.

**Suggested fix:** resolve the year through `getFiscalYear()` like the other
models.

---

## P3-1 — `selext` typo makes a query a permanent no-op

**Where:** `docsIncludes/updateCashDraft.php:25` and its duplicate
`includes/docsIncludes/updateCashDraft.php:25`.

```php
$qtxt = "selext max(id) as sourceid from kassekladde where kladde_id = '$kladde_id'";
```

`selext` is not SQL. The query cannot ever have returned a row, so
`$sourceid` has always been empty on this path — in two separate copies of the
same file.

---

*(Findings are appended as the suite grows.)*
