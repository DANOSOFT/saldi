<?php
// tests/characterization/includes/ordrefunc/VariantLinePriceTest.test.php
//
// SST-959: a variant added to a debtor order by its barcode, with no price typed, gets the variant's
// own sales price (variant_varer.variant_salgspris), as the quick search shows it, instead of the
// item's. A variant price of 0 or none means the item's price: the webshop sync creates variants with
// 0 when the shop sends no price. A typed price and a campaign price still come first.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261110 CL/MJ SST-959 Created.
// 20261112 CL/MJ SST-959 An item with a quantity discount keeps the item's price on a variant line (CodeRabbit).

require_once __DIR__ . '/../../support/CharacterizationTestCase.php';

final class VariantLinePriceTest extends CharacterizationTestCase
{
    /** An item priced 885 with one variant, its barcode and its own price (null for none). */
    private function itemWithVariant(?float $variantPrice): array
    {
        $item = self::$fx->item(['salgspris' => 885.00, 'kostpris' => 400.00]);
        $barcode = 'SST959V' . $item['id'];
        self::rows(
            'INSERT INTO variant_varer (vare_id, variant_type, variant_beholdning, variant_stregkode, lager, variant_salgspris, variant_kostpris) VALUES ($1, 0, 0, $2, 0, $3, 0)',
            [$item['id'], $barcode, $variantPrice]
        );
        return $item + ['barcode' => $barcode];
    }

    /** Adds the line as the order page does when $typed is entered with $pris (null: Pris left empty). */
    private function addLine(array $item, string $typed, ?float $pris): array
    {
        $order = self::$fx->order(self::$fx->debtor(), []);
        $out = $this->runChildJson('run_order_lifecycle.php', ['addline', $order['id'], $item['id'], 1, $pris ?? '', 0, $typed, CharacterizationEnv::testDb()]);
        $line = self::one('SELECT pris, variant_id FROM ordrelinjer WHERE ordre_id = $1 AND vare_id = $2', [$order['id'], $item['id']]);
        $this->assertNotNull($line, 'no line was written; opret_ordrelinje() said: ' . (string)($out['opret_ordrelinje'] ?? ''));
        return $line;
    }

    public function test_a_variant_with_its_own_price_gets_that_price(): void
    {
        $item = $this->itemWithVariant(1234.50);

        $line = $this->addLine($item, $item['barcode'], null);

        $this->assertEqualsWithDelta(1234.50, (float)$line['pris'], 0.001, 'the variant\'s price, not the item\'s 885');
        $this->assertGreaterThan(0, (int)$line['variant_id']);
    }

    public function test_a_variant_without_a_price_gets_the_items_price(): void
    {
        $item = $this->itemWithVariant(null);

        $this->assertEqualsWithDelta(885.00, (float)$this->addLine($item, $item['barcode'], null)['pris'], 0.001);
    }

    /** What the webshop sync writes for a variant the shop sends no price for. */
    public function test_a_variant_priced_0_gets_the_items_price(): void
    {
        $item = $this->itemWithVariant(0.0);

        $this->assertEqualsWithDelta(885.00, (float)$this->addLine($item, $item['barcode'], null)['pris'], 0.001);
    }

    public function test_a_typed_price_is_still_used(): void
    {
        $item = $this->itemWithVariant(1234.50);

        $this->assertEqualsWithDelta(777.77, (float)$this->addLine($item, $item['barcode'], 777.77)['pris'], 0.001);
    }

    public function test_a_campaign_price_still_comes_before_the_variants_price(): void
    {
        $item = $this->itemWithVariant(1234.50);
        self::rows(
            "UPDATE varer SET special_price = 700, specialtype = 'price', special_from_date = current_date - 1, special_to_date = current_date + 1, special_from_time = '00:00', special_to_time = '23:59' WHERE id = $1",
            [$item['id']]
        );

        $this->assertEqualsWithDelta(700.00, (float)$this->addLine($item, $item['barcode'], null)['pris'], 0.001);
    }

    /** m_rabat() only discounts lines at the item's price, so the variant's price would cost the line its quantity discount. */
    public function test_an_item_with_a_quantity_discount_keeps_the_items_price(): void
    {
        $item = $this->itemWithVariant(1234.50);
        self::rows("UPDATE varer SET m_antal = '2', m_rabat = '50', m_type = 'amount' WHERE id = $1", [$item['id']]);

        $this->assertEqualsWithDelta(885.00, (float)$this->addLine($item, $item['barcode'], null)['pris'], 0.001);
    }
}
