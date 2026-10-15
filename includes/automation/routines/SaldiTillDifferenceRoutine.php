<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/routines/SaldiTillDifferenceRoutine.php --- ai/mcp-assistant --- 2026.10.02
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
// Yesterday's cash-count differences, with till identity from the report head line.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Match stored Difference currency texts and exact Head line records.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiRoutine.php';
require_once __DIR__ . '/../../tools/SaldiToolRuntime.php';

final class SaldiTillDifferenceRoutine extends SaldiBaseRoutine
{
	public function name(): string
	{
		return 'till_difference';
	}
	public function permission(): string
	{
		return 'ledger.read';
	}
	public function titleTextId(): int
	{
		return 5943;
	}
	public function schedule(): string
	{
		return 'daily';
	}
	/** @return array{routine:string,scheduled_date:string,count:int,total?:array{amount:string,currency:string,converted:bool},rows:list<array>}|null */
	public function run(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): ?array
	{
		$date = $this->cutoff($scheduledDate, 1);
		$threshold = SaldiSettings::get($db, 'assistant', 'till_difference_threshold', '50');
		if (!preg_match('/^[0-9]{1,12}(?:\.[0-9]{1,3})?$/D', $threshold)) {
			$threshold = '50';
		}
		$where = " FROM report difference WHERE difference.type = 'cashCount' AND difference.date = ? AND difference.description LIKE 'Difference%' AND ABS(difference.total) >= CAST(? AS DECIMAL(15,3))";
		$count = (int)$db->value('SELECT COUNT(*)' . $where, [$date, $threshold]);
		if ($count === 0) {
			return null;
		}
		$rows = $db->all("SELECT difference.description, difference.total, (SELECT head.description FROM report head WHERE head.report_number = difference.report_number AND head.type = 'Head line' AND head.description LIKE 'Cash count, box %' ORDER BY head.id LIMIT 1) AS head" . $where . ' ORDER BY difference.report_number, difference.id LIMIT 20', [$date, $threshold]);
		return ['routine' => $this->name(), 'scheduled_date' => $scheduledDate, 'count' => $count, 'rows' => array_map(static function (array $row) use ($ctx, $date): array {
			$knownTill = preg_match('/^Cash count, box ([0-9]+)$/D', (string)$row['head'], $till);
			$knownCurrency = preg_match('/^Difference ([A-Z]{3}):/', (string)$row['description'], $currency);
			return ['label' => $knownTill ? 'Kasse ' . $till[1] : 'Kasse (ukendt)', 'amount' => SaldiToolRuntime::money($row['total']), 'currency' => $knownCurrency ? $currency[1] : $ctx->baseCurrency, 'date' => $date];
		}, $rows)];
	}
}
