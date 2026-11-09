<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoice/peppolPrecheck.php --- ver 5.1.0 --- 2026-11-09 ---
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
// 20261109 CL/MMR SD-815 Created (draft). Danish messages about what the user must fix before an e-invoice can be sent.
//                 Checks the document model before the XML is built, so the user gets a clear message instead of a rejection from Peppol.
// 20261109 CL/MMR SD-815 Review fixes: the format of every endpoint scheme with a fatal Peppol rule (0096, 0198, 0208, 0151 too), and the seller's own country's VAT number on an EU sale without VAT.
//                 A line whose quantity x price cannot reach its amount within 0.02 (PEPPOL-EN16931-R120) stops the send too.

include_once(__DIR__ . '/peppolDocumentModel.php');

if (!function_exists('peppol_precheck_amount')) {
	/**
	 * An amount in Danish notation for a message, e.g. "1.234,50".
	 *
	 * @param int|float|null $amount The amount.
	 * @return string
	 */
	function peppol_precheck_amount($amount) {
		return number_format((float)$amount, 2, ',', '.');
	}
}

if (!function_exists('peppol_precheck_endpoint_message')) {
	/**
	 * The message for a customer endpoint whose format peppol_model_identifier_valid() rejects.
	 *
	 * @param string $scheme     The EAS code.
	 * @param string $endpointId The identifier.
	 * @return string
	 */
	function peppol_precheck_endpoint_message($scheme, $endpointId) {
		switch ($scheme) {
			case '0088':
				return "EAN-nummeret $endpointId er ikke gyldigt. Et EAN-nummer har 13 cifre, og det sidste er et kontrolciffer.";
			case '0184':
				return "Kundens CVR-nummer $endpointId er ikke et gyldigt dansk CVR-nummer med 8 cifre.";
			case '0192':
			case '0007':
				return "Kundens organisationsnummer $endpointId er ikke gyldigt (forkert antal cifre eller kontrolciffer). Er det et dansk CVR-nummer, så skriv DK foran det i CVR-feltet.";
			case '0096':
				return "P-nummeret $endpointId i EAN-feltet er ikke gyldigt. Et P-nummer har 10 cifre.";
			case '0198':
				return "SE-nummeret $endpointId i EAN-feltet er ikke gyldigt. Et SE-nummer har 8 cifre.";
		}
		return "Kundens elektroniske adresse $scheme:$endpointId har ikke det format, Peppol kræver for skemaet $scheme (forkert antal cifre eller kontrolciffer). Ret EAN-feltet på ordren eller debitorkortet.";
	}
}

if (!function_exists('peppol_precheck')) {
	/**
	 * What the user must fix before the document can be sent, as Danish sentences; empty when it can be sent.
	 *
	 * Pure: reads only the model from peppol_document_model(). Covers the data the Peppol rules need that Saldi cannot make up:
	 * the invoice number and date, Stamdata (CVR, address, bank details), the customer's identifiers and address, the
	 * SD-774 rule (a Danish customer reached on an EAN must have a CVR, DK-R-017), VAT numbers for EU and reverse-charge
	 * sales (and not the seller's own country's number on an EU sale without VAT), DK-R-016 (no negative credit note to a
	 * Danish customer), totals that do not match the posting, and a line that would break PEPPOL-EN16931-R120.
	 *
	 * TODO SD-815: the texts are plain Danish. Before release they need findtekst() keys (importfiler/tekster_keyed.tsv,
	 * convention_findtekst_keys.md) with English and Norwegian; $sprog_id is kept for that. 5166/5167 already hold the SD-774 text.
	 *
	 * @param array      $model    From peppol_document_model().
	 * @param int|string $sprog_id The user's language, for the coming findtekst() keys.
	 * @return list<string> One message per problem.
	 */
	function peppol_precheck(array $model, $sprog_id = 1): array {
		$messages = array();
		$seller = is_array($model['seller'] ?? null) ? $model['seller'] : array();
		$buyer = is_array($model['buyer'] ?? null) ? $model['buyer'] : array();
		$lines = is_array($model['lines'] ?? null) ? $model['lines'] : array();
		$isCredit = (($model['documentType'] ?? '') === 'creditnote');
		$sellerCountry = (string)($seller['address']['country'] ?? '');
		$buyerCountry = (string)($buyer['address']['country'] ?? '');
		$payable = (float)($model['totals']['payable'] ?? 0);

		if (trim((string)($model['id'] ?? '')) === '' || trim((string)($model['issueDate'] ?? '')) === '') {
			$messages[] = 'Ordren er ikke faktureret endnu: der mangler fakturanummer eller fakturadato.';
		}

		// Stamdata
		if (trim((string)($seller['registrationName'] ?? '')) === '') {
			$messages[] = 'Firmanavnet mangler under Indstillinger > Stamdata.';
		}
		// DK-R-002/DK-R-014: a Danish seller must state its CVR; any seller needs an electronic address
		if (($sellerCountry === 'DK' && ($seller['legalId'] ?? null) === null) || trim((string)($seller['endpoint']['id'] ?? '')) === '') {
			$messages[] = 'Firmaets CVR-nummer mangler eller har ikke 8 cifre. Ret det under Indstillinger > Stamdata.';
		}
		$sellerAddress = $seller['address'] ?? array();
		if (($sellerAddress['street'] ?? null) === null || ($sellerAddress['city'] ?? null) === null || ($sellerAddress['postalCode'] ?? null) === null) {
			$messages[] = 'Firmaets adresse (vej, postnr. og by) skal udfyldes under Indstillinger > Stamdata.';
		}

		// The customer
		if (trim((string)($buyer['registrationName'] ?? '')) === '') {
			$messages[] = 'Kundens navn mangler på ordren.';
		}
		$scheme = (string)($buyer['endpoint']['scheme'] ?? '');
		$endpointId = (string)($buyer['endpoint']['id'] ?? '');
		if ($scheme === '' || $endpointId === '') {
			$messages[] = 'Kunden mangler både CVR-nummer og EAN-nummer. Mindst ét af dem skal stå på ordren eller debitorkortet.';
		} elseif (!in_array($scheme, peppol_model_eas_codes(), true)) {
			$messages[] = "EAN-feltet indeholder et ukendt Peppol-skema ($scheme). Skriv EAN-nummeret alene, eller fx 0088:<EAN> eller 0184:<CVR>.";
		} elseif (!peppol_model_identifier_valid($scheme, $endpointId)) {
			// PEPPOL-COMMON-R040 to R043, R049, R050, R052, R053: format and check digit of the scheme
			$messages[] = peppol_precheck_endpoint_message($scheme, $endpointId);
		}
		// SD-774 (DK-R-017 / E-APS24003): a Danish customer reached on an EAN must have a CVR on the invoice
		if ($scheme === '0088' && $buyerCountry === 'DK' && ($buyer['legalId'] ?? null) === null) {
			$messages[] = "Modtageren har EAN-nummer $endpointId, men der er ikke angivet noget gyldigt CVR-nummer, eller CVR-feltet indeholder EAN-nummeret. E-fakturaer til et EAN-nummer skal have modtagerens CVR-nummer, ellers bliver de afvist. Indtast CVR-nummeret på ordren eller debitorkortet, og send igen.";
		}
		$buyerAddress = $buyer['address'] ?? array();
		$missing = array();
		if (($buyerAddress['street'] ?? null) === null) {
			$missing[] = 'vej';
		}
		if (($buyerAddress['postalCode'] ?? null) === null) {
			$missing[] = 'postnummer';
		}
		if (($buyerAddress['city'] ?? null) === null) {
			$missing[] = 'by';
		}
		if ($missing) {
			$last = array_pop($missing);
			$messages[] = 'Kundens adresse mangler ' . ($missing ? implode(', ', $missing) . ' og ' : '') . $last . '. Udfyld den på ordren eller debitorkortet.';
		}

		// Lines and VAT
		if (!$lines) {
			$messages[] = 'Ordren har ingen varelinjer at sende.';
		}
		$categories = array();
		foreach ($lines as $line) {
			$categories[(string)($line['vatCategory'] ?? '')] = true;
		}
		$buyerVatId = trim((string)($buyer['vatId'] ?? ''));
		if (isset($categories['K']) && $buyerVatId === '') {
			$messages[] = 'Kunden skal have sit momsnummer (VAT-nummer med landekode) i CVR-feltet, når der sælges uden moms til et andet EU-land.';
		}
		if (isset($categories['AE']) && $buyerVatId === '' && ($buyer['legalId'] ?? null) === null) {
			$messages[] = 'Ved omvendt betalingspligt skal kundens CVR- eller momsnummer stå på ordren eller debitorkortet.';
		}
		// A sale without VAT to a company in another EU country needs that company's own VAT number: one with the seller's
		// country prefix (a Danish number on a delivery to Sweden) does not support it. Domestic reverse charge (AE to a
		// customer in the seller's country) carries a Danish number by nature.
		$buyerVatPrefix = strtoupper(substr($buyerVatId, 0, 2));
		$crossBorderAe = isset($categories['AE']) && $buyerCountry !== '' && $buyerCountry !== $sellerCountry;
		if ($buyerVatId !== '' && $sellerCountry !== '' && $buyerVatPrefix === $sellerCountry && (isset($categories['K']) || $crossBorderAe)) {
			$messages[] = "Kundens momsnummer $buyerVatId er fra samme land som firmaet, men fakturaen er uden moms som salg til et andet EU-land. Skriv kundens eget momsnummer med landekode i CVR-feltet (fx SE556677889901), eller kontrollér kundens land og debitorgruppe.";
		}

		// Payment and totals
		if (!$isCredit && $payable > 0 && empty($model['paymentMeans'])) {
			$messages[] = 'Der er ingen betalingsoplysninger. Udfyld reg.nr. og kontonr. eller IBAN under Indstillinger > Stamdata.';
		}
		if ($isCredit && $payable < 0 && $sellerCountry === 'DK' && $buyerCountry === 'DK') {
			$messages[] = 'Kreditnotaen giver i alt ' . peppol_precheck_amount($payable) . ' og kan ikke sendes digitalt til en dansk modtager med et negativt beløb. Send den som en faktura i stedet.';
		}
		if (!preg_match('/^[A-Z]{3}$/', (string)($model['currency'] ?? ''))) {
			$messages[] = 'Ordrens valuta (' . (string)($model['currency'] ?? '') . ') er ikke en gyldig valutakode.';
		}
		$reconciliation = $model['reconciliation'] ?? null;
		if (is_array($reconciliation) && empty($reconciliation['ok'])) {
			$difference = (float)$reconciliation['bookedNet'] + (float)$reconciliation['bookedVat'] - (float)$reconciliation['computedNet'] - (float)$reconciliation['computedVat'];
			$messages[] = 'Fakturaens linjer giver ikke det bogførte beløb (forskel ' . peppol_precheck_amount($difference) . '). Fakturaen er ikke sendt. Kontakt saldi.dk support.';
		}
		if (is_array($reconciliation) && (float)($reconciliation['maxLineSlack'] ?? 0) > 0.02) {
			// PEPPOL-EN16931-R120: the receiver would reject it
			$messages[] = 'En linje på fakturaen kan ikke angives præcist nok som antal gange pris i e-fakturaen (afvigelse over 0,02). Fakturaen er ikke sendt. Kontakt saldi.dk support.';
		}

		return array_values(array_unique($messages));
	}
}
