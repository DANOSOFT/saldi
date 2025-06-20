<?php

// --------------index/login.php----------lap 2.1.0----- 2009-11-01------
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

if (!function_exists('tjek4opdat')) {
	function tjek4opdat($dbver,$version) {
		if ($dbver<$version) {
			$tmp = str_replace(".",";",$dbver);		
			list($a, $b, $c)=split(";", trim($tmp)); 
			if ($a==0) {
				include("../includes/opdat_0.php");
				opdat_0('1.0', $dbver);
				$a=1;$b=0;
			}
			if ($a==1) {
				if ($b==0) {
					include("../includes/opdat_1.0.php");
					opdat_1_0($b, $c);
					$b=1;
				}
				if ($b==1) {
					include("../includes/opdat_1.1.php");
					opdat_1_1($b, $c);
					$b=9; $c=0;
				}
				if ($b==9) {
					include("../includes/opdat_1.9.php");
					opdat_1_9($b, $c);
					$a=2;$b=0;$c=0;
				}
			} 
			if ($a==2) {
				if ($b==0) {	
				include("../includes/opdat_2.0.php");
					opdat_2_0($b, $c);
					$b=1;$c=0;
				}
				if ($b==1) {
				include("../includes/opdat_2.1.php");
					opdat_2_1($b,$c);
					$a=3;$b=0;$c=0;
				}	
			} 
			if ($a==3) {
				if ($b==0) {	
				include("../includes/opdat_3.0.php");
					opdat_3_0($b, $c);
				}
			}
		}
	}
}
?>