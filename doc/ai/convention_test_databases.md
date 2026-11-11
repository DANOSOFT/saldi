# Test database ownership

Database-backed PHPUnit groups use `tests/characterization/support/database/TestDatabase.php`.
Create a fresh installed tenant per mutating class; do not clone an existing
account or introduce a fixed database name. Keep the installed test master for
HTTP routing. SQL-only groups may explicitly share `sqlFixture()` when every
table is temporary or inside a private randomized schema.

The helper prints a UUID and generated database name before creation, owns its
registry/session rows, and only deletes databases it created successfully.
`SALDI_TEST_KEEP_DATABASES=1` retains all groups for inspection; later runs must
never reuse or delete retained databases. See `tests/README.md` for usage.

Known batch-window legacy warnings remain visible as non-failing PHPUnit
warnings until repaired; previously unseen warning classes still fail the test.
