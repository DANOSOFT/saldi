<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261105 CL/LH Created. fakturer_ordre() in api/rest_api.php, mysale/api.php and remoteBooking/api.php subtracted the paid amount (ordrer.felt_2, a text column) from the order total.
//                A card order the shop sent without a payment has felt_2 '', and float - '' is a TypeError on PHP 8, so the REST API answered HTTP 500 (testlab websims ORDER-POS-PAYMENT).
//                api/rest_api.php also committed the fakturadate before that check, so a refused invoice kept its date; it now refuses before it writes anything.
//                Each copy is lifted out of its script by token (the scripts run their request dispatch on include) and run in its own namespace, whose stubs answer one order and record the queries.
//                api/rest_api.php put saldi_ordre_id and fakturadate into its SQL as sent, so fakturadate "2026-11-08', status=3 WHERE id='4711' -- " marked the order invoiced without posting it.
//                It now refuses an id that is not digits only and a date that is not YYYY-MM-DD before any query; the booking copies cast the id, and all three escape the stored tidspkt.
final class FakturerOrdreTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** Script => namespace its fakturer_ordre() is evaluated in. */
    private const SCRIPTS = array(
        'api/rest_api.php'      => 'FakturerOrdreRestApi',
        'mysale/api.php'        => 'FakturerOrdreMySale',
        'remoteBooking/api.php' => 'FakturerOrdreRemoteBooking',
    );

    /** @var string|false */
    private $cwd;

    /** @var string */
    private $tmp = '';

    public static function setUpBeforeClass(): void
    {
        require_once self::ROOT . '/includes/std_func.php'; // afrund()
        foreach (self::SCRIPTS as $script => $ns) {
            if (!function_exists($ns . '\fakturer_ordre')) {
                eval('namespace ' . $ns . ';' . self::STUBS . self::functionSource(self::ROOT . '/' . $script, 'fakturer_ordre'));
            }
        }
    }

    protected function setUp(): void
    {
        // api/rest_api.php's fakturer_ordre() includes "../includes/ordrefunc.php" from the working directory
        $this->tmp = sys_get_temp_dir() . '/fakturer_ordre_' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/api', 0700, true);
        mkdir($this->tmp . '/includes', 0700);
        file_put_contents($this->tmp . '/includes/ordrefunc.php', "<?php\n");
        $this->cwd = getcwd();
        chdir($this->tmp . '/api');
        $GLOBALS['db'] = 'fixture_1';
        $GLOBALS['db_modify_fejl'] = false;
    }

    protected function tearDown(): void
    {
        if ($this->cwd !== false) {
            chdir($this->cwd);
        }
        if (is_file($this->tmp . '/includes/ordrefunc.php')) {
            unlink($this->tmp . '/includes/ordrefunc.php');
        }
        foreach (array('/includes', '/api', '') as $dir) {
            if (is_dir($this->tmp . $dir)) {
                rmdir($this->tmp . $dir);
            }
        }
        unset($GLOBALS['db_modify_fejl']);
    }

    /** @return array<string, array{string}> */
    public static function scripts(): array
    {
        $cases = array();
        foreach (array_keys(self::SCRIPTS) as $script) {
            $cases[$script] = array($script);
        }
        return $cases;
    }

    /** The reported case: a Kreditkort order of 100 + 25 VAT with no payment recorded, invoiced with pos_betaling. */
    #[DataProvider('scripts')]
    public function testACardOrderWithoutAPaymentIsRefused(string $script): void
    {
        $result = $this->invoice($script, '', 'Kreditkort');

        self::assertSame('Error in amount (100.000+25.000) vs. paid amount () : diff 125', $result);
        $calls = $this->db($script)::$calls;
        self::assertNotContains('bogfor', $calls, 'a refused invoice must not be posted');
        self::assertSame(
            count(array_keys($calls, 'transaktion begin', true)),
            count(array_keys($calls, 'transaktion rollback', true)),
            'every transaction the refusal opened is rolled back'
        );
    }

    /** api/rest_api.php committed the fakturadate in its own transaction before the check; nothing may be written now. */
    public function testTheRestApiRefusesBeforeItWritesAnything(): void
    {
        $this->invoice('api/rest_api.php', '', 'Kreditkort');

        $db = $this->db('api/rest_api.php');
        self::assertSame(array(), $db::$writes, 'the refused invoice wrote to the database');
        self::assertSame(array(), $db::$calls, 'the refused invoice opened a transaction or delivered the order');
    }

    /** mysale and remoteBooking invoice their Kreditkort orders without pos_betaling; the subtraction ran anyway and died on ''. */
    #[DataProvider('scripts')]
    public function testAnOrderWithoutAPaymentIsInvoicedWhenNoPaymentIsChecked(string $script): void
    {
        self::assertSame('4711', (string)$this->invoice($script, '', 0));
        self::assertContains('bogfor', $this->db($script)::$calls);
    }

    /** A payment that covers the order still lets the invoice through, now that the check runs first. */
    #[DataProvider('scripts')]
    public function testAPaidCardOrderIsInvoiced(string $script): void
    {
        self::assertSame('4711', (string)$this->invoice($script, '125', 'Kreditkort'));
        $calls = $this->db($script)::$calls;
        self::assertContains('levering', $calls);
        self::assertContains('bogfor', $calls);
        self::assertNotContains('transaktion rollback', $calls);
    }

    /** @return array<string, array{string}> */
    public static function orderIdsThatAreNotIds(): array
    {
        return array(
            'quote'    => array("4711' or '1'='1"),
            'no quote' => array('4711 or 1=1'),
            'decimal'  => array('22.9'),
            'exponent' => array('2e1'),
            'negative' => array('-5'),
            'zero'     => array('0'),
            'empty'    => array(''),
        );
    }

    /** saldi_ordre_id went into every query as sent; one that is not an order id is refused before any query. */
    #[DataProvider('orderIdsThatAreNotIds')]
    public function testTheRestApiRefusesAnOrderIdThatIsNotDigits(string $id): void
    {
        $result = $this->invoice('api/rest_api.php', '125', 'Kreditkort', $id);

        self::assertSame('Invalid saldi_ordre_id (' . trim($id) . ')', $result);
        $this->assertNothingQueried('api/rest_api.php');
    }

    /** @return array<string, array{string}> */
    public static function datesThatAreNotDates(): array
    {
        return array(
            'the reported injection' => array("2026-11-08', status=3 WHERE id='4711' -- "),
            'quote after the date'   => array("2026-11-08'"),
            'no such day'            => array('2026-02-30'),
            'day first'              => array('08-11-2026'),
            'a word'                 => array('tomorrow'),
        );
    }

    /** fakturadate went into "update ordrer set fakturadate='...'" as sent, which let a request set status=3 without posting. */
    #[DataProvider('datesThatAreNotDates')]
    public function testTheRestApiRefusesAFakturadateThatIsNotADate(string $date): void
    {
        $result = $this->invoice('api/rest_api.php', '125', 'Kreditkort', '4711', $date);

        self::assertSame("Invalid fakturadate ($date)", $result);
        $this->assertNothingQueried('api/rest_api.php');
    }

    /** The shop's ordredate format, a date and a time, is still taken; only the date is written, as the date column kept it. */
    public function testAFakturadateWithATimeIsWrittenAsItsDate(): void
    {
        foreach (array('2026-09-04 10:15:00', '2026-09-04T10:15:00+01:00', '2026-09-04') as $date) {
            self::assertSame('4711', $this->invoice('api/rest_api.php', '125', 'Kreditkort', '4711', $date), $date);
            self::assertContains("update ordrer set fakturadate='2026-09-04' where id='4711'", $this->db('api/rest_api.php')::$writes, $date);
        }
    }

    /** The booking copies are called with an (int) order id; they cast it themselves too, as it goes into every query. */
    public function testTheBookingCopiesCastTheOrderId(): void
    {
        foreach (array('mysale/api.php', 'remoteBooking/api.php') as $script) {
            self::assertSame(4711, $this->invoice($script, '', 0, "4711' or '1'='1"), $script);
            $db = $this->db($script);
            foreach (array_merge($db::$selects, $db::$writes) as $sql) {
                self::assertMatchesRegularExpression("/ where (ordre_)?id='4711'( and vare_id > 0)?$/", $sql, "$script: $sql");
            }
        }
    }

    /** ordrer.tidspkt is written back after posting; it came from the shop's ordredate and is escaped like any outside value. */
    #[DataProvider('scripts')]
    public function testTheStoredTidspktIsEscaped(string $script): void
    {
        $this->invoice($script, '125', 'Kreditkort', '4711', '2026-09-04', "10:15'");

        self::assertContains("update ordrer set tidspkt='10:15''' where id='4711'", $this->db($script)::$writes);
    }

    private function assertNothingQueried(string $script): void
    {
        $db = $this->db($script);
        self::assertSame(array(), $db::$selects, 'the refused invoice ran a query');
        self::assertSame(array(), $db::$writes, 'the refused invoice wrote to the database');
        self::assertSame(array(), $db::$calls, 'the refused invoice opened a transaction or delivered the order');
    }

    /**
     * Invoices an order (one line of 100 at 25% VAT, sum 100, VAT 25, betalingsbet Kreditkort) through the script's fakturer_ordre().
     *
     * @param string $script Script whose copy runs.
     * @param string $paid The order's felt_2, the amount the shop says was paid.
     * @param string|int $posBetaling What the caller passes as pos_betaling.
     * @param string $id The order id the caller passes.
     * @param string $fakturadate The fakturadate passed to api/rest_api.php's copy (the others take none).
     * @param string $tidspkt The order's tidspkt.
     * @return mixed The order id when invoiced, else the error text.
     */
    private function invoice(string $script, string $paid, $posBetaling, string $id = '4711', string $fakturadate = '2026-09-04', string $tidspkt = '')
    {
        $db = $this->db($script);
        $db::$selects = array();
        $db::$writes = array();
        $db::$calls = array();
        $db::$order = array('betalingsbet' => 'Kreditkort', 'tidspkt' => $tidspkt, 'sum' => '100.000', 'moms' => '25.000', 'felt_1' => 'Kreditkort', 'felt_2' => $paid);
        $args = array($id, 'PDF', $posBetaling);
        if ($script === 'api/rest_api.php') {
            $args[] = $fakturadate;
        }
        return call_user_func_array(self::SCRIPTS[$script] . '\fakturer_ordre', $args);
    }

    /** @return class-string */
    private function db(string $script): string
    {
        return self::SCRIPTS[$script] . '\Db';
    }

    /**
     * Source of a top-level function in a file, cut out by token.
     *
     * @param string $file Path to the PHP file.
     * @param string $name Function name.
     * @return string
     */
    private static function functionSource(string $file, string $name): string
    {
        $tokens = token_get_all((string)file_get_contents($file));
        $count = count($tokens);
        $depth = 0;
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            if ($depth === 0 && is_array($token) && $token[0] === T_FUNCTION) {
                $j = $i + 1;
                while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING && $tokens[$j][1] === $name) {
                    $source = '';
                    $level = 0;
                    for ($k = $i; $k < $count; $k++) {
                        $part = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
                        $source .= $part;
                        if ($part === '{' || (is_array($tokens[$k]) && in_array($tokens[$k][0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
                            $level++;
                        } elseif ($part === '}' && --$level === 0) {
                            return $source . "\n";
                        }
                    }
                }
            }
            if ($text === '{' || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
                $depth++;
            } elseif ($text === '}') {
                $depth--;
            }
        }
        self::fail("$name() is gone from $file");
    }

    /**
     * Stand-ins for what fakturer_ordre() calls, declared in the fixture namespace so they win over any
     * global function of the same name. The order's lines match its sum and VAT, so only the paid amount decides.
     */
    private const STUBS = <<<'PHP'
        final class Db {
            /** @var array<string, string> */
            public static $order = array();
            /** @var list<string> */
            public static $selects = array();
            /** @var list<string> */
            public static $writes = array();
            /** @var list<string> */
            public static $calls = array();
        }
        function db_escape_string($value) {
            return str_replace("'", "''", (string)$value);
        }
        function db_select($sql, $where = '') {
            Db::$selects[] = $sql;
            if (stripos($sql, 'select betalingsbet') !== false) {
                return new \ArrayIterator(array(Db::$order));
            }
            if (stripos($sql, 'select fakturadate from ordrer') !== false) {
                return new \ArrayIterator(array(array('fakturadate' => '2026-09-04')));
            }
            if (stripos($sql, 'from ordrelinjer') !== false) {
                return new \ArrayIterator(array(array('antal' => '1.000', 'pris' => '100.000', 'rabat' => '0.000', 'momssats' => '25.000')));
            }
            return new \ArrayIterator(array());
        }
        function db_fetch_array($result) {
            if (!$result->valid()) {
                return false;
            }
            $row = $result->current();
            $result->next();
            return $row;
        }
        function db_modify($sql, $where = '') {
            Db::$writes[] = $sql;
            return true;
        }
        function transaktion($what) {
            Db::$calls[] = 'transaktion ' . $what;
        }
        function levering(...$args) {
            Db::$calls[] = 'levering';
            return 'OK';
        }
        function bogfor(...$args) {
            Db::$calls[] = 'bogfor';
            return 'OK';
        }
        function send_api_invoice_email($id) {
            return 'not sent';
        }
        function fopen($path, $mode) {
            return \fopen('php://memory', 'w+');
        }

        PHP;
}
