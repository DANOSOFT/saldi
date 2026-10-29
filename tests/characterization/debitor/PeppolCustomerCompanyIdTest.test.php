<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/stdFunc/countryCode.php';

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
// 20261023 CL/MMR SD-774 testSendingIsStillAllowedWithoutACvr no longer says that a customer with only a GLN is never blocked: sendInvoice() now stops a Danish GLN receiver without any CVR before the payload is built, so the two exemptions pinned there only let foreign GLN receivers through.
//                 The file requires includes/stdFunc/countryCode.php itself, because the lifted resolution block calls countryCodeIso2() since SST-870 and five tests errored when this file ran alone.
// 20261026 CL/MMR SD-774 testSendingIsStillAllowedWithoutACvr also accepts the first guard with "&& !einvoice_cvr_missing(...)", which sends a Danish receiver on another non-numeric scheme on to easyUblCvrMissing().
// 20261028 CL/MMR SST-905 "0088:<gln>" is now a GLN endpoint with companyId from the CVR field; the known-defect test is replaced, and 0007/0192/9908/0184 are pinned as unchanged.
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
     * SST-860 deliberately did NOT start refusing to send. Den-Tec holds only EAN
     * numbers for 43 municipal customers, and the standard permits routing on a GLN - what
     * it forbids is claiming a CVR scheme over a non-CVR value. So the GLN exemption in
     * both missing-CVR guards stays.
     *
     * 20261023 CL/MMR SD-774: the exemptions still stand, but they no longer mean that a
     * customer with only a GLN is never blocked. sendInvoice() now stops a Danish receiver
     * reached on a GLN when neither the order nor the debitor card has a CVR, before the
     * payload is built, because receivers reject those invoices (DK-R-017 / E-APS24003).
     * That block is pinned in EInvoiceCvrRequirementTest. Foreign GLN receivers without a
     * CVR still pass, and they are what these exemptions let through now. The first guard
     * runs before the SD-774 check, so without its exemption a Danish GLN receiver would
     * get that guard's "neither CVR nor EAN" alert instead of the SD-774 explanation.
     */
    public function testSendingIsStillAllowedWithoutACvr(): void
    {
        $src = self::source();

        // SD-774: the guard also lets a Danish receiver on another non-numeric scheme through to easyUblCvrMissing()
        self::assertMatchesRegularExpression('/\}elseif\(\$endpointType !== "GLN"( && !einvoice_cvr_missing\([^{]*\))?\)\{/', $src,
            'the first missing-CVR guard no longer exempts GLN, so foreign GLN receivers without a CVR are blocked,'
            . ' and Danish ones get its "neither CVR nor EAN" alert instead of the SD-774 explanation');
        self::assertStringContainsString('&& $endpointType !== "GLN"){', $src,
            'the pre-transmission validation no longer exempts GLN, so foreign GLN receivers without a CVR are blocked');
    }

    /**
     * SST-905: ICD 0088 is the GS1 GLN. "0088:<gln>" is routed like a plain EAN (endpoint type
     * GLN, the 13 digits), and companyId comes from the CVR field, not from the GLN. It was
     * mapped to SE:ORGNR and sent as "SE<gln>" (MEDSHOP faktura 238877, Error 2026-07-07).
     */
    public function testZeroZeroEightEightIsAGln(): void
    {
        $out = $this->resolve('0088:5798009003553', '');
        self::assertSame('GLN', $out['endpointIdType'], '0088 is the GS1 GLN scheme, not a Swedish organisation number');
        self::assertSame('5798009003553', $out['endpointId']);
        self::assertArrayNotHasKey('companyId', $out['party'], 'a GLN is no legal-entity id; without a CVR no companyId is sent');
        self::assertSame('DK', $out['countryCode']);

        $out = $this->resolve('0088:7350043510008', 'SE202100297301');
        self::assertSame('GLN', $out['endpointIdType']);
        self::assertSame('7350043510008', $out['endpointId']);
        self::assertSame('SE202100297301', $out['party']['companyId'], 'companyId must come from the CVR field, not from the GLN');
        self::assertSame('SE', $out['countryCode']);
    }

    /** @return array<string, array{string, string, string, string, string}> */
    public static function otherNumericSchemes(): array
    {
        return [
            '0007 Swedish org. no.'            => ['0007:5566196399', 'SE556619639901', 'SE:ORGNR', 'SE5566196399', 'SE5566196399'],
            '0192 Norwegian org. no.'          => ['0192:993467049', '993467049', 'NO:ORG', 'NO993467049', 'NO993467049'],
            '9908 Norwegian org. no. (old)'    => ['9908:919149795', '', 'NO:ORGNR', 'NO919149795', 'NO919149795'],
            '0184 Danish CVR'                  => ['0184:12345674', '', 'DK:CVR', 'DK12345674', 'DK12345674'],
        ];
    }

    /** SST-905: the other numeric schemes are unchanged; their value is the party's id and stays companyId. */
    #[DataProvider('otherNumericSchemes')]
    public function testOtherNumericSchemesKeepTheirMapping(string $ean, string $cvr, string $type, string $endpoint, string $companyId): void
    {
        $out = $this->resolve($ean, $cvr);
        self::assertSame($type, $out['endpointIdType']);
        self::assertSame($endpoint, $out['endpointId']);
        self::assertSame($companyId, $out['party']['companyId'] ?? null);
    }
}
