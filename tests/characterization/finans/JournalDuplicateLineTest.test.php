<?php
// tests/characterization/finans/JournalDuplicateLineTest.test.php
//
// The duplicate button on a line of an open cash journal (POST action=duplicate_line to finans/kassekladde.php,
// finans/kassekladde_includes/duplicate_line.php) copies the line within the journal.
//
// Pins: the copy keeps the line's own VAT codes (debetvat/kreditvat), so a line whose VAT code differs from its
// account's code posts the same VAT split twice (the defect bookkeeping F3 found in "Kopier til ny"), and the
// journal can be posted with the copy in it (an empty employee id made finans/bogfor.php throw a TypeError).
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261117 CL/ASR Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class JournalDuplicateLineTest extends CharacterizationTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // finans/kassekladde.php adds the line VAT columns on its first load (ensure_cash_vat_columns()).
        self::rows('ALTER TABLE kassekladde ADD COLUMN IF NOT EXISTS debetvat varchar');
        self::rows('ALTER TABLE kassekladde ADD COLUMN IF NOT EXISTS kreditvat varchar');
    }

    public function test_duplicated_line_keeps_its_vat_codes_and_posts_the_same_split(): void
    {
        $grp = self::one(
            "SELECT kodenr, box1, box2, box3 FROM grupper
             WHERE art = 'YM' AND fiscal_year = $1 AND box1 <> '' AND box2 <> '' AND box3 <> '' ORDER BY kodenr LIMIT 1",
            [self::$regnaar]
        );
        $this->assertNotNull($grp, 'the installed tenant has a reverse-charge VAT group (grupper YM)');
        [$yCode, $yVat, $yCounter] = ['Y' . (int)$grp['kodenr'], (int)$grp['box1'], (int)$grp['box3']];
        $vat = round(400.00 * (float)$grp['box2'] / 100, 2);
        [$service] = self::$fx->vatPurchaseAccount();
        [$bank] = self::$fx->plainAccounts(1);

        // The account's own code is K; the line's Moms field is set to the reverse charge.
        $kladdeId = self::$fx->kladde([['bilag' => 40, 'debet' => $service, 'kredit' => $bank, 'amount' => 400.00, 'tekst' => 'chartest duplicate']]);
        self::rows("UPDATE kassekladde SET debetvat = $1, kreditvat = '' WHERE kladde_id = $2", [$yCode, $kladdeId]);
        $sourceId = (int)self::one('SELECT id FROM kassekladde WHERE kladde_id = $1', [$kladdeId])['id'];

        $htmlFile = (string)tempnam(sys_get_temp_dir(), 'chartest-kassekladde-');
        try {
            $res = self::runChild('run_kassekladde_page.php', [
                "kladde_id=$kladdeId", CharacterizationEnv::sessionId(), $htmlFile,
                http_build_query(['action' => 'duplicate_line', 'kladde_id' => $kladdeId, 'source_id' => $sourceId]),
            ]);
            // The page has already buffered its HTML head when duplicate_line.php answers, so the JSON is
            // picked out of the response.
            $answer = preg_match('/\{"success":[^{}]*\}/', (string)file_get_contents($htmlFile), $m) ? json_decode($m[0], true) : null;
        } finally {
            @unlink($htmlFile);
        }
        $this->assertSame(0, $res['exit'], $res['stderr'] . $res['stdout']);
        $this->assertIsArray($answer, 'the duplicate request answers with JSON');
        $this->assertTrue($answer['success'] ?? false, json_encode($answer));

        $res = self::runChild('run_bogfor_page.php', ['bogfor', $kladdeId, CharacterizationEnv::sessionId()]);
        $this->assertSame(0, $res['exit'], $res['stderr'] . $res['stdout']);
        $net = [];
        foreach (self::rows(
            'SELECT kontonr, SUM(COALESCE(debet, 0) - COALESCE(kredit, 0)) AS net FROM transaktioner WHERE kladde_id = $1 GROUP BY kontonr',
            [$kladdeId]
        ) as $r) {
            $net[(int)$r['kontonr']] = round((float)$r['net'], 2);
        }
        $this->assertEquals(
            [$service => 800.00, $yVat => -2 * $vat, $yCounter => 2 * $vat, $bank => -800.00],
            $net,
            'the line and its duplicate post the same reverse-charge split'
        );

        $copy = self::one('SELECT debetvat, kreditvat FROM kassekladde WHERE id = $1', [(int)$answer['new_id']]);
        $this->assertSame([$yCode, ''], [$copy['debetvat'], $copy['kreditvat']], 'the duplicate keeps the line VAT codes');
    }
}
