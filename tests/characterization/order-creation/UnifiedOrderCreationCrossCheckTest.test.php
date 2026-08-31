<?php
// tests/characterization/order-creation/UnifiedOrderCreationCrossCheckTest.test.php
//
// SD-600 scope item 1, step 6: a cross-caller check that only makes sense
// once all 4 order-creation paths - OrdrePage (debitor/ordre.php), Classic
// (includes/ordrefunc.php::opret_ordre()), Webshop
// (api/rest_api.php::insert_shop_order()), and JWT REST
// (restapi/services/OrderService.php + OrderModel.php) - are migrated onto
// includes/order_creation.php. Each path's own behavior is already pinned by
// its own dedicated suite in this directory; this one proves the thing that
// spans all 4: they now draw ordrenr from the same shared, locked
// get_next_order_number('DO') sequence with no collisions, including JWT,
// which used to mint its own unscoped/unlocked number (SD-600 step 5b).
//
// Requires the docker-compose stack - skips cleanly otherwise (inherited
// from CharacterizationTestCase).
//
// History:
// 20260825 CL/SZ SD-600: created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class UnifiedOrderCreationCrossCheckTest extends CharacterizationTestCase
{
    private function createViaOrdrePage(int $kontoId): array
    {
        self::runChild('run_order_create_ordre_page.php', [$kontoId, CharacterizationEnv::SESSION_ID]);
        return self::one('SELECT * FROM ordrer WHERE konto_id = $1 ORDER BY id DESC LIMIT 1', [$kontoId]);
    }

    private function createViaClassic(int $sagId, int $kontoId): array
    {
        return $this->runChildJson('run_order_create_classic.php', ['quote', $sagId, $kontoId, CharacterizationEnv::testDb()]);
    }

    private function createViaWebshop(int $shopOrderId, array $params): array
    {
        $res = self::runChild('run_order_create_shop.php', [CharacterizationEnv::testDb(), json_encode($params)]);
        $return = json_decode(trim($res['stdout']), true);
        $order = self::one('SELECT * FROM ordrer WHERE shop_id = $1', [(string)$shopOrderId]);
        return ['return' => $return, 'order' => $order];
    }

    private function createViaJwt(array $payload): array
    {
        $res = self::runChild('run_order_create_jwt.php', [CharacterizationEnv::testDb(), json_encode($payload)]);
        return json_decode(trim($res['stdout']), true);
    }

    public function test_ordrenr_has_no_collisions_across_all_four_migrated_paths_for_the_same_art(): void
    {
        // OrdrePage
        $ordrePageDebtor = self::$fx->debtor();
        $ordrePageOrder = $this->createViaOrdrePage($ordrePageDebtor['id']);
        $this->assertNotNull($ordrePageOrder, 'OrdrePage creates an order');

        // Classic
        $classicDebtor = self::$fx->debtor();
        $sag = self::$fx->sag($classicDebtor);
        $classicOrder = $this->createViaClassic($sag['id'], $classicDebtor['id']);
        $this->assertGreaterThan(0, $classicOrder['id'], 'Classic creates an order');

        // Webshop
        $apiAccess = self::$fx->apiAccess();
        $shopOrderId = 600001;
        $webshopResult = $this->createViaWebshop($shopOrderId, [
            'shop_ordre_id' => $shopOrderId,
            'key' => $apiAccess['key'],
            'saldi_kontonr' => self::$fx->debtor()['kontonr'],
            'firmanavn' => 'chartest crosscheck shop kunde',
            'addr1' => 'Testvej 1',
            'postnr' => '8000',
            'bynavn' => 'Aarhus',
            'land' => 'Danmark',
            'email' => 'chartest-crosscheck-shop@example.invalid',
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
        ]);
        $this->assertNotNull($webshopResult['order'], 'Webshop creates an order');

        // JWT
        $jwtResult = $this->createViaJwt([
            'companyName' => 'chartest crosscheck jwt kunde',
            'phone' => '+45' . random_int(10000000, 99999999),
            'email' => 'chartest-crosscheck-jwt@example.invalid',
            'vatRate' => 25,
            'art' => 'DO',
        ]);
        $this->assertTrue($jwtResult['success'] ?? false, 'JWT creates an order: ' . ($jwtResult['message'] ?? ''));

        $ordrenrs = [
            'OrdrePage' => (int)$ordrePageOrder['ordrenr'],
            'Classic' => (int)$classicOrder['ordrenr'],
            'Webshop' => (int)$webshopResult['order']['ordrenr'],
            'Jwt' => (int)$jwtResult['data']['orderNo'],
        ];

        $this->assertCount(
            4,
            array_unique($ordrenrs),
            'all 4 migrated order-creation paths must draw from the same shared, locked get_next_order_number(\'DO\') sequence with no collisions: ' . json_encode($ordrenrs)
        );
    }
}
