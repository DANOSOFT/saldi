<?php
@session_start();
$s_id=session_id();

// -----------index/logud.php------lap 3.3.4------2013.10.10--------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2013 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20131010 Tilføjet sletning af lås fra kladdeliste.
// 20150114 PK - Tilføjet session_unset,session_destroy, som tømmer alle sessions variabler

$title="logud";
include("../includes/connect.php");
include("../includes/online.php");
if ($db && $db!=$sqdb) {
	db_modify("update ordrer set tidspkt='' where hvem = '".db_escape_string($brugernavn)."' and status < '3'",__FILE__ . " linje " . __LINE__);
	db_modify("update kladdeliste set tidspkt='' where hvem = '".db_escape_string($brugernavn)."' and bogfort != 'V'",__FILE__ . " linje " . __LINE__);
}
include("../includes/connect.php");
$r=db_fetch_array(db_select("select * from online where session_id = '$s_id'",__FILE__ . " linje " . __LINE__));
if ($r['revisor']) {
	if ($db && $db!=$sqdb) {
		db_modify("update online set db='$sqdb' where session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL='../admin/vis_regnskaber.php'\">";
		exit;
	}	
}
db_modify("delete from online where session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
session_unset();
session_destroy();
print "<meta http-equiv=\"refresh\" content=\"0;URL='../index/index.php'\">";
exit;
  
?>
