<?php
// tests/shopify/DokumentLinkTest.php
//
// Document links and api/dokument.php (SD-752, WP11-2, K45) against a throwaway tenant: a link
// is deterministic and only its hash is stored; it opens its own document as a PDF with
// no-store, nosniff and no-referrer; a mutated, unknown, missing or revoked token gets the same
// 404; a revoked document gets a new link; a GET never posts, mails or restocks; the rate limit
// answers 429 and fails closed; a database failure gets 503; an empty secret row doesn't break
// stable links. The endpoint runs under `php -S` with dokument_router.php; the link calls run in
// a child process booted by shopify_bootstrap(). pdftk is fake_pdftk.php, ps2pdf is required.
// Skips when ShopifyEnv is not configured.
//
// History:
// 20261030 CL/SZ SD-752: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class DokumentLinkTest extends TestCase
{
    private const BASE = 'https://dokument.example.test/sz';

    private static ?string $skip = null;
    private static string $db = '';
    private static int $debtorId = 0;
    private static int $next = 9500;
    private static string $mailLog = '';
    /** @var array{proc: resource, pipes: array<int, resource>, port: int, log: string}|null */
    private static ?array $server = null;

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason() ?? self::connectReason();
        $ps2pdf = trim((string)shell_exec('command -v ps2pdf 2>/dev/null'));
        if (self::$skip === null && $ps2pdf === '') {
            self::$skip = 'ps2pdf (ghostscript) is not installed';
        }
        if (self::$skip !== null) {
            return;
        }
        chmod(__DIR__ . '/support/fake_pdftk.php', 0755);
        try {
            self::$db = ShopifyEnv::testDb('_d');
            self::removeTempDir();
            ShopifyEnv::cloneTenant(self::$db);
            ShopifyEnv::registerTenant(self::$db);
            $run = ShopifyEnv::runSchema(self::$db, 'ensure');
            if ($run['exit'] !== 0) {
                throw new RuntimeException('schema ensure failed: ' . $run['stderr']);
            }
            $conn = ShopifyEnv::connect(self::$db);
            ShopifyEnv::rows($conn, "DELETE FROM grupper WHERE art = 'RA'");
            ShopifyEnv::rows($conn, "INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('RA', 1, 'RA', '01', $1, '12', $1, 'on')", [date('Y')]);
            ShopifyEnv::rows($conn, "DELETE FROM settings WHERE var_grp = 'shopify' AND var_name IN ('webhook_base_url', 'dokument_link_secret')");
            ShopifyEnv::rows($conn, "INSERT INTO settings (var_grp, var_name, var_value, var_description) VALUES ('shopify', 'webhook_base_url', $1, '')", [self::BASE]);
            $debtor = ShopifyEnv::one($conn, "INSERT INTO adresser (kontonr, firmanavn, addr1, postnr, bynavn, email, art, gruppe, betalingsbet, betalingsdage)"
                . " VALUES (91950, 'Link Kunde', 'Linkvej 4', '8000', 'Aarhus', 'link@example.invalid', 'D', 1, 'Netto', 8) RETURNING id");
            self::$debtorId = (int)$debtor['id'];
            pg_close($conn);
            self::$mailLog = (string)tempnam(sys_get_temp_dir(), 'sd752-mail');
            self::$server = self::startServer([
                'SALDI_SHOPIFY_DB' => self::$db,
                'DOKUMENT_TEST_PDFTK' => __DIR__ . '/support/fake_pdftk.php',
                'DOKUMENT_TEST_MAIL_LOG' => self::$mailLog,
            ]);
        } catch (Throwable $e) {
            self::tearDownAfterClass();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer(self::$server);
        self::$server = null;
        if (self::$mailLog !== '' && is_file(self::$mailLog)) {
            unlink(self::$mailLog);
        }
        if (self::$db !== '') {
            self::removeTempDir();
        }
        ShopifyEnv::unregisterTenants();
        ShopifyEnv::dropOwnedTenants();
    }

    protected function setUp(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
        $rate = ShopifyEnv::repoRoot() . '/temp/' . self::$db . '/.ht_dokument_rate';
        if (is_file($rate)) {
            unlink($rate);
        }
        array_map('unlink', glob("$rate/*") ?: []);
    }

    private static function connectReason(): ?string
    {
        return is_readable(ShopifyEnv::repoRoot() . '/includes/connect.php') ? null : 'includes/connect.php is not installed';
    }

    /** Removes temp/<test db>/ with everything in it, also what an earlier, aborted run left. */
    private static function removeTempDir(): void
    {
        $dir = ShopifyEnv::repoRoot() . '/temp/' . self::$db;
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    /** @return array{id: int, fakturanr: string} a posted invoice (DO) or credit note (DK), 2 x 125.00 at 25% VAT */
    private function posted(string $art = 'DO', int $status = 3): array
    {
        $nr = (string)++self::$next;
        $sign = $art === 'DK' ? -1 : 1;
        $conn = ShopifyEnv::connect(self::$db);
        $order = ShopifyEnv::one($conn, "INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsbet, betalingsdage, kontonr, art, valuta,"
            . " ordredate, levdate, fakturadate, ordrenr, fakturanr, sum, moms, momssats, status, konto_id, udskriv_til, mail_fakt, sprog)"
            . " VALUES ('Link Kunde', 'Linkvej 4', '8000', 'Aarhus', 'link@example.invalid', 'Netto', 8, 91950, $1, 'DKK',"
            . " '2026-10-01', '2026-10-01', '2026-10-01', $2, $3, $4, $5, 25, $6, $7, 'email', 'on', 'Dansk') RETURNING id",
            [$art, (int)$nr, $nr, $sign * 250, $sign * 62.5, $status, self::$debtorId]);
        ShopifyEnv::rows($conn, "INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, beskrivelse, enhed, posnr, antal, pris, rabat, momssats, procent)"
            . " VALUES ($1, 0, 'L1', 'Linkvare', 'stk', 1, $2, 125.00, 0, 25, 100)", [(int)$order['id'], $sign * 2]);
        pg_close($conn);
        return ['id' => (int)$order['id'], 'fakturanr' => $nr];
    }

    /**
     * Runs dokument_link / dokument_resolve / dokument_revoke calls in one child process.
     *
     * @param list<list<mixed>> $calls
     * @return list<array<string, mixed>>
     */
    private function calls(array $calls): array
    {
        $run = ShopifyEnv::waitChild(ShopifyEnv::startChild('run_dokument_links.php', [self::$db, json_encode($calls)]));
        $this->assertIsArray($run['result'], "runner gave no JSON (exit {$run['exit']}): {$run['stdout']} {$run['stderr']}");
        return $run['result'];
    }

    private function link(int $ordreId, string $docType = 'invoice'): string
    {
        $out = $this->calls([['link', $ordreId, $docType]])[0];
        $this->assertArrayNotHasKey('error', $out, json_encode($out['error'] ?? null));
        return $out['result'];
    }

    private static function token(string $link): string
    {
        parse_str((string)parse_url($link, PHP_URL_QUERY), $q);
        return (string)$q['t'];
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    private function get(string $query, string $method = 'GET'): array
    {
        $port = self::$server['port'];
        $context = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true, 'timeout' => 60]]);
        $body = (string)file_get_contents("http://127.0.0.1:$port/api/dokument.php$query", false, $context);
        $headers = [];
        $status = 0;
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $line, $m)) {
                $status = (int)$m[1];
            } elseif (strpos($line, ':') !== false) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }
        return ['status' => $status, 'headers' => $headers, 'body' => $body];
    }

    /** The response without what differs per request anyway. */
    private static function comparable(array $response): array
    {
        unset($response['headers']['date']);
        return $response;
    }

    private function assertPrivateHeaders(array $response): void
    {
        $this->assertSame('no-store', $response['headers']['cache-control'] ?? null);
        $this->assertSame('nosniff', $response['headers']['x-content-type-options'] ?? null);
        $this->assertSame('no-referrer', $response['headers']['referrer-policy'] ?? null);
    }

    public function testTheSameOrderAndTypeGiveTheSameLinkAndOnlyItsHashIsStored(): void
    {
        $doc = $this->posted();

        $first = $this->link($doc['id']);
        $second = $this->link($doc['id']);

        $this->assertSame($first, $second);
        $this->assertMatchesRegularExpression('#^' . preg_quote(self::BASE, '#') . '/api/dokument\.php\?t=[A-Za-z0-9_-]{43}$#', $first);
        $conn = ShopifyEnv::connect(self::$db);
        $rows = ShopifyEnv::rows($conn, 'SELECT doc_type, key_version, token_hash, revoked_at FROM dokument_links WHERE ordre_id = $1', [$doc['id']]);
        pg_close($conn);
        $this->assertSame([['doc_type' => 'invoice', 'key_version' => '1', 'token_hash' => hash('sha256', self::token($first)), 'revoked_at' => null]], $rows);
    }

    public function testTheLinkOpensItsInvoiceAsAPrivatePdf(): void
    {
        $doc = $this->posted();
        $link = $this->link($doc['id']);

        $response = $this->get('?t=' . self::token($link));

        $this->assertSame(200, $response['status'], $response['body']);
        $this->assertSame('application/pdf', $response['headers']['content-type'] ?? null);
        $this->assertSame("inline; filename=\"fakt{$doc['fakturanr']}.pdf\"", $response['headers']['content-disposition'] ?? null);
        $this->assertSame('%PDF-', substr($response['body'], 0, 5));
        $this->assertPrivateHeaders($response);

        $head = $this->get('?t=' . self::token($link), 'HEAD');
        $this->assertSame(200, $head['status']);
        $this->assertSame('', $head['body']);
    }

    public function testACreditNoteLinkOpensTheCreditNote(): void
    {
        $doc = $this->posted('DK');

        $response = $this->get('?t=' . self::token($this->link($doc['id'], 'credit')));

        $this->assertSame(200, $response['status'], $response['body']);
        $this->assertSame("inline; filename=\"kn{$doc['fakturanr']}.pdf\"", $response['headers']['content-disposition'] ?? null);
    }

    public function testAMutatedTokenGetsTheSame404AsAnUnknownOne(): void
    {
        $token = self::token($this->link($this->posted()['id']));
        $mutated = substr($token, 0, -1) . ($token[-1] === 'A' ? 'B' : 'A');

        $responses = [
            'mutated' => $this->get('?t=' . $mutated),
            'unknown' => $this->get('?t=' . str_repeat('x', 43)),
            'malformed' => $this->get('?t=' . rawurlencode("' OR 1=1 --")),
            'missing' => $this->get(''),
            'array' => $this->get('?t[]=' . $token),
        ];

        $this->assertSame(404, $responses['mutated']['status']);
        $this->assertPrivateHeaders($responses['mutated']);
        foreach ($responses as $name => $response) {
            $this->assertSame(self::comparable($responses['mutated']), self::comparable($response), "$name differs from a mutated token");
        }
    }

    public function testARevokedLinkGets404AndTheNextLinkResolves(): void
    {
        $doc = $this->posted();
        $old = $this->link($doc['id']);

        $out = $this->calls([['revoke', $doc['id'], 'invoice'], ['link', $doc['id'], 'invoice'], ['link', $doc['id'], 'invoice']]);

        $new = $out[1]['result'];
        $this->assertNotSame($old, $new);
        $this->assertSame($new, $out[2]['result'], 'the new link is stable too');
        $this->assertSame(404, $this->get('?t=' . self::token($old))['status']);
        $this->assertSame(200, $this->get('?t=' . self::token($new))['status']);
        $resolved = $this->calls([['resolve', self::token($old)], ['resolve', self::token($new)]]);
        $this->assertNull($resolved[0]['result']);
        $this->assertSame(['ordre_id' => $doc['id'], 'doc_type' => 'invoice', 'key_version' => 2], $resolved[1]['result']);
    }

    public function testOnlyAPostedDocumentOfTheRightTypeGetsALink(): void
    {
        $invoice = $this->posted();
        $draft = $this->posted('DO', 1);

        $out = $this->calls([['link', $invoice['id'], 'credit'], ['link', $draft['id'], 'invoice'], ['link', 999999, 'invoice'], ['link', $invoice['id'], 'receipt']]);

        foreach (array_slice($out, 0, 3) as $i => $call) {
            $this->assertSame('DocumentRenderError', $call['error']['class'] ?? null, "call $i");
            $this->assertSame(1, $call['error']['code'], "call $i is NO_DOCUMENT");
        }
        $this->assertSame('InvalidArgumentException', $out[3]['error']['class'] ?? null);
        $conn = ShopifyEnv::connect(self::$db);
        $this->assertSame('0', ShopifyEnv::one($conn, 'SELECT count(*) AS n FROM dokument_links WHERE ordre_id IN ($1, $2, 999999)', [$invoice['id'], $draft['id']])['n']);
        pg_close($conn);
    }

    public function testAGetNeverPostsMailsOrRestocks(): void
    {
        $doc = $this->posted();
        $token = self::token($this->link($doc['id']));
        $before = $this->ledgerAndStock();
        file_put_contents(self::$mailLog, '');

        $this->assertSame(200, $this->get("?t=$token")['status']);
        $this->assertSame(200, $this->get("?t=$token")['status']);

        $this->assertSame($before, $this->ledgerAndStock());
        $this->assertSame('', file_get_contents(self::$mailLog), 'an invoice set to email is not mailed');
    }

    /**
     * md5 of every row of the tables a post, mail or restock would change. ordrelinjer.samlevare is
     * left out: every formularprint() sets a NULL samlevare to '' on all lines (formfunk.php, #20180302).
     *
     * @return array<string, string>
     */
    private function ledgerAndStock(): array
    {
        $conn = ShopifyEnv::connect(self::$db);
        $out = [];
        foreach (['ordrer', 'ordrelinjer', 'transaktioner', 'kassekladde', 'openpost', 'batch_salg', 'batch_kob', 'lagerstatus', 'varer'] as $table) {
            $out[$table] = (string)ShopifyEnv::one($conn, "SELECT md5(coalesce(string_agg((to_jsonb(t) - 'samlevare')::text, '|' ORDER BY (to_jsonb(t) - 'samlevare')::text), '')) AS h FROM $table t")['h'];
        }
        pg_close($conn);
        return $out;
    }

    public function testOtherMethodsGet405(): void
    {
        $response = $this->get('?t=' . str_repeat('x', 43), 'POST');

        $this->assertSame(405, $response['status']);
        $this->assertSame('GET, HEAD', $response['headers']['allow'] ?? null);
        $this->assertPrivateHeaders($response);
    }

    public function testTheRateLimitAnswers429(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->assertSame(404, $this->get('?t=' . str_repeat('x', 43))['status'], "request $i");
        }

        $response = $this->get('?t=' . self::token($this->link($this->posted()['id'])));

        $this->assertSame(429, $response['status'], 'over the limit even a good link is refused');
        $this->assertSame('60', $response['headers']['retry-after'] ?? null);
        $this->assertPrivateHeaders($response);
    }

    public function testTheRateLimitFailsClosed(): void
    {
        $token = self::token($this->link($this->posted()['id']));
        $dir = ShopifyEnv::repoRoot() . '/temp/' . self::$db;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        @rmdir("$dir/.ht_dokument_rate");
        // a file where the counters go: they can't be kept
        file_put_contents("$dir/.ht_dokument_rate", '');

        $response = $this->get("?t=$token");

        $this->assertSame(503, $response['status']);
        $this->assertStringNotContainsString('%PDF', $response['body']);
    }

    public function testADatabaseFailureGets503WithThePrivateHeaders(): void
    {
        $token = self::token($this->link($this->posted()['id']));
        $conn = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($conn, 'ALTER TABLE dokument_links RENAME TO dokument_links_away');
        try {
            $response = $this->get("?t=$token");
        } finally {
            ShopifyEnv::rows($conn, 'ALTER TABLE dokument_links_away RENAME TO dokument_links');
            pg_close($conn);
        }

        $this->assertSame(503, $response['status']);
        $this->assertSame('Unavailable', $response['body']);
        $this->assertPrivateHeaders($response);
    }

    public function testAnEmptySecretRowIsReplacedOnceAndLinksStayStable(): void
    {
        $doc = $this->posted();
        $conn = ShopifyEnv::connect(self::$db);
        $saved = ShopifyEnv::rows($conn, "SELECT var_value FROM settings WHERE var_grp = 'shopify' AND var_name = 'dokument_link_secret'");
        ShopifyEnv::rows($conn, "DELETE FROM settings WHERE var_grp = 'shopify' AND var_name = 'dokument_link_secret'");
        ShopifyEnv::rows($conn, "INSERT INTO settings (var_grp, var_name, var_value, var_description) VALUES ('shopify', 'dokument_link_secret', '', '')");
        try {
            $out = $this->calls([['link', $doc['id'], 'invoice'], ['link', $doc['id'], 'invoice']]);
            $secrets = ShopifyEnv::one($conn, "SELECT count(*) AS n FROM settings WHERE var_grp = 'shopify' AND var_name = 'dokument_link_secret' AND var_value <> ''")['n'];
        } finally {
            ShopifyEnv::rows($conn, "DELETE FROM settings WHERE var_grp = 'shopify' AND var_name = 'dokument_link_secret'");
            foreach ($saved as $row) {
                ShopifyEnv::rows($conn, "INSERT INTO settings (var_grp, var_name, var_value, var_description) VALUES ('shopify', 'dokument_link_secret', $1, '')", [$row['var_value']]);
            }
            pg_close($conn);
        }

        $this->assertSame($out[0]['result'], $out[1]['result']);
        $this->assertSame('1', $secrets);
    }

    /** @return array{proc: resource, pipes: array<int, resource>, port: int, log: string} */
    private static function startServer(array $env): array
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);
        $log = (string)tempnam(sys_get_temp_dir(), 'sd752-server');
        $cmd = [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', ShopifyEnv::repoRoot(), __DIR__ . '/support/dokument_router.php'];
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
