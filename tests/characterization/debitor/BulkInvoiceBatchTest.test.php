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
 * History:
 * 20261105 CL/LH Created.
 */
final class BulkInvoiceBatchTest extends TestCase
{
    private const DATABASE = <<<'PHP'
<?php
function db_escape_string($s) { return addslashes((string) $s); }
function db_select($q, $spor = '') {
	$GLOBALS['__queries'][] = $q;
	$year = date('Y');
	if (strpos($q, "art='MFAKT'") !== false) return new ArrayIterator([['box2' => '', 'box3' => '0']]);
	if (strpos($q, "art='RA'") !== false) return new ArrayIterator([['box1' => '01', 'box2' => $year, 'box3' => '12', 'box4' => $year]]);
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
function db_modify($q, $spor = '') { $GLOBALS['__queries'][] = $q; return true; }
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
     * @return array{html: string, queries: string[], calls: string[]}
     */
    private function request(array $orders, array $get): array
    {
        $result = $this->fixture . '/result.json';
        $script = '$GLOBALS["__orders"] = ' . var_export($orders, true) . ';'
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
