<?php
// 20261117 CL/SZ MB-163 Only a master user with the admin flag may use the Admin Panel; an empty grant list is no grant.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MasterAdminRightTest extends TestCase
{
    protected function setUp(): void
    {
        $helper = __DIR__ . '/../../../includes/stdFunc/masterAdminRight.php';
        if (is_file($helper)) {
            require_once $helper;
        }
    }

    /** @return array<string, array{0: string|null, 1: bool}> */
    public static function rights(): array
    {
        return [
            'admin, all accounts' => ['on,on,on,*', true],
            'admin, some accounts' => ['on,,,12,34', true],
            'admin flag only' => ['on', true],
            'not admin, all accounts' => [',on,on,*', false],
            'not admin, legacy zeros' => ['0,0,0,12', false],
            'empty grant list' => ['', false],
            'only commas' => [',,,', false],
            'no user row' => [null, false],
        ];
    }

    #[DataProvider('rights')]
    public function testAdminFlagDecidesAccess(?string $rettigheder, bool $expected): void
    {
        $this->assertTrue(function_exists('masterUserIsAdmin'), 'masterUserIsAdmin() is missing');
        $this->assertSame($expected, masterUserIsAdmin($rettigheder));
    }
}
