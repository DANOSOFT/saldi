<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/poolArchive.php --- ver 5.0.0 --- 2026-10-03 ---
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
