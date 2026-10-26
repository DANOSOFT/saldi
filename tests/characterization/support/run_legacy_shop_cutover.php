<?php
// tests/characterization/support/run_legacy_shop_cutover.php
//
// Runs shopify_legacy_cutover() on the throwaway characterization tenant with an operation that
// writes intake_enabled=0 for slot 5 and then commits, leaves the transaction open, or throws
// inside it (SD-750). Refuses any database but CharacterizationEnv::testDb().
//
// argv: 1 = commits|uncommitted|throws
// Prints one JSON line: {result|exception, message, idle}.
//
// History:
// 20261026 CL/SZ Created (SD-750).

require_once __DIR__ . '/CharacterizationEnv.php';
CharacterizationEnv::assertSafeDatabases();

function alert($msg)
{
	fwrite(STDERR, "alert: $msg\n");
	exit(3);
}

$mode = $argv[1] ?? '';
$db_type = 'postgresql';
$db_encode = 'UTF8';
$brugernavn = 'chartest';
$sqdb = '';
$db_skriv_id = 0;
$db = CharacterizationEnv::testDb();

require_once CharacterizationEnv::repoRoot() . '/includes/db_query.php';
$connection = db_connect(CharacterizationEnv::pgHost(), CharacterizationEnv::pgUser(), CharacterizationEnv::pgPass(), $db);
require_once CharacterizationEnv::repoRoot() . '/includes/shopify/coexistence.php';

$out = array();
try {
	$out['result'] = shopify_legacy_cutover(array('db' => $db), 5, function () use ($mode, $connection) {
		pg_query($connection, 'BEGIN');
		pg_query($connection, "INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ('intake_enabled', 'shopify_legacy:5', '0', 'chartest')");
		if ($mode === 'throws') {
			throw new RuntimeException('settings write failed');
		}
		if ($mode === 'commits') {
			pg_query($connection, 'COMMIT');
		}
		return 'closed';
	});
} catch (Throwable $e) {
	$out['exception'] = get_class($e);
	$out['message'] = $e->getMessage();
}
$out['idle'] = pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE;
echo json_encode($out) . "\n";
