<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiJournalCheckTools.php --- ai/mcp-assistant --- 2026.10.04
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
// Permission-checked read-only journal checks and journal citations.
//
// 20261004 CDX/LH Created.
// 20261004 CDX/LH Implement journal check tool.

declare(strict_types=1);

require_once __DIR__ . '/../automation/SaldiJournalCheck.php';

final class SaldiJournalCheckTools
{
	/** @return array{data:array{journal_id:int,status:string,ready:bool,errors:list<array>,warnings:list<array>},citations:list<array{id:string,label:string,screen?:string}>,possibly_incomplete:bool,result_count:int} */
	public static function check(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		$id = ifset($args, 'journal_id');
		if (!is_int($id) || $id < 1) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid journal id');
		}
		$data = SaldiJournalCheck::run($ctx, $db, $id);
		return SaldiToolRuntime::result($data, [SaldiToolRuntime::citation('journal', $id, SaldiText::get($db, 1087, $ctx->languageId, 'Kladde') . ' ' . $id, 'journal')]);
	}
}
