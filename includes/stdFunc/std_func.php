<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -----------includes/std_func.php---- lap 4.0.6 -- 2022-07-16 ---
// LICENS
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2022 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20130210 Break ændret til break 1 Tastefejl rettet.
// 20140501 Funktion findtekst - teksten ignoreres nu hvis tekst="-"
// 20140505 Funktion findtekst insdat db_escape_string. (PHR - Danosoft) Søg 20140505
// 20141010 CA  Funktionen farvenuance antager hvid baggrund, hvis ingen angivet. Søg 20141010
// 20141031 CA  Funktionen advarselsboks skal erstatte JavaScript Alertbokse - er ikke færdig. Søg 20141031
// 20141031 PHR tilføjet funktion "find_varemomssats"
// 20141121 CA  Funktionen tekstsboks erstatter advarselsboks en udgave med og uden tabel. Søg 20141121
// 20141121 CA  Tilføjet funktion bokshjoerne til flytning af tekstbokse. Søg 20141121
// 20141223 PHR Tilføjet funktion find_lagervaerdi til brug med aut_lager. Søg 20141223
// 20150218 PK  Tilføjet to funktioner til at lave uppercase på tekst-streng. Søg mb_ucfirst eller mb_ucwords
// 20150313 CA  Byttet om på farverne for infoboks (nu blå) og popop (nu grøn). Søg 20150313
// 20160116 PHR Oprettet funktion regnstartslut
// 20161011 PHR Oprettet funktion lagerreguler
// 20161022 PHR Rettet funktion lagerreguler $diff skal ikke fratrækkes $rest 20161022
// 20161124 PHR Rettet funktion lagerreguler så lagerstatus fjernes for lagre > 0 hvis der ikke er flere lagre  Søg 20161022
// 20161217 PHR Tilføjet funktion find_beholdning (Flyttet fra lager/varer.php)
// 20161222 PHR Rettet funktion lagerreguler så dubletter på lager 0 slettes hvis der ikke er flere lagre.
// 20161222 PHR Rettet fejl. Lager o blev ikke fundet så dublet blev oprettet på lager 0
// 20170404 PHR Funktion 'find_lagervaerdi' udelader nu ikke bogførte ordrer da disse kan give skæve tal.
// 20180119 PHR Tilføjet funktion hent_shop_ordrer som opdaterer ordrer fra shop.
// 20180123 PHR En del rettelser i funktion lagerreguler i forhold til varianter og flere lagre.
// 20180518 PHR Tilføjet funktion alert.
// 20181126 PHR Variabeldefiniton i div. funktioner. 
// 20181220 MSC - Rettet fejl
// 20190104 PHR	- " nye funktioner create_debtor & get_next_number
// 20190221 MSC - Flytter $boksid="" til enden søg edit 2019.02.21 for kode
// 20190423 PHR - 'bynavn' (city) was not inserted in 'adresser' if not set in function create_debtor. 20190423
// 20190605 PHR - Added if_isset to aviod warning. 20190605
// 20190704 RG (Rune Grysbæk) Mysqli implementation 
// 20200326 PHR Optimized function findtekst.
// 20200327 PHR Moved function barcode form lager/varekort
// 20200407 PHR Some minor changes to function barcode to work with kreditor/ordre.php
// 20200907 PHR Function sync_shop_vare. Query parted in 2 to make it find varenr if no shop_id
// 20200917 PHR	- Added missing email in create_debtor
// 20210225 LOE Added active language function
// 20200917 PHR Added missing email in create_debtor
// 20201119 PHR Some optimation in function lagerreguler an extended logging in function sync_shop_vare 
// 20210107 PHR Function cvrnr_omr. Removed GB from EU
// 20210107 PHR Function lagerreguler. Added file & line to wget call. 
// 20210301 CA  Added function trim_utf8. Trims for traditional whitespaces and &nbsp;
// 20210302 LOE Function csv_to_array() and engdan() added 
// 20210303 LOE Added this block to handle a situation when tekster table is empty
// 20210304 LOE Replaced the if(!$teskt) fuction with an updated version 
// 20210409 LOE Modified engdan() to accomondate other languages
// 20210419 LOE Did some modifications in findtekst function 20210419
// 20210421 LOE modified added activelanguage id activelanguage() function 20210421
// 20210427 LOE Modified active language function
// 20210428 LOE findtekst function is modified to accomodate admin during installation Danish as the default 20210428
// 20210429 LOE activeLanguage function also updated 
// 20210517 Loe Updated findtekst function using abs function to get negative ids for admins positive and updated some where it applies
// 20210518 LOE Added this to check handle more than one account for the admin ..if the initial language still exists it updates the table with active one
// 20210704 LOE Little modification of findtekst() function
// 20210818 LOE Did some modication to findtekst function checking before insert if the value already exists in tekster table
// 20210828 LOE Updated findtekst function 
// 20210830 LOE Created get_ip function...This is used in login.php file and in brugere.php
// 20210831 LOE Created restrict_user_ip and restore_user_ip functions
// 20210901 LOE Created authenticate_user_ip function
// 20210908 LOE Created input_ip function
// 20210909 LOE Modified some function relating to Ip
// 20210915 LOE Modified activeLanguage function for older vesion compatibility
// 20210916 LOE Modified findtekst function to have a default language for develop db
// 20210917 LOE Set sprog_id for admins and updated some codes
// 20210922 LOE Added session language for admin users.
// 20211001 LOE Added lingua as language set for admins*
// 20211006 LOE Updating some findtekst function codes
// 20211007 LOE For admin checking before insert if the value already exists in tekster table
// 20211008 PHR Function findtekst rolled back.
// 20211013 PHR Function sync_shop_vare & lagerreguler - added totalStock 
// 20211015 LOE - Modified some codes to adjust to IP moved to settings table
// 20211029 PHR Function barcode - If not numeric, barcode is not EAN13
// 20220110 PHR Function 'sync_shop_vare' Check if item is a stock item
// 20200125 PHR Added func getAvailable, added it to func sync_shop_vare and addef func sync_shop_vare to func lagerreguler
// 20220514 PHR Function findtekst. Added str_replace('\n\n',"\n\n" to newTxt
// 20220603 PHR Function 'sync_shop_vare.' Added search for items that is part of items that is part of items
// 20220628 PHR Function usdate. Corrected type $slutaar was $slutadaar ???
// 20220716 PHR Function tekster. Texts is not fetched from 'inportfiler/egne_tekster.csv' if file exists
// 20220716 PHR Function usdecimal. $tal (number) is now trimmed as it returned 0 if space in either end.

if (!function_exists('nr_cast')) {
	function nr_cast($tekst)
	{
		global $db_type;
			if ($db_type=='mysql' or $db_type=="mysqli") $tmp = "CAST($tekst AS SIGNED)"; #RG_mysqli
			else $tmp = "to_number(text($tekst),text(999999999999999))";
		return $tmp;
	}
}
if (!function_exists('dkdecimal')) {
	function dkdecimal($tal,$decimaler = NULL) {
		if (!isset($decimaler)) $decimaler=2;
		elseif (!$decimaler && $decimaler!='0') $decimaler=2;
		if (is_numeric($tal)) { 
			if ($tal) $tal=afrund($tal,$decimaler); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
			$tal=number_format($tal,$decimaler,",",".");
		}
		return $tal;
	}
}

if (!function_exists('dkdato')) {
	function dkdato($dato) {
		if ($dato) {
			list ($year,$month,$day) = explode('-', $dato,3);
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
	function usdate($date) {
		global $regnaar;
		$day=NULL;$month=NULL;$year=NULL; 
		
		$date=trim($date);
		
		if (!isset($date) || !$date) $date=date("dmY");
		
		$date=str_replace (".","-",$date);
		$date=str_replace (" ","-",$date);
		$date=str_replace ("/","-",$date);
				
		if (strpos($date,"-")) list ($day, $month, $year) = explode('-', $date);
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
			$qtxt="select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'";
			if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
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
		
		
		

		list ($day, $month, $year) = explode('-', $date);

		
		$year=$year*1;
		$month=$month*1;
		$day=$day*1;
		
		if ($year<10){$year='0'.$year;}
		if ($month<10){$month='0'.$month;}
		if ($day<10){$day='0'.$day;}
		 
		if ($day>28) {
			while (!checkdate($month,$day,$year)){
				$day=$day-1;
				if ($day<28) break 1;
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
	function usdecimal($tal,$decimaler = NULL) {
		$tal = trim($tal);
		if (!$decimaler && $decimaler!='0') $decimaler=2;
		if (!$tal){
			$tal="0";
			if ($decimaler) {
				$tal.=',';
				for ($x=1;$x<=$decimaler;$x++) $tal.='0';
			}
		}
		$tal = str_replace(".","",$tal);
		$tal = str_replace(",",".",$tal);
		if (!is_numeric($tal)) $tal = 0;
		$tal=round($tal+0.0001,3);
		if (!$tal){
			$tal="0";
			if ($decimaler) {
				$tal.='.';
				for ($x=1;$x<=$decimaler;$x++) $tal.='0';
			}
		}
		return $tal;
	}
}

if (!function_exists('findtekst')) {
	function findtekst($textId,$languageID)	{
		global $bruger_id;
		global $db,$db_encode;
		global $sqdb;
		global $webservice;
		$id=0;

		#echo "L $languageID B $bruger_id<br>";
		
		$linje=$newTxt=$tekst=$tmp=NULL;
		$textId = trim($textId);
		if (!$languageID || $languageID > 3) {
			$languageID=1;
			$qtxt = "update brugere set language_id = '$languageID' where id = '$bruger_id'";
		}
		if (!is_numeric($textId)) $textId = 0;
		$qtxt="select id,tekst from tekster where tekst_id='$textId' and sprog_id = '$languageID'";
		if ($db != $sqdb && $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$tekst=$r['tekst'];
			$id=$r['id'];
		} elseif (file_exists("../importfiler/egnetekster.csv") ) {
			$fp=fopen("../importfiler/egnetekster.csv","r");
			if ($fp) {
				$tmp=array();
				while (!feof($fp)) {
					if ($linje=trim(fgets($fp))) {
						if (strpos($linje,chr(9))) $tmp=explode(chr(9),$linje);
						if ($textId==$tmp[0]) $newTxt=$tmp[$languageID];# Linjen efter 1. tab. 
						}
				}
				fclose($fp);
			}
		}
		if (!$tekst && $textId) { # 20210304
			$newTxt = NULL;
			if (file_exists("../importfiler/egne_tekster.csv")) {
				$fp = fopen("../importfiler/egne_tekster.csv","r");
				while (!feof($fp) && !$newTxt) {
					if ($linje=trim(fgets($fp))) {
						$a = explode("\t",$linje);
						if ($a[0] == $textId) {
							$newTxt = $a[$languageID];
						}
					}
				}
				fclose ($fp);
			}
			if (!$newTxt) {
				$fp = fopen("../importfiler/tekster.csv","r");
				while (!feof($fp) && !$newTxt) {
					if ($linje=trim(fgets($fp))) {
						$a = explode("\t",$linje);
						if ($a[0] == $textId) {
							$newTxt = $a[$languageID];
						}
					}
				}
			}
			}
		if ($db != $sqdb && $newTxt && $newTxt!='-') {
			if ($db_encode!="UTF8") $newTxt=utf8_decode($newTxt);
			$newTxt=str_replace('\n\n',"\n\n",$newTxt);
			$tmp=db_escape_string($newTxt); #20140505
			if ($id) $qtxt="update tekster set tekst='$tmp' where id=$id";
			else $qtxt="insert into tekster(sprog_id,tekst_id,tekst) values ('$languageID','$textId','$tmp')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$tekst=$newTxt;
		} elseif ($db == $sqdb) $tekst=$newTxt;
		if (!$tekst) $tekst="Tekst nr: $textId";
		elseif ($tekst=="-") $tekst='';
		return ($tekst);
	}//end offindtekst
}  

if (!function_exists('javascript')) {
	function javascript()	{
		
	}
}	
if (!function_exists('afrund')) {
	function afrund($tal,$decimaler)
	{
		# Korrigerer afrundingsfejl i php 
		$decimaler=$decimaler*1;	
		$tmp=0.001;
		for ($x=1;$x<$decimaler ;$x++) {
			$tmp=$tmp/10;
		}
		if ($tal>0) $tal=round($tal+$tmp,$decimaler);
		elseif ($tal<0) $tal=round($tal-$tmp,$decimaler);
		return $tal;
	}
}
if (!function_exists('fjern_nul')) {
	function fjern_nul($tal)
	{
		#fjerner decimalnuller fra tal 
		if (strpos($tal,",")) {
			list($a,$b)=explode(",",$tal);
			$b=$b*1;
			if ($b) $tal=$a.",".$b;
			else $tal=$a;
		}
		return $tal;
	}
}
if (!function_exists('bynavn')) {
	function bynavn($postnr) {
		global $db_encode;
	
		$fp=fopen("../importfiler/postnr.csv","r");
		if ($fp) {
			while ($linje=trim(fgets($fp))) {
				if ($db_encode=="UTF8") $linje=utf8_encode($linje);
				list($a,$b)=explode(chr(9),$linje);
					if ($a==$postnr) {
						$bynavn=str_replace('"','',$b);
						break 1;
					}
				}
			}
			fclose($fp);
		return("$bynavn");
	}
}

if (!function_exists('felt_fra_tekst')) {
	function felt_fra_tekst ($feltmatch, $tekstlinjer) {
		$matchende_linjer = preg_grep("/$feltmatch/", $tekstlinjer);
		foreach ($matchende_linjer as $linje) {
			$retur = str_replace($feltmatch, "", $linje);
		}
		return $retur;
	}
}

if (!function_exists('sidste_dag_i_maaned')) {
	function sidste_dag_i_maaned ($aar, $maaned) {
		$maaned++;
		$retur = date("d", mktime(12, 0, 0, $maaned, 0, $aar));
		return $retur;
	}
}

if (!function_exists('farvenuance')) {
	function farvenuance ($farve, $nuance) { # Notation for nuance: -33+33-33 eller -3+3-3
		global $bgcolor;
		
		if ( $bgcolor=="#" ) $bgcolor="#ffffff"; # 20141010 Hvis ingen bgcolor er angivet, så benyttes hvid som baggrund.
		if ( $farve=="#" ) $farve="#ffffff"; # 20141010 Hvis ingen farve er angivet, så benyttes hvid som baggrund.

		$retur = $bgcolor;

		$farve = preg_replace("/[^0-9A-Fa-f]/", '', $farve);

		if ( strlen($farve) == 3 ) {
			$roed_farve=hexdec(str_repeat(substr($farve, 0, 1), 2));
			$groen_farve=hexdec(str_repeat(substr($farve, 1, 1), 2));
			$blaa_farve=hexdec(str_repeat(substr($farve, 2, 1), 2));
		} else {
			$roed_farve=hexdec(substr($farve, 0, 2));
			$groen_farve=hexdec(substr($farve, 2, 2));
			$blaa_farve=hexdec(substr($farve, 4, 2));
		}

		if ( strlen($nuance) == 6 ) {
			$roed_fortegn=substr($nuance, 0, 1)."1";
			$roed_nuance=$roed_fortegn*hexdec(str_repeat(substr($nuance, 1, 1), 2));
			$groen_fortegn=substr($nuance, 2, 1)."1";
			$groen_nuance=$groen_fortegn*hexdec(str_repeat(substr($nuance, 3, 1), 2));
			$blaa_fortegn=substr($nuance, 4, 1)."1";
			$blaa_nuance=$blaa_fortegn*hexdec(str_repeat(substr($nuance, 5, 1), 2));
		} else {
			$roed_fortegn=substr($nuance, 0, 1)."1";
			$roed_nuance=$roed_fortegn*hexdec(substr($nuance, 1, 2));
			$groen_fortegn=substr($nuance, 3, 1)."1";
			$groen_nuance=$groen_fortegn*hexdec(substr($nuance, 4, 2));
			$blaa_fortegn=substr($nuance, 6, 1)."1";
			$blaa_nuance=$blaa_fortegn*hexdec(substr($nuance, 7, 2));
		}

		$roed_farve=$roed_farve+$roed_nuance;
		if ($roed_farve < 0 ) $roed_farve = 0;
		if ($roed_farve > 255 ) $roed_farve = 255;
		$groen_farve=$groen_farve+$groen_nuance;
		if ($groen_farve < 0 ) $groen_farve = 0;
		if ($groen_farve > 255 ) $groen_farve = 255;
		$blaa_farve=$blaa_farve+$blaa_nuance;
		if ($blaa_farve < 0 ) $blaa_farve = 0;
		if ($blaa_farve > 255 ) $blaa_farve = 255;

		$roed_farve=str_pad(dechex($roed_farve), 2, STR_PAD_LEFT);
		$groen_farve=str_pad(dechex($groen_farve), 2, STR_PAD_LEFT);
		$blaa_farve=str_pad(dechex($blaa_farve), 2, STR_PAD_LEFT);

		$retur = "#".$roed_farve.$groen_farve.$blaa_farve;

		return $retur;
	}
}

if (!function_exists('linjefarve')) {
	#function linjefarve ($linjefarve, $ulige_bg, $lige_bg, $nuance = 0, $stdnuance = 0) {
	function linjefarve ($linjefarve, $ulige_bg, $lige_bg, $stdnuance = 0, $nuance = 0) {

		if ( $linjefarve === $ulige_bg || $linjefarve === farvenuance($ulige_bg, $stdnuance) ) {
			if ( $nuance ) {
				$retur = farvenuance($lige_bg, $nuance);
			} else {
				$retur = $lige_bg;
			}
		} else { 
			if ( $nuance ) {
				$retur = farvenuance($ulige_bg, $nuance);
			} else {
				$retur = $ulige_bg;
			}
		}	
			
		return $retur;
	}
}

if (!function_exists('copy_row')) {
	function copy_row($table,$id) {
		if (!$table || !$id) return('0');
		$r=0;$x=0;
		$fieldstring=NULL;
		$q_string="select * from $table where pris != '0' and m_rabat != '0' and rabat = '0' and id='$id'";
		$q=db_select("$q_string",__FILE__ . " linje " . __LINE__);
		while ($r < db_num_fields($q)) {
			if (db_field_name($q,$r) != 'id') {
				$x++;
				$fieldName[$x] = db_field_name($q,$r); 
				$fieldType[$x] = db_field_type($q,$r);
				($fieldstring)?$fieldstring.=",".$fieldName[$x]:$fieldstring=$fieldName[$x];
			}
			$r++;
		}
		$feltantal=$x;
		$ordre_id=NULL;$posnr=NULL;
		$x=0;
		$q=db_select("$q_string");
		if ($r = db_fetch_array($q)) {
			$fieldvalues=NULL;
			$selectstring=NULL;
			for ($y=1;$y<=$feltantal;$y++){
				$linjerabat=afrund($r['pris']/$r['m_rabat'],2);
				$feltnavn=$fieldName[$y];
				$felt[$y]=$r[$feltnavn];
				if ($fieldType[$y]=='varchar' || $fieldType[$y]=='text') $felt[$y]=addslashes($felt[$y]);
				if (substr($fieldType[$y],0,3)=='int' || $fieldType[$y]=='numeric') $felt[$y]*=1;
				if ($fieldName[$y]=='posnr') {
					$felt[$y]++;
					$posnr=$felt[$y];
				}	
				if ($fieldName[$y]=='ordre_id') $ordre_id=$felt[$y];
				($fieldvalues)?$fieldvalues.=",'".$felt[$y]."'":$fieldvalues="'".$felt[$y]."'";
				($selectstring)?$selectstring.=" and ".$fieldName[$y]."='".$felt[$y]."'":$selectstring=$fieldName[$y]."='".$felt[$y]."'";
			}
		}
		if ($posnr && $ordre_id) db_modify("update $table set posnr=posnr+1 where ordre_id = '$ordre_id' and posnr >= '$posnr'",__FILE__ . " linje " . __LINE__);
		db_modify("insert into ordrelinjer ($fieldstring) values ($fieldvalues)",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select id from $table where $selectstring",__FILE__ . " linje " . __LINE__));
		$ny_id=$r['id'];
		return($ny_id);
	} # endfunc copy_row
}
if (!function_exists('reducer')) {
	function reducer($tal){
		while ((strpos($tal,".") || strpos($tal,",")) && ($tal && (substr($tal,-1,1)=='0' or substr($tal,-1,1)==',' or substr($tal,-1,1)=='.'))) {
			$tal=substr($tal,0,strlen($tal)-1);
		}
		return ($tal);
	}
}
if (!function_exists('transtjek')) {
	function transtjek () {
		global $db;
		$r=db_fetch_array(db_select("select sum(debet) as debet,sum(kredit) as kredit from transaktioner",__FILE__ . " linje " . __LINE__));
#cho __line__." ".$r['debet']."-".$r['kredit']."<br>";
		$diff=abs(afrund($r['debet']-$r['kredit'],2));
		if ($diff >= 1) { 
			$message=$db." | Ubalance i regnskab: kr: $diff";
			$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
			mail('fejl@saldi.dk', 'Ubalance i regnskab:'. $db, $message, $headers);
		}
		return($diff);
	}
}
if (!function_exists('cvrnr_omr')) {
	function cvrnr_omr($landekode) {
		$retur = "";
		if ( ! $landekode ) { 
			$retur = "";
		} else { 
			switch ( $landekode ) {
				case "dk": $retur = "DK"; break 1;
				case "at": $retur = "EU"; break 1;
				case "be": $retur = "EU"; break 1;
				case "cy": $retur = "EU"; break 1;
				case "cz": $retur = "EU"; break 1;
				case "de": $retur = "EU"; break 1;
				case "ee": $retur = "EU"; break 1;
				case "gr": $retur = "EU"; break 1;
				case "es": $retur = "EU"; break 1;
				case "fi": $retur = "EU"; break 1;
				case "fr": $retur = "EU"; break 1;
				#case "gb": $retur = "EU"; break 1;
				case "hu": $retur = "EU"; break 1;
				case "ie": $retur = "EU"; break 1;
				case "it": $retur = "EU"; break 1;
				case "lt": $retur = "EU"; break 1;
				case "lu": $retur = "EU"; break 1;
				case "lv": $retur = "EU"; break 1;
				case "mt": $retur = "EU"; break 1;
				case "nl": $retur = "EU"; break 1;
				case "pl": $retur = "EU"; break 1;
				case "pt": $retur = "EU"; break 1;
				case "ro": $retur = "EU"; break 1;
				case "se": $retur = "EU"; break 1;
				case "si": $retur = "EU"; break 1;
				case "sk": $retur = "EU"; break 1;
				case "gl": $retur = "UD"; break 1;
				default: $retur = "UD"; break 1;
			}
		}
		return $retur;
	}
}
if (!function_exists('cvrnr_land')) {
	function cvrnr_land($cvrnr) {
		$retur = "";
	
		$cvrnr = strtoupper($cvrnr);
		
		if ( ! $cvrnr ) {
			$retur = "";
		} elseif ( is_numeric(substr($cvrnr, 0, 1)) ) {
			$retur = "dk"; 
		} else {
			$start_tegn=strtolower(substr($cvrnr, 0, 3));
			switch ( $start_tegn ) {
				case "ger": $start_tegn="gl"; break 1;
				default : break 1;
			}
			$start_tegn=substr($start_tegn, 0, 2);
			switch ( $start_tegn ) {
				case "el": $retur = "gr"; break 1;
				default: $retur = $start_tegn; 
			}
		}
		return $retur;
	}
}
if (!function_exists('str2low')) {
	function str2low($string) {
	global $db_encode;

		$string=strtolower($string);

		if ($db_encode=='UTF8') {
			$string=str_replace(chr(195).chr(134),chr(195).chr(166),$string);
			$string=str_replace(chr(195).chr(152),chr(195).chr(184),$string);
			$string=str_replace(chr(195).chr(133),chr(195).chr(165),$string);
		} else {
			$string=str_replace(chr(198),chr(230),$string);
			$string=str_replace(chr(216),chr(248),$string);
			$string=str_replace(chr(197),chr(229),$string);
		}
		return ("$string");
	}
}
if (!function_exists('str2up')) {
	function str2up($string) {
		$string=strtoupper($string);
		if ($db_encode=='UTF8') {
			$string=str_replace(chr(195).chr(166),chr(195).chr(134),$string);
			$string=str_replace(chr(195).chr(184),chr(195).chr(152),$string);
			$string=str_replace(chr(195).chr(165),chr(195).chr(133),$string);
		} else {
			$string=str_replace(chr(230),chr(198),$string);
			$string=str_replace(chr(248),chr(216),$string);
			$string=str_replace(chr(229),chr(197),$string);
		}
		$string=str_replace('æ','Æ',$string);
		$string=str_replace('ø','Ø',$string);
		$string=str_replace('å','Å',$string);
		return ("$string");
	}
}

# Tekstvinduer i CSS i stedet for JavaScript Alert - 20141031 - 20141121 - 20141212
# boksflytbar=span giver kun div, boksflytbar=td giver en tabel i en div boksflybar=0 giver ingen mulighed for at flytte. 
if (!function_exists('tekstboks')) {
	function tekstboks($bokstekst, $bokstype='advarsel',  $boksid='boks1', $boksflytbar='span', $boksplacering='mm') {
		$boksindhold="\n<!-- Tekstboks ".$boksid." - start -->\n";

		if ( $boksflytbar==='td' ) {
# Nedenstående linjer er forsøg på at påvirker det originale udseende så lidt som muligt 
# ved brug af den flytbare boks med <table> inden i en <div>. Læser man dokumentationen, 
# så skulle et element med display:none ikke have nogen indflydelse på udseendet, men det 
# har det i både Opera 25.0 og Chrome 38.0.2125.111 m. 
# 
# Claus Agerskov 20141121.
#		$boksindhold.="<div style='display:none'><table style='display:none'><tr><td>Test</td></tr></table></div>\n";
#		$boksindhold.="<table style='display:none'><tr><td>Test</td></tr></table>\n";
			$boksindhold.="<table style='display:none'></table>\n"; # Giver mindst indvirkning på udseendet.
#		$boksindhold.="<tr style='display:none'><td>Test</td></tr>\n";
#		$boksindhold.="<table><tr><td>Test</td></tr></table>\n";
#		$boksindhold.="<div style='display:none'>Test2</div>\n";
		}

		if ( $bokstype==='fejl' ) {
			$bokskant='#ff3333';
			$boksbaggrund='#ffeeee';
		}
		if ( $bokstype==='advarsel' ) {
			$bokskant='#ff9900';
			$boksbaggrund='#ffeecc';
		}
		if ( $bokstype==='info' ) { 
			$bokskant='#0000ff'; # 20150313
			$boksbaggrund='#eeeeff';
		}
		if ( $bokstype==='popop' ) {
			$bokskant='#00ff00'; # 20150313
			$boksbaggrund='#eeffff';
		}
		if ( substr($boksplacering,0,1) == 'm' ) $boksvertikal='30%';
		if ( substr($boksplacering,0,1) == 't' ) $boksvertikal='1%';
		if ( substr($boksplacering,0,1) == 'b' ) $boksvertikal='68%';
		if ( substr($boksplacering,1,1) == 'm' ) $bokshorisontal='30%';
		if ( substr($boksplacering,1,1) == 'v' ) $bokshorisontal='1%';
		if ( substr($boksplacering,1,1) == 'h' ) $bokshorisontal='68%';


		$boksindhold.="\n<div id='".$boksid."' style='position:fixed; margin:10px; border:solid 4px ".$bokskant."; padding:1px; background:".$boksbaggrund.";";
                if ( $bokstype==='info') $boksindhold.=" display:none;";
                $boksindhold.=" top:".$boksvertikal."; left:".$bokshorisontal."; width:320px;'>\n";
		if ( $boksflytbar==='td' ) {
			$boksindhold.="<table><tr>\n";
			$boksindhold.=bokshjoerne($boksid, 'tv', 'td');
	                $boksindhold.="<td width='99%' rowspan='3'>\n";
		}
                $boksindhold.="<p style='font-size: 12pt; background: ".$boksbaggrund."; color: #000000'>\n";
		$boksindhold.=$bokstekst."</p>\n";
		$boksindhold.="<p style='font-size: 12pt; text-align:center'>\n";
                $boksindhold.="<button type='button' style='width:100px; height:30px'";
                $boksindhold.=" onClick=\"document.getElementById('".$boksid."').style.display = 'none';\">Luk</button>\n";
		if ( $boksflytbar==='span' ) {
			$boksindhold.="<br />";
			$boksindhold.=bokshjoerne($boksid, 'tv', 'span');
			$boksindhold.="&nbsp;";
			$boksindhold.=bokshjoerne($boksid, 'th', 'span');
			$boksindhold.="&nbsp;";
			$boksindhold.=bokshjoerne($boksid, 'bv', 'span');
			$boksindhold.="&nbsp;";
			$boksindhold.=bokshjoerne($boksid, 'bh', 'span');
		}
                $boksindhold.="</p>\n";
		if ( $boksflytbar==='td' ) {
	                $boksindhold.="</td>";
			$boksindhold.=bokshjoerne($boksid, 'th', 'td');
	                $boksindhold.="</tr>\n";
			$boksindhold.="<tr><td>&nbsp;</td>";
	                $boksindhold.="<td>&nbsp;</td></tr>\n";
	                $boksindhold.="<tr>";
			$boksindhold.=bokshjoerne($boksid, 'bv', 'td');
	#                $boksindhold.="<td onClick=\"document.getElementById('".$boksid."').style.top = '68%'; document.getElementById('".$boksid."').style.left = '68%'; \">&#9698;</td>\n";
			$boksindhold.=bokshjoerne($boksid, 'bh', 'td');
	                $boksindhold.="</tr></table>\n";
		}
                $boksindhold.="</div>\n";
		$boksindhold.="\n<!-- Tekstboks ".$boksid." - slut -->\n";
		return ("$boksindhold");
	}
}

# Hjørne til tekstbokse som ved klik flytter boksen i hjørnets retning. t=top, b=bund, v=venstre og h=hoejre. De kombineres til tv, th, bv og bh.
# Visning er td=<td>-celle, 0=intet, span=i teksten. 20141121
if (!function_exists('bokshjoerne')) {
	function bokshjoerne($boksid, $hjoerne, $visning='td', $kant_oppe='1%', $kant_nede='68%', $kant_venstre='1%', $kant_hoejre='68%', $kant_midt='40%') {
		if ( ! $visning ) return "";

		if ( $hjoerne == 'tv' ) {
			$vertikal_kant=$kant_oppe;
			$horisontal_kant=$kant_venstre;
			$tv_tegn='&#9700;';
			$popopbesked='Op til venstre';
		} elseif ( $hjoerne == 'th' ) {
			$vertikal_kant=$kant_oppe;
			$horisontal_kant=$kant_hoejre;
			$tv_tegn='&#9701;';
			$popopbesked='Op til højre';
		} elseif ( $hjoerne == 'bv' ) {
			$vertikal_kant=$kant_nede;
			$horisontal_kant=$kant_venstre;
			$tv_tegn='&#9699;';
			$popopbesked='Ned til venstre';
		} elseif ( $hjoerne == 'bh' ) {
			$vertikal_kant=$kant_nede;
			$horisontal_kant=$kant_hoejre;
			$tv_tegn='&#9698;';
			$popopbesked='Ned til højre';
		}

		$bokshjoerne="<".$visning." title='".$popopbesked."'";
		$bokshjoerne.=" onClick=\"document.getElementById('".$boksid."').style.top = '".$vertikal_kant."';";
		$bokshjoerne.=" document.getElementById('".$boksid."').style.left = '".$horisontal_kant."'; \">";
                $bokshjoerne.=$tv_tegn."</".$visning.">\n";
		return $bokshjoerne;
	}
}

if (!function_exists('find_varemomssats')) {
	function find_varemomssats($linje_id) {
		global $regnaar;

		$r=db_fetch_array(db_select("select ordre_id,vare_id,momsfri,omvbet from ordrelinjer where id='$linje_id'",__FILE__ . " linje " . __LINE__));
		$ordre_id=$r['ordre_id']*1;
		$vare_id=$r['vare_id']*1;
		$momsfri=$r['momsfri'];
		$omvbet=$r['omvbet'];

		if (!$vare_id) return("0");	
		
		if ($momsfri) {
			db_modify("update ordrelinjer set momssats='0' where id = '$linje_id'",__FILE__ . " linje " . __LINE__);
			return('0');
			exit;
		}
		$r=db_fetch_array(db_select("select momssats,status from ordrer where id='$ordre_id'",__FILE__ . " linje " . __LINE__));
		$momssats=$r['momssats'];
		$status=$r['status'];

		$r=db_fetch_array(db_select("select gruppe from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__)); 
		$gruppe=$r['gruppe'];
		$r=db_fetch_array(db_select("select box4,box6,box7,box8 from grupper where art = 'VG' and kodenr = '$gruppe'",__FILE__ . " linje " . __LINE__));
		$bogfkto = if_isset($r2['box4']); #20190605 + 1 line
		$momsfri = if_isset($r2['box7']);
		if ($momsfri) {
			db_modify("update ordrelinjer set momssats='0' where id = '$linje_id'",__FILE__ . " linje " . __LINE__);
			return('0');
			exit;
		}
		if ($bogfkto) {
			$r=db_fetch_array(db_select("select moms from kontoplan where kontonr = '$bogfkto' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
			if ($tmp=trim($r2['moms'])) { # f.eks S3
				$tmp=substr($tmp,1); #f.eks 3
				$r2 = db_fetch_array(db_select("select box2 from grupper where art = 'SM' and kodenr = '$tmp'",__FILE__ . " linje " . __LINE__));
				if ($r2['box2']) $varemomssats=$r2['box2']*1;
			}	else $varemomssats=$momssats;
		} else $varemomssats=$momssats;
		db_modify("update ordrelinjer set momssats='$varemomssats' where id = '$linje_id'",__FILE__ . " linje " . __LINE__);
		return("$varemomssats");	
	}
}

#edit 2019.02.21
if (!function_exists('infoboks')) {
	function infoboks($infosymbol, $infotekst, $infotype, $hjoerne, $visning='span', $kant_oppe='1%', $kant_nede='68%', $kant_venstre='1%', $kant_hoejre='68%', $kant_midt='40%', $boksid="") {
		$infoboks="";
		$infoboks.=tekstboks($infotekst, $infotype, $boksid);
		if ( ! $visning ) return "";

		$infoboks.="<".$visning." title='Hjælpetekst til siden'";
		$infoboks.=" onClick=\"document.getElementById('".$boksid."').style.display = 'block'; \">";
                $infoboks.=$infosymbol."</".$visning.">\n";
		return $infoboks;
	}
}
if (!function_exists('find_lagervaerdi')) {
function find_lagervaerdi($kontonr,$slut,$tidspkt) {
	global $regnaar;
	$x=0;
	$lagervaerdi=0;
	$lager=array();
	$gruppe=array();
	$kob=0;
	$salg=0;
	
	if (!$slut) {
		return('stop');	
	}
	$q=db_select("select kodenr,box1,box2,box3,box11,box13 from grupper where art = 'VG' and box8 = 'on' and (box1 = '$kontonr' or box2 = '$kontonr' or box3 = '$kontonr')",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if($r['box1']==$kontonr) $kob=1;
		if($r['box2']==$kontonr) $salg=1;
		if($r['box3']==$kontonr) {
			$salg=1;
			$kob=1;
		}
		if($r['box11']==$kontonr) {
			$salg=1;
			$kob=1;
		}
		if($r['box13']==$kontonr) {
			$salg=1;
			$kob=1;
		}
		$gruppe[$x]=$r['kodenr'];
		$x++;
	}
	$vare_id=array();
/////////////////////////////////////
/*
	$x=0;
	$qtxt="select kostpriser.vare_id,kostpriser.kostpris,varer.gruppe from kostpriser,varer";
	$qtxt.=" where ";
	$qtxt.="kostpriser.transdate<='$slut' and varer.id=kostpriser.vare_id";
	$qtxt.=" order by ";
	$qtxt.="kostpriser.transdate desc";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!in_array($r['vare_id'],$vare_id) && in_array($r['gruppe'],$gruppe)) {
			$vare_id[$x]=$r['vare_id'];
			$kostpris[$x]=$r['kostpris'];
			$antal[$x]=0;
			$x++;
		}
	}
	$qtxt="select id,kostpris,gruppe from varer";
	$qtxt.=" order by ";
	$qtxt.="id";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!in_array($r['id'],$vare_id) && in_array($r['gruppe'],$gruppe)) {
			$vare_id[$x]=$r['id'];
			$kostpris[$x]=$r['kostpris'];
			$antal[$x]=0;
			$x++;
		}
	}
/////////////////////////
*/
	$y=0;
	for ($x=0;$x<count($gruppe);$x++) {
		$q=db_select("select id,kostpris from varer where gruppe = '$gruppe[$x]' order by id",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$vare_id[$y]=$r['id'];
			$kostpris[$y]=$r['kostpris'];
			$antal[$y]=0;
			$y++;
		}
	}
	for ($x=0;$x<count($vare_id);$x++) {
		if ($kob) { # 20170404 Tilføjet and fakturadate > '1970-01-01' da ikke bogførte købsordrer kan give skæve tal.
			if ($tidspkt=='start') $qtxt="select sum(antal) as antal from batch_kob where vare_id = $vare_id[$x] and kobsdate < '$slut'and kobsdate < '$slut'";# or kobsdate < '$slut'
 			else $qtxt="select sum(antal) as antal from batch_kob where vare_id = $vare_id[$x] and kobsdate > '1970-01-01' and kobsdate <= '$slut'";# or kobsdate <= '$slut'	
 			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$antal[$x]+=$r['antal'];
		}
		if ($salg) {
			if ($tidspkt=='start') $qtxt="select sum(antal) as antal from batch_salg where vare_id = $vare_id[$x] and salgsdate < '$slut'";
			else $qtxt="select sum(antal) as antal from batch_salg where vare_id = $vare_id[$x] and salgsdate <= '$slut'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$antal[$x]-=$r['antal'];
		}
		$vaerdi[$x]=$antal[$x]*$kostpris[$x];
		$lagervaerdi+=$vaerdi[$x];
	}
	return($lagervaerdi);
}
}

// Funktion som laver uppercase på første bogstav i streng. Virker som php funktion 'ucfirst', men med æøå
if (!function_exists('mb_ucfirst')) {
	function mb_ucfirst($str, $encoding='UTF-8') {
		$firstChar = mb_substr($str, 0, 1, $encoding);
		$then = mb_substr($str, 1, mb_strlen($str, $encoding)-1, $encoding);
		return mb_strtoupper($firstChar, $encoding) . $then;
	}
}

// Funktion som laver uppercase på første bogstav i alle ord i strengen. Virker som php funktion 'ucwords', men med æøå
if (!function_exists('mb_ucwords')) {
	function mb_ucwords($str) {
		return mb_convert_case($str, MB_CASE_TITLE, "UTF-8");
	}
}
if (!function_exists('ftptest')) {
	function ftptest($server,$bruger,$kode) {
		global $db;
		global $exec_path;
		$fp=fopen("../temp/$db/test.txt","w");
		fwrite ($fp,"Hej der\n");
		fclose($fp);
		$fp=fopen("../temp/$db/ftpscript1","w");
		fwrite ($fp,"set confirm-close no\nput test.txt\nbye\n");
		fclose($fp);
		$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$bruger.":".$kode."@".$server." < ftpscript1 > ftp1.log ";
		system ($kommando);
		unlink ("../temp/$db/test.txt");
		$fp=fopen("../temp/$db/ftpscript2","w");
		fwrite ($fp,"set confirm-close no\nget test.txt\nbye\n");
		fclose($fp);
		$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$bruger.":".$kode."@".$server." < ftpscript2 > ftp2.log ";
		system ($kommando);
		(file_exists("../temp/$db/test.txt"))?$txt="FTP tjek OK":$txt="Fejl i FTP oplysninger";
		print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		unlink ("../temp/$db/test.txt");
		unlink ("../temp/$db/ftpscript1");
		unlink ("../temp/$db/ftpscript2");
	}
}
if (!function_exists('valutaopslag')) {
function valutaopslag($amount, $valuta, $transdate) {
	global $connection;
	global $fejltext;
	
	$r = db_fetch_array(db_select("select * from valuta where gruppe = '$valuta' and valdate <= '$transdate' order by valdate desc",__FILE__ . " linje " . __LINE__));
	if ($r['kurs']) {
		$kurs=$r['kurs'];
		$amount=afrund($amount*$kurs/100,2); # decimal rettet fra 3 til 2 20090617 grundet fejl i saldi_58_20090617-2224
	} else {
		$r = db_fetch_array(db_select("select box1 from grupper where art = 'VK' and kodenr = '$valuta'",__FILE__ . " linje " . __LINE__));
		$tmp=dkdato($transdate);
		$fejltext="---";
		print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs for $r[box1] den $tmp')\">";	
	}
	$r = db_fetch_array(db_select("select box3 from grupper where art = 'VK' and kodenr = '$valuta'",__FILE__ . " linje " . __LINE__));
	$diffkonto=$r['box3'];
	
	return array($amount,$diffkonto,$kurs); # 3'die parameter tilfojet 2009.02.10
}}

if (!function_exists('regnstartslut')) {
function regnstartslut($regnaar) {
	$r=db_fetch_array(db_select("select * from grupper where art = 'RA' and kodenr = '$regnaar'",__FILE__ . " linje " . __LINE__));
	$startmd=$r['box1'];
	$startaar=$r['box2'];
	$slutmd=$r['box3'];
	$slutaar=$r['box4'];
	$regnstart=$startaar.'-'.$startmd.'-01';
	$regnslut=$slutaar.'-'.$slutmd.'-31';
	return($regnstart.chr(9).$regnslut);
}}

if (!function_exists('lagerreguler')) {
function lagerreguler($vare_id,$ny_beholdning,$kostpris,$lager,$transdate,$variant_id) {
  global $db;

#if ($db == 'pos_85') echo "lagerreguler($vare_id,$ny_beholdning,$kostpris,$lager,$transdate,$variant_id)<br>";

 	$qtxt="select box4 from grupper where art='API'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$api_fil=trim($r['box4']);

	if ($lager<1) $lager=1;
	$ny_beholdning = (float)$ny_beholdning;
	$vare_id       = (int)$vare_id;
	$variant_id    = (int)$variant_id;
	$x=0;
	$qtxt="update lagerstatus set variant_id='0' where vare_id='$vare_id' and variant_id is NULL";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="update lagerstatus set lager='1' where  vare_id='$vare_id' and lager = '0' or lager is NULL";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="select id,beholdning from lagerstatus where vare_id='$vare_id' and lager='$lager' and variant_id='$variant_id' order by id limit 1";
#if ($db == 'pos_85') echo "$qtxt<br>";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['id']) {
		$qtxt = "delete from lagerstatus where vare_id='$vare_id' and lager='$lager' and variant_id='$variant_id' and id !='$r[id]'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$diff=$ny_beholdning-$r['beholdning'];
		if ($diff){
			$qtxt="update lagerstatus set beholdning='$ny_beholdning' where id='$r[id]'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="update variant_varer set variant_beholdning='$ny_beholdning' where id='$variant_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	} else {
		$qtxt="insert into lagerstatus(vare_id,variant_id,beholdning,lager) values ('$vare_id','$variant_id','$ny_beholdning','$lager')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$diff="$ny_beholdning";
	}
	if ($diff>0) {
		$qtxt="insert into batch_kob(vare_id,variant_id,linje_id,kobsdate,fakturadate,ordre_id,antal,pris,rest,lager)"; 
		$qtxt.="values"; 
		$qtxt.="('$vare_id','$variant_id','0','$transdate','$transdate','0','$diff','$kostpris','$diff','$lager')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	} else {
		$diff*=-1;
		$qtxt="select id,rest,pris from batch_kob where vare_id='$vare_id' and lager='$lager' and variant_id='$variant_id' and rest>'0' order by kobsdate,id";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while($diff && $r=db_fetch_array($q)){
			if ($diff-$r['rest']>=0){
				$qtxt="update batch_kob set rest='0' where id='$r[id]'";
				db_modify("update batch_kob set rest='0' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
				$qtxt="insert into batch_salg(batch_kob_id,vare_id,variant_id,linje_id,salgsdate,fakturadate,ordre_id,antal,pris,lev_nr,lager)"; 
				$qtxt.="values"; 
				$qtxt.="('$r[id]','$vare_id','$variant_id','0','$transdate','$transdate','0','$r[rest]','$r[pris]','1','$lager')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$diff-=$r['rest'];	
			} else {
				$qtxt="update batch_kob set rest=rest+$diff where id='$r[id]'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt="insert into batch_salg(batch_kob_id,vare_id,variant_id,linje_id,salgsdate,fakturadate,ordre_id,antal,pris,lev_nr,lager)"; 
				$qtxt.="values"; 
				$qtxt.="('$r[id]','$vare_id','$variant_id','0','$transdate','$transdate','0','$diff','$r[pris]','1','$lager')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$diff=0;
			}
		}
		if ($diff) {
			$qtxt="insert into batch_salg(batch_kob_id,vare_id,variant_id,linje_id,salgsdate,fakturadate,ordre_id,antal,pris,lev_nr,lager)"; 
			$qtxt.="values";
			$qtxt.="('0','$vare_id','$variant_id','0','$transdate','$transdate','0','$diff','$kostpris','1',$lager)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
#	sync_shop_vare($vare_id,$variant_id,$lager);
	$qtxt="select sum(beholdning) as beholdning from lagerstatus where vare_id='$vare_id'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$beholdning=$r['beholdning']*1;
	$qtxt="update varer set beholdning='$beholdning' where id='$vare_id'";
#if ($db == 'pos_85') echo "$qtxt<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#
}}

if (!function_exists('saldikrypt')) {
function saldikrypt($id,$pw) {
	$tmp='';
	for($i=0;$i<strlen($pw);$i++)$tmp.=ord(substr($pw,$i,1))*3;
	$pw=md5($tmp);
	for ($i=0;$i<$id*100;$i++) {
		$y=round(substr($i,-2)/4,0);
		if (is_numeric(substr($pw,$y,1))) $pw=md5(strrev($pw));
		else $pw=md5($pw);
	}
	return($pw);
}}
if (!function_exists('find_beholdning')) {
function find_beholdning($vare_id, $udskriv) {
/*
	$x=0;
	$ordre_id=array();
	$q = db_select("select id from ordrer where status < 1 and art = 'DO'",__FILE__ . " linje " . __LINE__);
	while ($r =db_fetch_array($q)){
		$ordre_id[$x]=$r['id'];
		$x++;
	}
*/	
	$x=0;
	$y='';
	$beholdning[1]=0;  // in salesoffer 
	$beholdning[2]=0;  // sales offer#
	$beholdning[3]=0;  // in sales order
	$beholdning[4]=0;  // sales ordre#
	$beholdning[5]=''; // in buy proposal  
	$beholdning[6]=''; // buy proposal#
	$beholdning[7]=''; // in buy order
	$beholdning[8]=''; // buy order#
	
	$qtxt = "select ordrelinjer.id as linje_id, ordrelinjer.ordre_id as ordre_id, ordrelinjer.antal as antal,";
	$qtxt.= "ordrelinjer.leveret as leveret,ordrer.ordrenr as ordrenr,ordrer.status as status,ordrer.art as art ";
	$qtxt.= "from ordrelinjer,ordrer where ordrelinjer.vare_id = $vare_id and ordrer.id=ordrelinjer.ordre_id";
	$query2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row2 =db_fetch_array($query2)) {
		if ($row2['status']<1 && $row2['art']=='DO') {
			$beholdning[1]+=$row2['antal'];
			($beholdning[5])?$beholdning[5].=",".$row2['ordrenr']:$beholdning[5].=$row2['ordrenr'];
		}
		elseif ($row2['status']<3 && $row2['art']=='DO') {
			$beholdning[2]+=$row2['antal'];
			($beholdning[6])?$beholdning[6].=",".$row2['ordrenr']:$beholdning[6].=$row2['ordrenr'];
			$beholdning[2]-=$row2['leveret'];
#			$query3 = db_select("select antal from batch_salg where linje_id = '$row2[linje_id]'",__FILE__ . " linje " . __LINE__);
#			while ($row3=db_fetch_array($query3)) {$beholdning[2]-=$row3['antal'];}
		}	elseif ($row2['status']<1 && $row2['art']=='KO') {
			$beholdning[3]+=$row2['antal'];
			($beholdning[7])?$beholdning[7].=",".$row2['ordrenr']:$beholdning[7].=$row2['ordrenr'];
		}	elseif ($row2['status']<3 && $row2['art']=='KO') {
			$beholdning[4]+=$row2['antal'];
			($beholdning[8])?$beholdning[8].=",".$row2['ordrenr']:$beholdning[8].=$row2['ordrenr'];
			$beholdning[4]-=$row2['leveret'];
#			$query3 = db_select("select antal from batch_kob where linje_id = '$row2[linje_id]'",__FILE__ . " linje " . __LINE__);
#			while ($row3=db_fetch_array($query3)) {$beholdning[4]-=$row3['antal'];}
		}
	}
	return $beholdning;
}} #endfunc find_beholdning()

if (!function_exists('hent_shop_ordrer')) {
function hent_shop_ordrer($shop_ordre_id,$from_date) {
	global $db;
	$qtxt = "select box4 from grupper where art='API'";
	($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$api_fil=trim($r['box4']):$api_fil=0;
	if ($api_fil) {
		if (file_exists("../temp/$db/shoptidspkt.txt")) {
			$fp=fopen("../temp/$db/shoptidspkt.txt","r");
			$tidspkt=fgets($fp);
		} else $tidspkt = 0;
		fclose ($fp);
		if ($tidspkt < date("U")-300 || $shop_ordre_id) {
			$fp=fopen("../temp/$db/shoptidspkt.txt","w");
			fwrite($fp,date("U"));
			fclose ($fp);
			$header="User-Agent: Mozilla/5.0 Gecko/20100101 Firefox/23.0";
#cho 	"/usr/bin/wget --spider --no-check-certificate --header='$header' $api_fil?put_new_orders=1 \n<br>";
			$api_txt="$api_fil?put_new_orders=1";
			if ($shop_ordre_id) $api_txt.="&ordre_id=$shop_ordre_id";
			if ($from_date) $api_txt.="&from_date=$from_date";
			exec ("nohup /usr/bin/wget  -O - -q --no-check-certificate --header='$header' '$api_txt' > /dev/null 2>&1 &\n");
		}	
	}
}} #endfunc hent_shop_ordrer()
if (!function_exists('alert')) {
function alert($msg) {
    echo "<script type='text/javascript'>alert('$msg');</script>";
}}
if (!function_exists('sync_shop_vare')) {
function sync_shop_vare($vare_id,$variant_id,$lager) {
	global $db;
	$costPrice = 0;
	$log=fopen("../temp/$db/rest_api.log","a");
	$qtxt="select box4 from grupper where art='API'";
	fwrite($log,__file__." ".__line__." $qtxt\n");
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$api_fil=trim($r['box4']); #20211013 $api_fil was omitted loe
	if (!$api_fil) {
		fwrite($log,__file__." ".__line__." no api\n");
		fclose($log);
		return('no api');
	}
	$qtxt="select delvare,gruppe from varer where id='$vare_id'"; #20220110
	fwrite($log,__file__." ".__line__." $qtxt\n");
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$itemGroup=$r['gruppe'];
	$partOfItem=$r['delvare'];
#if ($partOfItem) echo __line__." Id $vare_id is part of another item<br>";
	$qtxt="select box8 from grupper where kodenr='$itemGroup' and art = 'VG'";
	fwrite($log,__file__." ".__line__." $qtxt\n");
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if (!$r['box8']) {
		fwrite($log,__file__." ".__line__." no stock\n");
		fclose($log);
		return('no stock');
	}
	$header="User-Agent: Mozilla/5.0 Gecko/20100101 Firefox/23.0";
	if ($variant_id) {
		$qtxt="select shop_variant from shop_varer where saldi_variant='$variant_id'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$shop_id=$r['shop_variant'];
		$qtxt="select beholdning from lagerstatus where variant_id='$variant_id'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$variant_beholdning=$r['beholdning'];#-$antal;
		if (!$shop_id) {
			$qtxt="select variant_stregkode from variant_varer where id='$variant_id'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$shop_id=str_replace("EAN","",$r['variant_stregkode']);
		}
		if ($vare_id) {
			$qtxt="select kostpris from varer where id='$vare_id'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$costPrice=$r['kostpris'];
		}
		$txt="$api_fil?update_stock=$shop_id&stock=$variant_beholdning";
		$txt.="&stockno=$lager&stockvalue=$r[lagerbeh]&file=". __FILE__ ."&line=". __LINE__ ."'";
		fwrite($log,__file__." ".__line__." $txt\n");
		shell_exec ("nohup curl '$txt > ../temp/$db/curl.txt &\n");

	} else {
		$qtxt="select varer.varenr, varer.kostpris, lagerstatus.beholdning as stock from lagerstatus,varer ";
		$qtxt.="where lagerstatus.vare_id='$vare_id' and lagerstatus.lager='$lager' and varer.id='$vare_id'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$stock     = $r['stock'];
			$itemNo    = $r['varenr'];
			$costPrice = $r['kostpris'];
		} #$stock=$itemNo=NULL; #20210225
		$qtxt="select sum(beholdning) as total_stock from lagerstatus where vare_id='$vare_id'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$totalStock=$r['total_stock'];
		}
		$qtxt="select shop_id from shop_varer where saldi_id='$vare_id'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $shop_id=$r['shop_id'];
		elseif (is_integer($itemNo)) $shop_id=$r['itemNo'];
		else $shop_id=0;
		if (($shop_id || $itemNo) && is_numeric($stock)) {
			$rand = rand();
			$txt = "$api_fil?update_stock=$shop_id&stock=$stock&totalStock=$totalStock";
			$txt.= "&stockno=$lager&costPrice=$costPrice&itemNo=". urlencode("$itemNo"). "&rand=$rand";
			fwrite($log,__file__." ".__line__." nohup curl '$txt' &\n");
			shell_exec("nohup curl '$txt' > ../temp/$db/curl.txt &\n");
			if ($partOfItem) {
				$x=0;
				$qtxt = "select * from styklister where vare_id = '$vare_id'";
				$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$partOf[$x]  = $r['indgaar_i'];
					$x++;
				}
				$y=$x;
				for ($x=0;$x<count($partOf);$x++) { #20220603
					$qtxt = "select * from styklister where vare_id = '$partOf[$x]'";
					$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while ($r = db_fetch_array($q)) {
						$PartOf[$y]  = $r['indgaar_i'];
						$y++;
					}
				}
				for ($x=0;$x<count($partOf);$x++) {
					$shop_id = 0;
					$qtxt = "select varenr,kostpris from varer where id = $partOf[$x]";
					fwrite($log,__file__." ".__line__." $qtxt\n");
					if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
						$itemNo    = $r['varenr'];
						$costPrice = $r['kostpris'];
					}
					fwrite($log,__file__." ".__line__." ItemNo = $itemNo Cost price = $costPrice\n");
					$qtxt = "select shop_id from shop_varer where saldi_id = $partOf[$x]";
					fwrite($log,__file__." ".__line__." $qtxt\n");
					if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $shop_id=$r['shop_id'];
					list($totalStock,$stock) = explode('|', getAvailable($partOf[$x],$lager));
					$rand = rand();
					$txt = "$api_fil?update_stock=$shop_id&stock=$stock&totalStock=$totalStock&stockno=$lager";
					$txt.= "&itemNo=". urlencode("$itemNo") ."&file=". __FILE__ ."&line=". __LINE__ ."&rand=$rand";
					fwrite($log,__file__." ".__line__." nohup curl '$txt' &\n");
					shell_exec("nohup curl '$txt' > ../temp/$db/curl.txt &\n");
				}
			}
		}
	}
	return ('OK');
}} #endfunc sync_shop_vare()
if (!function_exists('getAvailable')) {
function getAvailable($itemId,$stockNo) {
	$x=0;
	$available = $totalAvailable = 100000;
	$qtxt = "select * from styklister where indgaar_i = '$itemId'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$IemPart[$x] = $r['vare_id'];
		$IemQty[$x]  = $r['antal'];
		$qtxt = "select gruppe from varer where id = $IemPart[$x]";
		$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$gruppe = (int)$r2['gruppe'];
		$qtxt = "select beholdning from lagerstatus where vare_id = $IemPart[$x] and lager = '$stockNo'";
		$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$stock = $r2['beholdning'];
		$qtxt = "select sum(beholdning) as totalstock from lagerstatus where vare_id = $IemPart[$x]";
		$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$totalStock = $r2['totalstock'];

		if ($stock == 0 || $total_stock == 0) {
			$qtxt = "select box8 from grupper where kodenr = '$gruppe' and art = 'VG'";
			$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$box8 = $r2['box8']; 
		}
		if (!$stock && $box8) $available = 0; 
		elseif ($stock && $stock / $IemQty[$x] < $available) $available = $stock / $IemQty[$x]; 
		if (!$totalStock && $box8) $totalAvailable = 0; 
		elseif ($totalStock && $totalStock / $IemQty[$x] < $totalAvailable) $totalAvailable = $totalStock / $IemQty[$x]; 
		$x++;
	}
	if ($available < 0) $available= 0;
	$available = floor($available); 
	if ($totalAvailable < 0) $otalAvailable= 0;
	$totalAvailable = floor($totalAvailable); 
	return ($totalAvailable."|".$available);
}}

//                   --------------------------------- alert ----------------------------------
if (!function_exists('alert')) {
function alert($msg) {
    echo "<script type='text/javascript'>alert('$msg');</script>";
}}

//                   ----------------------------- create_debtor ------------------------------
if (!function_exists('create_debtor')) {
function create_debtor($kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$email,$tlf,$cvrnr,$grp,$ean,$betalingsbet,$betalingsdage,$kontakt) {
	if (!$kontonr) $kontonr=get_next_number('adresser','D');
	else {
		$qtxt="select id from adresser where kontonr='$kontonr' and art='D'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			alert("Kontonr $kontonr er ikke ledigt!");
			return(NULL);
			exit;
		}
	} 
	if ($postnr && !$bynavn) $bynavn=bynavn($postnr); #20190423
	
	$qtxt = "insert into adresser "; 
	$qtxt.= "(kontonr,firmanavn,addr1,addr2,postnr,bynavn,email,tlf,cvrnr,ean,gruppe,kontakt,art,lukket,betalingsbet,betalingsdage)";
	$qtxt.=" values ";
	$qtxt.= "('".db_escape_string($kontonr)."','".db_escape_string($firmanavn)."','".db_escape_string($addr1)."',";
	$qtxt.= "'".db_escape_string($addr2)."','".db_escape_string($postnr)."','".db_escape_string($bynavn)."',";
	$qtxt.="'".db_escape_string($email)."','".db_escape_string($tlf)."','".db_escape_string($cvrnr)."','".db_escape_string($ean)."',";
	$qtxt.="'".db_escape_string($grp)."','".db_escape_string($kontakt)."','D','','$betalingsbet','$betalingsdage')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="select id from adresser where kontonr='".db_escape_string($kontonr)."' and art='D'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	return ($r['id']);
	
}}

//                   ----------------------------- get_next_number ------------------------------
if (!function_exists('get_next_number')) {
function get_next_number($table,$art) {
	$x=0;
	$qtxt="select kontonr from $table where art='$art'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ktonr[$x]=$r['kontonr'];
		$x++;
	}
	$kontonr=1000;
	while (in_array($kontonr,$ktonr)) {
		$kontonr++;
		
	}
	return($kontonr);
}}

if (!function_exists('barcode')) {
function barcode($stregkode) {
	global $bruger_id,$db,$exec_path;
		$ean13 = NULL;
	#(strpos($stregkode,';'))?$stregkoder=explode(";",$stregkode):$stregkoder[0]=$stregkode;
	$stregkoder=explode(";",$stregkode);
	$png=NULL;
	if (file_exists($exec_path."/barcode")||(file_exists($exec_path."/tbarcode")) && file_exists($exec_path."/convert")) { #20140603
		$dan_kode=1;
		if (strpos($stregkoder[0],'æ')) $dan_kode=0; 
		if (strpos($stregkoder[0],'Æ')) $dan_kode=0; 
		if (strpos($stregkoder[0],'ø')) $dan_kode=0; 
		if (strpos($stregkoder[0],'Ø')) $dan_kode=0; 
		if (strpos($stregkoder[0],'å')) $dan_kode=0; 
		if (strpos($stregkoder[0],'Å')) $dan_kode=0;
		if (strpos($stregkoder[0],' ')) $dan_kode=0;
		if ($dan_kode)	{
			$eps="../temp/$db/$stregkoder[0].eps";
			$png="../temp/$db/$stregkoder[0].png";
			if (is_numeric($stregkoder[0]) && strlen($stregkoder[0])==13) { #20211029 is_numeric($stregkoder[0]
			$a=substr($stregkoder[0],11,1)+substr($stregkoder[0],9,1)+substr($stregkoder[0],7,1)+substr($stregkoder[0],5,1)+substr($stregkoder[0],3,1)+substr($stregkoder[0],1,1);
			$a*=3;
			$a+=substr($stregkoder[0],10,1)+substr($stregkoder[0],8,1)+substr($stregkoder[0],6,1)+substr($stregkoder[0],4,1)+substr($stregkoder[0],2,1)+substr($stregkoder[0],0,1);
			$b=0;
			while(!is_int(($a+$b)/10)) $b++;
			($b==substr($stregkoder[0],12,1))?$ean13=1:$ean13=0; 
		}
			if (file_exists("../temp/$db/".abs($bruger_id)."_*.eps")) unlink("../temp/$db/".abs($bruger_id)."_*.eps");
			if (file_exists($exec_path."/barcode")) {
				$barcodgen=$exec_path."/barcode";
				($ean13)?$ean='ean13':$ean='128';
				$ms=date("is");
				$barcodtxt=$barcodgen." -n -E -e $ean -g 200x40 -b $stregkoder[0] -o $eps\n".$exec_path."/convert $eps $png\n".$exec_path."/rm $eps\n";
			} else {
				$barcodgen=$exec_path."/tbarcode";
				($ean13)?$ean='13':$ean='20';
				$barcodtxt=$barcodgen." --format=ps --barcode=$ean --text=hide --width=80 --height=15 --data=$stregkoder[0] > $eps\n".$exec_path."/convert $eps $png\n".$exec_path."/rm $eps\n";
			}
			system ($barcodtxt);
#			print "<!--"; #20140909
#			print "-->";
		} else $png=NULL;	
	}
	return($png);
}}

// Acts like the ordinary trim but also take the character Non-Breaking Space U+00A0
// Inspiration to enhance the function:
//   https://en.wikipedia.org/wiki/Whitespace_character#Unicode
if (!function_exists('trim_utf8')) {
	function trim_utf8($textstring,$htmlentity='&nbsp;',$showiteration=FALSE) {

			if ( $showiteration ) echo "&gt;<span style='background:#000;color:#9ff'>".$textstring."</span>&lt;<br />\n";
			$trimstring=$textstring;

			$nbsphtml=html_entity_decode($htmlentity, ENT_HTML401, 'UTF-8');

			if ( substr($trimstring,0,strlen($nbsphtml)) === $nbsphtml ) $trimstring = substr($trimstring,strlen($nbsphtml));
			$chrlen=strlen($nbsphtml);
			$restlen=strlen($trimstring)-$chrlen;
			$chrlen=(-1)*$chrlen;
			if ( substr($trimstring,$chrlen) === $nbsphtml ) $trimstring = substr($trimstring,0,$restlen);

			$trimstring = trim($trimstring);

			if ($trimstring===$textstring) {
					return $textstring;
			} else {
					return trim_utf8($trimstring,$htmlentity,$showiteration);
			}
	}
}

if(!function_exists('activeLanguage')){ //20210225
	#function activeLanguage($LangID=(true || false)){
	#function activeLanguage($LangID=true || false){
	function activeLanguage($LangID=true){ #20210915
		global $bruger_id;
		global $brugernavn;
		$active_language_id=$activeLanguage=$user_id=null;
        $user_id=abs($bruger_id); #20210517

		if($bruger_id < 0){
			$qtxt = "select sprog_id from brugere WHERE id = '$user_id'"; #20210505
		}else{
			$qtxt = "select sprog_id from brugere WHERE id = '$bruger_id'";	
		}
		###### 20210427
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)) ){
		   if ($r['sprog_id'] !== null){
			   $active_language_id=$r['sprog_id'];
			   $q = db_fetch_array(db_select("select id, var_name from settings where id = '$active_language_id'",__FILE__ . " linje " . __LINE__)); #20210506
			  #$active_language_id= $q['id'];
			   $activeLanguage =$q['var_name'];
		    
		    }elseif(	$q = db_fetch_array(db_select("select id, var_name from settings where var_value = 'Danish'",__FILE__ . " linje " . __LINE__)) )
				{
					$active_language_id= $q['id'];
					$activeLanguage =$q['var_name'];
				}else{
					db_modify("insert into settings (var_name,  var_grp , var_value , var_description, user_id)values('Danish','localization','Danish','Danish language', 0)",__FILE__ . " linje " . __LINE__);
					$q = db_fetch_array(db_select("select id, var_name from settings where var_value = 'Danish'",__FILE__ . " linje " . __LINE__));
					$active_language_id= $q['id'];
					$activeLanguage =$q['var_name'];


					if($bruger_id<0){
						db_modify("update brugere set sprog_id = '$active_language_id' where id ='$user_id'",__FILE__ . " linje " . __LINE__); #20210505
		            }else{
			            db_modify("update brugere set sprog_id = '$active_language_id' where id ='$bruger_id'",__FILE__ . " linje " . __LINE__);
		            }
					
				}



			} elseif(	$q = db_fetch_array(db_select("select id, var_name from settings where var_value = 'Danish'",__FILE__ . " linje " . __LINE__)) ){//20210501
		 
			 $active_language_id= $q['id'];
			 $activeLanguage =$q['var_name'];
		    }else{
			 db_modify("insert into settings (var_name,  var_grp , var_value , var_description, user_id)values('Danish','localization','Danish','Danish language', 0)",__FILE__ . " linje " . __LINE__);
			 $q = db_fetch_array(db_select("select id, var_name from settings where var_value = 'Danish'",__FILE__ . " linje " . __LINE__));
			 $active_language_id= $q['id'];
			 $activeLanguage =$q['var_name'];
		 
			if($bruger_id<0){
				db_modify("update brugere set sprog_id = '$active_language_id' where id ='$user_id'",__FILE__ . " linje " . __LINE__); #20210505
			}else{
				db_modify("update brugere set sprog_id = '$active_language_id' where id ='$bruger_id'",__FILE__ . " linje " . __LINE__);
			}
		}
 

		if($LangID==true){
		return $activeLanguage;
		}else{
			return $active_language_id;
		}
		#################
	}
}

if(!function_exists('csv_to_array')){ // 20210302
	function csv_to_array($filename='', $delimiter="\t"){
		if(!file_exists($filename) || !is_readable($filename))
			return FALSE;

		$header = NULL;
		$data = array();
		if (($handle = fopen($filename, 'r')) !== FALSE)
		{
			while (($row = fgetcsv($handle, 2500, $delimiter)) !== FALSE)
			{
				if(!$header)
					$header = $row;
				else
					#$data[] = array_combine($header, $row);
					// $h = array_pad($row, count($header),'');
					// $data[] = array_combine($header, $h);
					if(count($row)==count($header)){ #20211009 loe corrected array_combine bug
						$data[] = array_combine($header, $row);
					}
			}
			fclose($handle);
		}
		return $data;
	}
}



if(!function_exists('engdan')){ // 20210409

    function engdan(array $g,$newLanguage){


		if (array_key_exists("$newLanguage", $g[0])){
 
			$columnsNames = array_keys($g[0]);

            $tekst_id = $columnsNames[0];

            for ($i=0; $i<= count($columnsNames)-1; $i++){

                if ($columnsNames[$i]=="$newLanguage") $languageColumn=$columnsNames[$i];
            }
			
			foreach($g as $data){
			// {
			// #$temp[$data[1]] = $data["$languageColumn"];
			// #if($data["$languageColumn"]) $lang3 = $data["$languageColumn"];
			// $temp[$data[1]] = $data["$languageColumn"] ?? "-"; #
			// #$temp[$data[1]] = $lang3; #
			// }
			   if($data[1]){
				$temp[$data[1]] = $data["$languageColumn"];
				#$temp[$data[1]] = $data["$languageColumn"] ?? "-"; #
				#$temp[$data[1]] = $lang3; #
				}
		    }	
			$arr1 = array($tekst_id => $languageColumn);
			$newarr = $arr1+$temp;
			return $newarr;
		}
    }	
}

if (!function_exists('get_ip')) { #20210830

	function get_ip(){
		$ip_address = if_isset($_SERVER['REMOTE_ADDR']);     
		$proxy_ip = if_isset($_SERVER['HTTP_X_FORWARDED_FOR']);
		$client_ip = if_isset($_SERVER['HTTP_CLIENT_IP']); 
		  
		if($client_ip){
			  $ip = $client_ip;
		  }elseif($proxy_ip){
			 $ip= $proxy_ip ;
		  }else{
			  $ip = $ip_address;
		  }
		  return $ip;
	
	}
}
if (!function_exists('restrict_user_ip')) { #20210831 + 20210909+20211015
	function restrict_user_ip($user_ip, $ret_id){
		#global $ret_id ;
		$ret_id=$ret_id*1;
		$restricted_user_ip=null;
		#$d = new DateTime('NOW');
		#$created_ip_date =    $d->format('c'); 
#########
        $restrict = "$user_ip".chr(9)."$ret_id";
		$qtxt = "select var_value from settings where var_name = 'RestrictedUserIp' and user_id = '0'";
		if($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
		$restricted_user_ip = $r ['var_value'];}
		if (!$restricted_user_ip){
			db_modify("insert into settings (var_name,var_grp, var_value, user_id) values ('RestrictedUserIp','globals','$restrict','0' )",__FILE__ . " linje " . __LINE__);
		} 
##########

		// $query = db_select("select * from restricted_users where ip_values = '$user_ip' ",__FILE__ . " linje " . __LINE__);
		// if(!$row = db_fetch_array($query)){
		// db_modify("insert into restricted_users (user_id,ip_values,ip_restricted_date) values ('$ret_id', '$user_ip','$created_ip_date')",__FILE__ . " linje " . __LINE__);
	    // }
	}
}	
if (!function_exists('restore_user_ip')) { #20210831 + 20210909
	function restore_user_ip($user_ip, $ret_id){	
		$query = db_select("select ip_values from restricted_users where ip_values = '$user_ip' and user_id = '$ret_id' ",__FILE__ . " linje " . __LINE__);
		if($row = db_fetch_array($query)){
		db_modify("delete from restricted_users where user_id = '$ret_id' and ip_values = '$user_ip'",__FILE__ . " linje " . __LINE__);
	    }
	}
}	

if (!function_exists('authenticate_user_ip')) { #20210901 
	function authenticate_user_ip($user_ip){
		global $bruger_id;
		
		$query = db_select("select ip_values from restricted_users where ip_values = '$user_ip' and user_id = '$bruger_id' ",__FILE__ . " linje " . __LINE__);
		if($row = db_fetch_array($query)){
		return true;
	    }else{
		return false;
		}    
	}
}

if(!function_exists('input_ip')){ #20210908

	function input_ip($ip, $ret_id){
	    #global $ret_id;	
		$d = new DateTime('NOW');
		$created_ip_date =    $d->format('c'); // ISO8601 formated datetime
		$query = db_select("select * from users_ip where ip_values = '$ip' ",__FILE__ . " linje " . __LINE__);
		if(!$row = db_fetch_array($query)){
		$qtxt = "insert into users_ip (user_id,ip_values,ip_logged_date) values ('$ret_id', '$ip','$created_ip_date')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	   } 
	}
}
if(!function_exists('str_starts_with')) {
	function str_starts_with ($haystack,$needle) {
		$nl = strlen($needle);
		if (strpos($haystack,0,$nl) == $needle) return 1;
		else return 0;
	}
}



######################################################################################################################################
?>
