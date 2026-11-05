<?php
// tests/shopify/support/FakeShopifyStore.php
//
// One stateful fake Shopify for every lane (SD-766), behind shopify_graphql()'s transport seam like
// the per-feature fakes: opts() wires it in, with FakeShopify's clock (nothing really sleeps). Each
// shop has locations, products with variants and inventory items, inventory levels per location,
// customers, orders (line items, tags, note, customAttributes, metafields), fulfillment orders,
// fulfillments, refunds and webhook subscriptions, so a chain of lanes (web order, allocation,
// move, fulfilment, invoice, stock push) can run against one store.
//
// A request is dispatched on the Shopify root fields in its document, not on our operation names,
// so any lane's document works; the answer is projected onto the document's selection (aliases,
// fragments, connections). Every document and its variables are checked against the pinned schema
// fixture first. A root field nobody implemented fails loudly; lanes add theirs with handle().
// Mutations that fire webhooks in Shopify append REST-shaped deliveries to $outbox, for the
// WebhookReplayer. The Shopify behaviours encoded here are listed in FakeShopifyBehaviours, each
// with its source; uses('<id>') marks where one applies. $fail injects a failure into the next
// call of a root field: user_error, timeout_after_apply, http_500, http_401, throttled or refused,
// once, or "<mode>x<n>" n times. tests/shopify/support/FAKE-STORE.md shows how a lane adds a root
// field.
//
// History:
// 20261102 CL/LH SD-766: created.
// 20261103 CL/LH SD-766: review fixes: @idempotent required where 2026-07 requires it, taxes only from the input,
//                a move carries the order's commitments, a destination must stock the item, the documented move
//                request statuses, one location per fulfilment, compareDigest, the payload's cost on mutations,
//                one event id per action with a webhook id per delivery, inventoryActivate.
// 20261103 CL/LH SD-766: second review: the metafield digest is the persisted value's, so writing the same value keeps it (B36).
// 20261103 CL/SS SD-802: productVariantsBulkUpdate sets price and compareAtPrice (B37).
// 20261104 CL/SS SD-808: the order fields the paid-order read selects (billing address, line totals, shipping lines, payment details).
// 20261105 CL/SZ SD-756: inventorySetQuantities on a pair not stocked succeeds and stays hidden until activated, as the dev store does.
// 20261105 CL/SZ SD-757: bulkOperationRunQuery, bulkOperation(s) and the bulk download seam for the stock read (B38); setAvailable().
// 20261105 CL/SZ SD-757 review: rootObjectCount counts the items; the bulk query must select the item id and location id.

require_once __DIR__ . '/FakeShopify.php';
require_once __DIR__ . '/GraphqlDocument.php';
require_once __DIR__ . '/FakeShopifyBehaviours.php';

final class FakeShopifyStore
{
	public const API_VERSION = '2026-07';
	/** B09: a mutation root field costs 10 points, on top of its payload's selection. */
	public const MUTATION_COST = 10;
	/** B11 */
	public const MAX_QUERY_COST = 1000;
	/** B06 */
	public const METAFIELDS_SET_MAX = 25;
	/** B06: 10 MB, read as 10^7 bytes, the stricter reading. */
	public const METAFIELDS_SET_MAX_BYTES = 10000000;
	/** B21: the root fields that take @idempotent(key:) in 2026-07; it is required on each. A lane adds one with its 2026-07 doc. */
	public const IDEMPOTENT = ['inventoryActivate', 'inventorySetQuantities', 'refundCreate'];
	/** B21: Shopify keeps an idempotency key for 24 hours. */
	public const IDEMPOTENCY_SECONDS = 86400;
	/** B05: request statuses that keep a fulfillment order with its fulfillment service. */
	public const MOVE_REFUSED_REQUESTS = ['SUBMITTED', 'ACCEPTED', 'CANCELLATION_REQUESTED', 'CANCELLATION_REJECTED'];
	public const QUANTITY_NAMES = ['available', 'on_hand', 'committed', 'incoming', 'reserved', 'damaged', 'safety_stock', 'quality_control'];

	/** The clock, sleeps and log lines, shared with FakeShopify's opts(). */
	public FakeShopify $clock;
	/** B10 */
	public float $bucketMax = 2000.0;
	/** B10 */
	public float $restoreRate = 100.0;
	/** Unix time of fake clock second 0; the clock starts at 1000, Mon 2 Nov 2026 08:00 UTC. */
	public int $epoch;
	/** @var list<array{shop: string, operation: ?string, kind: string, roots: list<string>, variables: array, status: int}> */
	public array $calls = [];
	/** @var array<string, string> root field => user_error | timeout_after_apply | http_500 | http_401 | throttled | refused, once; "<mode>x<n>" n times */
	public array $fail = [];
	/** @var list<array{shop: string, topic: string, event_id: string, webhook_id: string, triggered_at: string, api_version: string, uri: string, body: string}> */
	public array $outbox = [];
	/** @var array<string, int> behaviour id => times applied */
	public array $used = [];

	/** @var array<string, array<string, mixed>> shop domain => state */
	private array $shops = [];
	/** @var array{query: array<string, callable>, mutation: array<string, callable>} */
	private array $handlers = ['query' => [], 'mutation' => []];
	/** Shopify ids are 64-bit; these have 13 digits so no int4 column can hold them. */
	private int $next = 7400000000001;
	private GraphqlSchema $schema;

	public function __construct(?FakeShopify $clock = null)
	{
		$this->clock = $clock ?? new FakeShopify([]);
		$this->epoch = (int)gmmktime(8, 0, 0, 11, 2, 2026) - 1000;
		$this->schema = GraphqlSchema::load();
		foreach (['shop', 'product', 'productByIdentifier', 'products', 'productVariant', 'productVariants', 'inventoryItem',
			'inventoryLevel', 'location', 'locations', 'order', 'orders', 'fulfillmentOrder', 'fulfillmentOrders', 'customer',
			'customers', 'customerByIdentifier', 'webhookSubscription', 'webhookSubscriptions', 'node', 'nodes', 'bulkOperations', 'bulkOperation'] as $root) {
			$method = 'q' . ucfirst($root);
			$this->handlers['query'][$root] = fn(self $store, string $domain, array $args, array $field): mixed => $this->$method($domain, $args, $field);
		}
		foreach (['productSet', 'productVariantsBulkUpdate', 'inventorySetQuantities', 'inventoryActivate', 'orderCreate', 'orderUpdate', 'fulfillmentOrderMove', 'fulfillmentCreate',
			'tagsAdd', 'tagsRemove', 'metafieldsSet', 'customerCreate', 'customerUpdate', 'refundCreate',
			'webhookSubscriptionCreate', 'webhookSubscriptionUpdate', 'webhookSubscriptionDelete', 'bulkOperationRunQuery'] as $root) {
			$method = 'm' . ucfirst($root);
			$this->handlers['mutation'][$root] = fn(self $store, string $domain, array $args, array $field): mixed => $this->$method($domain, $args, $field);
		}
	}

	// ---- setup -------------------------------------------------------------------------------

	/**
	 * Adds a shop with one location ("Lager 1") unless $fields['locations'] names others.
	 *
	 * @param array{domain?: string, token?: string, name?: string, locations?: list<string>} $fields
	 * @return array{shop_key: string, domain: string, api_version: string} the row shopify_graphql() takes
	 */
	public function addShop(string $key, array $fields = []): array
	{
		$domain = $fields['domain'] ?? "$key-fake.myshopify.com";
		if (isset($this->shops[$domain])) {
			throw new LogicException("FakeShopifyStore: shop $domain exists");
		}
		$this->shops[$domain] = [
			'key' => $key, 'domain' => $domain, 'id' => $this->gid('Shop'), 'name' => $fields['name'] ?? strtoupper($key) . ' (fake)',
			'token' => $fields['token'] ?? FakeShopify::TOKEN, 'available' => $this->bucketMax, 'bucket_at' => $this->clock->now,
			'locations' => [], 'products' => [], 'variants' => [], 'items' => [], 'levels' => [], 'customers' => [], 'orders' => [],
			'fulfillment_orders' => [], 'fulfillments' => [], 'refunds' => [], 'metafields' => [], 'subscriptions' => [],
			'receipts' => [], 'idempotent' => [], 'order_number' => 1000,
		];
		foreach ($fields['locations'] ?? ['Lager 1'] as $name) {
			$this->addLocation($domain, $name);
		}
		return $this->shopRow($domain);
	}

	/** @return array{shop_key: string, domain: string, api_version: string} */
	public function shopRow(string $domain): array
	{
		return ['shop_key' => $this->shop($domain)['key'], 'domain' => $domain, 'api_version' => self::API_VERSION];
	}

	/** Adds an active location; every existing inventory item is stocked there with 0 (B22). */
	public function addLocation(string $domain, string $name): string
	{
		$s = &$this->shop($domain);
		$gid = $this->gid('Location');
		$s['locations'][$gid] = ['id' => $gid, 'name' => $name, 'active' => true, 'created' => $this->clock->now];
		foreach (array_keys($s['items']) as $item) {
			$s['levels'][$item][$gid] = $this->emptyLevel();
		}
		return $gid;
	}

	/** @return list<string> the shop's location GIDs, in creation order */
	public function locations(string $domain): array
	{
		return array_keys($this->shop($domain)['locations']);
	}

	/**
	 * A product with one variant, stocked as $available per location GID, without going through
	 * a request (no call, no cost, no webhook).
	 *
	 * @param array<string, int> $available
	 * @return array{product: string, variant: string, item: string}
	 */
	public function seedProduct(string $domain, string $handle, string $sku, array $available = [], array $fields = []): array
	{
		$product = $this->applyProductSet($domain, ['handle' => $handle], $fields + [
			'title' => $handle, 'handle' => $handle, 'status' => 'ACTIVE',
			'variants' => [['sku' => $sku, 'price' => $fields['price'] ?? '100.00', 'optionValues' => [['optionName' => 'Title', 'name' => 'Default Title']]]],
		], false);
		$variant = $this->shop($domain)['products'][$product]['variants'][0];
		$item = $this->shop($domain)['variants'][$variant]['item'];
		foreach ($available as $location => $qty) {
			$this->setQuantity($domain, $item, $location, 'available', $qty);
		}
		return ['product' => $product, 'variant' => $variant, 'item' => $item];
	}

	/** Takes an item's inventory level away from a location without a request: the location no longer stocks it (B03, B35). */
	public function unstock(string $domain, string $item, string $location): void
	{
		$s = &$this->shop($domain);
		if (!isset($s['levels'][$item][$location])) {
			throw new LogicException("FakeShopifyStore: $item is not stocked at $location");
		}
		unset($s['levels'][$item][$location]);
	}

	/** A quantity changed in the shop's admin, not through Saldi: what the nightly read looks for. */
	public function setAvailable(string $domain, string $item, string $location, int $quantity): void
	{
		$this->setQuantity($domain, $item, $location, 'available', $quantity);
	}

	/**
	 * The download seam of shopify_bulk_download() for this store's bulk files.
	 *
	 * @return callable(string, resource, float, float): array{status: int, errno: int}
	 */
	public function bulkDownloader(): callable
	{
		return function (string $url, $fh, float $connect, float $timeout): array {
			foreach ($this->shops as $s) {
				foreach ($s['bulk'] ?? [] as $op) {
					if ($op['url'] === $url) {
						fwrite($fh, $op['file']);
						return ['status' => 200, 'errno' => 0];
					}
				}
			}
			return ['status' => 404, 'errno' => 0];
		};
	}

	/** A webhook subscription without a request, as register-webhooks would leave it. */
	public function subscribe(string $domain, string $topic, string $uri): string
	{
		$s = &$this->shop($domain);
		$gid = $this->gid('WebhookSubscription');
		$s['subscriptions'][$gid] = ['id' => $gid, 'topic' => $topic, 'uri' => $uri, 'created' => $this->clock->now];
		return $gid;
	}

	/** Adds or replaces the handler of a root field; fn(FakeShopifyStore, string $domain, array $args, array $field): mixed. */
	public function handle(string $kind, string $root, callable $handler): void
	{
		if (!isset($this->handlers[$kind])) {
			throw new InvalidArgumentException("kind must be query or mutation, not $kind");
		}
		$this->handlers[$kind][$root] = $handler;
	}

	/** The state of one shop, by reference, for assertions and for handlers lanes add. */
	public function &shop(string $domain): array
	{
		if (!isset($this->shops[$domain])) {
			throw new LogicException("FakeShopifyStore: no shop $domain");
		}
		return $this->shops[$domain];
	}

	/** Marks a FakeShopifyBehaviours entry as applied. */
	public function uses(string $id): void
	{
		FakeShopifyBehaviours::get($id);
		$this->used[$id] = ($this->used[$id] ?? 0) + 1;
	}

	/** shopify_graphql() opts with this store as the transport and FakeShopify's clock; $extra wins. */
	public function opts(array $extra = []): array
	{
		return $this->clock->opts($extra + [
			'transport' => fn(string $url, array $headers, string $body, float $connect, float $timeout): array
				=> $this->send($url, $headers, $body, $connect, $timeout),
		]);
	}

	/** @return list<array> the outbox, emptied */
	public function takeDeliveries(): array
	{
		$out = $this->outbox;
		$this->outbox = [];
		return $out;
	}

	/** Everything but the handlers, as JSON-safe arrays (graphql.json keeps it). */
	public function exportState(): array
	{
		return ['now' => $this->clock->now, 'epoch' => $this->epoch, 'next' => $this->next, 'shops' => $this->shops, 'outbox' => $this->outbox];
	}

	public static function fromState(array $state): self
	{
		$store = new self();
		$store->clock->now = (float)$state['now'];
		$store->epoch = (int)$state['epoch'];
		$store->next = (int)$state['next'];
		$store->shops = $state['shops'];
		$store->outbox = $state['outbox'];
		return $store;
	}

	// ---- transport ---------------------------------------------------------------------------

	/** The transport: same contract as shopify_http_post(). */
	public function send(string $url, array $headers, string $body, float $connect, float $timeout): array
	{
		$this->clock->now += $this->clock->requestSeconds;
		if (!preg_match('#^https://([^/]+)/admin/api/([0-9-]+)/graphql\.json$#', $url, $m) || !isset($this->shops[$m[1]])) {
			return FakeShopify::json(['errors' => 'Not Found'], 404);
		}
		[$domain, $version] = [$m[1], $m[2]];
		if ($version !== self::API_VERSION) {
			throw new LogicException("FakeShopifyStore models API $version? Only " . self::API_VERSION);
		}
		$token = null;
		foreach ($headers as $h) {
			if (stripos($h, 'X-Shopify-Access-Token:') === 0) {
				$token = trim(substr($h, 23));
			}
		}
		if ($token === null || !hash_equals($this->shop($domain)['token'], $token)) {
			$this->uses('B24');
			$this->calls[] = ['shop' => $domain, 'operation' => null, 'kind' => '?', 'roots' => [], 'variables' => [], 'status' => 401];
			return FakeShopify::json(['errors' => '[API] Invalid API key or access token (unrecognized login or wrong password)'], 401);
		}
		$request = json_decode($body, true);
		if (!is_array($request) || !is_string($request['query'] ?? null)) {
			return FakeShopify::json(['errors' => [['message' => 'Bad Request: no query']]], 400);
		}
		return $this->request($domain, $request['query'], is_array($request['variables'] ?? null) ? $request['variables'] : []);
	}

	/**
	 * Runs one document on a shop as Shopify answers it over HTTP, with cost, throttling and the
	 * injected failures.
	 *
	 * @return array{status: int, body: string, errno: int, error: string, sent: bool, retry_after: float|null}
	 */
	public function request(string $domain, string $query, array $vars): array
	{
		try {
			$doc = GraphqlDocument::parse($query);
			$problems = $this->schema->validateDocument($query);
			if ($problems) {
				throw new GraphqlRequestError(implode('; ', $problems), 'GRAPHQL_VALIDATION_FAILED');
			}
			$vars = $doc->variables($vars, $this->schema);
			$op = $doc->operation();
		} catch (GraphqlRequestError $e) {
			$this->calls[] = ['shop' => $domain, 'operation' => null, 'kind' => '?', 'roots' => [], 'variables' => $vars, 'status' => 200];
			return $this->errorResponse($e);
		} catch (RuntimeException $e) {
			// GraphqlSchema's own parse errors
			return $this->errorResponse(new GraphqlRequestError($e->getMessage()));
		}
		$kind = $op['type'] === 'mutation' ? 'mutation' : 'query';
		if ($op['type'] === 'subscription') {
			throw new LogicException('FakeShopifyStore: subscriptions are not part of the Admin API');
		}
		$fields = $doc->rootFields($vars);
		$roots = array_column($fields, 'name');
		$this->calls[] = ['shop' => $domain, 'operation' => $op['name'], 'kind' => $kind, 'roots' => $roots, 'variables' => $vars, 'status' => 200];
		$call = count($this->calls) - 1;
		try {
			$keys = array_map(fn(array $field): ?string => $this->idempotencyKey($kind, $field, $vars), $fields);
		} catch (GraphqlRequestError $e) {
			return $this->errorResponse($e);
		}

		$fail = $this->takeFailure($roots);
		if (in_array($fail, ['http_500', 'http_401', 'refused'], true)) {
			$this->calls[$call]['status'] = $fail === 'refused' ? 0 : (int)substr($fail, 5);
			return match ($fail) {
				'http_500' => FakeShopify::json(['errors' => [['message' => 'Internal error']]], 500),
				'http_401' => FakeShopify::json(['errors' => '[API] Invalid API key or access token (unrecognized login or wrong password)'], 401),
				'refused' => FakeShopify::refused(),
			};
		}

		$cost = $this->cost($fields, $this->schema->roots()[$kind], $vars, $doc, $kind === 'mutation');
		$this->uses('B09');
		if ($cost > self::MAX_QUERY_COST) {
			$this->uses('B11');
			return FakeShopify::json(['errors' => [['message' => "Query cost is $cost, which exceeds the single query max cost limit (" . self::MAX_QUERY_COST . ').',
				'extensions' => ['code' => 'MAX_COST_EXCEEDED', 'cost' => $cost, 'maxCost' => self::MAX_QUERY_COST]]]]);
		}
		$available = $this->restore($domain);
		if ($fail === 'throttled' || $cost > $available) {
			$this->uses('B10');
			return FakeShopify::json(['errors' => [['message' => 'Throttled', 'extensions' => ['code' => 'THROTTLED']]],
				'extensions' => ['cost' => $this->costInfo($cost, null, $available)]]);
		}
		$this->shop($domain)['available'] = $available - $cost;

		$data = [];
		try {
			foreach ($fields as $i => $field) {
				$data[$field['alias']] = $this->resolveRoot($domain, $kind, $field, $vars, $doc, $fail === 'user_error', $keys[$i]);
			}
		} catch (GraphqlRequestError $e) {
			return $this->errorResponse($e);
		}
		if ($fail === 'timeout_after_apply') {
			return FakeShopify::timeout();
		}
		return FakeShopify::json(['data' => $data, 'extensions' => ['cost' => $this->costInfo($cost, $cost, $this->shop($domain)['available'])]]);
	}

	private function resolveRoot(string $domain, string $kind, array $field, array $vars, GraphqlDocument $doc, bool $userError, ?string $key): mixed
	{
		$name = $field['name'];
		$type = trim($this->schema->fieldSpec($this->schema->roots()[$kind], $name)['type'], '[]!');
		if ($userError) {
			if ($kind !== 'mutation') {
				throw new LogicException("FakeShopifyStore: user_error is for mutations, not the query $name");
			}
			return $doc->project($this->refusal($type, $field, $vars, $doc, 'Injected failure (test)', 'INVALID'), $field['selections'], $type, $vars, $this->schema, $field['alias']);
		}
		$handler = $this->handlers[$kind][$name] ?? null;
		if ($handler === null) {
			throw new LogicException("FakeShopifyStore: root field $name not implemented");
		}
		$args = GraphqlDocument::args($field, $vars);
		$s = &$this->shop($domain);
		$slot = $key === null ? null : "$name|$key";
		$earlier = $slot === null ? null : ($s['idempotent'][$slot] ?? null);
		if ($earlier !== null && $this->clock->now - $earlier['at'] < self::IDEMPOTENCY_SECONDS) {
			if ($earlier['args'] !== sha1(json_encode($args))) {
				return $doc->project($this->refusal($type, $field, $vars, $doc, 'The same idempotency key cannot be used with different operation parameters.',
					'IDEMPOTENCY_KEY_PARAMETER_MISMATCH'), $field['selections'], $type, $vars, $this->schema, $field['alias']);
			}
			return $earlier['out'];
		}
		$value = $handler($this, $domain, $args, $field);
		if (str_ends_with($type, 'Connection') && is_array($value) && array_is_list($value)) {
			$this->uses('B19');
			$value = GraphqlDocument::connection($value, $args, $type);
		}
		$out = $doc->project($value, $field['selections'], $type, $vars, $this->schema, $field['alias']);
		if ($slot !== null) {
			$s['idempotent'][$slot] = ['args' => sha1(json_encode($args)), 'out' => $out, 'at' => $this->clock->now];
		}
		return $out;
	}

	/**
	 * B21: the key of a root field's @idempotent(key:). A field 2026-07 requires it on (IDEMPOTENT)
	 * is refused without one, as Shopify refuses the request; on any other field the directive
	 * fails loudly, as the fake cannot know whether Shopify takes it there.
	 */
	private function idempotencyKey(string $kind, array $field, array $vars): ?string
	{
		$takes = $kind === 'mutation' && in_array($field['name'], self::IDEMPOTENT, true);
		$key = null;
		foreach ($field['directives'] as $d) {
			if ($d['name'] !== 'idempotent') {
				continue;
			}
			if (!$takes) {
				throw new LogicException("FakeShopifyStore: @idempotent on $kind field {$field['name']} is not modelled; 2026-07 documents it on "
					. implode(', ', self::IDEMPOTENT) . ' (B21). A lane adds a field to FakeShopifyStore::IDEMPOTENT with its 2026-07 doc');
			}
			$key = isset($d['args']['key']) ? GraphqlDocument::value($d['args']['key'], $vars) : null;
		}
		if (!$takes) {
			return null;
		}
		$this->uses('B21');
		if (!is_string($key) || $key === '') {
			throw new GraphqlRequestError("{$field['name']} requires an idempotency key as of 2026-04: add @idempotent(key: ...) to the field");
		}
		return $key;
	}

	/** A payload with every selected field null and one userError, as a refused mutation answers; $code where the error type has it. */
	private function refusal(string $payloadType, array $field, array $vars, GraphqlDocument $doc, string $message, string $code): array
	{
		$out = [];
		foreach ($doc->fields($field['selections'] ?? [], $payloadType, $vars) as $sel) {
			$out[$sel['name']] = null;
		}
		$errorType = trim((string)($this->schema->fieldSpec($payloadType, 'userErrors')['type'] ?? 'UserError'), '[]!');
		$codeType = $this->schema->fieldSpec($errorType, 'code')['type'] ?? null;
		$out['userErrors'] = [['field' => null, 'message' => $message,
			'code' => $codeType !== null && $this->schema->validateInput($codeType, $code) === [] ? $code : null, 'elementIndex' => null]];
		return $out;
	}

	private function takeFailure(array $roots): ?string
	{
		foreach ($roots as $root) {
			$fail = $this->fail[$root] ?? null;
			if ($fail === null) {
				continue;
			}
			unset($this->fail[$root]);
			if (preg_match('/^(\w+?)x(\d+)$/', $fail, $times)) {
				$fail = $times[1];
				if ((int)$times[2] > 1) {
					$this->fail[$root] = $fail . 'x' . ((int)$times[2] - 1);
				}
			}
			if (!in_array($fail, ['user_error', 'timeout_after_apply', 'http_500', 'http_401', 'throttled', 'refused'], true)) {
				throw new LogicException("FakeShopifyStore: unknown failure mode $fail");
			}
			return $fail;
		}
		return null;
	}

	private function errorResponse(GraphqlRequestError $e): array
	{
		return FakeShopify::json(['errors' => [['message' => $e->getMessage(), 'extensions' => ['code' => $e->errorCode]]]]);
	}

	/** The bucket now, refilled at restoreRate since it was last used (B10). */
	private function restore(string $domain): float
	{
		$s = &$this->shop($domain);
		$s['available'] = min($this->bucketMax, $s['available'] + max(0.0, $this->clock->now - $s['bucket_at']) * $this->restoreRate);
		$s['bucket_at'] = $this->clock->now;
		return $s['available'];
	}

	private function costInfo(int $requested, ?int $actual, float $available): array
	{
		return ['requestedQueryCost' => $requested, 'actualQueryCost' => $actual,
			'throttleStatus' => ['maximumAvailable' => $this->bucketMax, 'currentlyAvailable' => $available, 'restoreRate' => $this->restoreRate]];
	}

	/**
	 * B09: scalars 0, objects 1, connections 2 + first (or last) times the node cost, fragments
	 * counted on their own type (every fragment of an interface or union, not the dearest one); a
	 * mutation root field costs MUTATION_COST plus its payload's selection.
	 */
	private function cost(array $selections, string $type, array $vars, GraphqlDocument $doc, bool $mutationRoots = false): int
	{
		$total = 0;
		foreach ($selections as $field) {
			if (!$doc->included($field['directives'], $vars)) {
				continue;
			}
			if ($field['kind'] !== 'field') {
				$fragment = $field['kind'] === 'spread' ? ($doc->fragments[$field['name']] ?? ['on' => null, 'selections' => []]) : $field;
				$total += $this->cost($fragment['selections'], $fragment['on'] ?? $type, $vars, $doc, $mutationRoots);
				continue;
			}
			if ($field['name'] === '__typename') {
				continue;
			}
			$spec = $this->schema->fieldSpec($type, $field['name']);
			$named = $spec === null ? '' : trim($spec['type'], '[]!');
			$composite = in_array($this->schema->kind($named), ['OBJECT', 'INTERFACE', 'UNION'], true);
			$inner = $composite ? $this->cost($field['selections'] ?? [], $named, $vars, $doc) : 0;
			if ($mutationRoots) {
				$total += self::MUTATION_COST + $inner;
				continue;
			}
			if (!$composite) {
				continue;
			}
			if (str_ends_with($named, 'Connection')) {
				$args = GraphqlDocument::args($field, $vars);
				$total += 2 + (int)($args['first'] ?? $args['last'] ?? 0) * max(1, $inner);
			} else {
				$total += 1 + $inner;
			}
		}
		return $total;
	}

	// ---- webhooks ----------------------------------------------------------------------------

	/**
	 * Appends one delivery per subscription to $topic (B12); none when the shop has none. The
	 * deliveries of one call share an event id; each has its own webhook id (B13).
	 */
	private function fire(string $domain, string $topic, array $payload): void
	{
		$enum = strtoupper(str_replace('/', '_', $topic));
		$this->uses('B12');
		$eventId = null;
		foreach ($this->shop($domain)['subscriptions'] as $sub) {
			if ($sub['topic'] !== $enum) {
				continue;
			}
			// B14: a fresh event id per topic and event, shared by the topic's subscriptions
			$eventId ??= $this->uuid();
			$this->uses('B14');
			$this->outbox[] = ['shop' => $domain, 'topic' => $topic, 'event_id' => $eventId, 'webhook_id' => self::webhookId($eventId, $sub['id']),
				'triggered_at' => $this->iso(true), 'api_version' => self::API_VERSION, 'uri' => $sub['uri'],
				'body' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
		}
	}

	/** B13: one delivery's webhook id, from its event and subscription, so every attempt carries the same one. */
	private static function webhookId(string $eventId, string $subscription): string
	{
		$h = sha1("$eventId $subscription");
		return sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20, 12));
	}

	private function restOrder(string $domain, string $gid): array
	{
		$o = $this->shop($domain)['orders'][$gid];
		$customer = $o['customer'] === null ? null : $this->restCustomer($domain, $o['customer']);
		return [
			'id' => $this->legacy($gid), 'admin_graphql_api_id' => $gid, 'name' => $o['name'], 'email' => $o['email'], 'phone' => $o['phone'],
			'created_at' => $this->local($o['created']), 'updated_at' => $this->local($o['updated']), 'processed_at' => $this->local($o['created']),
			'cancelled_at' => null, 'financial_status' => strtolower($this->financialStatus($domain, $gid)),
			'fulfillment_status' => ['UNFULFILLED' => null, 'PARTIALLY_FULFILLED' => 'partial', 'FULFILLED' => 'fulfilled'][$this->fulfillmentStatus($domain, $gid)],
			'currency' => $o['currency'], 'total_price' => sprintf('%.2f', $this->orderTotal($domain, $gid)),
			'subtotal_price' => sprintf('%.2f', $this->orderSubtotal($domain, $gid)), 'total_tax' => sprintf('%.2f', $this->orderTax($domain, $gid)),
			'taxes_included' => $o['taxes_included'], 'tax_lines' => array_map(fn(array $t): array => $this->restTaxLine($t), $this->orderTaxLines($domain, $gid)),
			'test' => $o['test'], 'tags' => implode(', ', $o['tags']), 'note' => $o['note'],
			'note_attributes' => array_map(fn(array $a): array => ['name' => $a['key'], 'value' => $a['value']], $o['custom_attributes']),
			'source_name' => $o['source_name'], 'source_identifier' => $o['source_identifier'], 'customer' => $customer,
			'line_items' => array_map(fn(array $l): array => [
				'id' => $this->legacy($l['id']), 'admin_graphql_api_id' => $l['id'], 'variant_id' => $l['variant'] === null ? null : $this->legacy($l['variant']),
				'sku' => $l['sku'], 'title' => $l['title'], 'name' => $l['title'], 'quantity' => $l['quantity'], 'price' => sprintf('%.2f', $l['price']),
				'taxable' => $l['taxable'], 'tax_lines' => array_map(fn(array $t): array => $this->restTaxLine($t), $l['tax_lines']),
				'fulfillable_quantity' => $this->unfulfilled($domain, $gid, $l['id']),
			], $o['lines']),
		];
	}

	private function restTaxLine(array $t): array
	{
		return ['title' => $t['title'], 'rate' => $t['rate'], 'price' => sprintf('%.2f', $t['amount']), 'channel_liable' => $t['channel_liable']];
	}

	private function restCustomer(string $domain, string $gid): array
	{
		$c = $this->shop($domain)['customers'][$gid];
		$addresses = array_map(fn(array $a, int $i): array => ['id' => $this->legacy($a['id']), 'customer_id' => $this->legacy($gid),
			'first_name' => $a['firstName'], 'last_name' => $a['lastName'], 'company' => $a['company'], 'address1' => $a['address1'],
			'address2' => $a['address2'], 'city' => $a['city'], 'zip' => $a['zip'], 'country' => $a['country'], 'country_code' => $a['countryCode'],
			'phone' => $a['phone'], 'name' => trim($a['firstName'] . ' ' . $a['lastName']), 'default' => $i === 0], $c['addresses'], array_keys($c['addresses']));
		return ['id' => $this->legacy($gid), 'admin_graphql_api_id' => $gid, 'email' => $c['email'], 'phone' => $c['phone'],
			'first_name' => $c['first_name'], 'last_name' => $c['last_name'], 'tags' => implode(', ', $c['tags']), 'note' => $c['note'],
			'created_at' => $this->local($c['created']), 'updated_at' => $this->local($c['updated']), 'addresses' => $addresses,
			'default_address' => $addresses[0] ?? null];
	}

	// ---- helpers -----------------------------------------------------------------------------

	public function gid(string $type): string
	{
		return "gid://shopify/$type/" . $this->next++;
	}

	private function legacy(string $gid): int
	{
		return (int)substr($gid, strrpos($gid, '/') + 1);
	}

	private function gidType(string $gid): string
	{
		return preg_match('#^gid://shopify/(\w+)/\d+#', $gid, $m) ? $m[1] : '';
	}

	private function uuid(): string
	{
		$n = $this->next++;
		return sprintf('%08x-%04x-4%03x-8%03x-%012x', $n >> 20 & 0xffffffff, $n & 0xffff, $n >> 4 & 0xfff, $n & 0xfff, $n);
	}

	/** The fake clock as Shopify's DateTime (UTC), or with milliseconds for X-Shopify-Triggered-At. */
	public function iso(bool $millis = false, ?float $at = null): string
	{
		$t = $this->epoch + ($at ?? $this->clock->now);
		return gmdate('Y-m-d\TH:i:s', (int)$t) . ($millis ? sprintf('.%03dZ', (int)round(($t - floor($t)) * 1000) % 1000) : 'Z');
	}

	/** The fake clock in the shop's zone, as REST webhook bodies carry times. */
	private function local(float $at): string
	{
		return (new DateTimeImmutable('@' . (int)($this->epoch + $at)))->setTimezone(new DateTimeZone('Europe/Copenhagen'))->format('c');
	}

	private static function money(float $amount, string $currency = 'DKK'): array
	{
		$s = rtrim(sprintf('%.2f', $amount), '0');
		$m = ['__typename' => 'MoneyV2', 'amount' => str_ends_with($s, '.') ? $s . '0' : $s, 'currencyCode' => $currency];
		return ['__typename' => 'MoneyBag', 'shopMoney' => $m, 'presentmentMoney' => $m];
	}

	private function emptyLevel(): array
	{
		return array_fill_keys(self::QUANTITY_NAMES, 0) + ['updated' => $this->clock->now];
	}

	private static function userError(array $field, string $message, ?string $code = null): array
	{
		return ['field' => $field, 'message' => $message, 'code' => $code, 'elementIndex' => null];
	}

	/** Mutation payload: every field null but those given. */
	private static function payload(array $fields, array $userErrors = []): array
	{
		return $fields + ['userErrors' => $userErrors];
	}

	private function setQuantity(string $domain, string $item, string $location, string $name, int $qty): void
	{
		$s = &$this->shop($domain);
		$level = &$s['levels'][$item][$location];
		$delta = $qty - $level[$name];
		$level[$name] = $qty;
		// available and on_hand move together; committed stays (B20)
		$level[$name === 'available' ? 'on_hand' : 'available'] += $delta;
		$level['updated'] = $this->clock->now;
	}

	/**
	 * Search terms (B23): field:value or field:"value", ANDed. Fails loudly on a field the store
	 * doesn't model for $resource.
	 *
	 * @return list<array{0: string, 1: string}>
	 */
	private function terms(?string $query, string $resource, array $fields): array
	{
		if ($query === null || trim($query) === '') {
			return [];
		}
		$this->uses('B23');
		preg_match_all('/(\w+):(?:"((?:[^"\\\\]|\\\\.)*)"|(\S+))|(\S+)/', $query, $m, PREG_SET_ORDER);
		$out = [];
		foreach ($m as $t) {
			if (($t[4] ?? '') !== '') {
				if (strtoupper($t[4]) === 'AND') {
					continue;
				}
				throw new LogicException("FakeShopifyStore: free-text search \"{$t[4]}\" on $resource is not modelled");
			}
			if (!in_array($t[1], $fields, true)) {
				throw new LogicException("FakeShopifyStore: search term {$t[1]}: on $resource is not modelled");
			}
			$out[] = [$t[1], ($t[2] ?? '') !== '' ? stripcslashes($t[2]) : ($t[3] ?? '')];
		}
		return $out;
	}

	// ---- views (what a document can select) --------------------------------------------------

	private function node(string $domain, ?string $gid): ?array
	{
		if ($gid === null) {
			return null;
		}
		$s = $this->shop($domain);
		return match ($this->gidType($gid)) {
			'Product' => isset($s['products'][$gid]) ? $this->vProduct($domain, $gid) : null,
			'ProductVariant' => isset($s['variants'][$gid]) ? $this->vVariant($domain, $gid) : null,
			'InventoryItem' => isset($s['items'][$gid]) ? $this->vItem($domain, $gid) : null,
			'Location' => isset($s['locations'][$gid]) ? $this->vLocation($domain, $gid) : null,
			'Customer' => isset($s['customers'][$gid]) ? $this->vCustomer($domain, $gid) : null,
			'Order' => isset($s['orders'][$gid]) ? $this->vOrder($domain, $gid) : null,
			'FulfillmentOrder' => isset($s['fulfillment_orders'][$gid]) ? $this->vFulfillmentOrder($domain, $gid) : null,
			'Fulfillment' => isset($s['fulfillments'][$gid]) ? $this->vFulfillment($domain, $gid) : null,
			'Refund' => isset($s['refunds'][$gid]) ? $this->vRefund($domain, $gid) : null,
			'WebhookSubscription' => isset($s['subscriptions'][$gid]) ? $this->vSubscription($domain, $gid) : null,
			default => null,
		};
	}

	/** metafield(namespace:, key:) and metafields(namespace:, keys:) of an owner. */
	private function metafieldFields(string $domain, string $owner): array
	{
		return [
			'metafield' => function (array $a) use ($domain, $owner): ?array {
				$mf = $this->shop($domain)['metafields'][$owner][($a['namespace'] ?? '$app') . '.' . $a['key']] ?? null;
				return $mf === null ? null : $this->vMetafield($mf);
			},
			'metafields' => function (array $a) use ($domain, $owner): array {
				$out = [];
				foreach ($this->shop($domain)['metafields'][$owner] ?? [] as $full => $mf) {
					if ((isset($a['namespace']) && $mf['namespace'] !== $a['namespace']) || (isset($a['keys']) && !in_array($full, $a['keys'], true))) {
						continue;
					}
					$out[] = $this->vMetafield($mf);
				}
				return $out;
			},
		];
	}

	private function vMetafield(array $mf): array
	{
		return ['__typename' => 'Metafield', 'id' => $mf['id'], 'namespace' => $mf['namespace'], 'key' => $mf['key'], 'value' => $mf['value'],
			'type' => $mf['type'], 'ownerType' => $mf['owner_type'], 'compareDigest' => self::digest($mf),
			'jsonValue' => str_starts_with($mf['type'], 'json') ? json_decode($mf['value'], true) : $mf['value'],
			'createdAt' => $this->iso(false, $mf['created']), 'updatedAt' => $this->iso(false, $mf['updated'])];
	}

	/** B32, B36: an opaque digest of the persisted metafield (owner, namespace, key, type, value), so a write of the same value keeps it; a caller only reads it and sends it back. */
	private static function digest(array $mf): string
	{
		return hash('sha256', json_encode([$mf['owner'], $mf['namespace'], $mf['key'], $mf['type'], $mf['value']]));
	}

	/** B32: null matches a metafield that does not exist yet, a digest one whose persisted value is the one it was read from (B36). */
	private function digestMatches(?array $current, ?string $digest): bool
	{
		if ($digest === null || $current === null) {
			return $digest === null && $current === null;
		}
		$this->uses('B36');
		return hash_equals(self::digest($current), $digest);
	}

	private function times(array $row): array
	{
		return ['createdAt' => $this->iso(false, $row['created']), 'updatedAt' => $this->iso(false, $row['updated'] ?? $row['created'])];
	}

	private function vShop(string $domain): array
	{
		$s = $this->shop($domain);
		return ['__typename' => 'Shop', 'id' => $s['id'], 'name' => $s['name'], 'myshopifyDomain' => $domain, 'email' => "shop@$domain",
			'currencyCode' => 'DKK', 'ianaTimezone' => 'Europe/Copenhagen',
			'plan' => ['__typename' => 'ShopPlan', 'publicDisplayName' => 'Development', 'partnerDevelopment' => true, 'shopifyPlus' => false]];
	}

	private function vLocation(string $domain, string $gid): array
	{
		$l = $this->shop($domain)['locations'][$gid];
		return ['__typename' => 'Location', 'id' => $gid, 'legacyResourceId' => (string)$this->legacy($gid), 'name' => $l['name'],
			'isActive' => $l['active'], 'fulfillsOnlineOrders' => true, 'hasActiveInventory' => true, 'shipsInventory' => true,
			'isFulfillmentService' => false, 'activatable' => false, 'deactivatable' => true, 'deletable' => false, 'deactivatedAt' => null,
			'address' => ['__typename' => 'LocationAddress', 'address1' => null, 'city' => null, 'zip' => null, 'countryCode' => 'DK'],
			'inventoryLevel' => fn(array $a): ?array => array_key_exists($gid, $this->shop($domain)['levels'][$a['inventoryItemId']] ?? [])
				? $this->vLevel($domain, $a['inventoryItemId'], $gid) : null,
			'inventoryLevels' => function () use ($domain, $gid): array {
				$out = [];
				foreach ($this->shop($domain)['levels'] as $item => $byLocation) {
					if (isset($byLocation[$gid])) {
						$out[] = $this->vLevel($domain, $item, $gid);
					}
				}
				return $out;
			},
		] + $this->times($l) + $this->metafieldFields($domain, $gid);
	}

	private function vProduct(string $domain, string $gid): array
	{
		$p = $this->shop($domain)['products'][$gid];
		return ['__typename' => 'Product', 'id' => $gid, 'legacyResourceId' => (string)$this->legacy($gid), 'handle' => $p['handle'],
			'title' => $p['title'], 'status' => $p['status'], 'descriptionHtml' => $p['descriptionHtml'], 'vendor' => $p['vendor'],
			'productType' => $p['productType'], 'tags' => $p['tags'],
			'variants' => fn(): array => array_map(fn(string $v): array => $this->vVariant($domain, $v), $this->shop($domain)['products'][$gid]['variants']),
			'variantsCount' => ['__typename' => 'Count', 'count' => count($p['variants']), 'precision' => 'EXACT'],
			'options' => fn(array $a): array => array_map(fn(array $o): array => ['__typename' => 'ProductOption', 'id' => $o['id'], 'name' => $o['name'],
				'position' => $o['position'], 'values' => array_column($o['values'], 'name'),
				'optionValues' => array_map(fn(array $v): array => ['__typename' => 'ProductOptionValue', 'id' => $v['id'], 'name' => $v['name'], 'hasVariants' => true], $o['values'])],
				array_slice($p['options'], 0, $a['first'] ?? null)),
			'totalInventory' => array_sum(array_map(fn(string $v): int => $this->variantAvailable($domain, $v), $p['variants'])),
		] + $this->times($p) + $this->metafieldFields($domain, $gid);
	}

	private function variantAvailable(string $domain, string $variant): int
	{
		$item = $this->shop($domain)['variants'][$variant]['item'];
		return array_sum(array_column($this->shop($domain)['levels'][$item] ?? [], 'available'));
	}

	private function vVariant(string $domain, string $gid): array
	{
		$v = $this->shop($domain)['variants'][$gid];
		$product = $this->shop($domain)['products'][$v['product']];
		$title = implode(' / ', array_column($v['options'], 'value'));
		return ['__typename' => 'ProductVariant', 'id' => $gid, 'legacyResourceId' => (string)$this->legacy($gid), 'sku' => $v['sku'],
			'barcode' => $v['barcode'], 'price' => $v['price'], 'compareAtPrice' => $v['compareAtPrice'], 'title' => $title,
			'displayName' => $product['title'] . ' - ' . $title, 'inventoryPolicy' => $v['inventoryPolicy'], 'taxable' => $v['taxable'],
			'inventoryQuantity' => $this->variantAvailable($domain, $gid),
			'selectedOptions' => array_map(fn(array $o): array => ['__typename' => 'SelectedOption'] + $o, $v['options']),
			'inventoryItem' => fn(): array => $this->vItem($domain, $this->shop($domain)['variants'][$gid]['item']),
			'product' => fn(): array => $this->vProduct($domain, $v['product']),
		] + $this->times($v) + $this->metafieldFields($domain, $gid);
	}

	private function vItem(string $domain, string $gid): array
	{
		$i = $this->shop($domain)['items'][$gid];
		return ['__typename' => 'InventoryItem', 'id' => $gid, 'legacyResourceId' => (string)$this->legacy($gid), 'sku' => $i['sku'],
			'tracked' => $i['tracked'], 'requiresShipping' => true, 'duplicateSkuCount' => 0,
			'unitCost' => $i['cost'] === null ? null : self::money((float)$i['cost'])['shopMoney'],
			'measurement' => ['__typename' => 'InventoryItemMeasurement', 'id' => $gid . 'M',
				'weight' => $i['weight'] === null ? null : ['__typename' => 'Weight'] + $i['weight']],
			'variants' => fn(): array => [$this->vVariant($domain, $i['variant'])],
			'inventoryLevel' => fn(array $a): ?array => array_key_exists($a['locationId'], $this->shop($domain)['levels'][$gid] ?? [])
				? $this->vLevel($domain, $gid, $a['locationId']) : null,
			'inventoryLevels' => fn(): array => array_map(fn(string $loc): array => $this->vLevel($domain, $gid, $loc),
				array_keys($this->shop($domain)['levels'][$gid] ?? [])),
		] + $this->times($i);
	}

	private function vLevel(string $domain, string $item, string $location): array
	{
		$level = $this->shop($domain)['levels'][$item][$location];
		$id = 'gid://shopify/InventoryLevel/' . $this->legacy($location) . '?inventory_item_id=' . $this->legacy($item);
		return ['__typename' => 'InventoryLevel', 'id' => $id, 'isActive' => true, 'canDeactivate' => true, 'deactivationAlert' => null,
			'item' => fn(): array => $this->vItem($domain, $item), 'location' => fn(): array => $this->vLocation($domain, $location),
			'quantities' => fn(array $a): array => array_map(fn(string $name): array => ['__typename' => 'InventoryQuantity',
				'id' => "$id&name=$name", 'name' => $name, 'quantity' => $level[$name], 'updatedAt' => $this->iso(false, $level['updated'])],
				$a['names'] ?? self::QUANTITY_NAMES),
			'createdAt' => $this->iso(false, $level['updated']), 'updatedAt' => $this->iso(false, $level['updated'])];
	}

	private function vCustomer(string $domain, string $gid): array
	{
		$c = $this->shop($domain)['customers'][$gid];
		$name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
		return ['__typename' => 'Customer', 'id' => $gid, 'legacyResourceId' => (string)$this->legacy($gid), 'firstName' => $c['first_name'],
			'lastName' => $c['last_name'], 'displayName' => $name !== '' ? $name : ($c['email'] ?? $c['phone'] ?? ''), 'note' => $c['note'],
			'tags' => $c['tags'], 'state' => 'DISABLED', 'email' => $c['email'], 'phone' => $c['phone'],
			'defaultEmailAddress' => $c['email'] === null ? null : ['__typename' => 'CustomerEmailAddress', 'emailAddress' => $c['email'],
				'marketingState' => 'NOT_SUBSCRIBED', 'marketingOptInLevel' => null, 'marketingUpdatedAt' => null, 'validFormat' => true],
			'defaultPhoneNumber' => $c['phone'] === null ? null : ['__typename' => 'CustomerPhoneNumber', 'phoneNumber' => $c['phone'],
				'marketingState' => 'NOT_SUBSCRIBED', 'marketingOptInLevel' => null, 'marketingUpdatedAt' => null],
			'numberOfOrders' => (string)count(array_filter($this->shop($domain)['orders'], fn(array $o): bool => $o['customer'] === $gid)),
			'defaultAddress' => isset($c['addresses'][0]) ? $this->vAddress($c['addresses'][0]) : null,
			'addressesV2' => array_map(fn(array $a): array => $this->vAddress($a), $c['addresses']),
		] + $this->times($c) + $this->metafieldFields($domain, $gid);
	}

	private function vAddress(array $a): array
	{
		return ['__typename' => 'MailingAddress', 'id' => $a['id'], 'firstName' => $a['firstName'], 'lastName' => $a['lastName'],
			'name' => trim($a['firstName'] . ' ' . $a['lastName']), 'company' => $a['company'], 'address1' => $a['address1'],
			'address2' => $a['address2'], 'city' => $a['city'], 'zip' => $a['zip'], 'country' => $a['country'],
			'countryCodeV2' => $a['countryCode'], 'phone' => $a['phone'], 'province' => null, 'provinceCode' => null];
	}

	private function vOrder(string $domain, string $gid): array
	{
		$o = $this->shop($domain)['orders'][$gid];
		$total = $this->orderTotal($domain, $gid);
		$financial = $this->financialStatus($domain, $gid);
		return ['__typename' => 'Order', 'id' => $gid, 'legacyResourceId' => (string)$this->legacy($gid), 'name' => $o['name'],
			'processedAt' => $this->iso(false, $o['created']), 'cancelledAt' => null, 'closed' => false, 'test' => $o['test'],
			'email' => $o['email'], 'phone' => $o['phone'], 'note' => $o['note'], 'tags' => $o['tags'], 'poNumber' => $o['po_number'],
			'customAttributes' => array_map(fn(array $a): array => ['__typename' => 'Attribute'] + $a, $o['custom_attributes']),
			'sourceName' => $o['source_name'], 'sourceIdentifier' => $o['source_identifier'],
			'displayFinancialStatus' => $financial, 'displayFulfillmentStatus' => $this->fulfillmentStatus($domain, $gid),
			'fullyPaid' => in_array($financial, ['PAID', 'PARTIALLY_REFUNDED', 'REFUNDED'], true), 'unpaid' => $financial === 'PENDING',
			'currencyCode' => $o['currency'], 'totalPriceSet' => self::money($total, $o['currency']),
			'subtotalPriceSet' => self::money($this->orderSubtotal($domain, $gid), $o['currency']),
			'currentTotalPriceSet' => self::money($total - $this->refunded($domain, $gid), $o['currency']),
			'taxesIncluded' => $o['taxes_included'], 'totalTaxSet' => self::money($this->orderTax($domain, $gid), $o['currency']),
			'taxLines' => array_map(fn(array $t): array => $this->vTaxLine($t, $o['currency']), $this->orderTaxLines($domain, $gid)),
			'shippingAddress' => $o['shipping_address'] === null ? null : ['__typename' => 'MailingAddress'] + $o['shipping_address'],
			// orderCreate keeps no billing address
			'billingAddress' => null,
			// orderCreate refuses shippingLines (B34), so an order of the store has none
			'shippingLines' => fn(): array => [],
			'customer' => fn(): ?array => $this->node($domain, $o['customer']),
			'lineItems' => fn(): array => array_map(fn(array $l): array => $this->vLine($domain, $gid, $l), $this->shop($domain)['orders'][$gid]['lines']),
			'fulfillmentOrders' => fn(array $a): array => array_values(array_map(fn(string $fo): array => $this->vFulfillmentOrder($domain, $fo),
				array_filter($this->shop($domain)['orders'][$gid]['fulfillment_orders'], fn(string $fo): bool
					=> !($a['displayable'] ?? false) || $this->shop($domain)['fulfillment_orders'][$fo]['status'] !== 'CANCELLED'))),
			'fulfillments' => fn(array $a): array => array_slice(array_map(fn(string $f): array => $this->vFulfillment($domain, $f),
				$this->shop($domain)['orders'][$gid]['fulfillments']), 0, $a['first'] ?? null),
			'refunds' => fn(array $a): array => array_slice(array_map(fn(string $r): array => $this->vRefund($domain, $r),
				$this->shop($domain)['orders'][$gid]['refunds']), 0, $a['first'] ?? null),
			'transactions' => fn(array $a): array => array_map(fn(array $t): array => ['__typename' => 'OrderTransaction', 'id' => $t['id'],
				'kind' => $t['kind'], 'status' => $t['status'], 'gateway' => $t['gateway'], 'test' => $o['test'],
				'amountSet' => self::money($t['amount'], $o['currency']), 'paymentDetails' => null, 'createdAt' => $this->iso(false, $o['created']),
				'processedAt' => $this->iso(false, $o['created'])], array_slice($o['transactions'], 0, $a['first'] ?? null)),
		] + $this->times($o) + $this->metafieldFields($domain, $gid);
	}

	/** B34: a tax line as given to orderCreate. */
	private function vTaxLine(array $t, string $currency): array
	{
		return ['__typename' => 'TaxLine', 'title' => $t['title'], 'rate' => $t['rate'], 'ratePercentage' => round($t['rate'] * 100, 4),
			'priceSet' => self::money($t['amount'], $currency), 'price' => sprintf('%.2f', $t['amount']), 'channelLiable' => $t['channel_liable'], 'source' => null];
	}

	private function vLine(string $domain, string $order, array $l): array
	{
		$refunded = $this->refundedQuantity($domain, $order, $l['id']);
		$o = $this->shop($domain)['orders'][$order];
		return ['__typename' => 'LineItem', 'id' => $l['id'], 'sku' => $l['sku'], 'name' => $l['title'], 'title' => $l['title'],
			'quantity' => $l['quantity'], 'currentQuantity' => $l['quantity'] - $refunded, 'unfulfilledQuantity' => $this->unfulfilled($domain, $order, $l['id']),
			'taxable' => $l['taxable'], 'requiresShipping' => true, 'originalUnitPriceSet' => self::money($l['price'], $o['currency']),
			// orderCreate refuses discountCode (B34), so a line has no discount
			'originalTotalSet' => self::money($l['price'] * $l['quantity'], $o['currency']), 'discountAllocations' => [],
			'taxLines' => function () use ($l, $o, $order): array {
				if ($o['tax_lines'] !== []) {
					throw new LogicException("FakeShopifyStore: LineItem.taxLines of $order is not modelled: Shopify splits its order-level tax lines across the taxable lines (B34); select Order.taxLines");
				}
				return array_map(fn(array $t): array => $this->vTaxLine($t, $o['currency']), $l['tax_lines']);
			},
			'customAttributes' => array_map(fn(array $a): array => ['__typename' => 'Attribute'] + $a, $l['custom_attributes']),
			'variant' => fn(): ?array => $this->node($domain, $l['variant']),
			'product' => fn(): ?array => $l['variant'] === null ? null : $this->node($domain, $this->shop($domain)['variants'][$l['variant']]['product'] ?? null)];
	}

	private function vFulfillmentOrder(string $domain, string $gid): array
	{
		$fo = $this->shop($domain)['fulfillment_orders'][$gid];
		$o = $this->shop($domain)['orders'][$fo['order']];
		$loc = $this->shop($domain)['locations'][$fo['location']];
		return ['__typename' => 'FulfillmentOrder', 'id' => $gid, 'status' => $fo['status'], 'requestStatus' => $fo['request_status'],
			'orderId' => $fo['order'], 'orderName' => $o['name'], 'orderProcessedAt' => $this->iso(false, $o['created']),
			'fulfillAt' => null, 'fulfillBy' => null, 'fulfillmentHolds' => [], 'destination' => null, 'channelId' => null,
			'assignedLocation' => ['__typename' => 'FulfillmentOrderAssignedLocation', 'name' => $loc['name'], 'countryCode' => 'DK',
				'address1' => null, 'address2' => null, 'city' => null, 'province' => null, 'zip' => null, 'phone' => null,
				'location' => fn(): array => $this->vLocation($domain, $fo['location'])],
			// B05: MOVE where fulfillmentOrderMove would take it
			'supportedActions' => array_map(fn(string $action): array => ['__typename' => 'FulfillmentOrderSupportedAction', 'action' => $action, 'externalUrl' => null],
				in_array($fo['status'], ['OPEN', 'IN_PROGRESS'], true) ? array_merge(['CREATE_FULFILLMENT'],
					in_array($fo['request_status'], self::MOVE_REFUSED_REQUESTS, true) || $fo['progress_reported'] ? [] : ['MOVE']) : []),
			'lineItems' => fn(): array => array_map(fn(array $li): array => $this->vFulfillmentOrderLine($domain, $fo['order'], $li),
				array_values(array_filter($this->shop($domain)['fulfillment_orders'][$gid]['lines'], fn(array $li): bool => $li['total'] > 0))),
			'order' => fn(): array => $this->vOrder($domain, $fo['order']),
			'fulfillments' => fn(): array => array_map(fn(string $f): array => $this->vFulfillment($domain, $f), $fo['fulfillments']),
		] + $this->times($fo);
	}

	private function vFulfillmentOrderLine(string $domain, string $order, array $li): array
	{
		$line = $this->orderLine($domain, $order, $li['line']);
		$item = $line['variant'] === null ? null : $this->shop($domain)['variants'][$line['variant']]['item'];
		return ['__typename' => 'FulfillmentOrderLineItem', 'id' => $li['id'], 'sku' => $line['sku'], 'totalQuantity' => $li['total'],
			'remainingQuantity' => $li['remaining'], 'inventoryItemId' => $item, 'productTitle' => $line['title'], 'variantTitle' => null,
			'vendor' => null, 'requiresShipping' => true, 'warnings' => [],
			'lineItem' => fn(): array => $this->vLine($domain, $order, $line), 'variant' => fn(): ?array => $this->node($domain, $line['variant'])];
	}

	private function vFulfillment(string $domain, string $gid): array
	{
		$f = $this->shop($domain)['fulfillments'][$gid];
		return ['__typename' => 'Fulfillment', 'id' => $gid, 'legacyResourceId' => (string)$this->legacy($gid), 'name' => $f['name'],
			'status' => 'SUCCESS', 'displayStatus' => 'FULFILLED', 'totalQuantity' => array_sum(array_column($f['lines'], 'quantity')),
			'requiresShipping' => true, 'deliveredAt' => null, 'inTransitAt' => null, 'estimatedDeliveryAt' => null,
			'trackingInfo' => fn(): array => $f['tracking'] === null ? [] : [['__typename' => 'FulfillmentTrackingInfo'] + $f['tracking']],
			'location' => fn(): array => $this->vLocation($domain, $f['location']), 'order' => fn(): array => $this->vOrder($domain, $f['order']),
			'fulfillmentLineItems' => fn(): array => array_map(fn(array $fl): array => ['__typename' => 'FulfillmentLineItem', 'id' => $fl['id'],
				'quantity' => $fl['quantity'], 'lineItem' => fn(): array => $this->vLine($domain, $f['order'], $this->orderLine($domain, $f['order'], $fl['line']))], $f['lines']),
		] + $this->times($f);
	}

	private function vRefund(string $domain, string $gid): array
	{
		$r = $this->shop($domain)['refunds'][$gid];
		$o = $this->shop($domain)['orders'][$r['order']];
		$amount = 0.0;
		foreach ($r['lines'] as $rl) {
			$amount += $rl['quantity'] * $this->orderLine($domain, $r['order'], $rl['line'])['price'];
		}
		return ['__typename' => 'Refund', 'id' => $gid, 'legacyResourceId' => (string)$this->legacy($gid), 'note' => $r['note'],
			'processedAt' => $this->iso(false, $r['created']), 'totalRefundedSet' => self::money($amount, $o['currency']),
			'order' => fn(): array => $this->vOrder($domain, $r['order']),
			'refundLineItems' => fn(): array => array_map(fn(array $rl): array => ['__typename' => 'RefundLineItem', 'id' => $rl['id'],
				'quantity' => $rl['quantity'], 'restockType' => $rl['restock_type'],
				'lineItem' => fn(): array => $this->vLine($domain, $r['order'], $this->orderLine($domain, $r['order'], $rl['line']))], $r['lines']),
		] + $this->times($r);
	}

	private function vSubscription(string $domain, string $gid): array
	{
		$sub = $this->shop($domain)['subscriptions'][$gid];
		return ['__typename' => 'WebhookSubscription', 'id' => $gid, 'legacyResourceId' => (string)$this->legacy($gid), 'topic' => $sub['topic'],
			'uri' => $sub['uri'], 'format' => 'JSON', 'includeFields' => [], 'metafieldNamespaces' => [], 'metafields' => [], 'filter' => null, 'name' => null,
			'apiVersion' => ['__typename' => 'ApiVersion', 'handle' => self::API_VERSION, 'displayName' => self::API_VERSION, 'supported' => true],
		] + $this->times($sub);
	}

	// ---- order arithmetic --------------------------------------------------------------------

	private function orderLine(string $domain, string $order, string $line): array
	{
		foreach ($this->shop($domain)['orders'][$order]['lines'] as $l) {
			if ($l['id'] === $line) {
				return $l;
			}
		}
		throw new LogicException("FakeShopifyStore: order $order has no line $line");
	}

	/** The lines at their prices, tax included when the order's prices include it. */
	private function orderSubtotal(string $domain, string $order): float
	{
		$total = 0.0;
		foreach ($this->shop($domain)['orders'][$order]['lines'] as $l) {
			$total += $l['quantity'] * $l['price'];
		}
		return round($total, 2);
	}

	/** B34: the order's tax lines, or its lines' summed by title and rate. */
	private function orderTaxLines(string $domain, string $order): array
	{
		$o = $this->shop($domain)['orders'][$order];
		if ($o['tax_lines'] !== []) {
			return $o['tax_lines'];
		}
		$out = [];
		foreach ($o['lines'] as $l) {
			foreach ($l['tax_lines'] as $t) {
				$k = $t['title'] . '|' . $t['rate'];
				$out[$k] = isset($out[$k]) ? ['amount' => round($out[$k]['amount'] + $t['amount'], 2)] + $out[$k] : $t;
			}
		}
		return array_values($out);
	}

	/** B34: the tax the input gave; none without tax lines. */
	private function orderTax(string $domain, string $order): float
	{
		return round(array_sum(array_column($this->orderTaxLines($domain, $order), 'amount')), 2);
	}

	/** B34: the subtotal, plus the tax unless the prices include it. */
	private function orderTotal(string $domain, string $order): float
	{
		return round($this->orderSubtotal($domain, $order) + ($this->shop($domain)['orders'][$order]['taxes_included'] ? 0.0 : $this->orderTax($domain, $order)), 2);
	}

	private function refundedQuantity(string $domain, string $order, string $line): int
	{
		$n = 0;
		foreach ($this->shop($domain)['orders'][$order]['refunds'] as $r) {
			foreach ($this->shop($domain)['refunds'][$r]['lines'] as $rl) {
				$n += $rl['line'] === $line ? $rl['quantity'] : 0;
			}
		}
		return $n;
	}

	private function refunded(string $domain, string $order): float
	{
		$amount = 0.0;
		foreach ($this->shop($domain)['orders'][$order]['lines'] as $l) {
			$amount += $this->refundedQuantity($domain, $order, $l['id']) * $l['price'];
		}
		return round($amount, 2);
	}

	private function unfulfilled(string $domain, string $order, string $line): int
	{
		$n = 0;
		foreach ($this->shop($domain)['orders'][$order]['fulfillment_orders'] as $fo) {
			foreach ($this->shop($domain)['fulfillment_orders'][$fo]['lines'] as $li) {
				$n += $li['line'] === $line ? $li['remaining'] : 0;
			}
		}
		return $n;
	}

	private function financialStatus(string $domain, string $order): string
	{
		$o = $this->shop($domain)['orders'][$order];
		if ($o['financial'] !== 'PAID') {
			return $o['financial'];
		}
		$refunded = $this->refunded($domain, $order);
		return $refunded <= 0 ? 'PAID' : ($refunded >= $this->orderTotal($domain, $order) ? 'REFUNDED' : 'PARTIALLY_REFUNDED');
	}

	private function fulfillmentStatus(string $domain, string $order): string
	{
		$total = 0;
		$left = 0;
		foreach ($this->shop($domain)['orders'][$order]['lines'] as $l) {
			$total += $l['quantity'];
			$left += $this->unfulfilled($domain, $order, $l['id']);
		}
		return $left === 0 && $total > 0 ? 'FULFILLED' : ($left < $total ? 'PARTIALLY_FULFILLED' : 'UNFULFILLED');
	}

	// ---- queries -----------------------------------------------------------------------------

	private function qShop(string $domain): array
	{
		$this->uses('B30');
		return $this->vShop($domain);
	}

	private function qProduct(string $domain, array $a): ?array
	{
		return $this->gidType($a['id']) === 'Product' ? $this->node($domain, $a['id']) : null;
	}

	private function qProductByIdentifier(string $domain, array $a): ?array
	{
		$id = $a['identifier'];
		if (isset($id['id'])) {
			return $this->qProduct($domain, ['id' => $id['id']]);
		}
		if (!isset($id['handle'])) {
			throw new LogicException('FakeShopifyStore: productByIdentifier by customId is not modelled');
		}
		$gid = $this->productByHandle($domain, $id['handle']);
		return $gid === null ? null : $this->vProduct($domain, $gid);
	}

	private function productByHandle(string $domain, string $handle): ?string
	{
		foreach ($this->shop($domain)['products'] as $gid => $p) {
			if ($p['handle'] === $handle) {
				return $gid;
			}
		}
		return null;
	}

	private function qProducts(string $domain, array $a): array
	{
		$terms = $this->terms($a['query'] ?? null, 'products', ['handle', 'sku', 'status', 'tag']);
		$out = [];
		foreach ($this->shop($domain)['products'] as $gid => $p) {
			foreach ($terms as [$f, $v]) {
				$skus = array_map(fn(string $var): string => (string)$this->shop($domain)['variants'][$var]['sku'], $p['variants']);
				$hit = match ($f) {
					'handle' => $p['handle'] === $v, 'status' => strcasecmp($p['status'], $v) === 0, 'tag' => in_array($v, $p['tags'], true),
					'sku' => (bool)array_filter($skus, fn(string $sku): bool => stripos($sku, $v) === 0),
				};
				if (!$hit) {
					continue 2;
				}
			}
			$out[] = $this->vProduct($domain, $gid);
		}
		return $out;
	}

	private function qProductVariant(string $domain, array $a): ?array
	{
		return $this->gidType($a['id']) === 'ProductVariant' ? $this->node($domain, $a['id']) : null;
	}

	private function qProductVariants(string $domain, array $a): array
	{
		$terms = $this->terms($a['query'] ?? null, 'productVariants', ['sku', 'product_id']);
		$out = [];
		foreach ($this->shop($domain)['variants'] as $gid => $v) {
			foreach ($terms as [$f, $value]) {
				$hit = $f === 'sku' ? stripos((string)$v['sku'], $value) === 0 : (string)$this->legacy($v['product']) === $value;
				if (!$hit) {
					continue 2;
				}
			}
			$out[] = $this->vVariant($domain, $gid);
		}
		return $out;
	}

	private function qInventoryItem(string $domain, array $a): ?array
	{
		return $this->gidType($a['id']) === 'InventoryItem' ? $this->node($domain, $a['id']) : null;
	}

	private function qInventoryLevel(string $domain, array $a): ?array
	{
		if (!preg_match('#^gid://shopify/InventoryLevel/(\d+)\?inventory_item_id=(\d+)$#', $a['id'], $m)) {
			return null;
		}
		[$location, $item] = ["gid://shopify/Location/{$m[1]}", "gid://shopify/InventoryItem/{$m[2]}"];
		return array_key_exists($location, $this->shop($domain)['levels'][$item] ?? []) ? $this->vLevel($domain, $item, $location) : null;
	}

	private function qLocation(string $domain, array $a): ?array
	{
		$id = $a['id'] ?? $this->locations($domain)[0];
		return $this->gidType($id) === 'Location' ? $this->node($domain, $id) : null;
	}

	private function qLocations(string $domain, array $a): array
	{
		$out = [];
		foreach ($this->shop($domain)['locations'] as $gid => $l) {
			if ($l['active'] || !empty($a['includeInactive'])) {
				$out[] = $this->vLocation($domain, $gid);
			}
		}
		return $out;
	}

	private function qOrder(string $domain, array $a): ?array
	{
		return $this->gidType($a['id']) === 'Order' ? $this->node($domain, $a['id']) : null;
	}

	private function qOrders(string $domain, array $a): array
	{
		$terms = $this->terms($a['query'] ?? null, 'orders', ['name', 'tag', 'source_identifier']);
		$out = [];
		foreach ($this->shop($domain)['orders'] as $gid => $o) {
			foreach ($terms as [$f, $v]) {
				$hit = match ($f) {
					'name' => ltrim($o['name'], '#') === ltrim($v, '#'), 'tag' => in_array($v, $o['tags'], true),
					'source_identifier' => $o['source_identifier'] === $v,
				};
				if (!$hit) {
					continue 2;
				}
			}
			$out[] = $this->vOrder($domain, $gid);
		}
		return $out;
	}

	private function qFulfillmentOrder(string $domain, array $a): ?array
	{
		return $this->gidType($a['id']) === 'FulfillmentOrder' ? $this->node($domain, $a['id']) : null;
	}

	private function qFulfillmentOrders(string $domain, array $a): array
	{
		$terms = $this->terms($a['query'] ?? null, 'fulfillmentOrders', ['status', 'assigned_location_id']);
		$out = [];
		foreach ($this->shop($domain)['fulfillment_orders'] as $gid => $fo) {
			if (empty($a['includeClosed']) && in_array($fo['status'], ['CLOSED', 'CANCELLED'], true)) {
				continue;
			}
			foreach ($terms as [$f, $v]) {
				$hit = $f === 'status' ? strcasecmp($fo['status'], $v) === 0 : (string)$this->legacy($fo['location']) === $v;
				if (!$hit) {
					continue 2;
				}
			}
			$out[] = $this->vFulfillmentOrder($domain, $gid);
		}
		return $out;
	}

	private function qCustomer(string $domain, array $a): ?array
	{
		return $this->gidType($a['id']) === 'Customer' ? $this->node($domain, $a['id']) : null;
	}

	private function qCustomers(string $domain, array $a): array
	{
		$terms = $this->terms($a['query'] ?? null, 'customers', ['email', 'phone', 'tag']);
		$out = [];
		foreach ($this->shop($domain)['customers'] as $gid => $c) {
			foreach ($terms as [$f, $v]) {
				$hit = $f === 'tag' ? in_array($v, $c['tags'], true) : strcasecmp((string)$c[$f], $v) === 0;
				if (!$hit) {
					continue 2;
				}
			}
			$out[] = $this->vCustomer($domain, $gid);
		}
		return $out;
	}

	private function qCustomerByIdentifier(string $domain, array $a): ?array
	{
		$id = $a['identifier'];
		if (isset($id['id'])) {
			return $this->qCustomer($domain, ['id' => $id['id']]);
		}
		$field = isset($id['emailAddress']) ? 'email' : (isset($id['phoneNumber']) ? 'phone' : null);
		if ($field === null) {
			throw new LogicException('FakeShopifyStore: customerByIdentifier by customId is not modelled');
		}
		$gid = $this->customerBy($domain, $field, $id[$field === 'email' ? 'emailAddress' : 'phoneNumber']);
		return $gid === null ? null : $this->vCustomer($domain, $gid);
	}

	private function customerBy(string $domain, string $field, ?string $value, ?string $except = null): ?string
	{
		if ($value === null || $value === '') {
			return null;
		}
		foreach ($this->shop($domain)['customers'] as $gid => $c) {
			if ($gid !== $except && strcasecmp((string)$c[$field], $value) === 0) {
				return $gid;
			}
		}
		return null;
	}

	private function qWebhookSubscription(string $domain, array $a): ?array
	{
		return $this->gidType($a['id']) === 'WebhookSubscription' ? $this->node($domain, $a['id']) : null;
	}

	private function qWebhookSubscriptions(string $domain, array $a): array
	{
		$out = [];
		foreach ($this->shop($domain)['subscriptions'] as $gid => $sub) {
			if ((isset($a['topics']) && !in_array($sub['topic'], $a['topics'], true)) || (isset($a['uri']) && $sub['uri'] !== $a['uri'])) {
				continue;
			}
			$out[] = $this->vSubscription($domain, $gid);
		}
		return $out;
	}

	private function qNode(string $domain, array $a): ?array
	{
		return $this->node($domain, $a['id']);
	}

	private function qNodes(string $domain, array $a): array
	{
		return array_map(fn(string $id): ?array => $this->node($domain, $id), $a['ids']);
	}

	private function qBulkOperations(string $domain, array $a): array
	{
		return array_map(fn(string $gid): array => $this->vBulk($domain, $gid), array_reverse(array_keys($this->shop($domain)['bulk'] ?? [])));
	}

	/** B38: the first look at a new operation finds it RUNNING, the next COMPLETED. */
	private function qBulkOperation(string $domain, array $a): ?array
	{
		$s = &$this->shop($domain);
		if (!isset($s['bulk'][$a['id']])) {
			return null;
		}
		$this->uses('B38');
		$view = $this->vBulk($domain, $a['id']);
		$s['bulk'][$a['id']]['status'] = 'COMPLETED';
		return $view;
	}

	private function vBulk(string $domain, string $gid): array
	{
		$op = $this->shop($domain)['bulk'][$gid];
		$done = $op['status'] === 'COMPLETED';
		return ['__typename' => 'BulkOperation', 'id' => $gid, 'status' => $op['status'], 'type' => 'QUERY', 'query' => $op['query'], 'errorCode' => null,
			'objectCount' => (string)$op['lines'], 'rootObjectCount' => (string)$op['items'], 'fileSize' => $done ? (string)strlen($op['file']) : null,
			'url' => $done && $op['file'] !== '' ? $op['url'] : null, 'partialDataUrl' => null, 'completedAt' => null, 'createdAt' => $this->iso()];
	}

	// ---- mutations: catalogue and stock ------------------------------------------------------

	/**
	 * B38: a bulk read of every inventory item's available levels, which is all it models. The file is
	 * written when the operation starts, so a later change isn't in it: one line per item, then
	 * one per level with the item as __parentId.
	 */
	private function mBulkOperationRunQuery(string $domain, array $a): array
	{
		if (!preg_match('/^\{\s*inventoryItems\s*\{\s*edges\s*\{\s*node\s*\{\s*id\b.*inventoryLevels.*location\s*\{\s*id\s*\}.*quantities\(names:\s*\["available"\]\)/s', $a['query'])) {
			throw new LogicException('FakeShopifyStore: this bulk query is not modelled (B38): ' . $a['query']);
		}
		$this->uses('B38');
		$s = &$this->shop($domain);
		$lines = [];
		foreach (array_keys($s['items']) as $item) {
			$lines[] = json_encode(['id' => $item]);
			foreach ($s['levels'][$item] ?? [] as $location => $level) {
				$lines[] = json_encode(['location' => ['id' => $location], 'quantities' => [['name' => 'available', 'quantity' => $level['available']]], '__parentId' => $item]);
			}
		}
		$gid = $this->gid('BulkOperation');
		$s['bulk'][$gid] = ['status' => 'RUNNING', 'query' => $a['query'], 'url' => 'https://storage.googleapis.com/fake-bulk/' . $this->legacy($gid) . '.jsonl',
			'file' => $lines === [] ? '' : implode("\n", $lines) . "\n", 'lines' => count($lines), 'items' => count($s['items'])];
		return self::payload(['bulkOperation' => $this->vBulk($domain, $gid)]);
	}

	private function mProductSet(string $domain, array $a): array
	{
		$identifier = $a['identifier'] ?? [];
		if (isset($identifier['customId'])) {
			throw new LogicException('FakeShopifyStore: productSet by customId is not modelled');
		}
		$this->uses('B22');
		if (isset($identifier['id']) && !array_key_exists($identifier['id'], $this->shop($domain)['products'])) {
			return self::payload(['product' => null, 'productSetOperation' => null],
				[self::userError(['identifier', 'id'], 'Product does not exist', 'PRODUCT_DOES_NOT_EXIST')]);
		}
		foreach ($a['input']['variants'] ?? [] as $i => $v) {
			if (isset($v['id']) && ($this->shop($domain)['variants'][$v['id']]['product'] ?? null) !== ($identifier['id'] ?? $this->productByHandle($domain, (string)($identifier['handle'] ?? '')))) {
				return self::payload(['product' => null, 'productSetOperation' => null],
					[self::userError(['input', 'variants', (string)$i, 'id'], 'Product variant does not exist', 'PRODUCT_VARIANT_DOES_NOT_EXIST')]);
			}
		}
		$gid = $this->applyProductSet($domain, $identifier, $a['input'], true);
		return self::payload(['product' => $this->vProduct($domain, $gid), 'productSetOperation' => null]);
	}

	/** B37: the variants' price and compareAtPrice; every entry is checked before anything changes. */
	private function mProductVariantsBulkUpdate(string $domain, array $a): array
	{
		$this->uses('B37');
		$s = &$this->shop($domain);
		if (!isset($s['products'][$a['productId']])) {
			return self::payload(['product' => null, 'productVariants' => null], [self::userError(['productId'], 'Product does not exist', 'PRODUCT_DOES_NOT_EXIST')]);
		}
		foreach ($a['variants'] as $i => $v) {
			if (array_diff(array_keys($v), ['id', 'price', 'compareAtPrice'])) {
				throw new LogicException('FakeShopifyStore: productVariantsBulkUpdate models id, price and compareAtPrice only');
			}
			if (($s['variants'][$v['id'] ?? '']['product'] ?? null) !== $a['productId']) {
				return self::payload(['product' => null, 'productVariants' => null],
					[self::userError(['variants', (string)$i, 'id'], 'Product variant does not exist', 'PRODUCT_VARIANT_DOES_NOT_EXIST')]);
			}
			if (isset($v['price']) && (float)$v['price'] < 0) {
				return self::payload(['product' => null, 'productVariants' => null],
					[self::userError(['variants', (string)$i, 'price'], 'Price must be greater than or equal to 0', 'GREATER_THAN_OR_EQUAL_TO')]);
			}
		}
		foreach ($a['variants'] as $v) {
			foreach (['price', 'compareAtPrice'] as $f) {
				if (array_key_exists($f, $v)) {
					$s['variants'][$v['id']][$f] = $v[$f];
				}
			}
			$s['variants'][$v['id']]['updated'] = $this->clock->now;
		}
		return self::payload(['product' => $this->vProduct($domain, $a['productId']),
			'productVariants' => array_map(fn(array $v): array => $this->vVariant($domain, $v['id']), $a['variants'])]);
	}

	/** productSet's effect (B22); fires products/create or products/update when $fire. */
	private function applyProductSet(string $domain, array $identifier, array $input, bool $fire): string
	{
		$s = &$this->shop($domain);
		$gid = $identifier['id'] ?? (isset($identifier['handle']) ? $this->productByHandle($domain, $identifier['handle']) : null);
		$created = $gid === null;
		if ($created) {
			$gid = $this->gid('Product');
			$s['products'][$gid] = ['handle' => $input['handle'] ?? $identifier['handle'] ?? 'product-' . $this->legacy($gid), 'title' => '',
				'status' => 'ACTIVE', 'descriptionHtml' => '', 'vendor' => $s['name'], 'productType' => '', 'tags' => [], 'options' => [],
				'variants' => [], 'created' => $this->clock->now];
		}
		$p = &$s['products'][$gid];
		foreach (['title', 'handle', 'status', 'descriptionHtml', 'vendor', 'productType', 'tags'] as $f) {
			if (array_key_exists($f, $input)) {
				$p[$f] = $input[$f];
			}
		}
		if (!isset($input['productOptions']) && $created) {
			// a product always has an option; Shopify names the only one Title (ASSUMED for a variant list without productOptions)
			$input['productOptions'] = [];
			foreach ($input['variants'] ?? [] as $v) {
				foreach ($v['optionValues'] ?? [['optionName' => 'Title', 'name' => 'Default Title']] as $ov) {
					$name = $ov['optionName'] ?? 'Title';
					$i = array_search($name, array_column($input['productOptions'], 'name'), true);
					if ($i === false) {
						$input['productOptions'][] = ['name' => $name, 'values' => []];
						$i = count($input['productOptions']) - 1;
					}
					if (!in_array($ov['name'] ?? '', array_column($input['productOptions'][$i]['values'], 'name'), true)) {
						$input['productOptions'][$i]['values'][] = ['name' => $ov['name'] ?? ''];
					}
				}
			}
		}
		if (isset($input['productOptions'])) {
			$p['options'] = [];
			foreach ($input['productOptions'] as $i => $o) {
				$p['options'][] = ['id' => $o['id'] ?? $this->gid('ProductOption'), 'name' => $o['name'], 'position' => $i + 1,
					'values' => array_map(fn(array $v): array => ['id' => $v['id'] ?? $this->gid('ProductOptionValue'), 'name' => $v['name']], $o['values'] ?? [])];
			}
		}
		if (isset($input['variants'])) {
			$keep = [];
			foreach ($input['variants'] as $v) {
				$keep[] = $this->applyVariant($domain, $gid, $v);
			}
			foreach (array_diff($p['variants'], $keep) as $gone) {
				$item = $s['variants'][$gone]['item'];
				unset($s['variants'][$gone], $s['items'][$item], $s['levels'][$item], $s['metafields'][$gone]);
			}
			$p['variants'] = $keep;
		}
		foreach ($input['metafields'] ?? [] as $mf) {
			$this->writeMetafield($domain, $gid, $mf);
		}
		$p['updated'] = $this->clock->now;
		unset($p);
		if ($fire) {
			$product = $s['products'][$gid];
			$this->fire($domain, $created ? 'products/create' : 'products/update', ['id' => $this->legacy($gid), 'admin_graphql_api_id' => $gid,
				'title' => $product['title'], 'handle' => $product['handle'], 'status' => strtolower($product['status']), 'tags' => implode(', ', $product['tags']),
				'variants' => array_map(fn(string $v): array => ['id' => $this->legacy($v), 'sku' => $s['variants'][$v]['sku'],
					'inventory_item_id' => $this->legacy($s['variants'][$v]['item'])], $product['variants'])]);
		}
		return $gid;
	}

	private function applyVariant(string $domain, string $product, array $v): string
	{
		$s = &$this->shop($domain);
		$gid = $v['id'] ?? null;
		if ($gid === null) {
			$gid = $this->gid('ProductVariant');
			$item = $this->gid('InventoryItem');
			$s['variants'][$gid] = ['product' => $product, 'item' => $item, 'sku' => null, 'barcode' => null, 'price' => '0.00',
				'compareAtPrice' => null, 'inventoryPolicy' => 'DENY', 'taxable' => true, 'options' => [], 'created' => $this->clock->now];
			$s['items'][$item] = ['variant' => $gid, 'sku' => null, 'tracked' => true, 'cost' => null, 'weight' => null, 'created' => $this->clock->now];
			foreach (array_keys($s['locations']) as $location) {
				$s['levels'][$item][$location] = $this->emptyLevel();
			}
		}
		$row = &$s['variants'][$gid];
		foreach (['sku', 'barcode', 'price', 'compareAtPrice', 'inventoryPolicy', 'taxable'] as $f) {
			if (array_key_exists($f, $v)) {
				$row[$f] = $v[$f];
			}
		}
		if (isset($v['optionValues'])) {
			$row['options'] = array_map(fn(array $o): array => ['name' => $o['optionName'] ?? '', 'value' => $o['name'] ?? ''], $v['optionValues']);
		}
		$row['updated'] = $this->clock->now;
		$item = &$s['items'][$row['item']];
		$item['sku'] = $row['sku'];
		if (isset($v['inventoryItem'])) {
			$ii = $v['inventoryItem'];
			$item['tracked'] = $ii['tracked'] ?? $item['tracked'];
			$item['cost'] = $ii['cost'] ?? $item['cost'];
			if (isset($ii['measurement']['weight'])) {
				$item['weight'] = ['value' => (float)$ii['measurement']['weight']['value'], 'unit' => $ii['measurement']['weight']['unit']];
			}
		}
		$itemGid = $row['item'];
		unset($row, $item);
		foreach ($v['inventoryQuantities'] ?? [] as $q) {
			$this->setQuantity($domain, $itemGid, $q['locationId'], $q['name'], (int)$q['quantity']);
		}
		foreach ($v['metafields'] ?? [] as $mf) {
			$this->writeMetafield($domain, $gid, $mf);
		}
		return $gid;
	}

	private function mInventorySetQuantities(string $domain, array $a): array
	{
		$this->uses('B20');
		$in = $a['input'];
		$s = &$this->shop($domain);
		$errors = [];
		if (!in_array($in['name'], ['available', 'on_hand'], true)) {
			$errors[] = self::userError(['input', 'name'], "The quantity name must be either 'available' or 'on_hand'.", 'INVALID_NAME');
		}
		foreach ($in['quantities'] as $i => $q) {
			$path = ['input', 'quantities', (string)$i];
			if (!isset($s['items'][$q['inventoryItemId']])) {
				$errors[] = self::userError(array_merge($path, ['inventoryItemId']), 'The specified inventory item could not be found.', 'INVALID_INVENTORY_ITEM');
			} elseif (!isset($s['locations'][$q['locationId']])) {
				$errors[] = self::userError(array_merge($path, ['locationId']), 'The specified location could not be found.', 'INVALID_LOCATION');
			} elseif (!array_key_exists('changeFromQuantity', $q)) {
				$errors[] = self::userError(array_merge($path, ['changeFromQuantity']), 'changeFromQuantity is required; send null to skip the check.');
			} elseif ($q['changeFromQuantity'] !== null && $errors === [] && isset($s['levels'][$q['inventoryItemId']][$q['locationId']])
				&& $s['levels'][$q['inventoryItemId']][$q['locationId']][$in['name']] !== $q['changeFromQuantity']) {
				$errors[] = self::userError(array_merge($path, ['changeFromQuantity']), 'The specified compare quantity does not match the current quantity.', 'CHANGE_FROM_QUANTITY_STALE');
			}
		}
		if ($errors) {
			return self::payload(['inventoryAdjustmentGroup' => null], $errors);
		}
		$changes = [];
		foreach ($in['quantities'] as $q) {
			if (!isset($s['levels'][$q['inventoryItemId']][$q['locationId']])) {
				$s['hidden'][$q['inventoryItemId']][$q['locationId']] = (int)$q['quantity'];
				continue;
			}
			$before = $this->shop($domain)['levels'][$q['inventoryItemId']][$q['locationId']];
			$this->setQuantity($domain, $q['inventoryItemId'], $q['locationId'], $in['name'], (int)$q['quantity']);
			$after = $this->shop($domain)['levels'][$q['inventoryItemId']][$q['locationId']];
			foreach (['available', 'on_hand'] as $name) {
				if ($after[$name] !== $before[$name]) {
					$changes[] = ['__typename' => 'InventoryChange', 'name' => $name, 'delta' => $after[$name] - $before[$name], 'quantityAfterChange' => $after[$name],
						'item' => fn(): array => $this->vItem($domain, $q['inventoryItemId']), 'location' => fn(): array => $this->vLocation($domain, $q['locationId'])];
				}
			}
			$this->fire($domain, 'inventory_levels/update', ['inventory_item_id' => $this->legacy($q['inventoryItemId']),
				'location_id' => $this->legacy($q['locationId']), 'available' => $after['available'], 'updated_at' => $this->local($this->clock->now)]);
		}
		return self::payload(['inventoryAdjustmentGroup' => ['__typename' => 'InventoryAdjustmentGroup', 'id' => $this->gid('InventoryAdjustmentGroup'),
			'reason' => $in['reason'], 'referenceDocumentUri' => $in['referenceDocumentUri'] ?? null, 'createdAt' => $this->iso(), 'changes' => $changes]]);
	}

	/** B35: stocks an item at a location; available and on_hand start at zero unless given, or at a quantity set while it wasn't stocked (B20). */
	private function mInventoryActivate(string $domain, array $a): array
	{
		$s = &$this->shop($domain);
		[$item, $location] = [$a['inventoryItemId'], $a['locationId']];
		if (!isset($s['items'][$item]) || !isset($s['locations'][$location])) {
			$this->uses('B29');
			return self::payload(['inventoryLevel' => null], [self::userError([isset($s['items'][$item]) ? 'locationId' : 'inventoryItemId'], 'The inventory item or location could not be found.')]);
		}
		$this->uses('B35');
		if (!isset($s['levels'][$item][$location])) {
			$available = $a['available'] ?? $a['onHand'] ?? $s['hidden'][$item][$location] ?? 0;
			unset($s['hidden'][$item][$location]);
			if (isset($a['available'], $a['onHand']) && $a['available'] !== $a['onHand']) {
				throw new LogicException('FakeShopifyStore: inventoryActivate with available and onHand apart is not modelled (B35)');
			}
			$s['levels'][$item][$location] = ['available' => (int)$available, 'on_hand' => (int)$available] + $this->emptyLevel();
		} elseif (isset($a['available'])) {
			return self::payload(['inventoryLevel' => $this->vLevel($domain, $item, $location)],
				[self::userError(['available'], 'Not allowed to set available quantity when the item is already active at the location.')]);
		}
		return self::payload(['inventoryLevel' => $this->vLevel($domain, $item, $location)]);
	}

	// ---- mutations: orders and fulfilment ----------------------------------------------------

	private function mOrderCreate(string $domain, array $a): array
	{
		$in = $a['order'];
		$options = $a['options'] ?? [];
		$behaviour = $options['inventoryBehaviour'] ?? 'BYPASS';
		$this->uses('B01');
		$this->uses('B02');
		$this->uses('B34');
		foreach (['shippingLines', 'discountCode', 'presentmentCurrency'] as $unmodelled) {
			if (!empty($in[$unmodelled])) {
				throw new LogicException("FakeShopifyStore: orderCreate's $unmodelled is not modelled: it changes the order's amounts (B34)");
			}
		}
		$s = &$this->shop($domain);
		$lines = [];
		foreach ($in['lineItems'] ?? [] as $i => $li) {
			$variant = $li['variantId'] ?? null;
			if ($variant !== null && !isset($s['variants'][$variant])) {
				return self::payload(['order' => null], [self::userError(['order', 'lineItems', (string)$i, 'variantId'], 'Variant does not exist', 'INVALID')]);
			}
			if ((int)$li['quantity'] < 1) {
				return self::payload(['order' => null], [self::userError(['order', 'lineItems', (string)$i, 'quantity'], 'Quantity must be greater than 0', 'INVALID')]);
			}
			$v = $variant === null ? null : $s['variants'][$variant];
			$price = isset($li['priceSet']['shopMoney']['amount']) ? (float)$li['priceSet']['shopMoney']['amount'] : (float)($v['price'] ?? 0);
			$lines[] = ['id' => $this->gid('LineItem'), 'variant' => $variant, 'sku' => $li['sku'] ?? $v['sku'] ?? null,
				'title' => $li['title'] ?? ($v === null ? 'Custom item' : $s['products'][$v['product']]['title']), 'quantity' => (int)$li['quantity'],
				'price' => $price, 'taxable' => (bool)($li['taxable'] ?? $v['taxable'] ?? true), 'tax_lines' => self::taxLines($li['taxLines'] ?? []),
				'custom_attributes' => array_map(fn(array $p): array => ['key' => $p['name'], 'value' => $p['value']], $li['properties'] ?? [])];
		}
		if ($lines === []) {
			return self::payload(['order' => null], [self::userError(['order', 'lineItems'], 'Line items must have at least one line item', 'INVALID')]);
		}
		$taxLines = self::taxLines($in['taxLines'] ?? []);
		if ($taxLines !== [] && array_filter(array_column($lines, 'tax_lines'))) {
			return self::payload(['order' => null], [self::userError(['order', 'taxLines'], 'Tax lines can be given on the order or on its line items, not both.', 'INVALID')]);
		}
		if ($taxLines !== [] && !in_array(true, array_column($lines, 'taxable'), true)) {
			throw new LogicException('FakeShopifyStore: order-level tax lines on an order without a taxable line are not modelled (B34)');
		}
		if ($behaviour === 'DECREMENT_OBEYING_POLICY') {
			foreach ($lines as $i => $l) {
				if ($l['variant'] !== null && $s['variants'][$l['variant']]['inventoryPolicy'] === 'DENY' && $this->variantAvailable($domain, $l['variant']) < $l['quantity']) {
					return self::payload(['order' => null], [self::userError(['order', 'lineItems', (string)$i], 'Unable to reserve inventory', 'INVENTORY_CLAIM_FAILED')]);
				}
			}
		}
		$customer = null;
		$newCustomer = false;
		if (isset($in['customer']['toAssociate'])) {
			$assoc = $in['customer']['toAssociate'];
			$customer = isset($assoc['id']) ? (isset($s['customers'][$assoc['id']]) ? $assoc['id'] : null) : $this->customerBy($domain, 'email', $assoc['email'] ?? null);
			if ($customer === null) {
				return self::payload(['order' => null], [self::userError(['order', 'customer', 'toAssociate'], 'Customer does not exist', 'INVALID')]);
			}
		} elseif (isset($in['customer']['toUpsert'])) {
			$up = $in['customer']['toUpsert'];
			$customer = $up['id'] ?? $this->customerBy($domain, 'email', $up['email'] ?? null) ?? $this->customerBy($domain, 'phone', $up['phone'] ?? null);
			if ($customer === null) {
				$made = $this->writeCustomer($domain, null, ['email' => $up['email'] ?? null, 'phone' => $up['phone'] ?? null,
					'firstName' => $up['firstName'] ?? null, 'lastName' => $up['lastName'] ?? null, 'tags' => $up['tags'] ?? [], 'note' => $up['note'] ?? null,
					'addresses' => $up['addresses'] ?? []]);
				if (isset($made['errors'])) {
					return self::payload(['order' => null], $made['errors']);
				}
				[$customer, $newCustomer] = [$made['gid'], true];
			}
		}
		$gid = $this->gid('Order');
		$paid = 0.0;
		$transactions = [];
		foreach ($in['transactions'] ?? [] as $t) {
			$amount = (float)$t['amountSet']['shopMoney']['amount'];
			$transactions[] = ['id' => $this->gid('OrderTransaction'), 'kind' => $t['kind'] ?? 'SALE', 'status' => $t['status'] ?? 'SUCCESS',
				'gateway' => $t['gateway'] ?? 'manual', 'amount' => $amount];
			$paid += in_array($t['kind'] ?? 'SALE', ['SALE', 'CAPTURE'], true) && ($t['status'] ?? 'SUCCESS') === 'SUCCESS' ? $amount : 0;
		}
		$taxesIncluded = (bool)($in['taxesIncluded'] ?? false);
		$tax = array_sum(array_column(array_merge($taxLines, ...array_column($lines, 'tax_lines')), 'amount'));
		$total = array_sum(array_map(fn(array $l): float => $l['quantity'] * $l['price'], $lines)) + ($taxesIncluded ? 0 : $tax);
		$s['order_number']++;
		$s['orders'][$gid] = [
			'name' => $in['name'] ?? '#' . $s['order_number'], 'email' => $in['email'] ?? ($customer ? $s['customers'][$customer]['email'] : null),
			'phone' => $in['phone'] ?? null, 'note' => $in['note'] ?? null, 'tags' => array_values(array_unique(array_map('trim', $in['tags'] ?? []))),
			'custom_attributes' => array_map(fn(array $c): array => ['key' => $c['key'], 'value' => $c['value']], $in['customAttributes'] ?? []),
			'source_name' => $in['sourceName'] ?? null, 'source_identifier' => $in['sourceIdentifier'] ?? null, 'po_number' => $in['poNumber'] ?? null,
			'currency' => $in['currency'] ?? 'DKK', 'test' => (bool)($in['test'] ?? false), 'taxes_included' => $taxesIncluded, 'tax_lines' => $taxLines,
			'financial' => $in['financialStatus'] ?? ($transactions === [] ? 'PENDING' : ($paid + 0.005 >= $total ? 'PAID' : 'PARTIALLY_PAID')),
			'shipping_address' => isset($in['shippingAddress']) ? array_intersect_key($in['shippingAddress'], array_flip(['address1', 'city', 'zip', 'firstName', 'lastName', 'phone'])) + ['countryCodeV2' => $in['shippingAddress']['countryCode'] ?? 'DK'] : null,
			'customer' => $customer, 'lines' => $lines, 'transactions' => $transactions, 'inventory_behaviour' => $behaviour,
			'fulfillment_orders' => [], 'fulfillments' => [], 'refunds' => [], 'created' => $this->clock->now, 'updated' => $this->clock->now,
		];
		foreach ($in['metafields'] ?? [] as $mf) {
			$this->writeMetafield($domain, $gid, $mf);
		}
		$this->route($domain, $gid);
		if (!empty($options['sendReceipt'])) {
			$s['receipts'][] = ['order' => $gid, 'kind' => 'order'];
		}
		if ($newCustomer) {
			$this->uses('B15');
			$this->fire($domain, 'customers/create', $this->restCustomer($domain, $customer));
		}
		if (($in['fulfillmentStatus'] ?? null) === 'FULFILLED') {
			$this->fulfil($domain, $gid, array_map(fn(string $fo): array => ['fulfillmentOrderId' => $fo], $s['orders'][$gid]['fulfillment_orders']), null, false);
		}
		$this->uses('B15');
		$this->fire($domain, 'orders/create', $this->restOrder($domain, $gid));
		if (in_array($this->financialStatus($domain, $gid), ['PAID'], true)) {
			$this->fire($domain, 'orders/paid', $this->restOrder($domain, $gid));
		}
		return self::payload(['order' => $this->vOrder($domain, $gid)]);
	}

	/**
	 * B34: orderCreate tax lines with their amounts; a tax line without priceSet fails loudly, as
	 * how Shopify computes one from its rate is not known.
	 *
	 * @return list<array{title: string, rate: float, amount: float, channel_liable: ?bool}>
	 */
	private static function taxLines(array $input): array
	{
		$out = [];
		foreach ($input as $t) {
			if (!isset($t['priceSet']['shopMoney']['amount'])) {
				throw new LogicException('FakeShopifyStore: a tax line without priceSet is not modelled (B34): give its amount');
			}
			$out[] = ['title' => (string)$t['title'], 'rate' => (float)$t['rate'], 'amount' => round((float)$t['priceSet']['shopMoney']['amount'], 2),
				'channel_liable' => $t['channelLiable'] ?? null];
		}
		return $out;
	}

	/** B18: fulfillment orders per assigned location; B01: decrement behaviours commit stock there. */
	private function route(string $domain, string $order): void
	{
		$this->uses('B18');
		$s = &$this->shop($domain);
		$o = &$s['orders'][$order];
		$byLocation = [];
		foreach ($o['lines'] as $l) {
			$location = array_key_first($s['locations']);
			$committed = 0;
			if ($l['variant'] !== null) {
				$item = $s['variants'][$l['variant']]['item'];
				foreach ($s['locations'] as $loc => $row) {
					if ($row['active'] && ($s['levels'][$item][$loc]['available'] ?? 0) >= $l['quantity']) {
						$location = $loc;
						break;
					}
				}
				if ($o['inventory_behaviour'] !== 'BYPASS' && isset($s['levels'][$item][$location])) {
					$this->commit($domain, $item, $location, $l['quantity']);
					$committed = $l['quantity'];
				}
			}
			// committed: what this line holds of the location's committed quantity (B01, B17, B31)
			$byLocation[$location][] = ['id' => $this->gid('FulfillmentOrderLineItem'), 'line' => $l['id'], 'total' => $l['quantity'], 'remaining' => $l['quantity'],
				'committed' => $committed];
		}
		foreach ($byLocation as $location => $lines) {
			$fo = $this->gid('FulfillmentOrder');
			$s['fulfillment_orders'][$fo] = ['order' => $order, 'location' => $location, 'status' => 'OPEN', 'request_status' => 'UNSUBMITTED',
				'progress_reported' => false, 'lines' => $lines, 'fulfillments' => [], 'created' => $this->clock->now];
			$o['fulfillment_orders'][] = $fo;
		}
	}

	/** Moves $qty of an item from available to committed at a location (negative: back). */
	private function commit(string $domain, string $item, string $location, int $qty): void
	{
		$level = &$this->shop($domain)['levels'][$item][$location];
		$level['available'] -= $qty;
		$level['committed'] += $qty;
		$level['updated'] = $this->clock->now;
	}

	private function mOrderUpdate(string $domain, array $a): array
	{
		$in = $a['input'];
		$s = &$this->shop($domain);
		if (!isset($s['orders'][$in['id']])) {
			$this->uses('B29');
			return self::payload(['order' => null], [self::userError(['id'], 'Order does not exist')]);
		}
		$this->uses('B26');
		$o = &$s['orders'][$in['id']];
		foreach (['note' => 'note', 'email' => 'email', 'phone' => 'phone', 'poNumber' => 'po_number'] as $field => $col) {
			if (array_key_exists($field, $in)) {
				$o[$col] = $in[$field];
			}
		}
		if (array_key_exists('tags', $in)) {
			$o['tags'] = array_values(array_unique(array_map('trim', $in['tags'] ?? [])));
		}
		if (array_key_exists('customAttributes', $in)) {
			$o['custom_attributes'] = array_map(fn(array $c): array => ['key' => $c['key'], 'value' => $c['value']], $in['customAttributes'] ?? []);
		}
		$o['updated'] = $this->clock->now;
		unset($o);
		foreach ($in['metafields'] ?? [] as $mf) {
			$this->writeMetafield($domain, $in['id'], $mf);
		}
		$this->fire($domain, 'orders/updated', $this->restOrder($domain, $in['id']));
		return self::payload(['order' => $this->vOrder($domain, $in['id'])]);
	}

	private function mFulfillmentOrderMove(string $domain, array $a): array
	{
		$s = &$this->shop($domain);
		$none = ['movedFulfillmentOrder' => null, 'originalFulfillmentOrder' => null, 'remainingFulfillmentOrder' => null];
		$fo = $s['fulfillment_orders'][$a['id']] ?? null;
		if ($fo === null) {
			$this->uses('B29');
			return self::payload($none, [self::userError(['id'], 'Fulfillment order does not exist.')]);
		}
		$this->uses('B05');
		if (in_array($fo['request_status'], self::MOVE_REFUSED_REQUESTS, true)) {
			return self::payload($none, [self::userError(['id'], "Cannot move a fulfillment order with request status {$fo['request_status']}: it awaits its fulfillment service.")]);
		}
		if ($fo['progress_reported']) {
			return self::payload($none, [self::userError(['id'], 'Cannot move a fulfillment order with reported progress.', 'CANNOT_MOVE_FULFILLMENT_ORDER_WITH_REPORTED_PROGRESS')]);
		}
		if (!in_array($fo['status'], ['OPEN', 'IN_PROGRESS'], true)) {
			return self::payload($none, [self::userError(['id'], "Cannot move a fulfillment order with status {$fo['status']}.")]);
		}
		$to = $a['newLocationId'];
		if (!isset($s['locations'][$to]) || !$s['locations'][$to]['active'] || $to === $fo['location']) {
			return self::payload($none, [self::userError(['newLocationId'], 'Location is not a valid destination for this fulfillment order.')]);
		}
		$wanted = [];
		foreach ($a['fulfillmentOrderLineItems'] ?? array_map(fn(array $li): array => ['id' => $li['id'], 'quantity' => $li['remaining']],
			array_filter($fo['lines'], fn(array $li): bool => $li['remaining'] > 0)) as $i => $req) {
			$li = array_values(array_filter($fo['lines'], fn(array $x): bool => $x['id'] === $req['id']))[0] ?? null;
			if ($li === null || $req['quantity'] < 1 || $req['quantity'] > $li['remaining']) {
				return self::payload($none, [self::userError(['fulfillmentOrderLineItems', (string)$i], 'Invalid fulfillment order line item quantity.')]);
			}
			$wanted[$req['id']] = (int)$req['quantity'];
		}
		$this->uses('B03');
		$items = [];
		foreach ($fo['lines'] as $li) {
			if (!isset($wanted[$li['id']])) {
				continue;
			}
			$variant = $this->orderLine($domain, $fo['order'], $li['line'])['variant'];
			$items[$li['id']] = $variant === null ? null : $s['variants'][$variant]['item'];
			// only stocking is checked: Shopify documents no minimum quantity at the destination
			if ($items[$li['id']] !== null && !isset($s['levels'][$items[$li['id']]][$to])) {
				return self::payload($none, [self::userError(['newLocationId'], "The destination location doesn't stock the requested inventory item.")]);
			}
		}
		$this->uses('B04');
		$whole = count($wanted) === count(array_filter($fo['lines'], fn(array $li): bool => $li['remaining'] > 0))
			&& array_sum($wanted) === array_sum(array_column($fo['lines'], 'remaining')) && $fo['fulfillments'] === [];
		$moved = $whole ? $a['id'] : $this->gid('FulfillmentOrder');
		$lines = [];
		foreach ($s['fulfillment_orders'][$a['id']]['lines'] as &$li) {
			if (!isset($wanted[$li['id']])) {
				continue;
			}
			// B31: the moved quantity takes its commitment along
			$carried = min($wanted[$li['id']], $li['committed']);
			if ($carried > 0) {
				$this->uses('B31');
				$this->commit($domain, $items[$li['id']], $fo['location'], -$carried);
				$this->commit($domain, $items[$li['id']], $to, $carried);
			}
			if ($whole) {
				continue;
			}
			$lines[] = ['id' => $this->gid('FulfillmentOrderLineItem'), 'line' => $li['line'], 'total' => $wanted[$li['id']], 'remaining' => $wanted[$li['id']],
				'committed' => $carried];
			$li['total'] -= $wanted[$li['id']];
			$li['remaining'] -= $wanted[$li['id']];
			$li['committed'] -= $carried;
		}
		unset($li);
		if ($whole) {
			$s['fulfillment_orders'][$a['id']]['location'] = $to;
		} else {
			$s['fulfillment_orders'][$moved] = ['order' => $fo['order'], 'location' => $to, 'status' => 'OPEN', 'request_status' => 'UNSUBMITTED',
				'progress_reported' => false, 'lines' => $lines, 'fulfillments' => [], 'created' => $this->clock->now];
			$s['orders'][$fo['order']]['fulfillment_orders'][] = $moved;
			if (array_sum(array_column($s['fulfillment_orders'][$a['id']]['lines'], 'remaining')) === 0) {
				$s['fulfillment_orders'][$a['id']]['status'] = 'CLOSED';
			}
		}
		$s['fulfillment_orders'][$a['id']]['updated'] = $this->clock->now;
		$this->fire($domain, 'fulfillment_orders/moved', ['original_fulfillment_order' => ['id' => $a['id']], 'moved_fulfillment_order' => ['id' => $moved],
			'destination_location_id' => $this->legacy($to)]);
		return self::payload(['movedFulfillmentOrder' => $this->vFulfillmentOrder($domain, $moved),
			'originalFulfillmentOrder' => $this->vFulfillmentOrder($domain, $a['id']), 'remainingFulfillmentOrder' => null]);
	}

	private function mFulfillmentCreate(string $domain, array $a): array
	{
		$in = $a['fulfillment'];
		$s = $this->shop($domain);
		$order = null;
		$location = null;
		foreach ($in['lineItemsByFulfillmentOrder'] as $i => $group) {
			$fo = $s['fulfillment_orders'][$group['fulfillmentOrderId']] ?? null;
			if ($fo === null) {
				$this->uses('B29');
				return self::payload(['fulfillment' => null], [self::userError(['fulfillment', 'lineItemsByFulfillmentOrder', (string)$i, 'fulfillmentOrderId'], 'Fulfillment order does not exist.')]);
			}
			if (!in_array($fo['status'], ['OPEN', 'IN_PROGRESS'], true) || ($order !== null && $fo['order'] !== $order)) {
				return self::payload(['fulfillment' => null], [self::userError(['fulfillment'], 'Fulfillment orders must be open and belong to one order.')]);
			}
			$this->uses('B33');
			if ($location !== null && $fo['location'] !== $location) {
				return self::payload(['fulfillment' => null], [self::userError(['fulfillment', 'lineItemsByFulfillmentOrder', (string)$i, 'fulfillmentOrderId'],
					'The fulfillment orders of one fulfillment must be assigned to the same location.')]);
			}
			[$order, $location] = [$fo['order'], $fo['location']];
			foreach ($group['fulfillmentOrderLineItems'] ?? [] as $req) {
				$li = array_values(array_filter($fo['lines'], fn(array $x): bool => $x['id'] === $req['id']))[0] ?? null;
				if ($li === null || $req['quantity'] < 1 || $req['quantity'] > $li['remaining']) {
					return self::payload(['fulfillment' => null], [self::userError(['fulfillment'], 'Invalid fulfillment order line item quantity requested.')]);
				}
			}
		}
		$gid = $this->fulfil($domain, (string)$order, $in['lineItemsByFulfillmentOrder'], $in['trackingInfo'] ?? null, true);
		return self::payload(['fulfillment' => $this->vFulfillment($domain, $gid)]);
	}

	/** Fulfils fulfillment order lines (all remaining when a group lists none); B16 webhooks, B17 stock. */
	private function fulfil(string $domain, string $order, array $groups, ?array $tracking, bool $fire): string
	{
		$this->uses('B16');
		$this->uses('B17');
		$s = &$this->shop($domain);
		$gid = $this->gid('Fulfillment');
		$lines = [];
		$location = null;
		foreach ($groups as $group) {
			$foGid = $group['fulfillmentOrderId'];
			$fo = &$s['fulfillment_orders'][$foGid];
			$location = $fo['location'];
			$wanted = [];
			foreach ($group['fulfillmentOrderLineItems'] ?? [] as $req) {
				$wanted[$req['id']] = (int)$req['quantity'];
			}
			foreach ($fo['lines'] as &$li) {
				$qty = isset($group['fulfillmentOrderLineItems']) ? ($wanted[$li['id']] ?? 0) : $li['remaining'];
				if ($qty <= 0) {
					continue;
				}
				$li['remaining'] -= $qty;
				$lines[] = ['id' => $this->gid('FulfillmentLineItem'), 'line' => $li['line'], 'quantity' => $qty];
				$variant = $this->orderLine($domain, $order, $li['line'])['variant'];
				$item = $variant === null ? null : $s['variants'][$variant]['item'];
				if ($item !== null && $s['orders'][$order]['inventory_behaviour'] !== 'BYPASS' && isset($s['levels'][$item][$location])) {
					// the line's commitment goes first; the rest of the quantity leaves available
					$released = min($qty, $li['committed']);
					$li['committed'] -= $released;
					$this->commit($domain, $item, $location, $qty - $released);
					$level = &$s['levels'][$item][$location];
					$level['on_hand'] -= $qty;
					$level['committed'] -= $qty;
					unset($level);
				}
			}
			unset($li);
			$fo['status'] = array_sum(array_column($fo['lines'], 'remaining')) === 0 ? 'CLOSED' : 'IN_PROGRESS';
			$fo['fulfillments'][] = $gid;
			$fo['updated'] = $this->clock->now;
			unset($fo);
		}
		$s['orders'][$order]['fulfillments'][] = $gid;
		$s['fulfillments'][$gid] = ['order' => $order, 'location' => $location, 'lines' => $lines, 'created' => $this->clock->now,
			'name' => $s['orders'][$order]['name'] . '.' . count($s['orders'][$order]['fulfillments']),
			'tracking' => $tracking === null ? null : ['company' => $tracking['company'] ?? null, 'number' => $tracking['number'] ?? null, 'url' => $tracking['url'] ?? null]];
		$s['orders'][$order]['updated'] = $this->clock->now;
		if (!$fire) {
			return $gid;
		}
		$f = $s['fulfillments'][$gid];
		$this->fire($domain, 'fulfillments/create', ['id' => $this->legacy($gid), 'admin_graphql_api_id' => $gid, 'order_id' => $this->legacy($order),
			'status' => 'success', 'name' => $f['name'], 'location_id' => $this->legacy((string)$location), 'created_at' => $this->local($f['created']),
			'tracking_number' => $f['tracking']['number'] ?? null, 'tracking_company' => $f['tracking']['company'] ?? null, 'tracking_url' => $f['tracking']['url'] ?? null,
			'line_items' => array_map(fn(array $fl): array => ['id' => $this->legacy($fl['line']), 'quantity' => $fl['quantity'],
				'sku' => $this->orderLine($domain, $order, $fl['line'])['sku']], $f['lines'])]);
		$this->fire($domain, 'orders/updated', $this->restOrder($domain, $order));
		if ($this->fulfillmentStatus($domain, $order) === 'FULFILLED') {
			$this->fire($domain, 'orders/fulfilled', $this->restOrder($domain, $order));
		}
		return $gid;
	}

	private function mRefundCreate(string $domain, array $a): array
	{
		$in = $a['input'];
		$s = &$this->shop($domain);
		if (!isset($s['orders'][$in['orderId']])) {
			$this->uses('B29');
			return self::payload(['refund' => null, 'order' => null], [self::userError(['orderId'], 'Order does not exist')]);
		}
		$this->uses('B27');
		$order = $in['orderId'];
		$lines = [];
		foreach ($in['refundLineItems'] ?? [] as $i => $rl) {
			$line = null;
			foreach ($s['orders'][$order]['lines'] as $l) {
				$line = $l['id'] === $rl['lineItemId'] ? $l : $line;
			}
			if ($line === null || $rl['quantity'] < 1 || $rl['quantity'] > $line['quantity'] - $this->refundedQuantity($domain, $order, $line['id'])) {
				return self::payload(['refund' => null, 'order' => null], [self::userError(['refundLineItems', (string)$i, 'quantity'], 'Quantity cannot refund more items than were purchased')]);
			}
			$restock = $rl['restockType'] ?? 'NO_RESTOCK';
			$lines[] = ['id' => $this->gid('RefundLineItem'), 'line' => $line['id'], 'quantity' => (int)$rl['quantity'], 'restock_type' => $restock,
				'location' => $rl['locationId'] ?? null];
			if (in_array($restock, ['RETURN', 'CANCEL'], true) && $line['variant'] !== null && isset($rl['locationId'])) {
				$item = $s['variants'][$line['variant']]['item'];
				$this->setQuantity($domain, $item, $rl['locationId'], 'available', $s['levels'][$item][$rl['locationId']]['available'] + (int)$rl['quantity']);
			}
		}
		$gid = $this->gid('Refund');
		$s['refunds'][$gid] = ['order' => $order, 'note' => $in['note'] ?? null, 'lines' => $lines, 'created' => $this->clock->now];
		$s['orders'][$order]['refunds'][] = $gid;
		$s['orders'][$order]['updated'] = $this->clock->now;
		$this->fire($domain, 'refunds/create', ['id' => $this->legacy($gid), 'admin_graphql_api_id' => $gid, 'order_id' => $this->legacy($order),
			'note' => $in['note'] ?? null, 'created_at' => $this->local($this->clock->now), 'processed_at' => $this->local($this->clock->now),
			'refund_line_items' => array_map(fn(array $rl): array => ['id' => $this->legacy($rl['id']), 'line_item_id' => $this->legacy($rl['line']),
				'quantity' => $rl['quantity'], 'restock_type' => strtolower($rl['restock_type']), 'location_id' => $rl['location'] === null ? null : $this->legacy($rl['location'])], $lines),
			'transactions' => []]);
		$this->fire($domain, 'orders/updated', $this->restOrder($domain, $order));
		return self::payload(['refund' => $this->vRefund($domain, $gid), 'order' => $this->vOrder($domain, $order)]);
	}

	private function mTagsAdd(string $domain, array $a): array
	{
		return $this->tags($domain, $a['id'], $a['tags'], true);
	}

	private function mTagsRemove(string $domain, array $a): array
	{
		return $this->tags($domain, $a['id'], $a['tags'], false);
	}

	/** B25 */
	private function tags(string $domain, string $id, array $tags, bool $add): array
	{
		$table = ['Order' => 'orders', 'Customer' => 'customers', 'Product' => 'products'][$this->gidType($id)] ?? null;
		$s = &$this->shop($domain);
		if ($table === null || !isset($s[$table][$id])) {
			$this->uses('B29');
			return self::payload(['node' => null], [self::userError(['id'], 'Resource does not exist')]);
		}
		$this->uses('B25');
		$tags = array_map('trim', $tags);
		$current = $s[$table][$id]['tags'];
		$s[$table][$id]['tags'] = array_values($add ? array_unique(array_merge($current, $tags)) : array_diff($current, $tags));
		$s[$table][$id]['updated'] = $this->clock->now;
		match ($table) {
			'orders' => $this->fire($domain, 'orders/updated', $this->restOrder($domain, $id)),
			'customers' => $this->fire($domain, 'customers/update', $this->restCustomer($domain, $id)),
			'products' => $this->fire($domain, 'products/update', ['id' => $this->legacy($id), 'admin_graphql_api_id' => $id, 'tags' => implode(', ', $s[$table][$id]['tags'])]),
		};
		return self::payload(['node' => $this->node($domain, $id)]);
	}

	// ---- mutations: metafields and customers -------------------------------------------------

	private function mMetafieldsSet(string $domain, array $a): array
	{
		$list = $a['metafields'];
		$this->uses('B06');
		if (strlen(json_encode($list)) > self::METAFIELDS_SET_MAX_BYTES) {
			throw new LogicException('FakeShopifyStore: a metafieldsSet payload over 10 MB is not modelled: Shopify refuses it, its answer is not known (B06)');
		}
		if (count($list) > self::METAFIELDS_SET_MAX) {
			return self::payload(['metafields' => null], [self::userError(['metafields'], 'Exceeded the maximum metafields input limit of ' . self::METAFIELDS_SET_MAX . '.', 'LESS_THAN_OR_EQUAL_TO')]);
		}
		$owners = ['Order' => 'orders', 'Customer' => 'customers', 'Product' => 'products', 'ProductVariant' => 'variants', 'Location' => 'locations', 'Shop' => null];
		$errors = [];
		foreach ($list as $i => $mf) {
			$type = $this->gidType($mf['ownerId']);
			$path = ['metafields', (string)$i, 'ownerId'];
			$current = $this->shop($domain)['metafields'][$mf['ownerId']][($mf['namespace'] ?? '$app') . '.' . $mf['key']] ?? null;
			if ($type === 'LineItem') {
				$this->uses('B07');
				$errors[] = ['elementIndex' => $i] + self::userError($path, 'Owner type LineItem cannot have metafields.', 'INVALID_TYPE');
			} elseif (!array_key_exists($type, $owners) || ($owners[$type] !== null && !array_key_exists($mf['ownerId'], $this->shop($domain)[$owners[$type]]))
				|| ($type === 'Shop' && $mf['ownerId'] !== $this->shop($domain)['id'])) {
				$this->uses('B29');
				$errors[] = ['field' => $path, 'message' => 'Owner does not exist.', 'code' => 'INVALID_VALUE', 'elementIndex' => $i];
			} elseif (array_key_exists('compareDigest', $mf) && !$this->digestMatches($current, $mf['compareDigest'] === null ? null : (string)$mf['compareDigest'])) {
				$this->uses('B32');
				$errors[] = ['field' => ['metafields', (string)$i, 'compareDigest'], 'message' => 'The resource has been updated since it was loaded. Try again with an updated `compareDigest` value.',
					'code' => 'STALE_OBJECT', 'elementIndex' => $i];
			} elseif ($current === null && ($mf['type'] ?? '') === '') {
				$errors[] = ['field' => ['metafields', (string)$i, 'type'], 'message' => "Type can't be blank.", 'code' => 'BLANK', 'elementIndex' => $i];
			}
		}
		if ($errors) {
			return self::payload(['metafields' => null], $errors);
		}
		$out = [];
		foreach ($list as $mf) {
			$out[] = $this->vMetafield($this->writeMetafield($domain, $mf['ownerId'], $mf));
		}
		return self::payload(['metafields' => $out]);
	}

	private function writeMetafield(string $domain, string $owner, array $mf): array
	{
		$s = &$this->shop($domain);
		$full = ($mf['namespace'] ?? '$app') . '.' . $mf['key'];
		$old = $s['metafields'][$owner][$full] ?? null;
		$ownerType = ['Order' => 'ORDER', 'Customer' => 'CUSTOMER', 'Product' => 'PRODUCT', 'ProductVariant' => 'PRODUCTVARIANT',
			'Location' => 'LOCATION', 'Shop' => 'SHOP'][$this->gidType($owner)] ?? 'SHOP';
		return $s['metafields'][$owner][$full] = ['id' => $old['id'] ?? $this->gid('Metafield'), 'namespace' => $mf['namespace'] ?? '$app',
			'key' => $mf['key'], 'value' => (string)$mf['value'], 'type' => $mf['type'] ?? $old['type'] ?? 'single_line_text_field', 'owner' => $owner, 'owner_type' => $ownerType,
			'created' => $old['created'] ?? $this->clock->now, 'updated' => $this->clock->now];
	}

	private function mCustomerCreate(string $domain, array $a): array
	{
		if (isset($a['input']['id'])) {
			return self::payload(['customer' => null], [self::userError(['id'], 'Id must not be given when creating a customer')]);
		}
		$made = $this->writeCustomer($domain, null, $a['input']);
		if (isset($made['errors'])) {
			return self::payload(['customer' => null], $made['errors']);
		}
		$this->fire($domain, 'customers/create', $this->restCustomer($domain, $made['gid']));
		return self::payload(['customer' => $this->vCustomer($domain, $made['gid'])]);
	}

	private function mCustomerUpdate(string $domain, array $a): array
	{
		$gid = $a['input']['id'] ?? null;
		if ($gid === null || !array_key_exists($gid, $this->shop($domain)['customers'])) {
			$this->uses('B29');
			return self::payload(['customer' => null], [self::userError(['id'], 'Customer does not exist')]);
		}
		$made = $this->writeCustomer($domain, $gid, $a['input']);
		if (isset($made['errors'])) {
			return self::payload(['customer' => null], $made['errors']);
		}
		$this->fire($domain, 'customers/update', $this->restCustomer($domain, $gid));
		return self::payload(['customer' => $this->vCustomer($domain, $gid)]);
	}

	/**
	 * Creates ($gid null) or updates a customer; B08 keeps phone and email unique per shop.
	 *
	 * @return array{gid: string}|array{errors: list<array>}
	 */
	private function writeCustomer(string $domain, ?string $gid, array $in): array
	{
		$this->uses('B08');
		$errors = [];
		if ($this->customerBy($domain, 'email', $in['email'] ?? null, $gid) !== null) {
			$errors[] = self::userError(['email'], 'Email has already been taken');
		}
		if ($this->customerBy($domain, 'phone', $in['phone'] ?? null, $gid) !== null) {
			$errors[] = self::userError(['phone'], 'Phone has already been taken');
		}
		if ($gid === null && ($in['email'] ?? null) === null && ($in['phone'] ?? null) === null && ($in['firstName'] ?? null) === null && ($in['lastName'] ?? null) === null) {
			$errors[] = ['field' => null, 'message' => 'Customer must have a name, phone number or email address', 'code' => null, 'elementIndex' => null];
		}
		if ($errors) {
			return ['errors' => $errors];
		}
		$s = &$this->shop($domain);
		if ($gid === null) {
			$gid = $this->gid('Customer');
			$s['customers'][$gid] = ['email' => null, 'phone' => null, 'first_name' => null, 'last_name' => null, 'tags' => [], 'note' => null,
				'addresses' => [], 'created' => $this->clock->now];
		}
		foreach ($in['addresses'] ?? [] as $a) {
			// CustomerInput has no addresses in 2026-07; they arrive with orderCreate's customer.toUpsert
			$s['customers'][$gid]['addresses'][] = ['id' => $this->gid('MailingAddress'), 'firstName' => $a['firstName'] ?? null,
				'lastName' => $a['lastName'] ?? null, 'company' => $a['company'] ?? null, 'address1' => $a['address1'] ?? null,
				'address2' => $a['address2'] ?? null, 'city' => $a['city'] ?? null, 'zip' => $a['zip'] ?? null,
				'countryCode' => in_array($a['country'] ?? 'DK', ['DK', 'Denmark', 'Danmark'], true) ? 'DK' : null,
				'country' => in_array($a['country'] ?? 'DK', ['DK', 'Denmark', 'Danmark'], true) ? 'Denmark' : $a['country'], 'phone' => $a['phone'] ?? null];
		}
		foreach (['email' => 'email', 'phone' => 'phone', 'firstName' => 'first_name', 'lastName' => 'last_name', 'note' => 'note'] as $field => $col) {
			if (array_key_exists($field, $in)) {
				$s['customers'][$gid][$col] = $in[$field];
			}
		}
		if (array_key_exists('tags', $in)) {
			$s['customers'][$gid]['tags'] = array_values(array_unique(array_map('trim', $in['tags'] ?? [])));
		}
		$s['customers'][$gid]['updated'] = $this->clock->now;
		foreach ($in['metafields'] ?? [] as $mf) {
			$this->writeMetafield($domain, $gid, $mf);
		}
		return ['gid' => $gid];
	}

	// ---- mutations: webhook subscriptions ----------------------------------------------------

	private function mWebhookSubscriptionCreate(string $domain, array $a): array
	{
		$uri = (string)($a['webhookSubscription']['uri'] ?? '');
		foreach ($this->shop($domain)['subscriptions'] as $sub) {
			if ($sub['topic'] === $a['topic'] && $sub['uri'] === $uri) {
				$this->uses('B28');
				return self::payload(['webhookSubscription' => null], [self::userError(['webhookSubscription', 'uri'], 'Address for this topic has already been taken')]);
			}
		}
		return self::payload(['webhookSubscription' => $this->vSubscription($domain, $this->subscribe($domain, $a['topic'], $uri))]);
	}

	private function mWebhookSubscriptionUpdate(string $domain, array $a): array
	{
		$s = &$this->shop($domain);
		if (!isset($s['subscriptions'][$a['id']])) {
			$this->uses('B29');
			return self::payload(['webhookSubscription' => null], [self::userError(['id'], 'Webhook subscription does not exist')]);
		}
		if (isset($a['webhookSubscription']['uri'])) {
			$s['subscriptions'][$a['id']]['uri'] = $a['webhookSubscription']['uri'];
			$s['subscriptions'][$a['id']]['updated'] = $this->clock->now;
		}
		return self::payload(['webhookSubscription' => $this->vSubscription($domain, $a['id'])]);
	}

	private function mWebhookSubscriptionDelete(string $domain, array $a): array
	{
		$s = &$this->shop($domain);
		if (!isset($s['subscriptions'][$a['id']])) {
			$this->uses('B29');
			return self::payload(['deletedWebhookSubscriptionId' => null], [self::userError(['id'], 'Webhook subscription does not exist')]);
		}
		unset($s['subscriptions'][$a['id']]);
		return self::payload(['deletedWebhookSubscriptionId' => $a['id']]);
	}
}
