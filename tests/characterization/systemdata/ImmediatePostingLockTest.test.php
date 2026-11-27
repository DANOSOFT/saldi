<?php
// tests/characterization/systemdata/ImmediatePostingLockTest.test.php
//
// Omgående bogføring of sales orders and of purchase orders can be switched on
// but no longer off (owner, 19 Nov 2026: "Hent ordrer må ikke være der med
// mindre omgående bogføring er slået fra ... Og her bør muligheden for at slå
// det fra fjernes").
//
// Both settings live in grupper DIV/3 box5 as "<sales>;<purchases>", each 'on'
// when on (a lone 'on' from before 3.6 is both), and are edited on
// Indstillinger » Ordrer & fakturering (sales.orders.post_sales_immediately)
// and Indstillinger » Købsordrer (purchase.orders.post_immediately). Off, an
// invoice waits at status 3 and the cash journal shows Hent; that rule
// (finans/kassekladde.php) is unchanged.
//
// Pins, driven through systemdata/settingsSection.php as a browser does:
// - a setting that is on is drawn on and locked with the lock text, and is not
//   part of the posted form; a crafted post that turns it off, and Gendan of a
//   history row from before it was switched on, change nothing;
// - a setting that is off is drawn unlocked with the warning under Læs mere, can
//   be switched on, and is locked from then on;
// - a setting that is off stays off when the page is saved for another field
//   (no data migration: its waiting invoices still need Hent);
// - Hent stays in the journal while sales or purchases are off and goes when
//   both are on, and a refused post to switch one off does not bring it back;
// - a setting is not switched on while invoices of its kind wait at status 3
//   for Hent ordrer, by a save, a crafted post or Gendan, and the page says to
//   fetch them into a cash journal first; the kinds are what Hent ordrer moves:
//   art K* (KO, KK) is purchases, every other art but PO is sales (DO, DK, and a
//   reminder R1 that a failed reminder posting left at status 3); POS orders
//   and, with vis_saet, a sales order paid at a till (the register close takes
//   them; Hent ordrer does not list them) do not keep it off;
// - the last setting that is off is not switched on while invoices of either
//   kind wait (Hent goes for all of them once both are on), also an invoice
//   that went back to status 3 when its journal lines were deleted, and the
//   message names the kind that waits;
// - from an older ledger's '' or NULL box5, or without a DIV/3 row, switching
//   one setting on stores 'on;' or ';on', never a lone 'on' (both), so the
//   other setting stays off, unlocked, and its waiting invoice keeps Hent.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261119 CL/ASR Created.
// 20261119 CL/ASR A setting is not switched on while invoices of its kind wait at status 3 for Hent ordrer (fix round 6): a waiting invoice no longer loses Hent.
// 20261119 CL/ASR The last setting that is off is not switched on while invoices of the other kind wait either (fix round 6, review round 1): Hent goes for every kind once both are on.
// 20261120 CL/ASR A reminder (R1) left at status 3 counts as a waiting sales document, as the Hent button and Hent ordrer count it (fix round 7).

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class ImmediatePostingLockTest extends CharacterizationTestCase
{
    private const SALES = 'sales.orders.post_sales_immediately';
    private const PURCHASES = 'purchase.orders.post_immediately';

    /** Another field of Ordrer & fakturering in the same row (DIV/3 box3, Medtag kommentarer på følgesedler). */
    private const OTHER = 'sales.orders.packing_comments';

    /** The lock text (settings.orders.post_immediately_locked) and the Læs mere warning (settings.orders.post_immediately_more), as drawn in Danish. */
    private const LOCK_TEXT = 'Kan ikke slås fra igen, når den er slået til.';
    private const MORE_TEXT = 'hent dem først';

    /** The message of a refused switch-on (settings.orders.post_immediately_waiting_sales / _purchases), as drawn in Danish. */
    private const WAITING_SALES = 'Salgsfakturaer venter på at blive hentet. Hent dem ind i en kassekladde med Hent ordrer først.';
    private const WAITING_PURCHASES = 'Købsfakturaer venter på at blive hentet. Hent dem ind i en kassekladde med Hent ordrer først.';

    /** The Hent button as kassekladde.php draws it. */
    private const HENT = "/<input[^>]*name='upload'/";

    /** @var list<array{id:string, box3:?string, box5:?string}> grupper DIV/3 before the test changed it; restored in tearDown(). */
    private array $orderSettings = [];

    /** @var list<int> Orders this test left at status 3; tearDown() takes them out of Hent's list again. */
    private array $createdOrders = [];

    /** @var list<array<string, mixed>> Whole grupper DIV/3 rows a test deleted to stand for a ledger without the row; tearDown() puts them back. */
    private array $removedRows = [];

    /** @var array{id:string, box12:?string, created:bool}|null grupper POS/2 of the fiscal year before setTillClose() changed it; restored in tearDown(). */
    private ?array $pos2 = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // The change history (Gendan) lives in audit_log, which includes/betweenUpdates.php creates at the first login of a
        // ledger; the class's fresh installation has not logged in, so it gets the same table here.
        self::rows("CREATE TABLE IF NOT EXISTS audit_log (id serial PRIMARY KEY, tidspunkt timestamp NOT NULL DEFAULT now(), bruger_id integer,
            brugernavn varchar(80), handling varchar(60) NOT NULL, objekt_type varchar(30), objekt_id varchar(60), detaljer text, ip varchar(45),
            kilde varchar(30), setting_key varchar(120), section varchar(80), old_value text, new_value text)");
    }

    protected function setUp(): void
    {
        $this->orderSettings = self::rows("SELECT id, box3, box5 FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
        $this->assertNotSame([], $this->orderSettings, 'the installed tenant has the order settings row (grupper DIV/3)');
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
        foreach ($this->orderSettings as $row) {
            self::rows('UPDATE grupper SET box3 = $1, box5 = $2 WHERE id = $3', [$row['box3'], $row['box5'], $row['id']]);
        }
        foreach ($this->createdOrders as $id) {
            self::rows('UPDATE ordrer SET status = 4 WHERE id = $1', [$id]);
        }
        if ($this->pos2 !== null) {
            if ($this->pos2['created']) {
                self::rows('DELETE FROM grupper WHERE id = $1', [$this->pos2['id']]);
            } else {
                self::rows('UPDATE grupper SET box12 = $1 WHERE id = $2', [$this->pos2['box12'], $this->pos2['id']]);
            }
        }
    }

    private static function setBox5(string $box5): void
    {
        self::rows("UPDATE grupper SET box5 = $1 WHERE art = 'DIV' AND kodenr = '3'", [$box5]);
    }

    /**
     * DIV/3 box5 as an older ledger may hold it, both settings off: '' (opdat_2.0 copied an empty DIV/2 box5, or the old
     * page saved a lone '' before 3.6), NULL (the row was created for another field), or no DIV/3 row at all.
     */
    private function setOlderBox5(bool $hasRow, ?string $box5): void
    {
        if ($hasRow) {
            self::rows("UPDATE grupper SET box5 = $1 WHERE art = 'DIV' AND kodenr = '3'", [$box5]);
            return;
        }
        $this->removedRows = self::rows("SELECT * FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
        self::rows("DELETE FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
    }

    private static function box5(): string
    {
        $row = self::one("SELECT box5 FROM grupper WHERE art = 'DIV' AND kodenr = '3' ORDER BY id LIMIT 1");
        return (string) ($row['box5'] ?? '');
    }

    private static function sectionOf(string $key): string
    {
        return $key === self::SALES ? 'sales.orders' : 'purchase.orders';
    }

    /** @return array{status:int|bool, html:string} */
    private function page(string $section, string $body = ''): array
    {
        return $this->runChildJson('run_settings_section_page.php', ["s=$section", $body, CharacterizationEnv::sessionId()]);
    }

    private static function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new DOMXPath($doc);
    }

    /**
     * What a browser posts for the section's form as drawn: every named, enabled input (ticked boxes only), the chosen
     * option of each select and each textarea. A locked field has no name, so it is not part of it.
     *
     * @return array<string, string>
     */
    private static function drawnForm(string $html): array
    {
        $x = self::xpath($html);
        $form = $x->query("//form[@id='st-form']")->item(0);
        if ($form === null) {
            self::fail('the section draws its form (st-form)');
        }
        $fields = [];
        foreach ($x->query('.//input[@name]', $form) as $input) {
            $type = strtolower($input->getAttribute('type'));
            if ($input->hasAttribute('disabled') || in_array($type, ['submit', 'button'], true)) {
                continue;
            }
            if (in_array($type, ['checkbox', 'radio'], true) && !$input->hasAttribute('checked')) {
                continue;
            }
            $fields[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        foreach ($x->query('.//select[@name]', $form) as $select) {
            if ($select->hasAttribute('disabled')) {
                continue;
            }
            $chosen = null;
            foreach ($select->getElementsByTagName('option') as $option) {
                if ($chosen === null || $option->hasAttribute('selected')) {
                    $chosen = $option->getAttribute('value');
                }
            }
            $fields[$select->getAttribute('name')] = (string) $chosen;
        }
        foreach ($x->query('.//textarea[@name]', $form) as $textarea) {
            $fields[$textarea->getAttribute('name')] = $textarea->textContent;
        }
        return $fields;
    }

    /**
     * How one bool field is drawn.
     *
     * @return array{on:bool, disabled:bool, posted:bool, locked:string, more:string, error:string}
     */
    private static function field(string $html, string $key): array
    {
        $x = self::xpath($html);
        $row = $x->query("//div[@data-key='$key']")->item(0);
        if ($row === null) {
            self::fail("the page draws the field $key");
        }
        $switch = $x->query(".//button[contains(@class, 'st-switch')]", $row)->item(0);
        $control = $x->query(".//input[@type='hidden' and @data-control]", $row)->item(0);
        $locked = $x->query(".//span[contains(@class, 'st-locked')]", $row)->item(0);
        $more = $x->query(".//details[contains(@class, 'st-more')]", $row)->item(0);
        $error = $x->query(".//span[contains(@class, 'st-error')]", $row)->item(0);
        return [
            'on' => $switch !== null && $switch->getAttribute('aria-checked') === 'true',
            'disabled' => $switch !== null && $switch->hasAttribute('disabled'),
            'posted' => $control !== null && $control->getAttribute('name') === "f[$key]",
            'locked' => $locked !== null ? trim($locked->textContent) : '',
            'more' => $more !== null ? trim($more->textContent) : '',
            'error' => $error !== null ? trim($error->textContent) : '',
        ];
    }

    /** Draws the section and posts its form with $changes on top, as a browser (or a crafted request) would. */
    private function save(string $section, array $changes): array
    {
        $page = $this->page($section);
        $form = self::drawnForm($page['html']);
        $this->assertArrayHasKey('csrf_token', $form, 'the form carries the session token');
        return $this->page($section, http_build_query($changes + $form));
    }

    /** @return array<string, array{0: string, 1: string}> box5 as stored, the setting that is on */
    public static function onStates(): array
    {
        return [
            'sales on, both on'                        => ['on;on', self::SALES],
            'purchases on, both on'                    => ['on;on', self::PURCHASES],
            'sales on, a lone on from before 3.6'      => ['on', self::SALES],
            'purchases on, a lone on from before 3.6'  => ['on', self::PURCHASES],
            'sales on, purchases off'                  => ['on;', self::SALES],
            'purchases on, sales off'                  => [';on', self::PURCHASES],
        ];
    }

    #[DataProvider('onStates')]
    public function test_a_setting_that_is_on_is_drawn_on_and_locked(string $box5, string $key): void
    {
        self::setBox5($box5);
        $page = $this->page(self::sectionOf($key));
        $field = self::field($page['html'], $key);

        $this->assertTrue($field['on'], 'drawn on');
        $this->assertTrue($field['disabled'], 'the switch cannot be clicked');
        $this->assertFalse($field['posted'], 'the field is not part of the posted form');
        $this->assertStringContainsString(self::LOCK_TEXT, $field['locked'], 'the lock says why');
        $this->assertArrayNotHasKey("f[$key]", self::drawnForm($page['html']), 'saving the page as drawn does not send it');
    }

    #[DataProvider('onStates')]
    public function test_a_crafted_post_that_turns_it_off_is_refused(string $box5, string $key): void
    {
        self::setBox5($box5);
        $res = $this->save(self::sectionOf($key), ["f[$key]" => '0']);

        $this->assertSame(302, $res['status'], 'the save returns to the page as any save does');
        $this->assertSame($box5, self::box5(), 'DIV/3 box5 is unchanged: the setting is still on');
    }

    public function test_gendan_of_a_value_from_before_it_was_switched_on_is_refused(): void
    {
        self::setBox5(';');
        $this->save('sales.orders', ['f[' . self::SALES . ']' => '1']);
        $this->assertSame('on;', self::box5(), 'switched on');
        $entry = self::one("SELECT max(id) AS id FROM audit_log WHERE setting_key = $1 AND new_value = 'on'", [self::SALES]);
        $this->assertNotNull($entry['id'] ?? null, 'the change is in the history');

        $page = $this->page('sales.orders');
        $this->assertSame(0, self::xpath($page['html'])->query("//*[@data-restore='" . (int) $entry['id'] . "']")->length, 'the history offers no Gendan for it');
        $form = self::drawnForm($page['html']);
        $res = $this->page('sales.orders', http_build_query(['csrf_token' => $form['csrf_token'], 'action' => 'revert', 'entry' => (int) $entry['id']]));

        $this->assertSame(302, $res['status']);
        $this->assertSame('on;', self::box5(), 'Gendan does not switch it off');
    }

    /** @return array<string, array{0: string, 1: string}> the setting that is off, box5 after it is switched on */
    public static function offStates(): array
    {
        return [
            'sales'     => [self::SALES, 'on;'],
            'purchases' => [self::PURCHASES, ';on'],
        ];
    }

    #[DataProvider('offStates')]
    public function test_a_setting_that_is_off_can_be_switched_on_and_is_locked_from_then_on(string $key, string $after): void
    {
        self::setBox5(';');
        $section = self::sectionOf($key);
        $field = self::field($this->page($section)['html'], $key);
        $this->assertFalse($field['on'], 'drawn off');
        $this->assertTrue($field['posted'] && !$field['disabled'] && $field['locked'] === '', 'off, it can be changed');
        $this->assertStringContainsString(self::MORE_TEXT, $field['more'], 'Læs mere warns that it cannot be switched off again');

        $res = $this->save($section, ["f[$key]" => '1']);
        $this->assertSame(302, $res['status']);
        $this->assertSame($after, self::box5(), 'switched on');

        $field = self::field($this->page($section)['html'], $key);
        $this->assertTrue($field['on'] && $field['disabled'] && !$field['posted'], 'from then on it is locked');
    }

    public function test_a_setting_that_is_off_stays_off_when_the_page_is_saved_for_another_field(): void
    {
        self::setBox5(';');
        self::rows("UPDATE grupper SET box3 = '' WHERE art = 'DIV' AND kodenr = '3'");
        foreach ([self::SALES, self::PURCHASES] as $key) {
            $field = self::field($this->page(self::sectionOf($key))['html'], $key);
            $this->assertTrue(!$field['on'] && $field['posted'] && $field['locked'] === '', "$key: drawn off and unlocked");
            $this->assertStringContainsString(self::MORE_TEXT, $field['more'], "$key: Læs mere warns before it is switched on");
        }

        $res = $this->save('sales.orders', ['f[' . self::OTHER . ']' => '1']);
        $this->assertSame(302, $res['status']);
        $this->assertSame('on', (string) self::one("SELECT box3 FROM grupper WHERE art = 'DIV' AND kodenr = '3' ORDER BY id LIMIT 1")['box3'], 'the other field is saved');
        $this->assertSame(';', self::box5(), 'both stay off: no migration, waiting invoices still need Hent');
    }

    /** The open journal's page, as finans/kassekladde.php?kladde_id=.. draws it. */
    private function journalHasHent(): bool
    {
        $kladdeId = self::$fx->kladde([]);
        $htmlFile = (string) tempnam(sys_get_temp_dir(), 'chartest-kassekladde-');
        try {
            $res = self::runChild('run_kassekladde_page.php', ["kladde_id=$kladdeId", CharacterizationEnv::sessionId(), $htmlFile]);
            $html = (string) file_get_contents($htmlFile);
        } finally {
            @unlink($htmlFile);
        }
        $this->assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], "the page ran to its end.\nstderr: {$res['stderr']}");
        $this->assertTrue(str_contains($html, "name='doPost'"), 'the journal is open for editing: its buttons are drawn');
        return preg_match(self::HENT, $html) === 1;
    }

    public function test_hent_follows_the_settings_and_no_waiting_invoice_is_left_without_it(): void
    {
        self::setBox5(';');
        $order = $this->waitingOrder('DO');
        $this->assertTrue($this->journalHasHent(), 'both off, an invoice waits: Hent');

        $res = $this->save('sales.orders', ['f[' . self::SALES . ']' => '1']);
        $this->assertNotSame(302, $res['status'], 'the save is refused');
        $this->assertSame(';', self::box5(), 'sales stays off while a sales invoice waits for Hent');

        $this->save('purchase.orders', ['f[' . self::PURCHASES . ']' => '1']);
        $this->assertSame(';on', self::box5(), 'no purchase invoice waits: purchases can be switched on');
        $this->assertTrue($this->journalHasHent(), 'sales is still off: the invoice keeps Hent');

        $this->save('purchase.orders', ['f[' . self::PURCHASES . ']' => '0']);
        $this->assertSame(';on', self::box5(), 'purchases cannot be switched off again');

        // Hent ordrer's Flyt til kladde moves the invoice into the journal and sets it to status 4.
        self::rows('UPDATE ordrer SET status = 4 WHERE id = $1', [$order]);
        $this->save('sales.orders', ['f[' . self::SALES . ']' => '1']);
        $this->assertSame('on;on', self::box5(), 'nothing waits any more: sales can be switched on');
        $this->assertFalse($this->journalHasHent(), 'both on and nothing waits: no Hent');

        $this->save('purchase.orders', ['f[' . self::PURCHASES . ']' => '0']);
        $this->save('sales.orders', ['f[' . self::SALES . ']' => '0']);
        $this->assertSame('on;on', self::box5(), 'neither can be switched off again');
        $this->assertFalse($this->journalHasHent(), 'so Hent does not come back');
    }

    /** An invoice (or credit note, or POS order) of this art at status 3, as invoicing leaves it while Omgående bogføring is off; a reminder (R1) as a reminder posting that failed leaves it. */
    private function waitingOrder(string $art): int
    {
        $lines = [['item' => self::$fx->item(['stocked' => false]), 'antal' => 1, 'pris' => 1000.00]];
        $order = in_array($art, ['KO', 'KK'], true)
            ? self::$fx->creditorOrder(self::$fx->creditor(), $lines, ['status' => 3, 'art' => $art])
            : self::$fx->order(self::$fx->debtor(), $lines, ['status' => 3, 'art' => $art]);
        $this->createdOrders[] = (int) $order['id'];
        return (int) $order['id'];
    }

    /** vis_saet (grupper POS/2 box12 of the fiscal year): the register close takes the sales orders paid at a till. */
    private function setTillClose(string $box12): void
    {
        $row = self::one("SELECT id, box12 FROM grupper WHERE art = 'POS' AND kodenr = '2' AND fiscal_year = $1 ORDER BY id LIMIT 1", [self::$regnaar]);
        if ($row === null) {
            $row = self::one("INSERT INTO grupper (beskrivelse, kodenr, art, fiscal_year, box12) VALUES ('Pos valg', '2', 'POS', $1, '') RETURNING id, box12", [self::$regnaar]);
            $this->pos2 ??= ['id' => $row['id'], 'box12' => null, 'created' => true];
        }
        $this->pos2 ??= ['id' => $row['id'], 'box12' => $row['box12'], 'created' => false];
        self::rows('UPDATE grupper SET box12 = $1 WHERE id = $2', [$box12, $row['id']]);
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> the setting, the art of the invoice that waits, box5 once it is switched on */
    public static function waitingStates(): array
    {
        return [
            'sales, an invoice (DO)'          => [self::SALES, 'DO', 'on;'],
            'sales, a credit note (DK)'       => [self::SALES, 'DK', 'on;'],
            'purchases, a bill (KO)'          => [self::PURCHASES, 'KO', ';on'],
            'purchases, a credit note (KK)'   => [self::PURCHASES, 'KK', ';on'],
            'sales, a reminder (R1)'          => [self::SALES, 'R1', 'on;'],
        ];
    }

    #[DataProvider('waitingStates')]
    public function test_a_setting_is_not_switched_on_while_invoices_of_its_kind_wait_for_hent(string $key, string $art, string $after): void
    {
        self::setBox5(';');
        $order = $this->waitingOrder($art);
        $section = self::sectionOf($key);
        $message = $key === self::SALES ? self::WAITING_SALES : self::WAITING_PURCHASES;

        $res = $this->save($section, ["f[$key]" => '1']);
        $this->assertNotSame(302, $res['status'], 'the save is refused and the page is drawn again');
        $this->assertSame(';', self::box5(), 'it stays off');
        $field = self::field($res['html'], $key);
        $this->assertSame($message, $field['error'], 'the field says to fetch the invoices into a cash journal first');
        $this->assertTrue($field['posted'] && !$field['disabled'] && $field['locked'] === '', 'it is not locked: it can be switched on later');
        $this->assertTrue($this->journalHasHent(), 'the invoice keeps Hent');

        // A crafted post of nothing but the token and the switch is refused the same way.
        $form = self::drawnForm($this->page($section)['html']);
        $res = $this->page($section, http_build_query(['csrf_token' => $form['csrf_token'], 'version' => $form['version'], 'f' => [$key => '1']]));
        $this->assertNotSame(302, $res['status']);
        $this->assertStringContainsString($message, $res['html']);
        $this->assertSame(';', self::box5(), 'a crafted post does not switch it on either');

        // Hent ordrer's Flyt til kladde moves the invoice into the journal and sets it to status 4.
        self::rows('UPDATE ordrer SET status = 4 WHERE id = $1', [$order]);
        $res = $this->save($section, ["f[$key]" => '1']);
        $this->assertSame(302, $res['status']);
        $this->assertSame($after, self::box5(), 'once the invoice is fetched, it can be switched on');
    }

    public function test_while_the_other_setting_is_off_only_invoices_of_its_own_kind_keep_a_setting_off(): void
    {
        self::setBox5(';');
        $bill = $this->waitingOrder('KO');
        $this->waitingOrder('PO');
        $this->save('purchase.orders', ['f[' . self::PURCHASES . ']' => '1']);
        $this->assertSame(';', self::box5(), 'the waiting purchase bill keeps purchases off');
        $this->save('sales.orders', ['f[' . self::SALES . ']' => '1']);
        $this->assertSame('on;', self::box5(), 'a waiting purchase bill and a POS order do not keep sales off');
        $this->assertTrue($this->journalHasHent(), 'purchases is off: the bill keeps Hent');

        self::setBox5(';');
        self::rows('UPDATE ordrer SET status = 4 WHERE id = $1', [$bill]);
        $this->waitingOrder('DK');
        $this->save('sales.orders', ['f[' . self::SALES . ']' => '1']);
        $this->assertSame(';', self::box5(), 'the waiting sales credit note keeps sales off');
        $this->save('purchase.orders', ['f[' . self::PURCHASES . ']' => '1']);
        $this->assertSame(';on', self::box5(), 'a waiting sales credit note does not keep purchases off');
    }

    public function test_a_sales_order_paid_at_a_till_keeps_sales_off_only_while_hent_ordrer_lists_it(): void
    {
        self::setBox5(';');
        $order = $this->waitingOrder('DO');
        // Paid at till 1 (felt_1 the payment, felt_2 the amount, felt_4 the change, felt_5 the till), as the till leaves it.
        self::rows("UPDATE ordrer SET felt_1 = 'Kontant', felt_2 = '1250', felt_4 = '0', felt_5 = '1' WHERE id = $1", [$order]);

        $this->setTillClose('');
        $this->save('sales.orders', ['f[' . self::SALES . ']' => '1']);
        $this->assertSame(';', self::box5(), 'without vis_saet Hent ordrer lists it: sales stays off');

        $this->setTillClose('on');
        $this->save('sales.orders', ['f[' . self::SALES . ']' => '1']);
        $this->assertSame('on;', self::box5(), 'with vis_saet the register close takes it and Hent ordrer does not list it: sales can be switched on');
    }

    /**
     * Hent ordrer leaves only a sales order (art D*) paid at a till to the register close: a reminder with the same fields is
     * listed, so it keeps sales off with vis_saet too.
     */
    public function test_a_reminder_keeps_sales_off_also_with_vis_saet(): void
    {
        self::setBox5(';');
        $reminder = $this->waitingOrder('R1');
        self::rows("UPDATE ordrer SET felt_1 = 'Kontant', felt_2 = '1250', felt_4 = '0', felt_5 = '1' WHERE id = $1", [$reminder]);
        $this->setTillClose('on');

        $res = $this->save('sales.orders', ['f[' . self::SALES . ']' => '1']);
        $this->assertNotSame(302, $res['status'], 'the save is refused');
        $this->assertSame(self::WAITING_SALES, self::field($res['html'], self::SALES)['error']);
        $this->assertSame(';', self::box5(), 'sales stays off');
        $this->assertTrue($this->journalHasHent(), 'the reminder keeps Hent');
    }

    public function test_gendan_that_would_switch_it_on_while_invoices_wait_is_refused_with_the_message(): void
    {
        self::setBox5(';');
        $this->waitingOrder('DO');
        // A switch-off from before it could no longer be switched off: its Gendan would switch sales on.
        $entry = self::one("INSERT INTO audit_log (handling, objekt_type, objekt_id, brugernavn, setting_key, section, old_value, new_value)
            VALUES ('setting.changed', 'indstilling', $1, 'chartest', $1, 'sales.orders', 'on', '') RETURNING id", [self::SALES]);

        $page = $this->page('sales.orders');
        $this->assertSame(1, self::xpath($page['html'])->query("//*[@data-restore='" . (int) $entry['id'] . "']")->length, 'switching on is not locked: the history offers Gendan');
        $form = self::drawnForm($page['html']);
        $res = $this->page('sales.orders', http_build_query(['csrf_token' => $form['csrf_token'], 'action' => 'revert', 'entry' => (int) $entry['id']]));

        $this->assertSame(302, $res['status']);
        $this->assertSame(';', self::box5(), 'Gendan does not switch it on while a sales invoice waits');
        $this->assertStringContainsString(self::WAITING_SALES, $this->page('sales.orders')['html'], 'the page it returns to says why');
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> box5 with one setting on, the art of the invoice of that kind that waits, the last setting that is off, the message */
    public static function otherKindStates(): array
    {
        return [
            'sales on, an invoice (DO) waits, purchases switched on'      => ['on;', 'DO', self::PURCHASES, self::WAITING_SALES],
            'sales on, a credit note (DK) waits, purchases switched on'   => ['on;', 'DK', self::PURCHASES, self::WAITING_SALES],
            'purchases on, a bill (KO) waits, sales switched on'          => [';on', 'KO', self::SALES, self::WAITING_PURCHASES],
            'purchases on, a credit note (KK) waits, sales switched on'   => [';on', 'KK', self::SALES, self::WAITING_PURCHASES],
            'sales on, a reminder (R1) waits, purchases switched on'      => ['on;', 'R1', self::PURCHASES, self::WAITING_SALES],
        ];
    }

    /**
     * One setting is on (from before this rule, or the invoice came back to status 3 since) and an invoice of its kind
     * waits; the journal shows Hent because the other setting is off. Switching the other setting on would turn both on,
     * and the journal then has no Hent for any kind: the invoice would stay at status 3, off the accounts. So the last
     * setting is not switched on, by a save, a crafted post or Gendan, and the message names the kind that waits.
     */
    #[DataProvider('otherKindStates')]
    public function test_the_last_setting_is_not_switched_on_while_invoices_of_the_other_kind_wait(string $box5, string $art, string $key, string $message): void
    {
        self::setBox5($box5);
        $order = $this->waitingOrder($art);
        $section = self::sectionOf($key);
        $this->assertTrue($this->journalHasHent(), 'one setting is off and an invoice waits: Hent');

        $res = $this->save($section, ["f[$key]" => '1']);
        $this->assertNotSame(302, $res['status'], 'the save is refused and the page is drawn again');
        $this->assertSame($box5, self::box5(), 'the last setting stays off');
        $field = self::field($res['html'], $key);
        $this->assertSame($message, $field['error'], 'the field says which invoices to fetch into a cash journal first');
        $this->assertTrue($field['posted'] && !$field['disabled'] && $field['locked'] === '', 'it is not locked: it can be switched on later');
        $this->assertTrue($this->journalHasHent(), 'the invoice keeps Hent');

        // A crafted post of nothing but the token and the switch is refused the same way.
        $form = self::drawnForm($this->page($section)['html']);
        $res = $this->page($section, http_build_query(['csrf_token' => $form['csrf_token'], 'version' => $form['version'], 'f' => [$key => '1']]));
        $this->assertNotSame(302, $res['status']);
        $this->assertStringContainsString($message, $res['html']);
        $this->assertSame($box5, self::box5(), 'a crafted post does not switch it on either');

        // Gendan of a switch-off from before it could no longer be switched off would switch it on.
        $entry = self::one("INSERT INTO audit_log (handling, objekt_type, objekt_id, brugernavn, setting_key, section, old_value, new_value)
            VALUES ('setting.changed', 'indstilling', $1, 'chartest', $1, $2, 'on', '') RETURNING id", [$key, $section]);
        $form = self::drawnForm($this->page($section)['html']);
        $res = $this->page($section, http_build_query(['csrf_token' => $form['csrf_token'], 'action' => 'revert', 'entry' => (int) $entry['id']]));
        $this->assertSame(302, $res['status']);
        $this->assertSame($box5, self::box5(), 'Gendan does not switch it on either');
        $this->assertStringContainsString($message, $this->page($section)['html'], 'the page Gendan returns to says why');

        // Hent ordrer's Flyt til kladde moves the invoice into the journal and sets it to status 4.
        self::rows('UPDATE ordrer SET status = 4 WHERE id = $1', [$order]);
        $res = $this->save($section, ["f[$key]" => '1']);
        $this->assertSame(302, $res['status']);
        $this->assertSame('on;on', self::box5(), 'once the invoice is fetched, the last setting can be switched on');
        $this->assertFalse($this->journalHasHent(), 'both on and nothing waits: no Hent');
    }

    public function test_the_last_setting_is_switched_on_when_only_orders_hent_ordrer_does_not_list_wait(): void
    {
        self::setBox5('on;');
        $this->waitingOrder('PO');
        $paid = $this->waitingOrder('DO');
        // Paid at till 1 (felt_1 the payment, felt_2 the amount, felt_4 the change, felt_5 the till), as the till leaves it.
        self::rows("UPDATE ordrer SET felt_1 = 'Kontant', felt_2 = '1250', felt_4 = '0', felt_5 = '1' WHERE id = $1", [$paid]);

        $this->setTillClose('');
        $this->save('purchase.orders', ['f[' . self::PURCHASES . ']' => '1']);
        $this->assertSame('on;', self::box5(), 'without vis_saet Hent ordrer lists the paid sales order: purchases stays off');

        $this->setTillClose('on');
        $this->save('purchase.orders', ['f[' . self::PURCHASES . ']' => '1']);
        $this->assertSame('on;on', self::box5(), 'a POS order, and with vis_saet a sales order paid at a till, are not in Hent ordrer: purchases can be switched on');
    }

    private static function orderStatus(int $id): string
    {
        return (string) self::one('SELECT status FROM ordrer WHERE id = $1', [$id])['status'];
    }

    /**
     * Under these rules alone: a sales invoice is fetched into a journal, sales is switched on (nothing waits), and the
     * invoice's journal lines are deleted, which sends it back to status 3 (finans/kassekladde.php). Hent is still there
     * while purchases is off, and purchases is not switched on until the invoice has been fetched again.
     */
    public function test_an_invoice_sent_back_from_the_journal_keeps_the_last_setting_off(): void
    {
        self::setBox5(';');
        $order = self::$fx->order(self::$fx->debtor(), [['item' => self::$fx->item(['stocked' => false]), 'antal' => 2, 'pris' => 125.00]]);
        $this->createdOrders[] = (int) $order['id'];
        self::rows("UPDATE ordrelinjer SET projekt = '' WHERE ordre_id = $1", [$order['id']]);
        $out = $this->runChildJson('run_order_invoice.php', ['invoice', $order['id'], CharacterizationEnv::testDb()]);
        $this->assertSame('OK', $out['bogfor'] ?? null, 'invoiced');
        $this->assertSame('3', self::orderStatus((int) $order['id']), 'sales is off: the invoice waits for Hent');
        $kladdeId = self::$fx->kladde([]);
        $res = self::runChild('run_hentordrer_page.php', ["kladde_id=$kladdeId&ordre_id={$order['id']}", CharacterizationEnv::sessionId()]);
        $this->assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], $res['stderr']);
        $this->assertSame('4', self::orderStatus((int) $order['id']), 'Flyt til kladde moved it into the journal');
        self::rows("UPDATE kladdeliste SET bogfort = '-' WHERE id = $1", [$kladdeId]); // an open journal, as kassekladde.php creates it

        $this->save('sales.orders', ['f[' . self::SALES . ']' => '1']);
        $this->assertSame('on;', self::box5(), 'nothing waits at status 3: sales can be switched on');

        $lineIds = array_map('intval', array_column(self::rows('SELECT id FROM kassekladde WHERE kladde_id = $1 AND ordre_id = $2 ORDER BY id', [$kladdeId, $order['id']]), 'id'));
        $this->assertNotSame([], $lineIds, 'the invoice has lines in the journal');
        $body = ['save' => 'Gem', 'kladde_id' => $kladdeId, 'antal_ny' => count($lineIds), 'id' => []];
        foreach ($lineIds as $i => $lineId) {
            $body['id'][$i + 1] = $lineId;
            $body['bila' . ($i + 1)] = '-';
        }
        $htmlFile = (string) tempnam(sys_get_temp_dir(), 'chartest-kassekladde-');
        try {
            $res = self::runChild('run_kassekladde_save_page.php', [http_build_query($body), CharacterizationEnv::sessionId(), $htmlFile]);
            $this->assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], $res['stderr']);
        } finally {
            @unlink($htmlFile);
        }
        $this->assertSame('3', self::orderStatus((int) $order['id']), 'its journal lines were deleted: back at status 3');
        $this->assertTrue($this->journalHasHent(), 'purchases is off: Hent is there');

        $res = $this->save('purchase.orders', ['f[' . self::PURCHASES . ']' => '1']);
        $this->assertNotSame(302, $res['status'], 'the save is refused');
        $this->assertSame(self::WAITING_SALES, self::field($res['html'], self::PURCHASES)['error']);
        $this->assertSame('on;', self::box5(), 'purchases stays off');
        $this->assertTrue($this->journalHasHent(), 'the invoice keeps Hent');
        $this->assertSame([], self::rows('SELECT id FROM transaktioner WHERE ordre_id = $1', [$order['id']]), 'it is not posted yet: Hent is the only way to the accounts');
    }

    /** @return array<string, array{0: bool, 1: ?string, 2: string, 3: string}> whether DIV/3 exists, its box5, the setting switched on, box5 after */
    public static function olderStates(): array
    {
        return [
            'sales on, box5 empty'          => [true, '', self::SALES, 'on;'],
            'sales on, box5 NULL'           => [true, null, self::SALES, 'on;'],
            'sales on, no DIV/3 row'        => [false, null, self::SALES, 'on;'],
            'purchases on, box5 empty'      => [true, '', self::PURCHASES, ';on'],
            'purchases on, box5 NULL'       => [true, null, self::PURCHASES, ';on'],
            'purchases on, no DIV/3 row'    => [false, null, self::PURCHASES, ';on'],
        ];
    }

    /**
     * Both off as an older ledger stores it. Switching one setting on must not write a lone 'on', which every reader
     * (includes/ordrefunc.php, kreditor/bogfor.php, finans/kassekladde.php) takes as both on and which the lock would then
     * keep: the other setting stays off, can be changed, and its waiting invoice keeps Hent.
     */
    #[DataProvider('olderStates')]
    public function test_switching_one_setting_on_from_an_older_empty_value_leaves_the_other_off(bool $hasRow, ?string $box5, string $key, string $after): void
    {
        $this->setOlderBox5($hasRow, $box5);
        $other = $key === self::SALES ? self::PURCHASES : self::SALES;
        // An invoice of the other kind waits at status 3: a purchase bill of 1.000,00 when sales is switched on, a sales invoice when purchases is.
        $lines = [['item' => self::$fx->item(['stocked' => false]), 'antal' => 1, 'pris' => 1000.00]];
        $waiting = $key === self::SALES
            ? self::$fx->creditorOrder(self::$fx->creditor(), $lines, ['status' => 3])
            : self::$fx->order(self::$fx->debtor(), $lines, ['status' => 3]);
        $this->createdOrders[] = (int) $waiting['id'];
        foreach ([self::SALES, self::PURCHASES] as $k) {
            $field = self::field($this->page(self::sectionOf($k))['html'], $k);
            $this->assertTrue(!$field['on'] && $field['posted'] && !$field['disabled'], "$k: drawn off and unlocked");
        }
        $this->assertTrue($this->journalHasHent(), 'both off, an invoice waits: Hent');

        $res = $this->save(self::sectionOf($key), ["f[$key]" => '1']);
        $this->assertSame(302, $res['status']);
        $this->assertSame($after, self::box5(), 'only the setting that was switched on is stored on, never a lone on (both)');

        $field = self::field($this->page(self::sectionOf($other))['html'], $other);
        $this->assertFalse($field['on'], 'the other setting is still off');
        $this->assertTrue($field['posted'] && !$field['disabled'] && $field['locked'] === '', 'the other setting is not locked');
        $this->assertTrue($this->journalHasHent(), 'the other setting is off: the waiting invoice keeps Hent');

        $res = $this->save(self::sectionOf($other), ["f[$other]" => '1']);
        $this->assertNotSame(302, $res['status']);
        $this->assertSame($after, self::box5(), 'the other setting is not switched on while its invoice waits for Hent');

        // Hent ordrer's Flyt til kladde moves the invoice into the journal and sets it to status 4.
        self::rows('UPDATE ordrer SET status = 4 WHERE id = $1', [$waiting['id']]);
        $this->save(self::sectionOf($other), ["f[$other]" => '1']);
        $this->assertSame('on;on', self::box5(), 'once it is fetched, the other setting can be switched on');
    }
}
