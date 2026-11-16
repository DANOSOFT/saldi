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
// 20261116 CL/MMR SST-962 Codex round 3: the columns are counted as vis_data() splits them, so a quoted field holding the separator
//                 (Windows-1252 file) saves for the width the preview shows. A MySQL lock that is not granted saves nothing, and a lock is released on an exception.
//                 The acquirer file no longer quotes a comma in the middle of a line, because vis_data() splits such a field (tested on its own).
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BankImportColumnsTest extends TestCase
{
    private const FILE = __DIR__ . '/../../../includes/stdFunc/bankImportColumns.php';
    private const CHILD = __DIR__ . '/support/run_bank_import_save.php';

    /** A card-acquirer export: 13 columns, comma separated, description in column 10 (index 9). The first field may be quoted. */
    private const ACQUIRER = "-,merchant_id,merchant_name,id,arn,type,processed_at,amount,currency,text_on_statement,reference,threed_secure,settlement\n"
        . "1,1000001,Testbutik ApS,a-1,700,capture,2026-10-26,100.00,DKK,testbutik.dk,000520000,full,S1\n"
        . "\"1\",1000001,Testbutik Nord,a-2,701,refund,2026-10-26,37.50,DKK,testbutik.dk,000520001,full,S1\n";

    /**
     * Codex round 3: a Windows-1252 bank file with a separator inside quotes. vis_data() skips a quote that follows a
     * separator, so the quoted text is split and the preview has 4 columns with «Semikolon».
     */
    private const QUOTED = "01-10-2026;\"\xE6\xF8\xE5; betaling\";100,00\r\n"
        . "02-10-2026;\"\xC6\xD8\xC5; retur\";-37,50\r\n";

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
        self::assertSame(12, bank_import_file_last_column(self::file(self::ACQUIRER), 'UTF-8'), 'a quoted first field is one column');
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
        self::assertStringContainsString('$felt=bank_import_split_line($linje, $splittegn);', $src, 'the preview splits with the shared function');
        self::assertStringNotContainsString('$anftegn', $src, 'no second split loop is left in bankimport.php');
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

    /** The split vis_data() has always used, moved unchanged to bank_import_split_line(). Field numbers start at 1. */
    public function testTheSplitIsTheLegacyOne(): void
    {
        self::assertSame([1 => 'a', 2 => 'b', 3 => 'c'], bank_import_split_line('a;b;c', ';'));
        self::assertSame([1 => 'a;b', 2 => 'c'], bank_import_split_line('"a;b";c', ';'), 'a quoted first field keeps the separator');
        self::assertSame([1 => 'a', 2 => 'b', 3 => 'c'], bank_import_split_line('a;"b";c', ';'), 'quotes around a later field are dropped');
        self::assertSame([1 => 'a', 2 => 'b', 3 => ' c', 4 => 'd'], bank_import_split_line('a;"b; c";d', ';'), 'a later quoted field is split (legacy)');
        self::assertSame([1 => 'a', 2 => '', 3 => 'c'], bank_import_split_line('a;;c', ';'));
        self::assertSame([1 => 'a', 2 => ''], bank_import_split_line('a;', ';'));
        self::assertSame([1 => 'a,b', 2 => 'c'], bank_import_split_line("a,b\tc", "\t"));
        self::assertSame([], bank_import_split_line('', ';'));
    }

    /** Codex round 3: the preview of the quoted Windows-1252 file has 4 columns with «Semikolon», and that is the width counted. */
    public function testAQuotedSeparatorCountsTheColumnsThePreviewShows(): void
    {
        $file = self::file(self::QUOTED);
        foreach (['UTF-8', 'ISO-8859-15'] as $charset) {
            self::assertSame(3, bank_import_file_last_column($file, $charset), "semicolons win the automatic choice ($charset)");
            self::assertSame(3, bank_import_file_last_column($file, $charset, 'Semikolon'), $charset);
            self::assertSame(1, bank_import_file_last_column($file, $charset, 'Komma'), $charset);
        }
        $lines = bank_import_read_lines($file, 'UTF-8');
        self::assertSame([1 => '01-10-2026', 2 => 'æøå', 3 => ' betaling', 4 => '100,00'], bank_import_split_line(trim($lines[0]), ';'));
        self::assertSame([1 => '02-10-2026', 2 => 'ÆØÅ', 3 => ' retur', 4 => '-37,50'], bank_import_split_line(trim($lines[1]), ';'));
    }

    /**
     * Codex round 3, finding 2: with both widths saved, confirming the 4-column preview of the quoted file saves only
     * columns_4. Before, the save counted 3 columns (str_getcsv) and cut the choice into columns_3, losing «belob».
     */
    public function testConfirmingTheQuotedPreviewChangesOnlyItsOwnWidth(): void
    {
        $file = self::file(self::QUOTED);
        bank_import_save_columns(27, ['dato', 'beskrivelse', 'belob'], 2);
        bank_import_save_columns(27, ['dato', 'beskrivelse', '', 'belob'], 3);

        $shown = bank_import_columns_for_upload(27, $file, 'ISO-8859-15', ['dato', 'beskrivelse', 'belob']);
        self::assertSame(['dato', 'beskrivelse', '', 'belob'], $shown, 'the upload shows the 4-column choices');

        $posted = ['dato', 'beskrivelse', '', 'belob'];
        self::assertSame($posted, bank_import_columns_for_post(27, $file, 'ISO-8859-15', $posted, 'Semikolon', 'Semikolon', true));
        self::assertSame('dato,beskrivelse,belob', self::value(27, 3), 'the 3-column choices are left alone');
        self::assertSame('dato,beskrivelse,,belob', self::value(27, 4));

        $posted = ['dato', '', 'beskrivelse', 'belob'];
        bank_import_columns_for_post(27, $file, 'ISO-8859-15', $posted, 'Semikolon', 'Semikolon', false);
        self::assertSame('dato,beskrivelse,belob', self::value(27, 3), '«Hent» leaves the 3-column choices alone too');
        self::assertSame('dato,,beskrivelse,belob', self::value(27, 4));
        self::assertSame($posted, bank_import_columns_for_upload(27, $file, 'ISO-8859-15', []), 'the next upload shows them');
    }

    /** Makes get_lock()/release_lock() answer in the private schema, so the MySQL branch runs on the PostgreSQL test database. */
    private static function fakeMysqlLock(?int $result): void
    {
        $GLOBALS['db_type'] = 'mysqli';
        $value = $result === null ? 'null' : (string)$result;
        pg_query($GLOBALS['connection'], "create table lock_calls (id serial primary key, fn text, name text)");
        pg_query($GLOBALS['connection'], "create function get_lock(text, integer) returns integer language sql as
            \$\$ insert into lock_calls (fn, name) values ('get', \$1); select $value::integer \$\$");
        pg_query($GLOBALS['connection'], "create function release_lock(text) returns integer language sql as
            \$\$ insert into lock_calls (fn, name) values ('release', \$1); select 1 \$\$");
    }

    /** @return string[] The lock calls in order, as "get" or "release". */
    private static function lockCalls(): array
    {
        $calls = [];
        $result = pg_query($GLOBALS['connection'], "select fn, name from lock_calls order by id");
        while ($r = pg_fetch_assoc($result)) {
            self::assertSame(bank_import_columns_lock_name(27, 2), $r['name']);
            $calls[] = $r['fn'];
        }
        return $calls;
    }

    /** @return array<string, array{0: int|null}> */
    public static function lockNotGranted(): array
    {
        return ['timeout (0)' => [0], 'error (NULL)' => [null]];
    }

    /** Codex round 3, finding 1: GET_LOCK() 0 (timeout) or NULL (error) means the lock is not held, so nothing is read or written. */
    #[PHPUnit\Framework\Attributes\DataProvider('lockNotGranted')]
    public function testAMysqlLockThatIsNotGrantedSavesNothing(?int $result): void
    {
        pg_query($GLOBALS['connection'], "insert into settings (var_name, var_grp, var_value, var_description, user_id)
            values ('columns_3', 'bankimport', 'dato,beskrivelse,belob', '', 27)");
        self::fakeMysqlLock($result);
        pg_query($GLOBALS['connection'], "alter table settings rename to settings_hidden");
        pg_query($GLOBALS['connection'], "create view settings as select * from settings_hidden where 1/0 = 1 limit 1000");
        self::assertFalse(bank_import_save_columns(27, ['belob', 'dato', 'beskrivelse'], 2), 'not saved');
        self::assertSame(['get'], self::lockCalls(), 'no release of a lock that is not held');
        pg_query($GLOBALS['connection'], "drop view settings");
        pg_query($GLOBALS['connection'], "alter table settings_hidden rename to settings");
        self::assertSame('dato,beskrivelse,belob', self::value(27, 3), 'the saved choices are unchanged');
    }

    /** Codex round 3, finding 1: a GET_LOCK() query that fails stops the save before any read or write. */
    public function testAMysqlLockQueryThatFailsSavesNothing(): void
    {
        $GLOBALS['db_type'] = 'mysqli';
        pg_query($GLOBALS['connection'], "create table lock_calls (id serial primary key, fn text, name text)");
        try {
            bank_import_save_columns(27, ['dato', 'beskrivelse', 'belob'], 2);
            self::fail('the failed lock query stops the save');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('get_lock', $e->getMessage());
        }
        self::assertSame([], self::rows(), 'nothing is written');
        self::assertSame([], self::lockCalls());
    }

    /** Codex round 3, finding 1: GET_LOCK() 1 is the lock held, so the choices are saved and the lock is released once. */
    public function testAGrantedMysqlLockSavesAndIsReleased(): void
    {
        self::fakeMysqlLock(1);
        self::assertTrue(bank_import_save_columns(27, ['dato', 'beskrivelse', 'belob'], 2));
        self::assertSame('dato,beskrivelse,belob', self::value(27, 3));
        self::assertSame(['get', 'release'], self::lockCalls());
    }

    /** A save that fails while it holds the lock releases it, on MySQL and on PostgreSQL. */
    public function testTheLockIsReleasedWhenTheWriteFails(): void
    {
        $connection = $GLOBALS['connection'];
        pg_query($connection, "alter table settings rename to settings_hidden");
        pg_query($connection, "create view settings as select * from settings_hidden where 1/0 = 1 limit 1000");
        try {
            bank_import_save_columns(27, ['dato', 'beskrivelse', 'belob'], 2);
            self::fail('the broken settings table stops the save');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('division by zero', $e->getMessage());
        }
        $held = pg_fetch_result(pg_query($connection, "select count(*) from pg_locks where locktype = 'advisory' and pid = pg_backend_pid()"), 0, 0);
        self::assertSame('0', $held, 'the PostgreSQL advisory lock is released');

        self::fakeMysqlLock(1);
        try {
            bank_import_save_columns(27, ['dato', 'beskrivelse', 'belob'], 2);
            self::fail('the broken settings table stops the save');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('division by zero', $e->getMessage());
        }
        self::assertSame(['get', 'release'], self::lockCalls(), 'the MySQL lock is released');
    }
}
