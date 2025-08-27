<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------------kreditor/kreditor.php---lap 4.0.8------2025-04-15----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2025 saldi.dk aps
// ----------------------------------------------------------------------
// 2018.03.08 Indhold kopieret dra debitor/debitor.php og tilrettet til kreditor
// 20210331 LOE Translated some of these texts to English
// 20210705 LOE Created switch case function for box6 to translate langue and also reassigned valg variable for creditor
// 20230323 PBLM Fixed minor errors
// 20230522 PHR php8
// 01072025 PBLM Added openKreditorKort function to open creditor card in same window


#ob_start();
@session_start();
$s_id = session_id();

global $menu;

$check_all = $ny_sort = $skjul_lukkede = NULL;
$dg_id = $dg_liste = $dg_navn = $find = $selectfelter = array();

print "
<script LANGUAGE=\"JavaScript\">
<!--
function MasseFakt(tekst)
{
	var agree = confirm(tekst);
	if (agree)
		return true ;
	else
    return false ;
}
function openKreditorKort(kreditorId) {
    // Open creditor card in same window
    window.location.href = 'kreditorkort.php?id=' + kreditorId + '&returside=kreditor.php';
}
// -->
</script>
";
$css = "../css/standard.css";
$modulnr = 6;
$firmanavn = NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/udvaelg.php");
include("../includes/topline_settings.php");
include("../includes/row-hover-style.js.php");


if ($menu == 'T') {
	$title = "Konti";
} else {
	$title = "Kreditorliste";
}

$id = if_isset($_GET['id']);
$returside = if_isset($_GET['returside']);

$valg = strtolower(if_isset($_GET['valg']));
$sort = if_isset($_GET['sort']);
$start = if_isset($_GET['start']);
$nysort = if_isset($_GET['nysort']);
$kreditor1 = lcfirst(findtekst(1169, $sprog_id)); #20210331
$brisk1 = findtekst(944, $sprog_id);

$aa = findtekst(360, $sprog_id);
$firmanavn = ucfirst(str_replace(' ', '_', $aa));

if (!$valg) $valg = "$kreditor1";
#echo "$kreditor1";
$sort = str_replace("adresser.", "", $sort);
if ($sort && $nysort == $sort) $sort = $sort . " desc";
elseif ($nysort) $sort = $nysort;
$r = db_fetch_array(db_select("select box7 from grupper where art = 'DIV' and kodenr = '2'", __FILE__ . " linje " . __LINE__));
$jobkort = $r['box7'];

#>>>>>>>>>>>>>>>>>>>>>
function select_valg($valg, $box)
{
	global $kreditor1, $sprog_id;
	if ($valg == "$kreditor1") {
		switch ($box) {
			case "box3":
				return "kontonr" . chr(9) . "firmanavn" . chr(9) . "addr1" . chr(9) . "addr2" . chr(9) . "postnr" . chr(9) . "bynavn" . chr(9) . "kontakt" . chr(9) . "tlf" . chr(9) . "kontoansvarlig";
				break;
			case "box5":
				return "right" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left";
				break;
			case "box4":
				return "5" . chr(9) . "35" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10";
				break;
			case "box6":
				return "" . findtekst(284, $sprog_id) . "" . chr(9) . "" . findtekst(360, $sprog_id) . "" . chr(9) . "Adresse" . chr(9) . "Adresse 2" . chr(9) . "" . findtekst(144, $sprog_id) . "" . chr(9) . "By" . chr(9) . "" . findtekst(502, $sprog_id) . "" . chr(9) . "" . findtekst(37, $sprog_id) . "";
			default:
				return "choose a box";
				break;
		}
	} else {

		switch ($box) {
			case "box3":
				return "kontonr" . chr(9) . "firmanavn" . chr(9) . "addr1" . chr(9) . "addr2" . chr(9) . "postnr" . chr(9) . "bynavn" . chr(9) . "kontakt" . chr(9) . "tlf" . chr(9) . "kontoansvarlig";
				break;
			case "box5":
				return "right" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left";
				break;
			case "box4":
				return "5" . chr(9) . "35" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10";
				break;
			case "box6":
				return "" . findtekst(284, $sprog_id) . "" . chr(9) . "" . findtekst(360, $sprog_id) . "" . chr(9) . "Adresse" . chr(9) . "Adresse 2" . chr(9) . "" . findtekst(144, $sprog_id) . "" . chr(9) . "By" . chr(9) . "" . findtekst(502, $sprog_id) . "" . chr(9) . "" . findtekst(37, $sprog_id) . ""; #20210705
			default:
				return "choose a box";
				break;
		}
	}
}

$box5 = select_valg("$valg", "box5");
$box3 = select_valg("$valg", "box3");
$box4 = select_valg("$valg", "box4");
$box6 = select_valg("$valg", "box6");
#>>>>>>>>>>>>>>>>>>>>>


if (!$r = db_fetch_array(db_select("select id from grupper where art = 'KLV' and kode='$valg' and kodenr = '$bruger_id'", __FILE__ . " linje " . __LINE__))) {
	#	db_modify("update grupper set box2='$returside' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
	#} else { ".findtekst(360,$sprog_id)."
	// if ($valg=="$kreditor1") { #20210331
	// 	#$box3="kontonr".chr(9)."firmanavn".chr(9)."addr1".chr(9)."addr2".chr(9)."postnr".chr(9)."bynavn".chr(9)."kontakt".chr(9)."tlf".chr(9)."kontoansvarlig"; 
	// 	$box3= "".findtekst(284,$sprog_id)."".chr(9)."".findtekst(360,$sprog_id)."".chr(9)."Adresse".chr(9)."Adresse 2".chr(9)."".findtekst(144,$sprog_id)."".chr(9)."By".chr(9)."".findtekst(502,$sprog_id)."".chr(9)."".findtekst(64,$sprog_id)."";
	// 	$box5="right".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left";
	// 	$box4="5".chr(9)."35".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10";
	// 	#$box6="Kontonr".chr(9)."Firmanavn".chr(9)."Adresse".chr(9)."Adresse 2".chr(9)."Postnr".chr(9)."By".chr(9)."Kontakt".chr(9)."Telefon".chr(9)."S&aelig;lger";
	// 	$box6="".findtekst(284,$sprog_id)."".chr(9)."".findtekst(360,$sprog_id)."".chr(9)."Adresse".chr(9)."Adresse 2".chr(9)."".findtekst(144,$sprog_id)."".chr(9)."By".chr(9)."".findtekst(502,$sprog_id)."".chr(9)."".findtekst(37,$sprog_id)."";
	// } else {
	// 	#$box3="kontonr".chr(9)."firmanavn".chr(9)."addr1".chr(9)."addr2".chr(9)."postnr".chr(9)."bynavn".chr(9)."kontakt".chr(9)."tlf".chr(9)."kontoansvarlig";
	// 	$box3= "".findtekst(284,$sprog_id)."".chr(9)."".findtekst(360,$sprog_id)."".chr(9)."Adresse".chr(9)."Adresse 2".chr(9)."".findtekst(144,$sprog_id)."".chr(9)."By".chr(9)."".findtekst(502,$sprog_id)."".chr(9)."".findtekst(64,$sprog_id)."";
	// 	$box5="right".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left";
	// 	$box4="5".chr(9)."35".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10";
	// 	#$box6="Kontonr".chr(9)."Firmanavn".chr(9)."Adresse".chr(9)."Adresse 2".chr(9)."Postnr".chr(9)."By".chr(9)."Kontakt".chr(9)."Telefon".chr(9)."S&aelig;lger";
	// 	$box6 = "".findtekst(284,$sprog_id)."".chr(9)."".findtekst(360,$sprog_id)."".chr(9)."Adresse".chr(9)."Adresse 2".chr(9)."".findtekst(144,$sprog_id)."".chr(9)."By".chr(9)."".findtekst(502,$sprog_id)."".chr(9)."".findtekst(37,$sprog_id)."";
	// }

	######


	db_modify("insert into grupper(beskrivelse,kode,kodenr,art,box3,box4,box5,box6,box7)values('$brisk1','$valg','$bruger_id','KLV','$box3','$box4','$box5','$box6','100')", __FILE__ . " linje " . __LINE__);
} else {

	if ($h1 = db_fetch_array(db_select("select*from grupper where art='KLV' and kode='$valg' and kodenr = '$bruger_id' ", __FILE__ . " linje " . __LINE__))) $q = $h1['box6']; #20210331

	if ($q !== "" || false) {
		if (!in_array(trim("$firmanavn"), explode(chr(9), $q))) {

			$qtxt = "update grupper set beskrivelse='$brisk1',kode='$valg',kodenr='$bruger_id',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='100' where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}

		######
	} else {
		$qtxt = "update grupper set box3='$box3',box6='$box6' where art = 'KLV' and kode='$valg' and kodenr = '$bruger_id'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}

	$r = db_fetch_array(db_select("select box1,box2,box7,box9,box10,box11 from grupper where art = 'KLV' and kode='$valg' and kodenr = '$bruger_id'", __FILE__ . " linje " . __LINE__));
	$dg_liste = explode(chr(9), $r['box1']);
	if (!empty($r["box2"])) {
		$cat_liste = explode(chr(9), $r['box2']);
	}
	$skjul_lukkede = $r['box11'];
	$linjeantal = $r['box7'];
	if (!$sort) $sort = $r['box9'];
	$find = explode("\n", $r['box10']);
	// var_dump($box6);
	// var_dump($firmanavn);
}
if ($valg == "$kreditor1") {
	$valg = 'kreditor';
} #20210705
$backUrl = isset($_GET['returside'])
	? $_GET['returside']
	: '../index/menu.php';

if ($popup) $returside = "../includes/luk.php";
else $returside = $backUrl;

db_modify("update grupper set box9='$sort' where art = 'KLV' and kode='$valg' and kodenr = '$bruger_id'", __FILE__ . " linje " . __LINE__);

$tidspkt = date("U");

if ($submit = if_isset($_POST['submit'])) {
	$find = if_isset($_POST['find']);
	$valg = if_isset($_POST['valg']);
	$sort = if_isset($_POST['sort']);
	$nysort = if_isset($_POST['nysort']);
	$firma = if_isset($_POST['firma']);
}


if (!$valg) $valg = "kreditor";
if (!$sort) $sort = "firmanavn";

$sort = str_replace("adresser.", "", $sort);
$sortering = $sort;

if ($menu == 'T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">";
	print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";
	print "<div class=\"headerTxt\">$title</div>";
	print "<div class=\"headerbtnRght headLink\"><a accesskey=V href=kreditorvisning.php?valg=$valg title='Ændre ordrevisnig'><i class='fa fa-gear'></i></a> &nbsp; <a accesskey=N href='kreditorkort.php?returside=kreditor.php' title='Opret nyt leverandør kort'><i class='fa fa-plus-square'></i></a></div>";
	print "</div>";
	print "<div class='content-noside'>";
} elseif ($menu == 'S') {
	print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>\n";
	print "<tr><td height = 25 align=center valign=top>";
	print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>\n";

	print "<tr><td width=10%><a href=$returside accesskey=L>
		   <button style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		. findtekst(30, $sprog_id) . "</button></a></td>";

	print "<td width = 80% align=center style='$topStyle'>" . findtekst(607, $sprog_id) . "</td>";

	print "<td width=5%><a accesskey=V href=kreditorvisning.php?valg=$valg>
		   <button style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		. findtekst(813, $sprog_id) . "</button></a></td>\n";

	print "<td width=5%><a href=kreditorkort.php?returside=kreditor.php>
		   <button style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		. findtekst(39, $sprog_id) . "</button></a></td></tr>\n";

	print "</tbody></table>";
	print " </td></tr>\n<tr><td align=\"center\" valign=\"top\" width=\"100%\">";
} else {
	print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>\n";
	print "<tr><td height = 25 align=center valign=top>";
	print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>\n";
	print "<tr><td width=10% $top_bund><a href=$returside accesskey=L>" . findtekst(30, $sprog_id) . "</a></td>";
	print "<td width = 80% align=center $top_bund>" . findtekst(607, $sprog_id) . "</td>";
	print "<td width=5% $top_bund><a accesskey=V href=kreditorvisning.php?valg=$valg>" . findtekst(813, $sprog_id) . "</a></td>\n";
	#if ($popup) {
	#		print "<td width=5% $top_bund onClick=\"javascript:kreditor=window.open('kreditorkort.php?returside=kreditor.php','ordre','scrollbars=1,resizable=1');ordre.focus();\"><a accesskey=N href=kreditor.php?sort=$sort>Ny</a></td>\n";
	#	} else {
	print "<td width=5%  $top_bund><a href=kreditorkort.php?returside=kreditor.php>" . findtekst(39, $sprog_id) . "</a></td></tr>\n";
	#	}
	#print "<tr><td></td><td align=center><table border=1	cellspacing=0 cellpadding=0><tbody>\n";
	#print "<td width = 20%$top_bund align=center><a href=kreditor.php?valg=tilbud accesskey=T>Tilbud</a></td>";
	#print "<td width = 20% bgcolor=$bgcolor5 align=center> Ordrer</td>";
	#print "<td width = 20% bgcolor=$bgcolor5 align=center> Faktura</td>"; 
	#print "</tbody></table></td><td></td</tr>\n";

	print "</tbody></table>";
	print " </td></tr>\n<tr><td align=\"center\" valign=\"top\" width=\"100%\">";
}
$vis_felt = array();
$qtxt = "select box3,box4,box5,box6,box8,box11 from grupper where art = 'KLV' and kodenr = '$bruger_id' and kode='$valg'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$vis_felt = explode(chr(9), $r['box3']);
	$feltbredde = explode(chr(9), $r['box4']);
	$justering = explode(chr(9), $r['box5']);
	$feltnavn = explode(chr(9), $r['box6']);
	$select = explode(chr(9), $r['box8']);
}

$y = 0;
for ($x = 0; $x <= count($vis_felt); $x++) {
	if (!empty($select[$x])) {
		$selectfelter[$y] = $vis_felt[$x];
		$y++;
	}
}

$numfelter = array("rabat", "momskonto", "kreditmax", "betalingsdage", "gruppe", "kontoansvarlig");
####################################################################################
$udvaelg = NULL;
$tmp = trim(if_isset($find[0], NULL));
for ($x = 1; $x < count($vis_felt); $x++) {
	$tmp = $tmp . "\n" . trim(if_isset($find[$x], NULL));
}
$tmp = db_escape_string(if_isset($tmp, NULL));
$qtxt = "update grupper set box10='$tmp' where art = 'KLV' and kode='$valg' and kodenr = '$bruger_id'";
db_modify($qtxt, __FILE__ . " linje " . __LINE__);
if ($skjul_lukkede) $udvaelg = " and lukket != 'on'";
for ($x = 0; $x < count($vis_felt); $x++) {
	$find[$x] = addslashes(trim($find[$x]));
	$tmp = $vis_felt[$x];
	if ($find[$x] && !in_array($tmp, $numfelter)) {
	 $searchTerm = "%" . $find[$x] . "%";

		$tmp2 = "adresser." . $tmp . "";
		$udvaelg = $udvaelg . udvaelg($searchTerm, $tmp2, '');

		// $udvaelg = $udvaelg . udvaelg($find[$x], $tmp2, '');
	} elseif ($find[$x] || $find[$x] == "0") {
		$searchTerm = "%" . $find[$x] . "%";

		$tmp2 = "adresser." . $tmp . "";
		// $udvaelg = $udvaelg . udvaelg($find[$x], $tmp2, 'NR');
		$udvaelg = $udvaelg . udvaelg($searchTerm, $tmp2, 'NR');

	}
}

if (count($dg_liste)) {
	$x = 0;
	$q = db_select("select * from grupper where art = 'DG' order by beskrivelse", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$dg_id[$x] = $r['id'];
		$dg_kodenr[$x] = $r['kodenr'] * 1;
		$dg_navn[$x] = $r['beskrivelse'];
	}
	$dg_antal = $x;
}

if (isset($cat_liste)) {
	$r = db_fetch_array(db_select("select box1,box2 from grupper where art='KredInfo'", __FILE__ . " linje " . __LINE__));
	$cat_id = explode(chr(9), $r['box1']);
	$cat_beskrivelse = explode(chr(9), $r['box2']);
	$cat_antal = count($cat_id);
}

$sortering = "adresser." . $sortering;

$ialt = 0;
$lnr = 0;
if (!$linjeantal) $linjeantal = 100;
$slut = $start + $linjeantal;
$adresserantal = 0;

$r = db_fetch_array(db_select("select count(id) as antal from adresser where art = 'K' $udvaelg", __FILE__ . " linje " . __LINE__));
$antal = $r['antal'];

print "<table cellpadding=1 cellspacing=1 border=0 valign=top width=100% class='dataTable'><tbody>\n<tr>";
if ($start > 0) {
	$prepil = $start - $linjeantal;
	if ($prepil < 0) $prepil = 0;
	print "<td><a href=kreditor.php?start=$prepil&valg=$valg><img class='imgFade' src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
} else {
	print "<td>";
	if (file_exists("rotary_addrsync.php")) print "<a href=\"rotary_addrsync.php\" target=\"blank\" title=\"Klik her for at synkronisere medlemsinfo\">!</a>";
	print "</td>";
}
for ($x = 0; $x < count($feltnavn); $x++) {
	if ($feltbredde[$x]) $width = "width=$feltbredde[$x]";
	else $width = "";
	print "<td align=$justering[$x] $width><b><a href='kreditor.php?nysort=$vis_felt[$x]&sort=$sort&valg=$valg'>$feltnavn[$x]</b></td>\n";
}
if ($antal > $slut && !$dg_liste[0] && !isset($cat_liste[0])) {
	$nextpil = $start + $linjeantal;
	print "<td align=right><a href=kreditor.php?start=$nextpil&valg=$valg><img class='imgFade' src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td><tr>";
}
print "</tr>\n";
if ($dg_antal || $cat_antal) $linjeantal = 0;
#################################### Sogefelter ##########################################


print "<form name=kreditorliste action=kreditor.php method=post>";
print "<input type=hidden name=valg value=$valg>";
print "<input type=hidden name=sort value='$ny_sort'>";
print "<input type=hidden name=nysort value='$sort'>";
print "<input type=hidden name=kontoid value=" . if_isset($kontoid, 0) . ">";

print "<tr><td></td>"; #giver plads til venstrepil v. flere sider
if (!$start) {
	for ($x = 0; $x < count($vis_felt); $x++) {
		$span = '';
		print "<td align=$justering[$x]><span title= '$span'>";
		if ($vis_felt[$x] == "kontoansvarlig") {
			$ansat_id = array();
			$ansat_init = array();
			$y = 0;
			$q = db_select("select distinct(ansatte.id) as ansat_id,ansatte.initialer as initialer from ansatte,adresser where adresser.art='S' and ansatte.konto_id=adresser.id order by ansatte.initialer", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$y++;
				$ansat_id[$y] = $r['ansat_id'];
				$ansat_init[$y] = $r['initialer'];
			}
			$ansatantal = $y;
			if (in_array($vis_felt[$x], $selectfelter)) {
				print "<SELECT NAME=\"find[$x]\">";
				if (!$find[$x]) print "<option value=\"\"></option>";
				for ($y = 1; $y <= $ansatantal; $y++) if ($ansat_init[$y] && $find[$x] == $ansat_id[$y]) print "<option value=\"$ansat_id[$y]\">" . stripslashes($ansat_init[$y]) . "</option>";
				if ($find[$x]) print "<option value=\"\"></option>";
				for ($y = 1; $y <= $ansatantal; $y++) if ($ansat_init[$y] && $find[$x] != $ansat_id[$y]) print "<option value=\"$ansat_id[$y]\">" . stripslashes($ansat_init[$y]) . "</option>";
				print "</SELECT></td>";
			}
			#			print "<input class=\"inputbox\" type=text readonly=$readonly size=$feltbredde[$x] style=\"text-align:$justering[$x]\" name=find[$x] value=\"$r[tmp]\">";
		} elseif ($vis_felt[$x] == "status") {
			$status_id = array();
			$status_init = array();
			$r = db_fetch_array(db_select("select box3,box4 from grupper where art='KredInfo'", __FILE__ . " linje " . __LINE__));
			$status_id = explode(chr(9), $r['box3']);
			$status_beskrivelse = explode(chr(9), $r['box4']);
			$status_antal = count($status_id);
			if (in_array($vis_felt[$x], $selectfelter)) {
				print "<SELECT NAME=\"find[$x]\">";
				if (!$find[$x]) print "<option value=\"\"></option>";
				for ($y = 0; $y < $status_antal; $y++) {
					if ($status_beskrivelse[$y] && $find[$x] == $status_id[$y]) print "<option value=\"$status_id[$y]\">" . stripslashes($status_beskrivelse[$y]) . "</option>";
				}
				if ($find[$x]) print "<option value=\"\"></option>";
				for ($y = 0; $y < $status_antal; $y++) {
					if ($status_beskrivelse[$y] && $find[$x] != $status_id[$y]) print "<option value=\"$status_id[$y]\">" . stripslashes($status_beskrivelse[$y]) . "</option>";
				}
				print "</SELECT></td>";
			}
			#			print "<input class=\"inputbox\" type=text readonly=$readonly size=$feltbredde[$x] style=\"text-align:$justering[$x]\" name=find[$x] value=\"$r[tmp]\">";
		} elseif (in_array($vis_felt[$x], $selectfelter)) {
			$tmp = $vis_felt[$x];
			print "<SELECT NAME=\"find[$x]\">";
			$q = db_select("select distinct($tmp) from adresser where art = 'K'");
			print "<option>" . stripslashes($find[$x]) . "</option>";
			if ($find[$x]) print "<option></option>";
			while ($r = db_fetch_array($q)) {
				print "<option>$r[$tmp]</option>";
			}
			print "</SELECT></td>";
		} else {
			if ($menu == 'T') {
				print "<input class=\"inputbox\" type=text size=10 style=\"text-align:$justering[$x]\" name=find[$x] value=\"$find[$x]\">";
			} else {
				print "<input class=\"inputbox\" type=text size=$feltbredde[$x] style=\"text-align:$justering[$x]\" name=find[$x] value=\"$find[$x]\">";
			}
		}
	}
	print "</td>\n";
	print "<td><input type=submit value=\"OK\" name=\"submit\"></td>";
	print "</form></tr><td></td>\n";
}
######################################################################################################################
$udv1    = $udvaelg;
$colspan = count($vis_felt) + 1;
$dgcount = count($dg_liste);
(!$dgcount) ? $dgcount = 1 : NULL;
for ($i = 0; $i < $dgcount; $i++) {
	if ($dg_liste[$i]) {
		for ($i2 = 0; $i2 <= $dg_antal; $i2++) {
			if ($dg_liste[$i] == $dg_id[$i2]) {
				if (!$start && !$lnr) {
					#					print "<tr><td colspan=\"$colspan\"><hr></td>";
					$tmp = $start + $linjeantal;
				}
				if (!$cat_liste[0]) {
					print "<tr><td></td><td colspan=\"2\"><b>$dg_navn[$i2]</b></td></tr>";
					print "<tr><td colspan=\"$colspan\"><hr></td>";
				}
				$udv1 = $udvaelg . " and gruppe=$dg_kodenr[$i2]";
				break 1;
			}
		}
	}
	if (isset($cat_liste)) {
		$catcount = count($cat_liste);
	} else {
		$catcount = 0;
	}
	(!$catcount) ? $catcount = 1 : NULL;
	for ($i3 = 0; $i3 < $catcount; $i3++) {
		if (isset($cat_liste) && $cat_liste[$i3]) {
			for ($i4 = 0; $i4 <= $cat_antal; $i4++) {
				if ($cat_liste[$i3] == $cat_id[$i4]) {
					if (!$start && !$lnr) {
						#						print "<td colspan=\"$colspan\"><b>$cat_beskrivelse[$i4]</b></td></tr>";
						#						print "<tr><td colspan=\"$colspan\"><hr></td>";
						$tmp = $start + $linjeantal;
						#						if ($antal>$slut) print "<td align=center><a href=kreditor.php?start=$tmp&valg=$valg><img src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td><tr>";
					}
					print "<tr><td colspan=\"$colspan\"><hr></td>";
					if ($dg_navn[$i2]) $tmp = "<td colspan=\"2\"><b>$dg_navn[$i2]</b></td>";
					else $tmp = "";
					print "<tr><td></td>$tmp<td colspan=\"2\"><b>$cat_beskrivelse[$i4]</b></td></tr>";
					print "<tr><td colspan=\"$colspan\"><hr></td>";
					$udv2 = $udv1 . " and (kategori = '$cat_id[$i4]' or kategori LIKE '$cat_id[$i4]" . chr(9) . "%' ";
					$udv2 .= "or kategori LIKE '%" . chr(9) . "$cat_id[$i4]' or kategori LIKE '%" . chr(9) . "$cat_id[$i4]" . chr(9) . "%')";	#20160218
					break 1;
				}
			}
		}

		if (!isset($udv2)) $udv2 = $udv1;
		if (!$udv2) $udv2 = $udvaelg;
		$adresseantal = 0;
		$query = db_select("select * from adresser where art = 'K' $udv2 order by $sortering", __FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			$kreditorkort = "kreditorkort" . $row['id'];
			$lnr++;
			if (($lnr >= $start && $lnr < $slut) || $udv2) {
				$adresseantal++;
				#if (($tidspkt-($row['tidspkt'])>3600)||($row['hvem']==$brugernavn)) {
				#				if ($popup) {
				#					$javascript="onClick=\"javascript:".$valg."kort=window.open('".$valg."kort.php?tjek=$row[id]&id=$row[id]&returside=kreditor.php','$kreditorkort','scrollbars=1,resizable=1');$kreditorkort.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" ";
				#					$understreg='<span style="text-decoration: underline;">';
				#					$hrefslut="";
				#				} else {
				$javascript = "";
				$understreg = "<a href=" . $valg . "kort.php?tjek=$row[id]&id=$row[id]&returside=kreditor.php>";
				$hrefslut = "</a>";
				#				}
				$linjetext = "";
				/*}	
			else {
				$javascript="onClick=\"javascript:$kreditorkort.focus();\"";
				$understreg='';
				$linjetext="<span title= 'Kortet er l&aring;st af $row[hvem]'>";
			}*/
				if (isset($linjebg) && $linjebg != $bgcolor) {
					$linjebg = $bgcolor;
					$color = '#000000';
				} else {
					$linjebg = $bgcolor5;
					$color = '#000000';
				}
				print "<tr bgcolor=\"$linjebg\" onclick='javascript:openKreditorKort(".$row["id"].");'><td></td>";
				print "<td align=$justering[0] $javascript> $linjetext $row[kontonr]</span><br></td>";
				for ($x = 1; $x < count($vis_felt); $x++) {
					print "<td align=$justering[$x]>";
					$tmp = $vis_felt[$x];
					if ($vis_felt[$x] == 'kontoansvarlig') {
						for ($y = 1; $y <= $ansatantal; $y++) {
							if ($ansat_id[$y] == $row[$tmp]) print stripslashes($ansat_init[$y]);
						}
					} elseif ($vis_felt[$x] == 'status') {
						for ($y = 0; $y <= $status_antal; $y++) {
							if ($row[$tmp] && $status_id[$y] == $row[$tmp]) print stripslashes($status_beskrivelse[$y]);
						}
					} else print $row[$tmp];
					print "</td>";
				}
				print "<td></td>";
				print "<input type=hidden name=adresse_id[$adresseantal] value=$row[id]>";
				#			$colspan = count($vis_felt) + 2;

				#		if ($r=db_fetch_array(db_select("select id from grupper where art = 'KLV' and kode = '$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__))) {
				#			db_modify("update grupper set box1='$kreditorliste' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
				#		} 
			}
		}
	}
}
#print "<tr><td colspan=$colspan><hr></td></tr>\n";
#$cols--;

print "<tr>";
if (isset($prepil))	print "<td colspan=$colspan><a href=kreditor.php?start=$prepil&valg=$valg><img class='imgFade' src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
else print "<td colspan=$colspan></td>";
if (isset($nextpil)) print "<td align=right><a href=kreditor.php?start=$nextpil&valg=$valg><img class='imgFade' src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td><tr>";
else print "<td></td>";
print "</tr>";
$colspan++;
print "<tr><td colspan=$colspan width=100%></td></tr>";
#print "<table border=0 width=100%><tbody>";

#print "</tbody></table></td>";
#print "<tr><td colspan=$colspan><hr></td></tr>\n";

print "</tbody>
</table>
	</td></tr>
</tbody></table>
";

if ($menu == 'T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}
