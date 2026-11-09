<?php
// 20261023 CDX/LH Cover tenant settings access and shared invoice preferences in the redesign.
// 20261023 CDX/LH Assert the real integration read/write permission and regress missing-table checks without warnings.
// 20261109 CL/ASR Link reminders: the six settings in Salg › Betalingsbetingelser & rykkere (stored 'on'/'off' in var_grp stripe, read by stripe_reminder_settings()) and the renteloven § 9 b warnings on the reminder settings.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../systemdata/diverseIncludes/stripeValg.php';
require_once __DIR__ . '/../../includes/settings/SettingsService.php';
require_once __DIR__ . '/reminderFixtures.php';
if (is_file(__DIR__ . '/../../includes/stripeIncludes/stripeReminder.php')) {
    require_once __DIR__ . '/../../includes/stripeIncludes/stripeReminder.php';
}

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

    public function testLinkReminderSettingsAreInPaymentTermsAndReminders(): void
    {
        $section = getSettingsSections()['sales.reminders'];
        $this->assertSame(8160, $section['subsections']['link']);
        $this->assertSame('Rykker med betalingslink', findtekst(8160, 1));
        $texts = [];
        foreach (file(__DIR__ . '/../../importfiler/tekster.csv', FILE_IGNORE_NEW_LINES) as $line) {
            $cells = explode("\t", $line);
            $texts[$cells[0]] = $cells;
        }
        $defs = settings_section_definitions('sales.reminders');
        foreach ([
            'reminder_format' => ['select', 'auto', 'raw'],
            'reminder_link_email' => ['bool', false, 'onOff'],
            'reminder_fee_next' => ['bool', true, 'onOff'],
            'reminder_fee_waive' => ['bool', true, 'onOff'],
            'reminder_close_warning' => ['bool', false, 'onOff'],
            'reminder_close_text' => ['textarea', 'Betales beløbet ikke senest {frist}, kan vi lukke for din adgang uden yderligere varsel.', 'raw'],
        ] as $name => [$type, $default, $encoding]) {
            $def = $defs['sales.reminders.' . $name] ?? null;
            $this->assertNotNull($def, $name);
            $this->assertSame('link', $def['sub'], $name);
            $this->assertSame($type, $def['type'], $name);
            $this->assertSame($default, $def['default'], $name);
            $this->assertSame(['settings', 'stripe', $name, $encoding], $def['storage'], $name);
            foreach (['label', 'help'] as $text) {
                $this->assertCount(4, $texts[(string)$def[$text]] ?? [], "$name $text: Danish, English and Norwegian");
            }
        }
        $this->assertSame(['auto', 'classic'], array_keys($defs['sales.reminders.reminder_format']['options']));
        $this->assertSame(['setting', 'sales.reminders.reminder_close_warning', true], $defs['sales.reminders.reminder_close_text']['visible_if']);
    }

    public function testLinkReminderSettingsAreStoredOnOrOffAndReadByTheReminderCode(): void
    {
        $names = ['format' => 'reminder_format', 'link_email' => 'reminder_link_email', 'fee_next' => 'reminder_fee_next',
            'waive' => 'reminder_fee_waive', 'close_warning' => 'reminder_close_warning', 'close_text' => 'reminder_close_text'];
        foreach ($names as $name) {
            StripeTestEnvironment::setting('stripe', $name, null);
        }
        $default = 'Betales beløbet ikke senest {frist}, kan vi lukke for din adgang uden yderligere varsel.';
        $this->assertReminderSettings(['format' => 'auto', 'link_email' => false, 'fee_next' => true, 'waive' => true,
            'close_warning' => false, 'close_text' => $default]);
        // A missing row is the default, so saving the default writes nothing.
        $this->assertFalse(SettingsService::save('sales.reminders.reminder_fee_next', true));
        $this->assertFalse(SettingsService::save('sales.reminders.reminder_close_text', $default));
        $this->assertSame([], $this->stored());

        $this->assertTrue(SettingsService::save('sales.reminders.reminder_format', 'classic'));
        $this->assertTrue(SettingsService::save('sales.reminders.reminder_link_email', true));
        $this->assertTrue(SettingsService::save('sales.reminders.reminder_fee_next', false));
        $this->assertTrue(SettingsService::save('sales.reminders.reminder_fee_waive', false));
        $this->assertTrue(SettingsService::save('sales.reminders.reminder_close_warning', true));
        $this->assertTrue(SettingsService::save('sales.reminders.reminder_close_text', 'Luk {frist}'));
        $this->assertSame(['reminder_close_text' => 'Luk {frist}', 'reminder_close_warning' => 'on', 'reminder_fee_next' => 'off',
            'reminder_fee_waive' => 'off', 'reminder_format' => 'classic', 'reminder_link_email' => 'on'], $this->stored());
        $this->assertReminderSettings(['format' => 'classic', 'link_email' => true, 'fee_next' => false, 'waive' => false,
            'close_warning' => true, 'close_text' => 'Luk {frist}']);

        SettingsService::reset();
        $this->assertTrue(SettingsService::save('sales.reminders.reminder_fee_next', true));
        $this->assertSame('on', $this->stored()['reminder_fee_next']);
        $this->assertTrue(SettingsService::save('sales.reminders.reminder_close_text', ''));
        $this->assertSame($default, stripe_reminder_settings()['close_text'], 'empty = the default text');

        // Saving Stripe's own settings page leaves them alone.
        $before = $this->stored();
        $_POST = [
            'csrf_token' => $_SESSION['csrf_token'], 'stripe_enabled' => '1', 'stripe_mode' => 'test', 'stripe_invoice_payments' => '1',
            'stripe_enrolment' => '1', 'stripe_publishable_key' => stripe_setting('publishable_key'), 'stripe_charge_on' => 'due',
            'stripe_public_base_url' => stripe_setting('public_base_url'), 'invoice_format' => 'link', 'invoice_link_text' => '',
        ];
        $this->assertSame('', $this->saveStripe());
        $this->assertSame($before, $this->stored());
        $this->assertFalse($GLOBALS['db_modify_fejl']);
    }

    public function testReminderSettingsWarnAboutTheTenDaysAndTheFeeCapWithoutRefusing(): void
    {
        $none = ['days_2' => false, 'days_3' => false, 'fee_item_1' => false, 'fee_item_2' => false, 'fee_item_3' => false];
        StripeTestEnvironment::query("DELETE FROM formularer WHERE beskrivelse = 'GEBYR'");
        StripeTestEnvironment::query("DELETE FROM grupper WHERE art = 'DIV' AND kodenr = '4'");
        $this->assertSame($none, settings_reminder_legal_warnings(), 'no fee items: nothing to warn about');

        $items = StripeReminderFixtures::forms();   // 100 kr. on every level, no deadlines set
        $this->assertSame(array_replace($none, ['days_2' => true, 'days_3' => true]), settings_reminder_legal_warnings());
        StripeFixtures::insert('grupper', ['art' => 'DIV', 'kodenr' => '4', 'beskrivelse' => 'Div_valg (Rykker)', 'box6' => '10', 'box7' => '9']);
        $this->assertSame(array_replace($none, ['days_3' => true]), settings_reminder_legal_warnings());

        StripeTestEnvironment::query('UPDATE varer SET salgspris = 125 WHERE id = $1', [$items['fee']]);
        $this->assertSame(array_replace($none, ['days_3' => true, 'fee_item_1' => true, 'fee_item_2' => true, 'fee_item_3' => true]), settings_reminder_legal_warnings());
        // A 3rd reminder without a fee needs no 10 days and has no fee to cap.
        StripeTestEnvironment::query("UPDATE formularer SET xb = 0 WHERE beskrivelse = 'GEBYR' AND formular = 8");
        $this->assertSame(array_replace($none, ['fee_item_1' => true, 'fee_item_2' => true]), settings_reminder_legal_warnings());
    }

    /** @param array<string, mixed> $expected stripe_reminder_settings() values. */
    private function assertReminderSettings(array $expected): void
    {
        $actual = stripe_reminder_settings();
        foreach ($expected as $key => $value) {
            $this->assertSame($value, $actual[$key] ?? null, $key);
        }
    }

    /** @return array<string, string> Stored link reminder settings (var_grp stripe) by name. */
    private function stored(): array
    {
        $rows = pg_fetch_all(StripeTestEnvironment::query("SELECT var_name, var_value FROM settings WHERE var_grp = 'stripe' AND var_name LIKE 'reminder\\_%' ORDER BY var_name")) ?: [];
        return array_column($rows, 'var_value', 'var_name');
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
