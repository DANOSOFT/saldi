<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/routines/SaldiVatMissingDocumentsRoutine.php --- ai/mcp-assistant --- 2026.10.02
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
// Find missing VAT attachments once per kladde/bilag before the indicative deadline.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Implement bilag findings and deadline-based requested attention.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiRoutine.php';
require_once __DIR__ . '/../SaldiDailyOverview.php';

final class SaldiVatMissingDocumentsRoutine extends SaldiBaseRoutine
{
	public function name(): string
	{
		return 'vat_missing_documents';
	}
	public function permission(): string
	{
		return 'journal.read';
	}
	public function titleTextId(): int
	{
		return 5940;
	}
	public function schedule(): string
	{
		return 'daily';
	}
	/** @return array{routine:string,scheduled_date:string,count:int,total?:array{amount:string,currency:string,converted:bool},rows:list<array>}|null */
	public function run(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): ?array
	{
		$this->cutoff($scheduledDate, 0);
		$period = SaldiVatPeriod::current(SaldiSettings::get($db, 'assistant', 'vat_period', 'quarter'), $scheduledDate);
		$missing = SaldiDailyOverview::missingDocuments($ctx, $db, $period['from'], $period['to'], true);
		if ($missing['count'] === 0) {
			return null;
		}
		$rows = array_map(static function (array $row): array {
			unset($row['journal_id'], $row['bilag']);
			return $row;
		}, $missing['rows']);
		return ['routine' => $this->name(), 'scheduled_date' => $scheduledDate, 'count' => $missing['count'], 'rows' => $rows, 'attention' => $period['days_left'] <= 7 ? 'interrupt' : 'ambient'];
	}
}
