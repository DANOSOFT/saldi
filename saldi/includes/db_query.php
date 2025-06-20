	<?php

// -----------------------------------------includes/db_query.php-------------------------lap 1.0.5----------
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
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------

// db_type: 
// 0: postgreSql
// 1: MySql

$db_type=0;


if (!function_exists('db_connect')) {
	function db_connect($qtext) 
	{
		global $db_type;
		$errTxt="";
		
	 switch ($db_type) {
			case 0:
				if (function_exists('pg_connect'))
					$connection = pg_connect ($qtext);
				else {
					$errTxt="<h1>Fejl: Problemer med pg_connect()</h1>".
					"Er b&aring;de postgres og php-pgsql installeret ?";
				}
				break;
			case 1:
				if (function_exists('mysql_connect'))
					$connection = mysql_connect ($qtext);
				else {
					$errTxt="<h1>Fejl: Problemer med mysql_connect()</h1>".
					"Er b&aring;de MySql og php-mysql installeret ?";
				}
				break;
			default:
				$errTxt="Ukendt databasetype ($db_type)";
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
				echo postgresql_error(). "\n";
				break;
			case 1:
				echo mysql_error(). "\n";
				break;
		}
	}
}

if (!function_exists('db_close')) {
	function db_close($qtext)
	{
		pg_close($qtext);
	}
}

if (!function_exists('db_modify')) {
	function db_modify($qtext) {
		global $brugernavn;
		global $db;
		
		$db=trim($db);
		$fp=fopen("../temp/.ht_$db.log","a");
		fwrite($fp,"# ".$brugernavn." ".date("Y-m-d H:i:s")."\n");
		fwrite($fp,$qtext."\n");
		if (!pg_query($qtext)) {
			fwrite($fp,"# ".$brugernavn." ".date("Y-m-d H:i:s")."\n");
			fwrite($fp,"# Fejl!! ".$qtext."\n");
			fclose($fp);
			print "<BODY onLoad=\"javascript:alert('Transaktion ikke gennemf&oslash;rt - Kontakt systemansvarlig')\">";
			exit;
		}
		fclose($fp);
	}
}

if (!function_exists('db_select')) {
	function db_select($qtext)
	{
		global $brugernavn;
		global $db;
		
		if (!$query=pg_query($qtext)) {
			$db=trim($db);
			$fp=fopen("../temp/.ht_$db.log","a");
			fwrite($fp,"# ".$brugernavn." ".date("Y-m-d h:i:s")."\n");
			fwrite($fp,"# Fejl!! ".$qtext."\n");
			fclose($fp);
			print "<BODY onLoad=\"javascript:alert('Transaktion ikke gennemf&oslash;rt - Kontakt systemansvarlig')\">";
			exit;
		}
		else {return $query;}
	}
}

if (!function_exists('db_catalog_setval')) {
	function db_catalog_setval($seq, $val, $bool)
	{
		return pg_catalog.setval($seq, $val, $bool);
	}
}

if (!function_exists('db_fetch_array')) {
	function db_fetch_array($qtext)
	{
		return pg_fetch_array($qtext);
	}
}

if (!function_exists('db_num_rows')) {
	function db_num_rows($qtext)
	{
		return pg_num_rows($qtext);
	}
}

if (!function_exists('transaktion')) {
	function transaktion($handling)
	{
		pg_query($handling);
	}
}
?>
