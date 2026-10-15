<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/routines/SaldiReorderSuggestionsRoutine.php --- ai/mcp-assistant --- 2026.10.04
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
// Weekly sales-based reorder finding, using the same exact calculation as the read tool.
//
// 20261004 CDX/LH Created.
// 20261004 CDX/LH Produce the top twenty replenishment suggestions.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiRoutine.php';
require_once __DIR__ . '/../../tools/SaldiPurchaseTools.php';

final class SaldiReorderSuggestionsRoutine extends SaldiBaseRoutine
{
	public function name(): string
	{
		return 'reorder_suggestions';
	}
	public function permission(): string
	{
		return 'products.read';
	}
	public function titleTextId(): int
	{
		return 6170;
	}
	public function schedule(): string
	{
		return 'weekly';
	}
	/** @return array{routine:string,scheduled_date:string,count:int,rows:list<array>}|null */
	public function run(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): ?array
	{
		$this->cutoff($scheduledDate, 0);
		$data = SaldiPurchaseTools::reorderRows($ctx, $db, [], $scheduledDate, 20);
		if ($data['total'] === 0) {
			return null;
		}
		$rows = array_map(fn(array $row): array => ['label' => $this->label($row['item_no'] . ' ' . $row['text'] . ' (' . $row['suggest_qty'] . ')'), 'screen' => SaldiScreens::link('product', $row['product_id'])], $data['rows']);
		return ['routine' => $this->name(), 'scheduled_date' => $scheduledDate, 'count' => $data['total'], 'rows' => $rows];
	}
}
