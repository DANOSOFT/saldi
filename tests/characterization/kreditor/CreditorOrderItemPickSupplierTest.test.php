<?php
// 20261119 CL/MMR SST-1041 Runs the supplier lookup for an item picked in the search list on a new creditor order.
//
// MEDshop picked 112AHN in the search list on a new order without a supplier, and nothing happened: the page reloaded as «Leverandørordre 0».
// Typing the item number and pressing Enter has always used the item's supplier no. 1, so the pick now does the same.
// A pick from the Lev. varenr. search prefers the supplier of that number. An item with no supplier shows a message on both paths.
// kreditor/ordre.php prints on include, so the blocks are lifted from the source and run against recorded stubs.
// 20261119 CL/MMR SST-1041 Codex rounds 1-2: a Lev. varenr. pick carries that row's number and price (see the Sql test).
// 20261119 CL/MMR SST-1041 Codex round 6: the Enter-path message shows the typed number, not the SQL-escaped one.
// 20261119 CL/MMR SST-1041 Review (CodeRabbit): the pick sends the supplier row id (vare_lev_id), not the number.
// 20261119 CL/MMR SST-1041 Codex review round: the write check and the vare_id branch read the order id the same way; a non-numeric id is refused.
namespace CreditorOrderItemPickFixture {

    final class Rec
    {
        /** @var array<int, array{id: string, lev_id: int, posnr: int, lev_varenr: string, kostpris: string}> Supplier rows for the picked item. */
        public static array $suppliers = array();
        /** @var array<int, string> */
        public static array $queries = array();
        /** @var array<int, string> */
        public static array $calls = array();
    }

    /** Answers the supplier lookup with Rec::$suppliers in the order the SQL asks for. */
    function db_select($qtxt, $spor = '')
    {
        Rec::$queries[] = (string)$qtxt;
        $rows = Rec::$suppliers;
        usort($rows, function ($a, $b) {
            return $a['posnr'] <=> $b['posnr'];
        });
        if (strpos((string)$qtxt, 'limit 1') !== false) {
            $rows = array_slice($rows, 0, 1);
        }
        return new \ArrayIterator($rows);
    }

    function db_fetch_array($q)
    {
        if (!$q instanceof \ArrayIterator || !$q->valid()) {
            return false;
        }
        $row = $q->current();
        $q->next();
        return $row;
    }

    function db_escape_string($value)
    {
        return str_replace("'", "''", (string)$value);
    }

    function transaktion($what)
    {
        Rec::$calls[] = 'transaktion:' . $what;
    }

    function noSupplierForItemAlert($itemNo)
    {
        Rec::$calls[] = 'alert:' . $itemNo;
    }

    function findtekst($key, $sprog_id)
    {
        $parts = explode('|', (string)$key, 2);
        return $parts[1] ?? $parts[0];
    }

    /** Same contract as includes/std_func.php ifset() for the array cases the lifted code uses. */
    function ifset($arrayOrVar, $key = null, $default = null)
    {
        return is_array($arrayOrVar) && array_key_exists($key, $arrayOrVar) ? $arrayOrVar[$key] : $default;
    }
}

namespace Saldi\Tests\CreditorOrderItemPick {

    use CreditorOrderItemPickFixture\Rec;
    use PHPUnit\Framework\TestCase;

    final class CreditorOrderItemPickSupplierTest extends TestCase
    {
        private const ORDRE = __DIR__ . '/../../../kreditor/ordre.php';
        private const FIXTURE = 'CreditorOrderItemPickFixture';

        protected function setUp(): void
        {
            Rec::$suppliers = array();
            Rec::$queries = array();
            Rec::$calls = array();
        }

        /** Source between $from and $before, with the stubbed functions pointed at the fixture namespace. */
        private static function slice(string $from, string $before): string
        {
            $src = file_get_contents(self::ORDRE);
            self::assertNotFalse($src, 'could not read kreditor/ordre.php');
            $at = strpos($src, $from);
            self::assertNotFalse($at, "the block starting \"$from\" is gone");
            $end = strpos($src, $before, $at);
            self::assertNotFalse($end, "the block no longer reaches \"$before\"");
            $block = substr($src, $at, $end - $at);
            foreach (array('db_select', 'db_fetch_array', 'db_escape_string', 'transaktion', 'noSupplierForItemAlert', 'findtekst', 'ifset') as $fn) {
                $block = preg_replace('/(?<![\w\\\\>$])(?<!function )' . $fn . '\(/', '\\' . self::FIXTURE . '\\' . $fn . '(', $block);
            }
            return $block;
        }

        /**
         * Runs the GET vare_id branch's new supplier lookup.
         *
         * @param array<string, string> $get The query string.
         * @return array{konto_id: mixed, exited: bool, output: string, lev_varenr: mixed, pris: mixed, picked: bool}
         */
        private function pick(int $id, $kontoId, array $get, string $itemNo = '112AHN'): array
        {
            $block = self::slice("if (!\$id && !\$konto_id) {\n\t\t// SST-1041", 'if ((!$pris[0] || !$lev_varenr[0]) && $vare_id[0] && $konto_id');
            $block = str_replace('exit;', 'return true;', $block);
            $_GET = $get;
            $vare_id = array(0 => $get['vare_id']);
            $varenr = array(0 => $itemNo);
            $pris = array(0 => 105.0);
            $lev_varenr = array(0 => '');
            $konto_id = $kontoId;
            ob_start();
            $exited = (bool)eval($block);
            $output = (string)ob_get_clean();
            return array('konto_id' => $konto_id, 'exited' => $exited, 'output' => $output,
                'lev_varenr' => $lev_varenr[0], 'pris' => $pris[0], 'picked' => !empty($pickedLevRow));
        }

        /** 112AHN at Harald Nyborg (posnr 1) and a second supplier (posnr 2). */
        private static function twoSuppliers(): array
        {
            return array(
                array('id' => '861', 'lev_id' => 77, 'posnr' => 2, 'lev_varenr' => 'OTHER-1', 'kostpris' => '120.000'),
                array('id' => '860', 'lev_id' => 57, 'posnr' => 1, 'lev_varenr' => '37761', 'kostpris' => '100.000'),
            );
        }

        public function testAPickOnANewOrderWithoutSupplierTakesSupplierNumberOne(): void
        {
            Rec::$suppliers = self::twoSuppliers();
            $got = $this->pick(0, null, array('id' => '', 'vare_id' => '887'));
            self::assertSame(57, $got['konto_id'], 'the order did not get the item\'s supplier no. 1');
            self::assertFalse($got['picked'], 'without a Lev. varenr. the line is left to the usual supplier lookup');
            self::assertSame(105.0, $got['pris']);
            self::assertFalse($got['exited']);
            self::assertSame(array(), Rec::$calls);
            self::assertStringContainsString("vare_lev.vare_id = '887'", Rec::$queries[0]);
            self::assertStringContainsString('adresser.id = vare_lev.lev_id', Rec::$queries[0], 'a supplier row without an account must not be used, as on the Enter path');
            self::assertStringContainsString('order by vare_lev.posnr', Rec::$queries[0]);
        }

        public function testALevVarenrPickTakesThePickedSupplierRow(): void
        {
            Rec::$suppliers = self::twoSuppliers();
            $got = $this->pick(0, null, array('id' => '', 'vare_id' => '887', 'vare_lev_id' => '861'));
            self::assertSame(77, $got['konto_id'], 'the picked supplier row was not used');
            self::assertSame('OTHER-1', $got['lev_varenr']);
            self::assertSame('120.000', $got['pris']);
            self::assertTrue($got['picked']);
        }

        public function testAnUnknownSupplierRowFallsBackToSupplierNumberOne(): void
        {
            Rec::$suppliers = self::twoSuppliers();
            $got = $this->pick(0, null, array('id' => '', 'vare_id' => '887', 'vare_lev_id' => '999'));
            self::assertSame(57, $got['konto_id']);
            self::assertFalse($got['picked']);
        }

        public function testTheSupplierRowIdIsCastAndNotPutInTheQuery(): void
        {
            Rec::$suppliers = self::twoSuppliers();
            $got = $this->pick(0, null, array('id' => '', 'vare_id' => '887', 'vare_lev_id' => "861' or '1'='1"));
            self::assertStringNotContainsString("or '1'", Rec::$queries[0]);
            self::assertSame(77, $got['konto_id'], '(int) of the value is the row id');
        }

        public function testTheItemIdIsCastToAnInteger(): void
        {
            Rec::$suppliers = self::twoSuppliers();
            $this->pick(0, null, array('id' => '', 'vare_id' => "887' or '1'='1"));
            self::assertStringContainsString("vare_lev.vare_id = '887'", Rec::$queries[0]);
        }

        public function testAnItemWithoutSupplierRollsBackShowsTheMessageAndReloads(): void
        {
            $got = $this->pick(0, null, array('id' => '', 'vare_id' => '4755'), '9888-EU');
            self::assertTrue($got['exited'], 'the branch went on without a supplier');
            self::assertSame(array('transaktion:rollback', 'alert:9888-EU'), Rec::$calls);
            self::assertStringContainsString('URL=ordre.php"', $got['output']);
        }

        public function testAnExistingOrderKeepsItsSupplier(): void
        {
            Rec::$suppliers = self::twoSuppliers();
            $got = $this->pick(169791, 57, array('id' => '169791', 'vare_id' => '887'));
            self::assertSame(57, $got['konto_id']);
            self::assertSame(array(), Rec::$queries, 'an order that already exists must not look up a supplier');
        }

        public function testANewOrderWithASupplierChosenFirstKeepsIt(): void
        {
            Rec::$suppliers = self::twoSuppliers();
            $got = $this->pick(0, 77, array('id' => '', 'vare_id' => '887'));
            self::assertSame(77, $got['konto_id']);
            self::assertSame(array(), Rec::$queries);
        }

        /**
         * Runs the Enter path's handling of the supplier lookup result.
         *
         * @param array<string, mixed>|false $r The lookup row, or false when nothing was found.
         * @return array{konto_id: mixed, firmanavn: mixed}
         */
        private function enterPath($r): array
        {
            $block = self::slice("\$konto_id  = ifset(\$r, 'konto_id', 0);", "include_once(__DIR__ . '/orderIncludes/insertAccount.php');\n\t\t\t\$id = insertAccount(\$id, \$konto_id, false);");
            $varenr = array(0 => "QA''ITEM");
            $_POST = array('vare0' => " qa'item ");
            eval($block);
            return array('konto_id' => $konto_id, 'firmanavn' => $firmanavn);
        }

        public function testEnterWithAnItemWithoutSupplierShowsTheMessage(): void
        {
            $got = $this->enterPath(false);
            self::assertSame(0, $got['konto_id']);
            self::assertSame('', $got['firmanavn']);
            self::assertSame(array("alert:QA'ITEM"), Rec::$calls, 'the message must show the typed number, not the SQL-escaped one');
        }

        public function testEnterWithASupplierIsUnchanged(): void
        {
            $got = $this->enterPath(array('vare_id' => 887, 'konto_id' => 57, 'firmanavn' => 'Harald Nyborg'));
            self::assertSame(57, $got['konto_id']);
            self::assertSame('Harald Nyborg', $got['firmanavn']);
            self::assertSame(array(), Rec::$calls);
        }

        /** The message function itself, with an item number that would break out of a quoted script string. */
        public function testTheMessageEscapesTheItemNumber(): void
        {
            $fn = self::slice('function noSupplierForItemAlert($itemNo) {', "\n######");
            $fn = str_replace('function noSupplierForItemAlert(', 'function sst1041Alert(', $fn);
            if (!function_exists('sst1041Alert')) {
                eval($fn);
            }
            ob_start();
            \sst1041Alert("A'B</script><b>");
            $out = (string)ob_get_clean();
            self::assertStringStartsWith('<script type="text/javascript">alert("', $out);
            self::assertStringNotContainsString("A'B", $out);
            self::assertStringNotContainsString('</script><b>', $out);
            $escaped = implode('', array('A', '\\', 'u0027B', '\\', 'u003C', '\\', '/script', '\\', 'u003E', '\\', 'u003Cb', '\\', 'u003E'));
            self::assertStringContainsString('Der blev ikke fundet en leverandør til varenummer ' . $escaped . '.', $out);
        }

        /**
         * Review (CodeRabbit): a vare_id without an order id creates the order, so the page must declare write before online.php.
         *
         * @param array<string, string> $get
         */
        private function declaredLevel(array $get): ?string
        {
            $block = self::slice("// A konto_id without an order id creates a purchase order from a GET link, so it needs write.", "\n\n\$id=\$konto_id=");
            $_GET = $get;
            $permission_level = null;
            eval($block);
            return $permission_level;
        }

        public function testANewOrderFromAnItemPickNeedsWrite(): void
        {
            self::assertSame('write', $this->declaredLevel(array('id' => '', 'vare_id' => '887')));
            self::assertSame('write', $this->declaredLevel(array('vare_id' => '887')));
            self::assertSame('write', $this->declaredLevel(array('id' => 'null', 'vare_id' => '887', 'vare_lev_id' => '861')));
            self::assertSame('write', $this->declaredLevel(array('konto_id' => '57')), 'the existing supplier-first rule must stay');
            self::assertNull($this->declaredLevel(array('id' => '169791')), 'opening an existing order stays read');
            self::assertNull($this->declaredLevel(array()), 'a blank new order form stays read');
        }

        /** Codex review round: the check reads the order id as the vare_id branch does, (int), so both agree on «new order». */
        public function testTheWriteCheckReadsTheOrderIdAsAnInteger(): void
        {
            foreach (array('abc', '0', ' 0', 'null', '') as $odd) {
                self::assertSame('write', $this->declaredLevel(array('id' => $odd, 'vare_id' => '887')), "id '$odd' is a new order for the vare_id branch, so it needs write");
            }
            self::assertSame('write', $this->declaredLevel(array('id' => array('1'), 'vare_id' => '887')), 'an array id is no order id');
            self::assertNull($this->declaredLevel(array('id' => '169791', 'vare_id' => '887')), 'an existing order stays as before');
        }

        /**
         * The order-id check for an item pick, which runs before the contact update and the item branch.
         *
         * @return array{refused: bool, output: string, warnings: array<int, string>}
         */
        private function branchStart($id): array
        {
            $block = self::slice("// SST-1041 An item pick (vare_id) with an order id that is not a whole number", "if (if_isset(\$_POST, NULL, 'copy'))");
            $block = str_replace('exit;', 'return true;', $block);
            $_GET = array('vare_id' => '887');
            $warnings = array();
            set_error_handler(function ($no, $msg) use (&$warnings) {
                $warnings[] = $msg;
                return true;
            });
            ob_start();
            try {
                $refused = (bool) eval($block);
            } finally {
                $out = (string) ob_get_clean();
                restore_error_handler();
            }
            return array('refused' => $refused, 'output' => $out, 'warnings' => $warnings);
        }

        /** The check must sit before every write on the path: the contact update comes right after it. */
        public function testTheOrderIdCheckComesBeforeTheContactUpdateAndTheItemBranch(): void
        {
            $src = (string) file_get_contents(__DIR__ . '/../../../kreditor/ordre.php');
            $check = strpos($src, '// SST-1041 An item pick (vare_id) with an order id that is not a whole number');
            $contact = strpos($src, 'if ( !empty($kontakt) && $id ) {');
            $branch = strpos($src, "if(isset(\$_GET['vare_id']) && \$_GET['vare_id']) {");
            self::assertNotFalse($check);
            self::assertNotFalse($contact);
            self::assertNotFalse($branch);
            self::assertLessThan($contact, $check);
            self::assertLessThan($branch, $contact);
        }

        public function testAnOrderIdThatIsNotAWholeNumberIsRefusedBeforeAnyWrite(): void
        {
            foreach (array('abc', '5abc', '-5', '1e3', '0x10', array('1'), array()) as $bad) {
                $got = $this->branchStart($bad);
                self::assertTrue($got['refused'], 'id ' . var_export($bad, true) . ' went on to the order creation');
                self::assertStringContainsString('URL=ordreliste.php', $got['output']);
                self::assertSame(array(), $got['warnings'], 'id ' . var_export($bad, true) . ' raised a PHP warning');
            }
            foreach (array('', 'null', '0', '169791', ' 169791 ', null) as $ok) {
                $got = $this->branchStart($ok);
                self::assertFalse($got['refused'], 'id ' . var_export($ok, true) . ' was refused');
                self::assertSame(array(), $got['warnings']);
            }
        }

        /** The keyed text is seeded, so translators see it. */
        public function testTheTextKeyIsSeeded(): void
        {
            $tsv = file_get_contents(__DIR__ . '/../../../importfiler/tekster_keyed.tsv');
            self::assertMatchesRegularExpression('/^creditor\.order\.no_supplier_for_item\tDer blev ikke fundet en leverandør til varenummer %s\. Vælg leverandøren under Kontonr\. først\.\t[^\t]+\t[^\t]+$/m', (string)$tsv);
        }
    }
}
