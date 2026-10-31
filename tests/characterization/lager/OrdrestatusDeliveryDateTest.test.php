<?php

// 20261013 CL/MJ Pins the Indkoebsordre hover box on ordrer.levdate.
//                Two halves: the four comma-separated lists the SQL builds, run against a real Postgres because STRING_AGG(... ORDER BY ... NULLS LAST) has no sqlite equivalent, and renderColumn() itself, lifted from the page and run with stubs.
//
// strict_types is deliberately off. renderColumn() is lifted out of a legacy page script and
// eval'd here, and eval'd code inherits the calling file's strict_types - the lifted code passes
// ints where the stubs below take strings and back again, which is fine for the page and would
// be a TypeError under strict_types.
// 20261029 CL/MJ Cover translation-id collisions with cached master/live voucher texts.

namespace OrdrestatusFixture {

    /**
     * The page's findtekst() needs a database. The lifted code is eval'd into this namespace, so
     * unqualified calls land here whatever the global scope happens to have loaded.
     */
    function findtekst($textId, $languageID)
    {
        $at = strpos((string)$textId, '|');
        $id = $at === false ? (string)$textId : substr((string)$textId, 0, $at);
        if (array_key_exists($id, $GLOBALS['ordrestatusCachedTexts'] ?? array())) {
            return $GLOBALS['ordrestatusCachedTexts'][$id];
        }
        return $at === false ? '#' . $textId : substr((string)$textId, $at + 1);
    }

    /** Mirrors includes/std_func.php: returns nothing for an empty date. */
    function dkdato($dato)
    {
        if ($dato) {
            list($year, $month, $day) = explode('-', $dato, 3);
            return sprintf('%02d-%02d-%s', (int)$day, (int)$month, $year);
        }
    }

    /** Mirrors includes/stdFunc/dkDecimal.php, which leaves a non-numeric value alone. */
    function dkdecimal($tal, $decimaler = null)
    {
        if (!isset($decimaler)) {
            $decimaler = 2;
        }
        return is_numeric($tal) ? number_format((float)$tal, $decimaler, ',', '.') : $tal;
    }

    /** Mirrors includes/std_func.php's array-key form. */
    function ifset($arrayOrVar, $key = null, $default = null)
    {
        return is_array($arrayOrVar) && isset($arrayOrVar[$key]) ? $arrayOrVar[$key] : $default;
    }
}

namespace {

    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\TestCase;

    final class OrdrestatusDeliveryDateTest extends TestCase
    {
        private const FILE = __DIR__ . '/../../../lager/lister/ordrestatus.php';

        private static ?\PDO $db = null;

        private static function source(): string
        {
            $src = file_get_contents(self::FILE);
            self::assertNotFalse($src, 'could not read lager/lister/ordrestatus.php');
            return $src;
        }

        // ------------------------------------------------------------------ renderColumn()

        /**
         * renderColumn() is a free function in a page script that opens a session and connects to
         * the database on include, so it is lifted out of the source and declared inside
         * OrdrestatusFixture, next to the stubs it calls.
         */
        public static function setUpBeforeClass(): void
        {
            if (function_exists('OrdrestatusFixture\renderColumn')) {
                return;
            }
            $src = file_get_contents(self::FILE);
            $at = strpos($src, 'function renderColumn(');
            self::assertNotFalse($at, 'renderColumn() is gone from ordrestatus.php');
            $end = strpos($src, "\n}\n", $at);
            self::assertNotFalse($end, 'renderColumn() no longer ends on a line of its own');
            eval('namespace OrdrestatusFixture; ' . substr($src, $at, $end - $at + 2));
        }

        /**
         * @param array<string, string|null> $row
         */
        private function render(array $row, string $value = '5,00', ...$dateText): string
        {
            $GLOBALS['sprog_id'] = 1;
            $args = array($value, $row, array('align' => 'right'), 'kreditor',
                'buy_order_orders_id', 'buy_order_orders', 'buy_order_qty', 'buy_order_date', '');
            foreach ($dateText as $extra) {
                $args[] = $extra;
            }
            return call_user_func_array('OrdrestatusFixture\renderColumn', $args);
        }

        /**
         * @param string $html
         * @return array<int, array<int, string>> one entry per body row: order number, quantity, date
         */
        private function bodyRows(string $html): array
        {
            if (strpos($html, '<tbody>') === false) {
                return array();
            }
            $body = substr($html, strpos($html, '<tbody>'), strpos($html, '</tbody>') - strpos($html, '<tbody>'));
            preg_match_all('#<tr><td><a [^>]*>([^<]*)</a></td><td>([^<]*)</td><td colspan=2>([^<]*)</td></tr>#', $body, $m, PREG_SET_ORDER);
            $rows = array();
            foreach ($m as $row) {
                $rows[] = array($row[1], $row[2], $row[3]);
            }
            return $rows;
        }

        private function dateHeader(string $html): string
        {
            preg_match('#<thead><tr><th>[^<]*</th><th>[^<]*</th><th>([^<]*)</th>#', $html, $m);
            return $m[1] ?? '';
        }

        /**
         * The bug this guards: with one open purchase order and no delivery date on it, the date
         * list is an empty string, and the old `if ($orders && $amounts && $dates)` threw away the
         * whole box - order number, link and quantity included.
         */
        public function testAnOrderWithNoDateStillGetsABox(): void
        {
            $html = $this->render(array(
                'buy_order_orders_id' => '77',
                'buy_order_orders' => '934',
                'buy_order_qty' => '1600.000',
                'buy_order_date' => '',
            ));

            self::assertSame(array(array('934', '1.600,00', '&ndash;')), $this->bodyRows($html),
                'a purchase order without a delivery date lost its row');
            self::assertStringContainsString("ordre.php?id=77", $html, 'the order link is gone');
        }

        /** An undated order among dated ones must not shift the other dates onto the wrong rows. */
        public function testADateIsMissingOnlyOnTheOrderItIsMissingFrom(): void
        {
            $html = $this->render(array(
                'buy_order_orders_id' => '77, 78, 79',
                'buy_order_orders' => '934, 1000, 1001',
                'buy_order_qty' => '1600.000, 2.000, 3.000',
                'buy_order_date' => '2026-12-15, , 2026-12-31',
            ), '1605,00');

            self::assertSame(array(
                array('934', '1.600,00', '15-12-2026'),
                array('1000', '2,00', '&ndash;'),
                array('1001', '3,00', '31-12-2026'),
            ), $this->bodyRows($html), 'the dates no longer line up with their orders');
        }

        /**
         * The guard no longer looks at the date list, so the date list is allowed to be absent
         * entirely. explode() on a null is a deprecation on PHP 8.3, hence the casts.
         */
        public function testAMissingDateListIsNotAnError(): void
        {
            $errors = array();
            set_error_handler(function ($severity, $message) use (&$errors) {
                $errors[] = $message;
                return true;
            });
            try {
                $html = $this->render(array(
                    'buy_order_orders_id' => '77',
                    'buy_order_orders' => '934',
                    'buy_order_qty' => '1.000',
                    'buy_order_date' => null,
                ), '1,00');
            } finally {
                restore_error_handler();
            }

            self::assertSame(array(), $errors, 'rendering a box without a date list warns');
            self::assertSame(array(array('934', '1,00', '&ndash;')), $this->bodyRows($html));
        }

        /** Every other box on the page keeps the plain "Dato" header (K6). */
        public function testTheDateHeaderDefaultsToDato(): void
        {
            $html = $this->render(array(
                'buy_order_orders_id' => '77',
                'buy_order_orders' => '934',
                'buy_order_qty' => '1.000',
                'buy_order_date' => '2026-12-15',
            ));

            self::assertSame('Dato', $this->dateHeader($html), 'the default date header changed');
        }

        /** The Indkoebsordre box asks for its own header, and gets it (K3). */
        public function testTheCallerCanNameTheDateColumn(): void
        {
            $html = $this->render(array(
                'buy_order_orders_id' => '77',
                'buy_order_orders' => '934',
                'buy_order_qty' => '1.000',
                'buy_order_date' => '2026-12-15',
            ), '1,00', '8407|Forventet lev. dato');

            self::assertSame('Forventet lev. dato', $this->dateHeader($html),
                'the caller-supplied date header is ignored');
        }

        /** An item with nothing on order is still a plain cell, not an empty hover box. */
        public function testNothingOnOrderRendersNoBox(): void
        {
            foreach (array('0,00', '', '0') as $value) {
                $html = $this->render(array(
                    'buy_order_orders_id' => null,
                    'buy_order_orders' => null,
                    'buy_order_qty' => null,
                    'buy_order_date' => null,
                ), $value);
                self::assertStringNotContainsString('hover-dropdown', $html,
                    "a value of '$value' should not open a box");
            }
        }

        /**
         * A row can reach renderColumn() with a quantity but no orders only if the SQL changes
         * shape; when it does, the box stays empty rather than warning on a null explode().
         */
        public function testAQuantityWithoutOrdersRendersAnEmptyBox(): void
        {
            $html = $this->render(array(
                'buy_order_orders_id' => null,
                'buy_order_orders' => null,
                'buy_order_qty' => null,
                'buy_order_date' => null,
            ), '5,00');

            self::assertStringContainsString('hover-dropdown', $html);
            self::assertSame(array(), $this->bodyRows($html));
        }

        // ------------------------------------------------------------------ the page's own wiring

        /**
         * Only the Indkoebsordre column passes a date header; the other three must not, or they
         * stop saying "Dato" (K6).
         *
         * @return array<string, array{string, string|null}>
         */
        public static function columnHeaders(): array
        {
            return array(
                'in_sales_offer' => array('sales_offer_orders_date', null),
                'in_sales_order' => array('sales_order_orders_date', null),
                'in_buy_proposal' => array('buy_proposal_orders_date', null),
                'in_buy_order' => array('buy_order_date', '8407|Forventet lev. dato'),
            );
        }

        #[DataProvider('columnHeaders')]
        public function testOnlyThePurchaseOrderColumnRenamesItsDateHeader(string $dateField, ?string $dateText): void
        {
            $src = self::source();
            $at = strpos($src, "'$dateField'");
            self::assertNotFalse($at, "no column renders $dateField any more");
            $call = substr($src, $at, strpos($src, ');', $at) - $at);

            if ($dateText === null) {
                self::assertStringNotContainsString('|', $call,
                    "$dateField's column now passes a date header, so its box stops saying Dato");
            } else {
                self::assertStringContainsString("'$dateText'", $call,
                    "$dateField's column no longer asks for the expected-delivery-date header");
            }
        }

        /**
         * The four buy_order lists are zipped by index, so they have to carry one and the same
         * ORDER BY. This is pinned on the source, not on a query result, because Postgres sorts
         * the group's input once and hands the same order to every aggregate in it: dropping the
         * clause from one of the four is invisible in a simple plan and only shows up once the
         * planner picks a different one. The other three boxes stay unordered (K6).
         */
        public function testAllFourBuyOrderListsCarryTheSameOrdering(): void
        {
            $src = self::source();
            $groups = array(
                'buy_order_qty' => 'ORDER BY levdate NULLS LAST, ordrenr_sort, linjeid)',
                'sales_offer_qty' => null,
                'sales_order_qty' => null,
                'buy_proposal_qty' => null,
            );
            foreach ($groups as $category => $ordering) {
                $at = strpos($src, "COALESCE(SUM(CASE WHEN category = '$category'");
                self::assertNotFalse($at, "the $category aggregates are gone from order_summary");
                $end = strpos($src, "\n\n", $at);
                if ($end === false || $end > strpos($src, '    FROM categorized_orders')) {
                    $end = strpos($src, '    FROM categorized_orders');
                }
                $block = substr($src, $at, $end - $at);

                self::assertSame(4, substr_count($block, 'STRING_AGG('),
                    "$category no longer builds exactly four lists, so renderColumn() cannot zip them");
                if ($ordering === null) {
                    self::assertSame(0, substr_count($block, 'ORDER BY'),
                        "$category's lists are now ordered, which changes a box this was not meant to touch");
                } else {
                    self::assertSame(4, substr_count($block, $ordering),
                        "the four $category lists no longer carry one identical ORDER BY, so they can drift apart");
                }
            }
        }

        /** Existing master/live translations must not override the new purchase-order wording. */
        public function testCachedVoucherTextsCannotReplaceTheDeliveryLabelsOrGuide(): void
        {
            $catalog = array();
            foreach (file(__DIR__ . '/../../../importfiler/tekster.csv', FILE_IGNORE_NEW_LINES) as $line) {
                $columns = explode("\t", $line);
                if (isset($columns[1]) && ctype_digit($columns[0])) {
                    $catalog[$columns[0]] = $columns[1];
                }
            }
            self::assertStringContainsString('Bilagsnummer %s', $catalog['5439']);
            self::assertStringContainsString('Kladden kan ikke bogføres', $catalog['5440']);
            $GLOBALS['ordrestatusCachedTexts'] = $catalog;
            try {
                preg_match("/'buy_order_date',\\s*'[^']*',\\s*'([^']+)'/", self::source(), $header);
                self::assertNotEmpty($header);
                $html = $this->render(array('buy_order_orders_id' => '77', 'buy_order_orders' => '934',
                    'buy_order_qty' => '1.000', 'buy_order_date' => '2026-12-15'), '1,00', $header[1]);
                self::assertSame('Forventet lev. dato', $this->dateHeader($html));
                foreach (array('openOrderData.php', 'selectOrder.php') as $file) {
                    $source = file_get_contents(__DIR__ . '/../../../kreditor/orderIncludes/' . $file);
                    preg_match("/findtekst\\('([0-9]+\\|Forventet lev\\. dato)'/", $source, $label);
                    self::assertNotEmpty($label);
                    self::assertSame('Forventet lev. dato', \OrdrestatusFixture\findtekst($label[1], 1));
                }
                preg_match("/findtekst\\('([0-9]+\\|Ordrestatus[^']*)'/", self::source(), $guide);
                self::assertNotEmpty($guide);
                $text = \OrdrestatusFixture\findtekst($guide[1], 1);
                self::assertStringContainsString('forventede leveringsdato', $text);
                self::assertStringNotContainsString('Kladden', $text);
            } finally {
                unset($GLOBALS['ordrestatusCachedTexts']);
            }
        }

        /** The two new texts have to exist in importfiler/tekster.csv or findtekst() returns "Tekst nr: n". */
        public function testTheNewTextsAreInTheImportFile(): void
        {
            $csv = file_get_contents(__DIR__ . '/../../../importfiler/tekster.csv');
            self::assertNotFalse($csv, 'could not read importfiler/tekster.csv');

            foreach (array('8407' => 'Forventet lev. dato', '8408' => 'forventede leveringsdato') as $id => $needle) {
                self::assertMatchesRegularExpression("/^$id\t.*" . preg_quote($needle, '/') . "/m", $csv,
                    "text $id is missing from importfiler/tekster.csv");
            }
            foreach (array('8407', '8408') as $id) {
                self::assertSame(1, preg_match_all("/^$id\t/m", $csv), "text $id is defined more than once");
            }
        }

        /** Text 886 is shared with sales orders, so the purchase order must have stopped using it. */
        public function testThePurchaseOrderNoLongerSharesText886(): void
        {
            foreach (array('openOrderData.php', 'selectOrder.php') as $file) {
                $src = file_get_contents(__DIR__ . '/../../../kreditor/orderIncludes/' . $file);
                self::assertNotFalse($src, "could not read kreditor/orderIncludes/$file");
                self::assertStringNotContainsString('findtekst(886', $src,
                    "$file still labels the delivery date with text 886, which sales orders share");
                self::assertStringContainsString("findtekst('8407|Forventet lev. dato',\$sprog_id)", $src,
                    "$file no longer labels the delivery date Forventet lev. dato");
            }
            $sales = file_get_contents(__DIR__ . '/../../../debitor/ordreliste.php');
            self::assertStringContainsString("findtekst('886|Dato for levering'", $sales,
                'the sales order list stopped using text 886, which this change was supposed to leave alone');
        }

        // ------------------------------------------------------------------ the SQL

        private function db(): \PDO
        {
            if (self::$db !== null) {
                return self::$db;
            }
            if (!extension_loaded('pdo_pgsql')) {
                self::markTestSkipped('pdo_pgsql is required for the order-summary SQL.');
            }
            $dsn = getenv('SALDI_CHAR_DSN');
            if (!$dsn) {
                self::markTestSkipped('set SALDI_CHAR_DSN, SALDI_CHAR_PGUSER and SALDI_CHAR_PGPASS to run the order-summary SQL.');
            }
            self::$db = new \PDO($dsn, getenv('SALDI_CHAR_PGUSER') ?: null, getenv('SALDI_CHAR_PGPASS') ?: null,
                array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));
            return self::$db;
        }

        /** The two CTEs, lifted verbatim, with a plain select on the end. */
        private function orderSummarySql(): string
        {
            $src = self::source();
            $at = strpos($src, 'categorized_orders AS (');
            self::assertNotFalse($at, 'the categorized_orders CTE is gone from ordrestatus.php');
            $end = strpos($src, "    GROUP BY vare_id\n)", $at);
            self::assertNotFalse($end, 'the order_summary CTE no longer groups by vare_id');

            return 'WITH ' . substr($src, $at, $end - $at) . "    GROUP BY vare_id\n)\n"
                . 'SELECT * FROM order_summary ORDER BY vare_id';
        }

        /**
         * @param array<int, array{int, int, int, string, string|null, string|null}> $ordrer id, ordrenr, status, art, ordredate, levdate
         * @param array<int, array{int, int, int, string, string}> $linjer id, ordre_id, vare_id, antal, leveret
         * @return array<int, array<string, string|null>> keyed by vare_id
         */
        private function summarise(array $ordrer, array $linjer): array
        {
            $db = $this->db();
            // TEMP tables only, and never a DROP: pg_temp comes first in the search path, so the
            // lifted SQL reads these and nothing here can reach a real ordrer/ordrelinjer even if
            // SALDI_CHAR_DSN points at a live tenant.
            $db->exec('CREATE TEMP TABLE IF NOT EXISTS ordrer (id int, ordrenr int, status int, art text, ordredate date, levdate date);
                CREATE TEMP TABLE IF NOT EXISTS ordrelinjer (id int, ordre_id int, vare_id int, antal numeric(15,3), leveret numeric(15,3))');
            $db->exec('TRUNCATE pg_temp.ordrer, pg_temp.ordrelinjer');
            $o = $db->prepare('INSERT INTO pg_temp.ordrer VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($ordrer as $row) {
                $o->execute($row);
            }
            $l = $db->prepare('INSERT INTO pg_temp.ordrelinjer VALUES (?, ?, ?, ?, ?)');
            foreach ($linjer as $row) {
                $l->execute($row);
            }
            $out = array();
            foreach ($db->query($this->orderSummarySql())->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $out[(int)$row['vare_id']] = $row;
            }
            return $out;
        }

        /** K1: the date in the box is the expected delivery date, not the order date. */
        public function testTheBuyOrderListCarriesTheDeliveryDate(): void
        {
            $rows = $this->summarise(
                array(array(77, 934, 1, 'KO', '2026-10-01', '2026-12-15')),
                array(array(1, 77, 4899, '1600.000', '0.000'))
            );

            self::assertSame('2026-12-15', $rows[4899]['buy_order_date'],
                'the purchase-order box is still dated by the order date');
            self::assertSame('934', $rows[4899]['buy_order_orders']);
            self::assertSame('1600.000', $rows[4899]['buy_order_qty']);
        }

        /** K6: the other three boxes keep the order date. */
        public function testTheOtherListsKeepTheOrderDate(): void
        {
            $rows = $this->summarise(
                array(
                    array(1, 10, 0, 'DO', '2026-10-01', '2026-12-15'),
                    array(2, 11, 1, 'DO', '2026-10-02', '2026-12-16'),
                    array(3, 12, 0, 'KO', '2026-10-03', '2026-12-17'),
                ),
                array(
                    array(1, 1, 4899, '1.000', '0.000'),
                    array(2, 2, 4899, '2.000', '0.000'),
                    array(3, 3, 4899, '3.000', '0.000'),
                )
            );

            self::assertSame('2026-10-01', $rows[4899]['sales_offer_orders_date']);
            self::assertSame('2026-10-02', $rows[4899]['sales_order_orders_date']);
            self::assertSame('2026-10-03', $rows[4899]['buy_proposal_orders_date']);
        }

        /**
         * K4: STRING_AGG drops NULLs, so without the COALESCE an undated order would be missing
         * from the date list only, and every later date would slide up onto the wrong order.
         */
        public function testAnUndatedOrderKeepsItsPlaceInTheDateList(): void
        {
            $rows = $this->summarise(
                array(
                    array(77, 934, 1, 'KO', '2026-10-01', '2026-11-01'),
                    array(78, 1000, 1, 'KO', '2026-10-02', null),
                    array(79, 1001, 2, 'KO', '2026-10-03', '2026-11-15'),
                ),
                array(
                    array(1, 77, 4899, '1.000', '0.000'),
                    array(2, 78, 4899, '2.000', '0.000'),
                    array(3, 79, 4899, '3.000', '0.000'),
                )
            );

            self::assertSame('934, 1001, 1000', $rows[4899]['buy_order_orders']);
            self::assertSame('2026-11-01, 2026-11-15, ', $rows[4899]['buy_order_date']);
            self::assertSame('1.000, 3.000, 2.000', $rows[4899]['buy_order_qty']);
            self::assertSame('77, 79, 78', $rows[4899]['buy_order_orders_id']);
        }

        /** K5: earliest expected delivery first, undated last. */
        public function testTheListIsSortedByDeliveryDateWithUndatedLast(): void
        {
            $rows = $this->summarise(
                array(
                    array(77, 934, 1, 'KO', '2026-10-01', '2026-11-15'),
                    array(78, 1000, 1, 'KO', '2026-10-02', null),
                    array(79, 1001, 1, 'KO', '2026-10-03', '2026-11-01'),
                ),
                array(
                    array(1, 77, 4899, '1.000', '0.000'),
                    array(2, 78, 4899, '2.000', '0.000'),
                    array(3, 79, 4899, '3.000', '0.000'),
                )
            );

            self::assertSame('1001, 934, 1000', $rows[4899]['buy_order_orders']);
            self::assertSame('2026-11-01, 2026-11-15, ', $rows[4899]['buy_order_date']);
        }

        /** K5: order numbers tie-break numerically, so 934 comes before 1000, not after. */
        public function testEqualDatesTieBreakOnTheOrderNumberNumerically(): void
        {
            $rows = $this->summarise(
                array(
                    array(78, 1000, 1, 'KO', '2026-10-01', '2026-11-01'),
                    array(77, 934, 1, 'KO', '2026-10-02', '2026-11-01'),
                ),
                array(
                    array(1, 78, 4899, '1.000', '0.000'),
                    array(2, 77, 4899, '2.000', '0.000'),
                )
            );

            self::assertSame('934, 1000', $rows[4899]['buy_order_orders'],
                'order numbers are being compared as text, so 1000 sorts before 934');
        }

        /**
         * Two lines of the same item on one order tie on every key but the line id. Without that
         * last key the four lists could sort those rows differently from one another and the box
         * would pair a quantity with the wrong order.
         */
        public function testTwoLinesOfOneItemStayAligned(): void
        {
            $rows = $this->summarise(
                array(array(77, 934, 1, 'KO', '2026-10-01', '2026-11-01')),
                array(
                    array(2, 77, 4899, '5.000', '0.000'),
                    array(1, 77, 4899, '7.000', '0.000'),
                )
            );

            self::assertSame('934, 934', $rows[4899]['buy_order_orders']);
            self::assertSame('7.000, 5.000', $rows[4899]['buy_order_qty'],
                'the quantity list no longer follows the line id, so it can drift from the others');
            self::assertSame('2026-11-01, 2026-11-01', $rows[4899]['buy_order_date']);
        }

        /** K1/K6: a partly received order is listed with what is left, at its expected date. */
        public function testAPartlyReceivedOrderShowsTheRemainder(): void
        {
            $rows = $this->summarise(
                array(array(77, 934, 2, 'KO', '2026-10-01', '2026-12-15')),
                array(array(1, 77, 4899, '10.000', '4.000'))
            );

            self::assertSame('6.000', $rows[4899]['buy_order_qty']);
            self::assertSame('6.000', $rows[4899]['in_buy_order']);
            self::assertSame('2026-12-15', $rows[4899]['buy_order_date']);
        }

        /** The scope is unchanged: purchase orders at status 1-2, proposals at 0, invoiced ones gone. */
        public function testTheScopeIsStillOpenPurchaseOrders(): void
        {
            $rows = $this->summarise(
                array(
                    array(77, 934, 0, 'KO', '2026-10-01', '2026-11-01'),
                    array(78, 935, 1, 'KO', '2026-10-01', '2026-11-02'),
                    array(79, 936, 2, 'KO', '2026-10-01', '2026-11-03'),
                    array(80, 937, 3, 'KO', '2026-10-01', '2026-11-04'),
                ),
                array(
                    array(1, 77, 4899, '1.000', '0.000'),
                    array(2, 78, 4899, '2.000', '0.000'),
                    array(3, 79, 4899, '3.000', '0.000'),
                    array(4, 80, 4899, '4.000', '0.000'),
                )
            );

            self::assertSame('935, 936', $rows[4899]['buy_order_orders']);
            self::assertSame('934', $rows[4899]['buy_proposal_orders']);
        }

        /** All four lists must be the same length, or renderColumn() pairs the wrong values. */
        public function testTheFourListsAlwaysHaveTheSameLength(): void
        {
            $rows = $this->summarise(
                array(
                    array(77, 934, 1, 'KO', '2026-10-01', null),
                    array(78, 1000, 1, 'KO', '2026-10-02', null),
                    array(79, 1001, 2, 'KO', '2026-10-03', '2026-11-15'),
                ),
                array(
                    array(1, 77, 4899, '1.000', '0.000'),
                    array(2, 78, 4899, '2.000', '0.000'),
                    array(3, 79, 4899, '3.000', '0.000'),
                )
            );

            $lengths = array();
            foreach (array('buy_order_orders', 'buy_order_orders_id', 'buy_order_qty', 'buy_order_date') as $field) {
                $lengths[$field] = count(explode(', ', $rows[4899][$field]));
            }
            self::assertSame(array('buy_order_orders' => 3, 'buy_order_orders_id' => 3,
                'buy_order_qty' => 3, 'buy_order_date' => 3), $lengths);
        }

        public static function tearDownAfterClass(): void
        {
            self::$db = null;
        }
    }
}
