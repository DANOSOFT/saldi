<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/docsIncludes/poolAccountInfo.php';

/**
 * Doc pool task 2: how a pool Debet/Kredit value and its stored type turn into the type + number the row shows.
 */
final class poolAccountSplit extends TestCase
{
    /**
     * @return array<string, array{mixed, mixed, string, array{string, string}}>
     */
    public static function values(): array
    {
        return [
            'prefix sets the type'                  => ['K1234', '', 'F', ['K', '1234']],
            'lower-case prefix'                     => ['d91064', '', 'F', ['D', '91064']],
            'prefix wins over the stored type'      => ['K81079', 'F', 'F', ['K', '81079']],
            'F prefix'                              => ['F1050', 'K', 'K', ['F', '1050']],
            'stored type kept for a plain number'   => ['91064', 'D', 'F', ['D', '91064']],
            'stored type in lower case'             => ['81079', 'k', 'F', ['K', '81079']],
            'empty stored type uses the default'    => ['1050', '', 'K', ['K', '1050']],
            'unknown stored type uses the default'  => ['1050', 'X', 'F', ['F', '1050']],
            'zero account is empty'                 => ['0', 'F', 'F', ['F', '']],
            'empty value keeps the type'            => ['', 'K', 'F', ['K', '']],
            'surrounding spaces'                    => [' K12 ', '', 'F', ['K', '12']],
            'text is not a prefix'                  => ['Kontor', '', 'F', ['F', 'Kontor']],
            'letter alone is not a prefix'          => ['K', '', 'F', ['F', 'K']],
            'integer value from the database'       => [1100, 'F', 'F', ['F', '1100']],
            'null value'                            => [null, null, 'K', ['K', '']],
            'bad default falls back to F'           => ['12', '', 'Z', ['F', '12']],
            'array input is ignored'                => [['K1'], ['K'], 'F', ['F', '']],
        ];
    }

    #[DataProvider('values')]
    public function testSplit(mixed $value, mixed $type, string $default, array $expected): void
    {
        self::assertSame($expected, poolAccountSplit($value, $type, $default));
    }
}
