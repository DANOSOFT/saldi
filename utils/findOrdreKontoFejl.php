<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$dd=date("Y-m-d");

$x = 0;
$qtxt = "SELECT id,konto_id,kontonr,firmanavn from sager order by id";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$sagId[$x]    = $r['id'];
	$kontoId[$x]  = $r['konto_id'];
	$kontonr[$x]  = $r['kontonr'];
	$firmanavn[$x]  = $r['firmanavn'];
	$x++;
}
$x=0;
for ($x=0;$x<count($sagId);$x++) {
	$qtxt = "SELECT id,konto_id,kontonr,firmanavn from ordrer where status < 1 and art = 'OT' and sag_id = '$sagId[$x]' and konto_id != '$kontoId[$x]' and tidspkt > ' 1669852861'";	
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		echo "Ordre ID $r[id] SagId $sagId[$x] ($kontonr[$x] $firmanavn[$x]) har konto ID $r[konto_id] ($r[kontonr] $r[firmanavn]) . Skulle være konto Id $kontoId[$x]<br>"; 
	}
}
?>
