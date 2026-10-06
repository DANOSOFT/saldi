<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../lager/lagerstatusValue.php';

final class LagerstatusPurchaseValueTest extends TestCase
{
    /**
     * @return array{id: int, linje_id: int, antal: string, pris: string, kred_linje_id: int}
     */
    private static function batch(int $id, int $line, float $quantity, float $price, int $original = 0): array
    {
        return ['id' => $id, 'linje_id' => $line, 'antal' => (string)$quantity,
            'pris' => (string)$price, 'kred_linje_id' => $original];
    }

    /**
     * @return array<string, array{array<int, array>, float, float}>
     */
    public static function valuationCases(): array
    {
        $old = self::batch(1, 101, 10, 1000);
        $replacement = self::batch(2, 102, 10, 20);
        $credit = self::batch(3, 103, -10, 1000, 101);
        return [
            'ordinary newest receipts' => [[$replacement, $old], 5, 100],
            'credit after replacement, five sold' => [[$credit, $replacement, $old], 5, 100],
            'replacement after credit, five sold' => [[$replacement, $credit, $old], 5, 100],
            'credit after replacement, no sales' => [[$credit, $replacement, $old], 10, 200],
            'split original deliveries' => [[$credit, $replacement,
                self::batch(8, 101, 6, 1000), self::batch(7, 101, 4, 1000)], 5, 100],
            'partial credit and partial sales' => [[self::batch(3, 103, -4, 1000, 101),
                self::batch(2, 102, 4, 20), $old], 8, 4080],
            'several credits on original line' => [[self::batch(5, 105, -3, 1000, 101),
                self::batch(4, 104, -2, 1000, 101), self::batch(2, 102, 5, 20), $old], 7, 2100],
            'copied positive line is a receipt' => [[$credit, self::batch(2, 102, 10, 20, 101), $old], 5, 100],
            'fractional split receipts cancel exactly' => [[self::batch(5, 103, -.3, 1000, 101),
                self::batch(4, 102, .3, 20), self::batch(2, 101, .2, 1000),
                self::batch(1, 101, .1, 1000)], .15, 3],
            'positive fractions without credits' => [[self::batch(1, 101, .25, 20)], .125, 2.5],
            'zero stock' => [[$credit, $replacement, $old], 0, 0],
            'negative stock' => [[$credit, $replacement, $old], -5, 0],
            'no purchase history' => [[], 5, 0],
            // These preserve the existing fallback, including its known limitations; no
            // original purchase can be inferred safely from price or position alone.
            'unlinked credit retains signed fallback' => [[self::batch(3, 103, -10, 1000), $replacement, $old], 5, -4800],
            'missing original retains signed fallback' => [[self::batch(3, 103, -10, 1000, 999), $replacement, $old], 5, -4800],
            'excess credit retains unmatched remainder' => [[self::batch(3, 103, -12, 1000, 101),
                self::batch(2, 102, 20, 20), $old], 13, -1700],
        ];
    }

    #[DataProvider('valuationCases')]
    public function testStockValuation(array $batches, float $stock, float $expected): void
    {
        $original = $batches;
        self::assertEqualsWithDelta($expected, lagerstatusPurchaseValue($batches, $stock), .000001);
        self::assertSame($original, $batches, 'Valuation must not mutate the caller\'s batch history.');
    }

    public function testQueryKeepsWarehouseDateAndItemScope(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for the isolated query fixture.');
        }
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE batch_kob (id INTEGER, linje_id INTEGER, vare_id INTEGER, antal NUMERIC,
            pris NUMERIC, lager INTEGER, kobsdate TEXT, fakturadate TEXT)');
        $db->exec('CREATE TABLE ordrelinjer (id INTEGER PRIMARY KEY, kred_linje_id INTEGER)');
        $db->exec('INSERT INTO ordrelinjer VALUES (101,0),(102,101),(103,101),(104,0),(105,0),(106,0)');
        $db->exec("INSERT INTO batch_kob VALUES
            (1,101,1,10,1000,1,'2026-09-01','2026-09-01'),
            (2,102,1,10,20,1,'2026-09-02','2026-09-04'),
            (3,103,1,-10,1000,1,'2026-09-03','2026-09-03'),
            (4,104,1,10,30,2,'2026-09-02','2026-09-02'),
            (5,105,2,999,999,1,'2026-09-04','2026-09-04'),
            (6,106,1,1,40,1,'2026-09-09','2026-09-09')");

        $read = static function ($warehouse, $type, $cutoff) use ($db) {
            return $db->query(lagerstatusPurchaseValueSql(1, $warehouse, $type, $cutoff))->fetchAll(PDO::FETCH_ASSOC);
        };
        $batches = $read(1, 'levdate', '2026-09-03');
        self::assertSame([3, 2, 1], array_column($batches, 'id'));
        self::assertEqualsWithDelta(100, lagerstatusPurchaseValue($batches, 5), .000001);

        // The credit is outside this delivery-date cutoff, so the old receipt is still valid.
        $batches = $read(1, 'levdate', '2026-09-02');
        self::assertSame([2, 1], array_column($batches, 'id'));
        self::assertEqualsWithDelta(5200, lagerstatusPurchaseValue($batches, 15), .000001);

        // Invoice-date selection excludes the uninvoiced replacement at this cutoff.
        $batches = $read(1, 'fakturadate', '2026-09-03');
        self::assertSame([3, 1], array_column($batches, 'id'));
        self::assertSame(0.0, lagerstatusPurchaseValue($batches, 0));

        self::assertSame([4], array_column($read(2, 'levdate', '2026-09-03'), 'id'));
        $batches = $read(0, 'levdate', '2026-09-03');
        self::assertSame([3, 4, 2, 1], array_column($batches, 'id'));
        self::assertEqualsWithDelta(400, lagerstatusPurchaseValue($batches, 15), .000001);

        // Null represents the report's current date: preserve its existing future-date inclusion.
        self::assertSame([6, 3, 2, 1], array_column($read(1, 'levdate', null), 'id'));
    }

    public function testQueryCastsIdentifiersAndRejectsAnUnsafeCutoff(): void
    {
        self::assertStringContainsString('bk.vare_id=1 and bk.lager=2',
            lagerstatusPurchaseValueSql('1 OR 1=1', '2 OR 1=1', 'levdate', null));
        $this->expectException(InvalidArgumentException::class);
        lagerstatusPurchaseValueSql(1, 0, 'levdate', "2026-09-03' OR 1=1");
    }
}
