# tools/guardrails

Checks for the mistakes reviewers kept correcting by hand. Each one fails with
the file, the line and what to do instead. AGENTS.md lists every rule and what
enforces it.

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
