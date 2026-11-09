<?php
// SALDI Assist Guide rollout switch (includes/saldi_assist_guide.php).
//
// 20261012 CL/LH Created.
// 20261028 CL/LH The Guide is the default; widget URL follows the switch.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/saldi_assist_guide.php';

final class saldiAssistGuide extends TestCase
{
    /**
     * @return array<string, array{string, ?string, bool}>
     */
    public static function modes(): array
    {
        return [
            'unset loads the Guide'          => ['', null, true],
            'off keeps v1'                   => ['off', 'on', false],
            'on loads v2 for every tenant'   => ['on', null, true],
            'pilot loads v2 for opted in'    => ['pilot', 'on', true],
            'pilot keeps v1 without the row' => ['pilot', null, false],
            'pilot keeps v1 for off'         => ['pilot', 'off', false],
            'unknown mode keeps v1'          => ['ON', 'on', false],
        ];
    }

    #[DataProvider('modes')]
    public function testGuideWidgetPerMode(string $mode, ?string $tenantSetting, bool $expected): void
    {
        self::assertSame($expected, saldi_assist_guide_enabled($mode, $tenantSetting));
    }

    public function testWidgetUrlFollowsTheSwitchUnlessOverridden(): void
    {
        self::assertSame(SALDI_ASSIST_GUIDE_WIDGET_URL, saldi_assist_widget_url('', true));
        self::assertSame(SALDI_ASSIST_V1_WIDGET_URL, saldi_assist_widget_url('', false));
        self::assertSame('http://localhost:3000/widget.js', saldi_assist_widget_url('http://localhost:3000/widget.js', true));
    }
}
