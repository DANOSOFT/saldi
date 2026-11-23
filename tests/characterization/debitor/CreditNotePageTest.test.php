<?php
// tests/characterization/debitor/CreditNotePageTest.test.php
//
// SD-833: partial credit notes on the order page (debitor/ordre.php), driven like the browser: the test fetches the
// page, reads its form and posts it back with a button pressed.
// An invoice can get several credit notes, one draft at a time; a new one takes what the earlier ones left;
// a draft offers dekort and restock per line and is held to what is left.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261118 CL/SS SD-833 Created.
// 20261120 CL/SS SD-833 review: once a credit line is delivered, its restock and dekort choices are locked.
// 20261123 CDX/NTR Use the test class-owned database and session.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class CreditNotePageTest extends CharacterizationTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::rows('ALTER TABLE ordrelinjer ADD COLUMN IF NOT EXISTS retur_lager varchar(2)');
        self::rows('ALTER TABLE ordrelinjer ADD COLUMN IF NOT EXISTS kredit_art varchar(6)');
    }

    /** Runs the page and returns its HTML. */
    private function page(array $get, array $post = []): string
    {
        $dir = sys_get_temp_dir();
        $req = tempnam($dir, 'ordreq');
        $out = tempnam($dir, 'ordout');
        file_put_contents($req, json_encode(['get' => $get, 'post' => $post]));
        $res = self::runChild('../support/run_ordre_page.php', [CharacterizationEnv::sessionId(), $req, $out]);
        $html = (string)file_get_contents($out);
        @unlink($req);
        @unlink($out);
        $this->assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], $res['stderr']);
        return $html;
    }

    /** The fields the order form would post, as the browser sends them: no buttons, only ticked boxes. */
    private static function formFields(string $html): array
    {
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $fields = [];
        foreach ((new DOMXPath($doc))->query('//form[.//input[@name="b_submit" or @name="print" or @name="dkan1"]]//input | //form[.//input[@name="b_submit" or @name="dkan1"]]//textarea | //form[.//input[@name="b_submit" or @name="dkan1"]]//select') as $el) {
            /** @var DOMElement $el */
            $name = $el->getAttribute('name');
            if ($name === '' || $el->hasAttribute('disabled')) {
                continue;
            }
            $type = strtolower($el->getAttribute('type'));
            if (in_array($type, ['submit', 'button', 'image', 'reset'], true)) {
                continue;
            }
            if (in_array($type, ['checkbox', 'radio'], true) && !$el->hasAttribute('checked')) {
                continue;
            }
            if ($el->nodeName === 'textarea') {
                $value = $el->textContent;
            } elseif ($el->nodeName === 'select') {
                $value = '';
                foreach ($el->getElementsByTagName('option') as $opt) {
                    if ($opt->hasAttribute('selected')) {
                        $value = $opt->hasAttribute('value') ? $opt->getAttribute('value') : $opt->textContent;
                    }
                }
            } else {
                $value = $el->hasAttribute('value') ? $el->getAttribute('value') : ($type === 'checkbox' ? 'on' : '');
            }
            self::setField($fields, $name, $value);
        }
        return $fields;
    }

    private static function setField(array &$fields, string $name, string $value): void
    {
        if (preg_match('/^([^\[]+)\[([^\]]*)\]$/', $name, $m)) {
            $fields[$m[1]][$m[2]] = $value;
        } else {
            $fields[$name] = $value;
        }
    }

    private static function hasButton(string $html, string $value): bool
    {
        return (bool)preg_match('/<input[^>]*value="' . preg_quote($value, '/') . '"[^>]*name="b_submit"/i', $html);
    }

    private function invoicedChairs(array $item, float $antal = 2): array
    {
        $order = self::$fx->order(self::$fx->debtor(), [['item' => $item, 'antal' => $antal, 'pris' => 1000.00]]);
        $this->runChildJson('run_order_invoice.php', ['invoice', $order['id'], CharacterizationEnv::testDb()]);
        return $order;
    }

    public function test_an_invoice_shows_the_credit_button(): void
    {
        $item = self::$fx->item(['beholdning' => 10.0]);
        $invoice = $this->invoicedChairs($item);
        $html = $this->page(['id' => $invoice['id']]);

        $this->assertTrue(self::hasButton($html, 'Kredit'), substr(strip_tags($html), 0, 2000));
    }

    private function pressCredit(int $invoiceId): string
    {
        $fields = self::formFields($this->page(['id' => $invoiceId]));
        $fields['b_submit'] = 'Kredit';
        return $this->page(['id' => $invoiceId], $fields);
    }

    private function saveDraft(int $draftId, array $change): string
    {
        $fields = self::formFields($this->page(['id' => $draftId]));
        foreach ($change as $name => $value) {
            self::setField($fields, $name, (string)$value);
        }
        $fields['save'] = 'Gem';
        return $this->page(['id' => $draftId], $fields);
    }

    private static function creditNotes(int $invoiceId): array
    {
        return self::rows("SELECT id, status FROM ordrer WHERE kred_ord_id = $1 AND art = 'DK' ORDER BY id", [$invoiceId]);
    }

    private static function itemLines(int $orderId): array
    {
        return self::rows('SELECT * FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0 ORDER BY id', [$orderId]);
    }

    public function test_the_credit_button_makes_one_draft_and_a_second_press_opens_it(): void
    {
        $item = self::$fx->item(['beholdning' => 10.0]);
        $invoice = $this->invoicedChairs($item);
        $this->pressCredit($invoice['id']);
        $notes = self::creditNotes($invoice['id']);
        $this->assertCount(1, $notes);
        $this->assertEqualsWithDelta(-2.0, (float)self::itemLines((int)$notes[0]['id'])[0]['antal'], 0.001);

        $html = $this->pressCredit($invoice['id']);
        $this->assertCount(1, self::creditNotes($invoice['id']), 'no second draft');
        $this->assertStringContainsString('ordre.php?id=' . $notes[0]['id'], $html);
    }

    public function test_after_a_posted_partial_credit_the_button_makes_a_draft_of_what_is_left(): void
    {
        $item = self::$fx->item(['beholdning' => 10.0]);
        $invoice = $this->invoicedChairs($item);
        $this->pressCredit($invoice['id']);
        $first = (int)self::creditNotes($invoice['id'])[0]['id'];
        self::rows('UPDATE ordrelinjer SET antal = -1 WHERE ordre_id = $1 AND vare_id > 0', [$first]);
        $this->runChildJson('run_order_invoice.php', ['invoice', $first, CharacterizationEnv::testDb()]);

        $this->assertTrue(self::hasButton($this->page(['id' => $invoice['id']]), 'Kredit'), 'one chair is left');
        $this->pressCredit($invoice['id']);
        $notes = self::creditNotes($invoice['id']);
        $this->assertCount(2, $notes);
        $this->assertEqualsWithDelta(-1.0, (float)self::itemLines((int)$notes[1]['id'])[0]['antal'], 0.001);
    }

    public function test_when_everything_is_credited_the_button_is_gone_and_makes_nothing(): void
    {
        $item = self::$fx->item(['beholdning' => 10.0]);
        $invoice = $this->invoicedChairs($item);
        $this->pressCredit($invoice['id']);
        $this->runChildJson('run_order_invoice.php', ['invoice', (int)self::creditNotes($invoice['id'])[0]['id'], CharacterizationEnv::testDb()]);

        $html = $this->page(['id' => $invoice['id']]);
        $this->assertFalse(self::hasButton($html, 'Kredit'));
        $fields = self::formFields($html);
        $fields['b_submit'] = 'Kredit';
        $this->page(['id' => $invoice['id']], $fields);
        $this->assertCount(1, self::creditNotes($invoice['id']));
    }

    public function test_a_dekort_typed_on_the_draft_becomes_a_dekort_line(): void
    {
        $item = self::$fx->item(['beholdning' => 10.0]);
        $invoice = $this->invoicedChairs($item, 1);
        $this->pressCredit($invoice['id']);
        $draft = (int)self::creditNotes($invoice['id'])[0]['id'];
        $html = $this->page(['id' => $draft]);
        $this->assertStringContainsString('credit_dekort[', $html, 'the draft shows the credit block');

        $lineId = (int)self::itemLines($draft)[0]['id'];
        $this->saveDraft($draft, ["credit_dekort[$lineId]" => '10']);
        $line = self::itemLines($draft)[0];
        $this->assertSame('dekort', trim((string)$line['kredit_art']));
        $this->assertEqualsWithDelta(-1.0, (float)$line['antal'], 0.001);
        $this->assertEqualsWithDelta(100.0, (float)$line['pris'], 0.001);
    }

    public function test_unticking_restock_on_the_draft_saves_no_restock(): void
    {
        $item = self::$fx->item(['beholdning' => 10.0]);
        $invoice = $this->invoicedChairs($item, 1);
        $this->pressCredit($invoice['id']);
        $draft = (int)self::creditNotes($invoice['id'])[0]['id'];
        $lineId = (int)self::itemLines($draft)[0]['id'];
        $fields = self::formFields($this->page(['id' => $draft]));
        $this->assertArrayHasKey($lineId, $fields['credit_restock'] ?? [], 'restock is ticked by default');
        unset($fields['credit_restock'][$lineId]);
        $fields['save'] = 'Gem';
        $this->page(['id' => $draft], $fields);

        $this->assertSame('of', trim((string)self::itemLines($draft)[0]['retur_lager']));
    }

    public function test_once_a_credit_line_is_delivered_its_restock_and_dekort_are_locked(): void
    {
        foreach (['on' => 'of', 'of' => 'on'] as $before => $after) {
            $item = self::$fx->item(['beholdning' => 10.0]);
            $invoice = $this->invoicedChairs($item, 1);
            $this->pressCredit($invoice['id']);
            $draft = (int)self::creditNotes($invoice['id'])[0]['id'];
            $lineId = (int)self::itemLines($draft)[0]['id'];
            self::rows('UPDATE ordrelinjer SET retur_lager = $1 WHERE id = $2', [$before, $lineId]);
            self::rows('UPDATE ordrer SET levdate = ordredate WHERE id = $1', [$draft]);
            $this->runChildJson('run_order_lifecycle.php', ['deliver', $draft, -1, CharacterizationEnv::testDb()]);

            $html = $this->page(['id' => $draft]);
            $this->assertMatchesRegularExpression('/name=.credit_restock\[' . $lineId . '\][^>]*disabled/', $html, "restock $before: the box is locked");
            $this->assertMatchesRegularExpression('/name=.credit_dekort\[' . $lineId . '\][^>]*disabled/', $html, "restock $before: the dekort is locked");

            $fields = self::formFields($html);
            $fields['credit_line'][$lineId] = '1';
            $fields['credit_dekort'][$lineId] = '10';
            if ($after === 'on') {
                $fields['credit_restock'][$lineId] = 'on';
            } else {
                unset($fields['credit_restock'][$lineId]);
            }
            $fields['save'] = 'Gem';
            $this->page(['id' => $draft], $fields);

            $line = self::itemLines($draft)[0];
            $this->assertSame($before, trim((string)$line['retur_lager']), "restock $before: a posted change is ignored after delivery");
            $this->assertNotSame('dekort', trim((string)$line['kredit_art']), "restock $before: no dekort after delivery");
        }
    }

    public function test_a_new_credit_note_reverses_a_negative_invoice_line_only_once(): void
    {
        $chair = self::$fx->item(['beholdning' => 10.0]);
        $returned = self::$fx->item(['beholdning' => 10.0]);
        $order = self::$fx->order(self::$fx->debtor(), [['item' => $chair, 'antal' => 2, 'pris' => 1000.00], ['item' => $returned, 'antal' => -1, 'pris' => 100.00]]);
        $this->runChildJson('run_order_invoice.php', ['invoice', $order['id'], CharacterizationEnv::testDb()]);
        $this->pressCredit($order['id']);
        $first = (int)self::creditNotes($order['id'])[0]['id'];
        self::rows('UPDATE ordrelinjer SET antal = -1 WHERE ordre_id = $1 AND vare_id = $2', [$first, $chair['id']]);
        $this->runChildJson('run_order_invoice.php', ['invoice', $first, CharacterizationEnv::testDb()]);

        $this->pressCredit($order['id']);
        $notes = self::creditNotes($order['id']);
        $this->assertCount(2, $notes);
        $lines = self::itemLines((int)$notes[1]['id']);
        $this->assertCount(1, $lines, 'only the chair that is left');
        $this->assertSame((int)$chair['id'], (int)$lines[0]['vare_id']);
    }

    public function test_the_first_credit_note_takes_discount_and_zero_priced_lines_too(): void
    {
        $chair = self::$fx->item(['beholdning' => 10.0]);
        $discount = self::$fx->item(['beholdning' => 10.0]);
        $part = self::$fx->item(['beholdning' => 10.0]);
        $order = self::$fx->order(self::$fx->debtor(), [
            ['item' => $chair, 'antal' => 1, 'pris' => 1000.00],
            ['item' => $discount, 'antal' => 1, 'pris' => -100.00],
            ['item' => $part, 'antal' => 2, 'pris' => 0.00],
        ]);
        $this->runChildJson('run_order_invoice.php', ['invoice', $order['id'], CharacterizationEnv::testDb()]);
        $this->pressCredit($order['id']);
        $lines = self::itemLines((int)self::creditNotes($order['id'])[0]['id']);

        $this->assertCount(3, $lines);
        $this->assertEqualsWithDelta(-2.0, (float)$lines[2]['antal'], 0.001, 'the zero-priced part can come back on stock');
    }
}
