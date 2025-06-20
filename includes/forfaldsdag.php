<?php
// -----------/includes/forfaldsdag.php ----------patch 2.0.7------2009.03.25-------------
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

if (!function_exists('dkdato')) include("../includes/std_func.php");
	
if (!function_exists('forfaldsdag')) { 
	function forfaldsdag($fakturadate, $betalingsbet, $betalingsdage) {
		$betalingsbet=strtolower($betalingsbet);
		
		list($faktaar, $faktmd, $faktdag) = explode("-", $fakturadate);
		$forfaldsaar=$faktaar; 
		$forfaldsmd=$faktmd;
		$forfaldsdag=$faktdag;
		$slutdag=31;

		if (($fakturadate)&&($betalingsbet!="efterkrav")) {
			while (!checkdate($forfaldsmd, $slutdag, $forfaldsaar)) {
				$slutdag--;
				if ($slutdag<27) break;
			}
			if ($betalingsbet!="netto") $forfaldsdag=$slutdag; # Saa maa det vaere lb. md
			$forfaldsdag=$forfaldsdag+$betalingsdage;
			while ($forfaldsdag>$slutdag) {
				$forfaldsmd++;
				if ($forfaldsmd>12) {
					$forfaldsaar++;
					$forfaldsmd=1;
				}
				$forfaldsdag=$forfaldsdag-$slutdag;
				$slutdag=31;
				while (!checkdate($forfaldsmd, $slutdag, $forfaldsaar)) {
					$slutdag--;
					if ($slutdag<27) break;
				}
			}		 
		}
		return(dkdato($forfaldsaar."-".$forfaldsmd."-".$forfaldsdag));
	}
}
?>
