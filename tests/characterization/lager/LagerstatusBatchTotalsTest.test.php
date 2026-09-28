<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// 20260921 CDX/MJ SST-801 Guards the batch totals in lager/lagerstatus.php against going back to a
//                  query per item. Measured on a dev tenant loaded to Dyre-Loppen's row counts
//                  (41.381 varer, 67.643 batch_kob, 56.938 batch_salg): the per-item form took
//                  256,78 s and 82.762 queries for one page load, the grouped form 0,10 s and two.
//                  The numbers are identical - verified across every filter combination - so the
//                  only thing worth pinning here is that the shape does not regress.
final class LagerstatusBatchTotalsTest extends TestCase
{
    private const FILE = __DIR__ . '/../../../lager/lagerstatus.php';

    /**
     * The file with comments removed, so the dead /* *\/ block further down - which still contains
     * the old per-item selects - is not mistaken for live code.
     *
     * @return string
     */
    private static function liveCode(): string
    {
        $code = '';
        foreach (token_get_all(file_get_contents(self::FILE)) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }
        return $code;
    }

    /** The totals must be fetched grouped, once, rather than per item. */
    public function testBatchTotalsAreFetchedGrouped(): void
    {
        $code = self::liveCode();
        self::assertStringContainsString(
            'select vare_id, sum(antal) as antal from batch_kob',
            $code,
            'batch_kob totals should be one grouped query'
        );
        self::assertStringContainsString(
            'select vare_id, sum(antal) as antal from batch_salg',
            $code,
            'batch_salg totals should be one grouped query'
        );
        self::assertStringContainsString('group by vare_id', $code);
    }

    /** The per-item form is what made the page time out; it must not return. */
    public function testNoPerItemBatchSumSurvives(): void
    {
        $code = self::liveCode();
        foreach (['batch_kob', 'batch_salg'] as $table) {
            self::assertStringNotContainsString(
                "select sum(antal) as antal from $table where vare_id=\$vare_id[\$x]",
                $code,
                "a per-item sum over $table is back - that is 41.000 round trips at Dyre-Loppen's volume"
            );
        }
    }

    /**
     * $dateType arrives from the request and is used as a column name. It must be whitelisted, not
     * interpolated, which is also one of the ticket's acceptance criteria.
     */
    public function testTheDateColumnIsWhitelisted(): void
    {
        $code = self::liveCode();
        self::assertStringNotContainsString('$kobDt  = $dateType', $code);
        self::assertMatchesRegularExpression(
            "/\\\$kobDt\s*=\s*\(\\\$dateType\s*==\s*'fakturadate'\)\s*\?\s*'fakturadate'\s*:\s*'kobsdate'/",
            $code,
            'the purchase date column should come from a whitelist'
        );
        self::assertMatchesRegularExpression(
            "/\\\$salgDt\s*=\s*\(\\\$dateType\s*==\s*'fakturadate'\)\s*\?\s*'fakturadate'\s*:\s*'salgsdate'/",
            $code,
            'the sales date column should come from a whitelist'
        );
    }

    /** The warehouse filter reaches SQL, so it must be cast. */
    public function testTheWarehouseFilterIsIntCast(): void
    {
        self::assertStringContainsString("(int) \$lagervalg", self::liveCode());
    }

    /**
     * lager/varer.php had the same shape: one lagerstatus sum per item plus one per warehouse per
     * item, run for every matching row because searching turns pagination off. Measured at 35,12 s
     * for 2.000 items, about 727 s for Dyre-Loppen's list; built once it is 0,48 s and two queries,
     * with identical values including the lok1 strings.
     */
    public function testVarerBuildsTheLagerstatusLookupsOnce(): void
    {
        $code = '';
        foreach (token_get_all(file_get_contents(__DIR__ . '/../../../lager/varer.php')) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        self::assertStringContainsString(
            'select vare_id, sum(beholdning) as lagersum from lagerstatus group by vare_id',
            $code,
            'the lagerstatus sums should be one grouped query'
        );
        self::assertStringContainsString(
            'select vare_id, lager, id, lok1, beholdning from lagerstatus',
            $code,
            'the per-warehouse rows should be fetched in one query'
        );
        self::assertStringNotContainsString(
            'from lagerstatus where vare_id',
            $code,
            'a per-item lagerstatus lookup is back - that ran for every matching row, not just the page'
        );
    }
}
