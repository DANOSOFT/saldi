<?php
// tests/shopify/LocationsFetchTest.php
//
// The read behind "Hent lokationer" (includes/shopify/services/locations.php, SD-865): the shop's
// locations, page by page, checked against the 2026-07 Admin schema by the fake store. No database.
//
// History:
// 20261123 CL/MJ SD-865: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/FakeShopifyStore.php';
require_once dirname(__DIR__, 2) . '/includes/shopify/client.php';
require_once dirname(__DIR__, 2) . '/includes/shopify/services/locations.php';

final class LocationsFetchTest extends TestCase
{
    public function testTheShopsLocationsAreReadWithTheirNames(): void
    {
        $store = new FakeShopifyStore();
        $shop = $store->addShop('hml', ['locations' => ['Lager Mårum', 'Showroom Nord']]);
        $added = $store->addLocation($shop['domain'], 'Lager Syd');

        $locations = shopify_locations_read($shop, $store->opts());

        $this->assertSame(['Lager Mårum', 'Showroom Nord', 'Lager Syd'], array_column($locations, 'name'));
        $this->assertSame($store->locations($shop['domain']), array_column($locations, 'gid'));
        $this->assertSame($added, $locations[2]['gid'], 'the GID is kept as Shopify sends it');
    }

    public function testEveryPageIsRead(): void
    {
        $page = fn(array $names, ?string $next): array => FakeShopify::ok(['locations' => [
            'nodes' => array_map(fn(string $n): array => ['id' => "gid://shopify/Location/$n", 'name' => "Lager $n"], $names),
            'pageInfo' => ['hasNextPage' => $next !== null, 'endCursor' => $next],
        ]]);
        $fake = new FakeShopify([$page(['1', '2'], 'c1'), $page(['3'], null)]);

        $locations = shopify_locations_read(FakeShopify::shop(), $fake->opts());

        $this->assertSame(['Lager 1', 'Lager 2', 'Lager 3'], array_column($locations, 'name'));
        $this->assertSame([null, 'c1'], array_map(fn(array $call) => $call['body']['variables']['after'], $fake->calls));
    }

    public function testAFailedReadThrowsSoTheJobIsTriedAgain(): void
    {
        $fake = new FakeShopify([FakeShopify::json(['errors' => 'Invalid API key or access token'], 401)]);

        $this->expectException(RuntimeException::class);
        shopify_locations_read(FakeShopify::shop(), $fake->opts());
    }
}
