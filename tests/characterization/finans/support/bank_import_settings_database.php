<?php
// 20261116 CL/MMR SST-962 Run the settings queries of includes/stdFunc/bankImportColumns.php against a private schema on the PostgreSQL connection in $GLOBALS['connection'].
//                 BankImportColumnsTest and its child process use it.
// 20261116 CL/MMR SST-962 Codex round 3: a failed query is read with pg_get_result(), so the tests of a failing lock or write get the exception without a PHP warning.

if (!function_exists('db_select')) {
	/** @return PgSql\Result */
	function db_select($sql, $source = '', $global = false) {
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
