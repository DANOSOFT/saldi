<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoice/peppolModel.php --- ver 5.1.0 --- 2026-11-09 ---
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
// 20261109 CL/MMR SD-815 Created (draft). The document model shared by the Peppol BIS 3.0 builder and writer.
//                 Saldi builds the e-invoice XML itself and sends it through Semantic's API instead of sending JSON to EasyUBL.
// 20261109 CL/MMR SD-815 Documented the builder's optional keys issues and reconciliation, reg.nr. in payeeAccount.bic for code 42, and dueDate on a credit note.
// 20261109 CL/MMR SD-815 Unit prices may carry up to 8 decimals; reconciliation has the optional maxLineSlack.

/*
 * SD-815 draft: how the pieces fit together.
 *
 *   peppol_invoice_rows($orderId)          includes/einvoice/peppolInvoiceData.php      reads ordrer, ordrelinjer, own Stamdata, the debitor card
 *        |
 *   peppol_document_model($rows, $opts)    includes/einvoice/peppolDocumentModel.php    pure: Saldi rows -> the model below (VAT, totals, identifiers, payment)
 *        |
 *   peppol_precheck($model, $sprog_id)     includes/einvoice/peppolPrecheck.php         pure: Danish messages for what the customer must fix before sending
 *        |
 *   peppol_bis_xml($model)                 includes/einvoice/peppolBisWriter.php        pure: the model -> UBL 2.1 Invoice / CreditNote, Peppol BIS Billing 3.0
 *        |
 *   semantic_send_document($orderId, $type) includes/einvoice/semanticSend.php          send log, duplicate guard, SemanticClient, ordrer.digital_status
 *
 * The model is a plain array so the writer can be tested without a database and the builder without XML.
 * Amounts are PHP floats already rounded to 2 decimals, except unit prices (netPrice, grossPrice, priceDiscount), which
 * may carry up to 8 decimals (more than 4 only for a returned item's net price that needs them for PEPPOL-EN16931-R120). Dates are 'YYYY-MM-DD'. Text is UTF-8 (the builder converts from the database encoding).
 * A null or '' optional value means "leave the element out": Peppol rejects empty elements (PEPPOL-EN16931-R008).
 */

if (!function_exists('peppol_model_example')) {
	/**
	 * A complete model for a small Danish B2B invoice with synthetic data, used as the reference shape and in tests.
	 *
	 * @param string $documentType 'invoice' or 'creditnote'.
	 * @return array{
	 *   documentType: string,               'invoice' | 'creditnote'.
	 *   id: string,                         BT-1 invoice/credit note number (ordrer.fakturanr).
	 *   issueDate: string,                  BT-2.
	 *   dueDate: string|null,               BT-9; invoice only (a CreditNote has no cbc:DueDate in BIS 3). The builder leaves it null on a credit
	 *                                       note; a date given there anyway goes to the first PaymentMeans as cbc:PaymentDueDate.
	 *   typeCode: string,                   BT-3: '380' invoice, '381' credit note.
	 *   note: string|null,                  BT-22.
	 *   currency: string,                   BT-5 ISO 4217.
	 *   taxCurrency: string|null,           BT-6, only when the VAT accounting currency differs from BT-5.
	 *   taxCurrencyTaxAmount: float|null,   BT-111, VAT total in taxCurrency; required when taxCurrency is set.
	 *   accountingCost: string|null,        BT-19.
	 *   buyerReference: string|null,        BT-10 (ordrer.kundeordnr). BT-10 or BT-13 is required (PEPPOL-EN16931-R003).
	 *   orderReference: string|null,        BT-13 the buyer's purchase order reference.
	 *   salesOrderId: string|null,          BT-14 (ordrer.ordrenr); written only together with an order reference.
	 *   billingReferences: list<array{
	 *     id: string,                       BT-25 the invoice a credit note corrects.
	 *     issueDate: string|null,           BT-26.
	 *   }>,
	 *   seller: array{
	 *     endpoint: array{scheme: string, id: string},     BT-34, e.g. 0184 + CVR without "DK", or 0088 + GLN.
	 *     identifiers: list<array{scheme: string|null, id: string}>,  BT-29.
	 *     name: string|null,                BT-28 trading name; left out when equal to registrationName.
	 *     registrationName: string,         BT-27.
	 *     legalId: array{scheme: string|null, id: string}|null,  BT-30; DK: scheme 0184, CVR digits (DK-R-002, DK-R-014).
	 *     vatId: string|null,               BT-31 with country prefix, e.g. 'DK12345678'.
	 *     address: array{street: string|null, additionalStreet: string|null, city: string|null, postalCode: string|null, countrySubentity: string|null, country: string},
	 *     contact: array{name: string|null, phone: string|null, email: string|null}|null,
	 *   },
	 *   buyer: array{
	 *     endpoint: array{scheme: string, id: string},     BT-49.
	 *     identifiers: list<array{scheme: string|null, id: string}>,  BT-46.
	 *     name: string|null,
	 *     registrationName: string,         BT-44.
	 *     legalId: array{scheme: string|null, id: string}|null,  BT-47; Danish customer: scheme 0184 only (DK-R-017).
	 *     vatId: string|null,               BT-48.
	 *     address: array{street: string|null, additionalStreet: string|null, city: string|null, postalCode: string|null, countrySubentity: string|null, country: string},
	 *     contact: array{name: string|null, phone: string|null, email: string|null}|null,
	 *   },
	 *   delivery: array{
	 *     date: string|null,                BT-72.
	 *     name: string|null,                BT-70.
	 *     address: array{street: string|null, additionalStreet: string|null, city: string|null, postalCode: string|null, countrySubentity: string|null, country: string}|null,
	 *   }|null,
	 *   paymentMeans: list<array{
	 *     code: string,                     BT-81 UNCL 4461; DK suppliers: 1, 10, 31, 42, 48, 49, 50, 58, 59, 93, 97 (DK-R-005).
	 *     paymentId: string|null,           BT-83; for code 93 (FIK) the '71'/'73'/'75' card type plus payment id, see the builder.
	 *     payeeAccount: array{id: string, name: string|null, bic: string|null}|null,  BT-84/85/86. 'bic' is written to
	 *                                       FinancialInstitutionBranch/ID: the BIC for 31/58, the Danish reg.nr. for 42 (DK-R-006).
	 *   }>,
	 *   paymentTerms: string|null,          BT-20.
	 *   allowanceCharges: list<array{
	 *     isCharge: bool,
	 *     amount: float,                    BT-92/99.
	 *     baseAmount: float|null,
	 *     percent: float|null,
	 *     reasonCode: string|null,          UNCL 5189 (allowance) / UNCL 7161 (charge).
	 *     reason: string|null,
	 *     vatCategory: string,
	 *     vatPercent: float|null,
	 *   }>,
	 *   taxSubtotals: list<array{
	 *     taxableAmount: float,             BT-116.
	 *     taxAmount: float,                 BT-117.
	 *     category: string,                 BT-118 UNCL 5305: S, Z, E, AE, K, G, O, L, M.
	 *     percent: float|null,              BT-119; null for category O.
	 *     exemptionReasonCode: string|null, BT-121 (VATEX code).
	 *     exemptionReason: string|null,     BT-120; E, AE, K, G and O need a code or a reason.
	 *   }>,
	 *   taxTotal: float,                    BT-110, the sum of the subtotals' taxAmount.
	 *   totals: array{
	 *     lineExtension: float,             BT-106 = sum of line lineExtension.
	 *     taxExclusive: float,              BT-109.
	 *     taxInclusive: float,              BT-112.
	 *     allowanceTotal: float|null,       BT-107.
	 *     chargeTotal: float|null,          BT-108.
	 *     prepaid: float|null,              BT-113.
	 *     roundingAmount: float|null,       BT-114.
	 *     payable: float,                   BT-115.
	 *   },
	 *   lines: list<array{
	 *     id: string,                       BT-126.
	 *     quantity: float,                  BT-129; negative for a discount line or a returned item (SST-746).
	 *     unitCode: string,                 BT-130 UN/ECE Rec 20, e.g. 'H87' piece, 'C62' one, 'HUR' hour.
	 *     lineExtension: float,             BT-131 = quantity x netPrice / baseQuantity - line allowances + line charges.
	 *     note: string|null,                BT-127.
	 *     accountingCost: string|null,      BT-133.
	 *     orderLineReference: string|null,  BT-132.
	 *     allowanceCharges: list<array{isCharge: bool, amount: float, baseAmount: float|null, percent: float|null, reasonCode: string|null, reason: string|null}>,
	 *     netPrice: float,                  BT-146; never negative (BR-27).
	 *     baseQuantity: float|null,         BT-149.
	 *     grossPrice: float|null,           BT-148.
	 *     priceDiscount: float|null,        BT-147.
	 *     itemName: string,                 BT-153.
	 *     itemDescription: string|null,     BT-154.
	 *     sellersItemId: string|null,       BT-155.
	 *     standardItemId: array{scheme: string, id: string}|null,  BT-157.
	 *     vatCategory: string,              BT-151.
	 *     vatPercent: float|null,           BT-152; null for category O.
	 *   }>,
	 *   attachments: list<array{
	 *     id: string,                       BT-122.
	 *     description: string|null,         BT-123.
	 *     mimeCode: string,                 e.g. 'application/pdf'.
	 *     filename: string,
	 *     base64: string,                   BT-125, the file content base64-encoded.
	 *   }>,
	 *   issues?: list<string>,              Optional, set by peppol_document_model() only: English notes for developers; the writer ignores it.
	 *   reconciliation?: array{bookedNet: float, bookedVat: float, computedNet: float, computedVat: float, vatAdjustment: float, roundingAmount: float, ok: bool, maxLineSlack?: float},
	 *                                       Optional, set by peppol_document_model() only: the totals against the booked ordrer.sum/moms;
	 *                                       peppol_precheck() stops the send when ok is false or the optional maxLineSlack (the largest
	 *                                       line amount difference to quantity x net price) is above 0.02; the writer ignores it.
	 * }
	 */
	function peppol_model_example($documentType = 'invoice') {
		$isCredit = ($documentType === 'creditnote');
		$address = array('street' => 'Eksempelvej 1', 'additionalStreet' => null, 'city' => 'Eksempelby', 'postalCode' => '1234', 'countrySubentity' => null, 'country' => 'DK');
		return array(
			'documentType' => $isCredit ? 'creditnote' : 'invoice',
			'id' => $isCredit ? '1002' : '1001',
			'issueDate' => '2026-11-09',
			'dueDate' => $isCredit ? null : '2026-11-23',
			'typeCode' => $isCredit ? '381' : '380',
			'note' => null,
			'currency' => 'DKK',
			'taxCurrency' => null,
			'taxCurrencyTaxAmount' => null,
			'accountingCost' => null,
			'buyerReference' => 'PO-4711',
			'orderReference' => null,
			'salesOrderId' => null,
			'billingReferences' => $isCredit ? array(array('id' => '1001', 'issueDate' => '2026-11-09')) : array(),
			'seller' => array(
				'endpoint' => array('scheme' => '0184', 'id' => '11111111'),
				'identifiers' => array(),
				'name' => null,
				'registrationName' => 'Eksempel Sælger ApS',
				'legalId' => array('scheme' => '0184', 'id' => '11111111'),
				'vatId' => 'DK11111111',
				'address' => $address,
				'contact' => array('name' => null, 'phone' => null, 'email' => 'faktura@example.invalid'),
			),
			'buyer' => array(
				'endpoint' => array('scheme' => '0184', 'id' => '22222222'),
				'identifiers' => array(),
				'name' => null,
				'registrationName' => 'Eksempel Køber A/S',
				'legalId' => array('scheme' => '0184', 'id' => '22222222'),
				'vatId' => 'DK22222222',
				'address' => array('street' => 'Kundevej 2', 'additionalStreet' => null, 'city' => 'Kundeby', 'postalCode' => '5678', 'countrySubentity' => null, 'country' => 'DK'),
				'contact' => null,
			),
			'delivery' => array('date' => '2026-11-09', 'name' => null, 'address' => null),
			'paymentMeans' => $isCredit ? array() : array(
				array('code' => '31', 'paymentId' => '1001', 'payeeAccount' => array('id' => 'DK5000400440116243', 'name' => null, 'bic' => 'DABADKKK')),
			),
			'paymentTerms' => $isCredit ? null : 'Netto 14 dage',
			'allowanceCharges' => array(),
			'taxSubtotals' => array(
				array('taxableAmount' => 300.00, 'taxAmount' => 75.00, 'category' => 'S', 'percent' => 25.0, 'exemptionReasonCode' => null, 'exemptionReason' => null),
			),
			'taxTotal' => 75.00,
			'totals' => array(
				'lineExtension' => 300.00,
				'taxExclusive' => 300.00,
				'taxInclusive' => 375.00,
				'allowanceTotal' => null,
				'chargeTotal' => null,
				'prepaid' => null,
				'roundingAmount' => null,
				'payable' => 375.00,
			),
			'lines' => array(
				array(
					'id' => '1',
					'quantity' => 2.0,
					'unitCode' => 'H87',
					'lineExtension' => 300.00,
					'note' => null,
					'accountingCost' => null,
					'orderLineReference' => null,
					'allowanceCharges' => array(),
					'netPrice' => 150.00,
					'baseQuantity' => null,
					'grossPrice' => null,
					'priceDiscount' => null,
					'itemName' => 'Eksempelvare',
					'itemDescription' => null,
					'sellersItemId' => 'VARE-1',
					'standardItemId' => null,
					'vatCategory' => 'S',
					'vatPercent' => 25.0,
				),
			),
			'attachments' => array(),
		);
	}
}
