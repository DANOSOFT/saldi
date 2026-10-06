---
name: convention_no_hardcoded_secrets
description: "Credentials and secrets are never literals in tracked files - they live only in gitignored files, the environment, or the settings table"
metadata:
  node_type: memory
  type: project
  originSessionId: 2026-08-26-no-hardcoded-secrets
---

No credential or secret may appear as a literal in any file tracked by git. That covers passwords, API keys, bearer/OAuth tokens, webhook/MD5 secrets, private keys and certificates - in PHP, JS, SQL, tests, docs, Docker/compose files, and **comments** (a commented-out `#$md5secret = '...'` is still a leaked secret). Hostnames, database names and usernames are configuration and may be literal; the thing that grants access may not.

Secrets live in exactly these places, and code reads them from there at runtime:

- **`includes/connect.php`** (gitignored under `# Secrets / local config` in `.gitignore`) - the DB host/user/password for the installation. Every page already gets `$sqhost`, `$squser`, `$sqpass` from it.
- **Environment variables** - `getenv('...')`, as `includes/stripeIncludes/stripeSettings.php` does with `SALDI_STRIPE_DB`, and as the docker-compose stack does for SMTP via the gitignored `.env` (`.env.example` is tracked and holds placeholders only).
- **The tenant `settings` table** - per-account third-party credentials (e.g. `paperflowBearer`, `rentalpayment.apikey`), edited by the user in the UI, never seeded from code.
- **`certs/`** (gitignored) for key material.
- **Nowhere, for accounts a test seeds itself** - `tests/TestCredentials.php` (tracked, no secret inside) hands each suite a password generated at random per process (`TestCredentials::password('restapi')`), overridable per run through `SALDI_TEST_PASSWORD_<KEY>` for logging in by hand. See `tests/README.md`.

**Why:** The repository is public on GitHub, so anything committed is compromised the moment it is pushed - deleting it in a later commit does not remove it from history, and the only fix is rotating the secret. On 2026-08-26 a characterization test was found carrying a literal Postgres password with a comment claiming it copied "includes/connect.php's checked-in dev defaults" - but connect.php is not checked in, contained no such value, and the password was wrong for the developer's machine. It could never have worked, it violated policy, and it referenced a sibling suite that did not exist. Hardcoded "dev defaults" are how real secrets end up in history.

Tightened 2026-09-04: the original rule exempted "a throwaway test user seeded by the test itself". That exemption was dropped because the REST suite registers its seeded user on whatever server it runs against - a forgotten clone on a shared server would be a login anyone reading GitHub could use. The old literal (`RestApiEnv::PASSWORD`) is in git history and is therefore treated as public; the seeded user's password is now random per run.

**How to apply:**

- **Application code:** never assign a secret literal. Take DB credentials from the `connect.php` globals, third-party keys from the `settings` table or `getenv()`. If a feature needs a new secret, add a `settings` row or an env var and document the variable name in `INSTALLATION.md` / `.env.example` with a placeholder value.
- **Before adding a test that needs to log in:** read `tests/README.md` first. It explains the per-process random password (`TestCredentials::password()`) for self-seeded accounts and the env-var route for real credentials; do not point the user at a literal instead.
- **Tests:** read connection details from env vars (the `SALDI_CHAR_PGHOST` / `SALDI_CHAR_PGUSER` / `SALDI_CHAR_PGPASS` names used by `tests/restapi/support/RestApiEnv.php`) or from the gitignored `includes/connect.php`, and `markTestSkipped()` when neither yields a working connection. Never fall back to a literal password so the test "works out of the box". This also covers users the test seeds itself: a password for a throwaway test user is still a password, so it is never a literal either. Take it from `TestCredentials::password('<suite key>')` in the tracked `tests/TestCredentials.php`: random per process, kept only in memory, overridable per run through `SALDI_TEST_PASSWORD_<KEY>` when a human needs to log in with it. Nothing to set up, and a run that dies half-way leaves behind a password nobody knows. Real credentials for something outside the test's own throwaway data (a third-party API key, a live account) come from an environment variable, and the test skips when it is unset. A test that seeds an account must also remove it: drop the throwaway database / delete the rows in `tearDownAfterClass()` (pattern: `RestApiEnv::teardownTenant()`), optionally with an env opt-out for debugging. Non-secret fixtures (tenant DB names, account names, usernames, e-mail addresses) may stay literal.
- **Docs and examples:** placeholders only (`SMTP_PASS=your-app-password`), never a value that has ever been real.
- **Commented-out code:** if it holds a secret, delete the line - don't leave it commented.
- **Existing violations:** when you notice a secret already in a tracked file, do not silently rip it out or "fix" it in passing - removing it changes runtime behaviour (the code still needs the value from somewhere) and the secret must be rotated anyway. Flag it to the team with file and line so it can be moved to a gitignored home and rotated deliberately.
- **Review gate:** before opening a PR, grep the diff for `password|pass|apikey|api_key|secret|token|bearer` assigned to a quoted literal. Anything that matches must be a placeholder or a non-secret.
