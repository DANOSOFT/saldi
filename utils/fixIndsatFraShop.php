<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");


$dd=date("Y-m-d");
$x=0;
$qtxt = "select * from varer where beskrivelse like '%(INDSAT FRA SHOP)'";
echo "$qtxt<br>";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
 $vaInFrShId[$x]   = $r['id'];
 $vaInFrShVnr[$x]   = $r['varenr'];
 $vaInFrShName[$x] = $r['beskrivelse'];
echo "$vaInFrShId[$x] | $vaInFrShVnr[$x] | $vaInFrShName[$x]<br>";
 $x++;
}
$x=0;
$qtxt = "select id,beskrivelse from variant_typer";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$variantTypeId[$x] = $r['id'];
	$variantTypeText[$x] = $r['beskrivelse'];
#cho ">$variantTypeText[$x]<<br>";
	$x++;
}
transaktion('begin');

for ($x=0;$x<count($vaInFrShId);$x++) {
	$tmp = trim(str_replace ('(INDSAT FRA SHOP)','',$vaInFrShName[$x])); 
	$nettoName[$x] = $tmp;
echo __line__." Renset navn: $nettoName[$x]<br>";
	$tmpA = explode (' - ',$nettoName[$x]);
	$t = count($tmpA)-1;
echo __line__." Array size $t<br>";
	if ($t > 1) {
		$tmpV[$x] = trim($tmpA[$t]);
#cho __line__." Variant >$tmpV[$x]<<br>";
		$tmpV1[$x] = str_replace('/','-',$tmpV[$x]);
#cho __line__." Variant >$tmpV1[$x]<<br>";
		$tmpV1[$x] = trim(str_replace('Str.','',$tmpV1[$x]));
#cho __line__." Variant >$tmpV1[$x]<<br>";
		$tmpV1[$x] = str_replace(' ','-',$tmpV1[$x]);
#cho __line__." Variant >$tmpV1[$x]<<br>";
		$vaInFrShVariant[$x] = trim($tmpV1[$x]);
echo __line__." Renset variant >$vaInFrShVariant[$x]<<br>";
		$nettoName[$x] = trim(str_replace("- $tmpV[$x]",'',$nettoName[$x]));
echo __line__." Renset navn: $nettoName[$x]<br>";
	}
echo "--------------------<br>";
#	if ($vaInFrShId[$x] == '2500') exit;
#	$qtxt = "select id from varer where beskrivelse = '$tmp'";
}
for ($x=0;$x<count($vaInFrShId);$x++) {
	$qtxt = "select id,varenr,kostpris from varer where beskrivelse = '$nettoName[$x]'";
echo "<hr>$qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	if ($r=db_fetch_array($q)) {
		$newId[$x]   = $r['id'];
		$newVnr[$x]  = $r['varenr'];
		$newCost[$x] = $r['kostpris'];
echo __line__." NewId $newId[$x]<br>";
		$newVariantId[$x] = 0;
		for ($v=0;$v<count($variantTypeText);$v++) {
#cho "$tmpV1[$x] == $variantTypeText[$v]<br>";
				if (!$newVariantId[$x] && 
					($vaInFrShVariant[$x] == $variantTypeText[$v] || 
						strtolower($vaInFrShVariant[$x]) == strtolower($variantTypeText[$v]) ||
						strtoupper($vaInFrShVariant[$x]) == strtoupper($variantTypeText[$v]))
				)	{
						$nettoName[$x] = trim(str_replace("- $vaInFrShVariant[$x]",'',$nettoName[$x]));
echo __line__." Renset navn: $nettoName[$x]<br>";
                    
$qtxt = "select * from variant_varer where vare_id = '$newId[$x]' and variant_type = '$variantTypeId[$v]'";
echo __line__." $qtxt<br>";
						$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
						if ($r=db_fetch_array($q)) {
								$newVariantId[$x] = $r['id'];
echo __line__." NyVariantID ".$newVariantId[$x]."<br>";
						}
#						break (1);
				} #else echo "Variant $tmpV[$x] ($tmpV1[$x]) ikke fundet<br>";
			}
		}
echo __line__." ".count($vaInFrShId) ."<br>";
echo __line__." NewId $newId[$x]<br>";
	if ($newId[$x]) {
		$qtxt = "update ordrelinjer set vare_id = '$newId[$x]', varenr = '$newVnr[$x]', variant_id = '$newVariantId[$x]' ";
		$qtxt.= "where vare_id = '$vaInFrShId[$x]'";
echo "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "update batch_kob set vare_id = '$newId[$x]', variant_id = '$newVariantId[$x]' where vare_id = '$vaInFrShId[$x]'";
echo "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "update batch_salg set vare_id = '$newId[$x]', variant_id = '$newVariantId[$x]' where vare_id = '$vaInFrShId[$x]'";
echo "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$newQty=0;
		$qtxt = "select sum(antal) as qty from batch_kob where vare_id = '$newId[$x]' and variant_id = '$newVariantId[$x]'";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		if ($r=db_fetch_array($q)) $newQty+=$r['qty'];
		$qtxt = "select sum(antal) as qty from batch_salg where vare_id = '$newId[$x]' and variant_id = '$newVariantId[$x]'";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		if ($r=db_fetch_array($q)) $newQty-=$r['qty'];
		echo "newQty $newQty<br>";
		if ($newQty < 0) $newQty = 0; 
		echo "newQty $newQty<br>";
		echo "lagerreguler($newId[$x],'0',$newCost[$x],'1',$dd,$newVariantId[$x])";
		lagerreguler($newId[$x],'0',$newCost[$x],'1',$dd,$newVariantId[$x]); 
/*
		$qtxt = "select id from lagerstatus where vare_id = '$newId[$x]' and variant_id = '$newVariantId[$x]'";
echo "$qtxt<br>";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		if ($r=db_fetch_array($q)) {
			$qtxt = "update lagerstatus set beholdning = '$newQty' where vare_id = '$newId[$x]' and variant_id = '$newVariantId[$x]'";
echo "$qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
*/
		$qtxt = "DELETE FROM  varer where id = '$vaInFrShId[$x]'";
echo "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "select * from shop_varer where saldi_id = '$newId[$x]' and saldi_variant = '$newVariantId[$x]'";   	
echo "$qtxt<br>";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			echo "sync_shop_vare($r[shop_id],$r[shop_variant],1)";
			sync_shop_vare($r['shop_id'],$r['shop_variant'],1);
		}
	} else {
		$newName[$x] = $nettoName[$x] ." (indsat fra shop ordre)";
		$qtxt = "update varer set beskrivelse = '$newName[$x]' where id = '$vaInFrShId[$x]'";  
		echo "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}
transaktion('commit');
/*
for ($x=0;$x<count($variantVareId);$x++) {
	$y=0;
	$qtxt = "Select id,ordre_id ,varenr,beskrivelse from ordrelinjer where vare_id='$variantVareId[$x]' and variant_id = '0'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$ordreLinjeId[$x][$y] = $r['id'];
		$ordreId[$x][$y] = $r['ordre_id'];
		$ordreLinjeVnr[$x][$y] = $r['varenr'];
		$ordreLinjeText[$x][$y] = $r['beskrivelse'];
#		echo $ordreLinjeId[$x][$y] ." | ". $ordreId[$x][$y] ." | ". $ordreLinjeVnr[$x][$y] ." | 	". $ordreLinjeText[$x][$y] ."<br>";
		$y++;
	}
}

for ($x=0;$x<count($variantVareId);$x++) {
	for ($y=0;$y<count($ordreLinjeId[$x]);$y++) {
		echo $variantVareId[$x] ." | ". $ordreLinjeId[$x][$y] ." | ". $ordreId[$x][$y] ." | ". $ordreLinjeVnr[$x][$y] ." | 	". $ordreLinjeText[$x][$y] ."<br>";
	}
}
*/
?>
