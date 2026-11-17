<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/flytteliste.php --- ver 5.1.0 --- 2026.11.13
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
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// The move list (flytteliste, WP10-2, K17 and K55): the day's web-order lines drawn from another
// lager than the one the order ships from, to print for the stores. A report only: it writes nothing.
//
// 20261113 CL/MJ SD-760: created (WP10-2).

@session_start();
$s_id = session_id();
$css = '../css/standard.css';
$title = 'Flytteliste';
$modulnr = 15;

include(__DIR__ . '/../includes/connect.php');
include(__DIR__ . '/../includes/online.php');
include_once(__DIR__ . '/../includes/std_func.php');
require_once __DIR__ . '/../includes/shopify/config.php';
require_once __DIR__ . '/../includes/shopify/moveList.php';
require_once __DIR__ . '/../systemdata/shopify/adminCommon.php';

$day = is_string($_GET['dag'] ?? null) ? $_GET['dag'] : date('Y-m-d');
$shipLager = is_string($_GET['lager'] ?? null) && ctype_digit($_GET['lager']) && strlen($_GET['lager']) <= 9 ? (int)$_GET['lager'] : null;

print "<style>
	.flytteliste { margin: 8px; border-collapse: collapse; }
	.flytteliste th, .flytteliste td { padding: 3px 8px; border-bottom: 1px solid #ccc; text-align: left; }
	.flytteliste td.antal { text-align: right; }
	@media print { form.flytteliste-valg, a.flytteliste-tilbage { display: none; } .flytteliste { font-size: 11pt; } }
</style>\n";
print "<h2 style='margin:8px'>" . shopify_admin_t('shopify.movelist.title|Flytteliste') . "</h2>\n";
if (!shopify_schema_ready()) {
	print "<p style='margin:8px'>" . shopify_admin_t('shopify.admin.not_installed|Shopify-modulet er ikke installeret på dette regnskab.') . "</p>\n";
	exit;
}
$names = shopify_move_list_lager_names();
print "<p style='margin:8px'>" . shopify_admin_t('shopify.movelist.help|Webordrelinjer trukket fra et andet lager end det, ordren sendes fra.') . "</p>\n";
print "<form class='flytteliste-valg' method='get' style='margin:8px'>";
print shopify_admin_t('shopify.movelist.day|Dato') . " <input type='date' name='dag' value='" . shopify_admin_h($day) . "'> ";
print shopify_admin_t('shopify.movelist.ship_lager|Sendes fra') . " <select name='lager'><option value=''>" . shopify_admin_t('shopify.movelist.all|Alle') . "</option>";
foreach ($names as $nr => $navn) {
	$selected = $shipLager === $nr ? ' selected' : '';
	print "<option value='" . (int)$nr . "'$selected>" . shopify_admin_h("$nr $navn") . "</option>";
}
print "</select> <input type='submit' value='" . shopify_admin_t('shopify.movelist.show|Vis') . "'> ";
print "<input type='button' value='" . shopify_admin_t('shopify.movelist.print|Udskriv') . "' onclick='window.print()'></form>\n";

try {
	$list = shopify_load_move_list($day, null, $shipLager);
} catch (InvalidArgumentException $e) {
	print "<p style='margin:8px'>" . shopify_admin_t('shopify.movelist.bad_date|Ugyldig dato.') . "</p>\n";
	exit;
}
if (!$list) {
	print "<p style='margin:8px'>" . shopify_admin_t('shopify.movelist.none|Ingen linjer skal flyttes denne dag.') . "</p>\n";
	exit;
}
print "<table class='flytteliste'><tr>";
$heads = array('shopify.movelist.order|Ordre', 'shopify.movelist.set|Sæt', 'shopify.movelist.item|Varenr.', 'shopify.movelist.description|Beskrivelse',
	'shopify.movelist.quantity|Antal', 'shopify.movelist.from|Fra lager', 'shopify.movelist.to|Til lager');
foreach ($heads as $head) {
	print '<th>' . shopify_admin_t($head) . '</th>';
}
print "</tr>\n";
foreach ($list as $row) {
	$antal = $row['antal'] == (int)$row['antal'] ? (string)(int)$row['antal'] : (string)$row['antal'];
	print '<tr><td>' . shopify_admin_h($row['ordrenr'] . ' (' . $row['order_name'] . ')') . '</td><td>' . shopify_admin_h((string)$row['saet']) . '</td>'
		. '<td>' . shopify_admin_h($row['varenr']) . '</td><td>' . shopify_admin_h($row['beskrivelse']) . "</td><td class='antal'>" . shopify_admin_h($antal) . '</td>'
		. '<td>' . shopify_admin_h($row['fra_lager'] . ' ' . $row['fra_navn']) . '</td><td>' . shopify_admin_h($row['til_lager'] . ' ' . $row['til_navn']) . "</td></tr>\n";
}
print "</table>\n";
