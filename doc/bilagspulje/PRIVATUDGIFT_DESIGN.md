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

### 1.1 The pool already creates journal lines

There are two paths, and the second one matters more than the ticket's framing suggests.

**Attaching to a line the user is editing.** `docPool()`
(`includes/docsIncludes/docPool.php:257`) pre-fills an open line's date (`:381`) and amount
(`:390`) from the pool record.

**Creating a line.** `docPool.php:557-575` includes `includes/docsIncludes/insertDoc.php`,
which at `:89` — `if ($source == 'kassekladde' && !$sourceId)` — does all of this already:

| Step | Where |
|---|---|
| Allocate the next `bilag` within the fiscal year | `insertDoc.php:118` |
| Allocate the next `pos` within that voucher | `:114` |
| Insert the `kassekladde` line (placeholder `d_type`/`k_type` = `F`, amount 0) | `:118` |
| Read back the new line's id as `$sourceId` | `:122` |
| Update the line's fields from POST | `:127` onwards |
| Write the `documents` row linking the file | see §1.3 |

So the skeleton SST-778 needs — new journal line, voucher number, document link — **exists
and is in daily use.** What a private expense adds on top is which accounts go on the two
sides, whose payable it is, and the VAT treatment, in place of the `F`/`F`/0 placeholder.

That materially shrinks the implementation; see §9.

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

That already carries actor, timestamp and the source reference the ticket asks for, and it is
what survives posting. It is **not** sufficient as an exactly-once key on its own — see §4,
which is the part of this design SST-740 changed.

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

### 1.5 What SST-740 shipped, and why it changes this design

This ticket is marked *blocked by* SST-740. SST-740 is now merged (`e852d8c5`, PR #596), so
its outcome is knowable rather than assumed, and it changes §4 materially.

Three things landed:

| What | Where |
|---|---|
| A UNIQUE index on `pool_files (filename)`, tenant-wide, with a dedupe pass that keeps the highest `id` | `includes/betweenUpdates.php:571-608` |
| `syncPuljeFilesToDatabase()` now **deletes** any `pool_files` row whose file is no longer in the pulje folder, before checking what is missing | `includes/docsIncludes/docPool.php` |
| `FileReservation` — atomic no-replace filename reservation via `fopen('x')`, with `_N` suffixing | `includes/docsIncludes/FileReservation.php` |

The deletion is guarded two ways: a failed `scandir()` bails out rather than reading a
permission error as "folder is empty" and wiping every row, and rows touched in the last 60
seconds are exempt so a concurrent upload landing between the snapshot and the delete
survives.

Two consequences for this design:

- **A `pool_files` row is no longer durable.** It can disappear on any sync pass once its
  file leaves the folder — which is exactly what an attach does. §4 is rewritten around this.
- **`FileReservation` solves file naming, not transfer idempotency.** It guarantees two
  concurrent uploads cannot settle on one path. It says nothing about whether a document has
  already been turned into a journal line, so §4 cannot delegate to it.

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
4. The document is attached to that line by the same `insertDoc.php` path that created it, so a
   `documents` row records filename, source, source_id, user and timestamp.
5. The source document **stays in the pool** and is marked as transferred. It is not moved or
   deleted.
6. User reviews and posts the journal when ready.

Nothing reaches `transaktioner` without an explicit post.

---

## 4. Exactly-once

Repeated clicks must not create two journal lines. The behaviour on a repeat click should be
to show the existing journal line and offer to open it — not to create a second one, and not
to silently do nothing. A document legitimately transferred twice (two different expenses on
one receipt) is an explicit override, not the default. That much is unchanged.

**What changed: the key this was built on does not hold.** An earlier revision of this
document recommended deriving the answer from `documents` — "already transferred" meaning a
`documents` row exists for that *filename* with a journal source. Reviewing SST-740 (§1.5)
shows that to be unsafe, and it fails in the one direction nobody notices.

### 4.1 Why the filename key fails

The pool filename is carried verbatim into `documents.filename` (`insertDoc.php:84`, then
`basename()` at `:360`), so the check would read exactly the name SST-740 allows to be reused:

1. SST-740's UNIQUE index makes `pool_files.filename` unique **at a point in time**, not over
   time.
2. `syncPuljeFilesToDatabase()` now deletes the row once the file leaves the pulje folder,
   which is precisely what attaching a document does. The name is then free.
3. A later unrelated upload can generate that same name. This is not hypothetical — it is the
   reported case SST-740 was written to fix, described in master's own comment as a
   *"recurring vendor + date, like NETS/META"*.

So: September's NETS invoice is transferred and leaves a `documents` row named
`NETS_2026-09-01.pdf`. October's NETS invoice generates the same filename. The check finds
September's row, reports "already transferred", and **refuses a legitimate, untransferred
expense**. It is silent, it looks like correct idempotency, and the user's only signal is that
a real expense cannot be booked.

### 4.2 There is no existing durable identifier to switch to

Checked, and each candidate fails:

| Candidate | Why not |
|---|---|
| `pool_files.id` | Dies with the row — both SST-740's orphan delete and `insertDoc.php`'s own delete on attach |
| `documents.global_id` | A tenant-wide value read from `settings` (`insertDoc.php:50-52`), not a per-document id |
| `documents.filepath` | For the journal path it is `/finance/<kladde>/<source>` (`insertDoc.php:370`) — identifies the destination, not the source document |
| `documents.filename` | §4.1 |

This is the part that needs a decision rather than a recommendation dressed up as a fact: on
the current schema, *nothing durably identifies "this particular pool document" after the
transfer*.

### 4.3 Recommendation: key on content, not on name

Store a content hash of the file at transfer time and make that the exactly-once key —
"already transferred" means a `documents` row exists with journal source and this hash.

- Durable: unaffected by the pool row disappearing, by renaming, and by filename reuse.
- Correct in both directions: October's NETS invoice hashes differently from September's, so
  it transfers; a genuine re-click on the same file matches and is caught.
- Cheap: one `hash_file('sha256', ...)` per transfer, on invoice-sized PDFs.
- Idempotent migration: a nullable `content_hash` column on `documents`, added in
  `includes/betweenUpdates.php` per the ticket's own constraint. Existing rows stay NULL and
  are simply never matched, which degrades to today's behaviour rather than to a wrong answer.

Open for product/finance, added to §8: whether two byte-identical receipts from the same
vendor on the same day are one expense or two. The hash cannot distinguish them, and the
answer decides whether the match is a hard block or a confirmable warning.

### 4.4 The pool row can vanish mid-flight

SST-740's 60-second grace window means a `pool_files` row can be deleted between the moment
the dialog is opened and the moment it is confirmed. The transfer must re-read the row inside
its transaction and fail cleanly if it is gone, rather than writing a journal line referencing
a document that is no longer in the pool.

This mirrors the idempotency rule in `doc/valuta/KURSREGULERING_DESIGN.md` §5.3: a thing
already referenced is not offered again — but the identity of "the thing" has to be something
that cannot be recycled.

---

## 5. Cancellation and failure

| Situation | Behaviour |
|---|---|
| User cancels the dialog | Nothing written. Document untouched in the pool |
| No open journal exists | Say so and stop, as `ompost()` does. Do not create one |
| Journal line write fails | No `documents` row, no partial state. The two writes belong in one transaction |
| Journal is posted, then the user wants it undone | Not a new mechanism — the line is in `transaktioner` and is corrected by `ompost()` like any other posting |
| Document deleted from the pool after transfer | The journal line and its `documents` row stand. The line is accounting; the pool is a staging area |
| Pool row deleted by folder sync while the dialog is open | Re-read inside the transaction and fail cleanly (§4.4). Never write a line for a document that has left the pool |
| Same filename reappears from a different document | Transfers normally — the content hash differs (§4.3). This is the case the filename key got wrong |
| Hash matches an existing transfer | Show the existing line. Whether this hard-blocks or warns depends on Q8 |

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

The user clicks twice, or retries after a timeout. The second attempt hashes the same file,
matches the existing `documents` row and offers the existing line instead of creating another
(§4.3).

Note what this example must *not* catch: next month's invoice from the same vendor, which may
well carry the same filename but hashes differently and has to transfer normally (§4.1).

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
9. **Filename reuse (§4.1).** Transfer a document, let the pulje sync remove its `pool_files`
   row, then upload a *different* document that generates the same filename — a recurring
   vendor plus date, the NETS/META case SST-740 names. Confirm the second one transfers.
   This is the scenario the filename key got wrong, and it fails silently, so it needs to be
   tested deliberately rather than trusted.
10. **Same document, genuine re-click.** Re-upload the identical file and transfer it;
    confirm the hash match is detected and the existing line is offered.
11. **Row removed mid-flight (§4.4).** Open the transfer dialog, remove the file from the
    pulje folder and let a sync pass run, then confirm. Confirm the failure is clean and no
    journal line is written.

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
8. **§4.3** Are two byte-identical receipts from the same vendor on the same day one expense
   or two? A content hash cannot tell them apart, so this decides whether a hash match hard-
   blocks the transfer or is a warning the user can confirm past.

---

## 9. Implementation scope, to be estimated separately

Not part of this ticket's estimate; listed so the estimate has something to price.

| Piece | Rough shape |
|---|---|
| Checkbox and dialog in the pool UI | `includes/docsIncludes/docPool.php`, and `finans/pulje_review.php` if the mobile screen gets it too |
| Draft line creation | **Mostly reuse.** `insertDoc.php` already creates the line, allocates the voucher and writes the `documents` row (§1.1). The work is setting the two account sides, the employee and the VAT treatment instead of the `F`/`F`/0 placeholder |
| Payable account setting | `settings` table; no migration if an existing group is reused |
| Exactly-once check (§4) | One query against `documents`, plus a `content_hash` column and its idempotent migration in `includes/betweenUpdates.php`, plus hashing at transfer time |
| Transferred marker in the pool list | Depends on Q7 |
| `findtekst` ids for the dialog | Coordinate with open branches — PR #447 (`feature/udfoert-af`) holds 5151–5152, SST-769 holds 5153–5155 |

### 9.1 Estimate

Acceptance criterion 4 asks for one. This is mine and needs the assignee's confirmation, and
it holds only under the assumptions below.

| Piece | Days |
|---|---|
| Checkbox, dialog, labels via `findtekst()` | 1.0 |
| Transfer path — setting the two account sides, employee and VAT in place of the `F`/`F`/0 placeholder, reusing `insertDoc.php` (§1.1) | 1.0–1.5 |
| Exactly-once: `content_hash` column, migration, hashing, the check and its UI response (§4) | 1.0 |
| Permissions (§2.5) and the payable-account setting | 0.5 |
| Automated coverage and the eight Prodtest scenarios in §7 | 1.0 |
| **Total** | **4.5–5.0 dev-days** |

Assumptions, each of which moves the number if wrong:

- One expense per receipt. The mixed receipt in §6.5 is excluded; allowing it adds a split UI
  and turns the §4 hash match into a per-line question rather than a per-document one.
- One payable account, reusing an existing `settings` group — no new settings UI.
- `insertDoc.php` is reused as-is rather than refactored. If the accounting decisions force a
  second caller through it, add roughly a day.
- The §8 questions are answered before implementation starts. They are not sequencing detail:
  Q2 (offset account) and Q4 (VAT) determine what the transfer writes, so the 1.0–1.5 above
  cannot start without them.

Not included: any change to the extraction pipeline, and the mixed-receipt case.

---

## 10. Status

Draft. Blocked on the answers in §8 — now eight questions, not seven.

**No longer blocked on SST-740.** It merged as `e852d8c5` (PR #596) and has been reviewed;
§1.5 records what it shipped and §4 is rewritten because of it. That was the gap this
document was opened with, and it is closed.

An estimate is now given in §9.1, covering acceptance criterion 4.

Note also that SST-778 is recorded as duplicating **SD-679 (Done)**, and there is no
reference to SD-679 anywhere in the git history — so it has not been possible to establish
what that ticket delivered or whether it overlaps with this design.

Once agreed, this file becomes the reference for the implementation ticket, amended in the
same commit as any code that deviates from it — following
`doc/stripe/INTERFACE_CONTRACT.md`.
