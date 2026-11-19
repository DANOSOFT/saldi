<?php
// --- includes/opdat_func/operatorPanelDialect.php --- ver 5.1.1 --- 2026-11-16 ---
// LICENSE: GNU GPL v2 or later; see admin/vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261116 CL/ASR New. The operator panel's SQL differences between PostgreSQL and MySQL/MariaDB in one place (review #785).
//                 New ids come from RETURNING on PostgreSQL and LAST_INSERT_ID() on MySQL/MariaDB, on the same connection.
//                 Locks follow initializeFormHtmlLayoutVersion(): pg_advisory_* on PostgreSQL, GET_LOCK()/RELEASE_LOCK() on MySQL/MariaDB, where the lock is released in a finally on success and on failure.
//                 The DDL helpers come from databaseDialect.php (opdat_id_column(), opdat_timestamp_column(), opdat_schema_filter(), opdat_index_query()).
//                 op_connect_ledger() selects the ledger's database after db_connect(), which selects none on MySQL/MariaDB (db_select_database()).
//                 op_table_exists() and op_column_exists() let the panel skip what an install does not have, e.g. license_features or brugere.email on a fresh install.

if (!function_exists('op_is_mysql')) {
	include_once(__DIR__ . '/databaseDialect.php');

	/** @return string the connection driver in lower case, as databaseDialect.php expects it */
	function op_db_type() {
		global $db_type;
		return strtolower((string)$db_type);
	}

	/** @return bool true on a MySQL/MariaDB master (db_type mysql or mysqli) */
	function op_is_mysql() {
		return opdat_is_mysql(op_db_type());
	}

	/**
	 * A boolean column as a PHP bool: PostgreSQL returns 't'/'f', MySQL/MariaDB 1/0.
	 *
	 * @param mixed $v the value from db_fetch_array()
	 * @return bool
	 */
	function op_bool($v) {
		return $v === true || $v === 1 || $v === 't' || $v === '1' || $v === 'true';
	}

	/** @return string the column named key, quoted on MySQL/MariaDB, where KEY is a reserved word */
	function op_key() {
		return op_is_mysql() ? '`key`' : 'key';
	}

	/** @return string a nullable timestamp column type: DATETIME on MySQL/MariaDB (no implicit default or time zone conversion) */
	function op_ts() {
		return op_is_mysql() ? 'DATETIME' : 'timestamp';
	}

	/**
	 * Connect to a ledger's own database and make it the current connection. db_connect() selects no database on
	 * MySQL/MariaDB, so db_select_database() follows it (a no-op on PostgreSQL). include("../includes/connect.php")
	 * goes back to the master database afterwards, as before.
	 *
	 * @param string $dbname the ledger's database (regnskab.db)
	 * @param string $spor the caller's trace
	 * @return mixed the connection, or false
	 */
	function op_connect_ledger($dbname, $spor) {
		global $sqhost, $squser, $sqpass;
		$c = @db_connect($sqhost, $squser, $sqpass, $dbname, $spor);
		if (!$c || !db_select_database($c, $dbname)) return false;
		return $c;
	}

	/**
	 * Whether a table exists in the current database (current_schema() on PostgreSQL, DATABASE() on MySQL/MariaDB).
	 *
	 * @param string $table
	 * @return bool
	 */
	function op_table_exists($table) {
		return (bool)db_fetch_array(db_select("SELECT 1 FROM information_schema.tables WHERE table_name = '" . db_escape_string($table) . "'" . opdat_schema_filter(op_db_type()), __FILE__ . " linje " . __LINE__));
	}

	/**
	 * Whether a column exists in the current database.
	 *
	 * @param string $table
	 * @param string $column
	 * @return bool
	 */
	function op_column_exists($table, $column) {
		return (bool)db_fetch_array(db_select("SELECT 1 FROM information_schema.columns WHERE table_name = '" . db_escape_string($table) . "' AND column_name = '" . db_escape_string($column) . "'" . opdat_schema_filter(op_db_type()), __FILE__ . " linje " . __LINE__));
	}

	/**
	 * Run an INSERT into a table whose key is the generated column id, and return the new id.
	 *
	 * @param string $sql the INSERT, without RETURNING
	 * @param string $spor the caller's trace (__FILE__ . " linje " . __LINE__)
	 * @param bool $global run it against the master database (db_modify()/db_select()'s third argument)
	 * @return int the new id, 0 when none came back
	 */
	function op_insert_id($sql, $spor, $global = false) {
		if (op_is_mysql()) {
			db_modify($sql, $spor, $global);
			$r = db_fetch_array(db_select("SELECT LAST_INSERT_ID() AS id", $spor, $global));
		} else {
			$r = db_fetch_array(db_select("$sql RETURNING id", $spor, $global));
		}
		return $r ? (int)$r['id'] : 0;
	}

	/**
	 * MySQL/MariaDB lock names are server-wide and at most 64 characters: the master database's name is part of the hash,
	 * so two masters on one server do not share a lock.
	 *
	 * @param string $name
	 * @return string
	 */
	function op_lock_name($name) {
		global $sqdb;
		return 'saldi:' . md5((string)$sqdb . ':' . $name);
	}

	/**
	 * Take a session lock. PostgreSQL: pg_advisory_lock() (waits) or pg_try_advisory_lock() ($wait 0).
	 * MySQL/MariaDB: GET_LOCK() with $wait seconds; it is re-entrant, so a caller may take a lock it already holds.
	 *
	 * @param string $name
	 * @param int $wait seconds to wait on MySQL/MariaDB; 0 = try once on both
	 * @param bool $global take it on the master connection
	 * @return bool true when the lock is held
	 */
	function op_lock($name, $wait = 30, $global = false) {
		if (op_is_mysql()) {
			$r = db_fetch_array(db_select("SELECT GET_LOCK('" . op_lock_name($name) . "', " . (int)$wait . ") AS ok", __FILE__ . " linje " . __LINE__, $global));
			return (int)($r['ok'] ?? 0) === 1;
		}
		$key = "hashtext('" . db_escape_string($name) . "')";
		if ($wait) {
			db_select("SELECT pg_advisory_lock($key)", __FILE__ . " linje " . __LINE__, $global);
			return true;
		}
		$r = db_fetch_array(db_select("SELECT CASE WHEN pg_try_advisory_lock($key) THEN 1 ELSE 0 END AS ok", __FILE__ . " linje " . __LINE__, $global));
		return !empty($r['ok']);
	}

	/**
	 * Release a lock taken with op_lock().
	 *
	 * @param string $name
	 * @param bool $global
	 * @return void
	 */
	function op_unlock($name, $global = false) {
		if (op_is_mysql()) db_select("SELECT RELEASE_LOCK('" . op_lock_name($name) . "')", __FILE__ . " linje " . __LINE__, $global);
		else db_select("SELECT pg_advisory_unlock(hashtext('" . db_escape_string($name) . "'))", __FILE__ . " linje " . __LINE__, $global);
	}

	/**
	 * Run $fn in a transaction under the named lock.
	 * PostgreSQL: pg_advisory_xact_lock(), so inside an outer transaction the lock is held until that transaction ends.
	 * MySQL/MariaDB: GET_LOCK() before the transaction and RELEASE_LOCK() after it, in a finally. A caller that runs this
	 * inside its own transaction takes the same lock around that transaction (op_lock()), so it is held until the commit there too.
	 * Without an open transaction the call gets its own; $fn returns '' or an error, and an error rolls that transaction back.
	 *
	 * @param string $name
	 * @param callable $fn
	 * @return string '' or the error
	 */
	function op_locked($name, $fn) {
		global $db_transaktion_depth;
		$own = !$db_transaktion_depth;
		$mysql = op_is_mysql();
		if ($mysql && !op_lock($name)) return 'Kunne ikke få lås – prøv igen om lidt';
		try {
			if ($own) transaktion('begin');
			if (!$mysql) db_select("SELECT pg_advisory_xact_lock(hashtext('" . db_escape_string($name) . "'))", __FILE__ . " linje " . __LINE__);
			try {
				$err = (string)$fn();
			} catch (Throwable $e) {
				if ($own) transaktion('rollback');
				throw $e;
			}
			if ($own) transaktion($err !== '' ? 'rollback' : 'commit');
			return $err;
		} finally {
			if ($mysql) op_unlock($name);
		}
	}
}
?>
