<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/orderImport.php --- ver 5.1.0 --- 2026.11.12
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
// A paid web order invoiced in Saldi, once (WP09-2, K15, K20, K26, NF-1): job order.import
// validates it (WP09-1), then runs four transactions, each a checkpoint on the order link:
// the link is reserved (validated), the order and its lines are created (created), it is
// delivered (delivery_committed) and it is posted (invoiced). A run that stops anywhere resumes
// from the link's state and the order's status, so a replay gives one order and one invoice.
// No Shopify call is made inside a transaction. The transaction that sets invoiced enqueues the
// first write-back stage (order.tags), which stays queued until WP10 registers its handler.
// An order cancelled in Shopify before it is invoiced is neither delivered nor posted any more.
//
// 20261103 CL/SS SD-743: created (WP09-2).
// 20261104 CL/SS SD-743 review: an order whose link holds no order past validation (cancelled before the import) is skipped.
// 20261104 CL/SS SD-743: an order cancelled in Shopify (the link's cancelled_at, SD-801) stops before its delivery or posting.
// 20261104 CL/SS SD-743 review: a set must be written with the components it was validated with.
// 20261104 CL/SS SD-743 review 2: the account check includes OreDif, which bogfor_nu() posts an øre difference of the posting on.
// 20261104 CL/SS SD-743 review 2: the debtor group must have the VAT group that bogfor() reads for an order with VAT.
// 20261112 CL/SS SD-804: an echo of our own store order queues the sale still waiting for its answer.

require_once __DIR__ . '/orderIntake.php';
require_once __DIR__ . '/customers.php';
require_once __DIR__ . '/orderLines.php';
require_once __DIR__ . '/../legacyBridge.php';
require_once __DIR__ . '/../queue.php';
require_once __DIR__ . '/storeOrders.php';

/** Checkpoints of a web order link, in order. */
const SHOPIFY_IMPORT_STATES = array('validated', 'created', 'delivery_committed', 'invoiced');
/** A written invoice this far from Shopify's total is refused, and its VAT further than this (plus per-line rounding) from totalTaxSet. */
const SHOPIFY_IMPORT_TOTAL_TOLERANCE = 0.05;
/** The write-back stage the invoiced order hands off to (WP10). */
const SHOPIFY_IMPORT_WRITEBACK_JOB = 'order.tags';

/** A deterministic refusal of the order: it is reported on the error list and not retried. */
final class ShopifyImportError extends DomainException
{
}

/** The order was cancelled in Shopify before it was invoiced: the import stops where it is. */
final class ShopifyImportCancelled extends RuntimeException
{
}

/**
 * A web link that holds no order and is past validation (an order cancelled before it was
 * imported) is never imported: logged, and the job is done.
 */
function shopify_import_not_importable(string $state, array $correlation): array {
	shopify_import_log('not_importable', $correlation + array('state' => $state));
	return array('outcome' => 'done', 'result' => array('skipped' => "the order's link is in state $state without an order"));
}

/**
 * Job order.import (prio 10, deduped on the order): imports the paid order of the payload, or
 * resumes it. Done also when the order is refused (its errors are on the error list) or not
 * imported (an echo, a shop whose orders flow is off, or a slot whose legacy intake is open);
 * a run that holds no lock on the order yields.
 *
 * @param array<string, mixed> $job as claimed; payload order_gid
 * @param array{fetch?: callable(array, string): array, crash?: callable(string): void} $services test seams:
 *   fetch replaces shopify_fetch_paid_order(), crash is called at each stage
 * @return array{outcome: string, run_after?: int, result?: array<string, mixed>}
 */
function shopify_import_paid_order(array $job, array $services = array()): array {
	$shopId = (int)$job['shop_id'];
	$gid = shopify_gid((string)($job['payload']['order_gid'] ?? ''), 'Order');
	$shop = shopify_db_rows('SELECT * FROM shopify_shops WHERE id = $1', array($shopId))[0] ?? null;
	if ($shop === null || !in_array($shop['orders_enabled'], array(true, 't', '1', 1), true)) {
		$link = $shop === null ? null : shopify_order_link($shopId, $gid);
		if ($link !== null && $link['direction'] === 'web' && $link['state'] !== 'invoiced' && $link['cancelled_at'] === null && ($link['ordre_id'] !== null || $link['state'] === 'validated')) {
			shopify_error_open($shopId, 'order.invoice', $gid, "order {$link['order_name']} stopped at {$link['state']}: the orders flow of the shop is off; it is resumed by a replay once the flow is on", 'error');
		}
		return array('outcome' => 'done', 'result' => array('skipped' => 'orders flow off'));
	}
	$correlation = array('job_id' => $job['id'] ?? null, 'order_gid' => $gid);
	$lock = "shopify_import:$shopId:$gid";
	// a session lock, held across the import's transactions: a second worker on the order yields
	if (shopify_db_rows('SELECT pg_try_advisory_lock(hashtext($1)) AS ok', array($lock))[0]['ok'] !== 't') {
		return array('outcome' => 'yield', 'run_after' => 30);
	}
	$crash = $services['crash'] ?? static function (string $stage): void {
	};
	try {
		shopify_import_require_legacy();
		$link = shopify_order_link($shopId, $gid);
		if ($link !== null && $link['direction'] !== 'web') {
			return array('outcome' => 'done', 'result' => array('skipped' => 'a store order'));
		}
		if ($link !== null && $link['state'] !== 'validated') {
			if ($link['ordre_id'] === null) {
				return shopify_import_not_importable($link['state'], $correlation);
			}
			return shopify_import_finish((int)$link['id'], $crash, $correlation);
		}
		$fetch = $services['fetch'] ?? static fn(array $s, string $g): array => shopify_fetch_paid_order($s, $g);
		try {
			$order = $fetch($shop, $gid);
		} catch (ShopifyOrderReadError $e) {
			if ($e->retryable) {
				throw $e;
			}
			shopify_error_open($shopId, 'order.read', $gid, $e->getMessage(), 'error', $correlation);
			return array('outcome' => 'done', 'result' => array('refused' => 'read'));
		}
		if (shopify_is_echo($order, (string)$GLOBALS['db'])) {
			shopify_import_log('echo', $correlation + array('order_name' => $order['name']));
			shopify_store_orders_echo($shopId, $order, (string)$GLOBALS['db']);
			return array('outcome' => 'done', 'result' => array('skipped' => 'echo'));
		}
		if (!shopify_intake_admitted($shop)) {
			return array('outcome' => 'done', 'result' => array('skipped' => 'legacy intake open'));
		}
		$validated = shopify_validate_paid_order($shop, $order, shopify_intake_context($shop, $order));
		$validated['header']['betalings_id'] = shopify_import_payment_id($order['transactions']);
		if (!$validated['ok']) {
			shopify_db_transaction(fn() => shopify_intake_report($shopId, $validated, $correlation));
			return array('outcome' => 'done', 'result' => array('refused' => array_column($validated['errors'], 'kind')));
		}
		$reserved = shopify_db_transaction(fn() => shopify_reserve_order_link($shopId, $gid, 'web', array('order_name' => $order['name'], 'state' => 'validated')));
		if ($reserved['state'] !== 'validated') {
			return shopify_import_not_importable($reserved['state'], $correlation);
		}
		$linkId = (int)$reserved['id'];
		$crash('reserved');
		try {
			shopify_create_web_order($linkId, $validated, $crash, $correlation);
		} catch (ShopifyImportError | ShopifyPreflightError $e) {
			shopify_error_open($shopId, 'order.create', $gid, "order {$order['name']}: " . $e->getMessage(), 'error', $correlation);
			return array('outcome' => 'done', 'result' => array('refused' => array('order.create')));
		}
		$crash('created');
		return shopify_import_finish($linkId, $crash, $correlation);
	} finally {
		shopify_db_rows('SELECT pg_advisory_unlock(hashtext($1))', array($lock));
	}
}

/**
 * shopify_resume_web_invoice() for the job: a refusal of the delivery or posting is an error on
 * the list (order.invoice) and the job is done; the order is resumed by a replay once it is fixed.
 * An order cancelled in Shopify before it was invoiced is not resumed: it is logged, an open
 * order.invoice is resolved, and the job is done. Its "do not ship or invoice" warning is SD-801's.
 */
function shopify_import_finish(int $linkId, callable $crash, array $correlation): array {
	$link = shopify_db_rows('SELECT shop_id, order_gid, order_name FROM shopify_order_links WHERE id = $1', array($linkId))[0];
	try {
		$result = shopify_resume_web_invoice($linkId, $crash);
	} catch (ShopifyImportCancelled $e) {
		shopify_import_log('cancelled', $correlation + array('link_id' => $linkId));
		shopify_error_resolve_key((int)$link['shop_id'], 'order.invoice', (string)$link['order_gid']);
		return array('outcome' => 'done', 'result' => array('skipped' => 'cancelled in Shopify before it was invoiced'));
	} catch (ShopifyImportError | ShopifyPreflightError $e) {
		shopify_error_open((int)$link['shop_id'], 'order.invoice', (string)$link['order_gid'], "order {$link['order_name']}: " . $e->getMessage(), 'error', $correlation);
		return array('outcome' => 'done', 'result' => array('refused' => array('order.invoice')));
	}
	shopify_error_resolve_key((int)$link['shop_id'], 'order.invoice', (string)$link['order_gid']);
	return array('outcome' => 'done', 'result' => $result);
}

/** Loads the legacy order functions; their relative paths need temp/<db> below the working directory's parent. */
function shopify_import_require_legacy(): void {
	if (!function_exists('get_next_order_number')) {
		require_once __DIR__ . '/../../std_func.php';
	}
	if (!function_exists('levering')) {
		require_once __DIR__ . '/../../ordrefunc.php';
	}
	$dir = '../temp/' . $GLOBALS['db'];
	if (!is_dir($dir)) {
		@mkdir($dir, 0777, true);
	}
}

/** Job handler of order.import for the worker. */
function shopify_order_import_job(array $job, float $deadline, callable $heartbeat): array {
	return shopify_import_paid_order($job);
}

/**
 * Creates the Saldi order of a validated paid order on its reserved link, in one transaction:
 * the debtor (WP08), the header, the lines at their net prices, the freight, the order's sum
 * and VAT, and a move barrier per item (WP06). The link becomes created with the order id. A
 * link that already has its order is left as it is.
 *
 * @param array<string, mixed> $validated shopify_validate_paid_order() with ok true
 * @return int ordrer.id
 * @throws ShopifyImportError when a line is refused or the invoice is too far from Shopify's total
 */
function shopify_create_web_order(int $linkId, array $validated, ?callable $crash = null, array $correlation = array()): int {
	$crash = $crash ?? static function (string $stage): void {
	};
	$h = $validated['header'];
	return shopify_db_transaction(function () use ($linkId, $validated, $h, $crash, $correlation): int {
		$link = shopify_db_rows('SELECT * FROM shopify_order_links WHERE id = $1 FOR UPDATE', array($linkId))[0] ?? null;
		if ($link === null) {
			throw new LogicException("order link $linkId does not exist");
		}
		if ($link['ordre_id'] !== null) {
			return (int)$link['ordre_id'];
		}
		$shopId = (int)$link['shop_id'];
		$kontoId = shopify_import_debtor($shopId, $h);
		$ordreId = shopify_import_header($kontoId, $h);
		shopify_with_legacy_context(array('momssats' => $h['momssats'], 'afd' => $h['afd']), function () use ($ordreId, $validated) {
			shopify_import_lines($ordreId, $validated);
		});
		$totals = shopify_import_totals($ordreId);
		$diff = round($totals['sum'] + $totals['moms'] - (float)$h['total'], 2);
		// below the tolerance, as bogfor_nu() accepts a header that far from its lines (forkontrolPosteringsbalance())
		if (abs($diff) >= SHOPIFY_IMPORT_TOTAL_TOLERANCE) {
			throw new ShopifyImportError('the written invoice comes to ' . number_format($totals['sum'] + $totals['moms'], 2, '.', '') . ", Shopify's total is {$h['total']}");
		}
		// the rounding goes on the VAT, as bogfor() does for a till sale: else bogfor_nu() posts the difference
		// between the invoice and the amount paid on the kasse's cash account
		$totals['moms'] = round((float)$h['total'] - $totals['sum'], 2);
		// Shopify rounds the VAT per line, Saldi on the sum: up to half an øre a line apart
		if ($h['total_tax'] !== null && abs($totals['moms'] - (float)$h['total_tax']) > SHOPIFY_IMPORT_TOTAL_TOLERANCE + 0.005 * $totals['rows']) {
			throw new ShopifyImportError('the written VAT is ' . number_format($totals['moms'], 2, '.', '') . ", Shopify charged {$h['total_tax']}");
		}
		shopify_db_rows('UPDATE ordrer SET sum = $1, moms = $2 WHERE id = $3', array($totals['sum'], $totals['moms'], $ordreId));
		shopify_import_check_accounts($ordreId);
		shopify_barrier_open($shopId, $ordreId, shopify_import_barrier_items($validated));
		// a replay of a refused order: what refused it is resolved
		shopify_db_rows("UPDATE shopify_errors SET resolved_at = now() WHERE shop_id = $1 AND ref = $2 AND severity = 'error' AND kind LIKE 'order.%' AND resolved_at IS NULL",
			array($shopId, (string)$link['order_gid']));
		shopify_advance_order_link($linkId, 'created', array(
			'ordre_id' => $ordreId,
			'ship_lager' => $validated['freight']['pickup_lager'] ?? null,
			'flags' => array('intake' => array('tags' => $validated['tags'], 'total_diff' => $diff)),
		));
		shopify_intake_report($shopId, array('errors' => array(), 'alarms' => $validated['alarms']), $correlation + array('ordre_id' => $ordreId));
		$crash('creating');
		return $ordreId;
	});
}

/**
 * Takes a web order from its link's checkpoint to invoiced: delivery, posting and, when the
 * posting left the order at status 3, the post-only step. The order's status decides over the
 * link's state, so an order posted meanwhile is not posted again. The delivery and the posting
 * each check the link's cancelled_at under its row lock, which the cancellation (SD-801) takes
 * too: either the import sees the cancellation, or the cancellation sees the invoiced order.
 *
 * @return array{link_id: int, ordre_id: int, state: string, status: int}
 * @throws ShopifyImportError when the order is gone or the posting refuses it
 * @throws ShopifyImportCancelled when the order was cancelled in Shopify before it was invoiced
 */
function shopify_resume_web_invoice(int $linkId, ?callable $crash = null): array {
	$crash = $crash ?? static function (string $stage): void {
	};
	$link = shopify_db_rows('SELECT * FROM shopify_order_links WHERE id = $1', array($linkId))[0] ?? null;
	if ($link === null || $link['ordre_id'] === null) {
		throw new LogicException("order link $linkId has no order to resume");
	}
	$ordreId = (int)$link['ordre_id'];
	$status = shopify_import_status($ordreId);
	if ($link['state'] === 'invoiced') {
		return array('link_id' => $linkId, 'ordre_id' => $ordreId, 'state' => 'invoiced', 'status' => $status);
	}
	if ($status < 3 && $link['state'] === 'created') {
		shopify_db_transaction(function () use ($linkId, $ordreId, $crash) {
			shopify_db_rows('SELECT id FROM ordrer WHERE id = $1 FOR UPDATE', array($ordreId));
			shopify_import_check_not_cancelled($linkId);
			shopify_preflight_delivery($ordreId);
			$answer = shopify_import_legacy(fn() => levering($ordreId, 'on', null, true));
			if ($answer !== 'OK') {
				throw new ShopifyImportError("levering() answered: $answer");
			}
			shopify_import_check_delivered($ordreId);
			shopify_advance_order_link($linkId, 'delivery_committed');
			$crash('delivering');
		});
		$crash('delivered');
	}
	if (shopify_import_status($ordreId) < 3) {
		shopify_db_transaction(function () use ($linkId, $ordreId, $crash) {
			shopify_db_rows('SELECT id FROM ordrer WHERE id = $1 FOR UPDATE', array($ordreId));
			shopify_import_check_not_cancelled($linkId);
			shopify_import_check_accounts($ordreId);
			unset($_SESSION['payment_id']);
			// 'on', as api/rest_api.php passes it: bogfor() hands the flag on to bogfor_nu() as its source, where
			// true == 'Dagsafslutning' holds and the order would be posted as a day-end cash sale without its debtor
			$answer = shopify_import_legacy(fn() => bogfor($ordreId, 'on', false, true));
			if ($answer !== 'OK') {
				throw new ShopifyImportError("bogfor() answered: $answer");
			}
			shopify_import_check_no_open_post($ordreId);
			$crash('posting');
		});
		$crash('posted');
	}
	if (shopify_import_status($ordreId) === 3) {
		shopify_db_transaction(function () use ($ordreId, $crash) {
			if ((int)shopify_db_rows('SELECT status FROM ordrer WHERE id = $1 FOR UPDATE', array($ordreId))[0]['status'] === 3) {
				shopify_import_check_accounts($ordreId);
				$answer = shopify_import_legacy(fn() => bogfor_nu($ordreId, 'webservice'));
				if ($answer !== 'OK') {
					throw new ShopifyImportError("bogfor_nu() answered: $answer");
				}
				shopify_import_check_no_open_post($ordreId);
			}
			$crash('posting_only');
		});
	}
	$status = shopify_import_status($ordreId);
	if ($status !== 4) {
		throw new ShopifyImportError("the order is at status $status after posting, not 4");
	}
	// also when the order was posted in Saldi meanwhile
	shopify_db_transaction(fn() => shopify_import_invoiced($linkId));
	shopify_error_resolve_key((int)$link['shop_id'], 'order.create', (string)$link['order_gid']);
	return array('link_id' => $linkId, 'ordre_id' => $ordreId, 'state' => 'invoiced', 'status' => $status);
}

/**
 * Throws ShopifyImportCancelled when the link's order was cancelled in Shopify, in the caller's
 * transaction and under the link's row lock, so a cancellation recorded meanwhile waits for it.
 */
function shopify_import_check_not_cancelled(int $linkId): void {
	$row = shopify_db_rows('SELECT cancelled_at FROM shopify_order_links WHERE id = $1 FOR UPDATE', array($linkId))[0] ?? null;
	if ($row !== null && $row['cancelled_at'] !== null) {
		throw new ShopifyImportCancelled("order link $linkId was cancelled in Shopify at {$row['cancelled_at']}");
	}
}

/** The link becomes invoiced and the write-back is handed off, in the caller's transaction. */
function shopify_import_invoiced(int $linkId): void {
	$link = shopify_advance_order_link($linkId, 'invoiced');
	$shop = shopify_db_rows('SELECT shop_key FROM shopify_shops WHERE id = $1', array((int)$link['shop_id']))[0];
	shopify_queue_enqueue(SHOPIFY_IMPORT_WRITEBACK_JOB, (int)$link['shop_id'], "{$shop['shop_key']}:{$link['order_gid']}",
		array('link_id' => $linkId, 'ordre_id' => (int)$link['ordre_id'], 'order_gid' => $link['order_gid']),
		SHOPIFY_QUEUE_PRIORITY[SHOPIFY_IMPORT_WRITEBACK_JOB], null,
		array('ordre_id' => (int)$link['ordre_id'], 'order_gid' => $link['order_gid']));
}

/**
 * The accounts the posting of this order will use, checked as they are now (the settings may have
 * changed since the validation): a till sale of the web kasse (felt_5) needs the kasse's account
 * for øredifferencer (POS/2 box9), which bogfor_nu() looks up for any rounding difference and
 * exits on when it is empty; an order posted as Kreditkort needs DIV/3 box10, else it is left as
 * an open post.
 *
 * @throws ShopifyImportError
 */
function shopify_import_check_accounts(int $ordreId): void {
	global $regnaar;
	$o = shopify_db_rows('SELECT felt_1, felt_2, felt_4, felt_5, moms, konto_id FROM ordrer WHERE id = $1', array($ordreId))[0];
	$account = static fn(string $nr): bool => ctype_digit($nr)
		&& shopify_db_rows("SELECT 1 FROM kontoplan WHERE kontonr = $1::numeric AND regnskabsaar::text = $2 AND kontotype IN ('D', 'S')", array($nr, (string)(int)$regnaar)) !== array();
	// bogfor_nu() sums the postings it wrote, each rounded to two decimals; an øre left over goes on an OreDif row's account
	// (whichever row its unordered query meets first, so every row must have one), which one of its branches takes only as a
	// drift account (kontotype D); without it, it refuses the posting after the delivery has gone through
	$drift = static fn(string $nr): bool => ctype_digit($nr)
		&& shopify_db_rows("SELECT 1 FROM kontoplan WHERE kontonr = $1::numeric AND regnskabsaar::text = $2 AND kontotype = 'D'", array($nr, (string)(int)$regnaar)) !== array();
	$oreDif = array_map(static fn(array $r): string => trim((string)$r['box2']), shopify_db_rows("SELECT box2 FROM grupper WHERE art = 'OreDif'"));
	if ($oreDif === array() || in_array(false, array_map($drift, $oreDif), true)) {
		throw new ShopifyImportError('no valid drift account for øredifferencer (Indstillinger > Diverse > Øredifferencer), which a rounding difference of the posting is put on');
	}
	if (trim((string)$o['felt_1']) !== '' && is_numeric($o['felt_2']) && is_numeric($o['felt_4']) && ctype_digit(trim((string)$o['felt_5'])) && (int)$o['felt_5'] > 0) {
		$kasse = (int)$o['felt_5'];
		$box9 = shopify_db_rows("SELECT box9 FROM grupper WHERE art = 'POS' AND kodenr::text = '2' AND fiscal_year::text = $1", array((string)(int)$regnaar))[0]['box9'] ?? '';
		$nr = trim(explode("\t", (string)$box9)[$kasse - 1] ?? '');
		if (!$account($nr)) {
			throw new ShopifyImportError("kasse $kasse has no valid account for øredifferencer (POS settings), which a rounding difference is posted on");
		}
		return;
	}
	$box10 = trim((string)(shopify_db_rows("SELECT box10 FROM grupper WHERE art = 'DIV' AND kodenr::text = '3'")[0]['box10'] ?? ''));
	if (!$account($box10)) {
		throw new ShopifyImportError('no valid account for Kreditkort (Indstillinger > Diverse > Ordrerelaterede valg); the order would be left as an open post');
	}
	// posted with its debtor (a kasse sale above takes the kasse's VAT code, which the validation checks): bogfor_nu() takes the
	// VAT code from the debtor's group, its second character as PHP tests it (S1 is 1, S0 none), and stops an order with VAT without it
	if ((float)$o['moms'] != 0 && (int)$o['konto_id'] > 0) {
		$group = shopify_db_rows("SELECT g.box1 FROM adresser a LEFT JOIN grupper g ON g.art = 'DG' AND g.kodenr::text = a.gruppe::text AND g.fiscal_year::text = $2
			WHERE a.id = $1", array((int)$o['konto_id'], (string)(int)$regnaar))[0] ?? null;
		if ($group === null || !substr(trim((string)$group['box1']), 1, 1)) {
			throw new ShopifyImportError("the debtor group of the order's customer has no VAT group (Debitorgrupper), which an order with VAT is posted with");
		}
	}
}

/** A web order is paid: its posting may leave no open post, else the transaction is rolled back. */
function shopify_import_check_no_open_post(int $ordreId): void {
	if (shopify_db_rows('SELECT 1 FROM openpost o JOIN ordrer r ON r.id = $1 WHERE o.refnr = r.id AND o.konto_id = r.konto_id AND o.faktnr = r.fakturanr', array($ordreId)) !== array()) {
		throw new ShopifyImportError('the posting left an open post; it is rolled back');
	}
}

/** ordrer.status of the order; ShopifyImportError when it was deleted. */
function shopify_import_status(int $ordreId): int {
	$rows = shopify_db_rows('SELECT status FROM ordrer WHERE id = $1', array($ordreId));
	if ($rows === array()) {
		throw new ShopifyImportError("order $ordreId no longer exists in Saldi");
	}
	return (int)$rows[0]['status'];
}

/**
 * Runs a legacy order function with the web order's globals; a write it reports as failed
 * ($db_modify_fejl) is an error, so the transaction is rolled back.
 */
function shopify_import_legacy(callable $op) {
	global $db_modify_fejl;
	$db_modify_fejl = false;
	$answer = shopify_with_legacy_context(array(), $op);
	if ($db_modify_fejl) {
		$db_modify_fejl = false;
		throw new ShopifyDbError('a legacy write failed; the transaction is rolled back');
	}
	return $answer;
}

/**
 * The debtor of the order's Shopify customer (WP08): the linked one, else ensure_customer()
 * with what the order knows of the customer.
 */
function shopify_import_debtor(int $shopId, array $h): int {
	$link = shopify_customer_link($shopId, (string)$h['customer_gid']);
	if ($link !== null && shopify_db_rows("SELECT 1 FROM adresser WHERE id = $1 AND art = 'D'", array((int)$link['adresser_id'])) !== array()) {
		return (int)$link['adresser_id'];
	}
	$a = $h['billing_address'] ?? $h['shipping_address'] ?? array();
	$customer = array(
		'admin_graphql_api_id' => $h['customer_gid'],
		'first_name' => (string)($a['firstName'] ?? ''),
		'last_name' => (string)($a['lastName'] ?? ''),
		'email' => (string)$h['email'],
		'phone' => (string)$h['phone'],
		'default_address' => array('company' => (string)($a['company'] ?? ''), 'address1' => (string)($a['address1'] ?? ''),
			'address2' => (string)($a['address2'] ?? ''), 'zip' => (string)($a['zip'] ?? ''), 'city' => (string)($a['city'] ?? ''),
			'country' => (string)($a['country'] ?? ''), 'country_code' => (string)($a['countryCodeV2'] ?? '')),
	);
	return ensure_customer($shopId, $customer);
}

/**
 * Inserts the order header (status 0, art DO): the order number in this transaction, the
 * customer and delivery address from the order, betalingsbet Kreditkort, the payment fields,
 * kundeordnr the Shopify order name, ref shopify, and the order, delivery and invoice date the
 * posting date.
 */
function shopify_import_header(int $kontoId, array $h): int {
	$ordrenr = (int)get_next_order_number('DO', false);
	$debtor = shopify_db_rows('SELECT kontonr, firmanavn, addr1, addr2, postnr, bynavn, land FROM adresser WHERE id = $1', array($kontoId))[0];
	$bill = $h['billing_address'] ?? array();
	$ship = $h['shipping_address'] ?? $bill;
	$person = static fn(array $a): string => trim(trim((string)($a['firstName'] ?? '')) . ' ' . trim((string)($a['lastName'] ?? '')));
	$name = static fn(array $a): string => trim((string)($a['company'] ?? '')) !== '' ? trim((string)$a['company']) : $person($a);
	$cut = static fn(string $v, int $n): string => mb_substr($v, 0, $n);
	$values = array(
		'ordrenr' => $ordrenr,
		'konto_id' => $kontoId,
		'kontonr' => (string)$debtor['kontonr'],
		'firmanavn' => $bill !== array() ? $name($bill) : (string)($debtor['firmanavn'] ?? ''),
		'addr1' => $bill !== array() ? (string)($bill['address1'] ?? '') : (string)($debtor['addr1'] ?? ''),
		'addr2' => $bill !== array() ? (string)($bill['address2'] ?? '') : (string)($debtor['addr2'] ?? ''),
		'postnr' => $bill !== array() ? (string)($bill['zip'] ?? '') : (string)($debtor['postnr'] ?? ''),
		'bynavn' => $bill !== array() ? (string)($bill['city'] ?? '') : (string)($debtor['bynavn'] ?? ''),
		'land' => $bill !== array() ? (string)($bill['country'] ?? '') : (string)($debtor['land'] ?? ''),
		'kontakt' => $bill !== array() && trim((string)($bill['company'] ?? '')) !== '' ? $person($bill) : '',
		'email' => (string)$h['email'],
		'phone' => $cut((string)$h['phone'], 50),
		'lev_navn' => $ship !== array() ? $name($ship) : '',
		'lev_addr1' => (string)($ship['address1'] ?? ''),
		'lev_addr2' => (string)($ship['address2'] ?? ''),
		'lev_postnr' => (string)($ship['zip'] ?? ''),
		'lev_bynavn' => (string)($ship['city'] ?? ''),
		'lev_land' => $cut((string)($ship['country'] ?? ''), 60),
		'lev_kontakt' => $ship !== array() && trim((string)($ship['company'] ?? '')) !== '' ? $person($ship) : '',
		'art' => 'DO',
		'status' => 0,
		'ordredate' => $h['posting_date'],
		'levdate' => $h['posting_date'],
		'fakturadate' => $h['posting_date'],
		'momssats' => $h['momssats'],
		'valuta' => $h['currency'],
		'valutakurs' => 100,
		'betalingsbet' => $h['betalingsbet'],
		'betalingsdage' => 0,
		'betalings_id' => (string)($h['betalings_id'] ?? ''),
		'afd' => (int)$h['afd'],
		'ref' => 'shopify',
		'hvem' => '',
		'kundeordnr' => (string)$h['order_name'],
		'sprog' => (string)$h['sprog'],
		'notes' => (string)$h['note'],
		'felt_1' => (string)($h['felt_1'] ?? ''),
		'felt_2' => (string)($h['felt_2'] ?? ''),
		'felt_3' => (string)($h['felt_3'] ?? ''),
		'felt_4' => (string)($h['felt_4'] ?? ''),
		'felt_5' => (string)($h['felt_5'] ?? ''),
		'tidspkt' => date('H:i'),
		'sum' => 0,
		'moms' => 0,
	);
	$placeholders = array();
	foreach (array_keys($values) as $i => $column) {
		$placeholders[] = '$' . ($i + 1);
	}
	return (int)shopify_db_rows('INSERT INTO ordrer (' . implode(', ', array_keys($values)) . ') VALUES (' . implode(', ', $placeholders) . ') RETURNING id',
		array_values($values))[0]['id'];
}

/** The numeric id of the order's first successful sale or capture, the order's payment id. */
function shopify_import_payment_id(array $transactions): string {
	foreach ($transactions as $t) {
		if (in_array($t['kind'], SHOPIFY_PAID_KINDS, true) && $t['status'] === 'SUCCESS') {
			return preg_replace('#^gid://shopify/OrderTransaction/#', '', (string)$t['gid']);
		}
	}
	return '';
}

/**
 * Writes the order's lines as S09a validated them: a plain line one row per lager of
 * shopify_intake_line_rows() at its net unit price with $fixed_price, a set through
 * opret_saet() with its lager plan, then the freight. Every plain row must be written as asked,
 * and a set's rows must add up to its net price.
 *
 * @throws ShopifyImportError when a line is refused or written differently
 */
function shopify_import_lines(int $ordreId, array $validated): void {
	$posnr = 0;
	foreach ($validated['lines'] as $line) {
		$varenr = (int)$line['variant_id'] > 0 ? (string)$line['variant_barcode'] : (string)$line['varenr'];
		$before = array_column(shopify_db_rows('SELECT id FROM ordrelinjer WHERE ordre_id = $1', array($ordreId)), 'id');
		if (!empty($line['set'])) {
			$posnr++;
			try {
				opret_saet($ordreId, (int)$line['vare_id'], (float)$line['line_net'] / (int)$line['quantity'], (float)$line['vat_rate'],
					(int)$line['quantity'], '', (int)($line['lager'] ?? 0), $line['lager_plan'] ?? array());
			} catch (InvalidArgumentException | DomainException $e) {
				throw new ShopifyImportError("set {$line['varenr']}: " . $e->getMessage());
			}
			$new = shopify_reread_new_lines($ordreId, $before);
			$net = 0.0;
			foreach ($new as $i => $row) {
				$net += round(((float)$row['pris'] - (float)$row['pris'] / 100 * (float)$row['rabat']) * (float)$row['antal'], 3);
				// opret_saet() numbers its lines itself: they follow on from the order's lines, the set line last
				shopify_db_rows('UPDATE ordrelinjer SET posnr = $1 WHERE id = $2', array($posnr + $i, $row['id']));
			}
			$posnr += max(0, count($new) - 1);
			if (count($new) < 2 || abs($net - (float)$line['line_net']) > 0.001 * count($new)) {
				throw new ShopifyImportError("set {$line['varenr']} was written as " . count($new) . " lines at $net, not at {$line['line_net']}");
			}
			// opret_saet() reads the BOM again and writes a row per draw of the plan: one changed since the validation would
			// draw other items, or the same items from other lagre, so each row must be a validated draw, duplicates included
			$bomItems = array_column($line['bom'] ?? array(), 'vare_id', 'bom_id');
			$expected = array_map(static fn(array $d): string => (int)($bomItems[$d['bom_id']] ?? 0) . ' from lager ' . (int)$d['lager'] . ' x ' . (int)$d['quantity_milli'],
				$line['lager_plan'] ?? array());
			$written = array_map(static fn(array $r): string => $r['vare_id'] . ' from lager ' . $r['lager'] . ' x ' . (int)round((float)$r['antal'] * 1000),
				array_values(array_filter($new, static fn(array $r): bool => !($r['vare_id'] === (int)$line['vare_id'] && $r['samlevare'] === 'on'))));
			sort($expected);
			sort($written);
			if ($written !== $expected) {
				throw new ShopifyImportError("set {$line['varenr']} was written as " . implode(', ', $written) . ', not as validated: ' . implode(', ', $expected));
			}
			continue;
		}
		$rows = shopify_intake_line_rows($line);
		foreach ($rows as $row) {
			$posnr++;
			$quantity = (float)$row['quantity'];
			$price = $quantity > 0 ? round($row['net'] / $quantity, 3) : 0.0;
			shopify_import_line($ordreId, (int)$line['vare_id'], $varenr, $quantity, $price, $posnr, (int)$row['lager'], (string)$line['varenr']);
		}
	}
	$freight = $validated['freight'];
	if ($freight !== null) {
		$item = shopify_db_rows('SELECT varenr FROM varer WHERE id = $1', array((int)$freight['vare_id']))[0] ?? null;
		if ($item === null) {
			throw new ShopifyImportError("the freight item {$freight['vare_id']} no longer exists");
		}
		$posnr++;
		shopify_import_line($ordreId, (int)$freight['vare_id'], (string)$item['varenr'], 1.0, round((float)$freight['net'], 3), $posnr, 0, (string)$item['varenr']);
	}
}

/** One plain row through opret_ordrelinje(); it must be the row asked for. */
function shopify_import_line(int $ordreId, int $vareId, string $varenr, float $quantity, float $price, int $posnr, int $lager, string $label): void {
	$before = array_column(shopify_db_rows('SELECT id FROM ordrelinjer WHERE ordre_id = $1', array($ordreId)), 'id');
	$answer = opret_ordrelinje($ordreId, $vareId, $varenr, $quantity, '', $price, 0, 100, 'DO', '', $posnr, '0', '', '', '', '0', '', '', '', $lager, __LINE__, true);
	$new = shopify_reread_new_lines($ordreId, $before);
	if (count($new) !== 1 || $new[0]['vare_id'] !== $vareId || abs((float)$new[0]['antal'] - $quantity) > 0.0005
		|| abs((float)$new[0]['pris'] - $price) > 0.0005 || (float)$new[0]['rabat'] != 0) {
		$why = is_string($answer) && !is_numeric($answer) ? ": $answer" : '';
		throw new ShopifyImportError("item $label was not written as $quantity at $price on lager $lager$why");
	}
}

/**
 * The order's sum and VAT as momsupdat() sums them: each line's net to three decimals, the VAT
 * on the sum when every taxed line has the order's one rate and there is no set, else per line
 * to two decimals, where a set's own line carries none.
 *
 * @return array{sum: float, moms: float, rows: int}
 */
function shopify_import_totals(int $ordreId): array {
	$rows = shopify_db_rows('SELECT pris, antal, rabat, procent, momssats, momsfri, samlevare, saet, vare_id FROM ordrelinjer WHERE ordre_id = $1 AND posnr >= 0', array($ordreId));
	$sum = 0.0;
	$vat = 0.0;
	$rates = array();
	$set = false;
	foreach ($rows as $r) {
		$net = ((float)$r['pris'] - (float)$r['pris'] / 100 * (float)$r['rabat']) * (float)$r['antal'];
		if ($r['procent'] !== null && $r['procent'] !== '') {
			$net *= (float)$r['procent'] / 100;
		}
		$sum += round($net, 3);
		if ((int)$r['saet'] > 0) {
			$set = true;
		}
		if (trim((string)$r['samlevare']) === 'on' && (int)$r['saet'] > 0) {
			continue;
		}
		$rate = trim((string)$r['momsfri']) === 'on' ? 0.0 : (float)$r['momssats'];
		$rates[(string)$rate] = true;
		$vat += round($net * $rate / 100, 2);
	}
	if (!$set && count($rates) === 1 && $rows !== array()) {
		$vat = round($sum * (float)array_key_first($rates) / 100, 2);
	}
	return array('sum' => round($sum, 2), 'moms' => round($vat, 2), 'rows' => count($rows));
}

/** The items a move barrier is opened for: every line's item, a set's components and the set. */
function shopify_import_barrier_items(array $validated): array {
	$ids = array();
	foreach ($validated['lines'] as $line) {
		$ids[] = (int)$line['vare_id'];
		foreach ($line['bom'] ?? array() as $c) {
			$ids[] = (int)$c['vare_id'];
		}
	}
	return array_values(array_unique(array_filter($ids, fn(int $id): bool => $id > 0)));
}

/**
 * After levering(): every item line is delivered in full, no more and no less.
 *
 * @throws ShopifyImportError
 */
function shopify_import_check_delivered(int $ordreId): void {
	$rows = shopify_db_rows(
		"SELECT l.posnr, l.varenr, l.antal, COALESCE((SELECT sum(b.antal) FROM batch_salg b WHERE b.linje_id = l.id), 0) AS delivered
		 FROM ordrelinjer l WHERE l.ordre_id = $1 AND l.posnr > 0 AND l.vare_id > 0 AND btrim(COALESCE(l.varenr, '')) <> ''",
		array($ordreId)
	);
	foreach ($rows as $r) {
		if (abs((float)$r['delivered'] - (float)$r['antal']) > 0.0005) {
			throw new ShopifyImportError("line {$r['posnr']} ({$r['varenr']}) is delivered {$r['delivered']} of {$r['antal']}");
		}
	}
}

/** One line in the PHP error log, redacted. */
function shopify_import_log(string $what, array $context): void {
	error_log('shopify import: ' . $what . ' ' . json_encode(shopify_redact($context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}
