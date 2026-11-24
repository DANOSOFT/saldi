<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Indstillinger » Indkøb » Kreditorgrupper has master's Sprog column (testlab round 2, chart-vat-group-setup, 2026-11-05).
 *
 * master's Debitor-/kreditorgrupper page (systemdata/syssetup.php?valg=debitor) showed a Sprog column for creditor
 * groups, "Det sprog, der skal anvendes ved kommunikation med kreditoren" (text 2461), stored in grupper KG box4 and
 * checked against the form languages (sprogtjek()). The settings redesign moved the page to
 * settingsSection.php?s=purchase.creditor_groups and left box4 out of its columns, while Debitorgrupper kept it. A new
 * supplier group was then stored with box4 NULL where master stores the language typed (the lab: KG 177 NULL on the
 * candidate, 'Dansk' on base), and so were the groups of "Opret dansk standardsæt" and of a copy from an earlier year,
 * which only write the table's columns. Baggrunde counts suppliers by their group's box4 (includes/settings/rows.php).
 *
 * Pins: Kreditorgrupper has a Sprog column between Valuta and Modkonto, a select of the form languages like
 * Debitorgrupper's with master's help text; every group opens with its stored language, also a value typed into master's
 * free-text field that the list does not offer; posting the page back unchanged writes nothing; a new group stores the
 * language chosen and a language without forms is refused; the Danish standard set and a copy from an earlier year carry
 * box4. The database layer is a double that serves four creditor groups, one extra form language and the accounts, and
 * records every write.
 *
 * History:
 * 20261105 CL/LH Created.
 */
#[RunTestsInSeparateProcesses]
final class CreditorGroupLanguageTest extends TestCase
{
    private const YEAR = 4;

    /** grupper KG rows of year 4: Danish, English, no language, and a language typed in lower case on master's page. */
    private const GROUPS = [
        ['id' => '156', 'kodenr' => '1', 'beskrivelse' => 'Danske leverandører', 'art' => 'KG', 'kode' => 'K', 'fiscal_year' => '4', 'box1' => 'K1',
            'box2' => '65100', 'box3' => 'DKK', 'box4' => 'Dansk', 'box5' => '58000', 'box6' => '', 'box7' => null, 'box8' => null, 'box9' => '',
            'box10' => '', 'inaktiv' => 'f'],
        ['id' => '157', 'kodenr' => '2', 'beskrivelse' => 'Engelske leverandører', 'art' => 'KG', 'kode' => 'K', 'fiscal_year' => '4', 'box1' => 'K1',
            'box2' => '65100', 'box3' => 'EUR', 'box4' => 'English', 'box5' => '', 'box6' => '', 'box7' => null, 'box8' => null, 'box9' => '',
            'box10' => 'B2B-EU', 'inaktiv' => 'f'],
        ['id' => '158', 'kodenr' => '3', 'beskrivelse' => 'Uden sprog', 'art' => 'KG', 'kode' => 'K', 'fiscal_year' => '4', 'box1' => 'K1',
            'box2' => '65100', 'box3' => 'DKK', 'box4' => null, 'box5' => '', 'box6' => '', 'box7' => null, 'box8' => null, 'box9' => '',
            'box10' => '', 'inaktiv' => 'f'],
        ['id' => '159', 'kodenr' => '4', 'beskrivelse' => 'Gamle leverandører', 'art' => 'KG', 'kode' => 'K', 'fiscal_year' => '4', 'box1' => 'K1',
            'box2' => '65100', 'box3' => 'DKK', 'box4' => 'dansk', 'box5' => '', 'box6' => '', 'box7' => null, 'box8' => null, 'box9' => '',
            'box10' => '', 'inaktiv' => 'f'],
    ];

    /**
     * @return array<string, mixed> the Kreditorgrupper table as settingsSection.php builds it
     */
    private static function boot(): array
    {
        $GLOBALS['__kg_groups'] = self::GROUPS;
        $GLOBALS['__kg_writes'] = [];
        $GLOBALS['regnaar'] = self::YEAR;
        eval('
            function db_escape_string($s) { return addslashes((string) $s); }
            function db_cast($expr, $type) { return "cast($expr as $type)"; }
            function db_select($q, $where = "") {
                $rows = [];
                if (strpos($q, "select * from grupper where art = \'KG\' and fiscal_year = 4 ") === 0) {
                    $rows = $GLOBALS["__kg_groups"];
                } elseif (strpos($q, "select kodenr, box1 from grupper where art = \'VSPR\'") === 0) {
                    $rows = [["kodenr" => "1", "box1" => "English"]];
                } elseif (strpos($q, "select kode, kodenr, beskrivelse from grupper where art in (\'KM\', \'EM\', \'YM\')") === 0) {
                    $rows = [["kode" => "K", "kodenr" => "1", "beskrivelse" => "Købsmoms"]];
                } elseif (strpos($q, "select box1 from grupper where art = \'VK\'") === 0) {
                    $rows = [["box1" => "EUR"]];
                } elseif (preg_match("/^select kontotype from kontoplan where kontonr = \'(65100|58000)\' and regnskabsaar = 4$/", $q)) {
                    $rows = [["kontotype" => "S"]];
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
            function db_modify($q, $where = "") { $GLOBALS["__kg_writes"][] = $q; }
            function transaktion($what) { }
            function findtekst($key, $sprog_id = 1) { return explode("|", (string) $key, 2)[0]; }
            function perm_can($key, $level = "read") { return true; }
        ');
        require_once dirname(__DIR__, 3) . '/includes/settings/components.php';
        require_once dirname(__DIR__, 3) . '/includes/settings/rowsView.php';
        return settings_rows_tables(getSettingsSections()['purchase.creditor_groups'])['groups'];
    }

    /**
     * The page as the browser gets it.
     */
    private static function render(array $t): string
    {
        ob_start();
        settings_rows_table(['canWrite' => true, 'year' => self::YEAR, 'years' => [self::YEAR], 'selfUrl' => 'settingsSection.php?s=purchase.creditor_groups'], 'groups', $t, [], []);
        return (string) ob_get_clean();
    }

    /**
     * The Sprog cell of each stored group: its name, data-orig, the value the browser selects and the options offered.
     *
     * @return array<int, array{name: string, orig: string, shown: string, options: array<string, string>}> row id => cell
     */
    private static function languageCells(string $html): array
    {
        preg_match_all('#<select class="st-rin" id="c-groups-(\d+)-box4" name="([^"]*)" data-orig="([^"]*)"[^>]*>(.*?)</select>#s', $html, $selects, PREG_SET_ORDER);
        $cells = [];
        foreach ($selects as [, $rowId, $name, $orig, $options]) {
            preg_match_all('#<option value="([^"]*)"( selected)?>([^<]*)</option>#', $options, $opts, PREG_SET_ORDER);
            $shown = $opts ? $opts[0][1] : '';
            $offered = [];
            foreach ($opts as $opt) {
                if ($opt[2] !== '') {
                    $shown = $opt[1];
                }
                $offered[html_entity_decode($opt[1])] = html_entity_decode($opt[3]);
            }
            $cells[(int) $rowId] = ['name' => html_entity_decode($name), 'orig' => html_entity_decode($orig), 'shown' => html_entity_decode($shown), 'options' => $offered];
        }
        return $cells;
    }

    /**
     * The page posted back as it was opened: every stored group's cells as the editor shows them. $rows adds rows or
     * overrides cells (row id => column => posted value).
     *
     * @return array{r: array{groups: array<string, array<string, string>>}}
     */
    private static function post(array $t, array $rows = []): array
    {
        $post = ['r' => ['groups' => []]];
        foreach (self::GROUPS as $g) {
            foreach ($t['columns'] as $col => $def) {
                $post['r']['groups'][$g['id']][$col] = settings_rows_form_value($def, (string) $g[$col]);
            }
        }
        foreach ($rows as $rowId => $cols) {
            foreach ($cols as $col => $v) {
                $post['r']['groups'][(string) $rowId][$col] = $v;
            }
        }
        return $post;
    }

    /**
     * The inserts the save wrote, as column => value (quotes taken off).
     *
     * @return list<array<string, string>>
     */
    private static function inserts(): array
    {
        $out = [];
        foreach ($GLOBALS['__kg_writes'] as $q) {
            if (preg_match('#^insert into grupper \((.*?)\) values \((.*)\)$#', $q, $m)) {
                $vals = array_map(fn ($v) => trim($v, "'"), explode(', ', $m[2]));
                $out[] = array_combine(explode(', ', $m[1]), $vals);
            }
        }
        return $out;
    }

    public function test_kreditorgrupper_has_a_sprog_column_between_valuta_and_modkonto(): void
    {
        $t = self::boot();
        $debtor = settings_rows_tables(getSettingsSections()['sales.debtor_groups'])['groups']['columns']['box4'];

        $cols = array_keys($t['columns']);
        self::assertContains('box4', $cols, 'KG box4 is the supplier group language, as on master');
        self::assertSame(array_search('box3', $cols, true) + 1, array_search('box4', $cols, true), 'after Valuta, as on master');
        self::assertSame(array_search('box4', $cols, true) + 1, array_search('box5', $cols, true), 'before Modkonto, as on master');
        $box4 = $t['columns']['box4'];
        self::assertSame(801, $box4['label'], 'Sprog');
        self::assertSame(2461, $box4['help'], "master's help: Det sprog, der skal anvendes ved kommunikation med kreditoren");
        foreach (['type', 'options_from', 'options_literal'] as $key) {
            self::assertSame($debtor[$key], $box4[$key], "the same $key as Debitorgrupper's Sprog");
        }
        self::assertMatchesRegularExpression('#<th scope="col" class="st-rc-select" title="2461">801</th>#', self::render($t), 'the column heading');
    }

    public function test_each_group_opens_with_the_language_it_has(): void
    {
        $cells = self::languageCells(self::render(self::boot()));

        self::assertSame([156, 157, 158, 159], array_keys($cells), 'a Sprog select per group');
        foreach ($cells as $rowId => $cell) {
            self::assertSame("r[groups][$rowId][box4]", $cell['name']);
            self::assertSame($cell['orig'], $cell['shown'], "group $rowId: the browser shows the stored language, so opening the page counts no change");
        }
        self::assertSame(['' => '', 'Dansk' => 'Dansk', 'English' => 'English'], $cells[156]['options'], 'a blank choice and every form language');
        self::assertSame('Dansk', $cells[156]['shown']);
        self::assertSame('English', $cells[157]['shown']);
        self::assertSame('', $cells[158]['shown'], 'NULL is the blank choice');
        self::assertSame('dansk', $cells[159]['shown'], "a language typed on master's page stays as it was stored");
    }

    public function test_saving_the_page_as_it_was_opened_writes_nothing_and_a_changed_language_writes_only_that_cell(): void
    {
        $t = self::boot();

        $result = settings_rows_save('purchase.creditor_groups', ['groups' => $t], self::post($t), self::YEAR, false);
        self::assertSame([], $result['errors']);
        self::assertSame([], $GLOBALS['__kg_writes'], 'every group keeps its language, the lower-case one too');

        $result = settings_rows_save('purchase.creditor_groups', ['groups' => $t], self::post($t, [158 => ['box4' => 'English']]), self::YEAR, false);
        self::assertSame([], $result['errors']);
        $updates = array_values(array_filter($GLOBALS['__kg_writes'], fn ($q) => strpos($q, 'update grupper') === 0));
        self::assertSame(["update grupper set box4 = 'English' where id = 158 and art = 'KG'"], $updates);
    }

    public function test_a_new_group_stores_the_language_chosen(): void
    {
        $t = self::boot();
        $new = ['kodenr' => '5', 'beskrivelse' => 'TL KG', 'box1' => 'K1', 'box2' => '65100', 'box3' => 'DKK', 'box4' => 'Dansk', 'box5' => '58000',
            'box6' => '', 'box9' => '', 'box10' => ''];

        $result = settings_rows_save('purchase.creditor_groups', ['groups' => $t], ['r' => ['groups' => ['n1' => $new]]], self::YEAR, false);

        self::assertSame([], $result['errors']);
        $inserts = self::inserts();
        self::assertCount(1, $inserts);
        self::assertSame('Dansk', $inserts[0]['box4'] ?? null, "the lab's KG 177: master stores 'Dansk', the redesign stored NULL");
        self::assertSame('KG', $inserts[0]['art']);
        self::assertSame('4', $inserts[0]['fiscal_year']);
    }

    public function test_a_language_without_forms_is_refused(): void
    {
        $t = self::boot();
        $new = ['kodenr' => '5', 'beskrivelse' => 'TL KG', 'box1' => 'K1', 'box2' => '65100', 'box3' => 'DKK', 'box4' => 'Tysk', 'box5' => '',
            'box6' => '', 'box9' => '', 'box10' => ''];

        $result = settings_rows_save('purchase.creditor_groups', ['groups' => $t], ['r' => ['groups' => ['n1' => $new]]], self::YEAR, false);

        self::assertSame(['groups/n1/box4' => 5719], $result['errors'], "master's sprogtjek(): no form has that language");
        self::assertSame([], $GLOBALS['__kg_writes']);
    }

    public function test_the_danish_standard_set_and_a_copy_from_an_earlier_year_keep_the_language(): void
    {
        $t = self::boot();

        $standard = array_column(settings_rows_standard($t), null, 'kodenr');
        self::assertSame('Dansk', $standard['1']['box4'] ?? null, "importfiler/grupper.txt's Danske Leverandører, as admin/opret.php creates it");

        $result = settings_rows_copy_year('purchase.creditor_groups', 'groups', $t, self::YEAR, self::YEAR + 1);
        self::assertSame('ok', $result[0]);
        $copies = array_values(array_filter($GLOBALS['__kg_writes'], fn ($q) => strpos($q, 'insert into grupper') === 0));
        self::assertCount(1, $copies);
        self::assertMatchesRegularExpression('#^insert into grupper \(([a-z0-9_]+, )*box4, .*\) select ([a-z0-9_]+, )*box4, .* from grupper where art = \'KG\' and fiscal_year = 4$#', $copies[0], 'year 5 gets the groups with their language');
    }
}
