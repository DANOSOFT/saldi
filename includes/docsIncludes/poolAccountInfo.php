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
// 20261004 CL/SZ SD-725 The VAT code per side of a pool line: the codes to choose from, the code shown, and the code saved.
//                The rules are the journal's (kassekladde.php): a blank code means the account's own code, unless the line is VAT-free (u/m) or only the other side has a code.

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

if (!function_exists('poolVatCodes')) {
	/**
	 * The VAT codes the journal offers (kassekladde.php): S, K, Y and E codes from grupper, code => description.
	 *
	 * @return array<string, string>
	 */
	function poolVatCodes() {
		static $codes = null;
		if ($codes !== null) return $codes;
		$codes = array();
		$q = db_select("select kode, kodenr, beskrivelse, art from grupper where substring(art,2,1)='M' order by kode, kodenr", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$prefix = strtoupper(trim((string)$r['kode']));
			if (!in_array($prefix, array('S', 'K', 'Y', 'E'), true)) continue;
			$code = $prefix . trim((string)$r['kodenr']);
			if ($code !== $prefix) $codes[$code] = trim((string)$r['beskrivelse']);
		}
		return $codes;
	}
}

if (!function_exists('poolVatColumnsReady')) {
	/**
	 * Whether kassekladde has the journal's debetvat and kreditvat columns (kassekladde.php adds them when the journal opens).
	 *
	 * @return bool
	 */
	function poolVatColumnsReady() {
		global $db_type;
		static $ready = null;
		if ($ready !== null) return $ready;
		$schema = ($db_type == 'mysql' || $db_type == 'mysqli') ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
		$q = db_select("SELECT column_name FROM information_schema.columns WHERE table_name = 'kassekladde' AND column_name IN ('debetvat', 'kreditvat')$schema", __FILE__ . " linje " . __LINE__);
		$n = 0;
		while (db_fetch_array($q)) $n++;
		$ready = $n === 2;
		return $ready;
	}
}

if (!function_exists('poolVatAccountCode')) {
	/**
	 * The account's own VAT code: kontoplan.moms of a finance account in the fiscal year; none for a debitor or kreditor.
	 *
	 * @return string '' when the account has none or isn't a finance account.
	 */
	function poolVatAccountCode($type, $kontonr, $regnaar, array $codes) {
		if ($type !== 'F' || trim((string)$kontonr) === '') return '';
		$info = poolAccountInfo('F', $kontonr, $regnaar);
		return array_key_exists($info['moms'], $codes) ? $info['moms'] : '';
	}
}

if (!function_exists('poolVatChoose')) {
	/**
	 * The VAT code saved for one side, by the journal's rules (resolve_post_vat_code() in kassekladde.php).
	 *
	 * @param string $submitted   The code chosen in the field ('' for none).
	 * @param string $accountCode The account's own code (poolVatAccountCode()).
	 * @param bool   $momsfri     The line is VAT-free (u/m): no code on either side.
	 * @param string $otherSide   The code chosen for the other side; a blank next to a code there is a deliberate choice.
	 * @param array  $codes       poolVatCodes(); an unknown code counts as blank.
	 * @return string
	 */
	function poolVatChoose($submitted, $accountCode, $momsfri, $otherSide, array $codes) {
		if ($momsfri) return '';
		$submitted = trim((string)$submitted);
		if (!array_key_exists($submitted, $codes)) $submitted = '';
		$otherSide = trim((string)$otherSide);
		if ($submitted === '' && !array_key_exists($otherSide, $codes)) return $accountCode;
		return $submitted;
	}
}

if (!function_exists('poolVatShown')) {
	/**
	 * The VAT code a pool line shows for one side: the saved code, else the account's own unless the line is VAT-free.
	 * A line saved before the field existed has no code saved and shows the account's, as the journal does.
	 *
	 * @param string|null $saved kassekladde.debetvat / kreditvat, null when not saved.
	 * @return string
	 */
	function poolVatShown($saved, $accountCode, $momsfri, array $codes) {
		$saved = trim((string)$saved);
		if ($saved !== '' && array_key_exists($saved, $codes)) return $saved;
		return $momsfri ? '' : $accountCode;
	}
}
