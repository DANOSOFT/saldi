<?php
// 20260921 CDX/LUI Exercise actual db_modify audit and web error policy for imports.
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message) { throw new RuntimeException($message); });
$dsn = getenv('SALDI_TEST_DSN') ?: getenv('SALDI_CHAR_PG_DSN');
if (!$dsn) { throw new RuntimeException('Isolated PostgreSQL DSN required'); }
$connection = pg_connect($dsn);
pg_query($connection, 'SET search_path TO pg_temp');
$work = sys_get_temp_dir() . '/saldi-import-policy-' . getmypid();
$db = 'import_policy'; $sqdb = 'develop'; $db_type = 'postgresql'; $db_skriv_id = 2;
$brugernavn = 'regression'; $webservice = false; $db_transaktion_depth = 0;
$_SERVER['REQUEST_URI'] = '/saldi/test.php';
mkdir($work . '/temp/' . $db, 0700, true); chdir($work);
function alert($message) { throw new LogicException('Unexpected web abort: ' . $message); }
require_once __DIR__ . '/../includes/db_query.php';
require_once __DIR__ . '/../includes/legacyItemImport.php';
require_once __DIR__ . '/../api/varesync.php';
function importPolicyCheck($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
try {
    pg_query($connection, 'CREATE TEMP TABLE varer (id serial PRIMARY KEY, varenr text, beskrivelse text, salgspris numeric, kostpris numeric CHECK(kostpris>=0), enhed text, gruppe int, min_lager int)');
    pg_query($connection, 'CREATE TEMP TABLE settings (id serial, var_name text, var_grp text, var_value text)');
    $id = varesyncInsertedId("INSERT INTO varer(varenr,kostpris) VALUES ('AUDITED',1)", 'varer');
    importPolicyCheck($id === 1, 'production returning insert yields exact ID');
    $audit = file_get_contents('temp/' . $db . '/.ht_modify.log');
    importPolicyCheck(str_contains($audit, "INSERT INTO varer(varenr,kostpris) VALUES ('AUDITED',1) RETURNING id;"), 'product creation remains in the write audit');
    importPolicyCheck(str_starts_with(db_modify('UPDATE varer SET kostpris=2 WHERE id=1', __FILE__), "0\t"), 'default db_modify return contract unchanged');
    $items = [
        ['number'=>'AUDITED','description'=>'Changed','unit'=>'stk','supplier_number'=>'','group'=>1,'sales_price'=>10,'cost_price'=>3],
        ['number'=>'INVALID','description'=>'Rejected','unit'=>'stk','supplier_number'=>'','group'=>1,'sales_price'=>10,'cost_price'=>-1],
    ];
    // Observe only the deliberately injected database constraint warning.
    $warnings = [];
    set_error_handler(static function ($severity, $message) use (&$warnings) {
        if (str_contains($message, 'pg_query(): Query failed: ERROR:') && str_contains($message, 'violates check constraint')) {
            $warnings[] = $message; return true;
        }
        throw new RuntimeException($message);
    });
    ob_start(); $caught = null;
    try { legacyItemImportApply($items, 0); }
    catch (RuntimeException $error) { $caught = $error; }
    finally { ob_end_clean(); restore_error_handler(); }
    importPolicyCheck($caught && str_contains($caught->getMessage(), 'Ingen varer er importeret'), 'real web write failure reaches import-specific catch');
    importPolicyCheck(count($warnings) === 1, 'only expected constraint warning observed');
    importPolicyCheck($webservice === false, 'caller web error policy restored after failure');
    importPolicyCheck(pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE, 'explicit rollback closes failed transaction');
    importPolicyCheck(pg_fetch_result(pg_query($connection, 'SELECT kostpris FROM varer WHERE id=1'), 0, 0) === '2', 'earlier successful write rolled back');
    importPolicyCheck(pg_fetch_result(pg_query($connection, 'SELECT count(*) FROM varer'), 0, 0) === '1', 'failed batch creates no products');
    importPolicyCheck(str_contains(file_get_contents('temp/' . $db . '/.ht_modify.log'), "rollback;"), 'explicit rollback recorded in actual audit log');
} finally {
    pg_close($connection);
    foreach (glob($work . '/temp/' . $db . '/{,.}*', GLOB_BRACE) as $path) {
        if (is_file($path)) { unlink($path); }
    }
    rmdir($work . '/temp/' . $db); rmdir($work . '/temp'); chdir('/tmp'); rmdir($work);
}
