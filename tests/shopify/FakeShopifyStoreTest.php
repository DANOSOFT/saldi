<?php
// tests/shopify/FakeShopifyStoreTest.php
//
// The shared fake store (SD-766) without a database: the documents the merged lanes send run
// through shopify_graphql() against it, each Shopify behaviour in FakeShopifyBehaviours does what
// its entry says, injected failures reach the client as Shopify's would, and the registry lists
// exactly the behaviours the store and the webhook replayer encode. Each test runs in its own
// process: the lanes' files define shopify_secret() and shopify_redact(), which ClientRetryTest
// needs absent.
//
// History:
// 20261103 CL/LH SD-766: created.
// 20261103 CL/LH SD-766: review fixes: @idempotent required, taxes from the input, moves carry commitments and need
//                only a stocking destination, the documented move statuses, one location per fulfilment, compareDigest,
//                mutation cost with its payload, stable webhook ids, a basis per registry entry.

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/FakeShopifyStore.php';
require_once __DIR__ . '/support/WebhookReplayer.php';
require_once __DIR__ . '/ClientRetryTest.php';

#[RunTestsInSeparateProcesses]
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
	private const METAFIELDS = 'mutation mf($m: [MetafieldsSetInput!]!) { metafieldsSet(metafields: $m) { metafields { id key value compareDigest } userErrors { field message code elementIndex } } }';
	private const STOCK_SET = 'mutation s($input: InventorySetQuantitiesInput!, $key: String!) { inventorySetQuantities(input: $input) @idempotent(key: $key) { userErrors { field message code } } }';
	private const ACTIVATE = 'mutation a($item: ID!, $loc: ID!, $key: String!) { inventoryActivate(inventoryItemId: $item, locationId: $loc) @idempotent(key: $key) {
		inventoryLevel { quantities(names: ["available", "on_hand"]) { name quantity } } userErrors { field message } } }';
	private const CUSTOMER_CREATE = 'mutation c($input: CustomerInput!) { customerCreate(input: $input) { customer { id defaultEmailAddress { emailAddress } defaultPhoneNumber { phoneNumber } } userErrors { field message } } }';

	private FakeShopifyStore $store;
	/** @var array{shop_key: string, domain: string, api_version: string} */
	private array $shop;
	private string $domain;
	private string $l1;
	private string $l2;

	protected function setUp(): void
	{
		require_once __DIR__ . '/../../includes/shopify/client.php';
		require_once __DIR__ . '/../../includes/shopify/services/catalogue.php';
		require_once __DIR__ . '/../../includes/shopify/events.php';
		$this->store = new FakeShopifyStore();
		$this->shop = $this->store->addShop('fakestore', ['locations' => ['Lager 1', 'Lager 2']]);
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

	private function move(string $fo, string $to, ?array $lines = null): array
	{
		return $this->gql(self::MOVE, ['id' => $fo, 'to' => $to, 'lines' => $lines])['data']['fulfillmentOrderMove'];
	}

	private function fulfil(string $fo, ?array $lines = null): array
	{
		$group = ['fulfillmentOrderId' => $fo] + ($lines === null ? [] : ['fulfillmentOrderLineItems' => $lines]);
		return $this->gql(self::FULFIL, ['f' => ['lineItemsByFulfillmentOrder' => [$group]]])['data']['fulfillmentCreate'];
	}

	public function testAMoveNeedsTheDestinationToStockTheItemNotToHaveItInStock(): void
	{
		$zero = $this->store->seedProduct($this->domain, 'bord', 'SD766-3', [$this->l1 => 5]);
		$short = $this->store->seedProduct($this->domain, 'bord-kort', 'SD766-3K', [$this->l1 => 5, $this->l2 => 1]);
		$none = $this->store->seedProduct($this->domain, 'bord-ude', 'SD766-3U', [$this->l1 => 5]);
		$this->store->unstock($this->domain, $none['item'], $this->l2);

		$unstocked = $this->order($none['variant'], 3)['order']['fulfillmentOrders']['nodes'][0];
		$refused = $this->move($unstocked['id'], $this->l2);
		$this->assertNull($refused['movedFulfillmentOrder']);
		$this->assertSame([['newLocationId'], "The destination location doesn't stock the requested inventory item."],
			[$refused['userErrors'][0]['field'], $refused['userErrors'][0]['message']], 'B03: an unstocked destination');
		$activated = $this->gql(self::ACTIVATE, ['item' => $none['item'], 'loc' => $this->l2, 'key' => 'activate-1'])['data']['inventoryActivate'];
		$this->assertSame([[], ['available' => 0, 'on_hand' => 0]], [$activated['userErrors'], array_column($activated['inventoryLevel']['quantities'], 'quantity', 'name')], 'B35: activated at zero');
		$this->assertSame([], $this->move($unstocked['id'], $this->l2)['userErrors'], 'B03: once stocked, even at zero');

		$understocked = $this->order($short['variant'], 3)['order']['fulfillmentOrders']['nodes'][0];
		$this->assertSame([], $this->move($understocked['id'], $this->l2)['userErrors'], 'B03: 1 at the destination does not hold back a move of 3');

		$fo = $this->order($zero['variant'], 3)['order']['fulfillmentOrders']['nodes'][0];
		$line = $fo['lineItems']['nodes'][0]['id'];
		$partial = $this->move($fo['id'], $this->l2, [['id' => $line, 'quantity' => 2]]);
		$this->assertSame([], $partial['userErrors'], 'B03: activated at zero');
		$this->assertNotSame($fo['id'], $partial['movedFulfillmentOrder']['id'], 'B04: a partial move makes a new fulfillment order');
		$this->assertSame([$this->l2, 2], [$partial['movedFulfillmentOrder']['assignedLocation']['location']['id'], $partial['movedFulfillmentOrder']['lineItems']['nodes'][0]['remainingQuantity']]);
		$this->assertSame([$this->l1, 1], [$partial['originalFulfillmentOrder']['assignedLocation']['location']['id'], $partial['originalFulfillmentOrder']['lineItems']['nodes'][0]['remainingQuantity']]);
		$this->assertSame(['id'], $this->move('gid://shopify/FulfillmentOrder/1', $this->l2)['userErrors'][0]['field'], 'B29');
	}

	public function testAMoveFollowsTheDocumentedRequestStatusesAndReportedProgress(): void
	{
		$p = $this->store->seedProduct($this->domain, 'liggestol', 'SD766-S9', [$this->l1 => 9, $this->l2 => 9]);
		$fo = $this->order($p['variant'], 3)['order']['fulfillmentOrders']['nodes'][0];
		$state = fn(string $id): array => $this->store->shop($this->domain)['fulfillment_orders'][$id];
		$set = function (string $id, string $key, mixed $value): void {
			$this->store->shop($this->domain)['fulfillment_orders'][$id][$key] = $value;
		};

		foreach (FakeShopifyStore::MOVE_REFUSED_REQUESTS as $request) {
			$set($fo['id'], 'request_status', $request);
			$this->assertNull($this->move($fo['id'], $this->l2)['movedFulfillmentOrder'], "B05: $request awaits the fulfillment service");
		}
		$this->assertSame($this->l1, $state($fo['id'])['location']);
		$set($fo['id'], 'request_status', 'CANCELLATION_ACCEPTED');
		$reassigned = $this->move($fo['id'], $this->l2);
		$this->assertSame([[], $fo['id'], $this->l2], [$reassigned['userErrors'], $reassigned['movedFulfillmentOrder']['id'], $state($fo['id'])['location']],
			'B05: an accepted cancellation lets it be re-assigned');

		$partly = $this->order($p['variant'], 3)['order']['fulfillmentOrders']['nodes'][0];
		$this->assertSame([], $this->fulfil($partly['id'], [['id' => $partly['lineItems']['nodes'][0]['id'], 'quantity' => 1]])['userErrors']);
		$this->assertSame('IN_PROGRESS', $state($partly['id'])['status']);
		$rest = $this->move($partly['id'], $this->l2);
		$this->assertSame([], $rest['userErrors'], 'B05: the rest of a partly fulfilled order moves');
		$this->assertNotSame($partly['id'], $rest['movedFulfillmentOrder']['id'], 'B04: once something is fulfilled the moved order is a new one');
		$this->assertSame([$this->l2, 2], [$rest['movedFulfillmentOrder']['assignedLocation']['location']['id'], $rest['movedFulfillmentOrder']['lineItems']['nodes'][0]['remainingQuantity']]);
		$this->assertSame([$this->l1, 'CLOSED'], [$state($partly['id'])['location'], $state($partly['id'])['status']], 'B04: the fulfilled piece stays, the emptied original closes');
		$this->assertSame("Cannot move a fulfillment order with status CLOSED.", $this->move($partly['id'], $this->l2)['userErrors'][0]['message'], 'B05');
		$actions = fn(string $id): array => array_column($this->gql('query f($id: ID!) { fulfillmentOrder(id: $id) { supportedActions { action } } }', ['id' => $id])['data']['fulfillmentOrder']['supportedActions'], 'action');
		$this->assertSame([[], ['CREATE_FULFILLMENT', 'MOVE']], [$actions($partly['id']), $actions($rest['movedFulfillmentOrder']['id'])], 'B05: MOVE where a move is taken');

		$reported = $this->order($p['variant'], 1)['order']['fulfillmentOrders']['nodes'][0];
		$set($reported['id'], 'status', 'IN_PROGRESS');
		$set($reported['id'], 'progress_reported', true);
		$this->assertSame('CANNOT_MOVE_FULFILLMENT_ORDER_WITH_REPORTED_PROGRESS', $this->move($reported['id'], $this->l2)['userErrors'][0]['code'], 'B05');
		$this->assertSame(['CREATE_FULFILLMENT'], $actions($reported['id']));
	}

	public function testAMoveTakesADecrementedOrdersCommitmentAlong(): void
	{
		$p = $this->store->seedProduct($this->domain, 'sofa', 'SD766-B4', [$this->l1 => 5, $this->l2 => 5]);
		$levels = function () use ($p): array {
			$out = [];
			foreach (['l1' => $this->l1, 'l2' => $this->l2] as $name => $location) {
				$q = $this->level($p['item'], $location);
				$this->assertSame($q['on_hand'], $q['available'] + $q['committed'], "on_hand = available + committed at $name");
				$out[$name] = [$q['available'], $q['on_hand'], $q['committed']];
			}
			return $out;
		};
		$decrement = ['inventoryBehaviour' => 'DECREMENT_OBEYING_POLICY'];

		$whole = $this->order($p['variant'], 2, $decrement)['order']['fulfillmentOrders']['nodes'][0];
		$this->assertSame(['l1' => [3, 5, 2], 'l2' => [5, 5, 0]], $levels(), 'B01');
		$this->assertSame([], $this->move($whole['id'], $this->l2)['userErrors']);
		$this->assertSame(['l1' => [5, 5, 0], 'l2' => [3, 5, 2]], $levels(), 'B31: the commitment moves with the order');
		$this->assertSame([], $this->fulfil($whole['id'])['userErrors']);
		$this->assertSame(['l1' => [5, 5, 0], 'l2' => [3, 3, 0]], $levels(), 'B17: shipped from the location it moved to');

		$split = $this->order($p['variant'], 3, $decrement)['order']['fulfillmentOrders']['nodes'][0];
		$this->assertSame(['l1' => [2, 5, 3], 'l2' => [3, 3, 0]], $levels());
		$moved = $this->move($split['id'], $this->l2, [['id' => $split['lineItems']['nodes'][0]['id'], 'quantity' => 1]])['movedFulfillmentOrder'];
		$this->assertSame(['l1' => [3, 5, 2], 'l2' => [2, 3, 1]], $levels(), 'B31: a partial move carries only the moved quantity');
		$this->assertSame([], $this->fulfil($split['id'])['userErrors']);
		$this->assertSame([], $this->fulfil($moved['id'])['userErrors']);
		$this->assertSame(['l1' => [3, 3, 0], 'l2' => [2, 2, 0]], $levels(), 'B17, B31: both parts shipped, nothing left committed');

		$bypass = $this->order($p['variant'], 1)['order']['fulfillmentOrders']['nodes'][0];
		$this->assertSame([], $this->move($bypass['id'], $this->l2)['userErrors']);
		$this->assertSame(['l1' => [3, 3, 0], 'l2' => [2, 2, 0]], $levels(), 'B31: a BYPASS order moves no stock');
	}

	public function testAFulfilmentTakesTheFulfillmentOrdersOfOneLocation(): void
	{
		$a = $this->store->seedProduct($this->domain, 'a', 'SD766-S4A', [$this->l1 => 2]);
		$b = $this->store->seedProduct($this->domain, 'b', 'SD766-S4B', [$this->l2 => 2]);
		$order = $this->gql(self::ORDER_CREATE, ['order' => ['lineItems' => [['variantId' => $a['variant'], 'quantity' => 1], ['variantId' => $b['variant'], 'quantity' => 1]]]])['data']['orderCreate']['order'];
		[$fo1, $fo2] = array_column($order['fulfillmentOrders']['nodes'], 'id');
		$before = $this->store->shop($this->domain)['fulfillment_orders'];

		$both = $this->gql(self::FULFIL, ['f' => ['lineItemsByFulfillmentOrder' => [['fulfillmentOrderId' => $fo1], ['fulfillmentOrderId' => $fo2]]]])['data']['fulfillmentCreate'];
		$this->assertNull($both['fulfillment']);
		$this->assertSame('The fulfillment orders of one fulfillment must be assigned to the same location.', $both['userErrors'][0]['message'], 'B33');
		$this->assertSame($before, $this->store->shop($this->domain)['fulfillment_orders'], 'B33: nothing changed');
		$this->assertSame([], $this->store->shop($this->domain)['fulfillments']);

		$this->assertSame([[], []], [$this->fulfil($fo1)['userErrors'], $this->fulfil($fo2)['userErrors']], 'one fulfilment per location');
		$this->assertSame('FULFILLED', $this->gql('query o($id: ID!) { order(id: $id) { displayFulfillmentStatus } }', ['id' => $order['id']])['data']['order']['displayFulfillmentStatus']);
	}

	public function testTaxesComeFromTheOrderInputAndNoneAreMadeUp(): void
	{
		foreach (SHOPIFY_WEBHOOK_TOPICS as $topic) {
			$this->store->subscribe($this->domain, $topic, 'https://saldi.example/api/shopify/webhook.php?shop=hml');
		}
		$p = $this->store->seedProduct($this->domain, 'parasol-moms', 'SD766-T1', [$this->l1 => 9]);
		$doc = 'mutation o($order: OrderCreateOrderInput!) { orderCreate(order: $order) { order { id taxesIncluded totalTaxSet { shopMoney { amount } }
			subtotalPriceSet { shopMoney { amount } } totalPriceSet { shopMoney { amount } } taxLines { title rate priceSet { shopMoney { amount } } } } userErrors { field message } } }';
		$money = fn(string $amount): array => ['shopMoney' => ['amount' => $amount, 'currencyCode' => 'DKK']];
		$moms = fn(string $amount): array => ['title' => 'Moms', 'rate' => '0.25', 'priceSet' => $money($amount)];
		$create = fn(array $order): array => $this->gql($doc, ['order' => $order + ['currency' => 'DKK']])['data']['orderCreate'];
		$amounts = fn(array $o): array => [$o['taxesIncluded'], $o['totalTaxSet']['shopMoney']['amount'], $o['subtotalPriceSet']['shopMoney']['amount'], $o['totalPriceSet']['shopMoney']['amount']];

		$plain = $create(['lineItems' => [['variantId' => $p['variant'], 'quantity' => 1]]])['order'];
		$this->assertSame([false, '0.0', '100.0', '100.0'], $amounts($plain), 'B34: taxesIncluded defaults to false and no tax lines make no tax');
		$this->assertSame([], $plain['taxLines']);

		$onLine = $create(['lineItems' => [['variantId' => $p['variant'], 'quantity' => 2, 'taxLines' => [$moms('50.00')]]]])['order'];
		$this->assertSame([false, '50.0', '200.0', '250.0'], $amounts($onLine), 'B34: the line\'s tax, added to the total');
		$this->assertSame([['title' => 'Moms', 'rate' => 0.25, 'priceSet' => ['shopMoney' => ['amount' => '50.0']]]], $onLine['taxLines']);

		$included = $create(['taxesIncluded' => true, 'taxLines' => [$moms('20.00')], 'lineItems' => [['variantId' => $p['variant'], 'quantity' => 1]]])['order'];
		$this->assertSame([true, '20.0', '100.0', '100.0'], $amounts($included), 'B34: tax inside the prices');
		$body = json_decode(array_values(array_filter($this->store->takeDeliveries(), fn(array $d): bool => $d['topic'] === 'orders/create'))[2]['body'], true);
		$this->assertSame([true, '20.00', '100.00', '100.00'], [$body['taxes_included'], $body['total_tax'], $body['subtotal_price'], $body['total_price']], 'the webhook body agrees');

		$both = $create(['taxLines' => [$moms('20.00')], 'lineItems' => [['variantId' => $p['variant'], 'quantity' => 1, 'taxLines' => [$moms('20.00')]]]]);
		$this->assertSame([null, ['order', 'taxLines']], [$both['order'], $both['userErrors'][0]['field']], 'B34: on the order or on the lines, not both');
		foreach (['a tax line without priceSet' => ['lineItems' => [['variantId' => $p['variant'], 'quantity' => 1, 'taxLines' => [['title' => 'Moms', 'rate' => '0.25']]]]],
			'shippingLines' => ['lineItems' => [['variantId' => $p['variant'], 'quantity' => 1]], 'shippingLines' => [['title' => 'GLS', 'priceSet' => $money('49.00')]]]] as $what => $order) {
			try {
				$create($order);
				$this->fail("$what must fail loudly");
			} catch (LogicException $e) {
				$this->assertStringContainsString('not modelled', $e->getMessage(), $what);
			}
		}
	}

	public function testMetafieldsSetComparesDigestsAndSetsNothingOnAMismatch(): void
	{
		$p = $this->store->seedProduct($this->domain, 'hynde-mf', 'SD766-MF');
		$mf = fn(string $key, string $value, array $extra = []): array => ['ownerId' => $p['product'], 'namespace' => 'saldi', 'key' => $key,
			'type' => 'single_line_text_field', 'value' => $value] + $extra;
		$set = fn(array ...$list): array => $this->gql(self::METAFIELDS, ['m' => $list])['data']['metafieldsSet'];
		$value = fn(string $key): ?string => $this->store->shop($this->domain)['metafields'][$p['product']]["saldi.$key"]['value'] ?? null;

		$created = $set($mf('a', 'v1', ['compareDigest' => null]));
		$this->assertSame([], $created['userErrors'], 'B32: null creates a metafield that does not exist');
		$digest = $created['metafields'][0]['compareDigest'];
		$exists = $set($mf('a', 'v2', ['compareDigest' => null]));
		$this->assertSame([['metafields', '0', 'compareDigest'], 'STALE_OBJECT', 0], [$exists['userErrors'][0]['field'], $exists['userErrors'][0]['code'], $exists['userErrors'][0]['elementIndex']], 'B32: null on an existing one');
		$this->assertSame('v1', $value('a'));

		$this->assertSame([], $set($mf('a', 'v2', ['compareDigest' => $digest]))['userErrors'], 'B32: the digest read before');
		$stale = $set($mf('b', 'ny'), $mf('a', 'v3', ['compareDigest' => $digest]));
		$this->assertSame(['STALE_OBJECT', 1], [$stale['userErrors'][0]['code'], $stale['userErrors'][0]['elementIndex']], 'B32: a stale digest');
		$this->assertSame(['v2', null], [$value('a'), $value('b')], 'B06, B32: the call sets nothing');
		$blind = $set($mf('a', 'v2'));
		$this->assertSame([], $blind['userErrors'], 'B32: no compareDigest, no check');
		$this->assertNotSame($blind['metafields'][0]['compareDigest'], $set($mf('a', 'v2'))['metafields'][0]['compareDigest'], 'B32: every write gives a new digest, the same value too');
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
		$this->assertSame(14, $mutation['extensions']['cost']['actualQueryCost'], 'B09: 10, and the customer with its two objects and the userErrors');
		$this->assertSame(1986.0, (float)$mutation['extensions']['cost']['throttleStatus']['currentlyAvailable'], 'B10');
		$fragment = json_decode($this->store->request($this->domain, 'mutation c($input: CustomerInput!) { customerCreate(input: $input) { ...Made userErrors { field } } }
			fragment Made on CustomerCreatePayload { customer { id defaultEmailAddress { emailAddress } defaultPhoneNumber { phoneNumber } } }', ['input' => ['email' => 'kunde3@example.dk']])['body'], true);
		$this->assertSame(14, $fragment['extensions']['cost']['requestedQueryCost'], 'B09: a fragment costs what it selects');

		$tooBig = $this->gql('query { products(first: 250) { nodes { variants(first: 250) { nodes { id } } } } }');
		$this->assertSame('MAX_COST_EXCEEDED', $tooBig['errors'][0]['extensions']['code'], 'B11');
		$dearSet = $this->gql('mutation p($identifier: ProductSetIdentifiers, $input: ProductSetInput!) { productSet(identifier: $identifier, input: $input) {
			product { variants(first: 250) { nodes { inventoryItem { inventoryLevels(first: 250) { nodes { location { id } } } } } } } userErrors { message } } }',
			['identifier' => ['handle' => 'dyr'], 'input' => ['title' => 'Dyr']]);
		$this->assertSame('MAX_COST_EXCEEDED', $dearSet['errors'][0]['extensions']['code'], 'B09, B11: a mutation costs what its payload selects');
		$this->assertGreaterThan(1000, $dearSet['errors'][0]['extensions']['cost']);
		$this->assertNotContains('dyr', array_column($this->store->shop($this->domain)['products'], 'handle'), 'refused before it ran');

		$this->store->shop($this->domain)['available'] = 5.0;
		$throttled = $this->gql(self::CUSTOMER_CREATE, ['input' => ['email' => 'kunde2@example.dk']]);
		$this->assertSame('THROTTLED', $throttled['errors'][0]['extensions']['code'], 'B10');
		$this->assertCount(2, $this->store->shop($this->domain)['customers'], 'a throttled request is not executed');

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

		$this->store->subscribe($this->domain, 'ORDERS_UPDATED', 'https://saldi2.example/api/shopify/webhook.php?shop=hml');
		$this->gql('mutation t($id: ID!) { tagsRemove(id: $id, tags: ["saldi-hold"]) { node { id } userErrors { message } } }', ['id' => $order['id']]);
		$twice = $this->store->takeDeliveries();
		$this->assertSame(['orders/updated', 'orders/updated'], array_column($twice, 'topic'), 'B12: one delivery per subscription');
		$this->assertSame($twice[0]['event_id'], $twice[1]['event_id'], 'B13: one event id for the action');
		$this->assertNotSame($twice[0]['webhook_id'], $twice[1]['webhook_id'], 'B13: a webhook id per delivery');
		$this->assertNotContains($twice[0]['event_id'], array_column($out, 'event_id'));

		$created = $this->gql('mutation w($t: WebhookSubscriptionTopic!, $s: WebhookSubscriptionInput!) { webhookSubscriptionCreate(topic: $t, webhookSubscription: $s) { userErrors { message } } }',
			['t' => 'ORDERS_PAID', 's' => ['uri' => 'https://saldi.example/api/shopify/webhook.php?shop=hml']]);
		$this->assertSame('Address for this topic has already been taken', $created['data']['webhookSubscriptionCreate']['userErrors'][0]['message'], 'B28');
	}

	public function testStockSetsAreAbsoluteAndNeedAnIdempotencyKeyThatAnswersOnce(): void
	{
		$p = $this->store->seedProduct($this->domain, 'lampe', 'SD766-6', [$this->l1 => 2]);
		$quantity = fn(int $qty, ?int $from): array => ['inventoryItemId' => $p['item'], 'locationId' => $this->l1, 'quantity' => $qty, 'changeFromQuantity' => $from];
		$set = fn(int $qty, ?int $from, string $key): array => $this->gql(self::STOCK_SET, ['key' => $key,
			'input' => ['name' => 'available', 'reason' => 'correction', 'quantities' => [$quantity($qty, $from)]]])['data']['inventorySetQuantities']['userErrors'];

		$unkeyed = $this->gql('mutation s($input: InventorySetQuantitiesInput!) { inventorySetQuantities(input: $input) { userErrors { code } } }',
			['input' => ['name' => 'available', 'reason' => 'correction', 'quantities' => [$quantity(9, null)]]]);
		$this->assertArrayNotHasKey('data', $unkeyed);
		$this->assertStringContainsString('inventorySetQuantities requires an idempotency key', $unkeyed['errors'][0]['message'], 'B21: required in 2026-07');
		$this->assertSame(2, $this->level($p['item'], $this->l1)['available'], 'B21: refused before it ran');

		$this->assertSame([], $set(7, 2, 'k1'), 'B20');
		$this->assertSame(['available' => 7, 'on_hand' => 7, 'committed' => 0], $this->level($p['item'], $this->l1));
		$this->assertSame('CHANGE_FROM_QUANTITY_STALE', $set(1, 2, 'k2')[0]['code'], 'B20');
		$omitted = $this->gql(self::STOCK_SET, ['key' => 'k3', 'input' => ['name' => 'available', 'reason' => 'correction',
			'quantities' => [['inventoryItemId' => $p['item'], 'locationId' => $this->l1, 'quantity' => 1]]]])['data']['inventorySetQuantities']['userErrors'];
		$this->assertSame(['input', 'quantities', '0', 'changeFromQuantity'], $omitted[0]['field'], 'B20: changeFromQuantity is required, null skips the check');

		$this->store->shop($this->domain)['levels'][$p['item']][$this->l1]['available'] = 5;
		$this->assertSame([], $set(7, 2, 'k1'), 'B21: the earlier answer');
		$this->assertSame(5, $this->level($p['item'], $this->l1)['available'], 'B21: and nothing changed');
		$mismatch = $set(8, 5, 'k1');
		$this->assertSame(['IDEMPOTENCY_KEY_PARAMETER_MISMATCH', 'The same idempotency key cannot be used with different operation parameters.'],
			[$mismatch[0]['code'], $mismatch[0]['message']], 'B21: a userError');
		$this->assertSame(5, $this->level($p['item'], $this->l1)['available']);
		$this->store->clock->now += FakeShopifyStore::IDEMPOTENCY_SECONDS;
		$this->assertSame([], $set(8, 5, 'k1'), 'B21: a key is kept 24 hours');
		$this->assertSame(8, $this->level($p['item'], $this->l1)['available']);

		try {
			$this->gql('mutation t($id: ID!) { tagsAdd(id: $id, tags: ["x"]) @idempotent(key: "t1") { userErrors { message } } }', ['id' => $p['product']]);
			$this->fail('@idempotent where 2026-07 does not document it must fail loudly');
		} catch (LogicException $e) {
			$this->assertStringContainsString('@idempotent on mutation field tagsAdd is not modelled', $e->getMessage());
		}
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
		$refund = fn(int $qty, string $key) => $this->gql('mutation r($input: RefundInput!, $key: String!) { refundCreate(input: $input) @idempotent(key: $key) {
			refund { id totalRefundedSet { shopMoney { amount } } } order { displayFinancialStatus } userErrors { message } } }',
			['key' => $key, 'input' => ['orderId' => $order['id'], 'refundLineItems' => [['lineItemId' => $line, 'quantity' => $qty, 'restockType' => 'RETURN', 'locationId' => $this->l1]]]])['data']['refundCreate'];
		$first = $refund(2, 'refund-1');
		$this->assertSame(['200.0', 'PARTIALLY_REFUNDED'], [$first['refund']['totalRefundedSet']['shopMoney']['amount'], $first['order']['displayFinancialStatus']], 'B27');
		$this->assertSame(11, $this->level($p['item'], $this->l1)['available'], 'B27: restocked');
		$this->assertSame($first, $refund(2, 'refund-1'), 'B21: the same key answers the same refund');
		$this->assertSame(11, $this->level($p['item'], $this->l1)['available'], 'B21: and restocks once');
		$this->assertSame('Quantity cannot refund more items than were purchased', $refund(2, 'refund-2')['userErrors'][0]['message'], 'B27');
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
			$this->assertContains($b['basis'], FakeShopifyBehaviours::BASES, $id);
			$cites = (bool)preg_match('#https://shopify\.dev/#', $b['source']);
			$assumes = str_contains($b['source'], 'ASSUMED');
			$this->assertSame(['verified' => [true, false], 'partly' => [true, true], 'assumed' => [$cites, true]][$b['basis']], [$cites, $assumes], "$id: a {$b['basis']} entry's source");
			if ($b['basis'] === 'assumed') {
				$this->assertStringStartsWith('ASSUMED', $b['source'], $id);
			}
			preg_match_all('#https://shopify\.dev/docs/api/admin-graphql/([^/]+)/#', $b['source'], $versions);
			$this->assertSame([], array_values(array_diff($versions[1], [FakeShopifyStore::API_VERSION])), "$id cites the API version the fake models");
			$this->assertNotSame('', $b['calibrated_by'], "$id names its calibration");
		}
		$this->assertStringContainsString("| B01 | orderCreate without options.inventoryBehaviour uses BYPASS", FakeShopifyBehaviours::markdown());
		$this->assertEqualsCanonicalizing(array_keys(FakeShopifyBehaviours::ALL), array_merge(...array_values(FakeShopifyBehaviours::byBasis())), 'byBasis() holds every entry once');
	}

	public function testTheReplayerSignsAtRunTimeAndArrangesADay(): void
	{
		$deliveries = array_map(fn(int $i): array => ['topic' => 'orders/paid', 'event_id' => "e$i", 'triggered_at' => '2026-11-02T08:00:00.000Z', 'attempt' => 1, 'raw' => "{\"id\":$i}"], range(1, 8));
		$replayer = new WebhookReplayer(['shop_key' => 'hml', 'domain' => 'hml-test.myshopify.com', 'api_version' => '2026-07'], 'not-a-real-secret');

		$a = $replayer->headers($deliveries[0]);
		$retry = $replayer->headers(['attempt' => 2] + $deliveries[0]);
		$this->assertTrue(shopify_verify_hmac('{"id":1}', $a['X-Shopify-Hmac-Sha256'], 'not-a-real-secret'));
		$this->assertSame(['orders/paid', 'hml-test.myshopify.com', 'e1', '2026-07'], [$a['X-Shopify-Topic'], $a['X-Shopify-Shop-Domain'], $a['X-Shopify-Event-Id'], $a['X-Shopify-API-Version']]);
		$this->assertSame($a, $retry, 'B13: a retry carries the same webhook id and event id');
		$this->assertNotSame($a['X-Shopify-Webhook-Id'], $replayer->headers($deliveries[1])['X-Shopify-Webhook-Id'], 'B13: another delivery, another webhook id');
		$this->assertSame('w-1', $replayer->headers(['webhook_id' => 'w-1'] + $deliveries[0])['X-Shopify-Webhook-Id'], 'the store\'s webhook id wins');
		$late = WebhookReplayer::arrange([['webhook_id' => 'w-1'] + $deliveries[0]], 'delayed');
		$this->assertSame(['w-1', 2], [$late[0]['webhook_id'], $late[0]['attempt']], 'a delayed retry keeps its webhook id');

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
