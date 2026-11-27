<?php
// 20261109 CL/ASR Link reminders (Linkrykker): which reminders become link reminders, registration, renteloven § 9 b, the reminder fee and interest on the next invoice (and when they are waived), the reminder mail and its send path with classic fallback, the dunning hold for card-on-file invoices and closing paid reminders.
// 20261109 CL/ASR Review fixes: paid and out-of-year reminders are not escalated, a shared fee/interest item, the earlier classic fee as a bank row (D1), the first mailed_at kept (D2), the PDF treatment (D3), staff texts in the database's encoding (D4), no fee on cash/card sales, the current lines and the order's project, the hold only while the job collects, booked drafts kept, "Frafald" with its token.
//
// 20261111 CDX/ASR Cover link PDF deadlines and preserve classic and collection-warning text.
// 20261116 CDX/ASR Multi-invoice buttons name one invoice and skip below-minimum invoices while retaining their balance and view link.
// 20261118 CDX/ASR Verify that customer mails greet the recipient company rather than a contact person.
// 20261126 ASR Review I5: the reminder letter states the reminder's own fee (capped when it was made), not the fee item's price; a capped 2nd reminder's letter says 100,00.
// 20261126 ASR Review I19: a fee and interest posted as an open item are paid by bank transfer, not with the Betal link: their rows say so, and the bank box names the reminder as the reference.
// 20261126 CL/ASR Review round 1 (I14): the multi-invoice button sentence has one full stop after the amount.
// 20261126 ASR Payment review (GPT-P-6): the card-on-file hold releases an invoice whose open item is one øre off its total, as the charge job does not collect it.
// 20261126 ASR Payment review (GPT-P-3): it also releases an invoice split in several open items, as the charge job does not collect it.
// 20261126 ASR Payment review (GPT-G-2): it releases an open item within half an øre of the total on another project than the order's, as the charge job does not collect it; an exact one stays held.
// 20261126 ASR Review GPT-R-3: the legal check counts the reminders with a fee for the same invoice in a closed chain: no 4th fee, and 10 days after the newest one was sent.
// 20261126 ASR Review GPT-R-4: a classic reminder counts the 10 days from the day it was first mailed or printed (reminder_dispatch), and one from before keeps its date.
// 20261126 ASR Review GPT-R-8: the letter's overdue balance ($forfalden_sum) leaves out an invoice the link reminder leaves out as settled, as the lines do.
// 20261126 ASR Review GPT-R-10: a settled installment leaves out only itself; the invoice stays on the link reminder with its remaining open item, by bank transfer.
// 20261126 ASR Review GPT-R-11: a credit note draft does not leave its invoice out of the link reminder; an invoiced credit note does.
// 20261126 ASR Review GPT-G-7: the fees counted per invoice leave out reminders made while the dispatch day is recorded that were closed without being mailed or printed.
//              Sent ones still block a 4th fee and the 10 days; reminders made before the day was recorded still count from their dates.
// 20261126 ASR Review R3-G7-1: such a reminder whose fee was booked to the customer (fee open item or postings) counts without a recorded day, dated by its date.
//              One left at status 3 without posting, or closed with Afslut unbooked, still does not.
// 20261127 ASR Review GPT-H-1: an older draft sent for the first time after a later chain counts that chain: no 4th fee after three, and the 10 days after one; sending a reminder that already went out again is checked as before.
//              The last check and the record of the day it went out hold the customer's dispatch lock, by mail and on paper, and another session's dispatch for the customer is waited for.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once __DIR__ . '/support/bootstrap.php';
require_once __DIR__ . '/support/reminderFixtures.php';
require_once __DIR__ . '/../../includes/stripeIncludes/stripeManualFee.php';
require_once __DIR__ . '/../../includes/stripeIncludes/stripeDunning.php';
// The link reminder code (spec §3.1/§3.2). A file that is not there leaves its tests red instead of stopping the suite.
foreach (['reminderLegal.php', 'stripeIncludes/stripeReminder.php', 'stripeIncludes/stripeReminderMail.php',
    'formFuncIncludes/reminderMailDelivery.php'] as $reminderInclude) {
    if (is_file(__DIR__ . '/../../includes/' . $reminderInclude)) {
        require_once __DIR__ . '/../../includes/' . $reminderInclude;
    }
}

final class StripeReminderTest extends StripeDatabaseTestCase
{
    private const SETTINGS = ['reminder_format', 'reminder_link_email', 'reminder_fee_next', 'reminder_fee_waive',
        'reminder_close_warning', 'reminder_close_text'];
    private const CLOSE_TEXT = 'Betales beløbet ikke senest {frist}, kan vi lukke for din adgang uden yderligere varsel.';
    /** After "Betal senest" of a reminder on the fixture date (20.01.2026 + 8 days): a carried fee no longer waits for the offer. */
    private const AFTER_DEADLINE = '2026-02-01';

    /** @var array{fee: int, interest: int} */
    private array $items = [];
    private array $files = [];
    private array $classic = [];
    private int $payments = 0;

    protected function setUp(): void
    {
        if (StripeTestEnvironment::unavailableReason() === null && function_exists('stripe_reminder_ensure_table')) {
            // Schema before the test transaction, as the bootstrap does for stripe_payments.
            StripeTestEnvironment::connect();
            stripe_reminder_ensure_table();
        }
        parent::setUp();
        $GLOBALS['stripe_charge_today'] = '2026-01-25';   // the reminded invoices (due 16.01.2026) are overdue
        $this->items = StripeReminderFixtures::forms();
        foreach (self::SETTINGS as $name) {
            StripeTestEnvironment::setting('stripe', $name, null);   // a missing row is the default
        }
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    // ---------------------------------------------------------------- which reminders are link reminders

    public function testEligibilityFollowsTheNewestInvoiceAndTheSettings(): void
    {
        $enrolled = ['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'stripe_date' => '2026-01-01'];
        foreach ([
            'auto, E-mail med link' => [[], ['udskriv_til' => 'Link'], [], ''],
            'auto, paid automatically by card' => [$enrolled, ['udskriv_til' => 'Stripe'], [], ''],
            'auto, classic e-mail' => [[], ['udskriv_til' => 'email'], [], 'channel'],
            'auto, classic e-mail with link_email on' => [[], ['udskriv_til' => 'email'], ['reminder_link_email' => 'on'], ''],
            'auto, printed invoice with link_email on' => [[], ['udskriv_til' => 'PDF'], ['reminder_link_email' => 'on'], 'channel'],
            'classic format' => [[], ['udskriv_til' => 'Link'], ['reminder_format' => 'classic'], 'off'],
            'customer without card payment (bank variant)' => [['stripe_fravalg' => 'on'], ['udskriv_til' => 'Link'], [], ''],
        ] as $case => [$debtorFields, $invoiceFields, $settings, $expected]) {
            foreach ($settings as $name => $value) {
                StripeTestEnvironment::setting('stripe', $name, $value);
            }
            $r = StripeReminderFixtures::linkReminder($debtorFields, $invoiceFields);
            $this->assertSame($expected, stripe_reminder_eligible($r['reminder']), $case);
            foreach ($settings as $name => $value) {
                StripeTestEnvironment::setting('stripe', $name, null);
            }
        }
        // The newest reminded invoice decides, whatever the order of the lines.
        foreach ([['Link', 'email', 'channel'], ['email', 'Link', '']] as [$older, $newer, $expected]) {
            $debtor = StripeFixtures::debtor();
            $new = StripeReminderFixtures::invoiceOn($debtor, '2026-01-12', ['udskriv_til' => $newer]);
            $old = StripeFixtures::invoice($debtor, ['udskriv_til' => $older]);
            $this->assertSame($expected, stripe_reminder_eligible(StripeReminderFixtures::reminder($debtor, [$new, $old])), "$older then $newer");
        }
    }

    public function testForeignNonDanishPbsUnmappedMaillessAndLinklessRemindersStayClassic(): void
    {
        $unmapped = static function (): int {
            $debtor = StripeFixtures::debtor();
            $invoice = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Link']);
            $d = StripeFixtures::row('adresser', $debtor);
            // An open item that is not an invoice of this customer (e.g. an opening balance).
            $other = StripeFixtures::insert('openpost', ['konto_id' => $debtor, 'konto_nr' => $d['kontonr'], 'faktnr' => 'PRIMO',
                'amount' => '50.000', 'udlignet' => '0', 'transdate' => '2026-01-02', 'forfaldsdate' => '2026-01-02',
                'valuta' => 'DKK', 'valutakurs' => '100.000', 'projekt' => '']);
            return StripeReminderFixtures::reminder($debtor, [$invoice], ['openposts' => [$other]]);
        };
        foreach ([
            'currency' => fn() => StripeReminderFixtures::linkReminder([], ['valuta' => 'EUR', 'valutakurs' => '746.000'],
                ['valuta' => 'EUR', 'valutakurs' => 746, 'fee' => 13])['reminder'],
            'language' => fn() => StripeReminderFixtures::linkReminder([], [], ['sprog' => 'English'])['reminder'],
            'pbs' => fn() => StripeReminderFixtures::linkReminder(['pbs_nr' => '12345'])['reminder'],
            'unmapped' => $unmapped,
            'no_email' => fn() => StripeReminderFixtures::linkReminder(['email' => ''], [], ['email' => ''])['reminder'],
        ] as $reason => $make) {
            $this->assertSame($reason, stripe_reminder_eligible($make()), $reason);
        }
        // Card payment of invoices switched off: no payment page to link to.
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'off');
        $this->assertSame('no_link', stripe_reminder_eligible(StripeReminderFixtures::linkReminder()['reminder']));
    }

    public function testSecondReminderIsALinkReminderOnlyWhenTheFirstOneWas(): void
    {
        $r = StripeReminderFixtures::linkReminder();
        $second = StripeReminderFixtures::nextLevel($r, 2, '2026-02-01');
        $this->assertSame('prev_classic', stripe_reminder_eligible($second));
        $this->assertFalse(stripe_reminder_register($second));
        $this->assertNull(StripeReminderFixtures::linkRowOf($second));

        StripeReminderFixtures::linkRow($r['reminder']);
        $this->assertSame('', stripe_reminder_eligible($second));
        $this->assertTrue(stripe_reminder_register($second));
        $row = StripeReminderFixtures::linkRowOf($second);
        $this->assertSame(2, (int)$row['level']);
        $this->assertSame('draft', $row['status']);
    }

    // ---------------------------------------------------------------- registration and the row's life

    public function testRegisterMakesADraftWithMailOnAndTheNewestInvoiceRecipient(): void
    {
        $debtor = StripeFixtures::debtor(['email' => '']);
        $old = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Link', 'email' => 'gammel@stripe-tests.invalid']);
        $new = StripeReminderFixtures::invoiceOn($debtor, '2026-01-12', ['email' => 'bogholder@stripe-tests.invalid']);
        $rykker = StripeReminderFixtures::reminder($debtor, [$old, $new], ['email' => '', 'mail_fakt' => '', 'interest' => 12.5]);

        $this->assertTrue(stripe_reminder_register($rykker));
        $order = StripeFixtures::row('ordrer', $rykker);
        $this->assertSame('on', $order['mail_fakt']);
        $this->assertSame('bogholder@stripe-tests.invalid', $order['email']);
        $row = StripeReminderFixtures::linkRowOf($rykker);
        $this->assertNotNull($row);
        $this->assertSame('draft', $row['status']);
        $this->assertSame('next', $row['fee_mode']);
        $this->assertSame(1, (int)$row['level']);
        $this->assertSame($debtor, (int)$row['konto_id']);
        $this->assertSame((int)$order['ordrenr'], (int)$row['ordrenr']);
        $this->assertSame(10000, (int)$row['fee_ore']);
        $this->assertSame(1250, (int)$row['interest_ore']);
        $this->assertNull($row['mailed_at']);
        $this->assertTrue(stripe_reminder_carried($rykker));

        // An address already on the reminder is kept.
        $own = StripeReminderFixtures::linkReminder([], [], ['email' => 'rykker@stripe-tests.invalid']);
        $this->assertTrue(stripe_reminder_register($own['reminder']));
        $this->assertSame('rykker@stripe-tests.invalid', StripeFixtures::row('ordrer', $own['reminder'])['email']);
    }

    public function testRegisterFreezesTheFeeModeAndLeavesClassicRemindersAlone(): void
    {
        StripeTestEnvironment::setting('stripe', 'reminder_fee_next', 'off');
        $r = StripeReminderFixtures::linkReminder();
        $this->assertTrue(stripe_reminder_register($r['reminder']));
        $this->assertSame('classic', StripeReminderFixtures::linkRowOf($r['reminder'])['fee_mode']);
        $this->assertFalse(stripe_reminder_carried($r['reminder']));
        StripeTestEnvironment::setting('stripe', 'reminder_fee_next', 'on');
        $this->assertSame('classic', StripeReminderFixtures::linkRowOf($r['reminder'])['fee_mode'], 'frozen at registration');

        $classic = StripeReminderFixtures::linkReminder([], ['udskriv_til' => 'email']);
        $this->assertFalse(stripe_reminder_register($classic['reminder']));
        $this->assertNull(StripeReminderFixtures::linkRowOf($classic['reminder']));
        $this->assertSame('', (string)StripeFixtures::row('ordrer', $classic['reminder'])['mail_fakt']);
        $this->assertFalse(stripe_reminder_carried($classic['reminder']));
    }

    public function testDraftSentChargedAndWaivedRowsAndTheReminderOfAnInvoice(): void
    {
        $r = StripeReminderFixtures::linkReminder([], [], ['interest' => 12.5]);
        $this->assertNull(stripe_reminder_for_invoice($r['invoice']));
        $this->assertTrue(stripe_reminder_register($r['reminder']));
        $this->assertNull(stripe_reminder_for_invoice($r['invoice']), 'a draft has not been sent');

        // Unticking "Send pr. mail" on a draft makes it a classic reminder again.
        stripe_reminder_unregister($r['reminder']);
        $this->assertNull(StripeReminderFixtures::linkRowOf($r['reminder']));
        $this->assertTrue(stripe_reminder_register($r['reminder']));

        // Sending reads the fee again: the bookkeeper may have changed the line in rykker.php.
        StripeTestEnvironment::query('UPDATE ordrelinjer SET pris = 80 WHERE ordre_id = $1 AND vare_id = $2', [$r['reminder'], $this->items['fee']]);
        stripe_reminder_mark_sent($r['reminder'], 'link', 'jens@stripe-tests.invalid');
        $row = StripeReminderFixtures::linkRowOf($r['reminder']);
        $this->assertSame('sent', $row['status']);
        $this->assertSame('link', $row['channel']);
        $this->assertSame('jens@stripe-tests.invalid', $row['mailed_to']);
        $this->assertNotEmpty($row['mailed_at']);
        $this->assertSame(8000, (int)$row['fee_ore']);
        $this->assertSame(1250, (int)$row['interest_ore']);
        stripe_reminder_unregister($r['reminder']);
        $this->assertNotNull(StripeReminderFixtures::linkRowOf($r['reminder']), 'a sent reminder is never unregistered');

        $found = stripe_reminder_for_invoice($r['invoice']);
        $this->assertNotNull($found);
        $this->assertSame($r['reminder'], (int)$found['rykker_id']);
        $this->assertSame(1, (int)$found['level']);
        $this->assertSame(8000, (int)$found['fee_ore']);
        $this->assertSame(1250, (int)$found['interest_ore']);
        $this->assertSame('next', $found['fee_mode']);
        $this->assertSame('sent', $found['status']);
        $this->assertSame(substr((string)$row['mailed_at'], 0, 10), substr((string)$found['mailed_at'], 0, 10));
        $this->assertNull(stripe_reminder_for_invoice(StripeFixtures::invoice()), 'an invoice without a reminder');

        // The latest sent level of the chain is the invoice's reminder.
        $second = StripeReminderFixtures::nextLevel($r, 2, '2026-02-05');
        StripeReminderFixtures::linkRow($second, ['mailed_at' => '2026-02-05 09:00:00']);
        $found = stripe_reminder_for_invoice($r['invoice']);
        $this->assertSame($second, (int)$found['rykker_id']);
        $this->assertSame(2, (int)$found['level']);

        // "Frafald rykkergebyr": sent and not yet charged.
        $this->assertTrue(stripe_reminder_waive($second, 'manual'));
        $this->assertSame('waived', StripeReminderFixtures::linkRowOf($second)['status']);
        $this->assertSame('manual', StripeReminderFixtures::linkRowOf($second)['waived_reason']);
        StripeTestEnvironment::query("UPDATE stripe_reminders SET status = 'charged' WHERE rykker_id = $1", [$r['reminder']]);
        $this->assertFalse(stripe_reminder_waive($r['reminder'], 'manual'), 'a charged fee is on an invoice already');
        stripe_reminder_mark_sent($r['reminder'], 'link', 'jens@stripe-tests.invalid');
        $this->assertSame('charged', StripeReminderFixtures::linkRowOf($r['reminder'])['status'], 'sending again does not reopen it');
    }

    // ---------------------------------------------------------------- renteloven § 9 b

    public function testFeeCapIsOneHundredKronerInEveryCurrency(): void
    {
        $this->assertSame(100.0, reminder_fee_cap_kr());
        $this->assertSame(['fee' => 100.0, 'capped' => true], reminder_legal_cap_fee(150.0, 'DKK', 100.0));
        $this->assertSame(['fee' => 100.0, 'capped' => false], reminder_legal_cap_fee(100.0, '', 100.0));
        $this->assertSame(['fee' => 65.0, 'capped' => false], reminder_legal_cap_fee(65.0, 'DKK', 100.0));
        $eur = reminder_legal_cap_fee(20.0, 'EUR', 746.0);
        $this->assertTrue($eur['capped']);
        $this->assertEqualsWithDelta(13.40, $eur['fee'], 0.005);
        $this->assertLessThanOrEqual(100.0, round($eur['fee'] * 746 / 100, 2));
        $this->assertSame(['fee' => 10.0, 'capped' => false], reminder_legal_cap_fee(10.0, 'EUR', 746.0));
        $this->assertSame(['fee' => 500.0, 'capped' => false], reminder_legal_cap_fee(500.0, 'EUR', 0.0), 'no rate: L1 is skipped');
    }

    public function testLegalCheckRefusesAFeeAboveTheCapAndFeesAfterTheThirdReminder(): void
    {
        $high = StripeReminderFixtures::linkReminder([], [], ['fee' => 150]);
        $message = reminder_legal_check($high['reminder'], '2026-01-20');
        $this->assertMatchesRegularExpression('/100,00 kr\./', $message);
        $this->assertStringContainsString('9 b', $message);
        // Interest is not a fee.
        $this->assertSame('', reminder_legal_check(StripeReminderFixtures::linkReminder([], [], ['fee' => 100, 'interest' => 50])['reminder'], '2026-01-20'));
        // A reminder in another currency is measured in kroner with its rate.
        $eur = ['valuta' => 'EUR', 'valutakurs' => '746.000'];
        $this->assertNotSame('', reminder_legal_check(StripeReminderFixtures::linkReminder([], $eur, ['valuta' => 'EUR', 'valutakurs' => 746, 'fee' => 14])['reminder'], '2026-01-20'));
        $this->assertSame('', reminder_legal_check(StripeReminderFixtures::linkReminder([], $eur, ['valuta' => 'EUR', 'valutakurs' => 746, 'fee' => 13])['reminder'], '2026-01-20'));
        // Fees on at most 3 reminders.
        $r = StripeReminderFixtures::linkReminder();
        $fourth = StripeReminderFixtures::nextLevel($r, 4, '2026-03-30', ['fee' => 50]);
        $this->assertNotSame('', reminder_legal_check($fourth, '2026-03-30'));
        $this->assertSame('', reminder_legal_check(StripeReminderFixtures::nextLevel($r, 4, '2026-03-30', ['fee' => 0]), '2026-03-30'));
    }

    public function testLegalCheckNeedsTenDaysAfterThePreviousReminderOnlyWhenTheNewOneHasAFee(): void
    {
        $r = StripeReminderFixtures::linkReminder();
        $this->assertSame('', reminder_previous_sent_date($r['reminder']), 'a 1st reminder has no previous one');
        StripeReminderFixtures::linkRow($r['reminder'], ['mailed_at' => '2026-01-21 16:45:00']);   // mailed the day after its date
        $second = StripeReminderFixtures::nextLevel($r, 2, '2026-01-25');
        $this->assertSame('2026-01-21', reminder_previous_sent_date($second));
        $this->assertSame('2. rykker kan tidligst sendes den 31.01.2026 (mindst 10 dage efter forrige rykker, renteloven § 9 b).',
            reminder_legal_check($second, '2026-01-30'));
        $this->assertSame('', reminder_legal_check($second, '2026-01-31'));
        // Without a fee a reminder is never held back; interest alone is not a fee.
        $noFee = StripeReminderFixtures::linkReminder();
        StripeReminderFixtures::linkRow($noFee['reminder'], ['mailed_at' => '2026-01-21 16:45:00']);
        $this->assertSame('', reminder_legal_check(StripeReminderFixtures::nextLevel($noFee, 2, '2026-01-22', ['fee' => 0, 'interest' => 5]), '2026-01-22'));

        // A 3rd reminder counts from the 2nd.
        StripeReminderFixtures::linkRow($second, ['mailed_at' => '2026-02-01 08:00:00']);
        $third = StripeReminderFixtures::nextLevel($r, 3, '2026-02-08');
        $this->assertSame('3. rykker kan tidligst sendes den 11.02.2026 (mindst 10 dage efter forrige rykker, renteloven § 9 b).',
            reminder_legal_check($third, '2026-02-10'));
        // A 2nd reminder of the same chain made after the 3rd is not the 3rd's previous one.
        StripeReminderFixtures::nextLevel($r, 2, '2026-02-09');
        $this->assertSame('2026-02-01', reminder_previous_sent_date($third));

        // Not mailed through Saldi: the previous reminder's date, then its order date.
        $classic = StripeReminderFixtures::linkReminder();
        $classicSecond = StripeReminderFixtures::nextLevel($classic, 2, '2026-01-25');
        $this->assertSame(StripeReminderFixtures::REMINDER_DATE, reminder_previous_sent_date($classicSecond));
        $this->assertSame('2. rykker kan tidligst sendes den 30.01.2026 (mindst 10 dage efter forrige rykker, renteloven § 9 b).',
            reminder_legal_check($classicSecond, '2026-01-29'));
        StripeTestEnvironment::query("UPDATE ordrer SET fakturadate = NULL, ordredate = '2026-01-18' WHERE id = $1", [$classic['reminder']]);
        $this->assertSame('2026-01-18', reminder_previous_sent_date($classicSecond));
        $this->assertSame('', reminder_legal_check($classicSecond, '2026-01-28'));
    }

    public function testLegalCheckCountsTheFeesForTheSameInvoiceInAClosedChain(): void
    {
        $debtor = StripeFixtures::debtor();
        $invoice = StripeFixtures::invoice($debtor, ['udskriv_til' => 'email']);
        $nr = StripeFixtures::row('ordrer', $invoice)['fakturanr'];
        $r = ['debtor' => $debtor, 'invoice' => $invoice, 'reminder' => StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-01-20'])];
        $chain = [$r['reminder'], StripeReminderFixtures::nextLevel($r, 2, '2026-02-01'), StripeReminderFixtures::nextLevel($r, 3, '2026-02-15')];
        // Afslut closes the chain; the invoice is still unpaid, so a new chain can be made for it.
        StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on', status = 4 WHERE id = ANY($1::int[])", ['{' . implode(',', $chain) . '}']);
        $again = StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-03-10']);
        $this->assertSame("Der er allerede opkrævet rykkergebyr for 3 rykkere for faktura $nr (renteloven § 9 b).", reminder_legal_check($again, '2026-03-10'));
        $this->assertSame('', reminder_legal_check(StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-03-10', 'fee' => 0]), '2026-03-10'),
            'a reminder without a fee may still be sent');
        // Another invoice of the same customer has its own count.
        $other = StripeFixtures::invoice($debtor, ['udskriv_til' => 'email']);
        $this->assertSame('', reminder_legal_check(StripeReminderFixtures::reminder($debtor, [$other], ['date' => '2026-03-10']), '2026-03-10'));

        // A chain closed after one reminder with a fee: a new reminder with a fee for the invoice waits 10 days after it was sent.
        $debtor2 = StripeFixtures::debtor();
        $invoice2 = StripeFixtures::invoice($debtor2, ['udskriv_til' => 'email']);
        $nr2 = StripeFixtures::row('ordrer', $invoice2)['fakturanr'];
        $first = StripeReminderFixtures::reminder($debtor2, [$invoice2], ['date' => '2026-01-20']);
        StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on' WHERE id = $1", [$first]);
        $new = StripeReminderFixtures::reminder($debtor2, [$invoice2], ['date' => '2026-01-25']);
        $this->assertSame("Faktura $nr2 fik en rykker med gebyr den 20.01.2026. En ny rykker med gebyr kan tidligst sendes den 30.01.2026 (renteloven § 9 b).",
            reminder_legal_check($new, '2026-01-29'));
        $this->assertSame('', reminder_legal_check($new, '2026-01-30'));
    }

    public function testAClassicReminderCountsTheTenDaysFromTheDayItWentOut(): void
    {
        require_once __DIR__ . '/../../includes/opdat_func/reminderDispatch.php';
        reminder_dispatch_schema_ensure('postgresql');
        reminder_dispatch_schema_ensure('postgresql');   // once
        $debtor = StripeFixtures::debtor();
        $invoice = StripeFixtures::invoice($debtor, ['udskriv_til' => 'email']);
        $r = ['debtor' => $debtor, 'invoice' => $invoice, 'reminder' => StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-01-01'])];
        // Made on 1 January, mailed on 20 January.
        $GLOBALS['stripe_charge_today'] = '2026-01-20';
        $result = formSendReminderEntry($r['reminder'], $this->pdf($r['reminder']), 'jens@stripe-tests.invalid', 'Dansk', 6, $this->classicSender());
        $this->assertTrue($result['sent']);
        $this->assertSame('classic', $result['channel']);
        $this->assertSame('2026-01-20', reminder_sent_date($r['reminder']));
        $second = StripeReminderFixtures::nextLevel($r, 2, '2026-01-21');
        $wait = '2. rykker kan tidligst sendes den 30.01.2026 (mindst 10 dage efter forrige rykker, renteloven § 9 b).';
        $this->assertSame($wait, reminder_legal_check($second, '2026-01-21'));
        $this->assertSame($wait, reminder_escalation_check($r['reminder'], '2026-01-21'));
        $this->assertSame('', reminder_legal_check($second, '2026-01-30'));
        // Mailed again later: the first day stays.
        $GLOBALS['stripe_charge_today'] = '2026-01-28';
        $this->assertTrue(formSendReminderEntry($r['reminder'], $this->pdf($r['reminder']), 'jens@stripe-tests.invalid', 'Dansk', 6, $this->classicSender())['sent']);
        $this->assertSame('2026-01-20', reminder_sent_date($r['reminder']));
        // A mail that did not go out records nothing.
        $failed = StripeReminderFixtures::linkReminder([], ['udskriv_til' => 'email'], ['date' => '2026-01-01']);
        formSendReminderEntry($failed['reminder'], $this->pdf($failed['reminder']), 'jens@stripe-tests.invalid', 'Dansk', 6, static fn(...$args): string => 'Mail error');
        $this->assertSame('2026-01-01', reminder_sent_date($failed['reminder']));

        // Printed on paper on 15 January.
        $printed = StripeReminderFixtures::linkReminder([], ['udskriv_til' => 'email'], ['date' => '2026-01-01']);
        $GLOBALS['stripe_charge_today'] = '2026-01-15';
        formReminderPrinted($printed['reminder']);
        $this->assertSame('2026-01-15', reminder_sent_date($printed['reminder']));
        $this->assertSame(['sent_date' => '2026-01-15', 'channel' => 'print'],
            pg_fetch_assoc(StripeTestEnvironment::query('SELECT sent_date, channel FROM reminder_dispatch WHERE order_id = $1', [$printed['reminder']])));

        // A reminder made before Saldi recorded the day keeps its date, as before.
        $old = StripeReminderFixtures::linkReminder([], ['udskriv_til' => 'email'], ['date' => '2026-01-05']);
        $this->assertSame('2026-01-05', reminder_sent_date($old['reminder']));
    }

    public function testAReminderMadeWhileDispatchIsRecordedCountsTowardsTheFeesOnlyWhenItWentOut(): void
    {
        require_once __DIR__ . '/../../includes/opdat_func/reminderDispatch.php';
        reminder_dispatch_schema_ensure('postgresql');
        $limit = static fn(string $nr): string => "Der er allerede opkrævet rykkergebyr for 3 rykkere for faktura $nr (renteloven § 9 b).";
        $chain = static function (int $debtor, int $invoice, array $dates): array {
            $r = ['debtor' => $debtor, 'invoice' => $invoice, 'reminder' => StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => $dates[0]])];
            $ids = [$r['reminder']];
            foreach (array_slice($dates, 1) as $i => $date) $ids[] = StripeReminderFixtures::nextLevel($r, $i + 2, $date);
            return $ids;
        };
        $close = static function (array $ids): void {   // Afslut: the invoice is still unpaid, so a new chain can be made for it
            StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on', status = 4 WHERE id = ANY($1::int[])", ['{' . implode(',', $ids) . '}']);
        };

        // Made before Saldi recorded the day reminders go out: they may have gone out then, so they count from their dates.
        $oldDebtor = StripeFixtures::debtor();
        $oldInvoice = StripeFixtures::invoice($oldDebtor, ['udskriv_til' => 'email']);
        $old = $chain($oldDebtor, $oldInvoice, ['2026-01-05', '2026-01-20', '2026-02-05']);
        $oldDebtor2 = StripeFixtures::debtor();
        $oldInvoice2 = StripeFixtures::invoice($oldDebtor2, ['udskriv_til' => 'email']);
        $old2 = $chain($oldDebtor2, $oldInvoice2, ['2026-01-20']);

        reminder_dispatch_since_ensure('postgresql');
        reminder_dispatch_since_ensure('postgresql');   // recorded once
        $first = reminder_dispatch_first_id();
        $this->assertSame([['id' => '1', 'first_order_id' => (string)$first]],
            pg_fetch_all(StripeTestEnvironment::query('SELECT id, first_order_id FROM reminder_dispatch_since')));
        $this->assertGreaterThan(max(array_merge($old, $old2)), $first, 'the reminders made so far are older');

        $close($old);
        $close($old2);
        $this->assertSame($limit(StripeFixtures::row('ordrer', $oldInvoice)['fakturanr']),
            reminder_legal_check(StripeReminderFixtures::reminder($oldDebtor, [$oldInvoice], ['date' => '2026-03-10']), '2026-03-10'),
            'three older reminders without a recorded day still count');
        $oldNr2 = StripeFixtures::row('ordrer', $oldInvoice2)['fakturanr'];
        $this->assertSame("Faktura $oldNr2 fik en rykker med gebyr den 20.01.2026. En ny rykker med gebyr kan tidligst sendes den 30.01.2026 (renteloven § 9 b).",
            reminder_legal_check(StripeReminderFixtures::reminder($oldDebtor2, [$oldInvoice2], ['date' => '2026-01-25']), '2026-01-29'));

        // Made afterwards and closed without being mailed or printed: no fee was claimed, so they do not hold back a real reminder's fee.
        $draftDebtor = StripeFixtures::debtor();
        $draftInvoice = StripeFixtures::invoice($draftDebtor, ['udskriv_til' => 'email']);
        $drafts = $chain($draftDebtor, $draftInvoice, ['2026-01-05', '2026-01-20', '2026-02-05']);
        $close($drafts);
        $close($chain($draftDebtor, $draftInvoice, ['2026-03-08']));   // abandoned two days before
        $real = StripeReminderFixtures::reminder($draftDebtor, [$draftInvoice], ['date' => '2026-03-10']);
        $this->assertSame(['count' => 0, 'invoice' => '', 'last_sent' => '', 'last_invoice' => ''], reminder_claim_fee_history($real));
        $this->assertSame('', reminder_claim_fee_refusal($real, '2026-03-10'), 'creation puts the fee on');
        $this->assertSame('', reminder_legal_check($real, '2026-03-10'), 'and it may be sent with it');
        $this->assertSame('2026-01-05', reminder_sent_date($drafts[0]), 'within a chain the 10 days still count from the date of a level without a recorded day');

        // Made afterwards and sent (printed, mailed, a link mail): they count as before. No 4th fee for the invoice ...
        $sentDebtor = StripeFixtures::debtor();
        $sentInvoice = StripeFixtures::invoice($sentDebtor, ['udskriv_til' => 'email']);
        $sent = $chain($sentDebtor, $sentInvoice, ['2026-01-05', '2026-01-20', '2026-02-05']);
        reminder_dispatch_record($sent[0], 'print', '2026-01-06');
        reminder_dispatch_record($sent[1], 'classic', '2026-01-21');
        StripeReminderFixtures::linkRow($sent[2], ['mailed_at' => '2026-02-06 09:00:00']);
        $close($sent);
        $sentNr = StripeFixtures::row('ordrer', $sentInvoice)['fakturanr'];
        $fourth = StripeReminderFixtures::reminder($sentDebtor, [$sentInvoice], ['date' => '2026-03-10']);
        $this->assertSame(['count' => 3, 'invoice' => $sentNr, 'last_sent' => '2026-02-06', 'last_invoice' => $sentNr], reminder_claim_fee_history($fourth));
        $this->assertSame($limit($sentNr), reminder_legal_check($fourth, '2026-03-10'));

        // ... and a new fee waits 10 days after the day one went out, not after an abandoned draft made later.
        $spacedDebtor = StripeFixtures::debtor();
        $spacedInvoice = StripeFixtures::invoice($spacedDebtor, ['udskriv_til' => 'email']);
        $went = $chain($spacedDebtor, $spacedInvoice, ['2026-01-10']);
        reminder_dispatch_record($went[0], 'classic', '2026-01-20');
        $close($went);
        $close($chain($spacedDebtor, $spacedInvoice, ['2026-01-28']));
        $spacedNr = StripeFixtures::row('ordrer', $spacedInvoice)['fakturanr'];
        $new = StripeReminderFixtures::reminder($spacedDebtor, [$spacedInvoice], ['date' => '2026-01-25']);
        $this->assertSame("Faktura $spacedNr fik en rykker med gebyr den 20.01.2026. En ny rykker med gebyr kan tidligst sendes den 30.01.2026 (renteloven § 9 b).",
            reminder_legal_check($new, '2026-01-29'));
        $this->assertSame('', reminder_legal_check($new, '2026-01-30'));
    }

    public function testAReminderWhoseFeeWasBookedCountsTowardsTheFeesWithoutARecordedDay(): void
    {
        require_once __DIR__ . '/../../includes/opdat_func/reminderDispatch.php';
        reminder_dispatch_schema_ensure('postgresql');
        reminder_dispatch_since_ensure('postgresql');   // every reminder of this test is made while the dispatch day is recorded
        $limit = static fn(string $nr): string => "Der er allerede opkrævet rykkergebyr for 3 rykkere for faktura $nr (renteloven § 9 b).";
        $close = static function (array $ids): void {   // Afslut: betalt only; the invoice is still unpaid, so a new chain can be made for it
            StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on' WHERE id = ANY($1::int[])", ['{' . implode(',', $ids) . '}']);
        };
        // What bogfor_nu() leaves: status 4 and the fee open item (refnr = the reminder) on the customer's account.
        $bookFeeOpenItem = static function (int $rykker): int {
            $o = StripeFixtures::row('ordrer', $rykker);
            StripeTestEnvironment::query('UPDATE ordrer SET status = 4 WHERE id = $1', [$rykker]);
            return StripeFixtures::insert('openpost', ['konto_id' => (int)$o['konto_id'], 'konto_nr' => $o['kontonr'], 'faktnr' => $o['fakturanr'],
                'refnr' => $rykker, 'amount' => '100.000', 'beskrivelse' => 'Gebyr mm. fra tidligere rykker', 'udlignet' => '0',
                'transdate' => $o['fakturadate'], 'valuta' => 'DKK', 'valutakurs' => '100.000', 'projekt' => '']);
        };

        // R1 is booked by "Ryk alle" when it escalates it, but never mailed or printed itself. R2 lists its fee and is mailed, R3 is mailed.
        $debtor = StripeFixtures::debtor();
        $invoice = StripeFixtures::invoice($debtor, ['udskriv_til' => 'email']);
        $nr = StripeFixtures::row('ordrer', $invoice)['fakturanr'];
        $r = ['debtor' => $debtor, 'invoice' => $invoice, 'reminder' => StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-01-05'])];
        $feeOpenItem = $bookFeeOpenItem($r['reminder']);
        $r2 = StripeReminderFixtures::nextLevel($r, 2, '2026-01-20', ['openposts' => [$feeOpenItem]]);
        $r3 = StripeReminderFixtures::nextLevel($r, 3, '2026-02-05');
        reminder_dispatch_record($r2, 'classic', '2026-01-20');
        reminder_dispatch_record($r3, 'classic', '2026-02-05');
        $close([$r['reminder'], $r2, $r3]);
        $this->assertSame('', reminder_dispatch_date($r['reminder']), 'precondition: R1 itself did not go out');
        $new = StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-03-10']);
        $this->assertSame(['count' => 3, 'invoice' => $nr, 'last_sent' => '2026-02-05', 'last_invoice' => $nr], reminder_claim_fee_history($new),
            'the booked fee was claimed from the customer, on R2');
        $this->assertSame($limit($nr), reminder_claim_fee_refusal($new, '2026-03-10'), 'creation leaves the fee off');
        $this->assertSame($limit($nr), reminder_legal_check($new, '2026-03-10'), 'and it may not be sent with one');

        // A booking that left only its postings (transaktioner.ordre_id) counts too, dated as reminder_sent_date(): its date, 25.02.
        $debtor2 = StripeFixtures::debtor();
        $invoice2 = StripeFixtures::invoice($debtor2, ['udskriv_til' => 'email']);
        $nr2 = StripeFixtures::row('ordrer', $invoice2)['fakturanr'];
        $posted = StripeReminderFixtures::reminder($debtor2, [$invoice2], ['date' => '2026-02-25', 'status' => 4]);
        StripeFixtures::insert('transaktioner', ['bilag' => 0, 'transdate' => '2026-02-25', 'beskrivelse' => 'Gebyr mm. fra tidligere rykker',
            'kontonr' => 56000, 'faktura' => StripeFixtures::row('ordrer', $posted)['fakturanr'], 'debet' => '100.000', 'kredit' => '0.000',
            'kladde_id' => 0, 'afd' => 0, 'projekt' => '0', 'ordre_id' => $posted]);
        // A level left at status 3 without anything posted (GPT-F-1) and a draft closed with Afslut were not booked: they do not count.
        $close([$posted, StripeReminderFixtures::reminder($debtor2, [$invoice2], ['date' => '2026-02-27', 'status' => 3]),
            StripeReminderFixtures::reminder($debtor2, [$invoice2], ['date' => '2026-02-28'])]);
        $spaced = StripeReminderFixtures::reminder($debtor2, [$invoice2], ['date' => '2026-03-01']);
        $this->assertSame(['count' => 1, 'invoice' => $nr2, 'last_sent' => '2026-02-25', 'last_invoice' => $nr2], reminder_claim_fee_history($spaced));
        $this->assertSame("Faktura $nr2 fik en rykker med gebyr den 25.02.2026. En ny rykker med gebyr kan tidligst sendes den 07.03.2026 (renteloven § 9 b).",
            reminder_legal_check($spaced, '2026-03-06'));
        $this->assertSame('', reminder_legal_check($spaced, '2026-03-07'));
    }

    public function testAnOlderDraftSentForTheFirstTimeCountsTheRemindersMadeAfterIt(): void
    {
        require_once __DIR__ . '/../../includes/opdat_func/reminderDispatch.php';
        reminder_dispatch_schema_ensure('postgresql');
        reminder_dispatch_since_ensure('postgresql');   // every reminder of this test is made while the dispatch day is recorded
        $limit = static fn(string $nr): string => "Der er allerede opkrævet rykkergebyr for 3 rykkere for faktura $nr (renteloven § 9 b).";
        $close = static function (array $ids): void {   // Afslut: betalt only; the invoice is still unpaid, so a new chain can be made for it
            StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on' WHERE id = ANY($1::int[])", ['{' . implode(',', $ids) . '}']);
        };
        $sentOn = static function (array $ids): void {   // each mailed on its date
            foreach ($ids as $id) reminder_dispatch_record($id, 'classic', (string)StripeFixtures::row('ordrer', $id)['fakturadate']);
        };

        // An R1 with a fee made on 05.01 and closed with Afslut without being mailed, printed or booked; its editor is still open.
        // A later chain for the same invoice then has three reminders with a fee, each mailed, and is closed.
        $debtor = StripeFixtures::debtor();
        $invoice = StripeFixtures::invoice($debtor, ['udskriv_til' => 'email']);
        $nr = StripeFixtures::row('ordrer', $invoice)['fakturanr'];
        $draft = StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-01-05']);
        $noFee = StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-01-06', 'fee' => 0]);
        $close([$draft, $noFee]);
        $r = ['debtor' => $debtor, 'invoice' => $invoice, 'reminder' => StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-01-20'])];
        $chain = [$r['reminder'], StripeReminderFixtures::nextLevel($r, 2, '2026-02-01'), StripeReminderFixtures::nextLevel($r, 3, '2026-02-15')];
        $sentOn($chain);
        $close($chain);
        $this->assertSame(0, reminder_claim_fee_history($draft)['count'], 'when the draft was made, nothing for the invoice came before it');
        $this->assertSame([true, false], [reminder_claim_first_dispatch($draft), reminder_claim_first_dispatch($chain[0])]);
        $this->assertSame(['count' => 3, 'invoice' => $nr, 'last_sent' => '2026-02-15', 'last_invoice' => $nr], reminder_claim_fee_history($draft, true));
        $this->assertSame($limit($nr), reminder_legal_check($draft, '2026-03-10'), 'its first dispatch would be a 4th fee');
        $draftNr = StripeFixtures::row('ordrer', $draft)['fakturanr'];
        $GLOBALS['stripe_charge_today'] = '2026-03-10';
        $mailed = formSendReminderEntry($draft, $this->pdf($draft), 'jens@stripe-tests.invalid', 'Dansk', 6, $this->classicSender());
        $this->assertSame([false, "Rykker $draftNr er ikke sendt: " . $limit($nr)], [$mailed['sent'], $mailed['message']], 'mailed: refused');
        $this->assertSame([], $this->classic, 'no mail went out');
        $this->assertSame("Rykker $draftNr er ikke sendt: " . $limit($nr), formReminderPrintChecked($draft), 'printed: refused');
        $this->assertSame('', reminder_dispatch_date($draft), 'nothing recorded as sent');
        // As before: an older draft without a fee may go, and a reminder made now is made without a fee.
        $this->assertSame('', reminder_legal_check($noFee, '2026-03-10'));
        $this->assertSame($limit($nr), reminder_claim_fee_refusal(StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-03-10']), '2026-03-10'));

        // One later reminder with a fee mailed on 20.02: the older draft's first dispatch waits 10 days after it, and then counts as sent.
        $debtor2 = StripeFixtures::debtor();
        $invoice2 = StripeFixtures::invoice($debtor2, ['udskriv_til' => 'email']);
        $nr2 = StripeFixtures::row('ordrer', $invoice2)['fakturanr'];
        $older = StripeReminderFixtures::reminder($debtor2, [$invoice2], ['date' => '2026-01-05']);
        $close([$older]);
        $later = StripeReminderFixtures::reminder($debtor2, [$invoice2], ['date' => '2026-02-18']);
        reminder_dispatch_record($later, 'classic', '2026-02-20');
        $this->assertSame(['count' => 1, 'invoice' => $nr2, 'last_sent' => '2026-02-20', 'last_invoice' => $nr2], reminder_claim_fee_history($older, true));
        $this->assertSame("Faktura $nr2 fik en rykker med gebyr den 20.02.2026. En ny rykker med gebyr kan tidligst sendes den 02.03.2026 (renteloven § 9 b).",
            reminder_legal_check($older, '2026-03-01'));
        $GLOBALS['stripe_charge_today'] = '2026-03-02';
        $this->assertSame('', formReminderPrintChecked($older), 'printed on 02.03');
        $this->assertSame('2026-03-02', reminder_dispatch_date($older));
        $this->assertFalse(reminder_claim_first_dispatch($older), 'from then on it counts');
        $this->assertSame('', reminder_legal_check($older, '2026-03-03'), 'printed again, it is checked as before');

        // A chain whose levels all went out: sending its R1 again is checked as before, not held back by its later levels (10 days after R3).
        $debtor3 = StripeFixtures::debtor();
        $invoice3 = StripeFixtures::invoice($debtor3, ['udskriv_til' => 'email']);
        $r3 = ['debtor' => $debtor3, 'invoice' => $invoice3, 'reminder' => StripeReminderFixtures::reminder($debtor3, [$invoice3], ['date' => '2026-01-05'])];
        $levels = [$r3['reminder'], StripeReminderFixtures::nextLevel($r3, 2, '2026-01-20'), StripeReminderFixtures::nextLevel($r3, 3, '2026-02-05')];
        $sentOn($levels);
        $this->assertFalse(reminder_claim_first_dispatch($levels[0]));
        $this->assertSame('', reminder_legal_check($levels[0], '2026-02-10'));
        $GLOBALS['stripe_charge_today'] = '2026-02-10';
        $this->assertTrue(formSendReminderEntry($levels[0], $this->pdf($levels[0]), 'jens@stripe-tests.invalid', 'Dansk', 6, $this->classicSender())['sent']);
        $this->assertSame('2026-01-05', reminder_dispatch_date($levels[0]), 'the first day stays');
        // A level booked by "Ryk alle" before it went out already counts: printing it the first time is checked as before too.
        $debtor4 = StripeFixtures::debtor();
        $invoice4 = StripeFixtures::invoice($debtor4, ['udskriv_til' => 'email']);
        $r4 = ['debtor' => $debtor4, 'invoice' => $invoice4, 'reminder' => StripeReminderFixtures::reminder($debtor4, [$invoice4], ['date' => '2026-01-05', 'status' => 4])];
        $o4 = StripeFixtures::row('ordrer', $r4['reminder']);
        $fee4 = StripeFixtures::insert('openpost', ['konto_id' => $debtor4, 'konto_nr' => $o4['kontonr'], 'faktnr' => $o4['fakturanr'],
            'refnr' => $r4['reminder'], 'amount' => '100.000', 'beskrivelse' => 'Gebyr mm. fra tidligere rykker', 'udlignet' => '0',
            'transdate' => '2026-01-05', 'valuta' => 'DKK', 'valutakurs' => '100.000', 'projekt' => '']);
        reminder_dispatch_record(StripeReminderFixtures::nextLevel($r4, 2, '2026-02-05', ['openposts' => [$fee4]]), 'classic', '2026-02-05');
        $close([$r4['reminder']]);
        $this->assertFalse(reminder_claim_first_dispatch($r4['reminder']));
        $this->assertSame('', reminder_legal_check($r4['reminder'], '2026-02-10'));
    }

    public function testTheLastCheckAndTheRecordOfAFirstDispatchHoldTheCustomersLock(): void
    {
        require_once __DIR__ . '/../../includes/opdat_func/reminderDispatch.php';
        reminder_dispatch_schema_ensure('postgresql');
        reminder_dispatch_since_ensure('postgresql');
        $dsn = [];
        foreach (['host' => 'SALDI_TEST_PGHOST', 'user' => 'SALDI_TEST_PGUSER', 'password' => 'SALDI_TEST_PGPASSWORD', 'dbname' => 'SALDI_TEST_DB'] as $key => $env) {
            $dsn[] = $key . "='" . str_replace(['\\', "'"], ['\\\\', "\\'"], (string)getenv($env)) . "'";
        }
        $dsn = implode(' ', $dsn);
        $lockSql = static fn(string $fn, int $konto): string => "SELECT $fn(hashtext('saldi:reminder_dispatch'), $konto)";
        $held = static fn(): string => pg_fetch_result(StripeTestEnvironment::query(
            "SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND pid = pg_backend_pid()"), 0, 0);
        // What the dispatch record sees: whether this session holds the customer's lock when the day it went out is written.
        StripeTestEnvironment::query('CREATE TEMP TABLE h1_dispatch_seen (order_id integer, held bigint)');
        StripeTestEnvironment::query("CREATE FUNCTION public.h1_dispatch_seen() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                INSERT INTO h1_dispatch_seen SELECT NEW.order_id, count(*) FROM pg_locks l, ordrer o
                    WHERE o.id = NEW.order_id AND l.locktype = 'advisory' AND l.pid = pg_backend_pid() AND l.granted AND l.objsubid = 2
                    AND l.objid::text::bigint = o.konto_id AND l.classid::text::bigint = (hashtext('saldi:reminder_dispatch')::bigint & 4294967295);
                RETURN NEW;
            END \$\$");
        StripeTestEnvironment::query('CREATE TRIGGER h1_dispatch_seen AFTER INSERT ON reminder_dispatch FOR EACH ROW EXECUTE FUNCTION public.h1_dispatch_seen()');
        $seen = static function (int $id): ?string {
            $held = pg_fetch_result(StripeTestEnvironment::query('SELECT held FROM h1_dispatch_seen WHERE order_id = $1', [$id]), 0, 0);
            return $held === false ? null : $held;
        };

        // Mailed: another session cannot take the customer's lock while the mail goes out, and it is let go afterwards.
        $debtor = StripeFixtures::debtor();
        $invoice = StripeFixtures::invoice($debtor, ['udskriv_til' => 'email']);
        $mailed = StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-01-20']);
        $second = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        $this->assertNotFalse($second);
        try {
            $during = null;
            $result = formSendReminderEntry($mailed, $this->pdf($mailed), 'jens@stripe-tests.invalid', 'Dansk', 6,
                function (...$args) use ($second, $lockSql, $debtor, &$during): string {
                    $during = pg_fetch_result(pg_query($second, $lockSql('pg_try_advisory_lock', $debtor)), 0, 0);
                    return 'Mail sent to fixture';
                });
            $this->assertTrue($result['sent']);
            $this->assertSame('f', $during, 'the lock is held while the mail goes out');
            $this->assertSame('1', $seen($mailed), 'and when the day it went out is recorded');
            $this->assertSame('t', pg_fetch_result(pg_query($second, $lockSql('pg_try_advisory_lock', $debtor)), 0, 0), 'let go afterwards');
            pg_query($second, $lockSql('pg_advisory_unlock', $debtor));
            $this->assertSame('t', pg_fetch_result(pg_query($second, $lockSql('pg_try_advisory_lock', $debtor + 1)), 0, 0), 'another customer is not locked');
            pg_query($second, $lockSql('pg_advisory_unlock', $debtor + 1));
        } finally {
            pg_close($second);
        }

        // Printed: recorded under the lock, and let go afterwards.
        $printed = StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-02-05', 'fee' => 0]);
        $this->assertSame('', formReminderPrintChecked($printed));
        $this->assertSame('1', $seen($printed));
        $this->assertSame('0', $held());

        // Another session is dispatching for the same customer: the check waits until it is done.
        $waiting = StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-02-20', 'fee' => 0]);
        $code = '$c = pg_connect(getenv("H1_DSN"), PGSQL_CONNECT_FORCE_NEW); $k = (int)getenv("H1_KONTO");'
            . ' pg_query($c, "SELECT pg_advisory_lock(hashtext(\'saldi:reminder_dispatch\'), $k)"); echo "locked\n"; usleep(1200000);'
            . ' pg_query($c, "SELECT pg_advisory_unlock(hashtext(\'saldi:reminder_dispatch\'), $k)"); echo "done\n";';
        $other = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['H1_DSN' => $dsn, 'H1_KONTO' => (string)$debtor] + getenv());
        $this->assertIsResource($other);
        try {
            $this->assertSame("locked\n", fgets($pipes[1]));
            $before = microtime(true);
            $this->assertSame('', formReminderPrintChecked($waiting));
            $this->assertGreaterThan(0.8, microtime(true) - $before, 'it waited for the other session');
            $this->assertSame("done\n", fgets($pipes[1]));
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($other);
        }
        $this->assertSame('1', $seen($waiting));
        $this->assertSame('0', $held(), 'nothing is left locked');
    }

    public function testCreationNoticeEscalationCheckAndReminderListMarkers(): void
    {
        // ny_rykker.php (helpers of reminderLegal.php): the L1 notice and the L3 check before a 2nd/3rd reminder is created.
        $this->assertSame('Rykkergebyret er sat ned til 100,00 kr. (renteloven § 9 b).', reminder_cap_notice(100.0, 'DKK'));
        $r = StripeReminderFixtures::linkReminder();
        StripeReminderFixtures::linkRow($r['reminder'], ['mailed_at' => '2026-01-21 16:45:00']);
        $this->assertSame('2. rykker kan tidligst sendes den 31.01.2026 (mindst 10 dage efter forrige rykker, renteloven § 9 b).',
            reminder_escalation_check($r['reminder'], '2026-01-25'));
        $this->assertSame('', reminder_escalation_check($r['reminder'], '2026-01-31'));
        StripeTestEnvironment::query("UPDATE formularer SET xb = 0 WHERE beskrivelse = 'GEBYR' AND formular = 7");
        $this->assertSame('', reminder_escalation_check($r['reminder'], '2026-01-25'), 'a 2nd reminder without a fee may come at once');

        // The reminder lists (rapportfunc.php, rykker.php) mark link reminders.
        $this->assertSame('Linkrykker · gebyr på næste faktura', stripe_reminder_marker($r['reminder']));
        StripeTestEnvironment::query("UPDATE stripe_reminders SET fee_mode = 'classic' WHERE rykker_id = $1", [$r['reminder']]);
        $this->assertSame('Linkrykker · gebyr som åben post', stripe_reminder_marker($r['reminder']));
        $this->assertTrue(stripe_reminder_waive($r['reminder'], 'manual'));
        $this->assertSame('Linkrykker · gebyr frafaldet', stripe_reminder_marker($r['reminder']));
        $this->assertSame('', stripe_reminder_marker(StripeReminderFixtures::linkReminder([], ['udskriv_til' => 'email'])['reminder']));
    }

    public function testBulkPrintHandlesEveryTickedReminderThroughTheReminderSendPath(): void
    {
        // A8: rapport.php passes only rykker_id; rykkerprint.php builds one account entry per reminder.
        $page = file_get_contents(__DIR__ . '/../../debitor/rykkerprint.php');
        $this->assertSame(1, preg_match('/if \(\$kontoliste\) \{.*?\} else \{(.*?)\}/s', $page, $fallback));
        $rykker_id = [11, 12, 13];
        eval($fallback[1]);
        $this->assertSame(['', '', ''], $konto_id);
        $formfunk = file_get_contents(__DIR__ . '/../../includes/formfunk.php');
        $start = strpos($formfunk, 'function rykkerprint(');
        $this->assertNotFalse($start);
        $body = substr($formfunk, $start, 40000);
        $this->assertStringContainsString('formSendReminderEntry(', $body);
        // renteloven § 9 b is checked for printed reminders too, not only for mailed ones.
        $this->assertMatchesRegularExpression('/\b(reminder_legal_check|formReminderRefusal)\(/', $body);
        // Where the fee went and what the mail leaves out (formReminderPdfTreatment(), tested above), a draft printed on paper
        // is sent, and every printed reminder writes its own HTML page while a mailed one's HTML is thrown away ("Brug html").
        $this->assertStringContainsString('formReminderPdfTreatment(', $body);
        // 20261127 ASR Review GPT-H-1: a printed reminder is checked again and recorded under the customer's lock before it is printed.
        $this->assertStringContainsString('formReminderPrintChecked(', $body);
        $this->assertLessThan(strpos($body, '$nomailantal++;'), strpos($body, 'formReminderPrintChecked('));
        $this->assertStringContainsString('$htmfp = fopen("../temp/$db/{$printfilnavn}_$nomailantal.htm", "w");', $body);
        $this->assertStringContainsString('$htmfp = fopen("../temp/$pfnavn.htm", "w");', $body);
    }

    // ---------------------------------------------------------------- the fee on the next invoice (bogfor)

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCarriedFeeGoesOnTheNextInvoiceAsVatFreeLinesThatDoNotRepeat(): void
    {
        StripeReminderFixtures::orderFunctionDoubles();
        $GLOBALS['stripe_charge_today'] = self::AFTER_DEADLINE;
        $r = StripeReminderFixtures::linkReminder([], [], ['interest' => 12.5, 'status' => 4]);
        StripeReminderFixtures::linkRow($r['reminder'], ['interest_ore' => 1250]);
        $rykkerNr = StripeFixtures::row('ordrer', $r['reminder'])['fakturanr'];
        $invoiceNr = StripeFixtures::row('ordrer', $r['invoice'])['fakturanr'];
        $next = StripeFixtures::order($r['debtor'], ['levdate' => '2026-02-01']);

        $this->assertSame('', stripe_reminder_fee_bogfor($next, 'DO', 25, false, false));
        $lines = $this->feeLines($next);
        $this->assertCount(2, $lines);
        [$fee, $interest] = $lines[0]['vare_id'] == $this->items['fee'] ? $lines : array_reverse($lines);
        $this->assertSame("Rykkergebyr, rykker $rykkerNr (faktura $invoiceNr)", $fee['beskrivelse']);
        $this->assertSame("Renter, rykker $rykkerNr (faktura $invoiceNr)", $interest['beskrivelse']);
        foreach ([[$fee, 100.0], [$interest, 12.5]] as [$line, $price]) {
            $this->assertEqualsWithDelta(1.0, (float)$line['antal'], 0.0001);
            $this->assertEqualsWithDelta($price, (float)$line['pris'], 0.0001);
            $this->assertSame('on', trim((string)$line['momsfri']), 'no VAT on a reminder fee');
            $this->assertSame('on', trim((string)$line['kdo']), 'not copied to the next recurring invoice');
            $this->assertEqualsWithDelta(0.0, (float)$line['rabat'], 0.0001);
        }
        $order = StripeFixtures::row('ordrer', $next);
        $this->assertEqualsWithDelta(272.0 + 112.5, (float)$order['sum'], 0.001);
        $this->assertEqualsWithDelta(68.0, (float)$order['moms'], 0.001);
        $row = StripeReminderFixtures::linkRowOf($r['reminder']);
        $this->assertSame('charged', $row['status']);
        $this->assertSame($next, (int)$row['fee_ordre_id']);

        // Posting again, or the next invoice after it, adds nothing.
        $this->assertSame('', stripe_reminder_fee_bogfor($next, 'DO', 25, false, false));
        $this->assertCount(2, $this->feeLines($next));
        $later = StripeFixtures::order($r['debtor'], ['levdate' => '2026-03-01']);
        $this->assertSame('', stripe_reminder_fee_bogfor($later, 'DO', 25, false, false));
        $this->assertCount(0, $this->feeLines($later));

        // One reminder for two invoices names both.
        $debtor = StripeFixtures::debtor();
        $a = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Link']);
        $b = StripeReminderFixtures::invoiceOn($debtor, '2026-01-12');
        $rykker = StripeReminderFixtures::reminder($debtor, [$a, $b], ['status' => 4]);
        StripeReminderFixtures::linkRow($rykker);
        $next = StripeFixtures::order($debtor, ['levdate' => '2026-02-01']);
        $this->assertSame('', stripe_reminder_fee_bogfor($next, 'DO', 25, false, false));
        $lines = $this->feeLines($next);
        $this->assertCount(1, $lines);
        $this->assertSame('Rykkergebyr, rykker ' . StripeFixtures::row('ordrer', $rykker)['fakturanr'] . ' (fakturaer '
            . StripeFixtures::row('ordrer', $a)['fakturanr'] . ', ' . StripeFixtures::row('ordrer', $b)['fakturanr'] . ')', $lines[0]['beskrivelse']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCarriedFeeIsWaivedWhenTheCustomerTurnsOnAutomaticPaymentAfterTheReminder(): void
    {
        StripeReminderFixtures::orderFunctionDoubles();
        $GLOBALS['stripe_charge_today'] = self::AFTER_DEADLINE;
        $mailed = '2026-01-20 15:30:00';
        foreach ([
            // The date compare is by day: enrolling later on the day the reminder was mailed counts.
            'enrolled the day the reminder was mailed' => [['stripe' => 'on', 'stripe_pm_id' => 'pm_new', 'stripe_date' => '2026-01-20'], null, 'waived'],
            'enrolled before the reminder' => [['stripe' => 'on', 'stripe_pm_id' => 'pm_old', 'stripe_date' => '2026-01-19'], null, 'charged'],
            'new card saved after the reminder' => [['stripe' => 'on', 'stripe_pm_id' => 'pm_new', 'stripe_date' => '2025-12-01'], '2026-01-22 09:00:00', 'waived'],
            'card saved the day before the reminder' => [['stripe' => 'on', 'stripe_pm_id' => 'pm_old', 'stripe_date' => '2025-12-01'], '2026-01-19 10:00:00', 'charged'],
            'automatic payment off again' => [['stripe' => '', 'stripe_pm_id' => null, 'stripe_date' => '2026-01-21'], '2026-01-21 09:00:00', 'charged'],
            'never enrolled' => [[], null, 'charged'],
        ] as $case => [$debtorFields, $cardSavedAt, $expected]) {
            $r = StripeReminderFixtures::linkReminder($debtorFields, [], ['status' => 4]);
            StripeReminderFixtures::linkRow($r['reminder'], ['mailed_at' => $mailed]);
            if ($cardSavedAt !== null) {
                $this->payment($r['invoice'], 'succeeded', 'page', ['save_card' => true, 'created_at' => $cardSavedAt, 'succeeded_at' => $cardSavedAt]);
            }
            $next = StripeFixtures::order($r['debtor'], ['levdate' => '2026-02-01']);
            $this->assertSame('', stripe_reminder_fee_bogfor($next, 'DO', 25, false, false), $case);
            $this->assertSame($expected, StripeReminderFixtures::linkRowOf($r['reminder'])['status'], $case);
            $this->assertCount($expected === 'charged' ? 1 : 0, $this->feeLines($next), $case);
        }
        // With the waiver switched off the fee is charged.
        StripeTestEnvironment::setting('stripe', 'reminder_fee_waive', 'off');
        $r = StripeReminderFixtures::linkReminder(['stripe' => 'on', 'stripe_pm_id' => 'pm_new', 'stripe_date' => '2026-01-21'], [], ['status' => 4]);
        StripeReminderFixtures::linkRow($r['reminder'], ['mailed_at' => $mailed]);
        $next = StripeFixtures::order($r['debtor'], ['levdate' => '2026-02-01']);
        $this->assertSame('', stripe_reminder_fee_bogfor($next, 'DO', 25, false, false));
        $this->assertSame('charged', StripeReminderFixtures::linkRowOf($r['reminder'])['status']);
        $this->assertCount(1, $this->feeLines($next));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCarriedFeeSkipsCreditNotesForeignInvoicesAndUnsentDeletedOrUnbookedReminders(): void
    {
        StripeReminderFixtures::orderFunctionDoubles();
        $GLOBALS['stripe_charge_today'] = self::AFTER_DEADLINE;
        $r = StripeReminderFixtures::linkReminder([], [], ['status' => 4]);
        StripeReminderFixtures::linkRow($r['reminder']);
        $negative = StripeFixtures::order($r['debtor'], ['levdate' => '2026-02-01']);
        StripeTestEnvironment::query('UPDATE ordrelinjer SET antal = -antal WHERE ordre_id = $1', [$negative]);
        $creditNote = StripeFixtures::order($r['debtor'], ['art' => 'DK', 'levdate' => '2026-02-01']);
        $foreign = StripeFixtures::order($r['debtor'], ['valuta' => 'EUR', 'valutakurs' => '746.000', 'levdate' => '2026-02-01']);
        $undelivered = StripeFixtures::order($r['debtor']);
        foreach (['posted as a credit note' => [$negative, 'DO'], 'credit note' => [$creditNote, 'DK'],
            'foreign currency' => [$foreign, 'DO'], 'no delivery date' => [$undelivered, 'DO']] as $case => [$order, $art]) {
            $this->assertSame('', stripe_reminder_fee_bogfor($order, $art, 25, false, false), $case);
            $this->assertCount(0, $this->feeLines($order), $case);
        }
        $this->assertSame('sent', StripeReminderFixtures::linkRowOf($r['reminder'])['status'], 'still waiting for an invoice');
        $ordinary = StripeFixtures::order($r['debtor'], ['levdate' => '2026-02-01']);
        $this->assertSame('', stripe_reminder_fee_bogfor($ordinary, 'DO', 25, false, false));
        $this->assertCount(1, $this->feeLines($ordinary));

        foreach ([
            'reminder not booked yet' => [['status' => 2], []],
            'reminder never sent' => [['status' => 4], ['status' => 'draft']],
            'fee posted as an open item' => [['status' => 4], ['fee_mode' => 'classic']],
            'nothing to carry' => [['status' => 4, 'fee' => 0], ['fee_ore' => 0, 'interest_ore' => 0]],
            'reminder deleted' => [['status' => 4], [], true],
        ] as $case => $spec) {
            [$reminder, $row] = $spec;
            $s = StripeReminderFixtures::linkReminder([], [], $reminder);
            StripeReminderFixtures::linkRow($s['reminder'], $row);
            if (!empty($spec[2])) {
                StripeTestEnvironment::query('DELETE FROM ordrelinjer WHERE ordre_id = $1', [$s['reminder']]);
                StripeTestEnvironment::query('DELETE FROM ordrer WHERE id = $1', [$s['reminder']]);
            }
            $next = StripeFixtures::order($s['debtor'], ['levdate' => '2026-02-01']);
            $this->assertSame('', stripe_reminder_fee_bogfor($next, 'DO', 25, false, false), $case);
            $this->assertCount(0, $this->feeLines($next), $case);
        }
    }

    public function testBothReminderBookingCopiesSkipCarriedFeesAndTagTheFeeOpenItem(): void
    {
        foreach (['includes/rapportfunc.php', 'includes/openpost.php'] as $file) {
            $source = file_get_contents(__DIR__ . '/../../' . $file);
            $start = strpos($source, 'function bogfor_rykker(');
            $this->assertNotFalse($start, $file);
            $this->assertStringContainsString('stripe_reminder_carried(', substr($source, $start, strpos($source, 'function bogfor_nu(', $start) - $start), $file);
            // bogfor_nu: the fee's open item gets projekt = '' so finans/bogfor.php openpost() can settle it.
            $insert = strpos($source, 'insert into openpost', strpos($source, 'function bogfor_nu('));
            $this->assertNotFalse($insert, $file);
            $this->assertStringContainsString('projekt', substr($source, $insert, strpos($source, 'db_modify(', $insert) - $insert), $file);
        }
        // The fee is added in the same posting transaction, right after the fee for manual payment.
        $ordrefunc = file_get_contents(__DIR__ . '/../../includes/ordrefunc.php');
        $manual = strpos($ordrefunc, 'stripe_manual_fee_bogfor($id, $art, $momssats, $webservice, $genfakt)');
        $reminder = strpos($ordrefunc, 'stripe_reminder_fee_bogfor($id, $art, $momssats, $webservice, $genfakt)');
        $this->assertNotFalse($manual);
        $this->assertNotFalse($reminder);
        $this->assertGreaterThan($manual, $reminder);
        $this->assertLessThan(strpos($ordrefunc, '$qtxt = "select * from ordrelinjer where ordre_id=\'$id\' and antal!=\'0\' order by saet";'), $reminder);
    }

    // ---------------------------------------------------------------- the reminder mail

    public function testReminderMailForOneInvoice(): void
    {
        $r = StripeReminderFixtures::linkReminder();
        StripeReminderFixtures::linkRow($r['reminder'], ['status' => 'draft']);
        $pdf = $this->pdf($r['reminder']);
        $this->assertTrue(stripe_mail_reminder($r['reminder'], $pdf, 'jens@stripe-tests.invalid'));
        $this->assertCount(1, StripeTestTransport::$mails);
        $mail = StripeTestTransport::$mails[0];
        $inv = stripe_invoice_load($r['invoice']);
        $nr = $inv['nr'];
        $url = stripe_invoice_url($r['invoice']);
        $this->assertSame('jens@stripe-tests.invalid', $mail['to']);
        $this->assertSame("Rykker: Faktura $nr fra {$inv['brand']} er ikke betalt", $mail['subject']);
        $plain = self::plain($mail['html']);
        $this->assertStringContainsStringIgnoringCase("1. rykker · faktura $nr", $plain);
        foreach (['Vi mangler din betaling', 'Hej Stripe test debtor,',
            "Vi har endnu ikke modtaget betalingen af faktura $nr, som forfaldt den 16.01.2026. Måske er den bare gledet forbi – du kan betale den med kort lige her.",
            'Slå automatisk betaling til – og spar rykkergebyret på 100,00 kr.',
            'Betaler du med knappen nedenfor og gemmer kortet, fjerner vi rykkergebyret.',
            "Faktura $nr · forfaldt 16.01.2026", 'Vil du kun betale denne faktura, kan du vælge det på siden.',
            'Har du betalt inden for de seneste dage, kan du se bort fra denne rykker. Rykkeren er vedhæftet som PDF.'] as $text) {
            $this->assertStringContainsString($text, $plain);
        }
        $this->assertMatchesRegularExpression('/Rykkergebyr 100,00 kr\. på næste faktura/u', $plain);
        $this->assertMatchesRegularExpression('/At betale nu 340,00 kr\./u', $plain);
        $this->assertMatchesRegularExpression('/Betal senest 28\.01\.2026/u', $plain);   // reminder date + 8 days
        $this->assertStringNotContainsString('Renter', $plain);
        $this->assertSame($url, self::link($mail['html'], 'Betal og slå automatisk betaling til'));
        $this->assertSame([$url], self::links($mail['html'], 'Betal ›'));
        $this->assertStringContainsString($url, $mail['text']);
        $this->assertStringContainsString('Betal og slå automatisk betaling til', $mail['text']);
        $this->assertReminderPdf($mail, $pdf, StripeFixtures::row('ordrer', $r['reminder'])['fakturanr']);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testReminderMailForSeveralInvoicesStartsWithTheOldest(): void
    {
        $debtor = StripeFixtures::debtor();
        $newer = StripeReminderFixtures::invoiceOn($debtor, '2026-01-12');   // due 20.01.2026, created first
        $older = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Link']);   // due 16.01.2026
        $rykker = StripeReminderFixtures::reminder($debtor, [$newer, $older]);
        StripeReminderFixtures::linkRow($rykker, ['status' => 'draft']);
        $this->assertTrue(stripe_mail_reminder($rykker, $this->pdf($rykker), 'jens@stripe-tests.invalid'));
        $mail = StripeTestTransport::$mails[0];
        $brand = stripe_invoice_load($older)['brand'];
        $olderNr = StripeFixtures::row('ordrer', $older)['fakturanr'];
        $newerNr = StripeFixtures::row('ordrer', $newer)['fakturanr'];
        $this->assertSame("Rykker: 2 fakturaer fra $brand er ikke betalt", $mail['subject']);
        $plain = self::plain($mail['html']);
        $this->assertStringContainsStringIgnoringCase('1. rykker · 2 fakturaer', $plain);
        $this->assertStringContainsString('Fakturaerne betales én ad gangen via deres egne links nedenfor.', $plain);
        $this->assertStringContainsString("Den store knap åbner faktura $olderNr på 340,00 kr. Brug linkene", $plain);
        $this->assertStringNotContainsString('kr..', $plain . $mail['text']);
        $first = strpos($plain, "Faktura $olderNr · forfaldt 16.01.2026");
        $second = strpos($plain, "Faktura $newerNr · forfaldt 20.01.2026");
        $this->assertNotFalse($first);
        $this->assertNotFalse($second);
        $this->assertLessThan($second, $first, 'the oldest invoice first');
        $this->assertMatchesRegularExpression('/At betale nu 680,00 kr\./u', $plain);
        $this->assertSame(stripe_invoice_url($older), self::link($mail['html'], "Betal faktura $olderNr · 340,00 kr."));
        $this->assertSame([stripe_invoice_url($older), stripe_invoice_url($newer)], self::links($mail['html'], 'Betal ›'));
        foreach ([$older, $newer] as $invoice) {
            $this->assertStringContainsString(stripe_invoice_url($invoice), $mail['text']);
        }
    }

    public function testMultiInvoiceReminderStartsWithAnInvoiceThatCanBePaidByCard(): void
    {
        $GLOBALS['stripe_charge_today'] = '2026-01-25';
        $debtor = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved']);
        $small = StripeFixtures::invoice($debtor, ['sum' => 1, 'moms' => 0.25]);
        $next = StripeFixtures::invoice($debtor, ['sum' => 4.5, 'moms' => 1.13]);
        $last = StripeFixtures::invoice($debtor, ['sum' => 133, 'moms' => 33.25, 'betalingsdage' => 12]);
        $rykker = StripeReminderFixtures::reminder($debtor, [$small, $next, $last]);
        StripeReminderFixtures::linkRow($rykker, ['status' => 'draft']);
        $mail = $this->mailReminder($rykker);
        $nr = StripeFixtures::row('ordrer', $next)['fakturanr'];
        $this->assertSame(stripe_invoice_url($next), self::link($mail['html'], "Betal faktura $nr · 5,63 kr."));
        $this->assertSame([stripe_invoice_url($small)], self::links($mail['html'], 'Se faktura ›'));
        $this->assertSame([stripe_invoice_url($next), stripe_invoice_url($last)], self::links($mail['html'], 'Betal ›'));
        foreach ([self::plain($mail['html']), $mail['text']] as $body) {
            $this->assertStringContainsString("Den store knap åbner faktura $nr på 5,63 kr.", $body);
            $this->assertMatchesRegularExpression('/At betale nu\s*:?\s*173,13 kr\./u', $body);
        }
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testLaterRemindersSayStillAndTheThirdCanWarnAboutClosing(): void
    {
        $r = StripeReminderFixtures::linkReminder();
        StripeReminderFixtures::linkRow($r['reminder']);
        $nr = StripeFixtures::row('ordrer', $r['invoice'])['fakturanr'];
        $brand = stripe_invoice_load($r['invoice'])['brand'];
        $second = StripeReminderFixtures::nextLevel($r, 2, '2026-02-01');
        StripeReminderFixtures::linkRow($second, ['status' => 'draft']);
        $mail = $this->mailReminder($second);
        $this->assertSame("2. rykker: Faktura $nr fra $brand er stadig ikke betalt", $mail['subject']);
        $plain = self::plain($mail['html']);
        $this->assertStringContainsString('Vi mangler stadig din betaling', $plain);
        $this->assertStringContainsStringIgnoringCase("2. rykker · faktura $nr", $plain);

        $third = StripeReminderFixtures::nextLevel($r, 3, '2026-02-12');   // pay by 20.02.2026
        StripeReminderFixtures::linkRow($third, ['status' => 'draft']);
        $mail = $this->mailReminder($third);
        $this->assertSame("3. rykker: Faktura $nr fra $brand er stadig ikke betalt", $mail['subject']);
        $this->assertStringNotContainsString('lukke for din adgang', self::plain($mail['html']), 'the warning is off by default');

        StripeTestEnvironment::setting('stripe', 'reminder_close_warning', 'on');
        $this->assertStringContainsString(str_replace('{frist}', '20.02.2026', self::CLOSE_TEXT), self::plain($this->mailReminder($third)['html']));
        StripeTestEnvironment::setting('stripe', 'reminder_close_text', 'Sidste frist er {frist}. Herefter lukker vi.');
        $this->assertStringContainsString('Sidste frist er 20.02.2026. Herefter lukker vi.', self::plain($this->mailReminder($third)['html']));
        $this->assertStringNotContainsString('Herefter lukker vi', self::plain($this->mailReminder($second)['html']), 'only the 3rd reminder');
    }

    public function testReminderButtonFollowsTheInvoicePageState(): void
    {
        $enrolled = ['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'stripe_date' => '2026-01-01'];

        // Automatic payment already on, unpaid Link invoice: one click with the saved card, nothing to offer.
        $saved = StripeReminderFixtures::linkReminder($enrolled);
        $mail = $this->mailReminder($saved['reminder'], true);
        $this->assertSame(stripe_invoice_url($saved['invoice']), self::link($mail['html'], 'Betal med dit gemte kort'));
        $this->assertStringNotContainsString('spar rykkergebyret', self::plain($mail['html']));

        // The saved card was declined: pay with another card, which is saved for the next invoices.
        $failed = StripeReminderFixtures::linkReminder($enrolled);
        $this->payment($failed['invoice'], 'failed', 'manual', ['last_error' => 'card_declined', 'attempt_date' => '2026-01-17']);
        $mail = $this->mailReminder($failed['reminder'], true);
        $nr = StripeFixtures::row('ordrer', $failed['invoice'])['fakturanr'];
        $this->assertStringContainsString("Vi har forsøgt at trække betalingen af faktura $nr på dit gemte kort, men det lykkedes ikke. Betal den nu med et andet kort – så gemmer vi også det nye kort til dine næste fakturaer.", self::plain($mail['html']));
        $this->assertSame(stripe_invoice_url($failed['invoice']), self::link($mail['html'], 'Opdater kort og betal'));

        // The bank asked the customer to approve a saved-card payment (no longer held back from reminders after 14 days).
        $action = StripeReminderFixtures::linkReminder($enrolled);
        $this->payment($action['invoice'], 'requires_action', 'manual', ['created_at' => '2026-01-01 10:00:00', 'action_required_at' => '2026-01-01 10:00:00']);
        $mail = $this->mailReminder($action['reminder'], true);
        $this->assertSame(stripe_invoice_url($action['invoice']), self::link($mail['html'], 'Godkend betalingen'));

        // No card payment possible: the bank details and a link to see the invoice.
        StripeTestEnvironment::query("UPDATE adresser SET bank_reg = '1234', bank_konto = '5678901' WHERE art = 'S'");
        $bank = StripeReminderFixtures::linkReminder(['stripe_fravalg' => 'on']);
        $mail = $this->mailReminder($bank['reminder'], true);
        $nr = StripeFixtures::row('ordrer', $bank['invoice'])['fakturanr'];
        $this->assertSame(stripe_invoice_url($bank['invoice']), self::link($mail['html'], 'Se fakturaen'));
        $this->assertNull(self::link($mail['html'], 'Betal og slå automatisk betaling til'));
        $this->assertStringContainsString("Reg. 1234 · konto 5678901 · reference $nr", self::plain($mail['html']));
        $this->assertSame([], self::links($mail['html'], 'Betal ›'), 'no card payment on its page');
        $this->assertSame([stripe_invoice_url($bank['invoice'])], self::links($mail['html'], 'Se faktura ›'));

        // A declined payment with the card form (source page): the page shows the card form again, not the saved card.
        $page = StripeReminderFixtures::linkReminder($enrolled);
        $this->payment($page['invoice'], 'failed', 'page', ['last_error' => 'card_declined', 'attempt_date' => '2026-01-17']);
        $mail = $this->mailReminder($page['reminder'], true);
        $this->assertNull(self::link($mail['html'], 'Betal med dit gemte kort'));
        $this->assertSame(stripe_invoice_url($page['invoice']), self::link($mail['html'], 'Se faktura og betal'));
    }

    public function testFeeAsAnOpenItemIsPaidNowAndACreditIsDeducted(): void
    {
        $r = StripeReminderFixtures::linkReminder([], [], ['interest' => 12.5]);
        $d = StripeFixtures::row('adresser', $r['debtor']);
        $credit = StripeFixtures::insert('openpost', ['konto_id' => $r['debtor'], 'konto_nr' => $d['kontonr'], 'faktnr' => 'K' . $r['invoice'],
            'amount' => '-40.000', 'udlignet' => '0', 'transdate' => '2026-01-10', 'forfaldsdate' => '2026-01-10',
            'valuta' => 'DKK', 'valutakurs' => '100.000', 'projekt' => '']);
        StripeFixtures::insert('ordrelinjer', ['ordre_id' => $r['reminder'], 'posnr' => 10, 'enhed' => (string)$credit, 'beskrivelse' => 'Kreditnota']);
        StripeReminderFixtures::linkRow($r['reminder'], ['status' => 'draft', 'fee_mode' => 'classic', 'interest_ore' => 1250]);
        StripeTestEnvironment::query("UPDATE adresser SET bank_reg = '', bank_konto = '' WHERE art = 'S'");
        $plain = self::plain($this->mailReminder($r['reminder'])['html']);
        // The Betal link pays the invoice only: the fee and interest open item is paid by bank transfer (I19).
        $this->assertMatchesRegularExpression('/Rykkergebyr 100,00 kr\. betales via bankoverførsel/u', $plain);
        $this->assertMatchesRegularExpression('/Renter 12,50 kr\. betales via bankoverførsel/u', $plain);
        $this->assertStringNotContainsString('betales sammen med fakturaen', $plain);
        $this->assertStringContainsString('Tilgodehavende', $plain);
        $this->assertMatchesRegularExpression('/At betale nu 412,50 kr\./u', $plain);   // 340 + 100 + 12,50 - 40
        $this->assertStringNotContainsString('på næste faktura', $plain);
        $this->assertStringNotContainsString('spar rykkergebyret', $plain);
        $this->assertStringNotContainsString('reg. ', $plain, 'no bank details, no bank line');

        // With the company's bank details: a bank line with the reminder as the reference.
        StripeTestEnvironment::query("UPDATE adresser SET bank_reg = '1234', bank_konto = '5678901' WHERE art = 'S'");
        $nr = StripeFixtures::row('ordrer', $r['reminder'])['fakturanr'];
        $mail = $this->mailReminder($r['reminder']);
        $this->assertStringContainsString("Rykkergebyr: reg. 1234 · konto 5678901 · reference $nr", self::plain($mail['html']));
        $this->assertStringContainsString("Rykkergebyr: reg. 1234 · konto 5678901 · reference $nr", $mail['text']);
    }

    public function testPaidOrInFlightInvoicesAreLeftOutAndAFullyPaidReminderIsNotMailed(): void
    {
        $debtor = StripeFixtures::debtor();
        $paid = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Link']);
        $open = StripeReminderFixtures::invoiceOn($debtor, '2026-01-12');
        $inFlight = StripeReminderFixtures::invoiceOn($debtor, '2026-01-13');
        $rykker = StripeReminderFixtures::reminder($debtor, [$paid, $open, $inFlight]);
        StripeReminderFixtures::linkRow($rykker, ['status' => 'draft']);
        StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1' WHERE id = $1", [StripeReminderFixtures::openpost($paid)['id']]);
        $this->payment($inFlight, 'processing', 'page');

        $selection = stripe_reminder_invoices($rykker);
        $this->assertSame([$open], array_map(static fn($inv) => (int)$inv['id'], $selection['invoices']));
        $this->assertCount(2, $selection['skipped']);
        $mail = $this->mailReminder($rykker);
        $nr = StripeFixtures::row('ordrer', $open)['fakturanr'];
        $this->assertStringStartsWith("Rykker: Faktura $nr fra ", $mail['subject']);
        $this->assertMatchesRegularExpression('/At betale nu 340,00 kr\./u', self::plain($mail['html']));
        foreach ([$paid, $inFlight] as $left) {
            $this->assertStringNotContainsString(stripe_invoice_url($left), $mail['html']);
        }

        // Paid by bank in Saldi (invoice marked paid): nothing left, nothing mailed.
        StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on' WHERE id = $1", [$open]);
        StripeTestTransport::$mails = [];
        $this->assertFalse(stripe_mail_reminder($rykker, $this->pdf($rykker), 'jens@stripe-tests.invalid'));
        $this->assertSame([], StripeTestTransport::$mails);
    }

    /** Review GPT-R-10: an invoice paid in installments stays on the reminder with what is left when one installment is settled. */
    public function testASettledInstallmentLeavesTheRestOfItsInvoiceOnTheReminder(): void
    {
        $debtor = StripeFixtures::debtor();
        $invoice = StripeReminderFixtures::invoiceOn($debtor, '2026-01-12');
        $first = StripeReminderFixtures::openpost($invoice);
        // Two due dates: 200 kr. on 20.01.2026 and 140 kr. on 27.01.2026.
        StripeTestEnvironment::query("UPDATE openpost SET amount = '200.000' WHERE id = $1", [$first['id']]);
        $second = StripeFixtures::insert('openpost', ['konto_id' => $debtor, 'konto_nr' => $first['konto_nr'], 'faktnr' => $first['faktnr'],
            'amount' => '140.000', 'udlignet' => '0', 'transdate' => '2026-01-12', 'forfaldsdate' => '2026-01-27',
            'valuta' => 'DKK', 'valutakurs' => '100.000', 'projekt' => '']);
        $rykker = StripeReminderFixtures::reminder($debtor, [$invoice], ['openposts' => [$second]]);
        StripeReminderFixtures::linkRow($rykker, ['status' => 'draft']);
        $set = stripe_reminder_invoices($rykker);
        $this->assertSame([[$invoice, 340.0]], array_map(static fn($inv) => [(int)$inv['id'], (float)$inv['amount']], $set['invoices']), 'one row, both amounts');

        StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1' WHERE id = $1", [$first['id']]);
        $set = stripe_reminder_invoices($rykker);
        $this->assertSame([[$invoice, 140.0]], array_map(static fn($inv) => [(int)$inv['id'], (float)$inv['amount']], $set['invoices']), 'what is left');
        $this->assertSame([], $set['skipped']);
        $this->assertSame([(int)$first['id'] => 'settled'], $set['skipped_items']);
        $this->assertSame([(int)$first['id']], formReminderPdfTreatment($rykker)['skip'], 'the PDF leaves out the settled installment only');
        $this->assertSame('', formReminderRefusal($rykker), 'it may be sent');
        $plain = self::plain($this->mailReminder($rykker)['html']);
        $this->assertMatchesRegularExpression('/At betale nu 140,00 kr\./u', $plain);
        $this->assertSame('bank', stripe_reminder_variant($set['invoices'][0]), 'a partly paid invoice is not collected by card');

        StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1' WHERE id = $1", [$second]);
        $set = stripe_reminder_invoices($rykker);
        $this->assertSame([], $set['invoices']);
        $this->assertSame([$invoice => 'settled'], $set['skipped'], 'every installment settled: the invoice is left out');
    }

    /** Review GPT-R-11: a credit note draft credits nothing yet, so the invoice is still on the link reminder; an invoiced credit note leaves it out. */
    public function testACreditNoteDraftDoesNotLeaveItsInvoiceOut(): void
    {
        $r = StripeReminderFixtures::linkReminder();
        StripeReminderFixtures::linkRow($r['reminder'], ['status' => 'draft']);
        $credit = StripeFixtures::insert('ordrer', ['art' => 'DK', 'konto_id' => $r['debtor'], 'kred_ord_id' => $r['invoice'], 'status' => 1,
            'ordrenr' => 900001, 'firmanavn' => 'Kreditnota-kladde', 'sum' => 100, 'moms' => 25, 'valuta' => 'DKK']);
        $set = stripe_reminder_invoices($r['reminder']);
        $this->assertSame([[$r['invoice'], 340.0]], array_map(static fn($inv) => [(int)$inv['id'], (float)$inv['amount']], $set['invoices']));
        $this->assertSame([], $set['skipped']);
        $this->assertSame([], formReminderPdfTreatment($r['reminder'])['skip']);
        $this->assertSame('', formReminderRefusal($r['reminder']), 'it may be sent');
        $this->assertMatchesRegularExpression('/At betale nu 340,00 kr\./u', self::plain($this->mailReminder($r['reminder'])['html']));
        $this->assertSame('bank', stripe_reminder_variant($set['invoices'][0]), 'no card payment while the draft exists');

        // Invoiced: the credit note credits the invoice, which is left out as before.
        StripeTestEnvironment::query("UPDATE ordrer SET status = 3, fakturanr = '900001' WHERE id = $1", [$credit]);
        $set = stripe_reminder_invoices($r['reminder']);
        $this->assertSame([], $set['invoices']);
        $this->assertSame([$r['invoice'] => 'credited'], $set['skipped']);
    }

    // ---------------------------------------------------------------- sending (rykkerprint)

    public function testSendingALinkReminderMailsTheLinkAndMarksItSent(): void
    {
        $r = StripeReminderFixtures::linkReminder();
        StripeReminderFixtures::linkRow($r['reminder'], ['status' => 'draft']);
        $pdf = $this->pdf($r['reminder']);
        $result = formSendReminderEntry($r['reminder'], $pdf, 'jens@stripe-tests.invalid', 'Dansk', 6, $this->classicSender());
        $rykkerNr = StripeFixtures::row('ordrer', $r['reminder'])['fakturanr'];
        $this->assertTrue($result['sent']);
        $this->assertSame('link', $result['channel']);
        $this->assertSame("Rykker $rykkerNr er sendt med betalingslink til jens@stripe-tests.invalid.", $result['message']);
        $this->assertCount(1, StripeTestTransport::$mails);
        $this->assertSame([], $this->classic);
        $row = StripeReminderFixtures::linkRowOf($r['reminder']);
        $this->assertSame('sent', $row['status']);
        $this->assertSame('link', $row['channel']);
        $this->assertSame('jens@stripe-tests.invalid', $row['mailed_to']);
        $this->assertNotEmpty($row['mailed_at']);
    }

    public function testSendingFallsBackToTheClassicPdfMailWhenTheLinkMailFails(): void
    {
        $r = StripeReminderFixtures::linkReminder();
        StripeReminderFixtures::linkRow($r['reminder'], ['status' => 'draft']);
        $pdf = $this->pdf($r['reminder']);
        StripeTestTransport::$mailSucceeds = false;
        $result = formSendReminderEntry($r['reminder'], $pdf, 'jens@stripe-tests.invalid', 'Dansk', 6, $this->classicSender());
        $rykkerNr = StripeFixtures::row('ordrer', $r['reminder'])['fakturanr'];
        $this->assertTrue($result['sent']);
        $this->assertSame('classic', $result['channel']);
        $this->assertSame("Rykker $rykkerNr er sendt som almindelig PDF-mail, fordi mailen med betalingslink ikke kunne sendes.", $result['message']);
        $this->assertCount(1, $this->classic);
        [$id, $file, $email, $language, $form] = $this->classic[0];
        $this->assertSame($r['reminder'], (int)$id);
        $this->assertSame($pdf, $file);
        $this->assertSame('jens@stripe-tests.invalid', $email);
        $this->assertSame('Dansk', $language);
        $this->assertSame(6, (int)$form);
        $row = StripeReminderFixtures::linkRowOf($r['reminder']);
        $this->assertSame('sent', $row['status']);
        $this->assertSame('classic', $row['channel']);
    }

    public function testSendingChecksTheLawFirstAndSendsClassicRemindersAsBefore(): void
    {
        // A classic reminder goes to the classic sender, as rykkerprint did.
        $classic = StripeReminderFixtures::linkReminder([], ['udskriv_til' => 'email']);
        $pdf = $this->pdf($classic['reminder']);
        $result = formSendReminderEntry($classic['reminder'], $pdf, 'jens@stripe-tests.invalid', 'Dansk', 6, $this->classicSender());
        $this->assertSame('classic', $result['channel']);
        $this->assertCount(1, $this->classic);
        $this->assertSame($pdf, $this->classic[0][1]);
        $this->assertSame([], StripeTestTransport::$mails);
        $this->assertNull(StripeReminderFixtures::linkRowOf($classic['reminder']));

        // L1 applies to classic reminders too.
        $this->classic = [];
        $high = StripeReminderFixtures::linkReminder([], ['udskriv_til' => 'email'], ['fee' => 150]);
        $result = formSendReminderEntry($high['reminder'], $this->pdf($high['reminder']), 'jens@stripe-tests.invalid', 'Dansk', 6, $this->classicSender());
        $this->assertFalse($result['sent']);
        $this->assertStringContainsString('100,00 kr.', $result['message']);
        $this->assertSame([], $this->classic);

        // L3: a 2nd reminder with a fee 2 days after the 1st (today is 25.01.2026).
        $r = StripeReminderFixtures::linkReminder();
        StripeReminderFixtures::linkRow($r['reminder'], ['mailed_at' => '2026-01-23 10:00:00']);
        $second = StripeReminderFixtures::nextLevel($r, 2, '2026-01-25');
        StripeReminderFixtures::linkRow($second, ['status' => 'draft']);
        $result = formSendReminderEntry($second, $this->pdf($second), 'jens@stripe-tests.invalid', 'Dansk', 7, $this->classicSender());
        $this->assertFalse($result['sent']);
        $this->assertStringContainsString('2. rykker kan tidligst sendes den 02.02.2026 (mindst 10 dage efter forrige rykker, renteloven § 9 b).', $result['message']);
        $this->assertSame([], StripeTestTransport::$mails);
        $this->assertSame([], $this->classic);
        $this->assertSame('draft', StripeReminderFixtures::linkRowOf($second)['status']);

        // Everything paid in the meantime: not sent at all.
        $p = StripeReminderFixtures::linkReminder();
        StripeReminderFixtures::linkRow($p['reminder'], ['status' => 'draft']);
        StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1' WHERE id = $1", [StripeReminderFixtures::openpost($p['invoice'])['id']]);
        $result = formSendReminderEntry($p['reminder'], $this->pdf($p['reminder']), 'jens@stripe-tests.invalid', 'Dansk', 6, $this->classicSender());
        $this->assertFalse($result['sent']);
        $this->assertSame('Rykker ' . StripeFixtures::row('ordrer', $p['reminder'])['fakturanr'] . ' er ikke sendt: alle fakturaer på rykkeren er betalt eller under betaling.', $result['message']);
        $this->assertSame([], StripeTestTransport::$mails);
        $this->assertSame([], $this->classic);
    }

    // ---------------------------------------------------------------- dunning protection and closing

    public function testCardOnFileInvoiceStaysOutOfRemindersUntilThreeDeclinesAndThreeDays(): void
    {
        // The SQL predicate and the PHP clock read the same day.
        $today = (string)pg_fetch_result(StripeTestEnvironment::query('SELECT CURRENT_DATE'), 0, 0);
        $GLOBALS['stripe_charge_today'] = $today;
        $ago = static fn(int $days): string => (new DateTimeImmutable($today))->modify("-$days days")->format('Y-m-d');
        $debtor = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'stripe_date' => '2025-12-01']);
        $card = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Stripe']);
        $link = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Link']);
        $beforeEnrolment = StripeReminderFixtures::invoiceOn($debtor, '2025-11-20', ['udskriv_til' => 'Stripe']);
        $this->assertFalse($this->remindable($card), 'not charged yet');
        $this->assertTrue($this->remindable($link));
        $this->assertTrue($this->remindable($beforeEnrolment), 'dated before the customer enrolled');

        $decline = fn(string $status, string $source, string $error, string $day, array $o = []) => $this->payment($card, $status, $source,
            $o + ['last_error' => $error, 'attempt_date' => $day, 'created_at' => $day . ' 03:00:00']);
        $decline('failed', 'auto', 'card_declined', $ago(10));
        $decline('failed', 'auto', 'insufficient_funds', $ago(7));
        // A backup card, a technical error and an authentication request are no primary declines.
        $decline('failed', 'backup', 'card_declined', $ago(7));
        $decline('canceled', 'auto', 'technical_error', $ago(6));
        $this->assertFalse($this->remindable($card), 'two declines');
        $third = $decline('canceled', 'auto', 'card_declined', $ago(1));
        $this->assertFalse($this->remindable($card), 'three declines, the last one yesterday');
        StripeTestEnvironment::query('UPDATE stripe_payments SET attempt_date = $1, created_at = $2 WHERE id = $3', [$ago(4), $ago(4) . ' 03:00:00', $third]);
        $this->assertTrue($this->remindable($card), 'three declines, the last one 4 days ago');

        // A prepared reminder with the held invoice is held as well.
        $held = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Stripe']);
        $rykker = StripeReminderFixtures::reminder($debtor, [$held]);
        $this->assertFalse((bool)pg_fetch_assoc(StripeTestEnvironment::query('SELECT o.id FROM ordrer o WHERE o.id = $1 AND ' . stripe_dunning_reminder_sql('o'), [$rykker])));

        // Only while the charge job will try the invoice. After an authentication request it never retries (the open request
        // itself is protected for 14 days, testSavedCardAuthenticationHoldsRemindersForFourteenDays).
        $asked = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Stripe']);
        $this->assertFalse($this->remindable($asked));
        $this->payment($asked, 'canceled', 'auto', ['last_error' => 'authentication_required', 'attempt_date' => $ago(20),
            'created_at' => $ago(20) . ' 03:00:00', 'action_required_at' => $ago(20) . ' 03:00:00']);
        $this->assertTrue($this->remindable($asked), 'authentication was asked for: the job stops');
        $pageAction = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Stripe']);
        $this->payment($pageAction, 'canceled', 'page', ['created_at' => $ago(10) . ' 03:00:00', 'action_required_at' => $ago(10) . ' 03:00:00']);
        $this->assertTrue($this->remindable($pageAction), 'the customer left the bank check on the page');
        // No saved card, a credit on the account or a partial payment: the job does not collect it.
        $noCard = StripeFixtures::invoice(StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => null, 'stripe_date' => '2025-12-01']), ['udskriv_til' => 'Stripe']);
        $this->assertTrue($this->remindable($noCard), 'no saved card');
        foreach ([125, 249, 250] as $ore) {
            $small = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Stripe', 'sum' => $ore / 100, 'moms' => 0]);
            $this->assertSame($ore < 250, $this->remindable($small), 'the hold must match the card minimum');
        }
        $creditDebtor = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'stripe_date' => '2025-12-01']);
        $credited = StripeFixtures::invoice($creditDebtor, ['udskriv_til' => 'Stripe']);
        $this->assertFalse($this->remindable($credited));
        StripeFixtures::insert('openpost', ['konto_id' => $creditDebtor, 'konto_nr' => StripeFixtures::row('adresser', $creditDebtor)['kontonr'],
            'faktnr' => 'K' . $credited, 'amount' => '-50.000', 'udlignet' => '0', 'transdate' => $ago(30), 'forfaldsdate' => $ago(30),
            'valuta' => 'DKK', 'valutakurs' => '100.000', 'projekt' => '']);
        $this->assertTrue($this->remindable($credited), 'a credit on the account');
        $partial = StripeFixtures::invoice(StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'stripe_date' => '2025-12-01']), ['udskriv_til' => 'Stripe']);
        StripeTestEnvironment::query("UPDATE openpost SET amount = '200.000' WHERE id = $1", [(int)StripeReminderFixtures::openpost($partial)['id']]);
        $this->assertTrue($this->remindable($partial), 'partly paid');
    }

    public function testAnOpenItemTheChargeDoesNotSettleIsNotHeldFromReminders(): void
    {
        $today = (string)pg_fetch_result(StripeTestEnvironment::query('SELECT CURRENT_DATE'), 0, 0);
        $GLOBALS['stripe_charge_today'] = $today;
        $debtor = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'stripe_date' => '2025-12-01']);
        foreach (['340.000' => false, '339.995' => false, '340.004' => false, '339.99' => true, '340.01' => true, '340.005' => true] as $amount => $remindable) {
            $invoice = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Stripe']);
            StripeTestEnvironment::query('UPDATE openpost SET amount = $1 WHERE id = $2', [$amount, (int)StripeReminderFixtures::openpost($invoice)['id']]);
            $this->assertSame(!$remindable, stripe_auto_charge_allowed(stripe_invoice_load($invoice)) && stripe_invoice_payable(stripe_invoice_load($invoice)), $amount . ': the charge job');
            $this->assertSame($remindable, $this->remindable($invoice), $amount . ': the hold follows the charge job');
        }
        // The invoice split in two open items: the charge job leaves it to the bookkeeper.
        $split = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Stripe']);
        $open = StripeReminderFixtures::openpost($split);
        StripeTestEnvironment::query("UPDATE openpost SET amount = '170.000' WHERE id = $1", [(int)$open['id']]);
        StripeFixtures::insert('openpost', ['konto_id' => (int)$open['konto_id'], 'konto_nr' => $open['konto_nr'], 'faktnr' => $open['faktnr'],
            'amount' => '170.000', 'udlignet' => '0', 'transdate' => $open['transdate'], 'forfaldsdate' => $open['forfaldsdate'],
            'valuta' => 'DKK', 'valutakurs' => '100.000', 'projekt' => '']);
        $this->assertFalse(stripe_invoice_payable(stripe_invoice_load($split)));
        $this->assertTrue($this->remindable($split), 'two open items');
        // Within half an øre only with the order's project; the exact amount whatever the project.
        foreach ([['', '', '340.004', false], ['821', '821', '340.004', false], ['', '821', '340.004', true],
            ['821', '', '339.995', true], ['', '821', '340.000', false], ['821', null, '340.000', false]] as [$orderProject, $itemProject, $amount, $remindable]) {
            $label = "order '$orderProject', item '" . ($itemProject ?? 'NULL') . "', $amount";
            $invoice = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Stripe', 'projekt' => $orderProject]);
            StripeTestEnvironment::query('UPDATE openpost SET amount = $1, projekt = $2 WHERE id = $3', [$amount, $itemProject, (int)StripeReminderFixtures::openpost($invoice)['id']]);
            $this->assertSame(!$remindable, stripe_auto_charge_allowed(stripe_invoice_load($invoice)) && stripe_invoice_payable(stripe_invoice_load($invoice)), $label . ': the charge job');
            $this->assertSame($remindable, $this->remindable($invoice), $label . ': the hold follows the charge job');
        }
    }

    public function testSavedCardAuthenticationHoldsRemindersForFourteenDays(): void
    {
        $debtor = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'stripe_date' => '2025-12-01']);
        $invoice = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Link']);
        $id = $this->payment($invoice, 'requires_action', 'auto');
        foreach ([['auto', '13 days', false], ['auto', '15 days', true], ['manual', '13 days', false], ['manual', '15 days', true],
            ['backup', '15 days', true], ['page', '1 day', false], ['page', '4 days', true]] as [$source, $age, $expected]) {
            StripeTestEnvironment::query('UPDATE stripe_payments SET source = $1, created_at = CURRENT_TIMESTAMP - $2::interval WHERE id = $3', [$source, $age, $id]);
            $this->assertSame($expected, $this->remindable($invoice), "$source $age");
        }
    }

    public function testPaidLinkRemindersAreClosedLikeAfslut(): void
    {
        $debtor = StripeFixtures::debtor();
        $a = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Link']);
        $b = StripeReminderFixtures::invoiceOn($debtor, '2026-01-12');
        $rykker = StripeReminderFixtures::reminder($debtor, [$a, $b], ['status' => 4]);
        StripeReminderFixtures::linkRow($rykker);
        $other = StripeReminderFixtures::linkReminder([], [], ['status' => 4]);
        StripeReminderFixtures::linkRow($other['reminder']);
        $classic = StripeReminderFixtures::linkReminder([], ['udskriv_til' => 'email'], ['status' => 4]);
        foreach ([$other['invoice'], $classic['invoice']] as $paid) {
            StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on' WHERE id = $1", [$paid]);
        }

        StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1' WHERE id = $1", [StripeReminderFixtures::openpost($a)['id']]);
        $this->assertSame(0, stripe_reminder_close_paid($debtor), 'one invoice is still open');
        $this->assertSame('-', StripeFixtures::row('ordrer', $rykker)['betalt']);
        $this->payment($b, 'succeeded', 'page', ['succeeded_at' => '2026-01-24 12:00:00']);
        $this->assertSame(1, stripe_reminder_close_paid($debtor));
        $this->assertSame('on', StripeFixtures::row('ordrer', $rykker)['betalt']);
        $this->assertSame('-', StripeFixtures::row('ordrer', $other['reminder'])['betalt'], 'only this customer was looked at');
        $this->assertSame(0, stripe_reminder_close_paid($debtor));

        $this->assertSame(1, stripe_reminder_close_paid());
        $this->assertSame('on', StripeFixtures::row('ordrer', $other['reminder'])['betalt']);
        $this->assertSame('-', StripeFixtures::row('ordrer', $classic['reminder'])['betalt'], 'classic reminders are closed with Afslut');
    }

    // ---------------------------------------------------------------- review fixes (20261109)

    public function testRykAlleAndNyRykkerLeaveAReminderWhoseInvoicesAreAllPaid(): void
    {
        $debtor = StripeFixtures::debtor();
        $paid = StripeFixtures::invoice($debtor, ['udskriv_til' => 'email']);
        $open = StripeReminderFixtures::invoiceOn($debtor, '2026-01-12', ['udskriv_til' => 'email']);
        $rykker = StripeReminderFixtures::reminder($debtor, [$paid, $open], ['date' => '2026-01-05']);
        $nr = StripeFixtures::row('ordrer', $rykker)['fakturanr'];
        $this->assertSame(['invoices' => 2, 'unpaid' => 2], reminder_invoice_payment($rykker));
        $this->assertSame('', reminder_escalation_skip($rykker, true));
        // One settled by bank: the other is still owed.
        StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1' WHERE id = $1", [StripeReminderFixtures::openpost($paid)['id']]);
        $this->assertSame(['invoices' => 2, 'unpaid' => 1], reminder_invoice_payment($rykker));
        $this->assertSame('', reminder_escalation_skip($rykker, true));
        $this->assertTrue(reminder_openpost_paid(StripeReminderFixtures::openpost($paid)));
        $this->assertFalse(reminder_openpost_paid(StripeReminderFixtures::openpost($open)));
        // The other one marked paid on the invoice: neither booked ("Ryk alle") nor escalated ("Ny rykker"); Afslut closes it.
        StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on' WHERE id = $1", [$open]);
        $this->assertTrue(reminder_openpost_paid(StripeReminderFixtures::openpost($open)));
        $this->assertSame(['invoices' => 2, 'unpaid' => 0], reminder_invoice_payment($rykker));
        $this->assertSame('paid', reminder_escalation_skip($rykker, true));
        $this->assertSame('paid', reminder_escalation_skip($rykker, false));
        $this->assertSame("Ingen ny rykker for $nr: alle fakturaer på rykkeren er betalt. Luk den med Afslut.", reminder_skip_notice($nr, 'paid'));

        // The fee of the previous level (an open item) is no invoice: its 2nd reminder is also left when the invoice is paid.
        $r = StripeReminderFixtures::linkReminder([], ['udskriv_til' => 'email'], ['status' => 4, 'date' => '2026-01-05']);
        $fee = $this->priorFeeOpenpost($r['reminder']);
        $second = StripeReminderFixtures::nextLevel($r, 2, '2026-01-20', ['openposts' => [$fee]]);
        $this->assertSame(['invoices' => 1, 'unpaid' => 1], reminder_invoice_payment($second));
        StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1' WHERE id = $1", [StripeReminderFixtures::openpost($r['invoice'])['id']]);
        $this->assertSame('paid', reminder_escalation_skip($second, false));
    }

    public function testRykAlleDoesNotBookAReminderDatedOutsideTheActiveFiscalYear(): void
    {
        $regnaar = $GLOBALS['regnaar'];
        try {
            StripeFixtures::insert('grupper', ['art' => 'RA', 'kodenr' => 905, 'box1' => '01', 'box2' => '2026', 'box3' => '12', 'box4' => '2026']);
            $GLOBALS['regnaar'] = '905';
            $now = StripeReminderFixtures::linkReminder([], ['udskriv_til' => 'email'], ['date' => '2026-01-05']);
            $this->assertTrue(reminder_in_active_year($now['reminder']));
            $this->assertSame('', reminder_escalation_skip($now['reminder'], true));
            $december = StripeReminderFixtures::linkReminder([], ['udskriv_til' => 'email'], ['date' => '2025-12-20']);
            $this->assertFalse(reminder_in_active_year($december['reminder']));
            $this->assertSame('year', reminder_escalation_skip($december['reminder'], true));
            $this->assertSame('', reminder_escalation_skip($december['reminder'], false), 'a new level posts nothing');
            // Booking a link reminder whose fee goes on the next invoice posts nothing: the fiscal year does not hold it.
            $carried = StripeReminderFixtures::linkReminder([], [], ['date' => '2025-12-20']);
            StripeReminderFixtures::linkRow($carried['reminder']);
            $this->assertFalse(reminder_in_active_year($carried['reminder']));
            $this->assertSame('', reminder_escalation_skip($carried['reminder'], true));
            StripeTestEnvironment::query("UPDATE stripe_reminders SET fee_mode = 'classic' WHERE rykker_id = $1", [$carried['reminder']]);
            $this->assertSame('year', reminder_escalation_skip($carried['reminder'], true), 'its fee is posted as an open item');
            // Outside the year comes first: a paid reminder that cannot be booked is not booked.
            StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on' WHERE id = $1", [$december['invoice']]);
            $this->assertSame('year', reminder_escalation_skip($december['reminder'], true));
            $this->assertSame('paid', reminder_escalation_skip($december['reminder'], false));
            $nr = StripeFixtures::row('ordrer', $december['reminder'])['fakturanr'];
            $this->assertSame("Ingen ny rykker for $nr: rykkerdatoen ligger uden for det aktive regnskabsår.", reminder_skip_notice($nr, 'year'));
            $GLOBALS['regnaar'] = '';
            $this->assertTrue(reminder_in_active_year($december['reminder']), 'no active fiscal year: not checked');
        } finally {
            $GLOBALS['regnaar'] = $regnaar;
        }
    }

    public function testFeeAndInterestOnTheSameItemCountOnlyTheLastLineAsFee(): void
    {
        StripeTestEnvironment::query("UPDATE formularer SET yb = xb WHERE beskrivelse = 'GEBYR'");
        StripeReminderFixtures::$items['interest'] = $this->items['fee'];   // the fixture's interest lines on the fee item
        $r = StripeReminderFixtures::linkReminder([], [], ['fee' => 80, 'interest' => 25]);
        $c = reminder_charge_lines($r['reminder']);
        $this->assertEqualsWithDelta(80.0, $c['fee'], 0.001);
        $this->assertEqualsWithDelta(25.0, $c['interest'], 0.001);
        $this->assertSame('', reminder_legal_check($r['reminder'], '2026-01-20'), 'interest is not a fee (L1)');

        // A 2nd reminder with interest only: ny_rykker.php adds the fee line (here at 0) last, without a posnr.
        StripeReminderFixtures::linkRow($r['reminder'], ['mailed_at' => '2026-01-20 10:00:00']);
        $second = StripeReminderFixtures::nextLevel($r, 2, '2026-01-22', ['fee' => 0, 'interest' => 5]);
        StripeFixtures::insert('ordrelinjer', ['ordre_id' => $second, 'vare_id' => $this->items['fee'], 'beskrivelse' => 'Rykkergebyr',
            'antal' => 1, 'pris' => 0, 'rabat' => 0, 'serienr' => '2026-01-22']);
        $c = reminder_charge_lines($second);
        $this->assertEqualsWithDelta(0.0, $c['fee'], 0.001);
        $this->assertEqualsWithDelta(5.0, $c['interest'], 0.001);
        $this->assertSame('', reminder_legal_check($second, '2026-01-22'), 'no fee: not held back by the 10 days (L3)');
    }

    public function testEarlierClassicFeeIsABankRowOnTheNextLinkReminder(): void
    {
        StripeTestEnvironment::setting('stripe', 'reminder_fee_next', 'off');
        StripeTestEnvironment::query("UPDATE adresser SET bank_reg = '1234', bank_konto = '5678901' WHERE art = 'S'");
        $r = StripeReminderFixtures::linkReminder([], [], ['status' => 4, 'date' => '2026-01-05']);
        StripeReminderFixtures::linkRow($r['reminder'], ['fee_mode' => 'classic', 'mailed_at' => '2026-01-05 10:00:00']);
        $fee = $this->priorFeeOpenpost($r['reminder']);   // booked: bogfor_nu() posted the fee as an open item
        $firstNr = StripeFixtures::row('ordrer', $r['reminder'])['fakturanr'];
        $second = StripeReminderFixtures::nextLevel($r, 2, '2026-01-25', ['openposts' => [$fee]]);
        $lines = stripe_reminder_lines($second);
        $this->assertSame(0, $lines['unmapped']);
        $this->assertSame([['openpost_id' => $fee, 'faktnr' => $firstNr, 'amount' => 100.0, 'level' => 1, 'with_interest' => false]], $lines['prior_fees']);
        $this->assertSame('', stripe_reminder_eligible($second), 'still a link reminder');
        $this->assertTrue(stripe_reminder_register($second));
        $this->assertSame('classic', StripeReminderFixtures::linkRowOf($second)['fee_mode']);

        $plain = self::plain($this->mailReminder($second)['html']);
        $this->assertMatchesRegularExpression('/Rykkergebyr fra 1\. rykker 100,00 kr\. betales via bankoverførsel/u', $plain);
        $this->assertMatchesRegularExpression('/Rykkergebyr 100,00 kr\. betales via bankoverførsel/u', $plain);   // I19: this fee too
        $this->assertMatchesRegularExpression('/At betale nu 540,00 kr\./u', $plain);   // 340 + the earlier fee 100 + this fee 100
        $secondNr = StripeFixtures::row('ordrer', $second)['fakturanr'];
        $this->assertStringContainsString("Rykkergebyr: reg. 1234 · konto 5678901 · reference $firstNr, $secondNr", $plain);

        // Paid in the meantime: no longer on the mail.
        StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1' WHERE id = $1", [$fee]);
        $plain = self::plain($this->mailReminder($second)['html']);
        $this->assertStringNotContainsString('Rykkergebyr fra 1. rykker', $plain);
        $this->assertMatchesRegularExpression('/At betale nu 440,00 kr\./u', $plain);

        // The fee of another chain's reminder is still unmapped.
        $other = StripeReminderFixtures::linkReminder([], [], ['status' => 4]);
        $otherFee = $this->priorFeeOpenpost($other['reminder']);
        $foreign = StripeReminderFixtures::reminder($other['debtor'], [$other['invoice']], ['level' => 2, 'openposts' => [$otherFee]]);
        $this->assertSame([], stripe_reminder_lines($foreign)['prior_fees']);
        $this->assertSame(1, stripe_reminder_lines($foreign)['unmapped']);
    }

    public function testResendingKeepsTheFirstMailedAtForTheTenDaysAndTheWaiver(): void
    {
        $r = StripeReminderFixtures::linkReminder(['stripe' => 'on', 'stripe_pm_id' => 'pm_new', 'stripe_date' => '2026-01-21'], [], ['status' => 4]);
        StripeReminderFixtures::linkRow($r['reminder'], ['mailed_at' => '2026-01-20 15:30:00', 'mailed_to' => 'first@stripe-tests.invalid']);
        stripe_reminder_mark_sent($r['reminder'], 'classic', 'jens@stripe-tests.invalid');   // sent again on 25.01.2026
        $row = StripeReminderFixtures::linkRowOf($r['reminder']);
        $this->assertSame('2026-01-20 15:30:00', $row['mailed_at']);
        $this->assertSame('classic', $row['channel']);
        $this->assertSame('jens@stripe-tests.invalid', $row['mailed_to']);
        $second = StripeReminderFixtures::nextLevel($r, 2, '2026-01-31');
        $this->assertSame('2026-01-20', reminder_previous_sent_date($second));
        $this->assertSame('', reminder_legal_check($second, '2026-01-30'));
        $this->assertSame('enrolled', stripe_reminder_waiver_reason($r['debtor'], ['udskriv_til' => 'Link', 'pbs' => ''], $row['mailed_at']));
    }

    public function testCardSavedOnTheReminderDayCountsByTheCopenhagenDay(): void
    {
        $r = StripeReminderFixtures::linkReminder(['stripe' => 'on', 'stripe_pm_id' => 'pm_new', 'stripe_date' => '2025-12-01'], [], ['status' => 4]);
        $order = ['udskriv_til' => 'Link', 'pbs' => ''];
        $this->assertSame('', stripe_reminder_waiver_reason($r['debtor'], $order, '2026-01-20 15:30:00'));
        // The database clock may run behind Copenhagen time: the payment's day is what counts.
        $id = $this->payment($r['invoice'], 'succeeded', 'page', ['save_card' => true, 'attempt_date' => '2026-01-20',
            'created_at' => '2026-01-20 14:35:00', 'succeeded_at' => '2026-01-20 14:35:00']);
        $this->assertSame('card_saved', stripe_reminder_waiver_reason($r['debtor'], $order, '2026-01-20 15:30:00'));
        StripeTestEnvironment::query("UPDATE stripe_payments SET attempt_date = '2026-01-19', created_at = '2026-01-19 22:00:00' WHERE id = $1", [$id]);
        $this->assertSame('', stripe_reminder_waiver_reason($r['debtor'], $order, '2026-01-20 15:30:00'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCarriedFeeIsNotPutOnACashOrCardSale(): void
    {
        StripeReminderFixtures::orderFunctionDoubles();
        $GLOBALS['stripe_charge_today'] = self::AFTER_DEADLINE;
        $r = StripeReminderFixtures::linkReminder([], [], ['status' => 4]);
        StripeReminderFixtures::linkRow($r['reminder']);
        foreach (['Kontant', 'kreditkort'] as $terms) {
            $sale = StripeFixtures::order($r['debtor'], ['levdate' => '2026-02-01', 'betalingsbet' => $terms]);
            $this->assertSame('', stripe_reminder_fee_bogfor($sale, 'DO', 25, false, false), $terms);
            $this->assertCount(0, $this->feeLines($sale), $terms);
            $this->assertEqualsWithDelta(272.0, (float)StripeFixtures::row('ordrer', $sale)['sum'], 0.001, $terms);
            $this->assertSame('sent', StripeReminderFixtures::linkRowOf($r['reminder'])['status'], $terms);
        }
        $account = StripeFixtures::order($r['debtor'], ['levdate' => '2026-02-01']);
        $this->assertSame('', stripe_reminder_fee_bogfor($account, 'DO', 25, false, false));
        $this->assertCount(1, $this->feeLines($account));
        $this->assertSame('charged', StripeReminderFixtures::linkRowOf($r['reminder'])['status']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCarriedFeeFollowsTheReminderLinesAndGetsTheOrdersProject(): void
    {
        StripeReminderFixtures::orderFunctionDoubles();
        $GLOBALS['stripe_charge_today'] = self::AFTER_DEADLINE;
        // The fee line deleted in rykker.php after the mail: nothing is charged and the row is waived.
        $gone = StripeReminderFixtures::linkReminder([], [], ['status' => 4]);
        StripeReminderFixtures::linkRow($gone['reminder']);
        StripeTestEnvironment::query('DELETE FROM ordrelinjer WHERE ordre_id = $1 AND vare_id = $2', [$gone['reminder'], $this->items['fee']]);
        $next = StripeFixtures::order($gone['debtor'], ['levdate' => '2026-02-01']);
        $this->assertSame('', stripe_reminder_fee_bogfor($next, 'DO', 25, false, false));
        $this->assertCount(0, $this->feeLines($next));
        $row = StripeReminderFixtures::linkRowOf($gone['reminder']);
        $this->assertSame('waived', $row['status']);
        $this->assertSame('removed', trim((string)$row['waived_reason']));

        // Lowered: the lower amount; raised after the mail: the mailed amount.
        foreach ([80 => 80.0, 150 => 100.0] as $price => $charged) {
            $s = StripeReminderFixtures::linkReminder([], [], ['status' => 4]);
            StripeReminderFixtures::linkRow($s['reminder']);
            StripeTestEnvironment::query('UPDATE ordrelinjer SET pris = $1 WHERE ordre_id = $2 AND vare_id = $3', [$price, $s['reminder'], $this->items['fee']]);
            $next = StripeFixtures::order($s['debtor'], ['levdate' => '2026-02-01']);
            $this->assertSame('', stripe_reminder_fee_bogfor($next, 'DO', 25, false, false));
            $lines = $this->feeLines($next);
            $this->assertCount(1, $lines, "fee line at $price");
            $this->assertEqualsWithDelta($charged, (float)$lines[0]['pris'], 0.001, "fee line at $price");
        }

        // The order's project goes on the fee line too, so bogfor_nu() sees one project on the invoice's open item.
        $p = StripeReminderFixtures::linkReminder([], [], ['status' => 4]);
        StripeReminderFixtures::linkRow($p['reminder']);
        $next = StripeFixtures::order($p['debtor'], ['levdate' => '2026-02-01', 'projekt' => '7']);
        $this->assertSame('', stripe_reminder_fee_bogfor($next, 'DO', 25, false, false));
        $this->assertSame('7', trim((string)$this->feeLines($next)[0]['projekt']));
        // Without one on the order: the one project of its item lines.
        $q = StripeReminderFixtures::linkReminder([], [], ['status' => 4]);
        StripeReminderFixtures::linkRow($q['reminder']);
        $next = StripeFixtures::order($q['debtor'], ['levdate' => '2026-02-01']);
        StripeTestEnvironment::query("UPDATE ordrelinjer SET vare_id = $1, projekt = '8' WHERE ordre_id = $2", [StripeReminderFixtures::item('Vare', 50), $next]);
        $this->assertSame('', stripe_reminder_fee_bogfor($next, 'DO', 25, false, false));
        $this->assertSame('8', trim((string)$this->feeLines($next)[0]['projekt']));
    }

    public function testABookedDraftStaysALinkReminderAndPrintingItSendsIt(): void
    {
        $r = StripeReminderFixtures::linkReminder();
        $this->assertTrue(stripe_reminder_register($r['reminder']));
        StripeTestEnvironment::query('UPDATE ordrer SET status = 4 WHERE id = $1', [$r['reminder']]);   // booked before it was sent
        stripe_reminder_unregister($r['reminder']);   // "Send pr. mail" unticked in rykker.php
        $this->assertSame('draft', StripeReminderFixtures::linkRowOf($r['reminder'])['status'], 'its fee was not posted: it must still be carried');
        formReminderPrinted($r['reminder']);   // printed on paper
        $row = StripeReminderFixtures::linkRowOf($r['reminder']);
        $this->assertSame('sent', $row['status']);
        $this->assertSame('classic', $row['channel']);
        $this->assertNotEmpty($row['mailed_at']);
        formReminderPrinted($r['reminder']);
        $this->assertSame($row['mailed_at'], StripeReminderFixtures::linkRowOf($r['reminder'])['mailed_at'], 'printed again: unchanged');

        // An unbooked draft is made classic, and so is a deleted reminder's draft.
        $u = StripeReminderFixtures::linkReminder();
        $this->assertTrue(stripe_reminder_register($u['reminder']));
        stripe_reminder_unregister($u['reminder']);
        $this->assertNull(StripeReminderFixtures::linkRowOf($u['reminder']));
        $d = StripeReminderFixtures::linkReminder();
        $this->assertTrue(stripe_reminder_register($d['reminder']));
        StripeTestEnvironment::query('DELETE FROM ordrelinjer WHERE ordre_id = $1', [$d['reminder']]);
        StripeTestEnvironment::query('DELETE FROM ordrer WHERE id = $1', [$d['reminder']]);
        stripe_reminder_unregister($d['reminder']);
        $this->assertNull(StripeReminderFixtures::linkRowOf($d['reminder']));
    }

    public function testReminderPdfSaysWhereTheFeeWentAndLeavesOutWhatTheMailLeavesOut(): void
    {
        $debtor = StripeFixtures::debtor();
        $paid = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Link']);
        $open = StripeReminderFixtures::invoiceOn($debtor, '2026-01-12');
        $rykker = StripeReminderFixtures::reminder($debtor, [$paid, $open], ['status' => 4]);
        $this->assertSame(['carried' => false, 'suffix' => '', 'skip' => []], formReminderPdfTreatment($rykker), 'a classic reminder');

        StripeReminderFixtures::linkRow($rykker);
        StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1' WHERE id = $1", [StripeReminderFixtures::openpost($paid)['id']]);
        $t = formReminderPdfTreatment($rykker);
        $this->assertTrue($t['carried']);
        $this->assertSame(' – opkræves på din næste faktura', $t['suffix']);
        $this->assertSame([(int)StripeReminderFixtures::openpost($paid)['id']], $t['skip'], 'settled: not on the mail, not on the PDF');

        // Sent again after the fee was charged or waived: still out of "I alt", and the PDF says why.
        $next = StripeFixtures::invoice($debtor);
        StripeTestEnvironment::query("UPDATE stripe_reminders SET status = 'charged', fee_ordre_id = $1 WHERE rykker_id = $2", [$next, $rykker]);
        $t = formReminderPdfTreatment($rykker);
        $this->assertTrue($t['carried']);
        $this->assertSame(' – opkrævet på faktura ' . StripeFixtures::row('ordrer', $next)['fakturanr'], $t['suffix']);
        $this->assertSame([], $t['skip'], 'no longer sent as a link reminder');
        StripeTestEnvironment::query("UPDATE stripe_reminders SET status = 'waived', fee_ordre_id = NULL WHERE rykker_id = $1", [$rykker]);
        $t = formReminderPdfTreatment($rykker);
        $this->assertTrue($t['carried']);
        $this->assertSame(' – frafaldet', $t['suffix']);
        // A fee posted as an open item is part of "I alt" as before.
        StripeTestEnvironment::query("UPDATE stripe_reminders SET status = 'sent', fee_mode = 'classic' WHERE rykker_id = $1", [$rykker]);
        $this->assertFalse(formReminderPdfTreatment($rykker)['carried']);
    }

    public function testPdfDeadlineUsesTheSameDateAsTheMailAndPreservesClassicForms(): void
    {
        $d = StripeReminderFixtures::linkReminder();
        $rid = $d['reminder'];
        $text = 'Beløbet bedes indbetalt inden 8 dage til banken.';
        $this->assertSame($text, formReminderDeadlineText($rid, $text));
        StripeReminderFixtures::linkRow($rid);
        StripeTestEnvironment::query("UPDATE ordrer SET fakturadate = '2026-11-11', ordredate = '2026-11-11', betalingsdage = 23 WHERE id = $1", [$rid]);
        $this->assertSame('Beløbet bedes indbetalt senest 04.12.2026 til banken.', formReminderDeadlineText($rid, $text));
        $this->assertSame('Betal senest 04.12.2026 til banken.', formReminderDeadlineText($rid, 'Betal straks dage til banken.'));
        $this->assertSame('Betal senest 04.12.2026.', formReminderDeadlineText($rid, 'Betal straks.'));
        $this->assertSame('Betal senest 04.12.2026.', formReminderDeadlineText($rid, 'Betal senest $rykker_frist.'));
        StripeTestEnvironment::query("UPDATE ordrer SET betalingsdage = 13 WHERE id = $1", [$rid]);
        $this->assertSame('Betal senest 24.11.2026.', formReminderDeadlineText($rid, 'Betal straks.'));
        $warning = 'Såfremt beløbet ikke betales inden 10 dage, sendes sagen til inkasso';
        $this->assertSame($warning, formReminderDeadlineText($rid, $warning));
        $this->assertSame('Kontakt os ved spørgsmål.', formReminderDeadlineText($rid, 'Kontakt os ved spørgsmål.'));
    }

    public function testFrafaldNeedsTheFormTokenAndAFeeNotYetCharged(): void
    {
        $r = StripeReminderFixtures::linkReminder([], [], ['status' => 4]);
        StripeReminderFixtures::linkRow($r['reminder']);
        $invalid = 'Ugyldig eller udløbet formular - genindlæs siden og prøv igen.';
        $this->assertSame($invalid, stripe_reminder_waive_request($r['reminder'], 'forged', 'token'));
        $this->assertSame($invalid, stripe_reminder_waive_request($r['reminder'], '', ''));
        $this->assertSame('sent', StripeReminderFixtures::linkRowOf($r['reminder'])['status']);
        $this->assertSame('Rykkergebyret er frafaldet.', stripe_reminder_waive_request($r['reminder'], 'token', 'token'));
        $this->assertSame('waived', StripeReminderFixtures::linkRowOf($r['reminder'])['status']);
        $this->assertSame('Rykkergebyret kan ikke frafaldes: det er allerede opkrævet eller frafaldet, eller rykkeren er ikke sendt.',
            stripe_reminder_waive_request($r['reminder'], 'token', 'token'));

        // A draft only once it is booked; never a fee posted as an open item.
        $d = StripeReminderFixtures::linkReminder();
        StripeReminderFixtures::linkRow($d['reminder'], ['status' => 'draft']);
        $row = stripe_reminder_row($d['reminder']);
        $this->assertFalse(stripe_reminder_waivable($row, 2));
        $this->assertTrue(stripe_reminder_waivable($row, 4));
        $this->assertFalse(stripe_reminder_waivable(['fee_mode' => 'classic'] + $row, 4));
        $this->assertFalse(stripe_reminder_waivable(null, 4));
    }

    public function testOfferNamesInterestAsInterest(): void
    {
        $r = StripeReminderFixtures::linkReminder([], [], ['interest' => 12.5]);
        StripeReminderFixtures::linkRow($r['reminder'], ['status' => 'draft', 'interest_ore' => 1250]);
        $plain = self::plain($this->mailReminder($r['reminder'])['html']);
        $this->assertStringContainsString('Slå automatisk betaling til – og spar rykkergebyret på 100,00 kr. og renterne på 12,50 kr.', $plain);
        $this->assertStringContainsString('Betaler du med knappen nedenfor og gemmer kortet, fjerner vi rykkergebyret og renterne.', $plain);
        $this->assertStringNotContainsString('112,50', $plain, 'interest is not part of the reminder fee');

        $i = StripeReminderFixtures::linkReminder([], [], ['fee' => 0, 'interest' => 12.5]);
        StripeReminderFixtures::linkRow($i['reminder'], ['status' => 'draft', 'fee_ore' => 0, 'interest_ore' => 1250]);
        $plain = self::plain($this->mailReminder($i['reminder'])['html']);
        $this->assertStringContainsString('Slå automatisk betaling til – og spar renterne på 12,50 kr.', $plain);
        $this->assertStringContainsString('fjerner vi renterne.', $plain);
        $this->assertStringNotContainsString('rykkergebyret', $plain);
    }

    public function testStaffTextsAreInTheDatabaseEncodingAndInTekster(): void
    {
        $texts = [];
        foreach (file(__DIR__ . '/../../importfiler/tekster.csv', FILE_IGNORE_NEW_LINES) as $line) {
            $cells = explode("\t", $line);
            $texts[$cells[0]] = $cells;
        }
        foreach ([8107, 8109, 8110, 8111, 8135, 8136, 8137, 8138, 8139] as $id) {
            $this->assertCount(4, $texts[(string)$id] ?? [], "$id: Danish, English and Norwegian");
        }
        $this->assertSame('Rykker 17-1 er sendt med betalingslink til a@b.dk.', formReminderText(8137, 'x', ['rykker' => '17-1', 'email' => 'a@b.dk']));

        $encode = $GLOBALS['db_encode'];
        try {
            // A legacy (non-UTF8) database: the Danish fallback in ISO-8859-1 like findtekst(), and an alert that shows it right.
            $GLOBALS['db_encode'] = 'LATIN9';
            $text = reminder_txt(8199, 'Rykkergebyret må højst være 100,00 kr.');   // 8199 is no text: the fallback
            $this->assertSame(mb_convert_encoding('Rykkergebyret må højst være 100,00 kr.', 'ISO-8859-1', 'UTF-8'), $text);
            $alert = formReminderAlertHtml([$text]);
            $this->assertStringContainsString('m\u00e5 h\u00f8jst v\u00e6re', $alert);   // ASCII escapes, right in either page charset
            $this->assertStringNotContainsString('\ufffd', $alert);
        } finally {
            $GLOBALS['db_encode'] = $encode;
        }
        $this->assertSame('Rykkergebyret må højst', reminder_txt(8199, 'Rykkergebyret må højst'));
    }

    // ---------------------------------------------------------------- review round 2 (20261111)

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCarriedFeeWaitsUntilTheDeadlineSoTheOfferCanStillBeTaken(): void
    {
        StripeReminderFixtures::orderFunctionDoubles();
        // Reminder of 20.01.2026 with 8 payment days: "Betal senest" 28.01.2026.
        $r = StripeReminderFixtures::linkReminder([], [], ['status' => 4]);
        StripeReminderFixtures::linkRow($r['reminder']);
        $this->assertSame('2026-01-28', stripe_reminder_deadline(StripeFixtures::row('ordrer', $r['reminder'])));
        foreach (['2026-01-25' => 'before the deadline', '2026-01-28' => 'on the deadline'] as $today => $case) {
            $GLOBALS['stripe_charge_today'] = $today;
            $next = StripeFixtures::order($r['debtor'], ['levdate' => $today]);
            $this->assertSame('', stripe_reminder_fee_bogfor($next, 'DO', 25, false, false), $case);
            $this->assertCount(0, $this->feeLines($next), $case);
            $this->assertSame('sent', StripeReminderFixtures::linkRowOf($r['reminder'])['status'], $case);
        }
        // The customer takes the offer before the deadline: the fee is waived on the next invoice.
        StripeTestEnvironment::query("UPDATE adresser SET stripe = 'on', stripe_pm_id = 'pm_new', stripe_date = '2026-01-26' WHERE id = $1", [$r['debtor']]);
        $GLOBALS['stripe_charge_today'] = '2026-01-29';
        $next = StripeFixtures::order($r['debtor'], ['levdate' => '2026-01-29']);
        $this->assertSame('', stripe_reminder_fee_bogfor($next, 'DO', 25, false, false));
        $this->assertCount(0, $this->feeLines($next));
        $this->assertSame('waived', StripeReminderFixtures::linkRowOf($r['reminder'])['status']);

        // Not taken: the first invoice after the deadline carries the fee.
        $n = StripeReminderFixtures::linkReminder([], [], ['status' => 4]);
        StripeReminderFixtures::linkRow($n['reminder']);
        $next = StripeFixtures::order($n['debtor'], ['levdate' => '2026-01-29']);
        $this->assertSame('', stripe_reminder_fee_bogfor($next, 'DO', 25, false, false));
        $this->assertCount(1, $this->feeLines($next));
        $this->assertSame('charged', StripeReminderFixtures::linkRowOf($n['reminder'])['status']);

        // Without the waiver there is no offer to wait for: charged at once.
        StripeTestEnvironment::setting('stripe', 'reminder_fee_waive', 'off');
        $GLOBALS['stripe_charge_today'] = '2026-01-21';
        $o = StripeReminderFixtures::linkReminder([], [], ['status' => 4]);
        StripeReminderFixtures::linkRow($o['reminder']);
        $next = StripeFixtures::order($o['debtor'], ['levdate' => '2026-01-21']);
        $this->assertSame('', stripe_reminder_fee_bogfor($next, 'DO', 25, false, false));
        $this->assertCount(1, $this->feeLines($next));
    }

    public function testEarlierFeeWithInterestIsNotCalledAFeeAlone(): void
    {
        StripeTestEnvironment::setting('stripe', 'reminder_fee_next', 'off');
        $r = StripeReminderFixtures::linkReminder([], [], ['status' => 4, 'date' => '2026-01-05', 'interest' => 12.5]);
        StripeReminderFixtures::linkRow($r['reminder'], ['fee_mode' => 'classic', 'mailed_at' => '2026-01-05 10:00:00', 'interest_ore' => 1250]);
        $fee = $this->priorFeeOpenpost($r['reminder']);
        StripeTestEnvironment::query("UPDATE openpost SET amount = '112.500' WHERE id = $1", [$fee]);   // R.sum: fee + interest
        $second = StripeReminderFixtures::nextLevel($r, 2, '2026-01-25', ['openposts' => [$fee]]);
        $this->assertTrue(stripe_reminder_lines($second)['prior_fees'][0]['with_interest']);
        $plain = self::plain($this->mailReminder($second, true)['html']);
        $this->assertMatchesRegularExpression('/Rykkergebyr og renter fra 1\. rykker 112,50 kr\./u', $plain);
        $this->assertStringNotContainsString('Rykkergebyr fra 1. rykker', $plain);
    }

    public function testALinkReminderWithNothingLeftToPayIsNeitherMailedNorPrinted(): void
    {
        $r = StripeReminderFixtures::linkReminder([], [], ['status' => 4]);
        StripeReminderFixtures::linkRow($r['reminder'], ['status' => 'draft']);
        $nr = StripeFixtures::row('ordrer', $r['reminder'])['fakturanr'];
        $this->assertSame('', formReminderRefusal($r['reminder']));
        StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on' WHERE id = $1", [$r['invoice']]);
        $refusal = "Rykker $nr er ikke sendt: alle fakturaer på rykkeren er betalt eller under betaling.";
        $this->assertSame($refusal, formReminderRefusal($r['reminder']), 'rykkerprint() leaves it out before it is printed');
        $result = formSendReminderEntry($r['reminder'], $this->pdf($r['reminder']), 'jens@stripe-tests.invalid', 'Dansk', 6, $this->classicSender());
        $this->assertFalse($result['sent']);
        $this->assertSame($refusal, $result['message']);
        $this->assertSame('draft', StripeReminderFixtures::linkRowOf($r['reminder'])['status'], 'not sent: no fee is carried');
        // A classic reminder is printed as before.
        $c = StripeReminderFixtures::linkReminder([], ['udskriv_til' => 'email']);
        StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on' WHERE id = $1", [$c['invoice']]);
        $this->assertSame('', formReminderRefusal($c['reminder']));
    }

    public function testAFeeWaivedBeforeTheReminderWasSentStillGoesAsALinkMail(): void
    {
        // Booked draft, fee waived with "Frafald" before it was sent.
        $r = StripeReminderFixtures::linkReminder([], [], ['status' => 4]);
        StripeReminderFixtures::linkRow($r['reminder'], ['status' => 'draft']);
        $this->assertTrue(stripe_reminder_waive($r['reminder'], 'manual'));
        $this->assertNotNull(formReminderLinkRow($r['reminder']));
        $result = formSendReminderEntry($r['reminder'], $this->pdf($r['reminder']), 'jens@stripe-tests.invalid', 'Dansk', 6, $this->classicSender());
        $this->assertSame('link', $result['channel']);
        $this->assertSame([], $this->classic);
        $plain = self::plain(StripeTestTransport::$mails[0]['html'] ?? '');
        $this->assertStringNotContainsString('Rykkergebyr 100,00', $plain);
        $row = StripeReminderFixtures::linkRowOf($r['reminder']);
        $this->assertSame('waived', $row['status']);
        $this->assertSame('link', $row['channel']);
        $this->assertNotEmpty($row['mailed_at'], 'the payment page and the 10 days of the next level use it');
        // Once sent, a waived reminder is sent again as a classic reminder (its PDF says "frafaldet").
        $this->assertNull(formReminderLinkRow($r['reminder']));

        // Its fee lines deleted before booking: waived as 'removed', still a link mail; printed instead, it gets its mailed_at.
        $p = StripeReminderFixtures::linkReminder();
        StripeReminderFixtures::linkRow($p['reminder'], ['status' => 'draft']);
        StripeTestEnvironment::query('DELETE FROM ordrelinjer WHERE ordre_id = $1 AND vare_id = $2', [$p['reminder'], $this->items['fee']]);
        $this->assertNull(stripe_reminder_refresh_amounts($p['reminder']));
        $this->assertSame('removed', trim((string)StripeReminderFixtures::linkRowOf($p['reminder'])['waived_reason']));
        $this->assertNotNull(formReminderLinkRow($p['reminder']));
        formReminderPrinted($p['reminder']);
        $row = StripeReminderFixtures::linkRowOf($p['reminder']);
        $this->assertSame('waived', $row['status']);
        $this->assertSame('classic', $row['channel']);
        $this->assertNotEmpty($row['mailed_at']);
    }

    public function testAnInvoicePaidByCardDoesNotHoldTheRestOfTheReminder(): void
    {
        $debtor = StripeFixtures::debtor();
        $paid = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Link']);
        $owed = StripeReminderFixtures::invoiceOn($debtor, '2026-01-12', ['udskriv_til' => 'Link']);
        $rykker = StripeReminderFixtures::reminder($debtor, [$paid, $owed], ['status' => 4]);
        $released = fn(): bool => (bool)pg_fetch_assoc(StripeTestEnvironment::query('SELECT o.id FROM ordrer o WHERE o.id = $1 AND '
            . stripe_dunning_reminder_sql('o'), [$rykker]));
        $this->assertTrue($released());
        // One invoice paid from the link reminder: the rest can still get the next level.
        $card = $this->payment($paid, 'succeeded', 'page');
        $this->assertTrue($released(), 'paid by card next to an invoice still owed');
        // A payment still in flight holds it, as before.
        StripeTestEnvironment::query("UPDATE stripe_payments SET status = 'processing' WHERE id = $1", [$card]);
        $this->assertFalse($released(), 'in flight');
        // All paid by card: held (Afslut closes it), as before.
        StripeTestEnvironment::query("UPDATE stripe_payments SET status = 'succeeded' WHERE id = $1", [$card]);
        $this->payment($owed, 'succeeded', 'page');
        $this->assertFalse($released(), 'all paid by card');
    }

    // ---------------------------------------------------------------- review round 1 (20261126)

    public function testTheLetterStatesTheRemindersOwnFeeNotTheFeeItemsPrice(): void
    {
        // The fee item costs 556 kr. today; the 2nd reminder was made with the fee capped to 100 kr. (renteloven § 9 b).
        StripeTestEnvironment::query('UPDATE varer SET salgspris = 556 WHERE id = $1', [$this->items['fee']]);
        $r = StripeReminderFixtures::linkReminder([], [], ['status' => 4, 'date' => '2026-01-05']);
        $second = StripeReminderFixtures::nextLevel($r, 2, '2026-01-25', ['fee' => 100]);
        $this->assertSame(100.0, formReminderLetterFee($second, 7, 'dansk', 'DKK', 100.0), 'the capped fee the reminder carries');

        // A reminder made without a fee (booked with fee 0) says 0,00 when it is printed again, not today's price.
        $without = StripeReminderFixtures::reminder($r['debtor'], [$r['invoice']], ['fee' => 0, 'status' => 4]);
        $this->assertSame(0.0, formReminderLetterFee($without, 6, 'dansk', 'DKK', 100.0));

        // A foreign currency reminder: its own fee line, already in its currency (no conversion).
        $eur = StripeReminderFixtures::reminder($r['debtor'], [$r['invoice']], ['fee' => 13.4, 'valuta' => 'EUR', 'valutakurs' => 746]);
        $this->assertSame(13.4, formReminderLetterFee($eur, 6, 'dansk', 'EUR', 746.0));

        // An order that is no reminder: the fee item's price as before, in the debtor's currency, capped.
        $this->assertSame(100.0, formReminderLetterFee($r['invoice'], 7, 'dansk', 'DKK', 100.0));
        $this->assertSame(13.4, formReminderLetterFee($r['invoice'], 7, 'dansk', 'EUR', 746.0), '556 kr. is 74,53 EUR, capped at 100 kr. = 13,40 EUR');
        StripeTestEnvironment::query('UPDATE varer SET salgspris = 63 WHERE id = $1', [$this->items['fee']]);
        $this->assertSame(63.0, formReminderLetterFee($r['invoice'], 7, 'dansk', 'DKK', 100.0), 'a fee below the cap is kept');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testACappedSecondReminderLetterSaysOneHundred(): void
    {
        if (!function_exists('send_mails')) {
            function send_mails(...$args)   // formfunk.php only includes its mail code when this is missing; nothing is mailed here
            {
                return 'OK';
            }
        }
        require_once __DIR__ . '/../../includes/formfunk.php';
        StripeTestEnvironment::query('UPDATE varer SET salgspris = 556 WHERE id = $1', [$this->items['fee']]);
        $r = StripeReminderFixtures::linkReminder([], [], ['status' => 4, 'date' => '2026-01-05']);
        $second = StripeReminderFixtures::nextLevel($r, 2, '2026-01-25', ['fee' => 100]);
        StripeTestEnvironment::query("UPDATE ordrer SET pbs = '' WHERE id = $1", [$second]);
        // The letter text of the 2nd reminder form (formular 7), as the standard forms word it.
        StripeTestEnvironment::query("DELETE FROM formularer WHERE formular = 7 AND side = 'S'");
        StripeFixtures::insert('formularer', ['formular' => 7, 'art' => 2, 'side' => 'S', 'sprog' => 'Dansk', 'xa' => 20, 'ya' => 100,
            'str' => 10, 'justering' => 'V', 'font' => 'Helvetica', 'beskrivelse' => 'Gebyr for denne skrivelse udgør kr. $rykker_gebyr']);
        $ps = tempnam(sys_get_temp_dir(), 'rem-letter-');
        $htm = $ps . '.htm';
        $this->files[] = $ps;
        $this->files[] = $htm;
        $GLOBALS['psfp'] = fopen($ps, 'w');
        $GLOBALS['htmfp'] = fopen($htm, 'w');
        $GLOBALS['formularsprog'] = 'dansk';
        $GLOBALS['deb_valuta'] = 'DKK';
        $GLOBALS['deb_valutakurs'] = 100;
        $GLOBALS['side'] = 1;
        ob_start();
        try {
            find_form_tekst($second, 'S', 7, 0, 0, '');
        } finally {
            ob_end_clean();
            fclose($GLOBALS['psfp']);
            fclose($GLOBALS['htmfp']);
        }
        $letter = file_get_contents($ps) . file_get_contents($htm);
        $this->assertStringContainsString('kr. 100,00', $letter);
        $this->assertStringNotContainsString('556,00', $letter, 'not the fee item\'s price above the cap');
    }

    /** Review GPT-R-8: the letter's overdue balance is the sum of the invoices on its lines, without one the link reminder leaves out. */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTheLettersOverdueBalanceLeavesOutAnInvoiceTheLinkReminderLeavesOut(): void
    {
        if (!function_exists('send_mails')) {
            function send_mails(...$args)   // formfunk.php only includes its mail code when this is missing; nothing is mailed here
            {
                return 'OK';
            }
        }
        require_once __DIR__ . '/../../includes/formfunk.php';
        $debtor = StripeFixtures::debtor();
        $settled = StripeReminderFixtures::invoiceOn($debtor, '2026-01-12');
        $open = StripeReminderFixtures::invoiceOn($debtor, '2026-01-13');
        $rykker = StripeReminderFixtures::reminder($debtor, [$settled, $open]);
        StripeTestEnvironment::query("UPDATE ordrer SET pbs = '' WHERE id = $1", [$rykker]);
        StripeReminderFixtures::linkRow($rykker, ['status' => 'draft']);
        $letter = function () use ($rykker): string {
            StripeTestEnvironment::query("DELETE FROM formularer WHERE formular = 6 AND side = 'S'");
            StripeFixtures::insert('formularer', ['formular' => 6, 'art' => 2, 'side' => 'S', 'sprog' => 'Dansk', 'xa' => 20, 'ya' => 100,
                'str' => 10, 'justering' => 'V', 'font' => 'Helvetica', 'beskrivelse' => 'Forfaldent tilgodehavende kr. $forfalden_sum']);
            $ps = tempnam(sys_get_temp_dir(), 'rem-letter-');
            $this->files[] = $ps;
            $this->files[] = $ps . '.htm';
            $GLOBALS['psfp'] = fopen($ps, 'w');
            $GLOBALS['htmfp'] = fopen($ps . '.htm', 'w');
            $GLOBALS['formularsprog'] = 'dansk';
            $GLOBALS['deb_valuta'] = 'DKK';
            $GLOBALS['deb_valutakurs'] = 100;
            $GLOBALS['side'] = 1;
            ob_start();
            try {
                find_form_tekst($rykker, 'S', 6, 0, 0, '');
            } finally {
                ob_end_clean();
                fclose($GLOBALS['psfp']);
                fclose($GLOBALS['htmfp']);
            }
            return file_get_contents($ps) . file_get_contents($ps . '.htm');
        };
        $this->assertStringContainsString('kr. 680,00', $letter(), 'both invoices are owed');
        StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1' WHERE id = $1", [StripeReminderFixtures::openpost($settled)['id']]);
        $this->assertSame([(int)StripeReminderFixtures::openpost($settled)['id']], formReminderPdfTreatment($rykker)['skip'], 'not on the lines');
        $text = $letter();
        $this->assertStringContainsString('kr. 340,00', $text, 'the invoice the lines and the mail still list');
        $this->assertStringNotContainsString('680,00', $text);
    }

    /** @return int openpost id of a classic reminder's fee as bogfor_nu() posts it (refnr = the reminder, faktnr = its number). */
    private function priorFeeOpenpost(int $rykkerId): int
    {
        $o = StripeFixtures::row('ordrer', $rykkerId);
        return StripeFixtures::insert('openpost', ['konto_id' => (int)$o['konto_id'], 'konto_nr' => $o['kontonr'], 'faktnr' => $o['fakturanr'],
            'refnr' => $rykkerId, 'amount' => '100.000', 'beskrivelse' => 'Gebyr mm. fra tidligere rykker', 'udlignet' => '0',
            'transdate' => $o['fakturadate'], 'forfaldsdate' => $o['fakturadate'], 'valuta' => 'DKK', 'valutakurs' => '100.000', 'projekt' => '']);
    }

    // ---------------------------------------------------------------- helpers

    /** @return array<int, array<string, mixed>> Lines of $order with the reminder fee or interest item. */
    private function feeLines(int $order): array
    {
        return pg_fetch_all(StripeTestEnvironment::query('SELECT * FROM ordrelinjer WHERE ordre_id = $1 AND vare_id IN ($2, $3) ORDER BY posnr, id',
            [$order, $this->items['fee'], $this->items['interest']])) ?: [];
    }

    /** @return int stripe_payments.id */
    private function payment(int $order, string $status, string $source, array $o = []): int
    {
        return StripeFixtures::insert('stripe_payments', $o + ['ordre_id' => $order, 'payment_intent_id' => 'pi_rem_' . $order . '_' . (++$this->payments),
            'amount_ore' => 34000, 'status' => $status, 'source' => $source, 'save_card' => false, 'pm_brand' => 'visa', 'pm_last4' => '0002']);
    }

    /** Whether the invoice's open item may go on a new reminder (stripe_dunning_openpost_sql()). */
    private function remindable(int $invoice): bool
    {
        return (bool)pg_fetch_assoc(StripeTestEnvironment::query('SELECT op.id FROM openpost op WHERE op.id = $1 AND ' . stripe_dunning_openpost_sql('op'),
            [(int)StripeReminderFixtures::openpost($invoice)['id']]));
    }

    private function pdf(int $rykkerId): string
    {
        $file = tempnam(__DIR__ . '/../../temp/' . $GLOBALS['db'], 'stripe-rem-');
        $this->files[] = $file;
        file_put_contents($file, '%PDF-1.4 reminder ' . $rykkerId . "\n" . str_repeat('%PDF fixture content ', 10));
        return $file;
    }

    /**
     * Mail a registered reminder and return the captured mail.
     *
     * @param bool $register Create the draft row first.
     * @return array{to: string, subject: string, html: string, text: string, attachments: array}
     */
    private function mailReminder(int $rykkerId, bool $register = false): array
    {
        if ($register) {
            StripeReminderFixtures::linkRow($rykkerId, ['status' => 'draft']);
        }
        StripeTestTransport::$mails = [];
        $this->assertTrue(stripe_mail_reminder($rykkerId, $this->pdf($rykkerId), 'jens@stripe-tests.invalid'));
        $this->assertCount(1, StripeTestTransport::$mails);
        return StripeTestTransport::$mails[0];
    }

    /** @return Closure A classic sender (send_mails) double that records its arguments. */
    private function classicSender(): Closure
    {
        return function (...$args): string {
            $this->classic[] = $args;
            return 'Mail sent to fixture';
        };
    }

    /** The PDF is attached and named after the reminder number. */
    private function assertReminderPdf(array $mail, string $pdf, string $rykkerNr): void
    {
        $this->assertCount(1, $mail['attachments']);
        $attachment = $mail['attachments'][0];
        $path = is_array($attachment) ? (string)$attachment['path'] : (string)$attachment;
        $name = is_array($attachment) ? (string)($attachment['name'] ?? '') : basename($path);
        $this->assertSame("Rykker $rykkerNr.pdf", $name);
        $this->assertSame(file_get_contents($pdf), file_get_contents($path));
    }

    /** @return string The mail's text as a reader sees it: tags and entities gone, one space between table cells. */
    private static function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }

    /** @return array<int, string> The targets of the links whose text is $text, in order. */
    private static function links(string $html, string $text): array
    {
        preg_match_all('/<a\b[^>]*\bhref="([^"]*)"[^>]*>(.*?)<\/a>/su', $html, $m, PREG_SET_ORDER);
        $found = [];
        foreach ($m as $a) {
            if (self::plain($a[2]) === $text) {
                $found[] = html_entity_decode($a[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        return $found;
    }

    /** @return string|null The target of the one link (button) whose text is $text. */
    private static function link(string $html, string $text): ?string
    {
        $links = self::links($html, $text);
        return $links ? $links[0] : null;
    }
}
