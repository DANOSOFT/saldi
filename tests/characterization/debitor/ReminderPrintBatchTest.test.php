<?php
// tests/characterization/debitor/ReminderPrintBatchTest.test.php
//
// Sending reminders with "Send pr. mail" (includes/formfunk.php rykkerprint(), behind Udskriv under Åbne
// rykkere and Send in debitor/rykker.php):
//
//   1. A reminder whose PDF cannot be made (ps2pdf) used to stop the whole batch with an uncaught exception,
//      printed inside an open HTML comment: the reminders before it were mailed, the ones after it were not,
//      and nothing said which. Now it is named ("Rykker X er ikke sendt: ...") and the others are sent.
//   2. A reminder the automatic flow tracks, sent by hand while the flow is off in this ledger (switched off,
//      or a copied ledger), stopped with a fatal error. Now it is refused with a message (owner default Q10).
//   3. A reminder the automatic flow drafted and that is sent later goes out dated on the day it is sent, so its
//      letter and its payment deadline are not those of the day it was drafted.
//
// Driven through support/run_rykkerprint.php with a ps2pdf stand-in and a send_mails() recorder.
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261126 ASR Created for review I7.
// 20261126 ASR Review M1: the batch's message is read before the page moves on to the report (no meta refresh outside <noscript>).
// 20261126 ASR Review GPT-R-7: a tracked draft approved later than it was made is dated on the day it is sent, before it is rendered.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class ReminderPrintBatchTest extends CharacterizationTestCase
{
    protected function setUp(): void
    {
        self::rows("DELETE FROM ordrelinjer WHERE ordre_id IN (SELECT id FROM ordrer WHERE art LIKE 'R%')");
        self::rows("DELETE FROM ordrer WHERE art LIKE 'R%'");
        self::rows('DELETE FROM openpost');
    }

    /** @return array{id: int, nr: string} An R1 with a 100 kr. fee line and "Send pr. mail", as Ny rykker makes it for an e-mail customer. */
    private function mailedReminder(): array
    {
        $debtor = self::$fx->debtor();
        $fee = self::$fx->item(['salgspris' => 100, 'kostpris' => 0, 'stocked' => false, 'beskrivelse' => Fixtures::TAG . ' gebyr']);
        $id = self::$fx->order($debtor, [['item' => $fee]], ['art' => 'R1', 'status' => 2, 'momssats' => 0])['id'];
        self::rows("UPDATE ordrer SET fakturanr = ordrenr || '-1', fakturadate = ordredate, mail_fakt = 'on', betalt = '', pbs = '' WHERE id = $1", [$id]);
        return ['id' => $id, 'nr' => (string)self::one('SELECT fakturanr FROM ordrer WHERE id = $1', [$id])['fakturanr']];
    }

    /**
     * @param list<int> $ids Reminders in the batch.
     * @param list<int> $failing Reminders whose PDF cannot be made.
     * @return array{printed: string, mails: list<array{ordre_id: int, email: string, pdf: string}>}
     */
    private function send(array $ids, array $failing = [], bool $automation = false): array
    {
        return $this->runChildJson('run_rykkerprint.php', [CharacterizationEnv::testDb(), implode(';', $ids), implode(';', $failing), $automation ? '1' : '0']);
    }

    /** The message is not inside an HTML comment the page left open. */
    private function assertOutsideComments(string $printed, string $text): void
    {
        $at = strpos($printed, $text);
        $this->assertNotFalse($at, "the page says: $text\n" . $printed);
        $before = substr($printed, 0, $at);
        $this->assertSame(substr_count($before, '<!--'), substr_count($before, '-->'), 'the message is printed inside an open HTML comment');
    }

    public function test_a_reminder_whose_pdf_fails_is_named_and_the_others_are_sent(): void
    {
        $first = $this->mailedReminder();
        $second = $this->mailedReminder();

        $run = $this->send([$first['id'], $second['id']], [$first['id']]);

        $this->assertSame([$second['id']], array_column($run['mails'], 'ordre_id'), 'the reminder after the failing one is mailed');
        $this->assertSame('%PDF-', $run['mails'][0]['pdf']);
        $this->assertOutsideComments($run['printed'], 'Rykker ' . $first['nr'] . ' er ikke sendt: Rykkerens PDF kunne ikke dannes.');
        $this->assertStringNotContainsString('Rykker ' . $second['nr'] . ' er ikke sendt', $run['printed']);
        $this->assertMessageReadBeforeMovingOn($run['printed'], 'rapport.php?rapportart=openpost');
    }

    /**
     * Review M1: Saldi's message box does not block like alert(), so a meta refresh right after it moved the page on before the
     * message could be read. The page moves on to $url when OK is pressed; a meta refresh only for a browser without script.
     */
    private function assertMessageReadBeforeMovingOn(string $printed, string $url): void
    {
        $this->assertSame(1, substr_count($printed, 'SaldiDialog.alert('), 'one message');
        $this->assertStringContainsString('SaldiDialog.alert(msg).then(go)', $printed);
        $this->assertStringContainsString(', ' . json_encode($url, JSON_UNESCAPED_SLASHES) . ');</script>', $printed, 'it goes on to ' . $url);
        $this->assertStringNotContainsString('http-equiv="refresh"', preg_replace('#<noscript>.*?</noscript>#s', '', $printed));
    }

    public function test_a_tracked_draft_sent_later_goes_out_dated_on_the_day_it_is_sent(): void
    {
        $draft = $this->mailedReminder();
        $order = self::one('SELECT konto_id, kontonr FROM ordrer WHERE id = $1', [$draft['id']]);
        $drafted = (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->modify('-30 days')->format('Y-m-d');
        $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
        // The invoice it reminds of, and the reminder as the automatic flow drafted it 30 days ago.
        $invoice = self::one("INSERT INTO openpost (konto_id, konto_nr, faktnr, amount, udlignet, transdate, forfaldsdate, valuta, valutakurs, projekt, beskrivelse)
            VALUES ($1, $2, 'CT-R7', 500, '0', $3, $3, 'DKK', 100, '', 'Faktura CT-R7') RETURNING id", [(int)$order['konto_id'], $order['kontonr'], $drafted]);
        self::rows("INSERT INTO ordrelinjer (ordre_id, posnr, enhed, beskrivelse) VALUES ($1, 0, $2, 'Faktura CT-R7')", [$draft['id'], (string)$invoice['id']]);
        self::rows('UPDATE ordrer SET ordredate = $2, levdate = $2, fakturadate = $2 WHERE id = $1', [$draft['id'], $drafted]);
        $this->send([], [], true);   // installs the tables
        self::rows('DELETE FROM reminder_automation_jobs');
        self::rows('DELETE FROM reminder_automation_config');
        self::rows('DELETE FROM reminder_automation_events');
        // The flow is on in this ledger on this installation (as saving it on its page binds it), and the draft was approved.
        $binding = hash('sha256', gethostname() . '|' . realpath(dirname(__DIR__, 3)) . '|' . CharacterizationEnv::testDb());
        self::rows("INSERT INTO reminder_automation_config (id, mode, binding, actor) VALUES (1, 'approval', $1, 'test')", [$binding]);
        self::rows("INSERT INTO reminder_automation_jobs (order_id, mode, state, actor) VALUES ($1, 'approval', 'queued', 'test')", [$draft['id']]);

        $run = $this->send([$draft['id']], [], true);

        $this->assertSame([$draft['id']], array_column($run['mails'], 'ordre_id'), $run['printed']);
        $dates = self::one('SELECT ordredate, fakturadate FROM ordrer WHERE id = $1', [$draft['id']]);
        $this->assertSame([$today, $today], [(string)$dates['ordredate'], (string)$dates['fakturadate']], 'dated on the day it was sent, before it was rendered');
        $job = self::one('SELECT state, sent_date FROM reminder_automation_jobs WHERE order_id = $1', [$draft['id']]);
        $this->assertSame(['sent', $today], [$job['state'], (string)$job['sent_date']]);
    }

    public function test_a_tracked_reminder_sent_while_the_flow_is_off_is_refused_with_a_message(): void
    {
        $tracked = $this->mailedReminder();
        $manual = $this->mailedReminder();
        // The flow made it as a draft and was switched off afterwards (no reminder_automation_config row = off).
        $this->send([], [], true);   // installs the tables
        self::rows('DELETE FROM reminder_automation_jobs');
        self::rows('DELETE FROM reminder_automation_config');
        self::rows("INSERT INTO reminder_automation_jobs (order_id, mode, state, actor) VALUES ($1, 'approval', 'approval', 'reminder_due')", [$tracked['id']]);

        $run = $this->send([$tracked['id'], $manual['id']], [], true);

        $this->assertSame([$manual['id']], array_column($run['mails'], 'ordre_id'), 'only the reminder the flow does not track is sent');
        $this->assertOutsideComments($run['printed'], 'Rykker ' . $tracked['nr'] . ' er ikke sendt:');
        $this->assertStringContainsString('eller slet kladden', $run['printed'], 'text 8211 says what to do');
        $this->assertSame('approval', (string)self::one('SELECT state FROM reminder_automation_jobs WHERE order_id = $1', [$tracked['id']])['state'], 'the draft is left as it was');
        $this->assertMessageReadBeforeMovingOn($run['printed'], 'rapport.php?rapportart=openpost');
    }
}
