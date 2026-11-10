# Agent Instructions

Before making changes in this repo, read [doc/ai/MEMORY.md](doc/ai/MEMORY.md) and follow the links inside it. Those files record project conventions and past feedback from the team that should guide how you work here.

Before you push, run `node tools/guardrails/check.mjs`. CI runs the same command on every pull request ([.github/workflows/guardrails.yml](.github/workflows/guardrails.yml)); see [tools/guardrails/README.md](tools/guardrails/README.md).

## Rules and what enforces them

| Rule | Enforced by | More |
|---|---|---|
| Every PHP file a change touches parses under PHP 8.2 (production's version) | guardrails `php-syntax` | |
| JavaScript a change touches still parses, including JS inside PHP (heredocs, `<script>` blocks) | guardrails `js-syntax` | |
| `importfiler/tekster.csv` rows are `id<TAB>Dansk<TAB>English<TAB>Norsk`: numeric unique id, no CSV quoting, no empty language | guardrails `tekster` | |
| `importfiler/tekster_keyed.tsv` rows are `tekst_key<TAB>Dansk<TAB>English<TAB>Norsk`: valid unique sorted key, no TSV quoting, no empty language | guardrails `tekster` | [convention_findtekst_keys](doc/ai/convention_findtekst_keys.md) |
| `importfiler/egne_tekster_keyed.tsv` uses the same validated format and overrides matching standard keys; a new overlap purges the cached standard text with `deleteStaleTekst()`, while absent keys fall through | guardrails `tekster` | [convention_findtekst_keys](doc/ai/convention_findtekst_keys.md) |
| New wording for an existing text id or key: use a new id/key, or purge the cached rows with `deleteStaleTekst()` at the end of `includes/betweenUpdates.php` | guardrails `tekster` | `includes/opdat_func/deleteStaleTekst.php` |
| `findtekst('NNNN\|fallback')`: the fallback is id NNNN's text in one of the languages | guardrails `tekster` | |
| `findtekst('descriptive.key\|fallback')`: the key exists in a keyed TSV and the fallback matches the effective custom-or-standard row | guardrails `tekster` | [convention_findtekst_keys](doc/ai/convention_findtekst_keys.md) |
| Schema changes (DDL, `ensureTableAndColumns()`) go at the end of `includes/betweenUpdates.php`; `admin/opret.php` and `includes/opretfunc.php` may define the initial schema when creating a new regnskab, until structure helper functions replace this setup | guardrails `migrations` | [convention_database_changes](doc/ai/convention_database_changes.md) |
| `includes/opdat_*.php` and `includes/betweenUpdates2.php` are not edited, except in a version cut | guardrails `migrations` | [feedback_protected_update_files](doc/ai/feedback_protected_update_files.md) |
| A new test lives where CI runs it: a `phpunit.xml` suite or `e2e/` | guardrails `test-discovery` | [tests/README.md](tests/README.md) |
| An edited PHP file gets a history line below the older ones; the header date and copyright move along | guardrails `file-history` | [convention_file_history](doc/ai/convention_file_history.md) |
| No line-ending-only edits, no new trailing whitespace, no file mode changes | guardrails `whitespace` | [feedback_whitespace_and_indentation](doc/ai/feedback_whitespace_and_indentation.md) |
| The PR description says what changed and how it was verified | guardrails `pr-body` | [feedback_pre_pr_test_report](doc/ai/feedback_pre_pr_test_report.md) |
| Header-history continuations start a new sentence and align with the description after the date/tag | guardrails `file-history` | [convention_file_history](doc/ai/convention_file_history.md) |
| Bump `ver` on the header line for a behaviour change, not for formatting | guardrails `file-version` | [convention_file_history](doc/ai/convention_file_history.md) |
| An `include`/`require` a change writes is anchored on `__DIR__` (`__DIR__ . '/../includes/connect.php'`), not a bare relative path; moving or re-indenting a legacy one is not writing it | guardrails `include-paths` | [convention_include_paths](doc/ai/convention_include_paths.md) |
| Cast or escape every outside value in SQL; HTML/JS output escaped (`jsString()`); `returside` through `nav_sanitize_returside()` | review, CodeRabbit | [convention_sql_escaping](doc/ai/convention_sql_escaping.md) |
| SQL runs on Postgres, MySQL and MariaDB; `information_schema` queries filter on the tenant's schema | review, CodeRabbit | CI only runs Postgres |
| A fix reaches every copy of the code it fixes (grid copies, the several save paths) | review | |
| Shared runtime files use a trusted shared group and parent directories; migrate foreign-owned files as an administrator, never grant access to all users | PHPUnit `SharedFileTest` | [runtime permissions](doc/runtime-permissions.md) |
| Shared-runtime diagnostics go to the server log without invoking request error handlers; a fallback write must not abort journal deletion | PHPUnit `SharedFileTest` | [runtime permissions](doc/runtime-permissions.md) |
| A symlinked migration root must target private application storage; refuse world-writable or sticky targets before changing their ownership or permissions | PHPUnit `SharedFileTest` | [runtime permissions](doc/runtime-permissions.md) |
| Budget saves require both posted counts (`kontoantal`, `maanedantal`) before any includes or database writes. The grid posts as one `budget_grid` field, so a long chart of accounts stays under PHP's `max_input_vars` (1000); a grid post that lost its Gem to that limit is refused the same way | PHPUnit `BudgetSubmissionTest`, `BudgetManyAccountsSaveTest`; e2e `budget-many-accounts.spec.mjs` | |
| No PHP file calls `phpinfo()`: the whole tree is served, and the page shows the server's environment and configuration to whoever opens it | PHPUnit `PhpinfoExposureTest` | |
| Every delete of a debtor or creditor (`adresser`) asks `account_has_entries()` before the delete and its cleanup, so open posts and orders keep their owner; hiding Slet is not enough | PHPUnit `AccountDeletePathsTest`, `AccountDeleteKeepsEntriesTest` | `includes/stdFunc/accountHasEntries.php` |
| A debtor or creditor card's form carries the version of the rows its fields were drawn from (`account_card_version_field($accountCard)`, never read again for the field), so a form drawn before a colleague's save cannot put the old values back. The field comes before any field the form repeats per row, as PHP keeps only the first `max_input_vars` (1000) fields of a post, and a Gem of an existing card without a version is refused. Its Gem compares and writes between `account_card_begin_save()`, whose transaction locks the account row before anything is read, and `account_card_end_save()` on every way out; the save opens no transaction of its own. A column a card's update writes goes in `account_card_columns()` | PHPUnit `AccountCardStaleSaveTest`, `AccountCardColumnsTest` | `includes/stdFunc/accountCardVersion.php` |
| Mail through the regnskab's SMTP settings (stamdata, `adresser` art 'S'): a server in `felt_1` gets the login in `felt_2`/`felt_3`, `felt_4`'s encryption and the stamdata e-mail as sender; empty `felt_1` or `localhost` is Saldi's own relay, anonymous and without `felt_4`. A sender a change adds or rewrites calls `stamdata_smtp()` instead of its own copy of that block | shared `stamdata_smtp()`; PHPUnit `stamdataSmtp`, `PasswordRecoveryMailTest` | `includes/stdFunc/stamdataSmtp.php` |
| Ledger, posting and migration changes get Nicolai's review | review | |
| CodeRabbit findings are answered before a human is asked to review | review | |
| Braces on multi-line bodies; `@var` provenance blocks; controller/view split; PHPDoc shapes; keep `<?= 'text' ?>` tags | review | [doc/ai/MEMORY.md](doc/ai/MEMORY.md) |

## When you are corrected

Fix the mistake, then add its rule to the table. If the rule is already there and only "review" enforces it, the correction is a repeat: in the same change, enforce it. Prefer making the mistake impossible to write (one owner, one way, delete the copy an agent would imitate); otherwise add a check in `tools/guardrails/checks/` and prove it fails on the commit that made the mistake. Remove a row once its mistake can no longer happen.
