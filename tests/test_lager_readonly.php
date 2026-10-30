<?php
// --- tests/test_lager_readonly.php --- 2026-10-30 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261026 LOE Cover MB-65: the item list and the stock move consult the Items right, and the item
//                 card refuses a save from a read-only user whatever the button says.
// 20261030 LOE MB-62: the move page is checked for hiding the form rather than drawing it disabled, and the
//                 item card for offering only the controls that read.
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
lagerCheck(strpos($guard, 'MB-65 a read-only or rightless user may open the card') !== false, 'the write refusal is a single gate near the top of the page');
lagerCheck(strpos($guard, 'MB-65 a read-only or rightless') < strpos($guard, 'insert into vare_lev'), 'and it sits above the first write handler, which GET can reach');
foreach (array('copy', 'delete_category', 'delete_var_type', 'rename_category', 'ChangeDescription') as $action) {
    lagerCheck(strpos($guard, "'$action'") !== false, 'the gate covers ' . $action);
}
lagerCheck(strpos($guard, '$deleteItem') !== false, 'a delete is refused too');
lagerCheck(strpos($guard, 'acceptStockChange') !== false, 'and so is a stock correction');
lagerCheck(strpos(lagerSource('/../lager/lagerflyt.php'), "REQUEST_METHOD'] === 'POST'") !== false, 'the move page refuses a POST from a read-only user');
lagerCheck(strpos($guard, '$rettigheder[9] ===') !== false, 'read-only (2) is refused');
lagerCheck(strpos($guard, '$rettigheder[9] <') !== false, 'no right (0) is refused');
lagerCheck(strpos($guard, 'error_log(') !== false, 'and the refused attempt is logged');

# ---- the rule, for the rights the matrix uses --------------------------------------------
function lagerMaySave($rettigheder) {
	return !($rettigheder !== '' && ($rettigheder[9] === '2' || $rettigheder[9] < '1'));
}
lagerCheck(lagerMaySave('1111111111111111') === true, 'a user with 1 may save');
lagerCheck(lagerMaySave('1111111112111111') === false, 'a read-only user (2 on module 9) may not');
lagerCheck(lagerMaySave('1111111110111111') === false, 'a user with 0 may not');
lagerCheck(lagerMaySave('') === true, 'an empty rights string is left as it was');

# ---- and the gate at the head of the save block, which every write path passes through ------------
$saveGate = strpos($guard, 'every path into this block writes');
$saveBlock = strpos($guard, 'if ($saveItem || $submit = trim($submit))');
lagerCheck($saveGate !== false, 'the save block carries its own refusal');
lagerCheck($saveGate !== false && $saveBlock !== false && $saveGate < $saveBlock, 'and it sits before the block every write path enters');
$saveRequest = strpos($guard, '$mb65_save_request = $saveItem || (bool) trim((string) $submit);');
lagerCheck($saveRequest !== false && strpos($guard, 'if ($mb65_save_request && $rettigheder') !== false && strpos($guard, 'if ($mb65_save_request') < $saveBlock, 'its refusal tests only what the block tests, so a read-only user still sees the card');
lagerCheck(strpos($guard, 'supplierLookUp') !== false && strpos($guard, '$mb65_write') !== false, 'the early gate names supplierLookUp as well');

# ---- viewing a card writes nothing either --------------------------------------------------
$supplierDefault = strpos($guard, 'only a user who may edit stores it');
$supplierStore = strpos($guard, 'update vare_lev set lev_varenr=', $supplierDefault);
$supplierGate = strpos($guard, 'if (!$noEdit) {', $supplierDefault);
lagerCheck($supplierDefault !== false && $supplierGate !== false && $supplierStore !== false && $supplierGate < $supplierStore, 'the supplier default is shown to all but stored only for an editor');
$cycleNote = strpos($guard, 'only a user who may edit removes the row');
$cycleDelete = strpos($guard, 'delete from styklister where id=', $cycleNote);
$cycleGate = strpos($guard, 'if (!$noEdit) {', $cycleNote);
lagerCheck($cycleNote !== false && $cycleGate !== false && $cycleDelete !== false && $cycleGate < $cycleDelete, 'a broken component reference is removed only for an editor');


# ---- and the move form is not drawn at all for a read-only user ------------------------------
$movePage = lagerSource('/../lager/lagerflyt.php');
$flag = strpos($movePage, '$mb65_readonly = (');
lagerCheck($flag !== false, 'the move page decides once whether the user may move stock');
lagerCheck($flag !== false && strpos($movePage, 'if ($mb65_readonly) {') > $flag, 'and decides before the form is drawn');
lagerCheck($flag !== false && strpos($movePage, "REQUEST_METHOD'] === 'POST'") < $flag, 'with the server-side refusal still first');
lagerCheck(strpos($movePage, 'if (!$mb65_readonly)') === false, 'the form is hidden rather than drawn disabled');
lagerCheck(strpos($movePage, 'name=\"opdater\" $mb65_noedit') === false, 'so no disabled control is left in the page');
lagerCheck(strpos($movePage, 'dkdecimal($max_antal,2)') !== false, 'while the stock figure is still shown to read-only');

# ---- the card offers only the controls that read ----------------------------------------------
$deleteGate = strpos($guard, 'if ($id && !$noEdit) {');
$deleteAt = strpos($guard, 'name=\\"deleteItem\\"');
lagerCheck($deleteGate !== false && $deleteAt !== false && $deleteGate < $deleteAt,
	'the delete button is not drawn for a read-only user');
$saveGate = strpos($guard, 'if (!$noEdit) {');
$saveAt = strpos($guard, 'name=\\"saveItem\\"');
lagerCheck($saveGate !== false && $saveAt !== false && $saveGate < $saveAt, 'so is the Save button');
lagerCheck(substr_count($guard, 'if (!$noEdit) {') >= 3, 'and the copy, item-lookup and supplier-lookup cells');
$stockAt = strpos($guard, "name='stockHistory'");
lagerCheck($stockAt !== false, 'Lagerhistorik is still offered, because it only reads');
foreach (array('saveItem', 'copy', 'supplierLookUp', 'deleteItem') as $name) {
	$carries = false;
	foreach (explode("\n", $guard) as $line) {
		if (strpos($line, "name=\\\"$name\\\"") !== false && strpos($line, '$noEdit') !== false) $carries = true;
	}
	lagerCheck(!$carries, $name . ' carries no read-only attribute, because it is not drawn at all');
}

# ---- the category and variant controls are not offered to a read-only user ------------------
$cats = lagerSource('/../lager/productCardIncludes/showCategories.php');
lagerCheck(substr_count($cats, 'if ($noEdit) {') === 2, 'both category levels hide the rename and delete icons');
	lagerCheck(strpos($cats, 'if ($noEdit) {') < strpos($cats, 'rename_category'),
	'and the icons they hide are the ones that post rename_category and delete_category');
$variants = lagerSource('/../lager/productCardIncludes/showVariantsInfo.php');
lagerCheck(strpos($variants, 'if ($var_beh[$x] || $noEdit)') !== false, 'a read-only user gets no delete icon for a variant');
lagerCheck(strpos($variants, 'delete_var_type') !== false, 'while the link behind it is still the one the gate refuses');

# ---- the mass-import button is a write too ----------------------------------------------------
$scanAt = strpos($guard, 'varescanimport.php');
$scanGate = $scanAt === false ? false : strrpos(substr($guard, 0, $scanAt), 'if (!$noEdit) {');
lagerCheck($scanAt !== false && $scanGate !== false && $scanAt - $scanGate < 250,
	'"Masse vare scan" is not drawn for a read-only user');

# ---- a read-only user is not offered a way to create an item ----------------------------------
$blankGate = strpos($guard, 'if (!$id && $noEdit) {');
$blankLog = strpos($guard, 'MB-62: item create refused');
lagerCheck($blankGate !== false && $blankLog !== false && $blankGate < $blankLog,
	'a blank card (a new item) is refused before the form is built');
$list = lagerSource('/../lager/varer.php');
lagerCheck(strpos($list, '$mb62_readonly = (') !== false, 'the item list decides read-only once, from module 9');
lagerCheck(substr_count($list, 'if (!$mb62_readonly) print') === 2, 'and both of its Ny buttons are hidden for that user');

# ---- a refused page offers a way back ----------------------------------------------------------
foreach (array('/../lager/varekort.php' => 3, '/../lager/lagerflyt.php' => 1, '/../lager/ret_varenr.php' => 1, '/../lager/ret_varenralias.php' => 1) as $path => $count) {
	$src = lagerSource($path);
	lagerCheck(substr_count($src, "main.php#/lager/lister/vareliste.php?inframe=1") === $count,
		basename($path) . ' sends a refused user back to the item list');
	lagerCheck(substr_count($src, "target='_top'") === $count, basename($path) . ' names the top window, so no frame is left behind');
	lagerCheck(substr_count($src, "findtekst('30|Tilbage', \$sprog_id) . \"</a></div>") === $count, basename($path) . ' labels it Tilbage, not Luk');
	lagerCheck(substr_count($src, 'background:#1c7cb9') === $count, basename($path) . ' uses the Saldi blue, because the page does not load top_menu.css');
}

echo "\n" . ($failures ? count($failures) . " of $checks checks FAILED" : "all $checks checks passed") . "\n";
foreach ($failures as $failure) echo "  FAILED: $failure\n";
exit($failures ? 1 : 0);
