<?php
// tests/characterization/support/run_legacy_shop_sync.php
//
// Child-process runner for the legacy shop sync in includes/std_func.php (SD-750):
// hent_shop_ordrer(), sync_shop_price() and sync_shop_vare().
//
// Those functions read grupper/varer/lagerstatus through db_select() and call the shop
// endpoints stored in grupper art='API' box4/box5/box6. This runner defines a scripted
// stand-in for the db_* functions before including the real std_func.php, so the
// functions run unchanged without a database: each query is answered by the first
// pattern it matches, and every query is recorded. The endpoints point at
// LegacyShopStub, so what was sent can be read back from there.
//
// argv: 1 = path to a JSON spec:
//   work    dir to run in; the functions write ../temp/<db>/ relative to <work>/page
//   db      tenant name used in the temp paths
//   regnaar fiscal year (sync_shop_vare's item-group lookup)
//   rows    list of [regex, list of rows]: the answer to a query matching regex
//   call    function name, args list of arguments
//
// Prints one JSON line: {return, sql}.
//
// History:
// 20261026 CL/SZ Created (SD-777, sub-task of SD-750).

$spec = json_decode((string)file_get_contents($argv[1] ?? ''), true);
if (!is_array($spec)) {
	fwrite(STDERR, "usage: php run_legacy_shop_sync.php <spec.json>\n");
	exit(2);
}

$GLOBALS['chartestRows'] = $spec['rows'];
$GLOBALS['chartestSql'] = [];

function db_select($qtxt, $where = '')
{
	$GLOBALS['chartestSql'][] = $qtxt;
	foreach ($GLOBALS['chartestRows'] as [$pattern, $rows]) {
		if (preg_match($pattern, $qtxt)) {
			return new ArrayIterator($rows);
		}
	}
	return new ArrayIterator([]);
}

function db_fetch_array($query)
{
	if (!$query instanceof ArrayIterator || !$query->valid()) {
		return false;
	}
	$row = $query->current();
	$query->next();
	return $row;
}

function db_modify($qtxt, $where = '')
{
	$GLOBALS['chartestSql'][] = $qtxt;
}

function db_escape_string($text)
{
	return str_replace("'", "''", (string)$text);
}

$db = $spec['db'];
$regnaar = $spec['regnaar'] ?? 1;
$bruger_id = 1;

require dirname(__DIR__, 3) . '/includes/std_func.php';

chdir($spec['work'] . '/page');
$return = $spec['call'](...$spec['args']);

echo "\n" . json_encode(['return' => $return, 'sql' => $GLOBALS['chartestSql']], JSON_UNESCAPED_SLASHES) . "\n";
