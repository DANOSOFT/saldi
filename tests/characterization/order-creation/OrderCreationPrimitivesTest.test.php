<?php
// tests/characterization/order-creation/OrderCreationPrimitivesTest.test.php
//
// Direct coverage of includes/order_creation.php (SD-600 scope item 1: the
// new shared module). Unlike the other 4 suites in this directory, this one
// is NOT characterizing an existing production path - order_creation.php has
// no caller wired to it yet (see the SD-600 plan for the migration order).
// This suite proves the module's own primitives behave as designed, before
// any live path depends on them.
//
// Requires the docker-compose stack - skips cleanly otherwise (inherited
// from CharacterizationTestCase).
//
// History:
// 20260825 CL/SZ SD-600: created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class OrderCreationPrimitivesTest extends CharacterizationTestCase
{
    private function runPrimitive(string $scenario, array $args = []): array
    {
        return $this->runChildJson('run_order_creation_primitives.php', [
            $scenario,
            ...$args,
            CharacterizationEnv::testDb(),
        ]);
    }

    public function test_debtor_art_map_resolves_debitor_arts_to_D(): void
    {
        $this->assertSame('D', $this->runPrimitive('debtor_art_map', ['DO'])['result']);
        $this->assertSame('D', $this->runPrimitive('debtor_art_map', ['DK'])['result']);
    }

    public function test_debtor_art_map_resolves_kreditor_arts_to_K(): void
    {
        $this->assertSame('K', $this->runPrimitive('debtor_art_map', ['KO'])['result']);
        $this->assertSame('K', $this->runPrimitive('debtor_art_map', ['KK'])['result']);
    }

    public function test_debtor_art_map_throws_on_an_unmapped_art(): void
    {
        $out = $this->runPrimitive('debtor_art_map', ['R1']);
        $this->assertArrayHasKey('error', $out, 'an unmapped order art must fail loudly, not silently guess a debtor art');
        $this->assertStringContainsString('R1', $out['error']);
    }

    public function test_allocate_number_override_bypasses_get_next_order_number(): void
    {
        $out = $this->runPrimitive('allocate_number_override', ['DO', '999999']);
        $this->assertSame(999999, $out['result']);
    }

    public function test_allocate_number_without_override_calls_the_real_shared_helper(): void
    {
        $out = $this->runPrimitive('allocate_number_real', ['DO']);
        $this->assertSame($out['first'] + 1, $out['second'], 'sequential allocations against the real get_next_order_number("DO") once the first is consumed');
    }

    public function test_resolve_debtor_finds_an_existing_debtor_by_konto_id(): void
    {
        $debtor = self::$fx->debtor();

        $out = $this->runPrimitive('resolve_debtor_existing', [$debtor['id']]);

        $this->assertTrue($out['found']);
        $this->assertSame($debtor['id'], $out['id']);
        $this->assertSame($debtor['kontonr'], $out['kontonr']);
        $this->assertFalse($out['created']);
    }

    public function test_resolve_debtor_creates_a_debitor_with_art_D_for_a_DO_order(): void
    {
        $tlf = '+45' . random_int(10000000, 99999999);
        $kontonr = (string)random_int(900000, 999999);

        $out = $this->runPrimitive('resolve_debtor_create', ['DO', $tlf, $kontonr]);

        $this->assertTrue($out['found']);
        $this->assertTrue($out['created']);
        $this->assertSame('D', $out['art'], 'a DO order creates a debitor (art=D)');
        $this->assertSame($kontonr, $out['kontonr']);
    }

    public function test_resolve_debtor_creates_a_kreditor_with_art_K_for_a_KO_order(): void
    {
        // This is the exact scenario the JWT REST path gets wrong today
        // (OrderService::createNewDebtor() hardcodes 'D' even here) - proving
        // the shared module gets it right before JWT is migrated onto it.
        $tlf = '+45' . random_int(10000000, 99999999);
        $kontonr = (string)random_int(900000, 999999);

        $out = $this->runPrimitive('resolve_debtor_create', ['KO', $tlf, $kontonr]);

        $this->assertTrue($out['found']);
        $this->assertTrue($out['created']);
        $this->assertSame('K', $out['art'], 'a KO order creates a kreditor (art=K), not a debitor');
        $this->assertSame($kontonr, $out['kontonr']);
    }

    public function test_insert_throws_on_an_unrecognized_column(): void
    {
        $out = $this->runPrimitive('insert_unknown_column');

        $this->assertArrayHasKey('error', $out);
        $this->assertStringContainsString('not_a_real_column', $out['error']);
    }

    public function test_insert_honors_a_sql_filter_applied_to_the_assembled_query(): void
    {
        // Proves the hook added for api/rest_api.php::insert_shop_order()'s
        // whole-string chk4utf8() call: order_creation_insert() must apply
        // $options['sql_filter'] to the fully assembled query, not per-value.
        $out = $this->runPrimitive('insert_with_sql_filter');

        $this->assertTrue($out['filter_called'], 'sql_filter is invoked with the assembled query string');
        $this->assertSame('filtered', $out['notes'], 'the filtered query is what actually gets executed, not the original');
    }

    public function test_create_facade_inserts_an_order_for_an_existing_debtor(): void
    {
        $debtor = self::$fx->debtor();

        $out = $this->runPrimitive('create_minimal', [$debtor['id']]);

        $this->assertGreaterThan(0, $out['id']);
        $this->assertGreaterThan(0, $out['ordrenr']);
        $this->assertSame($debtor['id'], $out['konto_id']);
        $this->assertFalse($out['debtor_created'], 'an existing debtor is reused, not recreated');
        $this->assertTrue($out['ok'], 'a successful insert reports ok=true');

        $row = self::one('SELECT * FROM ordrer WHERE id = $1', [$out['id']]);
        $this->assertSame('DO', $row['art']);
        $this->assertSame($debtor['kontonr'], $row['kontonr']);
    }

    public function test_insert_reports_ok_false_on_a_real_db_modify_failure(): void
    {
        // Proves the hook OrderModel::save() (JWT REST) needs: unlike the other
        // 3 migrated callers, it must be able to tell a failed insert from a
        // successful one instead of always proceeding to the id lookup.
        $out = $this->runPrimitive('insert_reports_ok_false_on_failure');

        $this->assertFalse($out['ok'], "a genuine db_modify() failure (art too long for its varchar(2) column) must report ok=false");
    }
}
