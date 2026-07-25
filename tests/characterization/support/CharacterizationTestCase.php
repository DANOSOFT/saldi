<?php
// tests/characterization/support/CharacterizationTestCase.php
//
// Base class for every DB-backed characterization suite.
//
// It carries the three things all of them need - a skip when the docker
// stack is not reachable, a connection to the throwaway tenant, and a
// Fixtures instance bound to the current fiscal year - so the suites
// themselves contain nothing but the behavior they pin.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/CharacterizationEnv.php';
require_once __DIR__ . '/Fixtures.php';

abstract class CharacterizationTestCase extends TestCase
{
    /** @var resource|\PgSql\Connection */
    protected static $tenant;
    protected static int $regnaar;
    protected static Fixtures $fx;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenantOnce();
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        $ra = CharacterizationEnv::one(self::$tenant, "SELECT max(kodenr) AS ra FROM grupper WHERE art = 'RA'");
        self::$regnaar = (int)$ra['ra'];
        self::$fx = new Fixtures(self::$tenant, self::$regnaar);
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant) {
            pg_close(self::$tenant);
        }
    }

    /** @param array<int,mixed> $params */
    protected static function rows(string $sql, array $params = []): array
    {
        return CharacterizationEnv::rows(self::$tenant, $sql, $params);
    }

    /** @param array<int,mixed> $params */
    protected static function one(string $sql, array $params = []): ?array
    {
        return CharacterizationEnv::one(self::$tenant, $sql, $params);
    }

    /**
     * Run one of the support/run_*.php child runners.
     *
     * @param list<int|string> $args
     * @return array{exit:int, stdout:string, stderr:string}
     */
    protected static function runChild(string $runner, array $args): array
    {
        return CharacterizationEnv::runChild(__DIR__ . '/' . $runner, $args);
    }

    /**
     * Run a child runner that reports its result as a single JSON object on
     * the last line of stdout, and fail with the child's own output when it
     * does not.
     *
     * @param list<int|string> $args
     */
    protected function runChildJson(string $runner, array $args): array
    {
        $res = self::runChild($runner, $args);
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $json = $lines !== [] ? json_decode(end($lines), true) : null;
        $this->assertIsArray(
            $json,
            "$runner must emit JSON on its last stdout line.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}"
        );
        return $json;
    }

    /** Sum a numeric column over a set of rows. */
    protected static function sumOf(array $rows, string $column): float
    {
        return array_sum(array_map(static fn($r) => (float)$r[$column], $rows));
    }
}
