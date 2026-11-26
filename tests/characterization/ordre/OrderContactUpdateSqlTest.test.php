<?php
// 20261124 CL/LH MB-177 Runs the contact update of the order pages against real PostgreSQL.
//
// Choosing a contact in the order's contact list opens the page by GET with kontakt and id, and the page writes
// that name on the order. The name and the id are outside values: the update must set this order's contact to
// the text as typed and leave every other order alone, whatever the text or id holds.
// The block is lifted from kreditor/ordre.php, finans/ordre.php and debitor/ordre.php and run with the real
// query on an ordrer table in a private schema on the shared SQL fixture database.
// Codex review: a malformed id changes no order at all, the debtor e-mail follows the same rules, and each page
// checks the order id from the link before it opens the database.
namespace OrderContactUpdateSqlFixture {

    final class Rec
    {
        /** @var \PgSql\Connection|null */
        public static $connection = null;
    }

    function db_modify($qtxt, $spor = '')
    {
        $result = pg_query(Rec::$connection, (string)$qtxt);
        if ($result === false) {
            throw new \RuntimeException('query failed: ' . pg_last_error(Rec::$connection));
        }
        return $result;
    }

    /** Same as includes/db_query.php db_escape_string() for PostgreSQL. */
    function db_escape_string($qtext)
    {
        return pg_escape_string(Rec::$connection, str_replace("\0", '', (string)$qtext));
    }

    /** Same contract as includes/std_func.php if_isset() for the two forms the pages use. */
    function if_isset($arrayOrVar, $default = null, $key = null)
    {
        if ($key === null) {
            return isset($arrayOrVar) ? $arrayOrVar : $default;
        }
        return is_array($arrayOrVar) && array_key_exists($key, $arrayOrVar) && $arrayOrVar[$key] !== null ? $arrayOrVar[$key] : $default;
    }
}

namespace Saldi\Tests\OrderContactUpdateSql {

    use OrderContactUpdateSqlFixture\Rec;
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\TestCase;

    final class OrderContactUpdateSqlTest extends TestCase
    {
        private const FIXTURE = 'OrderContactUpdateSqlFixture';

        private string $schema = '';

        protected function setUp(): void
        {
            require_once __DIR__ . '/../support/database/TestDatabase.php';
            $reason = \TestDatabase::unavailableReason();
            if ($reason !== null) {
                self::markTestSkipped($reason);
            }
            $connection = \TestDatabase::connect(\TestDatabase::sqlFixture(static::class)->name());
            $this->schema = 'mb177_' . bin2hex(random_bytes(6));
            pg_query($connection, 'CREATE SCHEMA ' . $this->schema);
            pg_query($connection, 'SET search_path TO ' . $this->schema);
            pg_query($connection, "SET statement_timeout = '15s'");
            pg_query($connection, 'CREATE TABLE ordrer (id serial PRIMARY KEY, kontakt varchar(150), email varchar(150))');
            pg_query($connection, "INSERT INTO ordrer (id, kontakt, email) VALUES (1, 'Første', 'a@x.dk'), (2, 'Anden', 'b@x.dk'), (3, 'Tredje', 'c@x.dk')");
            Rec::$connection = $connection;
        }

        protected function tearDown(): void
        {
            if (Rec::$connection !== null) {
                pg_query(Rec::$connection, 'DROP SCHEMA IF EXISTS ' . $this->schema . ' CASCADE');
                pg_close(Rec::$connection);
                Rec::$connection = null;
            }
        }

        /** @return array<string, array{0: string}> */
        public static function pages(): array
        {
            return array(
                'kreditor' => array('kreditor/ordre.php'),
                'finans' => array('finans/ordre.php'),
                'debitor' => array('debitor/ordre.php'),
            );
        }

        /** The statement around the page's "update ordrer set kontakt=", from its "if" to the end of its body. */
        private static function block(string $page): string
        {
            $src = file_get_contents(__DIR__ . '/../../../' . $page);
            self::assertNotFalse($src);
            $sink = strpos($src, 'update ordrer set kontakt=');
            self::assertNotFalse($sink, $page . ': the contact update is gone');
            $start = strrpos(substr($src, 0, $sink), "\nif");
            self::assertNotFalse($start);
            $start++;
            $depth = 0;
            $len = strlen($src);
            for ($i = $start; $i < $len; $i++) {
                $c = $src[$i];
                if ($c === '{') {
                    $depth++;
                } elseif ($c === '}') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                } elseif ($c === ';' && $depth === 0 && $i > $sink) {
                    break;
                }
            }
            $block = substr($src, $start, $i + 1 - $start);
            foreach (array('db_modify', 'db_escape_string', 'if_isset') as $fn) {
                $block = preg_replace('/(?<![\w\\\\>$])' . $fn . '\(/', '\\' . self::FIXTURE . '\\' . $fn . '(', $block);
            }
            return $block;
        }

        /**
         * Runs the page's contact update for one GET request.
         * @param array<string, mixed> $get
         */
        private function runContactUpdate(string $page, array $get): void
        {
            $block = self::block($page);
            $_GET = $get;
            // The pages have read these from $_GET by the time the block runs.
            $id = $get['id'] ?? null;
            $kontakt = $get['kontakt'] ?? null;
            eval($block);
        }

        /** @return array<int, string> kontakt by order id */
        private function contacts(): array
        {
            $rows = pg_fetch_all(pg_query(Rec::$connection, 'SELECT id, kontakt FROM ordrer ORDER BY id')) ?: array();
            $out = array();
            foreach ($rows as $row) {
                $out[(int)$row['id']] = (string)$row['kontakt'];
            }
            return $out;
        }

        #[DataProvider('pages')]
        public function testAPlainNameIsSetOnThisOrderOnly(string $page): void
        {
            $this->runContactUpdate($page, array('id' => '2', 'kontakt' => 'Hanne Holm'));
            self::assertSame(array(1 => 'Første', 2 => 'Hanne Holm', 3 => 'Tredje'), $this->contacts());
        }

        #[DataProvider('pages')]
        public function testANameWithAnApostropheIsStoredAsTyped(string $page): void
        {
            $this->runContactUpdate($page, array('id' => '2', 'kontakt' => "Sean O'Brien"));
            self::assertSame(array(1 => 'Første', 2 => "Sean O'Brien", 3 => 'Tredje'), $this->contacts());
        }

        /** A name that closes the quote and comments out the rest must not reach the other orders. */
        #[DataProvider('pages')]
        public function testANameThatClosesTheQuoteDoesNotChangeOtherOrders(string $page): void
        {
            $name = "Hanne' --";
            $this->runContactUpdate($page, array('id' => '2', 'kontakt' => $name));
            self::assertSame(array(1 => 'Første', 2 => $name, 3 => 'Tredje'), $this->contacts());
        }

        /** A name is text, never an SQL expression: string concatenation in it is stored as typed. */
        #[DataProvider('pages')]
        public function testANameIsNeverEvaluatedAsSql(string $page): void
        {
            $name = "A' || 'B";
            $this->runContactUpdate($page, array('id' => '2', 'kontakt' => $name));
            self::assertSame(array(1 => 'Første', 2 => $name, 3 => 'Tredje'), $this->contacts());
        }

        /** An id that is not a whole number changes no order at all. */
        #[DataProvider('pages')]
        public function testAnIdThatIsNotAWholeNumberChangesNoOrder(string $page): void
        {
            foreach (array('1 or id > 0', "1' or '1'='1") as $id) {
                try {
                    $this->runContactUpdate($page, array('id' => $id, 'kontakt' => 'Hanne Holm'));
                } catch (\RuntimeException $e) {
                    // A refused statement changes nothing; only a change to an order fails this test.
                }
                self::assertSame(array(1 => 'Første', 2 => 'Anden', 3 => 'Tredje'), $this->contacts(), 'id ' . $id . ' changed an order');
            }
        }

        /** debitor/ordre.php also takes the contact's e-mail from the link, with the same rules. */
        public function testTheDebtorEmailIsSetOnThisOrderOnlyAndStoredAsTyped(): void
        {
            $email = "o'brien@x.dk' --";
            $this->runContactUpdate('debitor/ordre.php', array('id' => '2', 'kontakt' => 'Sean', 'email' => $email));
            $rows = pg_fetch_all(pg_query(Rec::$connection, 'SELECT id, email FROM ordrer ORDER BY id')) ?: array();
            self::assertSame(array('a@x.dk', $email, 'c@x.dk'), array_column($rows, 'email'));
        }

        /**
         * The page refuses a link whose order id is not a whole number before it opens the database,
         * so every query on the page that reads the id gets a whole number.
         */
        #[DataProvider('pages')]
        public function testThePageChecksTheOrderIdBeforeItConnects(string $page): void
        {
            $src = (string)file_get_contents(__DIR__ . '/../../../' . $page);
            $check = strpos($src, 'if (!order_ids_from_link_are_whole_numbers($_GET, array(');
            $connect = strpos($src, 'include("../includes/connect.php")');
            self::assertNotFalse($check, $page . ' does not check the order id from the link');
            self::assertNotFalse($connect);
            self::assertLessThan($connect, $check, $page . ' checks the order id after it connects');
            $keys = $page === 'debitor/ordre.php' ? "array('id', 'ordre_id')" : "array('id', 'tjek')";
            self::assertStringContainsString('order_ids_from_link_are_whole_numbers($_GET, ' . $keys . ')', $src);
        }

        /** kontakt[]= in the link sends an array; the page must not write it. */
        #[DataProvider('pages')]
        public function testAnArrayAsTheNameChangesNothing(string $page): void
        {
            try {
                $this->runContactUpdate($page, array('id' => '2', 'kontakt' => array('x')));
            } catch (\Throwable $e) {
                // Refused is fine; a write is not.
            }
            self::assertSame(array(1 => 'Første', 2 => 'Anden', 3 => 'Tredje'), $this->contacts());
        }
    }
}
