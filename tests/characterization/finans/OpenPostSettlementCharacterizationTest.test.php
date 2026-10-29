<?php
// tests/characterization/finans/OpenPostSettlementCharacterizationTest.test.php
//
// Characterization tests for open posts and their settlement.
//
// A kassekladde line whose debit or credit side is typed 'D' (debtor) or 'K'
// (creditor) makes finans/bogfor.php call openpost() (bogfor.php:792/797) in
// addition to writing the ledger legs. openpost() (bogfor.php:1064) looks for
// an existing unsettled post on the same account with the same invoice number
// and an amount inside a half-ore window:
//
//     $min = $belob - 0.005;
//     $max = $belob + 0.005;
//     ... and amount >= '$min' and amount < '$max'
//
// If it finds one, both the original and a mirror row are marked udlignet='1'
// and tied together by udlign_id. If it does not, the amount is left standing
// as a new open post. There is no partial settlement: an amount that is not
// within half an ore of the outstanding one simply opens a second post.
//
// Open posts only exist on the real posting path - openpost() is skipped
// entirely when simulating - so every test here posts for real.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.
// 20261028 CL/LH MB-72: The settlement date is the later of invoice and payment date; an "A;B" payment is stored once.
//                A short "A;B" payment leaves the invoices and the payment open.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class OpenPostSettlementCharacterizationTest extends CharacterizationTestCase
{
    /** Invoice number counter, so each test works on its own invoice. */
    private static int $invoiceSeq = 5000;

    private static function nextInvoiceNo(): string
    {
        return (string)(++self::$invoiceSeq);
    }

    /**
     * Post a debtor invoice: debit the debtor account, credit a plain sales
     * account. Returns the invoice number used.
     */
    private function postInvoice(array $debtor, float $amount, string $faktura): void
    {
        [$sales] = self::$fx->plainAccounts(1);
        $kladdeId = self::$fx->kladde([
            [
                'bilag' => 1,
                'd_type' => 'D',
                'debet' => (int)$debtor['kontonr'],
                'k_type' => 'F',
                'kredit' => $sales,
                'amount' => $amount,
                'faktura' => $faktura,
                'tekst' => 'faktura ' . $faktura,
            ],
        ], 'invoice ' . $faktura);
        self::runChild('run_bogfor_page.php', ['bogfor', $kladdeId, CharacterizationEnv::SESSION_ID]);
    }

    /** Post a payment against the debtor: debit a plain bank account, credit the debtor. */
    private function postPayment(array $debtor, float $amount, string $faktura): void
    {
        $accounts = self::$fx->plainAccounts(2);
        $bank = $accounts[1];
        $kladdeId = self::$fx->kladde([
            [
                'bilag' => 1,
                'd_type' => 'F',
                'debet' => $bank,
                'k_type' => 'D',
                'kredit' => (int)$debtor['kontonr'],
                'amount' => $amount,
                'faktura' => $faktura,
                'tekst' => 'betaling ' . $faktura,
            ],
        ], 'payment ' . $faktura);
        self::runChild('run_bogfor_page.php', ['bogfor', $kladdeId, CharacterizationEnv::SESSION_ID]);
    }

    /** @return list<array<string,string>> every open-post row for a debtor, oldest first */
    private function postsFor(array $debtor): array
    {
        return self::rows(
            'SELECT faktnr, amount, udlignet, udlign_id, transdate, udlign_date
             FROM openpost WHERE konto_id = $1 ORDER BY id',
            [$debtor['id']]
        );
    }

    public function test_posting_a_debtor_invoice_creates_an_unsettled_open_post(): void
    {
        $debtor = self::$fx->debtor();
        $faktura = self::nextInvoiceNo();

        $this->postInvoice($debtor, 1000.00, $faktura);

        $posts = $this->postsFor($debtor);
        $this->assertCount(1, $posts, 'exactly one open post for the invoice');
        $this->assertSame($faktura, trim($posts[0]['faktnr']), 'open post carries the invoice number');
        $this->assertEqualsWithDelta(1000.00, (float)$posts[0]['amount'], 0.005, 'open post carries the invoice amount');
        $this->assertNotSame('1', trim((string)$posts[0]['udlignet']), 'the post starts out unsettled');
    }

    public function test_an_exact_payment_settles_the_invoice(): void
    {
        $debtor = self::$fx->debtor();
        $faktura = self::nextInvoiceNo();

        $this->postInvoice($debtor, 1000.00, $faktura);
        $this->postPayment($debtor, 1000.00, $faktura);

        $posts = $this->postsFor($debtor);
        $this->assertCount(2, $posts, 'the invoice post plus its settlement mirror');
        foreach ($posts as $p) {
            $this->assertSame('1', trim((string)$p['udlignet']), 'both rows are marked settled');
        }
        $this->assertSame(
            trim((string)$posts[0]['udlign_id']),
            trim((string)$posts[1]['udlign_id']),
            'settled rows share a udlign_id'
        );
        $this->assertEqualsWithDelta(
            0.0,
            self::sumOf($posts, 'amount'),
            0.005,
            'a settled pair nets to zero'
        );
    }

    /**
     * The half-ore window is the whole tolerance: a payment four tenths of an
     * ore off still settles the invoice.
     */
    public function test_a_payment_inside_the_half_ore_window_still_settles(): void
    {
        $debtor = self::$fx->debtor();
        $faktura = self::nextInvoiceNo();

        $this->postInvoice($debtor, 1000.00, $faktura);
        $this->postPayment($debtor, 1000.004, $faktura);

        $posts = $this->postsFor($debtor);
        $settled = array_filter($posts, static fn($p) => trim((string)$p['udlignet']) === '1');
        $this->assertNotEmpty($settled, 'a payment within half an ore settles the invoice');
        $this->assertCount(count($posts), $settled, 'no post is left standing');
    }

    /**
     * A short payment does not settle anything and does not reduce the
     * outstanding amount - it opens a second post alongside the first.
     * There is no partial settlement in this engine.
     */
    public function test_a_partial_payment_opens_a_second_post_instead_of_reducing_the_first(): void
    {
        $debtor = self::$fx->debtor();
        $faktura = self::nextInvoiceNo();

        $this->postInvoice($debtor, 1000.00, $faktura);
        $this->postPayment($debtor, 400.00, $faktura);

        $posts = $this->postsFor($debtor);
        $this->assertCount(2, $posts, 'the invoice and the payment each stand as their own post');
        foreach ($posts as $p) {
            $this->assertNotSame('1', trim((string)$p['udlignet']), 'neither post is settled');
        }
        $this->assertEqualsWithDelta(
            600.00,
            self::sumOf($posts, 'amount'),
            0.005,
            'the outstanding balance is the net of the two open posts'
        );
    }

    /** A payment quoting a different invoice number leaves both posts open. */
    public function test_a_payment_against_another_invoice_number_settles_nothing(): void
    {
        $debtor = self::$fx->debtor();
        $invoice = self::nextInvoiceNo();
        $wrong = self::nextInvoiceNo();

        $this->postInvoice($debtor, 750.00, $invoice);
        $this->postPayment($debtor, 750.00, $wrong);

        $posts = $this->postsFor($debtor);
        $this->assertCount(2, $posts);
        foreach ($posts as $p) {
            $this->assertNotSame('1', trim((string)$p['udlignet']), 'invoice numbers must match to settle');
        }
    }

    /** Settling one invoice leaves the debtor's other invoices untouched. */
    public function test_settling_one_invoice_leaves_the_others_open(): void
    {
        $debtor = self::$fx->debtor();
        $first = self::nextInvoiceNo();
        $second = self::nextInvoiceNo();

        $this->postInvoice($debtor, 100.00, $first);
        $this->postInvoice($debtor, 200.00, $second);
        $this->postPayment($debtor, 100.00, $first);

        $posts = $this->postsFor($debtor);
        $open = array_values(array_filter($posts, static fn($p) => trim((string)$p['udlignet']) !== '1'));

        $this->assertCount(1, $open, 'only the unpaid invoice is left open');
        $this->assertSame($second, trim($open[0]['faktnr']), 'the unpaid invoice is the one still standing');
        $this->assertEqualsWithDelta(200.00, (float)$open[0]['amount'], 0.005, 'its amount is unchanged');
    }

    /**
     * The settlement date is the later of the invoice date and the payment
     * date (MB-72), so an invoice paid months later is recorded as settled on
     * the day it was paid, not on the day it was raised.
     */
    public function test_settlement_date_is_the_later_of_invoice_and_payment_date(): void
    {
        $debtor = self::$fx->debtor();
        $faktura = self::nextInvoiceNo();
        $invoiceDate = date('Y-m-d', strtotime('-60 days'));

        [$sales] = self::$fx->plainAccounts(1);
        $kladdeId = self::$fx->kladde([
            [
                'bilag' => 1,
                'd_type' => 'D',
                'debet' => (int)$debtor['kontonr'],
                'k_type' => 'F',
                'kredit' => $sales,
                'amount' => 500.00,
                'faktura' => $faktura,
                'transdate' => $invoiceDate,
                'tekst' => 'gammel faktura',
            ],
        ], 'old invoice');
        self::runChild('run_bogfor_page.php', ['bogfor', $kladdeId, CharacterizationEnv::SESSION_ID]);

        $this->postPayment($debtor, 500.00, $faktura);

        $posts = $this->postsFor($debtor);
        $this->assertCount(2, $posts);
        foreach ($posts as $p) {
            $this->assertSame('1', trim((string)$p['udlignet']), 'the old invoice is settled');
            $this->assertSame(
                date('Y-m-d'),
                substr((string)$p['udlign_date'], 0, 10),
                'udlign_date is the payment date, the later of the two'
            );
        }
    }

    /**
     * An open invoice stored the way order invoicing stores it: projekt '0',
     * while a journal line without a project carries ''.
     */
    private function insertOrderInvoice(array $debtor, string $faktnr, float $amount, string $date): void
    {
        self::rows(
            "INSERT INTO openpost (konto_id, konto_nr, faktnr, amount, beskrivelse, udlignet,
                                   transdate, forfaldsdate, valuta, valutakurs, projekt)
             VALUES ($1, $2, $3, $4, $5, '0', $6, $6, 'DKK', 100, '0')",
            [$debtor['id'], $debtor['kontonr'], $faktnr, $amount, Fixtures::TAG . ' faktura ' . $faktnr, $date]
        );
    }

    /** Post one journal payment from the debtor whose Faktura field is $faktura (e.g. "A;B"). */
    private function postPaymentOn(array $debtor, float $amount, string $faktura, string $date): void
    {
        $accounts = self::$fx->plainAccounts(2);
        $kladdeId = self::$fx->kladde([
            [
                'bilag' => 1,
                'd_type' => 'F',
                'debet' => $accounts[1],
                'k_type' => 'D',
                'kredit' => (int)$debtor['kontonr'],
                'amount' => $amount,
                'faktura' => $faktura,
                'transdate' => $date,
                'tekst' => 'betaling ' . $faktura,
            ],
        ], 'payment ' . $faktura);
        self::runChild('run_bogfor_page.php', ['bogfor', $kladdeId, CharacterizationEnv::SESSION_ID]);
    }

    /**
     * A payment whose Faktura lists several invoices ("A;B") settles them as
     * one group and is stored once, with its own amount. MB-72: before, the
     * payment row was written once per settled invoice, so the customer
     * statement showed the payment twice. The invoices carry projekt '0', as
     * order invoices do; the journal line has no project.
     */
    public function test_a_payment_settling_two_invoices_is_stored_once(): void
    {
        $debtor = self::$fx->debtor();
        $first = self::nextInvoiceNo();
        $second = self::nextInvoiceNo();
        $secondDate = date('Y-m-d', strtotime('-5 days'));

        $this->insertOrderInvoice($debtor, $first, 200.00, date('Y-m-d', strtotime('-20 days')));
        $this->insertOrderInvoice($debtor, $second, 300.00, $secondDate);
        $this->postPaymentOn($debtor, 500.00, $first . ';' . $second, date('Y-m-d', strtotime('-10 days')));

        $posts = $this->postsFor($debtor);
        $payments = array_values(array_filter($posts, static fn($p) => (float)$p['amount'] < 0));
        $this->assertCount(1, $payments, 'the payment is stored once, not once per invoice');
        $this->assertEqualsWithDelta(-500.00, (float)$payments[0]['amount'], 0.005, 'with its own amount');
        $this->assertCount(3, $posts, 'two invoices and one payment');
        $this->assertEqualsWithDelta(0.0, self::sumOf($posts, 'amount'), 0.005, 'the settled group nets to zero');
        foreach ($posts as $p) {
            $this->assertSame('1', trim((string)$p['udlignet']), 'every row of the group is settled');
            $this->assertSame(
                trim((string)$payments[0]['udlign_id']),
                trim((string)$p['udlign_id']),
                'one udlign_id for the whole group'
            );
            $this->assertSame(
                $secondDate,
                substr((string)$p['udlign_date'], 0, 10),
                'every row gets the group\'s latest date'
            );
        }
    }

    /**
     * An "A;B" payment that does not cover the invoices it names settles
     * nothing: the invoices and the payment all stay open, so the unpaid
     * rest stays on the open-post list and matches the customer statement.
     * A single invoice behaves the same way (the half-ore window above).
     * MB-72: with the no-project match, a short payment used to close order
     * invoices as a settled group that did not net to zero.
     */
    public function test_a_short_payment_against_two_invoices_leaves_every_post_open(): void
    {
        $debtor = self::$fx->debtor();
        $first = self::nextInvoiceNo();
        $second = self::nextInvoiceNo();

        $this->insertOrderInvoice($debtor, $first, 200.00, date('Y-m-d', strtotime('-20 days')));
        $this->insertOrderInvoice($debtor, $second, 300.00, date('Y-m-d', strtotime('-5 days')));
        $this->postPaymentOn($debtor, 400.00, $first . ';' . $second, date('Y-m-d', strtotime('-10 days')));

        $posts = $this->postsFor($debtor);
        $this->assertCount(3, $posts, 'two invoices and one payment');
        foreach ($posts as $p) {
            $this->assertNotSame('1', trim((string)$p['udlignet']), 'no post is settled by a short payment');
            $this->assertNull($p['udlign_date'], 'and none gets a settlement date');
        }
        $payments = array_values(array_filter($posts, static fn($p) => (float)$p['amount'] < 0));
        $this->assertCount(1, $payments, 'the payment is stored once');
        $this->assertEqualsWithDelta(-400.00, (float)$payments[0]['amount'], 0.005, 'with its own amount');
        $this->assertEqualsWithDelta(100.00, self::sumOf($posts, 'amount'), 0.005, 'the unpaid 100 stays outstanding');
    }
}
