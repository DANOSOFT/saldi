<?php
// tests/characterization/finans/StripeJournalSettlementTest.test.php
//
// A card payment of an invoice is booked by the Stripe payment path as a line in the "Stripe-betalinger" journal
// (debit the card clearing account, credit the customer, with the invoice number and the order's project). It settles
// the invoice only when staff post that journal: finans/bogfor.php openpost() looks for the invoice's unsettled open item
// with the same customer, invoice number and project, within half an øre of the payment, and right after the posting
// equalizeMatchingRecords() (includes/genberegn.php) settles an open item and a payment with the same customer, invoice
// number and exact amount whatever their projects.
//
// These tests take the whole way: the order is invoiced by the real invoice posting (run_order_invoice.php, which gives
// the open item the order lines' project), the capture is booked by the real payment path (run_stripe_capture.php, no
// Stripe request or mail), and the journal is posted by the real posting page (run_bogfor_page.php).
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261126 ASR Created for the payment review: an invoice whose open item has the lines' project, not the order's, is settled when the journal is posted (GPT-P-3).
//              A capture after the invoice was paid another way (GPT-P-2), or of an invoice split in two open items (GPT-P-3), is posted as the customer's open credit and alerted.
// 20261126 ASR Payment review (GPT-G-2): an open item within half an øre of the total, not exact, is collected by card only with the order's project, as only then posting settles it.
// 20261126 ASR Payment review (GPT-G-3): an overpaid capture is posted with the reference Stripe-overbetaling-<payment id>, so it settles no other open item without an invoice number either.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class StripeJournalSettlementTest extends CharacterizationTestCase
{
    /** The card clearing account (grupper DIV/3 box10) the Stripe journal debits. */
    private static function clearingAccount(): int
    {
        [$clearing] = self::$fx->plainAccounts(1);
        if (self::one("SELECT id FROM grupper WHERE art = 'DIV' AND kodenr = '3'")) {
            self::rows("UPDATE grupper SET box10 = $1 WHERE art = 'DIV' AND kodenr = '3'", [(string)$clearing]);
        } else {
            self::rows("INSERT INTO grupper (beskrivelse, art, kodenr, kode, box10, fiscal_year) VALUES ('Diverse', 'DIV', '3', '', $1, $2)",
                [(string)$clearing, self::$regnaar]);
        }
        return (int)$clearing;
    }

    /** An invoiced order of 2 x 100 kr. + VAT = 250 kr.; $lineProject on its lines, none on the order. */
    private function invoice(array $debtor, string $lineProject): array
    {
        $item = self::$fx->item(['stocked' => false]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => 2, 'pris' => 100]]);
        self::rows("UPDATE ordrelinjer SET projekt = $1 WHERE ordre_id = $2", [$lineProject, $order['id']]);
        self::rows("UPDATE ordrer SET projekt = '' WHERE id = $1", [$order['id']]);
        $out = $this->runChildJson('run_order_invoice.php', ['invoice', $order['id'], CharacterizationEnv::testDb()]);
        $this->assertSame('OK', $out['bogfor'], 'the order is invoiced');
        return ['id' => $order['id'], 'fakturanr' => (string)$out['fakturanr']];
    }

    /** @return list<array<string,string>> the customer's open items, oldest first */
    private function openItems(array $debtor): array
    {
        return self::rows("SELECT faktnr, amount, udlignet, udlign_id, coalesce(projekt, '') AS projekt FROM openpost WHERE konto_id = $1 ORDER BY id", [$debtor['id']]);
    }

    public function test_a_card_payment_of_an_invoice_with_a_line_project_settles_it_when_the_stripe_journal_is_posted(): void
    {
        $clearing = self::clearingAccount();
        $debtor = self::$fx->debtor();
        $invoice = $this->invoice($debtor, '821');
        $open = $this->openItems($debtor);
        $this->assertCount(1, $open);
        $this->assertSame('821', $open[0]['projekt'], 'precondition: invoice posting gives the open item the lines\' project');
        $receivable = self::one('SELECT coalesce(projekt, \'\') AS projekt FROM transaktioner WHERE ordre_id = $1 AND debet > 0', [$invoice['id']]);
        $this->assertSame('', $receivable['projekt'], 'precondition: and the receivable the order\'s (none)');

        $capture = $this->runChildJson('run_stripe_capture.php', [$invoice['id'], CharacterizationEnv::testDb()]);
        $this->assertSame('applied', $capture['result']);
        $this->assertNull($capture['last_error']);
        $this->assertSame([$invoice['fakturanr'], '', $clearing], [(string)$capture['line']['faktura'], (string)$capture['line']['projekt'], (int)$capture['line']['debet']]);
        $this->assertSame([], $capture['alerts']);

        self::runChild('run_bogfor_page.php', ['bogfor', (int)$capture['line']['kladde_id'], CharacterizationEnv::sessionId()]);

        $open = $this->openItems($debtor);
        $this->assertCount(2, $open, 'the invoice and the card payment');
        foreach ($open as $item) {
            $this->assertSame('1', trim((string)$item['udlignet']), 'posting the Stripe journal settles the invoice');
        }
        $this->assertSame($open[0]['udlign_id'], $open[1]['udlign_id']);
        $this->assertEqualsWithDelta(0.0, self::sumOf($open, 'amount'), 0.005);
    }

    public function test_an_open_item_within_half_an_oere_is_collected_by_card_only_with_the_orders_project(): void
    {
        $clearing = self::clearingAccount();

        // The open item is 250.004 kr. and has the order's project (none): openpost() settles the 250.00 payment within half an øre.
        $debtor = self::$fx->debtor();
        $invoice = $this->invoice($debtor, '');
        self::rows("UPDATE openpost SET amount = 250.004 WHERE konto_id = $1", [$debtor['id']]);
        $capture = $this->runChildJson('run_stripe_capture.php', [$invoice['id'], CharacterizationEnv::testDb()]);
        $this->assertTrue($capture['fully_open'], 'collectable by card');
        $this->assertSame(['applied', null], [$capture['result'], $capture['last_error']]);
        $this->assertSame([$invoice['fakturanr'], ''], [(string)$capture['line']['faktura'], (string)$capture['line']['projekt']]);
        self::runChild('run_bogfor_page.php', ['bogfor', (int)$capture['line']['kladde_id'], CharacterizationEnv::sessionId()]);
        $open = $this->openItems($debtor);
        $this->assertCount(2, $open);
        $this->assertSame(['1', '1'], [trim((string)$open[0]['udlignet']), trim((string)$open[1]['udlignet'])], 'posting settles the invoice');
        $this->assertSame($open[0]['udlign_id'], $open[1]['udlign_id']);

        // The same open item with the order lines' project, not the order's: openpost() does not take it (another project) and
        // equalizeMatchingRecords() does not either (not the exact amount). Not collected by card: paid by bank transfer.
        $debtor = self::$fx->debtor();
        $invoice = $this->invoice($debtor, '821');
        self::rows("UPDATE openpost SET amount = 250.004 WHERE konto_id = $1", [$debtor['id']]);
        $check = $this->runChildJson('run_stripe_capture.php', [$invoice['id'], CharacterizationEnv::testDb(), 'check']);
        $this->assertFalse($check['fully_open'], 'not collectable by card');
        $this->assertFalse($check['open_item']['settles']);
        // Why: the journal line a card payment would have written (debit clearing, credit the customer, the invoice number, the
        // order's project), posted for real, leaves both the invoice and the payment open.
        $kladde = self::$fx->kladde([['d_type' => 'F', 'debet' => $clearing, 'k_type' => 'D', 'kredit' => (int)$debtor['kontonr'],
            'amount' => 250.00, 'faktura' => $invoice['fakturanr'], 'tekst' => 'Stripe ' . $invoice['fakturanr']]], 'stripe ' . $invoice['fakturanr']);
        self::runChild('run_bogfor_page.php', ['bogfor', $kladde, CharacterizationEnv::sessionId()]);
        $open = $this->openItems($debtor);
        $this->assertCount(2, $open);
        $this->assertSame(['0', '0'], [trim((string)$open[0]['udlignet']), trim((string)$open[1]['udlignet'])], 'neither is settled');
    }

    public function test_a_card_payment_of_an_invoice_split_in_two_open_items_is_posted_as_the_customers_credit(): void
    {
        self::clearingAccount();
        $debtor = self::$fx->debtor();
        $invoice = $this->invoice($debtor, '');
        $open = self::one('SELECT * FROM openpost WHERE konto_id = $1', [$debtor['id']]);
        self::rows("UPDATE openpost SET amount = 125 WHERE id = $1", [$open['id']]);
        self::rows("INSERT INTO openpost (konto_id, konto_nr, faktnr, amount, beskrivelse, udlignet, transdate, forfaldsdate, refnr, valuta, valutakurs, projekt)
            VALUES ($1, $2, $3, 125, $4, '0', $5, $6, $7, $8, $9, '')",
            [$open['konto_id'], $open['konto_nr'], $open['faktnr'], $open['beskrivelse'], $open['transdate'], $open['forfaldsdate'], $open['refnr'], $open['valuta'], $open['valutakurs']]);

        // The payment page no longer offers card payment for it; a capture of an intent from before is still booked.
        $capture = $this->runChildJson('run_stripe_capture.php', [$invoice['id'], CharacterizationEnv::testDb()]);
        $this->assertSame('applied', $capture['result']);
        $this->assertSame('overpaid', $capture['last_error'], 'a payment of the total settles neither open item');
        $this->assertStringStartsWith('Stripe-overbetaling-', (string)$capture['line']['faktura']);
        $this->assertCount(1, $capture['alerts'], 'the bookkeeper is told');

        self::runChild('run_bogfor_page.php', ['bogfor', (int)$capture['line']['kladde_id'], CharacterizationEnv::sessionId()]);

        $open = $this->openItems($debtor);
        $this->assertCount(3, $open);
        $this->assertSame(['0', '0', '0'], array_map(static fn ($item) => trim((string)$item['udlignet']), $open), 'left for the bookkeeper to settle');
        $this->assertEqualsWithDelta(0.0, self::sumOf($open, 'amount'), 0.005, 'the credit equals what the invoice owes');
    }

    public function test_a_card_payment_after_a_bank_payment_is_posted_as_the_customers_credit(): void
    {
        self::clearingAccount();
        $debtor = self::$fx->debtor();
        $invoice = $this->invoice($debtor, '');
        // The customer paid by bank transfer, and it was posted, while the card payment was under way.
        [, $bank] = self::$fx->plainAccounts(2);
        $kladde = self::$fx->kladde([['d_type' => 'F', 'debet' => $bank, 'k_type' => 'D', 'kredit' => (int)$debtor['kontonr'],
            'amount' => 250.00, 'faktura' => $invoice['fakturanr'], 'tekst' => 'bank ' . $invoice['fakturanr']]], 'bank ' . $invoice['fakturanr']);
        self::runChild('run_bogfor_page.php', ['bogfor', $kladde, CharacterizationEnv::sessionId()]);
        $settled = $this->openItems($debtor);
        $this->assertSame(['1', '1'], [trim((string)$settled[0]['udlignet']), trim((string)$settled[1]['udlignet'])], 'precondition: paid by bank');

        $capture = $this->runChildJson('run_stripe_capture.php', [$invoice['id'], CharacterizationEnv::testDb()]);
        $this->assertSame('applied', $capture['result']);
        $this->assertSame('overpaid', $capture['last_error']);
        $this->assertStringStartsWith('Stripe-overbetaling-', (string)$capture['line']['faktura']);
        $this->assertCount(1, $capture['alerts'], 'the bookkeeper is told');

        self::runChild('run_bogfor_page.php', ['bogfor', (int)$capture['line']['kladde_id'], CharacterizationEnv::sessionId()]);

        $open = $this->openItems($debtor);
        $this->assertCount(3, $open);
        $this->assertSame([$settled[0]['udlign_id'], $settled[1]['udlign_id']], [$open[0]['udlign_id'], $open[1]['udlign_id']], 'the bank settlement is left as it was');
        $this->assertSame([(string)$capture['line']['faktura'], '0'], [trim((string)$open[2]['faktnr']), trim((string)$open[2]['udlignet'])], 'the card payment stays open, with its reference');
        $this->assertEqualsWithDelta(-250.0, (float)$open[2]['amount'], 0.005, 'as the customer\'s credit of 250 kr.');
    }

    public function test_a_card_payment_after_a_bank_payment_settles_no_open_item_without_an_invoice_number(): void
    {
        $clearing = self::clearingAccount();
        $debtor = self::$fx->debtor();
        $invoice = $this->invoice($debtor, '');
        [, $bank, $other] = self::$fx->plainAccounts(3);
        // An unrelated 250 kr. the customer owes, posted without an invoice number (a remainder or an opening item).
        $kladde = self::$fx->kladde([['d_type' => 'D', 'debet' => (int)$debtor['kontonr'], 'k_type' => 'F', 'kredit' => $other,
            'amount' => 250.00, 'faktura' => '', 'tekst' => 'rest uden fakturanr.']], 'rest ' . $debtor['kontonr']);
        self::runChild('run_bogfor_page.php', ['bogfor', $kladde, CharacterizationEnv::sessionId()]);
        // The invoice is paid by bank transfer while the card payment is under way.
        $kladde = self::$fx->kladde([['d_type' => 'F', 'debet' => $bank, 'k_type' => 'D', 'kredit' => (int)$debtor['kontonr'],
            'amount' => 250.00, 'faktura' => $invoice['fakturanr'], 'tekst' => 'bank ' . $invoice['fakturanr']]], 'bank ' . $invoice['fakturanr']);
        self::runChild('run_bogfor_page.php', ['bogfor', $kladde, CharacterizationEnv::sessionId()]);
        $before = $this->openItems($debtor);
        $this->assertCount(3, $before);
        $this->assertSame(['', '0'], [trim((string)$before[1]['faktnr']), trim((string)$before[1]['udlignet'])], 'precondition: the unnumbered debit is open');
        $this->assertEqualsWithDelta(250.0, (float)$before[1]['amount'], 0.005);

        $capture = $this->runChildJson('run_stripe_capture.php', [$invoice['id'], CharacterizationEnv::testDb()]);
        $this->assertSame(['applied', 'overpaid'], [$capture['result'], $capture['last_error']]);
        $this->assertSame($clearing, (int)$capture['line']['debet']);
        self::runChild('run_bogfor_page.php', ['bogfor', (int)$capture['line']['kladde_id'], CharacterizationEnv::sessionId()]);

        $open = $this->openItems($debtor);
        $this->assertCount(4, $open);
        $this->assertSame(['', '0', $before[1]['udlign_id']], [trim((string)$open[1]['faktnr']), trim((string)$open[1]['udlignet']), $open[1]['udlign_id']],
            'the unnumbered debit stays open');
        $this->assertSame([(string)$capture['line']['faktura'], '0'], [trim((string)$open[3]['faktnr']), trim((string)$open[3]['udlignet'])],
            'and so does the card payment, for the bookkeeper to reconcile');
        $this->assertEqualsWithDelta(-250.0, (float)$open[3]['amount'], 0.005);
        $this->assertSame([$before[0]['udlign_id'], $before[2]['udlign_id']], [$open[0]['udlign_id'], $open[2]['udlign_id']], 'the bank settlement is left as it was');
    }
}
