<?php
// tests/shopify/AdminValidationTest.php
//
// Validation, rights and escaping of the Shopify admin pages (systemdata/shopify/adminValidation.php,
// adminCommon.php), SD-761. Pure functions; no database.
//
// History:
// 20261102 CL/MJ SD-761: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../systemdata/shopify/adminCommon.php';
require_once __DIR__ . '/../../systemdata/shopify/adminValidation.php';

final class AdminValidationTest extends TestCase
{
    /** @return list<array<string, string|null>> */
    private function shops(): array
    {
        return [
            ['id' => '1', 'shop_key' => 'hml', 'domain' => 'hml-test.myshopify.com', 'afd' => '1', 'is_primary' => 't'],
            ['id' => '2', 'shop_key' => 'brand2', 'domain' => 'brand2.myshopify.com', 'afd' => null, 'is_primary' => 'f'],
        ];
    }

    private function shop(array $override = []): array
    {
        return $override + ['shop_key' => 'brand3', 'domain' => 'brand3.myshopify.com', 'afd' => '3', 'is_primary' => false, 'legacy_slot' => '', 'web_kasse' => ''];
    }

    public function testANewValidShopHasNoErrors(): void
    {
        $this->assertSame([], shopify_admin_validate_shop($this->shop(), $this->shops(), true));
    }

    public function testDuplicateDepartmentIsRefused(): void
    {
        $errors = shopify_admin_validate_shop($this->shop(['afd' => '1']), $this->shops(), true);
        $this->assertSame(['afd'], array_keys($errors));
        $this->assertStringStartsWith('shopify.admin.error.afd_taken|', $errors['afd']);
    }

    public function testAShopKeepsItsOwnDepartmentAndDomainOnEdit(): void
    {
        $edit = $this->shop(['shop_key' => 'hml', 'domain' => 'HML-test.myshopify.com', 'afd' => '1', 'is_primary' => true]);
        $this->assertSame([], shopify_admin_validate_shop($edit, $this->shops(), false));
    }

    public function testDuplicateKeyDomainAndSecondPrimaryAreRefused(): void
    {
        $errors = shopify_admin_validate_shop($this->shop(['shop_key' => 'brand2', 'domain' => 'Hml-Test.myshopify.com', 'is_primary' => true]), $this->shops(), true);
        $this->assertStringStartsWith('shopify.admin.error.shop_key_taken|', $errors['shop_key']);
        $this->assertStringStartsWith('shopify.admin.error.domain_taken|', $errors['domain']);
        $this->assertStringStartsWith('shopify.admin.error.primary_taken|', $errors['is_primary']);
    }

    public function testMalformedValuesAreRefused(): void
    {
        $errors = shopify_admin_validate_shop($this->shop(['shop_key' => 'Big Key', 'domain' => 'shop.example.com', 'afd' => '-1', 'legacy_slot' => '7']), $this->shops(), true);
        $this->assertSame(['shop_key', 'domain', 'afd', 'legacy_slot'], array_keys($errors));
    }

    public function testDuplicateSellablePriorityIsRefused(): void
    {
        $errors = shopify_admin_validate_lagre([
            1 => ['rolle' => 'saelgbar', 'prioritet' => '1', 'udstilling_af' => ''],
            2 => ['rolle' => 'saelgbar', 'prioritet' => '1', 'udstilling_af' => ''],
            3 => ['rolle' => 'ingen', 'prioritet' => '1', 'udstilling_af' => ''],
        ]);
        $this->assertSame(['2.prioritet'], array_keys($errors));
        $this->assertStringStartsWith('shopify.admin.error.prioritet_taken|', $errors['2.prioritet']);
    }

    public function testSellableNeedsAPriorityAndDisplayNeedsAnotherKnownLager(): void
    {
        $errors = shopify_admin_validate_lagre([
            1 => ['rolle' => 'saelgbar', 'prioritet' => '', 'udstilling_af' => ''],
            2 => ['rolle' => 'udstilling', 'prioritet' => '', 'udstilling_af' => '2'],
            3 => ['rolle' => 'udstilling', 'prioritet' => '', 'udstilling_af' => '9'],
            4 => ['rolle' => 'sold', 'prioritet' => '', 'udstilling_af' => ''],
        ]);
        $this->assertSame(['1.prioritet', '2.udstilling_af', '3.udstilling_af', '4.rolle'], array_keys($errors));
    }

    public function testSwappedPrioritiesAndADisplayLagerAreValid(): void
    {
        $this->assertSame([], shopify_admin_validate_lagre([
            1 => ['rolle' => 'saelgbar', 'prioritet' => '2', 'udstilling_af' => ''],
            2 => ['rolle' => 'saelgbar', 'prioritet' => '1', 'udstilling_af' => ''],
            3 => ['rolle' => 'udstilling', 'prioritet' => '', 'udstilling_af' => '1'],
        ]));
    }

    public function testRightsAtOffsetOne(): void
    {
        $this->assertSame('write', shopify_admin_access('11000'));
        $this->assertSame('read', shopify_admin_access('12000'));
        $this->assertSame('none', shopify_admin_access('10000'));
        $this->assertSame('none', shopify_admin_access(''));
    }

    public function testDisplayedValuesAreEscaped(): void
    {
        $this->assertSame('&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt; &amp; &quot;', shopify_admin_h("<script>alert('x')</script> & \""));
        $this->assertSame('', shopify_admin_h(null));
        $this->assertSame('Havemøbelland', shopify_admin_h('Havemøbelland'), 'UTF-8 text is kept, not entity-encoded');
    }
}
