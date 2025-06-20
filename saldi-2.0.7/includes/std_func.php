<?php
// -----------includes/std_func.php-------lap 2.0.7----2009-05-21-----
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------
if (!function_exists('nr_cast')) {
	function nr_cast($tekst)
	{
		global $db_type;
		if ($db_type=='mysql') $tmp = "CAST($tekst AS SIGNED)";
		else $tmp = "to_number(text($tekst),text(99999999))";
		return $tmp;
	}
}
if (!function_exists('dkdecimal')) {
	function dkdecimal($tal)
	{
		$tal=round($tal+0.0001,2);#Afrunding tilføjet 2009.01.26 grundet diff i ordre 98 i saldi_104
		$tal=number_format($tal,2,",",".");
		return $tal;
	}
}
if (!function_exists('dkdato')) {
	function dkdato($dato)
	{
		if ($dato) {
			list ($year, $month, $day) = split ('-', $dato);
 	   $month=$month*1;
 	   $day=$day*1;
			if ($month<10){$month='0'.$month;}
			if ($day<10){$day='0'.$day;}
			$dato = $day . "-" . $month . "-" . $year;
			return $dato;
		}
	}
}
if (!function_exists('if_isset')) {
	function if_isset(&$var)
	{
		return isset($var)? $var:NULL;
	}
}
if (!function_exists('usdate')) {
	function usdate($date) 
	{
		global $regnaar;
		$day=NULL;$month=NULL;$year=NULL; 
		
		$date=trim($date);
		
		if (!isset($date) || !$date) $date=date("dmY");
		
		$date=str_replace (".","-",$date);
		$date=str_replace (" ","-",$date);
		$date=str_replace ("/","-",$date);
				
		if (strpos($date,"-")) list ($day, $month, $year) = split ('-', $date);
		if ($year) $year=$year*1;
		if ($month) $month=$month*1;
		if ($day) $day=$day*1;
		if ($year && $year<10) $year='0'.$year;
		elseif (!$year) $year="";
		if ($month && $month<10) $month='0'.$month;
		elseif (!$month) $month="";
		if ($day && $day<10) $day='0'.$day; 
		if ($day) $date=$day.$month.$year;

		if (strlen($date) <= 2) {
				$date=$date*1;
			if ($date<10) $date='0'.$date;
			$date=$date.date("m"); 
		}	
		if (strlen($date) <= 4) {
			$g1=substr($date,0,2);
			$g2=substr($date,2,2);
			if ($r = db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__))){
				$startmaaned=trim($r['box1']);
				$startaar=trim($r['box2']);
				$slutmaaned=trim($r['box3']);
				$slutaar=trim($r['box4']);
				if ($startaar==$slutaar) $g3=$startaar;
				elseif ($g2>=$startmaaned) $g3=$startaar;
				else $g3=$slutaar;
			} else {
				$alerttekst='Regnskabs&aring;r ikke oprettet!';
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				exit;
			}	
			$date=$g1."-".$g2."-".$g3;
		} elseif (strlen($date) <= 6) {
			$g1=substr($date,0,2);
			$g2=substr($date,2,2);
			$g3=substr($date,4,2);
			$date=$g1."-".$g2."-".$g3;
		} else {
			$g1=substr($date,0,2);
			$g2=substr($date,2,2);
			$g3=substr($date,4,4);
			$date=$g1."-".$g2."-".$g3;
		} 
		
		
		

		list ($day, $month, $year) = split ('-', $date);

		
		$year=$year*1;
		$month=$month*1;
		$day=$day*1;
		
		if ($year<10){$year='0'.$year;}
		if ($month<10){$month='0'.$month;}
		if ($day<10){$day='0'.$day;}
		 
		if ($day>28) {
			while (!checkdate($month,$day,$year)){
				$day=$day-1;
				if ($day<28){break;}
			}
		}
		 
		if ($year < 80) {$year = "20".$year;}
		elseif ($year < 100) {$year = "19".$year;}

		if (checkdate($month, $day, $year)) {$date = $year . "-" . $month . "-" . $day;}
		else {$date=date("Y-m-d");}
		
		return $date;
	}
}
if (!function_exists('usdecimal')) {
	function usdecimal($tal)
	{
		
		if (!$tal){$tal="0,00";}
		$tal = str_replace(".","",$tal);
		$tal = str_replace(",",".",$tal);
		$tal=$tal*1;
		$tal=round($tal+0.0001,3);
		if (!$tal){$tal="0.00";}
		return $tal;
	}
}
if (!function_exists('findtekst')) {
	function findtekst($tekst_id,$sprog_id)	{
		global $db_encode;
		$tekst_id=$tekst_id*1;
		$sprog_id=$sprog_id*1;
		if ($r = db_fetch_array(db_select("select tekst from tekster where tekst_id='$tekst_id' and sprog_id = '$sprog_id'",__FILE__ . " linje " . __LINE__))){
			$tekst=$r['tekst'];
		} else {
			$fp=fopen("../importfiler/tekster.csv","r");
			if ($fp) {
				while (!feof($fp)) {
					if ($linje=trim(fgets($fp))) {
						list($tekst_nr,$tmp)=split(chr(9),$linje);
						if ($tekst_id==$tekst_nr) {
						$tekst=substr(stristr($linje,chr(9)),1);# Linjen efter 1. tab. 
							for ($i=1;$i<=$sprog_id;$i++) $linje = substr(stristr($linje,chr(9)),1); # Start paa tekst med aktuel sprog id findes.
							list($tekst,$tmp)=split(chr(9),$linje); # Tekststrengen isoleres	
						}
					}
				}		
			}
			if ($tekst) {
				if ($db_encode=="UTF8") $tekst=utf8_encode($tekst);
				db_modify("insert into tekster(sprog_id,tekst_id,tekst) values ('$sprog_id','$tekst_id','$tekst')",__FILE__ . " linje " . __LINE__);
			} else $tekst="Tekst nr: $tekst_id";
			fclose($fp);
		}
#		$tekst=htmlentities($tekst);
		return $tekst;
	}
}
if (!function_exists('javascript')) {
	function javascript()	{
		
	}
}	
?>
