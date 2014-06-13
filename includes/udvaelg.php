<?php
// -------------/includes/udvaelg.php--------lap 3.4.0----2014.03.19-----
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
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------
#include("../includes/usdate.php");

if (!function_exists('udvaelg')){
	function udvaelg ($tmp, $key, $art){
		include("../includes/std_func.php");
		$tmp=strtolower($tmp);
		list ($tmp1, $tmp2)=explode(":", $tmp);
		if ((strstr($tmp,':'))&&($art!='TID')){
			if ($art=="DATO"){
				$tmp1=usdate($tmp1);
				$tmp2=usdate($tmp2);
			}
			elseif ($art=="BELOB"){
				$tmp1=usdecimal($tmp1);
				$tmp2=usdecimal($tmp2);
			}
			elseif ($art=="NR") {
				$tmp1=round($tmp1);
				$tmp2=round($tmp2);
			}
			$udvaelg= "and $key >= '$tmp1' and $key <= '$tmp2'";
		} else {
			if ($art=="TID") {
				if (!strstr($tmp,':')) {
					$tmp=$tmp*1;
					$tmp=str_replace(".",":",$tmp);
					if (!strstr($tmp,':')) $tmp=$tmp.":";
				}
			}
			elseif ($art=="DATO") $tmp=usdate($tmp);
			elseif ($art=="BELOB") $tmp=usdecimal($tmp);
			if (!$art) {
				$tmp=str_replace("*","%",$tmp);
				$udvaelg= " and lower($key) like '$tmp'";
				} else $udvaelg= " and $key = '$tmp'";
			}
		return $udvaelg;
	}
}
?>
