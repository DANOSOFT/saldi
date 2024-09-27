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
// LN 20190219 Make functions to print the different parts of the X-rapport


function proformaCount($x, $dkkpris, $kasse)
{
    for ($j = 0; $j <= $x; $j++) {
        $temp = str_replace('.', '', $dkkpris[$j]);
        $totalPrice += $temp;
    }
    $proformaQuery = db_fetch_array(db_select("select * from proforma where id='$kasse'",__FILE__."linje".__LINE__));
    
    if (isset($proformaQuery['id'])) {
        $totalPrice = $totalPrice + $proformaQuery['price'];
        $count = $proformaQuery['count'] + 1;
        db_modify("update proforma set price='$totalPrice' where id='$kasse'", __LINE__ . "linje" . __LINE__);
        db_modify("update proforma set count='$count' where id='$kasse'", __LINE__ . "linje" . __LINE__);
    } else {
        db_modify("insert into proforma (id, price, count) values ($kasse, $totalPrice, '1')",__FILE__."linje".__LINE__);
    }
}

function proformaReceipts()
{
    $proformas = db_select("select * from proforma", __FILE__ . " linje " . __LINE__);
    $proformaArray['count'] = 0;
    $proformaArray['price'] = 0;
    while($proforma = db_fetch_array($proformas)) {
        $proformaArray['count'] += $proforma['count'];
        $proformaArray['price'] += $proforma['price'];
    }
    return $proformaArray;

}















?>

