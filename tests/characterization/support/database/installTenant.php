<?php
// 20261111 CDX/NTR Run the production tenant installer against an already owned empty database.

require_once __DIR__ . '/TestDatabase.php';
require_once __DIR__ . '/../../../TestCredentials.php';

$db = $argv[1] ?? '';
if (!preg_match('/^t[a-f0-9]{24}$/', $db)) {
    throw new RuntimeException('The installer requires a generated test database name');
}
$connection = TestDatabase::connect($db);
$db_type = 'postgresql';
$db_encode = 'UTF8';
$sqdb = TestDatabase::masterDb();
$db_id = 0;
$bruger_id = 0;
$fra_formular = null;
$regnskab = 'Test fixture';
$s_id = '';
require __DIR__ . '/../../../../includes/version.php';

// The helper owns CREATE/DROP. This adapter executes the installer's real SQL
// without loading a machine-specific connect.php or writing application logs.
function db_create($name) {
    if ($name !== $GLOBALS['db']) {
        throw new RuntimeException('Installer attempted to switch databases');
    }
}
function db_modify($sql, $source = '') {
    TestDatabase::query($GLOBALS['connection'], $sql);
    return "0\t";
}
function db_select($sql, $source = '') {
    return TestDatabase::query($GLOBALS['connection'], $sql);
}
function db_fetch_array($result) {
    return pg_fetch_assoc($result);
}
function db_escape_string($value) {
    return pg_escape_string($GLOBALS['connection'], (string)$value);
}
function transaktion($operation) {
    TestDatabase::query($GLOBALS['connection'], $operation);
}
require_once __DIR__ . '/../../../../includes/std_func.php';
require_once __DIR__ . '/../../../../includes/opretfunc.php';
opret_build_tenant('', '', '', $db, 'chartest', TestCredentials::password('database_fixture'), true);
pg_close($connection);
