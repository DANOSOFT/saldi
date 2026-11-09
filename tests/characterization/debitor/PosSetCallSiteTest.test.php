<?php
// 20261023 CL/LH MB-95 Pin what opret_saet() does with the lager it is given, and run every POS call
// of it, as shipped, against the real function.
//
// The "på beløb" branch of pos_ordre.php called opret_saet() with six of its seven arguments, so on
// PHP 8 adding a set there died with an ArgumentCountError, and the lager sat in $incl_moms. It also
// handed over the raw Danish-format price. The calls are read from the shipped files and evaluated
// here, so the test runs what the POS runs; opret_saet() itself is lifted from includes/ordrefunc.php.

namespace Saldi\Tests\PosCallSites;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PosSetCallSiteTest extends TestCase
{
    private const ORDER = 7;
    private const SET = 10;
    private const LAGER = 3;

    /** opret_ordrelinje() argument positions (includes/ordrefunc.php). */
    private const ARG_PRICE = 5;
    private const ARG_INCL_MOMS = 12;
    private const ARG_LAGER = 19;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is required for the isolated POS fixtures.');
        }
        require_once __DIR__ . '/support/pos_call_sites_database.php';

        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $GLOBALS['posCallSitesDb'] = $db;
        $GLOBALS['posCallSitesSql'] = [];
        $GLOBALS['posCallSitesLines'] = [];
        $GLOBALS['regnaar'] = 2026;

        $db->exec("CREATE TABLE grupper (id INTEGER PRIMARY KEY AUTOINCREMENT, art TEXT, kodenr TEXT,
            box1 TEXT, box2 TEXT, box3 TEXT, box7 TEXT, fiscal_year TEXT)");
        $db->exec("CREATE TABLE varer (id INTEGER PRIMARY KEY, varenr TEXT, beskrivelse TEXT,
            salgspris NUMERIC, gruppe TEXT, samlevare TEXT)");
        $db->exec("CREATE TABLE styklister (id INTEGER PRIMARY KEY AUTOINCREMENT, vare_id INTEGER,
            indgaar_i INTEGER, antal NUMERIC, posnr INTEGER)");
        $db->exec("CREATE TABLE ordrer (id INTEGER PRIMARY KEY, momssats NUMERIC)");
        $db->exec("CREATE TABLE ordrelinjer (id INTEGER PRIMARY KEY AUTOINCREMENT, ordre_id INTEGER,
            vare_id INTEGER, varenr TEXT, saet INTEGER, samlevare TEXT, lev_varenr TEXT, kostpris NUMERIC,
            rabat NUMERIC)");

        // A set of two components, all in an ordinary (VAT-liable) item group.
        $db->exec("INSERT INTO grupper (art,kodenr,box7) VALUES ('VG','1','')");
        $db->exec("INSERT INTO varer VALUES (10,'SAET','Havesæt',120,'1','on')");
        $db->exec("INSERT INTO varer VALUES (11,'BORD','Bord',100,'1','')");
        $db->exec("INSERT INTO varer VALUES (12,'STOL','Stol',50,'1','')");
        $db->exec("INSERT INTO styklister (vare_id,indgaar_i,antal,posnr) VALUES (11,10,1,1)");
        $db->exec("INSERT INTO styklister (vare_id,indgaar_i,antal,posnr) VALUES (12,10,1,2)");
        $db->exec("INSERT INTO ordrer VALUES (7,25)");

        liftSetFunctions();
    }

    /** The saetpris opret_saet() recorded on the set's master line ("<saetpris>|<rabat>"). */
    private static function recordedSetPrice(): float
    {
        foreach ($GLOBALS['posCallSitesSql'] as $sql) {
            if (preg_match("/lev_varenr='([^|']*)\|/", $sql, $m)) {
                return (float)$m[1];
            }
        }
        self::fail('opret_saet() did not record the set price on the master line');
    }

    // ------------------------------------------------ opret_saet() as it stands

    public function testEveryLineOfTheSetIsDrawnFromTheLagerItWasGiven(): void
    {
        opret_saet(self::ORDER, self::SET, 150, 25, 1, 'on', self::LAGER);

        $lines = $GLOBALS['posCallSitesLines'];
        self::assertCount(3, $lines, 'two component lines and the set line');
        foreach ($lines as $line) {
            self::assertSame(self::LAGER, $line[self::ARG_LAGER]);
            self::assertSame('on', $line[self::ARG_INCL_MOMS]);
        }
    }

    public function testThePricesAreVatInclusiveAndDiscountedDownToTheSetPrice(): void
    {
        opret_saet(self::ORDER, self::SET, 150, 25, 1, 'on', self::LAGER);

        [$bord, $stol, $set] = $GLOBALS['posCallSitesLines'];
        self::assertEqualsWithDelta(125.0, $bord[self::ARG_PRICE], 0.001, '100 + 25 % VAT');
        self::assertEqualsWithDelta(62.5, $stol[self::ARG_PRICE], 0.001, '50 + 25 % VAT');
        self::assertEqualsWithDelta(20.0, $bord[6], 0.001, '187.50 down to 150 is 20 % off each line');
        self::assertEqualsWithDelta(0.0, $set[self::ARG_PRICE], 0.001, 'nothing left over for the set line');
        self::assertEqualsWithDelta(150.0, self::recordedSetPrice(), 0.001);
    }

    public function testSixArgumentsAreAFatalErrorOnPhp8(): void
    {
        // What pos_ordre.php's "på beløb" branch used to pass: the lager in the $incl_moms slot.
        $this->expectException(\ArgumentCountError::class);
        opret_saet(self::ORDER, self::SET, 150, 25, 1, self::LAGER);
    }

    // ---------------------------------------- the POS call sites, as shipped

    /** @return array<string, array{string, string}> */
    public static function posCalls(): array
    {
        require_once __DIR__ . '/support/pos_call_sites_database.php';
        $cases = [];
        foreach (['debitor/pos_ordre.php', 'debitor/pos_ordre_includes/itemId.php'] as $file) {
            foreach (callsOf(shipped($file), 'opret_saet') as $n => $call) {
                $cases["$file call " . ($n + 1)] = [$file, $call];
            }
        }
        return $cases;
    }

    /**
     * Runs one call exactly as written, with the variables the POS has in scope at that point.
     *
     * @param string $pris_ny The price as the cashier typed it.
     */
    private static function runCall(string $call, string $pris_ny): void
    {
        $scope = [
            'id' => self::ORDER,
            'r' => ['id' => self::SET, 'samlevare' => 'on'],
            'vare_id' => self::SET,
            'pris_ny' => $pris_ny,
            'momssats' => '25',
            'antal_ny' => 1,
            'incl_moms' => 'on',
            'lager_ny' => self::LAGER,
        ];
        $run = eval('namespace ' . __NAMESPACE__ . '; return static function (array $scope): void {'
            . ' extract($scope); ' . $call . '; };');
        $run($scope);
    }

    #[DataProvider('posCalls')]
    public function testEveryPosCallHandsOverAllSevenArgumentsWithTheLagerLast(string $file, string $call): void
    {
        self::runCall($call, '150');

        $lines = $GLOBALS['posCallSitesLines'];
        self::assertCount(3, $lines, "$call did not create the set");
        foreach ($lines as $line) {
            self::assertSame(self::LAGER, $line[self::ARG_LAGER], "$call: the set is not drawn from the chosen lager");
            self::assertSame('on', $line[self::ARG_INCL_MOMS], "$call: the POS price includes VAT");
        }
    }

    /** @return array<string, array{string, string}> */
    public static function paymentOnAmountBranches(): array
    {
        return [
            'pos_ordre.php' => ['debitor/pos_ordre.php', 'if (strpos($betaling, \'på beløb\')) {'],
            'itemId.php' => ['debitor/pos_ordre_includes/itemId.php', 'if (strpos($betaling,\'på beløb\')) {'],
        ];
    }

    /**
     * The "på beløb" branch only runs with a non-zero typed amount, so it must parse it like the
     * opret_ordrelinje() call beside it does.
     */
    #[DataProvider('paymentOnAmountBranches')]
    public function testThePaymentOnAmountBranchParsesTheDanishFormatPrice(string $file, string $branchStart): void
    {
        $branch = liftBlock(shipped($file), $branchStart);
        $calls = callsOf('<?php ' . $branch, 'opret_saet');
        self::assertCount(1, $calls, "expected one opret_saet() call in the \"på beløb\" branch of $file");

        self::runCall($calls[0], '1.000,00');

        self::assertEqualsWithDelta(1000.0, self::recordedSetPrice(), 0.001,
            'a raw "1.000,00" multiplies out to 1, so the set would be sold for one krone');
    }
}
