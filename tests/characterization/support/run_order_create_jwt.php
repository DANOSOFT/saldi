<?php
// tests/characterization/support/run_order_create_jwt.php
//
// Child-process runner for the JWT REST order-creation path (SD-600):
// restapi/services/OrderService.php::createOrder() + OrderModel::save().
//
// Production reaches this through restapi/endpoints/v1/{debitor,creditor}/orders/index.php
// -> BaseEndpoint (JWT auth, CORS, logging) -> OrderService::createOrder($data).
// This runner calls createOrder() directly and supplies the DB connection
// BaseEndpoint would have set up, the same way
// OrderInvoiceCharacterizationTest bypasses remoteBooking/api.php's HTTP
// layer to drive includes/ordrefunc.php directly - the JWT/HTTP wrapper is
// not what SD-600 is characterizing, the writes to ordrer/adresser are.
//
// getallheaders() (called unconditionally by OrderService::getSaldiUser())
// does not exist under the php-cli SAPI - polyfilled below, returning no
// headers (so ref ends up '', matching a request with no X-SaldiUser header).
//
// argv: 1 = tenant db
//       2 = JSON-encoded order payload (property names as OrderService
//           expects - see OrderService::mapApiToDanish())
//
// Prints OrderService::createOrder()'s own result array as a single JSON
// object on the LAST line of stdout.
//
// History:
// 20260805 CL/SZ SD-600: created.
// 20260814 CL/SZ SD-600: set REQUEST_URI so get_relative()'s temp/ path depth matches the remoteBooking/ chdir.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_order_create_jwt.php <tenant_db> <json_payload>\n");
    exit(2);
}
$tenantDb = $argv[1];
$payload = json_decode($argv[2]);
if (!is_object($payload)) {
    fwrite(STDERR, "argv[2] must be a JSON object\n");
    exit(2);
}

error_reporting(E_ERROR | E_PARSE);

if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        return [];
    }
}

// Matches bootstrap_ordrefunc.php: get_relative() (includes/db_query.php)
// derives its "../" depth from REQUEST_URI's slash count, not cwd - without
// this, db_select()'s temp/ logging path resolves one level too shallow and
// fopen() fails on the missing directory.
$_SERVER['REQUEST_URI'] = '/saldi/remoteBooking/api.php';

// One level below the repo root, so includes/connect.php's own
// "../includes/..." relative include resolves (see bootstrap_ordrefunc.php,
// which relies on the same trick from remoteBooking/).
chdir(dirname(__DIR__, 3) . '/remoteBooking');

ob_start(); // legacy includes print stray HTML on some branches
include('../includes/connect.php'); // connects to master, defines db_* + $sqhost/$squser/...
include('../includes/std_func.php');
$db = $tenantDb;
$connection = db_connect($sqhost, $squser, $sqpass, $db); // reconnect to the tenant, mirrors bootstrap_ordrefunc.php

require_once dirname(__DIR__, 3) . '/restapi/models/orders/OrderModel.php';
require_once dirname(__DIR__, 3) . '/restapi/services/OrderService.php';

$result = OrderService::createOrder($payload);

// Reload independently of the in-memory $order OrderService just built, the
// way a fresh GET /v1/{debitor,creditor}/orders/{id} would - this is what
// actually round-trips through the ordrer table, as opposed to properties
// still sitting on the object save() never re-read (see OrderModel::save():
// it does not reload from the row it just inserted).
if (!empty($result['success']) && isset($result['data']['id'])) {
    $art = $payload->art ?? ($payload->type ?? 'DO');
    $reloaded = new OrderModel((int)$result['data']['id'], $art);
    $result['reloaded'] = $reloaded->getId() ? $reloaded->toArray() : null;
}

ob_end_clean();
fwrite(STDOUT, json_encode($result) . "\n");
