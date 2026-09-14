<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * CHARACTERIZATION suite for lager/labelprint_includes/newlabel.php, pinning
 * two things:
 *
 *  - MB-16: printing a SPECIFIC label (one entry of a printIds batch) must
 *    render exactly one cell, never the template's full rows x cols sheet
 *    grid. Before that fix a 2-column template turned a single requested
 *    label into two - the second rendered with fallback/blank data nobody
 *    asked for. 9f756f03 later dropped $page from the gate, which leaves this
 *    case untouched: a specific label still renders one cell.
 *  - SST-790: a *plain* item-card print (no labelId/printIds) renders the
 *    template's whole grid again - that is the sheet the customer prints - and
 *    an item price of 0,00 leaves the price field empty instead of printing
 *    "0,00".
 *
 * newlabel.php is a legacy top-level script (no functions to unit-test in
 * isolation): it reads globals ($id/$printIds/$page/$account/...), queries
 * the mit-salg (mylabel) table directly, and writes its HTML output to
 * $filename. This suite drives it exactly as lager/labelprint.php does,
 * against a real Postgres tenant, mirroring the reproduction used to
 * root-cause MB-16.
 *
 * Requires a local Postgres reachable with includes/connect.php's checked-in
 * dev credentials (localhost/postgres), and a "saldi_chartest" database
 * already cloned from the saldidb template - the same tenant convention
 * used by tests/characterization/order-creation on the SD-600 branch.
 */
final class NewlabelCharacterizationTest extends TestCase
{
    private const TENANT_DB = 'saldi_chartest';
    private const KONTONR = '87654321';

    // includes/connect.php's own checked-in dev defaults. Not read back off
    // its globals: connect.php is require_once'd from inside this method,
    // so its top-level `$sqhost = ...`-style assignments land in THIS
    // method's local scope, not the true global scope db_connect() reads -
    // reusing them via `global $sqhost` would silently pull undefined
    // globals and leave the connection on connect.php's own default (the
    // schema-less "saldidb" master, which has none of the tenant tables
    // below).
    private const PG_HOST = 'localhost';
    private const PG_USER = 'postgres';
    private const PG_PASS = 'saul3112';

    private static string $repoRoot;
    private static string $originalCwd;
    private static int $accountId;

    public static function setUpBeforeClass(): void
    {
        self::$repoRoot = dirname(__DIR__, 4);
        self::$originalCwd = getcwd();

        $_SERVER['REQUEST_URI'] = '/saldi/lager/labelprint.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // newlabel.php and db_query.php's own logging both resolve paths
        // relative to cwd via get_relative() - stay inside lager/ for the
        // whole test lifecycle (matching how labelprint.php always runs),
        // restored only in tearDownAfterClass.
        chdir(self::$repoRoot . '/lager');
        ob_start();
        require_once self::$repoRoot . '/includes/connect.php';
        require_once self::$repoRoot . '/includes/std_func.php';
        ob_end_clean();

        global $db, $connection;
        $db = self::TENANT_DB;
        $connection = db_connect(self::PG_HOST, self::PG_USER, self::PG_PASS, $db);

        db_modify("delete from adresser where kontonr = '" . self::KONTONR . "'", __FILE__ . ' linje ' . __LINE__);
        db_modify(
            "insert into adresser (kontonr, art, firmanavn) values ('" . self::KONTONR . "', 'D', 'MB-16 characterization konto')",
            __FILE__ . ' linje ' . __LINE__
        );
        $row = db_fetch_array(db_select(
            "select id from adresser where kontonr = '" . self::KONTONR . "'",
            __FILE__ . ' linje ' . __LINE__
        ));
        self::$accountId = (int)$row['id'];

        // newlabel.php resolves the consignor's own item card via
        // `varenr like 'kn___<kontonr>'` (mit-salg "new condition" naming
        // convention) even when printing by mylabel id - seed a matching
        // item so that lookup succeeds exactly as it would in production.
        db_modify("delete from varer where varenr = 'knXXX" . self::KONTONR . "'", __FILE__ . ' linje ' . __LINE__);
        db_modify(
            "insert into varer (varenr, beskrivelse, gruppe, salgspris, kostpris) values " .
            "('knXXX" . self::KONTONR . "', 'MB-16 characterization item', 0, '1.00', '0.50')",
            __FILE__ . ' linje ' . __LINE__
        );
    }

    public static function tearDownAfterClass(): void
    {
        db_modify('delete from mylabel where account_id = ' . self::$accountId, __FILE__ . ' linje ' . __LINE__);
        db_modify("delete from adresser where kontonr = '" . self::KONTONR . "'", __FILE__ . ' linje ' . __LINE__);
        foreach (['knXXX', 'knPLAIN', 'knZERO', 'knZERO2', 'knPRICED', 'knSINGLE', 'knLEGACY', 'knLEGACY2'] as $prefix) {
            db_modify("delete from varer where varenr = '" . $prefix . self::KONTONR . "'", __FILE__ . ' linje ' . __LINE__);
        }
        chdir(self::$originalCwd);
    }

    protected function setUp(): void
    {
        db_modify('delete from mylabel where account_id = ' . self::$accountId, __FILE__ . ' linje ' . __LINE__);
    }

    /**
     * A two-column template ($cols=2, $rows=1) with a single mit-salg row
     * for the requested printId. Before the MB-16 fix this rendered 2 <p>
     * blocks (cell (1,2) blank on barcode/date); after the fix, exactly 1.
     */
    public function testPrintingOneSpecificLabelRendersExactlyOneCellOnAMultiColumnTemplate(): void
    {
        db_modify(
            'insert into mylabel (account_id, price, description, barcode, hidden, created) values ' .
            '(' . self::$accountId . ", 50, 'MB-16 consignor item', 'BC0001', FALSE, '010126')",
            __FILE__ . ' linje ' . __LINE__
        );
        $labelRow = db_fetch_array(db_select(
            'select id from mylabel where account_id = ' . self::$accountId . ' order by id limit 1',
            __FILE__ . ' linje ' . __LINE__
        ));
        $requestedId = (int)$labelRow['id'];

        $html = $this->renderLabel([
            'txt' => $this->twoColumnTemplate(),
            'account' => self::KONTONR,
            'condition' => 'new',
            'printIds' => (string)$requestedId,
        ]);

        self::assertSame(1, preg_match_all('/<p>/', $html), 'expected exactly one rendered label cell for one requested id');
        self::assertStringContainsString('MB-16 consignor item', $html);
    }

    /**
     * Two distinct requested printIds against the same two-column template
     * must render exactly 2 cells (one per requested id), not 4 (2 per id).
     */
    public function testPrintingTwoSpecificLabelsRendersExactlyTwoCells(): void
    {
        db_modify(
            'insert into mylabel (account_id, price, description, barcode, hidden, created) values ' .
            '(' . self::$accountId . ", 50, 'First consignor item', 'BC0001', FALSE, '010126'), " .
            '(' . self::$accountId . ", 75, 'Second consignor item', 'BC0002', FALSE, '020126')",
            __FILE__ . ' linje ' . __LINE__
        );
        $rows = db_select(
            'select id from mylabel where account_id = ' . self::$accountId . ' order by id',
            __FILE__ . ' linje ' . __LINE__
        );
        $ids = [];
        while ($r = db_fetch_array($rows)) {
            $ids[] = $r['id'];
        }

        $html = $this->renderLabel([
            'txt' => $this->twoColumnTemplate(),
            'account' => self::KONTONR,
            'condition' => 'new',
            'printIds' => implode(',', $ids),
        ]);

        self::assertSame(2, preg_match_all('/<p>/', $html), 'expected exactly two rendered label cells for two requested ids');
    }

    /**
     * A single-column template ($cols=1, $rows=1) already only had one cell
     * to begin with - this must stay unchanged by the fix (regression guard
     * on the common case).
     */
    public function testPrintingOneSpecificLabelOnASingleColumnTemplateIsUnaffected(): void
    {
        db_modify(
            'insert into mylabel (account_id, price, description, barcode, hidden, created) values ' .
            '(' . self::$accountId . ", 50, 'MB-16 consignor item', 'BC0001', FALSE, '010126')",
            __FILE__ . ' linje ' . __LINE__
        );
        $labelRow = db_fetch_array(db_select(
            'select id from mylabel where account_id = ' . self::$accountId . ' order by id limit 1',
            __FILE__ . ' linje ' . __LINE__
        ));

        $html = $this->renderLabel([
            'txt' => "\$cols=1;\n\$rows=1;\n\$txtlen=50;\n<top>\n<div id=\"main\">\n</top>\n\n" .
                "<p>\n\$minbeskrivelse<br>\nPris \$minpris<br>\nStregkode: \$barcode<br>\n</p>\n\n" .
                "<bottom>\n</div>\n/bottom;",
            'account' => self::KONTONR,
            'condition' => 'new',
            'printIds' => (string)(int)$labelRow['id'],
        ]);

        self::assertSame(1, preg_match_all('/<p>/', $html));
    }

    /**
     * SST-790: a plain item-card print carries no labelId/printIds, so the
     * template's full rows x cols grid is rendered - the sheet behaviour the
     * customer expects and 9f756f03 restored.
     */
    public function testPlainItemCardPrintRendersTheWholeSheetGrid(): void
    {
        $itemId = $this->seedItem('knPLAIN' . self::KONTONR, 'Plain print item', '1.00');

        $html = $this->renderLabel([
            'txt' => $this->twoColumnTemplate(),
            'id' => $itemId,
        ]);

        self::assertSame(2, preg_match_all('/<p>/', $html), 'a plain print must fill the template grid');
    }

    /**
     * SST-790: the item's own price is 0,00 and no mit-salg row is read, so
     * the price field must come out empty - not "0,00".
     */
    public function testZeroItemPriceLeavesTheMinprisFieldEmpty(): void
    {
        $itemId = $this->seedItem('knZERO' . self::KONTONR, 'Zero price item', '0.000');

        $html = $this->renderLabel([
            'txt' => $this->priceTemplate('$minpris'),
            'id' => $itemId,
        ]);

        self::assertStringNotContainsString('0,00', $html);
        self::assertMatchesRegularExpression('/Pris\s*<br/', $html, 'price field must be empty');
    }

    /**
     * SST-790: same for the $pris placeholder (the layout the reported tenant
     * prints) - that substitution was unconditional until now.
     */
    public function testZeroItemPriceLeavesThePrisFieldEmpty(): void
    {
        $itemId = $this->seedItem('knZERO2' . self::KONTONR, 'Zero price item 2', '0.000');

        $html = $this->renderLabel([
            'txt' => $this->priceTemplate('$pris'),
            'id' => $itemId,
        ]);

        self::assertStringNotContainsString('0,00', $html);
        self::assertMatchesRegularExpression('/Pris\s*<br/', $html, 'price field must be empty');
    }

    /**
     * SST-790: a mit-salg row with a real zero price goes through the other
     * branch, where the value was already formatted to "0,00" - and "0,00" == 0
     * is false on PHP 8, so the guard has to test the raw value.
     */
    public function testZeroPricedMylabelRowLeavesTheMinprisFieldEmpty(): void
    {
        db_modify(
            'insert into mylabel (account_id, price, description, barcode, hidden, created) values ' .
            '(' . self::$accountId . ", 0, 'Zero priced consignor item', 'BC0003', FALSE, '010126')",
            __FILE__ . ' linje ' . __LINE__
        );
        $labelRow = db_fetch_array(db_select(
            'select id from mylabel where account_id = ' . self::$accountId . ' order by id limit 1',
            __FILE__ . ' linje ' . __LINE__
        ));

        $html = $this->renderLabel([
            'txt' => $this->priceTemplate('$minpris'),
            'account' => self::KONTONR,
            'condition' => 'new',
            'printIds' => (string)(int)$labelRow['id'],
        ]);

        self::assertStringNotContainsString('0,00', $html);
        self::assertMatchesRegularExpression('/Pris\s*<br/', $html, 'price field must be empty');
    }

    /** Control: a real price still prints formatted. */
    public function testNonZeroItemPriceStillPrintsTheFormattedPrice(): void
    {
        $itemId = $this->seedItem('knPRICED' . self::KONTONR, 'Priced item', '12.50');

        $html = $this->renderLabel([
            'txt' => $this->priceTemplate('$pris'),
            'id' => $itemId,
        ]);

        # VAT handling in the tenant may scale the price, so assert the shape, not the amount.
        self::assertMatchesRegularExpression('/Pris\s+[0-9]+,[0-9]{2}<br/', $html);
    }

    /** Seeds an item and returns its id. */
    private function seedItem(string $varenr, string $beskrivelse, string $salgspris): int
    {
        db_modify("delete from varer where varenr = '" . $varenr . "'", __FILE__ . ' linje ' . __LINE__);
        db_modify(
            "insert into varer (varenr, beskrivelse, gruppe, salgspris, kostpris) values " .
            "('" . $varenr . "', '" . $beskrivelse . "', 0, '" . $salgspris . "', '0.50')",
            __FILE__ . ' linje ' . __LINE__
        );
        $row = db_fetch_array(db_select(
            "select id from varer where varenr = '" . $varenr . "'",
            __FILE__ . ' linje ' . __LINE__
        ));

        return (int)$row['id'];
    }

    /** One label cell with the given price placeholder. */
    private function priceTemplate(string $pricePlaceholder): string
    {
        return "\$cols=1;\n\$rows=1;\n\$txtlen=40;\n<top>\n<div id=\"main\">\n</top>\n\n" .
            "<p>\n\$varenr<br>\nPris " . $pricePlaceholder . "<br>\n</p>\n\n" .
            "<bottom>\n</div>\n/bottom;";
    }

    /**
     * single=1 is the explicit "one label" request the layout chooser and the Brother
     * forms carry (bcd1fd0a) - it must render one cell even though a plain print now
     * fills the template's grid.
     */
    public function testSingleParameterRendersOneCellForAPlainPrint(): void
    {
        $itemId = $this->seedItem('knSINGLE' . self::KONTONR, 'Single label item', '1.00');

        $html = $this->renderLabel([
            'txt' => $this->twoColumnTemplate(),
            'id' => $itemId,
            'single' => 1,
        ]);

        self::assertSame(1, preg_match_all('/<p>/', $html), 'single=1 must render exactly one cell');
    }

    /**
     * The same rule has to hold in oldlabel.php: labelprint.php:145-149 picks that
     * renderer whenever a layout has neither $rows nor $minpris/$minbeskrivelse, and
     * its $pris substitution is independent of newlabel.php's.
     */
    public function testZeroItemPriceLeavesTheLegacyPrisFieldEmpty(): void
    {
        $itemId = $this->seedItem('knLEGACY' . self::KONTONR, 'Legacy layout item', '0.000');
        $template = $this->legacyPriceTemplate('$pris');

        // Precondition: nothing here routes the layout to newlabel.php instead.
        self::assertStringNotContainsString('$rows', $template);
        self::assertStringNotContainsString('$minpris', $template);
        self::assertStringNotContainsString('$minbeskrivelse', $template);

        $html = $this->renderLegacyLabel(['txt' => $template, 'id' => $itemId]);

        self::assertStringNotContainsString('0,00', $html, 'legacy renderer must not print 0,00');
        self::assertMatchesRegularExpression('/Pris\s*<br/', $html, 'legacy price field must be empty');
    }

    /** Control: the legacy renderer still prints a real price. */
    public function testLegacyRendererStillPrintsARealPrice(): void
    {
        $itemId = $this->seedItem('knLEGACY2' . self::KONTONR, 'Legacy priced item', '12.50');

        $html = $this->renderLegacyLabel([
            'txt' => $this->legacyPriceTemplate('$pris'),
            'id' => $itemId,
        ]);

        self::assertMatchesRegularExpression('/Pris\s+[0-9]+,[0-9]{2}<br/', $html);
    }

    /** A layout that labelprint.php would send to oldlabel.php (no $rows/$minpris). */
    private function legacyPriceTemplate(string $pricePlaceholder): string
    {
        return "<top>\n<div id=\"main\">\n</top>\n\n" .
            "<p>\n\$varenr<br>\nPris " . $pricePlaceholder . "<br>\n</p>\n\n" .
            "<bottom>\n</div>\n/bottom;";
    }

    /**
     * Drives oldlabel.php the way labelprint.php does ($filename receives the
     * rendered text).
     *
     * @param array{txt: string, id?: int|null, stregkode?: string|null, varenr?: string|null} $args
     */
    private function renderLegacyLabel(array $args): string
    {
        $txt = $args['txt'];
        $id = $args['id'] ?? null;
        $stregkode = $args['stregkode'] ?? null;
        $varenr = $args['varenr'] ?? null;
        $img = null;
        $variant = $variant_type = null;
        $filename = tempnam(sys_get_temp_dir(), 'sst790_old_');
        self::assertNotFalse($filename);

        include self::$repoRoot . '/lager/labelprint_includes/oldlabel.php';

        $html = file_get_contents($filename);
        unlink($filename);

        return $html;
    }

    private function twoColumnTemplate(): string
    {
        return "\$cols=2;\n\$rows=1;\n\$txtlen=50;\n<top>\n<div id=\"main\">\n</top>\n\n" .
            "<p>\n\$minbeskrivelse<br>\nPris \$minpris<br>\nStregkode: \$barcode<br>\nDato: \$createdate<br>\n</p>\n\n" .
            "<bottom>\n</div>\n/bottom;";
    }

    /**
     * @param array{txt: string, account?: string, condition?: string, printIds?: string, id?: int|null, page?: int|null, single?: int} $args
     */
    private function renderLabel(array $args): string
    {
        $txt = $args['txt'];
        $account = $args['account'] ?? '';
        $condition = $args['condition'] ?? '';
        $printIds = $args['printIds'] ?? '';

        $id = $args['id'] ?? null;
        $labelId = null;
        $img = null;
        $stregkode = null;
        $varenr = null;
        $page = $args['page'] ?? null;
        $single = $args['single'] ?? 0;
        $qty = null;
        $brotherTD = 0;
        $filename = tempnam(sys_get_temp_dir(), 'mb16_char_') . '.html';

        include self::$repoRoot . '/lager/labelprint_includes/newlabel.php';

        $html = file_get_contents($filename);
        unlink($filename);

        return $html;
    }
}
