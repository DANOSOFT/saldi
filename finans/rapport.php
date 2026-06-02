
<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/rapport.php --- lap 5.0.0 --- 2026-04-30 ---
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
// Copyright (c) 2003-2026 saldi.dk ApS
// ----------------------------------------------------------------------

// 20210110 PHR some minor changes related til 'deferred financial year' 
// 20230611 +20230619 PHR php8
// 20240403 PHR Changet bankReconcile to $[POST]
// 20241018 LOE Checks that some variables are set before using and other minore modifications
// 20260227 PHR Moved include("../includes/row-hover-style.js.php") down as it broke saf-t and other using header 
// 20260306 LOE Updated some variables with if_isset() to avoid excessive undefined variable notices in error logs.
// 20260329 LOE Added conditions to allow standalone versions of kontokort and kontokort_moms to be used without modifying the code in this file.
@session_start();
$s_id = session_id();

$title = "Finansrapport";
$modulnr = 4;
$css = "../css/standard.css";

include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/topline_settings.php");
include("../includes/std_func.php");
include_once("../includes/emballage_schema.php");
$packagingModuleEnabled = (get_settings_value("packagingModuleEnabled", "items", "off") === "on");
if ($packagingModuleEnabled) ensure_emballage_schema();

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
$konto_fra2 = "";
$aar_til = "";


if (!isset($find))
	$find = NULL;
if (!isset($prj_navn_til))
	$prj_navn_til = NULL;
if (!isset($prj_navn_fra))
	$prj_navn_fra = NULL;

if ($_POST) {
	 if (isset($_POST['update_financial_year']) && $_POST['update_financial_year']) {
        // Just refresh the page with the new financial year
        $new_regnaar = if_isset($_POST['regnaar']);
        print "<meta http-equiv=\"refresh\" content=\"0;URL=rapport.php?regnaar=$new_regnaar\">";
        exit;
    }
	if (isset($_POST['kontrolspor']) && $_POST['kontrolspor']) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=kontrolspor.php\">";
		exit;
	}
	if (isset($_POST['provisionsrapport']) && $_POST['provisionsrapport']) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=provisionsrapport.php\">";
		exit;
	}
	$submit = str2low(trim(if_isset($_POST, NULL, 'submit')));
	$rapportart = if_isset($_POST, NULL, 'rapportart');
	$aar_fra = if_isset($_POST, NULL, 'aar_fra');
	$aar_til = if_isset($_POST, NULL, 'aar_til');
	$maaned_fra = trim(if_isset($_POST, NULL, 'maaned_fra'));
	$maaned_til = trim(if_isset($_POST, NULL, 'maaned_til'));
	if (strpos($maaned_fra, '|')) {
		list($aar_fra, $maaned_fra) = explode('|', $maaned_fra);
	}
	if (strpos($maaned_til, '|')) {
		list($aar_til, $maaned_til) = explode('|', $maaned_til);
	}

	$dato_fra = if_isset($_POST, NULL, 'dato_fra');
	$dato_til = if_isset($_POST, NULL, 'dato_til');
	$md = if_isset($_POST, NULL, 'md');
	$ansat_id = if_isset($_POST, NULL, 'ansat_id');
	$ansat_init = if_isset($_POST, NULL, 'ansat_init');
	$antal_ansatte = if_isset($_POST, NULL, 'antal_ansatte');
	$ansat_fra = if_isset($_POST, NULL, 'ansat_fra');
	$projekt_fra = if_isset($_POST, NULL, 'projekt_fra');
	$projekt_til = if_isset($_POST, NULL, 'projekt_til');
	$simulering = if_isset($_POST, NULL, 'simulering');
	$lagerbev = if_isset($_POST, NULL, 'lagerbev');

	$bankReconcile  = if_isset($_POST, NULL, 'bankReconcile');
	
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
	$delprojekt = if_isset($_POST,NULL,'delprojekt');
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
		$prj_cfg = if_isset($_POST,NULL,'prj_cfg');
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
		$projekt_fra = if_isset($_POST,NULL,'projekt_fra');
		if (strpos($projekt_fra, ":")) {
			list($projekt_fra, $prj_navn_fra) = explode(":", $projekt_fra);
			$projekt_fra = trim($projekt_fra);
		}
		$projekt_til = if_isset($_POST,NULL,'projekt_til');
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
	
	/**#+
	 * Processes 'konto_fra', 'konto_til', and 'regnaar' from the POST data.
	 * For each, if the value contains a delimiter (":" or " - "), it splits into two parts:
	 *  - The first part is assigned to the respective variable.
	 *  - The second part is assigned to `$beskrivelse`.
	 */
	$konto_fra = if_isset($_POST['konto_fra']);
	if ($konto_fra) list($konto_fra, $beskrivelse) = explode(":", $konto_fra) + [1 => null];

	$konto_til = if_isset($_POST['konto_til']);
	if ($konto_til) list($konto_til, $beskrivelse) = explode(":", $konto_til) + [1 => null];

	$regnaar = if_isset($_POST['regnaar']);
	if ($regnaar && !is_numeric($regnaar)) list($regnaar, $beskrivelse) = explode(" - ", $regnaar) + [1 => null];
	#+
}



################
$rapportart   = if_isset($_GET, $rapportart, 'rapportart');
$dato_fra     = if_isset($_GET, $dato_fra, 'dato_fra');
$maaned_fra   = if_isset($_GET, $maaned_fra, 'maaned_fra');
$aar_fra      = if_isset($_GET, $aar_fra, 'aar_fra');
$konto_fra    = if_isset($_GET, $konto_fra, 'konto_fra');
$konto_fra2   = if_isset($_GET, $konto_fra2, 'konto_fra2');
if ($konto_fra2) $konto_fra = $konto_fra2;  
$ansat_fra    = if_isset($_GET, $ansat_fra, 'ansat_fra');
$projekt_fra  = if_isset($_GET, $projekt_fra, 'projekt_fra');
$dato_til     = if_isset($_GET, $dato_til, 'dato_til');
$maaned_til   = if_isset($_GET, $maaned_til, 'maaned_til');
$aar_til      = if_isset($_GET, $aar_til, 'aar_til');
$konto_til    = if_isset($_GET, $konto_til, 'konto_til');
$ansat_til    = if_isset($_GET, $ansat_til, 'ansat_til');
$projekt_til  = if_isset($_GET, $projekt_til, 'projekt_til');
$regnaar      = if_isset($_GET, $regnaar, 'regnaar');
$afd          = if_isset($_GET, $afd, 'afd');
$simulering   = if_isset($_GET, $simulering, 'simulering');
$lagerbev     = if_isset($_GET, $lagerbev, 'lagerbev');
#############

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
} elseif ($submit == 'regnskabbasis') {
	header("Location: regnskabbasis.php?regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&aar_fra=$aar_fra&aar_til=$aar_til&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&rapportart=$rapportart");
	exit();
} elseif (isset($bankReconcile) && $bankReconcile) {
	header("Location: bankReconcile.php?regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&aar_fra=$aar_fra&aar_til=$aar_til&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&rapportart=$rapportart");
	exit();
} else {
	
	#########
	if ($submit === 'kontokort') {
        $params = http_build_query([
            'regnaar'     => $regnaar,
            'maaned_fra'  => $maaned_fra,
            'maaned_til'  => $maaned_til,
            'aar_fra'     => $aar_fra,
            'aar_til'     => $aar_til,
            'dato_fra'    => $dato_fra,
            'dato_til'    => $dato_til,
            'konto_fra'   => $konto_fra,
            'konto_til'   => $konto_til,
            'rapportart'  => $rapportart,
            'ansat_fra'   => $ansat_fra,
            'ansat_til'   => $ansat_til,
            'afd'         => $afd,
            'projekt_fra' => $projekt_fra,
            'projekt_til' => $projekt_til,
            'simulering'  => $simulering,
            'lagerbev'    => $lagerbev,
        ]);
        header("Location: kontokort_standalone.php?$params");
        exit();
    }elseif($submit === 'kontokort_moms') {
		$params = http_build_query([
			'regnaar'     => $regnaar,
			'maaned_fra'  => $maaned_fra,
			'maaned_til'  => $maaned_til,
			'aar_fra'     => $aar_fra,
			'aar_til'     => $aar_til,
			'dato_fra'    => $dato_fra,
			'dato_til'    => $dato_til,
			'konto_fra'   => $konto_fra,
			'konto_til'   => $konto_til,
			'rapportart'  => $rapportart,
			'ansat_fra'   => $ansat_fra,
			'ansat_til'   => $ansat_til,
			'afd'         => $afd,
			'projekt_fra' => $projekt_fra,
			'projekt_til' => $projekt_til,
			'simulering'  => $simulering,
			'lagerbev'    => $lagerbev,
		]);
		header("Location: kontokort_moms_standalone.php?$params");
		exit();
	}

	#########
	include("../includes/row-hover-style.js.php");
	include("rapport_includes/$submit.php");
}
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

<script>
function updateFinancialYear() {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'rapport.php';
    
    var regnaarInput = document.createElement('input');
    regnaarInput.type = 'hidden';
    regnaarInput.name = 'regnaar';
    regnaarInput.value = document.querySelector('select[name="regnaar"]').value;
    form.appendChild(regnaarInput);
    
    var updateInput = document.createElement('input');
    updateInput.type = 'hidden';
    updateInput.name = 'update_financial_year';
    updateInput.value = '1';
    form.appendChild(updateInput);
    
    document.body.appendChild(form);
    form.submit();
}

document.addEventListener('DOMContentLoaded', function() {
    var regnaarSelect = document.querySelector('select[name="regnaar"]');
    if (regnaarSelect) {
        regnaarSelect.addEventListener('change', updateFinancialYear);
    }
});
</script>
</body>
</html>





