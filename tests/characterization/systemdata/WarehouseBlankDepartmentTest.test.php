<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Indstillinger » Varer » Lagre opens unchanged when a warehouse has no department (testlab round 1, 2026-11-05).
 *
 * The Afd. select offered the department list (st_options 'departments'), whose blank choice is '0', as a till and an
 * employee store "no department". A warehouse stores it as '' (grupper LG box1, a text field on the old lagre page;
 * the REST API leaves it NULL), and '0' there is department 0. The cell's data-orig was '' while no option had that
 * value, so the browser selected '0' and javascript/settingsRows.js counted "1 ugemt ændring" on a look-only visit,
 * asked before every navigation, and Gem wrote box1 = '0', which warehouses_to_departments and the order pages'
 * box1 = afd lookups read as department 0.
 *
 * Pins: the warehouse's Afd. select offers '' as its blank choice and every department, department 0 included, as a
 * choice of its own; every warehouse's Afd. cell holds the option the browser shows (data-orig equals the selected
 * option, or the first one when none is marked); posting the page back as it was opened writes nothing; a blank Afd.
 * is stored as '', department 0 as '0'. The tills' and employees' list keeps '0' as its blank choice. The database
 * layer is a double that serves the departments, four warehouses (box1 NULL, '', '2' and '0'), one till and one
 * employee, and refuses any write.
 *
 * History:
 * 20261105 CL/LH Created.
 */
#[RunTestsInSeparateProcesses]
final class WarehouseBlankDepartmentTest extends TestCase
{
    private const WITH_DEPARTMENT_0 = [
        ['kodenr' => '0', 'beskrivelse' => 'Central'],
        ['kodenr' => '1', 'beskrivelse' => 'Butik'],
        ['kodenr' => '2', 'beskrivelse' => 'Lager Nord'],
    ];

    /**
     * @param list<array{kodenr: string, beskrivelse: string}> $departments grupper AFD rows
     * @return array<string, mixed> the warehouses table as settingsSection.php builds it
     */
    private static function boot(array $departments = self::WITH_DEPARTMENT_0): array
    {
        $GLOBALS['__wh_departments'] = $departments;
        $GLOBALS['__wh_warehouses'] = [
            ['id' => '175', 'kodenr' => '2', 'beskrivelse' => 'Lager Ærøskøbing', 'art' => 'LG', 'box1' => null, 'inaktiv' => 'f'],
            ['id' => '176', 'kodenr' => '3', 'beskrivelse' => 'Lager uden afd.', 'art' => 'LG', 'box1' => '', 'inaktiv' => 'f'],
            ['id' => '177', 'kodenr' => '4', 'beskrivelse' => 'Lager Nord', 'art' => 'LG', 'box1' => '2', 'inaktiv' => 'f'],
            ['id' => '178', 'kodenr' => '5', 'beskrivelse' => 'Centrallager', 'art' => 'LG', 'box1' => '0', 'inaktiv' => 'f'],
        ];
        eval('
            function db_escape_string($s) { return addslashes((string) $s); }
            function db_cast($expr, $type) { return "cast($expr as $type)"; }
            function db_select($q, $where = "") {
                $rows = [];
                if (strpos($q, "select kodenr, beskrivelse from grupper where art = \'AFD\'") === 0) {
                    $rows = $GLOBALS["__wh_departments"];
                } elseif (strpos($q, "select * from grupper where art = \'LG\'") === 0) {
                    $rows = $GLOBALS["__wh_warehouses"];
                } elseif (strpos($q, "select box1 from grupper where art = \'POS\' and kodenr = \'1\'") === 0) {
                    $rows = [["box1" => "1"]];
                } elseif (strpos($q, "select a.id, a.navn, a.nummer") === 0) {
                    $rows = [["id" => "7", "navn" => "Ib", "nummer" => "1", "lukket" => "", "uid" => null]];
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
            function db_modify($q, $where = "") { throw new \RuntimeException("the warehouses page wrote: " . $q); }
            function transaktion($what) { throw new \RuntimeException("the warehouses page started a write (" . $what . ")"); }
            function findtekst($key, $sprog_id = 1) { return explode("|", (string) $key, 2)[0]; }
            function perm_can($key, $level = "read") { return true; }
        ');
        require_once dirname(__DIR__, 3) . '/includes/settings/components.php';
        require_once dirname(__DIR__, 3) . '/includes/settings/rowsView.php';
        return settings_rows_tables(getSettingsSections()['items.warehouses'])['warehouses'];
    }

    /**
     * The Afd. cell of each stored warehouse: its data-orig, the value the browser selects and the options offered.
     *
     * @return array<int, array{orig: string, shown: string, options: array<string, string>}> row id => cell
     */
    private static function departmentCells(array $t): array
    {
        ob_start();
        settings_rows_table(['canWrite' => true, 'year' => null, 'years' => [], 'selfUrl' => 'settingsSection.php?s=items.warehouses'], 'warehouses', $t, [], []);
        $html = (string) ob_get_clean();
        preg_match_all('#<select class="st-rin" id="c-warehouses-(\d+)-box1"[^>]*\bdata-orig="([^"]*)"[^>]*>(.*?)</select>#s', $html, $selects, PREG_SET_ORDER);
        $cells = [];
        foreach ($selects as [, $rowId, $orig, $options]) {
            preg_match_all('#<option value="([^"]*)"( selected)?>([^<]*)</option>#', $options, $opts, PREG_SET_ORDER);
            $shown = $opts ? $opts[0][1] : '';
            $offered = [];
            foreach ($opts as $opt) {
                if ($opt[2] !== '') {
                    $shown = $opt[1];
                }
                $offered[html_entity_decode($opt[1])] = html_entity_decode($opt[3]);
            }
            $cells[$rowId] = ['orig' => html_entity_decode($orig), 'shown' => html_entity_decode($shown), 'options' => $offered];
        }
        return $cells;
    }

    public function test_every_warehouse_opens_with_the_department_it_has(): void
    {
        $cells = self::departmentCells(self::boot());

        self::assertSame([175, 176, 177, 178], array_keys($cells), 'an Afd. select per warehouse');
        foreach ($cells as $rowId => $cell) {
            self::assertSame($cell['orig'], $cell['shown'], "warehouse $rowId: the browser shows the stored department, so opening the page counts no change");
            self::assertSame(['' => '', '0' => 'Central', '1' => 'Butik', '2' => 'Lager Nord'], $cell['options'], "warehouse $rowId: a blank choice and every department, 0 included");
        }
        self::assertSame('', $cells[175]['shown'], 'NULL is the blank choice');
        self::assertSame('', $cells[176]['shown'], "'' is the blank choice");
        self::assertSame('2', $cells[177]['shown'], 'a department stays chosen');
        self::assertSame('0', $cells[178]['shown'], 'department 0 stays chosen, not shown as blank');
    }

    public function test_without_a_department_0_a_blank_warehouse_still_opens_unchanged(): void
    {
        $cells = self::departmentCells(self::boot([['kodenr' => '1', 'beskrivelse' => 'Butik']]));

        foreach ([175, 176] as $rowId) {
            self::assertSame('', $cells[$rowId]['orig']);
            self::assertSame('', $cells[$rowId]['shown'], "warehouse $rowId: the blank choice, not the till's '0'");
        }
        self::assertSame(['' => '', '1' => 'Butik'], $cells[176]['options']);
    }

    public function test_saving_the_page_as_it_was_opened_writes_nothing(): void
    {
        $t = self::boot();
        $cells = self::departmentCells($t);
        $post = ['r' => ['warehouses' => []]];
        foreach ($GLOBALS['__wh_warehouses'] as $w) {
            $post['r']['warehouses'][$w['id']] = ['kodenr' => $w['kodenr'], 'beskrivelse' => $w['beskrivelse'], 'box1' => $cells[$w['id']]['shown']];
        }

        $result = settings_rows_save('items.warehouses', ['warehouses' => $t], $post, null, false);

        self::assertSame([], $result['errors']);
    }

    public function test_a_blank_department_is_stored_as_before_and_a_chosen_one_as_its_number(): void
    {
        $def = self::boot()['columns']['box1'];

        self::assertSame(['raw' => '', 'error' => null], settings_rows_to_raw($def, '', null), 'no department, as the old lagre page stored it');
        self::assertSame(['raw' => '0', 'error' => null], settings_rows_to_raw($def, '0', null), 'department 0 is kept');
        self::assertSame(['raw' => '2', 'error' => null], settings_rows_to_raw($def, '2', null));
        self::assertSame('', settings_rows_form_value($def, ''));
        self::assertSame('0', settings_rows_form_value($def, '0'));
    }

    public function test_the_tills_and_employees_keep_0_as_no_department(): void
    {
        self::boot([['kodenr' => '1', 'beskrivelse' => 'Butik']]);
        $defs = getSettingDefinitions();

        foreach (['pos.tills.department.1', 'organisation.employees.afd.7'] as $key) {
            self::assertSame([0 => '', 1 => 'Butik'], st_options($defs[$key]), "$key: '0' is no department");
        }
        self::assertSame([0 => '', 1 => 'Butik'], st_options(['options_from' => 'departments']), 'the integrations pages read the same list');
    }

    public function test_a_till_with_a_department_0_lists_it_as_before(): void
    {
        self::boot();

        self::assertSame([0 => 'Central', 1 => 'Butik', 2 => 'Lager Nord'], st_options(getSettingDefinitions()['pos.tills.department.1']));
    }
}
