<?php
// tests/characterization/order-creation/JwtOrderCreationCharacterizationTest.test.php
//
// Characterization tests for the JWT REST order-creation path (SD-600,
// scope item 2): restapi/services/OrderService.php::createOrder() +
// restapi/models/orders/OrderModel.php::save(). See
// support/run_order_create_jwt.php for why this runner calls the service
// directly rather than going through the JWT/HTTP wrapper.
//
// Pins two discrepancies already flagged for SD-600's unification design
// (independent of which direction that takes):
//   - OrderModel::save()'s INSERT column list omits phone/tlf entirely, so a
//     freshly created order's phone number survives only in the in-memory
//     response, not in the row a later GET would reload (OrderModel.php:117-130
//     vs the 'tlf' it clearly tracks via setTelefon()/getTelefon()).
//   - OrderService::createNewDebtor() hardcodes adresser.art='D' regardless
//     of which endpoint is calling (OrderService.php:322), so a brand-new
//     supplier created via POST /v1/creditor/orders is filed as a debtor.
//
// Requires the docker-compose stack - skips cleanly otherwise (inherited
// from CharacterizationTestCase).
//
// History:
// 20260805 CL/SZ SD-600: created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class JwtOrderCreationCharacterizationTest extends CharacterizationTestCase
{
    private static int $tlfSeq = 700000;

    private function nextTlf(): string
    {
        return '+45' . (++self::$tlfSeq);
    }

    private function createOrder(array $payload): array
    {
        $res = self::runChild('run_order_create_jwt.php', [CharacterizationEnv::testDb(), json_encode($payload)]);
        $decoded = json_decode(trim($res['stdout']), true);
        $this->assertIsArray($decoded, "run_order_create_jwt.php must emit a JSON object.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
        return $decoded;
    }

    public function test_new_debtor_order_persists_the_debtor_but_drops_the_phone_number_on_reload(): void
    {
        $tlf = $this->nextTlf();
        $out = $this->createOrder([
            'companyName' => 'chartest jwt kunde',
            'phone' => $tlf,
            'email' => 'chartest-jwt@example.invalid',
            'vatRate' => 25,
            'address1' => 'Testvej 1',
            'postalCode' => '8000',
            'city' => 'Aarhus',
            'country' => 'Danmark',
            'art' => 'DO',
        ]);

        $this->assertTrue($out['success'] ?? false, 'order creation succeeds: ' . ($out['message'] ?? ''));
        $this->assertSame($tlf, $out['data']['phone'], 'the in-memory response still carries the phone number OrderService just set');
        $this->assertNotNull($out['reloaded'] ?? null, 'the order is findable by (ordrenr via id, art) immediately after creation');
        $this->assertEmpty($out['reloaded']['phone'] ?? null, 'a fresh reload has no phone number: save() never wrote tlf to ordrer');

        $debtor = self::one('SELECT art, tlf FROM adresser WHERE id = $1', [(int)$out['data']['accountId']]);
        $this->assertNotNull($debtor, 'a new adresser row is created for the unmatched phone number');
        $this->assertSame('D', $debtor['art']);
        $this->assertSame($tlf, $debtor['tlf'], 'unlike the order row, the NEW adresser row does keep the phone number');
    }

    public function test_existing_debtor_matched_by_phone_is_reused_not_duplicated(): void
    {
        $tlf = $this->nextTlf();
        $first = $this->createOrder([
            'companyName' => 'chartest jwt kunde',
            'phone' => $tlf,
            'email' => 'chartest-jwt@example.invalid',
            'vatRate' => 25,
            'art' => 'DO',
        ]);
        $this->assertTrue($first['success'] ?? false);

        $second = $this->createOrder([
            'companyName' => 'chartest jwt kunde (second order)',
            'phone' => $tlf,
            'email' => 'chartest-jwt@example.invalid',
            'vatRate' => 25,
            'art' => 'DO',
        ]);

        $this->assertTrue($second['success'] ?? false);
        $this->assertSame($first['data']['accountId'], $second['data']['accountId'], 'a second order for the same phone number reuses the debtor, not a new one');
        $this->assertNotSame($first['data']['id'], $second['data']['id'], 'each call still creates a new order row');
    }

    public function test_creditor_order_for_a_new_supplier_still_creates_the_adresser_row_as_a_debtor(): void
    {
        $tlf = $this->nextTlf();
        // Mirrors restapi/endpoints/v1/creditor/orders/index.php::handlePost(),
        // which sets art='KO' directly before calling OrderService::createOrder().
        $out = $this->createOrder([
            'companyName' => 'chartest jwt leverandoer',
            'phone' => $tlf,
            'email' => 'chartest-jwt-k@example.invalid',
            'vatRate' => 25,
            'art' => 'KO',
        ]);

        $this->assertTrue($out['success'] ?? false, 'order creation succeeds: ' . ($out['message'] ?? ''));
        $this->assertNotNull($out['reloaded'] ?? null, "the order round-trips under art='KO' (loadFromId filters on it)");

        $supplier = self::one('SELECT art FROM adresser WHERE id = $1', [(int)$out['data']['accountId']]);
        $this->assertNotNull($supplier);
        $this->assertSame(
            'D',
            $supplier['art'],
            "createNewDebtor() hardcodes art='D' regardless of the order's own art - a brand-new creditor-order supplier is filed as a debtor"
        );
    }
}
