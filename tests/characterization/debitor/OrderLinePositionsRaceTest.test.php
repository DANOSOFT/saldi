<?php
// tests/characterization/debitor/OrderLinePositionsRaceTest.test.php
//
// Regression test for the testlab finding DEVY-1 (concurrent-posting, round 2):
// two users adding lines to one order gave the new lines the same posnr.
// A opened the order and B opened it too. A added an item and pressed Gem, then
// B, without reloading, added another item and pressed Gem: both lines were
// stored at posnr 100. In the second round both added a line again from the
// page their save had drawn, and the order ended at 100, 200, 200, 300: B's
// form gave its new line the position A's line already had, and its save moved
// a line it showed onto A's new line.
//
// Each Gem writes the positions its form showed (1, 2, 3 ... x 100) and puts the
// new line at the position the form offered, the number of lines it showed + 1.
// The forms now carry the ids of the lines they were drawn with
// (order_lines_drawn), and the save keeps the lines it did not show on
// positions of their own (includes/stdFunc/orderLinePositions.php).
//
// Drives the pages as the lab did, through the order forms as they were drawn:
// debitor/ordre.php (support/run_debitor_order_page.php) and the purchase order
// page, kreditor/ordre.php (support/run_kreditor_order_page.php), which saves
// positions the same way. Also pins that one user's saves keep the positions
// the form gives: a new line at the end, or where Pos puts it.
//
// Three ways the first version of the fix (an id watermark, no lock on the
// sales order) still gave two lines one position:
// - Two sales order saves at the same moment: both read the order's lines
//   before either had written, so both put their new line at 100. Here the
//   saves run as two processes with sessions of their own, and a colleague's
//   connection holds the order's lines until both have started: the second
//   save must wait for the first, as the purchase order's does.
// - An order with a set, with Vis sæt on: the page numbers a set's rows
//   (first row, then + 1 each) after the save had moved a colleague's line to
//   + 1. The sets are now numbered before the save looks for clashes.
// - A purchase order line moved in from an older order keeps its id, which was
//   below the watermark, so the stale form put its new line on it.
//
// finans/ordre.php, an older copy of the sales order page, saved positions the
// same way: two forms of one empty order both offered Pos 1, and a text line
// saved from each was stored at posnr 1 twice. It is driven here through the
// same runner, the only way it can be used today: from forms of an order
// without lines, with text lines, and with a discount in the debtor group.
// The page dies on its own bugs otherwise - a save when the group's discount
// is empty ('' * 1), a Gem of an item (opret_ordrelinje()'s old argument list),
// and drawing an order that has lines (var2str() gets 2 of its 11 arguments),
// which also ends each save's answer after the save has committed.
//
// debitor/ret_genfakt.php, which edits the lines of subscription orders, saved
// positions the same way, as Pos 1, 2, 3: two forms of one empty order both
// offered Pos 1, and a text line saved from each was stored at posnr 1 twice.
//
// A position the fix picks stays within the posnr column. Regnskaber from
// Saldi's first versions have it as smallint (32767 at most): one user's line at
// Pos 327 (32700) and a stale form's new line put after it (32800) made the
// database refuse the second save.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class OrderLinePositionsRaceTest extends CharacterizationTestCase
{
    /** @var array{id:int, kontonr:string} */
    private array $debtor;
    /** @var array{id:int, kontonr:string} */
    private array $creditor;
    /** @var array{id:int, varenr:string, salgspris:float, kostpris:float, gruppe:int} */
    private array $first;
    /** @var array{id:int, varenr:string, salgspris:float, kostpris:float, gruppe:int} */
    private array $second;
    /** ordrer.id before the test: everything above it is the test's own */
    private int $lastOrderId = 0;
    /** The session of the second user, B, in the master online table; seeded by secondSession() */
    private const SESSION_B = 'saldichartestsession000000000002';
    private bool $sessionB = false;
    /** grupper.id of the Vis sæt setting a test turned on */
    private ?int $showSets = null;
    /** @var list<array{id:int, varenr:string, salgspris:float, kostpris:float, gruppe:int}> other items a test made */
    private array $items = [];
    /** @var list<array{id:string, box6:?string}> the debtor group rows whose discount a finans/ordre.php test set, with their value before */
    private array $groupDiscount = [];
    /** A test made ordrelinjer.posnr smallint, as in regnskaber from Saldi's first versions; tearDown() makes it integer again */
    private bool $smallintPosnr = false;

    protected function setUp(): void
    {
        $this->debtor = self::$fx->debtor();
        $this->creditor = self::$fx->creditor();
        $this->first = self::$fx->item(['salgspris' => 59.20]);
        $this->second = self::$fx->item(['salgspris' => 59.20]);
        foreach ([$this->first, $this->second] as $item) {
            self::rows('INSERT INTO vare_lev (posnr, lev_id, vare_id, lev_varenr, kostpris) VALUES (1, $1, $2, $3, 40)',
                [$this->creditor['id'], $item['id'], $item['varenr']]);
        }
        $max = self::one('SELECT COALESCE(max(id), 0) AS id FROM ordrer');
        $this->lastOrderId = (int)$max['id'];
    }

    protected function tearDown(): void
    {
        foreach ($this->groupDiscount as $group) {
            self::rows('UPDATE grupper SET box6 = $1 WHERE id = $2', [$group['box6'], $group['id']]);
        }
        $this->groupDiscount = [];
        if ($this->showSets !== null) {
            self::rows('DELETE FROM grupper WHERE id = $1', [$this->showSets]);
            $this->showSets = null;
        }
        if ($this->sessionB) {
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            CharacterizationEnv::rows($master, 'DELETE FROM online WHERE session_id = $1', [self::SESSION_B]);
            pg_close($master);
            $this->sessionB = false;
        }
        $items = array_merge([$this->first['id'], $this->second['id']], array_column($this->items, 'id'));
        self::rows('DELETE FROM ordrelinjer WHERE ordre_id > $1', [$this->lastOrderId]);
        if ($this->smallintPosnr) {
            self::rows('ALTER TABLE ordrelinjer ALTER COLUMN posnr TYPE integer');
            $this->smallintPosnr = false;
        }
        self::rows('DELETE FROM batch_salg WHERE vare_id = ANY($1::int[])', ['{' . implode(',', $items) . '}']);
        self::rows('DELETE FROM reservation WHERE vare_id = ANY($1::int[])', ['{' . implode(',', $items) . '}']);
        self::rows('DELETE FROM ordrer WHERE id > $1', [$this->lastOrderId]);
        self::rows('DELETE FROM vare_lev WHERE vare_id = ANY($1::int[])', ['{' . implode(',', $items) . '}']);
        self::rows('DELETE FROM varer WHERE id = ANY($1::int[])', ['{' . implode(',', $items) . '}']);
        self::rows('DELETE FROM adresser WHERE id IN ($1, $2)', [$this->debtor['id'], $this->creditor['id']]);
    }

    /**
     * Opens or saves a sales order: a GET of debitor/ordre.php?id=, or a POST of its form.
     *
     * @param array<string, string> $post the form's fields, or [] for a GET
     * @return string the page's HTML
     */
    private function salesOrder(int $orderId, array $post = [], string $session = CharacterizationEnv::SESSION_ID): string
    {
        return $this->finish($this->startSalesOrder($orderId, $post, $session));
    }

    /**
     * Opens or saves a sales order on finans/ordre.php, the older copy of the sales order page. A save's answer is the
     * order drawn again, which dies on the order's first line (var2str()) after the save has committed, so only the
     * opening of an order without lines must draw to the end.
     *
     * @param array<string, string> $post the form's fields, or [] for a GET
     * @return string the page's HTML
     */
    private function financeOrder(int $orderId, array $post = [], string $session = CharacterizationEnv::SESSION_ID): string
    {
        return $this->finish($this->startSalesOrder($orderId, $post, $session, 'finans/ordre.php'), $post !== []);
    }

    /**
     * finans/ordre.php saves only for a debtor whose group has a discount: an empty one stops the save ('' * 1).
     * Sets the debtor group's discount to 0, in every fiscal year, until tearDown().
     */
    private function giveTheDebtorGroupADiscount(): void
    {
        $this->groupDiscount = self::rows("SELECT g.id, g.box6 FROM grupper g, adresser a WHERE g.art = 'DG' AND cast(g.kodenr AS text) = cast(a.gruppe AS text) AND a.id = $1", [$this->debtor['id']]);
        $this->assertNotSame([], $this->groupDiscount, 'the debtor has a group');
        foreach ($this->groupDiscount as $group) {
            self::rows("UPDATE grupper SET box6 = '0' WHERE id = $1", [$group['id']]);
        }
    }

    /**
     * Starts a request to debitor/ordre.php?id= (or another copy of the page) in a child process and returns without
     * waiting for it.
     *
     * @param array<string, string> $post the form's fields, or [] for a GET
     * @param string $page the page, from the repository root
     * @return array{proc:resource, out:resource, err:resource, what:string}
     */
    private function startSalesOrder(int $orderId, array $post = [], string $session = CharacterizationEnv::SESSION_ID, string $page = 'debitor/ordre.php'): array
    {
        return $this->startPage("id=$orderId", $post, $session, $page);
    }

    /**
     * Starts a request to one of the pages run_debitor_order_page.php opens, in a child process, and returns without
     * waiting for it.
     *
     * @param string $query the page's query string
     * @param array<string, string> $post the form's fields, or [] for a GET
     * @param string $page the page, from the repository root
     * @return array{proc:resource, out:resource, err:resource, what:string}
     */
    private function startPage(string $query, array $post, string $session, string $page): array
    {
        $cmd = [PHP_BINARY, __DIR__ . '/../support/run_debitor_order_page.php', $query, $session, $post === [] ? '' : http_build_query($post), $page];
        $err = tmpfile();
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => $err], $pipes);
        $this->assertIsResource($proc, 'the child php starts');
        fclose($pipes[0]);
        return ['proc' => $proc, 'out' => $pipes[1], 'err' => $err, 'what' => ($post === [] ? 'GET' : 'POST') . " $page?$query"];
    }

    /**
     * Opens the line editor of subscription orders, debitor/ret_genfakt.php, for one order (?ordreliste=), or saves its
     * form, which posts to ?returside= as the form's action does.
     *
     * @param array<string, string> $post the form's fields, or [] for a GET
     * @return string the page's HTML
     */
    private function subscriptionOrder(int $orderId, array $post = [], string $session = CharacterizationEnv::SESSION_ID): string
    {
        return $this->finish($this->startSubscriptionOrder($orderId, $post, $session));
    }

    /**
     * Starts a request to debitor/ret_genfakt.php for one order, as subscriptionOrder() makes it, without waiting for it.
     *
     * @param array<string, string> $post the form's fields, or [] for a GET
     * @return array{proc:resource, out:resource, err:resource, what:string}
     */
    private function startSubscriptionOrder(int $orderId, array $post = [], string $session = CharacterizationEnv::SESSION_ID): array
    {
        return $this->startPage($post === [] ? "ordreliste=$orderId" : 'returside=ordreliste.php', $post, $session, 'debitor/ret_genfakt.php');
    }

    /**
     * Waits for a request startSalesOrder() began, and returns the page's HTML. The positions helper may raise no warning.
     *
     * @param array{proc:resource, out:resource, err:resource, what:string} $request
     * @param bool $pageMayDie the page may die on a bug of its own (finans/ordre.php drawing an order's lines), but not in the positions helper
     */
    private function finish(array $request, bool $pageMayDie = false): string
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
        if ($pageMayDie) {
            $this->assertStringNotContainsString('orderLinePositions.php', $stderr . $res['html'], "{$request['what']} did not die in the positions helper");
        } else {
            $this->assertStringNotContainsString('Fatal error', $stderr . $res['html'], "{$request['what']} crashed");
        }
        $own = array_values(array_filter($res['warnings'], static fn(string $warning): bool => str_contains($warning, 'orderLinePositions.php')));
        $this->assertSame([], $own, 'the positions helper raised no PHP warning');
        return $res['html'];
    }

    /**
     * A second user's session, B's, as the lab had: two requests of one session would wait for each other at
     * session_start(), so they could not save at the same moment.
     */
    private function secondSession(): string
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            CharacterizationEnv::rows($master, 'DELETE FROM online WHERE session_id = $1', [self::SESSION_B]);
            CharacterizationEnv::rows($master,
                'INSERT INTO online (session_id, brugernavn, db, dbuser, rettigheder, regnskabsaar, logtime, revisor, language_id)
                 SELECT $1, brugernavn, db, dbuser, rettigheder, regnskabsaar, $2, revisor, language_id FROM online WHERE session_id = $3',
                [self::SESSION_B, (string)time(), CharacterizationEnv::SESSION_ID]);
            $this->sessionB = true;
        } finally {
            pg_close($master);
        }
        return self::SESSION_B;
    }

    /**
     * Waits until each request startSalesOrder() began waits for a lock: the colleague's, or the other save's.
     *
     * @param list<array{proc:resource, out:resource, err:resource, what:string}> $requests
     */
    private function waitUntilAllWait(array $requests): void
    {
        $deadline = microtime(true) + 30;
        do {
            $waiting = self::one("SELECT count(*) AS n FROM pg_stat_activity WHERE datname = current_database() AND cardinality(pg_blocking_pids(pid)) > 0");
            if ((int)$waiting['n'] >= count($requests)) {
                return;
            }
            foreach ($requests as $request) {
                if (!proc_get_status($request['proc'])['running']) {
                    $this->fail("{$request['what']} ended before the colleague let go of the order's lines");
                }
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $this->fail('the saves did not both wait within 30 s');
    }

    /**
     * Opens or saves a purchase order: a GET of kreditor/ordre.php?id=, or a POST of its form.
     *
     * @param array<string, string> $post the form's fields, or [] for a GET
     * @return string the page's HTML
     */
    private function purchaseOrder(int $orderId, array $post = []): string
    {
        $res = $this->runChildJson('run_kreditor_order_page.php',
            ['ordre.php', $post === [] ? "id=$orderId" : '', http_build_query($post), CharacterizationEnv::SESSION_ID]);
        $own = array_values(array_filter($res['warnings'], static fn(string $warning): bool => str_contains($warning, 'orderLinePositions.php')));
        $this->assertSame([], $own, 'the positions helper raised no PHP warning');
        return $res['html'];
    }

    /**
     * The fields a browser posts for the form named ordre (or $formName) as it was drawn: every named, enabled input
     * except the buttons and unticked boxes (the last of two inputs with one name wins, as in PHP), the selected (or
     * first) option of each select, and each textarea.
     *
     * @return array<string, string>
     */
    private static function formFields(string $html, string $formName = 'ordre'): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);
        $form = $xpath->query("//form[@name='$formName']")->item(0);
        self::assertNotNull($form, "the page draws the form $formName");
        $fields = [];
        foreach ($xpath->query('.//input', $form) as $input) {
            $name = $input->getAttribute('name');
            $type = strtolower($input->getAttribute('type'));
            if ($name === '' || $input->hasAttribute('disabled') || in_array($type, ['submit', 'button', 'image', 'reset'], true)) {
                continue;
            }
            if (in_array($type, ['checkbox', 'radio'], true) && !$input->hasAttribute('checked')) {
                continue;
            }
            $fields[$name] = $input->getAttribute('value');
        }
        foreach ($xpath->query('.//select', $form) as $select) {
            $name = $select->getAttribute('name');
            if ($name === '' || $select->hasAttribute('disabled')) {
                continue;
            }
            $chosen = null;
            foreach ($select->getElementsByTagName('option') as $option) {
                $value = $option->hasAttribute('value') ? $option->getAttribute('value') : $option->textContent;
                if ($chosen === null || $option->hasAttribute('selected')) {
                    $chosen = $value;
                }
                if ($option->hasAttribute('selected')) {
                    break;
                }
            }
            $fields[$name] = (string)$chosen;
        }
        foreach ($xpath->query('.//textarea', $form) as $textarea) {
            if ($textarea->getAttribute('name') !== '' && !$textarea->hasAttribute('disabled')) {
                $fields[$textarea->getAttribute('name')] = $textarea->textContent;
            }
        }
        return $fields;
    }

    /**
     * A form as it was drawn, with an item typed into the new line and Gem pressed.
     *
     * @param array<string, string> $form formFields()
     * @param array<string, string> $more other fields the user changed, e.g. a line's Pos
     * @param string $quantityField the new line's Antal: dkan0 on the sales order, anta0 on the purchase order
     * @return array<string, string>
     */
    private static function addLine(array $form, string $varenr, string $antal, array $more = [], string $quantityField = 'dkan0'): array
    {
        $form['vare0'] = $varenr;
        $form[$quantityField] = $antal;
        $form['save'] = 'Gem';
        return $more + $form;
    }

    /** The form's line number x of the line with this id: the form posts it as linje_id[x]. */
    private static function formLine(array $form, int $lineId): string
    {
        foreach ($form as $name => $value) {
            if (preg_match('/^linje_id\[(\d+)\]$/', $name, $m) && (int)$value === $lineId) {
                return $m[1];
            }
        }
        self::fail("the form shows line $lineId");
    }

    /** @return list<array{id:string, varenr:string, antal:string, posnr:string}> the order's lines in id order */
    private static function lines(int $orderId): array
    {
        return self::rows('SELECT id, varenr, antal, posnr FROM ordrelinjer WHERE ordre_id = $1 AND posnr > 0 ORDER BY id', [$orderId]);
    }

    /**
     * The lines as the order shows them (by posnr), as "varenr antal", and that no two share a position.
     *
     * @return list<string>
     */
    private function shownInOrder(int $orderId): array
    {
        $lines = self::lines($orderId);
        $positions = array_map(static fn(array $line): int => (int)$line['posnr'], $lines);
        $this->assertSame(count($positions), count(array_unique($positions)),
            'every line has a position of its own: ' . json_encode(array_map(static fn(array $line): string => "$line[id]@$line[posnr]", $lines)));
        usort($lines, static fn(array $a, array $b): int => [(int)$a['posnr'], (int)$a['id']] <=> [(int)$b['posnr'], (int)$b['id']]);
        return array_map(static fn(array $line): string => $line['varenr'] . ' ' . (float)$line['antal'], $lines);
    }

    /**
     * The text lines as finans/ordre.php shows them (by posnr), and that no two share a position.
     *
     * @return list<string> beskrivelse
     */
    private function textsInOrder(int $orderId): array
    {
        $lines = self::rows('SELECT id, beskrivelse, posnr FROM ordrelinjer WHERE ordre_id = $1 AND posnr > 0 ORDER BY posnr, id', [$orderId]);
        $positions = array_map(static fn(array $line): int => (int)$line['posnr'], $lines);
        $this->assertSame(count($positions), count(array_unique($positions)),
            'every line has a position of its own: ' . json_encode(array_map(static fn(array $line): string => "$line[beskrivelse]@$line[posnr]", $lines)));
        return array_column($lines, 'beskrivelse');
    }

    /**
     * A finans/ordre.php form as it was drawn, with a text typed into the new line's Beskrivelse and Gem pressed.
     *
     * @param array<string, string> $form formFields()
     * @return array<string, string>
     */
    private static function addText(array $form, string $text): array
    {
        $form['beskrivelse0'] = $text;
        $form['submit'] = 'Gem';
        return $form;
    }

    /**
     * The line number y of the new line in a debitor/ret_genfakt.php form of one order: it posts an ordre_id[y] and no
     * linje_id[y], and its fields are posnr_y, beskrivelse_y and so on.
     *
     * @param array<string, string> $form formFields()
     */
    private static function newSubscriptionLine(array $form): string
    {
        foreach ($form as $name => $value) {
            if (preg_match('/^ordre_id\[(\d+)\]$/', $name, $m) && !isset($form["linje_id[$m[1]]"])) {
                return $m[1];
            }
        }
        self::fail('the form has a new line');
    }

    /**
     * A debitor/ret_genfakt.php form as it was drawn, with a text typed into the new line's Beskrivelse and Gem pressed.
     *
     * @param array<string, string> $form formFields()
     * @return array<string, string>
     */
    private static function addSubscriptionText(array $form, string $text): array
    {
        $form['beskrivelse_' . self::newSubscriptionLine($form)] = $text;
        $form['submit'] = 'Gem';
        return $form;
    }

    /**
     * Makes ordrelinjer.posnr smallint, as regnskaber from Saldi's first versions have it (includes/opdat_0.php), until
     * tearDown().
     */
    private function makePosnrSmallint(): void
    {
        self::rows('ALTER TABLE ordrelinjer ALTER COLUMN posnr TYPE smallint');
        $this->smallintPosnr = true;
    }

    public function test_two_users_adding_lines_to_one_sales_order_give_every_line_its_own_position(): void
    {
        $orderId = self::$fx->order($this->debtor, [], ['status' => 0])['id'];
        $a = self::formFields($this->salesOrder($orderId));
        $b = self::formFields($this->salesOrder($orderId));

        // Round 1: A saves an item, then B saves another from the form drawn before A's save.
        $this->salesOrder($orderId, self::addLine($a, $this->first['varenr'], '3,25'));
        $a = self::formFields($this->salesOrder($orderId));
        $this->salesOrder($orderId, self::addLine($b, $this->second['varenr'], '2,4'));
        $b = self::formFields($this->salesOrder($orderId));
        $this->assertSame([$this->first['varenr'] . ' 3.25', $this->second['varenr'] . ' 2.4'], $this->shownInOrder($orderId),
            "B's line goes after A's, which its form did not show");

        // Round 2: both add a line from the page their own save drew.
        $this->salesOrder($orderId, self::addLine($a, $this->first['varenr'], '1,75'));
        $this->salesOrder($orderId, self::addLine($b, $this->second['varenr'], '0,65'));
        $this->assertSame([
            $this->first['varenr'] . ' 3.25',
            $this->second['varenr'] . ' 2.4',
            $this->first['varenr'] . ' 1.75',
            $this->second['varenr'] . ' 0.65',
        ], $this->shownInOrder($orderId), 'the lines show in the order they were added');
    }

    public function test_a_stale_sales_order_form_does_not_move_its_line_onto_a_colleagues_line(): void
    {
        $orderId = self::$fx->order($this->debtor, [['item' => $this->first, 'antal' => 1]], ['status' => 0])['id'];
        $a = self::formFields($this->salesOrder($orderId));
        $b = self::formFields($this->salesOrder($orderId));

        // A adds a line and then moves it first: Pos 1 for the new line, Pos 2 for the old one.
        $this->salesOrder($orderId, self::addLine($a, $this->second['varenr'], '5'));
        $a = self::formFields($this->salesOrder($orderId));
        $this->assertSame('2', $a['posn2'], "A's new line shows second");
        $a['posn1'] = '2';
        $a['posn2'] = '1';
        $a['save'] = 'Gem';
        $this->salesOrder($orderId, $a);
        $this->assertSame([$this->second['varenr'] . ' 5', $this->first['varenr'] . ' 1'], $this->shownInOrder($orderId));

        // B, from the form drawn before all that, adds a line: its save writes the old line back to Pos 1.
        $this->salesOrder($orderId, self::addLine($b, $this->second['varenr'], '7'));
        $this->assertSame([
            $this->first['varenr'] . ' 1',
            $this->second['varenr'] . ' 5',
            $this->second['varenr'] . ' 7',
        ], $this->shownInOrder($orderId), "A's line stays next to the line B's form put on its position");
    }

    public function test_one_user_saving_lines_keeps_the_positions_the_form_gives(): void
    {
        $orderId = self::$fx->order($this->debtor, [], ['status' => 0])['id'];

        $this->salesOrder($orderId, self::addLine(self::formFields($this->salesOrder($orderId)), $this->first['varenr'], '1'));
        $this->salesOrder($orderId, self::addLine(self::formFields($this->salesOrder($orderId)), $this->second['varenr'], '2'));
        $form = self::formFields($this->salesOrder($orderId));
        $this->assertSame('3', $form['posn0'], 'the new line is offered Pos 3');
        // Pos 1,5 puts the third line between the two.
        $this->salesOrder($orderId, self::addLine($form, $this->first['varenr'], '3', ['posn0' => '1,5']));

        $this->assertSame(['100', '200', '150'], array_column(self::lines($orderId), 'posnr'), 'the positions are the ones the forms gave');
        $this->assertSame([$this->first['varenr'] . ' 1', $this->first['varenr'] . ' 3', $this->second['varenr'] . ' 2'], $this->shownInOrder($orderId));
    }

    public function test_two_users_adding_lines_to_one_purchase_order_give_every_line_its_own_position(): void
    {
        $orderId = self::$fx->creditorOrder($this->creditor, [], ['status' => 0])['id'];
        $a = self::formFields($this->purchaseOrder($orderId));
        $b = self::formFields($this->purchaseOrder($orderId));

        $this->purchaseOrder($orderId, self::addLine($a, $this->first['varenr'], '3,25', [], 'anta0'));
        $a = self::formFields($this->purchaseOrder($orderId));
        $this->purchaseOrder($orderId, self::addLine($b, $this->second['varenr'], '2,4', [], 'anta0'));
        $b = self::formFields($this->purchaseOrder($orderId));
        $this->assertSame([$this->first['varenr'] . ' 3.25', $this->second['varenr'] . ' 2.4'], $this->shownInOrder($orderId),
            "B's line goes after A's, which its form did not show");

        $this->purchaseOrder($orderId, self::addLine($a, $this->first['varenr'], '1,75', [], 'anta0'));
        $this->purchaseOrder($orderId, self::addLine($b, $this->second['varenr'], '0,65', [], 'anta0'));
        $this->assertSame([
            $this->first['varenr'] . ' 3.25',
            $this->second['varenr'] . ' 2.4',
            $this->first['varenr'] . ' 1.75',
            $this->second['varenr'] . ' 0.65',
        ], $this->shownInOrder($orderId), 'the lines show in the order they were added');
    }

    public function test_two_sales_order_saves_at_the_same_moment_give_their_lines_positions_of_their_own(): void
    {
        $orderId = self::$fx->order($this->debtor, [], ['status' => 0])['id'];
        $sessionB = $this->secondSession();
        $a = self::formFields($this->salesOrder($orderId));
        $b = self::formFields($this->salesOrder($orderId, [], $sessionB));

        // The colleague holds the order's lines: a save stops at its first line write, after it read the order's lines.
        $colleague = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        try {
            pg_query($colleague, 'BEGIN');
            pg_query($colleague, 'LOCK TABLE ordrelinjer IN EXCLUSIVE MODE');
            $saveA = $this->startSalesOrder($orderId, self::addLine($a, $this->first['varenr'], '3,25'));
            $saveB = $this->startSalesOrder($orderId, self::addLine($b, $this->second['varenr'], '2,4'), $sessionB);
            $this->waitUntilAllWait([$saveA, $saveB]);
            pg_query($colleague, 'COMMIT');
            $this->finish($saveA);
            $this->finish($saveB);
        } finally {
            pg_close($colleague);
        }

        $lines = self::lines($orderId);
        $positions = array_column($lines, 'posnr');
        sort($positions);
        $this->assertSame(['100', '200'], $positions, 'the save that waited put its line after the other one: ' . json_encode($lines));
        $shown = $this->shownInOrder($orderId);
        sort($shown);
        $expected = [$this->first['varenr'] . ' 3.25', $this->second['varenr'] . ' 2.4'];
        sort($expected);
        $this->assertSame($expected, $shown, 'both lines are saved');
    }

    public function test_a_stale_form_on_an_order_with_a_set_leaves_every_row_on_a_position_of_its_own(): void
    {
        // Vis sæt (POS 2, box12): the page numbers each set's rows from the set's first row, + 1 each.
        $setting = self::one("INSERT INTO grupper (beskrivelse, art, kodenr, fiscal_year, box12) VALUES ('chartest Vis saet', 'POS', '2', $1, 'on') RETURNING id", [self::$regnaar]);
        $this->showSets = (int)$setting['id'];
        $master = self::$fx->item(['salgspris' => 0]);
        $this->items[] = $master;
        $third = self::$fx->item(['salgspris' => 59.20]);
        $this->items[] = $third;
        $orderId = self::$fx->order($this->debtor, [], ['status' => 0])['id'];
        // A set of two items: their rows and the set's own row (samlevare 'on'), as opret_saet() makes them.
        foreach ([[$this->first, '', 100, 59.20], [$this->second, '', 101, 59.20], [$master, 'on', 102, 0]] as [$item, $samlevare, $posnr, $pris]) {
            self::rows("INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, beskrivelse, enhed, posnr, antal, pris, kostpris, rabat, momssats, procent, leveres, leveret, saet, samlevare)
                        VALUES ($1, $2, $3, 'chartest saet', 'stk', $4, 1, $5, 0, 0, 25, 100, 0, 0, 1, $6)",
                [$orderId, $item['id'], $item['varenr'], $posnr, $pris, $samlevare]);
        }
        $b = self::formFields($this->salesOrder($orderId));

        // A adds a line, then puts it first: Pos 1 for it, 2 and 3 for the set's rows.
        $this->salesOrder($orderId, self::addLine(self::formFields($this->salesOrder($orderId)), $third['varenr'], '1'));
        $a = self::formFields($this->salesOrder($orderId));
        $added = self::one('SELECT id FROM ordrelinjer WHERE ordre_id = $1 AND vare_id = $2', [$orderId, $third['id']]);
        $setRows = self::rows("SELECT id FROM ordrelinjer WHERE ordre_id = $1 AND saet = 1 AND samlevare = '' ORDER BY id", [$orderId]);
        $a['posn' . self::formLine($a, (int)$added['id'])] = '1';
        $a['posn' . self::formLine($a, (int)$setRows[0]['id'])] = '2';
        $a['posn' . self::formLine($a, (int)$setRows[1]['id'])] = '3';
        $a['save'] = 'Gem';
        $this->salesOrder($orderId, $a);
        $this->assertSame([$third['varenr'] . ' 1', $this->first['varenr'] . ' 1', $this->second['varenr'] . ' 1', $master['varenr'] . ' 1'],
            $this->shownInOrder($orderId), "A's line is first, the set after it");

        // B saves the form drawn before all that: it writes the set's rows to Pos 1 and 2.
        $b['save'] = 'Gem';
        $this->salesOrder($orderId, $b);
        $this->shownInOrder($orderId);
        // Opened again, the page numbers the set's rows once more.
        $this->salesOrder($orderId);
        $this->assertSame([$this->first['varenr'] . ' 1', $this->second['varenr'] . ' 1', $master['varenr'] . ' 1', $third['varenr'] . ' 1'],
            $this->shownInOrder($orderId), "the set's rows are where B's form put them, and A's line after them");
    }

    public function test_a_line_moved_into_a_purchase_order_after_its_form_was_drawn_keeps_a_position_of_its_own(): void
    {
        // The order the line comes from is older than the one it goes to, so its line has the lower id.
        $from = self::$fx->creditorOrder($this->creditor, [['item' => $this->first, 'antal' => 4], ['item' => $this->second, 'antal' => 5]], ['status' => 0])['id'];
        $this->purchaseOrder($from, ['save' => 'Gem'] + self::formFields($this->purchaseOrder($from)));
        $moved = self::one('SELECT id, posnr FROM ordrelinjer WHERE ordre_id = $1 AND vare_id = $2', [$from, $this->second['id']]);
        $this->assertSame('200', $moved['posnr'], 'the line is second in its order');
        $to = self::$fx->creditorOrder($this->creditor, array_fill(0, 11, ['item' => $this->first, 'antal' => 1]), ['status' => 0])['id'];
        $b = self::formFields($this->purchaseOrder($to));
        $this->assertSame('12', $b['posn0'], "B's form offers Pos 12 for a new line");

        // A moves the line, all 5 of it, from its order to B's: Del ordre, then Flyt.
        $split = self::formFields($this->purchaseOrder($from, ['split' => 'Del ordre'] + self::formFields($this->purchaseOrder($from))));
        $split['mQt[' . self::formLine($split, (int)$moved['id']) . ']'] = '5';
        $split['MoveItemsTo'] = (string)$to;
        $split['moveOrderLines'] = 'Flyt';
        $this->purchaseOrder($from, $split);
        $this->assertSame(['id' => $moved['id'], 'posnr' => '1200'], self::one('SELECT id, posnr FROM ordrelinjer WHERE ordre_id = $1 AND vare_id = $2', [$to, $this->second['id']]),
            'the line keeps its id and goes to position 1200 in B\'s order');

        // B adds a line from the form drawn before the move.
        $this->purchaseOrder($to, self::addLine($b, $this->first['varenr'], '7', [], 'anta0'));
        $this->assertSame(array_merge(array_fill(0, 11, $this->first['varenr'] . ' 1'), [$this->second['varenr'] . ' 5', $this->first['varenr'] . ' 7']),
            $this->shownInOrder($to), "B's new line goes after the line moved in");
    }

    public function test_forms_of_an_empty_order_on_finans_ordre_give_their_lines_positions_of_their_own(): void
    {
        $this->giveTheDebtorGroupADiscount();
        $orderId = self::$fx->order($this->debtor, [], ['status' => 0])['id'];
        $a = self::formFields($this->financeOrder($orderId));
        $b = self::formFields($this->financeOrder($orderId));
        $c = self::formFields($this->financeOrder($orderId));
        $this->assertSame(['1', '1', '1'], [$a['posn0'], $b['posn0'], $c['posn0']], 'every form offers Pos 1 for a new line');

        // A, B and C save a text line each, from the forms drawn before any of them saved.
        $this->financeOrder($orderId, self::addText($a, 'A'));
        $this->financeOrder($orderId, self::addText($b, 'B'));
        $this->financeOrder($orderId, self::addText($c, 'C'));
        $this->assertSame(['A', 'B', 'C'], $this->textsInOrder($orderId), 'each line goes after the lines its form did not show');
        $this->assertSame(['1', '2', '3'], array_column(self::rows('SELECT posnr FROM ordrelinjer WHERE ordre_id = $1 ORDER BY posnr', [$orderId]), 'posnr'),
            'the positions follow on from the ones the page gives, Pos 1, 2, 3');
    }

    public function test_two_saves_on_finans_ordre_at_the_same_moment_give_their_lines_positions_of_their_own(): void
    {
        $this->giveTheDebtorGroupADiscount();
        $orderId = self::$fx->order($this->debtor, [], ['status' => 0])['id'];
        $sessionB = $this->secondSession();
        $a = self::formFields($this->financeOrder($orderId));
        $b = self::formFields($this->financeOrder($orderId, [], $sessionB));

        // The colleague holds the order's lines: a save stops at its first line write.
        $colleague = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        try {
            pg_query($colleague, 'BEGIN');
            pg_query($colleague, 'LOCK TABLE ordrelinjer IN EXCLUSIVE MODE');
            $saveA = $this->startSalesOrder($orderId, self::addText($a, 'A'), CharacterizationEnv::SESSION_ID, 'finans/ordre.php');
            $saveB = $this->startSalesOrder($orderId, self::addText($b, 'B'), $sessionB, 'finans/ordre.php');
            $this->waitUntilAllWait([$saveA, $saveB]);
            pg_query($colleague, 'COMMIT');
            $this->finish($saveA, true);
            $this->finish($saveB, true);
        } finally {
            pg_close($colleague);
        }

        $lines = self::rows('SELECT beskrivelse, posnr FROM ordrelinjer WHERE ordre_id = $1 ORDER BY posnr', [$orderId]);
        $this->assertSame(['1', '2'], array_column($lines, 'posnr'), 'the save that waited put its line after the other one: ' . json_encode($lines));
        $texts = array_column($lines, 'beskrivelse');
        sort($texts);
        $this->assertSame(['A', 'B'], $texts, 'both lines are saved');
    }

    public function test_forms_of_an_empty_subscription_order_give_their_lines_positions_of_their_own(): void
    {
        $orderId = self::$fx->order($this->debtor, [], ['status' => 0])['id'];
        $a = self::formFields($this->subscriptionOrder($orderId), 'ret_genfakt');
        $b = self::formFields($this->subscriptionOrder($orderId), 'ret_genfakt');
        $c = self::formFields($this->subscriptionOrder($orderId), 'ret_genfakt');
        $this->assertSame(['1', '1', '1'], array_map(static fn(array $form): string => $form['posnr_' . self::newSubscriptionLine($form)], [$a, $b, $c]),
            'every form offers Pos 1 for a new line');

        // A, B and C save a text line each, from the forms drawn before any of them saved.
        $this->subscriptionOrder($orderId, self::addSubscriptionText($a, 'A'));
        $this->subscriptionOrder($orderId, self::addSubscriptionText($b, 'B'));
        $this->subscriptionOrder($orderId, self::addSubscriptionText($c, 'C'));
        $this->assertSame(['A', 'B', 'C'], $this->textsInOrder($orderId), 'each line goes after the lines its form did not show');
        $this->assertSame(['1', '2', '3'], array_column(self::rows('SELECT posnr FROM ordrelinjer WHERE ordre_id = $1 ORDER BY posnr', [$orderId]), 'posnr'),
            'the positions follow on from the ones the page gives, Pos 1, 2, 3');

        // A form drawn now shows the three lines, and its save keeps them where they are.
        $this->subscriptionOrder($orderId, self::addSubscriptionText(self::formFields($this->subscriptionOrder($orderId), 'ret_genfakt'), 'D'));
        $this->assertSame(['A', 'B', 'C', 'D'], $this->textsInOrder($orderId), "one user's save puts its line at the Pos the form offered");
    }

    public function test_two_saves_on_ret_genfakt_at_the_same_moment_give_their_lines_positions_of_their_own(): void
    {
        $orderId = self::$fx->order($this->debtor, [], ['status' => 0])['id'];
        $sessionB = $this->secondSession();
        $a = self::formFields($this->subscriptionOrder($orderId), 'ret_genfakt');
        $b = self::formFields($this->subscriptionOrder($orderId, [], $sessionB), 'ret_genfakt');

        // The colleague holds the order's lines: a save stops at its first line write.
        $colleague = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        try {
            pg_query($colleague, 'BEGIN');
            pg_query($colleague, 'LOCK TABLE ordrelinjer IN EXCLUSIVE MODE');
            $saveA = $this->startSubscriptionOrder($orderId, self::addSubscriptionText($a, 'A'));
            $saveB = $this->startSubscriptionOrder($orderId, self::addSubscriptionText($b, 'B'), $sessionB);
            $this->waitUntilAllWait([$saveA, $saveB]);
            pg_query($colleague, 'COMMIT');
            $this->finish($saveA);
            $this->finish($saveB);
        } finally {
            pg_close($colleague);
        }

        $lines = self::rows('SELECT beskrivelse, posnr FROM ordrelinjer WHERE ordre_id = $1 ORDER BY posnr', [$orderId]);
        $this->assertSame(['1', '2'], array_column($lines, 'posnr'), 'the save that waited put its line after the other one: ' . json_encode($lines));
        $texts = array_column($lines, 'beskrivelse');
        sort($texts);
        $this->assertSame(['A', 'B'], $texts, 'both lines are saved');
    }

    public function test_a_stale_form_keeps_its_line_within_a_smallint_posnr_column(): void
    {
        $orderId = self::$fx->order($this->debtor, [], ['status' => 0])['id'];
        $this->makePosnrSmallint();
        $a = self::formFields($this->salesOrder($orderId));
        $b = self::formFields($this->salesOrder($orderId));
        $c = self::formFields($this->salesOrder($orderId));
        $d = self::formFields($this->salesOrder($orderId));

        // A puts its line at Pos 327, posnr 32700. B adds one at the Pos 1 its form offers: after A's would be 32800.
        $this->salesOrder($orderId, self::addLine($a, $this->first['varenr'], '1', ['posn0' => '327']));
        $this->salesOrder($orderId, self::addLine($b, $this->second['varenr'], '2'));
        $this->assertSame(['32700', '32767'], array_column(self::lines($orderId), 'posnr'),
            "B's line goes after A's, on the highest posnr the column holds");

        // C adds one at Pos 1 as well. Nothing fits after B's line, so it keeps the position its form gave it.
        $this->salesOrder($orderId, self::addLine($c, $this->first['varenr'], '3'));
        $this->assertSame(['32700', '32767', '100'], array_column(self::lines($orderId), 'posnr'), "C's line stays at Pos 1");

        // D puts one on B's position, Pos 327,67. B's line has nowhere to go after it, so it goes to the first free posnr.
        $this->salesOrder($orderId, self::addLine($d, $this->second['varenr'], '4', ['posn0' => '327,67']));
        $this->assertSame(['32700', '1', '100', '32767'], array_column(self::lines($orderId), 'posnr'), "B's line moves to posnr 1");
        $this->assertSame([
            $this->second['varenr'] . ' 2',
            $this->first['varenr'] . ' 3',
            $this->first['varenr'] . ' 1',
            $this->second['varenr'] . ' 4',
        ], $this->shownInOrder($orderId), 'every line is saved, on a position of its own');
    }
}
