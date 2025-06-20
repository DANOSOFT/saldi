<?php
// -------------/includes/udvaelg.php--------lap 2.0.2a----2008.08.20-----
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
	// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------
#include("../includes/usdate.php");

if (!function_exists('udvaelg')){
	function udvaelg ($tmp, $key, $art){
		include("../includes/std_func.php");
		$tmp=strtolower($tmp);
		list ($tmp1, $tmp2)=split(":", $tmp);
		if ((strstr($tmp,':'))&&($art!='TID')){
			if ($art=="DATO"){
				$tmp1=usdate($tmp1);
				$tmp2=usdate($tmp2);
			}
			elseif ($art=="BELOB"){
				$tmp1=usdecimal($tmp1);
				$tmp2=usdecimal($tmp2);
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
			elseif ($art=="NR") $tmp=round($tmp);
			if (!$art) {
				$tmp=str_replace("*","%",$tmp);
				$udvaelg= " and lower($key) like '$tmp'";
				} else $udvaelg= " and $key = '$tmp'";
			}
		return $udvaelg;
	}
}
 ?>
