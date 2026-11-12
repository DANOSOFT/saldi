<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoice/peppolBisWriter.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261109 CL/MMR SD-815 Created (draft). Writes the document model as a Peppol BIS Billing 3.0 Invoice or CreditNote (UBL 2.1 XML).
//                 The writer is pure: no database and no globals, so it is tested against golden files that pass the Peppol validator.
// 20261109 CL/MMR SD-815 Unit prices are written with up to 8 decimals, so a returned item's net price can keep PEPPOL-EN16931-R120.
//
/*
 * The model is documented at peppol_model_example() in includes/einvoice/peppolModel.php.
 * The writer only maps it to XML: it does not compute totals, VAT or identifiers. Elements are written in UBL 2.1 schema
 * order, an optional value that is null or '' leaves its element out (PEPPOL-EN16931-R008), amounts get 2 decimals
 * and prices 2 to 8. An optional key that is missing counts as null.
 */

if (!defined('PEPPOL_BIS_NS_CAC')) {
	define('PEPPOL_BIS_NS_CAC', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
	define('PEPPOL_BIS_NS_CBC', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
	define('PEPPOL_BIS_NS_INVOICE', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
	define('PEPPOL_BIS_NS_CREDITNOTE', 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2');
	define('PEPPOL_BIS_CUSTOMIZATION_ID', 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0');
	define('PEPPOL_BIS_PROFILE_ID', 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0');
}

if (!function_exists('peppol_bis_xml')) {
	/**
	 * The Peppol BIS Billing 3.0 XML for a document model.
	 *
	 * documentType 'invoice' gives an Invoice (InvoiceLine, InvoicedQuantity, cbc:DueDate), 'creditnote' a CreditNote
	 * (CreditNoteLine, CreditedQuantity, no cbc:DueDate; a due date given anyway goes in the first PaymentMeans as
	 * PaymentDueDate, which is where BIS 3 puts BT-9 on a credit note).
	 *
	 * @param array $model The document model, see peppol_model_example() in includes/einvoice/peppolModel.php.
	 * @return string The XML document, UTF-8, with an XML declaration.
	 * @throws InvalidArgumentException When peppol_bis_validate_model() reports a missing required value.
	 * @throws RuntimeException When the PHP DOM extension (php-xml) is missing or the XML cannot be serialised.
	 */
	function peppol_bis_xml(array $model): string {
		$errors = peppol_bis_validate_model($model);
		if ($errors) {
			throw new InvalidArgumentException('peppol_bis_xml(): invalid document model: ' . implode('; ', $errors));
		}
		if (!class_exists('DOMDocument')) {
			throw new RuntimeException('peppol_bis_xml(): the PHP DOM extension (php-xml) is not installed.');
		}

		$isCredit = ($model['documentType'] === 'creditnote');
		$currency = peppol_bis_text($model['currency']);
		$taxCurrency = peppol_bis_text($model['taxCurrency'] ?? null);

		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->formatOutput = true;
		$root = $doc->createElementNS($isCredit ? PEPPOL_BIS_NS_CREDITNOTE : PEPPOL_BIS_NS_INVOICE, $isCredit ? 'CreditNote' : 'Invoice');
		$doc->appendChild($root);
		$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', PEPPOL_BIS_NS_CAC);
		$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', PEPPOL_BIS_NS_CBC);

		peppol_bis_add($root, 'cbc:CustomizationID', PEPPOL_BIS_CUSTOMIZATION_ID);
		peppol_bis_add($root, 'cbc:ProfileID', PEPPOL_BIS_PROFILE_ID);
		peppol_bis_add($root, 'cbc:ID', $model['id']);
		peppol_bis_add($root, 'cbc:IssueDate', $model['issueDate']);
		if (!$isCredit) {
			peppol_bis_add($root, 'cbc:DueDate', $model['dueDate'] ?? null);
		}
		peppol_bis_add($root, $isCredit ? 'cbc:CreditNoteTypeCode' : 'cbc:InvoiceTypeCode', $model['typeCode']);
		peppol_bis_add($root, 'cbc:Note', $model['note'] ?? null);
		peppol_bis_add($root, 'cbc:DocumentCurrencyCode', $currency);
		peppol_bis_add($root, 'cbc:TaxCurrencyCode', $taxCurrency);
		peppol_bis_add($root, 'cbc:AccountingCost', $model['accountingCost'] ?? null);
		peppol_bis_add($root, 'cbc:BuyerReference', $model['buyerReference'] ?? null);

		// BT-14 is written only together with BT-13, see the model.
		if (peppol_bis_text($model['orderReference'] ?? null) !== '') {
			$orderReference = peppol_bis_add($root, 'cac:OrderReference');
			peppol_bis_add($orderReference, 'cbc:ID', $model['orderReference']);
			peppol_bis_add($orderReference, 'cbc:SalesOrderID', $model['salesOrderId'] ?? null);
		}
		foreach ($model['billingReferences'] ?? array() as $reference) {
			$invoiceReference = peppol_bis_add(peppol_bis_add($root, 'cac:BillingReference'), 'cac:InvoiceDocumentReference');
			peppol_bis_add($invoiceReference, 'cbc:ID', $reference['id']);
			peppol_bis_add($invoiceReference, 'cbc:IssueDate', $reference['issueDate'] ?? null);
		}
		foreach ($model['attachments'] ?? array() as $attachment) {
			$documentReference = peppol_bis_add($root, 'cac:AdditionalDocumentReference');
			peppol_bis_add($documentReference, 'cbc:ID', $attachment['id']);
			peppol_bis_add($documentReference, 'cbc:DocumentDescription', $attachment['description'] ?? null);
			peppol_bis_add(peppol_bis_add($documentReference, 'cac:Attachment'), 'cbc:EmbeddedDocumentBinaryObject', $attachment['base64'],
				array('mimeCode' => $attachment['mimeCode'], 'filename' => $attachment['filename']));
		}

		peppol_bis_party(peppol_bis_add($root, 'cac:AccountingSupplierParty'), $model['seller']);
		peppol_bis_party(peppol_bis_add($root, 'cac:AccountingCustomerParty'), $model['buyer']);

		$delivery = $model['delivery'] ?? null;
		if (is_array($delivery)) {
			$deliveryElement = peppol_bis_add($root, 'cac:Delivery');
			peppol_bis_add($deliveryElement, 'cbc:ActualDeliveryDate', $delivery['date'] ?? null);
			if (is_array($delivery['address'] ?? null)) {
				peppol_bis_address(peppol_bis_add(peppol_bis_add($deliveryElement, 'cac:DeliveryLocation'), 'cac:Address'), $delivery['address']);
			}
			peppol_bis_add(peppol_bis_add(peppol_bis_add($deliveryElement, 'cac:DeliveryParty'), 'cac:PartyName'), 'cbc:Name', $delivery['name'] ?? null);
		}

		$creditNoteDueDate = $isCredit ? ($model['dueDate'] ?? null) : null;
		foreach ($model['paymentMeans'] ?? array() as $means) {
			$meansElement = peppol_bis_add($root, 'cac:PaymentMeans');
			peppol_bis_add($meansElement, 'cbc:PaymentMeansCode', $means['code']);
			// UBL-SR-45: the due date may occur once, so only the first PaymentMeans carries it.
			peppol_bis_add($meansElement, 'cbc:PaymentDueDate', $creditNoteDueDate);
			$creditNoteDueDate = null;
			peppol_bis_add($meansElement, 'cbc:PaymentID', $means['paymentId'] ?? null);
			$account = $means['payeeAccount'] ?? null;
			if (is_array($account)) {
				$accountElement = peppol_bis_add($meansElement, 'cac:PayeeFinancialAccount');
				peppol_bis_add($accountElement, 'cbc:ID', $account['id']);
				peppol_bis_add($accountElement, 'cbc:Name', $account['name'] ?? null);
				peppol_bis_add(peppol_bis_add($accountElement, 'cac:FinancialInstitutionBranch'), 'cbc:ID', $account['bic'] ?? null);
			}
		}
		peppol_bis_add(peppol_bis_add($root, 'cac:PaymentTerms'), 'cbc:Note', $model['paymentTerms'] ?? null);

		foreach ($model['allowanceCharges'] ?? array() as $allowanceCharge) {
			$element = peppol_bis_allowance_charge($root, $allowanceCharge, $currency);
			peppol_bis_tax_category(peppol_bis_add($element, 'cac:TaxCategory'), $allowanceCharge['vatCategory'], $allowanceCharge['vatPercent'] ?? null);
		}

		$taxTotal = peppol_bis_add($root, 'cac:TaxTotal');
		peppol_bis_amount($taxTotal, 'cbc:TaxAmount', $model['taxTotal'], $currency);
		foreach ($model['taxSubtotals'] as $subtotal) {
			$subtotalElement = peppol_bis_add($taxTotal, 'cac:TaxSubtotal');
			peppol_bis_amount($subtotalElement, 'cbc:TaxableAmount', $subtotal['taxableAmount'], $currency);
			peppol_bis_amount($subtotalElement, 'cbc:TaxAmount', $subtotal['taxAmount'], $currency);
			$category = peppol_bis_add($subtotalElement, 'cac:TaxCategory');
			peppol_bis_tax_category($category, $subtotal['category'], $subtotal['percent'] ?? null, $subtotal['exemptionReasonCode'] ?? null, $subtotal['exemptionReason'] ?? null);
		}
		// BT-111: the VAT total in the VAT accounting currency, a second TaxTotal without subtotals (PEPPOL-EN16931-R054).
		if ($taxCurrency !== '') {
			peppol_bis_amount(peppol_bis_add($root, 'cac:TaxTotal'), 'cbc:TaxAmount', $model['taxCurrencyTaxAmount'], $taxCurrency);
		}

		$totals = $model['totals'];
		$monetaryTotal = peppol_bis_add($root, 'cac:LegalMonetaryTotal');
		peppol_bis_amount($monetaryTotal, 'cbc:LineExtensionAmount', $totals['lineExtension'], $currency);
		peppol_bis_amount($monetaryTotal, 'cbc:TaxExclusiveAmount', $totals['taxExclusive'], $currency);
		peppol_bis_amount($monetaryTotal, 'cbc:TaxInclusiveAmount', $totals['taxInclusive'], $currency);
		peppol_bis_amount($monetaryTotal, 'cbc:AllowanceTotalAmount', $totals['allowanceTotal'] ?? null, $currency);
		peppol_bis_amount($monetaryTotal, 'cbc:ChargeTotalAmount', $totals['chargeTotal'] ?? null, $currency);
		peppol_bis_amount($monetaryTotal, 'cbc:PrepaidAmount', $totals['prepaid'] ?? null, $currency);
		peppol_bis_amount($monetaryTotal, 'cbc:PayableRoundingAmount', $totals['roundingAmount'] ?? null, $currency);
		peppol_bis_amount($monetaryTotal, 'cbc:PayableAmount', $totals['payable'], $currency);

		foreach ($model['lines'] as $line) {
			peppol_bis_line($root, $line, $currency, $isCredit);
		}

		peppol_bis_prune_empty($root);
		$xml = $doc->saveXML();
		if ($xml === false) {
			throw new RuntimeException('peppol_bis_xml(): the XML could not be serialised.');
		}
		return $xml;
	}
}

if (!function_exists('peppol_bis_validate_model')) {
	/**
	 * The required values the document model lacks, for developers: the builder must never produce such a model.
	 *
	 * Checks the keys the writer cannot do without (and the values a required element is written from), not the
	 * business rules: those belong to peppol_precheck() and the Peppol validator. A value counts as missing when the
	 * key is absent, null or blank; a number also when it is not numeric. An optional number that is given must be numeric.
	 *
	 * @param array $model The document model, see peppol_model_example() in includes/einvoice/peppolModel.php.
	 * @return list<string> One English message per problem, e.g. "buyer.endpoint.id is missing"; empty when the model is complete.
	 */
	function peppol_bis_validate_model(array $model): array {
		$errors = array();
		$documentType = $model['documentType'] ?? null;
		if (peppol_bis_missing_text($documentType)) {
			$errors[] = 'documentType is missing';
		} elseif ($documentType !== 'invoice' && $documentType !== 'creditnote') {
			$errors[] = "documentType must be 'invoice' or 'creditnote'";
		}
		foreach (array('id', 'issueDate', 'typeCode', 'currency') as $key) {
			if (peppol_bis_missing_text($model[$key] ?? null)) $errors[] = "$key is missing";
		}
		if (!peppol_bis_missing_text($model['taxCurrency'] ?? null) && peppol_bis_missing_number($model['taxCurrencyTaxAmount'] ?? null)) {
			$errors[] = 'taxCurrencyTaxAmount is missing (required when taxCurrency is set)';
		}
		foreach (peppol_bis_list($model, 'billingReferences', $errors) as $i => $reference) {
			if (peppol_bis_missing_text($reference['id'] ?? null)) $errors[] = "billingReferences[$i].id is missing";
		}
		foreach (array('seller', 'buyer') as $partyKey) {
			$party = $model[$partyKey] ?? null;
			if (!is_array($party)) {
				$errors[] = "$partyKey is missing";
				continue;
			}
			foreach (array('scheme', 'id') as $key) {
				if (peppol_bis_missing_text($party['endpoint'][$key] ?? null)) $errors[] = "$partyKey.endpoint.$key is missing";
			}
			if (peppol_bis_missing_text($party['registrationName'] ?? null)) $errors[] = "$partyKey.registrationName is missing";
			if (!is_array($party['address'] ?? null)) {
				$errors[] = "$partyKey.address is missing";
			} elseif (peppol_bis_missing_text($party['address']['country'] ?? null)) {
				$errors[] = "$partyKey.address.country is missing";
			}
			foreach (peppol_bis_list($party, 'identifiers', $errors, "$partyKey.") as $i => $identifier) {
				if (peppol_bis_missing_text($identifier['id'] ?? null)) $errors[] = "$partyKey.identifiers[$i].id is missing";
			}
			if (is_array($party['legalId'] ?? null) && peppol_bis_missing_text($party['legalId']['id'] ?? null)) {
				$errors[] = "$partyKey.legalId.id is missing";
			}
		}
		$delivery = $model['delivery'] ?? null;
		if (is_array($delivery) && is_array($delivery['address'] ?? null) && peppol_bis_missing_text($delivery['address']['country'] ?? null)) {
			$errors[] = 'delivery.address.country is missing';
		}
		foreach (peppol_bis_list($model, 'paymentMeans', $errors) as $i => $means) {
			if (peppol_bis_missing_text($means['code'] ?? null)) $errors[] = "paymentMeans[$i].code is missing";
			if (is_array($means['payeeAccount'] ?? null) && peppol_bis_missing_text($means['payeeAccount']['id'] ?? null)) {
				$errors[] = "paymentMeans[$i].payeeAccount.id is missing";
			}
		}
		foreach (peppol_bis_list($model, 'allowanceCharges', $errors) as $i => $allowanceCharge) {
			if (!is_bool($allowanceCharge['isCharge'] ?? null)) $errors[] = "allowanceCharges[$i].isCharge is missing (must be a bool)";
			if (peppol_bis_missing_number($allowanceCharge['amount'] ?? null)) $errors[] = "allowanceCharges[$i].amount is missing";
			if (peppol_bis_missing_text($allowanceCharge['vatCategory'] ?? null)) $errors[] = "allowanceCharges[$i].vatCategory is missing";
			foreach (array('baseAmount', 'percent', 'vatPercent') as $key) {
				if (peppol_bis_bad_number($allowanceCharge[$key] ?? null)) $errors[] = "allowanceCharges[$i].$key must be a number";
			}
		}
		if (!isset($model['taxSubtotals']) || !is_array($model['taxSubtotals']) || !$model['taxSubtotals']) {
			$errors[] = 'taxSubtotals is missing (at least one VAT breakdown)';
		} else {
			foreach ($model['taxSubtotals'] as $i => $subtotal) {
				foreach (array('taxableAmount', 'taxAmount') as $key) {
					if (peppol_bis_missing_number($subtotal[$key] ?? null)) $errors[] = "taxSubtotals[$i].$key is missing";
				}
				if (peppol_bis_missing_text($subtotal['category'] ?? null)) $errors[] = "taxSubtotals[$i].category is missing";
				if (peppol_bis_bad_number($subtotal['percent'] ?? null)) $errors[] = "taxSubtotals[$i].percent must be a number";
			}
		}
		if (peppol_bis_missing_number($model['taxTotal'] ?? null)) $errors[] = 'taxTotal is missing';
		if (!is_array($model['totals'] ?? null)) {
			$errors[] = 'totals is missing';
		} else {
			foreach (array('lineExtension', 'taxExclusive', 'taxInclusive', 'payable') as $key) {
				if (peppol_bis_missing_number($model['totals'][$key] ?? null)) $errors[] = "totals.$key is missing";
			}
			foreach (array('allowanceTotal', 'chargeTotal', 'prepaid', 'roundingAmount') as $key) {
				if (peppol_bis_bad_number($model['totals'][$key] ?? null)) $errors[] = "totals.$key must be a number";
			}
		}
		if (!isset($model['lines']) || !is_array($model['lines']) || !$model['lines']) {
			$errors[] = 'lines is missing (at least one line)';
		} else {
			foreach ($model['lines'] as $i => $line) {
				foreach (array('id', 'unitCode', 'itemName', 'vatCategory') as $key) {
					if (peppol_bis_missing_text($line[$key] ?? null)) $errors[] = "lines[$i].$key is missing";
				}
				foreach (array('quantity', 'lineExtension', 'netPrice') as $key) {
					if (peppol_bis_missing_number($line[$key] ?? null)) $errors[] = "lines[$i].$key is missing";
				}
				foreach (array('baseQuantity', 'grossPrice', 'priceDiscount', 'vatPercent') as $key) {
					if (peppol_bis_bad_number($line[$key] ?? null)) $errors[] = "lines[$i].$key must be a number";
				}
				foreach (peppol_bis_list($line, 'allowanceCharges', $errors, "lines[$i].") as $j => $allowanceCharge) {
					if (!is_bool($allowanceCharge['isCharge'] ?? null)) $errors[] = "lines[$i].allowanceCharges[$j].isCharge is missing (must be a bool)";
					if (peppol_bis_missing_number($allowanceCharge['amount'] ?? null)) $errors[] = "lines[$i].allowanceCharges[$j].amount is missing";
					foreach (array('baseAmount', 'percent') as $key) {
						if (peppol_bis_bad_number($allowanceCharge[$key] ?? null)) $errors[] = "lines[$i].allowanceCharges[$j].$key must be a number";
					}
				}
				// BT-147 is written as Price/AllowanceCharge/Amount next to BT-148 as its BaseAmount (PEPPOL-EN16931-R046).
				if (!peppol_bis_missing_number($line['priceDiscount'] ?? null) && peppol_bis_missing_number($line['grossPrice'] ?? null)) {
					$errors[] = "lines[$i].grossPrice is missing (required when priceDiscount is set)";
				}
				$standardItemId = $line['standardItemId'] ?? null;
				if (is_array($standardItemId) && (peppol_bis_missing_text($standardItemId['scheme'] ?? null) || peppol_bis_missing_text($standardItemId['id'] ?? null))) {
					$errors[] = "lines[$i].standardItemId needs both scheme and id";
				}
			}
		}
		foreach (peppol_bis_list($model, 'attachments', $errors) as $i => $attachment) {
			foreach (array('id', 'mimeCode', 'filename', 'base64') as $key) {
				if (peppol_bis_missing_text($attachment[$key] ?? null)) $errors[] = "attachments[$i].$key is missing";
			}
		}
		return $errors;
	}
}

if (!function_exists('peppol_bis_text')) {
	/**
	 * A model value as XML-safe text: UTF-8, without control characters (none that XML 1.0 forbids), line breaks as LF, trimmed.
	 *
	 * Text that is not valid UTF-8 is read as Windows-1252, as db_escape_string() does (includes/db_query.php).
	 * The builder should already deliver UTF-8; this keeps a stray Latin byte from breaking the document.
	 *
	 * @param mixed $value A string or number; null, arrays and objects give ''.
	 * @return string The cleaned text; '' means "leave the element out".
	 */
	function peppol_bis_text($value): string {
		if ($value === null || is_array($value) || is_object($value)) return '';
		if (is_bool($value)) return $value ? 'true' : 'false';
		$text = (string) $value;
		if (!mb_check_encoding($text, 'UTF-8')) {
			$text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
		}
		$text = str_replace(array("\r\n", "\r"), "\n", $text);
		// Keeps what the XML 1.0 Char production allows, except DEL and the C1 controls (U+007F-U+009F), which XML allows
		// but which are never meaningful in invoice text; control characters, U+FFFE and U+FFFF are dropped.
		$text = preg_replace('/[^\x{9}\x{A}\x{20}-\x{7E}\x{A0}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);
		return trim((string) $text);
	}
}

if (!function_exists('peppol_bis_decimal')) {
	/**
	 * A number as an XML decimal: '.' as separator, no thousands separator, never "-0".
	 *
	 * Rounded to $maxDecimals; trailing zeros beyond $minDecimals are dropped, so a price 12.3450 reads 12.345
	 * and an amount 75 reads 75.00.
	 *
	 * @param int|float|string $value       The number (a numeric string is accepted).
	 * @param int              $minDecimals Decimals always written.
	 * @param int              $maxDecimals Decimals at most.
	 * @return string The formatted number.
	 */
	function peppol_bis_decimal($value, int $minDecimals, int $maxDecimals): string {
		$text = number_format(round((float) $value, $maxDecimals), $maxDecimals, '.', '');
		if ($maxDecimals > $minDecimals && strpos($text, '.') !== false) {
			list($whole, $fraction) = explode('.', $text);
			$fraction = str_pad(rtrim($fraction, '0'), $minDecimals, '0');
			$text = ($fraction === '') ? $whole : $whole . '.' . $fraction;
		}
		if (preg_match('/^-0(\.0*)?$/', $text)) {
			$text = substr($text, 1);
		}
		return $text;
	}
}

if (!function_exists('peppol_bis_add')) {
	/**
	 * Appends a cbc: or cac: element to $parent.
	 *
	 * With $text null the element is an aggregate and is always appended (peppol_bis_prune_empty() removes it again if
	 * nothing is put in it). With $text given, the element is left out when the cleaned text is '', and the text goes in
	 * through a text node, so DOM escapes it. Attributes whose cleaned value is '' are left out.
	 *
	 * @param DOMElement|null      $parent        The parent; null (a left-out parent) appends nothing.
	 * @param string               $qualifiedName 'cbc:Name' or 'cac:Name'.
	 * @param mixed                $text          The element's text, or null for an aggregate.
	 * @param array<string, mixed> $attributes    Attribute name => value.
	 * @return DOMElement|null The new element, or null when it was left out.
	 */
	function peppol_bis_add(?DOMElement $parent, string $qualifiedName, $text = null, array $attributes = array()): ?DOMElement {
		if ($parent === null) return null;
		$isAggregate = ($text === null);
		if (!$isAggregate) {
			$text = peppol_bis_text($text);
			if ($text === '') return null;
		}
		$namespace = (strncmp($qualifiedName, 'cac:', 4) === 0) ? PEPPOL_BIS_NS_CAC : PEPPOL_BIS_NS_CBC;
		$element = $parent->ownerDocument->createElementNS($namespace, $qualifiedName);
		$parent->appendChild($element);
		foreach ($attributes as $name => $value) {
			$value = peppol_bis_text($value);
			if ($value !== '') $element->setAttribute($name, $value);
		}
		if (!$isAggregate) {
			$element->appendChild($parent->ownerDocument->createTextNode($text));
		}
		return $element;
	}
}

if (!function_exists('peppol_bis_amount')) {
	/**
	 * Appends an amount element with 2 decimals and currencyID; a null or '' amount appends nothing.
	 *
	 * @param DOMElement|null       $parent        The parent element.
	 * @param string                $qualifiedName e.g. 'cbc:TaxAmount'.
	 * @param int|float|string|null $amount        The amount.
	 * @param string                $currency      ISO 4217 code for currencyID.
	 * @return DOMElement|null The new element, or null when it was left out.
	 */
	function peppol_bis_amount(?DOMElement $parent, string $qualifiedName, $amount, string $currency): ?DOMElement {
		if ($amount === null || $amount === '') return null;
		return peppol_bis_add($parent, $qualifiedName, peppol_bis_decimal($amount, 2, 2), array('currencyID' => $currency));
	}
}

if (!function_exists('peppol_bis_price')) {
	/**
	 * Appends a unit price element (2 to 8 decimals) with currencyID; a null or '' price appends nothing.
	 * Saldi's prices have 3 decimals; the builder gives a returned item's net price more when PEPPOL-EN16931-R120 needs them
	 * (EN 16931 sets no decimal limit for BT-146/BT-147/BT-148).
	 *
	 * @param DOMElement|null       $parent        The parent element.
	 * @param string                $qualifiedName e.g. 'cbc:PriceAmount'.
	 * @param int|float|string|null $price         The price.
	 * @param string                $currency      ISO 4217 code for currencyID.
	 * @return DOMElement|null The new element, or null when it was left out.
	 */
	function peppol_bis_price(?DOMElement $parent, string $qualifiedName, $price, string $currency): ?DOMElement {
		if ($price === null || $price === '') return null;
		return peppol_bis_add($parent, $qualifiedName, peppol_bis_decimal($price, 2, 8), array('currencyID' => $currency));
	}
}

if (!function_exists('peppol_bis_party')) {
	/**
	 * Writes cac:Party for the seller or the buyer into AccountingSupplierParty / AccountingCustomerParty.
	 *
	 * @param DOMElement $partyRole The AccountingSupplierParty or AccountingCustomerParty element.
	 * @param array      $party     model['seller'] or model['buyer'].
	 * @return void
	 */
	function peppol_bis_party(DOMElement $partyRole, array $party): void {
		$element = peppol_bis_add($partyRole, 'cac:Party');
		peppol_bis_add($element, 'cbc:EndpointID', $party['endpoint']['id'], array('schemeID' => $party['endpoint']['scheme']));
		foreach ($party['identifiers'] ?? array() as $identifier) {
			peppol_bis_add(peppol_bis_add($element, 'cac:PartyIdentification'), 'cbc:ID', $identifier['id'], array('schemeID' => $identifier['scheme'] ?? null));
		}
		$name = peppol_bis_text($party['name'] ?? null);
		if ($name !== peppol_bis_text($party['registrationName'])) {
			peppol_bis_add(peppol_bis_add($element, 'cac:PartyName'), 'cbc:Name', $name);
		}
		peppol_bis_address(peppol_bis_add($element, 'cac:PostalAddress'), $party['address']);
		if (peppol_bis_text($party['vatId'] ?? null) !== '') {
			$taxScheme = peppol_bis_add($element, 'cac:PartyTaxScheme');
			peppol_bis_add($taxScheme, 'cbc:CompanyID', $party['vatId']);
			peppol_bis_add(peppol_bis_add($taxScheme, 'cac:TaxScheme'), 'cbc:ID', 'VAT');
		}
		$legalEntity = peppol_bis_add($element, 'cac:PartyLegalEntity');
		peppol_bis_add($legalEntity, 'cbc:RegistrationName', $party['registrationName']);
		if (is_array($party['legalId'] ?? null)) {
			peppol_bis_add($legalEntity, 'cbc:CompanyID', $party['legalId']['id'], array('schemeID' => $party['legalId']['scheme'] ?? null));
		}
		$contact = $party['contact'] ?? null;
		if (is_array($contact)) {
			$contactElement = peppol_bis_add($element, 'cac:Contact');
			peppol_bis_add($contactElement, 'cbc:Name', $contact['name'] ?? null);
			peppol_bis_add($contactElement, 'cbc:Telephone', $contact['phone'] ?? null);
			peppol_bis_add($contactElement, 'cbc:ElectronicMail', $contact['email'] ?? null);
		}
	}
}

if (!function_exists('peppol_bis_address')) {
	/**
	 * Writes the children of a PostalAddress or Delivery Address element.
	 *
	 * @param DOMElement|null $element The address element.
	 * @param array           $address street, additionalStreet, city, postalCode, countrySubentity, country.
	 * @return void
	 */
	function peppol_bis_address(?DOMElement $element, array $address): void {
		peppol_bis_add($element, 'cbc:StreetName', $address['street'] ?? null);
		peppol_bis_add($element, 'cbc:AdditionalStreetName', $address['additionalStreet'] ?? null);
		peppol_bis_add($element, 'cbc:CityName', $address['city'] ?? null);
		peppol_bis_add($element, 'cbc:PostalZone', $address['postalCode'] ?? null);
		peppol_bis_add($element, 'cbc:CountrySubentity', $address['countrySubentity'] ?? null);
		peppol_bis_add(peppol_bis_add($element, 'cac:Country'), 'cbc:IdentificationCode', $address['country'] ?? null);
	}
}

if (!function_exists('peppol_bis_tax_category')) {
	/**
	 * Writes the children of a TaxCategory / ClassifiedTaxCategory element; a null percent (category O) leaves Percent out.
	 *
	 * @param DOMElement|null       $element             The category element.
	 * @param string                $category            UNCL 5305 code.
	 * @param int|float|string|null $percent             The VAT rate.
	 * @param string|null           $exemptionReasonCode VATEX code (VAT breakdown only).
	 * @param string|null           $exemptionReason     Exemption reason text (VAT breakdown only).
	 * @return void
	 */
	function peppol_bis_tax_category(?DOMElement $element, $category, $percent, $exemptionReasonCode = null, $exemptionReason = null): void {
		peppol_bis_add($element, 'cbc:ID', $category);
		if ($percent !== null && $percent !== '') {
			peppol_bis_add($element, 'cbc:Percent', peppol_bis_decimal($percent, 2, 4));
		}
		peppol_bis_add($element, 'cbc:TaxExemptionReasonCode', $exemptionReasonCode);
		peppol_bis_add($element, 'cbc:TaxExemptionReason', $exemptionReason);
		peppol_bis_add(peppol_bis_add($element, 'cac:TaxScheme'), 'cbc:ID', 'VAT');
	}
}

if (!function_exists('peppol_bis_allowance_charge')) {
	/**
	 * Appends a document or line level cac:AllowanceCharge up to BaseAmount; the caller adds TaxCategory on document level.
	 *
	 * @param DOMElement $parent          The document root or the line element.
	 * @param array      $allowanceCharge isCharge, amount, baseAmount, percent, reasonCode, reason.
	 * @param string     $currency        ISO 4217 code for currencyID.
	 * @return DOMElement The AllowanceCharge element.
	 */
	function peppol_bis_allowance_charge(DOMElement $parent, array $allowanceCharge, string $currency): DOMElement {
		$element = peppol_bis_add($parent, 'cac:AllowanceCharge');
		peppol_bis_add($element, 'cbc:ChargeIndicator', $allowanceCharge['isCharge'] ? 'true' : 'false');
		peppol_bis_add($element, 'cbc:AllowanceChargeReasonCode', $allowanceCharge['reasonCode'] ?? null);
		peppol_bis_add($element, 'cbc:AllowanceChargeReason', $allowanceCharge['reason'] ?? null);
		$percent = $allowanceCharge['percent'] ?? null;
		if ($percent !== null && $percent !== '') {
			peppol_bis_add($element, 'cbc:MultiplierFactorNumeric', peppol_bis_decimal($percent, 2, 4));
		}
		peppol_bis_amount($element, 'cbc:Amount', $allowanceCharge['amount'], $currency);
		peppol_bis_amount($element, 'cbc:BaseAmount', $allowanceCharge['baseAmount'] ?? null, $currency);
		return $element;
	}
}

if (!function_exists('peppol_bis_line')) {
	/**
	 * Appends one cac:InvoiceLine or cac:CreditNoteLine.
	 *
	 * @param DOMElement $root     The document root.
	 * @param array      $line     One entry of model['lines'].
	 * @param string     $currency ISO 4217 code for currencyID.
	 * @param bool       $isCredit True for a CreditNote.
	 * @return void
	 */
	function peppol_bis_line(DOMElement $root, array $line, string $currency, bool $isCredit): void {
		$element = peppol_bis_add($root, $isCredit ? 'cac:CreditNoteLine' : 'cac:InvoiceLine');
		peppol_bis_add($element, 'cbc:ID', $line['id']);
		peppol_bis_add($element, 'cbc:Note', $line['note'] ?? null);
		peppol_bis_add($element, $isCredit ? 'cbc:CreditedQuantity' : 'cbc:InvoicedQuantity', peppol_bis_decimal($line['quantity'], 0, 4),
			array('unitCode' => $line['unitCode']));
		peppol_bis_amount($element, 'cbc:LineExtensionAmount', $line['lineExtension'], $currency);
		peppol_bis_add($element, 'cbc:AccountingCost', $line['accountingCost'] ?? null);
		peppol_bis_add(peppol_bis_add($element, 'cac:OrderLineReference'), 'cbc:LineID', $line['orderLineReference'] ?? null);
		// A CreditNoteLine has TaxTotal before AllowanceCharge in UBL 2.1; BIS has no line TaxTotal, so the order is the same.
		foreach ($line['allowanceCharges'] ?? array() as $allowanceCharge) {
			peppol_bis_allowance_charge($element, $allowanceCharge, $currency);
		}

		$item = peppol_bis_add($element, 'cac:Item');
		peppol_bis_add($item, 'cbc:Description', $line['itemDescription'] ?? null);
		peppol_bis_add($item, 'cbc:Name', $line['itemName']);
		peppol_bis_add(peppol_bis_add($item, 'cac:SellersItemIdentification'), 'cbc:ID', $line['sellersItemId'] ?? null);
		$standardItemId = $line['standardItemId'] ?? null;
		if (is_array($standardItemId)) {
			peppol_bis_add(peppol_bis_add($item, 'cac:StandardItemIdentification'), 'cbc:ID', $standardItemId['id'], array('schemeID' => $standardItemId['scheme']));
		}
		peppol_bis_tax_category(peppol_bis_add($item, 'cac:ClassifiedTaxCategory'), $line['vatCategory'], $line['vatPercent'] ?? null);

		$price = peppol_bis_add($element, 'cac:Price');
		peppol_bis_price($price, 'cbc:PriceAmount', $line['netPrice'], $currency);
		$baseQuantity = $line['baseQuantity'] ?? null;
		if ($baseQuantity !== null && $baseQuantity !== '') {
			// PEPPOL-EN16931-R130: the base quantity has the line's unit.
			peppol_bis_add($price, 'cbc:BaseQuantity', peppol_bis_decimal($baseQuantity, 0, 4), array('unitCode' => $line['unitCode']));
		}
		// BT-148 gross price with BT-147 price discount; net = gross - discount (PEPPOL-EN16931-R046), a charge is not allowed here (R044).
		$grossPrice = $line['grossPrice'] ?? null;
		if ($grossPrice !== null && $grossPrice !== '') {
			$priceDiscount = $line['priceDiscount'] ?? null;
			$priceAllowance = peppol_bis_add($price, 'cac:AllowanceCharge');
			peppol_bis_add($priceAllowance, 'cbc:ChargeIndicator', 'false');
			peppol_bis_price($priceAllowance, 'cbc:Amount', ($priceDiscount === null || $priceDiscount === '') ? 0 : $priceDiscount, $currency);
			peppol_bis_price($priceAllowance, 'cbc:BaseAmount', $grossPrice, $currency);
		}
	}
}

if (!function_exists('peppol_bis_prune_empty')) {
	/**
	 * Removes elements that ended up without children or text, deepest first (PEPPOL-EN16931-R008).
	 *
	 * @param DOMElement $element The element whose descendants are checked.
	 * @return void
	 */
	function peppol_bis_prune_empty(DOMElement $element): void {
		foreach (iterator_to_array($element->childNodes) as $child) {
			if (!$child instanceof DOMElement) continue;
			peppol_bis_prune_empty($child);
			if (!$child->hasChildNodes() || ($child->childElementCount === 0 && trim($child->textContent) === '')) {
				$element->removeChild($child);
			}
		}
	}
}

if (!function_exists('peppol_bis_missing_text')) {
	/**
	 * True when a required text value is absent, null, blank or not a scalar.
	 *
	 * @param mixed $value The value.
	 * @return bool
	 */
	function peppol_bis_missing_text($value): bool {
		return !is_scalar($value) || is_bool($value) || peppol_bis_text($value) === '';
	}
}

if (!function_exists('peppol_bis_missing_number')) {
	/**
	 * True when a required number is absent, null or not numeric.
	 *
	 * @param mixed $value The value.
	 * @return bool
	 */
	function peppol_bis_missing_number($value): bool {
		return !is_int($value) && !is_float($value) && !(is_string($value) && is_numeric(trim($value)));
	}
}

if (!function_exists('peppol_bis_bad_number')) {
	/**
	 * True when an optional number is given (not null or '') but is not numeric.
	 *
	 * @param mixed $value The value.
	 * @return bool
	 */
	function peppol_bis_bad_number($value): bool {
		return $value !== null && $value !== '' && peppol_bis_missing_number($value);
	}
}

if (!function_exists('peppol_bis_list')) {
	/**
	 * An optional list from the model: absent or null gives an empty list, anything but an array adds an error.
	 *
	 * @param array        $parent The array holding the list.
	 * @param string       $key    The list's key.
	 * @param list<string> $errors The error list peppol_bis_validate_model() builds; appended to.
	 * @param string       $path   The parent's path for the message, e.g. "lines[0].".
	 * @return array The list's entries; entries that are not arrays are reported and skipped.
	 */
	function peppol_bis_list(array $parent, string $key, array &$errors, string $path = ''): array {
		$list = $parent[$key] ?? null;
		if ($list === null) return array();
		if (!is_array($list)) {
			$errors[] = "$path$key must be a list";
			return array();
		}
		$entries = array();
		foreach ($list as $i => $entry) {
			if (is_array($entry)) {
				$entries[$i] = $entry;
			} else {
				$errors[] = "$path{$key}[$i] must be an array";
			}
		}
		return $entries;
	}
}
