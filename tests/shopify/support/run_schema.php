<?php
// tests/shopify/support/run_schema.php
//
// Child-process runner for the schema manifest: bootstraps the legacy database globals
// (includes/db_query.php, $connection, $db_type) against one test tenant and runs one of
//   ensure   ensure_shopify_schema()        the upgrade path, as includes/betweenUpdates.php calls it
//   force    ensure_shopify_schema(true)    the full pass regardless of the marker
//   opret    opret_shopify_schema()         the fresh-tenant path, as admin/opret.php calls it
//   ready    shopify_schema_ready()
//   tx_commit / tx_rollback   ensure_shopify_schema() inside a transaction the caller opened,
//            then commit or roll back that outer transaction
// A ':webservice' suffix on the mode (e.g. ensure:webservice) sets $webservice, so db_modify() returns
// its errors instead of alerting and exiting.
// It prints the result as JSON. A child process keeps the legacy exit() paths out of PHPUnit.
//
// History:
// 20261021 Sawaneh SD-733: created.
// 20261021 Sawaneh SD-733 review: ':webservice' mode suffix.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_schema.php <ensure|force|opret|ready|tx_commit|tx_rollback> <test_db>\n");
    exit(2);
}
[$mode, $flag] = array_pad(explode(':', $argv[1], 2), 2, '');
$db = $argv[2];

require_once __DIR__ . '/ShopifyEnv.php';
ShopifyEnv::assertSafeTestDb($db);

$_SERVER['REQUEST_URI'] = '/saldi/admin/opret.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
chdir(ShopifyEnv::repoRoot() . '/admin');

function alert($msg)
{
    fwrite(STDERR, "alert: $msg\n");
    exit(3);
}

$db_type = 'postgresql';
$db_encode = 'UTF8';
$brugernavn = 'shopifytest';
$sqdb = '';
$db_skriv_id = 0;
$webservice = ($flag === 'webservice');

require_once ShopifyEnv::repoRoot() . '/includes/db_query.php';
$connection = db_connect(ShopifyEnv::pgHost(), ShopifyEnv::pgUser(), ShopifyEnv::pgPass(), $db);

require_once ShopifyEnv::repoRoot() . '/includes/shopify/schema.php';

$started = microtime(true);
switch ($mode) {
    case 'ensure':
        $result = ensure_shopify_schema();
        break;
    case 'force':
        $result = ensure_shopify_schema(true);
        break;
    case 'opret':
        $result = opret_shopify_schema();
        break;
    case 'ready':
        $result = shopify_schema_ready();
        break;
    case 'tx_commit':
    case 'tx_rollback':
        transaktion('begin');
        $result = ensure_shopify_schema();
        $stillOpen = pg_transaction_status($connection) === PGSQL_TRANSACTION_INTRANS;
        transaktion($mode === 'tx_commit' ? 'commit' : 'rollback');
        $result = $result && $stillOpen;
        break;
    default:
        fwrite(STDERR, "unknown mode $mode\n");
        exit(2);
}

echo json_encode([
    'mode' => $mode,
    'result' => $result,
    'version' => shopify_schema_version_installed(),
    'ms' => (int)round((microtime(true) - $started) * 1000),
]);
exit(0);
