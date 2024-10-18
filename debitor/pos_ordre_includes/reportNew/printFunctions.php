<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_print/xRapportInc/printFunctions.php ---------- lap 3.7.5----2019.03.19-------
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
// 20190319 LN Print the new unique box id correct

function fiveSpace() {
    return $fiveSpace = str_repeat(' ', 5);
}

function printDots($fp) {
    fwrite($fp,"-----------------------------------------------\n");
}

function printHeadline($fp, $firmanavn, $cvrnr, $orgNr, $date, $uniqueShopId, $type, $kasse)
{
    if ($type == "xRapport") {
        $headline = str_pad("X-Rapport", 45, ' ', STR_PAD_BOTH);
    } elseif ($type == "zRapport") {
        $headline = str_pad("Z-Rapport", 45, ' ', STR_PAD_BOTH);
        $reportNumber = getReportNumber(true);
        $reportNumber = str_pad("Nr. $reportNumber", 45, ' ', STR_PAD_BOTH);
    }
    fwrite($fp, "$headline \n");
    fwrite($fp, "$reportNumber \n");

    $name = str_pad($firmanavn, 45, ' ', STR_PAD_BOTH);
    fwrite($fp, "$name \n");

    $orgNr = str_pad("ORG. Nr: $cvrnr", 45, ' ', STR_PAD_BOTH);
    fwrite($fp, "$orgNr \n");

    $date = date("Y/m/d");
    $date = $date . ' ' . date("H:i:s");
    $date = str_pad($date, 45, ' ', STR_PAD_BOTH);
    fwrite($fp,"$date \n");

    $shopId = str_pad("System ID: $uniqueShopId", 45, ' ', STR_PAD_BOTH);
    fwrite($fp,"$shopId \n");

    $boxNr = str_pad("Kasse: $kasse", 45, ' ', STR_PAD_BOTH);
    fwrite($fp,"$boxNr \n");
}

function printProductDescription($fp, $groupArray)
{
    $fiveSpace = fiveSpace();
    fwrite($fp, "Varegruppe beskrivelse $fiveSpace Antal $fiveSpace Totalt \n");
    $temp = 1;
    foreach ($groupArray as $key) {
        $text = '';
        foreach($key as $value) {
            if ($temp == 1) {
                $text .= str_pad($value, 22, ' ', STR_PAD_RIGHT);
                $text = iconv("UTF-8", "cp865", $text);
                $temp++;
            } elseif ($temp == 2) {
                $text .= str_pad($value, 11, ' ', STR_PAD_LEFT);
                $temp++;
            } else {
                $value = number_format($value, 2, ',', '.');
                $text .= str_pad($value, 13, ' ', STR_PAD_LEFT);
                $text = utf8_encode($text);
                fwrite($fp, $text);
                fwrite($fp, "\n");
                $temp = 1;
            }
        }
    }
}

function printTurnover($fp, $omsatning)
{
    $omsatning = number_format($omsatning, 2, ',', '.');
    printHeading($fp, "Dagens omsetning med MVA", $omsatning);
}

function printTurnoverWithoutVat($fp, $omsatningWithoutVat)
{
    $omsatningWithoutVat = number_format($omsatningWithoutVat, 2, ',', '.');
    printHeading($fp, "Dagens omsetning utan MVA", $omsatningWithoutVat);
}

function printPaymentMethod($fp, $paymentArray)
{
    printHeading($fp, "Betalingsform", "Salg");
    printTwoLiner($fp, $paymentArray);
}

function printVat($fp, $vatArray)
{
    printHeading($fp, "MVA", "Salg");
    printTwoLiner($fp, $vatArray);
}

function printBoxOpenings($fp, $drawArray)
{
    printHeading($fp, "Kasse", "åbninger");
    printTwoLiner($fp, $drawArray, false);
}


function printTwoLiner($fp, $dataArray, $decimals = true)
{
    $whiteSpace = 44;

    foreach ($dataArray as $key) {
        $temp = 0;
        foreach($key as $value) {
            if ($temp == 0) {
                $value = checkIfInt($value);
                $text = str_pad($value, 8, ' ', STR_PAD_BOTH);
                $secondStringLength = $whiteSpace - strlen($text);
                $temp++;
            } else {
                if ($decimals == true) {
                    $value = number_format($value, 2, ',', '.');
                }
                $text .= str_pad($value, $secondStringLength, ' ', STR_PAD_LEFT);
                fwrite($fp, $text);
                fwrite($fp, "\n");
                $temp = 0;
            }
        }
    }
}

function checkIfInt($value)
{
    if ((strpos($value, '.') == true || strpos($value, ',')) && is_numeric($value)) {
        $decimals = strcspn(strrev($value), '.');
        if ($decimals > 0) {
            return number_format($value, 2, ',', '.');
        }
    } else {
        return $value;
    }
}


?>
