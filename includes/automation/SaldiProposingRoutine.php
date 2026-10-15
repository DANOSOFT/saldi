<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiProposingRoutine.php --- ai/mcp-assistant --- 2026.10.02
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
// Optional SQL routine interface for approval-gated posting rule proposals.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/SaldiRoutine.php';

interface SaldiProposingRoutine
{
	/** @return list<array{match:array{type:string,value:string},account:string,vat_code?:string,description?:string,replaces_rule_id?:int,explanation?:string,dedupe_key?:string}> */
	public function proposals(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): array;
}
