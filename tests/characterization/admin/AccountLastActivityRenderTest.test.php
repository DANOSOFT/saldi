<?php
// 20261113 CDX/NTR Reproduce the account-list date TypeError and cover every admin last-activity display.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccountLastActivityRenderTest extends TestCase
{
    private string $timezone;

    protected function setUp(): void
    {
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $helper = __DIR__ . '/../../../includes/stdFunc/lastActivityDate.php';
        if (is_file($helper)) {
            require_once $helper;
        }
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    /** @return array<string, array{0: int|string|null, 1: string}> */
    public static function activityValues(): array
    {
        return [
            'integer Unix seconds' => [1794560400, '13-11-2026'],
            'database Unix seconds' => ['1794560400', '13-11-2026'],
            'legacy datetime' => ['2026-11-13 09:00:00', '13-11-2026'],
            'legacy date' => ['2026-11-13', '13-11-2026'],
            'missing' => [null, '-'],
            'blank' => ['', '-'],
            'zero' => ['0', '-'],
            'invalid legacy value' => ['not-a-timestamp', '-'],
            'invalid calendar date' => ['2026-02-30', '-'],
        ];
    }

    #[DataProvider('activityValues')]
    public function testNormalAndEditAccountRowsRenderSafely($storedValue, string $expected): void
    {
        $source = file_get_contents(__DIR__ . '/../../../admin/vis_regnskaber.php');
        // Execute the real date-cell statements, without running the session/DB controller.
        preg_match_all('/^\s*print [^\n]*\$sidst\[\$x\][^\n]*;/m', $source, $statements);
        self::assertCount(2, $statements[0], 'Both normal and edit views are exercised.');
        $x = 0;
        $sidst = [$storedValue];
        foreach ($statements[0] as $statement) {
            ob_start();
            try {
                eval($statement);
            } finally {
                $html = ob_get_clean();
            }
            self::assertStringContainsString('>' . $expected . '<', $html);
        }
    }

    #[DataProvider('activityValues')]
    public function testBothAdminPanelViewsRenderSafely($storedValue, string $expected): void
    {
        $source = file_get_contents(__DIR__ . '/../../../admin/admin_panel.php');
        preg_match_all('/^\s*\$(sidst|sidst_val)\s*=[^\n]*;/m', $source, $statements, PREG_SET_ORDER);
        self::assertCount(2, $statements, 'Both admin-panel views are exercised.');
        $reg = ['sidst' => $storedValue];
        foreach ($statements as $statement) {
            eval($statement[0]);
            self::assertSame($expected, ${$statement[1]});
        }
    }
}
