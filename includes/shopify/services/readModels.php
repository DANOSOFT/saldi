<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/readModels.php --- ver 5.1.0 --- 2026.11.03
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
// Read models of the Shopify module (WP08-2): what the order screen and the debtor card show of
// Shopify, read from the links and projections. Read-only; no Shopify call.
//
// 20261103 CL/SS SD-801: created (WP08-2).

require_once __DIR__ . '/../db.php';

/**
 * The Shopify side of a Saldi order: its link (the first, should it have two), its status from
 * ORDERS_UPDATED, its cancellation, its fulfillments with tracking and its refunds, newest first.
 * Null when the order has no Shopify link.
 *
 * @return array{shop_key: string, order_gid: string|null, order_name: string|null, direction: string, state: string, cancelled_at: string|null,
 *   status: array{financial_status: string|null, fulfillment_status: string|null, updated_at: string|null}|null, flags: array<string, mixed>,
 *   fulfillments: list<array{fulfillment_gid: string, status: string|null, tracking_company: string|null, tracking_number: string|null, tracking_url: string|null, shipped_at: string|null, shipment_status: string|null}>,
 *   refunds: list<array{refund_gid: string, amount: string|null, currency: string|null, state: string, refunded_at: string|null}>}|null
 */
function shopify_order_read_model(int $ordreId): ?array {
	$rows = shopify_db_rows(
		"SELECT l.shop_id, s.shop_key, l.order_gid, l.order_name, l.direction, l.state, l.cancelled_at, l.flags
		 FROM shopify_order_links l JOIN shopify_shops s ON s.id = l.shop_id WHERE l.ordre_id = $1 ORDER BY l.id LIMIT 1",
		array($ordreId)
	);
	if (!isset($rows[0])) {
		return null;
	}
	$link = $rows[0];
	$flags = json_decode((string)$link['flags'], true);
	$flags = is_array($flags) ? $flags : array();
	$fulfillments = array();
	foreach (shopify_db_rows(
		'SELECT fulfillment_gid, status, tracking_company, tracking_number, tracking_url, shipped_at, payload_b64 FROM shopify_fulfillments
		 WHERE shop_id = $1 AND order_gid = $2 ORDER BY shipped_at DESC NULLS LAST, id DESC',
		array((int)$link['shop_id'], (string)$link['order_gid'])
	) as $f) {
		$body = json_decode((string)base64_decode((string)$f['payload_b64'], true), true);
		unset($f['payload_b64']);
		$f['shipment_status'] = is_array($body) && is_string($body['shipment_status'] ?? null) ? $body['shipment_status'] : null;
		$fulfillments[] = $f;
	}
	$refunds = shopify_db_rows(
		'SELECT refund_gid, amount, currency, state, refunded_at FROM shopify_refunds WHERE shop_id = $1 AND order_gid = $2 ORDER BY refunded_at DESC NULLS LAST, id DESC',
		array((int)$link['shop_id'], (string)$link['order_gid'])
	);
	return array(
		'shop_key' => (string)$link['shop_key'], 'order_gid' => $link['order_gid'], 'order_name' => $link['order_name'],
		'direction' => (string)$link['direction'], 'state' => (string)$link['state'], 'cancelled_at' => $link['cancelled_at'],
		'status' => is_array($flags['status'] ?? null) ? $flags['status'] : null, 'flags' => $flags,
		'fulfillments' => $fulfillments, 'refunds' => $refunds,
	);
}

/**
 * The Shopify customers a Saldi debtor is linked to, one per shop.
 *
 * @return list<array{shop_key: string, customer_gid: string, source_updated_at: string|null}>
 */
function shopify_debtor_read_model(int $adresserId): array {
	return shopify_db_rows(
		'SELECT s.shop_key, l.customer_gid, l.source_updated_at FROM shopify_customer_links l JOIN shopify_shops s ON s.id = l.shop_id
		 WHERE l.adresser_id = $1 ORDER BY s.shop_key',
		array($adresserId)
	);
}
