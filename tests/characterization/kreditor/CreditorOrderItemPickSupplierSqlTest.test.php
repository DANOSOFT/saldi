<?php
// 20261119 CL/MMR SST-1041 Runs the search-list supplier lookup of kreditor/ordre.php against real PostgreSQL tables.
//
// Codex round 1 found that the first ranking, (lev_varenr = 'X') desc, put a supplier row with lev_varenr NULL first, because PostgreSQL sorts NULL first in descending order.
// A supplier can also have several numbers for one item, so the picked row's number and price must reach the line, not another row of the same supplier.
// vare_lev and adresser are built in a private schema on the shared SQL fixture database, with the column types they have in a customer database.
// 20261119 CL/MMR SST-1041 Codex round 2: a stored number with spaces around it is compared trimmed, as the search list shows it.
namespace CreditorOrderItemPickSqlFixture {

    final class Rec
    {
        /** @var \PgSql\Connection|null */
        public static $connection = null;
        /** @var array<int, string> */
        public static array $calls = array();
    }

    function db_select($qtxt, $spor = '')
    {
        $result = pg_query(Rec::$connection, (string)$qtxt);
        if ($result === false) {
            throw new \RuntimeException('query failed: ' . pg_last_error(Rec::$connection));
        }
        return $result;
    }

    function db_fetch_array($result)
    {
        return pg_fetch_assoc($result);
    }

    function db_escape_string($value)
    {
        return pg_escape_string(Rec::$connection, (string)$value);
    }

    function transaktion($what)
    {
        Rec::$calls[] = 'transaktion:' . $what;
    }

    function noSupplierForItemAlert($itemNo)
    {
        Rec::$calls[] = 'alert:' . $itemNo;
    }

    /** Same contract as includes/std_func.php ifset() for the array case the lifted code uses. */
    function ifset($arrayOrVar, $key = null, $default = null)
    {
        return is_array($arrayOrVar) && array_key_exists($key, $arrayOrVar) ? $arrayOrVar[$key] : $default;
    }
}

namespace Saldi\Tests\CreditorOrderItemPickSql {

    use CreditorOrderItemPickSqlFixture\Rec;
    use PHPUnit\Framework\TestCase;

    final class CreditorOrderItemPickSupplierSqlTest extends TestCase
    {
        private const ORDRE = __DIR__ . '/../../../kreditor/ordre.php';
        private const FIXTURE = 'CreditorOrderItemPickSqlFixture';

        private string $schema = '';

        protected function setUp(): void
        {
            require_once __DIR__ . '/../support/database/TestDatabase.php';
            $reason = \TestDatabase::unavailableReason();
            if ($reason !== null) {
                self::markTestSkipped($reason);
            }
            $connection = \TestDatabase::connect(\TestDatabase::sqlFixture(static::class)->name());
            $this->schema = 'sst1041_' . bin2hex(random_bytes(6));
            pg_query($connection, 'CREATE SCHEMA ' . $this->schema);
            pg_query($connection, 'SET search_path TO ' . $this->schema);
            pg_query($connection, "SET statement_timeout = '15s'");
            pg_query($connection, 'CREATE TABLE adresser (id integer PRIMARY KEY, kontonr text, art text)');
            pg_query($connection, 'CREATE TABLE vare_lev (id serial PRIMARY KEY, posnr integer, lev_id integer, vare_id integer,
                lev_varenr text, kostpris numeric(15,3))');
            pg_query($connection, "INSERT INTO adresser (id, kontonr, art) VALUES (57, '1052', 'K'), (788, '1069', 'K'), (2225, '2225', 'K')");
            Rec::$connection = $connection;
            Rec::$calls = array();
        }

        protected function tearDown(): void
        {
            if (Rec::$connection !== null) {
                pg_query(Rec::$connection, 'DROP SCHEMA IF EXISTS ' . $this->schema . ' CASCADE');
                pg_close(Rec::$connection);
                Rec::$connection = null;
            }
        }

        /**
         * @param array<int, array{0: int, 1: int, 2: string|null, 3: float}> $rows posnr, lev_id, lev_varenr, kostpris for item 618192
         */
        private function suppliers(array $rows): void
        {
            foreach ($rows as $row) {
                pg_query_params(Rec::$connection,
                    'INSERT INTO vare_lev (posnr, lev_id, vare_id, lev_varenr, kostpris) VALUES ($1, $2, 618192, $3, $4)',
                    array($row[0], $row[1], $row[2], $row[3]));
            }
        }

        /**
         * Runs the new block of the GET vare_id branch with the real queries.
         *
         * @return array{konto_id: mixed, lev_varenr: mixed, pris: mixed, picked: bool}
         */
        private function pick(string $levVarenr, $itemCost = 800.0): array
        {
            $src = file_get_contents(self::ORDRE);
            self::assertNotFalse($src);
            $at = strpos($src, "if (!\$id && !\$konto_id) {\n\t\t// SST-1041");
            self::assertNotFalse($at, 'the SST-1041 block is gone');
            $end = strpos($src, 'if ((!$pris[0] || !$lev_varenr[0]) && $vare_id[0] && $konto_id', $at);
            self::assertNotFalse($end);
            $block = substr($src, $at, $end - $at);
            foreach (array('db_select', 'db_fetch_array', 'db_escape_string', 'transaktion', 'noSupplierForItemAlert', 'ifset') as $fn) {
                $block = preg_replace('/(?<![\w\\\\>$])' . $fn . '\(/', '\\' . self::FIXTURE . '\\' . $fn . '(', $block);
            }
            $block = str_replace('exit;', 'return true;', $block);
            $_GET = $levVarenr === '' ? array('id' => '', 'vare_id' => '618192') : array('id' => '', 'vare_id' => '618192', 'lev_varenr' => $levVarenr);
            $id = 0;
            $konto_id = null;
            $vare_id = array(0 => '618192');
            $varenr = array(0 => '618192');
            $pris = array(0 => $itemCost);
            $lev_varenr = array(0 => '');
            ob_start();
            eval($block);
            ob_end_clean();
            return array('konto_id' => $konto_id, 'lev_varenr' => $lev_varenr[0], 'pris' => (float)$pris[0], 'picked' => !empty($pickedLevRow));
        }

        public function testANullNumberOnAnotherSupplierDoesNotWin(): void
        {
            $this->suppliers(array(array(1, 788, null, 2175.0), array(2, 2225, 'Bartelse', 1000.0)));
            $got = $this->pick('Bartelse');
            self::assertSame(2225, $got['konto_id'], 'the supplier with lev_varenr NULL was ranked first');
            self::assertSame('Bartelse', $got['lev_varenr']);
            self::assertSame(1000.0, $got['pris']);
        }

        /** Codex round 2: the search list shows the number trimmed, so a stored ' Bartelse ' must still be the picked row. */
        public function testAPaddedStoredNumberStillWinsOverALowerPositionSupplier(): void
        {
            $this->suppliers(array(array(1, 788, null, 2175.0), array(2, 2225, ' Bartelse ', 1000.0)));
            $got = $this->pick('Bartelse');
            self::assertSame(2225, $got['konto_id'], 'the padded number lost to supplier no. 1');
            self::assertSame('Bartelse', $got['lev_varenr'], 'the line should carry the number as the list showed it');
            self::assertSame(1000.0, $got['pris']);
            self::assertTrue($got['picked']);
        }

        /** PHP trim() also removes tabs and line breaks, so the SQL side must too. */
        public function testANumberWithATabAndALineBreakAroundItStillWins(): void
        {
            $this->suppliers(array(array(1, 788, 'CU-1', 2175.0), array(2, 2225, "\tBartelse\r\n", 1000.0)));
            $got = $this->pick('Bartelse');
            self::assertSame(2225, $got['konto_id']);
            self::assertSame('Bartelse', $got['lev_varenr']);
        }

        public function testAnEmptyNumberOnAnotherSupplierDoesNotWin(): void
        {
            $this->suppliers(array(array(1, 788, '', 2175.0), array(2, 2225, 'Bartelse', 1000.0)));
            self::assertSame(2225, $this->pick('Bartelse')['konto_id']);
        }

        public function testThePickedNumberAndPriceAreKeptWhenOneSupplierHasSeveralNumbers(): void
        {
            $this->suppliers(array(array(1, 57, 'A-100', 10.0), array(2, 57, 'B-200', 20.0)));
            $got = $this->pick('B-200');
            self::assertSame(57, $got['konto_id']);
            self::assertSame('B-200', $got['lev_varenr'], 'another number of the same supplier reached the line');
            self::assertSame(20.0, $got['pris']);
            self::assertTrue($got['picked'], 'the later lookup by item and supplier would overwrite the picked row');
        }

        public function testAZeroPriceOnThePickedRowKeepsTheItemCost(): void
        {
            $this->suppliers(array(array(1, 2225, 'Bartelse', 0.0)));
            $got = $this->pick('Bartelse', 800.0);
            self::assertSame('Bartelse', $got['lev_varenr']);
            self::assertSame(800.0, $got['pris']);
        }

        public function testWithoutANumberSupplierNumberOneIsUsedAndTheLineIsLeftToTheUsualLookup(): void
        {
            $this->suppliers(array(array(2, 2225, 'Bartelse', 1000.0), array(1, 788, null, 2175.0)));
            $got = $this->pick('');
            self::assertSame(788, $got['konto_id']);
            self::assertSame('', $got['lev_varenr']);
            self::assertSame(800.0, $got['pris']);
            self::assertFalse($got['picked']);
        }

        public function testAnUnknownNumberFallsBackToSupplierNumberOne(): void
        {
            $this->suppliers(array(array(1, 788, 'CU-1', 636.8), array(2, 2225, 'Bartelse', 1000.0)));
            $got = $this->pick('NOT-THERE');
            self::assertSame(788, $got['konto_id']);
            self::assertFalse($got['picked']);
        }

        public function testAnApostropheInTheNumberIsEscaped(): void
        {
            $this->suppliers(array(array(1, 788, 'CU-1', 636.8), array(2, 2225, "O'Brien", 1000.0)));
            self::assertSame(2225, $this->pick("O'Brien")['konto_id']);
        }

        public function testASupplierRowWithoutAnAccountIsNotUsed(): void
        {
            $this->suppliers(array(array(1, 999, 'GONE', 1.0), array(2, 2225, 'Bartelse', 1000.0)));
            self::assertSame(2225, $this->pick('')['konto_id']);
        }

        public function testNoSupplierRowsGivesTheMessage(): void
        {
            $got = $this->pick('');
            self::assertNull($got['konto_id']);
            self::assertSame(array('transaktion:rollback', 'alert:618192'), Rec::$calls);
        }
    }
}
