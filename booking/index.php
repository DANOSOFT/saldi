<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- booking/index.php --- lap 4.0.8 --- 2023-03-23 ---
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
// Copyright (c) 2023 saldi.dk aps
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

print "<html>";
print "	<head><title>Saldi booking</title><meta http-equiv='content-type' content='text/html; charset=UTF-8;'>
	<meta http-equiv='content-language' content='da'>
</head><body>";

$id=(int)$_GET['id'];
if (!$id) {
	echo "missing ID";
	exit;
}
include ('../includes/std_func.php');
include ('../includes/connect.php');
$qtxt = "select db from regnskab where id = '$id'";
if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$db = $r['db'];
	$qtxt = "select brugernavn from online where session_id = '$s_id' and db = '$db'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$username = $r['brugernavn']; #Existing user allready logged in;
	} else { #Access from 'outside'
		$username = 'booking';
		$qtxt = "insert into online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,sprog)";
		$qtxt.= " values ";
		$qtxt.= "('$s_id','booking','". db_escape_string($db) ."','". db_escape_string($squser) ."',";
		$qtxt.= "'0',0,'". date('U') ."',FALSE,'1')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	include ('../includes/online.php');
	if ($username == 'booking') {
		$qtxt = "select rettigheder from brugere where brugernavn = 'booking'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			if (strlen(strpos($r['rettigheder'],'1'))) {
				echo "user not valid";
				exit;
			}
		} else {
			echo "user not found";
			exit;
		} 
	}
}
$x=0;
$qtxt = "select distinct item_id from rentalitems";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$itemId[$x] = $r['item_id'];
	$x++;
}

for ($x = 0; $x < count($itemId); $x++) {
	$qtxt = "select * from varer where id = $itemId[$x]";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$itemSku[$x]     = $r['varenr'];
		$itemName[$x]    = $r['beskrivelse'];
		$itemPrice[$x]   = $r['salgspris'];
		$itemUnit[$x]    = $r['enhed'];
 }
}
if ($username == 'booking') {
	include ('../includes/connect.php');
	$qtxt = "delete from online where session_id = '$s_id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

print "<table border = '1'><tbody>";
print "<tr>";
print "<td>Item ID</td>";
print "<td>Item SKU</td>";
print "<td>Item Name</td>";
print "<td>Item Price</td>";
print "<td>Item Unit</td>";
print "</tr>";
for ($x = 0; $x < count($itemId); $x++) {
	print "<tr>";
	print "<td>$itemId[$x]</td>";
	print "<td>$itemSku[$x]</td>";
	print "<td>$itemName[$x]</td>";
	print "<td>$itemPrice[$x]</td>";
	print "<td>$itemUnit[$x]</td>";
	print "</tr>";
}
print "</tbody></table>";
?>
