<?php
// 20261112 CL/SS MB-49 The POS checkout (afslut()) must not finish a sale its posting refused, and must not take a card payment of 0 on a 0-kr sale.
//                The real afslut() runs in a child process (support/run_afslut_posting_error.php) with the database, settlement and receipt boundaries stood in for.
//                It needs no database, so it also runs in CI.
// 20261117 CL/SS MB-49: an exchange whose lines cancel out is not refused.
// 20261124 CL/SS MB-49: a card of 0 in the second payment slot is refused too.
// 20261125 CL/SS MB-49: the refusal alerts are not blank on a LATIN9 company.
// 20261126 CDX/NTR Automatic receipts that return must not print twice or resume a completed payment (#1183).

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

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
     * @return array{returned: mixed, events: list<string>, alerts: list<string>, html: string}
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
     * A card payment of 0 on a 0-kr sale, in either payment slot, is refused before settlement, so nothing is written and no invoice number is taken.
     */
    public function testACardPaymentOfZeroOnAZeroSaleIsRefusedBeforeSettlement(): void
    {
        $result = self::checkout(['payment' => 'Dankort', 'received' => 0, 'sum' => 0]);
        self::assertStringContainsString('Indtast beløbet, før du betaler med Dankort', (string)$result['returned']);
        self::assertSame([], $result['events'], 'nothing may run before the refusal');

        $result = self::checkout(['payment' => 'Kontant', 'received' => 0, 'payment2' => 'Dankort', 'received2' => 0, 'sum' => 0]);
        self::assertStringContainsString('Indtast beløbet, før du betaler med Dankort', (string)$result['returned'], 'a card in the second slot');
        self::assertSame([], $result['events']);
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
     * An exchange whose lines cancel out (+100 and -100 on different accounts) totals 0, but its lines still post, so a card payment of 0 is not refused.
     */
    public function testAnExchangeWhoseLinesCancelOutIsNotRefused(): void
    {
        $result = self::checkout(['payment' => 'Dankort', 'received' => 0, 'sum' => 0, 'lines' => 200]);
        self::assertNull($result['returned'], 'an exchange must not be refused');
        self::assertSame(['voucher', 'settled', 'commit'], array_slice($result['events'], 0, 3));
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

    public function testAReturningReceiptDoesNotReopenTheCompletedPaymentScreen(): void
    {
        $result = self::checkout(['payment' => 'Kontant', 'received' => 125, 'sum' => 100,
            'moms' => 25, 'account' => 9, 'autoPrint' => true, 'receiptReturns' => true]);

        self::assertSame([], $result['alerts'], 'a successful cash sale must not report that it was already completed');
        self::assertSame(['voucher', 'settled', 'commit', 'receipt'], $result['events'],
            'settle once and print one receipt, even when the receipt renderer returns');
        self::assertStringContainsString('content="0;URL=pos_ordre.php?id=42"', $result['html'],
            'a returning receipt renderer must still leave checkout through a GET');
        self::assertStringNotContainsString('Function betaling', $result['html'], 'checkout must end before the payment screen resumes');
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
        self::assertMatchesRegularExpression('/if \(\$svar\) \{\s*print "<script>alert\(" \. jsString\(array\(\$svar\)\) \. "\);<\/script>\\\\n";/',
            $next, 'the result must be shown as an alert through jsString(), so quotes, newlines, markup and LATIN9 text are safe');
    }

    /**
     * On a LATIN9 company findtekst() returns ISO-8859-1 text, which json_encode() rejects with false,
     * so the refusal alert came up empty. Both alerts that show a posting refusal must go through jsString().
     */
    public function testARefusalAlertIsNotBlankOnALatin9Company(): void
    {
        $src = (string)file_get_contents(__DIR__ . '/../../../debitor/pos_ordre.php');
        self::assertSame(0, substr_count($src, 'alert(" . json_encode($svar'), 'no refusal alert may embed $svar with json_encode()');
        self::assertSame(2, substr_count($src, 'print "<script>alert(" . jsString(array($svar)) . ");</script>\n";'),
            'the terminal return and the day-end alert both go through jsString()');

        require_once __DIR__ . '/../../../includes/stdFunc/jsString.php';
        $previous = $GLOBALS['db_encode'] ?? null;
        $GLOBALS['db_encode'] = 'LATIN9';
        try {
            $latin9 = mb_convert_encoding('Salget kunne ikke bogføres, da der ikke blev dannet nogen posteringer.', 'ISO-8859-1', 'UTF-8');
            self::assertFalse(json_encode($latin9), 'json_encode() gives up on ISO-8859-1 text');
            $literal = jsString(array($latin9));
            self::assertStringContainsString('bogf\\u00f8res', $literal, 'the alert keeps the message, with ø escaped');
        } finally {
            $GLOBALS['db_encode'] = $previous;
        }
    }
}
