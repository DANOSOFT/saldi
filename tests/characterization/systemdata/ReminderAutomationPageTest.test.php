<?php
// tests/characterization/systemdata/ReminderAutomationPageTest.test.php
//
// The automatic reminder page, systemdata/reminderAutomation.php (PR #1147), on this line.
//
// Pins, driven through the page as a browser does:
// - it is drawn in the standard settings layout (includes/settings/layout.php): the hub menu with
//   Rykkere & inkasso marked, Tilbage and the title, and its own forms in the content column;
// - before the update chain has installed the queue it says so and offers nothing to save;
// - saving a mode binds it to this installation and ledger (sha256 of host, installation folder
//   and database name), so the flow is armed here and the run button is offered;
// - a configuration that carries another binding, as a copy of the ledger restored under another
//   name or on another installation does, is not armed: no run button and a notice to save again;
// - a post with a stale form token or from another ledger changes nothing.
//
// The queue's tables are made with the statements of includes/opdat_func/reminderAutomation.php,
// which includes/betweenUpdates.php runs at login.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261126 CL/ASR Created (Stripe port, #1147).
// 20261126 ASR Review I14: "Du kan også starte en kørsel her." only where the run button is offered.
// 20261126 ASR Review GPT-R-5: a paused job whose sending attempt is uncertain still asks for the check of the mail server's log.
// 20261126 ASR Review GPT-R-6: a job whose sending attempt is uncertain can be registered as sent on the day the mail server's log shows.
// 20261126 ASR Review AR2-1: a job whose reminder was deleted shows "Rykker slettet" without a link to the reminder page and without actions.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class ReminderAutomationPageTest extends CharacterizationTestCase
{
    private const TABLES = ['reminder_automation_events', 'reminder_automation_jobs', 'reminder_automation_config'];

    protected function setUp(): void
    {
        parent::setUp();
        self::dropQueue();
    }

    protected function tearDown(): void
    {
        self::dropQueue();
        parent::tearDown();
    }

    private static function dropQueue(): void
    {
        foreach (self::TABLES as $table) {
            self::rows('DROP TABLE IF EXISTS ' . $table);
        }
    }

    /** The update chain's statements (includes/opdat_func/reminderAutomation.php), run on the test ledger. */
    private static function installQueue(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/includes/opdat_func/reminderAutomation.php');
        preg_match_all('/db_modify\("(.*?)", __FILE__/s', $source, $m);
        self::assertCount(4, $m[1], 'the four statements of reminder_automation_schema_ensure()');
        foreach ($m[1] as $sql) {
            self::rows($sql);
        }
    }

    /** @return array{status:mixed, html:string} */
    private function page(array $post = []): array
    {
        $json = $this->runChildJson('run_reminder_automation_page.php', [$post ? http_build_query($post) : '', CharacterizationEnv::sessionId()]);
        return ['status' => $json['status'], 'html' => (string) $json['html']];
    }

    private static function csrf(string $html): string
    {
        self::assertMatchesRegularExpression('/name="csrf_token" value="([0-9a-f]{64})"/', $html, 'the forms carry the session token');
        preg_match('/name="csrf_token" value="([0-9a-f]{64})"/', $html, $m);
        return $m[1];
    }

    private static function binding(string $ledger): string
    {
        return hash('sha256', gethostname() . '|' . realpath(dirname(__DIR__, 3)) . '|' . $ledger);
    }

    public function test_the_page_is_drawn_in_the_settings_layout_and_says_when_the_queue_is_missing(): void
    {
        $html = $this->page()['html'];

        $this->assertStringContainsString('<nav class="st-tabs st-menu"', $html, 'the hub menu of the settings pages');
        $this->assertMatchesRegularExpression('/<a class="st-menu-row[^"]* on" href="settingsSection\.php\?s=sales\.reminders"/', $html, 'Rykkere & inkasso is marked');
        $this->assertStringContainsString('data-back', $html, 'Tilbage above the title');
        $this->assertStringContainsString('<h1>Automatiske rykkere</h1>', $html);
        $this->assertStringContainsString('Vælg rykkerflow', $html);
        $this->assertStringContainsString('Automatisk rykkerflow er ikke installeret i dette regnskab endnu.', $html);
        $this->assertMatchesRegularExpression('/<fieldset disabled/', $html, 'nothing to save before the update chain ran');
    }

    public function test_saving_binds_the_mode_to_this_installation_and_ledger_and_a_copy_is_not_armed(): void
    {
        self::installQueue();
        $html = $this->page()['html'];
        $this->assertStringNotContainsString('ikke installeret', $html);
        $this->assertStringNotContainsString('Kør det valgte rykkerflow nu', $html, 'off: nothing to run');
        $this->assertStringNotContainsString('Du kan også starte en kørsel her', $html, 'off: the page does not offer a run it has no button for');

        $saved = $this->page(['csrf_token' => self::csrf($html), 'ledger' => CharacterizationEnv::testDb(), 'action' => 'configure', 'mode' => 'approval']);
        $this->assertSame(303, $saved['status'], 'the page redirects after the post');
        $row = self::one('SELECT mode, binding FROM reminder_automation_config WHERE id = 1');
        $this->assertSame('approval', $row['mode']);
        $this->assertSame(self::binding(CharacterizationEnv::testDb()), $row['binding'], 'bound to this installation and ledger');
        $this->assertSame('1', (string) self::one("SELECT count(*) AS n FROM reminder_automation_events WHERE action = 'configure'")['n']);

        $html = $this->page()['html'];
        $this->assertStringContainsString('Rykkerflowet er gemt.', $html);
        $this->assertStringContainsString('Kør det valgte rykkerflow nu', $html, 'armed here');
        $this->assertStringContainsString('Du kan også starte en kørsel her.', $html, 'with the button');
        $this->assertStringNotContainsString('Flowet er inaktivt her', $html);

        // A copy of the ledger restored under another name carries the original's binding.
        self::rows('UPDATE reminder_automation_config SET binding = $1 WHERE id = 1', [self::binding(CharacterizationEnv::testDb() . '_kopi')]);
        $html = $this->page()['html'];
        $this->assertStringContainsString('Flowet er inaktivt her. Gem indstillingen for at aktivere det på denne installation.', $html);
        $this->assertStringNotContainsString('Kør det valgte rykkerflow nu', $html, 'a copy is not armed');
        $this->assertStringNotContainsString('Du kan også starte en kørsel her', $html);
    }

    /** @return int ordrer.id of an R1 with a fee line, as the automatic flow makes it. */
    private function reminderOrder(): int
    {
        $debtor = self::$fx->debtor();
        $fee = self::$fx->item(['salgspris' => 100, 'kostpris' => 0, 'stocked' => false, 'beskrivelse' => Fixtures::TAG . ' gebyr']);
        $id = self::$fx->order($debtor, [['item' => $fee]], ['art' => 'R1', 'status' => 2, 'momssats' => 0])['id'];
        self::rows("UPDATE ordrer SET fakturanr = ordrenr || '-1', fakturadate = ordredate, mail_fakt = 'on', betalt = '', pbs = '' WHERE id = $1", [$id]);
        return (int)$id;
    }

    /** A tracked reminder in $state; with $attempt, its sending attempt was claimed a day ago (send() logs 'sending'). */
    private function job(string $state, bool $attempt): int
    {
        $id = $this->reminderOrder();
        self::rows("INSERT INTO reminder_automation_jobs (order_id, mode, state, actor, attempted_at) VALUES ($1, 'approval', $2, 'test', "
            . ($attempt ? "current_timestamp - interval '1 day'" : 'NULL') . ')', [$id, $state]);
        if ($attempt) {
            self::rows("INSERT INTO reminder_automation_events (order_id, action, actor, created_at) VALUES ($1, 'sending', 'test', current_timestamp - interval '1 day')", [$id]);
        }
        return $id;
    }

    /** @return string The row of the job's order in the jobs table. */
    private static function jobRow(string $html, int $id): string
    {
        self::assertMatchesRegularExpression('#<tr><td><a href="../debitor/rykker.php\?rykker_id=' . $id . '">.*?</tr>#s', $html);
        preg_match('#<tr><td><a href="../debitor/rykker.php\?rykker_id=' . $id . '">.*?</tr>#s', $html, $m);
        return $m[0];
    }

    public function test_a_paused_job_whose_attempt_is_uncertain_still_asks_for_the_mail_server_check(): void
    {
        self::installQueue();
        $paused = $this->job('held', true);
        $draft = $this->job('held', false);
        $html = $this->page()['html'];
        $check = 'Jeg har kontrolleret mailserverens log og bekræftet, at rykkeren ikke blev sendt.';
        $this->assertStringContainsString($check, self::jobRow($html, $paused), 'paused after an uncertain attempt');
        $this->assertStringNotContainsString($check, self::jobRow($html, $draft), 'a draft paused before any attempt');
    }

    public function test_an_uncertain_attempt_can_be_registered_as_sent_on_the_day_it_went_out(): void
    {
        self::installQueue();
        $failed = $this->job('failed', true);
        $draft = $this->job('approval', false);
        $html = $this->page()['html'];
        $row = self::jobRow($html, $failed);
        $this->assertStringContainsString('<input type="hidden" name="action" value="confirm_sent">', $row);
        $this->assertStringContainsString('Jeg har kontrolleret mailserverens log og bekræftet, at rykkeren blev sendt.', $row);
        $this->assertMatchesRegularExpression('/<input type="date" name="sent_date" required value="" min="\d{4}-\d{2}-\d{2}" max="\d{4}-\d{2}-\d{2}">/', $row,
            'the day is entered, not guessed');
        $this->assertStringContainsString('Registrér som sendt', $row);
        $this->assertStringNotContainsString('confirm_sent', self::jobRow($html, $draft), 'a draft that was never attempted');

        $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
        $saved = $this->page(['csrf_token' => self::csrf($html), 'ledger' => CharacterizationEnv::testDb(), 'action' => 'confirm_sent',
            'order_id' => (string)$failed, 'checked_delivered' => 'yes', 'sent_date' => $today]);
        $this->assertSame(303, $saved['status']);
        $job = self::one('SELECT state, sent_date, channel FROM reminder_automation_jobs WHERE order_id = $1', [$failed]);
        $this->assertSame(['sent', $today, 'classic'], [$job['state'], (string)$job['sent_date'], $job['channel']]);
        $html = $this->page()['html'];
        $this->assertStringContainsString('Rykkeren er registreret som sendt den ' . (new DateTimeImmutable($today))->format('d.m.Y') . '.', $html);
        $this->assertStringNotContainsString('<form', self::jobRow($html, $failed), 'a sent reminder offers nothing');
    }

    public function test_a_job_whose_reminder_was_deleted_shows_rykker_slettet_without_a_link_or_actions(): void
    {
        self::installQueue();
        $sent = $this->job('sent', false);
        $failed = $this->job('failed', true);
        $kept = $this->job('approval', false);
        foreach ([$sent, $failed] as $id) {
            self::rows('DELETE FROM ordrelinjer WHERE ordre_id = $1', [$id]);
            self::rows('DELETE FROM ordrer WHERE id = $1', [$id]);
        }
        $html = $this->page()['html'];
        foreach ([$sent, $failed] as $id) {
            $this->assertStringNotContainsString('rykker.php?rykker_id=' . $id . '"', $html, 'no link to a reminder that is gone');
            $this->assertStringNotContainsString('name="order_id" value="' . $id . '"', $html, 'and nothing to do with it');
        }
        $this->assertSame(2, substr_count($html, '<tr><td>Rykker slettet</td>'), 'each row says the reminder is deleted');
        $this->assertStringContainsString('name="order_id" value="' . $kept . '"', self::jobRow($html, $kept), 'a job with its reminder is unchanged');
    }

    public function test_a_stale_token_or_another_ledger_changes_nothing(): void
    {
        self::installQueue();
        $token = self::csrf($this->page()['html']);

        $this->page(['csrf_token' => str_repeat('0', 64), 'ledger' => CharacterizationEnv::testDb(), 'action' => 'configure', 'mode' => 'automatic']);
        $this->assertStringContainsString('Siden er udløbet', $this->page()['html']);
        $this->page(['csrf_token' => $token, 'ledger' => 'another_ledger', 'action' => 'configure', 'mode' => 'automatic']);
        $this->assertStringContainsString('Regnskabet er skiftet', $this->page()['html']);

        $this->assertSame([], self::rows('SELECT mode FROM reminder_automation_config'), 'no configuration was saved');
    }
}
