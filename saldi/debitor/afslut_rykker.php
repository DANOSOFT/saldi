<?php #topkode_start

// -----------------------debitor/afslut_rykker.php-----lap 2.1.0--2009.10.12-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaetelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$topniveau=NULL;
$rykker_id=array();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/openpost.php");

$konto_antal=$_GET['kontoantal'];
if (isset($_GET['rykker_id'])) $rykker_id=explode(";", $_GET['rykker_id']);
else $rykker_id=NULL;
$antal=count($rykker_id);
for ($x=0;$x<$antal;$x++) {
#echo "update ordrer set betalt='on' where id = '$rykker_id[$x]'<br>";	
	db_modify("update ordrer set betalt='on' where id = '$rykker_id[$x]'",__FILE__ . " linje " . __LINE__);
}
print "<body onload=\"javascript:window.opener.focus();window.close();\">";

?>