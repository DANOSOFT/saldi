<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * CHARACTERIZATION (golden master) suite for includes/usdecimal.php.
 *
 * Contract (bin/test-author.mjs --characterize brief, .factory-run/test-author-brief.md):
 * - Captures the module's CURRENT behavior under PHP 8.3 as the approved baseline.
 *   Every expected value below was observed by executing the real function on
 *   this machine (PHP 8.3.32) BEFORE this suite was written. Baseline equals
 *   reality: surprising outputs are asserted as-is and flagged with comments.
 * - The suite MUST pass against the current, unmodified module.
 * - The module itself is never edited by this task.
 *
 * Module under test: usdecimal($tal)
 *   Parses a Danish-formatted numeric string ("1.234,56") into a float:
 *   1. falsy input is replaced with the string "0,00"
 *   2. ALL "." characters are stripped (assumed thousands separators)
 *   3. "," becomes "."
 *   4. multiplied by 1 (string-to-number coercion), then round(x, 2)
 *   5. a falsy (zero) RESULT is replaced with the string "0.00"
 *
 * Consequence of step 5: the return type is float for nonzero results but the
 * literal STRING "0.00" for every zero-ish result. Callers downstream depend on
 * that string, so this suite pins the exact types with assertSame().
 */
final class UsdecimalCharacterizationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Defensive include: legacy function-based PHP. Buffer any include-time
        // output so a stray byte cannot corrupt PHPUnit's output stream.
        // Observed on 2026-07-16: the include emits NOTHING (asserted below).
        ob_start();
        require_once dirname(__DIR__, 4) . '/includes/usdecimal.php';
        $includeOutput = ob_get_clean();
        self::assertSame('', $includeOutput, 'usdecimal.php emitted output at include time');
        self::assertTrue(function_exists('usdecimal'), 'usdecimal() not defined after include');
    }

    // ---------------------------------------------------------------
    // Happy path: Danish-formatted strings -> float
    // ---------------------------------------------------------------

    /**
     * @return array<string, array{string, float}>
     */
    public static function danishNumericStrings(): array
    {
        return [
            'basic thousands + decimals'  => ['1.234,56', 1234.56],
            'millions'                    => ['1.234.567,89', 1234567.89],
            'large amount'                => ['999.999.999,99', 999999999.99],
            'thousands only, no decimals' => ['1.000', 1000.0],
            'plain integer string'        => ['100', 100.0],
            'simple decimal'              => ['1,5', 1.5],
            'leading comma'               => [',5', 0.5],
            'negative'                    => ['-1.234,56', -1234.56],
            'below one'                   => ['0,49', 0.49],
            // PHP 8 accepts leading/trailing whitespace in numeric strings,
            // so padded input parses instead of fataling. Current behavior.
            'whitespace padded'           => [' 123,45 ', 123.45],
        ];
    }

    #[DataProvider('danishNumericStrings')]
    public function testDanishFormattedStringsParseToFloat(string $input, float $expected): void
    {
        self::assertSame($expected, usdecimal($input));
    }

    // ---------------------------------------------------------------
    // Zero-ish inputs and zero-ish RESULTS return the STRING "0.00"
    // ---------------------------------------------------------------

    /**
     * @return array<string, array{mixed}>
     */
    public static function zeroishInputs(): array
    {
        return [
            'empty string'   => [''],
            'string zero'    => ['0'],
            'danish zero'    => ['0,00'],
            'int zero'       => [0],
            'null'           => [null],
            'false'          => [false],
        ];
    }

    #[DataProvider('zeroishInputs')]
    public function testZeroishInputReturnsTheStringZeroPointZeroZero(mixed $input): void
    {
        // NOT float 0.0 -- the function returns the literal string "0.00".
        self::assertSame('0.00', usdecimal($input));
    }

    public function testNonzeroInputThatRoundsToZeroAlsoReturnsTheString(): void
    {
        // round(0.004, 2) == 0.0, which is falsy, so the final guard swaps in
        // the string "0.00". A truthy INPUT can still yield the string result.
        self::assertSame('0.00', usdecimal('0,004'));
    }

    public function testNegativeTinyValueLosesItsSignAndBecomesTheString(): void
    {
        // SURPRISING but current: round(-0.001, 2) == -0.0, which is falsy,
        // so -0,001 collapses to the POSITIVE string "0.00" (sign discarded).
        self::assertSame('0.00', usdecimal('-0,001'));
        self::assertSame('0.00', usdecimal('-0,00'));
    }

    // ---------------------------------------------------------------
    // Rounding to 2 decimals
    // ---------------------------------------------------------------

    public function testRoundsHalfAwayFromZeroAtTwoDecimals(): void
    {
        self::assertSame(0.01, usdecimal('0,005'));
        self::assertSame(12.35, usdecimal('12,345'));
        // 2.675 is not exactly representable in binary floating point, yet
        // PHP's round() pre-rounding compensation still yields 2.68 here.
        // Observed live; pinned so a PHP upgrade that changes round()
        // semantics is caught.
        self::assertSame(2.68, usdecimal('2,675'));
    }

    // ---------------------------------------------------------------
    // The US-format / float-input trap (documented foot-gun, current behavior)
    // ---------------------------------------------------------------

    public function testUsFormattedStringIsSilentlyMangledByThousandsStripping(): void
    {
        // SURPRISING but current: "." is unconditionally treated as a
        // thousands separator, so a US-formatted "1234.56" becomes "123456"
        // and parses to 123456.0 -- a 100x error with no warning. This IS
        // the baseline; do not "fix" it without a human-approved change.
        self::assertSame(123456.0, usdecimal('1234.56'));
    }

    public function testFloatInputIsMangledTheSameWay(): void
    {
        // SURPRISING but current: a float argument is coerced to its string
        // form "1234.56" by str_replace, then hits the same trap as above.
        self::assertSame(123456.0, usdecimal(1234.56));
    }

    public function testIntegerInputPassesThroughAsFloat(): void
    {
        self::assertSame(7.0, usdecimal(7));
    }

    // ---------------------------------------------------------------
    // Error paths under PHP 8 (current behavior: TypeError, not a value)
    // ---------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function nonNumericStrings(): array
    {
        return [
            'letters'    => ['abc'],
            'dot only'   => ['.'],  // stripped to "" before the multiply
            'comma only' => [','],  // becomes "." before the multiply
        ];
    }

    #[DataProvider('nonNumericStrings')]
    public function testNonNumericStringThrowsTypeErrorUnderPhp8(string $input): void
    {
        // PHP 8 semantics: (non-numeric string) * 1 throws. Under PHP 5/7 this
        // returned 0-ish values instead; the baseline captures TODAY's runtime.
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Unsupported operand types: string * int');
        usdecimal($input);
    }

    public function testLeadingNumericGarbageParsesPrefixAndEmitsWarning(): void
    {
        // Current behavior: "12abc" * 1 yields 12 plus an E_WARNING
        // "A non-numeric value encountered". Captured via a scoped error
        // handler so the baseline records BOTH the value and the warning.
        $warnings = [];
        set_error_handler(function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = [$errno, $errstr];
            return true;
        });
        try {
            $result = usdecimal('12abc');
        } finally {
            restore_error_handler();
        }
        self::assertSame(12.0, $result);
        self::assertSame([[E_WARNING, 'A non-numeric value encountered']], $warnings);
    }
}
