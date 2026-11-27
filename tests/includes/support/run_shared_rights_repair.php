<?php
// tests/includes/support/run_shared_rights_repair.php
//
// Child process for roleSharedRightsConcurrency.test.php: runs perm_store_shared_rights() (the repair of stored rights
// strings at login, includes/betweenUpdates.php) on a connection of its own, in the test's disposable schema, with
// $sharedWithLive = true. The connection is named by application_name, so the test can see when it waits for a lock.
// Prints the number of users the repair rendered again as JSON.
//
// argv: 1 = the schema, 2 = the application_name of this connection
// env:  SALDI_ROLE_TEST_DSN, SALDI_ROLE_TEST_USER, SALDI_ROLE_TEST_PASSWORD (as the test)
//
// History:
// 20261127 ASR Created.

declare(strict_types=1);

if ($argc < 3 || !preg_match('/^[a-z0-9_]+$/', $argv[1]) || !preg_match('/^[a-z0-9_]+$/', $argv[2])) {
	fwrite(STDERR, "usage: php run_shared_rights_repair.php <schema> <application_name>\n");
	exit(2);
}
$db = new PDO((string) getenv('SALDI_ROLE_TEST_DSN'), getenv('SALDI_ROLE_TEST_USER') ?: '', getenv('SALDI_ROLE_TEST_PASSWORD') ?: '', array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
$db->exec('set search_path to ' . $argv[1]);
$db->exec("set application_name = '" . $argv[2] . "'");
$db->exec("set lock_timeout = '20s'");

$GLOBALS['role_test_db'] = $db;
$GLOBALS['sharedWithLive'] = true;
$GLOBALS['bruger_id'] = 0;
$GLOBALS['brugernavn'] = 'login';
$GLOBALS['rettigheder'] = '';
$GLOBALS['revisor'] = false;
$GLOBALS['db_modify_fejl'] = false;
require __DIR__ . '/RoleDatabase.php';
require __DIR__ . '/../../../includes/permissions.php';

echo json_encode(array('rendered' => perm_store_shared_rights())), "\n";
exit(0);
