<?php
// tests/characterization/support/legacy_shop_call.php
//
// A page for run_legacy_shop_page.php that calls the legacy shop functions nothing in Saldi
// calls today (SD-778), the way a page would: inside a logged-in session, from api/, so their
// ../includes and ../temp paths resolve as they do in production.
//   ?call=cost&id=<varer.id>  api/updateShopCostPrice.php updateShopCostPrice()
//   ?call=pos8&valg=1|2       api/varesync_pos8.php varesync()
//   ?call=qty                 api/shopQty2saldi.php varesync()
// Prints "RESULT <return value>" after the call.
//
// History:
// 20261027 CL/SZ Created (SD-778, sub-task of SD-750).
// 20261028 CL/SZ SD-778 review: 404 unless run from the CLI (the web root is a full checkout); __DIR__ includes.

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

@session_start();
$s_id = session_id();
$root = dirname(__DIR__, 3);
chdir($root . '/api');
include($root . '/includes/connect.php');
include($root . '/includes/online.php');
include($root . '/includes/std_func.php');

$call = $_GET['call'] ?? '';
if ($call === 'cost') {
	include($root . '/api/updateShopCostPrice.php');
	echo 'RESULT ' . updateShopCostPrice((int)($_GET['id'] ?? 0));
} elseif ($call === 'pos8') {
	include($root . '/api/varesync_pos8.php');
	echo 'RESULT ' . var_export(varesync($_GET['valg'] ?? '1'), true);
} elseif ($call === 'qty') {
	include($root . '/api/shopQty2saldi.php');
	echo 'RESULT ' . var_export(varesync(), true);
} else {
	echo 'RESULT unknown call';
}
