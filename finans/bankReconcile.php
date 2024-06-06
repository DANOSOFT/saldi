<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/bankReconcile.php --- patch 4.1.0 --- 2024.04.03 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2024 saldi.dk aps
// ----------------------------------------------------------------------

// 20240213	PHR Copied from bankimport.php
// 20240403 PHR Added instruction text

ini_set("auto_detect_line_endings", true);

@session_start();
$s_id = session_id();
$css = "../css/standard.css";
$bankName = NULL;

$title = "BankAfstemning";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");

global $menu;
global $fokus;

$up = $vend = NULL;
$show = null;
$kladde_id = null;
$bilag = null;
$feltantal = 0;

if ($menu == 'T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">";
	print "<div class=\"headerbtnLft headLink\"><a href=bankReconcile.php?kladde_id=$kladde_id accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst(30, $sprog_id) . "</a></div>";
	print "<div class=\"headerTxt\">$title</div>";
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
	print "</div>";
	print "<div class='content-noside'><center>";
} elseif ($menu == 'S') {
	include("../includes/sidemenu.php");
} else {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><a href=rapport.php accesskey=L>" . findtekst(30, $sprog_id) . "</a></td>";
	print "<td width=\"80%\" $top_bund>Bankafstemning</td>";
	print "<td width=\"10%\" $top_bund ><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
}
if ($_POST) {

	$reconcile = if_isset($_POST['reconcile'], NULL);
	$show = if_isset($_POST['show'], NULL);
	$filnavn = if_isset($_POST['filnavn'], NULL);
	$splitter = if_isset($_POST['splitter'], NULL);
	$feltnavn = if_isset($_POST['feltnavn'], NULL);
	$feltantal = if_isset($_POST['feltantal'], 0);
	$kontonr = if_isset($_POST['kontonr'], 0);
	$gebyrkonto = (int) if_isset($_POST['gebyrkonto'], NULL);
	$valuta = if_isset($_POST['valuta'], NULL);
	$valuta_kode = if_isset($_POST['valuta_kode'], NULL);
	$bilag = if_isset($_POST['bilag'], NULL);
	$afd = if_isset($_POST['afd'], NULL);
	$vend = if_isset($_POST['vend'], NULL);
	if ($vend)
		$vend = 'checked';
} elseif ($_GET) {
	$reconcile = if_isset($_GET['reconcile'], NULL);
	$filnavn = if_isset($_GET['filnavn'], NULL);
	$splitter = if_isset($_GET['splitter'], NULL);
	$feltnavn = explode("|", if_isset($_GET['feltnavne'], NULL));
	$kontonr = if_isset($_GET['kontonr'], 0);
	$vend = if_isset($_GET['vend'], NULL);
	if ($vend)
		$vend = 'checked';
} else {
/*
	print "<tr><td align = 'center'><table>";
	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td width=100% align=center><b>" . findtekst(1074, $sprog_id) . " " . lcfirst(findtekst(1076, $sprog_id)) . "</b></td></tr>"; #20210629
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<form enctype=\"multipart/form-data\" action=\"bankReconcile.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
	print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
	print "<tr><td width=100% align=center> " . findtekst(586, $sprog_id) . " " . findtekst(1077, $sprog_id) . ": <input class=\"inputbox\" name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"" . findtekst(1078, $sprog_id) . "\" /></td></tr>";
	print "<tr><td></form></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td>$hrlinje</td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "</table>";
*/
	exit;
}

if ($kontonr) {
	$tmp = $kontonr * 1;
	$qtxt = "SELECT id FROM kontoplan WHERE kontonr=$tmp";
	if (!$row = db_fetch_array(db_SELECT($qtxt, __FILE__ . " linje " . __LINE__))) {
		alert("Kontonummer $kontonr findes ikke i kontoplanen");
		$show = 'Vis';
		$reconcile = NULL;
	}
}
if (isset($_FILES['uploadedfile']) && basename($_FILES['uploadedfile']['name'])) {
	$filnavn = "../temp/" . $db . "_" . str_replace(" ", "_", $brugernavn) . ".csv";
	if (move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
		$qtxt = "SELECT * FROM grupper WHERE art = 'KASKL' AND kode='3' AND kodenr='$bruger_id'";
		if ($r = db_fetch_array(db_SELECT($qtxt, __FILE__ . " linje " . __LINE__))) {
			$kontonr = if_isset($r['box1']);
			$feltantal = if_isset($r['box2']);
			$feltnavn[0] = if_isset($r['box3']);
			$feltnavn[1] = if_isset($r['box4']);
			$feltnavn[2] = if_isset($r['box5']);
			$feltnavn[3] = if_isset($r['box6']);
			$feltnavn[4] = if_isset($r['box7']);
			$feltnavn[5] = if_isset($r['box8']);
			$feltnavn[6] = if_isset($r['box9']);
			$feltnavn[7] = if_isset($r['box10']);
			if ($feltantal > 8) {
				for ($x = 9; $x <= $feltantal; $x++) {
					$feltnavn[$x - 1] = null;
				}
			}
			// $gebyrkonto = if_isset($r['box11']) * 1;
		} else {
			$qtxt = "insert into grupper (beskrivelse,art,kode,kodenr) values ('Bankimport','KASKL','3','$bruger_id')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		if (!$feltantal)
			$feltantal = 1;
		vis_data($filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $vend);
	} else {
		echo findtekst(1370, $sprog_id);
	}
} elseif ($show) {
	vis_data($filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $vend);
} elseif ($reconcile) {

	if ($filnavn && $splitter && $kontonr) {
		reconcile($filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $vend);
	} else
		vis_data($filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $vend);
} else {
	upload($kladde_id, $bilag);
}

print "</tbody></table>";

if ($menu == 'T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

################################################################################################################
function upload($kladde_id, $bilag)
{
	global $bgcolor2;$charset;
	global $sprog_id;
	global $menu;

	print "<tr><td height='20%' align='center' valign = 'top'></td></tr>";
	print "<tr><td align='center' valign = 'top'>
		<table width=\"50%\" style='border:1px solid $bgcolor2;border-radius:5px;'><tbody>";
	print "<tr><td><center>Her kan du afstemme det der er bogført i Saldi med et kontoudtog fra banken<br>
		Systemet finder selv datoerne fra i kontoudtoget og de modsvarende datoer i Saldi,<br> 
		så du nemt kan finde eventuelle differencer.<br><br><br></td></tr>";
	print "<form enctype=\"multipart/form-data\" action=\"bankReconcile.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
	print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
	print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
	print "<tr><td width=100% align=center> " . findtekst(1364, $sprog_id) . ": 
	<input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input  style = 'width:150px;' type=\"submit\" value=\"" . findtekst(1078, $sprog_id) . "\" /></td></tr>";
	print "<tr><td></form></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}

function vis_data($filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $vend)
{

	global $bruger_id, $charset, $sprog_id;
	$bankName = '';
	$komma = $punktum = $semikolon = $tabulator = $x = 0;
	$valuta_kode = null;
	$valuta = array();
	$q = db_select("SELECT kodenr,box1 FROM grupper WHERE art='VK' order by box1", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (trim($r['box1'])) {
			$x++;
			$valutakode[$x] = $r['kodenr'];
			$valuta[$x] = $r['box1'];
		}
	}

	// $x = 0;
	// $afd_nr = array();
	// $q = db_select("SELECT kodenr,box1 FROM grupper WHERE art='AFD' order by kodenr", __FILE__ . " linje " . __LINE__);
	// while ($r = db_fetch_array($q)) {
	// 	$afd_nr[$x] = $r['kodenr'];
	// 	$afd_navn[$x] = $r['box1'];
	// 	$x++;
	// }

	$fp = fopen("$filnavn", "r");
	$tegnsaet = "iso";
	if ($fp) {
		$z = 0;
		while ($linje = fgets($fp)) {
			#	exit;
			if ($z == 0 && substr($linje, 0, 61) == "Planlagt;Type;Fil;Fra konto;Kontonavn;Til konto;Mottakernavn;") {
				$bankName = 'Sparebanken Vest';
			}
			if ($z <= 10) {
				$tmp = $linje;
				while ($tmp = substr(strstr($tmp, ";"), 1))
					$semikolon++;
				$tmp = $linje;
				while ($tmp = substr(strstr($tmp, ","), 1))
					$komma++;
				$tmp = $linje;
				while ($tmp = substr(strstr($tmp, chr(9)), 1))
					$tabulator++;
				$tmp = '';
			}
			$z++;
			if ($tegnsaet == 'iso') { #20170914
				if (strpos($linje, 'ø') || strpos($linje, 'Ø'))
					$tegnsaet = 'UTF-8';
			}
		}
		fclose($fp);
		if (($komma > $semikolon) && ($komma > $tabulator)) {
			$tmp = 'Komma';
			$feltantal = $komma;
		} elseif (($semikolon > $tabulator) && ($semikolon > $komma)) {
			$tmp = 'Semikolon';
			$feltantal = $semikolon;
		} elseif (($tabulator > $semikolon) && ($tabulator > $komma)) {
			$tmp = 'Tabulator';
			$feltantal = $tabulator;
		}
		if (!$splitter) {
			$splitter = $tmp;
		}
		if ($splitter == 'Komma')
			$feltantal = $komma;
		elseif ($splitter == 'Semikolon')
			$feltantal = $semikolon;
		elseif ($splitter == 'Tabulator')
			$feltantal = $tabulator;
		$cols = $feltantal + 1;
	}


	$fp = fopen("$filnavn", "r");
	if ($fp) {
		if ($splitter == 'Komma')
			$splittegn = ",";
		elseif ($splitter == 'Semikolon')
			$splittegn = ";";
		elseif ($splitter == 'Tabulator')
			$splittegn = chr(9);

		$y = 0;
		$feltantal = 0;
		#	for ($y=1; $y<20; $y++) {
		while ($linje = fgets($fp)) {
			if ($linje) {
				$y++;
				$ny_linje[$y] = '';
				if ($tegnsaet == 'UTF-8')
					$linje = utf8_decode($linje);
				$linje = trim($linje);
				$linje = trim($linje, "?");
				if ($charset == 'UTF-8')
					$linje = utf8_encode($linje);
				$anftegn = 0;
				$felt = array();
				$z = 0;
				for ($x = 0; $x < strlen($linje); $x++) {
					if ($x == 0 && substr($linje, $x, 1) == '"') {
						$z++;
						$anftegn = 1;
						$felt[$z] = '';
					} elseif ($x == 0) {
						$z++;
						$felt[$z] = substr($linje, $x, 1);
					} elseif (substr($linje, $x, 1) == '"' && substr($linje, $x - 1, 1) == $splittegn && !$anftegn) {
						$z++;
						$anftegn = 1;
						$felt[$z] = '';
					} elseif (substr($linje, $x, 1) == '"' && (substr($linje, $x + 1, 1) == $splittegn || $x == strlen($linje) - 1)) {
						$anftegn = 0;
						if (substr($linje, $x + 2, 1) == '"')
							$x++;
						#					if ($x==strlen($linje)) $z--;
					} elseif (!$anftegn && substr($linje, $x, 1) == $splittegn) {
						$z++;
						$felt[$z] = '';
						if (substr($linje, $x + 1, 1) == '"')
							$x++;
					} else {
						$felt[$z] = $felt[$z] . substr($linje, $x, 1);
					}
				}
				if ($z > $feltantal)
					$feltantal = $z - 1;
				if (!isset($felt[$x]))
					$felt[$x] = NULL;
				for ($x = 1; $x <= $z; $x++) {
					$ny_linje[$y] = $ny_linje[$y] . $felt[$x] . chr(9);
				}
				$x++;
				if (!isset($felt[$x]))
					$felt[$x] = NULL;
				$ny_linje[$y] = $ny_linje[$y] . $felt[$x] . "\n";
			}
		}
		fclose($fp);
	}
	$linjeantal = $y;
	#$cols=$feltantal;
	$fp = fopen($filnavn . "2", "w");
	if ($vend) {
		for ($y = $linjeantal; $y >= 1; $y--)
			fwrite($fp, $ny_linje[$y]);
	} else {
		for ($y = 1; $y <= $linjeantal; $y++)
			fwrite($fp, $ny_linje[$y]);
	}
	fclose($fp);
	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"bankReconcile.php\" method=\"POST\">";
	#print "<tr><td colspan=6 width=100% align=center> $filnavn</td></tr>";
	print "<tr><td colspan=$cols align=center>";
	print "<span title='" . findtekst(1392, $sprog_id) . "'>\n";
	print "Vend <input type=\"checkbox\" name=\"vend\" $vend>";
	print "</span>";
	print "<span title='" . findtekst(1389, $sprog_id) . "'>" . findtekst(1377, $sprog_id) . "<select name=splitter>\n";
	if ($splitter) {
		print "<option>$splitter</option>\n";
	}
	if ($splitter != 'Semikolon')
		print "<option>" . findtekst(1378, $sprog_id) . "</option>\n";
	if ($splitter != 'Komma')
		print "<option>" . findtekst(1379, $sprog_id) . "</option>\n";
	if ($splitter != 'Tabulator')
		print "<option>Tabulator</option>\n";
	print "</select></span>";
	// if (count($afd_nr)) {
	// 	print "<span title='" . findtekst(1393, $sprog_id) . "'> " . findtekst(658, $sprog_id) . "<select name='afd'>\n";
	// 	if (!$afd)
	// 		print "<option value='0'></option>\n";
	// 	for ($x = 0; $x < count($afd_nr); $x++) {
	// 		if ($afd_nr[$x] == $afd)
	// 			print "<option value='$afd_nr[$x]'>$afd_nr[$x]</option>\n";
	// 	}
	// 	for ($x = 0; $x < count($afd_nr); $x++) {
	// 		if ($afd_nr[$x] != $afd)
	// 			print "<option value='$afd_nr[$x]'>$afd_nr[$x]</option>\n";
	// 	}
	// 	if ($afd)
	// 		print "<option value='0'></option>\n";
	// 	print "</select>";
	// }
	if ($v_ant = count($valuta)) {
		$valuta_kode *= 1;
		print "<span title='" . findtekst(1394, $sprog_id) . "'> " . findtekst(1069, $sprog_id) . "<select name='valuta_kode'>\n";
		for ($x = 1; $x <= $v_ant; $x++) {
			if ($valutakode[$x] == $valuta_kode)
				print "<option value='$valutakode[$x]'>$valuta[$x]</option>\n";
		}
		print "<option value='0'>DKK</option>\n";
		for ($x = 1; $x <= $v_ant; $x++) {
			if ($valutakode[$x] != $valuta_kode)
				print "<option value='$valutakode[$x]'>$valuta[$x]</option>\n";
		}
		print "</select></span>";
	}
	print "&nbsp;<span title='" . findtekst(1395, $sprog_id) . "'>" . findtekst(1396, $sprog_id) . "<input type=text size=8 name=kontonr value=$kontonr></span>&nbsp;";
	#print "&nbsp;<span title='".findtekst(1398, $sprog_id)."'>".findtekst(1397, $sprog_id)."<input type=text size=8 name=gebyrkonto value=$gebyrkonto></span>&nbsp;";
	print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
	print "<input type=\"hidden\" name=\"feltantal\" value=$feltantal>";
	// print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
	print "&nbsp; <input style = 'width:75px;' type=\"submit\" name=\"show\" value=\"" . findtekst(1133, $sprog_id) . "\" />";
	$alertj = findtekst(1399, $sprog_id);
	$alertk = findtekst(1400, $sprog_id);
	$alertl = findtekst(1401, $sprog_id);
	$alertm = findtekst(1402, $sprog_id);
	$alertn = findtekst(1403, $sprog_id);
	$reconcile = findtekst(1356, $sprog_id);
	if (!in_array("dato", $feltnavn))
		alert("$alertj");
	elseif (!in_array("beskrivelse", $feltnavn))
		alert("$alertk");
	elseif (!in_array("belob", $feltnavn))
		alert("$alertl");
	elseif (!$splitter)
		alert("$alertm");
	elseif (!$kontonr)
		alert("$alertn");
	elseif ($filnavn && $splitter && $kontonr)
		print "&nbsp; <input style = 'width:75px;' type=\"submit\" name=\"reconcile\" value=\"Afstem\" /></td></tr>";
	print "<tr><td colspan=$cols><hr></td></tr>\n";
	#if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
#elseif ($splitter=='Komma') {$splitter=',';}
#elseif ($splitter=='Tabulator') {$splitter=chr(9);}
	$splitter = chr(9);
	// print_r($feltnavn);
	// echo $feltantal;
	// print "<tr><td><span title='" . findtekst(1404, $sprog_id) . "'><input type=text size=4 name=bilag value=$bilag></span></td>";
	$belob = $beskr = $dato = $kundenr = 0;
	for ($y = 0; $y < $feltantal; $y++) {
		if ($feltnavn[$y] == 'dato' && $dato == 1) {
			$aalert = findtekst(1405, $sprog_id);
			alert("$aalert");
			$feltnavn[$y] = '';
		}
		if (($feltnavn[$y] == 'beskrivelse') && ($beskr == 1)) {
			$abalert = findtekst(1406, $sprog_id);
			alert("$abalert");
			$feltnavn[$y] = '';
		}
		if (($feltnavn[$y] == 'belob') && ($belob == 1)) {
			$acalert = findtekst(1407, $sprog_id);
			alert("$acalert");
			$feltnavn[$y] = '';
		}
		if ($feltnavn[$y] == 'kundenr' && $kundenr == 1) {
			$adalert = findtekst(2053, $sprog_id);
			alert("$adalert");
			$feltnavn[$y] = '';
		}
		if ($feltnavn[$y] == 'belob' || $feltnavn[$y] == 'saldo')
			print "<td align=right><select name=feltnavn[$y]>\n";
		elseif ($feltnavn[$y])
			print "<td><select name=feltnavn[$y]>\n";
		else
			print "<td align=center><select name=feltnavn[$y]>\n";
		print "<option>$feltnavn[$y]</option>\n";
		if ($feltnavn[$y])
			print "<option></option>\n";
		if ($feltnavn[$y] != 'dato')
			print "<option value=\"dato\">" . findtekst(635, $sprog_id) . "</option>\n";
		else
			$dato = 1;
		if ($feltnavn[$y] != 'beskrivelse')
			print "<option value=\"beskrivelse\">" . findtekst(914, $sprog_id) . "</option>\n";
		else
			$beskr = 1;
		if ($feltnavn[$y] != 'belob')
			print "<option value=\"belob\">" . findtekst(934, $sprog_id) . "</option>\n";
		else
			$belob = 1;
		if ($feltnavn[$y] != 'saldo')
			print "<option value=\"saldo\">Saldo</option>\n";
		else
			$saldo = 1;
		if ($feltnavn[$y] != 'kundenr')
			print "<option value=\"kundenr\">" . findtekst(357, $sprog_id) . "</option>\n";
		else
			$kundenr = 1;
		if ($feltnavn[$y] != 'recieverAccount')
			print "<option value=\"recieverAccount\">recieverAccount</option>\n";
		else
			$recieverAccount = 1;
		print "</select>";
	}
	print "</form>";
	$fp = fopen($filnavn . "2", "r");
	if ($fp) {
		$x = 0;
		while ($linje = fgets($fp)) {
			#	while (!feof($fp)) {
			$skriv_linje = 0;
			if ($linje = trim($linje)) {
				$x++;
				$skriv_linje = 1;
				$felt = array();
				$felt = explode($splitter, $linje);
				for ($y = 0; $y <= $feltantal; $y++) {
					if (isset($felt[$y])) {
						$felt[$y] = trim($felt[$y]);
						$felt[$y] = trim($felt[$y], '"');
						if ((substr($felt[$y], 0, 1) == '"') && (substr($felt[$y], -1) == '"'))
							$felt[$y] = substr($felt[$y], 1, strlen($felt[$y]) - 2);
						if (isset($feltnavn[$y]) == 'dato') { # 20140203
							if (is_numeric($felt[$y]) && strlen($felt[$y]) == '8') { #20210916
								$thisYear = date('Y');
								if (
									substr($felt[$y], 0, 4) >= $thisYear - 2 &&
									substr($felt[$y], 0, 4) <= $thisYear + 2 &&
									substr($felt[$y], 4, 2) <= 12
								) {
									$year = substr($felt[$y], 0, 4);
									$month = substr($felt[$y], 4, 2);
									$day = substr($felt[$y], 6, 2);
									$felt[$y] = $day . "-" . $month . "-" . $year;
								}
							}
							$felt[$y] = str_replace("-jan-", "-01-", $felt[$y]);
							$felt[$y] = str_replace("-feb-", "-02-", $felt[$y]);
							$felt[$y] = str_replace("-mar-", "-03-", $felt[$y]);
							$felt[$y] = str_replace("-apr-", "-04-", $felt[$y]);
							$felt[$y] = str_replace("-maj-", "-05-", $felt[$y]);
							$felt[$y] = str_replace("-jun-", "-06-", $felt[$y]);
							$felt[$y] = str_replace("-jul-", "-07-", $felt[$y]);
							$felt[$y] = str_replace("-aug-", "-08-", $felt[$y]);
							$felt[$y] = str_replace("-sep-", "-09-", $felt[$y]);
							$felt[$y] = str_replace("-okt-", "-10-", $felt[$y]);
							$felt[$y] = str_replace("-nov-", "-11-", $felt[$y]);
							$felt[$y] = str_replace("-dec-", "-12-", $felt[$y]);
							$felt[$y] = str_replace(".", "-", $felt[$y]);
						}
						if ($feltnavn[$y] == 'belob') {
							if ($bankName == 'Sparebanken Vest' && $felt[1] == 'Betaling')
								$felt[$y] = '-' . $felt[$y];
							$felt[$y] = str_replace(chr(194) . chr(160), "", $felt[$y]); // 20220120 Weired dot?
							$felt[$y] = str_replace(" ", "", $felt[$y]);
							if (nummertjek($felt[$y]) == 'US') {
								if ($felt[$y] == 0)
									$skriv_linje = 0;
								else
									$felt[$y] = dkdecimal($felt[$y]);
							} elseif (nummertjek($felt[$y]) == 'DK') {
								if (usdecimal($felt[$y]) == 0)
									$skriv_linje = 0;
							} else {
								$skriv_linje = 0;
							}
						}
						if ($feltnavn[$y] == 'beskrivelse') { // 20220120
							$felt[$y] = str_replace("%c3%a6", "æ", $felt[$y]);
							$felt[$y] = str_replace("%c3%b8", "ø", $felt[$y]);
							$felt[$y] = str_replace("%c3%a5", "å", $felt[$y]);
							$felt[$y] = str_replace("%c3%86", "Æ", $felt[$y]);
							$felt[$y] = str_replace("%c3%98", "Ø", $felt[$y]);
							$felt[$y] = str_replace("%c3%85", "Å", $felt[$y]);
							$felt[$y] = str_replace("%C3%A6", "æ", $felt[$y]);
							$felt[$y] = str_replace("%C3%B8", "ø", $felt[$y]);
							$felt[$y] = str_replace("%C3%A5", "å", $felt[$y]);
							$felt[$y] = str_replace("%C3%86", "Æ", $felt[$y]);
							$felt[$y] = str_replace("%C3%98", "Ø", $felt[$y]);
							$felt[$y] = str_replace("%C3%85", "Å", $felt[$y]);
						}
					}
				}
				if ($skriv_linje == 1) {
					print "<tr>"; #<td>$bilag</td>";
					for ($y = 0; $y < $feltantal; $y++) {
						if (isset($felt[$y])) {
							if ($feltnavn[$y] == 'belob' || $feltnavn[$y] == 'saldo') {
								print "<td align=right>$felt[$y]&nbsp;</td>";
							} elseif ($feltnavn[$y]) {
								print "<td>$felt[$y]&nbsp;</td>";
							} else
								print "<td align=center><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
						}
					}
					print "</tr>";
					// $bilag++;
				} else {
					print "<tr><td><span style=\"color: rgb(153, 153, 153);\">-</span></td>";
					for ($y = 0; $y <= $feltantal; $y++) {
						if ($feltnavn[$y] == 'belob' || $feltnavn[$y] == 'saldo') {
							print "<td align=right><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
						} elseif ($feltnavn[$y])
							print "<td><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
						else
							print "<td align=center><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
					}
					print "</tr>";
				}
			}
		}
	}

	fclose($fp);
	print "</tbody></table>";
	print "</td></tr>";
	db_modify("update grupper set box1='$kontonr', box2='$feltantal' WHERE art='KASKL' AND kode='3' AND kodenr='$bruger_id'", __FILE__ . " linje " . __LINE__);
	for ($y = 0; $y <= $feltantal; $y++) {
		$box = $y + 3;
		if ($box <= 10) {
			$box = "box$box";
			db_modify("update grupper set $box='$feltnavn[$y]' WHERE art='KASKL' AND kode='3' AND kodenr='$bruger_id'", __FILE__ . " linje " . __LINE__);
		}
	}
}
function reconcile($filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $vend)
{
	global $bgcolor, $bgcolor5, $bruger_id, $charset, $fokus, $regnaar, $sprog_id;
	if ($splitter == 'Semikolon')
		$splitter = ';';
	$fileLines = explode("\n", file_get_contents($filnavn));

	$feltnavne = $feltnavn[0];
	for ($i = 1; $i < count($feltnavn); $i++) {
		$feltnavne .= "|" . $feltnavn[$i];
	}
	$up = if_isset($_GET['up'], 0);
	$down = if_isset($_GET['down'], 0);
	if ($up)
		$fokus = "u" . $up;
	if ($down)
		$fokus = "d" . $down;
	$byt = if_isset($_GET['byt'], 0);
	if ($up && $byt) {
		$qtxt = "update transaktioner set pos = pos+1 where id = $byt";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		$qtxt = "update transaktioner set pos = pos-1 where id = $up";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	} elseif ($down && $byt) {
		$qtxt = "update transaktioner set pos = pos-1 where id = $byt";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		$qtxt = "update transaktioner set pos = pos+1 where id = $down";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	print "</td></tr><tr><td><table width = '100%' height = '100%' valign = 'top'>";
	#	for ($l=0;$l<count($feltnavn);$l++) print "<td>$l $feltnavn[$l]</td>";
	$startdate = '2099-12-31';
	$enddate = '2000-01-01';
	$i = 0;
	for ($l = 0; $l < count($fileLines); $l++) {
		$columns[$l] = explode($splitter, $fileLines[$l]);
		for ($c = 0; $c < count($columns[$l]); $c++) {
			if (!isset($feltnavn[$c]))
				$feltnavn[$c] = null;
			if ($feltnavn[$c] == 'dato') {
				$columns[$l][$c] = str_replace('/', '-', $columns[$l][$c]);
				$columns[$l][$c] = str_replace('.', '-', $columns[$l][$c]);
				$bankDate[$i] = $columns[$l][$c];
				if (is_numeric(str_replace('-', '', $columns[$l][$c]))) {
					if ($columns[$l][$c] <= $startdate)
						$startdate = $columns[$l][$c];
					if ($columns[$l][$c] >= $enddate)
						$enddate = $columns[$l][$c];
				}
			} elseif ($feltnavn[$c] == 'belob') {
				if (substr($columns[$l][$c], -3, 1) == ',' && is_numeric(substr($columns[$l][$c], -2))) {
					$columns[$l][$c] = usdecimal($columns[$l][$c], 2);
					$bankAmount[$i] = $columns[$l][$c];
				}
			} elseif ($feltnavn[$c] == 'saldo') {
				if (substr($columns[$l][$c], -3, 1) == ',' && is_numeric(substr($columns[$l][$c], -2))) {
					$columns[$l][$c] = usdecimal($columns[$l][$c], 2);
					$bankSaldo[$i] = $columns[$l][$c];
				}
			} elseif ($feltnavn[$c] == 'beskrivelse') {
				$bankText[$i] = $columns[$l][$c];
			}
		}
		if (is_numeric(str_replace('-', '', $bankDate[$i])))
			$i++;
	}
	$fiscalYear = '';
	list($y, $m, $d) = explode('-', $startdate);
	$qtxt = "SELECT * FROM grupper WHERE art = 'RA' order by kodenr";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while (!$fiscalYear && $r = db_fetch_array($q)) {
		if ($r['box2'] <= $y && $r['box1'] >= $m && $r['box4'] >= $y && $r['box3'] <= $m) {
			$fiscalYear = $r['kodenr'];
			$fiscalYearStart = $r['box2'] . "-" . $r['box1'] . "-01";
		} elseif ($r['box2'] <= $y && $r['box1'] <= $m && $r['box4'] >= $y && $r['box3'] >= $m) {
			$fiscalYear = $r['kodenr'];
			$fiscalYearStart = $r['box2'] . "-" . $r['box1'] . "-01";
		}
	}
	$qtxt = "SELECT primo FROM kontoplan WHERE kontonr = '$kontonr' AND regnskabsaar = '$fiscalYear'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$primo = $r['primo'];

	$qtxt = "SELECT (SUM(debet) - SUM(kredit)) AS amount FROM transaktioner ";
	$qtxt .= "WHERE kontonr= $kontonr AND transdate < '$startdate' AND transdate > '$fiscalYearStart'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$primo += $r['amount'];

	$counter = $i = 1;
	$qtxt = "select * FROM transaktioner WHERE kontonr = '$kontonr' AND transdate >= '$startdate' AND transdate <= '$enddate' ";
	$qtxt .= "AND (debet != 0 OR kredit != 0) order by transdate,pos,id desc";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$transId[$i] = $r['id'];
		$transPos[$i] = $r['pos'];
		$transDate[$i] = $r['transdate'];
		$transText[$i] = $r['beskrivelse'];
		$transAmount[$i] = $r['debet'] - $r['kredit'];
		if ($i > 1 && $transDate[$i] != $transDate[$i - 1])
			$counter = 1;
		if ($transPos[$i] == 0) {
			$qtxt = "update transaktioner set pos = '$counter' where id = '$transId[$i]'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		$counter++;
		$i++;
	}
	$transSaldo = $primo;

	$bg = $i = 0;
	print "<tr><td>Dato</td><td>Tekst</td><td>Beløb</td><td>Saldo</td><td colspan='2'></td>";
	print "<td>Dato</td><td>Tekst</td><td>Beløb</td><td>Saldo</td></tr>";
	for ($l = 0; $l < count($fileLines); $l++) {
		$match[$l] = 0;
		for ($x = 1; $x <= count($transAmount); $x++) {
			if ($transDate[$x] == if_isset($bankDate[$l]) && !in_array($transId[$x], $match)) {
				#echo "$transDate[$x] == $bankDate[$l] && $bankAmount[$l]<br>";
				#if ($bankDate[$l] == '2023-12-01') echo "$transDate[$x] == $bankDate[$l] && $transAmount[$x] == $bankAmount[$l]<br>";
				if ($transAmount[$x] == $bankAmount[$l]) {
					$match[$l] = $transId[$x];
				}
			}
		}
		$amountLine = 0;
		($bg == $bgcolor) ? $bg = $bgcolor5 : $bg = $bgcolor;
		print "<tr bgcolor = '$bg' >";
		($match[$l]) ? $txtcolor = 'black' : $txtcolor = 'red';
		print "<td style='color:$txtcolor'>" . if_isset($bankDate[$l]) . "</td>
		<td style='color:$txtcolor'>" . if_isset($bankText[$l]) . "</td>
		<td style='color:$txtcolor'>" . dkdecimal(if_isset($bankAmount[$l])) . "</td>
		<td style='color:$txtcolor'>" . dkdecimal(if_isset($bankSaldo[$l])) . "</td>";
		/*
											  for ($c = 0; $c < count($columns[$l]); $c++) {
												  if (isset($feltnavn[$c]) && $feltnavn[$c]) {
													  if ($feltnavn[$c] == 'belob' || $feltnavn[$c] == 'saldo') {
														  if (is_numeric($columns[$l][$c])) {
															  $amountLine = 1;
															  if ($feltnavn[$c] == 'saldo')
																  $accountSaldo = $columns[$l][$c];
														  }
														  print "<td align = 'right'>" . dkdecimal($columns[$l][$c]) . "</td>";
													  } else
														  print "<td>" . $columns[$l][$c] . "</td>";
												  }
											  }
											  */
		$amountLine = 1;
		if ($amountLine && $match[$l]) {
			$i++;
			$transSaldo += $transAmount[$i];
			$transtjek = afrund($bankAmount[$l] - $transAmount[$i], 2);
			($transtjek != 0) ? $txtcolor = 'red' : $txtcolor = 'black';
			$saldotjek = afrund($bankSaldo[$l] - $transSaldo, 2);
			#			($saldotjek != 0) ? $txtcolor = 'red' : $txtcolor = 'black';
			print "<td width = '25px'>";
			if ($i > 1 && $transDate[$i - 1] == $transDate[$i]) {
				print "<a href='../finans/bankReconcile.php?reconcile=1&filnavn=$filnavn&kontonr=$kontonr&vend=$vend";
				print "&up=$transId[$i]&byt=" . $transId[$i - 1] . "&feltnavne=$feltnavne&splitter=$splitter' id='u$transId[$i]'>";
				print "<img src='../ikoner/up.png' width='25px' height='25px' style='border: 0px solid;'></a>";
			} else {
				print "<a href='../finans/bankReconcile.php?reconcile=1&filnavn=$filnavn&kontonr=$kontonr&vend=$vend";
				print "&feltnavne=$feltnavne&splitter=$splitter' id='u$transId[$i]'>";
			}
			print "</td>";
			print "<td width = '25px'>";
			if (isset($transDate[$i + 1]) && $transDate[$i] == $transDate[$i + 1]) {
				print "<a href='../finans/bankReconcile.php?reconcile=1&filnavn=$filnavn&kontonr=$kontonr&vend=$vend";
				print "&down=$transId[$i]&byt=" . $transId[$i + 1] . "&feltnavne=$feltnavne&splitter=$splitter' id='d$transId[$i]'>";
				print "<img src='../ikoner/down.png' width='25px' height='25px' style='border: 0px solid;'></a>";
			} else {
				print "<a href='../finans/bankReconcile.php?reconcile=1&filnavn=$filnavn&kontonr=$kontonr&vend=$vend";
				print "&feltnavne=$feltnavne&splitter=$splitter' id='d$transId[$i]'>";
			}
			print "</td>";
			print "<td style='color:$txtcolor'>$transDate[$i]</td>";
			print "<td style='color:$txtcolor'>$transText[$i]</td>";
			print "<td align = 'right' style='color:$txtcolor'>" . dkdecimal($transAmount[$i]) . "</td>";
			print "<td align = 'right' style='color:$txtcolor'>" . dkdecimal($transSaldo) . "</td>";
			if ($saldotjek) {
				print "<td align = 'right' style='color:red'>(" . dkdecimal($bankSaldo[$l] - $transSaldo) . ")</td>";
			}
		} else
			print "<td colspan = '6'></td>";
		print "</tr>";
	}
	print "</table>";
}
# endfunc # vis_data

# endfunc # vis_data

function nummertjek($nummer)
{
	$nummer = (float) $nummer;
	$komma = $punktum = 0;
	$retur = 1;
	$nummerliste = array("1", "2", "3", "4", "5", "6", "7", "8", "9", "0", ",", ".", "-");
	for ($x = 0; $x < strlen($nummer); $x++) {
		if (!in_array(substr($nummer, $x, 1), $nummerliste)) {
			$retur = 0;
		}
	}
	if ($retur) {
		for ($x = 0; $x < strlen($nummer); $x++) {
			if (substr($nummer, $x, 1) == ',')
				$komma++;
			elseif (substr($nummer, $x, 1) == '.')
				$punktum++;
		}
		if ((!$komma) && (!$punktum))
			$retur = 'US';
		elseif (($komma == 1) && (substr($nummer, -3, 1) == ','))
			$retur = 'DK';
		elseif (($punktum == 1) && (substr($nummer, -3, 1) == '.'))
			$retur = 'US';
		elseif (($komma == 1) && (!$punktum))
			$retur = 'DK';
		elseif (($punktum == 1) && (!$komma))
			$retur = 'US';
	}
	return $retur;
}
print "<script language=\"javascript\">";
#print "document.getElementById(\"$fokus\").focus();";
print "$fokus.focus();";
print "</script>";
?>
<!--
<script language="javascript">
	document.addEventListener("DOMContentLoaded", function(event) { 
			var scrollpos = localStorage.getItem('scrollpos');
			if (scrollpos) window.scrollTo(0, scrollpos);
		});

		window.onbeforeunload = function(e) {
			localStorage.setItem('scrollpos', window.scrollY);
		};
</script>
-->
