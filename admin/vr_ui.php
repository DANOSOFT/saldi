<?php
// --- admin/vr_ui.php --- ver 5.1.0 --- 2026.10.10 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR New. Shared view helpers for the admin-layer pages that use the new page layout
//                  (vis_regnskaber.php, regnskab.php, partnere.php): escaping that follows the database charset,
//                  number/date formatting, da/en texts without the uncached findtekst('plain text') path,
//                  and the page frame (head/tabs/foot).

if (!function_exists('vr_h')) {
function vr_cs() { global $db_encode; return ($db_encode == 'UTF8') ? 'UTF-8' : 'ISO-8859-1'; }
function vr_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, vr_cs()); }
function vr_num($n) { return number_format((int)$n, 0, ',', '.'); }
function vr_t($da, $en) {
	global $sprog_id, $db_encode;
	$t = ($sprog_id == 2) ? $en : $da;
	return ($db_encode == 'UTF8') ? $t : mb_convert_encoding($t, 'ISO-8859-1', 'UTF-8');
}
function vr_rel($ts) { # relative time; $ts unix time or 'Y-m-d H:i:s'
	if (!$ts) return '–';
	if (!is_numeric($ts)) $ts = strtotime($ts);
	if (!$ts) return '–';
	$days = (int) floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', $ts))) / 86400);
	if ($days <= 0) return vr_t('i dag', 'today');
	if ($days == 1) return vr_t('i går', 'yesterday');
	if ($days < 7)   return $days.' '.vr_t('dage siden', 'days ago');
	if ($days < 60)  { $w = (int) round($days/7); return $w.' '.($w==1 ? vr_t('uge siden', 'week ago') : vr_t('uger siden', 'weeks ago')); }
	if ($days < 400) { $mo = (int) round($days/30); return $mo.' '.vr_t('mdr. siden', 'months ago'); }
	$yr = (int) floor($days/365); return $yr.' '.vr_t('år siden', 'years ago');
}
function vr_url($page, $params) {
	$params = array_filter($params, fn($v) => isset($v) && $v !== '' && $v !== false);
	return $page.($params ? '?'.http_build_query($params) : '');
}
function vr_kind($kind) { return $kind == 'koncern' ? vr_t('Koncern', 'Group') : vr_t('Bogholder/revisor', 'Accountant'); }
// ---------- shell: sidebar in the user's colour + topbar, as in the approved prototypes ----------
// Menu entries follow index/admin_menu.php (same rights and gating). Partner users get the partner menu.
function vr_menu() {
	global $sprog_id, $revisorregnskab, $forhandlerregnskab, $mastername;
	$u = partner_current_user();
	$items = array();
	if ($u['is_operator'] || !$u['partner']) {
		$full = (!isset($revisorregnskab) && !isset($forhandlerregnskab)) || !empty($revisorregnskab) || !empty($forhandlerregnskab);
		$r_ap = db_fetch_array(db_select("select var_value from settings where var_name='useAdminPanel'", __FILE__ . " linje " . __LINE__));
		$items[] = array(vr_t('Regnskaber','Accounts'), '../admin/vis_regnskaber.php', 'list', true);
		if ($u['is_operator']) $items[] = array(vr_t('Bogholdere og koncerner','Accountants and groups'), '../admin/partnere.php', 'users', true);
		$items[] = array(findtekst('339|Opret regnskab', $sprog_id), '../admin/opret.php', 'plus', $u['admin'] == 'on' || $u['oprette'] == 'on');
		if ($full) {
			$items[] = array(findtekst('341|Slet regnskab', $sprog_id), '../admin/slet_regnskab.php', 'trash', $u['admin'] == 'on' || $u['slette'] == 'on');
			$items[] = array(findtekst('777|Brugere', $sprog_id), '../admin/admin_brugere.php', 'user', true);
			$items[] = array(findtekst('613|Indstillinger', $sprog_id), '../admin/admin_settings.php', 'gear', true);
			if ($r_ap && $r_ap['var_value']) $items[] = array('Admin Panel', '../admin/admin_panel.php', 'shield', true);
			if (isset($mastername) && $mastername == "ROTARY") $items[] = array(findtekst('567|Kortbetalinger', $sprog_id), '../admin/bankfordeling.php', 'card', true);
		}
	} else {
		$items[] = array(vr_t('Mine regnskaber','My accounts'), '../admin/vis_regnskaber.php', 'list', true);
		$items[] = array(vr_t('Mit firma','My firm'), '../admin/partnere.php', 'users', true);
	}
	return $items;
}
function vr_icon($k) {
	$d = array(
		'list' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 4v16"/>',
		'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17" cy="9" r="2.5"/><path d="M15.5 14.5A5 5 0 0 1 22 19"/>',
		'plus' => '<path d="M12 5v14M5 12h14"/>', 'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
		'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
		'shield' => '<path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/>', 'card' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/>',
		'out' => '<path d="M10 17l5-5-5-5M15 12H3M21 3v18"/>', 'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .8-1 1.5M12 17h.01"/>',
	);
	return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.(isset($d[$k]) ? $d[$k] : $d['list']).'</svg>';
}
// Page frame with shell. $crumbs = [[text, href], ..., text]; $actions = html
function vr_open($crumbs, $h1, $lead, $actions = '', $pill = '') {
	global $buttonColor, $buttonTxtColor, $sprog_id, $version, $brugernavn;
	if (!isset($version)) { $version = ''; if (file_exists("../includes/version.php")) include("../includes/version.php"); }
	$u = partner_current_user();
	$self = basename($_SERVER['PHP_SELF']);
	print "<link rel=\"stylesheet\" href=\"../css/vis_regnskaber.css?v=5.1.2\">\n";
	print "<div class=\"vr-app\" style=\"--vr-user:".vr_h($buttonColor).";--vr-user-text:".vr_h($buttonTxtColor).";\">\n";
	// sidebar
	print "<aside class=\"vr-side\"><div class=\"vr-logo\"><span class=\"vr-mark\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"#fff\" stroke-width=\"3\" stroke-linecap=\"round\"><path d=\"M16 5H10a4 4 0 0 0 0 8h4a4 4 0 0 1 0 8H8\"/></svg></span>Saldi</div>";
	print "<div class=\"vr-grp\">".($u['is_operator'] ? vr_t('Operatørpanel','Operator panel') : ($u['partner'] ? vr_t('Bogholderpanel','Accountant panel') : vr_t('Administration','Administration')))."</div><nav>";
	foreach (vr_menu() as $it) {
		$on = (basename($it[1]) == $self) || ($self == 'regnskab.php' && basename($it[1]) == 'vis_regnskaber.php');
		if ($it[3]) print "<a class=\"vr-nav".($on ? " vr-on" : "")."\" href=\"".vr_h($it[1])."\">".vr_icon($it[2]).vr_h($it[0])."</a>";
		else print "<span class=\"vr-nav vr-dim\">".vr_icon($it[2]).vr_h($it[0])."</span>";
	}
	print "</nav><div class=\"vr-spacer\"></div><hr><a class=\"vr-nav\" href=\"http://saldi.dk/dok/komigang.html\" target=\"_blank\">".vr_icon('help').findtekst('92|Vejledning', $sprog_id)."</a><a class=\"vr-nav\" href=\"../index/logud.php\" accesskey=\"L\">".vr_icon('out').findtekst('93|Log ud', $sprog_id)."</a><div class=\"vr-ver\">Saldi version ".vr_h($version)."</div></aside>\n";
	// column: topbar + main
	print "<div class=\"vr-col\"><div class=\"vr-top\"><nav class=\"vr-bc\" aria-label=\"Du er her\">";
	foreach ($crumbs as $i => $c) { if ($i) print "<i>/</i>"; if (is_array($c)) print "<a href=\"".vr_h($c[1])."\">".vr_h($c[0])."</a>"; else print "<b>".vr_h($c)."</b>"; }
	$ini = strtoupper(mb_substr((string)$brugernavn, 0, 2, vr_cs()));
	$sub = $u['is_operator'] ? vr_t('Saldi · Operatør','Saldi · Operator') : ($u['partner'] ? $u['partner']['name'].' · '.($u['partner']['partner_role'] == 'owner' ? vr_t('Ejer','Owner') : vr_t('Medarbejder','Employee')) : vr_t('Administration','Administration'));
	print "</nav><div class=\"vr-grow\"></div><div class=\"vr-chip\"><span class=\"vr-av\">".vr_h($ini)."</span><span class=\"vr-who\"><span>".vr_h($brugernavn)."</span><small>".vr_h($sub)."</small></span></div></div>\n";
	print "<main class=\"vr\">\n<div class=\"vr-head\">\n<div class=\"vr-title\">\n<h1>".vr_h($h1).($pill ? " <span class=\"vr-pill\">".vr_h($pill)."</span>" : "")."</h1>\n";
	if ($lead) print "<p class=\"vr-lead\">".$lead."</p>\n";
	print "</div>\n<div class=\"vr-acts\">$actions</div>\n</div>\n";
}
function vr_tabs($tabs, $active) { # $tabs = [key => [label, href, count|null]]
	print "<div class=\"vr-tabs\" role=\"tablist\">";
	foreach ($tabs as $k => $t) print "<a class=\"vr-tab\" role=\"tab\" aria-selected=\"".($k == $active ? 'true' : 'false')."\" href=\"".vr_h($t[1])."\">".vr_h($t[0]).(isset($t[2]) && $t[2] !== null ? " <span class=\"vr-cnt\">".(int)$t[2]."</span>" : "")."</a>";
	print "</div>\n";
}
function vr_note($lines, $kind = 'ok') {
	if (!$lines) return;
	print "<div class=\"vr-note vr-note-$kind\" role=\"status\">";
	foreach ((array)$lines as $l) print "<div>".vr_h($l)."</div>";
	print "</div>\n";
}
function vr_close() { print "</main></div></div>\n"; }
}
?>
