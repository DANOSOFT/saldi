<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/routines/SaldiLowStockRoutine.php --- ai/mcp-assistant --- 2026.10.01
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// Open products whose stock is below their positive minimum.
//
// 20261001 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiRoutine.php';

final class SaldiLowStockRoutine extends SaldiBaseRoutine
{
	public function name(): string { return 'low_stock'; }
	public function permission(): string { return 'products.read'; }
	public function titleTextId(): int { return 5722; }
	public function run(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): ?array
	{
		$this->cutoff($scheduledDate, 0);
		$where = " FROM varer WHERE COALESCE(lukket, '') <> 'on' AND min_lager > 0 AND beholdning < min_lager";
		$count = (int)$db->value('SELECT COUNT(*)' . $where);
		if ($count === 0) {
			return null;
		}
		$rows = $db->all('SELECT id, varenr, beskrivelse, beholdning, min_lager' . $where . ' ORDER BY min_lager - beholdning DESC, id LIMIT 20');
		return ['routine' => $this->name(), 'scheduled_date' => $scheduledDate, 'count' => $count, 'rows' => array_map(fn(array $r): array => ['label' => $this->label($r['varenr'] . ' ' . $r['beskrivelse'] . ' (' . $r['beholdning'] . ' / ' . $r['min_lager'] . ')'), 'screen' => SaldiScreens::link('product', (int)$r['id'])], $rows)];
	}
}
