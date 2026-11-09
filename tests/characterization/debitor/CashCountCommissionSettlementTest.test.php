<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

// 20261109 CL/SS SST-919 «Afregn kommission» / «Dan betalingsliste» in the cash count.
//                The settlement only debited the commission account and settled every sale since the from-date again on each run.
//                The include runs in a child process (support/run_cash_count_commission.php) against an isolated schema.
//                A child process keeps the legacy global helpers out of the PHPUnit process.
final class CashCountCommissionSettlementTest extends TestCase
{
    private const RUNNER = __DIR__ . '/../support/run_cash_count_commission.php';

    /** @var array<int, string> */
    private const EXPECTED = [
        'The settlement runs without a message',
        'Debit and credit balance after settling',
        'Each commission account is debited with its own total (used 120, new 30)',
        "Customers are credited 150 on this year's collective account",
        'One open post per customer with its share (Anna -100 over both prefixes, Bo -50)',
        'The existing empty list is left alone',
        'The new list holds one payment per customer for the amount just settled',
        'Only the settled orders on this register with a POS payment are stamped',
        'A second run settles nothing again',
        'Dan betalingsliste alone also settles, balanced, with only the new sale',
        'A period already settled in the journal is refused with a message',
        'A journal settlement with a negative net commission also blocks its period',
        'A period between journal settlements is not blocked and settles only its own sale',
        'After the journal period it settles, without a payment list',
        'A missing customer account stops the whole settlement before any posting',
        "A customer group without this year's collective account stops the settlement before any posting",
        'An order with commission lines for an unconfigured account pair is left out with a message, not half settled',
        'With both account pairs the mixed order settles both lines',
        "The default from-date is the register's last settled sale, or the configured date when later",
        'Orders settled by another cash count after they were read are not settled again',
        'The journal settlement leaves out sales already settled at the cash count',
    ];

    /** @var array{exit:int, stdout:string, stderr:string}|null */
    private static ?array $run = null;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $dsn = sprintf(
            "host='%s' dbname='%s' user='%s' password='%s' connect_timeout=3",
            addcslashes(CharacterizationEnv::pgHost(), "'\\"),
            addcslashes(CharacterizationEnv::masterDb(), "'\\"),
            addcslashes(CharacterizationEnv::pgUser(), "'\\"),
            addcslashes(CharacterizationEnv::pgPass(), "'\\")
        );
        // Through the environment, not argv, so the password is not visible in the process list.
        putenv('SALDI_COMMISSION_DSN=' . $dsn);
        try {
            self::$run = CharacterizationEnv::runChild(self::RUNNER);
        } finally {
            putenv('SALDI_COMMISSION_DSN');
        }
    }

    public function testTheRunnerFinishesWithoutFailures(): void
    {
        $run = self::$run;
        self::assertNotNull($run);
        self::assertStringNotContainsString('FAIL:', $run['stdout'], $run['stdout'] . $run['stderr']);
        self::assertSame(0, $run['exit'], $run['stdout'] . $run['stderr']);
    }

    public function testEveryCheckPassed(): void
    {
        $stdout = (string)(self::$run['stdout'] ?? '');
        foreach (self::EXPECTED as $label) {
            self::assertStringContainsString('PASS: ' . $label . "\n", $stdout, "missing check: $label");
        }
        self::assertSame(count(self::EXPECTED), substr_count($stdout, 'PASS: '), 'unexpected number of checks');
    }
}
