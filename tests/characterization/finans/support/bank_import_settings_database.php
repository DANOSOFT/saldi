<?php
// 20261116 CL/MMR SST-962 Run the settings queries of includes/stdFunc/bankImportColumns.php against a private schema on the PostgreSQL connection in $GLOBALS['connection'].
//                 BankImportColumnsTest and its child process use it.
// 20261116 CL/MMR SST-962 Codex round 3: a failed query is read with pg_get_result(), so the tests of a failing lock or write get the exception without a PHP warning.
// 20261116 CL/MMR SST-962 CodeRabbit: every statement is logged, and a test can make one statement return false like the real db_select().
//                 The injected statement is read with ifset() (doc/ai/convention_ifset.md, Codex round 5).

if (!function_exists('db_select')) {
	/** @return PgSql\Result|false */
	function db_select($sql, $source = '', $global = false) {
		$GLOBALS['bank_import_sql_log'][] = $sql;
		// A test can make one statement fail the way the real db_select() can report it: by returning false
		$fails = (string)ifset($GLOBALS, 'bank_import_select_returns_false', '');
		if ($fails !== '' && stripos($sql, $fails) !== false) {
			return false;
		}
		// pg_send_query() + pg_get_result() report a failed query as a result, not as a PHP warning, so it becomes the exception only
		if (!pg_send_query($GLOBALS['connection'], $sql) || ($result = pg_get_result($GLOBALS['connection'])) === false) {
			throw new RuntimeException(pg_last_error($GLOBALS['connection']) . ' | ' . $sql);
		}
		$error = pg_result_error($result);
		if ($error !== '' && $error !== false) {
			throw new RuntimeException($error . ' | ' . $sql);
		}
		return $result;
	}
}

if (!function_exists('db_modify')) {
	/** @return PgSql\Result */
	function db_modify($sql, $source = '', $global = false) {
		return db_select($sql, $source, $global);
	}
}

if (!function_exists('db_fetch_array')) {
	/** @return array|false */
	function db_fetch_array($result) {
		return pg_fetch_array($result);
	}
}

if (!function_exists('db_escape_string')) {
	/** @return string */
	function db_escape_string($value) {
		return pg_escape_string($GLOBALS['connection'], (string)$value);
	}
}
