<?php
// --- tests/test_lager_readonly.php --- 2026-10-23 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261023 LOE Cover MB-65: the item list and the stock move consult the Items right, and the item
//                 card refuses a save from a read-only user whatever the button says.
//
//   php tests/test_lager_readonly.php
//
// The pages are session-gated and cannot be included from a test, so the rules are read out of them.

$checks = 0;
$failures = array();
function lagerCheck($condition, $description) {
	global $checks, $failures;
	$checks++;
	if (!$condition) $failures[] = $description;
}
function lagerSource($path) {
	$src = file_get_contents(__DIR__ . $path);
	if ($src === false) $failures[] = 'cannot read ' . $path;
	return (string)$src;
}

# ---- both pages set module 9 before the gate it feeds --------------------------------------
foreach (array('/../lager/lister/vareliste.php', '/../lager/lagerflyt.php') as $path) {
	$src = lagerSource($path);
	$assign = strpos($src, '$modulnr = 9;');
	$include = strpos($src, 'includes/online.php');
	lagerCheck($assign !== false, basename($path) . ' assigns module 9');
	lagerCheck($assign !== false && $include !== false && $assign < $include,
		basename($path) . ' assigns it before includes/online.php, where the gate reads it');
}

# ---- the save path refuses a read-only or rightless user ----------------------------------
$guard = lagerSource('/../lager/varekort.php');
lagerCheck(strpos($guard, '$bilag_handling && $rettigheder !== ""') !== false, 'the save path tests the right itself, for every write action');
lagerCheck(strpos($guard, '$deleteItem') !== false, 'a delete is refused too');
lagerCheck(strpos($guard, 'acceptStockChange') !== false, 'and so is a stock correction');
lagerCheck(strpos(lagerSource('/../lager/lagerflyt.php'), "REQUEST_METHOD'] === 'POST'") !== false, 'the move page refuses a POST from a read-only user');
lagerCheck(strpos($guard, '$rettigheder[9] === "2"') !== false, 'read-only (2) is refused');
lagerCheck(strpos($guard, '$rettigheder[9] < "1"') !== false, 'no right (0) is refused');
lagerCheck(strpos($guard, 'error_log(') !== false, 'and the refused attempt is logged');

# ---- the rule, for the rights the matrix uses --------------------------------------------
function lagerMaySave($rettigheder) {
	return !($rettigheder !== '' && ($rettigheder[9] === '2' || $rettigheder[9] < '1'));
}
lagerCheck(lagerMaySave('1111111111111111') === true, 'a user with 1 may save');
lagerCheck(lagerMaySave('1111111112111111') === false, 'a read-only user (2 on module 9) may not');
lagerCheck(lagerMaySave('1111111110111111') === false, 'a user with 0 may not');
lagerCheck(lagerMaySave('') === true, 'an empty rights string is left as it was');

echo "\n" . ($failures ? count($failures) . " of $checks checks FAILED" : "all $checks checks passed") . "\n";
foreach ($failures as $failure) echo "  FAILED: $failure\n";
exit($failures ? 1 : 0);
