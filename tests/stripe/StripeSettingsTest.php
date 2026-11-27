<?php
// 20261022 CDX/LH Master settings save/render regression tests with real DB escaping.
// 20261022 CDX/LH Expect the signup control to remain unchecked when its setting is absent.
// 20261126 CL/ASR Review round 1 (I15): saving stores armed_for, which arms the ledger on this server; an unarmed own ledger gets warning 8250.
// 20261126 CL/ASR Review round 1 (I11): a MySQL ledger is told that Stripe requires PostgreSQL and gets no catalog query; the catalog check is tbl_exists().
// 20261126 CL/ASR Review round 1 (Q1): the one-time migration keeps subscription signup on where subscriptions are sold; the help says the default is off.
// 20261126 CL/ASR Review round 1 (SM-4): the port's login blocks in betweenUpdates.php wait for another login that runs them, and release their lock.
// 20261126 CL/ASR Review round 1 (S1, review): on a LATIN9 install the page shows its warnings, the switches' texts and the refusals (escaped in the page charset).
// 20261126 ASR Payment review (GPT-P-4): the update chain installs stripe_adjustment_alerts under the port's lock.
// 20261126 ASR Review GPT-R-4: the SM-4 test takes the port's blocks up to the unlock, as the reminder_dispatch block is appended after it.
// 20261126 ASR Merge of the two review tracks: the SM-4 test takes the blocks up to the port's last unlock, and the stripe_adjustment_alerts block only has to follow the port's earlier blocks.
// 20261126 ASR Review PAY-R2-5: clearing the Base-URL is refused while invoice payments or automatic card payment is on; an empty one is saved with a warning the page keeps showing, and E-mail med link is not available without it.

require_once __DIR__ . '/support/bootstrap.php';
require_once __DIR__ . '/../../systemdata/diverseIncludes/stripeValg.php';
require_once __DIR__ . '/../../debitor/orderIncludes/orderDelivery.php';

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
        $this->assertSame(stripe_arming_key($GLOBALS['db']), stripe_setting('armed_for'));
        $this->assertFalse($GLOBALS['db_modify_fejl']);
    }

    public function testSavingArmsALedgerOnThisServerAndAnUnarmedOneIsWarned(): void
    {
        $pinned = $GLOBALS['db'];
        try {
            $GLOBALS['db'] = 'other_ledger';
            StripeTestEnvironment::setting('stripe', 'target_db', 'other_ledger');
            $this->assertFalse(stripe_ledger_armed());
            $html = $this->render();
            $this->assertStringContainsString('Stripe-indstillingerne er gemt på en anden server eller før en opdatering.', $html);
            $this->assertStringNotContainsString('kopieret fra regnskabet', $html);
            StripeTestEnvironment::setting('stripe', 'target_db', 'third_ledger');
            $html = $this->render();
            $this->assertStringContainsString('kopieret fra regnskabet third_ledger', $html);
            $this->assertStringNotContainsString('anden server', $html);
            $this->assertSame('', $this->save());
            $this->assertSame('other_ledger', stripe_setting('target_db'));
            $this->assertSame(stripe_arming_key('other_ledger'), stripe_setting('armed_for'));
            $this->assertTrue(stripe_ledger_armed());
            $html = $this->render();
            $this->assertStringNotContainsString('anden server', $html);
            $this->assertStringNotContainsString('kopieret fra regnskabet', $html);
        } finally {
            $GLOBALS['db'] = $pinned;
            stripe_settings_flush();
        }
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
        // The signup switch's help states the code default (7112 said "Standard: til").
        $this->assertStringContainsString('Eksisterende abonnementer fortsætter. Standard: fra.', $html);
        $this->assertStringNotContainsString('Standard: til', $html);
    }

    public function testOneTimeMigrationKeepsSubscriptionSignupOnWhereSubscriptionsAreSold(): void
    {
        require_once __DIR__ . '/../../includes/opdat_func/databaseDialect.php';
        $source = file_get_contents(__DIR__ . '/../../includes/betweenUpdates.php');
        $start = strpos($source, '// 20261126 CL/ASR Review round 1 (Q1, owner default). #850');
        $this->assertNotFalse($start);
        $this->assertSame(1, preg_match('/\n\}\n/', $source, $m, PREG_OFFSET_CAPTURE, $start));
        $block = substr($source, $start, $m[0][1] + 3 - $start);
        $this->assertStringContainsString("'stripe_signup_default_migrated'", $block);
        $migrate = function () use ($block): void {
            $db_type = 'postgresql';
            eval($block);
            stripe_settings_flush();
        };
        $flag = fn() => (int)pg_fetch_result(StripeTestEnvironment::query("SELECT count(*) FROM settings WHERE var_grp = 'system' AND var_name = 'stripe_signup_default_migrated'"), 0, 0);
        StripeTestEnvironment::query('CREATE TABLE IF NOT EXISTS stripe_catalog (id SERIAL PRIMARY KEY, varenr text, active boolean NOT NULL DEFAULT true)');
        StripeTestEnvironment::query("DELETE FROM settings WHERE var_grp = 'system' AND var_name = 'stripe_signup_default_migrated'");
        StripeTestEnvironment::query('DELETE FROM stripe_catalog');
        // Stripe enabled, no catalog rows: not a subscription seller, the default stays off.
        $migrate();
        $this->assertFalse(stripe_feature_on('signup_enabled'));
        $this->assertSame('', stripe_setting('signup_enabled'));
        $this->assertSame(1, $flag());
        // A subscription seller (enabled + catalog rows) that never saved the switch keeps selling, once.
        StripeTestEnvironment::query("DELETE FROM settings WHERE var_grp = 'system' AND var_name = 'stripe_signup_default_migrated'");
        StripeFixtures::insert('stripe_catalog', ['varenr' => 'SUB-1', 'active' => true]);
        $migrate();
        $this->assertTrue(stripe_feature_on('signup_enabled'));
        $this->assertSame(1, $flag());
        // Done once: a later switch-off is not undone at the next login.
        StripeTestEnvironment::setting('stripe', 'signup_enabled', 'off');
        $migrate();
        $this->assertFalse(stripe_feature_on('signup_enabled'));
        $this->assertSame(1, $flag());
        // A switch already saved as off, or Stripe switched off: left alone.
        foreach ([['signup_enabled', 'off'], ['enabled', 'off']] as [$name, $value]) {
            StripeTestEnvironment::query("DELETE FROM settings WHERE var_grp = 'system' AND var_name = 'stripe_signup_default_migrated'");
            StripeTestEnvironment::setting('stripe', 'signup_enabled', $name === 'signup_enabled' ? 'off' : null);
            StripeTestEnvironment::setting('stripe', $name, $value);
            $migrate();
            $this->assertFalse(stripe_feature_on('signup_enabled'), $name);
            StripeTestEnvironment::setting('stripe', 'enabled', 'on');
        }
    }

    public function testMySqlLedgerIsToldStripeNeedsPostgresAndRunsNoCatalogQuery(): void
    {
        $html = $this->render();
        $this->assertStringNotContainsString('Stripe kræver PostgreSQL', $html);
        $this->assertMatchesRegularExpression('/Varenr &harr; Stripe price|Stripe-tabellerne er ikke oprettet/', $html);
        $type = $GLOBALS['db_type'];
        try {
            // No query may run: on MySQL the PostgreSQL-only check failed with an error box.
            $GLOBALS['db_type'] = 'mysqli';
            $this->assertTrue(stripeValgNeedsPostgres());
            ob_start();
            try {
                stripeValgCatalog();
                $catalog = ob_get_contents();
            } finally {
                ob_end_clean();
            }
        } finally {
            $GLOBALS['db_type'] = $type;
        }
        $this->assertSame('', $catalog);
        $this->assertFalse(stripeValgNeedsPostgres());
        $source = file_get_contents(__DIR__ . '/../../systemdata/diverseIncludes/stripeValg.php');
        $this->assertStringNotContainsString("to_regclass('", $source);
        $this->assertStringContainsString("if (stripeValgNeedsPostgres()) {\n\t\tprint stripeValgWarningRow(findtekst('8253', \$sprog_id));", $source);
        $this->assertSame('Stripe kræver PostgreSQL. Dette regnskab bruger MySQL, så Stripe kan ikke bruges her.', findtekst('8253', 1));
    }

    public function testALatin9InstallSeesTheWarningsTheSwitchesAndTheRefusals(): void
    {
        // On an install whose database is not UTF8, online.php sets the page charset to ISO-8859-1 and findtekst()
        // returns ISO-8859-1. Escaped as UTF-8, every text with æ/ø/å came out as an empty string.
        $saved = [$GLOBALS['charset'] ?? null, $GLOBALS['db_encode'] ?? null, $GLOBALS['db']];
        $GLOBALS['charset'] = 'ISO-8859-1';
        $GLOBALS['db_encode'] = 'LATIN9';
        $latin1 = static fn(string $s): string => mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
        $utf8 = static fn(string $s): string => mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        foreach (array_keys($_SESSION) as $key) {
            if (strpos($key, 'text_') === 0) {
                $_SESSION[$key] = $latin1($_SESSION[$key]);
            }
        }
        try {
            $_POST['stripe_charge_on'] = 'invalid';
            $this->assertStringContainsString("alert('Automatisk træk afvist", $utf8($this->save()));
            $this->assertSame('Stripe kræver PostgreSQL. Dette regnskab bruger MySQL, så Stripe kan ikke bruges her.',
                $utf8(strip_tags(stripeValgWarningRow(findtekst('8253', 1)))));
            // An own ledger that is not armed here (saved before armed_for, or on another server).
            $GLOBALS['db'] = 'other_ledger';
            StripeTestEnvironment::setting('stripe', 'target_db', 'other_ledger');
            $html = $utf8($this->render());
            $this->assertStringContainsString("<b style='color:red'>Stripe-indstillingerne er gemt på en anden server eller før en opdatering.", $html);
            $this->assertStringContainsString("<td title='Tillad nye abonnementstilmeldinger. Eksisterende abonnementer fortsætter. Standard: fra.'>", $html);
            foreach (['7107', '7108', '7109', '7110', '7111'] as $id) {
                $this->assertStringContainsString(htmlspecialchars($utf8(findtekst($id, 1)), ENT_QUOTES, 'UTF-8'), $html, $id);
            }
            $this->assertStringNotContainsString("<td title=''>", $html);
            $this->assertStringNotContainsString("<b style='color:red'></b>", $html);
        } finally {
            [$GLOBALS['charset'], $GLOBALS['db_encode'], $GLOBALS['db']] = $saved;
            stripe_settings_flush();
        }
    }

    public function testTheUpdateChainInstallsTheAdjustmentAlertsTable(): void
    {
        $includes = realpath(__DIR__ . '/../../includes');
        $source = file_get_contents($includes . '/betweenUpdates.php');
        $start = strpos($source, '// 20261126 ASR Payment review (GPT-P-4): stripe_adjustment_alerts keeps');
        $this->assertNotFalse($start);
        $this->assertSame(1, preg_match('/\n\}\n/', $source, $m, PREG_OFFSET_CAPTURE, $start));
        $block = substr($source, $start, $m[0][1] + 3 - $start);
        // Appended after the port's earlier blocks; later blocks (GPT-R-4: reminder_dispatch) may follow it.
        $this->assertGreaterThan(strpos($source, '// 20261126 CL/ASR SM-4: the lock taken'), $start, 'appended after the port blocks');
        // A ledger updated before the table existed (dropped inside this test's transaction, which is rolled back).
        StripeTestEnvironment::query('DROP TABLE stripe_adjustment_alerts');
        $this->assertFalse(stripe_adjustment_alerts_ready());
        foreach ([1, 2] as $login) {
            (function () use ($block, $includes): void {
                $db_type = 'postgresql';
                eval(str_replace('__DIR__', var_export($includes, true), $block));
            })();
        }
        $this->assertFalse($GLOBALS['db_modify_fejl']);
        $this->assertTrue(stripe_adjustment_alerts_ready());
        $columns = array_column(pg_fetch_all(StripeTestEnvironment::query("SELECT column_name FROM information_schema.columns
            WHERE table_name = 'stripe_adjustment_alerts' AND table_schema = current_schema() ORDER BY ordinal_position")), 'column_name');
        $this->assertSame(['event_id', 'payment_id', 'event_type', 'created_at', 'sent_at'], $columns);
        $this->assertSame('0', pg_fetch_result(StripeTestEnvironment::query(
            "SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND pid = pg_backend_pid()"), 0, 0), 'the lock is released');
    }

    public function testThePortsLoginBlocksRunOneLoginAtATime(): void
    {
        $includes = realpath(__DIR__ . '/../../includes');
        require_once $includes . '/opdat_func/databaseDialect.php';
        $source = file_get_contents($includes . '/betweenUpdates.php');
        $start = strpos($source, '// 20261126 CL/ASR SM-4: the Stripe port');
        $this->assertNotFalse($start);
        // To the port's last unlock (GPT-P-1/P-4 blocks take and release the lock themselves); reminder_dispatch follows it (GPT-R-4).
        $unlock = "pg_advisory_unlock(hashtext('saldi_stripe_port_migration'))\", __FILE__ . \" linje \" . __LINE__);\n}\n";
        $end = strrpos($source, $unlock);
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $section = substr($source, $start, $end + strlen($unlock) + 1 - $start);
        foreach (['"Saldi invoices, Stripe collects"', 'stripe_charge_schema_ensure();', "'reminder_fee_projekt_backfilled'",
            'reminder_automation_schema_ensure();', "'stripe_signup_default_migrated'", 'stripe_adjustment_alerts_schema_ensure();'] as $block) {
            $this->assertStringContainsString($block, $section, 'the lock covers ' . $block);
        }
        $this->assertStringEndsWith("pg_advisory_unlock(hashtext('saldi_stripe_port_migration'))\", __FILE__ . \" linje \" . __LINE__);\n}\n\n", $section);
        // Another login holds the lock for a moment (its own process and connection).
        $dsn = [];
        foreach (['host' => 'SALDI_TEST_PGHOST', 'user' => 'SALDI_TEST_PGUSER', 'password' => 'SALDI_TEST_PGPASSWORD', 'dbname' => 'SALDI_TEST_DB'] as $key => $env) {
            $dsn[] = $key . "='" . str_replace(['\\', "'"], ['\\\\', "\\'"], (string)getenv($env)) . "'";
        }
        $code = '$c = pg_connect(getenv("SM4_DSN"), PGSQL_CONNECT_FORCE_NEW); pg_query($c, "select pg_advisory_lock(hashtext(\'saldi_stripe_port_migration\'))");'
            . ' echo "locked\n"; usleep(1200000); pg_query($c, "select pg_advisory_unlock(hashtext(\'saldi_stripe_port_migration\'))"); echo "done\n";';
        $other = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['SM4_DSN' => implode(' ', $dsn)] + getenv());
        $this->assertIsResource($other);
        try {
            $this->assertSame("locked\n", fgets($pipes[1]));
            $before = microtime(true);
            (function () use ($section, $includes): void {
                $db_type = 'postgresql';
                eval(str_replace('__DIR__', var_export($includes, true), $section));
            })();
            $this->assertGreaterThan(0.8, microtime(true) - $before, 'the blocks ran while another login held the lock');
            $this->assertSame("done\n", fgets($pipes[1]));
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($other);
        }
        $this->assertFalse($GLOBALS['db_modify_fejl']);
        $this->assertSame('0', pg_fetch_result(StripeTestEnvironment::query(
            "SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND pid = pg_backend_pid()"), 0, 0), 'the lock is released');
    }

    /** Review PAY-R2-5: every invoice link is built from the Base-URL, so it cannot silently be empty while invoice links are used. */
    public function testAnEmptyBaseUrlIsRefusedOrWarnedWhileInvoiceLinksNeedItAndLinkIsNotOffered(): void
    {
        $base = (string)stripe_setting('public_base_url');
        $this->assertNotSame('', $base);
        $this->assertStringNotContainsString('Base-URL mangler', $this->render());
        // Clearing the saved Base-URL while invoice payments are on: refused, and the saved one is kept.
        $_POST['stripe_public_base_url'] = '';
        $output = $this->save();
        $this->assertStringContainsString("alert('Base-URL afvist: den skal udfyldes, når Kortbetaling af fakturaer eller Gem kort og betal automatisk er slået til.", $output);
        $this->assertStringContainsString('(feltet er IKKE gemt).', $output);
        $this->assertSame($base, stripe_setting('public_base_url'));
        // Only automatic card payment on: refused as well.
        unset($_POST['stripe_invoice_payments']);
        $this->assertStringContainsString('Base-URL afvist', $this->save());
        $this->assertSame($base, stripe_setting('public_base_url'));
        // Both off: clearing it is accepted without a word.
        unset($_POST['stripe_enrolment']);
        $this->assertSame('', $this->save());
        $this->assertSame('', (string)stripe_setting('public_base_url'));
        $this->assertStringNotContainsString('Base-URL mangler', $this->render());

        // Invoice payments turned on without a Base-URL: saved with a warning, which the page shows until it is filled in.
        $_POST = $this->validPost();
        $this->assertSame('', $_POST['stripe_public_base_url']);
        $output = $this->save();
        $this->assertStringContainsString("alert('Base-URL mangler: Uden den sendes fakturaer og rykkere som almindelig e-mail med PDF i stedet for med link", $output);
        $this->assertTrue(stripe_feature_on('invoice_payments'));
        $this->assertStringContainsString("<b style='color:red'>Base-URL mangler: Uden den sendes fakturaer og rykkere som almindelig e-mail med PDF", $this->render());
        // E-mail med link is not available meanwhile: no link default, a Link order goes as the classic e-mail, and the order page says why.
        $this->assertFalse(stripe_invoice_link_available());
        $this->assertTrue(stripe_invoice_link_base_missing());
        $this->assertSame('pdf', stripe_invoice_format());
        $this->assertSame('pdf', stripe_invoice_delivery(StripeFixtures::invoice(null, ['udskriv_til' => 'Link'])));
        $this->assertStringContainsString('E-mail med link kan ikke bruges: der mangler en Base-URL i Stripe-indstillingerne.', orderLinkBlockText('base_url', false));

        // Filled in again: no warning, and the link mail is available.
        $_POST['stripe_public_base_url'] = $base;
        $this->assertSame('', $this->save());
        $this->assertStringNotContainsString('Base-URL mangler', $this->render());
        $this->assertTrue(stripe_invoice_link_available());
        $this->assertFalse(stripe_invoice_link_base_missing());
        $this->assertFalse($GLOBALS['db_modify_fejl']);
    }

    private function render(): string
    {
        ob_start();
        try {
            stripeValg();
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
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
