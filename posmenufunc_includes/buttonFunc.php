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
// LN 20190218 Set text on the submenu buttons according to the given country or language
// LN 20190314 Disable the z-report button until the page has been reload

function setAccordinglyLanguage()
{
    $country = db_fetch_array(db_select("select land from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))['land'];
    
    if ($country == "Switzerland") {
        return ["draw" => "Drawer", "user" => "User", "price" => "Price", "discount" => "Discount", "boxCount" => "Cash count",
            "table" => "Table", "moveTable" => "Move table", "splitTable" => "Split table", "clear" => "Clear",
            "start" => "Reset", "findReceipt" => "Find receipt", "print" => "Print", "close" => "Close", 
            "sendToKitchen" => "Send to kitchen", "back" => "Back", "newCustomer" => "New customer", "copy" => "Copy",
            "correction" => "Correction"];
    } else {
        return ["draw" => "Skuffe", "user" => "Bruger", "price" => "Pris", "discount" => "Rabat", "boxCount" => "Kasseoptælling",
            "table" => "Bord", "moveTable" => "Flyt bord", "splitTable" => "Del bord", "clear" => "Ryd",
            "start" => "Forfra", "findReceipt" => "Find bon", "print" => "Udskriv", "close" => "Luk",
            "sendToKitchen" => "Send til køkken", "back" => "Tilbage", "newCustomer" => "Ny kunde", "copy" => "Kopier",
            "correction" => "Korrektion"];    
    }
}


function handleZreportClick($tmp, $b, $stil, $disabled)
{
    $tmp=str_replace("background-color: ;","background-color: $b;",$stil);
    if (isset($_SESSION['zreport']) && $_SESSION['zreport'] == "disabled") {
        $disabled .= "disabled";
        unset($_SESSION['zreport']);
    } 
    print "<td><INPUT $disabled $tmp id=\"zreport\" TYPE=\"submit\" NAME=\"zRapport\" VALUE=\"Z-Rapport\">";
}



?>

