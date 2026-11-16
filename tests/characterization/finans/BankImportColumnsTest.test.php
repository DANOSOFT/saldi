<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// 20261106 CL/MMR SST-962 Created. The column choices of finans/bankimport.php (includes/stdFunc/bankImportColumns.php).
//                 Only the first 8 columns used to be remembered, so a card acquirer's file with the description in column 10 asked for it on every import.
//                 The choices are now kept for every column and per number of columns, so a bank's file and the acquirer's file keep their own.
//                 The settings table is stubbed in memory, and the files are synthetic.
// 20261116 CL/MMR SST-962 Review (CodeRabbit): the settings table is a private schema on the test database, so the save's lock and the
//                 duplicate merge run for real. New: «Vis» with a new splitter, «Hent» after one, duplicate rows, two first saves at once.
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BankImportColumnsTest extends TestCase
{
    private const FILE = __DIR__ . '/../../../includes/stdFunc/bankImportColumns.php';
    private const CHILD = __DIR__ . '/support/run_bank_import_save.php';

    /** A card-acquirer export: 13 columns, comma separated, description in column 10 (index 9). */
    private const ACQUIRER = "-,merchant_id,merchant_name,id,arn,type,processed_at,amount,currency,text_on_statement,reference,threed_secure,settlement\n"
        . "1,1000001,Testbutik ApS,a-1,700,capture,2026-10-26,100.00,DKK,testbutik.dk,000520000,full,S1\n"
        . "1,1000001,\"Testbutik, Nord\",a-2,701,refund,2026-10-26,37.50,DKK,testbutik.dk,000520001,full,S1\n";

    /** A bank's own export: 7 columns, semicolon separated, no header. */
    private const BANK = "26-10-2026;Overførsel;Kunde A betaling;26-10-2026;1.250,00;10.250,00;DKK\n"
        . "27-10-2026;Overførsel;Kunde B betaling;27-10-2026;-375,50;9.874,50;DKK\n";

    /** Split on commas it has 4 columns, split on semicolons 3 (the example in the review). */
    private const MIXED = "2026-01-01;invoice, item, note, another;100\n"
        . "2026-01-02;invoice, item, note, again;200\n";

    private string $database = '';
    private string $schema = '';

    protected function setUp(): void
    {
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $this->database = TestDatabase::sqlFixture(static::class)->name();
        $connection = TestDatabase::connect($this->database);
        $this->schema = 'sst962_' . bin2hex(random_bytes(6));
        pg_query($connection, 'CREATE SCHEMA ' . $this->schema);
        pg_query($connection, 'SET search_path TO ' . $this->schema);
        pg_query($connection, "SET statement_timeout = '15s'");
        // As on live: no unique index on settings (master adds one at login, includes/settings/uniqueIndex.php).
        pg_query($connection, 'CREATE TABLE settings (id serial PRIMARY KEY, var_name text, var_grp text, var_value text,
            var_description text, user_id integer, pos_id integer)');
        $GLOBALS['connection'] = $connection;
        $GLOBALS['db_type'] = 'postgres';
        $GLOBALS['db'] = $this->database;
        require_once __DIR__ . '/support/bank_import_settings_database.php';
        require_once __DIR__ . '/../../../includes/std_func.php'; // the real ifset()
        require_once self::FILE;
    }

    protected function tearDown(): void
    {
        if (!empty($GLOBALS['connection'])) {
            pg_query($GLOBALS['connection'], 'DROP SCHEMA IF EXISTS ' . $this->schema . ' CASCADE');
            pg_close($GLOBALS['connection']);
            $GLOBALS['connection'] = null;
        }
    }

    /** @return array<int, array{id: int, var_name: string, user_id: int, var_value: string}> */
    private static function rows(string $where = "var_grp = 'bankimport'"): array
    {
        $result = pg_query($GLOBALS['connection'], "select id, var_name, user_id, var_value from settings where $where order by id");
        $rows = [];
        while ($r = pg_fetch_assoc($result)) {
            $rows[] = ['id' => (int)$r['id'], 'var_name' => $r['var_name'], 'user_id' => (int)$r['user_id'], 'var_value' => $r['var_value']];
        }
        return $rows;
    }

    private static function value(int $userId, int $columns): ?string
    {
        $rows = self::rows("var_grp = 'bankimport' and var_name = 'columns_$columns' and user_id = $userId");
        self::assertLessThanOrEqual(1, count($rows), "one row for user $userId, $columns columns");
        return $rows ? $rows[0]['var_value'] : null;
    }

    private static function file(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bankimport');
        file_put_contents($path, $content);
        return $path;
    }

    public function testLastColumnOfTheAcquirerFile(): void
    {
        self::assertSame(12, bank_import_file_last_column(self::file(self::ACQUIRER), 'UTF-8'), 'a quoted comma does not count as a column');
    }

    public function testLastColumnOfTheBankFile(): void
    {
        self::assertSame(6, bank_import_file_last_column(self::file(self::BANK), 'UTF-8'));
    }

    public function testUnreadableOrSeparatorlessFileGivesMinusOne(): void
    {
        self::assertSame(-1, bank_import_file_last_column('/nonexistent/bankfile.csv', 'UTF-8'));
        self::assertSame(-1, bank_import_file_last_column(self::file("just text\nmore text\n"), 'UTF-8'));
    }

    public function testChoiceInColumnTenSurvivesTheNextUpload(): void
    {
        $posted = [6 => 'dato', 7 => 'belob', 8 => 'currency', 9 => 'beskrivelse'];
        self::assertTrue(bank_import_save_columns(27, $posted, 12));
        $legacy = [0 => 'dato', 2 => 'beskrivelse', 4 => 'belob', 7 => 'currency'];
        $columns = bank_import_columns_for_upload(27, self::file(self::ACQUIRER), 'UTF-8', $legacy);
        self::assertCount(13, $columns, 'one entry per column, so vis_data() shows them all');
        self::assertSame('beskrivelse', $columns[9]);
        self::assertSame('currency', $columns[8]);
        self::assertSame('', $columns[0]);
    }

    public function testEachLayoutKeepsItsOwnChoices(): void
    {
        bank_import_save_columns(27, [0 => 'dato', 2 => 'beskrivelse', 4 => 'belob', 6 => 'currency'], 6);
        bank_import_save_columns(27, [6 => 'dato', 7 => 'belob', 9 => 'beskrivelse'], 12);
        $bank = bank_import_columns_for_upload(27, self::file(self::BANK), 'UTF-8', []);
        $acquirer = bank_import_columns_for_upload(27, self::file(self::ACQUIRER), 'UTF-8', []);
        self::assertSame(['dato', '', 'beskrivelse', '', 'belob', '', 'currency'], $bank);
        self::assertSame('beskrivelse', $acquirer[9]);
        self::assertSame('dato', $acquirer[6]);
    }

    public function testWithoutSavedChoicesTheLegacyOnesArePaddedToTheFile(): void
    {
        $legacy = [0 => 'dato', 1 => '', 2 => 'beskrivelse', 3 => '', 4 => 'belob', 5 => '', 6 => '', 7 => 'currency'];
        $columns = bank_import_columns_for_upload(27, self::file(self::ACQUIRER), 'UTF-8', $legacy);
        self::assertCount(13, $columns);
        self::assertSame('beskrivelse', $columns[2]);
        self::assertSame('', $columns[12]);
    }

    public function testUnknownNamesAreNeverStored(): void
    {
        bank_import_save_columns(27, [0 => "dato', box1='x", 1 => 'beskrivelse', 2 => 'belob'], 2);
        self::assertSame(',beskrivelse,belob', self::value(27, 3));
    }

    public function testNothingChosenStoresNothing(): void
    {
        self::assertFalse(bank_import_save_columns(27, [0 => '', 1 => ''], 1));
        self::assertFalse(bank_import_save_columns(0, [0 => 'dato'], 1));
        self::assertSame([], self::rows('true'));
    }

    public function testBankimportCallsTheHelpers(): void
    {
        $src = (string) file_get_contents(__DIR__ . '/../../../finans/bankimport.php');
        $save = strpos($src, 'bank_import_columns_for_post(');
        $firstPrint = strpos($src, "if (\$menu=='T') {");
        self::assertNotFalse($save);
        self::assertNotFalse($firstPrint);
        self::assertLessThan($firstPrint, $save, 'the choices are saved before the page prints');
        self::assertStringContainsString('bank_import_columns_for_upload($bruger_id, $filnavn, $charset, $feltnavn)', $src);
        self::assertStringContainsString('name=\\"previewSplitter\\"', $src, 'the preview posts the splitter it was made with');
    }

    public function testAChosenSplitterCountsItsOwnColumns(): void
    {
        $file = self::file(self::MIXED);
        self::assertSame(3, bank_import_file_last_column($file, 'UTF-8'), 'commas win the automatic choice');
        self::assertSame(3, bank_import_file_last_column($file, 'UTF-8', 'Komma'));
        self::assertSame(2, bank_import_file_last_column($file, 'UTF-8', 'Semikolon'));
        self::assertSame(0, bank_import_file_last_column($file, 'UTF-8', 'Tabulator'));
        self::assertSame(-1, bank_import_file_last_column($file, 'UTF-8', 'Pipe'));
    }

    /** Review r4491397564 and r4491397593: «Vis» after a new splitter saves nothing and restores the new width's choices. */
    public function testVisWithANewSplitterSavesNothingAndShowsTheChoicesOfTheNewWidth(): void
    {
        $file = self::file(self::MIXED);
        bank_import_save_columns(27, ['dato', 'beskrivelse', '', 'belob'], 3);
        bank_import_save_columns(27, ['dato', 'beskrivelse', 'belob'], 2);
        $posted = [0 => 'belob', 1 => '', 2 => 'dato', 3 => 'beskrivelse'];
        $shown = bank_import_columns_for_post(27, $file, 'UTF-8', $posted, 'Semikolon', 'Komma', true);
        self::assertSame(['dato', 'beskrivelse', 'belob'], $shown);
        self::assertSame('dato,beskrivelse,,belob', self::value(27, 4), 'the old width is not overwritten');
        self::assertSame('dato,beskrivelse,belob', self::value(27, 3), 'the new width is not overwritten');

        // and back again
        $shown = bank_import_columns_for_post(27, $file, 'UTF-8', ['belob', 'dato', ''], 'Komma', 'Semikolon', true);
        self::assertSame(['dato', 'beskrivelse', '', 'belob'], $shown);
        self::assertSame('dato,beskrivelse,,belob', self::value(27, 4));
        self::assertSame('dato,beskrivelse,belob', self::value(27, 3));
    }

    public function testWithNothingSavedForTheNewWidthThePostedChoicesArePaddedToIt(): void
    {
        $file = self::file(self::MIXED);
        $shown = bank_import_columns_for_post(27, $file, 'UTF-8', ['dato', 'beskrivelse', 'belob'], 'Komma', 'Semikolon', true);
        self::assertSame(['dato', 'beskrivelse', 'belob', ''], $shown);
        $shown = bank_import_columns_for_post(27, $file, 'UTF-8', ['dato', 'beskrivelse', 'belob', ''], 'Semikolon', 'Komma', true);
        self::assertSame(['dato', 'beskrivelse', 'belob'], $shown);
        self::assertSame([], self::rows(), 'nothing is saved on a splitter change');
    }

    public function testVisWithTheSameSplitterSavesForTheShownPreviewNotThePostedCount(): void
    {
        $file = self::file(self::MIXED);
        bank_import_save_columns(27, ['dato', 'beskrivelse', '', 'belob'], 3);
        $posted = ['dato', 'beskrivelse', 'belob'];
        self::assertSame($posted, bank_import_columns_for_post(27, $file, 'UTF-8', $posted, 'Semikolon', 'Semikolon', true));
        self::assertSame('dato,beskrivelse,belob', self::value(27, 3));
        self::assertSame('dato,beskrivelse,,belob', self::value(27, 4), 'another width is left alone');
    }

    public function testHentAfterASplitterChangeSavesForThePreviewItImports(): void
    {
        $file = self::file(self::MIXED);
        bank_import_columns_for_post(27, $file, 'UTF-8', ['dato', 'beskrivelse', '', 'belob'], 'Semikolon', 'Komma', false);
        self::assertSame('dato,beskrivelse,,belob', self::value(27, 4));
        self::assertNull(self::value(27, 3));
    }

    public function testAFormWithoutThePreviewFieldTakesThePostedSplitter(): void
    {
        $file = self::file(self::MIXED);
        bank_import_columns_for_post(27, $file, 'UTF-8', ['dato', 'beskrivelse', 'belob'], 'Semikolon', null, true);
        self::assertSame('dato,beskrivelse,belob', self::value(27, 3));
    }

    public function testAPreviewWithoutASplitterSavesNothing(): void
    {
        $file = self::file(self::MIXED);
        bank_import_columns_for_post(27, $file, 'UTF-8', ['dato', 'beskrivelse', 'belob'], 'Semikolon', '', true);
        bank_import_columns_for_post(27, $file, 'UTF-8', ['dato', 'beskrivelse', 'belob'], 'Semikolon', '', false);
        bank_import_columns_for_post(27, $file, 'UTF-8', ['dato', 'beskrivelse', 'belob'], ['x'], ['y'], true);
        bank_import_columns_for_post(27, $file, 'UTF-8', ['dato', 'beskrivelse', 'belob'], 'Komma', ['Komma'], false);
        self::assertSame([], self::rows());
    }

    /** Review r4491397574: rows left by an earlier race are read the same way every time and merged by the next save. */
    public function testDuplicateRowsAreReadDeterministicallyAndMergedOnSave(): void
    {
        $insert = "insert into settings (var_name, var_grp, var_value, var_description, user_id) values ";
        pg_query($GLOBALS['connection'], $insert . "('columns_3', 'bankimport', 'dato,beskrivelse,belob', '', 27),
            ('columns_3', 'bankimport', 'belob,dato,beskrivelse', '', 27), ('columns_3', 'bankimport', 'beskrivelse,belob,dato', '', 27),
            ('columns_3', 'bankimport', 'belob,beskrivelse,dato', '', null),
            ('columns_3', 'bankimport', 'dato,belob,beskrivelse', '', 28), ('columns_4', 'bankimport', 'dato,,beskrivelse,belob', '', 27),
            ('columns_3', 'other', 'keep', '', 27)");
        for ($i = 0; $i < 5; $i++) {
            self::assertSame(['beskrivelse', 'belob', 'dato'], bank_import_saved_columns(27, 2), 'the newest row of the user');
        }
        self::assertTrue(bank_import_save_columns(27, ['dato', 'beskrivelse', 'belob'], 2));
        self::assertSame('dato,beskrivelse,belob', self::value(27, 3));
        self::assertSame(3, self::rows("var_grp = 'bankimport' and var_name = 'columns_3' and user_id = 27")[0]['id'], 'the newest row is the one kept');
        self::assertCount(1, self::rows("var_grp = 'bankimport' and user_id is null"), 'a row without a user is user 0, not user 27');
        self::assertSame('dato,belob,beskrivelse', self::value(28, 3), 'another user is left alone');
        self::assertSame('dato,,beskrivelse,belob', self::value(27, 4), 'another width is left alone');
        self::assertCount(1, self::rows("var_grp = 'other'"), 'other settings are left alone');
    }

    /** Review r4491397574: two first saves at once, each on its own connection, leave one row. */
    public function testTwoFirstSavesAtOnceLeaveOneRow(): void
    {
        $observer = TestDatabase::connect($this->database);
        $lock = pg_escape_string($observer, bank_import_columns_lock_name(27, 2));
        pg_query($observer, "select pg_advisory_lock(hashtext('$lock'))");
        $children = [];
        foreach (['dato,beskrivelse,belob', 'belob,dato,beskrivelse'] as $columns) {
            $cmd = [PHP_BINARY, self::CHILD, $this->database, $this->schema, '27', '2', $columns];
            $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($proc);
            fclose($pipes[0]);
            $children[] = [$proc, $pipes];
        }
        try {
            $waiting = 0;
            for ($i = 0; $i < 200 && $waiting < 2; $i++) {
                usleep(50000);
                $waiting = (int)pg_fetch_result(pg_query($observer, "select count(*) from pg_locks where locktype = 'advisory' and not granted
                    and database = (select oid from pg_database where datname = current_database())"), 0, 0);
            }
            self::assertSame(2, $waiting, 'both saves wait for the lock');
            self::assertSame([], self::rows(), 'neither save looked for the row before it had the lock');
        } finally {
            pg_query($observer, "select pg_advisory_unlock(hashtext('$lock'))");
            pg_close($observer);
        }
        foreach ($children as [$proc, $pipes]) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($proc), $out . $err);
            self::assertSame("saved\n", $out, $err);
        }
        $rows = self::rows();
        self::assertCount(1, $rows, 'one row for the user and number of columns');
        self::assertContains($rows[0]['var_value'], ['dato,beskrivelse,belob', 'belob,dato,beskrivelse']);
        $first = bank_import_saved_columns(27, 2);
        self::assertSame($first, bank_import_saved_columns(27, 2));
        self::assertSame(explode(',', $rows[0]['var_value']), $first);
    }
}
