<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/boxCountMethods/posSaleFiscalYear.php --- ver 5.1.1 --- 2026.11.19 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// 20261118 CL/ASR Created. posSaleFiscalYear() is the one rule for which fiscal year an unposted POS sale belongs to, used by posbogfor() to post it and by findBoxSale() to count it.
//                 A sale dated in a deleted fiscal year, or in no fiscal year, belongs to none: it is neither posted nor counted (owner's decision 18 Nov 2026).
//                 The checks that an earlier year's result reaches the active year's opening balances moved here from posbogfor() unchanged, so they stay together.
//                 posAccountsOutsideChart(): the close is refused when it posted into an earlier fiscal year on an account that is not in that year's chart.
// 20261119 CL/ASR A year's result without a Sideskift account no longer refuses the close: the carry puts it on 6940, and where it has no account at all the close is committed with a warning for the bookkeeper (owner's decision 19 Nov 2026).
// 20261119 CL/ASR posCloseCarries() tells posbogfor() before its transaction whether the close will carry a sale from an earlier fiscal year, so it takes the carry's lock first (p1-pos-close-R1).
//                 posCarryOpeningBalances(): a carry whose lock is refused (OpeningCarryConflict) asks the clerk to approve the count again.
//                 The refusal for a status account without an account in the next year names that account and the two years where the carry stopped.
//                 posTillCarryWarnings(): a till account of an earlier year that the carry does not take onto the till's account in the active year is a warning for the bookkeeper (p1-pos-close-F4).
//                 posCarryOpeningBalances() also asks the clerk to approve again when a row of the active year that the carry writes was changed by another session after the close's snapshot, or in a deadlock, which was the database error box and its mail.

/**
 * The fiscal years (grupper art 'RA') by number.
 *
 * box10 is the deleted marker: a date('U') timestamp set by systemdata/fiscalYearInc/deleteFiscalYear.php, which also
 * deletes the year's chart of accounts, or 'on' from older code. Only an empty box10 is a live year.
 *
 * @return array<int, array{
 *   kodenr: string,       Fiscal year number.
 *   beskrivelse: string,  Fiscal year name.
 *   box1: string,         First month.
 *   box2: string,         Calendar year of the first month.
 *   box3: string,         Last month.
 *   box4: string,         Calendar year of the last month.
 *   box5: string,         'on' when the year is open for posting.
 *   box10: string,        Deleted marker; empty for a live year.
 * }>
 */
function posFiscalYears() {
	$years = array();
	$q = db_select("select kodenr,beskrivelse,box1,box2,box3,box4,box5,box10 from grupper where art = 'RA' order by kodenr", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$years[(int) $r['kodenr']] = $r;
	}
	return $years;
}

/**
 * The fiscal year that the day close (posbogfor()) posts an unposted POS sale into, and that the cash count
 * (findBoxSale()) counts it for. Both use this function, so a sale is counted exactly when the close posts it.
 *
 * From the first day of the active fiscal year ($regnaar) on, the sale belongs to the active year. A sale dated
 * before it belongs to the live year (box10 empty) whose months cover its date, open for posting or not; the close
 * refuses the count while that year is closed for posting. When the covering year is deleted, or no year covers the
 * date (for example a test sale before the first fiscal year), the sale belongs to no year: the close does not post
 * it and lists it for the bookkeeper, the count does not expect its cash, and it stays at status 3.
 *
 * @param string $saleDate ordrer.fakturadate (Y-m-d).
 * @return int The fiscal year's number (grupper.kodenr), or 0 when the sale belongs to no fiscal year.
 */
function posSaleFiscalYear($saleDate) {
	global $regnaar;
	$saleDate = substr((string) $saleDate, 0, 10);
	# The start of the active year, as findBoxSale() and cashBalance.php compute it.
	$r = db_fetch_array(db_select("select box1,box2 from grupper where art = 'RA' and kodenr = '" . (int) $regnaar . "'", __FILE__ . " linje " . __LINE__));
	$activeStart = ($r && $r['box1'] && $r['box2']) ? sprintf('%04d-%02d-01', (int) $r['box2'], (int) $r['box1']) : '2000-01-01';
	if ($saleDate >= $activeStart) {
		return (int) $regnaar;
	}
	$saleYear = 0;
	foreach (posFiscalYears() as $kodenr => $year) {
		if (trim((string) $year['box10']) !== '' || !(int) $year['box1'] || !(int) $year['box2'] || !(int) $year['box3'] || !(int) $year['box4']) {
			continue;
		}
		$start = sprintf('%04d-%02d-01', (int) $year['box2'], (int) $year['box1']);
		$end = date('Y-m-t', mktime(0, 0, 0, (int) $year['box3'], 1, (int) $year['box4']));
		if ($saleDate >= $start && $saleDate <= $end) {
			$saleYear = $kodenr;
		}
	}
	return $saleYear;
}

/**
 * Before the day close posts a sale into the earlier fiscal year $fromYear: why the sale cannot be carried into the
 * opening balances (primo) of the active year. updateFollowingOpeningBalances() carries a year only into a following year
 * that exists and is not deleted, so each year from $fromYear up to the one before $activeYear needs one. Kept next to
 * posCarryOpeningBalances(), which checks the carry after posting, so the two change together.
 *
 * The year's result is no reason to refuse (owner's decision 19 Nov 2026: the Sideskift account should be unnecessary,
 * and the till can always be closed). The carry puts it on the Overfør til of the year's Sideskift (X) account or, on a
 * chart without one like the standard chart, on the next year's result destination (resultDestinationWithoutSideskift(),
 * 6940 Overført resultat). Where it has no account at all, the sale is still posted in its own year, and
 * posCarryOpeningBalances() returns a warning for the bookkeeper instead of a refusal.
 *
 * @param int $fromYear The fiscal year the sale is posted into.
 * @param int $activeYear The active fiscal year.
 * @param array<int, array<string, string>> $fiscalYears posFiscalYears().
 * @return string The reason, without the sale date, or '' when a live year follows each year on the way.
 */
function posResultCarryRefusal($fromYear, $activeYear, $fiscalYears) {
	for ($fyNo = (int) $fromYear; $fyNo < $activeYear; $fyNo++) {
		$fyName = trim((string) $fiscalYears[$fyNo]['beskrivelse']) ?: $fyNo;
		if (!isset($fiscalYears[$fyNo + 1]) || trim((string) $fiscalYears[$fyNo + 1]['box10'])) {
			return "regnskabsåret efter $fyName findes ikke eller er slettet, så salget kan ikke føres til primo i det aktive regnskabsår.";
		}
	}
	return '';
}

/**
 * After the day close posted into earlier fiscal years, before posCarryOpeningBalances(): the accounts it posted on in
 * each of those years that are not a posting account (kontotype D or S, as bogfor_nu() reads them) in that year's chart
 * of accounts. Charts are per year, and an account added after the year switch is often in the active year's chart
 * only: the sales account of an item group, or the own commission account, which is one setting for all years. The
 * voucher balances, but updateFollowingOpeningBalances() reads a year's balances from that year's chart, so the amount
 * would be left out of the year's result and of the following years' opening balances. The caller rolls the close back.
 *
 * @param int $kasse The till being closed.
 * @param int $firstTransId transaktioner.id before the close posted anything; the close's rows have higher ids.
 * @param int[] $years The earlier fiscal years the close posted into.
 * @param array<int, array<string, string>> $fiscalYears posFiscalYears().
 * @return string[] One reason per date and account, with the date (d-m-Y); none when every account is in its year's chart.
 */
function posAccountsOutsideChart($kasse, $firstTransId, $years, $fiscalYears) {
	$reasons = array();
	foreach (array_unique(array_map('intval', $years)) as $fyNo) {
		$year = $fiscalYears[$fyNo];
		$start = sprintf('%04d-%02d-01', (int) $year['box2'], (int) $year['box1']);
		$end = date('Y-m-t', mktime(0, 0, 0, (int) $year['box3'], 1, (int) $year['box4']));
		$fyName = trim((string) $year['beskrivelse']) ?: $fyNo;
		$qtxt = "select distinct t.transdate, t.kontonr from transaktioner t where t.id > '" . (int) $firstTransId . "' ";
		$qtxt.= "and t.kasse_nr = '" . (int) $kasse . "' and t.kontonr <> 0 and t.transdate >= '$start' and t.transdate <= '$end' ";
		$qtxt.= "and not exists (select 1 from kontoplan k where k.regnskabsaar = '$fyNo' and k.kontonr = t.kontonr ";
		$qtxt.= "and (k.kontotype = 'D' or k.kontotype = 'S')) order by t.transdate, t.kontonr";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$reasons[] = date('d-m-Y', strtotime($r['transdate'])) . ": konto " . (int) $r['kontonr'] . " findes ikke som drifts- eller statuskonto i kontoplanen for regnskabsår $fyName.";
		}
	}
	return $reasons;
}

/**
 * After the day close posted sales into earlier fiscal years: carries them into the following years' opening
 * balances (primo), as finans/bogfor.php does after posting a journal into an earlier year. One walk from the
 * oldest year also passes the later ones. Each balance account goes onto its Overfør til (or itself), and the result
 * onto the Overfør til of the year's Sideskift (X) account or, without one, onto the next year's result destination
 * (6940 on the standard chart), which moves only by the change (updateFollowingOpeningBalances(), delta mode).
 *
 * When the walk stops before the active year, its reason ($stop) decides what the caller does:
 * - 'year' (a deleted or missing year, or one without status accounts) or 'status' (a status account without an account
 *   in the next year): a refusal; the caller rolls the close back.
 * - 'result' (the result has no account in the next year: the Sideskift account's Overfør til is not a status account of
 *   that year, or without a Sideskift account its chart has no Resultat (R) account with a status account after it): a
 *   warning (owner's decision 19 Nov 2026, the till can always be closed). The caller commits the close and shows it to
 *   the bookkeeper. The sales stay posted in their own year, the walk leaves the next year and every later one as they
 *   were, and the years before keep what it wrote.
 * The warning says how the bookkeeper carries the sales later: Gem/opdatér on the next year's Åbningsbalance
 * (Indstillinger » Virksomhed » Regnskabsår, which opens systemdata/regnskabskort.php). Without a Sideskift account last
 * year, that runs updateFollowingOpeningBalances() in delta mode from last year, once the accounts are in the chart;
 * with one, it writes that year's opening from the card's rows, the result onto the Overfør til chosen on its Resultat row.
 *
 * posbogfor() took the carry's lock before its transaction (openingCarryLockBeforeTransaction(), p1-pos-close-R1), so
 * the close's REPEATABLE READ snapshot holds what every other carry (a journal posted into last year, Gem on a year
 * card, a new fiscal year, another till's close) committed, and none of them writes until this close has committed.
 * Should the carry's lock still be refused, or a row the carry writes in a following year have been changed by another
 * session after the snapshot (a journal or an invoice into the active year; the carry locks those rows first), or end in
 * a deadlock (OpeningCarryConflict, which it asks the carry to throw), that is returned as 'retry': the caller rolls the
 * close back, so nothing of it is written, and the clerk approves the count again.
 *
 * @param int $fromYear The oldest fiscal year the close posted into.
 * @param int $activeYear The active fiscal year.
 * @param array<int, array<string, string>> $fiscalYears posFiscalYears().
 * @return array{refusal: string, warning: string, retry: string} Each without the sale dates and '' when it does not
 *   apply; all are '' when the active year's primo is updated.
 */
function posCarryOpeningBalances($fromYear, $activeYear, $fiscalYears) {
	include_once(__DIR__ . '/../../../includes/genberegn.php');
	include_once(__DIR__ . '/../../../includes/updateFollowingOpeningBalances.php');
	$stop = null;
	try {
		$updated = updateFollowingOpeningBalances($fromYear, OPENING_CARRY_DELTA, $stop, true);
	} catch (OpeningCarryConflict $e) {
		error_log('Saldi POS day close: ' . $e->getMessage());
		return array('refusal' => '', 'warning' => '', 'retry' => posOpeningCarryConflictText());
	}
	if (in_array($activeYear, $updated)) {
		return array('refusal' => '', 'warning' => '', 'retry' => '');
	}
	if ($stop && $stop['reason'] === 'result') {
		$source = (int) $stop['sourceYear'];
		$target = (int) $stop['targetYear'];
		$sourceName = trim((string) $fiscalYears[$source]['beskrivelse']) ?: $source;
		$targetName = trim((string) $fiscalYears[$target]['beskrivelse']) ?: $target;
		$later = isset($fiscalYears[$target + 1]) && !trim((string) $fiscalYears[$target + 1]['box10']);
		$where = "Gem/opdatér på åbningsbalancen for regnskabsår $targetName (Indstillinger » Virksomhed » Regnskabsår » Åbningsbalance)";
		$warning = "primo i regnskabsår $targetName" . ($later ? " og de følgende regnskabsår" : "") . " er ikke opdateret med salgene, ";
		$warning.= "fordi resultatet for regnskabsår $sourceName ikke har en konto at blive ført til. ";
		# The Sideskift account the carry takes: the first by account number.
		$qtxt = "select kontonr, overfor_til from kontoplan where regnskabsaar = '$source' and kontotype = 'X' order by kontonr limit 1";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			$transferTo = (int) $r['overfor_til'];
			$warning.= "Kontoen af typen Sideskift " . (int) $r['kontonr'] . " i kontoplanen for $sourceName har ";
			$warning.= ($transferTo ? "Overfør til $transferTo, som ikke er en statuskonto i kontoplanen for $targetName. " : "ingen Overfør til. ");
			# With a Sideskift account Gem/opdatér rewrites only its own year's opening (regnskabskort.php), so each later year needs its own.
			$warning.= "Vælg en statuskonto som Overfør til på linjen Resultat, og tryk $where";
			$warning.= ($later ? ", og derefter Gem/opdatér på åbningsbalancen for hvert af de følgende regnskabsår op til det aktive. Så føres salgene til primo i $targetName og de følgende regnskabsår." : ". Så føres salgene til primo i $targetName.");
		} else {
			$warning.= "Kontoplanen for $targetName har ingen konto af typen Resultat med en statuskonto efter sig (som 6935 Årets resultat og 6940 Overført resultat i standardkontoplanen), ";
			$warning.= "og kontoplanen for $sourceName har ingen konto af typen Sideskift. ";
			$warning.= "Opret kontiene i kontoplanen for $targetName, og tryk derefter $where. ";
			$warning.= "Så føres salgene til primo i $targetName" . ($later ? " og videre i de følgende regnskabsår, der har kontiene" : "") . ".";
		}
		return array('refusal' => '', 'warning' => $warning, 'retry' => '');
	}
	# Name the account and the years where the walk stopped, not the oldest year it started from.
	$yearName = function ($year) use ($fiscalYears) {
		return (isset($fiscalYears[$year]) ? trim((string) $fiscalYears[$year]['beskrivelse']) : '') ?: (string) $year;
	};
	if ($stop && $stop['reason'] === 'status') {
		$sourceName = $yearName((int) $stop['sourceYear']);
		$targetName = $yearName((int) $stop['targetYear']);
		$refusal = "primo i det aktive regnskabsår kan ikke opdateres med salget, fordi statuskontoen " . (int) $stop['account'];
		$refusal.= " i kontoplanen for regnskabsår $sourceName ikke kan føres til regnskabsår $targetName: hverken kontoen eller dens Overfør til ";
		$refusal.= "er en statuskonto i kontoplanen for $targetName. Opret kontoen i kontoplanen for $targetName, eller vælg en Overfør til, der findes dér.";
		return array('refusal' => $refusal, 'warning' => '', 'retry' => '');
	}
	if ($stop && $stop['reason'] === 'year') {
		$refusal = "primo i det aktive regnskabsår kan ikke opdateres med salget, fordi regnskabsår " . $yearName((int) $stop['targetYear']);
		$refusal.= " efter regnskabsår " . $yearName((int) $stop['sourceYear']) . " er slettet, mangler eller ikke har nogen statuskonti.";
		return array('refusal' => $refusal, 'warning' => '', 'retry' => '');
	}
	return array('refusal' => "primo i det aktive regnskabsår kan ikke opdateres med salget. Kontrollér, at alle statuskonti fra regnskabsår " . $yearName((int) $fromYear) . " findes i kontoplanen for det følgende år.", 'warning' => '', 'retry' => '');
}

/**
 * Whether the day close of till $kasse will post a sale into a fiscal year before the active one, and so carry it into
 * the active year's opening balances (posCarryOpeningBalances()): an unposted sale of the till, chosen as posbogfor()
 * chooses them, whose date posSaleFiscalYear() puts in an earlier year. posbogfor() asks before its transaction, so it
 * takes the carry's lock (openingCarryLockBeforeTransaction()) before its snapshot (p1-pos-close-R1).
 *
 * @param int|string $kasse The till being closed.
 * @param mixed $visSaet posbogfor()'s $vis_saet: the close also posts the till's sales of art D... (sets).
 * @return bool
 */
function posCloseCarries($kasse, $visSaet) {
	global $regnaar;
	$qtxt = "select distinct fakturadate from ordrer where felt_5 = '" . (int) $kasse . "' and status = '3' and ";
	$qtxt.= $visSaet ? "(art = 'PO' or art like 'D%')" : "art = 'PO'";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['fakturadate'] && ($year = posSaleFiscalYear($r['fakturadate'])) && $year != $regnaar) {
			return true;
		}
	}
	return false;
}

/**
 * The reason, without the sale dates, when the carry's lock was refused or the carry met another session's change
 * (OpeningCarryConflict). Nothing is posted; approving the count again starts from the new data.
 *
 * @return string
 */
function posOpeningCarryConflictText() {
	return "salgene fra et tidligere regnskabsår kunne ikke føres sikkert til primo i det aktive regnskabsår, fordi en anden bruger samtidig bogførte, godkendte en kasseoptælling, gemte en åbningsbalance eller oprettede et regnskabsår.";
}

/**
 * The reason, without the sale dates, when a sale from an earlier fiscal year came after posbogfor() checked for one
 * before its transaction (posCloseCarries()), so the close did not take the carry's lock. Nothing is posted; approved
 * again, the close takes the lock first.
 *
 * @return string
 */
function posLateEarlierYearSaleText() {
	return "salget fra et tidligere regnskabsår kom til, mens kasseoptællingen blev godkendt, så det kunne ikke føres sikkert til primo i det aktive regnskabsår.";
}

/**
 * After the day close posted into earlier fiscal years (p1-pos-close-F4): each till account it posted on there that the
 * carry does not take onto the till's own account in the active year. A cash sale from an earlier year is posted on that
 * year's till account (grupper POS 1 box2), and updateFollowingOpeningBalances() carries it onto that account's Overfør til
 * (or the account itself) year by year. When the till uses another account in the active year, the count reads the
 * till's float from that account, so the cash is missing there and the next count shows it as a difference. The close
 * goes through (the till can always be closed, owner's decision 19 Nov 2026) and the bookkeeper is told what to correct.
 * A foreign currency's till account (the till's VK account) is the same in every year, so it must be carried onto itself.
 *
 * @param int $kasse The till being closed.
 * @param int $firstTransId transaktioner.id before the close posted anything; the close's rows have higher ids.
 * @param int[] $years The earlier fiscal years the close posted into.
 * @param int $activeYear The active fiscal year.
 * @param array<int, array<string, string>> $fiscalYears posFiscalYears().
 * @param int $activeTill The till's account in the active year (grupper POS 1 box2).
 * @param array<string, int> $currencyTills The till's account per foreign currency.
 * @return string[] One line per year and till account; none when every till account reaches the active year's.
 */
function posTillCarryWarnings($kasse, $firstTransId, $years, $activeYear, $fiscalYears, $activeTill, $currencyTills) {
	$kasse = (int) $kasse;
	$activeYear = (int) $activeYear;
	$yearName = function ($year) use ($fiscalYears) {
		return (isset($fiscalYears[$year]) ? trim((string) $fiscalYears[$year]['beskrivelse']) : '') ?: (string) $year;
	};
	$isStatus = function ($account, $year) {
		$qtxt = "select id from kontoplan where regnskabsaar = '" . (int) $year . "' and kontonr = '" . (int) $account . "' and kontotype = 'S'";
		return (bool) db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	};
	$lines = array();
	$years = array_unique(array_map('intval', $years));
	sort($years);
	foreach ($years as $fyNo) {
		$year = $fiscalYears[$fyNo];
		$start = sprintf('%04d-%02d-01', (int) $year['box2'], (int) $year['box1']);
		$end = date('Y-m-t', mktime(0, 0, 0, (int) $year['box3'], 1, (int) $year['box4']));
		$r = db_fetch_array(db_select("select box2 from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '$fyNo'", __FILE__ . " linje " . __LINE__));
		$tills = explode(chr(9), (string) ($r ? $r['box2'] : ''));
		$pairs = array(); # till account in $fyNo => the till's account in the active year
		if ((int) ($tills[$kasse - 1] ?? 0) && (int) $activeTill) {
			$pairs[(int) $tills[$kasse - 1]] = (int) $activeTill;
		}
		foreach ($currencyTills as $account) {
			if ((int) $account && !isset($pairs[(int) $account])) {
				$pairs[(int) $account] = (int) $account;
			}
		}
		foreach ($pairs as $from => $to) {
			$qtxt = "select id from transaktioner where id > '" . (int) $firstTransId . "' and kasse_nr = '$kasse' and kontonr = '$from' ";
			$qtxt.= "and transdate >= '$start' and transdate <= '$end'";
			if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				continue;
			}
			# Follow the carry year by year, as updateFollowingOpeningBalances() moves a status account: onto its Overfør til
			# when that is a status account of the next year, else onto itself.
			$account = $from;
			$lastLink = $from; # the account in the year $lastLinkYear, whose Overfør til takes it into the next year
			$lastLinkYear = $fyNo;
			for ($y = $fyNo; $account && $y < $activeYear; $y++) {
				$lastLink = $account;
				$lastLinkYear = $y;
				$qtxt = "select overfor_til from kontoplan where regnskabsaar = '$y' and kontonr = '$account' and kontotype = 'S'";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				$next = $r ? (int) $r['overfor_til'] : 0;
				if ($next && $isStatus($next, $y + 1)) {
					$account = $next;
				} elseif (!$r || !$isStatus($account, $y + 1)) {
					$account = 0;
				}
			}
			if ($account === $to) {
				continue;
			}
			$activeName = $yearName($activeYear);
			$line = "Kassekontoen $from i regnskabsår " . $yearName($fyNo) . ($account ? " føres til konto $account i regnskabsår $activeName" : " føres ikke til primo i regnskabsår $activeName");
			$line.= ", men kassen bruger kassekontoen $to i $activeName. ";
			$line.= "Sæt Overfør til for konto $lastLink i regnskabsår " . $yearName($lastLinkYear) . " til $to, og tryk Gem/opdatér på åbningsbalancen for regnskabsår " . $yearName($lastLinkYear + 1) . " ";
			$line.= "(Indstillinger » Virksomhed » Regnskabsår » Åbningsbalance)";
			$line.= $account ? ", eller bogfør en overførsel fra konto $account til $to i regnskabsår $activeName." : ".";
			$lines[] = $line;
		}
	}
	return $lines;
}
