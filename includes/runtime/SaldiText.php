<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/runtime/SaldiText.php --- ai/mcp-assistant --- 2026.10.01
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
// Read-only translation lookup, independent of sessions.
//
// 20261001 CDX/LH Created.

declare(strict_types=1);

final class SaldiText
{
	private static ?array $texts = null;
	/** Read tenant override, CSV language column, then fallback; never writes. */
	public static function get(?SaldiDb $tenant, int $textId, int $languageId, string $fallback): string
	{
		$languageId = in_array($languageId, [1, 2, 3], true) ? $languageId : 1;
		if ($tenant && $tenant->hasTable('tekster')) {
			$row = $tenant->one('SELECT tekst FROM tekster WHERE tekst_id = ? AND sprog_id = ? ORDER BY id DESC LIMIT 1', [$textId, $languageId]);
			if ($row && $row['tekst'] !== null && $row['tekst'] !== '') {
				return (string)$row['tekst'];
			}
		}
		if (self::$texts === null) {
			self::$texts = [];
			foreach (file(__DIR__ . '/../../importfiler/tekster.csv', FILE_IGNORE_NEW_LINES) as $line) {
				$columns = explode("\t", $line);
				if (ctype_digit($columns[0])) {
					self::$texts[(int)$columns[0]] = $columns;
				}
			}
		}
		$text = self::$texts[$textId][$languageId] ?? '';
		return $text !== '' ? $text : $fallback;
	}
}
