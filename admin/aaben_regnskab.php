<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- admin/aaben_regnskab.php --- lap 4.0.9 --- 2023-11-03 ---
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 2015.01.04 Initerer variablen $nextver så den bypasser versionskontrol i online.php
// 2018.11.07 Rettet stavefejl i variablen $regnskabsaar linje 63
// 2020.03.08 PHR A lot of changes regarding MySQLi and handling missing or empty databases.
// 2020.02.22 PHR Added call to locator and added global_id;
// 2020.03.11 PHR Added call to betweenUpdates and added global_id to table regnskab if not exist;
// 2023.11.03 PHR Added call to online.php after tjek4opdat

@session_start();
$s_id=session_id();

$css="../css/standard.css";
$title="Aaben regnskab";
$nextver= $globalId = NULL;
		
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

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='regnskab' and column_name='global_id'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER TABLE regnskab ADD column global_id int default 0",__FILE__ . " linje " . __LINE__);
}
$qtxt = "select id, regnskab, global_id from regnskab where id = '$tmp_db_id'"; 
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
if ($r['id']) {
	$dbLocation="://".$_SERVER['SERVER_NAME'].=$_SERVER['PHP_SELF'];
	$dbLocation=str_replace("/admin/aaben_regnskab.php","",$dbLocation);
	if ($_SERVER['HTTPS']) $dbLocation="s".$dbLocation;
	$dbLocation="http".$dbLocation;
	$dbAlias=urlencode($r['regnskab']);
	$url = "https://saldi.dk/locator/locator.php?action=getDBlocation&dbAlias=$dbAlias&globalId=$r[global_id]";
	$url.= "&dbName=$tmp_db&dbLocation=$dbLocation";
	$result = file_get_contents($url);
	$a = explode(',',json_decode($result, true));
	if ($a[0] && !$globalId) {
		include("../includes/connect.php");
		if ($a[0] && !$r['global_id']) $qtxt = "update regnskab set global_id = '$a[0]' where id = '$db_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		include("../includes/online.php"); 
	}
}

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
if ($dbver<$version) {
	tjek4opdat($dbver,$version);
	include("../includes/online.php");
}
if (file_exists("../includes/betweenUpdates.php")) {
	include("../includes/betweenUpdates.php");
}
print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/menu.php\">";

?>
</tbody></table>
</body></html>
