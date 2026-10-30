<?php
// tests/shopify/support/FakeCatalogueShop.php
//
// A Shopify store's products behind shopify_graphql()'s transport seam (SD-741): answers the
// catalogue's documents (catalogueRead, catalogueByHandle, catalogueBySku (paged by $pageSize),
// catalogueVariant, catalogueSet, catalogueMetafields) from its own product list and records every operation. productSet
// behaves as Shopify documents it: identifier id updates or fails, identifier handle updates the
// product with that handle or creates one, and a variants list replaces the variants (one left
// out is deleted). Fields the input leaves out stay as they are. SKU search matches by prefix,
// the way Shopify's search is loose; with $searchLag a product created here is not found by search
// yet, as right after a create in Shopify. $fail makes the next call of an operation fail.
//
// History:
// 20261029 CL/SS SD-741: created.

require_once __DIR__ . '/FakeShopify.php';

final class FakeCatalogueShop
{
	/** @var array<string, array> product id => product */
	public array $products = [];
	/** @var list<array{op: string, vars: array}> */
	public array $calls = [];
	/** @var array<string, array{value: string, type: string}> "<owner>|<namespace>.<key>" => metafield */
	public array $metafields = [];
	/** @var array<string, string> op => user_error | timeout_after_apply | throw_after_apply | http_500 | http_401, used once; "<mode>x<n>" n times */
	public array $fail = [];
	/** SKU search hits per page; the catalogue asks for 100. */
	public int $pageSize = 100;
	/** Products created through productSet stay out of SKU search, like Shopify's index lag. */
	public bool $searchLag = false;
	/** @var array<string, true> */
	private array $unindexed = [];
	private int $next = 100;

	/** A product HML has, with $variants variants; the first has $sku. */
	public function add(string $handle, string $sku, int $variants = 1, array $fields = []): array
	{
		$id = 'gid://shopify/Product/' . $this->next++;
		$nodes = [];
		for ($i = 0; $i < $variants; $i++) {
			$nodes[] = $this->variant(['sku' => $i === 0 ? $sku : "$sku-$i", 'optionValues' => [['optionName' => 'Size', 'name' => "S$i"]]]);
		}
		$this->products[$id] = $fields + ['id' => $id, 'handle' => $handle, 'title' => $handle, 'status' => 'ACTIVE',
			'descriptionHtml' => '<p>HML text</p>', 'tags' => ['hml'], 'options' => [$this->option('Size', array_map(fn(array $v): string => $v['selectedOptions'][0]['value'], $nodes))],
			'variants' => $nodes];
		return $this->products[$id];
	}

	public function opts(array $extra = []): array
	{
		return (new FakeShopify([]))->opts($extra + [
			'transport' => fn(string $url, array $headers, string $body, float $connect, float $timeout): array => $this->send($body),
		]);
	}

	/** @return list<string> the operations sent, in order */
	public function ops(): array
	{
		return array_column($this->calls, 'op');
	}

	public function byHandle(string $handle): ?array
	{
		foreach ($this->products as $product) {
			if ($product['handle'] === $handle) {
				return $product;
			}
		}
		return null;
	}

	private function send(string $body): array
	{
		$request = json_decode($body, true);
		preg_match('/^\s*(query|mutation)\s+(\w+)/', $request['query'], $m);
		$op = $m[2] ?? '?';
		$vars = $request['variables'] ?? [];
		$this->calls[] = ['op' => $op, 'vars' => $vars];
		$fail = $this->fail[$op] ?? null;
		unset($this->fail[$op]);
		if ($fail !== null && preg_match('/^(\w+?)x(\d+)$/', $fail, $times)) {
			$fail = $times[1];
			if ((int)$times[2] > 1) {
				$this->fail[$op] = $fail . 'x' . ((int)$times[2] - 1);
			}
		}
		if ($fail === 'http_500' || $fail === 'http_401') {
			return FakeShopify::json(['errors' => [['message' => $fail === 'http_500' ? 'Internal error' : 'Invalid API key or access token']]], (int)substr($fail, 5));
		}
		switch ($op) {
			case 'catalogueRead':
				return FakeShopify::ok(['product' => $this->out($this->products[$vars['id']] ?? null)]);
			case 'catalogueByHandle':
				return FakeShopify::ok(['productByIdentifier' => $this->out($this->byHandle($vars['identifier']['handle']))]);
			case 'catalogueBySku':
				$hits = $this->bySku($vars['query']);
				$offset = (int)($vars['after'] ?? 0);
				$more = $offset + $this->pageSize < count($hits);
				return FakeShopify::ok(['productVariants' => ['nodes' => array_slice($hits, $offset, $this->pageSize),
					'pageInfo' => ['hasNextPage' => $more, 'endCursor' => $more ? (string)($offset + $this->pageSize) : null]]]);
			case 'catalogueVariant':
				foreach ($this->products as $product) {
					foreach ($product['variants'] as $v) {
						if ($v['id'] === $vars['id']) {
							return FakeShopify::ok(['productVariant' => ['id' => $v['id'], 'sku' => $v['sku'] ?? null]]);
						}
					}
				}
				return FakeShopify::ok(['productVariant' => null]);
			case 'catalogueMetafields':
				foreach ($vars['metafields'] as $mf) {
					$this->metafields[$mf['ownerId'] . '|' . $mf['namespace'] . '.' . $mf['key']] = ['value' => $mf['value'], 'type' => $mf['type']];
				}
				return FakeShopify::ok(['metafieldsSet' => ['metafields' => [['id' => 'gid://shopify/Metafield/1']], 'userErrors' => []]]);
			case 'catalogueSet':
				if ($fail === 'user_error') {
					return FakeShopify::ok(['productSet' => ['product' => null, 'userErrors' => [['field' => ['input', 'variants', '0', 'barcode'], 'message' => 'Barcode is invalid', 'code' => 'INVALID']]]]);
				}
				$product = $this->set($vars['identifier'] ?? null, $vars['input']);
				if ($fail === 'timeout_after_apply') {
					return FakeShopify::timeout();
				}
				if ($fail === 'throw_after_apply') {
					throw new RuntimeException('crash after Shopify applied the create (test)');
				}
				return FakeShopify::ok(['productSet' => ['product' => $this->out($product),
					'userErrors' => $product === null ? [['field' => ['identifier', 'id'], 'message' => 'Product does not exist', 'code' => 'PRODUCT_DOES_NOT_EXIST']] : []]]);
		}
		throw new LogicException("FakeCatalogueShop: unexpected operation $op");
	}

	private function set(?array $identifier, array $input): ?array
	{
		$product = isset($identifier['id']) ? ($this->products[$identifier['id']] ?? false)
			: (isset($identifier['handle']) ? $this->byHandle($identifier['handle']) : null);
		if ($product === false) {
			return null;
		}
		if ($product === null) {
			$id = 'gid://shopify/Product/' . $this->next++;
			$product = ['id' => $id, 'handle' => $input['handle'] ?? ($identifier['handle'] ?? "p$id"), 'title' => '', 'status' => 'ACTIVE',
				'descriptionHtml' => '', 'tags' => [], 'options' => [], 'variants' => []];
			if ($this->searchLag) {
				$this->unindexed[$id] = true;
			}
		}
		foreach (['title', 'handle', 'status', 'descriptionHtml', 'tags', 'vendor', 'productType', 'files', 'seo'] as $field) {
			if (array_key_exists($field, $input)) {
				$product[$field] = $input[$field];
			}
		}
		if (isset($input['productOptions'])) {
			$product['options'] = array_map(fn(array $o): array => $this->option($o['name'], array_column($o['values'], 'name'), $o['id'] ?? null), $input['productOptions']);
		}
		if (isset($input['variants'])) {
			$kept = [];
			foreach ($input['variants'] as $v) {
				$old = null;
				foreach ($product['variants'] as $existing) {
					if (isset($v['id']) && $existing['id'] === $v['id']) {
						$old = $existing;
					}
				}
				$kept[] = $this->variant($v, $old);
			}
			$product['variants'] = $kept;
		}
		$this->products[$product['id']] = $product;
		return $product;
	}

	private function variant(array $input, ?array $old = null): array
	{
		$id = $old['id'] ?? 'gid://shopify/ProductVariant/' . $this->next++;
		return array_merge($old ?? ['inventoryItem' => ['id' => 'gid://shopify/InventoryItem/' . $this->next++]], [
			'id' => $id,
			'selectedOptions' => array_map(fn(array $o): array => ['name' => $o['optionName'], 'value' => $o['name']], $input['optionValues'] ?? []),
		], array_intersect_key($input, array_flip(['sku', 'barcode', 'inventoryPolicy', 'taxable'])),
			isset($input['inventoryItem']) ? ['inventoryItem' => ['id' => ($old['inventoryItem']['id'] ?? 'gid://shopify/InventoryItem/' . $this->next++)] + $input['inventoryItem']] : []);
	}

	private function option(string $name, array $values, ?string $id = null): array
	{
		return ['id' => $id ?? 'gid://shopify/ProductOption/' . $this->next++, 'name' => $name,
			'optionValues' => array_map(fn(string $v): array => ['id' => 'gid://shopify/ProductOptionValue/' . $this->next++, 'name' => $v], $values)];
	}

	private function bySku(string $query): array
	{
		$term = stripcslashes(preg_replace('/^sku:"(.*)"$/s', '$1', $query));
		$nodes = [];
		foreach ($this->products as $product) {
			if (isset($this->unindexed[$product['id']])) {
				continue;
			}
			foreach ($product['variants'] as $v) {
				if (stripos((string)($v['sku'] ?? ''), $term) === 0) {
					$nodes[] = ['id' => $v['id'], 'sku' => $v['sku'], 'inventoryItem' => ['id' => $v['inventoryItem']['id']], 'product' => ['id' => $product['id']]];
				}
			}
		}
		return $nodes;
	}

	/** The product as SHOPIFY_CATALOGUE_PRODUCT reads it. */
	private function out(?array $product): ?array
	{
		if ($product === null) {
			return null;
		}
		return ['id' => $product['id'], 'handle' => $product['handle'], 'variantsCount' => ['count' => count($product['variants'])],
			'options' => $product['options'],
			'variants' => ['nodes' => array_map(fn(array $v): array => ['id' => $v['id'], 'sku' => $v['sku'] ?? null,
				'selectedOptions' => $v['selectedOptions'], 'inventoryItem' => ['id' => $v['inventoryItem']['id']]], array_slice($product['variants'], 0, 2))]];
	}
}
