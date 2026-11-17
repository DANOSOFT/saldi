<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/permissions.php';

/**
 * How includes/permissions.php reads an old rights string (brugere.rettigheder) when it turns it into
 * permission levels and when the first login gives the user a role (perm_migrate_users()).
 *
 * The old module gate in includes/online.php refuses a position only when its character sorts below '1'
 * (substr($rettigheder, $modulnr, 1) < '1'). The role migration must read a position the same way, or a
 * user loses modules at the first login. The e2e login (e2e/seed-user.mjs) has fifty '9's, for instance.
 */
final class permissionsLegacy extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function positions(): array
    {
        return [
            'zeros and ones kept' => ['0101', '0101'],
            'two (read) kept' => ['0212', '0212'],
            'any digit from 3 up is access' => ['3456789', '1111111'],
            'letters are access' => ['aZy', '111'],
            'space, comma, dot and star are no access' => [' ,.*', '0000'],
            'master-database format' => ['on,on,on,*', '1101101100'],
            'empty stays empty' => ['', ''],
        ];
    }

    #[DataProvider('positions')]
    public function test_each_position_is_read_like_the_old_module_gate(string $in, string $expected): void
    {
        self::assertSame($expected, perm_legacy_positions($in));
    }

    public function test_the_rule_matches_the_old_gate_for_every_byte(): void
    {
        for ($b = 0; $b < 256; $b++) {
            $c = chr($b);
            $oldGateAllows = !($c < '1');
            self::assertSame($oldGateAllows, perm_legacy_positions($c) !== '0', 'byte ' . $b);
        }
    }

    public function test_a_long_string_of_nines_normalises_to_all_rights(): void
    {
        self::assertSame(str_repeat('1', 16), perm_normalize_legacy(str_repeat('9', 50)));
    }

    public function test_normalising_pads_and_cuts_to_sixteen_positions(): void
    {
        self::assertSame('1010000000000000', perm_normalize_legacy('101'));
        self::assertSame(str_repeat('1', 16), perm_normalize_legacy(str_repeat('1', 20) . '0'));
        self::assertSame('', perm_normalize_legacy('   '));
    }

    public function test_nines_give_the_same_levels_as_ones(): void
    {
        self::assertSame(perm_levels_from_legacy(str_repeat('1', 16)), perm_levels_from_legacy(str_repeat('9', 16)));
    }

    public function test_a_two_is_read_and_a_zero_is_none(): void
    {
        $key = perm_key_for_modulnr(9);
        self::assertNotSame('', $key, 'position 9 (stock items) has a key');
        self::assertSame('read', perm_levels_from_legacy(str_repeat('0', 9) . '2' . str_repeat('0', 6))[$key]);
        self::assertSame('none', perm_levels_from_legacy(str_repeat('0', 16))[$key]);
        self::assertSame('write', perm_levels_from_legacy(str_repeat('0', 9) . '7' . str_repeat('0', 6))[$key]);
    }
}
