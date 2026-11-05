<?php
// --- tests/test_pos_menu_timeout.php --- 2026-10-20 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261020 LOE Cover SST-878: the finished-sale menu must draw every button when the POS setting
//                 "Tidsfrist for udskrift" holds an empty value.
//
//   php tests/test_pos_menu_timeout.php
//
// menubuttons() is called from the POS page script with its session globals, so it cannot be driven
// from a test. The rule is read out of the page and evaluated here, and no warning may be raised while
// it is evaluated - on PHP 8 the uncast form is a fatal TypeError, not a warning.

require_once __DIR__ . '/../includes/std_func.php';

$source = file_get_contents(__DIR__ . '/../includes/posmenufunc.php');
$checks = 0;
$failures = array();
$warnings = array();
set_error_handler(function ($severity, $message) use (&$warnings) {
	$warnings[] = $message;
	return true;
});

function posCheck($condition, $description) {
	global $checks, $failures;
	$checks++;
	if (!$condition) $failures[] = $description;
}

# ---- the page reads the setting through a cast, as tastatur() already did -------------------
$fixed = "\$timeout = (int) ifset(\$r, 'box13', 0);";
posCheck(strpos($source, $fixed) !== false, 'menubuttons() casts box13, defaulting to 0');
posCheck(strpos($source, "\$timeout = \$r['box13'] * 1;") === false, 'the uncast multiplication is gone');
posCheck(substr_count($source, '(int) $r[\'box13\']') >= 1, 'the sibling in tastatur() still casts');

# ---- the rule those settings values must produce ------------------------------------------
function posTimeout($r) {
	$timeout = (int) ifset($r, 'box13', 0);
	return $timeout;
}
posCheck(posTimeout(array('box13' => '')) === 0, 'an empty setting is 0, the same as the older fiscal years');
posCheck(posTimeout(array('box13' => '0')) === 0, 'and so is a saved 0');
posCheck(posTimeout(array('box13' => '5')) === 5, 'a saved 5 is kept');
posCheck(posTimeout(array('box13' => ' 7 ')) === 7, 'surrounding space is tolerated');
posCheck(posTimeout(array('box13' => null)) === 0, 'a NULL column is 0');
posCheck(posTimeout(array()) === 0, 'a missing row is 0');

# ---- and the form this replaces really does fail on this PHP --------------------------------
$legacy = null;
$threw = false;
try {
    $old = array('box13' => '');
    $legacy = $old['box13'] * 1;
} catch (Throwable $e) {
    $threw = $e->getMessage();
}
posCheck($threw !== false && $legacy === null, 'the uncast form throws on an empty value: ' . var_export($threw, true));
restore_error_handler();
posCheck($warnings === array(), 'evaluating the rule raises no warning (' . implode('; ', $warnings) . ')');

echo "\n" . ($failures ? count($failures) . " of $checks checks FAILED" : "all $checks checks passed") . "\n";
foreach ($failures as $failure) echo "  FAILED: $failure\n";
exit($failures ? 1 : 0);
