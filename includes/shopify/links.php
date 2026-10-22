<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/links.php --- ver 5.0.0 --- 2026.10.22
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
// Links between Saldi records and Shopify GIDs (WP01, interface sheet 2.2): order links with
// their reservation (the anchor that creates a web order once and exports a store order once),
// product links and customer links. Ids are always full GID text.
//
// 20261022 Sawaneh SD-735: created (WP01-2).
// 20261022 Sawaneh SD-735 review: empty flags encode as an object, flag keys must be namespaces.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema.php';

final class ShopifyLinkError extends RuntimeException
{
}

const SHOPIFY_LINK_DIRECTIONS = array('web', 'store');
const SHOPIFY_ORDER_LINK_COLUMNS = array('order_gid', 'draft_gid', 'order_name', 'ordre_id', 'state', 'ship_lager', 'tags_b64', 'easyroutes_levdato', 'cancelled_at');

/**
 * Reserves the link for one order so the work behind it runs once. A web order is keyed by its
 * Shopify order GID, a store order by the Saldi ordre_id; the unique keys of shopify_order_links
 * and an advisory lock on the key make two concurrent reservations end with one row, the second
 * caller getting created=false and the existing row. Runs in its own transaction or the caller's.
 *
 * @param string|null $orderGid Shopify Order GID (required for web)
 * @param string $direction web or store
 * @param array<string, mixed> $fields ordre_id (required for store), order_name, ship_lager, draft_gid, state (default reserved), flags
 * @return array{id: int, created: bool, state: string, link: array<string, string|null>}
 */
function shopify_reserve_order_link(int $shopId, ?string $orderGid, string $direction, array $fields = array()): array {
	if (!in_array($direction, SHOPIFY_LINK_DIRECTIONS, true)) {
		throw new ShopifyLinkError("direction must be web or store, '$direction' given");
	}
	$gid = $orderGid === null || $orderGid === '' ? null : shopify_gid($orderGid, 'Order');
	$ordreId = isset($fields['ordre_id']) ? (int)$fields['ordre_id'] : null;
	if ($direction === 'web' && $gid === null) {
		throw new ShopifyLinkError('a web order link needs the Shopify order GID');
	}
	if ($direction === 'store' && !$ordreId) {
		throw new ShopifyLinkError('a store order link needs the Saldi ordre_id');
	}
	$values = shopify_link_values($fields, array('order_name', 'ship_lager', 'draft_gid', 'state'), array('flags', 'ordre_id'));
	$values['shop_id'] = $shopId;
	$values['order_gid'] = $gid;
	$values['direction'] = $direction;
	$values['ordre_id'] = $ordreId;
	if (isset($fields['draft_gid'])) {
		$values['draft_gid'] = shopify_gid((string)$fields['draft_gid'], 'DraftOrder');
	}
	$flags = isset($fields['flags']) ? shopify_link_json($fields['flags']) : '{}';
	$lockKey = $direction === 'web' ? "order:$shopId:$gid" : "store:$ordreId";
	$conflict = $direction === 'web' ? '(shop_id, order_gid)' : '(ordre_id, direction)';

	return shopify_db_transaction(function () use ($values, $flags, $lockKey, $conflict, $shopId, $gid, $direction, $ordreId) {
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("shopify_order_link:$lockKey"));
		$columns = array_keys($values);
		$placeholders = array();
		foreach ($columns as $i => $column) {
			$placeholders[] = '$' . ($i + 1);
		}
		$columns[] = 'flags';
		$placeholders[] = '$' . (count($placeholders) + 1) . '::jsonb';
		$params = array_values($values);
		$params[] = $flags;
		$rows = shopify_db_rows(
			'INSERT INTO shopify_order_links (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ")
			 ON CONFLICT $conflict DO NOTHING RETURNING *",
			$params
		);
		if (isset($rows[0])) {
			return array('id' => (int)$rows[0]['id'], 'created' => true, 'state' => (string)$rows[0]['state'], 'link' => $rows[0]);
		}
		$existing = $direction === 'web' ? shopify_order_link($shopId, $gid) : shopify_order_link_for_ordre($ordreId, 'store');
		if ($existing === null) {
			throw new ShopifyLinkError('order link neither inserted nor found');
		}
		return array('id' => (int)$existing['id'], 'created' => false, 'state' => (string)$existing['state'], 'link' => $existing);
	});
}

/**
 * Moves an order link to a new state and sets the given columns; flags are merged into the
 * jsonb by top-level namespace (intake, allocation_v1, writeback_v1, note_ops, credits, draft),
 * so one package never wipes another's entries. Returns the updated row.
 *
 * @param array<string, mixed> $set columns of SHOPIFY_ORDER_LINK_COLUMNS except state, plus flags
 * @return array<string, string|null>
 */
function shopify_advance_order_link(int $linkId, string $state, array $set = array()): array {
	$values = shopify_link_values($set, array_diff(SHOPIFY_ORDER_LINK_COLUMNS, array('state')));
	if (isset($values['order_gid'])) {
		$values['order_gid'] = shopify_gid((string)$values['order_gid'], 'Order');
	}
	if (isset($values['draft_gid'])) {
		$values['draft_gid'] = shopify_gid((string)$values['draft_gid'], 'DraftOrder');
	}
	$params = array($state);
	$assignments = array('state = $1');
	foreach ($values as $column => $value) {
		$params[] = $value;
		$assignments[] = "$column = \$" . count($params);
	}
	if (isset($set['flags'])) {
		$params[] = shopify_link_json($set['flags']);
		$assignments[] = 'flags = flags || $' . count($params) . '::jsonb';
	}
	$params[] = $linkId;
	$rows = shopify_db_rows(
		'UPDATE shopify_order_links SET ' . implode(', ', $assignments) . ', updated_at = now() WHERE id = $' . count($params) . ' RETURNING *',
		$params
	);
	if (!isset($rows[0])) {
		throw new ShopifyLinkError("order link $linkId does not exist");
	}
	return $rows[0];
}

/** @return array<string, string|null>|null */
function shopify_order_link(int $shopId, string $orderGid): ?array {
	$rows = shopify_db_rows('SELECT * FROM shopify_order_links WHERE shop_id = $1 AND order_gid = $2', array($shopId, shopify_gid($orderGid, 'Order')));
	return $rows[0] ?? null;
}

/** @return array<string, string|null>|null */
function shopify_order_link_for_ordre(int $ordreId, string $direction): ?array {
	$rows = shopify_db_rows('SELECT * FROM shopify_order_links WHERE ordre_id = $1 AND direction = $2', array($ordreId, $direction));
	return $rows[0] ?? null;
}

/**
 * Creates or updates the link between a Saldi item (vare_id, variant_id 0 for the item itself)
 * and the product, variant and inventory item it is in one shop. Only the given fields change.
 *
 * @param array<string, mixed> $fields product_gid, variant_gid, inventory_item_gid, last_hash, listed
 * @return array<string, string|null> the link row
 */
function shopify_upsert_product_link(int $shopId, int $vareId, int $variantId, array $fields): array {
	$values = shopify_link_values($fields, array('product_gid', 'variant_gid', 'inventory_item_gid', 'last_hash', 'listed'));
	foreach (array('product_gid' => 'Product', 'variant_gid' => 'ProductVariant', 'inventory_item_gid' => 'InventoryItem') as $column => $resource) {
		if (isset($values[$column])) {
			$values[$column] = shopify_gid((string)$values[$column], $resource);
		}
	}
	return shopify_link_upsert('shopify_product_links', array('shop_id' => $shopId, 'vare_id' => $vareId, 'variant_id' => $variantId), $values, '(shop_id, vare_id, variant_id)');
}

/** @return array<string, string|null>|null */
function shopify_product_link(int $shopId, int $vareId, int $variantId = 0): ?array {
	$rows = shopify_db_rows('SELECT * FROM shopify_product_links WHERE shop_id = $1 AND vare_id = $2 AND variant_id = $3', array($shopId, $vareId, $variantId));
	return $rows[0] ?? null;
}

/** @return array<string, string|null>|null */
function shopify_product_link_for_variant(int $shopId, string $variantGid): ?array {
	$rows = shopify_db_rows('SELECT * FROM shopify_product_links WHERE shop_id = $1 AND variant_gid = $2', array($shopId, shopify_gid($variantGid, 'ProductVariant')));
	return $rows[0] ?? null;
}

/**
 * Creates or updates the link between a Shopify customer and a Saldi debtor (adresser.id) in
 * one shop. A debtor is linked to one customer per shop; linking it to a second customer is
 * refused by the link table's unique key.
 *
 * @param array<string, mixed> $fields source_updated_at, source_hash
 * @return array<string, string|null> the link row
 */
function shopify_upsert_customer_link(int $shopId, string $customerGid, int $adresserId, array $fields = array()): array {
	$values = shopify_link_values($fields, array('source_updated_at', 'source_hash'));
	$values['adresser_id'] = $adresserId;
	return shopify_link_upsert('shopify_customer_links', array('shop_id' => $shopId, 'customer_gid' => shopify_gid($customerGid, 'Customer')), $values, '(shop_id, customer_gid)');
}

/** @return array<string, string|null>|null */
function shopify_customer_link(int $shopId, string $customerGid): ?array {
	$rows = shopify_db_rows('SELECT * FROM shopify_customer_links WHERE shop_id = $1 AND customer_gid = $2', array($shopId, shopify_gid($customerGid, 'Customer')));
	return $rows[0] ?? null;
}

/** @return array<string, string|null>|null */
function shopify_customer_link_for_adresser(int $shopId, int $adresserId): ?array {
	$rows = shopify_db_rows('SELECT * FROM shopify_customer_links WHERE shop_id = $1 AND adresser_id = $2', array($shopId, $adresserId));
	return $rows[0] ?? null;
}

/**
 * INSERT ... ON CONFLICT DO UPDATE of the given values on top of the key columns; the update
 * touches only the given columns and updated_at.
 *
 * @param array<string, mixed> $key
 * @param array<string, mixed> $values
 * @return array<string, string|null>
 */
function shopify_link_upsert(string $table, array $key, array $values, string $conflict): array {
	$all = $key + $values;
	$columns = array_keys($all);
	$placeholders = array();
	foreach ($columns as $i => $column) {
		$placeholders[] = '$' . ($i + 1);
	}
	$updates = array('updated_at = now()');
	foreach (array_keys($values) as $column) {
		$updates[] = "$column = EXCLUDED.$column";
	}
	$rows = shopify_db_rows(
		"INSERT INTO $table (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ")
		 ON CONFLICT $conflict DO UPDATE SET " . implode(', ', $updates) . ' RETURNING *',
		array_values($all)
	);
	return $rows[0];
}

/**
 * Keeps the allowed columns of $input and refuses the rest; $handledElsewhere names the keys
 * the caller consumes itself (flags, and ordre_id in a reservation).
 *
 * @param array<string, mixed> $input
 * @param list<string> $allowed
 * @param list<string> $handledElsewhere
 * @return array<string, mixed>
 */
function shopify_link_values(array $input, array $allowed, array $handledElsewhere = array('flags')): array {
	$values = array();
	foreach ($input as $column => $value) {
		if (in_array($column, $handledElsewhere, true)) {
			continue;
		}
		if (!in_array($column, $allowed, true)) {
			throw new ShopifyLinkError("unknown link column '$column'");
		}
		$values[$column] = $value;
	}
	return $values;
}

/**
 * Flags as a JSON object. An empty map is '{}' and never '[]': jsonb's || turns an object
 * concatenated with an array into an array, which would break every namespace lookup.
 *
 * @param array<string, mixed> $flags namespace => values
 */
function shopify_link_json(array $flags): string {
	foreach (array_keys($flags) as $namespace) {
		if (!is_string($namespace) || $namespace === '') {
			throw new ShopifyLinkError('flags must be a map of namespace => values');
		}
	}
	if ($flags === array()) {
		return '{}';
	}
	return (string)json_encode($flags, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
