# Skærm og tekst (screen and text): review instructions

Owner: Saul. Nicolai reviews Saul's own PRs.

These instructions apply when `node tools/guardrails/check.mjs --station` names the station `skaerm-og-tekst`: pages, CSS, JavaScript, reports, print layouts, tests, docs and the text rows that no other row of the door map in `tools/guardrails/lib/doors.mjs` claims. They are read from the base branch, never from the PR's own changes. CS is `doc/ai/CODING_STANDARDS.md`. This file says only what is particular to this station; it points at CS by section and does not repeat it.

## What failure looks like here

Many small changes, quick to redo. Failure is what the bookkeeper sees, or a screen change that turns out to do more than change the screen.

- **A hidden write.** A change that claims to be screen-only writes to the database, or puts a request value into SQL. It belongs to Fællesgryden.
- **A report that selects differently.** A financial report now picks other accounts, dates or payments. That touches the books, and the PR belongs with the one-way doors.
- **A PHP warning the change adds**, on any page it touches (#780; CS 6).
- **Texts.** A text in one language only, or new wording under an id that tenants have already cached (SST-871; `doc/ai/convention_findtekst_keys.md`).
- **A removed `<?= 'text' ?>` placeholder** (`doc/ai/feedback_php_echo_translations.md`).
- **Customer data on screen.** A screenshot or fixture taken from a customer's books instead of a test tenant (CS 1.3).

## What Important means here

Important is a hidden write or SQL from request values, a report that selects other rows, a new warning, a missing language, a removed placeholder, and customer data in a screenshot or fixture. Look and feel is not a finding here: the person who owns the UI or the copy answers that on an image. Naming and style are Nit at most. A real bug the diff did not add is Pre-existing and never blocks (CS 8).

## Cap the nits

At most five Nits on a person's PR. None on an agent's PR: an agent fixes every nit, and each fix costs another review round.

## Do not report

- UI feel, layout taste and wording choices (CS 8).
- What the `tekster` guardrail reports about `importfiler/tekster_keyed.tsv` and the other text files.
- Generated and vendored files.
- In a PR that only changes tests or docs, anything but a test that cannot fail (CS 5.1, CS 5.2) or a doc that is false about the code (CS 9.2).

## Always check

- Before and after in the browser on a local test tenant, with PHP warnings visible: the door map's proof for this station.
- Every new user-visible text goes through a descriptive key, in all three languages (CS 6; `doc/ai/convention_findtekst_keys.md`).
- Printed values are escaped for where they land, and nothing loads external CSS or JavaScript (CS 1.2, CS 1.4).
- Braces and includes inside the edited block (CS 6; `doc/ai/convention_curly_braces.md`, `doc/ai/convention_include_paths.md`).
- Warnings are fixed, not hidden (`doc/ai/convention_testing_error_visibility.md`).

## Evidence

Every finding names the file and the line in the head, the input that fails, what happens against what should happen, and the CS section or door-map row it rests on. A claim about behaviour cites a line of source, not a guess from a function's name. No citation, no finding.

## Summary

Open with the count, in this form: `2 Important, 0 Nit, 1 Pre-existing`. When there is nothing to fix, write `0 Important, 0 Nit, 0 Pre-existing: nothing to fix.` and stop.
