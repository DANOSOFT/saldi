<?php
// tests/shopify/AlarmsLoggingTest.php
//
// Call log, redaction and purge, the error list with its hourly alarm, job alarms from the third
// failure, and the login watchdog (SD-738), on a throwaway tenant registered in master. Mails go
// to a recording transport in the child (support/run_alarms.php). Skips when ShopifyEnv is not
// configured.
//
// History:
// 20261028 Sawaneh SD-738: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class AlarmsLoggingTest extends TestCase
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
            self::$db = ShopifyEnv::testDb('_a');
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
        if (self::$db !== '') {
            ShopifyEnv::removeTempDir(self::$db);
        }
        ShopifyEnv::unregisterTenants();
        ShopifyEnv::dropOwnedTenants();
    }

    protected function setUp(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
        $conn = ShopifyEnv::connect(self::$db);
        foreach (['shopify_jobs', 'shopify_errors', 'shopify_call_log'] as $table) {
            ShopifyEnv::rows($conn, "DELETE FROM $table");
        }
        ShopifyEnv::rows($conn, "DELETE FROM settings WHERE var_grp = 'shopify_state' OR (var_grp = 'shopify' AND var_name = 'support_email')");
        ShopifyEnv::rows($conn, "DELETE FROM grupper WHERE art = 'RA'");
        ShopifyEnv::rows($conn, "INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('RA', 1, 'RA', '01', $1, '12', $1, 'on')", [date('Y')]);
        pg_close($conn);
    }

    private function runMode(string $mode, string $arg = ''): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_alarms.php', [$mode, self::$db, $arg]));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        $this->assertNotNull($run['result'], $run['stdout']);
        return $run['result'];
    }

    public function testRedactionKeepsSecretsAndTokensOutOfTheCallLog(): void
    {
        $r = $this->runMode('calllog');
        $token = $r['token'];
        $this->assertSame('header X-Shopify-Access-Token: [redacted] and [redacted] and [redacted]', $r['redact']['string'], 'given secrets, shpat_ and bearer tokens; a 2-character secret is ignored');
        $this->assertSame(['a' => ['accessToken' => '[redacted]', 'Password' => '[redacted]', 'webhook_hmac' => '[redacted]', 'lock_token' => '', 'note' => 'n [redacted]'],
            'list' => ['[redacted]']], $r['redact']['nested']);
        $this->assertSame('{"secret":"[redacted]","data":{},"n":5}', $r['redact']['object'], 'objects stay objects');
        $this->assertSame([5, null, true], $r['redact']['scalars']);
        $this->assertSame('GET /x?access_token=[redacted]&y=1 X-Shopify-Access-Token: [redacted] client_secret=[redacted], lock_token is fine', $r['redact']['assigned'], 'secrets written as key=value or as a header line');

        $row = $r['row'];
        $this->assertSame((string)$r['shop_id'], $row['shop_id'], 'the shop key is resolved to its id');
        $this->assertSame(['out', 'orderCreate', '41', '5', '77', 'gid://shopify/Order/9', 'req-1', '200', '12', '1988', '81'],
            [$row['direction'], $row['operation'], $row['job_id'], $row['event_row_id'], $row['ordre_id'], $row['order_gid'], $row['request_id'],
            $row['http_status'], $row['cost_actual'], $row['cost_available'], $row['ms']]);
        foreach ([json_encode($row), $r['decoded']] as $where) {
            $this->assertStringNotContainsString($token, $where);
            $this->assertStringNotContainsString('shpat_', $where);
        }
        $this->assertStringContainsString('"note":"token [redacted]"', $r['decoded'], 'the stored body is redacted again here');

        $client = $r['client'];
        $this->assertTrue($client['ok']);
        $this->assertNotNull($client['row'], "the client's default log writes the call log once logging.php is loaded");
        $this->assertSame(['shop', '200', '78', (string)$r['shop_id']], [$client['row']['operation'], $client['row']['http_status'], $client['row']['ordre_id'], $client['row']['shop_id']]);
        $this->assertStringNotContainsString('test-token-not-a-secret', json_encode($client['row']) . $client['decoded'], 'the access token never reaches the row');
        $this->assertStringNotContainsString('shpat_', $client['decoded']);
        $this->assertSame(['10', '10', '1990', '100'], [$client['row']['cost_requested'], $client['row']['cost_actual'], $client['row']['cost_available'], $client['row']['cost_restore_rate']], 'every cost field from the client');
        $this->assertSame([['200', '50', null], ['200', '10', '10']], $r['throttled'], 'a throttled attempt keeps its actual cost empty, not the requested one');
        $this->assertSame(['insert_failed' => true, 'caller_goes_on' => '1'], $r['inside_tx'], "a failed insert does not abort the caller's transaction");
    }

    public function testPurgeKeepsExactlyNinetyDays(): void
    {
        $r = $this->runMode('purge');
        $this->assertSame(1, $r['deleted']);
        $this->assertSame(['exact', 'young'], $r['left'], 'a row exactly 90 days old is kept, one second more is purged');
        $this->assertSame(['outcome' => 'done'], $r['job']);
    }

    public function testARepeatedErrorCountsOnItsOpenRowAndAlarmsAtMostHourly(): void
    {
        $r = $this->runMode('errors');
        [$a, $a2, $other, $info, $fresh] = $r['ids'];
        $this->assertSame($a, $a2, 'the same shop, kind and ref is the same open row');
        $this->assertSame('unknown SKU [redacted]', $r['row1']['message']);
        $this->assertSame('{"api_key": "[redacted]", "ordre_id": 7}', $r['row1']['correlation']);
        $this->assertSame(['occurrences' => 2, 'message' => 'unknown SKU again', 'alarmed' => 't'],
            ['occurrences' => (int)$r['row']['occurrences'], 'message' => $r['row']['message'], 'alarmed' => $r['row']['alarmed']]);
        $this->assertSame([['errors' => 1, 'mails' => 1], ['errors' => 1, 'mails' => 1], ['errors' => 1, 'mails' => 2], ['errors' => 3, 'mails' => 3], ['errors' => 4, 'mails' => 4]], $r['counts'],
            'one alarm, none within the hour, one after it; another shop is another error; info never alarms; a reopened error alarms');
        $this->assertNotSame($a, $other);
        $this->assertSame([true, false], $r['resolved']);
        $this->assertNotSame($a, $fresh, 'a resolved error opens a fresh row');
        $this->assertSame(1, $r['fresh_occurrences']);
        $this->assertSame(0, $r['rolled_back'], "an error opened in the caller's transaction rolls back with it");
        $this->assertTrue($r['key']);
        $mail = $r['mail_jobs'][0];
        $this->assertSame(5, $mail['priority']);
        $this->assertSame('support@saldi.dk', $mail['payload']['to'], 'the default until support_email is set');
        $this->assertStringContainsString(': order.sku gid://shopify/Order/1', $mail['payload']['subject']);
        $this->assertStringContainsString('Occurrences: 1', $mail['payload']['body']);
        $this->assertStringNotContainsString('shpat_', json_encode($r['mail_jobs']));
    }

    public function testAFailingJobAlarmsOnItsThirdFailureThrottledHourly(): void
    {
        $r = $this->runMode('jobs');
        $this->assertSame([
            ['result' => 'failed', 'errors' => 0, 'mails' => 0],
            ['result' => 'failed', 'errors' => 0, 'mails' => 0],
            ['result' => 'failed', 'errors' => 1, 'mails' => 1],
            ['result' => 'failed', 'errors' => 1, 'mails' => 1],
            ['result' => 'failed', 'errors' => 1, 'mails' => 2],
        ], $r['steps'], 'no alarm on failures 1-2, one on the third, none within the hour, one after it');
        $e = $r['import_error'];
        $this->assertSame(['job.failed', 'order.import:hml:order-1', 3, 'error'], [$e['kind'], $e['ref'], (int)$e['occurrences'], $e['severity']]);
        $this->assertStringContainsString('order.import attempt 5 failed: RuntimeException: fail [redacted]', $e['message']);
        $correlation = json_decode($e['correlation'], true);
        $this->assertSame([77, 'gid://shopify/Order/1'], [$correlation['ordre_id'], $correlation['order_gid']]);
        $this->assertArrayHasKey('job_id', $correlation);
        $this->assertSame('RuntimeException: fail [redacted]', $r['last_error'], 'the stored job error is redacted');
        $this->assertSame(['result' => 'dead', 'errors' => 2, 'mails' => 3], $r['dead'], 'a dead job alarms at once');
        $this->assertSame(['failed', 'failed', 'failed'], array_column($r['mail_steps'], 'result'));
        $this->assertSame(3, end($r['mail_steps'])['mails'], 'a failing alarm mail never queues mail about itself');
        $this->assertSame(['severity' => 'info'], $r['mail_error']);
        $this->assertSame(['done', 'done', 'done'], $r['sent']);
        $this->assertSame(['alarm@example.test'], array_values(array_unique(array_column($r['mails'], 'to'))), 'support_email from settings');
        $this->assertStringNotContainsString('shpat_', json_encode($r));
        $this->assertSame(['done', 't'], $r['succeeds'], "the job's error is resolved once it succeeds");
        $this->assertSame(1, $r['crash']['recovered']);
        $this->assertStringContainsString('order.tags attempt 3 failed: lease expired', (string)$r['crash']['error'], 'a crash on the third attempt alarms too');
    }

    public function testTheWatchdogMailsAndSpawnsOnceForAStaleHeartbeat(): void
    {
        $r = $this->runMode('watchdog');
        $none = ['stale' => false, 'spawned' => 0, 'mailed' => 0];
        $this->assertSame([
            'missing' => $none,
            'fresh' => $none,
            'ten_minutes' => $none,
            'stale' => ['stale' => true, 'spawned' => 1, 'mailed' => 1],
            'stale_again' => ['stale' => true, 'spawned' => 0, 'mailed' => 0],
            'after_ten_minutes' => ['stale' => true, 'spawned' => 1, 'mailed' => 0],
            'after_an_hour' => ['stale' => true, 'spawned' => 1, 'mailed' => 1],
        ], $r['checks'], 'one mail an hour, one run per 10 minutes, nothing for a fresh or missing heartbeat');
        $this->assertSame([self::$db], $r['spawned_for']);
        $this->assertSame('support@saldi.dk', $r['mail']['to']);
        $this->assertStringContainsString('the worker has stopped', $r['mail']['subject']);
        $this->assertStringContainsString('10 minutes ago', $r['mail']['body']);
    }

    public function testARefusedWatchdogMailIsRetriedOnTheNextLogin(): void
    {
        $r = $this->runMode('watchdog', 'mailfail');
        $this->assertSame([false, true, false], $r['mailed'], 'refused, sent on the next stale login, then throttled');
        $this->assertSame([true, false], $r['spawned'], 'the spawn throttle is kept');
    }

    public function testTheWatchdogIgnoresATenantWithoutAShop(): void
    {
        $r = $this->runMode('watchdog', 'noshop');
        $this->assertSame([false], array_values(array_unique(array_column($r['checks'], 'stale'))));
        $this->assertSame([], $r['spawned_for']);
    }

    public function testTheWatchdogsBackgroundRunWritesAFreshHeartbeat(): void
    {
        $r = $this->runMode('watchdog', 'spawn');
        $this->assertSame(['stale' => true, 'mailed' => true, 'spawned' => true], $r['watchdog']);
        $this->assertNotNull($r['heartbeat_after'], 'the spawned tools/shopify_worker.php run wrote the heartbeat within 15 seconds');
        $this->assertSame(1, $r['mails']);
    }
}
