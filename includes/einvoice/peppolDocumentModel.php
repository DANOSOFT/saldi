<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoice/peppolDocumentModel.php --- ver 5.1.0 --- 2026-11-09 ---
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
// 20261109 CL/MMR SD-815 Created (draft). Builds the Peppol document model of includes/einvoice/peppolModel.php from the rows of peppol_invoice_rows().
//                 The builder is pure: no database and no globals, so every accounting rule here is covered by tests with synthetic rows.

/*
 * What this file decides, in the order peppol_document_model() runs it (each point needs Morten's or an accountant's approval):
 *
 *  1. Lines: the same lines the posting counts (posnr >= 0, momsupdat() in includes/ordrefunc.php). A line with quantity 0 is a
 *     comment: it is not an invoice line, its text goes in the note of the line before it (option commentLines). A component of
 *     a samlevare (numeric samlevare, price 0) is left out like on the PDF. A line with an amount keeps it even when its text is
 *     empty; it is named after the item number or "Linje <n>".
 *  2. Line amount: the PDF's per-line amount (includes/formfunk.php, afrund() to 2 decimals), with rabatart 'amount' as an amount
 *     per unit and procent as the share of the line that is invoiced. Discount and procent go in one line allowance, so the net
 *     price stays the item price and LineExtensionAmount is that line amount (PEPPOL-EN16931-R120 holds within 0.005).
 *  3. Quantity and price signs: einvoice_line_quantity_price() (SST-746): a price is never negative, a credit note's quantities
 *     are negated so it shows the credited amounts as positive figures.
 *  4. VAT rate per line: momsupdat()'s rule (lower line rate, a free-text line's own rate, otherwise the order's rate; 0 when
 *     momsfri or omvbet). VAT category: S when the rate is above 0; AE for omvbet; E for an item whose varegruppe is momsfri
 *     (box7). Otherwise, with a rate of 0, the debitor group's EU zone (grupper DG box10) and then the customer's country
 *     decide: own country E, a customer whose order carries VAT (ordrer.momssats > 0, so B2C) E, EU K (goods) or AE
 *     (services, varegruppe box5 'ydelser'), outside the EU G. ordrelinjer.momsfri
 *     alone does not mean exempt: export orders carry it on every line (MEDSHOP: freight and goods to Norway).
 *     Greenland and the Faroe Islands are outside the EU (G) even though their address is sent as DK (option
 *     greenlandFaroeAsDk, as SST-870 does today). The customer's country is ordrer.land, then the card's land, then the
 *     CVR prefix, then DK.
 *  5. Totals: lines rounded to 2 decimals, VAT per category rounded like afrund(). When the booked ordrer.moms differs by less
 *     than 1.00 the largest S category takes the difference (BR-S-09/BR-CO-17 allow +-1); any difference to the booked
 *     ordrer.sum + ordrer.moms of at most maxRounding goes in PayableRoundingAmount, so PayableAmount is the booked receivable.
 *     A larger difference leaves reconciliation.ok false, and peppol_precheck() then stops the send.
 *  6. An invoice that bogfor_nu() settles at once (betalingsbet Kontant or Kreditkort with the account set under DIV 3) is
 *     sent as prepaid: PrepaidAmount is the total and PayableAmount 0 (option paidAsPrepaid).
 *  7. Payment: Danish customer: FIK 93 when Stamdata has an FI creditor number, then 42 (reg.nr. + account). Foreign customer
 *     or currency: 58 with the IBAN when there is one (SST-873), else 42. A credit note has no payment means.
 *  8. References: BT-10 is kundeordnr. Without it, OrderReference is 'NA' with our order number as SalesOrderID, which meets
 *     PEPPOL-EN16931-R003 without inventing a buyer reference. AccountingCost is left out (EasyUBL got "0").
 *  9. Foreign currency: the document currency is ordrer.valuta (empty: baseCurrency); the VAT total is also stated in the base
 *     currency at ordrer.valutakurs (TaxCurrencyCode, VAT directive art. 230; option taxCurrency).
 */

include_once(__DIR__ . '/../stdFunc/countryCode.php');
include_once(__DIR__ . '/../stdFunc/einvoiceCvr.php');
include_once(__DIR__ . '/../stdFunc/einvoiceLineSign.php');

if (!function_exists('peppol_model_default_options')) {
	/**
	 * The options peppol_document_model() understands, with their defaults.
	 *
	 * @return array{
	 *   documentType: string|null,       'invoice' or 'creditnote'; null takes it from ordrer.art (DK/KK give a credit note).
	 *   sellerEndpoint: array{scheme: string, id: string}|null,  The endpoint Semantic registered for the sender; null derives 0184 + CVR.
	 *   sellerVatRegistered: bool,       False sends every line as category O and leaves all VAT numbers out (BR-O-02).
	 *   greenlandFaroeAsDk: bool,        Send a Greenland/Faroe address as DK (EasyUBL behaviour, SST-870).
	 *   commentLines: string,            'note' puts the text of quantity-0 lines in the previous line's note, 'drop' leaves it out.
	 *   fik: bool,                       Offer FIK (code 93, kortart 71) when Stamdata has an FI creditor number.
	 *   taxCurrency: bool,               State the VAT total in the base currency when the document currency differs.
	 *   paidAsPrepaid: bool,             Send an invoice settled at posting (Kontant/Kreditkort) with PayableAmount 0.
	 *   maxRounding: float,              The largest difference to the booked total that PayableRoundingAmount may absorb.
	 * }
	 */
	function peppol_model_default_options() {
		return array(
			'documentType' => null,
			'sellerEndpoint' => null,
			'sellerVatRegistered' => true,
			'greenlandFaroeAsDk' => true,
			'commentLines' => 'note',
			'fik' => true,
			'taxCurrency' => true,
			'paidAsPrepaid' => true,
			'maxRounding' => 1.00,
		);
	}
}

if (!function_exists('peppol_model_round')) {
	/**
	 * Rounds like afrund() in includes/std_func.php, which the posting and the PDF use: 0.001 (2 decimals) or 0.0001
	 * (3 decimals) is added away from zero first, so a value that is a hair below .5 because of float arithmetic rounds up.
	 * A copy, so the builder needs no std_func.php; the result is never -0.0.
	 *
	 * @param int|float|string|null $value    The number.
	 * @param int                   $decimals Decimals to keep.
	 * @return float
	 */
	function peppol_model_round($value, $decimals = 2) {
		$value = (float)$value;
		$decimals = (int)$decimals;
		$nudge = 0.001;
		for ($x = 1; $x < $decimals; $x++) {
			$nudge = $nudge / 10;
		}
		if ($value > 0) {
			$value = round($value + $nudge, $decimals);
		} elseif ($value < 0) {
			$value = round($value - $nudge, $decimals);
		}
		return ($value == 0) ? 0.0 : $value;
	}
}

if (!function_exists('peppol_model_text')) {
	/**
	 * A database text as model text: control characters removed, line breaks as LF, trimmed; '' becomes null.
	 *
	 * @param mixed $value     The text.
	 * @param bool  $stripTags True for line texts, which can hold HTML from a webshop (<br> becomes a line break).
	 * @return string|null
	 */
	function peppol_model_text($value, $stripTags = false) {
		if ($value === null || is_array($value) || is_object($value)) {
			return null;
		}
		$text = (string)$value;
		if ($stripTags) {
			$text = preg_replace('/<br\s*\/?>/i', "\n", $text);
			$text = strip_tags($text);
		}
		$text = str_replace(array("\r\n", "\r"), "\n", $text);
		$text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
		$text = trim($text);
		return ($text === '') ? null : $text;
	}
}

if (!function_exists('peppol_model_one_line')) {
	/**
	 * Model text on one line: line breaks and runs of white space become one space.
	 *
	 * @param mixed $value The text.
	 * @return string|null
	 */
	function peppol_model_one_line($value) {
		$text = peppol_model_text($value);
		if ($text === null) {
			return null;
		}
		$text = trim(preg_replace('/\s+/', ' ', $text));
		return ($text === '') ? null : $text;
	}
}

if (!function_exists('peppol_model_date')) {
	/**
	 * A database date as 'YYYY-MM-DD' (PEPPOL-EN16931-F001), or null when it is empty or no real date.
	 *
	 * @param mixed $value 'YYYY-MM-DD', possibly with a time after it.
	 * @return string|null
	 */
	function peppol_model_date($value) {
		if (!preg_match('/^\s*(\d{4})-(\d{1,2})-(\d{1,2})/', (string)$value, $m)) {
			return null;
		}
		if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
			return null;
		}
		return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
	}
}

if (!function_exists('peppol_model_is_eu')) {
	/**
	 * Whether a country is an EU member state (the VAT area; Greenland and the Faroe Islands are not).
	 *
	 * @param string $country ISO 3166-1 alpha-2 code.
	 * @return bool
	 */
	function peppol_model_is_eu($country) {
		static $eu = array('AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK');
		return in_array(strtoupper((string)$country), $eu, true);
	}
}

if (!function_exists('peppol_model_eas_codes')) {
	/**
	 * The Electronic Address Scheme codes Peppol BIS Billing 3.0.22 accepts (PEPPOL-EN16931-CL008, $eaid in
	 * PEPPOL-EN16931-UBL.sch). 9908, 0037 and 9958, which EasyUBL's type names stood for, are not in it any more.
	 *
	 * @return list<string>
	 */
	function peppol_model_eas_codes() {
		return array('0002', '0007', '0009', '0060', '0088', '0096', '0097', '0106', '0130', '0135', '0142', '0151', '0158', '0183', '0184',
			'0188', '0190', '0191', '0192', '0195', '0196', '0198', '0199', '0200', '0201', '0204', '0208', '0209', '0210', '0211', '0216',
			'0218', '0221', '0225', '0230', '0235', '0240', '0242', '0244', '0245', '0246', '0248', '9910', '9913', '9914', '9915', '9918',
			'9919', '9920', '9922', '9923', '9924', '9925', '9926', '9927', '9928', '9929', '9930', '9931', '9932', '9933', '9934', '9935',
			'9936', '9937', '9938', '9939', '9940', '9941', '9942', '9943', '9944', '9945', '9946', '9947', '9948', '9949', '9950', '9951',
			'9952', '9953', '9957', '9959');
	}
}

if (!function_exists('peppol_model_vat_scheme')) {
	/**
	 * The EAS code for a country's VAT number, used to reach a foreign customer known only by its VAT number.
	 * Sweden, Norway and Finland use their organisation number schemes instead (see peppol_model_endpoint_from_cvr()).
	 *
	 * @param string $country ISO 3166-1 alpha-2 code ('EL' is read as Greece).
	 * @return string|null
	 */
	function peppol_model_vat_scheme($country) {
		static $schemes = array(
			'AD' => '9922', 'AL' => '9923', 'AT' => '9914', 'BA' => '9924', 'BE' => '9925', 'BG' => '9926', 'CH' => '9927', 'CY' => '9928',
			'CZ' => '9929', 'DE' => '9930', 'EE' => '9931', 'ES' => '9920', 'FR' => '9957', 'GB' => '9932', 'GR' => '9933', 'EL' => '9933',
			'HR' => '9934', 'HU' => '9910', 'IE' => '9935', 'IT' => '0211', 'LI' => '9936', 'LT' => '9937', 'LU' => '9938', 'LV' => '9939',
			'MC' => '9940', 'ME' => '9941', 'MK' => '9942', 'MT' => '9943', 'NL' => '9944', 'PL' => '9945', 'PT' => '9946', 'RO' => '9947',
			'RS' => '9948', 'SI' => '9949', 'SK' => '9950', 'SM' => '9951', 'TR' => '9952', 'VA' => '9953',
		);
		$country = strtoupper((string)$country);
		return $schemes[$country] ?? null;
	}
}

if (!function_exists('peppol_model_gln_valid')) {
	/**
	 * Whether a value is a GS1 GLN: 13 digits with a valid check digit (PEPPOL-COMMON-R040).
	 *
	 * @param string|null $gln The value.
	 * @return bool
	 */
	function peppol_model_gln_valid($gln) {
		$gln = (string)$gln;
		if (!preg_match('/^[0-9]{13}$/', $gln)) {
			return false;
		}
		$sum = 0;
		for ($i = 0; $i < 12; $i++) {
			$sum += (int)$gln[$i] * (($i % 2) ? 3 : 1);
		}
		return ((10 - ($sum % 10)) % 10) === (int)$gln[12];
	}
}

if (!function_exists('peppol_model_cvr_parts')) {
	/**
	 * Reads a CVR field (order, card or Stamdata) as a country and a number.
	 *
	 * Only a value einvoice_cvr_present() accepts counts (SD-774: "-", "N/A", a GLN are no CVR). Spaces, dashes and other
	 * separators are dropped, including the no-break space a webshop can leave. A number without a letter prefix belongs
	 * to $defaultCountry; "GER" is a Greenlandic GER number, "EL" Greece.
	 *
	 * @param string|null $cvr            The field.
	 * @param string      $defaultCountry Country of a number without prefix.
	 * @return array{
	 *   country: string,  ISO code ('' when there is no CVR).
	 *   number: string,   The number without the country prefix.
	 *   raw: string,      The whole value, upper case, letters and digits only.
	 *   prefixed: bool,   Whether the field carried a country prefix.
	 * }
	 */
	function peppol_model_cvr_parts($cvr, $defaultCountry = 'DK') {
		$raw = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$cvr));
		if ($raw === '' || !einvoice_cvr_present($cvr)) {
			return array('country' => '', 'number' => '', 'raw' => '', 'prefixed' => false);
		}
		if (preg_match('/^[0-9]/', $raw)) {
			return array('country' => strtoupper((string)$defaultCountry), 'number' => $raw, 'raw' => $raw, 'prefixed' => false);
		}
		if (substr($raw, 0, 3) === 'GER') {
			return array('country' => 'GL', 'number' => substr($raw, 3), 'raw' => $raw, 'prefixed' => true);
		}
		if (preg_match('/^([A-Z]{2})(.+)$/', $raw, $m)) {
			return array('country' => ($m[1] === 'EL') ? 'GR' : $m[1], 'number' => $m[2], 'raw' => $raw, 'prefixed' => true);
		}
		return array('country' => strtoupper((string)$defaultCountry), 'number' => $raw, 'raw' => $raw, 'prefixed' => false);
	}
}

if (!function_exists('peppol_model_endpoint_from_ean')) {
	/**
	 * The receiver's electronic address from the EAN field, mirroring the branches of sendInvoice() in debitor/api.php with
	 * EAS codes instead of EasyUBL's type names.
	 *
	 * A plain number is a GLN (0088). "scheme:value" keeps a numeric scheme, except the three codes BIS 3.0.22 dropped
	 * (9908 -> 0192, 0037 -> 0216, 9958 -> 0204); 0088 is the GLN (SST-905). A name such as "GLN:", "DK:CVR:" or
	 * "SE:ORGNR:" is mapped; an unknown name with a 13-digit value is read as a GLN. The value is cleaned for the scheme:
	 * 0184 without "DK", 0192 without "NO"/"MVA", 0007 as the 10-digit organisation number.
	 * An unknown numeric scheme is kept as typed; peppol_precheck() reports it (EasyUBL silently sent it as DK:CVR).
	 *
	 * @param string|null $ean The EAN field (the order's, or the card's when the order has none).
	 * @return array{scheme: string, id: string}|null Null when the field is empty or cannot be read.
	 */
	function peppol_model_endpoint_from_ean($ean) {
		$ean = preg_replace('/[\s\x{00A0}]+/u', '', (string)$ean);
		if ($ean === '' || $ean === null) {
			return null;
		}
		$pos = strrpos($ean, ':');
		if ($pos === false) {
			return array('scheme' => '0088', 'id' => preg_replace('/[^0-9A-Za-z]/', '', $ean));
		}
		$scheme = strtoupper(substr($ean, 0, $pos));
		$value = substr($ean, $pos + 1);
		$names = array(
			'GLN' => '0088', 'EAN' => '0088', 'DK:GLN' => '0088', 'DK:CVR' => '0184', 'CVR' => '0184', 'DK:P' => '0096', 'DK:SE' => '0198',
			'SE:ORGNR' => '0007', 'NO:ORGNR' => '0192', 'NO:ORG' => '0192', 'FI:OVT' => '0216', 'NL:KVK' => '0106', 'NL:OINO' => '0190',
			'DE:LWID' => '0204', 'DE:LID' => '0204', 'ES:VAT' => '9920',
		);
		$legacy = array('9908' => '0192', '0037' => '0216', '9958' => '0204');
		if (preg_match('/^[0-9]{4}$/', $scheme)) {
			$scheme = $legacy[$scheme] ?? $scheme;
		} elseif (isset($names[$scheme])) {
			$scheme = $names[$scheme];
		} elseif (preg_match('/^[0-9]{13}$/', $value)) {
			$scheme = '0088';
		} else {
			return null;
		}
		$clean = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $value));
		if ($scheme === '0184' || $scheme === '0088' || $scheme === '0096' || $scheme === '0192') {
			$digits = preg_replace('/[^0-9]/', '', $clean);
			$clean = ($digits !== '') ? $digits : $clean;
		} elseif ($scheme === '0198') {
			$clean = 'DK' . preg_replace('/[^0-9]/', '', $clean);
		} elseif ($scheme === '0007') {
			$digits = preg_replace('/[^0-9]/', '', $clean);
			$clean = (strlen($digits) === 12 && substr($digits, -2) === '01') ? substr($digits, 0, 10) : $digits;
		}
		if ($clean === '') {
			return null;
		}
		return array('scheme' => $scheme, 'id' => $clean);
	}
}

if (!function_exists('peppol_model_endpoint_from_cvr')) {
	/**
	 * The receiver's electronic address when there is no EAN: the CVR field read by peppol_model_cvr_parts().
	 *
	 * Danish (also a GL/FO prefix, as sendInvoice() does): 0184 + the digits, without "DK" (customers are registered as
	 * 0184:<CVR>). Sweden 0007 (organisation number), Norway 0192, Finland 0216 (OVT "0037" + Y-tunnus), other countries
	 * their VAT scheme with the full VAT number. EasyUBL got NL as KVK and DE as Leitweg-ID, which a VAT number is not.
	 *
	 * @param array{country: string, number: string, raw: string, prefixed: bool} $cvrParts From peppol_model_cvr_parts().
	 * @return array{scheme: string, id: string}|null
	 */
	function peppol_model_endpoint_from_cvr(array $cvrParts) {
		$country = $cvrParts['country'] ?? '';
		$number = $cvrParts['number'] ?? '';
		if ($country === '' || $number === '') {
			return null;
		}
		$digits = preg_replace('/[^0-9]/', '', $number);
		if ($country === 'DK' || $country === 'GL' || $country === 'FO') {
			return ($digits === '') ? null : array('scheme' => '0184', 'id' => $digits);
		}
		if ($country === 'SE') {
			if (strlen($digits) === 12 && substr($digits, -2) === '01') {
				$digits = substr($digits, 0, 10);
			}
			return ($digits === '') ? null : array('scheme' => '0007', 'id' => $digits);
		}
		if ($country === 'NO') {
			return ($digits === '') ? null : array('scheme' => '0192', 'id' => $digits);
		}
		if ($country === 'FI') {
			return ($digits === '') ? null : array('scheme' => '0216', 'id' => '0037' . $digits);
		}
		$scheme = peppol_model_vat_scheme($country);
		if ($scheme === null) {
			return null;
		}
		$prefix = ($country === 'GR') ? 'EL' : $country;
		return array('scheme' => $scheme, 'id' => $prefix . $number);
	}
}

if (!function_exists('peppol_model_legal_id')) {
	/**
	 * The legal registration identifier (BT-30/BT-47) from a CVR read by peppol_model_cvr_parts().
	 *
	 * Only numbers whose format the scheme's rule accepts: an 8-digit Danish CVR under 0184 (DK-R-014/DK-R-017,
	 * PEPPOL-COMMON-R042), a 10-digit Swedish (0007) or 9-digit Norwegian (0192) organisation number. Anything else gives
	 * null, because a wrong CompanyID is fatal while a missing one is not (except DK-R-002 for the seller).
	 *
	 * @param array{country: string, number: string, raw: string, prefixed: bool} $cvrParts From peppol_model_cvr_parts().
	 * @return array{scheme: string, id: string}|null
	 */
	function peppol_model_legal_id(array $cvrParts) {
		$country = $cvrParts['country'] ?? '';
		$digits = preg_replace('/[^0-9]/', '', (string)($cvrParts['number'] ?? ''));
		if (($country === 'DK' || $country === 'GL' || $country === 'FO') && preg_match('/^[0-9]{8}$/', $digits)) {
			return array('scheme' => '0184', 'id' => $digits);
		}
		if ($country === 'SE') {
			if (strlen($digits) === 12 && substr($digits, -2) === '01') {
				$digits = substr($digits, 0, 10);
			}
			return (strlen($digits) === 10) ? array('scheme' => '0007', 'id' => $digits) : null;
		}
		if ($country === 'NO' && strlen($digits) === 9) {
			return array('scheme' => '0192', 'id' => $digits);
		}
		return null;
	}
}

if (!function_exists('peppol_model_vat_id')) {
	/**
	 * The VAT identifier (BT-31/BT-48) with its country prefix (BR-CO-09) from a CVR read by peppol_model_cvr_parts().
	 *
	 * Danish: "DK" + the 8 digits (a GL/FO prefix is read as Danish, as sendInvoice() does). Norway: "NO" + 9 digits + "MVA".
	 * Greece uses "EL". Other foreign numbers are sent as stored, upper case without separators.
	 *
	 * @param array{country: string, number: string, raw: string, prefixed: bool} $cvrParts From peppol_model_cvr_parts().
	 * @return string|null
	 */
	function peppol_model_vat_id(array $cvrParts) {
		$country = $cvrParts['country'] ?? '';
		$number = (string)($cvrParts['number'] ?? '');
		if ($country === '' || $number === '') {
			return null;
		}
		$digits = preg_replace('/[^0-9]/', '', $number);
		if ($country === 'DK' || $country === 'GL' || $country === 'FO') {
			return preg_match('/^[0-9]{8}$/', $digits) ? 'DK' . $digits : null;
		}
		if ($country === 'NO') {
			return (strlen($digits) === 9) ? 'NO' . $digits . 'MVA' : null;
		}
		if ($country === 'SE' && strlen($digits) === 10) {
			return 'SE' . $digits . '01';
		}
		$prefix = ($country === 'GR') ? 'EL' : $country;
		return $prefix . $number;
	}
}

if (!function_exists('peppol_model_street')) {
	/**
	 * Street and additional street like splitStreetAddress() in debitor/api.php: text after the first comma in addr1 is
	 * additional address information and goes before addr2. BIS has no building number element (UBL-CR-155/218), so the
	 * house number stays in the street.
	 *
	 * @param string|null $addr1 Address line 1.
	 * @param string|null $addr2 Address line 2.
	 * @return array{street: string|null, additionalStreet: string|null}
	 */
	function peppol_model_street($addr1, $addr2) {
		$addr1 = (string)peppol_model_one_line($addr1);
		$addr2 = (string)peppol_model_one_line($addr2);
		$parts = array_map('trim', explode(',', $addr1, 2));
		$main = $parts[0];
		$extra = $parts[1] ?? '';
		if ($extra !== '' && $addr2 !== '') {
			$additional = $extra . ', ' . $addr2;
		} else {
			$additional = ($extra !== '') ? $extra : $addr2;
		}
		return array('street' => ($main === '') ? null : $main, 'additionalStreet' => ($additional === '') ? null : $additional);
	}
}

if (!function_exists('peppol_model_address')) {
	/**
	 * A postal address in model shape.
	 *
	 * @param string|null $addr1   Address line 1.
	 * @param string|null $addr2   Address line 2.
	 * @param string|null $postnr  Postal code.
	 * @param string|null $bynavn  City.
	 * @param string      $country ISO 3166-1 alpha-2 code.
	 * @return array{street: string|null, additionalStreet: string|null, city: string|null, postalCode: string|null, countrySubentity: string|null, country: string}
	 */
	function peppol_model_address($addr1, $addr2, $postnr, $bynavn, $country) {
		$street = peppol_model_street($addr1, $addr2);
		return array(
			'street' => $street['street'],
			'additionalStreet' => $street['additionalStreet'],
			'city' => peppol_model_one_line($bynavn),
			'postalCode' => peppol_model_one_line($postnr),
			'countrySubentity' => null,
			'country' => (string)$country,
		);
	}
}

if (!function_exists('peppol_model_unit_code')) {
	/**
	 * The UN/ECE Recommendation 20/21 unit code (BT-130, BR-CL-23) for ordrelinjer.enhed, which is free text.
	 * Pieces and anything unknown are 'EA', which is what EasyUBL got for every line until now.
	 *
	 * @param string|null $unit ordrelinjer.enhed.
	 * @return string
	 */
	function peppol_model_unit_code($unit) {
		$key = trim((string)$unit);
		$key = function_exists('mb_strtolower') ? mb_strtolower($key, 'UTF-8') : strtolower($key);
		$key = preg_replace('/[\s.]+/', '', $key);
		static $map = array(
			'stk' => 'EA', 'styk' => 'EA', 'stykker' => 'EA', 'st' => 'EA', 'pcs' => 'EA', 'pc' => 'EA', 'ea' => 'EA', 'each' => 'EA',
			'pk' => 'XPK', 'pkt' => 'XPK', 'pakke' => 'XPK', 'pakker' => 'XPK', 'pack' => 'XPK',
			'ks' => 'XBX', 'kasse' => 'XBX', 'kasser' => 'XBX', 'æske' => 'XBX', 'box' => 'XBX',
			'sæt' => 'SET', 'saet' => 'SET', 'set' => 'SET',
			'ps' => 'XBG', 'pose' => 'XBG', 'poser' => 'XBG', 'bag' => 'XBG',
			'rl' => 'XRO', 'rulle' => 'XRO', 'ruller' => 'XRO', 'roll' => 'XRO',
			'par' => 'PR', 'pair' => 'PR',
			'fl' => 'XBO', 'flaske' => 'XBO', 'flasker' => 'XBO', 'bottle' => 'XBO',
			't' => 'HUR', 'tim' => 'HUR', 'time' => 'HUR', 'timer' => 'HUR', 'hour' => 'HUR', 'hours' => 'HUR',
			'min' => 'MIN', 'minut' => 'MIN', 'minutter' => 'MIN',
			'dag' => 'DAY', 'dage' => 'DAY', 'døgn' => 'DAY', 'day' => 'DAY', 'days' => 'DAY',
			'uge' => 'WEE', 'uger' => 'WEE', 'week' => 'WEE',
			'md' => 'MON', 'mdr' => 'MON', 'mnd' => 'MON', 'måned' => 'MON', 'måneder' => 'MON', 'month' => 'MON',
			'år' => 'ANN', 'aar' => 'ANN', 'year' => 'ANN',
			'kg' => 'KGM', 'g' => 'GRM', 'gr' => 'GRM', 'gram' => 'GRM', 'ton' => 'TNE', 'tons' => 'TNE',
			'l' => 'LTR', 'ltr' => 'LTR', 'liter' => 'LTR', 'ml' => 'MLT', 'cl' => 'CLT',
			'm' => 'MTR', 'meter' => 'MTR', 'lbm' => 'MTR', 'cm' => 'CMT', 'mm' => 'MMT', 'km' => 'KMT',
			'm2' => 'MTK', 'm²' => 'MTK', 'kvm' => 'MTK', 'm3' => 'MTQ', 'm³' => 'MTQ', 'kbm' => 'MTQ',
			'kwh' => 'KWH',
		);
		return $map[$key] ?? 'EA';
	}
}

if (!function_exists('peppol_model_due_date')) {
	/**
	 * The due date as forfaldsdag() in includes/forfaldsdag.php computes it for the open item (bogfor_nu()), as 'YYYY-MM-DD'.
	 *
	 * Netto: invoice date + days. Kontant and Efterkrav: the invoice date. Any other term (Lb. md., Forud, Kreditkort):
	 * end of the invoice month + days. A copy so the builder needs no std_func.php; tests compare it with forfaldsdag().
	 *
	 * @param string|null     $fakturadate   ordrer.fakturadate.
	 * @param string|null     $betalingsbet  ordrer.betalingsbet.
	 * @param int|string|null $betalingsdage ordrer.betalingsdage.
	 * @return string|null Null without an invoice date.
	 */
	function peppol_model_due_date($fakturadate, $betalingsbet, $betalingsdage) {
		$date = peppol_model_date($fakturadate);
		if ($date === null) {
			return null;
		}
		$terms = strtolower(trim((string)$betalingsbet));
		list($year, $month, $day) = array_map('intval', explode('-', $date));
		if ($terms !== 'efterkrav' && $terms !== 'kontant') {
			$lastDay = 31;
			while (!checkdate($month, $lastDay, $year)) {
				$lastDay--;
				if ($lastDay < 27) break;
			}
			if ($terms !== 'netto') {
				$day = $lastDay;
			}
			$day += (int)$betalingsdage;
			while ($day > $lastDay) {
				$month++;
				if ($month > 12) {
					$year++;
					$month = 1;
				}
				$day -= $lastDay;
				$lastDay = 31;
				while (!checkdate($month, $lastDay, $year)) {
					$lastDay--;
					if ($lastDay < 27) break;
				}
			}
		}
		if (!checkdate($month, $day, $year)) {
			return $date;
		}
		return sprintf('%04d-%02d-%02d', $year, $month, $day);
	}
}

if (!function_exists('peppol_model_payment_terms')) {
	/**
	 * The payment terms text (BT-20) in Danish, from the order's terms as forfaldsdag() reads them.
	 *
	 * @param string|null     $betalingsbet  ordrer.betalingsbet.
	 * @param int|string|null $betalingsdage ordrer.betalingsdage.
	 * @param bool            $paid          True when the invoice was settled at posting.
	 * @return string|null
	 */
	function peppol_model_payment_terms($betalingsbet, $betalingsdage, $paid = false) {
		$terms = strtolower(trim((string)$betalingsbet));
		$days = (int)$betalingsdage;
		if ($paid) {
			return ($terms === 'kreditkort') ? 'Betalt med betalingskort' : 'Betalt kontant';
		}
		if ($terms === '') {
			return null;
		}
		if ($terms === 'netto') {
			return ($days > 0) ? "Netto $days dage" : 'Netto kontant';
		}
		if ($terms === 'kontant') {
			return 'Kontant';
		}
		if ($terms === 'efterkrav') {
			return 'Efterkrav';
		}
		if ($terms === 'forud') {
			return ($days > 0) ? "Forudbetaling, løbende måned + $days dage" : 'Forudbetaling';
		}
		if ($terms === 'kreditkort') {
			return 'Betalingskort';
		}
		return ($days > 0) ? "Løbende måned + $days dage" : 'Løbende måned';
	}
}

if (!function_exists('peppol_model_fik_payment_id')) {
	/**
	 * The 15-digit FIK payment id for kortart 71 exactly as modulus_10() in includes/formfunk.php builds it for the PDF:
	 * the customer number and invoice number in the layout of the invoice form (betalingsid(account,invoice)), cut or padded
	 * to 14 digits, plus a modulus 10 check digit. A copy without the HTML and database access of modulus_10().
	 *
	 * @param string|null $kontonr       ordrer.kontonr.
	 * @param string|null $fakturanr     ordrer.fakturanr.
	 * @param int         $accountLength Digits of the customer number in the layout (modulus_10() default 0).
	 * @param int         $invoiceLength Digits of the invoice number in the layout (modulus_10() default 14).
	 * @return string 15 digits.
	 */
	function peppol_model_fik_payment_id($kontonr, $fakturanr, $accountLength = 0, $invoiceLength = 14) {
		$fakturanr = trim((string)$fakturanr);
		$kontonr = trim((string)$kontonr);
		$accountLength = (int)$accountLength;
		$invoiceLength = (int)$invoiceLength;
		if (strpos($fakturanr, '-')) {
			$fakturanr = '0';
		}
		$fakturanr = preg_replace('/[^0-9]/', '', $fakturanr);
		$kontonr = preg_replace('/[^0-9]/', '', $kontonr);
		if (strlen($fakturanr) > $invoiceLength) {
			$fakturanr = substr($fakturanr, -$invoiceLength);
		}
		// substr($x, -0) is the whole string, which is what modulus_10() does with a layout of 0 account digits
		if (strlen($kontonr) > $accountLength) {
			$kontonr = substr($kontonr, -$accountLength);
		}
		$fakturanr = str_pad($fakturanr, max($invoiceLength, 0), '0', STR_PAD_LEFT);
		$kontonr = str_pad($kontonr, max($accountLength, 0), '0', STR_PAD_LEFT);
		$paymentId = $kontonr . $fakturanr;
		if (strlen($paymentId) > 14) {
			$paymentId = substr($paymentId, -14);
		} else {
			$paymentId = str_pad($paymentId, 14, '0', STR_PAD_LEFT);
		}
		$sum = 0;
		$double = true;
		for ($x = 13; $x >= 0; $x--) {
			$digit = (int)$paymentId[$x];
			if ($double) {
				$digit *= 2;
				if ($digit > 9) {
					$digit = intdiv($digit, 10) + ($digit % 10);
				}
			}
			$sum += $digit;
			$double = !$double;
		}
		$check = (10 - ($sum % 10)) % 10;
		return $paymentId . $check;
	}
}

if (!function_exists('peppol_model_number')) {
	/**
	 * A number in Danish notation for a reason text: comma as decimal separator, no needless decimals ("10", "12,5").
	 *
	 * @param int|float $value The number.
	 * @return string
	 */
	function peppol_model_number($value) {
		$text = number_format((float)$value, 3, ',', '.');
		$text = rtrim(rtrim($text, '0'), ',');
		return ($text === '-0') ? '0' : $text;
	}
}

if (!function_exists('peppol_model_line_rate')) {
	/**
	 * The VAT rate momsupdat() (includes/ordrefunc.php) books for a line.
	 *
	 * A line with an item or its own account that is neither momsfri nor omvbet carries VAT: its own rate when that is
	 * above 0 and below the order's rate, a free-text line's own rate (chosen on purpose, JOB-056), otherwise the order's
	 * rate. Other lines carry no VAT. A priced line without item and account is booked with the order's rate when the whole
	 * order has one rate, so it gets the order's rate here too (and the builder notes it).
	 *
	 * @param array<string, mixed> $line      An ordrelinjer row.
	 * @param float                $orderRate ordrer.momssats.
	 * @return float
	 */
	function peppol_model_line_rate(array $line, $orderRate) {
		$orderRate = (float)$orderRate;
		$momsfri = (trim((string)($line['momsfri'] ?? '')) === 'on');
		$omvbet = (trim((string)($line['omvbet'] ?? '')) !== '');
		if ($momsfri || $omvbet) {
			return 0.0;
		}
		$vareId = (int)($line['vare_id'] ?? 0);
		$account = (int)($line['bogf_konto'] ?? 0);
		$lineRate = (float)($line['momssats'] ?? 0);
		if (!$vareId && $account <= 0) {
			return $orderRate;
		}
		if ($lineRate > 0 && $lineRate < $orderRate) {
			return $lineRate;
		}
		if (!$vareId) {
			return $lineRate;
		}
		return $orderRate;
	}
}

if (!function_exists('peppol_model_vat_category')) {
	/**
	 * The VAT category (UNCL 5305) and exemption reason of a line. See point 4 at the top of this file.
	 *
	 * @param float  $rate          The rate from peppol_model_line_rate().
	 * @param bool   $exempt        The line is momsfri and its item group is momsfri (grupper VG box7).
	 * @param bool   $omvbet        ordrelinjer.omvbet is set (domestic reverse charge).
	 * @param bool   $isService     The item group is 'ydelser' (grupper VG box5).
	 * @param string $euZone        The debitor group's EU zone (grupper DG box10): '', 'B2B-EU', 'B2C-EU', 'B2B-UDL', 'B2C-UDL'.
	 * @param string $buyerCountry  The customer's real country (Greenland and the Faroe Islands as GL/FO).
	 * @param string $sellerCountry The seller's country.
	 * @param bool   $vatRegistered False for a seller without VAT registration (category O).
	 * @param bool   $orderHasVat   ordrer.momssats is above 0: the customer is normally charged VAT, so a line without VAT is
	 *                              exempt rather than an EU or export supply (a private customer's zero-priced freight line).
	 * @return array{category: string, percent: float|null, exemptionReasonCode: string|null, exemptionReason: string|null}
	 */
	function peppol_model_vat_category($rate, $exempt, $omvbet, $isService, $euZone, $buyerCountry, $sellerCountry, $vatRegistered = true, $orderHasVat = false) {
		$reasons = array(
			'E' => array(null, 'Momsfritaget'),
			'AE' => array('VATEX-EU-AE', 'Omvendt betalingspligt'),
			'K' => array('VATEX-EU-IC', 'Momsfri levering til virksomhed i andet EU-land'),
			'G' => array('VATEX-EU-G', 'Eksport uden for EU'),
			'O' => array('VATEX-EU-O', 'Ikke momspligtig'),
		);
		if (!$vatRegistered) {
			$category = 'O';
		} elseif ((float)$rate > 0) {
			return array('category' => 'S', 'percent' => (float)$rate, 'exemptionReasonCode' => null, 'exemptionReason' => null);
		} elseif ($omvbet) {
			$category = 'AE';
		} elseif ($exempt) {
			$category = 'E';
		} elseif ($euZone === 'B2B-EU') {
			$category = $isService ? 'AE' : 'K';
		} elseif ($euZone === 'B2B-UDL' || $euZone === 'B2C-UDL') {
			$category = 'G';
		} elseif ($buyerCountry === '' || $buyerCountry === $sellerCountry || $orderHasVat) {
			$category = 'E';
		} elseif (peppol_model_is_eu($buyerCountry) && peppol_model_is_eu($sellerCountry)) {
			$category = $isService ? 'AE' : 'K';
		} else {
			$category = 'G';
		}
		return array(
			'category' => $category,
			'percent' => ($category === 'O') ? null : 0.0,
			'exemptionReasonCode' => $reasons[$category][0],
			'exemptionReason' => $reasons[$category][1],
		);
	}
}

if (!function_exists('peppol_model_line_amounts')) {
	/**
	 * Quantity, price, allowance and amount of one order line.
	 *
	 * The amount is the PDF's line amount (formfunk.php: afrund((pris - pris * rabat / 100) * antal, 2), or
	 * afrund((pris - rabat) * antal, 2) for rabatart 'amount', then procent applied and rounded again), negated on a
	 * credit note. The difference to quantity x price is one line allowance (a charge when a negative discount raises it)
	 * with reason code 95 for a discount. When quantity x price is negative (a returned item on an invoice) the discount
	 * goes in the price instead (gross price, price discount, net price), so no allowance is negative.
	 *
	 * @param array<string, mixed> $line     An ordrelinjer row.
	 * @param bool                 $isCredit True for a credit note.
	 * @return array{
	 *   quantity: float,          BT-129.
	 *   netPrice: float,          BT-146, never negative.
	 *   grossPrice: float|null,   BT-148.
	 *   priceDiscount: float|null, BT-147.
	 *   lineExtension: float,     BT-131.
	 *   allowanceCharges: list<array{isCharge: bool, amount: float, baseAmount: float|null, percent: float|null, reasonCode: string|null, reason: string|null}>,
	 *   slack: float,             |lineExtension - (quantity x netPrice - allowances + charges)|, at most 0.02 for PEPPOL-EN16931-R120.
	 * }
	 */
	function peppol_model_line_amounts(array $line, $isCredit) {
		$antal = (float)($line['antal'] ?? 0);
		$pris = (float)($line['pris'] ?? 0);
		$rabat = (float)($line['rabat'] ?? 0);
		$isAmountDiscount = (trim((string)($line['rabatart'] ?? '')) === 'amount');
		$procentRaw = $line['procent'] ?? null;
		$hasProcent = ($procentRaw !== null && trim((string)$procentRaw) !== '');
		$procent = $hasProcent ? (float)$procentRaw : 100.0;

		if ($isAmountDiscount) {
			$amount = peppol_model_round(($pris - $rabat) * $antal, 2);
		} else {
			$amount = peppol_model_round($pris * $antal - ($pris * $antal * $rabat / 100), 2);
		}
		if ($hasProcent) {
			$amount = peppol_model_round($amount - ($amount * (100 - $procent) / 100), 2);
		}
		if ($isCredit) {
			$amount = -$amount;
		}
		$amount = ($amount == 0) ? 0.0 : $amount;

		$signed = einvoice_line_quantity_price($antal, $pris, $isCredit);
		$quantity = $signed['quantity'];
		$price = $signed['price'];
		$gross = peppol_model_round($quantity * $price, 2);
		$difference = peppol_model_round($gross - $amount, 2);

		$result = array(
			'quantity' => $quantity,
			'netPrice' => $price,
			'grossPrice' => null,
			'priceDiscount' => null,
			'lineExtension' => $amount,
			'allowanceCharges' => array(),
			'slack' => 0.0,
		);
		if ($difference == 0) {
			$result['slack'] = abs($amount - $quantity * $price);
			return $result;
		}

		$parts = array();
		if ($rabat != 0) {
			$parts[] = $isAmountDiscount
				? 'Rabat ' . peppol_model_number($rabat) . ' pr. ' . (peppol_model_one_line($line['enhed'] ?? null) ?? 'stk.')
				: 'Rabat ' . peppol_model_number($rabat) . ' %';
		}
		if ($hasProcent && $procent != 100) {
			$parts[] = 'Faktureret ' . peppol_model_number($procent) . ' %';
		}

		if ($gross < 0) {
			// A negative line (a returned item on an invoice): the discount reduces the unit price instead.
			$net = peppol_model_round(abs($amount / $quantity), 4);
			$result['netPrice'] = $net;
			if ($net < $price) {
				$result['grossPrice'] = $price;
				$result['priceDiscount'] = round($price - $net, 4);
			}
			$result['slack'] = abs($amount - $quantity * $net);
			return $result;
		}

		$isCharge = ($difference < 0);
		$allowance = array(
			'isCharge' => $isCharge,
			'amount' => abs($difference),
			'baseAmount' => null,
			'percent' => null,
			'reasonCode' => $isCharge ? null : '95',
			'reason' => $isCharge ? ($parts ? 'Tillæg: ' . implode(', ', $parts) : 'Tillæg') : ($parts ? implode(', ', $parts) : 'Rabat'),
		);
		// A plain percentage discount also states percentage and base (PEPPOL-EN16931-R040 allows 0.02).
		if (!$isCharge && !$isAmountDiscount && $procent == 100 && $rabat > 0 && abs($difference - $gross * $rabat / 100) <= 0.02) {
			$allowance['percent'] = $rabat;
			$allowance['baseAmount'] = $gross;
		}
		$result['allowanceCharges'][] = $allowance;
		$result['slack'] = abs($amount - ($quantity * $price - ($isCharge ? -1 : 1) * abs($difference)));
		return $result;
	}
}

if (!function_exists('peppol_document_model')) {
	/**
	 * Builds the document model (peppol_model_example() in includes/einvoice/peppolModel.php) from the rows of
	 * peppol_invoice_rows(). Pure: the same rows always give the same model. The decisions are listed at the top of this file.
	 *
	 * The model has two keys the contract does not require; the writer ignores them:
	 * 'issues' (list<string>, English developer diagnostics: what was adjusted, left out or guessed) and
	 * 'reconciliation' (the computed totals against the booked ordrer.sum/moms; peppol_precheck() stops the send when ok is false).
	 *
	 * @param array $rows From peppol_invoice_rows(): order, lines, seller, customer, debtorGroup, itemGroups, creditedInvoice,
	 *                    settings, fikLayout. Missing parts count as empty.
	 * @param array $opts See peppol_model_default_options().
	 * @return array The model, plus 'issues' => list<string> and 'reconciliation' => array{
	 *   bookedNet: float,       ordrer.sum, sign turned for a credit note.
	 *   bookedVat: float,       ordrer.moms, sign turned for a credit note.
	 *   computedNet: float,     Sum of the line amounts.
	 *   computedVat: float,     VAT of the categories before any adjustment.
	 *   vatAdjustment: float,   Added to the largest S category so the VAT total is the booked one.
	 *   roundingAmount: float,  Put in PayableRoundingAmount.
	 *   ok: bool,               False when a difference was too large to absorb.
	 * }
	 * @throws InvalidArgumentException When $rows has no order.
	 */
	function peppol_document_model(array $rows, array $opts = array()) {
		$order = $rows['order'] ?? null;
		if (!is_array($order) || !$order) {
			throw new InvalidArgumentException('peppol_document_model(): the rows have no order.');
		}
		$opts = array_merge(peppol_model_default_options(), $opts);
		$seller = is_array($rows['seller'] ?? null) ? $rows['seller'] : array();
		$customer = is_array($rows['customer'] ?? null) ? $rows['customer'] : array();
		$debtorGroup = is_array($rows['debtorGroup'] ?? null) ? $rows['debtorGroup'] : array();
		$itemGroups = is_array($rows['itemGroups'] ?? null) ? $rows['itemGroups'] : array();
		$settings = is_array($rows['settings'] ?? null) ? $rows['settings'] : array();
		$fikLayout = is_array($rows['fikLayout'] ?? null) ? $rows['fikLayout'] : array();
		$issues = array();

		// Document type
		$art = strtoupper(trim((string)($order['art'] ?? '')));
		$artType = (substr($art, 1, 1) === 'K') ? 'creditnote' : 'invoice';
		$documentType = strtolower(trim((string)($opts['documentType'] ?? '')));
		if ($documentType !== 'invoice' && $documentType !== 'creditnote') {
			$documentType = $artType;
		} elseif ($documentType !== $artType) {
			$issues[] = "documentType $documentType does not match ordrer.art $art";
		}
		$isCredit = ($documentType === 'creditnote');
		$vatRegistered = (bool)$opts['sellerVatRegistered'];

		// Currency
		$baseCurrency = strtoupper(trim((string)($settings['baseCurrency'] ?? '')));
		if ($baseCurrency === '') {
			$baseCurrency = 'DKK';
		}
		$currency = strtoupper(trim((string)($order['valuta'] ?? '')));
		if ($currency === '') {
			$currency = $baseCurrency;
		}

		// Seller (Stamdata, adresser art='S')
		$sellerCountry = countryCodeIso2((string)($seller['land'] ?? ''), '');
		if ($sellerCountry === '') {
			$sellerCountry = countryCodeIso2((string)($settings['baseCountry'] ?? ''), 'DK');
		}
		$sellerCvr = peppol_model_cvr_parts($seller['cvrnr'] ?? '', $sellerCountry);
		$sellerLegal = peppol_model_legal_id($sellerCvr);
		$sellerEndpoint = null;
		if (is_array($opts['sellerEndpoint']) && trim((string)($opts['sellerEndpoint']['id'] ?? '')) !== '') {
			$sellerEndpoint = array('scheme' => (string)$opts['sellerEndpoint']['scheme'], 'id' => (string)$opts['sellerEndpoint']['id']);
		} elseif ($sellerLegal !== null) {
			$sellerEndpoint = $sellerLegal;
		} else {
			$sellerEndpoint = peppol_model_endpoint_from_cvr($sellerCvr);
		}
		if ($sellerLegal === null) {
			$issues[] = 'seller CVR missing or not in a known format';
		}
		$sellerName = peppol_model_one_line($seller['firmanavn'] ?? null);
		$sellerContact = array(
			'name' => peppol_model_one_line($order['ref'] ?? null),
			'phone' => peppol_model_one_line($seller['tlf'] ?? null),
			'email' => peppol_model_one_line($seller['email'] ?? null),
		);
		$sellerParty = array(
			'endpoint' => $sellerEndpoint ?? array('scheme' => '', 'id' => ''),
			'identifiers' => array(),
			'name' => null,
			'registrationName' => (string)$sellerName,
			'legalId' => $sellerLegal,
			'vatId' => $vatRegistered ? peppol_model_vat_id($sellerCvr) : null,
			'address' => peppol_model_address($seller['addr1'] ?? null, $seller['addr2'] ?? null, $seller['postnr'] ?? null, $seller['bynavn'] ?? null, $sellerCountry),
			'contact' => ($sellerContact['name'] === null && $sellerContact['phone'] === null && $sellerContact['email'] === null) ? null : $sellerContact,
		);

		// Buyer: the order's EAN/CVR, each falling back to the debitor card (SST-884, SD-774)
		$ean = trim((string)($order['ean'] ?? ''));
		$cvr = (string)($order['cvrnr'] ?? '');
		if (!einvoice_cvr_present($cvr)) {
			$cvr = '';
		}
		if ($ean === '' && trim((string)($customer['ean'] ?? '')) !== '') {
			$ean = trim((string)$customer['ean']);
			$issues[] = 'EAN taken from the debitor card';
		}
		if ($cvr === '' && einvoice_cvr_present($customer['cvrnr'] ?? '')) {
			$cvr = (string)$customer['cvrnr'];
			$issues[] = 'CVR taken from the debitor card';
		}
		$cvrHint = peppol_model_cvr_parts($cvr, 'DK');
		// SST-870: the order's country field decides, the CVR prefix is the fallback, DK the last resort;
		// the card's country comes before the CVR prefix when the order has none
		$land = trim((string)($order['land'] ?? ''));
		if ($land === '') {
			$land = trim((string)($customer['land'] ?? ''));
		}
		$buyerCountry = countryCodeIso2($land, $cvrHint['country']);
		if ($buyerCountry === '') {
			$buyerCountry = 'DK';
		}
		$gfAsDk = (bool)$opts['greenlandFaroeAsDk'];
		$sentCountry = ($gfAsDk && ($buyerCountry === 'GL' || $buyerCountry === 'FO')) ? 'DK' : $buyerCountry;
		// A number without prefix is Danish when it has 8 digits (sendInvoice()), else it belongs to the address country
		$digitsOnly = preg_replace('/[^0-9]/', '', $cvrHint['raw']);
		$defaultCvrCountry = (!$cvrHint['prefixed'] && $cvrHint['raw'] !== '' && strlen($digitsOnly) !== 8 && $buyerCountry !== 'GL' && $buyerCountry !== 'FO') ? $buyerCountry : 'DK';
		$buyerCvr = peppol_model_cvr_parts($cvr, $defaultCvrCountry);

		$buyerEndpoint = null;
		if ($ean !== '') {
			$buyerEndpoint = peppol_model_endpoint_from_ean($ean);
			if ($buyerEndpoint === null) {
				$issues[] = 'EAN field could not be read as an endpoint';
			}
		}
		if ($buyerEndpoint === null) {
			$buyerEndpoint = peppol_model_endpoint_from_cvr($buyerCvr);
		}
		if ($buyerEndpoint === null) {
			$issues[] = 'buyer has neither EAN nor CVR';
		}
		$buyerLegal = peppol_model_legal_id($buyerCvr);
		if ($sentCountry === 'DK' && $sellerCountry === 'DK' && $buyerLegal !== null && $buyerLegal['scheme'] !== '0184') {
			// DK-R-017: a Danish customer's CompanyID must be a CVR under 0184
			$buyerLegal = null;
		}
		$buyerVatId = $vatRegistered ? peppol_model_vat_id($buyerCvr) : null;

		// Customer address, with the delivery address as fallback (sendInvoice(), 20260604)
		$addr1 = trim((string)($order['addr1'] ?? ''));
		$addr2 = (string)($order['addr2'] ?? '');
		$postnr = trim((string)($order['postnr'] ?? ''));
		$bynavn = trim((string)($order['bynavn'] ?? ''));
		$levAddr1 = trim((string)($order['lev_addr1'] ?? ''));
		$levPostnr = trim((string)($order['lev_postnr'] ?? ''));
		$levBynavn = trim((string)($order['lev_bynavn'] ?? ''));
		$levComplete = ($levAddr1 !== '' && $levPostnr !== '' && $levBynavn !== '');
		if (($addr1 === '' || $postnr === '' || $bynavn === '') && $levComplete) {
			$addr1 = $levAddr1;
			$addr2 = (string)($order['lev_addr2'] ?? '');
			$postnr = $levPostnr;
			$bynavn = $levBynavn;
			$issues[] = 'customer address incomplete, delivery address used';
		}
		$buyerContact = array(
			'name' => peppol_model_one_line($order['kontakt'] ?? null),
			'phone' => peppol_model_one_line($order['phone'] ?? null),
			'email' => peppol_model_one_line($order['email'] ?? null),
		);
		$buyerParty = array(
			'endpoint' => $buyerEndpoint ?? array('scheme' => '', 'id' => ''),
			'identifiers' => array(),
			'name' => null,
			'registrationName' => (string)peppol_model_one_line($order['firmanavn'] ?? null),
			'legalId' => $buyerLegal,
			'vatId' => $buyerVatId,
			'address' => peppol_model_address($addr1, $addr2, $postnr, $bynavn, $sentCountry),
			'contact' => ($buyerContact['name'] === null && $buyerContact['phone'] === null && $buyerContact['email'] === null) ? null : $buyerContact,
		);

		// Delivery: the delivery address when complete, else the customer's address (sendInvoice()); needed for K (BR-IC-12)
		$deliveryAddress = null;
		$deliveryName = null;
		if ($levComplete) {
			$levCountry = countryCodeIso2((string)($order['lev_land'] ?? ''), $sentCountry);
			if ($gfAsDk && ($levCountry === 'GL' || $levCountry === 'FO')) {
				$levCountry = 'DK';
			}
			$deliveryAddress = peppol_model_address($levAddr1, $order['lev_addr2'] ?? null, $levPostnr, $levBynavn, $levCountry);
			$deliveryName = peppol_model_one_line($order['lev_navn'] ?? null);
		} elseif ($buyerParty['address']['street'] !== null && $buyerParty['address']['city'] !== null && $buyerParty['address']['postalCode'] !== null) {
			$deliveryAddress = $buyerParty['address'];
		}
		$deliveryDate = peppol_model_date($order['levdate'] ?? null);
		$delivery = ($deliveryDate === null && $deliveryAddress === null) ? null
			: array('date' => $deliveryDate, 'name' => $deliveryName, 'address' => $deliveryAddress);

		// Lines
		$orderRate = (float)($order['momssats'] ?? 0);
		$euZone = strtoupper(trim((string)($debtorGroup['box10'] ?? '')));
		$commentMode = ($opts['commentLines'] === 'drop') ? 'drop' : 'note';
		$lines = array();
		$pendingComments = array();
		$commentCount = 0;
		$maxSlack = 0.0;
		foreach (($rows['lines'] ?? array()) as $row) {
			if (!is_array($row)) {
				continue;
			}
			$posnr = (int)($row['posnr'] ?? 0);
			$antal = (float)($row['antal'] ?? 0);
			$pris = (float)($row['pris'] ?? 0);
			$text = peppol_model_text($row['beskrivelse'] ?? null, true);
			$varenr = peppol_model_one_line($row['varenr'] ?? null);
			if ($varenr === 'null') {
				$varenr = null;
			}
			$hasItem = ($varenr !== null || (int)($row['vare_id'] ?? 0) > 0);
			if ($posnr < 0) {
				if ($antal * $pris != 0) {
					$issues[] = "line posnr $posnr with an amount left out: momsupdat() does not count posnr < 0";
				}
				continue;
			}
			$amounts = ($antal != 0) ? peppol_model_line_amounts($row, $isCredit) : null;
			$isComment = ($antal == 0) || (!$hasItem && $amounts['lineExtension'] == 0);
			if (!$isComment && preg_match('/^[0-9]+$/', trim((string)($row['samlevare'] ?? ''))) && $amounts['lineExtension'] == 0) {
				// A component of a samlevare: formfunk.php does not print it
				continue;
			}
			if ($isComment) {
				if ($text === null) {
					continue;
				}
				$commentCount++;
				if ($commentMode === 'drop') {
					continue;
				}
				if ($lines) {
					$last = count($lines) - 1;
					$lines[$last]['note'] = ($lines[$last]['note'] === null) ? $text : $lines[$last]['note'] . "\n" . $text;
				} else {
					$pendingComments[] = $text;
				}
				continue;
			}

			$rate = peppol_model_line_rate($row, $orderRate);
			if (!(int)($row['vare_id'] ?? 0) && (int)($row['bogf_konto'] ?? 0) <= 0 && $rate > 0) {
				$issues[] = "line posnr $posnr has an amount but neither item nor account; booked with the order's VAT rate";
			}
			$vareId = (int)($row['vare_id'] ?? 0);
			$itemGroup = ($vareId && isset($itemGroups[$vareId]) && is_array($itemGroups[$vareId])) ? $itemGroups[$vareId] : array();
			$isService = (strtolower(trim((string)($itemGroup['type'] ?? ''))) === 'ydelser');
			$exempt = (trim((string)($row['momsfri'] ?? '')) === 'on' && trim((string)($itemGroup['vatExempt'] ?? '')) === 'on');
			$vat = peppol_model_vat_category(
				$rate,
				$exempt,
				trim((string)($row['omvbet'] ?? '')) !== '',
				$isService,
				$euZone,
				$buyerCountry,
				$sellerCountry,
				$vatRegistered,
				$orderRate > 0
			);
			if ($vat['category'] === 'E' && $currency !== $baseCurrency && !$exempt) {
				$issues[] = "line posnr $posnr: no VAT for a customer in $buyerCountry in $currency; check the customer's country";
			}
			if ($amounts['slack'] > $maxSlack) {
				$maxSlack = $amounts['slack'];
			}
			$textLines = ($text === null) ? array() : array_values(array_filter(array_map('trim', explode("\n", $text)), 'strlen'));
			$name = $textLines ? $textLines[0] : ($varenr ?? 'Linje ' . (count($lines) + 1));
			$note = null;
			if ($pendingComments) {
				$note = implode("\n", $pendingComments);
				$pendingComments = array();
			}
			$lines[] = array(
				'id' => (string)(count($lines) + 1),
				'quantity' => $amounts['quantity'],
				'unitCode' => peppol_model_unit_code($row['enhed'] ?? null),
				'lineExtension' => $amounts['lineExtension'],
				'note' => $note,
				'accountingCost' => null,
				'orderLineReference' => null,
				'allowanceCharges' => $amounts['allowanceCharges'],
				'netPrice' => $amounts['netPrice'],
				'baseQuantity' => null,
				'grossPrice' => $amounts['grossPrice'],
				'priceDiscount' => $amounts['priceDiscount'],
				'itemName' => $name,
				'itemDescription' => (count($textLines) > 1) ? implode("\n", $textLines) : null,
				'sellersItemId' => $varenr,
				'standardItemId' => null,
				'vatCategory' => $vat['category'],
				'vatPercent' => $vat['percent'],
				'_vat' => $vat,
			);
		}
		if ($commentCount) {
			$issues[] = "$commentCount comment line(s) with quantity 0 are not invoice lines" . (($commentMode === 'note') ? '; their text is in the line notes' : ' and were left out');
		}
		if ($pendingComments) {
			$issues[] = 'comment text without any invoice line was left out';
		}
		if ($maxSlack > 0.02) {
			$issues[] = 'a line amount is more than 0.02 from quantity x price (PEPPOL-EN16931-R120)';
		}

		// VAT breakdown per category and rate
		$groups = array();
		foreach ($lines as $index => $line) {
			$vat = $line['_vat'];
			unset($lines[$index]['_vat']);
			$key = $vat['category'] . '|' . (($vat['percent'] === null) ? '' : number_format($vat['percent'], 4, '.', ''));
			if (!isset($groups[$key])) {
				$groups[$key] = array('taxableAmount' => 0.0, 'taxAmount' => 0.0, 'category' => $vat['category'], 'percent' => $vat['percent'],
					'exemptionReasonCode' => $vat['exemptionReasonCode'], 'exemptionReason' => $vat['exemptionReason']);
			}
			$groups[$key]['taxableAmount'] += $line['lineExtension'];
		}
		$lines = array_values($lines);
		uasort($groups, function ($a, $b) {
			if ($a['category'] !== $b['category']) {
				return ($a['category'] === 'S') ? -1 : (($b['category'] === 'S') ? 1 : strcmp($a['category'], $b['category']));
			}
			return ((float)$b['percent'] <=> (float)$a['percent']);
		});
		$lineTotal = 0.0;
		$vatTotal = 0.0;
		foreach ($groups as $key => $group) {
			$groups[$key]['taxableAmount'] = peppol_model_round($group['taxableAmount'], 2);
			$groups[$key]['taxAmount'] = ($group['category'] === 'S') ? peppol_model_round($groups[$key]['taxableAmount'] * $group['percent'] / 100, 2) : 0.0;
			$lineTotal += $groups[$key]['taxableAmount'];
			$vatTotal += $groups[$key]['taxAmount'];
		}
		$lineTotal = peppol_model_round($lineTotal, 2);
		$vatTotal = peppol_model_round($vatTotal, 2);

		// Reconciliation with the booked totals
		$sign = $isCredit ? -1 : 1;
		$bookedNet = peppol_model_round($sign * (float)($order['sum'] ?? 0), 2);
		$bookedVat = peppol_model_round($sign * (float)($order['moms'] ?? 0), 2);
		$reconciliation = array('bookedNet' => $bookedNet, 'bookedVat' => $bookedVat, 'computedNet' => $lineTotal, 'computedVat' => $vatTotal,
			'vatAdjustment' => 0.0, 'roundingAmount' => 0.0, 'ok' => true);
		$vatDiff = peppol_model_round($bookedVat - $vatTotal, 2);
		if ($vatDiff != 0) {
			$largest = null;
			foreach ($groups as $key => $group) {
				if ($group['category'] === 'S' && ($largest === null || abs($group['taxableAmount']) > abs($groups[$largest]['taxableAmount']))) {
					$largest = $key;
				}
			}
			if ($largest !== null && abs($vatDiff) < 1) {
				$groups[$largest]['taxAmount'] = peppol_model_round($groups[$largest]['taxAmount'] + $vatDiff, 2);
				$reconciliation['vatAdjustment'] = $vatDiff;
				$vatTotal = peppol_model_round($vatTotal + $vatDiff, 2);
				$issues[] = 'VAT ' . number_format($vatDiff, 2, '.', '') . ' added to the ' . number_format((float)$groups[$largest]['percent'], 2, '.', '') . ' % category to match the booked ordrer.moms';
			} else {
				$issues[] = 'VAT differs from the booked ordrer.moms by ' . number_format($vatDiff, 2, '.', '') . ' and was not adjusted';
			}
		}
		$taxInclusive = peppol_model_round($lineTotal + $vatTotal, 2);
		$rounding = peppol_model_round($bookedNet + $bookedVat - $taxInclusive, 2);
		if ($rounding != 0) {
			if (abs($rounding) <= (float)$opts['maxRounding']) {
				$reconciliation['roundingAmount'] = $rounding;
				$issues[] = 'PayableRoundingAmount ' . number_format($rounding, 2, '.', '') . ' makes the payable amount the booked ordrer.sum + ordrer.moms';
			} else {
				$reconciliation['ok'] = false;
				$issues[] = 'totals differ from the booked ordrer.sum + ordrer.moms by ' . number_format($rounding, 2, '.', '');
				$rounding = 0.0;
			}
		}

		// Settled at posting (bogfor_nu(): Kontant/Kreditkort with an account under DIV 3)
		$terms = strtolower(trim((string)($order['betalingsbet'] ?? '')));
		$paid = $opts['paidAsPrepaid'] && (($terms === 'kontant' && trim((string)($settings['cashAccount'] ?? '')) !== '')
			|| ($terms === 'kreditkort' && trim((string)($settings['cardAccount'] ?? '')) !== ''));
		$payableBeforePrepaid = peppol_model_round($taxInclusive + $rounding, 2);
		$prepaid = $paid ? $payableBeforePrepaid : null;
		$payable = $paid ? 0.0 : $payableBeforePrepaid;
		if ($paid) {
			$issues[] = 'settled at posting (' . $terms . '): sent as prepaid';
		}

		// Payment means and terms (invoices only; DK-R-005 to DK-R-011 apply to invoices)
		$invoiceNumber = trim((string)($order['fakturanr'] ?? ''));
		$paymentMeans = array();
		if (!$isCredit && !$paid) {
			$iban = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($seller['iban'] ?? '')));
			$bic = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($seller['swift'] ?? '')));
			$regNo = preg_replace('/[^0-9]/', '', (string)($seller['bank_reg'] ?? ''));
			$account = preg_replace('/[^0-9]/', '', (string)($seller['bank_konto'] ?? ''));
			$fi = preg_replace('/[^0-9]/', '', (string)($seller['bank_fi'] ?? ''));
			// SST-873: foreign when the customer is abroad or the currency is not the base currency
			$foreign = ($sentCountry !== $sellerCountry) || ($currency !== $baseCurrency);
			if (!$foreign && $opts['fik'] && strlen($fi) === 8 && $invoiceNumber !== '') {
				$fikId = peppol_model_fik_payment_id($order['kontonr'] ?? '', $invoiceNumber, $fikLayout['accountLength'] ?? 0, $fikLayout['invoiceLength'] ?? 14);
				$paymentMeans[] = array('code' => '93', 'paymentId' => '71#' . $fikId, 'payeeAccount' => array('id' => $fi, 'name' => null, 'bic' => null));
			}
			if ($foreign && $iban !== '') {
				$paymentMeans[] = array('code' => '58', 'paymentId' => ($invoiceNumber === '') ? null : $invoiceNumber,
					'payeeAccount' => array('id' => $iban, 'name' => null, 'bic' => ($bic === '') ? null : $bic));
			} elseif ($regNo !== '' && $account !== '') {
				$paymentMeans[] = array('code' => '42', 'paymentId' => ($invoiceNumber === '') ? null : $invoiceNumber,
					'payeeAccount' => array('id' => $account, 'name' => null, 'bic' => $regNo));
			} elseif ($iban !== '') {
				// DK-R-006: code 31 needs the BIC as FinancialInstitutionBranch/ID; 58 has no such rule
				$paymentMeans[] = array('code' => ($bic !== '') ? '31' : '58', 'paymentId' => ($invoiceNumber === '') ? null : $invoiceNumber,
					'payeeAccount' => array('id' => $iban, 'name' => null, 'bic' => ($bic === '') ? null : $bic));
			}
		}
		$dueDate = $isCredit ? null : peppol_model_due_date($order['fakturadate'] ?? null, $order['betalingsbet'] ?? null, $order['betalingsdage'] ?? 0);
		$paymentTerms = $isCredit ? null : peppol_model_payment_terms($order['betalingsbet'] ?? null, $order['betalingsdage'] ?? 0, $paid);

		// References (PEPPOL-EN16931-R003)
		$buyerReference = peppol_model_one_line($order['kundeordnr'] ?? null);
		if ($buyerReference === '0') {
			$buyerReference = null;
		}
		$orderReference = null;
		$salesOrderId = null;
		if ($buyerReference === null) {
			$orderReference = 'NA';
			$salesOrderId = peppol_model_one_line($order['ordrenr'] ?? null);
			$issues[] = 'no kundeordnr: OrderReference NA with our order number as SalesOrderID (R003)';
		}
		$billingReferences = array();
		$credited = $rows['creditedInvoice'] ?? null;
		if ($isCredit && is_array($credited) && trim((string)($credited['fakturanr'] ?? '')) !== '') {
			$billingReferences[] = array('id' => trim((string)$credited['fakturanr']), 'issueDate' => peppol_model_date($credited['fakturadate'] ?? null));
		} elseif ($isCredit) {
			$issues[] = 'credit note without a credited invoice (kred_ord_id): no BillingReference';
		}
		$note = null;
		if (trim((string)($settings['sendOrderNote'] ?? '')) === 'on') {
			$note = peppol_model_text($order['notes'] ?? null);
		}

		// VAT in the base currency (BT-6/BT-111)
		$taxCurrency = null;
		$taxCurrencyTaxAmount = null;
		if ($opts['taxCurrency'] && $currency !== $baseCurrency) {
			$rate = (float)($order['valutakurs'] ?? 0);
			if ($rate > 0) {
				$taxCurrency = $baseCurrency;
				$taxCurrencyTaxAmount = peppol_model_round($vatTotal * $rate / 100, 2);
			} else {
				$issues[] = "no exchange rate on the order: VAT not stated in $baseCurrency";
			}
		}

		return array(
			'documentType' => $documentType,
			'id' => $invoiceNumber,
			'issueDate' => (string)peppol_model_date($order['fakturadate'] ?? null),
			'dueDate' => $dueDate,
			'typeCode' => $isCredit ? '381' : '380',
			'note' => $note,
			'currency' => $currency,
			'taxCurrency' => $taxCurrency,
			'taxCurrencyTaxAmount' => $taxCurrencyTaxAmount,
			'accountingCost' => null,
			'buyerReference' => $buyerReference,
			'orderReference' => $orderReference,
			'salesOrderId' => $salesOrderId,
			'billingReferences' => $billingReferences,
			'seller' => $sellerParty,
			'buyer' => $buyerParty,
			'delivery' => $delivery,
			'paymentMeans' => $paymentMeans,
			'paymentTerms' => $paymentTerms,
			'allowanceCharges' => array(),
			'taxSubtotals' => array_values($groups),
			'taxTotal' => $vatTotal,
			'totals' => array(
				'lineExtension' => $lineTotal,
				'taxExclusive' => $lineTotal,
				'taxInclusive' => $taxInclusive,
				'allowanceTotal' => null,
				'chargeTotal' => null,
				'prepaid' => $prepaid,
				'roundingAmount' => ($rounding != 0) ? $rounding : null,
				'payable' => $payable,
			),
			'lines' => $lines,
			'attachments' => array(),
			'issues' => $issues,
			'reconciliation' => $reconciliation,
		);
	}
}
