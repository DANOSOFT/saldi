<?php
// --- tests/test_readonly_menu.php --- 2026-10-27 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261027 LOE Cover MB-62: "View only" (2) on Items counts as access in both menus, and the two
//                 renumbering pages, which write the item number into every table that carries it,
//                 refuse a read-only user before they can rename or merge an item.
//
//   php tests/test_readonly_menu.php
//
// The pages are session-gated and cannot be included from a test, so the rules are read out of them.

$checks = 0;
$failures = array();
function readonlyCheck($condition, $description) {
	global $checks, $failures;
	$checks++;
	if (!$condition) $failures[] = $description;
}
function readonlySource($path) {
	$src = file_get_contents(__DIR__ . $path);
	if ($src === false) $failures[] = 'cannot read ' . $path;
	return (string)$src;
}
# the function body without comments or whitespace, so three copies can be compared as one rule
function readonlyBody($path) {
	$src = readonlySource($path);
	if (!preg_match('/function check_permissions\(\$permarr\).*?\n\}/s', $src, $m)) return NULL;
	$body = preg_replace('/^\s*\/\/.*$/m', '', $m[0]);
	return preg_replace('/\s+/', '', $body);
}

$rule = "in_array(substr(\$rettigheder, \$item, 1), array('1', '2'), true)";

# ---- all three copies of check_permissions accept 1 and 2, and agree ------------------------
$bodies = array();
foreach (array('/../index/main.php', '/../index/dashboard.php', '/../systemdata/regnskabsaar.php') as $path) {
	$src = readonlySource($path);
	$name = basename($path);
	readonlyCheck(strpos($src, $rule) !== false, $name . ' counts read-only (2) as access');
	readonlyCheck(preg_match('/return\s*\(?\s*substr\(\$rettigheder,\s*\$item,\s*1\)\s*==\s*"1"/', $src) === 0,
		$name . ' no longer tests == "1"');
	$bodies[$name] = readonlyBody($path);
	readonlyCheck($bodies[$name] !== NULL, $name . ' still defines check_permissions');
}
readonlyCheck(count(array_unique($bodies)) === 1, 'the three copies are the same function, so they cannot drift apart');

# ---- the classic menu shows Items for 1 and 2, and keeps the stock list for 2 ---------------
$menu = readonlySource('/../index/menu.php');
$itemsAccept = strpos($menu, "in_array(substr(\$rettigheder,9,1), array('1', '2'), true)");
readonlyCheck($itemsAccept !== false, 'the classic menu Items button accepts read-only (2)');
readonlyCheck(preg_match("/substr\(\\\$rettigheder,9,1\)\s*==\s*'1'/", $menu) === 0,
	'and no longer gates Items on 1 alone');
$stockList = strpos($menu, 'beholdningsliste.php');
$employeeBranch = strpos($menu, "if (substr(\$rettigheder,9,1) != '1')");
readonlyCheck($stockList !== false, 'the employee stock list button is still offered');
readonlyCheck($employeeBranch !== false && $employeeBranch < $stockList,
	'and for a read-only user it comes on top of the item list, not instead of it');

# ---- a user with no Items right is still refused the item list -------------------------------
$varer = readonlySource('/../lager/varer.php');
readonlyCheck(strpos($varer, '$modulnr') !== false && strpos($varer, '$modulnr') < strpos($varer, 'includes/online.php'),
	'the item list still gates module 9, so right 0 stays refused');

# ---- the two renumbering pages refuse a read-only user before they write ---------------------
foreach (array('/../lager/ret_varenr.php', '/../lager/ret_varenralias.php') as $path) {
	$src = readonlySource($path);
	$name = basename($path);
	$refusal = strpos($src, "MB-62: item renumber refused");
	$firstWrite = strpos($src, 'db_modify(');
	readonlyCheck($refusal !== false, $name . ' refuses a read-only user');
	readonlyCheck($refusal !== false && $firstWrite !== false && $refusal < $firstWrite,
		$name . ' refuses before the first write, so nothing is renamed');
	readonlyCheck(strpos($src, "\$rettigheder[9] === '2'") !== false, $name . ' names read-only (2) in the refusal');
	readonlyCheck(strpos($src, "\$rettigheder[9] < '1'") !== false, $name . ' and a right below 1');
	readonlyCheck(strpos($src, 'error_log(') !== false, $name . ' logs the refused attempt');
	readonlyCheck(strpos($src, '$modulnr=9') !== false, $name . ' still assigns module 9');
}

# ---- the drag-drop endpoint refuses before it writes ----------------------------------------
$positions = readonlySource('/../lager/productCardIncludes/save_stykliste_positions.php');
$refuse = strpos($positions, 'MB-62: stykliste position refused');
$write = strpos($positions, 'UPDATE styklister');
readonlyCheck($refuse !== false, 'the bill-of-materials position endpoint refuses a read-only user');
readonlyCheck($refuse !== false && $write !== false && $refuse < $write, 'and refuses before it writes');
readonlyCheck(strpos($positions, 'http_response_code(403)') !== false, 'answering JSON with 403, not the HTML box');
readonlyCheck(strpos($positions, '--- DATA BEYOND THIS LINE ---') !== false,
	'answering behind the marker the drag script splits on, so its parser sees JSON');
readonlyCheck(preg_match('/json_encode\(array\(.success. => false, .error. =>/', $positions) === 1,
	'and with an error text the script shows, not a key it ignores');

# ---- a disabled button is a visible one -----------------------------------------------------
$card_css = readonlySource('/../css/standard.css');
readonlyCheck(strpos($card_css, '.button[disabled]') !== false, 'the item card stylesheet dims a disabled button');
$tab_css = readonlySource('/../css/top_menu.css');
readonlyCheck(strpos($tab_css, '.button[disabled]') !== false, 'so does the tab-view stylesheet');

echo "\n" . ($failures ? count($failures) . " of $checks checks FAILED" : "all $checks checks passed") . "\n";
foreach ($failures as $f) echo "  FAILED: $f\n";
exit($failures ? 1 : 0);
