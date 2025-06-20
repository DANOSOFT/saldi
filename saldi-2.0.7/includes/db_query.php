<?php

// ----------includes/db_query.php----lap 2.0.7----2009-05-20---20:52----------
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

// db_type: 
// 0: postgreSql
// 1: MySql

#$db_type=0;


if (!function_exists('db_connect')) {
	function db_connect($l_host, $l_bruger="", $l_password="", $l_database="", $l_spor="") 
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
				if ($l_password) {
					$connection = pg_connect ("host=$l_host dbname=$l_database user=$l_bruger password=$l_password");
				} elseif ($l_bruger) {
					$connection = pg_connect ("host=$l_host dbname=$l_database user=$l_bruger");
				} else {
					$connection = pg_connect ("$l_host");
				}
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
				break;
			case 1:
				echo mysql_error(). "\n";
				break;
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
		global $db_skriv_id;
		
		if ($db_type=="mysql") $db_query="mysql_query";
		else $db_query="pg_query";

		
		if (strpos($qtext,';')) {
			$tjek=1;
			for ($x=0;$x<strlen($qtext);$x++) {
				if ($tjek==1 && substr($qtext,$x,1)=="'" && substr($qtext,$x-1,1)!="\\") $tjek=0;
				elseif ($tjek==0 && substr($qtext,$x,1)=="'" && substr($qtext,$x-1,1)!="\\") $tjek=1;
				if ($tjek && substr($qtext,$x,1)==";") {	
					$s_id=session_id();
					$txt="SQL injection registreret!!! - Handling logget & afbrudt.";
					print "<BODY onLoad=\"javascript:alert('$txt')\">";
					$fp=fopen("../temp/.ht_$db.log","a");
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
		$db=trim($db);
		if ($db_skriv_id>1) {
				$fp=fopen("../temp/.ht_$db.log","a");
				fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$spor.": ".$db_skriv_id."\n");
				fwrite($fp,$qtext.";\n");
			fclose($fp);
		}
		
		if (!$db_query($qtext)) {
			if ($db_type=="mysql") $fejltekst=mysql_error();
			else $fejltekst=pg_last_error();
			$fp=fopen("../temp/.ht_$db.log","a");
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
				print "<BODY onLoad=\"javascript:alert('Uforudset h&aelig;ndelse - Kontakt venligst SALDI-teamet p&aring; telefon 46 90 22 08.')\">";
				if ($db_type=="mysql") {
					mysql_query("ROLLBACK");
				}
				exit;
			}
		}
	}
}

if (!function_exists('db_select')) {
	function db_select($qtext,$spor) {
		global $db_type;
		global $brugernavn;
		global $db;
		
		if ($db_type=="mysql") $query="mysql_query";
		else $query="pg_query";
		
		if (!$query=$query($qtext)) {
			if ($db_type=="mysql") $fejltekst=mysql_error();
			else $fejltekst=pg_last_error();
			$db=trim($db);
			$fp=fopen("../temp/.ht_$db.log","a");
			fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$spor."\n");
			fwrite($fp,"-- Fejl!! ".$qtext." | $fejltekst;\n");
			fclose($fp);
			$message=$db." | ".$qtext." | ".$spor." | ".$brugernavn." ".date("Y-m-d H:i:s")." | $fejltekst";
			$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
			mail('fejl@saldi.dk', 'SALDI Fejl - select', $message, $headers);
			print "<BODY onLoad=\"javascript:alert('Uforudset h&aelig;ndelse - Kontakt venligst SALDI-teamet p&aring; telefon 46 90 22 08')\">";
#			exit;
		}	else {
#			$fp=fopen("../temp/.ht_$db.log","a");
#			fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$spor."\n");
#			fwrite($fp,$qtext.";\n");
#			fclose($fp);
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
	function transaktion($handling){
		global $db_type;
		if ($db_type=="mysql") mysql_query($handling);
		else pg_query($handling);
	}
}
?>
