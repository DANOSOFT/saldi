<?php
// tests/characterization/order-creation/JwtOrderCreationCharacterizationTest.test.php
//
// Characterization tests for the JWT REST order-creation path (SD-600,
// scope item 2): restapi/services/OrderService.php::createOrder() +
// restapi/models/orders/OrderModel.php::save(). See
// support/run_order_create_jwt.php for why this runner calls the service
// directly rather than going through the JWT/HTTP wrapper.
//
// Still pins one discrepancy flagged for SD-600's unification design:
//   - OrderModel::save()'s INSERT column list omits phone/tlf entirely, so a
//     freshly created order's phone number survives only in the in-memory
//     response, not in the row a later GET would reload (OrderModel.php:117-130
//     vs the 'tlf' it clearly tracks via setTelefon()/getTelefon()). Not part
//     of SD-600's migration steps - flagged separately.
// The other originally-pinned discrepancy - OrderService::createNewDebtor()
// hardcoding adresser.art='D' regardless of the order's own art - was fixed
// in SD-600 step 5c; see the now-green
// test_creditor_order_for_a_new_supplier_creates_the_adresser_row_as_a_creditor
// below, which used to pin the bug and now proves the fix.
//
// Requires the docker-compose stack - skips cleanly otherwise (inherited
// from CharacterizationTestCase).
//
// History:
// 20260805 CL/SZ SD-600: created.
// 20260814 CL/SZ SD-600: first real run - loosened accountId's reused-debtor
//   comparison to assertEquals; getOrCreateDebtor() returns 'id' as int when
//   creating vs a raw pg string when matched by phone/konto_id.
// 20260825 CL/SZ SD-600 step 5b: added a NEW invariant test (ordrenr scoped to
//   the order's own art) proving OrderService::getNextOrderNumber()'s removal
//   fixed the unscoped/unlocked numbering bug - not a "same as before" pin.
// 20260825 CL/SZ SD-600 step 5c: flipped the art='D'-hardcode pin to a green
//   assertion proving createNewDebtor() now files a new creditor-order
//   supplier as art='K', via order_creation_debtor_art_for_order_art().

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
        // Loose comparison, not assertSame: OrderService::getOrCreateDebtor()
        // returns 'id' as a genuine int when it just created the debtor
        // (getLastInsertId() casts (int)) but as a raw pg string when it's
        // matched via getUserByPhone()/getUserByKontoId() (no cast there) -
        // same value, inconsistent type depending on which branch ran.
        $this->assertEquals($first['data']['accountId'], $second['data']['accountId'], 'a second order for the same phone number reuses the debtor, not a new one');
        $this->assertNotSame($first['data']['id'], $second['data']['id'], 'each call still creates a new order row');
    }

    public function test_creditor_order_for_a_new_supplier_creates_the_adresser_row_as_a_creditor(): void
    {
        // NEW invariant (not a "same as before" pin) added for SD-600 step 5c:
        // createNewDebtor() used to hardcode art='D' regardless of the order's
        // own art, filing a brand-new creditor-order supplier as a debtor.
        // This was RED before the 5c fix (asserted 'D') and is GREEN after it
        // (order_creation_debtor_art_for_order_art('KO') now threads through).
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
            'K',
            $supplier['art'],
            "a brand-new creditor-order supplier must be filed as a creditor (art='K'), not a debtor"
        );
    }

    public function test_ordrenr_is_scoped_to_the_orders_own_art_not_the_global_max(): void
    {
        // NEW invariant (not a "same as before" characterization pin) added for
        // SD-600 step 5b: OrderService used to number orders via its own
        // "SELECT MAX(ordrenr) FROM ordrer" with no art filter and no locking
        // (OrderService::getNextOrderNumber(), now removed). A DO order's
        // number could get dragged up by an unrelated art's higher ordrenr.
        // order_creation_allocate_number() now wraps the real, art-scoped
        // get_next_order_number('DO') instead. This was RED before the 5b
        // fix (the plain fixture row's high ordrenr leaked into the DO
        // order's own number) and is GREEN after it.
        $unrelatedHighOrdrenr = 900000 + (++self::$tlfSeq);
        $inserted = pg_query_params(
            self::$tenant,
            "INSERT INTO ordrer (ordrenr, art) VALUES ($1, 'KO')",
            [$unrelatedHighOrdrenr]
        );
        $this->assertNotFalse($inserted, 'fixture insert for an unrelated art with a high ordrenr must succeed: ' . pg_last_error(self::$tenant));

        $tlf = $this->nextTlf();
        $out = $this->createOrder([
            'companyName' => 'chartest jwt scoping kunde',
            'phone' => $tlf,
            'email' => 'chartest-jwt-scope@example.invalid',
            'vatRate' => 25,
            'art' => 'DO',
        ]);

        $this->assertTrue($out['success'] ?? false, 'order creation succeeds: ' . ($out['message'] ?? ''));
        $this->assertLessThan(
            $unrelatedHighOrdrenr,
            $out['data']['orderNo'],
            "a DO order's number must come from get_next_order_number('DO') scoped to its own art, not an unrelated art's higher ordrenr"
        );
    }
}
