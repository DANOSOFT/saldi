<?php
// 20261120 CL/LH K33/K34 Pin where the POS delivery choice and wished date are wired in.
//                The behaviour itself runs against a real tenant in tests/shopify/PosDeliveryChoicesTest.php, which skips where no Shopify test database is configured; this keeps the call sites from silently going missing.
// 20261124 CL/LH K33 The PoS-valg page is retired (#1191): the choices are kept on systemdata/posDeliveryChoices.php, linked from the till settings.
// 20261124 CL/LH K33 Codex review: the endpoint checks pos.kasse and the token, a refusal clears the tender, a table move takes the delivery along, the settings page always redirects.
// 20261126 CDX/NTR Keep endpoint guards before the shared response handler.
// 20261126 CL/MJ SD-844 K61 A table emptied by a move also clears the email and phone.

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
        // The tender suffixes (c, g, k) rebuild the payment from $modtaget, so a refusal clears it too.
        self::assertMatchesRegularExpression('/\$afslut = NULL;\s*\$modtaget = \$modtaget2 = \'\';/', $page);
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
        self::assertStringContainsString("\$tom_reset = ((int) \$r['id'] !== (int) \$id) ? pos_reset_sale_delivery_sql() . \",email='',phone=''\" : '';", $page);
        // The moved sale's delivery goes along to the table's sale before the emptied one is reset.
        $move = strpos($page, "pos_move_sale_delivery((int) \$id, (int) \$r['id']);");
        self::assertNotFalse($move);
        self::assertLessThan(strpos($page, '$tom_reset = '), $move);
    }

    public function testTheSaleHeaderShowsTheFields(): void
    {
        $scan = self::source('debitor/func/pos_ordre_itemscan.php');
        self::assertStringContainsString('pos_render_sale_delivery_fields((int) $id, (int) $status, $sprog_id);', $scan);
    }

    public function testTheTillSettingsLinkToTheDeliveryChoicesPage(): void
    {
        // #1191 retired the PoS-valg page, so the choices are kept on their own page, linked from
        // Indstillinger -> Kasse -> Skærm & kassemenuer -> Ekspedition, and shown only where the Shopify schema is installed.
        $definitions = self::source('systemdata/settingsDefinitions.php');
        self::assertMatchesRegularExpression(
            "/'pos\\.screen\\.delivery_choices' => array\\('sub' => 'sale', 'type' => 'link',[^\\n]*'href' => 'posDeliveryChoices\\.php'/", $definitions);
        self::assertMatchesRegularExpression("/'pos\\.screen\\.delivery_choices' => array\\(.*?'visible_if' => array\\('module', 'pos_delivery'\\)/s", $definitions);
        self::assertMatchesRegularExpression("/\\\$module === 'pos_delivery'.*?posSaleSettings\\.php.*?pos_delivery_supported\\(\\)/s",
            self::source('includes/settings/SettingsService.php'));
        // The retired page no longer carries them.
        self::assertStringNotContainsString('pos_render_delivery_choices_settings(', self::source('systemdata/diverseIncludes/posOptions.php'));
        self::assertStringNotContainsString('pos_save_delivery_choices(', self::source('systemdata/diverse.php'));
    }

    public function testTheDeliveryChoicesPageChecksTheTokenAndWriteRightBeforeItSaves(): void
    {
        $page = self::source('systemdata/posDeliveryChoices.php');
        $csrf = strpos($page, "hash_equals((string) \$csrfToken, (string) \$_POST['csrf_token'])");
        $write = strpos($page, "require_permission('settings.pos', 'write');");
        $save = strpos($page, 'pos_save_delivery_choices($_POST);');
        self::assertNotFalse($csrf);
        self::assertNotFalse($write);
        self::assertNotFalse($save);
        self::assertLessThan($save, $csrf);
        self::assertLessThan($save, $write);
        self::assertStringContainsString("require_permission('settings.pos', 'read');", $page);
        self::assertStringContainsString("pos_render_delivery_choices_settings(\$sprog_id, st_charset() === 'UTF-8');", $page);
        // Every POST that can commit a row redirects (PRG); a refusal travels in the session to the next page.
        self::assertStringContainsString("\$_SESSION['pos_delivery_refusals'] = (string) ob_get_clean();", $page);
        self::assertSame(1, substr_count($page, "header('Location: posDeliveryChoices.php?msg=saved');"));
        self::assertStringNotContainsString("if (\$refusals === '') {", $page);
        // A save with a refused row never lands on the success banner, also not on a reload.
        self::assertStringContainsString("header('Location: posDeliveryChoices.php?msg=refused');", $page);
    }

    public function testAChangeIsSavedAtOnceThroughTheDeliveryEndpoint(): void
    {
        // The POS product buttons leave the page by a link, which would lose a change not yet posted.
        $endpoint = self::source('debitor/pos_ordre_includes/saveDelivery.php');
        self::assertStringContainsString('$answer = pos_sale_delivery_response($_POST);', $endpoint);
        self::assertStringContainsString('$header = "nix";', $endpoint);
        // $webservice skips online.php's permission gate, so the endpoint checks the till right and the token itself.
        self::assertStringContainsString('$modulnr = 5;', $endpoint);
        self::assertLessThan(strpos($endpoint, '$answer = pos_sale_delivery_response('), strpos($endpoint, "require_permission('pos.kasse', 'write');"));
        self::assertLessThan(strpos($endpoint, '$answer = pos_sale_delivery_response('), strpos($endpoint, 'perm_require_post_csrf(true);'));
        self::assertStringContainsString("'X-CSRF-Token'", self::source('debitor/pos_ordre_includes/saleDeliveryFields.php'));
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
