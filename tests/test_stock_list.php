<?php
// --- tests/test_stock_list.php --- 2026-10-27 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261027 LOE Cover MB-99: opening the stock list writes nothing, its sort and warehouse input cannot reach
//                 SQL uncast, and a user without a warehouse gets a picker instead of being logged out.
//
//   php tests/test_stock_list.php
//
// The page is session-gated and cannot be included from a test, so the rules are read out of it.

$checks = 0;
$failures = array();
function stockCheck($condition, $description) {
	global $checks, $failures;
	$checks++;
	if (!$condition) $failures[] = $description;
}
$src = file_get_contents(__DIR__ . '/../lager/beholdningsliste.php');
if ($src === false) $failures[] = 'cannot read lager/beholdningsliste.php';
$src = (string)$src;
# the page carries a large commented-out legacy block; the rules below are about the live code
$live = preg_replace('#/\*.*?\*/#s', '', $src);

# ---- opening the list writes nothing (MB-99 criterion 1) -------------------------------------
stockCheck(strpos($live, 'db_modify(') === false, 'the live page contains no write at all');
stockCheck(strpos($live, "set beholdning=") === false, 'and in particular no beholding repair on view');
stockCheck(strpos($src, "\$row['beholdning'] = 0;") !== false, 'an item without a balance is shown as 0 instead');

# ---- the sort cannot be injected (criterion 2) ------------------------------------------------
stockCheck(strpos($src, "\$sorteringer = array('varenr', 'beskrivelse', 'salgspris', 'beholdning');") !== false,
	'the sortable columns are a fixed list');
$whitelist = strpos($src, 'in_array($sort, $sorteringer, true)');
$firstOrder = strpos($live, 'order by $sort');
stockCheck($whitelist !== false, 'the sort is checked against that list');
stockCheck($whitelist !== false && $firstOrder !== false && $whitelist < $firstOrder,
	'and checked before the query uses it');
stockCheck(strpos($src, "\$sort = if_isset(\$_GET, '', 'sort');") !== false, 'the sort comes in through if_isset()');
stockCheck(substr_count($live, 'order by $sort') === 1, 'exactly one query in the live code sorts by it');

# ---- the warehouse is a number that exists (criterion 2) --------------------------------------
stockCheck(strpos($src, "\$lager = (int) if_isset(\$_GET, 0, 'lager');") !== false,
	'the warehouse is cast to a number as it is read');
stockCheck(strpos($src, "select beskrivelse from grupper where art='LG' and kodenr='\$lager'") !== false,
	'and it must be a warehouse that exists');
stockCheck(strpos($src, 'and lager=$lager') !== false, 'only the validated value reaches the stock queries');

# ---- a user without a warehouse chooses one instead of losing the session (criterion 3) ------
stockCheck(strpos($src, 'logud.php') === false, 'the page no longer ends the session');
stockCheck(strpos($src, "findtekst('586|Vælg'") !== false, 'it asks the user to choose');
stockCheck(strpos($src, 'beholdningsliste.php?lager=') !== false, 'and offers the warehouses as links');
stockCheck(strpos($src, "select beskrivelse, kodenr from grupper where art='LG' order by kodenr") !== false,
	'built from the warehouse list');
	stockCheck(strpos($src, 'if (!$lager && !empty($ansat_id))') !== false, 'the employee department is still honoured first');
	stockCheck(strpos($src, 'if (!$lagre) {') !== false, 'an account with no warehouses falls back to the totals');

# ---- the chosen warehouse survives a sort, and the name is escaped (criterion 4) -------------
stockCheck(substr_count($src, '&amp;lager=$lager') === 6, 'all six sort and cost links keep the warehouse');
stockCheck(strpos($src, "htmlspecialchars(\$lagernavn, ENT_QUOTES, \$charset)") !== false,
	'the warehouse name is escaped with the tenant charset');
stockCheck(strpos($src, "\$charset = (\$db_encode == \"UTF8\") ? \"UTF-8\" : \"ISO-8859-1\";") !== false,
	'which comes from the tenant, not a hardcoded ISO-8859-1');
stockCheck(strpos($src, 'content=\\"text/html; charset=$charset\\"') !== false, 'and the document declares it');

# ---- no raw request value reaches SQL ---------------------------------------------------------
foreach (explode("\n", $src) as $line) {
	if (strpos($line, '$_GET') !== false && strpos($line, 'if_isset') === false) {
		stockCheck(false, 'a raw $_GET use survives: ' . trim($line));
	}
}

echo "\n" . ($failures ? count($failures) . " of $checks checks FAILED" : "all $checks checks passed") . "\n";
foreach ($failures as $failure) echo "  FAILED: $failure\n";
exit($failures ? 1 : 0);
