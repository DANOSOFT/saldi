<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/regnskabskort.php --- ver 5.1.1 -- 2026-11-20 --
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
// ----------------------------------------------------------------------------
// 2013.02.10 Break ændret til break 1
// 2015-01-02 Tilrettet til dynamisk lagerværdi. Søg find_lagervaerdi
// 20150327 CA  Topmenudesign tilføjet søg 20150327
// 20170516 Div oprydning samt tilføjelse af laas_lager Søg laas
// 20190221 MSC - Rettet topmenu design og isset fejl
// 20190225 MSC - Rettet topmenu design
// 20210101 PHR - Various changes and cleanup. Accounting now alloewd by default.
// 20210709 LOE - Translated these texts
// 20220103 PHR - Made some cleanup, set it to save twice and set year active when new year created.
// 20220614 MSC - Implementing new design
// 20230609 PHR - php8
// 20231230 PHR - Added individual groups for each year.
// 20250522	PHR	- (int)$id
// 20250702 PHR - PHP8
// 20261007 MJ SST-829 A new accounting year no longer copies the art='LG' rows.
//                  Warehouses are not per-year data - stock is keyed on grupper.kodenr
//                  - so each copy only added a duplicate to every warehouse list.
// 20261005 SS Settings redesign G1.2: the year list and "create" live in Indstillinger » Virksomhed » Regnskabsår; this page without an id redirects there and keeps the opening balance.
//             Audit fixes: F4 (primo transfer query built with = instead of .=), F5 (the previous year was tested as the year itself), F7 (dead lock-stock redirect), F8 (aaben unchecked), F9 (posted values cast/escaped).
// 20261008 SS Settings 4e: the old top-menu ($menu=='T') branches removed; the else legs stay (spec §7.6 R3).
// 20261105 CL/LH A save POST (no ?id) no longer warns Undefined array key "id": the redirect guard and $id read the id with ifset($_GET, 'id').
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
// 20261119 CL/ASR When last year's chart has no Sideskift (X) account, Gem saves the Overfør til and writes the opening with updateFollowingOpeningBalances() (owner's decision 19 Nov 2026).
//                 It uses place mode for a year whose chart it copies and delta mode otherwise, so it no longer drops last year's result.
// 20261119 CL/ASR Gem takes the opening-balance carry's lock before its transaction (p1-pos-close-R1).
//                 It warns when the carry stops at this year because the result has no Resultat account with a status account after it, so the rows write the opening without the result (p1-yearend-F1, owner's decision 19 Nov 2026).
// 20261119 CL/ASR The warning also comes where the carry stops at this year because a status account of last year has no account in it, so the rows leave out the result already in the opening and that balance (p1-yearend-F1, review round 2).
//                 It says what the rows wrote: what was left out with its amount, or that no result is missing when last year's result was already moved onto a status account, and the opening's sum.
//                 The result it calls left out counts the postings on 6935 Årets resultat and other accounts that are neither S nor D, as the carry's place rule does, so a result closed by D 6935 / K 6940 within last year is no longer reported as missing (review round 6).
// 20261120 CL/ASR Gem keeps the opening-balance carry's lock until the card has recalculated the saved year (genberegn()), so a POS day close waiting for the lock no longer starts in between and fails on a row the recalculation rewrites (p1-pos-close-N1).
@session_start();
$s_id=session_id();

$aut_lager=$aaben=$beskrivelse=$laast=$setFiscialYear=NULL;

$modulnr=2;
$title="Regnskabskort";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/genberegn.php");
include("../includes/topline_settings.php");

if (!(int) ifset($_GET, 'id', 0) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: settingsSection.php?s=company.fiscal_years&moved=regnskabskort#create');
	exit;
}

print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";
include("top.php");
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
	print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>"; ####################table 1a start.
	print "<tr><td align='center' valign=top>";
	print "<a href=\"javascript:confirmClose('regnskabsaar.php','". findtekst('154|Dine ændringer er ikke blevet gemt! Tryk OK for at forlade siden uden at gemme.', $sprog_id) ."')\">";
	if ($menu=='S') {
		print "<button style = '$buttonStyle; width: 10%' onMouseOver=\"this.style.cursor='pointer'\">";
	} else {
		print "<button style = 'width: 80px;'>";
	}
	print findtekst('2172|Luk', $sprog_id)."</button></a>";
	print "<table width=100% align='center' border=0 cellspacing=4 cellpadding=0><tbody>\n"; ##############table 2b start
	print "<tr>\n";
	print "</tbody></table>\n"; #####################################################table 2b slut.
	print "</td></tr>\n";
	print "<tr>\n";
	print "<td align = center valign = center>";
	print "<table class='dataTable2' cellpadding=0 cellspacing=0 border=1><tbody>\n"; ############################	##table 3b start

$id=ifset($_GET, 'id');

if ($_POST) {
	$id = (int)if_isset($_POST['id'],0);
	$beskrivelse = if_isset($_POST['beskrivelse']);
	$kodenr = (int) if_isset($_POST['kodenr'], 0);
	$kode = db_escape_string((string) if_isset($_POST['kode'], ''));
	$startmd = if_isset($_POST['startmd']);
	$startaar = if_isset($_POST['startaar']);
	$slutmd = if_isset($_POST['slutmd']);
	$slutaar = if_isset($_POST['slutaar']);
	$aaben = isset($_POST['aaben']) ? 'on' : '';
	$fakt = (int) if_isset($_POST['fakt'], 0);
	$modt = (int) if_isset($_POST['modt'], 0);
	$no_faktbill = isset($_POST['no_faktbill']) ? 'on' : '';
	$faktbill = isset($_POST['faktbill']) ? 'on' : '';
	$modtbill = isset($_POST['modtbill']) ? 'on' : '';
	$kontoantal = if_isset($_POST['kontoantal']);
	$kontonr = if_isset($_POST['kontonr']);
	$debet=if_isset($_POST['debet']);
	$kredit=if_isset($_POST['kredit']);
	$saldo = if_isset($_POST['saldo']);
	$overfor_til = if_isset($_POST['overfor_til']);
#		$primotal = if_isset($_POST['primotal']);
	$aar=date("Y");
	$topaar=$aar+10;
	$bundaar=$aar-20;
	$fejl=0;
	$startmd=$startmd*1;
	$startaar=$startaar*1;
	$slutmd=$slutmd*1;
	$slutaar=$slutaar*1;

	if (!$beskrivelse){
		$beskrivelse = $startaar;
		if ($startaar != $slutaar) $beskrivelse.= "/".$slutaar;
	}
	if (($startmd<1)||($startmd>12)){
		alert(findtekst('2476|Startmåned', $sprog_id)." ".strtolower(findtekst('2480|Skal være mellem', $sprog_id))." 1 ".strtolower(findtekst('2481|Og', $sprog_id))." 12!");
		$startmd="";
	}
	elseif ($startmd<10) $startmd="0".$startmd;
	if (($slutmd<1)||($slutmd>12)){
		alert(findtekst('2477|Slutmåned', $sprog_id)." ".strtolower(findtekst('2480|Skal være mellem', $sprog_id))." 1 ".strtolower(findtekst('2481|Og', $sprog_id))." 12!");
		$slutmd="";
	}
	elseif ($slutmd<10) $slutmd="0".$slutmd;
	if (($startaar<$bundaar)||($startaar>$topaar)){
		alert(findtekst('2478|Startår', $sprog_id)." ".strtolower(findtekst('2480|Skal være mellem', $sprog_id))." $bundaar ".strtolower(findtekst('2481|Og', $sprog_id))." $topaar!");
		$startaar="";
	}
	if (($slutaar<$bundaar)||($slutaar>$topaar)){
		alert(findtekst('2479|Slutår', $sprog_id)." ".strtolower(findtekst('2480|Skal være mellem', $sprog_id))." $bundaar ".strtolower(findtekst('2481|Og', $sprog_id))." $topaar!");
		$slutaar="";
	}
	$startdato=$startaar.$startmd;
	$slutdato=$slutaar.$slutmd;
	if ($slutdato<=$startdato){
		alert(findtekst('2482|Regnskabsår skal slutte senere end det starter', $sprog_id));
		$aaben="";
	}
	$qtxt = "select id from grupper where kodenr = '$kodenr' and art = 'RA'";
	if ((($id!=0)||(!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))) &&
	(($startmd)&&($slutmd)&&($startdato)&&($slutdato)&&($startaar)&&($slutaar)&&($beskrivelse))) {
		// The opening-balance carry's lock before this save's transaction (p1-pos-close-R1): a carry in another session (a
		// POS day close, a journal posted into last year, a new fiscal year) is waited for, the save reads what it
		// committed, and no other carry writes over this save's opening before it has committed.
		include_once(__DIR__ . '/../includes/updateFollowingOpeningBalances.php');
		openingCarryLockBeforeTransaction();
		transaktion ("begin");
		$carryStop = null; // Why the carry stopped at this year ('result' or 'status'), so the rows wrote the opening: a warning after the commit.
		$carryDropped = array(); // The balances the rows left out for want of an Overfør til, by account number.
		$carryWarning = '';
		$chartCopied = false; // Set when this save copies the year's chart of accounts from last year (a new year).
		($id == 0)?$nn=2:$nn=1;
		for($n=0;$n<$nn;$n++) { // 1. time it must be rerun to catch and update last year's result.
		if ($id == 0){
			$qtxt = "insert into grupper (beskrivelse,kodenr,kode,art,box1,box2,box3,box4,box5)";
			$qtxt.= " values ";
			$qtxt.= "('".db_escape_string($beskrivelse)."','$kodenr','$kode','RA','$startmd',";
			$qtxt.= "'$startaar','$slutmd','$slutaar','$aaben')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "select id from grupper where kodenr = '$kodenr' and art = 'RA'";
			$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$id = $row['id'];
			$setFiscialYear=1;
		}
		if ($n == 0) {
			$qtxt = "select id from grupper where fiscal_year = '$kodenr' and art != 'RA'";
			if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$tmp =$kodenr - 1;
				// 20261007 MJ SST-829 Warehouses are not tied to an accounting year - stock is
				// keyed on grupper.kodenr, and the rest of the code reads art='LG' without
				// fiscal_year. Copying them gave every warehouse an extra row per year, which
				// every list that does not de-duplicate then showed twice. Only the copy is
				// narrowed; the guard above asks whether this year was already populated, and
				// changing what that counts could make a half-migrated tenant copy twice.
				$qtxt = "select * from grupper where fiscal_year > '0' and fiscal_year = '$tmp' and art != 'RA' and art != 'LG'";
				$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$qtxt = "CREATE TEMP TABLE tmp (like grupper)";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "INSERT INTO tmp SELECT * FROM grupper WHERE id = '$r[id]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "UPDATE tmp SET id = nextval('grupper_id_seq'), fiscal_year = '$kodenr'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "INSERT INTO grupper SELECT * from tmp";
					db_modify("INSERT INTO grupper SELECT * from tmp",__FILE__ . " linje " . __LINE__);
					db_modify("DROP TABLE tmp",__FILE__ . " linje " . __LINE__);
				}
			}
		}
		if ($kodenr==1) {
			for ($x=1; $x<=$kontoantal; $x++) {
				$sum=0;
				if ($debet[$x]) $sum+=usdecimal($debet[$x]);
				if ($kredit[$x]) $sum-=usdecimal($kredit[$x]);
				$qtxt = "update kontoplan set primo='$sum' where kontonr='$kontonr[$x]' and regnskabsaar='1'";
				db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
			}
			if (db_fetch_array(db_select("select * from grupper where art = 'RB'",__FILE__ . " linje " . __LINE__))) {
				$qtxt = "update grupper set box1 = '$fakt', box2 = '$modt', box3 = '$faktbill', box4 = '$modtbill', box5 = '$no_faktbill' ";
				$qtxt.= "where art = 'RB'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			} else {
				$qtxt = "insert into grupper (beskrivelse,kodenr,kode,art,box1,box2,box3,box4,box5) values ";
				$qtxt.= "('Regnskabsbilag','1','1','RB','$fakt','$modt','$faktbill','$modtbill','$no_faktbill')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		if ($id>0 && $kodenr>0) {
			$qtxt = "select kodenr from grupper where id = '$id' and art = 'RA'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$preNo = $r['kodenr'] -1;
			if ($preNo) {
				$qtxt = "select box10 from grupper where art = 'RA' and kodenr = '$preNo'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$preDeleted = trim((string) $r['box10']) !== '' ? 1 : 0;
			}
			$qtxt = "update grupper set beskrivelse = '".db_escape_string($beskrivelse)."', kodenr = '$kodenr', kode = '$kode', ";
			$qtxt.= "box1 = '$startmd', box2 = '$startaar', box3 = '$slutmd', box4 = '$slutaar', box5 = '$aaben' where id = '$id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			if ($kodenr==1 || $preDeleted){
				for ($x=1; $x<=$kontoantal; $x++) {
					if ($saldo[$x] && $overfor_til[$x]) {
						$qtxt = "update kontoplan set primo=primo+" . (float) $saldo[$x] . ",overfor_til=" . (int) $overfor_til[$x] . " where ";
						$qtxt.= "kontonr='" . db_escape_string((string) $kontonr[$x]) . "' and regnskabsaar=$kodenr";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				}
			} else {
				$qtxt = "select id from kontoplan where regnskabsaar = '$kodenr'";
				$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				if($row = db_fetch_array($query)) {
					// Without a Sideskift (X) account last year the card has no Resultat row, so the rows below would take the
					// result off this year. Save the Overfør til first, as the rows do, and write the opening with the carry:
					// the balance accounts by that Overfør til, and the result account after the Resultat account (6940) moved
					// by the change only, or placed so the year balances when this save copied its chart. Only where the carry
					// stops before this year (a boundary) do the rows below write the opening, as before.
					$carried = array();
					$qtxt = "select id from kontoplan where kontotype = 'X' and regnskabsaar = '" . ($kodenr - 1) . "'";
					if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
						for ($x=1; $x<=$kontoantal; $x++) {
							$transferTo = (int) ifset($overfor_til, $x, 0);
							if ($transferTo) {
								$qtxt = "update kontoplan set overfor_til='$transferTo' ";
								$qtxt.= "where kontonr='" . ((int) ifset($kontonr, $x, 0)) . "' and (regnskabsaar=$kodenr-1 or regnskabsaar='$kodenr')";
								db_modify($qtxt, __FILE__ . " linje " . __LINE__);
							}
						}
						include_once(__DIR__ . '/../includes/updateFollowingOpeningBalances.php');
						$stop = null;
						$carried = updateFollowingOpeningBalances($kodenr - 1, $chartCopied ? OPENING_CARRY_PLACE : OPENING_CARRY_DELTA, $stop);
						$carryStop = null;
						if (!in_array($kodenr, $carried) && $stop && in_array($stop['reason'], array('result', 'status'), true) && (int) $stop['targetYear'] === (int) $kodenr) {
							// The rows below write the opening without last year's result, as before (owner's decision 19 Nov 2026),
							// also where a status account has no account in this year ('status', which drops a result already
							// placed in the opening): tell the bookkeeper after the commit what was left out, and why.
							$carryStop = $stop;
						}
					}
					if (!in_array($kodenr, $carried)) {
						$carryDropped = array();
						$qtxt = "update kontoplan set primo='0' where  regnskabsaar = '$kodenr'";
						db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
						for ($x=0; $x<=$kontoantal; $x++) {
							$overfor_til[$x]=(int)$overfor_til[$x];
							$kontonr[$x]=$kontonr[$x]*1;
							if ($overfor_til[$x]) {
								$saldo[$x]=(float)$saldo[$x]; #phr 20110605
								$qtxt = "update kontoplan set overfor_til='$overfor_til[$x]' ";
								$qtxt.= "where kontonr='$kontonr[$x]' and (regnskabsaar=$kodenr-1 or regnskabsaar='$kodenr')";
								db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
								$qtxt = "update kontoplan set primo=primo+'$saldo[$x]' where kontonr='$overfor_til[$x]' and regnskabsaar=$kodenr";
								db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
							} elseif ($x > 0 && abs((float) ifset($saldo, $x, 0)) >= 0.005) {
								$carryDropped[(int) $kontonr[$x]] = (float) $saldo[$x]; // no Overfør til: the rows leave this balance out
							}
						}
					}
				} else {
					$qtxt = "select * from kontoplan where regnskabsaar=$kodenr-1 order by kontonr";
					$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
					$y=0;
					while ($row = db_fetch_array($query)) {
						if ($row['kontotype']=="S") {
						$belob=$row['saldo'];
						} else $belob='0';
						if (!$belob) $belob='0';
						if (!$row['fra_kto']) $row['fra_kto']='0';
						if (!$row['til_kto']) $row['til_kto']='0';
						if (!$row['overfor_til']) $row['overfor_til']='0';
						$qtxt = "insert into kontoplan ";
						$qtxt.= "(kontonr,beskrivelse,kontotype,moms,fra_kto,til_kto,lukket,";
						$qtxt.= "primo,regnskabsaar,overfor_til,genvej,valuta)";
						$qtxt.= " values ";
						$qtxt.= "('$row[kontonr]','".db_escape_string($row['beskrivelse'])."','$row[kontotype]','$row[moms]',";
						$qtxt.= "'$row[fra_kto]','$row[til_kto]','$row[lukket]','$belob','$kodenr','$row[overfor_til]',";
						$qtxt.= "'$row[genvej]','". (int)$row['valuta'] ."')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
					$chartCopied = true;
					// The copy opens each balance account with its own saldo, the result on no account. Without a Sideskift
					// (X) account last year, place it as Indstillinger » Regnskabsår » Opret does (settings_fiscal_year_create()).
					$qtxt = "select id from kontoplan where kontotype = 'X' and regnskabsaar = '" . ($kodenr - 1) . "'";
					if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
						include_once(__DIR__ . '/../includes/updateFollowingOpeningBalances.php');
						updateFollowingOpeningBalances($kodenr - 1, OPENING_CARRY_PLACE);
					}
				}
			}
		}
		}
		if ($carryStop) {
			// What the rows wrote, read before the commit: the years' names, the opening's sum, and the part of last year's
			// result that is not on its status accounts, which the rows of a chart without a Sideskift account do not carry.
			// That part is counted as the carry's place rule counts it: the drift (D) accounts' primo and postings, plus the
			// postings on accounts that are neither S nor D (6935 Årets resultat and the like). A result the bookkeeper
			// already moved onto a status account (6940) within last year, by D 6935 / K 6940 or by closing the drift
			// accounts, is in the rows: the warning then says nothing is missing (p1-yearend-F1, review rounds 2 and 6).
			$yearName = function ($year) {
				$r = db_fetch_array(db_select("select beskrivelse from grupper where art = 'RA' and kodenr = '" . (int) $year . "'", __FILE__ . " linje " . __LINE__));
				return ($r && trim((string) $r['beskrivelse']) !== '') ? trim((string) $r['beskrivelse']) : (string) (int) $year;
			};
			$carryJoin = function (array $items) { // "a", "a og b", "a, b og c"
				$last = array_pop($items);
				return ($items ? implode(', ', $items) . " og " : '') . $last;
			};
			$carryPrev = (int) $kodenr - 1;
			$r = db_fetch_array(db_select("select sum(primo) as primo from kontoplan where regnskabsaar = '$kodenr' and kontotype = 'S'", __FILE__ . " linje " . __LINE__));
			$openingSum = afrund(($r ? (float) $r['primo'] : 0), 2);
			$r = db_fetch_array(db_select("select sum(primo) as primo from kontoplan where regnskabsaar = '$carryPrev' and kontotype = 'D'", __FILE__ . " linje " . __LINE__));
			$carryProfit = $r ? (float) $r['primo'] : 0; // last year's result: its drift accounts
			$carryMoved = 0; // the postings on last year's accounts that are neither S nor D, which moved the result onto a status account
			$carryMovedOn = array(); // those accounts, by number
			$r = db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art = 'RA' and kodenr = '$carryPrev'", __FILE__ . " linje " . __LINE__));
			if ($r && checkdate((int) $r['box1'], 1, (int) $r['box2']) && checkdate((int) $r['box3'], 1, (int) $r['box4'])) {
				$carryStart = sprintf('%04d-%02d-01', (int) $r['box2'], (int) $r['box1']);
				$carryEnd = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', (int) $r['box4'], (int) $r['box3'])));
				$qtxt = "select sum(debet) as debet, sum(kredit) as kredit from transaktioner where transdate >= '$carryStart' and transdate <= '$carryEnd' ";
				$qtxt.= "and kontonr in (select kontonr from kontoplan where regnskabsaar = '$carryPrev' and kontotype = 'D')";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				$carryProfit += $r ? (float) $r['debet'] - (float) $r['kredit'] : 0;
				$qtxt = "select kontonr, sum(debet) as debet, sum(kredit) as kredit from transaktioner where transdate >= '$carryStart' and transdate <= '$carryEnd' ";
				$qtxt.= "and kontonr in (select kontonr from kontoplan where regnskabsaar = '$carryPrev' and coalesce(kontotype, '') not in ('S', 'D')) ";
				$qtxt.= "group by kontonr order by kontonr";
				$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$carryAmount = afrund((float) $r['debet'] - (float) $r['kredit'], 2);
					if (abs($carryAmount) >= 0.005) {
						$carryMoved += $carryAmount;
						$carryMovedOn[] = (int) $r['kontonr'];
					}
				}
			}
			$carryProfit = afrund($carryProfit, 2);
			if (abs($carryProfit) < 0.005) $carryProfit = 0;
			$carryResult = afrund($carryProfit + $carryMoved, 2); // the result the rows leave out
			if (abs($carryResult) < 0.005) $carryResult = 0;
			if (abs($openingSum) < 0.005) $openingSum = 0;
			$thisYear = $yearName($kodenr);
			$lastYear = $yearName($carryPrev);
			$carryMovedText = $carryMovedOn ? "statuskontiene med posteringer på konto " . $carryJoin($carryMovedOn) : '';
			$carryLeftOut = array();
			if ($carryResult && $carryMovedText) {
				$carryLeftOut[] = "den del af resultatet for regnskabsår $lastYear, der ikke allerede er ført til $carryMovedText (" . dkdecimal($carryResult, 2) . ")";
			} elseif ($carryResult) {
				$carryLeftOut[] = "resultatet for regnskabsår $lastYear (" . dkdecimal($carryResult, 2) . ")";
			}
			foreach ($carryDropped as $carryAccount => $carryAmount) {
				$carryLeftOut[] = "saldoen på konto $carryAccount (" . dkdecimal($carryAmount, 2) . ")";
			}
			$carryWarning = "Åbningsbalancen for regnskabsår $thisYear er gemt fra saldiene på kortet" . ($carryLeftOut ? " uden " . $carryJoin($carryLeftOut) : '') . ".";
			if (!$carryResult && $carryProfit) {
				$carryWarning.= " Resultatet for regnskabsår $lastYear (" . dkdecimal($carryProfit, 2) . ") er allerede ført til $carryMovedText, så der mangler intet resultat.";
			} elseif (!$carryResult) {
				$carryWarning.= " Driftskontiene for regnskabsår $lastYear står i 0,00, så der mangler intet resultat.";
			}
			$carryWarning.= " Primo i alt er " . dkdecimal($openingSum, 2) . ($openingSum ? " og ikke 0,00." : ".");
			if ($carryStop['reason'] === 'status') {
				$carryAccount = (int) $carryStop['account'];
				$carryWarning.= " Primo føres kun med resultatet, når hver statuskonto i regnskabsår $lastYear har en konto at blive ført til i $thisYear, ";
				$carryWarning.= "og konto $carryAccount har ingen: dens Overfør til er tom eller findes ikke i kontoplanen for $thisYear, og det gør kontoen selv heller ikke.";
			} else {
				$carryWarning.= " Kontoplanen for $thisYear har ingen konto af typen Resultat med en statuskonto efter sig ";
				$carryWarning.= "(som 6935 Årets resultat og 6940 Overført resultat i standardkontoplanen), som resultatet kan føres til.";
			}
		}
		transaktion("commit");
		// The carry's lock is released after the card has recalculated the year below (genberegn()), or at the end of the
		// request: a POS day close waiting for it would otherwise take its snapshot in between and fail on a row the
		// recalculation rewrites (p1-pos-close-N1).
		if ($carryWarning) {
			print "<script>alert(" . json_encode($carryWarning, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ");</script>\n";
		}
	}
}
if ($setFiscialYear) {
	include("../includes/connect.php");
	db_modify("update online set regnskabsaar = '$kodenr' where session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
	if ($revisor) {
		$qtxt = "update revisor set regnskabsaar = '$kodenr' where brugernavn = '$brugernavn' and db_id='$db_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	include("../includes/online.php");
	if (!$revisor) db_modify("update brugere set regnskabsaar = '$kodenr' where id = '$bruger_id'",__FILE__ . " linje " . __LINE__);
}

if ($id > 0) {
	$qtxt = "select kodenr from grupper where id = '$id' and art = 'RA'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$preNo = (int)$r['kodenr'] - 1;
	$preDeleted = 0;
	if ($preNo) {
		$qtxt = "select box10 from grupper where art = 'RA' and kodenr = '$preNo'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$preDeleted = trim((string) $r['box10']) !== '' ? 1 : 0;
	}

	$query = db_select("select * from grupper where id = '$id' and art = 'RA'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		genberegn($row['kodenr']);
		if (function_exists('openingCarryUnlock')) openingCarryUnlock(); // held since Gem (p1-pos-close-N1)
		if ($row['kodenr']==1) {
			include_once("fiscalYearInc/year1.php");
			year1($row['id'], $row['kodenr'], $row['beskrivelse'], $row['box1'], $row['box2'], $row['box3'], $row['box4'], $row['box5'],'');
		} elseif ($preDeleted) {
			include_once("fiscalYearInc/yearX1.php");
			yearX1($row['id'], $row['kodenr'], $row['beskrivelse'], $row['box1'], $row['box2'], $row['box3'], $row['box4'], $row['box5'],$row['box9']);
		} else {
			include_once("fiscalYearInc/yearX.php");
			yearX($row['id'], $row['kodenr'], $row['beskrivelse'], $row['box1'], $row['box2'], $row['box3'], $row['box4'], $row['box5'],$row['box9']);
		}
	}
} else {
	// Create a new fical year
#	Print "<BODY onLoad=\"javascript:alert('$tekst')\">";
#	print "<BODY onLoad=\"javascript:velkommen=window.open('velkommen.html','velkommen','".$jsvars."';) velkommen.focus();\";"
	print "<BODY onLoad=\"javascript:docChange = true;\">";
	$x=0;
	$q = db_select("select * from grupper where art = 'RA' order by kodenr desc",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($x <= $r['kodenr']) $x = $r['kodenr'];
	}
	$query = db_select("select * from grupper where art = 'RA' and kodenr='$x'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		// Create the next fiscal year using last year as month values
		$x++;
		if ($row['box3']==12) {
			$startmd  = 1;
			$startaar = $row['box4']+1;
		} else {
			$startmd  = $row['box3']+1;
			$startaar = $row['box4'];
		}
		$slutmd  = $row['box3'];
		$slutaar = $row['box4']+1;
		$deleted = $row['box10'];
	} else {
		// Create the first year with sensable defaults
		$beskrivelse = date('Y');
		$startaar    = date('Y');
		$startmd     = '01';
		$slutaar     = date('Y');
		$slutmd      = '12';
		$aaben       = 'on';
	}

	if ($x==0) {
		include_once("fiscalYearInc/year1.php");
		year1($id, 1, $beskrivelse, $startmd, $startaar, $slutmd, $slutaar, $aaben,$aut_lager);
	} else {
		include_once("fiscalYearInc/yearX.php");
		yearX($id, $x, $beskrivelse, $startmd, $startaar, $slutmd, $slutaar, $aaben,$aut_lager);
	}
}

######################################################################################################################
?>