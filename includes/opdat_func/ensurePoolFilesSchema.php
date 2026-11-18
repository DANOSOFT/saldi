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
// 20261118 CL/MMR SD-815 Codex round 23: on PostgreSQL only an index whose single key expression uses filename and no other column, without a predicate or included columns, counts as such; any other unique index (e.g. on lower(subject) WHERE filename IS NOT NULL) is never dropped.
// 20261118 CL/MMR SD-815 Codex round 24: «uses filename and no other column» is read from the catalogue's column dependencies (pg_depend), not from the expression's text, so a literal 'filename' or an odd column name cannot mislead it.
// 20261118 CL/MMR SD-815 Codex round 25: on PostgreSQL only an expression that is exactly a prefix of filename (left(filename, N), substr(filename, 1, N), SUBSTRING(filename FROM 1 FOR N)) is dropped; any other unique expression index on filename is kept and named in the log for review.
// 20261118 CL/MMR SD-815 Codex round 26: and only when the index depends on nothing but pool_files' own columns: a tenant's own function (or type, operator, collation) of the same name records a dependency, the built-in ones do not; pg_catalog.-qualified names are recognised too.
// 20261118 CL/MMR SD-815 Codex round 27: and only when the prefix is shorter than filename's declared width (varchar(255): left(filename, 255) covers every name and is a plain lookup index, kept).
// 20261118 CL/MMR SD-815 Codex round 28: the width is read through a domain to its base type (only varchar/char with a length, or unbounded text/varchar, are understood; anything else is kept), and the prefix must be at least 1 (left(filename, 0) is a one-row rule).
// 20261118 CL/MMR SD-815 Codex round 29: on PostgreSQL no expression index is dropped any more. Whether one is an obsolete prefix depends on more than the catalogue can say for sure (CHECK constraints, domains, the function, the width), so a unique expression index on filename alone is kept and named in the log for review whenever the repair runs; the full unique index is still added. The MySQL/MariaDB prefix repair (sub_part) is unchanged.
// 20261118 CL/MMR SD-815 Codex round 30: a unique filename key with INCLUDE columns counts (indnkeyatts, not indnatts), and the new index's name is chosen from the free ones before anything is created or deleted.
// 20261118 CL/MMR SD-815 Codex round 31: the name search goes on past the four usual names (…_uidx4 … _uidx99, then a random suffix), on both databases, and every stop of the run is logged with its reason.
// 20261118 CL/MMR SD-815 Codex round 32: on MySQL/MariaDB the index name and the filename index state are read under LOCK TABLES (information_schema only), right before the duplicate collapse, so another connection's index of that name cannot make CREATE fail after rows were deleted.
// 20261118 CL/MMR SD-815 Codex round 33: the trial copy's definition (engine, row format, filename's type, character set, collation) is kept, and under the lock pool_files must still have that definition before anything is deleted; otherwise the run stops with both rows intact.
// 20261118 CL/MMR SD-815 Codex round 34: that definition also covers every index of the table (name, columns, prefix, uniqueness), read on the copy before the trial index, so an index another session adds between the trial and the lock (e.g. taking MariaDB's last of 64 key slots) stops the run before anything is deleted.
// 20261118 CL/MMR SD-815 Codex round 35: the definition is now the table's whole SHOW CREATE TABLE (AUTO_INCREMENT left out), so partitioning and every other option count too: taken before the trial copy, the copy must match it, and under the lock pool_files must still match it before anything is deleted.
// 20261118 CL/MMR SD-815 Codex round 36: the trial copy is compared without FOREIGN KEY lines (CREATE TABLE ... LIKE does not copy them), pool_files before the trial and under the lock still in full; only the name in the CREATE TABLE header is normalised, quoted or not (sql_quote_show_create=0).
// 20261118 CL/MMR SD-815 Codex round 37: when other tables refer to pool_files with foreign keys and there are duplicate filenames, nothing is deleted (a cascade would delete their rows too); the constraints are named in the log for a person. Checked under the lock that keeps the decision valid.
// 20261118 CL/MMR SD-815 Codex round 38: on MySQL/MariaDB the references are read from InnoDB's own list (INNODB_SYS_FOREIGN, MySQL 8: INNODB_FOREIGN), which also shows other databases' tables; when it cannot be read (no PROCESS privilege) and there are duplicates, nothing is deleted. Duplicates are looked for first.
// 20261118 CL/MMR SD-815 Codex round 39: NULL filenames are not duplicates (the collapse never deletes them); InnoDB's REF_NAME is compared with this database's name in MySQL's filename encoding (a '-' is @002d); only a name with non-ASCII characters, whose encoding is not rebuilt here, counts every encoded reference as possibly its own.
// 20261118 CL/MMR SD-815 Codex round 40: the database name and REF_NAME are read as HEX() and decoded in PHP, so the session's character_set_results cannot turn a non-ASCII name into '?'; the session settings are not touched.
// 20261118 CL/MMR SD-815 Codex round 41: the CREATE TABLE header's name is also recognised in double quotes (sql_mode ANSI_QUOTES); the session's sql_mode is not touched.

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

if (!function_exists('ensurePoolFilesFreeIndexName')) {
	/**
	 * The first name for the new unique filename index that is not in $taken: pool_files_filename_uidx, _full_uidx,
	 * _uidx2 … _uidx99, then pool_files_filename_uidx_<random hex>.
	 *
	 * @param array<string, true> $taken Lower-case names already used.
	 * @return string
	 */
	function ensurePoolFilesFreeIndexName(array $taken): string {
		$names = array('pool_files_filename_uidx', 'pool_files_filename_full_uidx');
		for ($i = 2; $i <= 99; $i++) {
			$names[] = 'pool_files_filename_uidx' . $i;
		}
		foreach ($names as $name) {
			if (!isset($taken[$name])) {
				return $name;
			}
		}
		do {
			$name = 'pool_files_filename_uidx_' . bin2hex(random_bytes(6));
		} while (isset($taken[$name]));
		return $name;
	}
}

if (!function_exists('ensurePoolFilesPostgresIndexName')) {
	/**
	 * A name for the new unique filename index that no relation in the current schema has yet (index names share the
	 * namespace with tables), from ensurePoolFilesFreeIndexName().
	 *
	 * @return string
	 * @throws EnsurePoolFilesSchemaUnknown When the names cannot be read.
	 */
	function ensurePoolFilesPostgresIndexName(): string {
		$taken = array();
		$q = ensurePoolFilesSelect("SELECT c.relname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = current_schema()
			AND c.relname LIKE 'pool\\_files\\_filename%'", __LINE__);
		while ($r = db_fetch_array($q)) {
			$taken[strtolower((string)$r['relname'])] = true;
		}
		return ensurePoolFilesFreeIndexName($taken);
	}
}

if (!function_exists('ensurePoolFilesMysqlRows')) {
	/**
	 * The rows of a read on the tenant's mysqli connection, or null when it fails - quietly, without db_select()'s error
	 * box and mail, for reads that may be refused (a privilege) or not exist on this server (a table of another version).
	 *
	 * @param string $sql A fixed statement.
	 * @return list<array<string, mixed>>|null
	 */
	function ensurePoolFilesMysqlRows(string $sql): ?array {
		$connection = $GLOBALS['connection'] ?? null;
		if (!($connection instanceof \mysqli)) {
			return null;
		}
		try {
			$result = mysqli_query($connection, $sql);
		} catch (mysqli_sql_exception $e) {
			return null;
		}
		if (!($result instanceof \mysqli_result)) {
			return null;
		}
		$rows = array();
		while ($row = mysqli_fetch_assoc($result)) {
			$rows[] = $row;
		}
		return $rows;
	}
}

if (!function_exists('ensurePoolFilesMysqlEncodedName')) {
	/**
	 * A database name as InnoDB writes it in REF_NAME (MySQL's filename encoding): letters, digits and '_' as they
	 * are, any other ASCII character as @ and four lower-case hex digits ('-' → @002d). Null for a name with non-ASCII
	 * characters, whose encoding (a table of its own in MySQL) is not rebuilt here.
	 *
	 * @param string $name
	 * @return string|null
	 */
	function ensurePoolFilesMysqlEncodedName(string $name): ?string {
		if (preg_match('/[^\\x00-\\x7f]/', $name)) {
			return null;
		}
		return (string)preg_replace_callback('/[^A-Za-z0-9_]/', static function (array $m): string {
			return '@' . sprintf('%04x', ord($m[0]));
		}, $name);
	}
}

if (!function_exists('ensurePoolFilesGuardInboundReferences')) {
	/**
	 * Stops the duplicate collapse when there is something to delete and other tables may refer to pool_files with
	 * foreign keys: a cascade would delete their rows as well (SET NULL would change them, RESTRICT would refuse), so a
	 * person has to merge those duplicates. Called while the lock that keeps the answer valid is held (PostgreSQL:
	 * SHARE ROW EXCLUSIVE on pool_files in the repair's transaction; MySQL: LOCK TABLES pool_files WRITE).
	 * PostgreSQL: pg_constraint (all schemas). MySQL/MariaDB: InnoDB's own foreign-key list, which also shows tables in
	 * other databases (information_schema.referential_constraints only shows what this user may see); it needs the
	 * PROCESS privilege, and when it cannot be read the collapse waits.
	 *
	 * @param string $db_type
	 * @return void
	 * @throws EnsurePoolFilesSchemaUnknown When duplicates exist and references exist or cannot be ruled out; logged.
	 */
	function ensurePoolFilesGuardInboundReferences(string $db_type): void {
		// The same rows the collapse deletes: equal, non-NULL filenames (two NULLs are never equal there, nor in the index).
		if (!db_fetch_array(ensurePoolFilesSelect("SELECT filename FROM pool_files WHERE filename IS NOT NULL GROUP BY filename HAVING COUNT(*) > 1 LIMIT 1", __LINE__))) {
			return; // nothing to delete, nothing can cascade
		}
		$names = array();
		if (in_array($db_type, array('mysql', 'mysqli'), true)) {
			$rows = null;
			foreach (array('INNODB_SYS_FOREIGN', 'INNODB_FOREIGN') as $list) {
				// REF_NAME is <database>/<table> in MySQL's filename encoding (a '-' is stored as @002d), so it is matched in
				// PHP: the table part must be pool_files and the database part this database's name encoded the same way
				// (a non-ASCII name: any pool_files counts). HEX(): the names' bytes as they are, whatever
				// character_set_results converts text results to.
				$rows = ensurePoolFilesMysqlRows("SELECT ID AS id, FOR_NAME AS child, HEX(REF_NAME) AS parent_hex, HEX(DATABASE()) AS here_hex FROM information_schema.$list WHERE REF_NAME LIKE '%pool%files'");
				if ($rows !== null) {
					break;
				}
			}
			if ($rows !== null) {
				$rows = array_values(array_filter($rows, static function (array $r): bool {
					$parent = hex2bin((string)($r['parent_hex'] ?? ''));
					$hereName = hex2bin((string)($r['here_hex'] ?? ''));
					if ($parent === false || $hereName === false) {
						return true; // cannot be read back: may be ours
					}
					$parts = explode('/', $parent, 2);
					if (count($parts) !== 2 || strtolower($parts[1]) !== 'pool_files') {
						return false;
					}
					$here = ensurePoolFilesMysqlEncodedName($hereName);
					// null: a non-ASCII name, whose encoding is not rebuilt here; then any reference to a pool_files may be ours.
					return $here === null ? true : strtolower($parts[0]) === strtolower($here);
				}));
			}
			if ($rows === null) {
				error_log('ensurePoolFilesSchema: nothing deleted, pool_files has duplicate filenames and the foreign keys that may refer to it cannot be read'
					. ' (information_schema.INNODB_SYS_FOREIGN / INNODB_FOREIGN need the PROCESS privilege); grant it or merge the duplicates by hand');
				throw new EnsurePoolFilesSchemaUnknown('references to pool_files cannot be ruled out');
			}
			foreach ($rows as $r) {
				$names[] = (string)($r['child'] ?? '') . ' (' . (string)($r['id'] ?? '') . ')';
			}
		} else {
			$q = ensurePoolFilesSelect("SELECT cn.nspname || '.' || ct.relname || '.' || c.conname AS reference FROM pg_constraint c
				JOIN pg_class t ON t.oid = c.confrelid JOIN pg_namespace n ON n.oid = t.relnamespace
				JOIN pg_class ct ON ct.oid = c.conrelid JOIN pg_namespace cn ON cn.oid = ct.relnamespace
				WHERE c.contype = 'f' AND t.relname = 'pool_files' AND n.nspname = current_schema()", __LINE__);
			while ($r = db_fetch_array($q)) {
				$names[] = (string)($r['reference'] ?? '');
			}
		}
		if (!$names) {
			return;
		}
		error_log('ensurePoolFilesSchema: nothing deleted, pool_files has duplicate filenames and other tables refer to it (' . implode(', ', $names)
			. '); merge the duplicates by hand, then the next login adds the unique filename index');
		throw new EnsurePoolFilesSchemaUnknown('duplicate pool_files rows are referenced by other tables');
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
	function ensurePoolFilesUniqueRepairPostgres(string $db_type, array $deferrable): void {
		$nested = ensurePoolFilesInTransaction();
		db_modify($nested ? 'SAVEPOINT pool_files_unique' : 'BEGIN', __FILE__ . " linje " . __LINE__);
		$ok = false;
		try {
			// No new reference or write while this decides and deletes; held until the transaction ends.
			db_modify("LOCK TABLE pool_files IN SHARE ROW EXCLUSIVE MODE", __FILE__ . " linje " . __LINE__);
			ensurePoolFilesGuardInboundReferences($db_type);
			foreach ($deferrable as $constraint) {
				db_modify('ALTER TABLE pool_files DROP CONSTRAINT "' . str_replace('"', '""', $constraint) . '"', __FILE__ . " linje " . __LINE__);
			}
			db_modify("DELETE FROM pool_files a USING pool_files b WHERE a.filename = b.filename AND a.id < b.id", __FILE__ . " linje " . __LINE__);
			// An immediate one may already exist next to a dropped deferrable one; then nothing more is needed. The usual
			// name may belong to an index that does not count (a partial one); then use another.
			if (!ensurePoolFilesSchemaState($db_type)['fullFilename']) {
				db_modify("CREATE UNIQUE INDEX " . ensurePoolFilesPostgresIndexName() . " ON pool_files (filename)", __FILE__ . " linje " . __LINE__);
			}
			$ok = ensurePoolFilesSchemaState($db_type)['uniqueFilename'];
		} catch (EnsurePoolFilesSchemaUnknown $e) {
			$ok = false; // a read failed, also when an earlier statement aborted the transaction
			$reason = $e->getMessage();
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
			throw new EnsurePoolFilesSchemaUnknown('pool_files unique filename repair rolled back' . (isset($reason) ? ': ' . $reason : ''));
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

if (!function_exists('ensurePoolFilesMysqlIndexState')) {
	/**
	 * The indexes of pool_files on MySQL/MariaDB, from information_schema alone (it may be read under LOCK TABLES, where
	 * other tables such as settings may not): whether a full one-column unique filename index exists, the one-column
	 * unique prefix (sub_part) indexes on filename, and every index name (lower case) for choosing a free one.
	 *
	 * @return array{full: bool, restrictive: list<string>, taken: array<string, true>}
	 * @throws EnsurePoolFilesSchemaUnknown When the read failed.
	 */
	function ensurePoolFilesMysqlIndexState(): array {
		$columns = array();
		$q = ensurePoolFilesSelect("SELECT index_name, non_unique, column_name, sub_part FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files'", __LINE__);
		while ($r = db_fetch_array($q)) {
			$index = (string)($r['index_name'] ?? $r['INDEX_NAME'] ?? '');
			$columns[$index][] = array(
				'unique' => (int)($r['non_unique'] ?? $r['NON_UNIQUE'] ?? 1) === 0,
				'column' => strtolower((string)($r['column_name'] ?? $r['COLUMN_NAME'] ?? '')),
				'prefix' => ($r['sub_part'] ?? $r['SUB_PART'] ?? null) !== null,
			);
		}
		$state = array('full' => false, 'restrictive' => array(), 'taken' => array());
		foreach ($columns as $index => $parts) {
			$state['taken'][strtolower($index)] = true;
			if (count($parts) === 1 && $parts[0]['unique'] && $parts[0]['column'] === 'filename') {
				if ($parts[0]['prefix']) {
					$state['restrictive'][] = $index;
				} else {
					$state['full'] = true;
				}
			}
		}
		return $state;
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

if (!function_exists('ensurePoolFilesMysqlTableDefinition')) {
	/**
	 * A MySQL/MariaDB table's whole effective definition, as SHOW CREATE TABLE prints it (columns, indexes, engine, row
	 * format, character set, partitioning, every option), with the AUTO_INCREMENT counter left out and the table's own
	 * name written as pool_files, so the trial copy and pool_files compare equal when they are the same. Readable under
	 * LOCK TABLES for a locked table.
	 *
	 * @param string $table A fixed table name (pool_files or this run's trial copy).
	 * @return string '' when it cannot be read.
	 * @throws EnsurePoolFilesSchemaUnknown When the read failed.
	 */
	function ensurePoolFilesMysqlTableDefinition(string $table): string {
		$r = db_fetch_array(ensurePoolFilesSelect("SHOW CREATE TABLE `$table`", __LINE__));
		if (!$r) {
			return '';
		}
		$create = '';
		foreach ($r as $key => $value) {
			if (strcasecmp((string)$key, 'Create Table') === 0) {
				$create = (string)$value;
			}
		}
		if ($create === '') {
			return '';
		}
		$create = preg_replace('/\\sAUTO_INCREMENT=\\d+/i', '', $create);
		// Only the header's table name, in backticks, in double quotes (sql_mode ANSI_QUOTES) or bare
		// (sql_quote_show_create=0); nothing else is touched.
		$name = preg_quote($table, '/');
		return (string)preg_replace('/^CREATE TABLE (?:`' . $name . '`|"' . $name . '"|' . $name . ')(?=\\s)/', 'CREATE TABLE `pool_files`', $create, 1);
	}
}

if (!function_exists('ensurePoolFilesMysqlDefinitionWithoutForeignKeys')) {
	/**
	 * A definition from ensurePoolFilesMysqlTableDefinition() without its FOREIGN KEY constraint lines, which CREATE
	 * TABLE ... LIKE does not copy, and without the line-end commas their removal shifts. Only for comparing the trial
	 * copy with pool_files; pool_files itself is always compared in full.
	 *
	 * @param string $definition
	 * @return string
	 */
	function ensurePoolFilesMysqlDefinitionWithoutForeignKeys(string $definition): string {
		$lines = array();
		foreach (preg_split('/\\R/', $definition) as $line) {
			if (preg_match('/^\\s*CONSTRAINT\\s.*\\sFOREIGN KEY\\s/i', $line)) {
				continue;
			}
			$lines[] = rtrim($line, " ,");
		}
		return implode("\n", $lines);
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
	 * @return string The trial copy's definition (ensurePoolFilesMysqlTableDefinition()): the repair acts only while
	 *                pool_files still has it.
	 * @throws EnsurePoolFilesSchemaUnknown When the index cannot be tried or does not fit; the reason is logged.
	 */
	function ensurePoolFilesMysqlProbeUniqueIndex(): string {
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
		// pool_files' definition before the copy; the copy must have the same one, and the repair acts only while pool_files
		// still has it under the lock (a change between these points stops the run).
		$definition = ensurePoolFilesMysqlTableDefinition('pool_files');
		if ($definition === '') {
			$stop('the definition of pool_files could not be read');
		}
		$created = ensurePoolFilesMysqlExec("CREATE TABLE $probe LIKE pool_files");
		if ($created === null) {
			$stop('the result of the trial copy cannot be checked on this connection');
		}
		if (!$created) {
			$failed();
		}
		try {
			// The copy, before the trial index, must be pool_files as read above (apart from the foreign keys LIKE does not
			// copy); otherwise the trial would test something else.
			if (ensurePoolFilesMysqlDefinitionWithoutForeignKeys(ensurePoolFilesMysqlTableDefinition($probe)) !== ensurePoolFilesMysqlDefinitionWithoutForeignKeys($definition)) {
				$stop('pool_files changed while the trial copy was made, or the copy differs from it');
			}
			if (!ensurePoolFilesMysqlExec("CREATE UNIQUE INDEX {$probe}_filename ON $probe (filename)")) {
				$failed();
			}
			return $definition;
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
		$tried = ensurePoolFilesMysqlProbeUniqueIndex(); // before anything is deleted: the table must be able to hold the index
		// Every statement's own result is checked (ensurePoolFilesMysqlExec()): db_modify() can report a failed one as
		// accepted when mysqli does not throw, and nothing may be deleted without the table lock.
		$locked = ensurePoolFilesMysqlExec("LOCK TABLES pool_files WRITE, pool_files AS a WRITE, pool_files AS b READ");
		if ($locked !== true) {
			error_log('ensurePoolFilesSchema: nothing deleted, pool_files could not be locked for the unique filename repair');
			throw new EnsurePoolFilesSchemaUnknown('pool_files table lock not acquired');
		}
		try {
			// Under the lock, before anything is deleted: no other session can add or drop an index on pool_files now,
			// so the name chosen here stays free and the state read here is the one the statements below act on.
			// The trial holds only for the definition it was made on: if pool_files changed since (another session altered
			// its row format, say), nothing is deleted and a later login tries again.
			if (ensurePoolFilesMysqlTableDefinition('pool_files') !== $tried) {
				error_log('ensurePoolFilesSchema: nothing deleted, pool_files changed between the trial and the table lock');
				throw new EnsurePoolFilesSchemaUnknown('pool_files definition changed after the trial');
			}
			ensurePoolFilesGuardInboundReferences($db_type);
			$before = ensurePoolFilesMysqlIndexState();
			$name = ensurePoolFilesFreeIndexName($before['taken']);
			if (!ensurePoolFilesMysqlExec("DELETE a FROM pool_files a JOIN pool_files b ON a.filename = b.filename AND a.id < b.id")) {
				throw new EnsurePoolFilesSchemaUnknown('pool_files duplicate collapse failed');
			}
			if (!$before['full'] && !ensurePoolFilesMysqlExec("CREATE UNIQUE INDEX $name ON pool_files (filename)")) {
				throw new EnsurePoolFilesSchemaUnknown('pool_files unique filename index not created');
			}
			// Only after the full index is in place: the prefix indexes on filename go.
			foreach ($before['restrictive'] as $index) {
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
	 * @return array{columns: list<string>, uniqueFilename: bool, fullFilename: bool, deferrableFilename: list<string>, restrictiveFilename: list<string>, otherFilenameExpressions: list<string>, normAmountIndex: bool, normAmountBackfilled: bool}|null
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
				WHERE t.relname = 'pool_files' AND n.nspname = current_schema() AND i.indisunique AND i.indisvalid AND i.indnkeyatts = 1
				AND i.indpred IS NULL AND i.indexprs IS NULL
				AND i.indkey[0] = (SELECT attnum FROM pg_attribute WHERE attrelid = t.oid AND attname = 'filename')
				AND NOT EXISTS (SELECT 1 FROM pg_constraint c WHERE c.conindid = i.indexrelid AND c.condeferrable)";
			// A deferrable unique or exclusion constraint on filename alone: ON CONFLICT (filename) infers it and then
			// refuses the insert, even when an immediate index exists too.
			$deferrable = "SELECT c.conname FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid JOIN pg_namespace n ON n.oid = t.relnamespace
				WHERE t.relname = 'pool_files' AND n.nspname = current_schema() AND c.contype IN ('u', 'x') AND c.condeferrable
				AND array_length(c.conkey, 1) = 1
				AND c.conkey[1] = (SELECT attnum FROM pg_attribute WHERE attrelid = t.oid AND attname = 'filename')";
			// Unique indexes on an expression of filename alone (left(filename, 7), reverse(filename), a CASE rule ...): the
			// catalogue says which column an expression reads (pg_depend: filename and no other column, and no dependency
			// on anything else), but not whether the index is an obsolete prefix, a lookup or a rule of its own (that also
			// depends on CHECK constraints, domains and widths). They are therefore never dropped, only named in the log for
			// review when the repair runs.
			$filenameAttnum = "(SELECT attnum FROM pg_attribute WHERE attrelid = t.oid AND attname = 'filename' AND NOT attisdropped)";
			$columnDependency = "SELECT 1 FROM pg_depend d WHERE d.classid = 'pg_class'::regclass AND d.objid = i.indexrelid
					AND d.refclassid = 'pg_class'::regclass AND d.refobjid = t.oid AND d.refobjsubid > 0";
			$restrictive = "SELECT ci.relname AS index_name FROM pg_index i JOIN pg_class t ON t.oid = i.indrelid JOIN pg_class ci ON ci.oid = i.indexrelid
				JOIN pg_namespace n ON n.oid = t.relnamespace
				WHERE t.relname = 'pool_files' AND n.nspname = current_schema() AND i.indisunique
				AND i.indnatts = 1 AND i.indnkeyatts = 1 AND i.indkey[0] = 0 AND i.indexprs IS NOT NULL AND i.indpred IS NULL
				AND EXISTS ($columnDependency AND d.refobjsubid = $filenameAttnum)
				AND NOT EXISTS ($columnDependency AND d.refobjsubid <> $filenameAttnum)
				AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.classid = 'pg_class'::regclass AND d.objid = i.indexrelid
					AND NOT (d.refclassid = 'pg_class'::regclass AND d.refobjid = t.oid))";
			$normIndex = "SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'pool_files' AND indexname = 'idx_pool_files_norm_amount'";
		}
		$deferrableNames = array();
		if (!$mysql) {
			$q = ensurePoolFilesSelect($deferrable, __LINE__);
			while ($r = db_fetch_array($q)) {
				$deferrableNames[] = (string)$r['conname'];
			}
		}
		// MySQL: prefix indexes (sub_part) are restrictive and replaced by the repair. PostgreSQL: unique expression
		// indexes on filename alone are only listed for review, never dropped.
		$restrictiveNames = array();
		$otherNames = array();
		$q = ensurePoolFilesSelect($restrictive, __LINE__);
		while ($r = db_fetch_array($q)) {
			$index = (string)($r['index_name'] ?? $r['INDEX_NAME'] ?? '');
			if ($mysql) {
				$restrictiveNames[] = $index;
			} else {
				$otherNames[] = $index;
			}
		}
		$full = (bool)db_fetch_array(ensurePoolFilesSelect($unique, __LINE__));
		return array(
			'columns' => $missing,
			'uniqueFilename' => $full && !$deferrableNames && !$restrictiveNames,
			'fullFilename' => $full,
			'deferrableFilename' => $deferrableNames,
			'restrictiveFilename' => $restrictiveNames,
			'otherFilenameExpressions' => $otherNames,
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
			// A read failed or a step could not be completed: no flag, nothing more this time; the next login tries again.
			error_log('ensurePoolFilesSchema: stopped, the next login tries again: ' . $e->getMessage());
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
					if ($state['otherFilenameExpressions']) {
						error_log('ensurePoolFilesSchema: kept unique expression indexes on filename for review (they may refuse names the full index allows): ' . implode(', ', $state['otherFilenameExpressions']));
					}
					ensurePoolFilesUniqueRepairPostgres($db_type, $state['deferrableFilename']);
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
