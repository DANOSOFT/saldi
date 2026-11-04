<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/prices.php --- ver 5.1.0 --- 2026.11.04
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
// Prices (WP05-2, K08): every quarter of an hour (job price.window, one per shop with
// prices_enabled) each linked webshop item gets the price POS charges now, in DKK including
// VAT, the normal price as compareAt while an offer makes it lower, and saldi.retail_price.
// Only what differs from what Shopify last confirmed (shopify_price_state) is sent, so an offer
// that starts or ends between two runs is picked up by the next. The catalogue never sends these
// fields: price, compareAtPrice and saldi.retail_price are this file's.
//
// 20261103 CL/SS SD-802: created (WP05-2).
// 20261104 CL/SS SD-802 review: a percent offer rounds as the POS total does; the VAT is find_pris()'s.
//                The state is dated when its rows were read.

require_once __DIR__ . '/catalogue.php';
if (!function_exists('afrund')) {
	require_once __DIR__ . '/../../std_func.php';
}

const SHOPIFY_PRICES_UPDATE = 'mutation pricesUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
	productVariantsBulkUpdate(productId: $productId, variants: $variants) { productVariants { id price compareAtPrice } userErrors { field message code } }
}';
/** Items read per query of a run. */
const SHOPIFY_PRICES_PAGE = 200;

/**
 * The price POS charges for a scanned item at $now, as Shopify gets it (pure). POS prefills the
 * gross price from find_pris() (includes/ordrefunc.php): special_price when specialtype is
 * 'price' inside find_pris()'s window, else salgspris, plus VAT, rounded by dkdecimal(); then
 * opret_ordrelinje() makes a percent special inside its own window the line's discount, and the
 * POS total takes it off as ordrelinjerDataII.php and showPosLinesFunc.php do. Weekday
 * offers (varetilbud) never reach a POS line (find_pris() reads them in a condition that is never
 * true, and the prefilled price is a given price), so they are not read here either.
 * compareAt is the normal price while an offer makes the price lower, else null.
 *
 * @param array<string, mixed> $item varer row as the database returns it: salgspris, retail_price,
 *   specialtype, special_price, special_from_date, special_to_date, special_from_time, special_to_time
 * @param float $vatPct the item's VAT rate as find_pris() reads it (shopify_catalogue_vat_rate())
 * @param DateTimeImmutable $now in the business time zone: POS compares its wall clock
 * @return array{price: string, compare_at: string|null, retail_price: string, offer: string|null} offer: price, percent or null
 */
function shopify_price_resolve(array $item, float $vatPct, DateTimeImmutable $now): array {
	$dd = $now->format('Y-m-d');
	$tt = $now->format('H:i:s');
	$type = $item['specialtype'] ?? null;
	$special = (float)($item['special_price'] ?? 0);
	$from = $item['special_from_date'] ?? null;
	$to = $item['special_to_date'] ?? null;
	$fromTime = $item['special_from_time'] ?? null;
	$toTime = $item['special_to_time'] ?? null;
	// the comparisons are POS's own, on the same strings, so null and edge days behave as there
	$findFrom = ($from < $dd && $fromTime != '00:00:00') ? '00:00:00' : $fromTime;
	$findTo = ($to > $dd && $toTime == '00:00:00') ? '23:59:59' : $toTime;
	$priceOffer = $type == 'price' && $special && $from <= $dd && $to >= $dd && $findFrom <= $tt && $findTo >= $tt;
	$period = ($from < $dd || ($from == $dd && $fromTime <= $tt)) && ($to > $dd || ($to == $dd && $toTime >= $tt));
	$percent = $period && $special && $type == 'percent';

	$normal = shopify_price_gross((float)($item['salgspris'] ?? 0), $vatPct);
	$price = $priceOffer ? shopify_price_gross($special, $vatPct) : $normal;
	if ($percent) {
		// ordrelinjerDataII.php:100 rounds the line to 3 decimals, showPosLinesFunc.php:163 the total to 2
		$price = afrund(afrund($price - ($price * $special / 100), 3), 2);
	}
	$offer = $priceOffer ? 'price' : ($percent ? 'percent' : null);
	return array(
		'price' => sprintf('%.2f', $price),
		'compare_at' => $offer !== null && $price < $normal ? sprintf('%.2f', $normal) : null,
		'retail_price' => sprintf('%.2f', afrund((float)($item['retail_price'] ?? 0), 2)),
		'offer' => $offer,
	);
}

/** A net price plus VAT, as find_pris() adds it, rounded to øre as dkdecimal() rounds (afrund()). */
function shopify_price_gross(float $net, float $vatPct): float {
	return afrund($net + $net / 100 * $vatPct, 2);
}

/** The productVariantsBulkUpdate entry of a variant; a null compareAt clears it in Shopify. */
function shopify_prices_variant_input(string $variantGid, array $want): array {
	return array('id' => $variantGid, 'price' => $want['price'], 'compareAtPrice' => $want['compare_at']);
}

/** saldi.retail_price as metafieldsSet takes it: Vejl.pris as stored on the item card, no VAT added or removed. */
function shopify_prices_retail_metafield(string $variantGid, array $want): array {
	return array('ownerId' => $variantGid, 'namespace' => 'saldi', 'key' => 'retail_price', 'type' => 'number_decimal', 'value' => $want['retail_price']);
}

/**
 * Sends what changed for the linked webshop items of one shop, in vare id order from
 * $rt['after']. State counts only when written after the link's last change (a new product or
 * variant gets everything again). A refusal (userErrors) of one item goes on the error list as
 * prices.rejected and the run goes on; any other failure ends it with the exception.
 *
 * @param array{deadline?: float, graphql?: array<string, mixed>, after?: int, now?: DateTimeImmutable, heartbeat?: callable(): bool} $rt
 * @return array{sent: int, unchanged: int, rejected: int, after: int, done: bool} done false: out of time or lease, go on from after
 */
function shopify_prices_sync(array $rt, int $shopId, string $corr): array {
	$out = array('sent' => 0, 'unchanged' => 0, 'rejected' => 0, 'after' => (int)($rt['after'] ?? 0), 'done' => true);
	$shop = shopify_db_rows('SELECT * FROM shopify_shops WHERE id = $1 AND prices_enabled', array($shopId))[0] ?? null;
	if ($shop === null) {
		return $out;
	}
	$now = $rt['now'] ?? new DateTimeImmutable('now');
	$rt['deadline'] = (float)($rt['deadline'] ?? microtime(true) + 50);
	$vat = array();
	do {
		$rows = shopify_db_rows(
			'SELECT l.vare_id, l.variant_id, l.product_gid, l.variant_gid, v.gruppe, v.salgspris, v.retail_price, v.specialtype,
			        v.special_price, v.special_from_date, v.special_to_date, v.special_from_time, v.special_to_time,
			        s.price AS state_price, s.compare_at AS state_compare_at, s.retail_price AS state_retail,
			        (s.acknowledged_at IS NOT NULL AND s.acknowledged_at >= l.updated_at) AS fresh, now()::timestamp AS read_at
			 FROM shopify_product_links l
			 JOIN varer v ON v.id = l.vare_id
			 JOIN grupper g ON g.art = \'VG\' AND g.kodenr = v.gruppe AND g.fiscal_year = $2
			 LEFT JOIN shopify_price_state s ON s.shop_id = l.shop_id AND s.vare_id = l.vare_id AND s.variant_id = l.variant_id
			 WHERE l.shop_id = $1 AND l.listed AND l.variant_id = 0 AND l.product_gid IS NOT NULL AND l.variant_gid IS NOT NULL
			   AND l.vare_id > $3 AND ' . SHOPIFY_CATALOGUE_MANAGED . '
			 ORDER BY l.vare_id LIMIT $4',
			array($shopId, shopify_catalogue_fiscal_year(), $out['after'], SHOPIFY_PRICES_PAGE)
		);
		foreach ($rows as $row) {
			if ($rt['deadline'] - microtime(true) < 5) {
				$out['done'] = false;
				return $out;
			}
			$vat[$row['gruppe']] ??= shopify_catalogue_vat_rate((int)$row['gruppe']);
			try {
				$out[shopify_prices_send_item($rt, $shop, $row, shopify_price_resolve($row, $vat[$row['gruppe']], $now), $corr)]++;
			} catch (ShopifyCatalogueError $e) {
				if ($e->error !== 'deadline') {
					throw $e;
				}
				$out['done'] = false;
				return $out;
			}
			$out['after'] = (int)$row['vare_id'];
		}
		if (count($rows) === SHOPIFY_PRICES_PAGE && isset($rt['heartbeat']) && !$rt['heartbeat']()) {
			$out['done'] = false;
			return $out;
		}
	} while (count($rows) === SHOPIFY_PRICES_PAGE);
	return $out;
}

/**
 * One item: price and compareAt, then the retail metafield, each only when it differs from the
 * state. What Shopify confirmed is written to the state afterwards, also when the other call failed,
 * dated when the row was read: a link changed meanwhile is newer, so it is sent again.
 *
 * @return string sent, unchanged or rejected
 * @throws ShopifyCatalogueError when Shopify could not be reached, or failed for another reason than refusing the item's values
 */
function shopify_prices_send_item(array $rt, array $shop, array $row, array $want, string $corr): string {
	$fresh = $row['fresh'] === 't';
	$money = fn(?string $v): ?string => $v === null ? null : sprintf('%.2f', (float)$v);
	$priceDue = !$fresh || $row['state_price'] === null || $money($row['state_price']) !== $want['price'] || $money($row['state_compare_at']) !== $want['compare_at'];
	$retailDue = !$fresh || $row['state_retail'] === null || $money($row['state_retail']) !== $want['retail_price'];
	if (!$priceDue && !$retailDue) {
		return 'unchanged';
	}
	$ack = array();
	$failure = null;
	try {
		if ($priceDue) {
			shopify_catalogue_call($rt, $shop, 'productVariantsBulkUpdate', SHOPIFY_PRICES_UPDATE, array('productId' => $row['product_gid'],
				'variants' => array(shopify_prices_variant_input($row['variant_gid'], $want))), $corr, 'idempotent');
			$ack += array('price' => $want['price'], 'compare_at' => $want['compare_at']);
		}
		if ($retailDue) {
			shopify_catalogue_call($rt, $shop, 'metafieldsSet', SHOPIFY_CATALOGUE_METAFIELDS,
				array('metafields' => array(shopify_prices_retail_metafield($row['variant_gid'], $want))), $corr, 'idempotent');
			$ack['retail_price'] = $want['retail_price'];
		}
	} catch (ShopifyCatalogueError $e) {
		$failure = $e;
	}
	if ($ack) {
		// a value not confirmed in this call keeps its state only while the state is fresh
		$keep = fn(string $field, string $column) => array_key_exists($field, $ack) ? $ack[$field] : ($fresh ? $row[$column] : null);
		shopify_db_rows(
			'INSERT INTO shopify_price_state (shop_id, vare_id, variant_id, price, compare_at, retail_price, acknowledged_at) VALUES ($1, $2, $3, $4, $5, $6, $7)
			 ON CONFLICT (shop_id, vare_id, variant_id) DO UPDATE SET price = EXCLUDED.price, compare_at = EXCLUDED.compare_at,
			   retail_price = EXCLUDED.retail_price, acknowledged_at = EXCLUDED.acknowledged_at',
			array((int)$shop['id'], (int)$row['vare_id'], (int)$row['variant_id'], $keep('price', 'state_price'),
				$keep('compare_at', 'state_compare_at'), $keep('retail_price', 'state_retail'), $row['read_at'])
		);
	}
	if ($failure === null) {
		shopify_error_resolve_key((int)$shop['id'], 'prices.rejected', "vare:{$row['vare_id']}");
		return 'sent';
	}
	if ($failure->error !== 'user_errors') {
		throw $failure;
	}
	shopify_error_open((int)$shop['id'], 'prices.rejected', "vare:{$row['vare_id']}", "item {$row['vare_id']}: " . $failure->getMessage(), 'warning', array('ref' => $corr));
	return 'rejected';
}

/**
 * Job price.window: shopify_prices_sync() for the job's shop, yielding with the cursor when time or the lease runs out.
 *
 * @param array{graphql?: array<string, mixed>, now?: DateTimeImmutable} $rt test seam; the worker passes none
 */
function shopify_prices_window_job(array $job, float $deadline, callable $heartbeat, array $rt = array()): array {
	$run = shopify_prices_sync(array('deadline' => $deadline, 'after' => (int)($job['checkpoint']['after'] ?? 0), 'heartbeat' => $heartbeat) + $rt,
		(int)$job['shop_id'], 'job:' . $job['id']);
	return $run['done'] ? array('outcome' => 'done') : array('outcome' => 'yield', 'checkpoint' => array('after' => $run['after']));
}
