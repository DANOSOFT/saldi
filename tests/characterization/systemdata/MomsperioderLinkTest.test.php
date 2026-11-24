<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Indstillinger » Virksomhed » Regnskabsår keeps master's Momsperioder button (testlab round 1, 2026-11-05).
 *
 * Master moved the link to finans/moms_periode.php (SD-646, open and close VAT periods) off the Finans sidebar and
 * onto systemdata/regnskabsaar.php. That page became a redirect to the settings section company.fiscal_years,
 * which had no such link, so the sidebar design could no longer reach the page.
 *
 * Pins: the section links to the page; the rows view shows the link to whoever may open the page (finans.kassekladde,
 * the page's $modulnr 2), read-only too and also when the section itself is read-only; anyone else gets neither the
 * link nor its heading. The database layer is a double without rows (one test lets it answer one row of each kind the
 * registry expands fields for, see oneOfEach()); perm_can() answers from the level a test sets.
 *
 * Also pins the old menu path the settings search shows and matches (systemdata/settingsSearch.php reads it for every
 * readable field): a field's 'legacy' is one path, a flat list of text ids, and only a section lists several paths;
 * this holds for the fields expanded per till, card, table, floor plan, pickup address, price list and form language
 * too. Momsperioder's path is Regnskabsår → Momsperioder and reads without a warning.
 *
 * History:
 * 20261105 CL/LH Created.
 * 20261116 CL/ASR Integration: every field's old menu path, the fields expanded per till, card, table, floor plan, pickup address, price list and form language included, is a flat list of text ids (sections list paths), and Momsperioder's path reads as the texts 778 and 3366 joined, without a warning (testlab R1).
 */
#[RunTestsInSeparateProcesses]
final class MomsperioderLinkTest extends TestCase
{
    private const KEY = 'company.fiscal_years.vat_periods';

    /**
     * @return array<string, array<string, mixed>> the definitions of the section, as settingsSection.php reads them
     */
    private static function boot(string $kassekladde, bool $oneOfEach = false): array
    {
        $GLOBALS['__perm'] = ['settings.company' => 'write', 'finans.kassekladde' => $kassekladde];
        $GLOBALS['__oneOfEach'] = $oneOfEach;
        eval('
            function db_escape_string($s) { return addslashes((string) $s); }
            function db_select($q, $where = "") {
                return (object) ["rows" => $GLOBALS["__oneOfEach"] ? MomsperioderLinkTest::oneOfEach((string) $q) : []];
            }
            function db_fetch_array($r) { return is_object($r) && $r->rows ? array_shift($r->rows) : false; }
            function db_modify($q, $where = "") { return true; }
            function findtekst($key, $sprog_id = 1) { return explode("|", (string) $key, 2)[0]; }
            function perm_can($key, $level = "read") {
                $rank = ["none" => 0, "read" => 1, "write" => 2];
                return $rank[$GLOBALS["__perm"][$key] ?? "none"] >= $rank[$level];
            }
        ');
        require_once dirname(__DIR__, 3) . '/includes/settings/components.php';
        require_once dirname(__DIR__, 3) . '/includes/settings/rowsView.php';
        return settings_section_definitions('company.fiscal_years');
    }

    /**
     * The rows of a company with one of each thing getSettingDefinitions() expands fields for, so the expanded fields
     * exist: one fiscal year, a warehouse, one extra employee field, a till currency, one KDS colour, one employee with a
     * user, one pickup address, one price list, one till with one card, one table, one floor plan and one form language.
     * Any other query has no rows.
     *
     * @return list<array<int|string, mixed>>
     */
    public static function oneOfEach(string $query): array
    {
        $rows = [
            "art = 'RA'" => ['kodenr' => '1', 'beskrivelse' => '2026'],
            "art = 'LG'" => ['id' => 1],
            "art = 'DIV' and kodenr = '2'" => ['id' => 1],
            "art = 'ANSAT' and kodenr = '0'" => ['kode' => '0', 'box1' => 'text'] + array_fill_keys(array_map(static fn (int $b): string => 'box' . $b, range(2, 14)), ''),
            "art = 'VK'" => ['kodenr' => '2', 'box1' => 'EUR'],
            "var_grp = 'KDS'" => ['n' => 1],
            "from adresser where art = 'S'" => ['id' => 1],
            'from ansatte' => ['id' => 1, 'navn' => 'Ansat', 'nummer' => '1', 'lukket' => '', 'uid' => 1],
            "var_grp = 'DFM_Pickup'" => ['group_id' => 1, 'var_name' => 'dfm_pickup_name1', 'var_value' => 'Afhentning'],
            "art = 'PL'" => ['id' => 1, 'beskrivelse' => 'Prisliste'],
            "art = 'POS' and kodenr = '1'" => ['box1' => '1', 'box5' => 'Dankort', 'box9' => ''],
            "art = 'POS' and kodenr = '2'" => ['box7' => 'Bord 1'],
            'information_schema.tables' => ['table_name' => 'table_pages'],
            'from table_pages' => ['id' => 1, 'name' => 'Plan'],
            "art = 'VSPR'" => ['kodenr' => '1', 'box1' => 'English'],
        ];
        foreach ($rows as $needle => $row) {
            if (str_contains($query, $needle)) {
                return [$row];
            }
        }
        return [];
    }

    /**
     * @param array<string, mixed> $def
     */
    private static function render(array $def, bool $sectionReadonly): string
    {
        ob_start();
        st_render_field($def, [
            'value' => '', 'original' => '', 'error' => null, 'readonly' => $sectionReadonly, 'locked' => 0,
            'visible' => st_visible($def, []), 'mine' => false, 'set_at' => '',
        ]);
        return (string) ob_get_clean();
    }

    public function test_the_fiscal_years_section_links_to_the_vat_periods_page(): void
    {
        $defs = self::boot('write');

        self::assertSame(3366, getSettingsSections()['company.fiscal_years']['subsections']['vat_periods'] ?? null, 'a Momsperioder sub-section');
        self::assertArrayHasKey(self::KEY, $defs);
        $def = $defs[self::KEY];
        self::assertSame('link', $def['type']);
        self::assertSame('vat_periods', $def['sub']);
        self::assertSame('../finans/moms_periode.php', $def['href']);
        self::assertFileExists(dirname(__DIR__, 3) . '/systemdata/' . $def['href'], 'the link resolves from systemdata/settingsSection.php');
        self::assertSame('finans.kassekladde', $def['permission'], 'gated like the page it opens ($modulnr 2)');
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function mayOpen(): array
    {
        return [
            'write' => ['write', false],
            'read' => ['read', false],
            'write, section read-only' => ['write', true],
            'read, section read-only' => ['read', true],
        ];
    }

    #[DataProvider('mayOpen')]
    public function test_a_user_who_may_open_the_page_gets_a_live_link(string $kassekladde, bool $sectionReadonly): void
    {
        $defs = self::boot($kassekladde);

        self::assertSame([self::KEY], array_keys(settings_rows_fields_by_sub($defs)['vat_periods'] ?? []));
        $html = self::render($defs[self::KEY], $sectionReadonly);
        self::assertStringContainsString('<a class="st-btn" href="../finans/moms_periode.php">', $html);
        self::assertStringNotContainsString('aria-disabled', $html);
    }

    public function test_a_user_without_kassekladde_gets_neither_the_link_nor_its_heading(): void
    {
        $defs = self::boot('none');

        self::assertArrayNotHasKey('vat_periods', settings_rows_fields_by_sub($defs), 'no empty Momsperioder heading');
        self::assertSame('', self::render($defs[self::KEY], false));
    }

    /**
     * settings_legacy_text() and settings_legacy_all_languages() take one path, a flat list of text ids. A field has one
     * path; a section lists several (hub.php and settingsSearch.php walk them one by one).
     *
     * Fields kept per till, card, table, floor plan, pickup address, employee, price list or form language exist only
     * for the company's rows and take their 'legacy' from the definition they are expanded from, so the database
     * double answers one row of each here; every kind of expansion in the registry is then walked at least once.
     */
    public function test_every_field_names_its_old_path_as_a_flat_list_of_text_ids(): void
    {
        self::boot('write', true);

        $checked = [];
        $kinds = [];
        foreach (getSettingDefinitions() as $key => $def) {
            if (isset($def['per'])) {
                self::assertArrayHasKey('per_key', $def, $key . ': expanded from the company\'s rows');
                $kinds[$def['per']] = true;
            }
            if (!isset($def['legacy'])) {
                continue;
            }
            self::assertIsArray($def['legacy'], $key);
            self::assertNotSame([], $def['legacy'], $key);
            self::assertTrue(array_is_list($def['legacy']), $key . ': a list');
            foreach ($def['legacy'] as $id) {
                self::assertIsInt($id, $key . ': a text id, not a nested path');
            }
            $checked[] = $key;
        }
        self::assertGreaterThan(250, count($checked), 'the registry\'s fields, the expanded ones included, were checked');

        $written = self::kindsWrittenInTheRegistry();
        self::assertNotSame([], $written);
        self::assertSame([], array_values(array_diff($written, array_keys($kinds))), 'every kind of expansion in the registry has a row here');
        $expanded = [
            'pos.tills.department.1', 'pos.tills.currency_interim_2.1', 'pos.cards.name.1', 'pos.tables.name.1',
            'pos.tables.plan_name.1', 'integrations.pickup.name1.1', 'purchase.pricelists.description.1', 'sales.reminders.fee_item_1.1',
        ];
        foreach ($expanded as $key) {
            self::assertContains($key, $checked, $key . ': an expanded field, checked like the others');
        }

        foreach (getSettingsSections() as $id => $section) {
            foreach ($section['legacy'] ?? [] as $path) {
                self::assertIsArray($path, $id . ': a section lists paths');
                self::assertTrue(array_is_list($path), $id . ': a path is a list');
                foreach ($path as $textId) {
                    self::assertIsInt($textId, $id . ': a text id');
                }
            }
        }
    }

    /**
     * The kinds the registry's code writes as 'per' => '<kind>', read from the PHP tokens of
     * systemdata/settingsDefinitions.php. Comments are tokens of their own and never match, so the history block, which
     * keeps the names a kind had before, does not count.
     *
     * @return list<string>
     */
    private static function kindsWrittenInTheRegistry(): array
    {
        $code = array_values(array_filter(
            token_get_all((string) file_get_contents(dirname(__DIR__, 3) . '/systemdata/settingsDefinitions.php')),
            static fn ($t): bool => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ));
        $kinds = [];
        for ($i = 0, $n = count($code) - 2; $i < $n; $i++) {
            if (is_array($code[$i]) && $code[$i][0] === T_CONSTANT_ENCAPSED_STRING && substr($code[$i][1], 1, -1) === 'per'
                && is_array($code[$i + 1]) && $code[$i + 1][0] === T_DOUBLE_ARROW
                && is_array($code[$i + 2]) && $code[$i + 2][0] === T_CONSTANT_ENCAPSED_STRING) {
                $kinds[substr($code[$i + 2][1], 1, -1)] = true;
            }
        }
        return array_keys($kinds);
    }

    public function test_the_vat_periods_old_path_is_the_fiscal_year_then_vat_periods(): void
    {
        $def = self::boot('write')[self::KEY];

        $warnings = [];
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        });
        try {
            $text = settings_legacy_text($def['legacy'], 1);
            $all = settings_legacy_all_languages($def['legacy']);
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $warnings, 'the path reads without a warning');
        self::assertSame([778, 3366], $def['legacy'], 'Regnskabsår, then Momsperioder');
        self::assertSame(findtekst('778', 1) . ' → ' . findtekst('3366', 1), $text, 'the "Tidligere: ..." tag');
        self::assertSame(' ' . settings_text_all_languages(778) . ' ' . settings_text_all_languages(3366), $all);
        self::assertStringContainsString('regnskabsår', $all, 'found by its old names in every language');
        self::assertStringContainsString('momsperioder', $all);
    }
}
