<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_func/operatorPanelSchema.php --- ver 5.1.1 --- 2026-11-16 ---
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
// 20261029 CL/ASR New. The master-database tables of the operator panel (SD-731): partners and their links, the entitlement catalogue and per-account plans, usage snapshots, billing summaries, proposals, trials, lifecycle, self-hosted installations and customizations, plus regnskab.lukkes_kommentar.
//                 Called from includes/betweenUpdates.php (existing installs, on the next login to any account) and from index/install.php (new installs).
//                 It runs once per master: a settings flag records the schema version, so later logins only read that flag.
//                 All statements use the master connection (db_modify/db_select with $global = true), so it works from an account login and from install.php.
//                 An advisory lock lets one login apply it at a time, and the flag is only set once every table and column is there.
// 20261109 CL/ASR PostgreSQL only (review #785): on a MySQL/MariaDB master operator_panel_schema() returns at once, with no alert, no mail and no flag.
//                 operator_panel_ready() is the one check the panel's entry points use: a PostgreSQL master with the schema flag at the current version.
//                 partner_users.scope comes in with default 'selected', is set to 'all' for employees without a selection, and only then defaults to 'all', so a run that stops halfway never widens anyone's access.
//                 The information_schema lookups are limited to current_schema().
// 20261116 CL/ASR The flag is set only when the indexes exist as well (review #785).
//                 Before the two unique indexes on regnskab_partners are created, duplicate active links (possible where an earlier panel version made the tables) are ended and a second active owner of an account becomes 'full', keeping the oldest link, so the indexes can be created.
//                 Schema version 2, so a master flagged at 1 runs the step once more.
// 20261116 CL/ASR MySQL/MariaDB masters get the panel too (review #785, SirRolin); the statements come from operatorPanelDialect.php and databaseDialect.php.
//                 The id columns, timestamps and the reserved column key follow the dialect, and the metadata lookups use opdat_schema_filter() and opdat_index_query().
//                 MySQL/MariaDB has no partial index: two stored generated columns, 1 for an active link (and for an active owner link) and NULL otherwise, carry the same two unique rules.
//                 The schema lock is op_lock() (pg_try_advisory_lock() or GET_LOCK() with no wait), released on every way out.

if (!function_exists('operator_panel_schema')) {
	define('OPERATOR_PANEL_SCHEMA', '2');
	define('OPERATOR_PANEL_SCHEMA_LOCK', 'operator_panel_schema'); # op_lock() name (SD-731)
	include_once(__DIR__ . '/operatorPanelDialect.php');

	/**
	 * True when the panel can be used: a master whose panel tables are complete (the settings flag is at the
	 * current schema version). index/admin_menu.php, admin/admin_panel.php, index/logud.php and partner_layer_ready()
	 * (includes/partnerScope.php) check it; while it is false they keep to the classic pages and read none of the panel's tables.
	 *
	 * @return bool
	 */
	function operator_panel_ready() {
		static $ready = false; # only a yes is kept: the schema step may set the flag later in the same request
		if (!$ready) {
			$flag = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_grp = 'system' AND var_name = 'operator_panel_schema'", __FILE__ . " linje " . __LINE__, true));
			$ready = ($flag && $flag['var_value'] >= OPERATOR_PANEL_SCHEMA);
		}
		return $ready;
	}

	/**
	 * partner_users.scope still waits for its backfill: the column is there, but its default is not 'all' yet.
	 *
	 * @return bool
	 */
	function operator_panel_scope_pending() {
		$r = db_fetch_array(db_select("SELECT column_default AS d FROM information_schema.columns WHERE table_name = 'partner_users' AND column_name = 'scope'" . opdat_schema_filter(op_db_type()), __FILE__ . " linje " . __LINE__, true));
		# PostgreSQL gives 'all'::character varying, MariaDB 'all', MySQL all
		return $r && !preg_match("/^'?all'?(::|$)/", (string)$r['d']);
	}

	/**
	 * Create or complete the operator panel's tables in the master database. Idempotent.
	 *
	 * @return bool true when the schema was applied in this call, false when it was already current
	 */
	function operator_panel_schema() {
		$flag = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_grp = 'system' AND var_name = 'operator_panel_schema'", __FILE__ . " linje " . __LINE__, true));
		if ($flag && $flag['var_value'] >= OPERATOR_PANEL_SCHEMA) return false;

		// Two logins at the same moment: only one applies the schema, the other carries on and the next login sees the flag.
		if (!op_lock(OPERATOR_PANEL_SCHEMA_LOCK, 0, true)) return false;
		try {
			return operator_panel_schema_apply();
		} finally {
			op_unlock(OPERATOR_PANEL_SCHEMA_LOCK, true);
		}
	}

	/**
	 * The schema step itself, run by operator_panel_schema() while it holds the lock.
	 *
	 * @return bool true when the schema was applied in this call
	 */
	function operator_panel_schema_apply() {
		$dt = op_db_type(); $my = op_is_mysql();
		$flag = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_grp = 'system' AND var_name = 'operator_panel_schema'", __FILE__ . " linje " . __LINE__, true));
		if ($flag && $flag['var_value'] >= OPERATOR_PANEL_SCHEMA) return false;
		$id = opdat_id_column($dt); $ts = op_ts(); $tsNow = opdat_timestamp_column($dt); $tsNowNN = opdat_timestamp_column($dt, true); $key = op_key();
		$sf = opdat_schema_filter($dt);
		$tables = array(
			"CREATE TABLE IF NOT EXISTS partners ($id, kind varchar(10) NOT NULL DEFAULT 'bogholder', name varchar(120) NOT NULL, cvr varchar(20), home_regnskab_id int, status varchar(10) NOT NULL DEFAULT 'active', can_create boolean NOT NULL DEFAULT false, can_manage_features boolean NOT NULL DEFAULT true, can_manage_users boolean NOT NULL DEFAULT true, created $tsNowNN, created_by int)",
			"CREATE TABLE IF NOT EXISTS partner_users (bruger_id int PRIMARY KEY, partner_id int NOT NULL, partner_role varchar(20) NOT NULL DEFAULT 'employee', created $tsNowNN)",
			"CREATE TABLE IF NOT EXISTS regnskab_partners ($id, regnskab_id int NOT NULL, partner_id int NOT NULL, access varchar(10) NOT NULL DEFAULT 'full', since $tsNowNN, until $ts, created_by int, note text".($my ? ", active_one TINYINT AS (IF(until IS NULL, 1, NULL)) STORED, owner_one TINYINT AS (IF(until IS NULL AND access = 'owner', 1, NULL)) STORED" : "").")",
			// 20261116 CL/ASR duplicates an earlier panel version may have left would make the two unique indexes fail: the oldest active link stays
			"UPDATE regnskab_partners SET until = now() WHERE until IS NULL AND id NOT IN (SELECT k.id FROM (SELECT min(id) AS id FROM regnskab_partners WHERE until IS NULL GROUP BY regnskab_id, partner_id) k)",
			"UPDATE regnskab_partners SET access = 'full' WHERE until IS NULL AND access = 'owner' AND id NOT IN (SELECT k.id FROM (SELECT min(id) AS id FROM regnskab_partners WHERE until IS NULL AND access = 'owner' GROUP BY regnskab_id) k)",
			"CREATE TABLE IF NOT EXISTS partner_user_access (bruger_id int NOT NULL, regnskab_id int NOT NULL, PRIMARY KEY (bruger_id, regnskab_id))",
			"CREATE TABLE IF NOT EXISTS partner_log ($id, ts $tsNowNN, actor_bruger_id int, actor_partner_id int, impersonated_by int, regnskab_id int, partner_id int, action varchar(60) NOT NULL, details text)",
			"CREATE TABLE IF NOT EXISTS catalog_versions ($id, name text NOT NULL, valid_from date NOT NULL, is_default boolean NOT NULL DEFAULT false, created_at $tsNow)",
			"CREATE TABLE IF NOT EXISTS plans ($id, catalog_version_id int NOT NULL, plan_key varchar(30) NOT NULL, name text NOT NULL, sort int NOT NULL, stripe_price_id varchar(255), varenr varchar(30), included_users int NOT NULL, max_users int, included_postings int, max_items int, included_webshops int NOT NULL DEFAULT 0, extra_user_stripe_price_id varchar(255), extra_user_varenr varchar(30), price_ore int, billing_interval varchar(10) NOT NULL DEFAULT 'month', extra_user_price_ore int, UNIQUE (catalog_version_id, plan_key))",
			"CREATE TABLE IF NOT EXISTS plan_features (plan_id int NOT NULL, feature_key varchar(50) NOT NULL, level varchar(10) NOT NULL, PRIMARY KEY (plan_id, feature_key))",
			"CREATE TABLE IF NOT EXISTS addons ($id, catalog_version_id int NOT NULL, addon_key varchar(50) NOT NULL, name text NOT NULL, feature_key varchar(50) NOT NULL, quantity_based boolean NOT NULL DEFAULT false, stripe_price_id varchar(255), varenr varchar(30), available_on text NOT NULL, price_ore int, UNIQUE (catalog_version_id, addon_key))",
			"CREATE TABLE IF NOT EXISTS posting_tiers (catalog_version_id int NOT NULL, from_count int NOT NULL, to_count int, unit_centiore int NOT NULL, varenr varchar(30))",
			"CREATE TABLE IF NOT EXISTS regnskab_entitlements (regnskab_id int PRIMARY KEY, catalog_version_id int, plan_key varchar(30), source varchar(20) NOT NULL DEFAULT 'manual', stripe_customer_id varchar(255), stripe_subscription_id varchar(255), subscription_status varchar(30), current_period_end $ts, enforcement varchar(10) NOT NULL DEFAULT 'observe', valid_from date, synced_at $ts, updated_at $tsNow)",
			"CREATE TABLE IF NOT EXISTS regnskab_addons (regnskab_id int NOT NULL, addon_key varchar(50) NOT NULL, quantity int NOT NULL DEFAULT 1, source varchar(20) NOT NULL DEFAULT 'manual', stripe_subscription_item_id varchar(255), expires_at date, PRIMARY KEY (regnskab_id, addon_key))",
			"CREATE TABLE IF NOT EXISTS regnskab_overrides ($id, regnskab_id int NOT NULL, kind varchar(20) NOT NULL, $key varchar(50) NOT NULL, value text NOT NULL, reason text NOT NULL, expires_at date, created_by text NOT NULL, created_at $tsNow)",
			"CREATE TABLE IF NOT EXISTS usage_snapshots ($id, regnskab_id int NOT NULL, taken_at date NOT NULL, postings_12m int, postings_month int, users_active int, users_invited int, items int, webshops int, pos_tills int, pos_locations int, warehouses int, billable_postings int, billable_users int, UNIQUE (regnskab_id, taken_at))",
			"CREATE TABLE IF NOT EXISTS regnskab_plan_changes ($id, regnskab_id int NOT NULL, plan_key varchar(30) NOT NULL, effective_from date NOT NULL, reason text, created_by text NOT NULL, created_at $tsNow, applied_at $ts, cancelled_at $ts)",
			"CREATE TABLE IF NOT EXISTS regnskab_billing (regnskab_id int PRIMARY KEY, billing_kontonr varchar(30), channel varchar(20), payer_name varchar(120), payer_email varchar(120), note text, updated_by text, updated_at $tsNow)",
			"CREATE TABLE IF NOT EXISTS partner_proposals ($id, regnskab_id int NOT NULL, partner_id int, proposed_by text NOT NULL, kind varchar(10) NOT NULL, $key varchar(50) NOT NULL, reason text, status varchar(10) NOT NULL DEFAULT 'open', decided_by varchar(60), decided_at $ts, created $tsNowNN, token varchar(64), sent_to varchar(120), expires_at date)",
			"CREATE TABLE IF NOT EXISTS regnskab_trial (regnskab_id int PRIMARY KEY, started date NOT NULL, ends date NOT NULL, source varchar(10), followup varchar(12) NOT NULL DEFAULT 'open', note text, contacted date, resolved varchar(10), resolved_at $ts, updated_at $ts, updated_by text)",
			"CREATE TABLE IF NOT EXISTS regnskab_lifecycle ($id, regnskab_id int NOT NULL, state varchar(12) NOT NULL, reason text, effective_from date NOT NULL, applied_at $ts, cancelled_at $ts, created_by text NOT NULL, created_at $tsNow)",
			"CREATE TABLE IF NOT EXISTS self_hosted ($id, name varchar(120) NOT NULL, cvr varchar(20), contact_name varchar(120), contact_email varchar(120), contact_phone varchar(40), url varchar(255), version varchar(20), support_level varchar(20), support_from date, support_to date, price_ore int, billing_interval varchar(10) NOT NULL DEFAULT 'year', billing_kontonr varchar(30), note text, status varchar(10) NOT NULL DEFAULT 'active', pay_status varchar(12), outstanding_ore bigint, overdue_n int, oldest_due date, last_invoice date, last_paid date, checked_at $ts, created $tsNowNN, created_by text, updated_at $ts)",
			"CREATE TABLE IF NOT EXISTS customizations ($id, regnskab_id int, self_hosted_id int, title varchar(160) NOT NULL, category varchar(20), description text, reference varchar(255), delivered date, important boolean NOT NULL DEFAULT false, status varchar(10) NOT NULL DEFAULT 'active', created $tsNowNN, created_by text, updated $ts, updated_by text)",
		);
		foreach ($tables as $qtxt) db_modify($qtxt, __FILE__ . " linje " . __LINE__, true);
		// name => [table, PostgreSQL, MySQL/MariaDB]. MySQL/MariaDB has no partial index: the stored generated columns
		// active_one and owner_one are 1 for an active (owner) link and NULL otherwise, and NULLs never collide in a unique index.
		$indexes = array(
			'regnskab_partners_active' => array('regnskab_partners', "CREATE UNIQUE INDEX regnskab_partners_active ON regnskab_partners (regnskab_id, partner_id) WHERE until IS NULL", "CREATE UNIQUE INDEX regnskab_partners_active ON regnskab_partners (regnskab_id, partner_id, active_one)"),
			'regnskab_partners_owner' => array('regnskab_partners', "CREATE UNIQUE INDEX regnskab_partners_owner ON regnskab_partners (regnskab_id) WHERE until IS NULL AND access = 'owner'", "CREATE UNIQUE INDEX regnskab_partners_owner ON regnskab_partners (regnskab_id, owner_one)"),
			'customizations_regnskab' => array('customizations', "CREATE INDEX customizations_regnskab ON customizations (regnskab_id)", "CREATE INDEX customizations_regnskab ON customizations (regnskab_id)"),
		);
		foreach ($indexes as $ix => $d) {
			if (!db_fetch_array(db_select(opdat_index_query($dt, $d[0], $ix), __FILE__ . " linje " . __LINE__, true))) db_modify($my ? $d[2] : $d[1], __FILE__ . " linje " . __LINE__, true);
		}

		// columns added after the first release of a table: [table, column that marks the step, statement]
		$columns = array(
			array('partner_users', 'rights', "ALTER TABLE partner_users ADD COLUMN rights text, ADD COLUMN scope varchar(10) NOT NULL DEFAULT 'selected', ADD COLUMN active boolean NOT NULL DEFAULT true, ADD COLUMN name varchar(120), ADD COLUMN last_seen $ts"), # scope: see the backfill below
			array('regnskab_partners', 'responsible_bruger_id', "ALTER TABLE regnskab_partners ADD COLUMN responsible_bruger_id int, ADD COLUMN payer varchar(10) NOT NULL DEFAULT 'customer'"),
			array('partners', 'billing_mode', "ALTER TABLE partners ADD COLUMN billing_mode varchar(12) NOT NULL DEFAULT 'individual', ADD COLUMN billing_regnskab_id int"),
			array('regnskab_billing', 'pay_status', "ALTER TABLE regnskab_billing ADD COLUMN pay_status varchar(12), ADD COLUMN outstanding_ore bigint, ADD COLUMN overdue_n int, ADD COLUMN oldest_due date, ADD COLUMN last_invoice date, ADD COLUMN checked_at $ts"),
			array('regnskab_billing', 'payer_regnskab_id', "ALTER TABLE regnskab_billing ADD COLUMN payer_regnskab_id int"),
			array('regnskab', 'lukkes_kommentar', "ALTER TABLE regnskab ADD COLUMN lukkes_kommentar text"),
		);
		foreach ($columns as $c) {
			$has = db_fetch_array(db_select("SELECT 1 FROM information_schema.columns WHERE table_name = '$c[0]' AND column_name = '$c[1]'$sf", __FILE__ . " linje " . __LINE__, true));
			if ($has) continue;
			db_modify($c[2], __FILE__ . " linje " . __LINE__, true);
		}

		// partner_users.scope (review #785): employees without a selection in partner_user_access keep seeing all of their
		// partner's customers ('all'), those with one keep it ('selected'). The column comes in as 'selected', so a run that
		// stops before this step limits access instead of widening it. The default turns 'all' only when no employee without
		// a selection is left on 'selected'; that default marks the step as done, so the next login finishes a step cut short.
		if (operator_panel_scope_pending()) {
			db_modify("UPDATE partner_users pu SET scope = 'all' WHERE pu.scope <> 'all' AND NOT EXISTS (SELECT 1 FROM partner_user_access pua WHERE pua.bruger_id = pu.bruger_id)", __FILE__ . " linje " . __LINE__, true);
			if (!db_fetch_array(db_select("SELECT 1 FROM partner_users pu WHERE pu.scope <> 'all' AND NOT EXISTS (SELECT 1 FROM partner_user_access pua WHERE pua.bruger_id = pu.bruger_id) LIMIT 1", __FILE__ . " linje " . __LINE__, true))) db_modify("ALTER TABLE partner_users ALTER COLUMN scope SET DEFAULT 'all'", __FILE__ . " linje " . __LINE__, true);
		}
		
		// db_modify() only logs and mails a failed statement here (includes/opdat), so check the result before the flag
		// is set; with something missing the flag stays unset and the next login tries again.
		$missing = 0;
		foreach ($tables as $qtxt) {
			if (preg_match('/^CREATE TABLE IF NOT EXISTS (\w+)/', $qtxt, $m) && !db_fetch_array(db_select("SELECT 1 FROM information_schema.tables WHERE table_name = '$m[1]'$sf", __FILE__ . " linje " . __LINE__, true))) $missing++;
		}
		foreach ($indexes as $ix => $d) {
			if (!db_fetch_array(db_select(opdat_index_query($dt, $d[0], $ix), __FILE__ . " linje " . __LINE__, true))) $missing++;
		}
		foreach ($columns as $c) {
			if (!db_fetch_array(db_select("SELECT 1 FROM information_schema.columns WHERE table_name = '$c[0]' AND column_name = '$c[1]'$sf", __FILE__ . " linje " . __LINE__, true))) $missing++;
		}
		if (operator_panel_scope_pending()) $missing++;
		if ($missing) return false;
		if ($flag) db_modify("UPDATE settings SET var_value = '".OPERATOR_PANEL_SCHEMA."' WHERE var_grp = 'system' AND var_name = 'operator_panel_schema'", __FILE__ . " linje " . __LINE__, true);
		else db_modify("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ('operator_panel_schema', 'system', '".OPERATOR_PANEL_SCHEMA."', 'Schema version of the operator panel tables (includes/opdat_func/operatorPanelSchema.php)')", __FILE__ . " linje " . __LINE__, true);
		return true;
	}
}
?>
