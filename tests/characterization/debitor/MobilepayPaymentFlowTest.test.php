<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The MobilePay till flow of PR #502, with every Vipps call answered by a local stub.
 *
 * 1. debitor/payments/mobilepay/mobilepay.php and mobilepayListen.php stop with "MobilePay er ikke
 *    tilsluttet - tjek loginoplysninger" when the access token call fails or returns no token,
 *    instead of starting or capturing a payment with a null token.
 * 2. After a capture, mobilepayListen.php sends the Vipps reference on to pos_ordre.php as
 *    receipt_id, and afslut()/settlePOS.php store it in pos_betalinger.receipt_id as a string.
 *
 * The pages run under `php -S` (support/mobilepay_page_router.php) so their redirects are real HTTP
 * responses; the Vipps API is tests/fixtures/mobilepay/vipps_stub.php on another `php -S`. afslut()
 * runs in a child process (support/run_afslut_receipt.php) against SQLite. No database server is
 * needed and nothing leaves the machine. Skips when the built-in server cannot be started.
 *
 * History:
 * 20261027 CL/LH Created (PR #502 review: test the payment persistence and the auth failure handling).
 */
final class MobilepayPaymentFlowTest extends TestCase
{
    private const DB = 'mptest';
    private const REFERENCE = 'ordreid-42-0907';
    private const NOT_CONNECTED = 'MobilePay er ikke tilsluttet - tjek loginoplysninger';

    /** @var array<int, resource> */
    private static array $servers = array();
    private static string $stubBase = '';
    private static string $pageBase = '';
    private static string $root = '';
    private static string $stubScenario = '';
    private static string $stubLog = '';
    private static string $pageScenario = '';

    public static function setUpBeforeClass(): void
    {
        if (!function_exists('curl_init') || !function_exists('proc_open')) {
            return;
        }
        $repo = dirname(__DIR__, 3);
        self::$root = sys_get_temp_dir() . '/mp-flow-' . bin2hex(random_bytes(6));
        mkdir(self::$root . '/debitor/payments', 0700, true);
        mkdir(self::$root . '/temp/' . self::DB, 0700, true);
        self::$stubScenario = tempnam(sys_get_temp_dir(), 'mpscn');
        self::$stubLog      = tempnam(sys_get_temp_dir(), 'mplog');
        self::$pageScenario = tempnam(sys_get_temp_dir(), 'mppage');

        $stubPort = self::serve($repo . '/tests/fixtures/mobilepay/vipps_stub.php',
            array('MP_STUB_SCENARIO' => self::$stubScenario, 'MP_STUB_LOG' => self::$stubLog));
        $pagePort = self::serve(__DIR__ . '/support/mobilepay_page_router.php',
            array('MP_PAGE_SCENARIO' => self::$pageScenario));
        if ($stubPort === 0 || $pagePort === 0) {
            self::tearDownAfterClass();
            return;
        }
        self::$stubBase = 'http://127.0.0.1:' . $stubPort;
        self::$pageBase = 'http://127.0.0.1:' . $pagePort;
    }

    /** Starts `php -S` on a free port with $router; returns the port, or 0 when it never answered. */
    private static function serve(string $router, array $env): int
    {
        $probe = @stream_socket_server('tcp://127.0.0.1:0');
        if (!$probe) {
            return 0;
        }
        $port = (int)substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);
        $server = proc_open(
            array(PHP_BINARY, '-S', '127.0.0.1:' . $port, $router),
            array(0 => array('file', '/dev/null', 'r'), 1 => array('file', '/dev/null', 'w'), 2 => array('file', '/dev/null', 'w')),
            $pipes,
            null,
            array_merge(getenv(), $env)
        );
        if (!$server) {
            return 0;
        }
        self::$servers[] = $server;
        for ($i = 0; $i < 50; $i++) {
            $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($fp) {
                fclose($fp);
                return $port;
            }
            usleep(100000);
        }
        return 0;
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$servers as $server) {
            proc_terminate($server);
            proc_close($server);
        }
        self::$servers = array();
        foreach (array(self::$stubScenario, self::$stubLog, self::$pageScenario) as $file) {
            if ($file !== '' && is_file($file)) {
                unlink($file);
            }
        }
        if (self::$root !== '' && is_dir(self::$root)) {
            foreach (glob(self::$root . '/temp/' . self::DB . '/*') ?: array() as $file) {
                unlink($file);
            }
            rmdir(self::$root . '/temp/' . self::DB);
            rmdir(self::$root . '/temp');
            rmdir(self::$root . '/debitor/payments');
            rmdir(self::$root . '/debitor');
            rmdir(self::$root);
        }
    }

    private function requireServers(): void
    {
        if (self::$pageBase === '') {
            self::markTestSkipped('php -S stub servers could not be started');
        }
    }

    private static function ok(array $payload, int $code = 200): array
    {
        return array('code' => $code, 'body' => json_encode($payload));
    }

    /**
     * Runs one page against the stub.
     *
     * @param string      $page     'mobilepay.php' or 'mobilepayListen.php'.
     * @param array       $query    GET parameters.
     * @param array       $vipps    Stub replies by call ('token', 'create', 'capture').
     * @param string|null $apiBase  Base url the page calls; the stub when null.
     * @return array{status: int, location: string, body: string, calls: string[]}
     */
    private function runPage(string $page, array $query, array $vipps, ?string $apiBase = null): array
    {
        file_put_contents(self::$stubScenario, json_encode($vipps));
        file_put_contents(self::$stubLog, '');
        file_put_contents(self::$pageScenario, json_encode(array(
            'apiBase'  => $apiBase ?? self::$stubBase,
            'root'     => self::$root,
            'db'       => self::DB,
            'settings' => array('client_id' => 'id', 'client_secret' => 'sec', 'subscriptionKey' => 'sub', 'MSN' => '123456'),
        )));

        $ch = curl_init(self::$pageBase . '/' . $page . '?' . http_build_query($query));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $raw = (string)curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $location = '';
        if (preg_match('/^Location:\s*(\S+)/mi', substr($raw, 0, $headerSize), $m)) {
            $location = $m[1];
        }
        $calls = array();
        foreach (array_filter(explode("\n", (string)file_get_contents(self::$stubLog))) as $line) {
            $parts   = explode(' ', $line, 3);
            $calls[] = $parts[0] . ' ' . $parts[1];
        }
        return array('status' => $status, 'location' => $location, 'body' => substr($raw, $headerSize), 'calls' => $calls);
    }

    /** The QR scan the webhook leaves for the till before mobilepay.php starts a payment. */
    private function scanQr(): void
    {
        file_put_contents(self::$root . '/temp/' . self::DB . '/data_kasse1.json', json_encode(array('customerToken' => 'ctok')));
    }

    /** The "authorized" callback the webhook leaves for mobilepayListen.php. */
    private function authorizeCallback(): void
    {
        file_put_contents(self::$root . '/temp/' . self::DB . '/data_' . self::REFERENCE . '.json',
            json_encode(array('reference' => self::REFERENCE, 'name' => 'AUTHORIZED')));
    }

    private static function startQuery(): array
    {
        return array('amount' => '125,00', 'id' => '42', 'indbetaling' => '0');
    }

    private static function listenQuery(): array
    {
        return array('ident' => self::REFERENCE, 'amount' => '12500', 'pretty_amount' => '125,00',
            'ordre_id' => '42', 'indbetaling' => '0', 'ref' => '42');
    }

    public static function failedTokenReplies(): array
    {
        return array(
            '401 unauthorized'      => array(array('code' => 401, 'body' => '{"error":"invalid_client"}')),
            '500 server error'      => array(array('code' => 500, 'body' => '')),
            '200 without a token'   => array(self::ok(array('token_type' => 'Bearer'))),
            '200 with an html body' => array(array('code' => 200, 'body' => '<html>maintenance</html>')),
        );
    }

    #[DataProvider('failedTokenReplies')]
    public function testStartingAPaymentStopsWhenTheTokenCallFails(array $tokenReply): void
    {
        $this->requireServers();
        $this->scanQr();
        $r = $this->runPage('mobilepay.php', self::startQuery(), array(
            'token'  => $tokenReply,
            'create' => self::ok(array('reference' => 'x'), 201),
        ));
        self::assertSame(200, $r['status']);
        self::assertStringContainsString(self::NOT_CONNECTED, $r['body']);
        self::assertSame('', $r['location']);
        self::assertSame(array('POST /accesstoken/get'), $r['calls'], 'no payment may be started without a token');
    }

    public function testStartingAPaymentStopsWhenVippsCannotBeReached(): void
    {
        $this->requireServers();
        $this->scanQr();
        $r = $this->runPage('mobilepay.php', self::startQuery(), array(), 'http://127.0.0.1:1');
        self::assertSame(200, $r['status']);
        self::assertStringContainsString(self::NOT_CONNECTED, $r['body']);
        self::assertStringNotContainsString('Curl error', $r['body']);
        self::assertSame(array(), $r['calls']);
    }

    public function testStartingAPaymentWithAValidTokenMovesOnToTheListener(): void
    {
        $this->requireServers();
        $this->scanQr();
        $r = $this->runPage('mobilepay.php', self::startQuery(), array(
            'token'  => self::ok(array('access_token' => 'tok')),
            'create' => self::ok(array('reference' => 'x'), 201),
        ));
        self::assertSame(302, $r['status']);
        self::assertStringStartsWith('mobilepayListen.php?ident=ordreid-42-', $r['location']);
        self::assertSame(array('POST /accesstoken/get', 'POST /epayment/v1/payments'), $r['calls']);
    }

    #[DataProvider('failedTokenReplies')]
    public function testCapturingStopsWhenTheTokenCallFails(array $tokenReply): void
    {
        $this->requireServers();
        $this->authorizeCallback();
        $r = $this->runPage('mobilepayListen.php', self::listenQuery(), array(
            'token'   => $tokenReply,
            'capture' => self::ok(array('state' => 'AUTHORIZED')),
        ));
        self::assertSame(200, $r['status']);
        self::assertStringContainsString(self::NOT_CONNECTED, $r['body']);
        self::assertSame('', $r['location']);
        self::assertSame(array('POST /accesstoken/get'), $r['calls'], 'no capture may be sent without a token');
    }

    public function testCapturedPaymentSendsItsReferenceToTheTillAsReceiptId(): void
    {
        $this->requireServers();
        $this->authorizeCallback();
        $r = $this->runPage('mobilepayListen.php', self::listenQuery(), array(
            'token'   => self::ok(array('access_token' => 'tok')),
            'capture' => self::ok(array('state' => 'AUTHORIZED', 'reference' => self::REFERENCE)),
        ));
        self::assertSame(302, $r['status']);
        self::assertSame(array('POST /accesstoken/get', 'POST /epayment/v1/payments/' . self::REFERENCE . '/capture'), $r['calls']);
        self::assertStringStartsWith('../pos_ordre.php?', $r['location']);
        parse_str((string)parse_url($r['location'], PHP_URL_QUERY), $redirect);
        self::assertSame('42', $redirect['id']);
        self::assertSame('OK', $redirect['godkendt']);
        self::assertSame('mobilepay', $redirect['betaling']);
        self::assertSame('125', $redirect['amount']);
        self::assertSame(self::REFERENCE, $redirect['receipt_id'] ?? null);
    }

    /**
     * Settles order 42 through afslut()/settlePOS.php and returns its pos_betalinger rows.
     *
     * @return array<int, array{ordre_id: int, betalingstype: string, amount: int|float|string, receipt_id: string|null}>
     */
    private function settle(string $receiptId, bool $placeholder): array
    {
        $process = proc_open(
            array(PHP_BINARY, __DIR__ . '/support/run_afslut_receipt.php', $receiptId, $placeholder ? '1' : '0'),
            array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
            $pipes
        );
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        self::assertSame(0, $status, "afslut() child failed:\n" . $out . $err);
        $result = json_decode((string)$out, true);
        self::assertIsArray($result, "afslut() child printed no JSON:\n" . $out . $err);
        return $result['pos_betalinger'];
    }

    public static function receiptIds(): array
    {
        return array(
            'MobilePay reference'   => array(self::REFERENCE),
            'Vibrant charge-intent' => array('ch_3Pq-pi_3Pq'),
            'quote in the id'       => array("o'brien-1"),
        );
    }

    #[DataProvider('receiptIds')]
    public function testSettlingKeepsTheReceiptIdOnThePlaceholderPayment(string $receiptId): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is required for the afslut() child process');
        }
        $rows = $this->settle($receiptId, true);
        self::assertCount(1, $rows);
        self::assertSame('mobilepay', $rows[0]['betalingstype']);
        self::assertEquals(125, $rows[0]['amount']);
        self::assertSame($receiptId, $rows[0]['receipt_id']);
    }

    public function testSettlingWithoutAPlaceholderInsertsThePaymentWithItsReceiptId(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is required for the afslut() child process');
        }
        $rows = $this->settle(self::REFERENCE, false);
        self::assertCount(1, $rows);
        self::assertSame('mobilepay', $rows[0]['betalingstype']);
        self::assertSame(self::REFERENCE, $rows[0]['receipt_id']);
    }
}
