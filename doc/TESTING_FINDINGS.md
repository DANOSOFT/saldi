# Findings from the end-to-end testing push

Defects and risks surfaced while building the regression suite (July 2026).
Every entry has a reproduction that runs against the repo's own
docker-compose stack. Entries marked **pinned** have a test that asserts the
current (wrong) behavior, so the test fails the moment the behavior changes -
which is the signal to rewrite it as an assertion of the correct behavior.

Severity is about money and data, not about how hard it is to hit:

- **P1** — the general ledger, a customer's money, or the server itself can
  end up wrong.
- **P2** — data loss, corruption, or a failure the user is not told about.
- **P3** — dead code, wrong-but-harmless behavior, maintenance hazards.

## Summary

| # | Sev | What | Where |
|---|---|---|---|
| P1-4 | **P1** | Anyone on the internet can create an accounting entity | `admin/opret.php:118` |
| P1-1 | **P1** | A voucher that balances on entry can post an unbalanced ledger entry | `finans/bogfor.php:751`, `:962` |
| P1-2 | **P1** | Every posting recomputes the whole chart of accounts three times | `finans/bogfor.php:74`, `:224`, `:561` |
| P1-3 | **P1** | The "days before first dunning" setting has never had any effect | `debitor/ny_rykker.php:75` |
| P2-0 | P2 | Six stock pages return HTTP 500 to every user, including the item list | `includes/dkdecimal.php:21` |
| P2-1 | P2 | `kladdeliste.id` allocated with `MAX(id)+1` by three writers | `finans/kassekladde.php:1045` +2 |
| P2-1b | P2 | The stock balance list logs the user out on first open | `lager/beholdningsliste.php` |
| P2-2 | P2 | Ledger imbalance is emailed to the vendor, never shown to the user | `includes/std_func.php:703` |
| P2-3 | P2 | A customer's email is discarded when created through the REST API | `CustomerModel.php:250` |
| P2-4 | P2 | `limit` reaches the SQL as a raw string on three endpoints | `products/index.php:30` +2 |
| P2-5 | P2 | Two REST endpoints cannot execute at all | `dashboard/stats.php:16` +1 |
| P2-6 | P2 | The product-groups endpoint always returns an empty list | `VareGruppeModel.php:233` |
| P3-1 | P3 | Two unreferenced cash-draft helpers were removed instead of repaired | Former `docsIncludes/updateCashDraft.php` and `includes/docsIncludes/updateCashDraft.php` |

Every one of these was found by a test rather than by reading code.

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

**Reachability:** not from the keyboard. `e2e/voucher-precision.spec.mjs`
types `100,005` into the cash journal and the form hands back a two-decimal
amount, so a bookkeeper cannot enter one by hand. What remains are the paths
that write `kassekladde.amount` directly: currency conversion
(`valutaopslag`, used at `bogfor.php:723`) produces sub-øre amounts by
construction, and the bank/CSV importers write the column straight.

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

## P1-4 — Anyone on the internet can create an accounting entity

**Where:** `admin/opret.php:118`.

**Pinned by:** `tests/restapi/AdminOpretAuthorizationTest.php`

`admin/opret.php` creates a whole tenant: a new PostgreSQL database with the
full ~116-table schema, seeded from `importfiler/`, plus an administrator
account that can log into it. It is reached from the admin panel, so it should
sit behind the same session check as everything else there. The check exists —
on the wrong side of the condition:

```php
if (!isset($_POST['regnskab']) || !$_POST['brugernavn']
    || !$_POST['passwd'] || !$_POST['passwd2']) {
    include("../includes/online.php");            // the only auth check
    if ($db != $sqdb) { ...refuse and log out... }
}
```

The session is consulted **only when the form fields are missing**. Supply all
four and `includes/online.php` is never loaded, so nothing is checked and the
creation below runs for anybody.

Verified against the docker stack with a single anonymous request — no cookie,
no token, no credentials:

```bash
curl -X POST http://localhost:5000/saldi/admin/opret.php \
  -d regnskab=probe -d brugernavn=probeadmin \
  -d passwd=... -d passwd2=...
```

It returned 200 and left behind a registry row, a database `saldi_88` with
**116 tables**, and a working `probeadmin` login. Omitting any one of the four
fields reaches the session check and is refused — which is what shows the
check works and is simply in the wrong branch.

Impact: unauthenticated database creation on any reachable Saldi installation.
Repeat it in a loop and it is a disk-exhaustion denial of service; do it once
and the caller has a foothold with a real login on someone else's server.

**Suggested fix:** include `../includes/online.php` and run the `$db != $sqdb`
check unconditionally, before any `$_POST` handling.

---

## P2-0 — Six stock pages return HTTP 500 to every user

**Where:** `includes/dkdecimal.php:21`; `lager/minmaxstock.php:140`.

**Pinned by:** `e2e/pages.spec.mjs` (`stock pages that cannot be opened`)

Five of them — `lager/enheder.php`, **`lager/vareliste.php`** (the item list),
`lager/fuld_stykliste.php`, `lager/opdater_kostpriser.php`,
`lager/vareimport.php` — include `../includes/dkdecimal.php`, which declares

```php
function dkdecimal($tal) { ... }        // no function_exists guard
```

while `includes/stdFunc/dkDecimal.php:27` declares a *guarded* two-argument
`dkdecimal($tal, $decimaler)` that `includes/online.php` has already loaded by
the time the page gets there:

```
PHP Fatal error: Cannot redeclare dkdecimal() (previously declared in
.../includes/stdFunc/dkDecimal.php:27) in .../includes/dkdecimal.php on line 21
```

The sixth, `lager/minmaxstock.php`, dies on its own:

```
PHP Fatal error: Uncaught TypeError: in_array(): Argument #2 ($haystack)
must be of type array, null given in .../lager/minmaxstock.php:140
```

All six answer 500 to a fully authenticated user. The item list being dead
means there is no way to browse stock in the UI at all.

Seven more files include the same unguarded `dkdecimal.php`
(`debitor/basis_stykliste.php`, `debitor/udlign_openpost.php`,
`finans/simuler.php`, `produktion/ordre.php`, `produktion/ordreliste.php`,
`produktion/rapport.php`, `lager/vvsimport.php`). Those survive today only
because of their include order — they are one edit away from the same fatal.

**Suggested fix:** delete `includes/dkdecimal.php` and point its seven
remaining callers at `includes/stdFunc/dkDecimal.php`; guard the `in_array`
in minmaxstock.

---

## P2-1b — The stock balance list logs the user out on first open

**Where:** `lager/beholdningsliste.php`.

**Pinned by:** `e2e/pages.spec.mjs` (`the stock balance list`)

Log in, open `/saldi/lager/beholdningsliste.php`, and the browser lands on the
login page. Navigate to it a second time and it works, and keeps working for
the rest of the session. Reproduced deterministically, with and without
visiting another stock page first, so it is this page's own first request that
loses the session.

The mechanism is not identified. Worth noting the page opens with its module
number commented out — `# $modulnr=9;` (`:25`) — while `includes/online.php:331`
authorises with `substr($rettigheder, $modulnr, 1)`; that is the first thing to
look at, but the test only pins the behaviour that was observed.

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

## P3-1 — Dead cash-draft helpers removed instead of repaired

**Where:** the former `docsIncludes/updateCashDraft.php` and
`includes/docsIncludes/updateCashDraft.php` paths.

The identical helpers had no tracked include, require, loader, route, link, or
other caller. Their `$sourceId` assignment was the last executable statement,
so neither file had an in-file consumer for the value. Repairing the malformed
query would therefore have activated a dormant path that inserted a blank
`kassekladde` row without an established caller or requirement.

The active document flow uses `includes/documents.php` and
`includes/docsIncludes/insertDoc.php`, where cash-journal row creation and the
resulting `$sourceId` are handled together. Both helpers were deleted: the
copy under the top-level `docsIncludes/` directory was also dead. General
`docsIncludes/` deduplication remains outside this resolution.

---

---

# What the suite does not cover

A list of findings is only meaningful next to the shape of the search that
produced it. These areas were **not** exercised, so their silence means
nothing:

**Modules with no coverage at all.** POS (`debitor/pos_ordre.php`),
production (`produktion/`), projects (`sager/`), rental (`rental/`), booking
(`booking/`), table plans (`bordplaner/`), payroll (`sager/opdat_loen.php`).
POS is the largest of these and carries its own order and crediting logic that
does not go through the paths tested here.

**The other two API surfaces.** Only `restapi/` is covered.
`api/rest_api.php` (the legacy webshop API, with its own auth) and `api/v2/`
(API keys) are untested and unmapped. The July debt audit flagged a pre-auth
SQL injection in `api/rest_api.php`; nothing here confirms or refutes it.

**Reports and their arithmetic.** The e2e sweep proves report pages *load*;
no test checks a single number any of them prints. `finans/rapport.php`,
`reportFunc/`, VAT settlement (`listeangivelse.php`) and the annual accounts
are unverified.

**Money leaving the system.** Payment files (`debitor/pbsfile.php`), Nets/PBS
integration, MobilePay and Stripe webhooks, e-invoicing (`easyUBL`,
NemHandel), and the webshop sync are all untouched. These are the highest-value
paths in the product and the least tested.

**Multi-tenancy and permissions.** Every test runs as one user with every
permission bit set, against one tenant. Nothing verifies that a user without a
module's permission is refused, or that one tenant cannot reach another's
data. Given P1-4, the tenant-isolation question deserves its own pass.

**Concurrency.** The `MAX(id)+1` races (P2-1) and the read-modify-write in
`lagerstatus()` are described from the code and reproduced only single-
threaded. No test runs two writers at once.

**Upgrades.** `includes/opdat_*.php` and `betweenUpdates.php` migrate customer
databases between versions. Nothing tests that an older tenant survives them,
which is the failure mode that would hit every customer at once.

**Browsers.** Chromium only.

## Suggested next steps

1. Fix P1-4 first. It is the only finding that exposes the server rather than
   the books, and the fix is three lines.
2. Land the fixes for P2-0 and P2-5 — dead pages and dead endpoints are cheap
   to fix and each one is a feature a customer cannot currently use.
3. Decide what P1-1 should do. The engine currently accepts a voucher that
   posts an unbalanced entry; the fix (round each leg before the balance
   guard sums it) changes posted numbers, so it wants a deliberate decision
   rather than a drive-by patch.
4. Take P1-2 seriously before the next large customer. Posting time already
   exceeds fifteen seconds on a book with ten transactions in it, and the cost
   grows with the ledger.
5. Extend coverage to the payment and e-invoicing paths — the money-leaving
   paths listed above are the largest remaining hole.
