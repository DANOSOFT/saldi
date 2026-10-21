<?php
// tests/shopify/ClientParseTest.php
//
// shopify_parse_graphql(): top-level errors and every nested userErrors list
// count as failure (SD-747).
//
// History:
// 20261021 CL/SZ created (SD-747).

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/shopify/client.php';

final class ClientParseTest extends TestCase
{
	public function test_clean_response_is_ok_and_keeps_cost(): void
	{
		$r = shopify_parse_graphql(200, json_encode([
			'data' => ['shop' => ['name' => 'HML']],
			'extensions' => ['cost' => ['requestedQueryCost' => 1, 'throttleStatus' => ['currentlyAvailable' => 1999]]],
		]));

		$this->assertTrue($r['ok']);
		$this->assertNull($r['error']);
		$this->assertSame('HML', $r['data']['shop']['name']);
		$this->assertSame(1999, $r['cost']['throttleStatus']['currentlyAvailable']);
	}

	public function test_top_level_errors_are_failure_even_with_data(): void
	{
		$r = shopify_parse_graphql(200, json_encode([
			'data' => ['shop' => null],
			'errors' => [['message' => "Field 'nme' doesn't exist", 'extensions' => ['code' => 'undefinedField']]],
		]));

		$this->assertFalse($r['ok']);
		$this->assertSame('graphql_errors', $r['error']);
		$this->assertSame('undefinedField', $r['errors'][0]['code']);
		$this->assertFalse($r['throttled']);
	}

	public function test_errors_as_a_plain_string_are_failure(): void
	{
		$r = shopify_parse_graphql(401, json_encode(['errors' => '[API] Invalid API key or access token']));

		$this->assertFalse($r['ok']);
		$this->assertSame('http_401', $r['error']);
		$this->assertSame('[API] Invalid API key or access token', $r['errors'][0]['message']);
	}

	public function test_throttled_is_flagged(): void
	{
		$r = shopify_parse_graphql(200, json_encode(['errors' => [['message' => 'Throttled', 'extensions' => ['code' => 'THROTTLED']]]]));

		$this->assertFalse($r['ok']);
		$this->assertTrue($r['throttled']);
		$this->assertSame('throttled', $r['error']);
	}

	public function test_nested_user_errors_are_failure_with_their_path(): void
	{
		$r = shopify_parse_graphql(200, json_encode(['data' => [
			'orderCreate' => ['order' => null, 'userErrors' => [['field' => ['order', 'lineItems'], 'message' => 'Variant not found', 'code' => 'INVALID']]],
		]]));

		$this->assertFalse($r['ok']);
		$this->assertSame('user_errors', $r['error']);
		$this->assertSame([['path' => 'orderCreate', 'field' => ['order', 'lineItems'], 'message' => 'Variant not found', 'code' => 'INVALID']], $r['user_errors']);
	}

	public function test_user_errors_deep_in_aliased_payloads_and_variants_are_found(): void
	{
		$r = shopify_parse_graphql(200, json_encode(['data' => [
			'first' => ['userErrors' => []],
			'second' => ['product' => ['id' => 'gid://shopify/Product/1'], 'mediaUserErrors' => [['field' => null, 'message' => 'Bad image']]],
		]]));

		$this->assertFalse($r['ok']);
		$this->assertCount(1, $r['user_errors']);
		$this->assertSame('second', $r['user_errors'][0]['path']);
		$this->assertSame('Bad image', $r['user_errors'][0]['message']);
	}

	public function test_empty_user_errors_are_not_failure(): void
	{
		$r = shopify_parse_graphql(200, json_encode(['data' => ['orderCreate' => ['order' => ['id' => 'gid://shopify/Order/1'], 'userErrors' => []]]]));

		$this->assertTrue($r['ok']);
		$this->assertSame([], $r['user_errors']);
	}

	public function test_non_json_body_is_failure(): void
	{
		$this->assertSame('invalid_json', shopify_parse_graphql(200, '<html>oops')['error']);
		$this->assertSame('http_502', shopify_parse_graphql(502, '<html>Bad gateway')['error']);
		$this->assertFalse(shopify_parse_graphql(200, '')['ok']);
	}

	public function test_odd_shapes_fail_cleanly_without_php_warnings(): void
	{
		set_error_handler(function (int $no, string $msg): never {
			throw new ErrorException($msg, 0, $no);
		});
		try {
			$r1 = shopify_parse_graphql(200, json_encode(['errors' => [['message' => ['nested'], 'extensions' => ['code' => ['x']]], 'plain', null]]));
			$r2 = shopify_parse_graphql(200, json_encode(['data' => ['x' => ['userErrors' => ['just text', ['message' => ['a' => 1], 'code' => 5]]]]]));
			$r3 = shopify_parse_graphql(200, json_encode(['errors' => [['message' => 'Throttled', 'extensions' => ['code' => 'THROTTLED']]],
				'extensions' => ['cost' => ['requestedQueryCost' => 'many', 'throttleStatus' => 'low']]]));
			$delay = shopify_retry_delay(['status' => 200, 'retry_after' => null], $r3, 1);
		} finally {
			restore_error_handler();
		}

		$this->assertSame(['["nested"]', 'plain', ''], array_column($r1['errors'], 'message'));
		$this->assertSame([null, null, null], array_column($r1['errors'], 'code'));
		$this->assertSame(['just text', '{"a":1}'], array_column($r2['user_errors'], 'message'));
		$this->assertSame('user_errors', $r2['error']);
		$this->assertTrue($r3['throttled']);
		$this->assertGreaterThan(0, $delay);
	}

	public function test_http_error_status_is_failure_even_with_data(): void
	{
		$r = shopify_parse_graphql(500, json_encode(['data' => ['shop' => ['name' => 'HML']]]));

		$this->assertFalse($r['ok']);
		$this->assertSame('http_500', $r['error']);
	}
}
