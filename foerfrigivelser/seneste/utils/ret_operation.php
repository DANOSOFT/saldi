<?php
session_start();
$s_id=session_id();

# include("../tidsreg/include/tabel.inc");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");


# mysql_connect("localhost","root","");

$q1=db_select("SELECT * from varer where varenr like 'O%' order by varenr");
while($r1 = db_fetch_array($q1)) {
	$tmp=str_replace('O','',$r1['varenr']);
	$tmp2=$tmp*1;
	echo "$tmp : $tmp2 : ";
	if ("$tmp" == "$tmp2") echo "Varenr: $r1[varenr]";	
	echo "<br>";
	db_query("update varer set komplementaer='$r1[komplementaer]', cirkulate='$r1[cirkulate]', pris='$r1[cirkulate]' where varenr='$r1[gruppe]'");
}

/*
$overskrifter=array("Prodnavn","Nummer","Navn");
$nyside="ordre.php";
tabeller($operation1,$overskrifter,"ja",$nyside,"ja");
*/

?>

