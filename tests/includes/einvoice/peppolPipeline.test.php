<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/einvoice/peppolPipeline.test.php --- ver 5.1.0 --- 2026-11-09 ---
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
// 20261109 CL/MMR SD-815 Created (draft). Builder to writer: synthetic order rows through peppol_document_model(), peppol_precheck() and peppol_bis_xml().
//                 With PEPPOL_BIS_VALIDATOR set, every produced XML also goes through the Peppol validator.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/einvoice/peppolModel.php';
require_once __DIR__ . '/../../../includes/einvoice/peppolDocumentModel.php';
require_once __DIR__ . '/../../../includes/einvoice/peppolPrecheck.php';
require_once __DIR__ . '/../../../includes/einvoice/peppolBisWriter.php';

/**
 * SD-815: the contract between the builder (peppol_document_model()) and the writer (peppol_bis_xml()), on synthetic rows.
 *
 * Each scenario is a path that the builder takes on real orders, or one that the anonymised test regnskab has no order for
 * (FIK, prepaid, EUR, domestic reverse charge, not VAT registered). For every scenario the precheck has nothing to say,
 * the writer's model check finds no error, and the XML carries the model's totals. All data is synthetic: CVR 11111111
 * and 22222222 fail the modulus 11 check, the GLN is built on a 000000 prefix, and the SE/NO numbers are test numbers
 * with a valid check digit.
 *
 * Environment: PEPPOL_BIS_VALIDATOR, the command that validates XML files and exits 0 when no file has a fatal error,
 * for example "<python> -I <path>/validate.py" (UBL XSD + Peppol schematron). Without it the validator test is skipped.
 */
final class peppolPipeline extends TestCase
{
    /**
     * The rows of a plain Danish invoice: 2 x 150.00 at 25 % to a customer with CVR, Netto 14 dage, paid by reg.nr./account.
     *
     * @param array<string, mixed> $order Fields that replace the order's.
     * @param list<array<string, mixed>>|null $lines The lines (each merged onto the default line), null for the default line.
     * @return array<string, mixed>
     */
    private static function rows(array $order = array(), ?array $lines = null): array
    {
        $defaultLine = array(
            'id' => '1', 'posnr' => '1', 'varenr' => 'VARE-1', 'vare_id' => '10', 'beskrivelse' => 'Eksempelvare', 'enhed' => 'stk',
            'antal' => '2.000', 'pris' => '150.000', 'rabat' => '0.000', 'rabatart' => '', 'procent' => '100.000', 'momsfri' => '',
            'momssats' => '25.000', 'omvbet' => '', 'bogf_konto' => '1000', 'samlevare' => '', 'saet' => '', 'serienr' => '', 'm_rabat' => '0',
        );
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
            'lines' => array_map(static fn (array $fields): array => array_merge($defaultLine, $fields), $lines ?? array(array())),
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
     * A synthetic GLN with a valid GS1 check digit.
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
     * The scenarios: rows plus builder options. Booked ordrer.sum/moms are what momsupdat() would book for the lines.
     *
     * @return array<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function scenarios(): array
    {
        $cases = array();
        $cases['plain DK invoice, reg.nr./account (42)'] = array(self::rows(), array());

        $rows = self::rows(array('art' => 'DK', 'fakturanr' => '1002', 'kred_ord_id' => '500', 'sum' => '-250', 'moms' => '-62.5'), array(
            array('antal' => '-2'),
            array('posnr' => '2', 'varenr' => 'RABAT', 'beskrivelse' => 'Rabat', 'antal' => '-1', 'pris' => '-50'),
        ));
        $rows['creditedInvoice'] = array('id' => 500, 'fakturanr' => '1001', 'fakturadate' => '2026-11-02', 'art' => 'DO');
        $cases['credit note with a discount line'] = array($rows, array());

        $cases['discounts: percent, amount per unit, procent, discount line, returned item'] = array(self::rows(array('sum' => '280', 'moms' => '70'), array(
            array('rabat' => '10'),
            array('posnr' => '2', 'varenr' => 'VARE-2', 'antal' => '1', 'pris' => '100', 'rabat' => '5', 'rabatart' => 'amount'),
            array('posnr' => '3', 'varenr' => 'VARE-3', 'antal' => '4', 'pris' => '50', 'procent' => '50'),
            array('posnr' => '4', 'varenr' => 'RABAT', 'beskrivelse' => 'Rabat', 'antal' => '1', 'pris' => '-50'),
            array('posnr' => '5', 'antal' => '-1', 'rabat' => '10'),
        )), array());

        $cases['comment lines, two-line text, GLN with CVR, no kundeordnr (NA)'] = array(self::rows(array('ean' => self::gln('000000000001'), 'kundeordnr' => ''), array(
            array('posnr' => '1', 'varenr' => '', 'vare_id' => '0', 'beskrivelse' => 'Levering til varemodtagelsen', 'antal' => '0', 'pris' => '0'),
            array('posnr' => '2', 'beskrivelse' => "Eksempelvare\nFarve: blå & grøn <XL>"),
            array('posnr' => '3', 'varenr' => '', 'vare_id' => '0', 'beskrivelse' => 'Tak for handlen', 'antal' => '0', 'pris' => '0'),
        )), array());

        $cases['S line and an exempt item group (E)'] = array(self::rows(array('sum' => '350', 'moms' => '75'), array(
            array(),
            array('posnr' => '2', 'varenr' => 'PORTO', 'vare_id' => '20', 'beskrivelse' => 'Porto', 'antal' => '1', 'pris' => '50', 'momsfri' => 'on', 'momssats' => '0'),
        )), array());

        $cases['EU company: goods K and services AE, Swedish orgnr'] = array(self::rows(array('land' => 'Sverige', 'cvrnr' => 'SE000000001801', 'momssats' => '0', 'sum' => '400', 'moms' => '0'), array(
            array('momsfri' => 'on', 'momssats' => '0'),
            array('posnr' => '2', 'varenr' => 'SERVICE', 'vare_id' => '30', 'beskrivelse' => 'Kalibrering', 'antal' => '1', 'pris' => '100', 'momsfri' => 'on', 'momssats' => '0', 'enhed' => 'timer'),
        )), array());

        $cases['export to Norway in NOK (G), IBAN (58)'] = array(self::rows(array('land' => 'Norge', 'cvrnr' => 'NO000000019MVA', 'valuta' => 'NOK', 'valutakurs' => '64.000', 'momssats' => '0', 'moms' => '0'),
            array(array('momsfri' => 'on', 'momssats' => '0'))), array());

        $cases['EUR invoice with the VAT in DKK'] = array(self::rows(array('land' => 'Tyskland', 'cvrnr' => 'DE000000001', 'valuta' => 'EUR', 'valutakurs' => '745.000')), array());

        $cases['Greenland: DK address, G'] = array(self::rows(array('land' => 'Grønland', 'momssats' => '0', 'moms' => '0'), array(array('momsfri' => 'on', 'momssats' => '0'))), array());

        $cases['domestic reverse charge (AE)'] = array(self::rows(array('moms' => '0', 'omvbet' => 'on'), array(array('omvbet' => 'on'))), array());

        $rows = self::rows();
        $rows['seller']['bank_fi'] = '12345678';
        $cases['FIK (93)'] = array($rows, array());

        $rows = self::rows(array('betalingsbet' => 'Kreditkort', 'betalingsdage' => '0'));
        $rows['settings']['cardAccount'] = '5800';
        $cases['card payment settled at posting: prepaid, payable 0'] = array($rows, array());

        $cases['rounding: booked 299.99 / 74.99 for three lines of 99.999'] = array(self::rows(array('sum' => '299.99', 'moms' => '74.99'), array(
            array('antal' => '3', 'pris' => '33.333'),
            array('posnr' => '2', 'antal' => '3', 'pris' => '33.333'),
            array('posnr' => '3', 'antal' => '3', 'pris' => '33.333'),
        )), array());

        $cases['sæt: components carry the amounts'] = array(self::rows(array('sum' => '270', 'moms' => '67.5'), array(
            array('posnr' => '1', 'varenr' => 'DEL-1', 'antal' => '1', 'pris' => '100', 'rabat' => '10', 'saet' => '1'),
            array('posnr' => '1', 'id' => '2', 'varenr' => 'DEL-2', 'antal' => '1', 'pris' => '200', 'rabat' => '10', 'saet' => '1'),
            array('posnr' => '2', 'id' => '3', 'varenr' => 'SAET', 'beskrivelse' => 'Sæt', 'antal' => '1', 'pris' => '0', 'rabat' => '10',
                'samlevare' => 'on', 'saet' => '1', 'lev_varenr' => '270|10'),
        )), array());

        $cases['seller not VAT registered (O)'] = array(self::rows(array('momssats' => '0', 'moms' => '0'), array(array('momssats' => '0'))),
            array('sellerVatRegistered' => false));

        $rows = self::rows(array('lev_navn' => 'Lageret', 'lev_addr1' => 'Lagervej 9', 'lev_postnr' => '7000', 'lev_bynavn' => 'Lagerby', 'notes' => 'Bemærkning til ordren'));
        $rows['settings']['sendOrderNote'] = 'on';
        $cases['delivery address and order note'] = array($rows, array());
        return $cases;
    }

    /**
     * @param array<string, mixed> $rows
     * @param array<string, mixed> $opts
     * @return array{array<string, mixed>, string}
     */
    private static function pipeline(array $rows, array $opts): array
    {
        $model = peppol_document_model($rows, $opts);
        return array($model, peppol_bis_xml($model));
    }

    /**
     * @param array<string, mixed> $rows
     * @param array<string, mixed> $opts
     */
    #[DataProvider('scenarios')]
    public function testBuilderOutputIsAcceptedByPrecheckAndWriter(array $rows, array $opts): void
    {
        $model = peppol_document_model($rows, $opts);

        $this->assertSame(array(), peppol_precheck($model), 'precheck');
        $this->assertSame(array(), peppol_bis_validate_model($model), 'writer model check');
        $this->assertTrue($model['reconciliation']['ok'], 'reconciliation');
        // The builder adds only the two optional keys the contract documents
        $this->assertSame(array('issues', 'reconciliation'), array_keys(array_diff_key($model, peppol_model_example())));
        $this->assertSame(array(), array_diff_key(peppol_model_example(), $model));

        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML(peppol_bis_xml($model)));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cbc', PEPPOL_BIS_NS_CBC);
        $xpath->registerNamespace('cac', PEPPOL_BIS_NS_CAC);
        $isCredit = ($model['documentType'] === 'creditnote');
        $this->assertSame($isCredit ? 'CreditNote' : 'Invoice', $dom->documentElement->localName);
        $this->assertSame($model['id'], $xpath->evaluate('string(/*/cbc:ID)'));
        $this->assertSame(count($model['lines']), (int)$xpath->evaluate($isCredit ? 'count(/*/cac:CreditNoteLine)' : 'count(/*/cac:InvoiceLine)'));
        $this->assertSame(number_format($model['totals']['payable'], 2, '.', ''), $xpath->evaluate('string(/*/cac:LegalMonetaryTotal/cbc:PayableAmount)'));
        $this->assertSame(number_format($model['taxTotal'], 2, '.', ''), $xpath->evaluate('string(/*/cac:TaxTotal[1]/cbc:TaxAmount)'));
        $this->assertSame($model['seller']['endpoint']['id'], $xpath->evaluate('string(/*/cac:AccountingSupplierParty/cac:Party/cbc:EndpointID)'));
        $this->assertSame($model['buyer']['endpoint']['id'], $xpath->evaluate('string(/*/cac:AccountingCustomerParty/cac:Party/cbc:EndpointID)'));
        // PEPPOL-EN16931-R008: no empty elements
        $this->assertSame(0, $xpath->query('//*[not(*) and not(normalize-space())]')->length);
        // The payable amount is what was booked, unless the invoice was settled at posting
        $booked = round(($isCredit ? -1 : 1) * ((float)$rows['order']['sum'] + (float)$rows['order']['moms']), 2);
        $this->assertSame($booked, round($model['totals']['payable'] + (float)$model['totals']['prepaid'], 2));
    }

    public function testValidatorAcceptsTheBuilderOutput(): void
    {
        $command = getenv('PEPPOL_BIS_VALIDATOR');
        if (!is_string($command) || trim($command) === '') {
            $this->markTestSkipped('PEPPOL_BIS_VALIDATOR is not set (the command that runs the UBL XSD and Peppol schematron validator).');
        }
        $dir = sys_get_temp_dir() . '/peppolPipeline-' . getmypid() . '-' . bin2hex(random_bytes(4));
        $this->assertTrue(mkdir($dir, 0700));
        $files = array();
        try {
            $n = 0;
            foreach (self::scenarios() as $case) {
                $n++;
                $file = sprintf('%s/%02d.xml', $dir, $n);
                file_put_contents($file, self::pipeline($case[0], $case[1])[1]);
                $files[] = $file;
            }
            $parts = array_map('escapeshellarg', preg_split('/\s+/', trim($command)));
            exec(implode(' ', $parts) . ' ' . implode(' ', array_map('escapeshellarg', $files)) . ' 2>&1', $output, $exitCode);
            $this->assertSame(0, $exitCode, "validator reported fatal errors:\n" . implode("\n", $output));
        } finally {
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }
}
