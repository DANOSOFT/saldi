# Shopify integration: interface sheet

Signatures that cross from one work package to another. Don't change one after it merges without approval, and add new cross-lane signatures here.

## Move barriers (WP06 → WP09, WP10)

`includes/shopify/services/stock.php`. A stock push skips an item while its web order's fulfilment moves are pending.

```php
shopify_barrier_open(int $shopId, int $ordreId, array $vareIds): int
shopify_barrier_release(int $shopId, int $ordreId): int
shopify_barrier_expire(int $shopId, ?int $ordreId = null): array // list<array{shop_id: int, ordre_id: int, vare_ids: list<int>}>
shopify_barrier_deferred(int $shopId, array $vareIds): array     // list<int>
```

`$ordreId` is `ordrer.id`.

| Function | Called by | Contract |
|---|---|---|
| `shopify_barrier_open` | WP09, in the order's creation transaction | Runs in the caller's transaction: never begins or commits one and never calls Shopify, so the rows commit or roll back with the order. Idempotent per (shop, order, item): calling it again after a crash creates no second barrier and doesn't throw. `$vareIds` holds the line items, the set components and the set itself. Returns how many were opened. |
| `shopify_barrier_release` | WP10, after the moves | The order's open barriers become `released`. WP09 never releases. Returns how many. |
| `shopify_barrier_expire` with an order id | WP10, after the 3rd failed move | The order's open barriers become `abandoned`. WP10 counts failed moves on its job. |
| `shopify_barrier_expire` without an order id | The sweep, which L10b schedules, per shop | The shop's open barriers past `expires_at` (30 minutes) become `abandoned`. |
| `shopify_barrier_deferred` | The stock push (WP06) | Which of the given items to skip, by exact item: the push asks about a set together with its components. A barrier past `expires_at` no longer defers, also before the sweep has run. |

`shopify_barrier_expire` returns what it just abandoned, one entry per order, and each barrier only once. L10b raises one alarm per order from it, naming the shop, order and items, and keeps the allocation on the move list; it doesn't read the rows back, so a resolved alarm stays resolved. Release and expire mark the items and every set above them in `shopify_stock_dirty` when a shop has `stock_capture_enabled`, so the next push publishes them.

## Split-set lager (WP06, WP07 → WP09)

`includes/shopify/services/allocation.php`, and the checked version in `services/stock.php`. Availability and allocation resolve the lager split sets go on the same way.

```php
shopify_split_set_lager(?int $setting, array $locations): int
shopify_split_set_lager_checked(?int $setting, array $locations): int
```

Returns the setting when it's a sellable lager, else the first sellable lager by priority, and throws when there is none. WP09 (S09b) calls it with the rows from `shopify_availability_lagre()`, so the virtual lager is left out, and passes the result to `shopify_allocate_order()`, which keeps its strict int parameter and keeps refusing 0.

`shopify_split_set_lager_checked()` does the same and needs a database: while `split_set_lager` is set and another lager is used, a warning naming both stays open on the error list (`kind` `config`, `ref` `split_set_lager`), and it's resolved once they match or the setting is unset. The stock push of sets (WP06-3) calls it once per push and passes the setting on to `shopify_availability()`, which stays pure; WP06-2's push sends plain items and passes null. WP09 calls it per order.

## Stock push (WP06-2 → WP06-3, WP13)

`includes/shopify/services/stock.php`. The worker's `stock` producer drains `shopify_stock_dirty`; the `stock.push` job sends absolute available quantities to one shop.

```php
shopify_stock_drain_dirty(array $rt, int $limit = 500): array // array{drained: int, queued: int}
shopify_stock_push(array $rt, array $job, float $deadline): array // array{outcome: 'done'|'yield', sent?: int, gate?: string}
```

| Queue state key | Holds | Rule for every writer |
|---|---|---|
| `stock.dirty:<shop_key>` | `vare_id => mark`, the transaction id of the last drain that saw the item | The drain sets marks for every shop with `stock_capture_enabled`. A push removes an item only while its mark is the one it read, so an item marked again during a call is sent again. A push whose remaining items all wait for a move barrier yields for 60 seconds. |
| `stock.pending:<shop_key>` | The shop's one unresolved `inventorySetQuantities` call: `vars` (the `key` and the `input` as sent), `rows`, `marks`, `read_at` | Written only when empty, before the call. Resent byte for byte with the same key until Shopify answers. Cleared with the `shopify_stock_state` update after an answer, or without one after a refusal, whose key is then not used again. |

A stored call with an item behind an open barrier isn't sent: it may be its first send, with a quantity read before the barrier opened. It waits with its key and input, and as a shop has one pending call, the shop's other items wait with it until the barrier ends, 30 minutes at most. It takes a call with no answer and a barrier on one of its items.

The drain also queues a push for a shop whose state holds a call or marks and has no active job, so a job that went dead doesn't strand them. `shopify_upsert_product_link()` marks an item when a listed link gets its inventory item. `shopify_save_shop()` refuses `stock_publish_enabled` without `stock_capture_enabled`.

WP06-3's `stock.baseline` and `stock.reconcile` jobs send through the same `stock.pending:<shop_key>` slot, so a shop never has two unresolved stock calls. `shopify_stock_state.acknowledged_at` is the time the quantities were read, and a row counts only while it is not older than the item's product link.
