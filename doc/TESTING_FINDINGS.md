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
