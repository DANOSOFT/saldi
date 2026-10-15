<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/routines/SaldiStaleOpenJournalsRoutine.php --- ai/mcp-assistant --- 2026.10.01
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
// Old unposted journals containing saved rows.
//
// 20261001 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiRoutine.php';

final class SaldiStaleOpenJournalsRoutine extends SaldiBaseRoutine
{
	public function name(): string { return 'stale_open_journals'; }
	public function permission(): string { return 'journal.read'; }
	public function titleTextId(): int { return 5721; }
	public function run(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): ?array
	{
		$where = " FROM kladdeliste j WHERE COALESCE(j.bogfort, '-') = '-' AND j.kladdedate < ? AND EXISTS (SELECT 1 FROM kassekladde k WHERE k.kladde_id = j.id)";
		$params = [$this->cutoff($scheduledDate, 14)];
		$count = (int)$db->value('SELECT COUNT(*)' . $where, $params);
		if ($count === 0) {
			return null;
		}
		$rows = $db->all('SELECT j.id, j.kladdedate, j.kladdenote' . $where . ' ORDER BY j.kladdedate, j.id LIMIT 20', $params);
		return ['routine' => $this->name(), 'scheduled_date' => $scheduledDate, 'count' => $count, 'rows' => array_map(fn(array $r): array => ['label' => $this->label($r['id'] . ' ' . $r['kladdenote']), 'date' => $r['kladdedate'], 'screen' => SaldiScreens::link('journal', (int)$r['id'])], $rows)];
	}
}
