<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_func/partnerPanelSchema.php --- ver 5.1.0 --- 2026-10-29 ---
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
// 20261029 CL/ASR New. The master-database tables of the accountant panel (admin/bogholder_*.php): invitations, access
//                  requests, requests to Saldi, favourites, recently opened, the work cache per customer and the
//                  provisional volume tiers, plus the firm's contact and invoicing columns on partners. Builds on the
//                  operator panel's tables (operatorPanelSchema.php runs first in betweenUpdates.php). Called from
//                  includes/betweenUpdates.php and index/install.php; runs once per master (settings flag), all
//                  statements on the master connection ($global = true).

if (!function_exists('partner_panel_schema')) {
	define('PARTNER_PANEL_SCHEMA', '1');

	/**
	 * Create the accountant panel's tables in the master database. Idempotent.
	 *
	 * @return bool true when the schema was applied in this call, false when it was already current
	 */
	function partner_panel_schema() {
		$flag = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_grp = 'system' AND var_name = 'partner_panel_schema'", __FILE__ . " linje " . __LINE__, true));
		if ($flag && $flag['var_value'] >= PARTNER_PANEL_SCHEMA) return false;
		$tables = array(
		"CREATE TABLE IF NOT EXISTS partner_invites (id SERIAL PRIMARY KEY, partner_id int NOT NULL, kind varchar(16) NOT NULL, regnskab_id int, name varchar(120), email varchar(120) NOT NULL, partner_role varchar(20), rights text, scope text, role varchar(16), message text, token varchar(64) NOT NULL, status varchar(12) NOT NULL DEFAULT 'sent', created_by int, created timestamp NOT NULL DEFAULT now(), sent_at timestamp, expires_at date NOT NULL, accepted_at timestamp, result_user_id int, replaces_user_id int)",
		"CREATE TABLE IF NOT EXISTS partner_access_requests (id SERIAL PRIMARY KEY, partner_id int NOT NULL, regnskab_id int NOT NULL, requested_by int, message text, token varchar(64) NOT NULL, sent_to varchar(120), status varchar(12) NOT NULL DEFAULT 'open', created timestamp NOT NULL DEFAULT now(), sent_at timestamp, expires_at date NOT NULL, decided_at timestamp, decided_by varchar(60))",
		"CREATE TABLE IF NOT EXISTS partner_requests (id SERIAL PRIMARY KEY, partner_id int NOT NULL, regnskab_id int NOT NULL, kind varchar(24) NOT NULL, details text, effective_from date, token varchar(64), sent_to varchar(120), status varchar(12) NOT NULL DEFAULT 'open', created_by int, created timestamp NOT NULL DEFAULT now(), decided_at timestamp, decided_by varchar(60), note text)",
		"CREATE TABLE IF NOT EXISTS partner_favorites (bruger_id int NOT NULL, regnskab_id int NOT NULL, added timestamp NOT NULL DEFAULT now(), PRIMARY KEY (bruger_id, regnskab_id))",
		"CREATE TABLE IF NOT EXISTS partner_recent (bruger_id int NOT NULL, regnskab_id int NOT NULL, opened_at timestamp NOT NULL DEFAULT now(), PRIMARY KEY (bruger_id, regnskab_id))",
		"CREATE TABLE IF NOT EXISTS partner_ledger_cache (regnskab_id int PRIMARY KEY, firma varchar(120), cvr varchar(20), email varchar(120), tlf varchar(40), addr1 varchar(120), postnr varchar(10), bynavn varchar(60), kontakt varchar(120), open_journals int, journal_lines int, pool_docs int, users_active int, checked_at timestamp)",
		"CREATE TABLE IF NOT EXISTS partner_pricing_tiers (id SERIAL PRIMARY KEY, min_count int NOT NULL, max_count int, discount_pct numeric(5,2) NOT NULL DEFAULT 0, note text)",
		);
		foreach ($tables as $qtxt) db_modify($qtxt, __FILE__ . " linje " . __LINE__, true);
		if (!db_fetch_array(db_select("SELECT 1 FROM information_schema.columns WHERE table_name = 'partners' AND column_name = 'contact_name'", __FILE__ . " linje " . __LINE__, true)))
			db_modify("ALTER TABLE partners ADD COLUMN contact_name varchar(120), ADD COLUMN contact_email varchar(120), ADD COLUMN contact_phone varchar(40), ADD COLUMN address varchar(120), ADD COLUMN zip varchar(10), ADD COLUMN city varchar(60), ADD COLUMN billing_email varchar(120), ADD COLUMN billing_ref varchar(60)", __FILE__ . " linje " . __LINE__, true);
		// provisional volume tiers for the bookkeeper scheme (data, only into an empty table)
		if (!db_fetch_array(db_select("SELECT 1 FROM partner_pricing_tiers LIMIT 1", __FILE__ . " linje " . __LINE__, true)))
			foreach (array(array(1, 4, 0), array(5, 9, 10), array(10, 24, 15), array(25, 49, 20), array(50, null, 25)) as $t) db_modify("INSERT INTO partner_pricing_tiers (min_count, max_count, discount_pct, note) VALUES ('$t[0]', ".($t[1] === null ? 'NULL' : "'$t[1]'").", '$t[2]', 'foreløbig sats')", __FILE__ . " linje " . __LINE__, true);
		if ($flag) db_modify("UPDATE settings SET var_value = '".PARTNER_PANEL_SCHEMA."' WHERE var_grp = 'system' AND var_name = 'partner_panel_schema'", __FILE__ . " linje " . __LINE__, true);
		else db_modify("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ('partner_panel_schema', 'system', '".PARTNER_PANEL_SCHEMA."', 'Schema version of the accountant panel tables (includes/opdat_func/partnerPanelSchema.php)')", __FILE__ . " linje " . __LINE__, true);
		return true;
	}
}
?>
