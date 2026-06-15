<?php
@session_start();
$s_id = session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("saftCreatorUtil/address.php");
ob_start();
include("saft.php");
ob_end_clean();

if (!function_exists('appendSaftTextElement')) {
    function appendSaftTextElement($dom, $parent, $name, $value)
    {
        $node = $dom->createElement($name);
        $node->appendChild($dom->createTextNode((string) $value));
        $parent->appendChild($node);

        return $node;
    }
}

$regnaar = if_isset($_POST['regnaar']);
$maaned_fra = if_isset($_POST['maaned_fra']);
$maaned_til = if_isset($_POST['maaned_til']);
$aar_fra = if_isset($_POST['aar_fra']);
$aar_til = if_isset($_POST['aar_til']);
$startmaaned = if_isset($_POST['startmaaned']);
$slutmaaned = if_isset($_POST['slutmaaned']);
$dato_fra = if_isset($_POST['dato_fra']);
$dato_til = if_isset($_POST['dato_til']);
$konto_fra = if_isset($_POST['konto_fra']);
$konto_til = if_isset($_POST['konto_til']);
$rapportart = if_isset($_POST['rapportart']);
$kontoantal = if_isset($_POST['kontoantal']);
// $kontonrString = if_isset($_POST['kontonrString']);
// $kontonr = unserialize($kontonrString);
$kontobeskrivelseString = if_isset($_POST['kontobeskrivelseString']);
$kontobeskrivelse = unserialize($kontobeskrivelseString);
$kontotypeString = if_isset($_POST['kontotypeString']);
$kontotype = unserialize($kontotypeString);
$openingDbCrString = if_isset($_POST['openingDbCrString']);
$openingDbCr = unserialize($openingDbCrString);
$closingDbCrString = if_isset($_POST['closingDbCrString']);
$closingDbCr = unserialize($closingDbCrString);
$standardKontonrString = if_isset($_POST['standardKontonrString']);
$standardKontonr = unserialize($standardKontonrString);

if ($Country == 'NO') {
    $attr1 = 'urn:StandardAuditFile-Taxation-Financial:NO';
    $attr2 = 'http://www.w3.org/2001/XMLSchema-instance';
    $attr3 = 'urn:StandardAuditFile-Taxation-Financial:NO Norwegian_SAF-T_Financial_Schema_v_1.10.xsd';
} else {
    $attr1 = 'urn:StandardAuditFile-Taxation-Financial:DK';
    $attr2 = 'http://www.w3.org/2001/XMLSchema-instance';
    $attr3 = 'urn:StandardAuditFile-Taxation-Financial: Danish_SAF-T_Financial_Schema_v_1_0.xsd';
}

$AuditFileDateCreated = date("Y-m-d");

$AuditFileDateTimeCreated = date("YmdHis");

$AuditFileName = "SAF-T Financial_" . $TaxRegistrationNumber . "_" . $AuditFileDateTimeCreated . ".xml";

$dom = new DOMDocument();

$dom->encoding = 'utf-8';

$dom->xmlVersion = '1.0';

$dom->formatOutput = true;

$dom->preserveWhiteSpace = FALSE;

$xml_file_path = "../temp/$db/financial/";
if (!is_dir($xml_file_path))
    mkdir($xml_file_path, 0777, true);

$xml_file_name = $xml_file_path . $AuditFileName;

$root = $dom->createElement('n1:AuditFile');

$attr1_root = new DOMAttr('xmlns:n1', $attr1);
$attr2_root = new DOMAttr('xmlns:xsi', $attr2);
$attr3_root = new DOMAttr('xsi:schemaLocation', $attr3);

$root->setAttributeNode($attr1_root);
$root->setAttributeNode($attr2_root);
$root->setAttributeNode($attr3_root);

$header_node = $dom->createElement('n1:Header');

appendSaftTextElement($dom, $header_node, 'n1:AuditFileVersion', $AuditFileVersion);

appendSaftTextElement($dom, $header_node, 'n1:AuditFileCountry', $Country);
if ($Region != '') {
    appendSaftTextElement($dom, $header_node, 'n1:AuditFileRegion', $Region);
}
appendSaftTextElement($dom, $header_node, 'n1:AuditFileDateCreated', $AuditFileDateCreated);

appendSaftTextElement($dom, $header_node, 'n1:SoftwareCompanyName', $SoftwareCompanyName);

appendSaftTextElement($dom, $header_node, 'n1:SoftwareID', $SoftwareID);

appendSaftTextElement($dom, $header_node, 'n1:SoftwareVersion', $SoftwareVersion);
/*--------------------- Company -----------------------*/
$company_node = $dom->createElement('n1:Company');

appendSaftTextElement($dom, $company_node, 'n1:RegistrationNumber', $RegistrationNumber);

appendSaftTextElement($dom, $company_node, 'n1:Name', $firmanavn);
/*---------------------- Address ----------------------*/
$address_node = $dom->createElement('n1:Address');

// address($dom, $address_node, $StreetName, $StreetNumber, $AdditionalAddressDetail, $City, $PostalCode, $Region, $Country, $AddressType); // function test
appendSaftTextElement($dom, $address_node, 'n1:StreetName', $StreetName);

appendSaftTextElement($dom, $address_node, 'n1:Number', $StreetNumber);

appendSaftTextElement($dom, $address_node, 'n1:AdditionalAddressDetail', $AdditionalAddressDetail);

appendSaftTextElement($dom, $address_node, 'n1:Building', $address_Building);

appendSaftTextElement($dom, $address_node, 'n1:City', $City);

appendSaftTextElement($dom, $address_node, 'n1:PostalCode', $PostalCode);

appendSaftTextElement($dom, $address_node, 'n1:Region', $Region);

appendSaftTextElement($dom, $address_node, 'n1:Country', $Country);

appendSaftTextElement($dom, $address_node, 'n1:AddressType', $AddressType);

$company_node->appendChild($address_node);
/*-------------------- End Address ------------------------*/
/*-------------------- Contact ----------------------------*/
$contact_node = $dom->createElement('n1:Contact');
/*-------------------- ContactPerson ----------------------*/
$contact_person_node = $dom->createElement('n1:ContactPerson');

// $child_node_Title = $dom->createElement('n1:Title', 'Fru');
// $contact_person_node->appendChild($child_node_Title);

appendSaftTextElement($dom, $contact_person_node, 'n1:FirstName', $ContactPersonName);

appendSaftTextElement($dom, $contact_person_node, 'n1:Initials', $ContactInitials);

// $child_node_LastNamePrefix = $dom->createElement('n1:LastNamePrefix', 'Von');
// $contact_person_node->appendChild($child_node_LastNamePrefix);

appendSaftTextElement($dom, $contact_person_node, 'n1:LastName', $ContactLastName);

// $child_node_BirthName = $dom->createElement('n1:BirthName', $ContactName);
// $contact_person_node->appendChild($child_node_BirthName);

// $child_node_Salutation = $dom->createElement('n1:Salutation', 'Skibsredder');
// $contact_person_node->appendChild($child_node_Salutation);

// $child_node_OtherTitles = $dom->createElement('n1:OtherTitles', 'Direktør');
// $contact_person_node->appendChild($child_node_OtherTitles);

$contact_node->appendChild($contact_person_node);
/*-------------------- End ContactPerson ------------------*/
appendSaftTextElement($dom, $contact_node, 'n1:Telephone', $PhoneNumber);
if ($FaxNumber != '') {
    appendSaftTextElement($dom, $contact_node, 'n1:Fax', $FaxNumber);
}
appendSaftTextElement($dom, $contact_node, 'n1:Email', $Email);
if ($WebSite != '') {
    appendSaftTextElement($dom, $contact_node, 'n1:Website', $WebSite);
}
appendSaftTextElement($dom, $contact_node, 'n1:MobilePhone', $PhoneNumber);

$company_node->appendChild($contact_node);
/*-------------------- End Contact ------------------------*/
/*-------------------- TaxRegistration --------------------*/
$TaxRegistration_node = $dom->createElement('n1:TaxRegistration');

appendSaftTextElement($dom, $TaxRegistration_node, 'n1:TaxRegistrationNumber', $TaxRegistrationNumber);

appendSaftTextElement($dom, $TaxRegistration_node, 'n1:TaxType', $TaxType);

appendSaftTextElement($dom, $TaxRegistration_node, 'n1:TaxNumber', $TaxRegistrationNumber);

appendSaftTextElement($dom, $TaxRegistration_node, 'n1:TaxAuthority', $TaxAuthority);

// $child_node_TaxVerificationDate = $dom->createElement('n1:TaxVerificationDate', '2019-01-01');
// $TaxRegistration_node->appendChild($child_node_TaxVerificationDate);

$company_node->appendChild($TaxRegistration_node);
/*-------------------- End TaxRegistration ----------------*/
/*-------------------- BankAccount ------------------------*/
$BankAccount_node = $dom->createElement('n1:BankAccount');

appendSaftTextElement($dom, $BankAccount_node, 'n1:BankAccountNumber', $BankAccountNumber);

appendSaftTextElement($dom, $BankAccount_node, 'n1:BankAccountName', $BankAccountName);

// $child_node_SortCode = $dom->createElement('n1:SortCode', '099009999');
// $BankAccount_node->appendChild($child_node_SortCode);

appendSaftTextElement($dom, $BankAccount_node, 'n1:CurrencyCode', $DefaultCurrencyCode);
if ($BankRegNumber != '') {
    appendSaftTextElement($dom, $BankAccount_node, 'n1:AccountID', $BankRegNumber);
}
$company_node->appendChild($BankAccount_node);
/*-------------------- End BankAccount --------------------*/
$header_node->appendChild($company_node);
/*-------------------- End Company ------------------------*/
appendSaftTextElement($dom, $header_node, 'n1:DefaultCurrencyCode', $DefaultCurrencyCode);
/*-------------------- SelectionCriteria ------------------*/
$SelectionCriteria_node = $dom->createElement('n1:SelectionCriteria');

appendSaftTextElement($dom, $SelectionCriteria_node, 'n1:PeriodStart', $startmaaned);

appendSaftTextElement($dom, $SelectionCriteria_node, 'n1:PeriodStartYear', $aar_fra);

appendSaftTextElement($dom, $SelectionCriteria_node, 'n1:PeriodEnd', $slutmaaned);

appendSaftTextElement($dom, $SelectionCriteria_node, 'n1:PeriodEndYear', $aar_til);

$header_node->appendChild($SelectionCriteria_node);
/*-------------------- End SelectionCriteria --------------*/
appendSaftTextElement($dom, $header_node, 'n1:TaxAccountingBasis', $TaxAccountingBasis);

appendSaftTextElement($dom, $header_node, 'n1:TaxEntity', $firmanavn);

appendSaftTextElement($dom, $header_node, 'n1:UserID', $UserID);

$root->appendChild($header_node);
/*-------------------- End Header -------------------------*/
/*-------------------- Start MasterFiles ------------------*/
$masterFiles_node = $dom->createElement('n1:MasterFiles');

$generalLedgerAccounts_node = $dom->createElement('n1:GeneralLedgerAccounts');
// loop through all accounts here
for ($x = 1; $x <= $kontoantal; $x++) {
    $account_node = $dom->createElement('n1:Account');

    appendSaftTextElement($dom, $account_node, 'n1:AccountID', $standardKontonr[$x]);

    appendSaftTextElement($dom, $account_node, 'n1:AccountDescription', $kontobeskrivelse[$x]);
    if ($standardKontonr[$x] != '') {
        appendSaftTextElement($dom, $account_node, 'n1:StandardAccountID', $standardKontonr[$x]);
    }
    appendSaftTextElement($dom, $account_node, 'n1:AccountType', $kontotype[$x]);
    if ($openingDbCr[$x] < 0) {
        appendSaftTextElement($dom, $account_node, 'n1:OpeningCreditBalance', number_format(abs($openingDbCr[$x]), 2, '.', ''));
    } else {
        appendSaftTextElement($dom, $account_node, 'n1:OpeningDebitBalance', $openingDbCr[$x]);
    }
    if ($closingDbCr[$x] < 0) {
        appendSaftTextElement($dom, $account_node, 'n1:ClosingCreditBalance', number_format(abs($closingDbCr[$x]), 2, '.', ''));
    } else {
        appendSaftTextElement($dom, $account_node, 'n1:ClosingDebitBalance', $closingDbCr[$x]);
    }
    $generalLedgerAccounts_node->appendChild($account_node);
}
// End loop here

$masterFiles_node->appendChild($generalLedgerAccounts_node);

$root->appendChild($masterFiles_node);

$dom->appendChild($root);

array_map('unlink', glob($xml_file_path . "*.xml")); // delete all files in folder

$dom->save($xml_file_name, LIBXML_NOEMPTYTAG);


$_SESSION['fileName'] = "$AuditFileName";
$_SESSION['filePath'] = "$xml_file_name";
$_SESSION['fileMessage'] = "$AuditFileName " . findtekst(2352, $sprog_id) . ""; // er blevet oprettet.
// echo $xml_file_name;
// echo '<script>location.replace("../finans/rapport_includes/saft.php");</script>';
// redirect($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart);
echo '<script>location.replace("saft.php?regnaar=' . $regnaar . '&maaned_fra=' . $maaned_fra . '&maaned_til=' . $maaned_til . '&aar_fra=' . $aar_fra . '&aar_til=' . $aar_til . '&dato_fra=' . $dato_fra . '&dato_til=' . $dato_til . '&konto_fra=' . $konto_fra . '&konto_til=' . $konto_til . '&rapportart=' . $rapportart . '");</script>';
// exit(); // IMPORTANT
