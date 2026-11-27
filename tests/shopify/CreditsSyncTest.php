<?php
// tests/shopify/CreditsSyncTest.php
//
// SD-834 (WP14-2, K46, K48): a posted credit note of a web order shows on its Shopify order as a dated
// note line, a tag and the credit note's link, once; Shopify refunds are matched against the credit
// notes, and a refund no credit note accounts for alarms once. Real tenant, fake store.
//
// History:
// 20261125 CL/SS SD-834: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class CreditsSyncTest extends TestCase
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
            self::$db = ShopifyEnv::testDb('_cr');
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
        $this->state = tempnam(sys_get_temp_dir(), 'sd834_');
        foreach (['shopify_refund_allocations', 'shopify_refunds', 'shopify_order_links', 'shopify_jobs', 'shopify_errors', 'dokument_links', 'shopify_shops'] as $table) {
            $this->sql("DELETE FROM $table");
        }
        $this->sql("DELETE FROM ordrer WHERE firmanavn = 'SD834'");
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
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_credits.php', [self::$db, $this->state, json_encode($steps)]));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        return $run['result'];
    }

    /** A web order as SD-743 leaves it: invoiced (2 × 1.250 kr.) and linked to its Shopify order. */
    private function webOrder(string $gid): int
    {
        $ordreId = (int)$this->sql("INSERT INTO ordrer (ordrenr, fakturanr, art, status, firmanavn, sum, moms, valuta) VALUES (4711, '1042', 'DO', 4, 'SD834', 2000, 500, 'DKK') RETURNING id")[0]['id'];
        $shopId = (int)$this->sql("SELECT id FROM shopify_shops WHERE shop_key = 'hml'")[0]['id'];
        $this->sql("INSERT INTO shopify_order_links (shop_id, order_gid, order_name, ordre_id, direction, state) VALUES ($1, $2, '#HML1240', $3, 'web', 'invoiced')", [$shopId, $gid, $ordreId]);
        return $ordreId;
    }

    /** A credit note of $origId, stored as Saldi stores one: negative sum and VAT. */
    private function credit(int $origId, string $nr, string $sum, string $moms, int $status = 4): int
    {
        return (int)$this->sql("INSERT INTO ordrer (ordrenr, fakturanr, art, status, firmanavn, sum, moms, valuta, kred_ord_id) VALUES (4712, $1, 'DK', $2, 'SD834', $3, $4, 'DKK', $5) RETURNING id",
            [$nr, $status, $sum, $moms, $origId])[0]['id'];
    }

    /** A refund as SD-801 projects it: amount = its successful refund transactions. */
    private function refund(string $orderGid, int $ordreId, string $refundGid, string $amount, string $note = ''): void
    {
        $shopId = (int)$this->sql("SELECT id FROM shopify_shops WHERE shop_key = 'hml'")[0]['id'];
        $this->sql('INSERT INTO shopify_refunds (shop_id, order_gid, refund_gid, ordre_id, amount, currency, refunded_at, payload_b64) VALUES ($1, $2, $3, $4, $5, $6, now(), $7)',
            [$shopId, $orderGid, $refundGid, $ordreId, $amount, 'DKK', base64_encode(json_encode(['note' => $note]))]);
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

    public function testAPostedCreditNoteShowsOnItsShopifyOrderAsANoteLineATagAndItsLink(): void
    {
        $gid = $this->steps(['order'])['results'][0];
        $credit = $this->credit($this->webOrder($gid), '1043', '-100.00', '-25.00');

        $run = $this->steps(['scan'], ['pass']);
        $this->assertSame(1, $run['results'][0]['queued'], json_encode($run));
        $this->assertSame([['credit.publish', 'done']], $run['results'][1], json_encode($run));
        $order = $run['orders'][$gid];
        $this->assertMatchesRegularExpression('/^Levering til døren\n\d{4}-\d{2}-\d{2} Kreditnota 1043, 125 kr\. \[saldi:[0-9a-f]+\]$/', $order['note'], 'K48: one dated line under the customer\'s own note');
        $this->assertSame(['saldi-krediteret'], $order['tags']);
        $this->assertStringStartsWith('https://saldi.example/', $order['metafields']['saldi.kreditnota_url'] ?? '', json_encode($order));
        $this->assertSame(['orderUpdate', 'tagsAdd', 'metafieldsSet'], self::mutations($run));
        $this->assertEquals(['note' => true, 'tag' => true, 'published' => true], $this->link($gid)['credits_v1'][(string)$credit]);

        $again = $this->steps(['scan'], ['pass']);
        $this->assertSame(0, $again['results'][0]['queued'], 'a published credit note is not queued again');
        $this->assertSame([], self::mutations($again));
    }

    public function testAReplayAfterALostCheckpointAppendsNoSecondNoteLine(): void
    {
        $gid = $this->steps(['order'])['results'][0];
        $credit = $this->credit($this->webOrder($gid), '1043', '-1000.40', '-250.10');
        $first = $this->steps(['scan'], ['pass'])['orders'][$gid]['note'];
        $this->assertStringContainsString('Kreditnota 1043, 1.250,50 kr.', $first);

        $this->sql("UPDATE shopify_order_links SET flags = flags - 'credits_v1' WHERE order_gid = $1", [$gid]);
        $run = $this->steps(['scan'], ['pass']);
        $this->assertSame($first, $run['orders'][$gid]['note'], 'the marker is found, so nothing is appended');
        $this->assertSame(1, substr_count($run['orders'][$gid]['note'], 'Kreditnota 1043'));
        $this->assertTrue($this->link($gid)['credits_v1'][(string)$credit]['published']);
    }

    public function testATagHmlRemovedIsNotPutBackWhenOnlyTheLinkIsLeft(): void
    {
        $gid = $this->steps(['order'])['results'][0];
        $credit = $this->credit($this->webOrder($gid), '1043', '-100.00', '-25.00');
        $this->steps(['scan'], ['pass']);
        $this->sql("UPDATE shopify_order_links SET flags = jsonb_set(flags, '{credits_v1}', jsonb_build_object($2::text, '{\"note\": true, \"tag\": true}'::jsonb)) WHERE order_gid = $1", [$gid, (string)$credit]);

        $run = $this->steps(['untag', $gid, 'saldi-krediteret'], ['scan'], ['pass']);
        $this->assertSame(['tagsRemove', 'metafieldsSet'], self::mutations($run), 'after staff remove the tag: note and tag are checkpointed, only the link is sent again');
        $this->assertSame([], $run['orders'][$gid]['tags']);
    }

    public function testADraftCreditNoteOrOneOfAnOrderNotFromShopifyIsNotPublished(): void
    {
        $gid = $this->steps(['order'])['results'][0];
        $this->credit($this->webOrder($gid), '', '-100.00', '-25.00', 1);
        $plain = (int)$this->sql("INSERT INTO ordrer (ordrenr, fakturanr, art, status, firmanavn, sum, moms) VALUES (4800, '900', 'DO', 4, 'SD834', 100, 25) RETURNING id")[0]['id'];
        $this->credit($plain, '901', '-100.00', '-25.00');

        $run = $this->steps(['scan'], ['pass']);
        $this->assertSame(0, $run['results'][0]['queued']);
        $this->assertSame([], self::mutations($run));
        $this->assertSame('Levering til døren', $run['orders'][$gid]['note'], 'only the customer\'s note');
    }

    public function testARefundOfTheCreditNotesAmountIsMatchedToIt(): void
    {
        $gid = 'gid://shopify/Order/9001';
        $orig = $this->webOrder($gid);
        $credit = $this->credit($orig, '1043', '-100.00', '-25.00');
        $this->refund($gid, $orig, 'gid://shopify/Refund/1', '125.000');

        $run = $this->steps(['reconcile', $gid], ['reconcile', $gid]);
        $this->assertSame([['refund_gid' => 'gid://shopify/Refund/1', 'credit_ordre_id' => $credit, 'amount_minor' => 12500]], $run['results'][0]['matched']);
        $this->assertSame(['matched' => [], 'unmatched' => []], $run['results'][1], 'a replay matches nothing twice');
        $this->assertSame([['refund_gid' => 'gid://shopify/Refund/1', 'credit_ordre_id' => (string)$credit, 'amount_minor' => '12500']],
            $this->sql('SELECT refund_gid, credit_ordre_id, amount_minor FROM shopify_refund_allocations'));
        $this->assertSame('matched', $this->sql('SELECT state FROM shopify_refunds')[0]['state']);
        $this->assertSame([], $this->sql('SELECT id FROM shopify_errors'));
    }

    public function testAPendingOrFailedRefundIsNotCounted(): void
    {
        $gid = 'gid://shopify/Order/9002';
        $orig = $this->webOrder($gid);
        $this->credit($orig, '1043', '-100.00', '-25.00');
        $this->refund($gid, $orig, 'gid://shopify/Refund/2', '0.000');

        $run = $this->steps(['reconcile', $gid]);
        $this->assertSame(['matched' => [], 'unmatched' => []], $run['results'][0]);
        $this->assertSame('received', $this->sql('SELECT state FROM shopify_refunds')[0]['state'], 'left for the update that brings its successful transaction');
        $this->assertSame([], $this->sql('SELECT id FROM shopify_refund_allocations'));
        $this->assertSame([], $this->sql('SELECT id FROM shopify_errors'));
    }

    public function testARefundWithATransactionStillPendingWaitsForItInsteadOfAlarming(): void
    {
        $gid = 'gid://shopify/Order/9005';
        $orig = $this->webOrder($gid);
        $this->credit($orig, '1043', '-100.00', '-25.00');
        $this->refund($gid, $orig, 'gid://shopify/Refund/7', '50.000');
        $transactions = [['kind' => 'refund', 'status' => 'success', 'amount' => '50.00'], ['kind' => 'refund', 'status' => 'pending', 'amount' => '75.00']];
        $this->sql('UPDATE shopify_refunds SET transactions_b64 = $1', [base64_encode(json_encode($transactions))]);

        $run = $this->steps(['reconcile', $gid]);
        $this->assertSame(['matched' => [], 'unmatched' => []], $run['results'][0]);
        $this->assertSame('received', $this->sql('SELECT state FROM shopify_refunds')[0]['state'], 'still received, so SD-801 can store the settled amount');
        $this->assertSame([], $this->sql('SELECT id FROM shopify_errors'));
    }

    public function testARefundWithNoCreditNoteAlarmsOnceAndIsMatchedWhenTheCreditNoteIsPosted(): void
    {
        $gid = 'gid://shopify/Order/9003';
        $orig = $this->webOrder($gid);
        $this->credit($orig, '1043', '-200.00', '-50.00');
        $this->refund($gid, $orig, 'gid://shopify/Refund/3', '125.000');

        $run = $this->steps(['reconcile', $gid], ['scan'], ['reconcile', $gid]);
        $this->assertSame(['gid://shopify/Refund/3'], $run['results'][0]['unmatched']);
        $errors = $this->sql("SELECT kind, ref, occurrences, resolved_at FROM shopify_errors");
        $this->assertSame([['kind' => 'refund.unmatched', 'ref' => 'gid://shopify/Refund/3', 'occurrences' => '1', 'resolved_at' => null]], $errors, 'one error, not counted again by the replays');
        $this->assertSame('1', $this->sql("SELECT count(*) AS n FROM shopify_jobs WHERE type = 'mail.alert'")[0]['n'], 'one alarm');

        $credit = $this->credit($orig, '1044', '-100.00', '-25.00');
        $after = $this->steps(['reconcile', $gid]);
        $this->assertSame([['refund_gid' => 'gid://shopify/Refund/3', 'credit_ordre_id' => $credit, 'amount_minor' => 12500]], $after['results'][0]['matched']);
        $this->assertNotNull($this->sql('SELECT resolved_at FROM shopify_errors')[0]['resolved_at']);
    }

    public function testOfTwoCreditNotesWithTheSameAmountTheRefundTakesTheOneItNames(): void
    {
        $gid = 'gid://shopify/Order/9004';
        $orig = $this->webOrder($gid);
        $first = $this->credit($orig, '1043', '-100.00', '-25.00');
        $second = $this->credit($orig, '1044', '-100.00', '-25.00');
        $this->refund($gid, $orig, 'gid://shopify/Refund/4', '125.000', 'Kreditnota 1044');
        $this->refund($gid, $orig, 'gid://shopify/Refund/5', '125.000');
        $this->refund($gid, $orig, 'gid://shopify/Refund/6', '125.000');

        $run = $this->steps(['reconcile', $gid]);
        $this->assertSame([['gid://shopify/Refund/4', $second], ['gid://shopify/Refund/5', $first]],
            array_map(fn(array $m): array => [$m['refund_gid'], $m['credit_ordre_id']], $run['results'][0]['matched']));
        $this->assertSame(['gid://shopify/Refund/6'], $run['results'][0]['unmatched'], 'one credit note is one refund: a third refund of the same amount is not covered');
    }
}
