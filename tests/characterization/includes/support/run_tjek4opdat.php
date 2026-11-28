<?php
// 20261105 CL/LH The other login of Tjek4opdatRaceTest, in a PHP process of its own: tjek4opdat() up to 5.1.0 on the fixture, then the rest of the login.
//                Arguments: schema, regnskab, login name, step (see tjekFixtureLogin()), the version the login read before it called tjek4opdat().
//                The connection string comes from SALDI_CHAR_PG_DSN in the environment, so it is not on the command line.
// 20261124 CL/ASR The first argument is now the database, the test's TestDatabase fixture, and the login connects to it with TestDatabase::connect() (SALDI_CHAR_PGHOST/PGUSER/PGPASS) instead of SALDI_CHAR_PG_DSN, so CI runs it.

[, $database, $schema, $regnskab, $login, $step, $dbver] = $argv;
require __DIR__ . '/tjek4opdat_database.php';
tjekFixtureLogin($database, $schema, $regnskab, $login, $step);
require dirname(__DIR__, 4) . '/includes/tjek4opdat.php';
// The chain includes its steps as ../includes/opdat_*.php, like the pages in index/ do.
chdir(dirname(__DIR__, 4) . '/index');
tjek4opdat($dbver, '5.1.0');
// The rest of the login, includes/betweenUpdates.php and the page, takes a moment as well.
db_select('SELECT pg_sleep(0.5)', 'login');
tjekFixtureEvent('rest of the login');
