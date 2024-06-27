<?php
//          ___   _   _   ___  _     ___  _ _
//         / __| / \ | | |   \| |   |   \| / /
//         \__ \/ _ \| |_| |) | | _ | |) |  <
//         |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/api/restApiIncludes/getNextAccountNo --- lap 4.0.5 --- 2022-03-09 ---
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

function getNextAccountNo($accountType) {
	global $db;

	$log=fopen("../temp/$db/rest_api.log","a");
	
	$minNo    = if_isset($_GET['minNo']);
	$maxNo    = if_isset($_GET['maxNo']);
	if (isset($_GET['accountType'])) $accountType = $_GET['accountType'];

	
	fwrite($log,__line__." minNo $minNo maxNo $maxNo\n");
	if (!$accountType) $accountType = 'D';
	if (!$minNo)       $kontonr     = 0;
	if (!$maxNo)       $kontonr     = 999999;
	
	fwrite($log,__line__." minNo $minNo maxNo $maxNo\n");

	$qtxt = "select kontonr from adresser where art = '$accountType'";
	fwrite($log,__line__." $qtxt\n");
	$q= db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['kontonr'] < 10000 && $r['kontonr'] >= $kontonr) {
			$kontonr = $r['kontonr']+1;
		}
	}
	fwrite($log,__line__." kontonr $kontonr\n");
	fclose ($log);
	return ($kontonr);
}
?>
