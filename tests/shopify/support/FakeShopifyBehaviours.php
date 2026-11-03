<?php
// tests/shopify/support/FakeShopifyBehaviours.php
//
// The one registry of Shopify behaviours FakeShopifyStore encodes (SD-766). Real Shopify stays
// the truth: every entry names its source (a shopify.dev page, "verified 2026-10-06" for the facts
// checked on shopify.dev for API 2026-07, or ASSUMED) and the real-store check that calibrates it,
// mostly SD-764's day-1 checklist. The store calls FakeShopifyStore::uses('<id>') where it applies
// one, so a test can see which behaviours a scenario relied on, and FakeShopifyStoreTest checks
// that every id in the store's source is listed here and every entry is used.
//
// History:
// 20261102 CL/LH SD-766: created.

final class FakeShopifyBehaviours
{
	private const DOCS = 'https://shopify.dev/docs/api/admin-graphql/2026-07/mutations/';

	/** @var array<string, array{behaviour: string, source: string, calibrated_by: string}> */
	public const ALL = [
		'B01' => [
			'behaviour' => 'orderCreate without options.inventoryBehaviour uses BYPASS: no inventory quantity changes; DECREMENT_IGNORING_POLICY and DECREMENT_OBEYING_POLICY move available to committed at the assigned location',
			'source' => 'verified 2026-10-06; ' . self::DOCS . 'orderCreate (decrement bookkeeping ASSUMED)',
			'calibrated_by' => 'SD-764: BYPASS store order, read inventory before and after',
		],
		'B02' => [
			'behaviour' => 'orderCreate sends no order receipt and no fulfilment receipt unless options.sendReceipt / sendFulfillmentReceipt is true',
			'source' => 'verified 2026-10-06; ' . self::DOCS . 'orderCreate',
			'calibrated_by' => 'SD-764: BYPASS store order (no mail in the order timeline)',
		],
		'B03' => [
			'behaviour' => 'fulfillmentOrderMove fails with a userError when the destination location has less available stock than the quantity moved, or does not stock the item',
			'source' => 'verified 2026-10-06 for zero stock; ' . self::DOCS . 'fulfillmentOrderMove (threshold above zero, message and null code ASSUMED)',
			'calibrated_by' => 'SD-764: fulfillmentOrderMove to a zero-stock destination',
		],
		'B04' => [
			'behaviour' => 'fulfillmentOrderMove of some lines or a partial quantity leaves the rest on the original fulfillment order and moves the rest to a new one; a full move of an order with nothing fulfilled reassigns the same fulfillment order',
			'source' => self::DOCS . 'fulfillmentOrderMove (partial quantity ASSUMED: the page is ambiguous)',
			'calibrated_by' => 'SD-764: fulfillmentOrderMove with a partial quantity',
		],
		'B05' => [
			'behaviour' => 'fulfillmentOrderMove fails when the fulfillment order is closed or cancelled, has a request status other than UNSUBMITTED, or has reported progress (IN_PROGRESS: code CANNOT_MOVE_FULFILLMENT_ORDER_WITH_REPORTED_PROGRESS)',
			'source' => 'verified 2026-10-06; ' . self::DOCS . 'fulfillmentOrderMove (messages ASSUMED)',
			'calibrated_by' => 'SD-764: fulfillmentOrderMove with work in progress',
		],
		'B06' => [
			'behaviour' => 'metafieldsSet takes at most 25 metafields per call; a larger call sets nothing and answers a userError (code LESS_THAN_OR_EQUAL_TO)',
			'source' => 'verified 2026-10-06 for the limit; ' . self::DOCS . 'metafieldsSet (error shape ASSUMED)',
			'calibrated_by' => 'calibration probe metafields_over_limit (dev store); no SD-764 item',
		],
		'B07' => [
			'behaviour' => 'order line items are not metafield owners: metafieldsSet with a LineItem ownerId answers a userError (code INVALID_TYPE) and sets nothing; LineItem has no metafield field',
			'source' => 'verified 2026-10-06 for the owner types; ' . self::DOCS . 'metafieldsSet (error code ASSUMED)',
			'calibrated_by' => 'calibration probe metafield_on_line_item (dev store); no SD-764 item',
		],
		'B08' => [
			'behaviour' => 'a phone number belongs to one customer per shop: customerCreate or customerUpdate with a phone another customer has answers the userError field [phone] "Phone has already been taken"; the same for email ("Email has already been taken")',
			'source' => self::DOCS . 'customerCreate (messages ASSUMED)',
			'calibrated_by' => 'SD-764: customerCreate with a duplicate phone and with a duplicate email',
		],
		'B09' => [
			'behaviour' => 'cost: every mutation root field costs 10; a query costs 1 per object and 2 + first (or last) times the node cost per connection; actualQueryCost equals requestedQueryCost',
			'source' => 'verified 2026-10-06 for mutations; https://shopify.dev/docs/api/usage/limits#cost-calculation (query formula approximated, actual cost ASSUMED)',
			'calibrated_by' => 'calibration probes (extensions.cost shape); SD-764 throttleStatus',
		],
		'B10' => [
			'behaviour' => 'throttle bucket per shop: maximumAvailable 2000, restoreRate 100 points a second (Standard plan); a request costing more than is available answers THROTTLED and is not executed',
			'source' => 'ASSUMED for the three sandboxes (verified 2026-10-06: restore 100/s Standard, bucket 10x restore)',
			'calibrated_by' => 'SD-764: throttleStatus (maximumAvailable, restoreRate) and plan tier per shop',
		],
		'B11' => [
			'behaviour' => 'a single query over 1000 points is refused with MAX_COST_EXCEEDED',
			'source' => 'verified 2026-10-06; https://shopify.dev/docs/api/usage/limits',
			'calibrated_by' => 'none needed (documented limit)',
		],
		'B12' => [
			'behaviour' => 'webhooks are delivered only for topics the shop has a subscription to, one delivery per subscription, as REST-shaped JSON bodies',
			'source' => 'https://shopify.dev/docs/apps/build/webhooks/subscribe',
			'calibrated_by' => 'SD-764: webhooks to the staging URL, fire an order and a customer',
		],
		'B13' => [
			'behaviour' => 'webhook headers: X-Shopify-Topic, X-Shopify-Hmac-Sha256 (base64 HMAC-SHA256 of the raw body with the app secret), X-Shopify-Shop-Domain, X-Shopify-Webhook-Id (new per delivery attempt), X-Shopify-Event-Id (the same on every retry), X-Shopify-Triggered-At, X-Shopify-API-Version',
			'source' => 'verified 2026-10-06; https://shopify.dev/docs/apps/build/webhooks/verify-webhooks-hmac',
			'calibrated_by' => 'SD-764: check HMAC and headers',
		],
		'B14' => [
			'behaviour' => 'X-Shopify-Event-Id differs per topic: orders/create and orders/paid of one order carry different event ids',
			'source' => 'ASSUMED',
			'calibrated_by' => 'SD-764: whether X-Shopify-Event-Id is shared across topics',
		],
		'B15' => [
			'behaviour' => 'orderCreate fires orders/create, then orders/paid when the order is created paid; a customer the order creates fires customers/create first',
			'source' => 'https://shopify.dev/docs/api/webhooks?reference=graphql (order of deliveries ASSUMED)',
			'calibrated_by' => 'SD-764: fire an order and a customer',
		],
		'B16' => [
			'behaviour' => 'fulfillmentCreate fires fulfillments/create and orders/updated, and orders/fulfilled once no unfulfilled quantity is left',
			'source' => 'https://shopify.dev/docs/api/webhooks?reference=graphql (which topics fire ASSUMED)',
			'calibrated_by' => 'SD-764: BYPASS store order, create and fulfil (watch the inbox)',
		],
		'B17' => [
			'behaviour' => 'fulfilling a BYPASS order changes no inventory; fulfilling a decremented order lowers on_hand and committed',
			'source' => 'ASSUMED',
			'calibrated_by' => 'SD-764: BYPASS store order, create, fulfil, read inventory before and after',
		],
		'B18' => [
			'behaviour' => 'order routing: each line is assigned to the first active location (in creation order) with enough available stock, else the first location; lines of one location share a fulfillment order',
			'source' => 'ASSUMED (the sandboxes route by their own rules)',
			'calibrated_by' => 'SD-764 / open question 14 (sandbox routing)',
		],
		'B19' => [
			'behaviour' => 'a connection needs first or last, at most 250 a page; cursors are opaque strings',
			'source' => 'https://shopify.dev/docs/api/usage/pagination-graphql',
			'calibrated_by' => 'calibration probes (pageInfo shape)',
		],
		'B20' => [
			'behaviour' => 'inventorySetQuantities sets absolute quantities by name available or on_hand (the other moves by the same delta); a stale changeFromQuantity answers CHANGE_FROM_QUANTITY_STALE and sets nothing; an item not stocked at the location answers ITEM_NOT_STOCKED_AT_LOCATION',
			'source' => 'verified 2026-10-06; ' . self::DOCS . 'inventorySetQuantities (error codes from the 2026-07 schema, when they fire ASSUMED)',
			'calibrated_by' => 'none in SD-764; SD-756 (stock push) on the sandbox',
		],
		'B21' => [
			'behaviour' => 'a mutation field with @idempotent(key:) that repeats an earlier key answers the earlier result and changes nothing; the same key with other arguments is refused (IDEMPOTENCY_KEY_PARAMETER_MISMATCH, here a top-level error)',
			'source' => 'https://shopify.dev/docs/api/usage/idempotent-requests (ASSUMED for every mutation the store implements; the mismatch code is in the 2026-07 schema, its error shape ASSUMED)',
			'calibrated_by' => 'none in SD-764; SD-756 (stock push) on the sandbox',
		],
		'B22' => [
			'behaviour' => 'productSet: identifier id updates that product or answers PRODUCT_DOES_NOT_EXIST, identifier handle updates the product with that handle or creates one; a variants list replaces the variants (one left out is deleted); fields the input leaves out keep their value; new variants are stocked at every location with 0',
			'source' => 'verified 2026-10-06; ' . self::DOCS . 'productSet (stocking at every location ASSUMED)',
			'calibrated_by' => 'SD-764: productSet with DRAFT by id and by handle, description survives an update',
		],
		'B23' => [
			'behaviour' => 'search syntax field:value, terms ANDed: products handle/sku/status/tag, productVariants sku (prefix, case-insensitive, like Shopify\'s loose match)/product_id, orders name/tag/source_identifier (exact), customers email/phone/tag, fulfillmentOrders status/assigned_location_id; any other term fails loudly',
			'source' => 'https://shopify.dev/docs/api/usage/search-syntax (source_identifier honoured ASSUMED)',
			'calibrated_by' => 'SD-764: search by tag and by source_identifier: (honoured, ignored or rejected)',
		],
		'B24' => [
			'behaviour' => 'a request with a missing or wrong access token answers HTTP 401 with errors as a string',
			'source' => 'https://shopify.dev/docs/api/usage/response-codes',
			'calibrated_by' => 'SD-764 connectivity; K05 on the sandbox',
		],
		'B25' => [
			'behaviour' => 'tagsAdd/tagsRemove treat tags as a set (trimmed, no duplicates) on orders, customers and products, and fire the resource\'s */updated topic',
			'source' => self::DOCS . 'tagsAdd (webhook ASSUMED)',
			'calibrated_by' => 'none in SD-764; SD-759 (hold tags) on the sandbox',
		],
		'B26' => [
			'behaviour' => 'orderUpdate replaces note, customAttributes and tags with the input (it does not append) and fires orders/updated',
			'source' => self::DOCS . 'orderUpdate (webhook ASSUMED)',
			'calibrated_by' => 'none in SD-764; SD-759 (noteAppend) on the sandbox',
		],
		'B27' => [
			'behaviour' => 'refundCreate records the refund (at most the unrefunded quantity), restocks RETURN/CANCEL lines at locationId, and fires refunds/create and orders/updated',
			'source' => self::DOCS . 'refundCreate (restock and webhooks ASSUMED)',
			'calibrated_by' => 'none in SD-764; SD-746 (credits) on the sandbox',
		],
		'B28' => [
			'behaviour' => 'webhookSubscriptionCreate for a topic and uri the app already subscribes answers the userError "Address for this topic has already been taken"',
			'source' => 'ASSUMED (message as FakeWebhookShop has it)',
			'calibrated_by' => 'SD-764: webhooks to the staging URL (register twice)',
		],
		'B29' => [
			'behaviour' => 'tagsAdd, orderUpdate, metafieldsSet and the other mutations on an id the shop does not have answer a userError on field [id] (or [metafields, i, ownerId]) and change nothing',
			'source' => 'ASSUMED',
			'calibrated_by' => 'calibration probe move_unknown_fulfillment_order (dev store)',
		],
	];

	/** @return array{behaviour: string, source: string, calibrated_by: string} */
	public static function get(string $id): array
	{
		if (!isset(self::ALL[$id])) {
			throw new LogicException("FakeShopifyBehaviours: no behaviour $id");
		}
		return self::ALL[$id];
	}

	/** The registry as a Markdown table, for the PR body and the calibration report. */
	public static function markdown(): string
	{
		$out = "| id | behaviour | source | calibrated by |\n|---|---|---|---|\n";
		foreach (self::ALL as $id => $b) {
			$out .= "| $id | " . str_replace('|', '/', $b['behaviour']) . ' | ' . str_replace('|', '/', $b['source']) . ' | ' . str_replace('|', '/', $b['calibrated_by']) . " |\n";
		}
		return $out;
	}
}
