<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/storeOrders.php --- ver 5.1.0 --- 2026.11.12
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
// Store orders (WP13-2, K18, K36a, K37, K38, K42, K54): a posted POS sale goes to its brand's shop as
// a paid order that draws no Shopify stock, with the debtor's customer when it has one. The sale is
// frozen into the orderCreate input, which is kept on the order link (state create_started) before it
// is sent. orderCreate is an uncertain mutation: a send that may have reached Shopify is looked up (tag,
// then sourceIdentifier) before the same input is sent again, never sooner than
// SHOPIFY_STORE_ORDER_SETTLE after it. The POS only queues; nothing here can fail a checkout.
//
// 20261112 CL/SS SD-804: created (WP13-2).

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../links.php';
require_once __DIR__ . '/../queue.php';

/** Seconds a send that may have reached Shopify is left before it may be sent again: the order search can lag the create. */
const SHOPIFY_STORE_ORDER_SETTLE = 600;
/** Seconds a sale waits for its debtor's customer export before it goes without the customer. */
const SHOPIFY_STORE_ORDER_CUSTOMER_WAIT = 3600;
/** Days back the hourly recovery looks for posted sales that were never queued. */
const SHOPIFY_STORE_ORDER_RECOVER_DAYS = 7;
// The keys DTAILS reads (defaults until DTAILS confirms them, Aa4).
const SHOPIFY_STORE_ORDER_ATTR_METHOD = 'Leveringsmaade';
const SHOPIFY_STORE_ORDER_ATTR_DATE = 'Leveringsdato';
const SHOPIFY_STORE_ORDER_DATE_FORMAT = 'Y-m-d';
const SHOPIFY_STORE_ORDER_UNDATED_NOTE = 'Leveringsdato aftales med kunden';
const SHOPIFY_STORE_ORDER_LINE_LAGER = 'Lager';
const SHOPIFY_STORE_ORDER_METAFIELD_NS = 'saldi';
const SHOPIFY_STORE_ORDER_TAX_TITLE = 'Moms';

const SHOPIFY_STORE_ORDER_CREATE = 'mutation orderCreate($order: OrderCreateOrderInput!, $options: OrderCreateOptionsInput) {
	orderCreate(order: $order, options: $options) { order { id name } userErrors { field message } }
}';
const SHOPIFY_STORE_ORDER_FIND = 'query storeOrderFind($query: String!) {
	orders(first: 5, query: $query) { nodes { id name tags sourceIdentifier } }
}';

/** A failure that is not the sale's: the queue retries the job and alarms after the third attempt. */
final class ShopifyStoreOrderError extends RuntimeException
{
}

/**
 * Queues a posted sale for its brand's shop (job store.export): only when that shop's store_orders
 * flow is on and it has gone live by $at. Called by the POS after bogfor(), inside the sale's open
 * transaction: everything runs in a savepoint, so a failure here rolls back only itself and the
 * caller catches it; the job commits with the sale.
 *
 * @return array{queued: bool, reason?: string, job?: array{id: int, created: bool, state: string}}
 */
function shopify_store_orders_enqueue(int $ordreId, DateTimeImmutable $at): array {
	require_once __DIR__ . '/../schema.php';
	if (!shopify_schema_ready()) {
		return array('queued' => false, 'reason' => 'no Shopify schema');
	}
	return shopify_db_transaction(function () use ($ordreId, $at): array {
		$order = shopify_db_rows("SELECT sprog FROM ordrer WHERE id = $1 AND art = 'PO'", array($ordreId))[0] ?? null;
		$shop = $order === null ? null : shopify_brand_shop((string)$order['sprog']);
		if ($shop === null || $shop['store_orders_enabled'] !== 't' || $shop['go_live_at'] === null || new DateTimeImmutable($shop['go_live_at']) > $at) {
			return array('queued' => false, 'reason' => 'the brand has no live shop with store orders');
		}
		$job = shopify_queue_enqueue('store.export', (int)$shop['id'], "{$shop['shop_key']}:store:$ordreId", array('ordre_id' => $ordreId),
			SHOPIFY_QUEUE_PRIORITY['store.export'], null, array('ordre_id' => $ordreId));
		return array('queued' => true, 'job' => $job);
	});
}

/** The heavier services the job needs; kept out of the POS page, which only queues. */
function shopify_store_orders_require(): void {
	require_once __DIR__ . '/../client.php';
	require_once __DIR__ . '/../errors.php';
	require_once __DIR__ . '/customersOut.php';
	require_once __DIR__ . '/orderIntake.php';
	require_once __DIR__ . '/../../documents/dokumentLinks.php';
}

/** The tag a store order carries in Shopify: saldi-po-<db>-<ordre_id>. */
function shopify_store_orders_tag(string $db, int $ordreId): string {
	return "saldi-po-$db-$ordreId";
}

/**
 * The sale as it is sent: the order, its lines, payments, delivery choice and the shop it belongs
 * to. Null when there is no POS order with that id.
 *
 * @return array<string, mixed>|null
 */
function shopify_store_orders_snapshot(int $ordreId): ?array {
	$order = shopify_db_rows("SELECT o.*, current_database() AS db, v.type AS lev_type, v.navn AS lev_valg, v.shopify_vaerdi
		FROM ordrer o LEFT JOIN pos_leveringsvalg v ON v.id = o.leveringsvalg_id WHERE o.id = $1 AND o.art = 'PO'", array($ordreId))[0] ?? null;
	if ($order === null) {
		return null;
	}
	$shop = shopify_brand_shop((string)$order['sprog']);
	$lines = shopify_db_rows('SELECT l.*, g.beskrivelse AS lager_navn FROM ordrelinjer l
		LEFT JOIN grupper g ON g.art = \'LG\' AND g.kodenr::text = l.lager::text WHERE l.ordre_id = $1 AND l.posnr >= 0 ORDER BY l.posnr, l.id', array($ordreId));
	$payments = shopify_db_rows('SELECT betalingstype, amount, valutakurs FROM pos_betalinger WHERE ordre_id = $1 ORDER BY id', array($ordreId));
	$links = array();
	if ($shop !== null) {
		foreach (shopify_db_rows('SELECT vare_id, variant_id, variant_gid FROM shopify_product_links WHERE shop_id = $1 AND variant_gid IS NOT NULL
			AND vare_id IN (SELECT vare_id FROM ordrelinjer WHERE ordre_id = $2)', array((int)$shop['id'], $ordreId)) as $l) {
			$links["{$l['vare_id']}:{$l['variant_id']}"] = $l['variant_gid'];
		}
	}
	return array('order' => $order, 'lines' => $lines, 'payments' => $payments, 'links' => $links, 'shop_id' => $shop === null ? null : (int)$shop['id']);
}

/**
 * Whether the sale goes to the shop (pure): the shop is the brand's and live, the sale was posted at
 * or after go-live (K38) and is a sale with a positive total and lines (an account payment has none),
 * and it has a debtor or is not taken along (an anonymous cash sale stays in Saldi, K37).
 * shopify_store_orders_recover() selects by the same rules.
 *
 * @return array{ok: bool, reason?: string}
 */
function shopify_store_orders_eligibility(array $snap, array $shop, DateTimeImmutable $now): array {
	$o = $snap['order'];
	$no = fn(string $why): array => array('ok' => false, 'reason' => $why);
	if ($snap['shop_id'] !== (int)$shop['id']) {
		return $no('the sale belongs to another brand');
	}
	if ($shop['go_live_at'] === null || new DateTimeImmutable($shop['go_live_at']) > $now) {
		return $no('the shop is not live');
	}
	if ((int)$o['status'] < 3 || trim((string)$o['fakturanr']) === '') {
		return $no('the sale is not posted');
	}
	$time = preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]/', trim((string)$o['tidspkt']), $m) ? $m[0] : '00:00';
	if (new DateTimeImmutable("{$o['fakturadate']} $time") < new DateTimeImmutable($shop['go_live_at'])) {
		return $no('the sale is from before go-live');
	}
	if (shopify_store_orders_cents((float)$o['sum'] + (float)$o['moms']) <= 0) {
		return $no('the sale has no positive total');
	}
	if ($snap['lines'] === array()) {
		return $no('nothing was sold (an account payment)');
	}
	if ((int)$o['konto_id'] <= 0 && shopify_store_orders_mode($o) === 'taget_med') {
		return $no('an anonymous sale taken along');
	}
	return array('ok' => true);
}

/** The delivery type of the sale; a sale without a delivery choice is taken along. */
function shopify_store_orders_mode(array $order): string {
	return in_array($order['lev_type'] ?? null, array('afhentning', 'levering'), true) ? $order['lev_type'] : 'taget_med';
}

function shopify_store_orders_cents(float $amount): int {
	return (int)round($amount * 100);
}

function shopify_store_orders_money(int $cents, string $currency): array {
	return array('shopMoney' => array('amount' => sprintf('%d.%02d', intdiv($cents, 100), $cents % 100), 'currencyCode' => $currency));
}

/**
 * Splits $total cents over $weights in proportion, the remainders to the largest fractions, so the
 * parts add up to $total exactly. Equal weights when they sum to nothing.
 *
 * @param array<int|string, float> $weights
 * @return array<int|string, int>
 */
function shopify_store_orders_spread(int $total, array $weights): array {
	$sum = array_sum($weights);
	if ($weights === array()) {
		return array();
	}
	$exact = array_map(fn(float $w): float => $sum > 0 ? $total * $w / $sum : $total / count($weights), $weights);
	$parts = array_map(fn(float $e): int => (int)floor($e), $exact);
	$order = array_keys($exact);
	usort($order, fn($a, $b): int => ($exact[$b] - $parts[$b]) <=> ($exact[$a] - $parts[$a]));
	for ($i = 0, $left = $total - array_sum($parts); $left > 0; $i++, $left--) {
		$parts[$order[$i % count($order)]]++;
	}
	return $parts;
}

/**
 * The order's lines for orderCreate (pure). Each line's amount is Saldi's (price less its discount,
 * times quantity and procent) with its VAT; a set's correction line is spread over the set's items and
 * a negative line over the lines of its VAT rate as far as they go, then over the others, so every line
 * is paid in full. The amounts are then scaled to the posted total, and the posted VAT is spread over the
 * taxed lines, to the øre. A whole quantity of a linked item is that variant (two lines
 * when the amount does not divide by the quantity); a fractional quantity or an unlinked item is a
 * custom item of quantity 1.
 *
 * @param array<string, string> $links "<vare_id>:<variant_id>" => variant GID
 * @return list<array<string, mixed>>
 * @throws DomainException when a line would go below zero or the posted VAT cannot sit on taxed lines within their price
 */
function shopify_store_orders_project_lines(array $snap, array $links): array {
	$o = $snap['order'];
	$currency = preg_match('/^[A-Z]{3}$/', (string)$o['valuta']) ? (string)$o['valuta'] : 'DKK';
	$rows = array();
	foreach ($snap['lines'] as $l) {
		$qty = (float)$l['antal'];
		$pris = (float)$l['pris'];
		$net = ($l['rabatart'] === 'amount' ? $pris - (float)$l['rabat'] : $pris - $pris / 100 * (float)$l['rabat']) * $qty;
		if ($l['procent'] !== null && $l['procent'] !== '') {
			$net *= (float)$l['procent'] / 100;
		}
		// VAT as momsupdat() reckons it: a set's correction line carries none, its items do
		$correction = (int)$l['saet'] > 0 && $l['samlevare'] === 'on';
		$taxed = !$correction && ((int)$l['vare_id'] > 0 || (int)$l['bogf_konto'] > 0) && $l['momsfri'] !== 'on' && !$l['omvbet'];
		$own = (float)$l['momssats'];
		$rate = !$taxed ? 0.0 : (($own > 0 && $own < (float)$o['momssats']) || (int)$l['vare_id'] <= 0 ? $own : (float)$o['momssats']);
		if ($qty == 0 && $net == 0) {
			continue;
		}
		$rows[] = array('line' => $l, 'qty' => $qty, 'gross' => $net * (1 + $rate / 100), 'rate' => $rate,
			'set' => (int)$l['saet'] > 0 ? (int)$l['saet'] : 0, 'correction' => $correction);
	}
	// a set's correction to its items, then every negative line to the positive ones
	foreach ($rows as $i => $r) {
		if (!$r['correction']) {
			continue;
		}
		$items = array_filter($rows, fn(array $x): bool => $x['set'] === $r['set'] && !$x['correction']);
		$rows = shopify_store_orders_fold($rows, $i, array_keys($items));
	}
	// first onto lines of its own VAT rate, as far as they go, then onto the others
	foreach ($rows as $i => $r) {
		if (isset($rows[$i]) && $r['gross'] < 0) {
			$rows = shopify_store_orders_fold($rows, $i, array_keys(array_filter($rows, fn(array $x): bool => $x['gross'] > 0 && $x['rate'] == $r['rate'])));
		}
		if (isset($rows[$i]) && $rows[$i]['gross'] < 0) {
			$rows = shopify_store_orders_fold($rows, $i, array_keys(array_filter($rows, fn(array $x): bool => $x['gross'] > 0)));
		}
	}
	$rows = array_values($rows);
	// the posted VAT over the taxed lines, in proportion to the VAT each holds at its rate
	$vatTotal = shopify_store_orders_cents((float)$o['moms']);
	$vatWeights = array_map(fn(array $r): float => $r['rate'] > 0 ? $r['gross'] * $r['rate'] / (100 + $r['rate']) : 0.0, $rows);
	if (array_filter($rows, fn(array $r): bool => $r['gross'] < 0) !== array() || $vatTotal < 0 || ($vatTotal > 0 && array_sum($vatWeights) <= 0)) {
		throw new DomainException('its lines cannot be sent as lines of zero or more with the posted VAT');
	}
	$gross = shopify_store_orders_spread(shopify_store_orders_cents((float)$o['sum'] + (float)$o['moms']), array_column($rows, 'gross'));
	$vat = shopify_store_orders_spread($vatTotal, $vatWeights);
	// a VAT-free return against a taxed purchase can leave more VAT than a line costs
	foreach ($vat as $i => $cents) {
		if ($cents > $gross[$i]) {
			throw new DomainException('its VAT is more than the lines it sits on cost');
		}
	}
	$out = array();
	foreach ($rows as $i => $r) {
		$l = $r['line'];
		$variant = $links[(int)$l['vare_id'] . ':' . (int)$l['variant_id']] ?? null;
		$whole = $r['qty'] >= 1 && floor($r['qty']) == $r['qty'];
		$base = array('title' => trim((string)$l['beskrivelse']) !== '' ? trim((string)$l['beskrivelse']) : (string)$l['varenr']);
		if ($variant !== null && $whole) {
			$base = array('variantId' => $variant);
		} elseif (!$whole) {
			$base['title'] .= ' (' . str_replace('.', ',', rtrim(rtrim(number_format($r['qty'], 3, '.', ''), '0'), '.')) . (trim((string)$l['enhed']) !== '' ? ' ' . trim((string)$l['enhed']) : '') . ')';
		}
		if (!isset($base['variantId']) && trim((string)$l['varenr']) !== '') {
			$base['sku'] = trim((string)$l['varenr']);
		}
		if ((int)$l['lager'] > 0) {
			$base['properties'] = array(array('name' => SHOPIFY_STORE_ORDER_LINE_LAGER, 'value' => trim((string)$l['lager_navn']) !== '' ? trim((string)$l['lager_navn']) : (string)(int)$l['lager']));
		}
		$qty = $whole ? (int)$r['qty'] : 1;
		$unit = intdiv($gross[$i], $qty);
		$extra = $gross[$i] - $unit * $qty;
		$parts = array_filter(array(array($qty - $extra, $unit), array($extra, $unit + 1)), fn(array $p): bool => $p[0] > 0);
		$vats = shopify_store_orders_spread($vat[$i], array_map(fn(array $p): float => (float)($p[0] * $p[1]), $parts));
		foreach ($parts as $k => [$n, $price]) {
			$item = $base + array('quantity' => $n, 'priceSet' => shopify_store_orders_money($price, $currency), 'taxable' => $vats[$k] > 0);
			if ($vats[$k] > 0) {
				$item['taxLines'] = array(array('title' => SHOPIFY_STORE_ORDER_TAX_TITLE, 'rate' => (string)round($r['rate'] / 100, 4), 'priceSet' => shopify_store_orders_money($vats[$k], $currency)));
			}
			$out[] = $item;
		}
	}
	return $out;
}

/**
 * Moves row $from's amount onto rows $to in proportion to their amounts; a negative amount only as far as
 * they hold, so none goes below zero. Row $from is dropped when all of it moved, else what is left stays on it.
 */
function shopify_store_orders_fold(array $rows, int $from, array $to): array {
	$to = array_values(array_diff($to, array($from)));
	$weight = array_sum(array_map(fn($k): float => abs($rows[$k]['gross']), $to));
	if ($to === array() || $weight <= 0) {
		return $rows;
	}
	$move = $rows[$from]['gross'] < 0 ? max($rows[$from]['gross'], -$weight) : $rows[$from]['gross'];
	foreach ($to as $k) {
		// a used-up target is 0, not a rounding error either side of it; real fractions of an øre stay for the spread
		$rows[$k]['gross'] = $move <= -$weight ? 0.0 : $rows[$k]['gross'] + $move * abs($rows[$k]['gross']) / $weight;
		if (abs($rows[$k]['gross']) < 1e-9) {
			$rows[$k]['gross'] = 0.0;
		}
	}
	$rows[$from]['gross'] -= $move;
	if (abs($rows[$from]['gross']) < 1e-9) {
		unset($rows[$from]);
	}
	return $rows;
}

/**
 * The sale's payments as SALE/SUCCESS transactions (pure), in DKK by the payment's rate, adding up to
 * the posted total: change and cash rounding come off the last payments, and what no payment covers
 * (an account sale) goes as the sale's own payment type.
 *
 * @return list<array<string, mixed>>
 */
function shopify_store_orders_project_payments(array $snap): array {
	$o = $snap['order'];
	$currency = preg_match('/^[A-Z]{3}$/', (string)$o['valuta']) ? (string)$o['valuta'] : 'DKK';
	$total = shopify_store_orders_cents((float)$o['sum'] + (float)$o['moms']);
	$paid = array();
	foreach ($snap['payments'] as $p) {
		$cents = shopify_store_orders_cents((float)$p['amount'] * ((float)$p['valutakurs'] ?: 100) / 100);
		if ($cents > 0) {
			$paid[] = array(trim((string)$p['betalingstype']) !== '' ? trim((string)$p['betalingstype']) : 'Kontant', $cents);
		}
	}
	for ($i = count($paid) - 1, $over = array_sum(array_column($paid, 1)) - $total; $i >= 0 && $over > 0; $i--) {
		$cut = min($over, $paid[$i][1]);
		$paid[$i][1] -= $cut;
		$over -= $cut;
	}
	$short = $total - array_sum(array_column($paid, 1));
	if ($short > 0) {
		$paid[] = array(trim((string)$o['felt_1']) !== '' ? trim((string)$o['felt_1']) : 'Kontant', $short);
	}
	$out = array();
	foreach ($paid as [$gateway, $cents]) {
		if ($cents > 0) {
			$out[] = array('kind' => 'SALE', 'status' => 'SUCCESS', 'gateway' => $gateway, 'amountSet' => shopify_store_orders_money($cents, $currency));
		}
	}
	return $out;
}

/**
 * The orderCreate variables (pure). Every delivery type is paid; one taken along is fulfilled at once,
 * a pickup has no shipping address (so no route), a delivery carries its address and its date, or the
 * fixed note when it has none (K36a). The invoice number goes in a metafield (K54).
 *
 * @param array{gid: string|null, contact: bool} $customer contact false: no email or phone go, so Shopify associates no one
 * @param list<array<string, mixed>> $lines shopify_store_orders_project_lines()
 * @param list<array<string, mixed>> $payments shopify_store_orders_project_payments()
 */
function shopify_store_orders_build_payload(array $snap, array $customer, array $lines, array $payments, ?string $invoiceUrl): array {
	$o = $snap['order'];
	$id = (int)$o['id'];
	$mode = shopify_store_orders_mode($o);
	$email = $customer['contact'] ? trim(shopify_customer_emails((string)$o['email'])[0] ?? '') : '';
	$phone = $customer['contact'] ? shopify_customer_export_phone((string)$o['phone'], (string)$o['land']) : '';
	$order = array(
		'currency' => preg_match('/^[A-Z]{3}$/', (string)$o['valuta']) ? (string)$o['valuta'] : 'DKK',
		'taxesIncluded' => true,
		'sourceName' => SHOPIFY_ECHO_SOURCE,
		'sourceIdentifier' => "{$o['db']}:$id",
		'tags' => array(SHOPIFY_ECHO_TAG, shopify_store_orders_tag($o['db'], $id)),
		'lineItems' => $lines,
		'transactions' => $payments,
		'metafields' => array(
			array('namespace' => SHOPIFY_STORE_ORDER_METAFIELD_NS, 'key' => 'fakturanr', 'type' => 'single_line_text_field', 'value' => trim((string)$o['fakturanr'])),
			array('namespace' => SHOPIFY_STORE_ORDER_METAFIELD_NS, 'key' => 'kassebon', 'type' => 'single_line_text_field', 'value' => trim((string)$o['fakturanr'])),
		),
	);
	if ($invoiceUrl !== null) {
		$order['metafields'][] = array('namespace' => SHOPIFY_STORE_ORDER_METAFIELD_NS, 'key' => 'faktura_url', 'type' => 'url', 'value' => $invoiceUrl);
	}
	if ($customer['gid'] !== null) {
		$order['customer'] = array('toAssociate' => array('id' => $customer['gid']));
	}
	if ($email !== '') {
		$order['email'] = $email;
	}
	if ($phone !== '') {
		$order['phone'] = $phone;
	}
	if ($mode === 'taget_med') {
		$order['fulfillmentStatus'] = 'FULFILLED';
	} else {
		$method = trim((string)($o['shopify_vaerdi'] ?? '')) ?: (trim((string)($o['lev_valg'] ?? '')) ?: trim((string)$o['leveringsmaade']));
		$order['customAttributes'] = array(array('key' => SHOPIFY_STORE_ORDER_ATTR_METHOD, 'value' => $method !== '' ? $method : $mode));
	}
	if ($mode === 'levering') {
		$order['shippingAddress'] = shopify_store_orders_address($o, $phone);
		if (trim((string)$o['oensket_levdato']) !== '') {
			$order['customAttributes'][] = array('key' => SHOPIFY_STORE_ORDER_ATTR_DATE, 'value' => (new DateTimeImmutable($o['oensket_levdato']))->format(SHOPIFY_STORE_ORDER_DATE_FORMAT));
		} else {
			$order['note'] = SHOPIFY_STORE_ORDER_UNDATED_NOTE;
		}
	}
	return array('order' => $order, 'options' => array('inventoryBehaviour' => 'BYPASS', 'sendReceipt' => $email !== ''));
}

/** The delivery address: the order's delivery fields when it has a delivery address, else its own. */
function shopify_store_orders_address(array $o, string $phone): array {
	$p = trim((string)$o['lev_addr1']) !== '' ? 'lev_' : '';
	$name = trim((string)($p === '' ? ($o['kontakt'] ?: $o['firmanavn']) : ($o['lev_kontakt'] ?: $o['lev_navn'])));
	$cut = strrpos($name, ' ');
	$land = mb_strtolower(trim((string)$o[$p === '' ? 'land' : 'lev_land']));
	$address = array_filter(array(
		'firstName' => $cut === false ? '' : substr($name, 0, $cut), 'lastName' => $cut === false ? $name : substr($name, $cut + 1),
		'company' => trim((string)$o[$p === '' ? 'firmanavn' : 'lev_navn']) !== $name ? trim((string)$o[$p === '' ? 'firmanavn' : 'lev_navn']) : '',
		'address1' => trim((string)$o[$p . 'addr1']), 'address2' => trim((string)$o[$p . 'addr2']), 'zip' => trim((string)$o[$p . 'postnr']),
		'city' => trim((string)$o[$p . 'bynavn']), 'phone' => $phone,
		'countryCode' => in_array($land, array('', 'dk', 'danmark', 'denmark'), true) ? 'DK' : (preg_match('/^[a-z]{2}$/', $land) ? strtoupper($land) : ''),
	), fn(string $v): bool => $v !== '');
	return $address;
}

/**
 * The customer the sale goes with: the debtor's customer in the shop, queued for export when it has
 * none. wait while that export is open (at most SHOPIFY_STORE_ORDER_CUSTOMER_WAIT); a parked export
 * sends no email or phone, so the order is not associated with whoever has them (K31).
 *
 * @return array{gid: string|null, contact: bool}|array{wait: int}
 */
function shopify_store_orders_customer(array $shop, array $snap): array {
	$adresserId = (int)$snap['order']['konto_id'];
	if ($adresserId <= 0 || $shop['customers_enabled'] !== 't') {
		return array('gid' => null, 'contact' => true);
	}
	$link = shopify_customer_link_for_adresser((int)$shop['id'], $adresserId);
	if ($link !== null) {
		return array('gid' => $link['customer_gid'], 'contact' => true);
	}
	$export = shopify_db_rows('SELECT state, extract(epoch FROM now() - created_at) AS age FROM shopify_customer_exports WHERE shop_id = $1 AND adresser_id = $2',
		array((int)$shop['id'], $adresserId))[0] ?? null;
	if ($export !== null && $export['state'] === 'parked') {
		return array('gid' => null, 'contact' => false);
	}
	if ($export !== null && (float)$export['age'] > SHOPIFY_STORE_ORDER_CUSTOMER_WAIT) {
		shopify_error_open((int)$shop['id'], 'store.customer', "ordre:{$snap['order']['id']}", "sale {$snap['order']['fakturanr']} went to Shopify without its customer: debtor $adresserId's export did not finish within an hour", 'info');
		return array('gid' => null, 'contact' => false);
	}
	shopify_customer_export_enqueue((int)$shop['id'], $adresserId, array('ordre_id' => (int)$snap['order']['id']));
	return array('wait' => 60);
}

/**
 * The order an earlier send made for this sale: by its tag, then by its source identifier (an exact
 * filter), where the order found must carry that tag or identifier. More than one is not guessed between.
 *
 * @throws ShopifyStoreOrderError
 */
function shopify_store_orders_find_remote(array $rt, array $shop, string $db, int $ordreId): ?array {
	$tag = shopify_store_orders_tag($db, $ordreId);
	$searches = array(
		array('tag:"' . $tag . '"', fn(array $n): bool => in_array($tag, (array)($n['tags'] ?? array()), true)),
		array('source_identifier:"' . "$db:$ordreId" . '"', fn(array $n): bool => ($n['sourceIdentifier'] ?? null) === "$db:$ordreId"),
	);
	foreach ($searches as [$query, $match]) {
		$res = shopify_graphql($shop, 'storeOrderFind', SHOPIFY_STORE_ORDER_FIND, array('query' => $query),
			($rt['graphql'] ?? array()) + array('deadline' => (float)($rt['deadline'] ?? microtime(true) + 50), 'correlation' => array('ref' => $tag)));
		if (!$res['ok']) {
			throw new ShopifyStoreOrderError("storeOrderFind failed ({$res['error']})");
		}
		$found = array_values(array_filter($res['data']['orders']['nodes'] ?? array(), $match));
		if (count($found) > 1) {
			throw new ShopifyStoreOrderError(count($found) . " orders in shop {$shop['shop_key']} are sale $ordreId's; link it by hand");
		}
		if ($found !== array()) {
			return $found[0];
		}
	}
	return null;
}

/**
 * Job store.export (prio 20): freezes the sale into its orderCreate input on the link, then sends it
 * once. A sale already linked to its order is done; one Shopify refused is built again on the next run.
 */
function shopify_store_orders_run(array $job, float $deadline, callable $heartbeat, array $rt = array()): array {
	shopify_store_orders_require();
	$ordreId = (int)($job['payload']['ordre_id'] ?? 0);
	if ($ordreId <= 0) {
		throw new LogicException("job {$job['id']} store.export has no ordre_id");
	}
	$rt += array('deadline' => $deadline);
	$shop = shopify_db_rows('SELECT * FROM shopify_shops WHERE id = $1 AND store_orders_enabled', array((int)$job['shop_id']))[0] ?? null;
	$link = shopify_order_link_for_ordre($ordreId, 'store');
	if ($shop === null || ($link !== null && $link['order_gid'] !== null)) {
		return array('outcome' => 'done');
	}
	$stored = $link === null || $link['state'] === 'refused' ? null : (json_decode((string)$link['flags'], true)['store'] ?? null);
	if ($stored === null) {
		$snap = shopify_store_orders_snapshot($ordreId);
		$check = $snap === null ? array('ok' => false) : shopify_store_orders_eligibility($snap, $shop, new DateTimeImmutable());
		if (!$check['ok']) {
			return array('outcome' => 'done');
		}
		$customer = shopify_store_orders_customer($shop, $snap);
		if (isset($customer['wait'])) {
			return array('outcome' => 'yield', 'checkpoint' => array(), 'run_after' => $customer['wait']);
		}
		try {
			$url = dokument_link($ordreId, 'invoice');
		} catch (DocumentRenderError | InvalidArgumentException $e) {
			$url = null;
		}
		try {
			$lines = shopify_store_orders_project_lines($snap, $snap['links']);
		} catch (DomainException $e) {
			shopify_error_open((int)$shop['id'], 'store.export', "ordre:$ordreId", "sale $ordreId was not created in Shopify: " . $e->getMessage(), 'error', array('ordre_id' => $ordreId));
			return array('outcome' => 'done');
		}
		$vars = shopify_store_orders_build_payload($snap, $customer, $lines, shopify_store_orders_project_payments($snap), $url);
		$link = shopify_db_transaction(function () use ($shop, $ordreId, $vars): array {
			$reserved = shopify_reserve_order_link((int)$shop['id'], null, 'store', array('ordre_id' => $ordreId, 'state' => 'create_started'));
			if (!$reserved['created'] && $reserved['state'] !== 'refused') {
				return $reserved['link'];
			}
			return shopify_advance_order_link($reserved['id'], 'create_started', array('flags' => array('store' => array('vars' => $vars, 'sent_at' => null))));
		});
		if ($link['order_gid'] !== null) {
			return array('outcome' => 'done');
		}
		$stored = json_decode((string)$link['flags'], true)['store'];
	}
	return shopify_store_orders_send($rt, $shop, $link, $stored);
}

/** Sends the frozen input once: a send that may have reached Shopify is looked up first, and waits for the search to catch up. */
function shopify_store_orders_send(array $rt, array $shop, array $link, array $stored): array {
	$ordreId = (int)$link['ordre_id'];
	$db = (string)shopify_db_rows('SELECT current_database() AS db')[0]['db'];
	if ($stored['sent_at'] !== null) {
		$found = shopify_store_orders_find_remote($rt, $shop, $db, $ordreId);
		if ($found !== null) {
			return shopify_store_orders_adopt($link, $found);
		}
		if (time() - (int)$stored['sent_at'] < SHOPIFY_STORE_ORDER_SETTLE) {
			return array('outcome' => 'yield', 'checkpoint' => array(), 'run_after' => SHOPIFY_STORE_ORDER_SETTLE - (time() - (int)$stored['sent_at']));
		}
	}
	$mark = fn(?int $at): array => shopify_advance_order_link((int)$link['id'], 'create_started', array('flags' => array('store' => array('vars' => $stored['vars'], 'sent_at' => $at))));
	$mark(time());
	$reconcile = function () use ($rt, $shop, $db, $ordreId): array {
		$found = shopify_store_orders_find_remote($rt, $shop, $db, $ordreId);
		return $found === null ? array('state' => 'unknown') : array('state' => 'applied', 'data' => array('orderCreate' => array('order' => $found, 'userErrors' => array())));
	};
	$res = shopify_graphql($shop, 'orderCreate', SHOPIFY_STORE_ORDER_CREATE, $stored['vars'], ($rt['graphql'] ?? array()) + array('deadline' => (float)$rt['deadline'],
		'retry_mode' => 'reconcile', 'reconcile' => $reconcile, 'correlation' => array('ref' => shopify_store_orders_tag($db, $ordreId), 'ordre_id' => $ordreId)));
	if ($res['ok']) {
		$order = $res['data']['orderCreate']['order'] ?? null;
		if (!is_array($order) || (string)($order['id'] ?? '') === '') {
			throw new ShopifyStoreOrderError("orderCreate for sale $ordreId answered without the order");
		}
		return shopify_store_orders_adopt($link, $order);
	}
	if ($res['error'] === 'user_errors') {
		$why = implode('; ', array_map(fn(array $e): string => ($e['field'] ? implode('.', (array)$e['field']) . ': ' : '') . $e['message'], $res['user_errors']));
		shopify_db_transaction(function () use ($link, $shop, $ordreId, $why): void {
			shopify_advance_order_link((int)$link['id'], 'refused');
			shopify_error_open((int)$shop['id'], 'store.export', "ordre:$ordreId", "sale $ordreId was not created in Shopify: $why", 'error', array('ordre_id' => $ordreId));
		});
		return array('outcome' => 'done');
	}
	if ($res['outcome'] === 'uncertain') {
		return array('outcome' => 'yield', 'checkpoint' => array(), 'run_after' => SHOPIFY_STORE_ORDER_SETTLE);
	}
	// nothing was created: the next attempt may send at once
	$mark(null);
	if ($res['error'] === 'deadline') {
		return array('outcome' => 'yield', 'checkpoint' => array(), 'run_after' => 1);
	}
	throw new ShopifyStoreOrderError("orderCreate for sale $ordreId failed ({$res['error']}): " . implode('; ', array_column($res['errors'], 'message')));
}

/** Links the sale to its order: GID and name on the link (K54: the name shows in the order's Shopify panel). */
function shopify_store_orders_adopt(array $link, array $order): array {
	shopify_db_transaction(function () use ($link, $order): void {
		shopify_advance_order_link((int)$link['id'], 'created', array('order_gid' => (string)$order['id'], 'order_name' => (string)($order['name'] ?? '')));
		shopify_error_resolve_key((int)$link['shop_id'], 'store.export', "ordre:{$link['ordre_id']}");
	});
	return array('outcome' => 'done');
}

/**
 * Job store.recover (hourly per shop, and queued by the echo guard): queues the shop's posted sales of
 * the last SHOPIFY_STORE_ORDER_RECOVER_DAYS that were never queued (the job itself refuses those from
 * before go-live), and hastens sales whose send has waited past the settle time.
 */
function shopify_store_orders_recover(array $job, float $deadline, callable $heartbeat, array $rt = array()): array {
	$shop = shopify_db_rows('SELECT * FROM shopify_shops WHERE id = $1 AND store_orders_enabled AND go_live_at IS NOT NULL', array((int)$job['shop_id']))[0] ?? null;
	if ($shop === null) {
		return array('outcome' => 'done');
	}
	// the rules of shopify_store_orders_eligibility(), so a sale that never goes does not hold a place under the limit
	$missed = shopify_db_rows("SELECT o.id FROM ordrer o LEFT JOIN pos_leveringsvalg v ON v.id = o.leveringsvalg_id
		WHERE o.art = 'PO' AND o.status >= 3 AND btrim(coalesce(o.fakturanr, '')) <> ''
		AND o.fakturadate >= greatest($2::timestamp, now() - make_interval(days => $3))::date
		AND o.fakturadate + coalesce(substring(o.tidspkt from '^\\s*(([01]?[0-9]|2[0-3]):[0-5][0-9])'), '00:00')::time >= $2::timestamp
		AND round((coalesce(o.sum, 0) + coalesce(o.moms, 0)) * 100) > 0
		AND (o.konto_id > 0 OR v.type IN ('afhentning', 'levering'))
		AND EXISTS (SELECT 1 FROM ordrelinjer ol WHERE ol.ordre_id = o.id AND ol.posnr >= 0)
		AND lower(coalesce(nullif(btrim(o.sprog), ''), 'dansk')) IN (SELECT lower(sprog) FROM shopify_brands WHERE shop_id = $1)
		AND NOT EXISTS (SELECT 1 FROM shopify_order_links l WHERE l.ordre_id = o.id AND l.direction = 'store')
		UNION SELECT l.ordre_id FROM shopify_order_links l WHERE l.shop_id = $1 AND l.direction = 'store' AND l.order_gid IS NULL
		AND l.state = 'create_started' AND l.updated_at < now() - make_interval(secs => $4) ORDER BY 1 LIMIT 500",
		array((int)$shop['id'], $shop['go_live_at'], SHOPIFY_STORE_ORDER_RECOVER_DAYS, SHOPIFY_STORE_ORDER_SETTLE));
	foreach ($missed as $row) {
		shopify_queue_enqueue_coalesced('store.export', (int)$shop['id'], "{$shop['shop_key']}:store:{$row['id']}", array('ordre_id' => (int)$row['id']),
			SHOPIFY_QUEUE_PRIORITY['store.export'], null, array('ordre_id' => (int)$row['id']));
	}
	return array('outcome' => 'done', 'queued' => count($missed));
}

/** The echo guard's part (WP09): our own store order came back, so a sale still waiting for its answer is queued to read it. */
function shopify_store_orders_echo(int $shopId, array $order, string $db): void {
	if (!preg_match('/^' . preg_quote($db, '/') . ':([0-9]+)$/', (string)($order['source_identifier'] ?? ''), $m)) {
		return;
	}
	$link = shopify_order_link_for_ordre((int)$m[1], 'store');
	$shop = shopify_db_rows('SELECT shop_key FROM shopify_shops WHERE id = $1', array($shopId))[0] ?? null;
	if ($link !== null && $link['order_gid'] === null && $link['state'] === 'create_started' && $shop !== null) {
		shopify_queue_enqueue_coalesced('store.export', $shopId, "{$shop['shop_key']}:store:{$m[1]}", array('ordre_id' => (int)$m[1]),
			SHOPIFY_QUEUE_PRIORITY['store.export'], null, array('ordre_id' => (int)$m[1]));
	}
}
