<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------/admin/aaben_regnskab.php-----lap 3.8.9 ------20209-03-08--------
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
//
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------
// 2015.01.04 Initerer variablen $nextver så den bypasser versionskontrol i online.php
// 2018.11.07 Rettet stavefejl i variablen $regnskabsaar linje 63
// 2020.03.08 PHR A lot of changes regarding MySQLi and handling missing or empty databases.


@session_start();
$s_id=session_id();

$css="../css/standard.css";
$title="Aaben regnskab";
$nextver=NULL;
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/version.php");
include("../includes/tjek4opdat.php");

if ($db != $sqdb) {
	print "<BODY onLoad=\"javascript:alert('Hmm du har vist ikke noget at g&oslash;re her! Dit IP nummer, brugernavn og regnskab er registreret!')\">";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">";
	exit;
}

$tmp_db_id=if_isset($_GET['db_id']);

$qtxt="select db from regnskab where id = '$tmp_db_id'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$tmp_db=$r['db'];
if (!db_exists($tmp_db)) {
	print "<center><br><br>";
	print "<center>Regnskab med ID $tmp_db_id eksisterer ikke<br><br>";
	print "<a href='vis_regnskaber.php'>Tilbage til oversigt</a><br><br>";
	print "<a href='restore.php?db=$tmp_db'>Indlæs sikkerhedskopi</a><br><br>";
	exit;
} else {
	$qtxt="select regnskabsaar from revisor where db_id = '$tmp_db_id' and brugernavn= '$brugernavn'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$regnskabsaar=$r['regnskabsaar'];	
	} else {
		$regnskabsaar='0';
		$qtxt="insert into revisor (db_id, brugernavn, regnskabsaar) values ('$tmp_db_id', '$brugernavn', '$regnskabsaar')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$db=$tmp_db;
	$db_id=$tmp_db_id;	
}
$connection=db_connect($sqhost,$squser,$sqpass,$tmp_db);
if (!tbl_exists('grupper')) {
	print "<center><br><br>";
	print "<center>Regnskab med ID $tmp_db_id mangler indhold<br><br>";
	print "<a href='vis_regnskaber.php'>Tilbage til oversigt</a><br><br>";
	print "<a href='restore.php?db=$tmp_db'>Indlæs sikkerhedskopi</a><br><br>";
	exit;
}
include("../includes/connect.php");
$qtxt = "update online set db='$tmp_db', brugernavn='$brugernavn', regnskabsaar='$regnskabsaar', revisor='1',"; 
$qtxt.= "rettigheder='111111111111111111111' where session_id='$s_id'";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);
include("../includes/online.php");
if (!$regnskabsaar) {
	$qtxt="select MAX(kodenr) as regnskabsaar from grupper where art = 'RA'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$regnskabsaar=$r['regnskabsaar']*1;
	include("../includes/connect.php");
	db_modify("update online set regnskabsaar='$regnskabsaar' where session_id='$s_id'",__FILE__ . " linje " . __LINE__);
	include("../includes/online.php");
}
$qtxt="select box1 from grupper where art = 'VE'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$dbver=$r['box1'];
$tmp = str_replace(".",";",$dbver);		
list($a, $b, $c)=explode(";", trim($tmp));
if ($dbver<$version) tjek4opdat($dbver,$version);	
print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/menu.php\">";

?>
</tbody></table>
</body></html>
