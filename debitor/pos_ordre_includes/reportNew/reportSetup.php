<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/report/reportSetup.php --- lap 4.0.0----2021.02.26 ---
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
// Copyright (c) 2019-2021 saldi.dk aps
// ----------------------------------------------------------------------
// LN 20190306 Make the most basic setup for the report
// 20210226 PHR Added $reportNumber

include("pos_ordre_includes/report/rapportData.php"); #20190219
include("pos_ordre_includes/report/rapportReceiptData.php"); #20190219
include("pos_ordre_includes/report/reportSubtract.php"); #20190219
include("pos_ordre_includes/report/saveReport.php"); #20190219
include("pos_ordre_includes/report/printReportFunc.php");
include("pos_ordre_includes/report/printFunctions.php");
include("pos_ordre_includes/report/printFuncII.php");

function setupReport($type, $kasse, $reportNumber) {
	$reportArray=array();
	if (in_array($type, ['zRapport', 'xRapport'])) { #Make array to the report
		$reportArray = retrieveReportData($kasse,$reportNumber);
		exit;
		if ($type == 'zRapport') {
			$_SESSION['zreport'] = "disabled";
  		saveLastReport($reportArray);
		}
	}
	return $reportArray;
}

function retrieveReportData($kasse,$reportNumber)
{
    $turnoverValues = calculateTurnover($kasse,$reportNumber);
    return ['groupArray' => productGroupDescription($kasse,$reportNumber), 'paymentArray' => paymentMethods($kasse,$reportNumber),
            'vatArray' => vatPayments($kasse,$reportNumber), 'receiptArray' => receiptCount($kasse),
            'drawArray' => drawCount($kasse), 'copiedReceiptsArray' => copiedReceipts($kasse),
            'turnoverWithoutVat' => $turnoverValues['turnoverWithoutVat'],
            'turnoverWithVat' => $turnoverValues['turnoverWithVat'],
            'proformaReceiptsArray' => proformaReceipts($kasse),
            'returnedReceiptsArray' => returnedReceipts($kasse),
            'discountArray' => ordersWithDiscount($kasse),
            'cancelledOrderArray' => cancelledOrders($kasse),
            'correctionArray' => correctionOrders($kasse),
            'priceCorrectionArray' => priceCorrectionOrders($kasse)];
}

function getReportType($key)
{
    switch($key) {
        case "groupArray":
            return "Item group description";
        case "paymentArray":
            return "Payment method";
        case "vatArray":
            return "Vat payment";
        case "turnoverWithoutVat":
            return "Turnover without vat";
        case "receiptArray":
            return "Receipt";
        case "drawArray":
            return "Draw opening";
        case "copiedReceiptsArray":
            return "Copied Receipt";
        case "proformaReceiptsArray":
            return "Proforma receipt";
        case "returnedReceiptsArray":
            return "Returned receipt";
        case "discountArray":
            return "Discount receipt";
        case "cancelledOrderArray":
            return "Cancelled receipt";
        case "correctionArray":
            return "Corrected receipt";
        case "priceCorrectionArray":
            return "Corrected price";
        case "saleWithoutVatArray":
            return "Sale without vat";
    }
}

function getReportId()
{
    $id = 0;
    $queryCheck = db_select("select id from report", __FILE__ . "linje" . __LINE__);
    $reportCheck = db_fetch_array($queryCheck);
    if (isset($reportCheck)) {
        $reports = db_select("select id from report", __FILE__ . "linje" . __LINE__);
        while ($report = db_fetch_array($reports)) {
            $id = ($report['id'] > $id) ? $report['id'] : $id;
        }
        return $id + 1;
    } else {
        return 0;
    }
}

function getReportNumber($header)
{
    if (isset($_SESSION['reportNumber'])) {
        return $_SESSION['reportNumber'];
    } elseif ($header == true) {
        $query = db_fetch_array(db_select("select MAX(report_number) from report where report_type = 'Z'", __FILE__ . "linje" . __LINE__));
        $reportNumber = $query['max'];
        return $reportNumber;
    } else {
        $reportNumber = 0;
        $queryCheck = db_select("select * from report where report_type = 'Z'", __FILE__ . "linje" . __LINE__);
        $reportCheck = db_fetch_array($queryCheck);
        if (isset($reportCheck)) {
            $reports = db_select("select * from report where report_type = 'Z'", __FILE__ . "linje" . __LINE__);
            while ($report = db_fetch_array($reports)) {
                $reportNumber = $report['report_number'];
            }
            $_SESSION['reportNumber'] = $reportNumber + 1;
            return $reportNumber + 1;
        } else {
            return 0;
        }
    }
 }

 function setReportType($xReport, $zReport)
 {
   if($xReport == true) {
     $reportVar = "xRapport";
   } elseif ($zReport == true) {
     $reportVar = "zRapport";
   }
   return $reportVar;
 }

?>
