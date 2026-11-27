<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../finans/rapport_includes/regnskab.php';

/**
 * Which side of the statement each line of a chart without a Sideskift account is on (regnskab_kontoside(),
 * finans/rapport_includes/regnskab.php): Resultat, Budget and Sidste år show the 'D' lines and Balance the 'S' lines.
 * D accounts are on the result side, S and R accounts on the balance side, a sum on the side of the accounts it adds
 * up (one that adds up both sides on the side of the first of them when its range starts at that account, on the side
 * of most of them when its range starts before it, e.g. at its heading, and by its place in the chart when it adds up
 * half of each side or more, or as many of each from before its first account), and a heading on the side of the
 * accounts under it (over both sides on the side of the sum that closes its group, and without one on the side of most
 * of them).
 * The page itself is covered by tests/characterization/finans/StatementWithoutSideskiftTest.test.php.
 *
 * History:
 * 20261119 CL/ASR Created.
 */
final class regnskabKontoside extends TestCase
{
    /**
     * A chart file as regnskab() reads the chart: lines keyed 1..n in account number order.
     *
     * @param list<array{0:int, 1:string, 2:int}> $rows kontonr, kontotype, fra_kto
     * @return array{0: array<int, int>, 1: array<int, string>, 2: array<int, int>}
     */
    private static function chart(array $rows): array
    {
        usort($rows, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $kontonr = $kontotype = $fra_kto = array();
        foreach ($rows as $i => [$nr, $type, $fra]) {
            $kontonr[$i + 1] = $nr;
            $kontotype[$i + 1] = $type;
            $fra_kto[$i + 1] = $fra;
        }
        return array($kontonr, $kontotype, $fra_kto);
    }

    /**
     * The side of each account number.
     *
     * @param list<array{0:int, 1:string, 2:int}> $rows kontonr, kontotype, fra_kto
     * @return array<int, string>
     */
    private static function sides(array $rows): array
    {
        [$kontonr, $kontotype, $fra_kto] = self::chart($rows);
        $sides = regnskab_kontoside($kontonr, $kontotype, $fra_kto);
        self::assertSame(array_keys($kontonr), array_keys($sides), 'a side for each line, in the chart\'s order');
        return array_combine(array_values($kontonr), array_values($sides));
    }

    /**
     * importfiler/kontoplan.txt, the chart of new regnskaber, as includes/opretfunc.php reads it.
     *
     * @return list<array{0:int, 1:string, 2:int}>
     */
    private static function standardChart(): array
    {
        $rows = array();
        foreach (file(__DIR__ . '/../../importfiler/kontoplan.txt') as $line) {
            $f = explode("\t", trim($line));
            if (count($f) >= 5) {
                $rows[] = array((int) $f[0], $f[2], is_numeric($f[4]) ? (int) $f[4] : 0);
            }
        }
        return $rows;
    }

    /**
     * importfiler/kontoplan2.txt, an older chart with the types written out and no Sideskift account.
     *
     * @return list<array{0:int, 1:string, 2:int}>
     */
    private static function kontoplan2(): array
    {
        $types = array('Tekst' => 'H', 'Drift' => 'D', 'Status' => 'S', 'Sum' => 'Z');
        $rows = array();
        foreach (file(__DIR__ . '/../../importfiler/kontoplan2.txt') as $line) {
            $f = explode("\t", rtrim($line, "\r\n"));
            if (isset($f[2]) && ctype_digit($f[0])) {
                $rows[] = array((int) $f[0], $types[trim($f[2], '"')], (int) trim($f[5] ?? ''));
            }
        }
        return $rows;
    }

    /**
     * @param array<int, string> $sides
     * @param list<int> $except lines left out, checked by the caller
     */
    private static function assertSplitAt(int $balanceFrom, array $sides, array $except = []): void
    {
        foreach ($sides as $nr => $side) {
            if (!in_array($nr, $except, true)) {
                self::assertSame($nr < $balanceFrom ? 'D' : 'S', $side, "account $nr");
            }
        }
    }

    /**
     * The standard chart with more accounts, numbered among the other side's accounts. Every other line stays on the
     * side it has in the standard chart, which splits at 5000; the caller checks the lines in $check.
     *
     * @param array<int, string> $accounts kontonr => kontotype
     * @param list<int> $check
     * @return array<int, string>
     */
    private static function standardChartWith(array $accounts, array $check): array
    {
        $rows = self::standardChart();
        foreach ($accounts as $kontonr => $type) {
            self::assertNotContains($kontonr, array_column($rows, 0), "the standard chart has no $kontonr");
            $rows[] = array($kontonr, $type, 0);
        }
        $sides = self::sides($rows);
        self::assertSplitAt(5000, $sides, array_merge(array_keys($accounts), $check));
        foreach ($accounts as $kontonr => $type) {
            self::assertSame($type == 'D' ? 'D' : 'S', $sides[$kontonr], "the account $kontonr goes by its type");
        }
        return $sides;
    }

    /**
     * importfiler/kontoplan2.txt with more accounts, numbered among the other side's accounts. Every other line stays on
     * the side it has in that chart, which splits at 50000 A K T I V E R; the caller checks the lines in $check.
     *
     * @param array<int, string> $accounts kontonr => kontotype
     * @param list<int> $check
     * @return array<int, string>
     */
    private static function kontoplan2With(array $accounts, array $check): array
    {
        $rows = self::kontoplan2();
        foreach ($accounts as $kontonr => $type) {
            self::assertNotContains($kontonr, array_column($rows, 0), "kontoplan2 has no $kontonr");
            $rows[] = array($kontonr, $type, 0);
        }
        $sides = self::sides($rows);
        self::assertSplitAt(50000, $sides, array_merge(array_keys($accounts), $check));
        foreach ($accounts as $kontonr => $type) {
            self::assertSame($type == 'D' ? 'D' : 'S', $sides[$kontonr], "the account $kontonr goes by its type");
        }
        return $sides;
    }

    /**
     * The standard chart with each sum's range starting at the heading before the first account it adds up instead of
     * at that account, as the sums of importfiler/kontoplan2.txt start at their heading, with more accounts as in
     * standardChartWith().
     *
     * @param array<int, string> $accounts kontonr => kontotype
     * @return array<int, string>
     */
    private static function standardChartFromHeadingsWith(array $accounts): array
    {
        $rows = self::standardChart();
        $headings = array();
        foreach ($rows as $row) {
            if ($row[1] == 'H') {
                $headings[] = $row[0];
            }
        }
        foreach ($rows as $i => $row) {
            if ($row[1] == 'Z') {
                $before = array_filter($headings, static fn(int $nr): bool => $nr < $row[2]);
                self::assertNotEmpty($before, "a heading before {$row[2]}");
                $rows[$i][2] = max($before);
            }
        }
        foreach ($accounts as $kontonr => $type) {
            self::assertNotContains($kontonr, array_column($rows, 0), "the standard chart has no $kontonr");
            $rows[] = array($kontonr, $type, 0);
        }
        $sides = self::sides($rows);
        self::assertSplitAt(5000, $sides, array_keys($accounts));
        foreach ($accounts as $kontonr => $type) {
            self::assertSame($type == 'D' ? 'D' : 'S', $sides[$kontonr], "the account $kontonr goes by its type");
        }
        return $sides;
    }

    public function test_the_standard_chart_splits_where_the_balance_begins(): void
    {
        $rows = self::standardChart();
        self::assertNotContains('X', array_column($rows, 1), 'the standard chart has no Sideskift account');
        $sides = self::sides($rows);
        self::assertCount(count($rows), $sides);
        // 1000 RESULTATOPGØRELSE to 4999 Årets resultat; 5000 BALANCE to 9999 PASSIVER I ALT, 6935 Årets resultat (R) included.
        self::assertSplitAt(5000, $sides);
    }

    public function test_an_older_chart_with_a_balance_check_over_the_whole_chart(): void
    {
        $rows = self::kontoplan2();
        self::assertContains(array(99999, 'Z', 1), $rows, 'Balancekontrol adds up the whole chart');
        $sides = self::sides($rows);
        // The balance begins with 50000 AKTIVER. Balancekontrol adds up all 62 D and all 37 S accounts: by the first account
        // it adds up, or by the number of accounts, it would be on the result side, but it adds up half of each side or
        // more, so it goes by its place, the last line of the chart: 99995 Ukendte poster before it.
        self::assertSplitAt(50000, $sides);
        self::assertSame('S', $sides[99999], '99999 Balancekontrol');
    }

    public function test_accounts_numbered_on_the_other_side_go_by_their_type(): void
    {
        $sides = self::sides(array(
            array(1000, 'H', 0),
            array(1010, 'D', 0),
            array(1020, 'D', 0),
            array(1030, 'S', 0), // a status account among the result accounts
            array(1040, 'D', 0),
            array(1090, 'Z', 1010), // its group: three D and the S
            array(1100, 'H', 0),
            array(1110, 'S', 0), // a group of status accounts only, among the result accounts
            array(1190, 'Z', 1110),
            array(4999, 'Z', 1010), // the year's result: three D and two S
            array(5000, 'H', 0),
            array(5010, 'S', 0),
            array(5020, 'D', 0), // a result account among the status accounts
            array(5030, 'S', 0),
            array(5090, 'Z', 5010),
            array(5100, 'H', 0),
            array(5110, 'D', 0),
            array(5190, 'Z', 5110), // a group of result accounts only, among the status accounts
            array(6930, 'H', 0), // a heading counts the accounts up to the next heading
            array(6935, 'R', 4999),
            array(6940, 'S', 0),
            array(9999, 'Z', 1010), // a balance check over the whole chart
        ));
        self::assertSame(array(
            1000 => 'D', 1010 => 'D', 1020 => 'D', 1030 => 'S', 1040 => 'D', 1090 => 'D',
            1100 => 'S', 1110 => 'S', 1190 => 'S', 4999 => 'D',
            5000 => 'S', 5010 => 'S', 5020 => 'D', 5030 => 'S', 5090 => 'S',
            5100 => 'D', 5110 => 'D', 5190 => 'D', 6930 => 'S', 6935 => 'S', 6940 => 'S', 9999 => 'S',
        ), $sides);
    }

    public function test_headings_empty_sums_and_the_resultat_account(): void
    {
        $sides = self::sides(array(
            array(1000, 'H', 0),
            array(1001, 'H', 0), // headings take the side of the first line after them that has one
            array(1050, 'Z', 1040), // adds up nothing: like a heading
            array(1100, 'D', 0),
            array(1190, 'Z', 1100),
            array(5000, 'H', 0),
            array(5100, 'S', 0),
            array(6930, 'H', 0),
            array(6935, 'R', 1190),
            array(6936, 'Z', 6935), // adds up the Resultat account only: the balance
            array(9000, 'H', 0), // after the last line with a side: the side of that line
            array(9990, 'Z', 9001),
        ));
        self::assertSame(array(
            1000 => 'D', 1001 => 'D', 1050 => 'D', 1100 => 'D', 1190 => 'D',
            5000 => 'S', 5100 => 'S', 6930 => 'S', 6935 => 'S', 6936 => 'S', 9000 => 'S', 9990 => 'S',
        ), $sides);
    }

    public function test_a_sum_over_both_sides_goes_with_the_first_account_it_adds_up(): void
    {
        // Five D and five S accounts. Each group starts with an account of its own side and has more accounts of the other
        // side numbered in it: the sum and the heading stay with the group's first account, not with most accounts.
        $sides = self::sides(array(
            array(1000, 'H', 0),
            array(1010, 'D', 0),
            array(1011, 'S', 0),
            array(1012, 'S', 0),
            array(1090, 'Z', 1010), // 1 D and 2 S, from the D account 1010
            array(2000, 'H', 0),
            array(2010, 'D', 0),
            array(2020, 'D', 0),
            array(2090, 'Z', 2010),
            array(4999, 'Z', 1010), // 3 D and 2 S, from 1010
            array(5000, 'H', 0),
            array(5010, 'S', 0),
            array(5011, 'D', 0),
            array(5012, 'D', 0),
            array(5090, 'Z', 5010), // 2 D and 1 S, from the S account 5010
            array(6930, 'H', 0),
            array(6935, 'R', 4999),
            array(6940, 'S', 0),
            array(6990, 'Z', 5010), // 2 D and 3 S, from 5010
            array(9999, 'Z', 1010), // all of both sides: by its place, the last line before it
        ));
        self::assertSame(array(
            1000 => 'D', 1010 => 'D', 1011 => 'S', 1012 => 'S', 1090 => 'D', 2000 => 'D', 2010 => 'D', 2020 => 'D',
            2090 => 'D', 4999 => 'D',
            5000 => 'S', 5010 => 'S', 5011 => 'D', 5012 => 'D', 5090 => 'S', 6930 => 'S', 6935 => 'S', 6940 => 'S',
            6990 => 'S', 9999 => 'S',
        ), $sides);

        // Only result accounts: a sum over them and a heading are on the result side.
        self::assertSame(array(1000 => 'D', 1010 => 'D', 1090 => 'D'), self::sides(array(array(1000, 'H', 0), array(1010, 'D', 0), array(1090, 'Z', 1000))));
        // No account with a side at all: nothing to split.
        self::assertSame(array(1000 => '', 1090 => ''), self::sides(array(array(1000, 'H', 0), array(1090, 'Z', 1000))));
    }

    public function test_a_drift_account_in_a_group_of_the_balance_leaves_the_group_on_the_balance(): void
    {
        // A D account 7445 among 7440 Leverandører (sum 7450 from 7440): 7450 adds up one account of each side. The
        // standard chart has more S and R than D accounts, so by the share of each side 7450 went to the result side; it
        // goes with 7440, the first account it adds up.
        $sides = self::standardChartWith(array(7445 => 'D'), array(7450, 7421));
        self::assertSame('S', $sides[7450], '7450 Leverandører af varer og tjenesteydelser');
        self::assertSame('S', $sides[7421], 'the heading Leverandører, over 7440 and 7445');
        self::assertSame('S', $sides[9950], 'Kortfristet gæld, from the S account 7310');
        self::assertSame('S', $sides[9999], 'PASSIVER I ALT');
    }

    public function test_a_drift_account_in_the_equity_leaves_the_equitys_sums_on_the_balance(): void
    {
        // A D account 6945 Privat among 6940 Overført resultat (sum 6950 from 6940).
        $sides = self::standardChartWith(array(6945 => 'D'), array(6950));
        self::assertSame('S', $sides[6950], '6950 Overført resultat');
        self::assertSame('S', $sides[6931], 'the heading Overført resultat, over 6935, 6940 and 6945');
        self::assertSame('S', $sides[6971], 'Egenkapital i alt');
    }

    public function test_a_status_account_in_a_group_of_the_result_side_leaves_the_group_on_the_result_side(): void
    {
        // An S account 3815 among 3810 Andre skatter (sum 3820 from 3810), the last group before 4999 Årets resultat.
        // 3820 and 4999 go with 3810 and 1010, the first accounts they add up. Were every sum over both sides placed by its
        // place, 4999 would follow 5010 onto the balance, and 3820 with it.
        $sides = self::standardChartWith(array(3815 => 'S'), array(3820));
        self::assertSame('D', $sides[3820], '3820 Andre skatter');
        self::assertSame('D', $sides[3791], 'the heading Andre skatter, over 3810 and 3815');
        self::assertSame('D', $sides[3701], 'Resultat før skat');
        self::assertSame('D', $sides[4999], '4999 Årets resultat');
    }

    public function test_several_drift_accounts_in_a_group_of_the_balance_leave_the_group_on_the_balance(): void
    {
        // Two D accounts 7441 and 7445 among 7440 Leverandører: 7450 adds up two D accounts and one S account, but goes
        // with 7440, the first of them, and the heading 7421 with 7450. By the most accounts both went to the result side.
        $sides = self::standardChartWith(array(7441 => 'D', 7445 => 'D'), array());
        self::assertSame('S', $sides[7450], '7450 Leverandører af varer og tjenesteydelser');
        self::assertSame('S', $sides[7421], 'the heading Leverandører, over 7440, 7441 and 7445');

        // Three D accounts 6471, 6475 and 6485 among 6470 and 6480 under 6462 Likvide beholdninger (sum 6490 from 6470).
        $sides = self::standardChartWith(array(6471 => 'D', 6475 => 'D', 6485 => 'D'), array());
        self::assertSame('S', $sides[6490], '6490 Likvide beholdninger');
        self::assertSame('S', $sides[6462], 'the heading Likvide beholdninger');
    }

    public function test_several_drift_accounts_in_the_equity_leave_the_equitys_sums_on_the_balance(): void
    {
        // Two D accounts 6945 Privat and 6946 Privat bil among 6940 Overført resultat: 6950 adds up two D accounts and
        // one S account, from 6940.
        $sides = self::standardChartWith(array(6945 => 'D', 6946 => 'D'), array());
        self::assertSame('S', $sides[6950], '6950 Overført resultat');
        self::assertSame('S', $sides[6931], 'the heading Overført resultat');
        self::assertSame('S', $sides[6971], 'Egenkapital i alt');
    }

    public function test_several_status_accounts_in_a_group_of_the_result_side_leave_the_group_on_the_result_side(): void
    {
        // Two S accounts 3815 and 3816 among 3810 Andre skatter: 3820 adds up two S accounts and one D account, from 3810.
        $sides = self::standardChartWith(array(3815 => 'S', 3816 => 'S'), array());
        self::assertSame('D', $sides[3820], '3820 Andre skatter');
        self::assertSame('D', $sides[3791], 'the heading Andre skatter');
        self::assertSame('D', $sides[4999], '4999 Årets resultat');

        // The same with 3811 right after 3810.
        $sides = self::standardChartWith(array(3811 => 'S', 3815 => 'S'), array());
        self::assertSame('D', $sides[3820], '3820 Andre skatter');
        self::assertSame('D', $sides[3791], 'the heading Andre skatter');
    }

    public function test_a_heading_goes_with_the_sum_that_closes_its_group(): void
    {
        // A D account 6465 as the first account under 6462 Likvide beholdninger (6470 and 6480 after it, summed by 6490
        // from 6470): the heading stays with 6490 on the balance instead of following 6465.
        $sides = self::standardChartWith(array(6465 => 'D'), array(6462));
        self::assertSame('S', $sides[6462], 'the heading Likvide beholdninger');

        // Accounts of both sides under a heading without a sum: the side of most of them.
        self::assertSame(array(1000 => 'D', 1010 => 'D', 1020 => 'S', 1030 => 'D', 2000 => 'S', 2010 => 'S'), self::sides(array(
            array(1000, 'H', 0),
            array(1010, 'D', 0),
            array(1020, 'S', 0),
            array(1030, 'D', 0),
            array(2000, 'H', 0),
            array(2010, 'S', 0),
        )));
        // As many of each under a heading, or none: the side of the first line after it that has one.
        self::assertSame(array(1000 => 'S', 1010 => 'S', 1020 => 'D', 1030 => 'D', 2000 => 'D', 2010 => 'D'), self::sides(array(
            array(1000, 'H', 0),
            array(1010, 'S', 0),
            array(1020, 'D', 0),
            array(1030, 'H', 0),
            array(2000, 'H', 0),
            array(2010, 'D', 0),
        )));
    }

    public function test_a_sum_from_its_heading_goes_with_most_of_the_accounts_it_adds_up(): void
    {
        // The sums of importfiler/kontoplan2.txt start at their heading (51995 Anlægsaktiver i alt and 59999 AKTIVER IALT
        // from 50000 A K T I V E R, 62995 Egenkapital i alt and 70000 PASSIVER IALT from 60000 P A S S I V E R), so an
        // account numbered before a group's first account is the first account its sums add up. Such a sum goes with most
        // of its accounts, and the headings with it.
        // A D account 50500 Afskrivninger before 50800 Driftsmidler & Inventar.
        $sides = self::kontoplan2With(array(50500 => 'D'), array());
        self::assertSame('S', $sides[51995], '51995 Anlægsaktiver i alt');
        self::assertSame('S', $sides[59999], '59999 AKTIVER IALT');
        self::assertSame('S', $sides[50000], 'the heading A K T I V E R');
        self::assertSame('S', $sides[50100], 'the heading ANLÆGSAKTIVER');
        // A D account 60100 Privat before 60500 Egenkapital.
        $sides = self::kontoplan2With(array(60100 => 'D'), array());
        self::assertSame('S', $sides[62995], '62995 Egenkapital i alt');
        self::assertSame('S', $sides[70000], '70000 PASSIVER IALT');
        self::assertSame('S', $sides[60000], 'the heading P A S S I V E R');
        self::assertSame('S', $sides[60001], 'the heading EGENKAPITAL');
        // An S account 4050 before 4100 Løn, A-indkomst, in 4999 Personale i alt from 4000 PERSONALE.
        $sides = self::kontoplan2With(array(4050 => 'S'), array());
        self::assertSame('D', $sides[4999], '4999 Personale i alt');
        self::assertSame('D', $sides[4000], 'the heading PERSONALE');

        // The standard chart with its sums from their heading: an S account 2025 before 2030 Husleje (2210
        // Lokaleomkostninger from 2020) and a D account 6465 before 6470 Kasse (6490 Likvide beholdninger from 6462).
        $sides = self::standardChartFromHeadingsWith(array(2025 => 'S', 6465 => 'D'));
        self::assertSame('D', $sides[2210], '2210 Lokaleomkostninger');
        self::assertSame('S', $sides[6490], '6490 Likvide beholdninger');
    }

    public function test_a_sum_from_its_heading_over_as_many_accounts_of_each_side_goes_by_its_place(): void
    {
        // An S account 40500 before 41000 Renteindtægter, the only account of 41995 Renteindtægter i alt from 40000: one
        // account of each side. 41995 goes by its place, with 43100 Renteudgifter after it, not with 40500.
        $sides = self::kontoplan2With(array(40500 => 'S'), array());
        self::assertSame('D', $sides[41995], '41995 Renteindtægter i alt');
        self::assertSame('D', $sides[30000], 'the heading RENTEINDTÆGTER');

        // The standard chart with its sums from their heading: a D account 7425 before 7440 (7450 Leverandører from 7421)
        // and an S account 3800 before 3810, the last group before 4999 Årets resultat (3820 Andre skatter from 3791).
        $sides = self::standardChartFromHeadingsWith(array(7425 => 'D', 3800 => 'S'));
        self::assertSame('S', $sides[7450], '7450 Leverandører af varer og tjenesteydelser');
        self::assertSame('S', $sides[7421], 'the heading Leverandører af varer og tjenesteydelser');
        self::assertSame('D', $sides[3820], '3820 Andre skatter');
        self::assertSame('D', $sides[3791], 'the heading Andre skatter');
    }
}
