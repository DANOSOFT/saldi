<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//
// --- systemdata/financialYearInc/deleteFinancialYear.php --- ver 4.0.4 --- 2024-05-24 --
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2022 Saldi.dk ApS
// ----------------------------------------------------------------------------
function deleteFinancialYear($year) {
	
	$itemId = $variantId = array();

	$qtxt = "update batch_kob set variant_id = 0 where variant_id is NULL";  
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "update batch_salg set variant_id = 0 where variant_id is NULL";  
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#cho __line__." Sletter år $year<br>";
	// Finds financial year start & end date 
	$qtxt = "select * from grupper where art = 'RA' and kodenr = '$year'";
	if ($r = db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__))) {
		$groupId = $r['id'];
		$yearBegin = $r['box2'].'-'.$r['box1'].'-01';
		$endY  = $r['box4'];
		$endM  = $r['box3'];
		$endD  = '31';
		$nextYearBegin = $r['box2']+1 .'-'.$r['box1'].'-01';
		if ($endD > 28) {
			while (!checkdate($endM,$endD,$endY)){
				$endD=$endD-1;
				if ($endD<28) break 1;
			}
		}
		$yearEnd = $endY.'-'.$endM.'-'.$endD; 
 	} else {
		return 'no year';
		exit;
	}
	$a = $endY . $endM;
	$b = date('Y')-5 . date('m');
	if ($a > $b) {
		alert("Regnskabsår kan først slettes efter 5 år");
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../systemdata/regnskabsaar.php\">";
		exit;
	}
	$qtxt = "update grupper set box10 = '' where box10 is NULL and art = 'RA'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "select * from grupper where art = 'RA' and id < '$groupId' and box10 != 'on' order by id limit 1";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		alert("Regnskabsår $r[kodenr] skal slettes først");
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../systemdata/regnskabsaar.php\">";
		exit;
	}
	
	$i = 0;
	$accountId = array();
	// finds accounts that has been invoiced prior to end of finacial year.
	$qtxt = "select distinct(konto_id) from openpost where transdate <= '$yearEnd'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$accountId[$i] = $r['konto_id'];
		$i++;
	}
	
/*	
	$qtxt = "select distinct(konto_id) from openpost where transdate < '$yearEnd'";
#cho __line__." $qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$accountId[$i] = $r['konto_id'];
#cho __line__." $accountId[$i]<br>";	
		$i++;
	}
*/
	transaktion('begin');
	$doDelete=1;
#cho __line__." ".count($accountId)."<br>";
	for ($i=0;$i<count($accountId);$i++) {
		$accountBalance[$i] = getAccountBalance($accountId[$i],$yearBegin,$yearEnd);
#cho __line__." $accountId[$i] -> $accountBalance[$i]<br>";
		if ($accountBalance[$i] == 0) {  
			$qtxt = "delete from openpost where konto_id = '$accountId[$i]' and transdate <= '$yearEnd'";
#cho __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else {
			if (!function_exists('createAccountPrimo')) include ('createAccountPrimo.php');
			#cho __line__." $i createAccountPrimo($accountId[$i],$yearBegin,$yearEnd,$nextYearBegin)<br>";
			createAccountPrimo($accountId[$i],$yearBegin,$yearEnd,$nextYearBegin);
			#cho __line__." $i ".count($accountId)."<br>";
##cho __line__." sletter ikke $accountId[$i]<br>";	
#			$doDelete = 0;
		}
		if (!$doDelete) {
			return "Not deleted";
			exit;
		}
#		for ($i = 0; $i < count($accountId); $i++) {
			$y=0;	
			$orderId = array();
			$qtxt = "select id from ordrer where konto_id = $accountId[$i] and fakturadate <= '$yearEnd'";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$orderId[$y] = $r['id'];
#cho "OrderId $orderId[$y]<br>";	
				$y++;
			}
			for ($y = 0; $y < count($orderId); $y++) {
				$qtxt = "delete from ordrelinjer where ordre_id = '$orderId[$y]'";
#cho __line__." $qtxt<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt = "delete from ordrer where id = '$orderId[$y]'";
#cho __line__." $qtxt<br>";
	  		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt = "delete from pos_betalinger where ordre_id = '$orderId[$y]'";
#cho __line__." $qtxt<br>";
 			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			$qtxt = "delete from report where date <= '$yearEnd'";
#cho __line__." $qtxt<br>";
 			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			
			$deleteAccount = 1;
			$qtxt = "select id from ordrer where konto_id = '$accountId[$i]' limit 1";
			if (db_fetch_array($q = db_select($qtxt,__FILE__ . " linje " . __LINE__))) $deleteAccount = 0;
			$qtxt = "select id from historik where konto_id = '$accountId[$i]' and notedate > '$yearEnd' limit 1";
			if (db_fetch_array($q = db_select($qtxt,__FILE__ . " linje " . __LINE__))) $deleteAccount = 0;
			$l = 0;
			$stockId = array();
			$q = db_select("select kodenr from grupper where art='LG' order by kodenr",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$stockId[$l]=$r['kodenr'];
				$l++;
			}
			$y = 0;
			$itemId = array();
			$qtxt = "select distinct vare_id from batch_kob where (fakturadate >= '2000-01-01' and fakturadate <= '$yearEnd') ";
			$qtxt.= "or (fakturadate is NULL and kobsdate  >= '2000-01-01' and kobsdate <= '$yearEnd')";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$itemId[$y] = $r['vare_id'];
#cho __line__." $itemId[$y]<br>";	
				$y++;
			}
			$qtxt = "select distinct vare_id from batch_salg where (fakturadate >= '2000-01-01' and fakturadate <= '$yearEnd') ";
			$qtxt.= "or (fakturadate is NULL and salgsdate  >= '2000-01-01' and salgsdate <= '$yearEnd')";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if (!in_array($r['vare_id'],$itemId)) {
					$itemId[$y] = $r['vare_id'];
#cho __line__." $itemId[$y]<br>";	
					$y++;
				}
			}
			for ($y = 0; $y < count($itemId); $y++) {
#cho __line__." $itemId[$y]<br>";	
				$qty[$y]  = $avgPrice[$y] = 0;
				$stockId[$y] = $variantId[$y] = array();
				$qtxt = "select gruppe from varer where id = '$itemId[$y]'";
				($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$itemGroup[$y] = $r['gruppe']:$itemGroup[$y] = 0;
				if ($itemGroup[$y]) {
					$v=0;
					$qtxt = "select box8 from grupper where art = 'VG' and kodenr = '$itemGroup[$y]'";
					($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$stockItem[$y] = $r['box8']:$stockItem[$y] = 0;
				}
#cho __line__." $itemId[$y]<br>";	
				if ($stockItem[$y]) {
					$qtxt = "select distinct(variant_id) as variant_id from variant_varer where vare_id = '$itemId[$y]'";
					$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while ($r = db_fetch_array($q)) {
						$variantId[$v] = $r['variant_id'];
						$v++;
					}	
					if (!count($variantId[$y])) $variantId[$y][0] = '0';
					for ($l=0;  $l<count($stockId[$y]);$l++) {
						for ($v=0; $v<count($variantId);$v++) { 
							$qtxt = "select count(antal) as qty, sum (pris) as price, sum (rest) as left ";
							$qty.= "from batch_kob where vare_id = $itemId[$y] and variant_id = '$variantId[$v]' and ";
							$qty.= "lager = $stockId[$l] and ((fakturadate >= '2000-01-01' and fakturadate <= '$yearEnd') ";
							$qtxt.= "or (fakturadate is NULL and kobsdate  >= '2000-01-01' and kobsdate <= '$yearEnd'))";
							if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
							$qty[$v]      = $r['qty'];
							$left[$v]     = $r['left'];
							$avgPrice[$v] = $r['price']/$r['qty'];
				
							$qtxt = "select count(antal) as qty from batch_salg where vare_id = $itemId[$y] ";
							$qty.= "and lager = $stockId[$l] and variant_id = '$variantId[$v]' ";
							$qty.= "and ((fakturadate >= '2000-01-01' and fakturadate <= '$yearEnd') ";
							$qtxt.= "or (fakturadate is NULL and salgsdate  >= '2000-01-01' and salgsdate <= '$yearEnd')";
							if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $qty[$v]-= $r['qty'];;
						}
						$qtxt = "delete from batch_kob where vare_id = $itemId[$y] and variant_id = '$variantId[$v]' ";
						$qty.= " and ((fakturadate >= '2000-01-01' and fakturadate <= '$yearEnd') ";
						$qtxt.= "or (fakturadate is NULL and kobsdate  >= '2000-01-01' and kobsdate <= '$yearEnd'))";
#cho __line__." $qtxt<br>";
					db_modify(qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt = "delete from batch_salg where  vare_id = $itemId[$y] and variant_id = '$variantId[$v]' ";
						$qty.= " and ((fakturadate >= '2000-01-01' and fakturadate <= '$yearEnd') ";
						$qtxt.= "or (fakturadate is NULL and salgsdate  >= '2000-01-01' and salgsdate <= '$yearEnd')";
#cho __line__." $qtxt<br>";
						db_modify(qtxt,__FILE__ . " linje " . __LINE__);
						if ($qty[$v] > 0) {
							$qtxt = "insert into batch_kob ";
							$qtxt.= "(kobsdate,fakturadate,vare_id,variant_id,linje_id,ordre_id,pris,antal,rest,lager) values";
							$qtxt.= "('$yearEnd','$yearEnd','$vareId[$y]','$variantId[$v]',";
							$qtxt.= "'0','0','$avgPrice[$v]',$qty[$v],$left[$v],$stockId[$l])";
#cho __line__." $qtxt<br>";
						db_modify(qtxt,__FILE__ . " linje " . __LINE__);
							}
						}
					}
			} else {
				$qtxt = "delete from batch_salg where  vare_id = '$itemId[$y]' ";
				$qtxt.= "and ((fakturadate >= '2000-01-01' and fakturadate <= '$yearEnd') ";
				$qtxt.= "or (fakturadate is NULL and salgsdate  >= '2000-01-01' and salgsdate <= '$yearEnd')";
#cho __line__." $qtxt<br>";
#			db_modify(qtxt,__FILE__ . " linje " . __LINE__);
			} 
	}
#		}
		#cho __line__." $i ".count($accountId)."<br>";
	}
	$i=0;
	$deleteLedgerId = array();
	$qtxt = "select distinct(kladde_id) as kladde_id from kassekladde where transdate <= '$yearEnd' ";
#cho __line__." $qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$deleteLedgerId[$i] = $r['kladde_id'];
#cho __line__." $deleteLedgerId[$i]<br>";
		$i++;
	}
	for ($i=0; $i<count($deleteLedgerId); $i++) {
		$qtxt = "select id from kassekladde where kladde_id = $deleteLedgerId[$i] and transdate > '$yearEnd' limit 1";
		if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$qtxt = "delete from kassekladde where kladde_id = '$deleteLedgerId[$i]' and transdate <= '$yearEnd'";
#cho __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "delete from kladdeliste where id = '$deleteLedgerId[$i]'";
#cho __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "delete from kassekladde where kladde_id = '$deleteLedgerId[$i]'";
#cho __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
	$qtxt = "delete from kassekladde where transdate <='$yearEnd'";
#cho __line__." $qtxt<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "delete from transaktioner where transdate <='$yearEnd'";
#cho __line__." $qtxt<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "delete from kontoplan where regnskabsaar ='$year'";
#cho __line__." $qtxt<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "update grupper set box10 = 'on' where art = 'RA' and kodenr ='$year'";
#cho __line__." $qtxt<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "delete from grupper where fiscal_year = '$year'";
#cho __line__." $qtxt<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	transaktion('commit');

}
function getAccountBalance($accountId,$yearBegin,$yearEnd) {

	if (!isset ($todate)) $todate = NULL;
	if (!isset ($totalsum)) $totalsum = NULL;

	$accountBalance = 0;

	$qtxt = "select * from openpost where konto_id='$accountId' ";
	if ($yearEnd) $qtxt.= "and transdate<='$yearEnd' ";
	$qtxt.= "order by id";
	$q = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$amount=afrund($r['amount'],2);
		$oppCurrency=$r['valuta'];
		if (!$oppCurrency) $oppCurrency='DKK';
		$oppExRate=$r['valutakurs']*1;
		if (!$oppExRate) $oppExRate=100;
		$dkkamount=$amount;
		if ($oppCurrency=='DKK') $dkkAmount = $amount;
		else $dkkAmount = afrund($amount*100/$oppExRate,2);
		$transdate=$r['transdate'];
		if ($oppCurrency!='DKK' && $oppExRate!=100) $amount=$amount*$oppExRate/100;
		$accountBalance=afrund($accountBalance+$amount,2);
	}
	return ($accountBalance);
}

?>
</body></html>
