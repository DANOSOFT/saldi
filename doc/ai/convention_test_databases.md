# Test database ownership

Database-backed PHPUnit groups use `tests/characterization/support/database/TestDatabase.php`.
Create a fresh installed tenant per mutating class; do not clone an existing
account or introduce a fixed database name. Keep the installed test master for
HTTP routing. SQL-only groups may explicitly share `sqlFixture()` when every
table is temporary or inside a private randomized schema.

The helper owns its registry/session rows and only deletes databases it created
successfully. It prints the UUID and generated database name before creation
and captures the identity in individual IDE/JUnit results only when retention
is enabled (`keep=yes`). Ordinary runs omit these debugging lines; actual errors
remain visible. Test routing rows use locked, explicitly allocated negative IDs
so creation, retention and cleanup never advance or reset the normal registry
sequence or increase the highest positive ID.
`SALDI_TEST_KEEP_DATABASES=1` retains all groups for inspection; later runs must
never reuse or automatically delete retained databases. Explicit operator cleanup
uses `tools/phpunit-databases.php`: listing by default, `--delete` for removal.
It requires the creating role, matching database comment and internal UUID/name,
retained status and originating master; a database-name prefix is never proof.
Preserve active and unmarked databases. See `tests/README.md` for usage.

Test routing accounts must follow the application's registry field formats.
Store `regnskab.sidst` as Unix seconds, matching login, rather than a calendar
date: these rows can appear in the admin account list while tests are running.
`TestDatabaseLifecycleTest` checks this contract.

Known batch-window legacy warnings remain visible as non-failing PHPUnit
warnings until repaired; previously unseen warning classes still fail the test.

## Possible future plan: a temporary master per test batch

Deferred proposal, not a current requirement. Revisit if tests need to enforce
production database naming (`<master>_<numeric tenant ID>`) or modify master
schema/data without affecting the installed test master.

- Create one owned temporary master per test invocation, with the complete
  master schema, its own registry entry and the required users/settings.
  Preserve user IDs with copied password hashes, or seed known test users.
  Keep separate tenant fixtures per mutating class.
- Use the existing PHPUnit bootstrap to register execution-start/finish hooks,
  covering terminal and IDE runs. Test discovery alone should not create a
  batch. The batch owner waits for all workers before cleanup; class teardown
  releases only its tenant. Record ownership for interrupted-run cleanup.
- Resolve the same batch master for CLI children and the test web installation
  used by API requests and `index/login.php`. This requires a deliberate web
  configuration mechanism; a PHPUnit environment variable alone does not
  redirect browser requests. Keep locator integration pointed at the test
  environment.
- `SALDI_TEST_KEEP_DATABASES=1` would retain the entire batch, including its
  master, tenant databases and routing data, for browser inspection. Otherwise
  remove owned tenants before their master after execution finishes.
- Normal installed-master counters and tenant names must remain untouched.
  Any production-style numeric allocation would happen inside the disposable
  master only, within the registry's database-name length limit. Cleanup must
  verify batch ownership rather than trusting a name prefix.

The current shared installed-master and UUID-tenant implementation remains in
use until this proposal is explicitly selected for implementation.
