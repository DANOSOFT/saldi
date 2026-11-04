<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/orderIntake.php --- ver 5.1.0 --- 2026.11.04
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
// A paid web order before any write (WP09-1, K15, K22, K24, K25, K26): fetched from Shopify,
// refused when it is Saldi's own store order coming back, everything S09b's writes would refuse
// checked (items, components, freight item, accounts and VAT, payments and POS accounts, the
// total, the posting date's period), and the lines allocated to lagre (WP07). What fails
// is a K25 error: zero rows in Saldi, one open error, and the order can be run again once it
// is fixed. What is only unusual (an unmapped freight rate, an unknown card) is an alarm, and
// the order goes on with the shop's default. The creation, delivery and posting are WP09-2.
//
// shopify_validate_paid_order() is pure: it reads nothing. shopify_intake_context() reads what
// it needs from the tenant, so the rules are tested without a database and the reads on one.
//
// 20261103 CL/SS SD-742: created (WP09-1).
// 20261103 CL/SS SD-742 review: a line split over several fulfillment locations is an alarm; an order that does not fit one read is refused.
// 20261103 CL/SS SD-742 review: batch-controlled items, a VAT group without a rate and an ambiguous SKU are refused.
//                The unit price is rounded down so the last unit is never below it; closed fulfillment orders are no assignment.
// 20261103 CL/SS SD-743: the order's VAT (totalTaxSet) is read for S09b, and an order going abroad is refused.
// 20261104 CL/SS SD-743 review: an absent totalTaxSet stays unknown, and an empty shipping country falls back to billing.
// 20261104 CL/SS SD-808: the paid order is read in pages, each document under Shopify's query cost limit of 1000.
// 20261104 CL/SS SD-808 review: an order changed while it was read in pages is refused as changed, and retried.
// 20261104 CL/SS SD-743 review 2: a kasse VAT code of 0 and a VAT account 1 are refused, as the posting stops at them.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../links.php';
require_once __DIR__ . '/../errors.php';
require_once __DIR__ . '/../client.php';
require_once __DIR__ . '/../coexistence.php';
require_once __DIR__ . '/allocation.php';
require_once __DIR__ . '/availability.php';
require_once __DIR__ . '/stock.php';

/** A store order Saldi exported (WP13) carries these; such an order is never imported (K37, K38). */
const SHOPIFY_ECHO_SOURCE = 'saldi-pos';
const SHOPIFY_ECHO_TAG = 'saldi-butik';
/** Tag of an order invoiced with a line short everywhere (plan P:215). */
const SHOPIFY_TAG_SHORTAGE = 'saldi-mangler-lager';
/** The payment type of a gateway no row in shopify_payment_map names. */
const SHOPIFY_PAYMENT_UNKNOWN = 'Ukendt kort';
/** The header's payment terms; also the posting of an order whose payments do not fit felt_1..felt_5 (DIV/3 box10). */
const SHOPIFY_PAYMENT_FALLBACK = 'Kreditkort';
/** An order going elsewhere may carry another country's VAT (Shopify Markets), which the intake cannot post. */
const SHOPIFY_INTAKE_COUNTRY = 'DK';
/** A variant item's stock is allocated under this base plus its variant id, so two variants of one item never share stock. */
const SHOPIFY_INTAKE_VARIANT_STOCK = 1000000000000;
/** Transactions that are money received. */
const SHOPIFY_PAID_KINDS = array('SALE', 'CAPTURE');

/** The fields of an order line, as the order read and its further line pages ask for them. */
const SHOPIFY_ORDER_LINE_FIELDS = 'id sku name quantity currentQuantity variant { id } product { id }
	originalTotalSet { shopMoney { amount } } discountAllocations { allocatedAmountSet { shopMoney { amount } } }';
/**
 * The order with its first 100 lines. Shopify prices a connection at 2 + first x its nodes, so one
 * document with every line and every fulfillment order's lines would cost far above the 1000
 * points a single query may (MAX_COST_EXCEEDED): the read is split, each document under it.
 */
const SHOPIFY_ORDER_READ = 'query paidOrderRead($id: ID!) { order(id: $id) {
	id name createdAt updatedAt sourceName sourceIdentifier tags note email phone taxesIncluded currencyCode displayFinancialStatus
	totalPriceSet { shopMoney { amount currencyCode } } totalTaxSet { shopMoney { amount } }
	customer { id }
	shippingAddress { firstName lastName company address1 address2 zip city country countryCodeV2 phone }
	billingAddress { firstName lastName company address1 address2 zip city country countryCodeV2 phone }
	lineItems(first: 100) { pageInfo { hasNextPage endCursor } nodes { ' . SHOPIFY_ORDER_LINE_FIELDS . ' } }
	shippingLines(first: 10) { nodes { id title code discountedPriceSet { shopMoney { amount } } } }
	transactions(first: 50) { id kind status gateway amountSet { shopMoney { amount } } paymentDetails { ... on CardPaymentDetails { company } } }
} }';
/** The order's next 100 lines. */
const SHOPIFY_ORDER_LINES_READ = 'query paidOrderLines($id: ID!, $after: String) { order(id: $id) {
	lineItems(first: 100, after: $after) { pageInfo { hasNextPage endCursor } nodes { ' . SHOPIFY_ORDER_LINE_FIELDS . ' } }
} }';
/** One fulfillment order of the order, with its lines. */
const SHOPIFY_ORDER_FULFILLMENT_READ = 'query paidOrderFulfillment($id: ID!, $after: String) { order(id: $id) {
	fulfillmentOrders(first: 1, after: $after) { pageInfo { hasNextPage endCursor } nodes { id status assignedLocation { location { id } }
		lineItems(first: 250) { pageInfo { hasNextPage } nodes { id totalQuantity lineItem { id } } } } }
} }';
/** Read last: the order and its fulfillment orders as they are now, to see the pages describe one state of the order. */
const SHOPIFY_ORDER_CHECK_READ = 'query paidOrderCheck($id: ID!) { order(id: $id) {
	updatedAt fulfillmentOrders(first: 21) { nodes { id status assignedLocation { location { id } } } }
} }';
/** An order with more is refused as too large: a partial order would be validated as a different one. */
const SHOPIFY_ORDER_MAX_LINES = 250;
const SHOPIFY_ORDER_MAX_FULFILLMENT_ORDERS = 20;

/** The read of a paid order failed; retryable when a job retry may succeed; error is shopify_graphql()'s code. */
final class ShopifyOrderReadError extends RuntimeException
{
	public function __construct(string $message, public readonly bool $retryable, public readonly string $error = '')
	{
		parent::__construct($message);
	}
}

/**
 * Reads a paid order from Shopify: the order with its first lines, its further lines a page at a
 * time, then its fulfillment orders one at a time (each document under the query cost limit).
 *
 * @param array{deadline?: float, graphql?: array<string, mixed>} $rt graphql: extra shopify_graphql() opts (test seams)
 * @return array<string, mixed> the order as shopify_order_from_graphql() shapes it
 * @throws ShopifyOrderReadError when a read fails, Shopify has no such order, it is too large to read whole
 *   (more than 250 lines, 20 fulfillment orders or 250 lines in one: a partial order would be validated as a different one),
 *   or it changed while it was read (edited, or a fulfillment order moved): retryable, so the job reads it again later
 */
function shopify_fetch_paid_order(array $shop, string $gid, array $rt = array()): array {
	$gid = shopify_gid($gid, 'Order');
	$opts = array('deadline' => (float)($rt['deadline'] ?? microtime(true) + 50), 'retry_mode' => 'read', 'correlation' => array('order_gid' => $gid));
	$read = static function (string $op, string $document, array $vars) use ($shop, $gid, $rt, $opts): array {
		$res = shopify_graphql($shop, $op, $document, array('id' => $gid) + $vars, ($rt['graphql'] ?? array()) + $opts);
		if (!$res['ok']) {
			$why = array_merge(array_column($res['user_errors'], 'message'), array_column($res['errors'], 'message'));
			throw new ShopifyOrderReadError("$op failed ({$res['error']})" . ($why ? ': ' . implode('; ', $why) : ''), $res['retryable'], (string)$res['error']);
		}
		$node = $res['data']['order'] ?? null;
		if (!is_array($node)) {
			throw new ShopifyOrderReadError("order $gid does not exist in {$shop['shop_key']}", false, 'not_found');
		}
		return $node;
	};
	$tooLarge = static fn(): ShopifyOrderReadError => new ShopifyOrderReadError("order $gid has more than " . SHOPIFY_ORDER_MAX_LINES . ' lines or '
		. SHOPIFY_ORDER_MAX_FULFILLMENT_ORDERS . ' fulfillment orders; enter it by hand', false, 'too_large');

	$node = $read('paidOrderRead', SHOPIFY_ORDER_READ, array());
	$page = $node['lineItems'] ?? array();
	$lines = $page['nodes'] ?? array();
	while (!empty($page['pageInfo']['hasNextPage'])) {
		if (count($lines) >= SHOPIFY_ORDER_MAX_LINES) {
			throw $tooLarge();
		}
		$page = $read('paidOrderLines', SHOPIFY_ORDER_LINES_READ, array('after' => $page['pageInfo']['endCursor'] ?? null))['lineItems'] ?? array();
		$lines = array_merge($lines, $page['nodes'] ?? array());
	}
	if (count($lines) > SHOPIFY_ORDER_MAX_LINES) {
		throw $tooLarge();
	}
	$node['lineItems'] = array('nodes' => $lines);

	$fulfillmentOrders = array();
	$after = null;
	do {
		$page = $read('paidOrderFulfillment', SHOPIFY_ORDER_FULFILLMENT_READ, array('after' => $after))['fulfillmentOrders'] ?? array();
		foreach ($page['nodes'] ?? array() as $fo) {
			if (!empty($fo['lineItems']['pageInfo']['hasNextPage'])) {
				throw $tooLarge();
			}
			$fulfillmentOrders[] = $fo;
		}
		$after = $page['pageInfo']['endCursor'] ?? null;
		$more = !empty($page['pageInfo']['hasNextPage']);
		if ($more && count($fulfillmentOrders) >= SHOPIFY_ORDER_MAX_FULFILLMENT_ORDERS) {
			throw $tooLarge();
		}
	} while ($more);
	$node['fulfillmentOrders'] = array('nodes' => $fulfillmentOrders);

	// the pages are several reads: an edit of the order, or a fulfillment order moved, in between would mix two states
	$summary = static fn(array $nodes): array => array_map(static fn(array $fo): array => array((string)($fo['id'] ?? ''), (string)($fo['status'] ?? ''),
		(string)($fo['assignedLocation']['location']['id'] ?? '')), $nodes);
	$now = $read('paidOrderCheck', SHOPIFY_ORDER_CHECK_READ, array());
	if (($now['updatedAt'] ?? null) !== ($node['updatedAt'] ?? null) || $summary($now['fulfillmentOrders']['nodes'] ?? array()) !== $summary($fulfillmentOrders)) {
		throw new ShopifyOrderReadError("order $gid changed while it was read; it is read again", true, 'changed');
	}
	return shopify_order_from_graphql($node);
}

/**
 * The order the way the intake reads it (pure): amounts as strings with two decimals, the
 * lines with the location their fulfillment order is assigned to (the first, when Shopify has
 * split a line over several fulfillment orders; assigned_locations lists them all). A closed or
 * cancelled fulfillment order, and a line it holds none of, is no assignment.
 *
 * @param array<string, mixed> $o the order node as shopify_fetch_paid_order() puts it together from its reads
 * @return array{gid: string, name: string, created_at: string, source_name: string, source_identifier: string,
 *   tags: list<string>, note: string, email: string, phone: string, taxes_included: bool, currency: string,
 *   total: string, total_tax: string|null, financial_status: string, customer_gid: string|null, shipping_address: array<string, string>|null,
 *   billing_address: array<string, string>|null,
 *   lines: list<array{gid: string, sku: string, name: string, quantity: int, current_quantity: int, variant_gid: string|null, product_gid: string|null,
 *     original_total: string, discount: string, assigned_location: string|null, assigned_locations: list<string>}>,
 *   shipping: list<array{gid: string, title: string, code: string, amount: string}>,
 *   transactions: list<array{gid: string, kind: string, status: string, gateway: string, amount: string, card: string}>}
 */
function shopify_order_from_graphql(array $o): array {
	$money = static fn($set): string => number_format((float)($set['shopMoney']['amount'] ?? 0), 2, '.', '');
	$assigned = array();
	foreach ($o['fulfillmentOrders']['nodes'] ?? array() as $fo) {
		if (in_array(strtoupper((string)($fo['status'] ?? '')), array('CLOSED', 'CANCELLED'), true)) {
			continue;
		}
		$location = $fo['assignedLocation']['location']['id'] ?? null;
		foreach ($fo['lineItems']['nodes'] ?? array() as $foLine) {
			if ((int)($foLine['totalQuantity'] ?? 0) < 1) {
				continue;
			}
			$lineGid = $foLine['lineItem']['id'] ?? null;
			if ($lineGid !== null && $location !== null && !in_array((string)$location, $assigned[$lineGid] ?? array(), true)) {
				$assigned[$lineGid][] = (string)$location;
			}
		}
	}
	$lines = array();
	foreach ($o['lineItems']['nodes'] ?? array() as $l) {
		$discount = 0.0;
		foreach ($l['discountAllocations'] ?? array() as $d) {
			$discount += (float)($d['allocatedAmountSet']['shopMoney']['amount'] ?? 0);
		}
		$lines[] = array(
			'gid' => (string)$l['id'],
			'sku' => trim((string)($l['sku'] ?? '')),
			'name' => (string)($l['name'] ?? ''),
			'quantity' => (int)($l['quantity'] ?? 0),
			'current_quantity' => (int)($l['currentQuantity'] ?? $l['quantity'] ?? 0),
			'variant_gid' => isset($l['variant']['id']) ? (string)$l['variant']['id'] : null,
			'product_gid' => isset($l['product']['id']) ? (string)$l['product']['id'] : null,
			'original_total' => $money($l['originalTotalSet'] ?? array()),
			'discount' => number_format($discount, 2, '.', ''),
			'assigned_location' => $assigned[(string)$l['id']][0] ?? null,
			'assigned_locations' => $assigned[(string)$l['id']] ?? array(),
		);
	}
	$shipping = array();
	foreach ($o['shippingLines']['nodes'] ?? array() as $s) {
		$shipping[] = array('gid' => (string)$s['id'], 'title' => trim((string)($s['title'] ?? '')), 'code' => trim((string)($s['code'] ?? '')),
			'amount' => $money($s['discountedPriceSet'] ?? array()));
	}
	$transactions = array();
	foreach ($o['transactions'] ?? array() as $t) {
		$transactions[] = array('gid' => (string)$t['id'], 'kind' => strtoupper((string)($t['kind'] ?? '')), 'status' => strtoupper((string)($t['status'] ?? '')),
			'gateway' => trim((string)($t['gateway'] ?? '')), 'amount' => $money($t['amountSet'] ?? array()),
			'card' => trim((string)($t['paymentDetails']['company'] ?? '')));
	}
	$address = static function ($a): ?array {
		if (!is_array($a)) {
			return null;
		}
		$out = array();
		foreach (array('firstName', 'lastName', 'company', 'address1', 'address2', 'zip', 'city', 'country', 'countryCodeV2', 'phone') as $k) {
			$out[$k] = trim((string)($a[$k] ?? ''));
		}
		return $out;
	};
	$tags = $o['tags'] ?? array();
	return array(
		'gid' => (string)$o['id'],
		'name' => (string)($o['name'] ?? ''),
		'created_at' => (string)($o['createdAt'] ?? ''),
		'source_name' => (string)($o['sourceName'] ?? ''),
		'source_identifier' => (string)($o['sourceIdentifier'] ?? ''),
		'tags' => array_values(array_map('strval', is_array($tags) ? $tags : array())),
		'note' => (string)($o['note'] ?? ''),
		'email' => trim((string)($o['email'] ?? '')),
		'phone' => trim((string)($o['phone'] ?? '')),
		'taxes_included' => (bool)($o['taxesIncluded'] ?? false),
		'currency' => (string)($o['currencyCode'] ?? ''),
		'total' => $money($o['totalPriceSet'] ?? array()),
		// Shopify may leave it out: unknown, not 0.00, so the VAT check is skipped rather than refusing the order
		'total_tax' => isset($o['totalTaxSet']['shopMoney']['amount']) ? $money($o['totalTaxSet']) : null,
		'financial_status' => strtoupper((string)($o['displayFinancialStatus'] ?? '')),
		'customer_gid' => isset($o['customer']['id']) ? (string)$o['customer']['id'] : null,
		'shipping_address' => $address($o['shippingAddress'] ?? null),
		'billing_address' => $address($o['billingAddress'] ?? null),
		'lines' => $lines,
		'shipping' => $shipping,
		'transactions' => $transactions,
	);
}

/**
 * Whether the order is one Saldi itself sent to Shopify as a store order (pure): by its source,
 * its source identifier (`<db>:...`) or the tag. Such an order is logged, never imported (K37, K38).
 */
function shopify_is_echo(array $order, string $db): bool {
	if (strcasecmp((string)($order['source_name'] ?? ''), SHOPIFY_ECHO_SOURCE) === 0) {
		return true;
	}
	$identifier = (string)($order['source_identifier'] ?? '');
	if ($db !== '' && strncmp($identifier, "$db:", strlen($db) + 1) === 0) {
		return true;
	}
	foreach ($order['tags'] ?? array() as $tag) {
		if (strcasecmp(trim((string)$tag), SHOPIFY_ECHO_TAG) === 0) {
			return true;
		}
	}
	return false;
}

/**
 * Whether the Shopify intake may import for the shop (K06): not while the legacy intake of the
 * shop's slot is still open. A shop without a slot has no legacy integration to wait for.
 */
function shopify_intake_admitted(array $shop): bool {
	$slot = isset($shop['legacy_slot']) && $shop['legacy_slot'] !== null ? (int)$shop['legacy_slot'] : 0;
	if (!in_array($slot, array(4, 5, 6), true)) {
		return true;
	}
	return !shopify_legacy_enabled(array('now' => time()), $slot, 'intake');
}

/**
 * Checks a paid order against the tenant and allocates its lines (pure; $ctx comes from
 * shopify_intake_context()). ok is false when an error is listed: nothing may be written, and
 * every error is a K25 row for the error list. The alarms are raised either way.
 *
 * What S09b writes must go through without a refusal, so everything opret_ordrelinje(),
 * opret_saet(), levering() and bogfor() would refuse is checked here: every line's item (by its
 * Shopify variant link first, its SKU as varenr or barcode second), every set component and the
 * freight item must exist, carry no serial numbers, and have an item group of the fiscal year
 * with a purchase and a sales account, the sales account in the chart with a VAT code that has
 * a VAT account (a VAT-free group needs none). A variant line needs its variant's barcode, the
 * way opret_ordrelinje() finds a variant. The order must be paid in full (sales and captures
 * less refunds equal the total) in the tenant's currency with taxes included, its lines and
 * freight must add up to the total, and it must not have been edited or refunded in Shopify
 * before the intake. It is posted on the intake date, which must be in the fiscal year and not
 * in a closed VAT period.
 *
 * Freight: a rate maps to its freight item and pickup lager (K24: click-and-collect records the
 * chosen shop); an unmapped one gets the shop's default freight item and an alarm, no default is
 * an error. Payments: a gateway maps to a Saldi payment type, an unmapped one is 'Ukendt kort'
 * and an alarm. One or two types go to felt_1..felt_5 (type 1, amount 1, type 2, amount 2, the
 * web kasse; one type leaves a second amount of 0.00, as bogfor() needs both amounts), which
 * bogfor() posts as a till sale of the web kasse: the kasse needs its cash account and VAT code,
 * and each type its card account when the POS settings name it, else the account for other cards. More than two types, or no web
 * kasse, posts as Kreditkort (DIV/3 box10) with an alarm; that account must be valid either way,
 * since the header's payment terms are Kreditkort.
 *
 * Prices: S09b writes the lines without VAT (incl_moms off), as ordrelinjer.pris keeps three
 * decimals net and a VAT-inclusive price would be rounded after the VAT is taken off. So a line's
 * paid total (original total less its discounts, VAT included) becomes line_net at the item's VAT
 * rate, and a plain line gets unit_net for all units but one and last_unit_net for that one,
 * which add up to line_net exactly. A set line's net set price is line_net in total: opret_saet()
 * takes the price per set times the sets, so S09b passes line_net / quantity unrounded. The
 * freight's net amount is freight.net. The invoice these give is then summed as momsupdat() sums
 * it, and a difference from Shopify's total is an alarm (S09b writes the order's sum and VAT).
 *
 * The allocation is WP07's: an assignment to a display, ignored or unmapped location is an
 * alarm, a shortage is tagged saldi-mangler-lager and alarmed, and the order is still invoiced.
 * A variant allocates under its own stock key (SHOPIFY_INTAKE_VARIANT_STOCK + variant id), so the
 * allocation's vare_id is that key: S09b takes vare_id and variant from the lines, the lager from
 * the allocation. A set must have one VAT rate for itself and its components.
 *
 * For S09b: the order's momssats (header momssats, the highest rate of its lines) is written on
 * the order and lent as the global $momssats, since opret_ordrelinje() caps a line's rate at it.
 *
 * @param array<string, mixed> $shop the shopify_shops row
 * @param array<string, mixed> $order as shopify_order_from_graphql() shapes it
 * @param array<string, mixed> $ctx as shopify_intake_context() returns it
 * @return array{ok: bool, errors: list<array{kind: string, ref: string, message: string}>,
 *   alarms: list<array{kind: string, ref: string, message: string}>, header: array<string, mixed>,
 *   lines: list<array<string, mixed>>, freight: array<string, mixed>|null, allocation: array<string, mixed>|null, tags: list<string>}
 */
function shopify_validate_paid_order(array $shop, array $order, array $ctx): array {
	$errors = array();
	$alarms = array();
	$gid = (string)$order['gid'];
	$error = static function (string $kind, string $message) use (&$errors, $gid): void {
		$errors[] = array('kind' => $kind, 'ref' => $gid, 'message' => $message);
	};
	$alarm = static function (string $kind, string $message) use (&$alarms, $gid): void {
		$alarms[] = array('kind' => $kind, 'ref' => $gid, 'message' => $message);
	};
	$name = (string)$order['name'];
	// what makes an item unusable for a headless order, or null
	$unusable = static function (?array $item, bool $variantAllowed): ?string {
		if ($item === null) {
			return 'is no item in Saldi';
		}
		if (!empty($item['serial'])) {
			return "(item {$item['varenr']}) carries serial numbers, which the intake cannot pick";
		}
		if (!empty($item['variant_item']) && !$variantAllowed) {
			return "(item {$item['varenr']}) is a variant item, which can't be used here";
		}
		if (!empty($item['batch'])) {
			return "(item {$item['varenr']}) is batch-controlled, which the intake cannot deliver (levering() stops when the batches don't cover it)";
		}
		if (!empty($item['written_as_other'])) {
			return "(item {$item['varenr']}) is written as '{$item['written_as']}', which is another variant's barcode, so opret_ordrelinje() would take that one";
		}
		if (($item['problem'] ?? null) !== null) {
			return "(item {$item['varenr']}) {$item['problem']}";
		}
		return null;
	};

	if (($order['customer_gid'] ?? null) === null) {
		$error('order.customer', "order $name has no customer: a debtor cannot be made for it");
	}
	$fiscal = $ctx['fiscal'] ?? null;
	$today = (string)($ctx['today'] ?? '');
	if (!is_array($fiscal) || $today === '' || $today < $fiscal['from'] || $today > $fiscal['to']) {
		$error('order.period', "order $name would be posted on $today, outside the fiscal year " . ($fiscal ? "{$fiscal['from']} to {$fiscal['to']}" : '(none open)'));
	} elseif (($ctx['period_closed'] ?? null) !== null) {
		$error('order.period', "order $name would be posted on $today: {$ctx['period_closed']}");
	}
	if ((string)$order['currency'] !== (string)($ctx['currency'] ?? 'DKK')) {
		$error('order.currency', "order $name is in {$order['currency']}, the tenant posts in " . ($ctx['currency'] ?? 'DKK'));
	}
	if (empty($order['taxes_included'])) {
		$error('order.taxes', "order $name has prices without VAT; the intake takes Shopify's prices as VAT-inclusive");
	}
	// an address without a country reads as '', so the billing country is the fallback for an empty one, not only a missing one
	$destination = strtoupper(trim((string)($order['shipping_address']['countryCodeV2'] ?? '')));
	if ($destination === '') {
		$destination = strtoupper(trim((string)($order['billing_address']['countryCodeV2'] ?? '')));
	}
	if ($destination !== '' && $destination !== SHOPIFY_INTAKE_COUNTRY) {
		$error('order.country', "order $name goes to $destination; the intake posts Danish VAT only, so an order abroad is entered by hand");
	}

	// lines
	$lines = array();
	$allocationLines = array();
	$sum = 0.0;
	foreach ($order['lines'] as $l) {
		$paid = round((float)$l['original_total'] - (float)$l['discount'], 2);
		$sum += $paid;
		$item = null;
		if ($l['variant_gid'] !== null && isset($ctx['by_variant'][$l['variant_gid']])) {
			$item = $ctx['by_variant'][$l['variant_gid']];
		} elseif ($l['sku'] !== '' && isset($ctx['by_sku'][$l['sku']])) {
			$item = $ctx['by_sku'][$l['sku']];
		}
		$label = $l['sku'] !== '' ? "SKU {$l['sku']}" : "line {$l['gid']}";
		if ($item === null) {
			$error('order.sku', "order $name: $label is no item in Saldi");
			continue;
		}
		if (!empty($item['sku_ambiguous'])) {
			$error('order.sku', "order $name: $label names more than one item or variant in Saldi");
			continue;
		}
		if ($l['quantity'] < 1) {
			$error('order.quantity', "order $name: $label has quantity {$l['quantity']}");
			continue;
		}
		if (isset($l['current_quantity']) && (int)$l['current_quantity'] !== (int)$l['quantity']) {
			$error('order.edited', "order $name: $label was changed in Shopify from {$l['quantity']} to {$l['current_quantity']} before Saldi took the order; enter it by hand");
			continue;
		}
		if (!empty($item['variant_item']) && ((int)$item['variant_id'] < 1 || trim((string)($item['variant_barcode'] ?? '')) === '')) {
			$error('order.variant', "order $name: $label (item {$item['varenr']}) is a variant item; its variant needs a barcode");
		}
		$why = $unusable($item, true);
		if ($why !== null) {
			$error(!empty($item['serial']) ? 'order.serial' : 'order.account', "order $name: $label $why");
		}
		if (!empty($item['set'])) {
			$listTotal = 0.0;
			foreach ($item['bom'] as $c) {
				$component = $ctx['items'][(int)$c['vare_id']] ?? null;
				$why = $unusable($component, false);
				if ($why === null && (float)$component['vat_rate'] !== (float)($item['vat_rate'] ?? 0)) {
					$why = "(item {$component['varenr']}) has VAT {$component['vat_rate']} %, the set {$item['vat_rate']} %: opret_saet() takes the VAT from the components, so a set must have one rate";
				}
				if ($why === null && !empty($component['bom'])) {
					$why = "(item {$component['varenr']}) is a set itself: a set is one BOM level";
				}
				if ($why !== null) {
					$error('order.component', "order $name: component {$c['vare_id']} of $label $why");
				}
				$listTotal += round($c['quantity_milli'] / 1000 * round((float)($component['salgspris'] ?? 0), 2), 3);
			}
			if ($listTotal <= 0) {
				$error('order.component', "order $name: the components of $label have no price, so no discount can bring them to the set price");
			}
		}
		if (count($l['assigned_locations'] ?? array()) > 1) {
			$alarm('order.assignment', "order $name: $label is split over " . count($l['assigned_locations']) . ' fulfillment locations in Shopify; it is drawn from the first, then by priority');
		}
		$quantity = (int)$l['quantity'];
		$rate = (float)($item['vat_rate'] ?? 0);
		$net = round($paid / (1 + $rate / 100), 3);
		$netMilli = (int)round($net * 1000);
		$unit = (float)(intdiv($netMilli, $quantity) / 1000);
		$lines[] = array(
			'key' => $l['gid'],
			'vare_id' => (int)$item['vare_id'],
			'varenr' => (string)$item['varenr'],
			'variant_id' => (int)$item['variant_id'],
			'variant_barcode' => (string)($item['variant_barcode'] ?? ''),
			'quantity' => $quantity,
			'vat_rate' => $rate,
			'line_paid' => $paid,
			'line_net' => $net,
			'unit_net' => $unit,
			'last_unit_net' => (float)(($netMilli - intdiv($netMilli, $quantity) * ($quantity - 1)) / 1000),
			'set' => !empty($item['set']),
			'bom' => !empty($item['set']) ? array_values($item['bom']) : array(),
			'sku' => $l['sku'],
			'name' => $l['name'],
		);
		$allocationLine = array(
			'key' => $l['gid'],
			'vare_id' => (int)($item['stock_key'] ?? $item['vare_id']),
			'quantity_milli' => $quantity * 1000,
			'assigned_location' => $l['assigned_location'],
		);
		if (!empty($item['set'])) {
			$allocationLine['components'] = array_values($item['bom']);
		}
		$allocationLines[] = $allocationLine;
	}

	// freight
	$freight = null;
	if ($order['shipping']) {
		$rate = $order['shipping'][0];
		$amount = 0.0;
		foreach ($order['shipping'] as $s) {
			$amount += (float)$s['amount'];
		}
		$sum += round($amount, 2);
		$map = $ctx['shipping'][$rate['title']] ?? $ctx['shipping'][$rate['code']] ?? null;
		if ($map !== null && (int)($map['vare_id'] ?? 0) > 0) {
			$freight = array('vare_id' => (int)$map['vare_id'], 'pickup_lager' => isset($map['pickup_lager']) ? (int)$map['pickup_lager'] : null,
				'rate' => $rate['title'], 'amount' => round($amount, 2));
		} elseif ((int)($ctx['freight_default_vare_id'] ?? 0) > 0) {
			$alarm('order.freight', "order $name: freight rate '{$rate['title']}' is not mapped; the default freight item is used");
			$freight = array('vare_id' => (int)$ctx['freight_default_vare_id'], 'pickup_lager' => null, 'rate' => $rate['title'], 'amount' => round($amount, 2));
		} else {
			$error('order.freight', "order $name: freight rate '{$rate['title']}' is not mapped and the shop has no default freight item");
		}
		if ($freight !== null) {
			$freightItem = $ctx['items'][$freight['vare_id']] ?? null;
			$why = $unusable($freightItem, false);
			if ($why !== null) {
				$error('order.freight', "order $name: the freight item {$freight['vare_id']} $why");
			}
			$freight['vat_rate'] = (float)($freightItem['vat_rate'] ?? 0);
			$freight['net'] = round($freight['amount'] / (1 + $freight['vat_rate'] / 100), 3);
		}
		if (count($order['shipping']) > 1) {
			$alarm('order.freight', "order $name has " . count($order['shipping']) . ' shipping lines; they are invoiced as one freight line');
		}
	}
	if (abs(round($sum, 2) - (float)$order['total']) > 0.005) {
		$error('order.total', "order $name: its lines and freight add up to " . number_format($sum, 2, '.', '') . ", Shopify's total is {$order['total']}");
	}

	// payments
	$felt = array('felt_1' => '', 'felt_2' => '', 'felt_3' => '', 'felt_4' => '', 'felt_5' => '');
	$byType = array();
	$net = 0.0;
	foreach ($order['transactions'] as $t) {
		if ($t['status'] !== 'SUCCESS') {
			continue;
		}
		if ($t['kind'] === 'REFUND') {
			$error('order.edited', "order $name was refunded in Shopify before Saldi took it; enter it by hand");
			$net -= (float)$t['amount'];
			continue;
		}
		if (!in_array($t['kind'], SHOPIFY_PAID_KINDS, true)) {
			continue;
		}
		$type = $ctx['payments']["{$t['gateway']}\x1f{$t['card']}"] ?? $ctx['payments']["{$t['gateway']}\x1f"] ?? null;
		if ($type === null) {
			$alarm('order.payment', "order $name: gateway '{$t['gateway']}'" . ($t['card'] !== '' ? " ({$t['card']})" : '') . " is not mapped; posted as '" . SHOPIFY_PAYMENT_UNKNOWN . "'");
			$type = SHOPIFY_PAYMENT_UNKNOWN;
		}
		$byType[$type] = round(($byType[$type] ?? 0) + (float)$t['amount'], 2);
		$net += (float)$t['amount'];
	}
	if ($byType === array()) {
		$error('order.payment', "order $name has no successful payment");
	} elseif (abs(round($net, 2) - (float)$order['total']) > 0.005) {
		$error('order.payment', "order $name is paid " . number_format($net, 2, '.', '') . " of {$order['total']}");
	}
	if (empty($ctx['kreditkort_ok'])) {
		$error('config.kreditkort', "no valid account for Kreditkort (Indstillinger > Diverse > Ordrerelaterede valg): order $name cannot be posted");
	}
	$webKasse = trim((string)($ctx['web_kasse'] ?? ''));
	if ($byType !== array() && count($byType) <= 2 && $webKasse !== '') {
		$pos = $ctx['pos'] ?? array();
		if (!ctype_digit($webKasse) || (int)$webKasse < 1) {
			$error('config.pos', "the web kasse of the shop is '$webKasse', not a kasse number");
		} else {
			if (empty($pos['kasse_account_ok'])) {
				$error('config.pos', "kasse $webKasse has no valid cash account (POS settings): order $name cannot be posted");
			}
			// bogfor_nu() asks for the code only when the invoice carries VAT, and tests it as PHP does, so a 0 stops it as an empty one does
			$carriesVat = max(array_merge(array(0.0), array_map('floatval', array_column($lines, 'vat_rate')), array((float)($freight['vat_rate'] ?? 0)))) > 0;
			if ($carriesVat && !trim((string)($pos['kasse_vat'] ?? ''))) {
				$error('config.pos', "kasse $webKasse has no VAT code (POS settings): order $name cannot be posted");
			}
			foreach (array_keys($byType) as $type) {
				$named = strtolower((string)$type);
				if (array_key_exists($named, $pos['card_accounts'] ?? array())) {
					if (empty($pos['card_accounts'][$named])) {
						$error('config.pos', "payment type '$type' is in the POS settings without a valid card account");
					}
				} elseif (empty($pos['fallback_card_account_ok'])) {
					$error('config.pos', "payment type '$type' is not in the POS settings and there is no valid account for other cards");
				}
			}
		}
		$types = array_keys($byType);
		$felt['felt_1'] = $types[0];
		$felt['felt_2'] = number_format($byType[$types[0]], 2, '.', '');
		$felt['felt_3'] = $types[1] ?? '';
		$felt['felt_4'] = number_format(isset($types[1]) ? $byType[$types[1]] : 0, 2, '.', '');
		$felt['felt_5'] = $webKasse;
	} elseif ($byType !== array()) {
		$alarm('order.payment', "order $name: " . ($webKasse === '' ? 'the shop has no web kasse' : count($byType) . ' payment types') . '; posted as ' . SHOPIFY_PAYMENT_FALLBACK);
	}

	// allocation
	$allocation = null;
	$tags = array();
	if ($errors === array() && $allocationLines !== array()) {
		try {
			$allocation = shopify_allocate_order($allocationLines, $ctx['locations'] ?? array(), $ctx['stock_milli'] ?? array(), (int)($ctx['assembly_lager'] ?? 0));
		} catch (InvalidArgumentException $e) {
			$error('order.allocation', "order $name cannot be allocated: " . $e->getMessage());
		}
	}
	if ($allocation !== null) {
		foreach ($allocation['alarms'] as $a) {
			$alarm('order.assignment', "order $name: line {$a['key']} is assigned to location {$a['location']} ({$a['kind']}); drawn by priority instead");
		}
		foreach ($allocation['shortages'] as $s) {
			$what = $s['vare_id'] >= SHOPIFY_INTAKE_VARIANT_STOCK ? 'variant ' . ($s['vare_id'] - SHOPIFY_INTAKE_VARIANT_STOCK) : "item {$s['vare_id']}";
			$alarm('order.shortage', "order $name: $what is short by " . ($s['quantity_milli'] / 1000) . " on lager {$s['lager']}; invoiced anyway");
		}
		if ($allocation['shortages'] !== array()) {
			$tags[] = SHOPIFY_TAG_SHORTAGE;
		}
		foreach ($allocation['tags'] as $t) {
			$tags[] = $t;
		}
		foreach ($lines as $i => $line) {
			$a = $allocation['lines'][$line['key']] ?? null;
			if ($a === null) {
				continue;
			}
			$lines[$i]['lager'] = $a['lager'];
			$lines[$i]['split'] = $a['split'];
			$lines[$i]['segments'] = $a['segments'];
			$lines[$i]['lager_plan'] = $a['lager_plan'];
			$lines[$i]['tags'] = $a['tags'];
		}
		$tags = array_values(array_unique($tags));
		foreach ($lines as $line) {
			if (empty($line['set']) && (int)round(array_sum(array_column(shopify_intake_line_rows($line), 'quantity'))) !== (int)$line['quantity']) {
				$error('order.allocation', "order $name: the lagre of line {$line['key']} do not add up to its {$line['quantity']} units");
			}
		}
		$invoiced = shopify_intake_invoice_total($lines, $freight, $ctx['items'] ?? array());
		if (abs($invoiced - (float)$order['total']) > 0.005) {
			$alarm('order.rounding', "order $name: the lines come to " . number_format($invoiced, 2, '.', '') . ", Shopify's total is {$order['total']}; the difference is put on the VAT");
		}
	}

	$vatRates = array_merge(array_column($lines, 'vat_rate'), $freight !== null ? array($freight['vat_rate'] ?? 0) : array());
	$header = array(
		'order_gid' => $gid,
		'order_name' => $name,
		'afd' => isset($shop['afd']) ? (int)$shop['afd'] : 0,
		'sprog' => (string)($ctx['brand_sprog'] ?? ''),
		'betalingsbet' => SHOPIFY_PAYMENT_FALLBACK,
		'customer_gid' => $order['customer_gid'],
		'email' => $order['email'],
		'phone' => $order['phone'],
		'note' => $order['note'],
		'created_at' => $order['created_at'],
		'posting_date' => $today,
		'momssats' => $vatRates === array() ? 0.0 : (float)max($vatRates),
		'taxes_included' => (bool)$order['taxes_included'],
		'total' => (string)$order['total'],
		'total_tax' => isset($order['total_tax']) ? (string)$order['total_tax'] : null,
		'currency' => (string)$order['currency'],
		'shipping_address' => $order['shipping_address'],
		'billing_address' => $order['billing_address'],
		'shopify_tags' => $order['tags'],
	) + $felt;

	return array('ok' => $errors === array(), 'errors' => $errors, 'alarms' => $alarms, 'header' => $header, 'lines' => $lines,
		'freight' => $freight, 'allocation' => $allocation, 'tags' => $tags);
}

/**
 * What shopify_validate_paid_order() needs from the tenant for one order: the items behind the
 * lines (by Shopify variant link, then by SKU as varenr or barcode), the set components and the
 * freight items, each with what makes it unusable; stock per lager (the line's variant for a
 * variant item); the shop's lagre with their locations, the split-set lager; the freight and
 * payment maps; the web kasse's POS accounts; the Kreditkort account; the intake date, the
 * fiscal year and whether the date's VAT period is closed.
 *
 * @param array<string, mixed> $shop the shopify_shops row
 * @param array<string, mixed> $order as shopify_order_from_graphql() shapes it
 * @return array<string, mixed>
 */
function shopify_intake_context(array $shop, array $order): array {
	global $regnaar, $baseCurrency;
	$shopId = (int)$shop['id'];
	$year = (int)$regnaar;
	$afd = (int)($shop['afd'] ?? 0);

	$byVariant = array();
	$bySku = array();
	foreach ($order['lines'] as $l) {
		if ($l['variant_gid'] !== null && !isset($byVariant[$l['variant_gid']])) {
			$link = shopify_product_link_for_variant($shopId, $l['variant_gid']);
			$item = $link === null ? null : shopify_intake_item((int)$link['vare_id'], (int)$link['variant_id'], $year, $afd);
			if ($item !== null) {
				$byVariant[$l['variant_gid']] = $item;
			}
		}
		if ($l['sku'] !== '' && !isset($bySku[$l['sku']])) {
			$item = shopify_intake_item_by_sku($l['sku'], $year, $afd);
			if ($item !== null) {
				$bySku[$l['sku']] = $item;
			}
		}
	}

	$shipping = array();
	foreach ($order['shipping'] as $s) {
		foreach (array($s['title'], $s['code']) as $rate) {
			if ($rate !== '' && !isset($shipping[$rate])) {
				$map = shopify_shipping($shopId, $rate);
				if ($map !== null) {
					$shipping[$rate] = $map;
				}
			}
		}
	}
	$freightVareId = (int)($shop['default_freight_vare_id'] ?? 0);

	// components and freight items, by vare_id
	$items = array();
	$wanted = array();
	foreach (array_merge(array_values($byVariant), array_values($bySku)) as $item) {
		$items[(int)$item['vare_id']] = $items[(int)$item['vare_id']] ?? $item;
		foreach ($item['bom'] as $c) {
			$wanted[(int)$c['vare_id']] = true;
		}
	}
	foreach ($shipping as $map) {
		if ((int)($map['vare_id'] ?? 0) > 0) {
			$wanted[(int)$map['vare_id']] = true;
		}
	}
	if ($freightVareId > 0) {
		$wanted[$freightVareId] = true;
	}
	foreach (array_keys($wanted) as $vareId) {
		if (!isset($items[$vareId])) {
			$item = shopify_intake_item($vareId, 0, $year, $afd);
			if ($item !== null) {
				$items[$vareId] = $item;
			}
		}
	}

	$payments = array();
	foreach ($order['transactions'] as $t) {
		$key = "{$t['gateway']}\x1f{$t['card']}";
		if (!isset($payments[$key])) {
			$type = shopify_payment($shopId, $t['gateway'], $t['card']);
			if ($type !== null) {
				$payments[$key] = $type;
			}
		}
	}

	$account = static fn(string $kontonr): bool => $kontonr !== '' && shopify_db_rows(
		"SELECT 1 FROM kontoplan WHERE kontonr::text = $1 AND regnskabsaar::text = $2 AND kontotype IN ('D', 'S')", array($kontonr, (string)$year)) !== array();
	$div = shopify_db_rows("SELECT box10 FROM grupper WHERE art = 'DIV' AND kodenr::text = '3'");
	$kreditkort = trim((string)($div[0]['box10'] ?? ''));

	$pos = array('kasse_account' => '', 'kasse_account_ok' => false, 'kasse_vat' => '', 'card_accounts' => array(), 'fallback_card_account_ok' => false);
	$kasse = trim((string)($shop['web_kasse'] ?? ''));
	if (ctype_digit($kasse) && (int)$kasse > 0) {
		$pos1 = shopify_db_rows("SELECT box2, box5, box6, box7 FROM grupper WHERE art = 'POS' AND kodenr::text = '1' AND fiscal_year::text = $1", array((string)$year));
		if ($pos1 !== array()) {
			$pos['kasse_account'] = trim(explode(chr(9), (string)$pos1[0]['box2'])[(int)$kasse - 1] ?? '');
			$pos['kasse_vat'] = trim(explode(chr(9), (string)$pos1[0]['box7'])[(int)$kasse - 1] ?? '');
			// as bogfor_nu(): a type named in box5 posts to its box6 account, even an empty one; other types to POS/2 box6
			$types = explode(chr(9), (string)$pos1[0]['box5']);
			$accounts = explode(chr(9), (string)$pos1[0]['box6']);
			foreach ($types as $i => $type) {
				if (trim($type) !== '' && !array_key_exists(strtolower(trim($type)), $pos['card_accounts'])) {
					$pos['card_accounts'][strtolower(trim($type))] = $account(trim($accounts[$i] ?? ''));
				}
			}
		}
		$pos['kasse_account_ok'] = $account($pos['kasse_account']);
		$pos2 = shopify_db_rows("SELECT box6 FROM grupper WHERE art = 'POS' AND kodenr::text = '2' AND fiscal_year::text = $1", array((string)$year));
		$pos['fallback_card_account_ok'] = $account(trim((string)($pos2[0]['box6'] ?? '')));
	}

	$config = shopify_lager_config();
	$gids = array();
	foreach (shopify_db_rows('SELECT lager, location_gid FROM shopify_location_map WHERE shop_id = $1', array($shopId)) as $m) {
		$gids[(int)$m['lager']] = (string)$m['location_gid'];
	}
	$locations = array();
	foreach ($config['lagre'] as $row) {
		$lager = (int)$row['lager'];
		if ($config['virtual_lager'] !== null && $lager === (int)$config['virtual_lager']) {
			continue;
		}
		$locations[] = array('lager' => $lager, 'rolle' => (string)$row['rolle'], 'prioritet' => $row['prioritet'] === null ? null : (int)$row['prioritet'],
			'location_gid' => $gids[$lager] ?? null);
	}
	$assembly = 0;
	try {
		$assembly = shopify_split_set_lager_checked($config['split_set_lager'], shopify_availability_lagre($config));
	} catch (InvalidArgumentException $e) {
		// no sellable lager: the allocation reports it
	}

	// stock in thousandths per stock key and lager: a variant line's own variant, else every row of the item
	$stock = array();
	$vareIds = array_keys($items);
	foreach (array_merge(array_values($byVariant), array_values($bySku)) as $item) {
		$vareIds[] = (int)$item['vare_id'];
	}
	$vareIds = array_values(array_unique($vareIds));
	if ($vareIds !== array()) {
		$rows = shopify_db_rows('SELECT vare_id, lager, COALESCE(variant_id, 0) AS variant_id, beholdning FROM lagerstatus WHERE vare_id = ANY($1::int[])',
			array('{' . implode(',', $vareIds) . '}'));
		foreach ($rows as $r) {
			$milli = (int)round((float)$r['beholdning'] * 1000);
			$lager = (int)$r['lager'];
			$stock[(int)$r['vare_id']][$lager] = ($stock[(int)$r['vare_id']][$lager] ?? 0) + $milli;
			if ((int)$r['variant_id'] > 0) {
				$key = SHOPIFY_INTAKE_VARIANT_STOCK + (int)$r['variant_id'];
				$stock[$key][$lager] = ($stock[$key][$lager] ?? 0) + $milli;
			}
		}
	}

	$today = date('Y-m-d');
	$fiscal = null;
	$ra = shopify_db_rows("SELECT box1, box2, box3, box4 FROM grupper WHERE art = 'RA' AND kodenr::text = $1", array((string)$year));
	if ($ra !== array() && (int)$ra[0]['box2'] > 0) {
		$from = sprintf('%04d-%02d-01', (int)$ra[0]['box2'], (int)$ra[0]['box1']);
		$to = date('Y-m-t', mktime(0, 0, 0, (int)$ra[0]['box3'], 1, (int)$ra[0]['box4']));
		$fiscal = array('from' => $from, 'to' => $to);
	}
	$closed = function_exists('check_periode_luk') ? check_periode_luk($today) : false;
	$brand = shopify_db_rows('SELECT sprog FROM shopify_brands WHERE shop_id = $1 ORDER BY sprog LIMIT 1', array($shopId));

	return array(
		'by_variant' => $byVariant,
		'by_sku' => $bySku,
		'items' => $items,
		'shipping' => $shipping,
		'freight_default_vare_id' => $freightVareId,
		'payments' => $payments,
		'web_kasse' => $kasse,
		'pos' => $pos,
		'kreditkort_account' => $kreditkort,
		'kreditkort_ok' => $account($kreditkort),
		'locations' => $locations,
		'assembly_lager' => $assembly,
		'stock_milli' => $stock,
		'today' => $today,
		'fiscal' => $fiscal,
		'period_closed' => $closed === false ? null : (string)$closed,
		'currency' => (string)($baseCurrency ?: 'DKK'),
		'brand_sprog' => (string)($brand[0]['sprog'] ?? ''),
	);
}

/**
 * One item for the validator: what makes it unusable for a line written headless (problem: no
 * item group of the year, no purchase or sales account, a sales account missing from the chart
 * or without a VAT code with a VAT account; null when it can be used), whether it is
 * serial-numbered, variant-controlled (with the variant's barcode) or a set, its VAT rate, the
 * key its stock is allocated under (the variant's for a variant), and its BOM occurrences as the
 * allocation wants them. The VAT lookup is opret_ordrelinje()'s: the
 * department's VAT group when the setting maxDepVatRate_<afd> exists, else the sales account's.
 *
 * @return array{vare_id: int, varenr: string, variant_id: int, variant_barcode: string, written_as: string, written_as_other: bool, batch: bool,
 *   stock_key: int, gruppe: int, problem: string|null, vat_rate: float, salgspris: float,
 *   serial: bool, variant_item: bool, set: bool, bom: list<array{bom_id: int, vare_id: int, quantity_milli: int}>}|null
 */
function shopify_intake_item(int $vareId, int $variantId, int $year, int $afd = 0): ?array {
	$rows = shopify_db_rows(
		"SELECT v.id, v.varenr, v.gruppe, v.serienr, v.samlevare, v.salgspris, g.id AS group_id, g.box3, g.box4, g.box7, g.box9,
			EXISTS (SELECT 1 FROM variant_varer vv WHERE vv.vare_id = v.id) AS variant_item
		 FROM varer v LEFT JOIN grupper g ON g.art = 'VG' AND g.kodenr::text = v.gruppe::text AND g.fiscal_year::text = $2
		 WHERE v.id = $1",
		array($vareId, (string)$year)
	);
	if ($rows === array()) {
		return null;
	}
	$r = $rows[0];
	$problem = null;
	$rate = 0.0;
	$sales = trim((string)($r['box4'] ?? ''));
	if ($r['group_id'] === null) {
		$problem = "has no item group {$r['gruppe']} in the fiscal year";
	} elseif (trim((string)$r['box3']) === '' || $sales === '') {
		$problem = "has item group {$r['gruppe']} without a purchase or sales account";
	} else {
		$konto = shopify_db_rows('SELECT moms FROM kontoplan WHERE kontonr::text = $1 AND regnskabsaar::text = $2', array($sales, (string)$year));
		if ($konto === array()) {
			$problem = "has sales account $sales, which is not in the chart of accounts for the fiscal year";
		} elseif (trim((string)$r['box7']) !== 'on') {
			$depVat = $afd > 0 && shopify_db_rows("SELECT 1 FROM settings WHERE var_name = $1", array("maxDepVatRate_$afd")) !== array();
			$code = $depVat ? $afd : (int)substr(trim((string)$konto[0]['moms']), 1);
			$sm = $code > 0 ? shopify_db_rows("SELECT box1, box2 FROM grupper WHERE art = 'SM' AND kodenr::text = $1 AND fiscal_year::text = $2", array((string)$code, (string)$year)) : array();
			if ($sm === array() || trim((string)$sm[0]['box1']) === '') {
				$problem = $depVat ? "is sold by department $afd, whose VAT group has no VAT account" : "has sales account $sales without a VAT code with a VAT account";
			} elseif (is_numeric(trim((string)$sm[0]['box1'])) && (int)trim((string)$sm[0]['box1']) === 1) {
				// bogfor_nu() stops at a VAT posting on account 1 (a leftover exit), after the delivery; the line keeps the
				// VAT account as a number, so 01 is account 1 too
				$problem = "has VAT group $code with VAT account 1, which the posting cannot use";
			} elseif ((float)$sm[0]['box2'] <= 0) {
				// opret_ordrelinje() takes the rate only when it is set, else the order's
				$problem = "has VAT group $code without a rate in a group that is not VAT-free";
			} else {
				$rate = (float)$sm[0]['box2'];
			}
		}
	}
	$barcode = '';
	if ($variantId > 0) {
		$v = shopify_db_rows('SELECT variant_stregkode FROM variant_varer WHERE id = $1 AND vare_id = $2', array($variantId, $vareId));
		$barcode = trim((string)($v[0]['variant_stregkode'] ?? ''));
	}
	// opret_ordrelinje() looks the written number up as a variant barcode first (a 12-digit number also with a leading 0)
	$writtenAs = $variantId > 0 ? $barcode : (string)$r['varenr'];
	$forms = array(strtoupper($writtenAs));
	if (strlen($writtenAs) === 12 && ctype_digit($writtenAs)) {
		$forms[] = '0' . $writtenAs;
	}
	$other = shopify_db_rows('SELECT 1 FROM variant_varer WHERE upper(variant_stregkode) = ANY($1::text[]) AND id <> $2 LIMIT 1',
		array('{' . implode(',', array_map(fn($f) => '"' . str_replace(array('\\', '"'), array('\\\\', '\\"'), $f) . '"', $forms)) . '}', $variantId));
	$bom = array();
	foreach (shopify_db_rows('SELECT id, vare_id, antal FROM styklister WHERE indgaar_i = $1 ORDER BY posnr, id', array($vareId)) as $c) {
		$bom[] = array('bom_id' => (int)$c['id'], 'vare_id' => (int)$c['vare_id'], 'quantity_milli' => (int)round((float)$c['antal'] * 1000));
	}
	return array(
		'vare_id' => (int)$r['id'],
		'varenr' => (string)$r['varenr'],
		'variant_id' => $variantId,
		'variant_barcode' => $barcode,
		'written_as' => $writtenAs,
		'written_as_other' => $writtenAs !== '' && $other !== array(),
		'batch' => trim((string)($r['box9'] ?? '')) === 'on',
		'stock_key' => $variantId > 0 ? SHOPIFY_INTAKE_VARIANT_STOCK + $variantId : (int)$r['id'],
		'gruppe' => (int)$r['gruppe'],
		'problem' => $problem,
		'vat_rate' => $rate,
		'salgspris' => round((float)$r['salgspris'], 2),
		'serial' => trim((string)($r['serienr'] ?? '')) === 'on',
		'variant_item' => in_array($r['variant_item'], array(true, 't', '1', 1), true),
		'set' => trim((string)($r['samlevare'] ?? '')) === 'on' && $bom !== array(),
		'bom' => $bom,
	);
}

/**
 * The item a SKU names: its varenr or barcode, or a variant's barcode (then with the variant).
 * sku_ambiguous is true when the SKU names more than one item or variant (case-insensitive for
 * a variant, as opret_ordrelinje() matches it): the first is returned, and must not be used.
 */
function shopify_intake_item_by_sku(string $sku, int $year, int $afd = 0): ?array {
	$rows = shopify_db_rows('SELECT id FROM varer WHERE varenr = $1 OR stregkode = $1 ORDER BY (varenr = $1) DESC, id LIMIT 2', array($sku));
	$variants = shopify_db_rows('SELECT id, vare_id FROM variant_varer WHERE upper(variant_stregkode) = upper($1) ORDER BY id LIMIT 2', array($sku));
	$ambiguous = count($rows) + count($variants) > 1;
	$item = null;
	if ($rows !== array()) {
		$item = shopify_intake_item((int)$rows[0]['id'], 0, $year, $afd);
	} elseif ($variants !== array()) {
		$item = shopify_intake_item((int)$variants[0]['vare_id'], (int)$variants[0]['id'], $year, $afd);
	}
	if ($item !== null) {
		$item['sku_ambiguous'] = $ambiguous;
	}
	return $item;
}

/**
 * The rows a validated line is written as (pure). A plain line, which S09b writes exactly so:
 * one row per lager its allocation draws from, at unit_net, the last unit of the last lager a row
 * of its own at last_unit_net when that differs. The allocation counts in thousandths, so a
 * lager's whole units are taken from the running total (the units always add up to the line's).
 * A set line, which opret_saet() writes from line_net and the lager plan: one row per BOM
 * occurrence and lager at the component's list price less the set discount, and the set line
 * with what that leaves, which carries no VAT (momsupdat() skips it). $items gives the
 * components' list prices.
 *
 * @param array<string, mixed> $line a line of shopify_validate_paid_order()
 * @param array<int, array<string, mixed>> $items ctx items, by vare_id
 * @return list<array{lager: int, quantity: float, net: float, vat: bool}>
 */
function shopify_intake_line_rows(array $line, array $items = array()): array {
	if (!empty($line['set'])) {
		return shopify_intake_set_rows($line, $items);
	}
	$segments = array();
	$done = 0;
	$milli = 0;
	foreach ($line['segments'] ?? array() as $seg) {
		$milli += (int)$seg['quantity_milli'];
		$units = intdiv($milli, 1000) - $done;
		$done += $units;
		$segments[] = array((int)$seg['lager'], $units);
	}
	if ($segments === array()) {
		$segments[] = array((int)($line['lager'] ?? 0), (int)$line['quantity']);
	} else {
		$segments[count($segments) - 1][1] += (int)$line['quantity'] - $done;
	}
	$rows = array();
	$row = static fn(int $lager, int $quantity, float $price): array => array('lager' => $lager, 'quantity' => (float)$quantity,
		'net' => round($quantity * $price, 3), 'vat' => true);
	$last = count($segments) - 1;
	foreach ($segments as $i => [$lager, $quantity]) {
		if ($i === $last && (float)$line['last_unit_net'] !== (float)$line['unit_net'] && $quantity > 0) {
			if ($quantity > 1) {
				$rows[] = $row($lager, $quantity - 1, (float)$line['unit_net']);
			}
			$rows[] = $row($lager, 1, (float)$line['last_unit_net']);
		} elseif ($quantity > 0) {
			$rows[] = $row($lager, $quantity, (float)$line['unit_net']);
		}
	}
	return $rows;
}

/**
 * The rows opret_saet() writes for a set line with its lager plan, by its arithmetic: each
 * component at its list price to two decimals, one discount on all of them (to three decimals)
 * that brings their list total to the set price, each occurrence and lager a row of its own,
 * and the set line taking what the rows leave.
 *
 * @return list<array{lager: int, quantity: float, net: float, vat: bool}>
 */
function shopify_intake_set_rows(array $line, array $items): array {
	$sets = (int)$line['quantity'];
	$price = array();
	$normal = 0.0;
	foreach ($line['bom'] ?? array() as $c) {
		$price[(int)$c['bom_id']] = round((float)($items[(int)$c['vare_id']]['salgspris'] ?? 0), 2);
		$normal += round($c['quantity_milli'] / 1000 * $sets * $price[(int)$c['bom_id']], 3);
	}
	$setPrice = (float)$line['line_net'];
	if ($normal == 0) {
		return array(array('lager' => (int)($line['lager'] ?? 0), 'quantity' => 1.0, 'net' => round($setPrice, 3), 'vat' => false));
	}
	$discount = round(($normal - $setPrice) * 100 / $normal, 3);
	$rows = array();
	$check = 0.0;
	foreach ($line['lager_plan'] ?? array() as $draw) {
		$p = $price[(int)$draw['bom_id']] ?? 0.0;
		$quantity = $draw['quantity_milli'] / 1000;
		$net = round(($p - $p / 100 * $discount) * $quantity, 3);
		$check += $net;
		$rows[] = array('lager' => (int)$draw['lager'], 'quantity' => (float)$quantity, 'net' => $net, 'vat' => true);
	}
	$rows[] = array('lager' => (int)($line['lager'] ?? 0), 'quantity' => 1.0, 'net' => round($setPrice - $check, 3), 'vat' => false);
	return $rows;
}

/**
 * What the invoice of these lines and freight comes to, summed as momsupdat() sums an order:
 * each row's net to three decimals (the rows of shopify_intake_line_rows()); the VAT on the sum
 * when every row has one rate and there is no set, else per row to two decimals, where a set's
 * own line carries none (pure).
 *
 * @param list<array<string, mixed>> $lines lines of shopify_validate_paid_order()
 * @param array<string, mixed>|null $freight with net and vat_rate
 * @param array<int, array<string, mixed>> $items ctx items, by vare_id
 */
function shopify_intake_invoice_total(array $lines, ?array $freight, array $items = array()): float {
	$parts = array();
	$set = false;
	foreach ($lines as $l) {
		$set = $set || !empty($l['set']);
		foreach (shopify_intake_line_rows($l, $items) as $row) {
			$parts[] = array($row['net'], $row['vat'] ? (float)$l['vat_rate'] : 0.0);
		}
	}
	if ($freight !== null) {
		$parts[] = array(round((float)$freight['net'], 3), (float)$freight['vat_rate']);
	}
	$sum = 0.0;
	$vat = 0.0;
	$rates = array_unique(array_map(fn($p) => (string)$p[1], $parts));
	foreach ($parts as [$net, $rate]) {
		$sum += $net;
		$vat += round($net * $rate / 100, 2);
	}
	if (count($rates) === 1 && !$set && $parts !== array()) {
		$vat = round($sum * (float)$parts[0][1] / 100, 2);
	}
	return round(round($sum, 2) + $vat, 2);
}

/**
 * Opens the K25 errors and the alarms of a validation on the error list, in the caller's
 * transaction when there is one. Errors are severity error, alarms warning.
 *
 * @param array{errors: list<array{kind: string, ref: string, message: string}>, alarms: list<array{kind: string, ref: string, message: string}>} $validated
 * @param array<string, mixed> $correlation job_id, event_row_id, order_gid, ...
 */
function shopify_intake_report(int $shopId, array $validated, array $correlation = array()): void {
	foreach ($validated['errors'] as $e) {
		shopify_error_open($shopId, $e['kind'], $e['ref'], $e['message'], 'error', $correlation);
	}
	foreach ($validated['alarms'] as $a) {
		shopify_error_open($shopId, $a['kind'], $a['ref'], $a['message'], 'warning', $correlation);
	}
}
