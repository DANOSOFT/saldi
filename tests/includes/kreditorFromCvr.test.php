<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/kreditorFromCvr.php';

/**
 * The parts of the kreditor from the CVR register (SD-721) that decide without a database:
 * whether the CVR register's company may be created, what goes into the kreditor, and how the invoice's bank details are stored.
 */
final class kreditorFromCvr extends TestCase
{
    public function testActiveCompanyIsNotDissolved(): void
    {
        $this->assertFalse(cvrLookupDissolved(['name' => 'LEGO A/S', 'enddate' => null, 'creditstatus' => null]));
    }

    public function testEndDateOrCreditStatusMeansDissolved(): void
    {
        $this->assertTrue(cvrLookupDissolved(['enddate' => '01/06 - 2024']));
        $this->assertTrue(cvrLookupDissolved(['enddate' => null, 'creditstatus' => 'OPLØST']));
        $this->assertTrue(cvrLookupDissolved(['enddate' => null, 'creditstatus' => 'Under konkurs']));
    }

    public function testCompanyFieldsAsTheKreditorCardFillsThem(): void
    {
        $fields = cvrLookupCompanyFields(['vat' => 54562519, 'name' => 'LEGO A/S', 'address' => 'Åstvej 1', 'zipcode' => '7190', 'city' => 'Billund', 'phone' => '79506070', 'email' => null, 'addressco' => null]);
        $this->assertSame(['cvrnr' => '54562519', 'firmanavn' => 'LEGO A/S', 'addr1' => 'Åstvej 1', 'addr2' => '', 'postnr' => '7190', 'bynavn' => 'Billund', 'tlf' => '79506070', 'email' => ''], $fields);
    }

    public function testCareOfMovesTheAddressToTheSecondLine(): void
    {
        $fields = cvrLookupCompanyFields(['vat' => 12345678, 'name' => 'Firma ApS', 'address' => 'Vej 2', 'addressco' => 'Revisor A/S']);
        $this->assertSame('c/o Revisor A/S', $fields['addr1']);
        $this->assertSame('Vej 2', $fields['addr2']);
    }

    /**
     * A CVR_Virksomhed node as Datafordeleren's flexibleCurrent/v3 returns it (shape taken from a real answer).
     */
    private function datafordelerNode(array $address, array $extra = []): array
    {
        return $extra + [
            'CVRNummer' => 10686873,
            'virksomhedOphoersdato' => null,
            'id_CVR_Navn_CVREnhedsId_ref' => ['vaerdi' => 'JPM Rådgivning'],
            'id_CVR_Adressering_CVREnhedsId_ref' => ['nodes' => [$address + [
                'AdresseringAnvendelse' => 'beliggenhedsadresse', 'coNavn' => null, 'CVRAdresse_vejnavn' => 'Stutterivænget',
                'CVRAdresse_husnummerFra' => '7', 'CVRAdresse_husnummerTil' => null, 'CVRAdresse_etagebetegnelse' => '2',
                'CVRAdresse_doerbetegnelse' => 'tv', 'CVRAdresse_postnummer' => '3400', 'CVRAdresse_postdistrikt' => 'Hillerød',
                'CVRAdresse_adresseFritekst' => null,
            ]]],
            'id_CVR_Telefonnummer_CVREnhedsId_ref' => ['vaerdi' => '23720375'],
            'id_CVR_e_mailadresse_CVREnhedsId_ref' => null,
        ];
    }

    public function testDatafordelerCompanyInCvrapiFields(): void
    {
        $this->assertSame(
            ['vat' => '10686873', 'name' => 'JPM Rådgivning', 'address' => 'Stutterivænget 7, 2. tv', 'addressco' => null, 'zipcode' => '3400',
             'city' => 'Hillerød', 'phone' => '23720375', 'email' => '', 'enddate' => null],
            cvrLookupDatafordelerCompany($this->datafordelerNode([]))
        );
    }

    public function testDatafordelerAddressForms(): void
    {
        $address = fn(array $a) => cvrLookupDatafordelerCompany($this->datafordelerNode($a))['address'];
        $this->assertSame('Stutterivænget 7, st. th', $address(['CVRAdresse_etagebetegnelse' => 'st', 'CVRAdresse_doerbetegnelse' => 'th']));
        $this->assertSame('Stutterivænget 7-9', $address(['CVRAdresse_husnummerTil' => '9', 'CVRAdresse_etagebetegnelse' => null, 'CVRAdresse_doerbetegnelse' => null]));
        $this->assertSame('Postboks 12', $address(['CVRAdresse_vejnavn' => null, 'CVRAdresse_adresseFritekst' => 'Postboks 12']));
    }

    public function testDatafordelerCareOfUnlessItRepeatsTheAddress(): void
    {
        $this->assertSame('Jens Prehn Mortensen', cvrLookupDatafordelerCompany($this->datafordelerNode(['coNavn' => 'Jens Prehn Mortensen']))['addressco']);
        $this->assertNull(cvrLookupDatafordelerCompany($this->datafordelerNode(['coNavn' => 'Stutterivænget 7, 2. tv.']))['addressco']);
        $fields = cvrLookupCompanyFields(cvrLookupDatafordelerCompany($this->datafordelerNode(['coNavn' => 'Revisor A/S'])));
        $this->assertSame(['c/o Revisor A/S', 'Stutterivænget 7, 2. tv'], [$fields['addr1'], $fields['addr2']]);
    }

    public function testDatafordelerPrefersTheLocationOverThePostalAddress(): void
    {
        $node = $this->datafordelerNode([]);
        array_unshift($node['id_CVR_Adressering_CVREnhedsId_ref']['nodes'], ['AdresseringAnvendelse' => 'postadresse', 'CVRAdresse_vejnavn' => null, 'CVRAdresse_adresseFritekst' => 'Postboks 12', 'CVRAdresse_postnummer' => '1000', 'CVRAdresse_postdistrikt' => 'København K']);
        $this->assertSame(['Stutterivænget 7, 2. tv', '3400'], array_values(array_intersect_key(cvrLookupDatafordelerCompany($node), ['address' => 1, 'zipcode' => 1])));
        $node['id_CVR_Adressering_CVREnhedsId_ref']['nodes'] = [$node['id_CVR_Adressering_CVREnhedsId_ref']['nodes'][0]];
        $this->assertSame('Postboks 12', cvrLookupDatafordelerCompany($node)['address']);
    }

    public function testDatafordelerRelationShapes(): void
    {
        $this->assertSame(['vaerdi' => 'a'], cvrLookupDatafordelerFirst(['vaerdi' => 'a']));
        $this->assertSame(['vaerdi' => 'b'], cvrLookupDatafordelerFirst(['nodes' => [['vaerdi' => 'b']]]));
        $this->assertSame(['vaerdi' => 'c'], cvrLookupDatafordelerFirst([['vaerdi' => 'c']]));
        $this->assertSame([], cvrLookupDatafordelerFirst(null));
        $this->assertSame([], cvrLookupDatafordelerFirst(['nodes' => []]));
    }

    public function testDanishIbanGivesRegAndKonto(): void
    {
        $this->assertSame(['iban' => 'DK5000400440116243', 'bank_reg' => '0040', 'bank_konto' => '0440116243'], kreditorCvrBankFields('DK5000400440116243'));
    }

    public function testForeignIbanGoesInKonto(): void
    {
        $this->assertSame(['iban' => 'DE89370400440532013000', 'bank_reg' => '', 'bank_konto' => 'DE89370400440532013000'], kreditorCvrBankFields('DE89370400440532013000'));
    }

    public function testRegKontoAsTheMatcherStoresIt(): void
    {
        $this->assertSame(['iban' => '', 'bank_reg' => '1551', 'bank_konto' => '3456789012'], kreditorCvrBankFields('1551-3456789012'));
    }

    public function testNoBankDetails(): void
    {
        $this->assertNull(kreditorCvrBankFields(null));
        $this->assertNull(kreditorCvrBankFields(''));
    }

    public function testOnlyEightDigitsAreLookedUp(): void
    {
        $this->assertTrue(kreditorCvrIsDanish('54562519'));
        $this->assertFalse(kreditorCvrIsDanish('SE556677889901'));
        $this->assertFalse(kreditorCvrIsDanish(null));
    }
}
