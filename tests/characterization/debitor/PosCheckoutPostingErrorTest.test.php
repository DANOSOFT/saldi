<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// 20261112 CL/SS MB-49 The POS checkout (afslut()) must not finish a sale its posting refused, and must not take a card payment of 0 on a 0-kr sale.
//                The real afslut() runs in a child process (support/run_afslut_posting_error.php) with the database, settlement and receipt boundaries stood in for.
//                It needs no database, so it also runs in CI.
final class PosCheckoutPostingErrorTest extends TestCase
{
    private static string $fixture;

    public static function setUpBeforeClass(): void
    {
        self::$fixture = sys_get_temp_dir() . '/saldi-mb49-' . bin2hex(random_bytes(8));
        mkdir(self::$fixture . '/debitor/pos_ordre_includes/voucherFunc', 0700, true);
        mkdir(self::$fixture . '/temp', 0700);
        file_put_contents(self::$fixture . '/debitor/pos_ordre_includes/voucherFunc/voucherPay.php',
            '<?php function voucherPay(...$args) { $GLOBALS["events"][] = "voucher"; }');
        file_put_contents(self::$fixture . '/debitor/settlePOS.php',
            '<?php $GLOBALS["events"][] = "settled"; $svar = $GLOBALS["case"]["settle"] ?? "OK"; $sum = $GLOBALS["case"]["sum"] ?? 0; $moms = 0;');
    }

    public static function tearDownAfterClass(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::$fixture, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir(self::$fixture);
    }

    /**
     * Runs afslut() for one case.
     *
     * @param array<string, mixed> $case
     * @return array{returned: mixed, events: list<string>, html: string}
     */
    private static function checkout(array $case): array
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/support/run_afslut_posting_error.php', json_encode($case), self::$fixture],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = (string)stream_get_contents($pipes[1]);
        $errors = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $lines = array_values(array_filter(explode("\n", trim($output))));
        $result = $lines ? json_decode((string)end($lines), true) : null;
        self::assertIsArray($result, "runner output: $output $errors");
        self::assertSame('', $errors, 'PHP warnings: ' . $errors);
        return $result;
    }

    /**
     * A card payment of 0 on a 0-kr sale is refused before settlement, so nothing is written and no invoice number is taken.
     */
    public function testACardPaymentOfZeroOnAZeroSaleIsRefusedBeforeSettlement(): void
    {
        $result = self::checkout(['payment' => 'Dankort', 'received' => 0, 'sum' => 0]);
        self::assertStringContainsString('Indtast beløbet, før du betaler med Dankort', (string)$result['returned']);
        self::assertSame([], $result['events'], 'nothing may run before the refusal');
    }

    /**
     * A free 0-kr sale paid with cash 0, and a 0-kr sale paid by card with an amount (cash withdrawal, also with the cash handed back as a second payment), still settle.
     */
    public function testAFreeSaleAndACashWithdrawalStillSettle(): void
    {
        foreach ([['payment' => 'Kontant', 'received' => 0, 'sum' => 0], ['payment' => 'Dankort', 'received' => 400, 'sum' => 0],
            ['payment' => 'Dankort', 'received' => 400, 'payment2' => 'Kontant', 'received2' => -400, 'sum' => 0]] as $case) {
            $label = $case['payment'] . (isset($case['payment2']) ? ' + ' . $case['payment2'] : '');
            $result = self::checkout($case);
            self::assertNull($result['returned'], "$label must not be refused");
            self::assertSame(['voucher', 'settled', 'commit'], array_slice($result['events'], 0, 3), "$label must settle and commit");
        }
    }

    /**
     * A refused posting is rolled back and returned, and the receipt and cash drawer are not used.
     */
    public function testAPostingErrorIsRolledBackAndReturnedInsteadOfFinishingTheSale(): void
    {
        $result = self::checkout(['payment' => 'Visa', 'received' => 400, 'sum' => 0, 'settle' => 'Betalingen med Visa kan ikke bogføres']);
        self::assertSame('Betalingen med Visa kan ikke bogføres', $result['returned']);
        self::assertSame(['voucher', 'settled', 'rollback'], $result['events']);
        self::assertStringNotContainsString('http-equiv="refresh"', $result['html'], 'no redirect or cash drawer after a refused posting');
    }

    /**
     * The card terminal's return calls afslut() too, and must show its error as the other callers do.
     */
    public function testTheCardTerminalReturnShowsAfslutsError(): void
    {
        $src = (string)file_get_contents(__DIR__ . '/../../../debitor/pos_ordre.php');
        $call = '$svar = afslut($id, $betaling, $betaling2, $modtaget, $modtaget2, $indbetaling, $godkendt, $kortnavn, $receipt_id, __LINE__);';
        $at = strpos($src, $call);
        self::assertNotFalse($at, "the terminal return's afslut() call must keep its result");
        $next = substr($src, $at + strlen($call), 200);
        self::assertMatchesRegularExpression('/if \(\$svar\) \{\s*print "<script>alert\(" \. json_encode\(\$svar, JSON_HEX_TAG \| JSON_HEX_AMP \| JSON_HEX_APOS \| JSON_HEX_QUOT\)/',
            $next, 'the result must be shown as an alert, JSON-encoded so quotes, newlines and markup in the message are safe');
    }
}
