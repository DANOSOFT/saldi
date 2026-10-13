<?php
// SALDI Assist Guide rollout switch (includes/saldi_assist_guide.php).
//
// 20261012 CL/LH Created.

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
            'unset keeps v1'                 => ['', 'on', false],
            'off keeps v1'                   => ['off', 'on', false],
            'on loads v2 for every tenant'   => ['on', null, true],
            'pilot loads v2 for opted in'    => ['pilot', 'on', true],
            'pilot keeps v1 without the row' => ['pilot', null, false],
            'pilot keeps v1 for off'         => ['pilot', 'off', false],
            'unknown mode keeps v1'          => ['ON', 'on', false],
        ];
    }

    #[DataProvider('modes')]
    public function testOnlyOnAndOptedInPilotTenantsGetTheGuideWidget(string $mode, ?string $tenantSetting, bool $expected): void
    {
        self::assertSame($expected, saldi_assist_guide_enabled($mode, $tenantSetting));
    }
}
