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
| `importfiler/egne_tekster_keyed.tsv` uses the same validated format and overrides matching standard keys; absent keys fall through | guardrails `tekster` | [convention_findtekst_keys](doc/ai/convention_findtekst_keys.md) |
| New wording for an existing text id or key: use a new id/key, or purge the cached rows with `deleteStaleTekst()` at the end of `includes/betweenUpdates.php` | guardrails `tekster` | `includes/opdat_func/deleteStaleTekst.php` |
| `findtekst('NNNN\|fallback')`: the fallback is id NNNN's text in one of the languages | guardrails `tekster` | |
| `findtekst('descriptive.key\|fallback')`: the key exists in a keyed TSV and the fallback matches the effective custom-or-standard row | guardrails `tekster` | [convention_findtekst_keys](doc/ai/convention_findtekst_keys.md) |
| Schema changes (DDL, `ensureTableAndColumns()`) go at the end of `includes/betweenUpdates.php`, never in pages or includes | guardrails `migrations` | [convention_database_changes](doc/ai/convention_database_changes.md) |
| `includes/opdat_*.php` and `includes/betweenUpdates2.php` are not edited, except in a version cut | guardrails `migrations` | [feedback_protected_update_files](doc/ai/feedback_protected_update_files.md) |
| A new test lives where CI runs it: a `phpunit.xml` suite or `e2e/` | guardrails `test-discovery` | [tests/README.md](tests/README.md) |
| An edited PHP file gets a history line below the older ones; the header date and copyright move along | guardrails `file-history` | [convention_file_history](doc/ai/convention_file_history.md) |
| No line-ending-only edits, no new trailing whitespace, no file mode changes | guardrails `whitespace` | [feedback_whitespace_and_indentation](doc/ai/feedback_whitespace_and_indentation.md) |
| The PR description says what changed and how it was verified | guardrails `pr-body` | [feedback_pre_pr_test_report](doc/ai/feedback_pre_pr_test_report.md) |
| Bump `ver` on the header line for a behaviour change, not for formatting | review | [convention_file_history](doc/ai/convention_file_history.md) |
| Cast or escape every outside value in SQL; HTML/JS output escaped (`jsString()`); `returside` through `nav_sanitize_returside()` | review, CodeRabbit | [convention_sql_escaping](doc/ai/convention_sql_escaping.md) |
| SQL runs on Postgres, MySQL and MariaDB; `information_schema` queries filter on the tenant's schema | review, CodeRabbit | CI only runs Postgres |
| A fix reaches every copy of the code it fixes (grid copies, the several save paths) | review | |
| Ledger, posting and migration changes get Nicolai's review | review | |
| CodeRabbit findings are answered before a human is asked to review | review | |
| Braces on multi-line bodies; `__DIR__` includes; `@var` provenance blocks; controller/view split; PHPDoc shapes; keep `<?= 'text' ?>` tags | review | [doc/ai/MEMORY.md](doc/ai/MEMORY.md) |

## When you are corrected

Fix the mistake, then add its rule to the table. If the rule is already there and only "review" enforces it, the correction is a repeat: in the same change, enforce it. Prefer making the mistake impossible to write (one owner, one way, delete the copy an agent would imitate); otherwise add a check in `tools/guardrails/checks/` and prove it fails on the commit that made the mistake. Remove a row once its mistake can no longer happen.
