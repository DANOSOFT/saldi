# Fællesgryden (the shared pot): review instructions

Owners: Nicolai, with Saul for the kassekladde and print rows. The door map's Reviewer column names the reviewer per row.

These instructions apply when `node tools/guardrails/check.mjs --station` names the station `faellesgryden`: a care row of the door map in `tools/guardrails/lib/doors.mjs` other than `texts` and `ci` (order pages, kassekladde, shared includes, shop and REST API writes, the print and mail engine, the VAT and SAF-T read paths), or a yes to the third door question: does the change write to the database, or build SQL from request values, in a path no row lists? They are read from the base branch, never from the PR's own changes. CS is `doc/ai/CODING_STANDARDS.md`. This file says only what is particular to this station; it points at CS by section and does not repeat it.

## What failure looks like here

A revert takes the code back, but while it is live it reaches every tenant, or it feeds a one-way path later.

- **A door answer the diff contradicts.** An order-page change to totals, VAT or the invoice date reaches the books at the next invoice, so it is one-way (#917, #966). Say so as an Important finding: the PR belongs with the one-way doors.
- **One entry point fixed, others missed.** The same code runs from the order card, the lists, POS, bulk actions, print and PDF, and the API (CS 7.1).
- **Kassekladde.** What the bookkeeper typed is overwritten or dropped (#848).
- **Shared includes.** A file loaded on every page fails to parse under PHP 8.2, or adds a warning on some caller; every tenant sees it until the revert deploys (#855, #878).
- **A suite that skipped** and still showed green (#936; CS 5.6).
- **Shop and REST API writes.** Wrong rows from a shop need a clean-up in each tenant they reached (#906, #907).

## What Important means here

Important is a false door answer, an entry point the change reaches with no before-and-after, a skipped suite, a parse failure or a new warning on a caller, and a database write or SQL built from request values in a path no row lists (the third door question; CS 1.1). Naming and style are Nit at most. A real bug the diff did not add is Pre-existing and never blocks (CS 8).

## Cap the nits

At most three Nits on a person's PR. None on an agent's PR: an agent fixes every nit, and each fix costs another review round.

## Do not report

- What a guardrail already reports; point at its output.
- Code the diff does not add, Danish identifiers, legacy names, and legacy style outside the edited block (CS 8).
- Print layout as such. When what is mailed, or to whom, changes, the PR belongs with the one-way doors: report that instead.

## Always check

- The three door questions (does it change the books, what is sent or to whom, or write where no row lists): each "no" cites a file and line, and anything else counts as yes.
- Before and after on every entry point the change reaches, and the related PHPUnit suite green and not skipped: the door map's proof for these rows (CS 4.3, CS 5.6).
- Every copy and caller of each changed function, and one concern per PR (CS 7.1, CS 7.2).
- House style inside the edited block only (CS 6), and SQL that runs on all three engines (CS 2.1).
- For kassekladde, a check that the line the bookkeeper typed survives every action the change touches.

## Evidence

Every finding names the file and the line in the head, the input that fails, what happens against what should happen, and the CS section or door-map row it rests on. A claim about behaviour cites a line of source, not a guess from a function's name. No citation, no finding. A finding lists every copy it applies to.

## Summary

Open with the count, in this form: `2 Important, 0 Nit, 1 Pre-existing`. Then one line per entry point the change reaches: tested before and after, or not. When there is nothing to fix, write `0 Important, 0 Nit, 0 Pre-existing: nothing to fix.` and stop.
