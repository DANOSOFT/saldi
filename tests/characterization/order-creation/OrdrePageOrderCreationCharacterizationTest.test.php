<?php
// tests/characterization/order-creation/OrdrePageOrderCreationCharacterizationTest.test.php
//
// Characterization tests for the plain "new order" UI path (SD-600, scope
// widened 2026-08-07 per Lui to 4 implementations): debitor/ordre.php's own
// direct INSERT INTO ordrer. This is the main "New order" flow, reached by
// GET debitor/ordre.php?konto_id=<id> - distinct from
// includes/ordrefunc.php::opret_ordre(), which only fires from a case's
// "new quote" button (see ClassicOrderCreationCharacterizationTest).
// opret_posordre() (POS) is explicitly out of scope for SD-600 per Lui -
// split into its own follow-up ticket.
//
// debitor/ordre.php is a full session-driven page, so this suite drives it
// the way KassekladdePostingCharacterizationTest drives finans/bogfor.php:
// see support/run_order_create_ordre_page.php.
//
// Requires the docker-compose stack - skips cleanly otherwise (inherited
// from CharacterizationTestCase).
//
// History:
// 20260807 CL/SZ SD-600: created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class OrdrePageOrderCreationCharacterizationTest extends CharacterizationTestCase
{
    private function createOrder(int $kontoId): array
    {
        self::runChild('run_order_create_ordre_page.php', [$kontoId, CharacterizationEnv::SESSION_ID]);

        return self::one('SELECT * FROM ordrer WHERE konto_id = $1 ORDER BY id DESC LIMIT 1', [$kontoId]);
    }

    public function test_new_order_for_an_existing_debtor_direct_inserts_ordrer(): void
    {
        $debtor = self::$fx->debtor();

        $order = $this->createOrder($debtor['id']);

        $this->assertNotNull($order, 'GET ordre.php?konto_id=.. with no id creates a new ordrer row directly');
        $this->assertSame($debtor['id'], (int)$order['konto_id']);
        $this->assertSame($debtor['kontonr'], $order['kontonr']);
        $this->assertSame('DO', $order['art'], 'art is hardcoded to DO here too');
        $this->assertSame('0', (string)$order['status'], 'a freshly created order starts at status 0');
        $this->assertGreaterThan(0, (int)$order['ordrenr'], 'an order number is assigned');
        // Observed: $hvem is declared NULL (ordre.php:122) and never assigned
        // before this INSERT for a plain GET request (only $_POST['hvem'] on
        // a later save sets it) - unlike opret_ordre(), which stamps
        // $brugernavn into hvem at creation time.
        $this->assertSame('', trim((string)$order['hvem']), 'hvem is empty on direct creation, not the logged-in user');
        // Observed: momssats is derived from the debtor's DG group -> SM
        // group box2, same lookup opret_ordre()/insert_shop_order() do; the
        // stock chart's only shipped rate is 25% (see Fixtures::vatSalesAccountAt()).
        $this->assertEqualsWithDelta(25.0, (float)$order['momssats'], 0.005);
    }

    public function test_order_numbers_are_sequential_with_the_shared_helper(): void
    {
        $debtorA = self::$fx->debtor();
        $first = $this->createOrder($debtorA['id']);

        $debtorB = self::$fx->debtor();
        $second = $this->createOrder($debtorB['id']);

        $this->assertSame(
            (int)$first['ordrenr'] + 1,
            (int)$second['ordrenr'],
            'ordrenr comes from the shared get_next_order_number("DO") helper - same sequence opret_ordre() and insert_shop_order() draw from'
        );
    }

    public function test_visiting_the_page_again_for_the_same_debtor_creates_another_order(): void
    {
        $debtor = self::$fx->debtor();
        $first = $this->createOrder($debtor['id']);
        $second = $this->createOrder($debtor['id']);

        $this->assertNotSame(
            $first['id'],
            $second['id'],
            'the trigger only checks for a missing id param, not for an existing open order - revisiting the URL creates a duplicate order'
        );
    }
}
