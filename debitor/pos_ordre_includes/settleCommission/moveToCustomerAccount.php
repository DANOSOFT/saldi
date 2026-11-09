<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -- debitor/pos_ordre_includes/settleCommission/moveToCustomerAccount.php --- ver 5.1.0 -- 2026-11-09 --
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
// 20261109 CL/SS SST-919: Credit each customer (open post + collective account) against the commission account.
//                Settle only orders that are not settled yet, and create the payment list before its payments.
//                Refuse a period that is already settled in the journal.
// 20261109 CL/SS SST-919 review: Lock and recheck the orders before posting, so two cash counts can't settle the same sales.
//                Refuse a customer whose debtor group has no collective account.
//                Leave an order out while one of its commission lines has no configured account pair.

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
global $sprog_id;

$settletime = date('U');
$commissionFromDate = usdate($_POST['commissionFromDate'] ?? '');
$commissionToDate = usdate($_POST['commissionToDate'] ?? '');
$refFromDate = str_replace('-', '', dkdato($commissionFromDate));
$refToDate = str_replace('-', '', dkdato($commissionToDate));

$journalSettledTo = '';
$qtxt = "select distinct beskrivelse from openpost where beskrivelse like 'Afr: %' and kladde_id > '0'";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	if (!preg_match('/^Afr: \S+ (.+?) - (.+?)(?: \(.*\))?\s*$/', $r['beskrivelse'], $period)) {
		continue;
	}
	$journalFrom = usdate($period[1]);
	$journalTo = usdate($period[2]);
	if ($journalFrom <= $commissionToDate && $journalTo >= $commissionFromDate && $journalTo > $journalSettledTo) {
		$journalSettledTo = $journalTo;
	}
}
if ($journalSettledTo) {
	$txt = findtekst('7710|Kommission er afregnet i kassekladden til og med %s. Vælg en fra-dato efter denne dato. Kommissionen er ikke afregnet.', $sprog_id);
	alert(sprintf($txt, dkdato($journalSettledTo)));
	return;
}

$debitAccount = array();
if ($commissionAccountNew && $customerCommissionAccountNew) {
	$debitAccount['kn'] = $customerCommissionAccountNew;
}
if ($commissionAccountUsed && $customerCommissionAccountUsed) {
	$debitAccount['kb'] = $customerCommissionAccountUsed;
}

$otherCommission = '';
foreach (array_diff(array('kn', 'kb'), array_keys($debitAccount)) as $prefix) {
	$otherCommission.= "and not exists (select 1 from ordrelinjer other, varer otherItem where other.ordre_id = ordrer.id ";
	$otherCommission.= "and otherItem.id = other.vare_id and otherItem.varenr like '$prefix%' and otherItem.provision > '0' ";
	$otherCommission.= "and other.varenr like '$prefix%' and other.pris != '0' and other.kostpris > '0') ";
}

$customerShare = $customerItemNo = $accountTotal = $settledOrders = array();
foreach ($debitAccount as $prefix => $account) {
	$qtxt = "select ordrelinjer.id as line_id, ordrelinjer.ordre_id, ordrelinjer.antal, ordrelinjer.kostpris, varer.varenr ";
	$qtxt.= "from ordrelinjer, ordrer, varer where ordrelinjer.ordre_id = ordrer.id and varer.id = ordrelinjer.vare_id ";
	$qtxt.= "and (ordrer.art like 'D%' or ordrer.art = 'PO') and ordrer.status >= '3' and ordrer.felt_5 = '$kasse' ";
	$qtxt.= "and ordrer.fakturadate >= '$commissionFromDate' and ordrer.fakturadate <= '$commissionToDate' ";
	$qtxt.= "and (ordrer.settletime = '0' or ordrer.settletime is null) ";
	$qtxt.= "and exists (select 1 from pos_betalinger where pos_betalinger.ordre_id = ordrer.id) $otherCommission";
	$qtxt.= "and ordrelinjer.varenr like '$prefix%' and varer.varenr like '$prefix%' and varer.provision > '0' ";
	$qtxt.= "and ordrelinjer.pris != '0' and ordrelinjer.kostpris > '0' order by varer.varenr, ordrelinjer.id";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$custNo = (string)substr($r['varenr'], 5);
		$cost = afrund($r['kostpris'] * $r['antal'], 2);
		$customerShare[$custNo] = ($customerShare[$custNo] ?? 0) + $cost;
		$customerItemNo[$custNo] = $customerItemNo[$custNo] ?? $r['varenr'];
		$accountTotal[$account] = ($accountTotal[$account] ?? 0) + $cost;
		$settledOrders[(int)$r['ordre_id']] = (int)$r['ordre_id'];
	}
}

if ($settledOrders) {
	$qtxt = "select id from ordrer where id in (" . implode(',', $settledOrders) . ") ";
	$qtxt.= "and (settletime = '0' or settletime is null) for update";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	$lockedOrders = 0;
	while (db_fetch_array($q)) {
		$lockedOrders++;
	}
	if ($lockedOrders != count($settledOrders)) {
		alert(findtekst('7713|Kommissionen er lige blevet afregnet af en anden kasseoptælling. Kommissionen er ikke afregnet igen.', $sprog_id));
		return;
	}
}

$customers = array();
foreach ($customerShare as $custNo => $amount) {
	$amount = afrund($amount, 2);
	if (!$amount) {
		continue;
	}
	$custNo = (string)$custNo;
	$qtxt = "select id, gruppe, firmanavn, bank_reg, bank_konto from adresser ";
	$qtxt.= "where kontonr = '" . db_escape_string($custNo) . "' and art = 'D'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (!$r || !$r['id']) {
		$txt = findtekst('7711|Konto mangler for varenummer %s. Kommissionen er ikke afregnet.', $sprog_id);
		alert(sprintf($txt, $customerItemNo[$custNo]));
		return;
	}
	$qtxt = "select box2 from grupper where art = 'DG' and kodenr = '" . db_escape_string($r['gruppe']) . "' ";
	$qtxt.= "and fiscal_year = '$regnaar'";
	$g = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (!$g || !(int)$g['box2']) {
		$txt = findtekst('7712|Debitorgruppe %s for konto %s mangler en samlekonto. Kommissionen er ikke afregnet.', $sprog_id);
		alert(sprintf($txt, $r['gruppe'], $custNo));
		return;
	}
	$customers[] = array(
		'id'      => (int)$r['id'],
		'no'      => $custNo,
		'amount'  => $amount,
		'account' => (int)$g['box2'],
		'name'    => $r['firmanavn'],
		'bank'    => $r['bank_reg'] . str_pad($r['bank_konto'], 10, '0', STR_PAD_LEFT),
	);
}

$payListId = 0;
$myBank = $myName = $myCurrency = $payDay = '';
if ($createPayList && max(array_merge(array(0), array_column($customers, 'amount'))) > 0) {
	$r = db_fetch_array(db_select("select * from adresser where art = 'S'", __FILE__ . " linje " . __LINE__));
	$myName = $r['firmanavn'];
	$myCtry = (strtolower($r['land']) == 'norway') ? 'NO' : 'DK';
	$myCurrency = ($myCtry == 'NO') ? 'NOK' : 'DKK';
	$myBank = $r['bank_reg'] . str_pad($r['bank_konto'], 10, '0', STR_PAD_LEFT);
	$payDay = date('dmY', date('U') + 60 * 60 * 24);
	$listDate = date("Y-m-d");
	$timeStamp = microtime();
	$listNote = "Afregning $refFromDate - $refToDate";
	$qtxt = "insert into betalingsliste(listedate, listenote, oprettet_af, hvem, tidspkt, bogfort) values ";
	$qtxt.= "('$listDate', '$listNote', '" . db_escape_string($brugernavn) . "', '" . db_escape_string($brugernavn) . "', '$timeStamp', '-')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$r = db_fetch_array(db_select("select max(id) as id from betalingsliste where tidspkt = '$timeStamp'", __FILE__ . " linje " . __LINE__));
	$payListId = (int)$r['id'];
}

$periodText = dkdato($commissionFromDate) . ' - ' . dkdato($commissionToDate);
foreach ($customers as $customer) {
	$custNo = db_escape_string($customer['no']);
	$commission = $customer['amount'];
	$qtxt = "insert into openpost ";
	$qtxt.= "(konto_id,konto_nr,faktnr,amount,beskrivelse,udlignet,transdate,uxtid,kladde_id,refnr,valuta,valutakurs,projekt)";
	$qtxt.= " values ('$customer[id]','$custNo','0','" . ($commission * -1) . "','Afr: $custNo $periodText','0','$dd','" . date('U') . "',";
	$qtxt.= "'0','$reportNumber','DKK','100','')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$debet = $kredit = 0;
	($commission > 0) ? $kredit = $commission : $debet = abs($commission);
	$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
	$qtxt.= "projekt,ansat,ordre_id,kasse_nr,report_number,moms)";
	$qtxt.= " values ";
	$qtxt.= "('0','$dd','Kommisionsafregning konto $custNo','$customer[account]','0','$debet','$kredit',0,'$afd','$dd','$logtime',";
	$qtxt.= "'','$ansat_id','0','0','$reportNumber','0')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	if ($payListId && $commission > 0) {
		$myRef = "Afr: " . $customer['no'] . " $refFromDate - $refToDate";
		$qtxt = "insert into betalinger";
		$qtxt.= "(bet_type,fra_kto,egen_ref,til_kto,modt_navn,kort_ref,belob, betalingsdato,valuta,bilag_id,ordre_id,liste_id) ";
		$qtxt.= "values ";
		$qtxt.= "('ERH356','" . db_escape_string($myBank) . "','" . db_escape_string($myRef) . "','" . db_escape_string($customer['bank']) . "',";
		$qtxt.= "'" . db_escape_string($customer['name']) . "','" . db_escape_string($myName) . "','" . dkdecimal($commission) . "','$payDay',";
		$qtxt.= "'$myCurrency', '0', '0','$payListId')";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
}

foreach ($accountTotal as $account => $commissionSum) {
	$commissionSum = afrund($commissionSum, 2);
	if (!$commissionSum) {
		continue;
	}
	$debet = $kredit = 0;
	($commissionSum > 0) ? $debet = $commissionSum : $kredit = abs($commissionSum);
	$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
	$qtxt.= "projekt,ansat,ordre_id,kasse_nr,report_number,moms)";
	$qtxt.= " values ";
	$qtxt.= "('0','$dd','Kommisionsafregning','" . db_escape_string($account) . "','0','$debet','$kredit',0,'$afd','$dd','$logtime',";
	$qtxt.= "'','$ansat_id','0','0','$reportNumber','0')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

if ($settledOrders) {
	$qtxt = "update ordrer set settletime = '$settletime' where id in (" . implode(',', $settledOrders) . ")";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
?> 
