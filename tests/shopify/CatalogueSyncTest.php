<?php
// tests/shopify/CatalogueSyncTest.php
//
// The catalogue on a throwaway tenant against a fake Shopify store (SD-741): an item becomes a
// draft in the primary shop with a link and the VAT metafield; nothing is sent while unchanged;
// a rename updates the same product and HML's description, status and tags survive; a create that
// reached Shopify before a crash, or timed out, is found again and never made twice; a product HML
// already has is linked by exact SKU, also past the first page of loose search hits; a product with
// other variants gets nothing; the other shops link by exact SKU (unlisted when absent, an error
// when ambiguous) and a stale link is looked up again or dropped with its GIDs; a product linked
// to another item, or a saldi-<id> handle with another SKU, is never taken over; a replaced
// variant with the item's SKU is followed; a refusal goes on the error list, a network failure is
// retried and running out of time yields; the varekort producer queues after commit, nothing on
// rollback, a follow-up behind a running job, and brings a job in backoff forward; closed items
// ('1' or 'on') are left out; the seed queues the webshop items in chunks without re-checking
// links and stops when its lease is lost. Skips without ShopifyEnv.
//
// History:
// 20261029 CL/SS SD-741: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';
require_once __DIR__ . '/support/FakeCatalogueShop.php';

final class CatalogueSyncTest extends TestCase
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
            self::$db = ShopifyEnv::testDb('_c');
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
        $this->state = tempnam(sys_get_temp_dir(), 'sd741_');
        unlink($this->state);
        foreach (['shopify_product_links', 'shopify_jobs', 'shopify_errors', 'shopify_shops'] as $table) {
            $this->sql("DELETE FROM $table");
        }
        $this->sql("DELETE FROM varer WHERE varenr LIKE 'SD741-%'");
        $this->sql("DELETE FROM grupper WHERE art = 'RA' OR (art = 'VG' AND kodenr IN (900, 901, 902)) OR (art = 'SM' AND kodenr = 77)");
        $this->sql("DELETE FROM kontoplan WHERE kontonr = 9001");
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('RA', 1, 'RA', '01', $1, '12', $1, 'on')", [date('Y')]);
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, box4, box7, box8, fiscal_year) VALUES ('Havemøbler', 900, 'VG', '9001', '', 'on', 1), ('Montage', 901, 'VG', '9001', '', '', 1)");
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, box2) VALUES ('Salgsmoms', 77, 'SM', '25')");
        $this->sql("INSERT INTO kontoplan (kontonr, beskrivelse, moms, regnskabsaar) VALUES (9001, 'Salg', 'S77', 1)");
        $this->sql("INSERT INTO shopify_shops (shop_key, domain, is_primary, products_enabled) VALUES
            ('hml', 'hml-test.myshopify.com', true, true), ('hms', 'hms-test.myshopify.com', false, true), ('di', 'di-test.myshopify.com', false, false)");
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            pg_close($this->conn);
        }
        if ($this->state !== '' && is_file($this->state)) {
            unlink($this->state);
        }
    }

    private function sql(string $sql, array $params = []): array
    {
        return ShopifyEnv::rows($this->conn, $sql, $params);
    }

    private function item(string $varenr, array $fields = []): int
    {
        $fields += ['beskrivelse' => "Havestol $varenr", 'stregkode' => '5701234567890', 'kostpris' => 800, 'gruppe' => 900,
            'lukket' => '', 'grossweight' => 7.5, 'grossweightunit' => 'kg'];
        $cols = implode(', ', array_keys($fields));
        $marks = implode(', ', array_map(fn(int $i): string => '$' . ($i + 2), array_keys(array_values($fields))));
        return (int)$this->sql("INSERT INTO varer (varenr, $cols) VALUES ($1, $marks) RETURNING id", array_merge(["SD741-$varenr"], array_values($fields)))[0]['id'];
    }

    /** Runs steps in a child process against the fake store kept in $this->state. */
    private function steps(array ...$steps): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_catalogue.php', [self::$db, $this->state, json_encode($steps)]));
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        return $run['result'];
    }

    private function link(int $vareId, string $shop = 'hml'): ?array
    {
        return $this->sql('SELECT l.* FROM shopify_product_links l JOIN shopify_shops s ON s.id = l.shop_id WHERE s.shop_key = $1 AND l.vare_id = $2', [$shop, $vareId])[0] ?? null;
    }

    public function testAnItemBecomesADraftAndIsNotSentAgainWhileUnchanged(): void
    {
        $id = $this->item('A1');
        $r = $this->steps(['sync', 'hml', $id], ['sync', 'hml', $id, false], ['sync', 'hml', $id, true]);

        $this->assertSame('created', $r['results'][0]['result']);
        $this->assertSame(['catalogueByHandle', 'catalogueBySku', 'catalogueSet', 'catalogueMetafields', 'catalogueRead'], $r['ops'],
            'looked up first, then created; unchanged: nothing from the seed, one read from an item card save');
        $product = array_values($r['products'])[0];
        $this->assertSame(['DRAFT', "saldi-$id", 'Havestol A1'], [$product['status'], $product['handle'], $product['title']]);
        $this->assertSame(['SD741-A1', '5701234567890', 'DENY', true], [$product['variants'][0]['sku'], $product['variants'][0]['barcode'],
            $product['variants'][0]['inventoryPolicy'], $product['variants'][0]['inventoryItem']['tracked']]);
        $link = $this->link($id);
        $this->assertSame([$product['id'], $product['variants'][0]['id'], 't'], [$link['product_gid'], $link['variant_gid'], $link['listed']]);
        $this->assertNotNull($link['last_hash']);
        $this->assertSame(['value' => '25.00', 'type' => 'number_decimal'], $r['metafields'][$link['variant_gid'] . '|saldi.vat_rate']);
        $this->assertSame('unchanged', $r['results'][1]['result']);
        $this->assertSame('unchanged', $r['results'][2]['result']);
    }

    public function testAnUnchangedSaveStillNoticesADeletedProductOrAReplacedVariant(): void
    {
        $id = $this->item('A5');
        $gid = $this->steps(['sync', 'hml', $id])['results'][0]['product_gid'];
        $fake = unserialize(file_get_contents($this->state));
        $fake->products[$gid]['variants'][0]['id'] = 'gid://shopify/ProductVariant/777';
        file_put_contents($this->state, serialize($fake));

        $r = $this->steps(['sync', 'hml', $id]);

        $this->assertSame(['result' => 'updated', 'product_gid' => $gid], $r['results'][0]);
        $this->assertSame(['catalogueRead', 'catalogueSet', 'catalogueMetafields'], $r['ops'], 'the new variant gets the owned values it may lack');
        $this->assertSame('gid://shopify/ProductVariant/777', $this->link($id)['variant_gid'], 'stock and orders map to the live variant');
        $this->assertSame(['catalogueRead'], $this->steps(['sync', 'hml', $id])['ops'], 'then unchanged: one read, nothing sent');
    }

    public function testARenameUpdatesTheSameProductAndHmlEnrichmentSurvives(): void
    {
        $id = $this->item('A2');
        $first = $this->steps(['sync', 'hml', $id]);
        $gid = $first['results'][0]['product_gid'];
        // HML enriches the draft in Shopify and publishes it
        $fake = unserialize(file_get_contents($this->state));
        $fake->products[$gid] = ['status' => 'ACTIVE', 'descriptionHtml' => '<p>Teak, FSC</p>', 'tags' => ['udendørs']] + $fake->products[$gid];
        file_put_contents($this->state, serialize($fake));
        $this->sql("UPDATE varer SET beskrivelse = 'Havestol A2 teak' WHERE id = $1", [$id]);

        $r = $this->steps(['sync', 'hml', $id]);

        $this->assertSame(['result' => 'updated', 'product_gid' => $gid], $r['results'][0], 'K11: the same product');
        $this->assertSame(['catalogueRead', 'catalogueSet', 'catalogueMetafields'], $r['ops']);
        $set = $r['calls'][1]['vars'];
        $this->assertSame(['id' => $gid], $set['identifier']);
        $this->assertSame(['title', 'productOptions', 'variants'], array_keys($set['input']));
        $this->assertCount(1, $r['products']);
        $product = $r['products'][$gid];
        $this->assertSame(['Havestol A2 teak', 'ACTIVE', '<p>Teak, FSC</p>', ['udendørs']], [$product['title'], $product['status'], $product['descriptionHtml'], $product['tags']],
            'K07: what HML owns is never overwritten');
        $this->assertSame($first['products'][$gid]['variants'][0]['id'], $product['variants'][0]['id'], 'the variant is updated, not replaced');
    }

    public function testACreateThatReachedShopifyBeforeACrashIsFoundAgain(): void
    {
        $id = $this->item('A3');
        $fake = new FakeCatalogueShop();
        $fake->searchLag = true;
        file_put_contents($this->state, serialize($fake));
        $r = $this->steps(['fail', 'catalogueSet', 'throw_after_apply'], ['sync', 'hml', $id], ['sync', 'hml', $id]);

        $this->assertSame('RuntimeException', $r['results'][1]['exception'], 'the worker died after Shopify created the product');
        $this->assertSame('updated', $r['results'][2]['result'], 'found by its handle: search does not show it yet');
        $this->assertCount(1, $r['products'], 'no second product');
        $this->assertSame(array_keys($r['products'])[0], $this->link($id)['product_gid']);
    }

    public function testACreateThatTimedOutIsReconciledByHandleNotSentTwice(): void
    {
        $id = $this->item('A4');
        $fake = new FakeCatalogueShop();
        $fake->searchLag = true;
        file_put_contents($this->state, serialize($fake));
        $r = $this->steps(['fail', 'catalogueSet', 'timeout_after_apply'], ['sync', 'hml', $id]);

        $this->assertSame('created', $r['results'][1]['result']);
        $this->assertSame(['catalogueByHandle', 'catalogueBySku', 'catalogueSet', 'catalogueByHandle', 'catalogueMetafields'], $r['ops']);
        $this->assertCount(1, $r['products']);
    }

    public function testAProductHmlAlreadyHasIsLinkedByExactSkuNotCreated(): void
    {
        $id = $this->item('B1');
        $fake = new FakeCatalogueShop();
        $fake->add('stol-b10', 'SD741-B10');
        $existing = $fake->add('stol-b1', 'SD741-B1', 1, ['title' => 'Stol B1 (legacy)']);
        file_put_contents($this->state, serialize($fake));

        $r = $this->steps(['sync', 'hml', $id]);

        $this->assertSame(['result' => 'updated', 'product_gid' => $existing['id']], $r['results'][0], 'the prefix match SD741-B10 is not taken');
        $this->assertSame(['catalogueByHandle', 'catalogueBySku', 'catalogueRead', 'catalogueSet', 'catalogueMetafields'], $r['ops']);
        $this->assertCount(2, $r['products']);
        $this->assertSame('Havestol B1', $r['products'][$existing['id']]['title'], 'the name is Saldi-owned');
    }

    public function testAProductWithOtherVariantsGetsNothing(): void
    {
        $id = $this->item('B2');
        $fake = new FakeCatalogueShop();
        $existing = $fake->add('stol-b2', 'SD741-B2', 2, ['title' => 'Havestol (3 farver)']);
        file_put_contents($this->state, serialize($fake));

        $r = $this->steps(['sync', 'hml', $id]);

        $this->assertSame('held', $r['results'][0]['result']);
        $this->assertNotContains('catalogueSet', $r['ops']);
        $this->assertSame('Havestol (3 farver)', $r['products'][$existing['id']]['title'], 'one item\'s name is not the product\'s');
        $this->assertCount(2, $r['products'][$existing['id']]['variants'], 'no variant deleted');
        $this->assertSame([['catalogue.variants', 'info']], array_map(fn(array $e): array => [$e['kind'], $e['severity']],
            $this->sql('SELECT kind, severity FROM shopify_errors WHERE resolved_at IS NULL')), 'listed, no alarm per save');
        $this->assertNull($this->link($id)['last_hash'], 'tried again on the next change');
    }

    public function testAHeldItemIsReleasedFromTheErrorListWhenHmlGoesBackToOneVariant(): void
    {
        $id = $this->item('B3');
        $gid = $this->steps(['sync', 'hml', $id])['results'][0]['product_gid'];
        $fake = unserialize(file_get_contents($this->state));
        $extra = $fake->products[$gid]['variants'][0];
        $fake->products[$gid]['variants'][] = ['id' => 'gid://shopify/ProductVariant/555'] + $extra;
        file_put_contents($this->state, serialize($fake));
        $this->assertSame('held', $this->steps(['sync', 'hml', $id])['results'][0]['result']);
        $fake = unserialize(file_get_contents($this->state));
        array_pop($fake->products[$gid]['variants']);
        file_put_contents($this->state, serialize($fake));

        $r = $this->steps(['sync', 'hml', $id]);

        $this->assertSame('unchanged', $r['results'][0]['result']);
        $this->assertSame([], $this->sql('SELECT kind FROM shopify_errors WHERE resolved_at IS NULL'), 'resolved: nothing is held any more');
    }

    public function testOtherShopsLinkByExactSkuOrMarkTheItemUnlisted(): void
    {
        $found = $this->item('C1');
        $absent = $this->item('C2');
        $twice = $this->item('C3');
        $fake = new FakeCatalogueShop();
        $product = $fake->add('c1', 'SD741-C1');
        $fake->add('c3a', 'SD741-C3');
        $fake->add('c3b', 'SD741-C3');
        file_put_contents($this->state, serialize($fake));

        $r = $this->steps(['discover', 'hms', $found], ['discover', 'hms', $found], ['discover', 'hms', $absent], ['discover', 'hms', $twice], ['sync', 'di', $found]);

        $this->assertSame(['result' => 'linked', 'product_gid' => $product['id']], $r['results'][0]);
        $this->assertSame('linked', $r['results'][1]['result']);
        $this->assertSame(['catalogueBySku', 'catalogueVariant', 'catalogueBySku', 'catalogueBySku'], $r['ops'], 'a linked item is checked with one read; a shop with products off gets nothing');
        $this->assertSame(['result' => 'unlisted'], $r['results'][2]);
        $this->assertSame('f', $this->link($absent, 'hms')['listed']);
        $this->assertSame(['result' => 'ambiguous'], $r['results'][3]);
        $this->assertSame(['f', null], [$this->link($twice, 'hms')['listed'], $this->link($twice, 'hms')['variant_gid']]);
        $this->assertSame(['catalogue.ambiguous'], array_column($this->sql('SELECT kind FROM shopify_errors WHERE resolved_at IS NULL'), 'kind'));
        $this->assertSame('skipped', $r['results'][4]['result']);
        $this->sql("UPDATE varer SET varenr = 'SD741-C3-NEW' WHERE id = $1", [$twice]);
        $this->steps(['discover', 'hms', $twice]);
        $this->assertSame([], $this->sql('SELECT kind FROM shopify_errors WHERE resolved_at IS NULL'), 'the entry is the item\'s: renumbered, it resolves');
    }

    public function testAnExactSkuPastTheFirstPageOfLooseHitsIsFoundAndTooManyHitsAreRefused(): void
    {
        $id = $this->item('P1');
        $fake = new FakeCatalogueShop();
        $fake->pageSize = 2;
        foreach (['P1A', 'P1B', 'P1C'] as $loose) {
            $fake->add(strtolower($loose), "SD741-$loose");
        }
        $exact = $fake->add('p1', 'SD741-P1');
        file_put_contents($this->state, serialize($fake));

        $r = $this->steps(['discover', 'hms', $id]);
        $this->assertSame(['result' => 'linked', 'product_gid' => $exact['id']], $r['results'][0]);
        $this->assertSame(['catalogueBySku', 'catalogueBySku'], $r['ops'], 'the second page holds it');

        $many = $this->item('Q1');
        $fake = new FakeCatalogueShop();
        $fake->pageSize = 2;
        for ($i = 0; $i < 11; $i++) {
            $fake->add("q$i", "SD741-Q1-$i");
        }
        file_put_contents($this->state, serialize($fake));
        $r = $this->steps(['sync', 'hml', $many]);
        $this->assertSame(['ShopifyCatalogueError', false], [$r['results'][0]['exception'], $r['results'][0]['retryable']]);
        $this->assertStringContainsString('more than', $r['results'][0]['message']);
        $this->assertNotContains('catalogueSet', $r['ops'], 'never a guess: no draft next to a product it could not rule out');
        $job = $this->steps(['job', 'catalogue.upsert', 'hml', $many]);
        $this->assertSame(['outcome' => 'done'], $job['results'][0], 'a refusal no retry changes: booked once, not retried for two days');
        $this->assertSame(['catalogue.rejected'], array_column($this->sql("SELECT kind FROM shopify_errors WHERE resolved_at IS NULL"), 'kind'));
    }

    public function testAStaleLinkInAnotherShopIsLookedUpAgain(): void
    {
        $id = $this->item('S1');
        $fake = new FakeCatalogueShop();
        $old = $fake->add('s1', 'SD741-S1');
        file_put_contents($this->state, serialize($fake));
        $this->steps(['discover', 'hms', $id]);
        $fake = unserialize(file_get_contents($this->state));
        unset($fake->products[$old['id']]);
        $new = $fake->add('s1-new', 'SD741-S1');
        file_put_contents($this->state, serialize($fake));

        $r = $this->steps(['discover', 'hms', $id]);

        $this->assertSame(['result' => 'linked', 'product_gid' => $new['id']], $r['results'][0]);
        $this->assertSame(['catalogueVariant', 'catalogueBySku'], $r['ops']);
        $this->assertSame($new['variants'][0]['id'], $this->link($id, 'hms')['variant_gid']);

        $fake = unserialize(file_get_contents($this->state));
        $fake->products = [];
        file_put_contents($this->state, serialize($fake));
        $this->assertSame(['result' => 'unlisted'], $this->steps(['discover', 'hms', $id])['results'][0]);
        $link = $this->link($id, 'hms');
        $this->assertSame(['f', null, null, null], [$link['listed'], $link['product_gid'], $link['variant_gid'], $link['inventory_item_gid']],
            'no flow maps the old variant to the item any more');

        $r = $this->steps(['job', 'catalogue.link', 'hms', $id, false]);
        $this->assertSame(['catalogueBySku'], $r['ops'], 'an unlinked item is looked up also from the seed');
    }

    public function testTheSeedDoesNotReCheckALinkedItem(): void
    {
        $id = $this->item('S2');
        $fake = new FakeCatalogueShop();
        $fake->add('s2', 'SD741-S2');
        file_put_contents($this->state, serialize($fake));

        $r = $this->steps(['discover', 'hms', $id], ['job', 'catalogue.link', 'hms', $id, false], ['job', 'catalogue.link', 'hms', $id, true]);

        $this->assertSame(['catalogueBySku', 'catalogueVariant'], $r['ops'], 'the seed job reads nothing for a linked item; an item card save checks it');
        $this->assertSame(['false'], array_column($this->sql("SELECT payload->>'verify' AS v FROM shopify_jobs WHERE type = 'catalogue.link'"), 'v') ?: ['false'],
            'seed jobs carry verify false');
    }

    public function testAProductLinkedToAnotherItemOrAForeignHandleIsNeverTakenOver(): void
    {
        $first = $this->item('K1');
        $second = $this->item('K2');
        $fake = new FakeCatalogueShop();
        $shared = $fake->add('k1', 'SD741-K1');
        $fake->add("saldi-$second", 'SOMETHING-ELSE');
        file_put_contents($this->state, serialize($fake));
        $this->steps(['sync', 'hml', $first], ['discover', 'hms', $first]);
        // item K2 gets K1's number, as after a rename the shop never saw
        $this->sql("UPDATE varer SET varenr = 'SD741-K1-B' WHERE id = $1", [$first]);
        $this->sql("UPDATE varer SET varenr = 'SD741-K1' WHERE id = $1", [$second]);

        $r = $this->steps(['sync', 'hml', $second], ['discover', 'hms', $second]);

        $this->assertSame(['result' => 'ambiguous'], $r['results'][0]);
        $this->assertSame(['result' => 'ambiguous'], $r['results'][1]);
        $this->assertNotContains('catalogueSet', $r['ops']);
        $this->assertSame("Havestol K1", $r['products'][$shared['id']]['title']);
        $this->assertNull($this->link($second)['product_gid'] ?? null);
        $this->assertSame(['catalogue.handle', 'catalogue.conflict'], array_column($this->sql("SELECT kind FROM shopify_errors WHERE resolved_at IS NULL ORDER BY id"), 'kind'),
            'the foreign saldi-<id> handle and the variant linked to K1');
    }

    public function testAReplacedVariantWithTheItemsSkuIsFollowed(): void
    {
        $id = $this->item('V1');
        $first = $this->steps(['sync', 'hml', $id]);
        $gid = $first['results'][0]['product_gid'];
        $fake = unserialize(file_get_contents($this->state));
        $fake->products[$gid]['variants'][0]['id'] = 'gid://shopify/ProductVariant/999';
        file_put_contents($this->state, serialize($fake));
        $this->sql("UPDATE varer SET kostpris = 950 WHERE id = $1", [$id]);

        $r = $this->steps(['sync', 'hml', $id]);

        $this->assertSame(['result' => 'updated', 'product_gid' => $gid], $r['results'][0]);
        $this->assertSame('gid://shopify/ProductVariant/999', $this->link($id)['variant_gid']);
        $this->assertSame('950.00', $r['products'][$gid]['variants'][0]['inventoryItem']['cost']);
    }

    public function testALinkLeftByADeletedItemDoesNotBlockItsSuccessor(): void
    {
        $old = $this->item('O1');
        $fake = new FakeCatalogueShop();
        $fake->add('o1', 'SD741-O1');
        file_put_contents($this->state, serialize($fake));
        $this->steps(['discover', 'hms', $old]);
        $this->sql('DELETE FROM varer WHERE id = $1', [$old]);
        $new = $this->item('O1');

        $r = $this->steps(['discover', 'hms', $new]);

        $this->assertSame('linked', $r['results'][0]['result']);
        $this->assertNull($this->link($old, 'hms'), 'the orphan link is gone');
        $this->assertSame([], $this->sql("SELECT kind FROM shopify_errors WHERE resolved_at IS NULL"));
    }

    public function testAClosedItemReleasesItsProductButARenumberedOneKeepsIt(): void
    {
        $sold = $this->item('U1');
        $renumbered = $this->item('U2');
        $fake = new FakeCatalogueShop();
        $fake->add('u1', 'SD741-U1');
        $fake->add('u2', 'SD741-U2');
        file_put_contents($this->state, serialize($fake));
        $this->steps(['discover', 'hms', $sold], ['discover', 'hms', $renumbered]);
        // a sold item is closed, not deleted; the other gets a new number the shop has not seen yet
        $this->sql("UPDATE varer SET lukket = '1', varenr = 'SD741-U1-OLD' WHERE id = $1", [$sold]);
        $this->sql("UPDATE varer SET varenr = 'SD741-U2-NEW' WHERE id = $1", [$renumbered]);
        $successor = $this->item('U1');
        $newcomer = $this->item('U2');

        $r = $this->steps(['discover', 'hms', $successor], ['discover', 'hms', $newcomer]);

        $this->assertSame(['linked', 'ambiguous'], array_column($r['results'], 'result'), 'the closed item lets go; the renumbered one is still that product');
        $this->assertNull($this->link($sold, 'hms'), 'released entirely: reopened, it is linked afresh');
        $this->assertSame('t', $this->link($renumbered, 'hms')['listed']);
        $this->assertSame(['catalogue.conflict'], array_column($this->sql('SELECT kind FROM shopify_errors WHERE resolved_at IS NULL'), 'kind'));
    }

    public function testAnOwnerMovedOutOfTheStockGroupsReleasesAndItsEntriesAreResolved(): void
    {
        $owner = $this->item('T1');
        $fake = new FakeCatalogueShop();
        $fake->add('t1a', 'SD741-T1');
        $fake->add('t1b', 'SD741-T1');
        $fake->add('t1c', 'SD741-T1-X');
        file_put_contents($this->state, serialize($fake));
        $this->assertSame('ambiguous', $this->steps(['discover', 'hms', $owner])['results'][0]['result']);
        $fake = unserialize(file_get_contents($this->state));
        $fake->products = array_slice($fake->products, 2, null, true);
        $fake->products[array_key_first($fake->products)]['variants'][0]['sku'] = 'SD741-T1';
        file_put_contents($this->state, serialize($fake));
        $this->assertSame('linked', $this->steps(['discover', 'hms', $owner])['results'][0]['result']);
        $this->sql("UPDATE varer SET gruppe = 901 WHERE id = $1", [$owner]);
        $this->sql("INSERT INTO shopify_errors (shop_id, error_key, kind, ref, message, severity) SELECT id, $1, 'catalogue.rejected', $2, 'old', 'warning' FROM shopify_shops WHERE shop_key = 'hms'",
            [hash('sha256', implode("\x1f", [(string)$this->sql("SELECT id FROM shopify_shops WHERE shop_key = 'hms'")[0]['id'], 'catalogue.rejected', "vare:$owner"])), "vare:$owner"]);
        $successor = $this->item('T1');

        $r = $this->steps(['discover', 'hms', $successor]);

        $this->assertSame('linked', $r['results'][0]['result'], 'no longer a webshop item: it lets go');
        $this->assertNull($this->link($owner, 'hms'));
        $this->assertSame([], $this->sql('SELECT kind, ref FROM shopify_errors WHERE resolved_at IS NULL'), 'the old owner\'s entries are resolved with it');
    }

    public function testTwoJobsNeverBothClaimOneVariant(): void
    {
        $first = $this->item('J1');
        $second = $this->item('J2');
        $fake = new FakeCatalogueShop();
        $variant = $fake->add('j1', 'SD741-J1')['variants'][0]['id'];
        file_put_contents($this->state, serialize($fake));
        $this->sql("UPDATE varer SET varenr = 'SD741-J1' WHERE id = $1", [$second]);
        $shopId = (int)$this->sql("SELECT id FROM shopify_shops WHERE shop_key = 'hms'")[0]['id'];
        // this connection is the other job, inside its claim: it holds the variant's lock
        $this->sql('SELECT pg_advisory_lock(hashtext($1))', ["shopify_variant:$shopId:$variant"]);
        $child = ShopifyEnv::startChild('run_catalogue.php', [self::$db, $this->state, json_encode([['discover', 'hms', $second]])]);
        $until = microtime(true) + 10;
        while (!$this->sql("SELECT 1 FROM pg_locks WHERE locktype = 'advisory' AND NOT granted") && microtime(true) < $until) {
            usleep(20000);
        }
        $this->sql('INSERT INTO shopify_product_links (shop_id, vare_id, variant_id, product_gid, variant_gid, inventory_item_gid) VALUES ($1, $2, 0, $3, $4, $5)',
            [$shopId, $first, 'gid://shopify/Product/100', $variant, 'gid://shopify/InventoryItem/102']);
        $this->sql('SELECT pg_advisory_unlock(hashtext($1))', ["shopify_variant:$shopId:$variant"]);
        $run = ShopifyEnv::waitChild($child);

        $this->assertSame(0, $run['exit'], $run['stderr']);
        $this->assertSame(['result' => 'ambiguous'], $run['result']['results'][0], 'it waited for the other claim and saw it');
        $this->assertSame([(string)$first], array_column($this->sql('SELECT vare_id FROM shopify_product_links WHERE variant_gid = $1', [$variant]), 'vare_id'));
    }

    public function testOnlyTheItemsWaitingForAVariantAreQueuedAfterItsOwnerChanges(): void
    {
        $owner = $this->item('H1');
        $gid = $this->steps(['sync', 'hml', $owner])['results'][0]['product_gid'];
        $waiting = $this->item('H2');
        $this->sql("UPDATE varer SET varenr = 'SD741-H1' WHERE id = $1", [$waiting]);
        $this->assertSame(['result' => 'ambiguous'], $this->steps(['sync', 'hml', $waiting])['results'][0], 'H1 owns the product');
        $jobs = fn(): array => array_column($this->sql("SELECT dedupe_key FROM shopify_jobs WHERE payload->>'vare_id' = $1", [(string)$waiting]), 'dedupe_key');

        $other = $this->item('H3');
        $this->steps(['sync', 'hml', $other]);
        $this->sql('DELETE FROM shopify_jobs');
        $this->sql("UPDATE varer SET kostpris = 990 WHERE id = $1", [$other]);
        $this->assertSame('updated', $this->steps(['sync', 'hml', $other])['results'][0]['result']);
        $this->assertSame([], $jobs(), 'an update of another product queues nobody');

        $this->sql("UPDATE varer SET varenr = 'SD741-H1-NEW' WHERE id = $1", [$owner]);
        $crash = $this->steps(['fail', 'catalogueSet', 'throw_after_apply'], ['sync', 'hml', $owner]);
        $this->assertSame('RuntimeException', $crash['results'][1]['exception'], 'Shopify moved the SKU, then the worker died');
        $this->steps(['sync', 'hml', $owner]);
        $this->assertSame(["hml:vare:$waiting"], $jobs(), 'the retry no longer sees the move, and still queues the item waiting for it');
        $this->assertSame('created', $this->steps(['sync', 'hml', $waiting])['results'][0]['result'], 'no product has SKU H1 any more: its own draft');
    }

    public function testAWaiterThatIsClosedDropsItsEntriesButAMissingGroupDropsNothing(): void
    {
        $owner = $this->item('E7');
        $this->steps(['sync', 'hml', $owner]);
        $waiting = $this->item('E8');
        $this->sql("UPDATE varer SET varenr = 'SD741-E7' WHERE id = $1", [$waiting]);
        $this->steps(['sync', 'hml', $waiting]);
        $open = fn(): array => array_column($this->sql('SELECT kind FROM shopify_errors WHERE resolved_at IS NULL'), 'kind');

        $this->sql("UPDATE varer SET gruppe = 903 WHERE id = $1", [$waiting]);
        $this->assertSame('skipped', $this->steps(['sync', 'hml', $waiting])['results'][0]['result']);
        $this->assertSame(['catalogue.conflict'], $open(), 'its group is missing this fiscal year: that proves nothing');

        $this->sql("UPDATE varer SET gruppe = 900, lukket = '1' WHERE id = $1", [$waiting]);
        $this->steps(['saved', $waiting]);
        $this->assertSame([], $open(), 'closed on the item card: no stale conflict for an item no longer synced');
    }

    public function testAWaiterQueuedFromAPageInAnotherTimeZoneRunsIn30Seconds(): void
    {
        $owner = $this->item('Z7');
        $this->steps(['sync', 'hml', $owner]);
        $waiting = $this->item('Z8');
        $this->sql("UPDATE varer SET varenr = 'SD741-Z7' WHERE id = $1", [$waiting]);
        $this->steps(['sync', 'hml', $waiting]);
        $this->sql('DELETE FROM shopify_jobs');
        $this->sql("UPDATE varer SET lukket = '1' WHERE id = $1", [$owner]);

        $this->steps(['saved', $owner, 0, 'Pacific/Auckland', 'UTC']);

        $this->assertSame(['t'], array_column($this->sql("SELECT next_run_at BETWEEN (now() AT TIME ZONE 'Europe/Copenhagen') + interval '20 seconds'
            AND (now() AT TIME ZONE 'Europe/Copenhagen') + interval '35 seconds' AS ok FROM shopify_jobs WHERE payload->>'vare_id' = $1", [(string)$waiting]), 'ok'),
            'the worker\'s clock, not the page\'s zones');
    }

    public function testAFollowedVariantQueuesTheItemsWaitingForTheOldOne(): void
    {
        $owner = $this->item('F7');
        $gid = $this->steps(['sync', 'hml', $owner])['results'][0]['product_gid'];
        $waiting = $this->item('F8');
        $this->sql("UPDATE varer SET varenr = 'SD741-F7' WHERE id = $1", [$waiting]);
        $this->steps(['sync', 'hml', $waiting]);
        $this->sql('DELETE FROM shopify_jobs');
        $fake = unserialize(file_get_contents($this->state));
        $fake->products[$gid]['variants'][0]['id'] = 'gid://shopify/ProductVariant/888';
        file_put_contents($this->state, serialize($fake));

        $this->steps(['sync', 'hml', $owner]);

        $this->assertSame(["hml:vare:$waiting"], array_column($this->sql("SELECT dedupe_key FROM shopify_jobs WHERE payload->>'vare_id' = $1", [(string)$waiting]), 'dedupe_key'));
    }

    public function testASkuHmlChangedInShopifyIsRestoredOnTheNextSave(): void
    {
        $id = $this->item('Q7');
        $gid = $this->steps(['sync', 'hml', $id])['results'][0]['product_gid'];
        $fake = unserialize(file_get_contents($this->state));
        $fake->products[$gid]['variants'][0]['sku'] = 'HML-EDIT';
        file_put_contents($this->state, serialize($fake));

        $r = $this->steps(['sync', 'hml', $id]);

        $this->assertSame('updated', $r['results'][0]['result'], 'SKU is Saldi-owned');
        $this->assertSame('SD741-Q7', $r['products'][$gid]['variants'][0]['sku']);
    }

    public function testADeletedProductOrAClosedOwnerQueuesItsWaiters(): void
    {
        $owner = $this->item('G7');
        $gid = $this->steps(['sync', 'hml', $owner])['results'][0]['product_gid'];
        $waiting = $this->item('G8');
        $this->sql("UPDATE varer SET varenr = 'SD741-G7' WHERE id = $1", [$waiting]);
        $this->steps(['sync', 'hml', $waiting]);
        $jobs = fn(): array => array_column($this->sql("SELECT dedupe_key FROM shopify_jobs WHERE payload->>'vare_id' = $1", [(string)$waiting]), 'dedupe_key');

        $this->sql('DELETE FROM shopify_jobs');
        $this->sql("UPDATE varer SET lukket = '1' WHERE id = $1", [$owner]);
        $this->steps(['saved', $owner]);
        $this->assertSame(["hml:vare:$waiting"], $jobs(), 'closed on the item card: the waiting item is queued');

        $this->sql('DELETE FROM shopify_jobs');
        $this->sql("UPDATE varer SET lukket = '0', kostpris = 1 WHERE id = $1", [$owner]);
        $fake = unserialize(file_get_contents($this->state));
        unset($fake->products[$gid]);
        file_put_contents($this->state, serialize($fake));
        $this->assertSame('removed', $this->steps(['sync', 'hml', $owner])['results'][0]['result']);
        $this->assertSame(["hml:vare:$waiting"], $jobs(), 'HML deleted the product: the waiting item is queued');
    }

    public function testAReleasedItemThatIsReopenedIsLookedAtAgain(): void
    {
        $sold = $this->item('N1');
        $gid = $this->steps(['sync', 'hml', $sold])['results'][0]['product_gid'];
        $this->sql("UPDATE varer SET lukket = '1', varenr = 'SD741-N1-OLD' WHERE id = $1", [$sold]);
        $successor = $this->item('N1');
        $this->assertSame(['result' => 'updated', 'product_gid' => $gid], $this->steps(['sync', 'hml', $successor])['results'][0]);
        $this->sql("UPDATE varer SET lukket = '0' WHERE id = $1", [$sold]);

        $r = $this->steps(['sync', 'hml', $sold]);

        $this->assertSame(['result' => 'ambiguous'], $r['results'][0], 'looked at again, not skipped as "not listed" (that marker means HML deleted the product)');
        $this->assertNotContains('catalogueSet', $r['ops'], 'its old handle now belongs to the successor\'s product, which is left alone');
        $this->assertStringContainsString("handle saldi-$sold has SKU SD741-N1", $this->sql("SELECT message FROM shopify_errors WHERE kind = 'catalogue.handle' AND resolved_at IS NULL")[0]['message']);
    }

    public function testAnOwnerWithoutAGroupInThisFiscalYearIsNotTakenAsDeleted(): void
    {
        $owner = $this->item('Y1');
        $fake = new FakeCatalogueShop();
        $fake->add('y1', 'SD741-Y1');
        file_put_contents($this->state, serialize($fake));
        $this->steps(['discover', 'hms', $owner]);
        $this->sql("UPDATE varer SET gruppe = 903 WHERE id = $1", [$owner]);
        $other = $this->item('Y2');
        $this->sql("UPDATE varer SET varenr = 'SD741-Y1' WHERE id = $1", [$other]);

        $this->assertSame(['result' => 'ambiguous'], $this->steps(['discover', 'hms', $other])['results'][0]);
        $this->assertSame('t', $this->link($owner, 'hms')['listed'], 'a live item keeps its product');
    }

    public function testAFailedReCheckUnlinksEvenWhenTheNewLookupFails(): void
    {
        $id = $this->item('X1');
        $fake = new FakeCatalogueShop();
        $product = $fake->add('x1', 'SD741-X1');
        file_put_contents($this->state, serialize($fake));
        $this->steps(['discover', 'hms', $id]);
        $fake = unserialize(file_get_contents($this->state));
        $fake->products[$product['id']]['variants'][0]['sku'] = 'SOMETHING-ELSE';
        file_put_contents($this->state, serialize($fake));

        $r = $this->steps(['fail', 'catalogueBySku', 'http_500x4'], ['discover', 'hms', $id]);

        $this->assertSame('ShopifyCatalogueError', $r['results'][1]['exception']);
        $this->assertSame(['f', null], [$this->link($id, 'hms')['listed'], $this->link($id, 'hms')['variant_gid']], 'the variant with another SKU no longer maps to the item');
    }

    public function testAConflictIsResolvedOnceTheItemLinks(): void
    {
        $first = $this->item('L1');
        $second = $this->item('L2');
        $fake = new FakeCatalogueShop();
        $fake->add('l1', 'SD741-L1');
        file_put_contents($this->state, serialize($fake));
        $this->steps(['discover', 'hms', $first]);
        $this->sql("UPDATE varer SET varenr = CASE id WHEN $1 THEN 'SD741-L9' ELSE 'SD741-L1' END WHERE id IN ($1, $2)", [$first, $second]);
        $this->assertSame('ambiguous', $this->steps(['discover', 'hms', $second])['results'][0]['result']);
        $this->assertSame(['catalogue.conflict'], array_column($this->sql('SELECT kind FROM shopify_errors WHERE resolved_at IS NULL'), 'kind'));

        $this->sql('DELETE FROM shopify_jobs');
        $letGo = $this->steps(['discover', 'hms', $first]);
        $this->assertSame('unlisted', $letGo['results'][0]['result'], 'L1 lets go of the SKU it no longer has');
        $this->assertSame([["hms:vare:$second", 'catalogue.link']], array_map(fn(array $j): array => [$j['dedupe_key'], $j['type']],
            $this->sql('SELECT dedupe_key, type FROM shopify_jobs')), 'the item it held back is queued again by itself');

        $r = $this->steps(['discover', 'hms', $second]);

        $this->assertSame(['linked'], array_column($r['results'], 'result'));
        $this->assertSame([], $this->sql('SELECT kind FROM shopify_errors WHERE resolved_at IS NULL'), 'the conflict is resolved');

    }

    public function testAConflictIsResolvedWhenTheProductIsGone(): void
    {
        $owner = $this->item('M1');
        $other = $this->item('M2');
        $fake = new FakeCatalogueShop();
        $fake->add('m1', 'SD741-M1');
        file_put_contents($this->state, serialize($fake));
        $this->steps(['discover', 'hms', $owner]);
        // both items carry M1's SKU for a while: M2 is refused
        $this->sql("UPDATE varer SET varenr = 'SD741-M1' WHERE id = $1", [$other]);
        $this->sql("UPDATE varer SET varenr = 'SD741-M2' WHERE id = $1", [$owner]);
        $this->sql("UPDATE varer SET varenr = 'SD741-M1' WHERE id = $1", [$owner]);
        $this->assertSame('ambiguous', $this->steps(['discover', 'hms', $other])['results'][0]['result']);
        $fake = unserialize(file_get_contents($this->state));
        $fake->products = [];
        file_put_contents($this->state, serialize($fake));

        $this->assertSame(['result' => 'unlisted'], $this->steps(['discover', 'hms', $other])['results'][0]);
        $this->assertSame([], $this->sql('SELECT kind FROM shopify_errors WHERE resolved_at IS NULL'), 'nothing left to conflict with');
    }

    public function testAGroupWithoutAVatFreeValueHasNoVatAsOnTheItemCard(): void
    {
        $this->sql("INSERT INTO grupper (beskrivelse, kodenr, art, box4, box7, box8, fiscal_year) VALUES ('Uden box7', 902, 'VG', '9001', NULL, 'on', 1)");
        $id = $this->item('W1', ['gruppe' => 902]);

        $r = $this->steps(['sync', 'hml', $id]);

        $link = $this->link($id);
        $this->assertSame('0.00', $r['metafields'][$link['variant_gid'] . '|saldi.vat_rate']['value'], 'vatPercent() reads a NULL box7 as no VAT');
        $this->assertFalse($r['products'][$link['product_gid']]['variants'][0]['taxable']);
    }

    public function testAProductHmlDeletedIsNotCreatedAgain(): void
    {
        $id = $this->item('D1');
        $this->steps(['sync', 'hml', $id]);
        $fake = unserialize(file_get_contents($this->state));
        $fake->products = [];
        file_put_contents($this->state, serialize($fake));
        $this->sql("UPDATE varer SET kostpris = 900 WHERE id = $1", [$id]);

        $r = $this->steps(['sync', 'hml', $id], ['sync', 'hml', $id]);

        $this->assertSame('removed', $r['results'][0]['result']);
        $this->assertSame(['result' => 'skipped', 'reason' => 'not listed in this shop'], $r['results'][1]);
        $this->assertSame([], $r['products']);
    }

    public function testARefusalGoesOnTheErrorListAndANetworkFailureIsRetried(): void
    {
        $id = $this->item('E1');
        $r = $this->steps(['fail', 'catalogueSet', 'user_error'], ['job', 'catalogue.upsert', 'hml', $id],
            ['fail', 'catalogueByHandle', 'http_500x4'], ['job', 'catalogue.upsert', 'hml', $id]);

        $this->assertSame(['outcome' => 'done'], $r['results'][1], 'a refusal no retry can change ends the job');
        $errors = $this->sql('SELECT kind, ref, message FROM shopify_errors WHERE resolved_at IS NULL');
        $this->assertSame([['catalogue.rejected', "vare:$id"]], array_map(fn(array $e): array => [$e['kind'], $e['ref']], $errors));
        $this->assertStringContainsString('Barcode is invalid', $errors[0]['message']);
        $this->assertSame(['ShopifyCatalogueError', true], [$r['results'][3]['exception'], $r['results'][3]['retryable']], 'the queue retries it');
        $denied = $this->steps(['fail', 'catalogueByHandle', 'http_401'], ['job', 'catalogue.upsert', 'hml', $id]);
        $this->assertSame(['ShopifyCatalogueError', false], [$denied['results'][1]['exception'], $denied['results'][1]['retryable']],
            'a revoked token is not this item\'s refusal: the job fails, so it syncs once the token is fixed');
        $this->assertSame(1, count($this->sql("SELECT 1 FROM shopify_errors WHERE kind = 'catalogue.rejected' AND resolved_at IS NULL")));
        $late = $this->steps(['job_late', 'catalogue.upsert', 'hml', $id]);
        $this->assertSame(['outcome' => 'yield', 'checkpoint' => []], $late['results'][0], 'no time left in the pass is no failed attempt');

        $ok = $this->steps(['job', 'catalogue.upsert', 'hml', $id]);
        $this->assertSame(['outcome' => 'done'], $ok['results'][0]);
        $this->assertSame([], $this->sql('SELECT kind FROM shopify_errors WHERE resolved_at IS NULL'), 'resolved when the item goes through');
    }

    public function testTheVarekortProducerQueuesAfterCommitAndNothingOnRollback(): void
    {
        $id = $this->item('F1');
        $closed = $this->item('F2', ['lukket' => '1']);
        $r = $this->steps(['saved_rollback', $id]);
        $this->assertSame([], $this->sql('SELECT type FROM shopify_jobs'), 'a rolled-back save queues nothing');

        $this->steps(['saved', $id], ['saved', $id], ['saved', $closed]);

        $jobs = $this->sql("SELECT j.type, s.shop_key, j.payload->>'vare_id' AS vare, j.priority FROM shopify_jobs j JOIN shopify_shops s ON s.id = j.shop_id ORDER BY j.type, j.payload->>'vare_id'");
        $this->assertSame([
            ['type' => 'catalogue.link', 'shop_key' => 'hms', 'vare' => (string)$id, 'priority' => '60'],
            ['type' => 'catalogue.upsert', 'shop_key' => 'hml', 'vare' => (string)$id, 'priority' => '60'],
        ], $jobs, 'one job per shop and item while it waits; di has products off; a closed item queues nothing');
        $this->assertSame(['result' => 'skipped', 'reason' => 'not a webshop item'], $this->steps(['sync', 'hml', $closed])['results'][0], 'closed on the item card: lukket 1');
    }

    public function testASaveWhileTheItemsJobRunsQueuesAFollowUp(): void
    {
        $id = $this->item('R1');
        $this->steps(['saved', $id]);
        $this->sql("UPDATE shopify_jobs SET state = 'running', locked_until = now() + interval '2 minutes' WHERE type = 'catalogue.upsert'");

        $this->steps(['saved', $id], ['saved', $id]);

        $this->assertSame([['hml:vare:' . $id, 'running'], ['hml:vare:' . $id . ':again', 'queued']],
            array_map(fn(array $j): array => [$j['dedupe_key'], $j['state']], $this->sql("SELECT dedupe_key, state FROM shopify_jobs WHERE type = 'catalogue.upsert' ORDER BY id")),
            'the running job may have read the item before the change; one follow-up waits behind it');
    }

    public function testASeedJobNeverSwallowsAnItemCardSave(): void
    {
        $id = $this->item('R3');
        $this->steps(['seed', 'hms', $id - 1, 1], ['saved', $id]);

        $this->assertSame([["hms:vare:$id", 'true'], ["hms:vare:$id:seed", 'false']],
            array_map(fn(array $j): array => [$j['dedupe_key'], $j['verify']], $this->sql("SELECT dedupe_key, payload->>'verify' AS verify FROM shopify_jobs WHERE type = 'catalogue.link' ORDER BY dedupe_key")),
            'the save keeps its re-check');
    }

    public function testThePagesTimeZoneDoesNotShiftTheRunTime(): void
    {
        $id = $this->item('Z1');
        $this->steps(['saved', $id, 30, 'Pacific/Auckland', 'UTC']);

        // the worker's clock: the business zone (no master setting here: Europe/Copenhagen)
        $this->assertSame(['t'], array_column($this->sql("SELECT next_run_at BETWEEN (now() AT TIME ZONE 'Europe/Copenhagen') + interval '20 seconds'
            AND (now() AT TIME ZONE 'Europe/Copenhagen') + interval '35 seconds' AS ok FROM shopify_jobs WHERE type = 'catalogue.upsert'"), 'ok'),
            'now + 30 s on the worker\'s clock, whatever zones the page runs in');
    }

    public function testASaveBringsAJobInRetryBackoffForward(): void
    {
        $id = $this->item('R2');
        $this->steps(['saved', $id]);
        $this->sql("UPDATE shopify_jobs SET next_run_at = created_at + interval '1 hour', attempts = 4 WHERE type = 'catalogue.upsert'");

        $this->steps(['saved', $id]);

        // compared with the job's own created_at: this session's clock zone is not the worker's
        $this->assertSame(['t'], array_column($this->sql("SELECT next_run_at < created_at + interval '30 minutes' AS soon FROM shopify_jobs WHERE type = 'catalogue.upsert'"), 'soon'),
            'the change does not wait out the backoff');
    }

    public function testTheSeedQueuesTheWebshopItemsInChunks(): void
    {
        $items = [$this->item('G1'), $this->item('G2'), $this->item('G3')];
        $this->item('G4', ['lukket' => 'on']);
        $this->item('G6', ['lukket' => '1']);
        $this->item('G5', ['gruppe' => 901]);
        $before = (int)$this->sql('SELECT coalesce(max(id), 0) AS m FROM varer WHERE id < $1', [min($items)])[0]['m'];

        $r = $this->steps(['seed', 'hml', $before, 2], ['seed', 'hml', $items[1], 2], ['seed', 'di', $before, 2]);

        $this->assertSame(['enqueued' => 2, 'after' => $items[1], 'done' => false], $r['results'][0]);
        $this->assertSame(['enqueued' => 1, 'after' => $items[2], 'done' => true], $r['results'][1], 'closed items and other groups are left out');
        $this->assertSame(['enqueued' => 0, 'after' => $before, 'done' => true], $r['results'][2], 'products off: nothing');
        $this->assertSame(array_map('strval', $items), array_column($this->sql("SELECT payload->>'vare_id' AS v FROM shopify_jobs WHERE type = 'catalogue.upsert' ORDER BY id"), 'v'));
    }

    public function testTheSeedJobYieldsWithItsCursorWhenTimeRunsOutOrItsLeaseIsLost(): void
    {
        $this->sql("UPDATE grupper SET box8 = '' WHERE art = 'VG' AND kodenr <> 900");
        $items = [$this->item('H1'), $this->item('H2'), $this->item('H3')];

        $r = $this->steps(['seed_job', 'hml', 1], ['seed_job', 'hml', 60], ['seed_job', 'hml', 60, false]);

        $this->assertSame('yield', $r['results'][0]['outcome']);
        $this->assertSame($items[1], $r['results'][0]['checkpoint']['after']);
        $this->assertSame(['outcome' => 'done'], $r['results'][1]);
        $this->assertSame('yield', $r['results'][2]['outcome'], 'a lost lease stops the seed after one chunk');
    }
}
