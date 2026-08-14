<?php
// tests/characterization/order-creation/ShopOrderCreationCharacterizationTest.test.php
//
// Characterization tests for the webshop order-creation path (SD-600,
// scope item 2): api/rest_api.php::insert_shop_order(), reached via
// ?action=insert_shop_order behind access_check(). See
// support/run_order_create_shop.php for why this path needs a whole-page
// runner rather than a direct function call, and Fixtures::apiAccess() for
// the access_check() gate this suite seeds.
//
// Requires the docker-compose stack - skips cleanly otherwise (inherited
// from CharacterizationTestCase).
//
// History:
// 20260805 CL/SZ SD-600: created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class ShopOrderCreationCharacterizationTest extends CharacterizationTestCase
{
    /** Distinct shop_ordre_id per call - insert_shop_order() refuses to re-import one it has already seen for the same art. */
    private static int $shopIdSeq = 500000;

    private function createShopOrder(array $params): array
    {
        $apiAccess = self::$fx->apiAccess();
        $shopOrderId = ++self::$shopIdSeq;

        $defaults = [
            'shop_ordre_id' => $shopOrderId,
            'key' => $apiAccess['key'],
            'firmanavn' => 'chartest shop kunde',
            'addr1' => 'Testvej 1',
            'postnr' => '8000',
            'bynavn' => 'Aarhus',
            'land' => 'Danmark',
            'email' => 'chartest-shop@example.invalid',
            'betalingsbet' => 'Netto',
            'betalingsdage' => 8,
            'ordredate' => date('Y-m-d'),
            'lev_date' => date('Y-m-d'),
            'valuta' => 'DKK',
            'nettosum' => '1000',
            'momssum' => '250',
            'lager' => 1,
            'sprog' => 0,
            'notes' => '',
            'ref' => 'chartest',
            'udskriv_til' => 'email',
        ];

        $res = self::runChild('run_order_create_shop.php', [CharacterizationEnv::testDb(), json_encode($params + $defaults)]);
        $returnValue = json_decode(trim($res['stdout']), true);

        $order = self::one('SELECT * FROM ordrer WHERE shop_id = $1', [(string)$shopOrderId]);

        return ['return' => $returnValue, 'shop_id' => $shopOrderId, 'order' => $order, 'raw' => $res];
    }

    public function test_creating_an_order_for_an_existing_debtor_reuses_it_by_kontonr(): void
    {
        $debtor = self::$fx->debtor();
        $adresserBefore = self::rows('SELECT count(*) AS n FROM adresser WHERE kontonr = $1', [$debtor['kontonr']]);

        $out = $this->createShopOrder([
            'saldi_kontonr' => $debtor['kontonr'],
            'gruppe' => $debtor['gruppe'],
            'tlf' => '',
        ]);

        $this->assertIsInt($out['return'], "insert_shop_order() returns the new ordrer.id on success.\nstdout: {$out['raw']['stdout']}\nstderr: {$out['raw']['stderr']}");
        $this->assertNotNull($out['order'], 'an order row is created, findable by shop_id');
        $this->assertSame($debtor['id'], (int)$out['order']['konto_id'], 'ordrer.konto_id is the existing debtor matched by saldi_kontonr');
        $this->assertSame($debtor['kontonr'], $out['order']['kontonr']);
        $this->assertSame('DO', $out['order']['art'], 'art defaults to DO when not passed');
        $this->assertSame('0', (string)$out['order']['status'], 'initial status is 0 for art != DK');
        $this->assertSame('', trim((string)$out['order']['hvem']), "hvem is hardcoded to '' here, unlike the classic UI path which stamps the logged-in user");
        $this->assertEqualsWithDelta(1000.0, (float)$out['order']['sum'], 0.005, 'ordrer.sum is nettosum verbatim, not recomputed from lines');
        $this->assertEqualsWithDelta(250.0, (float)$out['order']['moms'], 0.005, 'ordrer.moms is momssum verbatim');

        $adresserAfter = self::rows('SELECT count(*) AS n FROM adresser WHERE kontonr = $1', [$debtor['kontonr']]);
        $this->assertSame($adresserBefore[0]['n'], $adresserAfter[0]['n'], 'no new adresser row is created when saldi_kontonr matches an existing debtor');
    }

    public function test_creating_an_order_for_an_unknown_phone_number_creates_a_new_debtor_keyed_by_that_number(): void
    {
        $tlf = '+45' . self::$shopIdSeq . '00'; // fresh, never-seen digits each call

        $out = $this->createShopOrder([
            'saldi_kontonr' => '',
            'tlf' => $tlf,
        ]);

        $this->assertIsInt($out['return'], "insert_shop_order() returns the new ordrer.id on success.\nstdout: {$out['raw']['stdout']}\nstderr: {$out['raw']['stderr']}");
        $this->assertNotNull($out['order']);
        $expectedKontonr = (string)((int)str_replace('+', '', $tlf));
        $this->assertSame($expectedKontonr, $out['order']['kontonr'], 'a brand-new shop customer is assigned the digits of their phone number as kontonr');
        $this->assertSame($tlf, trim((string)$out['order']['phone']), 'ordrer.phone keeps the "+" the kontonr strips');

        $newDebtor = self::one('SELECT art, kontonr FROM adresser WHERE id = $1', [(int)$out['order']['konto_id']]);
        $this->assertNotNull($newDebtor, 'a new adresser row is created for the unmatched phone number');
        $this->assertSame('D', $newDebtor['art'], 'new shop customers are always created as debtors (art=D)');
        $this->assertSame($expectedKontonr, $newDebtor['kontonr']);
    }

    public function test_reimporting_the_same_shop_order_id_for_the_same_art_is_refused(): void
    {
        $debtor = self::$fx->debtor();
        $first = $this->createShopOrder(['saldi_kontonr' => $debtor['kontonr'], 'gruppe' => $debtor['gruppe']]);
        $this->assertIsInt($first['return']);

        // Re-run with the SAME shop_ordre_id (bypass the auto-incrementing default).
        $apiAccess = self::$fx->apiAccess();
        $params = [
            'shop_ordre_id' => $first['shop_id'],
            'key' => $apiAccess['key'],
            'saldi_kontonr' => $debtor['kontonr'],
            'gruppe' => $debtor['gruppe'],
            'firmanavn' => 'chartest shop kunde',
            'addr1' => 'Testvej 1',
            'postnr' => '8000',
            'bynavn' => 'Aarhus',
            'land' => 'Danmark',
            'ordredate' => date('Y-m-d'),
            'lev_date' => date('Y-m-d'),
            'nettosum' => '1000',
            'momssum' => '250',
            'lager' => 1,
        ];
        $res = self::runChild('run_order_create_shop.php', [CharacterizationEnv::testDb(), json_encode($params)]);
        $returnValue = json_decode(trim($res['stdout']), true);

        $this->assertIsString($returnValue, 'a duplicate shop_ordre_id (same art) is refused with a message, not a new order id');
        $this->assertStringContainsString('exists in saldi', $returnValue);
    }
}
