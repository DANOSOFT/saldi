<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/runtime/SaldiScreens.php --- ai/mcp-assistant --- 2026.10.04
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
// Typed links without action parameters.
// Verified: pulje_review.php renders the pool; rykker.php reads GET rykker_id
// and redirects a bare request to rapport.php?rapportart=openpost.
// rapport.php and moms_periode.php exist; assistent_rapport.php is reserved for C8.4.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Add companion screens; use the verified openpost overview instead of the redirecting bare rykker.php.
// 20261004 CDX/LH Add companion part 2 foundation contracts.

declare(strict_types=1);

final class SaldiScreens
{
	public const SCREENS = [
		'journal' => ['finans/kassekladde.php', 'kladde_id'], 'journal_list' => ['finans/kladdeliste.php', null],
		'sales_order' => ['debitor/ordre.php', 'id'], 'purchase_order' => ['kreditor/ordre.php', 'id'],
		'debtor' => ['debitor/debitorkort.php', 'id'], 'creditor' => ['kreditor/kreditorkort.php', 'id'],
		'document_pool' => ['finans/pulje_review.php', null], 'reminders' => ['debitor/rapport.php?rapportart=openpost', null],
		'reminder' => ['debitor/rykker.php', 'rykker_id'],
		'assist_settings' => ['systemdata/assistent.php', null], 'assist_report' => ['systemdata/assistent_rapport.php', null],
		'vat_report' => ['finans/rapport.php', null], 'vat_periods' => ['finans/moms_periode.php', null],
		'sales_offer' => ['debitor/ordre.php', 'id'], 'stock_count' => ['lager/optalling.php', null],
		// Reserved pages: the companion part 2 UI builder creates both files.
		'bank_import' => ['finans/assist_bankfil.php', null], 'year_end_pack' => ['systemdata/revisorpakke.php', null],
		'product' => ['lager/varekort.php', 'id'], 'inbox_item' => ['inbox/index.php', 'item'],
	];
	/** Generate one typed, action-free navigation link. */
	public static function link(string $type, ?int $id = null): string
	{
		if (!isset(self::SCREENS[$type])) {
			throw new InvalidArgumentException('Unknown screen reference');
		}
		[$path, $param] = self::SCREENS[$type];
		if ($param === null) {
			return $path;
		}
		if ($id === null || $id < 0 || strlen((string)$id) > 10) {
			throw new InvalidArgumentException('Invalid screen id');
		}
		return $path . '?' . $param . '=' . $id;
	}
	/** Accept only the exact page/parameter allowlist. */
	public static function isAllowed(string $screen): bool
	{
		foreach (self::SCREENS as [$path, $param]) {
			if ($param === null ? $screen === $path : preg_match('/^' . preg_quote($path . '?' . $param . '=', '/') . '[0-9]{1,10}$/D', $screen)) {
				return true;
			}
		}
		return false;
	}
}
