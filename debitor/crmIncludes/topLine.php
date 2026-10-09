<?php
// ---- debitor/crmIncludes/topLine.php --- lap 5.0.0 --- 2026.10.08 ---
// 20261008 Sawaneh The CRM pages (crmkalender, crmopret, crmvisning) on the new page head (topbar addendum §5,
//                  prototype_dashboard_tema v5): CRM as the title, Kalender as the tab, Visning as a page action (Alt+V),
//                  Tilbage only outside the shell.

include("../includes/oldDesign/header.php");
include("../includes/topline_settings.php");
include_once("../includes/stdFunc/pageBar.php");

$border = 'border:1px';
$TableBG = "bgcolor=$bgcolor";
$backUrl = isset($_GET['returside']) ? $_GET['returside'] : '../index/menu.php';
$vismenu = isset($_GET['vismenu']) ? (string) $_GET['vismenu'] : '';

page_bar(array(
	'title' => 'CRM',
	'tabs' => array(array(findtekst('2710|Kalender', $sprog_id), 'crmkalender.php?returside=' . urlencode((string) $returside) . '&vismenu=' . urlencode($vismenu), $valg == 'kalender')),
	'actions' => array(array(findtekst('813|Visning', $sprog_id), 'crmvisning.php?returside=' . urlencode($_SERVER['REQUEST_URI']), 'bx-show', 'accesskey' => 'V')), // WP-2.14: Tilbage from Visning returns here
	'back' => $backUrl,
));
?>
