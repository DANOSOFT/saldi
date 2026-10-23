<?php
// tests/shopify/ClientBulkTest.php
//
// shopify_bulk_start/poll/download(): bulk query helpers for the nightly stock
// reconcile and baseline (WP06). A start that may have reached Shopify is
// reconciled against a snapshot of recent bulk operations taken before the
// start, never resent blind (SD-747).
//
// History:
// 20261021 CL/SZ created (SD-747).
// 20261023 CDX/LH Cover a missing bulk operation as a terminal polling failure.
// 20261023 CL/SZ Exact bulk-start matching: formatting ignored, strings exact, a differing new operation is unknown.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/shopify/client.php';
require_once __DIR__ . '/support/FakeShopify.php';

final class ClientBulkTest extends TestCase
{
	private const INNER = '{ inventoryItems { edges { node { id } } } }';
	private const OP = 'gid://shopify/BulkOperation/1';

	private string $dir;

	protected function setUp(): void
	{
		$this->dir = sys_get_temp_dir() . '/shopify-bulk-' . bin2hex(random_bytes(4));
		mkdir($this->dir);
	}

	protected function tearDown(): void
	{
		array_map('unlink', glob($this->dir . '/*') ?: []);
		rmdir($this->dir);
	}

	private static function started(string $status = 'CREATED'): array
	{
		return FakeShopify::ok(['bulkOperationRunQuery' => ['bulkOperation' => ['id' => self::OP, 'status' => $status], 'userErrors' => []]]);
	}

	private static function recent(array $nodes): array
	{
		return FakeShopify::ok(['bulkOperations' => ['nodes' => $nodes]]);
	}

	public function test_start_sends_the_inner_query_and_returns_the_operation(): void
	{
		$fake = new FakeShopify([self::recent([]), self::started()]);

		$r = shopify_bulk_start(FakeShopify::shop(), self::INNER, $fake->opts());

		$this->assertTrue($r['ok']);
		$this->assertSame(['id' => self::OP, 'status' => 'CREATED'], $r['bulk']);
		$this->assertStringContainsString('bulkOperations', $fake->calls[0]['body']['query']);
		$this->assertSame(['query' => self::INNER], $fake->calls[1]['body']['variables']);
		$this->assertStringContainsString('bulkOperationRunQuery', $fake->calls[1]['body']['query']);
	}

	public function test_start_after_a_timeout_reads_first_and_finds_its_operation(): void
	{
		$running = ['id' => self::OP, 'status' => 'RUNNING', 'type' => 'QUERY', 'query' => "{ inventoryItems {\n edges { node { id } } } }"];
		$fake = new FakeShopify([self::recent([]), FakeShopify::timeout(), self::recent([$running])]);

		$r = shopify_bulk_start(FakeShopify::shop(), self::INNER, $fake->opts());

		$this->assertCount(3, $fake->calls, 'snapshot, one start, one read, no second start');
		$this->assertStringContainsString('bulkOperations', $fake->calls[2]['body']['query']);
		$this->assertTrue($r['ok']);
		$this->assertSame('reconciled', $r['outcome']);
		$this->assertSame(['id' => self::OP, 'status' => 'RUNNING'], $r['bulk']);
	}

	public function test_start_matches_its_operation_whatever_the_formatting_between_tokens(): void
	{
		$inner = '{ products(query: "title:a  b") { edges { node { id } } } }';
		$stored = "{products(query:\"title:a  b\"){edges{node{id}}}}  # stored reformatted";
		$mine = ['id' => self::OP, 'status' => 'RUNNING', 'type' => 'QUERY', 'query' => $stored];
		$fake = new FakeShopify([self::recent([]), FakeShopify::timeout(), self::recent([$mine])]);

		$r = shopify_bulk_start(FakeShopify::shop(), $inner, $fake->opts());

		$this->assertSame('reconciled', $r['outcome']);
		$this->assertSame(self::OP, $r['bulk']['id']);
	}

	public function test_whitespace_inside_a_string_literal_is_not_ignored(): void
	{
		$inner = '{ products(query: "title:a  b") { edges { node { id } } } }';
		// Only the search string differs: "a b" with one space is a different query.
		$other = ['id' => self::OP, 'status' => 'RUNNING', 'type' => 'QUERY', 'query' => '{ products(query: "title:a b") { edges { node { id } } } }'];
		$fake = new FakeShopify([self::recent([]), FakeShopify::timeout(), self::recent([$other])]);

		$r = shopify_bulk_start(FakeShopify::shop(), $inner, $fake->opts());

		$this->assertCount(3, $fake->calls, 'not started again');
		$this->assertSame('uncertain', $r['outcome']);
	}

	public function test_a_new_operation_that_differs_from_ours_is_unknown_not_absent(): void
	{
		$different = ['id' => self::OP, 'status' => 'RUNNING', 'type' => 'QUERY', 'query' => '{ products { edges { node { id } } } }'];
		$fake = new FakeShopify([self::recent([]), FakeShopify::timeout(), self::recent([$different])]);

		$r = shopify_bulk_start(FakeShopify::shop(), self::INNER, $fake->opts());

		$this->assertCount(3, $fake->calls, 'a new operation we cannot tell apart from ours is never started again');
		$this->assertSame('uncertain', $r['outcome']);
		$this->assertNull($r['bulk']);
	}

	public function test_normalise_keeps_strings_exact_and_ignores_formatting(): void
	{
		$this->assertSame(shopify_graphql_normalise("{ a(x: 1, y: \"p  q\") { b } }"), shopify_graphql_normalise("{a(x:1 y:\"p  q\"){b}} # c"));
		$this->assertNotSame(shopify_graphql_normalise('{ a(y: "p  q") { b } }'), shopify_graphql_normalise('{ a(y: "p q") { b } }'));
		$this->assertSame(shopify_graphql_normalise('{ a { ...f } }'), shopify_graphql_normalise('{a{... f}}'));
	}

	public function test_a_malformed_query_is_never_matched_to_another_operation(): void
	{
		// A stray quote: unreadable, so it can't match the valid query another worker started.
		$malformed = self::INNER . '"';
		$theirs = ['id' => self::OP, 'status' => 'RUNNING', 'type' => 'QUERY', 'query' => self::INNER];
		$fake = new FakeShopify([self::recent([]), FakeShopify::timeout(), self::recent([$theirs])]);

		$r = shopify_bulk_start(FakeShopify::shop(), $malformed, $fake->opts());

		$this->assertSame('uncertain', $r['outcome']);
		$this->assertNull($r['bulk']);
		$this->assertCount(3, $fake->calls);
	}

	public function test_normalise_refuses_documents_it_cannot_read_fully(): void
	{
		$this->assertNull(shopify_graphql_normalise(self::INNER . '"'));
		$this->assertNull(shopify_graphql_normalise('{ a(x: "unterminated) { b } }'));
		$this->assertNull(shopify_graphql_normalise('{ a(x: """open block) { b } }'));
		$this->assertNotNull(shopify_graphql_normalise('{ a(x: """closed "quotes" inside""") { b } }'));
	}

	public function test_start_resends_once_the_read_shows_it_did_not_start(): void
	{
		$other = ['id' => 'gid://shopify/BulkOperation/0', 'status' => 'COMPLETED', 'type' => 'QUERY', 'query' => '{ products { edges { node { id } } } }'];
		$fake = new FakeShopify([self::recent([$other]), FakeShopify::timeout(), self::recent([$other]), self::started()]);

		$r = shopify_bulk_start(FakeShopify::shop(), self::INNER, $fake->opts());

		$this->assertTrue($r['ok']);
		$this->assertSame('ok', $r['outcome']);
		$this->assertCount(4, $fake->calls);
	}

	public function test_start_does_not_mistake_an_earlier_run_of_the_same_query_for_its_own(): void
	{
		// The same query finished a minute ago; it is in the snapshot, so it is not this start's.
		$earlier = ['id' => 'gid://shopify/BulkOperation/0', 'status' => 'COMPLETED', 'type' => 'QUERY', 'query' => self::INNER];
		$fake = new FakeShopify([self::recent([$earlier]), FakeShopify::timeout(), self::recent([$earlier]), self::started()]);

		$r = shopify_bulk_start(FakeShopify::shop(), self::INNER, $fake->opts());

		$this->assertSame('ok', $r['outcome']);
		$this->assertSame(self::OP, $r['bulk']['id']);
		$this->assertCount(4, $fake->calls);
	}

	public function test_a_new_operation_that_failed_is_reported_not_started_again(): void
	{
		$failed = ['id' => self::OP, 'status' => 'FAILED', 'type' => 'QUERY', 'query' => self::INNER];
		$fake = new FakeShopify([self::recent([]), FakeShopify::timeout(), self::recent([$failed])]);

		$r = shopify_bulk_start(FakeShopify::shop(), self::INNER, $fake->opts());

		$this->assertCount(3, $fake->calls, 'no second start');
		$this->assertSame('reconciled', $r['outcome']);
		$this->assertSame(['id' => self::OP, 'status' => 'FAILED'], $r['bulk']);
	}

	public function test_two_new_operations_with_the_same_query_are_ambiguous(): void
	{
		$a = ['id' => 'gid://shopify/BulkOperation/5', 'status' => 'RUNNING', 'type' => 'QUERY', 'query' => self::INNER];
		$b = ['id' => 'gid://shopify/BulkOperation/6', 'status' => 'RUNNING', 'type' => 'QUERY', 'query' => self::INNER];
		$fake = new FakeShopify([self::recent([]), FakeShopify::timeout(), self::recent([$b, $a])]);

		$r = shopify_bulk_start(FakeShopify::shop(), self::INNER, $fake->opts());

		$this->assertCount(3, $fake->calls);
		$this->assertSame('uncertain', $r['outcome']);
		$this->assertNull($r['bulk']);
	}

	/**
	 * Answers a bulkOperations read the way Shopify 2026-07 does: newest first by default,
	 * oldest first with reverse: true, cut to first: N.
	 */
	private static function shopifyOrdered(array $oldestFirst): callable
	{
		return function (FakeShopify $fake) use ($oldestFirst): array {
			$query = end($fake->calls)['body']['query'];
			preg_match('/first:\s*(\d+)/', $query, $m);
			$list = preg_match('/reverse:\s*true/', $query) ? $oldestFirst : array_reverse($oldestFirst);
			return self::recent(array_slice($list, 0, (int) ($m[1] ?? 10)));
		};
	}

	public function test_start_finds_its_operation_behind_more_than_ten_older_ones(): void
	{
		$old = [];
		for ($i = 1; $i <= 12; $i++) {
			$old[] = ['id' => "gid://shopify/BulkOperation/10$i", 'status' => 'COMPLETED', 'type' => 'QUERY', 'query' => self::INNER];
		}
		$mine = ['id' => self::OP, 'status' => 'RUNNING', 'type' => 'QUERY', 'query' => self::INNER];
		$fake = new FakeShopify([self::shopifyOrdered($old), FakeShopify::timeout(), self::shopifyOrdered(array_merge($old, [$mine]))]);

		$r = shopify_bulk_start(FakeShopify::shop(), self::INNER, $fake->opts());

		$this->assertCount(3, $fake->calls, 'snapshot, start, read: no second start');
		$this->assertSame('reconciled', $r['outcome']);
		$this->assertSame(self::OP, $r['bulk']['id']);
	}

	public function test_no_start_is_sent_when_the_snapshot_read_fails(): void
	{
		$fake = new FakeShopify([FakeShopify::json(['errors' => 'Unauthorized'], 401)]);

		$r = shopify_bulk_start(FakeShopify::shop(), self::INNER, $fake->opts());

		$this->assertFalse($r['ok']);
		$this->assertNull($r['bulk']);
		$this->assertCount(1, $fake->calls);
	}

	public function test_start_stays_uncertain_when_the_read_fails(): void
	{
		$fake = new FakeShopify([self::recent([]), FakeShopify::timeout(), FakeShopify::json(['errors' => 'Unauthorized'], 401)]);

		$r = shopify_bulk_start(FakeShopify::shop(), self::INNER, $fake->opts());

		$this->assertFalse($r['ok']);
		$this->assertSame('uncertain', $r['outcome']);
		$this->assertNull($r['bulk']);
		$this->assertCount(3, $fake->calls);
	}

	public function test_poll_reports_done_and_the_url(): void
	{
		$node = ['id' => self::OP, 'status' => 'COMPLETED', 'errorCode' => null, 'objectCount' => '3', 'fileSize' => '120',
			'url' => 'https://storage.googleapis.com/x.jsonl?sig=abc', 'partialDataUrl' => null];
		$fake = new FakeShopify([FakeShopify::ok(['bulkOperation' => array_merge($node, ['status' => 'RUNNING', 'url' => null])]), FakeShopify::ok(['bulkOperation' => $node])]);
		$shop = FakeShopify::shop();

		$running = shopify_bulk_poll($shop, self::OP, $fake->opts());
		$done = shopify_bulk_poll($shop, self::OP, $fake->opts());

		$this->assertFalse($running['done']);
		$this->assertTrue($done['done']);
		$this->assertSame($node['url'], $done['bulk']['url']);
		$this->assertSame(['id' => self::OP], $fake->calls[0]['body']['variables']);
	}

	public function test_poll_null_operation_is_not_found_and_done(): void
	{
		$fake = new FakeShopify([FakeShopify::ok(['bulkOperation' => null])]);

		$r = shopify_bulk_poll(FakeShopify::shop(), self::OP, $fake->opts());

		$this->assertFalse($r['ok']);
		$this->assertSame('failed', $r['outcome']);
		$this->assertSame('not_found', $r['error']);
		$this->assertTrue($r['done']);
		$this->assertFalse($r['retryable']);
		$this->assertNull($r['bulk']);
		$this->assertCount(1, $fake->calls);
	}

	public function test_poll_failure_is_not_done(): void
	{
		$fake = new FakeShopify([FakeShopify::json(['errors' => 'Unauthorized'], 401)]);

		$r = shopify_bulk_poll(FakeShopify::shop(), self::OP, $fake->opts());

		$this->assertFalse($r['ok']);
		$this->assertFalse($r['done']);
		$this->assertNull($r['bulk']);
	}

	public function test_download_writes_the_file_and_logs_only_the_host(): void
	{
		$logs = [];
		$target = $this->dir . '/stock.jsonl';
		$line = "{\"id\":\"gid://shopify/InventoryItem/1\"}\n";
		$download = function (string $url, $fh) use ($line): array {
			fwrite($fh, $line);
			return ['status' => 200, 'errno' => 0, 'error' => ''];
		};

		$r = shopify_bulk_download('https://storage.googleapis.com/b/x.jsonl?X-Goog-Signature=secretsig', $target, [
			'download' => $download, 'log' => function (array $e) use (&$logs) { $logs[] = $e; },
		]);

		$this->assertTrue($r['ok']);
		$this->assertSame(strlen($line), $r['bytes']);
		$this->assertFileExists($target);
		$this->assertFileDoesNotExist($target . '.part');
		$this->assertSame('storage.googleapis.com', $logs[0]['host']);
		$this->assertStringNotContainsString('secretsig', json_encode($logs));
	}

	public function test_failed_download_leaves_no_file(): void
	{
		$target = $this->dir . '/stock.jsonl';
		$download = function (string $url, $fh): array {
			fwrite($fh, 'half a fi');
			return ['status' => 0, 'errno' => 28, 'error' => 'timeout'];
		};

		$r = shopify_bulk_download('https://storage.googleapis.com/x.jsonl', $target, ['download' => $download, 'log' => fn() => null]);

		$this->assertFalse($r['ok']);
		$this->assertSame('transport', $r['error']);
		$this->assertFileDoesNotExist($target);
		$this->assertFileDoesNotExist($target . '.part');
	}

	public function test_overlapping_downloads_to_one_target_do_not_share_a_temporary_file(): void
	{
		$target = $this->dir . '/stock.jsonl';
		$inner = null;
		// While the outer download is writing, a second download to the same target runs and finishes.
		$outer = function (string $url, $fh) use ($target, &$inner): array {
			fwrite($fh, "outer-1\n");
			$inner = shopify_bulk_download('https://storage.googleapis.com/b.jsonl', $target, [
				'download' => function (string $url, $fh2): array {
					fwrite($fh2, "inner\n");
					return ['status' => 200, 'errno' => 0, 'error' => ''];
				},
				'log' => fn() => null,
			]);
			fwrite($fh, "outer-2\n");
			return ['status' => 200, 'errno' => 0, 'error' => ''];
		};

		$r = shopify_bulk_download('https://storage.googleapis.com/a.jsonl', $target, ['download' => $outer, 'log' => fn() => null]);

		$this->assertTrue($inner['ok']);
		$this->assertTrue($r['ok']);
		$this->assertSame("outer-1\nouter-2\n", file_get_contents($target), 'the last complete download wins, never a mix');
		$this->assertSame([$target], glob($this->dir . '/*'));
	}

	public function test_download_into_an_unusable_path_fails_and_leaves_nothing(): void
	{
		$download = function (string $url, $fh): array {
			fwrite($fh, 'data');
			return ['status' => 200, 'errno' => 0, 'error' => ''];
		};

		$r = shopify_bulk_download('https://storage.googleapis.com/x.jsonl', $this->dir, ['download' => $download, 'log' => fn() => null]);

		$this->assertFalse($r['ok']);
		$this->assertSame('file', $r['error']);
		$this->assertSame([], glob($this->dir . '/*'));
	}

	public function test_download_into_a_missing_folder_fails_cleanly_without_warnings(): void
	{
		set_error_handler(function (int $no, string $msg): never {
			throw new ErrorException($msg, 0, $no);
		});
		try {
			$r = shopify_bulk_download('https://storage.googleapis.com/x.jsonl', $this->dir . '/missing/stock.jsonl', [
				'download' => fn() => throw new LogicException('must not download'), 'log' => fn() => null,
			]);
		} finally {
			restore_error_handler();
		}

		$this->assertFalse($r['ok']);
		$this->assertSame('file', $r['error']);
	}

	public function test_download_refuses_plain_http(): void
	{
		$this->expectException(InvalidArgumentException::class);
		shopify_bulk_download('http://storage.googleapis.com/x.jsonl', $this->dir . '/x', ['log' => fn() => null]);
	}
}
