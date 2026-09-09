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

**Why:** The repository is public on GitHub, so anything committed is compromised the moment it is pushed - deleting it in a later commit does not remove it from history, and the only fix is rotating the secret. On 2026-08-26 a characterization test was found carrying a literal Postgres password with a comment claiming it copied "includes/connect.php's checked-in dev defaults" - but connect.php is not checked in, contained no such value, and the password was wrong for the developer's machine. It could never have worked, it violated policy, and it referenced a sibling suite that did not exist. Hardcoded "dev defaults" are how real secrets end up in history.

**How to apply:**

- **Application code:** never assign a secret literal. Take DB credentials from the `connect.php` globals, third-party keys from the `settings` table or `getenv()`. If a feature needs a new secret, add a `settings` row or an env var and document the variable name in `INSTALLATION.md` / `.env.example` with a placeholder value.
- **Tests:** read connection details from env vars (the `SALDI_CHAR_PGHOST` / `SALDI_CHAR_PGUSER` / `SALDI_CHAR_PGPASS` names used by `tests/restapi/support/RestApiEnv.php`) or from the gitignored `includes/connect.php`, and `markTestSkipped()` when neither yields a working connection. Never fall back to a literal password so the test "works out of the box". Non-secret test fixtures (tenant DB name, a throwaway test user seeded by the test itself) are fine.
- **Docs and examples:** placeholders only (`SMTP_PASS=your-app-password`), never a value that has ever been real.
- **Commented-out code:** if it holds a secret, delete the line - don't leave it commented.
- **Existing violations:** when you notice a secret already in a tracked file, do not silently rip it out or "fix" it in passing - removing it changes runtime behaviour (the code still needs the value from somewhere) and the secret must be rotated anyway. Flag it to the team with file and line so it can be moved to a gitignored home and rotated deliberately.
- **Review gate:** before opening a PR, grep the diff for `password|pass|apikey|api_key|secret|token|bearer` assigned to a quoted literal. Anything that matches must be a placeholder or a non-secret.
