<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$dd=date("Y-m-d");

$x=0;
$qtxt = "SELECT distinct(vare_id) as vare_id FROM variant_varer ORDER BY vare_id";
#cho "$qtxt<br>";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$variantVareId[$x] = $r['vare_id'];
	$x++;
}

$x=0;
$qtxt = "SELECT id FROM variant_varer ORDER BY vare_id";
#cho "$qtxt<br>";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$variantId[$x] = $r['id'];
#cho __line__. "	$variantId[$x]<br>";
	$x++;
}

for ($y=0;$y<count($variantVareId);$y++) {
	$x=0;
	$batchSalgVariantId=$missingVariantId=array();
	$qtxt = "select * from batch_salg  where vare_id = '$variantVareId[$y]'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!in_array($r['variant_id'],$variantId) && !in_array($r['variant_id'],$missingVariantId)) {
			$missingVariantId[$x]=$r['variant_id'];
			$missingVariantBatchSalgId[$x]=$r['id'];
			$missingVariantBatchKobId[$x]=0;
			$missingVariantLineId[$x]=$r['linje_id'];
#cho __line__." $missingVariantLineId[$x] | $missingVariantBatchKobId[$x] | $missingVariantBatchSalgId[$x]<br>";
			$x++;
		}
	}
	$qtxt = "select * from batch_kob where vare_id = '$variantVareId[$y]'";
	#cho "$qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!in_array($r['variant_id'],$variantId) && !in_array($r['variant_id'],$missingVariantId)) {
			$missingVariantId[$x]=$r['variant_id'];
			$missingVariantBatchKobId[$x]=$r['id'];
			$missingVariantBatchSalgId[$x]=0;
			$missingVariantLineId[$x]=$r['linje_id'];
#cho __line__." $missingVariantBatchKobId[$x] | $missingVariantBatchSalgId[$x]<br>";
			$x++;
		}
	}
	for ($x=0;$x<count($missingVariantId);$x++) {
		#cho __line__." $missingVariantId[$x]<br>";
		if (!$missingVariantLineId[$x]) {
#cho __line__." $missingVariantBatchKobId[$x] | $missingVariantBatchSalgId[$x]<br>";
			if ($missingVariantBatchKobId[$x]) {
				$qtxt = "delete from batch_kob where id = '$missingVariantBatchKobId[$x]'";
				#cho "$qtxt<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			if ($missingVariantBatchSalgId[$x]) {
				$qtxt = "delete from batch_kob where id = '$missingVariantBatchSalgId[$x]'";
				#cho "$qtxt<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		} 
			
		$qtxt = "select shop_variant from shop_varer where saldi_variant = '$missingVariantId[$x]'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$ean="EAN".$r['shop_variant'];
			#cho "$ean<br>";
		}
		$variantName='';
		$qtxt = "select beskrivelse from ordrelinjer where id = '$missingVariantLineId[$x]'";
#cho "$qtxt<br>";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$txt=$r['beskrivelse'];
#cho __line__." $txt<br>";
			if (!$variantName && strpos ($txt,' - ')) { 
#cho __line__." $r2[beskrivelse]<br>";
				$tmpA = explode (' - ',$txt);
				$t=count($tmpA)-1;
				$variantName = $tmpA[$t];
#cho __line__." $variantName<br>";
				if (strpos ($variantName,',')) $variantName = '';
#cho __line__." $variantName<br>";
			}
			if (!$variantName) { 
	#cho __line__." $variantName<br>";
				$tmpA = explode (', ',$txt);
				$t=count($tmpA)-1;
				$variantName = $tmpA[$t];
#cho __line__." >$variantName<<br>";
			}
#cho __line__." $variantName<br>";
			if ($variantName) {
				$tmp=$variantName;
				if (strpos($tmp,' ')) $tmp = str_replace(' ','-',$tmp);
				if (strpos($tmp,'/')) $tmp = str_replace('/','-',$tmp);
				if ($tmp == $newVariant) $tmp = NULL;
				$variantNameType = 0;
				$qtxt = "select id from variant_typer where beskrivelse = '$variantName' ";
				$qtxt.= "or lower(beskrivelse) = '". strtolower($variantName) ."' ";
				$qtxt.= "or upper(beskrivelse) = '". strtoupper($variantName) ."'";
				if ($tmp) {
					$qtxt.= "or beskrivelse = '$tmp' ";
					$qtxt.= "or lower(beskrivelse) = '". strtolower($tmp) ."' ";
					$qtxt.= "or upper(beskrivelse) = '". strtoupper($tmp) ."'";
				}
				if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$variantType = $r['id'];
				}
			}
		}
		if ($missingVariantId[$x] && $variantVareId[$y] && $ean && $variantType) {
			$qtxt = "insert into variant_varer ";
			$qtxt.= "(id,vare_id,variant_type,variant_beholdning,variant_stregkode,";
			$qtxt.= "lager,variant_kostpris,variant_salgspris,variant_vejlpris,variant_id)";
			$qtxt.= " values ";
			$qtxt.= "('$missingVariantId[$x]','$variantVareId[$y]','$variantType','0','$ean',";
			$qtxt.= "'1','0','0','0','1')";
			#cho "$qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
}	
for ($y=0;$y<count($variantVareId);$y++) {
	$qtxt = "select id from batch_kob  where vare_id = '$variantVareId[$y]' and linje_id = '0' and variant_id = '0'";
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
	$variantCheck = array();
	$x=0;
	$qtxt = "select * from lagerstatus where vare_id = '$variantVareId[$y]'";
	#cho __line__." $qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['variant_id'] == '0' || in_array($r['variant_id'],$variantCheck)) {
			$qtxt = "delete from lagerstatus where id = '$r[id]'";
			#cho "$qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else {
			$variantCheck[$x] = $r['variant_id'];
	#cho __line__." $x $variantCheck[$x]<br>";
			$x++;
		}
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
/*
		$qtxt = "update lagerstatus set vare_id = '$variantVareId[$y]' where variant_id = '$BatchVariantId[$i]'";
#cho __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
*/
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
	#cho __line__." NyTotal $newTotalSum<br>";
	}
	$qtxt = "select varenr, beholdning from varer where id = '$variantVareId[$y]'";
#cho __line__." $qtxt<br>";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		if ($r['beholdning'] - $newTotalSum != 0) {
			echo "Vnr $r[varenr] - Ext. beholdning ". $r['beholdning']*1 ." ny beholdning $newTotalSum<br>";
			$qtxt = "update varer set beholdning = '$newTotalSum', varianter = '1' where id = '$variantVareId[$y]'";
		} else echo "Vnr $r[varenr] - Ingen ændring<br>";
#		if ($r['beholdning'] - $newTotalSum != 0) {
#cho __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#		}
	} 
}
transaktion('commit');

?>
