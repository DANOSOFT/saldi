<?php
// 20261113 CDX/NTR Reproduce the account-list date TypeError and cover every admin last-activity display.
// 20261113 CL/ASR The account list test follows the redesigned page (#776): one row loop draws list and edit mode, vr_ts() reads the stored value, and the cell shows the date as a tooltip or a dash.

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
        // Execute the real helpers and date-cell statements, without running the session/DB controller.
        // They run in their own namespace, so the page's vr_* helpers cannot clash with other tests.
        if (!function_exists('AccountListRender\vr_ts')) {
            foreach (['vr_t', 'vr_ts', 'vr_rel'] as $name) {
                self::assertSame(1, preg_match('/^function ' . $name . '\(.*?^}$/ms', $source, $fn), "$name() is defined.");
                eval('namespace AccountListRender; ' . $fn[0]);
            }
        }
        preg_match_all('/^\s*\$sidst\[\$x\] = vr_ts\(\$sidst\[\$x\]\);$/m', $source, $normalize);
        preg_match_all('/^\s*print [^\n]*\$sidst\[\$x\][^\n]*;/m', $source, $statements);
        self::assertCount(1, $normalize[0], 'The row loop reads the stored value through vr_ts().');
        self::assertCount(1, $statements[0], 'One row loop draws both the list and edit mode.');
        $x = 0;
        $sidst = [$storedValue];
        ob_start();
        try {
            eval('namespace AccountListRender; ' . $normalize[0][0] . ' ' . $statements[0][0]);
        } finally {
            $html = ob_get_clean();
        }
        if ($expected === '-') {
            self::assertStringContainsString('>&ndash;<', $html);
            self::assertStringNotContainsString('title=', $html);
        } else {
            self::assertStringContainsString('title="' . $expected . '"', $html);
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
