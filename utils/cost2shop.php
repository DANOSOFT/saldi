<?php
@session_start();
$s_id=session_id();

ini_set('display_errors', 1);

$title="cost2shop";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/std_func.php");

#if ($db!='bizsys_170') {
#	echo "forkert database<br>";
#	exit;
#}
if (!isset($_GET['nextId'])) {
	echo "Du har glemt noget";
	exit;
}
$nextId = $_GET['nextId'];
if ($nextId == 1) {
	$log=fopen("../temp/$db/shopsync.log","w");
	fwrite($log, date("H:i:s")."\n");
	fclose($log);
}
$lagerantal=1;
$qtxt="select box4 from grupper where art='API'";
$z=0;
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$api_fil=trim($r['box4']);
#$qtxt="select varer.* from varer,ordrelinjer where lukket != 'on' and gruppe = '2' and varer.id=ordrelinjer.vare_id and ordrelinjer.ordre_id >= '20278' order by varer.id";
$qtxt = "select id,varenr from varer where id >= '$nextId' and lukket != 'on' ";
# $qtxt.= "and (gruppe <= '8' or gruppe = '13' or gruppe = '14' or gruppe = '16') ";
#$qtxt.= "and gruppe = '4' and varenr = '2226'";
$qtxt.= "order by id limit 1";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
if ($r=db_fetch_array($q)) {
	$id=$r['id'];
	$varenr=$r['varenr'];
} else {
 echo "All done ($netxId) <br>";
 exit;
}
if ($api_fil) {
	echo "Opdaterer Id $id, varenr $varenr<br>";
	include('../api/updateShopCostPrice.php');
	updateShopCostPrice($id);
	$nextId=$id+1;
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../utils/cost2shop.php?nextId=$nextId\">\n";
	exit;
}
?>
