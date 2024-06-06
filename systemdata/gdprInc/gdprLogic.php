<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//
// --- systemdata/gdprInc/gdprLogic.php --- ver 4.0.4 --- 2023-01-09 --
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------------

if ($deleteAccounts) {
echo "DA $deleteAccounts<br>";

	$deleteAccount = $_POST['deleteAccount'];
	$accountId     = $_POST['accountId'];
echo __line__ . count($accountId)."<br>";
	for ($i=0; $i<count($accountId); $i++) {
		echo "I $i<br>";
echo __line__." $i | $deleteAccount[$i] || $accountId[$i]<br>";		
		if (isset($deleteAccount[$i]) && $deleteAccount[$i]) {
			$qtxt = "select id from jobkort where konto_id = '$accountId[$i]'";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				db_modify("delete from jobkort_felter where job_id = '$r[id]'",__FILE__ . " linje " . __LINE__);
				db_modify("delete from jobkort where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
			}
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "delete from historik where konto_id = '$accountId[$i]'";
echo __line__." $i -> $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "delete from adresser where id = '$accountId[$i]'";
echo __line__." $i -> $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
} else {
	$accountId = array();
	$gdprDate  = date('Y')-3 ."-". date('m') ."-". date('d');
	$fiveYear = 
	$i = 0;
	$qtxt = "select * from adresser where (art = 'D' or art = 'K') and modtime <'$gdprDate' order by art,firmanavn";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$deleteAccount[$i] = 1;
		$accountId[$i]     = $r['id'];
		$accountNo[$i]   = $r['kontonr'];
		$accountName[$i] = $r['firmanavn'];
		$accountType[$i] = $r['art'];
		$i++;
	}
	for ($i=0; $i<count($accountId); $i++) {
		$qtxt = "select id from ordrer where konto_id = '$accountId[$i]' limit 1";
#cho __line__." $qtxt<br>";
		if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $deleteAccount[$i] = 0;
		if ($deleteAccount[$i]) {
			$qtxt = "select id from openpost where konto_id = '$accountId[$i]' limit 1";
			if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $deleteAccount[$i] = 0;
		}
		if ($deleteAccount[$i]) {
			$qtxt = "select id from historik where konto_id = '$accountId[$i]' and notedate >= '$gdprDate' limit 1";
			if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $deleteAccount[$i] = 0;
		}
		if ($deleteAccount[$i]) {
			$qtxt = "select id from jobkort where konto_id = '$accountId[$i]' and initdate  >= '$gdprDate' limit 1";
			if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $deleteAccount[$i] = 0;
		}
	}
}
/*
	if ($deleteAccount[$i]) {
#			$qtxt = "delete from adresser where id = $accountId[$i]";
#cho __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#			$qtxt = "delete from historik where konto_id = $accountId[$i]";
#cho __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#			$qtxt = "delete from ansatte where konto_id = $accountId[$i]";
#cho __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#			$qtxt = "delete from jobkort where konto_id = $accountId[$i]";
#cho __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
*/

?>
