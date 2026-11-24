<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- ver 5.1.1 --- 2026.11.20
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
// 20261105 CL/ASR The one-time role changes and the till account copy are moved up before master's MB-73 block, so MB-73 and the signup block stay at the end.
//                 OrderLineDeliveredBackfillTest runs the file from MB-73 to the end with stubbed database functions.
// 20261105 CL/LH betalinger gets openpost_id (includes/opdat_func/paymentOpenpostColumn.php), the open post a payment on a payment list pays, so Fra liste and Afregn kommission no longer link an Afr open post to its payment through the report number in ordre_id.
// 20261110 CDX/ASR Keep CRM DDL and one-time follow-up conversion in the updater instead of ordinary page requests.
// 20261110 CDX/NTR Release 5.1.1: move pending migrations into opdat_5.1.php and retain the bounded backfill continuation.
// 20261112 ASR help.sansat.name (the help panel's name for Indstillinger > Brugere) follows the hub row: Brugere og ansatte; the cached old wording is removed.
// 20261113 CL/ASR The CRM page help's CR2 step 2, Q3 step 3 and Sager text are reworded (til only with employees, the empty board); the cached old wording is removed.
// 20261113 CL/ASR The VAT report range seeded as 6780..7740 (grupper MR, a digit swap of 7680) becomes 7680..7830 once, per fiscal year, on the standard chart of accounts (includes/opdat_func/fixVatReportRange.php).
//                 Hand-made ranges, other charts and years with a VAT code booking to 6780..7679 are left alone.
// 20261113 CL/ASR The item groups' EU and non-EU accounts seeded as 1220, 1105 and 1250 (not in the standard chart) and box11 1050 (the EU sales account as the EU purchase account) become the standard chart's 1050, 1150, 1200 and 1630 once, per fiscal year (includes/opdat_func/fixStdItemGroupAccounts.php).
//                 Only where the replacement is the standard chart's own account in that year; companies that have 1220, 1105 or 1250 themselves, other charts and hand-made groups are left alone.
// 20261113 CDX/NTR Keep pending roles and settings migrations after the released 5.1.1 backfill continuation.
// 20261113 CDX/NTR Use shared update dialect helpers for schema filters, column definitions, index lookups and integer conversions.
// 20261113 CDX/NTR Name the built-in role flag is_built_in and rename existing system columns before role seeding.
// 20261114 CL/ASR The help texts that named Varegrupper's Lagertilgang and Lagertræk (vg5 step 2, vg10 step 3, Lagerstatus Q1 step 2) are reworded now that the columns are gone; the cached old wording is removed.
// 20261114 CL/ASR The texts that named Nulstil regnskab in the ledger's own settings (6631, the Abonnement & konto help, the backup help) are reworded now that the reset is in the admin panel; the cached old wording is removed.
// 20261114 CL/ASR The help texts that told the old two-factor rule (SMS when there is a phone number) are reworded, as the user now chooses SMS or e-mail; the cached old wording is removed.
// 20261114 CL/ASR Varegrupper's EU and non-EU purchase accounts (6494-6497) no longer say "(lagerværdi)": they are ordinary purchase accounts.
//                 Lagerstatus Q1 step 2 no longer tells about calculated stock lines in the account specification, which shows only what is posted now. The cached old wording is removed.
// 20261117 CL/ASR kassekladde gets ompost_id (addJournalReversalColumn()), the posted line a line written by Tilbagefør reverses, so posting can convert the reversal at the rate that line was posted at.
// 20261119 CL/ASR The help's Rapporter Q5 explains how Resultat and Balance are split instead of the removed Sideskift alert; the cached old wording is removed.
// 20261119 CL/ASR The settings help's steps for Omgående bogføring (sales and purchase orders) no longer say it can be changed back, as it cannot be switched off once on; the cached old wording is removed.
// 20261119 CL/ASR The help's Momsrubrikker texts (about, RP15 step 2, MR2 steps 3 and 4) say that postings without an order on the accounts the report lists at Konti uden ordre are counted, what box A counts, and what is still added by hand (trade posted without an order on other accounts, other box C sales such as an order with domestic reverse charge), and SAF-T step 2 that the balances include the primo; the cached old wording is removed.
// 20261120 CL/ASR The help for Omgående bogføring (the creditor order's KO21, the purchase and sales settings' about texts, the purchase settings' Q1 and the steps of KB1 and OF3) no longer says to switch it on and contact support about invoices that were not posted, as the settings page refuses to switch it on while they wait for Hent and says to fetch them first; it says to fetch them with Hent and post the journal first, and the cached old wording is removed.
// 20261120 CL/ASR The help's Momsrubrikker MR2 step 3 says plainly that in older journals without VAT codes a domestic purchase can count as an EU purchase with the same VAT in its voucher; the cached wording of 19 Nov is removed.
// 20261120 CL/ASR The help's about texts for a sales and a purchase invoice that is not posted (dord and kord ikke_bogfoert) no longer say immediate posting is off, as an invoice whose journal lines were deleted after both settings were switched on waits too and the cash journal shows Hent for it; the cached old wording is removed.

/**
 * Injected by includes/connect.php via the entry page that includes this file:
 * @var string $db_type
 */

// ===== PROTECTED: when a version is cut (see doc/ai/convention_database_changes.md), COPY this =====
// ===== segment into the opdat_<major>.<minor>.php file - do not move it. The moved statements =====
// ===== call these helpers, and this file keeps needing them for the next release. =====
include_once(__DIR__ . '/opdat_func/deleteStaleTekst.php');
// guardrails-allow migrations until 2026-12-31 by NTR: helper must load before the existing roles/settings migrations; dialect extraction preserves their order
require_once __DIR__ . '/opdat_func/databaseDialect.php';
// guardrails-allow migrations until 2026-12-31 by NTR: rename the existing role flag before perm_ensure_default_roles reads is_built_in
require_once __DIR__ . '/opdat_func/migrateRoleBuiltInFlag.php';
// ===== END PROTECTED =====

// 20261002 Sawaneh Text 5646 (no active fiscal year) named the old "System » Indstillinger" menu. Rows still holding the
// old wording are removed so findtekst() re-seeds them from tekster.csv; edited texts are left alone.
foreach (array('Aktivér et regnskabsår under System » Indstillinger » Regnskabsår', 'Activate a fiscal year under System » Settings » Fiscal year', 'Aktiver et regnskapsår under System » Innstillinger » Regnskapsår') as $gammel5646) {
	db_modify("delete from tekster where tekst_id = '5646' and tekst = '" . db_escape_string($gammel5646) . "'", __FILE__ . " linje " . __LINE__);
}

// 20261016 Sawaneh Review (PR #803): every information_schema check of ours filters on the schema, as master's newer
//                  blocks do: on MySQL the view spans every database the user can see, so the first tenant's tables
//                  would hide the others' missing ones.
$sawanehSchema = opdat_schema_filter($db_type);

// 20260916 Sawaneh Roles & permissions (user/settings system spec, part 2): roles,
// role_permissions, brugere.role_id and the audit log. Built-in roles are seeded and users
// holding every legacy right get the Administrator role; see includes/permissions.php.
$sawanehMysql = opdat_is_mysql($db_type);
$sawanehId = opdat_id_column($db_type);
$sawanehNow = opdat_timestamp_column($db_type);
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
		is_built_in boolean DEFAULT false,
		oprettet $sawanehNow)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
// Existing tenants must be upgraded before perm_ensure_default_roles() reads is_built_in below.
migrateRoleBuiltInFlag($db_type);
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
$auditLogMysql = opdat_is_mysql($db_type);
$auditLogId = opdat_id_column($db_type);
$auditLogTimestamp = opdat_timestamp_column($db_type, true);
$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_name = 'audit_log'";
$qtxt .= opdat_schema_filter($db_type);
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	if ($auditLogMysql) {
		db_modify("CREATE TABLE IF NOT EXISTS audit_log (
			$auditLogId,
			tidspunkt $auditLogTimestamp,
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
			$auditLogId,
			tidspunkt $auditLogTimestamp,
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
		if (!opdat_is_mysql($db_type)) {
			// The old key and sequence keep the names the new table wants.
			db_modify("ALTER INDEX IF EXISTS notifications_pkey RENAME TO {$legacyName}_pkey", __FILE__ . " linje " . __LINE__);
			db_modify("ALTER SEQUENCE IF EXISTS notifications_id_seq RENAME TO {$legacyName}_id_seq", __FILE__ . " linje " . __LINE__);
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
	if (!opdat_is_mysql($db_type)) {
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
	$qtxt = opdat_index_query($db_type, 'audit_log', $auditIdx);
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
$ansatIdSchema = opdat_schema_filter($db_type);
$ansatIdType = db_fetch_array(db_select("select data_type as data_type from information_schema.columns where table_name = 'brugere' and column_name = 'ansat_id'$ansatIdSchema", __FILE__ . " linje " . __LINE__));
if ($ansatIdType && in_array(strtolower($ansatIdType['data_type']), ['text', 'character varying', 'varchar', 'char', 'tinytext', 'mediumtext', 'longtext'], true)) {
	foreach (opdat_nullable_integer_queries($db_type, 'brugere', 'ansat_id') as $qtxt) {
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
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
// inserted twice by two first logins at once. The one-time role changes below take the lock again for their own section,
// and the time-budgeted MB-73 backfill after them has its own try-lock.
if (!$sawanehMysql) {
	db_select("select pg_advisory_unlock(hashtext('saldi_roles_migration'))", __FILE__ . " linje " . __LINE__);
}

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

// 20261104 CL/ASR Decision 22 (2026-10-09) moved the till account per currency from the tab list in grupper VK box4
// (index kasse-1, the old POS settings page) to settings VK/till_account_<currency> per till (pos_id), and made box4
// the "used in the till" flag. Saving that flag ('1'/'0') overwrites a list, so the accounts are copied to the new
// rows once, under a lock and in one transaction with the flag system/vk_till_accounts_migrated. box4 is not changed:
// findBoxSale() and posbogfor() read the new rows first and the list only while no row is stored.
require_once __DIR__ . '/opdat_func/migrateTillAccounts.php';
migrateTillAccounts($db_type);

// guardrails-allow migrations until 2026-12-31 by NTR: 5.1.1 version cut retains the resumable backfill below
// Released MB-73 work can span several logins; keep this continuation outside the version gate.
// It returns immediately once the completion flag is set. Do not move it into a future release gate.
require_once __DIR__ . '/opdat_func/backfillOrderLineDelivered.php';
backfillOrderLineDelivered($db_type);

// 20261020 ASR CRM redesign (debitor/crm.php): historik.type + historik.completed and the crm_deals table, see includes/crmFunctions.php.
include_once(__DIR__ . "/crmFunctions.php");
if (!function_exists('crm_ensure_schema')) {
/**
 * CRM installation and legacy follow-up conversion; updater only, never a page request.
 */
function crm_ensure_schema(): void
{
	if (!crm_col_exists('historik', 'completed')) {
		db_modify("ALTER TABLE historik ADD COLUMN completed boolean", __FILE__ . " linje " . __LINE__);
	}
	if (!crm_col_exists('historik', 'type')) {
		db_modify("ALTER TABLE historik ADD COLUMN type varchar(10)", __FILE__ . " linje " . __LINE__);
	}
	if (!crm_col_exists('crm_deals', 'id')) {
		if (crm_is_mysql()) {
			db_modify("CREATE TABLE crm_deals (
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
			)", __FILE__ . " linje " . __LINE__);
		} else {
			db_modify("CREATE TABLE crm_deals (
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
			)", __FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX crm_deals_konto_idx ON crm_deals (konto_id)", __FILE__ . " linje " . __LINE__);
		}
	}
	// 20261020 ASR the offer/order made from a deal (ordre.php?..&crm_deal=<id> sets it at creation)
	if (!crm_col_exists('crm_deals', 'ordre_id')) {
		db_modify("ALTER TABLE crm_deals ADD COLUMN ordre_id integer", __FILE__ . " linje " . __LINE__);
	}
	// 20261024 ASR review R4: who wrote the entry (ansat_id stays the follow-up's owner, as the old pages read it)
	if (!crm_col_exists('historik', 'author_id')) {
		db_modify("ALTER TABLE historik ADD COLUMN author_id integer", __FILE__ . " linje " . __LINE__);
	}
	// 20261026 ASR review R13/R16: when a follow-up was marked done (the "done in the last 14 days" list, the timeline)
	if (!crm_col_exists('historik', 'completed_at')) {
		db_modify("ALTER TABLE historik ADD COLUMN completed_at date", __FILE__ . " linje " . __LINE__);
	}
	// 20261024 ASR review R56: historik is read per customer on every CRM request; the table had no index on konto_id
	if (!crm_is_mysql()) {
		if (!db_fetch_array(db_select("select indexname from pg_indexes where schemaname = current_schema() and tablename = 'historik' and indexname = 'historik_konto_idx'", __FILE__ . " linje " . __LINE__))) {
			db_modify("CREATE INDEX historik_konto_idx ON historik (konto_id)", __FILE__ . " linje " . __LINE__);
		}
	} elseif (!db_fetch_array(db_select("show index from historik where Key_name = 'historik_konto_idx'", __FILE__ . " linje " . __LINE__))) {
		db_modify("CREATE INDEX historik_konto_idx ON historik (konto_id)", __FILE__ . " linje " . __LINE__);
	}
	// 20261029 ASR meetings: the start time and length of a follow-up of type moede (historik.kontaktes is its date),
	// whether calendar invitations went out, the colleagues on a meeting ('h') or a deal ('d'), the personal calendar feed
	if (!crm_col_exists('crm_meetings', 'historik_id')) {
		db_modify("CREATE TABLE crm_meetings (historik_id integer NOT NULL PRIMARY KEY, start_time varchar(5) NOT NULL, duration integer NOT NULL DEFAULT 60, "
			. "seq integer NOT NULL DEFAULT 0, invite_team integer NOT NULL DEFAULT 0, invite_contact integer NOT NULL DEFAULT 0)", __FILE__ . " linje " . __LINE__);
	}
	if (!crm_col_exists('crm_participants', 'ref_id')) {
		if (crm_is_mysql()) {
			db_modify("CREATE TABLE crm_participants (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(1) NOT NULL, ref_id INT NOT NULL, ansat_id INT NOT NULL, "
				. "INDEX crm_participants_ref_idx (kind, ref_id), INDEX crm_participants_ansat_idx (ansat_id))", __FILE__ . " linje " . __LINE__);
		} else {
			db_modify("CREATE TABLE crm_participants (id SERIAL PRIMARY KEY, kind varchar(1) NOT NULL, ref_id integer NOT NULL, ansat_id integer NOT NULL)", __FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX crm_participants_ref_idx ON crm_participants (kind, ref_id)", __FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX crm_participants_ansat_idx ON crm_participants (ansat_id)", __FILE__ . " linje " . __LINE__);
		}
	}
	if (!crm_col_exists('crm_ical', 'token')) {
		db_modify("CREATE TABLE crm_ical (bruger_id integer NOT NULL PRIMARY KEY, token varchar(64) NOT NULL, created date)", __FILE__ . " linje " . __LINE__);
	}
	crm_migrate_legacy_followups();
}

/**
 * 20261024 ASR review R22. Rows written by the old CRM pages have completed NULL. The old model kept one "next contact"
 * per customer: a newer row WITH a next-contact date replaced it, a dateless note did not. Once, we make that explicit:
 * every NULL row with a date that is not the customer's newest dated row becomes done (TRUE). The newest dated row stays
 * NULL and is read as open by crm_open_sql(); later deletes and restores can then no longer flip old rows between open
 * and done. Guarded by a settings flag so it runs once per company.
 */
function crm_migrate_legacy_followups(): void
{
	if (!function_exists('get_settings_value') || !function_exists('update_settings_value')) {
		return;
	}
	if ((string) get_settings_value('crm_legacy_followups', 'crm', '') === '1') {
		return;
	}
	if (crm_is_mysql()) {
		db_modify("update historik h join (select konto_id, max(id) as m from historik where kontaktes is not null group by konto_id) x on x.konto_id = h.konto_id "
			. "set h.completed = TRUE where h.completed is null and h.kontaktes is not null and h.id <> x.m", __FILE__ . " linje " . __LINE__);
	} else {
		db_modify("update historik h set completed = TRUE where h.completed is null and h.kontaktes is not null "
			. "and h.id <> (select max(h2.id) from historik h2 where h2.konto_id = h.konto_id and h2.kontaktes is not null)", __FILE__ . " linje " . __LINE__);
	}
	update_settings_value('crm_legacy_followups', 'crm', '1', 'CRM: legacy follow-ups made explicit (review R22)');
}

}
crm_ensure_schema();

// 20261112 ASR help.sansat.name (the help panel's name for Indstillinger > Brugere) follows the hub row, which the roles
// administration renamed. Rows still holding the old wording are removed so findtekst() re-seeds the new one.
deleteStaleTekst('help.sansat.name', array(
	'Indstillinger: Brugere',
	'Settings: Users',
	'Innstillinger: Brukere',
));

// 20261113 CL/ASR The CRM page help (help.dcrm.*): CR2 step 2 and Q3 step 3 name the field til only for a company with
// employees, and the Sager text covers an empty board. Rows still holding the old wording are removed so findtekst() re-seeds them.
deleteStaleTekst('help.dcrm.task.cr2.step2', array(
	'Vælg evt. ved **til**, hvem der skal følge op. Skriv, hvad der skal ske, eller lad feltet stå tomt, og klik **Gem**.',
	'If you like, choose at **for** who is to follow up. Write what is to happen, or leave the field empty, and click **Save**.',
	'Velg eventuelt ved **til** hvem som skal følge opp. Skriv hva som skal skje, eller la feltet stå tomt, og klikk **Lagre**.',
));
deleteStaleTekst('help.dcrm.q.q3.step3', array(
	'Er knappen **Gem** grå, er der hverken tekst i feltet eller valgt en dato ved **Opfølgning**. Er din bruger ikke knyttet til en medarbejder, og har du valgt en dato, skal du også vælge ved **til**, hvem opfølgningen er til.',
	'If **Save** is greyed out, there is neither text in the field nor a date chosen at **Follow-up**. If your user is not linked to an employee and you have chosen a date, you must also choose at **for** who the follow-up is for.',
	'Er knappen **Lagre** grå, er det verken tekst i feltet eller valgt en dato ved **Oppfølging**. Er brukeren din ikke knyttet til en ansatt, og har du valgt en dato, må du også velge ved **til** hvem oppfølgingen er til.',
));
deleteStaleTekst('help.dcrm.about.sager', array(
	'Sager viser en tavle for hver statusgruppe. Hvert kort er en kunde, og kolonnen er kundens status. Træk et kort til en anden kolonne for at skifte status. Kortet viser kundens åbne sager og deres samlede beløb.',
	'Cases shows a board for each status group. Each card is a customer, and the column is the customer\'s status. Drag a card to another column to change the status. The card shows the customer\'s open cases and their total amount.',
	'Saker viser en tavle for hver statusgruppe. Hvert kort er en kunde, og kolonnen er kundens status. Dra et kort til en annen kolonne for å bytte status. Kortet viser kundens åpne saker og det samlede beløpet.',
));

// 20261113 CL/ASR The VAT report range (grupper art 'MR' box1..box2), which Finans > Rapporter > Momsangivelse and the
// dashboard's "Momsangivelse for perioden" add up, was seeded from importfiler/grupper.txt as 6780..7740: a digit swap
// of 7680, fixed in 852223af (2024-09-27) and lost again in b6ef0418 (2024-12-05). On the standard chart that range also
// takes in the reserves, the debt accounts, 7440 Leverandører and their sum accounts. The owner's range (13 Nov 2026) is
// 7680..7830: the VAT accounts 7680-7740 and the duty accounts 7760-7830, the fields of the VAT return; 7840 Skyldig moms
// (the settlement) stays out. The MR row of every fiscal year is judged against that year's chart, and only where 7680
// is a balance account there: 6780..7740 starts at 7680 unless a VAT code of the year books to 6780..7679, and the end
// 7740 becomes 7830 only when 7741..7830 holds nothing but the duty accounts. Other ranges were set by hand and are left
// alone. Once, under a lock and in one transaction with the flag system/vat_report_range_7680.
require_once __DIR__ . '/opdat_func/fixVatReportRange.php';
fixVatReportRange($db_type);

// 20261113 CL/ASR The item groups (grupper art 'VG') seeded from importfiler/grupper.txt carry EU and non-EU accounts
// the standard chart has never had: box12 (sales to EU customers) 1220 and 1105 and box14 (sales outside the EU) 1250.
// includes/ordrefunc.php batch_salg() puts them on the invoice line and bogfor_nu() posts it without a check, so the sale
// lands on an account no report shows. box11 (purchases from EU suppliers, kreditor/bogfor.php) was seeded as 1050 Salg af varer udland, EU,
// so goods bought in the EU were booked against EU sales. The seed is fixed; here 1220 -> 1050, 1105 -> 1150,
// 1250 -> 1200 and box11 1050 -> 1630, per fiscal year and only where the replacement is the standard chart's own
// account in that year (operating, open, standard name, no VAT code on a sales account) and 1220, 1105 or 1250 is not
// in that year's chart; box11 only on a row that still has all four seeded accounts. Postings already made are not
// touched. Once, under a lock and in one transaction with the flag system/std_item_group_accounts.
require_once __DIR__ . '/opdat_func/fixStdItemGroupAccounts.php';
fixStdItemGroupAccounts($db_type);

// 20261114 CL/ASR Varegrupper no longer shows Lagertilgang and Lagertræk (VG box1/box2, nothing posts to them since the 2015
// fiscal years). The help texts that named the two columns are reworded. Rows still holding the old wording are removed so findtekst() re-seeds them.
deleteStaleTekst('help.svaregrp.task.vg5.step2', array(
	'Skriv gruppens lagerkonti i **Lagertilgang** og **Lagertræk**, eller lad begge stå tomme. Saldi bogfører ikke selv lagerværdien på dem. Bogfør selv ændringen i en kassekladde, fx ved årets slutning: forskellen mellem lagerværdien til kostpris i Lagerstatus under Lager » Rapporter og saldoen på lagerkontoen, mellem lagerkontoen og kontoen for lagerregulering.',
	'Enter the group\'s stock accounts in **Stock in** and **Stock out**, or leave both empty. Saldi does not post the stock value to them itself. Post the change yourself in a cash journal, e.g. at the year end: the difference between the stock value at cost price in Inventory Status under Inventory » Reports and the balance of the stock account, between the stock account and the stock adjustment account.',
	'Skriv gruppens lagerkontoer i **Lagertilgang** og **Lageruttak**, eller la begge stå tomme. Saldi bokfører ikke lagerverdien på dem selv. Bokfør endringen selv i en kassekladd, f.eks. ved årets slutt: forskjellen mellom lagerverdien til kostpris i Lagerstatus under Lager » Rapporter og saldoen på lagerkontoen, mellom lagerkontoen og kontoen for lagerregulering.',
));
deleteStaleTekst('help.svaregrp.task.vg10.step3', array(
	'**Lagertilgang** og **Lagertræk** skal udfyldes begge eller ingen af dem, og **Batchkontrol** kræver **Lagerført**.',
	'**Stock in** and **Stock out** must both be filled in or neither, and **Batch control** requires **Stock-managed**.',
	'**Lagertilgang** og **Lageruttak** må fylles ut begge eller ingen av dem, og **Batchkontroll** krever **Lagerført**.',
));
deleteStaleTekst('help.lstat.q.lsq1.step2', array(
	'Har varegruppen konti i **Lagertilgang** og **Lagertræk**, viser kontospecifikationen lagerbevægelser beregnet ud fra lageret, når du klikker på et beløb under Finans » Regnskab. De er ikke bogført. Er du i tvivl, så spørg din bogholder eller revisor.',
	'If the item group has accounts in **Stock in** and **Stock out**, the account specification shows stock movements calculated from the stock when you click an amount under Finance » Financial Accounting. They are not posted. If in doubt, ask your bookkeeper or auditor.',
	'Har varegruppen kontoer i **Lagertilgang** og **Lageruttak**, viser kontospesifikasjonen lagerbevegelser beregnet ut fra lageret når du klikker på et beløp under Finansiere » Finansregnskap. De er ikke bokført. Er du i tvil, spør du bokføreren eller revisoren din.',
));

// 20261114 CL/ASR Nulstil regnskab left the ledger's own settings (Abonnement & konto); Saldi's staff reset a ledger from
// the admin panel. The texts that named the reset are reworded. Rows still holding the old wording are removed so findtekst() re-seeds them.
deleteStaleTekst(6631, array(
	'Regnskabets navn og farezonen: nulstil eller slet regnskabet',
	'The ledger\'s name and the danger zone: reset or delete the ledger',
	'Regnskapets navn og faresonen: nullstill eller slett regnskapet',
));
deleteStaleTekst('help.skonto.about.formular', array(
	'Her ændrer du regnskabets navn og kan nulstille eller slette regnskabet. Handlingerne under **Farezone** kan ikke fortrydes og kræver din adgangskode.',
	'Here you change the ledger\'s name and can reset or delete the ledger. The actions under **Danger zone** cannot be undone and need your password.',
	'Her endrer du regnskapets navn og kan nullstille eller slette regnskapet. Handlingene under **Faresone** kan ikke angres og krever passordet ditt.',
));
deleteStaleTekst('help.sbackup.task.bk1.why', array(
	'Tag en kopi før store ændringer, fx en import, en nulstilling eller sletning af et år.',
	'Take a copy before big changes, e.g. an import, a reset or deleting a year.',
	'Ta en kopi før store endringer, f.eks. en import, en tilbakestilling eller sletting av et år.',
));

// 20261114 CL/ASR Two-factor login has a choice of its own (SMS or e-mail, includes/twoFactor.php) in place of "SMS when there is a
// phone number", so the help texts of Brugere & roller and of the personal settings that told the old rule are reworded.
// Rows still holding the old wording are removed so findtekst() re-seeds them.
deleteStaleTekst('help.sbrugere.field.email.text', array(
	'Brugerens e-mail. En invitation og en midlertidig adgangskode sendes hertil, og den bruges til tofaktor-koder, når der ikke er et telefonnummer.',
	'The user\'s email. An invitation and a temporary password are sent here, and it is used for two-factor codes when there is no phone number.',
	'Brukerens e-post. En invitasjon og et midlertidig passord sendes hit, og den brukes til tofaktor-koder når det ikke er et telefonnummer.',
));
deleteStaleTekst('help.sbrugere.field.twofactor.text', array(
	'Til betyder, at brugeren ud over adgangskoden skal taste en kode, der sendes på SMS til telefonnummeret eller ellers på e-mail.',
	'On means that, besides the password, the user must type a code sent by text message to the phone number or otherwise by email.',
	'På betyr at brukeren i tillegg til passordet må taste en kode som sendes på SMS til telefonnummeret eller ellers på e-post.',
));
deleteStaleTekst('help.sbrugere.task.bk4.step1', array(
	'Sæt **Tofaktor** til **Til** på kortet.',
	'Set **Two-factor** to **On** on the card.',
	'Sett **Tofaktor** til **På** på kortet.',
));
deleteStaleTekst('help.sbrugere.task.bk4.step2', array(
	'Udfyld **Telefon**, hvis koden skal komme på SMS. Tofaktor-login kræver et telefonnummer eller en e-mail.',
	'Fill in **Telephone** if the code should come by text message. Two-factor login needs a phone number or an email.',
	'Fyll ut **Telefon** hvis koden skal komme på SMS. Tofaktor-innlogging krever et telefonnummer eller en e-post.',
));
deleteStaleTekst('help.spers.field.tlf.text', array(
	'Dit mobilnummer. Står der et nummer, sendes tofaktor-koden som SMS. Ændringen kræver din nuværende adgangskode.',
	'Your mobile number. If there is a number, the two-factor code is sent by SMS. The change needs your current password.',
	'Mobilnummeret ditt. Står det et nummer, sendes tofaktorkoden som SMS. Endringen krever nåværende passord.',
));
deleteStaleTekst('help.spers.field.twofactor.text', array(
	'Med flueben skal du ud over adgangskoden skrive en kode, der sendes som SMS eller e-mail.',
	'With a tick you type a code sent by SMS or e-mail in addition to the password.',
	'Med hake skriver du i tillegg til passordet en kode som sendes som SMS eller e-post.',
));
deleteStaleTekst('help.spers.task.ps4.step1', array(
	'Åbn fanen **Sikkerhed**, og sæt flueben i **Tofaktor-login (kode via SMS eller e-mail)**.',
	'Open the tab **Security**, and tick **Two-factor login (code by SMS or email)**.',
	'Åpne fanen **Sikkerhet**, og sett hake i **Tofaktor-innlogging (kode via SMS eller e-post)**.',
));
deleteStaleTekst('help.spers.task.ps4.step2', array(
	'Koden sendes som SMS, når der står et telefonnummer under **Profil**, ellers som e-mail. Et af dem skal være udfyldt.',
	'The code is sent by SMS when there is a phone number under **Profile**, otherwise by e-mail. One of them must be filled in.',
	'Koden sendes som SMS når det står et telefonnummer under **Profil**, ellers som e-post. Ett av dem må være fylt ut.',
));

// 20261114 CL/ASR Varegrupper's EU and non-EU purchase accounts (VG box11/box13) replace Varekøb on a purchase from a
// supplier with a VAT number in another EU country or outside the EU (kreditor/bogfor.php). Nothing about stock value, so
// their labels and help lose "(lagerværdi)". The account specification (finans/kontospec.php) shows only posted entries
// now, so Lagerstatus Q1 step 2 no longer mentions calculated stock lines there (its wording of earlier today goes too).
deleteStaleTekst('6494', array(
	'Alt. varekøbskonto i EU (lagerværdi)',
	'Alt. purchase account in the EU (stock value)',
	'Alt. varekjøpskonto i EU (lagerverdi)',
));
deleteStaleTekst('6495', array(
	'Alt. varekøbskonto uden for EU (lagerværdi)',
	'Alt. purchase account outside the EU (stock value)',
	'Alt. varekjøpskonto utenfor EU (lagerverdi)',
));
deleteStaleTekst('6496', array(
	'Ekstra varekøbskonto til beregning af lagerværdi. Hed tidligere "Køb i EU".',
	'Extra purchase account for the stock value. Formerly "Køb i EU".',
	'Ekstra varekjøpskonto for lagerverdi. Het tidligere "Køb i EU".',
));
deleteStaleTekst('6497', array(
	'Ekstra varekøbskonto til beregning af lagerværdi. Hed tidligere "Køb uden for EU".',
	'Extra purchase account for the stock value. Formerly "Køb uden for EU".',
	'Ekstra varekjøpskonto for lagerverdi. Het tidligere "Køb uden for EU".',
));
deleteStaleTekst('help.lstat.q.lsq1.step2', array(
	'Viser kontospecifikationen lagerbevægelser, når du klikker på et beløb under Finans » Regnskab, er de beregnet ud fra lageret. De er ikke bogført. Er du i tvivl, så spørg din bogholder eller revisor.',
	'If the account specification shows stock movements when you click an amount under Finance » Financial Accounting, they are calculated from the stock. They are not posted. If in doubt, ask your bookkeeper or auditor.',
	'Viser kontospesifikasjonen lagerbevegelser når du klikker på et beløp under Finansiere » Finansregnskap, er de beregnet ut fra lageret. De er ikke bokført. Er du i tvil, spør du bokføreren eller revisoren din.',
));

// 20261105 CL/LH betalinger.openpost_id: the open post a payment on a payment list pays. Afregn kommission and Fra liste
// linked a seller's Afr open post to its payment through the report number in ordre_id, which every seller of the report shares.
require_once __DIR__ . '/opdat_func/paymentOpenpostColumn.php';
addPaymentOpenpostColumn($db_type);

// 20261117 CL/ASR kassekladde.ompost_id: the posted journal line that a line written by Tilbagefør (ompost() in finans/kassekladde.php)
// reverses. Posting converts such a line at the rate that line was posted at (journalLineInBaseCurrency() in
// finans/bogfor_includes/postingRules.php), so a rate corrected in the rate table afterwards leaves no remainder on the accounts.
if (!function_exists('addJournalReversalColumn')) {
	/**
	 * Adds kassekladde.ompost_id once. Runs at every login until the column is there; two first logins at once both
	 * add it without an error.
	 *
	 * @param string $dbType $db_type from includes/connect.php ('postgresql', 'mysql' or 'mysqli').
	 * @return string 'added', 'present', 'no table' (a database without kassekladde) or 'busy' (the MySQL lock was
	 *                not granted; the next login tries again).
	 */
	function addJournalReversalColumn($dbType) {
		$columns = function () use ($dbType) {
			$qtxt = "SELECT column_name AS col FROM information_schema.columns WHERE table_name = 'kassekladde'" . opdat_schema_filter($dbType);
			$found = array();
			$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$found[] = strtolower((string) $r['col']);
			}
			return $found;
		};
		$found = $columns();
		if (!$found) {
			return 'no table';
		}
		if (in_array('ompost_id', $found, true)) {
			return 'present';
		}
		if (!opdat_is_mysql($dbType)) {
			// A second login waits for the first one's ALTER and then finds the column.
			db_modify("ALTER TABLE kassekladde ADD COLUMN IF NOT EXISTS ompost_id INTEGER", __FILE__ . " linje " . __LINE__);
			return 'added';
		}
		// MySQL has no ADD COLUMN IF NOT EXISTS. GET_LOCK is server-wide, so the name carries the database.
		$lockName = "CONCAT('saldi:kkompost:', MD5(DATABASE()))";
		$locked = db_fetch_array(db_select("SELECT GET_LOCK($lockName, 30) AS acquired", __FILE__ . " linje " . __LINE__));
		if ((int) ($locked['acquired'] ?? 0) !== 1) {
			return 'busy';
		}
		try {
			// Another login may have added it while this one waited for the lock.
			if (in_array('ompost_id', $columns(), true)) {
				return 'present';
			}
			db_modify("ALTER TABLE kassekladde ADD COLUMN ompost_id INTEGER", __FILE__ . " linje " . __LINE__);
			return 'added';
		} finally {
			db_select("SELECT RELEASE_LOCK($lockName)", __FILE__ . " linje " . __LINE__);
		}
	}
}
addJournalReversalColumn($db_type);

// 20261119 CL/ASR Finans > Rapporter splits Resultat and Balance by account type when the chart has no Sideskift account
// (regnskab_kontoside() in finans/rapport_includes/regnskab.php), so the help's Rapporter Q5 no longer explains the alert
// "Sideskiftkonto ikke defineret" but how the two are split. Rows still holding the old wording are removed so findtekst() re-seeds them.
deleteStaleTekst('help.rp.q.q5.q', array(
	'Hvorfor står der Sideskiftkonto ikke defineret?',
	'Why does it say Sideskiftkonto ikke defineret?',
	'Hvorfor står det Sideskiftkonto ikke defineret?',
));
deleteStaleTekst('help.rp.q.q5.intro', array(
	'Saldi skiller resultat og balance ad ved en konto med kontotypen Sideskift.',
	'Saldi separates result and balance by an account with the account type Page change.',
	'Saldi skiller resultat og balanse ved en konto med kontotypen Sideskift.',
));
deleteStaleTekst('help.rp.q.q5.step1', array(
	'Uden den konto viser **Resultat** og **Balance** alle konti.',
	'Without that account, **Result** and **Balance** show all accounts.',
	'Uten den kontoen viser **Resultat** og **Balanse** alle kontoer.',
));
deleteStaleTekst('help.rp.q.q5.step2', array(
	'Opret en konto med kontotypen Sideskift under Finans » Kontoplan, med et nummer mellem den sidste driftskonto og den første statuskonto.',
	'Create an account with the account type Page change under Finance » Chart of Accounts, numbered between the last operating account and the first balance sheet account.',
	'Opprett en konto med kontotypen Sideskift under Finansiere » Kontooversikt, med et nummer mellom den siste driftskontoen og den første statuskontoen.',
));

// 20261119 CL/ASR Omgående bogføring of sales and of purchase orders can be switched on but no longer off (owner, 19 Nov 2026,
// 'stays_on' in systemdata/settingsDefinitions.php), so the settings help's step that said to leave it on and save if you change
// it now says it cannot be switched off again. Rows still holding the old wording are removed so findtekst() re-seeds them.
deleteStaleTekst('help.sordre.task.of3.step3', array(
	'Lad den være slået til. Fakturaer, der er lavet, mens den var slået fra, bliver ikke bogført, når du slår den til igen. Kontakt support om dem. Tryk **Gem ændringer**, hvis du ændrer den.',
	'Leave it on. Invoices made while it was off are not posted when you turn it on again. Contact support about them. Press **Save changes** if you change it.',
	'La den være slått på. Fakturaer som er laget mens den var slått av, blir ikke bokført når du slår den på igjen. Kontakt support om dem. Trykk **Lagre endringer** hvis du endrer den.',
));
deleteStaleTekst('help.skoeb.task.kb1.step3', array(
	'Lad den være slået til. Regninger, der er bogført, mens den var slået fra, kommer ikke på kontiene, når du slår den til igen. Kontakt support om dem. Tryk **Gem ændringer**, hvis du ændrer den.',
	'Leave it on. Bills posted while it was off do not reach the accounts when you turn it on again. Contact support about them. Press **Save changes** if you change it.',
	'La den være slått på. Regninger som er bokført mens den var slått av, kommer ikke på kontoene når du slår den på igjen. Kontakt support om dem. Trykk **Lagre endringer** hvis du endrer den.',
));

// 20261119 CL/ASR Momsrubrikker counts posted orders and the postings without an order on the rubrik accounts (MR box3..box7), also from the
// kassekladde, and lists those accounts under its table at Konti uden ordre (finans/rapport_includes/moms_rubrik.php), and SAF-T's balances include
// the chart's primo (finans/saft.php), so the help no longer says that all journal postings are left out and must be added by hand, or that the
// file leaves out the primo. It says which postings are counted, points to that list, and says what is still added by hand: trade posted without
// an order on other accounts, and other box C sales, such as an order with domestic reverse charge. Rows still holding the old wording are removed
// so findtekst() re-seeds them.
deleteStaleTekst('help.rp.about.moms_rubrik', array(
	'Rapporten viser nettobeløbet til rubrik A, B og C på momsangivelsen. Beløbene kommer fra fakturerede salgs- og købsordrer.',
	'The report shows the net amount for boxes A, B and C on the VAT return. The amounts come from invoiced sales and purchase orders.',
	'Rapporten viser nettobeløpet til rubrikk A, B og C på mva-meldingen. Beløpene kommer fra fakturerte salgs- og innkjøpsordrer.',
));
deleteStaleTekst('help.rp.task.mr2.step3', array(
	'Har du bogført handel med udlandet direkte i en kassekladde, så læg den til selv.',
	'If you posted trade with other countries directly in a cash journal, add it yourself.',
	'Har du bokført handel med utlandet direkte i en kassekladd, legger du den til selv.',
	// 20261120: the wording of 19 Nov, whose last sentence did not say what goes wrong in older journals without VAT codes.
	'Posteringer uden ordre på kontiene ved **Konti uden ordre** er med, også fra en kassekladde, så læg dem ikke til igen. Dér tæller rubrik A køb med momskode E eller Y og køb uden moms (**u/m**), hvor erhvervelsesmomsen er tastet i bilaget, og rubrik B og C salg uden moms. Handel med udlandet, der er bogført uden ordre på andre konti, fx et EU-ydelseskøb med momskode Y på en udgiftskonto, er ikke med, så den lægger du selv til. I ældre kladder uden momskoder på linjerne afgør bilagets moms det, så tjek bilag med både EU-køb og indenlandske køb.',
	'Entries without an order on the accounts at **Konti uden ordre** are included, also from a cash journal, so do not add them again. There, box A counts purchases with VAT code E or Y and purchases without VAT (**n/v**) with the acquisition VAT typed in the voucher, and boxes B and C count sales without VAT. Trade abroad posted without an order to other accounts, e.g. an EU service bought with code Y on an expense account, is not included, so add it yourself. In older journals without VAT codes on the lines, the voucher\'s VAT decides, so check vouchers with EU and domestic purchases.',
	'Posteringer uten ordre på kontoene ved **Konti uden ordre** er med, også fra en kassekladd, så du skal ikke legge dem til igjen. Der teller rubrikk A kjøp med mva-kode E eller Y og kjøp uten mva (**u/m**) der mva-en av kjøpet er tastet i bilaget, og rubrikk B og C salg uten mva. Handel med utlandet som er bokført uten ordre på andre kontoer, f.eks. et tjenestekjøp i EU med mva-kode Y på en utgiftskonto, er ikke med, så den legger du til selv. I eldre kladder uten mva-koder på linjene avgjør bilagets mva det, så sjekk bilag med både EU-kjøp og innenlandske kjøp.',
));
deleteStaleTekst('help.rp.task.mr2.step4', array(
	'Rubrik C viser kun salg til kunder uden for EU. Andet salg til rubrik C, fx med omvendt betalingspligt i Danmark, lægger du selv til.',
	'Box C only shows sales to customers outside the EU. Other sales for box C, e.g. with domestic reverse charge in Denmark, you add yourself.',
	'Rubrikk C viser bare salg til kunder utenfor EU. Annet salg til rubrikk C, f.eks. med omvendt avgiftsplikt i Danmark, legger du til selv.',
));
deleteStaleTekst('help.rp.task.rp15.step2', array(
	'Beløbene kommer fra fakturerede salgs- og købsordrer. Posteringer direkte i en kassekladde er ikke med.',
	'The amounts come from invoiced sales and purchase orders. Entries made directly in a cash journal are not included.',
	'Beløpene kommer fra fakturerte salgs- og innkjøpsordrer. Posteringer direkte i en kassekladd er ikke med.',
));
deleteStaleTekst('help.saft.task.saft5.step2', array(
	'Saldiene regnes ud fra årets posteringer uden kontoplanens primo. På statuskonti med overført saldo viser filen derfor kun årets bevægelser.',
	'The balances are calculated from the year\'s entries without the opening balance in the chart of accounts. On balance sheet accounts with a balance carried forward, the file therefore shows only the year\'s movements.',
	'Saldoene beregnes ut fra årets posteringer uten kontoplanens primo. På balansekontoer med overført saldo viser filen derfor bare årets bevegelser.',
));

// 20261120 CL/ASR Omgående bogføring of sales or purchase orders is not switched on while invoices of that kind wait for Hent ordrer, and the settings page then says
// to fetch them into a cash journal first (st_hent_first_error() in includes/settings/components.php), so the help no longer says to switch it on and contact
// support about the invoices that were not posted: it says to fetch them with Hent and post the journal first (the creditor order's KO21, the purchase and sales
// settings' about texts, the purchase settings' Q1 and the steps of KB1 and OF3). Rows still holding the old wording are removed so findtekst() re-seeds them.
deleteStaleTekst('help.kord.task.ko21.step1', array(
	'Åbn **Indstillinger** i menuen til venstre. Klik på Kreditorer under Køb og lager, og vælg **Købsordrer**.',
	'Open **Settings** in the menu on the left. Click Creditors under Purchasing and stock, and choose **Purchase orders**.',
	'Åpne **Innstillinger** i menyen til venstre. Klikk på Kreditorer under Innkjøp og lager, og velg **Innkjøpsordrer**.',
));
deleteStaleTekst('help.kord.task.ko21.step2', array(
	'Slå **Omgående bogføring af købsordrer** til, og gem. Så bogføres fakturaen, når du klikker **Bogfør** på ordren.',
	'Turn on **Immediate posting of purchase orders** and save. Then the invoice is posted when you click **Post** on the order.',
	'Slå på **Umiddelbar postering av innkjøpsordrer**, og lagre. Da bokføres fakturaen når du klikker **Bokfør** på ordren.',
));
deleteStaleTekst('help.kord.task.ko21.step3', array(
	'Fakturaer, der allerede er lukket uden at være bogført, bliver ikke bogført af indstillingen. Kontakt support om dem.',
	'Invoices that are already closed without being posted are not posted by the setting. Contact support about them.',
	'Fakturaer som allerede er lukket uten å være bokført, blir ikke bokført av innstillingen. Kontakt support om dem.',
));
deleteStaleTekst('help.skoeb.about.uden_straks', array(
	'Omgående bogføring af købsordrer er slået fra: når du bogfører en købsordre, kommer regningen hverken på kontiene eller i momsen. Slå den til, og kontakt support om de regninger, der allerede er bogført sådan.',
	'Immediate posting of purchase orders is off: when you post a purchase order, the bill is neither on the accounts nor in the VAT. Turn it on, and contact support about the bills already posted this way.',
	'Umiddelbar postering av innkjøpsordrer er slått av: når du bokfører en innkjøpsordre, kommer regningen verken på kontoene eller i mva-en. Slå den på, og kontakt support om regningene som allerede er bokført slik.',
));
deleteStaleTekst('help.skoeb.q.q1.step1', array(
	'Slå **Omgående bogføring af købsordrer** til, og tryk **Gem ændringer**.',
	'Turn on **Immediate posting of purchase orders**, and press **Save changes**.',
	'Slå på **Umiddelbar postering av innkjøpsordrer**, og trykk **Lagre endringer**.',
));
deleteStaleTekst('help.skoeb.q.q1.step2', array(
	'Regninger, der blev bogført, mens den var slået fra, kommer ikke på kontiene af sig selv. Kontakt support om dem.',
	'Bills posted while it was off do not reach the accounts by themselves. Contact support about them.',
	'Regninger som ble bokført mens den var slått av, kommer ikke på kontoene av seg selv. Kontakt support om dem.',
));
deleteStaleTekst('help.skoeb.task.kb1.step3', array(
	'Når den er slået til, kan den ikke slås fra igen. Regninger, der er bogført, mens den var slået fra, kommer ikke på kontiene, når du slår den til, så hent dem ind i kassekladden med **Hent** først, eller kontakt support om dem. Tryk **Gem ændringer**, når du slår den til.',
	'Once it is on, it cannot be turned off again. Bills posted while it was off do not reach the accounts when you turn it on, so fetch them into the cash journal with **Fetch** first, or contact support about them. Press **Save changes** when you turn it on.',
	'Når den er slått på, kan den ikke slås av igjen. Regninger som er bokført mens den var slått av, kommer ikke på kontoene når du slår den på, så hent dem inn i kassekladden med **Hent** først, eller kontakt support om dem. Trykk **Lagre endringer** når du slår den på.',
));
deleteStaleTekst('help.sordre.about.uden_straks', array(
	'Omgående bogføring af salgsordrer er slået fra: nye fakturaer bliver ikke bogført, så de står hverken på kontiene eller i momsen. Slå den til, og kontakt support om de fakturaer, der allerede er lavet.',
	'Immediate posting of sales orders is off: new invoices are not posted, so they are neither on the accounts nor in the VAT. Turn it on, and contact support about the invoices already made.',
	'Umiddelbar postering av salgsordrer er slått av: nye fakturaer blir ikke bokført, så de står verken på kontoene eller i mva-en. Slå den på, og kontakt support om fakturaene som allerede er laget.',
));
deleteStaleTekst('help.sordre.task.of3.step3', array(
	'Når den er slået til, kan den ikke slås fra igen. Fakturaer, der er lavet, mens den var slået fra, bliver ikke bogført, når du slår den til, så hent dem ind i kassekladden med **Hent** først, eller kontakt support om dem. Tryk **Gem ændringer**, når du slår den til.',
	'Once it is on, it cannot be turned off again. Invoices made while it was off are not posted when you turn it on, so fetch them into the cash journal with **Fetch** first, or contact support about them. Press **Save changes** when you turn it on.',
	'Når den er slått på, kan den ikke slås av igjen. Fakturaer som er laget mens den var slått av, blir ikke bokført når du slår den på, så hent dem inn i kassekladden med **Hent** først, eller kontakt support om dem. Trykk **Lagre endringer** når du slår den på.',
));

// 20261120 CL/ASR The about texts of an invoice that is not posted (sales order dord, purchase order kord) said it was because Omgående bogføring is off.
// An invoice fetched into a cash journal, whose journal lines are deleted after both settings are switched on, waits at status 3 too, and the cash journal shows Hent for it (finans/kassekladde.php, owner 20 Nov 2026), so the texts name both causes.
// Rows still holding the old wording are removed so findtekst() re-seeds them.
deleteStaleTekst('help.dord.about.ikke_bogfoert', array(
	'Den er lavet, men ikke bogført på kontiene endnu, fordi omgående bogføring af salgsordrer er slået fra i dette regnskab.',
	'It is made but not posted to the accounts yet, because immediate posting of sales orders is turned off in these accounts.',
	'Den er laget, men ikke bokført på kontoene ennå, fordi umiddelbar postering av salgsordrer er slått av i dette regnskapet.',
));
deleteStaleTekst('help.kord.about.ikke_bogfoert', array(
	'Fakturaen står ikke i regnskabet endnu, fordi **Omgående bogføring af købsordrer** er slået fra i indstillingerne.',
	'The invoice is not in the accounts yet, because **Immediate posting of purchase orders** is turned off in the settings.',
	'Fakturaen står ikke i regnskapet ennå, fordi **Umiddelbar postering av innkjøpsordrer** er slått av i innstillingene.',
));

?>
