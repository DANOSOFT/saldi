<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/saftCashRegister.php --- patch 4.0.8 --- 2023-10-27 ---
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
//

@session_start();
$s_id = session_id();
$css = "../css/std.css";

$title = "SAF-T Cash Register";
include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

global $db;
global $bruger_id;
global $md, $menu;
global $top_bund;


$files = '';
$filePath = '';
$fileName = '';
$fileSize = '';
$fileSizeKb = '';
$fileExist = false;
$fileCreatedMessage = '';
$dateFrom = null;
$startDay = null;
$startMonth = null;
$startYear = null;
$startDatePeriod = null;
$dateTo = null;
$endDay = null;
$endMonth = null;
$endYear = null;
$endDatePeriod = null;
$startTimePeriod = null;
$endTimePeriod = null;
$empID = null;
$employees_dateOfEntry = null;
$timeOfEntry = null;
$firstName = null;
$surName = null;
$artID = null;
$dateOfEntry = null;
$artGroupID = null;
$artDesc = null;
$eventID = null;
$eventType = null;
$transID = null;
$event_empID = null;
$eventDate = null;
$eventTime = null;
$eventText = null;
$eventReport_artGroupID = null;
$artGroupNum = null;
$artGroupAmnt = null;
$paymentType = null;
$paymentNum = null;
$paymentAmnt = null;
$reportEmpPayments_empID = null;
$reportEmpPayments_paymentType = null;
$paymentEmpNum = null;
$paymentEmpAmnt = null;
$vatPerc = null;
$cashSaleAmnt = null;
$vatAmnt = null;
$vatAmntTp = null;
$cashtransaction_nr = null;
$cashtransaction_transID = null;
$transType = null;
$transAmntIn = null;
$transAmntEx = null;
$cashtransaction_amntTp = null;
$cashtransaction_empID = null;
$transDate = null;
$transTime = null;
$ctLine_nr = null;
$lineID = null;
$lineType = null;
$ctLine_artGroupID = null;
$ctLine_artID = null;
$qnt = null;
$lineAmntIn = null;
$lineAmntEx = null;
$ctLine_amntTp = null;
$vat_vatPerc = null;
$vat_vatAmnt = null;
$vat_vatAmntTp = null;
$vatBasAmnt = null;
$stringToHash = null;
$prev_stringToHash = null;
$hashString = null;
$signature_hashString = null;
$verify = null;
$payment_paymentType = null;
$paidAmnt = null;
$payment_empID = null;
$payment_curCode = null;
$basicType = null;
$basicID = null;
$predefinedBasicID = null;
$basicDesc = null;


if (isset($_POST['startDate']))
    $_SESSION['startDate'] = $_POST['startDate'];
if (isset($_POST['endDate']))
    $_SESSION['endDate'] = $_POST['endDate'];

$startDatePeriod = $_SESSION['startDate'];
$endDatePeriod = $_SESSION['endDate'];

/************************************************************************************* */

/**
 * Standart info for XML-file and companyname
 */
$auditfileVersion = "1.0";
$softwareCompanyName = "Saldi.dk ApS";
$softwareDesc = "Saldi";
$userID = $bruger_id;


/**
 * Function that convert countryname to taxauthority
 * @param string $NameOfCountry The name of the country
 * @return string Return taxauthority string
 */
function TaxAuthorityName($NameOfCountry)
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
function defaultCurrency($NameOfCountry)
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
function countryCode($NameOfCountry)
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
function regionNumber($cipcode, $NameOfCountry)
{
    $region = '';
    if ($NameOfCountry != 'Denmark') {
        $region = 'NA';
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
function splitName($ContactName)
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

/**
 * Takes a two digit month number and convert it to Danish monthname
 * @param int $monthNumber The two digit monthnumber
 * @return string The Danish montname
 */
function monthName($monthNumber)
{
    $nameOfMonth = '';
    switch ($monthNumber) {
        case '01':
            $nameOfMonth = "januar";
            break;
        case '02':
            $nameOfMonth = "februar";
            break;
        case '03':
            $nameOfMonth = "marts";
            break;
        case '04':
            $nameOfMonth = "april";
            break;
        case '05':
            $nameOfMonth = "maj";
            break;
        case '06':
            $nameOfMonth = "juni";
            break;
        case '07':
            $nameOfMonth = "juli";
            break;
        case '08':
            $nameOfMonth = "august";
            break;
        case '09':
            $nameOfMonth = "september";
            break;
        case '10':
            $nameOfMonth = "oktober";
            break;
        case '11':
            $nameOfMonth = "november";
            break;
        case '12':
            $nameOfMonth = "december";
            break;
    }
    return $nameOfMonth;
}

/**************************************************************************************************** */

/**
 * Private and public key for signing cash register data
 */
$private_key = file_get_contents('../.cert/.ht_saf-t_privateKey.pem');
$public_key = file_get_contents('../.cert/.ht_saf-t_publicCert.pem');

/**
 * Create a signature for signing data and return a base64 string
 * Takes to parameter
 * @param $data The string of data you wish to sign
 * @param $private_key The private key you want to sign the string with
 * @return string base64 encoded string
 */
function signatureString($data, $private_key)
{
    // create signature
    openssl_sign($data, $signature, $private_key, OPENSSL_ALGO_SHA512);

    // base64_encoded signature for xml-file
    return base64_encode($signature);
}

/**
 * Will take a string and hash it with sha512
 * @param $stringToHash The string you want to hash
 * @return string The hashed string
 */
function hashString($stringToHash)
{
    return hash('sha512', $stringToHash);
}

/**
 * Verify signature string from sha512 with RSA encryption 
 * Function takes 3 parameter:
 * @param $data The string of data used to generate the signature previously
 * @param $base64Signature The base64 signature string created from the private key
 * @param $publicKey The public key extracted from the private key
 * @return string A text with the result. 
 */
function verifySignatureString($data, $base64Signature, $publicKey)
{
    $verify = openssl_verify($data, base64_decode($base64Signature), $publicKey, "sha512WithRSAEncryption");

    if ($verify == 1) {
        $verifyText = "signature ok (as it should be)\n";
    } elseif ($verify == 0) {
        $verifyText = "bad (there's something wrong)\n";
    } else {
        $verifyText = "ugly, error checking signature\n";
    }
    return $verifyText;
}

/**************************************************************************************************** */

// FISCAL-YEAR
$qtxt = "SELECT box1, box2, box3, box4, kodenr FROM grupper WHERE kodenr = (SELECT max(kodenr) FROM grupper WHERE art = 'RA') AND art = 'RA'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $startFiscalMonth = (int) $r['box1']; //1
    $startFiscalYear = (int) $r['box2']; //2021
    $endFiscalMonth = (int) $r['box3']; //12
    $endFiscalYear = (int) $r['box4']; //2021
    $regnaar = (int) $r['kodenr'];
}

if (strlen($startFiscalMonth) == 1)
    $startFiscalMonth = "0" . $startFiscalMonth;
if (strlen($endFiscalMonth) == 1)
    $endFiscalMonth = "0" . $endFiscalMonth;

$lastDayOfMonth = date_create_from_format('!m', $endFiscalMonth)->format('t');

$fiscalYear = ($startFiscalYear != $endFiscalYear) ? $startFiscalYear . '-' . $endFiscalYear : $startFiscalYear;
$startDate = $startFiscalYear . '-' . $startFiscalMonth . '-01';
$endDate = $endFiscalYear . '-' . $endFiscalMonth . '-' . $lastDayOfMonth;

// SOFTWARE-VERSION
$qtxt = "SELECT box1 FROM grupper WHERE art = 'VE'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $softwareVersion = $r['box1'];
}

// COMPANY
$qtxt = "SELECT firmanavn, addr1, addr2, bynavn, postnr, land, kontakt, tlf, fax, email, web, bank_navn, bank_reg, bank_konto, cvrnr FROM adresser WHERE art = 'S'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $companyName = $r['firmanavn'];
    $Address = $r['addr1'];
    $streetAddress_streetname = trim(splitAddress($Address)[1]);
    $postalAddress_streetname = trim(splitAddress($Address)[1]);
    $streetAddress_number = splitAddress($Address)[2];
    $postalAddress_number = splitAddress($Address)[2];
    $streetAddress_additionalAddressDetails = $r['addr2'];
    $postalAddress_additionalAddressDetails = $r['addr2'];
    $streetAddress_city = $r['bynavn'];
    $postalAddress_city = $r['bynavn'];
    $streetAddress_postalCode = $r['postnr'];
    $postalAddress_postalCode = $r['postnr'];
    $CountryName = $r['land'];
    $taxRegistrationCountry = countryCode($CountryName);
    $streetAddress_country = countryCode($CountryName);
    $postalAddress_country = countryCode($CountryName);
    $curCode = defaultCurrency($CountryName);
    $Contact = $r['kontakt'];
    $PhoneNumber = $r['tlf'];
    $FaxNumber = $r['fax'];
    if (empty($FaxNumber))
        $FaxNumber = "NA";
    $Email = $r['email'];
    $WebSite = $r['web'];
    if (empty($WebSite))
        $WebSite = "NA";
    $BankAccountName = $r['bank_navn'];
    $BankRegNumber = $r['bank_reg'];
    $BankAccountNumber = $r['bank_konto'];
    $companyIdent = $r['cvrnr'];
    $taxRegIdent = $r['cvrnr'];
    $TaxAuthority = TaxAuthorityName($CountryName);
}

// VAT-CODE-DETAILS dateOfEntry (first record from cash register)
$qtxt = "SELECT report.date FROM report WHERE id='1'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $firstEntry = $r['date'];
}

// VAT-CODE-DETAILS 
$x = 0;
$query = db_select("SELECT beskrivelse, kode FROM grupper WHERE fiscal_year='$regnaar' AND (art='SM' OR art='KM' OR art='EM' OR art='YM') ORDER BY box1 ASC", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($query)) {
    $x++;
    $vatCode[$x] = $r['kode'];
    $vatCodeDetails_dateOfEntry[$x] = $firstEntry;
    $vatDesc[$x] = $r['beskrivelse'];
    $standardVatCode[$x] = $r['kode'];
}
$vatCodeDetail_count = $x;

$vatCodeString = serialize($vatCode);
$vatCodeDetails_dateOfEntryString = serialize($vatCodeDetails_dateOfEntry);
$vatDescString = serialize($vatDesc);
$standardVatCodeString = serialize($standardVatCode);

// START TIME FROM PERIOD
$qtxt = "SELECT fakturadate, MIN(tidspkt) AS tidspkt
FROM ordrer
WHERE status >= '3' AND fakturadate >= '$startDatePeriod' AND fakturadate <= '$endDatePeriod' AND tidspkt IS NOT NULL AND art = 'PO'
GROUP BY fakturadate
ORDER BY fakturadate ASC
LIMIT 1";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $startTimePeriod = $r['tidspkt'] . ':00';
}

// END TIME FROM PERIOD
$qtxt = "SELECT fakturadate, MAX(tidspkt) AS tidspkt
FROM ordrer
WHERE status >= '3' AND fakturadate >= '$startDatePeriod' AND fakturadate <= '$endDatePeriod' AND tidspkt IS NOT NULL AND art = 'PO'
GROUP BY fakturadate
ORDER BY fakturadate DESC
LIMIT 1";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $endTimePeriod = $r['tidspkt'] . ':00';
}

$periodNumber = $regnaar;

// EMPLOYEES
if (db_fetch_array(db_select("SELECT id FROM ansatte", __FILE__ . " linje " . __LINE__)) != null) {
    $x = 0;
    $query = db_select("SELECT * FROM (
    SELECT DISTINCT ON (ref) ref, min(tidspkt) AS tidspkt, fakturadate, ansatte.nummer AS nummer, ansatte.id AS ansatte_id
    FROM ordrer
    INNER JOIN ansatte ON ansatte.navn = ordrer.ref
    WHERE status >= '3' AND fakturadate >= '$startDatePeriod' AND fakturadate <= '$endDatePeriod' AND art = 'PO'
    GROUP BY ref, fakturadate, ansatte.nummer, ansatte.id
    ) o
    ORDER BY fakturadate, tidspkt", __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($query)) {
        $x++;
        $employee_id[$x] = $r["ansatte_id"];
        $employee_number[$x] = $r["nummer"];
        $empID[$x] = ($employee_number[$x]) ? $employee_number[$x] : $employee_id[$x];
        $employees_dateOfEntry[$x] = $r['fakturadate'];
        $timeOfEntry[$x] = $r['tidspkt'] . ':00';
        $employee_name[$x] = $r['ref'];
        $firstName[$x] = splitName($employee_name[$x])[0];
        $surName[$x] = splitName($employee_name[$x])[1];
    }
    $employee_count = $x;
} else {
    $x = 0;
    $query = db_select("SELECT * FROM (
        SELECT DISTINCT ON (hvem) hvem, min(tidspkt) AS tidspkt, fakturadate, brugere.id AS ansatte_id
        FROM ordrer
        INNER JOIN brugere ON brugere.brugernavn = ordrer.hvem
        WHERE ordrer.status >= '3' AND fakturadate >= '$startDatePeriod' AND fakturadate <= '$endDatePeriod' AND art = 'PO'
        GROUP BY hvem, fakturadate, brugere.id
        ) o
        ORDER BY fakturadate, tidspkt", __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($query)) {
        $x++;
        $empID[$x] = $r["ansatte_id"];
        $employees_dateOfEntry[$x] = $r['fakturadate'];
        $timeOfEntry[$x] = $r['tidspkt'] . ':00';
        $employee_name[$x] = $r['hvem'];
        $firstName[$x] = splitName($employee_name[$x])[0];
        $surName[$x] = splitName($employee_name[$x])[1];
    }
    $employee_count = $x;
}

$empIDString = serialize($empID);
$employees_dateOfEntryString = serialize($employees_dateOfEntry);
$timeOfEntryString = serialize($timeOfEntry);
$firstNameString = serialize($firstName);
$surNameString = serialize($surName);

// ARTICLE
$x = 0;
$query = db_select("SELECT DISTINCT ordrelinjer.varenr AS varenr, fakturadate, ordrelinjer.beskrivelse AS beskrivelse, varer.gruppe AS gruppe
    FROM ordrer
    INNER JOIN ordrelinjer ON ordrelinjer.ordre_id = ordrer.id
    INNER JOIN varer ON varer.id = ordrelinjer.vare_id
    WHERE status >= '3' AND fakturadate >= '$startDatePeriod' AND fakturadate <= '$endDatePeriod' AND ordrelinjer.varenr IS NOT null AND ordrelinjer.varenr <> '' AND art = 'PO'
    ORDER BY fakturadate, varer.gruppe ASC", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($query)) {
    $x++;
    $artID[$x] = $r["varenr"];
    $dateOfEntry[$x] = $r['fakturadate'];
    $artGroupID[$x] = $r['gruppe'];
    $artDesc[$x] = $r['beskrivelse'];

}
$article_count = $x;

$artIDString = serialize($artID);
$dateOfEntryString = serialize($dateOfEntry);
$artGroupIDString = serialize($artGroupID);
$artDescString = serialize($artDesc);

// BASIC
// Her skal der hentes en liste over de Basic elementer som definere de
// forskellige master data og oversætte dem til predefineret standart kode.
// f.eks. kode for betalingstype, kasse åben/lukket....
$x = 0;
$query = db_select("SELECT basic_id, eng_name FROM saf_t_codes ORDER BY id ASC", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($query)) {
    $x++;
    $basicType[$x] = substr($r['basic_id'], 0, 2);
    $basicID[$x] = $r['basic_id'];
    $predefinedBasicID[$x] = $r['basic_id'];
    $basicDesc[$x] = $r['eng_name']; // Hvis dansk, brug 'local_name'
}
$basic_count = $x;

$basicTypeString = serialize($basicType);
$basicIDString = serialize($basicID);
$predefinedBasicIDString = serialize($predefinedBasicID);
$basicDescString = serialize($basicDesc);

// LOCATION
$location_name = $companyName;

// CASHREGISTER
$qtxt = "SELECT var_value FROM settings WHERE var_name = 'globalId'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $registerID = $r['var_value'];
}

// EVENTS
// eventID: Unique ID for the given event
// eventType: Reference to the type of event. Must be stated in the table 'Basic' (basicType 13)
// transID: A reference to the cash transaction ID
// empID: Reference to the employee (must be included in table 'employees')
// eventDate: event date
// eventTime: event time
// eventText: event text (Optionel, but use it with z-report)
$x = 0;
$query = db_select("SELECT ev_id, ev_type, TO_TIMESTAMP(ev_time::numeric)::TIME AS ev_time, TO_TIMESTAMP(ev_time::numeric)::DATE AS ev_date, employee_id, order_id, saf_t_codes.eng_name
FROM pos_events 
INNER JOIN saf_t_codes On saf_t_codes.basic_id = pos_events.ev_type::numeric
WHERE TO_TIMESTAMP(ev_time::numeric)::DATE >= '$startDatePeriod' AND TO_TIMESTAMP(ev_time::numeric)::DATE <= '$endDatePeriod' AND LEFT(ev_type, 2) = '13'
ORDER BY ev_date, ev_time ASC", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($query)) {
    $x++;
    $eventID[$x] = $r['ev_id'];
    $eventType[$x] = $r['ev_type'];
    $transID[$x] = $r['order_id'];
    $event_empID[$x] = $r['employee_id'];
    $eventDate[$x] = $r['ev_date'];
    $eventTime[$x] = $r['ev_time'];
    $eventText[$x] = $r['eng_name'];
}
$event_count = $x;

$eventIDString = serialize($eventID);
$eventTypeString = serialize($eventType);
$transIDString = serialize($transID);
$event_empIDString = serialize($event_empID);
$eventDateString = serialize($eventDate);
$eventTimeString = serialize($eventTime);
$eventTextString = serialize($eventText);

// EVENT-REPORT
//---------------------------------------------------

// REPORT-ID
$qtxt = "SELECT report_number
FROM report
WHERE date >= '$startDatePeriod' AND date <= '$endDatePeriod'
ORDER BY report_number DESC
LIMIT 1";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $reportID = $r['report_number']; // numbered consecutively with sequential number 
}

// DATE AND TIME OF Z-REPORT CREATED
$reportDate = date("Y-m-d"); // The same as dateCreated
$reportTime = date("H:i:s"); // The same as timeCreated

// TOTAL-CASH-SALE-AMNT
$qtxt = "SELECT SUM(sum) AS total_amount
FROM ordrer
WHERE ordrer.status >= '3' AND fakturadate >= '$startDatePeriod' AND fakturadate <= '$endDatePeriod' AND art = 'PO'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $totalCashSaleAmnt = number_format(round($r['total_amount'], 2), 2, '.', '');
}

// REPORT-ART-GROUP
$x = 0;
$query = db_select("SELECT varer.gruppe AS groupid, count(varer.gruppe) AS groupnumber, sum(ordrelinjer.antal * ordrelinjer.pris) AS amount
FROM ordrer
INNER JOIN ordrelinjer ON ordrelinjer.ordre_id = ordrer.id
INNER JOIN varer ON varer.id = ordrelinjer.vare_id
WHERE ordrer.status >= '3' AND fakturadate >= '$startDatePeriod' AND fakturadate <= '$endDatePeriod' AND ordrelinjer.varenr IS NOT null AND ordrelinjer.varenr <> '' AND art = 'PO'
GROUP BY varer.gruppe
ORDER BY varer.gruppe ASC", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($query)) {
    $x++;
    $eventReport_artGroupID[$x] = $r['groupid'];
    $artGroupNum[$x] = $r['groupnumber'];
    $artGroupAmnt[$x] = number_format(round($r['amount'], 2), 2, '.', '');
}
$reportArtGroup_count = $x;

$eventReport_artGroupIDString = serialize($eventReport_artGroupID);
$artGroupNumString = serialize($artGroupNum);
$artGroupAmntString = serialize($artGroupAmnt);

// REPORT PAYMENT
$x = 0;
$query = db_select("SELECT saf_t_codes.eng_name AS payment_type, count(saf_t_codes.eng_name) AS payment_number, sum(ordrer.sum) AS amount
FROM ordrer
INNER JOIN pos_events ON pos_events.order_id = ordrer.id
INNER JOIN saf_t_codes ON saf_t_codes.basic_id = pos_events.ev_type::numeric
WHERE status >= '3' AND fakturadate >= '$startDatePeriod' AND fakturadate <= '$endDatePeriod' AND LEFT(ev_type, 2) = '12' AND ordrer.art = 'PO'
GROUP BY saf_t_codes.eng_name
ORDER BY saf_t_codes.eng_name ASC", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($query)) {
    $x++;
    $paymentType[$x] = $r['payment_type'];
    $paymentNum[$x] = $r['payment_number'];
    $paymentAmnt[$x] = number_format(round($r['amount'], 2), 2, '.', '');
}
$reportPayment_count = $x;

$paymentTypeString = serialize($paymentType);
$paymentNumString = serialize($paymentNum);
$paymentAmntString = serialize($paymentAmnt);

// REPORT-EMP-PAYMENT
if (db_fetch_array(db_select("SELECT id FROM ansatte", __FILE__ . " linje " . __LINE__)) != null) {
    $x = 0;
    $query = db_select("SELECT ansatte.nummer AS nummer, ansatte.id AS ansatte_id, saf_t_codes.eng_name AS payment_type, count(saf_t_codes.eng_name) AS payment_number,  sum(ordrer.sum) AS amount
    FROM ordrer
    INNER JOIN ansatte ON ansatte.navn = ordrer.ref
    INNER JOIN pos_events ON pos_events.order_id = ordrer.id
    INNER JOIN saf_t_codes ON saf_t_codes.basic_id = pos_events.ev_type::numeric
    WHERE status >= '3' AND fakturadate >= '$startDatePeriod' AND fakturadate <= '$endDatePeriod' AND LEFT(ev_type, 2) = '12' AND ordrer.art = 'PO'
    GROUP BY ansatte.id, saf_t_codes.eng_name
    ORDER BY ansatte.id ASC", __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($query)) {
        $x++;
        $employee_id[$x] = $r["ansatte_id"];
        $employee_number[$x] = $r["nummer"];
        $reportEmpPayments_empID[$x] = ($employee_number[$x]) ? $employee_number[$x] : $employee_id[$x];
        $reportEmpPayments_paymentType[$x] = $r['payment_type'];
        $paymentEmpNum[$x] = $r['payment_number'];
        $paymentEmpAmnt[$x] = number_format(round($r['amount'], 2), 2, '.', '');
    }
    $reportEmpPayment_count = $x;
} else {
    $x = 0;
    $query = db_select("SELECT brugere.id AS bruger_id, saf_t_codes.eng_name AS payment_type, count(saf_t_codes.eng_name) AS payment_number,  sum(ordrer.sum) AS amount
    FROM ordrer
    INNER JOIN brugere ON brugere.brugernavn = ordrer.hvem
    INNER JOIN pos_events ON pos_events.order_id = ordrer.id
    INNER JOIN saf_t_codes ON saf_t_codes.basic_id = pos_events.ev_type::numeric
    WHERE ordrer.status >= '3' AND fakturadate >= '$startDatePeriod' AND fakturadate <= '$endDatePeriod' AND LEFT(ev_type, 2) = '12' AND art = 'PO'
    GROUP BY brugere.id, saf_t_codes.eng_name
    ORDER BY brugere.id ASC", __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($query)) {
        $x++;
        $reportEmpPayments_empID[$x] = $r["bruger_id"];
        $reportEmpPayments_paymentType[$x] = $r['payment_type'];
        $paymentEmpNum[$x] = $r['payment_number'];
        $paymentEmpAmnt[$x] = number_format(round($r['amount'], 2), 2, '.', '');
    }
    $reportEmpPayment_count = $x;
}

$reportEmpPayments_empIDString = serialize($reportEmpPayments_empID);
$reportEmpPayments_paymentTypeString = serialize($reportEmpPayments_paymentType);
$paymentEmpNumString = serialize($paymentEmpNum);
$paymentEmpAmntString = serialize($paymentEmpAmnt);

// REPORT-CASH-SALE-VAT
$x = 0;
$query = db_select("SELECT ordrer.momssats AS vat_percentage, SUM(sum) AS cash_sale_amount, SUM(moms) AS vat_amount,
CASE WHEN sum < 0 THEN 'D' ELSE 'C' END AS vat_amount_tp
FROM ordrer
WHERE ordrer.status >= '3' AND fakturadate >= '$startDatePeriod' AND fakturadate <= '$endDatePeriod' AND art = 'PO'
GROUP BY ordrer.momssats, sum < 0, sum >= 0
ORDER BY ordrer.momssats", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($query)) {
    $x++;
    $vatPerc[$x] = number_format($r['vat_percentage'], 2, '.', '');
    $cashSaleAmnt[$x] = number_format(round(abs($r['cash_sale_amount']), 2), 2, '.', '');
    $vatAmnt[$x] = number_format(round(abs($r['vat_amount']), 2), 2, '.', '');
    $vatAmntTp[$x] = $r['vat_amount_tp'];

}
$reportCashSaleVat_count = $x;

$vatPercString = serialize($vatPerc);
$cashSaleAmntString = serialize($cashSaleAmnt);
$vatAmntString = serialize($vatAmnt);
$vatAmntTpString = serialize($vatAmntTp);

// REPORT-CORR-LINES
// Check if line correction is logged in db!!
// for now use this..
$corrLineType = array('NONE');
$corrLineNum = array(0);
$corrLineAmnt = array('0.00');

// REPORT-PRICE-INQUIRIES
// Here we need to see how many times a employee is doing a price look-up.
// It have to be by product group (groupnumber as in 'basics') and how many
// times of price inquiries in that group, and the total amount for the group.
$priceInquiryGroup = array(0); // DUMMY NUMBER, there is no groupnumber 0
$priceInquiryNum = array(0);
$priceInquiryAmnt = array('0.00');

// REPORT-OTHER-CORRS
// Check if other corrections is logged in db!!
// for now use this..
$otherCorrType = array('NONE');
$otherCorrNum = array(0);
$otherCorrAmnt = array('0.00');

// CASHTRANSACTION
$signature_from_previous = 0;
if (db_fetch_array(db_select("SELECT id FROM ansatte", __FILE__ . " linje " . __LINE__)) != null) {
    $x = 0;
    $query = db_select("SELECT DISTINCT ON (ordrer.id) ordrer.id AS nr, ordrer.id AS trans_id, saf_t_codes.eng_name AS trans_type, ordrer.sum + ordrer.moms AS trans_amnt_in, ordrer.sum AS trans_amnt_ex,
    CASE WHEN sum < 0 THEN 'D' ELSE 'C' END AS vat_amount_tp, ansatte.nummer AS nummer, ansatte.id AS ansatte_id, ordrer.fakturadate AS trans_date, ordrer.tidspkt AS trans_time
    FROM ordrer
    INNER JOIN ansatte ON ansatte.navn = ordrer.ref
    INNER JOIN pos_events ON pos_events.order_id = ordrer.id
    INNER JOIN saf_t_codes ON saf_t_codes.basic_id = pos_events.ev_type::numeric
    WHERE status >= '3' AND fakturadate >= '$startDatePeriod' AND fakturadate <= '$endDatePeriod' AND LEFT(ev_type, 2) = '11' AND ordrer.art = 'PO'
    ORDER BY ordrer.id ASC", __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($query)) {
        $x++;
        $cashtransaction_nr[$x] = $r['nr'];
        $cashtransaction_transID[$x] = $r['trans_id'];
        $transType[$x] = $r['trans_type'];
        $transAmntIn[$x] = number_format(round(abs($r['trans_amnt_in']), 2), 2, '.', '');
        $transAmntEx[$x] = number_format(round(abs($r['trans_amnt_ex']), 2), 2, '.', '');
        $cashtransaction_amntTp[$x] = $r['vat_amount_tp'];
        $employee_id[$x] = $r['ansatte_id'];
        $employee_number[$x] = $r['nummer'];
        $cashtransaction_empID[$x] = ($employee_number[$x]) ? $employee_number[$x] : $employee_id[$x];
        $transDate[$x] = $r['trans_date'];
        $transTime[$x] = $r['trans_time'] . ':00';
        $certificateData[$x] = $public_key;
        $keyVersion[$x] = 1;

        // Create string for hashing and signing
        $stringToHash[$x] = $cashtransaction_nr[$x] . ';' . $cashtransaction_transID[$x] . ';' . $transType[$x] . ';' . $transDate[$x] . ';' . $transTime[$x] .
            ';' . $cashtransaction_empID[$x] . ';' . $transAmntIn[$x] . ';' . $transAmntEx[$x] . ';' . $registerID . ';' . $companyIdent;
        $prev_stringToHash[$x] = (!isset($stringToHash[$x - 1]) == '') ? $stringToHash[$x - 1] : 0;
    }
    $cashtransaction_count = $x;
} else {
    $x = 0;
    $query = db_select("SELECT DISTINCT ON (ordrer.id) ordrer.id AS nr, ordrer.id AS trans_id, saf_t_codes.eng_name AS trans_type, ordrer.sum + ordrer.moms AS trans_amnt_in, ordrer.sum AS trans_amnt_ex,
    CASE WHEN sum < 0 THEN 'D' ELSE 'C' END AS vat_amount_tp, brugere.id AS emp_id, ordrer.fakturadate AS trans_date, ordrer.tidspkt AS trans_time
    FROM ordrer
    INNER JOIN brugere ON brugere.brugernavn = ordrer.hvem
    INNER JOIN pos_events ON pos_events.order_id = ordrer.id
    INNER JOIN saf_t_codes ON saf_t_codes.basic_id = pos_events.ev_type::numeric
    WHERE ordrer.status >= '3' AND fakturadate >= '$startDatePeriod' AND fakturadate <= '$endDatePeriod' AND LEFT(ev_type, 2) = '11' AND ordrer.art = 'PO'
    ORDER BY ordrer.id ASC", __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($query)) {
        $x++;
        $cashtransaction_nr[$x] = $r['nr'];
        $cashtransaction_transID[$x] = $r['trans_id'];
        $transType[$x] = $r['trans_type'];
        $transAmntIn[$x] = number_format(round(abs($r['trans_amnt_in']), 2), 2, '.', '');
        $transAmntEx[$x] = number_format(round(abs($r['trans_amnt_ex']), 2), 2, '.', '');
        $cashtransaction_amntTp[$x] = $r['vat_amount_tp'];
        $cashtransaction_empID[$x] = $r['emp_id'];
        $transDate[$x] = $r['trans_date'];
        $transTime[$x] = $r['trans_time'] . ':00';
        $certificateData[$x] = $public_key;
        $keyVersion[$x] = 1;

        // Create string for hashing and signing
        $stringToHash[$x] = $cashtransaction_nr[$x] . ';' . $cashtransaction_transID[$x] . ';' . $transType[$x] . ';' . $transDate[$x] . ';' . $transTime[$x] .
            ';' . $cashtransaction_empID[$x] . ';' . $transAmntIn[$x] . ';' . $transAmntEx[$x] . ';' . $registerID . ';' . $companyIdent;
        $prev_stringToHash[$x] = (!isset($stringToHash[$x - 1]) == '') ? $stringToHash[$x - 1] : 0;
    }
    $cashtransaction_count = $x;
}

/**
 * Hashing and signing of cash register data
 */
for ($x = 1; $x <= $cashtransaction_count; $x++) {

    if ($prev_stringToHash[$x] == 0) {
        $prev_stringToHash[$x] = signatureString($prev_stringToHash[$x], $private_key);
        $hashString[$x] = hashString($prev_stringToHash[$x] . ';' . $stringToHash[$x]);
        $signature_hashString[$x] = signatureString($hashString[$x], $private_key);
        $signature[$x] = $signature_hashString[$x];
        $verify[$x] = verifySignatureString($hashString[$x], $signature[$x], $public_key); // this is for verify digital signature TEST ONLY
    } else {
        $hashString[$x] = hashString($signature_hashString[$x - 1] . ';' . $prev_stringToHash[$x]);
        $signature_hashString[$x] = signatureString($hashString[$x], $private_key);
        $signature[$x] = $signature_hashString[$x];
        $verify[$x] = verifySignatureString($hashString[$x], $signature[$x], $public_key); // this is for verify digital signature TEST ONLY
    }
}

// echo '<pre>';
// print_r($stringToHash); // this is the string we want to hash
// echo '</pre>';
// echo '<pre>';
// print_r($prev_stringToHash); // Take the previous string and hash + signature
// echo '</pre>';
// echo '<pre>';
// print_r($hashString); // this is the hashed string
// echo '</pre>';
// echo '<pre>';
// print_r($signature_hashString); // string with signature
// echo '</pre>';
// echo '<pre>';
// print_r($verify); // verify signature string
// echo '</pre>';
$cashtransaction_nrString = serialize($cashtransaction_nr);
$cashtransaction_transIDString = serialize($cashtransaction_transID);
$transTypeString = serialize($transType);
$transAmntInString = serialize($transAmntIn);
$transAmntExString = serialize($transAmntEx);
$cashtransaction_amntTpString = serialize($cashtransaction_amntTp);
$cashtransaction_empIDString = serialize($cashtransaction_empID);
$transDateString = serialize($transDate);
$transTimeString = serialize($transTime);

($cashtransaction_nr) ? $cashtransaction_OID = implode(',', $cashtransaction_nr) : $cashtransaction_OID = 0;

// CT-LINE + VAT
$x = 0;
$query = db_select("SELECT ordrelinjer.ordre_id AS nr, posnr AS line_id, saf_t_codes.eng_name AS line_type, varer.gruppe AS art_group_id, ordrelinjer.varenr AS art_id, antal AS qnt, ordrelinjer.vat_price * antal AS line_amnt_in, pris * antal AS line_amnt_ex,
CASE WHEN pris < 0 THEN 'D' ELSE 'C' END AS amnt_tp, ordrelinjer.momssats AS vatperc, (ordrelinjer.vat_price * antal) - (pris * antal) AS vat_amnt
FROM ordrelinjer
INNER JOIN pos_events ON pos_events.order_id = ordrelinjer.ordre_id
INNER JOIN saf_t_codes ON saf_t_codes.basic_id = pos_events.ev_type::numeric
INNER JOIN varer ON varer.id = ordrelinjer.vare_id
WHERE ordrelinjer.ordre_id IN ($cashtransaction_OID) AND ordrelinjer.vat_price IS NOT NULL AND LEFT(ev_type, 2) = '11'
ORDER BY nr, line_id", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($query)) {
    $x++;
    $ctLine_nr[$x] = $r['nr'];
    $lineID[$x] = $r['line_id'];
    $lineType[$x] = $r['line_type'];
    $ctLine_artGroupID[$x] = $r['art_group_id'];
    $ctLine_artID[$x] = $r['art_id'];
    $qnt[$x] = number_format($r['qnt'], 2, '.', '');
    $lineAmntIn[$x] = number_format(round(abs($r['line_amnt_in']), 2), 2, '.', '');
    $lineAmntEx[$x] = number_format(round(abs($r['line_amnt_ex']), 2), 2, '.', '');
    $ctLine_amntTp[$x] = $r['amnt_tp'];
    $vat_vatPerc[$x] = number_format($r['vatperc'], 2, '.', '');
    $vat_vatAmnt[$x] = number_format(round(abs($r['vat_amnt']), 2), 2, '.', '');
    $vat_vatAmntTp[$x] = $r['amnt_tp'];
    $vatBasAmnt[$x] = number_format(round(abs($r['line_amnt_ex']), 2), 2, '.', '');
}
$ctLine_count = $x;

$ctLine_nrString = serialize($ctLine_nr);
$lineIDString = serialize($lineID);
$lineTypeString = serialize($lineType);
$ctLine_artGroupIDString = serialize($ctLine_artGroupID);
$ctLine_artIDString = serialize($ctLine_artID);
$qntString = serialize($qnt);
$lineAmntInString = serialize($lineAmntIn);
$lineAmntExString = serialize($lineAmntEx);
$ctLine_amntTpString = serialize($ctLine_amntTp);
$vat_vatPercString = serialize($vat_vatPerc);
$vat_vatAmntString = serialize($vat_vatAmnt);
$vat_vatAmntTpString = serialize($vat_vatAmntTp);
$vatBasAmntString = serialize($vatBasAmnt);

// PAYMENT
if (db_fetch_array(db_select("SELECT id FROM ansatte", __FILE__ . " linje " . __LINE__)) != null) {
    $x = 0;
    $query = db_select("SELECT DISTINCT ON (ordrer.id) ordrer.id AS id, saf_t_codes.eng_name AS payment_type, ordrer.sum + ordrer.moms AS paid_amnt,
    ansatte.nummer AS nummer, ansatte.id AS ansatte_id, ordrer.valuta AS curcode
    FROM ordrer
    INNER JOIN ansatte ON ansatte.navn = ordrer.ref
    INNER JOIN pos_events ON pos_events.order_id = ordrer.id
    INNER JOIN saf_t_codes ON saf_t_codes.basic_id = pos_events.ev_type::numeric
    WHERE ordrer.status >= '3' AND fakturadate >= '$startDatePeriod' AND fakturadate <= '$endDatePeriod' AND LEFT(ev_type, 2) = '12' AND ordrer.art = 'PO'
    ORDER BY ordrer.id ASC", __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($query)) {
        $x++;
        $payment_paymentType[$x] = $r['payment_type'];
        $paidAmnt[$x] = number_format(round($r['paid_amnt'], 2), 2, '.', '');
        $employee_id[$x] = $r['ansatte_id'];
        $employee_number[$x] = $r['nummer'];
        $payment_empID[$x] = ($employee_number[$x]) ? $employee_number[$x] : $employee_id[$x];
        $payment_curCode[$x] = $r['curcode'];
    }
    $payment_count = $x;
} else {
    $x = 0;
    $query = db_select("SELECT DISTINCT ON (ordrer.id) ordrer.id AS id, saf_t_codes.eng_name AS payment_type, ordrer.sum + ordrer.moms AS paid_amnt,
    brugere.id AS emp_id, ordrer.valuta AS curcode
    FROM ordrer
    INNER JOIN brugere ON brugere.brugernavn = ordrer.hvem
    INNER JOIN pos_events ON pos_events.order_id = ordrer.id
    INNER JOIN saf_t_codes ON saf_t_codes.basic_id = pos_events.ev_type::numeric
    WHERE ordrer.status >= '3' AND fakturadate >= '$startDatePeriod' AND fakturadate <= '$endDatePeriod' AND LEFT(ev_type, 2) = '12' AND ordrer.art = 'PO'
    ORDER BY ordrer.id ASC", __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($query)) {
        $x++;
        $payment_paymentType[$x] = $r['payment_type'];
        $paidAmnt[$x] = number_format(round($r['paid_amnt'], 2), 2, '.', '');
        $payment_empID[$x] = $r['emp_id'];
        $payment_curCode[$x] = $r['curcode'];
    }
    $payment_count = $x;
}

$payment_paymentTypeString = serialize($payment_paymentType);
$paidAmntString = serialize($paidAmnt);
$payment_empIDString = serialize($payment_empID);
$payment_curCodeString = serialize($payment_curCode);

/******************************************************************************************* */

/**
 * Here we set filename and filepath into variables 
 */
if (isset($_SESSION['fileName']) && isset($_SESSION['filePath'])) {
    $fileName = $_SESSION['fileName'];
    $filePath = $_SESSION['filePath'];
    $fileCreatedMessage = $_SESSION['fileMessage'];
    $startDatePeriod = $_SESSION['startDatePeriod'];
    $endDatePeriod = $_SESSION['endDatePeriod'];
    unset($_SESSION['fileName'], $_SESSION['filePath'], $_SESSION['fileMessage'], $_SESSION['startDatePeriod'], $_SESSION['endDatePeriod']);
}

/**
 * Find all files with .xml in folder and convert to four
 * variables:
 * $filePath, $fileName, $fileSize, $fileSizeKb
 */
$files = glob('../temp/' . $db . '/cashRegister/*xml');
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
 * Here we split startDate- and endDate-string into day, month and year
 */
if ($startDatePeriod) {
    $dateFrom = date_create_from_format("Y-m-d", $startDatePeriod);
    $startDay = date_format($dateFrom, "d");
    $startMonth = monthName(date_format($dateFrom, "m"));
    $startYear = date_format($dateFrom, "Y");
    $dateTo = date_create_from_format("Y-m-d", $endDatePeriod);
    $endDay = date_format($dateTo, "d");
    $endMonth = monthName(date_format($dateTo, "m"));
    $endYear = date_format($dateTo, "Y");
}

$periodDateFrom = 'Fra ' . $startDay . '. ' . $startMonth . ' ' . $startYear;
$periodDateTo = 'Til ' . $endDay . '. ' . $endMonth . ' ' . $endYear;

/***************************************************************************************************** */

$newTitle = "SAF-T Cash Register";
if ($menu == 'T') {
    $title = "Rapport • $newTitle";

    include_once '../includes/top_header.php';
    include_once '../includes/top_menu.php';
    print "<div id=\"header\">";
    print "<div class=\"headerbtnLft headLink\"><a href=\"rapport.php\" accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst(30, $sprog_id) . "</a></div>";
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
    print "<td width=\"10%\" $top_bund><a accesskey=L href=\"rapport.php\">Luk</a></td>";
    print "<td width=\"80%\" $top_bund> Rapport - $newTitle </td>";
    print "<td width=\"10%\" $top_bund></td>";
    print "</tbody></table>";
    print "</td></tr>";
}

/**
 * Popup message
 */
print "<div class=\"popup\"><span class=\"popuptext\" id=\"createMessage\">$fileCreatedMessage</span><span class=\"popuptext\" id=\"downloadMessage\"></span></div>";

print "<table class=\"saftHeader\">\n";
print "<tr><td rowspan=\"3\" class=\"saftTitle\">$newTitle</td><td>Regnskabsår</td><td>$regnaar.</td></tr>\n";
print "<tr><td rowspan=\"2\">Periode</td><td>$periodDateFrom</td></tr>\n";
print "<tr><td>$periodDateTo</td></tr>";
print "<tr><td colspan=\"3\" class=\"saftFirmName\">$companyName</td>\n";
print "</table>\n";
print "<table class=\"saftTable1\">\n";
if ($startTimePeriod && $endTimePeriod) {
    print "<tr><th colspan=\"2\">";
    print "Her kan du oprette en SAF-T Cash Register rapport:";
    print "</th><th>&nbsp;</th><th>&nbsp;</th></tr>\n";
    print "<tr><td>";
    print "<form method=\"post\" action=\"saftCashRegisterCreator.php\" style=\"margin-bottom: 0;\">";
    print "<input type='hidden' name='vatCodeDetail_count' value='" . $vatCodeDetail_count . "'>";
    print "<input type='hidden' name='vatCode' value='" . $vatCodeString . "'>";
    print "<input type='hidden' name='vatCodeDetails_dateOfEntry' value='" . $vatCodeDetails_dateOfEntryString . "'>";
    print "<input type='hidden' name='vatDesc' value='" . $vatDescString . "'>";
    print "<input type='hidden' name='standardVatCode' value='" . $standardVatCodeString . "'>";
    print "<input type='hidden' name='startDatePeriod' value='" . $startDatePeriod . "'>";
    print "<input type='hidden' name='endDatePeriod' value='" . $endDatePeriod . "'>";
    print "<input type='hidden' name='employee_count' value='" . $employee_count . "'>";
    print "<input type='hidden' name='empIDString' value='" . $empIDString . "'>";
    print "<input type='hidden' name='employees_dateOfEntryString' value='" . $employees_dateOfEntryString . "'>";
    print "<input type='hidden' name='timeOfEntryString' value='" . $timeOfEntryString . "'>";
    print "<input type='hidden' name='firstNameString' value='" . $firstNameString . "'>";
    print "<input type='hidden' name='surNameString' value='" . $surNameString . "'>";
    print "<input type='hidden' name='article_count' value='" . $article_count . "'>";
    print "<input type='hidden' name='artIDString' value='" . $artIDString . "'>";
    print "<input type='hidden' name='dateOfEntryString' value='" . $dateOfEntryString . "'>";
    print "<input type='hidden' name='artGroupIDString' value='" . $artGroupIDString . "'>";
    print "<input type='hidden' name='artDescString' value='" . $artDescString . "'>";
    print "<input type='hidden' name='basic_count' value='" . $basic_count . "'>";
    print "<input type='hidden' name='basicTypeString' value='" . $basicTypeString . "'>";
    print "<input type='hidden' name='basicIDString' value='" . $basicIDString . "'>";
    print "<input type='hidden' name='predefinedBasicIDString' value='" . $predefinedBasicIDString . "'>";
    print "<input type='hidden' name='basicDescString' value='" . $basicDescString . "'>";
    print "<input type='hidden' name='event_count' value='" . $event_count . "'>";
    print "<input type='hidden' name='eventIDString' value='" . $eventIDString . "'>";
    print "<input type='hidden' name='eventTypeString' value='" . $eventTypeString . "'>";
    print "<input type='hidden' name='transIDString' value='" . $transIDString . "'>";
    print "<input type='hidden' name='event_empIDString' value='" . $event_empIDString . "'>";
    print "<input type='hidden' name='eventDateString' value='" . $eventDateString . "'>";
    print "<input type='hidden' name='eventTimeString' value='" . $eventTimeString . "'>";
    print "<input type='hidden' name='eventTextString' value='" . $eventTextString . "'>";
    print "<input type='hidden' name='reportArtGroup_count' value='" . $reportArtGroup_count . "'>";
    print "<input type='hidden' name='eventReport_artGroupIDString' value='" . $eventReport_artGroupIDString . "'>";
    print "<input type='hidden' name='artGroupNumString' value='" . $artGroupNumString . "'>";
    print "<input type='hidden' name='artGroupAmntString' value='" . $artGroupAmntString . "'>";
    print "<input type='hidden' name='reportPayment_count' value='" . $reportPayment_count . "'>";
    print "<input type='hidden' name='paymentTypeString' value='" . $paymentTypeString . "'>";
    print "<input type='hidden' name='paymentNumString' value='" . $paymentNumString . "'>";
    print "<input type='hidden' name='paymentAmntString' value='" . $paymentAmntString . "'>";
    print "<input type='hidden' name='reportEmpPayment_count' value='" . $reportEmpPayment_count . "'>";
    print "<input type='hidden' name='reportEmpPayments_empIDString' value='" . $reportEmpPayments_empIDString . "'>";
    print "<input type='hidden' name='reportEmpPayments_paymentTypeString' value='" . $reportEmpPayments_paymentTypeString . "'>";
    print "<input type='hidden' name='paymentEmpNumString' value='" . $paymentEmpNumString . "'>";
    print "<input type='hidden' name='paymentEmpAmntString' value='" . $paymentEmpAmntString . "'>";
    print "<input type='hidden' name='reportCashSaleVat_count' value='" . $reportCashSaleVat_count . "'>";
    print "<input type='hidden' name='vatPercString' value='" . $vatPercString . "'>";
    print "<input type='hidden' name='cashSaleAmntString' value='" . $cashSaleAmntString . "'>";
    print "<input type='hidden' name='vatAmntString' value='" . $vatAmntString . "'>";
    print "<input type='hidden' name='vatAmntTpString' value='" . $vatAmntTpString . "'>";
    print "<input type='hidden' name='cashtransaction_count' value='" . $cashtransaction_count . "'>";
    print "<input type='hidden' name='cashtransaction_nrString' value='" . $cashtransaction_nrString . "'>";
    print "<input type='hidden' name='cashtransaction_transIDString' value='" . $cashtransaction_transIDString . "'>";
    print "<input type='hidden' name='transTypeString' value='" . $transTypeString . "'>";
    print "<input type='hidden' name='transAmntInString' value='" . $transAmntInString . "'>";
    print "<input type='hidden' name='transAmntExString' value='" . $transAmntExString . "'>";
    print "<input type='hidden' name='cashtransaction_amntTpString' value='" . $cashtransaction_amntTpString . "'>";
    print "<input type='hidden' name='cashtransaction_empIDString' value='" . $cashtransaction_empIDString . "'>";
    print "<input type='hidden' name='transDateString' value='" . $transDateString . "'>";
    print "<input type='hidden' name='transTimeString' value='" . $transTimeString . "'>";
    print "<input type='hidden' name='ctLine_count' value='" . $ctLine_count . "'>";
    print "<input type='hidden' name='ctLine_nrString' value='" . $ctLine_nrString . "'>";
    print "<input type='hidden' name='lineIDString' value='" . $lineIDString . "'>";
    print "<input type='hidden' name='lineTypeString' value='" . $lineTypeString . "'>";
    print "<input type='hidden' name='ctLine_artGroupIDString' value='" . $ctLine_artGroupIDString . "'>";
    print "<input type='hidden' name='ctLine_artIDString' value='" . $ctLine_artIDString . "'>";
    print "<input type='hidden' name='qntString' value='" . $qntString . "'>";
    print "<input type='hidden' name='lineAmntInString' value='" . $lineAmntInString . "'>";
    print "<input type='hidden' name='lineAmntExString' value='" . $lineAmntExString . "'>";
    print "<input type='hidden' name='ctLine_amntTpString' value='" . $ctLine_amntTpString . "'>";
    print "<input type='hidden' name='vat_vatPercString' value='" . $vat_vatPercString . "'>";
    print "<input type='hidden' name='vat_vatAmntString' value='" . $vat_vatAmntString . "'>";
    print "<input type='hidden' name='vat_vatAmntTpString' value='" . $vat_vatAmntTpString . "'>";
    print "<input type='hidden' name='vatBasAmntString' value='" . $vatBasAmntString . "'>";
    print "<input type='hidden' name='payment_paymentTypeString' value='" . $payment_paymentTypeString . "'>";
    print "<input type='hidden' name='paidAmntString' value='" . $paidAmntString . "'>";
    print "<input type='hidden' name='payment_empIDString' value='" . $payment_empIDString . "'>";
    print "<input type='hidden' name='payment_curCodeString' value='" . $payment_curCodeString . "'>";
    print "<span><button type=\"submit\">Opret SAF-T fil</button></span>";
    print "</form>";
    print "</td><td>&nbsp;</td><td>&nbsp;</td>";
    print "<td>&nbsp;</td></tr>\n";
} else {
    print "<tr><th colspan=\"4\" style=\"text-align: center;\"><i>Der er ingen data i den valgte periode</i></th></tr>";
}
print "</table>\n";
print "<hr>";
if ($fileExist) {
    print "<table class='saftTable2'>";
    print "<tr><th>File name</th><th>Size</th><th>Date and time of creation</th><th>&nbsp;</th>";
    print "<tr><td>$fileName</td><td>$fileSizeKb</td><td>$formatedDate</td><td><button id='download' onclick='downloadFile(\"$filePath\", \"$fileName\")'>Download</button></td></tr>"; // <a href='$filePath' download><button>Download</button></a> 
    print "</table>";
    print '<table class="saftTable3">';
    print '<tr><td>';
    print '<button onclick="showXML()" id="showXML">Vis XML fil</button>';
    print '</td></tr></table>';
}
if ($fileExist) {
    print '<div id="xmlFile">';
    $newFilePath = str_replace(' ', '%20', $filePath);
    print '<pre data-src=' . $newFilePath . '></pre>';
    print '</div>';
}

?>
<!-- Javascript for viewing xml file on page -->
<script src="../javascript/prism.js"></script> 

<script>
    // Javascript that will download xml file
    function downloadFile(url, fileName) {
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
            message.innerHTML = "Fil downloaded";
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

    // Javascript function that toggles view of XML file
    function showXML() {
        var x = document.getElementById('xmlFile');
        if (x.style.display === "block") {
            x.style.display = "none";
            document.getElementById('showXML').innerText = 'Vis XML fil';
        } else {
            x.style.display = "block";
            document.getElementById('showXML').innerText = 'Luk XML fil';
        }
    }
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
<?php
if ($menu == 'T') {
    include_once '../includes/topmenu/footer.php';
} else {
    include_once '../includes/oldDesign/footer.php';
}
?>
