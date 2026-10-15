<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/routines/SaldiOverdueDebtorsRoutine.php --- ai/mcp-assistant --- 2026.10.02
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
// Overdue debtor balances grouped and ranked in tenant base currency.
//
// 20261001 CDX/LH Created.
// 20261002 CL/LH Shared openpost conversion: placeholder rates resolved, unconvertible items excluded.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiRoutine.php';
require_once __DIR__ . '/../../tools/SaldiToolRuntime.php';

final class SaldiOverdueDebtorsRoutine extends SaldiBaseRoutine
{
	public function name(): string { return 'overdue_debtors'; }
	public function permission(): string { return 'debtors.read'; }
	public function titleTextId(): int { return 5720; }
	public function run(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): ?array
	{
		$cutoff = $this->cutoff($scheduledDate, 7);
		$conv = SaldiToolRuntime::openpostBaseSql($ctx->baseCurrency);
		$amount = $conv['amount'];
		$bind = $conv['params'];
		$open = " FROM openpost o JOIN adresser a ON a.id = o.konto_id WHERE a.art = 'D' AND COALESCE(o.udlignet, '') <> '1' AND o.forfaldsdate < ?";
		// Items whose foreign rate cannot be resolved stay out of the ranking and the total.
		$from = $open . ' AND (' . $amount . ') IS NOT NULL';
		$summary = $db->one('SELECT COUNT(DISTINCT a.id) AS count, ROUND(SUM(' . $amount . '), 2) AS total' . $from, array_merge($bind, [$cutoff], $bind));
		$unavailable = (int)$db->value('SELECT COUNT(*)' . $open . ' AND (' . $conv['missing'] . ') = 1', array_merge([$cutoff], $bind));
		if ((int)$summary['count'] === 0) {
			return null;
		}
		$rows = $db->all('SELECT a.id, a.kontonr, a.firmanavn, ROUND(SUM(' . $amount . '), 2) AS amount, MIN(o.forfaldsdate) AS date' . $from . ' GROUP BY a.id, a.kontonr, a.firmanavn ORDER BY SUM(' . $amount . ') DESC, a.id LIMIT 20', array_merge($bind, [$cutoff], $bind, $bind));
		return ['routine' => $this->name(), 'scheduled_date' => $scheduledDate, 'count' => (int)$summary['count'], 'total' => ['amount' => $this->money((string)$summary['total']), 'currency' => $ctx->baseCurrency, 'converted' => $unavailable === 0], 'rows' => array_map(fn(array $r): array => ['label' => $this->label($r['kontonr'] . ' ' . $r['firmanavn']), 'amount' => $this->money((string)$r['amount']), 'currency' => $ctx->baseCurrency, 'date' => $r['date'], 'screen' => SaldiScreens::link('debtor', (int)$r['id'])], $rows)];
	}
}
