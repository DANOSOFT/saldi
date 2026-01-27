<?php
// -----------includes/tid2decimal.php-------lap 3.0.2----2010-05-27-----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg

// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------

if (!function_exists('tid2decimal')) {
	function tid2decimal ($tid, $returenhed="timer", $arbdag_sekunder="26640") {
		# Adskillelsetegnet mellem timer, minutter og eventuelle sekunder skal vaere :
		# Returenhed kan vaere sekunder, minutter, timer, arbejdsdage, doegn, 
		if ( strstr($tid, ":") ) {
			$tidsenhed = explode(":", $tid); # 0=timer, 1=minutter og 2=sekunder
			if ( $tidsenhed[2] ) {
				$retur = 3600*$tidsenhed[0]+60*$tidsenhed[1]+$tidsenhed[2];
			} else {
				$retur = 3600*$tidsenhed[0]+60*$tidsenhed[1];
			}
		} else {
			$retur = 0;
		}

		if ( substr($returenhed, 0, 1) == substr("doegn", 0, 1) ) $retur = $retur/(3600*24);
		if ( substr($returenhed, 0, 1) == substr("arbejdsdag", 0, 1) ) $retur = $retur/$arbdag_sekunder;
		if ( substr($returenhed, 0, 1) == substr("timer", 0, 1) ) $retur = $retur/3600;
		if ( substr($returenhed, 0, 1) == substr("minutter", 0, 1) ) $retur = $retur/60;

		if ( strstr($retur, ".") ) $retur = str_replace(".", ",", $retur);
		return $retur;
	}
}
?>

