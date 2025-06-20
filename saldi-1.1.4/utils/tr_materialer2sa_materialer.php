<?php
session_start();
$s_id=session_id();

// -------------------------tidsreg/tr_materialer2sa_materialer.php---ver. 1.1.0 -------------------------
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
// Copyright (c) 2000-2007 A. B. Jensens Maskinfabrik A/S
// ----------------------------------------------------------------------


# include("../tidsreg/include/tabel.inc");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");

$link=mysql_connect("localhost","root","");
$x=0;
$q1=mysql_db_query("abjensen", "SELECT * from materiale order by materialenummer", $link);
if ($q1) {
	while($r1 = mysql_fetch_array($q1)) {
		$x++;
		$materialenr[$x]=$r1['materialenummer'];
		$enhed[$x]=$r1['enhed'];
		$beskrivelse[$x]=$r1['materiale'];
		$densitet[$x]=$r1['massefylde'];
		$tykkelse[$x]=$r1['tykkelse'];
		$kgpris[$x]=$r1['kgpris'];
		$avance[$x]=$r1['avance'];
		$opdat_date[$x]=date("Y-m-d",$r1['opdateret_sidst']);
		$opdat_time[$x]=date("H:i:s",$r1['opdateret_sidst']);
	}
	$antal=$x;
}
for ($x=1;$x<=$antal; $x++) {
	echo "insert into materialer (materialenr, enhed, beskrivelse, densitet, tykkelse, kgpris, avance, opdat_date, opdat_time) values ($materialenr[$x], $enhed[$x], $beskrivelse[$x], $densitet[$x], $tykkelse[$x], $kgpris[$x], $avance[$x], $opdat_date[$x], $opdat_time[$x])<br>;"; 
	db_modify("insert into materialer (materialenr, enhed, beskrivelse, densitet, tykkelse, kgpris, avance, opdat_date, opdat_time) values ('$materialenr[$x]', '$enhed[$x]', '$beskrivelse[$x]', '$densitet[$x]', '$tykkelse[$x]', '$kgpris[$x]', '$avance[$x]', '$opdat_date[$x]', '$opdat_time[$x]')");
}
/*
$overskrifter=array("Prodnavn","Nummer","Navn");
$nyside="ordre.php";
tabeller($operation1,$overskrifter,"ja",$nyside,"ja");
*/

?>

