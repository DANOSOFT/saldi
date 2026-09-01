<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * CHARACTERIZATION suite for lager/labelprint_includes/newlabel.php, pinning
 * the MB-16 fix: printing a SPECIFIC label (a plain item-card print, or one
 * entry of a printIds batch) must render exactly one cell, never the
 * template's full rows x cols sheet grid. Before the fix, a 2-column
 * template turned a single requested label into two - the second rendered
 * with fallback/blank data nobody asked for.
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
        db_modify("delete from varer where varenr = 'knXXX" . self::KONTONR . "'", __FILE__ . ' linje ' . __LINE__);
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

    private function twoColumnTemplate(): string
    {
        return "\$cols=2;\n\$rows=1;\n\$txtlen=50;\n<top>\n<div id=\"main\">\n</top>\n\n" .
            "<p>\n\$minbeskrivelse<br>\nPris \$minpris<br>\nStregkode: \$barcode<br>\nDato: \$createdate<br>\n</p>\n\n" .
            "<bottom>\n</div>\n/bottom;";
    }

    /**
     * @param array{txt: string, account: string, condition: string, printIds: string} $args
     */
    private function renderLabel(array $args): string
    {
        $txt = $args['txt'];
        $account = $args['account'];
        $condition = $args['condition'];
        $printIds = $args['printIds'];

        $id = null;
        $labelId = null;
        $img = null;
        $stregkode = null;
        $varenr = null;
        $page = null;
        $qty = null;
        $brotherTD = 0;
        $filename = tempnam(sys_get_temp_dir(), 'mb16_char_') . '.html';

        include self::$repoRoot . '/lager/labelprint_includes/newlabel.php';

        $html = file_get_contents($filename);
        unlink($filename);

        return $html;
    }
}
