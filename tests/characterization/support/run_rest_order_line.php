<?php
// tests/characterization/support/run_rest_order_line.php
//
// Child-process runner for the REST API's order line create:
// OrderLineService::createOrderLine(), which restapi/endpoints/v1/debitor/orderlines/
// and creditor/orderlines/ call with the request body once the bearer token
// is accepted. It connects the way restapi/core/BaseEndpoint.php does after
// a valid token: includes/db_query.php, connect.php and std_func.php, then the
// account database through db_connect() and db_select_database(), with the
// globals $db and $regnaar set.
//
// argv: 1 = tenant database
//       2 = the request body, JSON
//       3 = the endpoint's order types, comma separated: DO,DK,PO or KO,KK
//
// Prints one JSON line on stdout: the service's answer.
//
// History:
// 20261105 CL/LH Created for RestOrderLineTotalsTest.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_rest_order_line.php <tenant_db> <json body> <order types>\n");
    exit(2);
}
$tenantDb = $argv[1];
$body = json_decode($argv[2]);
$orderArts = explode(',', $argv[3]);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/saldi/restapi/endpoints/v1/debitor/orderlines/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

chdir(dirname(__DIR__, 3) . '/restapi/endpoints/v1/debitor/orderlines'); // the endpoint's own directory

include_once __DIR__ . '/../../../includes/db_query.php';
include_once __DIR__ . '/../../../includes/connect.php';
include_once __DIR__ . '/../../../includes/std_func.php';
include_once __DIR__ . '/../../../restapi/core/auth.php';

$connection = db_connect($sqhost, $squser, $sqpass, $tenantDb, __FILE__ . " linje " . __LINE__);
if (!$connection || !db_select_database($connection, $tenantDb)) {
    fwrite(STDERR, "could not connect to $tenantDb\n");
    exit(1);
}
$db = $tenantDb;
$regnaar = api_current_fiscal_year();

require_once __DIR__ . '/../../../restapi/services/OrderLineService.php';

$service = new OrderLineService();
echo "\n" . json_encode($service->createOrderLine($body, $orderArts)) . "\n";
