<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/orderLines.php --- ver 5.1.0 --- 2026.11.02
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
// The order lines the intake (WP09) just wrote. opret_ordrelinje() and opret_saet() return no line
// ids, so the intake reads the order's lines before and after a call and pairs them up from this.
//
// 20261102 CL/SS SD-789: created (WP07-2).

require_once __DIR__ . '/../db.php';

/**
 * The lines of an order that were not there before, in the order they were written.
 *
 * @param list<int> $beforeIds the order's line ids before the call
 * @return list<array{id: int, vare_id: int, antal: string, pris: string, rabat: string, saet: int, samlevare: string, lager: int}>
 */
function shopify_reread_new_lines(int $ordreId, array $beforeIds): array {
	$before = array_flip(array_map('intval', $beforeIds));
	$rows = shopify_db_rows("SELECT id, vare_id, antal, pris, rabat, saet, samlevare, lager FROM ordrelinjer WHERE ordre_id = $1 ORDER BY id", [$ordreId]);
	$new = array();
	foreach ($rows as $row) {
		if (isset($before[(int)$row['id']])) {
			continue;
		}
		$new[] = array('id' => (int)$row['id'], 'vare_id' => (int)$row['vare_id'], 'antal' => (string)$row['antal'], 'pris' => (string)$row['pris'],
			'rabat' => (string)$row['rabat'], 'saet' => (int)$row['saet'], 'samlevare' => (string)$row['samlevare'], 'lager' => (int)$row['lager']);
	}
	return $new;
}
