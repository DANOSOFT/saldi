# Kursregulering — backdated entries and traceable corrections

**SST-774 decision document. Draft for product/finance review — not implemented.**

SST-769 delivered the two immediate fixes: a rate can now be saved without posting, and an
adjustment row shows its amount on the account statement. This document covers what that
deliberately left open — what happens when an entry is posted *behind* an adjustment, and
how a wrong adjustment gets corrected.

Nothing here is built. The purpose is to agree a design, after which implementation is
estimated separately (SST-774 acceptance criterion 4).

---

## 1. What actually happens today

Facts, verified against master `44523819`.

### 1.1 How an adjustment is created

`systemdata/valutakort.php` when a rate is saved with posting:

| Step | Where |
|---|---|
| Read every account in the currency and its **current** `kontoplan.saldo` | `:119` |
| `diff = saldo × ny_kurs / gl_kurs − saldo` | `:146` |
| Insert two balanced rows straight into `transaktioner` | `:151`, `:154` |
| `update kontoplan set valutakurs` | `:158` |
| `genberegn($regnaar)` rebuilds every `saldo` from `transaktioner` | `:230` |

The two rows are written with `bilag='0'`, `kladde_id='0'`, `valuta='-1'`,
`valutakurs='100'`, and `transdate` = the rate's date.

### 1.2 Why the amount goes stale

The adjustment is a **point-in-time snapshot of a balance**, stored as a fixed amount.

`valutakort.php:95` and `:134` refuse a rate change when postings already exist *after* the
rate date. There is no check in the other direction: nothing stops an entry being posted
with a `transdate` on or before an existing adjustment. The moment that happens, the
adjustment was computed from a balance that no longer exists, and it is never recomputed.

MEDSHOP, verified in the ticket: USD account 58103 had 192.480,51 when the 700,060 → 650,000
adjustment ran on 2026-06-05, producing 13.763,93. Four vouchers were then posted with dates
up to 05-06 totalling 38.293,84. The adjustment still reads 13.763,93; it should have been
computed on 154.186,67.

Three of five currency accounts are affected. The counter account 7960 is out by 3.228,98.

### 1.3 The correction mechanism cannot reach these rows

This is the finding that shapes everything below.

Saldi already has a reversal flow: `ompost()` in `finans/kassekladde.php:4319`. It copies a
posting into an **open kassekladde** with debet and kredit swapped, and the user then reviews
and posts that journal like any other. The original is never touched. That is exactly the
audit-preserving correction SST-774 asks for — and it already exists, is understood by
bookkeepers, and needs no new concepts.

It operates on `kassekladde` rows. Rate adjustments are written **directly to
`transaktioner` with `kladde_id='0'`** and never exist in a kassekladde, so `ompost()`
cannot see them.

So traceability is not a nice-to-have that can be added later. **Without a `kladde_id`, the
correction mechanism the house already has cannot be pointed at these rows at all.**

### 1.4 What the schema can carry

`transaktioner` columns relevant here: `bilag`, `kladde_id`, `faktura` (text), `beskrivelse`
(text), `ordre_id`, `report_number`. There is **no column linking one row to another**, so a
reversal cannot reference its original without either a new column or a convention in an
existing text field. See §5.

### 1.5 Closed periods are enforced in the database

`tr_check_moms_periode_luk`, a PL/pgSQL trigger on `transaktioner` (installed via
`includes/betweenUpdates.php`, see `moms_periode_luk_ensure_schema()` in
`includes/std_func.php:3236`), rejects writes into a locked period.

This applies to *any* code path, including the adjustment inserts. The design therefore does
not need its own period rules — it needs to define what the user sees when the trigger
refuses.

---

## 2. The two decisions

### Decision A — what happens when someone posts behind an adjustment

| Option | Behaviour | Assessment |
|---|---|---|
| **A1 Block** | Refuse an entry dated on or before the latest adjustment on any account in that currency | Symmetric with the existing `:95`/`:134` blocks. Simple, no new postings. But it refuses ordinary bookkeeping — MEDSHOP's four vouchers were legitimate — and the bookkeeper cannot proceed without deleting the adjustment |
| **A2 Warn** | Allow, but tell the user the adjustment is now stale and offer the correction flow | Preserves the bookkeeper's freedom, makes staleness visible at the moment it is created. Requires the correction flow to exist |
| **A3 Recalculate** | Automatically recompute the adjustment when a backdated entry lands | Explicitly rejected by the customer: *"Den skal IKKE rette selv."* Also rejected by SST-774's scope — rewriting an existing adjustment is not an approved correction design |

**Recommendation: A2.** A3 is ruled out by the customer and the ticket. A1 is defensible and
cheap but converts a data-quality problem into a blocked workflow, and MEDSHOP's own case is
the counter-example. A2 needs A-side work only once the correction flow below exists.

### Decision B — how a wrong adjustment gets corrected

| Option | Behaviour | Assessment |
|---|---|---|
| **B1 Rewrite in place** | Update the existing `transaktioner` rows | Explicitly not approved by SST-774. Destroys audit trail |
| **B2 Delete and repost** | Remove the two rows, post fresh ones | Same audit problem; also trips the period trigger for closed periods |
| **B3 Reverse into an open kassekladde** | Mirror the two rows into a journal with debet/kredit swapped, user reviews and posts; optionally queue a fresh adjustment alongside | Preserves the original. Reuses `ompost()`. Gets bilag numbering, review-before-post and period-lock handling for free |

**Recommendation: B3**, which requires §3.

---

## 3. Recommended design

### 3.1 Give adjustments a journal identity

Create the rate adjustment as a **kassekladde entry** instead of two direct `transaktioner`
inserts. The user reviews and posts it exactly like any other journal.

This single change delivers, without new mechanisms:

- a real `bilag` number, so the row is clickable from the account statement (SST-769's
  fifth acceptance criterion, left open there)
- a `kladde_id`, which makes `ompost()` applicable — Decision B3 becomes reuse, not new code
- review-before-post, which is what the bookkeeper asked for in the first place
- correct behaviour in closed periods: the trigger refuses at posting time, with the entry
  still sitting in the journal rather than half-written

It also changes when the adjustment hits the ledger: on posting the journal, not on saving
the rate. **This is the main thing to agree with finance** — it is the right behaviour, but
it is a change in timing.

### 3.2 Staleness is derived, never stored

Do not add a "stale" flag. An adjustment is stale when an entry exists on the account with
`transdate <= adjustment.transdate` and `logdate > adjustment.logdate` — i.e. booked after
the adjustment but dated before it. That is computable from `transaktioner` alone and cannot
drift out of sync.

Surface it in two places: a warning when such an entry is posted (Decision A2), and a marker
on the account statement next to the adjustment row.

### 3.3 Correction flow

1. User opens the account statement, sees the adjustment marked stale.
2. "Korrigér kursregulering" → a preview showing: the original rows, the balance they were
   computed on, the balance now, and the resulting corrected amount.
3. Confirm → reversal of the original pair plus the recomputed adjustment, both into an open
   kassekladde, neither posted.
4. User reviews and posts the journal.

Step 2 is SST-774's "preview and user confirmation flow". Nothing is written to the ledger
without an explicit post.

---

## 4. Worked examples

Using MEDSHOP's verified figures. Rates: 700,060 from 2023-03-21, 650,000 from 2026-06-05,
655,000 from 2026-07-01. Account 58103, difference account 7960.

### 4.1 Entry dated before an adjustment

Adjustment posted 2026-06-05 on 192.480,51 → 13.763,93. Voucher 3059 then posted, dated
2026-06-01, 9.366,80 credit.

- **Today:** silent. Adjustment unchanged, account misstated.
- **Proposed:** warning on posting 3059. Statement marks the adjustment stale. Correction
  preview offers reversal of 13.763,93 and a replacement computed on the current balance.

### 4.2 Entry dated exactly on the adjustment date

Vouchers 2932–2934 are dated before; the boundary case is an entry dated 2026-06-05 itself.

**To agree:** an entry dated the same day as the adjustment is treated as *behind* it, so the
rule is `transdate <= adjustment.transdate`. Same-day ordering is not otherwise determined,
and treating same-day as behind is the conservative reading.

### 4.3 A later rate change

The 2026-07-01 adjustment (650,000 → 655,000) is computed on whatever the balance is then,
which already includes the backdated vouchers. It is **not** stale.

Correcting the June adjustment changes the balance the July one was computed on, making it
stale in turn. **The correction flow must therefore re-evaluate later adjustments on the same
account and offer them in the same preview** — otherwise correcting one creates another.

### 4.4 Multiple accounts in one currency

One rate change produces one adjustment per account. MEDSHOP: three of five affected.

Corrections are **per account**, not per currency, so an unaffected account is never touched.
The preview lists the affected accounts and lets the user act on each.

### 4.5 Closed period

If the adjustment falls in a period closed via `moms_periode_luk`, the reversal cannot be
posted there — the trigger refuses.

**To agree:** the correction is posted in the current open period with the original date in
the description, rather than reopening a closed period. Reopening is a finance decision that
should not be automated.

### 4.6 Repeated correction requests

Running the correction twice must not produce two reversals. Idempotency in §5.

---

## 5. Traceability and idempotency

### 5.1 Linking a correction to its original

`transaktioner` has no row-to-row reference column. Two options:

| Option | Cost | Assessment |
|---|---|---|
| **New column** e.g. `korrigerer_id integer`, added idempotently in `includes/betweenUpdates.php` | One migration across tenant DBs | Explicit, queryable, survives description edits. Per `doc/ai/convention_database_changes.md` this is where new structure goes |
| **Convention in `faktura` or `beskrivelse`** | None | Fragile: free text, editable, unqueryable |

**Recommendation: the new column.** The ticket asks to "define traceability"; a text
convention does not survive contact with a bookkeeper editing a description.

### 5.2 What a correction records

Actor, timestamp, original reference and reason, per SST-774. `logdate`/`logtime` already
carry the timestamp. Actor is **not** currently on `transaktioner` — decide whether to add it
or rely on `kladdeliste.oprettet_af` on the journal the correction goes through. The journal
route is cheaper and is the house pattern.

### 5.3 Idempotency

An adjustment already referenced by a correction row (§5.1) is not offered for correction
again. That makes "correct this adjustment" naturally idempotent without a lock or a flag,
and it is queryable for an audit.

---

## 6. Open questions for product/finance

1. **Timing (§3.1).** Adjustments would post when the journal is posted, not when the rate is
   saved. Agreed?
2. **Decision A.** Warn (A2) rather than block (A1)?
3. **Same-day (§4.2).** Is an entry dated the same day as an adjustment "behind" it?
4. **Closed periods (§4.5).** Post the correction in the current open period rather than
   reopening?
5. **Cascade (§4.3).** Should correcting one adjustment offer later adjustments on the same
   account in the same preview?
6. **Actor (§5.2).** New column on `transaktioner`, or rely on the journal?
7. **MEDSHOP's existing books.** Out of scope for this design and not to be touched without an
   explicit request. The figures in SST-769 are reported evidence, not authorisation.

---

## 7. Implementation scope, to be estimated separately

Not part of this ticket's estimate; listed so the estimate has something to price.

| Piece | Rough shape |
|---|---|
| Adjustment via kassekladde (§3.1) | Rework `valutakort.php`'s posting branch; the larger piece |
| Derived staleness (§3.2) | One query, plus a marker on the account statement |
| Warning on backdated posting (A2) | Check in the posting path |
| Correction preview + flow (§3.3) | New screen; reuses `ompost()` |
| `korrigerer_id` migration (§5.1) | Idempotent block in `includes/betweenUpdates.php` |
| Cascade to later adjustments (§4.3) | Depends on Q5 |

---

## 8. Status

Draft. Blocked on the answers in §6. Once agreed, this file becomes the reference for the
implementation ticket, amended in the same commit as any code that deviates from it —
following `doc/stripe/INTERFACE_CONTRACT.md`.
