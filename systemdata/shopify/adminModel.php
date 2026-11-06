<?php
// --- systemdata/shopify/adminModel.php --- ver 5.1.0 --- 2026-11-02 ---
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
// Reads of the Shopify admin pages (WP04) that WP01's config.php has no reader for: the tenant's
// lagre with their roles, departments, items by number, and whether a secret is set (never its
// value). Writes go through WP01's shopify_save_*() functions only. Runs on the Shopify SQL
// adapter, except shopify_admin_menu_visible(), which the Systemdata menus call on any tenant.
//
// 20261102 CL/MJ SD-761: created.

/**
 * Whether the Systemdata menus show the Shopify entry: only on a PostgreSQL tenant that has a
 * shop, so the module stays dark on every other install. Danosoft creates the first shop through
 * systemdata/shopify/shops.php directly. Uses the legacy db_select(), as the menus do.
 */
function shopify_admin_menu_visible(): bool {
	global $db_type;
	static $visible = null;
	if ($visible === null) {
		$visible = false;
		if (!in_array(strtolower((string)$db_type), array('mysql', 'mysqli'), true)) {
			$r = db_fetch_array(db_select("SELECT to_regclass('shopify_shops') IS NOT NULL AS installed", __FILE__ . " linje " . __LINE__));
			if ($r && ($r['installed'] === 't' || $r['installed'] === true)) {
				$visible = (bool)db_fetch_array(db_select("SELECT 1 FROM shopify_shops LIMIT 1", __FILE__ . " linje " . __LINE__));
			}
		}
	}
	return $visible;
}

/**
 * Every lager of the tenant, from the Lagre setup (grupper LG) and from shopify_lager, with its
 * role; a lager without a shopify_lager row is 'ingen'. The LG rows of older accounting years
 * may still be present (SST-829), so the newest description per kodenr is used.
 *
 * @return list<array{lager: int, beskrivelse: string, rolle: string, prioritet: string, udstilling_af: string}>
 */
function shopify_admin_lagre(): array {
	$rows = shopify_db_rows(
		"SELECT k.lager, coalesce(g.beskrivelse, '') AS beskrivelse, coalesce(l.rolle, 'ingen') AS rolle,
		        coalesce(l.prioritet::text, '') AS prioritet, coalesce(l.udstilling_af::text, '') AS udstilling_af
		   FROM (SELECT kodenr AS lager FROM grupper WHERE art = 'LG' AND kodenr IS NOT NULL
		         UNION SELECT lager FROM shopify_lager) k
		   LEFT JOIN (SELECT DISTINCT ON (kodenr) kodenr, beskrivelse FROM grupper
		               WHERE art = 'LG' ORDER BY kodenr, id DESC) g ON g.kodenr = k.lager
		   LEFT JOIN shopify_lager l ON l.lager = k.lager
		  ORDER BY k.lager"
	);
	foreach ($rows as $i => $row) {
		$rows[$i]['lager'] = (int)$row['lager'];
	}
	return $rows;
}

/**
 * The departments (grupper AFD) a shop can belong to.
 *
 * @return list<array{kodenr: string, beskrivelse: string}>
 */
function shopify_admin_departments(): array {
	return shopify_db_rows("SELECT kodenr, coalesce(beskrivelse, '') AS beskrivelse FROM grupper WHERE art = 'AFD' AND kodenr > 0 ORDER BY kodenr");
}

/** The id of the item with this item number, null when there is none. */
function shopify_admin_vare_id(string $varenr): ?int {
	$rows = shopify_db_rows('SELECT id FROM varer WHERE varenr = $1 ORDER BY id LIMIT 1', array($varenr));
	return isset($rows[0]) ? (int)$rows[0]['id'] : null;
}

/** The item number of an item id, '' when the item is gone. */
function shopify_admin_varenr(?string $vareId): string {
	if ($vareId === null || $vareId === '') {
		return '';
	}
	$rows = shopify_db_rows('SELECT varenr FROM varer WHERE id = $1', array((int)$vareId));
	return isset($rows[0]) ? (string)$rows[0]['varenr'] : '';
}

/** Whether a secret of a shop is stored. The value itself is never read here. */
function shopify_admin_secret_set(string $shopKey, string $name): bool {
	return (bool)shopify_db_rows("SELECT 1 FROM settings WHERE var_grp = $1 AND var_name = $2 AND coalesce(var_value, '') <> '' LIMIT 1", array("shopify:$shopKey", $name));
}
