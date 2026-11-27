<?php
// tests/characterization/finans/JournalReversalTest.test.php
//
// Reversing a posted cash-journal line must cancel its posting on every account.
//
// "Tilbagefør postering" (finans/kassekladde.php?ompost=<line>&ompost_til=<journal>, ompost()) writes the
// line reversed into an open journal. "Kopier til ny" (copy2new() in finans/kassekladde_includes/copy2new.php)
// copies a posted journal, reversed when "Vend fortegn" is ticked. Both results are posted afterwards
// through finans/bogfor.php, like any journal.
//
// Pins:
//  - a reversed line in EUR keeps the currency and the rate, so bank, expense and input VAT net to zero, and a
//    debtor's control account and open items in EUR net to zero (bookkeeping F2);
//  - that holds when the rate for the line's date is corrected after the original was posted and the journal page
//    saves the reversal before posting: the reversal (kassekladde.ompost_id) converts at the rate its original was
//    posted at, in the journal page, the posting page's preview and the posting, the difference account included;
//    a line typed in the journal still converts at the rate table's rate (Kimi review of bcc06320);
//  - a line posted before this change, which holds no rate or an older one in kassekladde.valutakurs, is reversed at
//    the one rate on its voucher's ledger rows;
//  - "reverse and re-enter" (Tilbagefør and the corrected line on the same voucher and date, posted at two rates):
//    each of the two lines, reversed again later, is reversed at its own rate, in either typing order;
//  - a copied line keeps its own VAT codes (debetvat/kreditvat), swapped with debit and credit when reversed,
//    so a reversed copy nets to zero and a plain copy posts the original VAT split (bookkeeping F3).
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261117 CL/ASR Created (bookkeeping F2 and F3).
// 20261117 CL/ASR Reversals after a corrected rate, posted through the journal page's Bogfør button and the posting page (Kimi review of bcc06320).
//                 Reversals of a reversal and of a corrected line posted on the same voucher and date, with ledger and open items (review of that change).
//                 Reversal of a line posted before that change, without its posted rate on the line (review of that change).

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class JournalReversalTest extends CharacterizationTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // finans/kassekladde.php adds the line VAT columns on its first load (ensure_cash_vat_columns()).
        self::rows('ALTER TABLE kassekladde ADD COLUMN IF NOT EXISTS debetvat varchar');
        self::rows('ALTER TABLE kassekladde ADD COLUMN IF NOT EXISTS kreditvat varchar');
    }

    /** Request finans/kassekladde.php like the browser; $post makes it a form POST. Returns the HTML. */
    private function journalPage(string $query, ?string $post = null): string
    {
        $htmlFile = (string)tempnam(sys_get_temp_dir(), 'chartest-kassekladde-');
        try {
            $args = [$query, CharacterizationEnv::sessionId(), $htmlFile];
            if ($post !== null) {
                $args[] = $post;
            }
            $res = self::runChild('run_kassekladde_page.php', $args);
            $this->assertSame(0, $res['exit'], $res['stderr'] . $res['stdout']);
            return (string)file_get_contents($htmlFile);
        } finally {
            @unlink($htmlFile);
        }
    }

    /**
     * The fields of an open journal's form as the browser would send them back, read from the rendered page.
     *
     * @return array<string, string>
     */
    private function journalForm(int $kladdeId): array
    {
        $html = $this->journalPage("kladde_id=$kladdeId");
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $form = $dom->getElementById('kassekladde');
        $this->assertNotNull($form, 'the journal page renders its form');
        $fields = [];
        foreach ((new DOMXPath($dom))->query('.//input | .//select | .//textarea', $form) as $el) {
            $name = $el->getAttribute('name');
            $type = strtolower($el->getAttribute('type'));
            if ($name === '' || in_array($type, ['submit', 'button', 'image', 'reset', 'file'], true)) {
                continue;
            }
            if (in_array($type, ['checkbox', 'radio'], true) && !$el->hasAttribute('checked')) {
                continue;
            }
            if ($el->nodeName === 'select') {
                $value = '';
                foreach ($el->getElementsByTagName('option') as $i => $option) {
                    if ($i === 0 || $option->hasAttribute('selected')) {
                        $value = $option->hasAttribute('value') ? $option->getAttribute('value') : $option->textContent;
                    }
                }
            } elseif ($el->nodeName === 'textarea') {
                $value = $el->textContent;
            } else {
                $value = $el->getAttribute('value');
            }
            $fields[$name] = $value;
        }
        return $fields;
    }

    /**
     * Press Bogfør on an open journal and confirm on the posting page, as the user does: the journal page saves
     * its form first (each line through kontroller()), then finans/bogfor.php posts. Returns the posting page's
     * HTML, which carries its preview of the journal.
     */
    private function postFromJournalPage(int $kladdeId): string
    {
        self::rows("UPDATE kladdeliste SET bogfort = '-' WHERE id = $1", [$kladdeId]);
        $fields = $this->journalForm($kladdeId);
        $fields['doPost'] = 'Bogfør';
        $answer = $this->journalPage("kladde_id=$kladdeId", http_build_query($fields));
        $this->assertStringContainsString("bogfor.php?kladde_id=$kladdeId&funktion=bogfor", $answer, 'the journal page saved and went on to the posting page');

        // The page's HTML: PHP flushes it to stdout when the page ends in exit(), and run_bogfor_page.php repeats it
        // on stderr with SALDI_CHAR_DEBUG when it runs to its end.
        putenv('SALDI_CHAR_DEBUG=1');
        try {
            $res = self::runChild('run_bogfor_page.php', ['bogfor', $kladdeId, CharacterizationEnv::sessionId()]);
        } finally {
            putenv('SALDI_CHAR_DEBUG');
        }
        $this->assertSame(0, $res['exit'], $res['stderr'] . $res['stdout']);
        $kladde = self::one('SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladdeId]);
        $this->assertSame('V', trim((string)$kladde['bogfort']), "journal $kladdeId is posted");
        return $res['stdout'] . $res['stderr'];
    }

    /** Post a journal through finans/bogfor.php, as Bogfør does. */
    private function post(int $kladdeId): void
    {
        $res = self::runChild('run_bogfor_page.php', ['bogfor', $kladdeId, CharacterizationEnv::sessionId()]);
        $this->assertSame(0, $res['exit'], $res['stderr'] . $res['stdout']);
        $kladde = self::one('SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladdeId]);
        $this->assertSame('V', trim((string)$kladde['bogfort']), "journal $kladdeId is posted");
    }

    /** The DKK amount the journal page shows for a line (the hidden field dkka<row> it posts back). */
    private function shownDkk(int $kladdeId, int $lineId): float
    {
        $form = $this->journalForm($kladdeId);
        foreach ($form as $name => $value) {
            if (preg_match('/^id\[(\d+)\]$/', $name, $m) && (int)$value === $lineId) {
                $this->assertArrayHasKey('dkka' . $m[1], $form, "the journal page shows line $lineId's DKK amount");
                return (float)$form['dkka' . $m[1]];
            }
        }
        $this->fail("the journal page shows line $lineId");
    }

    /** Move the EUR rate of the start of the year, which every line of this year converts at, to $rate. */
    private function correctEuroRate(int $eur, float $rate): void
    {
        self::rows('UPDATE valuta SET kurs = $1 WHERE gruppe = $2', [$rate, (string)$eur]);
    }

    /** Run Tilbagefør postering on a posted line into the open journal $target, through the link on the journal page. */
    private function reverseLine(int $kladdeId, int $lineId, int $target): void
    {
        $this->journalPage("kladde_id=$kladdeId&ompost=$lineId&ompost_til=$target");
    }

    /**
     * Debit minus credit per account over the postings of the given journals.
     *
     * @param list<int> $kladdeIds
     * @return array<int, float>
     */
    private function net(array $kladdeIds): array
    {
        $rows = self::rows(
            'SELECT kontonr, SUM(COALESCE(debet, 0) - COALESCE(kredit, 0)) AS net FROM transaktioner
             WHERE kladde_id = ANY($1::int[]) GROUP BY kontonr ORDER BY kontonr',
            ['{' . implode(',', array_map('intval', $kladdeIds)) . '}']
        );
        $net = [];
        foreach ($rows as $r) {
            $net[(int)$r['kontonr']] = round((float)$r['net'], 2);
        }
        return $net;
    }

    /** The currency group EUR (grupper VK) at $rate per 100 from the start of the year; returns its code. */
    private function euro(float $rate): int
    {
        $existing = self::one("SELECT kodenr FROM grupper WHERE art = 'VK' AND box1 = 'EUR'");
        if ($existing !== null) {
            $code = (int)$existing['kodenr'];
        } else {
            $next = self::one("SELECT COALESCE(MAX(kodenr), 0) + 1 AS kodenr FROM grupper WHERE art = 'VK'");
            $code = (int)$next['kodenr'];
            [, $diffAccount] = self::$fx->plainAccounts(2);
            self::rows(
                "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, fiscal_year)
                 VALUES ('Euro', 'V', $1, 'VK', 'EUR', '', $2, $3)",
                [$code, (string)$diffAccount, self::$regnaar]
            );
        }
        self::rows('DELETE FROM valuta WHERE gruppe = $1', [(string)$code]);
        self::rows('INSERT INTO valuta (gruppe, valdate, kurs) VALUES ($1, $2, $3)', [(string)$code, date('Y') . '-01-01', $rate]);
        return $code;
    }

    /** Id of the only line of a journal. */
    private function lineId(int $kladdeId): int
    {
        $rows = self::rows('SELECT id FROM kassekladde WHERE kladde_id = $1', [$kladdeId]);
        $this->assertCount(1, $rows, "journal $kladdeId has one line");
        return (int)$rows[0]['id'];
    }

    /** Run "Kopier til ny" on a posted journal with the form's fields; returns the new journal's id. */
    private function copyToNew(int $kladdeId, bool $reverse): int
    {
        $before = (int)self::one('SELECT COALESCE(MAX(id), 0) AS id FROM kladdeliste')['id'];
        $fields = ['copy2new' => 'Kopier til ny', 'kladde_id' => $kladdeId, 'bilagsnr' => '=', 'ny_dato' => '='];
        if ($reverse) {
            $fields['vend_fortegn'] = 'on';
        }
        $this->journalPage("kladde_id=$kladdeId", http_build_query($fields));
        $copy = (int)self::one('SELECT COALESCE(MAX(id), 0) AS id FROM kladdeliste')['id'];
        $this->assertGreaterThan($before, $copy, 'Kopier til ny created a journal');
        return $copy;
    }

    /** The reverse-charge VAT group Y<n> with its VAT account (box1), counter account (box3) and rate. */
    private function reverseChargeVat(): array
    {
        $grp = self::one(
            "SELECT kodenr, box1, box2, box3 FROM grupper
             WHERE art = 'YM' AND fiscal_year = $1 AND box1 <> '' AND box2 <> '' AND box3 <> '' ORDER BY kodenr LIMIT 1",
            [self::$regnaar]
        );
        $this->assertNotNull($grp, 'the installed tenant has a reverse-charge VAT group (grupper YM)');
        return ['Y' . (int)$grp['kodenr'], (int)$grp['box1'], (int)$grp['box3'], (float)$grp['box2']];
    }

    /**
     * The rate for the line's date is corrected after the line was posted (746,00 -> 750,00). Tilbagefør must still
     * reverse what was posted: the journal shows and posts the reversal at 746,00, also after the journal page has
     * saved the line (which writes the rate table's 750,00 into kassekladde.valutakurs).
     */
    public function test_tilbagefoer_after_the_rate_was_corrected_reverses_at_the_rate_the_line_was_posted_at(): void
    {
        $eur = $this->euro(746.00);
        [$expense, $inputVat] = self::$fx->vatPurchaseAccount();
        [$bank] = self::$fx->plainAccounts(1);

        $original = self::$fx->kladde([[
            'bilag' => 8, 'debet' => $expense, 'kredit' => $bank, 'amount' => 100.00,
            'valuta' => $eur, 'valutakurs' => 746.00, 'tekst' => 'chartest EUR expense, rate corrected',
        ]]);
        $this->post($original);
        $this->assertEquals([$expense => 596.80, $inputVat => 149.20, $bank => -746.00], $this->net([$original]));
        $originalLine = $this->lineId($original);

        $this->correctEuroRate($eur, 750.00);
        $target = self::$fx->kladde([]);
        $this->reverseLine($original, $originalLine, $target);

        self::rows("UPDATE kladdeliste SET bogfort = '-' WHERE id = $1", [$target]);
        $this->assertEqualsWithDelta(746.00, $this->shownDkk($target, $this->lineId($target)), 0.001, 'the journal shows the reversal at 746,00 DKK');

        $this->postFromJournalPage($target);
        $this->assertEquals([$expense => -596.80, $inputVat => -149.20, $bank => 746.00], $this->net([$target]));
        $this->assertEquals(
            [$expense => 0.00, $inputVat => 0.00, $bank => 0.00],
            $this->net([$original, $target]),
            'original and reversal cancel out although the rate table now says 750,00'
        );
        $rates = array_unique(array_map(
            fn($r) => round((float)$r['valutakurs'], 3),
            self::rows('SELECT valutakurs FROM transaktioner WHERE kladde_id = $1', [$target])
        ));
        $this->assertSame([746.0], array_values($rates), 'the reversal is posted at 746,00');
        $reversal = self::one('SELECT ompost_id FROM kassekladde WHERE kladde_id = $1', [$target]);
        $this->assertSame($originalLine, (int)$reversal['ompost_id'], 'the reversal names the line it reverses');
    }

    /** What a line posted before bogfor() recorded the posted rate on the line (recordPostedLineRate()) may hold. */
    public static function ratesStoredBeforeTheChange(): array
    {
        return [
            'no rate (an imported line, never saved)' => [null],
            'an older rate (saved before the rate table was updated)' => [740.00],
        ];
    }

    /**
     * A journal posted before this change: its line's kassekladde.valutakurs does not hold the rate it was posted at,
     * because nothing wrote the posted rate there. Every journal in a customer's database at deploy time is such a
     * journal. Its voucher's ledger rows hold one rate, the one it was posted at, and Tilbagefør after the rate table
     * was corrected (746,00 -> 750,00) reverses at that rate.
     */
    #[DataProvider('ratesStoredBeforeTheChange')]
    public function test_tilbagefoer_of_a_line_posted_before_the_change_reverses_at_its_ledger_rate(?float $storedRate): void
    {
        $eur = $this->euro(746.00);
        [$expense, $inputVat] = self::$fx->vatPurchaseAccount();
        [$bank] = self::$fx->plainAccounts(1);

        $original = self::$fx->kladde([[
            'bilag' => 7, 'debet' => $expense, 'kredit' => $bank, 'amount' => 100.00,
            'valuta' => $eur, 'valutakurs' => 746.00, 'tekst' => 'chartest EUR expense, posted before the change',
        ]]);
        $this->post($original);
        $this->assertEquals([$expense => 596.80, $inputVat => 149.20, $bank => -746.00], $this->net([$original]));
        $originalLine = $this->lineId($original);
        // As before this change, the posted line does not hold its posted rate.
        self::rows('UPDATE kassekladde SET valutakurs = $1 WHERE id = $2', [$storedRate, $originalLine]);
        $kurs = self::one('SELECT valutakurs FROM kassekladde WHERE id = $1', [$originalLine])['valutakurs'];
        $this->assertSame($storedRate, $kurs === null ? null : round((float)$kurs, 3), 'the original line holds what a line posted before the change may hold');

        $this->correctEuroRate($eur, 750.00);
        $target = self::$fx->kladde([]);
        $this->reverseLine($original, $originalLine, $target);
        self::rows("UPDATE kladdeliste SET bogfort = '-' WHERE id = $1", [$target]);
        $this->assertEqualsWithDelta(746.00, $this->shownDkk($target, $this->lineId($target)), 0.001, 'the journal shows the reversal at 746,00 DKK');

        $this->postFromJournalPage($target);
        $this->assertEquals([$expense => -596.80, $inputVat => -149.20, $bank => 746.00], $this->net([$target]), 'the reversal posts at 746,00');
        $this->assertEquals(
            [$expense => 0.00, $inputVat => 0.00, $bank => 0.00],
            $this->net([$original, $target]),
            'original and reversal cancel out although the rate table now says 750,00'
        );
        $rates = array_unique(array_map(
            fn($r) => round((float)$r['valutakurs'], 3),
            self::rows('SELECT valutakurs FROM transaktioner WHERE kladde_id = $1', [$target])
        ));
        $this->assertSame([746.0], array_values($rates), 'the reversal is posted at 746,00');
    }

    /**
     * A EUR invoice of two lines (debtor in EUR, income in DKK) reversed line by line after the rate was corrected.
     * Each reversed line keeps its original amount in DKK, so the reversed voucher balances, the posting page shows
     * no difference, and the debtor's open items in EUR and its control account net to zero.
     */
    public function test_tilbagefoer_of_a_two_line_euro_invoice_after_the_rate_was_corrected_cancels_it(): void
    {
        $eur = $this->euro(746.00);
        $debtor = self::$fx->debtor();
        $control = self::$fx->debtorCollectiveAccount();
        [$income] = self::$fx->plainAccounts(1);

        // The empty side is 0, as the journal page saves it.
        $original = self::$fx->kladde([
            ['bilag' => 22, 'd_type' => 'D', 'debet' => (int)$debtor['kontonr'], 'kredit' => 0, 'amount' => 100.00, 'valuta' => $eur,
             'valutakurs' => 746.00, 'momsfri' => 'on', 'faktura' => 'CHT-EUR-2', 'tekst' => 'chartest EUR invoice, two lines'],
            ['bilag' => 22, 'debet' => 0, 'kredit' => $income, 'amount' => 746.00, 'momsfri' => 'on', 'faktura' => 'CHT-EUR-2',
             'tekst' => 'chartest EUR invoice, two lines'],
        ]);
        $this->post($original);
        $this->assertEquals([$control => 746.00, $income => -746.00], $this->net([$original]));

        $this->correctEuroRate($eur, 750.00);
        $target = self::$fx->kladde([]);
        foreach (self::rows('SELECT id FROM kassekladde WHERE kladde_id = $1 ORDER BY id', [$original]) as $line) {
            $this->reverseLine($original, (int)$line['id'], $target);
        }
        $this->assertCount(2, self::rows('SELECT id FROM kassekladde WHERE kladde_id = $1', [$target]), 'both lines are reversed');
        $page = $this->postFromJournalPage($target);

        $this->assertFalse(str_contains($page, 'Der er differencer'), 'the posting page finds the reversed voucher balanced (no "Der er differencer")');
        $this->assertEquals([$control => -746.00, $income => 746.00], $this->net([$target]));
        $this->assertEquals([$control => 0.00, $income => 0.00], $this->net([$original, $target]), 'control account and income cancel out');
        $items = self::rows('SELECT amount, valutakurs FROM openpost WHERE konto_id = $1 ORDER BY id', [$debtor['id']]);
        $this->assertEquals(
            [[100.0, 746.0], [-100.0, 746.0]],
            array_map(fn($r) => [round((float)$r['amount'], 2), round((float)$r['valutakurs'], 2)], $items),
            'the invoice and its reversal are both 100 EUR at 746,00'
        );
    }

    /**
     * A EUR voucher whose lines round to 0,01 more on the debit side; bogfor() books that 0,01 on the currency's
     * difference account (grupper VK box3). Reversed line by line after the rate was corrected, the reversal books
     * the same 0,01 back on the difference account, and every account nets to zero.
     */
    public function test_tilbagefoer_of_a_euro_voucher_with_a_rounding_difference_reverses_the_difference_too(): void
    {
        $eur = $this->euro(746.13);
        $diffAccount = (int)self::one("SELECT box3 FROM grupper WHERE art = 'VK' AND kodenr = $1", [$eur])['box3'];
        [, , $expense, $bank] = self::$fx->plainAccounts(4);
        $this->assertNotContains($diffAccount, [$expense, $bank], 'the difference account is a separate account');

        // 2 x 33,33 EUR = 2 x 248,69 DKK on the debit side, 66,66 EUR = 497,37 DKK on the credit side.
        $line = ['bilag' => 23, 'amount' => 33.33, 'valuta' => $eur, 'valutakurs' => 746.13, 'momsfri' => 'on', 'tekst' => 'chartest EUR rounding'];
        $original = self::$fx->kladde([
            $line + ['debet' => $expense, 'kredit' => 0],
            $line + ['debet' => $expense, 'kredit' => 0],
            ['amount' => 66.66, 'debet' => 0, 'kredit' => $bank] + $line,
        ]);
        // As the journal page saves a line: no department is 0 (bogfor() writes it on the difference row as it is).
        self::rows('UPDATE kassekladde SET afd = 0 WHERE kladde_id = $1', [$original]);
        $this->post($original);
        $this->assertEquals([$diffAccount => -0.01, $expense => 497.38, $bank => -497.37], $this->net([$original]));

        $this->correctEuroRate($eur, 750.00);
        $target = self::$fx->kladde([]);
        foreach (self::rows('SELECT id FROM kassekladde WHERE kladde_id = $1 ORDER BY id', [$original]) as $row) {
            $this->reverseLine($original, (int)$row['id'], $target);
        }
        $this->postFromJournalPage($target);

        $this->assertEquals([$diffAccount => 0.01, $expense => -497.38, $bank => 497.37], $this->net([$target]));
        $this->assertEquals(
            [$diffAccount => 0.00, $expense => 0.00, $bank => 0.00],
            $this->net([$original, $target]),
            'original and reversal cancel out, the difference account included'
        );
    }

    /** A line saved in an open journal, as the user types it (the page's save later writes the rate table's rate). */
    private function addLine(int $kladdeId, array $line): void
    {
        self::rows(
            "INSERT INTO kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, amount, faktura, momsfri,
                                      valuta, valutakurs, afd, kladde_id)
             VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, NULL, 0, $12)",
            [
                $line['bilag'], $line['transdate'], $line['tekst'], $line['d_type'] ?? 'F', $line['debet'],
                $line['k_type'] ?? 'F', $line['kredit'], $line['amount'], $line['faktura'] ?? '', $line['momsfri'] ?? '',
                $line['valuta'], $kladdeId,
            ]
        );
    }

    /** The order the user types the two lines of a "reverse and re-enter" correction in. */
    public static function reEntryOrders(): array
    {
        return [
            'Tilbagefør first, then the corrected line' => [false],
            'the corrected line first, then Tilbagefør' => [true],
        ];
    }

    /**
     * "Reverse and re-enter": after the rate for the date was corrected (746,00 -> 750,00), the user reverses the
     * posted line with Tilbagefør and types the corrected line on the same voucher and date, in one journal. That
     * journal posts the reversal at 746,00 and the corrected line at 750,00. Each of the two lines can later be reversed
     * again with Tilbagefør, and each of those reversals cancels its own line exactly, at that line's own rate.
     */
    #[DataProvider('reEntryOrders')]
    public function test_reversal_and_corrected_line_on_one_voucher_are_each_reversed_at_their_own_rate(bool $correctionFirst): void
    {
        $eur = $this->euro(746.00);
        [$expense, $inputVat] = self::$fx->vatPurchaseAccount();
        [$bank] = self::$fx->plainAccounts(1);
        $line = [
            'bilag' => 7, 'transdate' => date('Y-m-d'), 'debet' => $expense, 'kredit' => $bank, 'amount' => 100.00,
            'valuta' => $eur, 'tekst' => 'chartest EUR expense, reverse and re-enter',
        ];
        $original = self::$fx->kladde([$line + ['valutakurs' => 746.00]]);
        $this->post($original);
        $this->assertEquals([$expense => 596.80, $inputVat => 149.20, $bank => -746.00], $this->net([$original]));
        $originalLine = $this->lineId($original);

        $this->correctEuroRate($eur, 750.00);
        $correction = self::$fx->kladde([]);
        if ($correctionFirst) {
            $this->addLine($correction, $line);
        }
        $this->reverseLine($original, $originalLine, $correction);
        if (!$correctionFirst) {
            $this->addLine($correction, $line);
        }
        $this->postFromJournalPage($correction);

        // The reversal at 746,00 (-596,80 / -149,20 / +746,00) and the corrected line at 750,00 (+600,00 / +150,00 / -750,00).
        $this->assertEquals([$expense => 3.20, $inputVat => 0.80, $bank => -4.00], $this->net([$correction]));
        $this->assertEquals([$expense => 600.00, $inputVat => 150.00, $bank => -750.00], $this->net([$original, $correction]), 'what is left is the corrected line at 750,00');
        $reversal = self::one('SELECT id FROM kassekladde WHERE kladde_id = $1 AND ompost_id = $2', [$correction, $originalLine]);
        $this->assertNotNull($reversal, 'the journal holds the reversal of the original');
        $reversalLine = (int)$reversal['id'];
        $corrected = self::one('SELECT id FROM kassekladde WHERE kladde_id = $1 AND ompost_id IS NULL', [$correction]);
        $this->assertNotNull($corrected, 'the journal holds the corrected line');
        $correctedLine = (int)$corrected['id'];

        // Tilbagefør of the corrected line: 750,00, the rate it was posted at.
        $undoCorrected = self::$fx->kladde([]);
        $this->reverseLine($correction, $correctedLine, $undoCorrected);
        $this->assertEqualsWithDelta(750.00, $this->shownDkk($undoCorrected, $this->lineId($undoCorrected)), 0.001, 'the journal shows the reversal of the corrected line at 750,00 DKK');
        $this->postFromJournalPage($undoCorrected);
        $this->assertEquals([$expense => -600.00, $inputVat => -150.00, $bank => 750.00], $this->net([$undoCorrected]), 'the corrected line is reversed at 750,00');

        // Tilbagefør of the reversal: 746,00, the rate the reversal was posted at.
        $undoReversal = self::$fx->kladde([]);
        $this->reverseLine($correction, $reversalLine, $undoReversal);
        $this->assertEqualsWithDelta(746.00, $this->shownDkk($undoReversal, $this->lineId($undoReversal)), 0.001, 'the journal shows the reversal of the reversal at 746,00 DKK');
        $this->postFromJournalPage($undoReversal);
        $this->assertEquals([$expense => 596.80, $inputVat => 149.20, $bank => -746.00], $this->net([$undoReversal]), 'the reversal is reversed at 746,00');

        $this->assertEquals(
            [$expense => 0.00, $inputVat => 0.00, $bank => 0.00],
            $this->net([$correction, $undoCorrected, $undoReversal]),
            'each line of the correction journal is cancelled by its own reversal'
        );
        $this->assertEquals(
            [$reversalLine => 746.0, $correctedLine => 750.0],
            array_column(array_map(
                fn($r) => ['id' => (int)$r['id'], 'kurs' => round((float)$r['valutakurs'], 3)],
                self::rows('SELECT id, valutakurs FROM kassekladde WHERE kladde_id = $1', [$correction])
            ), 'kurs', 'id'),
            'each posted line of the correction journal holds the rate it was posted at'
        );
    }

    /**
     * The same "reverse and re-enter" on a EUR invoice to a debtor: the corrected invoice line, reversed again later,
     * cancels on the control account, the income account and in the debtor's open items.
     */
    #[DataProvider('reEntryOrders')]
    public function test_reversal_of_a_corrected_debtor_line_cancels_it_in_the_open_items(bool $correctionFirst): void
    {
        $eur = $this->euro(746.00);
        $debtor = self::$fx->debtor();
        $control = self::$fx->debtorCollectiveAccount();
        [$income] = self::$fx->plainAccounts(1);
        $line = [
            'bilag' => 24, 'transdate' => date('Y-m-d'), 'd_type' => 'D', 'debet' => (int)$debtor['kontonr'], 'kredit' => $income,
            'amount' => 100.00, 'valuta' => $eur, 'momsfri' => 'on', 'faktura' => 'CHT-EUR-3', 'tekst' => 'chartest EUR invoice, re-entered',
        ];
        $original = self::$fx->kladde([$line + ['valutakurs' => 746.00]]);
        $this->post($original);
        $originalLine = $this->lineId($original);

        $this->correctEuroRate($eur, 750.00);
        $correction = self::$fx->kladde([]);
        if ($correctionFirst) {
            $this->addLine($correction, $line);
        }
        $this->reverseLine($original, $originalLine, $correction);
        if (!$correctionFirst) {
            $this->addLine($correction, $line);
        }
        $this->postFromJournalPage($correction);
        $this->assertEquals([$control => 750.00, $income => -750.00], $this->net([$original, $correction]), 'what is left is the corrected invoice at 750,00');
        $corrected = self::one('SELECT id FROM kassekladde WHERE kladde_id = $1 AND ompost_id IS NULL', [$correction]);
        $this->assertNotNull($corrected, 'the journal holds the corrected line');

        $undoCorrected = self::$fx->kladde([]);
        $this->reverseLine($correction, (int)$corrected['id'], $undoCorrected);
        $this->postFromJournalPage($undoCorrected);

        $this->assertEquals([$control => -750.00, $income => 750.00], $this->net([$undoCorrected]), 'the corrected invoice is reversed at 750,00');
        $this->assertEquals([$control => 0.00, $income => 0.00], $this->net([$original, $correction, $undoCorrected]), 'control account and income cancel out');
        $items = self::rows('SELECT amount, valutakurs FROM openpost WHERE konto_id = $1 ORDER BY id', [$debtor['id']]);
        $pairs = array_map(fn($r) => [round((float)$r['amount'], 2), round((float)$r['valutakurs'], 2)], $items);
        sort($pairs);
        $this->assertEquals(
            [[-100.0, 746.0], [-100.0, 750.0], [100.0, 746.0], [100.0, 750.0]],
            $pairs,
            'invoice and its reversal at 746,00, corrected invoice and its reversal at 750,00'
        );
        $this->assertEqualsWithDelta(
            0.00,
            array_sum(array_map(fn($r) => round((float)$r['amount'] * (float)$r['valutakurs'] / 100, 2), $items)),
            0.001,
            'the open items net to 0,00 DKK'
        );
    }

    /**
     * bogfor() puts the rate it posts a currency line at on the line (kassekladde.valutakurs), where a later
     * Tilbagefør of the line finds it. A simulation records nothing, and a DKK line keeps what it has.
     */
    public function test_posting_puts_the_posted_rate_on_a_currency_line_and_a_simulation_does_not(): void
    {
        $eur = $this->euro(746.00);
        [$expense] = self::$fx->vatPurchaseAccount();
        [$bank] = self::$fx->plainAccounts(1);
        // Saved while the rate was 746,00, posted after it became 750,00.
        $kladdeId = self::$fx->kladde([
            ['bilag' => 10, 'debet' => $expense, 'kredit' => $bank, 'amount' => 100.00, 'valuta' => $eur, 'valutakurs' => 746.00,
             'tekst' => 'chartest EUR expense, rate recorded'],
            ['bilag' => 11, 'debet' => $expense, 'kredit' => $bank, 'amount' => 50.00, 'tekst' => 'chartest DKK expense, rate recorded'],
        ]);
        $this->correctEuroRate($eur, 750.00);
        $stored = fn() => array_map(
            fn($r) => $r['valutakurs'] === null ? null : round((float)$r['valutakurs'], 3),
            self::rows('SELECT valutakurs FROM kassekladde WHERE kladde_id = $1 ORDER BY bilag', [$kladdeId])
        );

        $res = self::runChild('run_bogfor_page.php', ['simuler', $kladdeId, CharacterizationEnv::sessionId()]);
        $this->assertSame(0, $res['exit'], $res['stderr'] . $res['stdout']);
        $this->assertSame('S', trim((string)self::one('SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladdeId])['bogfort']), 'the journal is simulated');
        $this->assertSame([746.0, null], $stored(), 'a simulation leaves the lines as they are');

        self::rows("UPDATE kladdeliste SET bogfort = '-' WHERE id = $1", [$kladdeId]);
        $this->post($kladdeId);
        $this->assertSame([750.0, null], $stored(), 'the EUR line holds the rate it was posted at, the DKK line is left alone');
    }

    /**
     * Only a line written by Tilbagefør keeps an earlier rate. A line typed in the journal converts at the rate
     * table's rate for its date when it is posted, also when the rate was changed after the line was saved, as before.
     */
    public function test_an_ordinary_euro_line_still_posts_at_the_rate_tables_rate(): void
    {
        $eur = $this->euro(746.00);
        [$expense, $inputVat] = self::$fx->vatPurchaseAccount();
        [$bank] = self::$fx->plainAccounts(1);

        // Saved while the rate was 746,00 (the save stores that rate on the line), posted after it became 750,00.
        $kladdeId = self::$fx->kladde([[
            'bilag' => 9, 'debet' => $expense, 'kredit' => $bank, 'amount' => 100.00,
            'valuta' => $eur, 'valutakurs' => 746.00, 'tekst' => 'chartest EUR expense, ordinary',
        ]]);
        $this->correctEuroRate($eur, 750.00);
        $this->post($kladdeId);
        $this->assertEquals([$expense => 600.00, $inputVat => 150.00, $bank => -750.00], $this->net([$kladdeId]));
    }

    public function test_tilbagefoer_of_a_euro_expense_reverses_it_at_the_lines_rate(): void
    {
        $eur = $this->euro(746.00);
        [$expense, $inputVat, $pct] = self::$fx->vatPurchaseAccount();
        [$bank] = self::$fx->plainAccounts(1);
        $this->assertSame(25.0, $pct, 'the purchase account carries 25 % input VAT');

        // 100 EUR at 746,00 = 746,00 DKK: 596,80 expense + 149,20 input VAT, paid from the bank.
        $original = self::$fx->kladde([[
            'bilag' => 7, 'debet' => $expense, 'kredit' => $bank, 'amount' => 100.00,
            'valuta' => $eur, 'valutakurs' => 746.00, 'tekst' => 'chartest EUR expense',
        ]]);
        $this->post($original);
        $this->assertEquals([$expense => 596.80, $inputVat => 149.20, $bank => -746.00], $this->net([$original]));

        $target = self::$fx->kladde([]);
        $this->journalPage('kladde_id=' . $original . '&ompost=' . $this->lineId($original) . '&ompost_til=' . $target);
        $this->post($target);
        $this->assertEquals([$expense => -596.80, $inputVat => -149.20, $bank => 746.00], $this->net([$target]));
        $this->assertEquals([$expense => 0.00, $inputVat => 0.00, $bank => 0.00], $this->net([$original, $target]), 'original and reversal cancel out');

        $line = self::one('SELECT debet, kredit, amount, valuta, valutakurs FROM kassekladde WHERE kladde_id = $1', [$target]);
        $this->assertSame([$bank, $expense], [(int)$line['debet'], (int)$line['kredit']], 'debit and credit are swapped');
        $this->assertEqualsWithDelta(100.00, (float)$line['amount'], 0.001, 'the amount stays in EUR');
        $this->assertSame($eur, (int)$line['valuta'], 'the reversed line keeps the currency EUR');
        $this->assertEqualsWithDelta(746.00, (float)$line['valutakurs'], 0.001, 'the reversed line keeps the rate');
    }

    public function test_tilbagefoer_of_a_euro_debtor_line_reverses_ledger_and_open_item_in_euro(): void
    {
        $eur = $this->euro(746.00);
        $debtor = self::$fx->debtor();
        $control = self::$fx->debtorCollectiveAccount();
        [$income] = self::$fx->plainAccounts(1);

        $original = self::$fx->kladde([[
            'bilag' => 20, 'd_type' => 'D', 'debet' => (int)$debtor['kontonr'], 'kredit' => $income, 'amount' => 100.00,
            'valuta' => $eur, 'valutakurs' => 746.00, 'momsfri' => 'on', 'faktura' => 'CHT-EUR-1', 'tekst' => 'chartest EUR invoice',
        ]]);
        $this->post($original);
        $this->assertEquals([$control => 746.00, $income => -746.00], $this->net([$original]));

        $target = self::$fx->kladde([]);
        $this->journalPage('kladde_id=' . $original . '&ompost=' . $this->lineId($original) . '&ompost_til=' . $target);
        $this->post($target);

        $this->assertEquals([$control => 0.00, $income => 0.00], $this->net([$original, $target]), 'control account and income cancel out');
        $items = self::rows('SELECT amount, valutakurs FROM openpost WHERE konto_id = $1 ORDER BY id', [$debtor['id']]);
        $this->assertEquals(
            [[100.0, 746.0], [-100.0, 746.0]],
            array_map(fn($r) => [round((float)$r['amount'], 2), round((float)$r['valutakurs'], 2)], $items),
            'the invoice and its reversal are both 100 EUR at 746,00, so the open items net to 0 DKK'
        );
    }

    public function test_reversed_copy_keeps_the_lines_vat_codes_and_cancels_the_original(): void
    {
        [$yCode, $yVat, $yCounter, $yPct] = $this->reverseChargeVat();
        [$service] = self::$fx->vatPurchaseAccount();
        [$bank] = self::$fx->plainAccounts(1);
        $vat = round(1000.00 * $yPct / 100, 2);

        // A service bought abroad: the account's own code is K, the line's Moms field is set to the reverse charge.
        $original = self::$fx->kladde([['bilag' => 30, 'debet' => $service, 'kredit' => $bank, 'amount' => 1000.00, 'tekst' => 'chartest EU service']]);
        self::rows("UPDATE kassekladde SET debetvat = $1, kreditvat = '' WHERE kladde_id = $2", [$yCode, $original]);
        $this->post($original);
        $this->assertEquals([$service => 1000.00, $yVat => -$vat, $yCounter => $vat, $bank => -1000.00], $this->net([$original]));

        $copy = $this->copyToNew($original, true);
        $this->post($copy);
        $this->assertEquals([$service => -1000.00, $yVat => $vat, $yCounter => -$vat, $bank => 1000.00], $this->net([$copy]));
        $this->assertEquals(
            [$service => 0.00, $yVat => 0.00, $yCounter => 0.00, $bank => 0.00],
            $this->net([$original, $copy]),
            'original and reversed copy cancel out'
        );

        $line = self::one('SELECT debet, kredit, debetvat, kreditvat FROM kassekladde WHERE kladde_id = $1', [$copy]);
        $this->assertSame([$bank, $service], [(int)$line['debet'], (int)$line['kredit']], 'Vend fortegn swaps debit and credit');
        $this->assertSame(['', $yCode], [$line['debetvat'], $line['kreditvat']], 'the VAT codes move with their accounts');
    }

    public function test_plain_copy_posts_the_same_vat_split_as_the_original(): void
    {
        [$yCode, $yVat, $yCounter, $yPct] = $this->reverseChargeVat();
        [$service] = self::$fx->vatPurchaseAccount();
        [$bank] = self::$fx->plainAccounts(1);
        $vat = round(500.00 * $yPct / 100, 2);

        $original = self::$fx->kladde([['bilag' => 31, 'debet' => $service, 'kredit' => $bank, 'amount' => 500.00, 'tekst' => 'chartest recurring']]);
        self::rows("UPDATE kassekladde SET debetvat = $1, kreditvat = '' WHERE kladde_id = $2", [$yCode, $original]);
        $this->post($original);
        $expected = [$service => 500.00, $yVat => -$vat, $yCounter => $vat, $bank => -500.00];
        $this->assertEquals($expected, $this->net([$original]));

        $copy = $this->copyToNew($original, false);
        $this->post($copy);
        $this->assertEquals($expected, $this->net([$copy]), 'the copy posts the same accounts and VAT as the original');

        $line = self::one('SELECT debetvat, kreditvat FROM kassekladde WHERE kladde_id = $1', [$copy]);
        $this->assertSame([$yCode, ''], [$line['debetvat'], $line['kreditvat']], 'the copy keeps the line VAT codes');
    }
}
