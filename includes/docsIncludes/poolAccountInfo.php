<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/poolAccountInfo.php --- ver 5.0.0 --- 2026-10-03 ---
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
// 20261003 CL/SZ SD-714 Created: account type + number for the pool's Debet/Kredit fields, and the name and VAT code shown under them.

if (!function_exists('poolAccountSplit')) {
	/**
	 * Splits a Debet/Kredit value into account type and number.
	 *
	 * A value with a type prefix ("K1234", as saved and suggested by the pool) wins over the separate type.
	 * Otherwise the stored type is used, and when that is empty or unknown, $defaultType.
	 *
	 * @param string|int|null $value       Field value, e.g. "1234", "K1234" or "".
	 * @param string|null     $type        Stored type (kassekladde.d_type / k_type), may be empty.
	 * @param string          $defaultType 'F', 'D' or 'K' when nothing else gives the type.
	 * @return array{0: string, 1: string} [type, number]; number is '' for an empty or zero account.
	 */
	function poolAccountSplit($value, $type, $defaultType = 'F') {
		$value = is_scalar($value) ? trim((string)$value) : '';
		$type = is_scalar($type) ? strtoupper(trim((string)$type)) : '';
		if (preg_match('/^([DKF])(\d+)$/i', $value, $m)) {
			$type = strtoupper($m[1]);
			$value = $m[2];
		}
		if (!in_array($type, array('F', 'D', 'K'), true)) {
			$type = in_array($defaultType, array('F', 'D', 'K'), true) ? $defaultType : 'F';
		}
		if ($value === '0') $value = '';
		return array($type, $value);
	}
}

if (!function_exists('poolAccountInfo')) {
	/**
	 * Name and VAT code of a finance account, debitor or kreditor.
	 *
	 * @param string     $type    'F' (kontoplan), 'D' (debitor) or 'K' (kreditor).
	 * @param string|int $kontonr Account number.
	 * @param int        $regnaar Fiscal year the finance account is looked up in.
	 * @return array{name: string, moms: string} Empty strings when the account doesn't exist.
	 */
	function poolAccountInfo($type, $kontonr, $regnaar) {
		$info = array('name' => '', 'moms' => '');
		$kontonr = is_scalar($kontonr) ? trim((string)$kontonr) : '';
		if (!ctype_digit($kontonr) || !in_array($type, array('F', 'D', 'K'), true)) {
			return $info;
		}
		$kontonrSql = db_escape_string($kontonr);
		if ($type == 'F') {
			$qtxt = "select beskrivelse, moms from kontoplan where kontonr = '$kontonrSql' and regnskabsaar = '" . (int)$regnaar . "'";
			$qtxt .= " and (kontotype = 'D' or kontotype = 'S')";
			if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				$info['name'] = trim(stripslashes((string)$r['beskrivelse']));
				$info['moms'] = trim((string)$r['moms']);
			}
		} else {
			$qtxt = "select firmanavn from adresser where kontonr = '$kontonrSql' and art = '$type' order by id limit 1";
			if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				$info['name'] = trim(stripslashes((string)$r['firmanavn']));
			}
		}
		return $info;
	}
}
