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
     * Settlement stamps the settlement date from the ORIGINAL post's
     * transaction date, not the payment's - so an invoice paid months later
     * is recorded as settled on the day it was raised.
     */
    public function test_settlement_date_comes_from_the_invoice_not_the_payment(): void
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
                $invoiceDate,
                substr((string)$p['udlign_date'], 0, 10),
                'udlign_date is taken from the invoice, not from the payment (bogfor.php:1100)'
            );
        }
    }
}
