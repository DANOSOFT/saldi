<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$dd=date("Y-m-d");

$x=0;
$qtxt = "SELECT id,varenr,beskrivelse FROM varer ORDER BY id";
#cho "$qtxt<br>";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$vareId[$x] = $r['id'];
	$varenr[$x] = $r['varenr'];
	$vareText[$x] = $r['beskrivelse'];
	$x++;
}

$x=0;
$qtxt = "SELECT id,beskrivelse FROM variant_typer ORDER BY id";
#cho "$qtxt<br>";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$vtId[$x] = $r['id'];
	$vtText[$x] = $r['beskrivelse'];
	$x++;
}

$x=0;
$qtxt = "SELECT id,vare_id FROM variant_varer ORDER BY id";
#cho "$qtxt<br>";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$allVariantsVareId[$x] = $r['vare_id'];
	$allVariants[$x] = $r['id'];
	$x++;
}

$x=0;
$qtxt = "SELECT * FROM batch_salg where variant_id = '0' ORDER BY vare_id"; # where vare_id = 2699
#cho "$qtxt<br>";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
#cho "Found 2129 in batch_salg<br>";
	if (in_array($r['vare_id'],$allVariantsVareId)) {
		$batchSalgId[$x] = $r['id'];
		$lineId[$x] = $r['linje_id'];
		$orderId[$x] = $r['ordre_id'];
		$batchKobId[$x] = $r['batch_kob_id'];
		$variantId[$x] = $r['id'];
		$variantVareId[$x] = $r['vare_id'];
if ($variantVareId[$x] == 2129) #cho "Found 2129 in batch_salg<br>";
		$variantBarcode[$x] = $r['variant_stregkode'];
		for($i=0;$i<count($vtId);$i++) {
			if ($vtId[$i] == $r['variant_type']) $variantText[$x] = $vtText[$i];
		}
#		echo "VBK $variantId[$x] | $variantVareId[$x] | $variantBarcode[$x] | $variantText[$x]<br>";
		$x++;
	}	
}
transaktion ('begin');
$n=-1;
for ($y=0;$y<count($variantVareId);$y++) {
	$qtxt = "select id,linje_id,ordre_id,batch_kob_id from batch_salg where vare_id = '$variantVareId[$y]' and (variant_id = '0' OR variant_id IS NULL)";
#cho __line__." $qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$batchSalgId[$y] = $r['id'];
#cho __line__." BSI $batchSalgId[$y] <br>";
		$batchKobId[$y] = $r['batch_kob_id'];

		if ($lineId[$y]) {
		$qtxt = "select ordre_id,beskrivelse, variant_id from ordrelinjer where id = '$lineId[$y]'";
#cho __line__." $qtxt<br>";
			$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$lineTxt = $r2['beskrivelse'];
			$newVariantId = $r2['variant'];
			$newVariant = '';
			if (strpos($r2['beskrivelse'],'(INDSAT FRA SHOP)')) list($r2['beskrivelse'],$newVariant) = explode(' (INDSAT FRA SHOP),',$r2['beskrivelse']);  
			$newVariant = trim($newVariant);	
			if ($lineId[$y] == '24469') $newVariant = 'M';
			if ($lineId[$y] == '24475') $newVariant = 'L';
			if ($lineId[$y] == '24468') $newVariant = 'L/XL';
			
#cho __line__." $lineId[$y] | $newVariant | $r2[beskrivelse]<br>";
			if (!$newVariant && strpos ($r2['beskrivelse'],' - ')) { 
#cho __line__." $r2[beskrivelse]<br>";
				$tmpA = explode (' - ',$r2['beskrivelse']);
				$t=count($tmpA)-1;
				$newVariant = $tmpA[$t];
#cho __line__." $newVariant<br>";
				if (strpos ($newVariant,',')) $newVariant = '';
#cho __line__." $newVariant<br>";
			}
			if (!$newVariant) { 
	#cho __line__." $newVariant<br>";
				$tmpA = explode (', ',$r2['beskrivelse']);
				$t=count($tmpA)-1;
				$newVariant = $tmpA[$t];
#cho __line__." >$newVariant<<br>";
			}
#cho __line__." $newVariant<br>";
			if (!$newVariantId && $newVariant) {
				$tmp=$newVariant;
				if (strpos($tmp,' ')) $tmp = str_replace(' ','-',$tmp);
				if (strpos($tmp,'/')) $tmp = str_replace('/','-',$tmp);
				if ($tmp == $newVariant) $tmp = NULL;
				$newVariantType = 0;
				$qtxt = "select id from variant_typer where beskrivelse = '$newVariant' ";
				$qtxt.= "or lower(beskrivelse) = '". strtolower($newVariant) ."' ";
				$qtxt.= "or upper(beskrivelse) = '". strtoupper($newVariant) ."'";
				if ($tmp) {
					$qtxt.= "or beskrivelse = '$tmp' ";
					$qtxt.= "or lower(beskrivelse) = '". strtolower($tmp) ."' ";
					$qtxt.= "or upper(beskrivelse) = '". strtoupper($tmp) ."'";
				}
#cho __line__." $qtxt<br>";
				if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$newVariantType = $r2['id'];
					$qtxt = "select id as id from variant_varer where ";
					$qtxt.= " variant_type = '$newVariantType' ";
					$qtxt.= "and vare_id = '$variantVareId[$y]' ";
#cho __line__." $qtxt<br>";
					if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
						$newVariantId = $r2['id'];
					  #cho "nVId $newVariantId<br>";
					} else $newVariantId = 0;
					if ($newVariantId) {
						$qtxt = "update variant_varer set vare_id ='$variantVareId[$y]' where id =  '$newVariantId'";
#cho __line__." $qtxt<br>";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt = "update batch_salg set variant_id ='$newVariantId' where linje_id =  '$lineId[$y]'";
#cho __line__." $qtxt<br>";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt = "update batch_kob set variant_id ='$newVariantId' where id = '$batchKobId[$y]'";
#cho __line__." $qtxt<br>";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt = "update ordrelinjer set variant_id ='$newVariantId' where id =  '$lineId[$y]'";
							#cho "$qtxt<br>";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					} elseif ($newVariantType) {
						for ($z=0;$z<count($shopVnr);$z++) {
#									#cho "$newVariant == $shopVariant[$z]<br>";
							if ($newVariant == trim($shopVariant[$z],'"')) {
								#cho "Bingo $shopVariantId[$z] $shopBarcode[$z]<br>";
							}
						}
					}
				}
				if (!$newVariantId) {
					$qtxt = " SELECT count(id) as count from variant_varer where vare_id = '$variantVareId[$y]'";
#cho __line__." $qtxt<br>";
					$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#cho $r2['count']."<br>";
					if ($r2['count'] == '1') {
						$qtxt = " SELECT id from variant_varer where vare_id = '$variantVareId[$y]'";
#cho __line__." $qtxt<br>";
						if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
							$newVariantId = $r2['id'];
							$qtxt = "update batch_salg set variant_id = '$newVariantId' where id = '$batchSalgId[$y]'";
#cho __line__." $qtxt<br>";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
							$qtxt = "update ordrelinjer set variant_id = '$newVariantId' where id = '$lineId[$y]'";
#cho __line__." $qtxt<br>";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						}		
					}
				}
				if (!$newVariantId) exit;
			}
		}
  }
}

#cho __line__." ".count($variantVareId);
for ($y=0;$y<count($variantVareId);$y++) {
	$qtxt = "select id from batch_kob  where vare_id = '$variantVareId[$y]' and linje_id = '0' and variant_id = '0'";
#	#cho "$qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$qtxt = "delete from batch_kob where id = '$r[id]'";
		#cho "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$qtxt = "select id from batch_salg where vare_id = '$variantVareId[$y]' and linje_id = '0' and variant_id = '0'";
#	#cho "$qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$qtxt = "delete from batch_salg where id = '$r[id]'";
		#cho "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$qtxt = "select id from lagerstatus where vare_id = '$variantVareId[$y]' and variant_id = '0'";
#	#cho "$qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$qtxt = "delete from lagerstatus where id = '$r[id]'";
		#cho "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}

#cho "----------------- N $n<br>";
	$i=0;
	$BatchVariantId = array();
	$qtxt = "select variant_id, sum(antal) as qty from batch_kob where vare_id = '$variantVareId[$y]' group by variant_id order by variant_id";
	#cho "$qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$BatchVariantId[$i] = $r['variant_id'];
		$BatchVariantQty[$i] = $r['qty'];
#cho __line__." $BatchVariantId[$i] > $BatchVariantQty[$i] ($r[qty])<br>";
		$i++;
	 }
	for ($i=0;$i<count($BatchVariantId);$i++) {
		$qtxt = "select sum(antal) as qty from batch_salg where vare_id = '$variantVareId[$y]' and variant_id = $BatchVariantId[$i]";
#cho __line__." $qtxt<br>";		
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$BatchVariantQty[$i]-= $r['qty'];
#cho __line__." $BatchVariantId[$i] > $BatchVariantQty[$i] ($r[qty])<br>";		
		}
	}
	$i = count($BatchVariantId);
	$qtxt = "select variant_id, sum(antal) as qty from batch_salg where vare_id = '$variantVareId[$y]' group by variant_id order by variant_id";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!in_array($r['variant_id'],$BatchVariantId)) {
			$BatchVariantId[$i] = $r['variant_id'];
			$BatchVariantQty[$i] = $r['qty']*-1;
#cho __line__." $BatchVariantId[$i] > $BatchVariantQty[$i] ($r[qty])<br>";		
			$i++;
		}
  }
	$newTotalSum = 0; 
	#cho __line__." ".count($BatchVariantId)."<br>";
	for ($i=0;$i<count($BatchVariantId);$i++) {
		$qtxt = "update lagerstatus set vare_id = '$variantVareId[$y]' where variant_id = '$BatchVariantId[$i]'";
#cho __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "select id,beholdning from lagerstatus where vare_id = '$variantVareId[$y]' and variant_id = '$BatchVariantId[$i]'";
#cho __line__." $qtxt<br>";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$qtxt = "update lagerstatus set beholdning = '$BatchVariantQty[$i]' where vare_id = '$variantVareId[$y]' and variant_id = '$BatchVariantId[$i]'";
#cho __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else if ($BatchVariantQty[$i]) {
			$qtxt = "insert into lagerstatus (vare_id,variant_id,beholdning,lager) values ";
			$qtxt.= "('$variantVareId[$y]','$BatchVariantId[$i]','$BatchVariantQty[$i]','1')"; 
			#cho __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		$qtxt = "update variant_varer set variant_beholdning = '$BatchVariantQty[$i]' where vare_id = '$variantVareId[$y]' and id = '$BatchVariantId[$i]'";
		#cho __line__." $qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$newTotalSum+= $BatchVariantQty[$i];
	}
	$qtxt = "select varenr, beholdning from varer where id = '$variantVareId[$y]'";
#cho __line__." $qtxt<br>";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		if ($r['beholdning'] - $newTotalSum != 0) echo "Vnr $r[varenr] - Ext. beholdning ". $r['beholdning']*1 ." ny beholdning $newTotalSum<br>";
		else echo "Vnr $r[varenr] - Ingen ændring<br>";
	}
	$qtxt = "delete from lagerstatus where vare_id = '$variantVareId[$y]' and variant_id = '0'";
	#cho __line__." $qtxt<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "update varer set beholdning = '$newTotalSum', varianter = '1' where id = '$variantVareId[$y]'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
transaktion('commit');

?>
