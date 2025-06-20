<?php
@session_start();
$s_id=session_id();

#exit;

$title="brugertjek";

include("../includes/connect.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");

$x=0;
$q1= db_select("select id, fra_kto from kontoplan where kontotype = 'D' and fra_kto!='' order by id");
while ($r1=db_fetch_array($q1)) {
	$x++;
	$id[$x]=$r1['id'];
	$moms[$x]=str_replace('"','',$r1['fra_kto']);
}
$kontoantal=$x;
for ($x=1; $x<=$kontoantal; $x++) {
echo  "update kontoplan set moms='$moms[$x]' , fra_kto = '' where id='$id[$x]'<br>";	
	db_modify("update kontoplan set moms='$moms[$x]', fra_kto = '' where id='$id[$x]'");
}
$x=0;
$q1= db_select("select id, til_kto from kontoplan where kontotype = 'Z' and fra_kto !='' and til_kto!='kontonr' order by id");
while ($r1=db_fetch_array($q1)) {
	$x++;
	$id[$x]=$r1['id'];
	$kontonr[$x]=$r1['kontonr'];	
	$fra_kto[$x]=$r1['til_kto'];
}
$kontoantal=$x;
for ($x=1; $x<=$kontoantal; $x++) {
echo  "update kontoplan set fra_kto='$fra_kto[$x]', til_kto='$kontonr[$x]'  where id='$id[$x]'<br>";	
db_modify("update kontoplan set fra_kto='$fra_kto[$x]', til_kto='$kontonr[$x]'  where id='$id[$x]'");
}
