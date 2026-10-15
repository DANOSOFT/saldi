<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261007 MJ SST-860 Pins what sendInvoice() puts in accountingCustomerParty.companyId.
//                  Brøndby Kommune rejected Den-Tec's faktura 12034 on Schematron rule
//                  DK-R-017: PartyLegalEntity/CompanyID must carry schemeID 0184 (CVR) for
//                  a Danish customer, and the sent XML had the GLN there under 0088.
//                  Den-Tec's municipal customers hold an EAN/GLN and no CVR, so the GLN
//                  branch in sendInvoice() left $cvrnr_with_prefix empty and the payload
//                  carried "companyId": "". An empty string is not a legal-entity
//                  identifier, so the key is now omitted instead.
//
//                  sendInvoice() is a ~400-line function in a page script that talks to a
//                  third-party API, so the two relevant blocks are lifted out of the source
//                  and evaluated here. Restating them in the test would let it pass while
//                  the real code is wrong.
//
//                  NOTE: do not exercise the "neither EAN nor CVR" case through the lifted
//                  resolution block. That branch emits a <script>alert() and calls exit,
//                  which would end the PHPUnit process. It is asserted on the source only.
final class PeppolCustomerCompanyIdTest extends TestCase
{
    private const API = __DIR__ . '/../../../debitor/api.php';

    private static ?string $src = null;

    private static function source(): string
    {
        if (self::$src === null) {
            $src = file_get_contents(self::API);
            self::assertNotFalse($src, 'could not read debitor/api.php');
            self::$src = $src;
        }
        return self::$src;
    }

    /**
     * Lifts a contiguous run of source between two anchors.
     *
     * Anchor on code, not on a comment: the ticket id appears in api.php's header
     * history block as well, so a comment anchor matches there first and lifts the
     * whole file.
     */
    private function lift(string $from, string $to): string
    {
        $src = self::source();
        $a = strpos($src, $from);
        self::assertNotFalse($a, "anchor is gone from api.php: $from");
        $b = strpos($src, $to, $a);
        self::assertNotFalse($b, "end anchor is gone from api.php: $to");
        return substr($src, $a, $b - $a);
    }

    /**
     * Runs the real endpoint/CVR resolution and the real companyId omission over one
     * customer's registration data, and reports the resulting payload fields.
     *
     * @param string $ean   The adresser.ean value.
     * @param string $cvrnr The adresser.cvrnr value.
     * @return array{endpointId: string, endpointIdType: string, countryCode: string, party: array<string, mixed>}
     */
    private function resolve(string $ean, string $cvrnr): array
    {
        self::assertTrue($ean !== '' || $cvrnr !== '',
            'the neither-EAN-nor-CVR case exits the process; assert it on the source instead');

        $resolve = $this->lift('$cvrnr_with_prefix = "";', '// 20260604 - Validate recipient address');
        $omit = $this->lift('if ($cvrnr_with_prefix === "" || $cvrnr_with_prefix === "DK") {', 'file_put_contents("../temp/$db/data.json"');

        $db = 'saldi_791';
        $id = 8490;
        $r_faktura = array('ean' => $ean, 'cvrnr' => $cvrnr, 'firmanavn' => 'Tandplejen i Brøndby');
        $endpointId = $endpointType = null;

        eval($resolve);

        // The payload as sendInvoice() builds it, reduced to the identity fields.
        $data = array('accountingCustomerParty' => array(
            'endpointId' => $endpointId,
            'endpointIdType' => $endpointType,
            'name' => $r_faktura['firmanavn'],
            'companyId' => $cvrnr_with_prefix,
        ));

        eval($omit);

        return array(
            'endpointId' => (string)$endpointId,
            'endpointIdType' => (string)$endpointType,
            'countryCode' => (string)$countryCode,
            'party' => $data['accountingCustomerParty'],
        );
    }

    /** The reported case: an EAN and no CVR must not send a companyId at all. */
    public function testAGlnOnlyCustomerSendsNoCompanyId(): void
    {
        $out = $this->resolve('5798009003553', '');

        self::assertArrayNotHasKey('companyId', $out['party'],
            'an empty companyId is still being sent, which is what DK-R-017 rejected');
        self::assertSame('5798009003553', $out['endpointId'], 'the GLN must still route the document');
        self::assertSame('GLN', $out['endpointIdType'], 'the endpoint type must still be GLN');
    }

    /** A real CVR is still sent, so compliant customers are unaffected. */
    #[DataProvider('customersWithACvr')]
    public function testACustomerWithACvrStillSendsIt(string $ean, string $cvrnr, string $expected): void
    {
        $out = $this->resolve($ean, $cvrnr);

        self::assertArrayHasKey('companyId', $out['party'], 'the CVR was dropped');
        self::assertSame($expected, $out['party']['companyId']);
    }

    /** @return array<string, array{string, string, string}> */
    public static function customersWithACvr(): array
    {
        return [
            'CVR only' => ['', '12345674', 'DK12345674'],
            'EAN and CVR' => ['5798009003553', '12345674', 'DK12345674'],
            'EAN written as 0184:value' => ['0184:12345674', '', 'DK12345674'],
        ];
    }

    /**
     * A bare country prefix with no digits behind it is the same as no identifier, and the
     * pre-existing validation at the bottom of sendInvoice() already treats it that way.
     */
    public function testABareCountryPrefixIsTreatedAsNoIdentifier(): void
    {
        $omit = $this->lift('if ($cvrnr_with_prefix === "" || $cvrnr_with_prefix === "DK") {', 'file_put_contents("../temp/$db/data.json"');

        foreach (['', 'DK'] as $cvrnr_with_prefix) {
            $data = array('accountingCustomerParty' => array('companyId' => $cvrnr_with_prefix));
            eval($omit);
            self::assertArrayNotHasKey('companyId', $data['accountingCustomerParty'],
                "'$cvrnr_with_prefix' is not an identifier and must not be sent");
        }

        foreach (['DK12345674', 'SE5798009003553'] as $cvrnr_with_prefix) {
            $data = array('accountingCustomerParty' => array('companyId' => $cvrnr_with_prefix));
            eval($omit);
            self::assertArrayHasKey('companyId', $data['accountingCustomerParty'],
                "'$cvrnr_with_prefix' is an identifier and must be sent");
        }
    }

    /**
     * The omission has to happen before the payload is logged and before it is sent, or
     * temp/<db>/data.json would not match what went to EasyUBL - which is the file anyone
     * debugging this with EasyUBL will be reading.
     */
    public function testTheOmissionHappensBeforeThePayloadIsLoggedAndSent(): void
    {
        $src = self::source();

        $omission = strpos($src, 'unset($data["accountingCustomerParty"]["companyId"]);');
        self::assertNotFalse($omission, 'the companyId omission is gone');

        $log = strpos($src, 'file_put_contents("../temp/$db/data.json"');
        self::assertNotFalse($log, 'the payload log is gone');
        self::assertLessThan($log, $omission,
            'the payload is logged before the omission, so data.json would not match what was sent');

        // Scoped to sendInvoice()'s own body: getInvoicesOrder() is defined earlier in the
        // file, so a bare search for the URL finds the definition rather than this call.
        $fnStart = strpos($src, 'function sendInvoice(');
        self::assertNotFalse($fnStart, 'sendInvoice() is gone');
        $fnEnd = strpos($src, 'function sendOrder(', $fnStart);
        self::assertNotFalse($fnEnd, 'cannot bound sendInvoice() - sendOrder() moved');

        self::assertGreaterThan($fnStart, $omission, 'the omission is outside sendInvoice()');
        self::assertLessThan($fnEnd, $omission, 'the omission is outside sendInvoice()');

        $dispatch = strpos($src, 'getInvoicesOrder($data,', $fnStart);
        self::assertNotFalse($dispatch, 'sendInvoice() no longer dispatches the payload');
        self::assertLessThan($fnEnd, $dispatch, 'the dispatch moved out of sendInvoice()');
        self::assertLessThan($dispatch, $omission,
            'the payload is dispatched before the omission, so the empty companyId still goes out');
    }

    /** The payload still takes the value from the resolved CVR; omission is the only change. */
    public function testThePayloadStillSourcesCompanyIdFromTheResolvedCvr(): void
    {
        self::assertStringContainsString('"companyId" => $cvrnr_with_prefix,', self::source(),
            'the payload no longer sources companyId from the resolved CVR');
    }

    /**
     * This ticket deliberately did NOT start refusing to send. Den-Tec holds only EAN
     * numbers for 43 municipal customers, and the standard permits routing on a GLN - what
     * it forbids is claiming a CVR scheme over a non-CVR value. So the GLN exemption in
     * both missing-CVR guards stays.
     */
    public function testSendingIsStillAllowedWithoutACvr(): void
    {
        $src = self::source();

        self::assertStringContainsString('}elseif($endpointType !== "GLN"){', $src,
            'the first missing-CVR guard no longer exempts GLN, so GLN-only customers are blocked');
        self::assertStringContainsString('&& $endpointType !== "GLN"){', $src,
            'the pre-transmission validation no longer exempts GLN, so GLN-only customers are blocked');
    }

    /**
     * Characterization, NOT desired behaviour: ISO 6523 ICD 0088 is the GS1 GLN, and the
     * Swedish organisation number is 0007. api.php maps 0088 to SE:ORGNR and prefixes the
     * value with SE, so an EAN entered as "0088:<gln>" is transmitted as a Swedish org
     * number and the country code flips to SE with it. This records today's behaviour so
     * that fixing it is a deliberate change and this test has to be updated with it.
     * Flagged on PR for SST-860 as its own follow-up; Den-Tec's EAN has no prefix, so this
     * is not their cause.
     */
    public function testKnownDefectZeroZeroEightEightIsTreatedAsSwedish(): void
    {
        $out = $this->resolve('0088:5798009003553', '');

        self::assertSame('SE:ORGNR', $out['endpointIdType'],
            '0088 handling changed - if it was fixed to GLN, update this test and close the follow-up');
        self::assertSame('SE5798009003553', $out['endpointId']);
        self::assertSame('SE', $out['countryCode']);
    }
}
