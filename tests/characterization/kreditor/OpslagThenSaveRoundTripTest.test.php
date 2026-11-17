<?php
// tests/characterization/kreditor/OpslagThenSaveRoundTripTest.test.php
//
// SST-1024: an item added to an approved purchase order (KO) or credit note (KK), then the next «Gem», end to end.
//
// Saul's review of PR #1135 found that the unit tests ran the new-line helpers and the inserts, but not the save after
// them, where the bug showed. After «Opslag» (ordre.php?vare_id=…) the page shows the new line's «Antal» empty with the
// quantity as placeholder. «Gem», or Enter in any field, then posted Antal "" with «Modtag» 1: the line was stored as
// 0 / 0 with the alert «returnér ændret fra -1 til 0!», and Antal 5 kept «Modtag» 1.
//
// Each test drives kreditor/ordre.php in a child process (support/run_kreditor_ordre_page.php) against its own tenant:
// the request that adds the line, then the form of the page it rendered, filled and submitted the way a browser does
// (every successful control plus the first submit button, which is also what Enter submits), and reads the line back.
//
// Requires the test PostgreSQL server - skips cleanly otherwise.
//
// History:
// 20261117 CL/MMR SST-1024 Created for Saul's review: Opslag then Gem/Enter on KO and KK, Antal empty or typed, status 0/1/2, and the typed item.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class OpslagThenSaveRoundTripTest extends CharacterizationTestCase
{
    private static int $request = 0;

    /**
     * One request to kreditor/ordre.php; returns the page it rendered.
     *
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     */
    private static function page(array $get, array $post = []): string
    {
        $dir = sys_get_temp_dir() . '/chartest-sst1024-' . getmypid();
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $n = ++self::$request;
        $requestFile = "$dir/request$n.json";
        $pageFile = "$dir/page$n.html";
        file_put_contents($requestFile, json_encode(['get' => $get, 'post' => $post]));
        $res = self::runChild('run_kreditor_ordre_page.php', [$requestFile, CharacterizationEnv::sessionId(), $pageFile]);
        self::assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], "the page did not finish.\nstderr: {$res['stderr']}");
        $page = (string)file_get_contents($pageFile);
        unlink($requestFile);
        unlink($pageFile);
        @rmdir($dir);
        return $page;
    }

    /**
     * The order form of a rendered page as a browser submits it: every successful control in document order, plus the
     * first submit button (implicit submission, i.e. Enter, uses that one too). $set overrides typed values.
     *
     * @param array<string, string> $set
     * @return array<string, mixed> the POST array PHP builds from it
     */
    private static function submitOrderForm(string $page, array $set = []): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $page);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        $form = $xpath->query("//form[@name='ordre']")->item(0);
        self::assertNotNull($form, 'the page has no order form');

        $pairs = [];
        $submitter = false;
        foreach ($xpath->query('.//input | .//select | .//textarea', $form) as $el) {
            /** @var DOMElement $el */
            $name = $el->getAttribute('name');
            if ($name === '' || $el->hasAttribute('disabled')) {
                continue;
            }
            $tag = strtolower($el->tagName);
            if ($tag === 'select') {
                $options = $xpath->query('.//option', $el);
                $chosen = null;
                foreach ($options as $option) {
                    if ($option->hasAttribute('selected')) {
                        $chosen = $option;
                    }
                }
                $chosen = $chosen ?? $options->item(0);
                if ($chosen !== null) {
                    $pairs[] = [$name, $chosen->hasAttribute('value') ? $chosen->getAttribute('value') : trim($chosen->textContent)];
                }
                continue;
            }
            if ($tag === 'textarea') {
                $pairs[] = [$name, $set[$name] ?? $el->textContent];
                continue;
            }
            $type = strtolower($el->getAttribute('type') ?: 'text');
            if ($type === 'submit') {
                if (!$submitter) {
                    $pairs[] = [$name, $el->getAttribute('value')];
                    $submitter = true;
                }
                continue;
            }
            if (in_array($type, ['button', 'reset', 'image', 'file'], true)) {
                continue;
            }
            if ($type === 'checkbox' || $type === 'radio') {
                if (array_key_exists($name, $set) ? $set[$name] !== '' : $el->hasAttribute('checked')) {
                    $pairs[] = [$name, $el->hasAttribute('value') ? $el->getAttribute('value') : 'on'];
                }
                continue;
            }
            $pairs[] = [$name, $set[$name] ?? $el->getAttribute('value')];
        }
        self::assertTrue($submitter, 'the order form has no submit button');
        $query = implode('&', array_map(static fn($p) => urlencode($p[0]) . '=' . urlencode((string)$p[1]), $pairs));
        parse_str($query, $post);
        return $post;
    }

    /** The value and placeholder of a named input on a rendered page, null when it is not there. */
    private static function input(string $page, string $name): ?array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $page);
        libxml_clear_errors();
        $el = (new DOMXPath($dom))->query("//input[@name='$name']")->item(0);
        return $el === null ? null : ['value' => $el->getAttribute('value'), 'placeholder' => $el->getAttribute('placeholder')];
    }

    /** @return array{antal: float, leveres: ?float} the stored line of an item on an order */
    private static function line(int $orderId, array $item): array
    {
        $r = self::one('SELECT antal, leveres FROM ordrelinjer WHERE ordre_id = $1 AND vare_id = $2', [$orderId, $item['id']]);
        self::assertNotNull($r, "no line for item {$item['varenr']} on order $orderId");
        return ['antal' => (float)$r['antal'], 'leveres' => $r['leveres'] === null ? null : (float)$r['leveres']];
    }

    /**
     * An order with one item line already on it, as approval leaves it (leveres = antal), and a second item to add.
     *
     * @return array{order: int, first: array, added: array}
     */
    private static function orderWithOneLine(string $art, int $status, float $antal = 1): array
    {
        $creditor = self::$fx->creditor();
        $first = self::$fx->item();
        $added = self::$fx->item();
        $sign = $art === 'KK' ? -1 : 1;
        $order = self::$fx->creditorOrder($creditor, [['item' => $first, 'antal' => $sign * $antal, 'pris' => 60.00]], ['art' => $art, 'status' => $status]);
        if ($status >= 1) {
            self::rows('UPDATE ordrelinjer SET leveres = antal WHERE ordre_id = $1', [$order['id']]);
        }
        return ['order' => $order['id'], 'first' => $first, 'added' => $added];
    }

    /** «Opslag»: the item is picked in the lookup, which opens ordre.php?vare_id=… */
    private static function opslag(int $orderId, array $item): string
    {
        return self::page(['id' => $orderId, 'vare_id' => $item['id'], 'fokus' => 'vare0']);
    }

    private static function assertNoQuantityAlert(string $page): void
    {
        self::assertStringNotContainsString('return&eacute;r &aelig;ndret', $page, 'the save changed «Returner» with an alert');
        self::assertStringNotContainsString('modtag &aelig;ndret', $page, 'the save changed «Modtag» with an alert');
        self::assertStringNotContainsString('antal &aelig;ndret', $page, 'the save changed «Antal» with an alert');
    }

    /** @return array<string, array{string, string, ?string, float, float}> art, typed Antal (null = left empty), typed Modtag, stored antal, stored leveres */
    public static function approvedCases(): array
    {
        return [
            'KO, Opslag, Gem without typing'           => ['KO', null, null, 1.0, 1.0],
            'KO, Opslag, type 5 in Antal, Gem'         => ['KO', '5', null, 5.0, 5.0],
            'KO, Opslag, Antal 5 and Modtag 2, Gem'    => ['KO', '5', '2', 5.0, 2.0],
            'KK, Opslag, Gem without typing'           => ['KK', null, null, -1.0, -1.0],
            'KK, Opslag, type 5 in Antal, Gem'         => ['KK', '5', null, -5.0, -5.0],
        ];
    }

    /** The line Opslag adds on an approved order keeps its quantity on the next Gem, and «Modtag» follows «Antal». */
    #[\PHPUnit\Framework\Attributes\DataProvider('approvedCases')]
    public function test_an_opslag_line_on_an_approved_order_survives_the_next_save(string $art, ?string $antal, ?string $modtag, float $storedAntal, float $storedLeveres): void
    {
        $o = self::orderWithOneLine($art, 1);
        $page = self::opslag($o['order'], $o['added']);

        $this->assertSame(['antal' => $art === 'KK' ? -1.0 : 1.0, 'leveres' => $art === 'KK' ? -1.0 : 1.0], self::line($o['order'], $o['added']), 'Opslag adds the line ready to receive');
        $this->assertSame(['value' => '', 'placeholder' => '1'], self::input($page, 'anta2'), 'Antal is shown empty with the quantity as placeholder');
        $this->assertSame('1', self::input($page, 'leve2')['value'] ?? null, '«Modtag»/«Returner» shows 1');

        $set = [];
        if ($antal !== null) {
            $set['anta2'] = $antal;
        }
        if ($modtag !== null) {
            $set['leve2'] = $modtag;
        }
        $saved = self::page([], self::submitOrderForm($page, $set));

        $this->assertNoQuantityAlert($saved);
        $this->assertSame(['antal' => $storedAntal, 'leveres' => $storedLeveres], self::line($o['order'], $o['added']));
        $first = $art === 'KK' ? -1.0 : 1.0;
        $this->assertSame(['antal' => $first, 'leveres' => $first], self::line($o['order'], $o['first']), 'the line already on the order is untouched');
    }

    /**
     * A partly received order (status 2): one of two pieces received on the first line, the second line received in
     * full, then «Gem», which stores status 2. The added line keeps its quantity and the received lines are untouched.
     */
    public function test_an_opslag_line_on_a_partly_received_order_survives_the_next_save(): void
    {
        $creditor = self::$fx->creditor();
        $partly = self::$fx->item();
        $received = self::$fx->item();
        $added = self::$fx->item();
        $order = self::$fx->creditorOrder($creditor, [
            ['item' => $partly, 'antal' => 2, 'pris' => 60.00],
            ['item' => $received, 'antal' => 1, 'pris' => 60.00],
        ], ['status' => 1])['id'];
        self::rows('UPDATE ordrelinjer SET leveres = 1 WHERE ordre_id = $1', [$order]);
        self::runChild('run_modtag_page.php', [$order, CharacterizationEnv::sessionId()]);
        self::page([], self::submitOrderForm(self::page(['id' => $order])));
        $this->assertSame('2', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$order])['status'], 'precondition: status 2');
        $before = self::rows('SELECT id, antal, leveres, leveret FROM ordrelinjer WHERE ordre_id = $1 ORDER BY id', [$order]);
        $this->assertSame('1.000', $before[0]['leveret'], 'precondition: one of two received on the first line');

        $page = self::opslag($order, $added);
        $this->assertSame('1', self::input($page, 'leve3')['value'] ?? null);
        $saved = self::page([], self::submitOrderForm($page));
        $this->assertNoQuantityAlert($saved);
        $this->assertSame(['antal' => 1.0, 'leveres' => 1.0], self::line($order, $added));

        $page = self::opslag($order, self::$fx->item());
        self::page([], self::submitOrderForm($page, ['anta4' => '5']));
        $this->assertSame(['antal' => 1.0, 'leveres' => 1.0], self::line($order, $added), 'a later save leaves the earlier added line alone');
        $after = self::rows('SELECT id, antal, leveres, leveret FROM ordrelinjer WHERE ordre_id = $1 AND id <= $2 ORDER BY id', [$order, $before[1]['id']]);
        $this->assertEquals($before, $after, 'the received lines are untouched');
    }

    /** A draft (status 0) has no «Modtag»; the line Opslag adds keeps its quantity instead of becoming 0. */
    public function test_an_opslag_line_on_a_draft_keeps_its_quantity(): void
    {
        $o = self::orderWithOneLine('KO', 0);
        $page = self::opslag($o['order'], $o['added']);
        $this->assertNull(self::input($page, 'leve2'), 'precondition: a draft shows no «Modtag»');
        $this->assertSame('1', self::input($page, 'antaDefault2')['value'] ?? null);

        self::page([], self::submitOrderForm($page));
        $this->assertSame(1.0, self::line($o['order'], $o['added'])['antal']);
        $this->assertSame('0', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$o['order']])['status'], 'the draft stays a draft');
    }

    /** The reported case: an item typed on the empty line of an approved order, then Enter twice. */
    public function test_a_typed_line_on_an_approved_order_keeps_its_receive_quantity(): void
    {
        $o = self::orderWithOneLine('KO', 1);
        $page = self::page(['id' => $o['order']]);
        $this->assertNull(self::input($page, 'antaDefault1'), 'no default without Opslag');

        $page = self::page([], self::submitOrderForm($page, ['vare0' => $o['added']['varenr']]));
        $this->assertSame(['antal' => 1.0, 'leveres' => 1.0], self::line($o['order'], $o['added']));
        $this->assertSame('1', self::input($page, 'leve2')['value'] ?? null);

        $saved = self::page([], self::submitOrderForm($page));
        $this->assertNoQuantityAlert($saved);
        $this->assertSame(['antal' => 1.0, 'leveres' => 1.0], self::line($o['order'], $o['added']));
    }

    /** Only the line Opslag just added follows «Antal»: on any other line a changed Antal leaves «Modtag» as it was. */
    public function test_modtag_does_not_follow_antal_on_other_lines(): void
    {
        $o = self::orderWithOneLine('KO', 1);
        $page = self::page(['id' => $o['order']]);
        self::page([], self::submitOrderForm($page, ['anta1' => '5']));
        $this->assertSame(['antal' => 5.0, 'leveres' => 1.0], self::line($o['order'], $o['first']));
    }
}
