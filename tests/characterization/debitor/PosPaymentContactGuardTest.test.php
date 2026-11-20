<?php
// SD-844 K61: both POS payment entrances, afslut() (whole payment) and delbetal() (part payment),
// ask the contact check before any payment step, and stop there when it refuses. A card that
// comes back approved (godkendt OK) and a deposit (indbetaling) are never stopped.

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
}
