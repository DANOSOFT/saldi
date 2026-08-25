<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * CHARACTERIZATION suite for saveLabelText()/loadLabelText() (systemdata/sys_div_func.php),
 * pinning the MB-17 fix: editing a label's HTML and saving it must actually be what the next
 * load (page reopen, or lager/labelprint.php's own read) sees.
 *
 * Before commit d06696c2 ("SST-738 Label saving fix", 2026-08-24), each save branch in
 * systemdata/diverse.php wrote inline, without the account_id scoping the print path reads
 * with - so an edit could land in a row nothing else ever looked at, and reopening the editor
 * (or printing) would still show the previous value. saveLabelText()/loadLabelText() now share
 * the exact same scoping ("account_id = 0 or null", healed to 0 on write) so a save is always
 * visible to the very next load. This suite drives those two functions directly against a real
 * Postgres tenant - MB-17's reproduction confirmed the round trip already works on current
 * master; these tests pin that so it can't silently regress.
 *
 * Connection details are never literals here (doc/ai/convention_no_hardcoded_secrets.md): the
 * suite uses whatever the gitignored includes/connect.php says, exactly like the app does. The
 * "saldi_chartest" tenant database must already exist on that host, cloned from an installed
 * tenant; when it can't be reached the whole suite is skipped rather than errored.
 */
final class SaveLabelTextCharacterizationTest extends TestCase
{
    private const TENANT_DB = 'saldi_chartest';
    private const LABEL_NAME = 'MB17CharacterizationLabel';

    private static string $repoRoot;
    private static string $originalCwd;
    private static bool $connected = false;

    public static function setUpBeforeClass(): void
    {
        // Must come BEFORE the require below: an included file's top-level code runs in the
        // includer's scope, so connect.php's $sqhost/$squser/$sqpass/$sqdb assignments (and
        // settings.php's $db_type/$db_encode) would otherwise be trapped as locals of this
        // method. Binding the names to the global scope first sends them where the app's own
        // code expects them - db_connect() reads global $db_type, and db_query.php's reconnect
        // helper reads global $sqhost/$squser/$sqpass.
        global $sqhost, $squser, $sqpass, $sqdb, $db_type, $db_encode, $connection, $db;

        self::$repoRoot = dirname(__DIR__, 3);
        self::$originalCwd = getcwd();

        $_SERVER['REQUEST_URI'] = '/saldi/systemdata/diverse.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // sys_div_func.php itself include_once's ../includes/connect.php, whose own relative
        // paths (and db_query.php's logging) resolve against cwd - stay inside systemdata/ for
        // the whole test lifecycle, matching how diverse.php always runs.
        chdir(self::$repoRoot . '/systemdata');
        ob_start();
        require_once self::$repoRoot . '/systemdata/sys_div_func.php';
        $includeOutput = ob_get_clean();
        // Only whitespace is tolerated: the gitignored includes/connect.php is a per-developer
        // file and commonly ends with a closing PHP tag followed by a newline, which PHP emits
        // verbatim at include time.
        self::assertSame('', trim($includeOutput), 'sys_div_func.php emitted output at include time');

        // $sqhost/$squser/$sqpass now hold whatever the gitignored connect.php says.
        $db = self::TENANT_DB;
        $connection = @db_connect($sqhost, $squser, $sqpass, $db);
        if (!$connection) {
            self::markTestSkipped(
                "tenant db '" . self::TENANT_DB . "' not reachable on host '$sqhost' as user '$squser' " .
                '- clone it from an installed tenant first'
            );
        }
        self::$connected = true;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$connected) {
            self::cleanUpFixtures();
        }
        chdir(self::$originalCwd);
    }

    protected function setUp(): void
    {
        self::cleanUpFixtures();
    }

    private static function cleanUpFixtures(): void
    {
        db_modify(
            "delete from labels where labelname in ('" . self::LABEL_NAME . "', 'Standard')",
            __FILE__ . ' linje ' . __LINE__
        );
        db_modify("delete from grupper where art = 'LABEL'", __FILE__ . ' linje ' . __LINE__);
    }

    /**
     * The literal MB-17 scenario: edit a custom label's raw HTML and save it twice (simulating
     * an edit-then-save), then reload - the reload must see the LATEST save, not the first one.
     */
    public function testEditingAndSavingACustomLabelPersistsTheLatestEdit(): void
    {
        saveLabelText('box1', self::LABEL_NAME, '<p>version A</p>', 'sheet');
        saveLabelText('box1', self::LABEL_NAME, '<p>version B - edited</p>', 'label');

        $reloaded = loadLabelText('box1', self::LABEL_NAME);

        self::assertSame('<p>version B - edited</p>', $reloaded['labeltext']);
        self::assertSame('label', $reloaded['labeltype']);
    }

    /**
     * The 'Standard' label is mirrored into grupper.box1 (the pre-4.0 fallback) so that fallback
     * can never serve a stale template once the labels-table row has been edited.
     */
    public function testSavingTheStandardLabelMirrorsIntoGrupperFallback(): void
    {
        saveLabelText('box1', 'Standard', '<p>standard v1</p>', 'sheet');
        saveLabelText('box1', 'Standard', '<p>standard v2 - edited</p>', 'sheet');

        $reloaded = loadLabelText('box1', 'Standard');
        self::assertSame('<p>standard v2 - edited</p>', $reloaded['labeltext']);

        $fallback = db_fetch_array(db_select(
            "select box1 from grupper where art = 'LABEL'",
            __FILE__ . ' linje ' . __LINE__
        ));
        self::assertSame('<p>standard v2 - edited</p>', $fallback['box1']);
    }

    /**
     * Address labels (box2) only ever live in grupper, never in the labels table.
     */
    public function testAddressLabelRoundTripsThroughGrupperOnly(): void
    {
        saveLabelText('box2', 'Standard', '<p>address v1</p>', 'sheet');
        saveLabelText('box2', 'Standard', '<p>address v2 - edited</p>', 'sheet');

        $reloaded = loadLabelText('box2', 'Standard');
        self::assertSame('<p>address v2 - edited</p>', $reloaded['labeltext']);

        $labelsRow = db_fetch_array(db_select(
            "select id from labels where labelname = 'Standard'",
            __FILE__ . ' linje ' . __LINE__
        ));
        self::assertFalse($labelsRow, 'box2 saves must never create a row in the labels table');
    }

    /**
     * A pre-4.0 row with a NULL account_id (legacy data) must be healed to account_id = 0 on
     * save, not left alongside a second, newly-inserted row - two rows for the same labelname
     * is exactly the shape of bug that makes loadLabelText's ordering pick the wrong one and a
     * saved edit appear to "not stick".
     */
    public function testLegacyNullAccountRowIsHealedInPlaceNotDuplicated(): void
    {
        db_modify(
            "insert into labels (account_id, labelname, labeltype, labeltext) values " .
            "(NULL, '" . self::LABEL_NAME . "', 'sheet', '<p>legacy pre-4.0 text</p>')",
            __FILE__ . ' linje ' . __LINE__
        );

        saveLabelText('box1', self::LABEL_NAME, '<p>healed and edited</p>', 'sheet');

        $rows = db_select(
            "select account_id, labeltext from labels where labelname = '" . self::LABEL_NAME . "'",
            __FILE__ . ' linje ' . __LINE__
        );
        $all = [];
        while ($r = db_fetch_array($rows)) {
            $all[] = $r;
        }

        self::assertCount(1, $all, 'expected the legacy row to be healed in place, not duplicated');
        self::assertSame('0', $all[0]['account_id']);
        self::assertSame('<p>healed and edited</p>', $all[0]['labeltext']);

        $reloaded = loadLabelText('box1', self::LABEL_NAME);
        self::assertSame('<p>healed and edited</p>', $reloaded['labeltext']);
    }
}
