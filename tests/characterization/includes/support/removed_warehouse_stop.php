<?php
// Copyright (c) 2026 Danosoft ApS
// 20261116 CL/MMR SD-827 Runs order_lines_on_removed_warehouse() on fake order data and the real debitor/levering.php with doubles.
//                 Checks that a removed warehouse stops the page before the transaction begins, and exits non-zero on the first difference.
$root = dirname(__DIR__, 4);

// --- Doubles for the database layer used by removedWarehouseLines.php ---------------------------
$fixture = array();
function db_select($qtxt, $where = '') {
	$GLOBALS['queries'][] = $qtxt;
	$f = $GLOBALS['fixture'];
	$rows = array();
	if (strpos($qtxt, "from grupper where art = 'LG'") !== false) {
		foreach ($f['warehouses'] as $kodenr) {
			$rows[] = array('kodenr' => (string)$kodenr);
		}
	} elseif (strpos($qtxt, 'from ordrelinjer ol, varer v') !== false) {
		$rows = $f['lines'];
	} elseif (preg_match("/art = 'VG' and kodenr = '([^']*)' and fiscal_year = '(\d+)'/", $qtxt, $m)) {
		if ($m[2] === '10' && isset($f['groups'][$m[1]])) {
			$rows[] = array('box8' => $f['groups'][$m[1]]);
		}
	} elseif (preg_match("/from batch_salg where linje_id = '(\d+)'/", $qtxt, $m)) {
		$rows[] = array('antal' => $f['delivered'][$m[1]] ?? null);
	} elseif (preg_match("/saet = '(\d+)' and samlevare = 'on'/", $qtxt, $m)) {
		if (isset($f['sets'][$m[1]])) {
			$rows[] = array('varenr' => $f['sets'][$m[1]]);
		}
	} else {
		throw new RuntimeException("Unexpected query: $qtxt");
	}
	return new ArrayIterator($rows);
}
function db_fetch_array($it) {
	if (!$it->valid()) {
		return false;
	}
	$row = $it->current();
	$it->next();
	return $row;
}
function db_escape_string($s) { return str_replace("'", "''", (string)$s); }
function afrund($n, $d) { return round((float)$n, $d); }

require $root . '/debitor/orderIncludes/removedWarehouseLines.php';

function line($id, $posnr, $varenr, $antal, $leveres, $lager, $gruppe, $saet = 0, $samlevare = '') {
	return array('id' => $id, 'posnr' => $posnr, 'varenr' => $varenr, 'antal' => $antal, 'leveres' => $leveres,
		'lager' => $lager, 'gruppe' => $gruppe, 'saet' => $saet, 'samlevare' => $samlevare);
}
function check($label, $actual, $expected) {
	if ($actual !== $expected) {
		fwrite(STDERR, "FAIL: $label\nexpected " . json_encode($expected) . "\nactual   " . json_encode($actual) . "\n");
		exit(1);
	}
	echo "PASS: $label\n";
}

$lines = array(
	line(1, 1, 'DIB129WW', -1, -1, 10, '20'),              // removed warehouse, stock item, to deliver: reported
	line(2, 2, 'OK-ITEM', -1, -1, 4, '20'),                // existing warehouse
	line(3, 3, 'NO-WH', -1, -1, 0, '20'),                  // no warehouse: levering() posts it to 1, left alone
	line(4, 4, 'SERVICE', -1, -1, 10, '30'),               // varegruppe without stock
	line(5, 5, 'NOTHING-NOW', -1, 0, 10, '20'),            // nothing to deliver now (without hurtigfakturering)
	line(6, 100, 'PART-A', -1, -1, 10, '20', 1, ''),       // parts of set 1: reported once, as the set
	line(7, 101, 'PART-B', -4, -4, 10, '20', 1, ''),
	line(8, 102, 'SET-1', -1, 0, 10, '30', 1, 'on'),
	line(9, 6, 'DIB129WW', -2, -2, 10, '20'),              // same item and warehouse again: listed once
	line(10, 7, '  ', -1, -1, 10, '20'),                   // no item number: levering() skips it
);
$fixture = array(
	'warehouses' => array(1, 2, 4, 5),
	'lines' => $lines,
	'groups' => array('20' => 'on', '30' => ''),
	'delivered' => array(),
	'sets' => array('1' => 'SET-1'),
);
check('Lines on a removed warehouse are named by item, a set part by its set, each once',
	order_lines_on_removed_warehouse(41, '', 10),
	array(array('varenr' => 'DIB129WW', 'lager' => 10), array('varenr' => 'SET-1', 'lager' => 10)));

$fixture['lines'] = array(line(1, 1, 'FULL', 2, 0, 10, '20'), line(2, 2, 'HALF', 2, 0, 10, '20'));
$fixture['delivered'] = array('1' => '2', '2' => '1');
check('With hurtigfakturering only what is left to deliver counts',
	order_lines_on_removed_warehouse(41, 'on', 10),
	array(array('varenr' => 'HALF', 'lager' => 10)));

$fixture['warehouses'] = array(1);
$fixture['lines'] = $lines;
check('With one warehouse the Lager field is hidden, so nothing is stopped',
	order_lines_on_removed_warehouse(41, '', 10), array());

$fixture['warehouses'] = array();
check('Without warehouses nothing is stopped', order_lines_on_removed_warehouse(41, '', 10), array());

check('An order id that is not a number is never queried', order_lines_on_removed_warehouse("41' or '1'='1", '', 10) !== null
	&& !preg_grep("/'1'='1/", $GLOBALS['queries']), true);

// --- The real levering.php: a removed warehouse stops it before the transaction -----------------
$events = array();
function transaktion($command) { $GLOBALS['events'][] = $command; }
function levering(...$arguments) { $GLOBALS['events'][] = 'document'; return 'OK'; }
function findtekst($id, $sprog) { $p = explode('|', (string)$id, 2); return $p[1] ?? ''; }
function if_isset($value) { return $value; }
$src = file_get_contents($root . '/debitor/levering.php');
$src = preg_replace('/^[\t ]*include\("\.\.\/includes\/[^"\n]+"\);[\t ]*$/m', '', $src);
$src = preg_replace("/^[\\t ]*include_once\\(__DIR__ \\. '\\/orderIncludes\\/[^'\\n]+'\\);[\\t ]*$/m", '', $src, -1, $stripped);
check('levering.php includes the check from orderIncludes', $stripped, 1);
$src = str_replace(array('@session_start();', '$s_id=session_id();'), '', substr($src, strlen('<?php')));
$fixture = array('warehouses' => array(1, 4), 'lines' => array(line(1, 1, 'DIB129WW', -1, -1, 10, '20')),
	'groups' => array('20' => 'on'), 'delivered' => array(), 'sets' => array());
$regnaar = 10;
$sprog_id = 1;
$_GET = array('id' => '41', 'hurtigfakt' => 'on', 'genfakt' => '', 'pbs' => '', 'mail_fakt' => '', 'oioubl' => '');
register_shutdown_function(function () {
	$html = ob_get_clean();
	check('Nothing is posted: no transaction and no levering()', $GLOBALS['events'], array());
	check('The user is told which item and warehouse, translated text',
		strpos($html, 'Lageret på linjen findes ikke længere') !== false && strpos($html, 'DIB129WW: Lager 10') !== false, true);
	check('The message is one escaped JavaScript string in the attribute',
		(bool)preg_match('/<BODY onLoad="javascript:alert\(&quot;[^"<>]*&quot;\)">/', $html), true);
	check('The user is sent back to the order', strpos($html, 'URL=ordre.php?id=41"') !== false, true);
});
ob_start();
eval($src);
fwrite(STDERR, "FAIL: levering.php went on past the stop\n");
exit(1);
