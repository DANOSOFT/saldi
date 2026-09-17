<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/vareIncludes/updateProductPrice.php --- lap 4.0.8 --- 2023-08-22 ---
// LICENS
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2023 saldi.dk aps
// ----------------------------------------------------------------------

if (!function_exists('updateProductPrice')) {
function updateProductPrice($productId,$newCost,$deliveryDate) {
	$qtxt=NULL;
	if (!$deliveryDate) $deliveryDate=date("Y-m-d");
	$qtxt = "select id,kostpris,transdate from kostpriser where id='$productId' order by transdate desc limit 1";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['transdate'] != $deliveryDate && $r['kostpris'] != $newCost) {
		$qtxt="insert into kostpriser (vare_id,kostpris,transdate) values ('$productId','$newCost','$deliveryDate')";
	} elseif ($r['transdate'] == $deliveryDate && $r['kostpris'] != $newCost) {
		$qtxt="update kostpriser set kostpris='$newCost' where id = '$r[id]'";
	}
	if ($qtxt) db_modify("$qtxt",__FILE__ . " linje " . __LINE__);
	db_modify("update varer set kostpris='$newCost' where id='$productId'",__FILE__ . " linje " . __LINE__);
	$qtxt="select * from varer where id='$productId'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$salesPrice_multiplier   = $r['salgspris_multiplier'];
	$salesPrice_method       = $r['salgspris_method'];
	$salesPrice_rounding     = $r['salgspris_rounding'];
	$tier_price_multiplier   = $r['tier_price_multiplier'];
	$tier_price_method       = $r['tier_price_method'];
	$tier_price_rounding     = $r['tier_price_rounding'];
	$retail_price_multiplier = $r['retail_price_multiplier'];
	$retail_price_method     = $r['retail_price_method'];
	$retail_price_rounding   = $r['retail_price_rounding'];

	if ($salesPrice_multiplier || $tier_price_multiplier || $retail_price_multiplier) {
		include_once("../lager/productsIncludes/addToPriceFunc.php");
		if ($newCost && $salesPrice_multiplier>0 && $salesPrice_method && $salesPrice_rounding){
			$salesPrice = addToPriceFunc($newCost, $salesPrice_rounding, $salesPrice_multiplier, $salesPrice_method);
			if ($salesPrice) {
				$qtxt = "update varer set salgspris='$salesPrice' where id='$productId'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		if ($newCost && $tier_price_multiplier>0 && $tier_price_method && $tier_price_rounding){
			$tier_price = addToPriceFunc($newCost, $tier_price_rounding, $tier_price_multiplier, $tier_price_method);
			if ($tier_price) {
				$qtxt = "update varer set tier_price='$tier_price' where id='$productId'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		if ($newCost && $retail_price_multiplier>0 && $retail_price_method && $retail_price_rounding){
			$retail_price = addToPriceFunc($newCost, $retail_price_rounding, $retail_price_multiplier, $retail_price_method);
			if ($retail_price) {
				$qtxt = "update varer set retail_price='$retail_price' where id='$productId'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		}
}}

?>
