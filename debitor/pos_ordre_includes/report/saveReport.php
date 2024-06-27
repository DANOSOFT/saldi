<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/report/saveReport.php ---------- lap 3.7.4----2019.05.08-------
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
// LN 20190306 Save report to the database

function saveLastReport($dataArray) {
	global $kasse;
    $date = date("Y/m/d");
    $count = 0;
    $reportNumber = getReportNumber(false);
    foreach($dataArray as $key => $arrayType) {
        if ($key == "groupArray") {
            $type = "Item group description";
            foreach($arrayType as $arrayKey => $arrayInfo) {
                $description = $arrayInfo["description"];
                $count = $arrayInfo["count"];
                $total = $arrayInfo["sellPrice"];
                writeToDatabase($date, $type, $description, $count, $reportNumber, $total);
            }
        } elseif ($key == "paymentArray") {
            $type = "Payment method";
            foreach($arrayType as $arrayKey => $arrayInfo) {
                (isset($arrayInfo['payment']))?$description = $arrayInfo['payment']: $description='';
                (isset($arrayInfo['price']))?$total = $arrayInfo['price'] : $total = 0;
                writeToDatabase($date, $type, $description, $count, $reportNumber, $total);
            }
        } elseif ($key == "vatArray") {
            $type = "Vat payment";
            foreach($arrayType as $arrayKey => $arrayInfo) {
                $description = isset($arrayInfo['vat']) ? $arrayInfo['vat'] : $arrayInfo['payment'];
                $total = $arrayInfo['price'];
                writeToDatabase($date, $type, $description, $count, $reportNumber, $total);
            }
        } elseif ($key == "drawArray") {
            $type = "Draw openings";
            foreach($arrayType as $arrayKey => $arrayInfo) {
                $description = $arrayInfo['kasse'];
                $total = $arrayInfo['openings'];
                $count = $total;
                writeToDatabase($date, $type, $description, $count, $reportNumber, $total);
            }
        } elseif (in_array($key, ['turnoverWithVat', 'turnoverWithoutVat'])) {
            $type = ($key == 'turnoverWithVat') ? "Turnover with vat" : "Turnover without vat";
            $description = "";
            $total = $arrayType;
            writeToDatabase($date, $type, $description, $count, $reportNumber, $total);
        } elseif (matchReceiptType($key)) {
            $type = getReceiptTypeText($key);
            $description = "";
            $count = isset($arrayType['count']) ? $arrayType['count'] : 0;
            $total = isset($arrayType['totalPrice']) ? $arrayType['totalPrice'] : 0;
            writeToDatabase($date, $type, $description, $count, $reportNumber, $total);
        }
    }
    unset($_SESSION['reportNumber']);
}

function writeToDatabase($date, $type, $descr, $count, $repNr, $total) {
	global $kasse;
	if (!$total) $total = 0;
	$qtxt = "INSERT INTO report (date, type, description, count, report_number, total) values ";
  $qtxt.= "('$date', '$type', '$descr', $count, '$repNr', $total)";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "update corrections set report_number = '$repNr' where report_number = '0' and kasse = '$kasse'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "update deleted_order set report_number = '$repNr' where report_number = '0' and kasse = '$kasse'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "update price_correction set report_number = '$repNr' where report_number = '0' and kasse = '$kasse'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "update proforma set report_number = '$repNr' where report_number = '0' and kasse = '$kasse'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "update returnings set report_number = '$repNr' where report_number = '0' and kasse = '$kasse'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

function matchReceiptType($key)
{
    return in_array($key, ["receiptArray", "copiedReceiptsArray", "proformaReceiptsArray", "returnedReceiptsArray",
     "discountArray", "cancelledOrderArray", "correctionArray", "priceCorrectionArray", "saleWithoutVatArray"]);
}

function getReceiptTypeText($key)
{
    if ($key == "receiptArray") {
        return "Receipt";
    } elseif ($key == "copiedReceiptsArray") {
        return "Copied receipt";
    } elseif ($key == "proformaReceiptsArray") {
        return "Proforma receipt";
    } elseif ($key == "returnedReceiptsArray") {
        return "Returned receipt";
    } elseif ($key == "discountArray") {
        return "Discount receipt";
    } elseif ($key == "cancelledOrderArray") {
        return "Cancelled receipt";
    } elseif ($key == "correctionArray") {
        return "Corrected receipt";
    } elseif ($key == "priceCorrectionArray") {
        return "Corrected price receipt";
    } elseif ($key == "saleWithoutVatArray") {
        return "Sale without vat receipt";
    }
}


?>
