<?php
// tests/characterization/finans/KassekladdeHentButtonTest.test.php
//
// An open cash journal shows Hent (name='upload', it opens
// finans/hentordrer.php) while invoices that were not posted right away wait
// at status 3.
//
// "Omgående bogføring af salgsordrer" and "... købsordrer" (grupper DIV/3
// box5, "<sales>;<purchases>") unticked leave an invoice at status 3, and
// Kassekladde > Hent is how it reaches the journal. The button was drawn only
// when the DIV/3 row was missing, and every regnskab is created with it, so it
// never showed (testlab round 1, deferred-invoice-posting, DEVY-1).
//
// Pins: with sales or purchases not posted right away and a non-POS order at
// status 3, the journal shows Hent; with both posted right away (also the
// single 'on' from before 3.6) it does not; POS orders alone, which
// hentordrer.php does not list, do not show it.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class KassekladdeHentButtonTest extends CharacterizationTestCase
{
    /** The Hent button as kassekladde.php draws it; its name is the request field the page dispatches on. */
    private const HENT = "/<input[^>]*name='upload'/";

    /** @var list<array{id:string, art:string, box5:?string}> grupper DIV/3 before the test changed it; restored in tearDown(). */
    private array $orderSettings = [];

    /** @var list<int> Orders this test left at status 3; tearDown() takes them out of Hent's list again. */
    private array $createdOrders = [];

    /** @var list<string> Status-3 orders already in the tenant that a test set aside; tearDown() puts them back. */
    private array $setAside = [];

    protected function setUp(): void
    {
        $this->orderSettings = self::rows("SELECT id, art, box5 FROM grupper WHERE art = 'DIV' AND kodenr = 3");
        $this->assertNotSame([], $this->orderSettings, 'the template tenant has the order settings row (grupper DIV/3)');
    }

    protected function tearDown(): void
    {
        foreach ($this->orderSettings as $row) {
            self::rows('UPDATE grupper SET art = $1, box5 = $2 WHERE id = $3', [$row['art'], $row['box5'], $row['id']]);
        }
        foreach ($this->createdOrders as $id) {
            self::rows('UPDATE ordrer SET status = 4 WHERE id = $1', [$id]);
        }
        foreach ($this->setAside as $id) {
            self::rows('UPDATE ordrer SET status = 3 WHERE id = $1', [$id]);
        }
    }

    private function setPostRightAway(string $box5): void
    {
        self::rows("UPDATE grupper SET box5 = $1 WHERE art = 'DIV' AND kodenr = 3", [$box5]);
    }

    /** @param array<string,mixed> $overrides Fixtures::order() overrides, e.g. status and art. */
    private function order(array $overrides = []): int
    {
        $order = self::$fx->order(self::$fx->debtor(), [['item' => self::$fx->item(['stocked' => false]), 'antal' => 2, 'pris' => 125.00]], $overrides);
        $this->createdOrders[] = (int)$order['id'];
        return (int)$order['id'];
    }

    /** The open journal's page, as finans/kassekladde.php?kladde_id=.. draws it. */
    private function journalPage(): string
    {
        $kladdeId = self::$fx->kladde([]);
        $htmlFile = (string)tempnam(sys_get_temp_dir(), 'chartest-kassekladde-');
        try {
            $res = self::runChild('run_kassekladde_page.php', ["kladde_id=$kladdeId", CharacterizationEnv::SESSION_ID, $htmlFile]);
            $html = (string)file_get_contents($htmlFile);
        } finally {
            @unlink($htmlFile);
        }
        $this->assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], "the page ran to its end.\nstderr: {$res['stderr']}");
        // Booleans, not assertStringContainsString(): a failure would print the whole page.
        $this->assertTrue(str_contains($html, "name='doPost'"), 'the journal is open for editing: its buttons are drawn');
        return $html;
    }

    private static function hasHent(string $html): bool
    {
        return preg_match(self::HENT, $html) === 1;
    }

    public function test_hent_shows_for_an_invoice_left_unposted_by_the_order_settings(): void
    {
        // What the lab did: both "Omgående bogføring" boxes unticked and saved, then a sales invoice.
        $this->setPostRightAway(';');
        $id = $this->order();
        $out = $this->runChildJson('run_order_invoice.php', ['invoice', $id, CharacterizationEnv::testDb()]);
        $this->assertSame('OK', $out['bogfor'] ?? null, 'the order is invoiced');
        $this->assertSame('3', (string)self::one('SELECT status FROM ordrer WHERE id = $1', [$id])['status'], 'invoiced, not posted: it waits for Hent');

        $this->assertTrue(self::hasHent($this->journalPage()), 'Hent is drawn while the invoice waits');
    }

    /** @return array<string, array{0: ?string, 1: bool}> box5, or null for no DIV/3 row => Hent drawn */
    public static function settings(): array
    {
        return [
            'neither posted right away'          => [';', true],
            'purchases not posted right away'    => ['on;', true],
            'sales not posted right away'        => [';on', true],
            'single value from before 3.6, off'  => ['', true],
            'no order settings row'              => [null, true],
            'both posted right away'             => ['on;on', false],
            'single value from before 3.6, on'   => ['on', false],
        ];
    }

    #[DataProvider('settings')]
    public function test_hent_follows_the_immediate_posting_settings(?string $box5, bool $shown): void
    {
        if ($box5 === null) {
            self::rows("UPDATE grupper SET art = 'DIV-chartest' WHERE art = 'DIV' AND kodenr = 3");
        } else {
            $this->setPostRightAway($box5);
        }
        $this->order(['status' => 3]);

        $this->assertSame(
            $shown,
            self::hasHent($this->journalPage()),
            $shown ? 'an order waits at status 3 and is not posted right away: Hent is drawn' : 'sales and purchases are posted right away: no Hent'
        );
    }

    public function test_pos_orders_alone_do_not_show_hent(): void
    {
        foreach (self::rows("SELECT id FROM ordrer WHERE art != 'PO' AND status = 3") as $row) {
            self::rows('UPDATE ordrer SET status = -3 WHERE id = $1', [$row['id']]);
            $this->setAside[] = $row['id'];
        }
        $this->setPostRightAway(';');
        $this->order(['status' => 3, 'art' => 'PO']);

        $this->assertFalse(self::hasHent($this->journalPage()), 'hentordrer.php lists no POS order, so a POS order alone draws no Hent');
    }
}
