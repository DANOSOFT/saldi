<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre.php ---------- lap 3.7.4----2019.01.07-------
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
// LN 20190219 Make function to the pos_ordre file, helper methods to print warning message and make to point decimal numbers

function printWarningMessage($textType) { #LN Print warning message
    if($textType == "proforma") {
        $txt = "Der er ingen varer på regningen.";
    } elseif ($textType == "copied") {
        $txt = "Der er allerede udskrevet en kopi af denne kvittering";
    }
    print"<html>
                <head>
                <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
                <style>
                .alert {
                padding: 20px;
                background-color: #808080;
                color: white;
                }

                .closebtn {
                margin-left: 15px;
                color: white;
                font-weight: bold;
                float: right;
                font-size: 22px;
                line-height: 20px;
                cursor: pointer;
                transition: 0.3s;
                }

                .closebtn:hover {
                color: black;
                }
                </style>
                </head>
                <body>

                <div class=\"alert\">
                <span class=\"closebtn\" onclick=\"this.parentElement.style.display='none';\">&times;</span> 
                <strong>OBS!</strong> $txt 
                </div>

                </body>
            </html>";
}

function truncate_number( $number, $precision = 2) { #LN 20190212 Added function to round op numbers
    if ( 0 == (int)$number ) {
        return $number;
    }
    $negative = $number / abs($number);
    $number = abs($number);
    $precision = pow(10, $precision);
    return floor( $number * $precision ) / $precision * $negative;
}

function getCountry()
{   
    $adress = db_select("select land from adresser where art = 'S'",__FILE__ . " linje " . __LINE__);
    $countryArr = db_fetch_array($adress);
    return $countryArr['land'];
}

function onAmount()
{
    $country = getCountry();
    if ($country == "Switzerland") {
        return "on amount";
    } else {
        return "på beløb";
    }
}

function kitchenTxt()
{
    $country = getCountry();
    if ($country == "Switzerland") {
        return "Send to kitchen";
    } else {
        return "Send til køkken";
    }
}


?>

