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
node tools/guardrails/check.mjs --station                 # station, reviewer and proof for this change
```

CI runs the same command (`.github/workflows/guardrails.yml`). Only what the
change adds is judged, so legacy code that breaks a rule does not fail anyone
until they touch it.

`php-syntax` needs PHP 8.2 on `PATH`, or Docker (it then runs `php:8.2-cli`;
override with `GUARDRAILS_PHP_IMAGE`).

## The door map and `--station`

`lib/doors.mjs` is the one home for the door map: the one-way rows (a revert
does not take back what they leave behind: ledger rows, an issued number, a
schema or data step, money, a mail or shop update, deleted data, data shown to
the wrong tenant), the care rows, and the gate paths, each with its path
patterns, code markers, reviewer and minimum proof. The map is a floor: no
match means "no detected one-way match", never "two-way".

`--station` routes the change by it and never fails:

```bash
node tools/guardrails/check.mjs --station                         # working tree vs origin/master
node tools/guardrails/check.mjs --station --base origin/hml/shopify
node tools/guardrails/check.mjs --station --base 527579cc^ --head 527579cc
printf '%s\n' finans/kassekladde.php | node tools/guardrails/check.mjs --station --json --paths -
```

It prints the station, the party, the human at the pass, the taster tier, the
minimum proof, and every row, marker and gate path that matched. The first
path rule that matches owns the change (one-way row, Fællesgryden's care row,
gate path, anything else); every match adds its proof and its tier.
Each proof names the end-to-end run it asks for (`e2e`, strictest first: `pr`,
`pr-or-flow`, `flow`, `where-available`, `none`; the row fields in
`lib/doors.mjs` say what each means).
`--json` prints one `saldi.station.v1` object and nothing else, exit 0, or a
`{"schema", "error"}` object and exit 2 when it cannot route (a bad argument, an
unknown ref, a map that does not load, or no change between `--base` and
`--head`, as for two unrelated histories). A flag followed by another flag has
no value (`--base --json` is a bad argument); give a value that starts with
`--` as `--base=--value`. A plate that no match gives a tier (a care row whose
tier is null, alone) takes its station's own tier. `--paths` reads
candidate paths, one per line, instead of a diff, so markers do not count.
Each is matched as the repository path it names (`./finans/bogfor.php` is
`finans/bogfor.php`); a path that leaves the repository is refused.
Run the rules of the branch you are judged against, not your own: a change
must not loosen the map it is routed by.

`--station` and the door cross-check in `--pr-body` read PHP files with PHP's
own tokenizer (`lib/php.mjs`: `token_get_all` through the same PHP 8.2 on
`PATH`, or Docker, as `php-syntax`; one PHP process for the whole change).
Where PHP cannot run, every line of a PHP file that names a password counts,
references too, no PHP comment is passed over, and the report says why.
A piece of PHP outside HTML's data state (in a tag or an attribute, a comment,
the text of a raw text element such as `style`, `textarea` or `title`, or a
script's start tag or code) leaves HTML's state after it unknown, because what
it prints may finish a delimiter (`lib/doors.mjs` `htmlCodes`). From that piece
on, every line of the block that names a password counts, references too. In
HTML's data state a piece of PHP is text, and the inline HTML around it is also
read as JavaScript, so a script PHP itself opens is still read. A piece of PHP
inside an identifier or a string (`getPass<?php ?>word`, `'a<?= $x ?>b'`), or
in the middle of a password name, leaves the state unknown too, and a line names
a password where its text with its pieces of PHP taken out does. A piece of
PHP that a name's text touches on either side (`getPass<?= 'word' ?>`,
`get<?= $x ?>`, `name="row<?= $i ?>"`) leaves that name unresolved, whatever
the text holds (any character an identifier holds, or an escape's): what the
piece prints is never read, its lines count as naming a password, and the state
is unknown from there (`lib/doors.mjs` `edgeNames`). A piece with a space, a
quote, markup or an operator beside it is text, as before.

A changed line is read on over the unchanged lines below it, through the end of
the declaration or the statement it opens (`lib/changeset.mjs`
`addedWithContext` hands them to `--station` and to the door cross-check), and a
hit is the changed line's. An entry marked `cut`, or a gap in the line numbers,
is not the end of the file: a value still open there counts.

The changed paths are git's own, read exactly: the list comes NUL-separated
(`git diff --name-only -z`), and a diff header git quotes (a name with a quote,
a backslash, a tab or another control character) is unquoted the way git quoted
it (`lib/changeset.mjs` `headerPath`). A file whose header cannot be read is
never routed under a guessed path: it is read whole under the path git lists,
every line as changed, so a marker anywhere in it counts, and the output says so.

## The door in the PR description

`--pr-body` also reads the `## Merge Danger` section: `Door:` is `one-way` or
`two-way` (alone, or followed by a space and why), and `After merge:` is `none`
or each step and who takes it. When the base exists, a `two-way` door is held
against the change, and each file a one-way row claims (by path, or by a marker
on a changed line that is not a comment; a removed line is read in the file it
was removed from) is a finding. Care rows never fail, and `one-way` always
passes. CI's description job checks out the PR's history and passes its base
and head; a sync PR passes `--text-only`, and in CI a cross-check that cannot
run is itself a finding. Judge a past PR the same way:

```bash
gh pr view 884 --json body -q .body | node tools/guardrails/check.mjs --pr-body - --base cce4a13a^ --head cce4a13a
```

CI judges the description with the base revision's `tools/guardrails` (the
checker, the door map and `REVIEWERS`), unpacked from the base commit and run
against the PR's checkout, so a PR that loosens the map or the check is held to
the rules it goes into. `check.mjs` judges the repository it runs in, not the
one it lives in. Until the base has a door map (`lib/doors.mjs`), the PR's own
checker runs, and the job log says so.

A wrong finding is let through by the reviewer with a visible line in the
Merge Danger section that names the commit they approved (the PR's head when
they looked) and, in its reason, the file or the row:

```text
guardrails-allow pr-body until 2026-12-31 by NTR at 1a2b3c4d5e6f: debitor/betalinger.php removes a broken menu option
```

A line that names the file lets all of that file's rows through; one that names
a row (`payments`, `posting`, ...) lets only that row through, so a file that
another row also claims still fails on that row. A name counts whole:
`bogfor.php` does not name `finans/bogfor.php`, and `debitor/payments/` does not
name the `payments` row.

A body edit dismisses no approval, so the line alone proves nothing. It counts
only when the description's edit history shows that a reviewer in `REVIEWERS`
(`lib/doors.mjs`) put it into the active Merge Danger section (a reviewer's line
elsewhere that the author moves there is the author's), signed with their own
initials, on a PR they did not author. CI saves the history with `gh api
graphql` (the query is in `.github/workflows/guardrails.yml`) and passes it as
`--pr-edits`; without it, or when the history cannot tell who added the line,
the exception does not count and the finding says why. If the author edits the
line afterwards, it is theirs again.

The line also counts only while the file is as it was at the commit it names
(`git diff <sha> <head> -- <file>` shows no change). A later push that changes
the file brings the finding back, and the message asks for a new line at the
PR's current head. A line without `at <sha>`, or with a commit this checkout's
history does not hold (after a force-push), does not count.

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
to indent those lines. It rejects trailing spaces/tabs after code or comments,
incidental whitespace-only edits to nonblank lines, and file mode changes. Keep
useful blank lines between sections of code.

## Rules and enforcement

| Rule | Enforced by | More |
|---|---|---|
| Every PHP file a change touches parses under PHP 8.2 (production's version) | guardrails `php-syntax` | |
| JavaScript a change touches still parses, including JS inside PHP (heredocs, `<script>` blocks) | guardrails `js-syntax` | |
| `importfiler/tekster.csv` rows are `id<TAB>Dansk<TAB>English<TAB>Norsk`: numeric unique id, no CSV quoting, no empty language | guardrails `tekster` | |
| No new numeric ids in `importfiler/tekster.csv`: a new text takes a descriptive key in `importfiler/tekster_keyed.tsv` | guardrails `tekster` | [convention_findtekst_keys](../../doc/ai/convention_findtekst_keys.md) |
| `importfiler/tekster_keyed.tsv` rows are `tekst_key<TAB>Dansk<TAB>English<TAB>Norsk`: valid unique sorted key, no TSV quoting, no empty language | guardrails `tekster` | [convention_findtekst_keys](../../doc/ai/convention_findtekst_keys.md) |
| `importfiler/egne_tekster_keyed.tsv` uses the same validated format and overrides matching standard keys; a new overlap purges the cached standard text with `deleteStaleTekst()`, while absent keys fall through | guardrails `tekster` | [convention_findtekst_keys](../../doc/ai/convention_findtekst_keys.md) |
| New wording for an existing text id or key: use a new descriptive key, or purge the cached rows with `deleteStaleTekst()` at the end of `includes/betweenUpdates.php` | guardrails `tekster` | `includes/opdat_func/deleteStaleTekst.php` |
| `findtekst('NNNN\|fallback')`: the fallback is id NNNN's text in one of the languages | guardrails `tekster` | |
| `findtekst('descriptive.key\|fallback')`: the key exists in a keyed TSV and the fallback matches the effective custom-or-standard row | guardrails `tekster` | [convention_findtekst_keys](../../doc/ai/convention_findtekst_keys.md) |
| Schema changes (DDL, `ensureTableAndColumns()`) go at the end of `includes/betweenUpdates.php`; `admin/opret.php` and `includes/opretfunc.php` may define the initial schema when creating a new regnskab, until structure helper functions replace this setup | guardrails `migrations` | [convention_database_changes](../../doc/ai/convention_database_changes.md) |
| `includes/opdat_*.php` and `includes/betweenUpdates2.php` are not edited, except in a version cut | guardrails `migrations` | [feedback_protected_update_files](../../doc/ai/feedback_protected_update_files.md) |
| A new test lives where CI runs it: a `phpunit.xml` suite or `e2e/` | guardrails `test-discovery` | [tests/README.md](../../tests/README.md) |
| An edited PHP file gets a history line below the older ones; the header date and copyright move along | guardrails `file-history` | [convention_file_history](../../doc/ai/convention_file_history.md) |
| Blank separators may contain indentation; no trailing whitespace after content, line-ending-only content edits, or file mode changes | guardrails `whitespace` | [feedback_whitespace_and_indentation](../../doc/ai/feedback_whitespace_and_indentation.md) |
| Keep simple function calls on one line; when a call needs wrapping, put every argument on its own line | review | |
| The PR description says what changed and how it was verified | guardrails `pr-body` | [feedback_pre_pr_test_report](../../doc/ai/feedback_pre_pr_test_report.md) |
| The PR description has a `## Merge Danger` section with `Door:` (exactly `one-way` or `two-way`, then optionally a space and why) and `After merge:` (`none`, or each step and who takes it) | guardrails `pr-body` | [lib/doors.mjs](lib/doors.mjs) |
| A `Door: two-way` PR touches no one-way path or marker of the door map; care rows never fail, and CI judges the door against the PR's base and head with the base revision's checker and map | guardrails `pr-body` | [lib/doors.mjs](lib/doors.mjs) |
| A wrong door finding is let through only by a `guardrails-allow pr-body until YYYY-MM-DD by <INITIALS> at <sha>: <file or row> <why>` line that the description's edit history shows a reviewer put into Merge Danger, signed with their own initials, on a PR they did not author; it covers the rows it names, while the file is unchanged since `<sha>` | guardrails `pr-body` (CI reads the edit history) | [The door in the PR description](#the-door-in-the-pr-description) |
| The door map names each change's station, human at the pass, taster tier and minimum proof, by the first path rule that matches | `check.mjs --station` (prints, never fails) | [The door map and `--station`](#the-door-map-and---station) |
| A door-map marker reads quotes the way the file's language does and runs in linear time on a long line | guardrails tests (`tools/guardrails/tests/station.test.mjs` and `doors-*.test.mjs`, in CI) | [lib/doors.mjs](lib/doors.mjs) |
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
