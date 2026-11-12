<?php
// 20261025 CL/ASR Fee for manual payment: which paid invoices give a fee on the next invoice, once, and when it is waived.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/stripeIncludes/stripeManualFee.php';

final class StripeManualFeeTest extends StripeDatabaseTestCase
{
    private int $feeProduct = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $varenr = 'FEE' . bin2hex(random_bytes(4));
        $row = pg_fetch_assoc(StripeTestEnvironment::query("INSERT INTO varer (varenr, beskrivelse, salgspris, gruppe)
            VALUES ($1, 'Gebyr for manuel betaling', 129, 1) RETURNING id", [$varenr]));
        $this->feeProduct = (int)$row['id'];
        StripeTestEnvironment::setting('stripe', 'manual_fee_varenr', $varenr);
        StripeTestEnvironment::setting('stripe', 'manual_fee_from', '2026-01-01');
    }

    /** Issued invoice for $debtor; $paid = settle its open item (bank transfer). */
    private function invoice(int $debtor, array $overrides = [], bool $paid = false): int
    {
        $id = StripeFixtures::invoice($debtor, $overrides);
        if ($paid) {
            StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1' WHERE faktnr = $1", [(string)(90000000 + $id)]);
        }
        return $id;
    }

    private function stripePayment(int $order, string $source, bool $saved, string $status = 'succeeded'): void
    {
        StripeFixtures::insert('stripe_payments', ['ordre_id' => $order, 'payment_intent_id' => 'pi_fee_' . $order . '_' . $source,
            'amount_ore' => 34000, 'status' => $status, 'source' => $source, 'save_card' => $saved,
            'pm_brand' => 'visa', 'pm_last4' => '4242', 'succeeded_at' => StripeFixtures::TODAY]);
    }

    private static function ids(array $rows): array
    {
        return array_map(fn($row) => (int)$row['id'], $rows);
    }

    public function testOnlyManualPaymentsAfterTheStartDateGiveAFeeAndOnlyOnce(): void
    {
        $debtor = StripeFixtures::debtor();
        $bank = $this->invoice($debtor, [], true);
        $once = $this->invoice($debtor);
        $this->stripePayment($once, 'page', false);
        $enrolled = $this->invoice($debtor);
        $this->stripePayment($enrolled, 'page', true);
        $charged = $this->invoice($debtor);
        $this->stripePayment($charged, 'auto', false);
        $unpaid = $this->invoice($debtor);
        $pbs = $this->invoice($debtor, ['udskriv_til' => 'PBS'], true);
        $cash = $this->invoice($debtor, ['betalingsbet' => 'Kontant', 'betalingsdage' => 0], true);
        $credited = $this->invoice($debtor, [], true);
        StripeFixtures::order($debtor, ['art' => 'DK', 'kred_ord_id' => $credited]);
        $refunded = $this->invoice($debtor);
        $this->stripePayment($refunded, 'page', false, 'refunded');
        $early = $this->invoice($debtor, [], true);
        StripeTestEnvironment::query("UPDATE ordrer SET fakturadate = '2025-12-31' WHERE id = $1", [$early]);

        $next = StripeFixtures::order($debtor);
        $decision = stripe_manual_fee_decide($next);
        $this->assertSame([$bank, $once], self::ids($decision['charge']));
        $this->assertSame([], $decision['waive']);
        // Paid automatically or never chargeable: decided once as exempt, so later postings skip them.
        $this->assertEqualsCanonicalizing([$enrolled, $charged, $pbs, $cash, $credited, $refunded], self::ids($decision['exempt']));
        $this->assertSame($this->feeProduct, (int)$decision['vare']['id']);
        $this->assertSame('Gebyr for manuel betaling (fakturaer ' . (90000000 + $bank) . ', ' . (90000000 + $once) . ')',
            stripe_manual_fee_line_text($decision['vare'], $decision['charge']));

        stripe_manual_fee_record($next, $decision);
        foreach ([$bank => 'charged', $once => 'charged', $charged => 'exempt', $credited => 'exempt'] as $paid => $status) {
            $this->assertSame($status, pg_fetch_result(StripeTestEnvironment::query('SELECT status FROM stripe_manual_fees WHERE ordre_id = $1', [$paid]), 0, 0));
        }
        // Decided once: the next invoice does not charge the same payments again.
        $this->assertSame([], stripe_manual_fee_decide(StripeFixtures::order($debtor))['charge']);
        // The unpaid invoice counts once it is paid by bank transfer.
        StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1' WHERE faktnr = $1", [(string)(90000000 + $unpaid)]);
        $later = stripe_manual_fee_decide(StripeFixtures::order($debtor));
        $this->assertSame([$unpaid], self::ids($later['charge']));
        $this->assertSame([], $later['exempt']);
        $this->assertNotContains($early, self::ids(stripe_manual_fee_pending($debtor, 0, '2026-01-01')['manual']));
    }

    public function testAnOrderPostedAsACreditNoteNeverGetsAFee(): void
    {
        $debtor = StripeFixtures::debtor();
        $this->invoice($debtor, [], true);
        $credit = StripeFixtures::order($debtor);
        StripeTestEnvironment::query('UPDATE ordrelinjer SET antal = -antal WHERE ordre_id = $1', [$credit]);
        $decision = stripe_manual_fee_decide($credit);
        $this->assertSame([], $decision['charge']);
        $this->assertSame([], $decision['exempt']);
        $this->assertNull($decision['vare']);
    }

    public function testCustomerWhoPaysAutomaticallyNowHasPendingFeesWaived(): void
    {
        foreach ([
            'card on file' => [['stripe' => 'on', 'stripe_pm_id' => 'pm_saved'], []],
            'PBS agreement' => [['pbs_nr' => '12345'], []],
            'Stripe order' => [[], ['udskriv_til' => 'Stripe']],
        ] as $case => [$debtorFields, $orderFields]) {
            $debtor = StripeFixtures::debtor($debtorFields);
            $bank = $this->invoice($debtor, [], true);
            $decision = stripe_manual_fee_decide(StripeFixtures::order($debtor, $orderFields));
            $this->assertSame([], $decision['charge'], $case);
            $this->assertSame([$bank], self::ids($decision['waive']), $case);
        }
    }

    public function testFeeIsOffWithoutProductStartDateEnrolmentOrOutsideTheStripeLedger(): void
    {
        $debtor = StripeFixtures::debtor();
        $this->invoice($debtor, [], true);
        $next = StripeFixtures::order($debtor);
        $this->assertCount(1, stripe_manual_fee_decide($next)['charge']);
        $this->assertSame(16125, stripe_manual_fee_ore(25));
        $this->assertSame(16125, stripe_manual_fee_ore(25, '2026-01-08'));
        // The page note is not shown for an invoice dated before the start date (it can never give a fee).
        $this->assertSame(0, stripe_manual_fee_ore(25, '2025-12-31'));

        foreach ([['manual_fee_varenr', ''], ['manual_fee_varenr', 'NO-SUCH-ITEM'], ['manual_fee_from', ''], ['manual_fee_from', '2026-02-30'],
            ['enrolment', 'off'], ['invoice_payments', 'off'], ['enabled', 'off']] as [$name, $value]) {
            $saved = stripe_setting($name);
            StripeTestEnvironment::setting('stripe', $name, $value);
            $this->assertNull(stripe_manual_fee_config(), $name);
            $this->assertSame([], stripe_manual_fee_decide($next)['charge'], $name);
            $this->assertSame(0, stripe_manual_fee_ore(25), $name);
            StripeTestEnvironment::setting('stripe', $name, $saved);
        }
        $db = $GLOBALS['db'];
        try {
            $GLOBALS['db'] = 'another_ledger';
            $this->assertNull(stripe_manual_fee_config());
        } finally {
            $GLOBALS['db'] = $db;
        }
        // Credit notes never get a fee line.
        $this->assertSame([], stripe_manual_fee_decide(StripeFixtures::order($debtor, ['art' => 'DK']))['charge']);
    }

    public function testPostingAddsTheLineOnceAndRecurringCopiesLeaveItOut(): void
    {
        $ordrefunc = file_get_contents(__DIR__ . '/../../includes/ordrefunc.php');
        $hook = strpos($ordrefunc, 'stripe_manual_fee_bogfor($id, $art, $momssats, $webservice, $genfakt)');
        $this->assertNotFalse($hook);
        $this->assertGreaterThan(strpos($ordrefunc, "return ('Manglende vare til procenttillæg -- '"), $hook);
        $this->assertLessThan(strpos($ordrefunc, '$qtxt = "select * from ordrelinjer where ordre_id=\'$id\' and antal!=\'0\' order by saet";'), $hook);
        $fee = file_get_contents(__DIR__ . '/../../includes/stripeIncludes/stripeManualFee.php');
        // kdo = 'on' (14th argument) keeps the line out of genfakt's recurring copy.
        $this->assertStringContainsString("\$posnr, '', '', 'on', 'percent',", $fee);
        // No customer discount on the fee line; the order totals follow the stored line; levering() gets the posting context.
        $this->assertStringContainsString("set rabat = 0, m_rabat = 0, leveres = '\$count'", $fee);
        $this->assertStringContainsString('levering($id, \'\', $genfakt, $webservice)', $fee);
        $this->assertStringContainsString("(kdo!='on' or kdo is NULL)", file_get_contents(__DIR__ . '/../../debitor/genfakturer.php'));
    }
}
