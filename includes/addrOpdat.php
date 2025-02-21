<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------ includes/addrOpdat.php-------lap 3.9.1 ------2020-05-22---------------
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
// Copyright (c) 2003-2020 Saldi.dk ApS
// ----------------------------------------------------------------------

	$x=0;
	$qtxt="select id from adresser where invoiced is NULL limit 1000";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
	 $addrId[$x]=$r['id'];
	 $x++;
	}
	for ($x=0;$x<count($addrId);$x++) {
		$qtxt="select max(fakturadate) as fdate from ordrer where konto_id='$addrId[$x]'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$fdate=trim($r['fdate']);
		if (!$fdate) $fdate='1970-01-01';
		$qtxt="update adresser set invoiced = '$fdate' where id='$addrId[$x]'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
	}
?>
