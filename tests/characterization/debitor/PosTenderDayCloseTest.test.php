<?php
// tests/characterization/debitor/PosTenderDayCloseTest.test.php
//
// POS sales paid with several tenders, and the day close (approved cash count) that posts them.
// Runs the real bogfor_nu() (includes/ordrefunc.php), posbogfor() (debitor/pos_ordre.php) and
// findBoxSale() against a fresh test tenant, in a child process (support/run_pos_day_close.php).
// The sales are written as afslut()/settlePOS.php leave them for posting: status 3, the payment
// rows in pos_betalinger, the last tender in felt_1 and, for an account customer, the customer's
// balance in felt_3.
//
// POS-F1: the Konto part of a mixed tender is the customer's receivable (debtor account + open post,
//         or the DIV box10 account for a customer on Kreditkort terms), not till cash.
// POS-F1b: the day close posts an order paid Konto + another tender once, whatever the tender's name.
// POS-F10: an unposted sale dated before the active fiscal year is posted in its own fiscal year,
//          the following year's opening balances carry it, and the count expects its cash; when that
//          year is closed for posting, or a status account of it has no account in the active year's chart, the
//          count is refused and nothing is posted. The count is also refused when the close posts such a sale, or its
//          commission, on an account that is not in that year's chart (a sales or commission account of the active year only).
//          Without a Sideskift account the result is carried onto 6940, the status account after 6935 Årets resultat,
//          by the change only. Where the result has no account at all, the sale is posted, the opening is left as it
//          was and the close shows a warning for the bookkeeper (owner's decision 19 Nov 2026).
// A sale dated in a locked VAT period (moms_periode_luk) refuses the count, also in the active year.
// With POS box9 (posted at the sale) the count expects the cash of an account sale the close posts.
// A sale dated in a deleted fiscal year or in no fiscal year is neither counted nor posted; the close goes through and
// lists it for the bookkeeper (owner's decision 18 Nov 2026). The commission of a sale from an earlier fiscal year is
// moved to the commission account in that year, dated as the sale. Euro cash taken on such sales is counted as euro
// takings, and the close moves it from the kroner till account to the euro till account ('Kassesalg EUR').
// The commission reclass balances and keeps the VAT charged on the sale, also when the commission account has no VAT code
// or another rate.
// p1-pos-close-R1: the opening-balance carry of a close and of a journal posted into last year, or of a new fiscal year, in
// another session no longer overwrite each other. A close that posts into an earlier year takes the carry's lock before its
// transaction, so it waits for another session's carry before its snapshot; a journal's carry waits for the close's lock and
// then carries from what the close committed. A carry waiting for the lock does not deadlock with an invoice recalculating
// the active year, and one that reads from a snapshot and had to wait is refused. A journal, which does not catch the
// carry's conflict, is rolled back with a Danish message to post again: after that wait, and when it is the session
// PostgreSQL ends in a deadlock with a close that holds the lock. A new fiscal year whose lock is refused saves nothing and
// says so. A posting into the active year that commits while a close carries a sale from an earlier year, on a row the
// carry writes, rolls the close back with the message to approve the count again, not the database error box and its mail.
// p1-pos-close-F4: an earlier year's till account that the carry does not take onto the till's account in the active year
// lets the close through with a warning naming the year, both accounts and what to correct.
// p1-pos-close-N1: a close that waits for the real journal page (finans/bogfor.php) posting into last year gets the carry's
// lock only after the page has recalculated last year for the last time, and then posts on top of the journal.
//
// History:
// 20261117 CL/ASR Created.
// 20261118 CL/ASR A sale without a fiscal year is not counted, not posted and listed in a warning; commission is moved in the year of its sale.
//                 Euro cash on a box9 account sale and on a sale from before the year switch: the count and the close agree.
//                 Fifteen tills: tills 11 to 15 are used by the new cases, till 14 with a till account of its own.
// 20261118 CL/ASR An account the close posts on in an earlier fiscal year must be in that year's chart: a commission account and a sales account of the active year only.
//                 Seventeen tills: tills 16 and 17 are used by these cases.
// 20261118 CL/ASR The commission reclass balances: commission account without a VAT code, with another rate, and the margin case.
//                 Twenty tills: tills 18 to 20 are used by these cases.
// 20261119 CL/ASR A chart without a Sideskift account no longer refuses the count: the opening moves by the change onto 6940.
//                 Without any account for the result, or with a Sideskift account whose Overfør til is not in the active year's chart, the close goes through with a warning.
//                 Twenty-two tills: tills 8, 21 and 22 with till accounts of their own.
// 20261119 CL/ASR Two sessions' carries (p1-pos-close-R1), a till account that changed at the year switch (p1-pos-close-F4), and the refusal naming the status account where the carry stopped.
//                 Twenty-five tills: tills 23, 24 and 25 with till accounts of their own, till 23 another one last year.
//                 The journals post as finans/bogfor.php does (also kontoplan.saldo); cases with a new fiscal year, an invoice's genberegn(), a carry in REPEATABLE READ and a sale from last year that comes during the close.
//                 A journal whose carry conflicts ends with the carry's message, as finans/bogfor.php has no handler: after a wait in REPEATABLE READ and as the victim of a deadlock with a close; a new year whose lock is refused too.
//                 A journal into the active year that commits while a close carries a sale from last year: the close asks the clerk to approve again, without the database error box or a mail (review round 3).
//                 The two-session children write a mail to a file of their own instead of the mail program.
// 20261120 CL/ASR A close waiting for the real journal page into last year gets the lock only after the page's recalculations of that year (p1-pos-close-N1).

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

final class PosTenderDayCloseTest extends TestCase
{
    private const RUNNER = __DIR__ . '/../support/run_pos_day_close.php';
    private const TILL = 58101;
    private const OTHER_CARDS = 58102;
    private const DANKORT = 58103;
    private const DIFFERENCE = 58104;
    private const SAFE = 58105;
    private const CARD_SALES = 58106;
    private const TILL_4 = 58107;
    private const TILL_7 = 58108;
    private const TILL_14 = 58109;
    private const TILL_EUR = 58133;
    private const TILL_8 = 58138;
    private const TILL_21 = 58139;
    private const TILL_22 = 58140;
    private const TILL_23 = 58141;
    /** Till 23's account last year (p1-pos-close-F4); a status account in every year. */
    private const TILL_23_LAST_YEAR = 58142;
    private const TILL_24 = 58143;
    private const TILL_25 = 58144;
    private const SALES = 58110;
    private const ACTIVE_YEAR_SALES = 58136;
    private const ACTIVE_YEAR_COMMISSION = 58137;
    private const RESULT = 58119;
    private const RETAINED = 58120;
    private const COMMISSION = 58132;
    private const COMMISSION_VAT = 58134;
    private const MARGIN_SALES = 58135;
    /** The standard chart's (importfiler/kontoplan.txt) 6935 Årets resultat (R) and 6940 Overført resultat (S), in every year. */
    private const RESULT_LINE = 6935;
    private const RESULT_DESTINATION = 6940;
    /** The alert a journal (finans/bogfor.php, no handler of its own) ends with when its carry conflicts (openingCarryConflictExit()). */
    private const JOURNAL_CONFLICT_ALERT = 'Bogføringen er ikke gennemført, og intet er gemt: primo i de følgende regnskabsår kunne ikke opdateres sikkert, '
        . 'fordi en anden bruger samtidig bogførte i et tidligere regnskabsår, godkendte en kasseoptælling eller gemte en åbningsbalance. Bogfør igen.';
    /** The alert the year card and Opret end with when the database refuses the carry's lock before their transaction. */
    private const SAVE_CONFLICT_ALERT = 'Intet er gemt: primo i de følgende regnskabsår kunne ikke låses mod samtidige opdateringer fra andre brugere. Prøv igen.';

    /** @var resource|\PgSql\Connection */
    private static $tenant;
    private static int $year;
    private static int $openYear;
    private static int $closedYear;
    private static int $debtorAccount;
    private static int $vatAccount;
    private static int $customerId;
    private static int $cardCustomerId;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant(static::class);
        $t = self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());

        $today = date('Ym');
        foreach (CharacterizationEnv::rows($t, "SELECT kodenr, box1, box2, box3, box4 FROM grupper WHERE art = 'RA'") as $ra) {
            if (sprintf('%04d%02d', $ra['box2'], $ra['box1']) <= $today && sprintf('%04d%02d', $ra['box4'], $ra['box3']) >= $today) {
                self::$year = (int)$ra['kodenr'];
            }
        }
        // Fiscal years are numbered in the order they are created, and updateFollowingOpeningBalances() walks
        // kodenr + 1: the year before last (closed for posting) and last year (open) get the numbers below the
        // active year, which is moved up to make room.
        $max = (int)CharacterizationEnv::one($t, "SELECT max(cast(kodenr AS integer)) AS k FROM grupper WHERE art = 'RA'")['k'];
        $installed = self::$year;
        self::$closedYear = $max + 1;
        self::$openYear = $max + 2;
        self::$year = $max + 3;
        $y = self::$year;
        CharacterizationEnv::rows($t, "UPDATE grupper SET kodenr = $y WHERE art = 'RA' AND kodenr = $installed");
        CharacterizationEnv::rows($t, "UPDATE grupper SET fiscal_year = $y WHERE fiscal_year = $installed");
        CharacterizationEnv::rows($t, "UPDATE kontoplan SET regnskabsaar = $y WHERE regnskabsaar = $installed");
        CharacterizationEnv::rows($t, "UPDATE brugere SET regnskabsaar = $y WHERE regnskabsaar = $installed");
        $dg = CharacterizationEnv::one($t, "SELECT kodenr, box2 FROM grupper WHERE art = 'DG' AND fiscal_year = $y ORDER BY kodenr LIMIT 1");
        self::$debtorAccount = (int)$dg['box2'];
        $sm = CharacterizationEnv::one($t, "SELECT kodenr, box1 FROM grupper WHERE art = 'SM' AND fiscal_year = $y AND box2 = '25' ORDER BY kodenr LIMIT 1");
        self::$vatAccount = (int)$sm['box1'];
        self::assertGreaterThan(0, self::$debtorAccount, 'the installed debtor group has a collective account');
        self::assertGreaterThan(0, self::$vatAccount, 'the installed 25 % VAT code has an account');

        $tills = 25;
        $list = static fn($v): string => implode(chr(9), array_fill(0, $tills, (string)$v));
        // Tills 4, 7, 8, 14 and 21 to 25 have till accounts of their own, so their counts see only their own postings.
        $tillAccounts = array_fill(0, $tills, (string)self::TILL);
        $tillAccounts[3] = (string)self::TILL_4;
        $tillAccounts[6] = (string)self::TILL_7;
        $tillAccounts[7] = (string)self::TILL_8;
        $tillAccounts[13] = (string)self::TILL_14;
        $tillAccounts[20] = (string)self::TILL_21;
        $tillAccounts[21] = (string)self::TILL_22;
        $tillAccounts[22] = (string)self::TILL_23;
        $tillAccounts[23] = (string)self::TILL_24;
        $tillAccounts[24] = (string)self::TILL_25;
        // POS 1: tills, till account, department, one named card (Dankort), VAT code, box9 '' = posted at the day close.
        CharacterizationEnv::rows($t, "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5, box6, box7, box9, fiscal_year)
            VALUES ('Kasseopsætning', '', 1, 'POS', '$tills', $1, $2, '1', 'Dankort', '" . self::DANKORT . "', $3, '', $y)",
            [implode(chr(9), $tillAccounts), $list(0), $list($sm['kodenr'])]);
        // POS 2: no fixed float, other cards account, safe and difference account per till, no sets.
        CharacterizationEnv::rows($t, "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box6, box8, box9, box12, fiscal_year)
            VALUES ('Kasseopsætning 2', '', 2, 'POS', '', '" . self::OTHER_CARDS . "', $1, $2, '', $y)",
            [$list(self::SAFE), $list(self::DIFFERENCE)]);
        CharacterizationEnv::rows($t, "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, regnskabsaar, primo, saldo) VALUES
            (" . self::TILL . ", 'Kasse', 'S', '', $y, 0, 0), (" . self::OTHER_CARDS . ", 'Andre kort', 'S', '', $y, 0, 0),
            (" . self::DANKORT . ", 'Dankort', 'S', '', $y, 0, 0), (" . self::DIFFERENCE . ", 'Kassedifference', 'S', '', $y, 0, 0),
            (" . self::SAFE . ", 'Pengeskab', 'S', '', $y, 0, 0), (" . self::SALES . ", 'Salg', 'D', 'S{$sm['kodenr']}', $y, 0, 0),
            (" . self::CARD_SALES . ", 'Kreditkortsalg', 'S', '', $y, 0, 0), (" . self::TILL_4 . ", 'Kasse 4', 'S', '', $y, 0, 0),
            (" . self::TILL_7 . ", 'Kasse 7', 'S', '', $y, 0, 0), (" . self::RETAINED . ", 'Overført resultat', 'S', '', $y, 0, 0),
            (" . self::TILL_14 . ", 'Kasse 14', 'S', '', $y, 0, 0), (" . self::TILL_EUR . ", 'Kasse EUR', 'S', '', $y, 0, 0),
            (" . self::TILL_8 . ", 'Kasse 8', 'S', '', $y, 0, 0), (" . self::TILL_21 . ", 'Kasse 21', 'S', '', $y, 0, 0),
            (" . self::TILL_22 . ", 'Kasse 22', 'S', '', $y, 0, 0), (" . self::TILL_23 . ", 'Kasse 23', 'S', '', $y, 0, 0),
            (" . self::TILL_23_LAST_YEAR . ", 'Kasse 23 sidste år', 'S', '', $y, 0, 0), (" . self::TILL_24 . ", 'Kasse 24', 'S', '', $y, 0, 0),
            (" . self::TILL_25 . ", 'Kasse 25', 'S', '', $y, 0, 0)");
        // The result account that carries a year's profit into the next year's equity.
        CharacterizationEnv::rows($t, "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, regnskabsaar, primo, saldo, overfor_til)
            VALUES (" . self::RESULT . ", 'Resultat', 'X', '', $y, 0, 0, " . self::RETAINED . ")");
        CharacterizationEnv::rows($t, "UPDATE kontoplan SET saldo = 0 WHERE regnskabsaar = $y AND saldo IS NULL");
        $customer = CharacterizationEnv::one($t, "INSERT INTO adresser (kontonr, firmanavn, art, gruppe, betalingsbet, betalingsdage)
            VALUES ('91001', 'Testkunde ApS', 'D', $1, 'Netto', 8) RETURNING id", [(int)$dg['kodenr']]);
        self::$customerId = (int)$customer['id'];
        $customer = CharacterizationEnv::one($t, "INSERT INTO adresser (kontonr, firmanavn, art, gruppe, betalingsbet, betalingsdage)
            VALUES ('91002', 'Kortkunde ApS', 'D', $1, 'Kreditkort', 0) RETURNING id", [(int)$dg['kodenr']]);
        self::$cardCustomerId = (int)$customer['id'];

        // Two earlier fiscal years with the same setup: last calendar year open for posting, the year before closed.
        foreach ([self::$openYear => [date('Y') - 1, 'on'], self::$closedYear => [date('Y') - 2, '']] as $k => [$calendarYear, $open]) {
            CharacterizationEnv::rows($t, "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5, fiscal_year)
                VALUES ('$calendarYear', '', $k, 'RA', '01', '$calendarYear', '12', '$calendarYear', '$open', $k)");
            CharacterizationEnv::rows($t, "CREATE TEMP TABLE g AS SELECT * FROM grupper WHERE fiscal_year = $y AND art <> 'RA'");
            CharacterizationEnv::rows($t, "UPDATE g SET id = nextval('grupper_id_seq'), fiscal_year = $k");
            CharacterizationEnv::rows($t, "INSERT INTO grupper SELECT * FROM g");
            CharacterizationEnv::rows($t, "CREATE TEMP TABLE k AS SELECT * FROM kontoplan WHERE regnskabsaar = $y");
            CharacterizationEnv::rows($t, "UPDATE k SET id = nextval('kontoplan_id_seq'), regnskabsaar = $k");
            CharacterizationEnv::rows($t, "INSERT INTO kontoplan SELECT * FROM k");
            CharacterizationEnv::rows($t, "DROP TABLE g, k");
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant) {
            pg_close(self::$tenant);
            self::$tenant = null;
            $temp = CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb();
            if (is_dir($temp)) {
                foreach (glob($temp . '/{,.}[!.]*', GLOB_BRACE) ?: [] as $file) {
                    unlink($file);
                }
                rmdir($temp);
            }
            CharacterizationEnv::teardownTenant();
        }
    }

    /**
     * A POS sale at status 3, as settlePOS.php leaves it for posting.
     *
     * @param array<int, array{0:string, 1:float}> $tenders pos_betalinger rows in the order they were paid
     */
    private function sale(int $till, float $exVat, array $tenders, bool $customer, string $date = '', bool $cardTerms = false): int
    {
        $t = self::$tenant;
        [$customerId, $customerNo, $customerName, $terms, $days] = $cardTerms
            ? [self::$cardCustomerId, '91002', 'Kortkunde ApS', 'Kreditkort', 0]
            : [self::$customerId, '91001', 'Testkunde ApS', 'Netto', 8];
        $date = $date ?: date('Y-m-d');
        $vat = round($exVat * 0.25, 2);
        $last = end($tenders);
        $id = CharacterizationEnv::nextId($t, 'ordrer');
        // felt_3/felt_4 as settlePOS.php writes them: for an account customer the open-post balance (0 here) and balance + total.
        CharacterizationEnv::rows($t, "INSERT INTO ordrer (id, ordrenr, art, status, konto_id, kontonr, firmanavn, betalingsbet, betalingsdage,
                ordredate, levdate, fakturadate, fakturanr, sum, moms, momssats, felt_1, felt_2, felt_3, felt_4, felt_5, valuta, valutakurs, ref)
            VALUES ($1, $1, 'PO', 3, $2, $3, $4, $5, $6, $7, $7, $7, $15, $8, $9, 25, $10, $11, $12, $13, $14, 'DKK', 100, 'chartest')",
            [$id, $customer ? $customerId : 0, $customer ? $customerNo : '', $customer ? $customerName : '',
             $customer ? $terms : 'Kontant', $customer ? $days : 0, $date, $exVat, $vat, $last[0], $last[1],
             $customer ? '0' : '', $customer ? (string)$exVat : '0', (string)$till, (string)$id]);
        CharacterizationEnv::rows($t, "INSERT INTO ordrelinjer (ordre_id, posnr, vare_id, varenr, beskrivelse, antal, pris, rabat, momssats, bogf_konto, vat_account)
            VALUES ($1, 1, 0, 'T1', 'Testvare', 1, $2, 0, 25, " . self::SALES . ", " . self::$vatAccount . ")", [$id, $exVat]);
        foreach ($tenders as [$type, $amount]) {
            CharacterizationEnv::rows($t, "INSERT INTO pos_betalinger (ordre_id, betalingstype, amount, valuta, valutakurs) VALUES ($1, $2, $3, 'DKK', 100)",
                [$id, $type, $amount]);
        }
        return $id;
    }

    /** @return array<string, mixed> */
    private function runner(string ...$args): array
    {
        $res = CharacterizationEnv::runChild(self::RUNNER, array_merge([CharacterizationEnv::testDb()], $args));
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $json = $lines !== [] ? json_decode(end($lines), true) : null;
        self::assertIsArray($json, "runner must emit JSON (stdout: {$res['stdout']} stderr: {$res['stderr']})");
        return $json;
    }

    private function regnstart(): string
    {
        $ra = CharacterizationEnv::one(self::$tenant, "SELECT box1, box2 FROM grupper WHERE art = 'RA' AND kodenr = '" . self::$year . "'");
        return $ra['box2'] . '-' . $ra['box1'] . '-01';
    }

    /**
     * Debit minus credit per account for the given order (or, with $till, for everything posted on that till).
     *
     * @return array<int, float>
     */
    private function postings(?int $orderId, ?int $till = null): array
    {
        $where = $orderId !== null ? 'ordre_id = ' . $orderId : 'kasse_nr = ' . (int)$till;
        $net = [];
        foreach (CharacterizationEnv::rows(self::$tenant, "SELECT kontonr, sum(debet) - sum(kredit) AS net FROM transaktioner
            WHERE $where AND kontonr <> 0 GROUP BY kontonr ORDER BY kontonr") as $row) {
            $net[(int)$row['kontonr']] = round((float)$row['net'], 2);
        }
        return $net;
    }

    /**
     * @param array<int, float> $expected debit minus credit per account
     * @param array<int, float> $actual
     */
    private static function assertPostings(array $expected, array $actual, string $message = ''): void
    {
        ksort($expected);
        ksort($actual);
        self::assertSame($expected, $actual, $message);
    }

    /** @return array<int, float> open post amounts for the order */
    private function openPosts(int $orderId): array
    {
        return array_map(static fn(array $r): float => round((float)$r['amount'], 2), CharacterizationEnv::rows(self::$tenant,
            'SELECT amount FROM openpost WHERE refnr = $1 AND konto_id = $2 ORDER BY id', [$orderId, self::$customerId]));
    }

    private function orderStatus(int $orderId): int
    {
        return (int)CharacterizationEnv::one(self::$tenant, 'SELECT status FROM ordrer WHERE id = $1', [$orderId])['status'];
    }

    private function account(int $kontonr, int $year, string $field): float
    {
        return round((float)CharacterizationEnv::one(self::$tenant, "SELECT $field FROM kontoplan WHERE kontonr = $1 AND regnskabsaar = $2",
            [$kontonr, $year])[$field], 2);
    }

    /** Sets a box of a group for the test and returns the value it had. */
    private function setBox(string $art, int $kodenr, string $box, string $value, ?int $year = null): string
    {
        $where = "art = '$art' AND kodenr = $kodenr" . ($year !== null ? " AND fiscal_year = $year" : '');
        $old = (string)CharacterizationEnv::one(self::$tenant, "SELECT $box FROM grupper WHERE $where")[$box];
        CharacterizationEnv::rows(self::$tenant, "UPDATE grupper SET $box = $1 WHERE $where", [$value]);
        return $old;
    }

    // ------------------------------------------------------------------ POS-F1, posted at the sale

    public function testTheKontoPartOfKontoThenKontantIsTheCustomersReceivable(): void
    {
        $id = $this->sale(1, 200, [['Konto', 100], ['Kontant', 150]], true);

        self::assertSame('OK', $this->runner('post', (string)$id)['svar']);

        self::assertPostings([
            self::$debtorAccount => 100.0,
            self::TILL => 150.0,
            self::SALES => -200.0,
            self::$vatAccount => -50.0,
        ], $this->postings($id), 'Konto 100 on the debtor account, the cash 150 in the till');
        self::assertSame([100.0], $this->openPosts($id), 'the customer owes the Konto part');
    }

    public function testTheKontoPartOfKontoThenACardIsTheCustomersReceivable(): void
    {
        $id = $this->sale(1, 200, [['Konto', 100], ['Visa', 150]], true);

        self::assertSame('OK', $this->runner('post', (string)$id)['svar']);

        self::assertPostings([
            self::$debtorAccount => 100.0,
            self::OTHER_CARDS => 150.0,
            self::SALES => -200.0,
            self::$vatAccount => -50.0,
        ], $this->postings($id));
        self::assertSame([100.0], $this->openPosts($id));
    }

    public function testACardThenKontoChargesTheCustomerOnlyTheKontoPart(): void
    {
        // Konto paid last: the customer was kept before, but the open post was the whole sale and the
        // Konto part was debited to the card account.
        $id = $this->sale(1, 200, [['Dankort', 150], ['Konto', 100]], true);

        self::assertSame('OK', $this->runner('post', (string)$id)['svar']);

        self::assertPostings([
            self::$debtorAccount => 100.0,
            self::DANKORT => 150.0,
            self::SALES => -200.0,
            self::$vatAccount => -50.0,
        ], $this->postings($id));
        self::assertSame([100.0], $this->openPosts($id));
    }

    public function testAKontoPartForACustomerOnKreditkortTermsGoesToTheCardSalesAccount(): void
    {
        // Kreditkort terms with a card-sales account in DIV 3 box10: the customer's Konto part goes to that
        // account without an open post, as a sale only on Konto does. The cash is the till's.
        $old = $this->setBox('DIV', 3, 'box10', (string)self::CARD_SALES);
        try {
            $id = $this->sale(1, 200, [['Konto', 100], ['Kontant', 150]], true, '', true);

            self::assertSame('OK', $this->runner('post', (string)$id)['svar']);

            self::assertPostings([
                self::CARD_SALES => 100.0,
                self::TILL => 150.0,
                self::SALES => -200.0,
                self::$vatAccount => -50.0,
            ], $this->postings($id), 'Konto 100 on the card-sales account, the cash 150 in the till');
            self::assertSame(0, (int)CharacterizationEnv::one(self::$tenant, 'SELECT count(*) AS n FROM openpost WHERE refnr = $1', [$id])['n']);
        } finally {
            $this->setBox('DIV', 3, 'box10', $old);
        }
    }

    public function testASaleOnlyOnKontoIsPostedAsBefore(): void
    {
        $id = $this->sale(1, 200, [['Konto', 250]], true);

        self::assertSame('OK', $this->runner('post', (string)$id)['svar']);

        self::assertPostings([
            self::$debtorAccount => 250.0,
            self::SALES => -200.0,
            self::$vatAccount => -50.0,
        ], $this->postings($id));
        self::assertSame([250.0], $this->openPosts($id));
    }

    // ------------------------------------------------------------------ POS-F1b, posted at the day close

    public function testTheDayClosePostsKontoPlusVisaOnce(): void
    {
        $mixed = $this->sale(2, 200, [['Konto', 100], ['Visa', 150]], true);
        $visa1 = $this->sale(2, 15.96, [['Visa', 19.95]], false);
        $visa2 = $this->sale(2, 15.96, [['Visa', 19.95]], false);

        $count = $this->runner('count', '2', '0');
        self::assertSame(0.0, (float)$count['tilgang']);
        self::assertSame(100.0, (float)$count['kontosum']);

        $close = $this->runner('close', '2', $this->regnstart(), '0');
        self::assertFalse($close['exit'], json_encode($close));
        self::assertTrue($close['posbogfor']);

        self::assertPostings([
            self::$debtorAccount => 100.0,
            self::OTHER_CARDS => 189.9,
            self::SALES => -231.92,
            self::$vatAccount => -57.98,
        ], $this->postings(null, 2), 'the whole close: sales 200 + 2 x 15,96, VAT 50 + 2 x 3,99');
        self::assertPostings([
            self::$debtorAccount => 100.0,
            self::OTHER_CARDS => 150.0,
            self::SALES => -200.0,
            self::$vatAccount => -50.0,
        ], $this->postings($mixed), 'the mixed sale, once, with its receivable');
        self::assertSame([100.0], $this->openPosts($mixed));
        foreach ([$mixed, $visa1, $visa2] as $id) {
            self::assertSame(4, $this->orderStatus($id));
        }
    }

    public function testTheDayCloseIsNotStoppedWhenTheCardListHoldsOnlyTheMixedSale(): void
    {
        // Before: the mixed sale was posted alone in the Visa list with its own ordre_id, and posting it
        // again as an account sale tripped the duplicate guard, so the whole close exited and rolled back.
        $mixed = $this->sale(3, 200, [['Konto', 100], ['Visa', 150]], true);
        $cash = $this->sale(3, 16, [['Kontant', 20]], false);

        $close = $this->runner('close', '3', $this->regnstart(), '0');
        self::assertFalse($close['exit'], json_encode($close));

        self::assertPostings([
            self::$debtorAccount => 100.0,
            self::OTHER_CARDS => 150.0,
            self::SALES => -200.0,
            self::$vatAccount => -50.0,
        ], $this->postings($mixed));
        self::assertPostings([self::TILL => 20.0, self::SALES => -16.0, self::$vatAccount => -4.0], $this->postings($cash));
        self::assertSame(4, $this->orderStatus($mixed));
        self::assertSame(4, $this->orderStatus($cash));
    }

    public function testASaleWithTwoKontoPartsIsPostedOnce(): void
    {
        // Before: the Konto query returned the sale once per Konto row, and the second posting of the same
        // sale tripped the duplicate guard, so the close exited and rolled back.
        $mixed = $this->sale(6, 200, [['Konto', 50], ['Konto', 50], ['Visa', 150]], true);
        $visa = $this->sale(6, 15.96, [['Visa', 19.95]], false);

        $close = $this->runner('close', '6', $this->regnstart(), '0');
        self::assertFalse($close['exit'], json_encode($close));

        self::assertPostings([
            self::$debtorAccount => 100.0,
            self::OTHER_CARDS => 169.95,
            self::SALES => -215.96,
            self::$vatAccount => -53.99,
        ], $this->postings(null, 6), 'the mixed sale once, plus the Visa sale');
        self::assertSame([100.0], $this->openPosts($mixed));
        self::assertSame(4, $this->orderStatus($mixed));
        self::assertSame(4, $this->orderStatus($visa));
    }

    // ------------------------------------------------------------------ POS-F10, sales from before the fiscal year

    public function testASaleFromBeforeTheYearSwitchIsPostedInItsOwnFiscalYear(): void
    {
        $lastYear = (date('Y') - 1) . '-12-31';
        $old = $this->sale(4, 100, [['Kontant', 125]], false, $lastYear);
        $oldKonto = $this->sale(4, 80, [['Konto', 100]], true, $lastYear);
        $new = $this->sale(4, 16, [['Kontant', 20]], false);
        $y = self::$year;
        $oldYearTill = $this->account(self::TILL_4, self::$openYear, 'saldo');
        $oldYearDebtorPrimo = $this->account(self::$debtorAccount, self::$openYear, 'primo');

        $count = $this->runner('count', '4', '145');
        self::assertSame(0.0, (float)$count['byttepenge']);
        self::assertSame(145.0, (float)$count['tilgang'], 'the count expects the cash of both cash sales');
        self::assertSame(100.0, (float)$count['kontosum']);
        self::assertSame(0.0, (float)$count['diff']);
        self::assertSame([$old, $oldKonto, $new], $count['ids']);

        $close = $this->runner('close', '4', $this->regnstart(), '0');
        self::assertFalse($close['exit'], json_encode($close));

        self::assertPostings([self::TILL_4 => 125.0, self::SALES => -100.0, self::$vatAccount => -25.0], $this->postings($old));
        self::assertPostings([self::$debtorAccount => 100.0, self::SALES => -80.0, self::$vatAccount => -20.0], $this->postings($oldKonto));
        self::assertSame([$lastYear], array_column(CharacterizationEnv::rows(self::$tenant,
            'SELECT DISTINCT transdate FROM transaktioner WHERE ordre_id IN ($1, $2)', [$old, $oldKonto]), 'transdate'));
        self::assertSame(125.0, $this->account(self::TILL_4, self::$openYear, 'saldo') - $oldYearTill, 'posted with the fiscal year of its date');
        self::assertSame([100.0], $this->openPosts($oldKonto));
        foreach ([$old, $oldKonto, $new] as $id) {
            self::assertSame(4, $this->orderStatus($id));
        }
        self::assertSame(0, (int)CharacterizationEnv::one(self::$tenant,
            "SELECT count(*) AS n FROM transaktioner WHERE kasse_nr = 4 AND beskrivelse LIKE 'Kassedifference%'")['n']);

        // The active year opens with what last year's sales left: the cash in the till, the customer's debt
        // and last year's profit in equity.
        self::assertSame(125.0, $this->account(self::TILL_4, $y, 'primo'), 'the till opens with last year\'s cash');
        self::assertSame($oldYearDebtorPrimo + 100.0, $this->account(self::$debtorAccount, $y, 'primo'), 'the debtor account opens with the open post');
        self::assertSame(-180.0, $this->account(self::RETAINED, $y, 'primo'), 'last year\'s profit 100 + 80 in equity');

        // The next count of the same cash finds no difference: the float is the till's opening balance plus
        // this year's postings.
        $recount = $this->runner('count', '4', '145');
        self::assertSame(145.0, (float)$recount['byttepenge']);
        self::assertSame(0.0, (float)$recount['tilgang']);
        self::assertSame(0.0, (float)$recount['diff']);
    }

    public function testASaleInAFiscalYearClosedForPostingStopsTheCount(): void
    {
        $old = $this->sale(5, 100, [['Kontant', 125]], false, (date('Y') - 2) . '-06-30');
        $new = $this->sale(5, 16, [['Kontant', 20]], false);

        $close = $this->runner('close', '5', $this->regnstart(), '0');

        self::assertTrue($close['exit'], 'the close stops');
        self::assertStringContainsString('30-06-' . (date('Y') - 2), (string)($close['alerts'][0] ?? ''), json_encode($close));
        self::assertSame([], $this->postings(null, 5), 'nothing is posted');
        self::assertSame(3, $this->orderStatus($old));
        self::assertSame(3, $this->orderStatus($new));
        self::assertSame(0, (int)CharacterizationEnv::one(self::$tenant,
            "SELECT count(*) AS n FROM report WHERE description = 'Cash count, box 5'")['n']);
    }

    /** @return array<int, float> primo per status account of the active year */
    private function activePrimo(): array
    {
        $primo = [];
        foreach (CharacterizationEnv::rows(self::$tenant, "SELECT kontonr, primo FROM kontoplan WHERE regnskabsaar = $1 AND kontotype = 'S'",
            [self::$year]) as $row) {
            $primo[(int)$row['kontonr']] = round((float)$row['primo'], 2);
        }
        ksort($primo);
        return $primo;
    }

    /**
     * The close refused the count: an alert naming the sale date and the reason, nothing posted on the till,
     * the sales still waiting, no report and the active year's opening balances untouched.
     *
     * @param array<string, mixed> $close
     * @param int[] $orders
     * @param array<int, float> $primoBefore
     */
    private function assertCountRefused(array $close, int $till, string $date, string $reason, array $orders, array $primoBefore): void
    {
        self::assertTrue($close['exit'], 'the close stops: ' . json_encode($close));
        $alert = (string)($close['alerts'][0] ?? '');
        self::assertStringContainsString($date, $alert, json_encode($close));
        self::assertStringContainsString($reason, $alert);
        self::assertSame([], $this->postings(null, $till), 'nothing is posted');
        foreach ($orders as $id) {
            self::assertSame(3, $this->orderStatus($id));
        }
        self::assertSame(0, (int)CharacterizationEnv::one(self::$tenant,
            "SELECT count(*) AS n FROM report WHERE description = 'Cash count, box $till'")['n']);
        self::assertSame($primoBefore, $this->activePrimo(), 'the opening balances of the active year are unchanged');
    }

    // ------------------------------------------------------------------ A chart without a Sideskift account (owner's decision 19 Nov 2026)

    /**
     * Last year's chart without its Sideskift (X) account, as the standard chart is: last year's result is carried onto
     * 6940, the first status account after 6935 Årets resultat (R) in the active year's chart, which the installed chart
     * has in every year. The cases above carried last year's result into RETAINED through the Sideskift account; on a
     * standard chart the year's creation puts it on 6940, so the active year's RETAINED opening is moved onto 6940
     * (RETAINED has no postings, so 0 is its closing last year). The opening's sum is unchanged.
     * restoreSideskift() puts the Sideskift account back.
     */
    private function withoutSideskiftLastYear(): void
    {
        $t = self::$tenant;
        $y = self::$year;
        self::assertSame([self::RESULT_LINE => 'R', self::RESULT_DESTINATION => 'S'], array_column(CharacterizationEnv::rows($t,
            'SELECT kontonr, kontotype FROM kontoplan WHERE regnskabsaar = $1 AND kontonr IN ($2, $3) ORDER BY kontonr',
            [$y, self::RESULT_LINE, self::RESULT_DESTINATION]), 'kontotype', 'kontonr'), 'the installed chart has 6935 (R) and 6940 (S)');
        CharacterizationEnv::rows($t, 'DELETE FROM kontoplan WHERE kontonr = $1 AND regnskabsaar = $2', [self::RESULT, self::$openYear]);
        $retained = $this->account(self::RETAINED, $y, 'primo');
        CharacterizationEnv::rows($t, 'UPDATE kontoplan SET primo = coalesce(primo, 0) + $1 WHERE kontonr = $2 AND regnskabsaar = $3',
            [$retained, self::RESULT_DESTINATION, $y]);
        CharacterizationEnv::rows($t, 'UPDATE kontoplan SET primo = 0 WHERE kontonr = $1 AND regnskabsaar = $2', [self::RETAINED, $y]);
    }

    private function restoreSideskift(): void
    {
        $y = self::$openYear;
        CharacterizationEnv::rows(self::$tenant, 'DELETE FROM kontoplan WHERE kontonr = $1 AND regnskabsaar = $2', [self::RESULT, $y]);
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, regnskabsaar, primo, saldo, overfor_til)
            VALUES (" . self::RESULT . ", 'Resultat', 'X', '', $y, 0, 0, " . self::RETAINED . ")");
    }

    /**
     * $primo with the amounts added per account, rounded as activePrimo() rounds.
     *
     * @param array<int, float> $primo
     * @param array<int, float> $change
     * @return array<int, float>
     */
    private static function primoPlus(array $primo, array $change): array
    {
        foreach ($change as $account => $amount) {
            $primo[$account] = round(($primo[$account] ?? 0.0) + $amount, 2);
        }
        return $primo;
    }

    private function yearName(int $year): string
    {
        return (string)CharacterizationEnv::one(self::$tenant, "SELECT beskrivelse FROM grupper WHERE art = 'RA' AND kodenr = '$year'")['beskrivelse'];
    }

    public function testWithoutASideskiftAccountASaleFromBeforeTheYearSwitchMovesTheOpeningByTheChange(): void
    {
        // Before: the count was refused ('31-12-<last year>: resultatet for regnskabsår <last year> kan ikke føres til primo
        // i det følgende regnskabsår, ... Sideskift ...'), nothing was posted and the till could not be closed while the
        // sale waited. Now the sale is posted in last year and the carry moves the active year's opening by the change.
        $lastYear = (date('Y') - 1) . '-12-31';
        $this->withoutSideskiftLastYear();
        try {
            $old = $this->sale(8, 100, [['Kontant', 125]], false, $lastYear);
            $new = $this->sale(8, 16, [['Kontant', 20]], false);
            $primo = $this->activePrimo();
            $oldYearTill = $this->account(self::TILL_8, self::$openYear, 'saldo');

            $count = $this->runner('count', '8', '145');
            self::assertSame(145.0, (float)$count['tilgang'], 'the count expects the cash of both sales');
            self::assertSame(0.0, (float)$count['diff']);

            $close = $this->runner('close', '8', $this->regnstart(), '0');
            self::assertFalse($close['exit'], 'the close goes through: ' . json_encode($close));
            self::assertSame([], $close['alerts'] ?? [], 'no warning');

            self::assertPostings([self::TILL_8 => 125.0, self::SALES => -100.0, self::$vatAccount => -25.0], $this->postings($old));
            self::assertSame([$lastYear], array_column(CharacterizationEnv::rows(self::$tenant,
                'SELECT DISTINCT transdate FROM transaktioner WHERE ordre_id = $1', [$old]), 'transdate'));
            self::assertSame(125.0, $this->account(self::TILL_8, self::$openYear, 'saldo') - $oldYearTill, 'posted with the fiscal year of its date');
            self::assertSame(4, $this->orderStatus($old));
            self::assertSame(4, $this->orderStatus($new));

            // Last year's sale: till D 125, sales C 100, VAT C 25, so last year's profit grows by 100. The active year opens
            // with the till 125 higher and the VAT account 25 lower, and 6940 moves by minus the profit, -100: the opening
            // still sums as before (125 - 25 - 100 = 0). Every other account opens as it did.
            self::assertSame(self::primoPlus($primo, [self::TILL_8 => 125.0, self::$vatAccount => -25.0, self::RESULT_DESTINATION => -100.0]),
                $this->activePrimo());

            // The next count of the same cash finds no difference: the till opens with last year's 125, plus today's 20.
            $recount = $this->runner('count', '8', '145');
            self::assertSame(145.0, (float)$recount['byttepenge']);
            self::assertSame(0.0, (float)$recount['tilgang']);
            self::assertSame(0.0, (float)$recount['diff']);
        } finally {
            $this->restoreSideskift();
        }
    }

    public function testWithoutAnAccountForLastYearsResultASaleFromBeforeTheYearSwitchIsPostedAndListedForTheBookkeeper(): void
    {
        // Neither a Sideskift account last year nor a Resultat (R) account in the active year's chart, so last year's result
        // has no account to be carried to. Before: refused as above. Now the till can be closed: the sale is posted in last
        // year, the active year's opening is left as it was, and the close shows a warning for the bookkeeper.
        $lastYear = (date('Y') - 1) . '-12-31';
        $t = self::$tenant;
        $y = self::$year;
        $this->withoutSideskiftLastYear();
        CharacterizationEnv::rows($t, "UPDATE kontoplan SET kontotype = 'H' WHERE kontonr = $1 AND regnskabsaar = $2", [self::RESULT_LINE, $y]);
        try {
            $old = $this->sale(21, 100, [['Kontant', 125]], false, $lastYear);
            $new = $this->sale(21, 16, [['Kontant', 20]], false);
            $primo = $this->activePrimo();

            $close = $this->runner('close', '21', $this->regnstart(), '0');
            self::assertFalse($close['exit'], 'the close goes through: ' . json_encode($close));
            self::assertTrue($close['posbogfor']);
            self::assertCount(1, $close['alerts'] ?? [], json_encode($close));
            $alert = (string)$close['alerts'][0];
            $last = $this->yearName(self::$openYear);
            $active = $this->yearName($y);
            self::assertStringStartsWith('Kasse 21 har salg fra et tidligere regnskabsår, som er bogført i deres eget regnskabsår, men ikke ført til primo', $alert);
            self::assertStringContainsString("\n31-12-" . (date('Y') - 1) . ": primo i regnskabsår $active er ikke opdateret med salgene, "
                . "fordi resultatet for regnskabsår $last ikke har en konto at blive ført til. Kontoplanen for $active har ingen konto af typen Resultat "
                . 'med en statuskonto efter sig', $alert);
            self::assertStringContainsString("og kontoplanen for $last har ingen konto af typen Sideskift.", $alert);
            self::assertStringContainsString("Opret kontiene i kontoplanen for $active, og tryk derefter Gem/opdatér på åbningsbalancen for regnskabsår $active "
                . '(Indstillinger » Virksomhed » Regnskabsår » Åbningsbalance).', $alert);
            self::assertStringContainsString("Bogføres en sådan difference, skal den tilbageføres, når primo er opdateret, ellers står beløbet to gange på kassekontoen.", $alert);

            self::assertPostings([self::TILL_21 => 125.0, self::SALES => -100.0, self::$vatAccount => -25.0], $this->postings($old));
            self::assertSame([$lastYear], array_column(CharacterizationEnv::rows($t,
                'SELECT DISTINCT transdate FROM transaktioner WHERE ordre_id = $1', [$old]), 'transdate'));
            self::assertSame(4, $this->orderStatus($old));
            self::assertSame(4, $this->orderStatus($new));
            self::assertSame(1, (int)CharacterizationEnv::one($t, "SELECT count(*) AS n FROM report WHERE description = 'Cash count, box 21'")['n']);
            self::assertSame($primo, $this->activePrimo(), 'the active year\'s opening is left as it was');

            // Until the bookkeeper carries the sale, the till opens without last year's 125 (byttepenge 0 + today's 20), so a
            // count of the same 145 shows it as a difference, as the warning says.
            $recount = $this->runner('count', '21', '145');
            self::assertSame(20.0, (float)$recount['byttepenge']);
            self::assertSame(125.0, (float)$recount['diff']);

            // The bookkeeper adds the Resultat account and presses Gem/opdatér on the active year's Åbningsbalance. Without a
            // Sideskift account last year that runs updateFollowingOpeningBalances(last year) in delta mode
            // (systemdata/regnskabskort.php), and the opening moves by the change, as in the case above: till +125,
            // VAT -25, 6940 -100.
            CharacterizationEnv::rows($t, "UPDATE kontoplan SET kontotype = 'R' WHERE kontonr = $1 AND regnskabsaar = $2", [self::RESULT_LINE, $y]);
            $carry = $this->runner('carry', (string)self::$openYear);
            self::assertSame([$y], $carry['updated'], json_encode($carry));
            self::assertNull($carry['stop']);
            self::assertSame(self::primoPlus($primo, [self::TILL_21 => 125.0, self::$vatAccount => -25.0, self::RESULT_DESTINATION => -100.0]),
                $this->activePrimo());
            $recount = $this->runner('count', '21', '145');
            self::assertSame(145.0, (float)$recount['byttepenge']);
            self::assertSame(0.0, (float)$recount['diff']);
        } finally {
            CharacterizationEnv::rows($t, "UPDATE kontoplan SET kontotype = 'R' WHERE kontonr = $1 AND regnskabsaar = $2", [self::RESULT_LINE, $y]);
            $this->restoreSideskift();
        }
    }

    public function testASideskiftAccountWhoseOverforTilIsNotInTheActiveYearsChartLetsTheCountThroughWithAWarning(): void
    {
        // Last year's Sideskift account transfers to an account the active year's chart lacks. The carry takes the Sideskift
        // account and stops at the result. Before: refused before posting ('... Sideskift med overførsel til en statuskonto').
        // Now the sale is posted in last year, the opening is left as it was, and the warning names the account to correct.
        $lastYear = (date('Y') - 1) . '-12-31';
        $t = self::$tenant;
        CharacterizationEnv::rows($t, 'UPDATE kontoplan SET overfor_til = 58199 WHERE kontonr = $1 AND regnskabsaar = $2', [self::RESULT, self::$openYear]);
        try {
            $old = $this->sale(22, 100, [['Kontant', 125]], false, $lastYear);
            $new = $this->sale(22, 16, [['Kontant', 20]], false);
            $primo = $this->activePrimo();

            $close = $this->runner('close', '22', $this->regnstart(), '0');
            self::assertFalse($close['exit'], 'the close goes through: ' . json_encode($close));
            self::assertCount(1, $close['alerts'] ?? [], json_encode($close));
            $last = $this->yearName(self::$openYear);
            $active = $this->yearName(self::$year);
            self::assertStringContainsString("Kontoen af typen Sideskift " . self::RESULT . " i kontoplanen for $last har Overfør til 58199, "
                . "som ikke er en statuskonto i kontoplanen for $active. Vælg en statuskonto som Overfør til på linjen Resultat, og tryk "
                . "Gem/opdatér på åbningsbalancen for regnskabsår $active", (string)$close['alerts'][0]);

            self::assertPostings([self::TILL_22 => 125.0, self::SALES => -100.0, self::$vatAccount => -25.0], $this->postings($old));
            self::assertSame(4, $this->orderStatus($old));
            self::assertSame(4, $this->orderStatus($new));
            self::assertSame($primo, $this->activePrimo(), 'the active year\'s opening is left as it was');
        } finally {
            $this->restoreSideskift();
        }
    }

    public function testAStatusAccountThatCannotBeCarriedToTheActiveYearStopsTheCount(): void
    {
        // Last year has a status account that the active year lacks, so updateFollowingOpeningBalances() carries
        // nothing. The sale is posted first and the whole close is then rolled back.
        $lastYear = (date('Y') - 1) . '-12-31';
        $y = self::$openYear;
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, regnskabsaar, primo, saldo)
            VALUES (58131, 'Kun sidste år', 'S', '', $y, 0, 0)");
        try {
            $old = $this->sale(9, 100, [['Kontant', 125]], false, $lastYear);
            $oldKonto = $this->sale(9, 80, [['Konto', 100]], true, $lastYear);
            $new = $this->sale(9, 16, [['Kontant', 20]], false);
            $primo = $this->activePrimo();
            $oldYearTill = $this->account(self::TILL, $y, 'saldo');

            $close = $this->runner('close', '9', $this->regnstart(), '0');

            // The refusal names the account and the two years where the carry stopped. Before: 'Kontrollér, at alle statuskonti
            // fra regnskabsår <the oldest year posted into> findes i kontoplanen for det følgende år.'
            $this->assertCountRefused($close, 9, '31-12-' . (date('Y') - 1), 'fordi statuskontoen 58131 i kontoplanen for regnskabsår '
                . $this->yearName($y) . ' ikke kan føres til regnskabsår ' . $this->yearName(self::$year) . ': hverken kontoen eller dens Overfør til '
                . 'er en statuskonto i kontoplanen for ' . $this->yearName(self::$year) . '.', [$old, $oldKonto, $new], $primo);
            self::assertSame($oldYearTill, $this->account(self::TILL, $y, 'saldo'), 'last year\'s posting is rolled back');
            self::assertSame([], $this->openPosts($oldKonto));
        } finally {
            CharacterizationEnv::rows(self::$tenant, "DELETE FROM kontoplan WHERE kontonr = 58131 AND regnskabsaar = $y");
        }
    }

    public function testASaleInALockedPeriodOfTheActiveYearStopsTheCount(): void
    {
        // A sale on account on a day without a cash sale is now posted by the close. When its month is locked for
        // VAT, the count is refused with the lock's message instead of failing on the trigger (PostgreSQL) or
        // posting into the reported period (MySQL/MariaDB, no trigger). Schema as moms_periode_luk_ensure_schema().
        $t = self::$tenant;
        $start = CharacterizationEnv::one($t, "SELECT box1, box2 FROM grupper WHERE art = 'RA' AND kodenr = '" . self::$year . "'");
        $month = (int)$start['box1'] === (int)date('n') ? (int)$start['box1'] + 1 : (int)$start['box1'];
        $lockedDate = sprintf('%04d-%02d-15', (int)$start['box2'], $month);
        CharacterizationEnv::rows($t, "CREATE TABLE moms_periode_luk (id SERIAL PRIMARY KEY, kalender_aar INTEGER NOT NULL,
            kalender_maaned INTEGER NOT NULL, status VARCHAR(6) NOT NULL DEFAULT 'open', lukket_af VARCHAR(100), lukket_dato TIMESTAMP,
            aabnet_af VARCHAR(100), aabnet_dato TIMESTAMP, UNIQUE (kalender_aar, kalender_maaned))");
        CharacterizationEnv::rows($t, "CREATE FUNCTION check_moms_periode_luk() RETURNS TRIGGER AS \$\$ BEGIN
            IF EXISTS (SELECT 1 FROM moms_periode_luk WHERE kalender_aar = EXTRACT(YEAR FROM NEW.transdate)
                AND kalender_maaned = EXTRACT(MONTH FROM NEW.transdate) AND status = 'closed') THEN
                RAISE EXCEPTION 'Perioden % er lukket for bogfoering - kontakt bogholder for at genaabne.', TO_CHAR(NEW.transdate, 'MM-YYYY');
            END IF; RETURN NEW; END; \$\$ LANGUAGE plpgsql");
        CharacterizationEnv::rows($t, "CREATE TRIGGER tr_check_moms_periode_luk BEFORE INSERT OR UPDATE ON transaktioner
            FOR EACH ROW EXECUTE FUNCTION check_moms_periode_luk()");
        try {
            CharacterizationEnv::rows($t, "INSERT INTO moms_periode_luk (kalender_aar, kalender_maaned, status) VALUES ($1, $2, 'closed')",
                [(int)$start['box2'], $month]);
            $locked = $this->sale(10, 100, [['Konto', 125]], true, $lockedDate);
            $new = $this->sale(10, 16, [['Kontant', 20]], false);
            $primo = $this->activePrimo();

            $close = $this->runner('close', '10', $this->regnstart(), '0');

            $this->assertCountRefused($close, 10, date('d-m-Y', strtotime($lockedDate)), 'er lukket for bogfoering', [$locked, $new], $primo);
            self::assertSame([], $this->openPosts($locked));
        } finally {
            CharacterizationEnv::rows($t, 'DROP TRIGGER IF EXISTS tr_check_moms_periode_luk ON transaktioner');
            CharacterizationEnv::rows($t, 'DROP FUNCTION IF EXISTS check_moms_periode_luk()');
            CharacterizationEnv::rows($t, 'DROP TABLE IF EXISTS moms_periode_luk');
        }
    }

    // ------------------------------------------------------------------ Sales without a fiscal year (owner's decision 18 Nov 2026)

    public function testASaleWithoutAFiscalYearIsNeitherCountedNorPostedAndIsListedForTheBookkeeper(): void
    {
        // The year before last is deleted (deleteFiscalYear.php sets box10 to a timestamp) and no fiscal year covers six
        // years ago. Their sales stay at status 3 without a report number, the count does not expect them, and the close
        // posts today's sale and lists them. A lock on the deleted year's month does not stop the close: nothing is posted there.
        $t = self::$tenant;
        $deletedDate = (date('Y') - 2) . '-06-30';
        $beforeFirstDate = (date('Y') - 6) . '-12-31';
        $box10 = $this->setBox('RA', self::$closedYear, 'box10', '1700000000');
        CharacterizationEnv::rows($t, "CREATE TABLE moms_periode_luk (id SERIAL PRIMARY KEY, kalender_aar INTEGER NOT NULL,
            kalender_maaned INTEGER NOT NULL, status VARCHAR(6) NOT NULL DEFAULT 'open', lukket_af VARCHAR(100), lukket_dato TIMESTAMP,
            aabnet_af VARCHAR(100), aabnet_dato TIMESTAMP, UNIQUE (kalender_aar, kalender_maaned))");
        try {
            CharacterizationEnv::rows($t, "INSERT INTO moms_periode_luk (kalender_aar, kalender_maaned, status) VALUES ($1, 6, 'closed')", [date('Y') - 2]);
            $inDeleted = $this->sale(11, 100, [['Kontant', 125]], false, $deletedDate);
            $beforeFirst = $this->sale(11, 40, [['Konto', 50]], true, $beforeFirstDate);
            $new = $this->sale(11, 16, [['Kontant', 20]], false);

            $count = $this->runner('count', '11', '20');
            self::assertSame(20.0, (float)$count['tilgang'], 'only the cash of today\'s sale is expected');
            self::assertSame(0.0, (float)$count['kontosum']);
            self::assertSame(0.0, (float)$count['diff']);
            self::assertSame([$new], $count['ids']);

            $close = $this->runner('close', '11', $this->regnstart(), '0');
            self::assertFalse($close['exit'], 'the close goes through: ' . json_encode($close));
            self::assertTrue($close['posbogfor']);
            self::assertCount(1, $close['alerts'] ?? [], json_encode($close));
            $alert = (string)$close['alerts'][0];
            self::assertStringContainsString('regnskabsåret for salgsdatoen er slettet eller ikke findes', $alert);
            self::assertStringContainsString("\n" . date('d-m-Y', strtotime($beforeFirstDate)) . ", bon $beforeFirst: 50,00, kasse 11", $alert);
            self::assertStringContainsString("\n" . date('d-m-Y', strtotime($deletedDate)) . ", bon $inDeleted: 125,00, kasse 11", $alert);

            self::assertPostings([self::TILL => 20.0, self::SALES => -16.0, self::$vatAccount => -4.0], $this->postings(null, 11),
                'only today\'s sale is posted');
            self::assertSame([], $this->postings($inDeleted));
            self::assertSame([], $this->postings($beforeFirst));
            self::assertSame([], $this->openPosts($beforeFirst));
            self::assertSame(3, $this->orderStatus($inDeleted));
            self::assertSame(3, $this->orderStatus($beforeFirst));
            self::assertSame(4, $this->orderStatus($new));
            $report = (int)CharacterizationEnv::one($t, "SELECT report_number FROM report WHERE description = 'Cash count, box 11'")['report_number'];
            self::assertGreaterThan(0, $report);
            self::assertSame([$inDeleted => 0, $beforeFirst => 0, $new => $report], array_map('intval', array_column(CharacterizationEnv::rows($t,
                'SELECT id, report_number FROM ordrer WHERE id IN ($1, $2, $3) ORDER BY id', [$inDeleted, $beforeFirst, $new]), 'report_number', 'id')));
        } finally {
            CharacterizationEnv::rows($t, 'DROP TABLE IF EXISTS moms_periode_luk');
            $this->setBox('RA', self::$closedYear, 'box10', $box10);
        }
    }

    // ------------------------------------------------------------------ An account missing from the chart of the sale's fiscal year
    // Placed after the last count that asserts the float of the shared till account TILL, which a carry into the active year
    // changes when the fix is reverted, and before the commission cases, whose clean-up leaves rows on COMMISSION in last year.

    public function testACommissionAccountOnlyInTheActiveYearsChartStopsTheCountOfASaleFromBeforeTheYearSwitch(): void
    {
        // The commission account is one setting for all years, but here it names an account that is in the active year's chart
        // only, as an account added after the year switch is. The commission of last year's sale is moved in last year, and
        // updateFollowingOpeningBalances() reads last year's balances from last year's chart: it would leave the 40 on that
        // account out of last year's result and out of equity in the active year's opening balances, which would then sum to
        // 40 more than before. The whole close is rolled back instead.
        $lastYear = (date('Y') - 1) . '-12-31';
        $t = self::$tenant;
        $item = $this->setUpCommission();
        try {
            CharacterizationEnv::rows($t, "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, regnskabsaar, primo, saldo)
                SELECT $1, 'Kommissionssalg, ny konto', 'D', moms, regnskabsaar, 0, 0 FROM kontoplan WHERE kontonr = $2 AND regnskabsaar = $3",
                [self::ACTIVE_YEAR_COMMISSION, self::COMMISSION, self::$year]);
            CharacterizationEnv::rows($t, "UPDATE settings SET var_value = $1 WHERE var_grp = 'items' AND var_name IN ('commissionAccountNew', 'ownCommissionAccountNew')",
                [(string)self::ACTIVE_YEAR_COMMISSION]);
            $old = $this->commissionSale(16, $item, $lastYear);
            $new = $this->sale(16, 16, [['Kontant', 20]], false);
            $primo = $this->activePrimo();
            $oldYearTill = $this->account(self::TILL, self::$openYear, 'saldo');

            $close = $this->runner('close', '16', $this->regnstart(), '0');

            // First, so that a failure shows the amount the opening balances would leave out.
            self::assertSame(round(array_sum($primo), 2), round(array_sum($this->activePrimo()), 2), 'the active year\'s opening balances sum as before');
            $date = '31-12-' . (date('Y') - 1);
            $this->assertCountRefused($close, 16, $date,
                "$date: konto " . self::ACTIVE_YEAR_COMMISSION . ' findes ikke som drifts- eller statuskonto i kontoplanen for regnskabsår ' . (date('Y') - 1) . '.',
                [$old, $new], $primo);
            self::assertSame($oldYearTill, $this->account(self::TILL, self::$openYear, 'saldo'), 'nothing is posted in last year');
        } finally {
            $this->removeCommission();
            CharacterizationEnv::rows($t, 'DELETE FROM kontoplan WHERE kontonr = $1', [self::ACTIVE_YEAR_COMMISSION]);
        }
    }

    public function testASalesAccountOnlyInTheActiveYearsChartStopsTheCountOfASaleFromBeforeTheYearSwitch(): void
    {
        // A sale rung up after the active year was switched but dated last year has the sales account of the active year's
        // item group, here an account that last year's chart lacks. Posted in last year, its 100 would be left out of last
        // year's result and of the active year's opening balances (100 more than before). The whole close is rolled back.
        $lastYear = (date('Y') - 1) . '-12-31';
        $t = self::$tenant;
        CharacterizationEnv::rows($t, "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, regnskabsaar, primo, saldo)
            SELECT $1, 'Salg, ny varegruppe', 'D', moms, regnskabsaar, 0, 0 FROM kontoplan WHERE kontonr = $2 AND regnskabsaar = $3",
            [self::ACTIVE_YEAR_SALES, self::SALES, self::$year]);
        try {
            $old = $this->sale(17, 100, [['Kontant', 125]], false, $lastYear);
            CharacterizationEnv::rows($t, 'UPDATE ordrelinjer SET bogf_konto = $1 WHERE ordre_id = $2', [self::ACTIVE_YEAR_SALES, $old]);
            $new = $this->sale(17, 16, [['Kontant', 20]], false);
            $primo = $this->activePrimo();
            $oldYearTill = $this->account(self::TILL, self::$openYear, 'saldo');

            $close = $this->runner('close', '17', $this->regnstart(), '0');

            // First, so that a failure shows the amount the opening balances would leave out.
            self::assertSame(round(array_sum($primo), 2), round(array_sum($this->activePrimo()), 2), 'the active year\'s opening balances sum as before');
            $date = '31-12-' . (date('Y') - 1);
            $this->assertCountRefused($close, 17, $date,
                "$date: konto " . self::ACTIVE_YEAR_SALES . ' findes ikke som drifts- eller statuskonto i kontoplanen for regnskabsår ' . (date('Y') - 1) . '.',
                [$old, $new], $primo);
            self::assertSame($oldYearTill, $this->account(self::TILL, self::$openYear, 'saldo'), 'nothing is posted in last year');
        } finally {
            CharacterizationEnv::rows($t, 'DELETE FROM kontoplan WHERE kontonr = $1', [self::ACTIVE_YEAR_SALES]);
        }
    }

    // ------------------------------------------------------------------ Commission moved to the own commission account

    /**
     * Own commission on: item kn-commission in a group of its own whose sales account is $salesAccount (SALES by
     * default), the commission account with $vatCode in every year (by default SALES' 25 % VAT code; '' for none).
     * Returns the item id; removeCommission() undoes it, and also removes COMMISSION_VAT, MARGIN_SALES and VAT code 9.
     */
    private function setUpCommission(?string $vatCode = null, int $salesAccount = self::SALES): int
    {
        $t = self::$tenant;
        $vatCode ??= CharacterizationEnv::one($t, "SELECT moms FROM kontoplan WHERE kontonr = $1 AND regnskabsaar = $2", [self::SALES, self::$year])['moms'];
        foreach ([self::$closedYear, self::$openYear, self::$year] as $k) {
            CharacterizationEnv::rows($t, "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, regnskabsaar, primo, saldo) VALUES ($1, 'Kommissionssalg', 'D', $2, $3, 0, 0)",
                [self::COMMISSION, $vatCode, $k]);
            CharacterizationEnv::rows($t, "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box4, fiscal_year) VALUES ('Kommission', '', 991, 'VG', $1, $2)",
                [(string)$salesAccount, $k]);
        }
        CharacterizationEnv::rows($t, "INSERT INTO settings (var_name, var_grp, var_value) VALUES ('commissionAccountNew', 'items', $1), ('ownCommissionAccountNew', 'items', $1)",
            [(string)self::COMMISSION]);
        return (int)CharacterizationEnv::one($t, "INSERT INTO varer (varenr, beskrivelse, enhed, gruppe, salgspris, kostpris, provision, lukket, samlevare, delvare)
            VALUES ('kn-commission', 'Kommissionsvare', 'stk', 991, 100, 60, 50, '', '', '') RETURNING id")['id'];
    }

    private function removeCommission(): void
    {
        $t = self::$tenant;
        CharacterizationEnv::rows($t, "DELETE FROM varer WHERE varenr = 'kn-commission'");
        CharacterizationEnv::rows($t, "DELETE FROM settings WHERE var_grp = 'items' AND var_name IN ('commissionAccountNew', 'ownCommissionAccountNew')");
        CharacterizationEnv::rows($t, "DELETE FROM grupper WHERE art = 'VG' AND kodenr = 991");
        CharacterizationEnv::rows($t, "DELETE FROM grupper WHERE art = 'SM' AND kodenr = 9");
        CharacterizationEnv::rows($t, 'DELETE FROM kontoplan WHERE kontonr IN ($1, $2, $3)', [self::COMMISSION, self::COMMISSION_VAT, self::MARGIN_SALES]);
    }

    /** A sale of the commission item at 100 ex VAT, bought in at 60, paid Kontant 125. */
    private function commissionSale(int $till, int $item, string $date = ''): int
    {
        $id = $this->sale($till, 100, [['Kontant', 125]], false, $date);
        CharacterizationEnv::rows(self::$tenant, "UPDATE ordrelinjer SET vare_id = $1, varenr = 'kn-commission', kostpris = 60 WHERE ordre_id = $2", [$item, $id]);
        return $id;
    }

    /** @return list<array{0:string, 1:string, 2:int, 3:float, 4:float}> transdate, text, account, debit, credit of the till's commission rows */
    private function commissionRows(int $till): array
    {
        $rows = [];
        foreach (CharacterizationEnv::rows(self::$tenant, "SELECT transdate, beskrivelse, kontonr, debet, kredit FROM transaktioner
            WHERE kasse_nr = $1 AND ordre_id = 0 AND beskrivelse LIKE '%ommisionssalg%' ORDER BY transdate, kontonr, debet", [$till]) as $r) {
            $rows[] = [$r['transdate'], $r['beskrivelse'], (int)$r['kontonr'], round((float)$r['debet'], 2), round((float)$r['kredit'], 2)];
        }
        return $rows;
    }

    /**
     * The reclass of $commission (sale ex VAT - cost) with 25 % VAT on both accounts, dated $date, as moveToOwnAccount.php
     * posts it, in the order of commissionRows().
     */
    private function commissionReclass(string $date, int $till, float $commission): array
    {
        $vat = round($commission * 0.25, 2);
        $rows = [
            [$date, "Kommisionssalg, Kasse $till", self::SALES, $commission, 0.0],
            [$date, "Kommisionssalg, Kasse $till", self::COMMISSION, 0.0, $commission],
            [$date, "Moms af kommisionssalg, Kasse $till", self::$vatAccount, 0.0, $vat],
            [$date, "Moms af kommisionssalg, Kasse $till", self::$vatAccount, $vat, 0.0],
        ];
        usort($rows, static fn(array $a, array $b): int => [$a[2], $a[3]] <=> [$b[2], $b[3]]);
        return $rows;
    }

    public function testTheCommissionOfASaleFromBeforeTheYearSwitchIsMovedInItsOwnFiscalYear(): void
    {
        // Before: the close reclassified the commission of every listed date at once, dated today in the active year,
        // while the sale itself is posted in its own year: last year's sales 40 too high and its output VAT 10 too high.
        $lastYear = (date('Y') - 1) . '-12-31';
        $item = $this->setUpCommission();
        try {
            $old = $this->commissionSale(12, $item, $lastYear);
            $new = $this->commissionSale(12, $item);

            $close = $this->runner('close', '12', $this->regnstart(), '0');
            self::assertFalse($close['exit'], json_encode($close));

            self::assertSame(array_merge($this->commissionReclass($lastYear, 12, 40.0), $this->commissionReclass(date('Y-m-d'), 12, 40.0)),
                $this->commissionRows(12), 'the commission of each sale is moved as of the sale\'s fiscal year: last year\'s on its date, today\'s today');
            self::assertSame([date('Y-m-d')], array_column(CharacterizationEnv::rows(self::$tenant, "SELECT DISTINCT logdate FROM transaktioner
                WHERE kasse_nr = 12 AND beskrivelse LIKE '%ommisionssalg%'"), 'logdate'), 'both are registered today');
            self::assertPostings([self::TILL => 125.0, self::SALES => -100.0, self::$vatAccount => -25.0], $this->postings($old));
            $byYear = [];
            foreach (CharacterizationEnv::rows(self::$tenant, "SELECT transdate >= $1 AS active, kontonr, sum(debet) - sum(kredit) AS net FROM transaktioner
                WHERE kasse_nr = 12 AND kontonr IN ($2, $3, $4) GROUP BY 1, 2 ORDER BY 1, 2", [$this->regnstart(), self::SALES, self::COMMISSION, self::$vatAccount]) as $r) {
                $byYear[$r['active'] === 't' ? 'active' : 'last'][(int)$r['kontonr']] = round((float)$r['net'], 2);
            }
            $perYear = [self::SALES => -60.0, self::$vatAccount => -25.0, self::COMMISSION => -40.0];
            ksort($perYear);
            self::assertSame(['last' => $perYear, 'active' => $perYear], $byYear, 'each year: sales 100 - 40, VAT 25, commission 40');
            self::assertSame(4, $this->orderStatus($old));
            self::assertSame(4, $this->orderStatus($new));
        } finally {
            $this->removeCommission();
        }
    }

    public function testTheCommissionOfASaleOfTheActiveYearIsMovedAsBefore(): void
    {
        // Guard: sales of the active year are settled together, as of today, as before.
        $item = $this->setUpCommission();
        try {
            $this->commissionSale(13, $item);
            $this->commissionSale(13, $item);

            $close = $this->runner('close', '13', $this->regnstart(), '0');
            self::assertFalse($close['exit'], json_encode($close));

            self::assertSame($this->commissionReclass(date('Y-m-d'), 13, 80.0), $this->commissionRows(13), 'one reclass of both sales, dated today');
        } finally {
            $this->removeCommission();
        }
    }

    /** @return array<string, float> debit minus credit of everything posted on the till, per transaction date */
    private function imbalance(int $till): array
    {
        $net = [];
        foreach (CharacterizationEnv::rows(self::$tenant, 'SELECT transdate, sum(debet) - sum(kredit) AS net FROM transaktioner
            WHERE kasse_nr = $1 GROUP BY transdate ORDER BY transdate', [$till]) as $r) {
            $net[$r['transdate']] = round((float)$r['net'], 2);
        }
        return $net;
    }

    /** @return array<int, float> the VAT amount (transaktioner.moms) of the till's commission rows, per account */
    private function commissionRowVat(int $till): array
    {
        $vat = [];
        foreach (CharacterizationEnv::rows(self::$tenant, "SELECT kontonr, sum(moms) AS moms FROM transaktioner
            WHERE kasse_nr = $1 AND ordre_id = 0 AND beskrivelse LIKE 'Kommisionssalg%' GROUP BY kontonr ORDER BY kontonr", [$till]) as $r) {
            $vat[(int)$r['kontonr']] = round((float)$r['moms'], 2);
        }
        return $vat;
    }

    public function testACommissionAccountWithoutAVatCodeGetsTheCommissionWithoutVat(): void
    {
        // Before: the reclass debited the sale's VAT on the commission (VAT D 10, sales D 40) against commission C 40 only:
        // 10 more debit than credit, and the VAT charged on the sale lowered by 10. Now the 40 moves between the two
        // revenue accounts and the VAT account keeps the 25 charged on the sale.
        $item = $this->setUpCommission('');
        try {
            $sale = $this->commissionSale(18, $item);

            $close = $this->runner('close', '18', $this->regnstart(), '0');
            self::assertFalse($close['exit'], json_encode($close));

            $today = date('Y-m-d');
            self::assertSame([
                [$today, 'Kommisionssalg, Kasse 18', self::SALES, 40.0, 0.0],
                [$today, 'Kommisionssalg, Kasse 18', self::COMMISSION, 0.0, 40.0],
            ], $this->commissionRows(18), 'the commission moves without VAT rows');
            self::assertSame([self::SALES => 0.0, self::COMMISSION => 0.0], $this->commissionRowVat(18));
            self::assertSame([$today => 0.0], $this->imbalance(18), 'debit equals credit');
            self::assertPostings([self::TILL => 125.0, self::SALES => -60.0, self::$vatAccount => -25.0, self::COMMISSION => -40.0],
                $this->postings(null, 18), 'the VAT account holds the 25 charged on the sale');
            self::assertSame(4, $this->orderStatus($sale));
        } finally {
            $this->removeCommission();
        }
    }

    public function testACommissionAccountWithAnotherVatRateGetsTheVatChargedOnTheSale(): void
    {
        // The commission account has VAT code 9 at 12 % on a VAT account of its own. Before: the reclass debited the sale's
        // 25 % (VAT D 10) and credited 12 % (COMMISSION_VAT C 4,80): 5,20 more debit than credit, and the VAT charged on
        // the sale lowered by 5,20. Now the 10 charged on the sale moves to the commission code's VAT account.
        $t = self::$tenant;
        $item = $this->setUpCommission('S9');
        try {
            CharacterizationEnv::rows($t, "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, fiscal_year) VALUES ('Salgsmoms 12 %', '', 9, 'SM', $1, '12', $2)",
                [(string)self::COMMISSION_VAT, self::$year]);
            CharacterizationEnv::rows($t, "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, regnskabsaar, primo, saldo) VALUES ($1, 'Salgsmoms 12 %', 'S', '', $2, 0, 0)",
                [self::COMMISSION_VAT, self::$year]);
            $sale = $this->commissionSale(19, $item);

            $close = $this->runner('close', '19', $this->regnstart(), '0');
            self::assertFalse($close['exit'], json_encode($close));

            $today = date('Y-m-d');
            self::assertSame([
                [$today, 'Moms af kommisionssalg, Kasse 19', self::$vatAccount, 10.0, 0.0],
                [$today, 'Kommisionssalg, Kasse 19', self::SALES, 40.0, 0.0],
                [$today, 'Kommisionssalg, Kasse 19', self::COMMISSION, 0.0, 40.0],
                [$today, 'Moms af kommisionssalg, Kasse 19', self::COMMISSION_VAT, 0.0, 10.0],
            ], $this->commissionRows(19), 'the 25 % charged on the sale moves to the 12 % code\'s VAT account as charged');
            self::assertSame([self::SALES => 0.0, self::COMMISSION => -10.0], $this->commissionRowVat(19));
            self::assertSame([$today => 0.0], $this->imbalance(19), 'debit equals credit');
            self::assertPostings([
                self::TILL => 125.0,
                self::SALES => -60.0,
                self::$vatAccount => -15.0,
                self::COMMISSION => -40.0,
                self::COMMISSION_VAT => -10.0,
            ], $this->postings(null, 19), 'the VAT accounts hold the 25 charged on the sale: 15 + 10');
            self::assertSame(4, $this->orderStatus($sale));
        } finally {
            $this->removeCommission();
        }
    }

    public function testASalesAccountWithoutAVatCodeHasTheVatTakenOutOfTheCommissionAsBefore(): void
    {
        // Guard, the margin case: the item group's sales account has no VAT code, the commission account 25 %. The sale
        // of 125 is posted without VAT; the reclass moves the commission 125 - 60 = 65 and takes the VAT out of it,
        // 65 * 25 / 125 = 13, as before.
        $t = self::$tenant;
        $item = $this->setUpCommission(null, self::MARGIN_SALES);
        try {
            CharacterizationEnv::rows($t, "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, regnskabsaar, primo, saldo) VALUES ($1, 'Salg brugtmoms', 'D', '', $2, 0, 0)",
                [self::MARGIN_SALES, self::$year]);
            $sale = $this->sale(20, 125, [['Kontant', 125]], false);
            CharacterizationEnv::rows($t, 'UPDATE ordrer SET moms = 0, momssats = 0 WHERE id = $1', [$sale]);
            CharacterizationEnv::rows($t, "UPDATE ordrelinjer SET vare_id = $1, varenr = 'kn-commission', kostpris = 60, momssats = 0, bogf_konto = $2, vat_account = 0
                WHERE ordre_id = $3", [$item, self::MARGIN_SALES, $sale]);

            $close = $this->runner('close', '20', $this->regnstart(), '0');
            self::assertFalse($close['exit'], json_encode($close));

            $today = date('Y-m-d');
            self::assertSame([
                [$today, 'Moms af kommisionssalg, Kasse 20', self::$vatAccount, 0.0, 13.0],
                [$today, 'Kommisionssalg, Kasse 20', self::COMMISSION, 0.0, 52.0],
                [$today, 'Kommisionssalg, Kasse 20', self::MARGIN_SALES, 65.0, 0.0],
            ], $this->commissionRows(20), 'the margin 65 incl. VAT: VAT 13, commission 52');
            self::assertSame([self::COMMISSION => -13.0, self::MARGIN_SALES => 0.0], $this->commissionRowVat(20));
            self::assertSame([$today => 0.0], $this->imbalance(20), 'debit equals credit');
            self::assertPostings([self::TILL => 125.0, self::$vatAccount => -13.0, self::COMMISSION => -52.0, self::MARGIN_SALES => -60.0],
                $this->postings(null, 20));
            self::assertSame(4, $this->orderStatus($sale));
        } finally {
            $this->removeCommission();
        }
    }

    // ------------------------------------------------------------------ Euro cash on a sale the close posts

    /**
     * Euro counted in the till (VK box4 '1') by the count assistant (POS 2 box2), rate 745,60, till account TILL_EUR for
     * $till. Returns what restoreEuro() needs.
     */
    private function setUpEuro(int $till): string
    {
        $t = self::$tenant;
        CharacterizationEnv::rows($t, "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5, box6) VALUES ('Euro', '', 1, 'VK', 'EUR', '', '', '1', '', '')");
        CharacterizationEnv::rows($t, "INSERT INTO valuta (gruppe, valdate, kurs) VALUES (1, '2000-01-01', 745.60)");
        CharacterizationEnv::rows($t, "INSERT INTO settings (var_name, var_grp, var_value, var_description, user_id, pos_id) VALUES ('till_account_EUR', 'VK', $1, '', 0, $2)",
            [(string)self::TILL_EUR, $till]);
        return $this->setBox('POS', 2, 'box2', 'on', self::$year);
    }

    private function restoreEuro(string $box2): void
    {
        $t = self::$tenant;
        $this->setBox('POS', 2, 'box2', $box2, self::$year);
        CharacterizationEnv::rows($t, "DELETE FROM settings WHERE var_grp = 'VK' AND var_name = 'till_account_EUR'");
        CharacterizationEnv::rows($t, 'DELETE FROM valuta WHERE gruppe = 1');
        CharacterizationEnv::rows($t, "DELETE FROM grupper WHERE art = 'VK' AND kodenr = 1");
    }

    /** The last payment row of the sale was 20 EUR: 149,12 kr. at 745,60. */
    private function paidInEuro(int $orderId): void
    {
        CharacterizationEnv::rows(self::$tenant, "UPDATE pos_betalinger SET valuta = 'EUR', valutakurs = 745.6
            WHERE id = (SELECT max(id) FROM pos_betalinger WHERE ordre_id = $1)", [$orderId]);
    }

    /** @return array<string, list<string>> the count form's euro fields for 20 EUR of takings, no difference, nothing to the safe */
    private static function euroForm(): array
    {
        return ['valuta' => ['EUR'], 'ValutaTilgang' => ['20'], 'ValutaKasseDiff' => ['0'], 'ValutaUdtages' => ['0,00']];
    }

    public function testWithBox9EuroCashOnAnAccountSaleIsCountedAndPostedAsEuro(): void
    {
        // An account sale of 200 paid Konto 100 and 20 EUR (149,12 kr.), 49,12 kr. back, waits at status 3 for the close.
        // The kroner count expects the change out of the till and the Konto part on account, the euro count the euro; the
        // close posts the sale and moves the euro to the euro till, so each till account holds what its count expected.
        $box9 = $this->setBox('POS', 1, 'box9', 'on', self::$year);
        $box2 = $this->setUpEuro(14);
        try {
            $sale = $this->sale(14, 160, [['Konto', 100], ['Kontant', 149.12]], true);
            $this->paidInEuro($sale);

            $kroner = $this->runner('count', '14', '0');
            self::assertSame(-49.12, (float)$kroner['tilgang'], 'the change given in kroner, not the euro as a card');
            self::assertSame(100.0, (float)$kroner['kontosum']);
            self::assertSame([$sale], $kroner['ids']);
            $euro = $this->runner('count', '14', '149.12', 'EUR');
            self::assertSame(149.12, (float)$euro['tilgang'], 'the 20 EUR');
            self::assertSame(0.0, (float)$euro['diff']);

            $close = $this->runner('close', '14', $this->regnstart(), '0', json_encode(self::euroForm()));
            self::assertFalse($close['exit'], json_encode($close));

            self::assertPostings([
                self::$debtorAccount => 100.0,
                self::TILL_14 => -49.12,
                self::TILL_EUR => 149.12,
                self::SALES => -160.0,
                self::$vatAccount => -40.0,
            ], $this->postings(null, 14), 'kroner till: 100 from the sale - 149,12 moved to the euro till = the change');
            self::assertSame([100.0], $this->openPosts($sale));
            self::assertSame(0, (int)CharacterizationEnv::one(self::$tenant,
                "SELECT count(*) AS n FROM transaktioner WHERE kasse_nr = 14 AND beskrivelse LIKE 'Kassedifference%'")['n']);
            self::assertSame(4, $this->orderStatus($sale));

            // The next count finds both tills as the close left them: nothing more to move, no difference.
            $kroner = $this->runner('count', '14', '-49.12');
            self::assertSame(-49.12, (float)$kroner['tilgang']);
            self::assertSame(0.0, (float)$kroner['diff']);
            $euro = $this->runner('count', '14', '149.12', 'EUR');
            self::assertSame(149.12, (float)$euro['byttepenge'], 'the euro moved by the close is the euro till\'s float');
            self::assertSame(0.0, (float)$euro['tilgang']);
            self::assertSame(0.0, (float)$euro['diff']);
        } finally {
            $this->restoreEuro($box2);
            $this->setBox('POS', 1, 'box9', $box9, self::$year);
        }
    }

    public function testEuroCashOnASaleFromBeforeTheYearSwitchIsCountedAndPostedAsEuro(): void
    {
        // A sale of 100 dated last year, paid 20 EUR (149,12 kr.), 49,12 kr. back. The sale is posted in last year on the
        // kroner till account; the close moves the euro to the euro till today. Across the years the kroner till account
        // holds the change given, as the kroner count expected, and the active year opens with last year's 100.
        $lastYear = (date('Y') - 1) . '-12-31';
        $box2 = $this->setUpEuro(15);
        try {
            $sale = $this->sale(15, 80, [['Kontant', 149.12]], false, $lastYear);
            $this->paidInEuro($sale);
            $primo = $this->account(self::TILL, self::$year, 'primo');

            $kroner = $this->runner('count', '15', '0');
            self::assertSame(-49.12, (float)$kroner['tilgang'], 'the change given in kroner, not the euro as a card');
            self::assertSame([$sale], $kroner['ids']);
            $euro = $this->runner('count', '15', '149.12', 'EUR');
            self::assertSame(149.12, (float)$euro['tilgang'], 'the 20 EUR');
            self::assertSame([$sale], $euro['ids']);

            $close = $this->runner('close', '15', $this->regnstart(), '0', json_encode(self::euroForm()));
            self::assertFalse($close['exit'], json_encode($close));

            self::assertPostings([self::TILL => 100.0, self::SALES => -80.0, self::$vatAccount => -20.0], $this->postings($sale));
            self::assertSame([$lastYear], array_column(CharacterizationEnv::rows(self::$tenant,
                'SELECT DISTINCT transdate FROM transaktioner WHERE ordre_id = $1', [$sale]), 'transdate'));
            self::assertPostings([
                self::TILL => -49.12,
                self::TILL_EUR => 149.12,
                self::SALES => -80.0,
                self::$vatAccount => -20.0,
            ], $this->postings(null, 15), 'kroner till: 100 in last year - 149,12 moved to the euro till today = the change');
            self::assertSame(100.0, $this->account(self::TILL, self::$year, 'primo') - $primo, 'the active year opens with last year\'s 100');
            self::assertSame(0, (int)CharacterizationEnv::one(self::$tenant,
                "SELECT count(*) AS n FROM transaktioner WHERE kasse_nr = 15 AND beskrivelse LIKE 'Kassedifference%'")['n']);
        } finally {
            $this->restoreEuro($box2);
        }
    }

    // ------------------------------------------------------------------ POS box9: cash sales posted at the sale

    public function testWithBox9TheCountExpectsTheCashOfAnAccountSaleThatTheClosePosts(): void
    {
        // box9 posts a sale without a customer at once; a sale to an account customer stays at status 3 for the
        // day close. The count must expect its cash, or the close posts it twice: once as a difference, once as sale.
        $old = $this->setBox('POS', 1, 'box9', 'on', self::$year);
        try {
            $walkIn = $this->sale(7, 16, [['Kontant', 20]], false);
            self::assertSame('OK', $this->runner('post', (string)$walkIn)['svar'], 'posted at the sale');
            $account = $this->sale(7, 200, [['Konto', 100], ['Kontant', 150]], true);

            $count = $this->runner('count', '7', '170');
            self::assertSame(170.0, (float)$count['tilgang'], 'the walk-in sale 20 and the cash part 150');
            self::assertSame(100.0, (float)$count['kontosum']);
            self::assertSame(0.0, (float)$count['diff']);
            self::assertSame([$walkIn, $account], $count['ids']);

            $close = $this->runner('close', '7', $this->regnstart(), (string)$count['diff']);
            self::assertFalse($close['exit'], json_encode($close));

            self::assertPostings([
                self::TILL_7 => 170.0,
                self::$debtorAccount => 100.0,
                self::SALES => -216.0,
                self::$vatAccount => -54.0,
            ], $this->postings(null, 7), 'the till holds the 170 counted');
            self::assertSame([100.0], $this->openPosts($account));
            self::assertSame(4, $this->orderStatus($account));
        } finally {
            $this->setBox('POS', 1, 'box9', $old, self::$year);
        }
    }

    // ------------------------------------------------------------------ p1-pos-close-F4: the till account changed at the year switch

    /**
     * The active year's opening after carrying last year as it stands, so a case compares only what it adds. Cases above
     * moved openings by hand (withoutSideskiftLastYear()) that a carry on this Sideskift chart writes back.
     *
     * @return array<int, float>
     */
    private function settledPrimo(): array
    {
        $carry = $this->runner('carry', (string)self::$openYear);
        self::assertSame([self::$year], $carry['updated'], json_encode($carry));
        return $this->activePrimo();
    }

    public function testAnEarlierYearsTillAccountThatTheCarryDoesNotTakeToTheTillsAccountIsListedForTheBookkeeper(): void
    {
        // Till 23 used 58142 as its till account last year and uses 58141 this year; 58142 has no Overfør til, so the carry
        // takes last year's cash onto 58142 in the active year, where the count does not read it. Before: the close went
        // through without a word, and the next count of the same cash showed the 125 as a difference.
        $lastYear = (date('Y') - 1) . '-12-31';
        $t = self::$tenant;
        $last = self::$openYear;
        $tills = (string)CharacterizationEnv::one($t, "SELECT box2 FROM grupper WHERE art = 'POS' AND kodenr = 1 AND fiscal_year = $last")['box2'];
        $lastYearTills = explode(chr(9), $tills);
        $lastYearTills[22] = (string)self::TILL_23_LAST_YEAR;
        $this->setBox('POS', 1, 'box2', implode(chr(9), $lastYearTills), $last);
        try {
            $primo = $this->settledPrimo();
            $old = $this->sale(23, 100, [['Kontant', 125]], false, $lastYear);
            $new = $this->sale(23, 16, [['Kontant', 20]], false);

            $count = $this->runner('count', '23', '145');
            self::assertSame(145.0, (float)$count['tilgang'], 'the count expects the cash of both sales');
            self::assertSame(0.0, (float)$count['diff']);

            $close = $this->runner('close', '23', $this->regnstart(), '0');
            self::assertFalse($close['exit'], 'the close goes through: ' . json_encode($close));
            self::assertTrue($close['posbogfor']);
            self::assertPostings([self::TILL_23_LAST_YEAR => 125.0, self::SALES => -100.0, self::$vatAccount => -25.0], $this->postings($old));
            self::assertSame(4, $this->orderStatus($old));
            self::assertSame(4, $this->orderStatus($new));
            self::assertSame(self::primoPlus($primo, [self::TILL_23_LAST_YEAR => 125.0, self::$vatAccount => -25.0, self::RETAINED => -100.0]),
                $this->activePrimo(), 'last year\'s cash is carried onto 58142, not onto the till\'s 58141');

            $lastName = $this->yearName($last);
            $active = $this->yearName(self::$year);
            self::assertCount(1, $close['alerts'] ?? [], json_encode($close));
            $alert = (string)$close['alerts'][0];
            self::assertStringStartsWith('Kasse 23 har kontantsalg fra et tidligere regnskabsår, som er bogført på en kassekonto, som primo ikke fører over '
                . 'på kassens kassekonto i det aktive regnskabsår. Giv bogholderen denne besked:', $alert);
            self::assertStringContainsString("\nKassekontoen 58142 i regnskabsår $lastName føres til konto 58142 i regnskabsår $active, "
                . "men kassen bruger kassekontoen 58141 i $active. Sæt Overfør til for konto 58142 i regnskabsår $lastName til 58141, og tryk "
                . "Gem/opdatér på åbningsbalancen for regnskabsår $active (Indstillinger » Virksomhed » Regnskabsår » Åbningsbalance), "
                . "eller bogfør en overførsel fra konto 58142 til 58141 i regnskabsår $active.\n", $alert);

            // As the warning says, until it is corrected the till's float misses last year's 125.
            $recount = $this->runner('count', '23', '145');
            self::assertSame(20.0, (float)$recount['byttepenge']);
            self::assertSame(125.0, (float)$recount['diff']);

            // The bookkeeper's correction: Overfør til 58141 on last year's 58142, and the carry onto the active year.
            CharacterizationEnv::rows($t, 'UPDATE kontoplan SET overfor_til = $1 WHERE kontonr = $2 AND regnskabsaar = $3',
                [self::TILL_23, self::TILL_23_LAST_YEAR, $last]);
            $this->settledPrimo();
            $recount = $this->runner('count', '23', '145');
            self::assertSame(145.0, (float)$recount['byttepenge']);
            self::assertSame(0.0, (float)$recount['diff']);
        } finally {
            $this->setBox('POS', 1, 'box2', $tills, $last);
            CharacterizationEnv::rows($t, 'UPDATE kontoplan SET overfor_til = 0 WHERE kontonr = $1 AND regnskabsaar = $2', [self::TILL_23_LAST_YEAR, $last]);
        }
    }

    // ------------------------------------------------------------------ p1-pos-close-R1: two sessions' carries

    /**
     * Starts the runner in a child process that keeps running: with $hold true it stops just before its commit, with
     * 'begin' a close stops just before its transaction (SALDI_POS_HOLD). $settings are PostgreSQL settings for its session
     * (SALDI_PG_SET), e.g. ['deadlock_timeout' => '3s']. The child's sendmail_path writes to a file of its own, so a mail
     * it sends (db_modify()'s error mail to fejl@saldi.dk) reaches no mail program; finish() returns it as 'mail'.
     *
     * @param list<string> $args
     * @param array<string, string> $settings
     * @return array{proc: resource, pipes: array<int, resource>, err: resource, out: string, mail: string}
     */
    private function startRunner(array $args, bool|string $hold, array $settings = []): array
    {
        $err = tmpfile();
        $mail = (string)tempnam(sys_get_temp_dir(), 'saldi-pos-mail-');
        $env = getenv();
        $env['SALDI_POS_HOLD'] = is_string($hold) ? $hold : ($hold ? '1' : '0');
        $env['SALDI_PG_SET'] = implode(',', array_map(static fn(string $k, string $v): string => "$k=$v", array_keys($settings), $settings));
        $proc = proc_open(array_merge([PHP_BINARY, '-d', 'sendmail_path=cat >> ' . escapeshellarg($mail), self::RUNNER, CharacterizationEnv::testDb()], $args),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => $err], $pipes, null, $env);
        self::assertIsResource($proc);
        stream_set_blocking($pipes[1], false);
        return ['proc' => $proc, 'pipes' => $pipes, 'err' => $err, 'out' => '', 'mail' => $mail];
    }

    /** Reads what the child wrote so far; false once its stdout is closed. */
    private static function readRunner(array &$run): bool
    {
        $chunk = fread($run['pipes'][1], 65536);
        if ($chunk !== false && $chunk !== '') {
            $run['out'] .= $chunk;
            return true;
        }
        return !feof($run['pipes'][1]);
    }

    /** Waits until the held child is about to commit and returns its database backend's pid. */
    private function held(array &$run, float $timeout = 60.0): int
    {
        $deadline = microtime(true) + $timeout;
        while (!preg_match('/##HELD## (\{.*\})\n/', $run['out'], $m) && microtime(true) < $deadline && self::readRunner($run)) {
            usleep(20000);
        }
        self::assertSame(1, preg_match('/##HELD## (\{.*\})\n/', $run['out'], $m), 'the session holds before its commit: ' . $run['out']);
        return (int)json_decode($m[1], true)['pid'];
    }

    /** Lets a held child commit. */
    private static function release(array &$run): void
    {
        if (is_resource($run['pipes'][0])) {
            @fwrite($run['pipes'][0], "commit\n");
            @fclose($run['pipes'][0]);
        }
    }

    /**
     * Waits for the child to end (it is killed after $timeout) and returns its JSON line, with 'mail': what the child
     * sent by mail ('' when nothing).
     *
     * @return array<string, mixed>
     */
    private function finish(array &$run, float $timeout = 60.0): array
    {
        self::release($run);
        $deadline = microtime(true) + $timeout;
        while (self::readRunner($run) && microtime(true) < $deadline) {
            usleep(20000);
        }
        $finished = !self::readRunner($run);
        if (!$finished) {
            proc_terminate($run['proc'], 9);
        }
        fclose($run['pipes'][1]);
        proc_close($run['proc']);
        rewind($run['err']);
        $stderr = (string)stream_get_contents($run['err']);
        fclose($run['err']);
        $mail = (string)@file_get_contents($run['mail']);
        @unlink($run['mail']);
        self::assertTrue($finished, "the session ended within {$timeout} s: {$run['out']} $stderr");
        $lines = array_values(array_filter(explode("\n", trim($run['out'])), static fn(string $l): bool => !str_starts_with($l, '##HELD##')));
        $json = $lines !== [] ? json_decode(end($lines), true) : null;
        self::assertIsArray($json, "runner must emit JSON (stdout: {$run['out']} stderr: $stderr)");
        $json['mail'] = $mail;
        return $json;
    }

    /**
     * The statement another session of this database waits on a lock in, other than the given backends and the test's own.
     *
     * @param int[] $exclude
     */
    private function waitingQuery(array $exclude, float $timeout = 60.0): ?string
    {
        $deadline = microtime(true) + $timeout;
        do {
            $row = CharacterizationEnv::one(self::$tenant, "SELECT query FROM pg_stat_activity WHERE datname = current_database()
                AND wait_event_type = 'Lock' AND pid <> pg_backend_pid() AND pid <> ALL($1::int[]) ORDER BY backend_start LIMIT 1",
                ['{' . implode(',', array_map('intval', $exclude)) . '}']);
            if ($row) {
                return (string)$row['query'];
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        return null;
    }

    /** A drift account of last year that no close in these cases posts on, for the journals. */
    private function journalDriftAccount(): int
    {
        $row = CharacterizationEnv::one(self::$tenant, "SELECT min(kontonr) AS k FROM kontoplan WHERE regnskabsaar = $1 AND kontotype = 'D'
            AND kontonr <> ALL($2::int[])", [self::$openYear, '{' . implode(',', [self::SALES, self::COMMISSION, self::MARGIN_SALES,
            self::ACTIVE_YEAR_SALES, self::ACTIVE_YEAR_COMMISSION]) . '}']);
        self::assertGreaterThan(0, (int)$row['k'], 'last year\'s chart has a drift account of its own');
        return (int)$row['k'];
    }

    /** @return array<int, float> the opening of a fiscal year's status accounts */
    private function primoOf(int $year): array
    {
        $primo = [];
        foreach (CharacterizationEnv::rows(self::$tenant, "SELECT kontonr, primo FROM kontoplan WHERE regnskabsaar = $1 AND kontotype = 'S'",
            [$year]) as $row) {
            $primo[(int)$row['kontonr']] = round((float)$row['primo'], 2);
        }
        ksort($primo);
        return $primo;
    }

    /** The journal rows the carry runner has posted and committed so far (finans/bogfor.php's rows of a journal). */
    private function journalRows(): int
    {
        return (int)CharacterizationEnv::one(self::$tenant, "SELECT count(*) AS n FROM transaktioner WHERE beskrivelse = 'chartest kladde'")['n'];
    }

    /** Removes a fiscal year a case created: its chart, its groups and its RA row. */
    private function dropYear(int $year): void
    {
        CharacterizationEnv::rows(self::$tenant, 'DELETE FROM kontoplan WHERE regnskabsaar = $1', [$year]);
        CharacterizationEnv::rows(self::$tenant, "DELETE FROM grupper WHERE fiscal_year = $1 AND art <> 'RA'", [$year]);
        CharacterizationEnv::rows(self::$tenant, "DELETE FROM grupper WHERE art = 'RA' AND kodenr = $1", [$year]);
    }

    public function testAJournalCarryIntoLastYearWaitsForTheDayClosesCarryAndKeepsIt(): void
    {
        // p1-pos-close-R1, the reproduction: the day close of till 24 posts last year's sale (till 125 / sales 100 / VAT 25)
        // and carries it, and before it commits a journal is posted into last year in another session as finans/bogfor.php
        // posts it (the rows, kontoplan.saldo of safe 50 and a drift account -50 in last year, then the carry), at
        // PostgreSQL's default READ COMMITTED. Before: the journal's carry read the balances without the close's sale,
        // waited on the close's rows and then wrote its own absolute openings over the close's: the active year opened
        // till 24 with 0 instead of 125, and the close's result was lost too.
        $lastYear = (date('Y') - 1) . '-12-31';
        $primo = $this->settledPrimo();
        $old = $this->sale(24, 100, [['Kontant', 125]], false, $lastYear);
        $journalPostings = json_encode([[self::SAFE, 50, 0, $lastYear], [$this->journalDriftAccount(), 0, 50, $lastYear]]);

        $close = $this->startRunner(['close', '24', $this->regnstart(), '0'], true);
        $journal = $newest = null;
        try {
            $closePid = $this->held($close);
            // A posting into the active (newest) year has nothing to carry and takes no lock: it does not wait for the close.
            $newest = $this->startRunner(['carry', (string)self::$year], false);
            $newestResult = $this->finish($newest, 30.0);
            $journal = $this->startRunner(['carry', (string)self::$openYear, $journalPostings], false);
            $waiting = $this->waitingQuery([$closePid]);
        } finally {
            self::release($close);
        }
        $closed = $this->finish($close);
        $carried = $this->finish($journal);

        self::assertFalse($newestResult['exit'], json_encode($newestResult));
        self::assertSame([], $newestResult['updated']);
        self::assertFalse($closed['exit'], json_encode($closed));
        self::assertTrue($closed['posbogfor']);
        self::assertSame(4, $this->orderStatus($old));
        self::assertFalse($carried['exit'], json_encode($carried));
        self::assertSame([self::$year], $carried['updated'], json_encode($carried));
        // Both carries are in the active year's opening: till 24 125, safe 50, VAT -25, and last year's profit 100 + 50 in
        // equity.
        self::assertSame(self::primoPlus($primo, [self::TILL_24 => 125.0, self::SAFE => 50.0, self::$vatAccount => -25.0, self::RETAINED => -150.0]),
            $this->activePrimo());
        // The journal's carry waited at the carry's lock, before it read anything.
        self::assertNotNull($waiting, 'the journal\'s carry waited for the day close');
        self::assertStringContainsString('pg_advisory_xact_lock', (string)$waiting);
    }

    public function testADayCloseStartedWhileAJournalIntoLastYearCommitsWaitsForItAndPostsOnTopOfIt(): void
    {
        // p1-pos-close-R1, the other order (review round 1): a journal into last year, posted as finans/bogfor.php posts it
        // (the rows, then kontoplan.saldo of safe 30, sales -24 and VAT -6 in last year, then the carry), is about to
        // commit when the day close of till 25 starts with a sale from last year on the same sales and VAT accounts. The
        // close reads one REPEATABLE READ snapshot (cashCountSnapshot.php). Before: it began that snapshot, waited on the
        // journal's kontoplan rows and, when the journal committed, failed on PostgreSQL's serialization error with the
        // generic error box. Now it takes the carry's lock before its transaction, waits there for the journal, and then
        // posts and carries on top of what the journal committed.
        $lastYear = (date('Y') - 1) . '-12-31';
        $primo = $this->settledPrimo();
        $old = $this->sale(25, 100, [['Kontant', 125]], false, $lastYear);
        $journalPostings = json_encode([[self::SAFE, 30, 0, $lastYear], [self::SALES, 0, 24, $lastYear], [self::$vatAccount, 0, 6, $lastYear]]);

        $journal = $this->startRunner(['carry', (string)self::$openYear, $journalPostings], true);
        $close = null;
        try {
            $journalPid = $this->held($journal);
            $close = $this->startRunner(['close', '25', $this->regnstart(), '0'], false);
            $waiting = $this->waitingQuery([$journalPid]);
        } finally {
            self::release($journal);
        }
        $carried = $this->finish($journal);
        $closed = $this->finish($close);

        self::assertFalse($carried['exit'], json_encode($carried));
        self::assertSame([self::$year], $carried['updated'], json_encode($carried));
        self::assertFalse($closed['exit'], 'the close goes through: ' . json_encode($closed));
        self::assertTrue($closed['posbogfor']);
        self::assertSame([], $closed['alerts'] ?? []);
        self::assertSame(4, $this->orderStatus($old));
        self::assertPostings([self::TILL_25 => 125.0, self::SALES => -100.0, self::$vatAccount => -25.0], $this->postings($old));
        // Both are in the active year's opening: safe 30, till 25 125, VAT -6 - 25, and last year's profit 24 + 100 in equity.
        self::assertSame(self::primoPlus($primo, [self::SAFE => 30.0, self::TILL_25 => 125.0, self::$vatAccount => -31.0, self::RETAINED => -124.0]),
            $this->activePrimo());
        self::assertNotNull($waiting, 'the close waited for the journal');
        self::assertStringContainsString('pg_advisory_lock', (string)$waiting, 'at the carry\'s lock, before its transaction');
    }

    // ------------------------------------------------------------------ the real journal page (finans/bogfor.php)

    /** The journal page in a child process of its own, as Kassekladde's Bogfør posts the journal; finishJournalPage() ends it. */
    private function startJournalPage(int $kladdeId): array
    {
        $sessionDir = sys_get_temp_dir() . '/saldi-pos-journal-' . bin2hex(random_bytes(6));
        mkdir($sessionDir, 0700);
        $html = (string)tempnam(sys_get_temp_dir(), 'saldi-pos-journal-html-');
        $mail = (string)tempnam(sys_get_temp_dir(), 'saldi-pos-journal-mail-');
        $out = tmpfile();
        $err = tmpfile();
        $proc = proc_open(
            [PHP_BINARY, '-d', 'sendmail_path=cat >> ' . escapeshellarg($mail), __DIR__ . '/../support/run_bogfor_page.php', 'bogfor', (string)$kladdeId,
                CharacterizationEnv::sessionId(), $sessionDir, $html],
            [0 => ['file', '/dev/null', 'r'], 1 => $out, 2 => $err],
            $pipes
        );
        self::assertIsResource($proc, 'the journal page starts');
        return ['proc' => $proc, 'out' => $out, 'err' => $err, 'sessionDir' => $sessionDir, 'html' => $html, 'mail' => $mail];
    }

    /**
     * Waits for the journal page to end (it is killed after $timeout).
     *
     * @return array{exit: int, output: string, alerts: list<string>, mail: string}
     */
    private function finishJournalPage(array $page, float $timeout = 60.0): array
    {
        $deadline = microtime(true) + $timeout;
        $status = proc_get_status($page['proc']);
        while ($status['running'] && microtime(true) < $deadline) {
            usleep(50000);
            $status = proc_get_status($page['proc']);
        }
        if ($status['running']) {
            proc_terminate($page['proc'], 9);
        }
        proc_close($page['proc']);
        $output = '';
        foreach (['out', 'err'] as $stream) {
            rewind($page[$stream]);
            $output .= stream_get_contents($page[$stream]);
            fclose($page[$stream]);
        }
        preg_match_all("/alert\\((?:'|\")(.*?)(?:'|\")\\)/s", (string)@file_get_contents($page['html']), $m);
        $mail = (string)@file_get_contents($page['mail']);
        @unlink($page['html']);
        @unlink($page['mail']);
        array_map('unlink', glob($page['sessionDir'] . '/*') ?: []);
        @rmdir($page['sessionDir']);
        return ['exit' => $status['running'] ? -1 : (int)$status['exitcode'], 'output' => substr($output, -600), 'alerts' => $m[1], 'mail' => $mail];
    }

    /** @return array{pid: string, query: string}|null another session of this database waiting for a lock in a statement like $queryLike */
    private function lockWaiter(string $queryLike, float $timeout): ?array
    {
        $deadline = microtime(true) + $timeout;
        do {
            $row = CharacterizationEnv::one(self::$tenant, "SELECT pid, query FROM pg_stat_activity WHERE datname = current_database()
                AND pid <> pg_backend_pid() AND wait_event_type = 'Lock' AND query LIKE $1 ORDER BY backend_start LIMIT 1", [$queryLike]);
            if ($row) {
                return $row;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        return null;
    }

    public function testADayCloseWaitingForTheJournalPageIntoLastYearGetsTheLockOnlyAfterThePagesRecalculationsOfThatYear(): void
    {
        // p1-pos-close-N1: the real journal page posts safe 70 / a drift account without VAT -70 into last year, holding the
        // carry's lock, while the day close of till 25 with a sale from last year (40 + 10 VAT) waits for it. After its
        // commit the page recalculates last year (genberegn(), which rewrites every kontoplan row of the year and then the
        // year's RA row), and again where it shows the posted journal. It let go of the lock right after the commit, so the
        // close took its snapshot during that recalculation and its own posting into last year (kontoplan.saldo) failed on
        // PostgreSQL's serialization error: the database error box and a mail (4 of 4 runs in review round 3).
        // testADayCloseStartedWhileAJournalIntoLastYearCommitsWaitsForItAndPostsOnTopOfIt posts the journal without them.
        // A test session holds last year's RA row, so the page's first recalculation stops there: the close must still wait
        // for the lock then, get it only when the page lets go of it as its last statement, and post on top of the journal.
        $lastYear = (date('Y') - 1) . '-12-31';
        $primo = $this->settledPrimo();
        $old = $this->sale(25, 40, [['Kontant', 50]], false, $lastYear);
        $drift = CharacterizationEnv::one(self::$tenant, "SELECT min(kontonr) AS k FROM kontoplan WHERE regnskabsaar = $1 AND kontotype = 'D'
            AND COALESCE(moms, '') = '' AND COALESCE(valuta, 0) = 0 AND kontonr <> ALL($2::int[])", [self::$openYear, '{' . implode(',', [self::SALES,
            self::COMMISSION, self::MARGIN_SALES, self::ACTIVE_YEAR_SALES, self::ACTIVE_YEAR_COMMISSION]) . '}']);
        self::assertGreaterThan(0, (int)$drift['k'], 'last year\'s chart has a drift account without VAT');
        $drift = (int)$drift['k'];
        $kladde = CharacterizationEnv::nextId(self::$tenant, 'kladdeliste');
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO kladdeliste (id, kladdedate, bogfort, kladdenote, oprettet_af) VALUES ($1, CURRENT_DATE, '', 'chartest', 'chartest')", [$kladde]);
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, amount, kladde_id)
            VALUES (1, $1, 'chartest journal into last year', 'F', $2, 'F', $3, 70, $4)", [$lastYear, self::SAFE, $drift, $kladde]);
        // The page's session posts into last year (online.regnskabsaar), as a bookkeeper who switched year does.
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        $session = [CharacterizationEnv::sessionId(), CharacterizationEnv::testDb()];
        $sessionYear = CharacterizationEnv::one($master, 'SELECT regnskabsaar FROM online WHERE session_id = $1 AND db = $2', $session)['regnskabsaar'];
        CharacterizationEnv::rows($master, 'UPDATE online SET regnskabsaar = $1 WHERE session_id = $2 AND db = $3', array_merge([(string)self::$openYear], $session));
        $journalRow = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        $yearRow = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        $page = $close = $failure = null;
        try {
            // Parked inside its transaction: the page holds the carry's lock and waits for the journal's row.
            CharacterizationEnv::rows($journalRow, 'BEGIN');
            CharacterizationEnv::rows($journalRow, 'SELECT bogfort FROM kladdeliste WHERE id = $1 FOR UPDATE', [$kladde]);
            $page = $this->startJournalPage($kladde);
            $parked = $this->lockWaiter('%kladdeliste%for update%', 60.0);
            self::assertNotNull($parked, 'the page waits for the journal\'s row inside its transaction');
            $pagePid = (int)$parked['pid'];
            self::assertSame('1', CharacterizationEnv::one(self::$tenant, "SELECT count(*) AS n FROM pg_locks WHERE locktype = 'advisory' AND granted AND pid = $1", [$pagePid])['n'],
                'the page holds the carry\'s lock');
            // Last year's RA row, which genberegn() writes after the year's kontoplan rows; the page's own reads of it are done.
            CharacterizationEnv::rows($yearRow, 'BEGIN');
            CharacterizationEnv::rows($yearRow, "SELECT box6 FROM grupper WHERE art = 'RA' AND kodenr = $1 FOR UPDATE", [(string)self::$openYear]);
            $close = $this->startRunner(['close', '25', $this->regnstart(), '0'], 'begin');
            $waiting = $this->lockWaiter('%pg_advisory_lock(%', 60.0);
            self::assertNotNull($waiting, 'the close waits for the carry\'s lock before its transaction');
            $closeWaitPid = (int)$waiting['pid'];

            CharacterizationEnv::rows($journalRow, 'COMMIT');
            $recalculating = $this->lockWaiter('%update grupper set box6%', 60.0);
            self::assertNotNull($recalculating, 'the page posts, commits and recalculates last year up to its RA row');
            self::assertSame($pagePid, (int)$recalculating['pid']);
            self::assertSame('V', trim((string)CharacterizationEnv::one(self::$tenant, 'SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladde])['bogfort']), 'the journal is committed');
            $stillWaiting = $this->lockWaiter('%pg_advisory_lock(%', 5.0);
            self::assertNotNull($stillWaiting, 'while the page recalculates last year, the close still waits for the carry\'s lock');
            self::assertSame($closeWaitPid, (int)$stillWaiting['pid']);

            CharacterizationEnv::rows($yearRow, 'COMMIT');
            $this->held($close);
            $pageNow = CharacterizationEnv::one(self::$tenant, 'SELECT state, query FROM pg_stat_activity WHERE pid = $1', [$pagePid]);
            self::assertTrue($pageNow === null || str_contains((string)$pageNow['query'], 'pg_advisory_unlock'),
                'the close gets the lock when the page lets go of it, after its last recalculation of last year: ' . json_encode($pageNow));
        } catch (\Throwable $e) {
            $failure = $e;
        } finally {
            @pg_query($journalRow, 'ROLLBACK');
            @pg_query($yearRow, 'ROLLBACK');
            pg_close($journalRow);
            pg_close($yearRow);
            if ($close) {
                self::release($close);
            }
            CharacterizationEnv::rows($master, 'UPDATE online SET regnskabsaar = $1 WHERE session_id = $2 AND db = $3', array_merge([$sessionYear], $session));
            pg_close($master);
        }
        $journal = $page ? $this->finishJournalPage($page) : null;
        $closed = $close ? $this->finish($close) : null;
        if ($failure) {
            throw $failure;
        }

        self::assertSame([], $journal['alerts'], json_encode($journal));
        self::assertSame('', $journal['mail']);
        self::assertPostings([self::SAFE => 70.0, $drift => -70.0], array_map(static fn(array $r): float => round((float)$r['net'], 2),
            array_column(CharacterizationEnv::rows(self::$tenant, 'SELECT kontonr, sum(COALESCE(debet, 0)) - sum(COALESCE(kredit, 0)) AS net FROM transaktioner WHERE kladde_id = $1 GROUP BY kontonr', [$kladde]), null, 'kontonr')));
        self::assertFalse($closed['exit'], 'the close goes through: ' . json_encode($closed));
        self::assertTrue($closed['posbogfor']);
        self::assertSame([], $closed['alerts'] ?? []);
        self::assertSame([], $closed['plainAlerts'] ?? [], 'no database error box');
        self::assertSame('', $closed['mail'], 'no error mail');
        self::assertSame(4, $this->orderStatus($old));
        self::assertPostings([self::TILL_25 => 50.0, self::SALES => -40.0, self::$vatAccount => -10.0], $this->postings($old));
        // Both are in the active year's opening: safe 70, till 25 50, VAT -10, and last year's profit 70 + 40 in equity.
        self::assertSame(self::primoPlus($primo, [self::SAFE => 70.0, self::TILL_25 => 50.0, self::$vatAccount => -10.0, self::RETAINED => -110.0]),
            $this->activePrimo());
        // Last year's saldo of the till account holds the close's posting on top of the page's recalculation.
        $till = CharacterizationEnv::one(self::$tenant, "SELECT k.saldo, k.primo + COALESCE((SELECT sum(COALESCE(t.debet, 0)) - sum(COALESCE(t.kredit, 0))
            FROM transaktioner t WHERE t.kontonr = k.kontonr AND t.transdate BETWEEN $2 AND $3), 0) AS posted FROM kontoplan k WHERE k.regnskabsaar = $1 AND k.kontonr = $4",
            [self::$openYear, (date('Y') - 1) . '-01-01', $lastYear, self::TILL_25]);
        self::assertSame(round((float)$till['posted'], 2), round((float)$till['saldo'], 2), 'last year\'s saldo of till 25');
    }

    public function testADayCloseWhileANewFiscalYearIsCreatedCarriesItsSaleIntoTheNewYearToo(): void
    {
        // p1-pos-close-R1 with a year created meanwhile (review round 1): Indstillinger » Regnskabsår » Opret creates the
        // year after the active one (settings_fiscal_year_create(): the year, its groups, its chart and the carry that places
        // its opening) and is about to commit when the day close of till 24 starts with a sale from last year. Before: the
        // close's snapshot did not have the new year, so it carried last year into the active year only, and the new year
        // opened without the sale (till 24, VAT and retained earnings short by 125, -25 and -100) without a word. Now the
        // close waits for the creation before its transaction, and carries through the new year too.
        $lastYear = (date('Y') - 1) . '-12-31';
        $primo = $this->settledPrimo();
        $old = $this->sale(24, 100, [['Kontant', 125]], false, $lastYear);
        $next = self::$year + 1;

        $create = $this->startRunner(['create'], true);
        $close = null;
        try {
            $createPid = $this->held($create);
            $close = $this->startRunner(['close', '24', $this->regnstart(), '0'], false);
            $waiting = $this->waitingQuery([$createPid]);
        } finally {
            self::release($create);
        }
        $created = $this->finish($create);
        $closed = $this->finish($close);
        try {
            self::assertFalse($created['exit'], json_encode($created));
            self::assertNull($created['create']['error'], json_encode($created));
            self::assertSame($next, $created['create']['kodenr']);
            self::assertFalse($closed['exit'], json_encode($closed));
            self::assertTrue($closed['posbogfor']);
            self::assertSame(4, $this->orderStatus($old));
            $active = $this->activePrimo();
            self::assertSame(self::primoPlus($primo, [self::TILL_24 => 125.0, self::$vatAccount => -25.0, self::RETAINED => -100.0]), $active);
            // The new year opens with the sale: till 24 has no postings in the active year, so it opens with its opening
            // there, and a carry of the active year now changes nothing.
            $nextYear = $this->primoOf($next);
            self::assertSame($active[self::TILL_24], $nextYear[self::TILL_24]);
            $carry = $this->runner('carry', (string)self::$year);
            self::assertSame([$next], $carry['updated'], json_encode($carry));
            self::assertSame($nextYear, $this->primoOf($next), 'the new year\'s opening is what a carry of the active year writes');
            self::assertNotNull($waiting, 'the close waited for the creation');
            self::assertStringContainsString('pg_advisory_lock', (string)$waiting, 'at the carry\'s lock, before its transaction');
        } finally {
            $this->dropYear($next);
        }
    }

    public function testAJournalCarryIntoLastYearAndAnInvoiceRecalculatingTheActiveYearBothGoThrough(): void
    {
        // Review round 1: an invoice runs genberegn() of the active year inside its transaction (includes/ordrefunc.php
        // bogfor() -> bogfor_nu()), which updates the year's kontoplan rows and then the year's grupper RA row. A journal
        // posted into last year carries into the active year meanwhile and waits for the invoice's kontoplan rows. The
        // first attempt at p1-pos-close-R1 locked the RA rows: the journal held the active year's RA row while it waited,
        // the invoice then waited for that row, and PostgreSQL ended one of them (deadlock detected). The carry's lock is
        // no row now: the journal waits until the invoice has committed, and both go through, as they did without a lock.
        $lastYear = (date('Y') - 1) . '-06-30';
        $primo = $this->settledPrimo();
        $journalPostings = json_encode([[self::SAFE, 70, 0, $lastYear], [$this->journalDriftAccount(), 0, 70, $lastYear]]);

        $invoice = $this->startRunner(['genberegn', (string)self::$year], true);
        $journal = null;
        try {
            $invoicePid = $this->held($invoice);
            $journal = $this->startRunner(['carry', (string)self::$openYear, $journalPostings], false);
            $waiting = $this->waitingQuery([$invoicePid]);
        } finally {
            self::release($invoice);
        }
        $recalculated = $this->finish($invoice);
        $carried = $this->finish($journal);

        self::assertNotNull($waiting, 'the journal\'s carry waited for the invoice');
        self::assertStringContainsString('update kontoplan', strtolower((string)$waiting), 'on the active year\'s kontoplan rows');
        self::assertFalse($recalculated['exit'], 'the invoice\'s genberegn() goes through: ' . json_encode($recalculated));
        self::assertFalse($carried['exit'], 'the journal goes through: ' . json_encode($carried));
        self::assertSame([self::$year], $carried['updated'], json_encode($carried));
        self::assertSame(self::primoPlus($primo, [self::SAFE => 70.0, self::RETAINED => -70.0]), $this->activePrimo());
    }

    public function testACarryThatReadsFromASnapshotAndWaitedForAnotherSessionsCarryIsRefused(): void
    {
        // The carry's lock taken inside a transaction that reads one snapshot (REPEATABLE READ, as every transaction on
        // MySQL and MariaDB): the day close of till 24 holds the lock with a sale from last year (125), and a journal into
        // last year in REPEATABLE READ reaches its carry meanwhile. Its snapshot misses the close, so after the wait it is
        // refused and writes nothing; posted again, it carries from what the close committed. finans/bogfor.php does not
        // catch the refusal, so the carry rolls the journal back itself and tells the bookkeeper to post again (review
        // round 2: it was an uncaught exception, a PHP fatal error). Before: it carried from its snapshot, waited on the
        // close's rows and failed on PostgreSQL's serialization error with the generic error box (on MySQL and MariaDB it
        // wrote its openings over the close's).
        $lastYear = (date('Y') - 1) . '-12-31';
        $primo = $this->settledPrimo();
        $old = $this->sale(24, 100, [['Kontant', 125]], false, $lastYear);
        $journalPostings = json_encode([[self::SAFE, 20, 0, $lastYear], [$this->journalDriftAccount(), 0, 20, $lastYear]]);
        $rows = $this->journalRows();

        $close = $this->startRunner(['close', '24', $this->regnstart(), '0'], true);
        $journal = null;
        try {
            $closePid = $this->held($close);
            $journal = $this->startRunner(['carry', (string)self::$openYear, $journalPostings, 'rr'], false);
            $waiting = $this->waitingQuery([$closePid]);
        } finally {
            self::release($close);
        }
        $closed = $this->finish($close);
        $refused = $this->finish($journal);

        self::assertFalse($closed['exit'], json_encode($closed));
        self::assertSame(4, $this->orderStatus($old));
        self::assertTrue($refused['exit'], 'the journal ends with the carry\'s message: ' . json_encode($refused));
        self::assertSame([self::JOURNAL_CONFLICT_ALERT], $refused['alerts'] ?? [], json_encode($refused));
        self::assertSame($rows, $this->journalRows(), 'the journal\'s rows are rolled back');
        $afterClose = self::primoPlus($primo, [self::TILL_24 => 125.0, self::$vatAccount => -25.0, self::RETAINED => -100.0]);
        self::assertSame($afterClose, $this->activePrimo(), 'the close\'s carry stands');
        self::assertNotNull($waiting, 'the journal\'s carry waited for the day close');
        self::assertStringContainsString('pg_advisory_xact_lock', (string)$waiting);

        // Posted again, the journal carries its 20 on top (had the refused one kept its rows, this would carry 40).
        $again = $this->runner('carry', (string)self::$openYear, $journalPostings, 'rr');
        self::assertSame([self::$year], $again['updated'], json_encode($again));
        self::assertSame(self::primoPlus($afterClose, [self::SAFE => 20.0, self::RETAINED => -20.0]), $this->activePrimo());
    }

    public function testAJournalThatDeadlocksWithADayCloseHoldingTheCarrysLockIsRolledBackWithAMessage(): void
    {
        // Review round 2: the day close of till 25 takes the carry's lock before its transaction and holds there (hold
        // 'begin') with a sale from last year (till 125 / sales 100 / VAT 25). A journal into last year is posted as
        // finans/bogfor.php posts it, on the same sales and VAT accounts (safe 30, sales -24, VAT -6: the rows, then
        // kontoplan.saldo), and waits for the lock at its carry while it holds those kontoplan rows. The close then posts
        // its sale on the same rows, and PostgreSQL ends one of the two (deadlock detected). The journal's session checks
        // first here (deadlock_timeout 3 s against the close's 60 s), so the journal is the one ended. finans/bogfor.php
        // does not catch the carry's conflict: before, it ended in a PHP fatal error (an empty page, no word that nothing
        // was posted). Now the carry rolls the journal back and tells the bookkeeper to post again. The close posts and
        // carries, and the journal posted again carries on top of it.
        $lastYear = (date('Y') - 1) . '-12-31';
        $primo = $this->settledPrimo();
        $old = $this->sale(25, 100, [['Kontant', 125]], false, $lastYear);
        $journalPostings = json_encode([[self::SAFE, 30, 0, $lastYear], [self::SALES, 0, 24, $lastYear], [self::$vatAccount, 0, 6, $lastYear]]);
        $rows = $this->journalRows();

        $close = $this->startRunner(['close', '25', $this->regnstart(), '0'], 'begin', ['deadlock_timeout' => '60s']);
        $journal = null;
        try {
            $closePid = $this->held($close);
            $journal = $this->startRunner(['carry', (string)self::$openYear, $journalPostings], false, ['deadlock_timeout' => '3s']);
            $waiting = $this->waitingQuery([$closePid]);
        } finally {
            self::release($close);
        }
        $closed = $this->finish($close);
        $carried = $this->finish($journal);

        self::assertNotNull($waiting, 'the journal\'s carry waited for the close\'s lock');
        self::assertStringContainsString('pg_advisory_xact_lock', (string)$waiting);
        self::assertTrue($carried['exit'], 'the journal ends with the carry\'s message: ' . json_encode($carried));
        self::assertSame([self::JOURNAL_CONFLICT_ALERT], $carried['alerts'] ?? [], json_encode($carried));
        self::assertSame($rows, $this->journalRows(), 'nothing of the journal is written');
        self::assertFalse($closed['exit'], 'the close goes through: ' . json_encode($closed));
        self::assertTrue($closed['posbogfor']);
        self::assertSame([], $closed['alerts'] ?? []);
        self::assertSame(4, $this->orderStatus($old));
        self::assertPostings([self::TILL_25 => 125.0, self::SALES => -100.0, self::$vatAccount => -25.0], $this->postings($old));
        $afterClose = self::primoPlus($primo, [self::TILL_25 => 125.0, self::$vatAccount => -25.0, self::RETAINED => -100.0]);
        self::assertSame($afterClose, $this->activePrimo());

        // Posted again, the journal goes through and carries on top of the close: safe 30, VAT -6, last year's profit 24.
        $again = $this->runner('carry', (string)self::$openYear, $journalPostings);
        self::assertFalse($again['exit'], json_encode($again));
        self::assertSame([self::$year], $again['updated'], json_encode($again));
        self::assertSame($rows + 3, $this->journalRows());
        self::assertSame(self::primoPlus($afterClose, [self::SAFE => 30.0, self::$vatAccount => -6.0, self::RETAINED => -24.0]),
            $this->activePrimo());
    }

    public function testANewFiscalYearWhoseCarryLockIsRefusedCreatesNothingAndSaysSo(): void
    {
        // Review round 2: Indstillinger » Regnskabsår » Opret (settings_fiscal_year_create()) and Gem on the year card take
        // the carry's lock before their transaction and did not catch a refusal, so a lock the database refused ended in a
        // PHP fatal error. Here the day close of till 24 holds the lock before its transaction (a sale from last year), and
        // Opret runs with a lock timeout of 300 ms, so the database refuses it the lock. Now Opret ends with a message that
        // nothing is saved, and creates no year. The close then goes through.
        $lastYear = (date('Y') - 1) . '-12-31';
        $primo = $this->settledPrimo();
        $old = $this->sale(24, 100, [['Kontant', 125]], false, $lastYear);
        $next = self::$year + 1;

        $close = $this->startRunner(['close', '24', $this->regnstart(), '0'], 'begin');
        $created = null;
        try {
            $this->held($close);
            $create = $this->startRunner(['create'], false, ['lock_timeout' => '300ms']);
            $created = $this->finish($create, 30.0);
        } finally {
            self::release($close);
        }
        $closed = $this->finish($close);
        try {
            self::assertTrue($created['exit'], 'Opret ends with the message: ' . json_encode($created));
            self::assertSame([self::SAVE_CONFLICT_ALERT], $created['alerts'] ?? [], json_encode($created));
            self::assertNull(CharacterizationEnv::one(self::$tenant, "SELECT id FROM grupper WHERE art = 'RA' AND kodenr = $1", [$next]),
                'no new fiscal year');
            self::assertSame([], $this->primoOf($next), 'no chart for it');
            self::assertFalse($closed['exit'], json_encode($closed));
            self::assertTrue($closed['posbogfor']);
            self::assertSame(4, $this->orderStatus($old));
            self::assertSame(self::primoPlus($primo, [self::TILL_24 => 125.0, self::$vatAccount => -25.0, self::RETAINED => -100.0]),
                $this->activePrimo());
        } finally {
            $this->dropYear($next);
        }
    }

    public function testASaleFromLastYearThatComesAfterTheClosesCheckIsNotCarriedWithoutTheLock(): void
    {
        // The close takes the carry's lock before its transaction only when it finds a sale from an earlier fiscal year
        // (posCloseCarries()). A sale from last year that comes between that check and the transaction is not carried
        // without the lock: nothing is posted, and approved again the close takes the lock first. Before: there was no
        // such check.
        $lastYear = (date('Y') - 1) . '-12-31';
        $primo = $this->settledPrimo();
        $new = $this->sale(25, 16, [['Kontant', 20]], false);

        $close = $this->startRunner(['close', '25', $this->regnstart(), '0'], 'begin');
        try {
            $this->held($close);
            $old = $this->sale(25, 100, [['Kontant', 125]], false, $lastYear);
        } finally {
            self::release($close);
        }
        $closed = $this->finish($close);

        self::assertTrue($closed['exit'], 'the close is rolled back: ' . json_encode($closed));
        $alert = (string)($closed['alerts'][0] ?? '');
        self::assertStringContainsString("\n31-12-" . (date('Y') - 1) . ': salget fra et tidligere regnskabsår kom til, mens kasseoptællingen blev godkendt, '
            . 'så det kunne ikke føres sikkert til primo i det aktive regnskabsår.', $alert, json_encode($closed));
        self::assertStringEndsWith("\nIntet er bogført. Godkend kasseoptællingen igen.", $alert);
        self::assertSame([], $this->postings($old), 'nothing is posted');
        self::assertSame([], $this->postings($new));
        self::assertSame(3, $this->orderStatus($old));
        self::assertSame(3, $this->orderStatus($new));
        self::assertSame($primo, $this->activePrimo());

        $again = $this->runner('close', '25', $this->regnstart(), '0');
        self::assertFalse($again['exit'], 'approved again, the close goes through: ' . json_encode($again));
        self::assertSame([], $again['alerts'] ?? []);
        self::assertSame(4, $this->orderStatus($old));
        self::assertSame(4, $this->orderStatus($new));
        self::assertPostings([self::TILL_25 => 125.0, self::SALES => -100.0, self::$vatAccount => -25.0], $this->postings($old));
        self::assertSame(self::primoPlus($primo, [self::TILL_25 => 125.0, self::$vatAccount => -25.0, self::RETAINED => -100.0]),
            $this->activePrimo());
    }

    public function testAPostingIntoTheActiveYearDuringACloseThatCarriesALastYearSaleAsksTheClerkToApproveAgain(): void
    {
        // Review round 3 (accounting): till 24 has an unposted cash sale dated 31-12 last year (125 = 100 + 25 VAT). A journal
        // into the ACTIVE year (safe 10 / a drift account -10, the rows and kontoplan.saldo, as finans/bogfor.php posts it) is
        // about to commit when the close starts. A posting into the newest year has nothing to carry and takes no lock, so
        // the close takes the carry's lock, begins its REPEATABLE READ snapshot, posts the sale in last year and carries it:
        // the carry and genberegn() write the active year's kontoplan rows, the safe's among them, which the journal holds.
        // The journal commits. Before: PostgreSQL refused the close's write ('could not serialize access due to concurrent
        // update', on the carry's primo update), and db_modify() ended the close with the generic 'Uforudset hændelse,
        // kontakt salditeamet på telefon 4690 2208' and a mail to fejl@saldi.dk. The numbers stayed right (rolled back,
        // approved again it posts), but the clerk was not told to approve again. Now the carry locks those rows first, and
        // the conflict rolls the close back with the close's message to approve the count again.
        $lastYear = (date('Y') - 1) . '-12-31';
        $today = date('Y-m-d');
        $primo = $this->settledPrimo();
        $old = $this->sale(24, 100, [['Kontant', 125]], false, $lastYear);
        $journalPostings = json_encode([[self::SAFE, 10, 0, $today], [$this->journalDriftAccount(), 0, 10, $today]]);
        $rows = $this->journalRows();

        $journal = $this->startRunner(['carry', (string)self::$year, $journalPostings], true);
        $close = null;
        try {
            $journalPid = $this->held($journal);
            $close = $this->startRunner(['close', '24', $this->regnstart(), '0'], false);
            $waiting = $this->waitingQuery([$journalPid]);
        } finally {
            self::release($journal);
        }
        $posted = $this->finish($journal);
        $closed = $this->finish($close);

        self::assertFalse($posted['exit'], 'the journal into the active year goes through: ' . json_encode($posted));
        self::assertSame([], $posted['updated'], 'it has no following year to carry into');
        self::assertSame($rows + 2, $this->journalRows());
        self::assertTrue($closed['exit'], 'the close is rolled back: ' . json_encode($closed, JSON_UNESCAPED_UNICODE));
        self::assertSame('', $closed['mail'], 'no error mail to fejl@saldi.dk');
        self::assertSame([], $closed['plainAlerts'] ?? [], 'not the database error box');
        self::assertSame(["Kasseoptællingen er ikke bogført. Kasse 24 har ikke-bogførte salg, som ikke kan bogføres nu:\n31-12-" . (date('Y') - 1) . ': '
            . 'salgene fra et tidligere regnskabsår kunne ikke føres sikkert til primo i det aktive regnskabsår, fordi en anden bruger samtidig bogførte, '
            . "godkendte en kasseoptælling, gemte en åbningsbalance eller oprettede et regnskabsår.\nIntet er bogført. Godkend kasseoptællingen igen."],
            $closed['alerts'] ?? [], json_encode($closed, JSON_UNESCAPED_UNICODE));
        self::assertSame([], $this->postings($old), 'nothing is posted');
        self::assertSame(3, $this->orderStatus($old));
        self::assertSame($primo, $this->activePrimo(), 'the opening is as it was');
        // The close waited for the journal at the lock of the active year's rows, before the carry's first write.
        self::assertNotNull($waiting, 'the close waited for the journal');
        self::assertStringContainsString('for update', strtolower((string)$waiting));

        // Approved again, the close posts the sale and carries it on top of the journal.
        $again = $this->runner('close', '24', $this->regnstart(), '0');
        self::assertFalse($again['exit'], 'approved again, the close goes through: ' . json_encode($again, JSON_UNESCAPED_UNICODE));
        self::assertSame([], $again['alerts'] ?? []);
        self::assertSame(4, $this->orderStatus($old));
        self::assertPostings([self::TILL_24 => 125.0, self::SALES => -100.0, self::$vatAccount => -25.0], $this->postings($old));
        self::assertSame(self::primoPlus($primo, [self::TILL_24 => 125.0, self::$vatAccount => -25.0, self::RETAINED => -100.0]),
            $this->activePrimo());
    }
}
