<?php
// 20261022 CDX/LH Mixed batch ownership, classic/link delivery, fallback errors and recipient/archive regressions.
// 20261022 CDX/LH Use valid PDF fixtures for protected cache validation and mixed-batch ownership checks.
// 20261022 CL/LH S5 Pre-charge notice, PDF fallback, Danish decline sentences, greeting and consent texts.
// 20261022 CDX/LH Verify configured consent/notice dates and PDF fallback when automatic collection is no longer eligible.
// 20261022 CDX/LH Ensure receipts and paid/credited pages never refer to the unavailable billing menu.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/formFuncIncludes/invoiceMailDelivery.php';

final class StripeDeliveryTest extends StripeDatabaseTestCase
{
    private array $files = [];
    private array $cacheBackups = [];
    private array $classic = [];
    private string $classicResult = 'Mail sent to fixture';

    protected function tearDown(): void
    {
        try {
            foreach ($this->cacheBackups as $file => $contents) {
                if ($contents === null) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                } else {
                    file_put_contents($file, $contents);
                }
            }
            foreach ($this->files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testMixedPdfEmailEmailClassicBatchUsesEachOrdersPdfAndSubject(): void
    {
        StripeTestEnvironment::setting('email_settings', 'invoice_format', 'pdf');
        [$orders, $mailIds, $pdfs] = $this->mixedBatch();
        foreach ($mailIds as $slot => $id) {
            $result = $this->deliver($mailIds, $slot, $pdfs[$slot]);
            $this->assertSame('', $result['error']);
        }
        $this->assertSame([$orders[1], $orders[2]], array_column($this->classic, 'id'));
        $this->assertSame(array_values($pdfs), array_column($this->classic, 'pdf'));
        $this->assertSame(['invoice-B@stripe-tests.invalid', 'invoice-C@stripe-tests.invalid'], array_column($this->classic, 'email'));
        foreach ($this->classic as $index => $sent) {
            $expected = StripeFixtures::row('ordrer', $orders[$index + 1]);
            $this->assertSame('Faktura ' . $expected['fakturanr'] . ' til ' . $expected['firmanavn'], $sent['resolvedSubject']);
            $this->assertSame('%PDF-1.4 for order ' . $expected['id'], $sent['pdfIdentity']);
            $this->assertSame('Dansk', $sent['language']);
            $this->assertSame(4, $sent['form']);
            $this->assertSame('Subject $fakturanr til $firmanavn', $sent['subject']);
            $this->assertSame('Body override', $sent['text']);
            $this->assertSame('Attachment override', $sent['attachments']);
            $this->assertSame($index + 1, $sent['mailnr']);
        }
        $this->assertSame([], StripeTestTransport::$mails);
    }

    public function testMixedPdfEmailEmailLinkBatchCachesAndMailsTheMatchingInvoice(): void
    {
        [$orders, $mailIds, $pdfs] = $this->mixedBatch();
        foreach ($mailIds as $slot => $id) {
            $this->assertSame(['result' => 'OK', 'error' => ''], $this->deliver($mailIds, $slot, $pdfs[$slot]));
            $this->assertSame(file_get_contents($pdfs[$slot]), file_get_contents(stripe_invoice_pdf_path($id, false)));
        }
        $this->assertCount(2, StripeTestTransport::$mails);
        foreach ([$orders[1], $orders[2]] as $index => $id) {
            $inv = stripe_invoice_load($id);
            $mail = StripeTestTransport::$mails[$index];
            $this->assertSame($inv['email'], $mail['to']);
            $this->assertSame(stripe_txt('mail_subj_new', ['nr' => $inv['nr'], 'brand' => $inv['brand']]), $mail['subject']);
            $this->assertStringContainsString(stripe_invoice_url($id), $mail['text']);
            $this->assertStringNotContainsString(stripe_invoice_url($orders[$index === 0 ? 2 : 1]), $mail['text']);
            $this->assertSame([], $mail['attachments']);
        }
        $this->assertSame([], $this->classic);
    }

    public function testLinkMailFailureFallsBackForTheSameOrderAndErrorSurvivesLaterSuccess(): void
    {
        [$orders, $mailIds, $pdfs] = $this->mixedBatch();
        StripeTestTransport::$mailSucceeds = false;
        $failed = $this->deliver($mailIds, 1, $pdfs[1]);
        $this->assertStringContainsString('Ordre ' . $orders[1], $failed['error']);
        $this->assertStringContainsString('Klassisk PDF-mail er forsøgt', $failed['error']);
        $this->assertSame($orders[1], $this->classic[0]['id']);
        $this->assertSame($pdfs[1], $this->classic[0]['pdf']);
        $this->assertSame('invoice-B@stripe-tests.invalid', $this->classic[0]['email']);
        StripeTestTransport::$mailSucceeds = true;
        $succeeded = $this->deliver($mailIds, 2, $pdfs[2]);
        $this->assertSame('', $succeeded['error']);
        // Exercise the production batch accumulation and final return without the PDF renderer.
        $source = file_get_contents(__DIR__ . '/../../includes/formfunk.php');
        $this->assertSame(1, preg_match('/if \(\$delivery\[\x27error\x27\] !== \x27\x27\) \{\s*\$mailDeliveryErrors\[\] = \$delivery\[\x27error\x27\];\s*\}/', $source, $append));
        $this->assertSame(1, preg_match('/return \$mailDeliveryErrors \? implode\([^;]+;/', $source, $returned));
        $mailDeliveryErrors = [];
        foreach ([$failed, $succeeded] as $delivery) {
            eval($append[0]);
        }
        $this->assertSame($failed['error'], eval($returned[0]));
    }

    public function testMissingLinkConfigurationAndTransportExceptionBothFallBack(): void
    {
        $id = StripeFixtures::invoice();
        $pdf = $this->pdf($id);
        foreach (['public_base_url', 'link_secret'] as $setting) {
            $old = stripe_setting($setting);
            StripeTestEnvironment::setting('stripe', $setting, null);
            $result = $this->deliver([1 => $id], 1, $pdf);
            $this->assertNotSame('', $result['error']);
            StripeTestEnvironment::setting('stripe', $setting, $old);
        }
        StripeTestTransport::$beforeMail = static function (): void {
            throw new RuntimeException('Simulated SMTP failure');
        };
        $this->assertNotSame('', $this->deliver([1 => $id], 1, $pdf)['error']);
        $this->assertCount(3, $this->classic);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testCacheFailureAndFailedClassicFallbackAreReported(): void
    {
        $id = StripeFixtures::invoice();
        $this->classicResult = 'Missing sender mail';
        $result = $this->deliver([1 => $id], 1, __DIR__ . '/nonexistent-s4a.pdf');
        $this->assertStringContainsString('Klassisk PDF-mail kunne heller ikke sendes', $result['error']);
        $this->assertSame('Missing sender mail', $result['result']);
        $this->assertCount(1, $this->classic);
        $this->assertSame([], StripeTestTransport::$mails);
    }

    public function testClassicAndNonInvoicePathsKeepTheOriginalSenderResult(): void
    {
        $id = StripeFixtures::invoice();
        $calls = [];
        $sender = static function (...$args) use (&$calls): string {
            $calls[] = $args;
            return 'Legacy sender result';
        };
        // A non-invoice form bypasses Stripe even when the tenant prefers link delivery.
        $this->assertSame(['result' => 'Legacy sender result', 'error' => ''],
            formSendMailEntry([2 => $id], 2, 'quote.pdf', 'quote@example.invalid', 'English', 1, 'Quote subject', 'Quote body', 'Quote attachment', $sender));
        $this->assertSame([(string)$id, 'quote.pdf', 'quote@example.invalid', 'English', 1, 'Quote subject', 'Quote body', 'Quote attachment', 2], $calls[0]);
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'off');
        $this->assertSame(['result' => 'Legacy sender result', 'error' => ''],
            formSendMailEntry([1 => $id], 1, 'invoice.pdf', 'invoice@example.invalid', 'Dansk', 4, '', '', '', $sender));
        $this->assertSame([], StripeTestTransport::$mails);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testLinkMailPassesTheQueuedRecipientListWithoutFallingBack(): void
    {
        $email = 'first@example.invalid; second@example.invalid,third@example.invalid';
        $id = StripeFixtures::invoice(null, ['email' => $email]);
        $this->assertSame(['result' => 'OK', 'error' => ''], $this->deliver([1 => $id], 1, $this->pdf($id)));
        $this->assertCount(1, StripeTestTransport::$mails);
        $this->assertSame($email, StripeTestTransport::$mails[0]['to']);
        $this->assertSame(['to' => 'first@example.invalid', 'cc' => ['second@example.invalid', 'third@example.invalid'], 'bcc' => []],
            stripe_mail_envelope(StripeTestTransport::$mails[0]['to'], []));
        $this->assertSame([], $this->classic);
    }

    public function testEnrolledStripeEntryCachesOwnPdfAndMailsPreChargeNotice(): void
    {
        $debtor = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_fixture', 'stripe_customer_id' => 'cus_fixture', 'stripe_date' => StripeFixtures::INVOICE_DATE]);
        $id = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Stripe']);
        $pdf = $this->pdf($id);
        $this->assertSame(['result' => 'OK', 'error' => ''], $this->deliver([1 => $id], 1, $pdf));
        $this->assertSame(file_get_contents($pdf), file_get_contents(stripe_invoice_pdf_path($id, false)));
        $this->assertSame([], $this->classic);
        $this->assertCount(1, StripeTestTransport::$mails);
        $mail = StripeTestTransport::$mails[0];
        $inv = stripe_invoice_load($id);
        // Due 16 January (Netto 8 from 8 January) is the charge job's first primary date.
        $this->assertSame(stripe_txt('mail_subj_notice', ['nr' => $inv['nr'], 'brand' => $inv['brand'], 'date' => '16. januar 2026']), $mail['subject']);
        $this->assertSame('debtor@stripe-tests.invalid', $mail['to']);
        $this->assertSame([], $mail['attachments']);
        foreach (['Hej Anne,', '340,00 kr.', '16. januar 2026', stripe_txt('mail_card_saved'), stripe_invoice_url($id)] as $expected) {
            $this->assertStringContainsString($expected, $mail['text']);
        }
        $this->assertStringNotContainsString('kunde', $mail['text']);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testPreChargeNoticeShowsStoredSavedCardWithoutStripeCall(): void
    {
        $debtor = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_fixture', 'stripe_customer_id' => 'cus_fixture', 'stripe_date' => StripeFixtures::INVOICE_DATE]);
        $earlier = StripeFixtures::invoice($debtor);
        StripeFixtures::insert('stripe_payments', ['ordre_id' => $earlier, 'fakturanr' => '1', 'stripe_customer_id' => 'cus_fixture',
            'payment_intent_id' => 'pi_notice_card', 'amount_ore' => 34000, 'currency' => 'DKK', 'status' => 'succeeded',
            'source' => 'page', 'save_card' => true, 'pm_id' => 'pm_fixture', 'pm_brand' => 'visa', 'pm_last4' => '4242', 'attempt' => 1]);
        $id = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Stripe']);
        $inv = stripe_invoice_load($id);
        $this->assertTrue(stripe_mail_invoice_notice($inv));
        $this->assertStringContainsString('Visa •••• 4242', StripeTestTransport::$mails[0]['text']);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testFailedPreChargeNoticeFallsBackToPdfMailAndReportsIt(): void
    {
        $debtor = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_fixture', 'stripe_customer_id' => 'cus_fixture', 'stripe_date' => StripeFixtures::INVOICE_DATE]);
        $id = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Stripe']);
        $pdf = $this->pdf($id);
        $old = stripe_setting('public_base_url');
        StripeTestEnvironment::setting('stripe', 'public_base_url', null);
        $result = $this->deliver([1 => $id], 1, $pdf);
        StripeTestEnvironment::setting('stripe', 'public_base_url', $old);
        $this->assertNotSame('', $result['error']);
        $this->assertCount(1, $this->classic);
        $this->assertSame($id, $this->classic[0]['id']);
        $this->assertSame([], StripeTestTransport::$mails);
    }

    public function testConsentAndNoticeDateFollowBothChargeSettingsAcrossDst(): void
    {
        $debtor = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_fixture', 'stripe_date' => '2026-10-20']);
        $id = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Stripe']);
        StripeTestEnvironment::query("UPDATE ordrer SET fakturadate='2026-10-20' WHERE id=$1", [$id]);
        StripeTestEnvironment::query("UPDATE openpost SET forfaldsdate='2026-10-28' WHERE konto_id=$1", [$debtor]);
        $GLOBALS['stripe_charge_today'] = '2026-10-20';
        foreach (['due' => ['2026-10-28', 'forfaldsdatoen'], 'invoice_date' => ['2026-10-20', 'fakturadatoen']] as $setting => [$date, $word]) {
            StripeTestEnvironment::setting('stripe', 'charge_on', $setting);
            $inv = stripe_invoice_load($id);
            $this->assertSame($date, stripe_invoice_charge_date($inv));
            $this->assertStringContainsString($word, stripe_invoice_consent($inv));
            $this->assertStringContainsString(stripe_contact_phrase($inv), stripe_invoice_consent($inv));
            $this->assertTrue(stripe_mail_invoice_notice($inv));
            $mail = StripeTestTransport::$mails[count(StripeTestTransport::$mails) - 1];
            $this->assertStringContainsString(stripe_fmt_date_long($date), $mail['subject']);
            $this->assertStringContainsString(stripe_fmt_date_long($date), $mail['text']);
            $this->assertStringContainsString(stripe_fmt_date_long($date), $mail['html']);
            $this->assertSame([], $mail['attachments']);
        }
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testUncollectableAutomaticInvoiceDoesNotPromiseAChargeAndFallsBackToPdf(): void
    {
        foreach (['partial', 'predates_enrolment'] as $reason) {
            $debtor = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_fixture', 'stripe_date' => $reason === 'partial' ? StripeFixtures::INVOICE_DATE : '2026-01-09']);
            $id = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Stripe']);
            if ($reason === 'partial') {
                StripeTestEnvironment::query('UPDATE openpost SET amount=240 WHERE konto_id=$1', [$debtor]);
            }
            $this->assertFalse(stripe_mail_invoice_notice(stripe_invoice_load($id)));
            $result = $this->deliver([1 => $id], 1, $this->pdf($id));
            $this->assertNotSame('', $result['error']);
        }
        $this->assertCount(2, $this->classic);
        $this->assertSame([], StripeTestTransport::$mails);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testDeclineCodesBecomeDanishSentencesAndNeverLeakRawCodes(): void
    {
        $expected = [
            'insufficient_funds' => 'decline_insufficient_funds', 'card_declined' => 'decline_generic',
            'expired_card' => 'decline_expired_card', 'incorrect_cvc' => 'decline_incorrect_cvc',
            'processing_error' => 'decline_processing_error', 'authentication_required' => 'decline_authentication_required',
            'do_not_honor' => 'decline_generic', '' => 'decline_generic', null => 'decline_generic',
        ];
        foreach ($expected as $code => $key) {
            $text = stripe_decline_text($code);
            $this->assertSame(stripe_txt($key), $text);
            $this->assertStringNotContainsString('_', $text);
            if ($code !== '') {
                $this->assertStringNotContainsString((string)$code, $text);
            }
        }
        $this->assertSame('Din bank skal godkende betalingen.', stripe_decline_text('authentication_required'));
        foreach (['faktura.php', 'faktura_state.php', 'faktura_pay.php'] as $file) {
            $source = file_get_contents(__DIR__ . '/../../api/stripe/' . $file);
            $this->assertStringNotContainsString('Banken oplyser', $source);
            $this->assertStringNotContainsString("(string)\$inv['failed']['last_error'];", $source);
        }
    }

    public function testGreetingUsesContactFirstNameOrPlainHejNeverKunde(): void
    {
        $named = stripe_invoice_load(StripeFixtures::invoice());
        $this->assertSame('Hej Anne,', stripe_mail_greeting($named));
        $anonymous = stripe_invoice_load(StripeFixtures::invoice(StripeFixtures::debtor(['kontakt' => ''])));
        $this->assertSame('Hej,', stripe_mail_greeting($anonymous));
        $this->assertTrue(stripe_mail_invoice_link($anonymous));
        $this->assertStringContainsString("Hej,\n\n", StripeTestTransport::$mails[0]['text']);
        $this->assertStringNotContainsString('kunde', StripeTestTransport::$mails[0]['text']);
        StripeTestEnvironment::setting('email_settings', 'invoice_link_text', 'Hej [Fornavn], her er jeres faktura.');
        $this->assertTrue(stripe_mail_invoice_link($anonymous));
        $this->assertStringContainsString('Hej, her er jeres faktura.', StripeTestTransport::$mails[1]['text']);
        $company = stripe_invoice_load(StripeFixtures::invoice(StripeFixtures::debtor(['kontakt' => 'Stripe test debtor'])));
        $this->assertSame('Hej,', stripe_mail_greeting($company));
    }

    public function testReceiptAndPaidPageOmitUnavailableMenuAndConsentNamesContactRoute(): void
    {
        $inv = stripe_invoice_load(StripeFixtures::invoice());
        $inv['brand'] = 'Saldi';
        $this->assertTrue(stripe_mail_confirmation($inv, ['pm_brand' => 'visa', 'pm_last4' => '4242',
            'succeeded_at' => StripeFixtures::TODAY, 'amount_ore' => 34000, 'source' => 'page']));
        foreach (['html', 'text'] as $format) {
            $this->assertStringNotContainsString('Abonnement & fakturering', html_entity_decode(StripeTestTransport::$mails[0][$format], ENT_QUOTES, 'UTF-8'));
            // 20261025 the PDF button opens the invoice in the browser (vis=1 -> Content-Disposition inline)
            $this->assertMatchesRegularExpression('~/faktura_pdf\.php\?o=\d+&s=[0-9a-f]+&vis=1~', html_entity_decode(StripeTestTransport::$mails[0][$format], ENT_QUOTES, 'UTF-8'));
        }
        $this->assertStringContainsString("(!empty(\$_GET['vis']) ? 'inline' : 'attachment')", file_get_contents(__DIR__ . '/../../api/stripe/faktura_pdf.php'));
        // 20261025 Saldi's own mails show the logo; another company keeps its name as text.
        $this->assertStringContainsString('/img/SaldiMainLogo.png" width="128" alt="Saldi"', StripeTestTransport::$mails[0]['html']);
        $other = $inv;
        $other['brand'] = 'Hansen Tømrer';
        $this->assertStringNotContainsString('SaldiMainLogo', stripe_mail_layout($other, 'L', 'H', 'B', null, [], '', '', '', '', ''));
        $this->assertStringContainsString('margin-bottom:32px">Hansen Tømrer</div>', stripe_mail_layout($other, 'L', 'H', 'B', null, [], '', '', '', '', ''));
        $page = file_get_contents(__DIR__ . '/../../api/stripe/faktura.php');
        $this->assertStringNotContainsString('Abonnement & fakturering', $page);
        $this->assertStringNotContainsString('find_in_saldi', $page);
        $this->assertStringNotContainsString('saldiHint', $page);
        $phrase = stripe_contact_phrase($inv);
        $email = trim((string)($inv['firma']['email'] ?? ''));
        $this->assertSame($email !== '' ? 'kontakt os på ' . $email : 'kontakt os', $phrase);
        $consent = stripe_txt('chk_sub', ['brand' => 'Saldi', 'kontakt' => $phrase]);
        $this->assertStringContainsString('forfaldsdatoen', $consent);
        $this->assertStringContainsString($phrase, $consent);
        $this->assertStringNotContainsString('Indstillinger', $consent);
        $this->assertStringNotContainsString('Indstillinger', stripe_txt('manage_hint', ['kontakt' => $phrase]));
        $this->assertStringNotContainsString('ikke igen.', stripe_txt('mail_note_failed', ['retry' => '2. november 2026']));
    }

    public function testSemicolonCommaAndMixedRecipientsKeepCcAndArchiveBcc(): void
    {
        foreach (['a@example.invalid;b@example.invalid', 'a@example.invalid, b@example.invalid', ' a@example.invalid ; b@example.invalid,c@example.invalid '] as $recipients) {
            $envelope = stripe_mail_envelope($recipients, ['archive' => 'archive@example.invalid;copies@example.invalid', 'employee' => 'staff@example.invalid']);
            $recorder = new class {
                public array $to = [];
                public array $cc = [];
                public array $bcc = [];
                public function AddAddress($v): void { $this->to[] = $v; }
                public function AddCC($v): void { $this->cc[] = $v; }
                public function AddBCC($v): void { $this->bcc[] = $v; }
            };
            stripe_mail_apply_envelope($recorder, $envelope);
            $this->assertSame(['a@example.invalid'], $recorder->to);
            $this->assertSame(strpos($recipients, 'c@example') !== false ? ['b@example.invalid', 'c@example.invalid'] : ['b@example.invalid'], $recorder->cc);
            $this->assertSame(['archive@example.invalid', 'copies@example.invalid', 'staff@example.invalid'], $recorder->bcc);
        }
        foreach (['', 'a@example.invalid;bad', 'a@example.invalid;', "a@example.invalid\r\nBcc:other@example.invalid"] as $invalid) {
            $this->assertNull(stripe_mail_envelope($invalid, []));
        }
        $this->assertNull(stripe_mail_envelope('a@example.invalid', ['archive' => 'invalid']));
    }

    public function testSenderSettingsAndEmployeeArchiveMatchClassicMail(): void
    {
        StripeTestEnvironment::query("UPDATE adresser SET email='company@example.invalid', mailfakt='on' WHERE art='S'");
        StripeTestEnvironment::setting('email_settings', 'sender_email', 'archive@example.invalid;copies@example.invalid');
        StripeTestEnvironment::setting('email_settings', 'sender_name', 'Fixture sender');
        $employee = StripeFixtures::insert('ansatte', ['email' => 'staff@example.invalid']);
        $sender = stripe_mail_sender($employee);
        $this->assertSame('archive@example.invalid', $sender['from']);
        $this->assertSame('Fixture sender', $sender['name']);
        $this->assertSame(['archive@example.invalid', 'copies@example.invalid', 'staff@example.invalid'], stripe_mail_envelope('a@example.invalid;b@example.invalid', $sender)['bcc']);
        StripeTestEnvironment::query("UPDATE adresser SET mailfakt='' WHERE art='S'");
        $this->assertSame('', stripe_mail_sender($employee)['employee']);
    }

    /** Queue the real production assignment block, skipping the PDF entry in a mixed source batch. */
    private function mixedBatch(): array
    {
        $ordre_id = [];
        foreach (['A', 'B', 'C'] as $name) {
            $debtor = StripeFixtures::debtor(['firmanavn' => 'Debtor ' . $name, 'email' => 'invoice-' . $name . '@stripe-tests.invalid']);
            $ordre_id[] = StripeFixtures::invoice($debtor, ['udskriv_til' => $name === 'A' ? 'PDF' : 'email']);
        }
        $source = file_get_contents(__DIR__ . '/../../includes/formfunk.php');
        $this->assertSame(1, preg_match('/\$email\[\$mailantal\] = \$email\[0\];.*?\$mail_ordre_id\[\$mailantal\] = \$ordre_id\[\$o\];/s', $source, $queued));
        $this->assertStringContainsString('formSendMailEntry($mail_ordre_id, $x,', $source);
        $this->assertStringNotContainsString('$o = $x - 1;', $source);
        $mailantal = 0;
        $email = $mailsprog = $form_nr = $pfliste = $mail_ordre_id = $pdfs = [];
        foreach ($ordre_id as $o => $id) {
            $row = StripeFixtures::row('ordrer', $id);
            if ($row['udskriv_til'] !== 'email') {
                continue;
            }
            $mailantal++;
            $email[0] = $row['email'];
            $formularsprog = 'Dansk';
            $formular = 4;
            $printfilnavn = 'invoice-' . $id;
            eval($queued[0]);
            $pdfs[$mailantal] = $this->pdf($id);
        }
        $this->assertSame([1 => $ordre_id[1], 2 => $ordre_id[2]], $mail_ordre_id);
        return [$ordre_id, $mail_ordre_id, $pdfs];
    }

    private function pdf(int $id): string
    {
        $file = tempnam(__DIR__ . '/../../temp/' . $GLOBALS['db'], 'stripe-s4a-');
        $this->files[] = $file;
        file_put_contents($file, '%PDF-1.4 for order ' . $id . "\n" . str_repeat('%PDF fixture content ', 10));
        return $file;
    }

    private function deliver(array $mailIds, int $slot, string $pdf): array
    {
        $id = (int)$mailIds[$slot];
        $oldCache = stripe_invoice_pdf_path($id, false);
        if ($oldCache && !array_key_exists($oldCache, $this->cacheBackups)) {
            $this->cacheBackups[$oldCache] = file_get_contents($oldCache);
        }
        $result = formSendMailEntry($mailIds, $slot, $pdf, StripeFixtures::row('ordrer', $id)['email'], 'Dansk', 4,
            'Subject $fakturanr til $firmanavn', 'Body override', 'Attachment override',
            function ($orderId, $file, $email, $language, $form, $subject, $text, $attachments, $mailnr): string {
                $row = StripeFixtures::row('ordrer', (int)$orderId);
                $this->classic[] = ['id' => (int)$orderId, 'pdf' => $file, 'email' => $email, 'language' => $language,
                    'form' => $form, 'subject' => $subject, 'text' => $text, 'attachments' => $attachments, 'mailnr' => $mailnr,
                    'resolvedSubject' => 'Faktura ' . $row['fakturanr'] . ' til ' . $row['firmanavn'],
                    'pdfIdentity' => is_file($file) ? strtok(file_get_contents($file), "\n") : 'missing'];
                return $this->classicResult;
            });
        $newCache = stripe_invoice_pdf_path($id, false);
        if ($newCache && !array_key_exists($newCache, $this->cacheBackups)) {
            $this->cacheBackups[$newCache] = null;
        }
        return $result;
    }
}
