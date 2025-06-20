<?php
// -----------includes/std_func.php-------lap 3.2.7----2012-01-30-----
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
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------
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
	function dkdecimal($tal)
	{
		if (is_numeric($tal)) { 
			if ($tal>0) $tal=round($tal+0.0001,2);#Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
			elseif ($tal<0) $tal=round($tal-0.0001,2);
			$tal=number_format($tal,2,",",".");
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
		GLOBAL $webservice;
		$ny_tekst=NULL;
		$tekst_id=$tekst_id*1;
		$sprog_id=$sprog_id*1;
		if (!$sprog_id) $sprog_id=1;
		if ($r = db_fetch_array(db_select("select tekst from tekster where tekst_id='$tekst_id' and sprog_id = '$sprog_id'",__FILE__ . " linje " . __LINE__))){
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
			db_modify("insert into tekster(sprog_id,tekst_id,tekst) values ('$sprog_id','$tekst_id','$ny_tekst')",__FILE__ . " linje " . __LINE__);
			$tekst=$ny_tekst;
		} 
		if (!$tekst) $tekst="Tekst nr: $tekst_id";
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
						break;
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
?>
