<?php

// 20261013 MJ SST-833 Renders the batch window instead of reading it. The rest of this
//                  change was covered by source-string checks, which passed while the page
//                  itself was a 500 on PHP 8: debitor/batch.php wrote seven array keys as
//                  bare identifiers outside a string, and the first of them is reached on
//                  every open of the window. Reported by ZaynSaul on PR #789.
//
//                  Two halves. The first parses the file and fails if a bare key comes back
//                  anywhere in it - the whole class of defect, not just the seven. The second
//                  lifts the window's display half out of the page and runs it against a fake
//                  database, so an undefined constant, an undefined function or a lost column
//                  surfaces as a failure here rather than as a blank popup.
//
// strict_types is deliberately off: eval'd code inherits the calling file's setting, and the
// lifted legacy block passes ints where the stubs below take strings and back again.

namespace BatchWindowFixture {

    /** One query's worth of rows, handed back by db_fetch_array() one at a time. */
    final class Result
    {
        private array $rows;

        public function __construct(array $rows)
        {
            $this->rows = $rows;
        }

        public function next()
        {
            if ($this->rows === array()) {
                return false;
            }
            return array_shift($this->rows);
        }
    }

    /**
     * Routes each query to fixture rows by pattern. An unplanned query is an error rather than
     * an empty result, so a query the page gains or changes shows up as a failure.
     */
    final class Db
    {
        /** @var array<int, array{0: string, 1: array|callable}> */
        public static array $plan = array();
        /** @var array<int, string> */
        public static array $queries = array();
        /** @var array<int, string> */
        public static array $writes = array();

        public static function select(string $sql): Result
        {
            self::$queries[] = $sql;
            foreach (self::$plan as $entry) {
                if (preg_match($entry[0], $sql, $m)) {
                    $rows = is_callable($entry[1]) ? call_user_func($entry[1], $m) : $entry[1];
                    return new Result($rows);
                }
            }
            throw new \RuntimeException('no fixture matches query: ' . $sql);
        }
    }

    function db_select($sql, $context = null)
    {
        return Db::select((string)$sql);
    }

    function db_fetch_array($result)
    {
        return $result->next();
    }

    function db_modify($sql, $context = null)
    {
        Db::$writes[] = (string)$sql;
        return true;
    }

    /** The page's findtekst() needs a database; the default after the pipe is what it would return. */
    function findtekst($textId, $languageID)
    {
        $at = strpos((string)$textId, '|');
        return $at === false ? '#' . $textId : substr((string)$textId, $at + 1);
    }

    /** Mirrors includes/stdFunc/dkDecimal.php. */
    function dkdecimal($tal, $decimaler = null)
    {
        if (!isset($decimaler)) {
            $decimaler = 2;
        }
        return is_numeric($tal) ? number_format((float)$tal, $decimaler, ',', '.') : $tal;
    }

    /** Mirrors includes/std_func.php: nothing back for an empty date. */
    function dkdato($dato)
    {
        if ($dato) {
            list($year, $month, $day) = explode('-', $dato, 3);
            return sprintf('%02d-%02d-%s', (int)$day, (int)$month, $year);
        }
    }

    /** Mirrors includes/std_func.php. */
    function reducer($tal)
    {
        while ((strpos((string)$tal, '.') || strpos((string)$tal, ',')) && ($tal
            && (substr((string)$tal, -1, 1) == '0' || substr((string)$tal, -1, 1) == ',' || substr((string)$tal, -1, 1) == '.'))) {
            $tal = substr((string)$tal, 0, strlen((string)$tal) - 1);
        }
        return $tal;
    }

    /** Verbatim from includes/stdFunc/fefo.php. */
    function fefo_order_clause()
    {
        return "CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC, kobsdate ASC, id ASC";
    }
}

namespace {

    use PHPUnit\Framework\TestCase;

    final class BatchWindowRenderTest extends TestCase
    {
        private const BATCH = __DIR__ . '/../../../debitor/batch.php';

        /**
         * The warning floor this block already had, left alone per
         * doc/ai/convention_testing_error_visibility.md. Measured by running this same fixture
         * against master's batch.php with nothing but the seven keys quoted: 11 warnings there
         * against 9 here, the two missing ones being the string-offset writes PR #789 fixed.
         * $valg is only ever assigned inside the reservation loop, so a batch with no
         * reservation against it reads $valg undefined; $rest is an array interpolated into the
         * hidden field at the end. Neither line is touched by this change, and initialising
         * $valg would change what a saved form redisplays - not this PR's business.
         */
        private const LEGACY_WARNINGS = array(
            'Undefined variable $valg',
            'Trying to access array offset on null',
            'Array to string conversion',
        );

        private static function source(): string
        {
            $src = file_get_contents(self::BATCH);
            self::assertNotFalse($src, 'could not read debitor/batch.php');
            return $src;
        }

        // ------------------------------------------------------------------ the whole class of defect

        /**
         * `$row[art]` outside a string is an undefined constant on PHP 8 and throws. Inside a
         * double-quoted string the same text is legal simple interpolation, so this walks the
         * token stream and only counts the ones that are really subscripts.
         *
         * @return array<int, string> "line: $var[key]" for each one found
         */
        private static function bareArrayKeys(string $src): array
        {
            $tokens = token_get_all($src);
            $n = count($tokens);
            $hits = array();
            $inString = false;
            for ($i = 0; $i < $n; $i++) {
                if ($tokens[$i] === '"') {
                    $inString = !$inString;
                    continue;
                }
                if (is_array($tokens[$i]) && $tokens[$i][0] === T_START_HEREDOC) {
                    $inString = true;
                    continue;
                }
                if (is_array($tokens[$i]) && $tokens[$i][0] === T_END_HEREDOC) {
                    $inString = false;
                    continue;
                }
                if ($inString || $tokens[$i] !== '[') {
                    continue;
                }
                $prev = $i - 1;
                while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
                    $prev--;
                }
                if ($prev < 0) {
                    continue;
                }
                $isSubscript = (is_array($tokens[$prev]) && $tokens[$prev][0] === T_VARIABLE)
                    || $tokens[$prev] === ']';
                if (!$isSubscript) {
                    continue;
                }
                $key = $i + 1;
                while ($key < $n && is_array($tokens[$key]) && $tokens[$key][0] === T_WHITESPACE) {
                    $key++;
                }
                if (!is_array($tokens[$key]) || $tokens[$key][0] !== T_STRING) {
                    continue;
                }
                $close = $key + 1;
                while ($close < $n && is_array($tokens[$close]) && $tokens[$close][0] === T_WHITESPACE) {
                    $close++;
                }
                if ($tokens[$close] !== ']') {
                    continue; // a constant expression or a call, not a bare key
                }
                $hits[] = $tokens[$key][2] . ': $'
                    . (is_array($tokens[$prev]) ? ltrim($tokens[$prev][1], '$') : '?')
                    . '[' . $tokens[$key][1] . ']';
            }
            return $hits;
        }

        public function testNoArrayKeyIsWrittenAsABareIdentifier(): void
        {
            self::assertSame(array(), self::bareArrayKeys(self::source()),
                'these array keys are undefined constants on PHP 8 and throw where they are read');
        }

        /** The detector has to actually detect, or the test above passes for the wrong reason. */
        public function testTheDetectorFindsABareKeyAndIgnoresInterpolation(): void
        {
            $withOne = '<?php $a = $row[art]; $b = "id = $row[ordre_id]";';
            self::assertSame(array('1: $row[art]'), self::bareArrayKeys($withOne),
                'the detector misses a bare key, or mistakes string interpolation for one');

            $clean = '<?php $a = $row[\'art\']; $b = "id = $row[ordre_id]"; $c = $row[FOO] ?? 1;';
            self::assertSame(array('1: $row[FOO]'), self::bareArrayKeys($clean));
        }

        // ------------------------------------------------------------------ rendering the window

        /**
         * The window's display half, lifted from `$leveres=0;` to the end of the file and
         * declared in the fixture namespace so it calls the stubs beside it.
         *
         * @param array<int, array{0: string, 1: array|callable}> $plan
         * @return array{html: string, warnings: array<int, string>, queries: array<int, string>}
         */
        private function renderWindow(array $plan, int $linjeId = 4711): array
        {
            $src = self::source();
            $at = strpos($src, "\n\$leveres=0;\n");
            self::assertNotFalse($at, 'the display half of batch.php no longer starts at $leveres=0');
            $end = strrpos($src, '?>');
            self::assertNotFalse($end, 'batch.php no longer ends on a closing tag');
            $block = substr($src, $at, $end - $at);

            \BatchWindowFixture\Db::$plan = $plan;
            \BatchWindowFixture\Db::$queries = array();
            \BatchWindowFixture\Db::$writes = array();

            $warnings = array();
            set_error_handler(function ($severity, $message) use (&$warnings) {
                $warnings[] = $message;
                return true;
            });
            $linje_id = $linjeId;
            $sprog_id = 1;
            ob_start();
            try {
                eval('namespace BatchWindowFixture; ' . $block);
            } finally {
                $html = ob_get_clean();
                restore_error_handler();
            }

            return array('html' => $html, 'warnings' => $warnings,
                'queries' => \BatchWindowFixture\Db::$queries, 'art' => $art ?? null);
        }

        /**
         * One sales line on an open sales order, two batches in stock - one with batch details
         * and one from before the feature was switched on - and two reservations against
         * purchase orders that have not been received, one of them a credit note.
         *
         * @return array<int, array{0: string, 1: array|callable}>
         */
        private function fullFixture(): array
        {
            return array(
                array('/select \* from ordrelinjer where id = /', array(
                    array('antal' => '5.000', 'leveres' => '0.000', 'posnr' => 1,
                        'vare_id' => 88, 'varenr' => 'VARE-88', 'serienr' => '', 'ordre_id' => 500),
                )),
                array('/select status, art, konto_id, ref from ordrer/', array(
                    array('status' => 1, 'art' => 'DO', 'konto_id' => 12, 'ref' => 'Sælger'),
                )),
                array('/select afd from ansatte/', array(array('afd' => 'A1'))),
                array('/select kodenr from grupper/', array(array('kodenr' => '3'))),
                array('/select \* from batch_salg where linje_id/', array()),
                array('/select \* from batch_kob where vare_id/', array(
                    array('id' => 901, 'kobsdate' => '2026-09-01', 'batch_no' => 'B-7742',
                        'due_date' => '2027-03-31', 'rest' => '4.000', 'pris' => '12.500', 'ordre_id' => 700),
                    array('id' => 902, 'kobsdate' => '2025-01-15', 'batch_no' => null,
                        'due_date' => null, 'rest' => '2.000', 'pris' => '11.000', 'ordre_id' => 701),
                )),
                array('/select ordrenr from ordrer where id=(\d+)/', function ($m) {
                    return array(array('ordrenr' => $m[1] == 700 ? '1234' : '1235'));
                }),
                array('/select \* from reservation where batch_kob_id=/', array()),
                // the existence check that prints the "Varer på vej" heading, then the loop
                array("/select \* from reservation where batch_salg_id='0'/", array(
                    array('linje_id' => 61, 'antal' => '3.000', 'batch_kob_id' => 0),
                )),
                array('/select \* from reservation where batch_salg_id=0 and vare_id/', array(
                    array('linje_id' => 61, 'antal' => '3.000', 'batch_kob_id' => 0),
                    array('linje_id' => 62, 'antal' => '1.000', 'batch_kob_id' => 0),
                )),
                array('/select ordre_id from ordrelinjer where id=(\d+)/', function ($m) {
                    return array(array('ordre_id' => $m[1] == 61 ? 800 : 801));
                }),
                array('/select art, ordrenr, status from ordrer where id=(\d+)/', function ($m) {
                    return $m[1] == 800
                        ? array(array('art' => 'KO', 'ordrenr' => '5001', 'status' => 1))
                        : array(array('art' => 'DK', 'ordrenr' => '5002', 'status' => 1));
                }),
                array('/select \* from reservation where linje_id=\d+ and batch_salg_id != 0/', array()),
            );
        }

        /**
         * The batch table alone - from its own header row to the end of its tbody - so the
         * buttons table printed after it, which has a different shape, is not parsed as part
         * of it.
         */
        private function batchTable(string $html): string
        {
            $at = strpos($html, '<tr><td><b>K&oslash;bsordre nr.');
            self::assertNotFalse($at, 'the batch table has no header row');
            $end = strpos($html, '</tbody></table>', $at);
            self::assertNotFalse($end, 'the batch table is never closed');
            return substr($html, $at, $end - $at);
        }

        /**
         * @return array<int, array{cells: array<int, string>, width: int}> one entry per <tr>,
         *     with the cell texts and the row's effective width once colspans are counted
         */
        private function tableRows(string $html): array
        {
            preg_match_all('#<tr>(.*?)(?=<tr>|$)#s', $this->batchTable($html), $trs);
            $rows = array();
            foreach ($trs[1] as $tr) {
                preg_match_all('#<td([^>]*)>(.*?)(?=<td|</tr>|$)#s', $tr, $tds, PREG_SET_ORDER);
                $cells = array();
                $width = 0;
                foreach ($tds as $td) {
                    $cells[] = trim(strip_tags(preg_replace('#<input[^>]*>#', '', $td[2])));
                    $width += preg_match('#colspan=(\d+)#', $td[1], $c) ? (int)$c[1] : 1;
                }
                $rows[] = array('cells' => $cells, 'width' => $width);
            }
            return $rows;
        }

        /**
         * @return array<int, array<int, string>> just the cell texts, row by row
         */
        private function cellRows(string $html): array
        {
            $rows = array();
            foreach ($this->tableRows($html) as $row) {
                $rows[] = $row['cells'];
            }
            return $rows;
        }

        /**
         * The test ZaynSaul asked for, as an automated one: the window opens. Before the seven
         * keys were quoted this threw Error: Undefined constant "art" and printed nothing.
         */
        public function testTheWindowRendersAtAll(): void
        {
            $out = $this->renderWindow($this->fullFixture());

            self::assertNotSame('', $out['html'], 'the batch window printed nothing');
            self::assertStringContainsString('Varenr: VARE-88', $out['html'],
                'the window did not get as far as its own heading');
            self::assertStringContainsString('</tbody></table>', $out['html'],
                'the window stopped before the end of its markup');
        }

        /** Nothing new may warn; the two legacy ones are master's and stay. */
        public function testRenderingAddsNoNewWarnings(): void
        {
            $out = $this->renderWindow($this->fullFixture());

            $unexpected = array();
            foreach ($out['warnings'] as $warning) {
                $known = false;
                foreach (self::LEGACY_WARNINGS as $legacy) {
                    if (strpos($warning, $legacy) !== false) {
                        $known = true;
                    }
                }
                if (!$known) {
                    $unexpected[] = $warning;
                }
            }
            self::assertSame(array(), $unexpected, 'rendering the window emits a new warning');
        }

        /** What the change exists for: the batch number and expiry date of each batch in stock. */
        public function testEachBatchInStockShowsItsNumberAndExpiry(): void
        {
            $out = $this->renderWindow($this->fullFixture());
            $rows = $this->cellRows($out['html']);

            $header = null;
            $batch = null;
            foreach ($rows as $row) {
                if (in_array('Batchnr.', $row, true)) {
                    $header = $row;
                }
                if (isset($row[1]) && $row[1] === 'B-7742') {
                    $batch = $row;
                }
            }

            self::assertNotNull($header, 'the batch table has no Batchnr. column');
            self::assertSame(1, array_search('Batchnr.', $header, true),
                'the batch number is no longer the second column');
            self::assertSame('Udløbsdato', $header[2] ?? null, 'the expiry column is not beside it');
            self::assertNotNull($batch, 'the batch number is not printed on the batch row');
            self::assertSame('31-03-2027', $batch[2], 'the expiry date is not printed beside it');
            self::assertSame('1234', $batch[0], 'the purchase order number moved');
            self::assertSame('4', $batch[3], 'the remaining quantity moved');
        }

        /**
         * Stock received before Batch-kontrol was switched on has neither, and those cells stay
         * empty rather than showing a placeholder that would read as a value. This is the thing
         * the PR description warns the customer about, so it is worth pinning.
         */
        public function testStockFromBeforeTheFeatureShowsEmptyCells(): void
        {
            $out = $this->renderWindow($this->fullFixture());

            $older = null;
            foreach ($this->cellRows($out['html']) as $row) {
                if (isset($row[0]) && $row[0] === '1235') {
                    $older = $row;
                }
            }

            self::assertNotNull($older, 'the batch without details is no longer listed');
            self::assertSame('', $older[1], 'a placeholder appeared where there is no batch number');
            self::assertSame('', $older[2], 'a placeholder appeared where there is no expiry date');
            self::assertSame('2', $older[3], 'the quantity is wrong on the row without details');
        }

        /** The batch number is free text typed on the purchase order. */
        public function testTheBatchNumberIsEscaped(): void
        {
            $fixture = $this->fullFixture();
            $fixture[5][1][0]['batch_no'] = 'A<b>"&';
            $out = $this->renderWindow($fixture);

            self::assertStringContainsString('A&lt;b&gt;&quot;&amp;', $out['html'],
                'the batch number is printed unescaped');
            self::assertStringNotContainsString('A<b>', $out['html']);
        }

        /**
         * The defect CodeRabbit found, as a rendered row rather than a source check: an
         * unreceived credit note is labelled "(KN)" and an ordinary purchase order is not.
         */
        public function testAnUnreceivedCreditNoteIsLabelled(): void
        {
            $out = $this->renderWindow($this->fullFixture());

            $labels = array();
            foreach ($this->cellRows($out['html']) as $row) {
                if (isset($row[0]) && preg_match('/^500[12]/', $row[0])) {
                    $labels[] = $row[0];
                }
            }

            self::assertSame(array('5001', '5002 (KN)'), $labels,
                'the credit note is not labelled, or the ordinary order is');
        }

        /** $art carries the sales order's own type into the hidden field; it must survive the loop. */
        public function testTheOrderTypeIsNotCorruptedByTheIncomingRows(): void
        {
            $out = $this->renderWindow($this->fullFixture());

            self::assertSame('DO', $out['art'],
                'the order type was overwritten while listing the incoming orders');
            self::assertStringContainsString("name=art value='DO'", $out['html'],
                'the hidden field posts back something other than the order type');

            // Writing into $art by offset is what corrupted it, and PHP says so out loud.
            // On master with only the key fix this same fixture ends with $art = 'DO KD' and
            // two of these; here there are none.
            foreach ($out['warnings'] as $warning) {
                self::assertStringNotContainsString('first byte will be assigned to the string offset', $warning,
                    'something is being written into a string by offset again');
            }
        }

        /**
         * Both sections print into one table, so every row has to carry the same number of
         * cells or the batch columns slide out of line.
         */
        public function testBothSectionsLineUpOnFiveColumns(): void
        {
            $out = $this->renderWindow($this->fullFixture());

            $widths = array();
            foreach ($this->tableRows($out['html']) as $row) {
                $widths[$row['width']] = true;
            }
            self::assertSame(array(5), array_keys($widths),
                'a row in the batch table does not span the same five columns as the others');
        }

        /** A sold line reduces what is left to pick; the window has to still render. */
        public function testAPartlySoldLineStillRenders(): void
        {
            $fixture = $this->fullFixture();
            $fixture[4][1] = array(array('antal' => '2.000'));
            $out = $this->renderWindow($fixture);

            self::assertStringContainsString('Batchnr.', $out['html']);
            self::assertStringContainsString("name=antal value='3'", $out['html'],
                'the quantity left to pick is no longer posted back');
        }

        /**
         * With nothing in stock the window still has to come up and say so, rather than
         * printing a header row over an empty table.
         */
        public function testNothingInStockStillRendersTheWindow(): void
        {
            $fixture = $this->fullFixture();
            $fixture[5][1] = array();
            $fixture[8][1] = array();
            $fixture[9][1] = array();
            $out = $this->renderWindow($fixture);

            self::assertStringContainsString('Batchnr.', $out['html']);
            self::assertStringContainsString('indk&oslash;bsordre', $out['html'],
                'the window no longer explains that there is no batch to choose from');
        }
    }
}
