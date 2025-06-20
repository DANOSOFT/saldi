<?php
session_start();
$s_id=session_id();

# include("../tidsreg/include/tabel.inc");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");

if ($_GET[start]) $start=$_GET['start'];
else $start=0;
$slut=$start+500;
$tmp=$start;
echo "importer tidsreg poster $start til $slut<br>";


$link=mysql_connect("localhost","root","");
$q1=mysql_db_query("abjensen", "SELECT * from tidsreg where loebenummer >= $start and loebenummer <= $slut*2  order by loebenummer", $link);
if ($q1) {
	while($r1 = mysql_fetch_array($q1)) {
		$person = $r1[person]*1; $ordre=$r1[ordre]*1; $pnummer=$r1[pnummer]*1; $operation=$r1[operation]*1; $materiale=$r1[materiale]*1; $tykkelse=$r1[tykkelse]*1;
		$laengde= $r1[laengde]*1; $bredde=$r1[bredde]*1; $antal_plader=$r1[antal_plader]*1; $gaa_hjem= $r1[gaa_hjem]*1; $tid= $r1[tid]*1; 
		$forbrugt_tid= $r1[forbrugt_tid]*1; $opsummeret_tid=$r1[opsummeret_tid]*1; $beregnet=$r1[beregnet]*1; $pause=$r1[pause]*1; $antal=$r1[antal]*1; $faerdig=$r1[faerdig]*1; $circ_time=$r1[circ_time]*1;
		$tmp++;	
		db_modify ("insert into tidsreg (person, ordre, pnummer, operation, materiale , tykkelse, laengde, bredde, antal_plader, gaa_hjem , tid, forbrugt_tid , opsummeret_tid , beregnet , pause , antal , faerdig , circ_time) values ($person, $ordre, $pnummer, $operation, $materiale, $tykkelse, $laengde, $bredde, $antal_plader, $gaa_hjem, $tid, $forbrugt_tid, $opsummeret_tid, $beregnet, $pause, $antal, $faerdig, $circ_time)");		
#			db_modify("");
		if ($tmp>=$slut) break;
	}
} else echo "Ingen data fundet at importere<br>";
if ($tmp >= $slut) print "<meta http-equiv=\"refresh\" content=\"0;URL=tidsreg2tidsreg.php?start=$tmp\">";
else  echo "Tidsreg import afsluttet med succes<br>";
/*
$overskrifter=array("Prodnavn","Nummer","Navn");
$nyside="ordre.php";
tabeller($operation1,$overskrifter,"ja",$nyside,"ja");
*/

?>

