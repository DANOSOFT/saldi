<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/updateShopCostPrice --- lap 4.0.8 --- 2023-06-07 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2023 saldi.dk aps
// --------------------------------------------------------------
// 20260727 Sawaneh Sends the cost price with shopApiRequest() instead of a backgrounded
//                 shell curl, so failures are logged and item data cannot reach the shell.

require_once(__DIR__ . '/../includes/stdFunc/shopApiRequest.php');

if (!function_exists('updateShopCostPrice')) {
function updateShopCostPrice($productId) {
	global $db;
	$costPrice = 0;
	$log=fopen("../temp/$db/rest_api.log","a");
	$qtxt="select box4 from grupper where art='API'";
	fwrite($log,__file__." ".__line__." $qtxt\n");
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$api_fil=trim($r['box4']); #20211013 $api_fil was omitted loe
	if (!$api_fil) {
		fwrite($log,__file__." ".__line__." no api\n");
		fclose($log);
		return('no api');
	}
	$qtxt = "select varenr,kostpris from varer where id = '$productId'";
	fwrite($log,__file__." ".__line__." $qtxt\n");
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$sku       = $r['varenr'];
		$costPrice = $r['kostpris'];
		fwrite($log,__file__." ".__line__." ProductNo = $sku Cost price = $costPrice\n");
#		$qtxt = "select shop_id from shop_varer where saldi_id = $productId";
#		fwrite($log,__file__." ".__line__." $qtxt\n");
#		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $shop_id=$r['shop_id'];
		$rand = rand();
		$params = array(
			'sku'       => $sku,
			'costPrice' => $costPrice,
			'file'      => __FILE__,
			'line'      => __LINE__,
			'rand'      => $rand,
		);
		$res = shopApiRequest($api_fil, $params, $log, array('context' => "updateShopCostPrice productId $productId"));
		if (!$res['ok']) {
			fclose($log);
			return ('sync error'); # detail stays in rest_api.log, it may name internal hosts
		}
	}
	fclose($log);
	return ('OK');
}} #endfunc sync_shop_vare()


?>
