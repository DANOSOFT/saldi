<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/orderIncludes/stockWarningSave.php --- 2026-05-20 ---

ob_start();

@session_start();
$s_id = session_id();
$title = "stockWarningSave";
$modulnr = 0;
$bg = "nix";
$header = "nix";
$webservice = true;

include("../../includes/connect.php");
include("../../includes/online.php");
include("../../includes/std_func.php");

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', '0');

if (!function_exists('is_stock_warning_enabled')) {
	include_once("../../includes/ordrefunc.php");
}

function _sw_fail($msg) {
	echo json_encode(array('ok' => false, 'error' => $msg));
	exit;
}

if (!is_stock_warning_enabled()) _sw_fail('disabled');

$ordre_id = isset($_POST['ordre_id']) ? (int)$_POST['ordre_id'] : 0;
$varenr   = isset($_POST['varenr'])   ? trim((string)$_POST['varenr']) : '';
$note     = isset($_POST['note'])     ? trim((string)$_POST['note'])   : '';

if (!$ordre_id) _sw_fail('missing ordre_id');
if ($varenr === '') _sw_fail('missing varenr');
if ($note === '')   _sw_fail('missing note');

// Locate the most recent line on this order matching the varenr (the line the
// user is about to commit). If no line exists yet (brand-new order line that
// hasn't been saved yet), we still log with linje_id NULL — the line will be
// inserted by the form post that follows.
$varenrEsc = db_escape_string($varenr);
$linje_id  = 0;
$vare_id   = 0;
$r = db_fetch_array(db_select("select id, vare_id from ordrelinjer where ordre_id = '$ordre_id' and varenr = '$varenrEsc' order by id desc limit 1", __FILE__ . " linje " . __LINE__));
if ($r && $r['id']) {
	$linje_id = (int)$r['id'];
	$vare_id  = (int)$r['vare_id'];
}
if (!$vare_id) {
	$r = db_fetch_array(db_select("select id from varer where varenr = '$varenrEsc' or stregkode = '$varenrEsc' limit 1", __FILE__ . " linje " . __LINE__));
	if ($r && $r['id']) $vare_id = (int)$r['id'];
}

// Idempotency: one approval row per (order, varenr). Deduping by varenr (rather than
// linje_id) keeps every approved item — including samlesæt sub-items that share a
// varenr with a standalone item — logged exactly once and never dropped.
$rDup = db_fetch_array(db_select("select id from order_stock_warning_log where ordre_id = '$ordre_id' and varenr = '$varenrEsc' limit 1", __FILE__ . " linje " . __LINE__));
if ($rDup && $rDup['id']) {
	echo json_encode(array('ok' => true, 'log_id' => (int)$rDup['id'], 'linje_id' => $linje_id, 'deduped' => true));
	exit;
}

log_stock_warning($ordre_id, $vare_id, $note, $linje_id ?: null);

// Return the new row id so the client can confirm.
$rNew = db_fetch_array(db_select("select id from order_stock_warning_log where ordre_id = '$ordre_id' order by id desc limit 1", __FILE__ . " linje " . __LINE__));
echo json_encode(array(
	'ok'       => true,
	'log_id'   => $rNew ? (int)$rNew['id'] : 0,
	'linje_id' => $linje_id,
	'vare_id'  => $vare_id,
));
