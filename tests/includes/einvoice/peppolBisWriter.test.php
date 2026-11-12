<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/einvoice/peppolBisWriter.test.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261109 CL/MMR SD-815 Created (draft). Golden-file tests for the Peppol BIS Billing 3.0 writer, plus its model check and formatting.
//                 Regenerate the golden XML with PEPPOL_BIS_REGEN=1; run the Peppol validator over it with PEPPOL_BIS_VALIDATOR set.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/einvoice/peppolModel.php';
require_once __DIR__ . '/../../../includes/einvoice/peppolBisWriter.php';

/**
 * peppol_bis_xml() and peppol_bis_validate_model() from includes/einvoice/peppolBisWriter.php (SD-815).
 *
 * Each scenario is a synthetic document model in tests/fixtures/einvoice/<name>.json and the writer's output for it in
 * <name>.xml. Every golden file passed the UBL 2.1 XSD and the Peppol BIS Billing 3.0.22 schematron (CEN EN16931 and
 * PEPPOL rules, including the Danish DK-R rules) with no fatal error when it was made.
 *
 * Environment:
 * - PEPPOL_BIS_REGEN=1 writes the writer's output over the golden files instead of comparing (check the diff and
 *   validate them before committing).
 * - PEPPOL_BIS_VALIDATOR, the command that validates XML files and exits 0 when no file has a fatal error, for example
 *   "<python> -I <path>/validate.py" (UBL XSD + Peppol schematron). Without it the validator test is skipped.
 */
final class peppolBisWriter extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures/einvoice';

    /**
     * The scenarios and what each one covers. Every JSON fixture must be listed here.
     */
    private const SCENARIOS = [
        '01-example-invoice'                => "peppol_model_example('invoice'): DK to DK, 25 % S, IBAN payment (31).",
        '02-example-creditnote'             => "peppol_model_example('creditnote'): BillingReference, no DueDate, no PaymentMeans.",
        '03-mixed-s-z'                      => 'A 25 % S line and a 0 % Z line, two VAT breakdowns.',
        '04-reverse-charge-ae-se'           => 'Reverse charge AE to a Swedish company (0007), VATEX-EU-AE, SEPA payment (58).',
        '05-export-g-no'                    => 'Export outside the EU, category G, VATEX-EU-G, Norwegian buyer (0192).',
        '06-exempt-e'                       => 'Exempt E with an exemption reason text and no code.',
        '07-discount-line-invoice'          => 'A discount line as a negative quantity at a positive price (SST-746) on an invoice.',
        '08-creditnote-return-discount'     => 'A credit note with a returned item and a discount line (negative quantity).',
        '09-foreign-currency-eur'           => 'EUR invoice with VAT accounting currency DKK and the VAT total in DKK (second TaxTotal).',
        '10-public-gln-0088'                => 'Public buyer on a GLN endpoint (0088) with CVR legal ID (0184), order reference, delivery, Danish account (42).',
        '11-fik-93'                         => 'FIK payment (93): PaymentID 71# + 15 digits, FI creditor number as the 8-character account.',
        '12-line-allowance-price-discount'  => 'Line allowance with percent and base, gross price with price discount, base quantity; a line charge.',
        '13-document-allowance-charge'      => 'Document level allowance (percent of a base) and charge, allowance and charge totals.',
        '14-payable-rounding'               => 'PayableRoundingAmount and PrepaidAmount.',
        '15-text-escaping'                  => 'Text with æøå, &, <, >, quotes, a tab, CRLF and control characters that must be dropped.',
        '16-header-attachment-delivery'     => 'Optional header data: attachment, trading name, identifiers, contacts, accounting cost, line references.',
        '17-not-vat-registered-o'           => 'Seller not VAT registered: category O without a rate and without VAT IDs.',
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function scenarios(): array
    {
        $cases = [];
        foreach (array_keys(self::SCENARIOS) as $name) {
            $cases[$name] = [$name];
        }
        return $cases;
    }

    /**
     * The model of a scenario, read from its JSON fixture.
     *
     * @param string $name The scenario name.
     * @return array<string, mixed> The document model.
     */
    private static function model(string $name): array
    {
        $json = file_get_contents(self::FIXTURES . "/$name.json");
        self::assertIsString($json, "fixture $name.json is missing");
        $model = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($model);
        return $model;
    }

    /**
     * The writer's output as a DOM document with an XPath that knows the UBL prefixes.
     *
     * @param string $xml The XML.
     * @return DOMXPath XPath with the prefixes ubl (the root namespace), cac and cbc.
     */
    private static function xpath(string $xml): DOMXPath
    {
        $doc = new DOMDocument();
        self::assertTrue($doc->loadXML($xml), 'the XML is not well-formed');
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ubl', $doc->documentElement->namespaceURI);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        return $xpath;
    }

    /**
     * Sets or (with $unset) removes a value at a path in a model.
     *
     * @param array<string, mixed> $model The model, changed in place.
     * @param list<string|int>     $path  Keys from the top.
     * @param mixed                $value The new value.
     * @param bool                 $unset True to remove the key instead.
     * @return void
     */
    private static function change(array &$model, array $path, $value, bool $unset = false): void
    {
        $last = array_pop($path);
        $node = &$model;
        foreach ($path as $key) {
            $node = &$node[$key];
        }
        if ($unset) {
            unset($node[$last]);
        } else {
            $node[$last] = $value;
        }
    }

    public function testEveryFixtureIsADescribedScenario(): void
    {
        $files = array_map(static fn (string $path): string => basename($path, '.json'), glob(self::FIXTURES . '/*.json'));
        sort($files);
        $this->assertSame(array_keys(self::SCENARIOS), $files);
        foreach (array_keys(self::SCENARIOS) as $name) {
            $this->assertFileExists(self::FIXTURES . "/$name.xml", "golden file for $name");
        }
    }

    public function testExampleFixturesAreThePeppolModelExample(): void
    {
        $this->assertSame(peppol_model_example('invoice'), self::model('01-example-invoice'));
        $this->assertSame(peppol_model_example('creditnote'), self::model('02-example-creditnote'));
    }

    #[DataProvider('scenarios')]
    public function testWriterMatchesTheGoldenFile(string $name): void
    {
        $model = self::model($name);
        $this->assertSame([], peppol_bis_validate_model($model));
        $xml = peppol_bis_xml($model);
        $golden = self::FIXTURES . "/$name.xml";
        if (getenv('PEPPOL_BIS_REGEN') === '1') {
            file_put_contents($golden, $xml);
        }
        $this->assertSame(file_get_contents($golden), $xml);
    }

    #[DataProvider('scenarios')]
    public function testDocumentStructure(string $name): void
    {
        $model = self::model($name);
        $xpath = self::xpath(peppol_bis_xml($model));
        $root = $xpath->document->documentElement;
        $isCredit = ($model['documentType'] === 'creditnote');

        $this->assertSame($isCredit ? 'CreditNote' : 'Invoice', $root->localName);
        $this->assertSame('urn:oasis:names:specification:ubl:schema:xsd:' . ($isCredit ? 'CreditNote-2' : 'Invoice-2'), $root->namespaceURI);
        $this->assertSame('urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0', $xpath->evaluate('string(/ubl:*/cbc:CustomizationID)'));
        $this->assertSame('urn:fdc:peppol.eu:2017:poacc:billing:01:1.0', $xpath->evaluate('string(/ubl:*/cbc:ProfileID)'));
        $lines = count($model['lines']);
        if ($isCredit) {
            $this->assertSame(0, $xpath->query('/ubl:*/cbc:DueDate | //cac:InvoiceLine | //cbc:InvoicedQuantity | /ubl:*/cbc:InvoiceTypeCode')->length);
            $this->assertSame($lines, $xpath->query('/ubl:*/cac:CreditNoteLine/cbc:CreditedQuantity')->length);
            $this->assertSame($model['typeCode'], $xpath->evaluate('string(/ubl:*/cbc:CreditNoteTypeCode)'));
        } else {
            $this->assertSame(0, $xpath->query('//cac:CreditNoteLine | //cbc:CreditedQuantity | /ubl:*/cbc:CreditNoteTypeCode')->length);
            $this->assertSame($lines, $xpath->query('/ubl:*/cac:InvoiceLine/cbc:InvoicedQuantity')->length);
            $this->assertSame($model['typeCode'], $xpath->evaluate('string(/ubl:*/cbc:InvoiceTypeCode)'));
        }

        // currencyID on every amount, the document currency everywhere except the VAT total in the VAT accounting currency.
        $amounts = $xpath->query("//*[substring(local-name(), string-length(local-name()) - 5) = 'Amount']");
        $this->assertGreaterThan(0, $amounts->length);
        $taxCurrencyAmounts = 0;
        foreach ($amounts as $amount) {
            $this->assertTrue($amount->hasAttribute('currencyID'), $amount->getNodePath() . ' has no currencyID');
            $this->assertMatchesRegularExpression('/^-?\d+\.\d{2,4}$/', $amount->textContent, $amount->getNodePath());
            $this->assertDoesNotMatchRegularExpression('/^-0(\.0+)?$/', $amount->textContent, $amount->getNodePath());
            if ($amount->getAttribute('currencyID') !== $model['currency']) {
                $taxCurrencyAmounts++;
                $this->assertSame($model['taxCurrency'], $amount->getAttribute('currencyID'));
                $this->assertSame('TaxTotal', $amount->parentNode->localName);
            }
        }
        $this->assertSame(empty($model['taxCurrency']) ? 0 : 1, $taxCurrencyAmounts);
        // Amounts outside Price have exactly 2 decimals (UBL-DT-01, BR-DEC-*).
        foreach ($xpath->query("//*[substring(local-name(), string-length(local-name()) - 5) = 'Amount' and local-name() != 'PriceAmount' and not(ancestor::cac:Price)]") as $amount) {
            $this->assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', $amount->textContent, $amount->getNodePath());
        }

        // PEPPOL-EN16931-R008: no empty elements.
        $this->assertSame(0, $xpath->query('//*[not(*) and not(normalize-space())]')->length);
        // Only characters XML 1.0 allows, and no CR (line breaks are LF).
        $this->assertDoesNotMatchRegularExpression('/[\x00-\x08\x0B\x0C\x0E-\x1F\r]/', $xpath->document->saveXML());
    }

    public function testValidatorAcceptsEveryGoldenFile(): void
    {
        $command = getenv('PEPPOL_BIS_VALIDATOR');
        if (!is_string($command) || trim($command) === '') {
            $this->markTestSkipped('PEPPOL_BIS_VALIDATOR is not set (the command that runs the UBL XSD and Peppol schematron validator).');
        }
        $files = array_map(static fn (string $name): string => escapeshellarg(self::FIXTURES . "/$name.xml"), array_keys(self::SCENARIOS));
        $parts = array_map('escapeshellarg', preg_split('/\s+/', trim($command)));
        exec(implode(' ', $parts) . ' ' . implode(' ', $files) . ' 2>&1', $output, $exitCode);
        $this->assertSame(0, $exitCode, "validator reported fatal errors:\n" . implode("\n", $output));
    }

    public function testCompleteExamplesHaveNoModelErrors(): void
    {
        $this->assertSame([], peppol_bis_validate_model(peppol_model_example('invoice')));
        $this->assertSame([], peppol_bis_validate_model(peppol_model_example('creditnote')));
    }

    public function testOptionalKeysMayBeLeftOut(): void
    {
        $model = peppol_model_example('invoice');
        $drop = static function (array $values): array {
            return array_filter($values, static fn ($value): bool => $value !== null && $value !== []);
        };
        $model = $drop($model);
        $model['seller'] = $drop($model['seller']);
        $model['seller']['contact'] = $drop($model['seller']['contact']);
        $model['buyer'] = $drop($model['buyer']);
        $model['delivery'] = $drop($model['delivery']);
        $model['totals'] = $drop($model['totals']);
        $model['taxSubtotals'][0] = $drop($model['taxSubtotals'][0]);
        $model['lines'][0] = $drop($model['lines'][0]);
        $this->assertArrayNotHasKey('taxCurrency', $model);
        $this->assertArrayNotHasKey('allowanceCharges', $model);
        $this->assertSame([], peppol_bis_validate_model($model));
        $this->assertSame(file_get_contents(self::FIXTURES . '/01-example-invoice.xml'), peppol_bis_xml($model));
    }

    public function testCreditNoteDueDateGoesInTheFirstPaymentMeans(): void
    {
        $model = peppol_model_example('creditnote');
        $model['dueDate'] = '2026-11-30';
        $model['paymentMeans'] = [
            ['code' => '31', 'paymentId' => '1002', 'payeeAccount' => ['id' => 'DK5000400440116243', 'name' => null, 'bic' => 'DABADKKK']],
            ['code' => '93', 'paymentId' => '71#000000000010022', 'payeeAccount' => ['id' => '12345678', 'name' => null, 'bic' => null]],
        ];
        $xpath = self::xpath(peppol_bis_xml($model));
        $this->assertSame(0, $xpath->query('/ubl:CreditNote/cbc:DueDate')->length);
        $this->assertSame(1, $xpath->query('//cbc:PaymentDueDate')->length);
        $this->assertSame('2026-11-30', $xpath->evaluate('string(/ubl:CreditNote/cac:PaymentMeans[1]/cbc:PaymentDueDate)'));
        // UBL order: PaymentMeansCode, PaymentDueDate, PaymentID.
        $this->assertSame('PaymentDueDate', $xpath->query('/ubl:CreditNote/cac:PaymentMeans[1]/cbc:PaymentMeansCode/following-sibling::*[1]')->item(0)->localName);
    }

    public function testTradingNameEqualToTheRegistrationNameIsLeftOut(): void
    {
        $model = peppol_model_example('invoice');
        $model['seller']['name'] = $model['seller']['registrationName'];
        $model['buyer']['name'] = 'Kundens Butik';
        $xpath = self::xpath(peppol_bis_xml($model));
        $this->assertSame(0, $xpath->query('//cac:AccountingSupplierParty//cac:PartyName')->length);
        $this->assertSame('Kundens Butik', $xpath->evaluate('string(//cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name)'));
    }

    public function testSalesOrderIdIsWrittenOnlyWithAnOrderReference(): void
    {
        $model = peppol_model_example('invoice');
        $model['salesOrderId'] = '512';
        $this->assertSame(0, self::xpath(peppol_bis_xml($model))->query('//cac:OrderReference')->length);
        $model['orderReference'] = 'PO-1';
        $xpath = self::xpath(peppol_bis_xml($model));
        $this->assertSame('PO-1', $xpath->evaluate('string(/ubl:Invoice/cac:OrderReference/cbc:ID)'));
        $this->assertSame('512', $xpath->evaluate('string(/ubl:Invoice/cac:OrderReference/cbc:SalesOrderID)'));
    }

    public function testGrossPriceWithoutPriceDiscountWritesAZeroDiscount(): void
    {
        $model = peppol_model_example('invoice');
        $model['lines'][0]['grossPrice'] = 150.00;
        $xpath = self::xpath(peppol_bis_xml($model));
        $this->assertSame('0.00', $xpath->evaluate('string(//cac:InvoiceLine/cac:Price/cac:AllowanceCharge/cbc:Amount)'));
        $this->assertSame('150.00', $xpath->evaluate('string(//cac:InvoiceLine/cac:Price/cac:AllowanceCharge/cbc:BaseAmount)'));
    }

    public function testLatinTextIsReadAsWindows1252(): void
    {
        $model = peppol_model_example('invoice');
        $model['lines'][0]['itemName'] = "R\xF8dgr\xF8d med fl\xF8de";
        $xpath = self::xpath(peppol_bis_xml($model));
        $this->assertSame('Rødgrød med fløde', $xpath->evaluate('string(//cac:InvoiceLine/cac:Item/cbc:Name)'));
    }

    /**
     * @return array<string, array{int|float|string, int, int, string}>
     */
    public static function decimals(): array
    {
        return [
            'amount'                    => [375, 2, 2, '375.00'],
            'amount rounds half up'     => [0.125, 2, 2, '0.13'],
            'float noise'               => [0.1 + 0.2, 2, 2, '0.30'],
            'negative amount'           => [-50.0, 2, 2, '-50.00'],
            'negative zero'             => [-0.0, 2, 2, '0.00'],
            'rounds to negative zero'   => [-0.004, 2, 2, '0.00'],
            'large amount'              => [1234567.891, 2, 2, '1234567.89'],
            'numeric string'            => ['1234.5', 2, 2, '1234.50'],
            'price 2 decimals'          => [150, 2, 4, '150.00'],
            'price 3 decimals'          => [12.345, 2, 4, '12.345'],
            'price 4 decimals'          => [12.3456, 2, 4, '12.3456'],
            'price rounded to 4'        => [0.123456, 2, 4, '0.1235'],
            'price trailing zero'       => [12.3, 2, 4, '12.30'],
            'quantity whole'            => [2.0, 0, 4, '2'],
            'quantity negative'         => [-1, 0, 4, '-1'],
            'quantity fraction'         => [1.5, 0, 4, '1.5'],
            'quantity negative zero'    => [-0.00001, 0, 4, '0'],
        ];
    }

    #[DataProvider('decimals')]
    public function testDecimalFormatting($value, int $min, int $max, string $expected): void
    {
        $this->assertSame($expected, peppol_bis_decimal($value, $min, $max));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function texts(): array
    {
        return [
            'null'                   => [null, ''],
            'array'                  => [[1], ''],
            'blank'                  => ["  \n\t ", ''],
            'integer'                => [1001, '1001'],
            'zero'                   => [0, '0'],
            'markup kept as text'    => ['A & B <c>', 'A & B <c>'],
            'danish letters'         => ['Æble æøå ÆØÅ', 'Æble æøå ÆØÅ'],
            'control characters'     => ["A\x00B\x07C\x1FD\x7FE", 'ABCDE'],
            'c1 controls'            => ["A\u{0085}B\u{009F}C\u{00A0}D", "ABC\u{00A0}D"],
            'tab and line feed kept' => ["A\tB\nC", "A\tB\nC"],
            'crlf and cr to lf'      => ["A\r\nB\rC", "A\nB\nC"],
            'non-characters dropped' => ["A\u{FFFE}B\u{FFFF}C", 'ABC'],
            'astral plane kept'      => ["A\u{1F600}B", "A\u{1F600}B"],
            'windows-1252'           => ["K\xF8benhavn \x80 5", 'København € 5'],
            'trimmed'                => ['  Netto 14 dage  ', 'Netto 14 dage'],
        ];
    }

    #[DataProvider('texts')]
    public function testTextCleaning($value, string $expected): void
    {
        $this->assertSame($expected, peppol_bis_text($value));
    }

    /**
     * Each case changes one thing in a complete model and names the message peppol_bis_validate_model() must give.
     *
     * @return array<string, array{string, list<string|int>, mixed, bool, string}>
     */
    public static function brokenModels(): array
    {
        return [
            'no document type'          => ['invoice', ['documentType'], null, true, 'documentType is missing'],
            'unknown document type'     => ['invoice', ['documentType'], 'order', false, "documentType must be 'invoice' or 'creditnote'"],
            'no id'                     => ['invoice', ['id'], null, true, 'id is missing'],
            'blank issue date'          => ['invoice', ['issueDate'], ' ', false, 'issueDate is missing'],
            'no type code'              => ['creditnote', ['typeCode'], null, false, 'typeCode is missing'],
            'no currency'               => ['invoice', ['currency'], '', false, 'currency is missing'],
            'tax currency, no amount'   => ['invoice', ['taxCurrency'], 'EUR', false, 'taxCurrencyTaxAmount is missing (required when taxCurrency is set)'],
            'billing reference no id'   => ['creditnote', ['billingReferences', 0, 'id'], null, true, 'billingReferences[0].id is missing'],
            'billing references string' => ['creditnote', ['billingReferences'], '1001', false, 'billingReferences must be a list'],
            'no seller'                 => ['invoice', ['seller'], null, true, 'seller is missing'],
            'no buyer endpoint id'      => ['invoice', ['buyer', 'endpoint', 'id'], null, true, 'buyer.endpoint.id is missing'],
            'no buyer endpoint scheme'  => ['invoice', ['buyer', 'endpoint', 'scheme'], '', false, 'buyer.endpoint.scheme is missing'],
            'blank seller name'         => ['invoice', ['seller', 'registrationName'], "  \x07 ", false, 'seller.registrationName is missing'],
            'no buyer address'          => ['invoice', ['buyer', 'address'], null, false, 'buyer.address is missing'],
            'no seller country'         => ['invoice', ['seller', 'address', 'country'], null, false, 'seller.address.country is missing'],
            'identifier without id'     => ['invoice', ['buyer', 'identifiers'], [['scheme' => '0088', 'id' => '']], false, 'buyer.identifiers[0].id is missing'],
            'legal id without id'       => ['invoice', ['seller', 'legalId'], ['scheme' => '0184', 'id' => null], false, 'seller.legalId.id is missing'],
            'delivery without country'  => ['invoice', ['delivery', 'address'], ['street' => 'Vej 1', 'city' => 'By', 'country' => null], false, 'delivery.address.country is missing'],
            'payment means no code'     => ['invoice', ['paymentMeans', 0, 'code'], null, true, 'paymentMeans[0].code is missing'],
            'payee account no id'       => ['invoice', ['paymentMeans', 0, 'payeeAccount', 'id'], '', false, 'paymentMeans[0].payeeAccount.id is missing'],
            'allowance not a bool'      => ['invoice', ['allowanceCharges'], [['isCharge' => 'false', 'amount' => 5.0, 'vatCategory' => 'S']], false, 'allowanceCharges[0].isCharge is missing (must be a bool)'],
            'allowance no amount'       => ['invoice', ['allowanceCharges'], [['isCharge' => false, 'vatCategory' => 'S']], false, 'allowanceCharges[0].amount is missing'],
            'allowance no category'     => ['invoice', ['allowanceCharges'], [['isCharge' => true, 'amount' => 5.0]], false, 'allowanceCharges[0].vatCategory is missing'],
            'no tax subtotals'          => ['invoice', ['taxSubtotals'], [], false, 'taxSubtotals is missing (at least one VAT breakdown)'],
            'subtotal no category'      => ['invoice', ['taxSubtotals', 0, 'category'], null, true, 'taxSubtotals[0].category is missing'],
            'subtotal amount not number'=> ['invoice', ['taxSubtotals', 0, 'taxAmount'], 'abc', false, 'taxSubtotals[0].taxAmount is missing'],
            'no tax total'              => ['invoice', ['taxTotal'], null, false, 'taxTotal is missing'],
            'no totals'                 => ['invoice', ['totals'], null, true, 'totals is missing'],
            'no payable amount'         => ['invoice', ['totals', 'payable'], null, true, 'totals.payable is missing'],
            'no lines'                  => ['invoice', ['lines'], null, true, 'lines is missing (at least one line)'],
            'empty lines'               => ['creditnote', ['lines'], [], false, 'lines is missing (at least one line)'],
            'line no item name'         => ['invoice', ['lines', 0, 'itemName'], null, true, 'lines[0].itemName is missing'],
            'line no unit code'         => ['invoice', ['lines', 0, 'unitCode'], '', false, 'lines[0].unitCode is missing'],
            'line no quantity'          => ['invoice', ['lines', 0, 'quantity'], null, false, 'lines[0].quantity is missing'],
            'line price not a number'   => ['invoice', ['lines', 0, 'netPrice'], '150,00', false, 'lines[0].netPrice is missing'],
            'line no vat category'      => ['invoice', ['lines', 0, 'vatCategory'], null, true, 'lines[0].vatCategory is missing'],
            'line allowance no amount'  => ['invoice', ['lines', 0, 'allowanceCharges'], [['isCharge' => false, 'amount' => null]], false, 'lines[0].allowanceCharges[0].amount is missing'],
            'discount without gross'    => ['invoice', ['lines', 0, 'priceDiscount'], 5.0, false, 'lines[0].grossPrice is missing (required when priceDiscount is set)'],
            'standard id without scheme'=> ['invoice', ['lines', 0, 'standardItemId'], ['scheme' => null, 'id' => '5790000000012'], false, 'lines[0].standardItemId needs both scheme and id'],
            'optional number not number'=> ['invoice', ['lines', 0, 'baseQuantity'], 'two', false, 'lines[0].baseQuantity must be a number'],
            'rounding not a number'     => ['invoice', ['totals', 'roundingAmount'], '0,01', false, 'totals.roundingAmount must be a number'],
            'attachment without file'   => ['invoice', ['attachments'], [['id' => 'A1', 'mimeCode' => 'application/pdf', 'filename' => 'a.pdf']], false, 'attachments[0].base64 is missing'],
        ];
    }

    #[DataProvider('brokenModels')]
    public function testValidateModelReportsMissingRequiredValues(string $type, array $path, $value, bool $unset, string $expected): void
    {
        $model = peppol_model_example($type);
        self::change($model, $path, $value, $unset);
        $this->assertContains($expected, peppol_bis_validate_model($model));
    }

    #[DataProvider('brokenModels')]
    public function testWriterRefusesAnIncompleteModel(string $type, array $path, $value, bool $unset, string $expected): void
    {
        $model = peppol_model_example($type);
        self::change($model, $path, $value, $unset);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expected);
        peppol_bis_xml($model);
    }

    public function testValidateModelListsEveryProblem(): void
    {
        $errors = peppol_bis_validate_model([]);
        foreach (['documentType is missing', 'id is missing', 'seller is missing', 'buyer is missing', 'taxTotal is missing', 'totals is missing', 'lines is missing (at least one line)'] as $expected) {
            $this->assertContains($expected, $errors);
        }
    }
}
