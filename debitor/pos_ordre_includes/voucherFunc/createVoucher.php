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

if (!function_exists('createVoucher')) {
function createVoucher($orderLineId) {
	
	if (!$printserver) {
		$qtxt = "select box3,box4,box5,box6 from grupper where art = 'POS' and kodenr='2'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
		$x=$kasse-1;
		$tmp=explode(chr(9),$r['box3']);
		$printserver=trim($tmp[$x]);
		if (!$printserver)$printserver='localhost';
		if ($printserver=='box' || $printserver=='saldibox') {
			$filnavn="http://saldi.dk/kasse/".$_SERVER['REMOTE_ADDR'].".ip";
			if ($fp=fopen($filnavn,'r')) {
				$printserver=trim(fgets($fp));
				fclose ($fp);
			}
		}
	}
	
#    $lastOrderId=db_fetch_array(db_select("select id from ordrer order by id desc",__FILE__ . " linje " . __LINE__))[0];
#    $lastOrderId += 1;

	$qtxt = "select vare_id,ordre_id,antal,pris,momsfri,momssats,beskrivelse from ordrelinjer where id='$orderLineId'";
	$r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$itemId      = $r['vare_id'];
	$orderId     = $r['ordre_id'];
	$amount      = $r['pris'];
	$description = $r['beskrivelse'];
	$qty         = $r['antal'];
	($r['momsfri'])?$vat=0:$vat=afrund($amount*$r['momssats']/100,2);
	for ($addBc=1;$addBc<=$qty;$addBc++) {
		$barcode = rand();
		$qtxt="insert into voucher (item_id,barcode) values ('$itemId','$barcode' )";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="select id from voucher where barcode = '$barcode'";
		$r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$voucherId = $r['id'];
		$barcode = $voucherId . date('ymd');
		$qtxt="update voucher set barcode = '$barcode' where id = '$voucherId'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="insert into voucheruse ( voucher_id, order_id, amount, vat ) values ( '$voucherId', '$orderId', '$amount', '$vat' )";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#		if (file_exists("voucherPrint_$db_id.php")) include("pos_print/voucherPrint_$db_id.php");
#		else include('pos_print/voucherPrint.php');
#		ob_flush();
#		sleep(1);
	}
}}
?>
