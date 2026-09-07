<?php
// 20260907 CDX/LH Reject attribute and URI-scheme payloads while retaining encoded internal return URLs.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/includes/stdFunc/navStack.php';

final class NavReturnTargetRegressionTest extends TestCase
{
    public static function unsafeTargets(): array
    {
        return [
            ['x onclick=alert(1)'], ["x\tonclick=alert(1)"], ["x\nonclick=alert(1)"],
            ["java\tscript:alert(1)"], ["java\nscript:alert(1)"], ['javascript:alert(1)'],
            ['javascript&colon;alert(1)'], ['javascript&#58;alert(1)'], ['javascript&#58alert(1)'],
            ['java&Tab;script:alert(1)'], ['&#47;&#47;example.com'],
            ['https://example.com'], ['//example.com'], ["\\\\example.com"], ['/\\example.com'],
            ['data:text/html,payload'], ["x' onclick='alert(1)"], ['x`onclick=alert(1)'],
            ["x\0onclick=alert(1)"], ["x\x7f"], [['debitorkort.php']], [null], [42],
        ];
    }

    #[DataProvider('unsafeTargets')]
    public function testRejectsUnsafeOrNonStringReturnTargets($target): void
    {
        self::assertSame('', nav_sanitize_returside($target));
    }

    public static function safeTargets(): array
    {
        return [
            ['debitorkort.php?id=2110'], ['../includes/luk.php'],
            ['/saldi/debitor/rapport.php?konto_fra=100&konto_til=200'],
            ['varer.php?beskrivelse=blue%20chair&sort=varenr'],
            ['rapport.php?filter=a%26b%3Bc&returside=debitorkort.php%3Fid%3D2110'],
            ['../index/menu.php#finans'],
        ];
    }

    #[DataProvider('safeTargets')]
    public function testKeepsEncodedInternalTargets(string $target): void
    {
        self::assertSame($target, nav_sanitize_returside($target));
    }
}
