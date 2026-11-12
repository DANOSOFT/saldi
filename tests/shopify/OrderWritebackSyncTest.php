<?php
// tests/shopify/OrderWritebackSyncTest.php
//
// The order write-back (SD-759, WP10-1) through the worker on a cloned tenant: the order.tags job
// the import (SD-743) enqueues brings the hold tags, the order and invoice number with the invoice
// link (K23, K45), the split-set note (K55) and saldi-behandlet onto the Shopify order, in that
// order, and a failed or replayed stage does not upset it. Needs SALDI_SHOPIFY_TEST_DB (see
// tests/shopify/support/ShopifyEnv.php); skipped without it.
//
// History:
// 20261109 CL/MJ SD-759: created.
// 20261111 CL/MJ SD-759 (Sol): lager 91 also has an older LG row, as a copy from an earlier year leaves it.
// 20261112 CL/MJ SD-759 (review): a waiting stage yields without using an attempt; so does one of a shop whose orders flow is off. antal is a number.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class OrderWritebackSyncTest extends TestCase
{
    private static ?string $skip = null;
    private static string $db = '';
    /** @var resource|\PgSql\Connection|null */
    private $conn = null;
    private string $state = '';

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        try {
            self::$db = ShopifyEnv::testDb('_w');
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
        $this->conn = ShopifyEnv::connect(self::$db);
        $this->state = tempnam(sys_get_temp_dir(), 'sd759_');
        foreach (['shopify_order_links', 'shopify_jobs', 'shopify_errors', 'dokument_links', 'shopify_shops'] as $table) {
            $this->sql("DELETE FROM $table");
        }
        $this->sql("DELETE FROM ordrelinjer WHERE ordre_id IN (SELECT id FROM ordrer WHERE firmanavn = 'SD759')");
        $this->sql("DELETE FROM ordrer WHERE firmanavn = 'SD759'");
        $this->sql("DELETE FROM grupper WHERE art = 'LG' AND kodenr::text IN ('91', '92')");
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art) VALUES ('Mårum (gammel)', 91, 'LG')");
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art) VALUES ('Mårum', 91, 'LG'), ('Hillerød', 92, 'LG')");
        $this->sql("DELETE FROM settings WHERE var_grp = 'shopify' AND var_name = 'webhook_base_url'");
        $this->sql("INSERT INTO settings (var_grp, var_name, var_value) VALUES ('shopify', 'webhook_base_url', 'https://saldi.example')");
        $this->sql("INSERT INTO shopify_shops (shop_key, domain, is_primary, orders_enabled) VALUES ('hml', 'hml-fake.myshopify.com', true, true)");
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            pg_close($this->conn);
        }
        foreach ([$this->state, $this->state . '.heartbeat'] as $file) {
            if ($file !== '' && is_file($file)) {
                unlink($file);
            }
        }
    }

    private function sql(string $sql, array $params = []): array
    {
        return ShopifyEnv::rows($this->conn, $sql, $params);
    }

    private function steps(array ...$steps): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_writeback.php', [self::$db, $this->state, json_encode($steps)]));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        return $run['result'];
    }

    /**
     * An invoiced web order as SD-743 leaves it: posted, linked with the intake's hold tags, and
     * its order.tags job queued. $split puts the set's two components on two lagre.
     */
    private function invoiced(string $gid, array $tags, bool $split): int
    {
        $ordreId = (int)$this->sql("INSERT INTO ordrer (ordrenr, fakturanr, art, status, firmanavn) VALUES (4711, '1042', 'DO', 4, 'SD759') RETURNING id")[0]['id'];
        $rows = [[1, 'HAVESAET-1', 1, 91, 'on'], [2, 'STOL-X', 1, 91, ''], [3, 'BORD-Y', 2, $split ? 92 : 91, '']];
        foreach ($rows as [$posnr, $varenr, $antal, $lager, $samlevare]) {
            $this->sql('INSERT INTO ordrelinjer (ordre_id, posnr, varenr, antal, lager, saet, samlevare) VALUES ($1, $2, $3, $4, $5, 1, $6)', [$ordreId, $posnr, $varenr, $antal, $lager, $samlevare]);
        }
        $flags = json_encode(['intake' => ['tags' => $tags, 'total_diff' => 0]]);
        $shopId = (int)$this->sql("SELECT id FROM shopify_shops WHERE shop_key = 'hml'")[0]['id'];
        $linkSql = "INSERT INTO shopify_order_links (shop_id, order_gid, ordre_id, direction, state, flags) VALUES ($1, $2, $3, 'web', 'invoiced', $4::jsonb) RETURNING id";
        $linkId = (int)$this->sql($linkSql, [$shopId, $gid, $ordreId, $flags])[0]['id'];
        $payload = json_encode(['link_id' => $linkId, 'ordre_id' => $ordreId, 'order_gid' => $gid]);
        $jobSql = "INSERT INTO shopify_jobs (type, shop_id, dedupe_key, payload, priority) VALUES ('order.tags', $1, $2, $3::jsonb, 10)";
        $this->sql($jobSql, [$shopId, "hml:$gid", $payload]);
        return $ordreId;
    }

    private function link(string $gid): array
    {
        return json_decode($this->sql('SELECT flags FROM shopify_order_links WHERE order_gid = $1', [$gid])[0]['flags'], true);
    }

    /** @return list<string> the mutations sent, in order */
    private static function mutations(array $run): array
    {
        return array_values(array_filter(array_merge([], ...array_column($run['calls'], 'roots')), fn(string $r): bool => $r !== 'order'));
    }

    public function testAnInvoicedOrderGetsItsHoldTagsNumbersSplitSetNoteAndSaldiBehandletInThatOrder(): void
    {
        $gid = $this->steps(['order', 'Ring før levering'])['results'][0];
        $ordreId = $this->invoiced($gid, ['saldi-saet-delt'], true);

        $run = $this->steps(['pass']);

        $this->assertSame([['order.tags', 'done'], ['order.references', 'done'], ['order.note', 'done'], ['order.processed', 'done']], $run['results'][0]);
        $this->assertSame(['tagsAdd', 'metafieldsSet', 'orderUpdate', 'metafieldsSet', 'tagsAdd'], self::mutations($run), 'hold tags first, saldi-behandlet last');
        $order = $run['orders'][$gid];
        $this->assertSame(['saldi-saet-delt', 'saldi-behandlet'], $order['tags']);
        $this->assertSame(['4711', '1042'], [$order['metafields']['saldi.ordre_nr'], $order['metafields']['saldi.faktura_nr']], 'K23');
        $this->assertStringStartsWith('https://saldi.example/api/dokument.php?t=', $order['metafields']['saldi.faktura_link'], 'K45');
        $this->assertStringStartsWith("Ring før levering\n", $order['note']);
        $this->assertStringContainsString("Sæt HAVESAET-1 delt over flere lagre:\n- 1 x STOL-X: Mårum\n- 2 x BORD-Y: Hillerød", $order['note'], 'K55');
        $this->assertSame([['saet' => 'HAVESAET-1', 'saet_id' => 1, 'vare' => 'STOL-X', 'antal' => 1, 'lager' => 'Mårum'], ['saet' => 'HAVESAET-1', 'saet_id' => 1, 'vare' => 'BORD-Y', 'antal' => 2, 'lager' => 'Hillerød']],
            json_decode($order['metafields']['saldi.saet_fordeling'], true));
        $flags = $this->link($gid);
        $this->assertEquals(['order.tags' => 'done', 'order.references' => 'done', 'order.note' => 'done', 'order.processed' => 'done'], $flags['writeback_v1'], 'every checkpoint, in whatever key order jsonb keeps');
        $this->assertSame(["wp10.split_set:$ordreId"], array_keys($flags['note_ops']));
        $this->assertSame(['saldi-saet-delt'], $flags['intake']['tags'], "the intake's flags are kept");
    }

    public function testAnOrderWithoutHoldsOrASplitSetOnlyGetsItsNumbersAndSaldiBehandlet(): void
    {
        $gid = $this->steps(['order', null])['results'][0];
        $this->invoiced($gid, [], false);

        $run = $this->steps(['pass']);

        $this->assertSame(['metafieldsSet', 'tagsAdd'], self::mutations($run));
        $this->assertSame(['saldi-behandlet'], $run['orders'][$gid]['tags']);
        $this->assertNull($run['orders'][$gid]['note']);
        $this->assertArrayNotHasKey('saldi.saet_fordeling', $run['orders'][$gid]['metafields']);
    }

    public function testFailedHoldTagsQueueNothingElseAndSaldiBehandletStaysOff(): void
    {
        $gid = $this->steps(['order', null])['results'][0];
        $this->invoiced($gid, ['saldi-mangler-lager'], false);

        $run = $this->steps(['fail', 'tagsAdd', 'user_error'], ['pass']);

        $this->assertSame([['order.tags', 'failed']], $run['results'][1]);
        $this->assertSame([], $run['orders'][$gid]['tags']);
        $this->assertSame([], $run['orders'][$gid]['metafields']);
        $this->assertSame(['order.tags'], array_column($this->sql('SELECT type FROM shopify_jobs ORDER BY id'), 'type'), 'the later stages are not queued');
        $this->assertArrayNotHasKey('writeback_v1', $this->link($gid));
    }

    /** Waiting is not a failure: the attempt is given back, so a long wait never makes the job dead. */
    public function testAStageWaitingForAnEarlierOneYieldsAndWritesNothing(): void
    {
        $gid = $this->steps(['order', null])['results'][0];
        $this->invoiced($gid, [], false);
        $this->sql("UPDATE shopify_jobs SET type = 'order.processed', priority = 25");

        $run = $this->steps(['pass']);

        $this->assertSame([['order.processed', 'yielded']], $run['results'][0]);
        $this->assertSame([], $run['calls']);
        $job = $this->sql("SELECT state, attempts, next_run_at > now() + interval '4 minutes' AS later FROM shopify_jobs WHERE type = 'order.processed'")[0];
        $this->assertSame(['queued', 0, true], [$job['state'], (int)$job['attempts'], in_array($job['later'], [true, 't'], true)]);
    }

    /** An uninstall switches the orders flow off; the queued write-back waits rather than failing for want of a token. */
    public function testAShopWhoseOrdersFlowIsOffGetsNoWriteBack(): void
    {
        $gid = $this->steps(['order', null])['results'][0];
        $this->invoiced($gid, ['saldi-mangler-lager'], false);
        $this->sql('UPDATE shopify_shops SET orders_enabled = false');

        $run = $this->steps(['pass']);

        $this->assertSame([['order.tags', 'yielded']], $run['results'][0]);
        $this->assertSame([], $run['calls']);
        $this->assertSame([], $run['orders'][$gid]['tags']);
    }

    public function testAReplayedTagsJobPutsBackNoTagHmlRemoved(): void
    {
        $gid = $this->steps(['order', null])['results'][0];
        $this->invoiced($gid, ['saldi-mangler-lager'], false);
        $this->steps(['pass']);
        $this->sql("INSERT INTO shopify_jobs (type, shop_id, dedupe_key, payload, priority) SELECT type, shop_id, dedupe_key || ':replay', payload, priority FROM shopify_jobs WHERE type = 'order.tags'");

        $run = $this->steps(['untag', $gid, 'saldi-mangler-lager'], ['pass']);

        $this->assertSame([['order.tags', 'done']], $run['results'][1]);
        $this->assertSame(['tagsRemove'], self::mutations($run), 'the replay sent nothing');
        $this->assertSame(['saldi-behandlet'], $run['orders'][$gid]['tags']);
    }
}
