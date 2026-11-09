<?php
// tests/shopify/QueueWorkerTest.php
//
// Job queue and worker run (SD-737) on a throwaway tenant registered in master: dedupe,
// lease fencing, yield, backoff and the dead state, recovery of an expired lease, SKIP LOCKED
// between two claimers, run-loop order and priority, the time budget, and the command-line
// lock. Skips when ShopifyEnv is not configured.
//
// History:
// 20261026 Sawaneh SD-737: created.
// 20261027 Sawaneh SD-737 review: a producer that leaves a transaction open, and a leaked transaktion() depth.
// 20261028 Sawaneh SD-738: job and producer errors are stored and reported without the token.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class QueueWorkerTest extends TestCase
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
            self::$db = ShopifyEnv::testDb('_q');
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
        ShopifyEnv::rows($conn, 'DELETE FROM shopify_jobs');
        ShopifyEnv::rows($conn, "DELETE FROM settings WHERE var_grp = 'shopify_state'");
        ShopifyEnv::rows($conn, "DELETE FROM grupper WHERE art = 'RA'");
        ShopifyEnv::rows($conn, "INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('RA', 1, 'RA', '01', $1, '12', $1, 'on')", [date('Y')]);
        pg_close($conn);
    }

    private function runMode(string $mode, string $arg = ''): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_worker.php', [$mode, self::$db, $arg]));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        $this->assertNotNull($run['result'], $run['stdout']);
        return $run['result'];
    }

    public function testEnqueueDedupesActiveJobsAndKeepsCorrelation(): void
    {
        $r = $this->runMode('queue');
        $this->assertTrue($r['dedupe']['a']['created']);
        $this->assertFalse($r['dedupe']['b']['created'], 'the same type and key while queued is the same job');
        $this->assertSame($r['dedupe']['a']['id'], $r['dedupe']['b']['id']);
        $this->assertTrue($r['dedupe']['other_type'], 'another type with the same key is another job');
        $this->assertSame('77', $r['dedupe']['columns']['ordre_id']);
        $this->assertSame('gid://shopify/Order/1', $r['dedupe']['columns']['order_gid']);
        $this->assertSame('{"n": 1}', $r['dedupe']['columns']['payload'], 'the first payload is kept');
        $this->assertNull($r['not_due'], 'a job with a future run_at is not claimed');
        $this->assertFalse($r['dedupe_while_running']['created']);
        $this->assertSame('running', $r['dedupe_while_running']['state']);
        $this->assertTrue($r['after_done_enqueue']['created'], 'once done, the same key can be enqueued again');
        $this->assertSame('ShopifyQueueError', $r['bad_type']);
        $this->assertSame('ShopifyQueueError', $r['bad_key']);
        $this->assertSame('ShopifyQueueError', $r['bad_priority']);
    }

    public function testClaimAndLeaseFencing(): void
    {
        $r = $this->runMode('queue');
        $this->assertSame('order.import', $r['claim']['type'], 'priority 10 before 25');
        $this->assertSame(1, $r['claim']['attempts']);
        $this->assertSame(['n' => 1], $r['claim']['payload']);
        $this->assertSame(32, $r['claim']['token_len']);
        $this->assertSame('running', $r['claim']['state']);
        $this->assertNull($r['other_claimer_while_running'], 'a running job is not claimed twice');
        foreach (['stale_complete', 'stale_fail', 'stale_yield', 'stale_dead', 'stale_extend'] as $case) {
            $this->assertFalse($r['fence'][$case], "$case with a stale token changed the job");
        }
        $this->assertTrue($r['fence']['extend']);
        $this->assertTrue($r['fence']['complete']);
        $this->assertFalse($r['fence']['complete_again'], 'the current token completes the job once');
        $this->assertSame('done', $r['fence']['state']);
    }

    public function testYieldBackoffDeadAndRecovery(): void
    {
        $r = $this->runMode('queue');
        $this->assertTrue($r['yield']['same']);
        $this->assertSame(['offset' => 250], $r['yield']['checkpoint']);
        $this->assertSame(1, $r['yield']['attempts'], 'a yield gives its attempt back');
        $this->assertSame('t', $r['yield_run_after_later']);
        $this->assertSame([60, 300, 900, 3600, 3600], $r['fail']['waits'], 'retries after 1, 5, 15, 60 minutes, then hourly');
        $this->assertSame('queued', $r['fail']['state']);
        $this->assertSame(5, $r['fail']['attempts']);
        $this->assertSame('boom 5 [redacted]', $r['fail']['last_error'], 'a token in a job error is never stored');
        $this->assertSame(7, $r['retry_after'], 'a Retry-After overrides the backoff');
        $this->assertSame('dead', $r['dead_after_max']);
        $this->assertSame(1, $r['recover']['recovered']);
        $this->assertSame('queued', $r['recover']['state']);
        $this->assertSame('lease expired', $r['recover']['error']);
        $this->assertFalse($r['recover']['old_token_complete'], 'the crashed run cannot finish the job');
        $this->assertTrue($r['recover']['new_token_complete']);
        $this->assertSame(2, $r['recover']['attempts_after'], 'the crashed attempt counts');
    }

    public function testBackoffScheduleAndDeadStateArePure(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/shopify/queue.php';
        $this->assertSame(60, shopify_queue_backoff(1));
        $this->assertSame(300, shopify_queue_backoff(2));
        $this->assertSame(900, shopify_queue_backoff(3));
        $this->assertSame(3600, shopify_queue_backoff(4));
        $this->assertSame(3600, shopify_queue_backoff(51));
        $this->assertNull(shopify_queue_backoff(52), 'dead after 52 attempts');
        $total = 0;
        for ($n = 1; $n < 52; $n++) {
            $total += shopify_queue_backoff($n);
        }
        $this->assertSame(21 * 60 + 48 * 3600, $total, 'about 48 hours of retries');
    }

    public function testDurableStateRoundTrip(): void
    {
        $r = $this->runMode('queue');
        $this->assertSame(['cursor' => 'gid://shopify/Order/10', 'n' => 4], $r['state']['value']);
        $this->assertSame([], $r['state']['missing']);
        $this->assertSame(1, $r['state']['rows'], 'a second save updates the one row');
    }

    public function testSkipLockedLetsASecondClaimerPassALockedJob(): void
    {
        $conn = ShopifyEnv::connect(self::$db);
        $first = (int)ShopifyEnv::one($conn, "INSERT INTO shopify_jobs (type, dedupe_key, priority) VALUES ('test.job', 'tenant:a', 10) RETURNING id")['id'];
        $second = (int)ShopifyEnv::one($conn, "INSERT INTO shopify_jobs (type, dedupe_key, priority) VALUES ('test.job', 'tenant:b', 20) RETURNING id")['id'];
        pg_close($conn);
        $marker = ShopifyEnv::repoRoot() . '/temp/' . self::$db . '/.hold';
        @mkdir(dirname($marker), 0777, true);
        @unlink($marker);
        $holder = ShopifyEnv::startChild('run_worker.php', ['hold', self::$db, (string)$first]);
        for ($i = 0; $i < 100 && !is_file($marker); $i++) {
            usleep(50000);
        }
        $this->assertFileExists($marker, 'the holder did not lock the row');
        $claim = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_worker.php', ['claim', self::$db]));
        $hold = ShopifyEnv::waitChild($holder);
        @unlink($marker);
        $this->assertSame(0, $claim['exit'], $claim['stderr']);
        $this->assertSame(0, $hold['exit'], $hold['stderr']);
        $this->assertSame($second, $claim['result']['id'], 'the claimer skipped the locked, higher-priority job');
        $this->assertLessThan(3000, $claim['result']['ms'], 'the claimer did not wait for the lock');
    }

    public function testWorkerRunOrderOutcomesAndHeartbeat(): void
    {
        $r = $this->runMode('run');
        $this->assertSame(['events', 'stock_dirty'], $r['producer_calls'], 'producers run in order; a broken one does not stop the rest');
        $this->assertSame(['error' => 'RuntimeException: producer down [redacted]'], $r['summary']['producers']['broken'], 'a token in a producer error is redacted');
        $this->assertSame('refused', $r['dup_register']);
        $this->assertSame('import', $r['order'][0], 'priority 10 first');
        $position = array_flip($r['order']);
        $this->assertLessThan($position['seed'], $position['import'], 'the order (priority 10) before the catalogue (priority 60), the NF-5 basis');
        $this->assertLessThan($position['purge'], $position['seed'], 'the purge slot (priority 90) last');
        $this->assertNotContains('yield+{"page":2}', $r['order'], 'a yielded job waits for a later pass');
        $s = $r['states'];
        $this->assertSame('done', $s['order.import']['state']);
        $this->assertSame('queued', $s['order.tags']['state'], 'the yielded job is back in the queue');
        $this->assertSame(0, $s['order.tags']['attempts'], 'a yield gives its attempt back');
        $this->assertSame('queued', $s['stock.push']['state'], 'a thrown error is a failed attempt, retried later');
        $this->assertStringContainsString('RuntimeException: fail', $s['stock.push']['error']);
        $this->assertStringNotContainsString('shpat_', $s['stock.push']['error'], 'the stored job error is redacted');
        $this->assertSame('dead', $s['order.move']['state'], 'ShopifyDbMisuse is never retried');
        $this->assertStringContainsString('ShopifyDbMisuse', $s['order.move']['error']);
        $this->assertSame('dead', $s['order.note']['state'], 'a handler without a valid outcome is dead');
        $this->assertSame('done', $s['order.status']['state'], 'the heartbeat extended the lease');
        $this->assertSame('queued', $s['webhook.health']['state'], 'a type without a handler is not claimed');
        $this->assertSame('done', $s['runtime.purge']['state'], 'the 07:15 slot was scheduled and run');
        $this->assertSame(1, $r['summary']['scheduled']);
        $this->assertSame(0, $r['second_scheduled'], 'a slot is inserted once');
        $this->assertSame(1, $r['summary']['yielded']);
        $this->assertSame(1, $r['summary']['failed']);
        $this->assertSame(2, $r['summary']['dead']);
        $this->assertSame('empty', $r['summary']['stopped']);
        $this->assertSame(0, $r['heartbeat']['claimed'], 'the heartbeat file holds the last pass');
        $this->assertArrayHasKey('unix', $r['heartbeat']);
    }

    public function testATransactionLeftOpenByAHandlerCannotUndoTheJobsTerminalState(): void
    {
        $first = $this->runMode('misuse_replay', 'enqueue');
        $results = array_column($first['jobs'], 'result', 'type');
        $this->assertSame('dead', $results['order.import'], 'ShopifyDbMisuse: the work is already committed');
        $this->assertSame('dead', $results['order.tags'], 'a handler that leaves a transaction open did not finish cleanly');

        $conn = ShopifyEnv::connect(self::$db);
        $states = array_column(ShopifyEnv::rows($conn, "SELECT type, state FROM shopify_jobs WHERE type <> 'mail.alert' ORDER BY id"), 'state', 'type');
        $this->assertSame(['order.import' => 'dead', 'order.tags' => 'dead'], $states, 'the dead state is committed, not lost with the open transaction');
        $this->assertSame('2', ShopifyEnv::one($conn, "SELECT count(*) AS n FROM shopify_jobs WHERE type = 'mail.alert'")['n'], 'each dead job alarms');
        ShopifyEnv::rows($conn, "UPDATE shopify_jobs SET locked_until = now() - interval '1 second' WHERE state = 'running'");
        pg_close($conn);

        $second = $this->runMode('misuse_replay');
        $this->assertSame(0, $second['summary']['claimed'], 'nothing is claimed again');
        $conn = ShopifyEnv::connect(self::$db);
        $effects = ShopifyEnv::rows($conn, "SELECT var_value FROM settings WHERE var_grp = 'shopify_test_effect' ORDER BY var_value");
        ShopifyEnv::rows($conn, "DELETE FROM settings WHERE var_grp = 'shopify_test_effect'");
        pg_close($conn);
        $this->assertSame(['1'], array_column($effects, 'var_value'), 'the committed effect exists once and the uncommitted one is rolled back');
    }

    public function testATransactionLeftOpenByAProducerIsRolledBackBeforeClaiming(): void
    {
        $r = $this->runMode('producer_open');
        $this->assertSame(['error' => 'producer returned with a transaction open; its uncommitted work was rolled back'], $r['summary']['producers']['opens']);
        $this->assertSame(['error' => 'RuntimeException: producer failed mid-transaction'], $r['summary']['producers']['aborts'], 'an aborted transaction does not break the claims after it');
        $this->assertSame([['id' => $r['jobs'][0]['id'], 'type' => 'catalogue.seed', 'result' => 'done']], $r['jobs']);
        $this->assertSame('done', $r['state'], 'the job is finished and committed, not lost with the producer transaction');
        $this->assertSame(0, $r['effects'], "the producer's uncommitted row is rolled back");
        $this->assertSame(0, $r['depth'], 'legacy transaktion() depth left by a handler is reset');
    }

    public function testAYieldedJobIsNotReclaimedInTheSamePass(): void
    {
        $r = $this->runMode('busy_yield');
        $this->assertSame(1, $r['calls'], 'a job that yields waits for a later pass');
        $byType = array_column($r['jobs'], null, 'type');
        $this->assertSame('queued', $byType['order.import']['state']);
        $this->assertSame('t', $byType['order.import']['deferred']);
        $this->assertSame('{"n": 1}', $byType['order.import']['checkpoint']);
        $this->assertSame('done', $byType['catalogue.seed']['state'], 'lower-priority work still runs in the pass');
    }

    public function testRunStopsOnTheTimeBudget(): void
    {
        $r = $this->runMode('backlog');
        $this->assertSame('budget', $r['summary']['stopped']);
        $this->assertGreaterThan(0, $r['summary']['done']);
        $this->assertGreaterThan(0, $r['left'], 'the rest waits for the next pass');
        $this->assertLessThan(9000, $r['summary']['ms']);
    }

    public function testToolRunsOncePerTenantAndFailsClosed(): void
    {
        $dir = ShopifyEnv::repoRoot() . '/temp/' . self::$db;
        @mkdir($dir, 0777, true);
        $lockFile = "$dir/.ht_shopify_worker.lock";
        $tool = [PHP_BINARY, ShopifyEnv::repoRoot() . '/tools/shopify_worker.php', 'run', '--db=' . self::$db];

        $held = fopen($lockFile, 'c');
        $this->assertTrue(flock($held, LOCK_EX | LOCK_NB));
        $contended = $this->runTool($tool);
        flock($held, LOCK_UN);
        fclose($held);
        $this->assertSame(0, $contended['exit'], 'a second runner exits 0');
        $this->assertSame('', $contended['stdout'], 'and runs nothing');

        @unlink($lockFile);
        mkdir($lockFile);
        $unusable = $this->runTool($tool);
        rmdir($lockFile);
        $this->assertSame(3, $unusable['exit'], 'an unusable lock file runs nothing');
        $this->assertStringContainsString('cannot be opened', $unusable['stderr']);

        $usage = $this->runTool([PHP_BINARY, ShopifyEnv::repoRoot() . '/tools/shopify_worker.php', 'run', '--db=../etc']);
        $this->assertSame(2, $usage['exit'], 'a tenant name with a path is refused before any file is touched');
    }

    /** @return array{exit: int, stdout: string, stderr: string} */
    private function runTool(array $cmd): array
    {
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ShopifyEnv::repoRoot());
        fclose($pipes[0]);
        $stdout = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['exit' => proc_close($proc), 'stdout' => $stdout, 'stderr' => $stderr];
    }
}
