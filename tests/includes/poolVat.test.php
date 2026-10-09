<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/docsIncludes/poolAccountInfo.php';

/**
 * The VAT code per side of a pool line (SD-725): what is saved and what is shown, by the journal's rules.
 */
final class poolVat extends TestCase
{
    private const CODES = ['E1' => 'EU', 'K1' => 'Købsmoms 25%', 'S1' => 'Salgsmoms 25%', 'Y1' => 'Ydelser'];

    public function testBlankGivesTheAccountsCode(): void
    {
        $this->assertSame('K1', poolVatChoose('', 'K1', false, '', self::CODES));
    }

    public function testAChosenCodeIsKept(): void
    {
        $this->assertSame('E1', poolVatChoose('E1', 'K1', false, '', self::CODES));
    }

    public function testVatFreeLineHasNoCode(): void
    {
        $this->assertSame('', poolVatChoose('K1', 'K1', true, '', self::CODES));
        $this->assertSame('', poolVatChoose('', 'K1', true, 'S1', self::CODES));
    }

    public function testBlankNextToACodeOnTheOtherSideIsAChoice(): void
    {
        $this->assertSame('', poolVatChoose('', 'S1', false, 'K1', self::CODES));
    }

    public function testUnknownCodeCountsAsBlank(): void
    {
        $this->assertSame('K1', poolVatChoose('X9', 'K1', false, '', self::CODES));
        $this->assertSame('K1', poolVatChoose('', 'K1', false, 'X9', self::CODES));
    }

    public function testKreditorSideHasNoAccountCode(): void
    {
        $this->assertSame('', poolVatChoose('', '', false, 'K1', self::CODES));
        $this->assertSame('', poolVatAccountCode('K', '1002', 1, self::CODES));
    }

    public function testShownIsTheSavedCodeElseTheAccounts(): void
    {
        $this->assertSame('E1', poolVatShown('E1', 'K1', false, self::CODES));
        $this->assertSame('K1', poolVatShown(null, 'K1', false, self::CODES));
        $this->assertSame('K1', poolVatShown('', 'K1', false, self::CODES));
        $this->assertSame('', poolVatShown('', 'K1', true, self::CODES));
        $this->assertSame('K1', poolVatShown('X9', 'K1', false, self::CODES));
    }
}
