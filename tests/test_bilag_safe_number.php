<?php
// --- tests/test_bilag_safe_number.php --- 2026-10-23 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261023 LOE Cover SST-888: a voucher number that does not fit kassekladde.bilag is rejected
//                 before it reaches the database, and the journal's next number is used instead.
// 20261027 LOE Cover SST-888 (review): the allocator also passes the highest number a staged line holds, so a
//                 replacement cannot take the number another staged line is saved under.
// 20261027 LOE Cover SST-888 (review): an exact resend of a save that kept a refused line neither stages nor
//                 clears the staging table, and it balances no rows.
// 20261027 LOE Cover SST-888 (review): a staged 0 holds no number back, and only a save that wrote a row is
//                 cached as a replay, so a save that refused every line can be retried.
// 20261027 LOE Cover SST-888 (review): the staged-row test mirrors the save branch for branch, so a dated row
//                 with nothing on it, a zero amount in either notation, and a description-only line all read as the save
//                 reads them.
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
			# a staged line is saved when it has content, or an existing id, or a date other than today
			$rows[] = array(
				'lobenr' => count($rows) + 1,
				'id' => 0,
				'bilag' => $bilag,
				'beskrivelse' => $GLOBALS['bilag_test']['content'] ? 'line text' : '',
				'debet' => $GLOBALS['bilag_test']['content'] ? '100' : '',
				'transdate' => $GLOBALS['bilag_test']['transdate'],
				'id' => $GLOBALS['bilag_test']['id'],
			);
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
	$GLOBALS['bilag_test'] = array('journal_max' => $journal_max, 'staged' => $staged, 'content' => true, 'id' => 0, 'transdate' => '');
}
function bilagTestJournalEmptyLines($journal_max, $staged) {
	# lines that carry only a number: opdater() skips them, so they hold nothing back
	$GLOBALS['bilag_test'] = array('journal_max' => $journal_max, 'staged' => $staged, 'content' => false, 'id' => 0, 'transdate' => '');
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
	bilagCheck(substr_count($kassekladde, 'alert(findtekst("5454|') === 4,
	'every allocation that finds no storable number refuses its line with the exhaustion alert');
bilagTestJournalEmptyLines(3, array('4'));
bilagCheck(bilagNextNumberReserved(1, array()) === 4,
	'a staged 4 the save will skip does not hold a number back: the replacement is still 4');
bilagTestJournalEmptyLines(3, array('2147483647'));
bilagCheck(bilagNextNumberReserved(1, array()) === 4,
	'and a skipped 2147483647 does not claim the journal is full');
bilagTestJournal(3, array('4'));
bilagCheck(bilagNextNumberReserved(1, array()) === 5,
	'a staged 4 that will be saved still holds a number back, as before');
$GLOBALS['bilag_test']['content'] = false;   # so each branch below is proved on its own
$GLOBALS['bilag_test']['id'] = 17;
bilagCheck(bilagNextNumberReserved(1, array()) === 4,
	'a numbered existing line with no date or amount fails opdater() outer guard, so it holds nothing back');
$GLOBALS['bilag_test']['content'] = true;
bilagCheck(bilagNextNumberReserved(1, array()) === 5,
	'with an amount it is updated, so its 4 does hold a number back');
$GLOBALS['bilag_test']['content'] = false;
$GLOBALS['bilag_test']['id'] = 0;
$GLOBALS['bilag_test']['transdate'] = '01-02-2020';
bilagCheck(bilagNextNumberReserved(1, array()) === 4,
	'a line dated another day with nothing on it is not inserted either, so it holds nothing back');
$GLOBALS['bilag_test']['content'] = true;
bilagCheck(bilagNextNumberReserved(1, array()) === 5,
	'but one dated another day with a description is inserted, so its 4 does hold a number back');
$GLOBALS['bilag_test']['content'] = false;
$GLOBALS['bilag_test']['transdate'] = date('d-m-Y');
bilagCheck(bilagNextNumberReserved(1, array()) === 4,
	'a line dated today with nothing on it is skipped, and the date format does not matter');
$GLOBALS['bilag_test']['transdate'] = '';
bilagTestJournalEmptyLines(3, array('-'));
bilagCheck(bilagNextNumberReserved(1, array()) === 4,
	'a staged "-" row is skipped by the save, so its number cannot hold the allocator back');
bilagCheck(strpos($bilagHelper, "/**\n * Highest voucher number a staged line that the save will take already holds.") !== false,
	'both helpers carry the PHPDoc the convention asks for');
bilagTestJournal(3, array('4'));
bilagCheck(bilagNextNumberReserved(1, array(4 => 7)) === 8, 'and a replacement already handed out in the same save is passed too');


# ---- a refused line is not recorded as a saved one (SST-888 review) --------------------------
$kassekladde2 = file_get_contents(__DIR__ . '/../finans/kassekladde.php');
bilagCheck(strpos($kassekladde2, "\$GLOBALS['kk_refused_lobenr'] = array();") !== false,
	'opdater() starts with an empty list of refused lines');
bilagCheck(substr_count($kassekladde2, "\$GLOBALS['kk_refused_lobenr'][] = (int)\$r['lobenr'];") === 4,
	'and records every line it refuses, all four' . "
");
$refusedAt = strpos($kassekladde2, "if (\$fejl) {\n\t\t\t# SST-888 (review) the fingerprint is recorded");
bilagCheck($refusedAt !== false && strpos($kassekladde2, "\$_SESSION['kk_last_save'][\$kladde_id] = \$kk_payload;", $refusedAt) !== false,
	'a partial save records the fingerprint, so a resend of the same POST cannot insert its lines twice');
$rememberAt = strpos($kassekladde2, 'journalRememberSave(');
bilagCheck($refusedAt !== false && $rememberAt !== false && $refusedAt < $rememberAt,
	'the caller checks for a refused line before recording the save as successful');
bilagCheck(strpos($kassekladde2, "and lobenr not in (") !== false,
	'and keeps the refused rows staged while the saved ones are cleared');
bilagCheck(strpos($kassekladde2, "if (!\$fejl && \$submit == 'save' && \$kkAfterSave !== '')") !== false,
	'no document prompt is opened for a save that refused a line');

# ---- an exact resend neither re-stages nor clears what a refused save kept (SST-888 review) -----------
$replayAt = strpos($kassekladde2, "\$kk_replay = false;");
$stageAt = strpos($kassekladde2, "insert into tmpkassekl ");
bilagCheck($replayAt !== false && $stageAt !== false && $replayAt < $stageAt,
	'the replay is decided before anything is staged');
bilagCheck(strpos($kassekladde2, "if (!\$kk_replay) {") !== false,
	'and a replay stages nothing');
$replayNote = strpos($kassekladde2, 'an exact resend leaves the staging table alone');
bilagCheck($replayNote !== false && strpos(substr($kassekladde2, $replayNote, 240), 'delete from tmpkassekl') === false,
	'and it leaves the staged rows alone, so a refused line survives the resend');

bilagCheck(strpos($kassekladde2, "if (!\$kk_replay && \$x == \$antal - 1 && \$kladde_id)") !== false,
	'and it balances no rows on a replay, since the staged set is a refused line, not a form' . "\n");
# ---- a staged zero is no content, because the save reads it as nothing (SST-888 review) ---------
bilagCheck(bilagStagedRowWillBeSaved(array('bilag' => '9', 'transdate' => '', 'id' => 0, 'debet' => '0', 'kredit' => '',
	'beskrivelse' => '')) === false,
	'a line whose staged amount is the string 0 holds no number back, since the save reads it as empty' . "\n");
bilagCheck(bilagStagedRowWillBeSaved(array('bilag' => '9', 'transdate' => date('Y-m-d'), 'id' => 0, 'debet' => '0',
	'kredit' => '0', 'beskrivelse' => '', 'amount' => '0')) === false,
	'and neither does a row dated today that carries only zeros' . "\n");
bilagCheck(bilagStagedRowWillBeSaved(array('bilag' => '9', 'transdate' => date('Y-m-d'), 'id' => 0, 'debet' => '5',
	'kredit' => '', 'beskrivelse' => '')) === true,
	'while a row dated today with a real amount is still saved' . "\n");
bilagCheck(bilagStagedRowWillBeSaved(array('bilag' => '9', 'transdate' => '2020-01-01', 'id' => 0, 'debet' => '0',
	'kredit' => '0', 'beskrivelse' => '')) === false,
	'and a row dated another day with only a number is not inserted either' . "\n");
bilagCheck(bilagStagedRowWillBeSaved(array('bilag' => '9', 'transdate' => date('Y-m-d'), 'id' => 0, 'debet' => '',
	'kredit' => '', 'beskrivelse' => '', 'amount' => '0,00')) === false,
	'a zero written with a comma separator is no amount either, since the save converts it' . "\n");
bilagCheck(bilagStagedRowWillBeSaved(array('bilag' => '9', 'transdate' => date('Y-m-d'), 'id' => 0, 'debet' => '',
	'kredit' => '', 'beskrivelse' => '', 'amount' => '1.234,50')) === false,
	'a real amount alone does not enter the insert branch, so nothing is stored' . "\n");
bilagCheck(bilagStagedRowWillBeSaved(array('bilag' => '9', 'transdate' => date('Y-m-d'), 'id' => 0, 'debet' => '0',
	'kredit' => '0', 'beskrivelse' => '', 'forfaldsdate' => '01-02-2020')) === false,
	'and neither does a due date on a line the branch was never entered for' . "\n");
bilagCheck(bilagStagedRowWillBeSaved(array('bilag' => '9', 'transdate' => date('Y-m-d'), 'id' => 0, 'debet' => '',
	'kredit' => '', 'beskrivelse' => '', 'faktura' => '77', 'amount' => '1.234,50')) === true,
	'an amount is content once the branch is entered, here through the invoice number' . "\n");
bilagCheck(bilagStagedRowWillBeSaved(array('bilag' => '9', 'transdate' => date('Y-m-d'), 'id' => 0, 'debet' => '',
	'kredit' => '', 'beskrivelse' => '', 'faktura' => '77', 'amount' => '0,00')) === false,
	'but a zero amount is not content, so the line is not stored' . "\n");
bilagCheck(bilagStagedRowWillBeSaved(array('bilag' => '9', 'transdate' => '', 'id' => 0, 'debet' => '0',
	'kredit' => '', 'beskrivelse' => 'line text')) === false,
	'a staged 0 is no amount to the outer guard either, so a line it skips holds nothing back' . "\n");
bilagCheck(bilagStagedRowWillBeSaved(array('bilag' => '9', 'transdate' => '', 'id' => 0, 'debet' => '100',
	'kredit' => '', 'beskrivelse' => '')) === true,
	'a staged line with an amount and a number is inserted, so it holds its number back' . "\n");
bilagCheck(bilagStagedRowWillBeSaved(array('bilag' => '9', 'transdate' => date('Y-m-d'), 'id' => 0, 'debet' => '',
	'kredit' => '', 'beskrivelse' => 'line text')) === true,
	'a description alone is content, since the save tests it as a string' . "\n");

# ---- a save that committed nothing may be retried (SST-888 review) -----------------------------
bilagCheck(strpos($kassekladde2, "if (isset(\$kk_payload) && !empty(\$GLOBALS['kk_committed']))") !== false,
	'the replay fingerprint is recorded only when the save wrote a row' . "\n");
bilagCheck(strpos($kassekladde2, "\$GLOBALS['kk_committed']++;") !== false,
	'and opdater() counts the rows it writes' . "\n");
bilagCheck(strpos($kassekladde2, "\$GLOBALS['kk_committed'] = 0;") !== false,
	'starting from nothing on every save' . "\n");
echo "\n" . ($failures ? count($failures) . " of $checks checks FAILED" : "all $checks checks passed") . "\n";
foreach ($failures as $failure) echo "  FAILED: $failure\n";
exit($failures ? 1 : 0);
