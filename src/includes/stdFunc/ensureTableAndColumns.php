<?PHP
//                         ___   _   _   __  _     ___  _ _
//                        / __| / \ | | |  \| |   |   \| / /
//                        \__ \/ _ \| |_| | | | _ | |) |  <
//                        |___/_/ \_|___|__/|_||_||___/|_\_\
//
// --- includes/stdFunc/ensureTableAndColumns.php --- rel 4.1.1 --- 2025.04.24 ---
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
// Copyright (c) 2005-2025 Saldi.dk ApS
// ----------------------------------------------------------------------

if (!function_exists("ensureTableAndColumns")) {
	function ensureTableAndColumns($db, $tableName, $expectedColumns, $renameColumns = []) {
		// Check if table exists
		$qtxt = "SELECT table_name FROM information_schema.tables WHERE (table_schema = '$db' or table_catalog='$db') and table_name='$tableName'";
		$tableExists = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));

		if (!$tableExists) {
			// Create table with expected columns and their types
			$columnsTxt = implode(", ", array_map(function ($col, $type) {
				return "$col $type";
			}, array_keys($expectedColumns), $expectedColumns));
			$qtxt = "CREATE TABLE $tableName ($columnsTxt)";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		} else {
			// Fetch all columns of the table
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE (table_schema = '$db' or table_catalog='$db') and table_name='$tableName'";
			$result = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			$columns = [];
			while ($row = db_fetch_array($result)) {
				$columns[] = $row['column_name'];
			}

			// Rename columns if specified
			foreach ($renameColumns as $oldName => $newName) {
				if (in_array($oldName, $columns) && !in_array($newName, $columns)) {
					$qtxt = "ALTER TABLE $tableName RENAME COLUMN \"$oldName\" TO \"$newName\"";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					// Update the columns array to reflect the change
					$columns[array_search($oldName, $columns)] = $newName;
				}
			}
			// Check if all expected columns exist, if not, add them with their types
			foreach ($expectedColumns as $column => $type) {
				if (!in_array($column, $columns)) {
					$qtxt = "ALTER TABLE $tableName ADD COLUMN $column $type";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
			}
		}
		return true;
	}
}
?>
