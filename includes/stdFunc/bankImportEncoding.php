<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/bankImportEncoding.php --- ver 5.0.0 --- 2026.10.06 ---
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
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261006 CL/LH SST-838: Created. Shared decoding of uploaded bank files for bankimport.php and the bankReconcile readers.

/**
 * Decodes one line of an uploaded bank file to the tenant's charset.
 *
 * Bank files arrive as UTF-8, Windows-1252 or ISO-8859-1, and one file, or even one line, can mix them
 * (e.g. a Windows-1252 export with a single UTF-8 sender name). The line is therefore decoded byte by byte:
 * valid UTF-8 sequences are kept as they are, and every other byte from 0x80 to 0xFF is read as Windows-1252,
 * so æøå, € (0x80), dashes and typographic quotes survive. A leading UTF-8 byte order mark is removed.
 *
 * @param string $line    One raw line from the bank file, with or without its line ending.
 * @param string $charset Tenant charset from includes/online.php: 'UTF-8' for UTF8 databases, anything else for LATIN9 databases.
 * @return string The line as UTF-8 when $charset is 'UTF-8', otherwise as ISO-8859-15.
 */
function bank_import_decode_line(string $line, string $charset): string
{
	if (strncmp($line, "\xEF\xBB\xBF", 3) === 0) $line = substr($line, 3);
	$line = preg_replace_callback(
		'/[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]'
		. '|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}|[\x80-\xFF]/',
		function (array $match): string {
			if (strlen($match[0]) > 1) return $match[0];
			return mb_convert_encoding($match[0], 'UTF-8', 'Windows-1252');
		},
		$line
	);
	if ($charset == 'UTF-8') return $line;
	return mb_convert_encoding($line, 'ISO-8859-15', 'UTF-8');
}

/**
 * Reads an uploaded bank file and decodes every line with bank_import_decode_line().
 *
 * A file that starts with a UTF-16 byte order mark is converted to UTF-8 as a whole before it is split into
 * lines, because splitting UTF-16 on the byte 0x0A (as fgets() does) cuts characters in half.
 * Like fgets() with auto_detect_line_endings, "\r\n", "\n" and a lone "\r" all end a line, and each line keeps its line ending.
 *
 * @param string $filename Path to the uploaded bank file.
 * @param string $charset  Tenant charset, see bank_import_decode_line().
 * @return array<int, string>|false The decoded lines in file order, or false when the file cannot be read.
 */
function bank_import_read_lines(string $filename, string $charset)
{
	$content = file_get_contents($filename);
	if ($content === false) return false;
	if (strncmp($content, "\xFF\xFE", 2) === 0) {
		$content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
	} elseif (strncmp($content, "\xFE\xFF", 2) === 0) {
		$content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
	}
	preg_match_all('/[^\r\n]*(?:\r\n|\n|\r)|[^\r\n]+\z/', $content, $matches);
	$lines = array();
	foreach ($matches[0] as $line) {
		$lines[] = bank_import_decode_line($line, $charset);
	}
	return $lines;
}
