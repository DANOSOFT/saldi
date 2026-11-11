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
The generated name keeps a tenant's shape, `<master>_<regnskab id>` (`t` + 22 hex + `_1`):
`api/rest_api.php` takes a name without the underscore for the master database (MB-154).
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
