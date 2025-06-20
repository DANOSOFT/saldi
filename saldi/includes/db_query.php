<?php

// ----------includes/db_query.php----lap 3.5.9----2015-10-05--------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20121222 Tilføjet db_escape_string
// 2013.02.10 Break ændret til break 1
// 20151005 Funktion injecttjek tjekker om der sker forsøg på at lave sql injektion

if (!function_exists('db_connect')) {
	function db_connect($l_host, $l_bruger, $l_password, $l_database="", $l_spor="") 
	{
		global $db_type;
		global $db_encode;
		$errTxt="";
		
		if ($db_type=='mysql') {
			if (function_exists('mysql_connect')) {
				if ($l_host && !$l_bruger && !$l_password) list($l_host,$l_bruger,$l_password)=split(",",$l_host); 
				$connection = mysql_connect ("$l_host","$l_bruger","$l_password");
				if ($db_encode=='UTF8') mysql_query("SET NAMES 'utf8'");
				else mysql_query("SET NAMES 'latin9'");
			} else {
				$errTxt="<h1>Fejl: PHP-funktionen <b>mysql_connect()</b> kunne ikke findes</h1>".
				"<p>Er b&aring;de MySql og php-mysql installeret?</p>";
			}
		}	else {
			if (function_exists('pg_connect')) {
				if ($l_bruger && $l_database) {
					if ($l_password) $connection = pg_connect ("host=$l_host dbname=$l_database user=$l_bruger password=$l_password");
					else $connection = pg_connect ("host=$l_host dbname=$l_database user=$l_bruger");
				} elseif ($l_host) $connection = pg_connect ($l_host); # til systemer installert pre maj 09
			} else {
				$errTxt="<h1>Fejl: PHP-funktionen <b>pg_connect()</b> kunne ikke findes</h1>".
				"<p>Er b&aring;de postgres og php-pgsql installeret?</p>";
			}
		}
		if ($errTxt>"") {
			print $errTxt;
			die;
		}
		return $connection;
	}
}


if (!function_exists('db_error')) {
	function db_error()
	{
		global $db_type;
		switch ($db_type){
			case 0:
				echo pg_last_error(). "\n";
				break 1;
			case 1:
				echo mysql_error(). "\n";
				break 1;
		}
	}
}

if (!function_exists('db_close')) {
	function db_close($qtext) {
		global $db_type;
		if ($db_type=="mysql") mysql_close($qtext);
		else pg_close($qtext);
	}
}

if (!function_exists('db_modify')) {
	function db_modify($qtext, $spor) {
		global $db_type;
		global $brugernavn;
		global $db;
		global $sqdb;
		global $db_skriv_id;
		global $custom_alerttekst;
		
		if ($db_type=="mysql") $db_query="mysql_query";
		else $db_query="pg_query";
		
		$qtext=injecttjek($qtext);
		$db=trim($db);
		if ($db_skriv_id>1) {
				$fp=fopen("../temp/$db/.ht_modify.log","a");
				fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$spor.": ".$db_skriv_id."\n");
				fwrite($fp,$qtext.";\n");
			fclose($fp);
		}
		if (!$db_query($qtext)) {
			if ($db_type=="mysql") $fejltekst=mysql_error();
			else $fejltekst=pg_last_error();
			$fp=fopen("../temp/$db/.ht_modify.log","a");
			fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$spor."\n");
			fwrite($fp,"-- Fejl!! ".$qtext." | $fejltekst;\n");
			fclose($fp);
			$message=$db." | ".$qtext." | ".$spor." | ".$brugernavn." ".date("Y-m-d H:i:s")." | $fejltekst";
			if (strstr($spor,"includes/opdat")) {
				$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
				mail('fejl@saldi.dk', 'SALDI Opdat fejl', $message, $headers);
			} else {
				$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
				mail('fejl@saldi.dk', 'SALDI Fejl - modify', $message, $headers);
#				if (!function_exists('findtekst')) include("../includes/std_func.php");
#				$alerttekst=findtekst(342,$sprog_id);
				#	$custom_alerttekst saettes i connect.php;
				if ($db_type=="mysql") {
					mysql_query("ROLLBACK");
				}
				(isset($custom_alerttekst))?$alerttekst=$custom_alerttekst:$alerttekst="Uforudset h&aelig;ndelse, kontakt salditeamet på telefon 4690 2208"; 
				if ($webservice) return ('1'.chr(9).'$alerttekst');
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">\n";
				exit;
			}
		}
		return ('0'.chr(9).'query accepted');
	}
}

if (!function_exists('db_select')) {
	function db_select($qtext,$spor) {
		global $db_type;
		global $brugernavn;
		global $db;
		global $custom_alerttekst;
		$qtext=injecttjek($qtext);
		
		if (!file_exists("../temp/$db")) mkdir("../temp/$db", 0775);
		if ($db_type=="mysql") $query="mysql_query";
		else $query="pg_query";
		
		if (!$query=$query($qtext)) {
			if ($db_type=="mysql") $fejltekst=mysql_error();
			else $fejltekst=pg_last_error();
			$db=trim($db);
			$linje="";
			if (file_exists("../temp/$db/lasterror.txt")) {
				$fp=fopen("../temp/$db/lasterror.txt","r");
				$linje=trim(fgets($fp));
				fclose($fp);
			}
			list($tmp,$tmp2)=explode("\n",$fejltekst);
			$tmp.="_".date("h:i");
/*
				$tmp=str_replace("\r","",$tmp);
				$tmp=str_replace("\0","",$tmp);
				$tmp=str_replace("\x0B","",$tmp);
				$tmp=str_replace("^","",$tmp);
				$tmp=trim(str_replace("\n"," | ",$tmp));
*/
#				echo ">$tmp<<br>>$linje<<br><br>";
			if ($linje != $tmp) {
				$fp=fopen("../temp/$db/lasterror.txt","w");
				fwrite($fp,"$tmp");
				fclose($fp);
				$fp=fopen("../temp/$db/lasterror.txt","a");
				fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$spor."\n");
				fwrite($fp,"-- Fejl!! ".$qtext." | $fejltekst;\n");
				fclose($fp);
			
#				if (!strpos($fejltekst,'current transaction is aborted, commands ignored until end of transaction block')) {
					$message=$db." | ".$qtext." | ".$spor." | ".$brugernavn." ".date("Y-m-d H:i:s")." | $fejltekst";
					$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
					mail('fejl@saldi.dk', 'SALDI Fejl - select', $message, $headers);
#				}
				#	$custom_alerttekst saettes i connect.php;
				(isset($custom_alerttekst))?$alerttekst=$custom_alerttekst:$alerttekst="Uforudset h&aelig;ndelse, kontakt salditeamet på telefon 4690 2208"; 
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">\n";
			} else {
				#	$custom_alerttekst saettes i connect.php;
				(isset($custom_alerttekst))?$alerttekst=$custom_alerttekst:$alerttekst="Uforudset h&aelig;ndelse, kontakt salditeamet på telefon 4690 2208"; 
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">\n";
				exit;
			}
		} else {
			$fp=fopen("../temp/$db/.ht_select.log","a");
			fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$spor."\n");
			fwrite($fp,$qtext.";\n");
			fclose($fp);
		}
		return $query;
	}
}

if (!function_exists('db_catalog_setval')) {
	function db_catalog_setval($seq, $val, $bool) {
		global $db_type;
		return pg_catalog.setval($seq, $val, $bool);
	}
}

if (!function_exists('db_fetch_array')) {
	function db_fetch_array($qtext) {
		global $db_type;
		if ($db_type=="mysql") return mysql_fetch_array($qtext);
		else return pg_fetch_array($qtext);
	}
}


if (!function_exists('db_field_name')) {
	function db_field_name($a,$b) {
		global $db_type;
		if ($db_type=="mysql") return mysql_field_name($a,$b);
		else return pg_field_name($a,$b);
	}
}

if (!function_exists('db_field_type')) {
	function db_field_type($a,$b) {
		global $db_type;
		if ($db_type=="mysql") return mysql_field_type($a,$b);
		else return pg_field_type($a,$b);
	}
}

if (!function_exists('db_fetch_row')) {
	function db_fetch_row($qtext) {
		global $db_type;
		if ($db_type=="mysql") return mysql_fetch_row($qtext);
		else return pg_fetch_row($qtext);
	}
}

if (!function_exists('db_num_rows')) {
	function db_num_rows($qtext){
		global $db_type;
		if ($db_type=="mysql") return mysql_num_rows($qtext);
		else return pg_num_rows($qtext);
	}
}

if (!function_exists('db_num_fields')) {
	function db_num_fields($qtext) {
		global $db_type;
		if ($db_type=="mysql") return mysql_num_fields($qtext);
		else return pg_num_fields($qtext);
	}
}

if (!function_exists('transaktion')) {
	function transaktion($qtext){
		global $brugernavn;
		global $db_type;
		global $db;

		$fp=fopen("../temp/$db/.ht_modify.log","a");
		fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$qtext."\n");
		fwrite($fp,$qtext.";\n");
			if ($db_type=="mysql") mysql_query($qtext);
		else pg_query($qtext);
	}
}

if (!function_exists('db_escape_string')) {
	function db_escape_string($qtext) {
		global $db_type;
		
		if ($db_type=="mysql") return mysql_real_escape_string($qtext);
		else return pg_escape_string($qtext);
	}
}

if (!function_exists('injecttjek')) {
	function	injecttjek($qtext) {
		global $db;
		if (strpos($qtext,';')) {
			$tjek=1;
			for ($x=0;$x<strlen($qtext);$x++) {
				if ($tjek==1 && substr($qtext,$x,1)=="'" && substr($qtext,$x-1,1)!="\\") $tjek=0;
				elseif ($tjek==0 && substr($qtext,$x,1)=="'" && substr($qtext,$x-1,1)!="\\") $tjek=1;
				if ($tjek && substr($qtext,$x,1)==";") {	
					$s_id=session_id();
					$txt="SQL injection registreret!!! - Handling logget & afbrudt";
					print "<BODY onLoad=\"javascript:alert('$txt')\">";
					$fp=fopen("../temp/$db/.ht_modify.log","a");
					fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s")."\n");
					fwrite($fp,"-- SQL injection fra ".$_SERVER["REMOTE_ADDR"]." | " .$qtext.";\n");	
					fclose($fp);
					$s_id=session_id();
					include("../includes/connect.php");
					$db_query("delete from online where session_id = '$s_id'");
					print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/index.php\">";
					exit;
				}
			} 
		} 
		return("$qtext");
	}
}
?>
