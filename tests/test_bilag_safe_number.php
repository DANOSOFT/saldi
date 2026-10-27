<?php
// --- tests/test_bilag_safe_number.php --- 2026-10-23 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261023 LOE Cover SST-888: a voucher number that does not fit kassekladde.bilag is rejected
//                 before it reaches the database, and the journal's next number is used instead.
// 20261027 LOE Cover SST-888 (review): the allocator also passes the highest number a staged line holds, so a
//                 replacement cannot take the number another staged line is saved under.
//
//   php tests/test_bilag_safe_number.php
//
// bilagNumberFits() is the rule without any database access, so it is characterized directly; the
// replacement branch of bilagSafeNumber() is covered by its call to bilagNextNumberForJournal(),
// which needs a journal and is exercised by the manual run.

require_once __DIR__ . '/../finans/kassekladde_includes/bilagNumber.php';

# The allocator reads the journal's highest number and the staged lines; the test supplies those two
# reads and nothing else. A result is a handle that the caller consumes, the way the real one is, or
# bilagStagedMaxNumber() would read the same row for ever.
class BilagTestResult {
	public $rows;
	public function __construct($rows) { $this->rows = $rows; }
}
$GLOBALS['bilag_test'] = array('journal_max' => 0, 'staged' => array());
function db_select($qtext, $spor = '', $global = false) {
	if (strpos($qtext, 'FROM tmpkassekl') !== false) {
		$rows = array();
		foreach ($GLOBALS['bilag_test']['staged'] as $bilag) {
			$rows[] = array('lobenr' => count($rows) + 1, 'id' => 0, 'bilag' => $bilag);
		}
		return new BilagTestResult($rows);
	}
	return new BilagTestResult(array(array('bilag' => $GLOBALS['bilag_test']['journal_max'])));
}
function db_fetch_array($result) {
	if (!($result instanceof BilagTestResult) || !$result->rows) return false;
	return array_shift($result->rows);
}
function bilagTestJournal($journal_max, $staged) {
	$GLOBALS['bilag_test'] = array('journal_max' => $journal_max, 'staged' => $staged);
}

$checks = 0;
$failures = array();
function bilagCheck($condition, $description) {
	global $checks, $failures;
	$checks++;
	if (!$condition) $failures[] = $description;
}

# ---- numbers that fit the integer column are kept ------------------------------------------
bilagCheck(bilagNumberFits('2611473') === true, 'a normal journal number fits');
bilagCheck(bilagNumberFits('2147483647') === true, 'the column maximum fits');
bilagCheck(bilagNumberFits(' 42 ') === true, 'surrounding space is tolerated');
bilagCheck(bilagNumberFits('0000000001') === true, 'a zero-padded number fits');
bilagCheck(bilagSafeNumber('2611473', 0) === array('bilag' => 2611473, 'replaced' => false, 'original' => '2611473'),
	'and is returned as it arrived, not replaced');
bilagCheck(bilagSafeNumber(' 42 ', 0)['bilag'] === 42, 'with the value trimmed');

# ---- values that cannot be stored are refused by the rule ----------------------------------
bilagCheck(bilagNumberFits('2147483648') === false, 'one above the maximum does not fit');
bilagCheck(bilagNumberFits('26126114721473') === false, 'the fourteen digit typo does not fit');
bilagCheck(bilagNumberFits('99999999999') === false, 'more than ten digits does not fit');
bilagCheck(bilagNumberFits('-5') === false, 'a negative number does not fit');
bilagCheck(bilagNumberFits('1.5') === false, 'a decimal does not fit');
bilagCheck(bilagNumberFits('abc') === false, 'text does not fit');
bilagCheck(bilagNumberFits('') === false, 'an empty value is not a number to offer');
bilagCheck(bilagNumberFits('0') === false, 'and neither is a zero');
bilagCheck(bilagNumberFits(array()) === false, 'an array does not fit');

# ---- empty and zero are passed through, the caller offers its own number -------------------
bilagCheck(bilagSafeNumber('', 0) === array('bilag' => 0, 'replaced' => false, 'original' => ''),
	'an empty value stays 0 and is not replaced');
bilagCheck(bilagSafeNumber('0', 0) === array('bilag' => 0, 'replaced' => false, 'original' => '0'),
	'and so does a zero');
bilagCheck(bilagSafeNumber(null, 0)['original'] === '', 'a NULL is treated as empty');
# ---- neither allocator lets a value the column cannot hold raise the floor -----------------
$bilagHelper = file_get_contents(__DIR__ . '/../finans/kassekladde_includes/bilagNumber.php');
bilagCheck(strpos($bilagHelper, 'bilagNumberFits((string)$r') !== false, 'an oversized staged value does not raise the staged maximum');
bilagCheck(strpos($bilagHelper, 'unset($replacements[$replace_key])') !== false, 'and a replacement that does not fit is dropped');


# ---- a replacement never takes a number a staged line already holds (SST-888 review) --------
bilagTestJournal(3, array('26126114721473', '4'));
bilagCheck(bilagStagedMaxNumber(1) === 4, 'the staged maximum ignores the value the column cannot hold');
bilagCheck(bilagNextNumberReserved(1, array()) === 5,
	'a journal at 3 with a staged 4 offers the replacement 5, not the 4 that line is about to take');
bilagTestJournal(3, array('26126114721473'));
bilagCheck(bilagNextNumberReserved(1, array()) === 4, 'an oversized staged value alone leaves the floor at the journal');
bilagTestJournal(3, array());
bilagCheck(bilagNextNumberReserved(1, array()) === 4, 'and with nothing staged the journal continues at its next number');
bilagTestJournal(3, array('abc', ''));
bilagCheck(bilagNextNumberReserved(1, array()) === 4, 'staged values the column cannot hold are ignored');
bilagTestJournal(3, array('9', '4'));
bilagCheck(bilagNextNumberReserved(1, array()) === 10, 'the highest staged number is passed, not only the last one read');
bilagTestJournal(3, array('4'));
bilagTestJournal(2147483647, array());
bilagCheck(bilagNextNumberReserved(1, array()) === 0,
	'a journal at the column maximum has no storable number left: the allocator says 0');
bilagTestJournal(3, array('2147483647'));
bilagCheck(bilagNextNumberReserved(1, array()) === 0,
	'and so does a staged line at the maximum, which would otherwise push the floor past it');
bilagTestJournal(3, array('2147483646'));
bilagCheck(bilagNextNumberReserved(1, array()) === 2147483647,
	'one below the maximum still yields a storable number');
$kassekladde = file_get_contents(__DIR__ . '/../finans/kassekladde.php');
	bilagCheck(substr_count($kassekladde, '# SST-888 (review) no storable number is left') === 3,
	'the three other allocations refuse the line when no storable number is left');
bilagTestJournal(3, array('4'));
bilagCheck(bilagNextNumberReserved(1, array(4 => 7)) === 8, 'and a replacement already handed out in the same save is passed too');

echo "\n" . ($failures ? count($failures) . " of $checks checks FAILED" : "all $checks checks passed") . "\n";
foreach ($failures as $failure) echo "  FAILED: $failure\n";
exit($failures ? 1 : 0);
