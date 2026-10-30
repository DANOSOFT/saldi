<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/catalogue.php --- ver 5.1.0 --- 2026.10.30
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
// Catalogue (WP05-1, K07, K11): Saldi items go to the primary shop as Shopify drafts (productSet)
// and are linked in the other shops by exact SKU. An update sends only the Saldi-owned fields
// (name, SKU, EAN, cost, weight, tracking, oversell policy, taxable, saldi.vat_rate), never what
// HML owns. Webshop items = open items of a stock group (VG box8), as the legacy sync. Dark until
// products_enabled; pages only queue jobs, after their commit.
//
// 20261029 CL/SS SD-741: created (WP05-1).

require_once __DIR__ . '/../client.php';
require_once __DIR__ . '/../errors.php';
require_once __DIR__ . '/../links.php';
if (!function_exists('vatPercent')) {
	require_once __DIR__ . '/../../../lager/productCardIncludes/itemVat.php';
}

/** Which items are webshop items: open, in a stock item group. Aliases v = varer, g = its VG group. */
const SHOPIFY_CATALOGUE_MANAGED = "coalesce(g.box8, '') NOT IN ('', '0') AND coalesce(v.lukket, '') NOT IN ('1', 'on')";

const SHOPIFY_CATALOGUE_PRODUCT = 'fragment catalogueProduct on Product {
	id handle variantsCount { count }
	options { id name optionValues { id name } }
	variants(first: 2) { nodes { id sku selectedOptions { name value } inventoryItem { id } } }
}';
const SHOPIFY_CATALOGUE_READ = 'query catalogueRead($id: ID!) { product(id: $id) { ...catalogueProduct } } ' . SHOPIFY_CATALOGUE_PRODUCT;
const SHOPIFY_CATALOGUE_BY_HANDLE = 'query catalogueByHandle($identifier: ProductIdentifierInput!) {
	productByIdentifier(identifier: $identifier) { ...catalogueProduct }
} ' . SHOPIFY_CATALOGUE_PRODUCT;
const SHOPIFY_CATALOGUE_BY_SKU = 'query catalogueBySku($query: String!, $after: String) {
	productVariants(first: 100, after: $after, query: $query) { nodes { id sku inventoryItem { id } product { id } } pageInfo { hasNextPage endCursor } }
}';
const SHOPIFY_CATALOGUE_VARIANT = 'query catalogueVariant($id: ID!) { productVariant(id: $id) { id sku } }';
/** Pages of loose SKU search hits read before the lookup gives up instead of guessing. */
const SHOPIFY_CATALOGUE_SKU_PAGES = 5;
const SHOPIFY_CATALOGUE_SET = 'mutation catalogueSet($identifier: ProductSetIdentifiers, $input: ProductSetInput!) {
	productSet(identifier: $identifier, input: $input, synchronous: true) { product { ...catalogueProduct } userErrors { field message code } }
} ' . SHOPIFY_CATALOGUE_PRODUCT;
const SHOPIFY_CATALOGUE_METAFIELDS = 'mutation catalogueMetafields($metafields: [MetafieldsSetInput!]!) {
	metafieldsSet(metafields: $metafields) { metafields { id } userErrors { field message code } }
}';

/** A Shopify call that failed; retryable when a later attempt (a job retry) may succeed; error is shopify_graphql()'s code. */
final class ShopifyCatalogueError extends RuntimeException
{
	public function __construct(string $message, public readonly bool $retryable, public readonly string $error = '')
	{
		parent::__construct($message);
	}
}

/**
 * The productSet arguments for one item (pure). Creating: a DRAFT with one default variant, by
 * the handle saldi-<vare_id>, so a create that reached Shopify before a crash is found again.
 * Updating: identifier id, owned fields only, and only when $remote has exactly the linked
 * variant (productSet deletes variants a list leaves out; a product with more variants is not
 * one Saldi item). Otherwise problem says why and input is empty: nothing may be sent.
 *
 * @param array<string, mixed> $item shopify_catalogue_item() row
 * @param array{rate: float} $vat
 * @param array<string, mixed>|null $link shopify_product_links row (update)
 * @param array<string, mixed>|null $remote the product as SHOPIFY_CATALOGUE_PRODUCT reads it (update)
 * @return array{identifier: array<string, string>, input: array<string, mixed>, problem: string|null}
 */
function shopify_catalogue_payload(array $item, array $vat, ?array $link, ?array $remote, bool $creating): array {
	$owned = shopify_catalogue_owned($item, $vat);
	if ($creating) {
		$handle = shopify_catalogue_handle((int)$item['id']);
		$variant = array('optionValues' => array(array('optionName' => 'Title', 'name' => 'Default Title'))) + $owned['variant'];
		return array('identifier' => array('handle' => $handle), 'problem' => null, 'input' => array(
			'handle' => $handle, 'title' => $owned['title'], 'status' => 'DRAFT',
			'productOptions' => array(array('name' => 'Title', 'values' => array(array('name' => 'Default Title')))),
			'variants' => array($variant),
		));
	}
	if ($link === null || (string)($link['product_gid'] ?? '') === '') {
		throw new InvalidArgumentException('an update needs a linked product');
	}
	$out = array('identifier' => array('id' => (string)$link['product_gid']), 'input' => array(),
		'problem' => shopify_catalogue_variant_problem($link, $remote));
	if ($out['problem'] !== null) {
		return $out;
	}
	$out['input']['title'] = $owned['title'];
	$remoteVariant = $remote['variants']['nodes'][0];
	$out['input']['productOptions'] = array_map(fn(array $o): array => array('id' => $o['id'], 'name' => $o['name'],
		'values' => array_map(fn(array $v): array => array('id' => $v['id'], 'name' => $v['name']), $o['optionValues'])), $remote['options']);
	$out['input']['variants'] = array(array('id' => $remoteVariant['id'],
		'optionValues' => array_map(fn(array $s): array => array('optionName' => $s['name'], 'name' => $s['value']), $remoteVariant['selectedOptions']),
	) + $owned['variant']);
	return $out;
}

/**
 * The Saldi-owned values of an item, as productSet takes them (the ownership allowlist).
 *
 * @param array<string, mixed> $item
 * @param array{rate: float} $vat
 * @return array{title: string, variant: array<string, mixed>}
 */
function shopify_catalogue_owned(array $item, array $vat): array {
	$gross = (float)($item['grossweight'] ?? 0) > 0;
	$weight = (float)($gross ? $item['grossweight'] : ($item['netweight'] ?? 0));
	$unit = (string)($gross ? ($item['grossweightunit'] ?? '') : ($item['netweightunit'] ?? ''));
	$title = trim((string)($item['beskrivelse'] ?? ''));
	return array(
		'title' => $title !== '' ? $title : (string)$item['varenr'],
		'variant' => array(
			'sku' => (string)$item['varenr'],
			'barcode' => trim((string)($item['stregkode'] ?? '')),
			'inventoryPolicy' => ($item['oversalg'] ?? '') === 'on' ? 'CONTINUE' : 'DENY',
			'taxable' => $vat['rate'] > 0,
			'inventoryItem' => array(
				'cost' => sprintf('%.2f', (float)($item['kostpris'] ?? 0)),
				'tracked' => true, // webshop items are stock items (SHOPIFY_CATALOGUE_MANAGED)
				'measurement' => array('weight' => array('value' => round($weight, 3), 'unit' => $unit === 'g' ? 'GRAMS' : 'KILOGRAMS')),
			),
		),
	);
}

/** Why the variant can't be sent, or null when $remote has exactly the linked variant. */
function shopify_catalogue_variant_problem(array $link, ?array $remote): ?string {
	if ($remote === null) {
		return 'the product could not be read';
	}
	$nodes = $remote['variants']['nodes'] ?? array();
	$count = (int)($remote['variantsCount']['count'] ?? count($nodes));
	if ($count !== 1 || count($nodes) !== 1) {
		return "the product has $count variants in Shopify; Saldi sends one";
	}
	if ($nodes[0]['id'] !== ($link['variant_gid'] ?? null)) {
		return "the product's variant is not the linked one";
	}
	return null;
}

/** Changes when an owned field changes: an equal hash on the link means nothing to send. */
function shopify_catalogue_hash(array $item, array $vat): string {
	return hash('sha256', json_encode(array(shopify_catalogue_owned($item, $vat), round($vat['rate'], 2)), JSON_THROW_ON_ERROR));
}

function shopify_catalogue_handle(int $vareId): string {
	return 'saldi-' . $vareId;
}

/**
 * The item, and whether it is a webshop item.
 * Null when it doesn't exist or has no item group in the fiscal year.
 *
 * @return array<string, mixed>|null
 */
function shopify_catalogue_item(int $vareId): ?array {
	$rows = shopify_db_rows(
		'SELECT v.id, v.varenr, v.beskrivelse, v.stregkode, v.kostpris, v.grossweight, v.grossweightunit, v.netweight,
		        v.netweightunit, v.oversalg, v.gruppe, (' . SHOPIFY_CATALOGUE_MANAGED . ') AS managed
		 FROM varer v JOIN grupper g ON g.art = \'VG\' AND g.kodenr = v.gruppe AND g.fiscal_year = $2
		 WHERE v.id = $1',
		array($vareId, shopify_catalogue_fiscal_year())
	);
	if (!isset($rows[0])) {
		return null;
	}
	$item = $rows[0];
	$item['managed'] = $item['managed'] === 't';
	return $item;
}

/**
 * The VAT rate of an item: the item card's own vatPercent(), so Shopify and the card never differ.
 *
 * @param array<string, mixed> $item
 * @return array{rate: float}
 */
function shopify_catalogue_vat(array $item): array {
	return array('rate' => (float)vatPercent((int)$item['gruppe']));
}

function shopify_catalogue_fiscal_year(): int {
	global $regnaar;
	if (!$regnaar) {
		throw new RuntimeException('the catalogue needs the fiscal year: bootstrap with a fiscal year');
	}
	return (int)$regnaar;
}

/**
 * Sends one item to the primary shop: a draft when it has no product there, else the owned fields
 * of the linked one. A product HML already has (handle saldi-<id>, then exact SKU) is linked, not
 * created. Unchanged owned fields (last_hash) send nothing; with $rt['verify'] (an item card save)
 * the product is still read once, to notice a deleted product or a replaced variant. Another shop:
 * shopify_catalogue_discover().
 *
 * @param array{deadline?: float, graphql?: array<string, mixed>, verify?: bool} $rt graphql: extra shopify_graphql() opts; verify defaults to true
 * @return array{result: string, product_gid?: string, reason?: string} result: created, updated,
 *   unchanged, held (variants not this item's: nothing sent), removed (HML deleted the product:
 *   unlisted, not created again unless the link row is deleted), ambiguous, skipped
 * @throws ShopifyCatalogueError when Shopify refused or could not be reached
 */
function shopify_catalogue_sync(array $rt, int $shopId, int $vareId, string $corr): array {
	$shop = shopify_catalogue_shop($shopId);
	if ($shop === null) {
		return array('result' => 'skipped', 'reason' => 'products are off for this shop');
	}
	if ($shop['is_primary'] !== 't') {
		return shopify_catalogue_discover($rt, $shopId, $vareId, $corr);
	}
	$item = shopify_catalogue_item($vareId);
	if ($item === null || !$item['managed']) {
		if ($item !== null) {
			shopify_catalogue_resolve_item($shopId, $vareId); // a group missing this fiscal year proves nothing
		}
		return array('result' => 'skipped', 'reason' => 'not a webshop item');
	}
	$vat = shopify_catalogue_vat($item);
	$hash = shopify_catalogue_hash($item, $vat);
	$link = shopify_product_link($shopId, $vareId);
	if ($link !== null && $link['listed'] === 'f') {
		return array('result' => 'skipped', 'reason' => 'not listed in this shop');
	}
	if ($link !== null && (string)$link['product_gid'] !== '' && $link['last_hash'] === $hash && !($rt['verify'] ?? true)) {
		return array('result' => 'unchanged', 'product_gid' => $link['product_gid']);
	}
	if ($link === null || (string)$link['product_gid'] === '') {
		$found = shopify_catalogue_find_product($rt, $shop, $item, $corr);
		if ($found === 'ambiguous') {
			return array('result' => 'ambiguous');
		}
		if ($found === null) {
			return shopify_catalogue_create($rt, $shop, $item, $vat, $hash, $corr);
		}
		$link = shopify_catalogue_claim($shop, $vareId, $found, $corr);
		if ($link === null) {
			return array('result' => 'ambiguous');
		}
		$remote = $found['product'];
	} else {
		$remote = shopify_catalogue_call($rt, $shop, 'productRead', SHOPIFY_CATALOGUE_READ, array('id' => $link['product_gid']), $corr)['product'] ?? null;
		if ($remote === null) {
			shopify_catalogue_unlist($shopId, $vareId);
			shopify_catalogue_requeue_waiters($shop, (string)$link['variant_gid']);
			return array('result' => 'removed', 'product_gid' => $link['product_gid']);
		}
		// HML replaced the only variant (new id, same SKU): follow it
		$only = shopify_catalogue_first_variant($remote);
		if ((int)($remote['variantsCount']['count'] ?? 0) === 1 && $only !== null && $only['id'] !== $link['variant_gid']
			&& ($only['sku'] ?? null) === (string)$item['varenr']) {
			$old = (string)$link['variant_gid'];
			$link = shopify_catalogue_claim($shop, $vareId, $only, $corr) ?? $link;
			shopify_catalogue_requeue_waiters($shop, $old);
		}
		if ($link['last_hash'] === $hash && shopify_catalogue_variant_problem($link, $remote) === null
			&& ($remote['variants']['nodes'][0]['sku'] ?? null) === (string)$item['varenr']) {
			shopify_error_resolve_key($shopId, 'catalogue.variants', "vare:$vareId");
			return array('result' => 'unchanged', 'product_gid' => $link['product_gid']);
		}
	}
	$payload = shopify_catalogue_payload($item, $vat, $link, $remote, false);
	if ($payload['problem'] !== null) {
		// HML's own product shape; listed on the error list for a decision, no alarm per save
		shopify_error_open($shopId, 'catalogue.variants', "vare:$vareId", "item {$item['varenr']}: {$payload['problem']}; nothing was sent", 'info', array('ref' => $corr));
		return array('result' => 'held', 'product_gid' => $link['product_gid'], 'reason' => $payload['problem']);
	}
	shopify_catalogue_call($rt, $shop, 'productSet', SHOPIFY_CATALOGUE_SET, array('identifier' => $payload['identifier'], 'input' => $payload['input']), $corr, 'idempotent');
	// the SKU may have moved off this product (now, before anything else can fail): items waiting for it look again
	shopify_catalogue_requeue_waiters($shop, (string)$link['variant_gid']);
	shopify_catalogue_set_vat($rt, $shop, (string)$link['variant_gid'], $vat, $corr);
	shopify_upsert_product_link($shopId, $vareId, 0, array('last_hash' => $hash));
	shopify_error_resolve_key($shopId, 'catalogue.variants', "vare:$vareId");
	return array('result' => 'updated', 'product_gid' => $link['product_gid']);
}

/**
 * Creates the draft. Sent in reconcile mode: a create that may have reached Shopify is looked up
 * by its handle before anything is sent again.
 *
 * @return array{result: string, product_gid: string}
 */
function shopify_catalogue_create(array $rt, array $shop, array $item, array $vat, string $hash, string $corr): array {
	$payload = shopify_catalogue_payload($item, $vat, null, null, true);
	$reconcile = function () use ($rt, $shop, $payload, $corr): array {
		$found = shopify_catalogue_call($rt, $shop, 'productByHandle', SHOPIFY_CATALOGUE_BY_HANDLE, array('identifier' => $payload['identifier']), $corr)['productByIdentifier'] ?? null;
		return $found === null ? array('state' => 'absent') : array('state' => 'applied', 'data' => array('productSet' => array('product' => $found, 'userErrors' => array())));
	};
	$data = shopify_catalogue_call($rt, $shop, 'productSet', SHOPIFY_CATALOGUE_SET, array('identifier' => $payload['identifier'], 'input' => $payload['input']), $corr, 'reconcile', $reconcile);
	$product = $data['productSet']['product'] ?? null;
	$variant = shopify_catalogue_first_variant($product);
	if ($variant === null) {
		throw new ShopifyCatalogueError('productSet answered without the product and its variant', true);
	}
	$link = shopify_catalogue_store_link((int)$shop['id'], (int)$item['id'], $variant);
	shopify_catalogue_set_vat($rt, $shop, (string)$link['variant_gid'], $vat, $corr);
	shopify_upsert_product_link((int)$shop['id'], (int)$item['id'], 0, array('last_hash' => $hash));
	return array('result' => 'created', 'product_gid' => $link['product_gid']);
}

/**
 * Links an item in another shop by exact SKU: listed=false when absent (no alarm), an error when
 * several have it or another item owns it. A linked item is re-checked with one read (not from
 * the seed): a gone variant or another SKU means a new lookup. An unlinked item keeps no GIDs.
 *
 * @param array{deadline?: float, graphql?: array<string, mixed>, verify?: bool} $rt
 * @return array{result: string, product_gid?: string, reason?: string} result: linked, unlisted, ambiguous, skipped
 */
function shopify_catalogue_discover(array $rt, int $shopId, int $vareId, string $corr): array {
	$shop = shopify_catalogue_shop($shopId);
	$item = $shop === null ? null : shopify_catalogue_item($vareId);
	if ($item === null || !$item['managed']) {
		if ($item !== null) {
			shopify_catalogue_resolve_item($shopId, $vareId);
		}
		return array('result' => 'skipped', 'reason' => $shop === null ? 'products are off for this shop' : 'not a webshop item');
	}
	$link = shopify_product_link($shopId, $vareId);
	if ($link !== null && $link['listed'] === 't' && (string)$link['variant_gid'] !== '') {
		if (!($rt['verify'] ?? true)) {
			return array('result' => 'linked', 'product_gid' => $link['product_gid']);
		}
		$variant = shopify_catalogue_call($rt, $shop, 'productVariantRead', SHOPIFY_CATALOGUE_VARIANT, array('id' => $link['variant_gid']), $corr)['productVariant'] ?? null;
		if (($variant['sku'] ?? null) === (string)$item['varenr']) {
			return array('result' => 'linked', 'product_gid' => $link['product_gid']);
		}
		// no longer this item's variant: unlinked now, even if the lookup below fails
		shopify_catalogue_unlist($shopId, $vareId);
		shopify_catalogue_requeue_waiters($shop, (string)$link['variant_gid']);
	}
	$found = shopify_catalogue_by_sku($rt, $shop, $vareId, (string)$item['varenr'], $corr);
	$link = is_array($found) ? shopify_catalogue_claim($shop, $vareId, $found, $corr) : null;
	if ($link === null) {
		shopify_catalogue_unlist($shopId, $vareId);
		if ($found === null) {
			shopify_error_resolve_key($shopId, 'catalogue.conflict', "vare:$vareId");
		}
		return array('result' => $found === null ? 'unlisted' : 'ambiguous');
	}
	return array('result' => 'linked', 'product_gid' => $link['product_gid']);
}

/**
 * The product HML may already have for an item: handle saldi-<id> (our create, also one a crash
 * left unlinked) with the item's SKU, else the one variant with exactly that SKU. A handle with
 * another SKU is a conflict; ownership is decided by shopify_catalogue_claim().
 *
 * @return array<string, mixed>|string|null a variant with its product, 'ambiguous', or null
 */
function shopify_catalogue_find_product(array $rt, array $shop, array $item, string $corr): array|string|null {
	$product = shopify_catalogue_call($rt, $shop, 'productByHandle', SHOPIFY_CATALOGUE_BY_HANDLE,
		array('identifier' => array('handle' => shopify_catalogue_handle((int)$item['id']))), $corr)['productByIdentifier'] ?? null;
	if ($product !== null) {
		$variant = shopify_catalogue_first_variant($product);
		if (($variant['sku'] ?? null) !== (string)$item['varenr']) {
			// saldi-<id> made by another tenant or for another item: never taken over
			shopify_error_open((int)$shop['id'], 'catalogue.handle', "vare:{$item['id']}", "the product with handle {$product['handle']} has SKU "
				. ($variant['sku'] ?? '(none)') . ", not {$item['varenr']}; the item is not linked", 'warning', array('ref' => $corr));
			return 'ambiguous';
		}
		return $variant;
	}
	$found = shopify_catalogue_by_sku($rt, $shop, (int)$item['id'], (string)$item['varenr'], $corr);
	if (!is_array($found)) {
		return $found;
	}
	$product = shopify_catalogue_call($rt, $shop, 'productRead', SHOPIFY_CATALOGUE_READ, array('id' => $found['product']['id']), $corr)['product'] ?? null;
	return $product === null ? null : array('product' => $product) + $found;
}

/**
 * False, with an error on the error list, when the variant is already linked to another webshop
 * item: two items on one product would overwrite each other on every save.
 * The link of a deleted or closed item, or one moved out of the stock groups, is released instead.
 */
function shopify_catalogue_free(array $shop, int $vareId, array $variant, string $corr): bool {
	$other = shopify_product_link_for_variant((int)$shop['id'], (string)$variant['id']);
	if ($other === null || (int)$other['vare_id'] === $vareId) {
		return true;
	}
	$owner = shopify_db_rows(
		'SELECT v.lukket, g.kodenr IS NOT NULL AS grouped, (' . SHOPIFY_CATALOGUE_MANAGED . ') AS managed
		 FROM varer v LEFT JOIN grupper g ON g.art = \'VG\' AND g.kodenr = v.gruppe AND g.fiscal_year = $2 WHERE v.id = $1',
		array((int)$other['vare_id'], shopify_catalogue_fiscal_year())
	)[0] ?? null;
	if ($owner === null || in_array((string)$owner['lukket'], array('1', 'on'), true) || ($owner['grouped'] === 't' && $owner['managed'] !== 't')) {
		// deleted, closed (sold items are closed, not deleted) or moved out of the stock groups: its
		// link and its open entries go, so a reopened item is linked or created afresh. A group
		// missing in this fiscal year proves nothing and releases nothing. A renumbered webshop
		// item keeps the product: in the primary shop its next sync moves the SKU there; in another
		// shop its next link check lets go and queues the items waiting for it.
		shopify_db_rows('DELETE FROM shopify_product_links WHERE id = $1', array((int)$other['id']));
		shopify_catalogue_resolve_item((int)$shop['id'], (int)$other['vare_id']);
		return true;
	}
	shopify_error_open((int)$shop['id'], 'catalogue.conflict', "vare:$vareId", "the variant with SKU " . ($variant['sku'] ?? '')
		. " in {$shop['shop_key']} is linked to item {$other['vare_id']}; item $vareId is not linked", 'warning',
		array('ref' => $corr, 'variant_gid' => $other['variant_gid']));
	return false;
}

/**
 * Links the item to the variant unless another webshop item owns it (shopify_catalogue_free()),
 * as one step under a lock on the variant, so two jobs at once never both take it. No Shopify
 * call inside.
 *
 * @param array<string, mixed> $variant a variant with its product
 * @return array<string, mixed>|null the link, or null when refused
 */
function shopify_catalogue_claim(array $shop, int $vareId, array $variant, string $corr): ?array {
	return shopify_db_transaction(function () use ($shop, $vareId, $variant, $corr): ?array {
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("shopify_variant:{$shop['id']}:" . shopify_gid((string)$variant['id'], 'ProductVariant')));
		return shopify_catalogue_free($shop, $vareId, $variant, $corr) ? shopify_catalogue_store_link((int)$shop['id'], $vareId, $variant) : null;
	});
}

/**
 * Runs $op in one transaction with the worker's clock: the business time zone (master setting
 * timezone, as shopify_bootstrap() sets it) for PHP and, SET LOCAL, for the database session.
 * shopify_jobs times have no zone, so a page (cookie zone, server-default session zone) must write
 * them in the worker's zone; the rest of the page keeps its own.
 */
function shopify_catalogue_in_worker_zone(callable $op): void {
	$r = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name = 'timezone'", __FILE__ . ' linje ' . __LINE__, true));
	$zone = $r && $r['var_value'] ? (string)$r['var_value'] : 'Europe/Copenhagen';
	$php = date_default_timezone_get();
	date_default_timezone_set($zone);
	try {
		shopify_db_transaction(function () use ($op, $zone): void {
			shopify_db_rows("SELECT set_config('TimeZone', $1, true)", array($zone));
			$op();
		});
	} finally {
		date_default_timezone_set($php);
	}
}

/** Now + $seconds on the database's wall clock (in the worker's zone, see above), read back in PHP's. */
function shopify_catalogue_db_time(int $seconds): DateTimeImmutable {
	return new DateTimeImmutable(shopify_db_rows('SELECT (now() + make_interval(secs => $1))::timestamp AS t', array(max(0, $seconds)))[0]['t']);
}

/** Resolves every open catalogue entry of an item in the shop: it is linked elsewhere or no longer synced. */
function shopify_catalogue_resolve_item(int $shopId, int $vareId): void {
	foreach (array('catalogue.variants', 'catalogue.conflict', 'catalogue.handle', 'catalogue.ambiguous', 'catalogue.rejected') as $kind) {
		shopify_error_resolve_key($shopId, $kind, "vare:$vareId");
	}
}

/** Queues the items an open catalogue.conflict holds back for $variantGid in the shop: its owner let go of it. */
function shopify_catalogue_requeue_waiters(array $shop, ?string $variantGid): void {
	if ((string)$variantGid === '') {
		return;
	}
	$rows = shopify_db_rows("SELECT ref FROM shopify_errors WHERE shop_id = $1 AND kind = 'catalogue.conflict' AND resolved_at IS NULL AND correlation->>'variant_gid' = $2",
		array((int)$shop['id'], shopify_gid((string)$variantGid, 'ProductVariant')));
	// 30 s, as from the item card: never pulls a waiting item card job before its page has finished
	$runAt = $rows ? shopify_catalogue_db_time(30) : null;
	foreach ($rows as $row) {
		$waiting = (int)substr((string)$row['ref'], strlen('vare:'));
		if ($waiting > 0) {
			shopify_catalogue_enqueue($shop, $waiting, array('source' => 'conflict'), $runAt);
		}
	}
}

/** Marks the item not listed in the shop and drops the GIDs, so no flow maps that variant to it. */
function shopify_catalogue_unlist(int $shopId, int $vareId): void {
	shopify_upsert_product_link($shopId, $vareId, 0, array('listed' => false, 'product_gid' => null, 'variant_gid' => null, 'inventory_item_gid' => null, 'last_hash' => null));
	shopify_error_resolve_key($shopId, 'catalogue.variants', "vare:$vareId");
}

/**
 * The variant whose SKU is exactly $sku. Shopify's search matches loosely, so every page of hits
 * is read and filtered; two exact matches are an error on the error list (the item is not
 * linked), and more hits than SHOPIFY_CATALOGUE_SKU_PAGES pages is a refusal, never a guess.
 *
 * @return array<string, mixed>|string|null the variant with its product id, 'ambiguous', or null
 */
function shopify_catalogue_by_sku(array $rt, array $shop, int $vareId, string $sku, string $corr): array|string|null {
	if (trim($sku) === '') {
		return null;
	}
	$exact = array();
	$after = null;
	for ($page = 0; ; $page++) {
		if ($page === SHOPIFY_CATALOGUE_SKU_PAGES) {
			throw new ShopifyCatalogueError("the search for SKU $sku has more than " . ($page * 100) . ' hits; the item is not linked', false, 'refused');
		}
		$hits = shopify_catalogue_call($rt, $shop, 'productVariantsBySku', SHOPIFY_CATALOGUE_BY_SKU,
			array('query' => 'sku:"' . addcslashes($sku, '"\\') . '"', 'after' => $after), $corr)['productVariants'] ?? array();
		foreach ($hits['nodes'] ?? array() as $n) {
			if (($n['sku'] ?? null) === $sku) {
				$exact[] = $n;
			}
		}
		if (empty($hits['pageInfo']['hasNextPage'])) {
			break;
		}
		$after = $hits['pageInfo']['endCursor'];
	}
	if (count($exact) > 1) {
		shopify_error_open((int)$shop['id'], 'catalogue.ambiguous', "vare:$vareId", count($exact) . " products in {$shop['shop_key']} have SKU $sku; item $vareId is not linked there", 'warning', array('ref' => $corr));
		return 'ambiguous';
	}
	shopify_error_resolve_key((int)$shop['id'], 'catalogue.ambiguous', "vare:$vareId");
	return $exact[0] ?? null;
}

/** The first variant of a product read with SHOPIFY_CATALOGUE_PRODUCT, with the product inside. */
function shopify_catalogue_first_variant(?array $product): ?array {
	$variant = $product['variants']['nodes'][0] ?? null;
	return $variant === null ? null : $variant + array('product' => $product);
}

/** @param array<string, mixed> $variant a variant with its product */
function shopify_catalogue_store_link(int $shopId, int $vareId, array $variant): array {
	$link = shopify_upsert_product_link($shopId, $vareId, 0, array(
		'product_gid' => $variant['product']['id'], 'variant_gid' => $variant['id'],
		'inventory_item_gid' => $variant['inventoryItem']['id'], 'listed' => true, 'last_hash' => null,
	));
	shopify_error_resolve_key($shopId, 'catalogue.conflict', "vare:$vareId");
	shopify_error_resolve_key($shopId, 'catalogue.handle', "vare:$vareId");
	return $link;
}

/** The VAT rate on the variant as the metafield saldi.vat_rate (metafieldsSet adds, it never removes others). */
function shopify_catalogue_set_vat(array $rt, array $shop, string $variantGid, array $vat, string $corr): void {
	shopify_catalogue_call($rt, $shop, 'metafieldsSet', SHOPIFY_CATALOGUE_METAFIELDS, array('metafields' => array(array(
		'ownerId' => $variantGid, 'namespace' => 'saldi', 'key' => 'vat_rate', 'type' => 'number_decimal', 'value' => sprintf('%.2f', $vat['rate']),
	))), $corr, 'idempotent');
}

/**
 * @return array<string, mixed> the response data
 * @throws ShopifyCatalogueError
 */
function shopify_catalogue_call(array $rt, array $shop, string $op, string $query, array $vars, string $corr, string $mode = 'read', ?callable $reconcile = null): array {
	$opts = array('deadline' => (float)($rt['deadline'] ?? microtime(true) + 50), 'retry_mode' => $mode, 'correlation' => array('ref' => $corr));
	if ($reconcile !== null) {
		$opts['reconcile'] = $reconcile;
	}
	$res = shopify_graphql($shop, $op, $query, $vars, ($rt['graphql'] ?? array()) + $opts);
	if (!$res['ok']) {
		$why = array_merge(array_column($res['user_errors'], 'message'), array_column($res['errors'], 'message'));
		throw new ShopifyCatalogueError("$op failed ({$res['error']})" . ($why ? ': ' . implode('; ', $why) : ''), $res['retryable'], (string)$res['error']);
	}
	return $res['data'] ?? array();
}

/** The shop row when its products flow is on, else null. */
function shopify_catalogue_shop(int $shopId): ?array {
	return shopify_db_rows('SELECT * FROM shopify_shops WHERE id = $1 AND products_enabled', array($shopId))[0] ?? null;
}

/**
 * Queues one item for a shop: catalogue.upsert for the primary shop, catalogue.link for the
 * others, through shopify_queue_enqueue_coalesced(): one job per shop and item while it waits,
 * brought forward to $runAt, and a follow-up behind one that is already running.
 *
 * @param bool $verify catalogue.link re-checks an existing link (an item card save); the seed does not
 * @return array{id: int, created: bool, state: string}
 */
function shopify_catalogue_enqueue(array $shop, int $vareId, array $correlation = array(), ?DateTimeImmutable $runAt = null, bool $verify = true): array {
	$type = $shop['is_primary'] === 't' ? 'catalogue.upsert' : 'catalogue.link';
	// a seed job (no re-check) never swallows an item card save's re-check
	$key = "{$shop['shop_key']}:vare:$vareId" . ($verify ? '' : ':seed');
	return shopify_queue_enqueue_coalesced($type, (int)$shop['id'], $key, array('vare_id' => $vareId, 'verify' => $verify), SHOPIFY_QUEUE_PRIORITY[$type], $runAt, $correlation);
}

/**
 * The varekort producer (K07: new and changed items within five minutes). Called after the item
 * card's commit; queues a webshop item for every shop with products_enabled, $delaySeconds from now so
 * what the page still writes after its commit is in. Never calls Shopify and never stops the
 * page: a failure is written to the PHP error log.
 */
function shopify_catalogue_item_saved(int $vareId, int $delaySeconds = 0): void {
	if ($vareId <= 0 || !shopify_schema_is_postgres()) {
		return;
	}
	try {
		$shops = shopify_schema_ready() ? shopify_shops('products') : array();
		$item = $shops ? shopify_catalogue_item($vareId) : null;
		if ($item === null) {
			return;
		}
		shopify_catalogue_in_worker_zone(function () use ($shops, $item, $vareId, $delaySeconds): void {
			foreach ($shops as $shop) {
				if (!$item['managed']) {
					// closed or moved out of the stock groups: items waiting for its products may take their turn
					shopify_catalogue_requeue_waiters($shop, shopify_product_link((int)$shop['id'], $vareId)['variant_gid'] ?? null);
					shopify_catalogue_resolve_item((int)$shop['id'], $vareId);
				} else {
					shopify_catalogue_enqueue($shop, $vareId, array('source' => 'varekort'), shopify_catalogue_db_time($delaySeconds));
				}
			}
		});
	} catch (Throwable $e) {
		error_log("shopify catalogue: item $vareId was saved but not queued: " . get_class($e) . ': ' . $e->getMessage());
	}
}

/**
 * One chunk of the seed: queues the next $batch webshop items after $rt['after'] (vare id).
 *
 * @param array{after?: int} $rt
 * @return array{enqueued: int, after: int, done: bool}
 */
function shopify_catalogue_seed(array $rt, int $shopId, int $batch = 250): array {
	$after = (int)($rt['after'] ?? 0);
	$shop = shopify_catalogue_shop($shopId);
	if ($shop === null) {
		return array('enqueued' => 0, 'after' => $after, 'done' => true);
	}
	$batch = max(1, $batch);
	$rows = shopify_db_rows(
		'SELECT v.id FROM varer v JOIN grupper g ON g.art = \'VG\' AND g.kodenr = v.gruppe AND g.fiscal_year = $1
		 WHERE v.id > $2 AND ' . SHOPIFY_CATALOGUE_MANAGED . ' ORDER BY v.id LIMIT $3',
		array(shopify_catalogue_fiscal_year(), $after, $batch)
	);
	foreach ($rows as $row) {
		shopify_catalogue_enqueue($shop, (int)$row['id'], array('source' => 'seed'), null, false);
		$after = (int)$row['id'];
	}
	return array('enqueued' => count($rows), 'after' => $after, 'done' => count($rows) < $batch);
}

/** Job catalogue.seed: queues every webshop item in chunks, yielding with the cursor when time runs out. */
function shopify_catalogue_seed_job(array $job, float $deadline, callable $heartbeat): array {
	$after = (int)($job['checkpoint']['after'] ?? 0);
	$batch = (int)($job['payload']['batch'] ?? 250);
	while (true) {
		$chunk = shopify_catalogue_seed(array('after' => $after), (int)$job['shop_id'], $batch);
		$after = $chunk['after'];
		if ($chunk['done']) {
			return array('outcome' => 'done');
		}
		if (!$heartbeat() || $deadline - microtime(true) < 5) {
			return array('outcome' => 'yield', 'checkpoint' => array('after' => $after));
		}
	}
}

/** Job catalogue.upsert: shopify_catalogue_sync() for the job's shop and item. */
function shopify_catalogue_upsert_job(array $job, float $deadline, callable $heartbeat): array {
	return shopify_catalogue_job($job, $deadline, 'shopify_catalogue_sync');
}

/** Job catalogue.link: shopify_catalogue_discover() for the job's shop and item. */
function shopify_catalogue_link_job(array $job, float $deadline, callable $heartbeat): array {
	return shopify_catalogue_job($job, $deadline, 'shopify_catalogue_discover');
}

/**
 * Runs one item job. Shopify refusing the item's values (userErrors), or a lookup the catalogue
 * refuses to guess, goes on the error list as catalogue.rejected and ends the job; the next save of the item tries again. Any other failure
 * (network, throttling, a revoked token or missing scope) fails the attempt, so the queue retries
 * it and alarms after the third, and the item syncs once the cause is fixed. Running out of
 * the pass's time is no failure: the job yields to the next pass.
 */
function shopify_catalogue_job(array $job, float $deadline, callable $run): array {
	$shopId = (int)$job['shop_id'];
	$vareId = (int)($job['payload']['vare_id'] ?? 0);
	if ($vareId <= 0) {
		throw new LogicException("job {$job['type']} has no vare_id");
	}
	try {
		$run(array('deadline' => $deadline, 'verify' => (bool)($job['payload']['verify'] ?? true)), $shopId, $vareId, 'job:' . $job['id']);
		shopify_error_resolve_key($shopId, 'catalogue.rejected', "vare:$vareId");
	} catch (ShopifyCatalogueError $e) {
		if ($e->error === 'deadline') {
			return array('outcome' => 'yield', 'checkpoint' => array());
		}
		if ($e->retryable || !in_array($e->error, array('user_errors', 'refused'), true)) {
			// network, access or a broken document: not this item's fault; the queue retries and alarms
			throw $e;
		}
		shopify_error_open($shopId, 'catalogue.rejected', "vare:$vareId", "item $vareId: " . $e->getMessage(), 'warning', array('job_id' => $job['id']));
	}
	return array('outcome' => 'done');
}
