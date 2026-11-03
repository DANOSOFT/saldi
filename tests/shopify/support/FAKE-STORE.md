# The shared fake Shopify store: a quickstart

`FakeShopifyStore.php` is one stateful fake of the Shopify Admin GraphQL API 2026-07 for every
HML lane (SD-766). It sits behind `shopify_graphql()`'s transport seam: pass `$store->opts()` as
the client's opts. Every document and its variables are checked against the pinned schema
(`fixtures/admin-2026-07-schema.json.gz`), and the answer is projected onto the document's
selection. Anything the fake does not model fails loudly (`LogicException`), it never answers
with a plausible guess.

Real Shopify stays the truth. Every behaviour the fake encodes is an entry in
`FakeShopifyBehaviours.php` with its basis (`verified`, `partly`, `assumed`), its source and the
real-store check that calibrates it.

## A lane adds a root field

1. Write the handler in `FakeShopifyStore.php` and list the root field in the constructor's
   `query` or `mutation` list. The store calls `q<Field>()` or `m<Field>()` with the shop domain
   and the resolved arguments. A mutation returns its payload with `self::payload()` and
   `self::userError()`.

   ```php
   // constructor: add 'fulfillmentOrderHold' to the mutation list
   private function mFulfillmentOrderHold(string $domain, array $a): array
   {
       $s = &$this->shop($domain);
       if (!isset($s['fulfillment_orders'][$a['id']])) {
           $this->uses('B29');
           return self::payload(['fulfillmentOrder' => null], [self::userError(['id'], 'Fulfillment order does not exist.')]);
       }
       $this->uses('B37');
       $s['fulfillment_orders'][$a['id']]['status'] = 'ON_HOLD';
       return self::payload(['fulfillmentOrder' => $this->vFulfillmentOrder($domain, $a['id'])]);
   }
   ```

   A test that needs a field only for itself can use `$store->handle('mutation', 'fulfillmentOrderHold', fn(FakeShopifyStore $store, string $domain, array $args): array => ...)`
   instead. A shared behaviour belongs in the store.

2. Call `$this->uses('Bxx')` where the handler applies a Shopify behaviour, and add the entry to
   `FakeShopifyBehaviours::ALL`:

   ```php
   'B37' => [
       'behaviour' => 'fulfillmentOrderHold puts an open fulfillment order ON_HOLD ...',
       'basis' => 'partly',
       'source' => self::DOCS . 'mutations/fulfillmentOrderHold (the hold); the message ASSUMED',
       'calibrated_by' => 'SD-764 (to add): hold a fulfillment order on the dev store',
   ],
   ```

   `verified` means every claim is on the cited 2026-07 page. Anything you could not read there
   is `ASSUMED` in the source (`partly`), or the whole entry is `assumed`. The registry test fails
   when a basis does not match its source or an admin-graphql link is not 2026-07.

3. If 2026-07 documents `@idempotent` on the field, add it to `FakeShopifyStore::IDEMPOTENT` and
   cite the page. The directive anywhere else fails loudly.

4. Add a contract test to `tests/shopify/FakeShopifyStoreTest.php`. It sends the lane's real
   document (the constant from `includes/shopify/...`) through `shopify_graphql()` with
   `$this->store->opts()` and asserts what the entry says, including a refusal and that a
   refused call changes nothing.

The registry test also fails when a `uses()` id has no entry or an entry is never used.

## Refreshing the NF-1 replay fixtures

`fixtures/day.json` (the deliveries), `fixtures/graphql.json` (the store's state) and
`fixtures/expected.json` (what one replay stores) belong together. When the fake's semantics or
a lane's handler change, refresh all three in the same change. Run these from the repository
root with the suite's `SALDI_SHOPIFY_PGHOST`, `_PGUSER`, `_PGPASS`, `_MASTER_DB` and
`_TEMPLATE_DB` (see `support/ShopifyEnv.php`; CI's are in `.github/workflows/tests.yml`):

```sh
# 1. the day and the store's state, from the seed (no database)
php tests/shopify/support/build_replay_day.php

# 2. a throwaway clone of the template, registered and with the Shopify schema
php -r 'require "tests/shopify/support/ShopifyEnv.php"; $db = "saldi_shopify_test_rec";
  ShopifyEnv::cloneTenant($db); ShopifyEnv::registerTenant($db); exit(ShopifyEnv::runSchema($db, "ensure")["exit"]);'

# 3. replay twice and write expected.json from the first replay
php tests/shopify/support/run_replay.php saldi_shopify_test_rec record

# 4. drop the clone
php -r 'require "tests/shopify/support/ShopifyEnv.php"; $db = "saldi_shopify_test_rec"; $m = ShopifyEnv::connect(ShopifyEnv::masterDb());
  ShopifyEnv::rows($m, "DELETE FROM regnskab WHERE db = \$1", [$db]);
  ShopifyEnv::rows($m, "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = \$1", [$db]);
  ShopifyEnv::rows($m, "DROP DATABASE IF EXISTS " . pg_escape_identifier($m, $db));'

# 5. the test against the new files (it clones its own tenant from SALDI_SHOPIFY_TEST_DB)
SALDI_SHOPIFY_TEST_DB=saldi_shopify_test_ci php vendor/bin/phpunit --testsuite shopify --filter ReplayDayTest
```

Step 3 prints `changes2`, the rows the second replay changed. It must be `[]`.

Then review the result. A passing test after a refresh proves only that the files agree with each
other:

- `day.json`: the same topics and event ids unless you meant to change the day. New fields are
  fine; a changed amount or customer needs a reason.
- `graphql.json`: the state changes your change explains, nothing else.
- `expected.json`: read the business rows, not the diff size. Every SNAPSHOT part and every
  `changes` row (debtor numbers and names, addresses, orders, amounts and VAT, ledger, stock)
  must be what that day should store in Saldi. A row you cannot explain is a bug in the lane or
  the fake.
- Say in the PR what changed in the rows and why.

## Coverage today

- Root fields: `shop`, `product`, `productByIdentifier`, `products`, `productVariant(s)`,
  `inventoryItem`, `inventoryLevel`, `location(s)`, `order(s)`, `fulfillmentOrder(s)`,
  `customer(s)`, `customerByIdentifier`, `webhookSubscription(s)`, `node(s)`; mutations
  `productSet`, `inventorySetQuantities`, `inventoryActivate`, `orderCreate`, `orderUpdate`,
  `fulfillmentOrderMove`, `fulfillmentCreate`, `refundCreate`, `tagsAdd`, `tagsRemove`,
  `metafieldsSet`, `customerCreate`, `customerUpdate`, `webhookSubscriptionCreate/Update/Delete`.
- Not modelled, so they fail loudly: draft orders, bulk operations, order cancel, fulfillment
  holds and requests, returns, shipping lines, discounts, presentment currency, tax computed from a
  rate, search terms other than those in B23.
- The NF-1 replay runs one handler, `customer.upsert`. Orders, fulfilments, refunds, ledger and
  stock are empty in `expected.json` until SD-743, SD-740, SD-746, SD-739 and SD-756 add their
  handlers to `ReplayDay::registry()`. X01 reports those links as BLOCKED with their tickets.

## What is assumed

Of the 36 entries, 1 is verified, 27 are partly assumed (the rule is documented, a code, message or
detail is not), and 8 are wholly assumed: B10 (bucket sizes), B14 (event ids across topics), B17
(stock on fulfilment), B18 (order routing), B28 and B29 (error messages), B31 (stock when a
move carries a decremented order) and B36 (a metafield digest across a write of the same value).
`FakeShopifyBehaviours::byBasis()` lists them. They stay assumptions until SD-764 calibrates them
on a real dev store and the calibration probes (`sandbox/run.php --calibrate`) agree. A scenario
can pass on the fake and fail on a sandbox until then.
