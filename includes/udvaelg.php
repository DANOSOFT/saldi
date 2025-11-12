<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -------------/includes/udvaelg.php--------lap 4.1.1----2025.11.06-----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2025 saldi.dk aps
// ----------------------------------------------------------------------
// 2015.01.05 Retter fejlindtastning til noget brugbart 20150105-1
// 2015.01.05 sikrer at numeriske værdier er numeriske ved at gange med 1 20150105-2
// 2015.06.01 Ved beløb skal , ikke erstattes af ":", for så kan man ikke søge på decimaler.
// 2015.10.19 Ved enkelbeløb findes beløb ikke hvis de ikke stemmer på decimalen da der bogføres med 3 decimaler.
// 20170509 PHR Søgning med wildcards i TXT'er
// 20180228 PHR Fejlrettelse i ovenstående.
// 20231113 PHR Added lower & upper to text search
// 01072025 PBLM Added else $udvaelg= " and $key::text like '$tmp%'"; on line 97 for better search


/*
 * The function 'udvaelg' generates a SQL WHERE condition based on the inputs.
 * It uses `$tmp` (a string to be searched or matched) and `$key` (the column or field to compare against) 
 * to create various types of queries depending on the value of `$art` (which could represent the type of data being processed).
 * 
 * The function handles different types of input:
 * - `$art` can specify types like "DATO", "TID", "TEXT", "NR", "BELOB", etc., which change how `$tmp` is processed.
 * - If `$art` is "BELOB", the function creates a range using the values around `$tmp`.
 * - If `$art` is "DATO", it converts dates to a specific format.
 * - If `$art` is "TEXT", it creates more complex string comparisons, including case-insensitive matching and handling of wildcards.
 * 
 * In general, this function is used to create parts of SQL queries dynamically, based on various input types.
 */




if (!function_exists('udvaelg')){
	function udvaelg ($tmp, $key, $art){
		$tmp = db_escape_string($tmp);
		mb_internal_encoding('UTF-8');

		if ($tmp) $tmp=trim($tmp,"'");
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
		if (strstr($tmp,':') && ($art!='TID' && $art!='TEXT')){
			list ($tmp1, $tmp2)=explode(":", $tmp,2);
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
			} elseif ($art=="TEXT") {
				if (strstr($tmp,'*')) {
					$tmp=str_replace('*','%',$tmp);
					$udvaelg = " and ($key like '$tmp'";
					$udvaelg.= " or lower($key) like '".mb_strtolower($tmp)."'";
					$udvaelg.= " or upper($key) like '".mb_strtoupper($tmp)."')";
				} else {
					$udvaelg = " and ($key = '$tmp'";
					$udvaelg.= " or lower($key) like '".mb_strtolower($tmp)."'";
					$udvaelg.= " or upper($key) like '".mb_strtoupper($tmp)."'";
					$udvaelg.= " or lower($key) like '%".mb_strtolower($tmp)."%'";
					$udvaelg.= " or upper($key) like '%".mb_strtoupper($tmp)."%')";
				}
			} else $udvaelg= " and $key::text like '$tmp%'";
			}
		return $udvaelg;
	}
}

?>
