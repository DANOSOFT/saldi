<?php
// tests/characterization/finans/MomsRubrikLedgerTest.test.php
//
// Regression test for audit finding reports R-2 (integration 2026-11-14):
// Momsrubrikker (finans/rapport_includes/moms_rubrik.php) built boxes A, B and
// C from order lines only. Orders that were invoiced but not posted counted,
// and EU purchases and sales posted through the kassekladde did not, although
// their reverse-charge VAT is in Momsangivelse.
//
// The boxes now hold what is posted in the period: orders with status 4 and
// postings that carry the order id, plus the postings without an order on the
// rubrik accounts of Indstillinger > Moms (grupper art='MR' box3..box7).
//
// On one day the test
// - invoices an EU sale of 300 with "Bogfør straks" on (posted to 1050),
// - invoices an EU sale of 200 with "Bogfør straks" off (status 3, not posted),
// - invoices an EU sale of 350 with it off and gives it the status Hent ordrer
//   leaves when it moves an invoice to a journal that is not posted yet
//   (status 4, no postings),
// - adds an EU goods purchase of 1.000 that is posted (status 4, with its
//   acquisition VAT) and one of 600 that is not (status 3),
// - posts a journal through finans/bogfor.php with an EU goods sale of 500 on
//   1050 and an EU service purchase of 400 on 1710 (reverse-charge VAT 100 on
//   7720/7740 added by the posting),
// and checks the boxes by value, against the ledger and against the VAT lines
// of Momsangivelse for the same day.
//
// On the next day it posts a second journal (an EU service sale of 700 on the
// box B services account 1150, an EU goods purchase of 1.000 on 1630 and a
// sale of 300 outside the EU on 1200) and reads it with MR box4 pointed at
// 1150 and box5/box6 empty, a setup some ledgers use (EuSalesListKindTest
// WORKAROUND): the sale carries no reverse-charge VAT, so box A leaves it out.
//
// On the 14th it posts a journal whose lines carry their own VAT code
// (debetvat/kreditvat, as the kassekladde and the dokumentpulje save them)
// while the box A services account 1710 has no VAT code of its own: an EU
// service purchase of 400 (Y1), a domestic purchase on 1710 (K1), an EU credit
// note of 100 (Y1), a posting without VAT (Momsfri) on the box A goods account
// 1630 and a sale on 1050 charged with Danish VAT (S1). The ledger does not
// keep the line's code, so box A counts the postings that carry reverse-charge
// VAT and box B no sale charged with VAT.
//
// On the 15th it posts EU purchases without VAT (Momsfri) on 1630 and 1710
// with the reverse-charge VAT typed by hand on lines of their own in the same
// voucher (7740 against 7700 or 7720), as bookkeepers with partial VAT
// deduction and imported journals book them, and an EU credit note booked the
// same way. Box A counts them, whether or not the accounts have a VAT code.
//
// On the 16th it posts two vouchers with a posting without VAT on 1710 next to
// a line whose VAT the posting computed: the reverse-charge VAT in the voucher
// belongs to that other line, so the posting without VAT is not counted.
//
// On the 17th, 18th and 19th it posts vouchers where two postings carry the same
// VAT and only one of them is reverse-charge VAT (audit finding p1-reports-F1):
// an EU service purchase of 400 (Y1) and a domestic purchase of 500 incl. VAT
// (K1) on 1710, a domestic purchase of 500 incl. VAT on 1630 (K1) beside an EU
// service purchase of 400 on 1710, and an EU service purchase of 400 on 1710
// beside an EU goods purchase of 400 on 1630 without VAT with its acquisition
// VAT of 100 typed by hand. Box A pairs each posting with one acquisition VAT
// posting of the voucher, so it holds 25 % of the VAT on 7700/7720 each day.
//
// On the 20th, 21st and 22nd it posts journals whose lines carry VAT codes, in
// which the pairing by VAT let a domestic purchase take another line's EU VAT
// (audit p1r2 of p1-reports-F1): an EU service purchase of 400 (Y1) in
// department 1 beside a domestic purchase of 500 incl. VAT (K1) in department 2,
// an EU service purchase of 400 (Y1) on 2510, outside box A, beside a domestic
// purchase of 500 incl. VAT on 1710, and a domestic purchase of 500,01 incl.
// VAT (net 400,01) beside an EU service purchase of 399,99, both with VAT 100.
// Box A reads those journals by their lines: a posting counts for each line of
// its journal, bilag, date, account, department and side that the posting
// reverse-charged and that has its net amount. The same journals hold a
// purchase from a creditor whose group has the EU code E1 (line code K1, which
// the posting reverse-charges for that group) and an EU purchase without VAT
// with its acquisition VAT typed by hand, which count as before. The journals
// of the 12th, 13th, 15th and 19th have no line VAT codes (as a journal posted
// before kassekladde had them) and keep the pairing by VAT.
//
// On the 23rd it posts journal lines whose saved codes leave the VAT to the
// account's code or the creditor's group (audit p1r3 finding p1-reports-F1c):
// a purchase of 800 on 1630 (K1) from a creditor in an EU group (E1), beside a
// purchase of 1.000 incl. VAT on 1630 (K1) from a creditor in a domestic group
// (K1), both net 800 with VAT 200; a purchase of 400 on 1710 without codes,
// posted with 1710's code Y1; and, in a journal of its own posted while 1710
// had no VAT code, a purchase of 400 on 1710 without codes and without VAT.
// Box A reads such lines by their postings, so swapping the creditors' groups
// and giving 1710 the code K1 afterwards changes nothing, and the purchase
// posted without VAT does not count when 1710 has its code Y1 again.
//
// Under its table the page lists, at "Konti uden ordre", the accounts that
// postings without an order count on, by rubrik and with their names. The
// help points there, since Indstillinger > Moms shows only the box A accounts.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261117 CL/ASR Created.
// 20261117 CL/ASR A sales account in MR box4 is not counted in box A.
//                 A journal line's own VAT code: box A counts the postings with reverse-charge VAT, box B no sale charged with VAT.
//                 A Momsfri EU purchase with the reverse-charge VAT typed by hand in its voucher counts in box A; one beside another line's computed reverse charge does not.
// 20261119 CL/ASR Vouchers where two postings carry the same VAT and only one of them is reverse-charge VAT count that one in box A (audit finding p1-reports-F1).
// 20261119 CL/ASR Journals with line VAT codes: box A by the journal lines with a department filter, an EU purchase outside box A and equal VAT with different nets (audit p1r2 of p1-reports-F1); an EU creditor group and VAT typed by hand still count.
// 20261119 CL/ASR The page lists the accounts that postings without an order count on (Konti uden ordre), which the help points to, as Indstillinger > Moms shows only the box A accounts.
// 20261120 CL/ASR Journal lines whose VAT the account's code or a creditor's group decided keep their box A amount when those change after posting, a line posted without VAT does not count when the account gets an EU code (audit p1r3 finding p1-reports-F1c), and the footnote states the limit of journals without VAT codes.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class MomsRubrikLedgerTest extends CharacterizationTestCase
{
    /** The rubrik rows, in the order the screen lists them. */
    private const RUBRIKKER = ['A-varer', 'A-ydelser', 'B-varer', 'B-ydelser', 'C'];
    private const EU_CVR = 'DE123456789';

    /** The day everything is invoiced and posted on (Y-m-d), the 12th of the fiscal year's first month. */
    private static string $day = '';
    /** The day of the second journal (Y-m-d), the 13th. */
    private static string $nextDay = '';
    /** The day of the journal with line VAT codes (Y-m-d), the 14th. */
    private static string $lineVatDay = '';
    private static int $lineVatJournal = 0;
    /** The day of the journal with the reverse-charge VAT typed by hand (Y-m-d), the 15th. */
    private static string $manualVatDay = '';
    private static int $manualVatJournal = 0;
    /** The day of the journal with postings without VAT beside a computed reverse charge (Y-m-d), the 16th. */
    private static string $sideLineDay = '';
    private static int $sideLineJournal = 0;
    /** The days of the vouchers with two postings that carry the same VAT (Y-m-d): the 17th, 18th and 19th. */
    private static string $equalVatDay = '';
    private static string $otherBoxDay = '';
    private static string $typedBesideDay = '';
    /** @var array<string, int> the journals of those days, by day */
    private static array $sameVatJournals = [];
    /** The days of the journals with line VAT codes that the pairing by VAT misread (Y-m-d): the 20th, 21st and 22nd. */
    private static string $departmentDay = '';
    private static string $outsideDay = '';
    private static string $roundedDay = '';
    /** @var array<string, int> the journals of those days, by day */
    private static array $lineJournals = [];
    /** The collective account of the creditor group with the EU code E1. */
    private static int $euCreditorAccount = 0;
    /** The day of the journals whose lines leave the VAT to the account's code or the creditor's group (Y-m-d), the 23rd. */
    private static string $changedDay = '';
    /** @var array{codes?:int, none?:int} the journals of that day: posted while 1710 had its code Y1, and while it had none */
    private static array $changedJournals = [];
    /** @var array<string, array{id:int, kontonr:string, gruppe:int}> the creditors of that day ('eu', 'domestic') with the group they were posted with */
    private static array $changedCreditors = [];
    /** @var array{box3:string, box4:string, box5:string, box6:string, box7:string} the MR rubrik accounts of the installed tenant */
    private static array $mr = [];
    /** @var array<string, int> the test's orders by what happened to them */
    private static array $orders = [];
    private static int $journal = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        CharacterizationEnv::resyncSequences(self::$tenant);
        // The report writes its CSV to temp/<db>/, which the login creates; these requests skip the login.
        $tempDir = CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb();
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0770, true);
        }

        $ra = self::one("SELECT box1, box2 FROM grupper WHERE art = 'RA' AND kodenr = $1", [self::$regnaar]);
        self::$day = sprintf('%04d-%02d-12', (int)$ra['box2'], (int)$ra['box1']);
        self::$nextDay = sprintf('%04d-%02d-13', (int)$ra['box2'], (int)$ra['box1']);
        self::$lineVatDay = sprintf('%04d-%02d-14', (int)$ra['box2'], (int)$ra['box1']);
        self::$manualVatDay = sprintf('%04d-%02d-15', (int)$ra['box2'], (int)$ra['box1']);
        self::$sideLineDay = sprintf('%04d-%02d-16', (int)$ra['box2'], (int)$ra['box1']);
        self::$equalVatDay = sprintf('%04d-%02d-17', (int)$ra['box2'], (int)$ra['box1']);
        self::$otherBoxDay = sprintf('%04d-%02d-18', (int)$ra['box2'], (int)$ra['box1']);
        self::$typedBesideDay = sprintf('%04d-%02d-19', (int)$ra['box2'], (int)$ra['box1']);
        self::$departmentDay = sprintf('%04d-%02d-20', (int)$ra['box2'], (int)$ra['box1']);
        self::$outsideDay = sprintf('%04d-%02d-21', (int)$ra['box2'], (int)$ra['box1']);
        self::$roundedDay = sprintf('%04d-%02d-22', (int)$ra['box2'], (int)$ra['box1']);
        self::$mr = self::one("SELECT box3, box4, box5, box6, box7 FROM grupper WHERE art = 'MR' ORDER BY id LIMIT 1");

        // An EU business customer and supplier: groups with EU-zone B2B-EU and no VAT code, and an EU VAT number,
        // which makes the invoice post the line to the item group's EU sales account (VG box12).
        $customer = self::$fx->debtor();
        self::rows('UPDATE adresser SET gruppe = $2, cvrnr = $3, land = $4 WHERE id = $1', [$customer['id'], self::group('DG'), self::EU_CVR, 'Tyskland']);
        $supplier = self::$fx->creditor();
        self::rows('UPDATE adresser SET gruppe = $2, cvrnr = $3, land = $4 WHERE id = $1', [$supplier['id'], self::group('KG'), 'DE987654321', 'Tyskland']);
        // A Handelsvarer item: its group's EU sales account (box12) is the MR box B goods account 1050.
        $item = self::$fx->item();

        // Posted at once ("Bogfør straks" is on in the installed tenant).
        self::$orders['sale posted'] = self::invoice($customer, $item, 300.00);

        $settings = self::rows("SELECT id, box5 FROM grupper WHERE art = 'DIV' AND kodenr = 3");
        self::rows("UPDATE grupper SET box5 = '' WHERE art = 'DIV' AND kodenr = 3");
        try {
            // Invoiced, waiting for Hent ordrer: status 3.
            self::$orders['sale invoiced'] = self::invoice($customer, $item, 200.00);
            // Moved to a journal by Hent ordrer that is not posted yet. flytordre() sets status 4 and writes the journal
            // lines, not the ledger (HentOrdrerFlytTest pins that); only the status matters here. It is set directly:
            // on PostgreSQL flytordre() stops at its VAT lookup for a debtor group without a VAT code (grupper SM
            // kodenr = ''), which is what an EU business group has.
            self::$orders['sale moved'] = self::invoice($customer, $item, 350.00);
            self::rows('UPDATE ordrer SET status = 4 WHERE id = $1', [self::$orders['sale moved']]);
        } finally {
            foreach ($settings as $row) {
                self::rows('UPDATE grupper SET box5 = $1 WHERE id = $2', [$row['box5'], $row['id']]);
            }
        }

        // EU goods purchases on the MR box A goods account, as kreditor/bogfor.php posts them: one posted with its
        // acquisition VAT (EM 1: 7700 against 7740), one invoiced only.
        self::$orders['purchase posted'] = self::purchase($supplier, $item, 1000.00, true);
        self::$orders['purchase invoiced'] = self::purchase($supplier, $item, 600.00, false);

        // A journal posted through finans/bogfor.php: an EU goods sale to 1050 and an EU service purchase on 1710.
        $journal = self::$journal = self::$fx->kladde([
            ['bilag' => 1, 'transdate' => self::$day, 'debet' => 6480, 'kredit' => (int)self::$mr['box5'], 'amount' => 500.00, 'tekst' => 'EU varesalg'],
            ['bilag' => 2, 'transdate' => self::$day, 'debet' => (int)self::$mr['box4'], 'kredit' => 6480, 'amount' => 400.00, 'tekst' => 'EU ydelseskoeb'],
        ]);
        $res = self::runChild('run_bogfor_page.php', ['bogfor', $journal, CharacterizationEnv::sessionId()]);
        if (!self::rows('SELECT id FROM transaktioner WHERE kladde_id = $1', [$journal])) {
            throw new RuntimeException("the journal was not posted.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
        }

        // The next day: an EU service sale on the box B services account, an EU goods purchase on the box A goods
        // account (VAT code E1: the posting adds 250 on 7700/7740) and a sale outside the EU on the box C account.
        $next = self::$fx->kladde([
            ['bilag' => 3, 'transdate' => self::$nextDay, 'debet' => 6480, 'kredit' => (int)self::$mr['box6'], 'amount' => 700.00, 'tekst' => 'EU ydelsessalg'],
            ['bilag' => 4, 'transdate' => self::$nextDay, 'debet' => (int)self::$mr['box3'], 'kredit' => 6480, 'amount' => 1000.00, 'tekst' => 'EU varekoeb'],
            ['bilag' => 5, 'transdate' => self::$nextDay, 'debet' => 6480, 'kredit' => (int)self::$mr['box7'], 'amount' => 300.00, 'tekst' => 'Ydelsessalg udland'],
        ]);
        $res = self::runChild('run_bogfor_page.php', ['bogfor', $next, CharacterizationEnv::sessionId()]);
        if (!self::rows('SELECT id FROM transaktioner WHERE kladde_id = $1', [$next])) {
            throw new RuntimeException("the second journal was not posted.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
        }

        // The 14th: journal lines with their own VAT code, posted while 1710 (box A services) has no VAT code.
        // finans/kassekladde.php adds the line VAT columns on its first load (ensure_cash_vat_columns()).
        self::rows('ALTER TABLE kassekladde ADD COLUMN IF NOT EXISTS debetvat varchar');
        self::rows('ALTER TABLE kassekladde ADD COLUMN IF NOT EXISTS kreditvat varchar');
        $box3 = (int)self::$mr['box3'];
        $box4 = (int)self::$mr['box4'];
        $box5 = (int)self::$mr['box5'];
        // bilag => [debit account, credit account, amount, debetvat, kreditvat, momsfri, text]
        $lines = [
            6 => [$box4, 6480, 400.00, 'Y1', '', '', 'EU ydelseskoeb, linjens momskode'],
            7 => [$box4, 6480, 500.00, 'K1', '', '', 'Indenlandsk koeb'],
            8 => [6480, $box4, 100.00, '', 'Y1', '', 'EU kreditnota'],
            9 => [$box3, 6480, 200.00, '', '', 'on', 'Momsfri'],
            10 => [6480, $box5, 500.00, '', 'S1', '', 'Salg med dansk moms'],
        ];
        $kladde = [];
        foreach ($lines as $bilag => [$debet, $kredit, $amount, , , $momsfri, $tekst]) {
            $kladde[] = ['bilag' => $bilag, 'transdate' => self::$lineVatDay, 'debet' => $debet, 'kredit' => $kredit, 'amount' => $amount, 'momsfri' => $momsfri, 'tekst' => $tekst];
        }
        $journal = self::$lineVatJournal = self::$fx->kladde($kladde);
        foreach ($lines as $bilag => [, , , $debetvat, $kreditvat]) {
            self::rows('UPDATE kassekladde SET debetvat = $1, kreditvat = $2 WHERE kladde_id = $3 AND bilag = $4', [$debetvat, $kreditvat, $journal, $bilag]);
        }
        $res = self::withAccountVat($box4, '', static fn(): array => self::runChild('run_bogfor_page.php', ['bogfor', $journal, CharacterizationEnv::sessionId()]));
        if (!self::rows('SELECT id FROM transaktioner WHERE kladde_id = $1', [$journal])) {
            throw new RuntimeException("the journal with line VAT codes was not posted.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
        }

        // The 15th: EU purchases posted without VAT (Momsfri), each with its reverse-charge VAT typed by hand on a line
        // of its own in the same voucher, and an EU credit note booked the same way. 1630 and 1710 keep their codes.
        $day = self::$manualVatDay;
        $journal = self::$manualVatJournal = self::$fx->kladde([
            ['bilag' => 11, 'transdate' => $day, 'debet' => $box3, 'kredit' => 6480, 'amount' => 1000.00, 'momsfri' => 'on', 'tekst' => 'EU varekoeb, moms tastet'],
            ['bilag' => 11, 'transdate' => $day, 'debet' => 7740, 'kredit' => 7700, 'amount' => 250.00, 'momsfri' => 'on', 'tekst' => 'Erhvervelsesmoms'],
            ['bilag' => 12, 'transdate' => $day, 'debet' => $box4, 'kredit' => 6480, 'amount' => 800.00, 'momsfri' => 'on', 'tekst' => 'EU ydelseskoeb, moms tastet'],
            ['bilag' => 12, 'transdate' => $day, 'debet' => 7740, 'kredit' => 7720, 'amount' => 200.00, 'momsfri' => 'on', 'tekst' => 'Moms af ydelseskoeb'],
            ['bilag' => 13, 'transdate' => $day, 'debet' => 6480, 'kredit' => $box3, 'amount' => 400.00, 'momsfri' => 'on', 'tekst' => 'EU kreditnota, moms tastet'],
            ['bilag' => 13, 'transdate' => $day, 'debet' => 7700, 'kredit' => 7740, 'amount' => 100.00, 'momsfri' => 'on', 'tekst' => 'Erhvervelsesmoms retur'],
        ]);
        $res = self::runChild('run_bogfor_page.php', ['bogfor', $journal, CharacterizationEnv::sessionId()]);
        if (!self::rows('SELECT id FROM transaktioner WHERE kladde_id = $1', [$journal])) {
            throw new RuntimeException("the journal with hand-typed VAT was not posted.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
        }

        // The 16th: a posting without VAT on 1710 in a voucher whose reverse-charge VAT the posting computed for another
        // line: bilag 14 on 1710 itself (its own code Y1), bilag 15 on the expense account 2510 with the line code Y1.
        $day = self::$sideLineDay;
        $journal = self::$sideLineJournal = self::$fx->kladde([
            ['bilag' => 14, 'transdate' => $day, 'debet' => $box4, 'kredit' => 6480, 'amount' => 400.00, 'tekst' => 'EU ydelseskoeb'],
            ['bilag' => 14, 'transdate' => $day, 'debet' => $box4, 'kredit' => 6480, 'amount' => 50.00, 'momsfri' => 'on', 'tekst' => 'Gebyr uden moms'],
            ['bilag' => 15, 'transdate' => $day, 'debet' => 2510, 'kredit' => 6480, 'amount' => 120.00, 'tekst' => 'EU konsulentydelse'],
            ['bilag' => 15, 'transdate' => $day, 'debet' => $box4, 'kredit' => 6480, 'amount' => 60.00, 'momsfri' => 'on', 'tekst' => 'Gebyr uden moms'],
        ]);
        self::rows("UPDATE kassekladde SET debetvat = 'Y1', kreditvat = '' WHERE kladde_id = $1 AND bilag = 15 AND debet = 2510", [$journal]);
        $res = self::runChild('run_bogfor_page.php', ['bogfor', $journal, CharacterizationEnv::sessionId()]);
        if (!self::rows('SELECT id FROM transaktioner WHERE kladde_id = $1', [$journal])) {
            throw new RuntimeException("the journal with postings beside a computed reverse charge was not posted.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
        }

        // The 17th to 19th: one voucher a day in which two postings carry VAT 100 and only one of them is reverse-charge
        // VAT. Each line is [debit account, amount, debetvat, momsfri, text], against the bank 6480 unless the debit
        // is 7740 (VAT typed by hand against 7700). 1630 keeps its code E1 and 1710 its code Y1; a line code overrides it.
        $days = [
            // An EU service purchase of 400 (Y1) and a domestic purchase of 500 incl. VAT (K1), both on 1710.
            self::$equalVatDay => [16, [[$box4, 400.00, 'Y1', '', 'EU ydelseskoeb'], [$box4, 500.00, 'K1', '', 'Indenlandsk ydelseskoeb']]],
            // A domestic purchase of 500 incl. VAT on 1630 (K1), then an EU service purchase of 400 on 1710.
            self::$otherBoxDay => [17, [[$box3, 500.00, 'K1', '', 'Indenlandsk varekoeb'], [$box4, 400.00, '', '', 'EU ydelseskoeb']]],
            // An EU service purchase of 400 on 1710, and an EU goods purchase of 400 on 1630 without VAT with its
            // acquisition VAT of 100 typed by hand.
            self::$typedBesideDay => [18, [[$box4, 400.00, '', '', 'EU ydelseskoeb'], [$box3, 400.00, '', 'on', 'EU varekoeb, moms tastet'], [7740, 100.00, '', 'on', 'Erhvervelsesmoms']]],
        ];
        foreach ($days as $day => [$bilag, $lines]) {
            $kladde = [];
            foreach ($lines as [$debet, $amount, , $momsfri, $tekst]) {
                $kladde[] = ['bilag' => $bilag, 'transdate' => $day, 'debet' => $debet, 'kredit' => ($debet == 7740) ? 7700 : 6480, 'amount' => $amount, 'momsfri' => $momsfri, 'tekst' => $tekst];
            }
            $journal = self::$sameVatJournals[$day] = self::$fx->kladde($kladde);
            foreach ($lines as [$debet, $amount, $debetvat]) {
                if ($debetvat !== '') {
                    self::rows("UPDATE kassekladde SET debetvat = $1, kreditvat = '' WHERE kladde_id = $2 AND debet = $3 AND amount = $4", [$debetvat, $journal, $debet, $amount]);
                }
            }
            $res = self::runChild('run_bogfor_page.php', ['bogfor', $journal, CharacterizationEnv::sessionId()]);
            if (!self::rows('SELECT id FROM transaktioner WHERE kladde_id = $1', [$journal])) {
                throw new RuntimeException("the journal of $day was not posted.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
            }
        }

        // The 20th to 22nd: journals whose lines carry VAT codes, as the kassekladde saves them. Each line is
        // [bilag, debit account, credit, amount, debetvat, momsfri, department, text]; the credit is an account, or
        // 'K' and a creditor number. kreditvat is blank. 1630 keeps its code E1 and 1710 its code Y1.
        $creditor = self::$fx->creditor();
        $creditorGroup = self::group('KG', 'E1');
        self::rows('UPDATE adresser SET gruppe = $2 WHERE id = $1', [$creditor['id'], $creditorGroup]);
        self::$euCreditorAccount = (int)self::one("SELECT box2 FROM grupper WHERE art = 'KG' AND kodenr = $1", [$creditorGroup])['box2'];
        $days = [
            // An EU service purchase of 400 (Y1) in department 1 and a domestic purchase of 500 incl. VAT (K1) in
            // department 2, both on 1710.
            self::$departmentDay => [
                [71, $box4, 6480, 400.00, 'Y1', '', 1, 'EU ydelseskoeb, afd. 1'],
                [71, $box4, 6480, 500.00, 'K1', '', 2, 'Indenlandsk ydelseskoeb, afd. 2'],
            ],
            // An EU consulting service of 400 (Y1) on the expense account 2510, outside box A, and a domestic purchase of
            // 500 incl. VAT (K1) on 1710. Bilag 73: a purchase of 800 on 1630 from a creditor whose group has the EU code
            // E1, with the line code K1, which finans/bogfor.php reverse-charges for that group (EM 1, 7700 against 7740).
            self::$outsideDay => [
                [72, 2510, 6480, 400.00, 'Y1', '', 0, 'EU konsulentydelse'],
                [72, $box4, 6480, 500.00, 'K1', '', 0, 'Indenlandsk ydelseskoeb'],
                [73, $box3, 'K' . $creditor['kontonr'], 800.00, 'K1', '', 0, 'Varekoeb, EU-leverandoer'],
            ],
            // A domestic purchase of 500,01 incl. VAT (K1: net 400,01, VAT 100,00) and an EU service purchase of 399,99
            // (Y1: VAT 100,00), both on 1710. Bilag 75: an EU goods purchase of 600 on 1630 without VAT, with its
            // acquisition VAT of 150 typed by hand on 7700 against 7740.
            self::$roundedDay => [
                [74, $box4, 6480, 500.01, 'K1', '', 0, 'Indenlandsk ydelseskoeb'],
                [74, $box4, 6480, 399.99, 'Y1', '', 0, 'EU ydelseskoeb'],
                [75, $box3, 6480, 600.00, '', 'on', 0, 'EU varekoeb, moms tastet'],
                [75, 7740, 7700, 150.00, '', 'on', 0, 'Erhvervelsesmoms'],
            ],
        ];
        foreach ($days as $day => $lines) {
            $kladde = [];
            foreach ($lines as [$bilag, $debet, $kredit, $amount, , $momsfri, , $tekst]) {
                $party = is_string($kredit) && $kredit[0] === 'K';
                $kladde[] = [
                    'bilag' => $bilag, 'transdate' => $day, 'debet' => $debet,
                    'k_type' => $party ? 'K' : 'F', 'kredit' => $party ? (int)substr($kredit, 1) : $kredit,
                    'amount' => $amount, 'momsfri' => $momsfri, 'tekst' => $tekst,
                ];
            }
            $journal = self::$lineJournals[$day] = self::$fx->kladde($kladde);
            foreach ($lines as [$bilag, $debet, , $amount, $debetvat, , $afd]) {
                self::rows(
                    "UPDATE kassekladde SET debetvat = $1, kreditvat = '', afd = $2 WHERE kladde_id = $3 AND bilag = $4 AND debet = $5 AND amount = $6",
                    [$debetvat, $afd, $journal, $bilag, $debet, $amount]
                );
            }
            $res = self::runChild('run_bogfor_page.php', ['bogfor', $journal, CharacterizationEnv::sessionId()]);
            if (!self::rows('SELECT id FROM transaktioner WHERE kladde_id = $1', [$journal])) {
                throw new RuntimeException("the journal of $day was not posted.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
            }
        }

        // The 23rd: lines whose saved codes leave the VAT to the account's code or the creditor's group. Bilag 76: a
        // purchase of 800 on 1630 (line code K1) from a creditor whose group has the EU code E1, which the posting
        // reverse-charges (VAT 200 on 7700 against 7740), and a purchase of 1.000 incl. VAT on 1630 (K1) from a creditor
        // whose group has the domestic code K1 (net 800, VAT 200 on 7740 only). Bilag 77: a purchase of 400 on 1710
        // without codes, posted with 1710's code Y1 (VAT 100 on 7720 against 7740). Bilag 78, in a journal of its own
        // posted while 1710 has no VAT code: a purchase of 400 on 1710 without codes, posted without VAT.
        $day = self::$changedDay = sprintf('%04d-%02d-23', (int)$ra['box2'], (int)$ra['box1']);
        foreach (['eu' => 'E1', 'domestic' => 'K1'] as $kind => $code) {
            $gruppe = self::group('KG', $code);
            $creditor = self::$fx->creditor();
            self::rows('UPDATE adresser SET gruppe = $2 WHERE id = $1', [$creditor['id'], $gruppe]);
            self::$changedCreditors[$kind] = ['id' => $creditor['id'], 'kontonr' => $creditor['kontonr'], 'gruppe' => $gruppe];
        }
        $journal = self::$changedJournals['codes'] = self::$fx->kladde([
            ['bilag' => 76, 'transdate' => $day, 'debet' => $box3, 'k_type' => 'K', 'kredit' => (int)self::$changedCreditors['eu']['kontonr'], 'amount' => 800.00, 'tekst' => 'Varekoeb, EU-leverandoer'],
            ['bilag' => 76, 'transdate' => $day, 'debet' => $box3, 'k_type' => 'K', 'kredit' => (int)self::$changedCreditors['domestic']['kontonr'], 'amount' => 1000.00, 'tekst' => 'Varekoeb, dansk leverandoer'],
            ['bilag' => 77, 'transdate' => $day, 'debet' => $box4, 'kredit' => 6480, 'amount' => 400.00, 'tekst' => 'EU ydelseskoeb, kontoens momskode'],
        ]);
        self::rows("UPDATE kassekladde SET debetvat = CASE WHEN bilag = 76 THEN 'K1' ELSE '' END, kreditvat = '' WHERE kladde_id = $1", [$journal]);
        $res = self::runChild('run_bogfor_page.php', ['bogfor', $journal, CharacterizationEnv::sessionId()]);
        if (!self::rows('SELECT id FROM transaktioner WHERE kladde_id = $1', [$journal])) {
            throw new RuntimeException("the journal of $day was not posted.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
        }
        $journal = self::$changedJournals['none'] = self::$fx->kladde([
            ['bilag' => 78, 'transdate' => $day, 'debet' => $box4, 'kredit' => 6480, 'amount' => 400.00, 'tekst' => 'Ydelseskoeb uden momskode'],
        ]);
        self::rows("UPDATE kassekladde SET debetvat = '', kreditvat = '' WHERE kladde_id = $1", [$journal]);
        $res = self::withAccountVat($box4, '', static fn(): array => self::runChild('run_bogfor_page.php', ['bogfor', $journal, CharacterizationEnv::sessionId()]));
        if (!self::rows('SELECT id FROM transaktioner WHERE kladde_id = $1', [$journal])) {
            throw new RuntimeException("the journal of $day without VAT was not posted.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
        }
    }

    /**
     * Runs $fn while account $konto has VAT code $code in the fiscal year's chart of accounts, then restores the code.
     *
     * @return mixed what $fn returns
     */
    private static function withAccountVat(int $konto, string $code, callable $fn)
    {
        $saved = self::one('SELECT moms FROM kontoplan WHERE kontonr = $1 AND regnskabsaar = $2', [$konto, self::$regnaar]);
        self::rows('UPDATE kontoplan SET moms = $1 WHERE kontonr = $2 AND regnskabsaar = $3', [$code, $konto, self::$regnaar]);
        try {
            return $fn();
        } finally {
            self::rows('UPDATE kontoplan SET moms = $1 WHERE kontonr = $2 AND regnskabsaar = $3', [$saved['moms'] ?? null, $konto, self::$regnaar]);
        }
    }

    /**
     * A new debtor ('DG') or creditor ('KG') group with EU-zone B2B-EU, VAT code $box1 (none by default) and the installed
     * group's collective account.
     */
    private static function group(string $art, string $box1 = ''): int
    {
        $row = self::one(
            "INSERT INTO grupper (beskrivelse, art, kodenr, box1, box2, box3, box4, box10, fiscal_year)
             SELECT $1, art, (SELECT max(kodenr) + 1 FROM grupper WHERE art = $2), $4, box2, box3, box4, 'B2B-EU', $3
             FROM grupper WHERE art = $2 ORDER BY kodenr LIMIT 1
             RETURNING kodenr",
            [Fixtures::TAG . ' EU', $art, self::$regnaar, $box1]
        );
        return (int)$row['kodenr'];
    }

    /**
     * Invoice a sale of one line to $customer on the test day through ordrefunc's bogfor(), as remoteBooking does.
     *
     * @param array{id:int, kontonr:string} $customer
     * @param array{id:int} $item
     * @return int the order id
     */
    private static function invoice(array $customer, array $item, float $pris): int
    {
        $order = self::$fx->order($customer, [['item' => $item, 'antal' => 1, 'pris' => $pris]], ['ordredate' => self::$day, 'momssats' => 0]);
        self::rows('UPDATE ordrer SET cvrnr = $2 WHERE id = $1', [$order['id'], self::EU_CVR]);
        $res = self::runChild('run_order_invoice.php', ['invoice', $order['id'], CharacterizationEnv::testDb()]);
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $out = $lines ? json_decode(end($lines), true) : null;
        if (($out['bogfor'] ?? null) !== 'OK') {
            throw new RuntimeException("invoicing order {$order['id']} failed.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
        }
        return $order['id'];
    }

    /**
     * An invoiced purchase of one line from $supplier on the test day; posted with its postings, or left at status 3.
     *
     * @param array{id:int, kontonr:string} $supplier
     * @param array{id:int} $item
     * @return int the order id
     */
    private static function purchase(array $supplier, array $item, float $pris, bool $posted): int
    {
        $order = self::$fx->creditorOrder($supplier, [['item' => $item, 'antal' => 1, 'pris' => $pris]], ['ordredate' => self::$day, 'momssats' => 0, 'status' => $posted ? 4 : 3]);
        self::rows('UPDATE ordrer SET fakturadate = ordredate, fakturanr = $2, cvrnr = $3 WHERE id = $1', [$order['id'], 'K' . $order['ordrenr'], 'DE987654321']);
        self::rows('UPDATE ordrelinjer SET bogf_konto = $2 WHERE ordre_id = $1', [$order['id'], (int)self::$mr['box3']]);
        if ($posted) {
            $collective = self::one("SELECT g.box2 FROM adresser a JOIN grupper g ON g.art = 'KG' AND g.kodenr = a.gruppe WHERE a.id = $1", [$supplier['id']]);
            $vat = round($pris * 0.25, 2);
            foreach ([[(int)self::$mr['box3'], $pris, 0], [(int)$collective['box2'], 0, $pris], [7740, $vat, 0], [7700, 0, $vat]] as [$konto, $debet, $kredit]) {
                self::rows(
                    "INSERT INTO transaktioner (kontonr, bilag, transdate, beskrivelse, faktura, debet, kredit, kladde_id, afd, ordre_id, logdate, logtime)
                     VALUES ($1, 0, $2, $3, $4, $5, $6, 0, 0, $7, CURRENT_DATE, '12:00')",
                    [$konto, self::$day, Fixtures::TAG . ' EU varekoeb', 'K' . $order['ordrenr'], $debet, $kredit, $order['id']]
                );
            }
        }
        return $order['id'];
    }

    /**
     * Finans -> Rapporter, report $rapportart for the test day (or $day), as the front page posts it after OK; with
     * $afd, for that department only.
     */
    private function report(string $rapportart, string $day = '', string $afd = ''): string
    {
        [$aar, $maaned, $dato] = array_map('intval', explode('-', $day !== '' ? $day : self::$day));
        $form = [
            'regnaar' => (string)self::$regnaar,
            'rapportart' => $rapportart,
            'maaned_fra' => "$aar|$maaned",
            'dato_fra' => (string)$dato,
            'maaned_til' => "$aar|$maaned",
            'dato_til' => (string)$dato,
            'submit' => ' OK ',
        ];
        if ($afd !== '') {
            $form['afd'] = "$afd:Afdeling $afd";
        }
        return self::runChild('run_finans_rapport_page.php', [CharacterizationEnv::sessionId(), '', http_build_query($form)])['stdout'];
    }

    /**
     * The table rows of a report page: first cell => last cell, both with whitespace collapsed.
     *
     * @return array<string, string>
     */
    private static function rowsByFirstCell(string $html): array
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $rows = [];
        foreach ($doc->getElementsByTagName('tr') as $row) {
            $cells = [];
            foreach ($row->childNodes as $cell) {
                if ($cell instanceof DOMElement && strtolower($cell->nodeName) === 'td') {
                    $cells[] = trim((string)preg_replace('/\s+/u', ' ', $cell->textContent));
                }
            }
            if (count($cells) > 1 && !isset($rows[$cells[0]])) {
                $rows[$cells[0]] = (string)end($cells);
            }
        }
        return $rows;
    }

    /** A Danish amount as dkdecimal() prints it, e.g. 1.000,00, as a number. */
    private static function amount(string $dk): float
    {
        return (float)str_replace(['.', ','], ['', '.'], $dk);
    }

    /** The day's (or $day's) net movement on an account: debit minus credit. */
    private static function movement(int $konto, string $day = ''): float
    {
        $r = self::one(
            'SELECT COALESCE(SUM(COALESCE(debet, 0) - COALESCE(kredit, 0)), 0) AS net FROM transaktioner WHERE kontonr = $1 AND transdate = $2',
            [$konto, $day !== '' ? $day : self::$day]
        );
        return round((float)$r['net'], 2);
    }

    public function test_the_fixture_has_each_kind_of_invoice(): void
    {
        $state = [];
        foreach (self::$orders as $kind => $id) {
            $order = self::one('SELECT status FROM ordrer WHERE id = $1', [$id]);
            $posted = self::one(
                'SELECT COALESCE(SUM(kredit - debet), 0) AS kredit FROM transaktioner WHERE ordre_id = $1 AND kontonr = $2',
                [$id, (int)self::$mr['box5']]
            );
            $state[$kind] = [(int)$order['status'], (bool)self::rows('SELECT id FROM transaktioner WHERE ordre_id = $1', [$id]), round((float)$posted['kredit'], 2)];
        }
        $this->assertSame(
            [
                'sale posted' => [4, true, 300.0],
                'sale invoiced' => [3, false, 0.0],
                'sale moved' => [4, false, 0.0],
                'purchase posted' => [4, true, 0.0],
                'purchase invoiced' => [3, false, 0.0],
            ],
            $state,
            'status, postings, and credit on the box B goods account of each order'
        );
        $this->assertSame(
            [[(int)self::$mr['box5'], 0.0, 500.0, 0], [(int)self::$mr['box4'], 400.0, 0.0, 0], [7720, 0.0, 100.0, 0], [7740, 100.0, 0.0, 0]],
            array_map(
                static fn(array $r): array => [(int)$r['kontonr'], round((float)$r['debet'], 2), round((float)$r['kredit'], 2), (int)$r['ordre_id']],
                self::rows(
                    "SELECT kontonr, COALESCE(debet, 0) AS debet, COALESCE(kredit, 0) AS kredit, COALESCE(ordre_id, 0) AS ordre_id
                     FROM transaktioner WHERE kladde_id = $1 AND kontonr IN ($2, $3, 7720, 7740) ORDER BY kontonr",
                    [self::$journal, (int)self::$mr['box5'], (int)self::$mr['box4']]
                )
            ),
            'the journal posted the EU sale and the EU service purchase with its reverse-charge VAT, without an order id'
        );
    }

    public function test_the_boxes_hold_the_posted_orders_and_the_journal(): void
    {
        $html = $this->report('moms_rubrik');
        $rows = self::rowsByFirstCell($html);
        $screen = [];
        foreach ([...self::RUBRIKKER, 'I alt'] as $kode) {
            $screen[$kode] = $rows[$kode] ?? null;
        }
        $this->assertSame(
            [
                // The posted purchase; the purchase of 600 is not posted.
                'A-varer' => '1.000,00',
                // The service purchase from the journal.
                'A-ydelser' => '400,00',
                // The posted sale of 300 and the journal's sale of 500; the sale of 200 (status 3) and the
                // sale of 350 (moved to a journal that is not posted) are not in the ledger.
                'B-varer' => '800,00',
                'B-ydelser' => '0,00',
                'C' => '0,00',
                'I alt' => '2.200,00',
            ],
            $screen,
            "the boxes on screen:\n" . substr(strip_tags($html), 0, 3000)
        );
    }

    public function test_the_boxes_agree_with_the_ledger_and_the_vat_return(): void
    {
        $rubrik = self::rowsByFirstCell($this->report('moms_rubrik'));
        $this->assertSame(self::movement((int)self::$mr['box3']), self::amount($rubrik['A-varer']), 'A-varer is the debit on the box A goods account');
        $this->assertSame(self::movement((int)self::$mr['box4']), self::amount($rubrik['A-ydelser']), 'A-ydelser is the debit on the box A services account');
        $this->assertSame(-self::movement((int)self::$mr['box5']), self::amount($rubrik['B-varer']), 'B-varer is the credit on the box B goods account');

        // Momsangivelse for the same day: the acquisition VAT on EU goods (7700) and the reverse-charge VAT on EU
        // services (7720) are 25 % of boxes A goods and A services.
        $vat = self::rowsByFirstCell($this->report('momsangivelse'));
        $this->assertSame('250,00', $vat['7700'] ?? null, 'Momsangivelse: VAT on EU goods purchases');
        $this->assertSame('100,00', $vat['7720'] ?? null, 'Momsangivelse: VAT on EU service purchases');
        $this->assertSame(round(self::amount($rubrik['A-varer']) * 0.25, 2), self::amount($vat['7700']), 'box A goods agrees with its VAT');
        $this->assertSame(round(self::amount($rubrik['A-ydelser']) * 0.25, 2), self::amount($vat['7720']), 'box A services agrees with its VAT');
    }

    /**
     * Some ledgers point MR box4 at their EU services sales account and leave box5/box6 empty, as the EU sales list
     * used to read box4 (EuSalesListKindTest WORKAROUND). The journal part of box A counts only postings that carry
     * reverse-charge VAT, so the sale of 700 on that account is not reported as a negative EU service purchase.
     */
    public function test_a_sales_account_in_box4_is_not_counted_in_box_a(): void
    {
        $this->assertSame(
            [-700.0, 1000.0, -300.0],
            [self::movement((int)self::$mr['box6'], self::$nextDay), self::movement((int)self::$mr['box3'], self::$nextDay), self::movement((int)self::$mr['box7'], self::$nextDay)],
            'the second journal: sale on 1150, purchase on 1630, sale outside the EU on 1200'
        );

        $saved = self::rows("SELECT id, box4, box5, box6 FROM grupper WHERE art = 'MR'");
        self::rows("UPDATE grupper SET box4 = $1, box5 = '', box6 = '' WHERE art = 'MR'", [self::$mr['box6']]);
        try {
            $html = $this->report('moms_rubrik', self::$nextDay);
        } finally {
            foreach ($saved as $row) {
                self::rows('UPDATE grupper SET box4 = $1, box5 = $2, box6 = $3 WHERE id = $4', [$row['box4'], $row['box5'], $row['box6'], $row['id']]);
            }
        }
        $rows = self::rowsByFirstCell($html);
        $screen = [];
        foreach ([...self::RUBRIKKER, 'I alt'] as $kode) {
            $screen[$kode] = $rows[$kode] ?? null;
        }
        $this->assertSame(
            [
                // The purchase on 1630 (VAT code E1).
                'A-varer' => '1.000,00',
                // The sale on 1150 carries no reverse-charge VAT: it is not a purchase.
                'A-ydelser' => '0,00',
                // No box B account is set up in this setup.
                'B-varer' => '0,00',
                'B-ydelser' => '0,00',
                'C' => '300,00',
                'I alt' => '1.300,00',
            ],
            $screen,
            "the boxes on screen:\n" . substr(strip_tags($html), 0, 3000)
        );

        // Momsangivelse for that day: the acquisition VAT on the purchase is 25 % of box A goods.
        $vat = self::rowsByFirstCell($this->report('momsangivelse', self::$nextDay));
        $this->assertSame('250,00', $vat['7700'] ?? null, 'Momsangivelse: VAT on EU goods purchases');
        $this->assertSame(round(self::amount($screen['A-varer']) * 0.25, 2), self::amount($vat['7700']), 'box A goods agrees with its VAT');
    }

    /**
     * A journal line can carry its own VAT code (Kimi review of eceefa8c: Y1 on 1710 whose own code is blank gave
     * A-ydelser 0,00). The ledger does not keep the line's code, so the boxes read what the posting wrote: box A counts
     * a posting on its accounts that carries reverse-charge VAT (transaktioner.moms and the matching posting on 7700 or
     * 7720 in the same voucher), and boxes B and C count no sale charged with VAT. 1710 has no VAT code of its own while
     * the journal was posted and while the reports run.
     */
    public function test_the_line_vat_code_decides_the_boxes(): void
    {
        $box3 = (int)self::$mr['box3'];
        $box4 = (int)self::$mr['box4'];
        $box5 = (int)self::$mr['box5'];
        $this->assertSame(
            [
                // EU service purchase (Y1): reverse-charge VAT 100 on 7720 against 7740.
                [6, $box4, 400.0, 0.0, 100.0], [6, 7720, 0.0, 100.0, 0.0], [6, 7740, 100.0, 0.0, 0.0],
                // Domestic purchase on the same account (K1): input VAT 100 on 7740 only.
                [7, $box4, 400.0, 0.0, 100.0], [7, 7740, 100.0, 0.0, 0.0],
                // EU credit note (Y1 on the credit side): 25 back on 7720 and 7740.
                [8, $box4, 0.0, 100.0, -25.0], [8, 7720, 25.0, 0.0, 0.0], [8, 7740, 0.0, 25.0, 0.0],
                // Momsfri on the box A goods account (VAT code E1): no VAT.
                [9, $box3, 200.0, 0.0, 0.0],
                // Sale on the box B goods account with Danish VAT (S1): 100 on 7680.
                [10, $box5, 0.0, 400.0, -100.0], [10, 7680, 0.0, 100.0, 0.0],
            ],
            array_map(
                static fn(array $r): array => [(int)$r['bilag'], (int)$r['kontonr'], round((float)$r['debet'], 2), round((float)$r['kredit'], 2), round((float)$r['moms'], 2)],
                self::rows(
                    "SELECT bilag, kontonr, COALESCE(debet, 0) AS debet, COALESCE(kredit, 0) AS kredit, COALESCE(moms, 0) AS moms
                     FROM transaktioner WHERE kladde_id = $1 AND kontonr <> 6480 AND COALESCE(ordre_id, 0) = 0 ORDER BY bilag, kontonr",
                    [self::$lineVatJournal]
                )
            ),
            'the journal posted each line with the VAT of its own code, without an order id (bank 6480 not listed)'
        );

        [$html, $vatHtml] = self::withAccountVat($box4, '', fn(): array => [
            $this->report('moms_rubrik', self::$lineVatDay),
            $this->report('momsangivelse', self::$lineVatDay),
        ]);
        $rows = self::rowsByFirstCell($html);
        $screen = [];
        foreach ([...self::RUBRIKKER, 'I alt'] as $kode) {
            $screen[$kode] = $rows[$kode] ?? null;
        }
        $this->assertSame(
            [
                // The posting on 1630 without VAT carries no acquisition VAT.
                'A-varer' => '0,00',
                // 400 - 100 credit note; the domestic purchase of 400 on 1710 has no reverse-charge VAT.
                'A-ydelser' => '300,00',
                // The sale on 1050 was charged with Danish VAT, so it is not a sale without VAT.
                'B-varer' => '0,00',
                'B-ydelser' => '0,00',
                'C' => '0,00',
                'I alt' => '300,00',
            ],
            $screen,
            "the boxes on screen:\n" . substr(strip_tags($html), 0, 3000)
        );

        // Momsangivelse for that day: the reverse-charge VAT is 25 % of box A services, there is none on goods, and the
        // sale's Danish VAT is in the sales VAT.
        $vat = self::rowsByFirstCell($vatHtml);
        $this->assertSame('75,00', $vat['7720'] ?? null, 'Momsangivelse: VAT on EU service purchases');
        $this->assertSame('0,00', $vat['7700'] ?? null, 'Momsangivelse: VAT on EU goods purchases');
        $this->assertSame('100,00', $vat['7680'] ?? null, 'Momsangivelse: sales VAT');
        $this->assertSame(round(self::amount($screen['A-ydelser']) * 0.25, 2), self::amount($vat['7720']), 'box A services agrees with its VAT');
        $this->assertSame(round(self::amount($screen['A-varer']) * 0.25, 2), self::amount($vat['7700']), 'box A goods agrees with its VAT');
    }

    /**
     * An EU purchase posted without VAT (Momsfri) with its reverse-charge VAT typed by hand on a line of its own in the
     * same voucher (review of the line VAT code change: box A gave 0,00 while Momsangivelse showed the VAT). The posting
     * carries no VAT of its own, but its voucher holds acquisition VAT on 7700 or 7720 that no posting's own VAT
     * explains, so box A counts it, whatever VAT code the account has.
     */
    public function test_a_momsfri_purchase_with_hand_typed_reverse_charge_counts_in_box_a(): void
    {
        $box3 = (int)self::$mr['box3'];
        $box4 = (int)self::$mr['box4'];
        $this->assertSame(
            [
                // EU goods purchase of 1.000 without VAT, and 250 typed by hand on 7700 against 7740.
                [11, $box3, 1000.0, 0.0, 0.0], [11, 7700, 0.0, 250.0, 0.0], [11, 7740, 250.0, 0.0, 0.0],
                // EU service purchase of 800 without VAT, and 200 typed by hand on 7720 against 7740.
                [12, $box4, 800.0, 0.0, 0.0], [12, 7720, 0.0, 200.0, 0.0], [12, 7740, 200.0, 0.0, 0.0],
                // EU credit note of 400 on goods without VAT, and 100 typed by hand back on 7700.
                [13, $box3, 0.0, 400.0, 0.0], [13, 7700, 100.0, 0.0, 0.0], [13, 7740, 0.0, 100.0, 0.0],
            ],
            self::journalRows(self::$manualVatJournal),
            'the journal posted every line without VAT of its own, the VAT only as typed (bank 6480 not listed)'
        );

        $expected = [
            // 1.000 - the credit note of 400.
            'A-varer' => '600,00',
            'A-ydelser' => '800,00',
            'B-varer' => '0,00',
            'B-ydelser' => '0,00',
            'C' => '0,00',
            'I alt' => '1.400,00',
        ];
        $screen = self::screenBoxes($this->report('moms_rubrik', self::$manualVatDay));
        $this->assertSame($expected, $screen, 'the boxes, 1630 and 1710 with their VAT codes E1 and Y1');

        // Momsangivelse for that day: the VAT typed by hand is 25 % of each box A row.
        $vat = self::rowsByFirstCell($this->report('momsangivelse', self::$manualVatDay));
        $this->assertSame('150,00', $vat['7700'] ?? null, 'Momsangivelse: VAT on EU goods purchases');
        $this->assertSame('200,00', $vat['7720'] ?? null, 'Momsangivelse: VAT on EU service purchases');
        $this->assertSame(round(self::amount($screen['A-varer']) * 0.25, 2), self::amount($vat['7700']), 'box A goods agrees with its VAT');
        $this->assertSame(round(self::amount($screen['A-ydelser']) * 0.25, 2), self::amount($vat['7720']), 'box A services agrees with its VAT');

        // The same ledger read while 1630 and 1710 have no VAT code of their own.
        $html = self::withAccountVat($box3, '', fn(): string => self::withAccountVat($box4, '', fn(): string => $this->report('moms_rubrik', self::$manualVatDay)));
        $this->assertSame($expected, self::screenBoxes($html), 'the boxes, 1630 and 1710 without a VAT code');
    }

    /**
     * A posting without VAT on a box A account, in a voucher whose reverse-charge VAT the posting computed for another
     * line, carries no reverse-charge VAT itself: bilag 14 holds the VAT of the 400 on 1710, bilag 15 the VAT of a
     * consulting service of 120 on the expense account 2510 (line code Y1). Box A counts the 400 only, not the 50 and
     * the 60 without VAT.
     */
    public function test_a_posting_without_vat_beside_another_lines_reverse_charge_is_not_counted(): void
    {
        $box4 = (int)self::$mr['box4'];
        $this->assertSame(
            [
                // 400 with reverse-charge VAT 100 (1710's own code Y1), and a fee of 50 without VAT.
                [14, $box4, 400.0, 0.0, 100.0], [14, $box4, 50.0, 0.0, 0.0], [14, 7720, 0.0, 100.0, 0.0], [14, 7740, 100.0, 0.0, 0.0],
                // A fee of 60 without VAT, and 120 on 2510 with reverse-charge VAT 30 (line code Y1).
                [15, $box4, 60.0, 0.0, 0.0], [15, 2510, 120.0, 0.0, 30.0], [15, 7720, 0.0, 30.0, 0.0], [15, 7740, 30.0, 0.0, 0.0],
            ],
            self::journalRows(self::$sideLineJournal),
            'the journal posted the VAT of the 400 and the 120 only (bank 6480 not listed)'
        );

        $screen = self::screenBoxes($this->report('moms_rubrik', self::$sideLineDay));
        $this->assertSame(
            [
                'A-varer' => '0,00',
                // The 400; the 50 and the 60 carry no reverse-charge VAT.
                'A-ydelser' => '400,00',
                'B-varer' => '0,00',
                'B-ydelser' => '0,00',
                'C' => '0,00',
                'I alt' => '400,00',
            ],
            $screen,
            'the boxes'
        );

        // Momsangivelse for that day: 100 is 25 % of box A services, 30 is the VAT of the purchase on 2510, an account
        // that is not a box A account.
        $vat = self::rowsByFirstCell($this->report('momsangivelse', self::$sideLineDay));
        $this->assertSame('130,00', $vat['7720'] ?? null, 'Momsangivelse: VAT on EU service purchases');
        $this->assertSame(round(self::amount($screen['A-ydelser']) * 0.25 + 30.0, 2), self::amount($vat['7720']), 'box A services and the purchase on 2510 make up the VAT');
    }

    /**
     * Audit finding p1-reports-F1: an EU service purchase of 400 (Y1) and a domestic purchase of 500 incl. VAT (K1) on
     * 1710 in one voucher both carry VAT 100, and the voucher holds one posting of 100 on 7720. Box A paired each of
     * them with that posting and showed A-ydelser 800,00. It pairs one posting with it now: 400,00, 25 % of which is the
     * reverse-charge VAT Momsangivelse shows.
     */
    public function test_a_domestic_purchase_with_the_same_vat_as_an_eu_purchase_is_not_counted(): void
    {
        $box4 = (int)self::$mr['box4'];
        $this->assertSame(
            [
                // EU service purchase: reverse-charge VAT 100 on 7720 against 7740.
                [16, $box4, 400.0, 0.0, 100.0], [16, $box4, 400.0, 0.0, 100.0],
                // The domestic purchase: input VAT 100 on 7740 only.
                [16, 7720, 0.0, 100.0, 0.0], [16, 7740, 100.0, 0.0, 0.0], [16, 7740, 100.0, 0.0, 0.0],
            ],
            self::journalRows(self::$sameVatJournals[self::$equalVatDay]),
            'both postings on 1710 carry VAT 100, and the voucher holds one posting of 100 on 7720 (bank 6480 not listed)'
        );
        $this->assertSameVatDay(self::$equalVatDay, ['A-varer' => '0,00', 'A-ydelser' => '400,00', 'I alt' => '400,00'], ['7700' => '0,00', '7720' => '100,00']);
    }

    /**
     * A domestic purchase of 500 incl. VAT on the box A goods account 1630 (K1), then an EU service purchase of 400 on
     * 1710 (Y1): both carry VAT 100 and the voucher holds one posting of 100 on the services acquisition VAT account 7720
     * (YM box1). A posting first takes a VAT posting of its own box (YM for box A services, EM for goods), so the
     * service purchase takes it and the domestic purchase, first in the voucher, is not counted.
     */
    public function test_an_acquisition_vat_posting_goes_with_a_posting_of_its_own_box_first(): void
    {
        $box3 = (int)self::$mr['box3'];
        $box4 = (int)self::$mr['box4'];
        $this->assertSame(
            [
                [17, $box3, 400.0, 0.0, 100.0], [17, $box4, 400.0, 0.0, 100.0],
                [17, 7720, 0.0, 100.0, 0.0], [17, 7740, 100.0, 0.0, 0.0], [17, 7740, 100.0, 0.0, 0.0],
            ],
            self::journalRows(self::$sameVatJournals[self::$otherBoxDay]),
            'the postings on 1630 and 1710 carry VAT 100, and the voucher holds one posting of 100 on 7720 (bank 6480 not listed)'
        );
        $this->assertSameVatDay(self::$otherBoxDay, ['A-varer' => '0,00', 'A-ydelser' => '400,00', 'I alt' => '400,00'], ['7700' => '0,00', '7720' => '100,00']);
    }

    /**
     * An EU service purchase of 400 on 1710 (VAT 100 computed on 7720) beside an EU goods purchase of 400 on 1630 without
     * VAT whose acquisition VAT of 100 is typed by hand on 7700. Box A read the typed VAT as explained by the service
     * purchase's VAT of the same amount and left the goods purchase out. The voucher holds two acquisition VAT postings
     * of 100 and one posting with VAT of its own, so one of them is typed by hand and the goods purchase counts.
     */
    public function test_a_momsfri_purchase_counts_beside_a_computed_reverse_charge_of_the_same_amount(): void
    {
        $box3 = (int)self::$mr['box3'];
        $box4 = (int)self::$mr['box4'];
        $this->assertSame(
            [
                [18, $box3, 400.0, 0.0, 0.0], [18, $box4, 400.0, 0.0, 100.0],
                [18, 7700, 0.0, 100.0, 0.0], [18, 7720, 0.0, 100.0, 0.0], [18, 7740, 100.0, 0.0, 0.0], [18, 7740, 100.0, 0.0, 0.0],
            ],
            self::journalRows(self::$sameVatJournals[self::$typedBesideDay]),
            'VAT 100 computed for 1710 on 7720 and typed by hand on 7700, and 1630 without VAT (bank 6480 not listed)'
        );
        $this->assertSameVatDay(self::$typedBesideDay, ['A-varer' => '400,00', 'A-ydelser' => '400,00', 'I alt' => '800,00'], ['7700' => '100,00', '7720' => '100,00']);
    }

    /**
     * Audit p1r2 of p1-reports-F1, department filter: one voucher holds an EU service purchase of 400 (Y1) in department
     * 1 and a domestic purchase of 500 incl. VAT (K1, net 400) in department 2 on 1710, both with VAT 100, and one
     * posting of 100 on 7720. Filtered to department 2, box A paired the domestic purchase with that VAT, which belongs
     * to department 1's purchase, and showed A-ydelser 400,00. Box A reads the journal lines now: department 2 has no
     * reverse-charged line, so it shows 0,00, and the two departments add up to the whole voucher's 400,00.
     */
    public function test_a_department_filter_counts_only_the_departments_own_eu_purchase(): void
    {
        $box4 = (int)self::$mr['box4'];
        $day = self::$departmentDay;
        $this->assertSame(
            [
                [71, $box4, 400.0, 0.0, 100.0], [71, $box4, 400.0, 0.0, 100.0],
                [71, 7720, 0.0, 100.0, 0.0], [71, 7740, 100.0, 0.0, 0.0], [71, 7740, 100.0, 0.0, 0.0],
            ],
            self::journalRows(self::$lineJournals[$day]),
            'both postings on 1710 carry VAT 100, and the voucher holds one posting of 100 on 7720 (bank 6480 not listed)'
        );
        $this->assertSame(
            [[1, 100.0], [2, 100.0]],
            array_map(
                static fn(array $r): array => [(int)$r['afd'], round((float)$r['moms'], 2)],
                self::rows('SELECT afd, moms FROM transaktioner WHERE kladde_id = $1 AND kontonr = $2 ORDER BY afd', [self::$lineJournals[$day], $box4])
            ),
            'the EU purchase is posted in department 1, the domestic purchase in department 2'
        );
        $this->assertSameVatDay($day, ['A-varer' => '0,00', 'A-ydelser' => '400,00', 'I alt' => '400,00'], ['7700' => '0,00', '7720' => '100,00']);

        $boxes = static fn(string $html): array => array_intersect_key(self::screenBoxes($html), ['A-varer' => 0, 'A-ydelser' => 0, 'I alt' => 0]);
        $this->assertSame(
            ['A-varer' => '0,00', 'A-ydelser' => '400,00', 'I alt' => '400,00'],
            $boxes($this->report('moms_rubrik', $day, '1')),
            'department 1: its EU service purchase'
        );
        $this->assertSame(
            ['A-varer' => '0,00', 'A-ydelser' => '0,00', 'I alt' => '0,00'],
            $boxes($this->report('moms_rubrik', $day, '2')),
            'department 2: the domestic purchase only, which carries no reverse-charge VAT'
        );
    }

    /**
     * Audit p1r2 of p1-reports-F1, an EU purchase outside box A: one voucher holds an EU consulting service of 400 (Y1)
     * on the expense account 2510, which is not a box A account, and a domestic purchase of 500 incl. VAT (K1, net 400)
     * on 1710, both with VAT 100. Box A paired the domestic purchase with the consulting service's 100 on 7720 and showed
     * A-ydelser 400,00; it shows 0,00 now. Bilag 73 is a purchase of 800 on 1630 from a creditor whose group has the EU
     * code E1, with the line code K1: finans/bogfor.php reverse-charges it for that group, and box A counts it.
     */
    public function test_an_eu_purchase_outside_box_a_lends_its_vat_to_no_domestic_purchase(): void
    {
        $box3 = (int)self::$mr['box3'];
        $box4 = (int)self::$mr['box4'];
        $day = self::$outsideDay;
        $expected = [
            // 400 on 2510 with reverse-charge VAT 100, and the domestic purchase on 1710 with input VAT 100 only.
            [72, $box4, 400.0, 0.0, 100.0], [72, 2510, 400.0, 0.0, 100.0], [72, 7720, 0.0, 100.0, 0.0], [72, 7740, 100.0, 0.0, 0.0], [72, 7740, 100.0, 0.0, 0.0],
            // 800 on 1630 from the EU creditor: acquisition VAT 200 on 7700 against 7740, the creditor on the group's account.
            [73, $box3, 800.0, 0.0, 200.0], [73, self::$euCreditorAccount, 0.0, 800.0, -200.0], [73, 7700, 0.0, 200.0, 0.0], [73, 7740, 200.0, 0.0, 0.0],
        ];
        $actual = self::journalRows(self::$lineJournals[$day]);
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual, 'the VAT of 2510 on 7720, none for 1710, and the EU creditor\'s purchase reverse-charged (bank 6480 not listed)');

        $screen = self::screenBoxes($this->report('moms_rubrik', $day));
        $this->assertSame(
            [
                // The purchase from the EU creditor.
                'A-varer' => '800,00',
                // The domestic purchase on 1710 carries no reverse-charge VAT; the EU service is on 2510, outside box A.
                'A-ydelser' => '0,00',
                'B-varer' => '0,00',
                'B-ydelser' => '0,00',
                'C' => '0,00',
                'I alt' => '800,00',
            ],
            $screen,
            'the boxes'
        );

        // Momsangivelse for that day: 200 is 25 % of box A goods, 100 is the VAT of the service on 2510.
        $vat = self::rowsByFirstCell($this->report('momsangivelse', $day));
        $this->assertSame(['7700' => '200,00', '7720' => '100,00'], ['7700' => $vat['7700'] ?? null, '7720' => $vat['7720'] ?? null], 'Momsangivelse: VAT on EU goods and service purchases');
        $this->assertSame(round(self::amount($screen['A-varer']) * 0.25, 2), self::amount($vat['7700']), 'box A goods agrees with its VAT');
    }

    /**
     * Audit p1r2 of p1-reports-F1, equal VAT with different nets: a domestic purchase of 500,01 incl. VAT (K1: net
     * 400,01, VAT 100,00) and then an EU service purchase of 399,99 (Y1: reverse-charge VAT 100,00) on 1710 in one
     * voucher. Box A paired the first posting with the 100,00 on 7720 and showed A-ydelser 400,01; it shows the EU
     * purchase's 399,99 now. Bilag 75 is an EU goods purchase of 600 on 1630 without VAT, with its acquisition VAT of 150
     * typed by hand on 7700: in a journal with VAT codes it still counts.
     */
    public function test_equal_vat_with_different_nets_counts_the_eu_purchases_net(): void
    {
        $box3 = (int)self::$mr['box3'];
        $box4 = (int)self::$mr['box4'];
        $day = self::$roundedDay;
        $expected = [
            [74, $box4, 399.99, 0.0, 100.0], [74, $box4, 400.01, 0.0, 100.0], [74, 7720, 0.0, 100.0, 0.0], [74, 7740, 100.0, 0.0, 0.0], [74, 7740, 100.0, 0.0, 0.0],
            [75, $box3, 600.0, 0.0, 0.0], [75, 7700, 0.0, 150.0, 0.0], [75, 7740, 150.0, 0.0, 0.0],
        ];
        $actual = self::journalRows(self::$lineJournals[$day]);
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual, 'both postings on 1710 carry VAT 100, the voucher holds one posting of 100 on 7720, and 150 is typed on 7700 (bank 6480 not listed)');

        $screen = self::screenBoxes($this->report('moms_rubrik', $day));
        $this->assertSame(
            [
                // The purchase without VAT, its acquisition VAT typed by hand.
                'A-varer' => '600,00',
                // The EU purchase's net; the domestic purchase's 400,01 carries no reverse-charge VAT.
                'A-ydelser' => '399,99',
                'B-varer' => '0,00',
                'B-ydelser' => '0,00',
                'C' => '0,00',
                'I alt' => '999,99',
            ],
            $screen,
            'the boxes'
        );

        // Momsangivelse for that day: 150 is 25 % of box A goods, 100,00 is 25 % of 399,99 rounded to øre.
        $vat = self::rowsByFirstCell($this->report('momsangivelse', $day));
        $this->assertSame(['7700' => '150,00', '7720' => '100,00'], ['7700' => $vat['7700'] ?? null, '7720' => $vat['7720'] ?? null], 'Momsangivelse: VAT on EU goods and service purchases');
    }

    /**
     * Audit p1r3 finding p1-reports-F1c, as posted: box A read a journal line whose saved codes leave its VAT to the
     * account's code (no code) or to a creditor's group (another code against a creditor) by that code and those groups
     * as they are now. With 1710's code Y1, the purchase posted while 1710 had no code, without VAT, counted beside the
     * one posted with Y1 (A-ydelser 800,00 instead of 400,00). Box A reads such a line by its posting now: its VAT came
     * on top of the net, and its voucher holds that VAT on an acquisition VAT account in its department. The domestic
     * purchase of bilag 76 has the EU purchase's net and VAT but is posted net of its VAT, so it does not count.
     */
    public function test_a_journal_line_without_codes_counts_by_its_posting_not_by_the_accounts_code_now(): void
    {
        $box3 = (int)self::$mr['box3'];
        $box4 = (int)self::$mr['box4'];
        $collective = (int)self::one("SELECT box2 FROM grupper WHERE art = 'KG' AND kodenr = $1", [self::$changedCreditors['eu']['gruppe']])['box2'];
        $expected = [
            // 800 from the EU creditor: VAT 200 on top, 7700 against 7740, the creditor's -200 on the group's account.
            [76, $box3, 800.0, 0.0, 200.0], [76, $collective, 0.0, 800.0, -200.0], [76, 7700, 0.0, 200.0, 0.0], [76, 7740, 200.0, 0.0, 0.0],
            // 1.000 incl. VAT from the domestic creditor: net 800, VAT 200 on 7740 only.
            [76, $box3, 800.0, 0.0, 200.0], [76, $collective, 0.0, 1000.0, 0.0], [76, 7740, 200.0, 0.0, 0.0],
            // 400 on 1710 with its code Y1: VAT 100 on top, 7720 against 7740.
            [77, $box4, 400.0, 0.0, 100.0], [77, 7720, 0.0, 100.0, 0.0], [77, 7740, 100.0, 0.0, 0.0],
        ];
        $actual = self::journalRows(self::$changedJournals['codes']);
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual, 'the EU purchase reverse-charged, the domestic one with the same net and VAT not, 1710 by its code Y1 (bank 6480 not listed)');
        $this->assertSame([[78, $box4, 400.0, 0.0, 0.0]], self::journalRows(self::$changedJournals['none']), 'posted while 1710 had no VAT code: no VAT');

        // 1710 has its code Y1 again, which bilag 78 was not posted with; the creditors are in the groups they were posted with.
        $this->assertSameVatDay(self::$changedDay, ['A-varer' => '800,00', 'A-ydelser' => '400,00', 'I alt' => '1.200,00'], ['7700' => '200,00', '7720' => '100,00']);
    }

    /**
     * Audit p1r3 finding p1-reports-F1c, after a change: moving the EU creditor to a domestic group removed its posted
     * purchase from box A (A-varer 800,00 -> 0,00), and giving 1710 a domestic code removed the purchase posted with its
     * code Y1 (A-ydelser 400,00 -> 0,00), although the ledger and Momsangivelse are unchanged. The creditors swap groups
     * and 1710 gets the code K1 here: the boxes stay as posted, and the domestic purchase of bilag 76, now in the EU
     * group, does not count.
     */
    public function test_a_posted_line_keeps_its_box_when_the_account_code_or_the_creditor_group_changes(): void
    {
        $creditors = self::$changedCreditors;
        self::rows('UPDATE adresser SET gruppe = $2 WHERE id = $1', [$creditors['eu']['id'], $creditors['domestic']['gruppe']]);
        self::rows('UPDATE adresser SET gruppe = $2 WHERE id = $1', [$creditors['domestic']['id'], $creditors['eu']['gruppe']]);
        try {
            self::withAccountVat((int)self::$mr['box4'], 'K1', fn() => $this->assertSameVatDay(
                self::$changedDay,
                ['A-varer' => '800,00', 'A-ydelser' => '400,00', 'I alt' => '1.200,00'],
                ['7700' => '200,00', '7720' => '100,00']
            ));
        } finally {
            foreach ($creditors as $creditor) {
                self::rows('UPDATE adresser SET gruppe = $2 WHERE id = $1', [$creditor['id'], $creditor['gruppe']]);
            }
        }
    }

    /**
     * The help (RP15 step 2, MR2 steps 3 and 4) and the footnote tell the bookkeeper which postings without an order
     * the report already holds, so they are not added by hand once more. Indstillinger » Moms shows only the box A
     * accounts (MR box3/box4), not the box B and C accounts (box5..box7) that the journal's EU goods sale of 500 on
     * 1050 is counted on (B-varer 800,00 in test_the_boxes_hold_the_posted_orders_and_the_journal). The report lists
     * the accounts itself under its table, at "Konti uden ordre", by the rubrik they count in: with box4 pointed at
     * 1150 and box5/box6 empty, 1150 counts in A-ydelser and no box B account is listed; without rubrik accounts none.
     */
    public function test_the_report_lists_the_accounts_its_postings_without_an_order_come_from(): void
    {
        $mr = self::$mr;
        $names = [];
        foreach (self::rows('SELECT kontonr, beskrivelse FROM kontoplan WHERE regnskabsaar = $1 ORDER BY id', [self::$regnaar]) as $r) {
            $names[(int)$r['kontonr']] ??= trim((string)preg_replace('/\s+/u', ' ', (string)$r['beskrivelse']));
        }
        $account = static fn(string $box): string => $mr[$box] . ' (' . $names[(int)$mr[$box]] . ')';

        $html = $this->report('moms_rubrik');
        $this->assertSame(
            'Konti uden ordre: A-varer ' . $account('box3') . ' · A-ydelser ' . $account('box4')
                . ' · B-varer ' . $account('box5') . ' · B-ydelser ' . $account('box6') . ' · C ' . $account('box7'),
            self::accountList($html),
            'the standard setup: the five rubrik accounts of MR'
        );
        $footnote = trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
        $this->assertStringContainsString('indgår med periodens bevægelse på kontiene ved Konti uden ordre:', $footnote, 'the footnote points to the list');
        $this->assertStringContainsString(
            'I en kassekladde, hvor ingen linje har en momskode (bogført før kassekladden fik momskoder), og for posteringer uden kassekladde kan rapporten ikke se, hvilken linje momsen hører til',
            $footnote,
            'the footnote says where box A cannot tell an EU purchase from a domestic one'
        );
        $this->assertStringContainsString(
            'kan et indenlandsk køb på en rubrik A-konto tælle med som EU-køb, når bilaget også har et EU-køb med samme moms, også et på en anden konto eller i en anden afdeling. Tjek de bilag.',
            $footnote,
            'the footnote says what can go wrong there'
        );

        $saved = self::rows("SELECT id, box3, box4, box5, box6, box7 FROM grupper WHERE art = 'MR'");
        try {
            self::rows("UPDATE grupper SET box4 = $1, box5 = '', box6 = '' WHERE art = 'MR'", [$mr['box6']]);
            $moved = self::accountList($this->report('moms_rubrik', self::$nextDay));
            self::rows("UPDATE grupper SET box3 = '', box4 = '', box7 = '' WHERE art = 'MR'");
            $none = self::accountList($this->report('moms_rubrik', self::$nextDay));
        } finally {
            foreach ($saved as $row) {
                self::rows(
                    'UPDATE grupper SET box3 = $1, box4 = $2, box5 = $3, box6 = $4, box7 = $5 WHERE id = $6',
                    [$row['box3'], $row['box4'], $row['box5'], $row['box6'], $row['box7'], $row['id']]
                );
            }
        }
        $this->assertSame(
            'Konti uden ordre: A-varer ' . $account('box3') . ' · A-ydelser ' . $account('box6') . ' · C ' . $account('box7'),
            $moved,
            'box4 pointed at 1150, box5 and box6 empty (test_a_sales_account_in_box4_is_not_counted_in_box_a)'
        );
        $this->assertSame('Konti uden ordre: ingen. Posteringer uden ordre indgår ikke.', $none, 'no rubrik accounts');
    }

    /**
     * The list of accounts that postings without an order count on, as the Momsrubrikker page shows it under the table
     * (whitespace collapsed), or null when the page has none.
     */
    private static function accountList(string $html): ?string
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $list = (new DOMXPath($doc))->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' momsRubrikKonti ')]");
        if (!$list || $list->length !== 1) {
            return null;
        }
        return trim((string)preg_replace('/\s+/u', ' ', $list->item(0)->textContent));
    }

    /**
     * The boxes of Momsrubrikker for $day ($boxA: A-varer, A-ydelser and I alt; boxes B and C are 0,00), and the
     * acquisition VAT of Momsangivelse for that day ($vat: 7700 and 7720), which is 25 % of box A goods and services.
     *
     * @param array{A-varer:string, A-ydelser:string, I alt:string} $boxA
     * @param array{7700:string, 7720:string} $vat
     */
    private function assertSameVatDay(string $day, array $boxA, array $vat): void
    {
        $screen = self::screenBoxes($this->report('moms_rubrik', $day));
        $this->assertSame(
            ['A-varer' => $boxA['A-varer'], 'A-ydelser' => $boxA['A-ydelser'], 'B-varer' => '0,00', 'B-ydelser' => '0,00', 'C' => '0,00', 'I alt' => $boxA['I alt']],
            $screen,
            "the boxes of $day"
        );
        $rows = self::rowsByFirstCell($this->report('momsangivelse', $day));
        $this->assertSame($vat, ['7700' => $rows['7700'] ?? null, '7720' => $rows['7720'] ?? null], "Momsangivelse of $day: VAT on EU goods and service purchases");
        $this->assertSame(round(self::amount($screen['A-varer']) * 0.25, 2), self::amount($vat['7700']), 'box A goods agrees with its VAT');
        $this->assertSame(round(self::amount($screen['A-ydelser']) * 0.25, 2), self::amount($vat['7720']), 'box A services agrees with its VAT');
    }

    /**
     * A journal's postings except the bank 6480: [bilag, account, debit, credit, transaktioner.moms] each.
     *
     * @return list<array{int, int, float, float, float}>
     */
    private static function journalRows(int $journal): array
    {
        return array_map(
            static fn(array $r): array => [(int)$r['bilag'], (int)$r['kontonr'], round((float)$r['debet'], 2), round((float)$r['kredit'], 2), round((float)$r['moms'], 2)],
            self::rows(
                "SELECT bilag, kontonr, COALESCE(debet, 0) AS debet, COALESCE(kredit, 0) AS kredit, COALESCE(moms, 0) AS moms
                 FROM transaktioner WHERE kladde_id = $1 AND kontonr <> 6480 AND COALESCE(ordre_id, 0) = 0 ORDER BY bilag, kontonr, id",
                [$journal]
            )
        );
    }

    /**
     * The rubrik rows and I alt of a Momsrubrikker page, as the screen shows them.
     *
     * @return array<string, string|null>
     */
    private static function screenBoxes(string $html): array
    {
        $rows = self::rowsByFirstCell($html);
        $screen = [];
        foreach ([...self::RUBRIKKER, 'I alt'] as $kode) {
            $screen[$kode] = $rows[$kode] ?? null;
        }
        return $screen;
    }
}
