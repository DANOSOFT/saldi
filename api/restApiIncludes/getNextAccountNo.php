<?php
//          ___   _   _   ___  _     ___  _ _
//         / __| / \ | | |   \| |   |   \| / /
//         \__ \/ _ \| |_| |) | | _ | |) |  <
//         |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/api/restApiIncludes/getNextAccountNo --- ver 5.1.0 --- 2026-11-05 ---
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
// Copyright (c) 2016-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261105 CL/LH The next account number is the one after the highest numeric kontonr from minNo to maxNo (default 1 to 9999, the range the loop already used), or an error text when that range is used up.
//                With both bounds sent, $kontonr was never set, so an empty kontonr reached '' + 1 and the request died with an HTTP 500 TypeError; without bounds it always answered 999999.
//                A minNo above 9999 sent without maxNo has no upper end, so minNo=880100 alone gives 880100 and on, not an error.
//                Only a kontonr of digits counts (2e3 or 12.5 is text), and a minNo below 1 starts at 1.

function getNextAccountNo($accountType) {
	global $db;

	$log=fopen("../temp/$db/rest_api.log","a");
	
	$minNo    = (int)ifset($_GET, 'minNo');
	$maxNo    = (int)ifset($_GET, 'maxNo');
	if (isset($_GET['accountType'])) $accountType = $_GET['accountType'];

	
	fwrite($log,__line__." minNo $minNo maxNo $maxNo\n");
	if (!$accountType) $accountType = 'D';
	if ($minNo < 1)    $minNo       = 1;
	// maxNo defaults to 9999, the end the loop always used, but never below minNo: a minNo above it alone has no upper end (one below PHP_INT_MAX, so the next number stays an int)
	if ($maxNo < 1)    $maxNo       = ($minNo <= 9999) ? 9999 : PHP_INT_MAX - 1;
	
	fwrite($log,__line__." minNo $minNo maxNo $maxNo\n");

	$kontonr = $minNo;
	$qtxt = "select kontonr from adresser where art = '" . db_escape_string($accountType) . "'";
	fwrite($log,__line__." $qtxt\n");
	$q= db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!ctype_digit((string)$r['kontonr'])) continue; // an empty or text kontonr (also 2e3 or 12.5) is not in the sequence
		$nr = (int)$r['kontonr'];
		if ($nr >= $kontonr && $nr <= $maxNo) {
			$kontonr = $nr + 1;
		}
	}
	fwrite($log,__line__." kontonr $kontonr\n");
	fclose ($log);
	if ($kontonr > $maxNo) return "No free account number from $minNo to $maxNo";
	return ($kontonr);
}
?>
