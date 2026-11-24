<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Bulk invoicing (debitor/massefakt.php?valg=ordrer) invoices ten orders per request and sends the browser on to
 * the next batch (testlab round 1, 2026-11-05).
 *
 * On test_33 the page died with HTTP 500 as soon as ten orders were invoiced: the redirect to the next batch was
 * built with explode($rest_id), which PHP 8 refuses with an ArgumentCountError. Fixing only that call would still
 * not finish the run: the redirect left out valg, so the next request found no orders and printed only the first
 * ten invoices. The orders that could not be delivered came back as one string at index 0 of $rest_id, while the
 * last batch reads them from index 1.
 *
 * The page runs in a child process (it calls exit), from a directory whose includes/ holds doubles: connect.php
 * answers the order queries from a list of open order ids and records every query; ordrefunc.php and
 * genberegn.php record calls instead of posting. std_func.php is the real one.
 *
 * With Delfakturer on (grupper MFAKT box2), delfakturer() splits an order with more ordered than in stock: the
 * remainder goes to a new order with the same order number. Its line left out rabatart, procent and omvbet, so a
 * discount of 20 kr per unit was invoiced as 20 % on the remainder (audit finding sales-F3, the bulk copy of Del ordre).
 * $GLOBALS['__split'] gives the double that one order, its lines and its stock.
 *
 * The split ended in two db_modify() calls without the second argument, which the real db_modify() and db_select()
 * (includes/db_query.php) require: PHP 8 stopped the page with an ArgumentCountError and rolled the split back
 * (audit finding p1-sales-F2). The doubles require it too.
 *
 * History:
 * 20261105 CL/LH Created.
 * 20261117 CL/ASR delfakturer(): the remainder line keeps rabatart, procent and omvbet.
 * 20261119 CL/ASR The db_select() and db_modify() doubles require their second argument, as the real ones do.
 *                 The split test checks both orders' totals.
 *                 The split double can give each item its own stock, and a split line at a lower VAT rate is checked.
 *                 A split with negative stock, and a negative line, are checked.
 */
final class BulkInvoiceBatchTest extends TestCase
{
    private const DATABASE = <<<'PHP'
<?php
function db_escape_string($s) { return addslashes((string) $s); }
function db_select($q, $spor) {
	$GLOBALS['__queries'][] = $q;
	$year = date('Y');
	$split = $GLOBALS['__split'] ?? null;
	if (strpos($q, "art='MFAKT'") !== false) return new ArrayIterator([['box2' => $split ? 'on' : '', 'box3' => '0']]);
	if (strpos($q, "art='RA'") !== false) return new ArrayIterator([['box1' => '01', 'box2' => $year, 'box3' => '12', 'box4' => $year]]);
	if ($split) {
		if (preg_match("~^select \\* from ordrelinjer where ordre_id = '(\\d+)'~", $q, $m) && $m[1] === $split['order']['id']) return new ArrayIterator($split['lines']);
		if (strpos($q, 'select varer.beholdning as beholdning') === 0) {
			$stock = $split['beholdning'];
			if (is_array($stock)) $stock = preg_match('~varer\.id=(\d+)~', $q, $m) ? ($stock[$m[1]] ?? '0') : '0'; # per item: vare_id => stock
			return new ArrayIterator([['beholdning' => $stock, 'lagervare' => 'on']]);
		}
		if (preg_match("~^select \\* from ordrer where id = '(\\d+)'~", $q, $m) && $m[1] === $split['order']['id']) return new ArrayIterator([$split['order']]);
		if (strpos($q, 'select max(id) as id from ordrer') === 0) return new ArrayIterator([['id' => '999']]);
	}
	if (preg_match("~from ordrer where \(status='1' or status='2'\) and art='DO' and id > '(\d+)'~", $q, $m)) {
		return new ArrayIterator(array_map(fn ($id) => ['id' => (string) $id], array_values(array_filter($GLOBALS['__orders'], fn ($id) => $id > (int) $m[1]))));
	}
	if (strpos($q, 'select count(*) as antal from ordrer') !== false) return new ArrayIterator([['antal' => (string) count($GLOBALS['__orders'])]]);
	return new ArrayIterator([]);
}
function db_fetch_array($r) {
	if (!$r instanceof ArrayIterator || !$r->valid()) return false;
	$row = $r->current();
	$r->next();
	return $row;
}
function db_modify($q, $spor) { $GLOBALS['__queries'][] = $q; return true; }
function transaktion($action) { }
function findtekst($id, $sprog_id = 1) { return ''; }
PHP;

    private const ONLINE = "<?php\n\$regnaar = 1;\n\$sprog_id = 1;\n\$top_bund = '';\n";

    private const ORDREFUNC = <<<'PHP'
<?php
function momsupdat($id) { $GLOBALS['__calls'][] = "momsupdat $id"; }
function levering($id, $hurtigfakt, $genfakt, $webservice = false) { $GLOBALS['__calls'][] = "levering $id"; return 'OK'; }
function bogfor($id, $webservice = false, $genfakt = false, $callerOwnsTransaction = false) { $GLOBALS['__calls'][] = "bogfor $id"; return 'OK'; }
PHP;

    private const GENBEREGN = "<?php\nfunction genberegn(\$regnaar) { \$GLOBALS['__calls'][] = 'genberegn'; }\n";

    private string $fixture;

    protected function setUp(): void
    {
        $this->fixture = sys_get_temp_dir() . '/saldi-massefakt-' . bin2hex(random_bytes(8));
        mkdir($this->fixture . '/debitor', 0700, true);
        mkdir($this->fixture . '/includes', 0700);
        file_put_contents($this->fixture . '/includes/connect.php', self::DATABASE);
        file_put_contents($this->fixture . '/includes/online.php', self::ONLINE);
        file_put_contents($this->fixture . '/includes/std_func.php', '<?php require_once ' . var_export(dirname(__DIR__, 3) . '/includes/std_func.php', true) . ';');
        file_put_contents($this->fixture . '/includes/ordrefunc.php', self::ORDREFUNC);
        file_put_contents($this->fixture . '/includes/genberegn.php', self::GENBEREGN);
    }

    protected function tearDown(): void
    {
        foreach (array_merge(glob($this->fixture . '/sess_*'), glob($this->fixture . '/includes/*.php'), [$this->fixture . '/result.json']) as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->fixture . '/includes');
        rmdir($this->fixture . '/debitor');
        rmdir($this->fixture);
    }

    /**
     * One request to massefakt.php with the given open orders.
     *
     * @param int[] $orders open order ids
     * @param array<string, string> $get
     * @param array<string, mixed>|null $split an order delfakturer() splits: order (its ordrer row), lines, beholdning
     * @return array{html: string, queries: string[], calls: string[]}
     */
    private function request(array $orders, array $get, ?array $split = null): array
    {
        $result = $this->fixture . '/result.json';
        $script = '$GLOBALS["__orders"] = ' . var_export($orders, true) . ';'
            . '$GLOBALS["__split"] = ' . var_export($split, true) . ';'
            . '$GLOBALS["__queries"] = []; $GLOBALS["__calls"] = [];'
            . 'register_shutdown_function(static function () { file_put_contents(' . var_export($result, true)
            . ', json_encode(["queries" => $GLOBALS["__queries"], "calls" => $GLOBALS["__calls"]])); });'
            . '$_GET = ' . var_export($get, true) . ';'
            . 'require ' . var_export(dirname(__DIR__, 3) . '/debitor/massefakt.php', true) . ';';
        $process = proc_open(
            [PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'log_errors=0', '-d', 'session.save_path=' . $this->fixture, '-r', $script],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->fixture . '/debitor' // the page includes ../includes/...
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $html = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        // The legacy page warns a lot (undefined $gl_id, $y, ...); a failure shows only what is worse than a warning.
        $severe = implode("\n", preg_grep('/^(Warning|Deprecated|Notice): /', explode("\n", trim($errors)), PREG_GREP_INVERT));
        self::assertSame(0, proc_close($process), $severe);
        self::assertStringNotContainsString('Fatal error', $errors, $severe);
        $recorded = json_decode((string) file_get_contents($result), true);
        unlink($result);
        return ['html' => $html, 'queries' => $recorded['queries'], 'calls' => $recorded['calls']];
    }

    /** @return array<string, string>|null the query of the redirect to the next batch, if the page sent one */
    private static function nextBatch(string $html): ?array
    {
        if (!preg_match('~<meta http-equiv="refresh" content="0;URL=massefakt\.php\?([^"]*)">~', $html, $match)) {
            return null;
        }
        parse_str(html_entity_decode($match[1]), $get);
        return $get;
    }

    /**
     * Requests massefakt.php the way the browser does, following each redirect to the next batch.
     *
     * @param int[] $orders open order ids
     * @param array<string, string> $get the first request
     * @return array<int, array{html: string, queries: string[], calls: string[]}>
     */
    private function followBatches(array $orders, array $get): array
    {
        $responses = [];
        while ($get !== null) {
            self::assertLessThan(count($orders) + 2, count($responses), 'the batches come to an end');
            $responses[] = $response = $this->request($orders, $get);
            $get = self::nextBatch($response['html']);
        }
        return $responses;
    }

    /** @param int[] $ids */
    private static function invoiced(array $ids): array
    {
        return array_merge(...array_map(fn (int $id): array => ["momsupdat $id", "levering $id", "bogfor $id"], $ids));
    }

    public function test_the_tenth_order_sends_the_browser_on_to_the_next_batch(): void
    {
        $first = $this->request(range(101, 111), ['valg' => 'ordrer']);

        self::assertSame(self::invoiced(range(101, 110)), $first['calls'], 'ten orders per batch, then the redirect');
        self::assertSame(
            ['valg' => 'ordrer', 'ordre_id' => '110', 'ordre_antal' => '10', 'rest' => '0', 'rest_id' => '', 'skriv' => implode(',', range(101, 110))],
            self::nextBatch($first['html'])
        );
    }

    public function test_every_order_is_invoiced_once_and_all_invoices_are_printed_at_the_end(): void
    {
        $responses = $this->followBatches(range(101, 125), ['valg' => 'ordrer']);

        self::assertCount(3, $responses, 'two full batches and the five orders left');
        $calls = array_merge(...array_column($responses, 'calls'));
        self::assertSame(array_merge(self::invoiced(range(101, 125)), ['genberegn']), $calls);
        self::assertStringContainsString(
            '<meta http-equiv="refresh" content="0;URL=../debitor/formularprint.php?id=-1&skriv=' . implode(',', range(101, 125)) . '&formular=4,3&ordre_antal=25">',
            $responses[2]['html']
        );
    }

    /** The undeliverable orders a batch sends on are the ones the last batch reserves stock for. */
    public function test_orders_that_cannot_be_delivered_reach_the_last_batch(): void
    {
        $responses = $this->followBatches(range(101, 112), ['valg' => 'ordrer', 'ordre_id' => '100', 'rest' => '2', 'rest_id' => '7,9']);

        self::assertCount(2, $responses);
        self::assertSame('7,9', self::nextBatch($responses[0]['html'])['rest_id'] ?? null, 'the redirect carries the list on');
        $lineQueries = array_values(array_filter($responses[1]['queries'], fn (string $q): bool => strpos($q, 'select * from ordrelinjer where ordre_id=') === 0));
        self::assertSame(["select * from ordrelinjer where ordre_id='7'", "select * from ordrelinjer where ordre_id='9'"], $lineQueries);
    }

    public function test_a_split_order_keeps_the_discount_in_kr_on_the_remainder_line(): void
    {
        $split = [
            'order' => ['id' => '101', 'ordrenr' => '7', 'konto_id' => '5', 'kontonr' => '91001', 'firmanavn' => 'Kunde', 'art' => 'DO',
                        'ordredate' => date('Y-m-d'), 'momssats' => '25.000', 'status' => '1', 'valuta' => 'DKK'],
            'lines' => [['id' => '501', 'posnr' => '1', 'varenr' => 'V1', 'vare_id' => '9', 'beskrivelse' => 'Vare', 'enhed' => 'stk',
                         'antal' => '2.000', 'pris' => '125.010', 'rabat' => '20.000', 'rabatart' => 'amount', 'procent' => '100.000',
                         'omvbet' => 'on', 'serienr' => '', 'momsfri' => '', 'samlevare' => '', 'kostpris' => '50.000', 'momssats' => '25.000']],
            'beholdning' => '1',
        ];

        $response = $this->request([101], ['valg' => 'ordrer'], $split);

        self::assertContains("update ordrelinjer set antal=1 where id = '501'", $response['queries'], 'the order keeps the unit in stock');
        $inserts = array_values(array_filter($response['queries'], fn (string $q): bool => strpos($q, 'insert into ordrelinjer (ordre_id, posnr, varenr') === 0));
        self::assertCount(1, $inserts, 'the remainder order gets the other unit');
        $line = self::insertedRow($inserts[0]);
        self::assertSame('999', $line['ordre_id']);
        self::assertSame('1', $line['antal']);
        self::assertSame('20', $line['rabat']);
        self::assertSame('amount', $line['rabatart'] ?? null, 'the discount stays 20 kr per unit (it became 20 %)');
        self::assertSame('100', $line['procent'] ?? null, 'invoiced at 100 %');
        self::assertSame('on', $line['omvbet'] ?? null, 'the line stays reverse charge');
        // Both units are 125,01 less 20 kr = 105,01, reverse charge: no VAT (the totals were 100,008 + 25,002 each)
        self::assertContains("update ordrer set tidspkt = '',hvem = '', moms='0', sum='105.01' where id ='101'", $response['queries'],
            'the order keeps 1 x 105,01 with no VAT');
        self::assertContains("update ordrer set tidspkt = '',hvem = '', moms='0', sum='105.01' where id ='999'", $response['queries'],
            'the remainder order is 1 x 105,01 with no VAT');
    }

    /**
     * A line at a lower VAT rate of its own (12,5 % on an order at 25 %) carries that rate in the split totals, as
     * momsupdat() and the order page count it: momsupdat() keeps the header of such an order, so the totals are what is
     * invoiced. Item 9, 2 x 100,00 with 1 in stock, stays 1 on the order and 1 on the remainder; item 10, 1 x 80,00 at
     * 12,5 %, stays on the order. The order is 180,00 + 35,00 VAT (it was charged 25 % on both lines, 45,00).
     */
    public function test_a_split_line_at_a_lower_vat_rate_keeps_its_rate_in_the_totals(): void
    {
        $line = ['posnr' => '1', 'beskrivelse' => 'Vare', 'enhed' => 'stk', 'rabat' => '0.000', 'rabatart' => '', 'procent' => '100.000',
                 'omvbet' => '', 'serienr' => '', 'momsfri' => '', 'samlevare' => '', 'kostpris' => '40.000'];
        $split = [
            'order' => ['id' => '101', 'ordrenr' => '7', 'konto_id' => '5', 'kontonr' => '91001', 'firmanavn' => 'Kunde', 'art' => 'DO',
                        'ordredate' => date('Y-m-d'), 'momssats' => '25.000', 'status' => '1', 'valuta' => 'DKK'],
            'lines' => [
                ['id' => '501', 'varenr' => 'V9', 'vare_id' => '9', 'antal' => '2.000', 'pris' => '100.000', 'momssats' => '25.000'] + $line,
                ['id' => '502', 'varenr' => 'V10', 'vare_id' => '10', 'antal' => '1.000', 'pris' => '80.000', 'momssats' => '12.500', 'posnr' => '2'] + $line,
            ],
            'beholdning' => ['9' => '1', '10' => '100'],
        ];

        $response = $this->request([101], ['valg' => 'ordrer'], $split);

        self::assertContains("update ordrelinjer set antal=1 where id = '501'", $response['queries'], 'the order keeps the unit of item 9 in stock');
        self::assertContains("update ordrelinjer set antal=1 where id = '502'", $response['queries'], 'and item 10');
        self::assertContains("update ordrer set tidspkt = '',hvem = '', moms='35', sum='180' where id ='101'", $response['queries'],
            'the order is 100,00 at 25 % + 80,00 at 12,5 %');
        self::assertContains("update ordrer set tidspkt = '',hvem = '', moms='25', sum='100' where id ='999'", $response['queries'],
            'the remainder is 100,00 at 25 %');
    }

    /** The order delfakturer() splits, with item 9 (100,00) and item 10 (80,00) at $antal9 and $antal10 and the stock given. */
    private static function splitOf(string $antal9, string $antal10, array $beholdning): array
    {
        $line = ['posnr' => '1', 'beskrivelse' => 'Vare', 'enhed' => 'stk', 'rabat' => '0.000', 'rabatart' => '', 'procent' => '100.000',
                 'omvbet' => '', 'serienr' => '', 'momsfri' => '', 'samlevare' => '', 'kostpris' => '40.000', 'momssats' => '25.000'];
        return [
            'order' => ['id' => '101', 'ordrenr' => '7', 'konto_id' => '5', 'kontonr' => '91001', 'firmanavn' => 'Kunde', 'art' => 'DO',
                        'ordredate' => date('Y-m-d'), 'momssats' => '25.000', 'status' => '1', 'valuta' => 'DKK'],
            'lines' => [
                ['id' => '501', 'varenr' => 'V9', 'vare_id' => '9', 'antal' => $antal9, 'pris' => '100.000'] + $line,
                ['id' => '502', 'varenr' => 'V10', 'vare_id' => '10', 'antal' => $antal10, 'pris' => '80.000', 'posnr' => '2'] + $line,
            ],
            'beholdning' => $beholdning,
        ];
    }

    /** @return list<array<string, string>> the lines delfakturer() wrote on the remainder order */
    private static function remainderLines(array $queries): array
    {
        $inserts = array_filter($queries, fn (string $q): bool => strpos($q, 'insert into ordrelinjer') === 0);
        return array_values(array_map([self::class, 'insertedRow'], $inserts));
    }

    /**
     * Item 9 is at -1 in stock (an earlier run invoiced a remainder without stock), item 10 has 100. The order keeps
     * none of item 9 and moves both units to the remainder. It kept 2 - (-1) = 3 less, a quantity of -1, and the
     * remainder got 3 of the 2 ordered.
     */
    public function test_a_split_with_negative_stock_moves_the_whole_line_and_keeps_no_negative_quantity(): void
    {
        $response = $this->request([101], ['valg' => 'ordrer'], self::splitOf('2.000', '1.000', ['9' => '-1', '10' => '100']));

        self::assertContains("update ordrelinjer set antal=0 where id = '501'", $response['queries'], 'the order keeps none of item 9 (it kept -1)');
        self::assertContains("update ordrelinjer set antal=1 where id = '502'", $response['queries'], 'and all of item 10');
        $rest = self::remainderLines($response['queries']);
        self::assertCount(1, $rest);
        self::assertSame(['9', '2'], [$rest[0]['vare_id'], $rest[0]['antal']], 'the remainder is the 2 ordered of item 9 (it was 3)');
        self::assertContains("update ordrer set tidspkt = '',hvem = '', moms='20', sum='80' where id ='101'", $response['queries'],
            'the order is 80,00 + 20,00 VAT (it was -20,00 + -5,00)');
        self::assertContains("update ordrer set tidspkt = '',hvem = '', moms='50', sum='200' where id ='999'", $response['queries'],
            'the remainder is 200,00 + 50,00 VAT');
    }

    /**
     * A negative line (goods taken back on the order) is never split: it adds to the stock. Item 9, 2 with 1 in stock,
     * is split; item 10, -1 with -5 in stock, stays whole on the order. It kept -5 and the remainder got 4.
     */
    public function test_a_split_leaves_a_negative_line_whole_on_the_order(): void
    {
        $response = $this->request([101], ['valg' => 'ordrer'], self::splitOf('2.000', '-1.000', ['9' => '1', '10' => '-5']));

        self::assertContains("update ordrelinjer set antal=1 where id = '501'", $response['queries'], 'the order keeps the unit of item 9 in stock');
        self::assertContains("update ordrelinjer set antal=-1 where id = '502'", $response['queries'], 'and the negative line whole (it kept -5)');
        $rest = self::remainderLines($response['queries']);
        self::assertCount(1, $rest, 'the remainder has no part of the negative line');
        self::assertSame(['9', '1'], [$rest[0]['vare_id'], $rest[0]['antal']]);
        self::assertContains("update ordrer set tidspkt = '',hvem = '', moms='5', sum='20' where id ='101'", $response['queries'],
            'the order is 100,00 - 80,00 = 20,00 + 5,00 VAT');
    }

    /** @return array<string, string> the columns and values of an "insert into t (a, b) values ('x', 1)" */
    private static function insertedRow(string $insert): array
    {
        self::assertSame(1, preg_match('~^insert into \w+ \(([^)]*)\) values \((.*)\)$~s', $insert, $m), $insert);
        $columns = array_map('trim', explode(',', $m[1]));
        $values = array_map('trim', str_getcsv($m[2], ',', "'", ''));
        self::assertCount(count($columns), $values, $insert);
        return array_combine($columns, $values);
    }

    public function test_carried_order_ids_reach_sql_as_numbers_only(): void
    {
        $last = $this->request([], ['valg' => 'ordrer', 'ordre_id' => "1' or '1'='1", 'rest' => '5', 'rest_id' => "7,x,9' or '1'='1,"]);

        $lineQueries = array_values(array_filter($last['queries'], fn (string $q): bool => strpos($q, 'select * from ordrelinjer where ordre_id=') === 0));
        self::assertSame(["select * from ordrelinjer where ordre_id='7'", "select * from ordrelinjer where ordre_id='9'"], $lineQueries);
        foreach ($last['queries'] as $query) {
            self::assertStringNotContainsString("'1'='1", $query);
        }
    }
}
