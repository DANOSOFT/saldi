<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * mobilepay_webhook_sync() reconciles the Vipps webhook registration on login (called from
 * includes/betweenUpdates.php). It deletes webhooks at the provider, so every failure shape has
 * to leave 'reconciled' false - the caller only writes its one-shot marker when it is true.
 *
 * Runs against tests/fixtures/mobilepay/vipps_stub.php served by `php -S` on a free local port.
 * No database, nothing leaves the machine. Skips when the built-in server cannot be started.
 *
 * History:
 * 20260921 Sawaneh Created (PR #423 review): happy path, 4xx/5xx from each of the four calls,
 *                  2xx replies with an unexpected payload, and a stale webhook that cannot be deleted.
 */
final class MobilepayWebhookSyncTest extends TestCase
{
    private const EXPECTED = 'https://pos.example.dk/pos/debitor/payments/mobilepay/webhook_recive.php?db=acme';
    private const STALE    = 'https://old.example.dk/pos/debitor/payments/mobilepay/webhook_recive.php?db=acme';
    private const OTHER_DB = 'https://old.example.dk/pos/debitor/payments/mobilepay/webhook_recive.php?db=acme2';

    /** @var resource|null */
    private static $server = null;
    private static string $base = '';
    private static string $scenarioFile = '';
    private static string $logFile = '';

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/stdFunc/mobilepayWebhookSync.php';
        if (!function_exists('curl_init') || !function_exists('proc_open')) {
            return;
        }
        $probe = @stream_socket_server('tcp://127.0.0.1:0');
        if (!$probe) {
            return;
        }
        $port = (int)substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        self::$scenarioFile = tempnam(sys_get_temp_dir(), 'mpscn');
        self::$logFile      = tempnam(sys_get_temp_dir(), 'mplog');
        $env = array_merge(getenv(), array('MP_STUB_SCENARIO' => self::$scenarioFile, 'MP_STUB_LOG' => self::$logFile));
        $router = dirname(__DIR__, 2) . '/fixtures/mobilepay/vipps_stub.php';
        self::$server = proc_open(
            array(PHP_BINARY, '-S', '127.0.0.1:' . $port, $router),
            array(0 => array('file', '/dev/null', 'r'), 1 => array('file', '/dev/null', 'w'), 2 => array('file', '/dev/null', 'w')),
            $pipes,
            null,
            $env
        ) ?: null;
        self::$base = 'http://127.0.0.1:' . $port;
        for ($i = 0; $i < 50; $i++) {
            $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($fp) {
                fclose($fp);
                return;
            }
            usleep(100000);
        }
        self::tearDownAfterClass();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
        foreach (array(self::$scenarioFile, self::$logFile) as $file) {
            if ($file !== '' && is_file($file)) {
                unlink($file);
            }
        }
    }

    protected function setUp(): void
    {
        if (!self::$server) {
            self::markTestSkipped('php -S stub server could not be started');
        }
    }

    private static function ok(array $payload): array
    {
        return array('code' => 200, 'body' => json_encode($payload));
    }

    private static function hook(string $id, string $url): array
    {
        return array('id' => $id, 'url' => $url, 'events' => array());
    }

    /**
     * @return array{0: array, 1: string[]}  The sync result and the "METHOD PATH" lines the stub saw.
     */
    private function sync(array $scenario): array
    {
        $scenario += array(
            'token'    => self::ok(array('access_token' => 'tok')),
            'list'     => self::ok(array('webhooks' => array())),
            'delete'   => array('code' => 204, 'body' => ''),
            'register' => array('code' => 201, 'body' => json_encode(array('id' => 'new', 'secret' => 's3cr3t'))),
        );
        file_put_contents(self::$scenarioFile, json_encode($scenario));
        file_put_contents(self::$logFile, '');
        $result = mobilepay_webhook_sync(array(
            'apiBase' => self::$base, 'expectedUrl' => self::EXPECTED, 'db' => 'acme',
            'clientId' => 'id', 'clientSecret' => 'sec', 'subscriptionKey' => 'sub', 'msn' => '123456',
            'connectTimeout' => 2, 'timeout' => 5,
        ));
        $calls = array();
        foreach (array_filter(explode("\n", (string)file_get_contents(self::$logFile))) as $line) {
            $parts   = explode(' ', $line, 3);
            $calls[] = $parts[0] . ' ' . $parts[1];
        }
        return array($result, $calls);
    }

    public function testFirstTimeSetupRegistersTheWebhook(): void
    {
        [$r, $calls] = $this->sync(array());
        self::assertTrue($r['reconciled']);
        self::assertTrue($r['registered']);
        self::assertSame('s3cr3t', $r['secret']);
        self::assertSame(array(), $r['errors']);
        self::assertSame(array('POST /accesstoken/get', 'GET /webhooks/v1/webhooks', 'POST /webhooks/v1/webhooks'), $calls);
    }

    public function testRegistrationPostsTheExpectedUrlAndEvents(): void
    {
        $this->sync(array());
        $lines = array_values(array_filter(explode("\n", (string)file_get_contents(self::$logFile))));
        $body  = json_decode(explode(' ', $lines[2], 3)[2], true);
        self::assertSame(self::EXPECTED, $body['url']);
        self::assertSame(mobilepay_webhook_events(), $body['events']);
    }

    public function testAlreadyRegisteredMakesNoChanges(): void
    {
        [$r, $calls] = $this->sync(array('list' => self::ok(array('webhooks' => array(self::hook('a', self::EXPECTED))))));
        self::assertTrue($r['reconciled']);
        self::assertFalse($r['registered']);
        self::assertNull($r['secret']);
        self::assertSame(array('POST /accesstoken/get', 'GET /webhooks/v1/webhooks'), $calls);
    }

    public function testStaleWebhookForThisDbIsDeletedAndOtherAccountsAreLeftAlone(): void
    {
        [$r, $calls] = $this->sync(array('list' => self::ok(array('webhooks' => array(
            self::hook('stale-1', self::STALE),
            self::hook('other-1', self::OTHER_DB),
            self::hook('foreign', 'https://elsewhere.example.com/hook?db=acme'),
        )))));
        self::assertTrue($r['reconciled']);
        self::assertSame(1, $r['staleDeleted']);
        self::assertTrue($r['registered']);
        self::assertContains('DELETE /webhooks/v1/webhooks/stale-1', $calls);
        self::assertNotContains('DELETE /webhooks/v1/webhooks/other-1', $calls);
        self::assertNotContains('DELETE /webhooks/v1/webhooks/foreign', $calls);
    }

    public static function failingCalls(): array
    {
        $out = array();
        foreach (array(400, 401, 500, 503) as $code) {
            $out["token $code"]    = array('token', $code, array('POST /accesstoken/get'));
            $out["list $code"]     = array('list', $code, array('POST /accesstoken/get', 'GET /webhooks/v1/webhooks'));
            $out["register $code"] = array('register', $code, array('POST /accesstoken/get', 'GET /webhooks/v1/webhooks', 'POST /webhooks/v1/webhooks'));
        }
        return $out;
    }

    #[DataProvider('failingCalls')]
    public function testNon2xxFromTokenListOrRegisterStopsWithoutReconciling(string $call, int $code, array $expectedCalls): void
    {
        [$r, $calls] = $this->sync(array($call => array('code' => $code, 'body' => '{"title":"nope"}')));
        self::assertFalse($r['reconciled']);
        self::assertFalse($r['registered']);
        self::assertNull($r['secret']);
        self::assertNotEmpty($r['errors']);
        self::assertStringContainsString("http: $code", $r['errors'][0]);
        self::assertSame($expectedCalls, $calls);
    }

    public static function deleteFailures(): array
    {
        return array('404' => array(404), '409' => array(409), '500' => array(500));
    }

    #[DataProvider('deleteFailures')]
    public function testUndeletableStaleWebhookKeepsTheDbUnreconciled(int $code): void
    {
        [$r, $calls] = $this->sync(array(
            'list'   => self::ok(array('webhooks' => array(self::hook('stale-1', self::STALE)))),
            'delete' => array('code' => $code, 'body' => ''),
        ));
        self::assertFalse($r['reconciled']);
        self::assertTrue($r['staleDeleteFailed']);
        self::assertSame(0, $r['staleDeleted']);
        // the new webhook is still registered and its secret handed back, so payments keep working
        self::assertTrue($r['registered']);
        self::assertSame('s3cr3t', $r['secret']);
        self::assertContains('DELETE /webhooks/v1/webhooks/stale-1', $calls);
    }

    public function testStaleWebhookWithoutUsableIdCountsAsDeleteFailure(): void
    {
        [$r, $calls] = $this->sync(array('list' => self::ok(array('webhooks' => array(
            array('id' => array('x'), 'url' => self::STALE),
            self::hook('a', self::EXPECTED),
        )))));
        self::assertFalse($r['reconciled']);
        self::assertTrue($r['staleDeleteFailed']);
        self::assertSame(array('POST /accesstoken/get', 'GET /webhooks/v1/webhooks'), $calls);
    }

    public static function unexpectedListBodies(): array
    {
        return array(
            'empty body'        => array(''),
            'html'              => array('<html>maintenance</html>'),
            'json null'         => array('null'),
            'no webhooks key'   => array('{"items":[]}'),
            'webhooks not list' => array('{"webhooks":"none"}'),
        );
    }

    #[DataProvider('unexpectedListBodies')]
    public function testUnexpected2xxListPayloadIsAFailureNotAnEmptyList(string $body): void
    {
        [$r, $calls] = $this->sync(array('list' => array('code' => 200, 'body' => $body)));
        self::assertFalse($r['reconciled']);
        self::assertFalse($r['registered']);
        self::assertSame(array('POST /accesstoken/get', 'GET /webhooks/v1/webhooks'), $calls);
    }

    public function testTokenReplyWithoutAccessTokenStops(): void
    {
        [$r, $calls] = $this->sync(array('token' => self::ok(array('token_type' => 'Bearer'))));
        self::assertFalse($r['reconciled']);
        self::assertSame(array('POST /accesstoken/get'), $calls);
    }

    public function testRegisterReplyWithoutSecretIsNotReconciled(): void
    {
        [$r] = $this->sync(array('register' => self::ok(array('id' => 'new'))));
        self::assertFalse($r['reconciled']);
        self::assertFalse($r['registered']);
        self::assertNull($r['secret']);
    }

    public function testUnreachableApiIsATransportFailure(): void
    {
        $r = mobilepay_webhook_sync(array(
            'apiBase' => 'http://127.0.0.1:1', 'expectedUrl' => self::EXPECTED, 'db' => 'acme',
            'clientId' => 'id', 'clientSecret' => 'sec', 'subscriptionKey' => 'sub', 'msn' => '123456',
            'connectTimeout' => 1, 'timeout' => 2,
        ));
        self::assertFalse($r['reconciled']);
        self::assertStringContainsString('http: 0', $r['errors'][0]);
    }

    public function testMissingExpectedUrlOrDbMakesNoRequest(): void
    {
        file_put_contents(self::$logFile, '');
        $r = mobilepay_webhook_sync(array('apiBase' => self::$base, 'expectedUrl' => '', 'db' => 'acme',
            'clientId' => 'id', 'clientSecret' => 'sec', 'subscriptionKey' => 'sub', 'msn' => '1'));
        self::assertFalse($r['reconciled']);
        self::assertSame('', (string)file_get_contents(self::$logFile));
    }
}
