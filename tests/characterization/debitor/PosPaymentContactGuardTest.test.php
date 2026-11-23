<?php
// SD-844 K61: both POS payment entrances, afslut() (whole payment) and delbetal() (part payment),
// ask the contact check before any payment step, and stop there when it refuses. A card that
// comes back approved (godkendt OK) and a deposit (indbetaling) are never stopped. voucherPay()
// asks too for a gift card, the path in pos_ordre.php that settles without either entrance.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

final class PosPaymentContactGuardTest extends TestCase
{
    /** @return array{result: mixed, guardCalls: list<int>, paymentReached: ?string} */
    private function runGuard(string $function, string $godkendt, string $indbetaling, string $verdict): array
    {
        $child = CharacterizationEnv::runChild(__DIR__ . '/support/run_pos_payment_guard.php',
            [$function, $godkendt, $indbetaling, $verdict]);
        self::assertSame('', $child['stderr'], 'the runner wrote to stderr: ' . $child['stderr']);
        $report = json_decode($child['stdout'], true);
        self::assertIsArray($report, 'the runner did not report JSON: ' . $child['stdout']);
        return $report;
    }

    /** @return array<string, array{string}> */
    public static function entrances(): array
    {
        return ['whole payment' => ['afslut'], 'part payment' => ['delbetal']];
    }

    #[DataProvider('entrances')]
    public function testARefusedSaleStopsBeforeAnyPaymentStep(string $function): void
    {
        $report = $this->runGuard($function, '', '', 'refuse');

        self::assertSame([42], $report['guardCalls']);
        self::assertSame('contact missing', $report['result']);
        self::assertNull($report['paymentReached']);
    }

    #[DataProvider('entrances')]
    public function testAnAcceptedSaleGoesOnToPayment(string $function): void
    {
        $report = $this->runGuard($function, '', '', 'ok');

        self::assertSame([42], $report['guardCalls']);
        self::assertNotNull($report['paymentReached']);
    }

    #[DataProvider('entrances')]
    public function testACardThatCameBackApprovedIsNotStopped(string $function): void
    {
        $report = $this->runGuard($function, 'OK', '', 'refuse');

        self::assertSame([], $report['guardCalls']);
        self::assertNotNull($report['paymentReached']);
    }

    #[DataProvider('entrances')]
    public function testADepositIsNotStopped(string $function): void
    {
        $report = $this->runGuard($function, '', '1', 'refuse');

        self::assertSame([], $report['guardCalls']);
        self::assertNotNull($report['paymentReached']);
    }

    /** @return array{result: mixed, guardCalls: list<int>, formShown: bool} */
    private function runVoucher(string $betaling, string $indbetaling, string $verdict): array
    {
        $child = CharacterizationEnv::runChild(__DIR__ . '/support/run_pos_voucher_guard.php', [$betaling, $indbetaling, $verdict]);
        self::assertSame('', $child['stderr'], 'the runner wrote to stderr: ' . $child['stderr']);
        $report = json_decode($child['stdout'], true);
        self::assertIsArray($report, 'the runner did not report JSON: ' . $child['stdout']);
        return $report;
    }

    public function testARefusedSaleGetsNoGiftCardForm(): void
    {
        $report = $this->runVoucher('Gavekort', '', 'refuse');

        self::assertSame([42], $report['guardCalls']);
        self::assertFalse($report['result']);
        self::assertFalse($report['formShown']);
    }

    public function testAnAcceptedSaleGetsTheGiftCardForm(): void
    {
        $report = $this->runVoucher('Gavekort', '', 'ok');

        self::assertSame([42], $report['guardCalls']);
        self::assertTrue($report['formShown']);
    }

    public function testAGiftCardDepositIsNotStopped(): void
    {
        $report = $this->runVoucher('Gavekort', '1', 'refuse');

        self::assertSame([], $report['guardCalls']);
        self::assertTrue($report['formShown']);
    }

    public function testAPaymentThatIsNoGiftCardIsNotCheckedHere(): void
    {
        $report = $this->runVoucher('Kontant', '', 'refuse');

        self::assertSame([], $report['guardCalls']);
        self::assertFalse($report['formShown']);
    }

    public function testForfraClearsThePhoneTheContactRuleReads(): void
    {
        // Forfra reuses the sale's row. A phone left from the previous customer would let a pickup
        // or delivery picked afterwards pass the contact rule without the new customer's contact.
        $page = (string) file_get_contents(dirname(__DIR__, 3) . '/debitor/pos_ordre.php');

        self::assertStringContainsString("institution='',email='',kontakt='',phone='',art='PO',", $page,
            'Forfra clears email and phone together');
    }
}
