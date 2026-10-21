<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/stdFunc/countryCode.php';

// 20261019 CL/MJ SST-870 Pins which country an e-invoice is sent with.
//
//                  MEDSHOP's invoice 205406 to Region Skåne in Lund went out saying Denmark,
//                  on both the customer and the delivery address. sendInvoice() started the
//                  country at "DK" and only moved it if the CVR field carried a country
//                  prefix; ordrer.land was never read. Region Skåne is reached by GLN and had
//                  no CVR on file, so there was nothing to move it. 33 invoices in a year went
//                  out that way, to Sweden and Norway.
//
//                  Two things have to stay true while that is fixed, and both are pinned
//                  below: the prefix in the CVR field still decides companyId - it is a
//                  statement about the number, not the address - and Greenland and the Faroe
//                  Islands are still sent inside the Danish VAT area.
//
//                  sendInvoice() is a 400-line function that opens a database connection and
//                  posts to EasyUBL, so the derivation is lifted out of the source and
//                  evaluated here instead of being reimplemented, and no customer data is used.
final class EInvoiceCountryCodeTest extends TestCase
{
    private const API = __DIR__ . '/../../../debitor/api.php';

    private static ?string $block = null;

    /**
     * The country derivation, lifted from sendInvoice().
     *
     * Everything from the CVR prefix being read to the delivery code being settled. Lifting it
     * rather than copying it means this test cannot keep passing against a rule that has moved on.
     */
    private static function derivation(): string
    {
        if (self::$block === null) {
            $src = file_get_contents(self::API);
            self::assertNotFalse($src, 'could not read debitor/api.php');
            $at = strpos($src, '$cvrCountry = ($cvrnr_with_prefix !== "")');
            self::assertNotFalse($at, 'the country derivation is gone from sendInvoice()');
            $end = strpos($src, '// 20260604 - Validate recipient address', $at);
            self::assertNotFalse($end, 'the derivation no longer ends where the address check begins');
            self::$block = substr($src, $at, $end - $at);
        }
        return self::$block;
    }

    /**
     * Runs the real derivation for one invoice.
     *
     * @param string $land      ordrer.land
     * @param string $levLand   ordrer.lev_land
     * @param string $cvr       The CVR already assembled with its prefix, as the code upstream leaves it
     * @return array{country: string, delivery: string, companyId: string}
     */
    private function derive(string $land, string $levLand = '', string $cvr = ''): array
    {
        $r_faktura = array('land' => $land, 'lev_land' => $levLand);
        $cvrnr_with_prefix = $cvr;
        eval(self::derivation());
        return array(
            'country'   => $countryCode,
            'delivery'  => $deliveryCountryCode,
            'companyId' => $cvrnr_with_prefix,
        );
    }

    // ------------------------------------------------------------ the reported case

    /** Invoice 205406: country field "Sverige", reached by GLN, no CVR on file. */
    public function testTheReportedInvoiceIsSentAsSweden(): void
    {
        $got = $this->derive('Sverige');
        self::assertSame('SE', $got['country'],
            'a Swedish customer without a prefixed CVR is sent as Denmark again');
        self::assertSame('', $got['companyId'],
            'a companyId was invented for a customer who has no CVR');
    }

    /** @return array<string, array{string, string}> */
    public static function countryFields(): array
    {
        return array(
            // The three spellings MEDSHOP's own data actually holds.
            'Sverige'              => array('Sverige', 'SE'),
            'Sweden'               => array('Sweden', 'SE'),
            'SE'                   => array('SE', 'SE'),
            'Norge'                => array('Norge', 'NO'),
            'Norway'               => array('Norway', 'NO'),
            'NO'                   => array('NO', 'NO'),
            'Danmark'              => array('Danmark', 'DK'),
            'Denmark'              => array('Denmark', 'DK'),
            'DK'                   => array('DK', 'DK'),
            'Tyskland'             => array('Tyskland', 'DE'),
            'Germany'              => array('Germany', 'DE'),
            'lower case'           => array('sverige', 'SE'),
            'padded'               => array('  Sverige  ', 'SE'),
            // Inside the Danish VAT area for e-invoicing, as before.
            'Grønland'             => array('Grønland', 'DK'),
            'Greenland'            => array('Greenland', 'DK'),
            'Færøerne'             => array('Færøerne', 'DK'),
            'Faroe Islands'        => array('Faroe Islands', 'DK'),
            // Nothing recognisable: unchanged from before this ticket.
            'empty'                => array('', 'DK'),
            'unknown country'      => array('Mars', 'DK'),
            'alpha-3 is not read'  => array('SWE', 'DK'),
        );
    }

    /** The country field decides, for every spelling the mapping knows. */
    #[DataProvider('countryFields')]
    public function testTheCountryFieldDecidesTheCustomerAddress(string $land, string $expected): void
    {
        self::assertSame($expected, $this->derive($land)['country'],
            "land \"$land\" should be sent as $expected");
    }

    // ------------------------------------------- the CVR prefix is the fallback, not the source

    /** With no country field, the CVR prefix still decides, as it always did. */
    public function testTheCvrPrefixIsStillUsedWhenTheCountryFieldIsEmpty(): void
    {
        self::assertSame('SE', $this->derive('', '', 'SE232100025501')['country'],
            'the CVR prefix no longer fills in for a missing country field');
        self::assertSame('DK', $this->derive('', '', 'DK12345678')['country']);
    }

    /**
     * The country field wins over the CVR prefix, without touching companyId.
     *
     * This is the separation that matters. A Danish CVR on a Swedish customer is ordinary - the
     * number is registered in Denmark - and the address still has to say Sweden while companyId
     * keeps the number it was given.
     */
    public function testTheCountryFieldWinsWithoutRewritingTheCompanyId(): void
    {
        $got = $this->derive('Sverige', '', 'DK12345678');
        self::assertSame('SE', $got['country'], 'the CVR prefix is overriding the country field');
        self::assertSame('DK12345678', $got['companyId'], 'the companyId was rewritten from the address');
    }

    /** Neither source says anything: DK, exactly as before. */
    public function testNothingKnownIsStillDenmark(): void
    {
        self::assertSame('DK', $this->derive('')['country']);
        self::assertSame('', $this->derive('')['companyId']);
    }

    // ----------------------------------------------------- Greenland and the Faroe Islands

    /** @return array<string, array{string, string, string}> */
    public static function danishAreaCvrs(): array
    {
        return array(
            'Greenland CVR'    => array('GL12345678', 'DK12345678', 'DK'),
            'Faroe Islands CVR' => array('FO87654321', 'DK87654321', 'DK'),
        );
    }

    /** A GL/FO prefix is still rewritten to DK in companyId, and sent as DK. */
    #[DataProvider('danishAreaCvrs')]
    public function testAGreenlandOrFaroeCvrIsStillSentAsDanish(string $cvr, string $companyId, string $country): void
    {
        $got = $this->derive('', '', $cvr);
        self::assertSame($companyId, $got['companyId'], "$cvr should be rewritten to $companyId");
        self::assertSame($country, $got['country'], "$cvr should be sent as $country");
    }

    /** The same from the country field, where there is no number to rewrite. */
    public function testGreenlandFromTheCountryFieldDoesNotInventACompanyId(): void
    {
        $got = $this->derive('Grønland');
        self::assertSame('DK', $got['country']);
        self::assertSame('', $got['companyId'], 'a companyId was invented out of the country field');
    }

    /** A foreign CVR must not be rewritten because the address is in the Danish area. */
    public function testAForeignCvrSurvivesAGreenlandAddress(): void
    {
        $got = $this->derive('Grønland', '', 'SE232100025501');
        self::assertSame('DK', $got['country'], 'a Greenland address should still be sent as DK');
        self::assertSame('SE232100025501', $got['companyId'],
            "the Swedish organisation number was rewritten because the address is Greenlandic");
    }

    // ------------------------------------------------------------- the delivery address

    /** Goods to one country, invoice to another. */
    public function testTheDeliveryAddressCarriesItsOwnCountry(): void
    {
        $got = $this->derive('Sverige', 'Norge');
        self::assertSame('SE', $got['country'], 'the delivery country leaked onto the customer address');
        self::assertSame('NO', $got['delivery'], 'the delivery address ignores lev_land');
    }

    /** An empty delivery country follows the customer rather than falling to DK. */
    public function testAnEmptyDeliveryCountryFollowsTheCustomer(): void
    {
        self::assertSame('SE', $this->derive('Sverige', '')['delivery']);
        self::assertSame('SE', $this->derive('Sverige', 'Mars')['delivery'],
            'an unrecognised delivery country should fall back to the customer, not to DK');
    }

    /** The Danish-area rule applies to the delivery address too. */
    public function testAGreenlandDeliveryIsSentAsDanish(): void
    {
        self::assertSame('DK', $this->derive('Danmark', 'Grønland')['delivery']);
    }

    // ------------------------------------------------- what this change must not touch

    /**
     * Each address in the payload has to read the code meant for it.
     *
     * The derivation above is lifted and evaluated, so it cannot see which variable the payload
     * is actually built from. Without this, putting $countryCode back on the delivery address
     * would leave every test green and lev_land ignored again.
     */
    public function testEachAddressInThePayloadReadsItsOwnCode(): void
    {
        $src = file_get_contents(self::API);
        self::assertNotFalse($src);

        // The real delivery address is the block built from the lev_* fields.
        $at = strpos($src, '"cityName" => $levBynavn,');
        self::assertNotFalse($at, 'the delivery address block has moved');
        $delivery = substr($src, $at, 260);
        self::assertStringContainsString('"countryCode" => $deliveryCountryCode', $delivery,
            'the delivery address does not use the delivery country, so lev_land is ignored');

        // accountingCustomerParty is the customer, and must not take the delivery country.
        $at = strpos($src, '"accountingCustomerParty"');
        self::assertNotFalse($at, 'accountingCustomerParty has moved');
        $customer = substr($src, $at, 1400);
        self::assertStringContainsString('"countryCode" => $countryCode', $customer,
            'the customer address no longer uses the customer country');
        self::assertStringNotContainsString('$deliveryCountryCode', $customer,
            'the delivery country leaked onto the customer address');

        // Exactly one address reads the delivery code: the delivery one.
        self::assertSame(1, substr_count($src, '"countryCode" => $deliveryCountryCode'),
            'the delivery country is used on more or fewer than one address');
    }

    /**
     * The endpoint is decided upstream and must not move.
     *
     * Region Skåne is routed by GLN 7322730089999. The acceptance criteria call the endpoint and
     * its type out explicitly, so the lifted block is checked for ever assigning either.
     */
    public function testTheDerivationNeverTouchesTheEndpoint(): void
    {
        $block = self::derivation();
        foreach (array('$endpointId', '$endpointType') as $name) {
            self::assertDoesNotMatchRegularExpression(
                '/' . preg_quote($name, '/') . '\s*=[^=]/', $block,
                "the country derivation assigns $name, which routes the document");
        }
    }

    // ------------------------------------------------------------------ the mapping itself

    /**
     * The same name in either database encoding resolves the same way.
     *
     * A tenant database may be UTF-8 or ISO-8859-1, so "Grønland" reaches the function as
     * different bytes depending on the install. The lookup folds away everything outside a-z0-9
     * for exactly that reason, and this is what proves it rather than assuming it.
     */
    public function testANameResolvesTheSameInEitherDatabaseEncoding(): void
    {
        foreach (array('Grønland' => 'GL', 'Færøerne' => 'FO', 'Østrig' => 'AT',
                       'Grækenland' => 'GR', 'Rumænien' => 'RO', 'Sverige' => 'SE') as $name => $code) {
            $latin1 = mb_convert_encoding($name, 'ISO-8859-1', 'UTF-8');
            self::assertSame($code, countryCodeIso2($name, 'FALLBACK'), "utf-8 \"$name\"");
            self::assertSame($code, countryCodeIso2($latin1, 'FALLBACK'), "iso-8859-1 \"$name\"");
        }
    }

    /** The helper reports the real code for GL and FO; sending them as DK is the caller's rule. */
    public function testTheHelperDoesNotDecideTheDanishAreaRule(): void
    {
        self::assertSame('GL', countryCodeIso2('Grønland', 'FALLBACK'),
            'the Danish-area rule has moved into the mapping, where other callers cannot opt out');
        self::assertSame('FO', countryCodeIso2('Færøerne', 'FALLBACK'));
    }

    /** An unrecognised value returns the caller's fallback untouched, never a guess. */
    public function testAnUnknownCountryReturnsTheFallback(): void
    {
        foreach (array('', '   ', 'Mars', 'SWE', 'Atlantis', '42', '-') as $value) {
            self::assertSame('FALLBACK', countryCodeIso2($value, 'FALLBACK'),
                'an unrecognised country field produced a guess: ' . var_export($value, true));
        }
    }
}
