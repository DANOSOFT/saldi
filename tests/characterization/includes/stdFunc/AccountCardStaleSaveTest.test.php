<?php
// tests/characterization/includes/stdFunc/AccountCardStaleSaveTest.test.php
//
// A Gem on a debtor or creditor card drawn before someone else saved the card
// writes nothing and says so (account_card_begin_save() in
// includes/stdFunc/accountCardVersion.php).
//
// The cards wrote every field from the posted form. Two users had the same
// customer open; B saved a Bemærkning, then A pressed Gem/opdatér on the card
// A had drawn before, to save a new phone number. A's form still held the old,
// empty Bemærkning, and B's note was gone without a word (testlab round 2,
// concurrent-editing DEVY-2, 3 of 3 rounds; seen before on master and live).
// The debtor card also wrote back the e-mails and delivery addresses it was
// drawn with, deleting ones added since.
//
// Each card is drawn and saved through the page itself, with the form a
// browser would post: every field of the card's form as it was drawn, plus the
// pressed Gem button. A form posted again after its own save (a double click,
// or a reload of the page the save answered with) is still saved.
//
// Two colleagues at once: a Gem that arrives while the colleague's save holds
// the card waits for it and is then refused, instead of both passing the
// check before either has written. A card drawn while the colleague's save
// landed carries the version of the fields it shows, not of the newer card.
// The colleague is a second connection that does what a save does: lock the
// account, write the note, commit.
//
// A form without a version is refused as well. PHP keeps only the first
// max_input_vars (1000) fields of a post, so a card with 500 contact persons
// arrives without the fields after the cut. With the version printed after
// the contact persons, A's stale Gem arrived without it, but with id, tlf and
// the empty note, and was saved. The version is now near the top of each
// form, so a cut post of a current card is saved and a stale one refused.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
// 20261114 CL/ASR Merge of master: the debtor card (debitor/debitorkort.php) is left out, as it uses master's MB-82 check (debitorkortIncludes/staleSave.php, DebitorkortStaleSaveTest).
//                 The Sager customer card and both creditor cards keep this check and its cases.
// 20261115 CL/ASR Integration: the runner gets the session from CharacterizationEnv::sessionId(), as master's test environment (2bd490c6) has no SESSION_ID constant.

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../../support/CharacterizationTestCase.php';

final class AccountCardStaleSaveTest extends CharacterizationTestCase
{
    private const STALE = 'Kortet er blevet gemt af en anden eller i et andet vindue, efter du åbnede det. Dine ændringer er ikke gemt. Kortet vises nu, som det er gemt, så du kan indtaste dem igen.';
    private const INCOMPLETE = 'Kortet er ikke gemt, fordi formularen ikke kom helt frem. Kortet vises nu, som det er gemt, så du kan indtaste dine ændringer igen.';
    /** PHP's default, which the web image keeps: the fields of a post after the 1000th are dropped. */
    private const MAX_INPUT_VARS = 1000;
    private const NOTE = 'Levering kun hverdage før kl. 14, Århus lager';
    private const HELPER = 'includes/stdFunc/accountCardVersion.php:';

    /** @var list<int> adresser.id of the accounts a test made */
    private array $accounts = [];
    private static bool $warmedUp = false;

    /**
     * The cards: page, account kind, query string that draws account %d, the form's name and the Gem button's name.
     *
     * @return array<string, array{0:string, 1:string, 2:string, 3:string, 4:string}>
     */
    public static function cards(): array
    {
        return [
            // The debtor card (debitor/debitorkort.php) left: it uses master's MB-82 check, pinned by DebitorkortStaleSaveTest.
            'Sager customer card (debitor/debkort_save.php)' => ['sager/kunder.php', 'D', 'funktion=ret_kunde&konto_id=%d', 'ret_kunde', 'gem_rettelse'],
            'creditor card' => ['kreditor/kreditorkort.php', 'K', 'id=%d', 'kreditorkort', 'submit'],
            'old creditor card (includes/kreditorkort.php)' => ['includes/kreditorkort.php', 'K', 'id=%d', 'kreditorkort', 'submit'],
        ];
    }

    protected function setUp(): void
    {
        if (!self::$warmedUp) {
            // The first debtor card a tenant draws creates its categories row (grupper DebInfo), so that card has no
            // status fields, and its Gem stops on count(null) for them - before this fix too. Draw one card up front,
            // so whichever test runs first saves a card as every later one does.
            $this->request('debitor/debitorkort.php', 'id=' . $this->account('D'));
            self::$warmedUp = true;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->accounts as $id) {
            self::rows('DELETE FROM kontakt_emails WHERE konto_id = $1', [$id]);
            self::rows('DELETE FROM delivery_addresses WHERE account_id = $1', [$id]);
            self::rows('DELETE FROM ansatte WHERE konto_id = $1', [$id]);
            self::rows('DELETE FROM adresser WHERE id = $1', [$id]);
        }
        $this->accounts = [];
    }

    private function account(string $art): int
    {
        $account = $art === 'K' ? self::$fx->creditor() : self::$fx->debtor();
        $this->accounts[] = $account['id'];
        return $account['id'];
    }

    /**
     * One request to a card; it may raise no warning from includes/stdFunc/accountCardVersion.php.
     *
     * @param list<array{0:string, 1:string}> $form the posted fields in form order, [] for a GET
     * @return array{html:string, alerts:list<string>, warnings:list<string>, posted:list<string>}
     */
    private function request(string $page, string $query, array $form = []): array
    {
        return $this->finish($this->start($page, $query, $form));
    }

    /**
     * Start a request to a card in a child process and return without waiting for it.
     * PHP parses the post with max_input_vars at its default, as the web server does.
     *
     * @param list<array{0:string, 1:string}> $form the posted fields in form order, [] for a GET
     * @return array{proc:resource, out:resource, err:resource, what:string}
     */
    private function start(string $page, string $query, array $form = []): array
    {
        $body = implode('&', array_map(static fn(array $field): string => urlencode($field[0]) . '=' . urlencode($field[1]), $form));
        $err = tmpfile();
        $cmd = [PHP_BINARY, '-d', 'max_input_vars=' . self::MAX_INPUT_VARS, __DIR__ . '/../../support/run_account_card_page.php', $page, $query, $body, CharacterizationEnv::sessionId()];
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => $err], $pipes);
        $this->assertIsResource($proc, 'the child php starts');
        fclose($pipes[0]);
        return ['proc' => $proc, 'out' => $pipes[1], 'err' => $err, 'what' => ($form === [] ? 'GET' : 'POST') . " $page?$query"];
    }

    /**
     * Wait for a request start() began and return what the page answered.
     *
     * @param array{proc:resource, out:resource, err:resource, what:string} $request
     * @return array{html:string, alerts:list<string>, warnings:list<string>, posted:list<string>}
     */
    private function finish(array $request): array
    {
        $stdout = (string)stream_get_contents($request['out']);
        fclose($request['out']);
        proc_close($request['proc']);
        rewind($request['err']);
        $stderr = (string)stream_get_contents($request['err']);
        fclose($request['err']);
        $lines = array_values(array_filter(explode("\n", trim($stdout))));
        $res = $lines !== [] ? json_decode(end($lines), true) : null;
        $this->assertIsArray($res, "{$request['what']} answered no JSON line.\nstdout: $stdout\nstderr: $stderr");
        $this->assertStringNotContainsString('Fatal error', $stderr . $res['html'], "{$request['what']} crashed");
        $own = array_values(array_filter($res['warnings'], static fn(string $warning): bool => str_starts_with($warning, self::HELPER)));
        $this->assertSame([], $own, "{$request['what']} raised warnings in the version helper");
        return $res;
    }

    /**
     * Wait until a request start() began is blocked by a lock the colleague's connection holds.
     *
     * @param array{proc:resource, out:resource, err:resource, what:string} $request
     * @param resource|\PgSql\Connection $colleague
     */
    private function waitUntilBlockedBy(array $request, $colleague): void
    {
        $pid = pg_get_pid($colleague);
        $deadline = microtime(true) + 30;
        do {
            $blocked = self::one('SELECT count(*) AS n FROM pg_stat_activity WHERE datname = current_database() AND $1 = ANY(pg_blocking_pids(pid))', [$pid]);
            if ((int)$blocked['n'] > 0) {
                return;
            }
            if (!proc_get_status($request['proc'])['running']) {
                $this->fail("{$request['what']} ended without waiting for the colleague's lock: " . json_encode($this->finish($request)['alerts']));
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $this->fail("{$request['what']} did not wait for the colleague's lock within 30 s");
    }

    /**
     * A second connection to the tenant: the colleague whose save is under way.
     *
     * @return resource|\PgSql\Connection
     */
    private static function colleague()
    {
        return CharacterizationEnv::connect(CharacterizationEnv::testDb());
    }

    /**
     * Draw the card of account $id and return what a browser posts when its Gem is pressed.
     *
     * @return array{query:string, fields:list<array{0:string, 1:string}>}
     */
    private function draw(string $page, string $query, string $formName, string $button, int $id): array
    {
        $res = $this->request($page, sprintf($query, $id));
        $form = self::formOf($res['html'], $formName, $button);
        $this->assertContains(['id', (string)$id], $form['fields'], "the $formName form carries the account");
        $versions = array_values(array_filter($form['fields'], static fn(array $field): bool => $field[0] === 'card_version'));
        $this->assertCount(1, $versions, "the $formName form carries one card_version");
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $versions[0][1]);
        return $form;
    }

    /**
     * Post a drawn form with the given fields changed, as the user typed them, and the given unticked boxes ticked.
     *
     * @param array{query:string, fields:list<array{0:string, 1:string}>} $form
     * @param array<string, string> $changes
     * @param list<string> $tick
     * @return array{html:string, alerts:list<string>, warnings:list<string>, posted:list<string>}
     */
    private function save(string $page, array $form, array $changes = [], array $tick = []): array
    {
        return $this->request($page, $form['query'], $this->typed($form, $changes, $tick));
    }

    /**
     * The fields of a drawn form with the given fields changed and the given unticked boxes ticked.
     *
     * @param array{query:string, fields:list<array{0:string, 1:string}>} $form
     * @param array<string, string> $changes
     * @param list<string> $tick
     * @return list<array{0:string, 1:string}>
     */
    private function typed(array $form, array $changes = [], array $tick = []): array
    {
        $fields = $form['fields'];
        foreach ($tick as $name) {
            $this->assertNotContains($name, array_column($fields, 0), "$name is not ticked yet");
            array_splice($fields, count($fields) - 1, 0, [[$name, 'on']]); // before the pressed button
        }
        foreach ($changes as $name => $value) {
            $found = false;
            foreach ($fields as $i => $field) {
                if ($field[0] === $name) {
                    $fields[$i][1] = $value; // a name drawn twice (Sager's hidden copies) posts the edited value in both
                    $found = true;
                }
            }
            $this->assertTrue($found, "the form has a $name field");
        }
        return $fields;
    }

    /** @return array<string, string|null> the account's adresser row */
    private static function card(int $id): array
    {
        $row = self::one('SELECT * FROM adresser WHERE id = $1', [$id]);
        self::assertNotNull($row);
        return $row;
    }

    /**
     * The fields a browser posts for the form named $formName as it was drawn, and the query string of its action.
     * An element belongs to the form when it comes between the form's start and end tag, or names the form's id in
     * its form attribute, as a browser assigns them. Every named input except buttons, unticked boxes and disabled
     * fields, the selected (or first) option of each select and each textarea, then the first submit button named $button.
     *
     * @return array{query:string, fields:list<array{0:string, 1:string}>}
     */
    private static function formOf(string $html, string $formName, string $button): array
    {
        $html = preg_replace(['#<script\b.*?</script\s*>#is', '#<!--.*?-->#s'], '', $html);
        $attr = '((?:[^>"\']|"[^"]*"|\'[^\']*\')*)';
        preg_match_all("#<form\\b$attr>|</form\\s*>|<input\\b$attr>|<textarea\\b$attr>(.*?)</textarea\\s*>|<select\\b$attr>(.*?)</select\\s*>#is", $html, $tags, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
        $current = null;
        $formId = null;
        $action = null;
        $fields = [];
        $pressed = null;
        foreach ($tags as $tag) {
            $start = strtolower(substr($tag[0], 0, 6));
            if (str_starts_with($start, '<form')) {
                $attrs = self::attributes((string)$tag[1]);
                $current = $attrs['name'] ?? '';
                if ($current === $formName && $action === null) {
                    $formId = $attrs['id'] ?? null;
                    $action = $attrs['action'] ?? '';
                }
                continue;
            }
            if (str_starts_with($start, '</form')) {
                $current = null;
                continue;
            }
            $attrs = self::attributes((string)($tag[2] ?? $tag[3] ?? $tag[5]));
            $owner = isset($attrs['form']) ? ($formId !== null && $attrs['form'] === $formId) : ($current === $formName);
            $name = $attrs['name'] ?? '';
            if (!$owner || $name === '' || isset($attrs['disabled'])) {
                continue;
            }
            if ($start === '<input') {
                $type = strtolower($attrs['type'] ?? 'text');
                if (in_array($type, ['submit', 'image', 'button', 'reset', 'file'], true)) {
                    if ($type === 'submit' && $name === $button && $pressed === null) {
                        $pressed = [$name, $attrs['value'] ?? ''];
                    }
                    continue;
                }
                if (in_array($type, ['checkbox', 'radio'], true) && !isset($attrs['checked'])) {
                    continue;
                }
                $fields[] = [$name, $attrs['value'] ?? (in_array($type, ['checkbox', 'radio'], true) ? 'on' : '')];
            } elseif (str_starts_with($start, '<texta')) {
                $fields[] = [$name, preg_replace('/^\r?\n/', '', html_entity_decode((string)$tag[4], ENT_QUOTES | ENT_HTML5, 'UTF-8'))];
            } else {
                preg_match_all("#<option\\b$attr>(.*?)(?=<option\\b|</optgroup|$)#is", (string)$tag[6], $options, PREG_SET_ORDER);
                $chosen = null;
                foreach ($options as $option) {
                    $optionAttrs = self::attributes($option[1]);
                    $value = $optionAttrs['value'] ?? trim(html_entity_decode(strip_tags($option[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($chosen === null || isset($optionAttrs['selected'])) {
                        $chosen = $value;
                    }
                }
                if ($chosen !== null) {
                    $fields[] = [$name, $chosen];
                }
            }
        }
        self::assertNotNull($action, "the page draws a form named $formName");
        self::assertNotNull($pressed, "the $formName form has a $button button");
        $fields[] = $pressed;
        return ['query' => (string)parse_url(html_entity_decode($action, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_QUERY), 'fields' => $fields];
    }

    /** @return array<string, string> a tag's attributes, lower-case names, the first of a repeated name */
    private static function attributes(string $text): array
    {
        preg_match_all('#([^\s=/>"\']+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?#s', $text, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
        $attrs = [];
        foreach ($matches as $match) {
            $name = strtolower($match[1]);
            if (!array_key_exists($name, $attrs)) {
                $attrs[$name] = html_entity_decode((string)($match[2] ?? $match[3] ?? $match[4] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        return $attrs;
    }

    /**
     * The card as the lab left it before each round: drawn and saved once without changes.
     */
    private function normalised(string $page, string $art, string $query, string $formName, string $button): int
    {
        $id = $this->account($art);
        $res = $this->save($page, $this->draw($page, $query, $formName, $button, $id));
        $this->assertNotContains(self::STALE, $res['alerts']);
        return $id;
    }

    #[DataProvider('cards')]
    public function test_gem_on_a_card_drawn_before_a_colleagues_save_writes_nothing_and_says_so(string $page, string $art, string $query, string $formName, string $button): void
    {
        $id = $this->normalised($page, $art, $query, $formName, $button);
        $phone = self::card($id)['tlf'];
        $formA = $this->draw($page, $query, $formName, $button, $id);
        $formB = $this->draw($page, $query, $formName, $button, $id);

        $saveB = $this->save($page, $formB, ['notes' => self::NOTE]);
        $this->assertNotContains(self::STALE, $saveB['alerts'], "B's card was current");
        $afterB = self::card($id);
        $this->assertSame(self::NOTE, $afterB['notes'], "B's note is saved");

        $saveA = $this->save($page, $formA, ['tlf' => '86123451']);

        $this->assertContains(self::STALE, $saveA['alerts'], 'A is told that nothing was saved');
        $this->assertSame($afterB, self::card($id), "A's Gem writes nothing: B's note stays, the phone stays, modtime too");
        $this->assertSame(self::NOTE, self::card($id)['notes']);
        $this->assertSame($phone, self::card($id)['tlf']);
        // The debtor card's own case (the message sends the browser to the card) left with the debtor card, see cards().
        $redrawn = self::formOf($saveA['html'], $formName, $button)['fields'];
        $this->assertContains(['tlf', (string)$phone], $redrawn, 'the card below the message is drawn as it is saved');
    }

    #[DataProvider('cards')]
    public function test_gem_on_a_current_card_is_saved(string $page, string $art, string $query, string $formName, string $button): void
    {
        $id = $this->normalised($page, $art, $query, $formName, $button);

        $first = $this->save($page, $this->draw($page, $query, $formName, $button, $id), ['notes' => self::NOTE, 'tlf' => '86123452']);
        $this->assertNotContains(self::STALE, $first['alerts']);
        $this->assertSame(self::NOTE, self::card($id)['notes']);
        $this->assertSame('86123452', self::card($id)['tlf']);

        // The card drawn after a save carries that save's version.
        $second = $this->save($page, $this->draw($page, $query, $formName, $button, $id), ['tlf' => '86123453']);
        $this->assertNotContains(self::STALE, $second['alerts']);
        $this->assertSame(self::NOTE, self::card($id)['notes']);
        $this->assertSame('86123453', self::card($id)['tlf']);
    }

    #[DataProvider('cards')]
    public function test_the_same_form_posted_again_after_its_own_save_is_saved_until_a_colleague_saves(string $page, string $art, string $query, string $formName, string $button): void
    {
        $id = $this->normalised($page, $art, $query, $formName, $button);
        $form = $this->draw($page, $query, $formName, $button, $id);

        $first = $this->save($page, $form, ['tlf' => '86123454']);
        $again = $this->save($page, $form, ['tlf' => '86123454']);

        $this->assertNotContains(self::STALE, $first['alerts']);
        $this->assertNotContains(self::STALE, $again['alerts'], 'a double click or a reload is not refused');
        $this->assertSame('86123454', self::card($id)['tlf']);

        // A form from another window, drawn before that save, is refused although the same session saved.
        $other = $form;
        $refused = $this->save($page, $other, ['tlf' => '86123455']);
        $this->assertContains(self::STALE, $refused['alerts']);
        $this->assertSame('86123454', self::card($id)['tlf']);

        // Once a colleague has saved, the first form posted again is refused too.
        $this->save($page, $this->draw($page, $query, $formName, $button, $id), ['notes' => self::NOTE]);
        $late = $this->save($page, $form, ['tlf' => '86123454']);
        $this->assertContains(self::STALE, $late['alerts']);
        $this->assertSame(self::NOTE, self::card($id)['notes']);
    }

    // The debtor card's e-mail and delivery-address case left with the debtor card: DebitorkortStaleSaveTest covers both rows.

    #[DataProvider('cards')]
    public function test_a_gem_that_comes_while_a_colleague_is_saving_the_card_waits_for_that_save_and_is_refused(string $page, string $art, string $query, string $formName, string $button): void
    {
        $id = $this->normalised($page, $art, $query, $formName, $button);
        $phone = self::card($id)['tlf'];
        $formA = $this->draw($page, $query, $formName, $button, $id);

        // B pressed Gem on the same card, drawn at the same time as A's: B's save has locked the account and written
        // the note, and has not committed yet. A's Gem arrives now. Checked before B commits, A's form still matches
        // the card, and A's update would then follow B's and blank the note.
        $colleague = self::colleague();
        try {
            CharacterizationEnv::rows($colleague, 'BEGIN');
            CharacterizationEnv::rows($colleague, 'SELECT id FROM adresser WHERE id = $1 FOR UPDATE', [$id]);
            CharacterizationEnv::rows($colleague, 'UPDATE adresser SET notes = $1 WHERE id = $2', [self::NOTE, $id]);
            $gemA = $this->start($page, $formA['query'], $this->typed($formA, ['tlf' => '86123451']));
            $this->waitUntilBlockedBy($gemA, $colleague);
            CharacterizationEnv::rows($colleague, 'COMMIT');
            $saveA = $this->finish($gemA);
        } finally {
            pg_close($colleague);
        }

        $this->assertContains(self::STALE, $saveA['alerts'], 'A is told that nothing was saved');
        $this->assertSame(self::NOTE, self::card($id)['notes'], "B's note stays");
        $this->assertSame($phone, self::card($id)['tlf'], "A's phone is not written");

        // A types it again on the card as it is saved now, and that Gem is saved.
        $again = $this->save($page, $this->draw($page, $query, $formName, $button, $id), ['tlf' => '86123451']);
        $this->assertNotContains(self::STALE, $again['alerts']);
        $this->assertSame('86123451', self::card($id)['tlf']);
        $this->assertSame(self::NOTE, self::card($id)['notes']);
    }

    /**
     * The debtor cards, which split a private customer's name into Fornavn and Efternavn and write them while drawing.
     *
     * @return array<string, array{0:string, 1:string, 2:string, 3:string}>
     */
    public static function debtorCards(): array
    {
        return [
            // The debtor card left (master's MB-82 check, see cards()); DebitorkortStaleSaveTest covers its name split.
            'Sager customer card' => ['sager/kunder.php', 'funktion=ret_kunde&konto_id=%d', 'ret_kunde', 'gem_rettelse'],
        ];
    }

    #[DataProvider('debtorCards')]
    public function test_a_private_customer_whose_name_the_card_splits_while_drawing_it_is_saved(string $page, string $query, string $formName, string $button): void
    {
        $id = $this->account('D');
        self::rows("UPDATE adresser SET kontotype = 'privat', firmanavn = 'Jens Hansen', fornavn = '', efternavn = '' WHERE id = $1", [$id]);

        $form = $this->draw($page, $query, $formName, $button, $id);
        $this->assertSame(['Jens', 'Hansen'], [self::card($id)['fornavn'], self::card($id)['efternavn']], 'the card wrote the split name while drawing');
        $saved = $this->save($page, $form, ['tlf' => '86123458']);

        $this->assertNotContains(self::STALE, $saved['alerts'], "the card's own write is part of the version it was drawn with");
        $this->assertSame('86123458', self::card($id)['tlf']);
    }

    /**
     * The cards that read another table after the account and before they print the version: the page, its query,
     * its form, its Gem, and that table. The creditor cards print the version straight after they read the account.
     *
     * @return array<string, array{0:string, 1:string, 2:string, 3:string, 4:string}>
     */
    public static function drawnInSteps(): array
    {
        return [
            // The debtor card left (master's MB-82 check, see cards()).
            'Sager customer card (reads openpost after the account)' => ['sager/kunder.php', 'funktion=ret_kunde&konto_id=%d', 'ret_kunde', 'gem_rettelse', 'openpost'],
        ];
    }

    #[DataProvider('drawnInSteps')]
    public function test_a_card_drawn_while_a_colleague_saved_it_carries_the_version_of_the_fields_it_shows(string $page, string $query, string $formName, string $button, string $readAfter): void
    {
        $id = $this->normalised($page, 'D', $query, $formName, $button);
        $phone = self::card($id)['tlf'];

        // A opens the card. The page has read the account (no note) and waits for $readAfter, while B's save of a
        // note is committed. Read again for the version, the card would carry B's version next to A's empty note.
        $colleague = self::colleague();
        try {
            CharacterizationEnv::rows($colleague, 'BEGIN');
            CharacterizationEnv::rows($colleague, "LOCK TABLE $readAfter IN ACCESS EXCLUSIVE MODE");
            $drawing = $this->start($page, sprintf($query, $id));
            $this->waitUntilBlockedBy($drawing, $colleague);
            CharacterizationEnv::rows($colleague, 'UPDATE adresser SET notes = $1 WHERE id = $2', [self::NOTE, $id]);
            CharacterizationEnv::rows($colleague, 'COMMIT');
            $drawn = $this->finish($drawing);
        } finally {
            pg_close($colleague);
        }
        $formA = self::formOf($drawn['html'], $formName, $button);
        $this->assertContains(['notes', ''], $formA['fields'], "A's card was drawn from the account as it was before B's note");
        $this->assertSame(self::NOTE, self::card($id)['notes']);

        $saveA = $this->save($page, $formA, ['tlf' => '86123457']);

        $this->assertContains(self::STALE, $saveA['alerts'], "A's card is older than B's save");
        $this->assertSame(self::NOTE, self::card($id)['notes'], "B's note stays");
        $this->assertSame($phone, self::card($id)['tlf']);
    }

    /**
     * A drawn form without its version, as a form drawn before the cards carried one posts it.
     *
     * @param array{query:string, fields:list<array{0:string, 1:string}>} $form
     * @return array{query:string, fields:list<array{0:string, 1:string}>}
     */
    private static function withoutVersion(array $form): array
    {
        $form['fields'] = array_values(array_filter($form['fields'], static fn(array $field): bool => $field[0] !== 'card_version'));
        return $form;
    }

    #[DataProvider('cards')]
    public function test_a_gem_whose_form_carries_no_version_writes_nothing_and_says_so(string $page, string $art, string $query, string $formName, string $button): void
    {
        $id = $this->normalised($page, $art, $query, $formName, $button);
        $formA = self::withoutVersion($this->draw($page, $query, $formName, $button, $id));
        $this->save($page, $this->draw($page, $query, $formName, $button, $id), ['notes' => self::NOTE]);
        $afterB = self::card($id);
        $this->assertSame(self::NOTE, $afterB['notes'], "B's note is saved");

        $saveA = $this->save($page, $formA, ['tlf' => '86123451']);

        $this->assertContains(self::INCOMPLETE, $saveA['alerts'], 'A is told that nothing was saved');
        $this->assertNotContains(self::STALE, $saveA['alerts']);
        $this->assertSame($afterB, self::card($id), "A's Gem writes nothing: B's note stays, the phone stays");

        // Nothing says which card a form without a version was drawn from, so one drawn just now is refused too.
        $current = $this->save($page, self::withoutVersion($this->draw($page, $query, $formName, $button, $id)), ['tlf' => '86123452']);
        $this->assertContains(self::INCOMPLETE, $current['alerts']);
        $this->assertSame($afterB, self::card($id));
    }

    /**
     * The cards that draw two fields per contact person (Pos. and the person's id), so a card with many contact
     * persons has more fields than PHP keeps of a post. The Sager customer card lists none.
     *
     * @return array<string, array{0:string, 1:string, 2:string, 3:string, 4:string}>
     */
    public static function cardsWithContactPersons(): array
    {
        return array_filter(self::cards(), static fn(array $card): bool => $card[0] !== 'sager/kunder.php');
    }

    #[DataProvider('cardsWithContactPersons')]
    public function test_a_card_with_500_contact_persons_keeps_its_version_where_php_cuts_the_post(string $page, string $art, string $query, string $formName, string $button): void
    {
        $id = $this->account($art);
        self::rows("UPDATE adresser SET kontotype = 'erhverv' WHERE id = $1", [$id]); // the debtor card lists them for a business
        self::rows("INSERT INTO ansatte (konto_id, navn, posnr) SELECT $1, 'Kontakt ' || g, g FROM generate_series(1, 500) AS g", [$id]);
        $formA = $this->draw($page, $query, $formName, $button, $id);
        $formB = $this->draw($page, $query, $formName, $button, $id);
        $this->assertGreaterThan(self::MAX_INPUT_VARS, count($formA['fields']), 'the form has more fields than PHP keeps of a post');

        // B's Gem of the current card, cut by PHP after the 1000th field: the card is saved.
        $saveB = $this->save($page, $formB, ['notes' => self::NOTE]);
        $afterB = self::card($id);
        $this->assertSame(self::NOTE, $afterB['notes'], "B's note is saved");
        $this->assertSame([], array_values(array_intersect([self::STALE, self::INCOMPLETE], $saveB['alerts'])), "B's card was current");
        $this->assertSame('500', (string)self::one('SELECT count(*) AS n FROM ansatte WHERE konto_id = $1', [$id])['n'], 'the contact persons stay');

        // A's Gem of the card A drew before B's save, cut the same way, is refused.
        $saveA = $this->save($page, $formA, ['tlf' => '86123451']);
        $this->assertSame($afterB, self::card($id), "A's Gem writes nothing: B's note stays, the phone stays");
        $this->assertContains(self::STALE, $saveA['alerts'], 'A is told that nothing was saved');
        // PHP dropped the last contact persons, ans_ant and the Gem button of both posts, and kept the version.
        foreach (['B' => $saveB, 'A' => $saveA] as $who => $cut) {
            $this->assertNotContains('ans_ant', $cut['posted'], "PHP dropped the fields of $who's post after the 1000th");
            $this->assertNotContains($button, $cut['posted'], "PHP dropped the Gem button of $who's post with them");
            $this->assertContains('card_version', $cut['posted'], "the version of $who's form comes before the cut");
        }

        // With the version after the contact persons, where the debtor card printed it before, PHP drops it and keeps
        // id, tlf and A's empty note. That Gem blanked B's note; now it is refused.
        $version = array_values(array_filter($formA['fields'], static fn(array $field): bool => $field[0] === 'card_version'))[0];
        $late = self::withoutVersion($formA);
        array_splice($late['fields'], count($late['fields']) - 1, 0, [$version]); // before the pressed button
        $saveLate = $this->save($page, $late, ['tlf' => '86123451']);
        $this->assertSame($afterB, self::card($id), "A's Gem without the version writes nothing: B's note stays, the phone stays");
        $this->assertContains(self::INCOMPLETE, $saveLate['alerts'], 'A is told that nothing was saved');
        $this->assertNotContains('card_version', $saveLate['posted'], 'PHP dropped the version with the fields after the 1000th');
        $this->assertSame(['id', 'notes', 'tlf'], array_values(array_intersect(['id', 'notes', 'tlf'], $saveLate['posted'])), 'and kept the card fields before it');
    }
}
