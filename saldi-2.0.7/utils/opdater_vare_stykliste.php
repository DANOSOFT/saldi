<?php
@session_start();
$s_id=session_id();

if (isset($_GET['start'])) $start=$_GET['start'];
else $start=0;
$slut=$start+10000;

echo "opdater $start til $slut<br>";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/usdecimal.php");

transaktion("begin");
$vare_id=array();
$indgaar_i=array();
$x=0; $y=0; $z=0;

$q1 = db_select("select * from styklister");
while ($r1 = db_fetch_array($q1)) {
	$z++;
	if (($z>$start)&&($z<=$slut)) {
		if (!in_array($r1[vare_id], $vare_id)) {
			$x++;
			$vare_id[$x] =  $r1[vare_id]*1;
		}
		if (!in_array( $r1[indgaar_i], $indgaar_i)) {
			$y++;
			$indgaar_i[$y] =  $r1[indgaar_i]*1;
		}
	}
	if ($z>$slut) break;
}

ECHO "$x : $y : $z<br>";
$vare_id[0]=$x;
$indgaar_i[0]=$y;


for ($x=1; $x<=$vare_id[0]; $x++) {
#	if ($vare_id[$x] > 0) echo "UPDATE varer set delvare = 'on' where id = '$vare_id[$x]'<br>";
	if ($vare_id[$x] > 0) db_modify("UPDATE varer set delvare = 'on' where id = '$vare_id[$x]'");
}
 for ($y=1; $y<=$indgaar_i[0]; $y++) {
#	if ($indgaar_i[$y] > 0) echo "UPDATE varer set samlevare = 'on' where id = '$indgaar_i[$y]'<br>";
	if ($indgaar_i[$y] > 0) db_modify("UPDATE varer set samlevare = 'on' where id = '$indgaar_i[$y]'");
}


transaktion("commit");
 if ($z > $slut) print "<meta http-equiv=\"refresh\" content=\"0;URL=opdater_vare_stykliste.php?start=$slut\">";
 else print "Opdatering afsluttet";
?>