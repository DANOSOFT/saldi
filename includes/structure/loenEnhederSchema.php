<?php
// --- includes/structure/loenEnhederSchema.php --- ver 5.1.1 --- 2026-11-25 ---
// LICENSE: GNU GPL v2 or later. Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261125 CDX/NTR Restore missing piecework percentage and height-surcharge columns on existing tenants.

require_once __DIR__ . '/../database/compatibility.php';

/**
 * Complete the piecework line schema without replacing existing columns or values.
 * Called by betweenUpdates.php; new tenants get these columns in opret_build_tenant().
 * Concurrent logins recheck missing columns under the same database lock.
 *
 * @return void
 */
function loen_enheder_schema(): void {
	$required = array('procent', 'op_30', 'ned_30', 'op_70', 'ned_70', 'op_100', 'ned_100', 'op_160', 'ned_160');
	$existing = array();
	$result = db_select("SELECT column_name FROM information_schema.columns WHERE table_name = 'loen_enheder'" . opdat_schema_filter(db_type()), __FILE__ . ' linje ' . __LINE__);
	while ($row = db_fetch_array($result)) {
		$existing[] = $row['column_name'];
	}
	$missing = array_diff($required, $existing);
	// The master database has no wage-slip table; complete tenants need no DDL or lock.
	if (!$existing || !$missing) {
		return;
	}
	if (!db_lock('loen_enheder_schema')) {
		return;
	}
	try {
		foreach ($missing as $column) {
			if (!db_column_exists('loen_enheder', $column)) {
				db_modify("ALTER TABLE loen_enheder ADD COLUMN $column numeric(15,3)", __FILE__ . ' linje ' . __LINE__);
			}
		}
	} finally {
		db_unlock('loen_enheder_schema');
	}
}
