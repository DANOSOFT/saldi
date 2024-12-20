<?php
@session_start();
$s_id=session_id();

ini_set('display_errors', 1);

$title="stock2shop";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/std_func.php");
/*
if ($db!='bizsys_170') {
	echo "forkert database<br>";
	exit;
}
*/
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
#cho __line__." $qtxt<br>";
$z=0;
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$api_fil=trim($r['box4']);
#echo "$api_fil<br>";

$i=0;
$stockGroups = '';
$qtxt = "select kodenr from grupper where art = 'VG' and box8 = 'on'";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($stockGroups) $stockGroups .= " or ";
	$stockGroups.= "gruppe = '".$r['kodenr']."'" ;
	$i++;
}


#$qtxt="select varer.* from varer,ordrelinjer where lukket != 'on' and gruppe = '2' and varer.id=ordrelinjer.vare_id and ordrelinjer.ordre_id >= '20278' order by varer.id";
$qtxt = "select id,varenr,varianter,beholdning from varer where id >= '$nextId' and lukket != 'on' ";
$qtxt.= "and ($stockGroups) order by id limit 1";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$id=$r['id'];
		echo "opdaterer id $id, varenr $r[varenr], antal $r[beholdning]<br>";

		sync_shop_vare($id,0,1);
} else $id = 0;
/*
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
if ($r=db_fetch_array($q)) {
	$id=$r['id'];
	$varenr=$r['varenr'];
	$varianter=$r['varianter'];
	$beholdning==$r['beholdning'];
#		$txt.="?update_stock=$shop_id";
	$txt="curl '$api_fil?update_stock=$shop_id&itemNo=$varenr";
	for ($x=1;$x<=$lagerantal;$x++) {
		$qtxt="select beholdning from lagerstatus where vare_id='$id' and lager = '$x'";
		$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$txt2=$txt."&stock=".$r2['beholdning']*1;
		$txt2.="&stockno=$x'";
			$log=fopen("../temp/$db/shopsync.log","a");
		fwrite($log, "opdaterer shop id $shop_id saldi_id $id - antal $r2[beholdning]\n");
		fclose($log);
echo __line__." $txt2<br>";
echo __line__." opdaterer shop id $shop_id saldi_id $id varenr $varenr - antal $r2[beholdning]<br>";
				#exit;
		shell_exec ("$txt2 > /dev/null 2>&1 &\n");
	}
}
*/
$nextId=$id+1;
if ($nextId < 2) {
	echo "next $nextId<br>";
	exit;
}
print "<meta http-equiv=\"refresh\" content=\"1;URL=../utils/stock2shop.php?nextId=$nextId\">\n";
exit;
/*

} else {
 echo "All done ($netxId) <br>";
 exit;
}
if ($api_fil) {

} elseif ($api_fil) {
	$qtxt="select * from variant_varer where vare_id = '$id'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r2=db_fetch_array($q)) {
		$varId=$r2['id'];
		$shop_id=0;
		$qtxt="select shop_variant from shop_varer where saldi_variant='$varId'";
#	 echo #$qtxt<br>"
		if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $shop_id=$r2['shop_variant'];
	#		else $shop_id.=urlencode("$varenr");
		if ($shop_id) {
#			$txt="/usr/bin/wget  -O - -q  --no-check-certificate --header='User-Agent: Mozilla/5.0 Gecko/20100101 Firefox/23.0' '$api_fil";
			$txt="curl '$api_fil?update_stock=$shop_id";
			for ($y=1;$y<=$lagerantal;$y++) {
				$qtxt="select beholdning from lagerstatus where vare_id='$id' and variant_id='$varId' and lager = '$y'";
				$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$log=fopen("../temp/$db/shopsync.log","a");
				fwrite($log, "opdaterer shop variant id $shop_id saldi_variant $varId - antal $r2[beholdning]\n");
				fclose($log);
echo __line__." opdaterer shop variant $shop_id saldi_variant $varId - antal $r2[beholdning]<br>"; 
				$txt2=$txt."&stock=".$r2['beholdning']*1;
				$txt2.="&stockno=$y'";
				shell_exec ("$txt2 > /dev/null 2>&1 &\n");
				usleep(1000);	
			}
		}	
	}
	$nextId=$id+1;
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../utils/stock2shop.php?nextId=$nextId\">\n";
	exit;
	
}
*/
?>
