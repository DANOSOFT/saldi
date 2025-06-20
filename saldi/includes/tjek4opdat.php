<?php

// --------------includes/tjek4opdat.php----------lap 3.6.2----- 2016-01-16------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
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
// Copyright (c) 2004-2016 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2013.09.06 Indsat $b=3;$c=0;  Søg 20130906  
// 2014.05.02 Indsat $b==4{ osv. PHR Danosoft Søg 20140502
// 2015.02.09 Indsat $b==5{ osv. PHR Danosoft Søg 20150209
// 2016.01.16 Indsat $b==6{ osv. PHR Danosoft Søg 20160116

if (!function_exists('tjek4opdat')) {
	function tjek4opdat($dbver,$version) {
		if ($dbver<$version) {
			$tmp = str_replace(".",";",$dbver);		
			list($a, $b, $c)=explode(";", trim($tmp)); 
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
					opdat_3_0($b,$c);
					$b=1;$c=0;
				}
				if ($b==1) {
					include("../includes/opdat_3.1.php");
					opdat_3_1($b,$c);
					$b=2;$c=0;
				}
				if ($b==2) {
					include("../includes/opdat_3.2.php");
					opdat_3_2($b,$c);
					$b=3;$c=0; #20130906
				}
				if ($b==3) {
					include("../includes/opdat_3.3.php");
					opdat_3_3($b,$c);
					$b=4;$c=0;
				}
				if ($b==4) { #20140502
					include("../includes/opdat_3.4.php");
					opdat_3_4($b,$c);
					$b=5;$c=0;
				}
				if ($b==5) { #20150209
					include("../includes/opdat_3.5.php");
					opdat_3_5($b,$c);
					$b=6;$c=0;
				}
				if ($b==6) { #20161016
					include("../includes/opdat_3.6.php");
					opdat_3_6($b,$c);
					$b=7;$c=0;
				}
			}
		}
	}
}
?>