<?php
// tests/restapi/LedgerAllowListRestTest.php
//
// $allowedLedgers in includes/connect.php and the session-less entry points: the master database stays connected for
// the registry, but it is the working ledger of a REST login, a REST token, a token refresh or a Stripe request only
// when it is listed. The account of this class (listed) works as before.
//
// The requests go to a `php -S` server of the repository that is given the setting the way connect.php gives it, as a
// global variable (auto_prepend_file), so the shared web server and its connect.php stay as they are. The prepend file
// also enters the script's folder, as the web server does.
//
// History:
// 20261127 ASR Created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';

final class LedgerAllowListRestTest extends TestCase
{
    /** @var resource|null */
    private static $server = null;
    private static int $port = 0;
    private static string $dir = '';
    private static array $master = [];

    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant(static::class);
        $conn = RestApiEnv::connect(RestApiEnv::masterDb());
        try {
            $rows = RestApiEnv::rows($conn, 'SELECT id, regnskab FROM regnskab WHERE db = $1 ORDER BY id LIMIT 1', [RestApiEnv::masterDb()]);
        } finally {
            pg_close($conn);
        }
        if ($rows === []) {
            self::markTestSkipped('the master database has no row of its own in regnskab');
        }
        self::$master = $rows[0];

        self::$dir = sys_get_temp_dir() . '/saldi_allowlist_rest_' . bin2hex(random_bytes(6));
        mkdir(self::$dir, 0700);
        $prepend = self::$dir . '/prepend.php';
        file_put_contents($prepend, '<?php $allowedLedgers = ' . var_export([RestApiEnv::testDb()], true) . ";\n"
            . "if (!empty(\$_SERVER['SCRIPT_FILENAME'])) chdir(dirname(\$_SERVER['SCRIPT_FILENAME']));\n");
        $root = dirname(__DIR__, 2);
        for ($try = 0; $try < 5 && self::$server === null; $try++) {
            $port = random_int(20000, 45000);
            $proc = proc_open([PHP_BINARY, '-d', 'auto_prepend_file=' . $prepend, '-d', 'display_errors=0', '-S', '127.0.0.1:' . $port, '-t', dirname($root)],
                [0 => ['pipe', 'r'], 1 => ['file', self::$dir . '/server.log', 'a'], 2 => ['file', self::$dir . '/server.log', 'a']], $pipes);
            for ($i = 0; $i < 50; $i++) {
                $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
                if ($socket) {
                    fclose($socket);
                    self::$server = $proc;
                    self::$port = $port;
                    break;
                }
                usleep(50000);
            }
            if (self::$server === null) {
                proc_terminate($proc);
            }
        }
        if (self::$server === null) {
            self::markTestSkipped('php -S could not be started');
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
        if (self::$dir !== '' && is_dir(self::$dir)) {
            array_map('unlink', glob(self::$dir . '/*') ?: []);
            rmdir(self::$dir);
        }
        RestApiEnv::teardownTenant();
    }

    /** @return array{status:int, json:?array, body:string} */
    private static function http(string $method, string $path, ?array $body = null, array $headers = []): array
    {
        $ch = curl_init('http://127.0.0.1:' . self::$port . '/' . basename(dirname(__DIR__, 2)) . $path);
        $headers[] = 'Accept: application/json';
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
            $headers[] = 'Content-Type: application/json';
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $raw = $raw === false ? '' : (string) $raw;
        $json = json_decode($raw, true);
        return ['status' => $status, 'json' => is_array($json) ? $json : null, 'body' => $raw];
    }

    private static function login(string $user, string $password, string $account): array
    {
        return self::http('POST', '/restapi/endpoints/v1/auth/login.php', ['username' => $user, 'password' => $password, 'account_name' => $account]);
    }

    private static function refusal(): string
    {
        require_once dirname(__DIR__, 2) . '/includes/ledgerAllowList.php';
        return saldi_ledger_refusal_text();
    }

    private function requireInstallSecret(): void
    {
        $reason = RestApiEnv::installSecretUnavailableReason();
        if ($reason !== null) {
            $this->markTestSkipped($reason);
        }
    }

    public function test_a_login_to_the_master_database_is_refused_while_it_is_not_listed(): void
    {
        $res = self::login('anyone', 'anything', (string) self::$master['regnskab']);
        $this->assertSame(403, $res['status'], $res['body']);
        $this->assertFalse($res['json']['success']);
        $this->assertSame(self::refusal(), $res['json']['message'], 'refused before the account database is opened');
        $this->assertNull($res['json']['data']);
    }

    public function test_a_login_to_the_listed_ledger_works_with_the_master_database_as_registry(): void
    {
        $res = self::login(RestApiEnv::user(), RestApiEnv::password(), RestApiEnv::accountOpen());
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertSame(RestApiEnv::testDb(), $res['json']['data']['tenant']['db']);
        $this->assertNotEmpty($res['json']['data']['access_token']);
    }

    public function test_a_token_for_the_master_database_is_refused_and_one_for_the_listed_ledger_works(): void
    {
        $this->requireInstallSecret();
        $claims = ['user_id' => 1, 'username' => RestApiEnv::user(), 'type' => 'access'];
        $master = self::http('GET', '/restapi/endpoints/v1/debitor/customers/?limit=1', null,
            ['Authorization: Bearer ' . RestApiEnv::signToken($claims + ['tenant_id' => (int) self::$master['id']])]);
        $this->assertSame(403, $master['status'], $master['body']);
        $this->assertSame(self::refusal(), $master['json']['message']);

        $vat = self::http('GET', '/restapi/endpoints/v1/vat-codes/', null,
            ['Authorization: Bearer ' . RestApiEnv::signToken($claims + ['tenant_id' => (int) self::$master['id']])]);
        $this->assertSame(403, $vat['status'], $vat['body']);

        $login = self::login(RestApiEnv::user(), RestApiEnv::password(), RestApiEnv::accountOpen());
        $listed = self::http('GET', '/restapi/endpoints/v1/debitor/customers/?limit=1', null,
            ['Authorization: Bearer ' . $login['json']['data']['access_token']]);
        $this->assertSame(200, $listed['status'], $listed['body']);
        $this->assertTrue($listed['json']['success']);
    }

    public function test_a_refresh_for_the_master_database_is_refused(): void
    {
        $this->requireInstallSecret();
        $token = RestApiEnv::signToken(['user_id' => 1, 'username' => RestApiEnv::user(), 'type' => 'refresh', 'tenant_id' => (int) self::$master['id']], 600);
        $res = self::http('POST', '/restapi/endpoints/v1/auth/refresh.php', ['refresh_token' => $token]);
        $this->assertSame(403, $res['status'], $res['body']);
        $this->assertSame(self::refusal(), $res['json']['message']);
    }

    public function test_a_stripe_request_for_the_master_database_is_refused_before_it_is_opened(): void
    {
        $master = self::http('POST', '/api/stripe/webhook.php?r=' . rawurlencode(RestApiEnv::masterDb()), ['type' => 'ping']);
        $this->assertSame(403, $master['status'], $master['body']);
        $this->assertStringContainsString(htmlspecialchars(self::refusal(), ENT_QUOTES, 'UTF-8'), $master['body']);

        $listed = self::http('POST', '/api/stripe/webhook.php?r=' . rawurlencode(RestApiEnv::testDb()), ['type' => 'ping']);
        $this->assertNotSame(403, $listed['status'], 'a listed ledger goes on to the webhook\'s own checks: ' . $listed['body']);
    }
}
