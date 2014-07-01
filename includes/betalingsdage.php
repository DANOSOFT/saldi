<?php
// ---------------------------------/includes/betalingsdage.php ----------patch 0.936-------------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------

include("../includes/dkdato.php");
	
if (!function_exists('betalingsdage')) { 
	function betalingsdage($fakturadate,$forfaldsdate) {
		list($faktaar, $faktmd, $faktdag) = explode("-", $fakturadate);
		list($forfaldsaar, $forfaldsmd, $forfaldsdag) = explode("-", $forfaldsdate);
		$diffaar=$forfaldsaar-$faktaar;
		$diffmd=$forfaldsmd-$faktmd;
		$diffdag=$forfaldsdag-$faktdag;
		
		$dage=0;
		
		if ($diffmd) {
			switch ($faktmd) {
				case 1:
				case 3:
				case 5:
				case 7:
				case 8:
				case 10:
				case 12:
					$dage=$dage+31-$faktdag;
					break;
				case 2:
    			if (checkdate($faktmd,29,$faktaar)) $dage=$dage+29-$faktdag;
					else $dage=$dage+28-$faktdag;
					break;	
				case 4:
				case 6:
				case 9:
				case 11:
    			$dage=$dage+30-$faktdag;
					break;
			}
			$dage=$dage+$forfaldsdag;	
			
		}
		if ($diffaar) {
			
		}
		
		
		return(dkdato($forfaldsaar."-".$forfaldsmd."-".$betalingsdage));
	}
}
?>
