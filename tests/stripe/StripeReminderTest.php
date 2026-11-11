<?php
// 20261109 CL/ASR Link reminders (Linkrykker): which reminders become link reminders, registration, renteloven § 9 b, the reminder fee and interest on the next invoice (and when they are waived), the reminder mail and its send path with classic fallback, the dunning hold for card-on-file invoices and closing paid reminders.
// 20261109 CL/ASR Review fixes: paid and out-of-year reminders are not escalated, a shared fee/interest item, the earlier classic fee as a bank row (D1), the first mailed_at kept (D2), the PDF treatment (D3), staff texts in the database's encoding (D4), no fee on cash/card sales, the current lines and the order's project, the hold only while the job collects, booked drafts kept, "Frafald" with its token.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/reminderFixtures.php';
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
        $this->assertStringContainsString('formReminderPrinted(', $body);
        $this->assertStringContainsString('$htmfp = fopen("../temp/$db/{$printfilnavn}_$nomailantal.htm", "w");', $body);
        $this->assertStringContainsString('$htmfp = fopen("../temp/$pfnavn.htm", "w");', $body);
    }

    // ---------------------------------------------------------------- the fee on the next invoice (bogfor)

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCarriedFeeGoesOnTheNextInvoiceAsVatFreeLinesThatDoNotRepeat(): void
    {
        StripeReminderFixtures::orderFunctionDoubles();
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
        foreach (['Vi mangler din betaling', 'Hej Anne,',
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
        $this->assertStringContainsString('Vi har endnu ikke modtaget betalingen af disse fakturaer. Du kan betale dem med kort lige her – start med den ældste.', $plain);
        $this->assertStringContainsString('Når automatisk betaling er slået til, betaler du de øvrige fakturaer med ét klik med dit gemte kort.', $plain);
        $first = strpos($plain, "Faktura $olderNr · forfaldt 16.01.2026");
        $second = strpos($plain, "Faktura $newerNr · forfaldt 20.01.2026");
        $this->assertNotFalse($first);
        $this->assertNotFalse($second);
        $this->assertLessThan($second, $first, 'the oldest invoice first');
        $this->assertMatchesRegularExpression('/At betale nu 680,00 kr\./u', $plain);
        $this->assertSame(stripe_invoice_url($older), self::link($mail['html'], 'Betal og slå automatisk betaling til'));
        $this->assertSame([stripe_invoice_url($older), stripe_invoice_url($newer)], self::links($mail['html'], 'Betal ›'));
        foreach ([$older, $newer] as $invoice) {
            $this->assertStringContainsString(stripe_invoice_url($invoice), $mail['text']);
        }
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
        $plain = self::plain($this->mailReminder($r['reminder'])['html']);
        $this->assertMatchesRegularExpression('/Rykkergebyr 100,00 kr\. betales sammen med fakturaen/u', $plain);
        $this->assertMatchesRegularExpression('/Renter 12,50 kr\./u', $plain);
        $this->assertStringContainsString('Tilgodehavende', $plain);
        $this->assertMatchesRegularExpression('/At betale nu 412,50 kr\./u', $plain);   // 340 + 100 + 12,50 - 40
        $this->assertStringNotContainsString('på næste faktura', $plain);
        $this->assertStringNotContainsString('spar rykkergebyret', $plain);
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
        $this->assertSame([['openpost_id' => $fee, 'faktnr' => $firstNr, 'amount' => 100.0, 'level' => 1]], $lines['prior_fees']);
        $this->assertSame('', stripe_reminder_eligible($second), 'still a link reminder');
        $this->assertTrue(stripe_reminder_register($second));
        $this->assertSame('classic', StripeReminderFixtures::linkRowOf($second)['fee_mode']);

        $plain = self::plain($this->mailReminder($second)['html']);
        $this->assertMatchesRegularExpression('/Rykkergebyr fra 1\. rykker 100,00 kr\. betales via bankoverførsel/u', $plain);
        $this->assertMatchesRegularExpression('/Rykkergebyr 100,00 kr\. betales sammen med fakturaen/u', $plain);
        $this->assertMatchesRegularExpression('/At betale nu 540,00 kr\./u', $plain);   // 340 + the earlier fee 100 + this fee 100
        $this->assertStringContainsString("Rykkergebyr: reg. 1234 · konto 5678901 · reference $firstNr", $plain);

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
