<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_print/xRapportInc/printFuncII.php ---------- lap 3.7.4----2019.01.07-------
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
// 20190312 LN Make receipt functions to print the different parts of the report

function printDiscountOrders($fp, $ordersWithDiscount)
{
    printHeading($fp, "Givet rabatter", "Pris");
    printOneLiner($fp, $ordersWithDiscount);
}

function printReturnReceipts($fp, $returnedReceiptsArray)
{
    printHeading($fp, "Retur kvitteringer", "Pris");
    printOneLiner($fp, $returnedReceiptsArray);
}

function printProformaReceipts($fp, $proformaReceiptsArray)
{
    printHeading($fp, "Proforma kvitteringer", "Pris");
    printOneLiner($fp, $proformaReceiptsArray);
}

function printNumberOfReceipts($fp, $receiptArray)
{
    printHeading($fp, "Antal salgskvitteringer", "Pris");
    printOneLiner($fp, $receiptArray);
}

function printCopiedReceipts($fp, $copiedReceiptsArray)
{
    printHeading($fp, "Kopierede bonner", "Pris");
    printOneLiner($fp, $copiedReceiptsArray);
}

function printInterruptedOrders($fp, $deletedOrders)
{
    printHeading($fp, "Afbrudt salg", "Samlet beløb");
    printOneLiner($fp, $deletedOrders);
}

function printCorrectionOrders($fp, $correctedOrders)
{
    printHeading($fp, "Rettet ordrer", "Samlet beløb");
    printOneLiner($fp, $correctedOrders);
}

function printPriceCorrectionOrders($fp, $priceCorrectedOrders)
{
    printHeading($fp, "Rettet priser", "Samlet beløb");
    printOneLiner($fp, $priceCorrectedOrders);
}

function printSaleWithoutVat($fp, $saleWithoutVatArray)
{
    printHeading($fp, "Salg uden moms", "Samlet beløb");
    printOneLiner($fp, $saleWithoutVatArray);
}

function printHeading($fp, $title, $subTitle)
{
    $subTitle = iconv("UTF-8", "cp865",$subTitle);
    $whiteSpace = 44;
    $text = str_pad($title, 5, ' ', STR_PAD_BOTH);
    $secondStringLength = $whiteSpace - strlen($text);
    $text .= str_pad($subTitle, $secondStringLength, ' ', STR_PAD_LEFT);
    fwrite($fp, "$text \n");
}

function printOneLiner($fp, $dataArray)
{
    $whiteSpace = 44;
    $temp = 0;
    foreach($dataArray as $value) {
        if ($temp == 0) {
            $value = number_format($value, 0, ',', '.');
            $text = str_pad($value, 8, ' ', STR_PAD_BOTH);
            $secondStringLength = $whiteSpace - strlen($text);
            $temp++;
        } else {
            $value = number_format($value, 2, ',', '.');
            $text .= str_pad($value, $secondStringLength, ' ', STR_PAD_LEFT);
            fwrite($fp, $text);
            fwrite($fp, "\n");
            $temp = 0;
        }
    }
}


?>
