<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// 20261106 CL/MMR SST-961 Created. Overfør of an akkordafregning (sager/loenIncludes/retLoen.php) must not close a settlement
//                 while one of its listed Dyrtid slips is, or becomes, undated. The transfer locks the listed slips before it checks
//                 their dates, and a slip transferred after its form was opened is not saved over (two-session race, Codex round 8).
//                 The helpers are lifted from the source and run with a stubbed db_select(); the order of the steps in ret_loen()
//                 is checked in the source, because the page cannot be run without a database.
#[RunTestsInSeparateProcesses]
final class AkkordTransferLockTest extends TestCase
{
    private const RET_LOEN = __DIR__ . '/../../../sager/loenIncludes/retLoen.php';

    private static function source(): string
    {
        return (string)file_get_contents(self::RET_LOEN);
    }

    /** Lifts one top-level function from retLoen.php. */
    private static function lift(string $name): void
    {
        $src = self::source();
        $a = strpos($src, "function $name(");
        self::assertNotFalse($a, "$name() is gone from sager/loenIncludes/retLoen.php");
        $b = strpos($src, "\n}\n", $a);
        eval(substr($src, $a, $b - $a + 2));
    }

    private static function boot(): void
    {
        $GLOBALS['__queries'] = [];
        eval('
            function db_select($q, $where = "") { $GLOBALS["__queries"][] = $q; return count($GLOBALS["__queries"]); }
            function db_fetch_array($q) { return false; }
        ');
        foreach (['akkDyrtidMedDatoSql', 'akkDyrtidListeSql', 'akkDyrtidLaas', 'akkDyrtidUdenDato'] as $name) {
            self::lift($name);
        }
    }

    public function testTheLockSelectsEveryListedSlipForUpdate(): void
    {
        self::boot();
        akkDyrtidLaas(151071, 12708, 14897);
        $q = $GLOBALS['__queries'][0] ?? '';
        self::assertStringContainsString('for update', $q);
        self::assertStringContainsString("sag_id='12708' and opg_id='14897' and art='akktimer' and id != '151071'", $q);
        self::assertStringContainsString("master_id='151071' or master_id='0' or master_id is NULL", $q);
        // dated slips are locked too, so their date cannot be removed between the check and the transfer
        self::assertStringNotContainsString('loendate', $q);
    }

    public function testTheListConditionCastsItsValues(): void
    {
        self::boot();
        akkDyrtidLaas("1' or '1'='1", '2x', null);
        $q = $GLOBALS['__queries'][0] ?? '';
        self::assertStringContainsString("sag_id='2' and opg_id='0' and art='akktimer' and id != '1'", $q);
        self::assertStringNotContainsString("or '1'='1", $q);
    }

    public function testTheUndatedListUsesTheSameListAndTheSharedDateRule(): void
    {
        self::boot();
        akkDyrtidUdenDato(151071, 12708, 14897);
        $q = $GLOBALS['__queries'][0] ?? '';
        self::assertStringContainsString(akkDyrtidListeSql(151071, 12708, 14897), $q);
        self::assertStringContainsString('not ' . akkDyrtidMedDatoSql(), $q);
    }

    public function testTheTransferLocksBeforeItChecksAndBeforeItWrites(): void
    {
        $src = self::source();
        $lock = strpos($src, 'akkDyrtidLaas($id, $sag_id, $opg_id);');
        $check = strpos($src, '&& akkDyrtidUdenDato($id, $sag_id, $opg_id)) {');
        $sweep = strpos($src, "update loen set afsluttet='\$afsluttet',afsluttet_af='\$afsluttet_af',master_id='\$id'");
        self::assertNotFalse($lock, 'the transfer no longer locks the listed Dyrtid slips');
        self::assertNotFalse($check, 'the transfer no longer checks the listed Dyrtid slips for a date');
        self::assertNotFalse($sweep, 'the transfer UPDATE of the Dyrtid slips is gone');
        self::assertLessThan($check, $lock, 'the lock must be taken before the date check');
        self::assertLessThan($sweep, $check, 'the date check must come before the transfer writes');
    }

    public function testASlipTransferredAfterItsFormWasOpenedIsNotSavedOver(): void
    {
        $src = self::source();
        $read = strpos($src, "select afvist, afvist_af, afsluttet, afsluttet_af from loen where id='\" . (int)\$id . \"' for update\"");
        $refuse = strpos($src, "if (trim((string)\$r['afsluttet']) && !trim((string)ifset(\$_POST, 'afsluttet', ''))) {");
        $save = strpos($src, "\$qtxt = \"update loen set art='\$loen_art'");
        self::assertNotFalse($read, 'the save no longer reads and locks the stored transfer state');
        self::assertNotFalse($refuse, 'the save no longer refuses a slip transferred after its form was opened');
        self::assertNotFalse($save, 'the slip UPDATE is gone');
        self::assertLessThan($refuse, $read);
        self::assertLessThan($save, $refuse, 'the refusal must come before the slip is written');
        $exit = strpos($src, 'exit;', $refuse);
        self::assertNotFalse($exit);
        self::assertLessThan($save, $exit, 'the refusal must stop before the slip is written');
    }
}
