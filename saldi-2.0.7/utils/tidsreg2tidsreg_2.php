<?php
session_start();
$s_id=session_id();

# include("../tidsreg/include/tabel.inc");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");

if ($_GET[max]) $max=$_GET['max'];
if ($_GET[start]) $start=$_GET['start'];
else $start=0;
$slut=$start+5000;
$tmp=$start;
echo "Opdaterer tidsreg poster $start til $slut<br>";

$link=mysql_connect("localhost","root","");
if (!$max) {
	$q1=mysql_db_query("abjensen", "SELECT MAX(loebenummer) as max  from tidsreg", $link);
	if ($q1) $r1 = mysql_fetch_array($q1); 
	$max=$r1[max];
}

$q1=mysql_db_query("abjensen", "SELECT loebenummer, tid from tidsreg where loebenummer >= $start and loebenummer <= $slut  order by loebenummer", $link);
if ($q1) {
	while($r1 = mysql_fetch_array($q1)) {
		db_modify ("update tidsreg set tid = $r1[tid] where id = $r1[loebenummer]");		
		$tmp=$r1[loebenummer];
	}
} else echo "Ingen data fundet at importere<br>";
if ($tmp < $max) print "<meta http-equiv=\"refresh\" content=\"0;URL=tidsreg2tidsreg_2.php?start=$tmp&max=$max\">";
else echo "Tidsreg import afsluttet med succes<br>";
/*
$overskrifter=array("Prodnavn","Nummer","Navn");
$nyside="ordre.php";
tabeller($operation1,$overskrifter,"ja",$nyside,"ja");
*/

?>

