<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunvcheckOpenFiscalYear.php--- lap 4.1.1 --- 2024-09-05 ----
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
// Copyright (c) 2003-2024 saldi.dk aps
// ----------------------------------------------------------------------
// This function checks if the date is in at fiscak year where accounting is allowed.
function checkOpenFiscalYear($date) {
	global $regnaar;
	
	$i = 0;
	list($y,$m,$d) = explode('-',$date);
	$ym = $y.$m;	
	$open = 0;
	$qtxt = "select * from grupper where art = 'RA' and box5 = 'on' and kodenr = '$regnaar' order by kodenr"; // #box5 = accounting allowed1
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$startdate = $r['box2'].$r['box1'];
		$enddate   = $r['box4'].$r['box3'];
		if ($ym >= $startdate && $ym <= $enddate) $open = 1;
	}
	return ($open);
}
?>
