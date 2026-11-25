<?php
// 20261125 CDX/ASR Execute entitlement SQL against two private PostgreSQL schemas to verify master/tenant routing.

/** @return PDOStatement Statements run on real PostgreSQL; the global flag selects the master connection. */
function db_select($sql, $trace = '', $global = false)
{
	return $GLOBALS[$global ? 'ent_test_master' : 'ent_test_tenant']->query($sql);
}
/** @return PDOStatement */
function db_modify($sql, $trace = '', $global = false) { return db_select($sql, $trace, $global); }
/** @return array|false */
function db_fetch_array($statement) { return $statement->fetch(PDO::FETCH_ASSOC); }
/** @return string */
function db_escape_string($value) { return substr($GLOBALS['ent_test_master']->quote((string) $value), 1, -1); }
