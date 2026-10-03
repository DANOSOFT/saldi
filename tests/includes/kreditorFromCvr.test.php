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
