<?php
// 20261110 CDX/NTR SST-980 Run the real wage-slip controller with recorded database operations.

final class WageSlipRenderReached extends RuntimeException {}

final class WageSlipHarness {
	public static array $slip = array();
	public static array $queries = array();
	public static array $writes = array();
	public static array $transactions = array();
	public static ?int $initialCase = null;
	public static bool $stopBeforeRender = false;

	public static function select(string $sql): ArrayIterator {
		self::$queries[] = $sql;
		if (str_starts_with($sql, 'select sag_id from loen')) {
			return new ArrayIterator(array(array('sag_id' => self::$initialCase ?? self::$slip['sag_id'])));
		}
		if (str_starts_with($sql, 'select * from loen where id=')) {
			return new ArrayIterator(self::$slip ? array(self::$slip) : array());
		}
		if (str_starts_with($sql, 'select max(id) as id')) {
			return new ArrayIterator(array(array('id' => 100)));
		}
		if (str_contains($sql, "var_name = 'hideSalary'")) {
			throw new WageSlipRenderReached('The authorized action reached rendering');
		}
		return new ArrayIterator(array());
	}
}

function db_select($sql, $where = '') {
	return WageSlipHarness::select($sql);
}

function db_fetch_array($rows) {
	if (!$rows->valid()) {
		return false;
	}
	$row = $rows->current();
	$rows->next();
	return $row;
}

function db_modify($sql, $where = '') {
	WageSlipHarness::$writes[] = $sql;
}

function transaktion($action) {
	WageSlipHarness::$transactions[] = $action;
	if ($action === 'begin' && WageSlipHarness::$stopBeforeRender && count(array_filter(WageSlipHarness::$transactions, fn($item) => $item === 'begin')) > 1) {
		throw new WageSlipRenderReached('The authorized action reached rendering');
	}
}

function db_escape_string($value) {
	return str_replace("'", "''", (string)$value);
}

function findtekst($text, $language = 1) {
	return explode('|', (string)$text, 2)[1] ?? (string)$text;
}
