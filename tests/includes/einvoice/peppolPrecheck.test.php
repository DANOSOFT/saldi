<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/einvoice/peppolPrecheck.test.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261109 CL/MMR SD-815 Created (draft). Tests for peppol_precheck(): the Danish messages about data the user must fix before an e-invoice is sent.
// 20261109 CL/MMR SD-815 Review fixes: endpoint formats 0096/0198/0208/0151, the seller's own country's VAT number on an EU sale, and the R120 line slack.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/einvoice/peppolModel.php';
require_once __DIR__ . '/../../../includes/einvoice/peppolDocumentModel.php';
require_once __DIR__ . '/../../../includes/einvoice/peppolPrecheck.php';

/**
 * SD-815: peppol_precheck() reads only the model. All data is synthetic: CVR 11111111 and 22222222 fail the modulus 11
 * check, so they belong to nobody; GLNs are built on a 000000 prefix.
 */
final class peppolPrecheck extends TestCase
{
    /**
     * The contract's example model, which is complete and must pass.
     *
     * @return array<string, mixed>
     */
    private static function model(string $documentType = 'invoice'): array
    {
        return peppol_model_example($documentType);
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

    public function testCompleteModelPasses(): void
    {
        self::assertSame(array(), peppol_precheck(self::model()));
        self::assertSame(array(), peppol_precheck(self::model('creditnote')));
    }

    public function testModelBuiltFromCompleteRowsPasses(): void
    {
        $rows = array(
            'order' => array(
                'id' => '501', 'art' => 'DO', 'konto_id' => '77', 'kontonr' => '1077', 'firmanavn' => 'Testkunde ApS',
                'addr1' => 'Kundevej 2', 'postnr' => '5678', 'bynavn' => 'Kundeby', 'land' => 'Danmark', 'cvrnr' => '22222222',
                'ean' => self::gln('000000001234'), 'kundeordnr' => 'PO-4711', 'ordrenr' => '9001', 'fakturanr' => '1001',
                'fakturadate' => '2026-11-09', 'betalingsbet' => 'Netto', 'betalingsdage' => '8', 'valuta' => 'DKK',
                'momssats' => '25', 'sum' => '100', 'moms' => '25',
            ),
            'lines' => array(array('posnr' => '1', 'varenr' => 'VARE-1', 'vare_id' => '10', 'beskrivelse' => 'Eksempelvare',
                'antal' => '1', 'pris' => '100', 'rabat' => '0', 'procent' => '100', 'momssats' => '25', 'bogf_konto' => '1000')),
            'seller' => array('firmanavn' => 'Eksempel Sælger ApS', 'addr1' => 'Eksempelvej 1', 'postnr' => '1234', 'bynavn' => 'Eksempelby',
                'land' => 'Danmark', 'cvrnr' => '11111111', 'bank_reg' => '1234', 'bank_konto' => '0012345678'),
        );
        $model = peppol_document_model($rows);
        self::assertSame(array(), peppol_precheck($model));
    }

    public function testNotInvoicedYet(): void
    {
        $model = self::model();
        $model['id'] = '';
        self::assertSame(array('Ordren er ikke faktureret endnu: der mangler fakturanummer eller fakturadato.'), peppol_precheck($model));
    }

    public function testOwnCompanyDataFromStamdata(): void
    {
        $model = self::model();
        $model['seller']['registrationName'] = '';
        $model['seller']['legalId'] = null;
        $model['seller']['address']['street'] = null;
        self::assertSame(array(
            'Firmanavnet mangler under Indstillinger > Stamdata.',
            'Firmaets CVR-nummer mangler eller har ikke 8 cifre. Ret det under Indstillinger > Stamdata.',
            'Firmaets adresse (vej, postnr. og by) skal udfyldes under Indstillinger > Stamdata.',
        ), peppol_precheck($model));
    }

    public function testForeignSellerNeedsNoDanishCvr(): void
    {
        // DK-R-002 is only for a Danish seller; a Norwegian one is reached on its own endpoint
        $model = self::model();
        $model['seller']['legalId'] = null;
        $model['seller']['endpoint'] = array('scheme' => '0192', 'id' => '000000019');
        $model['seller']['address']['country'] = 'NO';
        self::assertSame(array(), peppol_precheck($model));

        $model['seller']['endpoint'] = array('scheme' => '', 'id' => '');
        self::assertContains('Firmaets CVR-nummer mangler eller har ikke 8 cifre. Ret det under Indstillinger > Stamdata.', peppol_precheck($model));
    }

    public function testBuyerWithoutAnyIdentifier(): void
    {
        $model = self::model();
        $model['buyer']['endpoint'] = array('scheme' => '', 'id' => '');
        self::assertSame(array('Kunden mangler både CVR-nummer og EAN-nummer. Mindst ét af dem skal stå på ordren eller debitorkortet.'), peppol_precheck($model));
    }

    public function testBuyerEndpointFormats(): void
    {
        $model = self::model();
        $model['buyer']['endpoint'] = array('scheme' => '1234', 'id' => '5678');
        self::assertSame(array('EAN-feltet indeholder et ukendt Peppol-skema (1234). Skriv EAN-nummeret alene, eller fx 0088:<EAN> eller 0184:<CVR>.'), peppol_precheck($model));

        $model['buyer']['endpoint'] = array('scheme' => '0088', 'id' => '0000000012340');
        self::assertSame(array('EAN-nummeret 0000000012340 er ikke gyldigt. Et EAN-nummer har 13 cifre, og det sidste er et kontrolciffer.'), peppol_precheck($model));

        $model['buyer']['endpoint'] = array('scheme' => '0184', 'id' => '1234567');
        self::assertSame(array('Kundens CVR-nummer 1234567 er ikke et gyldigt dansk CVR-nummer med 8 cifre.'), peppol_precheck($model));

        $model['buyer']['endpoint'] = array('scheme' => '0192', 'id' => '12345678');
        self::assertSame(array('Kundens organisationsnummer 12345678 er ikke gyldigt (forkert antal cifre eller kontrolciffer). Er det et dansk CVR-nummer, så skriv DK foran det i CVR-feltet.'), peppol_precheck($model));

        // PEPPOL-COMMON-R041/R049: the check digit too
        $model['buyer']['endpoint'] = array('scheme' => '0192', 'id' => '000000001');
        self::assertSame(array('Kundens organisationsnummer 000000001 er ikke gyldigt (forkert antal cifre eller kontrolciffer). Er det et dansk CVR-nummer, så skriv DK foran det i CVR-feltet.'), peppol_precheck($model));
        $model['buyer']['endpoint'] = array('scheme' => '0007', 'id' => '0000000001');
        self::assertCount(1, peppol_precheck($model));

        $model['buyer']['endpoint'] = array('scheme' => '0007', 'id' => '0000000018');
        self::assertSame(array(), peppol_precheck($model));
    }

    public function testEveryEndpointSchemeWithAFatalFormatRule(): void
    {
        $model = self::model();
        // PEPPOL-COMMON-R052: a P-number has 10 digits
        $model['buyer']['endpoint'] = array('scheme' => '0096', 'id' => '000000001');
        self::assertSame(array('P-nummeret 000000001 i EAN-feltet er ikke gyldigt. Et P-nummer har 10 cifre.'), peppol_precheck($model));
        $model['buyer']['endpoint'] = array('scheme' => '0096', 'id' => '0000000001');
        self::assertSame(array(), peppol_precheck($model));

        // PEPPOL-COMMON-R053: an SE number is DK + 8 digits
        $model['buyer']['endpoint'] = array('scheme' => '0198', 'id' => 'DK0000001');
        self::assertSame(array('SE-nummeret DK0000001 i EAN-feltet er ikke gyldigt. Et SE-nummer har 8 cifre.'), peppol_precheck($model));
        $model['buyer']['endpoint'] = array('scheme' => '0198', 'id' => 'DK00000001');
        self::assertSame(array(), peppol_precheck($model));

        // PEPPOL-COMMON-R043 (0208, mod 97) and R050 (0151, mod 89)
        $model['buyer']['endpoint'] = array('scheme' => '0208', 'id' => '0000000195');
        self::assertSame(array('Kundens elektroniske adresse 0208:0000000195 har ikke det format, Peppol kræver for skemaet 0208 (forkert antal cifre eller kontrolciffer). Ret EAN-feltet på ordren eller debitorkortet.'), peppol_precheck($model));
        $model['buyer']['endpoint'] = array('scheme' => '0208', 'id' => '0000000196');
        self::assertSame(array(), peppol_precheck($model));
        $model['buyer']['endpoint'] = array('scheme' => '0151', 'id' => '10000000031');
        self::assertCount(1, peppol_precheck($model));
        $model['buyer']['endpoint'] = array('scheme' => '0151', 'id' => '10000000032');
        self::assertSame(array(), peppol_precheck($model));

        // A CVR endpoint typed with DK is valid (PEPPOL-COMMON-R042)
        $model['buyer']['endpoint'] = array('scheme' => '0184', 'id' => 'DK22222222');
        self::assertSame(array(), peppol_precheck($model));
    }

    public function testEuSaleWithoutVatNeedsTheBuyersOwnVatNumber(): void
    {
        $model = self::model();
        $model['lines'][0]['vatCategory'] = 'K';
        $model['buyer']['address']['country'] = 'SE';
        $model['buyer']['vatId'] = 'DK22222222';
        self::assertSame(array('Kundens momsnummer DK22222222 er fra samme land som firmaet, men fakturaen er uden moms som salg til et andet EU-land. Skriv kundens eget momsnummer med landekode i CVR-feltet (fx SE556677889901), eller kontrollér kundens land og debitorgruppe.'), peppol_precheck($model));

        $model['buyer']['vatId'] = 'SE000000001801';
        self::assertSame(array(), peppol_precheck($model));

        // Reverse charge for a service abroad needs the foreign number too; domestic reverse charge carries a Danish one
        $model['lines'][0]['vatCategory'] = 'AE';
        $model['buyer']['vatId'] = 'DK22222222';
        self::assertCount(1, peppol_precheck($model));
        $model['buyer']['address']['country'] = 'DK';
        self::assertSame(array(), peppol_precheck($model));
    }

    public function testLineThatBreaksR120StopsTheSend(): void
    {
        $model = self::model();
        $model['reconciliation'] = array('bookedNet' => 300.0, 'bookedVat' => 75.0, 'computedNet' => 300.0, 'computedVat' => 75.0,
            'vatAdjustment' => 0.0, 'roundingAmount' => 0.0, 'ok' => true, 'maxLineSlack' => 0.03);
        self::assertSame(array('En linje på fakturaen kan ikke angives præcist nok som antal gange pris i e-fakturaen (afvigelse over 0,02). Fakturaen er ikke sendt. Kontakt saldi.dk support.'), peppol_precheck($model));

        $model['reconciliation']['maxLineSlack'] = 0.02;
        self::assertSame(array(), peppol_precheck($model));
        unset($model['reconciliation']['maxLineSlack']);
        self::assertSame(array(), peppol_precheck($model), 'a model without the key keeps the old behaviour');
    }

    public function testDanishGlnReceiverNeedsCvr(): void
    {
        // SD-774 / DK-R-017: the same rule as the EasyUBL send block
        $gln = self::gln('000000001234');
        $model = self::model();
        $model['buyer']['endpoint'] = array('scheme' => '0088', 'id' => $gln);
        self::assertSame(array(), peppol_precheck($model));

        $model['buyer']['legalId'] = null;
        $messages = peppol_precheck($model);
        self::assertCount(1, $messages);
        self::assertStringStartsWith("Modtageren har EAN-nummer $gln, men der er ikke angivet noget gyldigt CVR-nummer", $messages[0]);

        // A foreign receiver on a GLN is not covered by the Danish rule
        $model['buyer']['address']['country'] = 'SE';
        $model['buyer']['vatId'] = null;
        self::assertSame(array(), peppol_precheck($model));
    }

    public function testBuyerAddressParts(): void
    {
        $model = self::model();
        $model['buyer']['address']['street'] = null;
        $model['buyer']['address']['postalCode'] = null;
        self::assertSame(array('Kundens adresse mangler vej og postnummer. Udfyld den på ordren eller debitorkortet.'), peppol_precheck($model));

        $model['buyer']['address']['city'] = null;
        self::assertSame(array('Kundens adresse mangler vej, postnummer og by. Udfyld den på ordren eller debitorkortet.'), peppol_precheck($model));

        $model = self::model();
        $model['buyer']['address']['city'] = null;
        $model['buyer']['registrationName'] = '';
        self::assertSame(array('Kundens navn mangler på ordren.', 'Kundens adresse mangler by. Udfyld den på ordren eller debitorkortet.'), peppol_precheck($model));
    }

    public function testNoLines(): void
    {
        $model = self::model();
        $model['lines'] = array();
        self::assertSame(array('Ordren har ingen varelinjer at sende.'), peppol_precheck($model));
    }

    public function testIntraCommunitySupplyNeedsTheBuyersVatNumber(): void
    {
        $model = self::model();
        $model['lines'][0]['vatCategory'] = 'K';
        $model['buyer']['vatId'] = null;
        self::assertSame(array('Kunden skal have sit momsnummer (VAT-nummer med landekode) i CVR-feltet, når der sælges uden moms til et andet EU-land.'), peppol_precheck($model));
    }

    public function testReverseChargeNeedsAnyBuyerId(): void
    {
        $model = self::model();
        $model['lines'][0]['vatCategory'] = 'AE';
        $model['buyer']['vatId'] = null;
        self::assertSame(array(), peppol_precheck($model), 'the legal id is enough (BR-AE-02)');

        $model['buyer']['legalId'] = null;
        self::assertSame(array('Ved omvendt betalingspligt skal kundens CVR- eller momsnummer stå på ordren eller debitorkortet.'), peppol_precheck($model));
    }

    public function testInvoiceWithoutPaymentDetails(): void
    {
        $model = self::model();
        $model['paymentMeans'] = array();
        self::assertSame(array('Der er ingen betalingsoplysninger. Udfyld reg.nr. og kontonr. eller IBAN under Indstillinger > Stamdata.'), peppol_precheck($model));

        // Settled at posting: nothing to pay, so no bank details are needed
        $model['totals']['prepaid'] = $model['totals']['payable'];
        $model['totals']['payable'] = 0.0;
        self::assertSame(array(), peppol_precheck($model));
    }

    public function testNegativeCreditNoteToADanishReceiver(): void
    {
        // DK-R-016
        $model = self::model('creditnote');
        $model['totals']['payable'] = -375.0;
        self::assertSame(array('Kreditnotaen giver i alt -375,00 og kan ikke sendes digitalt til en dansk modtager med et negativt beløb. Send den som en faktura i stedet.'), peppol_precheck($model));

        $model['buyer']['address']['country'] = 'SE';
        $model['buyer']['endpoint'] = array('scheme' => '0007', 'id' => '0000000018');
        self::assertSame(array(), peppol_precheck($model));
    }

    public function testInvalidCurrency(): void
    {
        $model = self::model();
        $model['currency'] = 'kr';
        self::assertSame(array('Ordrens valuta (kr) er ikke en gyldig valutakode.'), peppol_precheck($model));
    }

    public function testTotalsThatDoNotMatchTheBookingStopTheSend(): void
    {
        $model = self::model();
        $model['reconciliation'] = array('bookedNet' => 310.0, 'bookedVat' => 77.5, 'computedNet' => 300.0, 'computedVat' => 75.0,
            'vatAdjustment' => 0.0, 'roundingAmount' => 0.0, 'ok' => false);
        self::assertSame(array('Fakturaens linjer giver ikke det bogførte beløb (forskel 12,50). Fakturaen er ikke sendt. Kontakt saldi.dk support.'), peppol_precheck($model));

        $model['reconciliation']['ok'] = true;
        self::assertSame(array(), peppol_precheck($model));
    }

    public function testEveryMessageOnlyOnce(): void
    {
        $model = self::model();
        $model['lines'][] = $model['lines'][0];
        $model['lines'][0]['vatCategory'] = 'K';
        $model['lines'][1]['vatCategory'] = 'K';
        $model['buyer']['vatId'] = null;
        self::assertCount(1, peppol_precheck($model));
    }
}
