<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/datotidTilTekst.php --- ver 5.1.0 --- 2026-11-04 ---
// LICENS
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261104 LOE SST-940 Created, one owner for the datotid formatting the Dagbog and the check sheets share.
//              Those columns are text: a unix timestamp normally, but older data holds a finished date string.
//              date() throws a TypeError on such a value in PHP 8.
//              The Dagbog autocomplete answered 500 and the list stopped halfway through the render.

if (!function_exists('datotidErTidsstempel')) {
	/**
	 * Tells whether a datotid value is a unix timestamp rather than a finished date string.
	 *
	 * @param mixed $vaerdi The column value, as the driver returned it.
	 *
	 * @return bool True when the value is a whole number of seconds.
	 */
	function datotidErTidsstempel($vaerdi)
	{
		// A whole number of seconds, with the minus sign a date before 1970 has; that older range is
		// real data in the check-sheet tables, so it must format rather than print as a raw number.
		// Not is_numeric(): "0.5" and "01-01-1970 01:00" would pass it but are not timestamps.
		return preg_match('/^-?\d+$/', (string)$vaerdi) === 1;
	}
}

if (!function_exists('datotidTilTekst')) {
	/**
	 * Formats a datotid value, or returns it unchanged when it is not a timestamp.
	 *
	 * @param mixed  $vaerdi The column value, normally a unix timestamp as text.
	 * @param string $format A date() format string.
	 *
	 * @return string The formatted date, or the value escaped for HTML when it holds text.
	 */
	function datotidTilTekst($vaerdi, $format = 'd-m-Y')
	{
		if (datotidErTidsstempel($vaerdi)) {
			return date($format, (int)$vaerdi);
		}
		// The value is printed inside the page and inside the autocomplete list, so it is escaped
		// here rather than at twenty call sites; the formatted date needs no escaping.
		return htmlspecialchars((string)$vaerdi, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('datoTekstTilDato')) {
	/**
	 * Reads a posted dd-mm-yyyy date and returns it normalised, or null when it is not one.
	 *
	 * @param mixed $tekst The value the form posted.
	 *
	 * @return string|null "dd-mm-yyyy" for a real calendar date, null for anything else.
	 */
	function datoTekstTilDato($tekst)
	{
		// Exactly three parts: a legacy datotid ("01-01-1970 01:00") has no second dash and a year of
		// "1970 01:00", which checkdate() refuses with a TypeError on PHP 8, and a posted value with a
		// fourth part ("04-11-2026-junk") is not the date the caller meant either. Both, and every other
		// unparsable value, go to the caller's "keep what is stored" branch.
		$dele = explode('-', trim((string)$tekst));
		if (count($dele) !== 3) {
			return NULL;
		}
		if (!ctype_digit($dele[0]) || !ctype_digit($dele[1]) || !ctype_digit($dele[2])) {
			return NULL;
		}
		if (strlen($dele[2]) != 4 || !checkdate((int)$dele[1], (int)$dele[0], (int)$dele[2])) {
			return NULL;
		}
		return $dele[0] . "-" . $dele[1] . "-" . $dele[2];
	}
}

if (!function_exists('datotidTilDatoOgTid')) {
	/**
	 * Formats a datotid value as the "date kl. time" cell the lists print.
	 *
	 * @param mixed  $vaerdi  The column value, normally a unix timestamp as text.
	 * @param string $klTekst The word between the date and the time, already translated.
	 *
	 * @return string "d-m-y kl. H:i" for a timestamp, the value escaped for HTML for text.
	 */
	function datotidTilDatoOgTid($vaerdi, $klTekst = 'kl.')
	{
		if (!datotidErTidsstempel($vaerdi)) {
			return htmlspecialchars((string)$vaerdi, ENT_QUOTES, 'UTF-8');
		}
		return date("d-m-y", (int)$vaerdi) . " " . $klTekst . " " . date("H:i", (int)$vaerdi);
	}
}
