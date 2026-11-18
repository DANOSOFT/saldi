<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_func/ensurePoolFilesSchema.php --- ver 5.1.1 --- 2026-11-18 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261118 CL/MMR SD-815 Created. Gives pool_files the columns and the unique filename index the pool writers need (poolUploadRegisterFile() inserts norm_amount with ON CONFLICT (filename)), called from betweenUpdates.php.
//                 A regnskab created at 5.1 or later never ran opdat_5.0.php, where these were added, so its pool_files lacked them and every write into the Bilagspulje failed.
// 20261118 CL/MMR SD-815 Codex round 7: on PostgreSQL only a valid unique index on filename alone, without a predicate or expression, counts (a partial one cannot serve ON CONFLICT (filename)).
//                 The norm_amount backfill has its own completion flag (settings system/pool_files_norm_amount_backfilled, as opdat_5.0.php), set only after it has run, so an interrupted run is resumed by the next login.
// 20261118 CL/MMR SD-815 Codex round 8: a deferrable unique constraint on filename (ON CONFLICT cannot use it) is replaced by an immediate unique index.
//                 The backfill flag is set only when a re-read finds no row left to fill (db_modify() reports a failed write from an update file as accepted), and an amount numeric(15,3) cannot hold stays NULL like an unparseable one.
// 20261118 CL/MMR SD-815 Codex round 9: a backfill update only writes while norm_amount is still NULL and amount is still the text it was computed from, so a pool save that commits meanwhile is never overwritten.
// 20261118 CL/MMR SD-815 Codex round 10: the text is compared by the md5 the database computes, never put into the SQL (db_modify() turns ' like ' into ' ilike ' inside literals, and injecttjek() reads quotes and ';').
// 20261118 CL/MMR SD-815 Codex round 11: a read that fails (db_select() returns false, e.g. a lock timeout) means «unknown», never «nothing to do»: the run stops without DDL or flag and the next login tries again.
// 20261118 CL/MMR SD-815 Codex round 12: on MySQL/MariaDB PHP 8's mysqli throws mysqli_sql_exception instead of returning false; a failed read or write is handled the same way and the login goes on.
// 20261118 CL/MMR SD-815 Codex round 13: on PostgreSQL the filename repair (drop deferrable constraints, collapse duplicates, create the index) runs in one transaction (a savepoint inside a caller's transaction) and is kept only when the index is read back; otherwise it is rolled back and the run stops.
//                 «unique_filename» is reported only after that read-back, on MySQL too.
// 20261118 CL/MMR SD-815 Codex round 14: inside a caller's PostgreSQL transaction the whole run sits in a savepoint with a transaction-level advisory lock, so a failure rolls back to it and leaves the caller's transaction usable and no lock behind.
//                 On MySQL/MariaDB the duplicate collapse and the unique index run under LOCK TABLES pool_files WRITE: if the table lock cannot be had nothing is deleted, and once it is held the index cannot be blocked by another session.
// 20261118 CL/MMR SD-815 Codex round 15: on MySQL/MariaDB the run does nothing while the connection is inside a transaction (DDL and LOCK TABLES would commit the caller's work); a later login does it.
//                 The unique index gets a name no index has yet (an existing non-unique pool_files_filename_uidx made CREATE fail after the duplicate collapse); the name is chosen before anything is deleted.
// 20261118 CL/MMR SD-815 Codex round 16: on MySQL/MariaDB the unique index is first tried on an empty copy of pool_files (CREATE TABLE ... LIKE, same row format and character set); if the table cannot hold it (e.g. ROW_FORMAT=COMPACT with utf8mb4) nothing is deleted and the reason is logged.
// 20261118 CL/MMR SD-815 Codex round 17: the copy is a TEMPORARY table with a random name, read with SHOW INDEX and removed with DROP TEMPORARY TABLE, so no permanent table can ever be dropped.
// 20261118 CL/MMR SD-815 Codex round 18: the copy is an ordinary table again (CREATE TEMPORARY TABLES is a privilege the migration did not need before), with a random name, created without IF NOT EXISTS and dropped only when this run created it.
//                 The log tells a missing privilege from an index that does not fit the table.
// 20261118 CL/MMR SD-815 Codex round 19: the trial copy is this run's own only when its CREATE TABLE itself succeeded, read from mysqli directly (ensurePoolFilesMysqlExec()), in both mysqli reporting modes; db_modify() can report a failed statement as accepted.
// 20261118 CL/MMR SD-815 Codex round 20: the MySQL repair's LOCK TABLES, DELETE, CREATE UNIQUE INDEX and UNLOCK TABLES go through the same checked exec: no lock, no delete; UNLOCK only after an own lock.
//                 Added columns and the norm_amount index are reported only when they are read back; otherwise the run stops (db_modify() can report a failed statement as accepted).
// 20261118 CL/MMR SD-815 Codex round 21: the backfill flag is reported only when it is read back; a failed flag INSERT stops the run (inside a caller's PostgreSQL transaction through the savepoint rollback, so the caller can still commit its own work).
// 20261118 CL/MMR SD-815 Codex round 22: a unique index on only part of filename (a MySQL prefix like filename(7), a PostgreSQL expression such as left(filename, 7)) does not count and is removed once the full unique index is there, in the same locked step / transaction.
//                 Such an index refused a second, different file whose name shares the part.

if (!function_exists('ensurePoolFilesSchemaColumns')) {
	/**
	 * The pool_files columns added after the table's first schema, as in poolUploadEnsureSchema()
	 * (includes/docsIncludes/poolUpload.php). vendor_match last: _docPoolData.php and the REST attachment model probe
	 * that column before using the other vendor columns.
	 *
	 * @return array<string, string> column => type
	 */
	function ensurePoolFilesSchemaColumns(): array {
		return array(
			'invoice_number' => 'varchar(100)',
			'description' => 'text',
			'currency' => 'varchar(10)',
			'norm_amount' => 'numeric(15,3)',
			'manually_edited' => 'boolean NOT NULL DEFAULT false',
			'content_sha256' => 'char(64)',
			'source_sha256' => 'char(64)',
			'vendor_name' => 'text',
			'vendor_cvr' => 'varchar(20)',
			'vendor_iban' => 'varchar(40)',
			'vendor_konto_id' => 'integer',
			'vendor_score' => 'numeric(4,3)',
			'vendor_match' => 'varchar(10)',
		);
	}
}

if (!defined('ENSURE_POOL_FILES_BACKFILL_FLAG_QUERY')) {
	// The flag opdat_5.0.php sets after its norm_amount backfill; a regnskab that ran it is done.
	define('ENSURE_POOL_FILES_BACKFILL_FLAG_QUERY', "SELECT var_value FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled' AND var_grp = 'system'");
}

if (!class_exists('EnsurePoolFilesSchemaUnknown', false)) {
	/** A read ensurePoolFilesSchema() depends on failed; the run stops and the next login tries again. */
	class EnsurePoolFilesSchemaUnknown extends RuntimeException {}
}

if (!function_exists('ensurePoolFilesSelect')) {
	/**
	 * db_select() that tells a failed read from an empty one: on PostgreSQL it returns false on an error (logged by
	 * db_select()); on MySQL/MariaDB PHP 8's mysqli throws mysqli_sql_exception instead.
	 *
	 * @param string $sql
	 * @param int    $line Caller's line, for the log.
	 * @return mixed The result.
	 * @throws EnsurePoolFilesSchemaUnknown When the read failed.
	 */
	function ensurePoolFilesSelect(string $sql, int $line) {
		try {
			$result = db_select($sql, __FILE__ . " linje " . $line);
		} catch (mysqli_sql_exception $e) {
			error_log('ensurePoolFilesSchema: read failed at line ' . $line . ': ' . $e->getMessage());
			throw new EnsurePoolFilesSchemaUnknown('pool_files schema read failed at line ' . $line, 0, $e);
		}
		if ($result === false) {
			throw new EnsurePoolFilesSchemaUnknown('pool_files schema read failed at line ' . $line);
		}
		return $result;
	}
}

if (!function_exists('ensurePoolFilesInTransaction')) {
	/**
	 * Whether the tenant's PostgreSQL connection ($connection, as db_query_connection() uses) is inside a transaction,
	 * so the filename repair takes a savepoint instead of its own BEGIN/COMMIT.
	 *
	 * @return bool
	 */
	function ensurePoolFilesInTransaction(): bool {
		$connection = $GLOBALS['connection'] ?? null;
		return $connection instanceof \PgSql\Connection && pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE;
	}
}

if (!function_exists('ensurePoolFilesUniqueRepairPostgres')) {
	/**
	 * Gives pool_files a unique index on filename alone on PostgreSQL, all or nothing: deferrable constraints on
	 * filename are dropped, duplicate filenames collapsed to the newest row and the index created in one transaction
	 * (a savepoint when the caller is in one), which is kept only when ensurePoolFilesSchemaState() reads the index
	 * back. A failed statement (db_modify() only logs it for an update file) aborts the transaction, so the read-back
	 * fails too and everything is rolled back: no row is deleted and nothing is reported.
	 *
	 * @param string       $db_type
	 * @param list<string> $deferrable ensurePoolFilesSchemaState()['deferrableFilename'].
	 * @return void
	 * @throws EnsurePoolFilesSchemaUnknown When the repair was rolled back.
	 */
	function ensurePoolFilesUniqueRepairPostgres(string $db_type, array $deferrable, array $restrictive = array()): void {
		$nested = ensurePoolFilesInTransaction();
		db_modify($nested ? 'SAVEPOINT pool_files_unique' : 'BEGIN', __FILE__ . " linje " . __LINE__);
		$ok = false;
		try {
			foreach ($deferrable as $constraint) {
				db_modify('ALTER TABLE pool_files DROP CONSTRAINT "' . str_replace('"', '""', $constraint) . '"', __FILE__ . " linje " . __LINE__);
			}
			db_modify("DELETE FROM pool_files a USING pool_files b WHERE a.filename = b.filename AND a.id < b.id", __FILE__ . " linje " . __LINE__);
			// An immediate one may already exist next to a dropped deferrable one; then nothing more is needed. The usual
			// name may belong to an index that does not count (a partial one); then use another.
			if (!ensurePoolFilesSchemaState($db_type)['fullFilename']) {
				$taken = db_fetch_array(ensurePoolFilesSelect("SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE c.relname = 'pool_files_filename_uidx' AND n.nspname = current_schema()", __LINE__));
				$name = $taken ? 'pool_files_filename_full_uidx' : 'pool_files_filename_uidx';
				db_modify("CREATE UNIQUE INDEX $name ON pool_files (filename)", __FILE__ . " linje " . __LINE__);
			}
			// Only after the full index is in place: the indexes on part of filename go.
			foreach ($restrictive as $index) {
				db_modify('DROP INDEX "' . str_replace('"', '""', $index) . '"', __FILE__ . " linje " . __LINE__);
			}
			$ok = ensurePoolFilesSchemaState($db_type)['uniqueFilename'];
		} catch (EnsurePoolFilesSchemaUnknown $e) {
			$ok = false; // a read failed, also when an earlier statement aborted the transaction
		} finally {
			if ($ok) {
				db_modify($nested ? 'RELEASE SAVEPOINT pool_files_unique' : 'COMMIT', __FILE__ . " linje " . __LINE__);
			} else {
				db_modify($nested ? 'ROLLBACK TO SAVEPOINT pool_files_unique' : 'ROLLBACK', __FILE__ . " linje " . __LINE__);
				if ($nested) {
					db_modify('RELEASE SAVEPOINT pool_files_unique', __FILE__ . " linje " . __LINE__);
				}
			}
		}
		if (!$ok || !ensurePoolFilesSchemaState($db_type)['uniqueFilename']) {
			throw new EnsurePoolFilesSchemaUnknown('pool_files unique filename repair rolled back');
		}
	}
}

if (!function_exists('ensurePoolFilesMysqlInTransaction')) {
	/**
	 * Whether the MySQL/MariaDB connection is inside a transaction, where DDL or LOCK TABLES would commit the caller's
	 * work: @@in_transaction (MariaDB); else an InnoDB transaction of this connection (MySQL, needs PROCESS); else
	 * autocommit off. Null when none of them can be read.
	 *
	 * @return bool|null
	 */
	function ensurePoolFilesMysqlInTransaction(): ?bool {
		$checks = array(
			"SELECT @@in_transaction AS open",
			"SELECT count(*) AS open FROM information_schema.innodb_trx WHERE trx_mysql_thread_id = CONNECTION_ID()",
			"SELECT 1 - @@autocommit AS open",
		);
		foreach ($checks as $sql) {
			try {
				$q = db_select($sql, __FILE__ . " linje " . __LINE__);
			} catch (mysqli_sql_exception $e) {
				continue;
			}
			$r = ($q === false) ? false : db_fetch_array($q);
			if ($r !== false && $r !== null && isset($r['open'])) {
				return (int)$r['open'] > 0;
			}
		}
		return null;
	}
}

if (!function_exists('ensurePoolFilesMysqlIndexName')) {
	/**
	 * A name for the new unique filename index that no index on pool_files has yet (an existing index of that name,
	 * e.g. a non-unique one, would make CREATE fail after the duplicates were collapsed).
	 *
	 * @return string
	 * @throws EnsurePoolFilesSchemaUnknown When the names cannot be read or none is free.
	 */
	function ensurePoolFilesMysqlIndexName(): string {
		$taken = array();
		$q = ensurePoolFilesSelect("SELECT DISTINCT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files'", __LINE__);
		while ($r = db_fetch_array($q)) {
			$taken[strtolower((string)($r['index_name'] ?? $r['INDEX_NAME'] ?? ''))] = true;
		}
		foreach (array('pool_files_filename_uidx', 'pool_files_filename_full_uidx', 'pool_files_filename_uidx2', 'pool_files_filename_uidx3') as $name) {
			if (!isset($taken[$name])) {
				return $name;
			}
		}
		throw new EnsurePoolFilesSchemaUnknown('no free name for the pool_files unique filename index');
	}
}

if (!function_exists('ensurePoolFilesMysqlExec')) {
	/**
	 * Runs one statement on the tenant's mysqli connection ($connection, as db_query_connection() uses) and says whether
	 * it really succeeded, in both mysqli reporting modes: db_modify() logs a failed statement from an update file and
	 * can still report it as accepted. Null when there is no mysqli connection to ask.
	 *
	 * @param string $sql A fixed statement (no outside values).
	 * @return bool|null A thrown mysqli_sql_exception is returned as false (and logged), never rethrown.
	 */
	function ensurePoolFilesMysqlExec(string $sql): ?bool {
		$GLOBALS['ensurePoolFilesMysqlLastError'] = null;
		$connection = $GLOBALS['connection'] ?? null;
		if (!($connection instanceof \mysqli)) {
			return null;
		}
		try {
			$ok = mysqli_query($connection, $sql) !== false;
			$error = $ok ? '' : mysqli_error($connection);
			$code = $ok ? 0 : mysqli_errno($connection);
		} catch (mysqli_sql_exception $e) {
			$ok = false;
			$error = $e->getMessage();
			$code = (int)$e->getCode();
		}
		if (!$ok) {
			$GLOBALS['ensurePoolFilesMysqlLastError'] = array('code' => $code, 'message' => $error);
			error_log('ensurePoolFilesSchema: ' . $sql . ' failed: ' . $error);
		}
		return $ok;
	}
}

if (!function_exists('ensurePoolFilesMysqlProbeName')) {
	/**
	 * A fresh random name for the trial copy of pool_files.
	 *
	 * @return string
	 */
	function ensurePoolFilesMysqlProbeName(): string {
		return 'pool_files_uidx_probe_' . bin2hex(random_bytes(6));
	}
}

if (!function_exists('ensurePoolFilesMysqlProbeReason')) {
	/**
	 * What a failed probe statement means, for the log: a missing privilege, or an index the table cannot hold.
	 *
	 * @param int    $code    mysqli error number.
	 * @param string $message mysqli error text.
	 * @return string
	 */
	function ensurePoolFilesMysqlProbeReason(int $code, string $message): string {
		if (in_array($code, array(1044, 1045, 1142, 1143, 1227), true)) {
			return 'the database user lacks a privilege the repair needs (CREATE, INDEX and DROP on the tenant database): ' . $message;
		}
		if (in_array($code, array(1071, 1709), true)) {
			return 'pool_files cannot hold a unique index on the whole filename (e.g. ALTER TABLE pool_files ROW_FORMAT=DYNAMIC is needed first): ' . $message;
		}
		return 'the trial failed: ' . $message;
	}
}

if (!function_exists('ensurePoolFilesMysqlProbeUniqueIndex')) {
	/**
	 * Tries the unique filename index on an empty copy of pool_files (CREATE TABLE ... LIKE keeps the row format, the
	 * character set and the column definitions), so a table that cannot hold it - e.g. an old ROW_FORMAT=COMPACT table
	 * in utf8mb4, where varchar(255) needs 1020 bytes and COMPACT allows 767 - is found before any duplicate is deleted.
	 * The copy is an ordinary table (the privileges the repair already needs: CREATE, INDEX, DROP) with a random name,
	 * created without IF NOT EXISTS through ensurePoolFilesMysqlExec(), so it counts as this run's own only when that
	 * CREATE itself succeeded; it is dropped only then, and no other table is ever dropped.
	 *
	 * @return void
	 * @throws EnsurePoolFilesSchemaUnknown When the index cannot be tried or does not fit; the reason is logged.
	 */
	function ensurePoolFilesMysqlProbeUniqueIndex(): void {
		$probe = ensurePoolFilesMysqlProbeName();
		$stop = static function (string $why): void {
			error_log('ensurePoolFilesSchema: nothing deleted, ' . $why);
			throw new EnsurePoolFilesSchemaUnknown('pool_files unique filename index cannot be tried or does not fit');
		};
		$failed = static function () use ($stop): void {
			$last = $GLOBALS['ensurePoolFilesMysqlLastError'] ?? array('code' => 0, 'message' => '');
			$stop(ensurePoolFilesMysqlProbeReason((int)$last['code'], (string)$last['message']));
		};
		// The copy is this run's own only when its own CREATE TABLE succeeded (no IF NOT EXISTS: an existing table of
		// that name, whoever made it in between, makes it fail). Nothing else is ever dropped.
		$created = ensurePoolFilesMysqlExec("CREATE TABLE $probe LIKE pool_files");
		if ($created === null) {
			$stop('the result of the trial copy cannot be checked on this connection');
		}
		if (!$created) {
			$failed();
		}
		try {
			if (!ensurePoolFilesMysqlExec("CREATE UNIQUE INDEX {$probe}_filename ON $probe (filename)")) {
				$failed();
			}
		} finally {
			ensurePoolFilesMysqlExec("DROP TABLE $probe"); // this run's own table; a failure is logged
		}
	}
}

if (!function_exists('ensurePoolFilesUniqueRepairMysql')) {
	/**
	 * Gives pool_files a unique index on filename on MySQL/MariaDB. DDL commits at once there, so the duplicate collapse
	 * and the index cannot share a transaction; instead both run under LOCK TABLES pool_files WRITE (with the aliases the
	 * self-join needs). If the table lock cannot be had (another session uses the table past lock_wait_timeout), the run
	 * stops before anything is deleted, in both mysqli reporting modes (ensurePoolFilesMysqlExec()); once it is held no
	 * other session can use the table, so the index cannot be blocked. The tables are unlocked after an own lock, and the
	 * index is reported only when it is read back.
	 *
	 * @param string $db_type
	 * @return void
	 * @throws EnsurePoolFilesSchemaUnknown When the index is not there afterwards.
	 */
	function ensurePoolFilesUniqueRepairMysql(string $db_type): void {
		$before = ensurePoolFilesSchemaState($db_type);
		$name = ensurePoolFilesMysqlIndexName(); // before anything is deleted
		ensurePoolFilesMysqlProbeUniqueIndex();   // likewise: the table must be able to hold the index
		// Every statement's own result is checked (ensurePoolFilesMysqlExec()): db_modify() can report a failed one as
		// accepted when mysqli does not throw, and nothing may be deleted without the table lock.
		$locked = ensurePoolFilesMysqlExec("LOCK TABLES pool_files WRITE, pool_files AS a WRITE, pool_files AS b READ");
		if ($locked !== true) {
			error_log('ensurePoolFilesSchema: nothing deleted, pool_files could not be locked for the unique filename repair');
			throw new EnsurePoolFilesSchemaUnknown('pool_files table lock not acquired');
		}
		try {
			if (!ensurePoolFilesMysqlExec("DELETE a FROM pool_files a JOIN pool_files b ON a.filename = b.filename AND a.id < b.id")) {
				throw new EnsurePoolFilesSchemaUnknown('pool_files duplicate collapse failed');
			}
			if (empty($before['fullFilename']) && !ensurePoolFilesMysqlExec("CREATE UNIQUE INDEX $name ON pool_files (filename)")) {
				throw new EnsurePoolFilesSchemaUnknown('pool_files unique filename index not created');
			}
			// Only after the full index is in place: the prefix indexes on filename go.
			foreach ($before['restrictiveFilename'] ?? array() as $index) {
				if (!ensurePoolFilesMysqlExec('ALTER TABLE pool_files DROP INDEX `' . str_replace('`', '``', $index) . '`')) {
					throw new EnsurePoolFilesSchemaUnknown('pool_files prefix index on filename not dropped');
				}
			}
		} finally {
			ensurePoolFilesMysqlExec("UNLOCK TABLES"); // this run's own lock; a failure is logged and the lock ends with the connection
		}
		if (!ensurePoolFilesSchemaState($db_type)['uniqueFilename']) {
			throw new EnsurePoolFilesSchemaUnknown('pool_files unique filename index missing after CREATE');
		}
	}
}

if (!function_exists('ensurePoolFilesBackfillValue')) {
	/**
	 * The norm_amount a row's amount text gives, as SQL, or null when there is none to store: not parseable
	 * (normalizePoolAmount()), or outside what numeric(15,3) holds (|value| < 10^12), so it can never fail the update.
	 *
	 * @param mixed $amount pool_files.amount.
	 * @return string|null
	 */
	function ensurePoolFilesBackfillValue($amount): ?string {
		include_once(__DIR__ . '/../docsIncludes/poolAmountNormalizer.php');
		$value = normalizePoolAmount($amount);
		if ($value === null || !is_finite((float)$value) || abs((float)$value) >= 1000000000000) {
			return null;
		}
		return sprintf('%.3F', (float)$value);
	}
}

if (!function_exists('ensurePoolFilesBackfillPending')) {
	/**
	 * Rows whose norm_amount is still empty although their amount text gives one: id => the md5 of the amount text,
	 * computed by the database (32 hex digits, so it is safe in SQL whatever the text holds), and the SQL value.
	 *
	 * @return array<int, array{amountMd5: string, value: string}>
	 * @throws EnsurePoolFilesSchemaUnknown When the read failed (never mistaken for «nothing left»).
	 */
	function ensurePoolFilesBackfillPending(): array {
		$pending = array();
		$q = ensurePoolFilesSelect("SELECT id, amount, md5(amount) AS amount_md5 FROM pool_files WHERE norm_amount IS NULL AND amount IS NOT NULL AND amount != ''", __LINE__);
		while ($r = db_fetch_array($q)) {
			$value = ensurePoolFilesBackfillValue($r['amount']);
			$md5 = strtolower((string)($r['amount_md5'] ?? ''));
			if ($value !== null && preg_match('/^[0-9a-f]{32}$/', $md5)) {
				$pending[(int)$r['id']] = array('amountMd5' => $md5, 'value' => $value);
			}
		}
		return $pending;
	}
}

if (!function_exists('ensurePoolFilesSchemaState')) {
	/**
	 * What pool_files is missing in the current database: columns, the unique index on filename alone, the
	 * norm_amount index, the norm_amount backfill. Null when there is no pool_files table.
	 *
	 * @param string $db_type
	 * @return array{columns: list<string>, uniqueFilename: bool, fullFilename: bool, deferrableFilename: list<string>, restrictiveFilename: list<string>, normAmountIndex: bool, normAmountBackfilled: bool}|null
	 * @throws EnsurePoolFilesSchemaUnknown When a read failed.
	 */
	function ensurePoolFilesSchemaState(string $db_type): ?array {
		$mysql = in_array($db_type, array('mysql', 'mysqli'), true);
		$schema = $mysql ? 'DATABASE()' : 'current_schema()';
		$present = array();
		$q = ensurePoolFilesSelect("SELECT column_name FROM information_schema.columns WHERE table_schema = $schema AND table_name = 'pool_files'", __LINE__);
		while ($r = db_fetch_array($q)) {
			$present[strtolower((string)($r['column_name'] ?? $r['COLUMN_NAME'] ?? ''))] = true;
		}
		if (!$present) {
			return null;
		}
		$missing = array();
		foreach (array_keys(ensurePoolFilesSchemaColumns()) as $column) {
			if (!isset($present[$column])) {
				$missing[] = $column;
			}
		}
		if ($mysql) {
			// Exactly one column in the index (a composite UNIQUE(filename, x) still allows duplicate filenames), on the
			// whole column: a prefix index (sub_part set, e.g. filename(7)) refuses different names that share the prefix.
			$unique = "SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files' AND non_unique = 0 GROUP BY index_name HAVING COUNT(*) = 1 AND MAX(column_name) = 'filename' AND MAX(sub_part) IS NULL";
			$restrictive = "SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files' AND non_unique = 0 GROUP BY index_name HAVING COUNT(*) = 1 AND MAX(column_name) = 'filename' AND MAX(sub_part) IS NOT NULL";
			$normIndex = "SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files' AND column_name = 'norm_amount' AND seq_in_index = 1";
		} else {
			// Any single-column unique index on filename counts, whatever its name (a table created by
			// poolUploadEnsureSchema() has the constraint pool_files_filename_key), as in opdat_5.0.php - but only one
			// ON CONFLICT (filename) can use: valid, no WHERE predicate, no expression, not behind a deferrable constraint.
			$unique = "SELECT i.indexrelid FROM pg_index i JOIN pg_class t ON t.oid = i.indrelid JOIN pg_namespace n ON n.oid = t.relnamespace
				WHERE t.relname = 'pool_files' AND n.nspname = current_schema() AND i.indisunique AND i.indisvalid AND i.indnatts = 1
				AND i.indpred IS NULL AND i.indexprs IS NULL
				AND i.indkey[0] = (SELECT attnum FROM pg_attribute WHERE attrelid = t.oid AND attname = 'filename')
				AND NOT EXISTS (SELECT 1 FROM pg_constraint c WHERE c.conindid = i.indexrelid AND c.condeferrable)";
			// A deferrable unique or exclusion constraint on filename alone: ON CONFLICT (filename) infers it and then
			// refuses the insert, even when an immediate index exists too.
			$deferrable = "SELECT c.conname FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid JOIN pg_namespace n ON n.oid = t.relnamespace
				WHERE t.relname = 'pool_files' AND n.nspname = current_schema() AND c.contype IN ('u', 'x') AND c.condeferrable
				AND array_length(c.conkey, 1) = 1
				AND c.conkey[1] = (SELECT attnum FROM pg_attribute WHERE attrelid = t.oid AND attname = 'filename')";
			// A unique index on an expression of filename (left(filename, 7), lower(filename) ...) refuses names the full
			// index allows; it is not an index ON CONFLICT (filename) can use either.
			$restrictive = "SELECT ci.relname AS index_name FROM pg_index i JOIN pg_class t ON t.oid = i.indrelid JOIN pg_class ci ON ci.oid = i.indexrelid
				JOIN pg_namespace n ON n.oid = t.relnamespace
				WHERE t.relname = 'pool_files' AND n.nspname = current_schema() AND i.indisunique AND i.indexprs IS NOT NULL
				AND pg_get_indexdef(i.indexrelid) ~* '\\mfilename\\M'";
			$normIndex = "SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'pool_files' AND indexname = 'idx_pool_files_norm_amount'";
		}
		$deferrableNames = array();
		if (!$mysql) {
			$q = ensurePoolFilesSelect($deferrable, __LINE__);
			while ($r = db_fetch_array($q)) {
				$deferrableNames[] = (string)$r['conname'];
			}
		}
		$restrictiveNames = array();
		$q = ensurePoolFilesSelect($restrictive, __LINE__);
		while ($r = db_fetch_array($q)) {
			$restrictiveNames[] = (string)($r['index_name'] ?? $r['INDEX_NAME'] ?? '');
		}
		$full = (bool)db_fetch_array(ensurePoolFilesSelect($unique, __LINE__));
		return array(
			'columns' => $missing,
			'uniqueFilename' => $full && !$deferrableNames && !$restrictiveNames,
			'fullFilename' => $full,
			'deferrableFilename' => $deferrableNames,
			'restrictiveFilename' => $restrictiveNames,
			'normAmountIndex' => in_array('norm_amount', $missing, true) ? false : (bool)db_fetch_array(ensurePoolFilesSelect($normIndex, __LINE__)),
			'normAmountBackfilled' => (bool)db_fetch_array(ensurePoolFilesSelect(ENSURE_POOL_FILES_BACKFILL_FLAG_QUERY, __LINE__)),
		);
	}
}

if (!function_exists('ensurePoolFilesSchema')) {
	/**
	 * Adds what pool_files is missing (see ensurePoolFilesSchemaState()). Cheap when nothing is missing: one column
	 * query, three index/constraint lookups and one settings lookup per login. Otherwise the work runs under a per-database lock
	 * and is checked again inside it, so two logins at once do it once. Duplicate filenames are collapsed to the newest row (highest id)
	 * before the unique index is added, as opdat_5.0.php does, so the index cannot fail on old data. Rows without
	 * norm_amount get it from their amount text (normalizePoolAmount()), as opdat_5.0.php's backfill does; its flag is
	 * written only after every row is done, so a run that stops halfway is finished by the next login.
	 *
	 * @param string $db_type
	 * @return list<string> What was done (column names, 'unique_filename', 'norm_amount_index', 'norm_amount_backfill');
	 *   empty when nothing.
	 */
	function ensurePoolFilesSchema(string $db_type): array {
		$done = array();
		// Inside a caller's PostgreSQL transaction a failed statement would abort the caller's transaction, so the run
		// gets a savepoint of its own and rolls back to it on any failure (the caller can go on), and it takes a
		// transaction-level advisory lock, which ends with the caller's transaction and never outlives it.
		$nested = !in_array($db_type, array('mysql', 'mysqli'), true) && ensurePoolFilesInTransaction();
		if ($nested) {
			db_modify('SAVEPOINT pool_files_schema', __FILE__ . " linje " . __LINE__);
		}
		$completed = false;
		try {
			$result = ensurePoolFilesSchemaRun($db_type, $done, $nested);
			$completed = true;
			return $result;
		} catch (EnsurePoolFilesSchemaUnknown $e) {
			// A read failed: no flag, nothing more this time; the next login tries again.
			return $nested ? array() : $done;
		} catch (mysqli_sql_exception $e) {
			// A write that failed on MySQL/MariaDB (mysqli throws). The flag is only written after a verified
			// backfill, so stopping here leaves it unset; the next login tries again.
			error_log('ensurePoolFilesSchema: write failed: ' . $e->getMessage());
			return $done;
		} finally {
			if ($nested) {
				if (!$completed) {
					db_modify('ROLLBACK TO SAVEPOINT pool_files_schema', __FILE__ . " linje " . __LINE__);
				}
				db_modify('RELEASE SAVEPOINT pool_files_schema', __FILE__ . " linje " . __LINE__);
			}
		}
	}
}

if (!function_exists('ensurePoolFilesSchemaRun')) {
	/**
	 * ensurePoolFilesSchema()'s work; $done collects what was done so far, also when a read fails halfway.
	 *
	 * @param string       $db_type
	 * @param list<string> $done
	 * @param bool         $nested  Inside a caller's PostgreSQL transaction (ensurePoolFilesSchema() holds a savepoint).
	 * @return list<string>
	 * @throws EnsurePoolFilesSchemaUnknown When a read failed.
	 */
	function ensurePoolFilesSchemaRun(string $db_type, array &$done, bool $nested = false): array {
		$state = ensurePoolFilesSchemaState($db_type);
		if ($state === null || (!$state['columns'] && $state['uniqueFilename'] && $state['normAmountIndex'] && $state['normAmountBackfilled'])) {
			return array();
		}
		$mysql = in_array($db_type, array('mysql', 'mysqli'), true);
		if ($mysql) {
			// MySQL/MariaDB commit an open transaction on DDL and LOCK TABLES, so nothing here may run inside one: the
			// caller's uncommitted work would be committed. A login outside a transaction does it instead.
			$open = ensurePoolFilesMysqlInTransaction();
			if ($open !== false) {
				error_log('ensurePoolFilesSchema: skipped, ' . ($open === null ? 'transaction state unknown' : 'connection is inside a transaction'));
				return array();
			}
			$lockName = "CONCAT('saldi:pool_files_schema:', MD5(DATABASE()))";
			$locked = db_fetch_array(ensurePoolFilesSelect("SELECT GET_LOCK($lockName, 30) AS acquired", __LINE__));
			if ((int)($locked['acquired'] ?? 0) !== 1) {
				return array(); // another login holds it; the next login checks again
			}
			$unlock = "SELECT RELEASE_LOCK($lockName)";
		} elseif ($nested) {
			// Ends with the caller's transaction (and with the savepoint when that is rolled back): nothing to unlock.
			ensurePoolFilesSelect("SELECT pg_advisory_xact_lock(hashtext('saldi:pool_files_schema'))", __LINE__);
			$unlock = null;
		} else {
			ensurePoolFilesSelect("SELECT pg_advisory_lock(hashtext('saldi:pool_files_schema'))", __LINE__);
			$unlock = "SELECT pg_advisory_unlock(hashtext('saldi:pool_files_schema'))";
		}
		try {
			$state = ensurePoolFilesSchemaState($db_type);
			if ($state === null) {
				return array();
			}
			$types = ensurePoolFilesSchemaColumns();
			foreach ($state['columns'] as $column) {
				$ifNotExists = $mysql ? '' : 'IF NOT EXISTS ';
				db_modify("ALTER TABLE pool_files ADD COLUMN $ifNotExists$column " . $types[$column], __FILE__ . " linje " . __LINE__);
			}
			if ($state['columns']) {
				// Read back: db_modify() can report a failed ALTER as accepted. Report only what is there; stop if not all.
				$stillMissing = ensurePoolFilesSchemaState($db_type)['columns'] ?? $state['columns'];
				foreach ($state['columns'] as $column) {
					if (!in_array($column, $stillMissing, true)) {
						$done[] = $column;
					}
				}
				if ($stillMissing) {
					throw new EnsurePoolFilesSchemaUnknown('pool_files columns still missing: ' . implode(', ', $stillMissing));
				}
			}
			if (!$state['uniqueFilename']) {
				if ($mysql) {
					ensurePoolFilesUniqueRepairMysql($db_type);
				} else {
					ensurePoolFilesUniqueRepairPostgres($db_type, $state['deferrableFilename'], $state['restrictiveFilename']);
				}
				$done[] = 'unique_filename';
			}
			if (!$state['normAmountIndex']) {
				$ifNotExists = $mysql ? '' : 'IF NOT EXISTS ';
				db_modify("CREATE INDEX {$ifNotExists}idx_pool_files_norm_amount ON pool_files (norm_amount)", __FILE__ . " linje " . __LINE__);
				if (!ensurePoolFilesSchemaState($db_type)['normAmountIndex']) {
					throw new EnsurePoolFilesSchemaUnknown('pool_files norm_amount index not created');
				}
				$done[] = 'norm_amount_index';
			}
			if (!$state['normAmountBackfilled']) {
				foreach (ensurePoolFilesBackfillPending() as $id => $row) {
					// Only while the row is as it was read: a pool save (poolMetadata.php writes amount and norm_amount
					// together) that commits while this waits for the row lock is re-checked by the WHERE and kept.
					// The text is matched by its md5 and never written into the SQL.
					db_modify("UPDATE pool_files SET norm_amount = " . $row['value'] . " WHERE id = " . (int)$id
						. " AND norm_amount IS NULL AND md5(amount) = '" . $row['amountMd5'] . "'", __FILE__ . " linje " . __LINE__);
				}
				// db_modify() reports a failed write from an update file as accepted, so read back: the flag only when no
				// row is left to fill. Otherwise no flag, and the next login tries the rest again.
				if (ensurePoolFilesBackfillPending()) {
					return $done;
				}
				// (A failed pending read throws EnsurePoolFilesSchemaUnknown, so reaching here means: really nothing left.)
				db_modify("INSERT INTO settings (var_name, var_grp, var_value, var_description, user_id) VALUES ('pool_files_norm_amount_backfilled', 'system', 'yes', 'One-time backfill of pool_files.norm_amount from the legacy amount text column', 0)", __FILE__ . " linje " . __LINE__);
				// Read back: db_modify() can report a failed INSERT as accepted. On PostgreSQL a failed INSERT also aborts
				// the transaction, so this read fails and the caller's savepoint is rolled back (ensurePoolFilesSchema()).
				if (!db_fetch_array(ensurePoolFilesSelect(ENSURE_POOL_FILES_BACKFILL_FLAG_QUERY, __LINE__))) {
					throw new EnsurePoolFilesSchemaUnknown('pool_files norm_amount backfill flag not written');
				}
				$done[] = 'norm_amount_backfill';
			}
		} finally {
			if ($unlock !== null) {
				try {
					db_select($unlock, __FILE__ . " linje " . __LINE__);
				} catch (mysqli_sql_exception $e) {
					error_log('ensurePoolFilesSchema: unlock failed: ' . $e->getMessage()); // the lock ends with the connection
				}
			}
		}
		return $done;
	}
}
