<?php
// -- finans/kassekladde_includes/transdate_inc.php ---- lap 4.0.8 -- 2023-03-22 --
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

// Return the next tranactions date on the specified account
function transdate($account,$date,$prevnext='next') {
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
