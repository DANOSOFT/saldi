<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Indstillinger » Varer » Varegrupper shows Lagerført as SALDI reads it (testlab round 1, 2026-11-05).
 *
 * The rows editor read a bool cell as ticked when it was not empty and not the column's false_value. The REST API
 * used to store a product group's inventory true as box8 = '1', and SALDI's stock and order code only treat
 * box8 = 'on' as stock-managed (lager/lagerstatus.php, includes/ordrefunc.php), as the old syssetup.php page only
 * ticked 'on'. So Varegrupper showed such a group as Lagerført while it was not, and ticking it again stored
 * nothing, because '1' and the posted value counted as the same.
 *
 * Pins: Lagerført and the other options are ticked only for 'on'; a '1' is shown unticked and kept while the user
 * leaves it alone; ticking it stores 'on'; batch control on such a group asks for Lagerført first; a currency's
 * "Vis i kassen" (true_value '1') is ticked only for '1'; the standard set seeds bool cells as '1' or ''. The
 * database layer is a double that serves three product groups and records every write.
 *
 * History:
 * 20261105 CL/LH Created.
 */
#[RunTestsInSeparateProcesses]
final class ItemGroupStockFlagTest extends TestCase
{
    private const YEAR = 4;

    /** grupper VG rows: a stock group with batch control, the group the lab created through the API, a service group. */
    private const GROUPS = [
        ['id' => '171', 'kodenr' => '2', 'beskrivelse' => 'Handelsvarer', 'art' => 'VG', 'fiscal_year' => '4', 'box1' => '', 'box2' => '',
            'box3' => '1610', 'box4' => '1010', 'box5' => '', 'box6' => '', 'box7' => '', 'box8' => 'on', 'box9' => 'on', 'box10' => '',
            'box11' => '', 'box12' => '', 'box13' => '', 'box14' => '', 'inaktiv' => 'f'],
        ['id' => '174', 'kodenr' => '7', 'beskrivelse' => 'AI-API-MS Varegruppe Ærø', 'art' => 'VG', 'fiscal_year' => '4', 'box1' => null, 'box2' => null,
            'box3' => '1000', 'box4' => '1100', 'box5' => null, 'box6' => '', 'box7' => '', 'box8' => '1', 'box9' => '', 'box10' => '',
            'box11' => '1200', 'box12' => '1220', 'box13' => '', 'box14' => '1270', 'inaktiv' => 'f'],
        ['id' => '175', 'kodenr' => '8', 'beskrivelse' => 'Ydelser', 'art' => 'VG', 'fiscal_year' => '4', 'box1' => '', 'box2' => '',
            'box3' => '1660', 'box4' => '1010', 'box5' => '', 'box6' => '', 'box7' => '', 'box8' => '', 'box9' => '', 'box10' => '',
            'box11' => '', 'box12' => '', 'box13' => '', 'box14' => '', 'inaktiv' => 'f'],
    ];

    /**
     * @return array<string, mixed> the Varegrupper table as settingsSection.php builds it
     */
    private static function boot(): array
    {
        $GLOBALS['__ig_groups'] = self::GROUPS;
        $GLOBALS['__ig_writes'] = [];
        $GLOBALS['regnaar'] = self::YEAR;
        eval('
            function db_escape_string($s) { return addslashes((string) $s); }
            function db_cast($expr, $type) { return "cast($expr as $type)"; }
            function db_select($q, $where = "") {
                $rows = [];
                if (strpos($q, "select * from grupper where art = \'VG\' and fiscal_year = 4") === 0) {
                    $rows = $GLOBALS["__ig_groups"];
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
            function db_modify($q, $where = "") { $GLOBALS["__ig_writes"][] = $q; }
            function transaktion($what) { }
            function findtekst($key, $sprog_id = 1) { return explode("|", (string) $key, 2)[0]; }
            function perm_can($key, $level = "read") { return true; }
        ');
        require_once dirname(__DIR__, 3) . '/includes/settings/components.php';
        require_once dirname(__DIR__, 3) . '/includes/settings/rowsView.php';
        return settings_rows_tables(getSettingsSections()['items.item_groups'])['groups'];
    }

    /**
     * The option checkboxes (box6..box10) of each stored group: whether the browser ticks them and their data-orig.
     *
     * @return array<int, array<string, array{checked: bool, orig: string}>> row id => column => checkbox
     */
    private static function optionCells(array $t): array
    {
        ob_start();
        settings_rows_table(['canWrite' => true, 'year' => self::YEAR, 'years' => [self::YEAR], 'selfUrl' => 'settingsSection.php?s=items.item_groups'], 'groups', $t, [], []);
        $html = (string) ob_get_clean();
        preg_match_all('#<input type="checkbox" class="st-rcheck" id="c-groups-(\d+)-(box\d+)"([^>]*)>#', $html, $boxes, PREG_SET_ORDER);
        $cells = [];
        foreach ($boxes as [, $rowId, $col, $attrs]) {
            $orig = preg_match('#data-orig="([^"]*)"#', $attrs, $m) ? html_entity_decode($m[1]) : null;
            $cells[(int) $rowId][$col] = ['checked' => (bool) preg_match('#\schecked\b#', $attrs), 'orig' => (string) $orig];
        }
        return $cells;
    }

    /**
     * The page posted back as the browser shows it: every cell's stored value, a ticked checkbox as '1', an unticked one
     * as the hidden ''. $changes overrides cells (row id => column => posted value).
     *
     * @return array{r: array{groups: array<string, array<string, string>>}}
     */
    private static function post(array $t, array $cells, array $changes = []): array
    {
        $post = ['r' => ['groups' => []]];
        foreach (self::GROUPS as $g) {
            foreach ($t['columns'] as $col => $def) {
                if ($def['type'] === 'bool') {
                    $post['r']['groups'][$g['id']][$col] = $cells[(int) $g['id']][$col]['checked'] ? '1' : '';
                } else {
                    $post['r']['groups'][$g['id']][$col] = settings_rows_form_value($def, (string) $g[$col]);
                }
            }
        }
        foreach ($changes as $rowId => $cols) {
            foreach ($cols as $col => $v) {
                $post['r']['groups'][(string) $rowId][$col] = $v;
            }
        }
        return $post;
    }

    public function test_lagerfoert_is_ticked_only_for_a_group_stored_as_on(): void
    {
        $cells = self::optionCells(self::boot());

        self::assertSame([171, 174, 175], array_keys($cells), 'five option checkboxes per group');
        self::assertTrue($cells[171]['box8']['checked'], "box8 = 'on' is Lagerført");
        self::assertTrue($cells[171]['box9']['checked'], "box9 = 'on' is Batchkontrol");
        self::assertFalse($cells[174]['box8']['checked'], "box8 = '1' is not stock-managed in SALDI, so it is not shown ticked");
        self::assertSame('', $cells[174]['box8']['orig'], 'its baseline is unticked, so opening the page counts no change');
        self::assertFalse($cells[175]['box8']['checked']);
        foreach ([174, 175] as $rowId) {
            foreach (['box6', 'box7', 'box9', 'box10'] as $col) {
                self::assertFalse($cells[$rowId][$col]['checked'], "group $rowId $col is empty");
            }
        }
    }

    public function test_saving_the_page_as_it_was_opened_writes_nothing(): void
    {
        $t = self::boot();
        $post = self::post($t, self::optionCells($t));

        $result = settings_rows_save('items.item_groups', ['groups' => $t], $post, self::YEAR, false);

        self::assertSame([], $result['errors']);
        self::assertSame([], $GLOBALS['__ig_writes'], "a '1' the user left alone is kept as it is");
    }

    public function test_ticking_lagerfoert_on_a_group_stored_as_1_stores_on(): void
    {
        $t = self::boot();
        $post = self::post($t, self::optionCells($t), [174 => ['box8' => '1']]);

        $result = settings_rows_save('items.item_groups', ['groups' => $t], $post, self::YEAR, false);

        self::assertSame([], $result['errors']);
        self::assertContains("update grupper set box8 = 'on' where id = 174 and art = 'VG'", $GLOBALS['__ig_writes'], 'the group becomes stock-managed as SALDI reads it');
        $updates = array_values(array_filter($GLOBALS['__ig_writes'], fn ($q) => strpos($q, 'update grupper') === 0));
        self::assertCount(1, $updates, 'only that cell is written');
    }

    public function test_batch_control_on_a_group_stored_as_1_asks_for_lagerfoert_first(): void
    {
        $t = self::boot();
        $post = self::post($t, self::optionCells($t), [174 => ['box9' => '1']]);

        $result = settings_rows_save('items.item_groups', ['groups' => $t], $post, self::YEAR, false);

        self::assertSame(['groups/174/box9' => 6498], $result['errors'], "batch control needs Lagerført, and box8 = '1' is not");
        self::assertSame([], $GLOBALS['__ig_writes']);
    }

    public function test_a_column_with_its_own_true_value_is_ticked_only_for_it(): void
    {
        self::boot();
        $def = settings_rows_tables(getSettingsSections()['finance.currencies'])['currencies']['columns']['box4'];

        self::assertSame('1', settings_rows_form_value($def, '1'), "Vis i kassen: the till lists a currency for box4 = '1'");
        self::assertSame('', settings_rows_form_value($def, '0'));
        self::assertSame('', settings_rows_form_value($def, "1000\t2000"), "an old till account list is not the flag the till reads");
        self::assertTrue(settings_rows_same($def, "1000\t2000", '0'), 'and left unticked it counts as unchanged, so the list is kept');
    }

    public function test_the_standard_set_seeds_bool_cells_as_the_editor_shows_them(): void
    {
        $t = self::boot();
        $standard = settings_rows_standard($t);

        self::assertNotSame([], $standard, 'importfiler/grupper.txt has product groups');
        foreach ($standard as $row) {
            foreach (['box6', 'box7', 'box8', 'box9', 'box10'] as $col) {
                self::assertContains($row[$col], ['1', ''], "standard group {$row['kodenr']} $col");
            }
        }
        $byCode = array_column($standard, null, 'kodenr');
        self::assertSame('1', $byCode['2']['box8'], "Handelsvarer is Lagerført ('on' in the groups file)");
        self::assertSame('', $byCode['1']['box8'], 'Ydelser is not');
    }
}
