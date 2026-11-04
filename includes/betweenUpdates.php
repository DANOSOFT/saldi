<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- ver 5.1.0 --- 2026.11.04
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261019 CL/LH MB-73: default 0 for ordrelinjer.leveret.
//                One-time backfill of NULL leveret on sales, credit note and POS lines from their batch_salg delivery records.
// 20261020 CL/NTR Convert brugere.ansat_id to integer (nullable, no default) on PostgreSQL and MySQL tenants where it is text/varchar.
// 20261027 CL/LH MB-73 (review): the leveret backfill runs in id windows under a try-lock with a time budget per
//                login (includes/opdat_func/backfillOrderLineDelivered.php) instead of one UPDATE at the first login.
//                The SET DEFAULT check also matches MariaDB, which reports a missing default as the text 'NULL'.
// 20261027 NTR New Release - Moved everything into opdat_5.0.php
// 20261028 CL/LH MB-72: One-time backfill of openpost.udlign_date for settled groups that have none.
// 20261028 CDX/NTR Apply the tekst_key schema migration to both tenant and master databases.
// 20261031 CL/ASR The redesign's migrations for the settings system, users and roles, the audit log and the notification center are appended after master's blocks; they were never in opdat_5.0.php. audit_log is created here too, with opdat_5.0.php's schema, before they add columns to it: a company created at 5.1.0 never runs opdat_5.0.php.
//                 A sentinel comment marks the end of the tekst_key migration, where TekstKeyMigrationTest stops reading.
// 20261031 CL/ASR Texts 7031 (personal text size) and 7021 (checklists lead) reworded; the cached old wording is removed.
// 20261102 CL/ASR Self-service signup (signup/, doc/signup.md): its master-database tables and the trial columns on regnskab, moved here from the flow's own first-use setup.
//                 Serialized with an advisory lock; a settings flag (system/signup_schema) marks it done.
// 20261103 CL/ASR QA: the audit_log indexes on objekt_type/objekt_id are made after all three columns exist (and made later where an earlier login stopped before them); the role tables, the built-in roles and the user migration run under a PostgreSQL advisory lock, so two first logins at once cannot seed the roles twice.
// 20261103 CL/ASR QA round 2: the built-in Kun visning role loses its dangerous keys (settings.backup) once, and role rows for keys no longer in the registry are removed.
// 20261104 CL/ASR The batch/expiry tools moved to Varerapport: the built-in Lager role gets Se Oversigt, the expiry warning and news, and the built-in Indkøb role loses the expiry warning, once and only where their Oversigt keys are still the old defaults.
// 20261104 CL/ASR QA round 3: the PostgreSQL advisory lock now also covers the settings migrations up to the stale texts, so two first logins at once cannot insert the same settings flag twice.
//                 The one-time role changes at the end of the file run under the same lock, check their flag again once they hold it, and run in one transaction with their flag.
//                 Text 7815 (the restore's warning about a backup of another ledger) is reworded; the cached old wording is removed.
// 20261104 CL/ASR QA round 3: the till account per currency is copied once from the tab list in grupper VK box4 to the settings rows VK/till_account_<currency> per till (includes/opdat_func/migrateTillAccounts.php), so switching the currency on in the till no longer loses it.
//                 box4 is left as it is, and a till that already has a row for the currency keeps it.

/**
 * Injected by includes/connect.php via the entry page that includes this file:
 * @var string $db_type
 */

// ===== PROTECTED: when a version is cut (see doc/ai/convention_database_changes.md), COPY this =====
// ===== segment into the opdat_<major>.<minor>.php file - do not move it. The moved statements =====
// ===== call these helpers, and this file keeps needing them for the next release. =====
include_once(__DIR__ . '/opdat_func/deleteStaleTekst.php');
// ===== END PROTECTED =====

// 20261019 CL/LH MB-72: automatic settlement (equalizeMatchingRecords() and the journal's openpost())
// closed open posts without a settlement date. Each settled group still missing one gets the latest
// transaction date in the group, which is what both now write. A group is an account plus its
// udlign_id, since autoudlign reuses small ids across accounts. Rows settled without an udlign_id
// cannot be grouped and are left alone, as are groups where no row has a transaction date.
// One-time, gated by a settings flag; the update only touches udlignet = '1' rows whose udlign_date
// is NULL, so a second run (or a concurrent login) changes nothing.
$udlignDateBackfilled = db_fetch_array(db_select(
	"SELECT var_value FROM settings WHERE var_name = 'openpost_udlign_date_backfilled' AND var_grp = 'system'",
	__FILE__ . " linje " . __LINE__
));
if (!$udlignDateBackfilled) {
	$udlignGroups = "SELECT konto_id, udlign_id, MAX(transdate) AS max_date FROM openpost";
	$udlignGroups.= " WHERE udlignet = '1' AND udlign_id > 0 GROUP BY konto_id, udlign_id";
	$udlignGroups.= " HAVING MAX(transdate) IS NOT NULL AND COUNT(*) > COUNT(udlign_date)";
	if (in_array($db_type, ['mysql', 'mysqli'], true)) {
		// MySQL has no UPDATE ... FROM; the grouped derived table is materialized, so it may read openpost.
		$qtxt = "UPDATE openpost JOIN ($udlignGroups) udlign_group";
		$qtxt.= " ON openpost.konto_id = udlign_group.konto_id AND openpost.udlign_id = udlign_group.udlign_id";
		$qtxt.= " SET openpost.udlign_date = udlign_group.max_date";
		$qtxt.= " WHERE openpost.udlignet = '1' AND openpost.udlign_date IS NULL";
	} else {
		$qtxt = "UPDATE openpost SET udlign_date = udlign_group.max_date FROM ($udlignGroups) udlign_group";
		$qtxt.= " WHERE openpost.konto_id = udlign_group.konto_id AND openpost.udlign_id = udlign_group.udlign_id";
		$qtxt.= " AND openpost.udlignet = '1' AND openpost.udlign_date IS NULL";
	}
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	db_modify(
		"INSERT INTO settings (var_name, var_grp, var_value, var_description)
		VALUES ('openpost_udlign_date_backfilled', 'system', 'yes', 'One-time backfill of openpost.udlign_date for settled groups that had none')",
		__FILE__ . " linje " . __LINE__
	);
}

// 20261022 CL/NTR tekster gets a descriptive string key (tekst_key) alongside the legacy numeric
// tekst_id, so findtekst() can be called with keys like 'common.accept'. Nullable, added as a
// separate column rather than retyping tekst_id. See doc/ai/convention_findtekst_keys.md.
$tekstKeyMysql = in_array($db_type, ['mysql', 'mysqli'], true);
$tekstKeyTargets = [false];
if (isset($GLOBALS['db'], $GLOBALS['sqdb']) && $GLOBALS['db'] !== $GLOBALS['sqdb']) {
	// db_select()/db_modify() route global=true to the shared master database.
	$tekstKeyTargets[] = true;
}
foreach ($tekstKeyTargets as $tekstKeyGlobal) {
	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='tekster' AND column_name='tekst_key'";
	$qtxt .= $tekstKeyMysql ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, $tekstKeyGlobal))) {
		if ($tekstKeyMysql) {
			// MySQL has no ADD COLUMN IF NOT EXISTS. Serialize this migration per database.
			$tekstKeyLock = "CONCAT('saldi:tekst_key:', MD5(DATABASE()))";
			$lockResult = db_fetch_array(db_select("SELECT GET_LOCK($tekstKeyLock, 30) AS acquired", __FILE__ . " linje " . __LINE__, $tekstKeyGlobal));
			if ((int) ($lockResult['acquired'] ?? 0) !== 1) {
				throw new RuntimeException('Could not acquire the tekst_key migration lock.');
			}
			try {
				// Another login may have added the column while this connection waited.
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, $tekstKeyGlobal))) {
					db_modify("ALTER TABLE tekster ADD COLUMN tekst_key VARCHAR(190)", __FILE__ . " linje " . __LINE__, $tekstKeyGlobal);
				}
			} finally {
				db_select("SELECT RELEASE_LOCK($tekstKeyLock)", __FILE__ . " linje " . __LINE__, $tekstKeyGlobal);
			}
		} else {
			db_modify("ALTER TABLE tekster ADD COLUMN IF NOT EXISTS tekst_key VARCHAR(190)", __FILE__ . " linje " . __LINE__, $tekstKeyGlobal);
		}
	}

	// Composite index for findtekst() key lookups (WHERE tekst_key = ... AND sprog_id = ...).
	if ($tekstKeyMysql) {
		$qtxt = "SELECT index_name FROM information_schema.statistics WHERE table_name='tekster' AND index_name='idx_tekster_sprog_key' AND table_schema = DATABASE()";
		if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, $tekstKeyGlobal))) {
			$tekstKeyIdxLock = "CONCAT('saldi:tekst_key_idx:', MD5(DATABASE()))";
			$idxLockResult = db_fetch_array(db_select("SELECT GET_LOCK($tekstKeyIdxLock, 30) AS acquired", __FILE__ . " linje " . __LINE__, $tekstKeyGlobal));
			if ((int) ($idxLockResult['acquired'] ?? 0) !== 1) {
				throw new RuntimeException('Could not acquire the tekst_key index migration lock.');
			}
			try {
				if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, $tekstKeyGlobal))) {
					db_modify("CREATE INDEX idx_tekster_sprog_key ON tekster(sprog_id, tekst_key)", __FILE__ . " linje " . __LINE__, $tekstKeyGlobal);
				}
			} finally {
				db_select("SELECT RELEASE_LOCK($tekstKeyIdxLock)", __FILE__ . " linje " . __LINE__, $tekstKeyGlobal);
			}
		}
	} else {
		db_modify("CREATE INDEX IF NOT EXISTS idx_tekster_sprog_key ON tekster(sprog_id, tekst_key)", __FILE__ . " linje " . __LINE__, $tekstKeyGlobal);
	}
}

// ===== end of the tekst_key migration (TekstKeyMigrationTest stops here) =====

// 20261002 Sawaneh Text 5646 (no active fiscal year) named the old "System » Indstillinger" menu. Rows still holding the
// old wording are removed so findtekst() re-seeds them from tekster.csv; edited texts are left alone.
foreach (array('Aktivér et regnskabsår under System » Indstillinger » Regnskabsår', 'Activate a fiscal year under System » Settings » Fiscal year', 'Aktiver et regnskapsår under System » Innstillinger » Regnskapsår') as $gammel5646) {
	db_modify("delete from tekster where tekst_id = '5646' and tekst = '" . db_escape_string($gammel5646) . "'", __FILE__ . " linje " . __LINE__);
}

// 20261016 Sawaneh Review (PR #803): every information_schema check of ours filters on the schema, as master's newer
//                  blocks do: on MySQL the view spans every database the user can see, so the first tenant's tables
//                  would hide the others' missing ones.
$sawanehSchema = in_array($db_type, array('mysql', 'mysqli'), true) ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";

// 20260916 Sawaneh Roles & permissions (user/settings system spec, part 2): roles,
// role_permissions, brugere.role_id and the audit log. Built-in roles are seeded and users
// holding every legacy right get the Administrator role; see includes/permissions.php.
$sawanehMysql = in_array($db_type, array('mysql', 'mysqli'), true);
$sawanehId = $sawanehMysql ? 'id INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'id SERIAL PRIMARY KEY NOT NULL';
$sawanehNow = $sawanehMysql ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'timestamp DEFAULT now()';
// One login at a time creates the role tables, seeds the built-in roles and migrates the users (released after the settings migrations below).
// A session lock: it also ends with the connection if a statement stops the login.
if (!$sawanehMysql) {
	db_select("select pg_advisory_lock(hashtext('saldi_roles_migration'))", __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='roles'" . $sawanehSchema;
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE roles (
		$sawanehId,
		role_key varchar(30),
		navn varchar(80) NOT NULL,
		beskrivelse text,
		system boolean DEFAULT false,
		oprettet $sawanehNow)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='role_permissions'" . $sawanehSchema;
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE role_permissions (
		role_id integer NOT NULL,
		permission_key varchar(60) NOT NULL,
		level varchar(5) NOT NULL,
		PRIMARY KEY (role_id, permission_key))";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='brugere' AND column_name='role_id'" . $sawanehSchema;
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER TABLE brugere ADD COLUMN role_id integer", __FILE__ . " linje " . __LINE__);
}

// 20261016 Sawaneh Review (PR #803): the table is created by master's SD-724 block just above (both engines, DATETIME on
//                  MySQL); our create, Postgres-only, is gone. The column additions below still run on older tables.
// 20261031 CL/ASR Depends on audit_log. Master's SD-724 create (20261003/20261004 CL/SZ) moved to includes/opdat_5.0.php at
//                 the 5.1.0 cut, and that file never runs for a company created at 5.1.0 or later (includes/opretfunc.php
//                 stamps the current version and creates no audit_log), so the ALTER TABLE audit_log below would stop its
//                 login. The same create, with opdat_5.0.php's schema, is repeated here; it is skipped where the table exists.
$auditLogMysql = in_array($db_type, ['mysql', 'mysqli'], true);
$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_name = 'audit_log'";
$qtxt .= $auditLogMysql ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	if ($auditLogMysql) {
		db_modify("CREATE TABLE IF NOT EXISTS audit_log (
			id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
			tidspunkt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			bruger_id INTEGER,
			brugernavn VARCHAR(80),
			handling VARCHAR(60) NOT NULL,
			objekt_type VARCHAR(30),
			objekt_id VARCHAR(60),
			detaljer TEXT,
			ip VARCHAR(45),
			kilde VARCHAR(30),
			INDEX audit_log_tidspunkt_idx (tidspunkt),
			INDEX audit_log_bruger_idx (bruger_id),
			INDEX audit_log_objekt_idx (objekt_type, objekt_id)
		)", __FILE__ . " linje " . __LINE__);
	} else {
		db_modify("CREATE TABLE IF NOT EXISTS audit_log (
			id SERIAL PRIMARY KEY,
			tidspunkt TIMESTAMP NOT NULL DEFAULT now(),
			bruger_id INTEGER,
			brugernavn VARCHAR(80),
			handling VARCHAR(60) NOT NULL,
			objekt_type VARCHAR(30),
			objekt_id VARCHAR(60),
			detaljer TEXT,
			ip VARCHAR(45),
			kilde VARCHAR(30)
		)", __FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX IF NOT EXISTS audit_log_tidspunkt_idx ON audit_log (tidspunkt)", __FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX IF NOT EXISTS audit_log_bruger_idx ON audit_log (bruger_id)", __FILE__ . " linje " . __LINE__);
		db_modify("CREATE INDEX IF NOT EXISTS audit_log_objekt_idx ON audit_log (objekt_type, objekt_id)", __FILE__ . " linje " . __LINE__);
	}
}

include_once(__DIR__ . "/permissions.php");
perm_ensure_default_roles();

// 20260927 Sawaneh Notification center (topbar spec §3.2): notifications + per-user read state.
// 20261002 Sawaneh Most companies already hold an older, unused table of the same name (id, msg, read_status; from
// admin/opret.php and opdat_4.0.php, all its readers are commented out). The new table is recognised by its 'type'
// column; the old one is kept under another name, so nothing is deleted.
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='notifications' and column_name='type'" . $sawanehSchema;
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='notifications'" . $sawanehSchema;
	if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$legacyName = 'notifications_legacy';
		if (db_fetch_array(db_select("SELECT column_name FROM information_schema.columns WHERE table_name='$legacyName'" . $sawanehSchema, __FILE__ . " linje " . __LINE__))) {
			$legacyName .= '_' . date('YmdHis');
		}
		db_modify("ALTER TABLE notifications RENAME TO $legacyName", __FILE__ . " linje " . __LINE__);
		if ($db_type != 'mysql' && $db_type != 'mysqli') {
			// The old key and sequence keep the names the new table wants.
			if (!$sawanehMysql) {
				db_modify("ALTER INDEX IF EXISTS notifications_pkey RENAME TO {$legacyName}_pkey", __FILE__ . " linje " . __LINE__);
				db_modify("ALTER SEQUENCE IF EXISTS notifications_id_seq RENAME TO {$legacyName}_id_seq", __FILE__ . " linje " . __LINE__);
			}
		}
	}
	$qtxt = "CREATE TABLE notifications (
		$sawanehId,
		user_id integer,
		type varchar(30) NOT NULL,
		title text,
		body text,
		link text,
		created $sawanehNow,
		expires date,
		source_key varchar(80))";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	if ($db_type != 'mysql' && $db_type != 'mysqli') {
		db_modify("DROP INDEX IF EXISTS notifications_user_idx", __FILE__ . " linje " . __LINE__);
	}
	db_modify("CREATE INDEX notifications_user_idx ON notifications (user_id, created)", __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='notification_read'" . $sawanehSchema;
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE notification_read (
		notification_id integer NOT NULL,
		user_id integer NOT NULL,
		read_at $sawanehNow,
		PRIMARY KEY (notification_id, user_id))";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

// 20260929 Sawaneh Settings redesign phase 4a: the change history of a settings section (spec §8.12)
// reads setting_key / section / old_value / new_value from audit_log, and the PoS licence becomes a
// real flag (decision 9): set to 'on' where the installation has the PoS module today.
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='audit_log' and column_name='setting_key'" . $sawanehSchema;
// Each column guarded on its own (review, PR #803): a login that died after the first ALTER must not leave the rest out.
foreach (array('setting_key' => 'varchar(120)', 'section' => 'varchar(80)', 'old_value' => 'text', 'new_value' => 'text') as $auditCol => $auditType) {
	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='audit_log' and column_name='$auditCol'" . $sawanehSchema;
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER TABLE audit_log ADD COLUMN $auditCol $auditType", __FILE__ . " linje " . __LINE__);
		if ($auditCol === 'section') {
			db_modify("CREATE INDEX audit_log_section_idx ON audit_log (section, id)", __FILE__ . " linje " . __LINE__);
		}
	}
}
$qtxt = "select id from settings where var_grp = 'system' and var_name = 'pos_licence'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)) && file_exists(__DIR__ . "/../debitor/pos_ordre.php")) {
	db_modify("insert into settings (var_grp, var_name, var_value, var_description, user_id) values ('system', 'pos_licence', 'on', 'PoS licence flag, set by the operator', 0)", __FILE__ . " linje " . __LINE__);
}

// 20260929 Sawaneh Roles stage 2 (Requirements_roles_stage2_EN.md §3): the audit log records what was
// changed (objekt_type, objekt_id) and from where (kilde); brugere.status is taken into use (closed users).
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='audit_log' and column_name='objekt_type'" . $sawanehSchema;
foreach (array('objekt_type' => 'varchar(30)', 'objekt_id' => 'varchar(60)', 'kilde' => 'varchar(30)') as $auditCol => $auditType) {
	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='audit_log' and column_name='$auditCol'" . $sawanehSchema;
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER TABLE audit_log ADD COLUMN $auditCol $auditType", __FILE__ . " linje " . __LINE__);
	}
}
// The indexes once all three columns exist (the objekt_id index cannot be made before its column).
foreach (array('audit_log_bruger_idx' => 'bruger_id', 'audit_log_objekt_idx' => 'objekt_type, objekt_id') as $auditIdx => $auditIdxCols) {
	$qtxt = $sawanehMysql
		? "SELECT index_name FROM information_schema.statistics WHERE table_name = 'audit_log' AND index_name = '$auditIdx' AND table_schema = DATABASE()"
		: "SELECT indexname FROM pg_indexes WHERE tablename = 'audit_log' AND indexname = '$auditIdx' AND schemaname = current_schema()";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		db_modify("CREATE INDEX $auditIdx ON audit_log ($auditIdxCols)", __FILE__ . " linje " . __LINE__);
	}
}
// 20261003 Sawaneh Aligned with the roles spec §3 so the document-pool work (SD-724) shares the table: handling 60 wide,
// tidspunkt not null. Runs once; a table made by SD-724 already looks like this.
$qtxt = "SELECT character_maximum_length AS character_maximum_length FROM information_schema.columns WHERE table_name='audit_log' and column_name='handling'" . $sawanehSchema;
// PostgreSQL syntax: only a table from before SD-724 can be narrower, and such a table only exists on PostgreSQL.
if (!$sawanehMysql && ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) && (int) $r['character_maximum_length'] > 0 && (int) $r['character_maximum_length'] < 60) {
	db_modify("ALTER TABLE audit_log ALTER COLUMN handling TYPE varchar(60)", __FILE__ . " linje " . __LINE__);
	db_modify("UPDATE audit_log SET tidspunkt = now() WHERE tidspunkt IS NULL", __FILE__ . " linje " . __LINE__);
	db_modify("ALTER TABLE audit_log ALTER COLUMN tidspunkt SET NOT NULL", __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='brugere' and column_name='status'" . $sawanehSchema;
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER TABLE brugere ADD COLUMN status boolean", __FILE__ . " linje " . __LINE__);
}

// 20260930 Sawaneh Roles stage 2 (§6.2): every user without a role gets one; other rights patterns become
// custom roles, kept for review on the dashboard. Last in the file, after the audit_log columns exist.
perm_migrate_users();

// 20260930 Sawaneh Audit log for administrators only (Adam 2026-09-30): the read level the built-in Bogholder and
// Revisor roles got on 2026-09-30 is taken back once. Custom roles are left as their administrator set them.
$qtxt = "select id from settings where var_grp = 'permissions' and var_name = 'audit_admin_only'";
if (perm_tables_ready() && !db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("delete from role_permissions where permission_key = 'settings.audit.read' and role_id in (select id from roles where role_key in ('bogholder', 'revisor'))", __FILE__ . " linje " . __LINE__);
	db_modify("insert into settings (var_grp, var_name, var_value, var_description, user_id) values ('permissions', 'audit_admin_only', '" . date('Ymd') . "', 'Audit log read removed from Bogholder and Revisor', 0)", __FILE__ . " linje " . __LINE__);
}

// 20261001 Sawaneh Settings redesign §7.2: one row per setting. Duplicates are merged (removed rows kept in
// settings_removed) and the unique index is added once; after that only the index lookup runs.
include_once(__DIR__ . "/settings/uniqueIndex.php");
settings_unique_migrate();

// 20261002 Sawaneh Risk review R9: the same for the settings arts of grupper (removed rows kept in grupper_removed).
include_once(__DIR__ . "/settings/grupperIndex.php");
grupper_unique_migrate();

// 20261003 Sawaneh G6.3 (05b bug 45): the payment-list mail text used its own spelling of the key; one spelling for all mail texts.
// Read first, then update by id: MySQL refuses an UPDATE that selects from its own table (error 1093).
if (!db_fetch_array(db_select("select id from settings where var_grp = 'paylist' and var_name = 'mailText'", __FILE__ . " linje " . __LINE__))) {
	$q = db_select("select id from settings where var_grp = 'paylist' and var_name = 'mailtext'", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		db_modify("update settings set var_name = 'mailText' where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
	}
}

// 20261002 Sawaneh Settings 4b (G5.7): the packaging tables are created here when the module is on, not when a page
// renders (spec P5). G2.5: paymentDays was written under 'payment' but only read under 'payment_list'; copied once.
// 20261005 Sawaneh Settings 4c (§8.2): grupper.inaktiv - rows that are in use are hidden from choice lists instead of deleted.
if (!db_fetch_array(db_select("select column_name from information_schema.columns where table_name = 'grupper' and column_name = 'inaktiv'" . $sawanehSchema, __FILE__ . " linje " . __LINE__))) {
	db_modify("alter table grupper add column inaktiv boolean default false", __FILE__ . " linje " . __LINE__);
}
// 20261004 Sawaneh When the new settings first ran here (settings_ui/landed_at): the transition banner is shown to users
//                  with activity from before that moment only (settings redesign §8.10).
if (!db_fetch_array(db_select("select id from settings where var_grp = 'settings_ui' and var_name = 'landed_at'", __FILE__ . " linje " . __LINE__))) {
	db_modify("insert into settings (var_grp, var_name, var_value, var_description, user_id) values ('settings_ui', 'landed_at', '" . date('Y-m-d H:i:s') . "', 'First login after the settings redesign', 0)", __FILE__ . " linje " . __LINE__);
}
// 20261004 Sawaneh G9.2 (B-D5): the Danske Fragtmænd settings get their own group; debitor/ordre.php reads both until 4f.
db_modify("update settings set var_grp = 'DFM' where var_grp = 'GLS' and var_name like 'dfm\\_%'", __FILE__ . " linje " . __LINE__);
// 20261004 Sawaneh The packaging tables are created for every company (idempotent), so switching the module on in
// settings only sets the flag (spec §7.5: no DDL in save handlers) and works before the next login.
include_once(__DIR__ . "/emballage_schema.php");
ensure_emballage_schema();
if (!db_fetch_array(db_select("select id from settings where var_grp = 'payment_list' and var_name = 'paymentDays'", __FILE__ . " linje " . __LINE__))) {
	$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'payment' and var_name = 'paymentDays' and coalesce(var_value, '') <> ''", __FILE__ . " linje " . __LINE__));
	if ($r) {
		db_modify("insert into settings (var_grp, var_name, var_value, var_description, user_id) values ('payment_list', 'paymentDays', '" . db_escape_string((string) $r['var_value']) . "', 'Default payment days', 0)", __FILE__ . " linje " . __LINE__);
	}
}

// 20261020 CL/NTR brugere.ansat_id must be integer (it references ansatte.id); older tenants have text/varchar.
// Blank values become NULL so the conversion cannot fail on them. Idempotent: only runs while the column is still a text type.
$ansatIdMysql = in_array($db_type, ['mysql', 'mysqli'], true);
$ansatIdSchema = $ansatIdMysql ? " and table_schema = DATABASE()" : "";
$ansatIdType = db_fetch_array(db_select("select data_type as data_type from information_schema.columns where table_name = 'brugere' and column_name = 'ansat_id'$ansatIdSchema", __FILE__ . " linje " . __LINE__));
if ($ansatIdType && in_array(strtolower($ansatIdType['data_type']), ['text', 'character varying', 'varchar', 'char', 'tinytext', 'mediumtext', 'longtext'], true)) {
	if ($ansatIdMysql) {
		db_modify("UPDATE brugere SET ansat_id = NULL WHERE TRIM(ansat_id) = ''", __FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE brugere MODIFY ansat_id integer NULL DEFAULT NULL", __FILE__ . " linje " . __LINE__);
	} else {
		db_modify("ALTER TABLE \"brugere\" ALTER \"ansat_id\" TYPE integer USING NULLIF(trim(ansat_id), '')::integer, ALTER \"ansat_id\" DROP DEFAULT, ALTER \"ansat_id\" DROP NOT NULL", __FILE__ . " linje " . __LINE__);
	}
}

// 20261031 CL/ASR Text 7031 (help for the personal text size) promised all text, but the size only applies to the topbar,
// Oversigt and Indstillinger. Rows still holding the old wording are removed so findtekst() re-seeds the new one.
deleteStaleTekst(7031, array(
	'Gør al tekst ca. 11 % større. Browserens egen zoom virker oveni.',
	"Makes all text about 11 % larger. The browser's own zoom works on top.",
	'Gjør all tekst ca. 11 % større. Nettleserens egen zoom virker i tillegg.',
));

// 20261031 CL/ASR Text 7021 (lead of sager/tjeklister.php) said the checklists had moved out of Indstillinger; they are reached
// from Indstillinger > Organisation again, so the sentence is dropped.
deleteStaleTekst(7021, array(
	'Tjeklister til sager: faser, grupper og tjekpunkter. Flyttet hertil fra Indstillinger.',
	'Checklists for cases: phases, groups and check points. Moved here from Settings.',
	'Sjekklister for saker: faser, grupper og sjekkpunkter. Flyttet hit fra Innstillinger.',
));
// 20261104 CL/ASR Text 7815 (admin/restore.php) called a backup of a ledger with another name one of ANOTHER company; it compares
// ledger names, so it now says that. Rows still holding the old wording are removed so findtekst() re-seeds the new one.
deleteStaleTekst(7815, array(
	'Sikkerhedskopien er fra et ANDET firma: %s. Dette regnskab hedder %s. Gendanner du, bliver regnskabets data erstattet af det andet firmas data.',
	"The backup is from ANOTHER company: %s. This ledger is called %s. If you restore, the data of this ledger will be replaced by the other company's data.",
	'Sikkerhetskopien er fra et ANNET firma: %s. Dette regnskapet heter %s. Hvis du gjenoppretter, blir regnskapets data erstattet av det andre firmaets data.',
));
// 20261104 CL/ASR The lock taken before the role tables ends here: the one-time settings rows above (landed_at, paymentDays) cannot be
// inserted twice by two first logins at once. The time-budgeted backfill below has its own try-lock.
if (!$sawanehMysql) {
	db_select("select pg_advisory_unlock(hashtext('saldi_roles_migration'))", __FILE__ . " linje " . __LINE__);
}

// 20261019 CL/LH MB-73: ordrelinjer.leveret had no default and opret_ordrelinje() never set it, so
// linjeopdat()'s leveret+n stayed NULL. debitor/ordre.php re-syncs leveret from the delivery records
// whenever the order is shown (the sum of batch_salg.antal for the line, order and item), but fast
// invoicing never shows it, so invoiced and credited lines kept leveret NULL.
// The default is set only while it is still missing, checked in the current tenant's database/schema
// like the column checks now in includes/opdat_5.0.php. SET DEFAULT is a catalog change only; existing
// rows are not rewritten.
$leveretMysql = in_array($db_type, ['mysql', 'mysqli'], true);
// 20261027 CL/LH MB-73 (review): MariaDB reports a missing default as the text 'NULL', not as NULL.
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'ordrelinjer' AND column_name = 'leveret'";
$qtxt .= " AND (column_default IS NULL OR column_default = 'NULL')";
$qtxt .= $leveretMysql ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER TABLE ordrelinjer ALTER COLUMN leveret SET DEFAULT 0", __FILE__ . " linje " . __LINE__);
}
// One-time backfill, flagged in settings like the pool_files backfills in opdat_5.0.php: leveret becomes the same
// batch_salg sum ordre.php shows, for sales, credit note and POS lines that have delivery records. Lines
// without any (not delivered, text lines) are left as they are, as ordre.php leaves them. Purchase lines are
// excluded: their receipts are in batch_kob and kreditor/ordre.php keeps their leveret.
// 20261027 CL/LH MB-73 (review): as one UPDATE this took 4.5 minutes at the first login on a large POS tenant.
// backfillOrderLineDelivered() runs it in windows of ordrelinjer ids under a try-lock and stops after about a
// second; later logins carry on from the stored window, and a login that finds the lock taken skips it.
require_once __DIR__ . '/opdat_func/backfillOrderLineDelivered.php';
backfillOrderLineDelivered($db_type);

// 20261103 CL/ASR QA round 2 (restricted roles): the built-in Kun visning role got read on every key with a legacy position,
// dangerous ones too, and read on Sikkerhedskopi (settings.backup) let it create a full backup. Its dangerous rows are removed
// once and the rights strings of its users rendered again. Role rows for keys that have left the registry
// (dashboard.block.suggestions, 1 Nov 2026) are removed in the same step. Flagged in settings, like audit_admin_only above.
include_once(__DIR__ . "/permissions.php");
// 20261104 CL/ASR QA round 3: the one-time role changes below run one login at a time (the flag is read once the lock is held)
// and each in one transaction with its flag, so a login that stops halfway leaves the role as it was.
if (!$sawanehMysql) {
	db_select("select pg_advisory_lock(hashtext('saldi_roles_migration'))", __FILE__ . " linje " . __LINE__);
}
$qtxt = "select id from settings where var_grp = 'permissions' and var_name = 'readonly_no_dangerous'";
if (perm_tables_ready() && !db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	transaktion('begin');
	$permDangerous = array();
	$permKnown = array();
	foreach (permission_registry() as $permKey => $permDef) {
		$permKnown[] = "'" . db_escape_string($permKey) . "'";
		if (!empty($permDef['dangerous'])) {
			$permDangerous[] = "'" . db_escape_string($permKey) . "'";
		}
	}
	$r = db_fetch_array(db_select("select id from roles where role_key = 'kunvisning'", __FILE__ . " linje " . __LINE__));
	if ($r && $permDangerous) {
		db_modify("delete from role_permissions where role_id = " . (int) $r['id'] . " and permission_key in (" . implode(', ', $permDangerous) . ")", __FILE__ . " linje " . __LINE__);
		perm_sync_role((int) $r['id']);
	}
	db_modify("delete from role_permissions where permission_key not in (" . implode(', ', $permKnown) . ")", __FILE__ . " linje " . __LINE__);
	db_modify("insert into settings (var_grp, var_name, var_value, var_description, user_id) values ('permissions', 'readonly_no_dangerous', '" . date('Ymd') . "', 'Dangerous keys removed from Kun visning, unknown keys from all roles', 0)", __FILE__ . " linje " . __LINE__);
	transaktion('commit');
}

// 20261104 CL/ASR The expiry report, batch overview, sales by postcode and item trace moved from Debitorrapport (right 12) to
// Varerapport (right 15, lager.rapporter; owner, 4 Nov 2026), and the expiry warning in the bell with them. In the built-in
// roles the warning (dashboard.block.expiry) now goes with lager.rapporter: Lager gets Se Oversigt, the warning and news,
// Indkøb loses the warning. Applied once, to a stored built-in role only while its Oversigt keys are still the old defaults
// (Lager none, Indkøb view, warning and news), and the rights strings of its users are rendered again. Flagged in settings.
include_once(__DIR__ . "/permissions.php");
$qtxt = "select id from settings where var_grp = 'permissions' and var_name = 'stock_reports_lager_rapporter'";
if (perm_tables_ready() && !db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	transaktion('begin');
	// role_key => array(Oversigt keys of the old default (sorted), keys to add at read, keys to remove)
	$permStockRoles = array(
		'lager'   => array(array(), array('dashboard.view', 'dashboard.block.expiry', 'dashboard.block.news'), array()),
		'indkoeb' => array(array('dashboard.block.expiry', 'dashboard.block.news', 'dashboard.view'), array(), array('dashboard.block.expiry')),
	);
	foreach ($permStockRoles as $permRoleKey => $permChange) {
		$permRole = db_fetch_array(db_select("select id from roles where role_key = '$permRoleKey'", __FILE__ . " linje " . __LINE__));
		if (!$permRole) {
			continue;
		}
		$permRoleId = (int) $permRole['id'];
		$permDash = array();
		$permQ = db_select("select permission_key from role_permissions where role_id = $permRoleId and permission_key like 'dashboard.%'", __FILE__ . " linje " . __LINE__);
		while ($permRow = db_fetch_array($permQ)) {
			$permDash[] = (string) $permRow['permission_key'];
		}
		sort($permDash, SORT_STRING);
		if ($permDash !== $permChange[0]) {
			continue; // changed by an administrator (or already the new default): left as it is
		}
		foreach ($permChange[1] as $permKey) {
			db_modify("insert into role_permissions (role_id, permission_key, level) values ($permRoleId, '" . db_escape_string($permKey) . "', 'read')", __FILE__ . " linje " . __LINE__);
		}
		foreach ($permChange[2] as $permKey) {
			db_modify("delete from role_permissions where role_id = $permRoleId and permission_key = '" . db_escape_string($permKey) . "'", __FILE__ . " linje " . __LINE__);
		}
		perm_sync_role($permRoleId);
	}
	db_modify("insert into settings (var_grp, var_name, var_value, var_description, user_id) values ('permissions', 'stock_reports_lager_rapporter', '" . date('Ymd') . "', 'Expiry warning moved to the roles with Varerapport (Lager in, Indkoeb out)', 0)", __FILE__ . " linje " . __LINE__);
	transaktion('commit');
}
if (!$sawanehMysql) {
	db_select("select pg_advisory_unlock(hashtext('saldi_roles_migration'))", __FILE__ . " linje " . __LINE__);
}

// 20261102 CL/ASR Self-service signup (signup/*.php, doc/signup.md, written by PS): its tables in the master
// database and the trial columns on regnskab. Master only (global=true); runs from any tenant's login, so the
// tables exist before the website's form is pointed at signup/. Serialized with an advisory lock (two logins at
// the same moment would otherwise both try the CREATE), and once done a master settings flag
// (var_grp 'system', var_name 'signup_schema') makes it a single query per login. The flag means this block never
// runs again: a later change to the signup tables needs a block (and flag) of its own.
if (isset($GLOBALS['db'], $GLOBALS['sqdb']) && $GLOBALS['db'] !== $GLOBALS['sqdb']) {
	$signupFlag = "SELECT var_value FROM settings WHERE var_grp = 'system' AND var_name = 'signup_schema'";
	if (!db_fetch_array(db_select($signupFlag, __FILE__ . " linje " . __LINE__, true))) {
		$signupMysql = in_array($db_type, ['mysql', 'mysqli'], true);
		$signupLock = $signupMysql ? "GET_LOCK('saldi:signup_schema', 30)" : "pg_advisory_lock(hashtext('saldi:signup_schema'))";
		$signupUnlock = $signupMysql ? "RELEASE_LOCK('saldi:signup_schema')" : "pg_advisory_unlock(hashtext('saldi:signup_schema'))";
		$signupLocked = db_fetch_array(db_select("SELECT $signupLock AS acquired", __FILE__ . " linje " . __LINE__, true));
		// MySQL returns 0 when the lock was not granted in time; the next login tries again.
		if (!$signupMysql || (int) ($signupLocked['acquired'] ?? 0) === 1) {
			try {
				// Another login may have finished it while this one waited for the lock.
				if (!db_fetch_array(db_select($signupFlag, __FILE__ . " linje " . __LINE__, true))) {
					$signupSchema = $signupMysql ? "table_schema = DATABASE()" : "table_schema = current_schema()";
					$signupId = $signupMysql ? 'id INT AUTO_INCREMENT NOT NULL' : 'id SERIAL NOT NULL';
					$signupNow = $signupMysql ? 'CURRENT_TIMESTAMP' : 'now()';
					$signupHasTable = function ($table) use ($signupSchema) {
						$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_name = '$table' AND $signupSchema";
						return (bool) db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, true));
					};
					$signupHasColumn = function ($table, $column) use ($signupSchema) {
						$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = '$table' AND column_name = '$column' AND $signupSchema";
						return (bool) db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, true));
					};
					if (!$signupHasTable('signup_pending')) {
						$qtxt = "CREATE TABLE signup_pending ($signupId, created TIMESTAMP NOT NULL DEFAULT $signupNow, status VARCHAR(20) NOT NULL DEFAULT 'pending', ";
						$qtxt.= "pakke VARCHAR(20) NOT NULL, source VARCHAR(40), navn VARCHAR(80) NOT NULL, email VARCHAR(120) NOT NULL, tlf VARCHAR(40), ";
						$qtxt.= "cvrnr VARCHAR(20), firmanavn VARCHAR(90) NOT NULL, addr1 VARCHAR(60), addr2 VARCHAR(60), postnr VARCHAR(10), bynavn VARCHAR(60), ";
						$qtxt.= "regnskab VARCHAR(60) NOT NULL, token_hash VARCHAR(64), token_expires TIMESTAMP NULL, old_token_hash VARCHAR(64), resend_hash VARCHAR(64), ";
						$qtxt.= "mails_sent INTEGER NOT NULL DEFAULT 0, last_mail TIMESTAMP NULL, ip VARCHAR(45), activated TIMESTAMP NULL, regnskab_id INTEGER, fejl TEXT, ";
						$qtxt.= "navn_fra_cvr SMALLINT, PRIMARY KEY (id))";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__, true);
						db_modify("CREATE INDEX signup_pending_token_hash_idx ON signup_pending (token_hash)", __FILE__ . " linje " . __LINE__, true);
						db_modify("CREATE INDEX signup_pending_email_idx ON signup_pending (email)", __FILE__ . " linje " . __LINE__, true);
					} elseif (!$signupHasColumn('signup_pending', 'navn_fra_cvr')) {
						// 1 = the proposed account name came from the CVR register, 0 = typed (tables from before 20261021)
						db_modify("ALTER TABLE signup_pending ADD COLUMN navn_fra_cvr SMALLINT", __FILE__ . " linje " . __LINE__, true);
					}
					if (!$signupHasTable('signup_events')) {
						$qtxt = "CREATE TABLE signup_events ($signupId, ts TIMESTAMP NOT NULL DEFAULT $signupNow, kind VARCHAR(20) NOT NULL, ";
						$qtxt.= "ip VARCHAR(45), email VARCHAR(120), PRIMARY KEY (id))";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__, true);
						db_modify("CREATE INDEX signup_events_ts_idx ON signup_events (ts)", __FILE__ . " linje " . __LINE__, true);
					}
					if (!$signupHasTable('signup_cvr_cache')) {
						$qtxt = "CREATE TABLE signup_cvr_cache (q VARCHAR(120) NOT NULL, result TEXT, fetched TIMESTAMP NOT NULL DEFAULT $signupNow, PRIMARY KEY (q))";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__, true);
					}
					// lukkes_kommentar: admin/admin_panel.php also adds it when first opened; the signup writes why the account closes.
					foreach (['trial_start' => 'DATE', 'trial_end' => 'DATE', 'pakke' => 'VARCHAR(20)', 'lukkes_kommentar' => 'TEXT'] as $signupColumn => $signupType) {
						if (!$signupHasColumn('regnskab', $signupColumn)) {
							db_modify("ALTER TABLE regnskab ADD COLUMN $signupColumn $signupType", __FILE__ . " linje " . __LINE__, true);
						}
					}
					$qtxt = "INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ('signup_schema', 'system', '1', ";
					$qtxt.= "'Self-service signup tables created (includes/betweenUpdates.php, 20261102)')";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__, true);
				}
			} finally {
				db_select("SELECT $signupUnlock", __FILE__ . " linje " . __LINE__, true);
			}
		}
	}
}

// 20261104 CL/ASR Decision 22 (2026-10-09) moved the till account per currency from the tab list in grupper VK box4
// (index kasse-1, the old POS settings page) to settings VK/till_account_<currency> per till (pos_id), and made box4
// the "used in the till" flag. Saving that flag ('1'/'0') overwrites a list, so the accounts are copied to the new
// rows once, under a lock and in one transaction with the flag system/vk_till_accounts_migrated. box4 is not changed:
// findBoxSale() and posbogfor() read the new rows first and the list only while no row is stored.
require_once __DIR__ . '/opdat_func/migrateTillAccounts.php';
migrateTillAccounts($db_type);

?>
