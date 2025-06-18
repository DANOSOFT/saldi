<?php
@session_start();
$s_id=session_id();

$modulnr=12;
$kontonr=array();
$linjebg=NULL;
$title="kassespor - fejlmoms";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$x=0;
$q = db_select("select * from ordrer where fakturadate>='2014-06-10' and art = 'PO' order by id",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$ordre_id[$x]=$r['id'];
	$ordre_sum[$x]=$r['sum'];
	$x++;
}
for ($x=0;$x<count($ordre_id);$x++) {
	$linjesum[$x]=0;
echo "select * from ordrelinjer where id='$ordre_id[$x]'<br>";
	$q = db_select("select * from ordrelinjer where ordre_id='$ordre_id[$x]'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
#		echo "$r[antal]*$r[pris]-($r[antal]*$r[pris]*$r[rabat]/100)<br>";
		$linjesum[$x]+=$r['antal']*$r['pris']-($r['antal']*$r['pris']*$r['rabat']/100);
	}
	echo "$ordre_id[$x] $ordre_sum[$x] $linjesum[$x]<br>";
	echo "$linjesum[$x]==".$ordre_sum[$x]*0.8."<br>";
	if (abs($linjesum[$x]-$ordre_sum[$x]*0.8)<0.01) {
		echo "update ordrer set sum='$linjesum[$x]' where id='$ordre_id[$x]'<br>";
		db_modify("update ordrer set sum='$linjesum[$x]' where id='$ordre_id[$x]'",__FILE__ . " linje " . __LINE__);
	} else echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!<br>";
}




?>