<?php
// 20261022 CDX/LH Exercise production invoice panels and consent defaults without session/master/bootstrap side effects.
// 20261022 CDX/LH Render actual paid/credited presentation locals and assert the unavailable Saldi menu hint is absent.
// 20261022 CDX/LH Assert whole-row consent appears between the amount box and Payment Element in every consent panel.
// 20261025 CL/ASR Recurring invoices without automatic payment: consent text above Stripe and two pay buttons; fee note.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/stripeIncludes/stripeManualFee.php';

final class StripePageTest extends StripeDatabaseTestCase
{
    /**
     * Execute the actual page's panel switch with a tenant fixture and presentation locals.
     * The HTTP/bootstrap and external renderer are deliberately outside this tenant-only test.
     *
     * @return array{panel: string, config: array<string, mixed>} Rendered controls and actual browser configuration.
     */
    private function render(array $inv): array
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
}
