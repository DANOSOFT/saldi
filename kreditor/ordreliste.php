<?php
// --- kreditor/ordreliste.php -----patch 4.1.0 ----2025-04-15----------
//                           LICENSE
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. 
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// 2014.03.19 addslashes erstattet med db_escape_string
// 2104.09.16	Tilføjet oioublimport i bunden
// 20211125 PHR Added 'Skan Bilag'
// 20220728 MSC - Implementing new design
// 20221106 PHR - Various changes to fit php8 / MySQLi
// 20230317 LOE - Applied some translated texts, and Also fixed some undefined variable errors and some more.
// 20230525 PHR - php8
// 20231017 PHR Fixed an error in account selection ($firma);
// 20240510 LOE fixed Undefined array key 1...file. Added a condition to list call on $firma
// 20250415 LOE Updated some variables using if_isset and some clean up

ob_start();
@session_start();
$s_id = session_id();

$css = "../css/std.css";
$modulnr = 5;
$title = "Leverandører • Ordreliste";
$dk_dg = $firmanavn = $hrefslut = $hurtigfakt = $konto_id = $linjebg = NULL;
$checked = $returside = $totalkost = $understreg = $vis_projekt = $nbsp = NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/udvaelg.php");
include("../includes/topline_settings.php");
include("../includes/row-hover-style.js.php");
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/jquery-3.6.4.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/moment.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/daterangepicker.min.js\" defer></script>";
print '<link rel="stylesheet" type="text/css" href="../css/daterangepicker.css" />';
include("../includes/datepkr.php");
$show_date_pkr = get_settings_value("datepicker", "personlige", "on");
$script = "";

global $menu;

$ordrenumre = if_isset($_GET, NULL, 'ordrenumre');
$kontonumre = if_isset($_GET, NULL, 'kontonumre');
$modtagelsesnumre = if_isset($_GET, NULL, 'modtagelsesnumre');
$fakturanumre = if_isset($_GET, NULL, 'fakturanumre');
$ordredatoer = if_isset($_GET, NULL, 'ordredatoer');
$lev_datoer = if_isset($_GET, NULL, 'lev_datoer');
$lev_navne = if_isset($_GET, NULL, 'lev_navne');
$fakturadatoer = if_isset($_GET, NULL, 'fakturadatoer');
$genfaktdatoer = if_isset($_GET, NULL, 'genfaktdatoer');
$summer = if_isset($_GET, NULL, 'summer');
$firma = if_isset($_GET, NULL, 'firma');
$ref[0] = if_isset($_GET, NULL, 'ref');
$projekt[0] = if_isset($_GET, NULL, 'projekt');
$valg = if_isset($_GET, NULL, 'valg');
$sort = if_isset($_GET, NULL, 'sort');
$nysort = if_isset($_GET, NULL, 'nysort');
$kontoid = if_isset($_GET, NULL, 'kontoid');

$tidspkt = date("U");

$r2 = db_fetch_array(db_select("select max(id) as id from grupper", __FILE__ . " linje " . __LINE__));

if (isset($_GET['returside'])) {
	$returside = $_GET['returside'];
	if ($r = db_fetch_array(db_select("select id from grupper where art = 'OLV' and kodenr = '$bruger_id'", __FILE__ . " linje " . __LINE__))) {
		db_modify("update grupper set box2='$returside' where id='$r[id]'", __FILE__ . " linje " . __LINE__);
	} else db_modify("insert into grupper (beskrivelse,kode,kodenr,art,box2) values ('Ordrelistevisning','$brugernavn','$bruger_id','OLV','$returside')", __FILE__ . " linje " . __LINE__);
} else {
	$r2 = db_fetch_array(db_select("select max(id) as id from grupper", __FILE__ . " linje " . __LINE__));
	$r = db_fetch_array(db_select("select box2 from grupper where art = 'OLV' and kodenr = '$bruger_id'", __FILE__ . " linje " . __LINE__));
	$returside = $r['box2'];
}
$backUrl = isset($_GET['returside'])
	? $_GET['returside']
	: '../index/menu.php';

if ($returside == 'ordreliste.php') $returside = NULL;
if (!$returside) {
	if ($popup) $returside = "../includes/luk.php";
	else $returside = $backUrl;
}

#if (isset($_POST)) {
if ($submit = if_isset($_POST, NULL, 'submit')) {
	$ordrenumre = if_isset($_POST, NULL, 'ordrenumre');
	$modtagelsesnumre = if_isset($_POST, NULL, 'modtagelsesnumre');
	$kontonumre = if_isset($_POST, NULL, 'kontonumre');
	$fakturanumre = if_isset($_POST, NULL, 'fakturanumre');
	$ordredatoer = if_isset($_POST, NULL, 'ordredatoer');
	$lev_datoer = if_isset($_POST, NULL, 'lev_datoer');
	$lev_navne = if_isset($_POST, NULL, 'lev_navne');
	$fakturadatoer = if_isset($_POST, NULL, 'fakturadatoer');
	$genfaktdatoer = if_isset($_POST, NULL, 'genfaktdatoer');
	$summer = if_isset($_POST, NULL, 'summer');
	$firma = if_isset($_POST, NULL, 'firma');
	$ref[0] = if_isset($_POST, NULL, 'ref');
	$projekt[0] = if_isset($_POST, NULL, 'projekt');
	$valg = if_isset($_POST, NULL, 'valg');
	$sort = if_isset($_POST, NULL, 'sort');
	$nysort = if_isset($_POST, NULL, 'nysort');
	$kontoid = if_isset($_POST, NULL, 'kontoid');
}


#list($kontoid,$firma) = explode('|',$firma,2);
// Check if $firma is empty
if (empty($firma)) { #20240510 
	$kontoid = NULL; // Assign NULL as default
	$firma = NULL;
} else {
	// Split with limit of 2
	list($kontoid, $firma) = explode('|', $firma, 2);
}

if ($valg) {
	$cookievalue = "$ordrenumre;$kontonumre;$fakturanumre;$ordredatoer;$lev_datoer;$fakturadatoer;$genfaktdatoer;$summer;$firma;$kontoid;$ref[0];$sort;$valg;$nysort;$modtagelsesnumre";
	setcookie("kred_ord_lst", $cookievalue);
} elseif (isset($_COOKIE['kred_ord_lst']) && $_COOKIE['kred_ord_lst']) {
	list($ordrenumre, $kontonumre, $fakturanumre, $ordredatoer, $lev_datoer, $fakturadatoer, $genfaktdatoer, $summer, $firma, $kontoid, $ref[0], $sort, $valg, $nysort, $modtagelsesnumre) = explode(";", $_COOKIE['kred_ord_lst']); #
}
ob_end_flush();	//Sender det "bufferede" output afsted...

if (!$valg) $valg = "ordrer";
if (!$sort) $sort = "firmanavn";
elseif ($nysort == $sort) $sort = $sort . " desc";
elseif ($nysort) $sort = $nysort;
if ($valg != 'faktura') {
	$fakturanumre = '';
	$fakturadatoer = '';
	$genfaktdatoer = '';
}
if ($valg == "forslag") $status = "status = 0";
elseif ($valg == "faktura") $status = "status >= 3";
else $status = "status = 1 or status = 2";

$paperflow = NULL;
$qtxt = "select var_value from settings where var_grp='creditor' and var_name='paperflow'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $paperflow = $r['var_value'];
if ($paperflow) $paperflow = "checked='checked'";

$qtxt = "select distinct id from ordrer where (art='DK' or art='KK') and projekt > '0' and $status";
if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $vis_projekt = 'on';
$qtxt = "select id from grupper where art = 'DIV' and kodenr = '3' and box4='on'";
if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $hurtigfakt = 'on';

$hreftext = "&ordrenumre=$ordrenumre&kontonumre=$kontonumre&fakturanumre=$fakturanumre&ordredatoer=$ordredatoer&lev_datoer=$lev_datoer&fakturadatoer=$fakturadatoer&genfaktdatoer=$genfaktdatoer&summer=$summer&ref=$ref[0]&kontoid=$kontoid&modtagelsesnumre=$modtagelsesnumre";
#if ($valg!="faktura") print "<meta http-equiv=\"refresh\" content=\"60;URL='ordreliste.php?sort=$sort&valg=$valg$hreftext'\">";


if ($submit == "Udskriv") {
	$ordre_antal = if_isset($_POST['ordre_antal']);
	$ordre_id = if_isset($_POST['ordre_id']);
	$checked = if_isset($_POST['checked']);

	for ($x = 1; $x <= $ordre_antal; $x++) {
		if ($checked[$x] == "on") {
			$y++;
			if (!$udskriv) $udskriv = $ordre_id[$x];
			else $udskriv = $udskriv . "," . $ordre_id[$x];
		}
	}
	if ($y > 0) {
		print "<BODY onLoad=\"JavaScript:window.open('formularprint.php?id=-1&ordre_antal=$y&skriv=$udskriv&formular=4' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
	} else print "<BODY onLoad=\"javascript:alert('Ingen fakturaer er markeret til udskrivning!')\">";
}
if (isset($_POST['check']) || isset($_POST['uncheck'])) {
	$ordre_antal = if_isset($_POST['ordre_antal']);
	$ordre_id = if_isset($_POST, NULL, 'ordre_id');
	if (isset($_POST['check'])) $check_all = 'on';
}

if ($menu == 'T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">";
	print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";
	print "<div class=\"headerTxt\">$title</div>";
	print "<div class=\"headerbtnRght headLink\">";
	print "<a id='visning' href='ordrevisning.php?valg=$valg&sort=$sort' title='Visningsindstillinger'><i class='fa fa-sliders-h fa-lg'></i></a>&nbsp;&nbsp;";
	print "<a accesskey=N href='ordre.php?returside=ordreliste.php' title='Opret ny ordre'><i class='fa fa-plus-square fa-lg'></i></a>";
	print "</div>";
	print "</div>";
	print "<div class='content-noside'>";
} elseif ($menu == 'S') {
	print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>";
	print "<tr><td height = 25 align=center valign=top>";
	print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";
	print "<td width=10% style='$buttonStyle'><a href=$backUrl>
		   <button style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">" . findtekst('2172|Luk', $sprog_id) . "</button></a></td>";

	print "<td width=75% style='$topStyle' align=center><table border=0 cellspacing=2 cellpadding=0><tbody>";

	if (!$hurtigfakt) {
		print "<td width = 20% align=center ";
		if ($valg == 'forslag') {
			print "<td width = '100px' align=center>
				   <button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">" . findtekst('827|Forslag', $sprog_id) . "</button></td>";
		} else {
			print "<td width = 20% align=center><a href='ordreliste.php?sort=$sort&valg=forslag$hreftext'>
				   <button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
				. findtekst('827|Forslag', $sprog_id) . "</button></a></td>";
		}
	}
	print "<td width = 20% align=center ";
	if ($valg == 'ordrer') {
		print "<td width = '100px' align=center>
			   <button style='$butDownStyle; width: 100%' id='ordrer' onMouseOver=\"this.style.cursor = 'pointer'\">" . findtekst('107|Ordrer', $sprog_id) . "</button></td>";
	} else {
		print "<td width = 20% align=center><a href='ordreliste.php?sort=$sort&valg=ordrer$hreftext'>
			   <button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
			. findtekst('107|Ordrer', $sprog_id) . "</button></a></td>";
	}
	print "</td><td width = 20% align=center ";

	if ($valg == 'faktura') {
		print "<td width = '100px' align=center>
			   <button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">" . findtekst('643|Faktura', $sprog_id) . "</button></td>";
	} else {
		print "<td width = 20% align=center><a href='ordreliste.php?sort=$sort&valg=faktura$hreftext'>
			   <button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
			. findtekst('643|Faktura', $sprog_id) . "</button></a></td>";
	}

	if ($paperflow) {
		print "</td><td width = 20% align=center ";
		if ($valg == 'skanBilag') {
			print "<td width = '100px' align=center>
				   <button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">" . findtekst('2182|Skan bilag', $sprog_id) . "</button></td>";
		} else {
			print "<td width = 20% align=center><a href='ordreliste.php?sort=$sort&valg=faktura$hreftext'>
				   <button style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
				. findtekst('2182|Skan bilag', $sprog_id) . "</button></a></td>";
		}
		print "</td>";
	}
	print "</tbody></table></td>";
	print "<td id='tutorial-help' width=5% style='$buttonStyle'>";
	print "<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
	print findtekst('2564|Hjælp', $sprog_id)."</button></td>";
	print "<td width=5% style='$buttonStyle'><a href=ordrevisning.php?valg=$valg&sort=$sort>
	       <button id='visning' style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">".findtekst('813|Visning', $sprog_id)."</button></a></td>";
	print "<td width=10% style='$buttonStyle'><a href=ordre.php?returside=ordreliste.php>
		   <button style='$buttonStyle; width: 100%' id='ny' onMouseOver=\"this.style.cursor = 'pointer'\">" . findtekst('39|Ny', $sprog_id) . "</button></a></td>";
	print "</tbody></table>";
} else {
	print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>";
	print "<tr><td height = 25 align=center valign=top>";
	print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";
	print "<td width=10% $top_bund><a href=$returside accesskey=L>" . findtekst('2172|Luk', $sprog_id) . "</a></td>";
	# print "<td width=50%$top_bund align=center>Kundeordrer</td>";

	print "<td width=80% $top_bund align=center><table border=0 cellspacing=2 cellpadding=0><tbody>";
	if (!$hurtigfakt) {
		print "<td width = 20% align=center ";
		if ($valg == 'forslag') print $knap_ind . ">&nbsp;Forslag&nbsp;";
		else print "<td width=20% align=center><a href='ordreliste.php?sort=$sort&valg=forslag$hreftext'>&nbsp;Forslag&nbsp;</a>";
		print "</td>";
	}
	print "<td width = 20% align=center ";
	if ($valg == 'ordrer') print $knap_ind . ">&nbsp;Ordrer&nbsp";
	else print "><a href='ordreliste.php?sort=$sort&valg=ordrer$hreftext'>&nbsp;Ordrer&nbsp;</a>";
	print "</td><td width = 20% align=center ";
	if ($valg == 'faktura') print $knap_ind . ">&nbsp;Faktura&nbsp;";
	else print "><a href='ordreliste.php?sort=$sort&valg=faktura$hreftext'>&nbsp;Faktura&nbsp;</a>";
	if ($paperflow) {
		print "</td><td width = 20% align=center ";
		if ($valg == 'skanBilag') print  $knap_ind . ">&nbsp;Skan bilag&nbsp;";
		else print "><a href='ordreliste.php?sort=$sort&valg=skanBilag$hreftext'>&nbsp;Skan bilag&nbsp;</a>";
		print "</td>";
	}
	print "</tbody></table></td>";
	print "<td width=10% $top_bund><a id='visning' href=ordrevisning.php?valg=$valg&sort=$sort>".findtekst('813|Visning', $sprog_id)."</a></td>";
	if ($popup) print "<td width=10% $top_bund onClick=\"javascript:ordre=window.open('ordre.php?returside=ordreliste.php','ordre','scrollbars=1,resizable=1');ordre.focus();\"><a accesskey=N href=ordreliste.php?sort=$sort>Ny</a></td>";
	else print "<td width=10% $top_bund><a href=ordre.php?returside=ordreliste.php>Ny</a></td>";
	print "</td></tr>\n";
	#print "<tr><td></td><td align=center><table border=1	cellspacing=0 cellpadding=0><tbody>";
	#print "<td width = 20%$top_bund align=center><a href=ordreliste.php?valg=forslag accesskey=T>Forslag</a></td>";
	#print "<td width = 20% bgcolor=$bgcolor5 align=center> Ordrer</td>";
	#print "<td width = 20% bgcolor=$bgcolor5 align=center> Faktura</td>";
	#print "</tbody></table></td><td></td</tr>\n";

	print "</tbody></table>";
}

////// Tutorial //////

$steps = array();
$steps[] = array(
	"selector" => "#ordrer",
	"content" => findtekst('2610|Her ser du en liste af alle dine ordrer', $sprog_id)."."
);
$steps[] = array(
	"selector" => "#ny",
	"content" => findtekst('2611|For at oprette en ny ordre, klik her', $sprog_id)."."
);

include(__DIR__ . "/../includes/tutorial.php");
create_tutorial("kreOrdList", $steps);

////// Tutorial end //////

print " </td></tr>\n<tr><td align=center valign=top>";
if ($valg != 'skanBilag') {
	print "<table cellpadding=1 cellspacing=1 border=0 width=100% valign = top class='dataTable'>";
	print "<tbody>";
	// Build headers from Visning config (OLV)
	$cfg = db_fetch_array(db_select("select box3,box4,box5,box6,box10 from grupper where art='KOLV' and kode='$valg' and kodenr='$bruger_id'", __FILE__ . " linje " . __LINE__));
	$vis_felt = $cfg && $cfg['box3'] ? explode(',', $cfg['box3']) : array();
	$feltbredde = $cfg && $cfg['box4'] ? explode(',', $cfg['box4']) : array();
	$justering = $cfg && $cfg['box5'] ? explode(',', $cfg['box5']) : array();
	$feltnavn = $cfg && $cfg['box6'] ? explode(',', $cfg['box6']) : array();
	if (count($vis_felt) < 1) {
		if ($valg == 'forslag') {
			$vis_felt = array('ordrenr','ordredate','kontonr','firmanavn','lev_navn','ref','sum');
			$justering = array('right','left','left','left','left','left','right');
			$feltnavn = array(findtekst('500|Ordrenr.', $sprog_id),findtekst('889|Tilbudsdato', $sprog_id),findtekst('804|Kontonr.', $sprog_id),findtekst('360|Firmanavn', $sprog_id),findtekst('814|Leveres til', $sprog_id),findtekst('884|Sælger', $sprog_id),findtekst('826|Forslagssum', $sprog_id));
		} elseif ($valg == 'ordrer') {
			$vis_felt = array('ordrenr','ordredate','levdate','kontonr','firmanavn','lev_navn','ref','sum');
			$justering = array('right','left','left','left','left','left','left','right');
			$feltnavn = array(findtekst('500|Ordrenr.', $sprog_id),findtekst('881|Ordredato', $sprog_id),findtekst('941|Modt.dato', $sprog_id),findtekst('804|Kontonr.', $sprog_id),findtekst('360|Firmanavn', $sprog_id),findtekst('814|Leveres til', $sprog_id),findtekst('884|Sælger', $sprog_id),findtekst('887|Ordresum', $sprog_id));
		} else {
			$vis_felt = array('ordrenr','ordredate','modtagelse','fakturanr','fakturadate','kontonr','firmanavn','lev_navn','ref','sum');
			$justering = array('right','left','right','right','left','left','left','left','left','right');
			$feltnavn = array(findtekst('500|Ordrenr.', $sprog_id),findtekst('881|Ordredato', $sprog_id),findtekst('940|Modt.nr.', $sprog_id),findtekst('882|Fakt.nr.', $sprog_id),findtekst('883|Fakt.dato', $sprog_id),findtekst('804|Kontonr.', $sprog_id),findtekst('360|Firmanavn', $sprog_id),findtekst('814|Leveres til', $sprog_id),findtekst('884|Sælger', $sprog_id),findtekst('885|Fakturasum', $sprog_id));
		}
	}
	print "<tr>";
	for ($i=0; $i<count($vis_felt); $i++) {
		$label = isset($feltnavn[$i]) && $feltnavn[$i] !== '' ? $feltnavn[$i] : $vis_felt[$i];
		$align = isset($justering[$i]) && $justering[$i] !== '' ? $justering[$i] : 'left';
		
		// Add width styling here - this was missing in kreditor version
		$width_style = '';
		if (isset($feltbredde[$i]) && $feltbredde[$i]) {
			$width_px = $feltbredde[$i];
			if ($width_px <= 10) $width_px *= 10; // Convert if needed
			$width_style = " style=\"width:{$width_px}px;\"";
		}
		
		print "<td align=$align$width_style><b><a href='ordreliste.php?nysort={$vis_felt[$i]}&sort=$sort&valg=$valg$hreftext'>$label</a></b></td>";
	}
	print "<td></td>";
	print "</tr>\n";
	#################################### Sogefelter ##########################################

	// Load dropDown flags
	$cfgDD = db_fetch_array(db_select("select box10 from grupper where art='KOLV' and kode='$valg' and kodenr='$bruger_id'", __FILE__ . " linje " . __LINE__));
	$dropDown = $cfgDD && $cfgDD['box10'] ? explode(',', $cfgDD['box10']) : array();

	// Load existing search values from grupper.box9
	$rFind = db_fetch_array(db_select("select box9 from grupper where art='KOLV' and kode='$valg' and kodenr='$bruger_id'", __FILE__ . " linje " . __LINE__));
	$find = $rFind && $rFind['box9'] ? explode("\n", $rFind['box9']) : array();
	if ($submit) { $find = if_isset($_POST['find']); }

	// Persist current search values
	$tmp = '';
	for ($x=0; $x<count($vis_felt); $x++) {
		$val = isset($find[$x]) ? trim($find[$x]) : '';
		$tmp .= ($x?"\n":"") . db_escape_string($val);
	}
	db_modify("update grupper set box9='$tmp' where art='KOLV' and kode='$valg' and kodenr='$bruger_id'", __FILE__ . " linje " . __LINE__);

	print "<form name=ordreliste action=ordreliste.php method=post>";
	print "<input type=hidden name=valg value=$valg>";
	print "<input type=hidden name=sort value=$sort>";
	print "<input type=hidden name=nysort value=$nysort>";
	print "<tr>";
	for ($x=0; $x<count($vis_felt); $x++) {
		$fname = trim($vis_felt[$x]);
		$align = isset($justering[$x]) && $justering[$x] ? $justering[$x] : 'left';
		
		// Apply width to search fields too
		$width = '';
		if (isset($feltbredde[$x]) && $feltbredde[$x]) {
			$width_px = $feltbredde[$x];
			if ($width_px <= 10) $width_px *= 10;
			$width = "width:{$width_px}px;";
		}
		
		print "<td align=$align>";
		if (strpos($fname,'date')!==false) {
			$val = isset($find[$x]) ? $find[$x] : '';
			print "<input type='text' name=find[$x] value='".htmlspecialchars($val,ENT_QUOTES)."' id='dateout$x' hidden>";
			date_picker($val, "find[$x]", "ordreliste", $align, $width);
		} elseif (isset($dropDown[$x]) && $dropDown[$x]) {
			print "<SELECT NAME=\"find[$x]\" class=\"inputbox\" style=\"$width\">";
			$qtxt = "select distinct($fname) as f from ordrer where (art='KO' or art='KK') and ($status) order by $fname";
			$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			$cur = isset($find[$x]) ? stripslashes($find[$x]) : '';
			print "<option>".htmlspecialchars($cur,ENT_QUOTES)."</option>";
			if ($cur) print "<option></option>";
			while ($r=db_fetch_array($q)) { print "<option>".$r['f']."</option>"; }
			print "</SELECT>";
		} else {
			$val = isset($find[$x]) ? $find[$x] : '';
			print "<input class='inputbox' type=text style=\"text-align:$align;$width\" name=find[$x] value=\"".htmlspecialchars($val,ENT_QUOTES)."\">";
		}
		print "</td>";
	}
	
	print "<td align=center><input class='inputbox' type=submit value=\"OK\" name=\"submit\"></td>";
	print "</form></tr>\n";
	print "\n<script>\n\twindow.onload = function() {\n\t\t$script\n\t};\n</script>\n";
	####################################################################################
$udvaelg = '';

// Process dynamic search fields FIRST
for ($x = 0; $x < count($vis_felt); $x++) {
    $val = isset($find[$x]) ? trim($find[$x]) : '';
    if ($val === '' || $val === '-') continue; // Skip empty fields
    
    $fname = trim($vis_felt[$x]);
    $val = db_escape_string($val); // Escape for security
    
    // Process different field types
    if ($fname == 'ordrenr') {
        if (strlen($val) >= 11) $val = substr($val, 0, 10);
        $val = (int)$val;
        if (strpos($val, ':')) {
            list($a, $b) = explode(':', $val);
            $udvaelg .= " and ordrer.ordrenr >= '$a' and ordrer.ordrenr <= '$b'";
        } else {
            $udvaelg .= " and ordrer.ordrenr='$val'";
        }
    } elseif ($fname == 'kontonr') {
        $val = (int)$val;
        $udvaelg .= " and ordrer.kontonr='$val'";
    } elseif ($fname == 'konto_id') {
        $val = (int)$val;
        $udvaelg .= " and ordrer.konto_id='$val'";
    } elseif ($fname == 'modtagelse') {
        $val = (int)$val;
        if (strpos($val, ':')) {
            list($a, $b) = explode(':', $val);
            $udvaelg .= " and ordrer.modtagelse >= '$a' and ordrer.modtagelse <= '$b'";
        } else {
            $udvaelg .= " and ordrer.modtagelse='$val'";
        }
    } elseif ($fname == 'fakturanr') {
        if (is_numeric(str_replace(":", "", $val))) {
            // Handle numeric ranges
            if (strpos($val, ':')) {
                list($a, $b) = explode(':', $val);
                $udvaelg .= " and (ordrer.fakturanr ~ '^[0-9]+\$' and cast(ordrer.fakturanr as numeric) >= '" . usdecimal($a) . "' and cast(ordrer.fakturanr as numeric) <= '" . usdecimal($b) . "')";
            } else {
                $udvaelg .= " and ordrer.fakturanr='" . $val . "'";
            }
        } else {
            // Handle text search with wildcards
            $udvaelg .= udvaelg($val, 'ordrer.fakturanr', 'TEXT');
        }
    } elseif ($fname == 'sum') {
        $udvaelg .= udvaelg($val, 'ordrer.sum', 'BELOB');
    } elseif (strpos($fname, 'date') !== false || $fname == 'nextfakt') {
        $udvaelg .= udvaelg($val, 'ordrer.'.$fname, 'DATO');
    } elseif ($fname == 'ref') {
        $udvaelg .= udvaelg($val, 'ordrer.ref', 'TEXT');
    } elseif ($fname == 'firmanavn' || $fname == 'lev_navn' || $fname == 'email' || $fname == 'addr1' || $fname == 'addr2' || $fname == 'kontakt') {
        $udvaelg .= udvaelg($val, 'ordrer.'.$fname, 'TEXT');
    } elseif (isset($dropDown[$x]) && $dropDown[$x] && $val) {
        $udvaelg .= " and ordrer.$fname='$val'";
    } else {
        // Default handling for other fields
        if (is_numeric($val)) {
            $udvaelg .= udvaelg($val, 'ordrer.'.$fname, 'NR');
        } else {
            $udvaelg .= udvaelg($val, 'ordrer.'.$fname, 'TEXT');
        }
    }
}

// Then process the old search parameters (keep existing code)
if ($ordrenumre) {
    $udvaelg = $udvaelg . udvaelg($ordrenumre, 'ordrenr', 'NR');
}
if ($modtagelsesnumre) {
    $udvaelg = $udvaelg .= udvaelg($modtagelsesnumre, 'modtagelse', 'NR');
}

if ($fakturanumre) {
    # If it is a number range, prefer number search
    if (is_numeric(str_replace(":", "", $fakturanumre))) {
        # Split the lower and upper number
        list($a, $b) = explode(':', $fakturanumre);
        # Perform a search that excludes any non numeric invoice numbers from counting
        $udvaelg = $udvaelg . " AND (ORDRER.FAKTURANR ~ '^[0-9]+$' AND CAST(ORDRER.FAKTURANR AS NUMERIC) >= '" . usdecimal($a) . "' AND CAST(ORDRER.FAKTURANR AS NUMERIC) <= '" . usdecimal($b) . "')";
    } else {
        $searchTerm = "*" . str_replace(" ", "*", $fakturanumre) . "*";
        $udvaelg .= udvaelg($searchTerm, 'fakturanr', 'TEXT');
    }
}
if ($kontonumre) {
    $udvaelg = $udvaelg .= udvaelg($kontonumre, 'kontonr', 'NR');
}
if ($ordredatoer) {
    $udvaelg = $udvaelg . udvaelg($ordredatoer, 'ordredate', 'DATO');
}
if ($lev_datoer) {
    $udvaelg = $udvaelg . udvaelg($lev_datoer, 'levdate', 'DATO');
}
if ($fakturadatoer) {
    $udvaelg = $udvaelg . udvaelg($fakturadatoer, 'fakturadate', 'DATO');
}
if ($genfaktdatoer) {
    $udvaelg = $udvaelg . udvaelg($genfaktdatoer, 'nextfakt', 'DATO');
}
if ($ref[0]) {
    $searchTerm = "*" . str_replace(" ", "*", $ref[0]) . "*";
    $udvaelg = $udvaelg . udvaelg($searchTerm, 'ref', 'TEXT');
}
if ($projekt[0]) {
    $udvaelg = $udvaelg . " and projekt='$projekt[0]'";
}
if ($summer) {
    $udvaelg = $udvaelg . udvaelg($summer, 'sum', 'BELOB');
}

if ($kontoid) {
    $udvaelg = $udvaelg . udvaelg($kontoid, 'konto_id', 'NR');
}

if ($lev_navne) {
    $lev_navne = trim($lev_navne);
    $searchTerm = "*" . str_replace(" ", "*", $lev_navne) . "*";
    $udvaelg = $udvaelg . udvaelg($searchTerm, 'lev_navn', 'TEXT');
}

	if ($valg == "forslag") {
		$ialt = 0;
		$query = db_select("select * from ordrer where (art = 'KO' or art = 'KK') and status < 1 $udvaelg order by $sort", __FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			$ordre = "ordre" . $row['id'];
			$sum = $row['sum'];
			$kostpris = $row['kostpris'];
			$valutakurs = $row['valutakurs'];
			$orderTime  = $row['tidspkt'];
			if (!$orderTime) $orderTime = 0;
			if (($tidspkt - ($orderTime) > 3600) || ($row['hvem'] == $brugernavn)) {
				if ($popup) {
					$javascript = "onClick=\"javascript:$ordre=window.open('ordre.php?tjek=$row[id]&id=$row[id]&returside=ordreliste.php','$ordre','scrollbars=1,resizable=1');$ordre.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" ";
					$understreg = '<span style="text-decoration: underline;">';
					$hrefslut = "";
				} else {
					$javascript = "";
					$understreg = "<a href=ordre.php?tjek=$row[id]&id=$row[id]&returside=ordreliste.php>";
					$hrefslut = "</a>";
				}
				$linjetext = "";
			} else {
				$javascript = "onClick=\"javascript:$ordre.focus();\"";
				$understreg = '';
				$linjetext = "<span title= 'Ordre er l&aring;st af $row[hvem]'>";
			}
			if ($linjebg != $bgcolor) {
				$linjebg = $bgcolor;
				$color = '#000000';
			} else {
				$linjebg = $bgcolor5;
				$color = '#000000';
			}

			print "<tr bgcolor=\"$linjebg\" style='color:$color'>";
			for ($i=0; $i<count($vis_felt); $i++) {
				$fname = trim($vis_felt[$i]);
				$align = isset($justering[$i]) && $justering[$i] ? $justering[$i] : 'left';
				print "<td align=$align style='color:$color'";
				if ($i==0) print " $javascript";
				print ">";
				if ($fname=='ordrenr') {
					$labelNr = ($row['art']=='KK')?"(KN)&nbsp;".$row['ordrenr']:$row['ordrenr'];
					print "$linjetext $understreg $labelNr$hrefslut";
				} elseif (strpos($fname,'date')!==false) {
					print dkdato($row[$fname]);
				} elseif ($fname=='sum') {
					$sumCell = $sum;
					if ($valutakurs && $valutakurs != 100) $sumCell = $sumCell * $valutakurs / 100;
					$ialt += $sumCell;
					print dkdecimal($sumCell);
				} else {
					if (isset($row[$fname])) print $row[$fname];
				}
				print "<br></td>";
			}
?>
			<td>
				<div style="display:flex;gap:5px;">
					<a href="formularprint.php?id=<?php print $row["id"]; ?>&formular=12&udskriv_til=PDF" target="_blank" title="Klik for at printe tilbud"><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000">
							<path d="M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-624v-168H264v624h432v-456H528ZM264-792v189-189 624-624Z" />
						</svg></a>
					<?php
					if ($row['email']) {
					?> <a href="formularprint.php?id=<?php print $row["id"]; ?>&formular=12&udskriv_til=email" target="_blank" title="Klik for at sende tilbud via email" onclick="return confirm('Er du sikker på, at du vil sende fakturaen?\nKundens mail: <?php print $row['email']; ?>')"><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000">
								<path d="M168-192q-29.7 0-50.85-21.16Q96-234.32 96-264.04v-432.24Q96-726 117.15-747T168-768h624q29.7 0 50.85 21.16Q864-725.68 864-695.96v432.24Q864-234 842.85-213T792-192H168Zm312-240L168-611v347h624v-347L480-432Zm0-85 312-179H168l312 179Zm-312-94v-85 432-347Z" />
							</svg></a><?php
									}
										?>
				</div>
			</td>
			</tr>
		<?php
		}
	} elseif ($valg == 'ordrer') {
		$ialt = 0;

		if ($hurtigfakt) $qtxt = "select * from ordrer where (art = 'KO' or art = 'KK') and (status < 3) $udvaelg order by $sort";
		else $qtxt = "select * from ordrer where (art = 'KO' or art = 'KK') and (status = 1 or status = 2) $udvaelg order by $sort";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($q)) {
			$ordre = "ordre" . $row['id'];
			$sum        = $row['sum'];
			$kostpris   = $row['kostpris'];
			$valutakurs = $row['valutakurs'];
			$orderTime  = $row['tidspkt'];
			if (!$orderTime) $orderTime = 0;
			if (($tidspkt - ($orderTime) > 3600) || ($row['hvem'] == $brugernavn)) {
				if ($popup) {
					$javascript = "onClick=\"javascript:$ordre=window.open('ordre.php?tjek=$row[id]&id=$row[id]&returside=ordreliste.php','$ordre','scrollbars=1,resizable=1');$ordre.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\"";
					$understreg = '<span style="text-decoration: underline;">';
					$hrefslut = "";
				} else {
					$javascript = "";
					$understreg = "<a href=ordre.php?tjek=$row[id]&id=$row[id]&returside=ordreliste.php>";
					$hrefslut = "</a>";
				}
				$linjetext = "";
			} else {
				$javascript = '';
				$understreg = '';
				$linjetext = "<span title= 'Kladde er l&aring;st af $row[hvem]'>";
			}

			if ($bgnuance1) {
				$q2 = db_select("select antal,leveres,leveret from ordrelinjer where ordre_id = '$row[id]'", __FILE__ . " linje " . __LINE__);
				$levstatus = 0;
				while ($r2 = db_fetch_array($q2)) {
					if ($levstatus === "Mangler") {
						continue;
					}
					if (($r2['leveret'] > 0 && $r2['antal'] > $r2['leveret']) || ($r2['antal'] > ($r2['leveres'] + $r2['leveret']))) {
						$levstatus = "Mangler";
					} elseif ($r2['leveret'] == 0 && ($r2['antal'] == $r2['leveres'])) {
						if ($levstatus === "Leveret") {
							$levstatus = "Mangler";
						} else {
							$levstatus = "Intet";
						}
					} elseif ((! $levstatus == "Intet") && $r2['leveret'] > 0 && $r2['antal'] == $r2['leveret']) {
						$levstatus = "Leveret";
					}
				}
				if ($levstatus == "Mangler") {
					$bgnuance = $bgnuance1;
					$color = '#000000';
					if ($row['art'] == 'KK') {
						$tr_title = "Mangler returnering af en eller flere vare.";
					} else {
						$tr_title = "Mangler modtagelse af en eller flere vare.";
					}
				} elseif ($levstatus == "Leveret") {
					$bgnuance = 0;
					$color = '#555555';
					if ($row['art'] == 'KK') {
						$tr_title = "Alt returneret, mangler kun at modtage kreditnota, s&aring; det kan bogf&oslash;res.";
					} else {
						$tr_title = "Alt modtaget, mangler kun at modtage faktura, s&aring; det kan bogf&oslash;res.";
					}
				} else {
					$bgnuance = 0;
					$color = '#000000';
					if ($row['art'] == 'KK') {
						$tr_title = "Intet returneret.";
					} else {
						$tr_title = "Intet modtaget.";
					}
				}
				$linjebg = linjefarve($linjebg, $bgcolor, $bgcolor5, $bgnuance1, $bgnuance);
				print "<tr style='color: $color; background: $linjebg' title='$tr_title'>";
			} else {
				if ($linjebg != $bgcolor) {
					$linjebg = $bgcolor;
					$color = '#000000';
				} else {
					$linjebg = $bgcolor5;
					$color = '#000000';
				}
				print "<tr style='color: $color; background: $linjebg'>";
			}

			if ($row['art'] == 'KK') {
				$firstCell = "(KN)&nbsp;".$row['ordrenr'];
			} else {
				$firstCell = $row['ordrenr'];
			}
			for ($i=0; $i<count($vis_felt); $i++) {
				$fname = trim($vis_felt[$i]);
				$align = isset($justering[$i]) && $justering[$i] ? $justering[$i] : 'left';
				print "<td align=$align";
				if ($i==0) print " $javascript";
				print ">";
				if ($fname=='ordrenr') {
					print "$understreg $linjetext $firstCell$hrefslut";
				} elseif (strpos($fname,'date')!==false) {
					print dkdato($row[$fname]);
				} elseif ($fname=='sum') {
					$sumCell = $sum;
					if ($valutakurs && $valutakurs != 100) $sumCell = $sumCell * $valutakurs / 100;
					$ialt += $sumCell;
					print dkdecimal($sumCell);
				} else {
					if (isset($row[$fname])) print $row[$fname];
				}
				print " <br></td>";
			}
		?>
			<td>
				<div style="display:flex;gap:5px;">
					<a href="formularprint.php?id=<?php print $row["id"]; ?>&formular=13&udskriv_til=PDF" target="_blank" title="Klik for at printe tilbud"><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000">
							<path d="M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-624v-168H264v624h432v-456H528ZM264-792v189-189 624-624Z" />
						</svg></a>
					<?php
					if ($row['email']) {
					?> <a href="formularprint.php?id=<?php print $row["id"]; ?>&formular=13&udskriv_til=email" target="_blank" title="Klik for at sende tilbud via email" onclick="return confirm('Er du sikker på, at du vil sende fakturaen?\nKundens mail: <?php print $row['email']; ?>')"><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000">
								<path d="M168-192q-29.7 0-50.85-21.16Q96-234.32 96-264.04v-432.24Q96-726 117.15-747T168-768h624q29.7 0 50.85 21.16Q864-725.68 864-695.96v432.24Q864-234 842.85-213T792-192H168Zm312-240L168-611v347h624v-347L480-432Zm0-85 312-179H168l312 179Zm-312-94v-85 432-347Z" />
							</svg></a><?php
									}
										?>
				</div>
			</td>
			</tr>
		<?php
		}
	} else {
		$x = 0;
		$ialt = 0;
		$query = db_select("select * from ordrer where (art = 'KO' or art = 'KK') and status >= 3 $udvaelg order by $sort", __FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			$x++;
			$ordre = "ordre" . $row['id'];
			$sum = $row['sum'];
			$kostpris = $row['kostpris'];
			$valutakurs = $row['valutakurs'];
			$javascript = "onClick=\"javascript:$ordre=window.open('ordre.php?&id=$row[id]&returside=ordreliste.php','$ordre','scrollbars=1,resizable=1');$ordre.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\"";
			if ($linjebg != $bgcolor) {
				$linjebg = $bgcolor;
				$color = '#000000';
			} else {
				$linjebg = $bgcolor5;
				$color = '#000000';
			}

			print "<tr style='color: $color; background: $linjebg'>";
			for ($i=0; $i<count($vis_felt); $i++) {
				$fname = trim($vis_felt[$i]);
				$align = isset($justering[$i]) && $justering[$i] ? $justering[$i] : 'left';
				print "<td align=$align>";
				if ($fname=='ordrenr') {
					if ($popup) {
						print "<a href=ordre.php?&id=$row[id]&returside=ordreliste.php>$row[ordrenr]</a>";
					} else {
						print "<a href=ordre.php?&id=$row[id]&returside=ordreliste.php>$row[ordrenr]</a>";
					}
				} elseif (strpos($fname,'date')!==false) {
					print dkdato($row[$fname]);
				} elseif ($fname=='sum') {
					$sumCell = $sum;
					if ($valutakurs && $valutakurs != 100) $sumCell = $sumCell * $valutakurs / 100;
					$ialt += $sumCell;
					print dkdecimal($sumCell);
				} else {
					if (isset($row[$fname])) print $row[$fname];
				}
				print "<br></td>";
			}
		?>
			<td>
				<div style="display:flex;gap:5px;">
					<a href="formularprint.php?id=<?php print $row["id"]; ?>&formular=14&udskriv_til=PDF" target="_blank" title="Klik for at printe tilbud"><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="http://www.w3.org/2000/svg" height="20px" viewBox="http://www.w3.org/2000/svg" height="20px" viewBox="http://www.w3.org/2000/svg" height="20px" viewBox="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000">
							<path d="M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-624v-168H264v624h432v-456H528ZM264-792v189-189 624-624Z" />
						</svg></a>
					<?php
					if ($row['email']) {
					?> <a href="formularprint.php?id=<?php print $row["id"]; ?>&formular=14&udskriv_til=email" target="_blank" title="Klik for at sende tilbud via email" onclick="return confirm('Er du sikker på, at du vil sende fakturaen?\nKundens mail: <?php print $row['email']; ?>')"><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="http://www.w3.org/2000/svg" height="20px" viewBox="http://www.w3.org/2000/svg" height="20px" viewBox="http://www.w3.org/2000/svg" height="20px" viewBox="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000">
								<path d="M168-192q-29.7 0-50.85-21.16Q96-234.32 96-264.04v-432.24Q96-726 117.15-747T168-768h624q29.7 0 50.85 21.16Q864-725.68 864-695.96v432.24Q864-234 842.85-213T792-192H168Zm312-240L168-611v347h624v-347L480-432Zm0-85 312-179H168l312 179Zm-312-94v-85 432-347Z" />
							</svg></a><?php
									}
										?>
				</div>
			</td>
			</tr>
<?php
		}
		$colspan = 12;
		if ($vis_projekt) $colspan++;
		print "	</td></tr>\n";
		print "<input type=hidden name=ordre_antal value='$x'>";
		print "<input type=hidden name=valg value='$valg'>";
		print "<input type=hidden name=ordrenumre value='$ordrenumre'>";
		print "<input type=hidden name=kontonumre value='$kontonumre'>";
		print "<input type=hidden name=modtagelsesnumre value='$modtagelsesnumre'>";
		print "<input type=hidden name=fakturanumre value='$fakturanumre'>";
		print "<input type=hidden name=ordredatoer value='$ordredatoer'>";
		print "<input type=hidden name=lev_datoer value='$lev_datoer'>";
		print "<input type=hidden name=fakturadatoer value='$fakturadatoer'>";
		print "<input type=hidden name=genfaktdatoer value='$genfaktdatoer'>";
		print "<input type=hidden name=summer value='$summer'>";
		print "<input type=hidden name=ref value='$ref[0]'>";
		print "<input type=hidden name=firma value='$firma'>";
		print "<input type=hidden name=lev_navne value='$lev_navne'>";
		print "<input type=hidden name=kontoid value='$kontoid'>";
		print "<input type=hidden name=sort value='$sort'>";
		print "<input type=hidden name=nysort value='$nysort'>";
		print "<tr><td colspan=$colspan align=right>";
	}

	if ($valg == 'forslag') {
		$cols = 8;
	} elseif ($valg == 'faktura') {
		$cols = 12;
	} else {
		$cols = 9;
	}

	if ($vis_projekt) $cols++;
	print "<tr><td colspan=20><hr></td></tr>\n";
	$cols = $cols - 4;
	$dk_db = dkdecimal($ialt - $totalkost);
	if ($ialt != 0) {
		$dk_dg = dkdecimal(($ialt - $totalkost) * 100 / $ialt);
	}
	$ialt = dkdecimal($ialt);
	$cols--;
	if ($valg == 'faktura') $cols--;
	print "<tr><td colspan=3></td><td align=center colspan=$cols-4><span title= 'Klik for at genberegne DB/DG'><b>" . findtekst('942|Samlet omsætning (excl. moms.))', $sprog_id) . "</td><td align=right colspan=2><b>$ialt</td><td></td></tr>\n";
} else {
	include "scanAttachments/frontpage/frontTable.php";
	include "scanAttachments/scanAttachments.php";
}
#if ($genberegn==1) print "<meta http-equiv=\"refresh\" content=\"0;URL='ordreliste.php?genberegn=2$hreftext'\">";

$cols++;
if ($valg == 'faktura') $cols++;
$cols = $cols + 4;
print "<tr><td colspan=20><hr></td></tr>\n";
if ($valg == 'skanBilag' && $r = db_fetch_array(db_select("select * from grupper where art='bilag'", __FILE__ . " linje " . __LINE__)) && $box6 = $r['box6']) {
	print "<tr><td colspan=\"4\" width=\"100%\" align=\"left\" valign=\"top\"><span title=\"" . findtekst('1507|Klik her for at importere en elektronisk faktura af typen OIOUBL)', $sprog_id) . "\"><a href=ublimport.php?funktion=gennemse>" . findtekst('943|Importer OIOUBL faktura)', $sprog_id) . "</a></span></td></tr>";
}

print "</tbody>
</table>
	</td></tr>
</tbody></table>";



if ($menu == 'T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

?>