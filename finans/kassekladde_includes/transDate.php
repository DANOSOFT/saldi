<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -- finans/kassekladde_includes/transdate_inc.php ---- lap 4.0.8 -- 2023-03-22 --
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
// --------------------------------------------------------------------------------

// Return the next tranactions date on the specified account
function transDate($account,$date,$prevnext='next') {
	$retval="9999-12-31";
	if ( strtolower($prevnext)==='next' ) {
		$query = db_select("select transdate from transaktioner where transdate>'$date' and kontonr='$account' order by transdate asc limit 1",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query) ) {
			$retval=$row['transdate'];
		} else {
			$retval="9999-12-31";
		}
	} else {
		$query = db_select("select transdate from transaktioner where transdate<='$date' and kontonr='$account' order by transdate desc limit 1",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query) ) {
			$retval=$row['transdate'];
		} else {
			$retval="1970-01-01";
		}
	}
	return $retval;
}
?>
