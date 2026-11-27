<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// 20261105 CL/LH Created. getNextAccountNo() (api/restApiIncludes/getNextAccountNo.php) ignored minNo and maxNo: without them it always answered 999999, and with both it never set $kontonr.
//                A debtor with an empty kontonr then reached '' + 1, a TypeError on PHP 8, so getNextAccountNo and createDebitor answered HTTP 500 (testlab websims DEBTOR-AUTO).
//                CreateDebitor() also stored $cvrnr and $ean, which it never set, so the cvr and ean that were sent were lost.
//                Both functions are lifted out by token and run in a namespace whose stubs hold the adresser rows and record the SQL.
//                A minNo above 9999 sent without maxNo is not cut off at the default end 9999: createDebitor with only minNo=880100 creates 880100, as it could create 999999 before.
final class RestApiNextAccountNoTest extends TestCase
{
    private const NS = 'RestApiNextAccountNoFixture';
    private const INCLUDES = __DIR__ . '/../../../api/restApiIncludes';

    /** @var array<string, mixed> */
    private $get = array();

    /** @var string|false */
    private $cwd;

    /** @var string */
    private $tmp = '';

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../../includes/std_func.php'; // ifset(), strip_placeholder_value()
        if (!function_exists(self::NS . '\getNextAccountNo')) {
            eval('namespace ' . self::NS . ';' . self::STUBS
                . self::functionSource(self::INCLUDES . '/getNextAccountNo.php', 'getNextAccountNo')
                . self::functionSource(self::INCLUDES . '/createDebitor.php', 'CreateDebitor'));
        }
    }

    protected function setUp(): void
    {
        $this->get = $_GET;
        $_GET = array();
        $GLOBALS['db'] = 'fixture_1';
        // CreateDebitor() includes "restApiIncludes/getNextAccountNo.php" from the working directory; the lifted copy is already defined
        $this->tmp = sys_get_temp_dir() . '/next_account_no_' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/restApiIncludes', 0700, true);
        file_put_contents($this->tmp . '/restApiIncludes/getNextAccountNo.php', "<?php\n");
        $this->cwd = getcwd();
        chdir($this->tmp);
        $db = self::NS . '\Db';
        $db::$rows = array();
        $db::$sql = array();
    }

    protected function tearDown(): void
    {
        $_GET = $this->get;
        if ($this->cwd !== false) {
            chdir($this->cwd);
        }
        if (is_file($this->tmp . '/restApiIncludes/getNextAccountNo.php')) {
            unlink($this->tmp . '/restApiIncludes/getNextAccountNo.php');
        }
        foreach (array('/restApiIncludes', '') as $dir) {
            if (is_dir($this->tmp . $dir)) {
                rmdir($this->tmp . $dir);
            }
        }
    }

    /** The reported case: bounds sent, and debtors whose kontonr is empty or text. */
    public function testTheBoundedRangeStartsAtMinNoAndSkipsTextAccounts(): void
    {
        $this->debtors('', 'ABC', '1500', '99887766', '880099', '880111');
        $_GET = array('minNo' => '880100', 'maxNo' => '880110');

        self::assertSame(880100, $this->next());
    }

    public function testTheNextNumberFollowsTheHighestOneInTheRange(): void
    {
        $this->debtors('', '880100', '880104', '880111');
        $_GET = array('minNo' => '880100', 'maxNo' => '880110');

        self::assertSame(880105, $this->next());
    }

    /** Without bounds the range is 1 to 9999, the one the loop already used: an old 999999 debtor or a phone number does not count. */
    public function testWithoutBoundsTheNumberFollowsTheHighestOneBelow10000(): void
    {
        $this->debtors('1000', '1050', '', '999999', '99887766');
        $this->debtor('5000', 'K');

        self::assertSame(1051, $this->next());
    }

    /** The review case: minNo above the default end and no maxNo, on an empty adresser table. */
    public function testAMinNoAboveTheDefaultEndWithoutMaxNoHasNoUpperEnd(): void
    {
        $_GET = array('minNo' => '880100');
        self::assertSame(880100, $this->next());

        $this->debtors('880100', '880105', '1500');
        self::assertSame(880106, $this->next());
    }

    /** A minNo within 1 to 9999 keeps the default end, so a phone number used as kontonr stays out of the series. */
    public function testAMinNoWithinTheDefaultEndKeepsIt(): void
    {
        $this->debtors('1000', '1200', '99887766');
        $_GET = array('minNo' => '1000');

        self::assertSame(1201, $this->next());
    }

    /** Only a kontonr of digits is in the sequence: 2e3 and 12.5 are text, and a minNo below 1 starts at 1. */
    public function testOnlyDigitsCountAndTheRangeStartsAtOne(): void
    {
        $this->debtors('2e3', '12.5', '-3');
        $_GET = array('minNo' => '-5');

        self::assertSame(1, $this->next());
    }

    public function testAUsedUpRangeIsAnError(): void
    {
        $this->debtors('880100', '880110');
        $_GET = array('minNo' => '880100', 'maxNo' => '880110');

        self::assertSame('No free account number from 880100 to 880110', $this->next());
    }

    public function testTheAccountTypeIsEscaped(): void
    {
        $_GET = array('accountType' => "D' or '1'='1");
        $this->next();

        $db = self::NS . '\Db';
        self::assertSame(array("select kontonr from adresser where art = 'D'' or ''1''=''1'"), $db::$sql);
    }

    /** Two debtors without kontonr get two numbers in the range, and keep the cvr and ean that were sent. */
    public function testCreateDebitorAllocatesInTheRangeAndKeepsCvrAndEan(): void
    {
        $this->debtors('', '1500');
        $_GET = array('firmanavn' => 'TESTLAB Debtor', 'tlf' => '99008888', 'email' => 'debtor@test.invalid', 'gruppe' => '1',
            'cvr' => '12345678', 'ean' => '5790000000001', 'minNo' => '880100', 'maxNo' => '880110');

        $first = $this->createDebitor();
        $second = $this->createDebitor();

        $db = self::NS . '\Db';
        $made = array_slice($db::$rows, 2);
        self::assertSame(array($made[0]['id'] . ',880100', $made[1]['id'] . ',880101'), array($first, $second));
        foreach ($made as $row) {
            self::assertSame(array('D', '12345678', '5790000000001', '1'), array($row['art'], $row['cvrnr'], $row['ean'], $row['gruppe']));
        }
    }

    /** The review case through createDebitor: only minNo=880100 sent, and no debtors yet. */
    public function testCreateDebitorWithOnlyMinNoCreatesTheAccount(): void
    {
        $_GET = array('firmanavn' => 'TESTLAB Debtor', 'minNo' => '880100');

        $result = $this->createDebitor();

        $db = self::NS . '\Db';
        self::assertCount(1, $db::$rows);
        self::assertSame($db::$rows[0]['id'] . ',880100', $result);
        self::assertSame('880100', $db::$rows[0]['kontonr']);
    }

    public function testCreateDebitorInAUsedUpRangeCreatesNothing(): void
    {
        $this->debtors('880101');
        $_GET = array('firmanavn' => 'TESTLAB Debtor', 'minNo' => '880100', 'maxNo' => '880101');

        self::assertSame('No free account number from 880100 to 880101', $this->createDebitor());
        $db = self::NS . '\Db';
        self::assertCount(1, $db::$rows);
    }

    /** @return int|string */
    private function next()
    {
        return call_user_func(self::NS . '\getNextAccountNo', '');
    }

    /** @return mixed */
    private function createDebitor()
    {
        return call_user_func(self::NS . '\CreateDebitor');
    }

    private function debtors(string ...$kontonr): void
    {
        foreach ($kontonr as $nr) {
            $this->debtor($nr, 'D');
        }
    }

    private function debtor(string $kontonr, string $art): void
    {
        $db = self::NS . '\Db';
        $db::$rows[] = array('id' => (string)(count($db::$rows) + 100), 'kontonr' => $kontonr, 'art' => $art);
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
     * Stand-ins declared in the fixture namespace, so they win over any global function of the same name.
     * Db::$rows is the adresser table; an insert into it is read back the way PostgreSQL reads quoted literals.
     */
    private const STUBS = <<<'PHP'
        final class Db {
            /** @var list<array<string, string>> */
            public static $rows = array();
            /** @var list<string> */
            public static $sql = array();
        }
        function db_escape_string($value) {
            return str_replace("'", "''", (string)$value);
        }
        function db_select($sql, $where = '') {
            $rows = array();
            if (preg_match("/^select kontonr from adresser where art = '((?:[^']|'')*)'$/", $sql, $m)) {
                Db::$sql[] = $sql;
                foreach (Db::$rows as $row) {
                    if ($row['art'] === str_replace("''", "'", $m[1])) {
                        $rows[] = array('kontonr' => $row['kontonr']);
                    }
                }
            } elseif (preg_match("/^select id from adresser where kontonr='((?:[^']|'')*)' and art = 'D'$/", $sql, $m)) {
                foreach (Db::$rows as $row) {
                    if ($row['art'] === 'D' && $row['kontonr'] === str_replace("''", "'", $m[1])) {
                        $rows[] = array('id' => $row['id']);
                    }
                }
            }
            return new \ArrayIterator($rows);
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
            if (preg_match('/^insert into adresser\s*\(([^)]*)\)\s*values\s*\((.*)\)$/s', $sql, $m)) {
                preg_match_all("/'((?:[^']|'')*)'/", $m[2], $values);
                $row = array_combine(array_map('trim', explode(',', $m[1])), str_replace("''", "'", $values[1]));
                $row['id'] = (string)(count(Db::$rows) + 100);
                Db::$rows[] = $row;
            }
            return true;
        }
        function chk4utf8($text) {
            return $text;
        }
        function fopen($path, $mode) {
            return \fopen('php://memory', 'w+');
        }

        PHP;
}
