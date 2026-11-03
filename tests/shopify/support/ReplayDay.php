<?php
// tests/shopify/support/ReplayDay.php
//
// NF-1 offline replay (SD-766): a synthetic Shopify day, made by FakeShopifyStore from a fixed
// seed, replayed into a test tenant and the persisted tables compared after a second replay.
// build() makes the day; tests/shopify/fixtures/day.json keeps its deliveries (unsigned: the
// replayer signs at run time), graphql.json the store's state at the end of the day (so a
// handler that reads Shopify gets the same answers), expected.json the snapshot after one replay.
// SNAPSHOT is the one list of tables the replay compares, and registry() the job handlers it
// runs: a lane that lands adds its handler there, and its tables to SNAPSHOT if they are not
// listed yet, then refreshes the fixtures (build_replay_day.php, then run_replay.php record).
//
// History:
// 20261103 CL/LH SD-766: created.
// 20261103 CL/LH SD-766: review fixes: the day's refunds carry an idempotency key, the move needs no stock at its destination.

require_once __DIR__ . '/FakeShopifyStore.php';
require_once __DIR__ . '/WebhookReplayer.php';

final class ReplayDay
{
	public const SEED = 766;
	public const SHOP = ['shop_key' => 'hml', 'domain' => 'hml-test.myshopify.com', 'api_version' => '2026-07'];
	public const WEBHOOK_URI = 'https://saldi.example/api/shopify/webhook.php?shop=hml';
	public const FIXTURES = __DIR__ . '/../fixtures';

	/**
	 * The persisted state the second replay must leave as the first did, one query per part,
	 * without serial ids or wall-clock times. Tables a lane does not write yet are listed when they
	 * exist today, so they are compared from the day the lane lands.
	 */
	public const SNAPSHOT = [
		// SD-748: the inbox; a replayed delivery is a duplicate, never a second row
		'events' => "SELECT e.topic, e.event_id, e.state FROM shopify_events e ORDER BY 1, 2",
		// work waiting for a lane that is not built yet (see registry())
		'pending_jobs' => "SELECT type, dedupe_key, state FROM shopify_jobs WHERE state <> 'done' ORDER BY 1, 2",
		'open_errors' => 'SELECT kind, ref, severity FROM shopify_errors WHERE resolved_at IS NULL ORDER BY 1, 2',
		// SD-792: customers
		'debtors' => "SELECT a.kontonr, a.firmanavn, a.kontakt, a.addr1, a.addr2, a.postnr, a.bynavn, a.land, a.email, a.tlf, a.mobile, a.gruppe, a.betalingsbet, a.betalingsdage
			FROM adresser a WHERE a.art = 'D' AND a.id IN (SELECT adresser_id FROM shopify_customer_links) ORDER BY 1",
		'customer_links' => 'SELECT l.customer_gid, a.kontonr, l.source_updated_at, l.source_hash FROM shopify_customer_links l LEFT JOIN adresser a ON a.id = l.adresser_id ORDER BY 1',
		// SD-743: orders; SD-740: fulfilments and refunds; SD-746: credits
		'order_links' => 'SELECT order_gid, order_name, direction, state, ship_lager, flags FROM shopify_order_links ORDER BY 1',
		'orders' => 'SELECT o.ordrenr, o.art, o.status, o.kontonr, o.sum, o.moms, o.betalt, o.fakturanr FROM ordrer o WHERE o.id IN (SELECT ordre_id FROM shopify_order_links) ORDER BY 1, 2',
		'order_lines' => 'SELECT o.ordrenr, l.posnr, l.varenr, l.antal, l.pris, l.leveret, l.lager FROM ordrelinjer l JOIN ordrer o ON o.id = l.ordre_id
			WHERE o.id IN (SELECT ordre_id FROM shopify_order_links) ORDER BY 1, 2, 3',
		'fulfillments' => 'SELECT order_gid, fulfillment_gid, status, tracking_number FROM shopify_fulfillments ORDER BY 1, 2',
		'refunds' => 'SELECT order_gid, refund_gid, amount, currency FROM shopify_refunds ORDER BY 1, 2',
		'credits' => 'SELECT refund_gid, amount_minor FROM shopify_refund_allocations ORDER BY 1, 2',
		// SD-743: the ledger and payments of Shopify orders and debtors
		'ledger' => 'SELECT o.ordrenr, t.kontonr, t.debet, t.kredit, t.faktura FROM transaktioner t JOIN ordrer o ON o.id = t.ordre_id
			WHERE o.id IN (SELECT ordre_id FROM shopify_order_links) ORDER BY 1, 2, 3, 4',
		'payments' => 'SELECT p.konto_nr, p.faktnr, p.amount, p.udlignet FROM openpost p WHERE p.konto_id IN (SELECT adresser_id FROM shopify_customer_links) ORDER BY 1, 2, 3',
		// SD-739/SD-756: stock of linked items and what was pushed to the shops
		'stock' => 'SELECT v.varenr, s.lager, s.beholdning FROM lagerstatus s JOIN varer v ON v.id = s.vare_id WHERE v.id IN (SELECT vare_id FROM shopify_product_links) ORDER BY 1, 2',
		'stock_pushed' => 'SELECT v.varenr, s.lager, s.quantity FROM shopify_stock_state s JOIN varer v ON v.id = s.vare_id ORDER BY 1, 2',
	];

	/**
	 * The job handlers a replay runs, as tools/shopify_worker.php wires them. A handler that calls
	 * Shopify gets the fake store's shopify_graphql() opts as a fourth argument. Not built yet, so
	 * their jobs stay queued (pending_jobs): order.import, order.observed, order.status,
	 * order.cancelled (SD-742/SD-743), fulfillment.upsert and refund.upsert (SD-740).
	 *
	 * @param array<string, mixed> $graphql
	 * @return array<string, callable>
	 */
	public static function registry(array $graphql): array
	{
		$handlers = [
			'customer.upsert' => 'shopify_customer_upsert_job',
		];
		return array_map(fn(callable $handler): Closure => fn(array $job, float $deadline, callable $heartbeat): array
			=> $handler($job, $deadline, $heartbeat, $graphql), $handlers);
	}

	/**
	 * Worker passes on the bootstrapped tenant until no event is left to promote and no job to run.
	 *
	 * @return array{passes: int, promoted: int, done: int, failed: int, dead: int}
	 */
	public static function drain(string $db, array $graphql): array
	{
		$heartbeat = sys_get_temp_dir() . "/sd766-$db.heartbeat";
		$out = ['passes' => 0, 'promoted' => 0, 'done' => 0, 'failed' => 0, 'dead' => 0];
		try {
			for ($i = 0; $i < 50; $i++) {
				$run = shopify_worker_run(['db' => $db, 'heartbeat_file' => $heartbeat], self::registry($graphql),
					['events' => fn(): array => shopify_promote_events(500)], 30);
				$events = $run['producers']['events'];
				if (isset($events['error']) && is_string($events['error'])) {
					throw new RuntimeException('promotion failed: ' . $events['error']);
				}
				$promoted = (int)$events['queued'] + (int)$events['ignored'] + (int)$events['error'];
				$out['passes']++;
				$out['promoted'] += $promoted;
				foreach (['done', 'failed', 'dead'] as $k) {
					$out[$k] += $run[$k];
				}
				if ($promoted === 0 && $run['claimed'] === 0) {
					return $out;
				}
			}
			throw new RuntimeException('the worker did not drain in 50 passes');
		} finally {
			@unlink($heartbeat);
		}
	}

	/**
	 * A clean hml shop on the bootstrapped tenant: its Shopify rows gone, the orders and customers
	 * flows on ($flows adds others), a debtor group and number range for its customers, and a fresh
	 * random webhook secret that the replayer reads back.
	 */
	public static function prepareTenant(array $flows = []): void
	{
		// a fresh install has adresser.mobile (includes/opretfunc.php); an old template may not
		shopify_db_rows('ALTER TABLE adresser ADD COLUMN IF NOT EXISTS mobile varchar(15)');
		foreach (['shopify_customer_links', 'shopify_customer_exports', 'shopify_refund_allocations', 'shopify_refunds', 'shopify_fulfillments', 'shopify_order_links',
			'shopify_stock_state', 'shopify_product_links', 'shopify_events', 'shopify_jobs', 'shopify_errors', 'shopify_shops'] as $table) {
			shopify_db_rows("DELETE FROM $table");
		}
		shopify_db_rows("DELETE FROM adresser WHERE btrim(kontonr) ~ '^29766[0-9]{3}$'");
		shopify_db_rows("INSERT INTO grupper (beskrivelse, kodenr, art) SELECT 'SD766 Shopify', 76, 'DG' WHERE NOT EXISTS (SELECT 1 FROM grupper WHERE art = 'DG' AND kodenr = 76)");
		shopify_db_rows("DELETE FROM settings WHERE (var_grp = 'shopify' AND var_name LIKE 'customer_%') OR var_grp = 'shopify:hml'");
		shopify_db_rows("INSERT INTO settings (var_grp, var_name, var_value) VALUES ('shopify', 'customer_number_start', '29766000'),
			('shopify', 'customer_group', '76'), ('shopify', 'customer_payment_terms', 'Kontant'), ('shopify', 'customer_payment_days', '0')");
		$columns = array_merge(['orders_enabled', 'customers_enabled'], $flows);
		shopify_db_rows('INSERT INTO shopify_shops (shop_key, domain, is_primary, ' . implode(', ', $columns) . ') VALUES ($1, $2, true' . str_repeat(', true', count($columns)) . ')',
			[self::SHOP['shop_key'], self::SHOP['domain']]);
		shopify_db_rows("INSERT INTO settings (var_grp, var_name, var_value, var_description) VALUES ('shopify:hml', $1, $2, '')",
			[SHOPIFY_WEBHOOK_SECRET, bin2hex(random_bytes(16))]);
	}

	/** @return array<string, list<array<string, mixed>>> SNAPSHOT, read now */
	public static function snapshot(): array
	{
		return array_map(fn(string $sql): array => shopify_db_rows($sql), self::SNAPSHOT);
	}

	/**
	 * The synthetic day (pure; no database): a shop with two locations and four products, two
	 * account sign-ups, nine web orders (new and returning customers, one unpaid), fulfilments
	 * with a move and a partial one, a hold tag, two refunds and customer edits. Then the
	 * delivery order of a real day: three retries of a delivery (same event id), a customer
	 * update that arrives after the newer one, and an orders/paid before its orders/create.
	 *
	 * @return array{day: array, graphql: array}
	 */
	public static function build(int $seed = self::SEED): array
	{
		$random = new Random\Randomizer(new Random\Engine\Mt19937($seed));
		$store = new FakeShopifyStore();
		$d = $store->addShop(self::SHOP['shop_key'], ['domain' => self::SHOP['domain'], 'name' => 'HML (synthetic)', 'locations' => ['Lager 1', 'Lager 2']])['domain'];
		[$l1, $l2] = $store->locations($d);
		foreach (SHOPIFY_WEBHOOK_TOPICS as $topic) {
			$store->subscribe($d, $topic, self::WEBHOOK_URI);
		}
		$products = [];
		foreach (range(1, 4) as $i) {
			$products[] = $store->seedProduct($d, "replay-$i", "D766-$i", [$l1 => $random->getInt(2, 6), $l2 => $random->getInt(0, 4)],
				['title' => "Havemøbel $i", 'price' => sprintf('%d.00', 250 * $i)]);
		}
		$gql = function (string $query, array $vars) use ($store, $d, $random): array {
			$store->clock->now += $random->getInt(120, 1500);
			$answer = json_decode($store->request($d, $query, $vars)['body'], true);
			$root = array_key_first($answer['data'] ?? []);
			if ($root === null || ($answer['data'][$root]['userErrors'] ?? []) !== []) {
				throw new LogicException('ReplayDay: the day did not build: ' . json_encode($answer));
			}
			return $answer['data'][$root];
		};
		$person = fn(int $n): array => ['email' => "replay$n@example.dk", 'phone' => sprintf('+45200007%02d', $n), 'firstName' => 'Test', 'lastName' => "Kunde $n"];
		$signUp = 'mutation c($input: CustomerInput!) { customerCreate(input: $input) { customer { id } userErrors { message } } }';
		$customers = [];
		foreach ([1, 2] as $n) {
			$customers[] = $gql($signUp, ['input' => $person($n) + ['tags' => ['newsletter']]])['customer']['id'];
		}

		$create = 'mutation o($order: OrderCreateOrderInput!) { orderCreate(order: $order) { order { id fulfillmentOrders(first: 5) { nodes { id lineItems(first: 5) { nodes { id remainingQuantity } } } } } userErrors { message } } }';
		$orders = [];
		foreach (range(1, 9) as $i) {
			$lines = [];
			$total = 0;
			foreach ((array)$random->pickArrayKeys($products, $random->getInt(1, 2)) as $k) {
				$qty = $random->getInt(1, 2);
				$lines[] = ['variantId' => $products[$k]['variant'], 'quantity' => $qty];
				$total += $qty * 250 * ($k + 1);
			}
			if ($i <= 5) {
				$n = $i + 2;
				$customer = ['toUpsert' => $person($n) + ['addresses' => [['firstName' => 'Test', 'lastName' => "Kunde $n", 'address1' => "Havevej $n",
					'zip' => '2750', 'city' => 'Ballerup', 'country' => 'Denmark']]]];
			} else {
				$customer = ['toAssociate' => ['id' => $customers[$random->getInt(0, count($customers) - 1)]]];
			}
			$paid = $i === 9 ? [] : ['transactions' => [['kind' => 'SALE', 'status' => 'SUCCESS', 'gateway' => 'shopify_payments',
				'amountSet' => ['shopMoney' => ['amount' => "$total.00", 'currencyCode' => 'DKK']]]]];
			$order = $gql($create, ['order' => ['lineItems' => $lines, 'customer' => $customer, 'currency' => 'DKK', 'sourceName' => 'web'] + $paid])['order'];
			$orders[] = $order;
			if ($i <= 5) {
				$customers[] = $store->shop($d)['orders'][$order['id']]['customer'];
			}
		}

		$move = 'mutation m($id: ID!, $to: ID!) { fulfillmentOrderMove(id: $id, newLocationId: $to) { movedFulfillmentOrder { id } userErrors { message } } }';
		$fulfil = 'mutation f($f: FulfillmentInput!) { fulfillmentCreate(fulfillment: $f) { fulfillment { id } userErrors { message } } }';
		foreach (array_slice($orders, 0, 7) as $i => $order) {
			$fo = $order['fulfillmentOrders']['nodes'][0];
			if ($i === 1) {
				// the warehouse moves one order to the other location before it ships
				$fo['id'] = $gql($move, ['id' => $fo['id'], 'to' => $l2])['movedFulfillmentOrder']['id'];
			}
			$group = ['fulfillmentOrderId' => $fo['id']];
			if ($i === 6) {
				// partly shipped: one piece of the first line
				$group['fulfillmentOrderLineItems'] = [['id' => $fo['lineItems']['nodes'][0]['id'], 'quantity' => 1]];
			}
			$gql($fulfil, ['f' => ['lineItemsByFulfillmentOrder' => [$group], 'trackingInfo' => ['company' => 'GLS', 'number' => sprintf('D766TRACK%02d', $i + 1)]]]);
		}
		$gql('mutation t($id: ID!) { tagsAdd(id: $id, tags: ["saldi-hold"]) { node { id } userErrors { message } } }', ['id' => $orders[7]['id']]);

		$refund = 'mutation r($input: RefundInput!, $key: String!) { refundCreate(input: $input) @idempotent(key: $key) { refund { id } userErrors { message } } }';
		foreach ([[0, 'RETURN'], [2, 'NO_RESTOCK']] as [$k, $restock]) {
			$line = $store->shop($d)['orders'][$orders[$k]['id']]['lines'][0];
			$gql($refund, ['key' => "replay-refund-$k", 'input' => ['orderId' => $orders[$k]['id'], 'note' => 'retur', 'refundLineItems' => [
				['lineItemId' => $line['id'], 'quantity' => 1, 'restockType' => $restock, 'locationId' => $l1]]]]);
		}
		$update = 'mutation u($input: CustomerInput!) { customerUpdate(input: $input) { customer { id } userErrors { message } } }';
		$gql($update, ['input' => ['id' => $customers[0], 'lastName' => 'Kunde 1 Hansen']]);
		$gql($update, ['input' => ['id' => $customers[0], 'lastName' => 'Kunde 1 Jensen']]);
		$gql($update, ['input' => ['id' => $customers[3], 'tags' => ['vip']]]);

		$deliveries = WebhookReplayer::fromOutbox($store->takeDeliveries());
		// a real day's delivery order: the older of two updates of one customer arrives last ...
		$updates = array_keys(array_filter($deliveries, fn(array $x): bool => $x['topic'] === 'customers/update'
			&& json_decode($x['raw'], true)['admin_graphql_api_id'] === $customers[0]));
		[$older] = array_splice($deliveries, $updates[0], 1);
		$older['note'] = 'out of order: older than the update before it';
		$deliveries[] = $older;
		// ... one orders/paid comes before its orders/create ...
		$create = array_search('orders/create', array_column($deliveries, 'topic'), true);
		[$early] = array_splice($deliveries, $create, 1);
		$early['note'] = 'out of order: after its orders/paid';
		array_splice($deliveries, $create + 1, 0, [$early]);
		// ... and three deliveries are retried later, with the same event id
		foreach ($random->pickArrayKeys($deliveries, 3) as $k) {
			$deliveries[] = ['attempt' => 2, 'note' => 'retry of an earlier delivery'] + $deliveries[$k];
		}

		$state = $store->exportState();
		return [
			'day' => ['about' => 'A synthetic Shopify day for the NF-1 replay (SD-766), made by tests/shopify/support/build_replay_day.php from seed '
				. $seed . ' with FakeShopifyStore. Bodies are unsigned; WebhookReplayer signs them at run time with the test tenant\'s webhook secret.',
				'seed' => $seed, 'shop' => self::SHOP, 'deliveries' => $deliveries],
			'graphql' => ['about' => 'FakeShopifyStore::exportState() at the end of the day in day.json (SD-766): what a handler reading Shopify during the replay is answered from.']
				+ $state,
		];
	}

	/** @return array{day: array, graphql: array, expected: array|null} the committed fixtures */
	public static function load(): array
	{
		$read = fn(string $name): ?array => is_file(self::FIXTURES . "/$name.json") ? json_decode((string)file_get_contents(self::FIXTURES . "/$name.json"), true, 512, JSON_THROW_ON_ERROR) : null;
		return ['day' => $read('day'), 'graphql' => $read('graphql'), 'expected' => $read('expected')];
	}

	public static function encode(array $data): string
	{
		return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
	}
}
