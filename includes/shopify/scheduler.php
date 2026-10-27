<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/scheduler.php --- ver 5.0.0 --- 2026.10.26
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
// Recurring jobs of the Shopify module (WP02, plan P:321-327). Pure: given the time and the
// shops, it returns the slots that are due. Each slot has a key, so the queue's unique index
// makes every slot run once even when several runs see it. Sub-daily slots are keyed in UTC;
// daily slots by their Copenhagen date, so a DST change neither skips nor repeats them.
//
// 20261026 Sawaneh SD-737: created (WP02-2).

require_once __DIR__ . '/queue.php';

const SHOPIFY_SCHEDULE_ZONE = 'Europe/Copenhagen';

/**
 * Slot definitions: every N minutes (UTC grid) or daily at a Copenhagen time; per shop when
 * the shop has one of the listed flows enabled, else once for the tenant.
 */
const SHOPIFY_SCHEDULE_SLOTS = array(
	'price.window' => array('every' => 15, 'flows' => array('prices')),
	'monitor.counterpart' => array('every' => 15, 'flows' => array('orders')),
	'webhook.health' => array('every' => 60, 'flows' => array('orders', 'customers')),
	'stock.reconcile' => array('daily' => '02:00', 'flows' => array('stock_publish')),
	'monitor.overview' => array('daily' => '07:00'),
	'runtime.purge' => array('daily' => '07:15'),
);

/**
 * The slots due at $now. A sub-daily slot is due during its own window only; a daily slot
 * from its local time until the end of that Copenhagen day, so a run that starts late still
 * catches it once.
 *
 * @param list<array<string, mixed>> $shops rows of shopify_shops (id, shop_key, <flow>_enabled)
 * @return list<array{type: string, shop_id: int|null, dedupe_key: string, schedule_slot: string, priority: int, payload: array<string, mixed>}>
 */
function shopify_schedule_due(DateTimeImmutable $now, array $shops): array {
	$utc = $now->setTimezone(new DateTimeZone('UTC'));
	$local = $now->setTimezone(new DateTimeZone(SHOPIFY_SCHEDULE_ZONE));
	$due = array();
	foreach (SHOPIFY_SCHEDULE_SLOTS as $type => $slot) {
		if (isset($slot['every'])) {
			$minutes = (int)$utc->format('H') * 60 + (int)$utc->format('i');
			$start = $minutes - $minutes % $slot['every'];
			$key = $utc->format('Y-m-d') . sprintf('T%02d:%02dZ', intdiv($start, 60), $start % 60);
		} else {
			[$hour, $minute] = array_map('intval', explode(':', $slot['daily']));
			// setTime on a missing local time (spring forward) moves to the next valid one
			if ($local < $local->setTime($hour, $minute)) {
				continue;
			}
			$key = $local->format('Y-m-d');
		}
		foreach (shopify_schedule_targets($slot, $shops) as $shop) {
			$due[] = array(
				'type' => $type,
				'shop_id' => $shop === null ? null : (int)$shop['id'],
				'dedupe_key' => ($shop === null ? 'tenant' : (string)$shop['shop_key']) . ':' . $key,
				'schedule_slot' => $key,
				'priority' => SHOPIFY_QUEUE_PRIORITY[$type],
				'payload' => array('slot' => $key),
			);
		}
	}
	return $due;
}

/**
 * The shops a slot runs for: those with one of its flows enabled, or a single tenant-wide
 * target (null) when the slot names no flows.
 *
 * @param array<string, mixed> $slot
 * @param list<array<string, mixed>> $shops
 * @return list<array<string, mixed>|null>
 */
function shopify_schedule_targets(array $slot, array $shops): array {
	if (!isset($slot['flows'])) {
		return array(null);
	}
	$targets = array();
	foreach ($shops as $shop) {
		foreach ($slot['flows'] as $flow) {
			$enabled = $shop[$flow . '_enabled'] ?? false;
			if ($enabled === true || $enabled === 't' || $enabled === '1' || $enabled === 1) {
				$targets[] = $shop;
				break;
			}
		}
	}
	return $targets;
}

/**
 * Inserts the due slots whose job type has a handler; a slot that already has a job is left
 * alone.
 *
 * @param list<string> $types job types with a registered handler
 * @return int slots inserted
 */
function shopify_schedule_insert(DateTimeImmutable $now, array $shops, array $types): int {
	$inserted = 0;
	foreach (shopify_schedule_due($now, $shops) as $slot) {
		if (!in_array($slot['type'], $types, true)) {
			continue;
		}
		$result = shopify_queue_insert(array(
			'type' => $slot['type'], 'shop_id' => $slot['shop_id'], 'dedupe_key' => $slot['dedupe_key'],
			'payload' => $slot['payload'], 'priority' => $slot['priority'], 'run_at' => null,
			'correlation' => array(), 'schedule_slot' => $slot['schedule_slot'],
		));
		$inserted += $result['created'] ? 1 : 0;
	}
	return $inserted;
}
