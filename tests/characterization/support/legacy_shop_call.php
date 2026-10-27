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

@session_start();
$s_id = session_id();
chdir(dirname(__DIR__, 3) . '/api');
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$call = $_GET['call'] ?? '';
if ($call === 'cost') {
	include('updateShopCostPrice.php');
	echo 'RESULT ' . updateShopCostPrice((int)($_GET['id'] ?? 0));
} elseif ($call === 'pos8') {
	include('varesync_pos8.php');
	echo 'RESULT ' . var_export(varesync($_GET['valg'] ?? '1'), true);
} elseif ($call === 'qty') {
	include('shopQty2saldi.php');
	echo 'RESULT ' . var_export(varesync(), true);
} else {
	echo 'RESULT unknown call';
}
