<?php
// 20261126 CDX/NTR Prove catalogue creation rejects invalid CSRF and stale/missing tenant context before writes.

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/includes/std_func.php';
require_once dirname(__DIR__, 3) . '/includes/orderFuncIncludes/externalPriceList.php';
require_once __DIR__ . '/support/product_lookup_security.php';

final class ProductLookupSecurityTest extends TestCase
{
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (['_POST', '_GET', '_SESSION', '_SERVER', 'db', 'db_encode'] as $key) {
            $this->saved[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
        }
        $_GET = ['id' => 11, 'art' => 'DO', 'fokus' => 'varenr'];
        $_SESSION = ['csrf_token' => 'synthetic-csrf-token'];
        $_SERVER['REQUEST_URI'] = '/debitor/productLookup.php';
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        $GLOBALS['db'] = 'fixture_1';
        $GLOBALS['db_encode'] = 'UTF8';
        \ProductLookupSecurityFixture\boot();
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $key => [$exists, $value]) {
            if ($exists) {
                $GLOBALS[$key] = $value;
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }

    public function testMissingAndWrongCsrfCannotCreateItems(): void
    {
        foreach ([null, 'wrong-token'] as $token) {
            $_POST = ['plVarenr' => 'A100', 'thisDb' => 'fixture_1'];
            if ($token !== null) {
                $_POST['csrf_token'] = $token;
            }
            self::page();
            self::assertSame(403, \ProductLookupSecurityFixture\State::$status);
            self::assertSame(0, \ProductLookupSecurityFixture\State::$writes);
        }
    }

    public function testMatchingTokenAndTenantPermitCreation(): void
    {
        $_POST = ['plVarenr' => 'A100', 'thisDb' => 'fixture_1', 'csrf_token' => 'synthetic-csrf-token'];
        self::page();
        self::assertSame(1, \ProductLookupSecurityFixture\State::$writes);
        self::assertSame(302, \ProductLookupSecurityFixture\State::$status);
    }

    public function testMissingTenantCannotCreateItems(): void
    {
        $_POST = ['plVarenr' => 'A100', 'csrf_token' => 'synthetic-csrf-token'];
        self::page();
        self::assertSame(0, \ProductLookupSecurityFixture\State::$writes);
    }

    public function testSwitchingTenantCannotCreateItemsInTheNewTenant(): void
    {
        $_POST = ['plVarenr' => 'A100', 'thisDb' => 'fixture_2', 'csrf_token' => 'synthetic-csrf-token'];
        self::page();
        self::assertSame(0, \ProductLookupSecurityFixture\State::$writes);
    }

    public function testFormGeneratesAndCarriesCsrfAndTenantContext(): void
    {
        $_POST = [];
        $_SESSION = [];
        $html = self::page(true);
        self::assertArrayHasKey('csrf_token', $_SESSION);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $_SESSION['csrf_token']);
        self::assertStringContainsString("name='csrf_token' value='" . $_SESSION['csrf_token'] . "'", $html);
        self::assertStringContainsString("name='thisDb' value='fixture_1'", $html);
        self::assertStringContainsString("accept-charset='UTF-8'", $html);
    }

    public function testLegacyPageFormKeepsUtf8CatalogueTextLossless(): void
    {
        $_POST = [];
        $GLOBALS['db_encode'] = 'LATIN9';
        $html = self::page(true);
        self::assertTrue(mb_check_encoding($html, 'ASCII'));
        self::assertStringContainsString("name='plVarenr' value='P&#230;re'", $html);
        self::assertStringContainsString('P&#230;re &#8364;', $html);
    }

    /** @return string Controller output, optionally followed by the real price-list form view. */
    private static function page(bool $form = false): string
    {
        $db = $GLOBALS['db'];
        $_SERVER['REQUEST_METHOD'] = $form ? 'GET' : 'POST';
        $source = file_get_contents(dirname(__DIR__, 3) . '/debitor/productLookup.php');
        $controller = substr($source, 5, strpos($source, '// Clear old grid state') - 5);
        $controller = str_replace('include("../includes/online.php");', '\\ProductLookupSecurityFixture\\onlineGuard();', $controller);
        $controller = preg_replace('/^\s*(?:include|require)(?:_once)?\b[^\n]*;/m', '', $controller);
        $controller = preg_replace('/\bexit\s*(?:\(\s*\))?\s*;/', 'throw new \\ProductLookupSecurityFixture\\Finished();', $controller);
        ob_start();
        try {
            eval('namespace ProductLookupSecurityFixture;' . $controller);
            if ($form) {
                $priceListFound = [['varenr' => 'Pære', 'enhed' => 'stk', 'beskrivelse' => 'Pære €', 'salgspris' => '2.000']];
                $incl_moms = false;
                $start = strpos($source, '                foreach ($priceListFound');
                $view = substr($source, $start, strpos($source, '                print "</table>";', $start) - $start);
                eval($view);
            }
        } catch (\ProductLookupSecurityFixture\Finished | \ProductLookupSecurityFixture\Denied $finished) {
        } finally {
            $html = ob_get_clean();
        }
        return $html;
    }
}
