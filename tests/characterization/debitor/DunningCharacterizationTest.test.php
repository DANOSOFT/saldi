<?php
// tests/characterization/debitor/DunningCharacterizationTest.test.php
//
// Characterization tests for the dunning generator (debitor/ny_rykker.php).
//
// The automatic run ("Dan rykkere", kontoliste=alle) does three things:
//
//   1. every unsettled open post that is overdue by more than ffdage1 days
//      gets a level-1 dunning order (art='R1') with a fee line;
//   2. every standing R1 older than ffdage2 days is booked and escalated;
//   3. every standing R2 older than ffdage3 days is booked and escalated.
//
// All three grace periods were broken, in two separate ways, and both are
// fixed now - the tests below are the regression guard for each:
//
//   * (2) and (3): a stray semicolon - `$utid = date('U');-$ffdage2*3600*24;`
//     - made the cutoff "now" instead of "now minus the grace period", so a
//     dunning raised yesterday was escalated today regardless of the
//     configured grace days. Fixed in b09ccd49 (PR #422); guarded by
//     test_a_level_1_dunning_inside_its_grace_period_is_not_escalated.
//
//   * (1): $rykkerfrist1 was computed from $dd before $dd was assigned, so
//     forfaldsdag() returned NULL, usdate(NULL) fell back to today, and
//     ffdage1 was discarded - customers were dunned the day after an invoice
//     fell due whatever the setting said. Fixed as a follow-up to SD-594; the
//     cutoff is now today minus ffdage1, the same arithmetic as (2) and (3).
//     Guarded by test_an_invoice_inside_the_first_dunning_grace_period_is_not_dunned.
//
// The grace-day counts and the fee item are tenant settings that a stock
// installation does not ship - see Fixtures::dunningConfig().
//
// Needs a postgres tenant to clone - skips cleanly otherwise. The docker
// stack is one way; SALDI_CHAR_* env vars can point it at any local tenant.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.
// 20260803 Sawaneh Rewrote the pinned ffdage1 defect as a grace-period
//                  assertion now that the cutoff honours ffdage1.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class DunningCharacterizationTest extends CharacterizationTestCase
{
    private const FFDAGE1 = 8;   // days overdue before an invoice is dunned
    private const FFDAGE2 = 10;  // days an R1 may stand before escalation
    private const FFDAGE3 = 14;  // days an R2 may stand before escalation

    private static int $faktnrSeq = 7000;

    protected function setUp(): void
    {
        // Every test starts from a tenant with no receivables and no dunnings
        // of its own, so an "all debtors" run only sees what the test put
        // there.
        self::rows("DELETE FROM ordrelinjer WHERE ordre_id IN (SELECT id FROM ordrer WHERE art LIKE 'R%')");
        self::rows("DELETE FROM ordrer WHERE art LIKE 'R%'");
        self::rows("DELETE FROM openpost");

        self::$fx->dunningConfig(self::FFDAGE1, self::FFDAGE2, self::FFDAGE3);
    }

    private static function nextFaktnr(): string
    {
        return (string)(++self::$faktnrSeq);
    }

    private function runDunning(): void
    {
        self::runChild('run_ny_rykker.php', [CharacterizationEnv::SESSION_ID]);
    }

    /** @return list<array<string,string>> dunning orders for a debtor, oldest first */
    private function dunningsFor(array $debtor): array
    {
        return self::rows(
            "SELECT id, art, status, betalt, sum, ordredate FROM ordrer
             WHERE konto_id = $1 AND art LIKE 'R%' ORDER BY id",
            [$debtor['id']]
        );
    }

    public function test_an_invoice_overdue_beyond_the_grace_period_is_dunned(): void
    {
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, 1000.00, self::FFDAGE1 + 5, self::nextFaktnr());

        $this->runDunning();

        $dunnings = $this->dunningsFor($debtor);
        $this->assertCount(1, $dunnings, 'one level-1 dunning is raised');
        $this->assertSame('R1', trim($dunnings[0]['art']), 'the dunning is at level 1');
    }

    /**
     * REGRESSION GUARD for the ffdage1 bug (follow-up to SD-594 / PR #422).
     *
     * An invoice that is overdue, but by fewer than ffdage1 days, is still
     * inside its grace period and must be left alone. Before the fix
     * ny_rykker.php computed the cutoff from $dd before $dd was assigned:
     *
     *     $rykkerfrist1 = usdate(forfaldsdag($dd, 'netto', $ffdage1));
     *
     * forfaldsdag() returns NULL for an empty date (forfaldsdag.php:33) and
     * usdate(NULL) falls back to today, so the cutoff was today, the open-post
     * query selected everything with forfaldsdate <= today, and ffdage1 was
     * discarded - every customer was dunned the day after an invoice fell due.
     * This test fails against the pre-fix code.
     */
    public function test_an_invoice_inside_the_first_dunning_grace_period_is_not_dunned(): void
    {
        $debtor = self::$fx->debtor();
        // Overdue, but well inside the configured 8-day grace period.
        self::$fx->openPost($debtor, 1000.00, 1, self::nextFaktnr());

        $this->runDunning();

        $this->assertSame(
            [],
            $this->dunningsFor($debtor),
            'an invoice one day overdue is inside the grace period of ffdage1 = ' . self::FFDAGE1
        );
    }

    /**
     * Where the boundary falls. The debtor-selection query uses
     * `forfaldsdate <= $rykkerfrist1` but the query that actually collects the
     * posts onto the dunning uses `<`, so an invoice overdue by exactly
     * ffdage1 days is picked up as a candidate and then produces no dunning
     * lines. The effective rule is "overdue by MORE than ffdage1 days".
     *
     * Pinned as characterization, not as intent: if the two comparisons are
     * ever made consistent this test is the one that will say so.
     */
    public function test_an_invoice_overdue_by_exactly_the_grace_period_is_not_yet_dunned(): void
    {
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, 1000.00, self::FFDAGE1, self::nextFaktnr());

        $this->runDunning();

        $this->assertSame(
            [],
            $this->dunningsFor($debtor),
            'the grace period is exclusive - dunning starts the day after ffdage1 days overdue'
        );
    }

    /** An invoice that is not yet due is never dunned, whatever ffdage1 is. */
    public function test_an_invoice_not_yet_due_is_not_dunned(): void
    {
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, 1000.00, -5, self::nextFaktnr()); // due in five days

        $this->runDunning();

        $this->assertSame([], $this->dunningsFor($debtor), 'an invoice that is not yet due is not dunned');
    }

    /** A settled open post is not a receivable and must not be dunned. */
    public function test_a_settled_open_post_is_not_dunned(): void
    {
        $debtor = self::$fx->debtor();
        $postId = self::$fx->openPost($debtor, 1000.00, self::FFDAGE1 + 30, self::nextFaktnr());
        self::rows("UPDATE openpost SET udlignet = '1' WHERE id = $1", [$postId]);

        $this->runDunning();

        $this->assertSame([], $this->dunningsFor($debtor), 'settled posts are not dunned');
    }

    /** A credit balance (negative open post) is not a receivable either. */
    public function test_a_credit_balance_is_not_dunned(): void
    {
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, -1000.00, self::FFDAGE1 + 30, self::nextFaktnr());

        $this->runDunning();

        $this->assertSame([], $this->dunningsFor($debtor), 'a customer in credit is not dunned');
    }

    /** The dunning carries a fee line taken from the configured fee item. */
    public function test_the_dunning_carries_the_configured_fee(): void
    {
        $config = self::$fx->dunningConfig(self::FFDAGE1, self::FFDAGE2, self::FFDAGE3, 100.00);
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, 2500.00, self::FFDAGE1 + 5, self::nextFaktnr());

        $this->runDunning();

        $dunnings = $this->dunningsFor($debtor);
        $this->assertCount(1, $dunnings);

        $feeLines = self::rows(
            'SELECT pris, antal FROM ordrelinjer WHERE ordre_id = $1 AND vare_id = $2',
            [(int)$dunnings[0]['id'], $config['fee_item']['id']]
        );
        $this->assertCount(1, $feeLines, 'the dunning has exactly one fee line');
        $this->assertEqualsWithDelta(100.00, (float)$feeLines[0]['pris'], 0.005, 'the fee is the item sales price');
        $this->assertEqualsWithDelta(100.00, (float)$dunnings[0]['sum'], 0.005, 'the dunning total is the fee');
    }

    /** Two overdue invoices for one debtor are collected into a single dunning. */
    public function test_several_overdue_invoices_produce_one_dunning_per_debtor(): void
    {
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, 400.00, self::FFDAGE1 + 5, self::nextFaktnr());
        self::$fx->openPost($debtor, 600.00, self::FFDAGE1 + 9, self::nextFaktnr());

        $this->runDunning();

        $dunnings = $this->dunningsFor($debtor);
        $this->assertCount(1, $dunnings, 'one dunning covers all of a debtor\'s overdue invoices');

        $postLines = self::rows(
            "SELECT enhed FROM ordrelinjer WHERE ordre_id = $1 AND enhed <> '' AND enhed IS NOT NULL",
            [(int)$dunnings[0]['id']]
        );
        $this->assertCount(2, $postLines, 'both open posts are listed on the dunning');
    }

    /** Separate debtors get separate dunnings. */
    public function test_each_debtor_gets_its_own_dunning(): void
    {
        $first = self::$fx->debtor();
        $second = self::$fx->debtor();
        self::$fx->openPost($first, 100.00, self::FFDAGE1 + 5, self::nextFaktnr());
        self::$fx->openPost($second, 200.00, self::FFDAGE1 + 5, self::nextFaktnr());

        $this->runDunning();

        $this->assertCount(1, $this->dunningsFor($first));
        $this->assertCount(1, $this->dunningsFor($second));
    }

    /**
     * REGRESSION GUARD for the grace-day bug fixed in b09ccd49 (PR #422).
     *
     * A level-1 dunning raised inside its ffdage2 grace period must be left
     * standing. Before the fix, `$rykkerfrist2` was today rather than today
     * minus ffdage2, so any dunning raised before today was booked and
     * escalated immediately - customers were charged the level-2 fee days or
     * weeks early.
     */
    public function test_a_level_1_dunning_inside_its_grace_period_is_not_escalated(): void
    {
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, 1000.00, self::FFDAGE1 + 5, self::nextFaktnr());
        $this->runDunning();

        $dunnings = $this->dunningsFor($debtor);
        $this->assertCount(1, $dunnings, 'precondition: a level-1 dunning exists');
        $dunningId = (int)$dunnings[0]['id'];

        // Age it to just inside the grace period.
        self::rows(
            "UPDATE ordrer SET ordredate = $1 WHERE id = $2",
            [date('Y-m-d', strtotime('-' . (self::FFDAGE2 - 2) . ' days')), $dunningId]
        );

        $this->runDunning();

        $after = self::one('SELECT art, betalt FROM ordrer WHERE id = $1', [$dunningId]);
        $this->assertSame('R1', trim((string)$after['art']), 'the dunning stays at level 1');
        $this->assertNotSame(
            'on',
            trim((string)$after['betalt']),
            'the dunning is not booked before its grace period is up (ny_rykker.php:132)'
        );
    }

    /**
     * The other half of the same guard: once the grace period HAS passed, the
     * level-1 dunning is booked, so the fix did not simply disable escalation.
     */
    public function test_a_level_1_dunning_past_its_grace_period_is_booked(): void
    {
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, 1000.00, self::FFDAGE1 + 5, self::nextFaktnr());
        $this->runDunning();

        $dunnings = $this->dunningsFor($debtor);
        $this->assertCount(1, $dunnings, 'precondition: a level-1 dunning exists');
        $dunningId = (int)$dunnings[0]['id'];

        self::rows(
            "UPDATE ordrer SET ordredate = $1 WHERE id = $2",
            [date('Y-m-d', strtotime('-' . (self::FFDAGE2 + 5) . ' days')), $dunningId]
        );

        $this->runDunning();

        $after = self::one('SELECT betalt FROM ordrer WHERE id = $1', [$dunningId]);
        $this->assertSame(
            'on',
            trim((string)$after['betalt']),
            'the dunning is booked once its grace period has passed'
        );
    }
}
