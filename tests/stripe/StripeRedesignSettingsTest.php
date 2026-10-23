<?php
// 20261023 CDX/LH Cover tenant settings access and shared invoice preferences in the redesign.
// 20261023 CDX/LH Assert the real integration read/write permission and regress missing-table checks without warnings.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../systemdata/diverseIncludes/stripeValg.php';
require_once __DIR__ . '/../../includes/settings/SettingsService.php';

final class StripeRedesignSettingsTest extends StripeDatabaseTestCase
{
    private array $savedPost;
    private array $savedSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedPost = $_POST;
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = ['csrf_token' => bin2hex(random_bytes(16))];
        foreach (file(__DIR__ . '/../../importfiler/tekster.csv', FILE_IGNORE_NEW_LINES) as $line) {
            $cells = explode("\t", $line);
            if (count($cells) >= 4) {
                $_SESSION['text_' . $cells[0] . '_1'] = $cells[1];
            }
        }
        $GLOBALS['csrf_token'] = $_SESSION['csrf_token'];
        $GLOBALS['bgcolor'] = '#ffffff';
        $GLOBALS['buttonStyle'] = '';
        SettingsService::reset();
    }

    protected function tearDown(): void
    {
        if (isset($this->savedPost)) {
            $_POST = $this->savedPost;
            $_SESSION = $this->savedSession;
        }
        SettingsService::reset();
        parent::tearDown();
    }

    public function testStripeAppearsInTenantIntegrationMenuAndLegacyRoute(): void
    {
        $this->assertNotSame($GLOBALS['db'], $GLOBALS['sqdb']);
        $this->assertTrue(perm_can('settings.integrations', 'read'));
        $this->assertTrue(perm_can('settings.integrations', 'write'));
        $tabs = settings_section_tabs('integrations', 1, 'integrations.connections');
        $stripeTabs = array_values(array_filter($tabs, static function (array $tab): bool {
            return $tab['id'] === 'stripe_valg';
        }));
        $this->assertCount(1, $stripeTabs);
        $this->assertSame('Stripe', $stripeTabs[0]['label']);
        $this->assertSame('diverse.php?sektion=stripe_valg', $stripeTabs[0]['url']);
        $entries = settings_entries_for_request('diverse.php', ['sektion' => 'stripe_valg']);
        $this->assertCount(1, $entries);
        $this->assertTrue(settings_entry_available($entries[0]));
        $this->assertSame('settings.integrations', settings_group_permission($entries[0]['group']));
    }

    public function testTableAvailabilityHandlesAbsentOptionalTablesWithoutWarnings(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }, E_WARNING);
        try {
            $this->assertSame(1, tbl_exists('settings'));
            $this->assertSame(0, tbl_exists('stripe_gate_absent_table'));
        } finally {
            restore_error_handler();
        }
    }

    public function testBothSettingsScreensKeepFormatAndLinkTextConsistent(): void
    {
        $formatKey = 'documents.email.invoice_format';
        $textKey = 'documents.email.invoice_link_text';
        $text = "Hej [Fornavn], O'Brien & Søn <test>";
        $this->assertSame(['pdf', 'link'], array_keys(SettingsService::definition($formatKey)['options']));

        // G6.3 saves PDF and text; Stripe must render those stored preferences even with payments off.
        $this->assertTrue(SettingsService::save($formatKey, 'pdf'));
        $this->assertTrue(SettingsService::save($textKey, $text));
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'off');
        ob_start();
        try {
            stripeValg();
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertStringContainsString("value='pdf' selected", $html);
        $this->assertStringContainsString(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'), $html);

        // Save unrelated Stripe controls with those displayed values, then change the format there.
        $_POST = [
            'csrf_token' => $_SESSION['csrf_token'], 'stripe_enabled' => '1',
            'stripe_mode' => 'test', 'stripe_invoice_payments' => '1', 'stripe_enrolment' => '1',
            'stripe_publishable_key' => stripe_setting('publishable_key'), 'stripe_charge_on' => 'invoice_date',
            'stripe_public_base_url' => stripe_setting('public_base_url'),
            'invoice_format' => 'pdf', 'invoice_link_text' => $text,
        ];
        $this->assertSame('', $this->saveStripe());
        SettingsService::reset(); // Each screen is a separate HTTP request.
        $this->assertSame('pdf', SettingsService::get($formatKey));
        $this->assertSame($text, SettingsService::get($textKey));

        $_POST['invoice_format'] = 'link';
        $this->assertSame('', $this->saveStripe());
        SettingsService::reset();
        $this->assertSame('link', SettingsService::get($formatKey));
        $this->assertSame($text, SettingsService::get($textKey));
        $this->assertSame('link', stripe_invoice_format());

        // Saving G6.3's format leaves both the text and Stripe's collection controls intact.
        $this->assertTrue(SettingsService::save($formatKey, 'pdf'));
        stripe_settings_flush();
        $this->assertSame('pdf', stripe_invoice_format());
        $this->assertSame($text, stripe_invoice_link_text());
        $this->assertTrue(stripe_feature_on('invoice_payments'));
        $this->assertTrue(stripe_feature_on('enrolment'));
        $this->assertSame('invoice_date', stripe_setting('charge_on'));
        $this->assertFalse($GLOBALS['db_modify_fejl']);
    }

    /**
     * Save through diverse.php's handler and capture its validation output.
     * @return string Validation output, empty on a successful save.
     */
    private function saveStripe(): string
    {
        ob_start();
        try {
            stripeValgSave();
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
