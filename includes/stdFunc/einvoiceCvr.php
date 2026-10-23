<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/einvoiceCvr.php --- ver 5.0.0 --- 2026-10-23 ---
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
// 20261023 CL/MMR SD-774 Created. One place decides when a digital invoice to an EAN/GLN needs the
//                 receiver's CVR, so the send block in debitor/api.php, the popup on the order and the
//                 warning on the debitor card always agree.

include_once(__DIR__ . '/countryCode.php');

if (!function_exists('einvoice_normalize_cvr')) {
	/**
	 * A CVR field as it is compared and saved: no whitespace, upper case, and a Danish number
	 * without its optional "DK" prefix ("DK 1234 5678" becomes "12345678").
	 *
	 * Foreign numbers keep their country prefix ("se232100025501" becomes "SE232100025501"), because
	 * debitor/api.php sendInvoice() reads the prefix to tell a foreign number from a Danish one.
	 *
	 * @param string|null $raw The CVR field as typed or stored.
	 * @return string The normalised value, '' when nothing is left.
	 */
	function einvoice_normalize_cvr($raw) {
		$cvr = strtoupper(preg_replace('/\s+/', '', (string)$raw));
		if (preg_match('/^DK([0-9]{8})$/', $cvr, $m)) {
			$cvr = $m[1];
		}
		return $cvr;
	}
}

if (!function_exists('einvoice_cvr_mod11_ok')) {
	/**
	 * Danish CVR check digit: the digits weighted 2,7,6,5,4,3,2,1 must sum to a multiple of 11.
	 *
	 * @param string $cvr Exactly 8 digits; anything else is not a Danish CVR and fails.
	 * @return bool
	 */
	function einvoice_cvr_mod11_ok($cvr) {
		$cvr = (string)$cvr;
		if (!preg_match('/^[0-9]{8}$/', $cvr)) {
			return false;
		}
		$weights = array(2, 7, 6, 5, 4, 3, 2, 1);
		$sum = 0;
		for ($i = 0; $i < 8; $i++) {
			$sum += (int)$cvr[$i] * $weights[$i];
		}
		return ($sum % 11) === 0;
	}
}

if (!function_exists('einvoice_cvr_input_problem')) {
	/**
	 * Checks a CVR the user types now (popup on the order, debitor card). Stricter than the send
	 * block on purpose: some stored numbers fail modulus 11 and were still accepted by the
	 * receiver (MEDSHOP, 2026), so only new input is held to the check digit.
	 *
	 * A Danish number is 8 digits with a valid check digit, optionally written with "DK" and spaces.
	 * A foreign number starts with a two-letter country code followed by at least two letters or
	 * digits, e.g. SE232100025501.
	 *
	 * @param string|null $raw The typed value.
	 * @return string '' when valid, 'missing' when empty, 'invalid' otherwise.
	 */
	function einvoice_cvr_input_problem($raw) {
		$cvr = einvoice_normalize_cvr($raw);
		if ($cvr === '') {
			return 'missing';
		}
		if (preg_match('/^[0-9]{8}$/', $cvr)) {
			return einvoice_cvr_mod11_ok($cvr) ? '' : 'invalid';
		}
		if (preg_match('/^[A-Z]{2}[A-Z0-9]{2,18}$/', $cvr) && substr($cvr, 0, 2) !== 'DK') {
			return '';
		}
		return 'invalid';
	}
}

if (!function_exists('einvoice_is_gln_endpoint')) {
	/**
	 * Whether sendInvoice() routes this EAN field as a GLN, mirroring its branches: a plain
	 * EAN (no colon) or a "scheme:value" with a non-numeric scheme such as "GLN:5798000000000".
	 * A numeric scheme ("0184:12345678", "0007:...") already carries the party's id and is not
	 * a GLN endpoint for this purpose.
	 *
	 * @param string|null $ean The EAN field (order, or the card when the order has none).
	 * @return bool
	 */
	function einvoice_is_gln_endpoint($ean) {
		$ean = trim((string)$ean);
		if ($ean === '') {
			return false;
		}
		if (strpos($ean, ':') === false) {
			return true;
		}
		$scheme = trim(explode(':', $ean, 2)[0]);
		return !is_numeric($scheme);
	}
}

if (!function_exists('einvoice_receiver_country')) {
	/**
	 * The receiver's country exactly as sendInvoice() derives it: the country field, then the
	 * prefix of the CVR, then DK; Greenland and the Faroe Islands count as DK.
	 *
	 * @param string|null $land The order's (or card's) country field.
	 * @param string|null $cvr  The CVR field, raw or normalised.
	 * @return string Upper-case ISO 3166-1 alpha-2 code.
	 */
	function einvoice_receiver_country($land, $cvr) {
		$cvr = einvoice_normalize_cvr($cvr);
		if ($cvr === '') {
			$cvrCountry = '';
		} elseif (preg_match('/^[0-9]/', $cvr)) {
			$cvrCountry = 'DK';
		} else {
			$cvrCountry = substr($cvr, 0, 2);
		}
		if ($cvrCountry === 'GL' || $cvrCountry === 'FO') {
			$cvrCountry = 'DK';
		}
		$country = countryCodeIso2((string)$land, $cvrCountry);
		if ($country === '') {
			$country = 'DK';
		}
		if ($country === 'GL' || $country === 'FO') {
			$country = 'DK';
		}
		return $country;
	}
}

if (!function_exists('einvoice_cvr_missing')) {
	/**
	 * SD-774: a digital invoice that goes to a GLN of a Danish receiver must carry the receiver's
	 * CVR, or the receiver rejects it (DK-R-017 / E-APS24003). True when exactly that is missing.
	 *
	 * Pass the values sendInvoice() will use: the order's EAN/CVR, each falling back to the
	 * debitor card when empty on the order (SST-884). Foreign receivers are not covered: the rule
	 * is Danish, and they are reached on their own identifiers.
	 *
	 * @param string|null $ean  Effective EAN field.
	 * @param string|null $cvr  Effective CVR field.
	 * @param string|null $land The order's country field.
	 * @return bool
	 */
	function einvoice_cvr_missing($ean, $cvr, $land) {
		if (!einvoice_is_gln_endpoint($ean)) {
			return false;
		}
		if (einvoice_normalize_cvr($cvr) !== '') {
			return false;
		}
		return einvoice_receiver_country($land, '') === 'DK';
	}
}

if (!function_exists('einvoice_nemhandel_lookup_url')) {
	/**
	 * Where the user can look up the CVR registered on an EAN.
	 *
	 * @return string
	 */
	function einvoice_nemhandel_lookup_url() {
		return 'https://registration.nemhandel.dk/NemHandelRegisterWeb/public/participant/info';
	}
}
