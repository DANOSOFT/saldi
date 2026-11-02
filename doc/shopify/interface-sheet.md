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
