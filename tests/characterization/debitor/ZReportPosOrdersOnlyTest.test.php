<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

// 20261029 CL/MMR SST-896 The POS X/Z-report counts only register sales.
//                 An ordinary invoice gets the register number in felt_5 when its department has a register, and
//                 the report used to add it to turnover, VAT, payments and item groups (Phone 2 Fix: 249 became
//                 12.249). The legacy report functions run in a child process (support/run_zreport_pos_orders.php)
//                 against an isolated schema, because they define global helpers the PHPUnit process must not share.
final class ZReportPosOrdersOnlyTest extends TestCase
{
    private const RUNNER = __DIR__ . '/../support/run_zreport_pos_orders.php';

    /** @var array<int, string> */
    private const EXPECTED = [
        'Register 2 report covers only its POS sale, not the invoice',
        'Turnover with VAT is the POS sale only (249, not 12.249)',
        'Turnover without VAT is the POS sale only',
        'No 0 % VAT line from the invoice',
        'Payment total is the POS sale only',
        'Item groups on register 2 have no line for the invoice-only group',
        'Register 1 item groups are unaffected',
        'Register 1 is unaffected',
        "Sets value '' counts as off: invoice left out, turnover 249",
        "Sets value '0' counts as off: invoice left out, turnover 249",
        "Sets value ' 0 ' counts as off: invoice left out, turnover 249",
        "Sets value '  ' counts as off: invoice left out, turnover 249",
        'Sets value NULL counts as off: invoice left out, turnover 249',
        'Missing POS settings row counts as sets off',
        'Sets enabled in another fiscal year do not count',
        'With sets enabled the debtor order is included',
        'With sets enabled turnover includes it',
        'With sets enabled the invoice item group is on the report',
        'A debtor credit note is left out without sets',
        'The register number is int-cast',
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
        putenv('SALDI_ZREPORT_DSN=' . $dsn);
        try {
            self::$run = CharacterizationEnv::runChild(self::RUNNER);
        } finally {
            putenv('SALDI_ZREPORT_DSN');
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

    public function testTheKnownLegacyWarningIsReported(): void
    {
        self::assertMatchesRegularExpression(
            '/^NOTE: vatPayments\(\) raised the known legacy warning .* at rapportData\.php:\d+ 1 time\(s\)$/m',
            (string)(self::$run['stdout'] ?? '')
        );
    }
}
