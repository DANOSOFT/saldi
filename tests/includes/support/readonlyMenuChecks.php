<?php
// tests/includes/support/readonlyMenuChecks.php
//
// MB-62: "View only" (2) on Items counts as access in both menus, and the pages that write the
// item number - or move stock - refuse a read-only user before they write. The pages are
// session-gated and cannot be included from a test, so the rules are read out of their source.
//
// The list runs from ReadonlyMenuTest, and on its own with:
//
//   php tests/includes/support/readonlyMenuChecks.php
//
// History:
// 20261030 LOE MB-62: moved out of tests/test_readonly_menu.php, which phpunit.xml never ran.

/**
 * Read the MB-62 rules out of the pages and the stylesheets.
 *
 * @return array{0: int, 1: array<int, string>} Number of checks run, and the descriptions that failed.
 */
function readonly_menu_checks(): array
{
	$root = dirname(__DIR__, 3);
	$checks = 0;
	$failures = array();

	$check = function ($condition, $description) use (&$checks, &$failures) {
		$checks++;
		if (!$condition) {
			$failures[] = $description;
		}
	};
	$source = function ($path) use ($root, &$failures) {
		$src = @file_get_contents($root . $path);
		if ($src === false) {
			$failures[] = 'cannot read ' . $path;
		}
		return (string)$src;
	};
	# the function body without comments or whitespace, so three copies can be compared as one rule
	$body = function ($path) use ($source) {
		$src = $source($path);
		if (!preg_match('/function check_permissions\(\$permarr\).*?\n\}/s', $src, $m)) {
			return NULL;
		}
		$stripped = preg_replace('/^\s*\/\/.*$/m', '', $m[0]);
		return preg_replace('/\s+/', '', $stripped);
	};

	$rule = "in_array(substr(\$rettigheder, \$item, 1), array('1', '2'), true)";

	# ---- all three copies of check_permissions accept 1 and 2, and agree ------------------------
	$bodies = array();
	foreach (array('/index/main.php', '/index/dashboard.php', '/systemdata/regnskabsaar.php') as $path) {
		$src = $source($path);
		$name = basename($path);
		$check(strpos($src, $rule) !== false, $name . ' counts read-only (2) as access');
		$check(preg_match('/return\s*\(?\s*substr\(\$rettigheder,\s*\$item,\s*1\)\s*==\s*"1"/', $src) === 0,
			$name . ' no longer tests == "1"');
		$bodies[$name] = $body($path);
		$check($bodies[$name] !== NULL, $name . ' still defines check_permissions');
	}
	$check(count(array_unique($bodies)) === 1, 'the three copies are the same function, so they cannot drift apart');

	# ---- the classic menu shows Items for 1 and 2, and keeps the stock list for 2 ---------------
	$menu = $source('/index/menu.php');
	$itemsAccept = strpos($menu, "in_array(substr(\$rettigheder,9,1), array('1', '2'), true)");
	$check($itemsAccept !== false, 'the classic menu Items button accepts read-only (2)');
	$check(preg_match("/substr\(\\\$rettigheder,9,1\)\s*==\s*'1'/", $menu) === 0,
		'and no longer gates Items on 1 alone');
	$stockList = strpos($menu, 'beholdningsliste.php');
	$employeeBranch = strpos($menu, "if (substr(\$rettigheder,9,1) != '1')");
	$check($stockList !== false, 'the employee stock list button is still offered');
	$check($employeeBranch !== false && $employeeBranch < $stockList,
		'and for a read-only user it comes on top of the item list, not instead of it');

	# ---- a user with no Items right is still refused the item list -------------------------------
	$varer = $source('/lager/varer.php');
	$check(strpos($varer, '$modulnr') !== false && strpos($varer, '$modulnr') < strpos($varer, 'includes/online.php'),
		'the item list still gates module 9, so right 0 stays refused');

	# ---- the two renumbering pages refuse a read-only user before they write ---------------------
	foreach (array('/lager/ret_varenr.php', '/lager/ret_varenralias.php') as $path) {
		$src = $source($path);
		$name = basename($path);
		$refusal = strpos($src, "MB-62: item renumber refused");
		$firstWrite = strpos($src, 'db_modify(');
		$check($refusal !== false, $name . ' refuses a read-only user');
		$check($refusal !== false && $firstWrite !== false && $refusal < $firstWrite,
			$name . ' refuses before the first write, so nothing is renamed');
		$check(strpos($src, "\$rettigheder[9] === '2'") !== false, $name . ' names read-only (2) in the refusal');
		$check(strpos($src, "\$rettigheder[9] < '1'") !== false, $name . ' and a right below 1');
		$check(strpos($src, 'error_log(') !== false, $name . ' logs the refused attempt');
		$check(strpos($src, '$modulnr=9') !== false, $name . ' still assigns module 9');
	}

	# ---- the drag-drop endpoint refuses before it writes ----------------------------------------
	$positions = $source('/lager/productCardIncludes/save_stykliste_positions.php');
	$refuse = strpos($positions, 'MB-62: stykliste position refused');
	$write = strpos($positions, 'UPDATE styklister');
	$check($refuse !== false, 'the bill-of-materials position endpoint refuses a read-only user');
	$check($refuse !== false && $write !== false && $refuse < $write, 'and refuses before it writes');
	$check(strpos($positions, 'http_response_code(403)') !== false, 'answering JSON with 403, not the HTML box');
	$check(strpos($positions, '--- DATA BEYOND THIS LINE ---') !== false,
		'answering behind the marker the drag script splits on, so its parser sees JSON');
	$check(preg_match('/json_encode\(array\(.success. => false, .error. =>/', $positions) === 1,
		'and with an error text the script shows, not a key it ignores');

	# ---- a disabled button is a visible one -----------------------------------------------------
	$cardCss = $source('/css/standard.css');
	$check(strpos($cardCss, '.button[disabled]') !== false, 'the item card stylesheet dims a disabled button');
	$tabCss = $source('/css/top_menu.css');
	$check(strpos($tabCss, '.button[disabled]') !== false, 'so does the tab-view stylesheet');

	return array($checks, $failures);
}

# Runs the same list from the command line, which is handy while a page is being edited.
if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
	list($checks, $failures) = readonly_menu_checks();
	echo "\n" . ($failures ? count($failures) . " of $checks checks FAILED" : "all $checks checks passed") . "\n";
	foreach ($failures as $failure) {
		echo '  FAILED: ' . $failure . "\n";
	}
	exit($failures ? 1 : 0);
}
