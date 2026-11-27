<?php
// ---- includes/restoreDumpCheck.php --- ver 5.1.1 --- 2026.11.27 ---
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
// 20261127 ASR Created: the line check of a dump that admin/restore.php restores (restore()), wherever on the line a word stands.
//              Every dump: DROP DATABASE and CREATE DATABASE are refused, and \connect in a MySQL dump.
//              A PostgreSQL dump is read as psql reads it: a psql command (a backslash outside a quoted string, a dollar-quoted body or COPY data) is refused, \c and \connect too, except the \restrict and \unrestrict lines pg_dump writes itself.
//              A COPY data line may hold only COPY's own backslash sequences, and the dump keeps standard_conforming_strings on.

if (!function_exists('restore_dump_check_start')) {
	/**
	 * The state restore_dump_check_line() carries from one line of a dump to the next.
	 *
	 * @param bool $psql whether the dump is read by psql (a PostgreSQL dump)
	 * @return array<string, mixed>
	 */
	function restore_dump_check_start(bool $psql): array
	{
		return array('psql' => $psql, 'copy' => false, 'mode' => '', 'tag' => '', 'depth' => 0, 'line' => 0);
	}

	/**
	 * Check one line of a dump, in order, with the state of the lines before it (restore_dump_check_start()).
	 *
	 * In a PostgreSQL dump the line is read as psql reads it: outside a quoted string ('...', E'...', "..."), a
	 * dollar-quoted body ($tag$...$tag$) and the data of COPY ... FROM stdin, a backslash starts a psql command. Every
	 * such command is refused, wherever it stands on the line, except the "\restrict <key>" and "\unrestrict <key>"
	 * lines pg_dump writes around a dump. A backslash in a comment is refused too. A COPY data line may hold only COPY's
	 * own sequences (\\, \N, \b, \f, \n, \r, \t, \v, octal and \x), and it ends at a line of only "\.". A change of
	 * standard_conforming_strings, under which psql would read strings in another way, is refused.
	 *
	 * @param string $line one line as fgets() reads it, with its line end
	 * @param array<string, mixed> $state
	 * @return string '' when the line may be restored, otherwise why not (for the server log)
	 */
	function restore_dump_check_line(string $line, array &$state): string
	{
		$state['line']++;
		$lower = strtolower($line);
		if (strpos($lower, 'drop database') !== false) {
			return 'DROP DATABASE';
		}
		if (strpos($lower, 'create database') !== false) {
			return 'CREATE DATABASE';
		}
		if (empty($state['psql'])) {
			return strpos($lower, '\\connect') !== false ? '\\connect' : '';
		}
		$text = rtrim($line, "\r\n");
		if ($state['copy']) {
			if ($text === '\\.') {
				$state['copy'] = false;
				return '';
			}
			$length = strlen($text);
			for ($i = 0; $i < $length; $i++) {
				if ($text[$i] !== '\\') {
					continue;
				}
				$next = $i + 1 < $length ? $text[$i + 1] : '';
				if ($next === '' || strpos('\\Nbfnrtv01234567x', $next) === false) {
					return 'a backslash in COPY data that COPY does not write';
				}
				$i++;
			}
			return '';
		}
		if ($state['mode'] === '' && preg_match('/^\\\\(un)?restrict [A-Za-z0-9]+[ \t]*$/', $text)) {
			return '';
		}
		$startsAtTop = $state['mode'] === '';
		if (stripos($text, 'standard_conforming_strings') !== false && !preg_match('/^SET standard_conforming_strings = on;$/', $text)) {
			return 'standard_conforming_strings is changed';
		}
		$length = strlen($text);
		for ($i = 0; $i < $length; $i++) {
			$c = $text[$i];
			$next = $i + 1 < $length ? $text[$i + 1] : '';
			switch ($state['mode']) {
				case '':
					if ($c === '\\') {
						return 'a psql command';
					}
					if ($c === '-' && $next === '-') {
						return strpos($text, '\\', $i) !== false ? 'a backslash in a comment' : '';
					}
					if ($c === '/' && $next === '*') {
						$state['mode'] = '/*';
						$state['depth'] = 1;
						$i++;
					} elseif ($c === "'") {
						$before = $i > 0 ? $text[$i - 1] : '';
						$beforeThat = $i > 1 ? $text[$i - 2] : '';
						$state['mode'] = (($before === 'E' || $before === 'e') && !restore_dump_ident_char($beforeThat)) ? 'E' : "'";
					} elseif ($c === '"') {
						$state['mode'] = '"';
					} elseif ($c === '$' && !restore_dump_ident_char($i > 0 ? $text[$i - 1] : '')
						&& preg_match('/\G\$(?:[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)?\$/', $text, $m, 0, $i)) {
						$state['mode'] = '$';
						$state['tag'] = $m[0];
						$i += strlen($m[0]) - 1;
					}
					break;
				case "'":
					if ($c === "'") {
						if ($next === "'") {
							$i++;
						} else {
							$state['mode'] = '';
						}
					}
					break;
				case 'E':
					if ($c === '\\') {
						$i++;
					} elseif ($c === "'") {
						if ($next === "'") {
							$i++;
						} else {
							$state['mode'] = '';
						}
					}
					break;
				case '"':
					if ($c === '"') {
						if ($next === '"') {
							$i++;
						} else {
							$state['mode'] = '';
						}
					}
					break;
				case '$':
					if ($c === '$' && substr($text, $i, strlen($state['tag'])) === $state['tag']) {
						$state['mode'] = '';
						$i += strlen($state['tag']) - 1;
					}
					break;
				case '/*':
					if ($c === '\\') {
						return 'a backslash in a comment';
					}
					if ($c === '/' && $next === '*') {
						$state['depth']++;
						$i++;
					} elseif ($c === '*' && $next === '/') {
						$state['depth']--;
						$i++;
						if ($state['depth'] === 0) {
							$state['mode'] = '';
						}
					}
					break;
			}
		}
		// pg_dump's COPY ... FROM stdin; on a line of its own: the lines after it are data up to "\."
		if ($startsAtTop && $state['mode'] === '' && preg_match('/^COPY\s.*\sFROM\s+stdin\s*;\s*$/i', $text)) {
			$state['copy'] = true;
		}
		return '';
	}

	/** Whether the character continues a word as psql reads one (a letter, a digit, _ or $, or a byte of a multibyte character). */
	function restore_dump_ident_char(string $c): bool
	{
		return $c !== '' && (ctype_alnum($c) || $c === '_' || $c === '$' || ord($c) >= 0x80);
	}

	/**
	 * Whether a dump ended where it may be restored: not inside a quoted string, a dollar-quoted body, a comment or COPY
	 * data, where psql would read what is left in another way.
	 *
	 * @param array<string, mixed> $state the state after the last line
	 */
	function restore_dump_check_end(array $state): string
	{
		if (empty($state['psql'])) {
			return '';
		}
		if (!empty($state['copy'])) {
			return 'the dump ends inside COPY data';
		}
		return $state['mode'] === '' ? '' : 'the dump ends inside a quoted string or a comment';
	}
}
