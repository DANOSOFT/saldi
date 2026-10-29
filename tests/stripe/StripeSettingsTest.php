<?php
// 20261022 CDX/LH Master settings save/render regression tests with real DB escaping.
// 20261022 CDX/LH Expect the signup control to remain unchecked when its setting is absent.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../systemdata/diverseIncludes/stripeValg.php';

final class StripeSettingsTest extends StripeDatabaseTestCase
{
    private array $savedPost;
    private array $savedSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedPost = $_POST;
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = ['csrf_token' => bin2hex(random_bytes(16))];
        // Use findtekst's normal cache with the real CSV translations; no session is started.
        foreach (file(__DIR__ . '/../../importfiler/tekster.csv', FILE_IGNORE_NEW_LINES) as $line) {
            $cells = explode("\t", $line);
            if (count($cells) >= 4) {
                $_SESSION['text_' . $cells[0] . '_1'] = $cells[1];
            }
        }
        $GLOBALS['csrf_token'] = $_SESSION['csrf_token'];
        $GLOBALS['bgcolor'] = '#ffffff';
        $GLOBALS['buttonStyle'] = '';
        $_POST = $this->validPost();
    }

    protected function tearDown(): void
    {
        if (isset($this->savedPost)) {
            $_POST = $this->savedPost;
            $_SESSION = $this->savedSession;
        }
        parent::tearDown();
    }

    public function testMasterSavePreservesFreeTextAndWritesSharedKeys(): void
    {
        $_POST['invoice_link_text'] = "Hej [Fornavn], O'Brien & Søn <test>";
        $_POST['stripe_charge_on'] = 'invoice_date';
        $_POST['stripe_signup_enabled'] = '1';
        $output = $this->save();
        $this->assertSame('', $output);
        $this->assertSame($_POST['invoice_link_text'], stripe_invoice_link_text());
        $this->assertSame('link', stripe_invoice_format());
        $this->assertSame($_POST['stripe_publishable_key'], stripe_setting('publishable_key'));
        $this->assertSame('invoice_date', stripe_setting('charge_on'));
        foreach (['invoice_payments', 'enrolment', 'signup_enabled'] as $feature) {
            $this->assertTrue(stripe_feature_on($feature));
        }
        $this->assertSame($GLOBALS['db'], stripe_setting('target_db'));
        $this->assertFalse($GLOBALS['db_modify_fejl']);
    }

    public function testInvalidAndMissingCsrfTokensWriteNothing(): void
    {
        $before = hash('sha256', json_encode(pg_fetch_all(StripeTestEnvironment::query('SELECT * FROM settings ORDER BY id'))));
        $_POST['csrf_token'] = bin2hex(random_bytes(16));
        $_POST['invoice_link_text'] = 'must not be saved';
        $this->assertStringContainsString('indstillingerne blev ikke gemt', $this->save());
        unset($_POST['csrf_token']);
        $this->assertStringContainsString('indstillingerne blev ikke gemt', $this->save());
        $after = hash('sha256', json_encode(pg_fetch_all(StripeTestEnvironment::query('SELECT * FROM settings ORDER BY id'))));
        $this->assertSame($before, $after);
    }

    public function testInvalidFieldsAccumulateErrorsAndRetainStoredValues(): void
    {
        $key = stripe_setting('publishable_key');
        $_POST['stripe_publishable_key'] = 'invalid';
        $_POST['stripe_charge_on'] = 'invalid';
        $_POST['invoice_format'] = 'invalid';
        $output = $this->save();
        $this->assertStringContainsString('Publishable key afvist', $output);
        $this->assertStringContainsString('Automatisk træk afvist', $output);
        $this->assertStringContainsString('Fakturaformat afvist', $output);
        $this->assertSame($key, stripe_setting('publishable_key'));
        $this->assertSame('due', stripe_setting('charge_on'));
        $this->assertSame('link', stripe_invoice_format());
    }

    public function testUncheckedSwitchesSaveOffAndOptionalFieldsDefault(): void
    {
        unset($_POST['stripe_invoice_payments'], $_POST['stripe_enrolment'], $_POST['stripe_signup_enabled'],
            $_POST['stripe_charge_on'], $_POST['invoice_format']);
        $this->assertSame('', $this->save());
        foreach (['invoice_payments', 'enrolment', 'signup_enabled'] as $feature) {
            $this->assertFalse(stripe_feature_on($feature));
            $this->assertSame('off', stripe_setting($feature));
        }
        $this->assertSame('due', stripe_setting('charge_on'));
        $this->assertSame('pdf', stripe_invoice_format());
    }

    public function testMasterFormShowsStoredFormatAndEscapesTextWhileGateIsOff(): void
    {
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'off');
        StripeTestEnvironment::setting('stripe', 'enrolment', 'off');
        StripeTestEnvironment::setting('email_settings', 'invoice_link_text', "</textarea><script>O'Brien</script>");
        ob_start();
        try {
            stripeValg();
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertStringContainsString("name='invoice_format'", $html);
        $this->assertStringContainsString("value='link' selected", $html);
        $this->assertStringContainsString('&lt;/textarea&gt;&lt;script&gt;O&#039;Brien&lt;/script&gt;', $html);
        $this->assertStringNotContainsString("</textarea><script>O'Brien</script>", $html);
        $this->assertStringContainsString("name='stripe_invoice_payments' value='1'>", $html);
        $this->assertStringContainsString("name='stripe_enrolment' value='1'>", $html);
        $this->assertStringContainsString("name='stripe_signup_enabled' value='1'>", $html);
        $this->assertSame('pdf', stripe_invoice_format());
    }

    private function save(): string
    {
        ob_start();
        try {
            stripeValgSave();
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    private function validPost(): array
    {
        return [
            'csrf_token' => $_SESSION['csrf_token'], 'stripe_enabled' => '1',
            'stripe_mode' => 'test', 'stripe_invoice_payments' => '1', 'stripe_enrolment' => '1',
            'stripe_publishable_key' => stripe_setting('publishable_key'), 'stripe_charge_on' => 'due',
            'stripe_public_base_url' => stripe_setting('public_base_url'),
            'invoice_format' => 'link', 'invoice_link_text' => '',
        ];
    }
}
