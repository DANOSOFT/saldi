<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/ensureColumn.php --- lap 5.0.0 --- 2026-09-02 ---
// LICENS
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
// Copyright (c) 2003-2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20260902 CL/LH  Self-healing column guard. includes/betweenUpdates.php only runs when a
//                 regnskab is opened through admin/aaben_regnskab.php (or the POS), so a page
//                 that writes a recently added column can meet a tenant that never ran the
//                 migration. Call this at the top of such pages - it is idempotent and cheap
//                 (one information_schema lookup per table/column per request).

if (!function_exists('ensure_column')) {
	/**
	 * Add $column to $table if it does not exist yet.
	 *
	 * @param string $table       table name (letters, digits, underscore)
	 * @param string $column      column name (letters, digits, underscore)
	 * @param string $definition  column type/definition, e.g. "varchar(2)" or "text default ''"
	 * @return bool               true when the column exists after the call
	 */
	function ensure_column($table, $column, $definition) {
		static $known = array();
		$table  = preg_replace('/[^a-z0-9_]/i', '', (string)$table);
		$column = preg_replace('/[^a-z0-9_]/i', '', (string)$column);
		if ($table === '' || $column === '') return false;
		$key = "$table.$column";
		if (isset($known[$key])) return true;

		$qtxt = "SELECT 1 FROM information_schema.columns WHERE table_name='$table' AND column_name='$column' LIMIT 1";
		if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			// IF NOT EXISTS: two concurrent requests can both pass the check above.
			db_modify("ALTER TABLE $table ADD COLUMN IF NOT EXISTS $column $definition", __FILE__ . " linje " . __LINE__);
			if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) return false;
		}
		$known[$key] = true;
		return true;
	}
}
?>
