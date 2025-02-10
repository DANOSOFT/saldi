<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/importer.php --- lap 4.1.0 --- 2024-04-19 ---
// 								LICENSE
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
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20130415 - Fejl hvis linje starter med separatortegn.
// 20150105 - Transdate sættes til dd hvis den ikke er sat20150105
// 20170307 - Tilføjet loppeafregning.
// 20180611 - Udkommenteret datotjek da den genererer en ugyldig dato fra en gyldig. 20180611 
// 20190116 MSC - Rettet topdesign til og rettet isset fejl.
// 20190119 PHR - Added dataløn.
// 20190311 PHR - Added UTF-8 - $fileCharSet.
// 20200305 LOE - Changed table height from '100%' to 'auto' as it looked awfull in FF
// 20210629 LOE - Translated these texts to English and Norsk
// 20210722 LOE - Tranlated some texts and some title tags
// 20200827	PHR	- Removed all non numeric characters from $bilag
// 20231030 PHR php8
// 20240402 PHR - Missing '>'
// 20240419 PHR Some claeanup
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding

@session_start();
$s_id = session_id();
$css = "../css/standard.css";

$title = "Import til kassekladde";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");


print "<div align=\"center\">";

$fileCharSet = "ISO-8895-1";
$feltnavn = array();
$fieldnames = array('bilag', 'dato', 'beskrivelse', 'debet(konto)', 'kredit(konto)', 'debet(beløb)', 'kredit(beløb)', 'debitor', 'kreditor', 'fakturanr', 'beløb', 'forfaldsdag', 'afd');


if (($_GET) || ($_POST)) {

	if (isset($_GET['bilagsnr'])) {
		$kladde_id = $_GET['kladde_id'];
		$bilag = $_GET['bilagsnr'];
	} else {
		$submit = if_isset($_POST['submit']);
		$kladde_id = if_isset($_POST['kladde_id']);
		$filnavn = if_isset($_POST['filnavn']);
		$splitter = if_isset($_POST['splitter']);
		(isset($_POST['feltnavn'])) ? $feltnavn = $_POST['feltnavn'] : $feltnavn = array();
		$feltantal = if_isset($_POST['feltantal']);
		#		$kontonr=$_POST['kontonr'];
		$bilag = if_isset($_POST['bilag']);
		$datoformat = if_isset($_POST['datoformat']);
		$fileCharSet = if_isset($_POST['fileCharSet']);
	}
	if ($menu == 'T') {
		print "<center><table width=\"75%\" height=\"auto\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	} else {
		print "<center><table width=\"100%\" height=\"auto\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	}
	if ($menu == 'T') {
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\"> 
				<div class=\"headerbtnLft\"></div>
				<span class=\"headerTxt\">" . findtekst(1074, $sprog_id) . " " . findtekst(904, $sprog_id) . " " . lcfirst(findtekst(105, $sprog_id)) . " (" . lcfirst(findtekst(105, $sprog_id)) . " $kladde_id)</span>";
		print "<div class=\"headerbtnRght\"></div>";
		print "</div><!-- end of header -->
			<div class=\"maincontentLargeHolder\">\n";
		print "<center><table border='0' cellspacing='1' width='75%'>";
	} else {
		print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
		print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
		print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>" . findtekst(30, $sprog_id) . "</a></td>";
		print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">" . findtekst(1074, $sprog_id) . " " . findtekst(904, $sprog_id) . " " . lcfirst(findtekst(105, $sprog_id)) . " (" . lcfirst(findtekst(105, $sprog_id)) . " $kladde_id)</td>";
		print "<td width=\"10%\" $top_bund ><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"></td>";
		print "</tbody></table>";
		print "</td></tr>";
	}

	if (!isset($_FILES['uploadedfile']['name']))
		$_FILES['uploadedfile']['name'] = NULL;
	if (!isset($submit))
		$submit = NULL;

	if (basename($_FILES['uploadedfile']['name'])) {
		#if (file_exists($_FILES['uploadedfile']['name'])) echo $_FILES['uploadedfile']['name']." eksisterer<br>";
		#else echo $_FILES['uploadedfile']['name']." er ikke uploaded<br>";
		$filnavn = "../temp/" . $db . "_" . str_replace(" ", "_", $brugernavn) . ".csv";
		#echo "$filnavn<br>";
		if (move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			if ($r = db_fetch_array(db_select("select * from grupper where ART = 'KASKL' and kode='2' and kodenr='$bruger_id'", __FILE__ . " linje " . __LINE__))) {
				$feltantal = if_isset($r['box1']);
				$feltnavn = explode(",", $r['box2']);
				$datoformat = $r['box3'];
				#box14 bruges at pbswebimport.php
			} else {
				db_modify("insert into grupper (beskrivelse,art,kode,kodenr) values ('Kassekladdeimport','KASKL','2','$bruger_id')", __FILE__ . " linje " . __LINE__);
			}
			if (!$feltantal)
				$feltantal = 1;
			if (in_array('bilag', $feltnavn))
				vis_data($kladde_id, $filnavn, '', $feltnavn, $feltantal, 0, $datoformat);
			else
				vis_data($kladde_id, $filnavn, '', $feltnavn, $feltantal, $bilag, $datoformat);
		} else {
			echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
		}
	} elseif ($submit == 'Vis') {
		if (in_array('bilag', $feltnavn))
			vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, 0, $datoformat);
		else
			vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $bilag, $datoformat);
	} elseif ($submit == 'Flyt') {
		if (($kladde_id) && ($filnavn) && ($splitter)) {
			if (in_array('bilag', $feltnavn))
				flyt_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, 0, $datoformat);
			else
				flyt_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $bilag, $datoformat);
		} else {
			if (in_array('bilag', $feltnavn))
				vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, 0, $datoformat);
			else
				vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $bilag, $datoformat);
		}
	} else {
		if (in_array('bilag', $feltnavn))
			upload($kladde_id, 0);
		else
			upload($kladde_id, $bilag);
	}

	/*	
		  if ($kladde_id)
			  {
				  hentdata($kladde_id);
			  }
		  */
}
print "</tbody></table>";
################################################################################################################
function upload($kladde_id, $bilag)
{
	global $charset, $menu, $sprog_id;

	if ($menu == 'T') {
		$hrlinje = "<hr width=100%>";
	} else {
		$hrlinje = "<hr width=30%>";
	}

	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td width=100% align=center><b>" . findtekst(1074, $sprog_id) . " " . lcfirst(findtekst(1076, $sprog_id)) . "</b></td></tr>"; #20210629
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<form enctype=\"multipart/form-data\" action=\"bankimport.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
	print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
	if ($bilag)
		print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
	print "<tr><td width=100% align=center> " . findtekst(586, $sprog_id) . " " . findtekst(1077, $sprog_id) . ": <input class=\"inputbox\" name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"" . findtekst(1078, $sprog_id) . "\" /></td></tr>";
	print "<tr><td></form></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td>$hrlinje</td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";

	$title = '';
	print "<tr><td width=100% align=center title='$title'><b>" . findtekst(1079, $sprog_id) . "</b></td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<form enctype=\"multipart/form-data\" action=\"danlonimport.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
	print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
	if ($bilag)
		print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
	print "<tr><td width=100% align=center title='$title'> " . findtekst(586, $sprog_id) . " " . findtekst(1077, $sprog_id) . ": <input class=\"inputbox\" name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"" . findtekst(1078, $sprog_id) . "\" /></td></tr>";
	print "<tr><td></form></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td>$hrlinje</td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";

	$title = '';
	print "<tr><td width=100% align=center title='$title'><b>" . findtekst(1080, $sprog_id) . "</b></td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<form enctype=\"multipart/form-data\" action=\"datalonimport.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
	print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
	if ($bilag)
		print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
	print "<tr><td width=100% align=center title='$title'> " . findtekst(586, $sprog_id) . " " . findtekst(1077, $sprog_id) . ": <input class=\"inputbox\" name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"" . findtekst(1078, $sprog_id) . "\" /></td></tr>";
	print "<tr><td></form></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td>$hrlinje</td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";

	$title = 'Import af betalingsoplysninger leverance 0602"';
	print "<tr><td width=100% align=center title='$title'><b>" . findtekst(1081, $sprog_id) . " M602</b></td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<form enctype=\"multipart/form-data\" action=\"pbsm602import.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
	print "<input type=\"hidden\" name=\"kladde_id\" value='$kladde_id'>";
	if ($bilag)
		print "<input type=\"hidden\" name=\"bilag\" value='$bilag'>";
	print "<tr><td width=100% align=center title='$title'> " . findtekst(586, $sprog_id) . " " . findtekst(1077, $sprog_id) . ": <input class=\"inputbox\" name=\"uploadedfile[]\" type=\"file\" multiple/><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"" . findtekst(1078, $sprog_id) . "\" /></td></tr>";
	print "<tr><td></form></td></tr>";

	print "<tr><td><br></td></tr>";
	print "<tr><td>$hrlinje</td></tr>";
	//PBS Web
	print "<tr><td width=100% align=center><br></td></tr>";
	$title = findtekst(1644, $sprog_id);
	print "<tr><td width=100% align=center title='$title'><b>" . findtekst(1643, $sprog_id) . "</b></td></tr>"; #20210722
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<form enctype=\"multipart/form-data\" action=\"pbswebimport.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
	print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
	if ($bilag)
		print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
	print "<tr><td width=100% align=center title='$title'> " . findtekst(586, $sprog_id) . " " . findtekst(1077, $sprog_id) . ": <input class=\"inputbox\" name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"" . findtekst(1078, $sprog_id) . "\" /></td></tr>";
	print "<tr><td></form></td></tr>";

	print "<tr><td><br></td></tr>";
	print "<tr><td>$hrlinje</td></tr>";
	//Other data
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<tr><td width=100% align=center><b>" . findtekst(1082, $sprog_id) . "</b></td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<form enctype=\"multipart/form-data\" action=\"importer.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"10000000\">";
	print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
	if ($bilag)
		print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
	print "<tr><td width=100% align=center> " . findtekst(586, $sprog_id) . " " . findtekst(1077, $sprog_id) . ": <input class=\"inputbox\" name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"" . findtekst(1078, $sprog_id) . "\" /></td></tr>";
	print "<tr><td></form></td></tr>";

	print "<tr><td><br></td></tr>";
	print "<tr><td>$hrlinje</td></tr>";
	//SAF-T XML file
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<tr><td width=100% align=center><b>" . findtekst(3053, $sprog_id) . "</b></td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<form enctype=\"multipart/form-data\" action=\"xmlimport.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"10000000\">";
	print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
	if ($bilag)
		print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
	print "<tr><td width=100% align=center> " . findtekst(586, $sprog_id) . " " . findtekst(1077, $sprog_id) . ": <input class=\"inputbox\" name=\"xml\" type=\"file\" /><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"" . findtekst(1078, $sprog_id) . "\" /></td></tr>";
	print "<tr><td></form></td></tr>";

	print "<tr><td><br></td></tr>";
	print "<tr><td>$hrlinje</td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<tr><td width=100% align=center><b>" . findtekst(1083, $sprog_id) . "</b></td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<tr><td width=100% align=center><a href='loppeafregning.php?kladde_id=$kladde_id'><button>Start</button></a></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}

function vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $bilag, $datoformat) {
	global $charset, $fileCharSet, $fieldnames, $bruger_id, $sprog_id;

	$x = 0;
	$check = array();
	for ($y = 0; $y < count($feltnavn); $y++) {
		if (in_array($feltnavn[$y], $check))
			alert("Der må kun være en kolonne med $feltnavn[$y]");
		if ($feltnavn[$y]) {
			$check[$x] = $feltnavn[$y];
			$x++;
		}
	}

	$komma = $semikolon = $tabulator = NULL;
	if (!$datoformat)
		$datoformat = "mm-dd-yyyy";

	$fp = fopen("$filnavn", "r");
	if ($fp) {
		for ($y = 1; $y < 10; $y++) {
			if ($linje = fgets($fp)) {
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
		}
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
	fclose($fp);

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
			$linje = trim($linje);
			if ($linje) {
				if (!$y && ord(substr($linje, 0, 1)) == 239 && ord(substr($linje, 1, 1)) == 187 && ord(substr($linje, 2, 1)) == 191)
					$linje = substr($linje, 3);
				$y++;
				if ($charset == 'UTF-8' && $fileCharSet != 'UTF-8')
					$linje = mb_convert_encoding($linje, 'UTF-8', 'ISO-8859-1');
				$anftegn = 0;
				$felt = array();
				$z = 0;
				for ($x = 0; $x < strlen($linje); $x++) {
					if ($x == 0 && substr($linje, $x, 1) == '"') {
						$z++;
						$anftegn = 1;
						$felt[$z] = '';
					} elseif ($x == 0 && substr($linje, $x, 1) == $splittegn) { #20130415
						$felt[$z] = '';
						$z++;
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
				for ($x = 1; $x <= $z; $x++) {
					if (!isset($felt[$x]))
						$felt[$x] = NULL;
					if (!isset($ny_linje[$y]))
						$ny_linje[$y] = NULL;
					$ny_linje[$y] = $ny_linje[$y] . $felt[$x] . chr(9);
				}
				$x++;
				if (!isset($felt[$x]))
					$felt[$x] = NULL;
				$ny_linje[$y] = $ny_linje[$y] . $felt[$x] . "\n";
			}
		}
	}
	$linjeantal = $y;
	#$cols=$feltantal;
	fclose($fp);
	$fp = fopen($filnavn . "2", "w");
	for ($y = 1; $y <= $linjeantal; $y++) {
		fwrite($fp, $ny_linje[$y]);
	}
	fclose($fp);
	#print "<tr><td><hr></td></tr>";
	#print "<tr><td><hr></td></tr>";
	#print "<tr><td><hr></td></tr>";
	#print "<tr><td><hr></td></tr>";
	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"importer.php\" method=\"POST\">";
	#print "<tr><td colspan=6 width=100% align=center> $filnavn</td></tr>";
	print "<tr><td colspan='$cols' align=center>";
	print "Tegnsæt <select style=\"width:80px\" type=\"text\" name=\"fileCharSet\" >";
	if ($fileCharSet)
		print "<option>$fileCharSet</option>";
	if ($fileCharSet != 'UTF-8')
		print "<option>UTF-8</option>";
	if ($fileCharSet != 'ISO-8859-1')
		print "<option>ISO-8859-1</option>";
	print "</select> ";
	print "Datoformat <input style=\"width:80px\" type=\"text\" name=\"datoformat\" value=\"$datoformat\"> ";
	print "<span title='" . findtekst(1645, $sprog_id) . "'>" . findtekst(1377, $sprog_id) . "<select name=\"splitter\">\n";
	if ($splitter) {
		print "<option>$splitter</option>\n";
	}
	if ($splitter != 'Semikolon')
		print "<option>" . findtekst(1378, $sprog_id) . "</option>\n"; #20210722
	if ($splitter != 'Komma')
		print "<option>" . findtekst(1379, $sprog_id) . "</option>\n";
	if ($splitter != 'Tabulator')
		print "<option>Tabulator</option>\n";
	print "</select></span>";
	print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
	print "<input type=\"hidden\" name=\"feltantal\" value=$feltantal>";
	print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
	print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Vis\" />";
	if (($kladde_id) && ($filnavn) && ($splitter))
		print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Flyt\" /></td></tr>";
	print "<tr><td colspan=$cols><hr></td></tr>\n";
	$fil_splitter = $splitter;
	#if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
	#elseif ($splitter=='Komma') {$splitter=',';}
	#elseif ($splitter=='Tabulator') {$splitter=chr(9);}
	$splitter = chr(9);
	print "<tr>";
	if (!in_array('bilag', $feltnavn))
		print "<td><span title='" . findtekst(1404, $sprog_id) . "'><input class=\"inputbox\" type=text size=4 name=bilag value=$bilag></span></td>";
	for ($y = 0; $y <= $feltantal; $y++) {
		if ($feltnavn[$y] == 'bilag' || $feltnavn[$y] == 'afd' || strstr($feltnavn[$y], 'beløb')) {
			print "<td align=right><select name=\"feltnavn[$y]\">\n";
			#	elseif (!$feltnavn[$y]) print "<td><select>\n";
		} else
			print "<td align=center><select name=\"feltnavn[$y]\">\n";
		if (!$feltnavn[$y])
			print "<option></option>\n";
		for ($f = 0; $f < count($fieldnames); $f++) {
			if ($feltnavn[$y] == $fieldnames[$f])
				print "<option \"align=right\" value='$fieldnames[$f]'>$fieldnames[$f]</option>\n";
		}
		for ($f = 0; $f < count($fieldnames); $f++) {
			if ($feltnavn[$y] != $fieldnames[$f])
				print "<option \"align=right\" value='$fieldnames[$f]'>$fieldnames[$f]</option>\n";
		}
		if ($feltnavn[$y])
			print "<option></option>\n";
		print "</select></td>";
	}
	if (!in_array('beløb', $feltnavn) && !in_array('debet(beløb)', $feltnavn) && !in_array('kredit(beløb)', $feltnavn)) {
		alert('Der skal være en kolonne med beløb');
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
					$felt[$y] = trim(if_isset($felt[$y]));
					if ((substr($felt[$y], 0, 1) == '"') && (substr($felt[$y], -1) == '"'))
						$felt[$y] = substr($felt[$y], 1, strlen($felt[$y]) - 2);
					if ($feltnavn[$y] == 'dato')
						$felt[$y] = str_replace(".", "-", $felt[$y]);
					if ($feltnavn[$y] == 'belob') {
						if (nummertjek($felt[$y]) == 'US') {
							if ($felt[$y] == 0)
								$skriv_linje = 0;
							else
								$felt[$y] = dkdecimal($felt[$y]);
						} elseif (nummertjek($felt[$y]) == 'DK') {
							if (usdecimal($felt[$y]) == 0)
								$skriv_linje = 0;
						} else
							$skriv_linje = 0;
					}
				}
			}
			if ($skriv_linje == 1) {
				print "<tr>";
				if (!in_array('bilag', $feltnavn))
					print "<td>$bilag</td>";
				for ($y = 0; $y <= $feltantal; $y++) {
					if ($feltnavn[$y] == 'dato')
						$felt[$y] = datotjek($datoformat, $felt[$y]);
					if ($feltnavn[$y] == 'bilag' || $feltnavn[$y] == 'afd' || strstr($feltnavn[$y], 'beløb')) {
						print "<td align=right>$felt[$y]&nbsp;</td>";
					} elseif ($feltnavn[$y])
						print "<td>$felt[$y]&nbsp;</td>";
					else
						print "<td align=center><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
				}
				print "</tr>";
				if (!in_array('bilag', $feltnavn))
					$bilag++;
			} else {
				print "<tr>";
				if (!in_array('bilag', $feltnavn))
					print "<td><span style=\"color: rgb(153, 153, 153);\">-</span></td>";
				for ($y = 0; $y <= $feltantal; $y++) {
					#				if ($feltnavn[$y]=='dato') $felt[$y]=datotjek($datoformat,$felt[$y]);
					if ($feltnavn[$y] == 'bilag' || strstr($feltnavn[$y], 'beløb')) {
						print "<td align=right><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
					} elseif ($feltnavn[$y])
						print "<td><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
					else
						print "<td align='center'><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
				}
				print "</tr>";
			}
		}
	}
	fclose($fp);
	print "</tbody></table>";
	print "</td></tr>";
	$box2 = $feltnavn[0];
	for ($y = 1; $y <= $feltantal; $y++)
		$box2 .= "," . $feltnavn[$y];
	db_modify("update grupper set box1='$feltantal',box2='$box2',box3='$datoformat' where ART='KASKL' and kode='2' and kodenr='$bruger_id'", __FILE__ . " linje " . __LINE__);
} # function vis_data slut;

function flyt_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $bilag, $datoformat) {

	global $charset, $fieldnames;

	$afd = $fakturanr = $d_type = $k_type = $forfaldsdate = $komma = $semikolon = $tabulator = NULL;
	transaktion('begin');
	$splitter = chr(9);
	$fp = fopen($filnavn . "2", "r");
	if ($fp) {
		$x = 0;
		while (!feof($fp)) {
			$skriv_linje = 0;
			if ($linje = trim(fgets($fp))) {
				$x++;
				$skriv_linje = 1;
				$felt = array();
				$felt = explode($splitter, $linje);
				for ($y = 0; $y < $feltantal; $y++) {
					(isset($felt[$y])) ? $felt[$y] = trim($felt[$y]) : $felt[$y] = NULL;
					if ((substr($felt[$y], 0, 1) == '"') && (substr($felt[$y], -1) == '"'))
						$felt[$y] = substr($felt[$y], 1, strlen($felt[$y]) - 2);
					if ($feltnavn[$y] == 'dato') {
						#						$felt[$y]=datotjek($datoformat,$felt[$y]); # Udkommenteret 20180611
					}
					#					if ($feltnavn[$y]=='dato') $felt[$y]=str_replace(".","-",$felt[$y]);
					if (strstr($feltnavn[$y], 'beløb')) {
						if (nummertjek($felt[$y]) == 'DK')
							$felt[$y] = (float) usdecimal($felt[$y]);
						elseif (nummertjek($felt[$y]) != 'US')
							$skriv_linje = 0;
					}
				}
			}
			if ($skriv_linje == 1) {
				$amount = $debet = $kredit = $forfaldsdag = NULL;
				for ($y = 0; $y <= $feltantal; $y++) {
					$felt[$y] = if_isset($felt[$y],NULL);
					if ($feltnavn[$y] == 'bilag')
						$bilag = (int) $felt[$y];
					$bilag = preg_replace("/[^0-9]/", "", $bilag); #20210827
					if (!$bilag)
						$bilag = $bilag;
					if (!$amount && $feltnavn[$y] == 'debet(beløb)') {
						if (nummertjek($felt[$y]) == 'DK')
							$amount = (float) usdecimal($felt[$y]);
						elseif (nummertjek($felt[$y]) == 'US')
							$amount = (float) $felt[$y];
					}
					if (!$amount && $feltnavn[$y] == 'kredit(beløb)') {
						if (nummertjek($felt[$y]) == 'DK')
							$amount = (float) usdecimal($felt[$y]);
						elseif (nummertjek($felt[$y]) == 'US')
							$amount = (float) $felt[$y];
						$amount *= -1;
					}
					if (!$amount && $feltnavn[$y] == 'beløb') {
						if (nummertjek($felt[$y]) == 'DK')
							$amount = (float) usdecimal($felt[$y]);
						elseif (nummertjek($felt[$y]) == 'US')
							$amount = (float) $felt[$y];
					} elseif ($feltnavn[$y] == "dato") {
						$transdate = usdate(datotjek($datoformat, $felt[$y]));
					} elseif ($feltnavn[$y] == "beskrivelse")
						$beskrivelse = $felt[$y];
					elseif ($feltnavn[$y] == "debet(konto)") {
						$d_type = "F";
						$debet = $felt[$y];
					} elseif ($feltnavn[$y] == "kredit(konto)") {
						$d_type = "F";
						$kredit = $felt[$y];
					} elseif ($feltnavn[$y] == "debitor") {
						$d_type = "D";
						$debet = $felt[$y];
					} elseif ($feltnavn[$y] == "kreditor") {
						$k_type = "K";
						$kredit = $felt[$y];
					} elseif ($feltnavn[$y] == "fakturanr")
						$fakturanr = $felt[$y];
					elseif ($feltnavn[$y] == "forfaldsdag") {
						($felt[$y]) ? $forfaldsdate = usdate($felt[$y]) : $forfaldsdate = NULL;
					} elseif ($feltnavn[$y] == "afd") {
						$afd = $felt[$y];
					}
				}
				if (!$transdate)
					$transdate = date('Y-m-d'); #20150105
				if ($amount) {
					$bilag = (int)$bilag;
					$amount *= 1;
					$qtxt = "insert into kassekladde (bilag,transdate,beskrivelse,faktura,amount,kladde_id";
					if ($debet)        $qtxt .= ",d_type,debet";
					if ($kredit)       $qtxt .= ",k_type,kredit";
					if ($forfaldsdate) $qtxt .= ",forfaldsdate";
					if ($afd)
						$qtxt .= ",afd";
					$qtxt .= ")";
					$qtxt .= " values ";
					$qtxt .= "('$bilag','$transdate','" . db_escape_string($beskrivelse) . "','" . db_escape_string($fakturanr) . "',";
					$qtxt .= "'$amount','$kladde_id'";
					if ($debet)        $qtxt .= ",'$d_type','$debet'";
					if ($kredit)       $qtxt .= ",'$k_type','$kredit'";
					if ($forfaldsdate) $qtxt .= ",'$forfaldsdate'";
					if ($afd)
						$qtxt .= ",'$afd'";
					$qtxt .= ")";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					if (!in_array('bilag', $feltnavn))
						$bilag++;
				}
			}
		}
	}
	fclose($fp);
	unlink($filnavn); # sletter filen.
	unlink($filnavn . "2"); # sletter filen.
	transaktion('commit');
	print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
}

function nummertjek($nummer) {
	$komma = $punktum = NULL;
	$nummer = trim($nummer);
	$retur = 1;
	$nummerliste = array("1", "2", "3", "4", "5", "6", "7", "8", "9", "0", ",", ".", "-");
	for ($x = 0; $x < strlen($nummer); $x++) {
		if (!in_array($nummer[$x], $nummerliste))
			$retur = 0;
	}
	if ($retur) {
		for ($x = 0; $x < strlen($nummer); $x++) {
			if ($nummer[$x] == ',')
				$komma++;
			elseif ($nummer[$x] == '.')
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
function datotjek($datoformat, $dato) {
	$dato = trim($dato);
	$datoformat = strtolower($datoformat);

	$dag = $maaned = $aar =NULL;

	$tal = "01234567890";
	$tegn_a = substr($datoformat, 0, 1);
	$dag = $tegn_b = $tegn_c = NULL;
	$a = substr($dato, 0, 1);
	$b = NULL;
	$c = NULL;
	for ($i = 1; $i < strlen($datoformat); $i++) {
		$tegn = substr($datoformat, $i, 1);
		$datotegn = substr($dato, $i, 1);
		if ($b == NULL) {
			if ($tegn == $tegn_a)
				$a .= $datotegn;
			elseif (strstr($tal, $datotegn)) {
				$tegn_b = $tegn;
				$b = $datotegn;
			} else
				$splitter = $tegn;
		} elseif ($c == NULL) {
			if ($tegn == $tegn_b)
				$b .= $datotegn;
			elseif (strstr($tal, $datotegn)) {
				$tegn_c = $tegn;
				$c = $datotegn;
			} else
				$splitter = $tegn;
		} else {
			if ($tegn == $tegn_c)
				$c .= $datotegn;
			else
				$splitter = $tegn;
		}
	}
	if ($tegn_a == 'd')
		$dag = $a;
	elseif ($tegn_a == 'm')
		$maaned = $a;
	else
		$aar = $a;
	if ($tegn_b == 'd')
		$dag = $b;
	elseif ($tegn_b == 'm')
		$maaned = $b;
	else
		$aar = $b;
	if ($tegn_c == 'd')
		$dag = $c;
	elseif ($tegn_c == 'm')
		$maaned = $c;
	else
		$aar = $c;

	return ("$dag-$maaned-$aar");
}
