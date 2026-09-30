<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/jsString.php --- patch 5.0.0 --- 2026-09-30 ---
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
// Copyright (c) 2026 Danosoft.ApS
// ----------------------------------------------------------------------
// 20260930 CL/NTR Created from serialnumber.php so other pages can replace addslashes() in JavaScript strings.

if (!function_exists('jsString')) {
	/**
	 * Turns text into a quoted JavaScript string literal, including the surrounding double quotes.
	 *
	 * Legacy (non-UTF8) databases return ISO-8859 text, which json_encode() rejects, so each part is
	 * converted to UTF-8 on its own before the parts are concatenated. Non-ASCII characters are
	 * emitted as \uXXXX escapes, so the output is ASCII and valid in either page charset.
	 * < > & ' " are escaped too, so the literal cannot break out of a <script> block. Inside an
	 * HTML attribute (e.g. onclick="...") also wrap the result in htmlspecialchars($js, ENT_QUOTES, 'UTF-8').
	 *
	 * @param string[] $parts Text pieces, concatenated in order (translations, user data, separators).
	 * @return string The JavaScript string literal.
	 */
	function jsString(array $parts) {
		global $db_encode;
		$text = "";
		foreach ($parts as $part) {
			$text .= ($db_encode == "UTF8") ? $part : mb_convert_encoding($part, "UTF-8", "ISO-8859-15");
		}
		return json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	}
}
