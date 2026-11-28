# tools/guardrails

Checks for the mistakes reviewers kept correcting by hand. Each one fails with
the file, the line and what to do instead. The [rules and enforcement](#rules-and-enforcement)
catalog below lists the checks and review requirements; project conventions and
feedback are indexed in [doc/ai/MEMORY.md](../../doc/ai/MEMORY.md).

```bash
node tools/guardrails/check.mjs                      # your working tree against origin/master
node tools/guardrails/check.mjs --base origin/hml/shopify
node tools/guardrails/check.mjs --only php-syntax,js-syntax
node tools/guardrails/check.mjs --list
gh pr view 123 --json body -q .body | node tools/guardrails/check.mjs --pr-body -
gh pr view 123 --json body -q .body | node tools/guardrails/check.mjs --pr-body - --base origin/master   # with the UI recording check
```

CI runs the same command (`.github/workflows/guardrails.yml`). Only what the
change adds is judged, so legacy code that breaks a rule does not fail anyone
until they touch it.

`php-syntax` needs PHP 8.2 on `PATH`, or Docker (it then runs `php:8.2-cli`;
override with `GUARDRAILS_PHP_IMAGE`).

## When a finding is wrong

Put an exception on the offending line, or the line above it:

```php
// guardrails-allow migrations until 2026-12-31 by NTR: must run before the shop sync block below
```

It names the check, an expiry date, the initials of the person who approved it
and the reason. It stops working after the expiry date. Exceptions are for the
odd case; a check that needs them often is wrong and should be fixed instead.

When a reviewer explicitly waives a PR's screen recording, record that decision
as a visible line under `## Screen recording`, using the same exception format:

```text
guardrails-allow pr-recording until 2026-12-31 by NTR: reviewer explicitly waived the recording for this PR
```

The reviewer validates the named approver and reason. An expired or incomplete
waiver fails the recording check; a waiver inside an HTML comment does not count.
Use `Ingen UI-ændring` only when the change has no UI effect.

## Proving a check

A check must fail on a real past mistake. Judge any commit against its parent:

```bash
node tools/guardrails/check.mjs --base 6fad671b^ --head 6fad671b --only js-syntax
```

The commits each check was proven on are named at the top of its file in
`checks/`.

`file-version` uses PHP tokenization without executing the source. It ignores code
comments and formatting, while changes to string contents count as behavior. It
requires PHP on `PATH`, as provided by the guardrails CI job. `file-history` also
rejects history entries inserted after a leading PHPDoc instead of in the header.
`file-history` also ignores comment-only and formatting-only changes when requiring a history entry or header date/copyright update. It ignores PHP comments and whitespace when comparing tokens, while preserving string contents (including SQL and heredocs) and inline HTML. History-entry validation and continuation alignment still run, including when only comments or indentation changed.

The exemption retains existing dated entries (date/tag and duplicate counts), the header date line, and the copyright line. Descriptions may be corrected; header dates and copyright ending years cannot move backward, and the starting year is retained. Comment and formatting edits retain the dated header's path and version value, allowing legacy version labels to normalize to `ver`. A changed copyright line must name `Danosoft ApS`; untouched legacy metadata needs no update.

Both token comparisons require maintenance when either decoded revision contains `U+FFFD`: distinct LATIN9 source bytes can become the same Unicode replacement character before comparison. Such edits require history/header maintenance and the current application version.

An edited PHP header must have no uncommented blank lines anywhere from the
opening tag through its last comment. Use comment-only separators such as `//`.
Empty lines inside a block comment are already commented; blank separation
between the completed header and code or function PHPDoc remains allowed.
`include-paths` finds include and require statements with the same tokenizer, so
one in a comment, a string or an inline `<script>` is not judged.

Run the guardrail regression tests with `node --test tools/guardrails/tests/*.test.mjs`;
the guardrails workflow runs the same tests before checking a pull request.

The `whitespace` check allows blank separator lines, including spaces or tabs used
to indent new lines. Existing blank-line indentation may change alongside content
edits in the same diff hunk. Hunks that only rewrite trailing whitespace on
existing lines, including blank-line indentation, are rejected so trim-on-save
cannot create unrelated churn throughout a file. Trailing spaces/tabs after code
or comments and file mode changes are also rejected. Keep useful blank lines
between sections of code.

## Rules and enforcement

| Rule | Enforced by | More |
|---|---|---|
| Every PHP file a change touches parses under PHP 8.2 (production's version) | guardrails `php-syntax` | |
| JavaScript a change touches still parses, including JS inside PHP (heredocs, `<script>` blocks) | guardrails `js-syntax` | |
| `importfiler/tekster.csv` rows are `id<TAB>Dansk<TAB>English<TAB>Norsk`: numeric unique id, no CSV quoting, no empty language | guardrails `tekster` | |
| `importfiler/tekster_keyed.tsv` rows are `tekst_key<TAB>Dansk<TAB>English<TAB>Norsk`: valid unique sorted key, no TSV quoting, no empty language | guardrails `tekster` | [convention_findtekst_keys](../../doc/ai/convention_findtekst_keys.md) |
| `importfiler/egne_tekster_keyed.tsv` uses the same validated format and overrides matching standard keys; a new overlap purges the cached standard text with `deleteStaleTekst()`, while absent keys fall through | guardrails `tekster` | [convention_findtekst_keys](../../doc/ai/convention_findtekst_keys.md) |
| New wording for an existing text id or key: use a new id/key, or purge the cached rows with `deleteStaleTekst()` at the end of `includes/betweenUpdates.php` | guardrails `tekster` | `includes/opdat_func/deleteStaleTekst.php` |
| `findtekst('NNNN\|fallback')`: the fallback is id NNNN's text in one of the languages | guardrails `tekster` | |
| `findtekst('descriptive.key\|fallback')`: the key exists in a keyed TSV and the fallback matches the effective custom-or-standard row | guardrails `tekster` | [convention_findtekst_keys](../../doc/ai/convention_findtekst_keys.md) |
| Schema changes (DDL, `ensureTableAndColumns()`) go at the end of `includes/betweenUpdates.php`; reusable structure builders live in `includes/structure/`; `admin/opret.php` and `includes/opretfunc.php` may define the initial schema when creating a new regnskab | guardrails `migrations` | [convention_database_changes](../../doc/ai/convention_database_changes.md) |
| `includes/opdat_*.php` and `includes/betweenUpdates2.php` are not edited, except in a version cut | guardrails `migrations` | [feedback_protected_update_files](../../doc/ai/feedback_protected_update_files.md) |
| A new test lives where CI runs it: a `phpunit.xml` suite or `e2e/` | guardrails `test-discovery` | [tests/README.md](../../tests/README.md) |
| An edited PHP header has no uncommented blank lines; changes beyond comments and formatting require history below older entries and updated header date/copyright | guardrails `file-history` | [convention_file_history](../../doc/ai/convention_file_history.md) |
| New blank separators may contain indentation; no incidental whitespace-only rewrites, trailing whitespace after content, or file mode changes | guardrails `whitespace` | [feedback_whitespace_and_indentation](../../doc/ai/feedback_whitespace_and_indentation.md) |
| Role editor follows the user's chosen blues from the selected settings navigation and role row: accent blue column heading, the same pale blue module headings, uniform content including group actions/help; no zebra or selected-cell fill | review; navigation selection/accent tokens and one group-owned content background | `css/usersRoles.css` |
| Keep simple function calls on one line; when a call needs wrapping, put every argument on its own line | review | |
| The PR description says what changed and how it was verified | guardrails `pr-body` | [feedback_pre_pr_test_report](../../doc/ai/feedback_pre_pr_test_report.md) |
| A PR that changes what users see (styles, scripts, markup, texts, PHP that writes markup) has a screen recording of it working in its description, or the line `Ingen UI-ændring: <why>` | guardrails `pr-recording` | |
| Header-history continuations start a new sentence and align with the description after the date/tag | guardrails `file-history` | [convention_file_history](../../doc/ai/convention_file_history.md) |
| Bump `ver` on the header line for a behaviour change, not for formatting | guardrails `file-version` | [convention_file_history](../../doc/ai/convention_file_history.md) |
| An `include`/`require` a change writes is anchored on `__DIR__` (`__DIR__ . '/../includes/connect.php'`), not a bare relative path; moving or re-indenting a legacy one is not writing it | guardrails `include-paths` | [convention_include_paths](../../doc/ai/convention_include_paths.md) |
| Cast or escape every outside value in SQL; HTML/JS output escaped (`jsString()`); `returside` through `nav_sanitize_returside()` | review, CodeRabbit | [convention_sql_escaping](../../doc/ai/convention_sql_escaping.md) |
| SQL runs on Postgres, MySQL and MariaDB; `information_schema` queries filter on the tenant's schema | review, CodeRabbit | CI only runs Postgres |
| A fix reaches every copy of the code it fixes (grid copies, the several save paths) | review | |
| Shared runtime files use a trusted shared group and parent directories; migrate foreign-owned files as an administrator, never grant access to all users | PHPUnit `SharedFileTest` | [runtime permissions](../../doc/runtime-permissions.md) |
| Shared-runtime diagnostics go to the server log without invoking request error handlers; a fallback write must not abort journal deletion | PHPUnit `SharedFileTest` | [runtime permissions](../../doc/runtime-permissions.md) |
| A symlinked migration root must target private application storage; refuse world-writable or sticky targets before changing their ownership or permissions | PHPUnit `SharedFileTest` | [runtime permissions](../../doc/runtime-permissions.md) |
| Budget saves require both posted counts (`kontoantal`, `maanedantal`) before any includes or database writes. The grid posts as one `budget_grid` field, so a long chart of accounts stays under PHP's `max_input_vars` (1000); a grid post that lost its Gem to that limit is refused the same way | PHPUnit `BudgetSubmissionTest`, `BudgetManyAccountsSaveTest`; e2e `budget-many-accounts.spec.mjs` | |
| No PHP file calls `phpinfo()`: the whole tree is served, and the page shows the server's environment and configuration to whoever opens it | PHPUnit `PhpinfoExposureTest` | |
| Every delete of a debtor or creditor (`adresser`) asks `account_has_entries()` before the delete and its cleanup, so open posts and orders keep their owner; hiding Slet is not enough | PHPUnit `AccountDeletePathsTest`, `AccountDeleteKeepsEntriesTest` | `includes/stdFunc/accountHasEntries.php` |
| A creditor card's form (kreditor/kreditorkort.php, includes/kreditorkort.php) and the Sager customer card's (sager/kunder.php, saved by debitor/debkort_save.php) carry the version of the rows its fields were drawn from (`account_card_version_field($accountCard)`, never read again for the field), so a form drawn before a colleague's save cannot put the old values back. The field comes before any field the form repeats per row, as PHP keeps only the first `max_input_vars` (1000) fields of a post, and a Gem of an existing card without a version is refused. Its Gem compares and writes between `account_card_begin_save()`, whose transaction locks the account row before anything is read, and `account_card_end_save()` on every way out; the save opens no transaction of its own. A column a card's update writes goes in `account_card_columns()`. The debtor card (debitor/debitorkort.php) refuses a stale Gem with MB-82's `recordHash` (`debitorkortSaveIsStale()`, `debitor/debitorkortIncludes/staleSave.php`) instead: one of the two checks per card, never both | PHPUnit `AccountCardStaleSaveTest`, `AccountCardColumnsTest` (the Sager and creditor cards); the debtor card's MB-82 check `DebitorkortStaleSaveTest`, `DebitorkortStaleSaveWiringTest` | `includes/stdFunc/accountCardVersion.php` |
| A form that saves the positions of an order's lines (debitor/ordre.php, kreditor/ordre.php, finans/ordre.php, debitor/ret_genfakt.php) prints `order_lines_drawn()` before the lines, and its save locks the order row first in the transaction, then calls `order_lines_added_since()`, `order_line_new_position()` and `order_lines_keep_added_apart()`, so a form drawn before a colleague's save cannot give its line a position a line already has. A position the helper picks stays within the posnr column, which is smallint in regnskaber from Saldi's first versions | PHPUnit `OrderLinePositionPathsTest`, `OrderLinePositionsRaceTest` | `includes/stdFunc/orderLinePositions.php` |
| Mail through the regnskab's SMTP settings (stamdata, `adresser` art 'S'): a server in `felt_1` gets the login in `felt_2`/`felt_3`, `felt_4`'s encryption and the stamdata e-mail as sender; empty `felt_1` or `localhost` is Saldi's own relay, anonymous and without `felt_4`. A sender a change adds or rewrites calls `stamdata_smtp()` instead of its own copy of that block | shared `stamdata_smtp()`; PHPUnit `stamdataSmtp`, `PasswordRecoveryMailTest` | `includes/stdFunc/stamdataSmtp.php` |
| Ledger, posting and migration changes get Nicolai's review | review | |
| CodeRabbit findings are answered before a human is asked to review | review | |
| Braces on multi-line bodies; `@var` provenance blocks; controller/view split; PHPDoc shapes; keep `<?= 'text' ?>` tags | review | [doc/ai/MEMORY.md](../../doc/ai/MEMORY.md) |
