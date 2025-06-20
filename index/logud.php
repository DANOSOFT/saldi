<?php
@session_start();
$s_id=session_id();

// ------------------------------------index/logud.php------lap 3.2.0------2011.05.18--------
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
// Copyright (c) 2003-2011 DANOSOFT ApS
// ----------------------------------------------------------------------


include("../includes/connect.php");
include("../includes/online.php");
if ($db != $sqdb) db_modify("update ordrer set tidspkt='' where hvem = '$brugernavn' and status < '3'",__FILE__ . " linje " . __LINE__);
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
print "<meta http-equiv=\"refresh\" content=\"0;URL='../index/index.php'\">";
exit;
  
?>
