<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------includes/ledgerYearRecalculation.php-------ver 5.1.1------2026.11.24---
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
// 20261124 CL/MMR SST-1005 The ledger recalculation of a fiscal year, moved here from includes/genberegn.php under a name of its own.
//                 Several pages declare their own genberegn() (debitor/ordreliste.php recalculates an order), so a caller that runs on any page calls recalculateLedgerYear().
//                 This file has no side effects when it is included.

if (!function_exists('recalculateLedgerYear')) {
	/**
	 * Recalculates one fiscal year from its postings: kontoplan.saldo (and primo of non-status accounts reset to 0),
	 * the sum and result accounts, and the year's summary in grupper art 'RA' box6-8. This is the ledger genberegn().
	 *
	 * @param int|string $regnskabsaar The fiscal year (grupper art 'RA' kodenr).
	 * @param bool $updateUsage Also store the posting count on the master's regnskab row (switches database connection).
	 * @return void
	 */
	function recalculateLedgerYear($regnskabsaar, $updateUsage = true) {
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
			$permission_key = 'any'; // back to the company database for the caller, whose own page already checked access
			include(__DIR__ . '/online.php');
		}
	}
}
