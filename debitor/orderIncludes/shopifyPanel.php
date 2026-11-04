<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/orderIncludes/shopifyPanel.php --- ver 5.1.0 --- 2026-11-04 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2026-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261104 CL/SS SD-744: created. The read-only Shopify panel of an order: status, cancellation, shipping, refunds and the EasyRoutes date.

/**
 * The Shopify side of a Saldi order as a read-only table row for the order header (three columns
 * wide), from shopify_order_read_model(): the Shopify order and shop, its payment and fulfilment
 * status, a cancellation, the delivery date EasyRoutes assigned, each shipment with its date and
 * tracking, and the refunds. Empty for an order without a Shopify link and on a tenant without
 * the Shopify schema, so other orders and installs show nothing new. Reads only; no Shopify call.
 *
 * @param int|string $sprogId the page's language, for findtekst()
 */
function shopify_order_panel(int $ordreId, $sprogId): string {
	if ($ordreId < 1) {
		return '';
	}
	require_once __DIR__ . '/../../includes/shopify/schema.php';
	if (!shopify_schema_ready()) {
		return '';
	}
	require_once __DIR__ . '/../../includes/shopify/services/readModels.php';
	$model = shopify_order_read_model($ordreId);
	if ($model === null) {
		return '';
	}
	$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	$day = static fn(?string $value): string => $value ? date('d-m-Y', strtotime($value)) : '';
	$row = static fn(string $label, string $html): string => "<tr><td style='width:200px;vertical-align:top'><b>$label</b></td><td>$html</td></tr>\n";

	$rows = $row(findtekst('8040|Shopify-ordre', $sprogId), $h($model['order_name'] ?? $model['order_gid']) . ' (' . $h($model['shop_key']) . ')');
	$status = $model['status'] ?? array();
	if (($status['financial_status'] ?? null) !== null) {
		$rows .= $row(findtekst('8041|Betaling', $sprogId), $h($status['financial_status']));
	}
	if (($status['fulfillment_status'] ?? null) !== null) {
		$rows .= $row(findtekst('8042|Levering', $sprogId), $h($status['fulfillment_status']));
	}
	if ($model['cancelled_at'] !== null) {
		$rows .= $row(findtekst('8043|Annulleret i Shopify', $sprogId), $day($model['cancelled_at']));
	}
	if ($model['easyroutes_levdato'] !== null) {
		$rows .= $row(findtekst('8047|Leveringsdato (EasyRoutes)', $sprogId), $day($model['easyroutes_levdato']));
	}
	foreach ($model['fulfillments'] as $f) {
		$tracking = trim($h($f['tracking_company']) . ' ' . $h($f['tracking_number']));
		$url = (string)($f['tracking_url'] ?? '');
		if ($tracking !== '' && preg_match('#^https?://#i', $url)) {
			$tracking = "<a href='" . $h($url) . "' target='_blank' rel='noopener noreferrer'>$tracking</a>";
		}
		$rows .= $row(findtekst('8044|Afsendt', $sprogId), $day($f['shipped_at']) . ($tracking !== '' ? ', ' . findtekst('8045|sporing', $sprogId) . ' ' . $tracking : ''));
	}
	foreach ($model['refunds'] as $r) {
		$rows .= $row(findtekst('8046|Refunderet', $sprogId), $day($r['refunded_at']) . ' ' . $h($r['amount'] === null ? '' : number_format((float)$r['amount'], 2, ',', '.')) . ' ' . $h($r['currency']));
	}
	return "<tr><td colspan='3'><table class='shopifyPanel' cellpadding='2' cellspacing='0' border='0' width='100%'><tbody>\n$rows</tbody></table></td></tr>\n";
}
