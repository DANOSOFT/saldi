<?php
// 20261113 CDX/NTR SD-599 Characterize the terminal-file approval and fallback payment screens.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../support/CharacterizationEnv.php';

final class CardPaymentRegistrationTest extends TestCase
{
    private function runPayment(string $scenario, string $entry = 'legacy'): array
    {
        $result = CharacterizationEnv::runChild(__DIR__ . '/../../support/run_card_registration.php', [$scenario, $entry]);
        self::assertSame(0, $result['exit'], $result['stderr']);
        $out = json_decode($result['stdout'], true);
        self::assertIsArray($out, $result['stdout'] . $result['stderr']);
        return $out;
    }

    public function test_matching_terminal_file_redirects_without_database_access(): void
    {
        $out = $this->runPayment('approved');
        self::assertSame([], $out['queries']);
        self::assertStringContainsString('pos_ordre.php?id=41&modtaget=1250.5&korttype=Visa&betaling=Visa&godkendt=OK', $out['html']);
        self::assertStringContainsString('godkendt=OK', $out['log']);
        self::assertSame([], $out['warnings']);
    }

    public function test_mismatched_terminal_amount_keeps_the_two_legacy_fallback_screens(): void
    {
        $out = $this->runPayment('mismatch');
        self::assertSame(2, substr_count($out['html'], "<table align='center'>"));
        self::assertSame(2, substr_count($out['html'], 'indbetaling=1250.5&godkendt=OK'));
        self::assertSame(2, substr_count($out['log'], 'Fejlet betaling: ID 41'));
        self::assertCount(8, $out['queries']);
        self::assertSame(['Undefined variable $betal_type', 'Undefined variable $betal_type'], $out['warnings']);
    }

    public function test_other_payment_type_renders_one_fallback_without_an_account_deposit(): void
    {
        $out = $this->runPayment('fallback');
        self::assertSame(1, substr_count($out['html'], "<table align='center'>"));
        self::assertStringContainsString('indbetaling=0&godkendt=OK', $out['html']);
        self::assertStringContainsString('godkendt=afvist', $out['html']);
        self::assertCount(3, $out['queries']);
        self::assertSame(['Undefined variable $betal_type'], $out['warnings']);
    }

    public function test_dedicated_module_can_register_without_loading_the_order_engine(): void
    {
        $out = $this->runPayment('approved', 'module');
        self::assertFalse($out['core_loaded']);
        self::assertStringContainsString('godkendt=OK', $out['html']);
        self::assertSame([], $out['queries']);
        self::assertSame([], $out['warnings']);
    }
}
