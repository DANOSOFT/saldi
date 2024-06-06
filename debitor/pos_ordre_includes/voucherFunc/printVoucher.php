<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- debitor/pos_ordre_includes/voucherFunc/createVoucher.php --- lap 3.9.9 --- 2021.01.25 ---
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
// Copyright (c) 2021 saldi.dk aps
// --------------------------------------------------------------------------
//

if (!function_exists('printVoucher')) {
function printVoucher($orderId,$bc) {
	global $bruger_id,$db_id,$id,$printserver;

	$barcode[0]=$bc;

	$qtxt = "select firmanavn,addr1,addr2,postnr,bynavn,cvrnr,tlf from adresser where art='S'";
	$r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$myName      = $r['firmanavn'];
	$myAddr1     = $r['addr1'];
	$myAddr2     = $r['addr2'];
	$myZip			=  $r['postnr'];
	$myCity			=  $r['bynavn'];
	$myVatNo		=  $r['cvrnr'];
	$myPhone		=  $r['tlf'];
	$v=0;
	$voucherId=array();
	if ($orderId) {
		$qtxt = "select voucher_id from voucheruse where order_id='$orderId' group by voucher_id order by voucher_id";
		$q    = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$voucherId[$v] = $r['voucher_id'];
			$v++;
		}
		for ($v=0;$v<count($voucherId);$v++) {
			$qtxt = "select item_id, barcode from voucher where id = '$voucherId[$v]'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$barcode[$v]   = $r['barcode'];
			$itemId[$v]    = $r['item_id'];
			$qtxt = "select sum (amount+vat) as amount from voucheruse where voucher_id = '$voucherId[$v]'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$amount[$v] = $r['amount'];
			$b=0;
/*
			$qtxt = "select ordrelinjer.beskrivelse from ordrelinjer where ";
			$qtxt.= "ordrelinjer.vare_id='$itemId[$v]' and ordrelinjer.ordre_id = '$orderId'"; 
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$itemName[$v][$b] = $r['beskrivelse'];
				$b++;
			}
*/			
				$qtxt = "select box5 from grupper where art = 'POS' and kodenr = '1'"; #fecth pay card names
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$payCards = explode(chr(9),$r['box5']);

				$qtxt = "select var_value from settings where var_name = 'voucherItems' and var_grp = 'Paycards'"; #fecth voucher item Id's 
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$vouchers = explode(chr(9),$r['var_value']);
				for ($x=0;$x<count($payCards);$x++) {
					if ($vouchers[$x] == $itemId[$v]) $itemName[$v] = $payCards[$x]; 
				}
		}
	} elseif ($barode[0]) {
		$qtxt   = "select * from voucher where barcode='". $barode[0] ."'";
		$r      = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$itemId[0]    = $r['item_id'];
		$voucherId[0] = $r['id'];
	}
	if (isset($itemId[0])) {
		if (file_exists("voucherPrint_$db_id.php")) include("pos_print/voucherPrint_$db_id.php");
		else include('pos_print/voucherPrint.php');
		return($bon);
	}	else return NULL;
	
}}

?>
