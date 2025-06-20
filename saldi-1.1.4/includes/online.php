<?php
// ---------------------includes/online.php----lap 1.1.3---18.01.08 ---
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
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------
 ini_set("display_errors", "0");

$query = db_select("select * from online where session_id = '$s_id'");
if ($row = db_fetch_array($query)) {
	$dbuser = trim($row['dbuser']);
	$db	= trim($row['db']);
	$regnaar = trim($row['regnskabsaar']);
	$brugernavn = addslashes(trim($row['brugernavn']));
	$unixtime=date("U");
	if ($row['logtime']) db_modify("update online set logtime = '$unixtime' where session_id = '$s_id'");
}
else {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/index.php\">";
	exit;
}
$query = db_select("select id, regnskab from regnskab where db = '$db'");
if ($row = db_fetch_array($query)) {
	$db_id = $row['id'];
	$regnskab = $row['regnskab'];
}
if ($db!=$sqdb) {
	$connection = db_connect ("host='$sqhost' dbname='$db' user='$dbuser' password='$sqpass'");
	if (!$connection) die( "Unable to connect to SQL");
	else {	
		$query = db_select("select * from brugere where brugernavn= '$brugernavn'");
		if ($row = db_fetch_array($query)) {
			$bruger_id=$row['id'];
			$rettigheder = trim($row['rettigheder']);
			$ansat_id=$row['ansat_id'];
		}
		elseif (($rettigheder)&&($modulnr)&&(substr($rettigheder,$modulnr,1)!='1')) { 
			exit;
		}
		$query = db_select("select id from grupper where art = 'RA'");
		if ((!db_fetch_array($query))&&($modulnr!=2)&&(!$online)){
			if ($modulnr) {
				print "<BODY onLoad=\"JavaScript:alert('Der er ikke oprettet nogen regnskabs&aring;r');regnaar=window.open('../systemdata/regnskabsaar.php' , 'regnaar' , ',scrollbars=yes, resizable=yes,alwaysRaised=yes');regnaar.focus();window.close();\">";
				exit;
			}
		}
	}
}

?>
	 
