<?php
// tests/shopify/NoteAppendTest.php
//
// shopify_append_note() and shopify_add_tags() (SD-759, WP10-1) against a fake Shopify
// order: a note survives byte for byte, a replay appends nothing, a lost response is
// reconciled by reading rather than resent, and tags only ever go through tagsAdd.
// No database needed.
//
// History:
// 20261022 CL/MJ SD-759: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/FakeShopifyGraphql.php';

final class NoteAppendTest extends TestCase
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
    }

    public function testTheMarkerIsHashedAndStable(): void
    {
        $marker = shopify_note_marker('faktura:4711');
        $this->assertSame('[saldi:' . hash('sha256', 'faktura:4711') . ']', $marker);
        $this->assertStringNotContainsString('4711', $marker, 'the op_key must not be readable in the shop note');
        $this->assertSame($marker, shopify_note_marker('faktura:4711'));
        $this->assertNotSame($marker, shopify_note_marker('faktura:4712'));
    }

    public function testHasMarkerAnswersForOneOpKeyOnly(): void
    {
        $note = shopify_note_compose('Eksisterende note', 'Delt sæt', 'op-1', '2026-10-22');

        $this->assertTrue(shopify_note_has_marker($note, 'op-1'));
        $this->assertFalse(shopify_note_has_marker($note, 'op-2'));
        $this->assertFalse(shopify_note_has_marker('', 'op-1'));
    }

    public function testComposeKeepsTheExistingNoteByteForByte(): void
    {
        $existing = "Kunden ringer før levering.\r\n  Ønsker æ, ø og å bevaret.  \n";
        $composed = shopify_note_compose($existing, 'Delt sæt', 'op-1', '2026-10-22');

        $this->assertSame($existing, substr($composed, 0, strlen($existing)));
        $this->assertSame("\n2026-10-22 Delt sæt " . shopify_note_marker('op-1'), substr($composed, strlen($existing)));
    }

    public function testComposeOnAnEmptyNoteAddsNoLeadingNewline(): void
    {
        $this->assertSame(
            '2026-10-22 Delt sæt ' . shopify_note_marker('op-1'),
            shopify_note_compose('', 'Delt sæt', 'op-1', '2026-10-22')
        );
    }

    public function testAnAppendAddsOneLineAndOneMarker(): void
    {
        $this->shop->note = 'Eksisterende note';

        $result = shopify_append_note(7, self::ORDER, 'op-1', 'Delt sæt over to lagre');

        $this->assertSame('appended', $result['status']);
        $this->assertTrue($result['appended']);
        $this->assertStringStartsWith("Eksisterende note\n", $this->shop->note);
        $this->assertSame(1, substr_count($this->shop->note, $result['marker']));
        $this->assertSame(1, $this->shop->callsOf('saldiOrderNoteAppend'));
    }

    public function testAReplayAppendsNothingAndLeavesExactlyOneMarker(): void
    {
        $this->shop->note = 'Eksisterende note';
        $first = shopify_append_note(7, self::ORDER, 'op-1', 'Delt sæt over to lagre');
        $after = $this->shop->note;

        $second = shopify_append_note(7, self::ORDER, 'op-1', 'Delt sæt over to lagre');

        $this->assertSame('already_present', $second['status']);
        $this->assertFalse($second['appended']);
        $this->assertSame($after, $this->shop->note, 'a replay must not touch the note');
        $this->assertSame(1, substr_count($this->shop->note, $first['marker']));
        $this->assertSame(1, $this->shop->callsOf('saldiOrderNoteAppend'), 'the second call must not mutate');
    }

    public function testADifferentOpKeyStillAppends(): void
    {
        shopify_append_note(7, self::ORDER, 'op-1', 'Første');
        shopify_append_note(7, self::ORDER, 'op-2', 'Anden');

        $this->assertSame(1, substr_count($this->shop->note, shopify_note_marker('op-1')));
        $this->assertSame(1, substr_count($this->shop->note, shopify_note_marker('op-2')));
        $this->assertSame(2, $this->shop->callsOf('saldiOrderNoteAppend'));
    }

    public function testALostResponseIsReconciledByReadingNotResent(): void
    {
        $this->shop->note = 'Eksisterende note';
        // Shopify applied the update; the response never came back.
        $this->shop->scriptNextMutation(true, new RuntimeException('cURL error 28: operation timed out'));

        $result = shopify_append_note(7, self::ORDER, 'op-1', 'Delt sæt over to lagre');

        $this->assertSame('reconciled', $result['status']);
        $this->assertTrue($result['appended']);
        $this->assertSame(1, $this->shop->callsOf('saldiOrderNoteAppend'), 'the mutation must never be resent');
        $this->assertSame(1, substr_count($this->shop->note, $result['marker']));
        $this->assertSame(['saldiOrderNote', 'saldiOrderNoteAppend', 'saldiOrderNote'], $this->shop->calls);
    }

    public function testAMutationThatNeverAppliedFailsWithoutResending(): void
    {
        $this->shop->note = 'Eksisterende note';
        $this->shop->scriptNextMutation(false, new RuntimeException('connection reset'));

        $result = shopify_append_note(7, self::ORDER, 'op-1', 'Delt sæt over to lagre');

        $this->assertSame('failed', $result['status']);
        $this->assertFalse($result['appended']);
        $this->assertSame('Eksisterende note', $this->shop->note);
        $this->assertSame(1, $this->shop->callsOf('saldiOrderNoteAppend'));
        $this->assertSame(['connection reset'], $result['errors']);
    }

    public function testARetryAfterAFailureStillAppendsOnlyOnce(): void
    {
        $this->shop->scriptNextMutation(true, new RuntimeException('cURL error 28: operation timed out'));
        $first = shopify_append_note(7, self::ORDER, 'op-1', 'Delt sæt');

        $second = shopify_append_note(7, self::ORDER, 'op-1', 'Delt sæt');

        $this->assertSame('reconciled', $first['status']);
        $this->assertSame('already_present', $second['status']);
        $this->assertSame(1, substr_count($this->shop->note, $first['marker']));
    }

    public function testUserErrorsMakeTheAppendFail(): void
    {
        $this->shop->scriptNextMutation(false, null, ['Note is too long']);

        $result = shopify_append_note(7, self::ORDER, 'op-1', 'Delt sæt');

        $this->assertSame('failed', $result['status']);
        $this->assertSame(['Note is too long'], $result['errors']);
        $this->assertSame('', $this->shop->note);
    }

    public function testAnUnreadableOrderIsNeverAppendedTo(): void
    {
        $this->shop->orderExists = false;

        $result = shopify_append_note(7, self::ORDER, 'op-1', 'Delt sæt');

        $this->assertSame('failed', $result['status']);
        $this->assertSame(0, $this->shop->callsOf('saldiOrderNoteAppend'), 'no read, no write');
    }

    public function testTagsGoThroughTagsAddOnly(): void
    {
        $result = shopify_add_tags(7, self::ORDER, ['saldi-mangler-lager', 'saldi-saet-delt']);

        $this->assertSame('added', $result['status']);
        $this->assertSame(['saldi-mangler-lager', 'saldi-saet-delt'], $this->shop->tags);
        $this->assertSame(['saldiOrderTagsAdd'], $this->shop->calls);
        $this->assertSame(0, $this->shop->callsOf('saldiOrderNoteAppend'), 'tags must never ride on orderUpdate');
    }

    public function testTagsAddLeavesForeignTagsAlone(): void
    {
        $this->shop->tags = ['dtails-hold', 'easyroutes-ready'];

        shopify_add_tags(7, self::ORDER, ['saldi-behandlet']);

        $this->assertSame(['dtails-hold', 'easyroutes-ready', 'saldi-behandlet'], $this->shop->tags);
    }

    public function testBlankAndDuplicateTagsAreDropped(): void
    {
        $result = shopify_add_tags(7, self::ORDER, ['saldi-behandlet', ' saldi-behandlet ', '', '  ']);

        $this->assertSame(['saldi-behandlet'], $result['tags']);
        $this->assertSame(['saldi-behandlet'], $this->shop->tags);
    }

    public function testAnEmptyTagListCostsNoCall(): void
    {
        $result = shopify_add_tags(7, self::ORDER, []);

        $this->assertSame('noop', $result['status']);
        $this->assertSame([], $this->shop->calls);
    }

    public function testAnOrderGidOfAnotherResourceIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        shopify_append_note(7, 'gid://shopify/Product/5544332211000', 'op-1', 'Delt sæt');
    }

    public function testAnEmptyOpKeyIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        shopify_note_marker('  ');
    }

    public function testWithoutARegisteredClientTheCallerIsTold(): void
    {
        shopify_set_graphql_client(null);

        $this->expectException(ShopifyWritebackError::class);
        shopify_append_note(7, self::ORDER, 'op-1', 'Delt sæt');
    }
}
