<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/std_func.php");
#$logdatetime=array();
#$sum=array();
$i=0;
$qtxt = "select id,db,email from regnskab where lukket != 'on' and id > '1' order by id";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$dbEmail[$i]	= $r['email'];
	$dbName[$i] = $r['db'];
	$i++;
}
for ($i=0;$i<count($dbName);$i++) {
	$qtxt = "update online set db='$dbName[$i]' where session_id = '$s_id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	include("../includes/online.php");
	$qtxt = "select max(kodenr) as fy from grupper where art = 'RA'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$fy = $r['fy'];
	$qtxt = "select box4 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$fy'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$harTerminal[$i] = trim($r['box4']);
	$qtxt = "select firmanavn,tlf from adresser where art = 'S'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$firmanavn[$i] = $r['firmanavn'];
	$tlf[$i] = $r['tlf'];

	include("../includes/connect.php");
}
print "<table>";
for ($i=0;$i<count($dbName);$i++) {
	if (!$harTerminal[$i]) print "<tr><td>$dbName[$i]</td><td>$firmanavn[$i]</td><td>$dbEmail[$i]</td><td>$tlf[$i]</td></tr>";
}
print "</table>";

?>
