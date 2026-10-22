# Pre-PR test report (HML / Shopify)

Paste this into the PR description, filled in, before asking for review.

**Ticket:** SD-xxx (K rows: ...)

## What changed
- Files and functions touched, one line each.
- Legacy core edit? Name the core edit number (plan 2.3) or write "none".

## Tests
- [ ] `php vendor/bin/phpunit --testsuite shopify` green locally (paste the summary line).
- [ ] Characterization tests written **before** any legacy core edit, and still green after it.
- [ ] POS-touching PR: `tests/test_pos_cash_checkout.php` run and green.
- [ ] Sandbox script `tests/shopify/sandbox/<K>.php` run against the sandboxes, or SKIP with the reason.

## Manual test scenarios
For each affected function/page: the steps you ran and what you saw.

## Data and safety
- [ ] Only synthetic data on `saldi_shopify_test_<initials>`; never pos_50 or test_12.
- [ ] Schema changes only through the WP01 manifest (`includes/shopify/schema.php`).
- [ ] New behaviour is behind a flag that is off by default.
- [ ] No secrets in code, tests, logs or this PR.
