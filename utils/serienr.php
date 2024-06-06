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

$a=0;
$qtxt = "select distinct(vare_id) as vare_id from serienr where salgslinje_id = 0 and vare_id = '3699' order by vare_id";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$vareId[$a]=$r['vare_id'];
	$a++;
}
for ($a=0;$a<count($vareId);$a++) {
	$b=0;
	$qtxt = "select kobslinje_id,serienr from serienr where vare_id='$vareId[$a]' and salgslinje_id = '0' order by kobslinje_id";
echo "$qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$kobslinjeId[$b] = $r['kobslinje_id'];
		$serienr[$b]     = $r['serienr'];
		$b++;
	}
	for ($b=0;$b<count($kobslinjeId);$b++) {
		if ($b && $serienr[$b] != $serienr[$b-1]) echo "Serienr: $serienr[$b]<b>";
		$c=0;
		$qtxt = "select batch_kob.id,batch_kob.ordre_id,.batch_kob.antal, ordrer.ordrenr from batch_kob,ordrer ";
		$qtxt.= "where batch_kob.linje_id = '$kobslinjeId[$b]' and ordrer.id = batch_kob.ordre_id"; 
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$bkId[$c]      = $r['id'];
			$bkOrdreId[$c] = $r['ordre_id'];
			$bkAntal[$c]   =  $r['antal'];
			echo "$r[antal] Købt på ordre $r[ordrenr]<br>";
			$qtxt = "select id,ordre_id,antal from batch_salg where batch_kob_id = $bkId[$c]";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r2=db_fetch_array($q)) {
				echo "$r[antal] Solgt på ordre $r[ordrenr]<br>";
			}	
		}
	}
}



?>
