<?php
// ------------------debitoripad/choose.php--------lap 3.2.9----2024.06.07--
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

@session_start();
$s_id=session_id();

$css="../css/debitoripad.css";

$title="batch";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/db_query.php");
include("../includes/std_func.php");

$ordreid = $_GET['id'];
$debitoripad = $_GET['debitoripad'];
if (isset($debitoripad)) {
	echo "Done";
	if ($debitoripad != "") {
		db_modify("UPDATE ordrer SET email='$debitoripad' WHERE id = '$ordreid'",__FILE__ . " linje " . __LINE__);
	}
	header("Location: ../debitor/ordre.php?id=$ordreid");
	die();
}


print '<div id="chooseform"><form>';
print "<input name='id' value='$ordreid' hidden></input>";
print '<select id="debitoripad" name="debitoripad">';
$q = db_select("select brugernavn from brugere where brugernavn like 'debitoripad%' order by brugernavn",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	print "<option value='$r[brugernavn]'>$r[brugernavn]</option>";
}
print '</select>';
print '<br>';
print '<button>Go</button>';
print "<button onclick=\"event.preventDefault(); window.location.href = '?id=$ordreid&debitoripad='\">Anuller</button>";
print '</form></div>';


?>
