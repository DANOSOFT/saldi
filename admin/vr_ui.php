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
// Page frame. $crumbs = [[text, href], ..., text]; $actions = html
function vr_open($crumbs, $h1, $lead, $actions = '', $pill = '') {
	global $buttonColor, $buttonTxtColor;
	print "<link rel=\"stylesheet\" href=\"../css/vis_regnskaber.css?v=5.1.1\">\n";
	print "<div class=\"vr\" style=\"--vr-user:".vr_h($buttonColor).";--vr-user-text:".vr_h($buttonTxtColor).";\">\n";
	print "<div class=\"vr-head\">\n<div class=\"vr-title\">\n<nav class=\"vr-bc\">";
	foreach ($crumbs as $i => $c) {
		if ($i) print "<i>/</i>";
		if (is_array($c)) print "<a href=\"".vr_h($c[1])."\">".vr_h($c[0])."</a>"; else print "<b>".vr_h($c)."</b>";
	}
	print "</nav>\n<h1>".vr_h($h1).($pill ? " <span class=\"vr-pill\">".vr_h($pill)."</span>" : "")."</h1>\n";
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
function vr_close() { print "</div>\n"; }
}
?>
