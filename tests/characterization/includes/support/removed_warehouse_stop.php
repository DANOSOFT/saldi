<?php
// Copyright (c) 2026 Danosoft ApS
// 20261116 CL/MMR SD-827 Runs order_lines_on_removed_warehouse() on fake order data and the real debitor/levering.php with doubles.
//                 Checks that a removed warehouse stops the page before the transaction begins, and exits non-zero on the first difference.
//                 The real includes/std_func.php is loaded, so if_isset(), afrund() and the other helpers are the production ones.
//                 The bootstrap includes are left out in their __DIR__ form.

namespace {
	$root = dirname(__DIR__, 4);
	// The production helpers (if_isset, afrund, ...); std_func.php has no side effects.
	require_once $root . '/includes/std_func.php';

	// --- Doubles for the database layer (includes/db_query.php is not loaded) --------------------
	$fixture = array();
	$queries = array();
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

	$queries = array();
	order_lines_on_removed_warehouse("41' or '1'='1", '', 10);
	check('An order id that is not a number is never put into a query', preg_grep("/'1'='1/", $queries), array());

	// --- The real levering.php: a removed warehouse stops it before the transaction -------------
	$src = file_get_contents($root . '/debitor/levering.php');
	$src = preg_replace("/^[\\t ]*include(?:_once)?\\((?:\"\\.\\.\\/includes\\/[^\"\\n]+\"|__DIR__ \\. '\\/\\.\\.\\/includes\\/[^'\\n]+')\\);[\\t ]*$/m", '', $src, -1, $bootstrap);
	check('The five bootstrap includes are found and left out', $bootstrap, 5);
	$src = preg_replace("/^[\\t ]*include_once\\(__DIR__ \\. '\\/orderIncludes\\/[^'\\n]+'\\);[\\t ]*$/m", '', $src, -1, $stripped);
	check('levering.php includes the check from orderIncludes', $stripped, 1);
	$src = str_replace(array('@session_start();', '$s_id=session_id();'), '', substr($src, strlen('<?php')));
	$fixture = array('warehouses' => array(1, 4), 'lines' => array(line(1, 1, 'DIB129WW', -1, -1, 10, '20')),
		'groups' => array('20' => 'on'), 'delivered' => array(), 'sets' => array());
	$events = array();
	$regnaar = 10;
	$sprog_id = 1;
	$_GET = array('id' => '41', 'hurtigfakt' => 'on', 'genfakt' => '', 'pbs' => '', 'mail_fakt' => '', 'oioubl' => '');
	register_shutdown_function(function () {
		$html = ob_get_clean();
		check('Nothing is posted: no transaction and no levering()', $GLOBALS['events'], array());
		check('The user is told which item and warehouse, in the text from importfiler/tekster.csv',
			strpos($html, 'Lageret på linjen findes ikke længere') !== false && strpos($html, 'DIB129WW: Lager 10') !== false, true);
		check('The message is one escaped JavaScript string in the attribute',
			(bool)preg_match('/<BODY onLoad="javascript:alert\(&quot;[^"<>]*&quot;\)">/', $html), true);
		check('The user is sent back to the order', strpos($html, 'URL=ordre.php?id=41"') !== false, true);
	});
	ob_start();
	// The page runs in the fixture namespace, so its unqualified transaktion(), levering() and findtekst()
	// calls reach the doubles below, while if_isset() and every other helper fall back to std_func.php.
	eval('namespace RemovedWarehouseStopFixture;' . $src);
	fwrite(STDERR, "FAIL: levering.php went on past the stop\n");
	exit(1);
}

namespace RemovedWarehouseStopFixture {
	/** Records the transaction calls instead of opening one. */
	function transaktion($command) { $GLOBALS['events'][] = $command; }

	/** Records that delivery and posting were reached. */
	function levering(...$arguments) { $GLOBALS['events'][] = 'document'; return 'OK'; }

	/**
	 * The text from importfiler/tekster.csv for the id, without the database the real findtekst() needs.
	 *
	 * @param string $textId     '<id>|<default>' as the page passes it.
	 * @param int    $languageID Column: 1=da, 2=en, 3=no.
	 * @return string The csv text, or the default after '|' when the id has no line.
	 */
	function findtekst($textId, $languageID) {
		list($id, $default) = array_pad(explode('|', (string)$textId, 2), 2, '');
		foreach (file(dirname(__DIR__, 4) . '/importfiler/tekster.csv', FILE_IGNORE_NEW_LINES) as $row) {
			$cols = explode("\t", $row);
			if ($cols[0] === $id && isset($cols[(int)$languageID]) && $cols[(int)$languageID] !== '') {
				return $cols[(int)$languageID];
			}
		}
		return $default;
	}
}
