<?php
// 20261116 CL/MMR SST-1023 Runs the batch window's Gem (save) block instead of reading it.
//                  On PHP 8 the block threw a TypeError when a Vælg field was left empty or the
//                  hidden lager field was empty, which the customer saw as a blank window.
//                  The block also put the line id from the address and the chosen credit line
//                  into SQL as received. The tests lift the line-id assignment and the save
//                  block out of debitor/batch.php, run them against a recording db_modify(), and
//                  check both the crash and what reaches SQL.
namespace BatchWindowSaveFixture {

    final class Db
    {
        /** @var array<int, string> */
        public static array $writes = array();
    }

    function db_modify($sql, $context = null)
    {
        Db::$writes[] = (string)$sql;
        return true;
    }

    /** Array/key form of includes/std_func.php ifset(), the only form the save block uses. */
    function ifset($arrayOrVar, $key = null, $default = null)
    {
        if ($key === null) {
            return isset($arrayOrVar) ? $arrayOrVar : $default;
        }
        return (is_array($arrayOrVar) && array_key_exists($key, $arrayOrVar) && $arrayOrVar[$key] !== null)
            ? $arrayOrVar[$key] : $default;
    }
}

namespace {

    use PHPUnit\Framework\TestCase;

    final class BatchWindowSaveTest extends TestCase
    {
        private const BATCH = __DIR__ . '/../../../debitor/batch.php';

        /**
         * Lifts `$linje_id = …` and the save block (from `if ($_POST['submit']){` to the
         * display half's `$leveres=0;`) and runs them with the given request.
         *
         * @param array<string, string> $get
         * @param array<string, mixed> $post
         * @return array{writes: array<int, string>, output: string}
         */
        private function save(array $get, array $post): array
        {
            $src = file_get_contents(self::BATCH);
            self::assertNotFalse($src, 'could not read debitor/batch.php');
            self::assertSame(1, preg_match('/^\$linje_id\s*=.*$/m', $src, $m), 'the line-id assignment moved');
            $assign = $m[0];
            $from = strpos($src, "if (\$_POST['submit']){");
            $to = strpos($src, "\n\$leveres=0;\n");
            self::assertNotFalse($from, 'the save block no longer starts at if ($_POST[\'submit\'])');
            self::assertNotFalse($to, 'the display half no longer starts at $leveres=0');
            $block = $assign . "\n" . substr($src, $from, $to - $from);

            \BatchWindowSaveFixture\Db::$writes = array();
            $savedGet = $_GET;
            $savedPost = $_POST;
            $_GET = $get;
            $_POST = $post;
            ob_start();
            try {
                eval('namespace BatchWindowSaveFixture; ' . $block);
            } finally {
                $output = ob_get_clean();
                $_GET = $savedGet;
                $_POST = $savedPost;
            }
            return array('writes' => \BatchWindowSaveFixture\Db::$writes, 'output' => $output);
        }

        /** The form as the window posts it for a sales line with two batches in stock. */
        private static function salesForm(array $overrides = array()): array
        {
            return array_merge(array(
                'submit' => 'Gem', 'vare_id' => '2364', 'antal' => '1.000', 'batch_antal' => '2',
                'art' => 'DO', 'status' => '1', 'lager' => '', 'rest' => 'Array',
                'valg_1' => '', 'ordrenr_1' => '16801', 'batch_kob_id_1' => '177873', 'rest_1' => '5.000',
                'valg_2' => '1', 'ordrenr_2' => '16802', 'batch_kob_id_2' => '178615', 'rest_2' => '2.000',
            ), $overrides);
        }

        public function testAnEmptyVælgFieldAndAnEmptyLagerSaveTheChoice(): void
        {
            $r = $this->save(array('linje_id' => '769118'), self::salesForm());
            self::assertSame(array(
                'delete from reservation where linje_id=769118',
                'delete from reservation where batch_salg_id=-769118',
                'insert into reservation (linje_id, vare_id, batch_kob_id, antal, lager) values (769118, 2364, 178615, 1, 0)',
            ), $r['writes']);
        }

        public function testTheVælgFieldTakesADotOrACommaAsTheDecimalSign(): void
        {
            foreach (array('1.5', '1,5') as $typed) {
                $r = $this->save(array('linje_id' => '769118'),
                    self::salesForm(array('antal' => '2.000', 'valg_2' => $typed)));
                self::assertSame(
                    'insert into reservation (linje_id, vare_id, batch_kob_id, antal, lager) values (769118, 2364, 178615, 1.5, 0)',
                    $r['writes'][2], "Vælg «{$typed}»");
            }
        }

        public function testChoosingMoreThanThereIsStillOnlyWarns(): void
        {
            $r = $this->save(array('linje_id' => '769118'), self::salesForm(array('valg_2' => '3')));
            self::assertSame(array(), $r['writes']);
            self::assertStringContainsString('Der kan ikke v&aelig;lges flere end', $r['output']);
        }

        public function testAMalformedLineIdCannotWidenTheDelete(): void
        {
            $r = $this->save(array('linje_id' => '769118 OR 1=1'), self::salesForm());
            self::assertSame('delete from reservation where linje_id=769118', $r['writes'][0]);
            foreach ($r['writes'] as $sql) {
                self::assertStringNotContainsString('OR 1=1', $sql);
            }
        }

        public function testTheCreditBranchStoresTheChosenLineAsANumber(): void
        {
            $form = array('submit' => 'Gem', 'vare_id' => '2364', 'antal' => '-1', 'batch_antal' => '2',
                'art' => 'DO', 'status' => '1', 'lager' => '', 'valg_1' => '2',
                'kred_linje_id' => array(1 => '700001', 2 => '700002 where 1=1 --'));
            $r = $this->save(array('linje_id' => '769118'), $form);
            self::assertSame(array('update ordrelinjer set kred_linje_id=700002 where id=769118'), $r['writes']);

            $form['valg_1'] = '3'; // «Opret som indkøb» is the row after the last sale
            $r = $this->save(array('linje_id' => '769118'), $form);
            self::assertSame(array('update ordrelinjer set kred_linje_id=-1 where id=769118'), $r['writes']);
        }
    }
}
