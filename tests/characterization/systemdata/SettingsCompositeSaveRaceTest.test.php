<?php
// tests/characterization/systemdata/SettingsCompositeSaveRaceTest.test.php
//
// Two saves at the same time of two settings that share one stored value keep
// both changes (p1-settings-posting-F1).
//
// A composite ('join' + 'index' in systemdata/settingsDefinitions.php) is
// several settings in one stored value: Omgående bogføring of sales and of
// purchase orders in grupper DIV/3 box5 ("<sales>;<purchases>"), the
// percentage invoicing surcharge and item in DIV/3 box13 ("<pct>\t<item>"),
// a KDS colour in settings color_<n> ("<minutes>-<colour>"). A save of one
// part rebuilt the whole value from the row the request read when it started,
// so a save of the other part that committed in between was undone: two users
// switching on sales and purchases at the same moment ended with 'on;' or
// ';on', and the setting that "can no longer be switched off" was off again.
//
// Each test holds a row lock (select ... for update) on the stored value,
// starts two real requests, waits until both have read the row and wait for
// the lock, and releases it. That is a possible interleaving of two requests;
// nothing in the code under test is replaced. The settings page runs in two
// PHP session directories, as two logins (PHP locks a session file for the
// whole request).
//
// Switching on the last of the two Omgående bogføring settings is judged on
// what is stored when the save writes, not on what the request read when it
// started: once both are on, the cash journal has no Hent, so a waiting
// invoice of either kind keeps the last one off.
//
// Without a stored row (an older ledger without grupper DIV/3, a KDS colour
// not saved yet) there is no row to lock: the two first saves each inserted a
// row, and the readers took one of them. Those tests hold a SHARE lock on the
// table instead, which lets the requests read and blocks their inserts, wait
// until both requests wait for a lock, and release it.
//
// The DIV/3 row also holds settings of their own (box4 Hurtigfakturering,
// box9 Tillad negativ lagerbeholdning), and the first save of any of them
// creates the row. Saved at the same moment as Omgående bogføring, or as each
// other, without the row, each save inserted a row of its own, and the
// readers took one of them: the other setting that was switched on read as
// off, and a sales invoice was not posted.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261119 CL/ASR Created.
// 20261119 CL/ASR Switching on the last Omgående bogføring setting is judged on the value read under the row lock (fix round 6, review round 1).
// 20261120 CL/ASR Two first saves without a stored row, DIV/3 on the settings pages and a KDS colour through saveRaw(), create one row with both parts (p1-settings-posting-F1r3). A save whose locked read fails (lock_timeout) writes nothing and says so.
// 20261120 CL/ASR First saves without DIV/3 of Hurtigfakturering (box4) or Tillad negativ lagerbeholdning (box9) next to Omgående bogføring or each other, on the pages and through saveRaw(), create one row, and a sales invoice of 2 x 125,00 is then posted; the same with the pages under repeatable read (fix round 7, review round 1).

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class SettingsCompositeSaveRaceTest extends CharacterizationTestCase
{
    private const SALES = 'sales.orders.post_sales_immediately';
    private const PURCHASES = 'purchase.orders.post_immediately';
    private const QUICK = 'sales.orders.quick_invoice';
    private const NEGATIVE_STOCK = 'items.stock.allow_negative';

    /** @var list<array<string, mixed>> grupper DIV/3 before the test changed it; restored in tearDown(). */
    private array $div3 = [];

    /** @var list<string> PHP session directories this test made; removed in tearDown(). */
    private array $sessionDirs = [];

    /** @var list<int> Orders this test left at status 3; tearDown() takes them out of Hent's list again. */
    private array $createdOrders = [];

    /** @var list<array<string, mixed>> Whole grupper DIV/3 rows a test deleted to stand for a ledger without the row; tearDown() puts them back. */
    private array $removedRows = [];

    /** True when secondSession() has seeded a second login in the master's online table; tearDown() removes it. */
    private bool $sessionB = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // The change history lives in audit_log, which includes/betweenUpdates.php creates at a ledger's first login.
        self::rows("CREATE TABLE IF NOT EXISTS audit_log (id serial PRIMARY KEY, tidspunkt timestamp NOT NULL DEFAULT now(), bruger_id integer,
            brugernavn varchar(80), handling varchar(60) NOT NULL, objekt_type varchar(30), objekt_id varchar(60), detaljer text, ip varchar(45),
            kilde varchar(30), setting_key varchar(120), section varchar(80), old_value text, new_value text)");
    }

    protected function setUp(): void
    {
        $this->div3 = self::rows("SELECT id, box5, box12, box13 FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
        $this->assertNotSame([], $this->div3, 'the installed tenant has the order settings row (grupper DIV/3)');
    }

    protected function tearDown(): void
    {
        if ($this->removedRows !== []) {
            self::rows("DELETE FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
            foreach ($this->removedRows as $row) {
                $columns = array_keys($row);
                $params = [];
                foreach ($columns as $i => $column) {
                    $params[] = '$' . ($i + 1);
                }
                self::rows('INSERT INTO grupper (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $params) . ')', array_values($row));
            }
        }
        foreach ($this->div3 as $row) {
            self::rows('UPDATE grupper SET box5 = $1, box12 = $2, box13 = $3 WHERE id = $4', [$row['box5'], $row['box12'], $row['box13'], $row['id']]);
        }
        self::rows("DELETE FROM settings WHERE var_grp = 'KDS' AND var_name LIKE 'color%'");
        foreach ($this->createdOrders as $id) {
            self::rows('UPDATE ordrer SET status = 4 WHERE id = $1', [$id]);
        }
        if ($this->sessionB) {
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            CharacterizationEnv::rows($master, 'DELETE FROM online WHERE session_id = $1', [self::sessionB()]);
            pg_close($master);
            $this->sessionB = false;
        }
        foreach ($this->sessionDirs as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    private function sessionDir(): string
    {
        $dir = sys_get_temp_dir() . '/chartest-race-' . bin2hex(random_bytes(6));
        $this->assertTrue(mkdir($dir, 0700), 'a session directory of its own');
        $this->sessionDirs[] = $dir;
        return $dir;
    }

    private static function pageRunner(): string
    {
        return __DIR__ . '/../support/run_settings_section_page.php';
    }

    /**
     * A second login (user B) in the master's online table, a copy of the class's own: two pages of one login update the
     * same online row, which under repeatable read fails for the second (could not serialize access).
     */
    private function secondSession(): string
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            CharacterizationEnv::rows($master, 'DELETE FROM online WHERE session_id = $1', [self::sessionB()]);
            CharacterizationEnv::rows($master,
                'INSERT INTO online (session_id, brugernavn, db, dbuser, rettigheder, regnskabsaar, logtime, revisor, language_id)
                 SELECT $1, brugernavn, db, dbuser, rettigheder, regnskabsaar, $2, revisor, language_id FROM online WHERE session_id = $3',
                [self::sessionB(), (string) time(), CharacterizationEnv::sessionId()]);
            $this->sessionB = true;
        } finally {
            pg_close($master);
        }
        return self::sessionB();
    }

    /** The second login's session: 32 hex characters derived from the class's own session. */
    private static function sessionB(): string
    {
        return md5(CharacterizationEnv::sessionId() . ':B');
    }

    /**
     * The settings section's form as drawn in this session, with $changes on top: what a browser posts.
     *
     * @param array<string, string> $changes
     */
    private function formBody(string $section, string $sessionDir, array $changes, ?string $sessionId = null): string
    {
        $res = CharacterizationEnv::runChild(self::pageRunner(), ["s=$section", '', $sessionId ?? CharacterizationEnv::sessionId(), $sessionDir]);
        $this->assertSame(0, $res['exit'], $res['stderr']);
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $page = json_decode((string) end($lines), true);
        $this->assertIsArray($page, $res['stdout'] . $res['stderr']);
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $page['html']);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $x = new DOMXPath($doc);
        $form = [];
        foreach ($x->query("//form[@id='st-form']//input[@name]") as $input) {
            $type = strtolower($input->getAttribute('type'));
            if ($input->hasAttribute('disabled') || in_array($type, ['submit', 'button'], true)
                || (in_array($type, ['checkbox', 'radio'], true) && !$input->hasAttribute('checked'))) {
                continue;
            }
            $form[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        foreach ($x->query("//form[@id='st-form']//select[@name]") as $select) {
            if ($select->hasAttribute('disabled')) {
                continue;
            }
            $chosen = null;
            foreach ($select->getElementsByTagName('option') as $option) {
                if ($chosen === null || $option->hasAttribute('selected')) {
                    $chosen = $option->getAttribute('value');
                }
            }
            $form[$select->getAttribute('name')] = (string) $chosen;
        }
        foreach ($x->query("//form[@id='st-form']//textarea[@name]") as $textarea) {
            $form[$textarea->getAttribute('name')] = $textarea->textContent;
        }
        $this->assertArrayHasKey('csrf_token', $form, 'the form carries the session token');
        foreach (array_keys($changes) as $name) {
            $this->assertArrayHasKey($name, $form, "the form draws $name");
        }
        return http_build_query($changes + $form);
    }

    /**
     * Starts the children while this test holds the lock $lockSql, waits until all of them wait for a lock, runs
     * $whileLocked in the same transaction (another save that commits first), and releases it. $lockSql is a row lock
     * (select ... for update), or a table lock (lock table ...) where there is no row to lock.
     *
     * @param list<list<string>> $children each child's script and arguments
     * @param array<string, string>|null $env the children's environment (null: this process's)
     * @return list<array{exit:int, stdout:string, stderr:string}>
     */
    private function runWhileLocked(string $lockSql, array $children, ?callable $whileLocked = null, ?array $env = null): array
    {
        $procs = [];
        $waiting = 0;
        self::rows('BEGIN');
        try {
            if (stripos($lockSql, 'select') === 0) {
                $this->assertNotSame([], self::rows($lockSql), 'the stored value has a row to lock');
            } else {
                self::rows($lockSql);
            }
            foreach ($children as $argv) {
                $err = tmpfile();
                $proc = proc_open(array_merge([PHP_BINARY], $argv), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => $err], $pipes, null, $env);
                $this->assertIsResource($proc, 'the child starts');
                fclose($pipes[0]);
                $procs[] = [$proc, $pipes[1], $err];
            }
            $deadline = microtime(true) + 30;
            do {
                self::rows('SELECT pg_stat_clear_snapshot()');
                $waiting = (int) self::one("SELECT count(*) AS n FROM pg_stat_activity
                    WHERE datname = current_database() AND pid <> pg_backend_pid() AND wait_event_type = 'Lock'")['n'];
                if ($waiting >= count($children)) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            if ($whileLocked !== null) {
                $whileLocked();
            }
        } finally {
            self::rows('COMMIT');
        }
        $out = [];
        foreach ($procs as [$proc, $stdout, $err]) {
            $text = (string) stream_get_contents($stdout);
            fclose($stdout);
            $exit = proc_close($proc);
            rewind($err);
            $out[] = ['exit' => $exit, 'stdout' => $text, 'stderr' => (string) stream_get_contents($err)];
            fclose($err);
        }
        $this->assertSame(count($children), $waiting, 'both requests had read the stored value and waited for a lock before it was released');
        return $out;
    }

    /** @return array<string, mixed> the JSON line a child printed last */
    private function childJson(array $res): array
    {
        $this->assertSame(0, $res['exit'], $res['stdout'] . $res['stderr']);
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $json = json_decode((string) end($lines), true);
        $this->assertIsArray($json, $res['stdout'] . $res['stderr']);
        return $json;
    }

    /** aria-checked of the switch $key as the section draws it in a new session: 'true' when it is drawn on. */
    private function drawnOn(string $section, string $key): ?string
    {
        $res = CharacterizationEnv::runChild(self::pageRunner(), ["s=$section", '', CharacterizationEnv::sessionId(), $this->sessionDir()]);
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $this->childJson($res)['html']);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $switch = (new DOMXPath($doc))->query("//div[@data-key='$key']//button[contains(@class, 'st-switch')]")->item(0);
        $this->assertNotNull($switch, "the page draws $key");
        return $switch->getAttribute('aria-checked');
    }

    /**
     * Deletes grupper DIV/3 (an older ledger without it; tearDown() puts it back) and saves each [section, key] switched on
     * from a page of its own at the same moment, while the test's SHARE lock holds back their inserts. The first page is
     * the class's login, the second another login (secondSession()), as two users.
     *
     * @param list<array{0: string, 1: string}> $saves
     * @param array<string, string>|null $env the pages' environment (null: this process's)
     * @return list<array<string, mixed>> DIV/3 afterwards, box4, box5 and box9, by id
     */
    private function firstSavesWithoutDiv3(array $saves, ?array $env = null): array
    {
        $this->removedRows = self::rows("SELECT * FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
        self::rows("DELETE FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
        self::rows('DELETE FROM audit_log WHERE setting_key IN ($1, $2, $3, $4)', [self::SALES, self::PURCHASES, self::QUICK, self::NEGATIVE_STOCK]);
        $children = [];
        foreach ($saves as $i => [$section, $key]) {
            $dir = $this->sessionDir();
            $session = $i === 0 ? CharacterizationEnv::sessionId() : $this->secondSession();
            $children[] = [self::pageRunner(), "s=$section", $this->formBody($section, $dir, ["f[$key]" => '1'], $session), $session, $dir];
        }

        $results = $this->runWhileLocked('LOCK TABLE grupper IN SHARE MODE', $children, null, $env);

        foreach ($results as $res) {
            $this->assertSame(302, $this->childJson($res)['status'], 'each save was accepted');
        }
        return self::rows("SELECT box4, box5, box9 FROM grupper WHERE art = 'DIV' AND kodenr = '3' ORDER BY id");
    }

    /** @return list<array{setting_key: string, new_value: string}> the change history of the four settings, by key */
    private static function history(): array
    {
        return self::rows('SELECT setting_key, new_value FROM audit_log WHERE setting_key IN ($1, $2, $3, $4) ORDER BY setting_key',
            [self::SALES, self::PURCHASES, self::QUICK, self::NEGATIVE_STOCK]);
    }

    private static function div3(string $box): ?string
    {
        $row = self::one("SELECT $box AS v FROM grupper WHERE art = 'DIV' AND kodenr = '3' ORDER BY id LIMIT 1");
        return $row['v'] ?? null;
    }

    public function test_switching_on_sales_and_purchases_at_the_same_time_keeps_both_on(): void
    {
        self::rows("UPDATE grupper SET box5 = ';' WHERE art = 'DIV' AND kodenr = '3'");
        self::rows("DELETE FROM audit_log WHERE setting_key IN ($1, $2)", [self::SALES, self::PURCHASES]);
        $children = [];
        foreach (['sales.orders' => self::SALES, 'purchase.orders' => self::PURCHASES] as $section => $key) {
            $dir = $this->sessionDir();
            $children[] = [self::pageRunner(), "s=$section", $this->formBody($section, $dir, ["f[$key]" => '1']), CharacterizationEnv::sessionId(), $dir];
        }

        $results = $this->runWhileLocked("SELECT id FROM grupper WHERE art = 'DIV' AND kodenr = '3' FOR UPDATE", $children);

        foreach ($results as $res) {
            $this->assertSame(302, $this->childJson($res)['status'], 'each save was accepted');
        }
        $this->assertSame('on;on', self::div3('box5'), 'both settings are on: neither save switched the other off again');
        $history = self::rows("SELECT setting_key, new_value FROM audit_log WHERE setting_key IN ($1, $2) ORDER BY setting_key", [self::SALES, self::PURCHASES]);
        $this->assertSame([['setting_key' => self::PURCHASES, 'new_value' => 'on'], ['setting_key' => self::SALES, 'new_value' => 'on']], $history, 'the history has each switch-on once, and it is what is stored');
    }

    public function test_two_parts_of_a_tab_joined_box_saved_at_the_same_time_are_both_kept(): void
    {
        $item = self::$fx->item(['stocked' => false]);
        self::rows("UPDATE grupper SET box12 = 'on', box13 = $1 WHERE art = 'DIV' AND kodenr = '3'", ["5\t"]);
        $children = [];
        foreach ([['sales.orders.percentage_surcharge', '7,5'], ['sales.orders.percentage_item', (string) $item['varenr']]] as [$key, $value]) {
            $dir = $this->sessionDir();
            $children[] = [self::pageRunner(), 's=sales.orders', $this->formBody('sales.orders', $dir, ["f[$key]" => $value]), CharacterizationEnv::sessionId(), $dir];
        }

        $results = $this->runWhileLocked("SELECT id FROM grupper WHERE art = 'DIV' AND kodenr = '3' FOR UPDATE", $children);

        foreach ($results as $res) {
            $this->assertSame(302, $this->childJson($res)['status'], 'each save was accepted');
        }
        $this->assertSame("7.5\t" . $item['varenr'], self::div3('box13'), 'the surcharge and the item are both stored');
    }

    public function test_two_parts_of_a_settings_row_saved_at_the_same_time_without_a_transaction_are_both_kept(): void
    {
        self::rows("DELETE FROM settings WHERE var_grp = 'KDS' AND var_name LIKE 'color%'");
        self::rows("INSERT INTO settings (var_grp, var_name, var_value, var_description, user_id) VALUES ('KDS', 'color_1', '5-#ff0000', 'chartest', 0)");
        $runner = __DIR__ . '/../support/run_settings_save_raw.php';
        $children = [
            [$runner, CharacterizationEnv::testDb(), 'pos.kitchen.colour_after_1', '10'],
            [$runner, CharacterizationEnv::testDb(), 'pos.kitchen.colour_1', '#00ff00'],
        ];

        $results = $this->runWhileLocked("SELECT id FROM settings WHERE var_grp = 'KDS' AND var_name = 'color_1' FOR UPDATE", $children);

        foreach ($results as $res) {
            $json = $this->childJson($res);
            $this->assertTrue($json['changed'], 'each save changed the value');
            $this->assertSame(0, $json['depth'], 'the transaction saveRaw() opened for itself is closed again');
        }
        $this->assertSame([['var_value' => '10-#00ff00']], self::rows("SELECT var_value FROM settings WHERE var_grp = 'KDS' AND var_name = 'color_1'"), 'the minutes and the colour are both stored');
    }

    /**
     * Sales is switched on by another save while this request, which switches purchases on, waits for the row: it read
     * both off when it started, but when it writes, sales is on and a sales invoice waits at status 3. Both on would leave
     * the invoice without Hent, so the save is refused with the message about the sales invoices, and nothing is written.
     */
    public function test_switching_on_the_last_setting_is_judged_on_what_is_stored_when_it_is_saved(): void
    {
        self::rows("UPDATE grupper SET box5 = ';' WHERE art = 'DIV' AND kodenr = '3'");
        $lines = [['item' => self::$fx->item(['stocked' => false]), 'antal' => 1, 'pris' => 1000.00]];
        $order = self::$fx->order(self::$fx->debtor(), $lines, ['status' => 3, 'art' => 'DO']);
        $this->createdOrders[] = (int) $order['id'];
        $dir = $this->sessionDir();
        $children = [[self::pageRunner(), 's=purchase.orders', $this->formBody('purchase.orders', $dir, ['f[' . self::PURCHASES . ']' => '1']), CharacterizationEnv::sessionId(), $dir]];

        $results = $this->runWhileLocked("SELECT id FROM grupper WHERE art = 'DIV' AND kodenr = '3' FOR UPDATE", $children, static function (): void {
            self::rows("UPDATE grupper SET box5 = 'on;' WHERE art = 'DIV' AND kodenr = '3'");
        });

        $page = $this->childJson($results[0]);
        $this->assertNotSame(302, $page['status'], 'the save is refused and the page is drawn again');
        $this->assertStringContainsString('Salgsfakturaer venter på at blive hentet. Hent dem ind i en kassekladde med Hent ordrer først.', $page['html'], 'it says which invoices to fetch first');
        $this->assertSame('on;', self::div3('box5'), 'sales stays on and purchases stays off: the sales invoice keeps Hent');
    }

    /**
     * An older ledger without grupper DIV/3: both pages read no row while the test's SHARE lock holds back their inserts.
     * Each save used to insert its own row, 'on;' and ';on', and the readers take one of them, so one setting that was
     * switched on read as off (p1-settings-posting-F1r3). Now one save creates the row under the named lock, which it holds
     * until it has committed, and the other reads that row under the lock and adds its part.
     */
    public function test_first_saves_of_both_settings_without_a_stored_row_create_one_row_with_both_on(): void
    {
        $this->removedRows = self::rows("SELECT * FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
        self::rows("DELETE FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
        self::rows("DELETE FROM audit_log WHERE setting_key IN ($1, $2)", [self::SALES, self::PURCHASES]);
        $children = [];
        foreach (['sales.orders' => self::SALES, 'purchase.orders' => self::PURCHASES] as $section => $key) {
            $dir = $this->sessionDir();
            $children[] = [self::pageRunner(), "s=$section", $this->formBody($section, $dir, ["f[$key]" => '1']), CharacterizationEnv::sessionId(), $dir];
        }

        $results = $this->runWhileLocked('LOCK TABLE grupper IN SHARE MODE', $children);

        foreach ($results as $res) {
            $this->assertSame(302, $this->childJson($res)['status'], 'each save was accepted');
        }
        $this->assertSame([['box5' => 'on;on']], self::rows("SELECT box5 FROM grupper WHERE art = 'DIV' AND kodenr = '3' ORDER BY id"), 'one DIV/3 row, with both settings on');
        $history = self::rows("SELECT setting_key, new_value FROM audit_log WHERE setting_key IN ($1, $2) ORDER BY setting_key", [self::SALES, self::PURCHASES]);
        $this->assertSame([['setting_key' => self::PURCHASES, 'new_value' => 'on'], ['setting_key' => self::SALES, 'new_value' => 'on']], $history, 'the history has each switch-on once, and it is what is stored');
        foreach (['sales.orders' => self::SALES, 'purchase.orders' => self::PURCHASES] as $section => $key) {
            $res = CharacterizationEnv::runChild(self::pageRunner(), ["s=$section", '', CharacterizationEnv::sessionId(), $this->sessionDir()]);
            $doc = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="UTF-8">' . $this->childJson($res)['html']);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $switch = (new DOMXPath($doc))->query("//div[@data-key='$key']//button[contains(@class, 'st-switch')]")->item(0);
            $this->assertNotNull($switch, "the page draws $key");
            $this->assertSame('true', $switch->getAttribute('aria-checked'), "$key is drawn on");
        }
    }

    /**
     * A KDS colour that has not been saved has no settings row: the minutes and the colour saved at the same time through
     * saveRaw(), without a transaction of the caller's, used to insert two color_1 rows, '10' and '-#00ff00'.
     */
    public function test_first_saves_of_two_parts_of_a_settings_row_without_a_row_create_one_row(): void
    {
        self::rows("DELETE FROM settings WHERE var_grp = 'KDS' AND var_name LIKE 'color%'");
        $runner = __DIR__ . '/../support/run_settings_save_raw.php';
        $children = [
            [$runner, CharacterizationEnv::testDb(), 'pos.kitchen.colour_after_1', '10'],
            [$runner, CharacterizationEnv::testDb(), 'pos.kitchen.colour_1', '#00ff00'],
        ];

        $results = $this->runWhileLocked('LOCK TABLE settings IN SHARE MODE', $children);

        foreach ($results as $res) {
            $json = $this->childJson($res);
            $this->assertTrue($json['changed'], 'each save changed the value');
            $this->assertSame(0, $json['depth'], 'the transaction saveRaw() opened for itself is closed again');
        }
        $this->assertSame([['var_value' => '10-#00ff00']], self::rows("SELECT var_value FROM settings WHERE var_grp = 'KDS' AND var_name = 'color_1'"), 'one row with the minutes and the colour');
    }

    /**
     * The row is locked by another session for longer than the page's lock_timeout, so the save's locked read fails. On
     * PostgreSQL that aborts the transaction; the page used to go on in it, with an error alert for every statement that
     * followed and no word that nothing was saved. Now it rolls back, writes nothing and says to try again. The child's mail
     * goes to a file (db_select() mails its errors).
     */
    public function test_a_save_whose_locked_read_fails_writes_nothing_and_says_to_try_again(): void
    {
        self::rows("UPDATE grupper SET box5 = ';' WHERE art = 'DIV' AND kodenr = '3'");
        self::rows("DELETE FROM audit_log WHERE setting_key = $1", [self::SALES]);
        $dir = $this->sessionDir();
        $body = $this->formBody('sales.orders', $dir, ['f[' . self::SALES . ']' => '1']);
        $mail = (string) tempnam(sys_get_temp_dir(), 'chartest-settings-mail-');
        $env = getenv();
        $env['PGOPTIONS'] = '-c lock_timeout=500';

        self::rows('BEGIN');
        try {
            $this->assertNotSame([], self::rows("SELECT id FROM grupper WHERE art = 'DIV' AND kodenr = '3' FOR UPDATE"), 'the stored value has a row to lock');
            $err = tmpfile();
            $proc = proc_open([PHP_BINARY, '-d', 'sendmail_path=cat >> ' . escapeshellarg($mail), self::pageRunner(), 's=sales.orders', $body, CharacterizationEnv::sessionId(), $dir],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => $err], $pipes, null, $env);
            $this->assertIsResource($proc, 'the child starts');
            fclose($pipes[0]);
            // The page gives up on the lock after 500 ms and ends while this test still holds it.
            $stdout = (string) stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $exit = proc_close($proc);
            rewind($err);
            $res = ['exit' => $exit, 'stdout' => $stdout, 'stderr' => (string) stream_get_contents($err)];
            fclose($err);
        } finally {
            self::rows('COMMIT');
            @unlink($mail);
        }

        $page = $this->childJson($res);
        $this->assertNotSame(302, $page['status'], 'the save is not answered as saved');
        $this->assertStringContainsString('Ændringen er ikke gemt, fordi indstillingen ikke kunne låses til at gemme den. Prøv igen om lidt.', $page['html'], 'it says to try again');
        $this->assertSame(';', self::div3('box5'), 'nothing is written');
        $this->assertSame([], self::rows("SELECT id FROM audit_log WHERE setting_key = $1", [self::SALES]), 'and nothing is in the history');
    }

    /**
     * An older ledger without DIV/3: Hurtigfakturering (box4, a setting of its own) is switched on at the same moment as
     * Omgående bogføring af købsordrer (box5 index 1). The box4 save did not take the row's named lock, so each save
     * inserted a row, {box4 'on'} and {box5 ';on'}, and the page drew purchases off although its save was accepted (fix
     * round 7, review round 1). Now the box4 save also creates the row under the named lock, or writes into the row the
     * other save created.
     */
    public function test_a_setting_of_its_own_and_omgaaende_bogfoering_first_saved_together_share_one_row(): void
    {
        $rows = $this->firstSavesWithoutDiv3([['sales.orders', self::QUICK], ['purchase.orders', self::PURCHASES]]);

        $this->assertSame([['box4' => 'on', 'box5' => ';on', 'box9' => null]], $rows, 'one DIV/3 row with both settings');
        $this->assertSame([['setting_key' => self::PURCHASES, 'new_value' => 'on'], ['setting_key' => self::QUICK, 'new_value' => 'on']], self::history());
        $this->assertSame('true', $this->drawnOn('purchase.orders', self::PURCHASES), 'purchases is drawn on');
        $this->assertSame('true', $this->drawnOn('sales.orders', self::QUICK), 'Hurtigfakturering is drawn on');
    }

    /**
     * Tillad negativ lagerbeholdning (box9, on Lager) and Omgående bogføring af salgsordrer switched on at the same moment
     * without DIV/3, then a sales invoice of 2 x 125,00 (250,00 + 62,50 VAT): it is posted right away (status 4, on the
     * accounts), not left at status 3 because the reader took the row without box5.
     */
    public function test_negative_stock_and_sales_first_saved_together_then_a_sales_invoice_is_posted(): void
    {
        $rows = $this->firstSavesWithoutDiv3([['items.stock', self::NEGATIVE_STOCK], ['sales.orders', self::SALES]]);

        $this->assertSame([['box4' => null, 'box5' => 'on;', 'box9' => 'on']], $rows, 'one DIV/3 row with both settings');
        $this->assertSame([['setting_key' => self::NEGATIVE_STOCK, 'new_value' => 'on'], ['setting_key' => self::SALES, 'new_value' => 'on']], self::history());
        $this->assertSame('true', $this->drawnOn('items.stock', self::NEGATIVE_STOCK), 'negative stock is drawn on');
        $this->assertSame('true', $this->drawnOn('sales.orders', self::SALES), 'sales is drawn on');

        $order = self::$fx->order(self::$fx->debtor(), [['item' => self::$fx->item(['stocked' => false]), 'antal' => 2, 'pris' => 125.00]], []);
        $this->createdOrders[] = (int) $order['id'];
        $out = $this->runChildJson('run_order_invoice.php', ['invoice', $order['id'], CharacterizationEnv::testDb()]);
        $this->assertSame('OK', $out['bogfor'] ?? null, 'the order is invoiced');
        $this->assertSame('4', (string) self::one('SELECT status FROM ordrer WHERE id = $1', [$order['id']])['status'], 'posted right away, not waiting for Hent');
        $posted = self::one('SELECT count(*) AS n, sum(debet) AS debet, sum(kredit) AS kredit FROM transaktioner WHERE ordre_id = $1', [$order['id']]);
        $this->assertGreaterThan(0, (int) $posted['n'], 'the invoice is on the accounts');
        $this->assertEqualsWithDelta(312.50, (float) $posted['debet'], 0.001, '312,50 on the debit side');
        $this->assertEqualsWithDelta(312.50, (float) $posted['kredit'], 0.001, 'and on the credit side');
    }

    /** Two settings of their own in DIV/3, on two pages, first saved at the same moment: one row with both. */
    public function test_two_settings_of_their_own_first_saved_together_share_one_row(): void
    {
        $rows = $this->firstSavesWithoutDiv3([['sales.orders', self::QUICK], ['items.stock', self::NEGATIVE_STOCK]]);

        $this->assertSame([['box4' => 'on', 'box5' => null, 'box9' => 'on']], $rows, 'one DIV/3 row with both settings');
        $this->assertSame('true', $this->drawnOn('sales.orders', self::QUICK));
        $this->assertSame('true', $this->drawnOn('items.stock', self::NEGATIVE_STOCK));
    }

    /** The same through saveRaw() on its own (setting_save()), which opens a transaction of its own for it. */
    public function test_a_setting_of_its_own_saved_through_save_raw_without_div3_shares_one_row(): void
    {
        $this->removedRows = self::rows("SELECT * FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
        self::rows("DELETE FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
        $runner = __DIR__ . '/../support/run_settings_save_raw.php';
        $children = [
            [$runner, CharacterizationEnv::testDb(), self::QUICK, 'on'],
            [$runner, CharacterizationEnv::testDb(), self::PURCHASES, 'on'],
        ];

        $results = $this->runWhileLocked('LOCK TABLE grupper IN SHARE MODE', $children);

        foreach ($results as $res) {
            $json = $this->childJson($res);
            $this->assertTrue($json['changed'], 'each save changed the value');
            $this->assertSame(0, $json['depth'], 'the transaction saveRaw() opened for itself is closed again');
        }
        $this->assertSame([['box4' => 'on', 'box5' => ';on']], self::rows("SELECT box4, box5 FROM grupper WHERE art = 'DIV' AND kodenr = '3' ORDER BY id"), 'one DIV/3 row');
    }

    /**
     * As above, with the pages' transactions under repeatable read (PGOPTIONS default_transaction_isolation), by two logins.
     * A save whose transaction took its snapshot before it waited for the named lock would not see the row the other save
     * created, and would insert a second one. The lock is taken before the transaction begins.
     */
    public function test_first_saves_without_a_stored_row_under_repeatable_read_create_one_row_with_both_on(): void
    {
        $env = getenv();
        $env['PGOPTIONS'] = '-c default_transaction_isolation=repeatable\\ read';

        $rows = $this->firstSavesWithoutDiv3([['sales.orders', self::SALES], ['purchase.orders', self::PURCHASES]], $env);

        $this->assertSame([['box4' => null, 'box5' => 'on;on', 'box9' => null]], $rows, 'one DIV/3 row, with both settings on');
        $this->assertSame([['setting_key' => self::PURCHASES, 'new_value' => 'on'], ['setting_key' => self::SALES, 'new_value' => 'on']], self::history());
    }
}
