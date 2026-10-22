<?php
// tests/shopify/BootstrapTest.php
//
// shopify_bootstrap() (SD-734) against a throwaway tenant registered in master regnskab:
// loads the tenant and the legacy globals under the cli SAPI and from SALDI_SHOPIFY_DB, rejects
// an unregistered or closed tenant, picks the one fiscal year that contains today and fails
// when several do. Skips when ShopifyEnv is not configured.
//
// History:
// 20261022 Sawaneh SD-734: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class BootstrapTest extends TestCase
{
    private static ?string $skip = null;
    private static string $db = '';
    private static int $regnskabId = 0;

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        try {
            self::$db = ShopifyEnv::testDb('_b');
            ShopifyEnv::cloneTenant(self::$db);
            self::$regnskabId = ShopifyEnv::registerTenant(self::$db);
            $run = ShopifyEnv::runSchema(self::$db, 'ensure');
            if ($run['exit'] !== 0) {
                throw new RuntimeException('schema ensure failed: ' . $run['stderr']);
            }
        } catch (Throwable $e) {
            self::tearDownAfterClass();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void
    {
        ShopifyEnv::unregisterTenants();
        ShopifyEnv::dropOwnedTenants();
    }

    protected function setUp(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
        $conn = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($conn, "DELETE FROM grupper WHERE art = 'RA'");
        $this->insertYear($conn, 1, (int)date('Y'), 'on');
        pg_close($conn);
    }

    private function insertYear($conn, int $kodenr, int $year, string $open, string $fromMonth = '01', string $toMonth = '12'): void
    {
        ShopifyEnv::rows(
            $conn,
            "INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ($1, $2, 'RA', $3, $4, $5, $6, $7)",
            ["Regnskabsår $year", $kodenr, $fromMonth, (string)$year, $toMonth, (string)$year, $open]
        );
    }

    public function testCliBootstrapLoadsTheTenantAndTheLegacyGlobals(): void
    {
        $run = ShopifyEnv::runBootstrap('cli', self::$db);
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        $r = $run['result'];
        $this->assertSame(self::$db, $r['context']['db']);
        $this->assertSame(self::$regnskabId, $r['context']['regnskab_id']);
        $this->assertSame(self::$db, $r['context']['regnskab']);
        $this->assertSame(1, $r['context']['regnaar']);
        $this->assertSame('shopify', $r['context']['brugernavn']);
        $this->assertSame('cli', $r['context']['sapi']);
        $this->assertSame(self::$db, $r['globals']['db']);
        $this->assertSame(self::$regnskabId, $r['globals']['db_id']);
        $this->assertSame(self::$regnskabId, $r['globals']['db_skriv_id']);
        $this->assertSame(1, $r['globals']['regnaar']);
        $this->assertSame('shopify', $r['globals']['brugernavn']);
        $this->assertSame(0, $r['globals']['bruger_id']);
        $this->assertSame(1, $r['globals']['sprog_id']);
        $this->assertTrue($r['globals']['webservice']);
        $this->assertSame('tools', $r['cwd']);
        $this->assertSame(3, substr_count($r['request_uri'], '/'));
        $this->assertSame('cli', $r['remote_addr']);
        $this->assertSame(self::$db, $r['connected_to']);
        $this->assertTrue($r['legacy_select_works']);
    }

    public function testWebBootstrapTakesTheTenantFromTheEnvironment(): void
    {
        $run = ShopifyEnv::runBootstrap('web', self::$db, ['SALDI_SHOPIFY_DB' => self::$db]);
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        $this->assertSame(self::$db, $run['result']['context']['db']);
        $this->assertSame(self::$db, $run['result']['connected_to']);

    }

    public function testWebBootstrapWithoutAnyConfigurationFails(): void
    {
        if (is_readable(ShopifyEnv::repoRoot() . '/includes/shopify/.ht_shopify_db')) {
            $this->markTestSkipped('includes/shopify/.ht_shopify_db exists on this machine, so the bootstrap is configured');
        }
        $none = ShopifyEnv::runBootstrap('web', self::$db, ['SALDI_SHOPIFY_DB' => '']);
        $this->assertSame(1, $none['exit']);
        $this->assertStringContainsString('no tenant configured', $none['result']['error']);
    }

    public function testAnUnregisteredTenantIsRejected(): void
    {
        $other = ShopifyEnv::testDb('_u');
        ShopifyEnv::cloneTenant($other);
        $run = ShopifyEnv::runBootstrap('cli', $other);
        $this->assertSame(1, $run['exit'], $run['stderr']);
        $this->assertSame('ShopifyBootstrapError', $run['result']['class']);
        $this->assertStringContainsString('not registered', $run['result']['error']);

        $bad = ShopifyEnv::runBootstrap('web', self::$db, ['SALDI_SHOPIFY_DB' => 'saldi_shopify_test_x; drop']);
        $this->assertSame(1, $bad['exit']);
        $this->assertStringContainsString('invalid tenant name', $bad['result']['error']);
    }

    public function testAClosedTenantIsRejected(): void
    {
        $master = ShopifyEnv::connect(ShopifyEnv::masterDb());
        try {
            ShopifyEnv::rows($master, "UPDATE regnskab SET lukket = 'on' WHERE id = $1", [self::$regnskabId]);
            $run = ShopifyEnv::runBootstrap('cli', self::$db);
            $this->assertSame(1, $run['exit'], $run['stderr']);
            $this->assertStringContainsString('is closed', $run['result']['error']);

            ShopifyEnv::rows($master, "UPDATE regnskab SET lukket = '', lukkes = current_date WHERE id = $1", [self::$regnskabId]);
            $byDate = ShopifyEnv::runBootstrap('cli', self::$db);
            $this->assertSame(1, $byDate['exit'], 'a reached closure date closes the tenant like online.php does');
            $this->assertStringContainsString('is closed', $byDate['result']['error']);

            ShopifyEnv::rows($master, "UPDATE regnskab SET lukkes = current_date + 1 WHERE id = $1", [self::$regnskabId]);
            $this->assertSame(0, ShopifyEnv::runBootstrap('cli', self::$db)['exit'], 'a future closure date is open');
            ShopifyEnv::rows($master, "UPDATE regnskab SET lukkes = '2099-12-31' WHERE id = $1", [self::$regnskabId]);
            $this->assertSame(0, ShopifyEnv::runBootstrap('cli', self::$db)['exit'], 'the never-closes placeholder is open');
        } finally {
            ShopifyEnv::rows($master, "UPDATE regnskab SET lukket = '', lukkes = NULL WHERE id = $1", [self::$regnskabId]);
            pg_close($master);
        }
    }

    public function testADeletedFiscalYearIsIgnored(): void
    {
        $conn = ShopifyEnv::connect(self::$db);
        $this->insertYear($conn, 2, (int)date('Y'), 'on');
        ShopifyEnv::rows($conn, "UPDATE grupper SET box10 = 'on' WHERE art = 'RA' AND kodenr = 2");
        pg_close($conn);
        $run = ShopifyEnv::runBootstrap('cli', self::$db);
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        $this->assertSame(1, $run['result']['context']['regnaar']);
    }

    public function testSeveralFiscalYearsContainingTodayIsAnError(): void
    {
        $conn = ShopifyEnv::connect(self::$db);
        $this->insertYear($conn, 2, (int)date('Y'), 'on');
        pg_close($conn);
        $run = ShopifyEnv::runBootstrap('cli', self::$db);
        $this->assertSame(1, $run['exit'], $run['stderr']);
        $this->assertStringContainsString('several fiscal years', $run['result']['error']);
        $this->assertStringContainsString('1, 2', $run['result']['error']);
    }

    public function testNewestOpenYearIsUsedWhenNoneContainsToday(): void
    {
        $conn = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($conn, "DELETE FROM grupper WHERE art = 'RA'");
        $this->insertYear($conn, 9, (int)date('Y') - 2, 'on');
        $this->insertYear($conn, 10, (int)date('Y') - 1, 'on');
        $this->insertYear($conn, 11, (int)date('Y') - 3, '');
        $this->insertYear($conn, 12, (int)date('Y') + 3, 'on');
        ShopifyEnv::rows($conn, "UPDATE grupper SET box10 = 'on' WHERE art = 'RA' AND kodenr = 12");
        pg_close($conn);
        $run = ShopifyEnv::runBootstrap('cli', self::$db);
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        $this->assertSame(10, $run['result']['context']['regnaar'], 'newest open, not-deleted year by its dates, not by kodenr text order');

        $conn = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($conn, "DELETE FROM grupper WHERE art = 'RA'");
        pg_close($conn);
        $none = ShopifyEnv::runBootstrap('cli', self::$db);
        $this->assertSame(1, $none['exit']);
        $this->assertStringContainsString('no open fiscal year', $none['result']['error']);
    }
}
