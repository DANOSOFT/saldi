<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/routines/SaldiExpiringBatchesRoutine.php --- ai/mcp-assistant --- 2026.10.02
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
// Find positive remaining batches expiring within the tenant warning horizon.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Implement opt-in batch expiry findings including past due stock.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiRoutine.php';
require_once __DIR__ . '/../../tools/SaldiToolRuntime.php';

final class SaldiExpiringBatchesRoutine extends SaldiBaseRoutine
{
	public function name(): string
	{
		return 'expiring_batches';
	}
	public function permission(): string
	{
		return 'products.read';
	}
	public function titleTextId(): int
	{
		return 5942;
	}
	public function schedule(): string
	{
		return 'daily';
	}
	/** @return array{routine:string,scheduled_date:string,count:int,total?:array{amount:string,currency:string,converted:bool},rows:list<array>}|null */
	public function run(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): ?array
	{
		$this->cutoff($scheduledDate, 0);
		if (SaldiSettings::get($db, 'items', 'batchExpiryEnabled') !== 'on') {
			return null;
		}
		$days = max(0, (int)SaldiSettings::get($db, 'lager', 'due_date_warning_days', '30'));
		$end = (new DateTimeImmutable($scheduledDate))->modify('+' . $days . ' days')->format('Y-m-d');
		$where = ' FROM batch_kob batch JOIN varer product ON product.id = batch.vare_id WHERE batch.rest > 0 AND batch.due_date <= ?';
		$count = (int)$db->value('SELECT COUNT(*)' . $where, [$end]);
		if ($count === 0) {
			return null;
		}
		$rows = $db->all('SELECT batch.due_date, batch.batch_no, product.id, product.varenr, product.beskrivelse' . $where . ' ORDER BY batch.due_date, batch.id LIMIT 20', [$end]);
		return ['routine' => $this->name(), 'scheduled_date' => $scheduledDate, 'count' => $count, 'rows' => array_map(static fn(array $row): array => ['label' => SaldiToolRuntime::text($row['varenr'] . ' ' . $row['beskrivelse'] . ', batch ' . $row['batch_no']), 'date' => $row['due_date'], 'amount' => null, 'screen' => SaldiScreens::link('product', (int)$row['id'])], $rows)];
	}
}
