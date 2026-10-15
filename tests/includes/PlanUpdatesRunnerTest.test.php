<?php

// 20260925 CL/LH Cover the ai/plan step runner: watermark, one select when up to date, resume after failure.

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/planUpdatesRunner.php';

final class PlanUpdatesRunnerTest extends TestCase
{
    /** @var list<string> */
    private array $selects = [];
    /** @var list<string> */
    private array $writes = [];
    private ?string $watermark = null;
    /** @var list<string> */
    private array $ran = [];

    protected function setUp(): void
    {
        $this->selects = [];
        $this->writes = [];
        $this->watermark = null;
        $this->ran = [];
    }

    public function testNoStepsRunsNoQueries(): void
    {
        $result = $this->runSteps([]);

        self::assertSame([], $result['applied']);
        self::assertSame([], $this->selects);
        self::assertSame([], $this->writes);
    }

    public function testFreshTenantAppliesEveryStepThenCostsOneSelect(): void
    {
        $steps = $this->steps(['s01_a', 's02_b']);

        $first = $this->runSteps($steps);
        self::assertSame(['s01_a', 's02_b'], $first['applied']);
        self::assertSame(['s01_a', 's02_b'], $this->ran);
        self::assertSame('s02_b', $this->watermark);
        self::assertStringStartsWith('INSERT INTO settings', $this->writes[0]);
        self::assertStringStartsWith('UPDATE settings', $this->writes[1]);

        $this->selects = [];
        $this->writes = [];
        $this->ran = [];
        $second = $this->runSteps($steps);
        self::assertSame([], $second['applied']);
        self::assertCount(1, $this->selects);
        self::assertSame([], $this->writes);
        self::assertSame([], $this->ran);
    }

    public function testOnlyStepsAfterTheWatermarkRun(): void
    {
        $this->watermark = 's01_a';

        $result = $this->runSteps($this->steps(['s01_a', 's02_b', 's03_c']));

        self::assertSame(['s02_b', 's03_c'], $result['applied']);
        self::assertSame('s03_c', $this->watermark);
        self::assertStringStartsWith('UPDATE settings', $this->writes[0]);
    }

    public function testFailingStepStopsWithoutAdvancingAndIsRetried(): void
    {
        $fail = true;
        $steps = $this->steps(['s01_a']);
        $steps['s02_b'] = function (string $dbType) use (&$fail): void {
            if ($fail) {
                throw new RuntimeException('boom');
            }
            $this->ran[] = 's02_b';
        };
        $steps += $this->steps(['s03_c']);

        $logged = ini_set('error_log', '/dev/null');
        $first = $this->runSteps($steps);
        ini_set('error_log', (string) $logged);
        self::assertSame(['s01_a'], $first['applied']);
        self::assertSame('s02_b', $first['failed']);
        self::assertSame('s01_a', $this->watermark);

        $fail = false;
        $second = $this->runSteps($steps);
        self::assertSame(['s02_b', 's03_c'], $second['applied']);
        self::assertNull($second['failed']);
        self::assertSame('s03_c', $this->watermark);
    }

    public function testUnknownWatermarkReplaysEveryStep(): void
    {
        $this->watermark = 'renamed_step';

        $result = $this->runSteps($this->steps(['s01_a', 's02_b']));

        self::assertSame(['s01_a', 's02_b'], $result['applied']);
        self::assertSame('s02_b', $this->watermark);
    }

    public function testStepsReceiveTheDatabaseType(): void
    {
        $seen = null;
        $this->runSteps(['s01_a' => function (string $dbType) use (&$seen): void {
            $seen = $dbType;
        }], 'mysqli');

        self::assertSame('mysqli', $seen);
    }

    /**
     * @param list<string> $names
     * @return array<string, callable(string): void>
     */
    private function steps(array $names): array
    {
        $steps = [];
        foreach ($names as $name) {
            $steps[$name] = function (string $dbType) use ($name): void {
                $this->ran[] = $name;
            };
        }
        return $steps;
    }

    /**
     * @param array<string, callable(string): void> $steps
     * @return array{applied: list<string>, failed: string|null}
     */
    private function runSteps(array $steps, string $dbType = 'postgresql'): array
    {
        return plan_updates_run(
            $steps,
            $dbType,
            function (string $sql) {
                $this->selects[] = $sql;
                return $this->watermark === null ? false : ['var_value' => $this->watermark];
            },
            function (string $sql): void {
                $this->writes[] = $sql;
                if (preg_match("/(?:SET var_value = |'system', )'([^']+)'/", $sql, $m)) {
                    $this->watermark = $m[1];
                }
            }
        );
    }
}
