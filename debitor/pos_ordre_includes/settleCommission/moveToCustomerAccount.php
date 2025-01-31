<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -- debitor/pos_ordre_includes/settleCommission/moveToCustomerAccount.php --- patch 4.0.9 -- 2023-11-20 --
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
// Copyright (c) 2003-2023 saldi.dk aps
// ----------------------------------------------------------------------
// 20210410 PHR Addad CreatePayList
// 20231120	PHR Added checkLineId to avoid same line counnted more than once.

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

for ($co=0;$co<count($coAc);$co++) { 
	$c=0;
	$cGroup=$itemId=$cItemId=array();

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
				$qtxt = "select id,gruppe from adresser where kontonr='$custNo' and art = 'D'"; 
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if (!$custId=$r['id']) {
					$alerttxt="Konto mangler for varenummer ". $cItemNo[$c][$v] ."!"; 
					alert($alerttxt);
					return "$alerttxt"; 
				}
				$qtxt = "select box2 from grupper where art = 'DG' and kodenr = '$r[gruppe]'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$custAccount = $r['box2'];
				$custAmount  = $commission * -1;
				if ($moveToCustomerAccount) {
#					$qtxt = "select id from openpost where konto_id = '$custId' and konto_nr = '$custNo' and faktnr = '0' ";
#					$qtxt = "and amount = '$custAmount' and beskrivelse = 'Afr: $custNo $moveToCustomerDate - $dd'";
#					if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$qtxt = "insert into openpost "; 
					$qtxt.= "(konto_id,konto_nr,faktnr,amount,beskrivelse,udlignet,transdate,uxtid,kladde_id,refnr,valuta,valutakurs,projekt)";
					$qtxt.= " values ('$custId','$custNo','0','$custAmount','Afr: $custNo $moveToCustomerDate - $dd','0','$dd','". date ('U'). "',";
					$qtxt.= "'0','$reportNumber','DKK','100','')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$debet=$kredit=0;
					($commission > 0)?$kredit=$commission:$debet=abs($commission);
					$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
					$qtxt.="projekt,ansat,ordre_id,kasse_nr,report_number,moms)";
					$qtxt.=" values ";
					$qtxt.="('0','$dd','Kommisionsafregning konto $custNo','$custAccount','0','$debet','$kredit',0,'$afd','$dd','$logtime',";
					$qtxt.="'','$ansat_id','0','0','$reportNumber','0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#				}
				}
				if ($payListId > 0 && $commission > 0) {
					$qtxt = "select firmanavn,bank_reg,bank_konto from adresser where kontonr='$custNo' and art = 'D'"; 
					$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$custName = $r['firmanavn'];
					$custReg = $r['bank_reg'];
					$custBank = $r['bank_konto'];
					while (strlen($custBank)<10) $custBank='0'.$custBank; # kontonumre skal vaere paa 10 cifre
					$custBank = $custReg.$custBank;
					$myRef="Afr: $custNo $refFromDate - $refToDate";

					$qtxt = "insert into betalinger";
					$qtxt.= "(bet_type,fra_kto,egen_ref,til_kto,modt_navn,kort_ref,belob, betalingsdato,valuta,bilag_id,ordre_id,liste_id) ";
					$qtxt.= "values ";
					$qtxt.= "('ERH356','$myBank','".db_escape_string($myRef)."','".db_escape_string($custBank)."',";
					$qtxt.= "'".db_escape_string($custName)."','".db_escape_string($myName)."','". dkdecimal($commission) ."','$payDay',"; 
					$qtxt.="'$myCurrency', '0', '0','$payListId')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		}
		if ($payListId > 0 && $commissionSum > 0) {
			$listDate = date("Y-m-d");
			$timeStamp = microtime();
			$listNote = "Afregning $refFromDate - $refToDate";
			$qtxt = "select id from betalingsliste where id = $payListId";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt = "update betalingsliste set listedate = '$listDate', tidspkt = '$timeStamp', listenote = '$listNote', ";
				$qtxt.= "hvem = '$brugernavn' where id='$payListId'";
			} else {
				$qtxt = "insert into betalingsliste(listedate, listenote, oprettet_af, hvem, tidspkt, bogfort) values ";
				$qtxt.= "('$listDate', '$listNote', '$brugernavn', '$brugernavn', '$timeStamp', '-')";
			}
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		if ($commissionSum) {
			$debet=$kredit=0;
			($commissionSum > 0)?$debet=$commissionSum:$kredit=abs($commissionSum);
			$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
			$qtxt.="projekt,ansat,ordre_id,kasse_nr,report_number,moms)";
			$qtxt.=" values ";
			$qtxt.="('0','$dd','Kommisionsafregning','$cusCoAc[$co]','0','$debet','$kredit',0,'$afd','$dd','$logtime',";
			$qtxt.="'','$ansat_id','0','0','$reportNumber','0')";
#cho __line__." $qtxt<br>";	
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			for ($oid=0;$oid<count($orderId);$oid++) {
				$qtxt = "update ordrer set settletime = '$settletime' where id = $orderId[$oid]";
#cho __line__." $qtxt<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
#cho __line__." $db<br>";	
	}
#cho __line__."<br>";	
}
?> 
