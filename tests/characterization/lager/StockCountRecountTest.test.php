<?php
// 20261105 CL/LH Pins that Bogfør in Lageroptælling adds up an item's counts and demands a recount when its stock changed between them (testlab stock-count-import-recount).
//                The recount list runs the real vis_optalling() on sqlite, so an item number is looked up as text, also O'RING or 0.

namespace Saldi\Tests\StockCountRecount;

use PDO;
use PHPUnit\Framework\TestCase;

function db_select($sql, $source = null) {
    $GLOBALS['stockCountSql'][] = $sql;
    return $GLOBALS['stockCountDb']->query($sql);
}

function db_fetch_array($statement) {
    if ($statement === false) {
        return false;
    }
    return $statement->fetch(PDO::FETCH_ASSOC);
}

function db_modify($sql, $source = null) {
    $GLOBALS['stockCountSql'][] = $sql;
    $GLOBALS['stockCountDb']->exec($sql);
    return "0\tquery accepted";
}

/** pg_escape_string() with standard_conforming_strings on, which is how sqlite quotes too. */
function db_escape_string($text) {
    return str_replace("'", "''", (string)$text);
}

function dkdecimal($tal, $decimaler = 2) {
    return is_numeric($tal) ? number_format((float)$tal, (int)$decimaler, ',', '.') : $tal;
}

function findtekst($textId, $languageID) {
    $parts = explode('|', (string)$textId, 2);
    return $parts[1] ?? $parts[0];
}

/**
 * bogfor() in lager/optalling.php used to open a new entry for every row of a variant-0 item. The
 * database returns variant_id 0 as the string '0', and in_array() never found it among the NULLs kept
 * for it. The last row then replaced the ones before, so after a sale and a second shelf count the
 * preview booked A at 1 - 20 = -19 and never asked for a recount. The recount list it never reached
 * also queried id='' for every item, and it hands the item number to vis_optalling(), which put it
 * into SQL unescaped and listed every item for a number PHP reads as false, such as "0".
 *
 * bogfor() is a page function that prints, posts and exits, so its grouping loop and recount list are
 * lifted from the shipped source, together with vis_optalling() and the page's own $vnr, and run on an
 * in-memory sqlite database that returns every value as a string, as pg_fetch_array() does.
 */
final class StockCountRecountTest extends TestCase
{
    private const OPTALLING = __DIR__ . '/../../../lager/optalling.php';

    /** The testlab's items A, B and C (varenr, kostpris), and an item with variants. */
    private const ITEMS = [
        13 => ['5744004902131', 'CaMaRose Snefnug - 7312 Mellemgrå', 30.4],
        18 => ['5744004902193', 'CaMaRose Snefnug - 7325 Sort', 30.4],
        102 => ['cmr2028', 'CaMaRose Økologisk Sommeruld - 2028 Mint Lyspetrol', 16.8],
        50 => ['VAR-50', 'Item with variants', 10.0],
    ];

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is required for the isolated stock count database.');
        }
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
        $db->exec("CREATE TABLE regulering (id INTEGER PRIMARY KEY, vare_id INTEGER, variant_id INTEGER, lager INTEGER,
            beholdning TEXT, optalt TEXT, tidspkt TEXT, bogfort TEXT)");
        $db->exec("CREATE TABLE varer (id INTEGER PRIMARY KEY, varenr TEXT, beskrivelse TEXT, kostpris TEXT,
            gruppe INTEGER, lukket TEXT)");
        $db->exec("CREATE TABLE lagerstatus (id INTEGER PRIMARY KEY AUTOINCREMENT, lager INTEGER, vare_id INTEGER,
            variant_id INTEGER, beholdning TEXT)");
        $db->exec("CREATE TABLE variant_varer (id INTEGER PRIMARY KEY, vare_id INTEGER, variant_stregkode TEXT)");
        $db->exec("CREATE TABLE grupper (id INTEGER PRIMARY KEY AUTOINCREMENT, art TEXT, kodenr TEXT, box8 TEXT,
            fiscal_year TEXT)");
        $db->exec("INSERT INTO grupper (art,kodenr,box8,fiscal_year) VALUES ('VG','2','on','1')");
        $db->exec("INSERT INTO variant_varer VALUES (7,50,'VAR-50-7'),(70,50,'VAR-50-70'),(8,50,'VAR-50-8')");
        $db->exec("INSERT INTO lagerstatus (lager,vare_id,variant_id,beholdning) VALUES (1,13,0,'20.000'),(1,18,0,'8.000'),
            (1,102,0,'15.000'),(1,50,7,'4.000'),(1,50,70,'4.000'),(1,50,8,'5.000')");
        foreach (self::ITEMS as $id => [$varenr, $beskrivelse, $kostpris]) {
            self::addItem($db, $id, $varenr, $beskrivelse, $kostpris);
        }
        $GLOBALS['stockCountDb'] = $db;
        $GLOBALS['stockCountSql'] = [];
        $GLOBALS['bgcolor'] = '#eeeeee';
        $GLOBALS['bgcolor2'] = '#ffffff';
        $GLOBALS['regnaar'] = 1;
        $GLOBALS['dato'] = '31-12-2025';
        $GLOBALS['sprog_id'] = 1;
        self::lift();
    }

    private static function addItem(PDO $db, int $id, ?string $varenr, string $beskrivelse, float $kostpris): void
    {
        $insert = $db->prepare("INSERT INTO varer (id,varenr,beskrivelse,kostpris,gruppe,lukket) VALUES (?,?,?,?,2,'')");
        $insert->execute([$id, $varenr, $beskrivelse, number_format($kostpris, 3, '.', '')]);
    }

    private static function renumber(int $id, ?string $varenr): void
    {
        $GLOBALS['stockCountDb']->prepare("UPDATE varer SET varenr = ? WHERE id = ?")->execute([$varenr, $id]);
    }

    /**
     * Evaluates vis_optalling(), the page's "$vnr = ...;" as page_vnr($varenr), and bogfor()'s
     * grouping loop and recount list, from "$x = -1;" up to the accept link, as
     * recount_check($lager, $godkend_regdif).
     */
    private static function lift(): void
    {
        static $lifted = false;
        if ($lifted) {
            return;
        }
        $src = file_get_contents(self::OPTALLING);
        self::assertNotFalse($src, 'could not read lager/optalling.php');

        $from = strpos($src, 'function vis_optalling(');
        $to = strpos($src, '} # vis_optalling', (int)$from);
        self::assertNotFalse($from, 'vis_optalling() is no longer in lager/optalling.php');
        self::assertNotFalse($to, 'vis_optalling() no longer ends with "} # vis_optalling"');
        $renderer = substr($src, $from, $to + 1 - $from);

        self::assertSame(1, preg_match('/^\$vnr = [^\n]*;$/m', $src, $page), 'the page no longer sets $vnr on a line of its own');

        $from = strpos($src, '$x = -1;');
        self::assertNotFalse($from, 'bogfor() no longer starts its grouping with $x = -1;');
        $recount = strpos($src, 'if ($y) {', $from);
        self::assertNotFalse($recount, 'bogfor() no longer tests $y for a recount');
        $to = strpos($src, 'print "<tr><td colspan=8>Klik <a href=optalling.php?nulstil_ej_optalt=', $recount);
        self::assertNotFalse($to, 'the recount list no longer ends with the accept link');
        $code = substr($src, $from, $to - $from);

        eval('namespace ' . __NAMESPACE__ . '; ' . $renderer
            . "\nfunction page_vnr(\$varenr) { " . $page[0] . " return \$vnr; }"
            . "\nfunction recount_check(\$lager, \$godkend_regdif) { \$y = 0; "
            . $code . "\n }\n return array('y' => \$y, 'reg_id' => \$reg_id ?? array(), 'reg_diff' => \$reg_diff,"
            . " 'reg_variant_id' => \$reg_variant_id, 'reguleres' => \$reguleres); }");
        $lifted = true;
    }

    /** @param list<array{int, int, int, float, float}> $rows id, vare_id, variant_id, beholdning, optalt */
    private static function counted(array $rows): void
    {
        $insert = $GLOBALS['stockCountDb']->prepare("INSERT INTO regulering VALUES (?,?,?,1,?,?,'202512312359','0')");
        foreach ($rows as [$id, $item, $variant, $stock, $counted]) {
            $insert->execute([$id, $item, $variant, number_format($stock, 3, '.', ''), number_format($counted, 3, '.', '')]);
        }
    }

    /**
     * @param list<array{int, int, int, float, float}> $rows
     * @return array{state: array{y: int, reg_id: array<int, string>, reg_diff: array<int|string, int>,
     *     reg_variant_id: array<int, ?string>, reguleres: array<int|string, float>}, output: string}
     */
    private function check(array $rows, $godkend_regdif = ''): array
    {
        self::counted($rows);
        ob_start();
        try {
            $state = recount_check('1', $godkend_regdif);
        } finally {
            $output = (string)ob_get_clean();
        }
        return ['state' => $state, 'output' => $output];
    }

    /** @return array{count: int, output: string} */
    private function render($vnr, int $gentael = 0): array
    {
        ob_start();
        try {
            $count = vis_optalling('1', $vnr, $gentael);
        } finally {
            $output = (string)ob_get_clean();
        }
        return ['count' => $count, 'output' => $output];
    }

    /** The cells vis_optalling() prints an item's number in. */
    private static function itemCells(string $output): array
    {
        preg_match_all('#<td><b>([^<]*)</b></td><td><b>#', $output, $matches);
        return $matches[1];
    }

    /** The testlab's rows: A counted 21 at stock 21, one A sold, A counted 1 more at stock 20; B and C once. */
    private static function afterASaleAndASecondCount(): array
    {
        return [[2855, 13, 0, 21, 21], [2856, 18, 0, 8, 7], [2857, 102, 0, 15, 17], [2858, 13, 0, 20, 1]];
    }

    /** @param array{state: array<string, mixed>, output: string} $result */
    private static function assertRecountOfOnly(string $varenr, array $result): void
    {
        self::assertGreaterThan(0, $result['state']['y'], 'the stock changed between the item\'s two counts, so Bogfør must ask for a recount');
        self::assertStringContainsString('skal optælles igen', $result['output']);
        self::assertSame([$varenr], self::itemCells($result['output']), 'the recount list shows that item and nothing else');
        self::assertStringNotContainsString('Optalt i alt', $result['output'], 'a recount list has no totals of the whole count');
    }

    public function testASaleBetweenTwoCountsOfAnItemDemandsARecountOfThatItem(): void
    {
        $result = $this->check(self::afterASaleAndASecondCount());
        $state = $result['state'];

        self::assertSame(['13', '18', '102'], array_values($state['reg_id']), 'one entry per item, not one per count');
        self::assertSame(1, $state['reg_diff'][13]);
        self::assertSame(0, $state['reg_diff'][18]);
        self::assertSame(0, $state['reg_diff'][102]);
        self::assertRecountOfOnly('5744004902131', $result);
        self::assertContains("select varenr from varer where id = 13", $GLOBALS['stockCountSql']);
        foreach ($GLOBALS['stockCountSql'] as $sql) {
            self::assertStringNotContainsString("id=''", $sql, 'an item id is never empty in SQL');
        }
    }

    public function testAnItemNumberWithAQuoteIsLookedUpAsText(): void
    {
        self::renumber(13, "O'RING");
        $result = $this->check(self::afterASaleAndASecondCount());

        self::assertRecountOfOnly("O'RING", $result);
        self::assertSame(1, count(preg_grep("/varer\.varenr='O''RING'/", $GLOBALS['stockCountSql'])));
    }

    public function testItemNumberZeroIsAnItemNumberAndNotEveryItem(): void
    {
        self::renumber(13, '0');
        $result = $this->check(self::afterASaleAndASecondCount());

        self::assertRecountOfOnly('0', $result);
    }

    public function testAnItemWithoutANumberDoesNotListEveryCount(): void
    {
        self::renumber(13, null);
        $result = $this->check(self::afterASaleAndASecondCount());

        self::assertGreaterThan(0, $result['state']['y']);
        self::assertSame([], self::itemCells($result['output']), 'B and C are not part of the recount');
    }

    public function testARecountForAnItemThatNoLongerExistsListsNothingElse(): void
    {
        $GLOBALS['stockCountDb']->exec('DELETE FROM varer WHERE id = 13');
        $result = $this->check(self::afterASaleAndASecondCount());

        self::assertGreaterThan(0, $result['state']['y']);
        self::assertSame([], self::itemCells($result['output']), 'an empty item number would list every count');
    }

    public function testCountsOfAnItemOnSeveralShelvesAddUp(): void
    {
        $result = $this->check([[1, 13, 0, 21, 15], [2, 13, 0, 21, 6]]);
        $state = $result['state'];

        self::assertSame(0, $state['y'], 'the stock did not change between the counts');
        self::assertSame(['13'], array_values($state['reg_id']));
        self::assertEqualsWithDelta(0.0, $state['reguleres'][13], .000001, '15 + 6 counted against 21 in stock needs no adjustment');
        self::assertSame('', $result['output']);
    }

    public function testAcceptingTheLatestStockAddsTheCountsUpAgainstIt(): void
    {
        $state = $this->check(self::afterASaleAndASecondCount(), '1')['state'];

        self::assertSame(0, $state['y'], 'godkend_regdif accepts the latest stock instead of asking again');
        self::assertEqualsWithDelta(2.0, $state['reguleres'][13], .000001, '21 + 1 counted against the latest stock 20');
        self::assertEqualsWithDelta(-1.0, $state['reguleres'][18], .000001);
        self::assertEqualsWithDelta(2.0, $state['reguleres'][102], .000001);
    }

    public function testAnItemCountedOnceIsAdjustedByItsCountAsBefore(): void
    {
        $state = $this->check([[2856, 18, 0, 8, 7], [2857, 102, 0, 15, 17]])['state'];

        self::assertSame(0, $state['y']);
        self::assertSame([null, null], array_values($state['reg_variant_id']));
        self::assertEqualsWithDelta(-1.0, $state['reguleres'][18], .000001);
        self::assertEqualsWithDelta(2.0, $state['reguleres'][102], .000001);
    }

    public function testVariantCountsAreGroupedPerVariant(): void
    {
        // Variants 7 and 70 of item 50: "50.7" and "50.70" are equal numbers, so they must not be compared as such.
        $state = $this->check([[1, 50, 7, 5, 3], [2, 50, 7, 5, 2], [3, 50, 70, 4, 4]])['state'];

        self::assertSame(0, $state['y']);
        self::assertSame(['50.7', '50.70'], array_values($state['reg_id']));
        self::assertSame(['50.7', '50.70'], array_values($state['reg_variant_id']));
        self::assertEqualsWithDelta(0.0, $state['reguleres']['50.7'], .000001, '3 + 2 counted against 5 in stock');
        self::assertEqualsWithDelta(0.0, $state['reguleres']['50.70'], .000001);
    }

    public function testAVariantWhoseStockChangedListsItsItemOnce(): void
    {
        $result = $this->check([[1, 50, 7, 5, 3], [2, 50, 7, 4, 1], [3, 50, 8, 6, 6], [4, 50, 8, 5, 1], [5, 18, 0, 8, 7]]);

        self::assertGreaterThan(0, $result['state']['y']);
        self::assertSame(1, $result['state']['reg_diff']['50.7']);
        self::assertSame(1, $result['state']['reg_diff']['50.8']);
        self::assertSame(1, count(preg_grep("/varer\.varenr='VAR-50'/", $GLOBALS['stockCountSql'])), 'the item is listed by its number, once');
        self::assertStringContainsString('VAR-50-7', $result['output']);
        self::assertStringContainsString('VAR-50-8', $result['output']);
        self::assertStringNotContainsString('5744004902193', $result['output'], 'B did not change');
    }

    public function testThePageListsEveryCountWhenNoItemIsLookedUp(): void
    {
        self::renumber(13, '0');
        self::counted(self::afterASaleAndASecondCount());

        // The page looks up no item for an empty or "0" input (if ($varenr)), so it lists every count then.
        foreach (['', null, '0'] as $varenr) {
            $page = $this->render(page_vnr($varenr));
            self::assertSame(4, $page['count'], 'varenr ' . var_export($varenr, true));
            self::assertSame(['0', '5744004902193', 'cmr2028'], self::itemCells($page['output']));
            self::assertStringContainsString('Optalt i alt 4', $page['output']);
        }
        self::assertSame('cmr2028', page_vnr('cmr2028'));
    }

    public function testTheListOfOneItemShowsThatItemAlone(): void
    {
        self::renumber(13, '0');
        self::counted(self::afterASaleAndASecondCount());

        $item = $this->render('0');
        self::assertSame(2, $item['count'], 'both counts of item 0');
        self::assertSame(['0'], self::itemCells($item['output']));
        self::assertStringNotContainsString('Optalt i alt', $item['output']);

        $none = $this->render("O'RING");
        self::assertSame(0, $none['count']);
        self::assertSame([], self::itemCells($none['output']));
    }
}
