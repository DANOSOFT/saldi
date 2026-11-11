# Test database ownership

Database-backed PHPUnit groups use `tests/characterization/support/database/TestDatabase.php`.
Create a fresh installed tenant per mutating class; do not clone an existing
account or introduce a fixed database name. Keep the installed test master for
HTTP routing. SQL-only groups may explicitly share `sqlFixture()` when every
table is temporary or inside a private randomized schema.

The helper prints a UUID and generated database name before creation, owns its
registry/session rows, and only deletes databases it created successfully. Its
identity must also be captured in individual IDE/JUnit test results.
`SALDI_TEST_KEEP_DATABASES=1` retains all groups for inspection; later runs must
never reuse or automatically delete retained databases. Explicit operator cleanup
uses `tools/phpunit-databases.php`: listing by default, `--delete` for removal.
It requires the creating role, matching database comment and internal UUID/name,
retained status and originating master; a database-name prefix is never proof.
Preserve active and unmarked databases. See `tests/README.md` for usage.

Known batch-window legacy warnings remain visible as non-failing PHPUnit
warnings until repaired; previously unseen warning classes still fail the test.
