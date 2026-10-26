<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/debitorkortIncludes/einvoiceCvrBanner.php --- ver 5.0.0 --- 2026-10-26 ---
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
// 20261023 CL/MMR SD-774 Created. The debitor card shows a warning when a Danish business customer with an EAN number has no CVR number, as the receiver then rejects e-invoices to it.
//                 It shows the same kind of warning when the CVR number is filled in but not valid.
//                 Neither warning blocks or changes the save of the card.
// 20261026 CL/MMR SD-774 The warning covers every card that is not 'privat', as sendInvoice() sends to the card's EAN whatever the type; an empty type and old 'Erhverv' were skipped.
//                 A CVR field holding the EAN/GLN, or no real number such as "-" or "N/A", counts as missing.

include_once(__DIR__ . '/../../includes/stdFunc/einvoiceCvr.php');

if (!function_exists('debitorkortEinvoiceCvrProblem')) {
	/**
	 * SD-774: what is wrong with a debitor card's CVR for e-invoicing, judged on the saved values.
	 *
	 * Every card except a private one ('privat', any case) is checked, including an empty type and
	 * 'Erhverv' from old data: sendInvoice() uses the card's EAN whatever the type, and MEDSHOP sent
	 * 465 of its 2026 e-invoices to cards with an empty type (446 EAN cards have 'Erhverv'). The card
	 * page itself shows the EAN field only for exactly 'erhverv'.
	 * 'missing' is exactly the case debitor/api.php sendInvoice() blocks (einvoice_cvr_missing()).
	 * 'invalid' is a soft warning for a Danish receiver on a GLN whose stored CVR fails the input
	 * check (8 digits with a valid check digit, or a foreign country prefix). Sending is not
	 * blocked for it, because invoices with such stored numbers have been accepted.
	 *
	 * @param string|null $kontotype adresser.kontotype ('erhverv', 'privat', '' or old 'Erhverv'/'Privat').
	 * @param string|null $ean       adresser.ean.
	 * @param string|null $cvr       adresser.cvrnr.
	 * @param string|null $land      adresser.land as stored, not HTML-escaped.
	 * @return string '' when there is nothing to warn about, otherwise 'missing' or 'invalid'.
	 */
	function debitorkortEinvoiceCvrProblem($kontotype, $ean, $cvr, $land) {
		// Only a private card is skipped: an empty type or old 'Erhverv' still gets e-invoices sent to its EAN.
		if (strtolower(trim((string)$kontotype)) === 'privat') {
			return '';
		}
		if (einvoice_cvr_missing($ean, $cvr, $land)) {
			return 'missing';
		}
		if (einvoice_normalize_cvr($cvr) === '' || !einvoice_is_gln_endpoint($ean)) {
			return '';
		}
		if (einvoice_receiver_country($land, $cvr) !== 'DK') {
			return '';
		}
		return (einvoice_cvr_input_problem($cvr) === 'invalid') ? 'invalid' : '';
	}
}

if (!function_exists('debitorkortEinvoiceCvrBanner')) {
	/**
	 * SD-774: the warning shown at the top of the debitor card when e-invoices to the customer
	 * lack a valid CVR number (see debitorkortEinvoiceCvrProblem()). A missing CVR gets a link to
	 * the NemHandel register, where the CVR registered on the EAN can be looked up.
	 *
	 * The texts come from findtekst() in the database encoding, so they are escaped with the
	 * page charset, which follows the same encoding.
	 *
	 * @param string|null $kontotype adresser.kontotype ('erhverv', 'privat', '' or old 'Erhverv'/'Privat').
	 * @param string|null $ean       adresser.ean.
	 * @param string|null $cvr       adresser.cvrnr.
	 * @param string|null $land      adresser.land as stored, not HTML-escaped.
	 * @param int         $sprog_id  Language id for findtekst().
	 * @param string      $charset   Page charset for htmlspecialchars(): 'UTF-8' or 'ISO-8859-1'.
	 * @return string The banner HTML, or '' when there is nothing to warn about.
	 */
	function debitorkortEinvoiceCvrBanner($kontotype, $ean, $cvr, $land, $sprog_id, $charset) {
		$problem = debitorkortEinvoiceCvrProblem($kontotype, $ean, $cvr, $land);
		if ($problem === '') {
			return '';
		}
		$flags = ENT_QUOTES | ENT_SUBSTITUTE;
		if ($problem === 'missing') {
			$text = findtekst('5168|Kunden har EAN-nummer, men intet CVR-nummer, eller CVR-feltet indeholder EAN-nummeret. E-fakturaer til kunden bliver afvist, indtil CVR-nummeret er udfyldt.', $sprog_id);
		} else {
			$text = findtekst('5169|CVR-nummeret er ikke gyldigt. Et dansk CVR-nummer har 8 cifre, og kontrolcifferet skal passe. Kunder i udlandet skal have momsnummer med landekode, fx SE232100025501.', $sprog_id);
		}
		$html = "<div class='einvoice-cvr-warning' role='alert' style='padding:8px 12px; color:#856404; ";
		$html .= "background-color:#fff3cd; border:1px solid #ffeeba; border-radius:4px;'>";
		$html .= htmlspecialchars($text, $flags, $charset);
		// Both cases: the right CVR is the one registered on the EAN, so offer the lookup.
		$linkText = findtekst('5163|Slå CVR op i NemHandelsregisteret', $sprog_id);
		$html .= " <a href='" . htmlspecialchars(einvoice_nemhandel_lookup_url(), $flags, $charset) . "'";
		$html .= " target='_blank' rel='noopener' style='color:#533f03; text-decoration:underline;'>";
		$html .= htmlspecialchars($linkText, $flags, $charset) . "</a>";
		$html .= "</div>";
		return $html;
	}
}
