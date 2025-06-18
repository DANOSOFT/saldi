<?php
@session_start();
$s_id=session_id();

// -----------index/logud.php------ ver 4.0.8 --- 2024-04-09 -------------
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
// Copyright (c) 2003-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 20131010 Tilføjet sletning af lås fra kladdeliste.
// 20150114 PK - Tilføjet session_unset,session_destroy, som tømmer alle sessions variabler
// 20230804	LOE Minor modification
// 20250211 Initialize $css to fix undefined variable in online.php

$title="logud";
$css = "";

include("../includes/connect.php");
include("../includes/online.php");
if ($db && $db!=$sqdb) {
	db_modify("update ordrer set tidspkt='' where hvem = '".db_escape_string($brugernavn)."' and status < '3'",__FILE__ . " linje " . __LINE__);
	db_modify("update kladdeliste set tidspkt='' where hvem = '".db_escape_string($brugernavn)."' and bogfort != 'V'",__FILE__ . " linje " . __LINE__);
}
include("../includes/connect.php");
$r=db_fetch_array(db_select("select * from online where session_id = '$s_id'",__FILE__ . " linje " . __LINE__));
if (isset($r['revisor'])) {
	if ($db && $db!=$sqdb) {
		db_modify("update online set db='$sqdb' where session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL='../admin/vis_regnskaber.php'\">";
		exit;
	}	
}
db_modify("delete from online where session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
session_unset();
session_destroy();
if(isset($_COOKIE["saldi_std"])) {
	unset($_COOKIE["saldi_std"]);
}
if(isset($_COOKIE["saldi_pfs"])){
	unset($_COOKIE["saldi_pfs"]);
}
#echo "Good bye";
echo "<script>if (window !== window.parent) {
	window.parent.postMessage('logud', '*');
}</script>";
print "<meta http-equiv=\"refresh\" content=\"0;URL='../index/index.php'\">";
exit;
#echo "<script>if (window !== window.parent) {
#	parent.postMessage('logud', '*');
#}</script>";
#print "<meta http-equiv=\"refresh\" content=\"0;URL='../index/index.php'\">";
#exit;
  
?>
