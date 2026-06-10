<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/fiscalYear.php -----patch 4.1.0 ----2024-01-14------------
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------

function fiscalYear($regnaar) {
	global $sprog_id; #20210720
	if ($row = db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__))){
		$start=trim($row['box2'])."-".trim($row['box1'])."-01";
		$slut=usdate("31-".trim($row['box3'])."-".trim($row['box4']))	; #usdate bruges for at sikre korrekt dato.
	} else {
		#$alerttekst='Regnskabs&aring;r ikke oprettet!';
		$alerttekst=findtekst(1596, $sprog_id);
		alert($alerttekst);
		exit;
	}
	return $start.":".$slut;
}
?>
