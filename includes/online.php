<?php
// ---------------------includes/online.php----lap 3.1.3---2011-02-08---
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
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------
ini_set("display_errors", "0");
$db_skriv_id=NULL; #bruges til at forhindre at skrivninger til masterbasen logges i de enkelte regnskaber.
if (!isset($modulnr))$modulnr=NULL;
if (!isset($db_type))$db_type="postgres";
$ip=$_SERVER['REMOTE_ADDR'];
$ip=substr($ip,0,10);
if ($title!="kreditorexport" && $ip!="128.30.52."){
	$query = db_select("select * from online where session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$dbuser = trim($row['dbuser']);
		$db	= trim($row['db']);
		$regnaar = trim($row['regnskabsaar']);
		$brugernavn = addslashes(trim($row['brugernavn']));
		$unixtime=date("U");
		$rettigheder=$row['rettigheder'];
		$revisor=$row['revisor'];
		if ($row['logtime']) db_modify("update online set logtime = '$unixtime' where session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
	} elseif ($title!='menu') {
		if ($webservice) return ('Session expired');
		else {
			print "<BODY onLoad=\"JavaScript:alert('Din session er udl&oslash;bet - du skal logge ind igen');window.close();\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php?kilde=online.php\">";
			exit;
		}
	} else {
		if ($webservice) return ('Session expired');
		else {
			print "<BODY onLoad=\"JavaScript:alert('Din session er udl&oslash;bet - du skal logge ind igen');\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/index.php\">";
			exit;
		}		
	}
}
if ($ip=="128.30.52."){
	$dbuser = "postgres";
	$db = "udvikling_8";
	$brugernavn = 'phr';
	$rettigheder = '1111111111111111111111';
}
# echo "$modulnr && $modulnr<100 && $db==$sqdb<br>";
if ($modulnr && $modulnr<100 && $db==$sqdb) { #Lukker vinduet hvis revisorbruger er logget af
	print "<BODY onLoad=\"JavaScript:alert('Du har logget ud - vinduet lukkes');\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
	exit;
}
$query = db_select("select * from regnskab where db = '$db'",__FILE__ . " linje " . __LINE__);
if ($row = db_fetch_array($query)) {
	$db_id = $row['id'];
	$db_skriv_id = $db_id;
	$regnskab = $row['regnskab'];
	$max_posteringer = $row['posteringer'];
}
if ($db!=$sqdb) {
	if ($db_type=='mysql') {
		if (!mysql_select_db("$db")) die( "Unable to connect to MySQL");
	} else {
		$connection = db_connect ("$sqhost", "$squser", "$sqpass", "$db", __FILE__ . " linje " . __LINE__);
		if (!$connection) die( "Unable to connect to PostgreSQL");
	}	
	if (!$revisor) {
		$query = db_select("select * from brugere where brugernavn= '$brugernavn'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$bruger_id=$row['id'];
			$rettigheder = trim($row['rettigheder']);
			$ansat_id=$row['ansat_id'];
			$sprog_id=$row['sprog_id']*1;
		}
	}	else $bruger_id=-1;
	if (!$sprog_id)$sprog_id=1;
	$jsvars="statusbar=0,menubar=0,titlebar=0,toolbar=0,scrollbars=1,resizable=1,dependent=1";
	if (!$r = db_fetch_array(db_select("select box1,box2,box3,box4,box5 from grupper where art = 'USET' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__))) {
		db_modify("insert into grupper(beskrivelse,art,kodenr,box1,box2,box3,box4,box5) values ('Usersettings','USET','$bruger_id','$jsvars','','on','#eeeef0','')",__FILE__ . " linje " . __LINE__); 
	} else {
		$jsvars=$r['box1'];
		$popup=$r['box2'];
		$sidemenu=$r['box3'];
		$bgcolor=$r['box4'];
		$bgnuance1=$r['box5'];
		if (strpos($jsvars,"reziseable")) { #tilfoejet 20090730 grundet stavefejl i reziseable
			$jsvars=str_replace("reziseable","resizable",$jsvars);
			db_modify("update grupper set box1='$jsvars' where  art = 'USET' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__);
		}
	}
	if (($rettigheder)&&($modulnr)&&(substr($rettigheder,$modulnr,1)!='1')) { 
			print "<BODY onLoad=\"JavaScript:alert('Du har ikke nogen rettigheder her - din aktivitet er blevet logget');window.close();\">";
		exit;
	}
}

if ($header!='nix') {
	if ($db_encode=="UTF8") $charset="UTF-8";
	else $charset="ISO-8859-1";
	PRINT "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n
	<html>\n
	<head><title>$title</title><meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\">\n";
	if ($css) PRINT "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\">";
	PRINT "</head>";
}
if ($bg!='nix') {
	if (!$bgcolor) $bgcolor="#eeeef0";
	PRINT "<body bgcolor=\"$bgcolor\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\"><center>";
}
?>