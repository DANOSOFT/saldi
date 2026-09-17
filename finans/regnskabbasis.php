<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/basiRegnskab.php --- patch 5.0.0 --- 2026-06-18 ---
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20250503 LOE reordered mix-up text_id from tekster.csv in findtekst()
// 20260618 LOE Added sticky header for S-mode and added a scrollable table for the data table in S-mode co-authored by Aj

@session_start();
$s_id = session_id();
$css = "../css/standard.css";

$title = "SAF-T Finance";

include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include_once '../includes/topline_settings.php';

global $csv;
global $db;
global $bruger_id;
global $md, $menu;
global $top_bund;
global $bgcolor, $bgcolor4, $bgcolor5;
global $sprog_id;
global $topStyle;
global $buttonStyle;

$regnaar = "";
$maaned_fra = "";
$maaned_til = "";
$aar_fra = "";
$aar_til = "";
$dato_fra = "";
$dato_til = "";
$konto_fra = "";
$konto_til = "";
$rapportart = "";
$startmaaned = null;
$slutmaaned = null;
$startdato = null;
$slutdato = null;
$startaar = null;
$slutaar = null;
$newTitle = "";
$standardKontonr = null;
$regnskabsaarStartmaaned = "";
$createCsvFile = null;
$standardKontoCheck = false;


if (isset($_GET['regnaar']))
    $regnaar = $_GET['regnaar'];
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

$maaned_fra = trim($maaned_fra);
$maaned_til = trim($maaned_til);

$mf = $maaned_fra;
$mt = $maaned_til;

for ($x = 1; $x <= 12; $x++) {
    if ($maaned_fra == $md[$x]) {
        $maaned_fra = $x;
    }
    if ($maaned_til == $md[$x]) {
        $maaned_til = $x;
    }
    if (strlen($maaned_fra) == 1) {
        $maaned_fra = "0" . $maaned_fra;
    }
    if (strlen($maaned_til) == 1) {
        $maaned_til = "0" . $maaned_til;
    }
    if (strlen($dato_fra) == 1) {
        $dato_fra = "0" . $dato_fra;
    }
    if (strlen($dato_til) == 1) {
        $dato_til = "0" . $dato_til;
    }
}

$qtxt = "SELECT box1, box2, box3, box4 FROM grupper WHERE kodenr = '$regnaar' AND art = 'RA'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $startmaaned = (int) $r['box1']; //1
    $startaar = (int) $r['box2']; //2021
    $slutmaaned = (int) $r['box3']; //12
    $slutaar = (int) $r['box4']; //2021
    $slutdato = 31;
    $regnskabsaarStartmaaned = $startmaaned;
}

if ($aar_fra < $aar_til) {
    if ($maaned_til > $slutmaaned)
        $aar_til = $aar_fra;
    elseif ($maaned_fra < $startmaaned)
        $aar_fra = $aar_til;
}

if (strlen($startmaaned) == 1)
    $startmaaned = "0" . $startmaaned;
if (strlen($slutmaaned) == 1)
    $slutmaaned = "0" . $slutmaaned;
if (strlen($regnskabsaarStartmaaned) == 1)
    $regnskabsaarStartmaaned = "0" . $regnskabsaarStartmaaned;

if ($maaned_fra)
    $startmaaned = $maaned_fra;
if ($maaned_til)
    $slutmaaned = $maaned_til;
if ($dato_fra)
    $startdato = $dato_fra;
if ($dato_til)
    $slutdato = $dato_til;

while (!checkdate($startmaaned, $startdato, $startaar)) {
    $startdato = $startdato - 1;
    if ($startdato < 28)
        break 1;
}

while (!checkdate($slutmaaned, $slutdato, $slutaar)) {
    $slutdato = $slutdato - 1;
    if ($slutdato < 28)
        break 1;
}

$startDate = $aar_fra . '-' . $maaned_fra . '-' . $startdato;
$endDate = $aar_til . '-' . $maaned_til . '-' . $slutdato;

/**
 * Convert standard account-types to names
 * @param mixed $csvType One letter type
 * @return mixed Return name from type
 */
function csvTypes($csvType)
{
    $csvTypeName = '';
    switch ($csvType) {
        case "D":
            $csvTypeName = "Drift";
            break;
        case "H":
            $csvTypeName = "Overskrift";
            break;
        case "Z":
            $csvTypeName = "Sum";
            break;
        case "S":
            $csvTypeName = "Status";
            break;
    }
    return $csvTypeName;
}

/**
 * Check if a account number exist in Standard Chart of Account
 * @param mixed $mapToNumber The map to number 
 * @param mixed $standardAcountNumber Standard Account number
 * @return mixed Return either blanc if number is '0' or 'null' or the wrong number with text
 */
function accountNumberExist($mapToNumber, $standardAcountNumber)
{
    global $sprog_id;
    if ($mapToNumber == '0' || $mapToNumber == null) {
        return '';
    } else if (!in_array($mapToNumber, $standardAcountNumber)) {
        return "$mapToNumber " . findtekst('2342|eksisterer ikke', $sprog_id) . "";
    }
}

/**
 * Converts a csv file to an array
 * @param mixed $csvFile The csv file you want to convert
 * @return mixed Return an array of the csv file
 */
function csvToArray($csvFile)
{
    $file_to_read = fopen($csvFile, 'r');
    while (!feof($file_to_read)) {
        $lines[] = fgetcsv($file_to_read, 0, "\t");
    }
    fclose($file_to_read);
    return $lines;
}

// Read the kontoplan csv file into an array 
$csvFile_kontoplan = '../importfiler/kontoplan.txt';
$csv_kontoplan = csvToArray($csvFile_kontoplan);

for ($x = 0; $x < count($csv_kontoplan) - 1; $x++) { // -1 is added .. space in the end of csv-file
    $csv_kontonr[$x] = $csv_kontoplan[$x][0];
    $csv_kontobeskrivelse[$x] = $csv_kontoplan[$x][1];
    $csv_kontotype[$x] = $csv_kontoplan[$x][2];
    $csv_kontotypename[$x] = csvTypes($csv_kontotype[$x]);
    $csv_momssats[$x] = $csv_kontoplan[$x][3];
}
$csv_kontoantal = $x;
$csv_kontonrOID = implode(',', $csv_kontonr);

// COMPANY
$qtxt = "SELECT firmanavn, cvrnr FROM adresser WHERE art = 'S'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $firmanavn = $r['firmanavn'];
    $TaxRegistrationNumber = $r['cvrnr'];
}

// KONTOPLAN
$x = 0;
$query = db_select("SELECT map_to, SUM(saldo) AS saldo
FROM kontoplan
WHERE regnskabsaar = '$regnaar' AND (kontotype = 'D' OR kontotype = 'S') AND map_to IN ($csv_kontonrOID)
GROUP BY map_to
ORDER BY map_to", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($query)) { // map_to IS NOT NULL AND map_to <> '0'
    $x++;
    $standardKontonr[$x] = $r['map_to'];
    $key = array_search($standardKontonr[$x], $csv_kontonr);
    $beskrivelse[$x] = $csv_kontobeskrivelse[$key];
    $saldo[$x] = $r['saldo'];
}
$kontoantal = $x;

// CHECK IF STANDARD ACOUNTNUMBER EXIST
$x = 0;
$query = db_select("SELECT id, kontonr, beskrivelse, kontotype, moms, saldo, map_to 
FROM kontoplan
WHERE regnskabsaar = '$regnaar' AND (kontotype = 'D' OR kontotype = 'S') AND (saldo != '0' OR primo != '0') AND (map_to IS NULL OR map_to = '0' OR map_to NOT IN ($csv_kontonrOID))
ORDER BY kontonr", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($query)) {
    $x++;
    $id_check[$x] = $r['id'];
    $kontonr_check[$x] = $r['kontonr'];
    $beskrivelse_check[$x] = $r['beskrivelse'];
    $kontotype_check[$x] = $r['kontotype'];
    $kontotypenavn_check[$x] = csvTypes($kontotype_check[$x]);
    $moms_check[$x] = $r['moms'];
    $saldo_check[$x] = $r['saldo'];
    $mapto_check[$x] = accountNumberExist($r['map_to'], $csv_kontonr);
}
$kontoantal_check = $x; // $x

$csvfile = "../temp/$db/regnskabbasis.csv";

if ($kontoantal_check <= 0) {
    $standardKontoCheck = true;
    $createCsvFile = "<a href='$csvfile'>csv</a>";
}

if ($rapportart == "regnskabbasis")
    $newTitle = findtekst('2326|Regnskab Basis', $sprog_id);
if ($menu == 'T') {
    $title = "Rapport • $newTitle";

    include_once '../includes/top_header.php';
    include_once '../includes/top_menu.php';
    print "<div id=\"header\">";
    print "<div class=\"headerbtnLft headLink\"><a href=\"rapport.php?rapportart=regnskabbasis&regnaar=$regnaar&maaned_fra=$mf&aar_fra=$aar_fra&maaned_til=$mt&aar_til=$aar_til&dato_fra=$startdato&dato_til=$slutdato&konto_fra=$konto_fra&konto_til=$konto_til\" accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst('30|Tilbage', $sprog_id) . "</a></div>"; // &ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev
    print "<div class=\"headerTxt\">$title</div>";
    print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
    print "</div>";
    print "<div class='content-noside'>";
    print "<table class='dataTable' border='0' cellspacing='1' width='100%'>";
    } elseif ($menu == 'S') {
        $backUrl = "rapport.php?rapportart=regnskabbasis&regnaar=$regnaar&maaned_fra=$mf&aar_fra=$aar_fra&maaned_til=$mt&aar_til=$aar_til&dato_fra=$startdato&dato_til=$slutdato&konto_fra=$konto_fra&konto_til=$konto_til";
        
        $tilbage_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
        
        // Get CVR number
        $query = db_select("select cvrnr from adresser where art='S'", __FILE__ . " linje " . __LINE__);
        if ($row = db_fetch_array($query))
            $cvrnr = $row['cvrnr'];
        
        // Format dates
        if ($startdato < 10) $startdato_fmt = "0" . (int)$startdato;
        else $startdato_fmt = $startdato;
        if ($slutdato < 10) $slutdato_fmt = "0" . (int)$slutdato;
        else $slutdato_fmt = $slutdato;

        print "<div style=\"position: sticky; top: 0; z-index: 100; background-color: $bgcolor;\">";

        // Internal padding shell to align the elements cleanly with the rest of the application grid
        print "<div>";

        // Action Top Menu Bar

        print "<div style=\"position: sticky; top: 0; z-index: 100;\">";
        print "<table width='100%' cellpadding='0' cellspacing='4' border='0'><tbody>";
        print "<tr>";
        print "<td width=\"5%\" align='center'><a href=\"javascript:confirmClose('$backUrl','')\" accesskey=L style=\"text-decoration: none;\">";
        print "<button class='headerbtn' type='button' style='$buttonStyle; width: 100%; display: flex; align-items: center; gap: 5px;' onMouseOver=\"this.style.cursor = 'pointer'\">";
        print "$tilbage_icon " . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>";
        print "<td width='75%' align='center' style='$topStyle'>" . findtekst('2326|Regnskab Basis', $sprog_id) . " ". findtekst('895|Finansrapport', $sprog_id) . "</td>";
        print "<td width='5%' align='center' style='$topStyle'>&nbsp;</td>";
        print "</tr>";
        print "</tbody></table>";
        print "</div>";
        
        // Customer Info Header Block
        print "<table width='100%' cellpadding='4' cellspacing='0' border='0' style='margin: 0 0 0 0;'>";
        print "<tr>";
        print "<td width='50%' valign='top' align='left'>";
        print "<div class='saftTitle' style='font-size: 1.4em;'>" . $newTitle . "</div>";
        print "<div style='margin-top: 4px;' class='saftFirmName'>cvr: $cvrnr | $firmanavn</div>";
        print "</td>";
        print "<td width='50%' align='right' valign='top' style=' font-size: 0.95em;'>";
        print "<div>Regnskabsår: $regnaar</div>";
        print "<div style='margin-top: 4px;'>Periode: $startdato_fmt/$mf $aar_fra - $slutdato_fmt/$mt $aar_til</div>";
        print "</td>";
        print "</tr>";
        print "<tr><td colspan='2'></td></tr>";
        print "<tr><td colspan='2'><hr></td></tr>";
        print "</table>";
        
        print "</div>"; // Closes padding shell
        print "</div>"; // Closes sticky container
        
        // Scrollable data table
        print "<div style=\"overflow-y: auto; max-height: calc(100vh - 136px);\">";

        // Core data table initial tag
        print "<table class='dataTable' border='0' cellspacing='1' width='100%'>";
    } else {
    print "<table width=100% cellpadding=\"0\" cellspacing=\"1px\" border=\"0\" valign = \"top\" align='center'> ";
    print "<tr><td height=\"8\" colspan=\"2\">";
    print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>";
    print "<td width=\"10%\" $top_bund><a accesskey=L href=\"rapport.php?rapportart=$rapportart&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=&ansat_til=&projekt_fra=&projekt_til=&simulering=&lagerbev=\">Luk</a></td>"; // &ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev
    print "<td width=\"80%\" $top_bund> Rapport - $newTitle </td>";
    print "<td width=\"10%\" $top_bund>$createCsvFile</td>";
    print "</tbody></table>";
    print "</td></tr>";
}

// Display warning messages only in S-mode
if ($menu == 'S' && $standardKontoCheck != true) {
    print "<div style=\"margin: 10px 0;\">";
    print "<h2>" . findtekst('2328|Bemærk!', $sprog_id) . "</h2>";
    if ($kontoantal_check <= 1) {
        print "<p>" . findtekst('2329|For at udskrive en csv skal nedenstående kontonummer mappes til <u>standard kontonummer</u>.', $sprog_id) . "</p>";
    } else {
        print "<p>" . findtekst('2330|For at udskrive en csv skal de', $sprog_id) . " <b>$kontoantal_check</b> " . findtekst('2331|nedenstående kontonumre mappes til <u>standard kontonumre</u>.', $sprog_id) . "</p>";
    }
    print "<p>" . findtekst('2332|Inde i', $sprog_id) . " <mark class=\"mark\"><b>" . findtekst('2333|systemdata &#8658 > diverse &#8658 > Import & Export', $sprog_id) . "</b></mark> " . findtekst('2334|kan du under', $sprog_id) . " <mark class=\"mark\"><b>" . findtekst('2335|Indlæs/udlæs kontoplan', $sprog_id) . "</b></mark> " . findtekst('2336|Importer mappingfil til offentlig standard kontoplan', $sprog_id) . " <a href=\"../systemdata/diverse.php?sektion=div_io\" style=\"color:blue;\">".findtekst('2157|her', $sprog_id)."</a></p>";
    print "<p>" . findtekst('2337|De kontonumre som <u>ikke</u> kan mappes ændres i', $sprog_id) . " <mark class=\"mark\"><b>" . findtekst('2338|systemdata &#8658 > kontoplan', $sprog_id) . "</b></mark>. " . findtekst('2339|Her kan man under det enkelte kontonummer tilføje standard kontonummer i feltet', $sprog_id) . " <mark class=\"mark\"><b>" . findtekst('2340|Map til', $sprog_id) . "</b></mark>.</p>";
    print "<p>" . findtekst('2341|Sidst på siden kan man se en udskrift af Standard Kontoplan', $sprog_id) . ".</p>";
    print "</div>";
}


if ($standardKontoCheck) {
    print "<table style='width:100%;'>";
    print "<tr><th>".strtoupper(findtekst('136|KONTONUMMER', $sprog_id))."</th>";
    print "<th>".strtoupper(findtekst('805|KONTONAVN', $sprog_id))."</th>";
    print "<th>".strtoupper(findtekst('476|VÆRDI', $sprog_id))."</th></tr>";

    for ($x = 1; $x <= $kontoantal; $x++) {
        ($linjebg != $bgcolor5) ? $linjebg = $bgcolor5 : $linjebg = $bgcolor;
        print "<tr bgcolor=\"$linjebg\">";
        print "<td>" . $standardKontonr[$x] . "</td>";
        print "<td>" . $beskrivelse[$x] . "</td>";
        print "<td>" . round($saldo[$x], 0) . "</td>";
        print "</tr>";
    }
    print "</table>";
} else {
    print "<table style='width:100%;'>";
    print "<tr><th>".strtoupper(findtekst('136|KONTONUMMER', $sprog_id))."</th>";
    print "<th>".strtoupper(findtekst('2340|MAP TIL', $sprog_id))."</th>";
    print "<th>".strtoupper(findtekst('805|KONTONAVN', $sprog_id))."</th>";
    print "<th>TYPE</th>";
    print "<th>".strtoupper(findtekst('770|MOMS', $sprog_id))."</th>";
    print "<th>".strtoupper(findtekst('476|VÆRDI', $sprog_id))."</th></tr>";

    for ($x = 1; $x <= $kontoantal_check; $x++) {
        ($linjebg != $bgcolor4) ? $linjebg = $bgcolor4 : $linjebg = $bgcolor;
        ($mapto_check[$x] > '') ? $checkColor = "style=\"color: red;\"" : $checkColor = "";
        print "<tr bgcolor=\"$linjebg\">";
        print "<td><a href=\"../systemdata/kontokort.php?id=$id_check[$x]&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&aar_fra=$aar_fra&aar_til=$aar_til&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&rapportart=$rapportart\">" . $kontonr_check[$x] . "</a></td>";
        print "<td $checkColor>" . $mapto_check[$x] . "</td>";
        print "<td>" . $beskrivelse_check[$x] . "</td>";
        print "<td>" . $kontotypenavn_check[$x] . "</td>";
        print "<td>" . $moms_check[$x] . "</td>";
        print "<td>" . round($saldo_check[$x], 0) . "</td>";
        print "</tr>";
    }
    print "</table>";
}

if ($menu == 'S') {
    print "</table>"; // closes dataTable
  #  print "</div>"; // closes overflow wrapper
}

$BOM = "\xEF\xBB\xBF"; // UTF-8 BOM
// Here we write csv file
$csv = fopen($csvfile, "w");
fwrite($csv, $BOM);
fwrite($csv, "KONTONUMMER_" . $TaxRegistrationNumber . ";KONTONAVN_" . $TaxRegistrationNumber . ";VAERDI_" . $TaxRegistrationNumber . "\n");
for ($x = 1; $x < $kontoantal; $x++) {
    fwrite($csv, $standardKontonr[$x] . ";" . $beskrivelse[$x] . ";" . round($saldo[$x], 0) . "\n");
}
fclose($csv);

/****************************************************************************************************** */
// STANDARD KONTOPLAN
$showStandardAccountPlan = "" . findtekst(2344, $sprog_id) . ""; // Vis Standard Kontoplan
$closeStandardAccountPlan = "" . findtekst(2345, $sprog_id) . ""; // Luk Standard Kontoplan

if ($standardKontoCheck != true) {
    print "<hr style=\"border: 1px solid #9a9a9a;\">";
    print "<div class=\"rbButtonSpace\"><button onclick=\"showStandardKontoplan()\" id=\"showStandardKontoplan\">$showStandardAccountPlan</button></div>";
    print "<div id=\"standardKontoplan\">";
    print "<h2 style=\"text-align: center;\">" . findtekst('2343|Standard Kontoplan', $sprog_id) . "</h2>";
    print "<table style='width:100%;'>";
    print "<tr><th>".findtekst('136|Kontonummer', $sprog_id)."</th>";
    print "<th>".findtekst('805|Kontonavn', $sprog_id)."</th>";
    print "<th>Type</th>";
    print "<th>".findtekst('770|Moms', $sprog_id)."</th></tr>";
    for ($x = 0; $x < $csv_kontoantal; $x++) {
        ($linjebg != $bgcolor5) ? $linjebg = $bgcolor5 : $linjebg = $bgcolor;
        print "<tr bgcolor=\"$linjebg\">";
        print "<td>" . $csv_kontonr[$x] . "</td>";
        print "<td>" . $csv_kontobeskrivelse[$x] . "</td>";
        print "<td>" . $csv_kontotypename[$x] . "</td>";
        print "<td>" . $csv_momssats[$x] . "</td>";
        print "</tr>";
    }
    print "</table>";
    print "</div>";
}
/****************************************************************************************************** */

// Get kontoplan.txt and write to page for test only
// echo "<hr>";

// // Her henter vi csv fil til variable
// $csv_file_to_read = fopen('../importfiler/kontoplan.txt', 'r');
// // checker om fil eksitere
// if ($csv_file_to_read !== FALSE) {
//     // Her skriver vi csv-filen ud i tabel
//     echo "<table style='width:100%;'>\n";
//     while (($data = fgetcsv($csv_file_to_read, 0, "\t")) !== FALSE) {
//         ($linjebg != $bgcolor5) ? $linjebg = $bgcolor5 : $linjebg = $bgcolor;
//         echo "<tr bgcolor=\"$linjebg\">";
//         for ($i = 0; $i < count($data); $i++) {
//             echo "<td>" . $data[$i] . "</td>";
//         }
//         echo "</tr>\n";
//     }
//     echo "</table>\n";
//     fclose($csv_file_to_read);
// }

// $csv_kontonrOID = implode(',', $csv_kontonr);
// echo $csv_kontonrOID;

// echo $csv[0][1];
// render the array with print_r 
// echo '<pre>';
// print_r($csv_kontonr);
// echo '</pre>';
?>
<script>
    // Javascript function that toggles view of Standard Kontoplan file
    function showStandardKontoplan() {
        const show = "<?php echo $showStandardAccountPlan; ?>";
        const close = "<?php echo $closeStandardAccountPlan; ?>";
        var x = document.getElementById('standardKontoplan');
        if (x.style.display === "block") {
            x.style.display = "none";
            document.getElementById('showStandardKontoplan').innerText = show;
        } else {
            x.style.display = "block";
            document.getElementById('showStandardKontoplan').innerText = close;
        }
    }
</script>
