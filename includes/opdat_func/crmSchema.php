<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_func/crmSchema.php --- ver 5.1.1 --- 2026.11.11 ---
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
// 20261110 CDX/ASR Initialize CRM through tenant updates and installation, with a tenant lock and resumable index creation.
// 20261110 CDX/ASR Detect failed writes before recording the migration flag, and let tenant creation compensate failures.
// 20261111 CDX/ASR Install isolated CRM notifications with per-recipient uniqueness and resumable indexes.

/**
 * Fail explicitly on a migration write, including callers such as signup that need to clean up.
 * db_modify() treats sources containing "includes/opdat" as old migrations and reports failed writes as success.
 * The intentional diagnostic label below avoids that legacy path; error-return mode prevents an early exit.
 */
function crm_schema_modify(string $sql, int $line): void
{
	$hadWebservice = array_key_exists('webservice', $GLOBALS);
	$previousWebservice = $GLOBALS['webservice'] ?? null;
	$GLOBALS['webservice'] = true;
	try {
		$result = db_modify($sql, 'CRM schema migration: crmSchema.php line ' . $line);
		if (strncmp((string) $result, "0\t", 2) !== 0) {
			throw new RuntimeException('CRM schema migration failed.');
		}
	} finally {
		if ($hadWebservice) {
			$GLOBALS['webservice'] = $previousWebservice;
		} else {
			unset($GLOBALS['webservice']);
		}
	}
}

/**
 * Build or upgrade the CRM schema in the current tenant during login/install only.
 * The caller owns any transaction (new-tenant creation already has one).
 * A session lock serializes checks and changes without committing the caller's work.
 *
 * @param string $dbType Driver from includes/connect.php.
 * @return bool False when the base tenant tables are missing or the MySQL lock timed out; a later login retries.
 */
function crm_ensure_schema(string $dbType): bool
{
	$mysql = in_array($dbType, array('mysql', 'mysqli'), true);
	$hasColumn = static function (string $table, string $column) use ($mysql): bool {
		$table = db_escape_string($table);
		$column = db_escape_string($column);
		$scope = $mysql ? 'table_schema = DATABASE()' : 'table_schema = current_schema()';
		return (bool) db_fetch_array(db_select("select column_name from information_schema.columns where $scope and table_name = '$table' and column_name = '$column'", __FILE__ . ' linje ' . __LINE__));
	};
	if (!$hasColumn('historik', 'id') || !$hasColumn('settings', 'id')) {
		return false;
	}
	if ($mysql) {
		// MySQL locks are server-wide; derive their scope from the active connection, not a caller's global $db.
		$lockName = "concat('saldi_crm_', md5(DATABASE()))";
		$locked = db_fetch_array(db_select("select GET_LOCK($lockName, 30) as acquired", __FILE__ . ' linje ' . __LINE__));
		if ((int) ($locked['acquired'] ?? 0) !== 1) {
			return false;
		}
		$unlock = "select RELEASE_LOCK($lockName)";
	} else {
		db_select("select pg_advisory_lock(hashtext('saldi_crm_schema'), hashtext(current_schema()))", __FILE__ . ' linje ' . __LINE__);
		$unlock = "select pg_advisory_unlock(hashtext('saldi_crm_schema'), hashtext(current_schema()))";
	}
	try {
		if (!$hasColumn('historik', 'completed')) {
			crm_schema_modify("ALTER TABLE historik ADD COLUMN completed boolean", __LINE__);
		}
		if (!$hasColumn('historik', 'type')) {
			crm_schema_modify("ALTER TABLE historik ADD COLUMN type varchar(10)", __LINE__);
		}
		if (!$hasColumn('crm_deals', 'id')) {
			if ($mysql) {
				crm_schema_modify("CREATE TABLE crm_deals (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					konto_id INT NOT NULL,
					title VARCHAR(120) NOT NULL,
					amount DECIMAL(15,2) DEFAULT 0,
					stage VARCHAR(10),
					expected_close DATE NULL,
					ansat_id INT NULL,
					state VARCHAR(10) NOT NULL DEFAULT 'open',
					stage_since DATE NULL,
					created DATE NULL,
					closed DATE NULL,
					lost_reason TEXT,
					INDEX crm_deals_konto_idx (konto_id)
				)", __LINE__);
			} else {
				crm_schema_modify("CREATE TABLE crm_deals (
					id SERIAL PRIMARY KEY,
					konto_id integer NOT NULL,
					title varchar(120) NOT NULL,
					amount numeric(15,2) DEFAULT 0,
					stage varchar(10),
					expected_close date,
					ansat_id integer,
					state varchar(10) NOT NULL DEFAULT 'open',
					stage_since date,
					created date,
					closed date,
					lost_reason text
				)", __LINE__);
			}
		}
		// 20261020 ASR the offer/order made from a deal (ordre.php?..&crm_deal=<id> sets it at creation)
		if (!$hasColumn('crm_deals', 'ordre_id')) {
			crm_schema_modify("ALTER TABLE crm_deals ADD COLUMN ordre_id integer", __LINE__);
		}
		// 20261024 ASR review R4: who wrote the entry (ansat_id stays the follow-up's owner, as the old pages read it)
		if (!$hasColumn('historik', 'author_id')) {
			crm_schema_modify("ALTER TABLE historik ADD COLUMN author_id integer", __LINE__);
		}
		// 20261026 ASR review R13/R16: when a follow-up was marked done (the "done in the last 14 days" list, the timeline)
		if (!$hasColumn('historik', 'completed_at')) {
			crm_schema_modify("ALTER TABLE historik ADD COLUMN completed_at date", __LINE__);
		}
		// 20261029 ASR meetings: the start time and length of a follow-up of type moede (historik.kontaktes is its date),
		// whether calendar invitations went out, the colleagues on a meeting ('h') or a deal ('d'), the personal calendar feed
		if (!$hasColumn('crm_meetings', 'historik_id')) {
			crm_schema_modify("CREATE TABLE crm_meetings (historik_id integer NOT NULL PRIMARY KEY, start_time varchar(5) NOT NULL, duration integer NOT NULL DEFAULT 60, "
				. "seq integer NOT NULL DEFAULT 0, invite_team integer NOT NULL DEFAULT 0, invite_contact integer NOT NULL DEFAULT 0)", __LINE__);
		}
		if (!$hasColumn('crm_participants', 'ref_id')) {
			if ($mysql) {
				crm_schema_modify("CREATE TABLE crm_participants (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(1) NOT NULL, ref_id INT NOT NULL, ansat_id INT NOT NULL, "
					. "INDEX crm_participants_ref_idx (kind, ref_id), INDEX crm_participants_ansat_idx (ansat_id))", __LINE__);
			} else {
				crm_schema_modify("CREATE TABLE crm_participants (id SERIAL PRIMARY KEY, kind varchar(1) NOT NULL, ref_id integer NOT NULL, ansat_id integer NOT NULL)", __LINE__);
			}
		}
		if (!$hasColumn('crm_ical', 'token')) {
			crm_schema_modify("CREATE TABLE crm_ical (bruger_id integer NOT NULL PRIMARY KEY, token varchar(64) NOT NULL, created date)", __LINE__);
		}
		if (!$hasColumn('crm_notifications', 'id')) {
			$idColumn = $mysql ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'SERIAL PRIMARY KEY';
			crm_schema_modify("CREATE TABLE crm_notifications (id $idColumn, event_key varchar(160) NOT NULL, bruger_id integer NOT NULL, "
				. "title text, body text, url text, created date, expires date, seen boolean NOT NULL DEFAULT false)", __LINE__);
		}
		// Resume an interrupted run even if a table was committed before its indexes were created.
		foreach (array(
			'historik_konto_idx' => array('historik', 'konto_id'),
			'crm_deals_konto_idx' => array('crm_deals', 'konto_id'),
			'crm_participants_ref_idx' => array('crm_participants', 'kind, ref_id'),
			'crm_participants_ansat_idx' => array('crm_participants', 'ansat_id'),
			'crm_notifications_event_user_idx' => array('crm_notifications', 'event_key, bruger_id', 'UNIQUE '),
			'crm_notifications_user_idx' => array('crm_notifications', 'bruger_id'),
		) as $index => $spec) {
			[$table, $columns] = $spec;
			$unique = $spec[2] ?? '';
			$query = $mysql ? "show index from $table where Key_name = '$index'"
				: "select indexname from pg_indexes where schemaname = current_schema() and tablename = '$table' and indexname = '$index'";
			if (!db_fetch_array(db_select($query, __FILE__ . ' linje ' . __LINE__))) {
				crm_schema_modify("CREATE {$unique}INDEX $index ON $table ($columns)", __LINE__);
			}
		}
		crm_migrate_legacy_followups($mysql);
		return true;
	} finally {
		db_select($unlock, __FILE__ . ' linje ' . __LINE__);
	}
}

/**
 * Retain the legacy rule: only the newest dated history entry stays open.
 * Older rows with NULL completed become explicit TRUE once; later customer edits are left alone.
 * Called under crm_ensure_schema()'s lock after the columns exist.
 */
function crm_migrate_legacy_followups(bool $mysql): void
{
	$flag = db_fetch_array(db_select("select var_value from settings where var_name = 'crm_legacy_followups' and var_grp = 'crm'", __FILE__ . ' linje ' . __LINE__));
	if ($flag && (string) $flag['var_value'] === '1') {
		return;
	}
	if ($mysql) {
		crm_schema_modify("update historik h join (select konto_id, max(id) as m from historik where kontaktes is not null group by konto_id) x on x.konto_id = h.konto_id "
			. "set h.completed = TRUE where h.completed is null and h.kontaktes is not null and h.id <> x.m", __LINE__);
	} else {
		crm_schema_modify("update historik h set completed = TRUE where h.completed is null and h.kontaktes is not null "
			. "and h.id <> (select max(h2.id) from historik h2 where h2.konto_id = h.konto_id and h2.kontaktes is not null)", __LINE__);
	}
	if ($flag) {
		crm_schema_modify("update settings set var_value = '1' where var_name = 'crm_legacy_followups' and var_grp = 'crm'", __LINE__);
	} else {
		crm_schema_modify("insert into settings (var_name, var_grp, var_value, var_description) values ('crm_legacy_followups', 'crm', '1', 'CRM: legacy follow-ups made explicit (review R22)')", __LINE__);
	}
}
