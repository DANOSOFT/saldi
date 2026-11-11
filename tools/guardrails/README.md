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
| Schema changes (DDL, `ensureTableAndColumns()`) go at the end of `includes/betweenUpdates.php`; `admin/opret.php` and `includes/opretfunc.php` may define the initial schema when creating a new regnskab, until structure helper functions replace this setup | guardrails `migrations` | [convention_database_changes](../../doc/ai/convention_database_changes.md) |
| `includes/opdat_*.php` and `includes/betweenUpdates2.php` are not edited, except in a version cut | guardrails `migrations` | [feedback_protected_update_files](../../doc/ai/feedback_protected_update_files.md) |
| A new test lives where CI runs it: a `phpunit.xml` suite or `e2e/` | guardrails `test-discovery` | [tests/README.md](../../tests/README.md) |
| An edited PHP file gets a history line below the older ones; the header date and copyright move along | guardrails `file-history` | [convention_file_history](../../doc/ai/convention_file_history.md) |
| New blank separators may contain indentation; no incidental whitespace-only rewrites, trailing whitespace after content, or file mode changes | guardrails `whitespace` | [feedback_whitespace_and_indentation](../../doc/ai/feedback_whitespace_and_indentation.md) |
| Keep simple function calls on one line; when a call needs wrapping, put every argument on its own line | review | |
| The PR description says what changed and how it was verified | guardrails `pr-body` | [feedback_pre_pr_test_report](../../doc/ai/feedback_pre_pr_test_report.md) |
| Header-history continuations start a new sentence and align with the description after the date/tag | guardrails `file-history` | [convention_file_history](../../doc/ai/convention_file_history.md) |
| Bump `ver` on the header line for a behaviour change, not for formatting | guardrails `file-version` | [convention_file_history](../../doc/ai/convention_file_history.md) |
| Cast or escape every outside value in SQL; HTML/JS output escaped (`jsString()`); `returside` through `nav_sanitize_returside()` | review, CodeRabbit | [convention_sql_escaping](../../doc/ai/convention_sql_escaping.md) |
| SQL runs on Postgres, MySQL and MariaDB; `information_schema` queries filter on the tenant's schema | review, CodeRabbit | CI only runs Postgres |
| A fix reaches every copy of the code it fixes (grid copies, the several save paths) | review | |
| Shared runtime files use a trusted shared group and parent directories; migrate foreign-owned files as an administrator, never grant access to all users | PHPUnit `SharedFileTest` | [runtime permissions](../../doc/runtime-permissions.md) |
| Shared-runtime diagnostics go to the server log without invoking request error handlers; a fallback write must not abort journal deletion | PHPUnit `SharedFileTest` | [runtime permissions](../../doc/runtime-permissions.md) |
| A symlinked migration root must target private application storage; refuse world-writable or sticky targets before changing their ownership or permissions | PHPUnit `SharedFileTest` | [runtime permissions](../../doc/runtime-permissions.md) |
| Budget saves require both posted counts (`kontoantal`, `maanedantal`) before any includes or database writes | PHPUnit `BudgetSubmissionTest` | |
| Ledger, posting and migration changes get Nicolai's review | review | |
| CodeRabbit findings are answered before a human is asked to review | review | |
| Braces on multi-line bodies; `__DIR__` includes; `@var` provenance blocks; controller/view split; PHPDoc shapes; keep `<?= 'text' ?>` tags | review | [doc/ai/MEMORY.md](../../doc/ai/MEMORY.md) |
