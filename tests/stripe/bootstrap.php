<?php
// 20261022 CDX/LH PostgreSQL Stripe test harness; no master DB, session or network.
// 20261022 CDX/LH Add transport interleaving/commit assertions and clearing-account fixture for booking tests.
// 20261022 CDX/LH Capture attachments and pin the shared Copenhagen clock for deterministic enrolment.
// 20261022 CDX/LH Reject changed request bodies under an already used Stripe idempotency key.
// 20261023 CDX/LH Supply the tenant user's legacy settings permission before the permission cache is populated.

require_once __DIR__ . '/../TestCredentials.php';

/** Strict in-memory Stripe transport and mail recorder shared by the suite. */
final class StripeTestTransport
{
    public static array $requests = [];
    public static array $responses = [];
    public static array $mails = [];
    public static array $logs = [];
    public static array $idempotentRequests = [];
    public static bool $mailSucceeds = true;
    public static $beforeResponse = null;
    public static $beforeMail = null;

    public static function reset(): void
    {
        self::$requests = [];
        self::$responses = [];
        self::$mails = [];
        self::$logs = [];
        self::$idempotentRequests = [];
        self::$mailSucceeds = true;
        self::$beforeResponse = null;
        self::$beforeMail = null;
    }

    /**
     * Queue the real client's response shape, optionally checking its destination.
     *
     * @param array{ok: bool, status: int, body: array|null, error: string, error_code?: string, request_id?: string} $response
     * @return void
     */
    public static function queue(array $response, ?string $method = null, ?string $path = null): void
    {
        self::$responses[] = ['response' => $response, 'method' => $method, 'path' => $path];
    }

    /** Queue a successful Stripe JSON response. */
    public static function queueBody(array $body, ?string $method = null, ?string $path = null): void
    {
        self::queue(['ok' => true, 'status' => 200, 'body' => $body, 'error' => ''], $method, $path);
    }
}

// Define replacements BEFORE any Stripe include: the production definitions are guarded.
/**
 * Record an API call and consume one canned response. Unexpected calls fail the test.
 *
 * @return array{ok: bool, status: int, body: array|null, error: string, error_code: string, request_id: string}
 */
function stripeHttpRequest($method, $path, $params = [], $opts = [])
{
    StripeTestTransport::$requests[] = [
        'method' => $method, 'path' => $path, 'params' => $params,
        'opts' => $opts, 'idempotency_key' => $opts['idempotency_key'] ?? null,
    ];
    $key = $opts['idempotency_key'] ?? null;
    if ($key !== null) {
        $body = ['method' => $method, 'path' => $path, 'params' => $params];
        if (isset(StripeTestTransport::$idempotentRequests[$key]) && StripeTestTransport::$idempotentRequests[$key] !== $body) {
            return ['ok' => false, 'status' => 400, 'body' => ['error' => ['type' => 'idempotency_error']],
                'error' => 'Idempotency key reused with different parameters', 'error_code' => 'idempotency_error', 'request_id' => ''];
        }
        StripeTestTransport::$idempotentRequests[$key] = $body;
    }
    if (!StripeTestTransport::$responses) {
        throw new RuntimeException("Unexpected Stripe request: $method $path");
    }
    $queued = array_shift(StripeTestTransport::$responses);
    if (($queued['method'] !== null && $queued['method'] !== $method)
        || ($queued['path'] !== null && $queued['path'] !== $path)) {
        throw new RuntimeException("Stripe request does not match queued response: $method $path");
    }
    if (StripeTestTransport::$beforeResponse !== null) {
        (StripeTestTransport::$beforeResponse)($method, $path);
    }
    return array_replace(['ok' => false, 'status' => 0, 'body' => null, 'error' => '', 'error_code' => '', 'request_id' => ''], $queued['response']);
}

/** Capture customer mail without loading PHPMailer or contacting SMTP. */
function stripe_mail_send($to, $subject, $html, $text, $attachments = [])
{
    if (StripeTestTransport::$beforeMail !== null) {
        (StripeTestTransport::$beforeMail)();
    }
    StripeTestTransport::$mails[] = compact('to', 'subject', 'html', 'text', 'attachments');
    return StripeTestTransport::$mailSucceeds;
}

/** Capture operational alerts too, so future payment tests cannot send real mail. */
function stripeAlertMail($subject, $body, $throttleKey = '', $throttleSec = 3600)
{
    StripeTestTransport::$mails[] = [
        'kind' => 'alert', 'subject' => $subject, 'text' => $body,
        'throttle_key' => $throttleKey, 'throttle_sec' => $throttleSec,
    ];
    return StripeTestTransport::$mailSucceeds;
}

/** Capture diagnostic output without writing the application's shared log. */
function stripe_log($line)
{
    StripeTestTransport::$logs[] = $line;
}

/**
 * Test-only resolver override: the restored tenant has no master regnskab table.
 * The production function_exists guard supplies this seam without a web bypass.
 *
 * @return string Validated test tenant name, never a production locator lookup.
 */
function stripeConfigDb()
{
    $db = (string)getenv('SALDI_TEST_DB');
    if (!preg_match('/^[a-z0-9_]{1,40}$/', $db)) {
        throw new RuntimeException('SALDI_TEST_DB must name the disposable restored test tenant.');
    }
    return $db;
}

require_once __DIR__ . '/../../includes/db_query.php';
require_once __DIR__ . '/../../includes/std_func.php';
require_once __DIR__ . '/../../includes/stripeIncludes/stripeInvoice.php';
require_once __DIR__ . '/../../includes/stripeIncludes/stripeEnrol.php';

/** Database lifecycle for an isolated, fully restored Saldi PostgreSQL tenant. */
final class StripeTestEnvironment
{
    private static bool $ready = false;

    /** Missing local configuration skips; configured connection/schema failures must fail. */
    public static function unavailableReason(): ?string
    {
        foreach (['SALDI_TEST_PGHOST', 'SALDI_TEST_PGUSER', 'SALDI_TEST_DB'] as $name) {
            if (getenv($name) === false || getenv($name) === '') {
                return "$name is not configured for the Stripe PostgreSQL suite";
            }
        }
        return null;
    }

    /** Connect once and provision only Stripe's schema, outside per-test transactions. */
    public static function connect(): void
    {
        if (self::$ready) {
            return;
        }
        if (!extension_loaded('pgsql')) {
            throw new RuntimeException('The configured Stripe suite requires ext-pgsql.');
        }
        chdir(dirname(__DIR__, 2));
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['db_encode'] = 'UTF8';
        $GLOBALS['charset'] = 'UTF-8';
        $GLOBALS['sqhost'] = (string)getenv('SALDI_TEST_PGHOST');
        $GLOBALS['squser'] = (string)getenv('SALDI_TEST_PGUSER');
        $GLOBALS['sqpass'] = (string)getenv('SALDI_TEST_PGPASSWORD');
        $GLOBALS['db'] = stripeConfigDb();
        $GLOBALS['sqdb'] = '';
        $GLOBALS['webservice'] = true;
        $GLOBALS['db_skriv_id'] = 0;
        $GLOBALS['db_modify_fejl'] = false;
        $GLOBALS['db_transaktion_depth'] = 0;
        $GLOBALS['bruger_id'] = 0;
        $GLOBALS['brugernavn'] = 'stripe_unittest';
        $GLOBALS['rettigheder'] = '01'; // Legacy Indstillinger write bit, inherited by settings.integrations.
        $GLOBALS['regnaar'] = '5'; // Restored tenant: RA kodenr=5 is 2026.
        $GLOBALS['sprog_id'] = 1;
        $GLOBALS['s_id'] = 'stripe_unittest';
        $_SERVER['REQUEST_URI'] = '/stripe-tests'; // Saldi DB logging resolves temp/ from cwd.
        $_SERVER['REMOTE_ADDR'] = 'cli';

        $GLOBALS['connection'] = db_connect($GLOBALS['sqhost'], $GLOBALS['squser'], $GLOBALS['sqpass'], $GLOBALS['db']);
        if (!$GLOBALS['connection']) {
            throw new RuntimeException('Cannot connect to configured Stripe test tenant.');
        }
        $temp = dirname(__DIR__, 2) . '/temp/' . $GLOBALS['db'];
        if (!is_dir($temp) && !mkdir($temp, 0750, true)) {
            throw new RuntimeException('Cannot prepare Saldi DB log directory for tests.');
        }
        self::query("SET TIME ZONE 'UTC'");
        // Pilot table from includes/betweenUpdates.php; a scrubbed older restore may lack it.
        self::query("CREATE TABLE IF NOT EXISTS stripe_customers (
            id SERIAL PRIMARY KEY, stripe_customer_id varchar(255) NOT NULL,
            stripe_subscription_id varchar(255), konto_id integer, kontonr varchar(30),
            order_id integer, status varchar(30) NOT NULL DEFAULT 'active',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP, updated_at timestamp)");
        self::query('ALTER TABLE adresser ADD COLUMN IF NOT EXISTS stripe_fravalg varchar(2)');
        stripe_payments_ensure();
        if ($GLOBALS['db_modify_fejl']) {
            throw new RuntimeException('Stripe schema provisioning failed.');
        }
        self::$ready = true;
    }

    /** Execute checked fixture SQL on the SAME connection the application uses. */
    public static function query(string $sql, array $params = [])
    {
        $result = $params
            ? pg_query_params($GLOBALS['connection'], $sql, $params)
            : pg_query($GLOBALS['connection'], $sql);
        if ($result === false) {
            throw new RuntimeException('Stripe fixture SQL failed: ' . pg_last_error($GLOBALS['connection']));
        }
        return $result;
    }

    /** Replace one tenant-wide setting without retaining scrubbed tenant credentials. */
    public static function setting(string $group, string $name, ?string $value): void
    {
        self::query('DELETE FROM settings WHERE var_grp=$1 AND var_name=$2', [$group, $name]);
        if ($value !== null) {
            self::query('INSERT INTO settings (var_grp, var_name, var_value, user_id, group_id)
                VALUES ($1, $2, $3, NULL, 0)', [$group, $name, $value]);
        }
        stripe_settings_flush();
    }

    /** Seed test-mode configuration inside the transaction; credentials are random and unusable. */
    public static function seedSettings(): void
    {
        $settings = [
            'mode' => 'test', 'enabled' => 'on',
            'secret_key' => 'sk_test_' . TestCredentials::password('stripe_api'),
            'publishable_key' => 'pk_test_' . bin2hex(random_bytes(12)),
            'link_secret' => bin2hex(random_bytes(32)),
            'webhook_secret' => 'whsec_' . TestCredentials::password('stripe_webhook'),
            'public_base_url' => 'https://stripe-tests.invalid/master',
            'invoice_payments' => 'on', 'enrolment' => 'on', 'charge_on' => 'due',
        ];
        foreach ($settings as $name => $value) {
            self::setting('stripe', $name, $value);
        }
        self::setting('stripe', 'signup_enabled', null);
        self::setting('email_settings', 'invoice_format', 'link');
        self::setting('email_settings', 'invoice_link_text', '');
    }
}

require_once __DIR__ . '/fixtures.php';

/** Each test rolls back fixtures/settings; schema initialization precedes the transaction. */
abstract class StripeDatabaseTestCase extends PHPUnit\Framework\TestCase
{
    private bool $transactionOpen = false;
    private string $previousTimezone;

    protected function setUp(): void
    {
        parent::setUp();
        $reason = StripeTestEnvironment::unavailableReason();
        if ($reason !== null) {
            $this->markTestSkipped($reason);
        }
        StripeTestEnvironment::connect();
        StripeTestTransport::reset();
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Copenhagen');
        $GLOBALS['stripe_charge_today'] = StripeFixtures::TODAY;
        StripeTestEnvironment::query('BEGIN');
        $this->transactionOpen = true;
        StripeTestEnvironment::seedSettings();
        StripeTestEnvironment::query("UPDATE grupper SET box10='58050' WHERE art='DIV' AND kodenr=3");
        if (!pg_fetch_assoc(StripeTestEnvironment::query("SELECT id FROM grupper WHERE art='DIV' AND kodenr=3"))) {
            StripeFixtures::insert('grupper', ['art' => 'DIV', 'kodenr' => 3, 'box10' => '58050', 'fiscal_year' => 5]);
        }
        $GLOBALS['db_modify_fejl'] = false;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->transactionOpen) {
                StripeTestEnvironment::query('ROLLBACK');
                $this->transactionOpen = false;
                stripe_settings_flush();
                date_default_timezone_set($this->previousTimezone);
                unset($GLOBALS['stripe_charge_today']);
            }
        } finally {
            parent::tearDown();
        }
    }
}
