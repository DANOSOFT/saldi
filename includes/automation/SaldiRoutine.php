<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiRoutine.php --- ai/mcp-assistant --- 2026.10.02
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
// SQL-only scheduled findings with exact numeric money boundaries.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Add companion shared foundation contracts.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';

interface SaldiRoutine
{
	public function name(): string;
	public function permission(): string;
	public function titleTextId(): int;
	public function schedule(): string;
	/** @return array{routine:string,scheduled_date:string,count:int,total?:array{amount:string,currency:string,converted:bool},rows:list<array>}|null */
	public function run(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): ?array;
}

abstract class SaldiBaseRoutine implements SaldiRoutine
{
	public function schedule(): string
	{
		return 'daily';
	}
	protected function cutoff(string $date, int $days): string
	{
		$d = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Copenhagen'));
		if (!$d || $d->format('Y-m-d') !== $date) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid scheduled date');
		}
		return $d->modify('-' . $days . ' days')->format('Y-m-d');
	}
	protected function label(string $value): string
	{
		return mb_substr($value === '' ? '(unnamed)' : $value, 0, 500);
	}
	protected function money(string $rounded): string
	{
		// SQL already rounded the exact numeric value half away from zero.
		[$whole, $fraction] = array_pad(explode('.', $rounded, 2), 2, '');
		return $whole . '.' . str_pad(substr($fraction, 0, 2), 2, '0');
	}
}
