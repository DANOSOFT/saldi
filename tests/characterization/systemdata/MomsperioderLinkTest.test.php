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
 * link nor its heading. The database layer is a double without rows; perm_can() answers from the level a test sets.
 *
 * History:
 * 20261105 CL/LH Created.
 */
#[RunTestsInSeparateProcesses]
final class MomsperioderLinkTest extends TestCase
{
    private const KEY = 'company.fiscal_years.vat_periods';

    /**
     * @return array<string, array<string, mixed>> the definitions of the section, as settingsSection.php reads them
     */
    private static function boot(string $kassekladde): array
    {
        $GLOBALS['__perm'] = ['settings.company' => 'write', 'finans.kassekladde' => $kassekladde];
        eval('
            function db_escape_string($s) { return addslashes((string) $s); }
            function db_select($q, $where = "") { return (object) ["rows" => []]; }
            function db_fetch_array($r) { return false; }
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
}
