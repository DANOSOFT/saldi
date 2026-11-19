<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/einvoice/peppolDocumentModel.test.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261109 CL/MMR SD-815 Created (draft). Tests for peppol_document_model() with synthetic order rows: VAT categories, discounts, totals against the booking, identifiers, payment.
// 20261109 CL/MMR SD-815 Review fixes: seller without VAT registration from the rows, foreign numbers without prefix, 0184 with "DK", R120 on large returns, rounding limits, open item, identifier formats.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/std_func.php';
require_once __DIR__ . '/../../../includes/forfaldsdag.php';
require_once __DIR__ . '/../../../includes/einvoice/peppolModel.php';
require_once __DIR__ . '/../../../includes/einvoice/peppolDocumentModel.php';
require_once __DIR__ . '/../../../includes/einvoice/peppolInvoiceData.php';

/**
 * SD-815: peppol_document_model() turns the rows of an order into the Peppol document model. All data is synthetic:
 * CVR 11111111 and 22222222 fail the modulus 11 check, so they belong to nobody; GLNs are built on a 000000 prefix.
 */
final class peppolDocumentModel extends TestCase
{
    /**
     * The rows of a plain Danish invoice: 2 x 150.00 at 25 % to a customer with CVR, Netto 14 dage.
     *
     * @param array<string, mixed> $order Fields that replace the order's.
     * @param list<array<string, mixed>>|null $lines The lines (each merged onto line()), null for the default line.
     * @return array<string, mixed>
     */
    private static function rows(array $order = array(), ?array $lines = null): array
    {
        return array(
            'order' => array_merge(array(
                'id' => '501', 'art' => 'DO', 'status' => '3', 'konto_id' => '77', 'kontonr' => '1077',
                'firmanavn' => 'Testkunde ApS', 'addr1' => 'Kundevej 2', 'addr2' => '', 'postnr' => '5678', 'bynavn' => 'Kundeby',
                'land' => 'Danmark', 'kontakt' => 'Kim Kunde', 'email' => 'faktura@kunde.example', 'phone' => '',
                'cvrnr' => '22222222', 'ean' => '', 'kundeordnr' => 'PO-4711', 'ordrenr' => '9001', 'fakturanr' => '1001',
                'fakturadate' => '2026-11-09', 'levdate' => '2026-11-08', 'betalingsbet' => 'Netto', 'betalingsdage' => '14',
                'valuta' => 'DKK', 'valutakurs' => '100.000', 'momssats' => '25.000', 'sum' => '300.000', 'moms' => '75.000',
                'kred_ord_id' => '', 'notes' => '', 'ref' => 'Sælger Søren', 'sprog' => 'Dansk', 'omvbet' => '',
                'lev_navn' => '', 'lev_addr1' => '', 'lev_addr2' => '', 'lev_postnr' => '', 'lev_bynavn' => '', 'lev_land' => '',
            ), $order),
            'lines' => array_map(array(self::class, 'line'), $lines ?? array(array())),
            'seller' => array(
                'firmanavn' => 'Eksempel Sælger ApS', 'addr1' => 'Eksempelvej 1', 'addr2' => '', 'postnr' => '1234', 'bynavn' => 'Eksempelby',
                'land' => 'Danmark', 'cvrnr' => '11111111', 'tlf' => '', 'email' => 'faktura@saelger.example',
                'bank_reg' => '1234', 'bank_konto' => '0012345678', 'bank_fi' => '', 'iban' => 'DK50 0040 0440 1162 43', 'swift' => 'DABADKKK',
            ),
            'customer' => array('id' => '77', 'cvrnr' => '22222222', 'ean' => '', 'gruppe' => '1', 'land' => 'Danmark'),
            'debtorGroup' => array('kodenr' => '1', 'box1' => 'S1', 'box10' => ''),
            'itemGroups' => array(
                10 => array('group' => 2, 'type' => 'varer', 'reverseCharge' => '', 'vatExempt' => ''),
                20 => array('group' => 4, 'type' => 'varer', 'reverseCharge' => '', 'vatExempt' => 'on'),
                30 => array('group' => 1, 'type' => 'ydelser', 'reverseCharge' => '', 'vatExempt' => ''),
            ),
            'creditedInvoice' => null,
            'settings' => array('baseCurrency' => 'DKK', 'baseCountry' => 'dk', 'sendOrderNote' => 'off', 'cashAccount' => '', 'cardAccount' => ''),
            'fikLayout' => array('accountLength' => 0, 'invoiceLength' => 14),
        );
    }

    /**
     * An ordrelinjer row: 2 x 150.00, item VARE-1 in a goods group, the order's 25 %.
     *
     * @param array<string, mixed> $fields Fields that replace the default line's.
     * @return array<string, mixed>
     */
    private static function line(array $fields): array
    {
        return array_merge(array(
            'id' => '1', 'posnr' => '1', 'varenr' => 'VARE-1', 'vare_id' => '10', 'beskrivelse' => 'Eksempelvare', 'enhed' => 'stk',
            'antal' => '2.000', 'pris' => '150.000', 'rabat' => '0.000', 'rabatart' => '', 'procent' => '100.000', 'momsfri' => '',
            'momssats' => '25.000', 'omvbet' => '', 'bogf_konto' => '1000', 'samlevare' => '', 'saet' => '', 'serienr' => '', 'm_rabat' => '0',
        ), $fields);
    }

    /**
     * A synthetic GLN with a valid check digit.
     */
    private static function gln(string $base12): string
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$base12[$i] * (($i % 2) ? 3 : 1);
        }
        return $base12 . (string)((10 - $sum % 10) % 10);
    }

    /**
     * The keys of an array, recursively for associative arrays, the first element standing for a list.
     *
     * @return array<string, mixed>
     */
    private static function shape(array $value): array
    {
        $shape = array();
        foreach ($value as $key => $item) {
            if (is_array($item) && $item && array_is_list($item)) {
                $shape[$key] = is_array($item[0]) ? array(self::shape($item[0])) : 'list';
            } elseif (is_array($item) && $item) {
                $shape[$key] = self::shape($item);
            } else {
                $shape[$key] = 'value';
            }
        }
        ksort($shape);
        return $shape;
    }

    public function testPlainDanishInvoice(): void
    {
        $model = peppol_document_model(self::rows());

        self::assertSame('invoice', $model['documentType']);
        self::assertSame('380', $model['typeCode']);
        self::assertSame('1001', $model['id']);
        self::assertSame('2026-11-09', $model['issueDate']);
        self::assertSame('2026-11-23', $model['dueDate']);
        self::assertSame('DKK', $model['currency']);
        self::assertNull($model['taxCurrency']);
        self::assertSame('PO-4711', $model['buyerReference']);
        self::assertNull($model['orderReference']);
        self::assertNull($model['accountingCost']);

        self::assertSame(array('scheme' => '0184', 'id' => '11111111'), $model['seller']['endpoint']);
        self::assertSame(array('scheme' => '0184', 'id' => '11111111'), $model['seller']['legalId']);
        self::assertSame('DK11111111', $model['seller']['vatId']);
        self::assertSame('Eksempel Sælger ApS', $model['seller']['registrationName']);
        self::assertSame(array('scheme' => '0184', 'id' => '22222222'), $model['buyer']['endpoint']);
        self::assertSame(array('scheme' => '0184', 'id' => '22222222'), $model['buyer']['legalId']);
        self::assertSame('DK22222222', $model['buyer']['vatId']);
        self::assertSame('DK', $model['buyer']['address']['country']);
        self::assertSame('Kundevej 2', $model['buyer']['address']['street']);

        self::assertCount(1, $model['lines']);
        $line = $model['lines'][0];
        self::assertSame('1', $line['id']);
        self::assertSame(2.0, $line['quantity']);
        self::assertSame('EA', $line['unitCode']);
        self::assertSame(150.0, $line['netPrice']);
        self::assertSame(300.0, $line['lineExtension']);
        self::assertSame(array(), $line['allowanceCharges']);
        self::assertSame('Eksempelvare', $line['itemName']);
        self::assertSame('VARE-1', $line['sellersItemId']);
        self::assertSame('S', $line['vatCategory']);
        self::assertSame(25.0, $line['vatPercent']);

        self::assertSame(array(array('taxableAmount' => 300.0, 'taxAmount' => 75.0, 'category' => 'S', 'percent' => 25.0,
            'exemptionReasonCode' => null, 'exemptionReason' => null)), $model['taxSubtotals']);
        self::assertSame(75.0, $model['taxTotal']);
        self::assertSame(array('lineExtension' => 300.0, 'taxExclusive' => 300.0, 'taxInclusive' => 375.0, 'allowanceTotal' => null,
            'chargeTotal' => null, 'prepaid' => null, 'roundingAmount' => null, 'payable' => 375.0), $model['totals']);

        self::assertSame(array(array('code' => '42', 'paymentId' => '1001',
            'payeeAccount' => array('id' => '0012345678', 'name' => null, 'bic' => '1234'))), $model['paymentMeans']);
        self::assertSame('Netto 14 dage', $model['paymentTerms']);
        self::assertSame(array('date' => '2026-11-08', 'name' => null, 'address' => $model['buyer']['address']), $model['delivery']);
        self::assertTrue($model['reconciliation']['ok']);
        self::assertSame(array(), $model['issues']);
    }

    public function testModelHasTheContractShape(): void
    {
        $model = peppol_document_model(self::rows());
        $example = peppol_model_example('invoice');
        unset($model['issues'], $model['reconciliation']);
        // Keys the example leaves empty or null are compared at the top level only
        self::assertSame(array_keys(self::shape($example)), array_keys(self::shape($model)));
        self::assertSame(self::shape($example['seller']), self::shape($model['seller']));
        self::assertSame(self::shape($example['lines'][0]), self::shape($model['lines'][0]));
        self::assertSame(self::shape($example['totals']), self::shape($model['totals']));
        self::assertSame(self::shape($example['taxSubtotals'][0]), self::shape($model['taxSubtotals'][0]));
    }

    public function testPercentDiscountIsALineAllowanceWithPercentAndBase(): void
    {
        $model = peppol_document_model(self::rows(array('sum' => '270', 'moms' => '67.5'), array(array('rabat' => '10'))));
        $line = $model['lines'][0];

        self::assertSame(150.0, $line['netPrice']);
        self::assertSame(270.0, $line['lineExtension']);
        self::assertSame(array(array('isCharge' => false, 'amount' => 30.0, 'baseAmount' => 300.0, 'percent' => 10.0,
            'reasonCode' => '95', 'reason' => 'Rabat 10 %')), $line['allowanceCharges']);
        self::assertSame(337.5, $model['totals']['payable']);
        self::assertTrue($model['reconciliation']['ok']);
    }

    public function testAmountDiscountIsPerUnitLikeTheBooking(): void
    {
        // rabatart 'amount': (150 - 20) x 2 = 260 (ordrefunc.php momsupdat()); EasyUBL read it as 20 %
        $model = peppol_document_model(self::rows(array('sum' => '260', 'moms' => '65'), array(array('rabat' => '20', 'rabatart' => 'amount'))));
        $line = $model['lines'][0];

        self::assertSame(260.0, $line['lineExtension']);
        self::assertSame(array(array('isCharge' => false, 'amount' => 40.0, 'baseAmount' => null, 'percent' => null,
            'reasonCode' => '95', 'reason' => 'Rabat 20 pr. stk')), $line['allowanceCharges']);
        self::assertSame(325.0, $model['totals']['payable']);
        self::assertTrue($model['reconciliation']['ok']);
    }

    public function testLinePercentageInvoicesThatShare(): void
    {
        $model = peppol_document_model(self::rows(array('sum' => '135', 'moms' => '33.75'), array(array('procent' => '50', 'rabat' => '10'))));
        $line = $model['lines'][0];

        // (2 x 150 - 10 %) x 50 % = 135
        self::assertSame(135.0, $line['lineExtension']);
        self::assertSame(150.0, $line['netPrice']);
        self::assertSame(165.0, $line['allowanceCharges'][0]['amount']);
        self::assertNull($line['allowanceCharges'][0]['percent']);
        self::assertSame('Rabat 10 %, Faktureret 50 %', $line['allowanceCharges'][0]['reason']);
    }

    public function testNullPercentageCountsAsTheWholeLine(): void
    {
        // formfunk.php treats an empty procent as 100 %; EasyUBL multiplied by 0
        $model = peppol_document_model(self::rows(array(), array(array('procent' => null))));
        self::assertSame(300.0, $model['lines'][0]['lineExtension']);
        self::assertSame(array(), $model['lines'][0]['allowanceCharges']);
    }

    public function testExemptItemGroupLineIsCategoryE(): void
    {
        $rows = self::rows(array('sum' => '350', 'moms' => '75'), array(
            array(),
            array('posnr' => '2', 'varenr' => 'PORTO', 'vare_id' => '20', 'beskrivelse' => 'Porto', 'antal' => '1', 'pris' => '50', 'momsfri' => 'on', 'momssats' => '0'),
        ));
        $model = peppol_document_model($rows);

        self::assertSame('E', $model['lines'][1]['vatCategory']);
        self::assertSame(0.0, $model['lines'][1]['vatPercent']);
        self::assertSame(array(
            array('taxableAmount' => 300.0, 'taxAmount' => 75.0, 'category' => 'S', 'percent' => 25.0, 'exemptionReasonCode' => null, 'exemptionReason' => null),
            array('taxableAmount' => 50.0, 'taxAmount' => 0.0, 'category' => 'E', 'percent' => 0.0, 'exemptionReasonCode' => null, 'exemptionReason' => 'Momsfritaget'),
        ), $model['taxSubtotals']);
        self::assertSame(425.0, $model['totals']['payable']);
        self::assertTrue($model['reconciliation']['ok']);
    }

    public function testDomesticReverseChargeIsAe(): void
    {
        $model = peppol_document_model(self::rows(array('moms' => '0', 'omvbet' => 'on'), array(array('omvbet' => 'on'))));

        self::assertSame('AE', $model['lines'][0]['vatCategory']);
        self::assertSame('VATEX-EU-AE', $model['taxSubtotals'][0]['exemptionReasonCode']);
        self::assertSame('Omvendt betalingspligt', $model['taxSubtotals'][0]['exemptionReason']);
        self::assertSame(0.0, $model['taxTotal']);
        self::assertSame(300.0, $model['totals']['payable']);
    }

    public function testEuCompanyGetsIntraCommunitySupplyAndReverseChargeForServices(): void
    {
        // As in real data: 0 % on the order and momsfri on every line, though the item groups are not momsfri
        $rows = self::rows(array('land' => 'Sverige', 'cvrnr' => 'SE000000001801', 'valuta' => 'DKK', 'momssats' => '0', 'sum' => '400', 'moms' => '0'), array(
            array('momsfri' => 'on', 'momssats' => '0'),
            array('posnr' => '2', 'varenr' => 'SERVICE', 'vare_id' => '30', 'beskrivelse' => 'Kalibrering', 'antal' => '1', 'pris' => '100', 'momsfri' => 'on', 'momssats' => '0', 'enhed' => 'timer'),
        ));
        $model = peppol_document_model($rows);

        self::assertSame('K', $model['lines'][0]['vatCategory']);
        self::assertSame('AE', $model['lines'][1]['vatCategory']);
        self::assertSame('HUR', $model['lines'][1]['unitCode']);
        self::assertSame(array('scheme' => '0007', 'id' => '0000000018'), $model['buyer']['endpoint']);
        self::assertSame(array('scheme' => '0007', 'id' => '0000000018'), $model['buyer']['legalId']);
        self::assertSame('SE000000001801', $model['buyer']['vatId']);
        self::assertSame('SE', $model['buyer']['address']['country']);
        self::assertSame('SE', $model['delivery']['address']['country']);
        self::assertSame(array('AE', 'K'), array_column($model['taxSubtotals'], 'category'));
        self::assertSame('VATEX-EU-IC', $model['taxSubtotals'][1]['exemptionReasonCode']);
        self::assertSame(400.0, $model['totals']['payable']);
    }

    public function testIntraCommunitySupplyWithoutLevdateIsDeliveredOnTheInvoiceDate(): void
    {
        $rows = self::rows(array('land' => 'Sverige', 'cvrnr' => 'SE000000001801', 'momssats' => '0', 'moms' => '0', 'levdate' => null),
            array(array('momsfri' => 'on', 'momssats' => '0')));
        $model = peppol_document_model($rows);
        self::assertSame('K', $model['lines'][0]['vatCategory']);
        self::assertSame('2026-11-09', $model['delivery']['date']);
        self::assertContains('no levdate: the invoice date is sent as the delivery date (BR-IC-11)', $model['issues']);

        // Other categories leave a missing delivery date out
        self::assertNull(peppol_document_model(self::rows(array('levdate' => '')))['delivery']['date']);
    }

    public function testEuZoneOfTheDebtorGroupDecidesBeforeTheCountry(): void
    {
        $rows = self::rows(array('momssats' => '0', 'moms' => '0'), array(array('momssats' => '0')));
        $rows['debtorGroup']['box10'] = 'B2B-UDL';
        self::assertSame('G', peppol_document_model($rows)['lines'][0]['vatCategory']);
        $rows['debtorGroup']['box10'] = 'B2B-EU';
        self::assertSame('K', peppol_document_model($rows)['lines'][0]['vatCategory']);
    }

    public function testZeroRatedLineOfACustomerWhoPaysVatIsExempt(): void
    {
        // A private Swedish customer charged 25 %, with a free freight line flagged momsfri: not an EU B2B supply
        $rows = self::rows(array('land' => 'Sverige', 'cvrnr' => ''), array(
            array(),
            array('posnr' => '2', 'varenr' => 'FRAGT', 'beskrivelse' => 'Fragt', 'antal' => '1', 'pris' => '0', 'momsfri' => 'on', 'momssats' => '25'),
        ));
        $model = peppol_document_model($rows);
        self::assertSame('E', $model['lines'][1]['vatCategory']);
    }

    public function testExportOutsideTheEuIsG(): void
    {
        $rows = self::rows(array('land' => 'Norge', 'cvrnr' => 'NO000000019MVA', 'valuta' => 'NOK', 'valutakurs' => '64.000', 'momssats' => '0', 'moms' => '0'),
            array(array('momsfri' => 'on', 'momssats' => '0')));
        $model = peppol_document_model($rows);

        self::assertSame('G', $model['lines'][0]['vatCategory']);
        self::assertSame('VATEX-EU-G', $model['taxSubtotals'][0]['exemptionReasonCode']);
        self::assertSame(array('scheme' => '0192', 'id' => '000000019'), $model['buyer']['endpoint']);
        self::assertSame(array('scheme' => '0192', 'id' => '000000019'), $model['buyer']['legalId']);
        self::assertSame('NO000000019MVA', $model['buyer']['vatId']);
        self::assertSame('NOK', $model['currency']);
        self::assertSame('DKK', $model['taxCurrency']);
        self::assertSame(0.0, $model['taxCurrencyTaxAmount']);
        // SST-873: a foreign invoice is paid by IBAN (58), not the Danish reg.nr./account
        self::assertSame('58', $model['paymentMeans'][0]['code']);
        self::assertSame(array('id' => 'DK5000400440116243', 'name' => null, 'bic' => 'DABADKKK'), $model['paymentMeans'][0]['payeeAccount']);
    }

    public function testGreenlandIsSentAsDkButZeroRatedAsExport(): void
    {
        $rows = self::rows(array('land' => 'Grønland', 'momssats' => '0', 'moms' => '0'), array(array('momsfri' => 'on', 'momssats' => '0')));
        $model = peppol_document_model($rows);
        self::assertSame('DK', $model['buyer']['address']['country']);
        self::assertSame('G', $model['lines'][0]['vatCategory']);

        $model = peppol_document_model($rows, array('greenlandFaroeAsDk' => false));
        self::assertSame('GL', $model['buyer']['address']['country']);
    }

    public function testGlnReceiverWithCvr(): void
    {
        $gln = self::gln('000000001234');
        $model = peppol_document_model(self::rows(array('ean' => $gln)));
        self::assertSame(array('scheme' => '0088', 'id' => $gln), $model['buyer']['endpoint']);
        self::assertSame(array('scheme' => '0184', 'id' => '22222222'), $model['buyer']['legalId']);
    }

    public function testGlnReceiverWithoutCvrHasNoLegalId(): void
    {
        $rows = self::rows(array('ean' => self::gln('000000001234'), 'cvrnr' => ''));
        $rows['customer']['cvrnr'] = '';
        $model = peppol_document_model($rows);
        self::assertSame('0088', $model['buyer']['endpoint']['scheme']);
        self::assertNull($model['buyer']['legalId']);
        self::assertNull($model['buyer']['vatId']);
    }

    public function testCvrFieldHoldingTheGlnFallsBackToTheCardsCvr(): void
    {
        // SD-774/SST-884: an order CVR that is the EAN (or "-") is no CVR, the card's is used
        $gln = self::gln('000000001234');
        $model = peppol_document_model(self::rows(array('ean' => $gln, 'cvrnr' => $gln)));
        self::assertSame(array('scheme' => '0184', 'id' => '22222222'), $model['buyer']['legalId']);
        self::assertContains('CVR taken from the debitor card', $model['issues']);
    }

    public function testEanFieldSchemes(): void
    {
        $gln = self::gln('000000001234');
        self::assertSame(array('scheme' => '0088', 'id' => $gln), peppol_model_endpoint_from_ean($gln));
        self::assertSame(array('scheme' => '0088', 'id' => $gln), peppol_model_endpoint_from_ean('0088:' . $gln));
        self::assertSame(array('scheme' => '0088', 'id' => $gln), peppol_model_endpoint_from_ean('GLN:' . $gln));
        // a CVR endpoint keeps "DK" when the field states it (PEPPOL-COMMON-R042 allows both forms)
        self::assertSame(array('scheme' => '0184', 'id' => 'DK22222222'), peppol_model_endpoint_from_ean('0184:DK22222222'));
        self::assertSame(array('scheme' => '0184', 'id' => 'DK22222222'), peppol_model_endpoint_from_ean('0184:dk 2222 2222'));
        self::assertSame(array('scheme' => '0184', 'id' => 'DK22222222'), peppol_model_endpoint_from_ean('DK:CVR:DK22222222'));
        self::assertSame(array('scheme' => '0184', 'id' => '22222222'), peppol_model_endpoint_from_ean('DK:CVR:22222222'));
        self::assertSame(array('scheme' => '0184', 'id' => '22222222'), peppol_model_endpoint_from_ean('0184:22-22-22-22'));
        // codes Peppol BIS 3.0.22 no longer accepts are mapped to their successors
        self::assertSame(array('scheme' => '0192', 'id' => '000000001'), peppol_model_endpoint_from_ean('9908:NO000000001MVA'));
        self::assertSame(array('scheme' => '0216', 'id' => '003700000000'), peppol_model_endpoint_from_ean('0037:003700000000'));
        self::assertSame(array('scheme' => '0007', 'id' => '0000000001'), peppol_model_endpoint_from_ean('0007:SE000000000101'));
        // an unknown numeric scheme is kept for peppol_precheck() to report, not sent as DK:CVR
        self::assertSame(array('scheme' => '1234', 'id' => '5678'), peppol_model_endpoint_from_ean('1234:5678'));
        self::assertNull(peppol_model_endpoint_from_ean(''));
        self::assertNull(peppol_model_endpoint_from_ean('XYZ:abc'));
    }

    public function testForeignVatNumbersUseTheirCountrysScheme(): void
    {
        self::assertSame(array('scheme' => '9930', 'id' => 'DE000000001'), peppol_model_endpoint_from_cvr(peppol_model_cvr_parts('DE 000000001')));
        self::assertSame(array('scheme' => '9944', 'id' => 'NL000000000B01'), peppol_model_endpoint_from_cvr(peppol_model_cvr_parts('NL000000000B01')));
        self::assertSame(array('scheme' => '0216', 'id' => '003700000001'), peppol_model_endpoint_from_cvr(peppol_model_cvr_parts('FI00000001')));
        self::assertSame(array('scheme' => '9933', 'id' => 'EL000000001'), peppol_model_endpoint_from_cvr(peppol_model_cvr_parts('EL000000001')));
        self::assertSame('EL000000001', peppol_model_vat_id(peppol_model_cvr_parts('EL000000001')));
        self::assertNull(peppol_model_endpoint_from_cvr(peppol_model_cvr_parts('N/A')));
    }

    public function testForeignLegalIdNeedsAValidCheckDigit(): void
    {
        // PEPPOL-COMMON-R041/R049 are fatal on CompanyID too, and a missing legal id is not: leave a wrong one out
        self::assertSame(array('scheme' => '0007', 'id' => '0000000018'), peppol_model_legal_id(peppol_model_cvr_parts('SE000000001801')));
        self::assertNull(peppol_model_legal_id(peppol_model_cvr_parts('SE000000000101')));
        self::assertSame(array('scheme' => '0192', 'id' => '000000019'), peppol_model_legal_id(peppol_model_cvr_parts('NO000000019MVA')));
        self::assertNull(peppol_model_legal_id(peppol_model_cvr_parts('NO000000001MVA')));
        self::assertTrue(peppol_model_no_orgnr_valid('000000019'));
        self::assertFalse(peppol_model_no_orgnr_valid('000000000'));
        self::assertFalse(peppol_model_se_orgnr_valid('00000000018'));
    }

    public function testForeignCurrencyStatesTheVatInDkk(): void
    {
        $rows = self::rows(array('land' => 'Tyskland', 'cvrnr' => 'DE000000001', 'valuta' => 'EUR', 'valutakurs' => '745.000'));
        $model = peppol_document_model($rows);

        self::assertSame('EUR', $model['currency']);
        self::assertSame('DKK', $model['taxCurrency']);
        self::assertSame(558.75, $model['taxCurrencyTaxAmount']);
        self::assertSame(array('scheme' => '9930', 'id' => 'DE000000001'), $model['buyer']['endpoint']);
        self::assertSame('58', $model['paymentMeans'][0]['code']);
        self::assertSame(375.0, $model['totals']['payable']);

        self::assertNull(peppol_document_model($rows, array('taxCurrency' => false))['taxCurrency']);
    }

    public function testCreditNoteCreditsPositiveAmountsAndReferencesTheInvoice(): void
    {
        $rows = self::rows(array('art' => 'DK', 'fakturanr' => '1002', 'kred_ord_id' => '500', 'sum' => '-300', 'moms' => '-75'),
            array(array('antal' => '-2.000')));
        $rows['creditedInvoice'] = array('id' => 500, 'fakturanr' => '1001', 'fakturadate' => '2026-11-02', 'art' => 'DO');
        $model = peppol_document_model($rows);

        self::assertSame('creditnote', $model['documentType']);
        self::assertSame('381', $model['typeCode']);
        self::assertSame(2.0, $model['lines'][0]['quantity']);
        self::assertSame(300.0, $model['lines'][0]['lineExtension']);
        self::assertSame(375.0, $model['totals']['payable']);
        self::assertNull($model['dueDate']);
        self::assertSame(array(), $model['paymentMeans']);
        self::assertNull($model['paymentTerms']);
        self::assertSame(array(array('id' => '1001', 'issueDate' => '2026-11-02')), $model['billingReferences']);
        self::assertTrue($model['reconciliation']['ok']);
    }

    public function testCreditNoteWithoutCreditedInvoiceHasNoBillingReference(): void
    {
        $model = peppol_document_model(self::rows(array('art' => 'DK', 'sum' => '-300', 'moms' => '-75'), array(array('antal' => '-2'))));
        self::assertSame(array(), $model['billingReferences']);
        self::assertContains('credit note without a credited invoice (kred_ord_id): no BillingReference', $model['issues']);
    }

    public function testDiscountLineWithNegativePriceIsANegativeQuantity(): void
    {
        // SST-746: a negative price goes to the quantity; BR-27 forbids a negative price
        $lines = array(array(), array('posnr' => '2', 'varenr' => 'RABAT', 'beskrivelse' => 'Rabat', 'antal' => '1', 'pris' => '-50', 'vare_id' => '10'));
        $model = peppol_document_model(self::rows(array('sum' => '250', 'moms' => '62.5'), $lines));
        $line = $model['lines'][1];
        self::assertSame(-1.0, $line['quantity']);
        self::assertSame(50.0, $line['netPrice']);
        self::assertSame(-50.0, $line['lineExtension']);
        self::assertSame(312.5, $model['totals']['payable']);

        // The same discount line on a credit note reduces what is credited
        $lines = array(array('antal' => '-2'), array('posnr' => '2', 'varenr' => 'RABAT', 'beskrivelse' => 'Rabat', 'antal' => '-1', 'pris' => '-50', 'vare_id' => '10'));
        $model = peppol_document_model(self::rows(array('art' => 'DK', 'sum' => '-250', 'moms' => '-62.5'), $lines));
        self::assertSame(-1.0, $model['lines'][1]['quantity']);
        self::assertSame(-50.0, $model['lines'][1]['lineExtension']);
        self::assertSame(312.5, $model['totals']['payable']);
    }

    public function testReturnedItemWithDiscountOnAnInvoiceKeepsTheDiscountInThePrice(): void
    {
        $lines = array(array('antal' => '3'), array('posnr' => '2', 'antal' => '-1', 'rabat' => '10'));
        $model = peppol_document_model(self::rows(array('sum' => '315', 'moms' => '78.75'), $lines));
        $line = $model['lines'][1];
        self::assertSame(-1.0, $line['quantity']);
        self::assertSame(-135.0, $line['lineExtension']);
        self::assertSame(135.0, $line['netPrice']);
        self::assertSame(150.0, $line['grossPrice']);
        self::assertSame(15.0, $line['priceDiscount']);
        self::assertSame(array(), $line['allowanceCharges']);
    }

    public function testCommentLinesBecomeLineNotes(): void
    {
        $lines = array(
            array('posnr' => '1', 'varenr' => '', 'vare_id' => '0', 'beskrivelse' => 'Levering til varemodtagelsen', 'antal' => '0', 'pris' => '0'),
            array('posnr' => '2'),
            array('posnr' => '3', 'varenr' => '', 'vare_id' => '0', 'beskrivelse' => 'SN: TEST-0001', 'antal' => '0', 'pris' => '0'),
            array('posnr' => '4', 'varenr' => '', 'vare_id' => '0', 'beskrivelse' => '', 'antal' => '0', 'pris' => '0'),
        );
        $model = peppol_document_model(self::rows(array(), $lines));
        self::assertCount(1, $model['lines']);
        self::assertSame("Levering til varemodtagelsen\nSN: TEST-0001", $model['lines'][0]['note']);

        $model = peppol_document_model(self::rows(array(), $lines), array('commentLines' => 'drop'));
        self::assertCount(1, $model['lines']);
        self::assertNull($model['lines'][0]['note']);
    }

    public function testLineWithAnAmountButNoTextIsKept(): void
    {
        // EasyUBL dropped lines with an empty text even when they had an amount
        $lines = array(
            array(),
            array('posnr' => '2', 'beskrivelse' => '', 'varenr' => 'VARE-9', 'antal' => '1', 'pris' => '100'),
            array('posnr' => '3', 'beskrivelse' => '<p></p>', 'varenr' => '', 'vare_id' => '0', 'bogf_konto' => '1000', 'antal' => '1', 'pris' => '50'),
        );
        $model = peppol_document_model(self::rows(array('sum' => '450', 'moms' => '112.5'), $lines));
        self::assertSame(array('Eksempelvare', 'VARE-9', 'Linje 3'), array_column($model['lines'], 'itemName'));
        self::assertTrue($model['reconciliation']['ok']);
    }

    public function testMultiLineTextGivesNameAndDescription(): void
    {
        $model = peppol_document_model(self::rows(array(), array(array('beskrivelse' => "Eksempelvare<br>Farve: blå\nStørrelse: L"))));
        self::assertSame('Eksempelvare', $model['lines'][0]['itemName']);
        self::assertSame("Eksempelvare\nFarve: blå\nStørrelse: L", $model['lines'][0]['itemDescription']);
    }

    public function testSamlevareComponentsAndNegativePosnrAreLeftOut(): void
    {
        $lines = array(
            array(),
            array('posnr' => '100', 'varenr' => 'DEL-1', 'beskrivelse' => 'Del af sæt', 'antal' => '1', 'pris' => '0', 'samlevare' => '1'),
            array('posnr' => '-1', 'varenr' => 'X', 'beskrivelse' => 'Fejl', 'antal' => '1', 'pris' => '10'),
        );
        $model = peppol_document_model(self::rows(array(), $lines));
        self::assertCount(1, $model['lines']);
        self::assertContains('line posnr -1 with an amount left out: momsupdat() does not count posnr < 0', $model['issues']);
    }

    public function testRoundingDifferenceToTheBookedTotalsIsAbsorbed(): void
    {
        // 3 x 33.333 = 99.999 per line; three lines each round to 100.00, the posting summed 299.997 and booked 300.00 / 74.99
        $lines = array(
            array('antal' => '3', 'pris' => '33.333'),
            array('posnr' => '2', 'antal' => '3', 'pris' => '33.333'),
            array('posnr' => '3', 'antal' => '3', 'pris' => '33.333'),
        );
        $model = peppol_document_model(self::rows(array('sum' => '299.99', 'moms' => '74.99'), $lines));

        self::assertSame(300.0, $model['totals']['lineExtension']);
        self::assertSame(-0.01, $model['reconciliation']['vatAdjustment']);
        self::assertSame(74.99, $model['taxTotal']);
        self::assertSame(74.99, $model['taxSubtotals'][0]['taxAmount']);
        self::assertSame(-0.01, $model['totals']['roundingAmount']);
        self::assertSame(374.98, $model['totals']['payable']);
        self::assertTrue($model['reconciliation']['ok']);
    }

    public function testLargeDifferenceToTheBookedTotalsIsNotHidden(): void
    {
        $model = peppol_document_model(self::rows(array('sum' => '310', 'moms' => '77.5')));
        self::assertFalse($model['reconciliation']['ok']);
        self::assertNull($model['totals']['roundingAmount']);
        self::assertSame(375.0, $model['totals']['payable']);
        self::assertSame(310.0, $model['reconciliation']['bookedNet']);
    }

    public function testBookedVatTheLinesDoNotCarryIsNotHidden(): void
    {
        // An old order with rate 0 on the order but VAT in ordrer.moms: the lines give no S category to put it in
        $model = peppol_document_model(self::rows(array('momssats' => '0', 'moms' => '0.50'), array(array('momssats' => '25'))));
        self::assertSame('E', $model['lines'][0]['vatCategory']);
        self::assertFalse($model['reconciliation']['ok']);
        self::assertSame(0.0, $model['taxTotal']);
        self::assertContains('VAT differs from the booked ordrer.moms by 0.50 and was not adjusted', $model['issues']);
    }

    public function testDueDateMatchesForfaldsdag(): void
    {
        $terms = array(array('Netto', 0), array('Netto', 8), array('Netto', 14), array('Netto', 30), array('Netto', 35),
            array('Lb. md.', 0), array('Lb. md.', 15), array('Lb. md.', 30), array('Kontant', 0), array('Efterkrav', 10),
            array('Forud', 8), array('Kreditkort', 0));
        $dates = array('2026-01-31', '2026-02-27', '2026-02-28', '2028-02-28', '2026-03-31', '2026-06-15', '2026-11-09', '2026-12-20', '2026-12-31');
        foreach ($dates as $date) {
            foreach ($terms as $term) {
                $expected = usdate(forfaldsdag($date, $term[0], $term[1]));
                self::assertSame($expected, peppol_model_due_date($date, $term[0], $term[1]), "$date {$term[0]} {$term[1]}");
            }
        }
        self::assertNull(peppol_model_due_date('', 'Netto', 8));
    }

    public function testFikPaymentIdMatchesModulus10(): void
    {
        // Independent Luhn check over the 14 digits as modulus_10() lays them out
        foreach (array(array('1077', '1001', 0, 14), array('1077', '1001', 5, 9), array('', '123456789012345678', 0, 14), array('12', '7-1', 0, 14)) as $case) {
            $id = peppol_model_fik_payment_id($case[0], $case[1], $case[2], $case[3]);
            self::assertMatchesRegularExpression('/^[0-9]{15}$/', $id);
            $sum = 0;
            for ($i = 0; $i < 15; $i++) {
                $digit = (int)$id[14 - $i];
                if ($i % 2) {
                    $digit *= 2;
                    $digit = ($digit > 9) ? $digit - 9 : $digit;
                }
                $sum += $digit;
            }
            self::assertSame(0, $sum % 10, implode(' ', $case));
        }
        self::assertSame('00000000001001', substr(peppol_model_fik_payment_id('1077', '1001', 0, 14), 0, 14));
        self::assertSame('01077000001001', substr(peppol_model_fik_payment_id('1077', '1001', 5, 9), 0, 14));
        self::assertSame('00000000000000', substr(peppol_model_fik_payment_id('12', '7-1', 0, 14), 0, 14));

        $rows = self::rows();
        $rows['seller']['bank_fi'] = '12345678';
        $model = peppol_document_model($rows);
        self::assertSame('93', $model['paymentMeans'][0]['code']);
        self::assertSame('71#' . peppol_model_fik_payment_id('1077', '1001', 0, 14), $model['paymentMeans'][0]['paymentId']);
        self::assertSame(array('id' => '12345678', 'name' => null, 'bic' => null), $model['paymentMeans'][0]['payeeAccount']);
        // One payment means only (UBL-SR-44/47); without FIK the bank account is used
        self::assertCount(1, $model['paymentMeans']);
        self::assertSame('42', peppol_document_model($rows, array('fik' => false))['paymentMeans'][0]['code']);
    }

    public function testInvoiceSettledAtPostingIsPrepaid(): void
    {
        $rows = self::rows(array('betalingsbet' => 'Kreditkort', 'betalingsdage' => '0'));
        $rows['settings']['cardAccount'] = '5800';
        $model = peppol_document_model($rows);
        self::assertSame(375.0, $model['totals']['prepaid']);
        self::assertSame(0.0, $model['totals']['payable']);
        self::assertSame(array(), $model['paymentMeans']);
        self::assertSame('Betalt med betalingskort', $model['paymentTerms']);

        // Without the account under DIV 3 bogfor_nu() leaves an open item, so it is payable
        $rows['settings']['cardAccount'] = '';
        self::assertSame(375.0, peppol_document_model($rows)['totals']['payable']);
    }

    public function testOrderNoteIsSentOnlyWithTheSetting(): void
    {
        $rows = self::rows(array('notes' => 'Bemærkning til ordre'));
        self::assertNull(peppol_document_model($rows)['note']);
        $rows['settings']['sendOrderNote'] = 'on';
        self::assertSame('Bemærkning til ordre', peppol_document_model($rows)['note']);
    }

    public function testWithoutKundeordnrOrderReferenceIsNa(): void
    {
        $model = peppol_document_model(self::rows(array('kundeordnr' => '')));
        self::assertNull($model['buyerReference']);
        self::assertSame('NA', $model['orderReference']);
        self::assertSame('9001', $model['salesOrderId']);
    }

    public function testDeliveryAddressAndFallbacks(): void
    {
        $rows = self::rows(array('lev_navn' => 'Lager', 'lev_addr1' => 'Lagervej 3, Port 2', 'lev_postnr' => '7000', 'lev_bynavn' => 'Lagerby', 'lev_land' => 'Sverige'));
        $model = peppol_document_model($rows);
        self::assertSame('Lager', $model['delivery']['name']);
        self::assertSame('Lagervej 3', $model['delivery']['address']['street']);
        self::assertSame('Port 2', $model['delivery']['address']['additionalStreet']);
        self::assertSame('SE', $model['delivery']['address']['country']);

        // The customer address is incomplete: the delivery address is used for it (sendInvoice(), 20260604)
        $rows['order']['addr1'] = '';
        $model = peppol_document_model($rows);
        self::assertSame('Lagervej 3', $model['buyer']['address']['street']);
    }

    public function testSellerWithoutVatRegistrationSendsCategoryO(): void
    {
        $model = peppol_document_model(self::rows(array('momssats' => '0', 'moms' => '0'), array(array('momssats' => '0'))), array('sellerVatRegistered' => false));
        self::assertSame('O', $model['lines'][0]['vatCategory']);
        self::assertNull($model['lines'][0]['vatPercent']);
        self::assertNull($model['taxSubtotals'][0]['percent']);
        self::assertNull($model['seller']['vatId']);
        self::assertNull($model['buyer']['vatId']);
    }

    public function testSellerEndpointCanComeFromTheRegistration(): void
    {
        $model = peppol_document_model(self::rows(), array('sellerEndpoint' => array('scheme' => '0184', 'id' => '11111111')));
        self::assertSame(array('scheme' => '0184', 'id' => '11111111'), $model['seller']['endpoint']);
    }

    public function testUnitCodes(): void
    {
        self::assertSame('EA', peppol_model_unit_code('stk.'));
        self::assertSame('EA', peppol_model_unit_code(''));
        self::assertSame('EA', peppol_model_unit_code('noget andet'));
        self::assertSame('HUR', peppol_model_unit_code('Timer'));
        self::assertSame('SET', peppol_model_unit_code('Sæt'));
        self::assertSame('XPK', peppol_model_unit_code('pk.'));
        self::assertSame('XBX', peppol_model_unit_code('ks'));
        self::assertSame('MON', peppol_model_unit_code('mdr.'));
    }

    public function testRoundingMirrorsAfrund(): void
    {
        foreach (array(1.005, 2.675, -1.005, 0.0049, 25.245, 479.275, -479.275, 0.0) as $value) {
            self::assertSame(afrund($value, 2), peppol_model_round($value, 2), (string)$value);
            self::assertSame(afrund($value, 3), peppol_model_round($value, 3), (string)$value);
        }
    }

    public function testLatin9RowsAreConvertedToUtf8(): void
    {
        $rows = array('order' => array('firmanavn' => "S\xE6lger \xA4", 'sum' => 10.5), 'lines' => array(array('beskrivelse' => "Bl\xE5")));
        $utf8 = peppol_rows_to_utf8($rows, 'LATIN9');
        self::assertSame('Sælger €', $utf8['order']['firmanavn']);
        self::assertSame(10.5, $utf8['order']['sum']);
        self::assertSame('Blå', $utf8['lines'][0]['beskrivelse']);
        self::assertSame('Sælger', peppol_rows_to_utf8('Sælger', 'UTF8'));
        self::assertSame('Sælger', peppol_rows_to_utf8("S\xE6lger", 'UTF8'));
    }

    public function testControlCharactersAreRemovedFromTexts(): void
    {
        $model = peppol_document_model(self::rows(array('firmanavn' => "Testkunde\x01 ApS"), array(array('beskrivelse' => "Vare\x0B med\x1F tegn"))));
        self::assertSame('Testkunde ApS', $model['buyer']['registrationName']);
        self::assertSame('Vare med tegn', $model['lines'][0]['itemName']);
    }

    public function testOrderWithoutCountryTakesTheCardsCountry(): void
    {
        // SST-870 order: the order's country, then (SD-815) the card's, then the CVR prefix, then DK
        $rows = self::rows(array('land' => '', 'cvrnr' => '', 'valuta' => 'SEK', 'valutakurs' => '65.000'));
        $rows['customer']['cvrnr'] = '';
        $rows['customer']['land'] = 'Sverige';
        $rows['order']['ean'] = self::gln('000000001234');
        $model = peppol_document_model($rows);
        self::assertSame('SE', $model['buyer']['address']['country']);
        self::assertContains('the order has no country: the debitor card\'s country is used', $model['issues']);

        $rows['customer']['land'] = '';
        self::assertSame('DK', peppol_document_model($rows)['buyer']['address']['country']);
        $rows['order']['cvrnr'] = 'SE000000001801';
        self::assertSame('SE', peppol_document_model($rows)['buyer']['address']['country']);
    }

    public function testZeroPricedExemptLineGivesNoCountryWarning(): void
    {
        // A private Swedish customer in SEK with a free freight line flagged momsfri: E for 0.00, nothing to check
        $rows = self::rows(array('land' => 'Sverige', 'cvrnr' => '', 'valuta' => 'SEK', 'valutakurs' => '65.000'), array(
            array(),
            array('posnr' => '2', 'varenr' => 'FRAGT', 'vare_id' => '10', 'beskrivelse' => 'Fragt', 'antal' => '1', 'pris' => '0', 'momsfri' => 'on'),
        ));
        $model = peppol_document_model($rows);
        self::assertSame('E', $model['lines'][1]['vatCategory']);
        self::assertSame(0.0, $model['lines'][1]['lineExtension']);
        self::assertSame(array(), preg_grep('/no VAT for a customer/', $model['issues']));

        $rows['lines'][1]['pris'] = '50';
        $rows['order']['sum'] = '350';
        self::assertNotSame(array(), preg_grep('/no VAT for a customer/', peppol_document_model($rows)['issues']));
    }

    public function testSetComponentsCarryTheAmountsAndTheHeadLineIsKept(): void
    {
        // A sæt: the components carry the set discount, the head line (samlevare 'on', saet) is priced 0 with the set
        // price in lev_varenr. momsupdat() books the components; the head line stays as a line of 0.00.
        $lines = array(
            array('posnr' => '1', 'varenr' => 'DEL-1', 'antal' => '1', 'pris' => '100', 'rabat' => '10', 'saet' => '1'),
            array('posnr' => '1', 'id' => '2', 'varenr' => 'DEL-2', 'antal' => '1', 'pris' => '200', 'rabat' => '10', 'saet' => '1'),
            array('posnr' => '2', 'id' => '3', 'varenr' => 'SAET', 'beskrivelse' => 'Sæt', 'antal' => '1', 'pris' => '0', 'rabat' => '10',
                'samlevare' => 'on', 'saet' => '1', 'lev_varenr' => '270|10'),
        );
        $model = peppol_document_model(self::rows(array('sum' => '270', 'moms' => '67.5'), $lines));
        self::assertSame(array(90.0, 180.0, 0.0), array_column($model['lines'], 'lineExtension'));
        self::assertSame(337.5, $model['totals']['payable']);
        self::assertTrue($model['reconciliation']['ok']);
        self::assertSame(0.0, $model['reconciliation']['vatAdjustment']);
    }

    public function testCreditNoteSettledAtPostingIsPrepaidToo(): void
    {
        $rows = self::rows(array('art' => 'DK', 'betalingsbet' => 'Kontant', 'betalingsdage' => '0', 'sum' => '-300', 'moms' => '-75'),
            array(array('antal' => '-2')));
        $rows['settings']['cashAccount'] = '1000';
        $model = peppol_document_model($rows);
        self::assertSame(375.0, $model['totals']['prepaid']);
        self::assertSame(0.0, $model['totals']['payable']);
        self::assertNull($model['paymentTerms']);
    }

    public function testRowsWithoutOrderAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        peppol_document_model(array('order' => null));
    }

    public function testSellerWithoutSalesVatRateFromTheRowsSendsCategoryO(): void
    {
        // peppol_invoice_rows() sets settings.vatRegistered false when no Salgsmoms rate is above 0
        $rows = self::rows(array('momssats' => '0', 'moms' => '0'), array(array('momssats' => '0')));
        $rows['settings']['vatRegistered'] = false;
        $model = peppol_document_model($rows);
        self::assertSame('O', $model['lines'][0]['vatCategory']);
        self::assertSame('O', $model['taxSubtotals'][0]['category']);
        self::assertNull($model['seller']['vatId'], 'BR-O-02: no seller VAT number');
        self::assertNull($model['buyer']['vatId'], 'BR-O-02: no buyer VAT number');
        self::assertSame(array('scheme' => '0184', 'id' => '11111111'), $model['seller']['legalId'], 'DK-R-002: the CVR stays as legal id');
        self::assertTrue($model['reconciliation']['ok']);

        // The explicit option wins over the rows, and rows without the key keep the registered default
        self::assertSame('E', peppol_document_model($rows, array('sellerVatRegistered' => true))['lines'][0]['vatCategory']);
        unset($rows['settings']['vatRegistered']);
        self::assertSame('DK11111111', peppol_document_model($rows)['seller']['vatId']);
    }

    public function testVatRegistrationIsReadFromTheSalesVatRates(): void
    {
        $groups = array(
            array('fiscal_year' => '1', 'box2' => '25'),
            array('fiscal_year' => '2', 'box2' => '0.00'),
            array('fiscal_year' => '2', 'box2' => '0,00'),
        );
        self::assertTrue(peppol_rows_vat_registered($groups, 1));
        self::assertFalse(peppol_rows_vat_registered($groups, 2), 'every rate of the fiscal year is 0');
        self::assertTrue(peppol_rows_vat_registered($groups, 3), 'no rows for the year: all rows count');
        self::assertTrue(peppol_rows_vat_registered($groups, null));
        self::assertFalse(peppol_rows_vat_registered(array(array('fiscal_year' => '1', 'box2' => '')), 1));
        self::assertTrue(peppol_rows_vat_registered(array(), 1), 'no Salgsmoms rows: the registered default');
        self::assertTrue(peppol_rows_vat_registered(array(array('fiscal_year' => '4', 'box2' => '12,5')), 4));
    }

    public function testForeignNumberWithoutPrefixBelongsToTheAddressCountry(): void
    {
        // An 8-digit number without prefix and a Finnish address is a Finnish business id, not a Danish CVR
        $rows = self::rows(array('land' => 'Finland', 'cvrnr' => '00000001', 'momssats' => '0', 'moms' => '0'), array(array('momssats' => '0')));
        $rows['customer']['cvrnr'] = '00000001';
        $rows['debtorGroup']['box10'] = 'B2B-EU';
        $model = peppol_document_model($rows);
        self::assertSame(array('scheme' => '0216', 'id' => '003700000001'), $model['buyer']['endpoint']);
        self::assertSame('FI00000001', $model['buyer']['vatId']);
        self::assertNull($model['buyer']['legalId']);
        self::assertSame('K', $model['lines'][0]['vatCategory']);

        // "DK" in front still makes it a Danish CVR
        $rows['order']['cvrnr'] = 'DK22222222';
        $model = peppol_document_model($rows);
        self::assertSame(array('scheme' => '0184', 'id' => '22222222'), $model['buyer']['endpoint']);
        self::assertSame('DK22222222', $model['buyer']['vatId']);

        // A Greenland or Faroe address keeps reading it as a CVR (sendInvoice())
        $rows = self::rows(array('land' => 'Grønland', 'cvrnr' => '22222222', 'momssats' => '0', 'moms' => '0'), array(array('momssats' => '0')));
        self::assertSame(array('scheme' => '0184', 'id' => '22222222'), peppol_document_model($rows)['buyer']['endpoint']);
    }

    public function testReturnedItemWithALargeQuantityKeepsR120(): void
    {
        // -612 x 12.345 less 7 %: at 4 decimals the net price 11.4808 gives 7026.25, 0.03 off (PEPPOL-EN16931-R120)
        $lines = array(array('antal' => '-612', 'pris' => '12.345', 'rabat' => '7'));
        $model = peppol_document_model(self::rows(array('sum' => '-7026.28', 'moms' => '-1756.57'), $lines));
        $line = $model['lines'][0];
        self::assertSame(-7026.28, $line['lineExtension']);
        self::assertSame(11.48085, $line['netPrice']);
        self::assertSame(12.345, $line['grossPrice']);
        self::assertSame(0.86415, $line['priceDiscount']);
        self::assertSame(0.0, round($line['grossPrice'] - $line['priceDiscount'] - $line['netPrice'], 8), 'PEPPOL-EN16931-R046');
        self::assertLessThanOrEqual(0.005, abs($line['lineExtension'] - $line['quantity'] * $line['netPrice']));
        self::assertLessThanOrEqual(0.005, $model['reconciliation']['maxLineSlack']);
        self::assertTrue($model['reconciliation']['ok']);

        // A small quantity keeps 4 decimals
        $small = peppol_model_line_amounts(array('antal' => '-7', 'pris' => '12.345', 'rabat' => '7'), false);
        self::assertSame(-80.37, $small['lineExtension']);
        self::assertSame(11.4814, $small['netPrice']);
    }

    public function testRoundingLimitFollowsTheNumberOfLines(): void
    {
        self::assertSame(0.015, peppol_model_rounding_limit(1));
        self::assertSame(0.015, peppol_model_rounding_limit(0));
        self::assertSame(0.51, peppol_model_rounding_limit(100));
        self::assertSame(1.0, peppol_model_rounding_limit(500));
        self::assertSame(0.2, peppol_model_rounding_limit(500, 0.2));

        // One line explains at most 0.015: a booked VAT 0.02 off is not rounding
        $model = peppol_document_model(self::rows(array('sum' => '300', 'moms' => '75.02')));
        self::assertFalse($model['reconciliation']['ok']);
        self::assertSame(0.0, $model['reconciliation']['vatAdjustment']);
        $model = peppol_document_model(self::rows(array('sum' => '300', 'moms' => '75.01')));
        self::assertTrue($model['reconciliation']['ok']);
        self::assertSame(0.01, $model['reconciliation']['vatAdjustment']);
        // ... and a net difference of 0.80 is no rounding at all (the old limit was 1.00)
        $model = peppol_document_model(self::rows(array('sum' => '300.80', 'moms' => '75')));
        self::assertFalse($model['reconciliation']['ok']);
        self::assertNull($model['totals']['roundingAmount']);
    }

    public function testSettledAtPostingFollowsTheOpenItemNotTodaysSettings(): void
    {
        $rows = self::rows(array('betalingsbet' => 'Kontant', 'betalingsdage' => '0'));
        // Posted straight to the cash account (no open item), although DIV 3 has no account today
        $rows['openItem'] = false;
        $model = peppol_document_model($rows);
        self::assertSame(375.0, $model['totals']['prepaid']);
        self::assertSame(0.0, $model['totals']['payable']);
        self::assertSame('Betalt kontant', $model['paymentTerms']);

        // An open item was made, although DIV 3 has an account today: the customer still owes it
        $rows['openItem'] = true;
        $rows['settings']['cashAccount'] = '5800';
        $model = peppol_document_model($rows);
        self::assertNull($model['totals']['prepaid']);
        self::assertSame(375.0, $model['totals']['payable']);

        // Netto terms are never settled at posting
        $rows = self::rows();
        $rows['openItem'] = false;
        self::assertSame(375.0, peppol_document_model($rows)['totals']['payable']);
    }

    public function testIdentifierFormatsOfTheFatalPeppolRules(): void
    {
        self::assertTrue(peppol_model_identifier_valid('0184', '22222222'));
        self::assertTrue(peppol_model_identifier_valid('0184', 'DK22222222'));
        self::assertFalse(peppol_model_identifier_valid('0184', '2222222'));
        self::assertTrue(peppol_model_identifier_valid('0096', '0000000001'));
        self::assertFalse(peppol_model_identifier_valid('0096', '000000001'), 'PEPPOL-COMMON-R052: a P-number has 10 digits');
        self::assertTrue(peppol_model_identifier_valid('0198', 'DK00000001'));
        self::assertFalse(peppol_model_identifier_valid('0198', 'DK0000001'), 'PEPPOL-COMMON-R053');
        self::assertTrue(peppol_model_identifier_valid('0208', '0000000196'));
        self::assertFalse(peppol_model_identifier_valid('0208', '0000000195'), 'PEPPOL-COMMON-R043: mod 97');
        self::assertTrue(peppol_model_identifier_valid('0151', '10000000032'));
        self::assertFalse(peppol_model_identifier_valid('0151', '10000000031'), 'PEPPOL-COMMON-R050: mod 89');
        self::assertTrue(peppol_model_identifier_valid('0088', self::gln('000000001234')));
        self::assertFalse(peppol_model_identifier_valid('0007', '0000000001'));
        self::assertTrue(peppol_model_identifier_valid('9930', 'anything'), 'no fatal format rule for the scheme');
    }
}
