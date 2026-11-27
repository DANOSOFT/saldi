<?php
// tests/characterization/debitor/ReminderActionLedgerTest.test.php
//
// The reminder overview at the bottom of Åbne poster (includes/rapportfunc.php openpost()) posts
// Slet, Bogfør, Udskriv, Afslut and Inkasso to debitor/rapport.php with the ticked reminder ids and
// thisDb, the ledger the report was drawn in, and every action checks it against the session's ledger:
//
//   1. rapport.php refuses every reminder action that writes when thisDb is missing or another ledger;
//   2. Slet deletes reminder orders (art R1-R3) with their lines;
//   3. the Udskriv and Afslut popups get thisDb and check it themselves (afslut_rykker.php,
//      rykkerprint.php), and they close or print only reminder orders.
//
// Driven through the pages the way the browser does (support/run_openpost_report.php and
// support/run_reminder_page.php). Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261126 ASR Created for review I4.
// 20261126 ASR Review AR2-1: the reminder page (rykker.php) says "Rykkeren findes ikke længere." for a reminder that no longer exists or an id that is not a reminder.
//              It then goes back to the report, also when its form is posted.
// 20261126 ASR Review LR2-5: the reminder page's Inkasso passes thisDb to inkassoprint.php.
//              inkassoprint.php hands nothing over without it, for another ledger, or for an id that is not a reminder.
// 20261127 ASR Review CL3-4: after either refusal inkassoprint.php goes back to the open-posts report once the message is read (formReminderAlertThenGo()).
// 20261127 ASR Review FXH-1: so does rykkerprint.php after its refusal, without thisDb and for another ledger.
// 20261127 CL/ASR Comments state the current rule; no code change.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class ReminderActionLedgerTest extends CharacterizationTestCase
{
    /** Text 8210, the end of the refusal for a report of another ledger (ASCII, as a script literal escapes the rest). */
    private const REFUSAL = 'arbejder videre med rykkerne';

    protected function setUp(): void
    {
        // The overview lists every R order, so only this test's rows may exist.
        self::rows("DELETE FROM ordrelinjer WHERE ordre_id IN (SELECT id FROM ordrer WHERE art LIKE 'R%')");
        self::rows("DELETE FROM ordrer WHERE art LIKE 'R%'");
        self::rows('DELETE FROM openpost');
    }

    /** @return int ordrer.id of an R1 with a fee line, standing under Åbne rykkere (status 2). */
    private function reminder(array $debtor): int
    {
        $fee = self::$fx->item(['salgspris' => 100, 'kostpris' => 0, 'stocked' => false, 'beskrivelse' => Fixtures::TAG . ' gebyr']);
        $id = self::$fx->order($debtor, [['item' => $fee]], ['art' => 'R1', 'status' => 2, 'momssats' => 0])['id'];
        self::rows("UPDATE ordrer SET fakturanr = ordrenr || '-1', fakturadate = ordredate, betalt = '' WHERE id = $1", [$id]);
        return $id;
    }

    /** @return int ordrer.id of an invoice (art DO, status 3) with one line: an order that is no reminder. */
    private function invoice(array $debtor): int
    {
        $item = self::$fx->item(['salgspris' => 250, 'kostpris' => 0, 'stocked' => false]);
        $id = self::$fx->order($debtor, [['item' => $item]], ['art' => 'DO', 'status' => 3])['id'];
        self::rows("UPDATE ordrer SET fakturanr = ordrenr, fakturadate = ordredate, betalt = '' WHERE id = $1", [$id]);
        return $id;
    }

    /**
     * A button of the reminder overview, posted as its form posts it (includes/rapportfunc.php openpost()).
     *
     * @param list<int> $ids The ticked reminder rows.
     * @param string|null $thisDb The ledger the report was drawn in; null = a form without it (drawn before the upgrade).
     * @return string The page's output.
     */
    private function overviewAction(string $button, array $ids, ?string $thisDb): string
    {
        $post = ['rapportart' => 'openpost', 'submit' => $button, 'rykkerantal' => (string)count($ids), 'rykker_id' => [], 'rykkerbox' => []];
        foreach (array_values($ids) as $n => $id) {
            $post['rykker_id'][(string)($n + 1)] = (string)$id;
            $post['rykkerbox'][(string)($n + 1)] = 'on';
        }
        if ($thisDb !== null) {
            $post['thisDb'] = $thisDb;
        }
        $request = ['get' => ['kontonr' => '', 'skjul_aabenpost' => 'on'], 'post' => $post];
        return self::runChild('run_openpost_report.php', [CharacterizationEnv::sessionId(), json_encode($request)])['stdout'];
    }

    /** @return string The popup page's output for these GET parameters (and the form posted, when $post is given). */
    private function popup(string $page, array $get, array $post = []): string
    {
        $args = [CharacterizationEnv::sessionId(), $page, json_encode((object)$get)];
        if ($post) {
            $args[] = json_encode((object)$post);
        }
        return self::runChild('run_reminder_page.php', $args)['stdout'];
    }

    /** @return string|null The message formReminderAlertThenGo() shows before the page goes back to the report, or null. */
    private static function goneMessage(string $page): ?string
    {
        if (!preg_match('/\(function \(msg, url\) \{.*?\}\)\((".*?"), "rapport\.php\?rapportart=openpost"\);<\/script>/s', $page, $m)) {
            return null;
        }
        return (string)json_decode($m[1]);
    }

    private static function exists(int $id): bool
    {
        return self::one('SELECT id FROM ordrer WHERE id = $1', [$id]) !== null;
    }

    private static function lineCount(int $id): int
    {
        return (int)self::one('SELECT count(*) AS n FROM ordrelinjer WHERE ordre_id = $1', [$id])['n'];
    }

    public function test_slet_deletes_only_reminder_orders(): void
    {
        $debtor = self::$fx->debtor();
        $reminder = $this->reminder($debtor);
        $invoice = $this->invoice($debtor);

        $this->overviewAction('Slet', [$invoice, $reminder], CharacterizationEnv::testDb());

        $this->assertFalse(self::exists($reminder), 'the ticked reminder is deleted');
        $this->assertSame(0, self::lineCount($reminder));
        $this->assertTrue(self::exists($invoice), 'an invoice with a ticked id is not a reminder and stays');
        $this->assertSame(1, self::lineCount($invoice), 'with its line');
    }

    public function test_a_report_without_its_ledger_or_of_another_ledger_changes_nothing(): void
    {
        $debtor = self::$fx->debtor();
        $reminder = $this->reminder($debtor);

        $html = $this->overviewAction('Slet', [$reminder], null);
        $this->assertStringContainsString(self::REFUSAL, $html, 'a report drawn before the upgrade is told to reopen');
        $this->assertTrue(self::exists($reminder), 'Slet from a report without thisDb deletes nothing');

        $this->overviewAction('Slet', [$reminder], CharacterizationEnv::testDb() . '_other');
        $this->assertTrue(self::exists($reminder), 'Slet from a report of another ledger deletes nothing');

        $this->overviewAction('Bogfør', [$reminder], null);
        $this->assertSame('2', self::one('SELECT status FROM ordrer WHERE id = $1', [$reminder])['status'], 'Bogfør from a report without thisDb books nothing');

        foreach (['Udskriv', 'Afslut', 'Inkasso'] as $button) {
            $html = $this->overviewAction($button, [$reminder], null);
            $this->assertStringContainsString(self::REFUSAL, $html, "$button from a report without thisDb");
            $this->assertStringNotContainsString('rykkerprint.php', $html, "$button opens no popup");
            $this->assertStringNotContainsString('afslut_rykker.php', $html, "$button opens no popup");
            $this->assertStringNotContainsString('inkasso.php', $html, "$button opens no popup");
        }
    }

    public function test_the_udskriv_afslut_and_inkasso_popups_get_the_reports_ledger(): void
    {
        $debtor = self::$fx->debtor();
        $reminder = $this->reminder($debtor);
        $thisDb = 'thisDb=' . rawurlencode(CharacterizationEnv::testDb());

        // The popup URL is a JSON string in the page, as rapport_result_popup() encodes it.
        $js = static fn(string $url): string => substr(json_encode($url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), 1, -1);
        $html = $this->overviewAction('Udskriv', [$reminder], CharacterizationEnv::testDb());
        $this->assertStringContainsString($js('rykkerprint.php?rykker_id=' . $reminder . '&kontoantal=1&' . $thisDb), $html);
        $html = $this->overviewAction('Afslut', [$reminder], CharacterizationEnv::testDb());
        $this->assertStringContainsString($js('afslut_rykker.php?rykker_id=' . $reminder . '&kontoantal=1&' . $thisDb), $html);
        $html = $this->overviewAction('Inkasso', [$reminder], CharacterizationEnv::testDb());
        $this->assertStringContainsString('inkasso.php?rykker_id=' . $reminder . '&amp;kontoantal=1&amp;' . $thisDb, $html);
    }

    public function test_afslut_checks_the_ledger_and_closes_only_reminder_orders(): void
    {
        $debtor = self::$fx->debtor();
        $reminder = $this->reminder($debtor);
        $invoice = $this->invoice($debtor);
        $ids = $invoice . ';' . $reminder;

        $html = $this->popup('afslut_rykker.php', ['rykker_id' => $ids, 'kontoantal' => '2']);
        $this->assertStringContainsString(self::REFUSAL, $html);
        $this->assertSame('', self::one('SELECT betalt FROM ordrer WHERE id = $1', [$reminder])['betalt'], 'nothing closed without thisDb');

        $this->popup('afslut_rykker.php', ['rykker_id' => $ids, 'kontoantal' => '2', 'thisDb' => CharacterizationEnv::testDb()]);
        $this->assertSame('on', self::one('SELECT betalt FROM ordrer WHERE id = $1', [$reminder])['betalt'], 'the reminder is closed');
        $this->assertSame('', self::one('SELECT betalt FROM ordrer WHERE id = $1', [$invoice])['betalt'], 'the invoice is not a reminder and is not marked paid');
    }

    /**
     * Review AR2-1: the automatic reminders' page links a reminder that "Ryk alle" or Slet has deleted since. The reminder page
     * says the reminder no longer exists and goes back to the report.
     */
    public function test_the_reminder_page_says_a_reminder_that_no_longer_exists_is_gone(): void
    {
        $debtor = self::$fx->debtor();
        $reminder = $this->reminder($debtor);
        $invoice = $this->invoice($debtor);
        $this->assertStringContainsString('<form name="rykker" action="rykker.php?rykker_id=' . $reminder . '"', $this->popup('rykker.php', ['rykker_id' => (string)$reminder]),
            'precondition: the reminder is shown');
        $this->overviewAction('Slet', [$reminder], CharacterizationEnv::testDb());
        $this->assertFalse(self::exists($reminder), 'precondition: deleted');

        foreach (['deleted' => $reminder, 'an invoice' => $invoice] as $case => $id) {
            $html = $this->popup('rykker.php', ['rykker_id' => (string)$id]);
            $this->assertSame('Rykkeren findes ikke længere.', self::goneMessage($html), $case);
            $this->assertStringNotContainsString('Fatal error', $html, $case);
            $this->assertStringNotContainsString('<form name="rykker"', $html, "$case: nothing is shown");
        }
        // Its form posted from a page opened before it was deleted: nothing is written.
        $html = $this->popup('rykker.php', ['rykker_id' => (string)$reminder], ['rykker_id' => (string)$reminder, 'submit' => 'Opdatér', 'email' => 'x@example.invalid',
            'kontakt' => '', 'valuta' => 'DKK', 'ny_valuta' => 'DKK', 'linjeantal' => '0', 'thisDb' => CharacterizationEnv::testDb()]);
        $this->assertSame('Rykkeren findes ikke længere.', self::goneMessage($html));
        $this->assertFalse(self::exists($reminder));
        $this->assertSame(1, self::lineCount($invoice), 'the invoice keeps its line');
    }

    /**
     * Review LR2-5: the reminder page's Inkasso moves on to inkassoprint.php (GET). The URL carries thisDb, and
     * inkassoprint.php checks it and hands over only a reminder order, as rykkerprint.php.
     * Review CL3-4: after either refusal it goes back to the open-posts report once the message is read, where it left a blank page.
     */
    public function test_inkassoprint_checks_the_ledger_and_hands_over_only_reminder_orders(): void
    {
        $debtor = self::$fx->debtor();
        $reminder = $this->reminder($debtor);
        $invoice = $this->invoice($debtor);
        $agency = self::$fx->debtor();   // the inkasso company, without an e-mail address, so nothing is mailed
        self::rows("UPDATE adresser SET email = '' WHERE id = $1", [$agency['id']]);
        self::rows("UPDATE grupper SET box9 = $1 WHERE art = 'DIV' AND kodenr = '4'", [(string)$agency['id']]);

        // The reminder page's Inkasso (its form posts thisDb, the ledger it was drawn in).
        $html = $this->popup('rykker.php', ['rykker_id' => (string)$reminder], ['rykker_id' => (string)$reminder, 'inkasso' => 'Inkasso', 'email' => '',
            'kontakt' => '', 'valuta' => 'DKK', 'ny_valuta' => 'DKK', 'linjeantal' => '1', 'thisDb' => CharacterizationEnv::testDb()]);
        $this->assertStringContainsString('URL=inkassoprint.php?rykker_id=' . $reminder . '&inkasso=' . $agency['id'] . '&thisDb=' . rawurlencode(CharacterizationEnv::testDb()) . '"', $html);

        $back = '<a href = rykker.php?rykker_id=' . $reminder . '>Tilbage</a>';
        foreach (['without thisDb' => null, 'of another ledger' => CharacterizationEnv::testDb() . '_other'] as $case => $thisDb) {
            $get = ['rykker_id' => (string)$reminder, 'inkasso' => (string)$agency['id']] + ($thisDb === null ? [] : ['thisDb' => $thisDb]);
            $html = $this->popup('inkassoprint.php', $get);
            $this->assertStringContainsString(self::REFUSAL, $html, $case);
            $this->assertSame('Regnskabet er skiftet, eller rapporten er for gammel. Åbn rapporten igen i det ønskede regnskab, før du arbejder videre med rykkerne.',
                self::goneMessage($html), "$case: the message, then back to the report");
            $this->assertStringContainsString('<noscript><meta http-equiv="refresh" content="0;URL=rapport.php?rapportart=openpost"></noscript>', $html, "$case: also without script");
            $this->assertStringNotContainsString($back, $html, "$case: nothing is handed over");
        }
        $html = $this->popup('inkassoprint.php', ['rykker_id' => (string)$invoice, 'inkasso' => (string)$agency['id'], 'thisDb' => CharacterizationEnv::testDb()]);
        $this->assertSame('Rykkeren findes ikke længere.', self::goneMessage($html), 'an invoice id is not a reminder: the message, then back to the report');
        $this->assertStringNotContainsString('<a href = rykker.php?rykker_id=' . $invoice . '>Tilbage</a>', $html);

        $html = $this->popup('inkassoprint.php', ['rykker_id' => (string)$reminder, 'inkasso' => (string)$agency['id'], 'thisDb' => CharacterizationEnv::testDb()]);
        $this->assertStringNotContainsString(self::REFUSAL, $html);
        $this->assertStringContainsString($back, $html, 'the reminder of this ledger is handed over');
    }

    /**
     * Review FXH-1: after the refusal the page goes back to the open-posts report once the message is read, where it left a
     * blank page (the reminder page's Udskriv and Send open it in the same window).
     */
    public function test_rykkerprint_checks_the_ledger_and_prints_only_reminder_orders(): void
    {
        $debtor = self::$fx->debtor();
        $reminder = $this->reminder($debtor);
        $invoice = $this->invoice($debtor);

        foreach (['without thisDb' => null, 'of another ledger' => CharacterizationEnv::testDb() . '_other'] as $case => $thisDb) {
            $get = ['rykker_id' => (string)$reminder, 'rykkernr' => '1', 'kontoantal' => '1'] + ($thisDb === null ? [] : ['thisDb' => $thisDb]);
            $html = $this->popup('rykkerprint.php', $get);
            $this->assertStringContainsString(self::REFUSAL, $html, $case);
            $this->assertSame('Regnskabet er skiftet, eller rapporten er for gammel. Åbn rapporten igen i det ønskede regnskab, før du arbejder videre med rykkerne.',
                self::goneMessage($html), "$case: the message, then back to the report");
            $this->assertStringContainsString('<noscript><meta http-equiv="refresh" content="0;URL=rapport.php?rapportart=openpost"></noscript>', $html, "$case: also without script");
            $this->assertStringNotContainsString('udskriv.php', $html, "$case: nothing is printed");
        }

        $html = $this->popup('rykkerprint.php', ['rykker_id' => (string)$invoice, 'kontoantal' => '1', 'thisDb' => CharacterizationEnv::testDb()]);
        $this->assertStringNotContainsString('udskriv.php', $html, 'an invoice id is not printed as a reminder');

        $html = $this->popup('rykkerprint.php', ['rykker_id' => (string)$reminder, 'kontoantal' => '1', 'thisDb' => CharacterizationEnv::testDb()]);
        $this->assertStringContainsString('udskriv.php?ps_fil=', $html, 'the reminder itself is printed');
    }
}
