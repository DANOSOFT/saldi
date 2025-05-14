<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/db_query.php ---patch 4.1.1 ----2025-05-10--------------
//                           LICENSE
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. 
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20121222 Tilføjet db_escape_string
// 20130210 Break ændret til break 1
// 20151005 Funktion injecttjek tjekker om der sker forsøg på at lave sql injektion
// 20170124 PHR split erstattet af explode
// 20170321 E.Viuff, Funktion injecttjek - Tilføjet $brugernavn til global og rettet db_query til db_modify.
// 20170501	Tilføjet understøttelse af mysqli.
// 20190412 customAlertText hentes nu fra tabellen settings.
// 20190704 RG (Rune Grysbæk) Mysqli implementering 20190704
// 20200225 PHR some changes regarding MySQLi
// 20200308 PHR addded function db_create, db_exists & tbl_exists.
// 20221106 PHR - Various changes to fit php8 / MySQLi
// 20230730 LOE - Minor modification, abolute path to std_func
// 20250121 connection as first parameter in pg_*
// 20250510 LOE Replaced mysql_query() with mysqli_query() to adjust for php7&above
// 20250510 LOE Added check for empty database and added error message if database is empty


if (!function_exists('get_relative')) {
    function get_relative() {
        $url = $_SERVER['REQUEST_URI'];
        $questionMarkPos = strpos($url, '?');
        if ($questionMarkPos !== false) {
            $path = substr($url, 0, $questionMarkPos);
        } else {
            $path = $url;
        }
        $slashCount = substr_count($path, '/');
        $relativePath = str_repeat('../', max(0, $slashCount - 2));

        return $relativePath;
    }
}

if (!function_exists('db_connect')) {
	function db_connect($l_host, $l_bruger, $l_password, $l_database="", $l_spor="") {
		global $db_type;
		global $db_encode;
		global $connection; #20190704
		
		$errTxt="";
		
		if (strtolower($db_type) == 'mysql' || strtolower($db_type) == 'mysqli') {
    		// Check if mysqli_connect exists (only if mysqli is available)
	  if (function_exists('mysqli_connect')) {
			if ($l_host && !$l_bruger && !$l_password) {
				list($l_host, $l_bruger, $l_password) = explode(",", $l_host); // Parse host, user, and password from a single string if needed
			}
			
			// Establish connection using mysqli
			$connection = mysqli_connect(trim($l_host, "'"), trim($l_bruger, "'"), trim($l_password, "'"));
			
			// Check if connection is successful
			if (!$connection) {
				$errTxt = "<h1>Fejl: PHP-funktionen <b>mysqli_connect()</b> kunne ikke findes eller forbindelsen fejlede</h1>" .
						"<p>Er både MySQLi og php-mysqli installeret?</p>";
			} else {
				// Set character encoding to UTF-8 or latin9 based on $db_encode
				if ($db_encode == 'UTF8') {
					mysqli_query($connection, "SET NAMES 'utf8'");
				} else {
					mysqli_query($connection, "SET NAMES 'latin9'");
				}
			}
		} else {
			$errTxt = "<h1>Fejl: PHP-funktionen <b>mysqli_connect()</b> kunne ikke findes</h1>" .
					"<p>Er både MySQLi og php-mysqli installeret?</p>";
		}
	  }else {
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
	function db_error() {
		if ($db_type=='mysqli') echo mysqli_error(). "\n";
		else if ($db_type=='mysql') echo mysql_error(). "\n";
		else  echo pg_last_error($connection). "\n";
	}
}

if (!function_exists('db_close')) {
	function db_close($qtext) {
		global $db_type;
		if ($db_type=="mysql") mysql_close($qtext);
		else if ($db_type=="mysqli") mysqli_close($qtext); #20190704
		else pg_close($qtext);
	}
}

if (!function_exists('db_modify')) {
	function db_modify($qtext, $spor) {
		global $brugernavn;
		global $connection,$customAlertText;
		global $db,$db_skriv_id,$db_type;
		global $sqdb;
		global $webservice;

		$temp = get_relative() . 'temp/' . $db;

		$qtext=injecttjek($qtext);
#20190704 START
		 if ($db_type == "mysql" || $db_type == "mysqli") {
            $db_query = mysqli_query($connection, $qtext);  //mysql_query deprecated in php 7 and above
			        }else {
			$qtext=str_replace(' like ',' ilike ',$qtext);
			$db_query=pg_query($connection, $qtext);
		}
#20190704 END
		
		$db=trim(if_isset($db, ''));
		if ($db_skriv_id>1) {
				$fp=fopen("$temp/.ht_modify.log","a");
				fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$spor.": ".$db_skriv_id."\n");
				fwrite($fp,$qtext.";\n");
			fclose($fp);
		}
		if (!$db_query) { #20190704
			if ($db_type=="mysql")       $errtxt = mysql_error($connection);
			else if ($db_type=="mysqli") $errtxt = mysqli_error($connection); #20190704
			else $errtxt=pg_last_error($connection);
			$fp=fopen("$temp/.ht_modify.log","a");
			fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$spor."\n");
			fwrite($fp,"-- Fejl!! ".$qtext." | $errtxt;\n");
			fclose($fp);
			$message=$db." | ".$qtext." | ".$spor." | ".$brugernavn." ".date("Y-m-d H:i:s")." | $errtxt";
			if (strstr($spor,"includes/opdat")) {
				if (file_exists("$temp/opdatfejl.txt")) {
					$ff=fopen("$temp/opdatfejl.txt","r");
					$lastmail=trim(fgets($ff));
					fclose($ff);
				} else $lastmail=0; 
				if($lastmail!=date("U")) {
					if ($sqdb == 'develop') echo "$message<br>";
					else {
						$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
						mail('fejl@saldi.dk', 'SALDI Opdat fejl', $message, $headers);
					}
					$ff=fopen("$temp/opdatfejl.txt","w");
					fwrite($ff,date("U")."\n");
					fclose($ff);
				} 
			} else {
				if (file_exists("$temp/modifyfejl.txt")) {
					$ff=fopen("$temp/modifyfejl.txt","r");
					$lastmail=trim(fgets($ff));
					fclose($ff);
				} else $lastmail=0; 
				if($lastmail!=date("U")) { 
					if ($sqdb == 'develop') echo "$message<br>";
					else {
						$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
						mail('fejl@saldi.dk', 'SALDI Fejl - modify', $message, $headers);
					}
					$ff=fopen("$temp/modifyfejl.txt","w");
					fwrite($ff,date("U")."\n");
					fclose($ff);
				} 
				// if ($db_type=="mysql") {
				// 	mysql_query("ROLLBACK");
				// } elseif ($db_type=="mysqli") { #20190704
				// 	mysqli_query($connection, "ROLLBACK");
				// }
				if ($db_type == "mysql" || $db_type == "mysqli") {
					mysqli_query($connection, "ROLLBACK");
				}
							
				(isset($customAlertText))?$alerttekst=$customAlertText:$alerttekst="Uforudset h&aelig;ndelse, kontakt salditeamet på telefon 4690 2208"; 
				if ($webservice) return ('1'.chr(9)."$alerttekst");
				alert("$alerttekst");
				exit;
			}
		}
		return ('0'.chr(9).'query accepted');
	}
}

if (!function_exists('db_select')) {
	function db_select($qtext,$spor) {
		global $brugernavn;
		global $connection,$customAlertText;
		global $db,$db_type;
		global $s_id,$sqdb;

		if (!function_exists('alert')) include('std_func.php'); #20230730

		$qtext=injecttjek($qtext);

		$temp = get_relative() . 'temp/' . $db;

		if (!file_exists($temp)) {
			mkdir($temp, 0777);
			$onlineLog = get_relative() . 'temp/.ht_online.log';
			$onlineTxt = date("Y-m-d")." ".date("H:i:s")." ".$_SERVER['REMOTE_ADDR']." ".$s_id." ".$db." ".$brugernavn."\n";
			file_put_contents($onlineLog, $onlineTxt, FILE_APPEND);
		}
		if ($db_type == "mysql" || $db_type == "mysqli") {
			// Use mysqli for MySQL as mysql_query() is deprecated
			$query = mysqli_query($connection, $qtext);
			$errtxt = mysqli_error($connection);  // Use mysqli_error for both MySQL and MySQLi
		} else {
			$qtext = str_replace(' like ', ' ilike ', $qtext);
			$query = pg_query($connection, $qtext);
			$errtxt = pg_last_error($connection);
		}

		if ($errtxt)	{		
			$db=trim($db);
			$linje="";
			if (file_exists("$temp/lasterror.txt")) {
				$fp=fopen("$temp/lasterror.txt","r");
				$linje=trim(fgets($fp));
				fclose($fp);
			}
			#list($tmp,$tmp2)=explode("\n",$errtxt);

			$lines = explode("\n", $errtxt);
			$tmp = $lines[0] ?? NULL;  
			$tmp2 = $lines[1] ?? NULL;

			$tmp.="_".date("h:i");
			if ($linje != $tmp) {
				$fp=fopen("$temp/lasterror.txt","a");
				fwrite($fp,"$tmp");
				fclose($fp);
				$fp=fopen("$temp/lasterror.txt","a");
				fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$spor."\n");
				fwrite($fp,"-- Fejl!! ".$qtext." | $errtxt;\n");
				fclose($fp);
#				if (!strpos($errtxt,'current transaction is aborted, commands ignored until end of transaction block')) {
				if (file_exists("$temp/selectfejl.txt")) {
					$ff=fopen("$temp/selectfejl.txt","r");
					$lastmail=trim(fgets($ff));
					fclose($ff);
				} else $lastmail=0; 
				if($lastmail!=date("U")) {
					$message=$db." | ".$qtext." | ".$spor." | ".$brugernavn." ".date("Y-m-d H:i:s")." | $errtxt";
					if ($sqdb == 'develop') echo "$message<br>";
					else {
						$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
						mail('fejl@saldi.dk', 'SALDI Fejl - select', $message, $headers);
					}
					$ff=fopen("$temp/selectfejl.txt","w");
					fwrite($ff,date("U")."\n");
					fclose($ff);
				} 
				(isset($customAlertText))?$alerttekst=$customAlertText:$alerttekst="Uforudset h&aelig;ndelse, kontakt salditeamet på telefon 4690 2208"; 
				if (strpos($spor,'sqlquery_io')) echo "$errtxt<br>";
				alert("$alerttekst");
			} else {
				#	$customAlertText saettes i connect.php;
				(isset($customAlertText))?$alerttekst=$customAlertText:$alerttekst="Uforudset h&aelig;ndelse, kontakt salditeamet på telefon 4690 2208"; 
				echo $fejltxt; 
				alert("$alerttekst");
				exit;
			}
		} else {
			$fp=fopen("$temp/.ht_select.log","a");
			fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$spor."\n");
			fwrite($fp,$qtext.";\n");
			fclose($fp);
		}
		return $query;
	}
}

if (!function_exists('db_catalog_setval')) { // <-- Never used
	function db_catalog_setval($seq, $val, $bool) {
		global $db_type;
		return pg_catalog.setval($seq, $val, $bool); // <-- invalid function
	}
}

if (!function_exists('db_fetch_array')) {
	function db_fetch_array($qtext) {
		global $db_type;
 		if ($db_type == "mysql" || $db_type == "mysqli") {
            if ($qtext && $qtext !== false) {
                return mysqli_fetch_array($qtext, MYSQLI_BOTH);
            } else {
                error_log("Error: db_fetch_array() - Invalid query result");
                return false;
            }
        } else return pg_fetch_array($qtext);
	}
}


if (!function_exists('db_field_name')) {
	function db_field_name($a,$b) {
		global $db_type;
		if ($db_type=="mysql") return mysql_field_name($a,$b);
		else if ($db_type=="mysqli") return mysqli_fetch_field_direct($a,$b); #20190704
		else return pg_field_name($a,$b);
	}
}

if (!function_exists('db_field_type')) {
	function db_field_type($a,$b) {
		global $db_type;
		if ($db_type=="mysql") return mysql_field_type($a,$b);
		else if ($db_type=="mysqli") return mysqli_fetch_field_direct($a,$b); #20190704
		else return pg_field_type($a,$b);
	}
}

if (!function_exists('db_fetch_row')) {
	function db_fetch_row($qtext) {
		global $db_type;
		if ($db_type=="mysql") return mysql_fetch_row($qtext);
		else if ($db_type=="mysqli") return mysqli_fetch_row($qtext); #20190704
		else return pg_fetch_row($qtext);
	}
}

if (!function_exists('db_num_rows')) {
	function db_num_rows($qtext){
		global $db_type;
		if ($db_type=="mysql") return mysql_num_rows($qtext);
		else if ($db_type=="mysqli") return mysqli_num_rows($qtext); #20190704
		else return pg_num_rows($qtext);
	}
}

if (!function_exists('db_num_fields')) {
	function db_num_fields($qtext) {
		global $db_type;
		if ($db_type=="mysql") return mysql_num_fields($qtext);
		else if ($db_type=="mysqli") return mysqli_num_fields($qtext); #20190704
		else return pg_num_fields($qtext);
	}
}

if (!function_exists('transaktion')) {
	function transaktion($qtext){
		global $brugernavn;
		global $db_type;
		global $db;
		global $connection; #20190704

		$temp = get_relative() . 'temp/' . $db;
		$fp=fopen("$temp/.ht_modify.log","a");
		fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$qtext."\n");
		fwrite($fp,$qtext.";\n");
		if ($db_type == "mysql" || $db_type == "mysqli") {
			$query = mysqli_query($connection, $qtext);
		} else {
			$query = pg_query($connection, $qtext);
		}

	}
}

if (!function_exists('db_escape_string')) {
	function db_escape_string($qtext) {
		global $db_type;
		global $connection; #20190704
		
		if ($db_type=="mysql") return mysql_real_escape_string($qtext);
		elseif ($db_type=="mysqli") return mysqli_real_escape_string($connection, $qtext); #20190704
		else return pg_escape_string($connection, $qtext);
	}
}

if (!function_exists('db_exists')) {
	function db_exists($tmpDb) {
		global$connection,$db,$db_type;
		if ($db_type=="mysql") {
			(mysql_select_db($tmpDb))?$db_exists=1:$db_exists=0;
			mysql_select_db($db);
		}	elseif ($db_type=="mysqli") { #20221106
			$qtxt="SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$tmpDb'";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			(db_fetch_array($q))?$db_exists=1:$db_exists=0;
		} else {
			$qtxt="SELECT datname FROM pg_catalog.pg_database where datname='$tmpDb'";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			(db_fetch_array($q))?$db_exists=1:$db_exists=0;
		}	
		return($db_exists);
	}
}

if (!function_exists('tbl_exists')) {
	function tbl_exists($table) {
 		global $connection,$db,$db_type;
		if ($db_type=="mysql") {
			$qtxt="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$db' AND table_name = '$table'";
			(db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$tbl_exists=1:$tbl_exists=0;
		}	elseif ($db_type=="mysqli") {
			$qtxt="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$db' AND table_name = '$table'";
			(db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$tbl_exists=1:$tbl_exists=0;
		} else {
			$qtxt="SELECT tablename FROM pg_tables where tablename='$table'";
#			$r=db_fetch_array(
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			($r['tablename'])?$tbl_exists=1:$tbl_exists=0;
		}
		return($tbl_exists);
	}
}

if (!function_exists('db_create')) {
	function db_create($db) {
		global $connection,$db_encode,$db_type,$sqhost,$squser,$sqpass;
		if ($db_type=="mysql" or $db_type=="mysqli") { #RG_mysqli
			db_modify("CREATE DATABASE $db",__FILE__ . " linje " . __LINE__);
			if ($db_type=="mysql") mysql_select_db($db);
			else mysqli_select_db($connection,$db);
			if ($db_encode=="UTF8") db_modify("SET character_set_client = 'UTF8'",__FILE__ . " linje " . __LINE__);
			else db_modify("SET character_set_client = 'LATIN1'",__FILE__ . " linje " . __LINE__);
		} else {
			if ($db_encode=="UTF8") db_modify("CREATE DATABASE $db encoding = 'UTF8' template template0",__FILE__ . " linje " . __LINE__);
			else db_modify("CREATE DATABASE $db encoding = 'LATIN9' template template0",__FILE__ . " linje " . __LINE__);
			db_close($connection);
			$connection = db_connect ($sqhost,$squser,$sqpass,$db,__FILE__ . " linje " . __LINE__);
		}
	}
}

if (!function_exists('injecttjek')) {
	function injecttjek($qtext) {
		global $brugernavn,$db;
		$temp = get_relative() . 'temp/' . $db;
		if (strpos($qtext,';')) {
			$tjek=1;
			for ($x=0;$x<strlen($qtext);$x++) {
				if ($tjek==1 && substr($qtext,$x,1)=="'" && substr($qtext,$x-1,1)!="\\") $tjek=0;
				elseif ($tjek==0 && substr($qtext,$x,1)=="'" && substr($qtext,$x-1,1)!="\\") $tjek=1;
				if ($tjek && substr($qtext,$x,1)==";") {	
					$s_id=session_id();
					$txt="SQL injection registreret!!! - Handling logget & afbrudt";
					alert("$txt");
					$fp=fopen("$temp/.ht_modify.log","a");
					fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s")."\n");
					fwrite($fp,"-- SQL injection fra ".$_SERVER["REMOTE_ADDR"]." | " .$qtext.";\n");	
					fclose($fp);
					$s_id=session_id();
					include("../includes/connect.php");
					$db_modify("delete from online where session_id = '$s_id'");
					print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/index.php\">";
					exit;
				}
			} 
		} 
		return("$qtext");
	}
}
?>
