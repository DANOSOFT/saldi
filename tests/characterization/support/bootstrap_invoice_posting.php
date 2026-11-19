<?php
// 20261113 CDX/NTR Bootstrap invoice posting adapters without loading ordrefunc.php (SD-599).

/** @var string $tenantDb Caller-owned characterization database, supplied by the child runner. */
if (!isset($tenantDb) || $tenantDb === '') {
    throw new RuntimeException('The invoice posting runner requires its owned tenant database');
}
error_reporting(E_ALL);
chdir(dirname(__DIR__, 3) . '/remoteBooking');
$_SERVER['REQUEST_URI'] = '/saldi/remoteBooking/api.php';
$_SERVER['PHP_SELF'] = '/saldi/remoteBooking/api.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
ob_start();
require dirname(__DIR__, 3) . '/includes/connect.php';
require_once dirname(__DIR__, 3) . '/includes/std_func.php';
$db = $tenantDb;
$registry = db_fetch_array(db_select("select id from regnskab where db='" . db_escape_string($db) . "'", __FILE__ . ' linje ' . __LINE__));
$db_id = $db_skriv_id = (int)$registry['id'];
$connection = db_connect($sqhost, $squser, $sqpass, $db);
$baseCurrency = get_settings_value('baseCurrency', 'globals', '') ?: 'DKK';
$brugernavn = 'chartest';
$bruger_id = 1;
require_once dirname(__DIR__, 3) . '/includes/invoicePosting.php';
