<?php
// ---- debitor/debLstIncludes/topLineJobkort.php --- lap 5.0.0 --- 2026.10.08 ---
// 20261008 Sawaneh The job card on the new page head (topbar addendum §5, prototype_dashboard_tema v5): Jobkort as the
//                  title, Hjælp kept as the hidden tour trigger for the Assist menu, Tilbage (jobkort.php?luk=luk, which
//                  releases the card) only outside the shell.

include("../includes/oldDesign/header.php");
include("../includes/topline_settings.php");
include_once("../includes/stdFunc/pageBar.php");

$border = 'border:1px';
$TableBG = "bgcolor=$bgcolor";
$backUrl = isset($_GET['returside']) ? $_GET['returside'] : 'jobliste.php';

page_bar(array(
	'title' => findtekst('29|Jobkort', $sprog_id),
	'back' => 'jobkort.php?luk=luk',
	'help' => true,
));
?>
