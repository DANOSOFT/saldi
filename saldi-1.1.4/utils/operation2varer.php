<?php
session_start();
$s_id=session_id();

# include("../tidsreg/include/tabel.inc");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");

if ($r1=db_fetch_array(db_select("select kodenr from grupper where art='VG' and box10='on'"))) {

	$gruppe=$r1['kodenr'];

	$link=mysql_connect("localhost","root","");
	$q1=mysql_db_query("abjensen", "SELECT * from operation order by gruppe", $link);
	if ($q1) {
		while($r1 = mysql_fetch_array($q1)) {
			if ($r1[gruppe]) {
				echo "update varer set komplementaer='$r1[komplementaer]', circulate='$r1[circulate]', pris='$r1[pris]', gruppe='$gruppe', operationsnr='$r1[operationsnummer]' where varenr='$r1[gruppe]'<br>";
				db_modify("update varer set komplementaer='$r1[komplementaer]', circulate='$r1[circulate]', kostpris='$r1[pris]', gruppe='$gruppe', operationsnr='$r1[operationsnummer]' where varenr='$r1[gruppe]'");
			}
		}
	}
}
/*
$overskrifter=array("Prodnavn","Nummer","Navn");
$nyside="ordre.php";
tabeller($operation1,$overskrifter,"ja",$nyside,"ja");
*/

?>

