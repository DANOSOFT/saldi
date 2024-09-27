<?php
if (!function_exists('getParentStock')) {
	function getParentStock($productId,$stockNo) {
		$i = 0;
		$qtxt = "select * from styklister where indgaar_i = '$productId' and antal > 0";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		$partListId = array();
		$partListQty = array();
		while ($r = db_fetch_array($q)) {
			$partListId[$i]  = $r['vare_id'];
			$partListQty[$i] = $r['antal'];
			$i++;
		}
		$possible = 0;
		for ($i=0;$i<count($partListId);$i++) {
			$qtxt = "select beholdning from lagerstatus where vare_id = '$partListId[$i]' and lager = '$stockNo'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			if ($i == 0 || $possible > $r['beholdning'] / $partListQty[$i]) {
				$possible = floor($r['beholdning'] / $partListQty[$i]);
			}
		}
		echo "$possible<br>";
		return($possible);
	}
}
?>
