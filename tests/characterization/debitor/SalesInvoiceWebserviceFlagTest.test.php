<?php
// tests/characterization/debitor/SalesInvoiceWebserviceFlagTest.test.php
//
// Regression test for D4: bogfor_nu() unconditionally set $webservice = 'webservice'
// right after checking $kilde == 'webservice', disabling current-year kontoplan
// scoping and the debtor-group control-account check for all UI sales invoices.
//
// History:
// 20261127 CL/HUG Created for D4 webservice-flag regression test.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class SalesInvoiceWebserviceFlagTest extends CharacterizationTestCase
{
    private static int $invoiceSeq = 94000;

    /**
     * Create a status-3 sales order whose debtor group points at $controlAccount.
     *
     * @param array<string, mixed> $orderOverrides
     * @return array{order_id:int, regnaar:int}
     */
    private function createOrderForControlAccount(int $controlAccount, int $groupCode, array $orderOverrides = []): array
    {
        $regnaar = self::$fx->regnaar();
        [$salesAcct, $vatAcct] = self::$fx->vatSalesAccount();

        $dg = self::one(
            "SELECT box1 FROM grupper WHERE art = 'DG' AND fiscal_year = $1 ORDER BY kodenr LIMIT 1",
            [$regnaar]
        );
        $this->assertNotNull($dg, 'template tenant must have a debtor group');

        self::rows(
            "DELETE FROM grupper WHERE art = 'DG' AND kodenr = $1 AND fiscal_year = $2",
            [$groupCode, $regnaar]
        );
        self::rows(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, fiscal_year)
             VALUES ($1, '', $2, 'DG', $3, $4, $5)",
            ['D4 test group ' . $groupCode, $groupCode, $dg['box1'], (string)$controlAccount, $regnaar]
        );

        $kontoNr = 84000 + $groupCode;
        $debtor = self::one(
            "INSERT INTO adresser (kontonr, firmanavn, addr1, postnr, bynavn, email, art, gruppe, betalingsbet, betalingsdage)
             VALUES ($1, 'D4 Test Kunde', 'Testvej 1', '8000', 'Aarhus', 'd4@example.invalid', 'D', $2, $3, 8)
             RETURNING id",
            [$kontoNr, $groupCode, $orderOverrides['betalingsbet'] ?? 'Netto']
        );
        $debtorId = (int)$debtor['id'];

        $fakturanr = (string)(++self::$invoiceSeq);
        $betalingsbet = $orderOverrides['betalingsbet'] ?? 'Netto';
        $status = $orderOverrides['status'] ?? 3;
        $felt1 = $orderOverrides['felt_1'] ?? null;
        $felt2 = $orderOverrides['felt_2'] ?? null;

        $order = self::one(
            "INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsbet, betalingsdage, kontonr, art, valuta, valutakurs,
                                 ordredate, levdate, fakturadate, fakturanr, ordrenr, sum, moms, momssats, status, konto_id, felt_1, felt_2)
             VALUES ('D4 Test Kunde', 'Testvej 1', '8000', 'Aarhus', 'd4@example.invalid', $1, 8, $2, 'DO', 'DKK', 100,
                     CURRENT_DATE, CURRENT_DATE, CURRENT_DATE, $3,
                     (SELECT COALESCE(max(ordrenr), 0) + 1 FROM ordrer), 100.00, 25.00, 25, $4, $5, $6, $7)
             RETURNING id",
            [$betalingsbet, $kontoNr, $fakturanr, $status, $debtorId, $felt1, $felt2]
        );
        $orderId = (int)$order['id'];

        self::rows(
            "INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, beskrivelse, enhed, posnr, antal, pris, rabat, momssats, procent, bogf_konto, vat_account, momsfri)
             VALUES ($1, 0, 'D4ITEM', 'D4 test line', 'stk', 1, 1, 100.00, 0, 25, 100, $2, $3, '')",
            [$orderId, $salesAcct, $vatAcct]
        );

        return ['order_id' => $orderId, 'regnaar' => $regnaar];
    }

    /** @return array{svar:string, tx_count:int, tx_rows:list<array{kontonr:int,debet:float,kredit:float}>, openpost_count:int, status:?int, regnaar:int} */
    private function runRunner(string $mode, int $orderId, string $kilde): array
    {
        $result = CharacterizationEnv::runChild(
            __DIR__ . '/support/run_bogfor_nu_webservice_flag.php',
            [$mode, $orderId, $kilde, CharacterizationEnv::testDb()]
        );
        $this->assertSame(0, $result['exit'], "runner failed:\nstdout: {$result['stdout']}\nstderr: {$result['stderr']}");
        $lines = array_values(array_filter(explode("\n", trim($result['stdout']))));
        $decoded = $lines ? json_decode((string)end($lines), true) : null;
        $this->assertIsArray($decoded, "expected JSON output, got:\nstdout: {$result['stdout']}\nstderr: {$result['stderr']}");
        return $decoded;
    }

    public function test_ui_posting_refuses_debtor_control_account_missing_from_current_year(): void
    {
        $missingAccount = 58991;
        $otherYear = self::$fx->regnaar() + 99;
        // Exists in another fiscal year only — catches both cross-year kontoplan load and skipped check.
        self::rows(
            "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, lukket, primo, saldo, regnskabsaar)
             VALUES ($1, 'Other year debtor control', 'S', '', '', 0, 0, $2)",
            [$missingAccount, $otherYear]
        );

        $fixture = $this->createOrderForControlAccount($missingAccount, 911);
        $out = $this->runRunner('bogfor_nu', $fixture['order_id'], '');

        $this->assertStringContainsString(
            "Kontonummer $missingAccount findes ikke i kontoplanen for regnskabsaar {$fixture['regnaar']}",
            (string)$out['svar']
        );
        $this->assertSame(0, $out['tx_count'], 'refused posting must leave no committed transaktioner rows');
        $this->assertSame(0, $out['openpost_count'], 'refused posting must leave no committed openpost rows');
    }

    public function test_webservice_posting_still_allows_missing_current_year_control_account(): void
    {
        $missingAccount = 58992;
        $otherYear = self::$fx->regnaar() + 99;
        self::rows(
            "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, lukket, primo, saldo, regnskabsaar)
             VALUES ($1, 'Other year debtor control', 'S', '', '', 0, 0, $2)",
            [$missingAccount, $otherYear]
        );

        $fixture = $this->createOrderForControlAccount($missingAccount, 912);
        $out = $this->runRunner('bogfor_nu', $fixture['order_id'], 'webservice');

        $this->assertSame('OK', $out['svar'], 'webservice path keeps existing behavior');
        $this->assertGreaterThan(0, $out['tx_count']);
    }

    public function test_ui_posting_via_bogfor_refuses_missing_control_account_and_rolls_back(): void
    {
        $missingAccount = 58993;
        $otherYear = self::$fx->regnaar() + 99;
        self::rows(
            "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, lukket, primo, saldo, regnskabsaar)
             VALUES ($1, 'Other year debtor control', 'S', '', '', 0, 0, $2)",
            [$missingAccount, $otherYear]
        );

        // Ensure straksbogfor (DIV kodenr=3 box5) is enabled so bogfor() invokes bogfor_nu().
        self::rows("DELETE FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
        self::rows("INSERT INTO grupper (beskrivelse, kode, kodenr, art, box5) VALUES ('D4 straks', '', 3, 'DIV', 'on')");

        $fixture = $this->createOrderForControlAccount($missingAccount, 913, ['status' => 2]);
        $out = $this->runRunner('bogfor', $fixture['order_id'], '');

        $this->assertStringContainsString(
            "Kontonummer $missingAccount findes ikke i kontoplanen for regnskabsaar {$fixture['regnaar']}",
            (string)$out['svar']
        );
        $this->assertSame(0, $out['tx_count'], 'rolled-back UI bogfor() leaves no transaktioner rows');
        $this->assertSame(0, $out['openpost_count'], 'rolled-back UI bogfor() leaves no openpost rows');
        $this->assertSame(2, $out['status'], 'rolled-back UI bogfor() leaves order status unchanged');
    }

    public function test_bogfor_with_truthy_webservice_flag_preserves_card_account_mapping(): void
    {
        $regnaar = self::$fx->regnaar();
        [$genericCardAccount, $specificCardAccount] = self::$fx->plainAccounts(2);
        $dg = self::one(
            "SELECT box2 FROM grupper WHERE art = 'DG' AND fiscal_year = $1 ORDER BY kodenr LIMIT 1",
            [$regnaar]
        );
        $this->assertNotNull($dg);

        // Configure straksbogfor (DIV kodenr=3 box5), generic Kreditkort account (box10), and POS card map (POS kodenr=1).
        self::rows("DELETE FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
        self::rows(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box5, box10) VALUES ('D4 card', '', 3, 'DIV', 'on', $1)",
            [(string)$genericCardAccount]
        );
        self::rows("DELETE FROM grupper WHERE art = 'POS' AND kodenr = '1' AND fiscal_year = $1", [$regnaar]);
        self::rows(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box5, box6, fiscal_year)
             VALUES ('D4 POS', '', 1, 'POS', 'Dankort', $1, $2)",
            [(string)$specificCardAccount, $regnaar]
        );

        // Both browser ('') and legacy API ('on') paths must preserve card-account mapping.
        foreach (['', 'on'] as $idx => $flag) {
            $fixture = $this->createOrderForControlAccount((int)$dg['box2'], 914 + $idx, [
                'status' => 2,
                'betalingsbet' => 'Kreditkort',
                'felt_1' => 'Dankort',
                'felt_2' => '125',
            ]);

            $out = $this->runRunner('bogfor', $fixture['order_id'], $flag);

            $this->assertSame('OK', $out['svar'], "flag='$flag' must post cleanly");
            $debitedAccounts = array_map(
                static fn(array $r) => $r['kontonr'],
                array_values(array_filter($out['tx_rows'], static fn(array $r) => $r['debet'] > 0))
            );
            $this->assertContains($specificCardAccount, $debitedAccounts, "bogfor(\$id, '$flag') must debit the card-specific account");
            $this->assertNotContains($genericCardAccount, $debitedAccounts, "bogfor(\$id, '$flag') must not fall back to generic card account");
        }
    }

    public function test_pos_sale_on_account_with_empty_debtor_control_account_is_refused_without_openpost(): void
    {
        $regnaar = self::$fx->regnaar();
        $fixture = $this->createOrderForControlAccount(0, 916, [
            'betalingsbet' => 'Netto',
            'felt_1' => 'Konto',
            'felt_2' => '125',
        ]);
        self::rows("UPDATE ordrer SET art = 'PO', felt_5 = '1' WHERE id = $1", [$fixture['order_id']]);
        self::rows(
            "INSERT INTO pos_betalinger (ordre_id, betalingstype, amount, valuta, valutakurs) VALUES ($1, 'Konto', 125.00, 'DKK', 100)",
            [$fixture['order_id']]
        );
        self::rows("DELETE FROM grupper WHERE art = 'POS' AND kodenr = '1' AND fiscal_year = $1", [$regnaar]);
        self::rows(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box2, box7, fiscal_year) VALUES ('D4 POS', '', 1, 'POS', '1000', '1', $1)",
            [$regnaar]
        );

        $out = $this->runRunner('bogfor_nu', $fixture['order_id'], '');

        $this->assertStringContainsString('Kontonummer for Debitorgruppe', (string)$out['svar']);
        $this->assertSame(0, $out['openpost_count'], 'refused POS sale on account must not leave openpost rows');
        $this->assertSame(0, $out['tx_count'], 'refused POS sale on account must not leave transaktioner rows');
    }

    public function test_missing_line_posting_account_returns_error_and_rolls_back(): void
    {
        $regnaar = self::$fx->regnaar();
        $dg = self::one(
            "SELECT box2 FROM grupper WHERE art = 'DG' AND fiscal_year = $1 ORDER BY kodenr LIMIT 1",
            [$regnaar]
        );
        $this->assertNotNull($dg);

        // Use a PO order (skips forkontrolPosteringsbalance) so bogfor_nu() reaches the missing bogf_konto branch.
        $fixture = $this->createOrderForControlAccount((int)$dg['box2'], 917, [
            'betalingsbet' => 'Netto',
            'felt_1' => 'Konto',
            'felt_2' => '125',
        ]);
        self::rows("UPDATE ordrer SET art = 'PO', felt_5 = '1' WHERE id = $1", [$fixture['order_id']]);
        self::rows(
            "INSERT INTO pos_betalinger (ordre_id, betalingstype, amount, valuta, valutakurs) VALUES ($1, 'Konto', 125.00, 'DKK', 100)",
            [$fixture['order_id']]
        );
        self::rows("DELETE FROM grupper WHERE art = 'POS' AND kodenr = '1' AND fiscal_year = $1", [$regnaar]);
        self::rows(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box2, box7, fiscal_year) VALUES ('D4 POS', '', 1, 'POS', '1000', '1', $1)",
            [$regnaar]
        );
        self::rows("UPDATE ordrelinjer SET posnr = -1, bogf_konto = 0 WHERE ordre_id = $1", [$fixture['order_id']]);

        $out = $this->runRunner('bogfor_nu', $fixture['order_id'], '');

        $this->assertSame('Fejl i kontoopsætning', $out['svar'], 'missing bogf_konto must return error rather than exit');
        $this->assertSame(0, $out['tx_count'], 'rolled-back posting must leave no transaktioner rows');
        $this->assertSame(0, $out['openpost_count'], 'rolled-back posting must leave no openpost rows');
    }

    public function test_ui_posting_succeeds_when_debtor_control_account_exists_in_current_year(): void
    {
        $regnaar = self::$fx->regnaar();
        $dg = self::one(
            "SELECT box2 FROM grupper WHERE art = 'DG' AND fiscal_year = $1 ORDER BY kodenr LIMIT 1",
            [$regnaar]
        );
        $this->assertNotNull($dg);
        $validAccount = (int)$dg['box2'];

        $fixture = $this->createOrderForControlAccount($validAccount, 918);
        $out = $this->runRunner('bogfor_nu', $fixture['order_id'], '');

        $this->assertSame('OK', $out['svar'], 'valid UI sales invoice must post cleanly');
        $this->assertGreaterThan(0, $out['tx_count']);
    }
}
