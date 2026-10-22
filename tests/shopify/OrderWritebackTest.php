<?php
// tests/shopify/OrderWritebackTest.php
//
// The writeback stage order and its builders (SD-759, WP10-1): hold tags before references
// and note, saldi-behandlet last, an earlier stage in backoff blocking the later ones, the
// K23/K45 metafields and the K55 split-set fixture. The stage decisions take their
// checkpoints as an argument, so none of this needs a database.
//
// History:
// 20261022 CL/MJ SD-759: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/FakeShopifyGraphql.php';
require_once dirname(__DIR__, 2) . '/includes/shopify/services/orderWriteback.php';

final class OrderWritebackTest extends TestCase
{
    private const ORDER = 'gid://shopify/Order/5544332211000';

    private FakeShopifyGraphql $shop;

    protected function setUp(): void
    {
        $this->shop = new FakeShopifyGraphql();
        shopify_set_graphql_client($this->shop);
    }

    protected function tearDown(): void
    {
        shopify_set_graphql_client(null);
        shopify_set_writeback_enqueuer(null);
    }

    /** @param array<string, string> $checkpoints */
    private function stage(string $stage, array $checkpoints, array $context = []): array
    {
        return shopify_writeback_stage(7, self::ORDER, $stage, $context, $checkpoints);
    }

    private function allDoneExcept(string ...$missing): array
    {
        $checkpoints = [
            'order.tags' => 'done',
            'order.move' => 'done',
            'order.references' => 'done',
            'order.note' => 'done',
        ];
        foreach ($missing as $stage) {
            unset($checkpoints[$stage]);
        }
        return $checkpoints;
    }

    public function testTheStagesRunInTheOrderOfPlan27(): void
    {
        $this->assertSame([
            'order.tags' => 10,
            'order.move' => 15,
            'order.references' => 20,
            'order.note' => 25,
            'order.processed' => 25,
        ], shopify_writeback_stages());
    }

    public function testHoldTagsHaveNoPrerequisites(): void
    {
        $this->assertSame([], shopify_writeback_prerequisites('order.tags'));
        $this->assertTrue(shopify_writeback_may_run([], 'order.tags'));
    }

    public function testReferencesAndNoteWaitForTheHoldTags(): void
    {
        $this->assertFalse(shopify_writeback_may_run([], 'order.references'));
        $this->assertFalse(shopify_writeback_may_run([], 'order.note'));
        $this->assertSame('order.tags', shopify_writeback_blocked_by([], 'order.references'));
        $this->assertSame('order.tags', shopify_writeback_blocked_by([], 'order.note'));
        $this->assertTrue(shopify_writeback_may_run(['order.tags' => 'done'], 'order.references'));
    }

    public function testTheNoteWaitsForTheReferences(): void
    {
        $this->assertSame('order.references', shopify_writeback_blocked_by(['order.tags' => 'done'], 'order.note'));
    }

    public function testSaldiBehandletIsLast(): void
    {
        $this->assertTrue(shopify_writeback_may_run($this->allDoneExcept(), 'order.processed'));
        foreach (['order.tags', 'order.move', 'order.references', 'order.note'] as $stage) {
            $checkpoints = $this->allDoneExcept($stage);
            $this->assertSame($stage, shopify_writeback_blocked_by($checkpoints, 'order.processed'));
        }
    }

    public function testSaldiBehandletAcceptsAnAbandonedMove(): void
    {
        $checkpoints = $this->allDoneExcept();
        $checkpoints['order.move'] = 'abandoned';

        $this->assertTrue(shopify_writeback_may_run($checkpoints, 'order.processed'));
    }

    public function testOnlyAMoveMayBeAbandoned(): void
    {
        foreach (['order.tags', 'order.references', 'order.note'] as $stage) {
            $checkpoints = $this->allDoneExcept();
            $checkpoints[$stage] = 'abandoned';
            $this->assertFalse(
                shopify_writeback_may_run($checkpoints, 'order.processed'),
                "$stage must not count as finished when abandoned"
            );
        }
    }

    public function testRecordingAnAbandonedTagsStageIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        shopify_writeback_mark(7, self::ORDER, 'order.tags', 'abandoned');
    }

    public function testSaldiBehandletIsNotSetWhileTheTagsStageIsRetrying(): void
    {
        // order.tags has no checkpoint: it is still backing off.
        $outcome = $this->stage('order.processed', ['order.references' => 'done', 'order.note' => 'done', 'order.move' => 'done']);

        $this->assertSame('requeue', $outcome['status']);
        $this->assertSame('order.tags', $outcome['blocked_by']);
        $this->assertNull($outcome['checkpoint']);
        $this->assertSame([], $this->shop->calls, 'a blocked stage must not call Shopify at all');
        $this->assertNotContains('saldi-behandlet', $this->shop->tags);
    }

    public function testABlockedReferencesStageWritesNoMetafields(): void
    {
        $outcome = $this->stage('order.references', [], ['references' => ['ordre_nr' => 1001, 'faktura_nr' => 2002]]);

        $this->assertSame('requeue', $outcome['status']);
        $this->assertSame([], $this->shop->calls);
        $this->assertSame([], $this->shop->metafields);
    }

    public function testTheHoldTagsStageWritesTheTagsAndCheckpointsItself(): void
    {
        $outcome = $this->stage('order.tags', [], ['allocation' => ['shortage' => true, 'split_set' => true]]);

        $this->assertSame('done', $outcome['status']);
        $this->assertSame('done', $outcome['checkpoint']);
        $this->assertSame(['saldi-mangler-lager', 'saldi-saet-delt'], $this->shop->tags);
    }

    public function testATagHmlRemovedIsNotPutBackOnceTheStageIsDone(): void
    {
        // The stage ran, HML took the tag off again, the job is retried for another reason.
        $outcome = $this->stage('order.tags', ['order.tags' => 'done'], ['allocation' => ['shortage' => true]]);

        $this->assertSame('done', $outcome['status']);
        $this->assertNull($outcome['checkpoint'], 'nothing new to record');
        $this->assertSame([], $this->shop->calls, 'a finished stage must not call tagsAdd again');
        $this->assertSame([], $this->shop->tags);
    }

    public function testAnOrderWithNoHoldsStillFinishesTheTagsStage(): void
    {
        $outcome = $this->stage('order.tags', [], ['allocation' => []]);

        $this->assertSame('done', $outcome['status']);
        $this->assertSame('done', $outcome['checkpoint']);
        $this->assertSame([], $this->shop->calls, 'no hold tags, no API call');
    }

    public function testHoldTagsComeFromTheAllocation(): void
    {
        $this->assertSame([], shopify_build_hold_tags([]));
        $this->assertSame(['saldi-mangler-lager'], shopify_build_hold_tags(['shortage' => true]));
        $this->assertSame(['saldi-saet-delt'], shopify_build_hold_tags(['split_set' => true]));
        $this->assertSame(
            ['saldi-mangler-lager', 'saldi-saet-delt'],
            shopify_build_hold_tags(['shortage' => true, 'split_set' => true])
        );
    }

    public function testK23AndK45TheOrderAndInvoiceNumberAndTheInvoiceLink(): void
    {
        $ctx = ['order_gid' => self::ORDER, 'ordre_nr' => 1001, 'faktura_nr' => 2002];

        $metafields = shopify_build_order_metafields($ctx, 'https://saldi.example/dok/abc123');

        $this->assertSame([
            ['ownerId' => self::ORDER, 'namespace' => 'saldi', 'key' => 'ordre_nr', 'type' => 'single_line_text_field', 'value' => '1001'],
            ['ownerId' => self::ORDER, 'namespace' => 'saldi', 'key' => 'faktura_nr', 'type' => 'single_line_text_field', 'value' => '2002'],
            ['ownerId' => self::ORDER, 'namespace' => 'saldi', 'key' => 'faktura_link', 'type' => 'url', 'value' => 'https://saldi.example/dok/abc123'],
        ], $metafields);
    }

    public function testAMissingInvoiceLinkIsLeftOutRatherThanWrittenEmpty(): void
    {
        $ctx = ['order_gid' => self::ORDER, 'ordre_nr' => 1001, 'faktura_nr' => 2002];

        $keys = array_column(shopify_build_order_metafields($ctx, null), 'key');

        $this->assertSame(['ordre_nr', 'faktura_nr'], $keys);
    }

    public function testTheReferencesStageSendsThemWithMetafieldsSet(): void
    {
        $context = ['references' => ['ordre_nr' => 1001, 'faktura_nr' => 2002], 'invoice_url' => 'https://saldi.example/dok/abc123'];

        $outcome = $this->stage('order.references', ['order.tags' => 'done'], $context);

        $this->assertSame('done', $outcome['status']);
        $this->assertSame(['saldiOrderMetafields'], $this->shop->calls);
        $this->assertSame('1001', $this->shop->metafields['saldi.ordre_nr']);
        $this->assertSame('2002', $this->shop->metafields['saldi.faktura_nr']);
        $this->assertSame('https://saldi.example/dok/abc123', $this->shop->metafields['saldi.faktura_link']);
    }

    /** K55: a set of 1 X and 2 Y whose components ship from two locations. */
    public function testK55SplitSetNoteTagAndMetafieldAreWrittenOnce(): void
    {
        $distribution = [
            ['vare' => 'X', 'antal' => 1, 'lager' => 'Hovedlager'],
            ['vare' => 'Y', 'antal' => 2, 'lager' => 'Showroom'],
        ];
        $context = ['distribution' => $distribution, 'ordre_id' => 4711];
        $checkpoints = ['order.tags' => 'done', 'order.references' => 'done'];

        // The hold tag is placed by order.tags, which the note stage waits for.
        $this->stage('order.tags', [], ['allocation' => ['split_set' => true]]);
        $first = $this->stage('order.note', $checkpoints, $context);
        // The whole job is retried after its checkpoint was written.
        $second = $this->stage('order.note', $checkpoints + ['order.note' => 'done'], $context);

        $this->assertSame('done', $first['status']);
        $this->assertSame('done', $second['status']);
        $this->assertSame(['saldi-saet-delt'], $this->shop->tags);
        $this->assertSame(1, $this->shop->callsOf('saldiOrderNoteAppend'));
        $this->assertSame(1, $this->shop->callsOf('saldiOrderTagsAdd'));
        $this->assertSame(1, substr_count($this->shop->note, shopify_note_marker(shopify_writeback_note_op_key(4711))));
        $this->assertSame(
            '[{"vare":"X","antal":1,"lager":"Hovedlager"},{"vare":"Y","antal":2,"lager":"Showroom"}]',
            $this->shop->metafields['saldi.saet_fordeling']
        );
    }

    public function testTheSplitSetNoteNamesEveryComponentAndItsLocation(): void
    {
        $note = shopify_build_split_set_note([
            ['vare' => 'X', 'antal' => 1, 'lager' => 'Hovedlager'],
            ['vare' => 'Y', 'antal' => 2, 'lager' => 'Showroom'],
        ]);

        $this->assertSame("Sæt delt over flere lagre:\n- 1 x X: Hovedlager\n- 2 x Y: Showroom", $note);
    }

    public function testAnOrderWithoutASplitSetFinishesTheNoteStageWithoutWriting(): void
    {
        $outcome = $this->stage('order.note', ['order.tags' => 'done', 'order.references' => 'done'], []);

        $this->assertSame('done', $outcome['status']);
        $this->assertSame('done', $outcome['checkpoint']);
        $this->assertSame([], $this->shop->calls);
    }

    public function testTheProcessedStageAddsSaldiBehandletLast(): void
    {
        $outcome = $this->stage('order.processed', $this->allDoneExcept());

        $this->assertSame('done', $outcome['status']);
        $this->assertSame(['saldi-behandlet'], $this->shop->tags);
        $this->assertSame(['saldiOrderTagsAdd'], $this->shop->calls);
    }

    public function testAFailedStageRecordsNoCheckpoint(): void
    {
        $this->shop->scriptNextMutation(false, null, ['Tag limit reached']);

        $outcome = $this->stage('order.tags', [], ['allocation' => ['shortage' => true]]);

        $this->assertSame('failed', $outcome['status']);
        $this->assertNull($outcome['checkpoint'], 'a permanent failure leaves the order on the error list');
        $this->assertSame(['Tag limit reached'], $outcome['errors']);
    }

    public function testTheNoteStageReportsItsOpKeyForTheNoteOpsLog(): void
    {
        $context = ['distribution' => [['vare' => 'X', 'antal' => 1, 'lager' => 'Hovedlager']], 'ordre_id' => 4711];

        $outcome = $this->stage('order.note', ['order.tags' => 'done', 'order.references' => 'done'], $context);

        $this->assertSame('wp10.split_set:4711', $outcome['note_op']);
    }

    public function testAStageThatAppendsNothingReportsNoNoteOp(): void
    {
        $outcome = $this->stage('order.tags', [], ['allocation' => ['shortage' => true]]);

        $this->assertNull($outcome['note_op']);
    }

    public function testAFinishedStageIsNeverRequeuedOverAMissingEarlierCheckpoint(): void
    {
        $outcome = $this->stage('order.references', ['order.references' => 'done'], []);

        $this->assertSame('done', $outcome['status']);
        $this->assertNull($outcome['blocked_by']);
        $this->assertSame([], $this->shop->calls);
    }

    public function testTheMoveStageBelongsToL10b(): void
    {
        $this->expectException(ShopifyWritebackError::class);
        $this->stage('order.move', ['order.tags' => 'done']);
    }

    public function testEveryStageIsQueuedUnderTheSameDedupeKey(): void
    {
        $jobs = shopify_writeback_jobs('hml-dk:' . self::ORDER, 4711, self::ORDER);

        $this->assertSame(
            ['order.tags', 'order.move', 'order.references', 'order.note', 'order.processed'],
            array_column($jobs, 'type')
        );
        $this->assertSame([10, 15, 20, 25, 25], array_column($jobs, 'priority'));
        $this->assertSame(['hml-dk:' . self::ORDER], array_unique(array_column($jobs, 'dedupe_key')));
        $this->assertSame(['ordre_id' => 4711, 'order_gid' => self::ORDER], $jobs[0]['payload']);
    }

    public function testWithoutAQueueTheCallerIsTold(): void
    {
        $this->expectException(ShopifyWritebackError::class);
        shopify_writeback_enqueuer();
    }

    public function testAnInjectedEnqueuerReceivesEveryStage(): void
    {
        $seen = [];
        shopify_set_writeback_enqueuer(function (string $type, int $shopId, string $dedupe, int $priority, array $payload) use (&$seen): void {
            $seen[] = $type;
        });

        $enqueue = shopify_writeback_enqueuer();
        foreach (shopify_writeback_jobs('hml-dk:x', 4711, self::ORDER) as $job) {
            $enqueue($job['type'], 7, $job['dedupe_key'], $job['priority'], $job['payload']);
        }

        $this->assertSame(array_keys(shopify_writeback_stages()), $seen);
    }
}
