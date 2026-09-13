# Private expense — transfer from the document pool to a journal

**SST-778 decision document. Draft for product/finance review — not implemented.**

SST-740 asks for a checkbox that transfers a pool document to a journal as a private
expense to be reimbursed. This is a feature request, not an extraction defect.

Nothing here is built. The purpose is to agree the accounting behaviour, after which
implementation is estimated separately (SST-778 acceptance criterion 4). Per the ticket,
implementation is out of scope until that agreement exists.

---

## 1. What exists today

Facts, verified against master `44523819`.

### 1.1 The pool attachment flow

A pool document reaches the journal today only by being attached to a journal line that the
user is already editing:

| Step | Where |
|---|---|
| `docPool($sourceId, $source, $kladde_id, $bilag, $fokus, $poolFile, $docFolder, $docFocus)` | `includes/docsIncludes/docPool.php:257` |
| Pre-fills the line's date from the pool metadata into `$_POST['dato']` | `:381` |
| Pre-fills the amount likewise | `:390` |

The pool never creates a journal line. It fills in one the user opened.

`finans/pulje_review.php` is a separate mobile-friendly review screen that lists and deletes
pool documents (`:28`). It has no journal path at all.

### 1.2 A journal line is already a draft

`kladdeliste.bogfort = '-'` marks an open, unposted journal (`finans/kassekladde.php:551`,
`:726`). Lines sit in `kassekladde` until the user posts the journal, at which point they
become rows in `transaktioner`.

So "draft versus posted" does not need inventing. **A line written into an open journal is
the draft.** The house already works this way, and bookkeepers already understand it.

### 1.3 Document linkage survives posting — via `documents`, not the line

`kassekladde` has a `dokument` column, but `transaktioner` does **not**. The durable link is
the `documents` table:

| Column | Use |
|---|---|
| `filename`, `filepath` | the document itself |
| `source`, `source_id` | what it is attached to |
| `user_id` | who attached it |
| `timestamp` | when |

That already carries actor, timestamp and the source reference the ticket asks for. It is
also the natural exactly-once key — see §4.

### 1.4 The fields the accounting questions map onto

`kassekladde` already has every hook needed; none of the decisions below require new
structure:

| Question | Existing field |
|---|---|
| Offset account (both sides) | `d_type` / `debet`, `k_type` / `kredit` |
| VAT treatment | `momsfri` |
| Whose expense | `ansat` (text), `medarb` (integer) |
| Which journal | `kladde_id` |
| Voucher number | `bilag` |
| Document link | `dokument`, plus the `documents` row |

---

## 2. The decisions to agree

### 2.1 Whose expense is reimbursed

| Option | Assessment |
|---|---|
| **The logged-in user** | Simplest. Wrong whenever a bookkeeper enters someone else's receipt, which is the common case |
| **Chosen from the employee list** (`ansat` / `medarb`) | Matches the existing fields. One more choice in the dialog |
| **A fixed owner/proprietor account** | Right for a one-person business, wrong for anyone with staff |

**Recommendation: chosen from the employee list, defaulting to the logged-in user.** The
fields already exist and the default covers the common self-service case without being wrong
for the others.

### 2.2 Offset account

A private expense paid personally is, in ledger terms, an expense debit against a payable to
the person. The expense account varies per document; the payable does not.

**Recommendation:** expense account chosen per document (defaulting to the account already
on the pool record, which the extraction or the user set); payable account taken from a
**new setting**, not typed each time. A per-employee payable is a possible refinement and is
Q3 in §8.

### 2.3 Journal selection

| Option | Assessment |
|---|---|
| **Create a new journal per transfer** | Clutters `kladdeliste`; a month of receipts becomes a month of journals |
| **Pick an open journal** | Matches `ompost()` in `finans/kassekladde.php:4319`, which already makes the user pick an open journal to reverse into. Consistent and familiar |
| **A dedicated standing "private expenses" journal** | Convenient, but a second mechanism to maintain |

**Recommendation: pick an open journal**, reusing the `ompost()` pattern. If none is open,
say so and stop — as `ompost()` does — rather than silently creating one.

### 2.4 VAT treatment

This is the decision with the most accounting consequence and the least I can settle from
the code.

A private outlay reimbursed by the business may or may not carry deductible VAT depending on
what was bought and for whom. The `momsfri` field exists per line, so both are expressible.

**Recommendation: do not assume.** Default the line to the VAT treatment already implied by
the chosen expense account, and leave it editable in the draft before posting. Never derive
a VAT split silently from the document's extracted total.

**This needs a finance answer, not a developer's** — Q4 in §8.

### 2.5 Permissions

The action creates an accounting entry naming an employee as a creditor, so it is not a
neutral filing action.

**Recommendation:** the same permission that already governs writing to a journal. Do not
invent a new permission level for this. Authorisation is checked independently of input
escaping, per the ticket's criteria.

### 2.6 Draft or posted

The ticket is explicit: *no automatic final posting by assumption*.

**Recommendation: a draft line in an open journal, never posted automatically** — §1.2. The
user reviews and posts through the normal flow, which also means the period lock
(`tr_check_moms_periode_luk` on `transaktioner`) applies at the right moment.

---

## 3. Recommended flow

1. User opens the document pool and ticks "privatudgift" on a document.
2. Dialog: employee (default = logged-in user), expense account (default = the pool
   record's account), open journal to write into, VAT treatment.
3. Confirm → one draft line in the chosen journal: expense account debit, employee payable
   credit, amount and date from the pool record, description from the document.
4. The document is attached to that line through the existing attachment flow, so a
   `documents` row records filename, source, source_id, user and timestamp.
5. The source document **stays in the pool** and is marked as transferred. It is not moved or
   deleted.
6. User reviews and posts the journal when ready.

Nothing reaches `transaktioner` without an explicit post.

---

## 4. Exactly-once

Repeated clicks must not create two journal lines.

**Derive it, do not store a flag.** A pool document has already been transferred when a
`documents` row exists for that filename with a journal source. That is queryable, cannot
drift out of sync with reality, and survives the journal being posted — the `documents` row
outlives the `kassekladde` row.

Behaviour on a repeat click: show the existing journal line and offer to open it, rather than
creating a second one or silently doing nothing. A document legitimately transferred twice
(two different expenses on one receipt) is an explicit override, not the default.

This mirrors the idempotency rule in `doc/valuta/KURSREGULERING_DESIGN.md` §5.3: a thing
already referenced is not offered again.

---

## 5. Cancellation and failure

| Situation | Behaviour |
|---|---|
| User cancels the dialog | Nothing written. Document untouched in the pool |
| No open journal exists | Say so and stop, as `ompost()` does. Do not create one |
| Journal line write fails | No `documents` row, no partial state. The two writes belong in one transaction |
| Journal is posted, then the user wants it undone | Not a new mechanism — the line is in `transaktioner` and is corrected by `ompost()` like any other posting |
| Document deleted from the pool after transfer | The journal line and its `documents` row stand. The line is accounting; the pool is a staging area |

---

## 6. Worked examples

### 6.1 Ordinary case

Employee buys printer paper for 250,00 DKK incl. VAT, photographs the receipt into the pool.

Ticks privatudgift → employee = herself, expense account = office supplies, journal = the
open August journal, VAT = as the account implies. One draft line: office supplies debit,
employee payable credit, 250,00, dated from the receipt. She posts the journal. The receipt
stays in the pool, marked transferred, and is reachable from the posting via `documents`.

### 6.2 Bookkeeper entering someone else's receipt

Same, but the bookkeeper selects the employee rather than accepting the default. This is why
§2.1 recommends a choice rather than the logged-in user.

### 6.3 Repeated click

The user clicks twice, or retries after a timeout. The second attempt finds the existing
`documents` row and offers the existing line instead of creating another (§4).

### 6.4 No open journal

Nothing is written; the user is told to create a journal first — the same message shape
`ompost()` already uses (`findtekst('2597|…')`).

### 6.5 Mixed receipt

One receipt covering a private expense and a business expense. **Out of scope for the first
implementation** — the design assumes one document produces one line. Splitting is Q6 in §8.

---

## 7. Prodtest scenarios

Concrete, per the ticket's acceptance criteria:

1. Transfer a receipt, confirm one draft line with the expected debit/credit and no
   `transaktioner` row before posting.
2. Post the journal; confirm the line reaches `transaktioner` and the document is still
   reachable via `documents`.
3. Click transfer twice; confirm exactly one line.
4. Cancel the dialog; confirm nothing written anywhere.
5. Attempt a transfer with no open journal; confirm the refusal and that nothing is written.
6. Transfer for an employee other than the logged-in user; confirm the payable names that
   employee.
7. Transfer a receipt whose expense account implies no VAT deduction; confirm the VAT
   treatment of the resulting line.
8. Delete the document from the pool after posting; confirm the posting and its `documents`
   row survive.

---

## 8. Open questions for product/finance

1. **§2.1** Employee chosen per transfer, defaulting to the logged-in user?
2. **§2.2** A single payable account as a setting, or one per employee?
3. **§2.2** Which account should be the default payable?
4. **§2.4** VAT treatment of a reimbursed private outlay — the finance answer, not a
   developer's.
5. **§2.3** Pick an open journal, rather than creating one per transfer?
6. **§6.5** Does the first implementation need to split one receipt across several lines?
7. Should a transferred document be hidden from the pool's default view, or stay visible with
   a marker? §3 assumes the latter.

---

## 9. Implementation scope, to be estimated separately

Not part of this ticket's estimate; listed so the estimate has something to price.

| Piece | Rough shape |
|---|---|
| Checkbox and dialog in the pool UI | `includes/docsIncludes/docPool.php`, and `finans/pulje_review.php` if the mobile screen gets it too |
| Draft line creation | New; writes `kassekladde` plus the `documents` row in one transaction |
| Payable account setting | `settings` table; no migration if an existing group is reused |
| Exactly-once check (§4) | One query against `documents` |
| Transferred marker in the pool list | Depends on Q7 |
| `findtekst` ids for the dialog | Coordinate with open branches — SST-775 holds 5151–5152, SST-769 holds 5153–5155 |

---

## 10. Status

Draft. Blocked on the answers in §8, and on SST-740, which this ticket is blocked by.

Note also that SST-778 is recorded as duplicating **SD-679 (Done)**, and there is no
reference to SD-679 anywhere in the git history — so it has not been possible to establish
what that ticket delivered or whether it overlaps with this design.

Once agreed, this file becomes the reference for the implementation ticket, amended in the
same commit as any code that deviates from it — following
`doc/stripe/INTERFACE_CONTRACT.md`.
