<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/findTxtUtf8.php --- ver 5.0.0 --- 2026-10-02 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261002 CL/NTR Created from autoudlign.php's local helper so any page can get UTF-8 text from findtekst().
//                 Accepts a string, a list or a keyed array of text ids.

if (!function_exists('findtekst_utf8')) {
	/**
	 * findtekst() that always returns UTF-8, for output that requires it (json_encode(), htmlspecialchars(..., 'UTF-8')).
	 *
	 * findtekst() returns text in the database encoding, which is ISO-8859-1 compatible when $db_encode isn't 'UTF8'.
	 * json_encode() returns false and htmlspecialchars(..., 'UTF-8') returns '' for invalid UTF-8 input.
	 *
	 * Accepts a single text id, a list of text ids, or a keyed array of text ids. In a keyed array a purely numeric
	 * value gets '|' . $key appended before the lookup (so the key becomes findtekst()'s fallback text); any other
	 * value is passed on unchanged. Arrays are returned with their keys preserved.
	 *
	 * @param string|array<int, string>|array<string, string> $textId Text id (or 'id|fallback text'), a list of them, or a keyed array of them.
	 * @param int $languageID Language id ($sprog_id).
	 * @return string|array<int, string>|array<string, string> The text as UTF-8; an array in the same shape as $textId when one was given.
	 */
	function findtekst_utf8($textId, $languageID) {
		global $db_encode;
		if (is_array($textId)) {
			$isList = array_is_list($textId);
			$texts = array();
			foreach ($textId as $key => $value) {
				if (!$isList && ctype_digit((string)$value)) {
					$value = $value . '|' . $key;
				}
				$texts[$key] = findtekst_utf8($value, $languageID);
			}
			return $texts;
		}
		$text = findtekst($textId, $languageID);
		if ($db_encode != 'UTF8') {
			$text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
		}
		return $text;
	}
}
