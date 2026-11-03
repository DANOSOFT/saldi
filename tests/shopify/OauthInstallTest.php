<?php
// tests/shopify/OauthInstallTest.php
//
// The app install (includes/shopify/oauth.php, SD-790) on a throwaway tenant: Shopify's signed
// install request, the state, the code exchange against a stand-in transport, and the uninstall
// job. The requests are signed here the way Shopify documents it, so the check under test meets
// an independent signature. The client secret is random per run.
//
// History:
// 20261103 CL/MJ SD-790: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class OauthInstallTest extends TestCase
{
    private const DOMAIN = 'hml-test.myshopify.com';
    private const BASE = 'https://dev-mj.example.dk/mj';
    private const CLIENT_ID = 'c0ffee00c0ffee00c0ffee00c0ffee00';
    private const NOW = 1793700000;

    private static string $secret = '';
    private static ?string $skip = null;
    private static string $db = '';

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
        if (self::$skip !== null) {
            return;
        }
        self::$secret = bin2hex(random_bytes(16));
        try {
            self::$db = ShopifyEnv::testDb('_o');
            ShopifyEnv::cloneTenant(self::$db);
            ShopifyEnv::registerTenant(self::$db);
            $run = ShopifyEnv::runSchema(self::$db, 'ensure');
            if ($run['exit'] !== 0) {
                throw new RuntimeException('schema ensure failed: ' . $run['stderr']);
            }
        } catch (Throwable $e) {
            self::tearDownAfterClass();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void
    {
        ShopifyEnv::unregisterTenants();
        ShopifyEnv::dropOwnedTenants();
    }

    protected function setUp(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
        $conn = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($conn, 'DELETE FROM shopify_errors');
        ShopifyEnv::rows($conn, 'DELETE FROM shopify_jobs');
        ShopifyEnv::rows($conn, 'DELETE FROM shopify_events');
        ShopifyEnv::rows($conn, 'DELETE FROM shopify_shops');
        ShopifyEnv::rows($conn, "DELETE FROM settings WHERE var_grp LIKE 'shopify:%' OR (var_grp = 'shopify' AND var_name = 'webhook_base_url')");
        ShopifyEnv::rows($conn, "INSERT INTO shopify_shops (shop_key, domain, is_primary, orders_enabled, customers_enabled, stock_publish_enabled) VALUES ('hml', $1, true, true, true, true)", [self::DOMAIN]);
        ShopifyEnv::rows($conn, "INSERT INTO settings (var_grp, var_name, var_value, var_description) VALUES ('shopify:hml', 'webhook_secret', $1, ''), ('shopify:hml', 'client_id', $2, ''), ('shopify', 'webhook_base_url', $3, '')", [self::$secret, self::CLIENT_ID, self::BASE]);
        pg_close($conn);
    }

    public function testTheInstallRedirectsToTheApprovalPageForAnOfflineToken(): void
    {
        $run = $this->request(self::signed(['shop' => self::DOMAIN, 'host' => 'YWRtaW4uc2hvcGlmeS5jb20vc3RvcmUvaG1sLXRlc3Q', 'timestamp' => (string)self::NOW]));

        $this->assertSame([302, 'authorize'], [$run['result']['status'], $run['result']['outcome']]);
        $url = parse_url($run['result']['location']);
        parse_str($url['query'], $params);
        $this->assertSame(['https', self::DOMAIN, '/admin/oauth/authorize'], [$url['scheme'], $url['host'], $url['path']]);
        $this->assertSame(['client_id', 'scope', 'redirect_uri', 'state'], array_keys($params), 'no grant_options: the token is offline, and no expiring=1');
        $this->assertSame(self::CLIENT_ID, $params['client_id']);
        $this->assertSame(self::BASE . '/api/shopify/oauth.php', $params['redirect_uri']);
        $this->assertStringNotContainsString('read_all_orders', $params['scope']);
        $this->assertSame($params['state'], explode(':', (string)$this->setting('oauth_state'))[0], 'the state in the link is the stored one');
        $this->assertSame((string)(self::NOW + 600), explode(':', (string)$this->setting('oauth_state'))[1], 'it expires after ten minutes');
        $this->assertSame([], $run['calls']);
    }

    public function testARequestShopifyDidNotSignIsRefusedAndStoresNothing(): void
    {
        $good = self::signed(['shop' => self::DOMAIN, 'timestamp' => (string)self::NOW]);
        $otherKey = $good;
        $otherKey['hmac'] = hash_hmac('sha256', 'shop=' . self::DOMAIN . '&timestamp=' . self::NOW, bin2hex(random_bytes(16)));
        $changed = $good;
        $changed['timestamp'] = (string)(self::NOW + 1);
        $noHmac = $good;
        unset($noHmac['hmac']);
        $array = self::signed(['shop' => self::DOMAIN, 'timestamp' => (string)self::NOW]) + ['ids' => ['1', '2']];

        $this->assertSame([401, 'bad_signature'], self::answer($this->request($otherKey)), 'signed with another secret');
        $this->assertSame([401, 'bad_signature'], self::answer($this->request($changed)), 'a parameter changed after signing');
        $this->assertSame([401, 'bad_signature'], self::answer($this->request($noHmac)), 'no signature');
        $this->assertSame([401, 'bad_signature'], self::answer($this->request($array)), 'a list parameter');
        $this->assertSame([401, 'bad_signature'], self::answer($this->request($good, self::NOW + 3601)), 'signed more than an hour ago');
        $this->assertSame([404, 'unknown_shop'], self::answer($this->request(self::signed(['shop' => 'brand9.myshopify.com', 'timestamp' => (string)self::NOW]))));
        $this->assertSame([400, 'bad_request'], self::answer($this->request(self::signed(['shop' => 'evil.example.com', 'timestamp' => (string)self::NOW]))));
        $this->assertNull($this->setting('oauth_state'));

        $conn = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($conn, "DELETE FROM settings WHERE var_grp = 'shopify:hml' AND var_name = 'client_id'");
        pg_close($conn);
        $this->assertSame([503, 'not_configured'], self::answer($this->request($good)), 'no client ID for the shop');
        $this->assertNull($this->setting('oauth_state'));
    }

    public function testTheCallbackExchangesTheCodeOnceAndStoresTheToken(): void
    {
        $state = $this->begin();
        $token = 'shpat_' . bin2hex(random_bytes(16));
        $answer = ['status' => 200, 'body' => json_encode(['access_token' => $token, 'scope' => 'write_products,write_orders'])];
        $callback = self::signed(['code' => 'code-1', 'shop' => self::DOMAIN, 'state' => $state, 'timestamp' => (string)(self::NOW + 20)]);

        $run = $this->request($callback, self::NOW + 20, $answer);
        $again = $this->request($callback, self::NOW + 21, $answer);

        $this->assertSame([302, 'installed', 'https://' . self::DOMAIN . '/admin/apps'], [$run['result']['status'], $run['result']['outcome'], $run['result']['location']]);
        $this->assertSame([['url' => 'https://' . self::DOMAIN . '/admin/oauth/access_token', 'headers' => ['Content-Type: application/json', 'Accept: application/json'],
            'body' => ['client_id' => self::CLIENT_ID, 'client_secret' => self::$secret, 'code' => 'code-1']]], $run['calls']);
        $this->assertSame($token, $this->setting('access_token'));
        $this->assertSame(gmdate('Y-m-d\TH:i:s\Z', self::NOW + 20), $this->setting('installed_at'));
        $this->assertNull($this->setting('oauth_state'), 'the state is used up');
        $this->assertSame([['webhook.health', 'queued']], array_map(fn(array $j): array => [$j['type'], $j['state']], $this->jobs()), 'the webhooks get subscribed');
        $this->assertStringNotContainsString($token, $run['stderr']);
        $this->assertStringNotContainsString(self::$secret, $run['stderr']);
        $this->assertStringNotContainsString('code-1', $run['stderr']);
        $this->assertSame([401, 'bad_state'], self::answer($again), 'the same callback a second time');
        $this->assertSame([], $again['calls'], 'is never exchanged');
    }

    public function testAWrongOrExpiredStateIsRefusedWithoutAnExchange(): void
    {
        $state = $this->begin();
        $wrong = $this->request(self::signed(['code' => 'c', 'shop' => self::DOMAIN, 'state' => strrev($state), 'timestamp' => (string)self::NOW]));
        $burnt = $this->request(self::signed(['code' => 'c', 'shop' => self::DOMAIN, 'state' => $state, 'timestamp' => (string)self::NOW]));
        $late = $this->begin();
        $expired = $this->request(self::signed(['code' => 'c', 'shop' => self::DOMAIN, 'state' => $late, 'timestamp' => (string)(self::NOW + 601)]), self::NOW + 601);
        $none = $this->request(self::signed(['code' => 'c', 'shop' => self::DOMAIN, 'timestamp' => (string)self::NOW]));

        $this->assertSame([401, 'bad_state'], self::answer($wrong));
        $this->assertSame([401, 'bad_state'], self::answer($burnt), 'a wrong state uses the pending one up');
        $this->assertSame([401, 'bad_state'], self::answer($expired));
        $this->assertSame([401, 'bad_state'], self::answer($none), 'no install pending');
        $this->assertSame([], array_merge($wrong['calls'], $burnt['calls'], $expired['calls'], $none['calls']));
        $this->assertNull($this->setting('access_token'));
    }

    public function testARefusedExchangeStoresNoToken(): void
    {
        $state = $this->begin();
        $run = $this->request(self::signed(['code' => 'used', 'shop' => self::DOMAIN, 'state' => $state, 'timestamp' => (string)self::NOW]), self::NOW,
            ['status' => 400, 'body' => '{"error":"invalid_request","error_description":"The authorization code was not found or was already used"}']);

        $this->assertSame([502, 'exchange_failed'], self::answer($run));
        $this->assertCount(1, $run['calls']);
        $this->assertNull($this->setting('access_token'));
        $this->assertSame([], $this->jobs());
    }

    public function testAppUninstalledIsPromotedWithEveryFlowOffAndDeletesTheTokenAndFlows(): void
    {
        $this->install('2026-11-03T08:00:00Z');
        $event = $this->uninstallEvent('2026-11-03T09:00:00Z');
        $conn = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($conn, 'UPDATE shopify_shops SET orders_enabled = false, customers_enabled = false');
        pg_close($conn);

        $promoted = $this->run_('promote');
        $job = $this->jobs()[0];
        $done = $this->run_('uninstall', ['job' => ['id' => (int)$job['id'], 'shop_id' => (int)$job['shop_id'], 'payload' => json_decode($job['payload'], true)]]);

        $this->assertSame([['APP_UNINSTALLED', 'queued']], array_map(fn(array $e): array => [$e['topic'], $e['state']], $promoted['result']['events']), 'promoted whatever the flows');
        $this->assertSame(['shop.uninstalled', $event, 8], [$job['type'], (int)$job['event_row_id'], (int)$job['priority']]);
        $this->assertSame(['outcome' => 'done'], $done['result']);
        $this->assertNull($this->setting('access_token'));
        $this->assertNull($this->setting('installed_at'));
        $this->assertSame((string)self::CLIENT_ID, $this->setting('client_id'), 'a reinstall needs only the install link');
        $conn = ShopifyEnv::connect(self::$db);
        $shop = ShopifyEnv::one($conn, 'SELECT * FROM shopify_shops');
        $errors = ShopifyEnv::rows($conn, 'SELECT kind, ref FROM shopify_errors');
        pg_close($conn);
        foreach (['products', 'prices', 'stock_capture', 'stock_publish', 'orders', 'customers', 'store_orders'] as $flow) {
            $this->assertSame('f', $shop[$flow . '_enabled'], $flow);
        }
        $this->assertSame([['kind' => 'shop.uninstalled', 'ref' => 'hml']], $errors);
    }

    public function testAnUninstallFromBeforeTheCurrentInstallChangesNothing(): void
    {
        $token = $this->install('2026-11-03T09:30:00Z');
        $event = $this->uninstallEvent('2026-11-03T09:00:00Z');

        $done = $this->run_('uninstall', ['job' => ['id' => 1, 'shop_id' => $this->shopId(), 'payload' => ['event_row_id' => $event]]]);

        $this->assertSame(['outcome' => 'done'], $done['result']);
        $this->assertSame($token, $this->setting('access_token'));
        $conn = ShopifyEnv::connect(self::$db);
        $this->assertSame('t', ShopifyEnv::one($conn, 'SELECT orders_enabled FROM shopify_shops')['orders_enabled']);
        pg_close($conn);
    }

    // ---- helpers ---------------------------------------------------------------------------

    /** Signs $query as Shopify does: the other parameters sorted by name, name=value joined with &, hex HMAC-SHA256. */
    private static function signed(array $query): array
    {
        $sorted = $query;
        ksort($sorted);
        $pairs = [];
        foreach ($sorted as $name => $value) {
            $pairs[] = "$name=$value";
        }
        return $query + ['hmac' => hash_hmac('sha256', implode('&', $pairs), self::$secret)];
    }

    private static function answer(array $run): array
    {
        return [$run['result']['status'], $run['result']['outcome']];
    }

    /** Starts an install and returns its state. */
    private function begin(): string
    {
        $run = $this->request(self::signed(['shop' => self::DOMAIN, 'timestamp' => (string)self::NOW]));
        parse_str((string)parse_url($run['result']['location'], PHP_URL_QUERY), $params);
        return $params['state'];
    }

    private function install(string $at): string
    {
        $token = 'shpat_' . bin2hex(random_bytes(8));
        $conn = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($conn, "INSERT INTO settings (var_grp, var_name, var_value, var_description) VALUES ('shopify:hml', 'access_token', $1, ''), ('shopify:hml', 'installed_at', $2, '')", [$token, $at]);
        pg_close($conn);
        return $token;
    }

    private function uninstallEvent(string $triggeredAt): int
    {
        $conn = ShopifyEnv::connect(self::$db);
        $row = ShopifyEnv::one($conn, "INSERT INTO shopify_events (shop_id, topic, event_id, webhook_id, triggered_at, api_version, payload_b64, state)"
            . " VALUES ($1, 'APP_UNINSTALLED', 'ev-u-1', 'wh-u-1', $2, '2026-07', $3, 'received') RETURNING id",
            [$this->shopId(), $triggeredAt, base64_encode('{"id":1,"domain":"' . self::DOMAIN . '"}')]);
        pg_close($conn);
        return (int)$row['id'];
    }

    private function shopId(): int
    {
        $conn = ShopifyEnv::connect(self::$db);
        $id = (int)ShopifyEnv::one($conn, "SELECT id FROM shopify_shops WHERE shop_key = 'hml'")['id'];
        pg_close($conn);
        return $id;
    }

    private function request(array $query, int $now = self::NOW, ?array $answer = null): array
    {
        return $this->run_('request', ['query' => $query, 'now' => $now, 'answer' => $answer]);
    }

    private function run_(string $mode, ?array $spec = null): array
    {
        $file = null;
        $args = [$mode, self::$db];
        if ($spec !== null) {
            $file = (string)tempnam(sys_get_temp_dir(), 'sd790-spec');
            file_put_contents($file, json_encode($spec));
            $args[] = $file;
        }
        try {
            $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_oauth.php', $args));
        } finally {
            if ($file !== null) {
                unlink($file);
            }
        }
        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        return $run['result'] + ['stderr' => $run['stderr']];
    }

    private function setting(string $name): ?string
    {
        $conn = ShopifyEnv::connect(self::$db);
        $row = ShopifyEnv::one($conn, "SELECT var_value FROM settings WHERE var_grp = 'shopify:hml' AND var_name = $1", [$name]);
        pg_close($conn);
        return $row['var_value'] ?? null;
    }

    private function jobs(): array
    {
        $conn = ShopifyEnv::connect(self::$db);
        try {
            return ShopifyEnv::rows($conn, 'SELECT * FROM shopify_jobs ORDER BY id');
        } finally {
            pg_close($conn);
        }
    }
}
