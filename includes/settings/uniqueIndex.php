<?php
// ---- includes/settings/uniqueIndex.php --- lap 5.0.0 --- 2026.10.01 ---
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
// 20261001 Sawaneh Settings redesign §7.2 (phase 4a): one row per setting. Duplicates are merged and the
//                  unique index settings(var_grp, var_name, user_id, pos_id, group_id) is added, NULL counted
//                  as 0. Every removed row is kept in settings_removed. Called from includes/betweenUpdates.php.
// 20261002 Sawaneh The migration can no longer stop a login: its statements run without db_modify()'s alert-and-exit and
//                  are rolled back on failure; the index is looked for again once the table lock is held (two logins at once).

const SETTINGS_UNIQUE_INDEX = 'settings_key_unique';

/**
 * Run one statement of a login-time migration without db_modify()'s alert-and-exit: a migration that
 * fails must never keep people from logging in. Returns false on failure (logged to the PHP error log).
 */
function settings_mig_exec(string $qtxt): bool
{
	global $connection;
	$ok = @pg_query($connection, $qtxt);
	if (!$ok) {
		error_log('Saldi settings migration failed: ' . pg_last_error($connection) . ' | ' . $qtxt);
	}
	return (bool) $ok;
}

/**
 * Undo everything the migration did in this login and leave the data as it was.
 */
function settings_mig_fail(): string
{
	transaktion('rollback');
	return 'failed';
}

function settings_unique_index_exists(): bool
{
	$qtxt = "select indexname from pg_indexes where tablename = 'settings' and indexname = '" . SETTINGS_UNIQUE_INDEX . "'";
	return (bool) db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
}

function settings_key_of(array $row): string
{
	return implode("\x1f", array(
		(string) $row['var_grp'],
		(string) $row['var_name'],
		(int) $row['user_id'],
		(int) $row['pos_id'],
		(int) $row['group_id'],
	));
}

/**
 * What happens to each duplicate. As for grupper in risk review R9: a non-empty value wins, then the
 * newest row. Hour types are different: wage lines point at them by name, so the oldest keeps the name
 * and the others get unused names instead of being removed.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array{remove: array<int, int>, rename: array<int, string>, normalise: array<int, array<int, string>>}
 *         remove: removed id => kept id; rename: id => new var_name; normalise: kept id => scope columns
 *         to set from NULL to 0, because a removed row of the key had a value there and readers asking
 *         for 0 must still find the setting.
 */
function settings_duplicate_plan(array $rows): array
{
	$groups = array();
	$nextHourType = 0;
	foreach ($rows as $row) {
		$groups[settings_key_of($row)][] = $row;
		if ($row['var_grp'] === 'casePayment' && strpos((string) $row['var_name'], 'hourTypes') === 0) {
			$nextHourType = max($nextHourType, (int) substr((string) $row['var_name'], 9) + 1);
		}
	}
	$plan = array('remove' => array(), 'rename' => array(), 'normalise' => array());
	foreach ($groups as $group) {
		if (count($group) < 2) {
			continue;
		}
		if ($group[0]['var_grp'] === 'casePayment' && strpos((string) $group[0]['var_name'], 'hourTypes') === 0) {
			usort($group, function ($a, $b) {
				return (int) $a['id'] - (int) $b['id'];
			});
			array_shift($group);
			foreach ($group as $row) {
				$plan['rename'][(int) $row['id']] = 'hourTypes' . $nextHourType++;
			}
			continue;
		}
		usort($group, function ($a, $b) {
			$aFilled = trim((string) $a['var_value']) !== '';
			$bFilled = trim((string) $b['var_value']) !== '';
			if ($aFilled !== $bFilled) {
				return $aFilled ? -1 : 1;
			}
			return (int) $b['id'] - (int) $a['id'];
		});
		$keep = array_shift($group);
		$keepId = (int) $keep['id'];
		$columns = array();
		foreach (array('user_id', 'pos_id', 'group_id') as $col) {
			if ($keep[$col] !== null) {
				continue;
			}
			foreach ($group as $row) {
				if ($row[$col] !== null) {
					$columns[] = $col;
					break;
				}
			}
		}
		if ($columns) {
			$plan['normalise'][$keepId] = $columns;
		}
		foreach ($group as $row) {
			$plan['remove'][(int) $row['id']] = $keepId;
		}
	}
	return $plan;
}

/**
 * The kitchen screen kept its colours as several rows named 'color'. They become color_1, color_2...
 * (systemdata/diverse.php writes them so; the readers match var_name like 'color%').
 */
function settings_split_kds_colours(): bool
{
	$n = 0;
	$q = db_select("select var_name from settings where var_grp = 'KDS' and var_name like 'color_%'", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$n = max($n, (int) substr((string) $r['var_name'], 6));
	}
	$ids = array();
	$q = db_select("select id from settings where var_grp = 'KDS' and var_name = 'color' order by id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ids[] = (int) $r['id'];
	}
	foreach ($ids as $id) {
		$n++;
		if (!settings_mig_exec("update settings set var_name = 'color_$n' where id = $id")) {
			return false;
		}
	}
	return true;
}

/**
 * Merge duplicates and add the unique index, once per company. PostgreSQL only: the MySQL versions
 * Saldi supports have no index on expressions, so MySQL is left as it is.
 *
 * @return string 'exists', 'skipped', 'duplicates' (clean-up left duplicates, nothing changed), 'failed' (a statement
 *         failed, everything rolled back) or 'created'
 */
function settings_unique_migrate(): string
{
	global $db_type;
	if ($db_type === 'mysql' || $db_type === 'mysqli') {
		return 'skipped';
	}
	if (settings_unique_index_exists()) {
		return 'exists';
	}
	$qtxt = "select count(*) as antal from information_schema.columns where table_name = 'settings' and column_name in ('var_grp', 'user_id', 'pos_id', 'group_id')";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (!$r || (int) $r['antal'] < 4) {
		return 'skipped';
	}
	transaktion('begin');
	// Nobody may add a duplicate between the clean-up and the index. A second login that arrives meanwhile
	// waits here and then finds the index made.
	if (!settings_mig_exec("lock table settings in share row exclusive mode")) {
		return settings_mig_fail();
	}
	if (settings_unique_index_exists()) {
		transaktion('commit');
		return 'exists';
	}
	if (!settings_split_kds_colours()) {
		return settings_mig_fail();
	}
	$rows = array();
	$q = db_select("select id, var_grp, var_name, var_value, user_id, pos_id, group_id from settings", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$rows[] = $r;
	}
	$plan = settings_duplicate_plan($rows);
	foreach ($plan['rename'] as $id => $name) {
		if (!settings_mig_exec("update settings set var_name = '" . db_escape_string($name) . "' where id = $id")) {
			return settings_mig_fail();
		}
	}
	if ($plan['remove']) {
		$qtxt = "select table_name from information_schema.tables where table_name = 'settings_removed'";
		if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)) && !settings_mig_exec("create table settings_removed (id integer, var_name text, var_grp text, var_value text, var_description text, user_id integer, pos_id integer, group_id integer, kept_id integer, removed timestamp)")) {
			return settings_mig_fail();
		}
		foreach ($plan['remove'] as $removeId => $keepId) {
			if (!settings_mig_exec("insert into settings_removed (id, var_name, var_grp, var_value, var_description, user_id, pos_id, group_id, kept_id, removed) select id, var_name, var_grp, var_value, var_description, user_id, pos_id, group_id, $keepId, now() from settings where id = $removeId")) {
				return settings_mig_fail();
			}
		}
		if (!settings_mig_exec("delete from settings where id in (" . implode(',', array_keys($plan['remove'])) . ")")) {
			return settings_mig_fail();
		}
	}
	foreach ($plan['normalise'] as $keepId => $columns) {
		$set = array();
		foreach ($columns as $col) {
			$set[] = "$col = 0";
		}
		if (!settings_mig_exec("update settings set " . implode(', ', $set) . " where id = $keepId")) {
			return settings_mig_fail();
		}
	}
	// Nothing may collide when the index is made: if a duplicate is left, everything is put back and the next login tries again.
	$key = "coalesce(var_grp, ''), coalesce(var_name, ''), coalesce(user_id, 0), coalesce(pos_id, 0), coalesce(group_id, 0)";
	if (db_fetch_array(db_select("select $key, count(*) from settings group by $key having count(*) > 1 limit 1", __FILE__ . " linje " . __LINE__))) {
		transaktion('rollback');
		return 'duplicates';
	}
	if (!settings_mig_exec("create unique index " . SETTINGS_UNIQUE_INDEX . " on settings ((coalesce(var_grp, '')), (coalesce(var_name, '')), (coalesce(user_id, 0)), (coalesce(pos_id, 0)), (coalesce(group_id, 0)))")) {
		return settings_mig_fail();
	}
	transaktion('commit');
	return 'created';
}
