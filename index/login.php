<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- index/login.php -----patch 4.1.0 ----2025-03-14--------------
// LICENSE
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
// 20130919 Tjekkede ikke om der var opdateringer ved login i "hovedregnskab" Søg 20130919
// 20140106	Tilføjet opslag i tmp_kode. Søg tmp_kode
// 20140920	Tilføjet db_escape_string foran brugernavn og regnskab så det også fungerer med apostrof i disse.
// 20150104 Initerer variablen $nextver så den bypasser versionskontrol i online.php
// 20150114 PK 	- Tilføjet session_unset,session_destroy, som tømmer alle sessions variabler
// 20150129 PHR - Fjernet session_unset,session_destroy, da man bliver smidt af under login.
// 20150129 PK 	- Tilføjet session_unset,session_destroy før session_start, som tømmer browser for sessions når man kommer ind på login siden.
// 20150209 PHR - Rettigheder sættes nu også ved temp koder, elle smides man af igen : 20150209
// 20151002	PHR - online.txt er omdøbt til .ht_online.txt
// 20161104	PHR - Div ændringer relateret til bedre sikkerhed
// 20170210	PHR - Aktivering af nyt API 20170217
// 20170911	PHR	- Tilføjet db_type til global og rettet $sqdb til $db grundet db fejl ved login fra anden session uden logaf. 20170911 
// 20180108	PHR	-	Udfaset gammelt API kald 20180108
// 20180305	PHR	-	Opdateret API kald
// 20181128 PHR - Timezone hentes nu fra tabellen settings.
// 20190704 RG	-	(Rune Grysbæk) Mysqli implementation 
// 20200622 PHR - Added include addrOpdat.php - can be removed after 3.9.3 (done 20210127)
// 20210127 PHR - Added trim() to $r['lukket']
// 20210826 PHR - Added squser & sqpass to function online.
// 20210830 LOE - When a user successfuly logs in if their IP is not found in ip's table it is added
// 20210902	PHR	- Added $regnskab to .ht_online.log 
// 20211006 LOE - This is not available in develop database
// 20211007 LOE - $_SESION changed to $_SESSION
// 20211009 PHR - language settings. ($languageId)
// 20211015 LOE - Modified some codes to adjust to IP moved to settings table 
// 20211018 LOE - Fixed some bugs
// 20211105 PHR - As above :o)
// 20211205 PHR - Sets language to 1 of not found;
// 20211215 PHR - moved call to online.php
// 20220118 PHR - Added 'if ($db != $sqdb && $dbver > '4.0.4')'
// 20200222 PHR - Added call to locator and added global_id;
// 20200225 PHR - Added call to 'includes/betewwnUpdates';
// 20220618 PHR - Laguage now fetched from cookie instead of table 'settings'
// 20230718 LOE - Made some modifications + 20230725
// 20240417 PHR - Unified login - redirets to correct server.
// 20240417 PHR - 'regnskab' and 'brugernavn' is now case-insensitive
// 20240425 LOE - Made some modifications.
// 20240502 LOE - Fixed some bugs concerning "PHP type juggling" and some variables checked for set or not
// 20241202 LOE - Added session for retrieving globalId from other pages.
// 20250314 LOE	- Sanitized some inputs to mitigate against XSS attack 
ob_start(); //Starter output buffering
@session_start();
session_unset();
session_destroy();

@session_start();


$s_id=session_id();
$css="../css/std.css";
$title="login";
$fortsaet=NULL;
$nextver='';

include("../includes/connect.php");
include("../includes/db_query.php");
include("../includes/tjek4opdat.php");
include("../includes/std_func.php");

print "<!--";
$timezone = system("timedatectl | grep \"Time zone\"");
print "-->";
list($tmp,$timezone) = explode(":",$timezone);
list($timezone,$tmp) = explode("(",$timezone);
$timezone = trim($timezone);
if (!$timezone) $timezone = 'Europe/Copenhagen';
date_default_timezone_set($timezone);

$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='regnskab' and column_name = 'invoices'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER table regnskab ADD column invoices int DEFAULT(0)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}


#$_COOKIE['timezone'] = $timezone;#20210929

#cho "select var_value from settings where var_name='alertText'<br>";
$r=db_fetch_array(db_select("select var_value from settings where var_name='alertText'",__FILE__ . " linje " . __LINE__));
if (isset($r['var_value'])) $_SESSION['customAlertText']=$r['var_value'];
$r=db_fetch_array(db_select("select var_value from settings where var_name='ps2pdf'",__FILE__ . " linje " . __LINE__)); #20211007
if (isset($r['var_value'])) $_SESSION['ps2pdf']=$r['var_value'];
$r=db_fetch_array(db_select("select var_value from settings where var_name='pdftk'",__FILE__ . " linje " . __LINE__));
#if (isset($r['var_value'])) $_SESSION['pdftk']=$r['var_value']; #20211006 This is not available in develop database
$r=db_fetch_array(db_select("select var_value from settings where var_name='ftp'",__FILE__ . " linje " . __LINE__));
if (isset($r['var_value'])) $_SESSION['ftp']=$r['var_value'];
$r=db_fetch_array(db_select("select var_value from settings where var_name='dbdump'",__FILE__ . " linje " . __LINE__));
if (isset($r['var_value'])) $_SESSION['dbdump']=$r['var_value'];
$r=db_fetch_array(db_select("select var_value from settings where var_name='tar'",__FILE__ . " linje " . __LINE__));
if (isset($r['var_value'])) $_SESSION['tar']=$r['var_value'];
$r=db_fetch_array(db_select("select var_value from settings where var_name='zip'",__FILE__ . " linje " . __LINE__));
if (isset($r['var_value'])) $_SESSION['zip']=$r['var_value'];



#$r=db_fetch_array(db_select("select var_value from settings where var_grp='localization'",__FILE__ . " linje " . __LINE__));#20211006
#if ($r['var_value']) $_SESSION['lang2']=$r['var_value'];

if ($db_encode=="UTF8") $charset="UTF-8";
else $charset="ISO-8859-1";
PRINT "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n
<html>\n
<head><title>$title</title><meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\">\n";
if ($css) PRINT "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\" />";
print "<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/login.css\" />";
print "</head>";

$dbMail=NULL;
function sanitize_input($input) {
	// Trim the input to remove any leading/trailing whitespace
	$input = trim($input);
	
	// Remove any special characters that might lead to SQL injection
	$input = preg_replace('/[^\w\s\-]/', '', $input);
	
	if (strlen($input) > 80) {
		return false;
	}
	
	return $input;
}

if ((isset($_POST['regnskab']))||($_GET['login']=='test')) {
	if ($regnskab = trim($_POST['regnskab'])){
		// $brugernavn = trim($_POST['brugernavn']);
		// $password = trim($_POST['password']); // password i formatet uppercase( md5( timestamp + uppercase( md5(original_password) ) ) )
		// (isset($_POST['timestamp']))?$timestamp = trim($_POST['timestamp']):$timestamp=NULL;
		// #(isset($_POST['timestamp']))?$timestamp = trim($_POST['timestamp']):$timestamp = date('Y-m-d'); #20211001 latr
		// if (isset($_POST['fortsaet'])) $fortsaet = $_POST['fortsaet'];
		// if (isset($_POST['afbryd'])) $afbryd = $_POST['afbryd'];
		#	}	else {
		#		 $regnskab = "test";
		#		 $brugernavn = "test";
		#		 $password = "test";

		// Sanitize
		
		$brugernavn = isset($_POST['brugernavn']) ? sanitize_input(htmlspecialchars(trim($_POST['brugernavn']), ENT_COMPAT, $charset)) : null;
		$password = isset($_POST['password']) ? sanitize_input(htmlspecialchars(trim($_POST['password']), ENT_COMPAT, $charset)) : null;
		$timestamp = isset($_POST['timestamp']) ? sanitize_input(trim($_POST['timestamp'])) : null;
		$fortsaet = isset($_POST['fortsaet']) ? sanitize_input(trim($_POST['fortsaet'])) : null;
		$afbryd = isset($_POST['afbryd']) ? sanitize_input(trim($_POST['afbryd'])) : null;


	}
	if (isset($_POST['huskmig'])) {
		if ($_POST['huskmig']) setcookie("saldi_huskmig",$_POST['huskmig'].chr(9).$regnskab.chr(9).$brugernavn,time()+60*60*24*365*10);
		else setcookie("saldi_huskmig",$huskmig.chr(9).$regnskab.chr(9).$brugernavn,time()-1);
	}#20211018
	if (isset($_COOKIE['timezone'])) $timezone=$_COOKIE['timezone'];
	if (!isset($timezone)) $timezone='Europe/Copenhagen';
	date_default_timezone_set($timezone);
	$qtxt="select version from regnskab where id='1'"; 
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['version'] >= '3.7.2') {
		$r=db_fetch_array(db_select("select var_value from settings where var_name='timezone'",__FILE__ . " linje " . __LINE__));
		if ($timezone=$r['var_value']) {
			date_default_timezone_set($timezone);
			#setcookie("saldi_timezone",$timezone,time(+60*60*24*365*10));
			setcookie("saldi_timezone",$timezone,time() + (60*60*24*7*10*365));#20211007
		} else {
			date_default_timezone_set('Europe/Copenhagen');
        }
	}
	$unixtime=date("U");
	$r=db_fetch_array(db_select("select * from regnskab where regnskab = '$sqdb'",__FILE__ . " linje " . __LINE__));
	$masterversion=$r["version"];
	$asIs = db_escape_string($regnskab);
	$low = strtolower($regnskab);
	$low = str_replace('Æ','æ',$low);
	$low = str_replace('Ø','ø',$low);
	$low = str_replace('Å','å',$low);
	$low = db_escape_string($low);
	$up = strtoupper($regnskab);
	$up = str_replace('æ','Æ',$up);
	$up = str_replace('ø','Ø',$up);
	$up = str_replace('å','Å',$up);
	$up = db_escape_string($up);

	$qtxt = "select * from regnskab where regnskab = '$asIs' or lower(regnskab) = '$low' or upper(regnskab) = '$up'";
#	$qtxt = "select * from regnskab where regnskab = '$asIs'";
 #	$qtxt.= " or lower(regnskab) = '".db_escape_string(strtolower($regnskab))."'";
 # $qtxt.= " or upper(regnskab) = '".db_escape_string(strtoupper($regnskab))."'";

	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
		$dbuser = trim($r['dbuser']);
		$dbver = trim($r['version']);
		if (isset($r['dbpass'])) $dbpass = trim($r['dbpass']);
		$db         = trim($r['db']);
		$db_id      = trim($r['id']);
		$post_max   = $r['posteringer']*1;
		$bruger_max = $r['brugerantal']*1;	
		$lukket     = trim($r['lukket']);
		if(isset($r['email'] ))  $dbMail = $r['email'];
		if(isset($r['global_id'])){
			  $globalId = $r['global_id'];
			  $_SESSION['globalId']= $globalId; //20241202
		}
		if (!$db) {
			$db=$sqdb;
			db_modify("update regnskab set db='$sqdb' where id='$db_id'",__FILE__ . " linje " . __LINE__);
		}
		if ($lukket) {
			if (!$mastername) $mastername='SALDI';
			if (!$mastertel) $mastertel='+45 4690 2208';
			$fejltxt="Regnskab '$regnskab' er lukket!<br>Ring $mastertel for gen&aring;bning";
			login($regnskab,$brugernavn,$fejltxt);
 			exit;
		}
 		if (isset($afbryd)) {
			login($regnskab,$brugernavn,$fejltxt);
 		}
		$tmp=date("U");
		if ($masterversion > "1.1.3") db_modify("update regnskab set sidst='$tmp' where id = '$db_id'",__FILE__ . " linje " . __LINE__);
	}	else {
/*
		$url = "https://saldi.dk/locator/locator.php?action=getDBlocation&dbAlias=". urlencode($regnskab);
		$result = json_decode(file_get_contents($url));
		if (strpos($result,chr(9))) {
			list ($regnskab,$url) = explode(chr(9),$result);
			$url = "$url/index/login.php";
			print "<form name=\"login\" METHOD=\"POST\" ACTION=\"$url\" onSubmit=\"return handleLogin(this);\">\n";
			print "<input type=\"hidden\" name=\"regnskab\" value=\"$regnskab\">\n";
			print "<input type=\"hidden\" name=\"brugernavn\" value=\"$_POST[brugernavn]\">\n";
			print "<input type=\"hidden\" name=\"password\"  value=\"$_POST[password]\">\n";
			if(isset($_COOKIE['languageId'])){
				print "<input type=\"hidden\" name=\"languageId\"  value=\"$_COOKIE[languageId]\">\n"; #20220330
			}
			print "<input type=\"hidden\" name=\"vent\"  value=\"$_POST[vent]\">\n";
			print "<body onload=\"document.login.submit()\">";
			print "</form>";
			exit;
		}
		if ($regnskab) $fejltxt="Regnskab $regnskab findes ikke";
		login(htmlentities($regnskab,ENT_COMPAT,$charset),htmlentities($brugernavn,ENT_COMPAT,$charset),$fejltxt);
*/
 	}
} else {
	
	#include("../includes/connect.php");#20210929
	login($regnskab,$brugernavn,$fejltxt);
	exit;
}


#######20210930?
if ((!(($regnskab=='test')&&($brugernavn=='test')&&($password=='test')))&&(!(($regnskab=='demo')&&($brugernavn=='admin')))) {#if not admin this blocks seems not to work if brugernavn is different from the sub datatabase
	$udlob=date("U")-36000;
	$x=0;
	$q=db_select("select distinct(brugernavn) from online where brugernavn != '".db_escape_string($brugernavn)."' and db = '$db' and session_id != '$s_id'  and logtime > '$udlob'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$x++;
		$aktiv[$x]=$r['brugernavn'];
	}
	$y=$x+1;
	#	if ($y > $bruger_max) {
	#		$headers = 'From: saldi@saldi.dk'."\r\n".'Reply-To: saldi@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
	#		mail("saldi@saldi.dk", "Brugerantal ($x) overskredet for $regnskab / $db", "$brugernavn logget ind som bruger nr $y.", "$headers");
	#		print "<BODY onLoad=\"javascript:alert('Max antal samtidige brugere ($x) er overskredet.')\">";
	#	}
	$asIs = db_escape_string($brugernavn);
	$low  = mb_strtolower($asIs);
	$up   = mb_strtoupper($asIs);
	$qtxt = "select * from online where (brugernavn='$asIs' or lower(brugernavn)='$low' or upper(brugernavn)='$up') ";
	$qtxt.= "and db = '$db' and session_id != '$s_id'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)){
		$last_time=$r['logtime'];
		if (!$fortsaet && $unixtime - $last_time < 3600) {
			online($regnskab,$db,$userId,$brugernavn,$password,$timestamp,$s_id);
 #			exit;
		} elseif (!$fortsaet) {
			$qtxt = "delete from online where (brugernavn='$asIs' or lower(brugernavn)='$low' or upper(brugernavn)='$up') ";
			$qtxt.= "and db = '$db' and session_id != '$s_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
}
if(isset($_COOKIE['languageId'])) $languageId = $_COOKIE['languageId']; #20220618
else $languageId = 1;
#$qtxt = "select id, var_value from settings where var_name = 'languageId'";
#$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
#($r['var_value'])? $languageId = $r['var_value'] : $languageId = 1;
$spor = null; 
db_modify("delete from online where session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
if ($db && !file_exists("../temp/.ht_$db.log")) {
	$fp=fopen("../temp/.ht_$db.log","a");
	fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$spor."\n");
	fwrite($fp,"\\connect $db;\n");
	fclose ($fp);
}
echo "db $db<br>";
if ($db) {
	$qtxt = "delete from online where (brugernavn='$asIs' or lower(brugernavn)='$low' or upper(brugernavn)='$up') ";
	$qtxt.= "and db = '$db'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "insert into online (session_id, brugernavn, db, dbuser, logtime,language_id) values ";
	$qtxt.= "('$s_id', '".db_escape_string($brugernavn)."', '$db', '$dbuser', '$unixtime','$languageId')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
else db_modify("delete from online where db=''",__FILE__ . " linje " . __LINE__); 
include("../includes/online.php"); #20211115 moved from line 259
# Versions kontrol / opdatering af database.
if ( $db && $db!=$sqdb ) {
	if (!file_exists("../temp/$db")) {
		mkdir("../temp/$db");
	}
	if($db_id > 1) {
		if (!strpos($_SERVER['PHP_SELF'],"stillads") && !strpos($_SERVER['PHP_SELF'],"udvikling") && !strpos($_SERVER['PHP_SELF'],"beta")) {
#			db_modify("update grupper set box3 = '' where art='USET'",__FILE__ . " linje " . __LINE__); #fjernes når topmenu fungerer.
		}
		$qtxt="select box1 from grupper where art = 'VE'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			if (!$dbver || $dbver>$r['box1']) $dbver=$r['box1'];
			include("../includes/connect.php");
			if ($dbver) db_modify("update regnskab set version = '$dbver' where id='$db_id'",__FILE__ . " linje " . __LINE__);
			include("../includes/online.php"); #20211008 moved from line 259
		}
	}
	if ($dbver<$version) {
		tjek4opdat($dbver,$version);
		include("../includes/online.php");
	}
}
#$qtxt = "select id, var_value from settings where var_name = 'languageId'";
#$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
#if ($r['var_value']) $languageId = $r['var_value'];
$userId=NULL;
if (isset ($brug_timestamp)) {
	$qtxt = "select * from brugere where brugernavn='".db_escape_string($brugernavn)."' ";
	$qtxt.= "and (upper(md5('$timestamp' || upper(kode)))=upper('$password'))";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$userId=$r['id'];
} else {
	$asIs = db_escape_string($brugernavn);
	$qtxt = "select * from brugere where brugernavn='$asIs'";
	if ($r  = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$brugernavn = $r['brugernavn'];
		$pw1  = md5($password);
		$pw2  = saldikrypt($r['id'],$password);
	} else {
		$low  = mb_strtolower($asIs);
		$up   = mb_strtoupper($asIs);
		$qtxt = "select * from brugere where lower(brugernavn)='$low' or upper(brugernavn)='$up' limit 1";
		$r  = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$brugernavn = $r['brugernavn'];
		$pw1  = md5($password);
		$pw2  = saldikrypt($r['id'],$password);
	}
	if ($r['kode']==$pw1 || $r['kode']==$pw2) {
		$userId      = $r['id'];
		$rettigheder = trim($r['rettigheder']);
		$regnskabsaar = $r['regnskabsaar'];
		($db != $sqdb)?$ansat_id=$r['ansat_id']*1:$ansat_id=NULL;
	}
	if ($ansat_id && $db!=$sqdb) {
		$r=db_fetch_array(db_select("select * from ansatte where id='$ansat_id'",__FILE__ . " linje " . __LINE__));
		$ansat_grp=$r['gruppe']*1;
		$userMail = $r['email'];
		$r=db_fetch_array(db_select("select box2 from grupper where id='$ansat_grp'",__FILE__ . " linje " . __LINE__));
		$sag_rettigheder=$r['box2'];		
	}
	if (!$userId) {
		$qtxt = "select * from brugere where brugernavn='".db_escape_string($brugernavn)."'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($r['tmp_kode']) {
			list($tidspkt,$tmp_kode)=explode("|",$r['tmp_kode']);
			if (date("U")<=$tidspkt) {
				if ($tmp_kode==$password) {
					$userId=$r['id'];
					$rettigheder=trim($r['rettigheder']); #20150209 + næste 2
					$regnskabsaar=$r['regnskabsaar'];
					$ansat_id=$r['ansat_id']*1;
				} 
			} elseif ($tmp_kode==$password) $fejltxt="Midlertidig adgangskode udløbet";
		}
	}
}
if (!$dbMail && $db && $db != $sqdb) {
	$qtxt = "select email from adresser where art = 'S'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$mainMail = $r['email'];
} else $mainMail = $dbMail;
# Check whether the user exsists
if ($userId) {
	$db_skriv_id=NULL;
	if ($db_type=='mysql') {
		if (!mysql_select_db("$sqdb")) die( "Unable to connect to MySQL");
	} elseif ($db_type=='mysqli') {
		if (!mysqli_select_db($connection,$sqdb)) die( "Unable to connect to MySQLi");
	} else {
		$connection = db_connect ("'$sqhost'", "'$dbuser'", "'$sqpass'", "'$sqdb'", __FILE__ . " linje " . __LINE__);
		if (!$connection) die( "Unable to connect to PostgreSQL");
	}
	include("../includes/connect.php"); #20111105

	# Get 2fa keys for SMS
	$qtxt = "SELECT var_value FROM settings WHERE var_name='nexmo_api_key' AND var_grp='2fa'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if(isset($r["var_value"])) $nexmo_api_key = $r["var_value"]; //20240502 Checks first that it is set before assigning it
	

	$qtxt = "SELECT var_value FROM settings WHERE var_name='nexmo_api_secret' AND var_grp='2fa'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if(isset($r["var_value"])) $nexmo_api_secret = $r["var_value"];


	#	if (($regnskabsaar)&&($db)) {
#		$qtxt = "update online set rettigheder='$rettigheder', regnskabsaar='$regnskabsaar', language_id='$languageId' ";
#		$qtxt.= "where session_id = '$s_id'";
#	} else $qtxt = "update online set rettigheder='$rettigheder', language_id='$languageId' where session_id = '$s_id'";
	
	$qtxt = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS ";
	$qtxt.= "WHERE table_name = 'online' AND column_name = 'rettigheder' ";
	$qtxt.= "AND DATA_TYPE = 'character varying' AND CHARACTER_MAXIMUM_LENGTH < 50";
	if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$qtxt = "ALTER TABLE online ALTER COLUMN rettigheder type varchar(50)";
		db_modify($qtxt, __FILE__ . "linje" . __LINE__);
	}
	$qtxt = "update online set brugernavn = '". db_escape_string($brugernavn) ."', rettigheder='$rettigheder' ";
	if (($regnskabsaar)&&($db)) $qtxt.= ", regnskabsaar='$regnskabsaar'";
	if ($dbver > '4.0.4') $qtxt.= ", language_id='$languageId' ";
	$qtxt.= "where session_id = '$s_id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($login=="cookie") {setcookie("saldi_std",$regnskab,time()+60*60*24*30);}
	include("../includes/online.php"); #20111105

	# ###################################################
	#
	# 2FA SETUP
	#
	# ###################################################
	# If its not an administrator
	#if ($db_id !== 1) {
	if ((int)$db_id !== 1) { // 20240502 Without the integer this always evaluates to true on goods account as admin
		$qtxt = "select email, twofactor, tmp_kode from brugere where id=$userId";
		$r  = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	} else {
		$r = array("twofactor" => FALSE);
	}
echo $db_id;
	if (isset($r["twofactor"])  && isset($tlf_num)) {
		$json_data = file_get_contents('php://input');
		$decoded_data = json_decode($json_data, true);
							       
		$tlf_num = $r["email"];
		$real_code = explode("|",$r["tmp_kode"])[0];
		$real_expire = explode("|",$r["tmp_kode"])[1];
		$status = NULL;

		$code = $_POST['code_1'] . $_POST['code_2'] . $_POST['code_3'] . $_POST['code_4'] . $_POST['code_5'] . $_POST['code_6'];
		if ($code && time() <= $real_expire) {
			if ($real_code == $code) {
				$status = "success";
			} else {
				$status = "Ikke en valid kode, prøv igen";
			}
		}
		if ($bruger_id && $tlf_num) {
		# The code has expired and a new one needs sent
		if (time() > $real_expire && $status !== "success") {
			$status = "En sms er sendt til din telefon +" . substr($tlf_num, 0, 4) . "______";

			# Generate secure random 4 didget integer using urandom
			$urandom = fopen('/dev/urandom', 'rb');
			$seed = fread($urandom, 32);
			fclose($urandom);
			$seed = unpack('L', $seed)[1];
			mt_srand($seed);
			$random_integer = mt_rand(100000, 999999);

			// Initialize cURL session
			$ch = curl_init();

			// Set cURL options
			curl_setopt($ch, CURLOPT_URL, "https://rest.nexmo.com/sms/json");
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
				'from' => 'Saldi',
				'text' => "Din tofaktor kode: $random_integer\nDen er valid i 3 minutter",
				'to' => $r['email'],
				'api_key' => $nexmo_api_key,
				'api_secret' => $nexmo_api_secret
			]));

			// Execute cURL request
			$result = curl_exec($ch);

			// Check for errors
			if ($result === false) {
				echo 'cURL error: ' . curl_error($ch);
			} else {
				$current_time = time();
				$expire = $current_time + 180; // Expires in 3 minutes
				
				db_modify("UPDATE brugere SET tmp_kode='$random_integer|$expire' WHERE id=$bruger_id", __FILE__ . "linje" . __LINE__);

				include("tofaktor.php");
			}
			
			// Close cURL session
			curl_close($ch);
			exit;
		} else if ($status !== "success") {
			include("tofaktor.php");
			exit;
		}
	}
	echo "2fa done";
	# ###################################################
	#
	# 2FA SETUP DONE
	#
	# ###################################################
	}
	if ($post_max && $db!=$sqdb) {
		$r=db_fetch_array(db_select("select box6 from grupper where art = 'RA' and kodenr = '$regnskabsaar'",__FILE__ . " linje " . __LINE__));
		$post_antal=$r['box6']*1;
#		if (($sqdb=="saldi" || $sqdb=="gratis" || $sqdb=="udvikling") && $post_max<=9000 && $post_max < $post_antal ) {
			$diff=$post_antal-$post_max;
			if ($sqdb=="gratis" && $post_antal>$post_max) {
				$alerttxt="Dit maksikale posteringsantal ($post_max) er overskredet.\\nDer er i alt foretaget $post_antal posteringer inden for de sidste 12 m&aring;neder.\\nDu kan bestille et professionelt regnskab p&aring; http://saldi.dk med hotline og automatisk \\nsikkerhedskopiering p&aring; hurtigere systemer, og let flytte hele dit regnskab dertil.\\nEller du kan kontakte DANOSOFT p&aring; tlf 4690 2208 og h&oslash;re om mulighederne for ekstra gratis posteringer.\\n";
			} elseif ($sqdb=="demo" && $post_antal>500) {
				$alerttxt="Dette system er beregnet til demonstration / selvstudie i Saldi og må ikke anvendes kommercielt\\n";
				$alerttxt.="Såfremt du ønsker at anvende systemet kommercielt bedes du venligst oprettet et regnekab på http://saldi.dk\\n";
				print "<BODY onLoad=\"javascript:alert('$alerttxt')\">";
			}
#		}
	}
} else $afbryd=1;
ob_end_flush();	//Sender det "bufferede" output afsted...
#################################################################### *XCK IER DWN 20211094
if(!isset($afbryd)){
	if (file_exists("$db.html")) print "<BODY onLoad=\"JavaScript:window.open('$db.html')\">";
	$db_skriv_id=NULL;
	$fp=fopen("../temp/.ht_online.log","a");
	fwrite($fp,date("Y-m-d")." ".date("H:i:s")." ".getenv("remote_addr")." ".$s_id." ".$regnskab." ".$brugernavn."\n"); #20210902
	fclose($fp);
	if ($regnskab==$sqdb) {
		if ($dbver<$version) tjek4opdat($dbver,$version); #20130919
		print "<meta http-equiv=\"refresh\" content=\"0;URL=admin_menu.php\">";
		exit;
	} else {
		if ($fortsaet) {
			include("../includes/connect.php");
			db_modify("delete from online where brugernavn = '".db_escape_string($brugernavn)."' and db = '$db' and session_id != '$s_id'",__FILE__ . " linje " . __LINE__);
			include("../includes/online.php");
		}
		if (1==1) {
			$url = "https://saldi.dk/locator/locator.php?action=getDBlocation&globalId=$globalId&dbName=$db&dbMail=$mainMail";
			$url.= "&dbAlias=". urlencode($regnskab) ."&dbLocation=$dbLocation&userId=$userId&userName=". urlencode($brugernavn);
			$url.= "&usermail=". urlencode($usermail);;
			$result = file_get_contents($url);
			$a = explode(',',json_decode($result, true));
			if ($a[0] && (!$globalId || (!$dbMail && $mainMail))) {
				$globalId = $a[0];
				include("../includes/connect.php");
					$qtxt = "update regnskab set global_id = '$globalId', email = '$mainMail' where id = '$db_id'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				include("../includes/online.php");
			}
			if ($globalId) {
				$_SESSION['globalId']= $globalId; //20241202
				$qtxt = "select id, var_value from settings where var_grp = 'globals' and var_name = 'globalId'"; 
				if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					if ($r['var_value'] != $globalId) {
						db_modify("update settings set var_value = '$globalId' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
					}
				} else {
					$qtxt="insert into settings (var_grp,var_name,var_value,var_description,user_id) values ";
					$qtxt.="('globals','globalId','$globalId','unique global account Id','0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		}
		#if (substr($rettigheder,5,1)=='1') include("../debitor/rykkertjek.php");
		# Lager status mail
		if (file_exists("../lager/lagerstatusmail.php")) {
			$email = get_settings_value("mail", "lagerstatus", "");
			if ($email) include("../lager/lagerstatusmail.php");
		}
#		transtjek();
		}
		if (file_exists("../utils/rotary_addrsync.php") && is_numeric($regnskab) && !file_exists("../temp/$db/rotary_addrsync.txt")) {
			include("../utils/rotary_addrsync.php");
		}
		if (file_exists("../includes/betweenUpdates.php")) {
			include("../includes/betweenUpdates.php");
		}
		hent_shop_ordrer(0,'');
#if (!$sag_rettigheder&&$rettigheder) print "<meta http-equiv=\"refresh\" content=\"0;URL=sidemenu.php\">";
if (!$sag_rettigheder&&$rettigheder) {
		##########################
	    $restricted=null; #20211018
			$ip = get_ip(); #20211015
			$restricted_user_ip=null;
			$qtxt = "select var_value from settings where var_name = 'RestrictedUserIp'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if($r){ $restricted = explode(chr(9),$r['var_value']);
				for ($x=0; $x<count($restricted);$x++) {
					if ($restricted[$x] == $ip) $restricted_user_ip  = $restricted[$x];
				}
				if ($restricted_user_ip){
					print "<meta http-equiv=\"refresh\" content=\"0;URL=login.php\">";
					exit;
				} 
			}	
		##########################
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/menu.php\">";
	}	elseif (substr($sag_rettigheder,2,1)) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/sager.php\">";
	} elseif (substr($sag_rettigheder,0,1)) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/loen.php\">";
	} else {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=index.php\">";
	}
} else {
	include("../includes/connect.php");
	db_modify("delete from online where session_id='$s_id'",__FILE__ . " linje " . __LINE__);
	include("../includes/std_func.php");
	$fejltxt='Fejl i brugernavn eller adgangskode';
	login($regnskab,$brugernavn,$fejltxt);
#	print "<meta http-equiv=\"refresh\" content=\"0;URL=index.php?regnskab=".htmlentities($regnskab,ENT_COMPAT,$charset)."&navn=".htmlentities($brugernavn,ENT_COMPAT,$charset)."\">";
	exit;
}

function online($regnskab,$db,$userId,$brugernavn,$password,$timestamp,$s_id) {
	global $charset;
	global $sqhost,$squser,$sqpass;
	global $dbuser,$dbpass,$db_type;

	if (!$dbuser) $dbuser = $squser;
	if (!$dbpass) $dbpass = $sqpass;

	if ($db_type=='mysql') {
	if (!mysql_select_db("$db")) die( "Unable to connect to MySQL"); #20170911
	} else {
		$connection = db_connect ("'$sqhost'", "'$dbuser'", "'$dbpass'", "'$db'", __FILE__ . " linje " . __LINE__);
		if (!$connection) die( "Unable to connect to PostgreSQL");
	}
	$r=db_fetch_array(db_select("select * from brugere where brugernavn='".db_escape_string($brugernavn)."'",__FILE__ . " linje " . __LINE__));
	$pw1=md5($password);
	$pw2=saldikrypt($r['id'],$password);
	if ($r['kode']==$pw1 || $r['kode']==$pw2) $pw_ok=1;
	else $pw_ok=0;
	if ($pw_ok) {
		print "<FORM METHOD=POST NAME=\"login\" ACTION=\"login.php\">";
		print "<INPUT TYPE=\"hidden\" NAME=\"regnskab\" VALUE=\"$regnskab\">";
		print "<INPUT TYPE=\"hidden\" NAME=\"brugernavn\" VALUE=\"$brugernavn\">";
		print "<INPUT TYPE=\"hidden\" NAME=\"password\" VALUE=\"$password\">";
		print "<INPUT TYPE=\"hidden\" NAME=\"timestamp\" VALUE=\"$timestamp\">";
		print "<INPUT TYPE=\"hidden\" NAME=\"vent\" VALUE=\"$vent\">";
		print "<table width=50% align=center border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
		print "<tr><td colspan=\"2\" align=\"center\" valign=\"center\"> <big><b>Brugeren <i>$brugernavn</i> er allerede logget ind.</b></big></td></tr>";
		print "<tr><td colspan=\"2\" align=\"center\"> <big><b>Vil du forts&aelig;tte?</b></big></td></tr>";
		print "<tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<td align=\"center\"><INPUT TYPE=\"submit\" name=\"afbryd\" VALUE=\"Afbryd\"></td>";
		print "<td align=\"center\"><INPUT TYPE=\"submit\" name=\"fortsaet\" VALUE=\"Forts&aelig;t\"></td>";
		print "</tr>";
	} else {
		print "<FORM METHOD=POST NAME=\"login\" ACTION=\"index.php\">";
		print "<INPUT TYPE=\"hidden\" NAME=\"regnskab\" VALUE=\"$regnskab\">";
		print "<INPUT TYPE=\"hidden\" NAME=\"brugernavn\" VALUE=\"$brugernavn\">";
		print "<INPUT TYPE=\"hidden\" NAME=\"password\" VALUE=\"$password\">";
		print "<INPUT TYPE=\"hidden\" NAME=\"timestamp\" VALUE=\"$timestamp\">";
		print "<INPUT TYPE=\"hidden\" NAME=\"vent\" VALUE=\"$vent\">";
		print "<INPUT TYPE=\"hidden\" NAME=\"fejltxt\" VALUE=\"Fejl i brugernavn eller adgangskode\">";
#		print "<tr><td colspan=\"2\" align=\"center\" valign=\"center\"> <big><b>Fejl i brugernavn eller adgangskode</b></big></td></tr>";
#		print "<tr>";
#		print "<tr><td><br></td></tr>";
#		print "<tr><td><br></td></tr>";
#		print "<td align=\"center\"><INPUT TYPE=\"submit\" name=\"afbryd\" VALUE=\"Ok\"></td>";
#		print "</tr>";
		print "<body onload=\"document.login.submit()\">\n";
		print "</FORM>";
	}
	exit;
}

function login($regnskab,$brugernavn,$fejltxt) {

	$timestamp = time(); //unix timestamp
	#if (isset($_POST['vent'])) $vent=$_POST['vent'];
	if (isset($_POST['vent'])) { #20250314
		if (strlen($_POST['vent']) > 80) {
			// Sanitize the message by encoding any special characters
			$message = htmlspecialchars("Input for vent is too long.", ENT_QUOTES, 'UTF-8');
			echo "<script nonce='{$nonce}'>alert('$message');</script>";
		
			echo "<script nonce='{$nonce}'>window.location.href = 'index.php';</script>";
        	exit;
		}
		$vent = htmlspecialchars($_POST['vent'], ENT_QUOTES, 'UTF-8'); 
	}
	if (!$vent) $vent=0;
	sleep($vent);
	$vent*=2;
	if (!$vent) $vent=2;
	print "<form NAME=\"login\" ACTION=\"index.php\" METHOD=\"POST\">\n";
	print "<INPUT TYPE=\"hidden\" NAME=\"regnskab\" VALUE=\"$regnskab\">\n";
	print "<INPUT TYPE=\"hidden\" NAME=\"brugernavn\" VALUE=\"$brugernavn\">\n";
	print "<INPUT TYPE=\"hidden\" NAME=\"fejltxt\" VALUE=\"$fejltxt\">";
	print "<INPUT TYPE=\"hidden\" NAME=\"timestamp\" VALUE=\"$timestamp\">\n";
	print "<INPUT TYPE=\"hidden\" NAME=\"vent\" VALUE=\"$vent\">\n";
	print "</form>\n";
#	exit;
	print "<body onload=\"document.login.submit()\">\n";
	#print "<meta http-equiv=\"refresh\" content=\"0;url=index.php?regnskab=$regnskab&navn=$brugernavn\">";
	exit;
	global $charset;
	global $version;

	include("../includes/std_func.php");

		// Define a function to sanitize input by removing special characters # 20250314
		function sanitize_input($input) {
			// Trim the input to remove any leading/trailing whitespace
			$input = trim($input);
			
			// Remove any special characters that might lead to SQL injection
			$input = preg_replace('/[^\w\s\-]/', '', $input);
			
			if (strlen($input) > 80) {
				return false;
			}
			
			return $input;
		}

		// Sanitize
		if (isset($_GET['navn'])) {
			$navn = sanitize_input(htmlspecialchars($_GET['navn'], ENT_COMPAT, $charset));
		}

		if (isset($_GET['brugernavn'])) {
			$brugernavn = sanitize_input(htmlspecialchars($_GET['brugernavn'], ENT_COMPAT, $charset));
		}

		if (isset($_GET['regnskab'])) {
			$regnskab = sanitize_input(htmlspecialchars($_GET['regnskab'], ENT_COMPAT, $charset));
		}

		if (isset($_GET['tlf'])) {
			$tlf = sanitize_input($_GET['tlf']);
		}
		
	if (isset($brug_timestamp)) {
		?>
		<script language="javascript" type="text/javascript" src="../javascript/md5.js"></script>

		<script language="javascript" type="text/javascript">
			function handleLogin (loginForm) {
				var inputTimestamp = loginForm.timestamp.value;
				var inputPassword = loginForm.password.value;

				loginForm.password.value = hex_md5(inputTimestamp+hex_md5(inputPassword));
				return true;
			}
		</script>
		<?php
	}
#	if ($db_encode=="UTF8") $charset="UTF-8";
#	else $charset="ISO-8859-1";
	if (file_exists("../doc/vejledning.pdf")) $vejledning="../doc/vejledning.pdf";
	else $vejledning="http://saldi.dk/dok/komigang.html";

	PRINT "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n
	<html>\n
	<head><title>$title</title>";
	if ($css) PRINT "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\">";
	print "<meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\"></head>\n";
	print "<body><table style=\"width:100%;height:100%;\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";# Tabel 1 ->
	print "<tr><td align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; #Tabel 1.1 ->
	print "<tr><td  style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" width=\"45%\"> Ver $version</td>";
	print "<td style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;;background:url(../img/grey1.gif)\" width=\"10%\" align = \"center\"> <a href=\"$vejledning\" target=\"_blank\">Vejledning</a></td>\n";
	print "<td style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;;background:url(../img/grey1.gif)\" width=\"45%\" align = \"right\">&nbsp;</td></tr>\n";
	print "</tbody></table></td></tr><tr><td align=\"center\" valign=\"middle\">\n"; # <- tabel 1.1 slut
	print "<table width=\"350\" align=\"center\" border=\"5\" cellspacing=\"5\" cellpadding=\"5\"><tbody>"; # tabel 1.2 ->
	print "<tr><td><FORM name=\"login\" METHOD=\"POST\" ACTION=\"login.php\" onSubmit=\"return handleLogin(this);\"><table width=\"100%\" align=center border=\"0\" cellspacing=\"0\" cellpadding=\"1\"><tbody>"; # tabel 1.2.1 ->
	sleep($vent);
	if (isset($mastername)&&$mastername) $tmp="<big><big><big><b>$mastername</b></big></big></big>";   
	elseif (strpos($_SERVER['PHP_SELF'],"beta")) $tmp="<big><big><big><b>!!! BETA !!!</b></big></big></big>";
	else $tmp="<big><big><big><b>SALDI</b></big></big></big>";
	print "<tr><td colspan=\"2\">";
	print "<table width=\"100%\"><tbody><tr><td width=\"10%\">"; # tabel 1.2.1.1 ->
	print "";
	if (file_exists("../img/logo.png")) print "<img style=\"border:0px solid;width:50px;heigth:50px\" alt=\"\" src=\"../img/logo.png\">";
	print "</td><td width=\"80%\" align=\"center\">$tmp</td><td width=\"10%\" align=\"right\">";
	if (file_exists("../img/logo.png")) print "<img style=\"border:0px solid;width:50px;heigth:50px\" alt=\"\" src=\"../img/logo.png\"></td></tr>\n";
	print "</tbody></table></td></tr>"; # <- tabel 1.2.1.1
	print "<tr><td colspan=\"2\"><hr></td></tr>\n";
	print "<tr><td>".findtekst(322,$sprog_id)."</td>";
	print "<td width=\"2%\">";
	if ($login=="dropdown") {
		print "<select name=regnskab>";
		$query = db_select("select regnskab from regnskab order by regnskab asc",__FILE__ . " linje " . __LINE__);
		if (db_num_rows($query)==0)	{
			print "<option>Ingen regnskaber oprettet</option>";
			} else {
				while ($r = db_fetch_array($query))
				print "<option>".$r['regnskab']."</option>";
				print "</select>";
		}
	}
	if (($login=="cookie")&&(!$navn)){
		if (isset($_COOKIE['saldi_regnskab'])) {
			$regnskab=$_COOKIE['saldi_regnskab'];
		}
	}
	print "<input class=\"inputbox\" style=\"width:160px\" type=\"TEXT\" NAME=\"regnskab\" value=\"$regnskab\">";
	print "</tr><tr><td>".findtekst(323,$sprog_id)."</td><td><INPUT class=\"inputbox\" style=\"width:160px\" TYPE=\"TEXT\" NAME=\"login\" value=\"$navn\"></td></tr>\n";
	print "<tr><td>".findtekst(324,$sprog_id)."</td>";
	print	"<td><INPUT class=\"inputbox\" style=\"width:160px\" TYPE=\"password\" NAME=\"password\" value=\"$kode\"></td></tr>\n";
	print "<tr><td colspan=\"2\" align=\"center\"><br></td></tr>\n";
	print "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"pwtjek\" value=\"Login\"></td></tr>\n";
	if (isset($mastername) && strtolower($mastername)=='rotary') {
		print "<tr><td colspan=\"2\" align=center>".findtekst(325,$sprog_id)."</td></tr>\n";
	}
	print "</tbody></table><INPUT TYPE=\"HIDDEN\" name=\"timestamp\" value=\"".date("U")."\"></FORM></td></tr>\n"; # <- tabel 1.2.1
	print	"</tbody></table></td></tr>\n"; # <- tabel 1.2
#	print "<tr><td align=\"center\" valign=\"bottom\">";
#	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.3 ->
#	print "<td width=\"20%\" style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" align=\"left\">&nbsp;Copyright&nbsp;&copy;&nbsp;2003-2012&nbsp;DANOSOFT&nbsp;ApS</td>";
#	print "<td width=\"60%\" style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" align=\"center\">Et <a href=\"http://www.saldi.dk\" target=\"blank\">SALDI</a> regnskab</td>";
#	print "<td width=\"20%\" style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" align=\"left\"><br></td>";
#	print "</tr></tbody></table>"; # <- tabel 1.3
#	print "</td></tr>\n";
#	print "</tbody></table>"; # <- tabel 1
	if (!isset($_COOKIE['saldi_std'])) {
		print "<script language=\"javascript\" type=\"text/javascript\">";
		print "document.login.regnskab.focus();";
		print "</script>";
	} else {
		print "<script language=\"javascript\" type=\"text/javascript\">";
		print "document.login.login.focus();";
		print "</script>";
	}
}

?>
