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
// 20261004 LOE Support original-upload hashes and cache column availability per tenant.
// 20261005 CL/SZ SD-727 poolContentHashStore() and poolContentHashBackfill(): a row an upload's extraction created had no hash, so the
//                  same bilag arriving again was never recognised (MB-42) and an archived one never restored.

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
	 * @param string $column Stored-file or original-upload hash column.
	 * @return bool Whether the column exists (after the attempt).
	 */
	function poolContentHashColumnExists($create = false, $column = 'content_sha256')
	{
		global $db_type, $db;
		if (!in_array($column, array('content_sha256', 'source_sha256'), true)) {
			throw new InvalidArgumentException('Invalid pool hash column');
		}
		static $columns = array();
		static $indexes = array();
		$key = $db_type . ':' . $db . ':' . $column;
		$exists = isset($columns[$key]) ? $columns[$key] : null;
		if ($exists === true && (!$create || isset($indexes[$key]))) {
			return true;
		}
		if ($exists === false && !$create) {
			return false;
		}

		$mysql = in_array($db_type, array('mysql', 'mysqli'), true);
		$schemaClause = $mysql ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
		$probe = "SELECT column_name FROM information_schema.columns WHERE table_name = 'pool_files' AND column_name = '$column'" . $schemaClause;
		$exists = (bool) db_fetch_array(db_select($probe, __FILE__ . " linje " . __LINE__));
		$columns[$key] = $exists;
		if (!$create) {
			return $exists;
		}
		if (!$exists) {
			$tableProbe = "SELECT table_name FROM information_schema.tables WHERE table_name = 'pool_files'" . $schemaClause;
			if (!db_fetch_array(db_select($tableProbe, __FILE__ . " linje " . __LINE__))) {
				return false;
			}
		}

		// MySQL lacks ADD COLUMN/CREATE INDEX IF NOT EXISTS. Hold the same schema lock
		// through both operations so concurrent login/API requests cannot race either one.
		$lock = "CONCAT('saldi:pool_files_hash_schema:', MD5(DATABASE()))";
		if ($mysql) {
			$lockResult = db_fetch_array(db_select("SELECT GET_LOCK($lock, 30) AS acquired", __FILE__ . " linje " . __LINE__));
			if ((int) (isset($lockResult['acquired']) ? $lockResult['acquired'] : 0) !== 1) {
				return false;
			}
		}
		try {
			if (!db_fetch_array(db_select($probe, __FILE__ . " linje " . __LINE__))) {
				$ifNotExists = $mysql ? '' : 'IF NOT EXISTS ';
				db_modify("ALTER TABLE pool_files ADD COLUMN " . $ifNotExists . "$column CHAR(64)", __FILE__ . " linje " . __LINE__);
			}
			$exists = (bool) db_fetch_array(db_select($probe, __FILE__ . " linje " . __LINE__));
			$columns[$key] = $exists;
			// Non-unique: older duplicate rows must remain valid until explicitly removed.
			if ($exists) {
				if ($mysql) {
					$indexProbe = "SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files' AND index_name = 'idx_pool_files_{$column}'";
					$indexSql = "CREATE INDEX idx_pool_files_{$column} ON pool_files ($column)";
				} else {
					$indexProbe = "SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'pool_files' AND indexname = 'idx_pool_files_{$column}'";
					$indexSql = "CREATE INDEX IF NOT EXISTS idx_pool_files_{$column} ON pool_files ($column)";
				}
				if (!db_fetch_array(db_select($indexProbe, __FILE__ . " linje " . __LINE__))) {
					db_modify($indexSql, __FILE__ . " linje " . __LINE__);
				}
				$indexes[$key] = true;
			}
		} finally {
			if ($mysql) {
				db_select("SELECT RELEASE_LOCK($lock)", __FILE__ . " linje " . __LINE__);
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

if (!function_exists('poolContentHashStore')) {
	/**
	 * Gives a pool row its content hash when it has none (a row created by an upload's extraction, not by the folder sync).
	 *
	 * @param string $filename Pool file name (the row's filename).
	 * @param string $path     Full path of the file.
	 * @return void
	 */
	function poolContentHashStore($filename, $path)
	{
		if (!poolContentHashColumnExists()) {
			return;
		}
		$hash = poolContentHashForFile($path);
		if ($hash === '') {
			return;
		}
		db_modify("UPDATE pool_files SET content_sha256 = '" . db_escape_string($hash) . "' WHERE filename = '" . db_escape_string((string)$filename) . "' AND (content_sha256 IS NULL OR content_sha256 = '')", __FILE__ . " linje " . __LINE__);
	}
}

if (!function_exists('poolContentHashBackfill')) {
	/**
	 * Hashes pool rows that have no content hash yet, a batch at a time, so rows from before poolContentHashStore() are recognised too.
	 *
	 * @param string $puljePath The company's pool folder.
	 * @param int    $limit     Rows per call.
	 * @return int Rows hashed.
	 */
	function poolContentHashBackfill($puljePath, $limit = 200)
	{
		if (!poolContentHashColumnExists()) {
			return 0;
		}
		$names = array();
		$q = db_select("SELECT filename FROM pool_files WHERE content_sha256 IS NULL OR content_sha256 = '' ORDER BY id LIMIT " . (int)$limit, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$names[] = (string)$r['filename'];
		}
		$done = 0;
		foreach ($names as $name) {
			if (basename($name) !== $name || !is_file("$puljePath/$name")) {
				continue;
			}
			poolContentHashStore($name, "$puljePath/$name");
			$done++;
		}
		return $done;
	}
}
