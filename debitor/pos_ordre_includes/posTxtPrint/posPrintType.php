<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/posPrintType.php ---------- lap 3.7.7----2019.06.11-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
//
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190611 Decide the arguments to the pos_txt_print func here


	$xReport = (isset($_POST['xRapport']) && $_POST['xRapport'] == "X-Rapport") ? True : False;
	$zReport = (isset($_POST['zRapport']) && $_POST['zRapport'] == "Z-Rapport") ? True : False;
	$copy = (isset($_POST['kopi']) && $_POST['kopi'] == "Kopi") ? True : False;
	$print = (isset($_POST['udskriv']) && ($_POST['udskriv'] == "Udskriv" || $_POST['udskriv'] == "Print")) ? True : False;
	$proforma = (isset($_POST['proforma']) && $_POST['proforma'] == 'Proforma') ? True : False;

	if (!$id && !$varenr_ny && $kundedisplay) kundedisplay('**** Velkommen ****','','1');
	if($copy || $proforma || $print || $xReport || $zReport) {
		$momssats=$momssats*1;
		if ($id && (!$xReport && !$zReport)) {
			$delayLoad = pos_txt_print($id,$betaling,$modtaget,$indbetaling);
		} elseif (isset($_POST['kopi']) && $_POST['kopi'] == "Kopier" && $linjeantal > 0) {
			$tmp=$kasse;
			if (!$tmp) $tmp=1;
			$r=db_fetch_array(db_select("select max(id) as id from ordrer where art = 'PO' and status >= '3' and felt_5 = '$tmp'",__FILE__ . " linje " . __LINE__));
			$delayLoad = pos_txt_print($r['id'],$betaling,$modtaget,$indbetaling);
		} elseif ($xReport || $zReport) {
			$reportVar = setReportType($xReport, $zReport);
			pos_txt_print($id, $betaling, $modtaget, $indbetaling, $modtaget2, $indbetaling, $reportVar);
		} else {
			printWarningMessage("proforma");
		}
	} elseif(isset($_POST['udskriv_sidste']) && $_POST['udskriv_sidste']) {
		$momssats=$momssats*1;
		$tmp=$kasse;
		if (!$tmp) $tmp=1;
		$r=db_fetch_array($q=db_select("select max(id) as id from ordrer where art = 'PO' and status >= '3' and felt_5 = '$tmp'",__FILE__ . " linje " . __LINE__));
		$delayLoad = pos_txt_print($r['id'],$betaling,$modtaget,$indbetaling);
	}



?>
