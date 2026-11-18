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
	 * @return array{columns: list<string>, uniqueFilename: bool, deferrableFilename: list<string>, normAmountIndex: bool, normAmountBackfilled: bool}|null
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
			// Exactly one column in the index: a composite UNIQUE(filename, x) still allows duplicate filenames.
			$unique = "SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files' AND non_unique = 0 GROUP BY index_name HAVING COUNT(*) = 1 AND MAX(column_name) = 'filename'";
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
			$normIndex = "SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'pool_files' AND indexname = 'idx_pool_files_norm_amount'";
		}
		$deferrableNames = array();
		if (!$mysql) {
			$q = ensurePoolFilesSelect($deferrable, __LINE__);
			while ($r = db_fetch_array($q)) {
				$deferrableNames[] = (string)$r['conname'];
			}
		}
		return array(
			'columns' => $missing,
			'uniqueFilename' => !$deferrableNames && (bool)db_fetch_array(ensurePoolFilesSelect($unique, __LINE__)),
			'deferrableFilename' => $deferrableNames,
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
		try {
			return ensurePoolFilesSchemaRun($db_type, $done);
		} catch (EnsurePoolFilesSchemaUnknown $e) {
			return $done; // a read failed: no flag, nothing more this time; the next login tries again
		} catch (mysqli_sql_exception $e) {
			// A write that failed on MySQL/MariaDB (mysqli throws). The flag is only written after a verified
			// backfill, so stopping here leaves it unset; the next login tries again.
			error_log('ensurePoolFilesSchema: write failed: ' . $e->getMessage());
			return $done;
		}
	}
}

if (!function_exists('ensurePoolFilesSchemaRun')) {
	/**
	 * ensurePoolFilesSchema()'s work; $done collects what was done so far, also when a read fails halfway.
	 *
	 * @param string       $db_type
	 * @param list<string> $done
	 * @return list<string>
	 * @throws EnsurePoolFilesSchemaUnknown When a read failed.
	 */
	function ensurePoolFilesSchemaRun(string $db_type, array &$done): array {
		$state = ensurePoolFilesSchemaState($db_type);
		if ($state === null || (!$state['columns'] && $state['uniqueFilename'] && $state['normAmountIndex'] && $state['normAmountBackfilled'])) {
			return array();
		}
		$mysql = in_array($db_type, array('mysql', 'mysqli'), true);
		if ($mysql) {
			$lockName = "CONCAT('saldi:pool_files_schema:', MD5(DATABASE()))";
			$locked = db_fetch_array(ensurePoolFilesSelect("SELECT GET_LOCK($lockName, 30) AS acquired", __LINE__));
			if ((int)($locked['acquired'] ?? 0) !== 1) {
				return array(); // another login holds it; the next login checks again
			}
			$unlock = "SELECT RELEASE_LOCK($lockName)";
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
				$done[] = $column;
			}
			if (!$state['uniqueFilename']) {
				if ($mysql) {
					db_modify("DELETE a FROM pool_files a JOIN pool_files b ON a.filename = b.filename AND a.id < b.id", __FILE__ . " linje " . __LINE__);
					db_modify("CREATE UNIQUE INDEX pool_files_filename_uidx ON pool_files (filename)", __FILE__ . " linje " . __LINE__);
				} else {
					foreach ($state['deferrableFilename'] as $constraint) {
						db_modify('ALTER TABLE pool_files DROP CONSTRAINT "' . str_replace('"', '""', $constraint) . '"', __FILE__ . " linje " . __LINE__);
					}
					db_modify("DELETE FROM pool_files a USING pool_files b WHERE a.filename = b.filename AND a.id < b.id", __FILE__ . " linje " . __LINE__);
					// The usual name may already belong to an index that does not count (a partial one); then use another.
					// An immediate one may already exist next to a dropped deferrable one; then nothing more is needed.
					if (!ensurePoolFilesSchemaState($db_type)['uniqueFilename']) {
						$taken = db_fetch_array(ensurePoolFilesSelect("SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE c.relname = 'pool_files_filename_uidx' AND n.nspname = current_schema()", __LINE__));
						$name = $taken ? 'pool_files_filename_full_uidx' : 'pool_files_filename_uidx';
						db_modify("CREATE UNIQUE INDEX $name ON pool_files (filename)", __FILE__ . " linje " . __LINE__);
					}
				}
				$done[] = 'unique_filename';
			}
			if (!$state['normAmountIndex']) {
				$ifNotExists = $mysql ? '' : 'IF NOT EXISTS ';
				db_modify("CREATE INDEX {$ifNotExists}idx_pool_files_norm_amount ON pool_files (norm_amount)", __FILE__ . " linje " . __LINE__);
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
				$done[] = 'norm_amount_backfill';
			}
		} finally {
			try {
				db_select($unlock, __FILE__ . " linje " . __LINE__);
			} catch (mysqli_sql_exception $e) {
				error_log('ensurePoolFilesSchema: unlock failed: ' . $e->getMessage()); // the lock ends with the connection
			}
		}
		return $done;
	}
}
