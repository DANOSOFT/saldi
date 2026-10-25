<?php
// 20261022 CDX/LH Purpose isolation, due-date and signup gates, private atomic PDFs, index repair and discount totals.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/formFuncIncludes/subscriptionLink.php';

final class StripeSecurityTest extends StripeDatabaseTestCase
{
    private ?string $artifactRoot = null;
    private $previousPdfEnv = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousPdfEnv = getenv('SALDI_STRIPE_PDF_DIR');
        putenv('SALDI_STRIPE_PDF_DIR');
        $this->artifactRoot = sys_get_temp_dir() . '/saldi-s4b-' . bin2hex(random_bytes(12));
        StripeTestEnvironment::setting('stripe', 'pdf_private_dir', $this->artifactRoot);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->artifactRoot !== null) {
                stripe_invoice_pdf_cleanup($this->artifactRoot);
                if ($this->previousPdfEnv === false) {
                    putenv('SALDI_STRIPE_PDF_DIR');
                } else {
                    putenv('SALDI_STRIPE_PDF_DIR=' . $this->previousPdfEnv);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testEveryLinkPurposeRejectsEveryOtherPurposeAndLegacySignature(): void
    {
        $id = StripeFixtures::invoice();
        $purposes = ['invoice', 'subscribe', 'remove-card'];
        foreach ($purposes as $issuedFor) {
            $signature = stripe_link_sign($id, $issuedFor);
            foreach ($purposes as $usedFor) {
                $this->assertSame($issuedFor === $usedFor, stripe_link_verify($id, $signature, $usedFor), "$issuedFor -> $usedFor");
            }
            $this->assertFalse(stripe_link_verify($id + 1, $signature, $issuedFor));
        }
        $legacy = hash_hmac('sha256', 'v1:' . $id, stripe_setting('link_secret'));
        foreach ($purposes as $purpose) {
            $this->assertFalse(stripe_link_verify($id, $legacy, $purpose));
        }
        $this->assertSame('', stripe_link_sign($id, 'unknown'));
        $this->assertSame('', stripe_link_sign(0, 'invoice'));
        parse_str((string)parse_url(stripe_invoice_url($id), PHP_URL_QUERY), $query);
        $this->assertTrue(stripe_link_verify($id, $query['s'], 'invoice'));
        $this->assertFalse(stripe_link_verify($id, $query['s'], 'remove-card'));
        $common = file_get_contents(__DIR__ . '/../../api/stripe/faktura_common.php');
        $this->assertStringContainsString("stripe_link_verify(\$faktura_o, \$faktura_s, 'invoice')", $common);
    }

    public function testPublicPostOriginUsesConfiguredOriginAndNeverTheHostHeader(): void
    {
        $this->assertTrue(stripe_invoice_origin_allowed('https://stripe-tests.invalid'));
        $this->assertTrue(stripe_invoice_origin_allowed('https://stripe-tests.invalid:443'));
        foreach (['', 'null', 'http://stripe-tests.invalid', 'https://stripe-tests.invalid:444',
            'https://other.invalid', 'https://stripe-tests.invalid.attacker.invalid',
            'https://stripe-tests.invalid/master', 'https://user@stripe-tests.invalid'] as $origin) {
            $this->assertFalse(stripe_invoice_origin_allowed($origin), $origin);
        }
        $source = file_get_contents(__DIR__ . '/../../api/stripe/faktura_pay.php');
        $this->assertLessThan(strpos($source, 'stripe_payment_create('), strpos($source, 'stripe_invoice_origin_allowed('));
    }

    public function testEnrolledPayNowRefusesBeforeDueWithoutContactingStripe(): void
    {
        $debtor = StripeFixtures::debtor(['stripe' => 'on', 'stripe_customer_id' => 'cus_fixture', 'stripe_pm_id' => 'pm_fixture']);
        $id = StripeFixtures::invoice($debtor, ['udskriv_til' => 'Stripe']);
        $invoice = stripe_invoice_load($id);
        $this->assertFalse(stripe_invoice_pay_now_allowed($invoice, '2026-01-15'));
        $this->assertSame('not_due', stripe_payment_create($invoice, 'manual', false, 'pm_fixture')['error']);
        $this->assertSame([], StripeTestTransport::$requests);
        $this->assertTrue(stripe_invoice_pay_now_allowed($invoice, '2026-01-16'));
        $this->assertTrue(stripe_invoice_pay_now_allowed($invoice, '2026-01-17'));
        $invoice['due'] = '';
        $this->assertFalse(stripe_invoice_pay_now_allowed($invoice));
        $invoice['due'] = '2026-01-16';
        StripeTestEnvironment::setting('stripe', 'enrolment', 'off');
        $this->assertFalse(stripe_invoice_pay_now_allowed($invoice, '2026-01-16'));
    }

    public function testSignupOffRefusesOutstandingLinkAndRemovesClassicMailButton(): void
    {
        $id = StripeFixtures::order(StripeFixtures::debtor());
        StripeTestEnvironment::setting('stripe', 'signup_enabled', 'on');
        $oldLink = stripe_link_url($id);
        $this->assertNotSame('', $oldLink);
        StripeFixtures::insert('stripe_customers', ['konto_id' => StripeFixtures::row('ordrer', $id)['konto_id'],
            'stripe_customer_id' => 'cus_existing', 'stripe_subscription_id' => 'sub_existing', 'status' => 'active']);
        $before = pg_fetch_all(StripeTestEnvironment::query('SELECT * FROM stripe_customers ORDER BY id'));
        foreach (['off', null] as $switch) {
            StripeTestEnvironment::setting('stripe', 'signup_enabled', $switch);
            $this->assertFalse(stripe_signup_allowed());
            $this->assertSame('', stripe_link_url($id));
            $this->assertSame('', subscriptionLinkHtml(subscriptionLinkUrl($id)));
            $this->assertTrue(stripe_feature_on('invoice_payments'));
            $this->assertTrue(stripe_feature_on('enrolment'));
            // Execute the actual endpoint refusal block, replacing only its terminating view call.
            $source = file_get_contents(__DIR__ . '/../../api/stripe/subscribe.php');
            $this->assertSame(1, preg_match('/if \(!stripe_signup_allowed\(\)\) \{.*?\n\}/s', $source, $matches));
            $this->assertLessThan(strpos($source, '$isPost   ='), strpos($source, $matches[0]));
            $fail = static function ($status, $headline, $text): void {
                throw new RuntimeException($text, $status);
            };
            try {
                eval(str_replace('stripe_fail_page(', '$fail(', $matches[0]));
                $this->fail('Disabled signup reached request processing');
            } catch (RuntimeException $exception) {
                $this->assertSame(503, $exception->getCode());
            }
        }
        $this->assertSame($before, pg_fetch_all(StripeTestEnvironment::query('SELECT * FROM stripe_customers ORDER BY id')));
        $this->assertSame([], StripeTestTransport::$requests);
        $this->assertSame([], StripeTestTransport::$mails);
    }

    public function testPdfStorageIsPrivateOpaqueAndSeparateFromLinkSignatures(): void
    {
        $id = StripeFixtures::invoice();
        $file = stripe_invoice_pdf_target($id);
        $next = stripe_invoice_pdf_target($id + 1);
        $this->assertFalse(stripe_pdf_inside($file, realpath(__DIR__ . '/../..')));
        $this->assertStringStartsWith($this->artifactRoot . '/', $file);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}\.pdf$/', basename($file));
        $this->assertNotSame($file, $next);
        $this->assertNotSame(stripe_link_sign($id, 'invoice') . '.pdf', basename($file));
        $this->assertSame(0750, fileperms(dirname($file)) & 0777);
        $this->assertSame('', stripe_invoice_pdf_path($id, false));
        $this->assertSame('', stripe_pdf_absolute('/tmp/../public'));
        StripeTestEnvironment::setting('stripe', 'link_secret', null);
        $this->assertSame('', stripe_invoice_pdf_target($id));
    }

    public function testEligibleClassicMailLosesItsSignupButtonWhenSignupIsOff(): void
    {
        StripeTestEnvironment::query('CREATE TABLE IF NOT EXISTS stripe_catalog (
            id SERIAL PRIMARY KEY, varenr text, active boolean NOT NULL DEFAULT true)');
        $id = StripeFixtures::order(StripeFixtures::debtor());
        StripeFixtures::insert('stripe_catalog', ['varenr' => 'TEST-A', 'active' => true]);
        StripeTestEnvironment::setting('stripe', 'signup_enabled', 'on');
        $link = subscriptionLinkUrl($id);
        $this->assertNotSame('', $link);
        $this->assertStringContainsString('<a ', subscriptionLinkHtml($link));
        StripeTestEnvironment::setting('stripe', 'signup_enabled', 'off');
        $this->assertSame('', subscriptionLinkHtml(subscriptionLinkUrl($id)));
        StripeTestEnvironment::setting('stripe', 'signup_enabled', 'on');
        StripeTestEnvironment::setting('stripe', 'enabled', 'off');
        $this->assertFalse(stripe_signup_allowed());
        $this->assertSame('', subscriptionLinkUrl($id));
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testAtomicPublicationKeepsExistingReadersAndRefusesBrokenSource(): void
    {
        $id = StripeFixtures::invoice();
        $work = stripe_invoice_pdf_work_dir();
        $source = $work . '/input.pdf';
        $first = '%PDF-1.4 first ' . str_repeat('A', 256);
        $second = '%PDF-1.4 second ' . str_repeat('B', 512);
        file_put_contents($source, $first);
        $this->assertTrue(stripe_invoice_pdf_cache($id, $source));
        $file = stripe_invoice_pdf_path($id, false);
        $this->assertSame(0640, fileperms($file) & 0777);
        $reader = fopen($file, 'rb');
        try {
            file_put_contents($source, $second);
            $this->assertTrue(stripe_invoice_pdf_cache($id, $source));
            $this->assertSame($first, stream_get_contents($reader));
            $this->assertSame($second, file_get_contents($file));
            file_put_contents($source, '%PDF incomplete');
            $this->assertFalse(stripe_invoice_pdf_cache($id, $source));
            $this->assertSame($second, file_get_contents($file));
            $this->assertSame([], glob(dirname($file) . '/.publish-*'));
        } finally {
            fclose($reader);
        }
    }

    public function testInvoicePageImagesFollowThePdfAndOnlyACompleteSetIsServed(): void
    {
        // 20261025 The payment page shows the invoice as its PDF pages (faktura_side.php serves them).
        $gs = stripe_invoice_gs();
        if ($gs === '') {
            $this->markTestSkipped('Ghostscript is not installed');
        }
        $id = StripeFixtures::invoice();
        $work = stripe_invoice_pdf_work_dir();
        $source = $work . '/input.pdf';
        $makePdf = function (string $postscript) use ($gs, $source): void {
            exec(escapeshellarg($gs) . ' -q -dSAFER -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile=' . escapeshellarg($source)
                . ' -c ' . escapeshellarg('/Helvetica findfont 24 scalefont setfont ' . $postscript) . ' 2>&1', $out, $rc);
            $this->assertSame(0, $rc, implode("\n", $out));
        };
        try {
            $makePdf('72 720 moveto (Side 1) show showpage 72 720 moveto (Side 2) show showpage');
            $this->assertTrue(stripe_invoice_pdf_cache($id, $source));
            $this->assertSame([], stripe_invoice_page_images($id, false));
            $pages = stripe_invoice_page_images($id, true);
            $this->assertCount(2, $pages);
            foreach ($pages as $png) {
                $this->assertSame("\x89PNG", file_get_contents($png, false, null, 0, 4));
                $this->assertSame(0640, fileperms($png) & 0777);
                $this->assertSame(dirname(stripe_invoice_pdf_path($id, false)), dirname($png));
            }
            $this->assertSame($pages, stripe_invoice_page_images($id, false));

            // A new PDF version gets new images and the old ones are removed.
            $makePdf('72 720 moveto (Ny version med kun en side) show showpage');
            $this->assertTrue(stripe_invoice_pdf_cache($id, $source));
            $this->assertSame([], stripe_invoice_page_images($id, false));
            $current = stripe_invoice_page_images($id, true);
            $this->assertCount(1, $current);
            foreach ($pages as $old) {
                $this->assertFileDoesNotExist($old);
            }
            // Without the page-count marker the set counts as incomplete and is not served.
            unlink(preg_replace('/\.p1\.png$/', '.pages', $current[0]));
            $this->assertSame([], stripe_invoice_page_images($id, false));

            $endpoint = file_get_contents(__DIR__ . '/../../api/stripe/faktura_side.php');
            $this->assertStringContainsString("include(__DIR__ . '/faktura_common.php');", $endpoint);
            $this->assertStringContainsString("stripe_invoice_page_images(\$inv['id'], false)", $endpoint);
        } finally {
            stripe_invoice_pdf_cleanup($work);
        }
    }

    public function testUnenrolmentInvoiceMailUsesTheSharedLayoutAndKeepsThePdf(): void
    {
        // 20261025 The outstanding-invoice mail after Afmeld looks like the other invoice mails (Saldi logo).
        require_once __DIR__ . '/../../includes/stripeIncludes/stripeEnrol.php';
        $id = StripeFixtures::invoice();
        $work = stripe_invoice_pdf_work_dir();
        file_put_contents($work . '/input.pdf', '%PDF-1.4 unenrol ' . str_repeat('U', 256));
        try {
            $this->assertTrue(stripe_invoice_pdf_cache($id, $work . '/input.pdf'));
            $inv = stripe_invoice_load($id);
            $inv['brand'] = 'Saldi';
            $before = count(StripeTestTransport::$mails);
            $this->assertTrue(stripe_mail_invoice_classic($inv));
            $mail = StripeTestTransport::$mails[$before];
            $this->assertStringContainsString('/img/SaldiMainLogo.png" width="128" alt="Saldi"', $mail['html']);
            $this->assertStringContainsString('Automatisk kortbetaling er stoppet', $mail['html']);
            $this->assertCount(1, $mail['attachments']);
            $this->assertSame(stripe_invoice_pdf_path($id, false), $mail['attachments'][0]);
        } finally {
            stripe_invoice_pdf_cleanup($work);
        }
    }

    public function testRenderWorkspacesAreIsolatedCleanedAndUseOneStableInvoiceLock(): void
    {
        $id = StripeFixtures::invoice();
        $first = stripe_invoice_pdf_work_dir();
        $second = stripe_invoice_pdf_work_dir();
        $this->assertNotSame($first, $second);
        $this->assertSame(0750, fileperms($first) & 0777);
        file_put_contents($first . '/invoice.ps', 'private source');
        file_put_contents($second . '/invoice.ps', 'another render');
        stripe_invoice_pdf_cleanup($first);
        $this->assertDirectoryDoesNotExist($first);
        $this->assertFileExists($second . '/invoice.ps');
        $lock = stripe_invoice_pdf_lock($id);
        $this->assertIsResource($lock);
        $contender = fopen(stripe_invoice_pdf_target($id) . '.lock', 'c');
        try {
            $this->assertFalse(flock($contender, LOCK_EX | LOCK_NB));
            flock($lock, LOCK_UN);
            $this->assertTrue(flock($contender, LOCK_EX | LOCK_NB));
        } finally {
            fclose($lock);
            fclose($contender);
        }
        $this->assertNotSame('', stripe_invoice_pdf_php());
        $renderer = file_get_contents(__DIR__ . '/../../tools/stripe_invoice_pdf.php');
        $this->assertStringContainsString('register_shutdown_function(', $renderer);
        $this->assertStringNotContainsString('99999', $renderer);
        $this->assertStringNotContainsString('umask(0)', $renderer);
    }

    public function testDenyAllFallbackIsInstalledBeforeItCanHoldPdfArtifacts(): void
    {
        // Force the production fallback by supplying no writable private candidates.
        $fallback = realpath(__DIR__ . '/../..') . '/temp/.stripe-private-'
            . substr(hash('sha256', realpath(__DIR__ . '/../..')), 0, 24);
        $existed = is_dir($fallback);
        try {
            $this->assertSame($fallback, stripe_invoice_pdf_root([]));
            $rule = file_get_contents($fallback . '/.htaccess');
            $this->assertStringContainsString('Require all denied', $rule);
            $this->assertStringContainsString('Deny from all', $rule);
            $this->assertSame(0750, fileperms($fallback) & 0777);
            $this->assertSame(0640, fileperms($fallback . '/.htaccess') & 0777);
        } finally {
            if (!$existed) stripe_invoice_pdf_cleanup($fallback);
        }
    }

    public function testOldPublicCacheAndSharedWorkDirectoryAreSealed(): void
    {
        stripe_setting('link_secret'); // Load settings before changing the filesystem-only tenant namespace.
        $previousDb = $GLOBALS['db'];
        $legacyDb = 's4blegacy_' . bin2hex(random_bytes(6));
        $legacyRoot = realpath(__DIR__ . '/../..') . '/temp/' . $legacyDb;
        try {
            foreach (['stripe_pdf', '99999'] as $name) {
                mkdir($legacyRoot . '/' . $name, 0777, true);
                file_put_contents($legacyRoot . '/' . $name . '/123.pdf', '%PDF old artifact');
            }
            $GLOBALS['db'] = $legacyDb;
            $this->assertNotSame('', stripe_invoice_pdf_dir());
            foreach (['stripe_pdf', '99999'] as $name) {
                $this->assertStringContainsString('Require all denied', file_get_contents($legacyRoot . '/' . $name . '/.htaccess'));
                $this->assertSame(0750, fileperms($legacyRoot . '/' . $name) & 0777);
            }
        } finally {
            $GLOBALS['db'] = $previousDb;
            stripe_invoice_pdf_cleanup($legacyRoot);
        }
    }

    public function testPublicConfiguredStorageIsRejectedAndSymlinkPublicationIsRefused(): void
    {
        $id = StripeFixtures::invoice();
        $sourceDir = stripe_invoice_pdf_work_dir();
        $source = $sourceDir . '/source.pdf';
        $contents = '%PDF-1.4 protected source ' . str_repeat('X', 200);
        file_put_contents($source, $contents);
        $destination = stripe_invoice_pdf_target($id);
        symlink($source, $destination);
        $this->assertFalse(stripe_invoice_pdf_cache($id, $source));
        $this->assertSame($contents, file_get_contents($source));
        StripeTestEnvironment::setting('stripe', 'pdf_private_dir', realpath(__DIR__ . '/../..') . '/temp');
        $root = stripe_invoice_pdf_root();
        $this->assertNotSame('', $root);
        $this->assertFalse(stripe_pdf_inside($root, realpath(__DIR__ . '/../..')));
    }

    public function testEveryMissingPaymentIndexIsRepairedWithTheTablePresent(): void
    {
        foreach (['stripe_payments_pi_uidx', 'stripe_payments_succeeded_uidx', 'stripe_payments_ordre_idx'] as $name) {
            StripeTestEnvironment::query('DROP INDEX public.' . $name);
            stripe_payments_ensure();
            stripe_payments_ensure(); // An existing index must also be safe to revisit.
            $index = pg_fetch_assoc(StripeTestEnvironment::query(
                'SELECT indexdef FROM pg_indexes WHERE schemaname=current_schema() AND indexname=$1', [$name]));
            $this->assertNotFalse($index, $name);
            if ($name === 'stripe_payments_succeeded_uidx') {
                $this->assertStringContainsString('UNIQUE', $index['indexdef']);
                $this->assertStringContainsString('WHERE', $index['indexdef']);
                $this->assertStringContainsString('succeeded', $index['indexdef']);
            }
        }
        $original = $GLOBALS['db_type'];
        try {
            $GLOBALS['db_type'] = 'mysqli';
            stripe_payments_ensure(); // No PostgreSQL probes/DDL on a MySQL install.
            stripe_payment_indexes_ensure();
        } finally {
            $GLOBALS['db_type'] = $original;
        }
        $this->assertFalse($GLOBALS['db_modify_fejl']);
    }

    public function testAmountPercentageAndZeroPercentLinesMatchInvoiceFormTotals(): void
    {
        $id = StripeFixtures::invoice();
        StripeTestEnvironment::query('DELETE FROM ordrelinjer WHERE ordre_id=$1', [$id]);
        $cases = [
            [1, 200, 10, 'amount', null, 190.0],
            [2, 200, 10, 'amount', null, 380.0],
            [2, 200, 10, '', null, 360.0],
            [2, 200, 10, 'amount', '50', 190.0],
            [2, 200, 10, '', '50', 180.0],
            [1, 200, 0, '', '0', 0.0],
            [-2, 200, 10, 'amount', '50', -190.0],
            [3, 0.335, 0, '', '50', 0.51],
        ];
        foreach ($cases as $position => [$quantity, $price, $discount, $kind, $percent, $expected]) {
            StripeFixtures::insert('ordrelinjer', ['ordre_id' => $id, 'varenr' => 'TEST-DISCOUNT',
                'beskrivelse' => 'Discount case ' . $position, 'posnr' => $position + 1,
                'antal' => $quantity, 'pris' => $price, 'rabat' => $discount, 'rabatart' => $kind, 'procent' => $percent]);
        }
        $invoice = stripe_invoice_load($id);
        $this->assertSame(array_column($cases, 5), array_column($invoice['lines'], 'sum'));
        $this->assertSame(34000, $invoice['total_ore']); // Booked invoice, never recomputed from display lines.
    }
}
