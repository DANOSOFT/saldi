<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//--- includes/stdFunc/findAccountVat.php --- ver 4.0.8 --- 20230707 ---
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
// ----------------------------------------------------------------------
// This function finds the Vat account belonging to an account in ledger.
//
function findAccountVat($accountNo) {
	global $regnaar;
	$vatAccount = NULL;
	$qtxt="select moms from kontoplan where kontonr = '$accountNo' and regnskabsaar = '$regnaar'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['moms']) {
		$vatType = substr($r['moms'],0,1).'M';
		$vatNo   = substr($r['moms'],1);
		$qtxt="select box1,box2 from grupper where art = '$vatType' and kodenr = '$vatNo'";
		if($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $vatAccount=$r['box1'];
	}
	return $vatAccount;
}
?>
