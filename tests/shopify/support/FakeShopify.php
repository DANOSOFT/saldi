<?php
// tests/shopify/support/FakeShopify.php
//
// Scripted stand-in for Shopify behind shopify_graphql()'s test seams: returns
// queued responses in order, records every request, log line and sleep, and
// runs a fake clock that only moves when the client sleeps or a request is
// made. No network, no real waiting.
//
// History:
// 20261021 CL/SZ created (SD-747).

final class FakeShopify
{
	/** A placeholder, not a credential: the fake never checks it. */
	public const TOKEN = 'test-token-not-a-secret';

	public float $now = 1000.0;
	/** Seconds each request takes on the fake clock. */
	public float $requestSeconds = 0.1;
	/** @var list<array{url: string, headers: list<string>, raw: string, body: array, connect: float, timeout: float}> */
	public array $calls = [];
	/** @var list<array> */
	public array $logs = [];
	/** @var list<float> */
	public array $sleeps = [];
	/** @var list<array|callable> */
	private array $script;

	public function __construct(array $script)
	{
		$this->script = $script;
	}

	/** A shop row with a key no other test uses, so pacing state never leaks between tests. */
	public static function shop(): array
	{
		static $n = 0;
		$n++;
		return ['shop_key' => "test$n", 'domain' => "test-$n.myshopify.com", 'api_version' => '2026-07'];
	}

	public static function json(array $payload, int $status = 200, ?float $retryAfter = null): array
	{
		return ['status' => $status, 'body' => json_encode($payload), 'errno' => 0, 'error' => '', 'sent' => true, 'retry_after' => $retryAfter];
	}

	/** Timed out after the request was sent: Shopify may have executed it. */
	public static function timeout(): array
	{
		return ['status' => 0, 'body' => '', 'errno' => 28, 'error' => 'Operation timed out', 'sent' => true, 'retry_after' => null];
	}

	/** Connection refused: the request never left. */
	public static function refused(): array
	{
		return ['status' => 0, 'body' => '', 'errno' => 7, 'error' => 'Failed to connect', 'sent' => false, 'retry_after' => null];
	}

	public static function throttled(float $available = 0, float $requested = 50): array
	{
		return self::json([
			'errors' => [['message' => 'Throttled', 'extensions' => ['code' => 'THROTTLED']]],
			'extensions' => ['cost' => ['requestedQueryCost' => $requested, 'actualQueryCost' => null,
				'throttleStatus' => ['maximumAvailable' => 2000.0, 'currentlyAvailable' => $available, 'restoreRate' => 100.0]]],
		]);
	}

	public static function ok(array $data, float $available = 1990): array
	{
		return self::json([
			'data' => $data,
			'extensions' => ['cost' => ['requestedQueryCost' => 10, 'actualQueryCost' => 10,
				'throttleStatus' => ['maximumAvailable' => 2000.0, 'currentlyAvailable' => $available, 'restoreRate' => 100.0]]],
		]);
	}

	/** shopify_graphql() opts wired to this fake; $extra wins. */
	public function opts(array $extra = []): array
	{
		return $extra + [
			'access_token' => self::TOKEN,
			'deadline' => $this->now + 300,
			'transport' => fn(string $url, array $headers, string $body, float $connect, float $timeout): array
				=> $this->send($url, $headers, $body, $connect, $timeout),
			'clock' => fn(): float => $this->now,
			'sleep' => function (float $s): void {
				$this->sleeps[] = $s;
				$this->now += $s;
			},
			'log' => function (array $entry): void {
				$this->logs[] = $entry;
			},
		];
	}

	public function send(string $url, array $headers, string $body, float $connect, float $timeout): array
	{
		$this->calls[] = ['url' => $url, 'headers' => $headers, 'raw' => $body, 'body' => json_decode($body, true), 'connect' => $connect, 'timeout' => $timeout];
		if (!$this->script) {
			throw new LogicException('FakeShopify: no scripted response left for call ' . count($this->calls));
		}
		$next = array_shift($this->script);
		$this->now += $this->requestSeconds;
		return is_callable($next) ? $next($this) : $next;
	}
}
