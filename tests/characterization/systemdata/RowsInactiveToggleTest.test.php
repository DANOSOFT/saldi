<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * A click into a rows table of a settings section throws no TypeError (testlab round 1, 2026-11-05).
 *
 * javascript/settingsRows.js handles every click in the form; one that is not on a switch, add, seed, trash or row
 * action button looks for the inaktiv eye with e.target.closest('[data-inactive]') and posts row_inactive for the eye's
 * row (closest('tr').dataset.row). includes/settings/rowsView.php also printed data-inactive on every table card
 * (div.st-card.st-rows, '1' or '0': whether the table has the inaktiv flag), so a click into a cell, a header or the
 * footer found the card, the card has no row, and reading tr.dataset threw "Cannot read properties of null (reading
 * 'dataset')" in the console (Moms » Moms af ydelseskøb i udlandet, clicking the Modkonto field of a new row).
 *
 * Pins: in a drawn table only the eye buttons carry data-inactive, each inside its row, so no cell, header, footer or
 * the card itself is taken for an eye; the card says whether the table has the inaktiv flag as data-can-inactive,
 * which the trash dialog reads to offer "Markér inaktiv". The database layer is a double that serves two VAT codes
 * of Moms af ydelseskøb i udlandet (one active, one inaktiv) and one row of Momsrapport, which has no inaktiv flag.
 *
 * History:
 * 20261105 CL/LH Created.
 */
#[RunTestsInSeparateProcesses]
final class RowsInactiveToggleTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /**
     * One table of Moms, drawn as settingsSection.php?s=finance.vat draws it.
     */
    private static function draw(string $tableId): DOMXPath
    {
        $GLOBALS['__rit_rows'] = [
            'YM' => [
                ['id' => '301', 'kodenr' => '1', 'beskrivelse' => 'Ydelseskøb EU', 'art' => 'YM', 'box1' => '66150', 'box2' => '25', 'box3' => '66160', 'fiscal_year' => '1', 'inaktiv' => 'f'],
                ['id' => '302', 'kodenr' => '2', 'beskrivelse' => 'Ydelseskøb udenfor EU', 'art' => 'YM', 'box1' => '66170', 'box2' => '25', 'box3' => '66180', 'fiscal_year' => '1', 'inaktiv' => 't'],
            ],
            'MR' => [
                ['id' => '401', 'kodenr' => '1', 'beskrivelse' => 'Momsrapport', 'art' => 'MR', 'box1' => '66100', 'fiscal_year' => '1'],
            ],
        ];
        eval('
            function db_escape_string($s) { return addslashes((string) $s); }
            function db_cast($expr, $type) { return "cast($expr as $type)"; }
            function db_select($q, $where = "") {
                $rows = [];
                if (preg_match("/^select \* from grupper where art = \'([A-Z]+)\'/", $q, $m) && isset($GLOBALS["__rit_rows"][$m[1]])) {
                    $rows = $GLOBALS["__rit_rows"][$m[1]];
                }
                return new \ArrayIterator($rows);
            }
            function db_fetch_array($r) {
                if (!$r->valid()) {
                    return false;
                }
                $row = $r->current();
                $r->next();
                return $row;
            }
            function db_modify($q, $where = "") { throw new \RuntimeException("drawing the VAT page wrote: " . $q); }
            function transaktion($what) { throw new \RuntimeException("drawing the VAT page started a write (" . $what . ")"); }
            function findtekst($key, $sprog_id = 1) { return explode("|", (string) $key, 2)[0]; }
            function perm_can($key, $level = "read") { return true; }
        ');
        require_once self::ROOT . '/includes/settings/components.php';
        require_once self::ROOT . '/includes/settings/rowsView.php';
        $t = settings_rows_tables(getSettingsSections()['finance.vat'])[$tableId];

        ob_start();
        settings_rows_table(['canWrite' => true, 'year' => 1, 'years' => [1], 'selfUrl' => 'settingsSection.php?s=finance.vat'], $tableId, $t, [], []);
        $html = (string) ob_get_clean();

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="root">' . $html . '</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new DOMXPath($doc);
    }

    /**
     * What javascript/settingsRows.js's e.target.closest('[data-inactive]') finds for a click on $el: $el itself or
     * its nearest ancestor with the attribute, or null.
     */
    private static function closestInactive(DOMNode $el): ?DOMElement
    {
        for ($n = $el; $n instanceof DOMElement; $n = $n->parentNode) {
            if ($n->hasAttribute('data-inactive')) {
                return $n;
            }
        }
        return null;
    }

    public function test_only_the_eye_buttons_carry_data_inactive_each_inside_its_row(): void
    {
        $x = self::draw('services');

        $eyes = [];
        foreach ($x->query('//*[@data-inactive]') as $el) {
            self::assertSame('button', $el->nodeName, 'only the inaktiv eye is a [data-inactive] target, not ' . $el->nodeName . '.' . $el->getAttribute('class'));
            $tr = $x->query('ancestor::tr[@data-row]', $el)->item(0);
            self::assertNotNull($tr, 'the eye sits in the row it marks, so closest(\'tr\').dataset.row is that row');
            $eyes[$tr->getAttribute('data-row')] = $el->getAttribute('data-inactive');
        }
        self::assertSame(['301' => '1', '302' => '0'], $eyes, 'the active code offers Markér inaktiv, the inaktiv one Markér aktiv');
    }

    public function test_a_click_into_a_cell_header_or_footer_finds_no_eye(): void
    {
        $x = self::draw('services');

        $card = $x->query('//div[contains(concat(" ", @class, " "), " st-rows ")]')->item(0);
        self::assertNotNull($card, 'the table card is drawn');
        $targets = [$card];
        foreach ($x->query('.//*[not(ancestor-or-self::button)]', $card) as $el) {
            $targets[] = $el;
        }
        self::assertGreaterThan(10, count($targets), 'cells, headers and the footer are drawn');
        foreach ($targets as $el) {
            self::assertNull(self::closestInactive($el), 'a click on ' . $el->nodeName . ' ' . ($el->getAttribute('id') ?: $el->getAttribute('class')) . ' is not taken for the inaktiv eye');
        }
    }

    public function test_the_card_says_whether_the_table_has_the_inaktiv_flag(): void
    {
        $services = self::draw('services')->query('//div[contains(concat(" ", @class, " "), " st-rows ")]')->item(0);
        self::assertSame('1', $services->getAttribute('data-can-inactive'), 'Moms af ydelseskøb i udlandet: trash on a used code offers Markér inaktiv');

        $js = (string) file_get_contents(self::ROOT . '/javascript/settingsRows.js');
        self::assertStringContainsString("card.dataset.canInactive === '1'", $js, 'the trash dialog reads the flag the card carries');
    }

    public function test_a_table_without_the_inaktiv_flag_has_no_eye(): void
    {
        $x = self::draw('report');

        self::assertSame(0, $x->query('//*[@data-inactive]')->length, 'Momsrapport has no inaktiv eye');
        self::assertSame('0', $x->query('//div[contains(concat(" ", @class, " "), " st-rows ")]')->item(0)->getAttribute('data-can-inactive'));
    }
}
