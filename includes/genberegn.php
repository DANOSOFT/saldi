<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------includes/genberegn.php-------lap 5.0.1------2026.09.07---
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

// 20130210 Break ændret til break 1
// 20181126 - PHR Definition af div. variabler mm.
// 20190321 PHR Added function equalizeMatchingRecords.
// 20260210 PHR PHP8
// 20260907 CDX/PHR Allow recalculation within the caller's tenant transaction and scope primo resets to one year.
// 20260920 CDX/LH Keep matched open items in the same currency and record their latest transaction date.

@session_start();
$s_id=session_id();

if (!function_exists('genberegn')) {
	function genberegn($regnskabsaar, $updateUsage = true) {
		$regnskabsaar = (int)$regnskabsaar;
		$qtxt="select * from grupper where kodenr='$regnskabsaar' and art='RA'";
		$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$startmaaned=$row['box1']*1;
		$startaar=$row['box2']*1;
		$slutmaaned=$row['box3']*1;
		$slutaar=$row['box4']*1;
		$slutdato=31;
		global $db_id;
		global $s_id;
		while (!checkdate($slutmaaned, $slutdato, $slutaar)) {
			$slutdato=$slutdato-1;
			if ($slutdato<28) break 1;
		}
		$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
		$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;
	
		db_modify("update kontoplan set primo=0 where kontotype!= 'S' and regnskabsaar='$regnskabsaar'",__FILE__ . " linje " . __LINE__);
		db_modify("update kontoplan set saldo=0 where regnskabsaar='$regnskabsaar'",__FILE__ . " linje " . __LINE__);
		$qtxt="select * from kontoplan where regnskabsaar='$regnskabsaar' and (kontotype='D' or kontotype='S') order by kontonr";
		$q1=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r1 = db_fetch_array($q1)) {
			$primo=$r1['primo']*1;
			$lukket=$r1['lukket'];
			$saldo=0;
			$qtxt="select debet, kredit from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' and kontonr='$r1[kontonr]'";
			$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r2=db_fetch_array($q2)) $saldo=$saldo+round($r2['debet']+0.0001,2)-round($r2['kredit']+0.0001,2);	
			db_modify("update kontoplan set saldo=$primo+$saldo,lukket='$lukket' where id='$r1[id]'",__FILE__ . " linje " . __LINE__);
		}
		$qtxt="select count(id) as transantal from transaktioner where transdate>='$regnstart' and transdate<='$regnslut'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$qtxt="update grupper set box6 = '$r[transantal]' where art = 'RA' and kodenr = '$regnskabsaar'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$x=0;
		$saldo=array();
		$q1=db_select("select * from kontoplan where regnskabsaar='$regnskabsaar' and kontotype!='H' order by kontonr",__FILE__ . " linje " . __LINE__);
		while ($r1 = db_fetch_array($q1)) {
			$x++;
			$konto_id[$x]=$r1['id'];
			$kontonr[$x]=$r1['kontonr'];
			$saldo[$x]=afrund($r1['saldo'],2);
			$kontotype[$x]=$r1['kontotype'];
			if ($kontotype[$x]=='Z' || $kontotype[$x]=='R') {
				$saldo[$x]=0;
				$fra_kto[$x]=$r1['fra_kto'];
				for ($z=1; $z<=$x; $z++){
					if ($kontotype[$x]=='R') {
						if ($kontonr[$z]==$r1['fra_kto']) {
							if ($r2=db_fetch_array(db_select("select saldo from kontoplan where regnskabsaar='$regnskabsaar' and kontotype='Z' and kontonr='$r1[fra_kto]'",__FILE__ . " linje " . __LINE__))) {
								$saldo[$x]=$r2['saldo'];
							}
						}
					} else {
						if (($kontonr[$z]>=$fra_kto[$x])&&($kontonr[$z]<=$kontonr[$x])&&($kontotype[$z]!='H')&&($kontotype[$z]!='Z')){
							$saldo[$x]=$saldo[$x]+$saldo[$z];
						} 
					} 		
				}
				db_modify("update kontoplan set  saldo='$saldo[$x]' where id='$konto_id[$x]'",__FILE__ . " linje " . __LINE__);
			} 
 		}
 		
		$y=date('Y')-1;$m=date('m');$d=date('d');
		while (!checkdate($m, $d, $y)) { #Skudår !
			$d=$d-1;
			if ($d<28) break 1;
		}
		$tmp=$y."-".$m."-".$d;
		$qtxt="select count(id) as transantal from transaktioner where transdate>='$tmp'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$transantal=$r['transantal']*1;
		$logdate=date("Y-m-d");
		$logtime=date("H:i:s");
		db_modify("update grupper set box7='$logdate',box8='$logtime' where art='RA' and kodenr='$regnskabsaar'",__FILE__ . " linje " . __LINE__);
		if ($updateUsage) {
			include(__DIR__ . '/connect.php');
			db_modify("update regnskab set posteret='$transantal' where id='" . (int)$db_id . "'", __FILE__ . ' line ' . __LINE__);
			include(__DIR__ . '/online.php');
		}
	}
} 

if (isset($_GET['regnskabsaar']) && $regnskabsaar=$_GET['regnskabsaar']) {		
	include("../includes/connect.php");
	include("../includes/online.php");
	print "Genberegner regnskabsaar $regnskabsaar<br>";
	genberegn($regnskabsaar);
}

if (!function_exists('equalizeMatchingRecords')) {
/**
 * Close exact invoice/payment pairs without changing partial or unrelated items.
 *
 * @return void
 */
function equalizeMatchingRecords() {
	global $baseCurrency;
	$currency = db_escape_string(isset($baseCurrency) && $baseCurrency ? $baseCurrency : 'DKK');
	$qtxt = "SELECT id, konto_id, faktnr, amount, transdate,
		COALESCE(NULLIF(TRIM(valuta), ''), '$currency') AS match_currency
		FROM openpost WHERE udlignet = '0' AND amount > 0 ORDER BY konto_id, transdate, id";
	$positiveRows = [];
	$q = db_select($qtxt, __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$positiveRows[] = $row;
	}
	$row = db_fetch_array(db_select('SELECT MAX(udlign_id) AS udlign_id FROM openpost', __FILE__ . ' line ' . __LINE__));
	$settlementId = (int) $row['udlign_id'];
	foreach ($positiveRows as $positive) {
		if (!$positive['transdate']) continue;
		$accountId = (int) $positive['konto_id'];
		$positiveId = (int) $positive['id'];
		$invoice = db_escape_string($positive['faktnr']);
		$matchCurrency = db_escape_string($positive['match_currency']);
		$negativeAmount = -(float) $positive['amount'];
		$qtxt = "SELECT id, transdate FROM openpost WHERE konto_id = $accountId AND udlignet = '0'
			AND faktnr = '$invoice' AND amount = $negativeAmount AND transdate IS NOT NULL
			AND COALESCE(NULLIF(TRIM(valuta), ''), '$currency') = '$matchCurrency'
			ORDER BY transdate, id LIMIT 1";
		$negative = db_fetch_array(db_select($qtxt, __FILE__ . ' line ' . __LINE__));
		if (!$negative) continue;
		$negativeId = (int) $negative['id'];
		$settlementDate = db_escape_string(max($positive['transdate'], $negative['transdate']));
		$settlementId++;
		$qtxt = "UPDATE openpost SET udlignet = '1', udlign_id = $settlementId, udlign_date = '$settlementDate'
			WHERE udlignet = '0' AND (id = $positiveId OR id = $negativeId)";
		db_modify($qtxt, __FILE__ . ' line ' . __LINE__);
	}
}}
?>
