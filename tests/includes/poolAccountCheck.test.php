<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/docsIncludes/poolAccountInfo.php';

/**
 * The account check when a pool line is saved: which posted Debet / Kredit values the journal would refuse.
 */
final class poolAccountCheck extends TestCase
{
    /** Finance account 1610 is open, 1699 is closed; kreditor 81079 exists. */
    private function accounts(): callable
    {
        return function (string $type, string $kontonr): string {
            $known = ['F1610' => 'ok', 'F1699' => 'closed', 'K81079' => 'ok'];
            return $known[$type . $kontonr] ?? 'missing';
        };
    }

    public function testExistingAccountsPass(): void
    {
        $this->assertSame(['problem' => '', 'account' => 'F1610'], poolAccountProblem('F1610', '', $this->accounts()));
        $this->assertSame(['problem' => '', 'account' => 'K81079'], poolAccountProblem('k81079', 'F', $this->accounts()));
    }

    public function testUnknownAccountIsMissing(): void
    {
        $this->assertSame(['problem' => 'missing', 'account' => 'F99999'], poolAccountProblem('F99999', '', $this->accounts()));
        // A kreditor number that is only a finance account is not a kreditor
        $this->assertSame(['problem' => 'missing', 'account' => 'K1610'], poolAccountProblem('K1610', '', $this->accounts()));
    }

    public function testTextIsNotAnAccountNumber(): void
    {
        $this->assertSame(['problem' => 'number', 'account' => 'xyzq'], poolAccountProblem('Fxyzq', '', $this->accounts()));
        $this->assertSame(['problem' => 'number', 'account' => 'tele'], poolAccountProblem('tele', '', $this->accounts()));
    }

    public function testClosedAccount(): void
    {
        $this->assertSame(['problem' => 'closed', 'account' => 'F1699'], poolAccountProblem('F1699', '', $this->accounts()));
    }

    public function testDigitsTakeTheLinesOwnType(): void
    {
        $this->assertSame(['problem' => '', 'account' => 'K81079'], poolAccountProblem('81079', 'K', $this->accounts()));
        $this->assertSame(['problem' => 'missing', 'account' => 'F81079'], poolAccountProblem('81079', '', $this->accounts()));
    }

    public function testEmptyAndZeroAreLeftToTheMandatoryCheck(): void
    {
        $this->assertSame('', poolAccountProblem('', 'F', $this->accounts())['problem']);
        $this->assertSame('', poolAccountProblem('F0', '', $this->accounts())['problem']);
        $this->assertSame('', poolAccountProblem(null, '', $this->accounts())['problem']);
    }
}
