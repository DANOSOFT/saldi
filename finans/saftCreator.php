<?php
@session_start();
$s_id = session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("saftCreatorUtil/address.php");
include("saft.php");

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

$child_node_AuditFileVersion = $dom->createElement('n1:AuditFileVersion', $AuditFileVersion);
$header_node->appendChild($child_node_AuditFileVersion);

$child_node_AuditFileCountry = $dom->createElement('n1:AuditFileCountry', $Country);
$header_node->appendChild($child_node_AuditFileCountry);
if ($Region != '') {
    $child_node_AuditFileRegion = $dom->createElement('n1:AuditFileRegion', $Region);
    $header_node->appendChild($child_node_AuditFileRegion);
}
$child_node_AuditFileDateCreated = $dom->createElement('n1:AuditFileDateCreated', $AuditFileDateCreated);
$header_node->appendChild($child_node_AuditFileDateCreated);

$child_node_SoftwareCompanyName = $dom->createElement('n1:SoftwareCompanyName', $SoftwareCompanyName);
$header_node->appendChild($child_node_SoftwareCompanyName);

$child_node_SoftwareID = $dom->createElement('n1:SoftwareID', $SoftwareID);
$header_node->appendChild($child_node_SoftwareID);

$child_node_SoftwareVersion = $dom->createElement('n1:SoftwareVersion', $SoftwareVersion);
$header_node->appendChild($child_node_SoftwareVersion);
/*--------------------- Company -----------------------*/
$company_node = $dom->createElement('n1:Company');

$child_node_RegistrationNumber = $dom->createElement('n1:RegistrationNumber', $RegistrationNumber);
$company_node->appendChild($child_node_RegistrationNumber);

$child_node_Name = $dom->createElement('n1:Name', $firmanavn);
$company_node->appendChild($child_node_Name);
/*---------------------- Address ----------------------*/
$address_node = $dom->createElement('n1:Address');

address($dom, $address_node, $StreetName, $StreetNumber, $AdditionalAddressDetail, $City, $PostalCode, $Region, $Country, $AddressType); // function test
// $child_node_StreetName = $dom->createElement('n1:StreetName', $StreetName);
// $address_node->appendChild($child_node_StreetName);

// $child_node_Number = $dom->createElement('n1:Number', $StreetNumber);
// $address_node->appendChild($child_node_Number);

// $child_node_AdditionalAddressDetail = $dom->createElement('n1:AdditionalAddressDetail', $AdditionalAddressDetail);
// $address_node->appendChild($child_node_AdditionalAddressDetail);

// $child_node_Building = $dom->createElement('n1:Building', $address_Building);
// $address_node->appendChild($child_node_Building);

// $child_node_City = $dom->createElement('n1:City', $City);
// $address_node->appendChild($child_node_City);

// $child_node_PostalCode = $dom->createElement('n1:PostalCode', $PostalCode);
// $address_node->appendChild($child_node_PostalCode);

// $child_node_Region = $dom->createElement('n1:Region', $Region);
// $address_node->appendChild($child_node_Region);

// $child_node_Country = $dom->createElement('n1:Country', $Country);
// $address_node->appendChild($child_node_Country);

// $child_node_AddressType = $dom->createElement('n1:AddressType', $AddressType);
// $address_node->appendChild($child_node_AddressType);

$company_node->appendChild($address_node);
/*-------------------- End Address ------------------------*/
/*-------------------- Contact ----------------------------*/
$contact_node = $dom->createElement('n1:Contact');
/*-------------------- ContactPerson ----------------------*/
$contact_person_node = $dom->createElement('n1:ContactPerson');

// $child_node_Title = $dom->createElement('n1:Title', 'Fru');
// $contact_person_node->appendChild($child_node_Title);

$child_node_FirstName = $dom->createElement('n1:FirstName', $ContactPersonName);
$contact_person_node->appendChild($child_node_FirstName);

$child_node_Initials = $dom->createElement('n1:Initials', $ContactInitials);
$contact_person_node->appendChild($child_node_Initials);

// $child_node_LastNamePrefix = $dom->createElement('n1:LastNamePrefix', 'Von');
// $contact_person_node->appendChild($child_node_LastNamePrefix);

$child_node_LastName = $dom->createElement('n1:LastName', $ContactLastName);
$contact_person_node->appendChild($child_node_LastName);

// $child_node_BirthName = $dom->createElement('n1:BirthName', $ContactName);
// $contact_person_node->appendChild($child_node_BirthName);

// $child_node_Salutation = $dom->createElement('n1:Salutation', 'Skibsredder');
// $contact_person_node->appendChild($child_node_Salutation);

// $child_node_OtherTitles = $dom->createElement('n1:OtherTitles', 'Direktør');
// $contact_person_node->appendChild($child_node_OtherTitles);

$contact_node->appendChild($contact_person_node);
/*-------------------- End ContactPerson ------------------*/
$child_node_Telephone = $dom->createElement('n1:Telephone', $PhoneNumber);
$contact_node->appendChild($child_node_Telephone);
if ($FaxNumber != '') {
    $child_node_Fax = $dom->createElement('n1:Fax', $FaxNumber);
    $contact_node->appendChild($child_node_Fax);
}
$child_node_Email = $dom->createElement('n1:Email', $Email);
$contact_node->appendChild($child_node_Email);
if ($WebSite != '') {
    $child_node_Website = $dom->createElement('n1:Website', $WebSite);
    $contact_node->appendChild($child_node_Website);
}
$child_node_MobilePhone = $dom->createElement('n1:MobilePhone', $PhoneNumber);
$contact_node->appendChild($child_node_MobilePhone);

$company_node->appendChild($contact_node);
/*-------------------- End Contact ------------------------*/
/*-------------------- TaxRegistration --------------------*/
$TaxRegistration_node = $dom->createElement('n1:TaxRegistration');

$child_node_TaxRegistrationNumber = $dom->createElement('n1:TaxRegistrationNumber', $TaxRegistrationNumber);
$TaxRegistration_node->appendChild($child_node_TaxRegistrationNumber);

$child_node_TaxType = $dom->createElement('n1:TaxType', $TaxType);
$TaxRegistration_node->appendChild($child_node_TaxType);

$child_node_TaxNumber = $dom->createElement('n1:TaxNumber', $TaxRegistrationNumber);
$TaxRegistration_node->appendChild($child_node_TaxNumber);

$child_node_TaxAuthority = $dom->createElement('n1:TaxAuthority', $TaxAuthority);
$TaxRegistration_node->appendChild($child_node_TaxAuthority);

// $child_node_TaxVerificationDate = $dom->createElement('n1:TaxVerificationDate', '2019-01-01');
// $TaxRegistration_node->appendChild($child_node_TaxVerificationDate);

$company_node->appendChild($TaxRegistration_node);
/*-------------------- End TaxRegistration ----------------*/
/*-------------------- BankAccount ------------------------*/
$BankAccount_node = $dom->createElement('n1:BankAccount');

$child_node_BankAccountNumber = $dom->createElement('n1:BankAccountNumber', $BankAccountNumber);
$BankAccount_node->appendChild($child_node_BankAccountNumber);

$child_node_BankAccountName = $dom->createElement('n1:BankAccountName', $BankAccountName);
$BankAccount_node->appendChild($child_node_BankAccountName);

// $child_node_SortCode = $dom->createElement('n1:SortCode', '099009999');
// $BankAccount_node->appendChild($child_node_SortCode);

$child_node_CurrencyCode = $dom->createElement('n1:CurrencyCode', $DefaultCurrencyCode);
$BankAccount_node->appendChild($child_node_CurrencyCode);
if ($BankRegNumber != '') {
    $child_node_AccountID = $dom->createElement('n1:AccountID', $BankRegNumber);
    $BankAccount_node->appendChild($child_node_AccountID);
}
$company_node->appendChild($BankAccount_node);
/*-------------------- End BankAccount --------------------*/
$header_node->appendChild($company_node);
/*-------------------- End Company ------------------------*/
$child_node_DefaultCurrencyCode = $dom->createElement('n1:DefaultCurrencyCode', $DefaultCurrencyCode);
$header_node->appendChild($child_node_DefaultCurrencyCode);
/*-------------------- SelectionCriteria ------------------*/
$SelectionCriteria_node = $dom->createElement('n1:SelectionCriteria');

$child_node_PeriodStart = $dom->createElement('n1:PeriodStart', $startmaaned);
$SelectionCriteria_node->appendChild($child_node_PeriodStart);

$child_node_PeriodStartYear = $dom->createElement('n1:PeriodStartYear', $aar_fra);
$SelectionCriteria_node->appendChild($child_node_PeriodStartYear);

$child_node_PeriodEnd = $dom->createElement('n1:PeriodEnd', $slutmaaned);
$SelectionCriteria_node->appendChild($child_node_PeriodEnd);

$child_node_PeriodEndYear = $dom->createElement('n1:PeriodEndYear', $aar_til);
$SelectionCriteria_node->appendChild($child_node_PeriodEndYear);

$header_node->appendChild($SelectionCriteria_node);
/*-------------------- End SelectionCriteria --------------*/
$child_node_TaxAccountingBasis = $dom->createElement('n1:TaxAccountingBasis', $TaxAccountingBasis);
$header_node->appendChild($child_node_TaxAccountingBasis);

$child_node_TaxEntity = $dom->createElement('n1:TaxEntity', $firmanavn);
$header_node->appendChild($child_node_TaxEntity);

$child_node_UserID = $dom->createElement('n1:UserID', $UserID);
$header_node->appendChild($child_node_UserID);

$root->appendChild($header_node);
/*-------------------- End Header -------------------------*/
/*-------------------- Start MasterFiles ------------------*/
$masterFiles_node = $dom->createElement('n1:MasterFiles');

$generalLedgerAccounts_node = $dom->createElement('n1:GeneralLedgerAccounts');
// loop through all accounts here
for ($x = 1; $x <= $kontoantal; $x++) {
    $account_node = $dom->createElement('n1:Account');

    $child_node_AccountID = $dom->createElement('n1:AccountID', $standardKontonr[$x]);
    $account_node->appendChild($child_node_AccountID);

    $child_node_AccountDescription = $dom->createElement('n1:AccountDescription', htmlspecialchars($kontobeskrivelse[$x]));
    $account_node->appendChild($child_node_AccountDescription);
    if ($standardKontonr[$x] != '') {
        $child_node_StandardAccountID = $dom->createElement('n1:StandardAccountID', $standardKontonr[$x]);
        $account_node->appendChild($child_node_StandardAccountID);
    }
    $child_node_AccountType = $dom->createElement('n1:AccountType', $kontotype[$x]);
    $account_node->appendChild($child_node_AccountType);
    if ($openingDbCr[$x] < 0) {
        $child_node_OpeningCreditBalance = $dom->createElement('n1:OpeningCreditBalance', number_format(abs($openingDbCr[$x]), 2, '.', ''));
        $account_node->appendChild($child_node_OpeningCreditBalance);
    } else {
        $child_node_OpeningDebitBalance = $dom->createElement('n1:OpeningDebitBalance', $openingDbCr[$x]);
        $account_node->appendChild($child_node_OpeningDebitBalance);
    }
    if ($closingDbCr[$x] < 0) {
        $child_node_ClosingCreditBalance = $dom->createElement('n1:ClosingCreditBalance', number_format(abs($closingDbCr[$x]), 2, '.', ''));
        $account_node->appendChild($child_node_ClosingCreditBalance);
    } else {
        $child_node_ClosingDebitBalance = $dom->createElement('n1:ClosingDebitBalance', $closingDbCr[$x]);
        $account_node->appendChild($child_node_ClosingDebitBalance);
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
$_SESSION['fileMessage'] = "$AuditFileName " . findtekst(3051, $sprog_id) . ""; // er blevet oprettet.
// echo $xml_file_name;
// echo '<script>location.replace("../finans/rapport_includes/saft.php");</script>';
// redirect($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart);
echo '<script>location.replace("saft.php?regnaar=' . $regnaar . '&maaned_fra=' . $maaned_fra . '&maaned_til=' . $maaned_til . '&aar_fra=' . $aar_fra . '&aar_til=' . $aar_til . '&dato_fra=' . $dato_fra . '&dato_til=' . $dato_til . '&konto_fra=' . $konto_fra . '&konto_til=' . $konto_til . '&rapportart=' . $rapportart . '");</script>';
// exit(); // IMPORTANT 