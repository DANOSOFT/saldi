<?php
// 20261022 CDX/LH Preserve Stripe order selection, validate Afmeld tokens and identify the current saved card.
// 20261126 CL/ASR Review round 1 (I10): Stripe is offered only where saved cards are charged; a stored Stripe that cannot be charged says it goes out as e-mail; the card row only where invoice payments are used.
// 20261126 ASR Review CARD2-2: a credit note is not offered Stripe, also for a customer whose invoices are charged; a stored Stripe on one says it is printed as PDF; ordre.php passes the order's art.

require_once __DIR__ . '/support/bootstrap.php';
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
        $this->assertSame(2, preg_match_all('/if \(\$udskriv_til === \x27Stripe\x27\) \{\s*print orderStripeDeliveryOption\(\$udskriv_til, \$konto_id, \(string\)\$art\);\s*\} elseif \(\$udskriv_til === \x27Link\x27\) \{[^}]*\} elseif \(\$showLocalPrint && \$localPrint == \x27on\x27\)/', $source));
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
        $this->assertStringContainsString('$udskriv_til = stripe_enrolled_copy_delivery($r, $fakturadate);', $genfakt);
        $call = strpos($genfakt, 'stripe_enrolled_copy_delivery($r, $fakturadate)');
        $this->assertLessThan(strpos($genfakt, "\$qtxt.= \"lev_postnr,lev_bynavn,levdate,fakturadate,nextfakt"), $call);
        // The copy's date is known before it is asked for, and a Stripe template is asked too.
        $this->assertLessThan($call, strpos($genfakt, 'if (!$fakturadate) $fakturadate=date("Y-m-d");'));
        $this->assertStringContainsString("\$udskriv_til == 'Stripe') && is_file(", $genfakt);
    }

    public function testGenfaktKeepsLinkOrEmailForACopyDatedBeforeTheEnrolment(): void
    {
        // Enrolled on 26 November; a series copy preset to 25 November is never charged, so it must not become Stripe.
        require_once __DIR__ . '/../../includes/stripeIncludes/stripeEnrol.php';
        $enrolled = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'stripe_customer_id' => 'cus_saved', 'stripe_date' => '2026-11-26']);
        $copy = fn(string $delivery, string $date) => stripe_enrolled_copy_delivery(
            StripeFixtures::row('ordrer', StripeFixtures::invoice($enrolled, ['udskriv_til' => $delivery, 'nextfakt' => '2026-11-25'])), $date);
        $this->assertSame('Link', $copy('Link', '2026-11-25'));
        $this->assertSame('email', $copy('email', '2026-11-25'));
        // Enrolment set the template to Stripe: the earlier copy goes as E-mail med link (classic e-mail without the payment page).
        $this->assertSame('Link', $copy('Stripe', '2026-11-25'));
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'off');
        $this->assertSame('email', $copy('Stripe', '2026-11-25'));
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'on');
        // On or after the enrolment date the copy is charged.
        foreach (['Link', 'email', 'Stripe'] as $delivery) {
            $this->assertSame('Stripe', $copy($delivery, '2026-11-26'), $delivery);
            $this->assertSame('Stripe', $copy($delivery, '2026-12-25'), $delivery);
        }
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
        $this->assertStringContainsString('$stripe_row_visible = stripe_staff_card_row_visible($r);', $source);
        $start = strpos($source, "if (!empty(\$stripe_row_visible)) {");
        $this->assertNotFalse($start);
        $end = strpos($source, '##################### LUKKET', $start);
        $block = substr($source, $start, $end - $start);
        $r = ['stripe' => 'on'];
        $stripe_status = 'on';
        $stripe_date = '2026-10-22';
        $stripe_saved_card = 'Visa •••• 4242 <card>';
        $stripe_row_visible = true;
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

    public function testStripeIsOfferedOnlyWhereSavedCardsAreChargedAndAStoredOneSaysItGoesAsEmail(): void
    {
        $saved = $_SESSION ?? [];
        foreach (file(__DIR__ . '/../../importfiler/tekster.csv', FILE_IGNORE_NEW_LINES) as $line) {
            $cells = explode("\t", $line);
            if (count($cells) >= 4) {
                $_SESSION['text_' . $cells[0] . '_1'] = $cells[1];
            }
        }
        $db = $GLOBALS['db'];
        try {
            $enrolled = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'stripe_date' => '2026-01-01']);
            $noCard = StripeFixtures::debtor(['stripe' => 'on']);
            $this->assertStringContainsString("value='Stripe'", orderStripeDeliveryOption('email', $enrolled));
            // A customer marked as signed up but without a saved card cannot be charged.
            $this->assertSame('', orderStripeDeliveryOption('email', $noCard));
            $stored = orderStripeDeliveryOption('Stripe', $noCard);
            $this->assertStringContainsString("value='Stripe' selected", $stored);
            $this->assertStringContainsString('Stripe (sendes som e-mail)', $stored);
            $this->assertStringContainsString('Fakturaen sendes som almindelig e-mail.', $stored);
            $order = StripeFixtures::invoice($noCard, ['udskriv_til' => 'Stripe']);
            $this->assertSame('pdf', stripe_invoice_delivery($order));
            // Automatic card payment, invoice payments or Stripe switched off, or a copied ledger: not offered, and a stored one goes as e-mail.
            foreach ([['enrolment', 'off'], ['invoice_payments', 'off'], ['enabled', 'off']] as [$name, $value]) {
                StripeTestEnvironment::setting('stripe', $name, $value);
                $this->assertSame('', orderStripeDeliveryOption('email', $enrolled), $name);
                $this->assertStringContainsString('Stripe (sendes som e-mail)', orderStripeDeliveryOption('Stripe', $enrolled), $name);
                $this->assertSame('pdf', stripe_invoice_delivery(StripeFixtures::invoice($enrolled, ['udskriv_til' => 'Stripe'])), $name);
                StripeTestEnvironment::setting('stripe', $name, 'on');
            }
            $GLOBALS['db'] = 'other_ledger';
            stripe_settings_flush();
            $this->assertFalse(stripe_card_charging_on());
            $this->assertSame('', orderStripeDeliveryOption('email', $enrolled));
            $this->assertStringContainsString('Stripe (sendes som e-mail)', orderStripeDeliveryOption('Stripe', $enrolled));
        } finally {
            $GLOBALS['db'] = $db;
            stripe_settings_flush();
            $_SESSION = $saved;
        }
        $this->assertTrue(stripe_card_charging_on());
        $option = orderStripeDeliveryOption('Stripe', $enrolled);
        $this->assertStringContainsString("value='Stripe' selected", $option);
        $this->assertStringNotContainsString('sendes som e-mail', $option);
    }

    public function testTheCustomerCardShowsTheStripeRowOnlyWhereInvoicePaymentsAreUsedOrForASignedUpCustomer(): void
    {
        $plain = StripeFixtures::row('adresser', StripeFixtures::debtor());
        $enrolled = StripeFixtures::row('adresser', StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved']));
        $this->assertTrue(stripe_staff_card_row_visible($plain));
        $this->assertTrue(stripe_staff_card_row_visible($enrolled));
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'off');
        $this->assertFalse(stripe_staff_card_row_visible($plain));
        // A signed-up customer keeps the row, so Afmeld can still be used.
        $this->assertTrue(stripe_staff_card_row_visible($enrolled));
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'on');
        StripeTestEnvironment::setting('stripe', 'enabled', 'off');
        $this->assertFalse(stripe_staff_card_row_visible($plain));
        StripeTestEnvironment::setting('stripe', 'enabled', 'on');
        // A ledger without the Stripe columns (MySQL) never shows it.
        unset($plain['stripe']);
        $this->assertFalse(stripe_staff_card_row_visible($plain));
    }

    private function payment(int $order, string $pm, string $customer, string $brand, string $last4): void
    {
        $row = StripeFixtures::row('ordrer', $order);
        StripeFixtures::insert('stripe_payments', ['ordre_id' => $order, 'fakturanr' => $row['fakturanr'],
            'payment_intent_id' => 'pi_staff_' . bin2hex(random_bytes(8)), 'status' => 'failed',
            'amount_ore' => 34000, 'pm_id' => $pm, 'pm_brand' => $brand, 'pm_last4' => $last4, 'stripe_customer_id' => $customer]);
    }

    /** Review CARD2-2: nothing charges or refunds a card from a credit note, so Udskriv til does not offer Stripe on one. */
    public function testACreditNoteIsNotOfferedStripeAndAStoredOneSaysItIsPrintedAsPdf(): void
    {
        $saved = $_SESSION ?? [];
        foreach (file(__DIR__ . '/../../importfiler/tekster.csv', FILE_IGNORE_NEW_LINES) as $line) {
            $cells = explode("\t", $line);
            if (count($cells) >= 4) {
                $_SESSION['text_' . $cells[0] . '_1'] = $cells[1];
            }
        }
        try {
            $enrolled = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'stripe_date' => '2026-01-01']);
            $this->assertTrue(stripe_card_charging_on());
            // The customer's invoices are charged: an invoice offers Stripe, a credit note does not.
            $this->assertStringContainsString("<option value='Stripe' title=", orderStripeDeliveryOption('email', $enrolled, 'DO'));
            $this->assertStringContainsString("<option value='Stripe' title=", orderStripeDeliveryOption('email', $enrolled));
            $this->assertSame('', orderStripeDeliveryOption('PDF', $enrolled, 'DK'));
            $this->assertSame('', orderStripeDeliveryOption('email', 0, 'DK'));
            // A stored Stripe on a credit note stays selected and says what happens: it is printed as PDF.
            $stored = orderStripeDeliveryOption('Stripe', $enrolled, 'DK');
            $this->assertSame("<option value='Stripe' selected title='Kreditnotaer trækkes ikke på kort. Kreditnotaen udskrives som PDF; vælg E-mail for at sende den til kunden.'>Stripe (udskrives som PDF)</option>\n", $stored);
            $this->assertStringNotContainsString('Trækkes automatisk', $stored);
        } finally {
            $_SESSION = $saved;
        }
        // Every Udskriv til list on the order page passes the order's art.
        $source = file_get_contents(__DIR__ . '/../../debitor/ordre.php');
        $this->assertSame(4, substr_count($source, 'print orderStripeDeliveryOption($udskriv_til, $konto_id, (string)$art);'));
        $this->assertSame(0, substr_count($source, 'print orderStripeDeliveryOption($udskriv_til, $konto_id);'));
    }
}
