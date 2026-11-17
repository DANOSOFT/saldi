<?php
// tests/shopify/OrderWritebackTest.php
//
// The write-back stages (SD-759, WP10-1) as decisions: which checkpoints each stage waits for,
// and what each one writes on the order, through shopify_graphql() against the shared fake
// store. No database needed; OrderWritebackSyncTest runs the worker handler on a tenant.
//
// History:
// 20261022 CL/MJ SD-759: created.
// 20261109 CL/MJ SD-759: the stages run against FakeShopifyStore; order.move counts only once it is recorded.
// 20261112 CL/MJ SD-759 (review): a recorded note op is not appended again; two split sets of one bundle keep apart.
// 20261112 CL/MJ SD-759 (CodeRabbit): the invoice link's token stays out of the logged request.
// 20261113 CL/MJ SD-759 (review): the token test loads logging.php in its own process, so the rest of the run keeps a client without a redactor.
// 20261114 CL/MJ SD-760 (CI): orderWriteback.php now loads orderMove.php and with it logging.php.
//                It is required when the class starts, not while PHPUnit collects the files.

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/FakeShopifyStore.php';

final class OrderWritebackTest extends TestCase
{
    private const ORDER_CREATE = 'mutation orderCreate($order: OrderCreateOrderInput!) { orderCreate(order: $order) { order { id } userErrors { message } } }';
    private const DISTRIBUTION = [
        ['saet' => 'HAVESAET-1', 'vare' => 'STOL-X', 'antal' => '1', 'lager' => 'Mårum'],
        ['saet' => 'HAVESAET-1', 'vare' => 'BORD-Y', 'antal' => '2.000', 'lager' => 'Hillerød'],
    ];

    private FakeShopifyStore $store;
    private string $domain;
    private string $order;

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/shopify/services/orderWriteback.php';
    }

    protected function setUp(): void
    {
        $this->store = new FakeShopifyStore();
        $this->domain = $this->store->addShop('hml')['domain'];
        $item = $this->store->seedProduct($this->domain, 'havesaet', 'SD759-1');
        $vars = ['order' => ['lineItems' => [['variantId' => $item['variant'], 'quantity' => 1]], 'note' => 'Ring før levering']];
        $answer = json_decode($this->store->request($this->domain, self::ORDER_CREATE, $vars)['body'], true);
        $this->order = $answer['data']['orderCreate']['order']['id'];
        $this->store->calls = [];
    }

    private function stage(string $stage, array $checkpoints, array $context = []): array
    {
        return shopify_writeback_stage($this->store->shopRow($this->domain), $this->order, $stage, $context, $checkpoints, ['graphql' => $this->store->opts()]);
    }

    private function orderRow(): array
    {
        return $this->store->shop($this->domain)['orders'][$this->order];
    }

    /** @return array<string, string> saldi.<key> => value */
    private function metafields(): array
    {
        return array_map(fn(array $m): string => (string)$m['value'], $this->store->shop($this->domain)['metafields'][$this->order] ?? []);
    }

    /** @return list<string> */
    private function roots(): array
    {
        return array_merge([], ...array_map(fn(array $c): array => $c['roots'], $this->store->calls));
    }

    private function allDoneExcept(string ...$missing): array
    {
        $checkpoints = ['order.tags' => 'done', 'order.references' => 'done', 'order.note' => 'done'];
        foreach ($missing as $stage) {
            unset($checkpoints[$stage]);
        }
        return $checkpoints;
    }

    public function testHoldTagsHaveNoPrerequisites(): void
    {
        $this->assertNull(shopify_writeback_blocked_by([], 'order.tags'));
    }

    public function testReferencesAndTheMoveWaitForTheHoldTags(): void
    {
        $this->assertSame('order.tags', shopify_writeback_blocked_by([], 'order.references'));
        $this->assertSame('order.tags', shopify_writeback_blocked_by([], 'order.move'));
        $this->assertNull(shopify_writeback_blocked_by(['order.tags' => 'done'], 'order.references'));
    }

    public function testTheNoteWaitsForTheReferences(): void
    {
        $this->assertSame('order.references', shopify_writeback_blocked_by(['order.tags' => 'done'], 'order.note'));
    }

    public function testSaldiBehandletWaitsForEveryEarlierStage(): void
    {
        foreach (['order.tags', 'order.references', 'order.note'] as $missing) {
            $this->assertSame($missing, shopify_writeback_blocked_by($this->allDoneExcept($missing), 'order.processed'));
        }
        $this->assertNull(shopify_writeback_blocked_by($this->allDoneExcept(), 'order.processed'), 'an order with no move recorded needs none');
    }

    public function testARecordedMoveHoldsSaldiBehandletUntilItIsDoneOrAbandoned(): void
    {
        $this->assertSame('order.move', shopify_writeback_blocked_by($this->allDoneExcept() + ['order.move' => 'queued'], 'order.processed'));
        $this->assertNull(shopify_writeback_blocked_by($this->allDoneExcept() + ['order.move' => 'done'], 'order.processed'));
        $this->assertNull(shopify_writeback_blocked_by($this->allDoneExcept() + ['order.move' => 'abandoned'], 'order.processed'));
    }

    public function testOnlyAMoveMayBeAbandoned(): void
    {
        $this->expectException(InvalidArgumentException::class);
        shopify_writeback_mark(1, 'order.tags', 'abandoned');
    }

    public function testAnUnknownStageIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        shopify_writeback_prerequisites('order.tagz');
    }

    public function testTheHoldTagsStageWritesTheIntakesTagsWithTagsAdd(): void
    {
        $result = $this->stage('order.tags', [], ['tags' => ['saldi-mangler-lager', 'saldi-saet-delt']]);

        $this->assertSame(['done', 'done'], [$result['status'], $result['checkpoint']]);
        $this->assertSame(['tagsAdd'], $this->roots());
        $this->assertSame(['saldi-mangler-lager', 'saldi-saet-delt'], $this->orderRow()['tags']);
    }

    public function testAnOrderWithNoHoldsStillFinishesTheTagsStageWithoutACall(): void
    {
        $result = $this->stage('order.tags', [], ['tags' => []]);
        $this->assertSame(['done', 'done'], [$result['status'], $result['checkpoint']]);
        $this->assertSame([], $this->store->calls);
    }

    public function testATagHmlRemovedIsNotPutBackOnceTheStageIsDone(): void
    {
        $result = $this->stage('order.tags', ['order.tags' => 'done'], ['tags' => ['saldi-mangler-lager']]);

        $this->assertSame('done', $result['status']);
        $this->assertNull($result['checkpoint'], 'nothing new to record');
        $this->assertSame([], $this->store->calls);
    }

    public function testABlockedStageWritesNothing(): void
    {
        $result = $this->stage('order.references', [], ['references' => ['ordre_nr' => 7]]);

        $this->assertSame(['blocked', 'order.tags', null], [$result['status'], $result['blocked_by'], $result['checkpoint']]);
        $this->assertSame([], $this->store->calls);
    }

    public function testK23AndK45TheOrderAndInvoiceNumberAndTheInvoiceLinkAreSetAsMetafields(): void
    {
        $context = ['references' => ['ordre_nr' => 4711, 'faktura_nr' => '1042'], 'invoice_url' => 'https://saldi.example/api/dokument.php?t=abc'];

        $result = $this->stage('order.references', ['order.tags' => 'done'], $context);

        $this->assertSame(['done', 'done'], [$result['status'], $result['checkpoint']]);
        $this->assertSame(['metafieldsSet'], $this->roots());
        $this->assertSame(['saldi.ordre_nr' => '4711', 'saldi.faktura_nr' => '1042', 'saldi.faktura_link' => 'https://saldi.example/api/dokument.php?t=abc'], $this->metafields());
    }

    /** The token opens the invoice; the call log keeps request bodies for 90 days. */
    /** logging.php defines shopify_redact(), which the client then uses for every call; in a process of its own it reaches no other test. */
    #[RunInSeparateProcess]
    public function testTheInvoiceLinksTokenIsSentButNotLogged(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/shopify/logging.php';
        $token = str_repeat('Ab3_-', 8) . 'xyz';
        $url = 'https://saldi.example/api/dokument.php?t=' . $token;
        $logged = [];
        $opts = $this->store->opts(['log' => function (array $entry) use (&$logged): void { $logged[] = $entry; }]);
        $context = ['references' => ['ordre_nr' => 4711, 'faktura_nr' => '1042'], 'invoice_url' => $url];

        $result = shopify_writeback_stage($this->store->shopRow($this->domain), $this->order, 'order.references', $context, ['order.tags' => 'done'], ['graphql' => $opts]);

        $this->assertSame('done', $result['status']);
        $this->assertSame($url, $this->metafields()['saldi.faktura_link'], 'Shopify gets the working link');
        $request = base64_decode((string)($logged[0]['request_b64'] ?? ''));
        $this->assertStringContainsString('dokument.php?t=[redacted]', $request);
        $this->assertStringNotContainsString($token, $request);
    }

    public function testAMissingValueIsLeftOutRatherThanWrittenEmpty(): void
    {
        $fields = shopify_build_order_metafields(['order_gid' => $this->order, 'ordre_nr' => 4711, 'faktura_nr' => ' '], null);
        $this->assertSame(['ordre_nr'], array_column($fields, 'key'));
    }

    public function testK55TheSplitSetNoteAndMetafieldAreWrittenOnce(): void
    {
        $context = ['ordre_id' => 31, 'distribution' => self::DISTRIBUTION];
        $checkpoints = ['order.tags' => 'done', 'order.references' => 'done'];

        $first = $this->stage('order.note', $checkpoints, $context);
        $note = $this->orderRow()['note'];
        $replay = $this->stage('order.note', $checkpoints, $context);

        $this->assertSame(['done', 'done', 'wp10.split_set:31'], [$first['status'], $first['checkpoint'], $first['note_op']]);
        $this->assertStringStartsWith("Ring før levering\n", $note);
        $this->assertStringContainsString("Sæt HAVESAET-1 delt over flere lagre:\n- 1 x STOL-X: Mårum\n- 2 x BORD-Y: Hillerød", $note);
        $this->assertSame('done', $replay['status']);
        $this->assertSame($note, $this->orderRow()['note'], 'a replay appends nothing');
        $this->assertSame(1, substr_count($note, shopify_note_marker('wp10.split_set:31')));
        $this->assertSame(self::DISTRIBUTION, json_decode($this->metafields()['saldi.saet_fordeling'], true));
        $this->assertSame(1, count(array_keys($this->roots(), 'orderUpdate')), 'the note is sent once');
    }

    public function testTheNoteNamesEverySplitSet(): void
    {
        $two = array_merge(self::DISTRIBUTION, [['saet' => 'LOUNGE-2', 'vare' => 'PUDE', 'antal' => '4', 'lager' => 'Mårum']]);
        $this->assertSame(
            "Sæt HAVESAET-1 delt over flere lagre:\n- 1 x STOL-X: Mårum\n- 2 x BORD-Y: Hillerød\nSæt LOUNGE-2 delt over flere lagre:\n- 4 x PUDE: Mårum",
            shopify_build_split_set_note($two)
        );
    }

    public function testTwoSplitSetsOfTheSameBundleGetAHeaderEach(): void
    {
        $rows = [
            ['saet' => 'HAVESAET-1', 'saet_id' => 1, 'vare' => 'STOL-X', 'antal' => 1, 'lager' => 'Mårum'],
            ['saet' => 'HAVESAET-1', 'saet_id' => 1, 'vare' => 'BORD-Y', 'antal' => 1, 'lager' => 'Hillerød'],
            ['saet' => 'HAVESAET-1', 'saet_id' => 2, 'vare' => 'STOL-X', 'antal' => 1, 'lager' => 'Hillerød'],
            ['saet' => 'HAVESAET-1', 'saet_id' => 2, 'vare' => 'BORD-Y', 'antal' => 1, 'lager' => 'Mårum'],
        ];
        $this->assertSame(
            "Sæt HAVESAET-1 delt over flere lagre:\n- 1 x STOL-X: Mårum\n- 1 x BORD-Y: Hillerød\nSæt HAVESAET-1 delt over flere lagre:\n- 1 x STOL-X: Hillerød\n- 1 x BORD-Y: Mårum",
            shopify_build_split_set_note($rows)
        );
    }

    /** The append is in note_ops (the metafield failed after it); the line was deleted on the order since. */
    public function testARecordedNoteOpIsNotAppendedAgain(): void
    {
        $context = ['ordre_id' => 31, 'note_recorded' => true, 'distribution' => self::DISTRIBUTION];

        $result = $this->stage('order.note', ['order.tags' => 'done', 'order.references' => 'done'], $context);

        $this->assertSame(['done', 'done'], [$result['status'], $result['checkpoint']]);
        $this->assertSame('Ring før levering', $this->orderRow()['note']);
        $this->assertSame(['metafieldsSet'], $this->roots(), 'only the metafield is sent');
    }

    public function testAnOrderWithoutASplitSetFinishesTheNoteStageWithoutACall(): void
    {
        $result = $this->stage('order.note', ['order.tags' => 'done', 'order.references' => 'done'], ['ordre_id' => 31, 'distribution' => []]);
        $this->assertSame(['done', 'done', null], [$result['status'], $result['checkpoint'], $result['note_op']]);
        $this->assertSame([], $this->store->calls);
    }

    public function testTheProcessedStageAddsSaldiBehandlet(): void
    {
        $result = $this->stage('order.processed', $this->allDoneExcept());
        $this->assertSame(['done', 'done'], [$result['status'], $result['checkpoint']]);
        $this->assertSame(['saldi-behandlet'], $this->orderRow()['tags']);
    }

    public function testSaldiBehandletIsNotSetWhileTheTagsStageIsUnfinished(): void
    {
        $result = $this->stage('order.processed', $this->allDoneExcept('order.tags'));
        $this->assertSame(['blocked', 'order.tags'], [$result['status'], $result['blocked_by']]);
        $this->assertSame([], $this->orderRow()['tags']);
    }

    public function testAFailedStageRecordsNoCheckpoint(): void
    {
        $missing = shopify_writeback_stage($this->store->shopRow($this->domain), 'gid://shopify/Order/999999999', 'order.tags', ['tags' => ['x']], [], ['graphql' => $this->store->opts()]);
        $this->assertSame(['failed', null], [$missing['status'], $missing['checkpoint']]);
        $this->assertNotEmpty($missing['errors']);
    }

    public function testTheMoveStageBelongsToL10b(): void
    {
        $this->expectException(LogicException::class);
        $this->stage('order.move', ['order.tags' => 'done']);
    }
}
