<?php
// 20261125 CDX/NTR Cover missing price headers, bounded lookups and serialized external item creation (PR #1220).

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/external_price_list.php';

final class ExternalPriceListTest extends TestCase
{
    protected function setUp(): void
    {
        \ExternalPriceListFixture\boot();
    }

    public function testMissingPriceHeadersCannotSupplyZeroPrices(): void
    {
        foreach (['varenr', 'varenr;kostpris', 'varenr;salgspris'] as $header) {
            \ExternalPriceListFixture\State::$csv = $header . "\nA100;12\n";
            self::assertNull(\ExternalPriceListFixture\externalPriceListSearch(self::priceList(), 'A100', ''), $header);
            self::assertNull(\ExternalPriceListFixture\externalPriceListFind(self::priceList(), 'A100'), $header);
        }
    }

    public function testExplicitZeroAndExactPricesRemainUsable(): void
    {
        \ExternalPriceListFixture\State::$csv = "varenr;kostpris;salgspris\nA100;0;0\nA101;1250.500;0.005\nA102;-0.005;999999999999.999\n";
        $rows = \ExternalPriceListFixture\externalPriceListSearch(self::priceList(), 'A', '');
        self::assertSame(['0.000', '0.005', '999999999999.999'], array_column($rows, 'salgspris'));
        self::assertSame(['0.000', '1250.500', '-0.005'], array_column($rows, 'kostpris'));
    }

    public function testExistingMatchesAreCheckedInBatchesBeforeCountingTheLimit(): void
    {
        $db = \ExternalPriceListFixture\State::$db;
        $db->exec("WITH RECURSIVE n(x) AS (SELECT 1 UNION ALL SELECT x+1 FROM n WHERE x<1001) INSERT INTO varer (varenr) SELECT 'A' || x FROM n");
        $lines = ["varenr;kostpris;salgspris"];
        for ($i = 1; $i <= 1060; $i++) {
            $lines[] = "A$i;1;2";
        }
        \ExternalPriceListFixture\State::$csv = implode("\n", $lines);
        $rows = \ExternalPriceListFixture\externalPriceListSearch(self::priceList(), 'A', '');
        self::assertCount(50, $rows);
        self::assertSame('A1002', $rows[0]['varenr']);
        self::assertSame('A1051', $rows[49]['varenr']);
        self::assertLessThanOrEqual(5, count(\ExternalPriceListFixture\State::$queries));
    }

    public function testBatchQueryEscapesItemNumbersAndKeepsFileOrder(): void
    {
        \ExternalPriceListFixture\State::$db->exec("INSERT INTO varer (varenr) VALUES ('O''Hara')");
        \ExternalPriceListFixture\State::$csv = "varenr;kostpris;salgspris\nO'Hara;1;2\nO'New;3;4\n";
        $rows = \ExternalPriceListFixture\externalPriceListSearch(self::priceList(), "O'", '');
        self::assertSame(["O'New"], array_column($rows, 'varenr'));
    }

    public function testCreationRechecksAfterTakingTheLock(): void
    {
        \ExternalPriceListFixture\State::$onLock = static function (): void {
            \ExternalPriceListFixture\State::$db->exec("INSERT INTO varer (id, varenr) VALUES (42, 'A100')");
        };
        self::assertSame(42, \ExternalPriceListFixture\externalPriceListCreateItem(self::row(), self::priceList()));
        self::assertSame(1, (int) \ExternalPriceListFixture\State::$db->query('SELECT count(*) FROM varer')->fetchColumn());
        self::assertFalse(\ExternalPriceListFixture\State::$locked);
    }

    public function testRefusedLockDoesNotCreateAnItem(): void
    {
        \ExternalPriceListFixture\State::$allowLock = false;
        self::assertSame(0, \ExternalPriceListFixture\externalPriceListCreateItem(self::row(), self::priceList()));
        self::assertSame(0, (int) \ExternalPriceListFixture\State::$db->query('SELECT count(*) FROM varer')->fetchColumn());
    }

    public function testInsertRunsUnderLockAndReleasesItOnFailure(): void
    {
        \ExternalPriceListFixture\State::$failInsert = true;
        try {
            \ExternalPriceListFixture\externalPriceListCreateItem(self::row(), self::priceList());
            self::fail('Expected insertion failure');
        } catch (RuntimeException $error) {
            self::assertSame('Synthetic insert failure', $error->getMessage());
        }
        self::assertTrue(\ExternalPriceListFixture\State::$insertWasLocked);
        self::assertFalse(\ExternalPriceListFixture\State::$locked);
    }

    public function testCreationPreservesExactPricesAndReusesTheItem(): void
    {
        $first = \ExternalPriceListFixture\externalPriceListCreateItem(self::row(), self::priceList());
        self::assertGreaterThan(0, $first);
        self::assertSame($first, \ExternalPriceListFixture\externalPriceListCreateItem(self::row(), self::priceList()));
        self::assertSame(['1250.500', '0.005'], \ExternalPriceListFixture\State::$db->query('SELECT kostpris, salgspris FROM varer')->fetch(PDO::FETCH_NUM));
        self::assertTrue(\ExternalPriceListFixture\State::$insertWasLocked);
        self::assertFalse(\ExternalPriceListFixture\State::$locked);
    }

    /** @return array{url: string, delimiter: string, encoding: string, group: int, groupName: string} */
    private static function priceList(): array
    {
        return ['url' => 'https://example.invalid/prices.csv', 'delimiter' => ';', 'encoding' => 'utf-8', 'group' => 10, 'groupName' => ''];
    }

    /** @return array{varenr: string, stregkode: string, trademark: string, beskrivelse: string, kostpris: string, salgspris: string, enhed: string} */
    private static function row(): array
    {
        return ['varenr' => 'A100', 'stregkode' => '', 'trademark' => '', 'beskrivelse' => 'Pump', 'kostpris' => '1250.500', 'salgspris' => '0.005', 'enhed' => 'stk'];
    }
}
