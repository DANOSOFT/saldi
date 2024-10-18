<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/rapport.php --- lap 4.0.8 --- 2024-10-18 ---
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
// Copyright (c) 2003-2024 saldi.dk ApS
// ----------------------------------------------------------------------

// 20120927 Hvis budgettal indsat og konto lukket blev konto alligevel vist under budget
// 20130210 Break ændret til break 1
// 20130918	Diverse tilretninger til simulering - Søg $simulering
// 20130919	Fejl i søgefunktion ved opdelte projektnumre. Søg 20130919
// 20140729 Listeangivelse ændret fra kvartal til måned - ca. Søg 20140729
// 20140825 Resultatkonto viste årssaldo uanset om den var valgt periode. PHR Søg 20140825
// 20140909 Resultat fra resultatkto kom ikke med i sum. PHR Søg 20140909
// 20150104 Tilføjet dynamisk vagerværdi - Søg /*aut_lager*/
// 20150125 Fejl i lagerberegning i statusrapport- lagetræk blev lagt til værdi, ombyttet + & - - Søg 20150125
// 20150408 Fejl i lagerberegning i statusrapport- medtog sidste dag i foregående md - tilføjet 'start'/'slut' til find_lagervaerdi. Søg find_lagervaerdi
// 20150825 Transaktioner med ens bilag, tekst og kontonummer blev samlet sammen til linje. Ved ikke hvorfor men det gør det svært at kontrollere bank
// 20151001 Sat fast bredde på felter i overskrifter.
// 20160116	Diverse i forbindelse med indførelse af valutakonti	Søg 'valuta'
// 20160515 Oprydning dk- og uscecimal, indsat ',2'
// 20170516 PHR Fakturadate ændret til kobsdate i søgning efter lagerbevægelser for bedre overensstemmelse med svar fra 'find_lagervaerdi' Søg 20170516
// 20180226 PHR - Bortkommenteret if (!$dim) så primo vises på afdelinger.
// 20180424 PHR - Tilføjet "regnskab" (Resultat + bufget i et).
// 20181031 PHR - Tilføjet  "&& $kontotype[$x]=='D'" så den kun søger i driftskonti da der kan ligge budgettal i andre konti hvis kontoplan ændret. 20181031
// 20181220 MSC - Rettet topmenu design til
// 20181221 MSC - Rettet topmenu design til og rettet isset fejl
// 20190207 MSC - Rettet array fejl (linje 815) - rettelse (linje 813)
// 20190220 PHR tilføjet " || ($kontotype[$x] == 'Z' && $x==$kontoantal) " Da balancekonto ellers ikke vises hvis sum=0 - 20190220
// 20190412 PHR Moved functions to 'rapport_includes' and added 'Resultat/Sidste år' 
// 20190924 PHR Added option 'Poster uden afd". when "afdelinger" is used. $afd='0' 
// 20210110 PHR some minor changes related til 'deferred financial year'
// 20230611 +20230619 PHR php8
// 20240403 PHR Changet bankReconcile to $[POST]
// 20241018 LOE Checks that some variables are set before using and other minore modifications

@session_start();
$s_id = session_id();

$title = "Finansrapport";
$modulnr = 4;
$css = "../css/standard.css";

include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$aar_fra = "";
$maaned_fra = "";
$maaned_til = "";
$dato_fra = "";
$dato_til = "";
$konto_fra = "";
$konto_til = "";
$rapportart = "";
$ansat_fra = "";
$ansat_til = "";
$projekt_fra = "";
$projekt_til = "";
$simulering = "";
$lagerbev = "";

if (!isset($find))
	$find = NULL;
if (!isset($prj_navn_til))
	$prj_navn_til = NULL;
if (!isset($prj_navn_fra))
	$prj_navn_fra = NULL;

if ($_POST) {
	if (isset($_POST['kontrolspor']) && $_POST['kontrolspor']) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=kontrolspor.php\">";
		exit;
	}
	if (isset($_POST['provisionsrapport']) && $_POST['provisionsrapport']) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=provisionsrapport.php\">";
		exit;
	}

	$submit = str2low(trim($_POST['submit']));
	$rapportart = if_isset($_POST['rapportart']);
	$aar_fra = if_isset($_POST['aar_fra']);
	$aar_til = if_isset($_POST['aar_til']);
	$maaned_fra = trim(if_isset($_POST['maaned_fra']));
	$maaned_til = trim(if_isset($_POST['maaned_til']));
	if (strpos($maaned_fra, '|')) {
		list($aar_fra, $maaned_fra) = explode('|', $maaned_fra);
	}
	if (strpos($maaned_til, '|')) {
		list($aar_til, $maaned_til) = explode('|', $maaned_til);
	}
	$dato_fra = if_isset($_POST['dato_fra']);
	$dato_til = if_isset($_POST['dato_til']);
	$md = if_isset($_POST['md']);
	$ansat_id = if_isset($_POST['ansat_id']);
	$ansat_init = if_isset($_POST['ansat_init']);
	$antal_ansatte = if_isset($_POST['antal_ansatte']);
	$ansat_fra = if_isset($_POST['ansat_fra']);
	$projekt_fra = if_isset($_POST['projekt_fra']);
	$projekt_til = if_isset($_POST['projekt_til']);
	$simulering = if_isset($_POST['simulering']);
	$lagerbev = if_isset($_POST['lagerbev']);

	$bankReconcile  = if_isset($_POST['bankReconcile']);
	
	#cho "prj_navn_fra $prj_navn_fra -> $projekt_fra<br>";
	if (stristr($rapportart, "Listeangivelse")) {
		$listeperiode = preg_replace('/[^0-9.]*/', '', $rapportart); # 20140729 afsnit 1
		print "<meta http-equiv=\"refresh\" content=\"0;URL=listeangivelse.php?listeperiode=$listeperiode\">";
		exit;
	}

	if ($ansat_fra) {
		list($tmp, $tmp2) = explode(":", $ansat_fra);
		$tmp = trim($tmp);
		for ($x = 1; $x <= $antal_ansatte; $x++) {
			if ($tmp == $ansat_init[$x]) {
				$ansat_fra = $ansat_id[$x];
				$ansat_init_fra = $ansat_init[$x];
				$ansatte = $tmp;
			}
		}
	}
	$ansat_til = if_isset($_POST['ansat_til']);
	if ($ansat_til) {
		$ansatte_id = $ansat_fra;
		list($tmp, $tmp2) = explode(":", $ansat_til);
		$tmp = trim($tmp);
		for ($x = 1; $x <= $antal_ansatte; $x++) {
			if ($tmp == $ansat_init[$x]) {
				$ansat_til = $ansat_id[$x];
				if ($ansat_init_fra != $tmp) {
					$ansatte = $ansatte . "," . $tmp;
					$ansatte_id = $ansatte_id . "," . $ansat_id[$x];
				}
				$x = $antal_ansatte;
			} elseif ($ansat_init[$x] > $ansat_init_fra) {
				$ansatte = $ansatte . "," . $ansat_init[$x];
				$ansatte_id = $ansatte_id . "," . $ansat_id[$x];
			}
		}
	}
	$afd = if_isset($_POST['afd']);
	if ($afd || $afd == '0') {
		list($afd, $afd_navn) = explode(":", $afd);
		$afd = trim($afd);
	}
	$delprojekt = if_isset($_POST['delprojekt']);
	if ($projekt_til)
		$delprojekt = NULL;
	elseif ($delprojekt) {
		$find = 0; #20130919 +næste 5 linjer
		for ($a = 0; $a < count($delprojekt); $a++) {
			if ($delprojekt[$a])
				$find = 1;
		}
	}
	if ($find) {
		$prj_cfg = if_isset($_POST['prj_cfg']);
		$prcfg = explode("|", $prj_cfg);
		$b = count($delprojekt);
		$projekt_fra = NULL;
		for ($a = 0; $a < $b; $a++) {
			$c = strlen($delprojekt[$a]);
			if ($c > $prcfg[$a])
				$delprojekt[$a] = mb_substr($delprojekt[$a], 0, $prcfg[$a], $db_encode);
			for ($d = $c; $d < $prcfg[$a]; $d++) {
				$delprojekt[$a] = "?" . $delprojekt[$a];
			}
			$projekt_fra .= $delprojekt[$a];
		}
		$projekt_til = $projekt_fra;
	} else {
		$projekt_fra = if_isset($_POST['projekt_fra']);
		if (strpos($projekt_fra, ":")) {
			list($projekt_fra, $prj_navn_fra) = explode(":", $projekt_fra);
			$projekt_fra = trim($projekt_fra);
		}
		$projekt_til = if_isset($_POST['projekt_til']);
		if (strpos($projekt_til, ":")) {
			list($projekt_til, $prj_navn_til) = explode(":", $projekt_til);
			$projekt_til = trim($projekt_til);
		}
		if ($projekt_fra && !$prj_navn_fra) {
			$r = db_fetch_array(db_select("select beskrivelse from grupper where kodenr = '$projekt_fra'", __FILE__ . " linje " . __LINE__));
			$prj_navn_fra = $r['beskrivelse'];
		}
		if ($projekt_til && !$prj_navn_til) {
			$r = db_fetch_array(db_select("select beskrivelse from grupper where kodenr = '$projekt_til'", __FILE__ . " linje " . __LINE__));
			$prj_navn_til = $r['beskrivelse'];
		}
	}
	$tmp = str_replace("?", "", $projekt_fra);
	if (!$tmp) {
		$projekt_fra = NULL;
		$projekt_til = NULL;
	}
	$konto_fra = if_isset($_POST['konto_fra']);
	if ($konto_fra) list($konto_fra, $beskrivelse) = explode(":", $konto_fra);
	$konto_til = if_isset($_POST['konto_til']);
	if ($konto_til) list($konto_til, $beskrivelse) = explode(":", $konto_til);
	$regnaar = if_isset($_POST['regnaar']);
	if ($regnaar && !is_numeric($regnaar)) list($regnaar, $beskrivelse) = explode(" - ", $regnaar);
}

if (isset($_GET['rapportart']))  $rapportart = $_GET['rapportart'];
if (isset($_GET['dato_fra']))    $dato_fra = $_GET['dato_fra'];
if (isset($_GET['maaned_fra']))  $maaned_fra = $_GET['maaned_fra'];
if (isset($_GET['aar_fra']))     $aar_fra = $_GET['aar_fra'];
if (isset($_GET['konto_fra']))	 $konto_fra = $_GET['konto_fra'];
if (isset($_GET['konto_fra2']) && $_GET['konto_fra2']) $konto_fra = $_GET['konto_fra2'];
if (isset($_GET['ansat_fra']))   $ansat_fra = $_GET['ansat_fra'];
if (isset($_GET['projekt_fra'])) $projekt_fra = $_GET['projekt_fra'];
if (isset($_GET['dato_til']))    $dato_til = $_GET['dato_til'];
if (isset($_GET['maaned_til']))  $maaned_til = $_GET['maaned_til'];
if (isset($_GET['aar_til']))     $aar_til = $_GET['aar_til'];
if (isset($_GET['konto_til']))   $konto_til = $_GET['konto_til'];
if (isset($_GET['ansat_til']))   $ansat_til = $_GET['ansat_til'];
if (isset($_GET['projekt_til'])) $projekt_til = $_GET['projekt_til'];
if (isset($_GET['regnaar']))     $regnaar = $_GET['regnaar'];
if (isset($_GET['afd']))         $afd = $_GET['afd'];
if (isset($_GET['simulering']))  $simulering = $_GET['simulering'];
if (isset($_GET['lagerbev']))    $lagerbev = $_GET['lagerbev'];

$regnaar = (int) $regnaar;
$md[1] = "januar";
$md[2] = "februar";
$md[3] = "marts";
$md[4] = "april";
$md[5] = "maj";
$md[6] = "juni";
$md[7] = "juli";
$md[8] = "august";
$md[9] = "september";
$md[10] = "oktober";
$md[11] = "november";
$md[12] = "december";

if ($submit != 'ok') $submit = 'forside';

elseif ($rapportart) {
	if ($rapportart == "balance" || $rapportart == "resultat" || $rapportart == "budget" || $rapportart == "lastYear") {
		$qtxt = "select kontonr from kontoplan where regnskabsaar='$regnaar' and kontotype='X'";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			if ($rapportart != "balance") {
				if (!$konto_til || $konto_til >= $r['kontonr']) {
					$qtxt = "select max(kontonr) as kontonr from kontoplan where regnskabsaar='$regnaar' and kontonr < '$r[kontonr]'";
					if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $konto_til = $r['kontonr'];
				}
			} else {
				if (!$konto_fra || $konto_fra <= $r['kontonr']) {
					$qtxt = "select min(kontonr) as kontonr from kontoplan where regnskabsaar='$regnaar' and kontonr > '$r[kontonr]'";
					if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $konto_fra = $r['kontonr'];
				}
			}
		}	else {
			$txt = 'Sideskiftkonto ikke defineret i kontoplan - Balance & Resultat kan ikke adskilles';
			alert($txt);
		}
		$submit = "regnskab";
	} else $submit = str2low($rapportart);
}
/*
elseif ($rapportart) {
	if ($rapportart == "balance" || $rapportart == "resultat" || $rapportart == "budget" || $rapportart == "lastYear") {
		$qtxt = "select kontonr from kontoplan where regnskabsaar='$regnaar' and kontotype='X'";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			if ($rapportart == "resultat") {
				if (!$konto_til || $konto_til >= $r['kontonr']) $konto_til = $r['kontonr'] - 1;
			} elseif ($rapportart != "balance") $konto_til = $r['kontonr'] - 1;
			elseif (!$konto_fra || $konto_fra <= $r['kontonr']) $konto_fra = $r['kontonr'] + 1;
			} else {
				$txt = 'Sideskiftkonto ikke defineret i kontoplan - Balance & Resultat kan ikke adskilles';
				alert($txt);
		}
		$submit = "regnskab";
	} else $submit = str2low($rapportart);
}
*/
if (!$aar_fra || !$aar_til) {
	$qtxt = "select box2,box4 from grupper where art='RA' and kodenr='$regnaar'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$aar_fra = $r['box2'];
	$aar_til = $r['box4'];
}
if ($submit == 'saft') {
	header("Location: saft.php?regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&aar_fra=$aar_fra&aar_til=$aar_til&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&rapportart=$rapportart");
	exit();
}
if ($submit == 'regnskabbasis') {
	header("Location: regnskabbasis.php?regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&aar_fra=$aar_fra&aar_til=$aar_til&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&rapportart=$rapportart");
	exit();
}
if (isset($bankReconcile) && $bankReconcile) {
	header("Location: bankReconcile.php?regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&aar_fra=$aar_fra&aar_til=$aar_til&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&rapportart=$rapportart");
	exit();
}
include("rapport_includes/$submit.php");
$submit($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til, $simulering, $lagerbev);
#################################################################################################
function kontobemaerkning($l_kontonavn)
{
	global $sprog_id;
	$retur = NULL;
	if (strstr($l_kontonavn, "RESULTAT")) {
		$retur = "title=\"Negativt resultat betyder overskud. Positivt resultat betyder underskud.\"";
	} elseif ($l_kontonavn == "Balancekontrol") {
		$retur = "title=\"Balancekontrollen viser det forel&oslash;bige eller periodens resultat, n&aring;r regnskabet ikke er afsluttet. Positivt viser et overskud. Negativt et underskud.\"";
	}
	return ($retur);
}

function momsrubrik($rubrik_konto, $rubrik_navn, $regnaar, $regnstart, $regnslut)
{
	global $sprog_id;
	print "<tr><td>" . $rubrik_konto . "</td><td colspan='3'>" . $rubrik_navn . "</td>";
	if ($rubrik_konto) {
		$q = db_select("select * from kontoplan where regnskabsaar='$regnaar' and kontonr=$rubrik_konto", __FILE__ . " linje " . __LINE__);
		$r = db_fetch_array($q);
		#			$kontobeskrivelse[$x]=$r['beskrivelse'];
		$rubriksum = 0;
		$q = db_select("select * from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' and kontonr=$rubrik_konto", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$rubriksum += afrund($r['debet'], 2) - afrund($r['kredit'], 2);
		}
		print "<td align='right'>" . dkdecimal($rubriksum, 2) . "</td>";
	} else {
		print "<td align='right'><span title='Intet bel&oslash;b i den angivne periode.'>-</span></td>";
	}
	print "<td>&nbsp;</td></tr>\n";
	return;
}

# Funktionen ændret fra kvartal til måned. 20140729 start afsnit 2 
function listeangivelser($regnaar, $rapportart, $option_type)
{
	global $sprog_id;

	$qtxt = "select box1, box2, box3, box4 from grupper where art = 'RA' and kodenr = '$regnaar' order by box2, box1 desc";
	$x = 0;
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($q);
	$liste_aar[$x] = ($row['box2'] * 1);
	$liste_md[$x] = ($row['box1'] * 1);
	$liste_rapportart[$x] = "Listeangivelse " . $liste_md[$x] . ". måned " . $liste_aar[$x];
	if (isset($liste_md[$x]) && $liste_md[$x] < 10)
		$liste_md[$x] = "0" . $liste_md[$x];
	$liste_aarmd[$x] = $liste_aar[$x] . $liste_md[$x];
	if (isset($kvartal_aar[$x]))
		$kvartal_aarmd[$x] = ($kvartal_aar[$x] . $row['box1']) * 1 + 2;
	$slut_aarmd = (int)($row['box4'] . $row['box3']);
	while ($liste_aarmd[$x] < $slut_aarmd) {
		$x++;
		$liste_md[$x] = $liste_md[$x - 1] + 1;
		$liste_aar[$x] = $liste_aar[$x - 1];
		if ($liste_md[$x] >= 13) {
			$liste_md[$x] = 1;
			$liste_aar[$x] += 1;
		}
		$liste_rapportart[$x] = "Listeangivelse " . $liste_md[$x] . ". måned " . $liste_aar[$x];
		if ($liste_md[$x] < 10)
			$liste_md[$x] = "0" . $liste_md[$x];
		$liste_aarmd[$x] = $liste_aar[$x] . $liste_md[$x];
	}
	$retur = "";
	for ($i = 0; $i <= $x; $i++) {
		if ($rapportart && $option_type == "matcher" && $rapportart == $liste_rapportart[$i]) {
			print "<option title=\"Listeangivelser pr. måned.\">" . $liste_rapportart[$i] . "</option>\n";
		}
	}
	for ($i = 0; $i <= $x; $i++) {
		if ($option_type == "alle andre" && (!$rapportart || !($rapportart == $liste_rapportart[$i]))) {
			#			print "<option value=\"".$liste_mdaar[$i]."\" title=\"Listeangivelser pr. måned.\">".$liste_rapportart[$i]."</option>\n";
			print "<option title=\"Listeangivelser pr. måned.\">" . $liste_rapportart[$i] . "</option>\n";
		}
	}

	return $retur;
} # slut function listeangivelser

?>
</body>
</html>

