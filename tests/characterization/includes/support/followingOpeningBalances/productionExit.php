<?php
// Copyright (c) 2026 Danosoft ApS
// 20261120 CL/MMR SST-1005 Run the automatic rebuild with the production includes/db_query.php, whose db_modify() ends the request with exit on a failed statement instead of throwing (CodeRabbit on PR #1109).
//                 Mode 'card' runs the year card's save order for year 10: begin, the guard, its own writes, then the carry-forward (Codex round 22).
//                 The card commits through followingOpeningBalanceCommit(), as systemdata/regnskabskort.php does (Codex round 23).
// 20261126 CL/MMR SST-1005 Mode 'card' arms the guard and prepares the settings rows before begin, as the card now does (CodeRabbit on PR #1109).
//
// Started by FollowingOpeningBalancesTest::testARebuildThatTheProductionErrorPathEndsLeavesTheFailedMessage() as a
// process of its own, because exit would end the test. Arguments: the test database, the schema holding its tables and
// a scratch directory for the logs db_query.php writes, and optionally the mode ('page', the default, or 'card'). It prints "shutdown-done" from the very last shutdown function,
// so the test knows every shutdown function ran, and "not-ended" if the rebuild returned instead of ending the request.

if ($argc < 4) {
	fwrite(STDERR, "usage: productionExit.php <database> <schema> <scratch directory>\n");
	exit(2);
}
list(, $database, $schema, $scratch) = $argv;
$mode = isset($argv[4]) ? $argv[4] : 'page';
$root = dirname(__DIR__, 5);
require_once $root . '/tests/characterization/support/database/TestDatabase.php';

// get_relative() gives '' for a top-level script, so db_query.php writes its logs under the scratch directory.
chdir($scratch);
$_SERVER['REQUEST_URI'] = '/sst1005.php';
$_SERVER['PHP_SELF'] = '/sst1005.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$GLOBALS['connection'] = TestDatabase::connect($database);
pg_query($GLOBALS['connection'], 'SET search_path TO ' . pg_escape_identifier($GLOBALS['connection'], $schema));
$GLOBALS['db_type'] = 'postgres';
$GLOBALS['db'] = 'sst1005';
// 'develop' makes db_modify() print its error mail instead of sending it.
$GLOBALS['sqdb'] = 'develop';
$GLOBALS['brugernavn'] = 'sst1005';
$GLOBALS['db_skriv_id'] = 0;
$GLOBALS['sprog_id'] = 1;

require_once $root . '/includes/db_query.php';
require_once $root . '/includes/std_func.php';
require_once $root . '/includes/genberegn.php';
require_once $root . '/includes/updateFollowingOpeningBalances.php';

register_shutdown_function(static function (): void {
	// Registered while shutting down, so it runs after every shutdown function registered before it.
	register_shutdown_function(static function (): void {
		echo "\nshutdown-done\n";
	});
});
if ($mode === 'card') {
	// systemdata/regnskabskort.php in the same order: the guard, the settings rows, begin, the card's own writes (the
	// description and the primo reset of its year), then updateOpeningBalancesAfterYearSave() and the commit.
	$guard = followingOpeningBalanceFailureGuard(9, 9);
	followingOpeningBalancePrepareRows();
	transaktion('begin');
	$primoBeforeSave = followingOpeningBalancePrimo(10, true);
	db_modify("update grupper set beskrivelse = 'saved by the card' where art = 'RA' and kodenr = '10'", __FILE__ . ' line ' . __LINE__);
	db_modify("update kontoplan set primo = '0' where regnskabsaar = '10'", __FILE__ . ' line ' . __LINE__);
	updateOpeningBalancesAfterYearSave(10, $primoBeforeSave);
	if (followingOpeningBalanceCommit() && $guard) followingOpeningBalanceFailureGuard(null);
} else {
	checkFollowingOpeningBalances(10);
}
echo "\nnot-ended\n";
