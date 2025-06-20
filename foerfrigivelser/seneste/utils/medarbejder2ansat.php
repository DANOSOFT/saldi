<?php
session_start();
$s_id=session_id();

# include("../tidsreg/include/tabel.inc");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");

$link=mysql_connect("localhost","root","");
if ($r=db_fetch_array(db_select("SELECT id from adresser where art = 'S'"))) $konto_id=$r['id'];
else {
	echo "Der er ikke opretet stamtata - medarbejdere kan ikke importeres<br>";   
	exit;
}
$q1=mysql_db_query("abjensen", "SELECT * from medarbejder order by personnummer", $link);
if ($q1) {
	while($r1 = mysql_fetch_array($q1)) {
		if ($r1['aktiv']==1) {
			$navn=$r1['navn'];
			if ($r1[hold]=='ja') $hold = 1;
			else $hold = 0;
echo "insert into ansatte (nummer, navn, loen, hold) values ('$r1[personnummer]', '$navn', '$r1[loen]', '$hold'<br>";		
			db_modify("insert into ansatte (nummer, navn, loen, hold) values ('$r1[personnummer]', '$navn', '$r1[loen]', '$hold')");
		}
	}
} else 
echo "Ingen data fundet at importere<br>";
/*
$overskrifter=array("Prodnavn","Nummer","Navn");
$nyside="ordre.php";
tabeller($operation1,$overskrifter,"ja",$nyside,"ja");
*/

?>

