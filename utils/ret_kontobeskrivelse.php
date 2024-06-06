<?php
@session_start();
$s_id=session_id();

ini_set('display_errors', 1);

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");

$r=db_fetch_array(db_select("select max(regnskabsaar) as maxaar from kontoplan"));
$maxaar=$r['maxaar'];
$preaar=$maxaar-1;

$x=0; 
$q=db_select("select id,kontonr,beskrivelse from kontoplan where regnskabsaar='$preaar' order by kontonr");
while ($r=db_fetch_array($q)){
	$id[$x]=$r['id'];
	$kontonr[$x]=$r['kontonr'];
	$beskrivelse[$x]=$r['beskrivelse'];
	$x++;
}
for ($x=0;$x<count($id);$x++){
	echo "update kontoplan set beskrivelse='$beskrivelse[$x]' where regnskabsaar='$maxaar' and kontonr='$kontonr[$x]'<br>";
	db_modify("update kontoplan set beskrivelse='$beskrivelse[$x]',valuta='0',valutakurs='100' where regnskabsaar='$maxaar' and kontonr='$kontonr[$x]'");
}

?>