<?php
// tests/shopify/OauthEndpointTest.php
//
// api/shopify/oauth.php over HTTP (SD-790): `php -S` serves the endpoint with SALDI_SHOPIFY_DB set
// to a throwaway tenant, and each test opens it the way Shopify's install link does. The exchange
// itself is covered in OauthInstallTest; here the wiring: method, tenant, redirect. Needs the
// install's includes/connect.php with the same master database as ShopifyEnv; skips otherwise.
//
// History:
// 20261103 CL/MJ SD-790: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class OauthEndpointTest extends TestCase
{
    private const DOMAIN = 'hml-test.myshopify.com';

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
            self::$db = ShopifyEnv::testDb('_oe');
            ShopifyEnv::cloneTenant(self::$db);
            ShopifyEnv::registerTenant(self::$db);
            $run = ShopifyEnv::runSchema(self::$db, 'ensure');
            if ($run['exit'] !== 0) {
                throw new RuntimeException('schema ensure failed: ' . $run['stderr']);
            }
            $conn = ShopifyEnv::connect(self::$db);
            ShopifyEnv::rows($conn, "INSERT INTO shopify_shops (shop_key, domain, is_primary) VALUES ('hml', $1, true)", [self::DOMAIN]);
            ShopifyEnv::rows($conn, "INSERT INTO settings (var_grp, var_name, var_value, var_description) VALUES ('shopify:hml', 'webhook_secret', $1, ''), ('shopify:hml', 'client_id', 'cid-1', ''), ('shopify', 'webhook_base_url', 'https://dev-mj.example.dk/mj', '')", [self::$secret]);
            pg_close($conn);
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
    }

    public function testAShopifySignedInstallIsRedirectedToTheApprovalPage(): void
    {
        [$status, $headers, $body] = $this->get(self::signed(['shop' => self::DOMAIN, 'timestamp' => (string)time()]));

        $this->assertSame([302, "Saldi: authorize\n"], [$status, $body]);
        $this->assertMatchesRegularExpression('#^location: https://hml-test\.myshopify\.com/admin/oauth/authorize\?client_id=cid-1&scope=[^&]+&redirect_uri=https%3A%2F%2Fdev-mj\.example\.dk%2Fmj%2Fapi%2Fshopify%2Foauth\.php&state=[0-9a-f]{32}\r?$#mi', $headers);
        $this->assertStringContainsStringIgnoringCase('cache-control: no-store', $headers);
    }

    public function testOnlyASignedGetForAKnownShopIsTakenAndTheTenantNeverComesFromTheRequest(): void
    {
        $signed = self::signed(['shop' => self::DOMAIN, 'timestamp' => (string)time()]);
        $this->assertSame(401, $this->get(['hmac' => strrev($signed['hmac'])] + $signed)[0]);
        $this->assertSame(404, $this->get(self::signed(['shop' => 'brand9.myshopify.com', 'timestamp' => (string)time()]))[0]);
        $this->assertSame(400, $this->get($signed + ['db' => ShopifyEnv::masterDb()])[0]);
        $this->assertSame(405, $this->get($signed, 'POST')[0]);
    }

    public function testSetAppStoresTheClientIdAndTheSecretFromStdin(): void
    {
        $secret = bin2hex(random_bytes(16));
        $set = $this->tool(['set-app', '--db=' . self::$db, '--shop=hml', '--client-id=cid-2'], "$secret\n");
        $keep = $this->tool(['set-app', '--db=' . self::$db, '--shop=hml', '--client-id=cid-3'], '');
        $unknown = $this->tool(['set-app', '--db=' . self::$db, '--shop=brand9', '--client-id=cid-3'], '');

        $conn = ShopifyEnv::connect(self::$db);
        $stored = ShopifyEnv::rows($conn, "SELECT var_name, var_value FROM settings WHERE var_grp = 'shopify:hml' AND var_name IN ('client_id', 'webhook_secret') ORDER BY var_name");
        ShopifyEnv::rows($conn, "UPDATE settings SET var_value = 'cid-1' WHERE var_grp = 'shopify:hml' AND var_name = 'client_id'");
        ShopifyEnv::rows($conn, "UPDATE settings SET var_value = $1 WHERE var_grp = 'shopify:hml' AND var_name = 'webhook_secret'", [self::$secret]);
        pg_close($conn);
        $this->assertSame([0, '{"shop":"hml","client_id":"set","client_secret":"set"}'], [$set['exit'], trim($set['stdout'])], $set['stderr']);
        $this->assertSame([0, '{"shop":"hml","client_id":"set","client_secret":"unchanged"}'], [$keep['exit'], trim($keep['stdout'])], $keep['stderr']);
        $this->assertSame([['var_name' => 'client_id', 'var_value' => 'cid-3'], ['var_name' => 'webhook_secret', 'var_value' => $secret]], $stored);
        $this->assertSame(2, $unknown['exit']);
        $this->assertStringNotContainsString($secret, $set['stdout'] . $set['stderr']);
    }

    // ---- helpers ---------------------------------------------------------------------------

    /** @return array{exit: int, stdout: string, stderr: string} tools/shopify_worker.php with $args and $stdin */
    private function tool(array $args, string $stdin): array
    {
        $proc = proc_open(array_merge([PHP_BINARY, ShopifyEnv::repoRoot() . '/tools/shopify_worker.php'], $args), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ShopifyEnv::repoRoot());
        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);
        $stdout = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['exit' => proc_close($proc), 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private static function signed(array $query): array
    {
        ksort($query);
        $pairs = [];
        foreach ($query as $name => $value) {
            $pairs[] = "$name=$value";
        }
        return $query + ['hmac' => hash_hmac('sha256', implode('&', $pairs), self::$secret)];
    }

    /** @return array{0: int, 1: string, 2: string} status, headers and body */
    private function get(array $query, string $method = 'GET'): array
    {
        $ch = curl_init('http://127.0.0.1:' . self::$server['port'] . '/api/shopify/oauth.php?' . http_build_query($query));
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 20]);
        $response = curl_exec($ch);
        $this->assertIsString($response, curl_error($ch));
        $size = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        return [(int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), substr($response, 0, $size), substr($response, $size)];
    }

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

    /** @return array{proc: resource, pipes: array<int, resource>, port: int, log: string} */
    private static function startServer(array $env): array
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);
        $log = (string)tempnam(sys_get_temp_dir(), 'sd790-server');
        $cmd = [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', ShopifyEnv::repoRoot(), __DIR__ . '/support/webhook_router.php'];
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, ShopifyEnv::repoRoot(), ['PATH' => (string)getenv('PATH')] + $env);
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
