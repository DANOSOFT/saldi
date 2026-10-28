<?php
// tests/shopify/WebhookEndpointTest.php
//
// api/shopify/webhook.php over HTTP (SD-748): `php -S` serves the endpoint with SALDI_SHOPIFY_DB
// set to a throwaway tenant, and each test posts to it the way Shopify does. Needs the
// install's includes/connect.php with the same master database as ShopifyEnv, because the
// endpoint connects through it; skips otherwise. The webhook secret is random per run.
//
// History:
// 20261027 CL/SZ SD-748: created.
// 20261028 CL/SZ SD-748: a body over 10 MB gets 413; the worker command promotes a stored delivery.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class WebhookEndpointTest extends TestCase
{
    private static string $secret = '';

    private static ?string $skip = null;
    private static string $db = '';
    /** @var array{proc: resource, pipes: array<int, resource>, port: int, log: string}|null */
    private static ?array $server = null;

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason() ?? self::connectReason();
        if (self::$skip !== null) {
            return;
        }
        self::$secret = bin2hex(random_bytes(16));
        try {
            self::$db = ShopifyEnv::testDb('_e');
            ShopifyEnv::cloneTenant(self::$db);
            ShopifyEnv::registerTenant(self::$db);
            $run = ShopifyEnv::runSchema(self::$db, 'ensure');
            if ($run['exit'] !== 0) {
                throw new RuntimeException('schema ensure failed: ' . $run['stderr']);
            }
            self::$server = self::startServer(['SALDI_SHOPIFY_DB' => self::$db]);
        } catch (Throwable $e) {
            self::tearDownAfterClass();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer(self::$server);
        self::$server = null;
        ShopifyEnv::unregisterTenants();
        ShopifyEnv::dropOwnedTenants();
    }

    protected function setUp(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
        $conn = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($conn, 'DELETE FROM shopify_jobs');
        ShopifyEnv::rows($conn, 'DELETE FROM shopify_events');
        ShopifyEnv::rows($conn, 'DELETE FROM shopify_shops');
        ShopifyEnv::rows($conn, "DELETE FROM settings WHERE var_grp LIKE 'shopify:%'");
        ShopifyEnv::rows($conn, "DELETE FROM grupper WHERE art = 'RA'");
        ShopifyEnv::rows($conn, "INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('RA', 1, 'RA', '01', $1, '12', $1, 'on')", [date('Y')]);
        ShopifyEnv::rows($conn, "INSERT INTO shopify_shops (shop_key, domain, is_primary, orders_enabled) VALUES ('hml', 'hml-test.myshopify.com', true, true)");
        ShopifyEnv::rows($conn, "INSERT INTO settings (var_grp, var_name, var_value, var_description) VALUES ('shopify:hml', 'webhook_secret', $1, '')", [self::$secret]);
        pg_close($conn);
    }

    public function testTheSameWebhookTwiceGives200TwiceAndOneInboxRow(): void
    {
        $raw = rtrim((string)file_get_contents(__DIR__ . '/fixtures/webhooks/orders_paid.json'), "\n");

        $first = $this->post('hml', $raw, self::headers($raw));
        $second = $this->post('hml', $raw, self::headers($raw, ['X-Shopify-Webhook-Id: wh-attempt-2']));

        $this->assertSame([200, '{"status":"stored"}'], $first);
        $this->assertSame([200, '{"status":"duplicate"}'], $second);
        $this->assertSame([['ORDERS_PAID', 'ev-http-1', 'received']], array_map(fn(array $r): array => [$r['topic'], $r['event_id'], $r['state']], $this->events()));
        $this->assertSame($raw, base64_decode($this->events()[0]['payload_b64']));
    }

    public function testAWrongSignatureGets401AndCreatesNoRows(): void
    {
        $raw = '{"id":5}';
        $headers = self::headers($raw);
        $headers[1] = 'X-Shopify-Hmac-Sha256: ' . base64_encode(hash_hmac('sha256', $raw, bin2hex(random_bytes(16)), true));

        $this->assertSame([401, '{"status":"bad_signature"}'], $this->post('hml', $raw, $headers));
        $this->assertSame([], $this->events());
    }

    public function testTheTenantNeverComesFromTheRequest(): void
    {
        $raw = '{"id":5}';
        $this->assertSame([400, '{"status":"bad_request"}'], $this->post('hml', $raw, self::headers($raw), '&db=' . ShopifyEnv::masterDb()));
        $this->assertSame([400, '{"status":"bad_request"}'], $this->post('hml', $raw, self::headers($raw), '', 'db=' . self::$db));
        $this->assertSame([], $this->events());
    }

    public function testOnlyAPostForAKnownShopIsTaken(): void
    {
        $raw = '{"id":5}';
        $this->assertSame([405, '{"status":"method_not_allowed"}'], $this->request('GET', '/api/shopify/webhook.php?shop=hml', '', []));
        $this->assertSame([400, '{"status":"bad_request"}'], $this->post('../hml', $raw, self::headers($raw)));
        $this->assertSame([400, '{"status":"bad_request"}'], $this->request('POST', '/api/shopify/webhook.php', $raw, self::headers($raw)));
        $this->assertSame([404, '{"status":"unknown_shop"}'], $this->post('brand9', $raw, self::headers($raw)));
        $this->assertSame([], $this->events());
    }

    public function testTheWorkerCommandTurnsAStoredDeliveryIntoAQueuedJob(): void
    {
        $raw = rtrim((string)file_get_contents(__DIR__ . '/fixtures/webhooks/orders_paid.json'), "\n");
        $this->assertSame([200, '{"status":"stored"}'], $this->post('hml', $raw, self::headers($raw)));

        $dir = ShopifyEnv::repoRoot() . '/temp/' . self::$db;
        $madeDir = !is_dir($dir);
        $proc = proc_open([PHP_BINARY, ShopifyEnv::repoRoot() . '/tools/shopify_worker.php', 'run', '--db=' . self::$db, '--max-seconds=5'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ShopifyEnv::repoRoot());
        $stdout = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);
        if ($madeDir) {
            @unlink("$dir/.ht_shopify_worker.lock");
            @rmdir($dir);
        }

        $this->assertSame(0, $exit, $stderr);
        $summary = json_decode($stdout, true);
        $this->assertSame(1, $summary['producers']['events']['queued'] ?? null, $stdout);
        $this->assertSame('queued', $this->events()[0]['state']);
        $conn = ShopifyEnv::connect(self::$db);
        $jobs = ShopifyEnv::rows($conn, 'SELECT type, event_row_id FROM shopify_jobs');
        pg_close($conn);
        $this->assertSame([['order.import', (int)$this->events()[0]['id']]], array_map(fn(array $j): array => [$j['type'], (int)$j['event_row_id']], $jobs));
    }

    public function testABodyOverTenMegabytesGets413AndIsNotStored(): void
    {
        $raw = '{"id":5,"pad":"' . str_repeat('a', 10485760) . '"}';
        $this->assertSame([413, '{"status":"payload_too_large"}'], $this->post('hml', $raw, self::headers($raw)));
        $this->assertSame([], $this->events());
    }

    public function testWithoutAConfiguredTenantItAnswers503(): void
    {
        if (is_file(ShopifyEnv::repoRoot() . '/includes/shopify/.ht_shopify_db')) {
            $this->markTestSkipped('includes/shopify/.ht_shopify_db is set on this install');
        }
        $bare = self::startServer([]);
        try {
            $raw = '{"id":5}';
            $this->assertSame([503, '{"status":"unavailable"}'], $this->request('POST', '/api/shopify/webhook.php?shop=hml', $raw, self::headers($raw), $bare['port']));
        } finally {
            self::stopServer($bare);
        }
        $this->assertSame([], $this->events());
    }

    // ---- helpers ---------------------------------------------------------------------------

    /** Null when includes/connect.php can serve the endpoint against ShopifyEnv's master. */
    private static function connectReason(): ?string
    {
        $file = ShopifyEnv::repoRoot() . '/includes/connect.php';
        if (!is_readable($file)) {
            return 'includes/connect.php is not installed';
        }
        if (!preg_match('/^\$sqdb\s*=\s*"([^"]*)"/m', (string)file_get_contents($file), $m) || $m[1] !== ShopifyEnv::masterDb()) {
            return 'includes/connect.php uses another master database than SALDI_SHOPIFY_MASTER_DB';
        }
        return null;
    }

    private static function headers(string $raw, array $extra = []): array
    {
        return array_merge([
            'X-Shopify-Topic: orders/paid',
            'X-Shopify-Hmac-Sha256: ' . base64_encode(hash_hmac('sha256', $raw, self::$secret, true)),
            'X-Shopify-Shop-Domain: hml-test.myshopify.com',
            'X-Shopify-Event-Id: ev-http-1',
            'X-Shopify-Webhook-Id: wh-attempt-1',
            'X-Shopify-Triggered-At: 2026-10-27T08:12:44.123Z',
            'X-Shopify-API-Version: 2026-07',
            'Content-Type: application/json',
        ], $extra);
    }

    private function post(string $shop, string $raw, array $headers, string $query = '', ?string $cookie = null): array
    {
        if ($cookie !== null) {
            $headers[] = "Cookie: $cookie";
        }
        return $this->request('POST', '/api/shopify/webhook.php?shop=' . rawurlencode($shop) . $query, $raw, $headers);
    }

    /** @return array{0: int, 1: string} status and body */
    private function request(string $method, string $path, string $body, array $headers, ?int $port = null): array
    {
        $ch = curl_init('http://127.0.0.1:' . ($port ?? self::$server['port']) . $path);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 20]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->assertIsString($response, curl_error($ch));
        return [$status, $response];
    }

    private function events(): array
    {
        $conn = ShopifyEnv::connect(self::$db);
        try {
            return ShopifyEnv::rows($conn, 'SELECT * FROM shopify_events ORDER BY id');
        } finally {
            pg_close($conn);
        }
    }

    /** @return array{proc: resource, pipes: array<int, resource>, port: int, log: string} */
    private static function startServer(array $env): array
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);
        $log = (string)tempnam(sys_get_temp_dir(), 'sd748-server');
        $cmd = [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', ShopifyEnv::repoRoot(), __DIR__ . '/support/webhook_router.php'];
        $baseEnv = ['PATH' => (string)getenv('PATH')];
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, ShopifyEnv::repoRoot(), $baseEnv + $env);
        if (!is_resource($proc)) {
            throw new RuntimeException('could not start php -S');
        }
        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($socket) {
                fclose($socket);
                return ['proc' => $proc, 'pipes' => $pipes, 'port' => $port, 'log' => $log];
            }
            usleep(100000);
        }
        self::stopServer(['proc' => $proc, 'pipes' => $pipes, 'port' => $port, 'log' => $log]);
        throw new RuntimeException("php -S did not start on port $port");
    }

    private static function stopServer(?array $server): void
    {
        if ($server === null) {
            return;
        }
        proc_terminate($server['proc']);
        foreach ($server['pipes'] as $pipe) {
            fclose($pipe);
        }
        proc_close($server['proc']);
        if (is_file($server['log'])) {
            unlink($server['log']);
        }
    }
}
