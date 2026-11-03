<?php
// tests/shopify/ReplayDayTest.php
//
// NF-1 (SD-766): replaying a day's webhooks changes nothing. A synthetic day (fixtures/day.json,
// made by support/build_replay_day.php from a fixed seed) is replayed into a cloned tenant in
// process, signed at run time with a random webhook secret, and the worker is drained; the
// persisted tables (ReplayDay::SNAPSHOT) must match fixtures/expected.json. The day is then sent
// again, shuffled and with retries, every event is processed again, and the tables must not
// change. Without a database only the fixtures' reproducibility is checked.
//
// History:
// 20261103 CL/LH SD-766: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';
require_once __DIR__ . '/../../includes/shopify/events.php';
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

    private function replay(string ...$args): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_replay.php', array_merge([self::$db], $args ?: ['replay'])));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        return $run['result'];
    }

    public function testTheFixturesAreTheGeneratorsDayAndCarryNoSignature(): void
    {
        $built = ReplayDay::build();
        $fixtures = ReplayDay::load();

        $this->assertSame(ReplayDay::encode($built['day']), ReplayDay::encode($fixtures['day']), 'day.json is stale: run php tests/shopify/support/build_replay_day.php');
        $this->assertSame(ReplayDay::encode($built['graphql']), ReplayDay::encode($fixtures['graphql']), 'graphql.json is stale: run php tests/shopify/support/build_replay_day.php');
        $raw = (string)file_get_contents(ReplayDay::FIXTURES . '/day.json');
        $this->assertStringNotContainsStringIgnoringCase('hmac', $raw);
        $this->assertDoesNotMatchRegularExpression('/shpat_|shpss_/', $raw . file_get_contents(ReplayDay::FIXTURES . '/graphql.json'), 'no Shopify token or secret');
        $this->assertSame(array_keys(ReplayDay::SNAPSHOT), array_values(array_diff(array_keys($fixtures['expected'] ?? []), ['about'])), 'expected.json has every snapshot table');

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
        unset($expected['about']);

        $this->assertSame(['duplicate' => 3, 'stored' => 52], $run['answers1'], 'three retries in the day');
        $this->assertSame(['duplicate'], array_keys($run['answers2']), 'the second replay stores nothing');
        $this->assertSame(0, $run['drain1']['failed'] + $run['drain1']['dead'] + $run['drain2']['failed'] + $run['drain2']['dead']);
        $this->assertGreaterThan(0, $run['reset'], 'every event was processed again');
        $this->assertSame($run['first'], $run['second'], 'NF-1: the second replay changed the stored data');
        $this->assertSame($expected, $run['first'], 'expected.json: after one replay (refresh with run_replay.php record when a lane changes what is stored)');
        $this->assertCount(7, $run['first']['debtors'], 'one debtor per customer');
        $this->assertSame('Kunde 1 Jensen', substr($run['first']['debtors'][0]['firmanavn'], 5), 'the newer update wins over the older one that arrived last');
    }
}
