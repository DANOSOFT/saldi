<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -- debitor/pos_ordre_includes/settleCommission/moveToCustomerAccount.php --- ver 5.1.0 -- 2026-11-05 --
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
// 20210410 PHR Addad CreatePayList
// 20231120	PHR Added checkLineId to avoid same line counnted more than once.
// 20261105 CL/LH Afregn kommission posts each seller's share on the samlekonto of the seller's debtor group in the year posted and gives the seller an Afr open post.
//                These sat behind $moveToCustomerAccount, which nothing set, so only the debits to the customer commission accounts were posted.
//                A share is posted also when the item group's shares cancel out, and the payment list is made whenever the group adds a payment to it.
//                The whole settlement is worked out before anything is written, so a seller without an account or samlekonto stops it with no payment, posting or open post written.
//                A seller's payment carries the id of the seller's Afr open post in openpost_id, so Fra liste on the payment list does not pay that open post again; ordre_id stays 0.

/**
 * Injected from the local scope of posbogfor() in ../../pos_ordre.php,
 * which includes this file:
 * @var int    $kasse
 * @var int    $reportNumber
 * @var string $regnaar
 * @var string $dd
 * @var string $logtime
 * @var int    $ansat_id
 * @var int    $afd
 * @var string $brugernavn
 * @var string $createPayList
 * @var string $commissionAccountNew
 * @var string $commissionAccountUsed
 * @var string $customerCommissionAccountNew
 * @var string $customerCommissionAccountUsed
 */
$coAc=$itNo=array();
/*
$qtxt = "select var_value from settings where var_name = 'commissionFromDate'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$commissionFromDate=$r['var_value'];
if (!$commissionFromDate) $commissionFromDate='2021-01-01'; 
*/
$settletime=date('U');
if (isset($_POST['commissionFromDate'])) {
	$commissionFromDate = usdate($_POST['commissionFromDate']);
	$refFromDate = str_replace('-','',dkdato($commissionFromDate));
}
if (isset($_POST['commissionToDate'])) {
	$commissionToDate = usdate($_POST['commissionToDate']);
	$refToDate = str_replace('-','',dkdato($commissionToDate));
}

if ($createPayList) {
	$x = 0;
	$pList = array();
	$qtxt = "select id from betalingsliste where bogfort = 'V' order by id";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$pList[$x] == $r['id'];
		$x++;
	}
	$payListId=1;
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "select liste_id from betalinger order by liste_id"; 
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['liste_id'] == $payListId || in_array($payListId,$pList)) $payListId++;
	}
	$r=db_fetch_array(db_select("select * from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
	$myBank=$r['bank_konto'];
	$myReg=$r['bank_reg'];
	$myName=$r['firmanavn'];
	$myEan=$r['ean'];
	$myCtry=$r['land'];
	(strtolower($myCtry)=='norway')?$myCtry = 'NO':$myCtry = 'DK'; 
	($myCtry == 'NO')?$myCurrency = 'NOK':$myCurrency = 'DKK'; 
	while (strlen($myBank)<10) $myBank='0'.$myBank;
	$myBank = $myReg.$myBank;
	$payDay = date('U')+60*60*24;
	$payDay = date('dmY',$payDay); 
} else $payListId=0;
if ($commissionAccountNew && $customerCommissionAccountNew) {
	$coAc[0]    = $commissionAccountNew;
	$cusCoAc[0] = $customerCommissionAccountNew;
	$itNo[0]    = 'kn%';
	if ($commissionAccountUsed && $customerCommissionAccountUsed) {
		$coAc[1]    = $commissionAccountUsed;
		$cusCoAc[1] = $customerCommissionAccountUsed;
		$itNo[1]    = 'kb%';
	}
} elseif ($commissionAccountUsed && $customerCommissionAccountUsed) {
	$coAc[0]    = $commissionAccountUsed;
	$cusCoAc[0] = $customerCommissionAccountUsed;
	$itNo[0]    = 'kb%';
}

// The settlement is worked out first and written below, once every seller has an account.
$settleGroups = array();
for ($co=0;$co<count($coAc);$co++) { 
	$c=0;
	$cGroup=$itemId=$cItemId=$cItemNo=array();

	$qtxt = "select moms from kontoplan where kontonr = '$coAc[$co]' and regnskabsaar <= '$regnaar' ";
	$qtxt.= "order by regnskabsaar desc limit 1";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($toVatCode=substr($r['moms'],1)) {
		$qtxt = "select box1,box2 from grupper where art = 'SM' and kodenr = '$toVatCode' ";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$toVatAccount = $r['box1']*1;
		$toVatPercent  = $r['box2']*1;
	} else $toVatAccount=$toVatPercent=0;

	$qtxt = "select distinct(gruppe) as cgroup from varer where provision > '0' and varenr like '$itNo[$co]'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$cGroup[$c]=$r['cgroup'];
		$c++;
	}
	$qtxt = "select distinct(ordrelinjer.vare_id) as vare_id from ordrelinjer,ordrer,pos_betalinger where ";
	$qtxt.= "(ordrer.art like 'D%' or ordrer.art = 'PO') and ordrer.fakturadate >= '$commissionFromDate' ";
	$qtxt.= "and ordrer.fakturadate <= '$commissionToDate' "; #and (settletime = '0' or settletime = '$settletime') ";
	$qtxt.= "and ordrelinjer.varenr like '$itNo[$co]' and ordrelinjer.kostpris > '0' and ordrer.status >= '3' ";
	$qtxt.= "and ordrelinjer.ordre_id = ordrer.id and pos_betalinger.ordre_id = ordrer.id and ordrer.felt_5 = '$kasse' ";
	$qtxt.= "order by ordrelinjer.vare_id";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$v=0;
	while ($r=db_fetch_array($q)) {
		$itemId[$v]=$r['vare_id'];
		$v++;
	}
	
	for ($c=0;$c<count($cGroup);$c++) {
		$v=0;
		$cItemId[$c] = array();
		$qtxt = "select id, varenr, provision from varer where gruppe = '$cGroup[$c]' and provision > '0' and varenr like '$itNo[$co]'";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if (in_array($r['id'],$itemId)) {
				$cItemId[$c][$v]=$r['id'];
				$cItemNo[$c][$v]=$r['varenr'];
				$v++;
			}
		}
	}
	$checkLineId = $orderId = array();
	$i = $oid = 0;
	for ($c = 0 ; $c < count($cGroup) ; $c++) {
		$commissionSum=0;
		$sellerShares = array();
		for ($v=0;$v<count($cItemId[$c]);$v++) {
			$commission = 0;
			$qtxt = "select ordrer.id as orderid,ordrelinjer.pris,ordrelinjer.antal,ordrelinjer.rabat,ordrelinjer.kostpris,";
			$qtxt.= "ordrelinjer.momssats,ordrelinjer.id as line_id from ordrelinjer,ordrer,pos_betalinger ";
			$qtxt.= "where (ordrer.art like 'D%' or ordrer.art = 'PO') ";
			$qtxt.= "and ordrer.fakturadate <= '$commissionToDate' and ordrer.fakturadate >= '$commissionFromDate' ";
			$qtxt.= "and ordrelinjer.pris != '0' and ordrer.status >= '3' "; #and (settletime = '0' or settletime = '$settletime')
			$qtxt.= "and ordrelinjer.ordre_id = ordrer.id and pos_betalinger.ordre_id = ordrer.id and ";
			$qtxt.= "ordrelinjer.vare_id='". $cItemId[$c][$v] ."' and ordrer.felt_5 = '$kasse'";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				if ($r['kostpris'] > 0 && !in_array($r['line_id'],$checkLineId)) {
					if (!in_array($r['orderid'],$orderId)) {
						$orderId[$oid] = $r['orderid'];
						$oid++;
					}
					$linePrice   = $r['pris']*$r['antal'] - ($r['pris']*$r['antal']*$r['rabat']/100);
					$linePrice   = afrund($linePrice + ($linePrice * $r['momssats']/100),2);
					
					$cost        = $r['kostpris'] * $r['antal'];
#					$cost        = $cost + ($cost  * $r['momssats']/100);
					$commission += afrund($cost,2); 
					$checkLineId[$i] = $r['line_id'];
					$i++;
				}
			}
			$commissionSum += $commission;
			if ($commission) {
				$custNo=substr($cItemNo[$c][$v],5);	
				$qtxt = "select id,gruppe,firmanavn,bank_reg,bank_konto from adresser where kontonr='" . db_escape_string($custNo) . "' and art = 'D'";
				$seller=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$custId = $seller ? (int) $seller['id'] : 0;
				$custAccount = 0;
				if ($custId) {
					$qtxt = "select box2 from grupper where art = 'DG' and kodenr = '" . (int) $seller['gruppe'] . "' and fiscal_year = '" . (int) $regnaar . "'";
					$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$custAccount = $r ? (int) $r['box2'] : 0;
				}
				if (!$custId || !$custAccount) {
					$alerttxt="Konto mangler for varenummer ". $cItemNo[$c][$v] ."!"; 
					alert($alerttxt);
					return "$alerttxt"; 
				}
				// Written below, with the other shares, once every seller in the settlement has an account.
				$sellerShares[] = array(
					'custId' => $custId, 'custNo' => $custNo, 'custAccount' => $custAccount, 'commission' => $commission,
					'name' => (string) $seller['firmanavn'], 'bankReg' => (string) $seller['bank_reg'], 'bankAccount' => (string) $seller['bank_konto'],
				);
			}
		}
		$settleGroups[] = array(
			'customerCommissionAccount' => $cusCoAc[$co], 'commissionSum' => $commissionSum,
			'sellerShares' => $sellerShares, 'orderIds' => $orderId,
		);
	}
}

// Every seller has an account; nothing was written before here.
foreach ($settleGroups as $settleGroup) {
	// Each seller's share on the samlekonto of the seller's debtor group, and an Afr open post the seller is paid against.
	// A share is posted also when the group's shares cancel out (one seller's sale and another seller's return), as the
	// shares then balance each other.
	$payments = 0;
	foreach ($settleGroup['sellerShares'] as $share) {
		$sellerNo = db_escape_string($share['custNo']);
		$sellerAmount = $share['commission'] * -1;
		$afrText = db_escape_string("Afr: " . $share['custNo'] . " " . dkdato($commissionFromDate) . " - " . dkdato($commissionToDate));
		$qtxt = "insert into openpost ";
		$qtxt.= "(konto_id,konto_nr,faktnr,amount,beskrivelse,udlignet,transdate,uxtid,kladde_id,refnr,valuta,valutakurs,projekt)";
		$qtxt.= " values ('" . $share['custId'] . "','$sellerNo','0','$sellerAmount','$afrText','0','$dd','". date ('U'). "',";
		$qtxt.= "'0','$reportNumber','DKK','100','')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		// The open post just written: this report's open posts are written only here, in the cash count's transaction.
		$qtxt = "select max(id) as id from openpost where konto_id = '" . $share['custId'] . "' and refnr = '" . (int) $reportNumber . "'";
		$openpost = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$debet=$kredit=0;
		($share['commission'] > 0)?$kredit=$share['commission']:$debet=abs($share['commission']);
		$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
		$qtxt.="projekt,ansat,ordre_id,kasse_nr,report_number,moms)";
		$qtxt.=" values ";
		$qtxt.="('0','$dd','Kommisionsafregning konto $sellerNo','" . $share['custAccount'] . "','0','$debet','$kredit',0,'$afd','$dd','$logtime',";
		$qtxt.="'','$ansat_id','0','0','$reportNumber','0')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		if ($payListId > 0 && $share['commission'] > 0) {
			$custBank = $share['bankAccount'];
			while (strlen($custBank)<10) $custBank='0'.$custBank; # kontonumre skal vaere paa 10 cifre
			$custBank = $share['bankReg'].$custBank;
			$myRef = "Afr: " . $share['custNo'] . " $refFromDate - $refToDate";

			// openpost_id is the open post this payment pays, as debitor/betalinger.php's "Fra liste" writes it: that is how
			// Fra liste knows this seller's open post is already on a list. ordre_id is an order (the list's link and the XML
			// export read it so), and the report number is every seller's refnr.
			$qtxt = "insert into betalinger";
			$qtxt.= "(bet_type,fra_kto,egen_ref,til_kto,modt_navn,kort_ref,belob, betalingsdato,valuta,bilag_id,ordre_id,openpost_id,liste_id) ";
			$qtxt.= "values ";
			$qtxt.= "('ERH356','".db_escape_string($myBank)."','".db_escape_string($myRef)."','".db_escape_string($custBank)."',";
			$qtxt.= "'".db_escape_string($share['name'])."','".db_escape_string($myName)."','". dkdecimal($share['commission']) ."','$payDay',";
			$qtxt.="'$myCurrency', '0', '0', '" . (int) $openpost['id'] . "','$payListId')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$payments++;
		}
	}
	// The list the payments are on, also when the group's shares add up to nothing.
	if ($payments) {
		$listDate = date("Y-m-d");
		$timeStamp = microtime();
		$listNote = "Afregning $refFromDate - $refToDate";
		$qtxt = "select id from betalingsliste where id = $payListId";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$qtxt = "update betalingsliste set listedate = '$listDate', tidspkt = '$timeStamp', listenote = '$listNote', ";
			$qtxt.= "hvem = '" . db_escape_string($brugernavn) . "' where id='$payListId'";
		} else {
			$qtxt = "insert into betalingsliste(listedate, listenote, oprettet_af, hvem, tidspkt, bogfort) values ";
			$qtxt.= "('$listDate', '$listNote', '" . db_escape_string($brugernavn) . "', '" . db_escape_string($brugernavn) . "', '$timeStamp', '-')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$commissionSum = $settleGroup['commissionSum'];
	if ($commissionSum) {
		$debet=$kredit=0;
		($commissionSum > 0)?$debet=$commissionSum:$kredit=abs($commissionSum);
		$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
		$qtxt.="projekt,ansat,ordre_id,kasse_nr,report_number,moms)";
		$qtxt.=" values ";
		$qtxt.="('0','$dd','Kommisionsafregning','" . $settleGroup['customerCommissionAccount'] . "','0','$debet','$kredit',0,'$afd','$dd','$logtime',";
		$qtxt.="'','$ansat_id','0','0','$reportNumber','0')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	if ($settleGroup['sellerShares']) {
		foreach ($settleGroup['orderIds'] as $settledOrderId) {
			$qtxt = "update ordrer set settletime = '$settletime' where id = " . (int) $settledOrderId;
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
}
?> 
