<?php
// tests/shopify/ClientRetryTest.php
//
// shopify_graphql() retry rules: THROTTLED / 429 / 5xx retried at most 3
// times within the deadline, an uncertain mutation is never resent blind
// (the reconcile callback reads first), failures are surfaced, and every
// attempt is logged with its correlation id but never with the token (SD-747).
//
// History:
// 20261021 CL/SZ created (SD-747).
// 20261023 CDX/LH Cover reconcile data, retry state, programming errors and mutation guard fixes.
// 20261023 CL/SZ Duplicate-order branches, @idempotent(key:) document, request id and redacted bodies in the log.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/shopify/client.php';
require_once __DIR__ . '/support/FakeShopify.php';

final class ClientRetryTest extends TestCase
{
	private const ORDER_CREATE = 'mutation orderCreate($order: OrderCreateOrderInput!) { orderCreate(order: $order) { order { id } userErrors { field message } } }';
	private const SHOP_QUERY = 'query { shop { name } }';

	private static function created(): array
	{
		return FakeShopify::ok(['orderCreate' => ['order' => ['id' => 'gid://shopify/Order/1'], 'userErrors' => []]]);
	}

	private static function server(int $status): array
	{
		return FakeShopify::json(['errors' => 'Internal error'], $status);
	}

	public function test_request_goes_to_the_pinned_version_with_the_token_header_and_object_variables(): void
	{
		$fake = new FakeShopify([FakeShopify::ok(['shop' => ['name' => 'HML']])]);
		$shop = FakeShopify::shop();

		$r = shopify_graphql($shop, 'shop', self::SHOP_QUERY, [], $fake->opts());

		$this->assertTrue($r['ok']);
		$this->assertSame("https://{$shop['domain']}/admin/api/2026-07/graphql.json", $fake->calls[0]['url']);
		$this->assertContains('X-Shopify-Access-Token: ' . FakeShopify::TOKEN, $fake->calls[0]['headers']);
		$this->assertSame(self::SHOP_QUERY, $fake->calls[0]['body']['query']);
		// Empty variables must go out as {} (an object), not [].
		$this->assertStringContainsString('"variables":{}', $fake->calls[0]['raw']);
	}

	public function test_api_version_defaults_to_2026_07_and_no_other_version_is_sent(): void
	{
		$shop = FakeShopify::shop();
		unset($shop['api_version']);

		$this->assertStringContainsString('/admin/api/2026-07/', shopify_graphql_url($shop));
		$this->expectException(InvalidArgumentException::class);
		shopify_graphql_url(['domain' => 'x.myshopify.com', 'api_version' => '2025-01']);
	}

	public function test_throttled_is_retried_after_the_reported_restore_time(): void
	{
		$fake = new FakeShopify([FakeShopify::throttled(0, 50), FakeShopify::throttled(0, 50), FakeShopify::ok(['shop' => ['name' => 'HML']])]);

		$r = shopify_graphql(FakeShopify::shop(), 'shop', self::SHOP_QUERY, [], $fake->opts());

		$this->assertTrue($r['ok']);
		$this->assertSame(3, $r['attempts']);
		// 50 points needed, 0 available, 100 back per second.
		$this->assertEqualsWithDelta(0.5, $fake->sleeps[0], 1e-9);
	}

	public function test_5xx_on_a_read_is_retried_at_most_three_times_then_surfaced(): void
	{
		$fake = new FakeShopify(array_fill(0, 5, self::server(503)));

		$r = shopify_graphql(FakeShopify::shop(), 'shop', self::SHOP_QUERY, [], $fake->opts());

		$this->assertFalse($r['ok']);
		$this->assertSame(4, $r['attempts'], 'one try plus three retries');
		$this->assertCount(4, $fake->calls);
		$this->assertSame('retries_exhausted', $r['error']);
		$this->assertTrue($r['retryable']);
		$this->assertSame(503, $r['status']);
		$this->assertSame([1.0, 2.0, 4.0], $fake->sleeps);
	}

	public function test_throttled_and_429_also_stop_after_three_retries(): void
	{
		foreach ([FakeShopify::throttled(0, 50), FakeShopify::json(['errors' => 'Too many'], 429, 1.0)] as $reply) {
			$fake = new FakeShopify(array_fill(0, 5, $reply));

			$r = shopify_graphql(FakeShopify::shop(), 'shop', self::SHOP_QUERY, [], $fake->opts());

			$this->assertSame(4, $r['attempts']);
			$this->assertSame('retries_exhausted', $r['error']);
			$this->assertTrue($r['retryable']);
		}
	}

	public function test_429_waits_for_retry_after(): void
	{
		$fake = new FakeShopify([FakeShopify::json(['errors' => 'Too many'], 429, 3.0), FakeShopify::ok(['shop' => ['name' => 'HML']])]);

		$r = shopify_graphql(FakeShopify::shop(), 'shop', self::SHOP_QUERY, [], $fake->opts());

		$this->assertTrue($r['ok']);
		$this->assertSame([3.0], $fake->sleeps);
	}

	public function test_retries_stop_at_the_deadline_instead_of_sleeping_past_it(): void
	{
		$fake = new FakeShopify(array_fill(0, 5, self::server(500)));

		// 1 s + 2 s of backoff, each with 1 s left for the next request, fit in 4.5 s;
		// the 4 s wait before the last retry does not.
		$r = shopify_graphql(FakeShopify::shop(), 'shop', self::SHOP_QUERY, [], $fake->opts(['deadline' => $fake->now + 4.5]));

		$this->assertFalse($r['ok']);
		$this->assertSame('deadline', $r['error']);
		$this->assertTrue($r['retryable']);
		$this->assertSame(3, $r['attempts']);
		$this->assertLessThan(1000.0 + 4.5, $fake->now);
	}

	public function test_timeouts_are_10_and_30_seconds_clipped_to_the_deadline(): void
	{
		$fake = new FakeShopify([FakeShopify::ok(['shop' => ['name' => 'a']]), FakeShopify::ok(['shop' => ['name' => 'b']])]);
		$shop = FakeShopify::shop();

		shopify_graphql($shop, 'shop', self::SHOP_QUERY, [], $fake->opts(['deadline' => $fake->now + 300]));
		shopify_graphql($shop, 'shop', self::SHOP_QUERY, [], $fake->opts(['deadline' => $fake->now + 5]));

		$this->assertSame([10.0, 30.0], [$fake->calls[0]['connect'], $fake->calls[0]['timeout']]);
		$this->assertEqualsWithDelta(5.0, $fake->calls[1]['connect'], 1e-9);
		$this->assertEqualsWithDelta(5.0, $fake->calls[1]['timeout'], 1e-9);
	}

	public function test_a_passed_or_almost_passed_deadline_sends_nothing(): void
	{
		foreach ([-1, 0.001, SHOPIFY_MIN_ATTEMPT_BUDGET - 0.01] as $left) {
			$fake = new FakeShopify([]);

			$r = shopify_graphql(FakeShopify::shop(), 'shop', self::SHOP_QUERY, [], $fake->opts(['deadline' => $fake->now + $left]));

			$this->assertSame('deadline', $r['error'], "with $left s left");
			$this->assertSame(0, $r['attempts']);
			$this->assertSame([], $fake->calls);
		}
	}

	public function test_order_create_after_a_timeout_is_not_resent_the_reconcile_callback_runs(): void
	{
		$fake = new FakeShopify([FakeShopify::timeout()]);
		$seen = [];
		$reconcile = function (array $attempt) use (&$seen): array {
			$seen[] = $attempt;
			return ['state' => 'applied', 'data' => ['orderCreate' => ['order' => ['id' => 'gid://shopify/Order/77'], 'userErrors' => []]]];
		};

		$r = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts(['retry_mode' => 'reconcile', 'reconcile' => $reconcile]));

		$this->assertCount(1, $fake->calls, 'orderCreate must not be sent a second time');
		$this->assertCount(1, $seen);
		$this->assertSame('orderCreate', $seen[0]['op']);
		$this->assertTrue($r['ok']);
		$this->assertSame('reconciled', $r['outcome']);
		$this->assertSame('gid://shopify/Order/77', $r['data']['orderCreate']['order']['id']);
	}

	public function test_order_create_after_a_5xx_is_not_resent_either(): void
	{
		$fake = new FakeShopify([self::server(502)]);
		$calls = 0;
		$reconcile = function () use (&$calls): array {
			$calls++;
			return ['state' => 'applied', 'data' => []];
		};

		$r = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts(['retry_mode' => 'reconcile', 'reconcile' => $reconcile]));

		$this->assertCount(1, $fake->calls);
		$this->assertSame(1, $calls);
		$this->assertTrue($r['ok']);
		$this->assertSame('reconciled', $r['outcome']);
		$this->assertSame([], $r['data'], 'an empty array still satisfies the reconcile contract');
	}

	public function test_reconcile_applied_requires_array_data(): void
	{
		$cases = [
			'missing' => ['state' => 'applied'],
			'null' => ['state' => 'applied', 'data' => null],
			'string' => ['state' => 'applied', 'data' => 'found'],
			'integer' => ['state' => 'applied', 'data' => 1],
			'float' => ['state' => 'applied', 'data' => 1.5],
			'boolean' => ['state' => 'applied', 'data' => false],
		];
		foreach ($cases as $name => $found) {
			$fake = new FakeShopify([FakeShopify::timeout()]);
			$r = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts([
				'retry_mode' => 'reconcile', 'reconcile' => fn(): array => $found,
			]));

			$this->assertFalse($r['ok'], $name);
			$this->assertSame('uncertain', $r['outcome'], $name);
			$this->assertSame('uncertain', $r['error'], $name);
			$this->assertTrue($r['retryable'], $name);
			$this->assertNull($r['data'], $name);
			$this->assertSame(1, $r['attempts'], $name);
			$this->assertCount(1, $fake->calls, $name . ': never resend without absent');
		}
	}

	public function test_transport_failures_do_not_keep_fields_from_an_earlier_attempt(): void
	{
		$first = FakeShopify::json([
			'data' => ['orderCreate' => ['order' => null, 'userErrors' => [['message' => 'Invalid order']]]],
			'errors' => [['message' => 'Internal error']],
			'extensions' => ['cost' => ['requestedQueryCost' => 10]],
		], 503);
		$fake = new FakeShopify([$first, FakeShopify::timeout(), FakeShopify::timeout(), FakeShopify::timeout()]);

		$r = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts([
			'retry_mode' => 'reconcile', 'reconcile' => fn(): array => ['state' => 'absent'],
		]));

		$this->assertFalse($r['ok']);
		$this->assertSame(4, $r['attempts']);
		$this->assertCount(4, $fake->calls);
		$this->assertSame('retries_exhausted', $r['error']);
		$this->assertSame(0, $r['status']);
		$this->assertNull($r['data']);
		$this->assertSame([], $r['errors']);
		$this->assertSame([], $r['user_errors']);
		$this->assertNull($r['cost']);
	}

	public function test_reconciled_results_clear_graphql_and_user_errors(): void
	{
		$fake = new FakeShopify([FakeShopify::json([
			'data' => ['orderCreate' => ['order' => null, 'userErrors' => [['message' => 'Invalid order']]]],
			'errors' => [['message' => 'Internal error']],
		], 502)]);
		$data = ['orderCreate' => ['order' => ['id' => 'gid://shopify/Order/77'], 'userErrors' => []]];

		$r = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts([
			'retry_mode' => 'reconcile', 'reconcile' => fn(): array => ['state' => 'applied', 'data' => $data],
		]));

		$this->assertTrue($r['ok']);
		$this->assertSame('reconciled', $r['outcome']);
		$this->assertNull($r['error']);
		$this->assertSame($data, $r['data']);
		$this->assertSame([], $r['errors']);
		$this->assertSame([], $r['user_errors']);
		$this->assertCount(1, $fake->calls);
	}

	public function test_reconcile_absent_allows_one_resend(): void
	{
		$fake = new FakeShopify([FakeShopify::timeout(), self::created()]);

		$r = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts([
			'retry_mode' => 'reconcile',
			'reconcile' => fn(): array => ['state' => 'absent'],
		]));

		$this->assertTrue($r['ok']);
		$this->assertSame('ok', $r['outcome']);
		$this->assertCount(2, $fake->calls);
	}

	public function test_reconcile_that_cannot_tell_ends_uncertain_without_resending(): void
	{
		foreach ([fn() => ['state' => 'unknown'], fn() => null, fn() => throw new RuntimeException('read failed')] as $reconcile) {
			$fake = new FakeShopify([FakeShopify::timeout()]);

			$r = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts(['retry_mode' => 'reconcile', 'reconcile' => $reconcile]));

			$this->assertCount(1, $fake->calls);
			$this->assertFalse($r['ok']);
			$this->assertSame('uncertain', $r['outcome']);
			$this->assertSame('uncertain', $r['error']);
		}
	}

	public function test_a_mutation_that_never_left_is_resent_without_asking(): void
	{
		$fake = new FakeShopify([FakeShopify::refused(), FakeShopify::throttled(), self::created()]);
		$reconcile = fn(): array => throw new LogicException('must not be called for a request that was never sent');

		$r = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts(['retry_mode' => 'reconcile', 'reconcile' => $reconcile]));

		$this->assertTrue($r['ok']);
		$this->assertCount(3, $fake->calls);
	}

	public function test_order_create_answered_200_with_internal_server_error_is_reconciled_not_resent(): void
	{
		$internal = FakeShopify::json(['errors' => [['message' => 'Internal error', 'extensions' => ['code' => 'INTERNAL_SERVER_ERROR']]]]);
		$fake = new FakeShopify([$internal]);
		$calls = 0;
		$reconcile = function () use (&$calls): array {
			$calls++;
			return ['state' => 'applied', 'data' => ['orderCreate' => ['order' => ['id' => 'gid://shopify/Order/8']]]];
		};

		$r = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts(['retry_mode' => 'reconcile', 'reconcile' => $reconcile]));

		$this->assertSame([1, 1, 'reconciled'], [count($fake->calls), $calls, $r['outcome']]);
	}

	public function test_order_create_answered_with_a_garbled_200_is_reconciled_not_resent(): void
	{
		$garbled = ['status' => 200, 'body' => '{"data":{"orderCreate":{"order":{"id":"gid://sho', 'errno' => 0, 'error' => '', 'sent' => true, 'retry_after' => null];
		$fake = new FakeShopify([$garbled]);
		$calls = 0;
		$reconcile = function () use (&$calls): array {
			$calls++;
			return ['state' => 'unknown'];
		};

		$r = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts(['retry_mode' => 'reconcile', 'reconcile' => $reconcile]));

		$this->assertSame([1, 1, 'uncertain'], [count($fake->calls), $calls, $r['outcome']]);
	}

	public function test_repeated_timeouts_with_absent_stop_at_four_sends(): void
	{
		$fake = new FakeShopify(array_fill(0, 6, FakeShopify::timeout()));
		$calls = 0;
		$reconcile = function () use (&$calls): array {
			$calls++;
			return ['state' => 'absent'];
		};

		$r = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts(['retry_mode' => 'reconcile', 'reconcile' => $reconcile]));

		$this->assertCount(4, $fake->calls, 'one send plus three retries, never more');
		$this->assertSame(4, $calls, 'every possibly-sent attempt is read first');
		$this->assertSame(['failed', 'retries_exhausted', true], [$r['outcome'], $r['error'], $r['retryable']]);
	}

	public function test_429_without_retry_after_backs_off_one_second(): void
	{
		$fake = new FakeShopify([FakeShopify::json(['errors' => 'Too many requests'], 429), FakeShopify::ok(['shop' => ['name' => 'HML']])]);

		$r = shopify_graphql(FakeShopify::shop(), 'shop', self::SHOP_QUERY, [], $fake->opts());

		$this->assertTrue($r['ok']);
		$this->assertSame([1.0], $fake->sleeps);
	}

	public function test_attempt_log_carries_the_request_id_and_redacted_bodies(): void
	{
		$reply = FakeShopify::ok(['customerCreate' => ['customer' => ['email' => 'kunde@example.dk'], 'userErrors' => []]]);
		$reply['request_id'] = 'req-123';
		$fake = new FakeShopify([$reply]);
		$secretsSeen = [];
		$redact = function (mixed $v, array $secrets) use (&$secretsSeen): mixed {
			$secretsSeen[] = $secrets;
			array_walk_recursive($v, function (&$x) {
				$x = is_string($x) && str_contains($x, '@') ? '[email]' : $x;
			});
			return $v;
		};
		$reconcile = fn(): array => ['state' => 'absent'];
		$doc = 'mutation c($input: CustomerInput!) { customerCreate(input: $input) { customer { email } userErrors { message } } }';

		shopify_graphql(FakeShopify::shop(), 'customerCreate', $doc, ['input' => ['email' => 'kunde@example.dk']], $fake->opts([
			'retry_mode' => 'reconcile', 'reconcile' => $reconcile, 'redact' => $redact,
		]));

		$entry = $fake->logs[0];
		$this->assertSame(['req-123', 'out'], [$entry['request_id'], $entry['direction']]);
		$request = json_decode(base64_decode($entry['request_b64']), true);
		$response = json_decode(base64_decode($entry['response_b64']), true);
		$this->assertSame('[email]', $request['variables']['input']['email']);
		$this->assertSame('[email]', $response['data']['customerCreate']['customer']['email']);
		$this->assertSame([[FakeShopify::TOKEN], [FakeShopify::TOKEN]], $secretsSeen, 'request and response: the token is handed to the redactor as a secret to strip');
		$this->assertStringNotContainsString('kunde@example.dk', json_encode($fake->logs));
		$this->assertStringNotContainsString(FakeShopify::TOKEN, json_encode($fake->logs));
	}

	public function test_a_failing_redactor_or_logger_never_costs_a_sent_mutations_result(): void
	{
		$created = FakeShopify::ok(['orderCreate' => ['order' => ['id' => 'gid://shopify/Order/3'], 'userErrors' => []]]);
		$reconcile = fn(): array => ['state' => 'absent'];
		$redact = fn(mixed $v, array $secrets): mixed => throw new RuntimeException('redact failed for ' . $secrets[0]);
		$file = tempnam(sys_get_temp_dir(), 'shopify-log');
		$previous = ini_set('error_log', $file);
		try {
			$fake = new FakeShopify([$created]);
			$r1 = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts([
				'retry_mode' => 'reconcile', 'reconcile' => $reconcile, 'redact' => $redact,
			]));
			$entry = $fake->logs[0];

			$fake2 = new FakeShopify([$created]);
			$r2 = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake2->opts([
				'retry_mode' => 'reconcile', 'reconcile' => $reconcile,
				'log' => fn(array $e) => throw new RuntimeException('call log down, token ' . FakeShopify::TOKEN),
			]));
		} finally {
			ini_set('error_log', (string) $previous);
		}
		$fallback = (string) file_get_contents($file);
		unlink($file);

		$this->assertSame(['ok', 1], [$r1['outcome'], count($fake->calls)], 'redactor failure: result kept, nothing resent');
		$this->assertSame([null, null, 'RuntimeException'], [$entry['request_b64'], $entry['response_b64'], $entry['redact_error']]);
		$this->assertSame(['ok', 1], [$r2['outcome'], count($fake2->calls)], 'logger failure: result kept, nothing resent');
		$this->assertStringContainsString('"log_error":"RuntimeException"', $fallback);
		$this->assertStringNotContainsString(FakeShopify::TOKEN, json_encode($fake->logs) . $fallback, 'exception messages are never logged');
	}

	public function test_an_invalid_log_or_redact_option_is_refused_before_anything_is_sent(): void
	{
		foreach (['log' => 'no_such_logger', 'redact' => 'no_such_redactor'] as $seam => $value) {
			$fake = new FakeShopify([FakeShopify::ok(['shop' => ['name' => 'HML']])]);
			try {
				shopify_graphql(FakeShopify::shop(), 'shop', self::SHOP_QUERY, [], $fake->opts([$seam => $value]));
				$this->fail("invalid $seam accepted");
			} catch (InvalidArgumentException $e) {
				$this->assertStringContainsString($seam, $e->getMessage());
			}
			$this->assertSame([], $fake->calls, "$seam: nothing sent");
		}
	}

	public function test_without_a_redactor_no_bodies_are_logged(): void
	{
		$fake = new FakeShopify([FakeShopify::ok(['shop' => ['name' => 'HML']])]);

		shopify_graphql(FakeShopify::shop(), 'shop', self::SHOP_QUERY, [], $fake->opts(['redact' => null]));

		$this->assertNull($fake->logs[0]['request_b64']);
		$this->assertNull($fake->logs[0]['response_b64']);
	}

	public function test_the_default_log_line_never_carries_bodies(): void
	{
		$file = tempnam(sys_get_temp_dir(), 'shopify-log');
		$previous = ini_set('error_log', $file);
		try {
			shopify_client_log(['op' => 'shop', 'request_id' => 'req-9', 'request_b64' => base64_encode('{"secret":1}'), 'response_b64' => 'eA=='], ['tok']);
		} finally {
			ini_set('error_log', (string) $previous);
		}
		$line = (string) file_get_contents($file);
		unlink($file);

		$this->assertStringContainsString('"request_id":"req-9"', $line);
		$this->assertStringNotContainsString('request_b64', $line);
		$this->assertStringNotContainsString('response_b64', $line);
	}

	public function test_order_create_with_errors_next_to_its_order_data_is_reconciled_not_failed(): void
	{
		// The order was created; only a protected customer field in the selection was refused.
		$partial = FakeShopify::json([
			'data' => ['orderCreate' => ['order' => ['id' => 'gid://shopify/Order/5', 'customer' => null], 'userErrors' => []]],
			'errors' => [['message' => 'Access denied for customer field', 'extensions' => ['code' => 'ACCESS_DENIED']]],
		]);
		$fake = new FakeShopify([$partial]);
		$seen = 0;
		$reconcile = function () use (&$seen): array {
			$seen++;
			return ['state' => 'applied', 'data' => ['orderCreate' => ['order' => ['id' => 'gid://shopify/Order/5']]]];
		};

		$r = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts(['retry_mode' => 'reconcile', 'reconcile' => $reconcile]));

		$this->assertSame(1, $seen);
		$this->assertCount(1, $fake->calls, 'not sent again');
		$this->assertSame('reconciled', $r['outcome']);
		$this->assertSame('gid://shopify/Order/5', $r['data']['orderCreate']['order']['id']);
	}

	public function test_errors_with_only_null_payload_stay_fatal(): void
	{
		foreach ([null, ['orderCreate']] as $path) {
			$denied = FakeShopify::json(['data' => ['orderCreate' => null], 'errors' => [['message' => 'Access denied', 'path' => $path, 'extensions' => ['code' => 'ACCESS_DENIED']]]]);
			$fake = new FakeShopify([$denied, $denied]);
			$reconcile = fn(): array => throw new LogicException('nothing ran, nothing to reconcile');

			$r1 = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts(['retry_mode' => 'reconcile', 'reconcile' => $reconcile]));
			$r2 = shopify_graphql(FakeShopify::shop(), 'tagsAdd', 'mutation { tagsAdd(id: "x", tags: []) { userErrors { message } } }', [], $fake->opts(['retry_mode' => 'idempotent']));

			$this->assertSame(['failed', 'graphql_errors', 1], [$r1['outcome'], $r1['error'], $r1['attempts']]);
			$this->assertSame(['failed', 'graphql_errors', 1], [$r2['outcome'], $r2['error'], $r2['attempts']]);
			$this->assertCount(2, $fake->calls);
		}
	}

	public function test_errors_below_a_null_mutation_root_reconcile_without_resending(): void
	{
		foreach (['orderCreate', 'made'] as $root) {
			$path = [$root, 'userErrors'];
			$fake = new FakeShopify([FakeShopify::json([
				'data' => [$root => null],
				'errors' => [['message' => 'Resolver failed', 'path' => $path]],
			])]);
			$seen = 0;
			$reconcile = function () use (&$seen): array {
				$seen++;
				return ['state' => 'unknown'];
			};
			$doc = 'mutation { ' . $root . ': orderCreate(order: {}) { order { id } userErrors { message } } }';

			$r = shopify_graphql(FakeShopify::shop(), 'orderCreate', $doc, [], $fake->opts(['retry_mode' => 'reconcile', 'reconcile' => $reconcile]));

			$this->assertFalse($r['ok']);
			$this->assertSame('uncertain', $r['outcome']);
			$this->assertSame('uncertain', $r['error']);
			$this->assertTrue($r['retryable']);
			$this->assertSame($path, $r['errors'][0]['path']);
			$this->assertSame(1, $seen);
			$this->assertCount(1, $fake->calls);
		}
	}

	public function test_errors_below_a_null_root_stay_fatal_in_idempotent_mode(): void
	{
		$fake = new FakeShopify([FakeShopify::json([
			'data' => ['tagsAdd' => null],
			'errors' => [['message' => 'Resolver failed', 'path' => ['tagsAdd', 'userErrors']]],
		])]);

		$r = shopify_graphql(FakeShopify::shop(), 'tagsAdd', 'mutation { tagsAdd(id: "x", tags: []) { userErrors { message } } }', [], $fake->opts(['retry_mode' => 'idempotent']));

		$this->assertSame(['failed', 'graphql_errors', 1], [$r['outcome'], $r['error'], $r['attempts']]);
		$this->assertFalse($r['retryable']);
		$this->assertCount(1, $fake->calls);
	}

	/** A valid 2026-07 absolute stock set: every attempt carries the same @idempotent key. */
	public const INVENTORY_SET = 'mutation set($input: InventorySetQuantitiesInput!, $key: String!) {
		inventorySetQuantities(input: $input) @idempotent(key: $key) { userErrors { field message } }
	}';

	public static function inventorySetVars(): array
	{
		return [
			'key' => 'hml:stock:42:1',
			'input' => ['name' => 'available', 'reason' => 'correction', 'quantities' => [
				['inventoryItemId' => 'gid://shopify/InventoryItem/1', 'locationId' => 'gid://shopify/Location/1', 'quantity' => 3, 'changeFromQuantity' => null],
			]],
		];
	}

	public function test_idempotent_mutation_is_resent_after_a_timeout_with_the_same_key(): void
	{
		$fake = new FakeShopify([FakeShopify::timeout(), FakeShopify::ok(['inventorySetQuantities' => ['userErrors' => []]])]);

		$r = shopify_graphql(FakeShopify::shop(), 'inventorySetQuantities', self::INVENTORY_SET, self::inventorySetVars(), $fake->opts(['retry_mode' => 'idempotent']));

		$this->assertTrue($r['ok']);
		$this->assertCount(2, $fake->calls);
		$this->assertSame($fake->calls[0]['raw'], $fake->calls[1]['raw'], 'the resend is byte for byte the same request, key included');
		$this->assertSame('hml:stock:42:1', $fake->calls[1]['body']['variables']['key']);
	}

	public function test_user_errors_and_auth_errors_are_not_retried(): void
	{
		$userErrors = FakeShopify::ok(['orderCreate' => ['order' => null, 'userErrors' => [['field' => ['order'], 'message' => 'Invalid']]]]);
		$fake = new FakeShopify([$userErrors, FakeShopify::json(['errors' => '[API] Invalid API key'], 401)]);
		$shop = FakeShopify::shop();
		$opts = $fake->opts(['retry_mode' => 'reconcile', 'reconcile' => fn() => ['state' => 'absent']]);

		$r1 = shopify_graphql($shop, 'orderCreate', self::ORDER_CREATE, ['order' => []], $opts);
		$r2 = shopify_graphql($shop, 'shop', self::SHOP_QUERY, [], $fake->opts());

		$this->assertSame(['user_errors', 1, false], [$r1['error'], $r1['attempts'], $r1['retryable']]);
		$this->assertSame('Invalid', $r1['user_errors'][0]['message']);
		$this->assertSame(['http_401', 1, false], [$r2['error'], $r2['attempts'], $r2['retryable']]);
	}

	public function test_uncertain_mutations_refuse_a_blind_retry_mode(): void
	{
		$fake = new FakeShopify([]);
		foreach (SHOPIFY_RECONCILE_OPS as $op) {
			try {
				shopify_graphql(FakeShopify::shop(), $op, 'mutation { ' . $op . ' { userErrors { message } } }', [], $fake->opts(['retry_mode' => 'idempotent']));
				$this->fail("$op accepted retry_mode idempotent");
			} catch (InvalidArgumentException $e) {
				$this->assertStringContainsString('reconcile', $e->getMessage());
			}
		}
		$this->assertSame([], $fake->calls);
	}

	public function test_the_guard_reads_the_document_not_the_op_label(): void
	{
		$fake = new FakeShopify([]);
		$blind = $fake->opts(['retry_mode' => 'idempotent']);
		$cases = [
			'other label' => ['createOrder', self::ORDER_CREATE, ['order' => []]],
			'aliased field' => ['import', 'mutation { made: customerCreate(input: {}) { customer { id } userErrors { message } } }', []],
			'note in variables' => ['addNote', 'mutation u($input: OrderInput!) { orderUpdate(input: $input) { userErrors { message } } }', ['input' => ['id' => 'gid://shopify/Order/1', 'note' => 'x']]],
			'note inline' => ['addNote', 'mutation { orderUpdate(input: {id: "gid://shopify/Order/1", note: "x"}) { userErrors { message } } }', []],
			'note comma' => ['addNote', 'mutation { orderUpdate(input: {id: "gid://shopify/Order/1", note,: "x"}) { userErrors { message } } }', []],
			'note BOM' => ['addNote', "mutation { orderUpdate(input: {id: \"gid://shopify/Order/1\", note\u{FEFF}: \"x\"}) { userErrors { message } } }", []],
			'note mixed separators' => ['addNote', "mutation { orderUpdate(input: {id: \"gid://shopify/Order/1\", note ,\u{FEFF}\n,: \"x\"}) { userErrors { message } } }", []],
			'note next to invalid UTF-8' => ['addNote', "mutation { orderUpdate(input: {id: \"gid://shopify/Order/1\", note: \"x\"}) { userErrors { message \xFF } } }", []],
			'fragment first' => ['createOrder', "fragment f on Order { id }\nmutation { orderCreate(order: {}) { order { ...f } userErrors { message } } }", []],
			'hash in a string' => ['tag', 'mutation { tagsAdd(id: "#", tags: []) { userErrors { message } } b: orderCreate(order: {}) { userErrors { message } } }', []],
			'leading comma' => ['import', ', mutation { orderCreate(order: {}) { order { id } } }', []],
			'leading BOM' => ['import', "\u{FEFF}mutation { fulfillmentOrderMove(id: \"x\", newLocationId: \"y\") { userErrors { message } } }", []],
			'named, with variables' => ['x', 'mutation  ,draft($input: DraftOrderInput!) { draftOrderCreate(input: $input) { userErrors { message } } }', []],
			'comment ended by a lone CR' => ['import', "# header\rmutation { orderCreate(order: {}) { order { id } } }", []],
			'10,000-character string' => ['importOrder', 'mutation { orderCreate(order: {note: "' . str_repeat('x', 10000) . '"}) { order { id } } }', []],
		];
		foreach ($cases as $name => [$op, $doc, $vars]) {
			try {
				shopify_graphql(FakeShopify::shop(), $op, $doc, $vars, $blind);
				$this->fail("$name: blind retry mode accepted");
			} catch (InvalidArgumentException $e) {
				$this->assertStringContainsString('reconcile', $e->getMessage(), $name);
			}
			try {
				shopify_graphql(FakeShopify::shop(), $op, $doc, $vars, $fake->opts());
				$this->fail("$name: read mode accepted for a mutation");
			} catch (InvalidArgumentException $e) {
				$this->assertStringContainsString('reconcile', $e->getMessage(), $name);
			}
		}
		$this->assertSame([], $fake->calls);
	}

	public function test_mutation_detection_follows_graphql_not_the_first_word(): void
	{
		$mutations = [
			'mutation { tagsAdd(id: "x", tags: []) { userErrors { message } } }',
			', mutation { tagsAdd(id: "x", tags: []) { userErrors { message } } }',
			"\u{FEFF}mutation { tagsAdd(id: \"x\", tags: []) { userErrors { message } } }",
			"# leading comment\nmutation m { tagsAdd(id: \"x\", tags: []) { userErrors { message } } }",
			"fragment f on Order { id }\nmutation { tagsAdd(id: \"x\", tags: []) { node { ...f } } }",
			"# header\rmutation { tagsAdd(id: \"x\", tags: []) { userErrors { message } } }",
			'mutation { tagsAdd(id: "' . str_repeat('y', 1000000) . '", tags: []) { userErrors { message } } }',
		];
		$queries = [
			'{ shop { name } }',
			'query { orders(first: 1, query: "} mutation x") { nodes { id } } }',
			'query q($mutation: String) { orders(first: 1, query: $mutation) { nodes { id } } }',
			"query { shop { name } } # mutation",
			'query { orders(first: 1) { nodes { id mutation: name } } }',
		];
		foreach ($mutations as $doc) {
			$this->assertTrue(shopify_graphql_is_mutation($doc), $doc);
		}
		foreach ($queries as $doc) {
			$this->assertFalse(shopify_graphql_is_mutation($doc), $doc);
		}
	}

	public function test_a_document_that_cannot_be_checked_is_not_sent(): void
	{
		$fake = new FakeShopify([FakeShopify::ok(['orders' => ['nodes' => []]])]);
		$jit = ini_get('pcre.jit');
		$limit = ini_get('pcre.backtrack_limit');
		ini_set('pcre.jit', '0');
		ini_set('pcre.backtrack_limit', '5');
		try {
			shopify_graphql(FakeShopify::shop(), 'orders', 'query { orders(query: "' . str_repeat('a\\b', 40) . '") { nodes { id } } }', [], $fake->opts());
			$this->fail('a document the guard could not read was accepted');
		} catch (RuntimeException $e) {
			$this->assertStringContainsString('not sent', $e->getMessage());
		} finally {
			ini_set('pcre.jit', (string) $jit);
			ini_set('pcre.backtrack_limit', (string) $limit);
		}
		$this->assertSame([], $fake->calls);
	}

	public function test_a_mutation_word_inside_a_string_does_not_make_a_query_a_mutation(): void
	{
		$fake = new FakeShopify([FakeShopify::ok(['orders' => ['nodes' => []]])]);

		$r = shopify_graphql(FakeShopify::shop(), 'orders', 'query { orders(first: 1, query: "} mutation orderCreate") { nodes { id } } }', [], $fake->opts());

		$this->assertTrue($r['ok']);
	}

	public function test_reconcile_decisions_and_exceptions_are_logged(): void
	{
		$fake = new FakeShopify([FakeShopify::timeout()]);
		$reconcile = fn(): array => throw new RuntimeException('lookup by sourceIdentifier failed');

		$r = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts([
			'retry_mode' => 'reconcile', 'reconcile' => $reconcile, 'correlation' => ['job_id' => 7],
		]));

		$this->assertSame('uncertain', $r['outcome']);
		$entry = $fake->logs[1];
		$this->assertSame(['reconcile', 'unknown', ['job_id' => 7]], [$entry['event'], $entry['state'], $entry['correlation']]);
		$this->assertSame('RuntimeException: lookup by sourceIdentifier failed', $entry['exception']);
	}

	public function test_a_reconcile_exception_carrying_the_token_is_redacted_in_the_log(): void
	{
		$fake = new FakeShopify([FakeShopify::timeout()]);
		$reconcile = fn(): array => throw new RuntimeException('read failed, header X-Shopify-Access-Token: ' . FakeShopify::TOKEN);

		shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts(['retry_mode' => 'reconcile', 'reconcile' => $reconcile]));

		$this->assertStringNotContainsString(FakeShopify::TOKEN, json_encode($fake->logs));
		$this->assertStringContainsString('[redacted]', $fake->logs[1]['exception']);
	}

	public function test_a_bug_in_the_reconcile_callback_is_not_hidden_as_uncertain(): void
	{
		$fake = new FakeShopify([FakeShopify::timeout()]);
		$reconcile = fn(): array => strlen([]);

		$this->expectException(TypeError::class);
		shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts(['retry_mode' => 'reconcile', 'reconcile' => $reconcile]));
	}

	public function test_a_logic_exception_in_the_reconcile_callback_is_rethrown(): void
	{
		foreach ([new LogicException('bug in callback'), new InvalidArgumentException('wrong retry_mode')] as $exception) {
			$fake = new FakeShopify([FakeShopify::timeout()]);
			$reconcile = fn(): array => throw $exception;

			try {
				shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts(['retry_mode' => 'reconcile', 'reconcile' => $reconcile]));
				$this->fail('programming error was swallowed as uncertain');
			} catch (LogicException $e) {
				$this->assertSame($exception, $e);
			}
			$this->assertCount(1, $fake->calls);
		}
	}

	public function test_an_order_update_without_a_note_may_be_idempotent(): void
	{
		$fake = new FakeShopify([FakeShopify::ok(['orderUpdate' => ['userErrors' => []]])]);
		$doc = 'mutation u($input: OrderInput!) { orderUpdate(input: $input) { userErrors { message } } }';

		$r = shopify_graphql(FakeShopify::shop(), 'orderUpdate', $doc, ['input' => ['id' => 'gid://shopify/Order/1', 'tags' => ['a']]], $fake->opts(['retry_mode' => 'idempotent']));

		$this->assertTrue($r['ok']);
	}

	public function test_no_reconcile_read_starts_after_the_deadline(): void
	{
		$fake = new FakeShopify([FakeShopify::timeout()]);
		$fake->requestSeconds = 5.0;
		$called = false;
		$reconcile = function () use (&$called): array {
			$called = true;
			return ['state' => 'absent'];
		};

		$r = shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts([
			'deadline' => $fake->now + 4, 'retry_mode' => 'reconcile', 'reconcile' => $reconcile,
		]));

		$this->assertFalse($called);
		$this->assertSame('uncertain', $r['outcome']);
		$this->assertCount(1, $fake->calls);
	}

	public function test_the_reconcile_callback_gets_the_deadline(): void
	{
		$fake = new FakeShopify([FakeShopify::timeout()]);
		$deadline = $fake->now + 60;
		$seen = null;
		$reconcile = function (array $attempt) use (&$seen): array {
			$seen = $attempt['deadline'];
			return ['state' => 'applied', 'data' => []];
		};

		shopify_graphql(FakeShopify::shop(), 'orderCreate', self::ORDER_CREATE, ['order' => []], $fake->opts(['deadline' => $deadline, 'retry_mode' => 'reconcile', 'reconcile' => $reconcile]));

		$this->assertSame($deadline, $seen);
	}

	public function test_a_mutation_cannot_use_read_mode_and_reconcile_needs_a_callback(): void
	{
		$fake = new FakeShopify([]);

		$this->expectException(InvalidArgumentException::class);
		try {
			shopify_graphql(FakeShopify::shop(), 'tagsAdd', "# note\n  mutation { tagsAdd(id: \"x\", tags: []) { userErrors { message } } }", [], $fake->opts());
		} catch (InvalidArgumentException) {
			shopify_graphql(FakeShopify::shop(), 'orderUpdate', 'mutation { orderUpdate(input: {}) { userErrors { message } } }', [], $fake->opts(['retry_mode' => 'reconcile']));
		}
	}

	public function test_every_attempt_is_logged_with_the_correlation_and_never_the_token(): void
	{
		$fake = new FakeShopify([self::server(503), FakeShopify::ok(['shop' => ['name' => 'HML']])]);
		$correlation = ['job_id' => 42, 'order_gid' => 'gid://shopify/Order/9'];

		shopify_graphql(FakeShopify::shop(), 'shop', self::SHOP_QUERY, [], $fake->opts(['correlation' => $correlation]));

		$this->assertCount(2, $fake->logs);
		$this->assertSame([1, 2], array_column($fake->logs, 'attempt'));
		foreach ($fake->logs as $entry) {
			$this->assertSame($correlation, $entry['correlation']);
			$this->assertSame('shop', $entry['op']);
		}
		$this->assertSame(['http_503', null], array_column($fake->logs, 'error'));
		$this->assertStringNotContainsString(FakeShopify::TOKEN, json_encode($fake->logs));
	}

	public function test_missing_token_and_foreign_domain_fail_before_any_request(): void
	{
		$fake = new FakeShopify([]);
		$opts = $fake->opts();
		unset($opts['access_token']);
		try {
			shopify_graphql(FakeShopify::shop(), 'shop', self::SHOP_QUERY, [], $opts);
			$this->fail('no token accepted');
		} catch (RuntimeException $e) {
			$this->assertStringContainsString('No Shopify access token', $e->getMessage());
		}

		$this->expectException(InvalidArgumentException::class);
		shopify_graphql(['shop_key' => 'evil', 'domain' => 'attacker.example.com'], 'shop', self::SHOP_QUERY, [], $fake->opts());
	}

	public function test_pacing_waits_before_sending_when_the_last_response_left_the_bucket_short(): void
	{
		// The first call reports 5 points left; the same op costs 10, at 100 points/s.
		$fake = new FakeShopify([FakeShopify::ok(['shop' => ['name' => 'a']], 5), FakeShopify::ok(['shop' => ['name' => 'b']])]);
		$fake->requestSeconds = 0.0;
		$shop = FakeShopify::shop();

		shopify_graphql($shop, 'shop', self::SHOP_QUERY, [], $fake->opts());
		shopify_graphql($shop, 'shop', self::SHOP_QUERY, [], $fake->opts());

		$this->assertCount(1, $fake->sleeps);
		$this->assertEqualsWithDelta(0.05, $fake->sleeps[0], 1e-9);
	}
}
