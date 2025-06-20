<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/helperMethods/helperFunc.php ---------- lap 3.7.4----2019.05.07-------
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
// LN 20190312 Make various helper functions for the pos_ordre and the report files



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

function onCash()
{
    $country = getCountry();
    if ($country == "Switzerland") {
        return "Cash";
    } else {
        return "Kontant";
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

function countDrawOpening($kasse)
{
	$drawer = db_fetch_array(db_select("select * from drawer where id ='$kasse'", __FILE__ . "linje " . __LINE__));
	if (isset($drawer['id'])) {
        $newOpenings = $drawer['openings'] + 1;
        db_modify("update drawer set openings = '$newOpenings' where id='$kasse'",__FILE__ . " linje " . __LINE__);
	} else {
        db_modify("INSERT INTO drawer (id, openings) values ('$kasse', '1')", __FILE__."linje".__LINE__);
	}
}

function countPriceCorrectionSetup($newPrice, $oldPrice)
{
    if(isset($newPrice) && isset($oldPrice) && $newPrice != $oldPrice)  {
        $_SESSION['price_correction'] = true;
    }
}

function countPriceCorrection($id, $price, $kasse)
{
    $temp = $_SESSION['price_correction'];
    if ($temp == true) {
        db_modify("insert into price_correction (id, price, kasse) values ($id, $price, $kasse)", __LINE__ . "linje" . __LINE__);
        unset($_SESSION['price_correction']);
    }
}

function countReturns($id, $kasse)
{
      $correction = (isset($_SESSION['creditType']) && ($_SESSION['creditType'] == "krediter" || $_SESSION['creditType'] == "retur")) ? true : false;
      $payment = (isset($_POST['betaling']) && strpos($_POST['betaling'], "på beløb")) ? true : false;
      $negative = (isset($_POST['sum']) && $_POST['sum'] < 0) ? true : false;
      if ($payment && ($correction || $negative)) {
          $id = getReceiptId("returnings");
          $price = isset($_POST['sum']) ? abs($_POST['sum']) : 0;
          db_modify("insert into returnings (id, price, kasse) values ('$id', '$price', '$kasse')", $queryVar);
      }
}

function countCorrection($id, $kasse)
{
    $credit = (isset($_SESSION['creditType']) && $_SESSION['creditType'] == "krediter") ? true : false;
    $payment = (isset($_POST['betaling']) && strpos($_POST['betaling'], "på beløb")) ? true : false;
    if ($payment && $credit) {
        $sum = isset($_POST['sum']) ? abs($_POST['sum']) : 0;
        $id = getReceiptId("corrections");
        db_modify("insert into corrections (id, price, kasse) values ('$id', '$sum', '$kasse')", __LINE__ . "linje" . __LINE__);
    }
}

function getReceiptId($table)
{
    $maxId = db_fetch_array(db_select("select MAX(id) from $table", __LINE__ . "linje" . __LINE__))['max'];
    return $maxId + 1;
}


function makeOrderIdArray($kasse)
{
    $orderQuery = db_select("select ordrenr from ordrer where felt_5 = '$kasse'", __FILE__ . "linje" . __LINE__);
    while ($order = db_fetch_array($orderQuery)) {
        $ordreIdArr[] = $order['ordrenr'];
    }
    ksort($ordreIdArr);
    return $ordreIdArr;
}

function getUniqueBoxId($kasse)
{
    global $db;
    $db = (int) filter_var($db, FILTER_SANITIZE_NUMBER_INT);
    $uri = $_SERVER['REQUEST_URI'];
    if (strpos($uri, "bizbeta")) {
        $uniqueShopId = "BI-";
    } elseif (strpos($uri, "grillbar")) {
        $uniqueShopId = "GR-";
    } else {
        $uniqueShopId = "BI-";
    }
    $uniqueShopId .= "$db-";
    if ($kasse < 10) {
        $uniqueShopId .= sprintf("%02d", $kasse);
    } else {
        $uniqueShopId .= $kasse;
    }
    return $uniqueShopId;
}

function fejl ($id,$fejltekst) {
  alert($fejltekst);
  print "<meta http-equiv=\"refresh\" content=\"0;URL=$php_self\">\n";

}



?>
