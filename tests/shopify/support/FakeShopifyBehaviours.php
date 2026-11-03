<?php
// tests/shopify/support/FakeShopifyBehaviours.php
//
// The one registry of Shopify behaviours FakeShopifyStore encodes (SD-766). Real Shopify stays
// the truth: every entry names its source and the real-store check that calibrates it, mostly
// SD-764's day-1 checklist. basis says how far the docs carry it: verified (every claim is on the
// cited shopify.dev pages for API 2026-07), partly (the rule is documented, the details the source
// marks ASSUMED are not) or assumed (no page states it). The pages of verified entries and of the
// review fixes were read on 2026-11-03. The store calls
// FakeShopifyStore::uses('<id>') where it applies one, so a test can see which behaviours a
// scenario relied on, and FakeShopifyStoreTest checks that every id in the store's source is
// listed here, every entry is used, and each basis matches its source.
//
// History:
// 20261102 CL/LH SD-766: created.
// 20261103 CL/LH SD-766: review fixes: a basis per entry, sources re-read for API 2026-07, B31-B35 added
//                (move bookkeeping, metafield compareDigest, one location per fulfilment, order taxes, inventoryActivate).

final class FakeShopifyBehaviours
{
	private const DOCS = 'https://shopify.dev/docs/api/admin-graphql/2026-07/';
	private const LIMITS = 'https://shopify.dev/docs/apps/build/apis/graphql-admin/rate-limits';
	private const IDEMPOTENCY = 'https://shopify.dev/docs/apps/build/apis/graphql-admin/implementing-idempotency';
	private const DELIVERIES = 'https://shopify.dev/docs/apps/build/webhooks/verify-deliveries';
	private const HEADERS = 'https://shopify.dev/docs/apps/build/webhooks/delivery-structure';

	public const BASES = ['verified', 'partly', 'assumed'];

	/** @var array<string, array{behaviour: string, basis: string, source: string, calibrated_by: string}> */
	public const ALL = [
		'B01' => [
			'behaviour' => 'orderCreate without options.inventoryBehaviour uses BYPASS: no inventory quantity changes; DECREMENT_IGNORING_POLICY and DECREMENT_OBEYING_POLICY move each line\'s quantity from available to committed at its assigned location',
			'basis' => 'partly',
			'source' => self::DOCS . 'input-objects/OrderCreateOptionsInput (default BYPASS); the decrement bookkeeping ASSUMED',
			'calibrated_by' => 'SD-764: BYPASS store order, read inventory before and after',
		],
		'B02' => [
			'behaviour' => 'orderCreate sends no order confirmation unless options.sendReceipt is true (the fake records it in the shop\'s receipts); sendFulfillmentReceipt defaults to false too',
			'basis' => 'verified',
			'source' => self::DOCS . 'input-objects/OrderCreateOptionsInput (sendReceipt and sendFulfillmentReceipt default false)',
			'calibrated_by' => 'SD-764: BYPASS store order (no mail in the order timeline)',
		],
		'B03' => [
			'behaviour' => 'fulfillmentOrderMove fails with a userError on [newLocationId] when the destination does not stock a moved item (has no inventory level for it); a destination that stocks it takes the move whatever its available quantity, zero or less than moved; one unstocked line refuses the whole move',
			'basis' => 'partly',
			'source' => self::DOCS . 'mutations/fulfillmentOrderMove ("The destination location doesn\'t stock the requested inventory item"); no quantity threshold ASSUMED (the page names none), refusing the whole move ASSUMED (its partial example moves only the stocked lines), message and null code ASSUMED',
			'calibrated_by' => 'SD-764: fulfillmentOrderMove to an unstocked, an activated-at-zero and an understocked destination',
		],
		'B04' => [
			'behaviour' => 'fulfillmentOrderMove of all remaining lines of a fulfillment order with nothing fulfilled reassigns that fulfillment order; moving some lines, a partial quantity or what is left after a fulfilment makes a new fulfillment order at the destination, fulfilled quantities stay; an original left with nothing to fulfil is closed',
			'basis' => 'partly',
			'source' => self::DOCS . 'mutations/fulfillmentOrderMove (whole move, fulfilled lines stay, moved order differs once something is fulfilled); partial quantities and closing the emptied original ASSUMED',
			'calibrated_by' => 'SD-764: fulfillmentOrderMove with a partial quantity',
		],
		'B05' => [
			'behaviour' => 'fulfillmentOrderMove fails when the fulfillment order\'s request status is SUBMITTED, ACCEPTED, CANCELLATION_REQUESTED or CANCELLATION_REJECTED, when progress was reported on it (code CANNOT_MOVE_FULFILLMENT_ORDER_WITH_REPORTED_PROGRESS), or when it is not OPEN or IN_PROGRESS; it moves after an accepted cancellation (CANCELLATION_ACCEPTED) and moves the rest of a partly fulfilled one',
			'basis' => 'partly',
			'source' => self::DOCS . 'mutations/fulfillmentOrderMove (the four request statuses, closed orders, reported progress, re-assignment after an accepted cancellation; the code from the 2026-07 schema); refusing CANCELLED, INCOMPLETE, SCHEDULED and ON_HOLD ASSUMED, messages ASSUMED',
			'calibrated_by' => 'SD-764: fulfillmentOrderMove with work in progress',
		],
		'B06' => [
			'behaviour' => 'metafieldsSet takes at most 25 metafields per call and is atomic: a call with an error sets nothing; more than 25 answers a userError (code LESS_THAN_OR_EQUAL_TO); a payload over 10 MB fails loudly in the fake',
			'basis' => 'partly',
			'source' => self::DOCS . 'mutations/metafieldsSet (25, atomic, 10MB payload; read on the latest, 2026-10 page); the error code ASSUMED, Shopify\'s answer over 10 MB not modelled',
			'calibrated_by' => 'calibration probe metafields_over_limit (dev store); no SD-764 item',
		],
		'B07' => [
			'behaviour' => 'order line items are not metafield owners: metafieldsSet with a LineItem ownerId answers a userError (code INVALID_TYPE) and sets nothing; LineItem has no metafield field',
			'basis' => 'partly',
			'source' => self::DOCS . 'enums/MetafieldOwnerType (no line item owner; the 2026-07 schema fixture has no LineItem.metafield); the error code ASSUMED',
			'calibrated_by' => 'calibration probe metafield_on_line_item (dev store); no SD-764 item',
		],
		'B08' => [
			'behaviour' => 'a phone number and an email belong to one customer per shop: customerCreate or customerUpdate with one another customer has answers the userError field [phone] "Phone has already been taken" ([email] "Email has already been taken"); a customer with no name, phone or email answers "Customer must have a name, phone number or email address" (field null)',
			'basis' => 'partly',
			'source' => self::DOCS . 'input-objects/CustomerInput ("unique" email and phone) and ' . self::DOCS . 'mutations/customerCreate (the no-name message); per shop and the taken messages ASSUMED',
			'calibrated_by' => 'SD-764: customerCreate with a duplicate phone and with a duplicate email',
		],
		'B09' => [
			'behaviour' => 'cost: scalars and enums 0, an object 1, a connection 2 + first (or last) times its node cost, fragments counted on their type (every fragment of an interface or union); a mutation root field costs 10 plus its payload\'s selection; actualQueryCost equals requestedQueryCost',
			'basis' => 'partly',
			'source' => self::LIMITS . ' (object 1, scalar 0, connections sized by first/last, mutation 10); how 10 adds to the payload, lists of objects at 1, every fragment counted and actual = requested ASSUMED',
			'calibrated_by' => 'calibration probes (requestedQueryCost per probe, extensions.cost shape); Shopify-GraphQL-Cost-Debug on the dev store',
		],
		'B10' => [
			'behaviour' => 'throttle bucket per shop: maximumAvailable 2000, restoreRate 100 points a second; a request costing more than is available answers a THROTTLED error and is not executed',
			'basis' => 'assumed',
			'source' => 'ASSUMED: the sandboxes\' plans and bucket sizes are not known (' . self::LIMITS . ' gives restore rates per plan, 100/s on Standard, and no bucket size); the THROTTLED shape is what the client handles',
			'calibrated_by' => 'SD-764: throttleStatus (maximumAvailable, restoreRate) and plan tier per shop; calibration records the dev store\'s',
		],
		'B11' => [
			'behaviour' => 'a single query or mutation over 1000 points is refused before it runs, with MAX_COST_EXCEEDED',
			'basis' => 'partly',
			'source' => self::LIMITS . ' (1,000 points, enforced on the requested cost before execution); the error code ASSUMED',
			'calibrated_by' => 'none needed for the limit (documented); the error shape on the dev store',
		],
		'B12' => [
			'behaviour' => 'webhooks are delivered only for topics the shop has a subscription to, one delivery per subscription, as REST-shaped JSON bodies',
			'basis' => 'partly',
			'source' => self::DELIVERIES . ' (each subscription gets its own delivery); the body fields (a subset of Shopify\'s) ASSUMED',
			'calibrated_by' => 'SD-764: webhooks to the staging URL, fire an order and a customer',
		],
		'B13' => [
			'behaviour' => 'webhook headers: X-Shopify-Topic, X-Shopify-Hmac-Sha256 (base64 HMAC-SHA256 of the raw body with the app secret), X-Shopify-Shop-Domain, X-Shopify-Webhook-Id (one per delivery: each subscription\'s delivery has its own, every retry of it the same), X-Shopify-Event-Id (one per merchant action, shared by the subscriptions it reaches), X-Shopify-Triggered-At, X-Shopify-API-Version',
			'basis' => 'partly',
			'source' => self::HEADERS . ' (the headers) and ' . self::DELIVERIES . ' (several subscriptions to a topic: different webhook ids, one event id; the webhook id detects a retried duplicate); the same webhook id on a retry is implied there, not stated: ASSUMED',
			'calibrated_by' => 'SD-764: check HMAC and headers',
		],
		'B14' => [
			'behaviour' => 'X-Shopify-Event-Id differs per topic: orders/create and orders/paid of one order carry different event ids',
			'basis' => 'assumed',
			'source' => 'ASSUMED (' . self::HEADERS . ' says the event id is "shared across all deliveries produced by the same merchant action", which may span topics; Saldi\'s inbox keys on shop, topic and event id either way)',
			'calibrated_by' => 'SD-764: whether X-Shopify-Event-Id is shared across topics',
		],
		'B15' => [
			'behaviour' => 'orderCreate fires orders/create, then orders/paid when the order is created paid; a customer the order creates fires customers/create first',
			'basis' => 'partly',
			'source' => self::DOCS . 'enums/WebhookSubscriptionTopic (the topics; the 2026-07 schema fixture lists them); that orderCreate fires them and their order ASSUMED',
			'calibrated_by' => 'SD-764: fire an order and a customer',
		],
		'B16' => [
			'behaviour' => 'fulfillmentCreate fires fulfillments/create and orders/updated, and orders/fulfilled once no unfulfilled quantity is left',
			'basis' => 'partly',
			'source' => self::DOCS . 'enums/WebhookSubscriptionTopic (the topics; the 2026-07 schema fixture lists them); which of them fulfillmentCreate fires ASSUMED',
			'calibrated_by' => 'SD-764: BYPASS store order, create and fulfil (watch the inbox)',
		],
		'B17' => [
			'behaviour' => 'fulfilling a BYPASS order changes no inventory; fulfilling a decremented order lowers on_hand by the quantity and committed by what the line had committed (available by the rest)',
			'basis' => 'assumed',
			'source' => 'ASSUMED',
			'calibrated_by' => 'SD-764: BYPASS store order, create, fulfil, read inventory before and after',
		],
		'B18' => [
			'behaviour' => 'order routing: each line is assigned to the first active location (in creation order) with enough available stock, else the first location; lines of one location share a fulfillment order',
			'basis' => 'assumed',
			'source' => 'ASSUMED (the sandboxes route by their own rules)',
			'calibrated_by' => 'SD-764 / open question 14 (sandbox routing)',
		],
		'B19' => [
			'behaviour' => 'a connection needs first or last, at most 250 a page; cursors are opaque strings',
			'basis' => 'partly',
			'source' => 'https://shopify.dev/docs/api/usage/pagination-graphql (at most 250); first or last being required and the cursor format ASSUMED',
			'calibrated_by' => 'calibration probes connection_without_first, locations, products (pageInfo shape)',
		],
		'B20' => [
			'behaviour' => 'inventorySetQuantities sets absolute quantities by name available or on_hand (the other moves by the same delta); every quantity needs changeFromQuantity (null skips the check); a stale one answers CHANGE_FROM_QUANTITY_STALE and sets nothing; an item not stocked at the location answers ITEM_NOT_STOCKED_AT_LOCATION',
			'basis' => 'partly',
			'source' => self::DOCS . 'mutations/inventorySetQuantities (absolute set, changeFromQuantity required as of 2026-04 with null to skip, CHANGE_FROM_QUANTITY_STALE); the other codes from the 2026-07 schema and when they fire ASSUMED, the error for an omitted changeFromQuantity ASSUMED',
			'calibrated_by' => 'none in SD-764; SD-756 (stock push) on the sandbox',
		],
		'B21' => [
			'behaviour' => '@idempotent(key:) is taken by inventorySetQuantities, refundCreate and inventoryActivate (FakeShopifyStore::IDEMPOTENT) and required on each: without a key the request is refused with a top-level error and nothing runs; a key repeated within 24 hours answers the earlier response and changes nothing; the same key with other arguments answers the userError IDEMPOTENCY_KEY_PARAMETER_MISMATCH; the directive on any other field fails loudly in the fake',
			'basis' => 'partly',
			'source' => self::DOCS . 'mutations/inventorySetQuantities, ' . self::DOCS . 'mutations/refundCreate and ' . self::DOCS . 'mutations/inventoryActivate (required as of 2026-04); ' . self::IDEMPOTENCY . ' (24 hours, cached response, the mismatch userError); the error for a missing key ASSUMED, a key scoped per shop and root field ASSUMED',
			'calibrated_by' => 'calibration probe inventory_set_without_key (dev store); SD-756 (stock push) on the sandbox',
		],
		'B22' => [
			'behaviour' => 'productSet: identifier id updates that product or answers PRODUCT_DOES_NOT_EXIST, identifier handle updates the product with that handle or creates one; a variants list replaces the variants (one left out is deleted); fields the input leaves out keep their value; new variants are stocked at every location with 0',
			'basis' => 'partly',
			'source' => self::DOCS . 'mutations/productSet (list fields replace, omitted fields stay); identifier behaviour, the error code and stocking at every location ASSUMED',
			'calibrated_by' => 'SD-764: productSet with DRAFT by id and by handle, description survives an update',
		],
		'B23' => [
			'behaviour' => 'search syntax field:value, terms ANDed: products handle/sku/status/tag, productVariants sku (prefix, case-insensitive, like Shopify\'s loose match)/product_id, orders name/tag/source_identifier (exact), customers email/phone/tag, fulfillmentOrders status/assigned_location_id; any other term fails loudly',
			'basis' => 'partly',
			'source' => 'https://shopify.dev/docs/api/usage/search-syntax (the syntax); the matching per field and source_identifier being honoured ASSUMED',
			'calibrated_by' => 'SD-764: search by tag and by source_identifier: (honoured, ignored or rejected)',
		],
		'B24' => [
			'behaviour' => 'a request with a missing or wrong access token answers HTTP 401 with errors as a string',
			'basis' => 'partly',
			'source' => 'https://shopify.dev/docs/api/usage/response-codes (401 for missing or incorrect credentials); the body shape ASSUMED',
			'calibrated_by' => 'SD-764 connectivity; K05 on the sandbox',
		],
		'B25' => [
			'behaviour' => 'tagsAdd/tagsRemove treat tags as a set (trimmed, no duplicates) on orders, customers and products, and fire the resource\'s */updated topic',
			'basis' => 'partly',
			'source' => self::DOCS . 'mutations/tagsAdd; the webhook ASSUMED',
			'calibrated_by' => 'none in SD-764; SD-759 (hold tags) on the sandbox',
		],
		'B26' => [
			'behaviour' => 'orderUpdate replaces note, customAttributes and tags with the input (it does not append) and fires orders/updated',
			'basis' => 'partly',
			'source' => self::DOCS . 'mutations/orderUpdate; the webhook ASSUMED',
			'calibrated_by' => 'none in SD-764; SD-759 (noteAppend) on the sandbox',
		],
		'B27' => [
			'behaviour' => 'refundCreate records the refund (at most the unrefunded quantity, without tax), restocks RETURN/CANCEL lines at locationId, and fires refunds/create and orders/updated',
			'basis' => 'partly',
			'source' => self::DOCS . 'mutations/refundCreate (refunds line items with optional restocking); restock bookkeeping, no tax on the refund and the webhooks ASSUMED',
			'calibrated_by' => 'none in SD-764; SD-746 (credits) on the sandbox',
		],
		'B28' => [
			'behaviour' => 'webhookSubscriptionCreate for a topic and uri the app already subscribes answers the userError "Address for this topic has already been taken"',
			'basis' => 'assumed',
			'source' => 'ASSUMED (message as FakeWebhookShop has it)',
			'calibrated_by' => 'SD-764: webhooks to the staging URL (register twice)',
		],
		'B29' => [
			'behaviour' => 'tagsAdd, orderUpdate, metafieldsSet and the other mutations on an id the shop does not have answer a userError on field [id] (or [metafields, i, ownerId]) and change nothing',
			'basis' => 'assumed',
			'source' => 'ASSUMED',
			'calibrated_by' => 'calibration probe move_unknown_fulfillment_order (dev store)',
		],
		'B30' => [
			'behaviour' => 'shop.plan.partnerDevelopment is true on a development store, as the sandboxes are taken to be, and false on a paying (production) shop; the runner\'s K05 refuses a shop that answers false',
			'basis' => 'partly',
			'source' => self::DOCS . 'objects/ShopPlan ("Whether the shop is a partner development shop"); the sandboxes being development stores ASSUMED',
			'calibrated_by' => 'SD-764 connectivity (record plan per shop); calibration probe shop',
		],
		'B31' => [
			'behaviour' => 'fulfillmentOrderMove of a decremented order takes the moved quantity\'s commitment along: committed goes back to available at the original location and is taken from available at the destination (on_hand stays at both), for a partial move only the moved quantity; a BYPASS order moves no stock',
			'basis' => 'assumed',
			'source' => 'ASSUMED',
			'calibrated_by' => 'SD-764 (to add): DECREMENT order, move, read inventory at both locations, fulfil, read again',
		],
		'B32' => [
			'behaviour' => 'metafieldsSet compareDigest: a digest sets the metafield only when it is unchanged since the digest was read, null only when it does not exist yet, leaving it out skips the check; a mismatch answers a userError on [metafields, i, compareDigest] (code STALE_OBJECT) and sets nothing of the call; the digest is opaque (read it, never compute it)',
			'basis' => 'partly',
			'source' => self::DOCS . 'mutations/metafieldsSet (compare-and-set since 2024-07, null for a new metafield, omitted opts out, atomic; read on the latest, 2026-10 page, the 2026-07 schema has compareDigest); the code and message ASSUMED',
			'calibrated_by' => 'SD-764 (to add): metafieldsSet with a stale digest and with null on an existing metafield',
		],
		'B33' => [
			'behaviour' => 'fulfillmentCreate takes fulfillment orders of one order assigned to one location; fulfillment orders of two locations answer a userError and change nothing, so a caller splits them by location',
			'basis' => 'partly',
			'source' => self::DOCS . 'mutations/fulfillmentCreate ("associated with the same Order and are assigned to the same Location"); the message ASSUMED',
			'calibrated_by' => 'SD-764 (to add): fulfillmentCreate across two locations',
		],
		'B34' => [
			'behaviour' => 'orderCreate taxes come from the input only: taxesIncluded defaults to false; tax lines go on the order or on its lines, not both; each tax line\'s priceSet is its amount, totalTaxSet their sum, totalPriceSet the lines plus the tax unless taxesIncluded; no tax lines, no tax. A tax line without priceSet, order-level tax lines read per line, shippingLines, discountCode and presentmentCurrency fail loudly (not modelled)',
			'basis' => 'partly',
			'source' => self::DOCS . 'input-objects/OrderCreateOrderInput (taxesIncluded default false, order or lines not both, order lines split across taxable lines) and ' . self::DOCS . 'mutations/orderCreate (examples: totalTaxSet is the given tax); no tax without tax lines ASSUMED (the docs show no automatic tax), the message ASSUMED',
			'calibrated_by' => 'SD-764 (to add): orderCreate with and without tax lines, taxesIncluded true and false',
		],
		'B35' => [
			'behaviour' => 'inventoryActivate stocks an item at a location: its inventory level starts with available and on_hand at zero unless given; an item already stocked there is answered as it is',
			'basis' => 'partly',
			'source' => self::DOCS . 'mutations/inventoryActivate (creates the inventory level, quantities default to zero, @idempotent required as of 2026-04); answering an active level unchanged ASSUMED',
			'calibrated_by' => 'none in SD-764; SD-756 (stock push) on the sandbox',
		],
	];

	/** @return array{behaviour: string, basis: string, source: string, calibrated_by: string} */
	public static function get(string $id): array
	{
		if (!isset(self::ALL[$id])) {
			throw new LogicException("FakeShopifyBehaviours: no behaviour $id");
		}
		return self::ALL[$id];
	}

	/** @return array<string, list<string>> basis => ids */
	public static function byBasis(): array
	{
		$out = array_fill_keys(self::BASES, []);
		foreach (self::ALL as $id => $b) {
			$out[$b['basis']][] = $id;
		}
		return $out;
	}

	/** The registry as a Markdown table, for the PR body and the calibration report. */
	public static function markdown(): string
	{
		$out = "| id | behaviour | basis | source | calibrated by |\n|---|---|---|---|---|\n";
		foreach (self::ALL as $id => $b) {
			$out .= "| $id | " . implode(' | ', array_map(fn(string $s): string => str_replace('|', '/', $s), [$b['behaviour'], $b['basis'], $b['source'], $b['calibrated_by']])) . " |\n";
		}
		return $out;
	}
}
