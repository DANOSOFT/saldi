<?php
// tests/shopify/FakeShopifyStoreTest.php
//
// The shared fake store (SD-766) without a database: the documents the merged lanes send run
// through shopify_graphql() against it, each Shopify behaviour in FakeShopifyBehaviours does what
// its entry says, injected failures reach the client as Shopify's would, and the registry lists
// exactly the behaviours the store and the webhook replayer encode.
//
// History:
// 20261103 CL/LH SD-766: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/shopify/client.php';
require_once __DIR__ . '/../../includes/shopify/services/catalogue.php';
require_once __DIR__ . '/../../includes/shopify/events.php';
require_once __DIR__ . '/support/FakeShopifyStore.php';
require_once __DIR__ . '/support/WebhookReplayer.php';
require_once __DIR__ . '/ClientRetryTest.php';

final class FakeShopifyStoreTest extends TestCase
{
	private const ORDER_CREATE = 'mutation orderCreate($order: OrderCreateOrderInput!, $options: OrderCreateOptionsInput) {
		orderCreate(order: $order, options: $options) {
			order { id name displayFinancialStatus customer { id }
				fulfillmentOrders(first: 5) { nodes { id status assignedLocation { location { id } } lineItems(first: 5) { nodes { id totalQuantity remainingQuantity } } } } }
			userErrors { field message }
		}
	}';
	private const MOVE = 'mutation move($id: ID!, $to: ID!, $lines: [FulfillmentOrderLineItemInput!]) {
		fulfillmentOrderMove(id: $id, newLocationId: $to, fulfillmentOrderLineItems: $lines) {
			movedFulfillmentOrder { id assignedLocation { location { id } } lineItems(first: 5) { nodes { remainingQuantity } } }
			originalFulfillmentOrder { id assignedLocation { location { id } } lineItems(first: 5) { nodes { remainingQuantity } } }
			userErrors { field message code }
		}
	}';
	private const FULFIL = 'mutation fulfil($f: FulfillmentInput!) { fulfillmentCreate(fulfillment: $f) { fulfillment { id status totalQuantity } userErrors { field message } } }';
	private const LEVEL = 'query level($item: ID!, $loc: ID!) { inventoryItem(id: $item) { inventoryLevel(locationId: $loc) { quantities(names: ["available", "on_hand", "committed"]) { name quantity } } } }';
	private const METAFIELDS = 'mutation mf($m: [MetafieldsSetInput!]!) { metafieldsSet(metafields: $m) { metafields { id key value } userErrors { field message code elementIndex } } }';
	private const CUSTOMER_CREATE = 'mutation c($input: CustomerInput!) { customerCreate(input: $input) { customer { id defaultEmailAddress { emailAddress } defaultPhoneNumber { phoneNumber } } userErrors { field message } } }';

	private FakeShopifyStore $store;
	/** @var array{shop_key: string, domain: string, api_version: string} */
	private array $shop;
	private string $domain;
	private string $l1;
	private string $l2;

	protected function setUp(): void
	{
		static $n = 0;
		$n++;
		$this->store = new FakeShopifyStore();
		// a shop key no other test uses: the client keeps pacing state per key for the process
		$this->shop = $this->store->addShop("fakestore$n", ['locations' => ['Lager 1', 'Lager 2']]);
		$this->domain = $this->shop['domain'];
		[$this->l1, $this->l2] = $this->store->locations($this->domain);
	}

	/** Runs a document straight on the store, without the client, and decodes the answer. */
	private function gql(string $query, array $vars = []): array
	{
		return json_decode($this->store->request($this->domain, $query, $vars)['body'], true);
	}

	private function call(string $op, string $query, array $vars, array $opts = []): array
	{
		return shopify_graphql($this->shop, $op, $query, $vars, $this->store->opts($opts));
	}

	/** @return array<string, int> available, on_hand, committed */
	private function level(string $item, string $location): array
	{
		$q = $this->gql(self::LEVEL, ['item' => $item, 'loc' => $location])['data']['inventoryItem']['inventoryLevel']['quantities'];
		return array_column($q, 'quantity', 'name');
	}

	private function order(string $variant, int $qty, array $options = [], array $extra = []): array
	{
		return $this->gql(self::ORDER_CREATE, ['order' => $extra + ['lineItems' => [['variantId' => $variant, 'quantity' => $qty]],
			'transactions' => [['kind' => 'SALE', 'status' => 'SUCCESS', 'amountSet' => ['shopMoney' => ['amount' => (string)(100 * $qty), 'currencyCode' => 'DKK']]]]],
			'options' => $options ?: null])['data']['orderCreate'];
	}

	public function testTheMergedLanesDocumentsRunAgainstTheStore(): void
	{
		$seeded = $this->store->seedProduct($this->domain, 'havestol', 'SD766-1', [$this->l1 => 4]);

		$read = $this->call('catalogueRead', SHOPIFY_CATALOGUE_READ, ['id' => $seeded['product']]);
		$byHandle = $this->call('catalogueByHandle', SHOPIFY_CATALOGUE_BY_HANDLE, ['identifier' => ['handle' => 'havestol']]);
		$bySku = $this->call('catalogueBySku', SHOPIFY_CATALOGUE_BY_SKU, ['query' => 'sku:SD766-1', 'after' => null]);
		$variant = $this->call('catalogueVariant', SHOPIFY_CATALOGUE_VARIANT, ['id' => $seeded['variant']]);
		$set = $this->call('catalogueSet', SHOPIFY_CATALOGUE_SET, ['identifier' => ['id' => $seeded['product']], 'input' => ['title' => 'Havestol', 'variants' => [
			['id' => $seeded['variant'], 'sku' => 'SD766-1', 'barcode' => '5701234567890', 'optionValues' => [['optionName' => 'Title', 'name' => 'Default Title']]]]]],
			['retry_mode' => 'idempotent']);
		$mf = $this->call('catalogueMetafields', SHOPIFY_CATALOGUE_METAFIELDS, ['metafields' => [
			['ownerId' => $seeded['variant'], 'namespace' => 'saldi', 'key' => 'vat_rate', 'type' => 'number_decimal', 'value' => '25']]], ['retry_mode' => 'idempotent']);
		$create = $this->call('webhookSubscriptionCreate', SHOPIFY_WEBHOOK_CREATE_MUTATION, ['topic' => 'ORDERS_PAID',
			'webhookSubscription' => ['uri' => 'https://saldi.example/api/shopify/webhook.php?shop=hml']], ['retry_mode' => 'idempotent']);
		$sub = $create['data']['webhookSubscriptionCreate']['webhookSubscription']['id'] ?? '';
		$update = $this->call('webhookSubscriptionUpdate', SHOPIFY_WEBHOOK_UPDATE_MUTATION, ['id' => $sub,
			'webhookSubscription' => ['uri' => 'https://saldi2.example/api/shopify/webhook.php?shop=hml']], ['retry_mode' => 'idempotent']);
		$list = $this->call('webhookSubscriptions', SHOPIFY_WEBHOOK_LIST_QUERY, ['topics' => ['ORDERS_PAID'], 'after' => null]);
		$stock = $this->call('inventorySetQuantities', ClientRetryTest::INVENTORY_SET, ['key' => 'hml:stock:1', 'input' => ['name' => 'available', 'reason' => 'correction',
			'quantities' => [['inventoryItemId' => $seeded['item'], 'locationId' => $this->l2, 'quantity' => 3, 'changeFromQuantity' => null]]]], ['retry_mode' => 'idempotent']);

		foreach (compact('read', 'byHandle', 'bySku', 'variant', 'set', 'mf', 'create', 'update', 'list', 'stock') as $name => $r) {
			$this->assertTrue($r['ok'], "$name: " . json_encode([$r['error'], $r['errors'], $r['user_errors']]));
		}
		$this->assertSame('SD766-1', $read['data']['product']['variants']['nodes'][0]['sku']);
		$this->assertSame([['name' => 'Title', 'value' => 'Default Title']], $read['data']['product']['variants']['nodes'][0]['selectedOptions']);
		$this->assertSame($seeded['product'], $byHandle['data']['productByIdentifier']['id']);
		$this->assertSame([$seeded['variant']], array_column($bySku['data']['productVariants']['nodes'], 'id'));
		$this->assertFalse($bySku['data']['productVariants']['pageInfo']['hasNextPage']);
		$this->assertSame('Havestol', $this->store->shop($this->domain)['products'][$seeded['product']]['title']);
		$this->assertSame([['topic' => 'ORDERS_PAID', 'uri' => 'https://saldi2.example/api/shopify/webhook.php?shop=hml']],
			array_map(fn(array $n): array => ['topic' => $n['topic'], 'uri' => $n['uri']], $list['data']['webhookSubscriptions']['nodes']));
		$this->assertSame(3, $this->level($seeded['item'], $this->l2)['available']);
	}

	public function testADocumentOrVariablesTheSchemaRefusesFailBeforeAnything(): void
	{
		$wrongField = $this->gql('query { shop { nam } }');
		$undeclared = $this->gql('query q { product(id: $id) { id } }');
		$missing = $this->gql('query q($id: ID!) { product(id: $id) { id } }');
		$badEnum = $this->gql('mutation m($t: WebhookSubscriptionTopic!) { webhookSubscriptionCreate(topic: $t, webhookSubscription: {uri: "https://x.example"}) { userErrors { message } } }', ['t' => 'ORDERS_EATEN']);
		$noPage = $this->gql('query { products { nodes { id } } }');

		$this->assertSame('GRAPHQL_VALIDATION_FAILED', $wrongField['errors'][0]['extensions']['code']);
		$this->assertStringContainsString('Shop has no field nam', $wrongField['errors'][0]['message']);
		$this->assertStringContainsString('$id is used by q but not declared', $undeclared['errors'][0]['message']);
		$this->assertStringContainsString('required ID! is null', $missing['errors'][0]['message']);
		$this->assertStringContainsString('ORDERS_EATEN is not a value of WebhookSubscriptionTopic', $badEnum['errors'][0]['message']);
		$this->assertSame('you must provide one of first or last', $noPage['errors'][0]['message'], 'B19');
		$this->assertSame([], $this->store->shop($this->domain)['subscriptions']);
		$this->assertArrayNotHasKey('data', $wrongField);
	}

	public function testAnUnimplementedRootFieldFailsLoudlyUntilALaneRegistersIt(): void
	{
		$doc = 'mutation d($input: DraftOrderInput!) { draftOrderCreate(input: $input) { draftOrder { id name } userErrors { message } } }';
		try {
			$this->gql($doc, ['input' => ['email' => 'kunde@example.dk']]);
			$this->fail('an unimplemented root field must not answer');
		} catch (LogicException $e) {
			$this->assertSame('FakeShopifyStore: root field draftOrderCreate not implemented', $e->getMessage());
		}

		$this->store->handle('mutation', 'draftOrderCreate', fn(FakeShopifyStore $store, string $domain, array $args): array
			=> ['draftOrder' => ['__typename' => 'DraftOrder', 'id' => $store->gid('DraftOrder'), 'name' => '#D1'], 'userErrors' => []]);
		$this->assertSame('#D1', $this->gql($doc, ['input' => ['email' => 'kunde@example.dk']])['data']['draftOrderCreate']['draftOrder']['name']);

		$this->expectExceptionMessage('ProductVariant.barcode is not modelled');
		$this->store->handle('query', 'productVariant', fn(): array => ['__typename' => 'ProductVariant', 'id' => 'gid://shopify/ProductVariant/1']);
		$this->gql('query { productVariant(id: "gid://shopify/ProductVariant/1") { id barcode } }');
	}

	public function testAnOrderBypassesInventoryAndSendsNoReceiptUnlessAsked(): void
	{
		$p = $this->store->seedProduct($this->domain, 'stol', 'SD766-2', [$this->l1 => 5]);

		$bypass = $this->order($p['variant'], 2);
		$this->assertSame([], $bypass['userErrors']);
		$this->assertSame(['available' => 5, 'on_hand' => 5, 'committed' => 0], $this->level($p['item'], $this->l1), 'B01: BYPASS by default');
		$this->assertSame([], $this->store->shop($this->domain)['receipts'], 'B02: no receipt by default');

		$decrement = $this->order($p['variant'], 2, ['inventoryBehaviour' => 'DECREMENT_OBEYING_POLICY', 'sendReceipt' => true]);
		$this->assertSame([], $decrement['userErrors']);
		$this->assertSame(['available' => 3, 'on_hand' => 5, 'committed' => 2], $this->level($p['item'], $this->l1));
		$this->assertCount(1, $this->store->shop($this->domain)['receipts']);
		$this->assertSame('Unable to reserve inventory', $this->order($p['variant'], 9, ['inventoryBehaviour' => 'DECREMENT_OBEYING_POLICY'])['userErrors'][0]['message']);

		$fo = $decrement['order']['fulfillmentOrders']['nodes'][0];
		$fulfil = $this->gql(self::FULFIL, ['f' => ['lineItemsByFulfillmentOrder' => [['fulfillmentOrderId' => $fo['id']]]]])['data']['fulfillmentCreate'];
		$this->assertSame(2, $fulfil['fulfillment']['totalQuantity']);
		$this->assertSame(['available' => 3, 'on_hand' => 3, 'committed' => 0], $this->level($p['item'], $this->l1), 'B17: fulfilling a decremented order');
		$this->gql(self::FULFIL, ['f' => ['lineItemsByFulfillmentOrder' => [['fulfillmentOrderId' => $bypass['order']['fulfillmentOrders']['nodes'][0]['id']]]]]);
		$this->assertSame(['available' => 3, 'on_hand' => 3, 'committed' => 0], $this->level($p['item'], $this->l1), 'B17: fulfilling a BYPASS order changes nothing');
	}

	public function testAnOrderIsRoutedToTheFirstLocationWithStock(): void
	{
		$a = $this->store->seedProduct($this->domain, 'a', 'SD766-A', [$this->l2 => 3]);
		$b = $this->store->seedProduct($this->domain, 'b', 'SD766-B', []);
		$r = $this->gql(self::ORDER_CREATE, ['order' => ['lineItems' => [['variantId' => $a['variant'], 'quantity' => 2], ['variantId' => $b['variant'], 'quantity' => 1]]]])['data']['orderCreate'];

		$this->assertSame('PENDING', $r['order']['displayFinancialStatus']);
		$this->assertSame([$this->l2, $this->l1], array_map(fn(array $fo): string => $fo['assignedLocation']['location']['id'], $r['order']['fulfillmentOrders']['nodes']), 'B18');
	}

	public function testAMoveNeedsStockAtTheDestinationAndSplitsAPartialQuantity(): void
	{
		$p = $this->store->seedProduct($this->domain, 'bord', 'SD766-3', [$this->l1 => 5]);
		$fo = $this->order($p['variant'], 3)['order']['fulfillmentOrders']['nodes'][0];
		$line = $fo['lineItems']['nodes'][0]['id'];

		$empty = $this->gql(self::MOVE, ['id' => $fo['id'], 'to' => $this->l2, 'lines' => null])['data']['fulfillmentOrderMove'];
		$this->assertNull($empty['movedFulfillmentOrder']);
		$this->assertSame(['newLocationId'], $empty['userErrors'][0]['field'], 'B03: no stock at the destination');

		$this->store->shop($this->domain)['levels'][$p['item']][$this->l2]['available'] = 2;
		$partial = $this->gql(self::MOVE, ['id' => $fo['id'], 'to' => $this->l2, 'lines' => [['id' => $line, 'quantity' => 2]]])['data']['fulfillmentOrderMove'];
		$this->assertSame([], $partial['userErrors']);
		$this->assertNotSame($fo['id'], $partial['movedFulfillmentOrder']['id'], 'B04: a partial move makes a new fulfillment order');
		$this->assertSame([$this->l2, 2], [$partial['movedFulfillmentOrder']['assignedLocation']['location']['id'], $partial['movedFulfillmentOrder']['lineItems']['nodes'][0]['remainingQuantity']]);
		$this->assertSame([$this->l1, 1], [$partial['originalFulfillmentOrder']['assignedLocation']['location']['id'], $partial['originalFulfillmentOrder']['lineItems']['nodes'][0]['remainingQuantity']]);

		$this->store->shop($this->domain)['fulfillment_orders'][$fo['id']]['status'] = 'IN_PROGRESS';
		$this->assertSame('CANNOT_MOVE_FULFILLMENT_ORDER_WITH_REPORTED_PROGRESS',
			$this->gql(self::MOVE, ['id' => $fo['id'], 'to' => $this->l2, 'lines' => null])['data']['fulfillmentOrderMove']['userErrors'][0]['code'], 'B05');
		$this->assertSame(['id'], $this->gql(self::MOVE, ['id' => 'gid://shopify/FulfillmentOrder/1', 'to' => $this->l2, 'lines' => null])['data']['fulfillmentOrderMove']['userErrors'][0]['field'], 'B29');
	}

	public function testMetafieldsSetTakesAtMost25AndNoLineItemOwner(): void
	{
		$p = $this->store->seedProduct($this->domain, 'parasol', 'SD766-4');
		$line = $this->order($p['variant'], 1)['order'];
		$lineItem = $this->store->shop($this->domain)['orders'][$line['id']]['lines'][0]['id'];
		$mf = fn(string $owner, int $i): array => ['ownerId' => $owner, 'namespace' => 'saldi', 'key' => "k$i", 'type' => 'single_line_text_field', 'value' => "v$i"];

		$over = $this->gql(self::METAFIELDS, ['m' => array_map(fn(int $i): array => $mf($line['id'], $i), range(1, 26))])['data']['metafieldsSet'];
		$this->assertSame('LESS_THAN_OR_EQUAL_TO', $over['userErrors'][0]['code'], 'B06');
		$this->assertArrayNotHasKey($line['id'], $this->store->shop($this->domain)['metafields'], 'B06: nothing set');

		$onLine = $this->gql(self::METAFIELDS, ['m' => [$mf($line['id'], 1), $mf($lineItem, 2)]])['data']['metafieldsSet'];
		$this->assertSame([['ownerId'], 'INVALID_TYPE', 1], [array_slice($onLine['userErrors'][0]['field'], -1), $onLine['userErrors'][0]['code'], $onLine['userErrors'][0]['elementIndex']], 'B07');
		$this->assertArrayNotHasKey($line['id'], $this->store->shop($this->domain)['metafields'], 'B07: the valid one is not set either');

		$ok = $this->gql(self::METAFIELDS, ['m' => array_map(fn(int $i): array => $mf($line['id'], $i), range(1, 25))])['data']['metafieldsSet'];
		$this->assertSame([], $ok['userErrors']);
		$this->assertCount(25, $this->store->shop($this->domain)['metafields'][$line['id']]);
		$this->assertSame('v3', $this->gql('query o($id: ID!) { order(id: $id) { metafield(namespace: "saldi", key: "k3") { value } } }', ['id' => $line['id']])['data']['order']['metafield']['value']);
	}

	public function testAPhoneOrAnEmailBelongsToOneCustomerPerShop(): void
	{
		$first = $this->gql(self::CUSTOMER_CREATE, ['input' => ['email' => 'kunde1@example.dk', 'phone' => '+4520000001', 'firstName' => 'Test']])['data']['customerCreate'];
		$phone = $this->gql(self::CUSTOMER_CREATE, ['input' => ['email' => 'kunde2@example.dk', 'phone' => '+4520000001']])['data']['customerCreate'];
		$email = $this->gql(self::CUSTOMER_CREATE, ['input' => ['email' => 'KUNDE1@example.dk']])['data']['customerCreate'];
		$other = new FakeShopifyStore();
		$otherShop = $other->addShop('fakestore-other');

		$this->assertSame([], $first['userErrors']);
		$this->assertSame([['phone'], 'Phone has already been taken'], [$phone['userErrors'][0]['field'], $phone['userErrors'][0]['message']], 'B08');
		$this->assertSame([['email'], 'Email has already been taken'], [$email['userErrors'][0]['field'], $email['userErrors'][0]['message']], 'B08');
		$this->assertCount(1, $this->store->shop($this->domain)['customers']);
		$this->assertSame([], json_decode($other->request($otherShop['domain'], self::CUSTOMER_CREATE, ['input' => ['phone' => '+4520000001']])['body'], true)['data']['customerCreate']['userErrors'], 'per shop');
	}

	public function testCostsDrainTheBucketAndAnExpensiveQueryIsRefused(): void
	{
		$mutation = json_decode($this->store->request($this->domain, self::CUSTOMER_CREATE, ['input' => ['email' => 'kunde@example.dk']])['body'], true);
		$this->assertSame(10, $mutation['extensions']['cost']['actualQueryCost'], 'B09');
		$this->assertSame(1990.0, (float)$mutation['extensions']['cost']['throttleStatus']['currentlyAvailable'], 'B10');

		$tooBig = $this->gql('query { products(first: 250) { nodes { variants(first: 250) { nodes { id } } } } }');
		$this->assertSame('MAX_COST_EXCEEDED', $tooBig['errors'][0]['extensions']['code'], 'B11');

		$this->store->shop($this->domain)['available'] = 5.0;
		$throttled = $this->gql(self::CUSTOMER_CREATE, ['input' => ['email' => 'kunde2@example.dk']]);
		$this->assertSame('THROTTLED', $throttled['errors'][0]['extensions']['code'], 'B10');
		$this->assertCount(1, $this->store->shop($this->domain)['customers'], 'a throttled request is not executed');

		// a slow bucket, so the client's first attempt is refused and it waits on the fake clock
		$this->store->restoreRate = 1.0;
		$this->store->shop($this->domain)['available'] = 0.0;
		$r = $this->call('customerCreate', self::CUSTOMER_CREATE, ['input' => ['email' => 'kunde2@example.dk']], ['retry_mode' => 'reconcile', 'reconcile' => fn(): array => ['state' => 'absent']]);
		$this->assertTrue($r['ok'], 'the client waits for the bucket on the fake clock and gets through');
		$this->assertNotEmpty($this->store->clock->sleeps);
	}

	public function testInjectedFailuresReachTheClientAsShopifysWould(): void
	{
		$reconciled = [];
		$reconcile = function (array $info) use (&$reconciled): array {
			$reconciled[] = $info['status'];
			return ['state' => 'absent'];
		};
		$mode = ['retry_mode' => 'reconcile', 'reconcile' => $reconcile];

		$this->store->fail['customerCreate'] = 'user_error';
		$userError = $this->call('customerCreate', self::CUSTOMER_CREATE, ['input' => ['email' => 'a@example.dk']], $mode);
		$this->assertSame(['user_errors', 1, 'Injected failure (test)'], [$userError['error'], $userError['attempts'], $userError['user_errors'][0]['message']]);

		$this->store->fail['customerCreate'] = 'http_500x2';
		$retried = $this->call('customerCreate', self::CUSTOMER_CREATE, ['input' => ['email' => 'b@example.dk']], $mode);
		$this->assertSame([true, 3, [500, 500]], [$retried['ok'], $retried['attempts'], $reconciled], 'a 5xx is read first, then sent again');

		$this->store->fail['customerCreate'] = 'timeout_after_apply';
		$reconciled = [];
		$applied = $this->call('customerCreate', self::CUSTOMER_CREATE, ['input' => ['email' => 'c@example.dk']],
			['retry_mode' => 'reconcile', 'reconcile' => fn(): array => ['state' => 'applied', 'data' => ['found' => true]]]);
		$this->assertSame('reconciled', $applied['outcome']);
		$this->assertCount(2, $this->store->shop($this->domain)['customers'], 'b once despite two 500s, c once though it timed out; the user error made none');

		$this->store->fail['shop'] = 'http_401';
		$this->assertSame('http_401', $this->call('shop', 'query { shop { id } }', [])['error']);
		$this->store->fail['shop'] = 'throttled';
		$this->assertTrue($this->call('shop', 'query { shop { id } }', [])['ok'], 'THROTTLED is retried');
		$this->assertSame('http_401', shopify_graphql($this->shop, 'shop', 'query { shop { id } }', [], $this->store->opts(['access_token' => 'wrong']))['error'], 'B24');
		$this->assertSame([], $this->store->fail);
	}

	public function testWebhooksFollowTheSubscriptionsAndTheOrderOfShopify(): void
	{
		foreach (SHOPIFY_WEBHOOK_TOPICS as $topic) {
			$this->store->subscribe($this->domain, $topic, 'https://saldi.example/api/shopify/webhook.php?shop=hml');
		}
		$p = $this->store->seedProduct($this->domain, 'hynde', 'SD766-5', [$this->l1 => 4]);
		$this->assertSame([], $this->store->outbox, 'seeding fires nothing');

		$order = $this->order($p['variant'], 2, [], ['customer' => ['toUpsert' => ['email' => 'ny@example.dk', 'firstName' => 'Ny', 'lastName' => 'Kunde',
			'addresses' => [['address1' => 'Havevej 1', 'zip' => '2750', 'city' => 'Ballerup', 'country' => 'Denmark']]]]])['order'];
		$this->gql(self::FULFIL, ['f' => ['lineItemsByFulfillmentOrder' => [['fulfillmentOrderId' => $order['fulfillmentOrders']['nodes'][0]['id']]],
			'trackingInfo' => ['company' => 'GLS', 'number' => 'SD766TRACK']]]);

		$out = $this->store->takeDeliveries();
		$this->assertSame(['customers/create', 'orders/create', 'orders/paid', 'fulfillments/create', 'orders/updated', 'orders/fulfilled'], array_column($out, 'topic'), 'B15, B16');
		$this->assertCount(6, array_unique(array_column($out, 'event_id')), 'B14: an event id per topic');
		$customer = json_decode($out[0]['body'], true);
		$this->assertSame(['ny@example.dk', 'Havevej 1', 'DK'], [$customer['email'], $customer['default_address']['address1'], $customer['default_address']['country_code']]);
		$paid = json_decode($out[2]['body'], true);
		$this->assertSame([$order['id'], 'paid', '200.00', 2], [$paid['admin_graphql_api_id'], $paid['financial_status'], $paid['total_price'], $paid['line_items'][0]['quantity']]);
		$this->assertSame('SD766TRACK', json_decode($out[3]['body'], true)['tracking_number']);
		$this->assertMatchesRegularExpression('/^2026-11-02T08:\d\d:\d\d\.\d{3}Z$/', $out[0]['triggered_at']);

		$this->gql('mutation t($id: ID!) { tagsAdd(id: $id, tags: ["saldi-hold", " saldi-hold"]) { node { id } userErrors { message } } }', ['id' => $order['id']]);
		$this->assertSame(['orders/updated'], array_column($this->store->takeDeliveries(), 'topic'), 'B25');
		$this->assertSame(['saldi-hold'], $this->store->shop($this->domain)['orders'][$order['id']]['tags']);

		$created = $this->gql('mutation w($t: WebhookSubscriptionTopic!, $s: WebhookSubscriptionInput!) { webhookSubscriptionCreate(topic: $t, webhookSubscription: $s) { userErrors { message } } }',
			['t' => 'ORDERS_PAID', 's' => ['uri' => 'https://saldi.example/api/shopify/webhook.php?shop=hml']]);
		$this->assertSame('Address for this topic has already been taken', $created['data']['webhookSubscriptionCreate']['userErrors'][0]['message'], 'B28');
	}

	public function testStockSetsAreAbsoluteAndAnIdempotencyKeyAnswersOnce(): void
	{
		$p = $this->store->seedProduct($this->domain, 'lampe', 'SD766-6', [$this->l1 => 2]);
		$set = fn(int $qty, ?int $from, string $key) => $this->gql(ClientRetryTest::INVENTORY_SET, ['key' => $key, 'input' => ['name' => 'available', 'reason' => 'correction',
			'quantities' => [['inventoryItemId' => $p['item'], 'locationId' => $this->l1, 'quantity' => $qty, 'changeFromQuantity' => $from]]]]);

		$this->assertSame([], $set(7, 2, 'k1')['data']['inventorySetQuantities']['userErrors'], 'B20');
		$this->assertSame(['available' => 7, 'on_hand' => 7, 'committed' => 0], $this->level($p['item'], $this->l1));
		$this->assertSame('CHANGE_FROM_QUANTITY_STALE', $this->gql('mutation s($input: InventorySetQuantitiesInput!) { inventorySetQuantities(input: $input) { userErrors { code } } }',
			['input' => ['name' => 'available', 'reason' => 'correction', 'quantities' => [['inventoryItemId' => $p['item'], 'locationId' => $this->l1, 'quantity' => 1, 'changeFromQuantity' => 2]]]])
			['data']['inventorySetQuantities']['userErrors'][0]['code'], 'B20');

		$this->store->shop($this->domain)['levels'][$p['item']][$this->l1]['available'] = 5;
		$this->assertSame([], $set(7, 2, 'k1')['data']['inventorySetQuantities']['userErrors'], 'B21: the earlier answer');
		$this->assertSame(5, $this->level($p['item'], $this->l1)['available'], 'B21: and nothing changed');
		$this->assertSame('IDEMPOTENCY_KEY_PARAMETER_MISMATCH', $set(8, 5, 'k1')['errors'][0]['extensions']['code'], 'B21');
	}

	public function testProductSetUpdatesByIdOrHandleAndKeepsWhatItLeavesOut(): void
	{
		$doc = 'mutation p($identifier: ProductSetIdentifiers, $input: ProductSetInput!) { productSet(identifier: $identifier, input: $input) { product { id handle status descriptionHtml variants(first: 5) { nodes { sku } } } userErrors { field message code } } }';
		$made = $this->gql($doc, ['identifier' => ['handle' => 'saldi-1'], 'input' => ['title' => 'Bænk', 'status' => 'DRAFT', 'descriptionHtml' => '<p>HML</p>',
			'variants' => [['sku' => 'SD766-7', 'optionValues' => [['optionName' => 'Title', 'name' => 'Default Title']]]]]])['data']['productSet']['product'];
		$byHandle = $this->gql($doc, ['identifier' => ['handle' => 'saldi-1'], 'input' => ['title' => 'Bænk 2']])['data']['productSet']['product'];
		$byId = $this->gql($doc, ['identifier' => ['id' => $made['id']], 'input' => ['variants' => [['sku' => 'SD766-8', 'optionValues' => [['optionName' => 'Title', 'name' => 'Default Title']]]]]])['data']['productSet']['product'];
		$gone = $this->gql($doc, ['identifier' => ['id' => 'gid://shopify/Product/1'], 'input' => ['title' => 'x']])['data']['productSet'];

		$this->assertSame(['saldi-1', 'DRAFT'], [$made['handle'], $made['status']]);
		$this->assertSame([$made['id'], '<p>HML</p>'], [$byHandle['id'], $byHandle['descriptionHtml']], 'B22: by handle, the description survives');
		$this->assertSame([['sku' => 'SD766-8']], $byId['variants']['nodes'], 'B22: the variants list replaces');
		$this->assertSame('PRODUCT_DOES_NOT_EXIST', $gone['userErrors'][0]['code']);
		$this->assertCount(1, $this->store->shop($this->domain)['products']);
	}

	public function testSearchesOrderUpdatesAndRefunds(): void
	{
		$p = $this->store->seedProduct($this->domain, 'skammel', 'SD766-9', [$this->l1 => 9]);
		$order = $this->order($p['variant'], 3, [], ['sourceName' => 'saldi-pos', 'sourceIdentifier' => 'saldi:41', 'tags' => ['pos']])['order'];
		$this->order($p['variant'], 1);
		$search = fn(string $q): array => array_column($this->gql('query o($q: String) { orders(first: 10, query: $q) { nodes { id } } }', ['q' => $q])['data']['orders']['nodes'], 'id');

		$this->assertSame([$order['id']], $search('source_identifier:saldi:41'), 'B23');
		$this->assertSame([$order['id']], $search('tag:pos AND name:' . ltrim($order['name'], '#')), 'B23');
		try {
			$search('created_at:>2026-01-01');
			$this->fail('an unmodelled search term must fail loudly');
		} catch (LogicException $e) {
			$this->assertStringContainsString('search term created_at: on orders is not modelled', $e->getMessage());
		}

		$update = $this->gql('mutation u($input: OrderInput!) { orderUpdate(input: $input) { order { note tags customAttributes { key value } } userErrors { message } } }',
			['input' => ['id' => $order['id'], 'note' => 'ny note', 'customAttributes' => [['key' => 'Leveringsdato', 'value' => '2026-11-20']]]])['data']['orderUpdate']['order'];
		$this->assertSame(['ny note', ['pos'], [['key' => 'Leveringsdato', 'value' => '2026-11-20']]], [$update['note'], $update['tags'], $update['customAttributes']], 'B26');

		$line = $this->store->shop($this->domain)['orders'][$order['id']]['lines'][0]['id'];
		$refund = fn(int $qty) => $this->gql('mutation r($input: RefundInput!) { refundCreate(input: $input) { refund { id totalRefundedSet { shopMoney { amount } } } order { displayFinancialStatus } userErrors { message } } }',
			['input' => ['orderId' => $order['id'], 'refundLineItems' => [['lineItemId' => $line, 'quantity' => $qty, 'restockType' => 'RETURN', 'locationId' => $this->l1]]]])['data']['refundCreate'];
		$first = $refund(2);
		$this->assertSame(['200.0', 'PARTIALLY_REFUNDED'], [$first['refund']['totalRefundedSet']['shopMoney']['amount'], $first['order']['displayFinancialStatus']], 'B27');
		$this->assertSame(11, $this->level($p['item'], $this->l1)['available'], 'B27: restocked');
		$this->assertSame('Quantity cannot refund more items than were purchased', $refund(2)['userErrors'][0]['message'], 'B27');
	}

	public function testPagesAndStateSurviveAnExport(): void
	{
		foreach (range(1, 3) as $i) {
			$this->store->seedProduct($this->domain, "p$i", "SD766-P$i");
		}
		$page = fn(?string $after): array => $this->gql('query p($after: String) { products(first: 2, after: $after) { edges { cursor node { handle } } pageInfo { hasNextPage endCursor } } }', ['after' => $after])['data']['products'];
		$one = $page(null);
		$two = $page($one['pageInfo']['endCursor']);

		$this->assertSame([['p1', 'p2'], true], [array_column(array_column($one['edges'], 'node'), 'handle'), $one['pageInfo']['hasNextPage']], 'B19');
		$this->assertSame([['p3'], false], [array_column(array_column($two['edges'], 'node'), 'handle'), $two['pageInfo']['hasNextPage']], 'B19');

		$copy = FakeShopifyStore::fromState(json_decode(json_encode($this->store->exportState()), true));
		$this->assertSame($this->store->request($this->domain, 'query { products(first: 5) { nodes { id handle } } }', [])['body'],
			$copy->request($this->domain, 'query { products(first: 5) { nodes { id handle } } }', [])['body']);
		$this->assertSame($this->store->gid('X'), $copy->gid('X'), 'ids go on from the same number');
	}

	public function testTheRegistryListsExactlyTheBehavioursTheFakesEncode(): void
	{
		preg_match_all("/uses\('(B\d\d)'\)/", (string)file_get_contents(__DIR__ . '/support/FakeShopifyStore.php'), $store);
		preg_match_all('/\bB\d\d\b/', (string)file_get_contents(__DIR__ . '/support/WebhookReplayer.php'), $replayer);
		$encoded = array_unique(array_merge($store[1], $replayer[0]));
		sort($encoded);

		$this->assertSame(array_keys(FakeShopifyBehaviours::ALL), $encoded, 'every behaviour the fakes apply is listed, and every listed one is applied');
		foreach (FakeShopifyBehaviours::ALL as $id => $b) {
			$this->assertMatchesRegularExpression('#https://shopify\.dev/|verified 2026-10-06|ASSUMED#', $b['source'], "$id names its source");
			$this->assertNotSame('', $b['calibrated_by'], "$id names its calibration");
		}
		$this->assertStringContainsString("| B01 | orderCreate without options.inventoryBehaviour uses BYPASS", FakeShopifyBehaviours::markdown());
	}

	public function testTheReplayerSignsAtRunTimeAndArrangesADay(): void
	{
		$deliveries = array_map(fn(int $i): array => ['topic' => 'orders/paid', 'event_id' => "e$i", 'triggered_at' => '2026-11-02T08:00:00.000Z', 'attempt' => 1, 'raw' => "{\"id\":$i}"], range(1, 8));
		$replayer = new WebhookReplayer(['shop_key' => 'hml', 'domain' => 'hml-test.myshopify.com', 'api_version' => '2026-07'], 'not-a-real-secret');

		$a = $replayer->headers($deliveries[0]);
		$b = $replayer->headers($deliveries[0]);
		$this->assertTrue(shopify_verify_hmac('{"id":1}', $a['X-Shopify-Hmac-Sha256'], 'not-a-real-secret'));
		$this->assertSame(['orders/paid', 'hml-test.myshopify.com', 'e1', '2026-07'], [$a['X-Shopify-Topic'], $a['X-Shopify-Shop-Domain'], $a['X-Shopify-Event-Id'], $a['X-Shopify-API-Version']]);
		$this->assertNotSame($a['X-Shopify-Webhook-Id'], $b['X-Shopify-Webhook-Id'], 'a new webhook id per attempt');

		$ids = fn(array $list): array => array_column($list, 'event_id');
		$this->assertSame(array_reverse($ids($deliveries)), $ids(WebhookReplayer::arrange($deliveries, 'reversed')));
		$this->assertSame($ids(WebhookReplayer::arrange($deliveries, 'shuffled', 7)), $ids(WebhookReplayer::arrange($deliveries, 'shuffled', 7)), 'a seed gives the same order');
		$this->assertNotSame($ids($deliveries), $ids(WebhookReplayer::arrange($deliveries, 'shuffled', 7)));
		$dup = WebhookReplayer::arrange($deliveries, 'duplicated', 3);
		$this->assertGreaterThan(8, count($dup));
		$this->assertSame($ids($deliveries), array_values(array_unique($ids($dup))));
		$late = WebhookReplayer::arrange($deliveries, 'delayed', 3);
		$this->assertCount(8, $late);
		$this->assertSame(2, end($late)['attempt']);
		$this->expectException(InvalidArgumentException::class);
		WebhookReplayer::arrange($deliveries, 'sideways');
	}
}
