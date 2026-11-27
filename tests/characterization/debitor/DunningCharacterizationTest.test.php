<?php
// tests/characterization/debitor/DunningCharacterizationTest.test.php
//
// Characterization tests for the dunning generator (debitor/ny_rykker.php).
//
// The automatic run ("Dan rykkere", kontoliste=alle) does three things:
//
//   1. every unsettled open post that is overdue by more than ffdage1 days
//      gets a level-1 dunning order (art='R1') with a fee line;
//   2. every standing R1 older than ffdage2 days is booked and escalated;
//   3. every standing R2 older than ffdage3 days is booked and escalated.
//
// The grace-day arithmetic in (2) and (3) was broken for years by a stray
// semicolon - `$utid = date('U');-$ffdage2*3600*24;` - which made the cutoff
// "now" instead of "now minus the grace period", so a dunning raised
// yesterday was escalated today regardless of the configured grace days. That
// was fixed in b09ccd49 (PR #422). The tests below are the regression guard:
// test_a_level_1_dunning_inside_its_grace_period_is_not_escalated fails
// against the pre-fix code.
//
// "Ny rykker" (rykker_id=...) raises the next level for reminders ticked
// under Bogførte rykkere. It used to skip the grace period: an R1 posted
// today got its R2 straight away (testlab dunning-run, R2 10809). It now
// waits ffdage2 / ffdage3 days, the same cutoffs as (2) and (3).
//
// The grace-day counts and the fee item are tenant settings that a stock
// installation does not ship - see Fixtures::dunningConfig().
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.
// 20261028 CL/LH The first-grace-period test pinned a defect that #444 fixed; it now asserts the fix.
// 20261105 CL/LH Ny rykker waits the grace period of the reminder it escalates.
// 20261111 CDX/NTR Isolate the test class database and session.
// 20261126 CL/ASR Stripe port (#1098): aging a dunning moves its fakturadate with its ordredate, as ny_rykker.php writes both on the day it creates the reminder.
//                 Renteloven § 9 b (L3, includes/reminderLegal.php) counts the 10 days before a next level with a fee from the day the previous reminder was sent (fakturadate), so a reminder aged by ordredate only still counted as sent today.
// 20261126 CL/ASR Stripe port (#1134): the runs pass thisDb (the test database), as debitor/rapport.php does.
//                 A run for another ledger creates no dunning.
// 20261126 CL/ASR Stripe port (#1147): Ny rykker escalates a booked dunning (bookDunning()), as the overview offers it under Bogførte rykkere only.
//                 reminder_create_batch() (includes/reminderCreate.php) refuses a next level while the previous fee has no open item, where the page made a fee line without one.
// 20261126 ASR Review M2: the test of a level-1 dunning past its grace period uses a fee item that can be booked.
//              It asserts that the R1 is booked (status 4) and closed, that the debtor gets an R2, and that no alert is printed; betalt alone hid an aborted run.
// 20261126 ASR Review I1: "Ryk alle" handles each debtor and chain on its own.
//              A debtor without an exchange rate, or a fee that cannot be booked, is skipped with a notice, and the other debtors still get their reminders.
// 20261126 ASR Review I2: Ny rykker on a level whose next level exists makes no second one and leaves the existing one as it was.
// 20261126 ASR Review I3: "Ryk alle" neither books nor escalates an automatic reminder that was not sent.
// 20261126 ASR Review M1: the page's notices are one message, and the popup moves on to luk.php only after it is read (SaldiDialog.alert().then()); no meta refresh outside <noscript>.
// 20261126 ASR Review R1: a debtor in another currency (EUR at 746,12) gets its R2 from "Ryk alle" and from Bogfør + Ny rykker, and the R2 lists the R1's fee open item.
// 20261126 ASR Review GPT-F-1: Bogfør on a reminder with a bookable and a stock-tracked line keeps nothing, and books once the item is corrected.
//              A reminder left at status 3 without anything posted is listed under Åbne rykkere and booked by Bogfør or "Ryk alle"; one with a fee open item is not booked again.
// 20261126 ASR Review GPT-F-2: a debtor whose statement the database rejects (a fee item group without a booking account) is rolled back and named, and "Ryk alle" goes on with the others.
//              Before, db_modify() ended the request there, and the reminders made for the other debtors in the same run were lost with it.
// 20261126 ASR Review CL2-1: a level-1 dunning without a fee that was shown in the reminder page (debitor/rykker.php writes its sum, '0.000') is escalated by Bogfør + Ny rykker and by "Ryk alle".
//              Before, both refused it for good with "Det forrige rykkergebyr skal bogføres før næste rykker.", as no fee open item is booked for it.
// 20261126 ASR Review AR2-2: Bogfør and Ny rykker on an automatic reminder that is paused or whose sending failed or is uncertain point to Automatiske rykkere and do not offer to delete it; a draft keeps "eller slet den".
// 20261126 ASR Review AR2-1: "Ryk alle" deletes an open reminder of a customer without open items only when it never went out.
//              One that was printed or mailed (reminder_dispatch), sent by the automatic flow, whose sending is unresolved, or mailed as a link reminder stays, unbooked, and the notice names it.
//              A deleted automatic draft takes its job with it, and the deletion is logged as the flow's event 'deleted'.
// 20261126 ASR Review R3E2E-1: a classic reminder that was only printed or mailed (a reminder_dispatch row) is deleted by "Ryk alle" with that row once its customer has paid, as before the update.
//              One reminder is printed on paper and one mailed through the real Udskriv (support/run_rykkerprint.php), and the next "Ryk alle" names neither.
// 20261127 ASR Review AR3-1: a paused automatic reminder without an unresolved sending attempt is offered to be approved or deleted (8234); paused with one it keeps the pointer to Registrér som sendt (8231).

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class DunningCharacterizationTest extends CharacterizationTestCase
{
    private const FFDAGE1 = 8;   // days overdue before an invoice is dunned
    private const FFDAGE2 = 10;  // days an R1 may stand before escalation
    private const FFDAGE3 = 14;  // days an R2 may stand before escalation

    private static int $faktnrSeq = 7000;

    protected function setUp(): void
    {
        // Every test starts from a tenant with no receivables and no dunnings
        // of its own, so an "all debtors" run only sees what the test put
        // there.
        self::rows("DELETE FROM ordrelinjer WHERE ordre_id IN (SELECT id FROM ordrer WHERE art LIKE 'R%')");
        self::rows("DELETE FROM ordrer WHERE art LIKE 'R%'");
        self::rows("DELETE FROM openpost");

        self::$fx->dunningConfig(self::FFDAGE1, self::FFDAGE2, self::FFDAGE3);
    }

    private static function nextFaktnr(): string
    {
        return (string)(++self::$faktnrSeq);
    }

    /** @param string|null $thisDb the report's ledger; null = the test database, as debitor/rapport.php sends it */
    private function runDunning(?string $thisDb = null): string
    {
        return self::runChild('run_ny_rykker.php', [CharacterizationEnv::sessionId(), $thisDb ?? CharacterizationEnv::testDb()])['stdout'];
    }

    /**
     * "Ny rykker" on the given reminders, as debitor/rapport.php sends it.
     *
     * @return string the page's output
     */
    private function newReminder(int $reminderId): string
    {
        return self::runChild('run_ny_rykker.php', [CharacterizationEnv::sessionId(), CharacterizationEnv::testDb(), (string)$reminderId])['stdout'];
    }

    /**
     * A debtor with one overdue invoice and the R1 the automatic run raises
     * for it. ny_rykker.php does not read the status, so the R1 is not posted.
     *
     * @return array{0:array, 1:array<string,string>} the debtor and the R1 row
     */
    private function debtorWithLevelOneDunning(): array
    {
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, 810.00, self::FFDAGE1 + 5, self::nextFaktnr());
        $this->runDunning();
        $dunnings = $this->dunningsFor($debtor);
        $this->assertCount(1, $dunnings, 'precondition: a level-1 dunning exists');
        return [$debtor, $dunnings[0]];
    }

    /**
     * Books a dunning as "Bogfør" leaves it for "Ny rykker": status 4 and, for a fee, its open item (refnr = the dunning),
     * which reminder_create_batch() carries into the next level.
     */
    private function bookDunning(array $debtor, int $dunningId): void
    {
        $row = self::one('SELECT sum, fakturanr FROM ordrer WHERE id = $1', [$dunningId]);
        self::rows('UPDATE ordrer SET status = 4 WHERE id = $1', [$dunningId]);
        if ((float)$row['sum'] > 0) {
            $openPost = self::$fx->openPost($debtor, round((float)$row['sum'], 2), 0, (string)$row['fakturanr']);
            self::rows('UPDATE openpost SET refnr = $1 WHERE id = $2', [$dunningId, $openPost]);
        }
    }

    private static function ageDunning(int $dunningId, int $days): void
    {
        self::rows(
            'UPDATE ordrer SET ordredate = $1, fakturadate = $1 WHERE id = $2',
            [date('Y-m-d', strtotime("-$days days")), $dunningId]
        );
    }

    /** Fee item in an untracked item group, so "Ryk alle" really posts a fee (the default fixture fee item is stock-tracked). */
    private function bookableFee(): void
    {
        $fee = self::$fx->item(['salgspris' => 100, 'kostpris' => 0, 'stocked' => false, 'beskrivelse' => 'chartest bogfoerbart gebyr']);
        self::rows("UPDATE formularer SET xb = $1 WHERE beskrivelse = 'GEBYR'", [$fee['id']]);
    }

    /** A debtor in a copy of the first debtor group whose currency has no exchange rate. */
    private function debtorWithoutRate(): array
    {
        return $this->debtorInCurrency('977', 'XYZ');
    }

    /** A debtor in a copy of the first debtor group with the given currency (grupper.box3). */
    private function debtorInCurrency(string $kodenr, string $currency): array
    {
        $group = self::one("SELECT * FROM grupper WHERE art = 'DG' ORDER BY kodenr LIMIT 1");
        unset($group['id']);
        $group['kodenr'] = $kodenr;
        $group['box3'] = $currency;
        $group['beskrivelse'] = "chartest $currency";
        self::rows('DELETE FROM grupper WHERE art = $1 AND kodenr = $2', ['DG', $kodenr]);
        $columns = array_keys($group);
        $placeholders = array_map(static fn(int $n): string => '$' . ($n + 1), array_keys($columns));
        self::rows('INSERT INTO grupper (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')', array_values($group));
        $debtor = self::$fx->debtor();
        self::rows('UPDATE adresser SET gruppe = $1 WHERE id = $2', [(int)$kodenr, $debtor['id']]);
        return $debtor;
    }

    /** A debtor in EUR at 746,12: 100 kr. is 13,403 EUR, which is 100,003 kr. again when the fee is booked. */
    private function debtorInEuro(): array
    {
        $debtor = $this->debtorInCurrency('978', 'EUR');
        $fiscalYear = (int)self::one("SELECT fiscal_year FROM grupper WHERE art = 'DG' AND kodenr = '978'")['fiscal_year'];
        self::rows('DELETE FROM valuta WHERE gruppe = 91');
        self::rows("DELETE FROM grupper WHERE art = 'VK' AND box1 = 'EUR'");
        self::rows("INSERT INTO grupper (art, kodenr, box1, beskrivelse, fiscal_year) VALUES ('VK', '91', 'EUR', 'chartest Euro', $1)", [$fiscalYear]);
        self::rows("INSERT INTO valuta (gruppe, valdate, kurs) VALUES (91, '2000-01-01', 746.12)");
        return $debtor;
    }

    /** "Bogfør" on the reminder overview (includes/rapportfunc.php bogfor_rykker()), as its form posts it. */
    private function bookWithTheOverview(int $dunningId): string
    {
        $post = ['rapportart' => 'openpost', 'submit' => 'Bogfør', 'rykkerantal' => '1', 'rykker_id' => ['1' => (string)$dunningId],
            'rykkerbox' => ['1' => 'on'], 'thisDb' => CharacterizationEnv::testDb()];
        $request = ['get' => ['kontonr' => '', 'skjul_aabenpost' => 'on'], 'post' => $post];
        return self::runChild('run_openpost_report.php', [CharacterizationEnv::sessionId(), json_encode($request)])['stdout'];
    }

    /**
     * The reminder overview with Åbne rykkere and Bogførte rykkere expanded, as the report's own links request it.
     *
     * @return array{open: string, booked: string} The HTML of the two section tables.
     */
    private function overviewSections(): array
    {
        $get = ['rapportart' => 'openpost', 'submit' => 'ok', 'openpost_content' => '1', 'kontonr' => '', 'skjul_aabenpost' => 'on',
            'vis_aaben_rykker' => 'on', 'vis_bogfort_rykker' => 'on'];
        $html = self::runChild('run_openpost_report.php', [CharacterizationEnv::sessionId(), json_encode(['get' => $get, 'post' => (object)[]])])['stdout'];
        $section = static function (int $n) use ($html): string {
            $start = strpos($html, "id='rykkerOverviewTable$n'");
            if ($start === false) {
                return '';
            }
            $end = strpos($html, '</table>', $start);
            return substr($html, $start, $end === false ? null : $end - $start);
        };
        return ['open' => $section(1), 'booked' => $section(3)];
    }

    /** @return bool Whether the overview section lists the reminder (its link to the reminder editor). */
    private static function lists(string $section, int $dunningId): bool
    {
        return str_contains($section, "rykker.php?rykker_id=$dunningId\"");
    }

    /**
     * What a booking of the dunning left: status, the lines' accounts, the fee open items and the postings.
     *
     * @return array{status: int, accounts: list<array<string,string>>, open: list<float>, debet: float, kredit: float, postings: int}
     */
    private static function booking(int $dunningId): array
    {
        $journal = self::rows('SELECT debet, kredit FROM transaktioner WHERE ordre_id = $1', [$dunningId]);
        return [
            'status' => (int)self::one('SELECT status FROM ordrer WHERE id = $1', [$dunningId])['status'],
            'accounts' => self::rows('SELECT id, bogf_konto FROM ordrelinjer WHERE ordre_id = $1 ORDER BY id', [$dunningId]),
            'open' => array_map(static fn(array $r): float => round((float)$r['amount'], 2),
                self::rows('SELECT amount FROM openpost WHERE refnr = $1 ORDER BY id', [$dunningId])),
            'debet' => round(self::sumOf($journal, 'debet'), 2),
            'kredit' => round(self::sumOf($journal, 'kredit'), 2),
            'postings' => count($journal),
        ];
    }

    /** @return int varer.id of a stock-tracked item (the fixture's default group), for a line the booking refuses. */
    private function trackedItemLine(int $dunningId, float $price): int
    {
        $item = self::$fx->item(['salgspris' => $price, 'kostpris' => 0, 'beskrivelse' => 'chartest lagerfoert rente']);
        $pos = (int)self::one('SELECT coalesce(max(posnr), 0) + 1 AS pos FROM ordrelinjer WHERE ordre_id = $1', [$dunningId])['pos'];
        self::rows(
            "INSERT INTO ordrelinjer (ordre_id, posnr, vare_id, varenr, beskrivelse, antal, pris, rabat, serienr)
             VALUES ($1, $2, $3, $4, 'chartest rente', 1, $5, 0, $6)",
            [$dunningId, $pos, $item['id'], $item['varenr'], $price, date('Y-m-d')]
        );
        self::rows('UPDATE ordrer SET sum = sum + $1 WHERE id = $2', [$price, $dunningId]);
        return $item['id'];
    }

    /** Moves an item to the group of the bookable fee item (not stock-tracked), as correcting the item's group does. */
    private static function untrack(int $itemId): void
    {
        self::rows("UPDATE varer SET gruppe = (SELECT f.gruppe FROM varer f WHERE f.id = (SELECT xb FROM formularer WHERE beskrivelse = 'GEBYR' ORDER BY formular LIMIT 1)) WHERE id = $1", [$itemId]);
    }

    /**
     * Puts the dunning's fee line on an item whose group (a copy of an untracked item group) has no booking account (box4), so
     * booking it builds "update ordrelinjer set bogf_konto= where ...", which the database rejects.
     */
    private function feeWithoutAccount(int $dunningId): void
    {
        $group = self::one("SELECT * FROM grupper WHERE art = 'VG' AND coalesce(trim(box8), '') = '' AND coalesce(trim(box4), '') <> '' ORDER BY kodenr LIMIT 1");
        unset($group['id']);
        $group['kodenr'] = '976';
        $group['box4'] = '';
        $group['beskrivelse'] = 'chartest uden bogfoeringskonto';
        self::rows("DELETE FROM grupper WHERE art = 'VG' AND kodenr = '976'");
        $columns = array_keys($group);
        $placeholders = array_map(static fn(int $n): string => '$' . ($n + 1), array_keys($columns));
        self::rows('INSERT INTO grupper (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')', array_values($group));
        $item = self::$fx->item(['salgspris' => 100, 'kostpris' => 0, 'stocked' => false, 'beskrivelse' => 'chartest gebyr uden konto']);
        self::rows('UPDATE varer SET gruppe = 976 WHERE id = $1', [$item['id']]);
        self::rows('UPDATE ordrelinjer SET vare_id = $1, varenr = $2 WHERE ordre_id = $3 AND vare_id > 0', [$item['id'], $item['varenr'], $dunningId]);
    }

    /** @return int|null openpost.id of the fee open item booked for the dunning that the dunning's next level lists, or null */
    private static function carriedFee(int $dunningId, int $nextId): ?int
    {
        $row = self::one(
            'SELECT o.id FROM openpost o JOIN ordrelinjer l ON l.enhed = o.id::text WHERE o.refnr = $1 AND l.ordre_id = $2',
            [$dunningId, $nextId]
        );
        return $row ? (int)$row['id'] : null;
    }

    /** @return array{0:array, 1:int} a debtor and its R1, dated past ffdage2 so "Ryk alle" books and escalates it */
    private function debtorWithAgedLevelOne(): array
    {
        [$debtor, $r1] = $this->debtorWithLevelOneDunning();
        self::ageDunning((int)$r1['id'], self::FFDAGE2 + 5);
        return [$debtor, (int)$r1['id']];
    }

    /** @return list<string> the levels of a debtor's dunnings, oldest first */
    private function levelsFor(array $debtor): array
    {
        return array_map(static fn(array $d): string => trim((string)$d['art']), $this->dunningsFor($debtor));
    }

    /** @return list<array<string,string>> dunning orders for a debtor, oldest first */
    private function dunningsFor(array $debtor): array
    {
        return self::rows(
            "SELECT id, art, status, betalt, sum, ordredate FROM ordrer
             WHERE konto_id = $1 AND art LIKE 'R%' ORDER BY id",
            [$debtor['id']]
        );
    }

    public function test_an_invoice_overdue_beyond_the_grace_period_is_dunned(): void
    {
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, 1000.00, self::FFDAGE1 + 5, self::nextFaktnr());

        $this->runDunning();

        $dunnings = $this->dunningsFor($debtor);
        $this->assertCount(1, $dunnings, 'one level-1 dunning is raised');
        $this->assertSame('R1', trim($dunnings[0]['art']), 'the dunning is at level 1');
    }

    /**
     * ffdage1, the "days overdue before the first dunning" setting, holds an
     * invoice back until its grace period has passed.
     *
     * ny_rykker.php used to compute the cutoff before $dd existed, so the
     * setting was discarded and an invoice was dunned the day after it fell
     * due. #444 fixed that (dunning_cutoff_date($ffdage1)).
     */
    public function test_an_invoice_inside_the_first_grace_period_is_not_dunned(): void
    {
        $debtor = self::$fx->debtor();
        // Well inside the configured 8-day grace period.
        self::$fx->openPost($debtor, 1000.00, 1, self::nextFaktnr());

        $this->runDunning();

        $this->assertSame(
            [],
            $this->dunningsFor($debtor),
            'an invoice one day overdue is not dunned while ffdage1 = ' . self::FFDAGE1
        );
    }

    /**
     * An invoice that is not yet due is not dunned.
     */
    public function test_an_invoice_not_yet_due_is_not_dunned(): void
    {
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, 1000.00, -5, self::nextFaktnr()); // due in five days

        $this->runDunning();

        $this->assertSame([], $this->dunningsFor($debtor), 'an invoice that is not yet due is not dunned');
    }

    /** A settled open post is not a receivable and must not be dunned. */
    public function test_a_settled_open_post_is_not_dunned(): void
    {
        $debtor = self::$fx->debtor();
        $postId = self::$fx->openPost($debtor, 1000.00, self::FFDAGE1 + 30, self::nextFaktnr());
        self::rows("UPDATE openpost SET udlignet = '1' WHERE id = $1", [$postId]);

        $this->runDunning();

        $this->assertSame([], $this->dunningsFor($debtor), 'settled posts are not dunned');
    }

    /** A credit balance (negative open post) is not a receivable either. */
    public function test_a_credit_balance_is_not_dunned(): void
    {
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, -1000.00, self::FFDAGE1 + 30, self::nextFaktnr());

        $this->runDunning();

        $this->assertSame([], $this->dunningsFor($debtor), 'a customer in credit is not dunned');
    }

    /** The dunning carries a fee line taken from the configured fee item. */
    public function test_the_dunning_carries_the_configured_fee(): void
    {
        $config = self::$fx->dunningConfig(self::FFDAGE1, self::FFDAGE2, self::FFDAGE3, 100.00);
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, 2500.00, self::FFDAGE1 + 5, self::nextFaktnr());

        $this->runDunning();

        $dunnings = $this->dunningsFor($debtor);
        $this->assertCount(1, $dunnings);

        $feeLines = self::rows(
            'SELECT pris, antal FROM ordrelinjer WHERE ordre_id = $1 AND vare_id = $2',
            [(int)$dunnings[0]['id'], $config['fee_item']['id']]
        );
        $this->assertCount(1, $feeLines, 'the dunning has exactly one fee line');
        $this->assertEqualsWithDelta(100.00, (float)$feeLines[0]['pris'], 0.005, 'the fee is the item sales price');
        $this->assertEqualsWithDelta(100.00, (float)$dunnings[0]['sum'], 0.005, 'the dunning total is the fee');
    }

    /** Two overdue invoices for one debtor are collected into a single dunning. */
    public function test_several_overdue_invoices_produce_one_dunning_per_debtor(): void
    {
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, 400.00, self::FFDAGE1 + 5, self::nextFaktnr());
        self::$fx->openPost($debtor, 600.00, self::FFDAGE1 + 9, self::nextFaktnr());

        $this->runDunning();

        $dunnings = $this->dunningsFor($debtor);
        $this->assertCount(1, $dunnings, 'one dunning covers all of a debtor\'s overdue invoices');

        $postLines = self::rows(
            "SELECT enhed FROM ordrelinjer WHERE ordre_id = $1 AND enhed <> '' AND enhed IS NOT NULL",
            [(int)$dunnings[0]['id']]
        );
        $this->assertCount(2, $postLines, 'both open posts are listed on the dunning');
    }

    /** Separate debtors get separate dunnings. */
    public function test_each_debtor_gets_its_own_dunning(): void
    {
        $first = self::$fx->debtor();
        $second = self::$fx->debtor();
        self::$fx->openPost($first, 100.00, self::FFDAGE1 + 5, self::nextFaktnr());
        self::$fx->openPost($second, 200.00, self::FFDAGE1 + 5, self::nextFaktnr());

        $this->runDunning();

        $this->assertCount(1, $this->dunningsFor($first));
        $this->assertCount(1, $this->dunningsFor($second));
    }

    /**
     * A report rendered in another ledger, or a popup without its ledger,
     * creates no dunning in the session's ledger.
     */
    public function test_a_run_for_another_ledger_creates_no_dunning(): void
    {
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, 300.00, self::FFDAGE1 + 5, self::nextFaktnr());

        foreach ([CharacterizationEnv::testDb() . '_other', ''] as $thisDb) {
            $this->assertStringContainsString('Regnskabet er skiftet', $this->runDunning($thisDb), 'the page names why it stopped');
        }

        $this->assertCount(0, $this->dunningsFor($debtor));
        $this->runDunning();
        $this->assertCount(1, $this->dunningsFor($debtor), 'the same run for the report\'s own ledger dunns the debtor');
    }

    /**
     * REGRESSION GUARD for the grace-day bug fixed in b09ccd49 (PR #422).
     *
     * A level-1 dunning raised inside its ffdage2 grace period must be left
     * standing. Before the fix, `$rykkerfrist2` was today rather than today
     * minus ffdage2, so any dunning raised before today was booked and
     * escalated immediately - customers were charged the level-2 fee days or
     * weeks early.
     */
    public function test_a_level_1_dunning_inside_its_grace_period_is_not_escalated(): void
    {
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, 1000.00, self::FFDAGE1 + 5, self::nextFaktnr());
        $this->runDunning();

        $dunnings = $this->dunningsFor($debtor);
        $this->assertCount(1, $dunnings, 'precondition: a level-1 dunning exists');
        $dunningId = (int)$dunnings[0]['id'];

        // Age it to just inside the grace period.
        self::rows(
            "UPDATE ordrer SET ordredate = $1, fakturadate = $1 WHERE id = $2",
            [date('Y-m-d', strtotime('-' . (self::FFDAGE2 - 2) . ' days')), $dunningId]
        );

        $this->runDunning();

        $after = self::one('SELECT art, betalt FROM ordrer WHERE id = $1', [$dunningId]);
        $this->assertSame('R1', trim((string)$after['art']), 'the dunning stays at level 1');
        $this->assertNotSame(
            'on',
            trim((string)$after['betalt']),
            'the dunning is not booked before its grace period is up (ny_rykker.php:132)'
        );
    }

    /**
     * The other half of the same guard: once the grace period HAS passed, the
     * level-1 dunning is booked, so the fix did not simply disable escalation.
     * With a fee item that can be booked, the R1 is booked and closed, the
     * debtor gets its R2, and no alert is printed.
     */
    public function test_a_level_1_dunning_past_its_grace_period_is_booked(): void
    {
        $this->bookableFee();
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, 1000.00, self::FFDAGE1 + 5, self::nextFaktnr());
        $this->runDunning();

        $dunnings = $this->dunningsFor($debtor);
        $this->assertCount(1, $dunnings, 'precondition: a level-1 dunning exists');
        $dunningId = (int)$dunnings[0]['id'];

        self::rows(
            "UPDATE ordrer SET ordredate = $1, fakturadate = $1 WHERE id = $2",
            [date('Y-m-d', strtotime('-' . (self::FFDAGE2 + 5) . ' days')), $dunningId]
        );

        $page = $this->runDunning();

        $after = self::one('SELECT status, betalt FROM ordrer WHERE id = $1', [$dunningId]);
        $this->assertSame('4', (string)$after['status'], 'the dunning is booked once its grace period has passed');
        $this->assertSame('on', trim((string)$after['betalt']), 'and closed, as its next level carries it on');
        $this->assertSame(['R1', 'R2'], $this->levelsFor($debtor), 'the debtor gets its 2nd reminder');
        $this->assertStringNotContainsString('alert(', $page, 'no alert: nothing was refused or rolled back');
    }

    /**
     * REGRESSION GUARD for testlab dunning-run (R2 10809): Ny rykker on an
     * R1 raised today is refused, and the page says which løbenr.
     */
    public function test_a_new_reminder_on_a_level_1_dunning_raised_today_is_refused(): void
    {
        [$debtor, $r1] = $this->debtorWithLevelOneDunning();
        $ordrenr = (string)self::one('SELECT ordrenr FROM ordrer WHERE id = $1', [(int)$r1['id']])['ordrenr'];

        $page = $this->newReminder((int)$r1['id']);

        $this->assertSame(['R1'], $this->levelsFor($debtor), 'no R2 is raised while ffdage2 = ' . self::FFDAGE2);
        $this->assertStringContainsString("løbenr. $ordrenr,", (string)self::notice($page), 'the refusal is shown and names the løbenr');
    }

    /**
     * The page's one message, as formReminderAlertThenGo() prints it: shown with Saldi's message box, and the popup moves on
     * to luk.php when it is read.
     *
     * @return string|null The message, or null when the page shows none.
     */
    private static function notice(string $page): ?string
    {
        if (!preg_match('/\(function \(msg, url\) \{.*?\}\)\((".*?"), "\.\.\/includes\/luk\.php"\);<\/script>/s', $page, $m)) {
            return null;
        }
        return (string)json_decode($m[1]);
    }

    /**
     * Review M1: Saldi's message box does not block like alert(), so the meta refresh to luk.php right after the notice closed
     * the popup before it could be read. The notices are one message, and the popup moves on when OK is pressed.
     */
    public function test_the_notice_is_read_before_the_popup_closes(): void
    {
        [, $r1] = $this->debtorWithLevelOneDunning();

        $page = $this->newReminder((int)$r1['id']);

        $this->assertNotNull(self::notice($page), "the notice is shown:\n$page");
        $this->assertSame(1, substr_count($page, 'SaldiDialog.alert('), 'one message');
        $this->assertStringContainsString('SaldiDialog.alert(msg).then(go)', $page, 'the popup moves on once the message is read');
        $this->assertStringNotContainsString('<BODY onLoad', $page);
        $this->assertStringNotContainsString('http-equiv="refresh"', preg_replace('#<noscript>.*?</noscript>#s', '', $page),
            'no meta refresh moves the popup on before the message is read (only for a browser without script)');

        // Without a notice the page closes the popup at once, as before.
        $this->bookableFee();
        $debtor = self::$fx->debtor();
        self::$fx->openPost($debtor, 500.00, self::FFDAGE1 + 5, self::nextFaktnr());
        $quiet = $this->runDunning();
        $this->assertNull(self::notice($quiet));
        $this->assertStringContainsString('<meta http-equiv="refresh" content="0;URL=../includes/luk.php">', $quiet);
    }

    /** The cutoff is the automatic run's: an R1 must be more than ffdage2 days old. */
    public function test_a_new_reminder_waits_until_the_level_1_dunning_is_past_ffdage2(): void
    {
        [$debtor, $r1] = $this->debtorWithLevelOneDunning();
        $this->bookDunning($debtor, (int)$r1['id']);

        self::ageDunning((int)$r1['id'], self::FFDAGE2);
        $this->newReminder((int)$r1['id']);
        $this->assertSame(['R1'], $this->levelsFor($debtor), 'an R1 exactly ffdage2 days old is not escalated, as in the automatic run');

        self::ageDunning((int)$r1['id'], self::FFDAGE2 + 1);
        $page = $this->newReminder((int)$r1['id']);
        $this->assertSame(['R1', 'R2'], $this->levelsFor($debtor), 'an R1 past ffdage2 gets its R2');
        $this->assertStringNotContainsString('<script>alert(', $page, 'no refusal for an escalation that is due');
    }

    /** R2 -> R3 waits ffdage3, not ffdage2. */
    public function test_a_new_reminder_waits_until_the_level_2_dunning_is_past_ffdage3(): void
    {
        [$debtor, $r1] = $this->debtorWithLevelOneDunning();
        $this->bookDunning($debtor, (int)$r1['id']);
        self::ageDunning((int)$r1['id'], self::FFDAGE3 + 10);
        $this->newReminder((int)$r1['id']);
        $dunnings = $this->dunningsFor($debtor);
        $this->assertSame(['R1', 'R2'], $this->levelsFor($debtor), 'precondition: an R2 exists');
        $r2Id = (int)$dunnings[1]['id'];
        $this->bookDunning($debtor, $r2Id);

        self::ageDunning($r2Id, self::FFDAGE3);
        $this->newReminder($r2Id);
        $this->assertSame(['R1', 'R2'], $this->levelsFor($debtor), 'an R2 past ffdage2 but not past ffdage3 is not escalated');

        self::ageDunning($r2Id, self::FFDAGE3 + 1);
        $this->newReminder($r2Id);
        $this->assertSame(['R1', 'R2', 'R3'], $this->levelsFor($debtor), 'an R2 past ffdage3 gets its R3');
    }

    /**
     * Review I1: one debtor that cannot be reminded (here: no exchange rate) is skipped with a notice.
     * The other debtors still get their reminders, and a chain is booked and escalated together.
     */
    public function test_one_debtor_that_cannot_be_reminded_does_not_stop_the_others(): void
    {
        $this->bookableFee();
        [$a, $r1] = $this->debtorWithAgedLevelOne();
        $b = self::$fx->debtor();
        self::$fx->openPost($b, 500.00, self::FFDAGE1 + 5, self::nextFaktnr());
        $c = $this->debtorWithoutRate();
        self::$fx->openPost($c, 400.00, self::FFDAGE1 + 5, self::nextFaktnr());

        $page = $this->runDunning();

        $this->assertSame(['R1', 'R2'], $this->levelsFor($a), 'A is booked and escalated');
        $after = self::one('SELECT status, betalt FROM ordrer WHERE id = $1', [$r1]);
        $this->assertSame(['4', 'on'], [(string)$after['status'], trim((string)$after['betalt'])], 'A\'s R1 is booked and closed');
        $this->assertSame(['R1'], $this->levelsFor($b), 'B gets its first reminder');
        $this->assertSame([], $this->levelsFor($c), 'C has no exchange rate and is skipped');
        $this->assertStringContainsString('valutakurs', $page, 'the notice says why C was skipped');
    }

    /**
     * Review I1: a fee that cannot be booked (a stock-tracked fee item) leaves its reminder as it was: not booked, not closed and
     * not escalated. The other debtors still get their reminders.
     */
    public function test_a_fee_that_cannot_be_booked_leaves_its_dunning_as_it_was(): void
    {
        [$a, $r1] = $this->debtorWithAgedLevelOne(); // dunningConfig()'s fee item is stock-tracked
        $b = self::$fx->debtor();
        self::$fx->openPost($b, 500.00, self::FFDAGE1 + 5, self::nextFaktnr());

        $page = $this->runDunning();

        $this->assertSame(['R1'], $this->levelsFor($a), 'no R2 without a booked R1');
        $after = self::one('SELECT status, betalt FROM ordrer WHERE id = $1', [$r1]);
        $this->assertLessThan(3, (int)$after['status'], 'the R1 stays under Åbne rykkere');
        $this->assertNotSame('on', trim((string)$after['betalt']), 'the R1 is not closed');
        $this->assertSame([], self::rows('SELECT id FROM openpost WHERE refnr = $1', [$r1]), 'no fee open item');
        $this->assertSame(['R1'], $this->levelsFor($b), 'B gets its first reminder');
        $this->assertStringContainsString('lagerf', $page, 'the notice says why A was not escalated');
    }

    /**
     * Review I2: Ny rykker from a stale overview (or a second click) on an R1 whose R2 already exists makes no second R2,
     * and the existing R2 keeps its lines and fee.
     */
    public function test_a_new_reminder_on_a_level_that_already_has_its_next_level_makes_no_second_one(): void
    {
        $this->bookableFee();
        [$debtor, $r1] = $this->debtorWithAgedLevelOne();
        $this->runDunning(); // books the R1 and makes its R2
        $this->assertSame(['R1', 'R2'], $this->levelsFor($debtor), 'precondition: the R2 exists');
        $r2 = (int)$this->dunningsFor($debtor)[1]['id'];
        $linesBefore = self::rows('SELECT id, pris FROM ordrelinjer WHERE ordre_id = $1 ORDER BY id', [$r2]);

        $page = $this->newReminder($r1);

        $this->assertSame(['R1', 'R2'], $this->levelsFor($debtor), 'one R2 per chain');
        $this->assertSame($linesBefore, self::rows('SELECT id, pris FROM ordrelinjer WHERE ordre_id = $1 ORDER BY id', [$r2]), 'the R2 is left as it was');
        $this->assertStringContainsString('alert(', $page, 'a notice says why');
    }

    /**
     * Review I3: an R1 the automatic flow drafted and nobody approved or sent is neither booked nor escalated by "Ryk alle",
     * as its fee is not owed. (The table is the one includes/opdat_func/reminderAutomation.php installs.)
     */
    public function test_an_automatic_reminder_that_was_not_sent_is_neither_booked_nor_escalated(): void
    {
        self::rows("CREATE TABLE IF NOT EXISTS reminder_automation_jobs (
            order_id integer PRIMARY KEY, previous_id integer, mode varchar(12) NOT NULL,
            state varchar(12) NOT NULL, created_at timestamptz NOT NULL DEFAULT current_timestamp,
            attempted_at timestamptz, sent_at timestamptz, sent_date date,
            channel varchar(12) NOT NULL DEFAULT '', error text NOT NULL DEFAULT '', actor text NOT NULL DEFAULT '')");
        self::rows('DELETE FROM reminder_automation_jobs');
        $this->bookableFee();
        [$debtor, $r1] = $this->debtorWithAgedLevelOne();
        self::rows("INSERT INTO reminder_automation_jobs (order_id, mode, state, actor) VALUES ($1, 'approval', 'approval', 'reminder_due')", [$r1]);

        $this->runDunning();

        $this->assertSame(['R1'], $this->levelsFor($debtor), 'no 2nd reminder for a 1st reminder that never went out');
        $after = self::one('SELECT status, betalt FROM ordrer WHERE id = $1', [$r1]);
        $this->assertLessThan(3, (int)$after['status'], 'the draft is not booked');
        $this->assertNotSame('on', trim((string)$after['betalt']), 'and not closed');
        $this->assertSame([], self::rows('SELECT id FROM openpost WHERE refnr = $1', [$r1]), 'no fee open item');
        $this->assertSame('approval', (string)self::one('SELECT state FROM reminder_automation_jobs WHERE order_id = $1', [$r1])['state']);

        // Once the flow has sent it, the same run books and escalates it.
        self::rows("UPDATE reminder_automation_jobs SET state = 'sent', sent_date = $1 WHERE order_id = $2",
            [date('Y-m-d', strtotime('-' . (self::FFDAGE2 + 5) . ' days')), $r1]);
        $this->runDunning();
        $this->assertSame(['R1', 'R2'], $this->levelsFor($debtor), 'a sent reminder is escalated as before');
    }

    /**
     * Review R1: a debtor in another currency. Its R1's fee is booked in kroner with three decimals (13,403 EUR at 746,12 is
     * 100,003 kr.), and "Ryk alle" still finds that open item, books and closes the R1 and makes its R2, as before the port.
     */
    public function test_a_level_1_dunning_in_another_currency_is_booked_and_escalated(): void
    {
        $this->bookableFee();
        $debtor = $this->debtorInEuro();
        self::$fx->openPost($debtor, 800.00, self::FFDAGE1 + 5, self::nextFaktnr());
        $this->runDunning();
        $this->assertSame(['R1'], $this->levelsFor($debtor), 'precondition: an R1 in EUR');
        $r1 = (int)$this->dunningsFor($debtor)[0]['id'];
        self::ageDunning($r1, self::FFDAGE2 + 5);

        $page = $this->runDunning();

        $this->assertSame(['R1', 'R2'], $this->levelsFor($debtor), 'the debtor gets its 2nd reminder');
        $after = self::one('SELECT status, betalt FROM ordrer WHERE id = $1', [$r1]);
        $this->assertSame(['4', 'on'], [(string)$after['status'], trim((string)$after['betalt'])], 'the R1 is booked and closed');
        $this->assertNotNull(self::carriedFee($r1, (int)$this->dunningsFor($debtor)[1]['id']), 'the R2 lists the R1\'s fee open item');
        $this->assertNull(self::notice($page), 'nothing was skipped');
    }

    /**
     * Review GPT-F-1: Bogfør on a reminder with a bookable fee line and a stock-tracked line. The bookable line used to set status 3
     * and the refused line stopped the posting, and that was committed: the reminder showed under Bogførte rykkere without its fee,
     * and a later Bogfør skipped it as booked. Now the refused booking keeps nothing, and once the item is corrected the same Bogfør
     * books it once.
     */
    public function test_a_refused_booking_keeps_nothing_and_the_corrected_reminder_is_booked_once(): void
    {
        $this->bookableFee();
        [, $r1] = $this->debtorWithLevelOneDunning();
        $r1 = (int)$r1['id'];
        $tracked = $this->trackedItemLine($r1, 25.0);
        $before = self::booking($r1);

        $page = $this->bookWithTheOverview($r1);

        $this->assertStringContainsString('lagerf', $page, 'the refusal is shown');
        $after = self::booking($r1);
        $this->assertLessThan(3, $after['status'], 'the reminder stays under Åbne rykkere');
        $this->assertSame($before['accounts'], $after['accounts'], 'no line got an account');
        $this->assertSame([[], 0], [$after['open'], $after['postings']], 'no fee open item and no posting');
        $sections = $this->overviewSections();
        $this->assertTrue(self::lists($sections['open'], $r1), 'listed under Åbne rykkere, where Bogfør is offered');
        $this->assertFalse(self::lists($sections['booked'], $r1), 'not under Bogførte rykkere');

        self::untrack($tracked);
        $page = $this->bookWithTheOverview($r1);

        $this->assertStringNotContainsString('lagerf', $page);
        $booked = self::booking($r1);
        $this->assertSame(4, $booked['status'], 'booked');
        $this->assertSame([125.0], $booked['open'], 'one fee open item with the fee and the corrected line');
        $this->assertSame([125.0, 125.0], [$booked['debet'], $booked['kredit']], 'the postings balance at the fee and the line');

        $this->bookWithTheOverview($r1);
        $this->assertSame($booked, self::booking($r1), 'a second Bogfør posts nothing more');
    }

    /**
     * Review GPT-F-1: a reminder that earlier code left at status 3 without a fee open item or a posting (a refused line beside a
     * bookable one) is not booked. The overview lists it under Åbne rykkere, and Bogfør books it. A reminder at status 3 with its fee
     * open item is booked: it stays under Bogførte rykkere and Bogfør posts nothing more for it.
     */
    public function test_a_reminder_left_at_status_3_without_posting_is_listed_as_open_and_booked_by_bogfoer(): void
    {
        $this->bookableFee();
        [$a, $left] = $this->debtorWithLevelOneDunning();
        $left = (int)$left['id'];
        // As the bookable line left it: status 3 and its account, nothing posted.
        $account = self::one(
            "SELECT g.box4 FROM grupper g JOIN varer v ON cast(g.kodenr AS text) = cast(v.gruppe AS text)
             WHERE g.art = 'VG' AND coalesce(trim(g.box4), '') <> ''
               AND v.id = (SELECT xb FROM formularer WHERE beskrivelse = 'GEBYR' ORDER BY formular LIMIT 1)
             ORDER BY g.fiscal_year DESC LIMIT 1"
        );
        self::rows('UPDATE ordrer SET status = 3 WHERE id = $1', [$left]);
        self::rows('UPDATE ordrelinjer SET bogf_konto = $1 WHERE ordre_id = $2 AND vare_id > 0', [(int)trim((string)$account['box4']), $left]);
        [$b, $done] = $this->debtorWithLevelOneDunning();
        $done = (int)$done['id'];
        $this->bookDunning($b, $done);
        self::rows('UPDATE ordrer SET status = 3 WHERE id = $1', [$done]);   // booked, with its fee open item, at status 3
        $doneBefore = self::booking($done);

        $sections = $this->overviewSections();

        $this->assertTrue(self::lists($sections['open'], $left), 'the unposted reminder is listed under Åbne rykkere');
        $this->assertFalse(self::lists($sections['booked'], $left));
        $this->assertTrue(self::lists($sections['booked'], $done), 'the reminder with its fee open item stays under Bogførte rykkere');
        $this->assertFalse(self::lists($sections['open'], $done));

        $this->bookWithTheOverview($left);
        $this->bookWithTheOverview($done);

        $booked = self::booking($left);
        $this->assertSame(4, $booked['status'], 'the unposted reminder is booked');
        $this->assertSame([100.0], $booked['open'], 'with its fee open item');
        $this->assertSame([100.0, 100.0], [$booked['debet'], $booked['kredit']], 'and balanced postings');
        $this->assertSame($doneBefore, self::booking($done), 'the booked one gets no second fee');
    }

    /**
     * Review GPT-F-1: "Ryk alle" books and escalates an R1 past its grace period that earlier code left at status 3 without posting,
     * as it does an open R1. An R1 at status 3 with its fee open item is booked and is not booked again.
     */
    public function test_ryk_alle_books_and_escalates_a_level_1_dunning_left_at_status_3_without_posting(): void
    {
        $this->bookableFee();
        [$a, $left] = $this->debtorWithAgedLevelOne();
        self::rows('UPDATE ordrer SET status = 3 WHERE id = $1', [$left]);
        [$b, $done] = $this->debtorWithAgedLevelOne();
        $this->bookDunning($b, $done);
        self::rows('UPDATE ordrer SET status = 3 WHERE id = $1', [$done]);
        $doneBefore = self::booking($done);

        $page = $this->runDunning();

        $this->assertSame(['R1', 'R2'], $this->levelsFor($a), 'the unposted R1 gets its 2nd reminder');
        $after = self::one('SELECT status, betalt FROM ordrer WHERE id = $1', [$left]);
        $this->assertSame(['4', 'on'], [(string)$after['status'], trim((string)$after['betalt'])], 'booked and closed');
        $this->assertNotNull(self::carriedFee($left, (int)$this->dunningsFor($a)[1]['id']), 'the R2 lists its fee open item');
        $this->assertSame($doneBefore, self::booking($done), 'the R1 with its fee open item is not booked again');
        $this->assertNull(self::notice($page), 'nothing was skipped');
    }

    /**
     * Review GPT-F-2: "Ryk alle" with a debtor whose booking the database rejects, between two debtors that can be reminded.
     * db_modify() showed the error and ended the request at the rejected statement, so the savepoint of the debtor was never
     * rolled back, the next debtor was never reached and the reminders already made in the same transaction were lost.
     * Now the rejected debtor is rolled back to its savepoint and named with the error reference, and both others are kept.
     */
    public function test_a_debtor_whose_statement_the_database_rejects_does_not_stop_the_others(): void
    {
        $this->bookableFee();
        [$b, $rejected] = $this->debtorWithLevelOneDunning();
        [$c, $valid] = $this->debtorWithLevelOneDunning();
        $rejected = (int)$rejected['id'];
        $valid = (int)$valid['id'];
        self::ageDunning($rejected, self::FFDAGE2 + 5);
        self::ageDunning($valid, self::FFDAGE2 + 5);
        $this->feeWithoutAccount($rejected);
        $a = self::$fx->debtor();   // its first reminder is made first in the run, B's chain next and C's chain last
        self::$fx->openPost($a, 500.00, self::FFDAGE1 + 5, self::nextFaktnr());
        $rejectedBefore = self::booking($rejected);
        $rejectedNr = (string)self::one('SELECT fakturanr FROM ordrer WHERE id = $1', [$rejected])['fakturanr'];

        $res = self::runChild('run_ny_rykker.php', [CharacterizationEnv::sessionId(), CharacterizationEnv::testDb()]);

        $this->assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], "the page runs to its end:\n" . $res['stdout']);
        $this->assertSame(['R1'], $this->levelsFor($a), 'the debtor before it gets its 1st reminder');
        $this->assertSame(['R1', 'R2'], $this->levelsFor($c), 'the debtor after it is booked and escalated');
        $after = self::one('SELECT status, betalt FROM ordrer WHERE id = $1', [$valid]);
        $this->assertSame(['4', 'on'], [(string)$after['status'], trim((string)$after['betalt'])]);
        $this->assertSame(['R1'], $this->levelsFor($b), 'no 2nd reminder for the rejected chain');
        $this->assertSame($rejectedBefore, self::booking($rejected), 'nothing of its booking is kept: status, accounts, open item, postings');
        $this->assertNotSame('on', trim((string)self::one('SELECT betalt FROM ordrer WHERE id = $1', [$rejected])['betalt']), 'and it is not closed');
        $notice = (string)self::notice($res['stdout']);
        $this->assertStringContainsString("Ingen ny rykker for $rejectedNr: databasen afviste en ændring", $notice, 'the notice names the rejected chain');
        $this->assertMatchesRegularExpression('/referencen F-\d{8}-[A-Z0-9]{6}/', $notice, 'with the error reference of the log and the error mail');
    }

    /** Review R1: the same after "Bogfør" on the overview (rapportfunc.php's copy) and "Ny rykker". */
    public function test_a_new_reminder_on_a_booked_dunning_in_another_currency_carries_its_fee(): void
    {
        $this->bookableFee();
        $debtor = $this->debtorInEuro();
        self::$fx->openPost($debtor, 800.00, self::FFDAGE1 + 5, self::nextFaktnr());
        $this->runDunning();
        $r1 = (int)$this->dunningsFor($debtor)[0]['id'];
        self::ageDunning($r1, self::FFDAGE2 + 5);
        $this->bookWithTheOverview($r1);
        $this->assertSame('4', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$r1])['status'], 'precondition: the R1 is booked');

        $page = $this->newReminder($r1);

        $this->assertSame(['R1', 'R2'], $this->levelsFor($debtor), 'the debtor gets its 2nd reminder');
        $this->assertNotNull(self::carriedFee($r1, (int)$this->dunningsFor($debtor)[1]['id']), 'the R2 lists the R1\'s fee open item');
        $this->assertNull(self::notice($page), 'nothing was skipped');
    }

    /** @return string The reminder page (debitor/rykker.php) for the dunning, as the overview's link opens it. */
    private function showReminder(int $dunningId): string
    {
        return self::runChild('run_reminder_page.php', [CharacterizationEnv::sessionId(), 'rykker.php', json_encode(['rykker_id' => (string)$dunningId])])['stdout'];
    }

    /**
     * Review CL2-1: no fee item for the 1st reminder, a fee item for the 2nd. The R1 has no fee line and its sum stays NULL, until
     * it is shown in the reminder page, which writes the sum of its lines, '0.000'. Booked, it has no fee open item, as there
     * is no fee. Its 2nd reminder is made by Ny rykker after Bogfør, and by "Ryk alle".
     */
    public function test_a_level_1_dunning_without_a_fee_shown_in_the_reminder_page_is_escalated(): void
    {
        $this->bookableFee();
        self::rows("UPDATE formularer SET xb = 0 WHERE beskrivelse = 'GEBYR' AND formular = 6");   // no fee on the 1st reminder
        $byNewReminder = self::$fx->debtor();
        self::$fx->openPost($byNewReminder, 600.00, self::FFDAGE1 + 5, self::nextFaktnr());
        $byRykAlle = self::$fx->debtor();
        self::$fx->openPost($byRykAlle, 700.00, self::FFDAGE1 + 5, self::nextFaktnr());
        $this->runDunning();
        $first = (int)$this->dunningsFor($byNewReminder)[0]['id'];
        $second = (int)$this->dunningsFor($byRykAlle)[0]['id'];
        foreach ([$first, $second] as $id) {
            $this->assertSame([], self::rows('SELECT id FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$id]), 'precondition: no fee line');
            $this->assertStringContainsString('<form name="rykker" action="rykker.php?rykker_id=' . $id . '"', $this->showReminder($id), 'the reminder page is shown');
            $this->assertSame('0.000', (string)self::one('SELECT sum FROM ordrer WHERE id = $1', [$id])['sum'], 'the sum the reminder page writes');
            self::ageDunning($id, self::FFDAGE2 + 5);
        }

        // Bogfør, then Ny rykker.
        $this->bookWithTheOverview($first);
        $this->assertSame(['status' => 4, 'open' => [], 'postings' => 0], array_intersect_key(self::booking($first), ['status' => 1, 'open' => 1, 'postings' => 1]),
            'booked without a fee open item, as there is no fee');
        $page = $this->newReminder($first);
        $this->assertSame(['R1', 'R2'], $this->levelsFor($byNewReminder), 'Ny rykker makes the 2nd reminder');
        $this->assertStringNotContainsString('skal bogføres', (string)self::notice($page));

        // "Ryk alle" books the other one and makes its 2nd reminder.
        $page = $this->runDunning();
        $this->assertSame(['R1', 'R2'], $this->levelsFor($byRykAlle), '"Ryk alle" makes the 2nd reminder');
        $after = self::one('SELECT status, betalt FROM ordrer WHERE id = $1', [$second]);
        $this->assertSame(['4', 'on'], [(string)$after['status'], trim((string)$after['betalt'])], 'booked and closed');
        $this->assertSame([], self::booking($second)['open'], 'no fee open item');
        $this->assertNull(self::notice($page), 'nothing was skipped');
    }

    /** @return list<string> The messages of the page's alert() calls, decoded. */
    private static function alerts(string $page): array
    {
        preg_match_all('/alert\(("(?:[^"\\\\]|\\\\.)*")\)/', $page, $m);
        return array_map(static fn(string $js): string => (string)json_decode($js), $m[1]);
    }

    /**
     * Review AR2-2: Bogfør on Åbne rykkere and Ny rykker on an R1 the automatic flow tracks and has not sent. A draft (approval,
     * queued) may be approved or deleted. One whose sending failed or is uncertain (failed, sending), or that is paused with an
     * unresolved sending attempt (held), may have reached the customer: the refusal points to Automatiske rykkere and does not
     * offer to delete it. Review AR3-1: one paused before any attempt (held) may be approved there or deleted.
     */
    public function test_an_automatic_reminder_that_is_paused_or_whose_sending_failed_is_not_offered_to_be_deleted(): void
    {
        self::rows("CREATE TABLE IF NOT EXISTS reminder_automation_jobs (
            order_id integer PRIMARY KEY, previous_id integer, mode varchar(12) NOT NULL,
            state varchar(12) NOT NULL, created_at timestamptz NOT NULL DEFAULT current_timestamp,
            attempted_at timestamptz, sent_at timestamptz, sent_date date,
            channel varchar(12) NOT NULL DEFAULT '', error text NOT NULL DEFAULT '', actor text NOT NULL DEFAULT '')");
        self::rows('DELETE FROM reminder_automation_jobs');
        $created = self::reminderTables(['reminder_automation_events']);
        try {
            $this->bookableFee();
            [, $r1] = $this->debtorWithAgedLevelOne();
            $nr = (string)self::one('SELECT fakturanr FROM ordrer WHERE id = $1', [$r1])['fakturanr'];
            self::rows("INSERT INTO reminder_automation_jobs (order_id, mode, state, actor) VALUES ($1, 'approval', 'approval', 'reminder_due')", [$r1]);
            $checked = 'den automatiske rykker er ikke registreret som sendt. Den er sat på pause, eller dens afsendelse fejlede eller er uafklaret. '
                . 'Se den under Automatiske rykkere: vælg Registrér som sendt, hvis mailserverens log viser, at den blev sendt, ellers Godkend den.';
            $draft = 'den automatiske rykker er ikke sendt endnu. Godkend den i rykkerflowet, eller slet den.';
            $paused = 'den automatiske rykker er sat på pause og ikke sendt. Godkend den under Automatiske rykkere, eller slet den.';

            // [state, a sending attempt logged before (and not approved since), the refusal]
            foreach ([['approval', false, $draft], ['queued', false, $draft], ['held', false, $paused],
                ['held', true, $checked], ['failed', true, $checked], ['sending', true, $checked]] as [$state, $attempt, $reason]) {
                if ($attempt && !self::one("SELECT id FROM reminder_automation_events WHERE order_id = $1 AND action = 'sending'", [$r1])) {
                    self::rows("INSERT INTO reminder_automation_events (order_id, action, actor) VALUES ($1, 'sending', 'reminder_due')", [$r1]);
                }
                self::rows('UPDATE reminder_automation_jobs SET state = $1 WHERE order_id = $2', [$state, $r1]);
                $case = $state . ($attempt ? ' after an attempt' : '');
                $this->assertContains("Rykker $nr: $reason", self::alerts($this->bookWithTheOverview($r1)), "Bogfør, $case");
                $this->assertSame("Ingen ny rykker for $nr: $reason", self::notice($this->newReminder($r1)), "Ny rykker, $case");
                $this->assertSame(2, self::booking($r1)['status'], "$case: not booked");
            }
        } finally {
            self::dropTables($created);
        }
    }

    /** DDL of the tables that record how a reminder went out, as includes/opdat_func installs them (PostgreSQL). */
    private const REMINDER_TABLES = [
        'reminder_dispatch' => "CREATE TABLE reminder_dispatch (order_id integer NOT NULL PRIMARY KEY, sent_date date NOT NULL, channel varchar(12) NOT NULL DEFAULT '')",
        'reminder_automation_jobs' => "CREATE TABLE reminder_automation_jobs (order_id integer PRIMARY KEY, previous_id integer, mode varchar(12) NOT NULL,
            state varchar(12) NOT NULL, created_at timestamptz NOT NULL DEFAULT current_timestamp, attempted_at timestamptz, sent_at timestamptz, sent_date date,
            channel varchar(12) NOT NULL DEFAULT '', error text NOT NULL DEFAULT '', actor text NOT NULL DEFAULT '')",
        'reminder_automation_events' => "CREATE TABLE reminder_automation_events (id bigserial PRIMARY KEY, order_id integer, action varchar(30) NOT NULL,
            actor text NOT NULL, detail text NOT NULL DEFAULT '', created_at timestamptz NOT NULL DEFAULT current_timestamp)",
        'stripe_reminders' => "CREATE TABLE stripe_reminders (rykker_id integer PRIMARY KEY, konto_id integer NOT NULL, ordrenr integer NOT NULL, level smallint NOT NULL,
            status varchar(10) NOT NULL DEFAULT 'draft', fee_mode varchar(8) NOT NULL DEFAULT 'next', channel varchar(8), mailed_at timestamp NULL, mailed_to text,
            fee_ore integer NOT NULL DEFAULT 0, interest_ore integer NOT NULL DEFAULT 0, fee_ordre_id integer, waived_reason varchar(20), created_at timestamp DEFAULT CURRENT_TIMESTAMP)",
    ];

    /**
     * Makes the named tables of REMINDER_TABLES that the test database lacks and empties the others.
     *
     * @param list<string> $names
     * @return list<string> The tables it made, for dropTables().
     */
    private static function reminderTables(array $names): array
    {
        $created = [];
        foreach ($names as $table) {
            if (!self::one('SELECT tablename FROM pg_tables WHERE tablename = $1', [$table])) {
                self::rows(self::REMINDER_TABLES[$table]);
                $created[] = $table;
            } else {
                self::rows("DELETE FROM $table");
            }
        }
        return $created;
    }

    /** @param list<string> $tables Tables reminderTables() made. */
    private static function dropTables(array $tables): void
    {
        foreach ($tables as $table) {
            self::rows("DROP TABLE IF EXISTS $table");
        }
    }

    /**
     * Review AR2-1: "Ryk alle" first deletes the open reminders of customers whose open items net to 0 (it settles them) or who
     * have none left. It deleted a reminder the automatic flow had sent too, and the flow's job then pointed at nothing.
     * Now a reminder that the automatic flow or a link reminder tracks as sent, or whose sending is unresolved, stays with a
     * notice, and the run does not book it (whether it is closed, and its fee, is owner question Q26). Review R3E2E-1: a classic
     * reminder that was only printed (a reminder_dispatch row) is deleted with that row, as before; so is a draft, with its job.
     */
    public function test_ryk_alle_deletes_only_reminders_that_never_went_out_when_the_customer_has_no_open_items(): void
    {
        $this->bookableFee();
        // Six customers with an overdue invoice get their R1 in one run; then each R1 is dated past ffdage2, so the run would
        // book and escalate it, and the customer's open items net to 0 (a payment not yet settled) or are all settled.
        $debtors = [];
        foreach (['printed', 'autoSent', 'uncertain', 'linked', 'autoDraft', 'draft'] as $case) {
            $debtors[$case] = self::$fx->debtor();
            self::$fx->openPost($debtors[$case], 810.00, self::FFDAGE1 + 5, self::nextFaktnr());
        }
        // A last customer with an open item that is not overdue: the run's netting loop decides on an account when it reaches the
        // next one, so the last account with open items is never settled (as before this change).
        self::$fx->openPost(self::$fx->debtor(), 500.00, 0, self::nextFaktnr());
        $this->runDunning();
        // The tables that record how a reminder went out, made after the R1s, so they are made as without them.
        $created = self::reminderTables(array_keys(self::REMINDER_TABLES));
        try {
            $r1 = $nr = [];
            foreach ($debtors as $case => $debtor) {
                $r1[$case] = (int)$this->dunningsFor($debtor)[0]['id'];
                $nr[$case] = (string)self::one('SELECT fakturanr FROM ordrer WHERE id = $1', [$r1[$case]])['fakturanr'];
                self::ageDunning($r1[$case], self::FFDAGE2 + 5);
                if (in_array($case, ['printed', 'draft'], true)) {
                    self::$fx->openPost($debtor, -810.00, 0, self::nextFaktnr());
                } else {
                    self::rows("UPDATE openpost SET udlignet = '1' WHERE konto_id = $1", [$debtor['id']]);
                }
            }
            [$printed, $autoSent, $uncertain, $linked, $autoDraft, $draft] = array_values($r1);
            [$printedNr, $autoSentNr, $uncertainNr, $linkedNr, $autoDraftNr, $draftNr] = array_values($nr);
            $linkDebtor = $debtors['linked'];
            self::rows("INSERT INTO reminder_dispatch (order_id, sent_date, channel) VALUES ($1, current_date - 15, 'print')", [$printed]);
            self::rows("INSERT INTO reminder_dispatch (order_id, sent_date, channel) VALUES ($1, current_date - 15, 'classic')", [$autoSent]);
            self::rows("INSERT INTO reminder_automation_jobs (order_id, mode, state, actor, sent_date) VALUES ($1, 'automatic', 'sent', 'reminder_due', current_date - 15)", [$autoSent]);
            self::rows("INSERT INTO reminder_automation_jobs (order_id, mode, state, actor, attempted_at) VALUES ($1, 'automatic', 'held', 'test', current_timestamp - interval '1 day')", [$uncertain]);
            self::rows("INSERT INTO reminder_automation_events (order_id, action, actor) VALUES ($1, 'sending', 'reminder_due')", [$uncertain]);
            self::rows("INSERT INTO stripe_reminders (rykker_id, konto_id, ordrenr, level, status, fee_mode, channel, mailed_at)
                SELECT id, konto_id, ordrenr, 1, 'sent', 'classic', 'link', now() - interval '15 days' FROM ordrer WHERE id = $1", [$linked]);
            self::rows("INSERT INTO reminder_automation_jobs (order_id, mode, state, actor) VALUES ($1, 'approval', 'approval', 'reminder_due')", [$autoDraft]);

            $page = $this->runDunning();

            $notice = (string)self::notice($page);
            foreach (['sent by the automatic flow' => [$autoSent, $autoSentNr], 'sending unresolved' => [$uncertain, $uncertainNr],
                'mailed as a link reminder' => [$linked, $linkedNr]] as $case => [$id, $nr]) {
                $this->assertSame(['status' => 2, 'open' => [], 'postings' => 0], array_intersect_key(self::booking($id), ['status' => 1, 'open' => 1, 'postings' => 1]),
                    "$case: it stays, not booked");
                $this->assertStringContainsString("Rykker $nr er ikke slettet, selvom kunden ikke har flere åbne poster, da den er sendt til kunden eller dens afsendelse er uafklaret.", $notice, $case);
                $this->assertSame(1, (int)self::one("SELECT count(*) AS n FROM ordrer WHERE id = $1 AND art = 'R1'", [$id])['n'], $case);
            }
            $this->assertNotNull(self::one('SELECT order_id FROM reminder_automation_jobs WHERE order_id = $1', [$autoSent]), 'the sent job stays with its reminder');
            $this->assertNotNull(self::one('SELECT order_id FROM reminder_dispatch WHERE order_id = $1', [$autoSent]), 'and so does its sending day');
            $this->assertNotNull(self::one('SELECT rykker_id FROM stripe_reminders WHERE rykker_id = $1', [$linked]));
            foreach (['a printed classic reminder' => [$printed, $printedNr], 'an automatic draft' => [$autoDraft, $autoDraftNr], 'a draft' => [$draft, $draftNr]] as $case => [$id, $nr]) {
                $this->assertNull(self::one('SELECT id FROM ordrer WHERE id = $1', [$id]), "$case is deleted");
                $this->assertSame([], self::rows('SELECT id FROM ordrelinjer WHERE ordre_id = $1', [$id]), "$case: with its lines");
                $this->assertStringNotContainsString("Rykker $nr ", $notice, $case);
            }
            $this->assertNull(self::one('SELECT order_id FROM reminder_dispatch WHERE order_id = $1', [$printed]), 'the printed one goes with its sending day');
            $this->assertNull(self::one('SELECT order_id FROM reminder_automation_jobs WHERE order_id = $1', [$autoDraft]), 'the draft\'s job goes with it');
            $event = self::one("SELECT actor, detail FROM reminder_automation_events WHERE order_id = $1 AND action = 'deleted'", [$autoDraft]);
            $this->assertSame('Ryk alle: kunden har ingen åbne poster.', $event['detail'] ?? null, 'and the deletion is logged');
            $this->assertSame(['R1'], $this->levelsFor($linkDebtor), 'no 2nd reminder for a kept one');
        } finally {
            self::dropTables($created);
        }
    }

    /**
     * Review R3E2E-1: in a classic ledger, "Ryk alle" makes an R1, staff press Udskriv, and the customer pays. The next "Ryk alle"
     * deletes the unbooked R1, as before the update, also now that printing and mailing record the day it went out
     * (reminder_dispatch). That row goes with it, and the run's message does not ask to handle it manually.
     */
    public function test_ryk_alle_deletes_a_classic_reminder_that_was_printed_or_mailed_once_the_customer_has_paid(): void
    {
        $paper = self::$fx->debtor();
        $mailed = self::$fx->debtor();
        foreach ([$paper, $mailed] as $debtor) {
            self::$fx->openPost($debtor, 810.00, self::FFDAGE1 + 5, self::nextFaktnr());
        }
        // A last customer with an open item that is not overdue, so the netting loop also reaches the customers above.
        self::$fx->openPost(self::$fx->debtor(), 500.00, 0, self::nextFaktnr());
        $this->runDunning();
        $created = self::reminderTables(['reminder_dispatch']);
        try {
            $onPaper = (int)$this->dunningsFor($paper)[0]['id'];
            $byMail = (int)$this->dunningsFor($mailed)[0]['id'];
            self::rows("UPDATE ordrer SET mail_fakt = '' WHERE id = $1", [$onPaper]);
            self::rows("UPDATE ordrer SET mail_fakt = 'on', email = (SELECT a.email FROM adresser a WHERE a.id = ordrer.konto_id) WHERE id = $1", [$byMail]);

            // Udskriv under Åbne rykkere: one on paper, one by mail (support/run_rykkerprint.php records the mail).
            $run = $this->runChildJson('run_rykkerprint.php', [CharacterizationEnv::testDb(), "$onPaper;$byMail", '', '0']);
            $this->assertSame([$byMail], array_column($run['mails'], 'ordre_id'), $run['printed']);
            $channels = self::rows('SELECT order_id, channel FROM reminder_dispatch WHERE order_id IN ($1, $2) ORDER BY order_id', [$onPaper, $byMail]);
            $this->assertSame([[(string)$onPaper, 'print'], [(string)$byMail, 'classic']],
                array_map(static fn(array $r): array => [(string)$r['order_id'], $r['channel']], $channels), 'precondition: both went out');

            // The customers pay: one by a payment that nets the invoice to 0 (not settled yet), the other is settled.
            self::$fx->openPost($paper, -810.00, 0, self::nextFaktnr());
            self::rows("UPDATE openpost SET udlignet = '1' WHERE konto_id = $1", [$mailed['id']]);

            $page = $this->runDunning();

            foreach (['printed on paper' => $onPaper, 'mailed' => $byMail] as $case => $id) {
                $this->assertNull(self::one('SELECT id FROM ordrer WHERE id = $1', [$id]), "$case: deleted");
                $this->assertSame([], self::rows('SELECT id FROM ordrelinjer WHERE ordre_id = $1', [$id]), "$case: with its lines");
                $this->assertNull(self::one('SELECT order_id FROM reminder_dispatch WHERE order_id = $1', [$id]), "$case: and its sending day");
            }
            $this->assertStringNotContainsString('er ikke slettet', (string)self::notice($page));
            $this->assertSame([], $this->dunningsFor($paper), 'no reminder is left for the customer who paid');
        } finally {
            self::dropTables($created);
        }
    }
}
