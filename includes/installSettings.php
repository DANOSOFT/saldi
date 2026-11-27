<?php
// --- includes/installSettings.php --- ver 5.1.1 --- 2026.11.27 ---
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
// 20261127 ASR Created. saldi_install_setting() reads an optional setting of this installation ($allowedLedgers, $sharedWithLive) from includes/connect.php, also where that file is not included yet or was included in a function.

if (!function_exists('saldi_install_setting')) {
	/**
	 * An optional setting of this installation, set in its own includes/connect.php (see doc/installation_settings.md).
	 * The value of the global variable when connect.php has set it; otherwise the literal value connect.php assigns at
	 * its top level (a string, a number, true/false, or a list of strings), read without running the file. Null when
	 * the setting is not there.
	 *
	 * @param string $name the variable name without '$', e.g. 'allowedLedgers'
	 * @return mixed
	 */
	function saldi_install_setting(string $name)
	{
		if (isset($GLOBALS[$name])) {
			return $GLOBALS[$name];
		}
		$file = saldi_install_settings_file();
		return array_key_exists($name, $file) ? $file[$name] : null;
	}

	/**
	 * The literal values includes/connect.php assigns at its top level, read once per request with PHP's tokenizer:
	 * comments are skipped and nothing in the file runs. An assignment inside a block ({ ... }) or with any other value
	 * (a function call, a variable) is left out; a later assignment of the same name wins.
	 *
	 * @param string|null $source the file's source; null reads includes/connect.php (cached)
	 * @return array<string, mixed>
	 */
	function saldi_install_settings_file(?string $source = null): array
	{
		static $cached = null;
		if ($source === null) {
			if ($cached !== null) {
				return $cached;
			}
			$path = __DIR__ . '/connect.php';
			$read = is_file($path) ? @file_get_contents($path) : false;
			$cached = ($read === false) ? array() : saldi_install_settings_file($read);
			return $cached;
		}
		if (!function_exists('token_get_all')) {
			return array();
		}
		$tokens = array();
		foreach (token_get_all($source) as $token) {
			if (!is_array($token) || !in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG, T_INLINE_HTML), true)) {
				$tokens[] = $token;
			}
		}
		$settings = array();
		$depth = 0;
		$count = count($tokens);
		for ($i = 0; $i < $count; $i++) {
			$token = $tokens[$i];
			if ($token === '{' || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
				$depth++;
				continue;
			}
			if ($token === '}') {
				$depth = max(0, $depth - 1);
				continue;
			}
			if ($depth > 0 || !is_array($token) || $token[0] !== T_VARIABLE || ($tokens[$i + 1] ?? null) !== '=') {
				continue;
			}
			// A statement on its own: the token before the variable ends the previous statement or is the file start.
			$before = $tokens[$i - 1] ?? ';';
			if ($before !== ';' && $before !== '{' && $before !== '}') {
				continue;
			}
			$end = $i + 2;
			while ($end < $count && $tokens[$end] !== ';') {
				$end++;
			}
			$value = saldi_install_settings_literal(array_slice($tokens, $i + 2, $end - $i - 2));
			if ($value !== null) {
				$settings[substr($token[1], 1)] = $value[0];
			}
			$i = $end;
		}
		return $settings;
	}

	/**
	 * The value of a literal: a quoted string without variables, a whole number, true or false, or array(...) / [...]
	 * of quoted strings. Null for anything else.
	 *
	 * @param array<int, mixed> $tokens the tokens between '=' and ';'
	 * @return array{0: mixed}|null the value wrapped, or null
	 */
	function saldi_install_settings_literal(array $tokens): ?array
	{
		$string = function ($token) {
			if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
				return null;
			}
			$text = $token[1];
			$inner = substr($text, 1, -1);
			return ($text[0] === "'") ? str_replace(array("\\'", '\\\\'), array("'", '\\'), $inner) : stripcslashes($inner);
		};
		if (count($tokens) === 1) {
			$token = $tokens[0];
			if (is_array($token) && $token[0] === T_LNUMBER) {
				return array((int) $token[1]);
			}
			if (is_array($token) && in_array(strtolower($token[1]), array('true', 'false'), true)) {
				return array(strtolower($token[1]) === 'true');
			}
			$text = $string($token);
			return ($text === null) ? null : array($text);
		}
		$open = $tokens[0] ?? null;
		if (is_array($open) && $open[0] === T_ARRAY && ($tokens[1] ?? null) === '(' && end($tokens) === ')') {
			$items = array_slice($tokens, 2, -1);
		} elseif ($open === '[' && end($tokens) === ']') {
			$items = array_slice($tokens, 1, -1);
		} else {
			return null;
		}
		$list = array();
		$expectValue = true;
		foreach ($items as $item) {
			if ($expectValue) {
				$text = $string($item);
				if ($text === null) {
					return null;
				}
				$list[] = $text;
				$expectValue = false;
			} elseif ($item === ',') {
				$expectValue = true;
			} else {
				return null;
			}
		}
		return array($list);
	}
}
