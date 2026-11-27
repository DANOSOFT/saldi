<?php
// tests/characterization/rental/support/innodb_booking_stub_database.php
//
// Scripted database answers for RentalStandLockTest: the booking tables on MySQL or MariaDB, whose InnoDB tables run
// at REPEATABLE READ by default. A plain read in a transaction reads the snapshot taken at the transaction's first plain
// read; a locking read (FOR UPDATE) reads the newest committed rows and takes no snapshot. Outside a transaction every
// statement reads the newest committed rows.
// Another save holds the stand's lock when this one asks for it, and commits its booking before it lets go.
// Every statement is recorded in $GLOBALS['innodbStub']['statements'].
//
// History:
// 20261105 CL/LH Created.

/**
 * Sets up the database a save runs against.
 *
 * @param list<int>                                                                    $stands  rentalitems ids
 * @param list<array{id:int, item_id:int, rt_from:int, rt_to:int, order_id:int}>       $periods committed rentalperiod rows
 * @param array{id:int, item_id:int, rt_from:int, rt_to:int, order_id:int}|null        $waiting the booking another save commits
 *                                                                                              while this one waits for the stand
 */
function innodbStubDatabase(array $stands, array $periods, ?array $waiting) {
	$GLOBALS['db_type'] = 'mysqli';
	$GLOBALS['innodbStub'] = [
		'stands' => $stands,
		'committed' => $periods,
		'waiting' => $waiting,
		'inTransaction' => false,
		'snapshot' => null,
		'statements' => [],
	];
}

function transaktion($qtext) {
	$stub = &$GLOBALS['innodbStub'];
	$stub['statements'][] = $qtext;
	$stub['inTransaction'] = strtolower(trim($qtext)) === 'begin';
	$stub['snapshot'] = null;
}

function db_select($qtext, $spor, $global = false) {
	$stub = &$GLOBALS['innodbStub'];
	$stub['statements'][] = $qtext;
	if (preg_match('/^select id from rentalitems where id = (\d+) for update$/i', $qtext, $lock)) {
		if ($stub['waiting'] !== null) {
			$stub['committed'][] = $stub['waiting'];
			$stub['waiting'] = null;
		}
		return new ArrayIterator(in_array((int)$lock[1], $stub['stands'], true) ? [['id' => (int)$lock[1]]] : []);
	}
	if (preg_match('/ from ordrer where /i', $qtext)) {
		return new ArrayIterator([]);
	}
	if (!preg_match('/^select (.+?) from rentalperiod where (.+)$/i', $qtext, $query)) {
		throw new RuntimeException("Not scripted: $qtext");
	}
	if ($stub['inTransaction'] && $stub['snapshot'] === null) {
		$stub['snapshot'] = $stub['committed'];
	}
	$rows = $stub['inTransaction'] ? $stub['snapshot'] : $stub['committed'];
	foreach (preg_split('/\s+and\s+/i', trim($query[2])) as $condition) {
		if (!preg_match('/^(\w+)\s*(=|<>|<=|>=)\s*(-?\d+)$/', $condition, $c)) {
			throw new RuntimeException("Not scripted: $condition in $qtext");
		}
		$rows = array_filter($rows, static function (array $row) use ($c) {
			$value = $row[$c[1]];
			$limit = (int)$c[3];
			return $c[2] === '=' ? $value === $limit : ($c[2] === '<>' ? $value !== $limit : ($c[2] === '<=' ? $value <= $limit : $value >= $limit));
		});
	}
	$columns = [];
	foreach (array_map('trim', explode(',', $query[1])) as $column) {
		$parts = preg_split('/\s+as\s+/i', $column);
		$columns[$parts[1] ?? $parts[0]] = $parts[0];
	}
	return new ArrayIterator(array_values(array_map(static function (array $row) use ($columns) {
		if (isset($columns['*'])) {
			return $row;
		}
		$selected = [];
		foreach ($columns as $alias => $column) {
			$selected[$alias] = $row[$column];
		}
		return $selected;
	}, $rows)));
}

function db_modify($qtext, $spor, $global = false) {
	$GLOBALS['innodbStub']['statements'][] = $qtext;
	return true;
}

function db_fetch_array($result) {
	if (!$result instanceof ArrayIterator || !$result->valid()) {
		return false;
	}
	$row = $result->current();
	$result->next();
	return $row;
}

function db_num_rows($result) {
	return $result instanceof ArrayIterator ? $result->count() : 0;
}

function db_escape_string($text) {
	return addslashes((string)$text);
}
