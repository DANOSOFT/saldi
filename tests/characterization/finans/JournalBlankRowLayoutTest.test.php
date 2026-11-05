<?php
// 20261105 CDX/NTR Render the journal's supplementary blank rows and verify approval-column alignment.

namespace JournalBlankRowsFixture;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Supply a minimal VAT control so the real row renderer needs no database/bootstrap.
 *
 * @param string $name Field name supplied by the journal renderer.
 * @param string $selected Selected code, unused for empty fixture rows.
 * @param array $codes Available codes, unused for layout assertions.
 * @param string $charset Output encoding.
 * @return string VAT select preserving the production field name.
 */
function render_vat_select($name, $selected, $codes, $charset) {
    return '<select name="' . htmlspecialchars($name, ENT_QUOTES, $charset) . '"><option></option></select>';
}

final class JournalBlankRowLayoutTest extends TestCase {
    /**
     * Cover optional leading and trailing columns without relying on tenant display settings.
     *
     * @return array<string, array{0: bool, 1: bool, 2: bool}> Approval, attachment, and optional columns enabled.
     */
    public static function columnConfigurations(): array {
        $cases = [];
        foreach ([false, true] as $approval) {
            foreach ([false, true] as $attachments) {
                foreach ([false, true] as $optional) {
                    $cases["approval=$approval attachments=$attachments optional=$optional"] = [$approval, $attachments, $optional];
                }
            }
        }
        return $cases;
    }

    /**
     * Render the actual supplementary-row loop, which previously omitted the approval cell.
     *
     * @param bool $approval Whether the editable view renders the approval column.
     * @param bool $attachments Whether the attachment column precedes approval.
     * @param bool $optional Whether department, employee, project, currency, and balance columns appear.
     * @return string Seven blank rows as emitted by kassekladde.php.
     */
    private function renderRows(bool $approval, bool $attachments, bool $optional): string {
        $source = file_get_contents(dirname(__DIR__, 3) . '/finans/kassekladde.php');
        $start = strpos($source, 'for ($z = $x; $z <= $y; $z++) {');
        self::assertNotFalse($start, 'Supplementary blank-row renderer must exist.');
        $end = strpos($source, "\n\t#\tif (count(\$bilag)<10)", $start);
        self::assertNotFalse($end, 'Blank-row renderer boundary must exist.');
        $x = 4;
        $y = 10;
        $kk_godkendt_col = $approval;
        $vis_bilag = $attachments;
        $vis_afd = $vis_ansat = $vis_projekt = $vis_valuta = $vis_forfald = $vis_bet_id = $optional;
        $control_bal_fetched = $kontrolkonto = $kontrolsaldo = $saldoDiff = $optional;
        $fejl = false;
        $de_fok = '';
        $vat_codes = [];
        $charset = 'UTF-8';
        ob_start();
        try {
            eval('namespace ' . __NAMESPACE__ . '; ' . substr($source, $start, $end - $start));
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Every extra blank row must retain the voucher and amount columns' positions.
     *
     * @param bool $approval Whether an empty approval cell is required.
     * @param bool $attachments Whether a leading attachment cell is required.
     * @param bool $optional Whether optional trailing controls are rendered.
     */
    #[DataProvider('columnConfigurations')]
    public function testBlankRowsKeepTheirFieldColumns(bool $approval, bool $attachments, bool $optional): void {
        $document = new DOMDocument();
        $document->loadHTML('<table>' . $this->renderRows($approval, $attachments, $optional) . '</table>');
        $xpath = new DOMXPath($document);
        $rows = $xpath->query('//tr');
        self::assertCount(7, $rows);
        $fields = ['bila', 'dato', 'besk', 'd_ty', 'debe', 'dvat', 'k_ty', 'kred', 'kvat', 'fakt', 'belo'];
        foreach ($rows as $index => $row) {
            $cells = $xpath->query('./td', $row);
            $approvalCells = $xpath->query('./td[@class="kk-col-godkendt"]', $row);
            self::assertCount($approval ? 1 : 0, $approvalCells, 'Each blank row needs the same leading columns.');
            if ($approval) {
                self::assertSame(0, $approvalCells->item(0)->childNodes->length, 'An unsaved row cannot be approved.');
            }
            $offset = (int)$approval + (int)$attachments;
            foreach ($fields as $column => $prefix) {
                $fieldName = $prefix . ($index + 4);
                self::assertCount(1, $xpath->query('.//*[@name="' . $fieldName . '"]', $cells->item($column + $offset)), "$fieldName shifted away from its header column.");
            }
        }
    }
}
