<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------includes/genberegn.php-------ver 5.1.1------2026.11.24---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// 20130210 Break ændret til break 1
// 20181126 - PHR Definition af div. variabler mm.
// 20190321 PHR Added function equalizeMatchingRecords.
// 20260210 PHR PHP8
// 20260907 CDX/PHR Allow recalculation within the caller's tenant transaction and scope primo resets to one year.
// 20261019 CL/LH MB-72: equalizeMatchingRecords() sets udlign_date to the later of the two transaction dates.
// 20260916 SS Declared $permission_key (roles & permissions, phase 3)
// 20261018 SS Review 2 (PR #803): the direct ?regnskabsaar= recompute needs finans.regnskab write instead of 'any'.
// 20261103 CL/ASR QA: the online.php include inside genberegn() declares $permission_key 'any' (as orderFuncIncludes/genberegn.php), so bogføring is not logged as unguarded.
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
// 20261124 CL/MMR SST-1005 The body of genberegn() moved unchanged to recalculateLedgerYear() in includes/ledgerYearRecalculation.php; genberegn() calls it, so its callers are unchanged.
@session_start();
$s_id=session_id();

include_once(__DIR__ . '/ledgerYearRecalculation.php');

if (!function_exists('genberegn')) {
	function genberegn($regnskabsaar, $updateUsage = true) {
		recalculateLedgerYear($regnskabsaar, $updateUsage);
	}
} 

if (isset($_GET['regnskabsaar']) && $regnskabsaar = (int) $_GET['regnskabsaar']) {
	// Recomputing a whole fiscal year is a write in the ledger: finance write, not every logged-in user.
	include("../includes/connect.php");
	$permission_key = 'finans.regnskab';
	$permission_level = 'write';
	include("../includes/online.php");
	print "Genberegner regnskabsaar $regnskabsaar<br>";
	genberegn($regnskabsaar);
}

if (!function_exists('equalizeMatchingRecords')) {
function equalizeMatchingRecords () {
// This function matches open records and equalize those that match accountId, invoicenumber and amount.

	$konto_id = array();
	$x=0;
	$qtxt="select distinct(konto_id) from openpost where udlignet='0'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$konto_id[$x]=$r['konto_id'];
		$x++;
	}
	for ($x=0;$x<count($konto_id);$x++) {
		$faktnr[$x]=array();
		$y=0;
		$qtxt="select id,faktnr,amount,transdate from openpost where konto_id='$konto_id[$x]' and udlignet='0' and amount>'0' order by transdate";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (!in_array($r['faktnr'],$faktnr[$x])) {
				$openId[$x][$y]=$r['id'];
			}
			$faktnr[$x][$y]=$r['faktnr'];
			$amount[$x][$y]=$r['amount'];
			$transdate[$x][$y]=$r['transdate'];
			$y++;
		}
	}
	$qtxt="select max(udlign_id) as udlign_id from openpost";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$udlignId=$r['udlign_id'];
	for ($x=0;$x<count($konto_id);$x++) {
		for ($y=0;$y<count($faktnr[$x]);$y++) {
			$negAmount=$amount[$x][$y]*-1;
			$qtxt = "select id,transdate from openpost where konto_id='$konto_id[$x]' and udlignet='0' and faktnr='".db_escape_string($faktnr[$x][$y])."'";
			$qtxt.= "and  amount= '$negAmount' order by transdate limit 1";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			if ($r = db_fetch_array($q)) {
				$udlignId++;
				if ($openId[$x][$y]) {
					// The settlement date is the later of the two transaction dates.
					$udlignDate = max((string)$transdate[$x][$y], (string)$r['transdate']);
					$qtxt="update openpost set udlignet='1',udlign_id='$udlignId'";
					if ($udlignDate) $qtxt.= ",udlign_date='" . db_escape_string($udlignDate) . "'";
					$qtxt.= " where id = '$r[id]' or id = '" .$openId[$x][$y] ."'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
}}
?>
