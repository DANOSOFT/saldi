<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/usdecimal.php';

final class usdecimal extends TestCase
{
    public function testStrictParserHasTheRequiredSignature(): void
    {
        self::assertTrue(function_exists('usdecimal_strict'));

        $function = new ReflectionFunction('usdecimal_strict');
        $parameters = $function->getParameters();

        self::assertCount(1, $parameters);
        self::assertSame('amount', $parameters[0]->getName());
        self::assertSame('string', (string) $parameters[0]->getType());
        self::assertSame('float', (string) $function->getReturnType());
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
    public function testStrictParserMatchesLegacyParserForUnambiguousDanishInput(
        string $amount,
        float $expected
    ): void {
        self::assertTrue(function_exists('usdecimal_strict'));
        self::assertSame($expected, usdecimal($amount));
        self::assertSame(usdecimal($amount), usdecimal_strict($amount));
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

    #[DataProvider('ambiguousUsDecimalAmounts')]
    public function testStrictParserRejectsSingleDotFollowedByExactlyTwoDigits(
        string $amount,
        float $legacyResult
    ): void {
        self::assertSame($legacyResult, usdecimal($amount));
        self::assertTrue(function_exists('usdecimal_strict'));

        $this->expectException(InvalidArgumentException::class);
        usdecimal_strict($amount);
    }

    public function testLegacyParserSignatureRemainsUnchanged(): void
    {
        $function = new ReflectionFunction('usdecimal');
        $parameters = $function->getParameters();

        self::assertCount(1, $parameters);
        self::assertSame('tal', $parameters[0]->getName());
        self::assertNull($parameters[0]->getType());
        self::assertNull($function->getReturnType());
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
