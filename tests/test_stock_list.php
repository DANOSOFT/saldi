<?php
// --- tests/test_stock_list.php --- 2026-10-27 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261027 LOE Cover MB-99: opening the stock list writes nothing, its sort and warehouse input cannot reach
//                 SQL uncast, and a user without a warehouse gets a picker instead of being logged out.
// 20261027 CL/LH Cover the #902 review: the balance sort sums the warehouse's variant rows and counts a
//                missing balance as 0, the column shows that sum, and both run on a real PostgreSQL.
//
//   php tests/test_stock_list.php
//
// The page is session-gated and cannot be included from a test, so the rules are read out of it. With
// SALDI_CHAR_DSN, SALDI_CHAR_PGUSER and SALDI_CHAR_PGPASS set, the page's own sort and stock SQL also
// run against temporary tables (rolled back); without them those cases are skipped.

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
$firstOrder = strpos($live, 'order by $orderby');
stockCheck($whitelist !== false, 'the sort is checked against that list');
stockCheck($whitelist !== false && $firstOrder !== false && $whitelist < $firstOrder,
	'and checked before the query uses it');
stockCheck(strpos($src, "\$sort = if_isset(\$_GET, '', 'sort');") !== false, 'the sort comes in through if_isset()');
stockCheck(substr_count($live, 'order by $orderby') === 1, 'exactly one query in the live code sorts, by the checked expression');

# ---- the warehouse is a number that exists (criterion 2) --------------------------------------
stockCheck(strpos($src, "\$lager = (int) if_isset(\$_GET, 0, 'lager');") !== false,
	'the warehouse is cast to a number as it is read');
stockCheck(strpos($src, "select beskrivelse from grupper where art='LG' and kodenr='\$lager'") !== false,
	'and it must be a warehouse that exists');
stockCheck(strpos($src, 'and lager=$lager') !== false, 'only the validated value reaches the stock queries');

# ---- the cost-price switch and the balance sort (review) --------------------------------------
stockCheck(strpos($src, "\$vis_kost = (if_isset(\$_GET, '', 'vis_kost') === 'on') ? 'on' : '';") !== false,
	'only "on" is accepted for the cost-price switch, which is put back into the links');
stockCheck(strpos($src, "if (\$sort === 'beholdning') {") !== false, 'the balance column sorts by what it shows');
stockCheck(strpos($src, 'coalesce((select sum(beholdning) from lagerstatus where vare_id = varer.id and lager = $lager), 0)') !== false,
	'which is the selected warehouse stock summed over its variant rows, with a missing row counting as 0');
stockCheck(strpos($src, 'coalesce(beholdning, 0) from lagerstatus') === false,
	'and the coalesce wraps the subquery, or a missing row would sort as NULL (last) instead of 0');
stockCheck(strpos($src, ": 'coalesce(beholdning, 0)';") !== false,
	'without a warehouse the total sorts with a missing balance as the 0 the column shows');
stockCheck(strpos($src, 'select coalesce(sum(beholdning), 0) as beholdning from lagerstatus where vare_id=$row[id] and lager=$lager') !== false,
	'the column shows the same summed warehouse stock the sort uses');
stockCheck(strpos($src, "\$orderby = \$sort;") !== false, 'and every other column sorts by itself');

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

# ---- the balance sort and column on a real PostgreSQL (review) --------------------------------
# An item has one lagerstatus row per variant in a warehouse. A bare scalar subquery then aborts the
# list with "more than one row returned by a subquery used as an expression", and NULL sorts after
# every positive balance although the column prints it as 0. The page's own SQL is run here.
if (!getenv('SALDI_CHAR_DSN')) {
	echo "SKIP: the database cases need SALDI_CHAR_DSN, SALDI_CHAR_PGUSER and SALDI_CHAR_PGPASS.\n";
} else {
	$orderStart = strpos($live, '$orderby = $sort;');
	$orderEnd = ($orderStart === false) ? false : strpos($live, '$linjebg = NULL;', $orderStart);
	$hasStockQuery = preg_match('/db_select\("([^"]*from lagerstatus where vare_id=\$row\[id\] and lager=\$lager)"/', $live, $stockMatch);
	stockCheck($orderEnd !== false, 'the sort block can be read out of the page');
	stockCheck($hasStockQuery === 1, 'the warehouse stock query can be read out of the page');
	if ($orderEnd !== false && $hasStockQuery === 1) {
		$orderBlock = substr($live, $orderStart, $orderEnd - $orderStart);
		$pdo = new PDO(getenv('SALDI_CHAR_DSN'), getenv('SALDI_CHAR_PGUSER'), getenv('SALDI_CHAR_PGPASS'), array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
		$pdo->beginTransaction();
		try {
			$pdo->exec('CREATE TEMP TABLE varer (id integer, varenr text, lukket text, beholdning numeric)');
			$pdo->exec('CREATE TEMP TABLE lagerstatus (id serial, vare_id integer, variant_id integer, lager integer, beholdning numeric)');
			# warehouse 1: A has two variant rows (2 + 5), B one row (3), C none, D a variant row without a
			# balance next to one of 1; warehouse 2 holds 100 of A and must not count. Totals: C has none.
			$pdo->exec("INSERT INTO varer VALUES (1, 'A', '', 10), (2, 'B', '', 3), (3, 'C', '', NULL), (4, 'D', '', 1)");
			$pdo->exec('INSERT INTO lagerstatus (vare_id, variant_id, lager, beholdning) VALUES (1, 11, 1, 2), (1, 12, 1, 5), (2, 0, 1, 3), (4, 41, 1, NULL), (4, 0, 1, 1), (1, 0, 2, 100)');
			$sortedBy = function ($lager) use ($pdo, $orderBlock) {
				$sort = 'beholdning';
				eval($orderBlock);
				$pdo->exec('SAVEPOINT stock_sort');
				try {
					$order = $pdo->query("select varenr from varer where lukket != '1' order by $orderby, varenr")->fetchAll(PDO::FETCH_COLUMN);
				} catch (PDOException $e) {
					$pdo->exec('ROLLBACK TO SAVEPOINT stock_sort');
					return 'SQL error: ' . $e->getMessage();
				}
				return implode(',', $order);
			};
			$order = $sortedBy(1);
			stockCheck($order === 'C,D,B,A', "warehouse 1 sorts by its summed stock, a missing row as 0 (got $order)");
			$order = $sortedBy(0);
			stockCheck($order === 'C,D,B,A', "the totals sort a missing balance as 0, not last (got $order)");
			$shown = array();
			foreach (array(1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D') as $id => $varenr) {
				$row = array('id' => $id);
				$lager = 1;
				$stockSql = eval('return "' . $stockMatch[1] . '";');
				$stock = $pdo->query($stockSql)->fetch(PDO::FETCH_ASSOC);
				$shown[] = $varenr . '=' . ($stock ? (float) $stock['beholdning'] : 'none');
			}
			$shown = implode(',', $shown);
			stockCheck($shown === 'A=7,B=3,C=0,D=1', "the column shows the same summed stock per item (got $shown)");
		} finally {
			$pdo->rollBack();
		}
	}
}

echo "\n" . ($failures ? count($failures) . " of $checks checks FAILED" : "all $checks checks passed") . "\n";
foreach ($failures as $failure) echo "  FAILED: $failure\n";
exit($failures ? 1 : 0);
