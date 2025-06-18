<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//
// --- systemdata/financialYearInc/createAccountPrimo --- ver 4.0.4 --- 2024-05-24 --
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
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------------
function createAccountPrimo($accountId,$yearBegin,$yearEnd,$nextYearBegin) {
	$amount = $maxEqId = $x = 0;
	$maxEqDate = $maxTransDate = $nextYearBegin;
	$equalId = $id = array();
	$qtxt = "select * from openpost where konto_id = '$accountId' and transdate <= '$yearEnd' and udlignet = '1' ";
	$qtxt.= "order by transdate,id";

	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$id[$x]        = $r['id'];
		$equalId[$x]   = $r['udlign_id'];
		$equalDate[$x] = $r['udlign_date'];
		$transDate[$x] = $r['transdate'];
		if ($r['valutakurs'] && $r['valutakurs'] != 100) $amount+=$r['amount']*=$r['valutakurs']/100;
		else $amount+= $r['amount'];
		if ($equalId[$x]   > $maxEqId  ) $maxEqId   = $equalId[$x];
		if ($equalDate[$x] > $maxEqDate) $maxEqDate = $equalDate[$x];
		$x++;
	}
	if (count($id)) {
		if ($amount) {
			$x--;
			$qtxt = "update openpost set transdate = '$nextYearBegin', beskrivelse = 'Primo', amount  = '$amount' where id = $id[$x]";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		$qtxt = "delete from openpost where konto_id = '$accountId' and transdate <= '$yearEnd'  and udlignet = '1'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);

	}
	for ($x = 0; $x<count($equalId); $x++) {
		$qtxt = "update openpost set udlign_id = '$maxEqId', udlign_date = '$maxEqDate' where id = $id[$x]";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$amount = $maxEqId = $x = 0;
	$maxEqDate = $maxTransDate = $nextYearBegin;
	$equalId = $id = array();
	$qtxt = "select * from openpost where konto_id = '$accountId' and transdate <= '$yearEnd' and udlignet != '1' ";
	$qtxt.= "order by transdate,id";

	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$id[$x]        = $r['id'];
		$equalId[$x]   = $r['udlign_id'];
		$equalDate[$x] = $r['udlign_date'];
		$transDate[$x] = $r['transdate'];
		if ($r['valutakurs'] && $r['valutakurs'] != 100) $amount+=$r['amount']*=$r['valutakurs']/100;
		else $amount+= $r['amount'];
		if ($equalId[$x]   > $maxEqId  ) $maxEqId   = $equalId[$x];
		if ($equalDate[$x] > $maxEqDate) $maxEqDate = $equalDate[$x];
		$x++;
	}
	if (count($id)) {
		if ($amount) {
			$x--;
			$qtxt = "update openpost set transdate = '$nextYearBegin', beskrivelse = 'Primo', amount  = '$amount' where id = $id[$x]";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		$qtxt = "delete from openpost where konto_id = '$accountId' and transdate <= '$yearEnd'  and udlignet != '1'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}

#	$qtxt = "insert into openpost ";
#	$qtxt.= "(konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,transdate,kladde_id,bilag_id";
#	$qtxt.= "udlign_id,udlign_date,valuta,valutakurs,forfaldsdate,betal_id,projekt,betalings_id,uxtid)";
#	$qtxt.= " values ";
# if ($accountId == '1436') exit;
	
}
