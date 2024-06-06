<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/updateInvoiceField.php --- ver 4.0.6 --- 2022.11.11 ---
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
// Copyright (c) 2003-2022 saldi.dk ApS
// ----------------------------------------------------------------------


function updateInvoiceField($kladde_id) {
	
	$alignThis  = if_isset($_POST['alignThis']);
	$postId = if_isset($_POST['postId']);
	$invoiceNo  = if_isset($_POST['invoiceNo']);
	
	$newInvoiceNo = NULL;
	
	for ($x=0;$x<count($invoiceNo);$x++) {
		if (isset($alignThis[$x]) && $alignThis[$x] == 'on') $newInvoiceNo.= $invoiceNo[$x].";";  
	}
	$newInvoiceNo = trim($newInvoiceNo,';');
	
	if ($newInvoiceNo) {
		$qtxt = "update kassekladde set faktura = '$newInvoiceNo' where id = '$postId'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "update tmpkassekl set faktura = '$newInvoiceNo' where id = '$postId'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}
?>
