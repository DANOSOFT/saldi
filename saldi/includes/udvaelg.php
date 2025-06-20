<?php
// -------------/includes/udvaelg.php--------lap 3.5.9----2015.01.05-----
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
// Copyright (c) 2003-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2015.01.05 Retter fejlindtastning til noget brugbart 20150105-1
// 2015.01.05 sikrer at numeriske værdier er numeriske ved at gange med 1 20150105-2
// 2015.06.01 Ved beløb skal , ikke erstattes af ":", for så kan man ikke søge på decimaler.
// 2015.10.19 Ved enkelbeløb findes beløb ikke hvis de ikke stemmer på decimalen da der bogføres med 3 decimaler.

if (!function_exists('udvaelg')){
	function udvaelg ($tmp, $key, $art){
		include("../includes/std_func.php");
		$tmp=strtolower($tmp);
		if ($art) { #20150105-1
			if ($art!='BELOB') $tmp=str_replace(",",":",$tmp); #20150601
			$tmp=str_replace(";",":",$tmp);
			if ($art=='BELOB' && !strpos($tmp,':')) { #20151019
				$tmp=usdecimal($tmp);
				$tmp1=$tmp-0.005;
				$tmp2=$tmp+0.004;
				$tmp=number_format($tmp1,3,',','').":".number_format($tmp2,3,',','');
			}
		}
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
				$tmp1=afrund($tmp1*1,2); #21050105-2
				$tmp2=afrund($tmp2*1,2);
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
			if (!$art) {
				$tmp=str_replace("*","%",$tmp);
				$tmp=db_escape_string($tmp);
				$udvaelg= " and lower($key) like '$tmp'";
				} else $udvaelg= " and $key = '$tmp'";
			}
		return $udvaelg;
	}
}
?>
