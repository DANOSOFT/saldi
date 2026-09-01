<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/importer_kontoplan.php --- lap 4.0.6 --- 2022-04-03 ---
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
// Copyright (c) 2003 - 2022 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20181112 Håndtering af tegnsæt og MAC linjeskift.
// 20181204 Valg gemmes i cookie
// 20210713 LOE - Translated some texts
// 20220404	PHR function vis_data & overfoer_data: Inserted trim($felt[$y],'"');	
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20260825 Sawaneh JOB-086: import ended in a blank page - now returns to import/export with a
//                  message; file charset detected on upload and lines with an invalid charset are
//                  rejected instead of aborting the transaction; validation shared between preview
//                  and import; charset, separator and column names whitelisted before use in SQL.
// 20260827 Sawaneh JOB-086 review: cookie set before online.php sends output; preview field count
//                  follows the chosen separator; non-numeric opening balances flag the row as error.

@session_start();
$s_id=session_id();

ini_set("auto_detect_line_endings", true);

$css="../css/standard.css";

$returside="diverse.php?sektion=div_io";

$charsets = array('UTF-8' => 'UTF-8', 'ISO-8859-15' => 'ISO-8859-15 (Windows)', 'cp865' => 'cp865 (DOS)', 'macintosh' => 'MAC');
$splitters = array('Semikolon' => ';', 'Komma' => ',', 'Tabulator' => chr(9));
$kolonner = array('Kontonr', 'Beskrivelse', 'Kontotype', 'Moms', 'Fra_kto', 'map_to', 'primo');

$file_charset = $_POST['file_charset'] ?? '';
$splitter = $_POST['splitter'] ?? '';
$feltantal = intval($_POST['feltantal'] ?? 0);
$feltnavn = $_POST['feltnavn'] ?? '';
$gemValg = is_array($feltnavn);
if (!$gemValg) {
	$feltnavn = array();
	if (isset($_COOKIE['saldi_kto_imp']) && substr_count($_COOKIE['saldi_kto_imp'], '|') == 2) {
		list($file_charset, $splitter, $fn) = explode("|", $_COOKIE['saldi_kto_imp']);
		$feltnavn = explode(";", $fn);
	}
}
if (!array_key_exists($file_charset, $charsets)) $file_charset = '';
if (!array_key_exists($splitter, $splitters)) $splitter = '';
foreach ($feltnavn as $i => $navn) {
	if (!in_array($navn, $kolonner)) $feltnavn[$i] = '';
}
if ($gemValg) setcookie('saldi_kto_imp', $file_charset."|".$splitter."|".implode(";", $feltnavn));

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

$title = findtekst('1356|Importér', $sprog_id)." ".findtekst('612|Kontoplan', $sprog_id);
$filnavn = "../temp/".$db."_".str_replace(" ", "_", $brugernavn).".csv";

print "<div align=\"center\">";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #tabel 1 
print "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.1.1

print "<td width=\"170px\"><a href=\"$returside\" accesskey=\"L\">
       <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst(30, $sprog_id)."</button></a></td>

       <td align='center' style='$topStyle'>".$title."<br></td>

       <td width=\"170px\" style='$topStyle'><br></td></tr>
       </tbody></table></td></tr>"; # <- tabel 1.1.1

print "</tr></tbody></table></td></tr>";

if (isset($_FILES['uploadedfile']['name']) && basename($_FILES['uploadedfile']['name'])) {
	if (move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
		vis_data(find_file_charset($filnavn), $filnavn, $splitter, $feltnavn, 1);
	} else {
		print findtekst('5084|Der er sket en fejl under hentningen, prøv venligst igen', $sprog_id);
	}
} elseif ((isset($_POST['vis']) || isset($_POST['import'])) && file_exists($filnavn)) {
	if (!$file_charset) $file_charset = find_file_charset($filnavn);
	if (isset($_POST['import']) && $splitter) overfoer_data($file_charset, $filnavn, $splitter, $feltnavn, $feltantal);
	else vis_data($file_charset, $filnavn, $splitter, $feltnavn, $feltantal);
} else {
	$qtxt = "select box1, box2, beskrivelse from grupper where art='RA' order by box2 desc,box1 desc";
	if (!$r1 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		alert(findtekst('5087|Der er ikke oprettet et regnskabsår', $sprog_id));
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
		exit;
	}
	$startdate = $r1['box2']."-".$r1['box1']."-01";
	$qtxt = "select id from transaktioner where transdate >= '$startdate'";
	if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		alert(findtekst(1365, $sprog_id).": ".$r1['beskrivelse']." - ".findtekst(1366, $sprog_id));
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
		exit;
	}
	upload();
}

print "</tbody></table>";
#####################################################################################################
function upload() {
	global $sprog_id;

	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"importer_kontoplan.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
	print "<tr><td width=100% align=center> ".findtekst(1364, $sprog_id).": <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" name=\"hent\" value=\"".findtekst('1078|Hent', $sprog_id)."\" /></td></tr>";
	print "<tr><td></form></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}

function find_file_charset($filnavn) {
	return mb_check_encoding(file_get_contents($filnavn), 'UTF-8') ? 'UTF-8' : 'ISO-8859-15';
}

function find_splitter($filnavn) {
	global $splitters;

	$linje = '';
	$antal = array();
	$fp = fopen($filnavn, "r");
	if (!$fp) return array('', array());
	for ($y = 1; $y < 4; $y++) $linje = fgets($fp);
	fclose($fp);
	foreach ($splitters as $navn => $tegn) $antal[$navn] = substr_count((string)$linje, $tegn);
	arsort($antal);
	if (!$antal[key($antal)]) return array('', $antal);
	return array(key($antal), $antal);
}

function tjek_kolonner($feltnavn, $feltantal) {
	global $sprog_id;

	$brugt = array();
	for ($y = 0; $y <= $feltantal; $y++) {
		$navn = $feltnavn[$y] ?? '';
		if ($navn && in_array($navn, $brugt)) {
			alert(findtekst('5083|Der kan kun være 1 kolonne med', $sprog_id)." ".$navn);
			$navn = '';
		} elseif ($navn) {
			$brugt[] = $navn;
		}
		$feltnavn[$y] = $navn;
	}
	return $feltnavn;
}

function tjek_linje($linje, $file_charset, $splitTegn, $feltnavn, $feltantal, &$kontonumre) {
	global $charset;

	$fejl = 0;
	if ($file_charset == 'UTF-8' && !mb_check_encoding($linje, 'UTF-8')) {
		$fejl = 1;
		$linje = mb_convert_encoding($linje, $charset, 'ISO-8859-1');
	} elseif ($file_charset != $charset) {
		$linje = iconv($file_charset, $charset.'//TRANSLIT', $linje);
	}
	$felt = explode($splitTegn, $linje);
	$kontotyper = array("H", "D", "S", "Z", "R");
	$typeKolonne = array_search('Kontotype', $feltnavn);
	$kontotype = ($typeKolonne === false) ? '' : trim(trim($felt[$typeKolonne] ?? ''), "\"'");
	for ($y = 0; $y <= $feltantal; $y++) {
		$felt[$y] = trim(trim($felt[$y] ?? ''), "\"'");
		$navn = $feltnavn[$y] ?? '';
		if ($navn == 'Kontonr') {
			if (!ctype_digit($felt[$y]) || in_array($felt[$y], $kontonumre)) $fejl = 1;
			else $kontonumre[] = $felt[$y];
		} elseif ($navn == 'Kontotype' && !in_array($felt[$y], $kontotyper)) {
			$fejl = 1;
		} elseif ($navn == 'Moms' && $felt[$y] && !preg_match('/^[SKEY][0-9]*$/', $felt[$y])) {
			$fejl = 1;
		} elseif ($navn == 'Fra_kto') {
			if (!$felt[$y] || ($kontotype != 'Z' && $kontotype != 'R')) $felt[$y] = '0';
			if (!ctype_digit($felt[$y])) $fejl = 1;
		} elseif ($navn == 'map_to') {
			$felt[$y] = (int)$felt[$y];
		} elseif ($navn == 'primo' && !is_numeric($felt[$y])) {
			if (!$felt[$y]) $felt[$y] = '0';
			elseif (preg_match('/^-?(\d+|\d{1,3}(\.\d{3})+)(,\d+)?$/', $felt[$y])) $felt[$y] = usdecimal($felt[$y]);
			else $fejl = 1;
		}
	}
	return array('felt' => $felt, 'fejl' => $fejl);
}

function vis_data($file_charset, $filnavn, $splitter, $feltnavn, $feltantal) {
	global $charset, $charsets, $splitters, $kolonner, $regnaar, $sprog_id;

	list($fundet, $antal) = find_splitter($filnavn);
	if (!$splitter) $splitter = $fundet;
	if (!empty($antal[$splitter])) $feltantal = $antal[$splitter];
	$cols = $feltantal + 1;
	$feltnavn = tjek_kolonner($feltnavn, $feltantal);

	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"importer_kontoplan.php\" method=\"POST\">";
	print "<tr><td colspan='$cols' align=center>".findtekst('1376|Tegnsæt', $sprog_id)." <select name='file_charset'>\n";
	foreach ($charsets as $kode => $navn) {
		$valgt = ($kode == $file_charset) ? " selected" : "";
		print "<option value='$kode'$valgt>$navn</option>\n";
	}
	print "</select>&nbsp; ".findtekst('1377|Separatortegn', $sprog_id)." <select name='splitter'>\n";
	foreach (array_keys($splitters) as $navn) {
		$valgt = ($navn == $splitter) ? " selected" : "";
		print "<option$valgt>$navn</option>\n";
	}
	print "</select>";
	print "<input type=\"hidden\" name=\"feltantal\" value=\"$feltantal\">";
	print "&nbsp; <input type=\"submit\" name=\"vis\" value=\"".findtekst('1133|Vis', $sprog_id)."\" />";
	if (in_array('Kontonr', $feltnavn) && in_array('Beskrivelse', $feltnavn) && in_array('Kontotype', $feltnavn)) {
		print "&nbsp; <input type=\"submit\" name=\"import\" value=\"".findtekst('1356|Importér', $sprog_id)."\" />";
	}
	print "</td></tr><tr><td colspan=$cols><hr></td></tr>\n";
	print "<tr>";
	for ($y = 0; $y <= $feltantal; $y++) {
		print "<td align=center><select name='feltnavn[$y]'>\n<option></option>\n";
		foreach ($kolonner as $kolonne) {
			if ($kolonne == 'primo' && $regnaar != 1) continue;
			$valgt = ($kolonne == $feltnavn[$y]) ? " selected" : "";
			$tekst = ($kolonne == 'map_to') ? 'Map til' : $kolonne;
			print "<option value='$kolonne'$valgt>$tekst</option>\n";
		}
		print "</select></td>";
	}
	print "</tr></form>";

	$splitTegn = $splitters[$splitter] ?? ';';
	$kontonumre = array();
	$advaret = 0;
	$fp = fopen($filnavn, "r");
	if ($fp) {
		while (!feof($fp)) {
			$linje = trim((string)fgets($fp));
			if (!$linje) continue;
			$tjek = tjek_linje($linje, $file_charset, $splitTegn, $feltnavn, $feltantal, $kontonumre);
			if ($tjek['fejl'] && !$advaret) {
				alert(findtekst('5081|Røde linjer indeholder fejl og bliver ikke importeret', $sprog_id));
				$advaret = 1;
			}
			$farve = $tjek['fejl'] ? "#e00000" : "#000000";
			print "<tr>";
			for ($y = 0; $y <= $feltantal; $y++) {
				$vaerdi = htmlspecialchars((string)$tjek['felt'][$y], ENT_QUOTES | ENT_SUBSTITUTE, $charset);
				if ($feltnavn[$y]) print "<td><span style=\"color: $farve;\">$vaerdi&nbsp;</span></td>";
				else print "<td align=center><span style=\"color: rgb(153, 153, 153);\">$vaerdi&nbsp;</span></td>";
			}
			print "</tr>";
		}
		fclose($fp);
	}
	print "</tbody></table>";
	print "</td></tr>";
}

function overfoer_data($file_charset, $filnavn, $splitter, $feltnavn, $feltantal) {
	global $splitters, $regnaar, $sprog_id, $returside;

	$feltnavn = tjek_kolonner($feltnavn, $feltantal);
	if (!in_array('Kontonr', $feltnavn) || !in_array('Beskrivelse', $feltnavn) || !in_array('Kontotype', $feltnavn)) {
		alert(findtekst('5085|Kontonr, Beskrivelse og Kontotype skal være valgt', $sprog_id));
		vis_data($file_charset, $filnavn, $splitter, $feltnavn, $feltantal);
		return;
	}
	$r1 = db_fetch_array(db_select("select kodenr from grupper where art='RA' order by box2 desc,box1 desc limit 1", __FILE__ . " linje " . __LINE__));
	$regnskabsaar = intval($r1['kodenr'] ?? 0);

	$splitTegn = $splitters[$splitter];
	$kontonumre = array();
	$linjer = array();
	$balance = 0;
	$fp = fopen($filnavn, "r");
	if ($fp) {
		while (!feof($fp)) {
			$linje = trim((string)fgets($fp));
			if (!$linje) continue;
			$tjek = tjek_linje($linje, $file_charset, $splitTegn, $feltnavn, $feltantal, $kontonumre);
			if (!$tjek['fejl']) $linjer[] = $tjek['felt'];
		}
		fclose($fp);
	}
	if (!$linjer) {
		alert(findtekst('5086|Ingen konti importeret - kontoplanen er uændret', $sprog_id));
		vis_data($file_charset, $filnavn, $splitter, $feltnavn, $feltantal);
		return;
	}

	$sqlKolonner = array();
	for ($y = 0; $y <= $feltantal; $y++) {
		if ($feltnavn[$y]) $sqlKolonner[$y] = strtolower($feltnavn[$y]);
	}
	transaktion('begin');
	db_modify("delete from kontoplan where regnskabsaar='$regnskabsaar'", __FILE__ . " linje " . __LINE__);
	foreach ($linjer as $felt) {
		$vaerdier = array();
		foreach ($sqlKolonner as $y => $kolonne) {
			$vaerdier[] = "'".db_escape_string($felt[$y])."'";
			if ($kolonne == 'primo') $balance += $felt[$y];
		}
		$qtxt = "insert into kontoplan (".implode(",", $sqlKolonner).", regnskabsaar) values (".implode(",", $vaerdier).", '$regnskabsaar')";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	db_modify("update kontoplan set til_kto=kontonr where kontotype='Z' and regnskabsaar='$regnskabsaar'", __FILE__ . " linje " . __LINE__);
	transaktion('commit');

	if ($regnaar == 1 && round($balance, 2) != 0) alert(findtekst('5080|Åbningsbalance stemmer ikke - kontroller sum', $sprog_id));
	else alert(count($linjer)." ".findtekst('5082|konti importeret - husk at overføre åbningstal', $sprog_id));
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
}
