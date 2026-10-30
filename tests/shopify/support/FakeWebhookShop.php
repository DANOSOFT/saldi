<?php
// tests/shopify/support/FakeWebhookShop.php
//
// A Shopify store's webhook subscriptions behind shopify_graphql()'s transport seam (SD-749):
// answers webhookSubscriptions (paged), webhookSubscriptionCreate and webhookSubscriptionUpdate
// from its own list, and records every operation. $fail makes one topic's create time out
// after it was applied, or answer a userError.
//
// History:
// 20261028 CL/SZ SD-749: created.

require_once __DIR__ . '/FakeShopify.php';

final class FakeWebhookShop
{
	/** @var list<array{id: string, topic: string, uri: string}> */
	public array $subs = [];
	/** @var list<array{op: string, vars: array}> */
	public array $calls = [];
	public int $pageSize = 100;
	/** @var array<string, string> topic => timeout | user_error, used once */
	public array $fail = [];
	private int $next = 1;

	public function add(string $topic, string $uri): void
	{
		$this->subs[] = ['id' => 'gid://shopify/WebhookSubscription/' . $this->next++, 'topic' => $topic, 'uri' => $uri];
	}

	/** shopify_graphql() opts with this store as the transport; $extra wins. */
	public function opts(array $extra = []): array
	{
		return (new FakeShopify([]))->opts($extra + [
			'transport' => fn(string $url, array $headers, string $body, float $connect, float $timeout): array => $this->send($body),
		]);
	}

	/** @return list<string> the ops sent, in order */
	public function ops(): array
	{
		return array_column($this->calls, 'op');
	}

	/** @return array<string, list<string>> topic => uris */
	public function byTopic(): array
	{
		$out = [];
		foreach ($this->subs as $sub) {
			$out[$sub['topic']][] = $sub['uri'];
		}
		ksort($out);
		return $out;
	}

	private function send(string $body): array
	{
		$request = json_decode($body, true);
		$query = $request['query'];
		$vars = $request['variables'];
		if (str_contains($query, 'webhookSubscriptionCreate(')) {
			$this->calls[] = ['op' => 'create', 'vars' => $vars];
			$topic = $vars['topic'];
			$fail = $this->fail[$topic] ?? null;
			unset($this->fail[$topic]);
			if ($fail === 'user_error') {
				return FakeShopify::ok(['webhookSubscriptionCreate' => ['webhookSubscription' => null,
					'userErrors' => [['field' => ['webhookSubscription', 'uri'], 'message' => 'Address for this topic has already been taken']]]]);
			}
			$this->add($topic, $vars['webhookSubscription']['uri']);
			if ($fail === 'timeout') {
				return FakeShopify::timeout();
			}
			return FakeShopify::ok(['webhookSubscriptionCreate' => ['webhookSubscription' => end($this->subs), 'userErrors' => []]]);
		}
		if (str_contains($query, 'webhookSubscriptionUpdate(')) {
			$this->calls[] = ['op' => 'update', 'vars' => $vars];
			foreach ($this->subs as &$sub) {
				if ($sub['id'] === $vars['id']) {
					$sub['uri'] = $vars['webhookSubscription']['uri'];
					return FakeShopify::ok(['webhookSubscriptionUpdate' => ['webhookSubscription' => $sub, 'userErrors' => []]]);
				}
			}
			unset($sub);
			return FakeShopify::ok(['webhookSubscriptionUpdate' => ['webhookSubscription' => null, 'userErrors' => [['field' => ['id'], 'message' => 'not found']]]]);
		}
		$this->calls[] = ['op' => 'list', 'vars' => $vars];
		$matching = array_values(array_filter($this->subs, fn(array $s): bool => in_array($s['topic'], $vars['topics'] ?? [], true)));
		$offset = (int)($vars['after'] ?? 0);
		$page = array_slice($matching, $offset, $this->pageSize);
		$more = $offset + $this->pageSize < count($matching);
		return FakeShopify::ok(['webhookSubscriptions' => ['nodes' => $page,
			'pageInfo' => ['hasNextPage' => $more, 'endCursor' => $more ? (string)($offset + $this->pageSize) : null]]]);
	}
}
