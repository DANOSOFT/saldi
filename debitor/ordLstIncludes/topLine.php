<?php
// ---- debitor/ordLstIncludes/topLine.php --- lap 5.0.0 --- 2026.10.08 ---
// 20261008 Sawaneh The customer order list on the new page head (topbar addendum §5, prototype_dashboard_tema v5): Tilbud /
//                  Ordrer / Fakturaer / BS as a tab row, Import PBS and the UBL import as page actions, Ny ordre as the primary
//                  button, Hjælp kept as the hidden tour trigger, Tilbage to the debtor card only outside the shell.

include("../includes/oldDesign/header.php");
include("../includes/topline_settings.php");
include_once("../includes/stdFunc/pageBar.php");

$border = 'border:1px';
$TableBG = "bgcolor=$bgcolor";
$backUrl = nav_back_url(isset($_GET['returside']) ? $_GET['returside'] : null);
if ($konto_id) {
	$backUrl = "debitorkort.php?id=" . (int) $konto_id;
}
// Tab links pass valg and the explicit account context when opened from debitorkort.
$konto_param = $konto_id ? "&konto_id=" . (int) $konto_id . "&account_context=1" : "";
$ny_konto_param = $konto_id ? "&konto_id=" . (int) $konto_id : "";

$tabs = array();
if (!$hurtigfakt) {
	$tabs[] = array(findtekst('2770|Tilbud', $sprog_id), "ordreliste.php?valg=tilbud$konto_param", $valg == 'tilbud');
}
$tabs[] = array(findtekst('107|Ordrer', $sprog_id), "ordreliste.php?valg=ordrer$konto_param", $valg == 'ordrer');
$tabs[] = array(findtekst('1777|Fakturaer', $sprog_id), "ordreliste.php?valg=faktura$konto_param", $valg == 'faktura');
if ($valg == 'pbs' || !empty($pbs)) {
	$tabs[] = array(findtekst('385|BS', $sprog_id), "ordreliste.php?valg=pbs", $valg == 'pbs');
}

$actions = array();
$primary = null;
if ($valg == 'pbs') {
	$pbsAction = array('Import PBS', 'pbs_import.php?returside=ordreliste.php', 'bx-import');
	if ($popup) {
		$pbsAction['onclick'] = "ordre=window.open('pbs_import.php?returside=ordreliste.php','ordre','scrollbars=1,resizable=1');ordre.focus();";
	}
	$actions[] = $pbsAction;
} else {
	if ($valg == "$ordrer1") { #20121017
		$dir = '../ublfiler/ind/';
		if (file_exists("$dir")) {
			foreach (scandir($dir) as $fil) {
				if (substr($fil, -3) == 'xml') {
					$actions[] = array(findtekst('876|Importer UBL til ordrer', $sprog_id), 'ubl2ordre.php', 'bx-import', '_blank');
					break;
				}
			}
		}
	}
	$primary = array(findtekst('7004|Ny ordre', $sprog_id), "ordre.php?returside=ordreliste.php$ny_konto_param");
	if ($popup) {
		$primary['onclick'] = "ordre=window.open('ordre.php?returside=ordreliste.php$ny_konto_param','ordre','scrollbars=1,resizable=1');ordre.focus();";
	}
}

page_bar(array(
	'title' => findtekst('107|Ordrer', $sprog_id),
	'tabs' => $tabs,
	'actions' => $actions,
	'primary' => $primary,
	'back' => $backUrl,
	'help' => ($valg != 'pbs'),
));

if ($valg == 'pbs') {
	include("pbsliste.php");
	exit;
}
?>
