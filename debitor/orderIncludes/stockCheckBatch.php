<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/orderIncludes/stockCheckBatch.php --- 2026-05-20 ---
ob_start();

@session_start();
$s_id = session_id();
$title = "stockCheckBatch";
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

$out = array('enabled' => false, 'out_of_stock' => array());
$out['enabled'] = is_stock_warning_enabled();
if (!$out['enabled']) {
	echo json_encode($out);
	exit;
}

$itemsRaw = isset($_POST['items']) ? $_POST['items'] : (isset($_GET['items']) ? $_GET['items'] : '');
$idsRaw   = isset($_POST['ids'])   ? $_POST['ids']   : (isset($_GET['ids'])   ? $_GET['ids']   : '');

$vare_ids = array();

if ($idsRaw) {
	foreach (explode(',', $idsRaw) as $v) {
		$v = (int)trim($v);
		if ($v > 0) $vare_ids[$v] = true;
	}
}
if ($itemsRaw) {
	foreach (explode(',', $itemsRaw) as $vn) {
		$vn = trim($vn);
		if ($vn === '') continue;
		$vnEsc = db_escape_string($vn);
		$r = db_fetch_array(db_select("select id from varer where varenr = '$vnEsc' or stregkode = '$vnEsc' limit 1", __FILE__ . " linje " . __LINE__));
		if ($r && $r['id']) $vare_ids[(int)$r['id']] = true;
	}
}


$seen_subitem = array();  // dedupe sub-items if multiple masters share them
foreach (array_keys($vare_ids) as $vid) {
	$info = check_stock_warning($vid);
	if (!empty($info['out_of_stock'])) {
		$out['out_of_stock'][] = array(
			'vare_id'     => $vid,
			'varenr'      => (string)$info['varenr'],
			'beskrivelse' => (string)$info['beskrivelse'],
			'beholdning'  => (float)$info['beholdning'],
			'min_lager'   => (float)$info['min_lager'],
		);
	}

	$rMaster = db_fetch_array(db_select("select samlevare, varenr from varer where id = '$vid'", __FILE__ . " linje " . __LINE__));
	if ($rMaster && trim($rMaster['samlevare']) === 'on') {
		$masterVarenr = (string)$rMaster['varenr'];
		$qSub = db_select("select vare_id, antal from styklister where indgaar_i = '$vid' and vare_id is not null and vare_id > 0", __FILE__ . " linje " . __LINE__);
		while ($rSub = db_fetch_array($qSub)) {
			$subVid = (int)$rSub['vare_id'];
			$subAntal = (float)$rSub['antal'];
			if ($subVid <= 0 || isset($seen_subitem[$subVid])) continue;
			$seen_subitem[$subVid] = true;
			if (isset($vare_ids[$subVid])) continue;  // already covered as a top-level varenr
			$subInfo = check_stock_warning($subVid);
			$insufficient = ($subAntal > 0 && (float)$subInfo['beholdning'] < $subAntal);
			if (!empty($subInfo['out_of_stock']) || $insufficient) {
				$desc = trim((string)$subInfo['beskrivelse']) . ' (samlesæt: ' . $masterVarenr;
				if ($insufficient) $desc .= ', kræver ' . rtrim(rtrim(number_format($subAntal, 3, '.', ''), '0'), '.') . ' på lager: ' . rtrim(rtrim(number_format((float)$subInfo['beholdning'], 3, '.', ''), '0'), '.');
				$desc .= ')';
				$out['out_of_stock'][] = array(
					'vare_id'     => $subVid,
					'varenr'      => (string)$subInfo['varenr'],
					'beskrivelse' => $desc,
					'beholdning'  => (float)$subInfo['beholdning'],
					'min_lager'   => (float)$subInfo['min_lager'],
				);
			}
		}
	}
}

echo json_encode($out);
