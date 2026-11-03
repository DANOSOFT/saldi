<?php
// tests/shopify/support/WebhookReplayer.php
//
// Delivers webhooks to Saldi the way Shopify does (SD-766): each delivery is signed at run time
// with the shop's webhook secret from the test tenant (base64 HMAC-SHA256 of the raw body) and
// carries Shopify's headers (FakeShopifyBehaviours B13). In process it calls
// shopify_receive_webhook() on the bootstrapped tenant; over HTTP it posts to
// api/shopify/webhook.php, served by `php -S` with webhook_router.php (startServer()). arrange()
// gives the orders a day is replayed in: as recorded, reversed, shuffled by seed, with duplicates
// (a retry: same event id, new webhook id) and delayed (retries that arrive after the rest).
// Deliveries come from FakeShopifyStore's outbox or from a day file; neither holds a signature.
//
// History:
// 20261103 CL/LH SD-766: created.

require_once __DIR__ . '/FakeShopifyBehaviours.php';

final class WebhookReplayer
{
	public const ORDERS = ['recorded', 'reversed', 'shuffled', 'duplicated', 'delayed'];

	/** @var list<array{event_id: string, topic: string, attempt: int, status: int, outcome: string}> */
	public array $answers = [];
	private int $sent = 0;

	/**
	 * @param array{shop_key: string, domain: string, api_version?: string} $shop a shopify_shops row
	 * @param string|null $url the endpoint (http://127.0.0.1:<port>/api/shopify/webhook.php) or null for in process
	 */
	public function __construct(private array $shop, private string $secret, private ?string $url = null)
	{
		if ($secret === '') {
			throw new InvalidArgumentException('WebhookReplayer: the shop has no webhook secret');
		}
	}

	/** A replayer for a shop of the bootstrapped tenant, signing with its stored webhook secret. */
	public static function forShop(array $shop, ?string $url = null): self
	{
		return new self($shop, (string)shopify_secret((string)$shop['shop_key'], SHOPIFY_WEBHOOK_SECRET), $url);
	}

	/**
	 * FakeShopifyStore outbox entries as deliveries.
	 *
	 * @return list<array{topic: string, event_id: string, triggered_at: string, api_version: string, attempt: int, raw: string}>
	 */
	public static function fromOutbox(array $outbox): array
	{
		return array_map(fn(array $d): array => ['topic' => $d['topic'], 'event_id' => $d['event_id'], 'triggered_at' => $d['triggered_at'],
			'api_version' => $d['api_version'], 'attempt' => 1, 'raw' => $d['body']], $outbox);
	}

	/**
	 * The deliveries in one of ORDERS. duplicated sends each delivery picked by the seed (about one
	 * in four, at least one) a second time right after the first; delayed moves the picked ones to
	 * the end as second attempts, as Shopify retries a delivery that got no 200.
	 *
	 * @param list<array> $deliveries
	 * @return list<array>
	 */
	public static function arrange(array $deliveries, string $order, int $seed = 1): array
	{
		$random = new Random\Randomizer(new Random\Engine\Mt19937($seed));
		$picked = static function () use ($deliveries, $random): array {
			$keys = [];
			foreach (array_keys($deliveries) as $i) {
				if ($random->getInt(0, 3) === 0) {
					$keys[$i] = true;
				}
			}
			return $keys ?: ($deliveries ? [$random->getInt(0, count($deliveries) - 1) => true] : []);
		};
		$retry = fn(array $d): array => ['attempt' => ($d['attempt'] ?? 1) + 1] + $d;
		switch ($order) {
			case 'recorded':
				return array_values($deliveries);
			case 'reversed':
				return array_reverse(array_values($deliveries));
			case 'shuffled':
				return $random->shuffleArray(array_values($deliveries));
			case 'duplicated':
				$out = [];
				$again = $picked();
				foreach (array_values($deliveries) as $i => $d) {
					$out[] = $d;
					if (isset($again[$i])) {
						$out[] = $retry($d);
					}
				}
				return $out;
			case 'delayed':
				$late = $picked();
				$out = [];
				$after = [];
				foreach (array_values($deliveries) as $i => $d) {
					if (isset($late[$i])) {
						$after[] = $retry($d);
					} else {
						$out[] = $d;
					}
				}
				return array_merge($out, $after);
		}
		throw new InvalidArgumentException('WebhookReplayer: order must be one of ' . implode(', ', self::ORDERS) . ", not $order");
	}

	/**
	 * Shopify's headers for one delivery attempt, signed now (B13). The webhook id is new on every
	 * attempt; the event id stays.
	 *
	 * @return array<string, string>
	 */
	public function headers(array $delivery): array
	{
		$this->sent++;
		return [
			'X-Shopify-Topic' => (string)$delivery['topic'],
			'X-Shopify-Hmac-Sha256' => base64_encode(hash_hmac('sha256', (string)$delivery['raw'], $this->secret, true)),
			'X-Shopify-Shop-Domain' => (string)$this->shop['domain'],
			'X-Shopify-Webhook-Id' => sprintf('replay-%s-%d-%d', $delivery['event_id'], $delivery['attempt'] ?? 1, $this->sent),
			'X-Shopify-Event-Id' => (string)$delivery['event_id'],
			'X-Shopify-Triggered-At' => (string)$delivery['triggered_at'],
			'X-Shopify-API-Version' => (string)($delivery['api_version'] ?? $this->shop['api_version'] ?? '2026-07'),
		];
	}

	/** @return array{status: int, outcome: string} */
	public function deliver(array $delivery): array
	{
		$headers = $this->headers($delivery);
		if ($this->url === null) {
			$answer = shopify_receive_webhook($this->shop, (string)$delivery['raw'], $headers);
		} else {
			$answer = $this->post((string)$delivery['raw'], $headers);
		}
		$this->answers[] = ['event_id' => (string)$delivery['event_id'], 'topic' => (string)$delivery['topic'], 'attempt' => (int)($delivery['attempt'] ?? 1),
			'status' => (int)$answer['status'], 'outcome' => (string)$answer['outcome']];
		return ['status' => (int)$answer['status'], 'outcome' => (string)$answer['outcome']];
	}

	/**
	 * Delivers every delivery in turn.
	 *
	 * @return array<string, int> outcome => count (stored, duplicate, anomaly, bad_signature, ...)
	 */
	public function replay(array $deliveries): array
	{
		$counts = [];
		foreach ($deliveries as $d) {
			$outcome = $this->deliver($d)['outcome'];
			$counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
		}
		ksort($counts);
		return $counts;
	}

	/** @return array{status: int, outcome: string} */
	private function post(string $raw, array $headers): array
	{
		$lines = ['Content-Type: application/json'];
		foreach ($headers as $name => $value) {
			$lines[] = "$name: $value";
		}
		$ch = curl_init($this->url . (strpos($this->url, '?') === false ? '?' : '&') . 'shop=' . rawurlencode((string)$this->shop['shop_key']));
		curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $raw, CURLOPT_HTTPHEADER => $lines, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
		$body = curl_exec($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$outcome = is_string($body) ? (json_decode($body, true)['status'] ?? 'unreadable') : 'transport: ' . curl_error($ch);
		return ['status' => $status, 'outcome' => (string)$outcome];
	}

	/**
	 * Serves api/shopify/webhook.php with `php -S` for $env (SALDI_SHOPIFY_DB = the test tenant).
	 * Needs the install's includes/connect.php, as the endpoint connects through it.
	 *
	 * @param array<string, string> $env
	 * @return array{proc: resource, url: string, log: string}
	 */
	public static function startServer(array $env): array
	{
		$root = dirname(__DIR__, 3);
		$probe = stream_socket_server('tcp://127.0.0.1:0');
		$port = (int)substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
		fclose($probe);
		$log = (string)tempnam(sys_get_temp_dir(), 'sd766-server');
		$cmd = [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root, __DIR__ . '/webhook_router.php'];
		$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root, ['PATH' => (string)getenv('PATH')] + $env);
		if (!is_resource($proc)) {
			throw new RuntimeException('could not start php -S');
		}
		$server = ['proc' => $proc, 'url' => "http://127.0.0.1:$port/api/shopify/webhook.php", 'log' => $log];
		for ($i = 0; $i < 50; $i++) {
			$socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
			if ($socket) {
				fclose($socket);
				return $server;
			}
			usleep(100000);
		}
		self::stopServer($server);
		throw new RuntimeException("php -S did not start on port $port");
	}

	public static function stopServer(?array $server): void
	{
		if ($server === null) {
			return;
		}
		proc_terminate($server['proc']);
		proc_close($server['proc']);
		@unlink($server['log']);
	}
}
