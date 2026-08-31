<?php
// tests/characterization/order-creation/ClassicOrderCreationCharacterizationTest.test.php
//
// Characterization tests for the classic UI order-creation path (SD-600,
// scope item 2: characterize what each of the three INSERT INTO ordrer
// paths currently writes to ordrer/adresser before any unification design).
//
// Pins includes/ordrefunc.php::opret_ordre(), reached in production only via
// debitor/ordre.php?funktion=opret_ordre&sag_id=..&konto_id=.. from
// sager/sager.php:2106/2108 (the "new quote/tilbud for this case" button).
// There is no plain "new order without a case" trigger of this function -
// see Fixtures::sag() for the grep that established that.
//
// Requires the docker-compose stack - skips cleanly otherwise (inherited
// from CharacterizationTestCase).
//
// History:
// 20260805 CL/SZ SD-600: created.
// 20260825 CL/SZ SD-600: tilbudnr fixed per Nicolai to a plain max(tilbudnr)+1
//                scoped by sagsnr - updated assertions to match, and added a
//                test that two different cases don't share a tilbudnr sequence.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class ClassicOrderCreationCharacterizationTest extends CharacterizationTestCase
{
    private function runCreate(int $sagId, int $kontoId): array
    {
        return $this->runChildJson('run_order_create_classic.php', ['quote', $sagId, $kontoId, CharacterizationEnv::testDb()]);
    }

    public function test_creating_a_quote_for_a_case_writes_the_cases_own_debtor_to_ordrer(): void
    {
        $debtor = self::$fx->debtor();
        $sag = self::$fx->sag($debtor);

        $out = $this->runCreate($sag['id'], $debtor['id']);

        $this->assertGreaterThan(0, $out['id'], 'an order row is created');
        $this->assertGreaterThan(0, $out['ordrenr'], 'an order number is assigned');
        $this->assertSame($debtor['id'], $out['konto_id'], 'ordrer.konto_id is the case\'s own debtor');
        $this->assertSame($debtor['kontonr'], $out['kontonr'], 'ordrer.kontonr matches the debtor');
        $this->assertSame('DO', $out['art'], "art is hardcoded to 'DO' regardless of context");
        $this->assertSame('0', (string)$out['status'], 'a freshly created quote starts at status 0');
        $this->assertSame($sag['id'], $out['sag_id'], 'ordrer.sag_id links back to the case');
        $this->assertSame('1', (string)$out['tilbudnr'], 'first tilbudnr for a case is 1');
        $this->assertSame('1', (string)$out['nr'], 'first quote for a case gets nr=1');
    }

    public function test_a_second_quote_for_the_same_case_increments_tilbudnr_and_nr(): void
    {
        $debtor = self::$fx->debtor();
        $sag = self::$fx->sag($debtor);

        $first = $this->runCreate($sag['id'], $debtor['id']);
        $second = $this->runCreate($sag['id'], $debtor['id']);

        $this->assertNotSame($first['id'], $second['id'], 'each call creates a new order row, not a re-save');
        // Fixed per Nicolai (SD-600): tilbudnr is max(tilbudnr)+1 scoped by
        // sagsnr (ordrefunc.php), a plain revision number - no more hyphenated
        // "<sagsnr>-0N" string against the numeric(15,0) column.
        $this->assertSame('2', (string)$second['tilbudnr'], 'tilbudnr sequence number increments per case');
        $this->assertSame('2', (string)$second['nr'], 'nr increments per case independently of tilbudnr');
    }

    public function test_two_different_cases_each_start_their_own_tilbudnr_at_one(): void
    {
        $debtorA = self::$fx->debtor();
        $sagA = self::$fx->sag($debtorA);
        $debtorB = self::$fx->debtor();
        $sagB = self::$fx->sag($debtorB);

        $this->runCreate($sagA['id'], $debtorA['id']);
        $outB = $this->runCreate($sagB['id'], $debtorB['id']);

        // tilbudnr is scoped by sagsnr, not global - a different case's
        // quotes must not bump this case's tilbudnr sequence (SD-600).
        $this->assertSame('1', (string)$outB['tilbudnr'], 'a different case starts its own tilbudnr at 1');
    }

    public function test_order_numbers_are_sequential_across_cases(): void
    {
        $debtorA = self::$fx->debtor();
        $sagA = self::$fx->sag($debtorA);
        $first = $this->runCreate($sagA['id'], $debtorA['id']);

        $debtorB = self::$fx->debtor();
        $sagB = self::$fx->sag($debtorB);
        $second = $this->runCreate($sagB['id'], $debtorB['id']);

        $this->assertSame(
            $first['ordrenr'] + 1,
            $second['ordrenr'],
            'ordrenr comes from the shared get_next_order_number("DO") helper, sequential across unrelated cases'
        );
    }
}
