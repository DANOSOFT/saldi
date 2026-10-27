<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/config.php --- ver 5.0.0 --- 2026.10.27
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
// Shop registry and configuration of the Shopify module (WP01, interface sheet 2.2): shops and
// their flow flags, secrets, lager roles, location, shipping, payment and brand maps. Readers
// return the rows as the adapter gives them (text values, 't'/'f' for booleans); writers run
// through shopify_db_rows() inside shopify_db_transaction(), so secrets never meet db_modify()
// or its log.
//
// 20261022 Sawaneh SD-735: created (WP01-2).
// 20261022 Sawaneh SD-735 review: secret saves serialized per secret, brand compared lower-cased on both sides.
// 20261027 Sawaneh SD-736: saving a shop ensures the stock triggers in the same transaction, once the schema is current.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema.php';

final class ShopifyConfigError extends RuntimeException
{
}

const SHOPIFY_FLOWS = array('products', 'prices', 'stock_capture', 'stock_publish', 'orders', 'customers', 'store_orders');
const SHOPIFY_LAGER_ROLES = array('saelgbar', 'udstilling', 'ingen');

/**
 * The shop row for a shop key, null when the key is unknown.
 *
 * @return array<string, string|null>|null
 */
function shopify_shop(string $key): ?array {
	$rows = shopify_db_rows('SELECT * FROM shopify_shops WHERE shop_key = $1', array($key));
	return $rows[0] ?? null;
}

/**
 * Every shop, or only the shops where the given flow is enabled, ordered by id.
 *
 * @param string|null $flow one of SHOPIFY_FLOWS
 * @return list<array<string, string|null>>
 */
function shopify_shops(?string $flow = null): array {
	if ($flow === null) {
		return shopify_db_rows('SELECT * FROM shopify_shops ORDER BY id');
	}
	if (!in_array($flow, SHOPIFY_FLOWS, true)) {
		throw new InvalidArgumentException("unknown flow '$flow'; one of " . implode(', ', SHOPIFY_FLOWS));
	}
	return shopify_db_rows("SELECT * FROM shopify_shops WHERE {$flow}_enabled ORDER BY id");
}

/**
 * Creates or updates a shop by its shop_key and returns its id. Unknown columns are refused,
 * the domain must be a *.myshopify.com host, flags take booleans. The first save installs the stock
 * triggers in the same transaction; later saves only check them. Call inside shopify_db_transaction().
 *
 * @param array<string, mixed> $shop shop_key and domain required on create
 */
function shopify_save_shop(array $shop): int {
	$key = (string)($shop['shop_key'] ?? '');
	if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,39}$/', $key)) {
		throw new ShopifyConfigError('shop_key must be 1-40 characters of a-z, 0-9, _ or -');
	}
	if (isset($shop['domain']) && !preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/', strtolower((string)$shop['domain']))) {
		throw new ShopifyConfigError('domain must be a *.myshopify.com host');
	}
	$allowed = array('domain', 'navn', 'afd', 'api_version', 'is_primary', 'legacy_slot', 'default_freight_vare_id', 'web_kasse', 'go_live_at', 'stock_baseline_at');
	foreach (SHOPIFY_FLOWS as $flow) {
		$allowed[] = $flow . '_enabled';
	}
	$values = shopify_config_values($shop, $allowed, 'shopify_shops');
	if (isset($values['domain'])) {
		$values['domain'] = strtolower((string)$values['domain']);
	}
	$existing = shopify_shop($key);
	if ($existing) {
		if ($values !== array()) {
			shopify_config_update('shopify_shops', $values, 'shop_key = $%d', array($key), true);
		}
		shopify_save_shop_triggers();
		return (int)$existing['id'];
	}
	if (!isset($values['domain'])) {
		throw new ShopifyConfigError('domain is required for a new shop');
	}
	$values = array('shop_key' => $key) + $values;
	$row = shopify_config_insert('shopify_shops', $values, 'id');
	shopify_save_shop_triggers();
	return (int)$row['id'];
}

/** On a tenant still below the current schema the next login pass migrates and installs the triggers. */
function shopify_save_shop_triggers(): void {
	if (shopify_schema_ready()) {
		ensure_shopify_stock_triggers();
	}
}

/**
 * A secret of a shop (access token, API secret, webhook secret), stored in settings under the
 * group shopify:<shop_key>. Null when not set. The value is returned to the caller only; it is
 * never logged by this module.
 */
function shopify_secret(string $key, string $name): ?string {
	$rows = shopify_db_rows('SELECT var_value FROM settings WHERE var_grp = $1 AND var_name = $2 LIMIT 1', array("shopify:$key", $name));
	return isset($rows[0]) && $rows[0]['var_value'] !== null ? (string)$rows[0]['var_value'] : null;
}

/**
 * Stores a secret of a shop; an empty value removes it. The shop must exist. Runs through the
 * adapter only, so the value reaches neither db_modify() nor the modify log. Call inside
 * shopify_db_transaction().
 */
function shopify_save_secret(string $key, string $name, string $value): void {
	if (!preg_match('/^[a-z_]{1,40}$/', $name)) {
		throw new ShopifyConfigError('secret name must be 1-40 characters of a-z or _');
	}
	if (!shopify_shop($key)) {
		throw new ShopifyConfigError("unknown shop '$key'");
	}
	$group = "shopify:$key";
	// settings has no unique key, so the update-or-insert is serialized per secret with an
	// advisory lock; two concurrent saves then end with one row, the later value winning.
	shopify_db_transaction(function () use ($group, $name, $value, $key) {
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("shopify_secret:$group:$name"));
		if ($value === '') {
			shopify_db_rows('DELETE FROM settings WHERE var_grp = $1 AND var_name = $2', array($group, $name));
			return;
		}
		$updated = shopify_db_rows('UPDATE settings SET var_value = $3 WHERE var_grp = $1 AND var_name = $2 RETURNING id', array($group, $name, $value));
		if ($updated === array()) {
			shopify_db_rows('INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ($2, $1, $3, $4)', array($group, $name, $value, "Shopify $name for shop $key"));
		}
	});
}

/**
 * Lager roles of the tenant (shopify_lager rows, sellable ones first by priority) and the
 * tenant-wide lager settings split_set_lager (Mårum) and virtual_lager, null when unset.
 *
 * @return array{lagre: list<array<string, string|null>>, split_set_lager: int|null, virtual_lager: int|null}
 */
function shopify_lager_config(): array {
	$config = array(
		'lagre' => shopify_db_rows("SELECT * FROM shopify_lager ORDER BY (rolle = 'saelgbar') DESC, prioritet NULLS LAST, lager"),
		'split_set_lager' => null,
		'virtual_lager' => null,
	);
	$rows = shopify_db_rows("SELECT var_name, var_value FROM settings WHERE var_grp = 'shopify' AND var_name IN ('split_set_lager', 'virtual_lager')");
	foreach ($rows as $row) {
		$config[$row['var_name']] = ($row['var_value'] === null || $row['var_value'] === '') ? null : (int)$row['var_value'];
	}
	return $config;
}

/**
 * Sets the role of a lager (grupper LG kodenr): saelgbar with a priority, udstilling for the
 * lager it displays, or ingen. Call inside shopify_db_transaction().
 */
function shopify_save_lager(int $lager, string $rolle, ?int $prioritet = null, ?int $udstillingAf = null): void {
	if (!in_array($rolle, SHOPIFY_LAGER_ROLES, true)) {
		throw new ShopifyConfigError("unknown lager role '$rolle'; one of " . implode(', ', SHOPIFY_LAGER_ROLES));
	}
	if ($rolle === 'saelgbar' && $prioritet === null) {
		throw new ShopifyConfigError('a sellable lager needs a priority');
	}
	shopify_db_rows(
		'INSERT INTO shopify_lager (lager, rolle, prioritet, udstilling_af) VALUES ($1, $2, $3, $4)
		 ON CONFLICT (lager) DO UPDATE SET rolle = EXCLUDED.rolle, prioritet = EXCLUDED.prioritet, udstilling_af = EXCLUDED.udstilling_af, updated_at = now()',
		array($lager, $rolle, $rolle === 'saelgbar' ? $prioritet : null, $rolle === 'udstilling' ? $udstillingAf : null)
	);
}

/**
 * Maps a lager to the shop's Shopify location. One location per lager and shop, and one lager
 * per location; a second lager on the same location is refused by the map's unique key. Call
 * inside shopify_db_transaction().
 */
function shopify_save_map(int $shopId, int $lager, string $locationGid): void {
	$gid = shopify_gid($locationGid, 'Location');
	shopify_db_rows(
		'INSERT INTO shopify_location_map (shop_id, lager, location_gid) VALUES ($1, $2, $3)
		 ON CONFLICT (shop_id, lager) DO UPDATE SET location_gid = EXCLUDED.location_gid, updated_at = now()',
		array($shopId, $lager, $gid)
	);
}

/** The Shopify location GID a lager maps to in a shop, null when unmapped. */
function shopify_location(int $shopId, int $lager): ?string {
	$rows = shopify_db_rows('SELECT location_gid FROM shopify_location_map WHERE shop_id = $1 AND lager = $2', array($shopId, $lager));
	return $rows[0]['location_gid'] ?? null;
}

/**
 * The freight item and pickup lager a Shopify shipping rate maps to, null when the rate is
 * unmapped (the intake then uses the shop's default freight item and raises an alarm).
 *
 * @return array{vare_id: int|null, pickup_lager: int|null}|null
 */
function shopify_shipping(int $shopId, string $rateName): ?array {
	$rows = shopify_db_rows('SELECT vare_id, pickup_lager FROM shopify_shipping_map WHERE shop_id = $1 AND rate_name = $2', array($shopId, $rateName));
	if (!isset($rows[0])) {
		return null;
	}
	return array(
		'vare_id' => $rows[0]['vare_id'] === null ? null : (int)$rows[0]['vare_id'],
		'pickup_lager' => $rows[0]['pickup_lager'] === null ? null : (int)$rows[0]['pickup_lager'],
	);
}

/**
 * The Saldi payment type for a gateway and card brand: the exact pair first, then the
 * gateway's entry without a card brand. Null when unmapped ("Ukendt kort" plus alarm, by the intake).
 */
function shopify_payment(int $shopId, string $gateway, string $cardBrand = ''): ?string {
	$rows = shopify_db_rows(
		'SELECT betalingstype FROM shopify_payment_map WHERE shop_id = $1 AND gateway = $2 AND card_brand IN ($3, \'\') ORDER BY (card_brand = $3) DESC LIMIT 1',
		array($shopId, $gateway, $cardBrand)
	);
	return $rows[0]['betalingstype'] ?? null;
}

/**
 * The shop that sells a brand, by the order's language (ordrer.sprog): the comparison is on
 * the lower-cased language on both sides, and an empty language is dansk, as the form printer
 * treats it. Null when no shop carries that brand.
 *
 * @return array<string, string|null>|null
 */
function shopify_brand_shop(string $sprog): ?array {
	$brand = strtolower(trim($sprog));
	if ($brand === '') {
		$brand = 'dansk';
	}
	$rows = shopify_db_rows('SELECT s.* FROM shopify_brands b JOIN shopify_shops s ON s.id = b.shop_id WHERE lower(b.sprog) = $1 ORDER BY b.sprog LIMIT 1', array($brand));
	return $rows[0] ?? null;
}

/**
 * The one locked debtor-number allocator shared by the web intake, the POS and the store-order
 * export. Hosted here per the interface sheet; WP08 (SD-740) implements it.
 */
function shopify_customer_number(): string {
	throw new ShopifyConfigError('shopify_customer_number() is implemented by WP08 (SD-740)');
}

/**
 * Keeps the allowed columns of $input and refuses the rest, so a typo cannot silently write
 * nothing. Booleans are passed as such; the adapter binds them as t/f.
 *
 * @param array<string, mixed> $input
 * @param list<string> $allowed
 * @return array<string, mixed>
 */
function shopify_config_values(array $input, array $allowed, string $table): array {
	$values = array();
	foreach ($input as $column => $value) {
		if ($column === 'shop_key') {
			continue;
		}
		if (!in_array($column, $allowed, true)) {
			throw new ShopifyConfigError("unknown column '$column' for $table");
		}
		$values[$column] = $value;
	}
	return $values;
}

/**
 * @param array<string, mixed> $values
 * @return array<string, string|null>
 */
function shopify_config_insert(string $table, array $values, string $returning = '*'): array {
	$columns = array_keys($values);
	$placeholders = array();
	foreach ($columns as $i => $column) {
		$placeholders[] = '$' . ($i + 1);
	}
	$rows = shopify_db_rows(
		"INSERT INTO $table (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ") RETURNING $returning",
		array_values($values)
	);
	return $rows[0];
}

/**
 * UPDATE $table SET <values> WHERE <$where with $%d for the parameter number>; $touch adds
 * updated_at = now().
 *
 * @param array<string, mixed> $values
 * @param list<mixed> $whereParams
 * @return list<array<string, string|null>>
 */
function shopify_config_update(string $table, array $values, string $where, array $whereParams, bool $touch = false, string $returning = 'id'): array {
	$set = array();
	$params = array();
	foreach ($values as $column => $value) {
		$params[] = $value;
		$set[] = "$column = \$" . count($params);
	}
	if ($touch) {
		$set[] = 'updated_at = now()';
	}
	$whereNumbers = array();
	foreach ($whereParams as $value) {
		$params[] = $value;
		$whereNumbers[] = count($params);
	}
	return shopify_db_rows("UPDATE $table SET " . implode(', ', $set) . ' WHERE ' . vsprintf($where, $whereNumbers) . " RETURNING $returning", $params);
}
