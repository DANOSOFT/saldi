<?php
@session_start();
$s_id=session_id();

(isset($_GET['commit']))?$commit=$_GET['commit']:$commit=0;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$dd=date("Y-m-d");

$x = 0;
$vareId=array();
$qtxt = "select ordrelinjer.id,ordrelinjer.varenr,batch_salg.pris,ordrelinjer.kostpris,ordrelinjer.fast_db,ordrelinjer.rabatart ";
$qtxt.= "from ordrelinjer,batch_salg where "; 
$qtxt.= "batch_salg.salgsdate > '2022-10-10' and batch_salg.pris > 0 and batch_salg.pris < ordrelinjer.kostpris ";
$qtxt.= "and ordrelinjer.fast_db > 0 and ordrelinjer.fast_db < 1 and ordrelinjer.id = batch_salg.linje_id ";
$qtxt.= "order by ordrelinjer.varenr,ordrelinjer.id";

$qtxt = "select ordrelinjer.id,ordrelinjer.varenr,batch_salg.pris,ordrelinjer.kostpris,ordrelinjer.fast_db,ordrelinjer.m_rabat ";
$qtxt.= "from ordrelinjer,batch_salg where "; 
$qtxt.= "batch_salg.salgsdate > '2022-10-23' and batch_salg.salgsdate < '2022-10-27' and ordrelinjer.pris > 0 and ordrelinjer.m_rabat > 0 ";  
$qtxt.= "and ordrelinjer.fast_db > 0 and ordrelinjer.fast_db < 1 and ordrelinjer.id = batch_salg.linje_id ";
$qtxt.= "order by ordrelinjer.varenr,ordrelinjer.id";


echo "$qtxt<br>";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$k = afrund($r['kostpris'],3);
	$a= afrund ($r['pris'] * (1-$r['fast_db']),3);
	$b= afrund($r['pris'] * $r['fast_db'],3);
#echo "$k | $a | $b<br>"; 
	if ($k != $a && $k != $b) {
		$linjeId[$x]  = $r['id'];
		$pris[$x]     = $r['pris'];
		$kostpris[$x] = $r['kostpris'];
		$fastDb[$x]   = $r['fast_db'];
		$varenr[$x]   = $r['varenr'];
		$mRabat[$x]  = $r['m_rabat'];
		$x++;
	}
}
for ($x=0;$x<count($linjeId);$x++) {
#echo "$linjeId[$x] $pris[$x] $kostpris[$x] $fastDb[$x] <br>";	

	if ($fastDb[$x] >	 0.5) $ny_kostpris[$x] = $pris[$x] * (1-$fastDb[$x]);
	else $ny_kostpris[$x] = $pris[$x] * $fastDb[$x];
	$qtxt = "update ordrelinjer set kostpris = '$ny_kostpris[$x]' where id = '$linjeId[$x]'";
	echo "$varenr[$x] -> $qtxt<br>";
	IF ($commit) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
?>
