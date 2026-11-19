# Envejsdørene (the one-way doors): review instructions

Owner: Nicolai. Saul reviews Nicolai's own PRs.

These instructions apply when `node tools/guardrails/check.mjs --station` names the station `envejsdorene`: any one-way row of the door map in `tools/guardrails/lib/doors.mjs`, or a yes to either of the first two door questions: does the change alter totals, VAT, invoice dates, or which accounts, dates or payments a financial report selects (question 1); does it change what is mailed or sent, or to whom (question 2). They are read from the base branch, never from the PR's own changes. CS is `doc/ai/CODING_STANDARDS.md`. This file says only what is particular to the one-way doors; it points at CS by section and does not repeat it.

## What failure looks like here

A one-way door leaves something a revert does not take back. Failure here is rarely a crash. It is a row, a number, a payment, a message or exposed data that is still there after the revert.

- **1a, the books** (`posting`, `stock`, `numbers`). Vouchers that do not balance, a reload that posts again, a copy of the changed function the fix never reached, or a test whose expected value came from running the code. #884 was reverted by #931; #831 deleted journal rows the bookkeeper had not finished. CS 3, CS 5.4, CS 7.1.
- **1b, payments and sending** (`payments`, `outbound`). A charge, a collection or a mail that happens twice, when the provider calls back twice or the run stops between the provider's answer and Saldi's own write. A key that reaches the browser (#768). An e-invoice whose output differs in fields the ticket did not name (#881, #885, #955). CS 1.3, CS 3.3, CS 9.5.
- **1c, the update chain** (`schema`). A step that changes rows on its second run, a login that fails on one engine (#927, #930), a lookup answered from another tenant's schema, or a step placed outside the end of `includes/betweenUpdates.php` and the two initial-schema owners, `admin/opret.php` and `includes/opretfunc.php`. CS 2.1, CS 2.2, CS 2.4, CS 2.5, and `doc/ai/convention_database_changes.md`.
- **1d, access and deletion** (`auth`, `destructive`, `deploy`, `secrets`). A denial test with no allowed partner, so a blanket denial passes; another tenant's id that returns data; a delete that reaches past its stated scope; a retention question nobody asked (#731 needed one before merge). CS 1.1, CS 1.3, CS 1.5, and `doc/ai/convention_no_hardcoded_secrets.md`.

## What Important means here

Important is anything that can leave behind a row, a number, a payment, a sent message or exposed data that a revert does not take back. A missing minimum proof for a matched row is Important (CS 4.3), and so is an Undo line that says only `revert` (CS 4.2). Naming and style are Nit at most. A real bug the diff did not add is Pre-existing: one line with a ticket proposal, and it never blocks (CS 8).

## Cap the nits

At most three Nits on a person's PR. None on an agent's PR: an agent fixes every nit, and each fix costs another review round. Fix mode never touches these hunks, whatever the finding (CS 4.2).

## Do not report

- What a guardrail already reports. Point at its output; `node tools/guardrails/check.mjs --list` names every check.
- Untouched code, and warnings master already has (CS 8).
- `includes/opdat_*.php` and `includes/betweenUpdates2.php`. The `migrations` guardrail owns them (`doc/ai/feedback_protected_update_files.md`).
- What the books should show. That is agreed before the code is written, in the expected-result table the PR body quotes; check that the tests assert that table, not whether the table is right.

## Always check

- The rows `node tools/guardrails/check.mjs --station --base <base>` lists, against Merge Danger: the door, the blast radius, and an Undo that names the rows, how to find what was written while the change was live, the counter-entry and who must be told (CS 4.1, CS 4.2).
- The minimum proof of every matched row, not only the first one (CS 4.3).
- Door questions 1 and 2. A "no" cites a file and line; anything else counts as yes, and a yes makes the change one-way even when no listed path changed (#917, #966).
- Every copy and caller of each changed function (CS 7.1).
- **1a:** the test asserts the rows written, with expected values from the agreed table, and fails on the base (CS 3.1, CS 5.1, CS 5.4).
- **1b:** test mode only. A repeated callback and a run stopped after the provider acted each end with exactly one effect, checked in the provider's state and in the stored payment state (CS 3.3, CS 9.5).
- **1c:** a rehearsal on Postgres and on MySQL or MariaDB that runs the step twice, interrupts it, runs two logins at once and logs in afterwards; a new tenant ends with the structure of an upgraded one (CS 2.4).
- **1d:** a denied case, an empty grant list and an allowed same-tenant case; for a deletion, the counts before and after on a disposable copy, and a retention line in Merge Danger (CS 1.1, CS 1.5).

## Evidence

Every finding names the file and the line in the head, the input that fails, what happens against what should happen, and the CS section or door-map row it rests on. A claim about behaviour cites a line of source, not a guess from a function's name. No citation, no finding. A finding lists every copy it applies to.

## Summary

Open with the count, in this form: `2 Important, 0 Nit, 1 Pre-existing`. Then one line per matched row: its proof present or missing. When there is nothing to fix, write `0 Important, 0 Nit, 0 Pre-existing: nothing to fix.` and stop.
