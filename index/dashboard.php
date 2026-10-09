<!doctype html>
<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/dashboard.php --- lap 4.1.1 --- 2025.08.13 ---
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
// Copyright (c) 2024-2025 saldi.dk aps
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
// 20261006 Sawaneh Onboarding part 1: the "Kom godt i gang" card at the top (Requirements_onboarding_welcome_EN.md §7).
// 20261008 Sawaneh The VAT widget is hidden when not VAT registered and shows the current VAT period (decision 19).
// 20261008 Sawaneh Rebuilt on Adam's prototype_dashboard_tema.html v5: KPI cards with trend, revenue per month and per day,
//                  customers per hour, revenue per item group, orders, active users and suggestions on the Saldi theme
//                  (light/dark); "Rediger oversigt" is a drawer whose choices are per user and saved at once; the numbers
//                  come from dashboardIncludes/dashData.php. Texts 6920-6929, 6944-6949, 6951-6999.
@session_start();
$s_id = session_id();
ob_start();

global $sprog_id;

$css = "../css/dashboard.css?v=3";
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
include_once("../includes/onboarding.php");
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
$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$brugerId = (int) $bruger_id;
$canEditAccounts = function_exists('perm_can') ? perm_can('settings.finance', 'write') : (substr((string) $rettigheder, 1, 1) === '1');

// The widgets (prototype keys = the old dashboard_toggles names; weekkpi is new and starts from the old revweek).
$widgetDefaults = array('weekkpi' => 'on', 'revmonth' => 'on', 'revyear' => 'on', 'vatcount' => 'on', 'onlineusers' => 'off', 'ordercount' => 'on', 'suggest' => 'on',
	'revgraph' => 'on', 'revweek' => 'on', 'customergraph' => 'off', 'varegrp_doughnut' => 'on');
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
	if (!$r) {
		$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'dashboard_toggles' and var_name = '$k' and coalesce(user_id, 0) = 0 order by id desc limit 1", __FILE__ . " linje " . __LINE__));
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
			update_settings_value((string) $_POST['key'], 'dashboard_toggles', !empty($_POST['on']) ? 'on' : 'off', 'Dashboard widget (per user)', $brugerId);
		} elseif ($action === 'hide') {
			update_settings_value('hide_dash', 'dashboard', !empty($_POST['hidden']) ? 1 : 0, 'Whether the overview is hidden for the user', $brugerId);
		} elseif ($action === 'reset') {
			db_modify("delete from settings where var_grp = 'dashboard_toggles' and user_id = $brugerId", __FILE__ . " linje " . __LINE__);
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

print "<link rel='stylesheet' href='../css/saldi-theme.css?v=6'>";
if (!check_permissions(array(3,4)) || is_null($regnaar)) {
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

/**
 * A KPI card: number counting up, change against last year, trend line, a note.
 */
function dash_kpi_card(string $key, string $title, string $sub, array $k, string $note, bool $on): void
{
	global $h;
	$v = (float) $k['value'];
	$fmt = abs($v) >= 1000000 ? 'mio' : 'kr';
	$delta = '';
	if ($k['delta'] !== null) {
		$up = $k['delta'] >= 0;
		$arrow = $up ? '<path d="M12 19V5m-6 6 6-6 6 6"/>' : '<path d="M12 5v14m6-6-6 6-6-6"/>';
		$delta = "<span class='delta " . ($up ? 'up' : 'down') . "'><svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'>$arrow</svg>" . number_format(abs($k['delta']), 1, $GLOBALS['dashDec'], $GLOBALS['dashThou']) . " %</span>";
	}
	$spark = implode(',', array_map(function ($x) { return round((float) $x); }, $k['spark']));
	print "<section data-widget='" . $h($key) . "' class='card kpi" . ($on ? '' : ' gone') . "'><h3>" . $h($title) . ($sub !== '' ? " <span class='sub'>" . $h($sub) . "</span>" : '') . "</h3>"
		. "<div class='row'><div><div class='val' data-count='" . round($v) . "' data-fmt='$fmt'>0 kr</div>$delta</div><svg class='spark' viewBox='0 0 100 40' data-spark='$spark'></svg></div>"
		. ($note !== '' ? "<div class='note'>$note</div>" : '') . "</section>\n";
}

print "<div class='dash'>";
onb_render_card($brugerId, (int) $sprog_id);
role_review_card($brugerId, (int) $sprog_id);
print "<div class='grid" . ($hide_dash ? ' all-hidden' : '') . "' id='grid'>";

// Key figures
print "<div class='krow'>";
$w = $data['weekkpi'];
$diff = $w['value'] - $w['last'];
dash_kpi_card('weekkpi', $tx('6920|Omsætning denne uge'), $tx('6922|ex moms'), $w, $h(sprintf($tx($diff >= 0 ? '6924|%s mere end samme uge sidste år' : '6925|%s mindre end samme uge sidste år'), $money($diff))), $widgets['weekkpi']);
$m = $data['revmonth'];
$diff = $m['value'] - $m['last'];
dash_kpi_card('revmonth', $tx('6921|Omsætning denne måned'), $tx('6922|ex moms'), $m, $h($money($diff) . ' ' . $tx($diff >= 0 ? '2385|mere end sidste år til dato' : '2386|mindre end sidste år til dato')), $widgets['revmonth']);
$y = $data['revyear'];
$diff = $y['value'] - $y['last'];
dash_kpi_card('revyear', $tx('2160|Omsætning for året'), $tx('6922|ex moms'), $y, $h($money($diff) . ' ' . $tx($diff >= 0 ? '2385|mere end sidste år til dato' : '2386|mindre end sidste år til dato')), $widgets['revyear']);
if ($data['vatcount'] !== null) {
	$v = $data['vatcount'];
	$deadline = date('j.n.Y', strtotime($v['deadline']));
	dash_kpi_card('vatcount', $tx('6923|Momsangivelse for perioden'), '', $v, $h(sprintf($tx('6926|Frist %s'), $deadline)) . " · <a class='link' href='../finans/rapport.php?rapportart=momsangivelse'>" . $h($tx('6927|Se angivelse')) . "</a>", $widgets['vatcount']);
}
print "</div>";

// Revenue per month + customers per hour
$mo = $data['month'];
print "<div class='crow'>";
print "<section data-widget='revgraph' class='card w8" . ($widgets['revgraph'] ? '' : ' gone') . "' data-anim='month'>"
	. "<div class='head'><h3>" . $h($tx('6928|Omsætning pr. måned')) . " <span class='sub'>" . $h($tx('6929|ekskl. moms')) . "</span></h3>"
	. "<div class='tools'><button type='button' class='link' id='tblToggle'>" . $h($tx('6944|Vis som tabel')) . "</button>"
	. "<select class='sel' id='cmpSel'><option value='both'>" . $h(sprintf($tx('6946|%s mod %s'), $mo['nowLabel'], $mo['lastLabel'])) . "</option><option value='now'>" . $h(sprintf($tx('6947|Kun %s'), $mo['nowLabel'])) . "</option></select></div></div>"
	. "<div class='swap'><div id='monthView'><div class='legend'><span><i style='background:var(--s1)'></i>" . $h($mo['nowLabel']) . "</span><span id='legLast'><i style='background:var(--context)'></i>" . $h($mo['lastLabel'] . ' ' . $tx('6948|(sidste år)')) . "</span><span><i style='background:var(--accent)'></i>" . $h($tx('6949|Bedste måned')) . "</span></div>"
	. "<svg class='chart' id='monthChart' viewBox='0 0 760 262' role='img' aria-label='" . $h($tx('6928|Omsætning pr. måned')) . "'></svg></div><table class='tbl off' id='monthTbl'></table></div></section>";
print "<section data-widget='customergraph' class='card w4" . ($widgets['customergraph'] ? '' : ' gone') . "' data-anim='heat'>"
	. "<div class='head'><h3>" . $h($tx('6951|Kunder pr. time')) . "</h3><select class='sel' id='heatSel'>";
foreach (array(30, 7, 90) as $span) {
	print "<option value='$span'>" . $h(sprintf($tx('6952|%s dage'), $span)) . "</option>";
}
print "</select></div><svg class='chart' id='heat' viewBox='0 0 330 262' role='img' aria-label='" . $h($tx('6951|Kunder pr. time')) . "'></svg>"
	. "<div class='ramp'>" . $h($tx('6953|Få')) . " <i style='background:var(--q1)'></i><i style='background:var(--q2)'></i><i style='background:var(--q3)'></i><i style='background:var(--q4)'></i><i style='background:var(--q5)'></i><i style='background:var(--q6)'></i><i style='background:var(--q7)'></i> " . $h($tx('6954|Mange')) . "</div></section>";
print "</div>";

// Revenue per day + per item group
$wk = $data['weeks']['list'];
print "<div class='crow'>";
print "<section data-widget='revweek' class='card w6" . ($widgets['revweek'] ? '' : ' gone') . "' data-anim='day'>"
	. "<div class='head'><h3>" . $h($tx('6955|Omsætning pr. dag')) . " <span class='sub'>" . $h($tx('6929|ekskl. moms')) . "</span></h3><select class='sel' id='weekSel'>";
foreach ($wk as $wrow) {
	print "<option value='" . $wrow['no'] . '-' . $wrow['year'] . "'>" . $h(sprintf($tx('6956|Uge %s'), $wrow['no'])) . "</option>";
}
print "</select></div><div class='legend'><span><i style='background:var(--s1)'></i><span id='wkNow'>" . $h(sprintf($tx('6957|Uge %s, %s'), $wk[0]['no'], $wk[0]['year'])) . "</span></span><span><i style='background:var(--context)'></i>" . $h(sprintf($tx('6958|Samme uge %s'), $wk[0]['lastYear'])) . "</span><span><i style='background:var(--accent)'></i>" . $h($tx('6959|Bedste dag')) . "</span></div>"
	. "<svg class='chart' id='dayChart' viewBox='0 0 560 222' role='img' aria-label='" . $h($tx('6955|Omsætning pr. dag')) . "'></svg></section>";
$dn = $data['donut'];
print "<section data-widget='varegrp_doughnut' class='card w6" . ($widgets['varegrp_doughnut'] ? '' : ' gone') . "' data-anim='donut'>"
	. "<div class='head'><h3>" . $h($tx('6960|Omsætning pr. varegruppe')) . " <span class='sub'>" . $h($tx('6961|regnskabsåret')) . "</span></h3>"
	. ($dn['count'] > 5 ? "<a class='link' href='../debitor/rapport.php?rapportart=salgsstat'>" . $h(sprintf($tx('6962|Se alle %s'), $dn['count'])) . "</a>" : '') . "</div>"
	. ($dn['items'] ? "<div class='donut-wrap' id='donutWrap'><svg class='donut' id='donut' viewBox='0 0 176 176' role='img' aria-label='" . $h($tx('6960|Omsætning pr. varegruppe')) . "'></svg><ul id='donutLeg'></ul></div>" : "<div class='ph'>" . $h($tx('6961|regnskabsåret')) . ": 0 kr</div>") . "</section>";
print "</div>";

// Users, orders, suggestions
print "<div class='krow'>";
print "<section data-widget='onlineusers' class='card w4" . ($widgets['onlineusers'] ? '' : ' gone') . "'><h3>" . $h($tx('2379|Aktive medarbejdere')) . "</h3><div class='val' data-count='" . (int) $data['online'] . "' data-fmt='int'>0</div><div class='note'>" . $h($tx('6965|Aktive inden for den sidste time')) . "</div></section>";
print "<section data-widget='ordercount' class='card w4" . ($widgets['ordercount'] ? '' : ' gone') . "'><h3>" . $h($tx('2161|Ufakturerede ordrer')) . " <span class='sub'>" . $h($tx('6966|30 dage')) . "</span></h3><div class='val'><span data-count='" . (int) $data['orders']['count'] . "' data-fmt='int'>0</span> <small>· " . $h($money($data['orders']['sum'])) . "</small></div><div class='note'><a class='link' href='../debitor/ordreliste.php'>" . $h($tx('6967|Gå til ordrer')) . "</a></div></section>";
print "<section data-widget='suggest' class='card w4 cta" . ($widgets['suggest'] ? '' : ' gone') . "'><h3>" . $h($tx('6968|Forslag klar til bekræftelse')) . "</h3><div class='val' data-count='" . (int) $data['suggest'] . "' data-fmt='int'>0</div><div class='note'>" . $h($tx('6969|Notifikationer med forslag, der venter på dig')) . "</div>"
	. "<button type='button' class='btn' onclick=\"try { var b = window.parent.document.getElementById('topbar-bell-btn'); if (b) { b.click(); } } catch (e) {}\">" . $h($tx('6970|Gennemgå forslag')) . " <svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'><path d='M5 12h14m-6-6 6 6-6 6'/></svg></button></section>";
print "</div>";

// POS shortcuts, as before
$pos = db_fetch_array(db_select("SELECT id FROM grupper WHERE art='POS' AND box1>='1' AND fiscal_year='$regnaar'", __FILE__ . " linje " . __LINE__));
if ($pos) {
	print "<div class='krow'><section class='card'><h3>" . $h($tx('2771|POS-muligheder')) . "</h3><div class='pos-links'>"
		. "<a href='../lager/varer.php?returside=../index/dashboard.php'>" . $h($tx('2584|Åbn vareliste')) . "</a>"
		. "<a href='../lager/varekort.php?returside=../index/dashboard.php'>" . $h($tx('2585|Opret vare')) . "</a>"
		. "<a href='../systemdata/posmenuer.php'>" . $h($tx('2586|Menu opsætning')) . "</a>"
		. "<a href='../debitor/rapport.php'>" . $h($tx('2587|Åbn rapporter')) . "</a></div></section></div>";
}
print "</div>"; // grid
print "<div class='card in ready dash-hidden' id='dashHidden'><div class='ph'><div><b style='color:var(--text);font-size:15px'>" . $h($tx('6993|Oversigten er skjult')) . "</b><br>" . $h($tx('6994|Du kan altid slå den til igen.')) . "<br><button type='button' class='pbtn' id='showDash' style='margin-top:14px'>" . $h($tx('6995|Vis oversigt')) . "</button></div></div></div>";
print "</div>"; // dash

print "<div class='tip' id='tip' role='status' aria-live='polite'></div><div class='scrim' id='scrim'></div>";
print "<aside class='drawer' id='drawer' role='dialog' aria-modal='true' aria-labelledby='drawerTitle'>"
	. "<header><div><h2 id='drawerTitle'>" . $h($tx('5605|Rediger oversigt')) . "</h2><p>" . $h($tx('6977|Vælg, hvad du vil se på din forside. Ændringer vises og gemmes med det samme.')) . "</p></div>"
	. "<button type='button' class='ibtn' id='drawerClose' aria-label='" . $h($tx('2172|Luk')) . "'><svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.2' stroke-linecap='round'><path d='M6 6l12 12M18 6 6 18'/></svg></button></header>"
	. "<div class='body' id='drawerBody'></div>"
	. "<footer><button type='button' class='link' id='dashReset'>" . $h($tx('5717|Nulstil til standard')) . "</button><span class='saved' id='savedMsg'><svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'><path d='m5 12 5 5 9-10'/></svg>" . $h($tx('3322|Gemt')) . "</span><span class='grow'></span><button type='button' class='pbtn' id='drawerDone'>" . $h($tx('6697|Færdig')) . "</button></footer></aside>";

$groups = array(
	array('g' => $u8($tx('2158|Nøgletal')), 'items' => array(
		array('weekkpi', $u8($tx('6920|Omsætning denne uge')), ''), array('revmonth', $u8($tx('6921|Omsætning denne måned')), ''), array('revyear', $u8($tx('2160|Omsætning for året')), ''),
		array('vatcount', $u8($tx('520|Momsangivelse')), $u8($tx('6980|Beløb og frist for perioden'))), array('onlineusers', $u8($tx('2379|Aktive medarbejdere')), $u8($tx('6981|Hvem der har været aktive den sidste time'))),
		array('ordercount', $u8($tx('2161|Ufakturerede ordrer')), $u8($tx('6982|Ordrer fra de sidste 30 dage'))), array('suggest', $u8($tx('6968|Forslag klar til bekræftelse')), $u8($tx('6969|Notifikationer med forslag, der venter på dig'))))),
	array('g' => $u8($tx('2162|Grafer')), 'items' => array(
		array('revgraph', $u8($tx('6928|Omsætning pr. måned')), $u8($tx('6983|Sammenlignet med sidste år'))), array('revweek', $u8($tx('6955|Omsætning pr. dag')), $u8($tx('6984|Ugen dag for dag'))),
		array('customergraph', $u8($tx('6951|Kunder pr. time')), $u8($tx('6985|Hvornår på ugen der er travlt'))), array('varegrp_doughnut', $u8($tx('6960|Omsætning pr. varegruppe')), $u8($tx('6986|Fordeling i regnskabsåret'))))),
);
if (!$vatRegistration['registered']) {
	$groups[0]['items'] = array_values(array_filter($groups[0]['items'], function ($it) { return $it[0] !== 'vatcount'; }));
}
$donutItems = array();
foreach ($data['donut']['items'] as $it) {
	$donutItems[] = array($u8($it[0]), $it[1], $it[2]);
}
$locales = array(1 => 'da-DK', 2 => 'en-GB', 3 => 'nb-NO');
$js = array(
	'csrf' => $_SESSION['csrf_token'],
	'locale' => isset($locales[(int) $sprog_id]) ? $locales[(int) $sprog_id] : 'da-DK',
	'widgets' => $widgets, 'hidden' => $hide_dash, 'widgetGroups' => $groups,
	'month' => array('labels' => array_map($u8, $data['month']['labels']), 'now' => $data['month']['now'], 'last' => $data['month']['last'], 'nowLabel' => $u8($data['month']['nowLabel']), 'lastLabel' => $u8($data['month']['lastLabel'])),
	'weeks' => $data['weeks'], 'days' => array_map($u8, $data['days']), 'heat' => $data['heat'], 'heatDefault' => '30',
	'donut' => array('items' => $donutItems, 'total' => $data['donut']['total']),
	'accounts' => array('kontomin' => $kontomin, 'kontomaks' => $kontomaks, 'canEdit' => $canEditAccounts, 'guide' => 'https://site.saldi.dk/saldi-manualer/omsaetningstal'),
	'txt' => array(
		'now' => $u8($tx('6971|I år')), 'last' => $u8($tx('6972|Sidste år')), 'vsLast' => $u8($tx('6973|mod sidste år')), 'avgCust' => $u8($tx('6974|Gns. kunder pr. time')), 'at' => $u8($tx('6996|kl.')),
		'share' => $u8($tx('6975|Andel')), 'revenue' => $u8($tx('1166|Omsætning')), 'showTable' => $u8($tx('6944|Vis som tabel')), 'showGraph' => $u8($tx('6945|Vis som graf')),
		'week' => $u8($tx('6957|Uge %s, %s')), 'sameWeek' => $u8($tx('6958|Samme uge %s')), 'month' => $u8($tx('1217|Måned')), 'change' => $u8($tx('6976|Ændring')), 'total' => $u8($tx('6963|mio. kr i alt')),
		'mio' => $u8($tx('6997|mio. kr')), 'mioShort' => $u8($tx('6998|mio.')), 'thousandShort' => $u8($tx('6999|t.')),
		'showDash' => $u8($tx('6978|Vis oversigten')), 'showDashHelp' => $u8($tx('6979|Slå fra, hvis du hellere vil starte på en tom forside')),
		'accounts' => $u8($tx('6987|Omsætningskonti')), 'companyWide' => $u8($tx('6988|Gælder hele regnskabet')), 'fromAcc' => $u8($tx('6989|Fra kontonr.')), 'toAcc' => $u8($tx('6990|Til kontonr.')),
		'accountsHelp' => $u8($tx('6991|Bestemmer, hvilke konti der tæller som omsætning i nøgletal og grafer. Er du i tvivl?')), 'seeGuide' => $u8($tx('6992|Se guiden')),
	),
);
print "<script>window.SALDI_DASH = " . json_encode($js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR) . ";</script>\n";
print "<script src='../javascript/dashboard.js?v=1'></script>\n";
