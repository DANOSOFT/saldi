<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/credits.php --- ver 5.1.1 --- 2026.11.25
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
// Credit notes on web orders (WP14-2, K46, K48). A posted credit note of an imported web order is
// published on its Shopify order (job credit.publish): a dated note line "Kreditnota <nr>, <beløb>",
// a tag and the metafield saldi.kreditnota_url with the credit note's link. Shopify refunds, which
// customer service makes by hand, are matched against the posted credit notes into
// shopify_refund_allocations; a refund no credit note accounts for opens an error and alarms once.
// Nothing here moves money in Shopify. The scan (job credit.scan, every five minutes per shop with
// orders) finds the credit notes to publish and runs the matching, so posting needs no hook.
//
// 20261125 CL/SS SD-834: created (WP14-2).

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../links.php';
require_once __DIR__ . '/../queue.php';

/** shopify_order_links.flags namespace of the web order: one entry per published credit note. */
const SHOPIFY_CREDIT_NAMESPACE = 'credits_v1';
/** The tag a web order gets once one of its credit notes is published. */
const SHOPIFY_CREDIT_TAG = 'saldi-krediteret';
const SHOPIFY_CREDIT_METAFIELD_KEY = 'kreditnota_url';
/** Credit notes queued by one scan; the next scan takes the rest. */
const SHOPIFY_CREDIT_SCAN_LIMIT = 200;
/** shopify_refunds.state once matched, and once found to have no credit note (the alarm is raised on that step only). */
const SHOPIFY_REFUND_MATCHED = 'matched';
const SHOPIFY_REFUND_UNMATCHED = 'unmatched';

/** A failure that is not the credit note's: the queue retries the job and alarms after the third attempt. */
final class ShopifyCreditPublishError extends RuntimeException
{
}

/** The heavier services the jobs need. */
function shopify_credits_require(): void {
	require_once __DIR__ . '/../client.php';
	require_once __DIR__ . '/../errors.php';
	require_once __DIR__ . '/../noteAppend.php';
	require_once __DIR__ . '/orderWriteback.php';
	require_once __DIR__ . '/../../documents/dokumentLinks.php';
}

/**
 * The posted credit notes among $ids that belong to an imported web order of a shop with orders on,
 * with that order's link. Not posted, not a credit note, or no linked web order: left out.
 *
 * @param list<int> $ids
 * @return list<array<string, string|null>> credit (id, fakturanr, sum, moms, valuta) and link (link_id, shop_id, shop_key, order_gid)
 */
function shopify_credit_rows(array $ids): array {
	$ids = array_values(array_unique(array_map('intval', $ids)));
	if (!$ids) {
		return array();
	}
	return shopify_db_rows("SELECT c.id, c.fakturanr, c.sum, c.moms, c.valuta, l.id AS link_id, l.shop_id, s.shop_key, l.order_gid, l.flags
		FROM ordrer c JOIN shopify_order_links l ON l.ordre_id = c.kred_ord_id AND l.direction = 'web' AND l.order_gid IS NOT NULL
		JOIN shopify_shops s ON s.id = l.shop_id AND s.orders_enabled
		WHERE c.id = ANY($1::int[]) AND c.art = 'DK' AND c.status >= 3 AND btrim(coalesce(c.fakturanr, '')) <> '' ORDER BY c.id",
		array('{' . implode(',', $ids) . '}'));
}

/**
 * Queues credit.publish for each of $ids that is a posted credit note of a linked web order and
 * not published yet.
 *
 * @param list<int> $ids
 * @return int jobs queued
 */
function shopify_credit_posted(array $ids): int {
	$queued = 0;
	foreach (shopify_credit_rows($ids) as $c) {
		if (shopify_credit_published($c)) {
			continue;
		}
		$job = shopify_queue_enqueue_coalesced('credit.publish', (int)$c['shop_id'], "{$c['shop_key']}:credit:{$c['id']}", array('ordre_id' => (int)$c['id']),
			SHOPIFY_QUEUE_PRIORITY['credit.publish'], null, array('ordre_id' => (int)$c['id'], 'order_gid' => $c['order_gid']));
		$queued += $job['created'] ? 1 : 0;
	}
	return $queued;
}

/** @param array<string, string|null> $credit a row of shopify_credit_rows() */
function shopify_credit_checkpoint(array $credit): array {
	$flags = json_decode((string)$credit['flags'], true);
	return (array)($flags[SHOPIFY_CREDIT_NAMESPACE][(string)$credit['id']] ?? array());
}

/** @param array<string, string|null> $credit a row of shopify_credit_rows() */
function shopify_credit_published(array $credit): bool {
	return !empty(shopify_credit_checkpoint($credit)['published']);
}

/** Stores the credit note's checkpoint on the web order's link, beside those of its other credit notes. */
function shopify_credit_save_checkpoint(int $linkId, int $creditId, array $checkpoint): void {
	shopify_db_rows("UPDATE shopify_order_links SET flags = jsonb_set(flags, '{" . SHOPIFY_CREDIT_NAMESPACE . "}',
		coalesce(flags->'" . SHOPIFY_CREDIT_NAMESPACE . "', '{}'::jsonb) || jsonb_build_object($2::text, $3::jsonb)), updated_at = now() WHERE id = $1",
		array($linkId, (string)$creditId, shopify_link_json($checkpoint)));
}

/** The credit note's amount incl. VAT in øre, positive (credit lines are stored negative). */
function shopify_credit_amount_minor(array $credit): int {
	return abs((int)round(((float)$credit['sum'] + (float)$credit['moms']) * 100));
}

/** An amount in øre as HML reads it: 1.250 kr., or 1.250,50 kr. with øre. */
function shopify_credit_kroner(int $minor, string $currency = 'DKK'): string {
	$decimals = $minor % 100 === 0 ? 0 : 2;
	$unit = in_array(strtoupper(trim($currency)), array('', 'DKK'), true) ? 'kr.' : strtoupper(trim($currency));
	return number_format($minor / 100, $decimals, ',', '.') . " $unit";
}

/** K48: the note line of a credit note, "Kreditnota 12345, 1.250 kr." (shopify_append_note() dates it). */
function shopify_build_credit_note(array $credit): string {
	return 'Kreditnota ' . trim((string)$credit['fakturanr']) . ', ' . shopify_credit_kroner(shopify_credit_amount_minor($credit), (string)($credit['valuta'] ?? ''));
}

/** The op_key of a credit note's note line, stable across replays. */
function shopify_credit_note_op_key(int $creditId): string {
	return "credit:$creditId";
}

/**
 * Job credit.publish (prio 25): the note line, the tag and the link of one posted credit note on its
 * web order. The note carries a marker, so a replay appends nothing; the tag is sent once (tagsAdd
 * would put back a tag HML removed); metafieldsSet is safe to repeat. Each step is checkpointed on
 * the link, so a retry carries on where the last attempt stopped.
 */
function shopify_publish_credit(array $job, float $deadline, callable $heartbeat, array $rt = array()): array {
	shopify_credits_require();
	$creditId = (int)($job['payload']['ordre_id'] ?? 0);
	if ($creditId <= 0) {
		throw new LogicException("job {$job['id']} credit.publish has no ordre_id");
	}
	$rt += array('deadline' => $deadline);
	$credit = shopify_credit_rows(array($creditId))[0] ?? null;
	if ($credit === null || shopify_credit_published($credit)) {
		return array('outcome' => 'done');
	}
	$shopId = (int)$credit['shop_id'];
	$checkpoint = shopify_credit_checkpoint($credit);
	$save = function () use ($credit, $creditId, &$checkpoint): void {
		shopify_credit_save_checkpoint((int)$credit['link_id'], $creditId, $checkpoint);
	};
	if (empty($checkpoint['note'])) {
		$note = shopify_append_note($shopId, (string)$credit['order_gid'], shopify_credit_note_op_key($creditId), shopify_build_credit_note($credit), $rt);
		if ($note['status'] === 'failed') {
			throw new ShopifyCreditPublishError("credit note $creditId: the note was not appended: " . implode('; ', $note['errors']));
		}
		$checkpoint['note'] = true;
		$save();
	}
	if (empty($checkpoint['tag'])) {
		$tags = shopify_add_tags($shopId, (string)$credit['order_gid'], array(SHOPIFY_CREDIT_TAG), $rt);
		if ($tags['status'] === 'failed') {
			throw new ShopifyCreditPublishError("credit note $creditId: the tag was not added: " . implode('; ', $tags['errors']));
		}
		$checkpoint['tag'] = true;
		$save();
	}
	$url = dokument_link($creditId, 'credit');
	$metafields = array(array('ownerId' => shopify_gid((string)$credit['order_gid'], 'Order'), 'namespace' => SHOPIFY_METAFIELD_NAMESPACE,
		'key' => SHOPIFY_CREDIT_METAFIELD_KEY, 'type' => 'url', 'value' => $url));
	$set = shopify_set_metafields(shopify_writeback_shop($shopId), $metafields, $rt);
	if ($set['status'] === 'failed') {
		throw new ShopifyCreditPublishError("credit note $creditId: the link was not set: " . implode('; ', $set['errors']));
	}
	$checkpoint['published'] = true;
	$save();
	return array('outcome' => 'done');
}

/**
 * Job credit.scan (every five minutes per shop with orders): queues the shop's posted credit notes
 * that are not published, then matches the shop's refunds that are not matched yet.
 */
function shopify_credit_scan(array $job, float $deadline, callable $heartbeat, array $rt = array()): array {
	shopify_credits_require();
	$shopId = (int)$job['shop_id'];
	$ids = array_column(shopify_db_rows("SELECT c.id FROM ordrer c
		JOIN shopify_order_links l ON l.ordre_id = c.kred_ord_id AND l.direction = 'web' AND l.order_gid IS NOT NULL AND l.shop_id = $1
		WHERE c.art = 'DK' AND c.status >= 3 AND btrim(coalesce(c.fakturanr, '')) <> ''
		AND NOT coalesce((l.flags->'" . SHOPIFY_CREDIT_NAMESPACE . "'->(c.id::text)->>'published')::boolean, false)
		ORDER BY c.id LIMIT $2", array($shopId, SHOPIFY_CREDIT_SCAN_LIMIT)), 'id');
	$queued = shopify_credit_posted(array_map('intval', $ids));
	$orders = array_column(shopify_db_rows('SELECT DISTINCT order_gid FROM shopify_refunds WHERE shop_id = $1 AND state IN ($2, $3) ORDER BY 1',
		array($shopId, 'received', SHOPIFY_REFUND_UNMATCHED)), 'order_gid');
	$matched = 0;
	foreach ($orders as $gid) {
		$matched += count(shopify_reconcile_refunds($shopId, (string)$gid)['matched']);
	}
	return array('outcome' => 'done', 'queued' => $queued, 'matched' => $matched);
}

/**
 * K46: matches the order's completed refunds against its posted credit notes. One credit note is one
 * refund (procedure-delkreditering.md): a refund is allocated to a credit note with exactly its amount
 * still unrefunded, the one whose number the refund's note names first, else the oldest. A refund with
 * a transaction still pending, or none successful, is left received for later, so SD-801 can still store
 * the settled amount; one that no credit note accounts for opens
 * refund.unmatched and alarms on that first look only, and is matched once its credit note is posted.
 * A credit note nobody has refunded is no error here (the bookkeeper's check, procedure section 5).
 *
 * @return array{matched: list<array{refund_gid: string, credit_ordre_id: int, amount_minor: int}>, unmatched: list<string>}
 */
function shopify_reconcile_refunds(int $shopId, string $orderGid): array {
	shopify_credits_require();
	return shopify_db_transaction(function () use ($shopId, $orderGid): array {
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("shopify_refund_match:$shopId:$orderGid"));
		$out = array('matched' => array(), 'unmatched' => array());
		$link = shopify_db_rows("SELECT ordre_id FROM shopify_order_links WHERE shop_id = $1 AND order_gid = $2 AND direction = 'web'", array($shopId, $orderGid))[0] ?? null;
		if ($link === null || $link['ordre_id'] === null) {
			return $out;
		}
		$credits = array();
		foreach (shopify_db_rows("SELECT c.id, c.fakturanr, c.sum, c.moms, coalesce(nullif(btrim(c.valuta), ''), 'DKK') AS valuta,
			coalesce((SELECT sum(a.amount_minor) FROM shopify_refund_allocations a WHERE a.credit_ordre_id = c.id), 0) AS allocated
			FROM ordrer c WHERE c.kred_ord_id = $1 AND c.art = 'DK' AND c.status >= 3 AND btrim(coalesce(c.fakturanr, '')) <> '' ORDER BY c.id",
			array((int)$link['ordre_id'])) as $c) {
			$credits[(int)$c['id']] = $c + array('left' => shopify_credit_amount_minor($c) - (int)$c['allocated']);
		}
		$refunds = shopify_db_rows('SELECT refund_gid, amount, currency, state, transactions_b64, payload_b64 FROM shopify_refunds
			WHERE shop_id = $1 AND order_gid = $2 AND state IN ($3, $4) ORDER BY refunded_at NULLS LAST, id',
			array($shopId, $orderGid, 'received', SHOPIFY_REFUND_UNMATCHED));
		foreach ($refunds as $r) {
			$minor = (int)round((float)$r['amount'] * 100);
			if ($minor <= 0 || shopify_refund_pending($r)) {
				continue;
			}
			$creditId = shopify_refund_pick_credit($credits, $minor, strtoupper((string)$r['currency']), shopify_refund_note($r));
			if ($creditId === null) {
				if ($r['state'] === 'received') {
					shopify_db_rows('UPDATE shopify_refunds SET state = $3, updated_at = now() WHERE shop_id = $1 AND refund_gid = $2', array($shopId, $r['refund_gid'], SHOPIFY_REFUND_UNMATCHED));
					shopify_error_open($shopId, 'refund.unmatched', (string)$r['refund_gid'], "a Shopify refund of " . shopify_credit_kroner($minor, (string)$r['currency'])
						. " on order $orderGid has no Saldi credit note with that amount: make the credit note in Saldi first (K46)", 'error',
						array('order_gid' => $orderGid, 'refund_gid' => $r['refund_gid'], 'ordre_id' => (int)$link['ordre_id']));
				}
				$out['unmatched'][] = (string)$r['refund_gid'];
				continue;
			}
			shopify_db_rows('INSERT INTO shopify_refund_allocations (refund_gid, credit_ordre_id, amount_minor) VALUES ($1, $2, $3)', array($r['refund_gid'], $creditId, $minor));
			shopify_db_rows('UPDATE shopify_refunds SET state = $3, updated_at = now() WHERE shop_id = $1 AND refund_gid = $2', array($shopId, $r['refund_gid'], SHOPIFY_REFUND_MATCHED));
			shopify_error_resolve_key($shopId, 'refund.unmatched', (string)$r['refund_gid']);
			$credits[$creditId]['left'] -= $minor;
			$out['matched'][] = array('refund_gid' => (string)$r['refund_gid'], 'credit_ordre_id' => $creditId, 'amount_minor' => $minor);
		}
		return $out;
	});
}

/**
 * The credit note a refund of $minor øre settles: exactly that amount left and the same currency;
 * the one whose number the refund's note names, else the oldest. Null when none.
 *
 * @param array<int, array<string, mixed>> $credits by id, oldest first
 */
function shopify_refund_pick_credit(array $credits, int $minor, string $currency, string $note): ?int {
	$fits = array_filter($credits, fn(array $c): bool => $c['left'] === $minor && ($currency === '' || strtoupper((string)$c['valuta']) === $currency));
	foreach ($fits as $id => $c) {
		if (preg_match('/(?<![0-9])' . preg_quote(trim((string)$c['fakturanr']), '/') . '(?![0-9])/', $note)) {
			return $id;
		}
	}
	return $fits ? (int)array_key_first($fits) : null;
}

/** Whether one of the refund's refund transactions has not settled yet (SD-801 keeps updating it while it is received). */
function shopify_refund_pending(array $refund): bool {
	$transactions = json_decode((string)base64_decode((string)$refund['transactions_b64'], true), true);
	foreach (is_array($transactions) ? $transactions : array() as $t) {
		if (is_array($t) && strtolower((string)($t['kind'] ?? '')) === 'refund' && strtolower((string)($t['status'] ?? '')) === 'pending') {
			return true;
		}
	}
	return false;
}

/** The refund's note as Shopify sent it (customer service writes the credit note's number there). */
function shopify_refund_note(array $refund): string {
	$payload = json_decode((string)base64_decode((string)$refund['payload_b64'], true), true);
	return is_array($payload) ? (string)($payload['note'] ?? '') : '';
}
