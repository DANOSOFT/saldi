<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/usdecimal.php");

if (isset($_GET['start']))  $startlinje=$_GET['start'];
else $startlinje=0;
$slutlinje=$startlinje+99;
echo "Importerer linje $startlinje til linje $slutlinje<br>";
transaktion("begin");
$fp=fopen("../../tidsreg/dumps/2006-06-27/Struct.txt","r");
if ($fp) {
	$x=0;
	while (!feof($fp)) {
		$x++;
		$linje=fgets($fp);
		if (($x>=$startlinje) && ($x<$slutlinje) && (substr($linje,0,1)=='"')) { 
			list($ProdNo, $LnNo, $SubProd, $Descr, $ID, $NoPerStr, $SpecSub, $FFm, $FSz, $FSt, $LnFl, $Srt, $PrM2, $RawMat, $ProdTp1, $ProdTp2, $ProdTp3, $ProdTp4, $TrInf1, $TrInf2, $StrWgt, $PrM1, $PrM3, $PrM4, $PrM5, $TrInf3, $TrInf4) = split("\" \"", $linje);
			$ProdNo=substr($ProdNo,1);
			$r1=db_fetch_array(db_select("select id from varer where varenr='$ProdNo'"));
			$indgaar_i=$r1['id'];
			$r1=db_fetch_array(db_select("select id from varer where varenr='$SubProd'"));
			$vare_id=$r1['id'];

			if (($vare_id) && ($indgaar_i)) db_modify("INSERT INTO styklister (vare_id, indgaar_i, antal, posnr) values ('$vare_id', '$indgaar_i', '$NoPerStr', '$LnNo')");
		}
		if ($x>$slutlinje) break;
	}
} 
fclose($fp);
echo "$x styklister importeret<br>";
transaktion("commit");
if ($x > $slutlinje) {
	$slutlinje++;
	print "<meta http-equiv=\"refresh\" content=\"0;URL=import_visma_stykliste.php?start=$slutlinje\">";
}
?>