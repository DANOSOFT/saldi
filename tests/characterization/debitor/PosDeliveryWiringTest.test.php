<?php
// 20261120 CL/LH K33/K34 Pin where the POS delivery choice and wished date are wired in.
//                The behaviour itself runs against a real tenant in tests/shopify/PosDeliveryChoicesTest.php, which skips where no Shopify test database is configured; this keeps the call sites from silently going missing.

namespace Saldi\Tests\PosDeliveryWiring;

use PHPUnit\Framework\TestCase;

final class PosDeliveryWiringTest extends TestCase
{
    private static function source(string $path): string
    {
        $src = file_get_contents(dirname(__DIR__, 3) . '/' . $path);
        self::assertNotFalse($src, "cannot read $path");
        return $src;
    }

    public function testThePosPageSavesTheFieldsFromTheSaleForm(): void
    {
        $page = self::source('debitor/pos_ordre.php');
        self::assertStringContainsString('include_once(__DIR__ . "/pos_ordre_includes/saleDeliveryFields.php");', $page);
        self::assertMatchesRegularExpression('/pos_save_sale_delivery\(\$id, \$posted_valg, \$posted_dato\)/', $page);
        // Saved before any payment is handled, and a refused delivery stops the payment.
        $save = strpos($page, 'pos_save_sale_delivery($id, $posted_valg, $posted_dato)');
        self::assertLessThan(strpos($page, "if (strpos(\$betaling, 'på beløb') && strlen(\$varenr_ny) > 1)"), $save);
        self::assertMatchesRegularExpression('/alert\(pos_sale_brand_for_alert\(\$leveringsfejl\)\);\s*\$betaling = NULL;\s*\$afslut = NULL;/', $page);
        // A deactivated last choice must not strand a sale that already has it.
        self::assertStringContainsString("is_scalar(\$_POST['leveringsvalg_id']) && pos_delivery_supported()", $page);
    }

    public function testEveryPathThatStartsASaleAgainClearsTheFields(): void
    {
        $page = self::source('debitor/pos_ordre.php');
        // The 48 hour reuse of an abandoned sale, Forfra and a table emptied by a move all leave a row
        // that the next customer's sale is built on.
        self::assertSame(3, substr_count($page, 'pos_reset_sale_delivery_sql()'),
            'the reused abandoned sale, Forfra and an emptied table all clear the delivery choice and wished date');
        self::assertStringContainsString("sprog='\$genbrug_brand'\" . pos_reset_sale_delivery_sql()", $page);
        self::assertStringContainsString("ref=''\" . pos_reset_sale_delivery_sql() . \" where id = '\$id'", $page);
        self::assertStringContainsString("\$tom_reset = ((int) \$r['id'] !== (int) \$id) ? pos_reset_sale_delivery_sql() : '';", $page);
    }

    public function testTheSaleHeaderShowsTheFields(): void
    {
        $scan = self::source('debitor/func/pos_ordre_itemscan.php');
        self::assertStringContainsString('pos_render_sale_delivery_fields((int) $id, (int) $status, $sprog_id);', $scan);
    }

    public function testPosValgShowsAndSavesTheChoices(): void
    {
        self::assertStringContainsString('pos_render_delivery_choices_settings($sprog_id);',
            self::source('systemdata/diverseIncludes/posOptions.php'));
        self::assertStringContainsString('pos_save_delivery_choices($_POST);', self::source('systemdata/diverse.php'));
    }

    public function testAChangeIsSavedAtOnceThroughTheDeliveryEndpoint(): void
    {
        // The POS product buttons leave the page by a link, which would lose a change not yet posted.
        $endpoint = self::source('debitor/pos_ordre_includes/saveDelivery.php');
        self::assertStringContainsString('$answer(pos_save_sale_delivery($orderId, $valg, $dato));', $endpoint);
        self::assertStringContainsString('$header = "nix";', $endpoint);
        self::assertStringContainsString("fetch('pos_ordre_includes/saveDelivery.php'",
            self::source('debitor/pos_ordre_includes/saleDeliveryFields.php'));
    }

    public function testTheWishedDateNeverGoesInLevdate(): void
    {
        // settlePOS.php sets levdate when the sale is posted, which would overwrite a wished date there.
        $fields = self::source('debitor/pos_ordre_includes/saleDeliveryFields.php');
        self::assertDoesNotMatchRegularExpression('/levdate\s*=/', $fields);
    }
}
