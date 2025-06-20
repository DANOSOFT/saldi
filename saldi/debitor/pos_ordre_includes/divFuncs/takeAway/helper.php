<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/divFuncs/takeAway/setup.php ---------- lap 3.7.7----2019.07.09-------
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
// LN 20190709 Make helper functions for the takeAway


function formatDate($date)
{
    if (isset($date)) {
        $arr = str_split($date);
        $temp = $arr[6] . $arr[7] . $arr[8] . $arr[9] . "-" . $arr[3] . $arr[4] . "-" . $arr[0] . $arr[1];
        return $temp;
    } else {
        $arr = str_split($_POST['takeAwayDate']);
        $_POST['takeAwayDate'] = $arr[8] . $arr[9] . "/" . $arr[5] . $arr[6] . "/" . $arr[0] . $arr[1] . $arr[2] . $arr[3];
    }
}

function getOrderFromDatabase($ordrenr, $withPrice = false)
{
    $queryVar = __FILE__ . " linje " . __LINE__;
    $selectFields = "id, status, bynavn, lev_navn, addr1, lev_addr1, ordredate, ordrenr, sum, valuta,";
    $selectFields .= "modtagelse, lev_adr, tidspkt, betalt, datotid, kontakt_tlf, postnr,";
    $selectFields .= "fakturanr, levdate, felt_5, moms, felt_1";
    $queryTxt = "select " . $selectFields . " from ordrer where ordrenr='$ordrenr'";
    $query = db_fetch_array(db_select($queryTxt  , $queryVar));

    $temp = $query['modtagelse'];
    $query['modtagelse'] = ($temp == '0') ? "Walk-in" : (($temp == '1') ? "Web" : "Telefon");

    $_GET['id'] = $query['id'];
    $tempId = $query['id'];
    $query['cash'] = db_fetch_array(db_select("select amount from pos_betalinger where ordre_id='$tempId'", $queryVar))['amount'];
    $cash = $query['cash'];
    $sum = $query['sum'] + $query['vat'];
    $query['ordrenr'] = $ordrenr;
    $query['returnings'] = $query['cash'] - ($query['sum'] + $query['moms']);
    $query['products'] = getReceiptProducts($query['id'], $withPrice);

    return $query;
}

function getprintVariables()
{
    $r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
    $printer_ip=explode(chr(9),$r['box3']);
    $printserver=$printer_ip[0];
    return $printserver;
}

function adjustLength($number, $description, $price)
{
    $length = strlen($number) + mb_strlen(utf8_decode($description)) + strlen($price);
    $txt = $number . " " . $description;
    for ($i = 0; $i < (45 - $length); $i++) {
        $txt .= " ";
    }
    $txt .= $price;
    return $txt;
}

function getReceiptProducts($ordreId, $withPrice = false)
{
    $queryVar = __FILE__ . " linje " . __LINE__;
    $queryTxt="select * from ordrelinjer where ordre_id = '$ordreId' and varenr!='R' and ordre_id > 0 and posnr >= 0 ";
    $queryTxt = db_select($queryTxt, $queryVar);
    while($query = db_fetch_array($queryTxt)) {
        $number = number_format($query['antal'], '0', '', '.');
        $price = number_format($query['vat_price'], '2', ',', '.') * $number;
        if ($withPrice == true) {
            $returnArray[] = adjustLength($number, $query['beskrivelse'], $price);
        } else {
            $returnArray[] = $number . " " . $query['beskrivelse'];
        }
        if ($query['tilfravalg']) {
            $tfvare = explode(chr(9),$query['tilfravalg']);
            foreach ($tfvare as $key => $value) {
                $extraQuery = db_fetch_array(db_select("select beskrivelse from varer where id = '$value'",__FILE__ . " linje " . __LINE__));
                $returnArray[] = $extraQuery['beskrivelse'];
            }
        }
    }
    return $returnArray;
}


function getShopInfo()
{
    $selectFields = "firmanavn, addr1, bynavn, postnr, tlf, cvrnr";
    $queryTxt = "select " . $selectFields . " from adresser where art='S'";
    $basicDataQuery = db_fetch_array(db_select($queryTxt, __FILE__ . " linje " . __LINE__));
    $basicDataQuery['bynavn'] = $basicDataQuery['postnr'] . " " . $basicDataQuery['bynavn'];
    return $basicDataQuery;
}

function resetOrder($id)
{ // This function reset the pos, when a takeAway order has been saved
    if (isset($_SESSION['saveOrder']) && $_SESSION['saveOrder'] == "Gem bestilling") {
        db_modify("update ordrer set status=-1 where id='$id'",__FILE__ . " linje " . __LINE__);
        unset($_SESSION['saveOrder']);
        $kasse = stripslashes($_COOKIE['saldi_pos']);
        opret_posordre(0, $kasse);
        return 0;
    } else {
        return $id;
    }
}

function calculateSaveOrderSetup($products)
{
    $ordreId = $_GET['id'];
    // $sizeOfProductsArray = ((sizeof($products) * 25) > 150) ? 150 : (sizeof($products) * 25);
    $sizeOfProductsArray = (sizeof($products) * 25);
    $selectFields = "bynavn, lev_navn, addr1, ordrenr, lev_addr1,";
    $selectFields .= "modtagelse, lev_adr, tidspkt, betalt, datotid, kontakt_tlf, postnr";
    $queryTxt = "select " . $selectFields . " from ordrer where id='$ordreId'";
    $query = db_fetch_array(db_select($queryTxt  ,__FILE__ . " linje " . __LINE__));
    return ['size' => $sizeOfProductsArray, 'info' => $query, 'ordre_id' => $ordreId];
}


?>
