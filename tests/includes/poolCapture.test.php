<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/docsIncludes/poolCapture.php';
require_once __DIR__ . '/../../finans/kassekladde_includes/contraSuggestion.php';

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

    public function testSuggestionIsTheNewestAccountAndItsRun(): void
    {
        $rows = [
            ['bilag' => 12, 'dato' => '01-10-2026', 'kontonr' => 2800, 'art' => 'F', 'tekst' => 'Arla okt'],
            ['bilag' => 12, 'dato' => '01-10-2026', 'kontonr' => 2800, 'art' => 'F', 'tekst' => 'Arla okt (fordeling)'],
            ['bilag' => 9, 'dato' => '01-09-2026', 'kontonr' => 2800, 'art' => 'F', 'tekst' => 'Arla sep'],
            ['bilag' => 5, 'dato' => '01-08-2026', 'kontonr' => 2810, 'art' => 'F', 'tekst' => 'Arla aug'],
            ['bilag' => 3, 'dato' => '01-07-2026', 'kontonr' => 2800, 'art' => 'F', 'tekst' => 'Arla jul'],
        ];
        $this->assertSame(['kontonr' => '2800', 'tekst' => 'Arla okt', 'count' => 2], contraSuggestionPick($rows));
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
