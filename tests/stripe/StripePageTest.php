<?php
// 20261022 CDX/LH Exercise production invoice panels and consent defaults without session/master/bootstrap side effects.
// 20261022 CDX/LH Render actual paid/credited presentation locals and assert the unavailable Saldi menu hint is absent.
// 20261022 CDX/LH Assert whole-row consent appears between the amount box and Payment Element in every consent panel.
// 20261025 CL/ASR Recurring invoices without automatic payment: consent text above Stripe and two pay buttons; fee note.
// 20261109 CL/ASR Link reminders: the overdue lead before and after a reminder, where the carried reminder fee goes, the saved card on an overdue Link invoice, and a declined attempt canceled at Stripe shown as failed (B8).
// 20261109 CL/ASR Review fixes: the fee note sums every carried fee and names interest as interest, an enrolled customer is told too, and the lead after a reminder that was not a link reminder.
// 20261116 CDX/ASR Below-minimum invoices show bank payment without a Stripe element or saved-card button; paid states still win.
// 20261126 ASR Review PAY-R2-1: an invoice settled by a journal payment is paid by bank and one settled by its credit note is credited, with no amount and no bank box, also while a 3-D Secure intent waits; payment and resume are refused.
// 20261126 ASR Review LR2-1: an invoice in installments asks for what is due now since the earliest unsettled due date, as the link reminder does (one installment settled, only installment 1 overdue); a part payment leaves the rest.
// 20261126 ASR Review PAY-R2-2: after an overpaid capture the page promises no receipt and shows no saved card; an ordinary capture still does.
// 20261127 ASR Review GPT-H-2: a part payment entered in EUR on a DKK invoice counts by its own rate in the amount left and the rendered panel; without a usable rate, or in kroner on an EUR invoice, no amount and no bank box are shown.

require_once __DIR__ . '/support/bootstrap.php';
require_once __DIR__ . '/../../includes/stripeIncludes/stripeManualFee.php';
require_once __DIR__ . '/support/reminderFixtures.php';

final class StripePageTest extends StripeDatabaseTestCase
{
    /**
     * Execute the actual page's panel switch with a tenant fixture and presentation locals.
     * The HTTP/bootstrap and external renderer are deliberately outside this tenant-only test.
     *
     * @param array<string, string> $locals Presentation locals to use instead of the empty defaults (bankBox).
     * @return array{panel: string, config: array<string, mixed>} Rendered controls and actual browser configuration.
     */
    private function render(array $inv, array $locals = []): array
    {
        $source = file_get_contents(__DIR__ . '/../../api/stripe/faktura.php');
        if (!function_exists('fak_status')) {
            $start = strpos($source, 'function fak_pill(');
            eval(substr($source, $start, strpos($source, '$amountBox =', $start) - $start));
        }
        $state = $inv['state'];
        $h = 'stripe_h';
        $d = $inv['debtor'];
        $o = $inv['o'];
        $brand = $inv['brand'];
        $start = strpos($source, '$contact = stripe_contact_phrase($inv);');
        eval(substr($source, $start, strpos($source, 'function fak_pill(', $start) - $start));
        $totalKr = stripe_fmt_kr($inv['total_ore']) . ' kr.';
        $dueFmt = stripe_fmt_date($inv['due']);
        $nr = $inv['nr'];
        $amountBox = '<div class="amt">Amount</div>';
        $elementBlock = $trust = $bankBox = $foot = '';
        $elementBlock = '<div id="payment-element"></div>';
        $hasBank = false;
        if (isset($locals['bankBox'])) {
            $bankBox = $locals['bankBox'];
            $hasBank = true;
        }
        $start = strpos($source, "$" . "panel = '';");
        eval(substr($source, $start, strpos($source, '// ---- invoice (left) ----', $start) - $start));
        $pk = stripe_setting('publishable_key');
        $faktura_s = stripe_link_sign($inv['id'], 'invoice');
        $self = stripe_invoice_url($inv['id']);
        $start = strpos($source, '$cfg = array(');
        eval(substr($source, $start, strpos($source, "\nwhile (ob_get_level())", $start) - $start));
        return ['panel' => $panel, 'config' => $cfg];
    }

    public function testFailedPanelCheckboxAndElementsDefaultAgreeForOneOffRecurringAndReplacement(): void
    {
        foreach (['one_off', 'recurring', 'replacement', 'disabled'] as $case) {
            $debtor = StripeFixtures::debtor($case === 'replacement' ? ['stripe' => 'on', 'stripe_pm_id' => 'pm_old'] : []);
            $id = StripeFixtures::invoice($debtor, ['nextfakt' => in_array($case, ['recurring', 'disabled'], true) ? '2026-02-08' : null]);
            StripeFixtures::insert('stripe_payments', ['ordre_id' => $id, 'payment_intent_id' => 'pi_failed_' . $case,
                'amount_ore' => 34000, 'status' => 'failed', 'source' => 'page', 'last_error' => 'insufficient_funds',
                'pm_brand' => 'visa', 'pm_last4' => '9995', 'attempt_date' => StripeFixtures::TODAY]);
            if ($case === 'disabled') {
                StripeTestEnvironment::setting('stripe', 'enrolment', 'off');
            }
            $rendered = $this->render(stripe_invoice_load($id));
            // 20261027 a one-off invoice offers automatic payment like a recurring one
            $allowed = in_array($case, ['one_off', 'recurring', 'replacement'], true);
            $twoButtons = in_array($case, ['one_off', 'recurring'], true);
            $this->assertSame($allowed, $rendered['config']['saveDefault'], $case);
            $this->assertSame($case === 'replacement', strpos($rendered['panel'], 'id="chk"') !== false, $case);
            $this->assertSame($twoButtons, strpos($rendered['panel'], 'id="pay-once"') !== false, $case);
            $this->assertStringContainsString('disabled', $rendered['panel']);
            $this->assertStringContainsString('Der er ikke dækning på kortet.', $rendered['panel']);
            $this->assertStringNotContainsString('insufficient_funds', $rendered['panel']);
            if ($case === 'replacement') {
                $this->assertConsentBeforeElement($rendered['panel']);
            } elseif ($twoButtons) {
                $this->assertTwoButtonsAfterConsentText($rendered['panel']);
            }
            if ($case === 'replacement') {
                $this->assertStringContainsString('Brug dette kort fremover', $rendered['panel']);
            }
        }
        $this->assertSame([], StripeTestTransport::$requests);
    }

    /** Verify the focusable, named checkbox is the whole row, directly above Stripe. */
    private function assertConsentBeforeElement(string $panel): void
    {
        $this->assertMatchesRegularExpression('/<div class="check" role="checkbox" aria-checked="(?:true|false)" tabindex="0" id="chk" aria-labelledby="consent-title" aria-describedby="consent-text">/', $panel);
        $this->assertStringContainsString('<b id="consent-title">', $panel);
        $this->assertStringContainsString('<span id="consent-text">', $panel);
        $this->assertStringContainsString('<div class="box" aria-hidden="true">', $panel);
        $amount = strpos($panel, 'class="amt"');
        $consent = strpos($panel, 'id="chk"');
        $element = strpos($panel, 'id="payment-element"');
        $this->assertNotFalse($amount);
        $this->assertNotFalse($consent);
        $this->assertNotFalse($element);
        $this->assertTrue($amount < $consent && $consent < $element);
        $this->assertStringContainsString('</span></div></div><div id="payment-element"', $panel);
    }

    /** Consent text sits between the amount and Stripe; the automatic-payment button and "pay-once" follow Stripe. */
    private function assertTwoButtonsAfterConsentText(string $panel): void
    {
        $this->assertStringNotContainsString('role="checkbox"', $panel);
        $amount = strpos($panel, 'class="amt"');
        $consent = strpos($panel, '<div class="auto">');
        $element = strpos($panel, 'id="payment-element"');
        $main = strpos($panel, 'id="pay"');
        $once = strpos($panel, 'id="pay-once"');
        foreach ([$amount, $consent, $element, $main, $once] as $position) {
            $this->assertNotFalse($position);
        }
        $this->assertTrue($amount < $consent && $consent < $element && $element < $main && $main < $once);
        $this->assertStringContainsString('<span id="consent-text">', $panel);
        $this->assertStringContainsString('aria-describedby="consent-text">Betal 340,00 kr. og slå automatisk betaling til</button>', $panel);
        $this->assertStringContainsString('>Betal kun denne faktura</button>', $panel);
    }

    public function testOneOffInvoiceAlsoOffersAutomaticPaymentSoTheFeeCanBeAvoided(): void
    {
        $varenr = 'FEE' . bin2hex(random_bytes(4));
        StripeFixtures::insert('varer', ['varenr' => $varenr, 'beskrivelse' => 'Gebyr', 'salgspris' => 129, 'gruppe' => 1]);
        StripeTestEnvironment::setting('stripe', 'manual_fee_varenr', $varenr);
        StripeTestEnvironment::setting('stripe', 'manual_fee_from', '2026-01-01');
        $inv = stripe_invoice_load(StripeFixtures::invoice());
        $this->assertFalse($inv['recurring']);
        $this->assertSame('open', $inv['state']);
        $this->assertTrue(stripe_invoice_save_allowed($inv));
        $rendered = $this->render($inv);
        $this->assertTwoButtonsAfterConsentText($rendered['panel']);
        $this->assertStringContainsString('class="fee"', $rendered['panel']);
        // A credit note never offers it; the enrolment switch still turns it off everywhere.
        $this->assertFalse(stripe_invoice_save_allowed(array('recurring' => false, 'enrolled' => false, 'is_credit' => true, 'o' => array('art' => 'DK'))));
        StripeTestEnvironment::setting('stripe', 'enrolment', 'off');
        $this->assertFalse(stripe_invoice_save_allowed($inv));
        $this->assertStringNotContainsString('id="pay-once"', $this->render(stripe_invoice_load($inv['id']))['panel']);
    }

    public function testOpenRecurringShowsConsentTextAndTwoButtonsAndTheFeeNote(): void
    {
        $id = StripeFixtures::invoice(null, ['nextfakt' => '2026-02-08']);
        $inv = stripe_invoice_load($id);
        $this->assertSame('open_recurring', $inv['state']);
        $rendered = $this->render($inv);
        $this->assertTwoButtonsAfterConsentText($rendered['panel']);
        $this->assertFalse($rendered['config']['saveDefault']);
        $this->assertStringNotContainsString('class="fee"', $rendered['panel']);

        $varenr = 'FEE' . bin2hex(random_bytes(4));
        StripeTestEnvironment::query("INSERT INTO varer (varenr, beskrivelse, salgspris, gruppe) VALUES ($1, 'Gebyr', 129, 1)", [$varenr]);
        StripeTestEnvironment::setting('stripe', 'manual_fee_varenr', $varenr);
        StripeTestEnvironment::setting('stripe', 'manual_fee_from', '2026-01-01');
        $panel = $this->render(stripe_invoice_load($id))['panel'];
        $this->assertStringContainsString('<p class="fee">Uden automatisk betaling lægges et gebyr for manuel betaling på 161,25 kr. på din næste faktura.</p>', $panel);
        $this->assertGreaterThan(strpos($panel, 'id="pay-once"'), strpos($panel, 'class="fee"'));
    }

    public function testManualCreditBankPaidAndPendingPanelsHaveNoNewPaymentControls(): void
    {
        foreach (['manual', 'credit', 'bank_paid', 'pending'] as $case) {
            $id = StripeFixtures::invoice(null, $case === 'credit' ? ['art' => 'DK', 'sum' => -272, 'moms' => -68] : []);
            if ($case === 'manual') {
                StripeTestEnvironment::query('UPDATE openpost SET amount=240 WHERE konto_id=$1', [StripeFixtures::row('ordrer', $id)['konto_id']]);
            } elseif ($case === 'bank_paid') {
                StripeTestEnvironment::query("UPDATE ordrer SET betalt='on' WHERE id=$1", [$id]);
            } elseif ($case === 'pending') {
                StripeFixtures::insert('stripe_payments', ['ordre_id' => $id, 'payment_intent_id' => 'pi_panel_pending', 'amount_ore' => 34000, 'status' => 'processing']);
            }
            $rendered = $this->render(stripe_invoice_load($id));
            foreach (['id="payment-element"', 'id="pay"', 'id="paynow"', 'id="chk"'] as $control) {
                $this->assertStringNotContainsString($control, $rendered['panel'], $case);
            }
            $this->assertFalse($rendered['config']['saveDefault']);
            if ($case === 'pending') {
                StripeTestTransport::queueBody(['id' => 'pi_panel_pending', 'status' => 'processing'], 'GET', '/v1/payment_intents/pi_panel_pending');
            }
            $this->assertFalse(stripe_payment_create(stripe_invoice_load($id))['ok']);
        }
        $this->assertCount(1, StripeTestTransport::$requests);
        $this->assertSame([], StripeTestTransport::$responses);
        $this->assertSame([], StripeTestTransport::$mails);
    }

    public function testPaymentButtonIsDisabledUntilStripeElementReadyAndLoadErrorsAreVisible(): void
    {
        $rendered = $this->render(stripe_invoice_load(StripeFixtures::invoice()));
        $this->assertStringContainsString('id="pay" type="submit" disabled', $rendered['panel']);
        $source = file_get_contents(__DIR__ . '/../../api/stripe/faktura.php');
        $this->assertStringContainsString("pe.on('ready',", $source);
        $this->assertStringContainsString("pe.on('loaderror',", $source);
        $this->assertStringContainsString('if (!elementReady || submitting) return;', $source);
    }

    public function testBelowMinimumInvoiceHasNoCardControlsAndKeepsPaymentStatus(): void
    {
        $id = StripeFixtures::invoice(StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved']),
            ['sum' => 1, 'moms' => 0.25]);
        $inv = stripe_invoice_load($id);
        $rendered = $this->render($inv);
        $this->assertSame('below_minimum', $inv['state']);
        $this->assertStringContainsString('Kortbetaling kræver mindst 2,50 kr.', $rendered['panel']);
        foreach (['id="payment-element"', 'id="pay"', 'id="paynow"', 'id="pay-once"'] as $control) {
            $this->assertStringNotContainsString($control, $rendered['panel']);
        }
        StripeTestEnvironment::query("UPDATE ordrer SET betalt='on' WHERE id=$1", [$id]);
        $this->assertSame('paidbank', stripe_invoice_load($id)['state']);
        StripeFixtures::insert('stripe_payments', ['ordre_id' => $id, 'payment_intent_id' => 'pi_minimum_paid',
            'amount_ore' => 125, 'status' => 'succeeded']);
        $this->assertSame('paid', stripe_invoice_load($id)['state']);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testPaidAndCreditedSaldiPanelsDoNotMentionUnavailableBillingMenu(): void
    {
        foreach (['paid', 'credited'] as $state) {
            $order = StripeFixtures::invoice(null, $state === 'credited' ? ['art' => 'DK', 'sum' => -272, 'moms' => -68] : []);
            if ($state === 'paid') {
                StripeFixtures::insert('stripe_payments', ['ordre_id' => $order, 'payment_intent_id' => 'pi_paid_panel',
                    'status' => 'succeeded', 'amount_ore' => 34000, 'source' => 'page', 'save_card' => false,
                    'pm_brand' => 'visa', 'pm_last4' => '4242', 'succeeded_at' => StripeFixtures::TODAY]);
            }
            $inv = stripe_invoice_load($order);
            $this->assertSame($state, $inv['state']);
            $inv['brand'] = 'Saldi';
            $rendered = $this->render($inv);
            $panel = html_entity_decode($rendered['panel'], ENT_QUOTES, 'UTF-8');
            $this->assertStringNotContainsString('Abonnement & fakturering', $panel);
            $this->assertStringNotContainsString('Indstillinger', $panel);
            $this->assertFalse($rendered['config']['saveDefault']);
        }
    }

    public function testReminderLeadAndFeeNoteOnTheOverduePage(): void
    {
        $GLOBALS['stripe_charge_today'] = '2026-01-25';
        StripeTestEnvironment::query("DELETE FROM formularer WHERE beskrivelse = 'GEBYR'");
        $id = StripeFixtures::invoice();
        $inv = stripe_invoice_load($id);
        $this->assertSame('overdue', $inv['state']);
        $this->assertNull($inv['reminder']);
        $this->assertStringContainsString('<p class="lead">Fakturaen forfaldt den 16.01.2026. Betal nu for at undgå en rykker.</p>', $this->render($inv)['panel']);

        // With a reminder fee set up the customer is told that the reminder costs a fee.
        StripeReminderFixtures::forms();
        $this->assertStringContainsString('<p class="lead">Fakturaen forfaldt den 16.01.2026. Betal nu, så undgår du en rykker med gebyr.</p>',
            $this->render(stripe_invoice_load($id))['panel']);

        // After a link reminder: when it was sent, and that the fee goes on the next invoice unless automatic payment is turned on.
        $rykker = StripeReminderFixtures::reminder((int)StripeFixtures::row('ordrer', $id)['konto_id'], [$id], ['status' => 4]);
        StripeReminderFixtures::linkRow($rykker, ['mailed_at' => '2026-01-20 15:30:00']);
        $inv = stripe_invoice_load($id);
        $this->assertSame($rykker, (int)$inv['reminder']['rykker_id']);
        $panel = $this->render($inv)['panel'];
        $this->assertStringContainsString('<p class="lead">Vi sendte dig en rykker den 20.01.2026. Betal fakturaen nu, så undgår du flere rykkere.</p>', $panel);
        $this->assertStringContainsString('Slår du automatisk betaling til, frafalder rykkergebyret på 100,00 kr. Ellers kommer det på din næste faktura.', $panel);
        $this->assertStringNotContainsString('undgå en rykker', $panel);
        $this->assertGreaterThan(strpos($panel, 'id="pay"'), strpos($panel, 'frafalder rykkergebyret'), 'under the pay buttons');

        StripeTestEnvironment::setting('stripe', 'reminder_fee_waive', 'off');
        $panel = $this->render(stripe_invoice_load($id))['panel'];
        $this->assertStringContainsString('Rykkergebyret på 100,00 kr. kommer på din næste faktura.', $panel);
        $this->assertStringNotContainsString('frafalder rykkergebyret', $panel);

        // A fee posted as an open item (classic fee mode) is not mentioned.
        StripeTestEnvironment::query("UPDATE stripe_reminders SET fee_mode = 'classic' WHERE rykker_id = $1", [$rykker]);
        $panel = $this->render(stripe_invoice_load($id))['panel'];
        $this->assertStringContainsString('Vi sendte dig en rykker den 20.01.2026.', $panel);
        $this->assertStringNotContainsString('Rykkergebyret på', $panel);
        $this->assertStringNotContainsString('frafalder rykkergebyret', $panel);
    }

    public function testReminderOverdueLinkInvoiceOfAnEnrolledCustomerIsPaidWithTheSavedCard(): void
    {
        $GLOBALS['stripe_charge_today'] = '2026-01-25';
        $debtor = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'stripe_date' => '2026-01-01']);
        $inv = stripe_invoice_load(StripeFixtures::invoice($debtor, ['udskriv_til' => 'Link']));
        $this->assertSame('overdue', $inv['state']);
        $this->assertTrue(stripe_invoice_pay_now_allowed($inv));
        $panel = $this->render($inv)['panel'];
        $this->assertStringContainsString('<button class="btn primary" id="paynow" type="button">Betal 340,00 kr. med dit gemte kort</button>', $panel);
        $this->assertStringNotContainsString('id="payment-element"', $panel);
        $this->assertStringNotContainsString('id="pay"', $panel);

        // Without a saved card the overdue invoice keeps the card form.
        $panel = $this->render(stripe_invoice_load(StripeFixtures::invoice(null, ['udskriv_til' => 'Link'])))['panel'];
        $this->assertStringContainsString('id="payment-element"', $panel);
        $this->assertStringNotContainsString('id="paynow"', $panel);
    }

    public function testReminderLatestDeclinedAttemptCanceledAtStripeShowsTheFailedView(): void
    {
        $debtor = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'stripe_date' => '2026-01-01']);
        $id = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Stripe']);
        $this->assertSame('enrolled', stripe_invoice_load($id)['state']);
        $declined = StripeFixtures::insert('stripe_payments', ['ordre_id' => $id, 'payment_intent_id' => 'pi_b8_declined', 'amount_ore' => 34000,
            'status' => 'canceled', 'source' => 'auto', 'last_error' => 'card_declined', 'pm_brand' => 'visa', 'pm_last4' => '0002',
            'attempt_date' => StripeFixtures::DUE_DATE]);
        $inv = stripe_invoice_load($id);
        $this->assertSame('failed', $inv['state']);
        $this->assertSame($declined, (int)$inv['failed']['id']);
        $panel = $this->render($inv)['panel'];
        $this->assertStringContainsString('Brug dette kort fremover', $panel);
        $this->assertStringContainsString('id="payment-element"', $panel);
        $this->assertStringNotContainsString('Trækkes den', $panel);

        // A later attempt (here a technical error) is the latest word: the decline no longer shows.
        StripeFixtures::insert('stripe_payments', ['ordre_id' => $id, 'payment_intent_id' => 'pi_b8_technical', 'amount_ore' => 34000,
            'status' => 'canceled', 'source' => 'auto', 'last_error' => 'technical_error']);
        $inv = stripe_invoice_load($id);
        $this->assertNull($inv['failed']);
        $this->assertSame('enrolled', $inv['state']);
    }

    public function testReminderFeeNoteSumsEveryCarriedFeeAndTellsAnEnrolledCustomer(): void
    {
        $GLOBALS['stripe_charge_today'] = '2026-01-25';
        StripeReminderFixtures::forms();
        $debtor = StripeFixtures::debtor();
        $id = StripeFixtures::invoice($debtor);
        $first = StripeReminderFixtures::reminder($debtor, [$id], ['status' => 4, 'date' => '2026-01-05']);
        StripeReminderFixtures::linkRow($first, ['mailed_at' => '2026-01-05 10:00:00']);
        $ordrenr = (int)StripeFixtures::row('ordrer', $first)['ordrenr'];
        $second = StripeReminderFixtures::reminder($debtor, [$id], ['level' => 2, 'ordrenr' => $ordrenr, 'status' => 4, 'interest' => 12.5, 'date' => '2026-01-20']);
        StripeReminderFixtures::linkRow($second, ['mailed_at' => '2026-01-20 15:30:00', 'interest_ore' => 1250]);
        $panel = $this->render(stripe_invoice_load($id))['panel'];
        $this->assertStringContainsString('Slår du automatisk betaling til, frafalder rykkergebyret på 200,00 kr. og renterne på 12,50 kr. Ellers kommer de på din næste faktura.', $panel);
        $this->assertStringNotContainsString('rykkergebyret på 212,50', $panel);
        // The latest reminder waived: the first one's fee still goes on the next invoice.
        StripeTestEnvironment::query("UPDATE stripe_reminders SET status = 'waived' WHERE rykker_id = $1", [$second]);
        $this->assertStringContainsString('Slår du automatisk betaling til, frafalder rykkergebyret på 100,00 kr. Ellers kommer det på din næste faktura.',
            $this->render(stripe_invoice_load($id))['panel']);

        // A customer who already pays automatically (enrolled before the reminder) pays the fee on the next invoice.
        $enrolled = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'stripe_date' => '2026-01-01']);
        $link = StripeFixtures::invoice($enrolled, ['udskriv_til' => 'Link']);
        $rykker = StripeReminderFixtures::reminder($enrolled, [$link], ['status' => 4]);
        StripeReminderFixtures::linkRow($rykker, ['mailed_at' => '2026-01-20 15:30:00']);
        $panel = $this->render(stripe_invoice_load($link))['panel'];
        $this->assertStringContainsString('id="paynow"', $panel);
        $this->assertStringContainsString('Rykkergebyret på 100,00 kr. kommer på din næste faktura.', $panel);
        // Enrolled on or after the day of the reminder: the fee is waived anyway.
        StripeTestEnvironment::query("UPDATE adresser SET stripe_date = '2026-01-20' WHERE id = $1", [$enrolled]);
        $this->assertStringNotContainsString('Rykkergebyret', $this->render(stripe_invoice_load($link))['panel']);
    }

    public function testOverdueLeadAfterAReminderThatWasNotALinkReminder(): void
    {
        $GLOBALS['stripe_charge_today'] = '2026-01-25';
        StripeReminderFixtures::forms();
        $id = StripeFixtures::invoice();
        $rykker = StripeReminderFixtures::reminder((int)StripeFixtures::row('ordrer', $id)['konto_id'], [$id]);
        // A reminder being prepared: no promise about avoiding one with a fee.
        $this->assertStringContainsString('<p class="lead">Fakturaen forfaldt den 16.01.2026. Betal nu for at undgå en rykker.</p>',
            $this->render(stripe_invoice_load($id))['panel']);
        // Booked (sent as a PDF or on paper): the customer has had it.
        StripeTestEnvironment::query('UPDATE ordrer SET status = 4 WHERE id = $1', [$rykker]);
        $panel = $this->render(stripe_invoice_load($id))['panel'];
        $this->assertStringContainsString('<p class="lead">Vi har sendt dig en rykker. Betal fakturaen nu, så undgår du flere rykkere.</p>', $panel);
        $this->assertStringNotContainsString('med gebyr', $panel);
    }

    /**
     * faktura_pay.php's refusals before a payment is started or resumed, with only faktura_fail() replaced.
     *
     * @return array{0: int, 1: string, 2: string}|null [status, code, message], or null when it would go on to Stripe.
     */
    private function payRefusal(array $inv): ?array
    {
        $source = file_get_contents(__DIR__ . '/../../api/stripe/faktura_pay.php');
        $start = strpos($source, '$settled = stripe_invoice_settled_reason($inv);');
        $end = strpos($source, '$saveCard = ', (int)$start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $result = null;
        $fail = function ($status, $headline, $text, $code = 'error') use (&$result): void {
            $result = [$status, $code, $text];
            throw new RuntimeException('faktura_fail');
        };
        try {
            eval(str_replace('faktura_fail(', '$fail(', substr($source, $start, $end - $start)));
        } catch (RuntimeException $finished) {
            if ($finished->getMessage() !== 'faktura_fail') throw $finished;
        }
        return $result;
    }

    /** An open item as BASE's journal posting writes one: a bank payment with the invoice number. */
    private function bankPayment(int $invoice, string $amount, bool $settles): int
    {
        $o = StripeFixtures::row('ordrer', $invoice);
        $id = StripeFixtures::insert('openpost', ['konto_id' => (int)$o['konto_id'], 'konto_nr' => $o['kontonr'], 'faktnr' => $o['fakturanr'],
            'amount' => $amount, 'udlignet' => $settles ? '1' : '0', 'transdate' => StripeFixtures::TODAY, 'forfaldsdate' => StripeFixtures::TODAY,
            'valuta' => 'DKK', 'valutakurs' => '100.000', 'projekt' => '']);
        if ($settles) {
            StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1' WHERE konto_id = $1 AND faktnr = $2", [(int)$o['konto_id'], $o['fakturanr']]);
        }
        return $id;
    }

    /** Review PAY-R2-1: the journal posting settles the open items but does not set ordrer.betalt; a waiting 3-D Secure intent does not reopen the invoice. */
    public function testAnInvoiceSettledByAJournalPaymentIsPaidByBankAndAsksForNothing(): void
    {
        $id = StripeFixtures::invoice();
        $this->bankPayment($id, '-340.000', true);
        StripeFixtures::insert('stripe_payments', ['ordre_id' => $id, 'payment_intent_id' => 'pi_settled_waiting', 'amount_ore' => 34000,
            'status' => 'requires_action', 'source' => 'page']);
        $inv = stripe_invoice_load($id);
        $this->assertSame('', trim((string)$inv['o']['betalt']), 'BASE leaves ordrer.betalt empty');
        $this->assertSame([true, false, 'paidbank'], [$inv['paid_bank'], $inv['credited'], $inv['state']]);
        $this->assertSame('paid_bank', stripe_invoice_settled_reason($inv));
        $panel = $this->render($inv, ['bankBox' => '<div class="bank">Betal via bankoverførsel</div>'])['panel'];
        $this->assertStringContainsString('<h2>Fakturaen er betalt</h2>', $panel);
        foreach (['Kontakt os om betalingen', 'Godkend betalingen', 'At betale', 'class="bank"', 'id="resume"', 'id="pay"', 'id="payment-element"'] as $text) {
            $this->assertStringNotContainsString($text, $panel, $text);
        }
        // The waiting intent is not resumed and nothing new is started; Stripe is not asked.
        $this->assertSame(['ok' => false, 'error' => 'already_paid'], stripe_payment_resume($id));
        $this->assertSame([409, 'already_paid', 'Fakturaen er allerede betalt.'], $this->payRefusal($inv));
        $this->assertSame(['status' => 409, 'code' => 'already_paid', 'message' => 'Fakturaen er allerede betalt.'],
            stripe_payment_failure_response(['error' => 'already_paid']));
        $this->assertSame([], StripeTestTransport::$requests);
        // Afmeld's invoice mail and the follow-up of a declined automatic charge leave the settled invoice alone.
        StripeTestEnvironment::query('INSERT INTO stripe_unenrol_mail (ordre_id) VALUES ($1)', [$id]);
        StripeFixtures::insert('stripe_payments', ['ordre_id' => $id, 'payment_intent_id' => 'pi_settled_declined', 'amount_ore' => 34000,
            'status' => 'failed', 'source' => 'auto', 'last_error' => 'card_declined', 'attempt_date' => StripeFixtures::TODAY]);
        $this->assertSame(0, stripe_unenrol_mail_retry((int)$inv['o']['konto_id']));
        $this->assertNotEmpty(pg_fetch_result(StripeTestEnvironment::query('SELECT sent_at FROM stripe_unenrol_mail WHERE ordre_id = $1', [$id]), 0, 0));
        $this->assertSame('handled', stripe_auto_failure_followup($id));
        $this->assertSame([], StripeTestTransport::$mails);

        // A page opened before the bank payment was posted: the payment start says the invoice is paid.
        $other = StripeFixtures::invoice();
        $stale = stripe_invoice_load($other);
        $this->assertSame('open', $stale['state']);
        $this->assertNull($this->payRefusal($stale));
        $this->bankPayment($other, '-340.000', true);
        $this->assertSame('already_paid', stripe_payment_create($stale)['error']);
        $this->assertSame([], StripeTestTransport::$requests);

        // ordrer.betalt still counts; an unsettled payment of the same amount does not.
        $marked = StripeFixtures::invoice();
        StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on' WHERE id = $1", [$marked]);
        $this->assertSame('paidbank', stripe_invoice_load($marked)['state']);
        $pair = StripeFixtures::invoice();
        $this->bankPayment($pair, '-340.000', false);
        $inv = stripe_invoice_load($pair);
        $this->assertFalse($inv['paid_bank']);
        $this->assertSame('manual', $inv['state']);
    }

    /** Review PAY-R2-1: an invoice settled by its invoiced credit note is credited: no amount, no bank box, no payment. */
    public function testAnInvoiceSettledByItsCreditNoteIsCreditedWithNoAmountOrBankBox(): void
    {
        $debtor = StripeFixtures::debtor();
        $id = StripeFixtures::invoice($debtor);
        $credit = StripeFixtures::invoice($debtor, ['art' => 'DK', 'kred_ord_id' => $id, 'sum' => -272, 'moms' => -68]);
        StripeTestEnvironment::query("UPDATE ordrer SET fakturadate = '2026-01-09' WHERE id = $1", [$credit]);
        // Crediting the whole invoice settles its open item against the credit note's (ordrer.betalt stays '').
        StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1' WHERE konto_id = $1", [$debtor]);
        StripeFixtures::insert('stripe_payments', ['ordre_id' => $id, 'payment_intent_id' => 'pi_credited_waiting', 'amount_ore' => 34000,
            'status' => 'requires_action', 'source' => 'page']);
        $inv = stripe_invoice_load($id);
        $this->assertSame([true, false, 'invoice_credited', 34000], [$inv['credited'], $inv['paid_bank'], $inv['state'], $inv['credited_ore']]);
        $nr = StripeFixtures::row('ordrer', $credit)['fakturanr'];
        $panel = $this->render($inv, ['bankBox' => '<div class="bank">Betal via bankoverførsel</div>'])['panel'];
        $this->assertStringContainsString('<h2>Fakturaen er krediteret</h2>', $panel);
        $this->assertStringContainsString('<b>Krediteret 9. januar 2026</b><span>Kreditnota ' . $nr . '</span>', $panel);
        foreach (['At betale', '340,00', 'class="bank"', 'Kontakt os om betalingen', 'Godkend betalingen', 'id="resume"'] as $text) {
            $this->assertStringNotContainsString($text, $panel, $text);
        }
        $this->assertSame(['ok' => false, 'error' => 'credited'], stripe_payment_resume($id));
        $this->assertSame([409, 'credited', 'Fakturaen er krediteret, så der er intet at betale.'], $this->payRefusal($inv));
        $this->assertSame([], StripeTestTransport::$requests);

        // A credit note for part of the invoice, the rest paid by bank: paid by bank.
        $part = StripeFixtures::invoice($debtor);
        StripeFixtures::invoice($debtor, ['art' => 'DK', 'kred_ord_id' => $part, 'sum' => -80, 'moms' => -20]);
        $this->bankPayment($part, '-240.000', true);
        $this->assertSame('paidbank', stripe_invoice_load($part)['state']);
        // A credit note draft credits nothing yet: settled by bank is paid by bank.
        $draft = StripeFixtures::invoice($debtor);
        StripeFixtures::order($debtor, ['art' => 'DK', 'kred_ord_id' => $draft, 'sum' => -272, 'moms' => -68]);
        $this->bankPayment($draft, '-340.000', true);
        $inv = stripe_invoice_load($draft);
        $this->assertSame([[], 'paidbank'], [$inv['credit_notes'], $inv['state']]);
    }

    /** Review LR2-1: an invoice in two installments asks for what its unsettled open items owe, as the link reminder does. */
    public function testAnInvoiceInInstallmentsAsksForWhatIsDueAsTheLinkReminderDoes(): void
    {
        StripeReminderFixtures::forms();
        $debtor = StripeFixtures::debtor();
        $id = StripeFixtures::invoice($debtor);
        $first = StripeReminderFixtures::openpost($id);
        // 340 kr. in two installments: 200 kr. due 20.01.2026 and 140 kr. due 05.02.2026 (the calendar date is 16.01.2026).
        StripeTestEnvironment::query("UPDATE openpost SET amount = '200.000', forfaldsdate = '2026-01-20' WHERE id = $1", [$first['id']]);
        $second = StripeFixtures::insert('openpost', ['konto_id' => $debtor, 'konto_nr' => $first['konto_nr'], 'faktnr' => $first['faktnr'],
            'amount' => '140.000', 'udlignet' => '0', 'transdate' => StripeFixtures::INVOICE_DATE, 'forfaldsdate' => '2026-02-05',
            'valuta' => 'DKK', 'valutakurs' => '100.000', 'projekt' => '']);
        $bank = ['bankBox' => '<div class="bank">Betal via bankoverførsel</div>'];

        // Only installment 1 is overdue: the reminder asks for 200 kr. since 20.01.2026, and so does the page; installment 2 is shown as later.
        $GLOBALS['stripe_charge_today'] = '2026-01-25';
        $inv = stripe_invoice_load($id);
        $this->assertSame(['manual', '2026-01-16'], [$inv['state'], $inv['due']], 'two due dates: the invoice due date is the calendar date');
        $panel = $this->render($inv, $bank)['panel'];
        $this->assertStringContainsString('<span>At betale nu</span><span>Forfaldt 20.01.2026</span></div><b class="num">200,00 kr.</b>', $panel);
        $this->assertStringContainsString('<span>Senere rate, forfalder 05.02.2026</span><b class="num">140,00 kr.</b>', $panel);
        $this->assertStringContainsString('<span>Fakturabeløb</span><b class="num">340,00 kr.</b>', $panel);
        $this->assertStringContainsString('class="bank"', $panel);
        $this->assertStringNotContainsString('16.01.2026', $panel);
        $rykker = StripeReminderFixtures::reminder($debtor, [$id]);
        StripeReminderFixtures::linkRow($rykker, ['status' => 'draft']);
        $row = stripe_reminder_invoices($rykker)['invoices'][0];
        $this->assertSame([200.0, '2026-01-20'], [(float)$row['amount'], $row['forfaldsdate']], 'the reminder row');

        // Installment 1 paid by bank (the journal posting settles it): the page asks for the 140 kr. left, due 05.02.2026, as the reminder listing both does.
        $GLOBALS['stripe_charge_today'] = '2026-02-10';
        StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1' WHERE id = $1", [$first['id']]);
        StripeFixtures::insert('openpost', ['konto_id' => $debtor, 'konto_nr' => $first['konto_nr'], 'faktnr' => $first['faktnr'],
            'amount' => '-200.000', 'udlignet' => '1', 'transdate' => '2026-01-30', 'forfaldsdate' => '2026-01-30',
            'valuta' => 'DKK', 'valutakurs' => '100.000', 'projekt' => '']);
        $inv = stripe_invoice_load($id);
        $this->assertSame([false, 'manual'], [$inv['paid_bank'], $inv['state']]);
        $panel = $this->render($inv, $bank)['panel'];
        $this->assertStringContainsString('<span>At betale</span><span>Forfaldt 05.02.2026</span></div><b class="num">140,00 kr.</b>', $panel);
        $this->assertStringContainsString('<span>Fakturabeløb</span><b class="num">340,00 kr.</b>', $panel);
        $this->assertStringNotContainsString('Senere rate', $panel);
        $both = StripeReminderFixtures::reminder($debtor, [$id], ['openposts' => [$second]]);
        StripeReminderFixtures::linkRow($both, ['status' => 'draft']);
        $row = stripe_reminder_invoices($both)['invoices'][0];
        $this->assertSame([140.0, '2026-02-05'], [(float)$row['amount'], $row['forfaldsdate']], 'the reminder row');

        // Nothing due yet: the next installment, with the rest as later.
        $GLOBALS['stripe_charge_today'] = '2026-01-12';
        StripeTestEnvironment::query("UPDATE openpost SET udlignet = '0' WHERE id = $1", [$first['id']]);
        StripeTestEnvironment::query("DELETE FROM openpost WHERE konto_id = $1 AND amount < 0", [$debtor]);
        $owed = stripe_invoice_owed(stripe_invoice_load($id));
        $this->assertSame([false, 34000, 20000, '2026-01-20', [['due' => '2026-02-05', 'ore' => 14000]]],
            [$owed['plain'], $owed['owed_ore'], $owed['now_ore'], $owed['now_due'], $owed['later']]);
    }

    /** Review LR2-1: a part payment on the open item leaves the rest to pay; a whole open item is shown as before; nothing owed shows no amount. */
    public function testAPartPaymentLeavesTheRestAndAnInvoiceOwedInFullIsShownAsBefore(): void
    {
        $GLOBALS['stripe_charge_today'] = '2026-01-25';
        $bank = ['bankBox' => '<div class="bank">Betal via bankoverførsel</div>'];
        $part = StripeFixtures::invoice();
        $this->bankPayment($part, '-100.000', false);
        $panel = $this->render(stripe_invoice_load($part), $bank)['panel'];
        $this->assertStringContainsString('<span>At betale</span><span>Forfaldt 16.01.2026</span></div><b class="num">240,00 kr.</b>', $panel);
        $this->assertStringContainsString('<span>Fakturabeløb</span><b class="num">340,00 kr.</b>', $panel);

        // The whole total owed at one date (here with card payment of invoices off): the amount box as before.
        $whole = StripeFixtures::invoice();
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'off');
        $inv = stripe_invoice_load($whole);
        $this->assertSame('nocard', $inv['state']);
        $this->assertTrue(stripe_invoice_owed($inv)['plain']);
        $panel = $this->render($inv, $bank)['panel'];
        $this->assertStringContainsString('<div class="amt">Amount</div><div class="bank">', $panel);
        $this->assertStringNotContainsString('Fakturabeløb', $panel);
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'on');

        // An unsettled payment of the whole amount: the open items owe nothing, so no amount and no bank box.
        $pair = StripeFixtures::invoice();
        $this->bankPayment($pair, '-340.000', false);
        $panel = $this->render(stripe_invoice_load($pair), $bank)['panel'];
        $this->assertStringContainsString('<h2>Kontakt os om betalingen</h2>', $panel);
        foreach (['class="amt"', 'At betale', 'class="bank"'] as $text) {
            $this->assertStringNotContainsString($text, $panel, $text);
        }

        // Part of the invoice credited by an invoiced credit note that is not settled against it: the rest.
        $debtor = StripeFixtures::debtor();
        $credited = StripeFixtures::invoice($debtor);
        StripeFixtures::invoice($debtor, ['art' => 'DK', 'kred_ord_id' => $credited, 'sum' => -80, 'moms' => -20]);
        $inv = stripe_invoice_load($credited);
        $this->assertSame('manual', $inv['state']);
        $this->assertStringContainsString('<b class="num">240,00 kr.</b>', $this->render($inv, $bank)['panel']);
    }

    /** Review GPT-H-2: a part payment entered in another currency counts in the invoice's currency by its own rate; without a usable rate no amount is named. */
    public function testAPartPaymentInAnotherCurrencyCountsByItsRateAndWithoutOneNoAmountIsNamed(): void
    {
        $GLOBALS['stripe_charge_today'] = '2026-01-25';
        $bank = ['bankBox' => '<div class="bank">Betal via bankoverførsel</div>'];
        // An open item as the journal posts a bank payment with the invoice number, in the journal line's currency and rate.
        $payment = static function (int $invoice, string $amount, string $valuta, string $kurs): int {
            $o = StripeFixtures::row('ordrer', $invoice);
            return StripeFixtures::insert('openpost', ['konto_id' => (int)$o['konto_id'], 'konto_nr' => $o['kontonr'], 'faktnr' => $o['fakturanr'],
                'amount' => $amount, 'udlignet' => '0', 'transdate' => StripeFixtures::TODAY, 'forfaldsdate' => StripeFixtures::TODAY,
                'valuta' => $valuta, 'valutakurs' => $kurs, 'projekt' => '']);
        };
        $noAmount = function (array $inv) use ($bank): void {
            $this->assertSame(['plain' => false, 'unknown' => true, 'owed_ore' => 0, 'now_ore' => 0, 'now_due' => '', 'later' => []], stripe_invoice_owed($inv));
            $panel = $this->render($inv, $bank)['panel'];
            $this->assertStringContainsString('<h2>Kontakt os om betalingen</h2>', $panel);
            foreach (['class="amt"', 'At betale', 'Fakturabeløb', 'class="bank"', 'Senere rate'] as $text) {
                $this->assertStringNotContainsString($text, $panel, $text);
            }
        };

        // A 340 kr. invoice and a bank payment of EUR 20 at 750 (150 kr.), not settled yet: 190 kr. is left, not 320.
        $id = StripeFixtures::invoice();
        $payment($id, '-20.000', 'EUR', '750.000');
        $inv = stripe_invoice_load($id);
        $this->assertSame('manual', $inv['state']);
        $owed = stripe_invoice_owed($inv);
        $this->assertSame([false, false, 19000, 19000, '2026-01-16', []],
            [$owed['plain'], $owed['unknown'], $owed['owed_ore'], $owed['now_ore'], $owed['now_due'], $owed['later']]);
        $panel = $this->render($inv, $bank)['panel'];
        $this->assertStringContainsString('<h2>Kontakt os om betalingen</h2>', $panel);
        $this->assertStringContainsString('<span>At betale</span><span>Forfaldt 16.01.2026</span></div><b class="num">190,00 kr.</b>', $panel);
        $this->assertStringContainsString('<span>Fakturabeløb</span><b class="num">340,00 kr.</b>', $panel);
        $this->assertStringContainsString('class="bank"', $panel);
        $this->assertStringNotContainsString('320,00', $panel);
        // Paid in full that way (EUR 45,333 at 750 = 340 kr.): nothing is left, so no amount and no bank box, as for a payment in kroner.
        $payment($id, '-25.333', 'EUR', '750.000');
        $this->assertSame(0, stripe_invoice_owed(stripe_invoice_load($id))['owed_ore']);

        // The same payment without its rate: what is left is not known.
        $noRate = StripeFixtures::invoice();
        $payment($noRate, '-20.000', 'EUR', '0.000');
        $inv = stripe_invoice_load($noRate);
        $this->assertSame('manual', $inv['state']);
        $noAmount($inv);

        // An invoice in EUR: a part payment in EUR is set against it as it is; one in kroner holds no rate to euro, so no amount.
        $eur = StripeFixtures::invoice(null, ['valuta' => 'EUR', 'valutakurs' => '746.000']);
        StripeTestEnvironment::query("UPDATE openpost SET valuta = 'EUR', valutakurs = '746.000' WHERE id = $1", [StripeReminderFixtures::openpost($eur)['id']]);
        $payment($eur, '-100.000', 'EUR', '746.000');
        $inv = stripe_invoice_load($eur);
        $this->assertSame(['manual', 24000], [$inv['state'], stripe_invoice_owed($inv)['owed_ore']]);
        $this->assertStringContainsString('<b class="num">240,00 EUR</b>', $this->render($inv, $bank)['panel']);
        $payment($eur, '-50.000', 'DKK', '100.000');
        $noAmount(stripe_invoice_load($eur));
    }

    /** Review PAY-R2-2: an overpaid capture gets no receipt and saves no card, so the page promises neither. */
    public function testAnOverpaidCapturePromisesNoReceiptAndNoSavedCard(): void
    {
        $id = StripeFixtures::invoice();
        // The bank payment was posted while the customer authenticated; the capture is booked as the customer's credit.
        StripeFixtures::insert('stripe_payments', ['ordre_id' => $id, 'payment_intent_id' => 'pi_overpaid_page', 'amount_ore' => 34000,
            'status' => 'succeeded', 'source' => 'page', 'save_card' => true, 'card_state' => 'skipped', 'last_error' => 'overpaid',
            'pm_brand' => 'visa', 'pm_last4' => '3155', 'succeeded_at' => '2026-01-10 12:00:00']);
        StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on' WHERE id = $1", [$id]);
        $inv = stripe_invoice_load($id);
        $this->assertSame('paid', $inv['state']);
        $panel = $this->render($inv)['panel'];
        $this->assertStringContainsString('<h2>Betalingen er modtaget</h2><p class="lead">Vi har modtaget din betaling. Fakturaen var allerede betalt, så vi kontakter dig om beløbet.</p>', $panel);
        $this->assertStringContainsString('340,00 kr. · Visa •••• 3155', $panel);
        foreach (['kvittering', 'Kortet er gemt', 'class="card-row"'] as $text) {
            $this->assertStringNotContainsString($text, $panel, $text);
        }

        // An ordinary capture that saved the card: the receipt and the saved card as before.
        $other = StripeFixtures::invoice();
        StripeFixtures::insert('stripe_payments', ['ordre_id' => $other, 'payment_intent_id' => 'pi_paid_saved_page', 'amount_ore' => 34000,
            'status' => 'succeeded', 'source' => 'page', 'save_card' => true, 'card_state' => 'applied',
            'pm_brand' => 'visa', 'pm_last4' => '4242', 'succeeded_at' => '2026-01-10 12:00:00']);
        $panel = $this->render(stripe_invoice_load($other))['panel'];
        $this->assertStringContainsString('<h2>Tak for din betaling</h2><p class="lead">Fakturaen er betalt. Vi sender en kvittering til debtor@stripe-tests.invalid.</p>', $panel);
        $this->assertStringContainsString('Kortet er gemt til dine næste fakturaer', $panel);
    }
}
