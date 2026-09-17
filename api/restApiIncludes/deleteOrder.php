 
<?php
//          ___   _   _   ___  _     ___  _ _
//         / __| / \ | | |   \| |   |   \| / /
//         \__ \/ _ \| |_| |) | | _ | |) |  <
//         |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/restApiIncludes/deleteOrder.php --- lap 4.0.5 --- 2022-03-23 ---
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
// Copyright (c) 2016-2022 saldi.dk aps
// ----------------------------------------------------------------------

function deleteOrder($id) {
	global $db;

	$log=fopen("../temp/$db/rest_api.log","a");
	fwrite($log,__line__." ". date("H:i:s") ." deleteOrder($id)\n");
	
	if (!$id) {
		return ("Missing orderID");
		exit;
	}
	
	$qtxt = "select status from ordrer where id = '$id'";
 	fwrite($log,__line__." $qtxt\n");
  if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		if ($r['status'] > 2) {
			return ("Order ID $id is invoiced and can't be deleted");
			exit;
		}
	} else {
		return ("Order ID $id not found");
		exit;
	}
	$qtxt = "select id from batch_salg where ordre_id = '$id'";
 	fwrite($log,__line__." $qtxt\n");
  $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
  if ($r['id']) {
		return ("Items from order ID $id has beed delivered, order can't be deleted");
		exit;
	}
	$qtxt = "delete from ordrelinjer where ordre_id = '$id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "delete from ordrer where id = '$id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	
	return (0);
}

?>
 
