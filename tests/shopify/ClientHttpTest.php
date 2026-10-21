<?php
// tests/shopify/ClientHttpTest.php
//
// The real curl transport (shopify_http_post / shopify_http_download) against
// a local `php -S` stub: headers and body go out, Retry-After is read, a
// timeout after connecting counts as possibly sent, a refused connection as
// not sent (SD-747).
//
// History:
// 20261021 CL/SZ created (SD-747).

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/shopify/client.php';

final class ClientHttpTest extends TestCase
{
	/** @var resource|null */
	private static $server = null;
	private static string $base = '';

	public static function setUpBeforeClass(): void
	{
		if (!function_exists('curl_init') || !function_exists('proc_open')) {
			self::markTestSkipped('curl and proc_open are needed for the transport test');
		}
		$port = self::freePort();
		$cmd = [PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/support/http_stub.php'];
		self::$server = proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
		self::$base = "http://127.0.0.1:$port";
		for ($i = 0; $i < 50; $i++) {
			if (shopify_http_post(self::$base . '/echo', [], '{}', 0.2, 0.5)['errno'] === 0) {
				return;
			}
			usleep(100000);
		}
		self::markTestSkipped('php -S did not start');
	}

	public static function tearDownAfterClass(): void
	{
		if (self::$server !== null) {
			proc_terminate(self::$server);
			proc_close(self::$server);
		}
	}

	private static function freePort(): int
	{
		$socket = stream_socket_server('tcp://127.0.0.1:0');
		$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
		fclose($socket);
		return $port;
	}

	public function test_post_sends_headers_and_body_and_returns_the_response(): void
	{
		$r = shopify_http_post(self::$base . '/echo', ['Content-Type: application/json', 'X-Shopify-Access-Token: abc'], '{"query":"{ shop { name } }"}', 2, 5);

		$this->assertSame(0, $r['errno']);
		$this->assertSame(200, $r['status']);
		$this->assertTrue($r['sent']);
		$echo = json_decode($r['body'], true);
		$this->assertSame(['POST', 'abc', '{"query":"{ shop { name } }"}'], [$echo['method'], $echo['token'], $echo['body']]);
	}

	public function test_429_retry_after_header_is_read(): void
	{
		$r = shopify_http_post(self::$base . '/throttled', [], '{}', 2, 5);

		$this->assertSame(429, $r['status']);
		$this->assertSame(2.0, $r['retry_after']);
	}

	public function test_timeout_after_connecting_counts_as_possibly_sent(): void
	{
		$r = shopify_http_post(self::$base . '/slow', [], '{}', 2, 0.5);

		$this->assertSame(CURLE_OPERATION_TIMEDOUT, $r['errno']);
		$this->assertTrue($r['sent']);
	}

	public function test_refused_connection_counts_as_not_sent(): void
	{
		$r = shopify_http_post('http://127.0.0.1:' . self::freePort() . '/echo', [], '{}', 2, 5);

		$this->assertSame(CURLE_COULDNT_CONNECT, $r['errno']);
		$this->assertFalse($r['sent']);
	}

	public function test_download_streams_into_the_file_handle(): void
	{
		$fh = fopen('php://temp', 'w+b');

		$r = shopify_http_download(self::$base . '/file', $fh, 2, 5);

		rewind($fh);
		$this->assertSame(['status' => 200, 'errno' => 0, 'error' => ''], $r);
		$this->assertSame("{\"id\":1}\n{\"id\":2}\n", stream_get_contents($fh));
	}
}
