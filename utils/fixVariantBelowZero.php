<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$dd=date("Y-m-d");

$x=0;
$qtxt = "SELECT * FROM variant_varer where variant_beholdning < 0 ORDER BY id";
#cho "$qtxt<br>";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$variantId[$x] = $r['id'];
	$variantVareId[$x] = $r['vare_id'];
	$variantQty[$x] = abs($r['variant_beholdning']);
	$ean[$x] = $r['variant_stregkode'];
echo __line__. "	$variantId[$x] $variantQty[$x]<br>";
	$x++;
}


for ($y=0;$y<count($variantId);$y++) {
	$qtxt = "select max(id) as id from batch_kob where variant_id = '$variantId[$y]'";
	echo "$qtxt ($ean[$y])<br>";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		if ($r['id']) {
		$qtxt = "update batch_kob set antal = antal + $variantQty[$y] where id = '$r[id]'";
		echo "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "update lagerstatus set beholdning = beholdning + $variantQty[$y] where variant_id = '$variantId[$y]'";
		echo "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "update variant_varer set variant_beholdning = variant_beholdning + $variantQty[$y] where id = '$variantId[$y]'";
		echo "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "update varer set beholdning =beholdning + $variantQty[$y] where id = '$variantVareId[$y]'";
		echo "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	}
}
transaktion('commit');

?>
