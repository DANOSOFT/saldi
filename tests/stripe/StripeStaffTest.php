<?php
// 20261022 CDX/LH Preserve Stripe order selection, validate Afmeld tokens and identify the current saved card.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../debitor/orderIncludes/orderDelivery.php';
require_once __DIR__ . '/../../includes/stripeIncludes/stripeStaff.php';

final class StripeStaffTest extends StripeDatabaseTestCase
{
    public function testStoredStripeWinsOverMailOioPbsAndLocalPrintAndStaysSelected(): void
    {
        $debtor = StripeFixtures::debtor(['stripe' => 'on']);
        foreach ([false, true] as $mail) {
            foreach ([false, true] as $oioubl) {
                foreach ([false, true] as $pbs) {
                    $selected = orderDeliveryValue('Stripe', $mail, $oioubl, $pbs);
                    $this->assertSame('Stripe', $selected);
                    $this->assertStringContainsString("value='Stripe' selected", orderStripeDeliveryOption($selected, $debtor));
                }
            }
        }
        // Both layouts execute this production branch before checking the printer cookie.
        $source = file_get_contents(__DIR__ . '/../../debitor/ordre.php');
        // 20261025: a stored Link sits between Stripe and the local printer preference (it wins over it too).
        $this->assertSame(2, preg_match_all('/if \(\$udskriv_til === \x27Stripe\x27\) \{\s*print orderStripeDeliveryOption\(\$udskriv_til, \$konto_id\);\s*\} elseif \(\$udskriv_til === \x27Link\x27\) \{[^}]*\} elseif \(\$showLocalPrint && \$localPrint == \x27on\x27\)/', $source));
        // A stored Stripe order remains visible even if its debtor is no longer enrolled.
        $this->assertStringContainsString("value='Stripe' selected", orderStripeDeliveryOption('Stripe', 0));
        $this->assertSame('email', orderDeliveryValue('PDF', true, false, false));
        // 20261025 "E-mail med link" is kept, and a new e-mail order gets the G6.3 default (link in the suite).
        $this->assertSame('Link', orderDeliveryValue('Link', true, false, false));
        $this->assertSame('Link', orderDeliveryValue('', true, false, false));
        $this->assertStringContainsString("value='Link' selected", orderLinkDeliveryOption('Link'));
        $this->assertSame('oioubl', orderDeliveryValue('PDF', true, true, false));
        $this->assertSame('PBS', orderDeliveryValue('PDF', true, true, true));
    }

    public function testLinkCannotBeChosenWhenTheOrderCannotUseThePaymentPage(): void
    {
        // 20261025 Kontant/Kreditkort, foreign currency etc.: "E-mail med link" is disabled and the text says what to do.
        $saved = $_SESSION ?? [];
        foreach (file(__DIR__ . '/../../importfiler/tekster.csv', FILE_IGNORE_NEW_LINES) as $line) {
            $cells = explode("\t", $line);
            if (count($cells) >= 4) {
                $_SESSION['text_' . $cells[0] . '_1'] = $cells[1];
            }
        }
        try {
            $debtor = StripeFixtures::debtor();
            $netto = StripeFixtures::order($debtor);
            $cash = StripeFixtures::order($debtor, ['betalingsbet' => 'Kontant', 'betalingsdage' => 0]);
            $this->assertSame('', orderLinkBlockReason($netto));
            $this->assertSame('terms', orderLinkBlockReason($cash));
            // Posted but not yet saved terms/currency decide on save.
            $this->assertSame('terms', orderLinkBlockReason($netto, ['betalingsbet' => 'Kreditkort']));
            $this->assertSame('', orderLinkBlockReason($cash, ['betalingsbet' => 'Netto']));
            $this->assertSame('currency', orderLinkBlockReason($netto, ['valuta' => 'EUR']));
            $this->assertSame('', orderLinkBlockReason($netto, ['betalingsbet' => null, 'valuta' => '']));

            $option = orderLinkDeliveryOption('', 'terms');
            $this->assertStringContainsString("value='Link' disabled", $option);
            $this->assertStringContainsString('kræver Netto eller Lb. md.', $option);
            $this->assertStringNotContainsString('disabled', orderLinkDeliveryOption('', ''));

            $this->assertStringContainsString('Vælg Netto eller Lb. md.', orderLinkBlockText('terms', false));
            $this->assertStringContainsString('almindelig e-mail med PDF i stedet', orderLinkBlockText('terms', true));
            $notice = orderLinkNotice('terms', true, false);
            $this->assertStringContainsString("id='orderLinkNotice'", $notice);
            $this->assertStringContainsString('kontant eller kreditkort', $notice);
            $this->assertStringContainsString('display:none', orderLinkNotice('terms', false, false));
            $this->assertStringContainsString('display:none', orderLinkNotice('credit', true, true));

            $this->assertStringContainsString('kontant|kreditkort', orderLinkTermsScript($netto, false));
            $this->assertSame('', orderLinkTermsScript(StripeFixtures::order($debtor, ['valuta' => 'EUR']), false));
            // Both dropdowns render the block, and the save path refuses a posted Link.
            $source = file_get_contents(__DIR__ . '/../../debitor/ordre.php');
            $this->assertSame(2, substr_count($source, "print orderLinkDeliveryOption('', \$linkBlock);"));
            $this->assertStringContainsString("orderLinkBlockReason(\$id, array('betalingsbet' => \$_POST['betalingsbet'] ?? null));", $source);
            $this->assertSame(2, substr_count($source, "if (\$linkChosen && orderLinkHardBlock(\$linkBlock)) \$udskriv_til = 'email';"));
            // Fixed on the order -> blocked and switched; temporary (page off, card opt-out, subscriber) -> stored Link kept.
            foreach (['terms', 'currency', 'credit', 'debtor', 'pbs'] as $hard) {
                $this->assertTrue(orderLinkHardBlock($hard), $hard);
            }
            foreach (['ledger', 'no_card', 'subscriber', ''] as $soft) {
                $this->assertFalse(orderLinkHardBlock($soft), $soft);
            }
            $this->assertStringContainsString("value='Link' selected", orderLinkDeliveryOption('Link', 'no_card'));
            $this->assertStringContainsString("value='Link' disabled", orderLinkDeliveryOption('', 'no_card'));
            // A stored Link wins over the per-user local printer preference, in both views (like Stripe).
            $this->assertSame(2, preg_match_all('/\} elseif \(\$udskriv_til === \x27Link\x27\) \{\s*\/\/[^\n]*\n\s*print orderLinkDeliveryOption\(\x27Link\x27\);\s*\} elseif \(\$showLocalPrint && \$localPrint == \x27on\x27\)/', $source));
            // Quotes/order confirmations of Stripe orders are not mailed: the draft button says Udskriv.
            $this->assertStringContainsString("if (\$udskriv_til == 'email' || \$udskriv_til == 'Link') { // 20261025 CL/ASR Stripe quotes/confirmations", $source);
            // The recurring editor keeps mail_fakt for Link, so those invoices are still mailed.
            $this->assertSame(2, substr_count(file_get_contents(__DIR__ . '/../../debitor/ret_genfakt.php'),
                "(\$udskriv_til[\$x]=='email' || \$udskriv_til[\$x]=='Link')?\$mail_fakt[\$x]='on'"));
        } finally {
            $_SESSION = $saved;
        }
    }

    public function testGenfaktGivesAnEnrolledCustomerTheNextInvoiceAsStripeInEverySeries(): void
    {
        // 20261027 Enrolment on any invoice (also a one-off) covers the customer's next invoices in every series.
        require_once __DIR__ . '/../../includes/stripeIncludes/stripeEnrol.php';
        $enrolled = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'stripe_customer_id' => 'cus_saved']);
        $copy = fn(int $debtor, array $order = []) => stripe_enrolled_copy_delivery(
            StripeFixtures::row('ordrer', StripeFixtures::invoice($debtor, array_replace(['nextfakt' => '2026-02-08'], $order))));
        foreach (['Link' => 'Stripe', 'email' => 'Stripe', 'PBS' => 'PBS', 'oioubl' => 'oioubl', 'PDF' => 'PDF', 'Stripe' => 'Stripe'] as $delivery => $expected) {
            $this->assertSame($expected, $copy($enrolled, ['udskriv_til' => $delivery]), $delivery);
        }
        // Not enrolled, or the series cannot be paid by card (terms, currency, PBS, card opt-out): unchanged.
        $this->assertSame('Link', $copy(StripeFixtures::debtor()));
        $this->assertSame('Link', $copy(StripeFixtures::debtor(['stripe' => 'on'])));
        $this->assertSame('Link', $copy($enrolled, ['betalingsbet' => 'Kontant']));
        $this->assertSame('Link', $copy($enrolled, ['valuta' => 'EUR']));
        $this->assertSame('email', $copy($enrolled, ['udskriv_til' => 'email', 'pbs' => 'BS']));
        $this->assertSame('Link', $copy(StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_x', 'stripe_fravalg' => 'on'])));
        // A customer who still has an old Stripe subscription is never charged twice.
        $subscriber = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_sub']);
        StripeFixtures::insert('stripe_customers', ['konto_id' => $subscriber, 'stripe_customer_id' => 'cus_copy_sub', 'stripe_subscription_id' => 'sub_copy', 'status' => 'active']);
        $this->assertSame('Link', $copy($subscriber));
        StripeTestEnvironment::setting('stripe', 'enrolment', 'off');
        $this->assertSame('Link', $copy($enrolled));
        StripeTestEnvironment::setting('stripe', 'enrolment', 'on');
        $genfakt = file_get_contents(__DIR__ . '/../../debitor/genfakturer.php');
        $this->assertStringContainsString('$udskriv_til = stripe_enrolled_copy_delivery($r);', $genfakt);
        $this->assertLessThan(strpos($genfakt, "\$qtxt.= \"lev_postnr,lev_bynavn,levdate,fakturadate,nextfakt"), strpos($genfakt, 'stripe_enrolled_copy_delivery($r)'));
    }

    public function testExistingEmailCustomersAndRecurringOrdersSwitchToLinkOnce(): void
    {
        // 20261025 Go-live: rows set to "E-mail" before "E-mail med link" existed are switched once from Stripe settings.
        $debtor = StripeFixtures::debtor(['udskriv_til' => 'email']);
        $cash = StripeFixtures::debtor(['udskriv_til' => 'email', 'betalingsbet' => 'Kontant']);
        $recurring = StripeFixtures::order($debtor, ['udskriv_til' => 'email', 'nextfakt' => '2026-02-08']);
        $oneOff = StripeFixtures::order($debtor, ['udskriv_til' => 'email', 'nextfakt' => null]);
        $euro = StripeFixtures::order($debtor, ['udskriv_til' => 'email', 'nextfakt' => '2026-02-08', 'valuta' => 'EUR']);
        $before = stripe_link_conversion(false);
        $this->assertGreaterThanOrEqual(1, $before['customers']);
        $this->assertGreaterThanOrEqual(1, $before['orders']);
        $this->assertSame('email', StripeFixtures::row('ordrer', $recurring)['udskriv_til']);
        stripe_link_conversion(true);
        $this->assertSame('Link', StripeFixtures::row('adresser', $debtor)['udskriv_til']);
        $this->assertSame('email', StripeFixtures::row('adresser', $cash)['udskriv_til']);
        $this->assertSame('Link', StripeFixtures::row('ordrer', $recurring)['udskriv_til']);
        $this->assertSame('email', StripeFixtures::row('ordrer', $oneOff)['udskriv_til']);
        $this->assertSame('email', StripeFixtures::row('ordrer', $euro)['udskriv_til']);
        $this->assertSame(['customers' => 0, 'orders' => 0], stripe_link_conversion(false));
        $this->assertStringContainsString("stripe_link_conversion(true)", file_get_contents(__DIR__ . '/../../systemdata/diverseIncludes/stripeValg.php'));
    }

    public function testEnrolledDebtorCanChooseStripeWithoutOverwritingAnExplicitOtherMode(): void
    {
        $debtor = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved']);
        $option = orderStripeDeliveryOption('email', $debtor);
        $this->assertStringContainsString("value='Stripe'", $option);
        $this->assertStringNotContainsString(' selected', $option);
        $other = StripeFixtures::debtor();
        $this->assertSame('', orderStripeDeliveryOption('email', $other));
    }

    public function testPartialSavePreservesStoredStripeAndExplicitChoiceCanReplaceIt(): void
    {
        $id = StripeFixtures::invoice(null, ['udskriv_til' => 'Stripe']);
        foreach ([[], ['udskriv_til' => ''], ['udskriv_til' => ['invalid']], ['udskriv_til' => 'Stripe']] as $input) {
            $this->assertSame('Stripe', orderDeliveryPostedValue($input, $id));
        }
        foreach (['email', 'PDF', 'localPrint', 'oioubl'] as $picked) {
            $this->assertSame($picked, orderDeliveryPostedValue(['udskriv_til' => $picked], $id));
        }
        $this->assertSame('Stripe', StripeFixtures::row('ordrer', $id)['udskriv_til']);
        $this->assertStringContainsString('db_escape_string(orderDeliveryPostedValue($_POST, $id))', file_get_contents(__DIR__ . '/../../debitor/ordre.php'));
    }

    public function testForgedAfmeldCannotChangeTheDebtorOrdersOrSendMail(): void
    {
        $debtor = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'udskriv_til' => 'Stripe']);
        $order = StripeFixtures::order($debtor, ['udskriv_til' => 'Stripe', 'nextfakt' => '2026-02-01']);
        $token = bin2hex(random_bytes(32));
        $beforeDebtor = StripeFixtures::row('adresser', $debtor);
        $beforeOrder = StripeFixtures::row('ordrer', $order);
        foreach ([
            ['POST', ['stripe_afmeld' => 'on']],
            ['POST', ['stripe_afmeld' => 'on', 'csrf_token' => '']],
            ['POST', ['stripe_afmeld' => 'on', 'csrf_token' => bin2hex(random_bytes(32))]],
            ['POST', ['stripe_afmeld' => 'on', 'csrf_token' => [$token]]],
            ['GET', ['stripe_afmeld' => 'on', 'csrf_token' => $token]],
        ] as [$method, $input]) {
            $this->assertFalse(stripe_staff_unenrol($debtor, $method, $input, $token));
            $this->assertSame($beforeDebtor, StripeFixtures::row('adresser', $debtor));
            $this->assertSame($beforeOrder, StripeFixtures::row('ordrer', $order));
        }
        $valid = ['stripe_afmeld' => 'on', 'csrf_token' => $token];
        $this->assertTrue(stripe_staff_afmeld_allowed('POST', $valid, $token));
        $this->assertFalse(stripe_staff_afmeld_allowed('POST', $valid, ''));
        $this->assertFalse(stripe_staff_afmeld_allowed('POST', $valid, null));
        $this->assertFalse(stripe_staff_afmeld_allowed('POST', ['csrf_token' => $token], $token));
        $this->assertSame([], StripeTestTransport::$mails);
        $this->assertSame([], StripeTestTransport::$requests);
        $source = file_get_contents(__DIR__ . '/../../debitor/debitorkort.php');
        $this->assertLessThan(strpos($source, 'print "<script'), strpos($source, 'http_response_code(403)'));
        $this->assertStringContainsString("name='csrf_token'", $source);
        $this->assertStringContainsString('stripe_staff_unenrol((int)$id, $stripeStaffMethod, $stripeStaffInput, $stripeStaffToken)', $source);
    }

    public function testSavedCardMatchesCurrentPaymentMethodDebtorAndCustomer(): void
    {
        $debtor = StripeFixtures::debtor(['stripe' => 'on', 'stripe_customer_id' => 'cus_saved', 'stripe_pm_id' => 'pm_saved']);
        $id = StripeFixtures::invoice($debtor);
        $this->payment($id, 'pm_saved', 'cus_saved', 'visa', '4242');
        // A newer one-off card must never replace the saved card display.
        $this->payment($id, 'pm_oneoff', 'cus_saved', 'mastercard', '4444');
        $this->payment($id, 'pm_saved', 'cus_other', 'amex', '0005');
        $other = StripeFixtures::invoice();
        $this->payment($other, 'pm_saved', 'cus_saved', 'mastercard', '5556');
        $this->assertSame('Visa •••• 4242', stripe_staff_saved_card(StripeFixtures::row('adresser', $debtor)));
        StripeTestEnvironment::query('UPDATE adresser SET stripe_pm_id=$1 WHERE id=$2', ['pm_oneoff', $debtor]);
        $this->assertSame('Mastercard •••• 4444', stripe_staff_saved_card(StripeFixtures::row('adresser', $debtor)));
        StripeTestEnvironment::query("UPDATE adresser SET stripe_pm_id='pm_missing' WHERE id=$1", [$debtor]);
        $this->assertSame('', stripe_staff_saved_card(StripeFixtures::row('adresser', $debtor)));
        StripeTestEnvironment::query("UPDATE adresser SET stripe='', stripe_pm_id='pm_saved' WHERE id=$1", [$debtor]);
        $this->assertSame('', stripe_staff_saved_card(StripeFixtures::row('adresser', $debtor)));
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testDebitorkortRendersCardBesideEnrollmentDateAndEscapesStoredDetails(): void
    {
        $source = file_get_contents(__DIR__ . '/../../debitor/debitorkort.php');
        $this->assertStringContainsString('$stripe_saved_card = stripe_staff_saved_card($r);', $source);
        $start = strpos($source, "if (isset(\$r['stripe']) || isset(\$stripe_status))");
        $end = strpos($source, '##################### LUKKET', $start);
        $block = substr($source, $start, $end - $start);
        $r = ['stripe' => 'on'];
        $stripe_status = 'on';
        $stripe_date = '2026-10-22';
        $stripe_saved_card = 'Visa •••• 4242 <card>';
        $bg = $bgcolor = '#fff';
        $bgcolor5 = '#eee';
        ob_start();
        try {
            eval($block);
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertStringContainsString('Tilmeldt automatisk kortbetaling 22-10-2026 · Visa •••• 4242 &lt;card&gt;', $html);
        $this->assertStringNotContainsString('<card>', $html);
        $this->assertStringContainsString('name=stripe_afmeld', $html);
    }

    private function payment(int $order, string $pm, string $customer, string $brand, string $last4): void
    {
        $row = StripeFixtures::row('ordrer', $order);
        StripeFixtures::insert('stripe_payments', ['ordre_id' => $order, 'fakturanr' => $row['fakturanr'],
            'payment_intent_id' => 'pi_staff_' . bin2hex(random_bytes(8)), 'status' => 'failed',
            'amount_ore' => 34000, 'pm_id' => $pm, 'pm_brand' => $brand, 'pm_last4' => $last4, 'stripe_customer_id' => $customer]);
    }
}
