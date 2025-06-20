<?php
// -----------includes/std_func.php----------------- lap 3.4.7 -- 2014-12-23 --
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

// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------
//
// 2013.02.10 Break ændret til break 1
// Tastefejl rettet.
// 2014.05.01 Funktion findtekst - teksten ignoreres nu hvis tekst="-"
// 2014.05.05 Funktion findtekst insdat db_escape_string. (PHR - Danosoft) Søg 20140505
// 2014.10.10 CA  Funktionen farvenuance antager hvid baggrund, hvis ingen angivet. Søg 20141010
// 2014.10.31 CA  Funktionen advarselsboks skal erstatte JavaScript Alertbokse - er ikke færdig. Søg 20141031
// 2014.10.31 PHR tilføjet funktion "find_varemomssats"
// 2014.11.21 CA  Funktionen tekstsboks erstatter advarselsboks en udgave med og uden tabel. Søg 20141121
// 2014.11.21 CA  Tilføjet funktion bokshjoerne til flytning af tekstbokse. Søg 20141121
// 2014.12.23 PHR  Tilføjet funktion find_lagervaerdi til brug med aut_lager. Søg 20141223
// 2015.02.18 PK Tilføjet to funktioner til at lave uppercase på tekst-streng. Søg mb_ucfirst eller mb_ucwords
// 2015.03.13 CA  Byttet om på farverne for infoboks (nu blå) og popop (nu grøn). Søg 20150313
// 2016.01.16 PHR Oprettet funktion regnstartslut

if (!function_exists('nr_cast')) {
	function nr_cast($tekst)
	{
		global $db_type;
			if ($db_type=='mysql') $tmp = "CAST($tekst AS SIGNED)";
			else $tmp = "to_number(text($tekst),text(999999999999))";
		return $tmp;
	}
}
if (!function_exists('dkdecimal')) {
	function dkdecimal($tal,$decimaler) {
		if (!$decimaler && $decimaler!='0') $decimaler=2;
		if (is_numeric($tal)) { 
			if ($tal) $tal=afrund($tal,$decimaler); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
			$tal=number_format($tal,$decimaler,",",".");
		}
		return $tal;
	}
}

if (!function_exists('dkdato')) {
	function dkdato($dato)
	{
		if ($dato) {
			list ($year, $month, $day) = explode('-', $dato);
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
	function usdecimal($tal,$decimaler) {
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
		$tal=$tal*1;
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
	function findtekst($tekst_id,$sprog_id)	{
		global $db_encode;
		global $webservice;
		$ny_tekst=NULL;
		$tekst_id=$tekst_id*1;
		$sprog_id=$sprog_id*1;
		if (!$sprog_id) $sprog_id=1;
		if ($r = db_fetch_array(db_select("select tekst from tekster where tekst_id='$tekst_id' and sprog_id = '$sprog_id' and tekst != '-'",__FILE__ . " linje " . __LINE__))){
			$tekst=$r['tekst'];
		} elseif (file_exists("../importfiler/egnetekster.csv") ) {
			$fp=fopen("../importfiler/egnetekster.csv","r");
			if ($fp) {
				while (!feof($fp)) {
					if ($linje=trim(fgets($fp))) {
						list($tekst_nr,$tmp)=explode(chr(9),$linje);
						if ($tekst_id==$tekst_nr) {
						$ny_tekst=substr(stristr($linje,chr(9)),1);# Linjen efter 1. tab.
							for ($i=1;$i<=$sprog_id;$i++) $linje = substr(stristr($linje,chr(9)),1); # Start paa tekst med aktuel sprog id findes.
							list($ny_tekst,$tmp)=explode(chr(9),$linje); # Tekststrengen isoleres
							$tekst=$ny_tekst;
						}
					}
				}
				fclose($fp);
			}
		}
		if (!$tekst) {
			$fp=fopen("../importfiler/tekster.csv","r");
			if ($fp) {
				while (!feof($fp)) {
					if ($linje=trim(fgets($fp))) {
						list($tekst_nr,$tmp)=explode(chr(9),$linje);
						if ($tekst_id==$tekst_nr) {
						$ny_tekst=substr(stristr($linje,chr(9)),1);# Linjen efter 1. tab. 
							for ($i=1;$i<=$sprog_id;$i++) $linje = substr(stristr($linje,chr(9)),1); # Start paa tekst med aktuel sprog id findes.
							list($ny_tekst,$tmp)=explode(chr(9),$linje); # Tekststrengen isoleres	
						}
					}
				}		
				fclose($fp);
			}
		}
		if ($ny_tekst) {
			if ($db_encode!="UTF8") $ny_tekst=utf8_decode($ny_tekst);
			$tmp=db_escape_string($ny_tekst); #20140505
			db_modify("insert into tekster(sprog_id,tekst_id,tekst) values ('$sprog_id','$tekst_id','$tmp')",__FILE__ . " linje " . __LINE__);
			$tekst=$ny_tekst;
		} 
		if (!$tekst) $tekst="Tekst nr: $tekst_id";
		elseif ($tekst=="-") $tekst='';
		return ($tekst);
	}
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
				case "gb": $retur = "EU"; break 1;
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
	function cvrnr_land($cvrnr, $skat) {
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
		$bogfkto = $r2['box4'];
		$momsfri = $r2['box7'];
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

if (!function_exists('infoboks')) {
	function infoboks($infosymbol, $infotekst, $infotype, $boksid, $hjoerne, $visning='span', $kant_oppe='1%', $kant_nede='68%', $kant_venstre='1%', $kant_hoejre='68%', $kant_midt='40%') {
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
		print "<BODY onLoad=\"javascript:alert('737 | $slut | $linje')\">";
		return('stop');	
	}
	$q=db_select("select kodenr,box1,box2,box3 from grupper where art = 'VG' and box8 = 'on' and (box1 = '$kontonr' or box2 = '$kontonr' or box3 = '$kontonr')",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if($r['box1']==$kontonr) $kob=1;
		if($r['box2']==$kontonr) $salg=1;
		if($r['box3']==$kontonr) {
			$salg=1;
			$kob=1;
		}
		$gruppe[$x]=$r['kodenr'];
		$x++;
	}
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
		if ($kob) {
			if ($tidspkt=='start') $qtxt="select sum(antal) as antal from batch_kob where vare_id = $vare_id[$x] and (fakturadate < '$slut' or kobsdate < '$slut')";
			else $qtxt="select sum(antal) as antal from batch_kob where vare_id = $vare_id[$x] and (fakturadate <= '$slut' or kobsdate <= '$slut')";
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
echo "$regnstart $regnslut<br>";
	return($regnstart.chr(9).$regnslut);
}}

######################################################################################################################################

?>
