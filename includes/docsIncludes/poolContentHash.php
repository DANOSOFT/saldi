<?php
// --- includes/docsIncludes/poolContentHash.php ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260925 LOE MB-42 pool_files.content_sha256 is how the pool recognises the same bilag under
//                  another filename. The column is created here rather than in betweenUpdates.php
//                  alone: the REST attachment endpoint (restapi/core/BaseEndpoint.php includes
//                  connect.php but never betweenUpdates.php) and the pulje-folder sync both read and
//                  write it, so an installation that only ever receives API traffic would otherwise
//                  hit "column content_sha256 does not exist" on its first upload.

if (!function_exists('poolContentHashColumnExists')) {
	/**
	 * Is pool_files.content_sha256 there - and, on request, create it.
	 *
	 * betweenUpdates.php calls this with $create = true at login, the REST upload and the pulje sync
	 * call it before their hash queries. Missing schema is never fatal: the callers fall back to
	 * filename-only behaviour when the column cannot be created (no ALTER privilege on MySQL, or a
	 * tenant whose pool_files table does not exist yet - in that case the caller's own
	 * CREATE TABLE fallback already includes the column).
	 *
	 * @param bool $create Create the column and its index when they are missing.
	 * @return bool Whether the column exists (after the attempt).
	 */
	function poolContentHashColumnExists($create = false)
	{
		global $db_type;
		static $exists = null;

		if ($exists === true) {
			return true;
		}
		if ($exists === false && !$create) {
			return false;
		}

		$mysql = in_array($db_type, array('mysql', 'mysqli'), true);
		$schemaClause = $mysql ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
		$probe = "SELECT column_name FROM information_schema.columns WHERE table_name = 'pool_files' AND column_name = 'content_sha256'" . $schemaClause;

		$exists = (bool) db_fetch_array(db_select($probe, __FILE__ . " linje " . __LINE__));
		if ($exists || !$create) {
			return $exists;
		}

		// Without the table there is nothing to alter; the caller's CREATE TABLE fallback creates it
		// with the column already in place.
		$tableProbe = "SELECT table_name FROM information_schema.tables WHERE table_name = 'pool_files'" . $schemaClause;
		if (!db_fetch_array(db_select($tableProbe, __FILE__ . " linje " . __LINE__))) {
			return false;
		}

		if ($mysql) {
			// No ADD COLUMN IF NOT EXISTS on MySQL and two concurrent uploads can both pass the check
			// above, so serialize per tenant and recheck under the lock (same as betweenUpdates.php).
			$lock = "CONCAT('saldi:pool_files_content_hash:', MD5(DATABASE()))";
			$lockResult = db_fetch_array(db_select("SELECT GET_LOCK($lock, 30) AS acquired", __FILE__ . " linje " . __LINE__));
			if ((int) (isset($lockResult['acquired']) ? $lockResult['acquired'] : 0) !== 1) {
				return false;
			}
			try {
				if (!db_fetch_array(db_select($probe, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE pool_files ADD COLUMN content_sha256 CHAR(64)", __FILE__ . " linje " . __LINE__);
				}
			} finally {
				db_select("SELECT RELEASE_LOCK($lock)", __FILE__ . " linje " . __LINE__);
			}
		} else {
			db_modify("ALTER TABLE pool_files ADD COLUMN IF NOT EXISTS content_sha256 CHAR(64)", __FILE__ . " linje " . __LINE__);
		}

		$exists = (bool) db_fetch_array(db_select($probe, __FILE__ . " linje " . __LINE__));

		// Non-unique on purpose: two different bilag may share content, and a unique index would make
		// the sync's own duplicate cleanup fail on the rows that cleanup exists to remove.
		if ($exists) {
			if ($mysql) {
				$indexProbe = "SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files' AND index_name = 'idx_pool_files_content_sha256'";
				$indexSql = "CREATE INDEX idx_pool_files_content_sha256 ON pool_files (content_sha256)";
			} else {
				$indexProbe = "SELECT indexname FROM pg_indexes WHERE tablename = 'pool_files' AND indexname = 'idx_pool_files_content_sha256'";
				$indexSql = "CREATE INDEX IF NOT EXISTS idx_pool_files_content_sha256 ON pool_files (content_sha256)";
			}
			if (!db_fetch_array(db_select($indexProbe, __FILE__ . " linje " . __LINE__))) {
				db_modify($indexSql, __FILE__ . " linje " . __LINE__);
			}
		}

		return $exists;
	}
}

if (!function_exists('poolContentHashEnsureSchema')) {
	/**
	 * Make sure pool_files.content_sha256 exists before anything queries it.
	 *
	 * @return bool Whether the column is available.
	 */
	function poolContentHashEnsureSchema()
	{
		return poolContentHashColumnExists(true);
	}
}

if (!function_exists('poolContentHashForFile')) {
	/**
	 * The sha256 of a file on disk, or '' when it cannot be read.
	 *
	 * @param string $path
	 * @return string 64-character hex digest, or ''
	 */
	function poolContentHashForFile($path)
	{
		if (!is_file($path)) {
			return '';
		}
		$hash = @hash_file('sha256', $path);
		return $hash ? $hash : '';
	}
}
