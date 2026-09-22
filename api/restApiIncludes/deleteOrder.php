
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

// 20260920 CDX/LH Remove order mappings atomically and preserve all rows on deletion failure.
function deleteOrder($id) {
	global $db_modify_fejl;
	if (!$id) {
		return 'Missing orderID';
	}
	if (!preg_match('/^[0-9]+$/D', (string)$id) || filter_var(ltrim((string)$id, '0'), FILTER_VALIDATE_INT) === false) {
		return 'Invalid orderID';
	}
	$id = (int)$id;
	if (!transaktion('begin')) {
		return 'Failed to start order deletion';
	}
	$finished = false;
	try {
		$r = db_fetch_array(db_select("SELECT status FROM ordrer WHERE id='$id' FOR UPDATE", __FILE__ . ' linje ' . __LINE__));
		if (!$r) {
			return "Order ID $id not found";
		}
		if ($r['status'] > 2) {
			return "Order ID $id is invoiced and can't be deleted";
		}
		$r = db_fetch_array(db_select("SELECT id FROM batch_salg WHERE ordre_id='$id'", __FILE__ . ' linje ' . __LINE__));
		if ($r) {
			return "Items from order ID $id has beed delivered, order can't be deleted";
		}
		foreach (array("DELETE FROM ordrelinjer WHERE ordre_id='$id'", "DELETE FROM shop_ordrer WHERE saldi_id='$id'", "DELETE FROM ordrer WHERE id='$id'") as $sql) {
			db_modify($sql, __FILE__ . ' linje ' . __LINE__);
			if ($db_modify_fejl) {
				return "Failed to delete order ID $id; no rows removed";
			}
		}
		$commitResult = transaktion('commit');
		$finished = true;
		if (!$commitResult || $db_modify_fejl) {
			return "Failed to commit deletion of order ID $id";
		}
		return 0;
	} finally {
		if (!$finished) {
			transaktion('rollback');
		}
	}
}
