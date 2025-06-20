<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/report/printReportFunc.php ---------- lap 3.7.5----2019.03.19-------
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
// LN 20190312 Make function that print all the parts of the report
// LN 20190319 LN use the correct parameter

function printReportFunctions($fp, $firmanavn, $cvrnr, $orgNr, $date, $uniqueShopId, $reportArray, $type, $kasse)
{
    printHeadline($fp, $firmanavn, $cvrnr, $orgNr, $date, $uniqueShopId, $type, $kasse);
    printDots($fp);
    printProductDescription($fp, $reportArray['groupArray']);
    printDots($fp);
    printTurnoverWithoutVat($fp, $reportArray['turnoverWithoutVat']);
    printDots($fp);
    printTurnover($fp, $reportArray['turnoverWithVat']);
    printDots($fp);
    printPaymentMethod($fp, $reportArray['paymentArray']);
    printDots($fp);
    printVat($fp, $reportArray['vatArray']);
    printDots($fp);
    printBoxOpenings($fp, $reportArray['drawArray']);
    printDots($fp);
    printNumberOfReceipts($fp, $reportArray['receiptArray']);
    printDots($fp);
    printCopiedReceipts($fp, $reportArray['copiedReceiptsArray']);
    printDots($fp);
    printProformaReceipts($fp, $reportArray['proformaReceiptsArray']);
    printDots($fp);
    printReturnReceipts($fp, $reportArray['returnedReceiptsArray']);
    printDots($fp);
    printDiscountOrders($fp, $reportArray['discountArray']);
    printDots($fp);
    printInterruptedOrders($fp, $reportArray['cancelledOrderArray']);
    printDots($fp);
    printCorrectionOrders($fp, $reportArray['correctionArray']);
    printDots($fp);
    printPriceCorrectionOrders($fp, $reportArray['priceCorrectionArray']);
}


?>
