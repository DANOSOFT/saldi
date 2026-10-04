<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/poolArchive.php --- ver 5.0.0 --- 2026-10-04 ---
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
// 20261003 CL/SZ SD-717 Created: the archive of the document pool ("Arkivér", "Vis arkiverede", "Gendan").
//                An archived document keeps its file in the pool folder and its pool_files row; pool_files.archived is set.
//                Every place that offers pool documents for use leaves archived ones out through poolArchiveActiveSql().
//                Archive and restore are written to audit_log (SD-724).
//                Automatic deletion after 12 months is a later ticket; the archive date is stored for it.
// 20261004 CL/SZ SD-727 Archived documents are deleted 12 months after archiving: file, cached XML preview and pool_files row.
//                poolArchivePurge() runs inside the pool's periodic folder sync (no scheduler), and writes document.purged to audit_log and a line to the pool log.
//                The date a document will be deleted is shown in the archive (poolArchiveDeleteDate()).

require_once __DIR__ . '/../auditLog.php';

if (!function_exists('poolArchiveReady')) {
	/**
	 * Whether pool_files has the archive columns. includes/betweenUpdates.php adds them at login, so a session
	 * that was logged in before the update has the table without them until the next login.
	 *
	 * @return bool
	 */
	function poolArchiveReady() {
		global $db_type;
		static $ready = null;
		if ($ready !== null) {
			return $ready;
		}
		$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'pool_files' AND column_name = 'archived_by'";
		$qtxt .= ($db_type == 'mysql' || $db_type == 'mysqli') ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
		$ready = (bool)db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		return $ready;
	}
}

if (!function_exists('poolArchiveActiveSql')) {
	/**
	 * SQL condition for "not archived", to AND into a query on pool_files. Always true before the columns exist.
	 *
	 * @param string $alias Table alias with its dot, e.g. 'p.', or '' for none.
	 * @return string
	 */
	function poolArchiveActiveSql($alias = '') {
		return poolArchiveReady() ? "{$alias}archived IS NULL" : "1=1";
	}
}

if (!function_exists('poolArchiveAuditEntry')) {
	/**
	 * What one archive or restore writes to audit_log.
	 *
	 * @param array{id: int|string, filename: string, archived: string|null, archived_by: int|string|null} $row The row before the change.
	 * @param bool $archive true to archive, false to restore.
	 * @param string $now The archive time as stored in the row (archive only).
	 * @param int|null $userId Who archives (archive only).
	 * @return array{handling: string, objekt_type: string, objekt_id: string, detaljer: array{before: array<string, mixed>, after: array<string, mixed>}}
	 */
	function poolArchiveAuditEntry(array $row, $archive, $now = '', $userId = null) {
		$before = array(
			'filename' => (string)$row['filename'],
			'archived' => $row['archived'] === '' ? null : $row['archived'],
			'archived_by' => ($row['archived_by'] === null || $row['archived_by'] === '') ? null : (int)$row['archived_by'],
		);
		$after = $archive
			? array('filename' => (string)$row['filename'], 'archived' => $now, 'archived_by' => $userId)
			: array('filename' => (string)$row['filename'], 'archived' => null, 'archived_by' => null);
		return array(
			'handling' => $archive ? 'document.archived' : 'document.restored',
			'objekt_type' => 'dokument',
			'objekt_id' => (string)(int)$row['id'],
			'detaljer' => array('before' => $before, 'after' => $after),
		);
	}
}

if (!function_exists('poolArchiveSet')) {
	/**
	 * Archives or restores pool documents by file name. A document already in the wanted state, or not in the pool, is skipped.
	 *
	 * @param array<int, string> $filenames
	 * @param bool $archive true to archive, false to restore ("Gendan").
	 * @param int|null $userId brugere.id of the user, -1 for a revisor session.
	 * @return array{changed: array<int, string>, skipped: array<int, string>}
	 */
	function poolArchiveSet(array $filenames, $archive, $userId) {
		$changed = array();
		$skipped = array();
		if (!poolArchiveReady()) {
			return array('changed' => $changed, 'skipped' => array_values($filenames));
		}
		$userSql = $userId === null ? 'NULL' : (int)$userId;
		foreach (array_unique($filenames) as $filename) {
			$filename = (string)$filename;
			$qtxt = "SELECT id, filename, archived, archived_by FROM pool_files WHERE filename = '" . db_escape_string($filename) . "'";
			$row = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			$isArchived = $row && $row['archived'] !== null && $row['archived'] !== '';
			if (!$row || $isArchived === (bool)$archive) {
				$skipped[] = $filename;
				continue;
			}
			// The state is checked again in the update, so two users clicking at once give one change and one audit entry
			if ($archive) {
				// The database's clock, as audit_log.tidspunkt and pool_files.updated use
				$qtxt = "UPDATE pool_files SET archived = CURRENT_TIMESTAMP, archived_by = $userSql WHERE id = " . (int)$row['id'] . " AND archived IS NULL";
			} else {
				$qtxt = "UPDATE pool_files SET archived = NULL, archived_by = NULL WHERE id = " . (int)$row['id'] . " AND archived IS NOT NULL";
			}
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			$check = db_fetch_array(db_select("SELECT archived FROM pool_files WHERE id = " . (int)$row['id'], __FILE__ . " linje " . __LINE__));
			$nowArchived = $check && $check['archived'] !== null && $check['archived'] !== '';
			if (!$check || $nowArchived !== (bool)$archive) {
				$skipped[] = $filename;
				continue;
			}
			$entry = poolArchiveAuditEntry($row, $archive, $archive ? (string)$check['archived'] : '', $userId === null ? null : (int)$userId);
			audit_log_write($entry['handling'], $entry['objekt_type'], $entry['objekt_id'], $entry['detaljer'], 'ui');
			$changed[] = $filename;
		}
		return array('changed' => $changed, 'skipped' => $skipped);
	}
}

if (!function_exists('poolArchiveRetentionMonths')) {
	/**
	 * How long an archived document is kept before it is deleted (spec Task 8, requirement 4).
	 *
	 * @return int
	 */
	function poolArchiveRetentionMonths() {
		return 12;
	}
}

if (!function_exists('poolArchiveDeleteDate')) {
	/**
	 * The date an archived document will be deleted: the archive date plus the retention, as the database counts months
	 * (31 March + 12 months = 31 March, 29 February + 12 months = 28 February).
	 *
	 * @param string|null $archived pool_files.archived.
	 * @return string Y-m-d, or '' when not archived.
	 */
	function poolArchiveDeleteDate($archived) {
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string)$archived, $m)) {
			return '';
		}
		$months = (int)$m[1] * 12 + (int)$m[2] - 1 + poolArchiveRetentionMonths();
		$year = intdiv($months, 12);
		$month = $months % 12 + 1;
		$day = min((int)$m[3], (int)date('t', mktime(0, 0, 0, $month, 1, $year)));
		return sprintf('%04d-%02d-%02d', $year, $month, $day);
	}
}

if (!function_exists('poolArchiveDueSql')) {
	/**
	 * SQL condition for "archived long enough to be deleted", by the database's own clock (the clock pool_files.archived was set by).
	 * Counted forward from the archive date, as poolArchiveDeleteDate() shows it: counted back from today, a document archived on
	 * 29 February would be deleted a day after the date shown.
	 *
	 * @return string
	 */
	function poolArchiveDueSql() {
		global $db_type;
		$months = (int)poolArchiveRetentionMonths();
		$interval = ($db_type == 'mysql' || $db_type == 'mysqli') ? "INTERVAL $months MONTH" : "INTERVAL '$months months'";
		return "archived IS NOT NULL AND archived + $interval <= CURRENT_TIMESTAMP";
	}
}

if (!function_exists('poolArchivePurgeEntry')) {
	/**
	 * What one automatic deletion writes to audit_log.
	 *
	 * @param array{id: int|string, filename: string, archived: string, archived_by: int|string|null} $row The row before deletion.
	 * @param string $deleted When it was deleted, by the database's clock.
	 * @return array{handling: string, objekt_type: string, objekt_id: string, detaljer: array{before: array<string, mixed>, after: array<string, mixed>}}
	 */
	function poolArchivePurgeEntry(array $row, $deleted) {
		return array(
			'handling' => 'document.purged',
			'objekt_type' => 'dokument',
			'objekt_id' => (string)(int)$row['id'],
			'detaljer' => array(
				'before' => array(
					'filename' => (string)$row['filename'],
					'archived' => (string)$row['archived'],
					'archived_by' => ($row['archived_by'] === null || $row['archived_by'] === '') ? null : (int)$row['archived_by'],
				),
				'after' => array('filename' => (string)$row['filename'], 'deleted' => (string)$deleted, 'reason' => 'archived ' . poolArchiveRetentionMonths() . ' months'),
			),
		);
	}
}

if (!function_exists('poolArchivePurge')) {
	/**
	 * Deletes the documents archived longer than the retention: the file in the pool folder, its cached XML preview and its pool_files row.
	 * Each deletion is written to audit_log (document.purged, kilde 'system') and the pool log.
	 *
	 * A file that can't be deleted keeps its row, so the folder sync doesn't bring the document back as a new, unarchived one; it is tried again on the next sync.
	 *
	 * @param string $puljePath The company's pool folder.
	 * @param string $db The company's database, for the preview cache in temp/<db>.
	 * @param int $limit At most this many per run, so one sync stays short; the rest follow on the next.
	 * @return array{purged: array<int, string>, failed: array<int, string>}
	 */
	function poolArchivePurge($puljePath, $db, $limit = 100) {
		$purged = array();
		$failed = array();
		if (!poolArchiveReady()) {
			return array('purged' => $purged, 'failed' => $failed);
		}
		$qtxt = "SELECT id, filename, archived, archived_by FROM pool_files WHERE " . poolArchiveDueSql() . " ORDER BY archived, id LIMIT " . (int)$limit;
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		$rows = array();
		while ($r = db_fetch_array($q)) {
			$rows[] = $r;
		}
		$tempDir = __DIR__ . '/../../temp/' . preg_replace('/[^A-Za-z0-9_]/', '', (string)$db);
		foreach ($rows as $row) {
			$filename = (string)$row['filename'];
			$id = (int)$row['id'];
			// Still archived and due: "Gendan" in the meantime keeps the document
			$still = db_fetch_array(db_select("SELECT id FROM pool_files WHERE id = $id AND " . poolArchiveDueSql(), __FILE__ . " linje " . __LINE__));
			if (!$still) {
				continue;
			}
			// A name from the database is never a path
			$path = ($filename !== '' && basename($filename) === $filename) ? "$puljePath/$filename" : '';
			if ($path !== '' && file_exists($path) && !@unlink($path)) {
				$failed[] = $filename;
				poolArchiveLogLine("Archive purge: could not delete $filename (archived {$row['archived']}); the row is kept and the deletion is tried again on the next sync");
				continue;
			}
			$preview = "$tempDir/xml_preview_" . md5($filename) . ".html";
			if (is_file($preview)) {
				@unlink($preview);
			}
			db_modify("DELETE FROM pool_files WHERE id = $id", __FILE__ . " linje " . __LINE__);
			$now = db_fetch_array(db_select("SELECT CURRENT_TIMESTAMP AS now", __FILE__ . " linje " . __LINE__));
			$deleted = $now ? substr((string)$now['now'], 0, 19) : date('Y-m-d H:i:s');
			$entry = poolArchivePurgeEntry($row, $deleted);
			audit_log_write($entry['handling'], $entry['objekt_type'], $entry['objekt_id'], $entry['detaljer'], 'system');
			poolArchiveLogLine("Archive purge: deleted $filename (pool_files id $id, archived {$row['archived']}, deleted $deleted, after " . poolArchiveRetentionMonths() . " months in the archive)");
			$purged[] = $filename;
		}
		return array('purged' => $purged, 'failed' => $failed);
	}
}

if (!function_exists('poolArchiveLogLine')) {
	/**
	 * One line in the pool log, temp/<db>/docPool.log (docPool.php's docPoolLog() when it is loaded).
	 *
	 * @param string $message
	 * @return void
	 */
	function poolArchiveLogLine($message) {
		global $db;
		if (function_exists('docPoolLog')) {
			docPoolLog($message);
			return;
		}
		$dir = __DIR__ . '/../../temp/' . preg_replace('/[^A-Za-z0-9_]/', '', (string)($db ?? 'unknown_db'));
		if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
			error_log("poolArchive: $message");
			return;
		}
		@file_put_contents("$dir/docPool.log", date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND | LOCK_EX);
	}
}
