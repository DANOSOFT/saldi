<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// 20261106 CL/MMR SST-962 Created. The column choices of finans/bankimport.php (includes/stdFunc/bankImportColumns.php).
//                 Only the first 8 columns used to be remembered, so a card acquirer's file with the description in column 10 asked for it on every import.
//                 The choices are now kept for every column and per number of columns, so a bank's file and the acquirer's file keep their own.
//                 The settings table is stubbed in memory, and the files are synthetic.
#[RunTestsInSeparateProcesses]
final class BankImportColumnsTest extends TestCase
{
    private const FILE = __DIR__ . '/../../../includes/stdFunc/bankImportColumns.php';

    /** A card-acquirer export: 13 columns, comma separated, description in column 10 (index 9). */
    private const ACQUIRER = "-,merchant_id,merchant_name,id,arn,type,processed_at,amount,currency,text_on_statement,reference,threed_secure,settlement\n"
        . "1,1000001,Testbutik ApS,a-1,700,capture,2026-10-26,100.00,DKK,testbutik.dk,000520000,full,S1\n"
        . "1,1000001,\"Testbutik, Nord\",a-2,701,refund,2026-10-26,37.50,DKK,testbutik.dk,000520001,full,S1\n";

    /** A bank's own export: 7 columns, semicolon separated, no header. */
    private const BANK = "26-10-2026;Overførsel;Kunde A betaling;26-10-2026;1.250,00;10.250,00;DKK\n"
        . "27-10-2026;Overførsel;Kunde B betaling;27-10-2026;-375,50;9.874,50;DKK\n";

    private static function boot(): void
    {
        $GLOBALS['__settings'] = [];
        eval('
            function get_settings_value($name, $grp, $default, $user = null, $kasse = null, $global = false) {
                return $GLOBALS["__settings"]["$grp/$name/$user"] ?? $default;
            }
            function update_settings_value($name, $grp, $value, $description, $user = null, $posid = null, $global = false) {
                $GLOBALS["__settings"]["$grp/$name/$user"] = $value;
            }
        ');
        require_once __DIR__ . '/../../../includes/std_func.php'; // the real ifset(); its settings helpers are guarded by function_exists
        require self::FILE;
    }

    private static function file(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bankimport');
        file_put_contents($path, $content);
        return $path;
    }

    public function testLastColumnOfTheAcquirerFile(): void
    {
        self::boot();
        self::assertSame(12, bank_import_file_last_column(self::file(self::ACQUIRER), 'UTF-8'), 'a quoted comma does not count as a column');
    }

    public function testLastColumnOfTheBankFile(): void
    {
        self::boot();
        self::assertSame(6, bank_import_file_last_column(self::file(self::BANK), 'UTF-8'));
    }

    public function testUnreadableOrSeparatorlessFileGivesMinusOne(): void
    {
        self::boot();
        self::assertSame(-1, bank_import_file_last_column('/nonexistent/bankfile.csv', 'UTF-8'));
        self::assertSame(-1, bank_import_file_last_column(self::file("just text\nmore text\n"), 'UTF-8'));
    }

    public function testChoiceInColumnTenSurvivesTheNextUpload(): void
    {
        self::boot();
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
        self::boot();
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
        self::boot();
        $legacy = [0 => 'dato', 1 => '', 2 => 'beskrivelse', 3 => '', 4 => 'belob', 5 => '', 6 => '', 7 => 'currency'];
        $columns = bank_import_columns_for_upload(27, self::file(self::ACQUIRER), 'UTF-8', $legacy);
        self::assertCount(13, $columns);
        self::assertSame('beskrivelse', $columns[2]);
        self::assertSame('', $columns[12]);
    }

    public function testUnknownNamesAreNeverStored(): void
    {
        self::boot();
        bank_import_save_columns(27, [0 => "dato', box1='x", 1 => 'beskrivelse', 2 => 'belob'], 2);
        self::assertSame(',beskrivelse,belob', $GLOBALS['__settings']['bankimport/columns_3/27']);
    }

    public function testNothingChosenStoresNothing(): void
    {
        self::boot();
        self::assertFalse(bank_import_save_columns(27, [0 => '', 1 => ''], 1));
        self::assertFalse(bank_import_save_columns(0, [0 => 'dato'], 1));
        self::assertSame([], $GLOBALS['__settings']);
    }

    public function testBankimportCallsTheHelpers(): void
    {
        $src = (string) file_get_contents(__DIR__ . '/../../../finans/bankimport.php');
        $save = strpos($src, 'bank_import_save_columns(');
        $firstPrint = strpos($src, "if (\$menu=='T') {");
        self::assertNotFalse($save);
        self::assertNotFalse($firstPrint);
        self::assertLessThan($firstPrint, $save, 'the choices are saved before the page prints');
        self::assertStringContainsString('bank_import_columns_for_upload($bruger_id, $filnavn, $charset, $feltnavn)', $src);
    }
}
