<?php
// ---- includes/tmpCode.php --- lap 5.0.0 --- 2026.09.29 ---
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
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20260929 Sawaneh Roles stage 2 (Requirements_roles_stage2_EN.md §8.6): brugere.tmp_kode has one
//                  format for its three uses, "<type>|<expire>|<code>", type reset, invite or 2fa.

/**
 * The value to store in brugere.tmp_kode.
 */
function tmp_code_make(string $type, int $expire, string $code): string
{
	return $type . '|' . $expire . '|' . $code;
}

/**
 * Split a stored tmp_kode. Values written before the common format carry no type: the
 * forgotten-password flow wrote "<expire>|<code>" and two-factor login "<code>|<expire>",
 * so the caller says which of the two it would have written.
 *
 * @return array{type: string, expire: int, code: string}|null
 */
function tmp_code_parse($stored, string $legacyType)
{
	$parts = explode('|', (string) $stored);
	if (count($parts) === 3 && in_array($parts[0], array('reset', 'invite', '2fa'), true)) {
		return array('type' => $parts[0], 'expire' => (int) $parts[1], 'code' => $parts[2]);
	}
	if (count($parts) !== 2) {
		return null;
	}
	if ($legacyType === '2fa') {
		return array('type' => '2fa', 'expire' => (int) $parts[1], 'code' => $parts[0]);
	}
	return array('type' => 'reset', 'expire' => (int) $parts[0], 'code' => $parts[1]);
}

/**
 * 'ok' when the stored code is of this type, matches and has not expired; 'expired' when it
 * matches but is too old; else ''.
 */
function tmp_code_check($stored, string $type, string $given): string
{
	$code = tmp_code_parse($stored, $type);
	if (!$code || $code['type'] !== $type || $given === '' || !hash_equals($code['code'], $given)) {
		return '';
	}
	return (time() <= $code['expire']) ? 'ok' : 'expired';
}
