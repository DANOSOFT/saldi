<?php
// tests/characterization/finans/HentOrdrerFlytTest.test.php
//
// Hent ordrer (finans/hentordrer.php) moves an invoice that was not posted
// straight away into the journal.
//
// With "Bogfør straks" off (grupper DIV/3 box5), invoicing leaves the order
// at status 3, and Kassekladde > Hent ordrer > "Flyt til kladde" is how it
// reaches the ledger: flytordre() writes the invoice's journal lines and sets
// the order to status 4. flytordre() split the invoice date with split(),
// which PHP 7 removed, so every Flyt died with "Call to undefined function
// split()" before it wrote anything (testlab round 1,
// deferred-invoice-posting).
//
// Pins: Flyt til kladde on a deferred sales invoice writes the debtor line
// for the gross amount, the revenue line and the VAT line to the journal,
// sets the order to status 4, and posts nothing to transaktioner or openpost
// yet (that happens when the journal is posted).
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class HentOrdrerFlytTest extends CharacterizationTestCase
{
    /** @var list<array{id:string, box5:?string}> grupper DIV/3 before the test turned "Bogfør straks" off; restored in tearDown(). */
    private array $orderSettings = [];

    protected function setUp(): void
    {
        $this->orderSettings = self::rows("SELECT id, box5 FROM grupper WHERE art = 'DIV' AND kodenr = 3");
        $this->assertNotSame([], $this->orderSettings, 'the template tenant has the order settings row (grupper DIV/3)');
        self::rows("UPDATE grupper SET box5 = '' WHERE art = 'DIV' AND kodenr = 3");
    }

    protected function tearDown(): void
    {
        foreach ($this->orderSettings as $row) {
            self::rows('UPDATE grupper SET box5 = $1 WHERE id = $2', [$row['box5'], $row['id']]);
        }
    }

    /**
     * A sales invoice for 2 x 125.00 + 25% VAT, invoiced through ordrefunc's
     * bogfor() with "Bogfør straks" off, so it is left at status 3.
     *
     * @return array{id:int, kontonr:string, konto_id:int, fakturanr:string, fakturadate:string}
     */
    private function deferredInvoice(): array
    {
        $debtor = self::$fx->debtor();
        $item = self::$fx->item(['stocked' => false]);
        $order = self::$fx->order($debtor, [['item' => $item, 'antal' => 2, 'pris' => 125.00]]);
        // opret_ordrelinje() stores the line's project as text; flytordre() books the lines per project.
        self::rows("UPDATE ordrelinjer SET projekt = '' WHERE ordre_id = $1", [$order['id']]);

        $out = $this->runChildJson('run_order_invoice.php', ['invoice', $order['id'], CharacterizationEnv::testDb()]);
        $this->assertSame('OK', $out['bogfor'] ?? null, 'the order is invoiced');

        $row = self::one('SELECT status, fakturanr, fakturadate FROM ordrer WHERE id = $1', [$order['id']]);
        $this->assertSame('3', (string)$row['status'], 'invoiced, not posted: Hent ordrer lists it');
        $this->assertSame([], self::rows('SELECT id FROM transaktioner WHERE ordre_id = $1', [$order['id']]), 'nothing is in the ledger yet');

        return [
            'id' => $order['id'],
            'kontonr' => $debtor['kontonr'],
            'konto_id' => $debtor['id'],
            'fakturanr' => (string)$row['fakturanr'],
            'fakturadate' => (string)$row['fakturadate'],
        ];
    }

    public function test_flyt_til_kladde_moves_a_deferred_invoice_into_the_journal(): void
    {
        $invoice = $this->deferredInvoice();
        $kladdeId = self::$fx->kladde([]);
        $revenueAccount = self::one('SELECT DISTINCT bogf_konto FROM ordrelinjer WHERE ordre_id = $1', [$invoice['id']]);
        $vatAccount = self::one(
            "SELECT sm.box1 FROM adresser a
             JOIN grupper dg ON dg.art = 'DG' AND dg.kodenr = a.gruppe
             JOIN grupper sm ON sm.art = 'SM' AND sm.kodenr = substr(trim(dg.box1), 2, 1)::integer
             WHERE a.id = $1",
            [$invoice['konto_id']]
        );

        $res = self::runChild('run_hentordrer_page.php', [
            "kladde_id=$kladdeId&ordre_id={$invoice['id']}",
            CharacterizationEnv::SESSION_ID,
        ]);
        $this->assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], "the page ran to its end.\nstderr: {$res['stderr']}");

        $lines = array_map(
            static fn(array $r): array => [
                'debet' => $r['d_type'] ? [$r['d_type'], (int)$r['debet']] : null,
                'kredit' => $r['k_type'] ? [$r['k_type'], (int)$r['kredit']] : null,
                'amount' => round((float)$r['amount'], 2),
                'faktura' => $r['faktura'],
            ],
            self::rows(
                'SELECT d_type, debet, k_type, kredit, amount, faktura FROM kassekladde WHERE kladde_id = $1 AND ordre_id = $2 ORDER BY id',
                [$kladdeId, $invoice['id']]
            )
        );
        $this->assertSame(
            [
                ['debet' => ['D', (int)$invoice['kontonr']], 'kredit' => null, 'amount' => 312.5, 'faktura' => $invoice['fakturanr']],
                ['debet' => null, 'kredit' => ['F', (int)$revenueAccount['bogf_konto']], 'amount' => 250.0, 'faktura' => $invoice['fakturanr']],
                ['debet' => null, 'kredit' => ['F', (int)$vatAccount['box1']], 'amount' => 62.5, 'faktura' => $invoice['fakturanr']],
            ],
            $lines,
            'the debtor is debited the gross amount, revenue and VAT are credited'
        );

        $debtorLine = self::one("SELECT forfaldsdate FROM kassekladde WHERE kladde_id = $1 AND ordre_id = $2 AND d_type = 'D'", [$kladdeId, $invoice['id']]);
        $this->assertSame(
            date('Y-m-d', strtotime($invoice['fakturadate'] . ' +8 days')),
            $debtorLine['forfaldsdate'],
            'the debtor line is due on the invoice\'s terms (Netto 8)'
        );

        $order = self::one('SELECT status FROM ordrer WHERE id = $1', [$invoice['id']]);
        $this->assertSame('4', (string)$order['status'], 'the order is marked as moved, so Hent ordrer no longer lists it');
        $this->assertSame([], self::rows('SELECT id FROM transaktioner WHERE ordre_id = $1', [$invoice['id']]), 'the ledger is written when the journal is posted, not by the move');
        $this->assertSame(
            [],
            self::rows('SELECT id FROM openpost WHERE konto_id = $1 AND faktnr = $2', [$invoice['konto_id'], $invoice['fakturanr']]),
            'no open post before the journal is posted'
        );
    }
}
