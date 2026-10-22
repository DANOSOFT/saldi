<?php
// tests/shopify/DbAdapterTest.php
//
// shopify_db_rows() and shopify_db_transaction() (SD-734) on a throwaway tenant: parameters are
// bound and never interpolated, a non-scalar parameter is refused, a nested transaction uses a
// savepoint so an inner failure rolls back only the inner work, a failing outer transaction
// leaves nothing, and an aborted transaction is refused. Skips when ShopifyEnv is not configured.
//
// History:
// 20261022 Sawaneh SD-734: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class DbAdapterTest extends TestCase
{
    private static ?string $skip = null;
    private static string $db = '';

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        try {
            self::$db = ShopifyEnv::testDb('_d');
            ShopifyEnv::cloneTenant(self::$db);
            ShopifyEnv::registerTenant(self::$db);
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
        ShopifyEnv::rows($conn, 'DELETE FROM shopify_shops');
        ShopifyEnv::rows($conn, "DELETE FROM grupper WHERE art = 'RA'");
        ShopifyEnv::rows($conn, "INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('RA', 1, 'RA', '01', $1, '12', $1, 'on')", [date('Y')]);
        pg_close($conn);
    }

    private function runMode(string $mode): array
    {
        $run = ShopifyEnv::runBootstrap($mode, self::$db);
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        $this->assertNotNull($run['result'], $run['stdout']);
        return $run['result'];
    }

    public function testParametersAreBoundNeverInterpolated(): void
    {
        $r = $this->runMode('rows');
        $this->assertSame($r['values']['quote'], $r['returning'][0]['navn']);
        $this->assertSame('7', $r['returning'][0]['afd']);
        $this->assertSame('t', $r['returning'][0]['is_primary']);
        $this->assertSame('2026-01-02 03:04:05', $r['returning'][0]['go_live_at']);
        $this->assertSame(['e', 'q', 'u'], array_column($r['back'], 'shop_key'));
        $this->assertSame($r['values']['quote'], $r['back'][1]['navn']);
        $this->assertSame($r['values']['unicode'], $r['back'][2]['navn']);
        $this->assertNull($r['back'][2]['afd']);
        $this->assertSame('', $r['back'][0]['navn']);
        $this->assertSame('f', $r['back'][0]['is_primary']);
        $this->assertSame([], $r['none']);
        $this->assertSame('$2', $r['literal']);
        $this->assertSame(3, $r['count'], 'the semicolon in the bound value did not run a second statement');
    }

    public function testANonScalarParameterIsRefusedBeforeAnythingIsSent(): void
    {
        $r = $this->runMode('badparam');
        $this->assertTrue($r['refused']);
        $this->assertStringContainsString('parameter $1', $r['message']);
        $this->assertSame(0, $r['count']);
    }

    public function testNestedTransactionRollsBackOnlyTheInnerWork(): void
    {
        $r = $this->runMode('tx');
        $this->assertNotNull($r['inner_error']);
        $this->assertStringContainsString('shopify_shops_shop_key_key', $r['inner_error']);
        $this->assertTrue($r['status_after_inner_intrans'], 'the outer transaction survives the inner failure');
        $this->assertSame(42, $r['returned']);
        $this->assertSame(['after', 'outer'], $r['keys']);
        $this->assertTrue($r['status_end_idle']);
    }

    public function testAFailingOuterTransactionLeavesNothing(): void
    {
        $r = $this->runMode('tx_fail');
        $this->assertSame('outer failure', $r['caught']);
        $this->assertSame(0, $r['count']);
        $this->assertTrue($r['status_end_idle']);
    }

    public function testAnOperationThatSwallowsAFailedStatementCannotCommit(): void
    {
        $r = $this->runMode('tx_unhandled');
        $this->assertTrue($r['threw'], 'COMMIT in an aborted transaction answers ROLLBACK; the wrapper must not report success');
        $this->assertStringContainsString('failed and was not handled', $r['message']);
        $this->assertSame(0, $r['count']);
        $this->assertTrue($r['status_end_idle']);
    }

    public function testAnOperationThatEndsTheTransactionItselfCannotCommit(): void
    {
        $r = $this->runMode('tx_early_commit');
        $this->assertTrue($r['threw']);
        $this->assertStringContainsString('ended inside the operation', $r['message']);
    }

    public function testDateTimeParametersLandAsWallClockTimeInPhpsZone(): void
    {
        $r = $this->runMode('datetime');
        $this->assertSame($r['expected'], $r['rows'][1]['go_live_at'], 'a UTC instant is stored in the PHP zone, like now()');
        $this->assertSame($r['rows'][1]['go_live_at'], $r['rows'][0]['go_live_at'], 'the same instant given in another zone stores the same value');
    }

    public function testAFailedStatementNeverExposesBoundValues(): void
    {
        $r = $this->runMode('errors');
        foreach (['unique', 'check', 'missing'] as $case) {
            $this->assertStringNotContainsString('SECRET_TOKEN', $r[$case]['message'], "$case error leaks the bound value");
            $this->assertStringNotContainsString('DETAIL', $r[$case]['message']);
        }
        $this->assertSame('23505', $r['unique']['sqlstate']);
        $this->assertSame('shopify_shops_shop_key_key', $r['unique']['constraint']);
        $this->assertStringContainsString('shopify_shops_shop_key_key', $r['unique']['message']);
        $this->assertSame('23514', $r['check']['sqlstate']);
        $this->assertSame('shopify_shops_legacy_slot_check', $r['check']['constraint']);
        $this->assertSame('42P01', $r['missing']['sqlstate']);
        $this->assertSame(1, $r['usable_after'], 'the connection stays usable after failed statements');
    }

    public function testANulByteIsRefusedBeforeAnythingIsSent(): void
    {
        $r = $this->runMode('nul');
        $this->assertStringContainsString('NUL byte', $r['param']);
        $this->assertStringContainsString('parameter $3', $r['param']);
        $this->assertStringContainsString('NUL byte', $r['sql']);
        $this->assertSame(0, $r['count'], 'nothing was stored, so no silent truncation');
    }

    public function testWorkInsideAnAbortedTransactionIsRefused(): void
    {
        $r = $this->runMode('tx_aborted');
        $this->assertTrue($r['refused']);
        $this->assertStringContainsString('aborted', $r['message']);
    }
}
