<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/saft.php --- patch 4.0.8 --- 2024-01-28 ---
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
// 20240128 PHR Changed "AND transdate <= '$startDate'" to "AND transdate < '$startDate'" as opening balance 
// included day 1.

@session_start();
$s_id = session_id();
$css = "../css/standard.css";

$title = "SAF-T Finance";

include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

global $db;
global $bruger_id;
global $md, $menu;
global $top_bund;
global $bgcolor, $bgcolor4, $bgcolor5;
global $sprog_id;

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
$filePath = "";
$fileName = "";
$fileSize = "";
$fileSizeKb = "";
$fileExist = false;
$fileCreatedMessage = "";
$kontobeskrivelse = null;
$kontotype = null;
$openingDbCr = null;
$closingDbCr = null;
$standardKontonr = null;
$regnskabsaarStartDato = "";
$regnskabsaarStartmaaned = "";
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

$AuditFileDateCreated = date("Y-m-d");

$AuditFileVersion = "1.0";
$SoftwareCompanyName = "Saldi.dk ApS";
$SoftwareID = "Saldi";
$AddressType = "StreetAddress";
$TaxType = "VAT";
$TaxAccountingBasis = "A"; // The only valid value is "A" (Accounting)

/**
 * Function that convert countryname to taxauthority
 * @param string $NameOfCountry The name of the country
 * @return string Return taxauthority string
 */
function TaxAuthorityName(string $NameOfCountry)
{
	$TaxAuthorityName = '';
	switch ($NameOfCountry) {
		case "Denmark":
			$TaxAuthorityName = "Skat";
			break;
		case "Norway":
			$TaxAuthorityName = "Skatteetaten";
			break;
		case "Switzerland":
			$TaxAuthorityName = "FTA/ESTV";
			break;
		default:
			$TaxAuthorityName = "Skat";
	}
	return $TaxAuthorityName;
}

/**
 * Function that convert countryname to ISO 4217 currencycode
 * @param string $NameOfCountry The name of the country
 * @return string Returns the ISO 4217 currencycode
 */
function defaultCurrency(string $NameOfCountry)
{
	$currencyCode = '';
	switch ($NameOfCountry) {
		case "Denmark":
			$currencyCode = "DKK";
			break;
		case "Norway":
			$currencyCode = "NOK";
			break;
		case "Switzerland":
			$currencyCode = "CHF";
			break;
		default:
			$currencyCode = "DKK";
	}
	return $currencyCode;
}

/**
 * Function that will convert countryname to countrycode
 * @param string $NameOfCountry The name of the country
 * @return string Returns the countrycode
 */
function countryCode(string $NameOfCountry)
{
	$countryCode = '';
	switch ($NameOfCountry) {
		case "Denmark":
			$countryCode = "DK";
			break;
		case "Norway":
			$countryCode = "NO";
			break;
		case "Switzerland":
			$countryCode = "CH";
			break;
		default:
			$countryCode = "DK";
	}
	return $countryCode;
}

/**
 * Function that convert cipcode to ISO 3166-2 codes for denmark
 * If other countryname, regionnumber return 'NA'
 * @param int $cipcode Danish cipcode number
 * @param string $NameOfCountry Countryname
 * @return string Return ISO 3166-2 region code
 */
function regionNumber(int $cipcode, string $NameOfCountry)
{
	$region = '';
	if ($NameOfCountry != 'Denmark') {
		$region = '';
	} else {
		$HovedstadenRange1 = range(1, 2635);
		$HovedstadenRange2 = range(2650, 2665);
		$HovedstadenRange3 = range(2700, 3670);
		$HovedstadenRange4 = range(3700, 3790);
		$HovedstadenRange5 = range(4050, 4050);
		$SjaellandRange1 = range(2640, 2644);
		$SjaellandRange2 = range(2670, 2690);
		$SjaellandRange3 = range(4000, 4040);
		$SjaellandRange4 = range(4060, 4990);
		$SyddanmarkRange1 = range(5000, 6870);
		$SyddanmarkRange2 = range(7000, 7120);
		$SyddanmarkRange3 = range(7173, 7260);
		$SyddanmarkRange4 = range(7300, 7323);
		$MidtjyllandRange1 = range(6880, 6990);
		$MidtjyllandRange2 = range(7130, 7171);
		$MidtjyllandRange3 = range(7270, 7280);
		$MidtjyllandRange4 = range(7330, 7680);
		$MidtjyllandRange5 = range(7790, 7884);
		$MidtjyllandRange6 = range(8000, 8990);
		$NordjyllandRange1 = range(7700, 7770);
		$NordjyllandRange2 = range(7900, 7990);
		$NordjyllandRange3 = range(9000, 9990);

		switch (true) {
			case(in_array($cipcode, $HovedstadenRange1) || in_array($cipcode, $HovedstadenRange2) || in_array($cipcode, $HovedstadenRange3) || in_array($cipcode, $HovedstadenRange4) || in_array($cipcode, $HovedstadenRange5)):
				$region = 'DK-84';
				break;
			case(in_array($cipcode, $SjaellandRange1) || in_array($cipcode, $SjaellandRange2) || in_array($cipcode, $SjaellandRange3) || in_array($cipcode, $SjaellandRange4)):
				$region = 'DK-85';
				break;
			case(in_array($cipcode, $SyddanmarkRange1) || in_array($cipcode, $SyddanmarkRange2) || in_array($cipcode, $SyddanmarkRange3) || in_array($cipcode, $SyddanmarkRange4)):
				$region = 'DK-83';
				break;
			case(in_array($cipcode, $MidtjyllandRange1) || in_array($cipcode, $MidtjyllandRange2) || in_array($cipcode, $MidtjyllandRange3) || in_array($cipcode, $MidtjyllandRange4) || in_array($cipcode, $MidtjyllandRange5) || in_array($cipcode, $MidtjyllandRange6)):
				$region = 'DK-82';
				break;
			case(in_array($cipcode, $NordjyllandRange1) || in_array($cipcode, $NordjyllandRange2) || in_array($cipcode, $NordjyllandRange3)):
				$region = 'DK-81';
				break;
			default:
				$region = 'NA';
		}
	}
	return $region;
}

/**
 * Function that split address into street and number   
 * return an array:
 * Full address [0]
 * Address name [1]
 * Address number [2]
 * @param mixed $FullAddress The full length address string
 * @return array Returns an array with full address([0]), address street name([1]) and address street number([2])
 */
function splitAddress($FullAddress)
{
	if (preg_match('/([^\d]+)\s?(.+)/i', $FullAddress, $result)) {
		if (preg_match('/^\pL+$/u', $result[2])) {
			$result[1] = $FullAddress;
			$result[2] = '';
			return $result;
		}
		return $result;
	}
	return $FullAddress;
}

/**
 * Function that split a fullname into firstname and surname
 * return a array
 * splitName('Fullname')[0]; -> firstname
 * splitName('Fullname')[1]; -> surname
 * @param string $ContactName the full name that you would split
 * @return array Return an array with firstname([0]) and lastname([1])
 */
function splitName(string $ContactName)
{
	$names = preg_split('/\s+/', $ContactName);
	if (count($names) != 1) {
		if (count($names) > 2) {
			$result[1] = end($names);
			$firstNames = array_slice($names, 0, -1);
			$result[0] = implode(" ", $firstNames);
		} else {
			$result[0] = $names[0];
			$result[1] = $names[1];
		}
	} else {
		$result[0] = $names[0];
		$result[1] = 'NA';
	}
	return $result;
}

/************************************************************************** */
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

/*********************************************************************************** */
// Functions that will check if accountnumber exist

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
		return "$mapToNumber " . findtekst(3041, $sprog_id) . "";
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

for ($x = 0; $x < count($csv_kontoplan) - 1; $x++) { // -1 is added because of space in the end of the csv-file
	$csv_kontonr[$x] = $csv_kontoplan[$x][0];
	$csv_kontobeskrivelse[$x] = $csv_kontoplan[$x][1];
	$csv_kontotype[$x] = $csv_kontoplan[$x][2];
	$csv_kontotypename[$x] = csvTypes($csv_kontotype[$x]);
	$csv_momssats[$x] = $csv_kontoplan[$x][3];
}
$csv_kontoantal = $x;
$csv_kontonrOID = implode(',', $csv_kontonr);

/*********************************************************************************************** */

// SOFTWARE-VERSION
$qtxt = "SELECT box1 FROM grupper WHERE art = 'VE'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$SoftwareVersion = $r['box1'];
}

// COMPANY
$qtxt = "SELECT firmanavn, addr1, addr2, bynavn, postnr, land, kontakt, tlf, fax, email, web, bank_navn, bank_reg, bank_konto, cvrnr FROM adresser WHERE art = 'S'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$firmanavn = $r['firmanavn'];
	$Address = $r['addr1'];
	$StreetName = trim(splitAddress($Address)[1]);
	$StreetNumber = splitAddress($Address)[2];
	$AdditionalAddressDetail = $r['addr2'];
	$City = $r['bynavn'];
	$PostalCode = $r['postnr'];
	$CountryName = $r['land'];
	if (!$CountryName)
		$CountryName = 'Denmark';
	$Region = regionNumber($PostalCode, $CountryName);
	$Country = countryCode($CountryName);
	$DefaultCurrencyCode = defaultCurrency($CountryName);
	$Contact = $r['kontakt'];
	$PhoneNumber = $r['tlf'];
	$FaxNumber = $r['fax'];
	$Email = $r['email'];
	$WebSite = $r['web'];
	$BankAccountName = $r['bank_navn'];
	$BankRegNumber = $r['bank_reg'];
	$BankAccountNumber = $r['bank_konto'];
	$TaxRegistrationNumber = $r['cvrnr'];
	$TaxAuthority = TaxAuthorityName($CountryName);
}

// REGISTRATION-NUMBER
$qtxt = "SELECT var_value FROM settings WHERE var_name = 'globalId'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$RegistrationNumber = $r['var_value'];
}

// Ejer af firma 
$qtxt = "SELECT navn, initialer FROM ansatte WHERE id = '1'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$ContactName = $r['navn'];
	$ContactInitials = $r['initialer'];
	$ContactPersonName = splitName($ContactName)[0];
	$ContactLastName = splitName($ContactName)[1];
}

// Ansatte som er logget ind
$qtxt = "SELECT * FROM ansatte WHERE id = $bruger_id";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$UserID = $r['nummer'];
}

if (empty($ContactName))
	$ContactName = "NA";
if (empty($ContactInitials))
	$ContactInitials = "NA";
if (empty($ContactPersonName))
	$ContactPersonName = "NA";
if (empty($ContactLastName))
	$ContactLastName = "NA";
if (empty($UserID))
	$UserID = $bruger_id; //CHECK OM bruger_id er userID når man er logget ind!!!!

// GENERAL-LEDGER-ACCOUNTS
$x = 0;
$query = db_select("SELECT map_to, STRING_AGG(kontonr::varchar, ',') AS kontonr
FROM kontoplan
WHERE regnskabsaar = '$regnaar' AND (kontotype = 'D' OR kontotype = 'S') AND map_to IN ($csv_kontonrOID)
GROUP BY map_to
ORDER BY map_to", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($query)) {
	$x++;
	$kontonr[$x] = $r['kontonr'];
	$kontotype[$x] = "GL"; // The only valid value is "GL" (General Ledger)
	$standardKontonr[$x] = $r['map_to'];
	$key = array_search($standardKontonr[$x], $csv_kontonr);
	$kontobeskrivelse[$x] = $csv_kontobeskrivelse[$key];
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
$kontoantal_check = 0; // $x

if ($kontoantal_check <= 0) {
	$standardKontoCheck = true;
}

$regnskabsaarStartDato = $startaar . '-' . $regnskabsaarStartmaaned . '-01';
for ($x = 1; $x <= $kontoantal; $x++) {
	$qtxt = "SELECT sum(debet) AS debet, sum(kredit) AS kredit FROM transaktioner WHERE transdate >= '$regnskabsaarStartDato' AND transdate < '$startDate' AND kontonr IN ($kontonr[$x])";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$debetStart[$x] = $r['debet'];
		$kreditStart[$x] = $r['kredit'];
		$openingBalance[$x] = $debetStart[$x] - $kreditStart[$x];
		$openingDbCr[$x] = number_format(round($openingBalance[$x], 2), 2, '.', '');
	}

	// echo "kontonr: " . $kontonr[$x] . " openingDbCr: " . $openingDbCr[$x] . '<br>';
}

for ($x = 1; $x <= $kontoantal; $x++) {
	$qtxt = "SELECT sum(debet) AS debet,sum(kredit) AS kredit FROM transaktioner WHERE transdate >= '$regnskabsaarStartDato' AND transdate <= '$endDate' AND kontonr IN ($kontonr[$x])";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$debetSlut[$x] = $r['debet'];
		$kreditSlut[$x] = $r['kredit'];
		$closingBalance[$x] = $debetSlut[$x] - $kreditSlut[$x];
		$closingDbCr[$x] = number_format(round($closingBalance[$x], 2), 2, '.', '');
	}

	// echo "kontonr: " . $kontonr[$x] . ' closingDbCr: ' . $closingDbCr[$x] . '<br>';
}

$kontobeskrivelseString = serialize($kontobeskrivelse);
$kontotypeString = serialize($kontotype);
$openingDbCrString = serialize($openingDbCr);
$closingDbCrString = serialize($closingDbCr);
$standardKontonrString = serialize($standardKontonr);



// $arr = (json_encode(array_map(null, $kontonr, $openingDbCr, $closingDbCr)));
// echo "<pre>";
// print_r(json_decode($arr));
// echo "</pre>";


/************************************************************ */


/**
 * Find all files with .xml in folder and convert to four
 * variables:
 * $filePath, $fileName, $fileSize, $fileSizeKb
 */
$files = glob('../temp/' . $db . '/financial/*xml');
foreach ($files as $file) {
	$filePath = $file;
	$fileName = basename($file);
	$fileSize = filesize($file);
	$fileSizeKb = round(($fileSize / 1024), 2) . " KB";
}

/**
 * Get DateTime from filename and format to readable string
 */
if (file_exists($filePath)) {
	$fileExist = true;
	$SplitFileName = explode("_", $fileName);
	$DateTimeString = explode(".", $SplitFileName[2]);
	$d = DateTime::createFromFormat('YmdHis', $DateTimeString[0]);
	$formatedDate = $d->format('d-m-Y H:i:s');
}

/**
 * Here we set filename and filepath into variables 
 */
if (isset($_SESSION['fileName']) && isset($_SESSION['filePath'])) {
	$fileName = $_SESSION['fileName'];
	$filePath = $_SESSION['filePath'];
	$fileCreatedMessage = $_SESSION['fileMessage'];
	unset($_SESSION['fileName'], $_SESSION['filePath'], $_SESSION['fileMessage']);
}

if ($rapportart == "saft")
	$newTitle = "SAF-T Finance";
if ($menu == 'T') {
	$title = "Rapport • $newTitle";

	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">";
	print "<div class=\"headerbtnLft headLink\"><a href=rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=&ansat_til=&projekt_fra=&projekt_til=&simulering=&lagerbev= accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst(30, $sprog_id) . "</a></div>"; // &ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev
	print "<div class=\"headerTxt\">$title</div>";
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
	print "</div>";
	print "<div class='content-noside'>";
	print "<table class='dataTable' border='0' cellspacing='1' width='100%'>";
} elseif ($menu == 'S') {
	include("../includes/sidemenu.php");
} else {
	print "<table width=100% cellpadding=\"0\" cellspacing=\"1px\" border=\"0\" valign = \"top\" align='center'> ";
	print "<tr><td height=\"8\" colspan=\"2\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><a accesskey=L href=\"rapport.php?rapportart=$rapportart&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=&ansat_til=&projekt_fra=&projekt_til=&simulering=&lagerbev=\">Luk</a></td>"; // &ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev
	print "<td width=\"80%\" $top_bund> Rapport - $newTitle </td>";
	print "<td width=\"10%\" $top_bund></td>";
	print "</tbody></table>";
	print "</td></tr>";
}

/**
 * Popup message
 */
$downloadMessage = "" . findtekst(3052, $sprog_id) . "";
print "<div class=\"popup\"><span class=\"popuptext\" id=\"createMessage\">$fileCreatedMessage</span><span class=\"popuptext\" id=\"downloadMessage\"></span></div>";


print "<table class=\"saftHeader\">\n";
print "<tr><td rowspan=\"3\" class=\"saftTitle\">$newTitle</td><td>Regnskabs&aring;r</td><td>$regnaar.</td></tr>\n";
if ($startdato < 10)
	$startdato = "0" . $startdato * 1;
print "<tr><td rowspan=\"2\">Periode</td><td>Fra " . $startdato . ". $mf $aar_fra</td></tr>\n";
print "<tr><td>Til " . $slutdato . ". $mt $aar_til</td></tr>";
print "<tr><td colspan=\"3\" class=\"saftFirmName\">$firmanavn</td>\n";
if ($standardKontoCheck != true) {
	print "<tr><td colspan=\"3\"><hr></td></tr>";
	print "<tr><td colspan=\"3\"><h2>" . findtekst(3027, $sprog_id) . "<h2></td></tr>";
	if ($kontoantal_check <= 1) {
		print "<tr><td colspan=\"3\" style=\"padding-bottom:5px;\">" . findtekst(3045, $sprog_id) . "</td></tr>";
	} else {
		print "<tr><td colspan=\"3\" style=\"padding-bottom:5px;\">" . findtekst(3046, $sprog_id) . " <b>$kontoantal_check</b> " . findtekst(3030, $sprog_id) . "</td></tr>";
	}
	print "<tr><td colspan=\"3\" style=\"padding-bottom:5px;\">" . findtekst(3031, $sprog_id) . " <mark class=\"mark\"><b>" . findtekst(3032, $sprog_id) . "</b></mark> " . findtekst(3033, $sprog_id) . " <mark class=\"mark\"><b>" . findtekst(3034, $sprog_id) . "</b></mark> " . findtekst(3035, $sprog_id) . " <a href=\"../systemdata/diverse.php?sektion=div_io\" style=\"color:blue;\">Her</a></td></tr>";
	print "<tr><td colspan=\"3\" style=\"padding-bottom:5px;\">" . findtekst(3036, $sprog_id) . " <mark class=\"mark\"><b>" . findtekst(3037, $sprog_id) . "</b></mark>. " . findtekst(3038, $sprog_id) . " <mark class=\"mark\"><b>" . findtekst(3039, $sprog_id) . "</b></mark>.</td></tr>";
	print "<tr><td colspan=\"3\" style=\"padding-bottom:5px;\">" . findtekst(3040, $sprog_id) . ".</td></tr>";
}
print "</table>\n";

$showXMLFile = "" . findtekst(3049, $sprog_id) . ""; // Vis XML fil
$closeXMLFile = "" . findtekst(3050, $sprog_id) . ""; // Luk XML fil

if ($standardKontoCheck) {
	print "<table class=\"saftTable1\">\n";
	print "<tr><td colspan=\"2\">";
	print "" . findtekst(3047, $sprog_id) . "";
	print "</td><td>&nbsp;</td><td>&nbsp;</td></tr>\n";
	print "<tr><td colspan=\"4\"></td></tr>";
	print "<tr><td>";
	print "<form method=\"post\" style=\"margin-bottom: 0;\" action=\"saftCreator.php\">";
	print "<input type='hidden' name='regnaar' value='" . $regnaar . "'>";
	print "<input type='hidden' name='maaned_fra' value='" . $mf . "'>";
	print "<input type='hidden' name='maaned_til' value='" . $mt . "'>";
	print "<input type='hidden' name='aar_fra' value='" . $aar_fra . "'>";
	print "<input type='hidden' name='aar_til' value='" . $aar_til . "'>";
	print "<input type='hidden' name='startmaaned' value='" . $startmaaned . "'>";
	print "<input type='hidden' name='slutmaaned' value='" . $slutmaaned . "'>";
	print "<input type='hidden' name='dato_fra' value='" . $dato_fra . "'>";
	print "<input type='hidden' name='dato_til' value='" . $dato_til . "'>";
	print "<input type='hidden' name='konto_fra' value='" . $konto_fra . "'>";
	print "<input type='hidden' name='konto_til' value='" . $konto_til . "'>";
	print "<input type='hidden' name='rapportart' value='" . $rapportart . "'>";
	print "<input type='hidden' name='kontoantal' value='" . $kontoantal . "'>";
	print "<input type='hidden' name='kontobeskrivelseString' value='" . $kontobeskrivelseString . "'>";
	print "<input type='hidden' name='kontotypeString' value='" . $kontotypeString . "'>";
	print "<input type='hidden' name='openingDbCrString' value='" . $openingDbCrString . "'>";
	print "<input type='hidden' name='closingDbCrString' value='" . $closingDbCrString . "'>";
	print "<input type='hidden' name='standardKontonrString' value='" . $standardKontonrString . "'>";
	print "<span><button type=\"submit\">" . findtekst(3048, $sprog_id) . "</button></span>";
	print "</form></td>";
	print "<td>&nbsp;</td><td></td>";
	print "<td>&nbsp;</td></tr>\n";
	print "</table>\n";

	if ($fileExist) {
		print "<table class='saftTable2'>";
		print "<tr><th>File name</th><th>Size</th><th>Date and time of creation</th><th>&nbsp;</th>";
		print "<tr><td>$fileName</td><td>$fileSizeKb</td><td>$formatedDate</td><td><button id='download' onclick='downloadFile(\"$filePath\", \"$fileName\")'>Download</button></td></tr>"; // <a href='$filePath' download><button>Download</button></a> 
		print "</table>";
		print "<table class=\"saftTable3\">";
		print "<tr><td>";
		print "<button onclick=\"showXML()\" id=\"showXML\">$showXMLFile</button>";
		print "</td></tr></table>";
	}

	print "<div id=\"xmlFile\">";
	if ($filePath) {
		$newFilePath = str_replace(' ', '%20', $filePath);
		print '<pre data-src=' . $newFilePath . '></pre>';
	}
	print "</div>";
} else {
	print "<table style=\"width:100%;\">";
	print "<tr><th>KONTONUMMER</th><th>MAP TIL</th><th>KONTONAVN</th><th>TYPE</th><th>MOMS</th><th>VAERDI</th></tr>";
	for ($x = 1; $x <= $kontoantal_check; $x++) {
		($linjebg != $bgcolor4) ? $linjebg = $bgcolor4 : $linjebg = $bgcolor;
		($mapto_check[$x] > '') ? $checkColor = "style=\"color: red;\"" : $checkColor = "";
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href=\"../systemdata/kontokort.php?id=$id_check[$x]&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&aar_fra=$aar_fra&aar_til=$aar_til&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&rapportart=$rapportart\">" . $kontonr_check[$x] . "</a></td>";
		print "<td $checkColor>" . $mapto_check[$x] . "</td>";
		print "<td>" . $beskrivelse_check[$x] . "</td>";
		print "<td>" . $kontotypenavn_check[$x] . "</td>";
		print "<td>" . $moms_check[$x] . "</td>";
		print "<td align = 'right'>" . dkdecimal(round($saldo_check[$x], 0), 0) . "</td>";
		print "</tr>";
	}
	print "</table>";
}

/****************************************************************************************************** */
// STANDARD KONTOPLAN
$showStandardAccountPlan = "" . findtekst(3043, $sprog_id) . ""; // Vis Standard Kontoplan
$closeStandardAccountPlan = "" . findtekst(3044, $sprog_id) . ""; // Luk Standard Kontoplan

if ($standardKontoCheck != true) {
	print "<hr style=\"border: 1px solid #9a9a9a;\">";
	print "<div class=\"rbButtonSpace\"><button onclick=\"showStandardKontoplan()\" id=\"showStandardKontoplan\">$showStandardAccountPlan</button></div>";
	print "<div id=\"standardKontoplan\">";
	print "<h2 style=\"text-align: center;\">" . findtekst(3042, $sprog_id) . "</h2>";
	print "<table style='width:100%;'>";
	print "<tr><th>Kontonummer</th><th>Kontonavn</th><th>Type</th><th>Moms</th></tr>";
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

?>
<!-- Javascript for viewing xml file on page -->
<script src="../javascript/prism.js"></script>

<script>
	// Javascript that will download xml file
	// function downloadFile(url, fileName) {
	// 	fetch(url, {
	// 			method: 'get',
	// 			mode: 'no-cors',
	// 			referrerPolicy: 'no-referrer'
	// 		})
	// 		.then(res => res.blob())
	// 		.then(res => {
	// 			const aElement = document.createElement('a');
	// 			aElement.setAttribute('download', fileName);
	// 			const href = URL.createObjectURL(res);
	// 			aElement.href = href;
	// 			aElement.setAttribute('target', '_blank');
	// 			aElement.click();
	// 			URL.revokeObjectURL(href);
	// 		}).then(res => {
	// 			document.getElementById('downloadMessage').innerHTML = "Fil downloaded";
	// 			setTimeout(function() {
	// 				document.getElementById('downloadMessage').innerHTML = "";
	// 			}, 5000);
	// 		}).catch((err) => {
	// 			console.log(err);
	// 		})
	// };

	// Javascript that will download xml file
	function downloadFile(url, fileName) {
		const downloadMessage = "<?php echo $downloadMessage; ?>";
			fetch(url, {
					method: 'get',
					mode: 'no-cors',
					referrerPolicy: 'no-referrer'
				})
				.then(res => res.blob())
				.then(res => {
					const aElement = document.createElement('a');
					aElement.setAttribute('download', fileName);
					const href = URL.createObjectURL(res);
					aElement.href = href;
					aElement.setAttribute('target', '_blank');
					aElement.click();
					URL.revokeObjectURL(href);
				}).then(res => {
					let message = document.getElementById('downloadMessage');
					// message.style.display = "block";
					message.innerHTML = downloadMessage;
					message.classList.toggle("show");
					setTimeout(function() {
						message.classList.toggle("show");
						// message.style.display = 'none';
						document.getElementById('downloadMessage').innerHTML = "";
					}, 5000);
				}).catch((err) => {
					console.log(err);
				})
		}
	// Javascript function that toggles view of XML file and change button name
	function showXML() {
		const show = "<?php echo $showXMLFile; ?>";
		const close = "<?php echo $closeXMLFile; ?>";
		var x = document.getElementById('xmlFile');
		if (x.style.display === "block") {
			x.style.display = "none";
			document.getElementById('showXML').innerText = show;
		} else {
			x.style.display = "block";
			document.getElementById('showXML').innerText = close;
		}
	};
</script>
<script>
	let fcm = '<?php echo (!empty($fileCreatedMessage)); ?>';
	if (fcm) {
		let message = document.getElementById('createMessage');
		message.classList.toggle("show");
		message.onclick = setTimeout(function() {
			message.classList.toggle("show");
			message.style.display = 'none';
		}, 5000);
	}
</script>
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
<?php
if ($menu == 'T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}
print "<!--Function regnskab slut-->\n";
// }

?>
