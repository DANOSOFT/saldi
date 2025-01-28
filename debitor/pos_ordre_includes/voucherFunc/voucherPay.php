<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- debitor/pos_ordre_includes/voucherFunc/voucherPay.php --- lap 4.0.5 --- 2022.07.18 ---
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
// Copyright (c) 2021-2022 saldi.dk aps
// --------------------------------------------------------------------------
//
// 20210111 PHR changed elseif ($betaling == "Gavekort" || $betaling == "Gavekort på beløb") 
//              to elseif (isset($_POST['giftcardNumber']) && !$_POST['giftcardNumber'])
// 20220718 PHR Added $indbetaling as payment was not registered when paid to account using voucher

if (!function_exists('voucherPay')) {
function voucherPay($orderId, $betaling, $modtaget) {
	global $betvaluta,$betvalkurs;
	global $indbetaling, $regnaar;
	
	$payCardNo = 0;
	$vouchers[0] = 0;
	
	$qtxt = "select box5 from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '$regnaar'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$payCards = explode(chr(9),$r['box5']);
	for ($p=0;$p<count($payCards);$p++) {
		if (trim(str_replace('på beløb','',$betaling)) == $payCards[$p]) $payCardNo = $p;
	}
	$qtxt = "select var_value from settings where var_name = 'voucherItems' and var_grp = 'Paycards'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $vouchers = explode(chr(9),$r['var_value']);
	if ($vouchers[$payCardNo] && !isset($_POST['giftcardNumber'])) {
		#cho __line__." $modtaget -> >$_POST[modtaget]<<br>";
		if (!$modtaget && strpos($betaling,'på beløb')) {
			$qtxt = "select sum (amount*valutakurs/100) as paid from pos_betalinger where ordre_id='$orderId'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$modtaget = $_POST['sum'] - $r['paid'];
		}
		if (str_replace('q','',$_POST['modtaget'])) $modtaget = str_replace('q','',$_POST['modtaget']);
		print "<center>
			<div>
				<div>
					<h2>
						Skan stregkode på eller tast $_POST[betaling] nr:
					</h2>
				</div>
				<form name='pos_ordre' action='pos_ordre.php' method='post'>
					<input type=\"text\" id='getGiftcardNumber' name=\"giftcardNumber\" placeholder='fx 11'>
					<input type=\"submit\" value=\"Indtast\">
					<input name='fokus' value       = '$_POST[fokus]' style='display: none;'>
					<input name='pre_bordnr' value  = '$_POST[pre_bordnr]' style='display: none;'>
					<input name='momssats' value    = '$_POST[momssats]' style='display: none;'>
					<input name='leveret' value     = '$_POST[leveret]' style='display: none;'>
					<input name='varenr_ny' value   = '$_POST[varenr_ny]' style='display: none;'>
					<input name='betvaluta' value   = '$_POST[betvaluta]' style='display: none;'>
					<input name='sum' value         = '$_POST[sum]' style='display: none;'>
					<input name='betaling' value    = '$_POST[betaling]' style='display: none;'>
					<input name='indbetaling' value = '$_POST[indbetaling]' style='display: none;'>
<!--			<input name='modtaget' value    = '$modtaget' style='display: none;'> -->
					<input name='price' value       = '$modtaget' style='display: none;'>
				</form>
			</div>
		</center>";
		print "<script> 
			window.onload = function() { 
			$('#getGiftcardNumber').focus(); 
			}
		</script>";
		#cho "Post array: <br>";
		#hho '<pre>'; print_r($_POST); #cho '</pre>';
		#cho "Get array: <br>";
		#cho '<pre>'; print_r($_GET); #cho '</pre>';
		#cho "Id: $orderId <br>";
		exit(0);
	} elseif (isset($_POST['giftcardNumber']) && $_POST['giftcardNumber'] >= '0') {
		if (!isset($_COOKIE['giftcard']) || !$_COOKIE['giftcard']) {
			include ("../debitor/pos_ordre_includes/voucherFunc/useVoucher.php");
			$amount = useVoucher($orderId,$betaling)*1;
		}
		if ($amount && $_COOKIE['giftcard'] && !strpos($betaling,'på beløb')) {
			$qtxt="insert into pos_betalinger(ordre_id,betalingstype,amount,valuta,valutakurs) values ";
			$qtxt.="('$orderId','$betaling','$amount','$betvaluta','100')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			return true;
		}
	} elseif (isset($_POST['giftcardNumber']) && !$_POST['giftcardNumber']) {
		alert ("Gavekort nummer ikke angivet");
		print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
		exit;
	}	
	#cho __line__."<br>";
}}
?>
