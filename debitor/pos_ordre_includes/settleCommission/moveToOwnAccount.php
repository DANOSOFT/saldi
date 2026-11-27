<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/settleCommission/moveToOwnAccount.php --- ver 5.1.1 --- 2026-11-19 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20230912 corrected error in VAT sign (was positve when is should be negative)
// 20231120	PHR Added checkLineId to avoid same line counnted more than once.
// 20250701 PHR PHP8
// 20261006 CL/LH SST-824: Reset the commission totals per item group and order the groups, so a group's commission is posted once and the result does not depend on row order.
// 20261008 CDX/NTR per-line query lacked report_number and kostpris > 0 filters; lines from a prior close on the same date could be counted again.
// 20261118 CL/ASR The reclass is dated $commissionDate when posbogfor() sets it to the date of a sale from an earlier fiscal year, else $dd as before; logdate stays $dd.
// 20261118 CL/ASR Every reclass balances and keeps the VAT charged on the sale.
//                 A commission account without a VAT code gets the commission without VAT rows; the sale's VAT was debited and left the voucher unbalanced.
//                 When both accounts have VAT codes, the VAT charged on the sale is moved as charged, also at different rates.
// 20261119 CL/ASR The item group and VAT code rows are those of the fiscal year the commission is settled in ($regnaar), else a row without a year, else the nearest other year's, instead of any year's row.

/**
 * Injected from the local scope of posbogfor() in ../../pos_ordre.php,
 * which includes this file:
 * @var int      $kasse
 * @var int      $reportNumber
 * @var string   $regnaar
 * @var string   $dd
 * @var string|null $commissionDate The date of the sales settled, when they belong to an earlier fiscal year.
 * @var string   $logtime
 * @var int      $ansat_id
 * @var string   $afd
 * @var string   $commissionAccountNew
 * @var string   $commissionAccountUsed
 * @var string[] $fakturadate
 */
$minDate=$fakturadate[0];
$a=count($fakturadate)-1;
$maxDate=$fakturadate[$a];
$coAc=array();
// Dated as the sales of an earlier fiscal year when posbogfor() settles them on their own, else today; registered today.
$commissionTransdate = $commissionDate ?? $dd;
// A ledger has a row per fiscal year for each item group (VG) and VAT code (SM). The reclass uses the rows of the year it
// is settled in ($regnaar), as the sale itself was posted with them. A row without a year (0 or NULL, older ledgers) serves
// every year. Only when the year has neither is the nearest other year's row used, an earlier year before a later one.
$settleYear = (int) $regnaar;
$yearRowOrder = "order by case when fiscal_year = $settleYear then 0 when fiscal_year is null or fiscal_year = 0 then 1 ";
$yearRowOrder.= "when fiscal_year < $settleYear then 2 else 3 end, abs(coalesce(fiscal_year, 0) - $settleYear), id limit 1";

if ($minDate < '2021-01-01') $minDate = '2021-01-01';

if ($commissionAccountNew) {
	$coAc[0] = $commissionAccountNew;
	$itNo[0] = 'kn%';
	if ($commissionAccountUsed) {
		$coAc[1] = $commissionAccountUsed;
		$itNo[1] = 'kb%';
	}
} elseif ($commissionAccountUsed) {
	$coAc[0] = $commissionAccountUsed;
	$itNo[0] = 'kb%';
}

for ($co=0;$co<count($coAc);$co++) { 
	$c=0;
	$cItemId=$cGroup=$itemId=array();

	$qtxt = "select moms from kontoplan where kontonr = '$coAc[$co]' and regnskabsaar <= '$regnaar' ";
	$qtxt.= "order by regnskabsaar desc limit 1";
	// echo __line__." $qtxt<br>";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($toVatCode=substr($r['moms'],1)) {
		$qtxt = "select box1,box2 from grupper where art = 'SM' and kodenr = '$toVatCode' $yearRowOrder";
		// echo __line__." $qtxt<br>";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$toVatAccount = $r['box1']*1;
		$toVatPercent  = $r['box2']*1;
	} else $toVatAccount=$toVatPercent=0;

	$qtxt = "select distinct(gruppe) as cgroup from varer where provision > '0' and varenr like '$itNo[$co]' order by gruppe";
	// echo __line__." $qtxt<br>";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$cGroup[$c]=$r['cgroup'];
		$c++;
	}
	$v=0;
	$qtxt = "select distinct(ordrelinjer.vare_id) from ordrelinjer,ordrer,pos_betalinger where ";
	$qtxt.= "(ordrer.art like 'D%' or ordrer.art = 'PO') and ordrer.fakturadate >= '$minDate' and ordrer.fakturadate <= '$maxDate' ";
	$qtxt.= "and ordrelinjer.varenr like '$itNo[$co]' ";
	$qtxt.= "and (ordrer.report_number = '0' or ordrer.report_number = '$reportNumber') and ordrelinjer.ordre_id = ordrer.id ";
	$qtxt.= "and pos_betalinger.ordre_id = ordrer.id and ordrer.felt_5 = '$kasse' order by ordrelinjer.vare_id";
	// echo __line__." $qtxt<br>";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$itemId[$v]=$r['vare_id'];
		$v++;
	}
	
	for ($c=0;$c<count($cGroup);$c++) {
		$v=0;
		$cItemId[$c]=array();
		$qtxt = "select id, provision from varer where gruppe = '$cGroup[$c]' and provision > '0' and varenr like '$itNo[$co]' order by id";
		// echo __line__." $qtxt<br>";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if (in_array($r['id'],$itemId)) {
				$cItemId[$c][$v]=$r['id'];
				$v++;
			}
		}
	}
	$checkLineId = array();
	$i=0;
	// echo __line__." ".count($cGroup)."<br>";
	for ($c = 0;$c < count($cGroup);$c++) {
		// Each group posts only its own commission. Without this reset the previous
		// group's amount (minus its VAT) was carried over and posted again under the
		// next group, even when nothing was sold in it (SST-824).
		$commission = $costVat = $commissionVat = $fromVat = $toVat = 0;
		// echo __line__." ".count($cItemId[$c])."<br>";
		for ($v=0;$v<count($cItemId[$c]);$v++) {
			$kontrol = 0;
			$qtxt = "select ordrelinjer.id,ordrelinjer.varenr,ordrelinjer.ordre_id,ordrelinjer.pris,ordrelinjer.antal,";
			$qtxt.= "ordrelinjer.rabat,ordrelinjer.kostpris,ordrelinjer.momssats ";
			$qtxt.= "from ordrelinjer,ordrer,pos_betalinger where ";
			$qtxt.= "(ordrer.art like 'D%' or ordrer.art = 'PO') and ordrer.fakturadate >= '$minDate' ";
			$qtxt.= "and ordrer.fakturadate <= '$maxDate' and ordrelinjer.antal != '0' and ordrelinjer.pris != '0' ";
			$qtxt.= "and ordrer.status = '3' and ordrelinjer.ordre_id = ordrer.id ";
			$qtxt.= "and pos_betalinger.ordre_id = ordrer.id and ordrelinjer.vare_id='". $cItemId[$c][$v] ."' ";
			$qtxt.= "and (ordrer.report_number = '0' or ordrer.report_number = '$reportNumber') ";
			$qtxt.= "and ordrer.felt_5 = '$kasse' order by ordrer.id";
			// echo __line__." $qtxt<br>";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				if (!in_array($r['id'],$checkLineId)) {
					$checkLineId[$i]=$r['id'];
					$linePrice   = afrund($r['pris']*$r['antal'] - ($r['pris']*$r['antal']*$r['rabat']/100),2);
					$lineVat     = $linePrice * $r['momssats']/100;
					$cost        = $r['kostpris'] * $r['antal'];
					$costVat    +=afrund( $cost  * $r['momssats']/100,2);
					$commission += afrund($linePrice-$cost,2);
					$kontrol += afrund($linePrice-$cost,2);
					// echo __line__." $r[id] $r[varenr] $r[antal] $r[ordre_id] $linePrice | $cost ".afrund($linePrice-$cost,2) ." > $commission > $kontrol<br>";
					$i++;
				}
			}
		}
		$qtxt = "select box4 from grupper where art = 'VG' and kodenr = '$cGroup[$c]' $yearRowOrder";
		// echo __line__." $qtxt<br>";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$fromAccount=$r['box4'];
		$qtxt = "select moms from kontoplan where kontonr = '$fromAccount' and regnskabsaar <= '$regnaar' ";
		$qtxt.= "order by regnskabsaar desc limit 1";
		// echo __line__." $qtxt<br>";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($fromVatCode=substr($r['moms'],1)) {
			$qtxt = "select box1,box2 from grupper where art = 'SM' and kodenr = '$fromVatCode' $yearRowOrder";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$fromVatAccount = $r['box1']*1;
			$fromVatPercent  = $r['box2']*1;
		} else $fromVatAccount=$fromVatPercent=0;
		// echo __line__." $commission && $fromVatPercent<br>";
		// The sale's VAT on the commission is moved only to a commission account with a VAT code of its own. Without one
		// the commission is moved without VAT: the VAT charged on the sale stays where the sale posted it.
		if ($commission && $fromVatPercent && $toVatPercent) {
			// if ($costVat) $fromVat = afrund($commission * $fromVatPercent / (100+$fromVatPercent),2);
			$fromVat = afrund($commission * $fromVatPercent / 100,2);
			$debet=$kredit=0;
			($fromVat > 0)?$debet=$fromVat:$kredit=abs($fromVat);
			$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
			$qtxt.="projekt,ansat,ordre_id,kasse_nr,report_number,moms)";
			$qtxt.=" values ";
			$qtxt.="('0','$commissionTransdate','Moms af kommisionssalg, Kasse $kasse','$fromVatAccount','0','$debet','$kredit',0,'$afd','$dd','$logtime','',";
			$qtxt.="'$ansat_id','0','$kasse','$reportNumber','0')";
			// echo __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else $commissionVat = 0;
		if ($commission) {
			$debet=$kredit=0;
			($commission)?$debet=$commission:$kredit=abs($commission);
			$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
			$qtxt.="projekt,ansat,ordre_id,kasse_nr,report_number,moms)";
			$qtxt.=" values ";
			$qtxt.="('0','$commissionTransdate','Kommisionssalg, Kasse $kasse','$fromAccount','0','$debet','$kredit',0,'$afd','$dd','$logtime','',";
			$qtxt.="'$ansat_id','0','$kasse','$reportNumber','$commissionVat')";
			// echo __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		if ($commission && $toVatPercent) {
			// The VAT charged on the sale (fromVat) is moved as charged, also when the commission account's rate differs.
			if ($fromVatPercent) $toVat = $fromVat;
			else $toVat = afrund($commission * $toVatPercent / (100+$toVatPercent),2);
			$debet=$kredit=0;
			($toVat > 0)?$kredit=$toVat:$debet=abs($toVat);
			$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
			$qtxt.="projekt,ansat,ordre_id,kasse_nr,report_number,moms)";
			$qtxt.=" values ";
			$qtxt.="('0','$commissionTransdate','Moms af kommisionssalg, Kasse $kasse','$toVatAccount','0','$debet','$kredit',0,'$afd','$dd','$logtime','',";
			$qtxt.="'$ansat_id','0','$kasse','$reportNumber','0')";
			// echo __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else $toVat = 0;
		if ($commission) {
			if (!$fromVatPercent) $commission-= $toVat;
 			$debet=$kredit=0;
			($commission > 0)?$kredit=$commission:$debet=abs($commission);
			($debet > $kredit)?$toVat = abs($toVat):$toVat = -abs($toVat); #20230912
			$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
			$qtxt.="projekt,ansat,ordre_id,kasse_nr,report_number,moms)";
			$qtxt.=" values ";
			$qtxt.="('0','$commissionTransdate','Kommisionssalg, Kasse $kasse','$coAc[$co]','0','$debet','$kredit',0,'$afd','$dd','$logtime',";
			$qtxt.="'','$ansat_id','0','$kasse','$reportNumber','$toVat')";
			// echo __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
}
// exit;
?> 
