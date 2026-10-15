<?php

// 20261014 MJ SST-863 A historical-date Åbne poster report hid rows that were still open on the
//                  date asked for. The report judged a settled row by its own udlign_date, and
//                  includes/autoudlign.php stamps rows with their own transdate rather than the
//                  date the settlement group closed - so an invoice booked 31/3 and paid 6/4 had
//                  udlign_date 2026-03-31 and counted as closed on 31/3. For Loppen Maribo
//                  (pos_72) that hid 94.370,04 of the 96.659,38 the report differed from finans
//                  56100 by.
//
//                  Two halves. The first calls openpost_account_aging() directly with rows shaped
//                  the way each query hands them over. The second runs the SQL that
//                  openpost_account_query_parts() builds against a real Postgres, because the
//                  settlement date has to come from rows the report's own date filter excludes,
//                  and a left join against a grouped derived table is not something a string
//                  assertion can tell you works.
//
// strict_types is off: the file under test is legacy and passes '1'/'0' strings where the stubs
// below take and return scalars loosely, and eval'd/required legacy code inherits the setting.

namespace {

    use PHPUnit\Framework\TestCase;

    final class OpenPostHistoricalSettleDateTest extends TestCase
    {
        private const ROOT = __DIR__ . '/../../../..';

        private static ?\PDO $db = null;

        public static function setUpBeforeClass(): void
        {
            // Real helpers where they stand alone; the file under test is pure function
            // definitions, so requiring it has no side effects.
            require_once self::ROOT . '/includes/usdate.php';
            require_once self::ROOT . '/includes/forfaldsdag.php';
            require_once self::ROOT . '/includes/stdFunc/nrCast.php';

            if (!function_exists('afrund')) {
                // verbatim from includes/std_func.php
                eval('function afrund($tal, $decimaler) {
                    $tal = (float)$tal; $decimaler = (int)$decimaler; $tmp = 0.001;
                    for ($x = 1; $x < $decimaler; $x++) { $tmp = $tmp / 10; }
                    if ($tal > 0) { $tal = round($tal + $tmp, $decimaler); }
                    elseif ($tal < 0) { $tal = round($tal - $tmp, $decimaler); }
                    return $tal;
                }');
            }
            if (!function_exists('ifset')) {
                eval('function ifset($arrayOrVar, $key = null, $default = null) {
                    return is_array($arrayOrVar) && isset($arrayOrVar[$key]) ? $arrayOrVar[$key] : $default;
                }');
            }
            // db_escape_string() is deliberately NOT stubbed. Another test file in the suite may
            // already have required includes/db_query.php, in which case a function_exists() stub
            // never installs and the real one runs anyway - so the real one is what this test
            // uses, and db() hands it the live connection it needs.
            // Only reached for a foreign-currency row at the kurs=100 placeholder, which these
            // fixtures do not use; a call would mean the test drifted into untested territory.
            if (!function_exists('db_select')) {
                eval('function db_select($sql, $ctx = null) {
                    throw new \RuntimeException("openpost_account_aging reached the database: " . $sql);
                }');
            }
            if (!function_exists('db_fetch_array')) {
                eval('function db_fetch_array($r) { return false; }');
            }

            require_once self::ROOT . '/includes/reportFunc/showOpenPosts.php';
            $GLOBALS['baseCurrency'] = 'DKK';
        }

        // ------------------------------------------------------------------ the decision

        /**
         * One openpost row as the report selects it.
         *
         * @param string|null $groupDate the settlement group's close date, or null when the query
         *                               did not join one (the everyday report)
         */
        private function row(string $transdate, float $amount, string $udlignet,
            ?string $udlignDate, ?string $groupDate = null): array
        {
            $row = array(
                'konto_id' => 846, 'amount' => $amount, 'valuta' => 'DKK', 'valutakurs' => 100,
                'transdate' => $transdate, 'forfaldsdate' => $transdate,
                'udlignet' => $udlignet, 'udlign_date' => $udlignDate,
            );
            if ($groupDate !== null) {
                $row['udlign_group_date'] = $groupDate;
            }
            return $row;
        }

        /**
         * @param array<int, array> $posts
         * @return array the aging result
         */
        private function aging(array $posts, string $todate, string $today = '2026-10-14'): array
        {
            $cache = array();
            return openpost_account_aging($posts, $todate, $today, 'D', $cache);
        }

        /**
         * The ticket's own example: konto 1790's settlement is booked 31/3 and the payout that
         * offsets it 6/4. Both rows carry their own transdate as udlign_date. The report's date
         * filter keeps only the 31/3 row, which master then treated as closed.
         */
        public function testASettledRowWhoseGroupClosesLaterIsStillOpen(): void
        {
            $post = $this->row('2026-03-31', -6488.90, '1', '2026-03-31', '2026-04-06');

            $aging = $this->aging(array($post), '2026-03-31');

            self::assertSame(-6488.90, afrund($aging['openY'], 2),
                'the row is still being treated as closed on a date its group had not closed');
            self::assertSame(0, $aging['accountAligned'],
                'the account counts as fully settled on 31/3, which it was not');
        }

        /** Once the group has closed, the row is closed - the fix must not open everything. */
        public function testARowWhoseGroupClosedOnOrBeforeTheDateIsClosed(): void
        {
            foreach (array('2026-03-31', '2026-03-30') as $groupClose) {
                $post = $this->row('2026-03-29', -1000.00, '1', '2026-03-29', $groupClose);

                $aging = $this->aging(array($post), '2026-03-31');

                self::assertSame(0.0, afrund($aging['openY'], 2),
                    "a group closed $groupClose should not be open on 2026-03-31");
                self::assertSame(1, $aging['accountAligned']);
            }
        }

        /**
         * A row in no settlement group (udlign_id null or 0, so nothing to join) keeps the old
         * behaviour: the ticket's 238,00 of rows with no settlement date stay open.
         */
        public function testARowWithNoGroupFallsBackToItsOwnDate(): void
        {
            $withoutDate = $this->row('2026-02-01', -238.00, '1', null, null);
            self::assertSame(-238.00, afrund($this->aging(array($withoutDate), '2026-03-31')['openY'], 2),
                'a settled row with no settlement date at all should still count as open');

            $dated = $this->row('2026-02-01', -500.00, '1', '2026-02-15', null);
            self::assertSame(0.0, afrund($this->aging(array($dated), '2026-03-31')['openY'], 2),
                'without a group the row\'s own udlign_date should still decide');

            $later = $this->row('2026-02-01', -500.00, '1', '2026-04-06', null);
            self::assertSame(-500.00, afrund($this->aging(array($later), '2026-03-31')['openY'], 2),
                'a row settled after the date should be open whether or not it has a group');
        }

        /** An unsettled row is open regardless of any date. */
        public function testAnUnsettledRowIsAlwaysOpen(): void
        {
            foreach (array('0', '', null) as $udlignet) {
                $post = $this->row('2026-01-15', -750.00, (string)$udlignet, '2026-01-15', '2026-01-20');
                self::assertSame(-750.00, afrund($this->aging(array($post), '2026-03-31')['openY'], 2),
                    'an unsettled row stopped counting as open');
            }
        }

        /**
         * The everyday report (todate == today) never looks at a settlement date - its row set
         * already excludes settled rows - so the rows it hands over carry no group date and the
         * decision must be untouched by this change.
         */
        public function testTheEverydayReportIsUnaffected(): void
        {
            $today = '2026-10-14';
            $settled = $this->row('2026-03-31', -6488.90, '1', '2026-03-31', null);
            $open = $this->row('2026-09-01', -200.00, '0', null, null);

            // Those rows carry no udlign_group_date key at all, because the everyday queries do
            // not select one. Reading it without ifset() would warn on every row of every
            // account, so the absence of a warning is part of the contract here.
            $warnings = array();
            set_error_handler(function ($severity, $message) use (&$warnings) {
                $warnings[] = $message;
                return true;
            });
            try {
                $aging = $this->aging(array($settled, $open), $today, $today);
            } finally {
                restore_error_handler();
            }

            self::assertSame(array(), $warnings,
                'the aging loop warns on a row set that carries no settlement-group column');
            self::assertSame(-200.00, afrund($aging['openY'], 2),
                'the everyday report changed which rows it counts as open');
            self::assertSame(-6688.90, afrund($aging['y'], 2), 'the full balance changed');
        }

        // ------------------------------------------------------------------ the query parts

        /**
         * The parts for a report as of $todate. openpost_account_filter() escapes through the
         * real db_escape_string(), which wants a live pgsql connection, so this needs the
         * database even where the assertion is only about the SQL string it builds.
         *
         * @return array
         */
        private function parts(string $todate, string $today = '2026-10-14', $visAlle = false): array
        {
            $this->db();
            return openpost_account_query_parts(null, null, 'D', true, $todate, $today, $visAlle, '', '', 'postgresql');
        }

        /** The everyday report must issue no extra SQL at all. */
        public function testNoJoinIsBuiltForTheEverydayReport(): void
        {
            $parts = $this->parts('2026-10-14');

            self::assertSame('', $parts['settleJoin'], 'the everyday report now carries a settlement join');
            self::assertSame('', $parts['settleSelect'], 'the everyday report now selects a settlement date');
        }

        /** On a past date the join has to be scoped by account as well as by group id. */
        public function testTheHistoricalJoinIsScopedByAccountAndGroup(): void
        {
            $parts = $this->parts('2026-03-31');

            self::assertNotSame('', $parts['settleJoin'], 'the historical report builds no settlement join');
            self::assertStringContainsString('max(transdate) as udlign_group_date', $parts['settleJoin']);
            self::assertStringContainsString('group by konto_id, udlign_id', $parts['settleJoin'],
                'the group date is no longer grouped per account, so reused udlign_ids merge accounts');
            self::assertStringContainsString('udlign_group.konto_id = openpost.konto_id', $parts['settleJoin'],
                'the join no longer matches on the account');
            self::assertStringContainsString("udlign_id is not null and udlign_id != 0", $parts['settleJoin'],
                'rows with no settlement group are no longer excluded from the aggregate');
            self::assertStringNotContainsString('transdate<=', $parts['settleJoin'],
                'the aggregate is date-limited, so it cannot see the postings that closed the group');
        }

        // ------------------------------------------------------------------ the SQL

        private function db(): \PDO
        {
            if (self::$db !== null) {
                return self::$db;
            }
            if (!extension_loaded('pdo_pgsql')) {
                self::markTestSkipped('pdo_pgsql is required to run the report SQL.');
            }
            $dsn = getenv('SALDI_CHAR_DSN');
            if (!$dsn) {
                self::markTestSkipped('set SALDI_CHAR_DSN, SALDI_CHAR_PGUSER and SALDI_CHAR_PGPASS to run the report SQL.');
            }
            if (!extension_loaded('pgsql')) {
                self::markTestSkipped('the pgsql extension is required: db_escape_string() escapes through it.');
            }
            $user = getenv('SALDI_CHAR_PGUSER') ?: null;
            $pass = getenv('SALDI_CHAR_PGPASS') ?: null;
            self::$db = new \PDO($dsn, $user, $pass, array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));

            // The real db_escape_string() reads these two globals, so give it the live connection
            // rather than stubbing the escaping the code under test depends on.
            $host = preg_match('/host=([^;]+)/', $dsn, $m) ? $m[1] : 'localhost';
            $name = preg_match('/dbname=([^;]+)/', $dsn, $m) ? $m[1] : '';
            $conn = pg_connect("host=$host dbname=$name user=$user password=$pass connect_timeout=5");
            if ($conn === false) {
                self::markTestSkipped('could not open the pgsql connection db_escape_string() needs.');
            }
            $GLOBALS['connection'] = $conn;
            $GLOBALS['db_type'] = 'postgresql';
            return self::$db;
        }

        /**
         * The ticket's konto 1790 and a control account whose group closed in time, plus a second
         * account sharing udlign_id 1 - the shape an upgrade's autoudlign('0') left behind.
         */
        private function seed(): void
        {
            $db = $this->db();
            // TEMP tables only, and never a DROP: pg_temp precedes public in the search path, so
            // nothing here can reach a real openpost even if SALDI_CHAR_DSN names a live tenant.
            $db->exec("CREATE TEMP TABLE IF NOT EXISTS openpost (id serial, konto_id integer, konto_nr text,
                    faktnr text, amount numeric(15,3), beskrivelse text, udlignet varchar(2), transdate date,
                    udlign_id integer, udlign_date date, valuta text, valutakurs numeric(15,3), forfaldsdate date);
                CREATE TEMP TABLE IF NOT EXISTS adresser (id integer, kontonr integer, firmanavn text,
                    addr1 text, addr2 text, postnr text, bynavn text, email text, betalingsbet text,
                    betalingsdage integer, pbs varchar(2), pbs_nr text, art varchar(2))");
            $db->exec('TRUNCATE pg_temp.openpost, pg_temp.adresser');
            $db->exec("INSERT INTO pg_temp.adresser VALUES
                (846, 1790, 'Loppesalg', '', '', '', '', '', '', 0, '', '', 'D'),
                (847, 1371, 'Balanceret konto', '', '', '', '', '', '', 0, '', '', 'D'),
                (848, 1500, 'Uudlignet konto', '', '', '', '', '', '', 0, '', '', 'D')");
            // konto 1790: settled 31/3, paid 6/4 - both rows stamped with their own transdate,
            // both in group 1, which is also used by the other account.
            $db->exec("INSERT INTO pg_temp.openpost
                    (konto_id, konto_nr, faktnr, amount, udlignet, transdate, udlign_id, udlign_date, valuta, valutakurs, forfaldsdate)
                VALUES
                (846, 1790, 'AFR', -6488.90, '1', '2026-03-31', 1, '2026-03-31', 'DKK', 100, '2026-03-31'),
                (846, 1790, 'IND',  6488.90, '1', '2026-04-06', 1, '2026-04-06', 'DKK', 100, '2026-04-06'),
                (847, 1371, 'F1',    1000.00, '1', '2026-02-01', 1, '2026-02-01', 'DKK', 100, '2026-02-01'),
                (847, 1371, 'B1',   -1000.00, '1', '2026-02-10', 1, '2026-02-10', 'DKK', 100, '2026-02-10'),
                (848, 1500, 'UNS',    250.00, '0', '2026-02-20', 0, NULL,        'DKK', 100, '2026-02-20')");
        }

        /**
         * Runs the page query's row source the way vis_aabne_poster() assembles it and returns
         * the per-account open totals openpost_account_aging() derives from it.
         *
         * @return array<int, float> keyed by konto_id
         */
        private function accountState(string $todate, string $today = '2026-10-14'): array
        {
            $parts = $this->parts($todate, $today);
            $sql = "select openpost.*{$parts['settleSelect']} from openpost {$parts['settleJoin']} "
                . "where {$parts['postWhere']} order by openpost.konto_id, openpost.faktnr";
            $rows = array();
            foreach ($this->db()->query($sql)->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $rows[(int)$row['konto_id']][] = $row;
            }
            $state = array();
            foreach ($rows as $accountId => $posts) {
                $aging = $this->aging($posts, $todate, $today);
                $state[$accountId] = array(
                    'openY' => afrund($aging['openY'], 2),
                    'accountAligned' => $aging['accountAligned'],
                );
            }
            ksort($state);
            return $state;
        }

        /**
         * @return array<int, float> the open total per konto_id
         */
        private function openTotals(string $todate, string $today = '2026-10-14'): array
        {
            $totals = array();
            foreach ($this->accountState($todate, $today) as $accountId => $state) {
                $totals[$accountId] = $state['openY'];
            }
            return $totals;
        }

        /** The whole bug, through the real SQL: konto 1790 owes 6.488,90 on 31/3. */
        public function testTheReportSqlLeavesTheLaterSettlementOpen(): void
        {
            $this->seed();

            self::assertSame(array(846 => -6488.90, 847 => 0.0, 848 => 250.00), $this->openTotals('2026-03-31'),
                'the historical report no longer agrees with finans on 31/3');
        }

        /** After the group closes, the account is settled again. */
        public function testTheSameAccountIsSettledOnceThePaymentIsPosted(): void
        {
            $this->seed();

            self::assertSame(array(846 => 0.0, 847 => 0.0, 848 => 250.00), $this->openTotals('2026-04-06'),
                'the account is still showing as owing after its payment was posted');
        }

        /**
         * udlign_id 1 is shared by both accounts here, which is what an upgrade's autoudlign('0')
         * leaves behind. Grouping by (konto_id, udlign_id) keeps 1371's February group from
         * inheriting 1790's April date.
         *
         * Asserted on accountAligned rather than on the total: 1371's two rows net to zero, so a
         * leaked date would reopen both of them and still sum to 0,00 - the account would simply
         * be reported as unsettled through February and March, which is what the customer would
         * see as "it says I owe something" on an account that balances.
         */
        public function testAReusedSettlementIdDoesNotLeakBetweenAccounts(): void
        {
            $this->seed();

            // 31/3: 1371's group closed 10/2, 1790's not until 6/4, and both accounts have rows
            // in scope. Only the shared id could make 1371 read as unsettled here.
            $state = $this->accountState('2026-03-31');

            self::assertSame(1, $state[847]['accountAligned'],
                'a reused udlign_id is leaking another account\'s settlement date, so a balanced account reads as unsettled');
            self::assertSame(0.0, $state[847]['openY']);
            self::assertSame(0, $state[846]['accountAligned'],
                'konto 1790 should still be unsettled on 15/3 - its payment lands 6/4');
        }

        /** A row that does not exist yet at the report date is not in the report at all. */
        public function testRowsBookedAfterTheDateAreNotIncluded(): void
        {
            $this->seed();

            self::assertSame(array(847, 848), array_keys($this->accountState('2026-02-28')),
                'an account whose only rows are booked after the report date is being listed');
        }

        /** The everyday report's SQL is byte-for-byte what it was, and still excludes settled rows. */
        public function testTheEverydaySqlStillExcludesSettledRows(): void
        {
            $this->seed();
            $today = '2026-10-14';

            $parts = $this->parts($today, $today);
            self::assertSame('', $parts['settleJoin']);
            $sql = "select openpost.*{$parts['settleSelect']} from openpost {$parts['settleJoin']} "
                . "where {$parts['postWhere']}";

            $rows = $this->db()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            self::assertCount(1, $rows, 'the everyday report should show exactly the one unsettled row');
            self::assertSame('UNS', $rows[0]['faktnr']);
            self::assertArrayNotHasKey('udlign_group_date', $rows[0],
                'the everyday query selects a settlement date it has no use for');
        }

        /**
         * The three row sources assemble the join into different query shapes - the CSV export
         * selects openpost.*, the filter/sort pre-pass names its columns and cross-joins adresser
         * laterally, and the page query joins openpost onto a derived account page. A left join
         * against a grouped derived table has to survive all three.
         *
         * @return array<string, array{string}>
         */
        public static function queryShapes(): array
        {
            return array(
                'csv export' => array(
                    "select openpost.*{SELECT} from openpost {JOIN} where openpost.konto_id in (846,847,848) and {WHERE} "
                    . "order by openpost.konto_id, openpost.faktnr, openpost.amount"),
                'filter/sort pre-pass' => array(
                    "select openpost.konto_id, openpost.amount, openpost.valuta, openpost.valutakurs, openpost.transdate, "
                    . "openpost.forfaldsdate, openpost.udlignet, openpost.udlign_date{SELECT}, adresser.kontonr as account_sort "
                    . "from openpost {JOIN} "
                    . "cross join lateral (select id, kontonr, firmanavn from adresser where id=openpost.konto_id and adresser.art='D' offset 0) adresser "
                    . "where {WHERE} order by account_sort, openpost.konto_id"),
                'page query' => array(
                    "select account_page.account_id, account_page.account_kontonr, openpost.*{SELECT} from ("
                    . "select adresser.id as account_id, adresser.kontonr as account_kontonr from adresser where adresser.art='D'"
                    . ") account_page "
                    . "join openpost on openpost.konto_id=account_page.account_id {JOIN} where {WHERE} "
                    . "order by account_page.account_kontonr, openpost.konto_id"),
            );
        }

        /**
         * The three query builders in the file, as source. The shapes above are hand-written
         * reconstructions - good for proving the SQL grammar holds, useless for noticing that one
         * of the real queries stopped asking for the column. So each real builder is pinned too.
         *
         * @return array<string, array{string, string}> anchor => [first line, last line]
         */
        public static function queryBuilders(): array
        {
            return array(
                'csv export' => array(
                    '$postQtxt = "select openpost.*',
                    'order by openpost.konto_id, openpost.faktnr, openpost.amount";'),
                'filter/sort pre-pass' => array(
                    '$qtxt = "select openpost.konto_id, openpost.amount',
                    'order by account_sort, openpost.konto_id";'),
                'page query' => array(
                    '$qtxt = "select account_page.account_id, account_page.account_kontonr',
                    'order by account_page.account_sort, openpost.konto_id, openpost.faktnr, openpost.amount $tmp";'),
            );
        }

        #[\PHPUnit\Framework\Attributes\DataProvider('queryBuilders')]
        public function testEveryRealQueryBuilderAsksForTheGroupDate(string $firstLine, string $lastLine): void
        {
            $src = file_get_contents(self::ROOT . '/includes/reportFunc/showOpenPosts.php');
            self::assertNotFalse($src, 'could not read showOpenPosts.php');

            $at = strpos($src, $firstLine);
            self::assertNotFalse($at, "this query builder is gone: $firstLine");
            $end = strpos($src, $lastLine, $at);
            self::assertNotFalse($end, "this query builder no longer ends on: $lastLine");
            $block = substr($src, $at, $end - $at + strlen($lastLine));

            self::assertStringContainsString('$settleSelect', $block,
                'this row source no longer selects the settlement-group date, so the rows it hands '
                . 'openpost_account_aging() fall back to the row-level udlign_date');
            self::assertStringContainsString('$settleJoin', $block,
                'this row source no longer joins the settlement-group date');
        }

        /**
         * $settleJoin has to sit directly after the openpost reference, before any comma join.
         * A LEFT JOIN placed after `, adresser` binds to adresser, and MySQL then rejects an ON
         * clause naming openpost ("Unknown column 'openpost.konto_id' in 'on clause'") because
         * openpost is outside that join group. Postgres is more forgiving, so only a MySQL tenant
         * would find out.
         */
        public function testTheJoinIsPlacedBeforeTheCommaJoin(): void
        {
            $src = file_get_contents(self::ROOT . '/includes/reportFunc/showOpenPosts.php');
            $at = strpos($src, '$qtxt = "select openpost.konto_id, openpost.amount');
            self::assertNotFalse($at, 'the filter/sort pre-pass is gone');
            $block = substr($src, $at, strpos($src, 'order by account_sort', $at) - $at);

            $join = strpos($block, '$settleJoin');
            $comma = strpos($block, '", adresser "');
            self::assertNotFalse($join, 'the pre-pass no longer joins the settlement-group date');
            self::assertNotFalse($comma, 'the pre-pass no longer has a comma join to sit in front of');
            self::assertLessThan($comma, $join,
                'the settlement join moved after the comma join, so its ON clause cannot name openpost on MySQL');
        }

        #[\PHPUnit\Framework\Attributes\DataProvider('queryShapes')]
        public function testEveryRowSourceRunsAndCarriesTheGroupDate(string $template): void
        {
            $this->seed();
            $parts = $this->parts('2026-03-31');
            $sql = str_replace(
                array('{SELECT}', '{JOIN}', '{WHERE}'),
                array($parts['settleSelect'], $parts['settleJoin'], $parts['postWhere']),
                $template
            );

            $rows = $this->db()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

            self::assertNotSame(array(), $rows, 'the query returned nothing');
            foreach ($rows as $row) {
                self::assertArrayHasKey('udlign_group_date', $row,
                    'this row source does not carry the settlement group date');
            }
            $byAccount = array();
            foreach ($rows as $row) {
                // a row in no group joins to nothing, so its date comes back null
                $byAccount[(int)$row['konto_id']][$row['udlign_group_date'] ?? 'null'] = true;
            }
            self::assertSame(array('2026-04-06' => true), $byAccount[846],
                'konto 846 did not get its group\'s last posting as the settlement date');
            self::assertSame(array('2026-02-10' => true), $byAccount[847],
                'konto 847 inherited another account\'s settlement date');
            self::assertSame(array('null' => true), $byAccount[848],
                'a row in no settlement group was given a group date anyway');
        }

        public static function tearDownAfterClass(): void
        {
            self::$db = null;
        }
    }
}
