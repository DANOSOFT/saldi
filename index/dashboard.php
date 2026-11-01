<!doctype html>
<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/dashboard.php --- ver 5.1.0 --- 2026.11.01 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2024-2026 Danosoft ApS
// ----------------------------------------------------------------------
//20241004 MMK
//20241018 LOE checks that some variables are set before using.
//20250513 Sawaneh display number of users online.
//20250805 LOE added close button to settings popup. and also added weekly graph snippet
// 20260916 Sawaneh Declared $permission_key (roles & permissions, phase 3)
// 20260922 Sawaneh Dashboard cleanup (topbar spec 5): heading, year/language selectors and buttons removed.
// 20260927 Sawaneh News and batch-expiry banners removed (now notifications in the bell).
// 20260930 Sawaneh "Review roles" card for administrators after the migration to roles (roles spec §6.3).
// 20260930 Sawaneh check_permissions() moved to includes/std_func.php (roles spec §4.4).
// 20261008 Sawaneh The VAT widget is hidden when not VAT registered and shows the current VAT period (decision 19).
// 20261008 Sawaneh Rebuilt on Adam's prototype_dashboard_tema.html v5: KPI cards with trend, revenue per month and per day,
//                  customers per hour, revenue per item group, orders, active users and suggestions on the Saldi theme
//                  (light/dark); "Rediger oversigt" is a drawer whose choices are per user and saved at once; the numbers
//                  come from dashboardIncludes/dashData.php. Texts 6920-6929, 6944-6949, 6951-6999.
// 20261009 Sawaneh Front page on prototype v8.13 (addendum 3): blocks in the user's order (settings dashboard_layout/order), the KPI band, Ubetalte salgsfakturaer (§5.9), Ufakturerede ordrer with the largest order, the suggestions list, 'Ingen omsætning endnu'. Texts 7032-7065.
// 20261009 Sawaneh Oversigt access keys (front-page addendum 4): dashboard.view gates the page, dashboard.block.* the blocks and the drawer.
// 20261011 Sawaneh Review: the block order is saved (action=order, cleared by reset); the old Finans-only gate replaced by
//                  dashboard.view, so Salg/Indkøb roles get the page their keys promise.
// 20261012 Sawaneh Test pass: the view key only refuses in deny mode (perm_refuse above), observe mode shows the page; widget
//                  choices are per user with the fixed defaults, no fallback to old company-wide rows.
// 20261014 Sawaneh Review: chart data only in the page JSON for blocks the role allows; fiscal-year dates zero-padded
//                  (string compares dropped months 1-9 of the year to date).
// 20261010 Sawaneh Ufakturerede ordrer: key figures 1 and 3 of addendum 3 §7 (delivered over 14 days ago, ready to invoice now). Texts 7072-7073.
// 20261031 CL/ASR The drawer's widget key and block order are escaped before update_settings_value(): it takes its arguments as given again (master's contract, the caller escapes).
// 20261101 CL/ASR Restyled on prototype D ("Som ordrelisten"): flat panels with a grey header band, the KPI strip without sparklines, Omsætning pr. varegruppe as a table with share bars, Ubetalte salgsfakturaer with the due groups as a table and a stacked bar, the small cards with lists and buttons, "Rediger oversigt" as a panel at the top right.
//                 Blocks, switches, order, access keys and links are unchanged.
//                 Fiscal months and this week's days after today have no figure yet ("–", no bar), as in the prototype.
//                 Texts 7700-7701.
@session_start();
$s_id = session_id();
ob_start();

global $sprog_id;

$css = "../css/dashboard.css?v=5";
print "<title>Overblik</title>";

include ("../includes/std_func.php");
include ("../includes/connect.php");
# get superUsers
$qtxt = "SELECT brugernavn FROM brugere";
$result = db_select($qtxt, __FILE__ . " linje " . __LINE__);
$superUsers = array();
while ($row = db_fetch_array($result)) {
    $superUsers[] = $row['brugernavn'];
}
# Get database name of current online user
$qtxt = "SELECT db FROM online WHERE session_id='$s_id' limit 1";
$db = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))[0];

# Get amount of active users
$superUsersPlaceholders = implode("','", $superUsers);
$timestamp = (int) date("U") - (60*60);
$qtxt = "SELECT count(brugernavn) FROM online WHERE db='$db' AND logtime > '$timestamp' AND revisor is not true AND brugernavn NOT IN ('$superUsersPlaceholders')";
$online_people_amount = (int) db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))[0];

$permission_key = 'any';
include ("../includes/online.php");
include ("../includes/stdFunc/dkDecimal.php");
include_once("../includes/notifications.php");
include_once("dashboardIncludes/dashData.php");
include_once(__DIR__ . "/dashboardIncludes/unpaidInvoices.php");
include_once("dashboardIncludes/roleReview.php");

$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$box1 = (int)$row['box1'];
$box2 = (int)$row['box2'];
$box3 = (int)$row['box3'];
$box4 = (int)$row['box4'];
$startmaaned = if_isset($box1, 1);
$startaar = if_isset($box2, 2000);
$slutmaaned = if_isset($box3, 1);
$slutaar = if_isset($box4, 2001);
$slutdato=31;
while (!checkdate($slutmaaned,$slutdato,$slutaar)){
	$slutdato=$slutdato-1;
	if ($slutdato<28) break 1;
}
$regnstart = sprintf('%04d-%02d-01', $startaar, $startmaaned);
$regnslut = sprintf('%04d-%02d-%02d', $slutaar, $slutmaaned, $slutdato);

if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$brugerId = (int) $bruger_id;
$canEditAccounts = function_exists('perm_can') ? perm_can('settings.finance', 'write') : (substr((string) $rettigheder, 1, 1) === '1');

// The widgets (prototype keys = the old dashboard_toggles names; weekkpi is new and starts from the old revweek).
$widgetDefaults = array('weekkpi' => 'on', 'revmonth' => 'on', 'revyear' => 'on', 'vatcount' => 'on', 'onlineusers' => 'off', 'ordercount' => 'on', 'suggest' => 'on',
	'revgraph' => 'on', 'revweek' => 'on', 'customergraph' => 'off', 'varegrp_doughnut' => 'on', 'unpaid_invoices' => 'on');
// The blocks and their default order (front-page spec §4, §8.2/§8.4); the user's order is one JSON list per user.
$blockDefaults = array('kpis', 'revgraph', 'customergraph', 'revweek', 'varegrp_doughnut', 'unpaid_invoices', 'small');
/**
 * The user's block order: saved list filtered to known blocks, missing blocks inserted at their default position.
 */
function dash_order(int $userId, array $defaults): array
{
	$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'dashboard_layout' and var_name = 'order' and user_id = $userId order by id desc limit 1", __FILE__ . " linje " . __LINE__));
	$saved = $r ? json_decode((string) $r['var_value'], true) : null;
	$order = array();
	if (is_array($saved)) {
		foreach ($saved as $id) {
			if (is_string($id) && in_array($id, $defaults, true) && !in_array($id, $order, true)) {
				$order[] = $id;
			}
		}
	}
	foreach ($defaults as $i => $id) {
		if (!in_array($id, $order, true)) {
			array_splice($order, min($i, count($order)), 0, array($id));
		}
	}
	return $order;
}
/**
 * A widget switch: the user's own choice, else the company's old choice, else the default.
 */
function dash_toggle(string $key, int $userId, string $default): bool
{
	$k = db_escape_string($key);
	$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'dashboard_toggles' and var_name = '$k' and user_id = $userId order by id desc limit 1", __FILE__ . " linje " . __LINE__));
	if (!$r && $key === 'weekkpi') {
		$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'dashboard_toggles' and var_name = 'revweek' and user_id = $userId order by id desc limit 1", __FILE__ . " linje " . __LINE__));
	}
	$v = $r ? trim((string) $r['var_value']) : $default;
	return $v !== 'off';
}

// Saved at once from the drawer (JSON answers); the shell's older ?hidden= path still works.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !isset($_POST['cookieLanguageId'])) {
	$ok = hash_equals((string) $_SESSION['csrf_token'], (string) ifset($_POST, 'csrf_token', ''));
	$res = array('ok' => $ok);
	if ($ok) {
		$action = (string) $_POST['action'];
		if ($action === 'widget' && isset($widgetDefaults[(string) ifset($_POST, 'key', '')])) {
			update_settings_value(db_escape_string((string) $_POST['key']), 'dashboard_toggles', !empty($_POST['on']) ? 'on' : 'off', 'Dashboard widget (per user)', $brugerId);
		} elseif ($action === 'hide') {
			update_settings_value('hide_dash', 'dashboard', !empty($_POST['hidden']) ? 1 : 0, 'Whether the overview is hidden for the user', $brugerId);
		} elseif ($action === 'order') {
			$order = json_decode((string) ifset($_POST, 'order', ''), true);
			$order = is_array($order) ? array_values(array_filter($order, 'is_string')) : array();
			update_settings_value('order', 'dashboard_layout', db_escape_string(json_encode($order)), 'Dashboard block order (per user)', $brugerId);
		} elseif ($action === 'reset') {
			db_modify("delete from settings where var_grp in ('dashboard_toggles', 'dashboard_layout') and user_id = $brugerId", __FILE__ . " linje " . __LINE__);
			update_settings_value('hide_dash', 'dashboard', 0, 'Whether the overview is hidden for the user', $brugerId);
		} elseif ($action === 'accounts' && $canEditAccounts) {
			update_settings_value('kontomin', 'dashboard_values', (int) ifset($_POST, 'kontomin', 0), 'Dashboard revenue accounts from');
			update_settings_value('kontomaks', 'dashboard_values', (int) ifset($_POST, 'kontomaks', 2000), 'Dashboard revenue accounts to');
			$res['reload'] = true;
		} else {
			$res['ok'] = false;
		}
	}
	ob_end_clean();
	header('Content-Type: application/json; charset=utf-8');
	print json_encode($res);
	exit;
}
if (isset($_GET['hidden']) && ($_GET['hidden'] == '1' || $_GET['hidden'] == '0')) {
	update_settings_value("hide_dash", "dashboard", (int) $_GET['hidden'], "Whether the overview is hidden for the user", $brugerId);
}

$kontomin = (int) get_settings_value("kontomin", "dashboard_values", 0);
$kontomaks = (int) get_settings_value("kontomaks", "dashboard_values", 2000);
$hide_dash = get_settings_value("hide_dash", "dashboard", "0", $brugerId) === "1";
$widgets = array();
foreach ($widgetDefaults as $k => $def) {
	$widgets[$k] = dash_toggle($k, $brugerId, $def);
}
$vatRegistration = vat_registration();
if (!$vatRegistration['registered']) {
	$widgets['vatcount'] = false;
}
// Who may see which block: the role keys of front-page addendum 4 (derived from the module keys for users without a role),
// the unpaid card also needs the Debitorkonti/Debitorrapport rights (§5.9.5).
$dashKey = function (string $id): bool { return !function_exists('perm_can') || perm_can('dashboard.block.' . $id); };
$blockAccess = array();
foreach ($blockDefaults as $bid) {
	$blockAccess[$bid] = $dashKey($bid) && ($bid !== 'unpaid_invoices' || check_permissions(array(6, 12)));
}
if (!$dashKey('suggestions')) {
	$widgets['suggest'] = false;
	$widgetDefaults['suggest'] = 'off';
}
// Se Oversigt (addendum 4 §2.1): refused once enforcement is on, logged while it only observes.
if (function_exists('perm_can') && !perm_can('dashboard.view')) {
	if (perm_enforcement_mode() === 'deny') {
		perm_refuse('dashboard.view (read)', '/index/dashboard.php');
	}
	perm_log_once('would_deny', 'dashboard.view /index/dashboard.php');
}
$blockOrder = dash_order($brugerId, $blockDefaults);

$h = function ($s) use ($charset) {
	return htmlspecialchars((string) $s, ENT_QUOTES, $charset === 'UTF-8' ? 'UTF-8' : 'ISO-8859-1');
};
$tx = function ($t) use ($sprog_id) {
	return findtekst($t, $sprog_id);
};
$u8 = function ($s) use ($charset) {
	return $charset === 'UTF-8' ? (string) $s : mb_convert_encoding((string) $s, 'UTF-8', 'ISO-8859-1');
};
// Numbers follow the user's language: 1.234,5 in Danish and Norwegian, 1,234.5 in English.
$GLOBALS['dashDec'] = ((int) $sprog_id === 2) ? '.' : ',';
$GLOBALS['dashThou'] = ((int) $sprog_id === 2) ? ',' : '.';
$money = function ($v) {
	return number_format(round(abs((float) $v)), 0, $GLOBALS['dashDec'], $GLOBALS['dashThou']) . ' kr';
};

print "<link rel='stylesheet' href='../css/saldi-theme.css?v=7'>";
if ((function_exists('perm_can') ? false : !check_permissions(array(3,4))) || is_null($regnaar)) {
	print "<div style='display: flex; flex-direction: column; padding: 2em 1em; gap: 2em;' class='content'>";
	if (is_null($regnaar)) {
		print "<p>".findtekst('2575|Der er i øjeblikket intet aktivt regnskabsår. Aktivér et regnskabsår gennem System » Indstillinger » Regnskabsår', $sprog_id)."</p>";
	}
	print "<img src='../img/Saldi_Main_Logo.png' style='position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 40%'></img>";
	print "</div>";
	exit;
}

$data = dash_data(array('regnstart' => $regnstart, 'regnslut' => $regnslut, 'regnaar' => $regnaar, 'kontomin' => $kontomin, 'kontomaks' => $kontomaks,
	'sprog_id' => (int) $sprog_id, 'bruger_id' => $brugerId, 'vat' => $vatRegistration, 'online' => $online_people_amount));

// Prototype D ("Som ordrelisten"): flat panels with a grey header band, the KPI strip, tables with share bars. Every block
// keeps its data, switch, order and access key; the charts are drawn by dashboard.js at the box's real width.
$num = function ($v, int $dec = 0) {
	return str_replace('-', '&minus;', number_format((float) $v, $dec, $GLOBALS['dashDec'], $GLOBALS['dashThou']));
};
$bigKr = function ($v) use ($num, $tx, $h) {
	$v = (float) $v;
	return abs($v) >= 1000000 ? $num($v / 1000000, 2) . ' ' . $h($tx('6997|mio. kr')) : $num(round($v)) . ' kr';
};
$delta = function ($d) use ($num) {
	if ($d === null) {
		return '';
	}
	$up = $d >= 0;
	return "<span class='db-delta " . ($up ? 'db-up' : 'db-down') . "'><svg class='ic' viewBox='0 0 24 24' aria-hidden='true'><path d='" . ($up ? 'M5 15h14l-7-8z' : 'm11.998 17 7-8h-14z') . "'/></svg>"
		. "<span class='sr-only'>" . ($up ? '+' : '&minus;') . "</span>" . $num(abs($d), 1) . " %</span>";
};
$gone = function (bool $on) {
	return $on ? '' : ' gone';
};
// A block's header band: bold title, grey subtitle, controls on the right.
$blockHead = function (string $title, string $sub, string $tools) use ($h) {
	return "<div class='db-h'><h2 class='db-t'>" . $h($title) . ($sub !== '' ? "<span class='db-sub'>" . $h($sub) . "</span>" : '') . "</h2>"
		. ($tools !== '' ? "<div class='db-tools'>$tools</div>" : '') . "</div>";
};
// A KPI cell: label band, the number with its change against last year, a grey note.
$kpiCell = function (string $key, string $title, string $sub, array $k, string $note, bool $on) use ($h, $bigKr, $delta, $gone) {
	print "<div class='db-cell" . $gone($on) . "' data-widget='" . $h($key) . "'><div class='db-lbl'>" . $h($title) . ($sub !== '' ? "<small>" . $h($sub) . "</small>" : '') . "</div>"
		. "<div class='db-val'><span>" . $bigKr($k['value']) . "</span>" . $delta($k['delta']) . "</div>"
		. ($note !== '' ? "<div class='db-note'>$note</div>" : '') . "</div>";
};

$noRevenue = !array_sum($data['month']['now']) && !array_sum($data['month']['last']);
$emptyRevenue = "<div class='db-ph'>" . $h($tx('7053|Ingen omsætning endnu')) . "</div>";
$blocks = array();

// Nøgletal: four cells in one panel (addendum 3 §4a)
$blocks['kpis'] = function () use ($data, $widgets, $tx, $h, $money, $kpiCell) {
	print "<section class='blk db-sec db-kpis' data-block='kpis' style='--w:1' aria-label='" . $h($tx('2158|Nøgletal')) . "'>";
	$w = $data['weekkpi'];
	$diff = $w['value'] - $w['last'];
	$kpiCell('weekkpi', $tx('6920|Omsætning denne uge'), $tx('6922|ex moms'), $w, $h(sprintf($tx($diff >= 0 ? '6924|%s mere end samme uge sidste år' : '6925|%s mindre end samme uge sidste år'), $money($diff))), $widgets['weekkpi']);
	$m = $data['revmonth'];
	$diff = $m['value'] - $m['last'];
	$kpiCell('revmonth', $tx('6921|Omsætning denne måned'), $tx('6922|ex moms'), $m, $h($money($diff) . ' ' . $tx($diff >= 0 ? '2385|mere end sidste år til dato' : '2386|mindre end sidste år til dato')), $widgets['revmonth']);
	$y = $data['revyear'];
	$diff = $y['value'] - $y['last'];
	$kpiCell('revyear', $tx('2160|Omsætning for året'), $tx('6922|ex moms'), $y, $h($money($diff) . ' ' . $tx($diff >= 0 ? '2385|mere end sidste år til dato' : '2386|mindre end sidste år til dato')), $widgets['revyear']);
	if ($data['vatcount'] !== null) {
		$v = $data['vatcount'];
		$period = date('j.n.', strtotime($v['from'])) . '&ndash;' . date('j.n.Y', strtotime($v['to']));
		$deadline = date('j.n.Y', strtotime($v['deadline']));
		$kpiCell('vatcount', $tx('6923|Momsangivelse for perioden'), '', $v, $period . ' &middot; ' . $h(sprintf($tx('6926|Frist %s'), $deadline)) . " &middot; <a href='../finans/rapport.php?rapportart=momsangivelse'>" . $h($tx('6927|Se angivelse')) . "</a>", $widgets['vatcount']);
	}
	print "</section>";
};

// Omsætning pr. måned
$blocks['revgraph'] = function () use ($data, $widgets, $tx, $h, $noRevenue, $emptyRevenue, $gone, $blockHead) {
	$mo = $data['month'];
	$tools = $noRevenue ? '' : "<button type='button' class='db-link' id='tblToggle'>" . $h($tx('6944|Vis som tabel')) . "</button>"
		. "<select class='db-fld' id='cmpSel'><option value='both'>" . $h(sprintf($tx('6946|%s mod %s'), $mo['nowLabel'], $mo['lastLabel'])) . "</option><option value='now'>" . $h(sprintf($tx('6947|Kun %s'), $mo['nowLabel'])) . "</option></select>";
	print "<section class='blk db-sec" . $gone($widgets['revgraph']) . "' data-widget='revgraph' data-block='revgraph' style='--w:.6667'>"
		. $blockHead($tx('6928|Omsætning pr. måned'), $tx('6929|ekskl. moms'), $tools) . "<div class='db-b'>"
		. ($noRevenue ? $emptyRevenue : "<div id='monthView'><div class='db-leg'><span><i class='db-sw sw-now'></i>" . $h($mo['nowLabel']) . "</span>"
		. "<span id='legLast'><i class='db-sw sw-last'></i>" . $h($mo['lastLabel'] . ' ' . $tx('6948|(sidste år)')) . "</span><span><i class='db-sw sw-best'></i>" . $h($tx('6949|Bedste måned')) . "</span></div>"
		. "<div class='db-chartbox' id='monthChart'></div></div><div id='monthTbl' hidden></div>") . "</div></section>";
};

// Kunder pr. time
$blocks['customergraph'] = function () use ($widgets, $tx, $h, $gone, $blockHead) {
	$sel = "<select class='db-fld' id='heatSel'>";
	foreach (array(30, 7, 90) as $span) {
		$sel .= "<option value='$span'>" . $h(sprintf($tx('6952|%s dage'), $span)) . "</option>";
	}
	$sel .= "</select>";
	print "<section class='blk db-sec" . $gone($widgets['customergraph']) . "' data-widget='customergraph' data-block='customergraph' style='--w:.3333'>"
		. $blockHead($tx('6951|Kunder pr. time'), '', $sel) . "<div class='db-b'><div class='db-chartbox' id='heat'></div>"
		. "<div class='db-ramp'>" . $h($tx('6953|Få')) . "<span></span><i class='q1'></i><i class='q2'></i><i class='q3'></i><i class='q4'></i><i class='q5'></i><i class='q6'></i><i class='q7'></i><span></span>" . $h($tx('6954|Mange')) . "</div></div></section>";
};

// Omsætning pr. dag
$blocks['revweek'] = function () use ($data, $widgets, $tx, $h, $noRevenue, $emptyRevenue, $gone, $blockHead) {
	$wk = $data['weeks']['list'];
	$sel = '';
	if (!$noRevenue) {
		$sel = "<select class='db-fld' id='weekSel'>";
		foreach ($wk as $wrow) {
			$sel .= "<option value='" . (int) $wrow['no'] . '-' . (int) $wrow['year'] . "'>" . $h(sprintf($tx('6956|Uge %s'), $wrow['no'])) . "</option>";
		}
		$sel .= "</select>";
	}
	print "<section class='blk db-sec" . $gone($widgets['revweek']) . "' data-widget='revweek' data-block='revweek' style='--w:.5'>"
		. $blockHead($tx('6955|Omsætning pr. dag'), $tx('6929|ekskl. moms'), $sel) . "<div class='db-b'>"
		. ($noRevenue ? $emptyRevenue : "<div class='db-leg'><span><i class='db-sw sw-now'></i><span id='wkNow'>" . $h(sprintf($tx('6957|Uge %s, %s'), $wk[0]['no'], $wk[0]['year'])) . "</span></span>"
		. "<span><i class='db-sw sw-last'></i><span id='wkLast'>" . $h(sprintf($tx('6958|Samme uge %s'), $wk[0]['lastYear'])) . "</span></span><span><i class='db-sw sw-best'></i>" . $h($tx('6959|Bedste dag')) . "</span></div>"
		. "<div class='db-chartbox' id='dayChart'></div>") . "</div></section>";
};

// Omsætning pr. varegruppe: the five largest groups and the rest as a table with share bars
$blocks['varegrp_doughnut'] = function () use ($data, $widgets, $tx, $h, $num, $gone, $blockHead) {
	$dn = $data['donut'];
	$tools = $dn['count'] > 5 ? "<a class='db-link' href='../debitor/rapport.php?rapportart=salgsstat'>" . $h(sprintf($tx('6962|Se alle %s'), $dn['count'])) . "</a>" : '';
	print "<section class='blk db-sec" . $gone($widgets['varegrp_doughnut']) . "' data-widget='varegrp_doughnut' data-block='varegrp_doughnut' style='--w:.5'>"
		. $blockHead($tx('6960|Omsætning pr. varegruppe'), $tx('6961|regnskabsåret'), $tools) . "<div class='db-b'>";
	if (!$dn['items']) {
		print "<div class='db-ph'>" . $h($tx('7053|Ingen omsætning endnu')) . "</div></div></section>";
		return;
	}
	$top = max(array_map(function ($it) { return (float) $it[1]; }, $dn['items']));
	print "<table class='dt dt-fixed db-vg'><colgroup><col class='c-name'><col><col class='c-pct'><col class='c-amt'></colgroup>"
		. "<thead><tr><th>" . $h($tx('429|Varegruppe')) . "</th><th></th><th class='dt-num'>" . $h($tx('6975|Andel')) . "</th><th class='dt-num'>" . $h($tx('1166|Omsætning')) . "</th></tr></thead><tbody>";
	foreach ($dn['items'] as $it) {
		$bar = $top > 0 ? round((float) $it[1] / $top * 100, 1) : 0;
		print "<tr><td title='" . $h($it[0]) . "'>" . $h($it[0]) . "</td><td><span class='db-hbar" . ($it[2] ? ' oth' : '') . "' style='width:$bar%'></span></td>"
			. "<td class='dt-num db-pct'>" . $num($it[1], 1) . " %</td><td class='dt-num'>" . $num(round((float) $it[3])) . "</td></tr>";
	}
	print "</tbody><tfoot><tr><td>" . $h($tx('2373|I alt')) . "</td><td></td><td class='dt-num'>" . $num(100, 1) . " %</td><td class='dt-num'>" . $num(round((float) $dn['total'])) . "</td></tr></tfoot></table></div></section>";
};

// Ubetalte salgsfakturaer (§5.9, addendum 3 §6): the due groups on the left, the oldest invoices on the right
$unpaid = $blockAccess['unpaid_invoices'] ? dash_unpaid((string) $baseCurrency) : null;
$blocks['unpaid_invoices'] = function () use ($unpaid, $widgets, $tx, $h, $num, $gone, $blockHead) {
	if ($unpaid === null) {
		return;
	}
	print "<section class='blk db-sec" . $gone($widgets['unpaid_invoices']) . "' data-widget='unpaid_invoices' data-block='unpaid_invoices' style='--w:1'>"
		. $blockHead($tx('7032|Ubetalte salgsfakturaer'), $tx('7700|inkl. moms'), '');
	if (!$unpaid['count']) {
		print "<div class='db-b'><div class='db-ph db-ok'><svg class='ic' viewBox='0 0 24 24' aria-hidden='true'><path d='m10 15.586-3.293-3.293-1.414 1.414L10 18.414l9.707-9.707-1.414-1.414z'/></svg>" . $h($tx('7047|Ingen ubetalte salgsfakturaer')) . "</div></div></section>";
		return;
	}
	$groups = array('late' => $tx('7035|Forfalden over 15 dage'), 'warn' => $tx('7034|Forfalden 1–15 dage'), 'ok' => $tx('7033|Ikke forfalden'));
	$count = (int) $unpaid['count'];
	$stack = '';
	$rows = '';
	foreach ($groups as $g => $name) {
		$sum = (float) $unpaid['groups'][$g]['sum'];
		if ($sum > 0) {
			$stack .= "<i class='sw-$g' data-g='$g' style='flex:" . round($sum, 2) . " 1 0'></i>";
		}
		$rows .= "<tr data-g='$g' tabindex='0'><td><i class='db-dot sw-$g'></i>" . $h($name) . "</td><td class='dt-num'>" . (int) $unpaid['groups'][$g]['n'] . "</td><td class='dt-num'>" . $num($sum, 2) . "</td></tr>";
	}
	print "<div class='db-unp'><div class='db-unp-l'><div class='db-lbl'>" . $h($tx('2373|I alt')) . "</div>"
		. "<div class='db-val'><span>" . $num($unpaid['total'], 2) . " kr</span><small>" . $h($count === 1 ? $tx('7049|1 faktura') : sprintf($tx('7048|%s fakturaer'), $count)) . "</small></div>"
		. "<div class='db-stack' aria-hidden='true'>$stack</div>"
		. "<table class='dt db-grp' id='unpGrp'><thead><tr><th>" . $h($tx('7701|Forfaldsgruppe')) . "</th><th class='dt-num'>" . $h($tx('916|Antal')) . "</th><th class='dt-num'>" . $h($tx('934|Beløb')) . "</th></tr></thead><tbody>$rows</tbody></table></div>";
	print "<div class='db-unp-r'><table class='dt dt-fixed db-inv' id='unpTbl'><colgroup><col class='c-nr'><col><col class='c-due'><col class='c-amt'><col class='c-st'></colgroup>"
		. "<thead><tr><th>" . $h($tx('828|Fakturanr.')) . "</th><th>" . $h($tx('35|Kunde')) . "</th><th>" . $h($tx('1070|Forfald')) . "</th><th class='dt-num'>" . $h($tx('934|Beløb')) . "</th><th>" . $h($tx('494|Status')) . "</th></tr></thead><tbody>";
	foreach ($unpaid['list'] as $i => $o) {   // the oldest of each due group; the first 8 show until a group is picked
		$over = (int) $o['over'];
		$age = $over > 0 ? ($over === 1 ? $tx('7063|1 dag over') : sprintf($tx('7042|%s dage over'), $over)) : ($over === 0 ? $tx('7043|forfalder i dag') : ($over === -1 ? $tx('7064|om 1 dag') : sprintf($tx('7044|om %s dage'), -$over)));
		$href = $h($o['href']);
		$nr = (string) $o['nr'];
		$due = substr((string) $o['due'], 0, 10);
		$due = preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? substr($due, 8, 2) . '-' . substr($due, 5, 2) . '-' . substr($due, 0, 4) : $due;
		print "<tr data-g='" . $h($o['g']) . "' data-href='$href' title='" . $h(sprintf($tx('7065|Åbn %s'), $nr !== '' ? $nr : $o['kunde'])) . "'" . ($nr === '' ? " tabindex='0'" : '') . ($i >= 8 ? ' hidden' : '') . ">"
			. "<td>" . ($nr !== '' ? "<a href='$href'>" . $h($nr) . "</a>" : '') . "</td><td>" . $h($o['kunde']) . "</td><td>" . $h($due) . "</td>"
			. "<td class='dt-num'>" . $num($o['amount'], 2) . "</td><td><i class='db-dot sw-" . $h($o['g']) . "'></i><span class='db-age'>" . $h($age) . "</span></td></tr>";
	}
	print "</tbody></table><div class='db-unp-f'><a class='db-link' href='../debitor/rapport.php?rapportart=openpost'>" . $h(sprintf($tx('7037|Vis alle (%s)'), $count)) . "</a><span class='db-grow'></span>";
	if ((float) $unpaid['unapplied'] != 0) {
		$parts = explode('%s', $tx('7045|Ikke-udlignede indbetalinger og kreditnotaer: %s'), 2);
		print "<span>" . $h($parts[0]) . "<b>" . $num($unpaid['unapplied'], 2) . " kr</b>" . (isset($parts[1]) ? $h($parts[1]) : '') . "</span>"
			. "<a class='db-link' href='../debitor/rapport.php?rapportart=openpost'>" . $h($tx('1066|Udlign')) . "</a>";
	}
	print "</div></div></div></section>";
};

// Små tal: aktive medarbejdere, ufakturerede ordrer (addendum 3 §7), forslag (§7a)
$blocks['small'] = function () use ($data, $widgets, $tx, $h, $num, $gone) {
	$kr = function ($v) use ($num) {
		return $num($v, 2) . ' kr';
	};
	print "<section class='blk db-sec db-small' data-block='small' style='--w:1' aria-label='" . $h($tx('7059|Små tal')) . "'>";
	print "<div class='db-cell" . $gone($widgets['onlineusers']) . "' data-widget='onlineusers'><div class='db-lbl'>" . $h($tx('2379|Aktive medarbejdere')) . "</div>"
		. "<div class='db-val'><span>" . $num((int) $data['online']) . "</span></div><div class='db-note'>" . $h($tx('6965|Aktive inden for den sidste time')) . "</div></div>";
	$o = $data['orders'];
	print "<div class='db-cell" . $gone($widgets['ordercount']) . "' data-widget='ordercount'><div class='db-lbl'>" . $h($tx('2161|Ufakturerede ordrer')) . "<small>" . $h($tx('6922|ex moms')) . "</small></div>"
		. "<div class='db-val'><span>" . $num((int) $o['count']) . "</span><small>" . $kr($o['sum']) . "</small></div>"
		. "<ul class='db-kv'><li><span>" . $h($tx('7072|Leveret for over 14 dage siden')) . "</span><b>" . (int) $o['late']['count'] . ' &middot; ' . $kr($o['late']['sum']) . "</b></li>"
		. ($o['largest'] ? "<li><span>" . $h($tx('7051|Største ufakturerede')) . "</span><b>" . $h($o['largest']['name']) . ' &middot; ' . $kr($o['largest']['sum']) . "</b></li>" : '')
		. "<li><span>" . $h($tx('7073|Klar til fakturering nu')) . "</span><b>" . (int) $o['ready']['count'] . ' &middot; ' . $kr($o['ready']['sum']) . "</b></li></ul>"
		. "<div class='db-acts'><a class='btn btn-sm btn-secondary' href='../debitor/ordreliste.php'>" . $h($tx('6967|Gå til ordrer')) . "</a></div></div>";
	$sl = $data['suggestList'];
	print "<div class='db-cell" . $gone($widgets['suggest']) . "' data-widget='suggest'><div class='db-lbl'>" . $h($tx('6968|Forslag klar til bekræftelse')) . "</div>"
		. "<div class='db-val'><span>" . $num((int) $data['suggest']) . "</span><small>" . $h($tx('6969|Notifikationer med forslag, der venter på dig')) . "</small></div>";
	if ($sl) {
		print "<ul class='db-kv'>";
		foreach ($sl as $sg) {
			print "<li><span>" . $h($sg['title']) . "</span><b>" . $h(mb_substr($sg['body'], 0, 60)) . "</b></li>";
		}
		if ((int) $data['suggest'] > count($sl)) {
			print "<li class='more'><span>" . $h(sprintf($tx('7052|og %s forslag mere'), (int) $data['suggest'] - count($sl))) . "</span></li>";
		}
		print "</ul>";
	}
	print "<div class='db-acts'><button type='button' class='btn btn-sm btn-secondary' onclick=\"try { var b = window.parent.document.getElementById('topbar-bell-btn'); if (b) { b.click(); } } catch (e) {}\">" . $h($tx('6970|Gennemgå forslag')) . "</button></div></div>";
	print "</section>";
};

print "<div class='dash'>";
role_review_card($brugerId, (int) $sprog_id);
print "<div class='grid" . ($hide_dash ? ' all-hidden' : '') . "' id='grid'>";
foreach ($blockOrder as $bid) {
	if (isset($blocks[$bid]) && (!isset($blockAccess[$bid]) || $blockAccess[$bid])) {
		$blocks[$bid]();
	}
}

// POS shortcuts, as before
$pos = db_fetch_array(db_select("SELECT id FROM grupper WHERE art='POS' AND box1>='1' AND fiscal_year='$regnaar'", __FILE__ . " linje " . __LINE__));
if ($pos) {
	print "<section class='blk db-sec db-pos' style='--w:1'>" . $blockHead($tx('2771|POS-muligheder'), '', '') . "<div class='db-b db-links'>"
		. "<a class='btn btn-sm btn-secondary' href='../lager/varer.php?returside=../index/dashboard.php'>" . $h($tx('2584|Åbn vareliste')) . "</a>"
		. "<a class='btn btn-sm btn-secondary' href='../lager/varekort.php?returside=../index/dashboard.php'>" . $h($tx('2585|Opret vare')) . "</a>"
		. "<a class='btn btn-sm btn-secondary' href='../systemdata/posmenuer.php'>" . $h($tx('2586|Menu opsætning')) . "</a>"
		. "<a class='btn btn-sm btn-secondary' href='../debitor/rapport.php'>" . $h($tx('2587|Åbn rapporter')) . "</a></div></section>";
}
print "</div>"; // grid
print "<div class='db-sec dash-hidden' id='dashHidden'><div class='db-ph'><div><b>" . $h($tx('6993|Oversigten er skjult')) . "</b><br>" . $h($tx('6994|Du kan altid slå den til igen.')) . "<br>"
	. "<button type='button' class='btn btn-sm btn-secondary' id='showDash'>" . $h($tx('6995|Vis oversigt')) . "</button></div></div></div>";
print "</div>"; // dash

// "Rediger oversigt" (opened from the user chip menu): a panel at the top right, like the prototype's popover.
print "<div class='db-tip' id='tip' role='status' aria-live='polite'></div><div class='scrim' id='scrim'></div>";
print "<aside class='drawer' id='drawer' role='dialog' aria-modal='true' aria-labelledby='drawerTitle'>"
	. "<header><h2 id='drawerTitle'>" . $h($tx('5605|Rediger oversigt')) . "</h2>"
	. "<button type='button' class='btn btn-icon btn-sm' id='drawerClose' title='" . $h($tx('2172|Luk')) . "' aria-label='" . $h($tx('2172|Luk')) . "'><svg class='ic' viewBox='0 0 24 24' aria-hidden='true'><path d='m16.192 6.344-4.243 4.242-4.242-4.242-1.414 1.414L10.535 12l-4.242 4.242 1.414 1.414 4.242-4.242 4.243 4.242 1.414-1.414L13.364 12l4.242-4.242z'/></svg></button></header>"
	. "<p class='drawer-note'>" . $h($tx('6977|Vælg, hvad du vil se på din forside. Ændringer vises og gemmes med det samme.')) . "</p>"
	. "<div class='body' id='drawerBody'></div>"
	. "<footer><span class='saved' id='savedMsg'><svg class='ic' viewBox='0 0 24 24' aria-hidden='true'><path d='m10 15.586-3.293-3.293-1.414 1.414L10 18.414l9.707-9.707-1.414-1.414z'/></svg>" . $h($tx('3322|Gemt')) . "</span>"
	. "<button type='button' class='btn btn-sm btn-secondary' id='dashReset'>" . $h($tx('5717|Nulstil til standard')) . "</button>"
	. "<button type='button' class='btn btn-sm btn-secondary' id='drawerDone'>" . $h($tx('6697|Færdig')) . "</button></footer></aside>";

// The blocks for the drawer (§8.2/§8.3): id, name, description, the cards inside Nøgletal and Små tal.
$kpiItems = array(array('weekkpi', $u8($tx('6920|Omsætning denne uge')), ''), array('revmonth', $u8($tx('6921|Omsætning denne måned')), ''), array('revyear', $u8($tx('2160|Omsætning for året')), ''));
if ($vatRegistration['registered']) {
	$kpiItems[] = array('vatcount', $u8($tx('520|Momsangivelse')), $u8($tx('6980|Beløb og frist for perioden')));
}
$blockList = array(
	array('id' => 'kpis', 'name' => $u8($tx('2158|Nøgletal')), 'desc' => $u8($tx('7061|Omsætning og moms')), 'items' => $kpiItems),
	array('id' => 'revgraph', 'name' => $u8($tx('6928|Omsætning pr. måned')), 'desc' => $u8($tx('6983|Sammenlignet med sidste år'))),
	array('id' => 'customergraph', 'name' => $u8($tx('6951|Kunder pr. time')), 'desc' => $u8($tx('6985|Hvornår på ugen der er travlt'))),
	array('id' => 'revweek', 'name' => $u8($tx('6955|Omsætning pr. dag')), 'desc' => $u8($tx('6984|Ugen dag for dag'))),
	array('id' => 'varegrp_doughnut', 'name' => $u8($tx('6960|Omsætning pr. varegruppe')), 'desc' => $u8($tx('6986|Fordeling i regnskabsåret'))),
	array('id' => 'unpaid_invoices', 'name' => $u8($tx('7032|Ubetalte salgsfakturaer')), 'desc' => $u8($tx('7050|Forfaldne beløb og hvilke fakturaer det er'))),
	array('id' => 'small', 'name' => $u8($tx('7059|Små tal')), 'desc' => $u8($tx('7060|Medarbejdere, ordrer, forslag')), 'items' => array(
		array('onlineusers', $u8($tx('2379|Aktive medarbejdere')), $u8($tx('6981|Hvem der har været aktive den sidste time'))),
		array('ordercount', $u8($tx('2161|Ufakturerede ordrer')), $u8($tx('7097|Leverede ordrer, der ikke er faktureret'))),
		array('suggest', $u8($tx('6968|Forslag klar til bekræftelse')), $u8($tx('6969|Notifikationer med forslag, der venter på dig'))))),
);
if (!$dashKey('suggestions')) {
	$blockList[6]['items'] = array_values(array_filter($blockList[6]['items'], function ($it) { return $it[0] !== 'suggest'; }));
}
$blockList = array_values(array_filter($blockList, function ($b) use ($blockAccess) { return !isset($blockAccess[$b['id']]) || $blockAccess[$b['id']]; }));
$unpaidJs = null;
if ($unpaid !== null && $unpaid['count']) {
	$unpaidJs = array('groups' => $unpaid['groups'], 'total' => $unpaid['total'], 'count' => $unpaid['count']);
}
// Fiscal months begun by today: later months have no figure yet (no bar, "–" in the tooltip and the table, as the prototype).
$monthsToDate = 0;
for ($i = 0; $i < 12; $i++) {
	if (date('Y-m-01', mktime(0, 0, 0, (int) substr($regnstart, 5, 2) + $i, 1, (int) substr($regnstart, 0, 4))) <= date('Y-m-d')) {
		$monthsToDate++;
	}
}
$locales = array(1 => 'da-DK', 2 => 'en-GB', 3 => 'nb-NO');
$js = array(
	'csrf' => $_SESSION['csrf_token'],
	'locale' => isset($locales[(int) $sprog_id]) ? $locales[(int) $sprog_id] : 'da-DK',
	'widgets' => $widgets, 'hidden' => $hide_dash, 'blocks' => $blockList, 'order' => $blockOrder, 'defaultOrder' => $blockDefaults,
	'defaultOff' => array_keys(array_filter($widgetDefaults, function ($v) { return $v === 'off'; })),
	'month' => !$blockAccess['revgraph'] ? null : array('labels' => array_map($u8, $data['month']['labels']), 'now' => $data['month']['now'], 'last' => $data['month']['last'], 'nowLabel' => $u8($data['month']['nowLabel']), 'lastLabel' => $u8($data['month']['lastLabel']), 'toDate' => $monthsToDate),
	'weeks' => $blockAccess['revweek'] ? $data['weeks'] + array('dayToDate' => (int) date('N')) : null, 'days' => array_map($u8, $data['days']), 'heat' => $blockAccess['customergraph'] ? $data['heat'] : null, 'heatDefault' => '30',
	'unpaid' => $unpaidJs,
	'accounts' => array('kontomin' => $kontomin, 'kontomaks' => $kontomaks, 'canEdit' => $canEditAccounts, 'guide' => 'https://site.saldi.dk/saldi-manualer/omsaetningstal'),
	'txt' => array(
		'avgCust' => $u8($tx('6974|Gns. kunder pr. time')), 'at' => $u8($tx('6996|kl.')), 'vsLast' => $u8($tx('6973|mod sidste år')),
		'showTable' => $u8($tx('6944|Vis som tabel')), 'showGraph' => $u8($tx('6945|Vis som graf')),
		'week' => $u8($tx('6957|Uge %s, %s')), 'sameWeek' => $u8($tx('6958|Samme uge %s')), 'month' => $u8($tx('1217|Måned')), 'change' => $u8($tx('6976|Ændring')),
		'mioShort' => $u8($tx('6998|mio.')), 'thousandShort' => $u8($tx('6999|t.')), 'noRevenue' => $u8($tx('7053|Ingen omsætning endnu')),
		'showDash' => $u8($tx('6978|Vis oversigten')), 'showDashHelp' => $u8($tx('6979|Slå fra, hvis du hellere vil starte på en tom forside')),
		'accounts' => $u8($tx('6987|Omsætningskonti')), 'companyWide' => $u8($tx('6988|Gælder hele regnskabet')), 'fromAcc' => $u8($tx('6989|Fra kontonr.')), 'toAcc' => $u8($tx('6990|Til kontonr.')),
		'accountsHelp' => $u8($tx('6991|Bestemmer, hvilke konti der tæller som omsætning i nøgletal og grafer. Er du i tvivl?')), 'seeGuide' => $u8($tx('6992|Se guiden')),
		'contentOrder' => $u8($tx('7054|Indhold og rækkefølge')), 'dragHint' => $u8($tx('7055|Træk i ⋮⋮ for at flytte')), 'moveLabel' => $u8($tx('7056|Flyt %s. Alt+pil op/ned')),
		'movedTo' => $u8($tx('7057|%s flyttet til plads %s af %s')), 'shownOf' => $u8($tx('7058|%s af %s vises')), 'showCards' => $u8($tx('7062|Vis kort i %s')),
		'dueOk' => $u8($tx('7033|Ikke forfalden')), 'dueWarn' => $u8($tx('7034|Forfalden 1–15 dage')), 'dueLate' => $u8($tx('7035|Forfalden over 15 dage')),
		'amount' => $u8($tx('934|Beløb')), 'count' => $u8($tx('916|Antal')),
	),
);
print "<script>window.SALDI_DASH = " . json_encode($js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR) . ";</script>\n";
print "<script src='../javascript/dashboard.js?v=6'></script>\n";
