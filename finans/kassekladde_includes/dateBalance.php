<?php
// -- finans/kassekladde_includes/dateBalance.php -- lap 4.0.8 -- 2015-04-24 --
//
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2023 Saldi.dk ApS
// --------------------------------------------------------------------------------

// Return an account's balance at the specified date
function dateBalance($account,$financialyear,$recorddate) {
	global $db;
	$balance = 0;
	$qtxt = "select box1,box2 from grupper where kodenr='$financialyear' and art='RA'";
	$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$startmonth=$row['box1'];
	$startyear=$row['box2'];
	$financialstart=$startyear. "-" . $startmonth . "-" . '01';
	$qtxt = "select debet,kredit from transaktioner where transdate>='$financialstart' ";
	$qtxt.= "and transdate<='$recorddate' and kontonr='$account' order by transdate";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$balance+=round($r['debet']+0.0001,2)-round($r['kredit']+0.0001,2);
	}
/*
	$qtxt = "select * from kontoplan where kontonr='$account' and regnskabsaar='$financialyear' and lukket != 'on'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	if ( $r=db_fetch_array($q) ) {
		if ( $r['kontotype']==='S' ) $balance+=afrund($r['primo'],2);
	}
*/
	return $balance;
}
?>
