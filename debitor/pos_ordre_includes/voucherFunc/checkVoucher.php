<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- debitor/pos_ordre_includes/voucherFunc/checkVoucher.php --- lap 3.9.9 --- 2021.01.25 ---
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

function voucherstatus($id,$konto_id) {
	if ($_POST['voucherstatus'] == "status") {
		$barcode = $_POST['giftcardNumber'];
		$qtxt="select id,item_id from voucher where barcode = '$barcode'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$gkId   = $r['id'];
		$itemId = $r['item_id'];
		if ($gkId) {
			$qtxt="select beskrivelse from varer where id = '$itemId'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$gkName = $r['beskrivelse'];
            
			$qtxt="select sum (amount+vat) as amount from voucheruse where voucher_id = '$gkId'";
			$gkUses = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$amount = $gkUses[0];
			$amount = number_format($amount, 2, ',', '.');
			$alertTxt="$gkName #" . $barcode . ": Saldo: $amount";
		} else {
			$alertTxt="Gavekort nummer: " . $barcode . ": ikke fundet";
		}
		alert($alertTxt);
	} else {
		print "<p>Indtast nummer:</p>\n";
		print "<form name=\"voucherstatus\" action=\"pos_ordre.php\" method=\"post\" autocomplete=\"off\">\n";
		print "Gavekort nummer: <input id='giftcardStatus' name=\"giftcardNumber\" type=\"text\" /><br />\n";
		print "<input value=\"status\" type=\"submit\" name=\"voucherstatus\"/>\n";
		print "</form>\n";
		print "<script>window.onload = function() { $('#giftcardStatus').focus(); } </script>";
	}
}