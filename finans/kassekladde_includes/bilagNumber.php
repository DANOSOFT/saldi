<?php
// --- finans/kassekladde_includes/bilagNumber.php --- 20261028 LOE SST-817 ---
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
 * @param int        $kladde_id Journal (kladdeliste.id); 0 or less means the journal does not exist yet.
 * @param string|null $regnstart Fiscal year start (Y-m-d); derived from $regnaar when not given.
 * @param string|null $regnslut  Fiscal year end (Y-m-d); derived from $regnaar when not given.
 * @return int The voucher number to offer, or allocate, for the next line.
 */
function bilagNextNumberForJournal($kladde_id, $regnstart = null, $regnslut = null) {
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
 * @param array<int, int> $own_ids   Lines this save already inserted ("<bilag>+N"), which never take a proposal.
 * @return array<int, int> Taken proposal => the number the line is saved under instead; empty when none was taken.
 */
function bilagReplaceTakenProposals($kladde_id, $proposed, $own_ids = array()) {
	$kladde_id = (int)$kladde_id;
	$replacements = array();
	if ($kladde_id <= 0 || !$proposed) {
		return $replacements;
	}
	$kept = array();
	$staged_max = 0;
	$q = db_select("SELECT lobenr, id, bilag FROM tmpkassekl WHERE kladde_id = '$kladde_id'", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$bilag = (int)$r['bilag'];
		if (bilagNumberFits((string)$r['bilag'])) $staged_max = max($staged_max, $bilag);
		if ((int)$r['id'] > 0) {
			continue;
		}
		$lobenr = (int)$r['lobenr'];
		if ($bilag > 0 && isset($proposed[$lobenr]) && (int)$proposed[$lobenr] === $bilag) {
			$kept[$bilag] = true;
		}
	}
	$own_ids = array_filter(array_map('intval', $own_ids));
	$not_own = $own_ids ? " AND id NOT IN (" . implode(',', $own_ids) . ")" : '';
	foreach (array_keys($kept) as $bilag) {
		# SST-888 (review) a number the column cannot hold is never taken, and asking the integer
		# column about it made the whole save die on a value-out-of-range error instead of being repaired
		if (!bilagNumberFits((string)$bilag)) {
			continue;
		}
		$taken = db_fetch_array(db_select("SELECT id FROM kassekladde WHERE kladde_id = '$kladde_id' AND bilag = '$bilag' LIMIT 1", __FILE__ . " linje " . __LINE__));
		if ($taken) {
			// Past the journal, past every number this save brings, and past a number already handed out here
			$replacements[$bilag] = max(bilagNextNumberForJournal($kladde_id), $staged_max + 1, $replacements ? max($replacements) + 1 : 0);
		}
	}
	return $replacements;
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
 * That branch tests the description as a string and the debit, the credit and the amount as numbers, so a
 * zero in a numeric column is nothing while a zero in the description is not.
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
 * Highest voucher number a staged line that the save will take already holds.
 *
 * Lines the save skips (see bilagStagedRowWillBeSaved()) are ignored, and so is a value the column
 * cannot store, or a staged typo would raise the floor the allocator works from.
 *
 * @param int $kladde_id Journal (kladdeliste.id).
 * @return int The highest staged number, or 0 when there is none.
 */
function bilagStagedMaxNumber($kladde_id) {
	$kladde_id = (int)$kladde_id;
	$staged_max = 0;
	if ($kladde_id <= 0) {
		return 0;
	}
	$q = db_select("SELECT * FROM tmpkassekl WHERE kladde_id = '$kladde_id'", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		# SST-888 (review) only a line the save will take can hold a number back
		if (bilagStagedRowWillBeSaved($r) && bilagNumberFits((string)$r['bilag'])) {
			$staged_max = max($staged_max, (int)$r['bilag']);
		}
	}
	return $staged_max;
}

/**
 * bilagNextNumberForJournal() for a line saved without a number in the same save as bilagReplaceTakenProposals():
 * kept past the numbers the moved proposals get, which are not in the journal until their lines are inserted,
 * and past the highest number a staged line holds.
 *
 * @param int             $kladde_id    Journal (kladdeliste.id).
 * @param array<int, int> $replacements What bilagReplaceTakenProposals() returned.
 * @return int The voucher number to allocate, or 0 when the column cannot hold the next one.
 */
function bilagNextNumberReserved($kladde_id, $replacements) {
	# a replacement that does not fit must not raise the floor, or a free number is passed over
	if ($replacements) {
		foreach ($replacements as $replace_key => $replace_value) {
			if (!bilagNumberFits((string)$replace_value)) unset($replacements[$replace_key]);
		}
	}
	$next = bilagNextNumberForJournal($kladde_id);
	# a staged line is saved under the number it holds, so the floor passes the highest of them:
	# journal max 3 with a staged 4 must offer 5, not the 4 that line is about to take
	$next = max($next, bilagStagedMaxNumber($kladde_id) + 1);
	$next = $replacements ? max($next, max($replacements) + 1) : $next;
	# SST-888 (review) the column cannot hold a number past its maximum, so say there is none left
	return bilagNumberFits((string)$next) ? $next : 0;
}

/**
 * @return array{0: string, 1: string} Fiscal year start and end (Y-m-d), or two empty strings.
 */
function bilagFiscalYearBounds() {
	$regnaar = $GLOBALS['regnaar'] ?? null;
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
