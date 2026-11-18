<?php
// 20261118 CL/MMR SST-1033 Runs the receipt half of kreditor/modtag.php's lot loop against recorded queries.

// A receipt of a batch-controlled item (group box8 and box9 on) is its own lot, so it must not cover negative rest on older lots.
// MEDshop received 5 x 966040 and 1 went to a 2021 return lot, after which an order of 4 could not be delivered.
// Items without batch control keep covering negative rest as before (cost price of earlier sales).
namespace ModtagLotRestFixture {

    final class Db
    {
        /** @var array<int, string> */
        public static array $writes = array();
        /** @var array<string, array<int, array<string, mixed>>> */
        public static array $rows = array();
    }

    /** Returns the rows registered for the first fixture key the query contains. */
    function db_select($qtxt, $spor = '')
    {
        foreach (Db::$rows as $needle => $rows) {
            if (strpos((string)$qtxt, $needle) !== false) {
                return new \ArrayIterator($rows);
            }
        }
        return new \ArrayIterator(array());
    }

    function db_fetch_array($q)
    {
        if (!$q->valid()) {
            return false;
        }
        $row = $q->current();
        $q->next();
        return $row;
    }

    function db_modify($qtxt, $spor = '')
    {
        Db::$writes[] = (string)$qtxt;
        return true;
    }

}

namespace {

    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\TestCase;

    final class ModtagLotRestTest extends TestCase
    {
        private const MODTAG = __DIR__ . '/../../../kreditor/modtag.php';

        private static function source(): string
        {
            $src = file_get_contents(self::MODTAG);
            self::assertNotFalse($src, 'could not read kreditor/modtag.php');
            return $src;
        }

        /**
         * Lifts the second line loop (the lot rest bookkeeping after the stock update) and runs it for one
         * received line: 5 units of item 2294 on purchase order 10, line 100, own lot 500.
         *
         * @param array<string, array<int, array<string, mixed>>> $rows
         * @return array<int, string> the db_modify() calls
         */
        private function receive(bool $lotOwnReceipt, array $rows): array
        {
            $src = self::source();
            $from = strpos($src, "\tfor (\$x=1; \$x<=\$linjeantal; \$x++) {\n\t\tif (\$leveres[\$x]<0) { #returnering af vare");
            $to = strpos($src, "\ttransaktion(\"commit\");", (int)$from);
            self::assertNotFalse($from, 'the lot loop no longer starts with the return branch');
            self::assertNotFalse($to, 'the lot loop no longer ends before the commit');
            $block = substr($src, $from, $to - $from);

            \ModtagLotRestFixture\Db::$writes = array();
            \ModtagLotRestFixture\Db::$rows = $rows;
            $id = 10;
            $levdate = '2026-11-18';
            $linjeantal = 1;
            $leveres = array(1 => 5);
            $vare_id = array(1 => 2294);
            $linje_id = array(1 => 100);
            $lager = array(1 => 1);
            $lotOwnReceipt = array(1 => $lotOwnReceipt);
            eval('namespace ModtagLotRestFixture; ' . $block);
            return \ModtagLotRestFixture\Db::$writes;
        }

        /**
         * The receipt's own lot, and a 2021 return lot on another purchase order left at rest -1.
         *
         * @return array<string, array<int, array{id: int, rest: int, kobsdate: string|null}>> rows per query fragment
         */
        private static function otherOrderRows(): array
        {
            return array(
                "ordre_id='10' and vare_id='2294' and linje_id='100'" => array(
                    array('id' => 500, 'rest' => 5, 'kobsdate' => '2026-11-18'),
                ),
                "ordre_id!='0' and ordre_id!='10' and vare_id='2294' and rest<'0'" => array(
                    array('id' => 81843, 'rest' => -1, 'kobsdate' => '2021-03-10'),
                ),
            );
        }

        /**
         * The receipt's own lot, and a hole (antal 0, ordre 0) from a sale before the item had a lot.
         *
         * @return array<string, array<int, array{id: int, rest: int, kobsdate: string|null}>> rows per query fragment
         */
        private static function holeRows(): array
        {
            return array(
                "ordre_id='10' and vare_id='2294' and linje_id='100'" => array(
                    array('id' => 500, 'rest' => 5, 'kobsdate' => '2026-11-18'),
                ),
                "antal='0' and ordre_id='0' and vare_id='2294' and rest<'0'" => array(
                    array('id' => 167696, 'rest' => -2, 'kobsdate' => null),
                ),
            );
        }

        /** @return array<string, array{0: string}> the name of the fixture method per case */
        public static function batchControlledCases(): array
        {
            return array(
                'negative rest on another purchase order' => array('otherOrderRows'),
                'hole from a sale without a lot' => array('holeRows'),
            );
        }

        #[DataProvider('batchControlledCases')]
        public function testABatchControlledReceiptKeepsAllItsUnits(string $rows): void
        {
            self::assertSame(array(), $this->receive(true, self::$rows()));
        }

        public function testAReceiptWithoutBatchControlStillCoversNegativeRestOnAnotherOrder(): void
        {
            self::assertSame(array(
                "update batch_kob set kobsdate='2021-03-10',rest='0' where id = 81843",
                "update batch_kob set kobsdate='2026-11-18',rest='4' where id = 500",
            ), $this->receive(false, self::otherOrderRows()));
        }

        public function testAReceiptWithoutBatchControlStillFillsAHole(): void
        {
            self::assertSame(array(
                "update batch_kob set kobsdate='2026-11-18',antal='2',ordre_id='10',linje_id='100',rest='0' where id = 167696",
                "update batch_kob set kobsdate='2026-11-18',antal=antal-2,rest='3' where id = '500'",
            ), $this->receive(false, self::holeRows()));
        }

        /**
         * box9 only takes the reservation() lot path when the group is also stock-managed (box8).
         *
         * @return array<string, array{0: string, 1: string, 2: bool}> box8, box9 and the expected flag per case
         */
        public static function groupCases(): array
        {
            return array(
                'stock-managed with batch control' => array('on', 'on', true),
                'stock-managed without batch control' => array('on', '', false),
                'batch control on a group that is not stock-managed' => array('', 'on', false),
                'neither' => array('', '', false),
            );
        }

        #[DataProvider('groupCases')]
        public function testOnlyStockManagedBatchControlledGroupsKeepTheReceiptOnItsOwnLot(string $box8, string $box9, bool $expected): void
        {
            self::assertSame(1, preg_match('/^\s*(\$lotOwnReceipt\[\$x\]\s*=.*;)\s*$/m', self::source(), $m),
                'the batch-control flag is no longer set in the stock loop');
            $x = 1;
            $lotOwnReceipt = array();
            eval($m[1]);
            self::assertSame($expected, $lotOwnReceipt[1]);
        }
    }
}
