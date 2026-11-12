<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/stdFunc/usDecimal.php';

/**
 * Pins the current behavior of usdecimal() from includes/stdFunc/usDecimal.php.
 *
 * includes/usdecimal.php and its usdecimal_strict() were removed (SD-619), so
 * nothing rejects ambiguous input any more; these tests document what the
 * remaining parser does with it instead.
 */
final class usdecimal extends TestCase
{
    public function testParserSignatureRemainsUnchanged(): void
    {
        self::assertTrue(function_exists('usdecimal'));

        $function = new ReflectionFunction('usdecimal');
        $parameters = $function->getParameters();

        self::assertCount(2, $parameters);
        self::assertSame('tal', $parameters[0]->getName());
        self::assertSame('decimaler', $parameters[1]->getName());
        self::assertNull($parameters[0]->getType());
        self::assertNull($function->getReturnType());
    }

    /**
     * @return array<string, array{string, float}>
     */
    public static function unambiguousDanishAmounts(): array
    {
        return [
            'thousands and decimals' => ['1.234,56', 1234.56],
            'plain integer'          => ['100', 100.0],
            'decimal comma'          => ['12,34', 12.34],
            'thousands only'         => ['1.234', 1234.0],
        ];
    }

    #[DataProvider('unambiguousDanishAmounts')]
    public function testParsesUnambiguousDanishInput(string $amount, float $expected): void
    {
        self::assertSame($expected, usdecimal($amount));
    }

    /**
     * @return array<string, array{string, float}>
     */
    public static function ambiguousUsDecimalAmounts(): array
    {
        return [
            'ticket example' => ['1234.56', 123456.0],
            'two digits before separator' => ['12.34', 1234.0],
            'one digit before separator' => ['1.23', 123.0],
            'negative amount' => ['-12.34', -1234.0],
        ];
    }

    /**
     * A single dot followed by two digits is read as a thousands separator,
     * so a US-style decimal comes back a hundred times too large.
     */
    #[DataProvider('ambiguousUsDecimalAmounts')]
    public function testReadsASingleDotAsAThousandsSeparator(string $amount, float $legacyResult): void
    {
        self::assertSame($legacyResult, usdecimal($amount));
    }

    /**
     * @return array<string, array{mixed, mixed}>
     */
    public static function legacyOutputs(): array
    {
        return [
            'Danish amount'       => ['1.234,56', 1234.56],
            'US decimal foot-gun' => ['1234.56', 123456.0],
            'zero string'         => ['0', '0.00'],
            'integer input'       => [7, 7.0],
        ];
    }

    #[DataProvider('legacyOutputs')]
    public function testLegacyParserOutputsRemainUnchanged(mixed $amount, mixed $expected): void
    {
        self::assertSame($expected, usdecimal($amount));
    }
}
