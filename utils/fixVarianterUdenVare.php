<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$dd=date("Y-m-d");

$x=0;
$qtxt = "SELECT id,varenr,beskrivelse FROM varer ORDER BY id";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$vareId[$x] = $r['id'];
	$varenr[$x] = $r['varenr'];
	$vareText[$x] = $r['beskrivelse'];
	$x++;
}

$x=0;
$qtxt = "SELECT id,beskrivelse FROM variant_typer ORDER BY id";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$vtId[$x] = $r['id'];
	$vtText[$x] = $r['beskrivelse'];
	$x++;
}

$x=0;
$qtxt = "SELECT id FROM variant_varer ORDER BY id";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$allVariants[$x] = $r['id'];
	$x++;
}

$x=0;
$qtxt = "SELECT * FROM variant_varer ORDER BY vare_id"; # where vare_id = 2699
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if (!in_array($r['vare_id'],$vareId)) {
		$variantId[$x] = $r['id'];
		$variantVareId[$x] = $r['vare_id'];
		$variantBarcode[$x] = $r['variant_stregkode'];
		for($i=0;$i<count($vtId);$i++) {
			if ($vtId[$i] == $r['variant_type']) $variantText[$x] = $vtText[$i];
		}
		$x++;
	}	
}
$indhold=file_get_contents("var_1034.csv");
$line=explode("\n",$indhold);
$y=0;
for ($x=0;$x<count($line);$x++) {
	if ($line[$x] = trim($line[$x])) {
		list($shopVnr[$y],$shopParentId[$y],$shopVariantId[$y],$shopBarcode[$y],$shopVariant[$y],$shopVariantType[$y],$shopVariantText[$y])=explode(";",$line[$x]);
		$y++;
	}
} 
transaktion ('begin');
$n=-1;
for ($y=0;$y<count($shopVnr);$y++) {
	$shopBarcode[$y] = trim(trim($shopBarcode[$y],'"'));
	for ($x=0;$x<count($variantVareId);$x++) {
		if ($shopBarcode[$y] == $variantBarcode[$x]) {
			$shopVnr[$y] = trim(trim($shopVnr[$y],'"'));
			$shopParentId[$y] = trim(trim($shopParentId[$y],'"'));
			$shopVariantId[$y] = trim(trim($shopVariantId[$y],'"'));
			$qtxt = "select id from varer where varenr = '$shopVnr[$y]'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$tmp = $r['id'];
			$qtxt = "select distinct(variant_id) as variant_id from batch_salg where vare_id = '$tmp' and variant_id != '0'";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if (!in_array($r['variant_id'],$allVariants)) {
					$qtxt = "update batch_salg set variant_id = '0' where variant_id = '$r[variant_id]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
			$qtxt = "select distinct(variant_id) as variant_id from batch_kob where vare_id = '$tmp' and variant_id != '0'";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if (!in_array($r['variant_id'],$allVariants)) {
					$qtxt = "update batch_kob set variant_id = '0' where variant_id = '$r[variant_id]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
			$qtxt = "select id from varer where varenr = '$shopVnr[$y]'";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				if (!in_array($r['id'],$newId)) {
					$n++;
					$newId[$n] = $r['id'];
				}
				$qtxt = "select id,linje_id from batch_salg where vare_id = '$variantVareId[$x]' and (variant_id = '0' OR variant_id IS NULL)";
				$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
				}
				$qtxt = "update variant_varer set vare_id = '$newId[$n]' where id = '$variantId[$x]'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt = "update shop_varer set saldi_id = '$newId[$n]', shop_id = '$shopParentId[$y]', shop_variant = '$shopVariantId[$y]' ";
				$qtxt.= "where saldi_variant = '$variantId[$x]'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt = "update batch_salg set vare_id = '$newId[$n]' where variant_id = '$variantId[$x]'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt = "update batch_kob set vare_id = '$newId[$n]' where variant_id = '$variantId[$x]'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt = "update ordrelinjer set vare_id = '$newId[$n]' where variant_id = '$variantId[$x]'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt = "update lagerstatus set vare_id = '$newId[$n]' where variant_id = '$variantId[$x]'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt = "select * from batch_salg where vare_id = '$newId[$n]' and variant_id != '0'";
				$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					if (!in_array($r['variant_id'],$variantId)) {
						$qtxt = "update batch_salg set variant_id = '0' where id = '$r[id]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt = "update ordrelinjer set variant_id = '0' where id = '$r[linje_id]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				}
				$qtxt = "select id,linje_id,ordre_id,batch_kob_id from batch_salg where vare_id = '$newId[$n]' and (variant_id = '0' OR variant_id IS NULL)";
				$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$batchSalgId = $r['id'];
					$batchKobId = $r['batch_kob_id'];
					if ($r['linje_id'] && $lineId = $r['linje_id']) {
					$qtxt = "select ordre_id,beskrivelse, variant_id from ordrelinjer where id = '$lineId'";
						$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						$lineTxt = $r2['beskrivelse'];
						$newVariantId = $r2['variant'];
						$newVariant = '';
						if (strpos($r2['beskrivelse'],'(INDSAT FRA SHOP)')) list($r2['beskrivelse'],$newVariant) = explode(' (INDSAT FRA SHOP),',$r2['beskrivelse']);  
						$newVariant = trim($newVariant);	
						if ($lineId == '24469') $newVariant = 'M';
						if (!$newVariant && strpos ($r2['beskrivelse'],' - ')) { 
							$tmpA = explode (' - ',$r2['beskrivelse']);
							$t=count($tmpA)-1;
							$newVariant = $tmpA[$t];
							if (strpos ($newVariant,',')) $newVariant = '';
						}
						if (!$newVariant) { 
							$tmpA = explode (', ',$r2['beskrivelse']);
							$t=count($tmpA)-1;
							$newVariant = $tmpA[$t];
						}
						if (!$newVariantId && $newVariant) {
							if (strpos($newVariant,' ')) $tmp = str_replace(' ','-',$newVariant);
							elseif (strpos($newVariant,'/')) $tmp = str_replace('/','-',$newVariant);
							else $tmp=NULL;
							$newVariantType = 0;
							$qtxt = "select id from variant_typer where beskrivelse = '$newVariant' ";
							$qtxt.= "or lower(beskrivelse) = '". strtolower($newVariant) ."' ";
							$qtxt.= "or upper(beskrivelse) = '". strtoupper($newVariant) ."'";
							if ($tmp) {
								$qtxt.= "or beskrivelse = '$tmp' ";
								$qtxt.= "or lower(beskrivelse) = '". strtolower($tmp) ."' ";
								$qtxt.= "or upper(beskrivelse) = '". strtoupper($tmp) ."'";
							}
							if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
							$newVariantType = $r2['id'];
							$qtxt = "select id as id from variant_varer where ";
							$qtxt.= " variant_type = '$newVariantType' ";
							$qtxt.= "and (vare_id = '$newId[$n]' or vare_id = '$variantVareId[$x]') ";
								if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
								$newVariantId = $r2['id'];
							} else $newVariantId = 0;
							if ($newVariantId) {
								$qtxt = "update variant_varer set vare_id ='$newId[$n]' where id =  '$newVariantId'";
								db_modify($qtxt,__FILE__ . " linje " . __LINE__);
								$qtxt = "update batch_salg set variant_id ='$newVariantId' where linje_id =  '$lineId'";
								db_modify($qtxt,__FILE__ . " linje " . __LINE__);
								$qtxt = "update batch_kob set variant_id ='$newVariantId' where id = '$batchKobId'";
								db_modify($qtxt,__FILE__ . " linje " . __LINE__);
								$qtxt = "update ordrelinjer set variant_id ='$newVariantId' where id =  '$lineId'";
								db_modify($qtxt,__FILE__ . " linje " . __LINE__);
							} elseif ($newVariantType) {
								for ($z=0;$z<count($shopVnr);$z++) {
#									#cho "$newVariant == $shopVariant[$z]<br>";
									if ($newVariant == trim($shopVariant[$z],'"')) {
									}
								}
							}
						}
							if (!$newVariantId) exit;
					}
				}
  		}
		}
	}
}
}
for ($n=0;$n<count($newId);$n++) {
	$qtxt = "delete from batch_kob where vare_id = '$newId[$n]' and linje_id = '0' and variant_id = '0'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "delete from batch_salg where vare_id = '$newId[$n]' and linje_id = '0' and variant_id = '0'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "delete from lagerstatus where vare_id = '$newId[$n]' and variant_id = '0'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);

	$i=0;
	$BatchVariantId = array();
	$qtxt = "select variant_id, sum(antal) as qty from batch_kob where vare_id = '$newId[$n]' group by variant_id order by variant_id";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$BatchVariantId[$i] = $r['variant_id'];
		$BatchVariantQty[$i] = $r['qty'];
		$i++;
	 }
	for ($i=0;$i<count($BatchVariantId);$i++) {
		$qtxt = "select sum(antal) as qty from batch_salg where vare_id = '$newId[$n]' and variant_id = $BatchVariantId[$i]";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$BatchVariantQty[$i]-= $r['qty'];
		}
	}
	$i = count($BatchVariantId);
	$qtxt = "select variant_id, sum(antal) as qty from batch_salg where vare_id = '$newId[$n]' group by variant_id order by variant_id";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!in_array($r['variant_id'],$BatchVariantId)) {
			$BatchVariantId[$i] = $r['variant_id'];
			$BatchVariantQty[$i] = $r['qty']*-1;
			$i++;
		}
  }
	$newTotalSum = 0; 
	for ($i=0;$i<count($BatchVariantId);$i++) {
		$qtxt = "update lagerstatus set vare_id = '$newId[$n]' where variant_id = '$BatchVariantId[$i]'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "select id,beholdning from lagerstatus where vare_id = '$newId[$n]' and variant_id = '$BatchVariantId[$i]'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$qtxt = "update lagerstatus set beholdning = '$BatchVariantQty[$i]' where vare_id = '$newId[$n]' and variant_id = '$BatchVariantId[$i]'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else if ($BatchVariantQty[$i]) {
			$qtxt = "insert into lagerstatus (vare_id,variant_id,beholdning,lager) values ";
			$qtxt.= "('$newId[$n]','$BatchVariantId[$i]','$BatchVariantQty[$i]','1')"; 
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		$qtxt = "update variant_varer set variant_beholdning = '$BatchVariantQty[$i]' where vare_id = '$newId[$n]' and id = '$BatchVariantId[$i]'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$newTotalSum+= $BatchVariantQty[$i];
	}
	$qtxt = "select varenr, beholdning from varer where id = '$newId[$n]'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		echo "Vnr $r[varenr] - Ext. beholdning ". $r['beholdning']*1 ." ny beholdning $newTotalSum<br>";
	}
	$qtxt = "delete from lagerstatus where vare_id = '$newId[$n]' and variant_id = '0'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "update varer set beholdning = '$newTotalSum', varianter = '1' where id = '$newId[$n]'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
transaktion('commit');
/*
for ($x=0;$x<count($variantVareId);$x++) {
	for ($y=0;$y<count($vareId);$y++) {
		if ($variantVareId[$x] == $vareId[$y]) #cho $beskrivelse
	}
}
/*
exit;
$x=0;
$qtxt = "SELECT id,beskrivelse FROM varer ORDER BY id";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$variantTypeId[$x] = $r['id'];
	$variantTypeText[$x] = $r['beskrivelse'];
	$x++;
}
transaktion('begin');

for ($x=0;$x<count($vaInFrShId);$x++) {
	$tmp = trim(str_replace ('(INDSAT FRA SHOP)','',$vaInFrShName[$x])); 
	$nettoName[$x] = $tmp;
	$tmpA = explode (' - ',$nettoName[$x]);
	$t = count($tmpA)-1;
	if ($t > 1) {
		$tmpV[$x] = trim($tmpA[$t]);
		$tmpV1[$x] = str_replace('/','-',$tmpV[$x]);
		$tmpV1[$x] = trim(str_replace('Str.','',$tmpV1[$x]));
		$tmpV1[$x] = str_replace(' ','-',$tmpV1[$x]);
		$vaInFrShVariant[$x] = trim($tmpV1[$x]);
		$nettoName[$x] = trim(str_replace("- $tmpV[$x]",'',$nettoName[$x]));
	}
#	if ($vaInFrShId[$x] == '2500') exit;
#	$qtxt = "select id from varer where beskrivelse = '$tmp'";
}
for ($x=0;$x<count($vaInFrShId);$x++) {
	$qtxt = "select id,varenr,kostpris from varer where beskrivelse = '$nettoName[$x]'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	if ($r=db_fetch_array($q)) {
		$newId[$x]   = $r['id'];
		$newVnr[$x]  = $r['varenr'];
		$newCost[$x] = $r['kostpris'];
		$newVariantId[$x] = 0;
		for ($v=0;$v<count($variantTypeText);$v++) {
				if (!$newVariantId[$x] && 
					($vaInFrShVariant[$x] == $variantTypeText[$v] || 
						strtolower($vaInFrShVariant[$x]) == strtolower($variantTypeText[$v]) ||
						strtoupper($vaInFrShVariant[$x]) == strtoupper($variantTypeText[$v]))
				)	{
						$nettoName[$x] = trim(str_replace("- $vaInFrShVariant[$x]",'',$nettoName[$x]));
                    
$qtxt = "select * from variant_varer where vare_id = '$newId[$x]' and variant_type = '$variantTypeId[$v]'";
						$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
						if ($r=db_fetch_array($q)) {
								$newVariantId[$x] = $r['id'];
						}
#						break (1);
				} #else #cho "Variant $tmpV[$x] ($tmpV1[$x]) ikke fundet<br>";
			}
		}
	if ($newId[$x]) {
		$qtxt = "update ordrelinjer set vare_id = '$newId[$x]', varenr = '$newVnr[$x]', variant_id = '$newVariantId[$x]' ";
		$qtxt.= "where vare_id = '$vaInFrShId[$x]'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "update batch_kob set vare_id = '$newId[$x]', variant_id = '$newVariantId[$x]' where vare_id = '$vaInFrShId[$x]'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "update batch_salg set vare_id = '$newId[$x]', variant_id = '$newVariantId[$x]' where vare_id = '$vaInFrShId[$x]'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$newQty=0;
		$qtxt = "select sum(antal) as qty from batch_kob where vare_id = '$newId[$x]' and variant_id = '$newVariantId[$x]'";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		if ($r=db_fetch_array($q)) $newQty+=$r['qty'];
		$qtxt = "select sum(antal) as qty from batch_salg where vare_id = '$newId[$x]' and variant_id = '$newVariantId[$x]'";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		if ($r=db_fetch_array($q)) $newQty-=$r['qty'];
		if ($newQty < 0) $newQty = 0; 
		lagerreguler($newId[$x],'0',$newCost[$x],'1',$dd,$newVariantId[$x]); 
/*
		$qtxt = "select id from lagerstatus where vare_id = '$newId[$x]' and variant_id = '$newVariantId[$x]'";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		if ($r=db_fetch_array($q)) {
			$qtxt = "update lagerstatus set beholdning = '$newQty' where vare_id = '$newId[$x]' and variant_id = '$newVariantId[$x]'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
*/
/*
$qtxt = "DELETE FROM  varer where id = '$vaInFrShId[$x]'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "select * from shop_varer where saldi_id = '$newId[$x]' and saldi_variant = '$newVariantId[$x]'";   	
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			sync_shop_vare($r['shop_id'],$r['shop_variant'],1);
		}
	} else {
		$newName[$x] = $nettoName[$x] ." (indsat fra shop ordre)";
		$qtxt = "update varer set beskrivelse = '$newName[$x]' where id = '$vaInFrShId[$x]'";  
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}
transaktion('commit');
*/
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
#		#cho $ordreLinjeId[$x][$y] ." | ". $ordreId[$x][$y] ." | ". $ordreLinjeVnr[$x][$y] ." | 	". $ordreLinjeText[$x][$y] ."<br>";
		$y++;
	}
}

for ($x=0;$x<count($variantVareId);$x++) {
	for ($y=0;$y<count($ordreLinjeId[$x]);$y++) {
	}
}
*/
?>
