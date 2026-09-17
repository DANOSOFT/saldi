<?php
// --- includes/docsIncludes/updateCashDraft.php -----patch 4.0.8 ----2024-01-19---
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2024-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
if ($moveDoc) {
	if (!$sourceId) {
		$qtxt = "insert into kassekladde (kladde_id) values ('$kladde_id')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "selext max(id) as sourceid from kassekladde where kladde_id = '$kladde_id'";
		$r =  db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$sourceId = $r['sourceid'];
	}
/*
	$insertFile  = $_POST['insertFile'];
	$bilag       = $_POST['bilag'];
	$dato        = $_POST['dato'];
	$beskrivelse = $_POST['beskrivelse'];
	$debet       = $_POST['debet'];
	$kredit      = $_POST['kredit'];
	$fakturanr   = $_POST['fakturanr'];
	$sum         = $_POST['sum'];
*/
}
?>
