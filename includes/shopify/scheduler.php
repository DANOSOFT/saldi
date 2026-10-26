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
// 20261026 Sawaneh SD-737 seam: contracts only (classes, constants, public signatures); bodies follow.

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
	throw new LogicException('SD-737: contract only; the body lands in the implementation PR');
}
