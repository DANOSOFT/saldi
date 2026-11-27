<?php
// ---- includes/tmpCode.php --- ver 5.1.1 --- 2026.11.27 ---
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
// Copyright (c) 2026-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260929 SS Roles stage 2 (Requirements_roles_stage2_EN.md §8.6): brugere.tmp_kode has one format for its three uses, "<type>|<expire>|<code>", type reset, invite or 2fa.
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
// 20261127 ASR brugere.tmp_kode is written in two forms: a temporary password as "<expire>|<code>" (the form every Saldi version reads), and an invitation or any other type as "0|0|<type>|<expire>|<code>".
//              Read as "<expire>|<code>", the second form is expired, and so is its second field. The form "<type>|<expire>|<code>" written before is still read, and tmp_code_storage_form() turns it into the current form.
//              tmp_code_new_password() makes the temporary password; it is never a numeric string, so it only equals itself.
// 20261127 ASR A temporary password starts with '0', so read as "<code>|<expire>" its second field is a time that has passed.
//              One in the form "<type>|<expire>|<code>" written before is rewritten to "0|0|reset|<expire>|<code>", as its code does not start with '0'.
/**
 * The types a stored value can carry.
 *
 * @return array<int, string>
 */
function tmp_code_types(): array
{
	return array('reset', 'invite', '2fa');
}

/**
 * The value to store in brugere.tmp_kode. A temporary password (type reset) is "<expire>|<code>", the form every
 * Saldi version reads as a temporary password. Every other type is "0|0|<type>|<expire>|<code>": read as
 * "<expire>|<code>" it is expired (0), and so is its second field, so it is never taken for a temporary password.
 * The code never contains '|' (a random password or a hex hash).
 */
function tmp_code_make(string $type, int $expire, string $code): string
{
	if ($type === 'reset') {
		return $expire . '|' . $code;
	}
	return '0|0|' . $type . '|' . $expire . '|' . $code;
}

/**
 * A temporary password of ten characters: '0', then nine from the system's random source, without the characters that
 * are easy to mix up when typed from a mail (0 O, 1 l I; the first '0' is the only zero, and O is never in it).
 * Stored as "<expire>|<code>", the value read the other way round, as "<code>|<expire>", has a second field that
 * compares below any time, so it is expired in that form too. It is never a numeric string ("0234567e89" would be one),
 * so a login that compares it with == accepts this exact text only.
 */
function tmp_code_new_password(): string
{
	$alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	do {
		$code = '0';
		for ($i = 0; $i < 9; $i++) {
			$code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
		}
	} while (is_numeric($code));
	return $code;
}

/**
 * Split a stored tmp_kode. Values written before the common format carry no type: the
 * forgotten-password flow wrote "<expire>|<code>" and two-factor login "<code>|<expire>",
 * so the caller says which of the two it would have written. "<type>|<expire>|<code>" is
 * the form written before tmp_code_make() wrote the current forms; it is still read.
 *
 * @return array{type: string, expire: int, code: string}|null
 */
function tmp_code_parse($stored, string $legacyType)
{
	$parts = explode('|', (string) $stored);
	if (count($parts) === 5 && $parts[0] === '0' && $parts[1] === '0' && in_array($parts[2], tmp_code_types(), true)) {
		return array('type' => $parts[2], 'expire' => (int) $parts[3], 'code' => $parts[4]);
	}
	if (count($parts) === 3 && in_array($parts[0], tmp_code_types(), true)) {
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

/**
 * True when a stored value is an invitation (current or earlier form).
 */
function tmp_code_is_invite($stored): bool
{
	$code = tmp_code_parse($stored, 'reset');
	return $code !== null && $code['type'] === 'invite';
}

/**
 * The current form of a stored value (tmp_code_make()). A value in the form "<type>|<expire>|<code>" is rewritten:
 * an invitation keeps its expiry and hash as "0|0|invite|<expire>|<hash>"; a temporary password keeps its expiry and
 * code as "0|0|reset|<expire>|<code>", which this code accepts until it expires and every "<expire>|<code>" or
 * "<code>|<expire>" reading takes as expired (its code was made before temporary passwords started with '0', see
 * tmp_code_new_password()); a two-factor code is cleared (null), as the two-factor code is kept in the ledger's settings
 * (includes/twoFactor.php) and this one is not read. A value in that form without a numeric expiry or a code is
 * cleared too. Any other value is returned as it is.
 *
 * @return string|null the value to store, null to clear the field
 */
function tmp_code_storage_form($stored)
{
	if ($stored === null) {
		return null;
	}
	$stored = (string) $stored;
	$parts = explode('|', $stored);
	if (count($parts) < 2 || !in_array($parts[0], tmp_code_types(), true)) {
		return $stored;
	}
	if (count($parts) !== 3 || $parts[0] === '2fa' || !ctype_digit($parts[1]) || $parts[2] === '') {
		return null;
	}
	return '0|0|' . $parts[0] . '|' . (int) $parts[1] . '|' . $parts[2];
}
