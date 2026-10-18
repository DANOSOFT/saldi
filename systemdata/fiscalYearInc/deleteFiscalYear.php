<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//
// --- systemdata/financialYearInc/deleteFinancialYear.php --- ver 4.1.1 --- 2025-07-02 --
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------------
// 20250629 - PHR $basecurrency  & some cleanup
// 20250702 - PHR Updated deletion of fiscal year
// 20261005 Sawaneh Settings redesign G1.2 (audit F10-F15): returns '' when deleted or the reason it refused instead of
//                  alert + exit; refuses a year somebody has active; the base currency is compared as a value, not as
//                  the text '$baseCurrency'; the report rows are deleted once; no stray HTML after the include. The
//                  stock re-basing never ran (F11: its loops never started and would have died on db_modify(qtxt));
//                  its variables are fixed but it stays switched off until the valuation rule is decided.

function deleteFinancialYear($year, $rebaseStock = false) {
	global $regnaar;

	$year = (int) $year;
	$qtxt = "select * from grupper where art = 'RA' and kodenr = '$year'";
	if (!$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		return 'no year';
	}
	$groupId = $r['id'];
	$startM = str_pad((int) $r['box1'], 2, '0', STR_PAD_LEFT);
	$endM   = str_pad((int) $r['box3'], 2, '0', STR_PAD_LEFT);
	$endY   = (int) $r['box4'];
	$yearBegin = $r['box2'].'-'.$startM.'-01';
	$nextYearBegin = ((int) $r['box2'] + 1).'-'.$startM.'-01';
	$yearEnd = date('Y-m-t', mktime(0, 0, 0, (int) $endM, 1, $endY));

	if ($endY . $endM > (date('Y') - 5) . date('m')) {
		return findtekst('6577|Regnskabsåret kan først slettes 5 år efter periodens slutning', $GLOBALS['sprog_id']);
	}
	$qtxt = "update grupper set box10 = '' where box10 is NULL and art = 'RA'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "select kodenr from grupper where art = 'RA' and kodenr < '$year' and box10 = '' order by kodenr limit 1";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		return findtekst('6578|Det ældste regnskabsår skal slettes først', $GLOBALS['sprog_id']);
	}
	if ($year == (int) $regnaar || db_fetch_array(db_select("select id from brugere where cast(regnskabsaar as text) = '$year' limit 1",__FILE__ . " linje " . __LINE__))) {
		return findtekst('6569|det er dit aktive år', $GLOBALS['sprog_id']);
	}

	$qtxt = "update batch_kob set variant_id = 0 where variant_id is NULL";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "update batch_salg set variant_id = 0 where variant_id is NULL";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);

	$accountId = array();
	// Accounts invoiced up to the end of the year.
	$qtxt = "select distinct(konto_id) from openpost where transdate <= '$yearEnd'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$accountId[] = $r['konto_id'];
	}

	$stockList = array();
	$q = db_select("select kodenr from grupper where art='LG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$stockList[] = (int) $r['kodenr'];
	}
	if (!$stockList) $stockList[] = 0;

	transaktion('begin');
	for ($i=0;$i<count($accountId);$i++) {
		if (getAccountBalance($accountId[$i],$yearBegin,$yearEnd) == 0) {
			$qtxt = "delete from openpost where konto_id = '$accountId[$i]' and transdate <= '$yearEnd'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else {
			if (!function_exists('createAccountPrimo')) include (__DIR__ . '/createAccountPrimo.php');
			createAccountPrimo($accountId[$i],$yearBegin,$yearEnd,$nextYearBegin);
		}
		$orderId = array();
		$qtxt = "select id from ordrer where konto_id = '$accountId[$i]' and fakturadate <= '$yearEnd'";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$orderId[] = $r['id'];
		}
		for ($y = 0; $y < count($orderId); $y++) {
			db_modify("delete from ordrelinjer where ordre_id = '$orderId[$y]'",__FILE__ . " linje " . __LINE__);
			db_modify("delete from ordrer where id = '$orderId[$y]'",__FILE__ . " linje " . __LINE__);
			db_modify("delete from pos_betalinger where ordre_id = '$orderId[$y]'",__FILE__ . " linje " . __LINE__);
		}
	}
	db_modify("delete from report where date <= '$yearEnd'",__FILE__ . " linje " . __LINE__);

	if ($rebaseStock) {
		$itemId = array();
		$qtxt = "select distinct vare_id from batch_kob where (fakturadate >= '2000-01-01' and fakturadate <= '$yearEnd') ";
		$qtxt.= "or (fakturadate is NULL and kobsdate  >= '2000-01-01' and kobsdate <= '$yearEnd')";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$itemId[] = $r['vare_id'];
		}
		$qtxt = "select distinct vare_id from batch_salg where (fakturadate >= '2000-01-01' and fakturadate <= '$yearEnd') ";
		$qtxt.= "or (fakturadate is NULL and salgsdate  >= '2000-01-01' and salgsdate <= '$yearEnd')";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (!in_array($r['vare_id'],$itemId)) $itemId[] = $r['vare_id'];
		}
		$kobPeriod  = "((fakturadate >= '2000-01-01' and fakturadate <= '$yearEnd') or (fakturadate is NULL and kobsdate  >= '2000-01-01' and kobsdate <= '$yearEnd'))";
		$salgPeriod = "((fakturadate >= '2000-01-01' and fakturadate <= '$yearEnd') or (fakturadate is NULL and salgsdate  >= '2000-01-01' and salgsdate <= '$yearEnd'))";
		foreach ($itemId as $item) {
			$item = (int) $item;
			$r = db_fetch_array(db_select("select gruppe from varer where id = '$item'",__FILE__ . " linje " . __LINE__));
			$itemGroup = $r ? $r['gruppe'] : 0;
			$stockItem = 0;
			if ($itemGroup) {
				$r = db_fetch_array(db_select("select box8 from grupper where art = 'VG' and kodenr = '$itemGroup'",__FILE__ . " linje " . __LINE__));
				$stockItem = $r ? $r['box8'] : 0;
			}
			if (!$stockItem) continue;
			$variants = array();
			$q = db_select("select distinct(variant_id) as variant_id from variant_varer where vare_id = '$item'",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$variants[] = (int) $r['variant_id'];
			}
			if (!$variants) $variants[] = 0;
			foreach ($stockList as $stock) {
				foreach ($variants as $variant) {
					$r = db_fetch_array(db_select("select sum(antal) as qty, sum(pris * antal) as value, sum(rest) as left_qty from batch_kob where vare_id = $item and variant_id = '$variant' and lager = $stock and $kobPeriod",__FILE__ . " linje " . __LINE__));
					$qty = $r ? (float) $r['qty'] : 0;
					$left = $r ? (float) $r['left_qty'] : 0;
					$avgPrice = $qty ? (float) $r['value'] / $qty : 0;
					$r = db_fetch_array(db_select("select sum(antal) as qty from batch_salg where vare_id = $item and lager = $stock and variant_id = '$variant' and $salgPeriod",__FILE__ . " linje " . __LINE__));
					if ($r) $qty -= (float) $r['qty'];
					db_modify("delete from batch_kob where vare_id = $item and variant_id = '$variant' and lager = $stock and $kobPeriod",__FILE__ . " linje " . __LINE__);
					db_modify("delete from batch_salg where vare_id = $item and variant_id = '$variant' and lager = $stock and $salgPeriod",__FILE__ . " linje " . __LINE__);
					if ($qty > 0) {
						$qtxt = "insert into batch_kob (kobsdate,fakturadate,vare_id,variant_id,linje_id,ordre_id,pris,antal,rest,lager) values ";
						$qtxt.= "('$yearEnd','$yearEnd','$item','$variant','0','0','$avgPrice','$qty','$left','$stock')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				}
			}
		}
	}

	$deleteLedgerId = array();
	$qtxt = "select distinct(kladde_id) as kladde_id from kassekladde where transdate <= '$yearEnd' ";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$deleteLedgerId[] = (int) $r['kladde_id'];
	}
	for ($i=0; $i<count($deleteLedgerId); $i++) {
		$qtxt = "select id from kassekladde where kladde_id = $deleteLedgerId[$i] and transdate > '$yearEnd' limit 1";
		if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			db_modify("delete from kassekladde where kladde_id = '$deleteLedgerId[$i]' and transdate <= '$yearEnd'",__FILE__ . " linje " . __LINE__);
		} else {
			db_modify("delete from kladdeliste where id = '$deleteLedgerId[$i]'",__FILE__ . " linje " . __LINE__);
			db_modify("delete from kassekladde where kladde_id = '$deleteLedgerId[$i]'",__FILE__ . " linje " . __LINE__);
		}
	}
	db_modify("delete from kassekladde where transdate <='$yearEnd'",__FILE__ . " linje " . __LINE__);
	db_modify("delete from transaktioner where transdate <='$yearEnd'",__FILE__ . " linje " . __LINE__);
	db_modify("delete from kontoplan where regnskabsaar ='$year'",__FILE__ . " linje " . __LINE__);
	db_modify("update grupper set box10 = '".date('U')."' where art = 'RA' and kodenr ='$year'",__FILE__ . " linje " . __LINE__);
	db_modify("delete from grupper where fiscal_year = '$year'",__FILE__ . " linje " . __LINE__);
	transaktion('commit');
	return '';
}

function getAccountBalance($accountId,$yearBegin,$yearEnd) {
	global $baseCurrency;

	$base = $baseCurrency ? $baseCurrency : 'DKK';
	$accountBalance = 0;
	$qtxt = "select amount, valuta, valutakurs from openpost where konto_id='$accountId' ";
	if ($yearEnd) $qtxt.= "and transdate<='$yearEnd' ";
	$qtxt.= "order by id";
	$q = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$amount = afrund($r['amount'],2);
		$currency = $r['valuta'] ? $r['valuta'] : $base;
		$exRate = $r['valutakurs'] * 1;
		if (!$exRate) $exRate = 100;
		if ($currency != $base && $exRate != 100) $amount = $amount * $exRate / 100;
		$accountBalance = afrund($accountBalance + $amount,2);
	}
	return ($accountBalance);
}
