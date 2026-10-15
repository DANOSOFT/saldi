<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/routines/SaldiMasterDataHygieneRoutine.php --- ai/mcp-assistant --- 2026.10.04
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
// Weekly read-only master-data findings using the same checks as the insight tool.
//
// 20261004 CDX/LH Created.
// 20261004 CDX/LH Implement shared read-only hygiene findings.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiRoutine.php';
require_once __DIR__ . '/../SaldiMasterDataHygiene.php';

final class SaldiMasterDataHygieneRoutine extends SaldiBaseRoutine
{
	public function name(): string
	{
		return 'master_data_hygiene';
	}
	public function permission(): string
	{
		return 'company.read';
	}
	public function titleTextId(): int
	{
		return 6171;
	}
	public function schedule(): string
	{
		return 'weekly';
	}
	/** @return array{routine:string,scheduled_date:string,count:int,rows:list<array>}|null */
	public function run(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): ?array
	{
		$this->cutoff($scheduledDate, 0);
		SaldiPermissionService::require($ctx, $db, $this->permission());
		$findings = SaldiMasterDataHygiene::scan($ctx, $db, 20);
		return $findings['total'] === 0 ? null : ['routine' => $this->name(), 'scheduled_date' => $scheduledDate, 'count' => $findings['total'], 'rows' => $findings['rows']];
	}
}
