<?php
// --- finans/kassekladde_includes/bilagNumber.php --- 20261127 LOE SST-906 ---
//
// Voucher number (bilag) allocation for the cash journal.
//
// The number for the next line used to be derived from the row above it ($bilag[$x-1] + 1), and only
// when that row had a debit or credit. In a journal whose last row is an empty row - which is what a
// journal that has been saved a few times looks like - no number was offered at all, the line the user
// then typed was stored as bilag 0, and the line after it derived 1: the series restarted instead of
// continuing, and the voucher numbers no longer followed the journal (SST-817).
//
// Two rules fix that, both decided here so the display and the save path cannot drift apart:
//   - the next number is the journal's highest used number + 1, so neither a 0 row nor a lower row
//     can pull the series back and no number is reused while the journal is open
//   - a journal with no numbered row yet continues the fiscal year's series (highest + 1), so a new
//     journal starts after the last voucher of the year instead of at 1
//
// bilagNextNumberAfter() is the rule without any database access, so it can be characterized directly;
// bilagNextNumberForJournal() is the same rule applied to one journal.
//
// 20261020 CL/SZ MB-81 The number is only proposed when the page is drawn, so two users with the same
// journal open were offered the same number and both lines were saved under it. The page now posts the
// number it proposed with each line (bilfN), and bilagReplaceTakenProposals() gives a new line that kept
// the proposal the next free number when another save took it meanwhile. It runs with kkJournalLock()
// held, so two saves cannot both find the proposal free. A number the user typed is kept
// as it is: several lines on one voucher stay possible.
//
// 20261020 CL/SZ MB-81 (CodeRabbit) A line saved without a number in the same save gets one past the moved proposals
// (bilagNextNumberReserved()), and an existing line renumbered in that save counts toward the numbers it brings.
// 20261027 LOE SST-888 (review) the allocator also passes the highest number a staged line holds, so a
//                replacement cannot take the number another staged line is about to be saved under.
// 20261027 LOE SST-888 (review) a staged row with neither a date nor an amount is skipped by the save before it
//                looks at anything else, so it cannot hold a number back.
// 20261027 LOE SST-888 (review) a staged 0 is no amount to the save, which reads it as empty, so it does
//                not count as content on the line either.
// 20261027 LOE SST-888 (review) the staged-row test now mirrors the save branch for branch: the outer guard,
//                the update of an existing line, and the insert that needs a due date or a number with something on it.
//                A zero in a numeric column is no value there, while a zero in the description is not a zero at all.
// 20261028 LOE SST-888 (review) the query that asks whether a proposed number is taken is never given a value
//                the column cannot hold: asking made the save die on a value-out-of-range error instead of refusing it.
// 20261105 CL/MMR SST-934 Voucher number 0 can be used.
//                 The save has always read an empty Bilag field as '0', and since SST-817 a line saved with that '0' is given the journal's next number, so a typed 0 was replaced too.
//                 A typed 0, and a line already saved under 0, now travel through the save as '00' (bilagFromInput()).
//                 Only an empty field is numbered (bilagIsUnnumbered()), and the page shows a saved 0 as 0 (bilagFieldValue()).
// 20261125 LOE SST-906 A line dated in another fiscal year continues that year's series: bilagFiscalYearForDate(), a year on bilagFiscalYearBounds(), the line date on bilagNextNumberForJournal(), and bilagReplaceForeignYearProposals() for the save.
//              That last one skips the rows the save will not store, gives lines of one voucher one number, and is told the numbers the taken-proposal pass already moved lines onto.
//              It answers per line, so a number the operator typed on another line stays his, and it reports a line the year has no number left for so the save can refuse it.
//              A staged date without a year (ddmm) is read the way usdate() reads it, and a year's window is built from its own box row, so a legacy one-digit month or a retired year (box10) cannot hide a year from the lookup.
// 20261127 LOE SST-906 (review) A staged date is read only in the syntax the journal itself reads, a yearless value takes the journal's own year, a target equal to the number the line carries is no move, and the pass reports the taken replacements it frees.
//              A staged row keeps the taken-proposal replacement reserved whether its number was proposed or typed, and a day its month does not have is clamped the way usdate() clamps it.
/**
 * @param int|string $journal_max     Highest voucher number already used in the journal (0 when none).
 * @param int|string $fiscal_year_max Highest voucher number used in the fiscal year (0 when none).
 * @return int The number to use next: journal max + 1, else fiscal year max + 1, else 1.
 */
function bilagNextNumberAfter($journal_max, $fiscal_year_max = 0) {
	$journal_max = (int)$journal_max;
	if ($journal_max > 0) {
		return $journal_max + 1;
	}
	$fiscal_year_max = (int)$fiscal_year_max;
	if ($fiscal_year_max > 0) {
		return $fiscal_year_max + 1;
	}
	return 1;
}

/**
 * Next voucher number for one journal, read from the database.
 *
 * Rows with bilag 0 (or no bilag) are ignored on purpose: they are the residue of the defect above,
 * and they must not decide the series.
 *
 * @param int         $kladde_id Journal (kladdeliste.id); 0 or less means the journal does not exist yet.
 * @param string|null $regnstart Fiscal year start (Y-m-d); derived from $regnaar when not given.
 * @param string|null $regnslut  Fiscal year end (Y-m-d); derived from $regnaar when not given.
 * @param string|null $lineDate  The date the proposed line carries (Y-m-d); its fiscal year's series is
 *                               continued instead of the active year's one (SST-906).
 * @return int The voucher number to offer, or allocate, for the next line.
 */
function bilagNextNumberForJournal($kladde_id, $regnstart = null, $regnslut = null, $lineDate = null) {
	$kladde_id = (int)$kladde_id;
	$journal_max = 0;
	if ($kladde_id > 0) {
		$row = db_fetch_array(db_select(
			"SELECT COALESCE(MAX(bilag), 0) AS bilag FROM kassekladde WHERE kladde_id = '$kladde_id' AND bilag > 0",
			__FILE__ . " linje " . __LINE__
		));
		if ($row) {
			$journal_max = (int)$row['bilag'];
		}
	}
	if ($journal_max > 0) {
		return bilagNextNumberAfter($journal_max, 0);
	}

	# SST-906 a line dated in another fiscal year continues that year's series, not the active one's. The
	# journal's own series wins above, so the two lookups below only run for a journal with no numbered row
	if ($lineDate !== null && $lineDate !== '') {
		$lineYear = bilagFiscalYearForDate($lineDate);
		if ($lineYear > 0) {
			list($yearStart, $yearEnd) = bilagFiscalYearBounds($lineYear);
			if ($yearStart && $yearEnd) {
				$regnstart = $yearStart;
				$regnslut = $yearEnd;
			}
		}
	}
	if (!$regnstart || !$regnslut) {
		list($regnstart, $regnslut) = bilagFiscalYearBounds();
	}
	$fiscal_year_max = 0;
	if ($regnstart && $regnslut) {
		$regnstart = db_escape_string($regnstart);
		$regnslut = db_escape_string($regnslut);
		$row = db_fetch_array(db_select(
			"SELECT COALESCE(MAX(bilag), 0) AS bilag FROM kassekladde WHERE transdate >= '$regnstart' AND transdate <= '$regnslut' AND bilag > 0",
			__FILE__ . " linje " . __LINE__
		));
		if ($row) {
			$fiscal_year_max = (int)$row['bilag'];
		}
	}
	return bilagNextNumberAfter($journal_max, $fiscal_year_max);
}

/**
 * New numbers for the proposed voucher numbers that another save has taken since the page was drawn.
 *
 * Only new lines (no id yet) whose number is still the one the page proposed count; a number the user
 * typed is never replaced. Must run with kkJournalLock() held, so the check and the insert that
 * follows it cannot interleave with another save.
 *
 * @param int             $kladde_id Journal (kladdeliste.id).
 * @param array<int, int> $proposed  Line number (tmpkassekl.lobenr) => the number the page proposed on that line.
 * @param int             $from      Series start: the journal's next number, read once per save.
 * @param array<int, int> $own_ids   Lines this save already inserted ("<bilag>+N"), which never take a proposal.
 * @return array<int, int> Taken proposal => the number the line is saved under instead; empty when none was taken.
 */
function bilagReplaceTakenProposals($kladde_id, $proposed, $from = 0, $own_ids = array()) {
	$kladde_id = (int)$kladde_id;
	$replacements = array();
	if ($kladde_id <= 0 || !$proposed) {
		return $replacements;
	}
	# SST-888 (review) the series start is the journal's next number as it stood when the save began
	if ((int)$from <= 0) {
		$from = bilagNextNumberForJournal($kladde_id);
	}
	$kept = array();
	$held = bilagStagedNumbersWillBeSaved($kladde_id);
	$q = db_select("SELECT lobenr, id, bilag FROM tmpkassekl WHERE kladde_id = '$kladde_id'", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$bilag = (int)$r['bilag'];
		if ((int)$r['id'] > 0) {
			continue;
		}
		$lobenr = (int)$r['lobenr'];
		if ($bilag > 0 && isset($proposed[$lobenr]) && (int)$proposed[$lobenr] === $bilag) {
			$kept[$bilag] = true;
		}
	}
	# MB-126 the "<bilag>+N" lines this save inserted itself never take a proposal back
	$own_ids = array_filter(array_map('intval', $own_ids));
	$not_own = $own_ids ? " AND id NOT IN (" . implode(',', $own_ids) . ")" : '';
	foreach (array_keys($kept) as $bilag) {
		# SST-888 (review) a number the column cannot hold is never taken, and asking the integer
		# column about it made the whole save die on a value-out-of-range error instead of being repaired
		if (!bilagNumberFits((string)$bilag)) {
			continue;
		}
		$taken = db_fetch_array(db_select("SELECT id FROM kassekladde WHERE kladde_id = '$kladde_id' AND bilag = '$bilag'$not_own LIMIT 1", __FILE__ . " linje " . __LINE__));
		if ($taken) {
			# SST-888 (review) the replacement walks past the numbers this save already holds
			$replacements[$bilag] = bilagNextNumberSkipping($held, array_values($replacements), $from);
			if ($replacements[$bilag] <= 0) {
				unset($replacements[$bilag]);   # no storable number left: the line is refused, not saved past the column
			}
		}
	}
	return $replacements;
}

/**
 * The proposals the page drew for another fiscal year than the one the line is now dated in (SST-906).
 *
 * The page proposes a number from the series of the date the line carries when it is drawn - today, for a
 * blank line. An operator who then dates the line in another year (year-end entries, in a session that still
 * stands in the closing year) leaves a number from the wrong series on the line. A number the operator did
 * not type is a proposal, so the save takes it from the year the line is dated in instead.
 *
 * Only a line the save will actually store, that is new, staged, and still carrying the proposal it was posted
 * with, is touched: a typed number is kept, so is a proposal whose date belongs to the same fiscal year as the
 * line's date, and so is a row the save skips (it never gets a line to renumber, so announcing one would be a
 * lie). Rows that share a proposal share the replacement, because they are lines of one voucher.
 *
 * The answer is per line, not per number, because only the line that still carries its proposal may have it
 * replaced - the same number typed on another line is the operator's, and stays. A line whose own fiscal year
 * has no number left comes back with 'to' = 0: the caller refuses that line rather than store the number the
 * page had offered for a different year.
 *
 * @param int                $kladde_id     Journal (kladdeliste.id).
 * @param array<int, int>    $proposed      Line number (tmpkassekl.lobenr) => the number the page proposed.
 * @param array<int, string> $proposedDates Line number => the date that proposal was drawn for (Y-m-d).
 * @param int                $from          Series start: the journal's next number, read once per save.
 * @param array<int, int>    $alreadyUsed   Replacements this save has already made (bilagReplaceTakenProposals()),
 *                                          whose numbers are taken and must not be handed out again.
 * @param array<int, bool>   $freed         Set (by reference) for every taken proposal whose replacement no line
 *                                          of this save stores, because every line carrying it moved to another
 *                                          year. The caller can hand that number out again, and must not
 *                                          announce it.
 * @return array<int, array{from: int, to: int}> Line number => the number it carries and the number to save it
 *                                               under; 'to' = 0 when that year has no number left. Lines this
 *                                               does not touch are absent.
 */
function bilagReplaceForeignYearProposals($kladde_id, $proposed, $proposedDates, $from = 0, $alreadyUsed = array(), &$freed = array()) {
	$kladde_id = (int)$kladde_id;
	$replacements = array();
	if ($kladde_id <= 0 || !$proposed || !$proposedDates) {
		return $replacements;
	}
	$held = bilagStagedNumbersWillBeSaved($kladde_id);
	# read the staged rows first: a proposal another save took is free again only when every line carrying it
	# moved to another year - a line that stays in its own year is still stored under that replacement
	$rows = array();
	$q = db_select("SELECT * FROM tmpkassekl WHERE kladde_id = '$kladde_id'", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$rows[] = $r;
	}
	# the window a date without a year needs, looked up only when such a value is actually staged
	$activeBounds = null;
	foreach ($rows as $r) {
		$staged = trim((string)($r['transdate'] ?? ''));
		if ($staged !== '' && preg_match('/^\d{1,4}$|^\d{1,2}[.\-\/ ]\d{1,2}$/', $staged)) {
			$activeBounds = function_exists('db_select') ? bilagFiscalYearBounds() : array('', '');
			break;
		}
	}
	$moved = array();
	$stayed = array();
	foreach ($rows as $r) {
		$bilag = (int)$r['bilag'];
		$lobenr = (int)$r['lobenr'];
		# only a row the save stores can consume a number, and only a number another save took has a
		# replacement to consume
		if (!bilagStagedRowWillBeSaved($r) || (int)$r['id'] > 0 || $bilag <= 0 || !isset($alreadyUsed[$bilag])) {
			continue;
		}
		# this pass moves only a line that still carries its proposal. Every other line keeps the number it
		# has - proposed or typed - and is stored under $alreadyUsed[$bilag], so it keeps that replacement taken
		$isUntouchedProposal = isset($proposed[$lobenr]) && (int)$proposed[$lobenr] === $bilag;
		if ($isUntouchedProposal && bilagForeignYearMove($r, $proposedDates, $activeBounds)) {
			$moved[$bilag] = true;
		} else {
			$stayed[$bilag] = true;
		}
	}
	$freed = array();
	foreach (array_keys($moved) as $bilag) {
		if (!isset($stayed[$bilag])) {
			$freed[$bilag] = true;
		}
	}
	# SST-906 (review) the numbers the taken-proposal pass already moved lines onto are this save's as well -
	# unless no line stores under one, and then it is free again
	$used = array();
	foreach ((array)$alreadyUsed as $taken_key => $taken_number) {
		if (isset($freed[(int)$taken_key])) {
			continue;
		}
		$used[] = (int)$taken_number;
	}
	# a proposal number => the number its lines move to, so the lines of one voucher share it
	$targets = array();
	foreach ($rows as $r) {
		$bilag = (int)$r['bilag'];
		$lobenr = (int)$r['lobenr'];
		if (!bilagStagedRowWillBeSaved($r) || (int)$r['id'] > 0 || $bilag <= 0
			|| !isset($proposed[$lobenr]) || (int)$proposed[$lobenr] !== $bilag) {
			continue;   # a row the save skips, an existing line, a line without a number, or a number the operator typed
		}
		$lineDate = bilagForeignYearMove($r, $proposedDates, $activeBounds);
		if ($lineDate === '') {
			continue;   # a page that posted no proposal date, or a line the proposal's year already fits
		}
		if (isset($targets[$bilag])) {
			# another line of the same voucher already worked out where it goes, so this one follows it
			if ((int)$targets[$bilag] <= 0) {
				$replacements[$lobenr] = array('from' => $bilag, 'to' => 0);
			} elseif ((int)$targets[$bilag] !== $bilag) {
				$replacements[$lobenr] = array('from' => $bilag, 'to' => (int)$targets[$bilag]);
			}
			continue;
		}
		# the line's own year's next number, past the numbers this save already holds or hands out
		$heldHere = $held;
		unset($heldHere[$bilag]);
		$target = bilagNextNumberForJournal($kladde_id, null, null, $lineDate);
		if ((int)$from > 0 && $target <= 0) {
			$target = (int)$from;
		}
		$target = bilagNextNumberSkipping($heldHere, array_merge($used, array_values($targets)), $target);
		# no number left in that year (its series is at the column maximum): the line is refused by the caller
		# rather than stored under a number that belongs to another year
		$targets[$bilag] = (int)$target;
		if ($target <= 0) {
			$replacements[$lobenr] = array('from' => $bilag, 'to' => 0);   # refused by the caller
		} elseif ($target !== $bilag) {
			$replacements[$lobenr] = array('from' => $bilag, 'to' => (int)$target);
		}
		# a journal that already has numbered rows answers with its own next number whatever the date, so the
		# target can be the number the line already carries: nothing to change, nothing to announce
	}
	return $replacements;
}

/**
 * A staged date as Y-m-d.
 *
 * tmpkassekl keeps what the operator typed, so this reads the forms the journal itself accepts: Y-m-d,
 * dd-mm-yyyy, dd.mm.yyyy, and the compacts ddmm, ddmmyy and ddmmyyyy. A value without a year (ddmm) is read
 * the way usdate() reads it, from the boxes of the active fiscal year, because that is the year the save
 * itself will give the line. It never asks usdate() for the answer: usdate() returns today's date for
 * anything it cannot read, and a garbage date turned into today would renumber a line from the wrong year.
 * Anything this still cannot read is no date, and the caller leaves the line's number alone.
 *
 * @param string|null      $date         The staged transdate.
 * @param array{0: string, 1: string}|null $activeBounds The active fiscal year's window (start, end), needed
 *                                       only for a value without a year; without it such a value is not read.
 * @return string The date as Y-m-d, or '' when it cannot be read.
 */
function bilagStagedDate($date, $activeBounds = null) {
	$date = trim((string)$date);
	if ($date === '') {
		return '';
	}
	# the form the page itself writes back for a saved line, and the form a saved row holds: both are written
	# by the application rather than typed, so a calendar-invalid one here is a bug, not input, and stays
	# rejected - a typed 2026-02-31 reaches the parts below as 31-02-2026, where it is clamped as usdate() clamps it
	if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $ymd)) {
		return checkdate((int)$ymd[2], (int)$ymd[3], (int)$ymd[1]) ? $date : '';
	}
	# day, month and year with one of the separators usdate() accepts. Anything else ('13x06x2025') is not a
	# date usdate() can read either, and reading it here while the save reads it as today is how a proposal
	# ends up moved to a year the line is not dated in
	if (preg_match('/^(\d{1,2})[.\-\/ ](\d{1,2})[.\-\/ ](\d{2,4})$/', $date, $dmy)) {
		return bilagStagedDateFromParts((int)$dmy[1], (int)$dmy[2], (int)$dmy[3], $activeBounds);
	}
	# day and month, with or without a separator: the year is the one the journal gives such a value
	if (preg_match('/^(\d{1,2})[.\-\/ ](\d{1,2})$/', $date, $dm)) {
		return bilagStagedDateFromParts((int)$dm[1], (int)$dm[2], 0, $activeBounds);
	}
	# the compacts: ddmmyy, ddmmyyyy, ddmm, and a bare day
	if (preg_match('/^(\d{2})(\d{2})(\d{2}|\d{4})$/', $date, $compact)) {
		return bilagStagedDateFromParts((int)$compact[1], (int)$compact[2], (int)$compact[3], $activeBounds);
	}
	if (preg_match('/^(\d{2})(\d{2})$/', $date, $ddmm)) {
		return bilagStagedDateFromParts((int)$ddmm[1], (int)$ddmm[2], 0, $activeBounds);
	}
	if (preg_match('/^(\d{1,2})$/', $date, $day)) {
		# a bare day is the day of the month the journal is in now, which is what usdate() makes of it
		return bilagStagedDateFromParts((int)$day[1], (int)date('n'), 0, $activeBounds);
	}
	return '';
}

/**
 * A staged date from its parts, with the year rules the journal's own converter applies.
 *
 * A year of 0 means the operator typed none: the value then takes the active fiscal year's opening year from
 * its opening month onwards (January 2026 belongs to 2025/26 when that year opens in October), otherwise the
 * closing year. A two-digit year is 20xx below 80 and 19xx above, as elsewhere in the journal.
 *
 * @param int   $day         Day of the month.
 * @param int   $month       Month, 1-12.
 * @param int   $year        Year, 0 when the operator typed none.
 * @param array|null $activeBounds The active fiscal year's window (start, end), needed when $year is 0.
 * @return string Y-m-d, or '' when the parts are not a date or the year cannot be settled.
 */
function bilagStagedDateFromParts($day, $month, $year, $activeBounds = null) {
	if ($year > 0 && $year < 100) {
		$year += $year < 80 ? 2000 : 1900;
	}
	if ($year === 0) {
		if ($activeBounds === null) {
			# the caller hands the window in, so this stays a pure function: without one it cannot give a
			# yearless date a year, and the line's number is left alone
			return '';
		}
		$start = bilagIsoDate($activeBounds[0] ?? '');
		$end = bilagIsoDate($activeBounds[1] ?? '');
		if ($start === '' || $end === '') {
			return '';
		}
		$year = ($month >= (int)substr($start, 5, 2)) ? (int)substr($start, 0, 4) : (int)substr($end, 0, 4);
	}
	# usdate() clamps a day its month does not have down to the last valid one (31-02 -> 28-02), and the save
	# reads the staged date through usdate(). Agreeing on that day is the point: otherwise the line is numbered
	# in one fiscal year and stored in another
	if ($day > 28) {
		while (!checkdate($month, $day, $year)) {
			$day--;
			if ($day < 28) {
				break;
			}
		}
	}
	return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : '';
}

/**
 * The date of a staged line whose own fiscal year differs from the one its proposal came from, else ''.
 *
 * The answer is the line's date, so the caller can work out where the line belongs; '' means "leave this line
 * alone": no proposal was posted for it, or the proposal's year is the year the line is dated in.
 *
 * @param array              $r            One tmpkassekl row.
 * @param array<int, string> $proposedDates Line number => the date that proposal was drawn for (Y-m-d).
 * @param array|null         $activeBounds The active fiscal year's window, for a staged date without a year.
 * @return string The line's date as Y-m-d when the years differ, else ''.
 */
function bilagForeignYearMove($r, $proposedDates, $activeBounds = null) {
	$lobenr = (int)$r['lobenr'];
	$proposalDate = trim((string)($proposedDates[$lobenr] ?? ''));
	if ($proposalDate === '') {
		return '';
	}
	$lineDate = bilagStagedDate($r['transdate'] ?? '', $activeBounds);
	if ($lineDate === '') {
		return '';
	}
	$proposalYear = bilagFiscalYearForDate($proposalDate);
	$lineYear = bilagFiscalYearForDate($lineDate);
	if ($proposalYear <= 0 || $lineYear <= 0 || $proposalYear === $lineYear) {
		return '';
	}
	return $lineDate;
}

/**
 * Whether the save will take a staged line at all, branch for branch.
 *
 * opdater() decides in this order, and so does this helper:
 *   1. the outer guard skips a line whose voucher number is '-' and a line with neither a date nor a debit
 *      or credit, testing the raw columns as PHP booleans (the string '0' is empty, '0,00' is not);
 *   2. an existing line (an id) with a number is updated, whatever else it holds;
 *   3. otherwise the line is only stored when the insert branch is entered and takes it: the branch is
 *      entered by a line dated another day, or one with a description, a debit, a credit or an invoice
 *      number, and it stores the line when the line has a due date, or a number and something on it. A
 *      line without a date of its own can inherit the date of the line above it, so it is never ruled out
 *      by its date alone.
 *
 * Anything else is skipped by the save, so its number must not hold a number back from a line that is
 * saved. A zero in a numeric column is no value there, because the save casts the debit and the credit to
 * integers and converts the amount before it tests them.
 *
 * @param array $row One tmpkassekl row.
 * @return bool True when one of opdater()'s save branches takes the line.
 */
function bilagStagedRowWillBeSaved($row) {
	# the helper stays free of the page's own helpers, so it can be read and tested on its own
	$bilag = isset($row['bilag']) ? trim((string)$row['bilag']) : '';
	$transdate = isset($row['transdate']) ? trim((string)$row['transdate']) : '';
	$debet = isset($row['debet']) ? trim((string)$row['debet']) : '';
	$kredit = isset($row['kredit']) ? trim((string)$row['kredit']) : '';
	$id = isset($row['id']) ? (int)$row['id'] : 0;
	# 1. the outer guard, in the same order as the save
	if ($bilag === '-') {
		return false;
	}
	if (!bilagStagedValueIsSet($row, 'transdate') && !bilagStagedValueIsSet($row, 'debet') && !bilagStagedValueIsSet($row, 'kredit')) {
		return false;
	}
	# 2. an existing line with an id and a number is updated
	if ($id > 0 && $bilag !== '') {
		return true;
	}
	# 3. the insert branch, which is entered by a date that is not today or by something on the line
	$enters = !bilagStagedValueIsSet($row, 'transdate')
		|| !bilagStagedRowIsToday($transdate)
		|| bilagStagedValueIsSet($row, 'beskrivelse')
		|| ((int)$debet != 0)
		|| ((int)$kredit != 0)
		|| bilagStagedValueIsSet($row, 'faktura');
	if (!$enters) {
		return false;
	}
	# and stores the line for its due date, or for a number with something on it
	if (bilagStagedValueIsSet($row, 'forfaldsdate')) {
		return true;
	}
	return ($bilag !== '' && bilagStagedRowHasContent($row));
}

/**
 * Whether a staged column holds a value the way opdater() sees it: the raw text as a PHP boolean.
 *
 * The string '0' is empty to PHP and '0,00' is not, which is exactly what the save's outer guard uses when
 * it decides whether a line has a date or an amount.
 *
 * @param array  $row   One tmpkassekl row.
 * @param string $field The column to read.
 * @return bool True when the column holds a value the save reads as one.
 */
function bilagStagedValueIsSet($row, $field) {
	if (!isset($row[$field])) {
		return false;
	}
	$text = trim((string)$row[$field]);
	return ($text !== '' && $text !== '0');
}

/**
 * Whether a staged date names today, the comparison opdater() makes when it enters the insert branch.
 *
 * The staging table keeps the operator's dd-mm-yyyy, so the day the value names is compared, not the text.
 *
 * @param string $date The staged transdate.
 * @return bool True when the value names today in a format strtotime() understands.
 */
function bilagStagedRowIsToday($date) {
	$date = trim((string)$date);
	if ($date === '') {
		return false;
	}
	$date_ts = strtotime($date);
	if ($date_ts === false) {
		return false;
	}
	return (date('Y-m-d', $date_ts) === date('Y-m-d'));
}

/**
 * Whether a staged line holds anything the insert branch of opdater() would store.
 *
 * That branch tests the description as a string and the debit, the credit and the amount as numbers, and a
 * staged '0' is read as empty in every one of them, which is what opdater() does too.
 *
 * @param array $row One tmpkassekl row.
 * @return bool True when the line has something the save stores.
 */
function bilagStagedRowHasContent($row) {
	foreach (array('beskrivelse', 'debet', 'kredit', 'amount') as $field) {
		if (!isset($row[$field])) {
			continue;
		}
		$text = trim((string)$row[$field]);
		if ($text === '' || $text === '0') {
			continue;
		}
		if ($field === 'debet' || $field === 'kredit') {
			if ((int)$text == 0) {
				continue;
			}
		} elseif ($field === 'amount' && ((float)str_replace(',', '.', $text) == 0)) {
			# the save converts the amount through usdecimal(), so a zero written '0,00' is nothing
			continue;
		}
		return true;
	}
	return false;
}
/**
 * The voucher numbers the journal's staged lines will be saved under.
 *
 * Only a line the save will take can hold a number back, and only a number the column can store can be
 * held: a line the save skips, or a value it has to repair, does not block the series.
 *
 * @param int $kladde_id Journal (kladdeliste.id).
 * @return array<int, bool> Number => true for every staged number the save will use.
 */
function bilagStagedNumbersWillBeSaved($kladde_id) {
	$kladde_id = (int)$kladde_id;
	$held = array();
	if ($kladde_id <= 0) {
		return $held;
	}
	$q = db_select("SELECT * FROM tmpkassekl WHERE kladde_id = '$kladde_id'", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!bilagStagedRowWillBeSaved($r) || !bilagNumberFits((string)$r['bilag'])) {
			continue;
		}
		$held[(int)$r['bilag']] = true;
	}
	return $held;
}

/**
 * The next free voucher number above the journal's series.
 *
 * The series starts at the journal's next number (the fiscal year's when the journal has none) and only
 * steps over the numbers this save already holds: the ones the staged lines will be saved under, and the
 * replacements handed out earlier in this save. Walking over those instead of starting past the highest
 * staged number keeps a typed number far above the series from dragging every unnumbered line up with it.
 *
 * @param array $held Numbers the staged lines will be saved under (bilagStagedNumbersWillBeSaved()).
 * @param array $used Replacements already handed out in this save.
 * @param int   $from The series start: the journal's next number, read once per save.
 * @return int The number to use, or 0 when the column cannot hold one.
 */
function bilagNextNumberSkipping($held, $used, $from) {
	$next = (int)$from;
	if ($next <= 0) {
		return 0;
	}
	while (isset($held[$next]) || in_array($next, $used, true)) {
		$next++;
		if (!bilagNumberFits((string)$next)) {
			return 0;
		}
	}
	return bilagNumberFits((string)$next) ? $next : 0;
}
/**
 * bilagNextNumberForJournal() for a line saved without a number in the same save as bilagReplaceTakenProposals():
 * kept past the numbers the moved proposals get, which are not in the journal until their lines are inserted,
 * and past the highest number a staged line holds.
 *
 * @param int             $kladde_id    Journal (kladdeliste.id).
 * @param array<int, int> $replacements What bilagReplaceTakenProposals() returned.
 * @param int             $from        The series start: the journal's next number, read once per save
 *                                    (0 reads it now, before the save writes anything).
 * @param array<int, int> $handed      Numbers this save has already allocated, in that order.
 * @return int The voucher number to allocate, or 0 when the column cannot hold the next one.
 */
function bilagNextNumberReserved($kladde_id, $replacements, $from = 0, $handed = array()) {
	# SST-888 (review) the series start is the journal's next number as it stood when the save began:
	# a line this save has already inserted must not drag the next number along behind it
	if ((int)$from <= 0) {
		$from = bilagNextNumberForJournal($kladde_id);
	}
	# a replacement that does not fit must not raise the floor, or a free number is passed over
	if ($replacements) {
		foreach ($replacements as $replace_key => $replace_value) {
			if (!bilagNumberFits((string)$replace_value)) unset($replacements[$replace_key]);
		}
	}
	# SST-888 (review) the number a staged line holds is skipped, not passed: journal max 3 with a
	# staged 4 offers 4 to a line without a number once 4 is not held, and 5 when it is
	# the numbers handed out earlier in this save are taken too, or two unnumbered lines get the same one
	$used = array_merge(array_values($replacements), array_map('intval', (array)$handed));
	return bilagNextNumberSkipping(bilagStagedNumbersWillBeSaved($kladde_id), $used, $from);
}

/**
 * The fiscal year a voucher line belongs to, from its own date (SST-906).
 *
 * A line is dated today (the page fills the date with dkdato(date("Y-m-d"))), but the number used to be
 * taken from the ACTIVE fiscal year. A session still standing in the previous year therefore stamped that
 * year's series onto a line dated in the new one, and the single stray number owned the new year's series.
 * The date is what decides which series a line continues, so this returns the fiscal year that contains it.
 *
 * The active year is asked first, and the other years in `grupper art='RA'` only when it does not contain
 * the date, so the common case costs one lookup and no extra query.
 *
 * @param string $date Line date as Y-m-d (the page's dkdato() input).
 * @return int The regnskabsaar whose fiscal year contains the date; 0 for a date that is not Y-m-d, and the
 *             active year when no year contains it (a year still to be created, or one that was deleted) -
 *             the caller then keeps the bounds it already had.
 */
function bilagFiscalYearForDate($date) {
	$date = trim((string)$date);
	# a date the calendar does not have is no date either: '2026-11-31' would otherwise be compared against
	# the fiscal year windows and quietly answer the fallback below
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) {
		return 0;
	}
	$active = (int)($GLOBALS['regnaar'] ?? 0);
	# without a database there is no fiscal year to read, which is what the unit tests run as
	if (!function_exists('db_select')) {
		return $active;
	}
	if ($active > 0) {
		list($start, $end) = bilagFiscalYearBounds($active);
		if (bilagFiscalYearContaining($date, array($active => array($start, $end))) === $active) {
			return $active;
		}
	}
	$others = array();
	# one query for every remaining year: the boxes are read here rather than one fiscalYear() call per year,
	# and a year that was deleted (grupper.box10 = 'on', which the rest of the application filters on) is
	# skipped, so a retired year cannot claim a date
	$q = db_select("select kodenr, box1, box2, box3, box4 from grupper where art = 'RA' and (box10 is null or box10 = '') order by kodenr", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$year = (int)$r['kodenr'];
		if ($year <= 0 || $year === $active) {
			continue;
		}
		$others[$year] = bilagFiscalYearWindow((int)$r['box1'], (int)$r['box2'], (int)$r['box3'], (int)$r['box4']);
	}
	$found = bilagFiscalYearContaining($date, $others);
	return $found > 0 ? $found : $active;
}

/**
 * A fiscal year's window from the boxes of its grupper art='RA' row.
 *
 * The same two dates fiscalYear() builds, but zero-padded: a legacy row whose start month is one digit
 * ('1') gives '2026-1-01' there, which compares as a string before every date of that year and would hide
 * the year from bilagFiscalYearContaining(). The end is the last day of the closing month, which is what
 * usdate('31-<month>-<year>') lands on.
 *
 * @param int $startMonth Month the year opens in (box1).
 * @param int $startYear  Year the year opens in (box2).
 * @param int $endMonth   Month it closes in (box3).
 * @param int $endYear    Year it closes in (box4).
 * @return array{0: string, 1: string} Start and end as Y-m-d, or two empty strings when the boxes are unusable.
 */
function bilagFiscalYearWindow($startMonth, $startYear, $endMonth, $endYear) {
	$startMonth = (int)$startMonth;
	$startYear = (int)$startYear;
	$endMonth = (int)$endMonth;
	$endYear = (int)$endYear;
	if ($startMonth < 1 || $startMonth > 12 || $endMonth < 1 || $endMonth > 12 || $startYear < 1 || $endYear < 1) {
		return array('', '');
	}
	# date('t') is the number of days in the month, which is the day usdate() clamps 31 to
	$endDay = (int)date('t', mktime(0, 0, 0, $endMonth, 1, $endYear));
	return array(sprintf('%04d-%02d-01', $startYear, $startMonth), sprintf('%04d-%02d-%02d', $endYear, $endMonth, $endDay));
}

/**
 * A date string as a zero-padded Y-m-d, whatever separator and padding it arrived with.
 *
 * @param string|null $value A date such as '2026-1-01', '2026-01-01' or '2026/01/01'.
 * @return string Y-m-d, or '' when it is not a date the calendar has.
 */
function bilagIsoDate($value) {
	$value = trim((string)$value);
	if (!preg_match('/^(\d{4})[^0-9](\d{1,2})[^0-9](\d{1,2})$/', $value, $parts)) {
		return '';
	}
	$year = (int)$parts[1];
	$month = (int)$parts[2];
	$day = (int)$parts[3];
	return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : '';
}

/**
 * Which of the given fiscal years contains the date.
 *
 * The bounds are the `start => end` pairs bilagFiscalYearBounds() answers with, so the choice can be
 * characterized without a tenant: the year whose window holds the date, and 0 when none does.
 *
 * @param string $date  Line date as Y-m-d.
 * @param array  $years Regnskabsaar => array(start, end), both Y-m-d.
 * @return int The regnskabsaar containing the date, or 0 when no year does.
 */
function bilagFiscalYearContaining($date, array $years) {
	$date = bilagIsoDate($date);
	if ($date === '') {
		return 0;
	}
	ksort($years);
	foreach ($years as $year => $bounds) {
		$start = bilagIsoDate($bounds[0] ?? '');
		$end = bilagIsoDate($bounds[1] ?? '');
		if ($start === '' || $end === '') {
			continue;
		}
		if ($date >= $start && $date <= $end) {
			return (int)$year;
		}
	}
	return 0;
}

/**
 * Start and end of a fiscal year's window, as the rest of the application reads it.
 *
 * @param int|null $regnskabsaar The regnskabsaar to answer for; the session's when not given.
 * @return array{0: string, 1: string} Fiscal year start and end (Y-m-d), or two empty strings.
 */
function bilagFiscalYearBounds($regnskabsaar = null) {
	$regnaar = $regnskabsaar !== null ? $regnskabsaar : ($GLOBALS['regnaar'] ?? null);
	if (!$regnaar) {
		return array('', '');
	}
	if (!function_exists('fiscalYear')) {
		include_once(__DIR__ . '/../../includes/stdFunc/fiscalYear.php');
	}
	if (!function_exists('fiscalYear')) {
		return array('', '');
	}
	$bounds = explode(':', fiscalYear($regnaar));
	if (count($bounds) != 2) {
		return array('', '');
	}
	return array($bounds[0], $bounds[1]);
}

/**
 * Whether a value can be stored as kassekladde.bilag, which is an integer.
 *
 * Empty and "0" are not numbers to offer - the caller keeps its own rule for those - so they do not
 * fit. Otherwise the value has to be all digits once trimmed, no longer than ten characters, and not
 * above 2147483647. The comparison stays on the string: casting first would wrap values above
 * PHP_INT_MAX and silently accept signs or decimals, which is how SST-888 reached the database.
 *
 * @param mixed $raw The value as it arrived.
 * @return bool True when it can be stored unchanged.
 */
function bilagNumberFits($raw) {
	if (!is_scalar($raw)) return false;
	$value = trim((string)$raw);
	if ($value === '' || $value === '0') return false;
	if (!ctype_digit($value)) return false;
	if (strlen($value) > 10) return false;
	if (strlen($value) === 10 && strcmp($value, '2147483647') > 0) return false;
	return true;
}

/**
 * A voucher number the caller can store, replacing one that does not fit with the journal's next.
 *
 * `replaced` tells the caller the value was not the user's, so it can alert; `original` keeps what
 * arrived, for that message and for logging.
 *
 * @param mixed       $raw       The value as it arrived (form input, or tmpkassekl.bilag, which is text).
 * @param int         $kladde_id Journal (kladdeliste.id); 0 or less means the journal does not exist yet.
 * @param string|null $regnstart Fiscal year start (Y-m-d); derived from $regnaar when not given.
 * @param string|null $regnslut  Fiscal year end (Y-m-d); derived from $regnaar when not given.
 * @return array{bilag: int, replaced: bool, original: string}
 */
function bilagSafeNumber($raw, $kladde_id, $regnstart = null, $regnslut = null) {
	$result = array('bilag' => 0, 'replaced' => false, 'original' => is_scalar($raw) ? trim((string)$raw) : '');
	if ($result['original'] === '' || $result['original'] === '0') return $result;
	if (bilagNumberFits($result['original'])) {
		$result['bilag'] = (int)$result['original'];
		return $result;
	}
	$result['bilag'] = bilagNextNumberForJournal($kladde_id, $regnstart, $regnslut);
	$result['replaced'] = true;
	# a journal already at the column maximum has no number left to offer: bilag 0 with
	# replaced = true is the caller's signal to refuse the line instead of writing it
	if (!bilagNumberFits((string)$result['bilag'])) $result['bilag'] = 0;
	return $result;
}

/**
 * The Bilag field as the save carries it (SST-934).
 *
 * kassekladde.php has always read an empty field as '0': kontroller() keeps that '0' and does not give the line
 * the number of the line above, and opdater() gives it the journal's next number (SST-817). A zero the user typed
 * is carried as '00' instead. That is numeric and 0 like any zero, but PHP does not read it as empty, so
 * kontroller() leaves it as it is and opdater() saves the line under 0.
 *
 * @param mixed $raw The posted field.
 * @return string '0' for an empty field, '00' for a zero ("0", "00", ...), otherwise the trimmed value.
 */
function bilagFromInput($raw) {
	$value = is_scalar($raw) ? trim((string)$raw) : '';
	if ($value === '') return '0';
	if (preg_match('/^0+$/', $value)) return '00';
	return $value;
}

/**
 * Whether a staged line has no voucher number, so the save gives it the journal's next one (SST-817).
 *
 * @param mixed $staged tmpkassekl.bilag (text), as bilagFromInput() and kontroller() leave it.
 * @return bool False for a zero the user typed ('00') and for a positive number; true for an empty field
 *              ('', NULL or the '0' an empty field is read as) and for anything else (a negative or not a number).
 */
function bilagIsUnnumbered($staged) {
	$value = is_scalar($staged) ? trim((string)$staged) : '';
	if (preg_match('/^0{2,}$/', $value)) return false;
	return !(ctype_digit($value) && ltrim($value, '0') !== '');
}

/**
 * The value the Bilag field shows.
 *
 * A saved line's 0 is a voucher number, and showing it as an empty field would let the next save number the
 * line (SST-934). A staged '0' is an empty field, so it stays empty.
 *
 * @param mixed $value The line's bilag: kassekladde.bilag for a saved line, tmpkassekl.bilag for a staged one.
 * @param bool  $saved True when the value is kassekladde.bilag, false when it is tmpkassekl.bilag.
 * @return string '' for a line without a number, '0' for a zero, otherwise the value as it is.
 */
function bilagFieldValue($value, $saved) {
	$value = is_scalar($value) ? trim((string)$value) : '';
	if ($value === '') return '';
	if (preg_match('/^0+$/', $value)) return ($saved || $value !== '0') ? '0' : '';
	return $value;
}
