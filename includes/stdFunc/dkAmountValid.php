<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/dkAmountValid.php --- lap 5.0.0 --- 2026-09-02 ---
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
// Copyright (c) 2003-2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20260902 CL/LH  usdecimal() strips every "." as a thousands separator, so an operator who
//                 types "1234.56" gets 123456,00 posted (L4 finding adversarial-numbers DEVY-2).
//                 Validate operator-typed amounts BEFORE handing them to usdecimal(): a dot is
//                 only accepted as a thousands separator (groups of exactly three digits) and
//                 the decimal separator must be a comma.

if (!function_exists('dk_amount_is_valid')) {
	/**
	 * True when $amount is an unambiguous Danish-formatted number or empty.
	 *
	 * Accepted:  "1234"  "1234,5"  "1.234,56"  "1.234.567,89"  "-17,89"  "+5"  ""  " 12,3 "
	 * Rejected:  "1234.56"  "12.34"  "1,234.56"  "1.23.4"  ",5"  "1.2345"  "abc"
	 *
	 * @param string|int|float $amount
	 * @return bool
	 */
	function dk_amount_is_valid($amount) {
		if ($amount === null) return true;
		$s = trim((string)$amount);
		if ($s === '') return true;
		// Plain digits with optional comma decimals, or dot-grouped thousands (exactly 3 digits per group).
		return (bool)preg_match('/^[-+]?(\d+|\d{1,3}(\.\d{3})+)(,\d+)?$/', $s);
	}
}
?>
