<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../index/dashboardIncludes/dashData.php';

/**
 * Which accounts Oversigt (index/dashboard.php) counts as revenue: the net revenue sum account of the tenant's own
 * chart (dash_chart_revenue_range()), a saved Fra/Til kontonr. unless it is the old dashboard's unchosen 0/2000, an empty
 * Fra or Til filled in from the chart bound by bound (dash_revenue_accounts(), dash_account_input()), and both ends of the
 * range in every query (dash_account_sql()).
 * The charts are the files Saldi ships, read the way a regnskab gets them.
 */
final class dashRevenueRange extends TestCase
{
    /**
     * importfiler/kontoplan.txt as includes/opretfunc.php reads it: kontonr, beskrivelse, kontotype, moms, fra_kto.
     *
     * @return array<int, array{kontonr: string, beskrivelse: string, kontotype: string, fra_kto: string}>
     */
    private static function standardChart(): array
    {
        $rows = array();
        foreach (file(__DIR__ . '/../../importfiler/kontoplan.txt') as $line) {
            $f = explode("\t", trim($line));
            if (count($f) >= 5) {
                $rows[] = array('kontonr' => $f[0], 'beskrivelse' => $f[1], 'kontotype' => $f[2], 'fra_kto' => is_numeric($f[4]) ? $f[4] : '0');
            }
        }
        return $rows;
    }

    /**
     * A chart file with the types written out (importfiler/kontoplan2.txt, kontoplan3.txt), its types as letters.
     *
     * @return array<int, array{kontonr: string, beskrivelse: string, kontotype: string, fra_kto: string}>
     */
    private static function wordChart(string $file, int $fromColumn): array
    {
        $types = array('Tekst' => 'H', 'Overskrift' => 'H', 'Hovedoverskrift' => 'H', 'Sideskift' => 'X', 'Drift' => 'D', 'Status' => 'S', 'Sum' => 'Z', 'Total' => 'Z');
        $rows = array();
        foreach (file(__DIR__ . '/../../importfiler/' . $file) as $line) {
            $f = explode("\t", rtrim($line, "\r\n"));
            if (!isset($f[2]) || !ctype_digit($f[0])) {
                continue;
            }
            $type = trim($f[2], '"');
            $rows[] = array('kontonr' => $f[0], 'beskrivelse' => trim($f[1], '"'), 'kontotype' => $types[$type] ?? '?', 'fra_kto' => trim($f[$fromColumn] ?? ''));
        }
        return $rows;
    }

    private static function row(int $nr, string $name, string $type, int $from = 0): array
    {
        return array('kontonr' => (string) $nr, 'beskrivelse' => $name, 'kontotype' => $type, 'fra_kto' => (string) $from);
    }

    public function testStandardChartCountsNettoomsaetningOnly(): void
    {
        // 1390 Nettoomsætning sums 1010-1390; 1410 (stock), 1610-1740 (Varekøb, Ydelseskøb) and 1830-1990 stay out.
        self::assertSame(array(1010, 1390), dash_chart_revenue_range(self::standardChart()));
    }

    public function testWordChartsFindTheirNetRevenueSum(): void
    {
        // kontoplan2.txt: "Varesalg i alt" (1000-1890) comes first but holds goods only; NETTOOMSÆTNING sums 2-1999.
        self::assertSame(array(2, 1999), dash_chart_revenue_range(self::wordChart('kontoplan2.txt', 5)));
        // kontoplan3.txt (Latin-1): OMSÆTNING I ALT from 10000; OMSÆTNINGSAKTIVER I ALT is in the balance sheet.
        self::assertSame(array(10000, 11797), dash_chart_revenue_range(self::wordChart('kontoplan3.txt', 3)));
    }

    public function testOldSqlChartFindsOmsaetningIAlt(): void
    {
        preg_match_all("/values \\('(\\d+)', '([^']*)', '([A-Z])', '([^']*)'/", (string) file_get_contents(__DIR__ . '/../../includes/kontoplan.php'), $m, PREG_SET_ORDER);
        $rows = array();
        foreach ($m as $r) {
            $rows[] = array('kontonr' => $r[1], 'beskrivelse' => $r[2], 'kontotype' => $r[3], 'fra_kto' => $r[4]);
        }
        self::assertGreaterThan(50, count($rows));
        self::assertSame(array(1000, 10990), dash_chart_revenue_range($rows));
    }

    public function testStandardChartBefore2024(): void
    {
        // The standard chart until 2024: the old default 0-2000 was made for it.
        $chart = array(
            self::row(10, 'RESULTATOPGØRELSE', 'H'), self::row(100, 'OMSÆTNING:', 'H'), self::row(1000, 'Udført arbejde', 'D'),
            self::row(1250, 'Salg af ydelser udenfor EU', 'D'), self::row(1290, 'Salg af varer og ydelser udenfor EU', 'Z', 1250),
            self::row(1900, 'Tab på debitorer', 'D'), self::row(1950, 'OMSÆTNING I ALT', 'Z', 1000),
            self::row(2000, 'VARIABLE OMKOSTNINGER:', 'H'), self::row(2100, 'Varekøb i DK', 'D'), self::row(2850, 'VAREFORBRUG I ALT', 'Z', 2000),
            self::row(55099, 'OMSÆTNINGSAKTIVER:', 'H'), self::row(55100, 'Varelager', 'S'), self::row(58999, 'OMSÆTNINGSAKTIVER I ALT', 'Z', 55099),
        );
        self::assertSame(array(1000, 1950), dash_chart_revenue_range($chart));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function names(): array
    {
        return array(
            'Nettoomsætning' => array('Nettoomsætning', true),
            'upper case with entity' => array('NETTOOMS&AElig;TNING', true),
            'Latin-1 bytes' => array("OMS\xC6TNING I ALT", true),
            'i alt in one word' => array('Omsætning ialt', true),
            'ae spelling' => array('Nettoomsaetning', true),
            'Norwegian net revenue' => array('Netto omsetning', true),
            'Norwegian operating income' => array('Sum driftsinntekter', true),
            'Norwegian heading style' => array('Salgs- og driftsinntekter', true),
            'English net revenue' => array('Net revenue', true),
            'English turnover' => array('Total turnover', true),
            'English net sales' => array('Net sales', true),
            'renamed: Total omsætning' => array('Total omsætning', true),
            'renamed: Samlet omsætning' => array('Samlet omsætning', true),
            'renamed: Omsætning (netto)' => array('Omsætning (netto)', true),
            'Norwegian total first' => array('Samlet omsetning', true),
            'Norwegian totalt last' => array('Driftsinntekter totalt', true),
            'English sales revenue' => array('Total sales revenue', true),
            'English total net sales' => array('Total net sales', true),
            'renamed to something else' => array('Salg i alt', false),
            'total income' => array('Total income', false),
            'balance sheet' => array('OMSÆTNINGSAKTIVER I ALT', false),
            'other operating income' => array('Andre driftsindtægter', false),
            'goods only' => array('Varesalg i alt', false),
            'gross profit' => array('Bruttofortjeneste/Bruttotab', false),
            'bare sales' => array('Sales', false),
        );
    }

    #[DataProvider('names')]
    public function testNetRevenueNames(string $name, bool $expected): void
    {
        self::assertSame($expected, dash_is_net_revenue_name($name));
    }

    public function testASumOverStatusAccountsIsNotRevenue(): void
    {
        $chart = array(self::row(5000, 'Omsætning', 'H'), self::row(5100, 'Bank', 'S'), self::row(5190, 'Omsætning i alt', 'Z', 5000));
        self::assertNull(dash_chart_revenue_range($chart));
        // a sum without any operating account below it, or with its from-account above it, is no range either
        self::assertNull(dash_chart_revenue_range(array(self::row(1000, 'Nettoomsætning', 'H'), self::row(1390, 'Nettoomsætning', 'Z', 1010))));
        self::assertNull(dash_chart_revenue_range(array(self::row(1010, 'Salg', 'D'), self::row(1390, 'Nettoomsætning', 'Z', 1400))));
    }

    public function testTheFirstMatchingSumWinsWhateverTheOrder(): void
    {
        $chart = array(
            self::row(2840, 'Nettoomsætning i alt', 'Z', 1010), self::row(1010, 'Salg', 'D'), self::row(1390, 'Nettoomsætning', 'Z', 1010),
            self::row(1610, 'Varekøb', 'D'),
        );
        self::assertSame(array(1010, 1390), dash_chart_revenue_range($chart));
    }

    public function testNoChartNoRange(): void
    {
        self::assertNull(dash_chart_revenue_range(array()));
        $renamed = array(self::row(1010, 'Salg', 'D'), self::row(1390, 'Salg og ydelser', 'Z', 1010));
        self::assertNull(dash_chart_revenue_range($renamed));
    }

    /**
     * Stored Fra/Til kontonr. (null = nothing stored, '' = an empty field saved from the drawer), the chart's range, and the
     * expected from, to, chosen bounds and automatic range (dash_revenue_accounts()).
     *
     * @return array<string, array{mixed, mixed, array{0: int, 1: int}|null, array{0: int, 1: int, 2: int|null, 3: int|null, 4: int, 5: int, 6: string}}>
     */
    public static function savedRanges(): array
    {
        $std = array(1010, 1390);
        return array(
            // both empty: the chart's net revenue accounts, or 0-2000 when the chart has none
            'nothing stored' => array(null, null, $std, array(1010, 1390, null, null, 1010, 1390, 'chart')),
            'both stored empty' => array('', '', $std, array(1010, 1390, null, null, 1010, 1390, 'chart')),
            'both empty, another chart' => array('', '', array(2, 1999), array(2, 1999, null, null, 2, 1999, 'chart')),
            'both empty, no chart range' => array('', '', null, array(0, 2000, null, null, 0, 2000, 'default')),
            'nothing stored, no chart range' => array(null, null, null, array(0, 2000, null, null, 0, 2000, 'default')),
            'blanks only' => array(' ', "\t", $std, array(1010, 1390, null, null, 1010, 1390, 'chart')),
            // an empty Fra: the chart's from-account (0 without one); an empty Til: the chart's sum account (2000 without one)
            'empty from' => array('', '2000', $std, array(1010, 2000, null, 2000, 1010, 1390, 'chart')),
            'empty from, no chart range' => array('', '1999', null, array(0, 1999, null, 1999, 0, 2000, 'default')),
            'empty to' => array('1000', '', $std, array(1000, 1390, 1000, null, 1010, 1390, 'chart')),
            'empty to, no chart range' => array('1000', '', null, array(1000, 2000, 1000, null, 0, 2000, 'default')),
            'from stored, to never stored' => array('1100', null, $std, array(1100, 1390, 1100, null, 1010, 1390, 'chart')),
            'a 0 from is a choice' => array('0', '', $std, array(0, 1390, 0, null, 1010, 1390, 'chart')),
            // the old dashboard's unchosen pairs are automatic; with no chart range the pair stays the automatic range
            'the old dashboard\'s 0/2000' => array('0', '2000', $std, array(1010, 1390, null, null, 1010, 1390, 'chart')),
            '0/2000 as numbers' => array(0, 2000, array(2, 1999), array(2, 1999, null, null, 2, 1999, 'chart')),
            '0/2000, no chart range' => array('0', '2000', null, array(0, 2000, null, null, 0, 2000, 'default')),
            'the 2024 dashboard\'s 0/12000' => array('0', '12000', $std, array(1010, 1390, null, null, 1010, 1390, 'chart')),
            '0/12000, no chart range, stays' => array('0', '12000', null, array(0, 12000, null, null, 0, 12000, 'default')),
            // explicit values win, the chart only gives the placeholders
            'a chosen range' => array('1000', '1999', $std, array(1000, 1999, 1000, 1999, 1010, 1390, 'chart')),
            'another upper account with 0' => array('0', '1999', $std, array(0, 1999, 0, 1999, 1010, 1390, 'chart')),
            'only the from-account changed' => array('1010', '2000', $std, array(1010, 2000, 1010, 2000, 1010, 1390, 'chart')),
            'only the to-account changed' => array('0', '1390', $std, array(0, 1390, 0, 1390, 1010, 1390, 'chart')),
            'chosen, no chart range' => array('10000', '11797', null, array(10000, 11797, 10000, 11797, 0, 2000, 'default')),
            'a reversed pair stored earlier is kept' => array('1500', '1000', $std, array(1500, 1000, 1500, 1000, 1010, 1390, 'chart')),
            'not digits reads as automatic' => array('-5', 'abc', $std, array(1010, 1390, null, null, 1010, 1390, 'chart')),
        );
    }

    /**
     * @param array{0: int, 1: int}|null $chart
     * @param array{0: int, 1: int, 2: int|null, 3: int|null, 4: int, 5: int, 6: string} $e
     */
    #[DataProvider('savedRanges')]
    public function testEmptyBoundsAreAutomatic(mixed $min, mixed $max, ?array $chart, array $e): void
    {
        self::assertSame(
            array('from' => $e[0], 'to' => $e[1], 'chosenFrom' => $e[2], 'chosenTo' => $e[3], 'autoFrom' => $e[4], 'autoTo' => $e[5], 'autoSource' => $e[6]),
            dash_revenue_accounts($min, $max, $chart)
        );
    }

    /**
     * What the drawer may store: digits without leading zeros, '' for an empty field, null (refused) for anything else.
     *
     * @return array<string, array{string, string|null}>
     */
    public static function typedAccounts(): array
    {
        return array(
            'empty' => array('', ''),
            'blanks' => array('  ', ''),
            'a number' => array('1010', '1010'),
            'trimmed' => array(' 1390 ', '1390'),
            'leading zeros' => array('0010', '10'),
            'zero' => array('0', '0'),
            '15 digits' => array('999999999999999', '999999999999999'),
            '16 digits' => array('9999999999999999', null),
            'letters' => array('abc', null),
            'digits and letters' => array('10a', null),
            'negative' => array('-5', null),
            'decimal' => array('10.5', null),
            'Danish decimal' => array('10,5', null),
            'space inside' => array('10 10', null),
        );
    }

    #[DataProvider('typedAccounts')]
    public function testWhatTheDrawerStores(string $typed, ?string $expected): void
    {
        self::assertSame($expected, dash_account_input($typed));
    }

    public function testAStoredEmptyFieldIsNotZero(): void
    {
        // The drawer saved an empty field as (int) '' = 0, so 1010/'' became 1010-0 and every figure 0.
        $r = dash_revenue_accounts(dash_account_input('1010'), dash_account_input(''), array(1010, 1390));
        self::assertSame(array(1010, 1390), array($r['from'], $r['to']));
        self::assertNull(dash_saved_account(''));
        self::assertNull(dash_saved_account(null));
        self::assertSame(0, dash_saved_account('0'));
    }

    public function testTheStandardChartFillsTheEmptyFields(): void
    {
        $r = dash_revenue_accounts('', '', dash_chart_revenue_range(self::standardChart()));
        self::assertSame(array(1010, 1390, 'chart'), array($r['autoFrom'], $r['autoTo'], $r['autoSource']));
    }

    public function testBothEndsCountInEveryRevenueQuery(): void
    {
        self::assertSame('kontonr >= 1010 and kontonr <= 1390', dash_account_sql(1010, 1390));
        // No query in the dashboard's data compares kontonr with < or > on its own (the old daily query left the edges out).
        $code = (string) file_get_contents(__DIR__ . '/../../index/dashboardIncludes/dashData.php');
        self::assertDoesNotMatchRegularExpression('/kontonr\s*[<>](?!=)/', $code);
        self::assertSame(2, substr_count($code, '" . dash_account_sql($min, $max)'));
    }
}
