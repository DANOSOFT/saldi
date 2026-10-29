<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_func/operatorPanelSchema.php --- ver 5.1.0 --- 2026-10-29 ---
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
// 20261029 CL/ASR New. The master-database tables of the operator panel (SD-731): partners and their links, the
//                  entitlement catalogue and per-account plans, usage snapshots, billing summaries, proposals, trials,
//                  lifecycle, self-hosted installations and customizations, plus regnskab.lukkes_kommentar.
//                  Called from includes/betweenUpdates.php (existing installs, on the next login to any account) and
//                  from index/install.php (new installs). It runs once per master: a settings flag records the
//                  schema version, so later logins only read that flag. All statements use the master connection
//                  (db_modify/db_select with $global = true), so it works from an account login and from install.php.

if (!function_exists('operator_panel_schema')) {
	define('OPERATOR_PANEL_SCHEMA', '1');

	/**
	 * Create or complete the operator panel's tables in the master database. Idempotent.
	 *
	 * @return bool true when the schema was applied in this call, false when it was already current
	 */
	function operator_panel_schema() {
		$flag = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_grp = 'system' AND var_name = 'operator_panel_schema'", __FILE__ . " linje " . __LINE__, true));
		if ($flag && $flag['var_value'] >= OPERATOR_PANEL_SCHEMA) return false;
		$tables = array(
		"CREATE TABLE IF NOT EXISTS partners (id SERIAL PRIMARY KEY, kind varchar(10) NOT NULL DEFAULT 'bogholder', name varchar(120) NOT NULL, cvr varchar(20), home_regnskab_id int, status varchar(10) NOT NULL DEFAULT 'active', can_create boolean NOT NULL DEFAULT false, can_manage_features boolean NOT NULL DEFAULT true, can_manage_users boolean NOT NULL DEFAULT true, created timestamp NOT NULL DEFAULT now(), created_by int)",
		"CREATE TABLE IF NOT EXISTS partner_users (bruger_id int PRIMARY KEY, partner_id int NOT NULL, partner_role varchar(20) NOT NULL DEFAULT 'employee', created timestamp NOT NULL DEFAULT now())",
		"CREATE TABLE IF NOT EXISTS regnskab_partners (id SERIAL PRIMARY KEY, regnskab_id int NOT NULL, partner_id int NOT NULL, access varchar(10) NOT NULL DEFAULT 'full', since timestamp NOT NULL DEFAULT now(), until timestamp, created_by int, note text)",
		"CREATE UNIQUE INDEX IF NOT EXISTS regnskab_partners_active ON regnskab_partners(regnskab_id, partner_id) WHERE until IS NULL",
		"CREATE UNIQUE INDEX IF NOT EXISTS regnskab_partners_owner ON regnskab_partners(regnskab_id) WHERE until IS NULL AND access = 'owner'",
		"CREATE TABLE IF NOT EXISTS partner_user_access (bruger_id int NOT NULL, regnskab_id int NOT NULL, PRIMARY KEY (bruger_id, regnskab_id))",
		"CREATE TABLE IF NOT EXISTS partner_log (id SERIAL PRIMARY KEY, ts timestamp NOT NULL DEFAULT now(), actor_bruger_id int, actor_partner_id int, impersonated_by int, regnskab_id int, partner_id int, action varchar(60) NOT NULL, details text)",
		"CREATE TABLE IF NOT EXISTS catalog_versions (id SERIAL PRIMARY KEY, name text NOT NULL, valid_from date NOT NULL, is_default boolean NOT NULL DEFAULT false, created_at timestamp DEFAULT now())",
		"CREATE TABLE IF NOT EXISTS plans (id SERIAL PRIMARY KEY, catalog_version_id int NOT NULL, plan_key varchar(30) NOT NULL, name text NOT NULL, sort int NOT NULL, stripe_price_id varchar(255), varenr varchar(30), included_users int NOT NULL, max_users int, included_postings int, max_items int, included_webshops int NOT NULL DEFAULT 0, extra_user_stripe_price_id varchar(255), extra_user_varenr varchar(30), price_ore int, billing_interval varchar(10) NOT NULL DEFAULT 'month', extra_user_price_ore int, UNIQUE (catalog_version_id, plan_key))",
		"CREATE TABLE IF NOT EXISTS plan_features (plan_id int NOT NULL, feature_key varchar(50) NOT NULL, level varchar(10) NOT NULL, PRIMARY KEY (plan_id, feature_key))",
		"CREATE TABLE IF NOT EXISTS addons (id SERIAL PRIMARY KEY, catalog_version_id int NOT NULL, addon_key varchar(50) NOT NULL, name text NOT NULL, feature_key varchar(50) NOT NULL, quantity_based boolean NOT NULL DEFAULT false, stripe_price_id varchar(255), varenr varchar(30), available_on text NOT NULL, price_ore int, UNIQUE (catalog_version_id, addon_key))",
		"CREATE TABLE IF NOT EXISTS posting_tiers (catalog_version_id int NOT NULL, from_count int NOT NULL, to_count int, unit_centiore int NOT NULL, varenr varchar(30))",
		"CREATE TABLE IF NOT EXISTS regnskab_entitlements (regnskab_id int PRIMARY KEY, catalog_version_id int, plan_key varchar(30), source varchar(20) NOT NULL DEFAULT 'manual', stripe_customer_id varchar(255), stripe_subscription_id varchar(255), subscription_status varchar(30), current_period_end timestamp, enforcement varchar(10) NOT NULL DEFAULT 'observe', valid_from date, synced_at timestamp, updated_at timestamp DEFAULT now())",
		"CREATE TABLE IF NOT EXISTS regnskab_addons (regnskab_id int NOT NULL, addon_key varchar(50) NOT NULL, quantity int NOT NULL DEFAULT 1, source varchar(20) NOT NULL DEFAULT 'manual', stripe_subscription_item_id varchar(255), expires_at date, PRIMARY KEY (regnskab_id, addon_key))",
		"CREATE TABLE IF NOT EXISTS regnskab_overrides (id SERIAL PRIMARY KEY, regnskab_id int NOT NULL, kind varchar(20) NOT NULL, key varchar(50) NOT NULL, value text NOT NULL, reason text NOT NULL, expires_at date, created_by text NOT NULL, created_at timestamp DEFAULT now())",
		"CREATE TABLE IF NOT EXISTS usage_snapshots (id SERIAL PRIMARY KEY, regnskab_id int NOT NULL, taken_at date NOT NULL, postings_12m int, postings_month int, users_active int, users_invited int, items int, webshops int, pos_tills int, pos_locations int, warehouses int, billable_postings int, billable_users int, UNIQUE (regnskab_id, taken_at))",
		"CREATE TABLE IF NOT EXISTS regnskab_plan_changes (id SERIAL PRIMARY KEY, regnskab_id int NOT NULL, plan_key varchar(30) NOT NULL, effective_from date NOT NULL, reason text, created_by text NOT NULL, created_at timestamp DEFAULT now(), applied_at timestamp, cancelled_at timestamp)",
		"CREATE TABLE IF NOT EXISTS regnskab_billing (regnskab_id int PRIMARY KEY, billing_kontonr varchar(30), channel varchar(20), payer_name varchar(120), payer_email varchar(120), note text, updated_by text, updated_at timestamp DEFAULT now())",
		"CREATE TABLE IF NOT EXISTS partner_proposals (id SERIAL PRIMARY KEY, regnskab_id int NOT NULL, partner_id int, proposed_by text NOT NULL, kind varchar(10) NOT NULL, key varchar(50) NOT NULL, reason text, status varchar(10) NOT NULL DEFAULT 'open', decided_by varchar(60), decided_at timestamp, created timestamp NOT NULL DEFAULT now(), token varchar(64), sent_to varchar(120), expires_at date)",
		"CREATE TABLE IF NOT EXISTS regnskab_trial (regnskab_id int PRIMARY KEY, started date NOT NULL, ends date NOT NULL, source varchar(10), followup varchar(12) NOT NULL DEFAULT 'open', note text, contacted date, resolved varchar(10), resolved_at timestamp, updated_at timestamp, updated_by text)",
		"CREATE TABLE IF NOT EXISTS regnskab_lifecycle (id SERIAL PRIMARY KEY, regnskab_id int NOT NULL, state varchar(12) NOT NULL, reason text, effective_from date NOT NULL, applied_at timestamp, cancelled_at timestamp, created_by text NOT NULL, created_at timestamp DEFAULT now())",
		"CREATE TABLE IF NOT EXISTS self_hosted (id SERIAL PRIMARY KEY, name varchar(120) NOT NULL, cvr varchar(20), contact_name varchar(120), contact_email varchar(120), contact_phone varchar(40), url varchar(255), version varchar(20), support_level varchar(20), support_from date, support_to date, price_ore int, billing_interval varchar(10) NOT NULL DEFAULT 'year', billing_kontonr varchar(30), note text, status varchar(10) NOT NULL DEFAULT 'active', pay_status varchar(12), outstanding_ore bigint, overdue_n int, oldest_due date, last_invoice date, last_paid date, checked_at timestamp, created timestamp NOT NULL DEFAULT now(), created_by text, updated_at timestamp)",
		"CREATE TABLE IF NOT EXISTS customizations (id SERIAL PRIMARY KEY, regnskab_id int, self_hosted_id int, title varchar(160) NOT NULL, category varchar(20), description text, reference varchar(255), delivered date, important boolean NOT NULL DEFAULT false, status varchar(10) NOT NULL DEFAULT 'active', created timestamp NOT NULL DEFAULT now(), created_by text, updated timestamp, updated_by text)",
		"CREATE INDEX IF NOT EXISTS customizations_regnskab ON customizations (regnskab_id)",
		);
		foreach ($tables as $qtxt) db_modify($qtxt, __FILE__ . " linje " . __LINE__, true);
		// columns added after the first release of a table: [table, column that marks the step, statement, optional data step]
		$columns = array(
		array('partner_users', 'rights', "ALTER TABLE partner_users ADD COLUMN rights text, ADD COLUMN scope varchar(10) NOT NULL DEFAULT 'all', ADD COLUMN active boolean NOT NULL DEFAULT true, ADD COLUMN name varchar(120), ADD COLUMN last_seen timestamp", "UPDATE partner_users SET scope = 'selected' WHERE bruger_id IN (SELECT bruger_id FROM partner_user_access)"), # existing selections stay selections
		array('regnskab_partners', 'responsible_bruger_id', "ALTER TABLE regnskab_partners ADD COLUMN responsible_bruger_id int, ADD COLUMN payer varchar(10) NOT NULL DEFAULT 'customer'"),
		array('partners', 'billing_mode', "ALTER TABLE partners ADD COLUMN billing_mode varchar(12) NOT NULL DEFAULT 'individual', ADD COLUMN billing_regnskab_id int"),
		array('regnskab_billing', 'pay_status', "ALTER TABLE regnskab_billing ADD COLUMN pay_status varchar(12), ADD COLUMN outstanding_ore bigint, ADD COLUMN overdue_n int, ADD COLUMN oldest_due date, ADD COLUMN last_invoice date, ADD COLUMN checked_at timestamp"),
		array('regnskab_billing', 'payer_regnskab_id', "ALTER TABLE regnskab_billing ADD COLUMN payer_regnskab_id int"),
		array('regnskab', 'lukkes_kommentar', "ALTER TABLE regnskab ADD COLUMN lukkes_kommentar text"),
		);
		foreach ($columns as $c) {
			$has = db_fetch_array(db_select("SELECT 1 FROM information_schema.columns WHERE table_name = '$c[0]' AND column_name = '$c[1]'", __FILE__ . " linje " . __LINE__, true));
			if ($has) continue;
			db_modify($c[2], __FILE__ . " linje " . __LINE__, true);
			if (!empty($c[3])) db_modify($c[3], __FILE__ . " linje " . __LINE__, true);
		}
		if ($flag) db_modify("UPDATE settings SET var_value = '".OPERATOR_PANEL_SCHEMA."' WHERE var_grp = 'system' AND var_name = 'operator_panel_schema'", __FILE__ . " linje " . __LINE__, true);
		else db_modify("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ('operator_panel_schema', 'system', '".OPERATOR_PANEL_SCHEMA."', 'Schema version of the operator panel tables (includes/opdat_func/operatorPanelSchema.php)')", __FILE__ . " linje " . __LINE__, true);
		return true;
	}
}
?>
