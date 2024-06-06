<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/saftCashRegisterCreator.php --- patch 4.0.8 --- 2023-10-27 ---
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
$auditSender = false;
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("saftCashRegister.php");

$vatCodeDetail_count = if_isset($_POST['vatCodeDetail_count']);
$vatCodeString = if_isset($_POST['vatCode']);
$vatCode = unserialize($vatCodeString);
$vatCodeDetails_dateOfEntryString = if_isset($_POST['vatCodeDetails_dateOfEntry']);
$vatCodeDetails_dateOfEntry = unserialize($vatCodeDetails_dateOfEntryString);
$vatDescString = if_isset($_POST['vatDesc']);
$vatDesc = unserialize($vatDescString);
$standardVatCodeString = if_isset($_POST['standardVatCode']);
$standardVatCode = unserialize($standardVatCodeString);
$startDatePeriod = if_isset($_POST['startDatePeriod']);
$endDatePeriod = if_isset($_POST['endDatePeriod']);
$employee_count = if_isset($_POST['employee_count']);
$empIDString = if_isset($_POST['empIDString']);
$empID = unserialize($empIDString);
$employees_dateOfEntryString = if_isset($_POST['employees_dateOfEntryString']);
$employees_dateOfEntry = unserialize($employees_dateOfEntryString);
$timeOfEntryString = if_isset($_POST['timeOfEntryString']);
$timeOfEntry = unserialize($timeOfEntryString);
$firstNameString = if_isset($_POST['firstNameString']);
$firstName = unserialize($firstNameString);
$surNameString = if_isset($_POST['surNameString']);
$surName = unserialize($surNameString);
$article_count = if_isset($_POST['article_count']);
$artIDString = if_isset($_POST['artIDString']);
$artID = unserialize($artIDString);
$dateOfEntryString = if_isset($_POST['dateOfEntryString']);
$dateOfEntry = unserialize($dateOfEntryString);
$artGroupIDString = if_isset($_POST['artGroupIDString']);
$artGroupID = unserialize($artGroupIDString);
$artDescString = if_isset($_POST['artDescString']);
$artDesc = unserialize($artDescString);
$basic_count = if_isset($_POST['basic_count']);
$basicTypeString = if_isset($_POST['basicTypeString']);
$basicType = unserialize($basicTypeString);
$basicIDString = if_isset($_POST['basicIDString']);
$basicID = unserialize($basicIDString);
$predefinedBasicIDString = if_isset($_POST['predefinedBasicIDString']);
$predefinedBasicID = unserialize($predefinedBasicIDString);
$basicDescString = if_isset($_POST['basicDescString']);
$basicDesc = unserialize($basicDescString);
$event_count = if_isset($_POST['event_count']);
$eventIDString = if_isset($_POST['eventIDString']);
$eventID = unserialize($eventIDString);
$eventTypeString = if_isset($_POST['eventTypeString']);
$eventType = unserialize($eventTypeString);
$transIDString = if_isset($_POST['transIDString']);
$transID = unserialize($transIDString);
$event_empIDString = if_isset($_POST['event_empIDString']);
$event_empID = unserialize($event_empIDString);
$eventDateString = if_isset($_POST['eventDateString']);
$eventDate = unserialize($eventDateString);
$eventTimeString = if_isset($_POST['eventTimeString']);
$eventTime = unserialize($eventTimeString);
$eventTextString = if_isset($_POST['eventTextString']);
$eventText = unserialize($eventTextString);
$reportArtGroup_count = if_isset($_POST['reportArtGroup_count']);
$eventReport_artGroupIDString = if_isset($_POST['eventReport_artGroupIDString']);
$eventReport_artGroupID = unserialize($eventReport_artGroupIDString);
$artGroupNumString = if_isset($_POST['artGroupNumString']);
$artGroupNum = unserialize($artGroupNumString);
$artGroupAmntString = if_isset($_POST['artGroupAmntString']);
$artGroupAmnt = unserialize($artGroupAmntString);
$reportPayment_count = if_isset($_POST['reportPayment_count']);
$paymentTypeString = if_isset($_POST['paymentTypeString']);
$paymentType = unserialize($paymentTypeString);
$paymentNumString = if_isset($_POST['paymentNumString']);
$paymentNum = unserialize($paymentNumString);
$paymentAmntString = if_isset($_POST['paymentAmntString']);
$paymentAmnt = unserialize($paymentAmntString);
$reportEmpPayment_count = if_isset($_POST['reportEmpPayment_count']);
$reportEmpPayments_empIDString = if_isset($_POST['reportEmpPayments_empIDString']);
$reportEmpPayments_empID = unserialize($reportEmpPayments_empIDString);
$reportEmpPayments_paymentTypeString = if_isset($_POST['reportEmpPayments_paymentTypeString']);
$reportEmpPayments_paymentType = unserialize($reportEmpPayments_paymentTypeString);
$paymentEmpNumString = if_isset($_POST['paymentEmpNumString']);
$paymentEmpNum = unserialize($paymentEmpNumString);
$paymentEmpAmntString = if_isset($_POST['paymentEmpAmntString']);
$paymentEmpAmnt = unserialize($paymentEmpAmntString);
$reportCashSaleVat_count = if_isset($_POST['reportCashSaleVat_count']);
$vatPercString = if_isset($_POST['vatPercString']);
$vatPerc = unserialize($vatPercString);
$cashSaleAmntString = if_isset($_POST['cashSaleAmntString']);
$cashSaleAmnt = unserialize($cashSaleAmntString);
$vatAmntString = if_isset($_POST['vatAmntString']);
$vatAmnt = unserialize($vatAmntString);
$vatAmntTpString = if_isset($_POST['vatAmntTpString']);
$vatAmntTp = unserialize($vatAmntTpString);
$cashtransaction_count = if_isset($_POST['cashtransaction_count']);
$cashtransaction_nrString = if_isset($_POST['cashtransaction_nrString']);
$cashtransaction_nr = unserialize($cashtransaction_nrString);
$cashtransaction_transIDString = if_isset($_POST['cashtransaction_transIDString']);
$cashtransaction_transID = unserialize($cashtransaction_transIDString);
$transTypeString = if_isset($_POST['transTypeString']);
$transType = unserialize($transTypeString);
$transAmntInString = if_isset($_POST['transAmntInString']);
$transAmntIn = unserialize($transAmntInString);
$transAmntExString = if_isset($_POST['transAmntExString']);
$transAmntEx = unserialize($transAmntExString);
$cashtransaction_amntTpString = if_isset($_POST['cashtransaction_amntTpString']);
$cashtransaction_amntTp = unserialize($cashtransaction_amntTpString);
$cashtransaction_empIDString = if_isset($_POST['cashtransaction_empIDString']);
$cashtransaction_empID = unserialize($cashtransaction_empIDString);
$transDateString = if_isset($_POST['transDateString']);
$transDate = unserialize($transDateString);
$transTimeString = if_isset($_POST['transTimeString']);
$transTime = unserialize($transTimeString);
$ctLine_count = if_isset($_POST['ctLine_count']);
$ctLine_nrString = if_isset($_POST['ctLine_nrString']);
$ctLine_nr = unserialize($ctLine_nrString);
$lineIDString = if_isset($_POST['lineIDString']);
$lineID = unserialize($lineIDString);
$lineTypeString = if_isset($_POST['lineTypeString']);
$lineType = unserialize($lineTypeString);
$ctLine_artGroupIDString = if_isset($_POST['ctLine_artGroupIDString']);
$ctLine_artGroupID = unserialize($ctLine_artGroupIDString);
$ctLine_artIDString = if_isset($_POST['ctLine_artIDString']);
$ctLine_artID = unserialize($ctLine_artIDString);
$qntString = if_isset($_POST['qntString']);
$qnt = unserialize($qntString);
$lineAmntInString = if_isset($_POST['lineAmntInString']);
$lineAmntIn = unserialize($lineAmntInString);
$lineAmntExString = if_isset($_POST['lineAmntExString']);
$lineAmntEx = unserialize($lineAmntExString);
$ctLine_amntTpString = if_isset($_POST['ctLine_amntTpString']);
$ctLine_amntTp = unserialize($ctLine_amntTpString);
$vat_vatPercString = if_isset($_POST['vat_vatPercString']);
$vat_vatPerc = unserialize($vat_vatPercString);
$vat_vatAmntString = if_isset($_POST['vat_vatAmntString']);
$vat_vatAmnt = unserialize($vat_vatAmntString);
$vat_vatAmntTpString = if_isset($_POST['vat_vatAmntTpString']);
$vat_vatAmntTp = unserialize($vat_vatAmntTpString);
$vatBasAmntString = if_isset($_POST['vatBasAmntString']);
$vatBasAmnt = unserialize($vatBasAmntString);
$payment_paymentTypeString = if_isset($_POST['payment_paymentTypeString']);
$payment_paymentType = unserialize($payment_paymentTypeString);
$paidAmntString = if_isset($_POST['paidAmntString']);
$paidAmnt = unserialize($paidAmntString);
$payment_empIDString = if_isset($_POST['payment_empIDString']);
$payment_empID = unserialize($payment_empIDString);
$payment_curCodeString = if_isset($_POST['payment_curCodeString']);
$payment_curCode = unserialize($payment_curCodeString);

if ($taxRegistrationCountry == 'NO') {
    $attr1 = 'urn:StandardAuditFile-Taxation-CashRegister:NO';
    $attr2 = 'http://www.w3.org/2001/XMLSchema-instance';
    $attr3 = 'urn:StandardAuditFile-Taxation-CashRegister:NO Norwegian_SAF-T_Cash_Register_Schema_v_1.00.xsd';
} else {
    $attr1 = 'urn:StandardAuditFile-Taxation-CashRegister:DK';
    $attr2 = 'http://www.w3.org/2001/XMLSchema-instance';
    $attr3 = 'urn:StandardAuditFile-Taxation-CashRegister:DK Danish_SAF-T_Cash_Register_Schema_v_1.0.2.xsd';
}

$prfx = ($taxRegistrationCountry != 'NO') ? 'd1:' : '';
$prfx_attr = ($taxRegistrationCountry != 'NO') ? ':d1' : '';

$dateCreated = date("Y-m-d");

$timeCreated = date("H:i:s");

$AuditFileDateTimeCreated = date("YmdHis");

$AuditFileName = "SAF-T Cash Register_" . $companyIdent . "_" . $AuditFileDateTimeCreated . ".xml"; // Use cvr

$dom = new DOMDocument();

$dom->encoding = 'utf-8';

$dom->xmlVersion = '1.0';

$dom->formatOutput = true;

$dom->preserveWhiteSpace = FALSE;

$xml_file_path = "../temp/$db/cashRegister/";
if (!is_dir($xml_file_path))
    mkdir($xml_file_path, 0777, true);

$xml_file_name = $xml_file_path . $AuditFileName;

$root = $dom->createElement($prfx . 'AuditFile');

$attr1_root = new DOMAttr('xmlns' . $prfx_attr, $attr1);
$attr2_root = new DOMAttr('xmlns:xsi', $attr2);
$attr3_root = new DOMAttr('xsi:schemaLocation', $attr3);

$root->setAttributeNode($attr1_root);
$root->setAttributeNode($attr2_root);
$root->setAttributeNode($attr3_root);

$header_node = $dom->createElement($prfx . 'header');

$child_node_fiscalYear = $dom->createElement($prfx . 'fiscalYear', $fiscalYear);
$header_node->appendChild($child_node_fiscalYear);

$child_node_startDate = $dom->createElement($prfx . 'startDate', $startDate);
$header_node->appendChild($child_node_startDate);

$child_node_endDate = $dom->createElement($prfx . 'endDate', $endDate);
$header_node->appendChild($child_node_endDate);

$child_node_curCode = $dom->createElement($prfx . 'curCode', $curCode);
$header_node->appendChild($child_node_curCode);

$child_node_dateCreated = $dom->createElement($prfx . 'dateCreated', $dateCreated);
$header_node->appendChild($child_node_dateCreated);

$child_node_timeCreated = $dom->createElement($prfx . 'timeCreated', $timeCreated);
$header_node->appendChild($child_node_timeCreated);

$child_node_softwareDesc = $dom->createElement($prfx . 'softwareDesc', $softwareDesc);
$header_node->appendChild($child_node_softwareDesc);

$child_node_softwareVersion = $dom->createElement($prfx . 'softwareVersion', $softwareVersion);
$header_node->appendChild($child_node_softwareVersion);

$child_node_softwareCompanyName = $dom->createElement($prfx . 'softwareCompanyName', $softwareCompanyName);
$header_node->appendChild($child_node_softwareCompanyName);

$child_node_auditfileVersion = $dom->createElement($prfx . 'auditfileVersion', $auditfileVersion);
$header_node->appendChild($child_node_auditfileVersion);

// $child_node_headerComment = $dom->createElement($prfx . 'headerComment', $headerComment);
// $header_node->appendChild($child_node_headerComment);

$child_node_userID = $dom->createElement($prfx . 'userID', $userID);
$header_node->appendChild($child_node_userID);
// IF auditfileSender is not the company that ows the data (accounting office, parent company, etc.)
if ($auditSender) {
    $auditfileSender_node = $dom->createElement($prfx . 'auditfileSender');

    $child_node_companyIdent = $dom->createElement($prfx . 'companyIdent', $companyIdent);
    $auditfileSender_node->appendChild($child_node_companyIdent);

    $child_node_companyName = $dom->createElement($prfx . 'companyName', $companyName);
    $auditfileSender_node->appendChild($child_node_companyName);

    $child_node_taxRegistrationCountry = $dom->createElement($prfx . 'taxRegistrationCountry', $taxRegistrationCountry);
    $auditfileSender_node->appendChild($child_node_taxRegistrationCountry);

    $child_node_taxRegIdent = $dom->createElement($prfx . 'taxRegIdent', $taxRegIdent);
    $auditfileSender_node->appendChild($child_node_taxRegIdent);

    $streetAddress_node = $dom->createElement($prfx . 'streetAddress');

    $child_node_streetname = $dom->createElement($prfx . 'streetname', $streetAddress_streetname);
    $streetAddress_node->appendChild($child_node_streetname);

    $child_node_number = $dom->createElement($prfx . 'number', $streetAddress_number);
    $streetAddress_node->appendChild($child_node_number);

    $child_node_building = $dom->createElement($prfx . 'building', $streetAddress_building);
    $streetAddress_node->appendChild($child_node_building);

    $child_node_additionalAddressDetails = $dom->createElement($prfx . 'additionalAddressDetails', $streetAddress_additionalAddressDetails);
    $streetAddress_node->appendChild($child_node_additionalAddressDetails);

    $child_node_city = $dom->createElement($prfx . 'city', $streetAddress_city);
    $streetAddress_node->appendChild($child_node_city);

    $child_node_postalCode = $dom->createElement($prfx . 'postalCode', $streetAddress_postalCode);
    $streetAddress_node->appendChild($child_node_postalCode);

    $child_node_country = $dom->createElement($prfx . 'country', $streetAddress_country);
    $streetAddress_node->appendChild($child_node_country);

    $auditfileSender_node->appendChild($streetAddress_node);

    $postalAddress_node = $dom->createElement($prfx . 'postalAddress');

    $child_node_streetname = $dom->createElement($prfx . 'streetname', $postalAddress_streetname);
    $postalAddress_node->appendChild($child_node_streetname);

    $child_node_number = $dom->createElement($prfx . 'number', $postalAddress_number);
    $postalAddress_node->appendChild($child_node_number);

    $child_node_building = $dom->createElement($prfx . 'building', $postalAddress_building);
    $postalAddress_node->appendChild($child_node_building);

    $child_node_additionalAddressDetails = $dom->createElement($prfx . 'additionalAddressDetails', $postalAddress_additionalAddressDetails);
    $postalAddress_node->appendChild($child_node_additionalAddressDetails);

    $child_node_city = $dom->createElement($prfx . 'city', $postalAddress_city);
    $postalAddress_node->appendChild($child_node_city);

    $child_node_postalCode = $dom->createElement($prfx . 'postalCode', $postalAddress_postalCode);
    $postalAddress_node->appendChild($child_node_postalCode);

    $child_node_country = $dom->createElement($prfx . 'country', $postalAddress_country);
    $postalAddress_node->appendChild($child_node_country);

    $auditfileSender_node->appendChild($postalAddress_node);


    $header_node->appendChild($auditfileSender_node);
}
$root->appendChild($header_node);
/*-------------------- End Header -------------------------*/

/*--------------------- Company -----------------------*/
$company_node = $dom->createElement($prfx . 'company');

$child_node_companyIdent = $dom->createElement($prfx . 'companyIdent', $companyIdent);
$company_node->appendChild($child_node_companyIdent);

$child_node_companyName = $dom->createElement($prfx . 'companyName', $companyName);
$company_node->appendChild($child_node_companyName);

$child_node_taxRegistrationCountry = $dom->createElement($prfx . 'taxRegistrationCountry', $taxRegistrationCountry);
$company_node->appendChild($child_node_taxRegistrationCountry);

$child_node_taxRegIdent = $dom->createElement($prfx . 'taxRegIdent', $taxRegIdent);
$company_node->appendChild($child_node_taxRegIdent);

/*---------------------- Address ----------------------*/

$streetAddress_node = $dom->createElement($prfx . 'streetAddress');

$child_node_streetname = $dom->createElement($prfx . 'streetname', $streetAddress_streetname);
$streetAddress_node->appendChild($child_node_streetname);

$child_node_number = $dom->createElement($prfx . 'number', $streetAddress_number);
$streetAddress_node->appendChild($child_node_number);

// $child_node_building = $dom->createElement($prfx . 'building', $streetAddress_building);
// $streetAddress_node->appendChild($child_node_building);
if ($streetAddress_additionalAddressDetails) {
    $child_node_additionalAddressDetails = $dom->createElement($prfx . 'additionalAddressDetails', $streetAddress_additionalAddressDetails);
    $streetAddress_node->appendChild($child_node_additionalAddressDetails);
}
$child_node_city = $dom->createElement($prfx . 'city', $streetAddress_city);
$streetAddress_node->appendChild($child_node_city);

$child_node_postalCode = $dom->createElement($prfx . 'postalCode', $streetAddress_postalCode);
$streetAddress_node->appendChild($child_node_postalCode);

$child_node_country = $dom->createElement($prfx . 'country', $streetAddress_country);
$streetAddress_node->appendChild($child_node_country);

$company_node->appendChild($streetAddress_node);

$postalAddress_node = $dom->createElement($prfx . 'postalAddress');

$child_node_streetname = $dom->createElement($prfx . 'streetname', $postalAddress_streetname);
$postalAddress_node->appendChild($child_node_streetname);

$child_node_number = $dom->createElement($prfx . 'number', $postalAddress_number);
$postalAddress_node->appendChild($child_node_number);

// $child_node_building = $dom->createElement($prfx . 'building', $postalAddress_building);
// $postalAddress_node->appendChild($child_node_building);
if ($postalAddress_additionalAddressDetails) {
    $child_node_additionalAddressDetails = $dom->createElement($prfx . 'additionalAddressDetails', $postalAddress_additionalAddressDetails);
    $postalAddress_node->appendChild($child_node_additionalAddressDetails);
}
$child_node_city = $dom->createElement($prfx . 'city', $postalAddress_city);
$postalAddress_node->appendChild($child_node_city);

$child_node_postalCode = $dom->createElement($prfx . 'postalCode', $postalAddress_postalCode);
$postalAddress_node->appendChild($child_node_postalCode);

$child_node_country = $dom->createElement($prfx . 'country', $postalAddress_country);
$postalAddress_node->appendChild($child_node_country);

$company_node->appendChild($postalAddress_node);

/*--------------------- vatCodeDetails ----------------*/
$vatCodeDetails_node = $dom->createElement($prfx . 'vatCodeDetails');

for ($x = 1; $x <= $vatCodeDetail_count; $x++) {
    $vatCodeDetail_node = $dom->createElement($prfx . 'vatCodeDetail');

    $child_node_vatCode = $dom->createElement($prfx . 'vatCode', $vatCode[$x]);
    $vatCodeDetail_node->appendChild($child_node_vatCode);

    $child_node_dateOfEntry = $dom->createElement($prfx . 'dateOfEntry', $vatCodeDetails_dateOfEntry[$x]);
    $vatCodeDetail_node->appendChild($child_node_dateOfEntry);

    $child_node_vatDesc = $dom->createElement($prfx . 'vatDesc', $vatDesc[$x]);
    $vatCodeDetail_node->appendChild($child_node_vatDesc);

    $child_node_standardVatCode = $dom->createElement($prfx . 'standardVatCode', $standardVatCode[$x]);
    $vatCodeDetail_node->appendChild($child_node_standardVatCode);

    $vatCodeDetails_node->appendChild($vatCodeDetail_node);
}
$company_node->appendChild($vatCodeDetails_node);

/*------------------ periods ------------------*/
$periods_node = $dom->createElement($prfx . 'periods');

// for ($x = 0; $x < 1; $x++) {
$period_node = $dom->createElement($prfx . 'period');

$child_node_periodNumber = $dom->createElement($prfx . 'periodNumber', $periodNumber);
$period_node->appendChild($child_node_periodNumber);

// $child_node_periodDesc = $dom->createElement($prfx . 'periodDesc', $periodDesc);
// $period_node->appendChild($child_node_periodDesc);

$child_node_startDatePeriod = $dom->createElement($prfx . 'startDatePeriod', $startDatePeriod);
$period_node->appendChild($child_node_startDatePeriod);

$child_node_startTimePeriod = $dom->createElement($prfx . 'startTimePeriod', $startTimePeriod);
$period_node->appendChild($child_node_startTimePeriod);

$child_node_endDatePeriod = $dom->createElement($prfx . 'endDatePeriod', $endDatePeriod);
$period_node->appendChild($child_node_endDatePeriod);

$child_node_endTimePeriod = $dom->createElement($prfx . 'endTimePeriod', $endTimePeriod);
$period_node->appendChild($child_node_endTimePeriod);

$periods_node->appendChild($period_node);
// }
$company_node->appendChild($periods_node);

/*------------------ employees --------------------*/
$employees_node = $dom->createElement($prfx . 'employees');

for ($x = 1; $x <= $employee_count; $x++) {
    $employee_node = $dom->createElement($prfx . 'employee');

    $child_node_empID = $dom->createElement($prfx . 'empID', $empID[$x]);
    $employee_node->appendChild($child_node_empID);

    $child_node_dateOfEntry = $dom->createElement($prfx . 'dateOfEntry', $employees_dateOfEntry[$x]);
    $employee_node->appendChild($child_node_dateOfEntry);

    $child_node_timeOfEntry = $dom->createElement($prfx . 'timeOfEntry', $timeOfEntry[$x]);
    $employee_node->appendChild($child_node_timeOfEntry);

    $child_node_firstName = $dom->createElement($prfx . 'firstName', $firstName[$x]);
    $employee_node->appendChild($child_node_firstName);

    $child_node_surName = $dom->createElement($prfx . 'surName', $surName[$x]);
    $employee_node->appendChild($child_node_surName);

    // $employeeRole_node = $dom->createElement($prfx . 'employeeRole');

    // $child_node_roleType = $dom->createElement($prfx . 'roleType', $roleType[$x]);
    // $employeeRole_node->appendChild($child_node_roleType);

    // $child_node_roleTypeDesc = $dom->createElement($prfx . 'roleTypeDesc', $roleTypeDesc[$x]);
    // $employeeRole_node->appendChild($child_node_roleTypeDesc);

    // $employee_node->appendChild($employeeRole_node);

    $employees_node->appendChild($employee_node);
}
$company_node->appendChild($employees_node);

/*------------------ article -------------------*/
$articles_node = $dom->createElement($prfx . 'articles');

for ($x = 1; $x <= $article_count; $x++) {
    $article_node = $dom->createElement($prfx . 'article');

    $child_node_artID = $dom->createElement($prfx . 'artID', $artID[$x]);
    $article_node->appendChild($child_node_artID);

    $child_node_dateOfEntry = $dom->createElement($prfx . 'dateOfEntry', $dateOfEntry[$x]);
    $article_node->appendChild($child_node_dateOfEntry);

    $child_node_artGroupID = $dom->createElement($prfx . 'artGroupID', $artGroupID[$x]);
    $article_node->appendChild($child_node_artGroupID);

    $child_node_artDesc = $dom->createElement($prfx . 'artDesc', htmlspecialchars($artDesc[$x]));
    $article_node->appendChild($child_node_artDesc);

    $articles_node->appendChild($article_node);
}
$company_node->appendChild($articles_node);

/*------------------- basics ---------------------*/
$basics_node = $dom->createElement($prfx . 'basics');

for ($x = 1; $x <= $basic_count; $x++) {
    $basic_node = $dom->createElement($prfx . 'basic');

    $child_node_basicType = $dom->createElement($prfx . 'basicType', $basicType[$x]);
    $basic_node->appendChild($child_node_basicType);

    $child_node_basicID = $dom->createElement($prfx . 'basicID', $basicID[$x]);
    $basic_node->appendChild($child_node_basicID);

    $child_node_predefinedBasicID = $dom->createElement($prfx . 'predefinedBasicID', $predefinedBasicID[$x]);
    $basic_node->appendChild($child_node_predefinedBasicID);

    $child_node_basicDesc = $dom->createElement($prfx . 'basicDesc', $basicDesc[$x]);
    $basic_node->appendChild($child_node_basicDesc);

    $basics_node->appendChild($basic_node);
}
$company_node->appendChild($basics_node);

/*--------------- location ----------------*/
$location_node = $dom->createElement($prfx . 'location');

$child_node_name = $dom->createElement($prfx . 'name', $location_name);
$location_node->appendChild($child_node_name);

/*---------------------- Address ----------------------*/
$streetAddress_node = $dom->createElement($prfx . 'streetAddress');

$child_node_streetname = $dom->createElement($prfx . 'streetname', $streetAddress_streetname);
$streetAddress_node->appendChild($child_node_streetname);

$child_node_number = $dom->createElement($prfx . 'number', $streetAddress_number);
$streetAddress_node->appendChild($child_node_number);

// $child_node_building = $dom->createElement($prfx . 'building', $streetAddress_building);
// $streetAddress_node->appendChild($child_node_building);

$child_node_additionalAddressDetails = $dom->createElement($prfx . 'additionalAddressDetails', $streetAddress_additionalAddressDetails);
$streetAddress_node->appendChild($child_node_additionalAddressDetails);

$child_node_city = $dom->createElement($prfx . 'city', $streetAddress_city);
$streetAddress_node->appendChild($child_node_city);

$child_node_postalCode = $dom->createElement($prfx . 'postalCode', $streetAddress_postalCode);
$streetAddress_node->appendChild($child_node_postalCode);

$child_node_country = $dom->createElement($prfx . 'country', $streetAddress_country);
$streetAddress_node->appendChild($child_node_country);

$location_node->appendChild($streetAddress_node);

/*----------------- cashregister -----------------*/
$cashregister_node = $dom->createElement($prfx . 'cashregister');

$child_node_registerID = $dom->createElement($prfx . 'registerID', $registerID);
$cashregister_node->appendChild($child_node_registerID);

// $child_node_regDesc = $dom->createElement($prfx . 'regDesc', $regDesc);
// $cashregister_node->appendChild($child_node_regDesc);

/*--------------------- event --------------------*/
for ($x = 1; $x <= $event_count; $x++) {
    $event_node = $dom->createElement($prfx . 'event');

    $child_node_eventID = $dom->createElement($prfx . 'eventID', $eventID[$x]);
    $event_node->appendChild($child_node_eventID);

    $child_node_eventType = $dom->createElement($prfx . 'eventType', $eventType[$x]);
    $event_node->appendChild($child_node_eventType);
    if ($transID[$x]) {
        $child_node_transID = $dom->createElement($prfx . 'transID', $transID[$x]);
        $event_node->appendChild($child_node_transID);
    }
    if ($event_empID[$x]) {
        $child_node_empID = $dom->createElement($prfx . 'empID', $event_empID[$x]);
        $event_node->appendChild($child_node_empID);
    }
    $child_node_eventDate = $dom->createElement($prfx . 'eventDate', $eventDate[$x]);
    $event_node->appendChild($child_node_eventDate);

    $child_node_eventTime = $dom->createElement($prfx . 'eventTime', $eventTime[$x]);
    $event_node->appendChild($child_node_eventTime);
    if ($eventText[$x]) {
        $child_node_eventText = $dom->createElement($prfx . 'eventText', $eventText[$x]);
        $event_node->appendChild($child_node_eventText);
    }

    /*------------------- eventReport ------------------------*/
    if ($eventType[$x] == '13009') {
        $eventReport_node = $dom->createElement($prfx . 'eventReport');

        $child_node_reportID = $dom->createElement($prfx . 'reportID', $reportID);
        $eventReport_node->appendChild($child_node_reportID);

        $child_node_reportType = $dom->createElement($prfx . 'reportType', $eventText[$x]);
        $eventReport_node->appendChild($child_node_reportType);

        $child_node_companyIdent = $dom->createElement($prfx . 'companyIdent', $companyIdent);
        $eventReport_node->appendChild($child_node_companyIdent);

        $child_node_companyName = $dom->createElement($prfx . 'companyName', $companyName);
        $eventReport_node->appendChild($child_node_companyName);

        $child_node_reportDate = $dom->createElement($prfx . 'reportDate', $reportDate);
        $eventReport_node->appendChild($child_node_reportDate);

        $child_node_reportTime = $dom->createElement($prfx . 'reportTime', $reportTime);
        $eventReport_node->appendChild($child_node_reportTime);

        $child_node_registerID = $dom->createElement($prfx . 'registerID', $registerID);
        $eventReport_node->appendChild($child_node_registerID);

        /*--------------- reportTotalCashSales ---------------*/
        $reportTotalCashSales_node = $dom->createElement($prfx . 'reportTotalCashSales');

        $child_node_totalCashSaleAmnt = $dom->createElement($prfx . 'totalCashSaleAmnt', $totalCashSaleAmnt);
        $reportTotalCashSales_node->appendChild($child_node_totalCashSaleAmnt);

        $eventReport_node->appendChild($reportTotalCashSales_node);

        /*------------------- reportArtGroups --------------------*/
        $reportArtGroups_node = $dom->createElement($prfx . 'reportArtGroups');

        for ($y = 1; $y <= $reportArtGroup_count; $y++) {
            $reportArtGroup_node = $dom->createElement($prfx . 'reportArtGroup');

            $child_node_artGroupID = $dom->createElement($prfx . 'artGroupID', $eventReport_artGroupID[$y]);
            $reportArtGroup_node->appendChild($child_node_artGroupID);

            $child_node_artGroupNum = $dom->createElement($prfx . 'artGroupNum', $artGroupNum[$y]);
            $reportArtGroup_node->appendChild($child_node_artGroupNum);

            $child_node_artGroupAmnt = $dom->createElement($prfx . 'artGroupAmnt', $artGroupAmnt[$y]);
            $reportArtGroup_node->appendChild($child_node_artGroupAmnt);

            $reportArtGroups_node->appendChild($reportArtGroup_node);
        }
        $eventReport_node->appendChild($reportArtGroups_node);

        /*---------------- reportPayments --------------*/
        $reportPayments_node = $dom->createElement($prfx . 'reportPayments');

        for ($y = 1; $y <= $reportPayment_count; $y++) {
            $reportPayment_node = $dom->createElement($prfx . 'reportPayment');

            $child_node_paymentType = $dom->createElement($prfx . 'paymentType', $paymentType[$y]);
            $reportPayment_node->appendChild($child_node_paymentType);

            $child_node_paymentNum = $dom->createElement($prfx . 'paymentNum', $paymentNum[$y]);
            $reportPayment_node->appendChild($child_node_paymentNum);

            $child_node_paymentAmnt = $dom->createElement($prfx . 'paymentAmnt', $paymentAmnt[$y]);
            $reportPayment_node->appendChild($child_node_paymentAmnt);

            $reportPayments_node->appendChild($reportPayment_node);
        }

        $eventReport_node->appendChild($reportPayments_node);

        /*--------------------- reportEmpPayments -------------------*/
        $reportEmpPayments_node = $dom->createElement($prfx . 'reportEmpPayments');

        for ($y = 1; $y <= $reportEmpPayment_count; $y++) {
            $reportEmpPayment_node = $dom->createElement($prfx . 'reportEmpPayment');

            $child_node_empID = $dom->createElement($prfx . 'empID', $reportEmpPayments_empID[$y]);
            $reportEmpPayment_node->appendChild($child_node_empID);

            $child_node_paymentType = $dom->createElement($prfx . 'paymentType', $reportEmpPayments_paymentType[$y]);
            $reportEmpPayment_node->appendChild($child_node_paymentType);

            $child_node_paymentNum = $dom->createElement($prfx . 'paymentNum', $paymentEmpNum[$y]);
            $reportEmpPayment_node->appendChild($child_node_paymentNum);

            $child_node_paymentAmnt = $dom->createElement($prfx . 'paymentAmnt', $paymentEmpAmnt[$y]);
            $reportEmpPayment_node->appendChild($child_node_paymentAmnt);

            $reportEmpPayments_node->appendChild($reportEmpPayment_node);
        }
        $eventReport_node->appendChild($reportEmpPayments_node);

        /*----------------- reportCashSalesVat --------------------*/
        $reportCashSalesVat_node = $dom->createElement($prfx . 'reportCashSalesVat');

        for ($y = 1; $y <= $reportCashSaleVat_count; $y++) {
            $reportCashSaleVat_node = $dom->createElement($prfx . 'reportCashSaleVat');

            // $child_node_vatCode = $dom->createElement($prfx . 'vatCode', $reportCashSalesVat_vatCode[$y]);
            // $reportCashSaleVat_node->appendChild($child_node_vatCode);

            $child_node_vatPerc = $dom->createElement($prfx . 'vatPerc', $vatPerc[$y]);
            $reportCashSaleVat_node->appendChild($child_node_vatPerc);

            $child_node_vatCode = $dom->createElement($prfx . 'cashSaleAmnt', $cashSaleAmnt[$y]);
            $reportCashSaleVat_node->appendChild($child_node_vatCode);

            $child_node_vatAmnt = $dom->createElement($prfx . 'vatAmnt', $vatAmnt[$y]);
            $reportCashSaleVat_node->appendChild($child_node_vatAmnt);

            $child_node_vatAmntTp = $dom->createElement($prfx . 'vatAmntTp', $vatAmntTp[$y]);
            $reportCashSaleVat_node->appendChild($child_node_vatAmntTp);

            $reportCashSalesVat_node->appendChild($reportCashSaleVat_node);
        }

        $eventReport_node->appendChild($reportCashSalesVat_node);

        /*--------------- reportEmpOpeningChangeFloats --------------*/
        // $reportEmpOpeningChangeFloats_node = $dom->createElement($prfx . 'reportEmpOpeningChangeFloats');

        // for ($y = 0; $y < 2; $y++) {
        //     $reportEmpOpeningChangeFloat_node = $dom->createElement($prfx . 'reportEmpOpeningChangeFloat');

        //     $child_node_empID = $dom->createElement($prfx . 'empID', $reportEmpOpeningChangeFloats_empID[$y]);
        //     $reportEmpOpeningChangeFloat_node->appendChild($child_node_empID);

        //     $child_node_openingChangeFloatAmnt = $dom->createElement($prfx . 'openingChangeFloatAmnt', $openingChangeFloatAmnt[$y]);
        //     $reportEmpOpeningChangeFloat_node->appendChild($child_node_openingChangeFloatAmnt);

        //     $reportEmpOpeningChangeFloats_node->appendChild($reportEmpOpeningChangeFloat_node);
        // }

        // $eventReport_node->appendChild($reportEmpOpeningChangeFloats_node);

        /*--------------------- reportCorrLines ---------------------*/
        $reportCorrLines_node = $dom->createElement($prfx . 'reportCorrLines');

        for ($y = 0; $y < 1; $y++) {
            $reportCorrLine_node = $dom->createElement($prfx . 'reportCorrLine');

            $child_node_corrLineType = $dom->createElement($prfx . 'corrLineType', $corrLineType[$y]);
            $reportCorrLine_node->appendChild($child_node_corrLineType);

            $child_node_corrLineNum = $dom->createElement($prfx . 'corrLineNum', $corrLineNum[$y]);
            $reportCorrLine_node->appendChild($child_node_corrLineNum);

            $child_node_corrLineAmnt = $dom->createElement($prfx . 'corrLineAmnt', $corrLineAmnt[$y]);
            $reportCorrLine_node->appendChild($child_node_corrLineAmnt);

            $reportCorrLines_node->appendChild($reportCorrLine_node);
        }

        $eventReport_node->appendChild($reportCorrLines_node);

        /*-------------------- reportPriceInquiries ---------------------*/
        $reportPriceInquiries_node = $dom->createElement($prfx . 'reportPriceInquiries');

        for ($y = 0; $y < 1; $y++) {
            $reportPriceInquiry_node = $dom->createElement($prfx . 'reportPriceInquiry');

            $child_node_priceInquiryGroup = $dom->createElement($prfx . 'priceInquiryGroup', $priceInquiryGroup[$y]);
            $reportPriceInquiry_node->appendChild($child_node_priceInquiryGroup);

            $child_node_priceInquiryNum = $dom->createElement($prfx . 'priceInquiryNum', $priceInquiryNum[$y]);
            $reportPriceInquiry_node->appendChild($child_node_priceInquiryNum);

            $child_node_priceInquiryAmnt = $dom->createElement($prfx . 'priceInquiryAmnt', $priceInquiryAmnt[$y]);
            $reportPriceInquiry_node->appendChild($child_node_priceInquiryAmnt);

            $reportPriceInquiries_node->appendChild($reportPriceInquiry_node);
        }

        $eventReport_node->appendChild($reportPriceInquiries_node);

        /*------------------ reportOtherCorrs --------------------*/
        $reportOtherCorrs_node = $dom->createElement($prfx . 'reportOtherCorrs');

        for ($y = 0; $y < 1; $y++) {
            $reportOtherCorr_node = $dom->createElement($prfx . 'reportOtherCorr');

            $child_node_otherCorrType = $dom->createElement($prfx . 'otherCorrType', $otherCorrType[$y]);
            $reportOtherCorr_node->appendChild($child_node_otherCorrType);

            $child_node_otherCorrNum = $dom->createElement($prfx . 'otherCorrNum', $otherCorrNum[$y]);
            $reportOtherCorr_node->appendChild($child_node_otherCorrNum);

            $child_node_otherCorrAmnt = $dom->createElement($prfx . 'otherCorrAmnt', $otherCorrAmnt[$y]);
            $reportOtherCorr_node->appendChild($child_node_otherCorrAmnt);

            $reportOtherCorrs_node->appendChild($reportOtherCorr_node);
        }

        $eventReport_node->appendChild($reportOtherCorrs_node);

        $event_node->appendChild($eventReport_node);
    }
    $cashregister_node->appendChild($event_node);
}
/*----------------- cashtransaction -----------------*/
for ($x = 1; $x <= $cashtransaction_count; $x++) {
    $cashtransaction_node = $dom->createElement($prfx . 'cashtransaction');

    $child_node_nr = $dom->createElement($prfx . 'nr', $cashtransaction_nr[$x]);
    $cashtransaction_node->appendChild($child_node_nr);

    $child_node_transID = $dom->createElement($prfx . 'transID', $cashtransaction_transID[$x]);
    $cashtransaction_node->appendChild($child_node_transID);

    $child_node_transType = $dom->createElement($prfx . 'transType', $transType[$x]);
    $cashtransaction_node->appendChild($child_node_transType);

    $child_node_transAmntIn = $dom->createElement($prfx . 'transAmntIn', $transAmntIn[$x]);
    $cashtransaction_node->appendChild($child_node_transAmntIn);

    $child_node_transAmntEx = $dom->createElement($prfx . 'transAmntEx', $transAmntEx[$x]);
    $cashtransaction_node->appendChild($child_node_transAmntEx);

    $child_node_amntTp = $dom->createElement($prfx . 'amntTp', $cashtransaction_amntTp[$x]);
    $cashtransaction_node->appendChild($child_node_amntTp);

    $child_node_empID = $dom->createElement($prfx . 'empID', $cashtransaction_empID[$x]);
    $cashtransaction_node->appendChild($child_node_empID);

    // $child_node_periodNumber = $dom->createElement($prfx . 'periodNumber', $cashtransaction_periodNumber[$x]);
    // $cashtransaction_node->appendChild($child_node_periodNumber);

    $child_node_transDate = $dom->createElement($prfx . 'transDate', $transDate[$x]);
    $cashtransaction_node->appendChild($child_node_transDate);

    $child_node_transTime = $dom->createElement($prfx . 'transTime', $transTime[$x]);
    $cashtransaction_node->appendChild($child_node_transTime);

    // ctLine
    for ($y = 1; $y <= $ctLine_count; $y++) {
        if ($ctLine_nr[$y] === $cashtransaction_nr[$x]) {
            $ctLine_node = $dom->createElement($prfx . 'ctLine');

            $child_node_nr = $dom->createElement($prfx . 'nr', $ctLine_nr[$y]);
            $ctLine_node->appendChild($child_node_nr);

            $child_node_lineID = $dom->createElement($prfx . 'lineID', $lineID[$y]);
            $ctLine_node->appendChild($child_node_lineID);

            $child_node_lineType = $dom->createElement($prfx . 'lineType', $lineType[$y]);
            $ctLine_node->appendChild($child_node_lineType);

            $child_node_artGroupID = $dom->createElement($prfx . 'artGroupID', $ctLine_artGroupID[$y]);
            $ctLine_node->appendChild($child_node_artGroupID);

            $child_node_artID = $dom->createElement($prfx . 'artID', $ctLine_artID[$y]);
            $ctLine_node->appendChild($child_node_artID);

            $child_node_qnt = $dom->createElement($prfx . 'qnt', $qnt[$y]);
            $ctLine_node->appendChild($child_node_qnt);

            $child_node_lineAmntIn = $dom->createElement($prfx . 'lineAmntIn', $lineAmntIn[$y]);
            $ctLine_node->appendChild($child_node_lineAmntIn);

            $child_node_lineAmntEx = $dom->createElement($prfx . 'lineAmntEx', $lineAmntEx[$y]);
            $ctLine_node->appendChild($child_node_lineAmntEx);

            $child_node_amntTp = $dom->createElement($prfx . 'amntTp', $ctLine_amntTp[$y]);
            $ctLine_node->appendChild($child_node_amntTp);

            // $child_node_empID = $dom->createElement($prfx . 'empID', $ctLine_empID[$y]);
            // $ctLine_node->appendChild($child_node_empID);

            // $child_node_lineDate = $dom->createElement($prfx . 'lineDate', $lineDate[$y]);
            // $ctLine_node->appendChild($child_node_lineDate);

            // $child_node_lineTime = $dom->createElement($prfx . 'lineTime', $lineTime[$y]);
            // $ctLine_node->appendChild($child_node_lineTime);

            // vat
            $vat_node = $dom->createElement($prfx . 'vat');

            // $child_node_vatCode = $dom->createElement($prfx . 'vatCode', $vat_vatCode[$y]);
            // $vat_node->appendChild($child_node_vatCode);

            $child_node_vatPerc = $dom->createElement($prfx . 'vatPerc', $vat_vatPerc[$y]);
            $vat_node->appendChild($child_node_vatPerc);

            $child_node_vatAmnt = $dom->createElement($prfx . 'vatAmnt', $vat_vatAmnt[$y]);
            $vat_node->appendChild($child_node_vatAmnt);

            $child_node_vatAmntTp = $dom->createElement($prfx . 'vatAmntTp', $vat_vatAmntTp[$y]);
            $vat_node->appendChild($child_node_vatAmntTp);

            $child_node_vatBasAmnt = $dom->createElement($prfx . 'vatBasAmnt', $vatBasAmnt[$y]);
            $vat_node->appendChild($child_node_vatBasAmnt);

            $ctLine_node->appendChild($vat_node);

            $cashtransaction_node->appendChild($ctLine_node);
        }
    }
    /*---------------- payment ----------------*/
    $payment_node = $dom->createElement($prfx . 'payment');

    $child_node_paymentType = $dom->createElement($prfx . 'paymentType', $payment_paymentType[$x]);
    $payment_node->appendChild($child_node_paymentType);

    $child_node_paidAmnt = $dom->createElement($prfx . 'paidAmnt', $paidAmnt[$x]);
    $payment_node->appendChild($child_node_paidAmnt);

    $child_node_empID = $dom->createElement($prfx . 'empID', $payment_empID[$x]);
    $payment_node->appendChild($child_node_empID);

    $child_node_curCode = $dom->createElement($prfx . 'curCode', $payment_curCode[$x]);
    $payment_node->appendChild($child_node_curCode);

    // $child_node_exchRt = $dom->createElement($prfx . 'exchRt', $exchRt[$x]);
    // $payment_node->appendChild($child_node_exchRt);
    // if ($paymentRefID[$x]) {
    //     $child_node_paymentRefID = $dom->createElement($prfx . 'paymentRefID', $paymentRefID[$x]);
    //     $payment_node->appendChild($child_node_paymentRefID);
    // }
    $cashtransaction_node->appendChild($payment_node);

    // cashtransaction part 2
    $child_node_signature = $dom->createElement($prfx . 'signature', $signature[$x]);
    $cashtransaction_node->appendChild($child_node_signature);

    $child_node_keyVersion = $dom->createElement($prfx . 'keyVersion', $keyVersion[$x]);
    $cashtransaction_node->appendChild($child_node_keyVersion);

    $child_node_certificateData = $dom->createElement($prfx . 'certificateData', $certificateData[$x]);
    $cashtransaction_node->appendChild($child_node_certificateData);

    // $child_node_registerID = $dom->createElement($prfx . 'registerID', $cashtransaction_registerID[$x]);
    // $cashtransaction_node->appendChild($child_node_registerID);

    // $child_node_voidTransaction = $dom->createElement($prfx . 'voidTransaction', $voidTransaction[$x]);
    // $cashtransaction_node->appendChild($child_node_voidTransaction);

    // $child_node_trainingID = $dom->createElement($prfx . 'trainingID', $trainingID[$x]);
    // $cashtransaction_node->appendChild($child_node_trainingID);

    $cashregister_node->appendChild($cashtransaction_node);
}


$location_node->appendChild($cashregister_node);

$company_node->appendChild($location_node);

$root->appendChild($company_node);

$dom->appendChild($root);

array_map('unlink', glob($xml_file_path . "*.xml"));
$dom->save($xml_file_name);

$_SESSION['fileName'] = "$AuditFileName";
$_SESSION['filePath'] = "$xml_file_name";
$_SESSION['fileMessage'] = "$AuditFileName er blevet oprettet.";
$_SESSION['startDatePeriod'] = "$startDatePeriod";
$_SESSION['endDatePeriod'] = "$endDatePeriod";

echo '<script>location.replace("saftCashRegister.php");</script>';
// redirect();

// exit(); // IMPORTANT 