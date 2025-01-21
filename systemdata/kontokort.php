<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/kontokort.php -----patch 4.0.8 ----2024-01-18--------
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
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 2013.02.10 Break ændret til break 1
// 20160116 Tilføjet valuta  
// 20160129	Valutakode og kurs blev ikke sat ved oprettelse af ny driftskonti.
// 20190220 MSC - Rettet isset fejl og topmenu design
// 20190221 MSC - Rettet isset fejl
// 20210211 PHR - Some cleanup
// 20210707 LOE - Translated these texts with findtekst function 
// 20220605 MSC - Implementing new design
// 20220605 PHR - php8

@session_start();
$s_id = session_id();
$title = "Kontokort";
$css = "../css/standard.css";

?>
	<script LANGUAGE="JavaScript">
	<!--
	function confirmSlet()
	{
		var agree=confirm("Slet?");
		if (agree)
	   return true ;
		else
	   return false ;
		}
	// -->
	</script>
<?php
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/genberegn.php");
include("../includes/topline_settings.php");

$genvej = $kontotype = NULL; # ellers hentes typen fra connect.php  
/*
if (!isset ($_POST['gem'])) $_POST['gem'] = NULL;
if (!isset ($previousPage)) $previousPage = 0;
if (!isset ($nextPage)) $nextPage = 0;
if (!isset ($valuta)) $valuta = 0;
if (!isset ($saldo)) $saldo = 0;
if (!isset ($kontonr)) $kontonr = 0;
if (!isset ($beskrivelse)) $beskrivelse = 0;
if (!isset ($lukket)) $lukket = 0;
if (!isset ($slet)) $slet = 0;
if (!isset ($_POST['fra_kto'])) $_POST['fra_kto'] = NULL;
if (!isset ($valuta_kode)) $valuta_kode = null;
*/
$regnaar_return = "";
$maaned_fra = "";
$maaned_til = "";
$aar_fra = "";
$aar_til = "";
$dato_fra = "";
$dato_til = "";
$konto_fra = "";
$konto_til = "";
$rapportart = "";

if (isset($_GET['regnaar']))
	$regnaar_return = $_GET['regnaar'];
if (isset($_GET['maaned_fra']))
	$maaned_fra = $_GET['maaned_fra'];
if (isset($_GET['maaned_til']))
	$maaned_til = $_GET['maaned_til'];
if (isset($_GET['aar_fra']))
	$aar_fra = $_GET['aar_fra'];
if (isset($_GET['aar_til']))
	$aar_til = $_GET['aar_til'];
if (isset($_GET['dato_fra']))
	$dato_fra = $_GET['dato_fra'];
if (isset($_GET['dato_til']))
	$dato_til = $_GET['dato_til'];
if (isset($_GET['konto_fra']))
	$konto_fra = $_GET['konto_fra'];
if (isset($_GET['konto_til']))
	$konto_til = $_GET['konto_til'];
if (isset($_GET['rapportart']))
	$rapportart = $_GET['rapportart'];

$id = if_isset($_GET['id']);
if (isset($_POST['slet'])) {
	$id = $_POST['id'] * 1;
	$kontonr = $_POST['kontonr'] * 1;

	db_modify("delete from kontoplan where id = $id", __FILE__ . " linje " . __LINE__);
	$q = db_select("select id from kontoplan where kontonr >= $kontonr and regnskabsaar = '$regnaar' order by kontonr", __FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q))
		$id = $r['id'];
	else
		$id = 0;
} elseif (isset($_POST['gem'])) {
	$id = $_POST['id'];
	$kontonr = (int) $_POST['kontonr'];
	$map_to = (int) $_POST['map_to'];
	$beskrivelse = addslashes($_POST['beskrivelse']);
	$kontotype = if_isset($_POST['kontotype']);
	#	$katagori=if_isset($_POST['katagori']);
	$moms = if_isset($_POST['moms']);
	$fra_kto = if_isset($_POST['fra_kto']) * 1;
	$til_kto = if_isset($_POST['kontonr']);
	$saldo = if_isset($_POST['saldo']);
	$valuta = if_isset($_POST['valuta']);
	$ny_valuta = if_isset($_POST['ny_valuta']);
	$genvej = if_isset($_POST['genvej']);
	$lukket = if_isset($_POST['lukket']);

	$regnaar_return = if_isset($_POST['regnaar']);
	$maaned_fra = if_isset($_POST['maaned_fra']);
	$maaned_til = if_isset($_POST['maaned_til']);
	$aar_fra = if_isset($_POST['aar_fra']);
	$aar_til = if_isset($_POST['aar_til']);
	$dato_fra = if_isset($_POST['dato_fra']);
	$dato_til = if_isset($_POST['dato_til']);
	$konto_fra = if_isset($_POST['konto_fra']);
	$konto_til = if_isset($_POST['konto_til']);
	$rapportart = if_isset($_POST['rapportart']);

	if ($kontotype != 'Sum' && $kontotype != 'Resultat') {
		$fra_kto = 0;
		$til_kto = 0;
	}
	if (!$valuta)
		$valuta = 'DKK';
	if (!$ny_valuta)
		$ny_valuta = 'DKK';
	if ($ny_valuta != $valuta) {
		$dd = date("Y-m-d");
		#		if ($saldo) {
		if ($valuta == 'DKK') {
			$valutakode = 0;
			$kurs = 100;
		} else {
			$qtxt = "select kodenr from grupper where art='VK' and box1 = '$valuta'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			$valutakode = $r['kodenr'];
			$qtxt = "select kurs from valuta where gruppe='$valutakode' and valdate <= '$dd' ";
			$qtxt .= "order by valuta.valdate desc limit 1";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			if ($r['kurs']) {
				$kurs = $r['kurs'];
			}
		}
		if ($ny_valuta == 'DKK') {
			$ny_valutakode = 0;
			$ny_kurs = 100;
		} else {
			$qtxt = "select kodenr from grupper where art='VK' and box1 = '$ny_valuta'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			$ny_valutakode = $r['kodenr'] * 1;
			$qtxt = "select kurs from valuta where gruppe='$ny_valutakode' and valdate <= '$dd' ";
			$qtxt .= "order by valuta.valdate desc limit 1";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			if ($r['kurs']) {
				$ny_kurs = $r['kurs'];
			}
		}
		$qtxt = "update kontoplan set valuta ='$ny_valutakode', valutakurs='$ny_kurs' where id = '$id'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		#		}
	}
	if ($kontotype == 'Overskrift') {
		$kontotype = 'H';
		$moms = "";
	} elseif ($kontotype == 'Drift')
		$kontotype = 'D';
	elseif ($kontotype == 'Status')
		$kontotype = 'S';
	elseif ($kontotype == 'Lukket')
		$kontotype = 'L';
	elseif ($kontotype == 'Sum') {
		$kontotype = 'Z';
		$moms = "";
	} elseif ($kontotype == 'Resultat') {
		$kontotype = 'R';
		$moms = "";
	} elseif ($kontotype == 'Sideskift')
		$kontotype = 'X';

	if ($kontonr < 1)
		print "<BODY onLoad=\"javascript:alert('Kontonummer skal v&aelig;re et positivt heltal')\">";
	elseif (!$id) {
		$qtxt = "select id from kontoplan where kontonr = $kontonr and regnskabsaar = '$regnaar'";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			print "<BODY onLoad=\"javascript:alert('Der findes allerede en konto med nr: $kontonr')\">";
			$id = 0;
		} else {
			$x = 0;
			$q = db_select("select kodenr from grupper where art = 'RA' order by kodenr", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if ($r['kodenr'] >= $x)
					$x = $r['kodenr'];
			}
			for ($y = $regnaar; $y <= $x; $y++) {
				$qtxt = "select id from kontoplan where kontonr = $kontonr and regnskabsaar = '$y'";
				$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
				if (!$r = db_fetch_array($q)) {
					$qtxt = "insert into kontoplan (kontonr, beskrivelse, kontotype, primo, regnskabsaar,";
					$qtxt .= "genvej,valuta,valutakurs)";
					$qtxt .= "values ($kontonr, '$beskrivelse', '$kontotype', '0', '$y', '$genvej','0','100')";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
			}
			$qtxt = "select id from kontoplan where kontonr = $kontonr and regnskabsaar = '$regnaar'";
			($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) ? $id = $r['id'] : $id = 0;
		}
	}
	if ($id > 0) {
		if (!$fra_kto)
			$fra_kto = 0;
		if (!$til_kto)
			$til_kto = 0;
		$qtxt = "select id from kontoplan where kontonr = $kontonr and regnskabsaar = '$regnaar' and id!='$id'";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			alert("Der findes allerede en konto med nr: $kontonr");
		} else {
			$qtxt = "update kontoplan set kontonr = $kontonr, beskrivelse = '$beskrivelse', kontotype = '$kontotype', ";
			$qtxt .= "moms = '$moms', fra_kto = '$fra_kto', til_kto = '$til_kto', genvej='$genvej', lukket = '$lukket', ";
			$qtxt .= "map_to = '$map_to' where id = '$id'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	}
	genberegn($regnaar);
}
if ($id > 0) {
	$tmpA = explode("\n",file_get_contents("../importfiler/kontoplan.txt"));
	$d = $s = 0;
	for ($i = 0;$i < count($tmpA);$i++) {
		list($n,$b,$t) = explode(chr(9),$tmpA[$i],4);
		if ($t == 'D') {
			$stdDkt[$d]  = $n;
			$stdDtxt[$d] = $b;
			$d++;		
		} elseif ($t == 'S') {
			$stdSkt[$s]  = $n;
			$stdStxt[$s] = $b;
			$s++;		
		}
	}
	
	$q = db_select("select * from kontoplan where id = '$id'", __FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		$id = $r['id'];
		$kontonr = (int)$r['kontonr'];
		$map_to = (int)$r['map_to'];
		$beskrivelse = htmlentities(stripslashes($r['beskrivelse']), ENT_COMPAT, $charset);
		$kontotype = $r['kontotype'];
		#		$katagori=$r['katagori'];
		$moms = $r['moms'];
		$fra_kto = $r['fra_kto'];
		#		$til_kto=$r['til_kto'];
		$genvej = $r['genvej'];
		$lukket = $r['lukket'];
		$saldo = $r['saldo'] * 1;
		$valutakode = $r['valuta'];
		$valutakurs = $r['valutakurs'];
		if (!$valutakurs)
			$valutakurs = 100;
		if ($valutakode) {
			#cho "select box1 from grupper where art='VK' and kodenr = '$valutakode'<br>";
			$r = db_fetch_array(db_select("select box1 from grupper where art='VK' and kodenr = '$valutakode'", __FILE__ . " linje " . __LINE__));
			$valuta = $r['box1'];
		} else
			$valuta = 'DKK';
		$qtxt = "select id from kontoplan where kontonr < '$kontonr' and regnskabsaar = '$regnaar' order by kontonr desc limit 1";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$previousPage = $r['id'] * 1;
		$qtxt = "select id from kontoplan where kontonr > '$kontonr' and regnskabsaar = '$regnaar' order by kontonr limit 1";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$nextPage = $r['id'] * 1;
	}
	$r = db_fetch_array(db_select("select id from kontoplan where kontotype = 'R' and regnskabsaar = '$regnaar'", __FILE__ . " linje " . __LINE__));
	$resultatkonto = $r['id'] * 1;

	if (!$kontonr) {
		print "<META HTTP-EQUIV=REFRESH CONTENT=\"0; URL=kontoplan.php\">";
		exit;
	}
	$qtxt = "select * from grupper where kodenr='$regnaar' and art='RA'";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$startmaaned = $r['box1'] * 1;
	$startaar = $r['box2'] * 1;
	$slutmaaned = $r['box3'] * 1;
	$slutaar = $r['box4'] * 1;
	$slutdato = 31;


	while (!checkdate($slutmaaned, $slutdato, $slutaar)) {
		$slutdato = $slutdato - 1;
		if ($slutdato < 28)
			break 1;
	}
	$regnstart = $startaar . "-" . $startmaaned . "-" . '01';
	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

	$qtxt = "select id from transaktioner where transdate>'$regnstart' and transdate<'$regnslut' ";
	$qtxt .= "and kontonr='$kontonr'";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	($r = db_fetch_array($q)) ? $slet = 0 : $slet = 1;

}
if ($kontotype == 'H')
	$kontotype = 'Overskrift';
elseif ($kontotype == 'D')
	$kontotype = 'Drift';
elseif ($kontotype == 'S')
	$kontotype = 'Status';
elseif ($kontotype == 'Z')
	$kontotype = 'Sum';
elseif ($kontotype == 'R')
	$kontotype = 'Resultat';
elseif ($kontotype == 'X')
	$kontotype = 'Sideskift';

if ($menu == 'T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">";
	print "<div class=\"headerbtnLft headLink\"><a href=kontoplan.php accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst(30, $sprog_id) . "</a></div>";
	print "<div class=\"headerTxt\">$title</div>";
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
	print "</div>";
	print "<div class='content-noside'>";
	print "<center><table class='dataTableSmall' border='0' cellspacing='1' align='center' style='width:700px;'>";
} elseif ($menu == 'S') {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td align=\"center\"  height=1% valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";

	print "<tr><td width=\"10%\"><a href=kontoplan.php accesskey=L>
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst(30, $sprog_id)."</button></a></td>";

	print "<td width=\"80%\" align='center' style='$topStyle'> ". findtekst(443, $sprog_id)."</td>";

	print "<td width=\"10%\"><a href=kontokort.php accesskey=N>
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst(39, $sprog_id)."</button></a><br></td></tr>";

	if ($previousPage)
		print "<tr><td colspan=2><a href='kontokort.php?id=$previousPage'><img src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
	else
		print "<tr><td colspan=2></td>";
	if ($nextPage)
		print "<td align=\"right\"><a href='kontokort.php?id=$nextPage'><img src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
	else
		print "<td></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<td align = center valign = center height=99%>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
} else {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td align=\"center\"  height=1% valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<tr><td width=\"10%\" $top_bund ><a href=kontoplan.php accesskey=L>" . findtekst(30, $sprog_id) . "</a></td>"; #20210707
	print "<td width=\"80%\"  $top_bund >  " . findtekst(443, $sprog_id) . "</td>";
	print "<td width=\"10%\"  $top_bund > <a href=kontokort.php accesskey=N>" . findtekst(39, $sprog_id) . "</a><br></td></tr>";
	if ($previousPage)
		print "<tr><td colspan=2><a href='kontokort.php?id=$previousPage'><img src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
	else
		print "<tr><td colspan=2></td>";
	if ($nextPage)
		print "<td align=\"right\"><a href='kontokort.php?id=$nextPage'><img src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
	else
		print "<td></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<td align = center valign = center height=99%>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
}



print "<form name=kontokort action=kontokort.php method=post>";
print "<input type=hidden name=id value='$id'>";
print "<input type=hidden name=valuta value='$valuta'>";
print "<input type=hidden name=saldo value='$saldo'>";
print "<input type=\"hidden\" name=\"regnaar\" value=\"$regnaar_return\">";
print "<input type=\"hidden\" name=\"maaned_fra\" value=\"$maaned_fra\">";
print "<input type=\"hidden\" name=\"maaned_til\" value=\"$maaned_til\">";
print "<input type=\"hidden\" name=\"aar_fra\" value=\"$aar_fra\">";
print "<input type=\"hidden\" name=\"aar_til\" value=\"$aar_til\">";
print "<input type=\"hidden\" name=\"dato_fra\" value=\"$dato_fra\">";
print "<input type=\"hidden\" name=\"dato_til\" value=\"$dato_til\">";
print "<input type=\"hidden\" name=\"konto_fra\" value=\"$konto_fra\">";
print "<input type=\"hidden\" name=\"konto_til\" value=\"$konto_til\">";
print "<input type=\"hidden\" name=\"rapportart\" value=\"$rapportart\">";
if ($id && $saldo) {
	print "<tr><td>" . findtekst(804, $sprog_id) . "</td><td><br></td><td colspan=2> $kontonr</td></tr>\n";
	print "<input type=hidden name=kontonr value=\"$kontonr\">";
} else {
	print "<tr><td>" . findtekst(804, $sprog_id) . ":</td><td><br></td>";
	print "<td colspan=2><input type='text' style = 'width:70px' name=kontonr value=\"$kontonr\"></td></tr>\n";
}
if ($kontotype == 'Drift') {
	if ($map_to && !in_array($map_to,$stdDkt)) $map_to = 0; 
	print "<tr><td>Map til:</td><td><br></td>";
	print "<td colspan=2><SELECT name='map_to' style = 'width:70px'>";
	print "<OPTION value = '$map_to'>$map_to</OPTION>";
	for ($i=0;$i<count($stdDkt);$i++) {
		print "<OPTION value = '$stdDkt[$i]'>$stdDkt[$i] : $stdDtxt[$i]</OPTION>";
	}
	print "</SELECT>";
} elseif ($kontotype == 'Status') {
	if ($map_to && !in_array($map_to,$stdSkt)) $map_to = 0; 
	print "<tr><td>Map til:</td><td><br></td>";
	print "<td colspan=2><SELECT name='map_to' style = 'width:70px'>";
	print "<OPTION value = '$map_to'>$map_to</OPTION>";
	for ($i=0;$i<count($stdSkt);$i++) {
		print "<OPTION value = '$stdSkt[$i]'>$stdSkt[$i] : $stdStxt[$i]</OPTION>";
	}
}	
	
print "<tr><td>" . findtekst(805, $sprog_id) . ":</td><td><br></td><td colspan=2><input type=text size=25 name=beskrivelse value=\"$beskrivelse\"></td></tr>\n";
print "<tr><td>" . findtekst(1192, $sprog_id) . ":</td><td><br></td>";
print "<td colspan=2><SELECT NAME = 'kontotype' style = 'width:70px'>";
#print "<td>sa $saldo sl $slet<br></td>";
if ($kontotype)
	print "<OPTION>$kontotype</OPTION>\n";
if ($saldo) {
	if ($kontotype != 'Drift')
		print "<OPTION>" . findtekst(1194, $sprog_id) . "</OPTION>\n";
	if ($kontotype != 'Status')
		print "<OPTION>Status</OPTION>\n";
	if (!$resultatkonto && $kontotype != 'Resultat')
		print "<OPTION>" . findtekst(518, $sprog_id) . "</OPTION>\n";
} else {
	if ($kontotype != 'Overskrift')
		print "<OPTION>" . findtekst(1195, $sprog_id) . "</OPTION>\n";
	if ($kontotype != 'Drift')
		print "<OPTION>" . findtekst(1194, $sprog_id) . "</OPTION>\n";
	if ($kontotype != 'Status') print "<OPTION>Status</OPTION>\n";
	$qtxt = "SELECT id from kontoplan where regnskabsaar = '$regnaar' and kontotype='R'";
	$r = db_fetch_array($q = db_SELECT($qtxt, __FILE__ . " linje " . __LINE__));
	if (!$r['id'] && $kontotype != 'Resultat')
		print "<OPTION>" . findtekst(518, $sprog_id) . "</OPTION>\n";
	if ($kontotype != 'Sum') print "<OPTION>Sum</OPTION>\n";
	$qtxt =	"select id from kontoplan where regnskabsaar = '$regnaar' and kontotype='X'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (!$r['id'] && $kontotype != 'Sideskift')
		print "<OPTION>" . findtekst(1193, $sprog_id) . "</OPTION>\n";
}
print "</SELECT></td></tr>\n";

if ($kontotype == 'Drift' || $kontotype == 'Status') {
	print "<tr><td>" . findtekst(1095, $sprog_id) . ":</td><td><br></td>";
	print "<td colspan=2><SELECT NAME = 'moms' style = 'width:70px'>";
	print "<OPTION>$moms</OPTION>\n";
	$qtxt = "select kode, kodenr from grupper where art = 'KM' or art = 'SM' or art = 'EM' or art = 'YM'";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	if ($moms)
		print "<OPTION></OPTION>\n";
	while ($r = db_fetch_array($q)) {
		$tmp = $r['kode'] . $r['kodenr'];
		if ($moms != $tmp)
			print "<OPTION>$tmp</OPTION>\n";
	}
	print "</SELECT></td></tr>\n";
}

if ($kontotype == 'Sum') {
	print "<tr><td>" . findtekst(900, $sprog_id) . ":</td><td><br></td><td><input type=text size=6 name=fra_kto value='$fra_kto'></td></tr>\n";
} elseif ($kontotype == 'Resultat') {
	print "<tr><td>" . findtekst(518, $sprog_id) . "" . findtekst(592, $sprog_id) . "_</td><td><br></td><td><input type=text size=6 name=fra_kto value='$fra_kto'></td></tr>\n";
}
if (($kontotype == 'Drift') || ($kontotype == 'Status')) {
	$x = 0;
	$alfabet = array("A", "B", "C", "E", "F", "G", "H", "I", "J", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "X", "Y", "Z");
	$shortCut = array();
	$q = db_select("select genvej from kontoplan where regnskabsaar='$regnaar' order by genvej", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$shortCut[$x] = $r['genvej'];
	}
	print "<tr><td>" . findtekst(1191, $sprog_id) . ":</td><td><br></td>";
	print "<td><SELECT NAME='genvej' style = 'width:70px'>";
	print "<OPTION>$genvej</OPTION>\n";
	if ($genvej)
		print "<OPTION></OPTION>\n";
	for ($x = 0; $x < count($alfabet); $x++) {
		if (!in_array($alfabet[$x], $shortCut))
			print "<OPTION>$alfabet[$x]</OPTION>\n";
	}
	print "</SELECT></td>";
	if ($kontotype == 'Status') {
		$x = 1;
		$valuta_kode[0] = 'DKK';
		$valuta_navn[0] = 'Danske kroner';
		$q = db_select("select beskrivelse,box1 from grupper where art='VK' order by box1", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$valuta_kode[$x] = $r['box1'];
			$valuta_navn[$x] = $r['beskrivelse'];
			$x++;
		}
		print "</tr><tr><td>" . findtekst(2101, $sprog_id) . ":</td><td></td>";
		print "<td><SELECT NAME = 'ny_valuta' style = 'width:70px'>";
		for ($x = 0; $x < count($valuta_kode); $x++) {
			if ($valuta == $valuta_kode[$x])
				print "<OPTION value='$valuta_kode[$x]'>$valuta_kode[$x] : $valuta_navn[$x]</OPTION>";
		}
		for ($x = 0; $x < count($valuta_kode); $x++) {
			if ($valuta != $valuta_kode[$x])
				print "<OPTION value='$valuta_kode[$x]'>$valuta_kode[$x] : $valuta_navn[$x]</OPTION>";
		}
		print "</select></td></tr>";
	} else
		print "<input type=\"hidden\" name=\"ny_valuta\" value='DKK'>";
}
if ($kontotype == 'Drift' || $kontotype == 'Status') {
	print "<tr><td colspan=\"2\">" . findtekst(1073, $sprog_id) . "</td>";
	if ($valutakurs != 0)
		print "<td>$valuta: " . dkdecimal($saldo * 100 / $valutakurs) . "</td>";
	print "</tr>";
}
if ($lukket == 'on')
	$lukket = "checked";
print "<tr><td colspan=\"2\">" . findtekst(387, $sprog_id) . ":</td>";
print "<td><input type=checkbox name=lukket $lukket></td></tr>\n";
print "<tr><td><br></td></tr>\n";
print "<tr><td><br></td></tr>\n";
print "<tr><td colspan=4 align=center>";
print "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
print "<tr>";
print "<td align=center><input class='button green medium' type=submit accesskey=\"g\" value=\"" . findtekst(471, $sprog_id) . "\" name=\"gem\"></td>";
if ($slet == 1)
	print "<td align = center><input class='button red medium' type=submit accesskey=\"s\" value=\"" . findtekst(1099, $sprog_id) . "\" name=\"slet\" onclick=\"return confirm('Vil du slette konto $kontonr?')\" ></td>";
print "</tr>";
print "<tr><td><br></td></tr>\n";
if ($regnaar_return > "")
	print "<tr><td align=center><a href=\"../finans/$rapportart.php?regnaar=$regnaar_return&maaned_fra=$maaned_fra&maaned_til=$maaned_til&aar_fra=$aar_fra&aar_til=$aar_til&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&rapportart=$rapportart\"><input type=\"button\" accesskey=\"r\" value=\"Retur til Regnskab Basis\"></a></td>";
print "</tr>\n</tbody></table>";

print "</tbody>";
print "</table>";
print "</td></tr>";
print "<tr><td align = 'center' valign = 'bottom'>";
if ($menu == 'T' || $menu == 'S') {

} else {
	print "<table width='100%' align='center' border='1' cellspacing='0' cellpadding='0'><tbody>";
	print "<td width='100%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'><br></td>";
	print "</tbody></table>";
}
print "</td></tr>";
print "</tbody></table>";

if ($menu == 'T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

?>
