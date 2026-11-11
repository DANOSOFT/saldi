<?php
// tests/shopify/NoteAppendTest.php
//
// shopify_append_note() and shopify_add_tags() (SD-759, WP10-1) through shopify_graphql() against
// the shared fake store: a note survives byte for byte, a replay appends nothing, a lost response
// is reconciled by reading rather than resent blind, and tags only ever go through tagsAdd.
// No database needed.
//
// History:
// 20261022 CL/MJ SD-759: created.
// 20261109 CL/MJ SD-759: runs through shopify_graphql() and FakeShopifyStore instead of a test-only client.
// 20261111 CL/MJ SD-759 (Sol): a note edited while the update's answer was lost is kept.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/FakeShopifyStore.php';
require_once dirname(__DIR__, 2) . '/includes/shopify/noteAppend.php';

final class NoteAppendTest extends TestCase
{
    private const ORDER_CREATE = 'mutation orderCreate($order: OrderCreateOrderInput!) { orderCreate(order: $order) { order { id } userErrors { message } } }';

    private FakeShopifyStore $store;
    private string $domain;
    private string $order;
    /** @var callable|null replaces the store's answer to the next orderUpdate */
    private $onUpdate = null;

    protected function setUp(): void
    {
        $this->store = new FakeShopifyStore();
        $this->domain = $this->store->addShop('hml')['domain'];
        $this->order = $this->placeOrder('Ring før levering');
        $this->store->calls = [];
    }

    private function placeOrder(?string $note): string
    {
        $item = $this->store->seedProduct($this->domain, 'havestol-' . count($this->store->calls), 'SD759-' . count($this->store->calls));
        $vars = ['order' => ['lineItems' => [['variantId' => $item['variant'], 'quantity' => 1]], 'note' => $note, 'tags' => ['dtails-hold']]];
        $answer = json_decode($this->store->request($this->domain, self::ORDER_CREATE, $vars)['body'], true);
        return $answer['data']['orderCreate']['order']['id'];
    }

    /** The runtime the functions get: the store as transport; $this->onUpdate can take over an orderUpdate. */
    private function rt(): array
    {
        $transport = function (string $url, array $headers, string $body, float $connect, float $timeout): array {
            if ($this->onUpdate !== null && str_contains($body, 'orderUpdate')) {
                $take = $this->onUpdate;
                $this->onUpdate = null;
                return $take($url, $headers, $body, $connect, $timeout);
            }
            return $this->store->send($url, $headers, $body, $connect, $timeout);
        };
        return ['graphql' => $this->store->opts(['transport' => $transport])];
    }

    private function append(string $opKey, string $text, ?string $order = null): array
    {
        return shopify_note_append_to($this->store->shopRow($this->domain), $order ?? $this->order, $opKey, $text, $this->rt());
    }

    private function note(): string
    {
        return (string)$this->store->shop($this->domain)['orders'][$this->order]['note'];
    }

    /** @return list<string> */
    private function roots(): array
    {
        return array_merge(...array_map(fn(array $c): array => $c['roots'], $this->store->calls) ?: [[]]);
    }

    public function testTheMarkerIsHashedAndStable(): void
    {
        $marker = shopify_note_marker('credit:K-1042');
        $this->assertSame('[saldi:' . hash('sha256', 'credit:K-1042') . ']', $marker);
        $this->assertSame($marker, shopify_note_marker('credit:K-1042'));
        $this->assertStringNotContainsString('K-1042', $marker, 'the op_key never shows in the note');
    }

    public function testHasMarkerAnswersForOneOpKeyOnly(): void
    {
        $note = shopify_note_compose('', 'Sæt delt', 'a', '2026-11-09');
        $this->assertTrue(shopify_note_has_marker($note, 'a'));
        $this->assertFalse(shopify_note_has_marker($note, 'b'));
    }

    public function testComposeKeepsTheExistingNoteByteForByte(): void
    {
        $existing = "  Ring før levering \r\nPort 3 ";
        $composed = shopify_note_compose($existing, 'Sæt delt', 'a', '2026-11-09');
        $this->assertSame($existing, substr($composed, 0, strlen($existing)));
        $this->assertSame($existing . "\n2026-11-09 Sæt delt " . shopify_note_marker('a'), $composed);
    }

    public function testComposeOnAnEmptyNoteAddsNoLeadingNewline(): void
    {
        $this->assertSame('2026-11-09 Sæt delt ' . shopify_note_marker('a'), shopify_note_compose('', 'Sæt delt', 'a', '2026-11-09'));
    }

    public function testAnAppendAddsOneLineAndOneMarker(): void
    {
        $result = $this->append('op-1', 'Sæt delt over flere lagre');

        $this->assertSame('appended', $result['status']);
        $this->assertTrue($result['appended']);
        $this->assertStringStartsWith("Ring før levering\n", $this->note(), 'the note HML had is kept as it was');
        $this->assertSame(1, substr_count($this->note(), shopify_note_marker('op-1')));
        $this->assertSame($this->note(), $result['note']);
    }

    public function testAReplayAppendsNothingAndLeavesExactlyOneMarker(): void
    {
        $this->append('op-1', 'Sæt delt');
        $before = $this->note();

        $result = $this->append('op-1', 'Sæt delt');

        $this->assertSame('already_present', $result['status']);
        $this->assertFalse($result['appended']);
        $this->assertSame($before, $this->note(), 'byte for byte');
        $this->assertSame(1, count(array_keys($this->roots(), 'orderUpdate')), 'the replay only read the note');
    }

    public function testADifferentOpKeyStillAppends(): void
    {
        $this->append('op-1', 'Første');
        $this->assertSame('appended', $this->append('op-2', 'Anden')['status']);
        $this->assertSame(1, substr_count($this->note(), shopify_note_marker('op-1')));
        $this->assertSame(1, substr_count($this->note(), shopify_note_marker('op-2')));
    }

    public function testALostResponseIsReconciledByReadingNotResent(): void
    {
        $this->onUpdate = function (...$request): array {
            $this->store->send(...$request);
            return FakeShopify::timeout();
        };

        $result = $this->append('op-1', 'Sæt delt');

        $this->assertSame('reconciled', $result['status']);
        $this->assertTrue($result['appended']);
        $this->assertSame(1, count(array_keys($this->roots(), 'orderUpdate')), 'the update that went through is not sent again');
        $this->assertSame(1, substr_count($this->note(), shopify_note_marker('op-1')));
    }

    public function testAnUpdateThatNeverAppliedIsSentAgainOnlyAfterTheReadFindsNoMarker(): void
    {
        $this->onUpdate = fn(): array => FakeShopify::timeout();

        $result = $this->append('op-1', 'Sæt delt');

        $this->assertSame('appended', $result['status']);
        $this->assertSame(['order', 'order', 'orderUpdate'], $this->roots(), 'read, read after the lost answer, then the one update that reached the store');
        $this->assertSame(1, substr_count($this->note(), shopify_note_marker('op-1')));
    }

    public function testANoteEditedWhileTheAnswerWasLostIsKeptAndTheNextAttemptAppendsToIt(): void
    {
        $this->onUpdate = function (): array {
            $this->store->shop($this->domain)['orders'][$this->order]['note'] = "Ring før levering\nKunden henter selv";
            return FakeShopify::timeout();
        };

        $first = $this->append('op-1', 'Sæt delt');

        $this->assertSame('failed', $first['status'], 'not resent over the edit');
        $this->assertSame(['order', 'order'], $this->roots(), 'the read and the reconcile read, no second update');
        $this->assertSame("Ring før levering\nKunden henter selv", $this->note());

        $again = $this->append('op-1', 'Sæt delt');

        $this->assertSame('appended', $again['status']);
        $this->assertStringStartsWith("Ring før levering\nKunden henter selv\n", $this->note(), 'appended to the edited note');
        $this->assertSame(1, substr_count($this->note(), shopify_note_marker('op-1')));
    }

    public function testAnOrderThatCannotBeReadIsNeverAppendedTo(): void
    {
        $result = $this->append('op-1', 'Sæt delt', 'gid://shopify/Order/999999999');

        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['note']);
        $this->assertNotEmpty($result['errors']);
        $this->assertNotContains('orderUpdate', $this->roots());
    }

    public function testTagsGoThroughTagsAddAndLeaveForeignTagsAlone(): void
    {
        $result = shopify_tags_add_to($this->store->shopRow($this->domain), $this->order, ['saldi-mangler-lager'], $this->rt());

        $this->assertSame('added', $result['status']);
        $this->assertSame(['tagsAdd'], $this->roots());
        $tags = $this->store->shop($this->domain)['orders'][$this->order]['tags'];
        $this->assertContains('dtails-hold', $tags, 'a tag DTAILS set stays');
        $this->assertContains('saldi-mangler-lager', $tags);
    }

    public function testBlankAndDuplicateTagsAreDroppedAndAnEmptyListCostsNoCall(): void
    {
        $this->assertSame(['a', 'b'], shopify_clean_tags([' a', 'b', '', 'a ', '  ']));
        $result = shopify_tags_add_to($this->store->shopRow($this->domain), $this->order, ['', ' '], $this->rt());
        $this->assertSame('noop', $result['status']);
        $this->assertSame([], $this->store->calls);
    }

    public function testATagsAddOnAMissingOrderFails(): void
    {
        $result = shopify_tags_add_to($this->store->shopRow($this->domain), 'gid://shopify/Order/999999999', ['x'], $this->rt());
        $this->assertSame('failed', $result['status']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testAnOrderGidOfAnotherResourceIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->append('op-1', 'x', 'gid://shopify/Customer/1');
    }

    public function testAnEmptyOpKeyIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->append(' ', 'x');
    }
}
