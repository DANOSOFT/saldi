<?php
// 20261021 CL/MJ SD-758 Pin the POS sale header: the department and brand a sale is created with,
// the customer's phone number coming along, and the receipt print no longer rewriting either.
//
// opret_posordre() cannot be called here - it writes a lock file under ../temp, calls
// hent_shop_ordrer(), prints redirects and exits - so its insert is read from the shipped source.
// Everything else runs for real against sqlite. The functions live in files that are include()d
// into a page, so they are lifted and evaluated into this namespace; eval() does not inherit the
// caller's namespace, so it is restated.

namespace Saldi\Tests\PosSaleHeader;

use PDO;
use PHPUnit\Framework\TestCase;

final class PosSaleHeaderTest extends TestCase
{
    private PDO $db;
    private static bool $lifted = false;

    private const REGNAAR = 2026;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is required for the isolated POS fixtures.');
        }
        require_once __DIR__ . '/support/pos_sale_header_database.php';

        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $GLOBALS['posSaleHeaderDb'] = $this->db;
        $GLOBALS['posSaleHeaderSql'] = [];
        $GLOBALS['posSaleHeaderAlerts'] = [];
        $GLOBALS['sprog_id'] = 1;
        $GLOBALS['regnaar'] = self::REGNAAR;
        $GLOBALS['baseCurrency'] = 'DKK';
        $GLOBALS['kasse'] = 1;

        $this->db->exec("CREATE TABLE grupper (id INTEGER PRIMARY KEY AUTOINCREMENT, beskrivelse TEXT,
            kode TEXT, kodenr TEXT, art TEXT, box1 TEXT, box2 TEXT, box3 TEXT, box4 TEXT, box5 TEXT,
            box6 TEXT, box7 TEXT, fiscal_year TEXT)");
        $this->db->exec("CREATE TABLE ordrer (id INTEGER PRIMARY KEY, konto_id INTEGER, kontonr TEXT,
            firmanavn TEXT, addr1 TEXT, addr2 TEXT, postnr TEXT, bynavn TEXT, land TEXT, kontakt TEXT,
            email TEXT, phone TEXT, cvrnr TEXT, ean TEXT, institution TEXT, betalingsbet TEXT,
            betalingsdage INTEGER, art TEXT, valuta TEXT, valutakurs NUMERIC, sprog TEXT,
            afd INTEGER, status INTEGER)");
        $this->db->exec("CREATE TABLE adresser (id INTEGER PRIMARY KEY, kontonr TEXT, art TEXT,
            firmanavn TEXT, addr1 TEXT, addr2 TEXT, postnr TEXT, bynavn TEXT, land TEXT, kontakt TEXT,
            email TEXT, tlf TEXT, cvrnr TEXT, ean TEXT, institution TEXT, betalingsbet TEXT,
            betalingsdage INTEGER, lukket TEXT)");

        // A brand has to be an existing form variant, because ordrer.sprog picks the form an
        // invoice is printed from. formularer is where every other sprog chooser reads its list.
        $this->db->exec("CREATE TABLE formularer (id INTEGER PRIMARY KEY AUTOINCREMENT,
            formular INTEGER, art TEXT, beskrivelse TEXT, sprog TEXT)");
        $this->variants('Dansk', 'havemoebelland', 'udehygge');

        // Three terminals: departments 1, 2 and 4; brands only on the first two.
        $tab = chr(9);
        $this->db->exec("INSERT INTO grupper (art,kodenr,box3,fiscal_year)
            VALUES ('POS','1','1{$tab}2{$tab}4','" . self::REGNAAR . "')");

        if (!self::$lifted) {
            eval('namespace ' . __NAMESPACE__ . '; ' . self::liftedSource());
            self::$lifted = true;
        }
    }

    /** The sale-header and sale-settings functions exactly as they ship. */
    private static function liftedSource(): string
    {
        $src = '';
        foreach ([self::saleHeaderPath(), self::saleSettingsPath()] as $path) {
            $file = file_get_contents($path);
            self::assertNotFalse($file, "cannot read $path");
            $file = preg_replace('/^<\?php/', '', $file, 1);
            // The lift supplies posSaleSettings.php itself, in this namespace; letting the include
            // run would define its functions globally instead, where the lifted code cannot see them.
            $file = preg_replace('/^include_once\(__DIR__ \. "\/\.\.\/\.\.\/systemdata.*$/m', '', $file, 1, $included);
            if (strpos($file, 'function pos_terminal_sale_defaults(') !== false) {
                self::assertSame(1, $included,
                    'saleHeaderFunctions.php no longer includes the sale settings, so the brand row is read twice');
            }
            // define() at file scope is global, and the constant is re-declared on a second lift.
            $file = preg_replace("/define\('POS_SALE_SETTINGS_KODENR', '([0-9]+)'\);/",
                "if (!defined('POS_SALE_SETTINGS_KODENR')) { define('POS_SALE_SETTINGS_KODENR', '\$1'); }", $file);
            $src .= $file;
        }
        foreach (['pos_terminal_sale_defaults', 'opret_posordre', 'opdater_konto',
                  'pos_sale_brands', 'pos_sale_brand_variants', 'pos_save_sale_settings',
                  'pos_render_sale_brand_field'] as $needed) {
            self::assertStringContainsString('function ' . $needed . '(', $src,
                $needed . '() is no longer where the lift expects it');
        }
        return $src;
    }

    private static function saleHeaderPath(): string
    {
        return dirname(__DIR__, 3) . '/debitor/pos_ordre_includes/saleHeaderFunctions.php';
    }

    private static function saleSettingsPath(): string
    {
        return dirname(__DIR__, 3) . '/systemdata/diverseIncludes/posSaleSettings.php';
    }

    private static function printPath(): string
    {
        return dirname(__DIR__, 3) . '/debitor/pos_ordre_includes/posTxtPrint/posTxtPrintFunc.php';
    }

    /** The insert opret_posordre() runs, read from the shipped source. */
    private static function createInsert(): string
    {
        $src = file_get_contents(self::saleHeaderPath());
        $from = strpos($src, 'insert into ordrer');
        self::assertNotFalse($from, 'opret_posordre() no longer inserts into ordrer');
        return substr($src, $from, strpos($src, 'db_modify', $from) - $from);
    }

    /** Form variants: the list a brand has to come from. */
    private function variants(string ...$names): void
    {
        foreach ($names as $name) {
            $this->db->exec("INSERT INTO formularer (formular,art,sprog)
                VALUES (1,'3','" . str_replace("'", "''", $name) . "')");
        }
    }

    private function brandsRow(string $list): void
    {
        $this->db->exec("INSERT INTO grupper (art,kodenr,box1,fiscal_year)
            VALUES ('POS','4','" . $list . "','" . self::REGNAAR . "')");
    }

    // -------------------------------------------- the terminal's own defaults

    public function testEachTerminalGetsItsOwnDepartment(): void
    {
        self::assertSame(1, pos_terminal_sale_defaults(1, self::REGNAAR)['afd']);
        self::assertSame(2, pos_terminal_sale_defaults(2, self::REGNAAR)['afd']);
        self::assertSame(4, pos_terminal_sale_defaults(3, self::REGNAAR)['afd']);
    }

    public function testEachTerminalGetsItsOwnBrand(): void
    {
        $this->brandsRow('havemoebelland' . chr(9) . 'udehygge' . chr(9));
        self::assertSame('havemoebelland', pos_terminal_sale_defaults(1, self::REGNAAR)['brand']);
        self::assertSame('udehygge', pos_terminal_sale_defaults(2, self::REGNAAR)['brand']);
        self::assertSame('', pos_terminal_sale_defaults(3, self::REGNAAR)['brand'],
            'a terminal with no brand configured gets none');
    }

    public function testAnUnconfiguredInstallationGetsNoDepartmentAndNoBrand(): void
    {
        $this->db->exec("DELETE FROM grupper");
        self::assertSame(['afd' => 0, 'brand' => ''], pos_terminal_sale_defaults(1, self::REGNAAR));
    }

    public function testATerminalBeyondTheConfiguredListGetsNothingRatherThanAWarning(): void
    {
        $this->brandsRow('havemoebelland');
        self::assertSame(['afd' => 0, 'brand' => ''], pos_terminal_sale_defaults(9, self::REGNAAR));
    }

    public function testATerminalNumberBelowOneIsNotATerminal(): void
    {
        self::assertSame(['afd' => 0, 'brand' => ''], pos_terminal_sale_defaults(0, self::REGNAAR));
        self::assertSame(['afd' => 0, 'brand' => ''], pos_terminal_sale_defaults(-1, self::REGNAAR));
    }

    public function testTheSettingsOfAnotherFiscalYearAreNotRead(): void
    {
        $this->brandsRow('havemoebelland');
        $other = pos_terminal_sale_defaults(1, 2025);
        self::assertSame('', $other['brand']);
        self::assertSame(0, $other['afd'],
            'the department list belongs to a fiscal year too, like every other POS setting');
    }

    public function testATerminalNumberThatIsNotANumberIsAnsweredWithoutAWarning(): void
    {
        // $kasse is cast before it is compared and before one is subtracted from it. Uncast, PHP 8
        // raises "A non-numeric value encountered" on the subtraction and the index goes nowhere.
        set_error_handler(static function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            self::assertSame(['afd' => 0, 'brand' => ''], pos_terminal_sale_defaults('ikke et tal', self::REGNAAR));
        } finally {
            restore_error_handler();
        }
    }

    public function testATerminalNumberBelowOneAsksTheDatabaseNothing(): void
    {
        $GLOBALS['posSaleHeaderSql'] = [];
        pos_terminal_sale_defaults(0, self::REGNAAR);
        self::assertSame([], $GLOBALS['posSaleHeaderSql'],
            'a number that is not a terminal is answered from the guard, not from two queries');
        pos_terminal_sale_defaults(1, self::REGNAAR);
        self::assertNotSame([], $GLOBALS['posSaleHeaderSql'], 'a real terminal is still looked up');
    }

    public function testTheStoredBrandListIsReadThroughOneFunction(): void
    {
        // pos_sale_brands() is what the settings page writes against, and pos_terminal_sale_defaults()
        // reads the brand through it rather than repeating the query - one definition of the row.
        $this->brandsRow('havemoebelland' . chr(9) . 'udehygge');
        self::assertSame(['havemoebelland', 'udehygge'], pos_sale_brands(self::REGNAAR));
        self::assertSame([], pos_sale_brands(2025), 'another fiscal year has its own settings');
    }

    // ------------------------------------------- what a new sale is created with

    public function testANewSaleCarriesTheDepartmentAndTheBrand(): void
    {
        $insert = self::createInsert();
        self::assertStringContainsString('afd)', $insert,
            'the sale is created without a department, so an unprinted sale has none');
        self::assertStringContainsString("'\$brand'", $insert,
            "the brand is not written onto the sale, so the shop export cannot tell them apart");
        self::assertStringContainsString("'\$afd'", $insert);
    }

    public function testTheBrandAndDepartmentComeFromTheTerminalDefaults(): void
    {
        $src = file_get_contents(self::saleHeaderPath());
        $from = strpos($src, 'function opret_posordre(');
        $body = substr($src, $from, strpos($src, 'insert into ordrer', $from) - $from);
        self::assertMatchesRegularExpression('/\$salgsdefault\s*=\s*pos_terminal_sale_defaults\(/', $body,
            'opret_posordre() has to ask for the terminal defaults before it writes the sale');
        self::assertStringContainsString("\$salgsdefault['afd']", $body);
        self::assertStringContainsString("\$salgsdefault['brand']", $body);
        self::assertStringContainsString('db_escape_string($salgsdefault[\'brand\'])', $body,
            'the brand is free text from the settings page and reaches SQL, so it has to be escaped');
    }

    // ------------------------------------------ the receipt print leaves it alone

    public function testTheReceiptPrintNoLongerRewritesTheDepartment(): void
    {
        $print = file_get_contents(self::printPath());
        self::assertStringNotContainsString('update ordrer set afd', $print,
            'a reprint would put the printing terminal\'s department back onto the sale');
        self::assertStringNotContainsString('$afdelinger', $print,
            'the department list is no longer read here, so nothing should still explode it');
    }

    public function testTheReceiptPrintStillResolvesItsPrintServer(): void
    {
        // The department block sat immediately above the printer block and shared a $tmp with it.
        $print = file_get_contents(self::printPath());
        self::assertStringContainsString('$printer_ip=explode(chr(9),$r[\'box3\'])', $print);
        self::assertStringContainsString("kodenr = '2'", $print,
            'the printer list is still read from the POS settings');
    }

    // ------------------------------------- attaching a customer brings the phone

    public function testAttachingACustomerCopiesTheirPhoneNumberOntoTheSale(): void
    {
        $this->db->exec("INSERT INTO ordrer (id,status,art) VALUES (77,0,'PO')");
        $this->db->exec("INSERT INTO adresser (id,kontonr,art,firmanavn,email,tlf,betalingsdage,lukket)
            VALUES (5,'1001','D','Fru Hansen','fru@example.test','+45 12 34 56 78',0,'')");

        $id = opdater_konto(5, '1001', 77);

        self::assertSame(77, (int)$id);
        $row = $this->db->query("SELECT konto_id,firmanavn,email,phone FROM ordrer WHERE id=77")
            ->fetch(PDO::FETCH_ASSOC);
        self::assertSame('5', (string)$row['konto_id']);
        self::assertSame('Fru Hansen', $row['firmanavn']);
        self::assertSame('+45 12 34 56 78', $row['phone'],
            'a pickup or delivery sale has to carry a way to reach the customer');
    }

    public function testACustomerWithNoPhoneNumberLeavesItEmptyRatherThanUnset(): void
    {
        $this->db->exec("INSERT INTO ordrer (id,status,art) VALUES (78,0,'PO')");
        $this->db->exec("INSERT INTO adresser (id,kontonr,art,firmanavn,tlf,betalingsdage,lukket)
            VALUES (6,'1002','D','Hr. Jensen','',0,'')");

        opdater_konto(6, '1002', 78);
        self::assertSame('', (string)$this->db->query("SELECT phone FROM ordrer WHERE id=78")->fetchColumn());
    }

    public function testAPhoneNumberCarryingAQuoteIsStored(): void
    {
        $this->db->exec("INSERT INTO ordrer (id,status,art) VALUES (79,0,'PO')");
        $this->db->exec("INSERT INTO adresser (id,kontonr,art,firmanavn,tlf,betalingsdage,lukket)
            VALUES (7,'1003','D','O''Brien','12 34 ''56',0,'')");

        opdater_konto(7, '1003', 79);
        self::assertSame("12 34 '56", $this->db->query("SELECT phone FROM ordrer WHERE id=79")->fetchColumn());
    }

    public function testASaleAlreadyInvoicedIsNotTouched(): void
    {
        $this->db->exec("INSERT INTO ordrer (id,status,art,phone) VALUES (80,3,'PO','unchanged')");
        $this->db->exec("INSERT INTO adresser (id,kontonr,art,firmanavn,tlf,betalingsdage,lukket)
            VALUES (8,'1004','D','Sen kunde','99 99 99 99',0,'')");

        opdater_konto(8, '1004', 80);
        self::assertSame('unchanged', $this->db->query("SELECT phone FROM ordrer WHERE id=80")->fetchColumn());
    }

    // --------------------------------------------- the settings page round trip

    public function testABrandSavedInSettingsIsTheBrandTheTillUses(): void
    {
        pos_save_sale_settings(['kasseantal' => 3, 'brand_navn' => [0 => 'havemoebelland', 1 => 'udehygge']], self::REGNAAR);
        self::assertSame('havemoebelland', pos_terminal_sale_defaults(1, self::REGNAAR)['brand']);
        self::assertSame('udehygge', pos_terminal_sale_defaults(2, self::REGNAAR)['brand']);
        self::assertSame('', pos_terminal_sale_defaults(3, self::REGNAAR)['brand']);
    }

    public function testATerminalLeftBlankKeepsItsPlaceInTheList(): void
    {
        // Terminal 2 has no brand; terminal 3 must not inherit terminal 3's value from a shifted list.
        $this->variants('tredje');
        pos_save_sale_settings(['kasseantal' => 3, 'brand_navn' => [0 => 'havemoebelland', 2 => 'tredje']], self::REGNAAR);
        self::assertSame('havemoebelland', pos_terminal_sale_defaults(1, self::REGNAAR)['brand']);
        self::assertSame('', pos_terminal_sale_defaults(2, self::REGNAAR)['brand']);
        self::assertSame('tredje', pos_terminal_sale_defaults(3, self::REGNAAR)['brand']);
    }

    public function testSavingTwiceUpdatesTheSameSettingsRow(): void
    {
        $this->variants('foerste', 'anden');
        pos_save_sale_settings(['kasseantal' => 1, 'brand_navn' => [0 => 'foerste']], self::REGNAAR);
        pos_save_sale_settings(['kasseantal' => 1, 'brand_navn' => [0 => 'anden']], self::REGNAAR);
        self::assertSame('1', (string)$this->db->query(
            "SELECT COUNT(*) FROM grupper WHERE art='POS' AND kodenr='4'")->fetchColumn());
        self::assertSame('anden', pos_terminal_sale_defaults(1, self::REGNAAR)['brand']);
    }

    public function testABrandCannotCarryTheListSeparatorIntoAnotherTerminal(): void
    {
        $this->variants('havemoebelland smuglet');
        pos_save_sale_settings(['kasseantal' => 2,
            'brand_navn' => [0 => "havemoebelland" . chr(9) . "smuglet", 1 => 'udehygge']], self::REGNAAR);
        self::assertSame('havemoebelland smuglet', pos_terminal_sale_defaults(1, self::REGNAAR)['brand']);
        self::assertSame('udehygge', pos_terminal_sale_defaults(2, self::REGNAAR)['brand'],
            'a tab in one brand would otherwise push every later terminal onto the wrong one');
    }

    public function testABrandCarryingAQuoteIsStoredAsTyped(): void
    {
        $this->variants("O'Brien & Sons");
        pos_save_sale_settings(['kasseantal' => 1, 'brand_navn' => [0 => "O'Brien & Sons"]], self::REGNAAR);
        self::assertSame("O'Brien & Sons", pos_terminal_sale_defaults(1, self::REGNAAR)['brand']);
    }

    public function testAFormWithNoBrandFieldsLeavesTheSettingsAlone(): void
    {
        $this->brandsRow('havemoebelland');
        pos_save_sale_settings(['kasseantal' => 1], self::REGNAAR);
        self::assertSame('havemoebelland', pos_terminal_sale_defaults(1, self::REGNAAR)['brand'],
            'another section of the settings page must not wipe the brands');
    }

    public function testTheBrandFieldIsEscapedForTheBrowser(): void
    {
        $html = $this->renderField(["<script>alert('x')</script>"], 0, ["<script>alert('x')</script>"]);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('name=\'brand_navn[0]\'', $html);
    }

    // ------------------------------------- the brand has to be an existing form variant

    public function testTheBrandFieldOffersTheFormVariantsAndAnEmptyChoice(): void
    {
        $html = $this->renderField([], 0, pos_sale_brand_variants());
        self::assertStringContainsString('<select', $html,
            'a typed brand can name a form variant that does not exist');
        self::assertStringNotContainsString("type='text'", $html);
        self::assertStringContainsString("<option value=''></option>", $html,
            'no brand has to stay selectable - it is what every install has today');
        foreach (['Dansk', 'havemoebelland', 'udehygge'] as $variant) {
            self::assertStringContainsString("<option value='" . $variant . "'>", $html);
        }
    }

    public function testTheConfiguredBrandIsTheSelectedOption(): void
    {
        $html = $this->renderField(['udehygge'], 0, pos_sale_brand_variants());
        self::assertStringContainsString("<option value='udehygge' selected>", $html);
        self::assertSame(1, substr_count($html, 'selected'), 'exactly one option may be selected');
        self::assertSame(1, substr_count($html, "value='udehygge'"),
            'the brand has to be selected where it is listed, not listed once and appended again');
    }

    public function testABrandWhoseVariantWasDeletedStaysSelectedRatherThanClearing(): void
    {
        $html = $this->renderField(['forsvundet'], 0, pos_sale_brand_variants());
        self::assertStringContainsString("<option value='forsvundet' selected>", $html,
            'drawing the page must not silently change what a terminal sells under');
    }

    public function testAStaleBrandIsEscapedForTheBrowserToo(): void
    {
        // The stored brand reaches the page down its own path when its variant is gone, so it needs
        // escaping of its own.
        $html = $this->renderField(["<script>alert('x')</script>"], 0, pos_sale_brand_variants());
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('selected', $html);
    }

    public function testABlankFormVariantIsNotOfferedAsABrand(): void
    {
        $this->variants('   ');
        self::assertSame(['Dansk', 'havemoebelland', 'udehygge'], pos_sale_brand_variants(),
            'a blank variant would offer a brand that is empty but not the empty choice');
    }

    public function testVariantsAreReadFromFormularerWithoutDuplicates(): void
    {
        $this->variants('havemoebelland');
        self::assertSame(['Dansk', 'havemoebelland', 'udehygge'], pos_sale_brand_variants());
    }

    public function testABrandWithNoFormVariantIsRefusedAndTheStoredOneKept(): void
    {
        pos_save_sale_settings(['kasseantal' => 1, 'brand_navn' => [0 => 'havemoebelland']], self::REGNAAR);
        pos_save_sale_settings(['kasseantal' => 1, 'brand_navn' => [0 => 'ikkeenvariant']], self::REGNAAR);
        self::assertSame('havemoebelland', pos_terminal_sale_defaults(1, self::REGNAAR)['brand'],
            'ordrer.sprog would name a form variant that does not exist, and formprintfunc.php would '
            . 'lay the invoice out from unset variables');
    }

    public function testARefusedBrandIsReportedRatherThanDroppedSilently(): void
    {
        pos_save_sale_settings(['kasseantal' => 1, 'brand_navn' => [0 => 'ikkeenvariant']], self::REGNAAR);
        self::assertCount(1, $GLOBALS['posSaleHeaderAlerts']);
        self::assertStringContainsString('ikkeenvariant', $GLOBALS['posSaleHeaderAlerts'][0]);
    }

    public function testClearingABrandIsNotARefusal(): void
    {
        pos_save_sale_settings(['kasseantal' => 1, 'brand_navn' => [0 => 'havemoebelland']], self::REGNAAR);
        pos_save_sale_settings(['kasseantal' => 1, 'brand_navn' => [0 => '']], self::REGNAAR);
        self::assertSame('', pos_terminal_sale_defaults(1, self::REGNAAR)['brand']);
        self::assertSame([], $GLOBALS['posSaleHeaderAlerts'], 'emptying a brand is a normal edit');
    }

    public function testARefusedBrandDoesNotDisturbTheOtherTerminals(): void
    {
        pos_save_sale_settings(['kasseantal' => 2,
            'brand_navn' => [0 => 'ikkeenvariant', 1 => 'udehygge']], self::REGNAAR);
        self::assertSame('', pos_terminal_sale_defaults(1, self::REGNAAR)['brand']);
        self::assertSame('udehygge', pos_terminal_sale_defaults(2, self::REGNAAR)['brand']);
    }

    private function renderField(array $brands, int $index, array $variants): string
    {
        ob_start();
        pos_render_sale_brand_field($brands, $index, $variants);
        return (string)ob_get_clean();
    }

    // --------------------------------------------------- how the page wires it up

    public function testTheSettingsPageRendersAndSavesTheBrand(): void
    {
        $options = file_get_contents(dirname(__DIR__, 3) . '/systemdata/diverseIncludes/posOptions.php');
        self::assertStringContainsString('posSaleSettings.php', $options,
            'posOptions.php has to load the sale settings to render them');
        self::assertStringContainsString('pos_render_sale_brand_header(', $options);
        self::assertStringContainsString('pos_render_sale_brand_field(', $options);
        self::assertMatchesRegularExpression('/\$pos_sale_brands\s*=\s*pos_sale_brands\(/', $options,
            'the rendered field needs the stored brands');
        self::assertStringContainsString('pos_sale_brand_variants()', $options,
            'without the variant list the field would offer no brands at all');

        $diverse = file_get_contents(dirname(__DIR__, 3) . '/systemdata/diverse.php');
        self::assertStringContainsString('pos_save_sale_settings($_POST, $regnaar)', $diverse,
            'diverse.php owns the posOptions POST, so without this the brand is never saved');
    }

    public function testTheSaleHeaderFunctionsAreNoLongerDefinedInThePage(): void
    {
        $page = file_get_contents(dirname(__DIR__, 3) . '/debitor/pos_ordre.php');
        self::assertStringNotContainsString('function opret_posordre(', $page);
        self::assertStringNotContainsString('function opdater_konto(', $page);
        self::assertStringContainsString('saleHeaderFunctions.php', $page,
            'pos_ordre.php calls both functions, so it has to include them');
    }
}
