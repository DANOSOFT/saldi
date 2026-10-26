<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/docsIncludes/poolCapture.php';
require_once __DIR__ . '/../../finans/kassekladde_includes/contraSuggestion.php';

if (!function_exists('findtekst')) {
    // The text's Danish default, as for a text not yet in the database
    function findtekst($textId, $languageID)
    {
        $at = strpos((string)$textId, '|');
        return $at === false ? '#' . $textId : substr((string)$textId, $at + 1);
    }
}

/**
 * The parts of the data capture (SD-722) that decide without a database: the snapshot of what was read, which saved values count
 * as corrections, which fields are still "Aflæst", the e-mail to support, and which account the kreditor's history suggests.
 */
final class poolCapture extends TestCase
{
    private function serviceAnswer(): array
    {
        return [
            'status' => 'success',
            'id' => 'pool-faktura-1759500000',
            'extracted_data' => [
                'invoice_date' => '17.oktober.2025',
                'total_amount' => '1.250,00',
                'invoice_number' => ' F-1001 ',
                'currency' => 'kr.',
                'vendor' => 'Arla Foods amba',
                'vendor_vat_number' => 'DK25313763',
            ],
        ];
    }

    private function extracted(): array
    {
        return ['date' => '2025-10-17', 'amount' => '1.250,00', 'invoiceNumber' => ' F-1001 ', 'currency' => 'kr.', 'vendorCvr' => 'DK25313763'];
    }

    public function testSnapshotKeepsBothLayers(): void
    {
        $snapshot = poolCaptureSnapshot($this->extracted(), $this->serviceAnswer(), ['match' => 'cvr', 'kontonr' => '1002']);
        $this->assertSame(['raw' => '17.oktober.2025', 'norm' => '2025-10-17'], $snapshot['fields']['date']);
        $this->assertSame(['raw' => '1.250,00', 'norm' => '1250.00'], $snapshot['fields']['amount']);
        $this->assertSame(['raw' => 'F-1001', 'norm' => 'F-1001'], $snapshot['fields']['invoiceNumber']);
        $this->assertSame(['raw' => 'kr.', 'norm' => 'DKK'], $snapshot['fields']['currency']);
        $this->assertSame(['raw' => 'Arla Foods amba (CVR DK25313763)', 'norm' => '1002'], $snapshot['fields']['kreditor']);
        $this->assertSame('pool-faktura-1759500000', $snapshot['extractionId']);
        $this->assertSame('25313763', $snapshot['vendorCvr']);
        $this->assertSame('cvr', $snapshot['vendorMatch']);
    }

    public function testNoKreditorWithoutAConfidentMatch(): void
    {
        $this->assertSame('', poolCaptureSnapshot($this->extracted(), $this->serviceAnswer(), ['match' => 'ambiguous', 'kontonr' => null])['fields']['kreditor']['norm']);
        $this->assertSame('', poolCaptureSnapshot($this->extracted(), $this->serviceAnswer(), null)['fields']['kreditor']['norm']);
    }

    public function testUblSnapshotTakesTheXmlValuesAsRaw(): void
    {
        $values = ['date' => '2026-09-01', 'amount' => '500.00', 'invoiceNumber' => '77', 'currency' => 'EUR', 'vendor' => 'Leverandør ApS', 'vendorCvr' => '12345678'];
        $snapshot = poolCaptureSnapshot($values, ['source' => 'ubl', 'values' => $values], null);
        $this->assertSame(['raw' => '500.00', 'norm' => '500.00'], $snapshot['fields']['amount']);
        $this->assertSame('Leverandør ApS (CVR 12345678)', $snapshot['fields']['kreditor']['raw']);
        $this->assertSame('', $snapshot['extractionId']);
    }

    public function testNothingChangedGivesNoCorrection(): void
    {
        $snapshot = poolCaptureSnapshot($this->extracted(), $this->serviceAnswer(), ['match' => 'cvr', 'kontonr' => '1002']);
        // As poolCaptureFinalValues() reads the journal line: transdate, amount without sign, faktura, valuta code or '' for DKK, kreditor number
        $this->assertSame([], poolCaptureDiff($snapshot, ['date' => '2025-10-17', 'amount' => '1250.00', 'invoiceNumber' => 'F-1001', 'currency' => '', 'kreditor' => '1002']));
    }

    public function testCorrectedAmountGivesOneRecord(): void
    {
        $snapshot = poolCaptureSnapshot($this->extracted(), $this->serviceAnswer(), ['match' => 'cvr', 'kontonr' => '1002']);
        $diff = poolCaptureDiff($snapshot, ['date' => '2025-10-17', 'amount' => '1520.00', 'invoiceNumber' => 'F-1001', 'currency' => 'DKK', 'kreditor' => '1002']);
        $this->assertSame([['field' => 'amount', 'raw' => '1.250,00', 'norm' => '1250.00', 'final' => '1520.00']], $diff);
    }

    public function testAMissedFieldTheUserFilledInIsACorrection(): void
    {
        $answer = $this->serviceAnswer();
        unset($answer['extracted_data']['invoice_number']);
        $result = $this->extracted();
        unset($result['invoiceNumber']);
        $diff = poolCaptureDiff(poolCaptureSnapshot($result, $answer, null), ['invoiceNumber' => 'F-1001', 'kreditor' => '1002']);
        $this->assertSame(['invoiceNumber', 'kreditor'], array_column($diff, 'field'));
        $this->assertSame('', $diff[0]['raw']);
    }

    public function testCreditNoteSignAndKPrefixAreNotCorrections(): void
    {
        $snapshot = poolCaptureSnapshot(['amount' => '-200,00'], null, ['match' => 'bank', 'kontonr' => '1002']);
        $this->assertSame([], poolCaptureDiff($snapshot, ['amount' => '200', 'kreditor' => 'K1002']));
    }

    public function testFieldsStillAsRead(): void
    {
        $snapshot = poolCaptureSnapshot($this->extracted(), $this->serviceAnswer(), ['match' => 'cvr', 'kontonr' => '1002']);
        $now = ['date' => '2025-10-17 00:00:00', 'amount' => '1250', 'invoiceNumber' => 'F-1002', 'currency' => 'DKK', 'kreditor' => '1002'];
        $this->assertSame(['date', 'amount', 'currency', 'kreditor'], poolCaptureFieldsShown($now, $snapshot, true));
    }

    public function testWithoutSnapshotUntilSomeoneCorrectedIt(): void
    {
        $now = ['date' => '2025-10-17', 'amount' => '', 'invoiceNumber' => 'F-1', 'currency' => '', 'kreditor' => ''];
        $this->assertSame(['date', 'invoiceNumber'], poolCaptureFieldsShown($now, null, false));
        $this->assertSame([], poolCaptureFieldsShown($now, null, true));
    }

    public function testMailSubjectAndBody(): void
    {
        $mail = poolCaptureReportMailContent([
            'company' => 'Chartest ApS',
            'db' => 'saldi_chartest',
            'user' => 'admin',
            'time' => '2026-10-03 21:00:00',
            'filename' => 'arla.pdf',
            'hash' => str_repeat('a', 64),
            'extractionId' => 'pool-arla-1',
            'kreditor' => "1002 Arla\nFoods",
            'fields' => [['field' => 'amount', 'raw' => '1.250,00', 'norm' => '1250.00', 'final' => '1520.00']],
            'comment' => "Beløbet er <forkert>\nSe side 2",
        ], ['amount' => 'Beløb', 'comment' => 'Kommentar']);
        $this->assertSame('Aflæsningsfejl: Chartest ApS – 1002 Arla Foods', $mail['subject']);
        $this->assertStringContainsString('<td>Beløb</td><td>1.250,00</td><td>1250.00</td><td>1520.00</td>', $mail['html']);
        $this->assertStringContainsString('Beløbet er &lt;forkert&gt;<br />', $mail['html']);
        $this->assertStringContainsString('pool-arla-1', $mail['html']);
        $this->assertStringContainsString("Beløb: 1.250,00 | 1250.00 | 1520.00\n", $mail['text']);
        $this->assertStringContainsString("Felt: Fra tjenesten | Efter Saldi | Korrekt\n", $mail['text']);
    }

    public function testMailSubjectFallsBackToTheFilename(): void
    {
        $mail = poolCaptureReportMailContent(['company' => 'Chartest ApS', 'filename' => 'scan_7.pdf', 'fields' => []], []);
        $this->assertSame('Aflæsningsfejl: Chartest ApS – scan_7.pdf', $mail['subject']);
        $this->assertStringNotContainsString('extractionId', $mail['html']);
    }

    public function testDanishDisplayOfDateAndAmount(): void
    {
        $this->assertSame('17-10-2025', poolCaptureDisplay('date', '2025-10-17'));
        $this->assertSame('3.858,39', poolCaptureDisplay('amount', '3858.39'));
        $this->assertSame('F-1001', poolCaptureDisplay('invoiceNumber', 'F-1001'));
        $this->assertSame('', poolCaptureDisplay('date', ''));
    }

    public function testRetryWaitsLongerEachTime(): void
    {
        $this->assertSame('2026-10-03 12:02:00', poolCaptureNextAttempt(1, strtotime('2026-10-03 12:00:00')));
        $this->assertSame('2026-10-03 12:16:00', poolCaptureNextAttempt(4, strtotime('2026-10-03 12:00:00')));
    }

    public function testSaldiServerSendsAsTheDatabaseWithTheCompanyInReplyTo(): void
    {
        $this->assertSame(
            ['from' => 'test_12@ssl12.saldi.dk', 'replyTo' => 'info@havemoebelland.dk'],
            poolCaptureMailSender('info@havemoebelland.dk', '', 'test_12', 'ssl12.saldi.dk')
        );
        $this->assertSame(
            ['from' => 'test_12@ssl12.saldi.dk', 'replyTo' => 'sz@danosoft.dk'],
            poolCaptureMailSender('info@havemoebelland.dk', 'sz@danosoft.dk', 'test_12', 'SSL12.saldi.dk')
        );
    }

    public function testOtherServersKeepTheCompanyAsSender(): void
    {
        $keep = ['from' => 'info@firma.dk', 'replyTo' => ''];
        $this->assertSame($keep, poolCaptureMailSender('info@firma.dk', '', 'saldi_5', 'regnskab.firma.dk'));
        $this->assertSame($keep, poolCaptureMailSender('info@firma.dk', '', 'saldi_5', 'saldi.dk.example.com'));
        $this->assertSame($keep, poolCaptureMailSender('info@firma.dk', '', 'saldi_5', ''));
        $this->assertSame($keep, poolCaptureMailSender('info@firma.dk', '', 'saldi_5', "ssl12.saldi.dk\n"));
        $this->assertSame($keep, poolCaptureMailSender('info@firma.dk', '', 'bad-db;', 'ssl12.saldi.dk'));
    }

    public function testSuggestionIsTheAccountMostBilagWentTo(): void
    {
        $rows = [
            ['bilag' => 14, 'dato' => '01-11-2026', 'kontonr' => 2810, 'art' => 'F', 'tekst' => 'Arla nov'],
            ['bilag' => 12, 'dato' => '01-10-2026', 'kontonr' => 2800, 'art' => 'F', 'tekst' => 'Arla okt'],
            ['bilag' => 12, 'dato' => '01-10-2026', 'kontonr' => 2800, 'art' => 'F', 'tekst' => 'Arla okt (fordeling)'],
            ['bilag' => 9, 'dato' => '01-09-2026', 'kontonr' => 2800, 'art' => 'F', 'tekst' => 'Arla sep'],
            ['bilag' => 3, 'dato' => '01-07-2026', 'kontonr' => 2800, 'art' => 'F', 'tekst' => 'Arla jul'],
        ];
        // The newest bilag went to 2810, but three of the four went to 2800; a bilag on two lines counts once
        $this->assertSame(['kontonr' => '2800', 'tekst' => 'Arla okt', 'count' => 3], contraSuggestionPick($rows));
    }

    public function testTheOtherAccountsAreRankedByCountThenNewest(): void
    {
        $rank = contraSuggestionRank([
            ['bilag' => 20, 'dato' => '05-10-2026', 'kontonr' => 7100, 'art' => 'F', 'tekst' => 'Papir'],
            ['bilag' => 19, 'dato' => '04-10-2026', 'kontonr' => 15011, 'art' => 'F', 'tekst' => 'Internet'],
            ['bilag' => 18, 'dato' => '03-10-2026', 'kontonr' => 15020, 'art' => 'F', 'tekst' => 'Telefon'],
            ['bilag' => 17, 'dato' => '02-10-2026', 'kontonr' => 15011, 'art' => 'F', 'tekst' => 'Internet sep'],
            ['bilag' => 16, 'dato' => '01-10-2026', 'kontonr' => 1002, 'art' => 'D', 'tekst' => 'Modregning'],
        ]);
        $this->assertSame(5, $rank['total']);
        $this->assertSame(['15011', '7100', '15020'], array_column($rank['accounts'], 'kontonr'));
        $this->assertSame([2, 1, 1], array_column($rank['accounts'], 'count'));
    }

    public function testABilagSplitOverTwoAccountsCountsForBoth(): void
    {
        $rank = contraSuggestionRank([
            ['bilag' => 8, 'dato' => '01-10-2026', 'kontonr' => 4000, 'art' => 'F', 'tekst' => 'Husleje'],
            ['bilag' => 8, 'dato' => '01-10-2026', 'kontonr' => 4010, 'art' => 'F', 'tekst' => 'Varme'],
            ['bilag' => 7, 'dato' => '01-09-2026', 'kontonr' => 4000, 'art' => 'F', 'tekst' => 'Husleje'],
        ]);
        $this->assertSame(2, $rank['total']);
        $this->assertSame([['kontonr' => '4000', 'tekst' => 'Husleje', 'count' => 2], ['kontonr' => '4010', 'tekst' => 'Varme', 'count' => 1]], $rank['accounts']);
    }

    public function testFiveBilagAreFiveBilagNotFiveLines(): void
    {
        $rows = [
            ['kladde_id' => 30, 'bilag' => 9, 'dato' => '09-10-2026', 'kontonr' => 2800, 'art' => 'F', 'tekst' => 'B'],
            ['kladde_id' => 30, 'bilag' => 8, 'dato' => '08-10-2026', 'kontonr' => 2700, 'art' => 'F', 'tekst' => 'A 1/3'],
            ['kladde_id' => 30, 'bilag' => 8, 'dato' => '08-10-2026', 'kontonr' => 2700, 'art' => 'F', 'tekst' => 'A 2/3'],
            ['kladde_id' => 30, 'bilag' => 8, 'dato' => '08-10-2026', 'kontonr' => 2700, 'art' => 'F', 'tekst' => 'A 3/3'],
            ['kladde_id' => 30, 'bilag' => 7, 'dato' => '07-10-2026', 'kontonr' => 2700, 'art' => 'F', 'tekst' => 'A'],
            // Same number and date in two journals: two bilag
            ['kladde_id' => 21, 'bilag' => 70, 'dato' => '01-10-2026', 'kontonr' => 2800, 'art' => 'F', 'tekst' => 'B'],
            ['kladde_id' => 22, 'bilag' => 70, 'dato' => '01-10-2026', 'kontonr' => 2800, 'art' => 'F', 'tekst' => 'B'],
            // The sixth bilag is not looked at
            ['kladde_id' => 20, 'bilag' => 60, 'dato' => '01-09-2026', 'kontonr' => 2700, 'art' => 'F', 'tekst' => 'A'],
        ];
        $rank = contraSuggestionRank($rows);
        $this->assertSame(5, $rank['total']);
        $this->assertSame(['2800', '2700'], array_column($rank['accounts'], 'kontonr'));
        $this->assertSame([3, 2], array_column($rank['accounts'], 'count'));
    }

    public function testAChosenBilagsLaterRowsStillCount(): void
    {
        // Same date, ordered by row id: bilag 1 and 2 have a second line after the sixth bilag's first
        $row = function ($bilag, $konto) {
            return ['kladde_id' => 30, 'bilag' => $bilag, 'dato' => '01-10-2026', 'kontonr' => $konto, 'art' => 'F', 'tekst' => ''];
        };
        $rank = contraSuggestionRank([$row(1, 1000), $row(2, 1000), $row(3, 2000), $row(4, 2000), $row(5, 3000), $row(6, 4000), $row(1, 2000), $row(2, 2000)]);
        $this->assertSame(5, $rank['total']);
        $this->assertSame(['2000', '1000', '3000'], array_column($rank['accounts'], 'kontonr'));
        $this->assertSame([4, 2, 1], array_column($rank['accounts'], 'count'));
    }

    public function testTheReasonSaysHowManyOfTheBilag(): void
    {
        $this->assertSame('Foreslået fordi de sidste 5 bilag fra denne kreditor blev bogført på konto 15011', contraSuggestionReason(5, '15011', 1, 5));
        $this->assertSame('Foreslået fordi 3 af de sidste 5 bilag fra denne kreditor blev bogført på konto 15011', contraSuggestionReason(3, '15011', 1, 5));
        $this->assertSame('Foreslået fordi det seneste bilag fra denne kreditor blev bogført på konto 15011', contraSuggestionReason(1, '15011', 1, 1));
        $this->assertSame('Foreslået fordi de sidste 2 bilag fra denne kreditor blev bogført på konto 2800', contraSuggestionReason(2, '2800', 1));
    }

    public function testOnlyFinanceAccountsAreSuggested(): void
    {
        $this->assertNull(contraSuggestionPick([]));
        $this->assertNull(contraSuggestionPick([['bilag' => 1, 'dato' => '01-10-2026', 'kontonr' => 1002, 'art' => 'K', 'tekst' => '']]));
        $pick = contraSuggestionPick([
            ['bilag' => 2, 'dato' => '02-10-2026', 'kontonr' => 1002, 'art' => 'D', 'tekst' => ''],
            ['bilag' => 1, 'dato' => '01-10-2026', 'kontonr' => 4000, 'art' => 'F', 'tekst' => 'Husleje'],
        ]);
        $this->assertSame(['kontonr' => '4000', 'tekst' => 'Husleje', 'count' => 1], $pick);
    }
}
