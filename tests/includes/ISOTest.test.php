<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/ISO.php';

final class ISOTest extends TestCase
{
    public function testAllTwentySevenEuMemberStatesAreListedWithUniqueCodes(): void
    {
        self::assertCount(27, ISO::EU_COUNTRIES);
        self::assertCount(27, array_unique(ISO::EU_COUNTRIES));
        foreach (ISO::EU_COUNTRIES as $code) {
            self::assertMatchesRegularExpression('/^[A-Z]{2}$/', $code);
        }
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function countries(): array
    {
        return [
            'Denmark'        => ['Denmark', 'DK'],
            'Germany'        => ['Germany', 'DE'],
            'Greece'         => ['Greece', 'GR'],
            'Czech alias'    => ['Czech Republic', 'CZ'],
            'Dutch alias'    => ['The Netherlands', 'NL'],
            'Norway'         => ['Norway', 'NO'],
            'Switzerland'    => ['Switzerland', 'CH'],
            'unknown'        => ['Atlantis', null],
            'empty'          => ['', null],
        ];
    }

    #[DataProvider('countries')]
    public function testCountryCode(string $name, ?string $expected): void
    {
        self::assertSame($expected, ISO::countryCode($name));
    }

    public function testIsEuCountry(): void
    {
        self::assertTrue(ISO::isEuCountry('Sweden'));
        self::assertTrue(ISO::isEuCountry('Czech Republic'));
        self::assertFalse(ISO::isEuCountry('Norway'));
        self::assertFalse(ISO::isEuCountry('Atlantis'));
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function regions(): array
    {
        return [
            'Copenhagen'     => [1000, 'Denmark', 'DK-84'],
            'Bornholm'       => [3700, 'Denmark', 'DK-84'],
            'Roskilde'       => [4000, 'Denmark', 'DK-85'],
            'Odense'         => [5000, 'Denmark', 'DK-83'],
            'Aarhus'         => [8000, 'Denmark', 'DK-82'],
            'Aalborg'        => [9000, 'Denmark', 'DK-81'],
            'range start'    => [1, 'Denmark', 'DK-84'],
            'range end'      => [9990, 'Denmark', 'DK-81'],
            'gap'            => [2636, 'Denmark', 'NA'],
            'zero'           => [0, 'Denmark', 'NA'],
            'above range'    => [10000, 'Denmark', 'NA'],
            'other country'  => [1000, 'Norway', 'NA'],
        ];
    }

    #[DataProvider('regions')]
    public function testRegionNumber(int $postalCode, string $country, string $expected): void
    {
        self::assertSame($expected, ISO::regionNumber($postalCode, $country));
    }

    public function testRegionNumberMatchesTheOriginalImplementationForEveryPostalCode(): void
    {
        $original = static function (int $cipcode): string {
            $ranges = [
                'DK-84' => [range(1, 2635), range(2650, 2665), range(2700, 3670), range(3700, 3790), range(4050, 4050)],
                'DK-85' => [range(2640, 2644), range(2670, 2690), range(4000, 4040), range(4060, 4990)],
                'DK-83' => [range(5000, 6870), range(7000, 7120), range(7173, 7260), range(7300, 7323)],
                'DK-82' => [range(6880, 6990), range(7130, 7171), range(7270, 7280), range(7330, 7680), range(7790, 7884), range(8000, 8990)],
                'DK-81' => [range(7700, 7770), range(7900, 7990), range(9000, 9990)],
            ];
            foreach ($ranges as $region => $lists) {
                foreach ($lists as $list) {
                    if (in_array($cipcode, $list)) {
                        return $region;
                    }
                }
            }
            return 'NA';
        };

        for ($postalCode = -5; $postalCode <= 10010; $postalCode++) {
            self::assertSame($original($postalCode), ISO::regionNumber($postalCode, 'Denmark'), (string) $postalCode);
        }
    }
}
