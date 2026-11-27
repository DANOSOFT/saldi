<?php
// tests/characterization/debitor/CashCountTransactionTest.test.php
//
// Runs the cash count script tests/test_cash_count_transaction.php in a child process, so CI runs it.
// The script stubs the database layer by hand and loads findBoxSale.php without includes/std_func.php.
// When findBoxSale() began reading a foreign currency's till account from settings (decision 22), the EUR count died
// on an undefined get_settings_value() and 13 of its 14 checks never ran, unseen, because nothing ran the script.
// It builds and drops a schema of its own in the master database, as ZReportPosOrdersOnlyTest does.
//
// History:
// 20261105 CL/LH Created.
// 20261117 CL/ASR POS-F2: three checks for euro cash on a kroner POS order.
// 20261117 CL/ASR Expects the check that with box9 an unposted account sale is counted.
// 20261118 CL/ASR Expects the checks for euro cash on a box9 account sale and on a sale from before the fiscal year, and for a sale without a fiscal year.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

final class CashCountTransactionTest extends TestCase
{
    private const SCRIPT = __DIR__ . '/../../test_cash_count_transaction.php';

    /** @var array<int, string> */
    private const EXPECTED = [
        'Select cash/card orders once; exclude bank-only, other registers/currencies and prior dates',
        'Foreign currency selects its cash account without base-currency card accounts',
        'A till account stored in settings for the till wins over the box4 list',
        'Assign only included orders and preserve earlier assignments',
        'Reject the newly committed sale and roll back before posting',
        'Accept recalculated totals in the posting transaction',
        'Eligible orders and balances retain the validated snapshot despite a concurrent commit',
        'The concurrent sale stays outside the posting snapshot',
        'The concurrent sale remains unassigned for the next report',
        'The next calculation sees the later sale',
        'With box9 an unposted account sale is counted: its cash in the till, its Konto part on account',
        'Deferred counts include unposted cash and already-posted card sales without payment rows',
        'Calculating a count does not mutate unposted orders',
        'Deferred posting also retains the validated snapshot after a concurrent sale',
        'An ordinary invoice with a transaction on the register is left out of the count',
        'With sets enabled a debtor order without payment type is counted in a named bucket',
        'Without the count assistant euro cash stays a card in the kroner count',
        'Deferred posting counts euro cash on a kroner order as euro takings, not as a card',
        'Immediate posting moves euro cash taken since the last count from the kroner to the euro takings',
        'With box9 euro cash on an account sale the close posts is euro takings, not a card, and its Konto part is on account',
        'A sale from before the fiscal year paid in euro is counted as euro takings, not as a card',
        'A sale dated in a deleted fiscal year or in no fiscal year is not counted, with or without box9',
    ];

    /** @var array{exit:int, stdout:string, stderr:string}|null */
    private static ?array $run = null;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        // The script reads SALDI_CHAR_PGHOST/PGUSER/PGPASS/PGDATABASE; host, user and database get this suite's values.
        // Through the environment, not argv, so the password is not visible in the process list.
        $env = [
            'SALDI_CHAR_PGHOST' => CharacterizationEnv::pgHost(),
            'SALDI_CHAR_PGUSER' => CharacterizationEnv::pgUser(),
            'SALDI_CHAR_PGDATABASE' => CharacterizationEnv::masterDb(),
        ];
        $saved = [];
        foreach ($env as $name => $value) {
            $saved[$name] = getenv($name);
            putenv("$name=$value");
        }
        try {
            self::$run = CharacterizationEnv::runChild(self::SCRIPT);
        } finally {
            foreach ($saved as $name => $value) {
                putenv($value === false ? $name : "$name=$value");
            }
        }
    }

    public function testTheScriptFinishesWithoutAnError(): void
    {
        $run = self::$run;
        self::assertNotNull($run);
        self::assertSame(0, $run['exit'], $run['stdout'] . $run['stderr']);
    }

    public function testEveryCheckPassed(): void
    {
        $stdout = (string)(self::$run['stdout'] ?? '');
        $stderr = (string)(self::$run['stderr'] ?? '');
        foreach (self::EXPECTED as $label) {
            self::assertStringContainsString('PASS: ' . $label . "\n", $stdout, "missing check: $label\n$stdout$stderr");
        }
        self::assertSame(count(self::EXPECTED), substr_count($stdout, 'PASS: '), 'unexpected number of checks');
    }
}
