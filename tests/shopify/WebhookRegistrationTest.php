<?php
// tests/shopify/WebhookRegistrationTest.php
//
// Webhook registration (includes/shopify/events.php, SD-749) against FakeWebhookShop, a store
// that keeps its own subscription list behind shopify_graphql()'s transport seam: the reconcile
// is idempotent, a changed base URL moves only our subscriptions, a deleted one comes back, a
// create that may have reached Shopify is looked up before it is sent again, and the documents
// match the pinned 2026-07 schema. Runs in its own processes, like EventsTest (config.php).
//
// History:
// 20261028 CL/SZ SD-749: created.

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class WebhookRegistrationTest extends TestCase
{
    private const SHOP = ['id' => '1', 'shop_key' => 'hml', 'domain' => 'hml-test.myshopify.com', 'api_version' => '2026-07'];
    private const BASE = 'https://dev-sz.example.dk/sz';
    private const URI = 'https://dev-sz.example.dk/sz/api/shopify/webhook.php?shop=hml';

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../includes/shopify/events.php';
        require_once __DIR__ . '/support/FakeWebhookShop.php';
        require_once __DIR__ . '/support/GraphqlSchema.php';
    }

    public function testTheSameSetTwiceCreatesNothingTheSecondTime(): void
    {
        $shop = new FakeWebhookShop();

        $first = shopify_register_webhooks(self::SHOP, self::BASE . '/', $shop->opts());
        $sent = count($shop->calls);
        $second = shopify_register_webhooks(self::SHOP, self::BASE, $shop->opts());

        $this->assertSame([10, 0, 0, 0], [$first['created'], $first['updated'], $first['unchanged'], $first['failed']]);
        $this->assertSame([0, 0, 10, 0], [$second['created'], $second['updated'], $second['unchanged'], $second['failed']]);
        $this->assertSame(['list'], array_slice($shop->ops(), $sent), 'the second run only reads');
        $expected = SHOPIFY_WEBHOOK_TOPICS;
        sort($expected);
        $this->assertSame($expected, array_keys($shop->byTopic()));
        $this->assertNotContains('INVENTORY_LEVELS_UPDATE', array_keys($shop->byTopic()), 'not subscribed in P1');
        foreach ($shop->byTopic() as $uris) {
            $this->assertSame([self::URI], $uris);
        }
        $this->assertSame(['uri' => self::URI, 'format' => 'JSON'], $shop->calls[1]['vars']['webhookSubscription']);
    }

    public function testABaseUrlChangeMovesOnlyOurSubscriptions(): void
    {
        $shop = new FakeWebhookShop();
        foreach (SHOPIFY_WEBHOOK_TOPICS as $topic) {
            $shop->add($topic, 'https://old-tunnel.trycloudflare.com/api/shopify/webhook.php?shop=hml');
        }
        $shop->add('ORDERS_PAID', 'https://erp.example.com/hooks/orders');
        $shop->add('ORDERS_PAID', 'https://old-tunnel.trycloudflare.com/api/shopify/webhook.php?shop=brand');

        $result = shopify_register_webhooks(self::SHOP, self::BASE, $shop->opts());

        $this->assertSame([0, 10, 0, 0], [$result['created'], $result['updated'], $result['unchanged'], $result['failed']]);
        $this->assertNotContains('create', $shop->ops());
        $this->assertSame([self::URI, 'https://erp.example.com/hooks/orders', 'https://old-tunnel.trycloudflare.com/api/shopify/webhook.php?shop=brand'],
            $shop->byTopic()['ORDERS_PAID'], 'another endpoint and another shop key are not ours');
        $this->assertSame([self::URI], $shop->byTopic()['REFUNDS_CREATE']);
    }

    public function testSubscriptionsToAnotherEndpointOrShopAreNeverTouched(): void
    {
        $shop = new FakeWebhookShop();
        $shop->add('ORDERS_PAID', 'https://erp.example.com/hooks/orders');
        $shop->add('ORDERS_PAID', 'https://old-tunnel.trycloudflare.com/api/shopify/webhook.php?shop=brand');
        $shop->add('ORDERS_PAID', 'https://old-tunnel.trycloudflare.com/api/shopify/other.php?shop=hml');

        $result = shopify_register_webhooks(self::SHOP, self::BASE, $shop->opts());

        $this->assertSame([10, 0], [$result['created'], $result['updated']]);
        $this->assertSame(['https://erp.example.com/hooks/orders', 'https://old-tunnel.trycloudflare.com/api/shopify/webhook.php?shop=brand',
            'https://old-tunnel.trycloudflare.com/api/shopify/other.php?shop=hml', self::URI], $shop->byTopic()['ORDERS_PAID']);
    }

    public function testASubscriptionShopifyDeletedIsCreatedAgain(): void
    {
        $shop = new FakeWebhookShop();
        shopify_register_webhooks(self::SHOP, self::BASE, $shop->opts());
        $shop->subs = array_values(array_filter($shop->subs, fn(array $s): bool => $s['topic'] !== 'ORDERS_PAID'));

        $result = shopify_register_webhooks(self::SHOP, self::BASE, $shop->opts());

        $this->assertSame([1, 0, 9, 0], [$result['created'], $result['updated'], $result['unchanged'], $result['failed']]);
        $this->assertSame('created', $result['topics']['ORDERS_PAID']);
        $this->assertSame([self::URI], $shop->byTopic()['ORDERS_PAID']);
    }

    public function testRestoreOnlyCreatesMissingTopicsAndLeavesOursElsewhere(): void
    {
        $shop = new FakeWebhookShop();
        $shop->add('ORDERS_PAID', 'https://other-install.example.dk/sz/api/shopify/webhook.php?shop=hml');

        $result = shopify_register_webhooks(self::SHOP, self::BASE, $shop->opts(['restore_only' => true]));

        $this->assertSame([9, 0, 1], [$result['created'], $result['updated'], $result['unchanged']]);
        $this->assertSame('elsewhere', $result['topics']['ORDERS_PAID']);
        $this->assertNotContains('update', $shop->ops());
        $this->assertSame(['https://other-install.example.dk/sz/api/shopify/webhook.php?shop=hml'], $shop->byTopic()['ORDERS_PAID']);
    }

    public function testACreateThatMayHaveReachedShopifyIsLookedUpNotSentTwice(): void
    {
        $shop = new FakeWebhookShop();
        $shop->fail['ORDERS_PAID'] = 'timeout';

        $result = shopify_register_webhooks(self::SHOP, self::BASE, $shop->opts());

        $this->assertSame('created', $result['topics']['ORDERS_PAID']);
        $this->assertSame(10, $result['created']);
        $creates = array_filter($shop->calls, fn(array $c): bool => $c['op'] === 'create' && $c['vars']['topic'] === 'ORDERS_PAID');
        $this->assertCount(1, $creates, 'the reconcile read found it; no second create');
        $this->assertSame([self::URI], $shop->byTopic()['ORDERS_PAID']);
    }

    public function testAFailedTopicIsReportedAndTheOthersAreStillRegistered(): void
    {
        $shop = new FakeWebhookShop();
        $shop->fail['REFUNDS_CREATE'] = 'user_error';

        $result = shopify_register_webhooks(self::SHOP, self::BASE, $shop->opts());

        $this->assertSame([9, 1], [$result['created'], $result['failed']]);
        $this->assertSame('failed', $result['topics']['REFUNDS_CREATE']);
        $this->assertSame(['REFUNDS_CREATE: Address for this topic has already been taken'], $result['errors']);
    }

    public function testEveryPageOfSubscriptionsIsRead(): void
    {
        $shop = new FakeWebhookShop();
        $shop->pageSize = 3;
        shopify_register_webhooks(self::SHOP, self::BASE, $shop->opts());

        $again = shopify_register_webhooks(self::SHOP, self::BASE, $shop->opts());

        $this->assertSame(10, $again['unchanged']);
        $this->assertCount(3, array_filter($shop->calls, fn(array $c): bool => $c['op'] === 'list' && ($c['vars']['after'] ?? null) !== null), 'pages 2-4 of the second run (10 subscriptions, 3 per page)');
    }

    public function testUnreadableSubscriptionsChangeNothing(): void
    {
        $fake = new FakeShopify([FakeShopify::json(['errors' => [['message' => 'Access denied', 'extensions' => ['code' => 'ACCESS_DENIED']]]], 200)]);

        try {
            shopify_register_webhooks(self::SHOP, self::BASE, $fake->opts());
            $this->fail('expected a RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('could not be read', $e->getMessage());
        }
        $this->assertCount(1, $fake->calls, 'no mutation after a failed read');
    }

    public function testTheBaseUrlMustBeHttpsWithoutQuery(): void
    {
        $this->assertSame(self::BASE, shopify_webhook_base_url_check(self::BASE . '/'));
        $this->assertNull(shopify_webhook_base_url_check('http://dev-sz.example.dk/sz'));
        $this->assertNull(shopify_webhook_base_url_check('https://dev-sz.example.dk/sz?x=1'));
        $this->assertNull(shopify_webhook_base_url_check('https://user:pw@dev-sz.example.dk'));
        $this->assertNull(shopify_webhook_base_url_check('dev-sz.example.dk'));
        $this->expectException(InvalidArgumentException::class);
        shopify_register_webhooks(self::SHOP, 'http://dev-sz.example.dk', (new FakeWebhookShop())->opts());
    }

    public function testTheDocumentsAndInputsMatchThe202607Schema(): void
    {
        $schema = GraphqlSchema::load();
        foreach ([SHOPIFY_WEBHOOK_LIST_QUERY, SHOPIFY_WEBHOOK_CREATE_MUTATION, SHOPIFY_WEBHOOK_UPDATE_MUTATION] as $document) {
            $this->assertSame([], $schema->validateDocument($document));
        }
        $this->assertSame([], $schema->validateInput('WebhookSubscriptionInput!', ['uri' => self::URI, 'format' => 'JSON']));
        foreach (SHOPIFY_WEBHOOK_TOPICS as $topic) {
            $this->assertSame([], $schema->validateInput('WebhookSubscriptionTopic!', $topic), $topic);
        }
    }
}
