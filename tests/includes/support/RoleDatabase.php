<?php
// 20261110 CDX/ASR Disposable-schema database adapter for real role transaction tests.

function db_select($sql, $source = '', $global = false)
{
	return $GLOBALS['role_test_db']->query($sql);
}

function db_fetch_array($result)
{
	return $result->fetch(PDO::FETCH_ASSOC);
}

function db_modify($sql, $source = '', $global = false)
{
	$fail = $GLOBALS['role_test_fail'] ?? '';
	if ($fail !== '' && strpos($sql, $fail) !== false) {
		throw new RuntimeException('Injected write failure');
	}
	$GLOBALS['role_test_db']->exec($sql);
	return "0\tquery accepted";
}

function transaktion($sql): void
{
	$GLOBALS['role_test_db']->exec($sql);
}

function db_escape_string($value): string
{
	return substr($GLOBALS['role_test_db']->quote((string) $value), 1, -1);
}

function tbl_exists($table): bool
{
	$stmt = $GLOBALS['role_test_db']->prepare('select 1 from information_schema.tables where table_schema = current_schema() and table_name = ?');
	$stmt->execute(array($table));
	return (bool) $stmt->fetchColumn();
}

function saldi_schema_filter(): string
{
	return ' and table_schema = current_schema()';
}

function ifset($values, $key, $default = null)
{
	return $values[$key] ?? $default;
}
