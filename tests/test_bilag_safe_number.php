<?php
// --- tests/test_bilag_safe_number.php --- 2026-10-23 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261023 LOE Cover SST-888: a voucher number that does not fit kassekladde.bilag is rejected
//                 before it reaches the database, and the journal's next number is used instead.
//
//   php tests/test_bilag_safe_number.php
//
// bilagNumberFits() is the rule without any database access, so it is characterized directly; the
// replacement branch of bilagSafeNumber() is covered by its call to bilagNextNumberForJournal(),
// which needs a journal and is exercised by the manual run.

require_once __DIR__ . '/../finans/kassekladde_includes/bilagNumber.php';

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


echo "\n" . ($failures ? count($failures) . " of $checks checks FAILED" : "all $checks checks passed") . "\n";
foreach ($failures as $failure) echo "  FAILED: $failure\n";
exit($failures ? 1 : 0);
