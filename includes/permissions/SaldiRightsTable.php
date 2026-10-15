<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/permissions/SaldiRightsTable.php --- ver 5.0.0 --- 2026.10.08
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
// 20261008 CL/LH Created for the mobile Assist contract.

declare(strict_types=1);

final class SaldiRightsTable
{
	public const LENGTH = 16;
	public const LEGACY_MODULES = [
		'kontoplan' => [0], 'admin' => [1], 'kassekladde' => [2], 'regnskab' => [3],
		'finansrapport' => [4], 'debitorordre' => [5], 'debitorkonti' => [6],
		'kreditorordre' => [7], 'kreditorkonti' => [8], 'varer' => [9], 'enheder' => [10],
		'backup' => [11], 'debitorrapport' => [12], 'kreditorrapport' => [13],
		'produktionsordre' => [14], 'varerapport' => [15],
		'finans' => [0, 2, 3, 4], 'debitor' => [5, 6, 12], 'lager' => [9, 10, 14, 15], 'bilag' => [2],
	];
	public const MODULES = self::LEGACY_MODULES + [
		'assistant' => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
	];
	public const PERMISSIONS = [
		'accounts.read' => [[0, 3, 4], 'read'], 'ledger.read' => [[3, 4], 'read'],
		'journal.read' => [[2], 'read'], 'rules.read' => [[2], 'read'],
		'documents.read' => [[2, 7, 8], 'read'], 'sales.read' => [[5], 'read'],
		'debtors.read' => [[6, 12], 'read'], 'purchases.read' => [[7], 'read'],
		'creditors.read' => [[8, 13], 'read'], 'products.read' => [[9, 15], 'read'],
		'journal.draft' => [[2], 'write'], 'sales.draft' => [[5], 'write'],
		'debtors.draft' => [[6], 'write'], 'purchases.draft' => [[7], 'write'],
		'creditors.draft' => [[8], 'write'], 'settings.admin' => [[1], 'write'],
	];
	public const BASELINE = ['company.read', 'inbox.read', 'memory.read', 'memory.propose', 'followup.draft', 'support.draft'];

	/** Decode only module digits; callers own principal, employee and own rules. */
	public static function digitAllows(string $rights, array $positions, string $action, bool $emptyIsUnrestricted): bool
	{
		if (!in_array($action, ['read', 'write'], true)) {
			return false;
		}
		$rights = trim($rights);
		if ($rights === '') {
			return $emptyIsUnrestricted;
		}
		foreach ($positions as $position) {
			$digit = substr($rights, $position, 1);
			if ($action === 'read' ? in_array($digit, ['1', '2'], true) : $digit === '1') {
				return true;
			}
		}
		return false;
	}
}
