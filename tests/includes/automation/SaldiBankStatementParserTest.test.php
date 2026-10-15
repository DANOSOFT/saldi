<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiBankStatementParserTest.test.php --- ai/mcp-assistant --- 2026.10.05
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// Synthetic bank layouts, decimal and date classification, bounds and XML entity rejection.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Add regression coverage for companion review corrections.

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/automation/SaldiBankStatementParser.php';

final class SaldiBankStatementParserTest extends PHPUnit\Framework\TestCase
{
    /** @return array<string,array{string}> */
    public static function layouts(): array
    {
        return array_combine(
            ['Danske Bank', 'Nordea', 'Jyske Bank', 'Sydbank', 'Spar Nord', 'Lunar', 'Arbejdernes Landsbank', 'camt.053'],
            array_map(static fn(string $file): array => [$file], [
                'danske-bank.csv', 'nordea.csv', 'jyske-bank.csv', 'sydbank.csv', 'spar-nord.csv',
                'lunar.csv', 'arbejdernes-landsbank.csv', 'camt053.xml',
            ])
        );
    }

    #[PHPUnit\Framework\Attributes\DataProvider('layouts')]
    public function testSyntheticBankLayouts(string $file): void
    {
        $parsed = SaldiBankStatementParser::parse(file_get_contents(__DIR__ . '/../fixtures/bank/' . $file), $file);
        self::assertSame($file === 'camt053.xml' ? 'camt.053' : 'csv', $parsed['format']);
        self::assertCount(2, $parsed['rows']);
        self::assertSame(['2025-06-04', '2025-06-05'], array_column($parsed['rows'], 'date'));
        self::assertSame(['1234.56', '-25.00'], array_column($parsed['rows'], 'amount'));
        self::assertSame(['DKK', 'DKK'], array_column($parsed['rows'], 'currency'));
        if (!in_array($file, ['nordea.csv', 'arbejdernes-landsbank.csv'], true)) {
            self::assertStringContainsString('Æble', $parsed['rows'][0]['description']);
        }
        if ($file === 'sydbank.csv') {
            self::assertStringContainsString('Windows-1252', $parsed['warnings'][0]);
        }
        if ($file === 'camt053.xml') {
            self::assertSame('Kunde Æble betaling & faktura 501', $parsed['rows'][0]['description']);
            self::assertSame(['INV-501', 'BANK-25'], array_column($parsed['rows'], 'reference'));
        } else {
            self::assertSame(['11234.56', '11209.56'], array_column($parsed['rows'], 'balance'));
        }
    }

    /** @return list<array{string,?string}> */
    public static function amounts(): array
    {
        return [
            ['1.234,56', '1234.56'], ['-1.234,56', '-1234.56'], ['1234,56', '1234.56'],
            ['1234.56', '1234.56'], ['1,234.56', '1234.56'], ['-1,234.56', '-1234.56'],
            ['+12', '12.00'], ['00012,1', '12.10'], ['0.005', '0.005'], ['-0,00', '0.00'],
            ['1 234,56', '1234.56'], ["1\xc2\xa0234,56", '1234.56'],
            ['12.34,56', null], ['1,23.45', null], ['--1', null], ['-+1', null],
            ['1e3', null], ['12,1234', null], ['9999999999999', null], ['', null],
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('amounts')]
    public function testClassifiesDecimalsBeforeConversion(string $input, ?string $expected): void
    {
        self::assertSame($expected, SaldiBankStatementParser::amount($input));
    }

    /** @return list<array{string,?string}> */
    public static function dates(): array
    {
        return [
            ['04-06-2025', '2025-06-04'], ['04.06.2025', '2025-06-04'],
            ['04/06/2025', '2025-06-04'], ['2025-06-04', '2025-06-04'],
            ['04-06-25', '2025-06-04'], ['29-02-2024', '2024-02-29'],
            ['29-02-2025', null], ['31/04/2025', null], ['2025-13-04', null],
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('dates')]
    public function testDatesRejectRollover(string $input, ?string $expected): void
    {
        self::assertSame($expected, SaldiBankStatementParser::date($input));
    }

    public function testHeaderlessDescendingBalanceAndNumericReferences(): void
    {
        $parsed = SaldiBankStatementParser::parse("05-06-2025;Gebyr;77;-25,00;11209,56\n04-06-2025;Indbetaling;88;1234,56;11234,56\n");
        self::assertSame(['-25.00', '1234.56'], array_column($parsed['rows'], 'amount'));
    }

    public function testBomQuotedDelimitersMultilineTextAndPreamble(): void
    {
        $parsed = SaldiBankStatementParser::parse("\xef\xbb\xbfKontoudtog\nDato;Tekst;Beløb;Saldo\n04-06-2025;\"Æble, A; B\nBetaling\";10,00;100,00\n05-06-2025;Gebyr;-5,00;95,00\n");
        self::assertSame("Æble, A; B\nBetaling", $parsed['rows'][0]['description']);
        self::assertSame('10.00', $parsed['rows'][0]['amount']);
    }

    public function testHeaderWithoutDescriptionAndHeaderlessSingleAmount(): void
    {
        $parsed = SaldiBankStatementParser::parse("date;amount\n2025-06-04;10,00\n");
        self::assertSame('', $parsed['rows'][0]['description']);
        $parsed = SaldiBankStatementParser::parse("2025-06-04\tBetaling\t10.00\n");
        self::assertSame('10.00', $parsed['rows'][0]['amount']);
    }

    public function testInvalidRowsProduceVisibleWarnings(): void
    {
        $parsed = SaldiBankStatementParser::parse("Dato;Tekst;Beløb\n31-02-2025;Ugyldig;10,00\n04-06-2025;Gyldig;10,00\n");
        self::assertCount(1, $parsed['rows']);
        self::assertCount(1, $parsed['warnings']);
    }

    /** @return list<array{string}> */
    public static function invalidFiles(): array
    {
        return [
            [''], [str_repeat('x', 2 * 1024 * 1024 + 1)],
            ["date;amount\n" . str_repeat("2025-06-04;1,00\n", 5001)],
            ['<Document><broken></Document>'], ['<other/>'],
            ['<!DOCTYPE x [<!ENTITY injected SYSTEM "file:///etc/hostname">]><Document>&injected;</Document>'],
            ['<!DOCTYPE x [<!ENTITY injected "secret-free synthetic text">]><Document>&injected;</Document>'],
            ["04-06-2025;One;2;2;100\n05-06-2025;Two;2;2;102\n06-06-2025;Three;2;2;104\n"],
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('invalidFiles')]
    public function testRejectsUnsafeOversizeOrAmbiguousFiles(string $content): void
    {
        $this->expectException(SaldiRuntimeError::class);
        SaldiBankStatementParser::parse($content);
    }

    public function testExactlyFiveThousandRowsAreAccepted(): void
    {
        $parsed = SaldiBankStatementParser::parse("date;amount\n" . str_repeat("2025-06-04;1,00\n", 5000));
        self::assertCount(5000, $parsed['rows']);
    }

    public function testNamespacedXmlReferenceFallbackAndCurrency(): void
    {
        $xml = str_replace('Ccy="DKK"', 'Ccy="EUR"', file_get_contents(__DIR__ . '/../fixtures/bank/camt053.xml'));
        self::assertSame(['EUR', 'EUR'], array_column(SaldiBankStatementParser::parse($xml)['rows'], 'currency'));
    }

    public function testHeaderlessTabDelimiterWinsOverDecimalCommas(): void
    {
        $parsed = SaldiBankStatementParser::parse("04-06-2025\tÆble\t10,00\t100,00\n05-06-2025\tGebyr\t-5,00\t95,00\n");
        self::assertSame(['10.00', '-5.00'], array_column($parsed['rows'], 'amount'));
    }

    public function testXmlRowLimitIsEnforced(): void
    {
        $entry = '<Ntry><Amt Ccy="DKK">1.00</Amt><CdtDbtInd>CRDT</CdtDbtInd>'
            . '<BookgDt><Dt>2025-06-04</Dt></BookgDt></Ntry>';
        $this->expectException(SaldiRuntimeError::class);
        SaldiBankStatementParser::parse('<Document><BkToCstmrStmt><Stmt>' . str_repeat($entry, 5001)
            . '</Stmt></BkToCstmrStmt></Document>');
    }

    public function testWhitespaceAroundNotProvidedStillUsesBankReference(): void
    {
        $xml = str_replace(
            '>NOTPROVIDED<',
            '> NOTPROVIDED <',
            file_get_contents(__DIR__ . '/../fixtures/bank/camt053.xml')
        );
        self::assertSame('BANK-25', SaldiBankStatementParser::parse($xml)['rows'][1]['reference']);
    }
    public function testHeaderlessUnresolvedTwoNumericColumnsRequireDanishHeaderWarning(): void
    {
        foreach (["2025-06-04;Tekst;998877;100,00\n", "2025-06-04;Tekst;998877;100,00\n2025-06-05;Tekst;998878;200,00\n"] as $csv) {
            try {
                SaldiBankStatementParser::parse($csv);
                self::fail('Ambiguous movement must be rejected');
            } catch (SaldiRuntimeError $e) {
                self::assertStringContainsString('overskriftsrække', $e->detail['warning_da']);
            }
        }
    }

    public function testDanishThousandsWithoutDecimalsUseFileConvention(): void
    {
        $parsed = SaldiBankStatementParser::parse("Dato;Tekst;Beløb;Saldo\n04-06-2025;Køb;-1.500;1.234\n05-06-2025;Tilgang;10,50;1.244,50\n");
        self::assertSame(['-1500.00', '10.50'], array_column($parsed['rows'], 'amount'));
        self::assertSame(['1234.00', '1244.50'], array_column($parsed['rows'], 'balance'));
        self::assertSame('1.234', SaldiBankStatementParser::amount('1.234'));
    }

    public function testCamtUnbookedEntriesAreRejected(): void
    {
        $xml = str_replace('<Ntry>', '<Ntry><Sts><Cd>PDNG</Cd></Sts>', file_get_contents(__DIR__ . '/../fixtures/bank/camt053.xml'));
        $this->expectException(SaldiRuntimeError::class);
        SaldiBankStatementParser::parse($xml);
    }

}
