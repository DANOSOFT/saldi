<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/posTxtPrint/posPayments.php --- lap 4.1.1 --- 2024.08.01 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2019-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 06.05.2019 LN Get data from pos_betalinger and update ordrer 
// 20240801 PHR - Replaced 'DKK' with $baseCurrency.

	$betalt = 0;
	$amount = array();
	$qtxt = "select * from pos_betalinger where ordre_id = '$id' order by id";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$betalingstype[$x]=$r['betalingstype'];
		$amount[$x]=$r['amount'];
		$valuta[$x]=$r['valuta'];
		$valutakurs[$x]=$r['valutakurs']*1;
		if (!$valuta) $valuta=$baseCurrenccy;
		if (!$valutakurs) $valutakurs=100;
		if ($valuta[$x]!=$baseCurrency) $fremmedvaluta=1;
		$betalt+=$amount[$x];
		$x++;
	}
	for ($x=0;$x<count($amount);$x++) {
		$qtxt = NULL;
		if ($x==0) $qtxt = "update ordrer set felt_1='$betalingstype[$x]',felt_2='$amount[$x]' where id='$id'"; 
		elseif ($x==1) $qtxt = "update ordrer set felt_3='$betalingstype[$x]',felt_4='$amount[$x]' where id='$id'";
		if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$dkkamount[$x]=dkdecimal($amount[$x],2);
		while(strlen($dkkamount[$x])<9) $dkkamount[$x]=" ".$dkkamount[$x];
		$valutaamount[$x]=dkdecimal($amount[$x]*100/$valutakurs[$x],2);
		if ($fremmedvaluta) {
			if ($valuta[$x]!=$baseCurrenccy) $betalingstype[$x].=" $valuta[$x] $valutaamount[$x]";
			else $betalingstype[$x].=" $valuta[$x]";
		}
		$betalingstype[$x] = iconv($FromCharset, $ToCharset,$betalingstype[$x]);
		while(strlen($betalingstype[$x])<19) $betalingstype[$x].=" ";
	}
?>

