<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- debitor/pos_ordre_includes/helperMethods/helperFunc.php ---- lap 3.9.5----2020.10.28-------
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20190312 LN Make various helper functions for the pos_ordre and the report files
// 20201028 PHR replaced 'bizsys' by 'pos' in $uniqueShopId 


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
		$uniqueShopId = str_replace('bizsys','pos',$uniqueShopId)
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
