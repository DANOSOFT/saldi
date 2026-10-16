<?php
// ---- includes/settings/grupperIndex.php --- lap 5.0.0 --- 2026.10.02 ---
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
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20261002 Sawaneh Settings redesign §7.2 + risk review R9: one grupper row per setting key for the settings arts.
//                  kode is trimmed (incl. NBSP), duplicates are merged (per box: the newest non-empty value wins),
//                  removed rows are kept in grupper_removed, then the partial unique index is added.
//                  Called from includes/betweenUpdates.php. PostgreSQL only.
// 20261002 Sawaneh Cannot stop a login: statements run through settings_mig_exec() (includes/settings/uniqueIndex.php)
//                  and are rolled back on failure; the index is looked for again once the table lock is held.

include_once(__DIR__ . '/uniqueIndex.php');

const GRUPPER_UNIQUE_INDEX = 'grupper_settings_key';

/**
 * Arts that hold settings, one row per key. Lists and master data are left out, and so are VV, HV,
 * DGV and SKN, which keep several rows per key by design (the user sits in box1, or rows are copies).
 * POS and OreDif have one row per fiscal year; KASKL and the list views have one row per kode.
 */
function grupper_settings_arts(): array
{
	return array(
		'DIV', 'API', 'MFAKT', 'PV', 'MAIL', 'bilag', 'FTP', 'LABEL', 'DebInfo', 'KredInfo', 'IMP', 'loen', 'SAGSTAT',
		'POS', 'OreDif',
		'USET', 'GF', 'KASKL', 'OLV', 'KOLV', 'DLV', 'KLV', 'DRV', 'KRV',
	);
}

/**
 * The key columns as SQL expressions, the same in the clean-up check and in the index.
 */
function grupper_key_sql(bool $kodenrIsInt): string
{
	$kodenr = $kodenrIsInt ? "coalesce(kodenr, 0)" : "coalesce(nullif(btrim(kodenr), ''), '0')";
	return "art, $kodenr, btrim(coalesce(kode, ''), ' ' || chr(160)), coalesce(fiscal_year, 0)";
}

/**
 * Which rows go and what the surviving row looks like. The newest row survives; per box1..box14
 * and beskrivelse the newest non-empty value of the key wins (R9).
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array{remove: array<int, int>, update: array<int, array<string, string>>}
 */
function grupper_duplicate_plan(array $rows): array
{
	// kode is already trimmed in the database (grupper_unique_migrate), so NULL and '' are the only blanks left.
	$groups = array();
	foreach ($rows as $row) {
		$key = implode("\x1f", array($row['art'], (string) (int) trim((string) $row['kodenr']), (string) $row['kode'], (int) $row['fiscal_year']));
		$groups[$key][] = $row;
	}
	$plan = array('remove' => array(), 'update' => array());
	$cols = array('beskrivelse');
	for ($i = 1; $i <= 14; $i++) {
		$cols[] = 'box' . $i;
	}
	foreach ($groups as $group) {
		if (count($group) < 2) {
			continue;
		}
		usort($group, function ($a, $b) {
			return (int) $b['id'] - (int) $a['id'];
		});
		$keep = $group[0];
		$keepId = (int) $keep['id'];
		$changes = array();
		foreach ($cols as $col) {
			if (!array_key_exists($col, $keep) || trim((string) $keep[$col]) !== '') {
				continue;
			}
			foreach ($group as $row) {
				if (isset($row[$col]) && trim((string) $row[$col]) !== '') {
					$changes[$col] = (string) $row[$col];
					break;
				}
			}
		}
		if ($changes) {
			$plan['update'][$keepId] = $changes;
		}
		foreach (array_slice($group, 1) as $row) {
			$plan['remove'][(int) $row['id']] = $keepId;
		}
	}
	return $plan;
}

/**
 * @return string 'exists', 'skipped', 'duplicates' (clean-up left duplicates, nothing changed), 'failed' (a statement
 *         failed, everything rolled back) or 'created'
 */
function grupper_unique_migrate(): string
{
	global $db_type;
	if ($db_type === 'mysql' || $db_type === 'mysqli') {
		return 'skipped';
	}
	if (db_fetch_array(db_select("select indexname from pg_indexes where tablename = 'grupper' and indexname = '" . GRUPPER_UNIQUE_INDEX . "'", __FILE__ . " linje " . __LINE__))) {
		return 'exists';
	}
	$types = array();
	$q = db_select("select column_name, data_type from information_schema.columns where table_name = 'grupper' and column_name in ('kodenr', 'kode', 'fiscal_year')", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$types[$r['column_name']] = $r['data_type'];
	}
	if (count($types) < 3) {
		return 'skipped';
	}
	$kodenrIsInt = in_array($types['kodenr'], array('integer', 'bigint', 'smallint'), true);
	$arts = "'" . implode("', '", grupper_settings_arts()) . "'";

	transaktion('begin');
	// A second login that arrives meanwhile waits here and then finds the index made.
	if (!settings_mig_exec("lock table grupper in share row exclusive mode")) {
		return settings_mig_fail();
	}
	if (db_fetch_array(db_select("select indexname from pg_indexes where tablename = 'grupper' and indexname = '" . GRUPPER_UNIQUE_INDEX . "'", __FILE__ . " linje " . __LINE__))) {
		transaktion('commit');
		return 'exists';
	}
	// Blank codes (space, NBSP) become '', word codes lose surrounding blanks.
	if (!settings_mig_exec("update grupper set kode = btrim(kode, ' ' || chr(160)) where art in ($arts) and kode is not null and kode <> btrim(kode, ' ' || chr(160))")) {
		return settings_mig_fail();
	}

	$rows = array();
	$q = db_select("select * from grupper where art in ($arts)", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$rows[] = $r;
	}
	$plan = grupper_duplicate_plan($rows);
	if ($plan['remove']) {
		if (!db_fetch_array(db_select("select table_name from information_schema.tables where table_name = 'grupper_removed'", __FILE__ . " linje " . __LINE__)) && !settings_mig_exec("create table grupper_removed as select *, 0 as kept_id, now() as removed from grupper where false")) {
			return settings_mig_fail();
		}
		foreach ($plan['remove'] as $removeId => $keepId) {
			if (!settings_mig_exec("insert into grupper_removed select *, $keepId, now() from grupper where id = $removeId")) {
				return settings_mig_fail();
			}
		}
	}
	foreach ($plan['update'] as $keepId => $changes) {
		$set = array();
		foreach ($changes as $col => $value) {
			$set[] = "$col = '" . db_escape_string($value) . "'";
		}
		if (!settings_mig_exec("update grupper set " . implode(', ', $set) . " where id = $keepId")) {
			return settings_mig_fail();
		}
	}
	if ($plan['remove'] && !settings_mig_exec("delete from grupper where id in (" . implode(',', array_keys($plan['remove'])) . ")")) {
		return settings_mig_fail();
	}
	// Nothing may collide when the index is made: if a duplicate is left, everything is put back and the next login tries again.
	$key = grupper_key_sql($kodenrIsInt);
	if (db_fetch_array(db_select("select $key, count(*) from grupper where art in ($arts) group by $key having count(*) > 1 limit 1", __FILE__ . " linje " . __LINE__))) {
		transaktion('rollback');
		return 'duplicates';
	}
	if (!settings_mig_exec("create unique index " . GRUPPER_UNIQUE_INDEX . " on grupper ($key) where art in ($arts)")) {
		return settings_mig_fail();
	}
	transaktion('commit');
	return 'created';
}
