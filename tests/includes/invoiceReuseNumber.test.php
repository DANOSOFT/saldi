<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../finans/kassekladde_includes/invoiceReuse.php';

/**
 * Doc pool task 3: which invoice numbers the "already used on this kreditor" check compares, and how.
 */
final class invoiceReuseNumber extends TestCase
{
    /**
     * @return array<string, array{mixed, string}>
     */
    public static function numbers(): array
    {
        return [
            'plain number'                   => ['4711', '4711'],
            'surrounding spaces'             => ['  4711 ', '4711'],
            'case is ignored'                => ['INV/2026/04156', 'inv/2026/04156'],
            'Danish letters lower-cased'     => ['ÆØÅ-12', 'æøå-12'],
            'inner spaces are kept'          => ['191768 191772', '191768 191772'],
            'empty'                          => ['', ''],
            'only spaces'                    => ['   ', ''],
            'dash placeholder'               => ['-', ''],
            'punctuation only'               => ['#/.', ''],
            'zero'                           => ['0', ''],
            'zero with spaces'               => [' 0 ', ''],
            'number starting with zero'      => ['0123', '0123'],
            'integer from the database'      => [4711, '4711'],
            'null'                           => [null, ''],
            'array is ignored'               => [['4711'], ''],
        ];
    }

    #[DataProvider('numbers')]
    public function testNumber(mixed $faktura, string $expected): void
    {
        self::assertSame($expected, invoice_reuse_number($faktura));
    }
}
