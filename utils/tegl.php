<?php
echo "ok<br>";
if (file_exists("../temp/tegl.log")) echo "fundet<br>";
$fp=fopen("../temp/tegl.log","r");
$vId=$qty=0;
while ($linje=fgets($fp)) {
	if ($linje) {
		if (strpos($linje,'update_stock')) {
			list($a,$b)=explode('(',$linje,2);
			$b = trim(str_replace(')','',$b));
			list($vId,$qty)=explode(',',$b);
			echo "$vId,$qty<br>";
		}
	}
	if ($vId) {
		if (strpos($linje,'Existing stock')) {
			list($a,$b)=explode('Existing stock',$linje,2);
			$b = trim($b);
			echo "Existing stock $b<br>";
			if ($b != $qty) {
				$qtxt = "update st18_cataloginventory_stock_status set qty='$b' where product_id='$vId'";
				echo "$qtxt<br>";
			}
			$vId=$qty=0;
		}
	}
}

?>
