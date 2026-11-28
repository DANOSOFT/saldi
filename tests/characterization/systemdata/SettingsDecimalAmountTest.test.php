<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * A settings field of type 'decimal' takes a Danish amount with thousands separators (testlab round 2,
 * company-masterdata, 2026-11-05).
 *
 * The old employee card (systemdata/ansatte_save.php) read Løn and Løntillæg through usdecimal(), which drops the
 * dots and turns the comma into a dot: 32.456,78 was stored as 32456.78. The card moved into the settings hub
 * (organisation.employees.loen/extraloen, type 'decimal'), whose st_posted_to_raw() took only digits with one '.' or
 * ',' as the decimal separator. Løn 32.456,78 and Løntillæg 1.234,50 got "Ret fejlene nedenfor. Intet er gemt. Løn:
 * Skal være et tal", and nothing on the card was saved. The same check runs for every 'decimal' field in the hub.
 *
 * Pins, for every 'decimal' field in the registry (the employee's and the till's fields included): a Danish amount
 * whose dots cannot be a decimal separator is stored as usdecimal() stores it; one '.' or ',' is still the decimal
 * separator (2.5 is 2.5, 1.234 is 1.234); a number with misplaced separators is still refused with 5732. The
 * database layer is a double that serves one employee and one till, and refuses any write.
 *
 * History:
 * 20261105 CL/LH Created.
 */
#[RunTestsInSeparateProcesses]
final class SettingsDecimalAmountTest extends TestCase
{
    /**
     * Every 'decimal' field of the settings forms, as settingsSection.php gets it.
     *
     * @return array<string, array<string, mixed>> key => definition
     */
    private static function decimalFields(): array
    {
        eval('
            function db_escape_string($s) { return addslashes((string) $s); }
            function db_select($q, $where = "") {
                $rows = [];
                if (strpos($q, "select id from adresser where art = \'S\'") === 0) {
                    $rows = [["id" => "1"]];
                } elseif (strpos($q, "select a.id, a.navn, a.nummer") === 0) {
                    $rows = [["id" => "7", "navn" => "Søren Kjærgård", "nummer" => "1", "lukket" => "", "uid" => null]];
                } elseif (strpos($q, "select box1 from grupper where art = \'POS\' and kodenr = \'1\'") === 0) {
                    $rows = [["box1" => "1"]];
                }
                return new \ArrayIterator($rows);
            }
            function db_fetch_array($r) {
                if (!$r->valid()) {
                    return false;
                }
                $row = $r->current();
                $r->next();
                return $row;
            }
            function db_modify($q, $where = "") { throw new \RuntimeException("a settings value was written: " . $q); }
            function transaktion($what) { throw new \RuntimeException("a settings save started a write (" . $what . ")"); }
            function findtekst($key, $sprog_id = 1) { return explode("|", (string) $key, 2)[0]; }
            function perm_can($key, $level = "read") { return true; }
        ');
        require_once dirname(__DIR__, 3) . '/includes/settings/components.php';
        require_once dirname(__DIR__, 3) . '/includes/stdFunc/usDecimal.php';
        $fields = [];
        foreach (getSettingDefinitions() as $key => $def) {
            if ($def['type'] === 'decimal') {
                $fields[$key] = $def + ['key' => $key];
            }
        }
        return $fields;
    }

    /** The stored string of a typed value, or the error text id when it is refused. */
    private static function stored(array $def, string $typed): string
    {
        $res = st_posted_to_raw($def, $typed, []);
        return $res['error'] !== null ? 'error ' . $res['error'] : $res['raw'];
    }

    /** The stored string as a number with a dot, for a field that keeps the Danish comma ('decimal_comma'). */
    private static function asNumber(array $def, string $raw): string
    {
        return empty($def['decimal_comma']) ? $raw : str_replace(',', '.', $raw);
    }

    public function test_the_employee_card_stores_the_labs_danish_salaries(): void
    {
        $fields = self::decimalFields();
        $loen = $fields['organisation.employees.loen.7'];
        $extraloen = $fields['organisation.employees.extraloen.7'];

        self::assertSame('32456.78', self::stored($loen, '32.456,78'), 'Løn 32.456,78 is stored as the old card stored it');
        self::assertSame('1234.50', self::stored($extraloen, '1.234,50'), 'Løntillæg 1.234,50 is stored as the old card stored it');
        self::assertSame('32456.78', self::stored($loen, '32456,78'), 'the amount without thousands separators is stored as before');
    }

    /** @return array<string, array{string, string}> typed amount, the number it is stored as */
    public static function danishAmounts(): array
    {
        return [
            'thousands and a decimal comma (the lab\'s Løn)' => ['32.456,78', '32456.78'],
            'thousands and a decimal comma (the lab\'s Løntillæg)' => ['1.234,50', '1234.50'],
            'two thousands separators, no decimals' => ['1.234.567', '1234567'],
            'two thousands separators and three decimals' => ['12.345.678,125', '12345678.125'],
            'a negative amount' => ['-1.234,5', '-1234.5'],
        ];
    }

    #[DataProvider('danishAmounts')]
    public function test_every_decimal_field_takes_a_danish_amount_as_usdecimal_does(string $typed, string $number): void
    {
        $fields = self::decimalFields();
        self::assertArrayHasKey('organisation.employees.loen.7', $fields, 'the employee\'s Løn is among the fields');
        self::assertArrayHasKey('pos.tills.mobile_zoom.1', $fields, 'a field that keeps the Danish comma is among the fields');

        foreach ($fields as $key => $def) {
            $raw = self::stored($def, $typed);
            self::assertSame(empty($def['decimal_comma']) ? $number : str_replace('.', ',', $number), $raw, "$key: $typed is stored");
            self::assertEqualsWithDelta((float) usdecimal($typed, 3), (float) self::asNumber($def, $raw), 0.0000001, "$key: $typed is the number usdecimal() reads");
        }
    }

    /** @return array<string, array{string, string}> typed value, the number it is stored as */
    public static function oneSeparator(): array
    {
        return [
            'a decimal point' => ['2.5', '2.5'],
            'a decimal comma' => ['2,5', '2.5'],
            'one dot and three digits stays a decimal point' => ['1.234', '1.234'],
            'a negative decimal comma' => ['-0,125', '-0.125'],
            'a whole number' => ['32456', '32456'],
        ];
    }

    #[DataProvider('oneSeparator')]
    public function test_one_separator_is_still_the_decimal_separator(string $typed, string $number): void
    {
        foreach (self::decimalFields() as $key => $def) {
            self::assertSame(empty($def['decimal_comma']) ? $number : str_replace('.', ',', $number), self::stored($def, $typed), "$key: $typed is stored");
        }
    }

    public function test_an_empty_field_is_not_refused(): void
    {
        foreach (self::decimalFields() as $key => $def) {
            $res = st_posted_to_raw($def, '', []);
            self::assertNull($res['error'], "$key: an empty field is saved");
            self::assertSame(isset($def['value_map']['']) ? $def['value_map'][''] : '', $res['raw'], "$key: an empty field is stored empty, or as its value_map says");
        }
    }

    /** @return array<string, array{string}> */
    public static function notANumber(): array
    {
        return [
            'a thousands group of two digits' => ['32.45,78'],
            'four digits before a thousands separator' => ['3245.678,9'],
            'English thousands separators' => ['1,234.56'],
            'dots in a group of two digits' => ['1.234.56'],
            'a decimal comma with no decimals' => ['32.456,'],
            'no digit before the comma' => [',5'],
            'a letter' => ['12a'],
        ];
    }

    #[DataProvider('notANumber')]
    public function test_a_number_with_misplaced_separators_is_still_refused(string $typed): void
    {
        foreach (self::decimalFields() as $key => $def) {
            self::assertSame('error 5732', self::stored($def, $typed), "$key: $typed is refused as \"Skal være et tal\"");
        }
    }
}
