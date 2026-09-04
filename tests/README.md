# Tests

- `characterization/` - PHPUnit characterization suites for legacy engine
  code (`*.test.php`, see the READMEs inside).
- `restapi/` - PHPUnit suite for the REST API (`*Test.php`, see
  [restapi/README.md](restapi/README.md)).
- `test_*.php` - older stand-alone scripts run directly with `php`.

Run everything with `composer test` (PHPUnit discovery via `phpunit.xml`).

## Passwords for seeded accounts: `tests/TestCredentials.php`

Suites that create the account they log in with take its password from
`TestCredentials::password('<key>')`. That file is tracked and contains no
secret: each password is generated at random once per PHP process and only
exists in the throwaway data the suite seeds and drops again. There is
nothing to set up on a checkout, and a run that dies half-way leaves behind a
password nobody knows.

To log in by hand while a suite keeps its data (`SALDI_REST_KEEP_TENANT=1`
for `restapi/`), set the override in the environment for that run:

```bash
SALDI_TEST_PASSWORD_RESTAPI=... SALDI_REST_KEEP_TENANT=1 php vendor/bin/phpunit --testsuite restapi
```

A suite that seeds an account must remove it again in `tearDownAfterClass()`
(see `RestApiEnv::teardownTenant()`).

Anything a test needs that grants access to something real - a third-party
API key, a live account - is read from an environment variable and the test
skips when it is unset. Never write such a value into a tracked file (see
`doc/ai/convention_no_hardcoded_secrets.md`). Database connection details
for the local stack come from env vars or the gitignored
`includes/connect.php`, as documented per suite.

<!-- 20260904 CL/NTR created. -->
