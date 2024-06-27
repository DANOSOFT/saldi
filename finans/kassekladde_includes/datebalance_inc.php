<?php
// -- finans/kassekladde_includes/datebalance_inc.php -- patch 4.0.7--- 2023.03.04 --
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// --------------------------------------------------------------------------------

// Return an account's balance at the specified date
function datebalance($account,$financialyear,$recorddate) {
	$balance=0;
	$query = db_select("select box1,box2 from grupper where kodenr='$financialyear' and art='RA'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$startmonth=$row['box1'];
	$startyear=$row['box2'];
	$financialstart=$startyear. "-" . $startmonth . "-" . '01';
	$q = db_select("select debet,kredit from transaktioner where transdate>='$financialstart' and transdate<='$recorddate' and kontonr='$account' order by transdate",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$balance+=round($r['debet']+0.0001,2)-round($r['kredit']+0.0001,2);
	} 

	$q = db_select("select * from kontoplan where kontonr='$account' and regnskabsaar='$financialyear' and lukket != 'on'",__FILE__ . " linje " . __LINE__);
	if ( $r=db_fetch_array($q) ) {
#		if ( $r['kontotype']==='S' ) $balance+=afrund($r['primo'],2);
	}
	return $balance;
}
?>
