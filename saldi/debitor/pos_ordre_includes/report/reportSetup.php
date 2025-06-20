<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/report/reportSetup.php ---------- lap 3.7.4----2019.05.08-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
//
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190306 Make the most basic setup for the report

include("pos_ordre_includes/report/rapportData.php"); #20190219
include("pos_ordre_includes/report/rapportReceiptData.php"); #20190219
include("pos_ordre_includes/report/reportSubtract.php"); #20190219
include("pos_ordre_includes/report/saveReport.php"); #20190219
include("pos_ordre_includes/report/printReportFunc.php");
include("pos_ordre_includes/report/printFunctions.php");
include("pos_ordre_includes/report/printFuncII.php");

function setupReport($type, $kasse)
{
    if (in_array($type, ['zRapport', 'xRapport'])) { #Make array to the report
		$reportArray = retrieveReportData($kasse);
		if ($type == 'zRapport') {
            $_SESSION['zreport'] = "disabled";
  			saveLastReport($reportArray);
		}
	}
	return $reportArray;
}


function retrieveReportData($kasse)
{
    $turnoverValues = calculateTurnover($kasse);
    return ['groupArray' => productGroupDescription($kasse), 'paymentArray' => paymentMethods($kasse),
            'vatArray' => vatPayments($kasse), 'receiptArray' => receiptCount($kasse),
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
        $query = db_fetch_array(db_select("select MAX(report_number) from report", __FILE__ . "linje" . __LINE__));
        $reportNumber = $query['max'];
        return $reportNumber;
    } else {
        $reportNumber = 0;
        $queryCheck = db_select("select * from report", __FILE__ . "linje" . __LINE__);
        $reportCheck = db_fetch_array($queryCheck);
        if (isset($reportCheck)) {
            $reports = db_select("select * from report", __FILE__ . "linje" . __LINE__);
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
