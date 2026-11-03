<?php
// tests/shopify/ReplayDayTest.php
//
// NF-1 (SD-766): replaying a day's webhooks changes nothing. A synthetic day (fixtures/day.json,
// made by support/build_replay_day.php from a fixed seed) is replayed into a cloned tenant in
// process, signed at run time with a random webhook secret, and the worker is drained; what was
// stored (ReplayDay::SNAPSHOT, and the rows of ReplayDay::TABLES it added, links or not) must
// match fixtures/expected.json. The day is then sent again, shuffled and with retries, every
// event is processed again (each handler runs again), and no business row may change. Sent over
// HTTP (php -S, api/shopify/webhook.php) the day stores the same. Without a database only the
// fixtures' reproducibility is checked: the generator runs as a child, as events.php (its topic
// list) defines functions ClientRetryTest needs absent.
//
// History:
// 20261103 CL/LH SD-766: created.
// 20261103 CL/LH SD-766: review fixes: whole business tables compared, the second drain must run every handler again.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';
require_once __DIR__ . '/support/ReplayDay.php';

final class ReplayDayTest extends TestCase
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
            self::$db = ShopifyEnv::testDb('_nf');
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

    private function replay(string ...$args): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_replay.php', array_merge([self::$db], $args ?: ['replay'])));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        return $run['result'];
    }

    public function testTheFixturesAreTheGeneratorsDayAndCarryNoSignature(): void
    {
        $dir = sys_get_temp_dir() . '/sd766-day-' . bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/support/build_replay_day.php') . ' ' . ReplayDay::SEED . ' ' . escapeshellarg($dir) . ' 2>&1', $output, $exit);
            $this->assertSame(0, $exit, implode("\n", $output));
            foreach (['day', 'graphql'] as $name) {
                $this->assertSame((string)file_get_contents("$dir/$name.json"), (string)file_get_contents(ReplayDay::FIXTURES . "/$name.json"),
                    "$name.json is stale: run php tests/shopify/support/build_replay_day.php");
            }
        } finally {
            array_map('unlink', glob("$dir/*.json"));
            rmdir($dir);
        }
        $fixtures = ReplayDay::load();
        $raw = (string)file_get_contents(ReplayDay::FIXTURES . '/day.json');
        $this->assertStringNotContainsStringIgnoringCase('hmac', $raw);
        $this->assertDoesNotMatchRegularExpression('/shpat_|shpss_/', $raw . file_get_contents(ReplayDay::FIXTURES . '/graphql.json'), 'no Shopify token or secret');
        $this->assertSame(array_merge(array_keys(ReplayDay::SNAPSHOT), ['changes']), array_values(array_diff(array_keys($fixtures['expected'] ?? []), ['about'])),
            'expected.json has every snapshot part and the table changes');

        $topics = array_count_values(array_column($fixtures['day']['deliveries'], 'topic'));
        foreach (['orders/create', 'orders/paid', 'customers/create', 'customers/update', 'fulfillments/create', 'refunds/create'] as $topic) {
            $this->assertArrayHasKey($topic, $topics, "the day has $topic");
        }
        $events = array_map(fn(array $d): string => $d['topic'] . ' ' . $d['event_id'], $fixtures['day']['deliveries']);
        $this->assertGreaterThan(0, count($events) - count(array_unique($events)), 'the day retries a delivery');
        $this->assertContains('out of order: older than the update before it', array_column($fixtures['day']['deliveries'], 'note'));
    }

    public function testReplayingTheDayTwiceChangesNothing(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
        $run = $this->replay();
        $expected = ReplayDay::load()['expected'];
        $changes = $expected['changes'];
        unset($expected['about'], $expected['changes']);

        $this->assertSame(['duplicate' => 3, 'stored' => 52], $run['answers1'], 'three retries in the day');
        $this->assertSame(['duplicate'], array_keys($run['answers2']), 'the second replay stores nothing');
        $this->assertSame(0, $run['drain1']['failed'] + $run['drain1']['dead'] + $run['drain2']['failed'] + $run['drain2']['dead']);
        $this->assertGreaterThan(0, $run['reset'], 'every event was processed again');
        foreach (array_keys(ReplayDay::registry([])) as $type) {
            $this->assertGreaterThan(0, $run['drain1']['handled'][$type], "the first drain ran $type");
            $this->assertSame($run['drain1']['handled'][$type], $run['drain2']['handled'][$type], "the second drain ran every $type job again");
        }
        $this->assertSame([], $run['changes2'], 'NF-1: the second replay changed business rows (ReplayDay::TABLES, linked or not)');
        $this->assertSame($run['first'], $run['second'], 'NF-1: the second replay changed the stored data');
        $this->assertSame($expected, $run['first'], 'expected.json: after one replay (refresh and review as tests/shopify/support/FAKE-STORE.md says)');
        $this->assertSame($changes, $run['changes1'], 'expected.json changes: the rows one replay adds');
        $this->assertCount(7, $run['first']['debtors'], 'one debtor per customer');
        $this->assertSame('Kunde 1 Jensen', substr($run['first']['debtors'][0]['firmanavn'], 5), 'the newer update wins over the older one that arrived last');
    }

    public function testTheDayOverHttpStoresWhatTheInProcessReplayStores(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
        $file = ShopifyEnv::repoRoot() . '/includes/connect.php';
        if (!is_readable($file) || !preg_match('/^\$sqdb\s*=\s*"([^"]*)"/m', (string)file_get_contents($file), $m) || $m[1] !== ShopifyEnv::masterDb()) {
            $this->markTestSkipped('api/shopify/webhook.php needs the install\'s includes/connect.php on SALDI_SHOPIFY_MASTER_DB');
        }
        $run = $this->replay('http');
        $expected = ReplayDay::load()['expected'];
        unset($expected['about']);

        $this->assertSame(['duplicate' => 3, 'stored' => 52], $run['answers1'], 'signed at run time, answered by the endpoint');
        $changes = $expected['changes'];
        unset($expected['changes']);
        $this->assertSame($expected, $run['first']);
        $this->assertSame($changes, $run['changes1']);
    }
}
