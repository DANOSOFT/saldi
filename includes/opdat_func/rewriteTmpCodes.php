<?php
// includes/opdat_func/rewriteTmpCodes.php — ver 5.1.1 — 2026.11.27
// Copyright (c) 2026 Danosoft ApS. GNU GPL version 2 or later.
// 20261127 ASR Created. brugere.tmp_kode values in the form "<type>|<expire>|<code>" are rewritten to the current forms of includes/tmpCode.php; installed by betweenUpdates.php.
// 20261127 ASR A temporary password in that form becomes "0|0|reset|<expire>|<code>" (tmp_code_storage_form()).

require_once __DIR__ . '/databaseDialect.php';
require_once __DIR__ . '/../tmpCode.php';

/**
 * Rewrite the brugere.tmp_kode values that are in the form "<type>|<expire>|<code>" to the current forms
 * (tmp_code_storage_form()): a temporary password becomes "0|0|reset|<expire>|<code>", which this code accepts until it
 * expires, an invitation "0|0|invite|<expire>|<hash>", and a two-factor code or a value without a numeric expiry is
 * cleared. A ledger then holds only values that every Saldi version reads the same way: a temporary password with its
 * expiry, or nothing a login accepts.
 *
 * Runs at every login and finds nothing once the values are rewritten; a value written in the earlier form later is
 * rewritten at the next login. Each row is updated only while it still holds the value read, so two logins at once
 * write the same result once. The field has existed since 3.3; a ledger without it is left alone. PostgreSQL and MySQL.
 *
 * @param string $dbType $db_type from includes/connect.php ('postgresql', 'mysql' or 'mysqli').
 * @return int the number of rows rewritten
 */
function rewriteTmpCodes(string $dbType): int
{
	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'brugere' AND column_name = 'tmp_kode'" . opdat_schema_filter($dbType);
	if (!db_fetch_array(db_select($qtxt, __FILE__ . ' linje ' . __LINE__))) {
		return 0;
	}
	$likes = array();
	foreach (tmp_code_types() as $type) {
		$likes[] = "tmp_kode LIKE '" . $type . "|%'";
	}
	$rows = array();
	$q = db_select("SELECT id, tmp_kode FROM brugere WHERE " . implode(' OR ', $likes), __FILE__ . ' linje ' . __LINE__);
	while ($r = db_fetch_array($q)) {
		$rows[] = $r;
	}
	$done = 0;
	foreach ($rows as $r) {
		$old = (string) $r['tmp_kode'];
		$new = tmp_code_storage_form($old);
		if ($new === $old) {
			continue;
		}
		$value = ($new === null) ? 'NULL' : "'" . db_escape_string($new) . "'";
		db_modify("UPDATE brugere SET tmp_kode = $value WHERE id = " . (int) $r['id'] . " AND tmp_kode = '" . db_escape_string($old) . "'", __FILE__ . ' linje ' . __LINE__);
		$done++;
	}
	return $done;
}
