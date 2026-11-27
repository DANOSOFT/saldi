<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Indstillinger » Stamdata's Ansatte link says where the employees are (testlab round 2, company-masterdata, 2026-11-05).
 *
 * The link was written while the employees were still on stamkort.php: "Ansattes rækkefølge og kontaktoplysninger ligger
 * indtil videre på den gamle Stamdata-side" with the button "Åbn den gamle side". stamkort.php is only a redirect now, and
 * the link (settingsSection.php?s=organisation.employees) lands on the hub's Brugere & roller » Brugere, the one list of
 * employees and logins (usersRoles.php?tab=users). Varegrupper's Prisgrupper link to Salg » Rabatter & prisgrupper had
 * the same "Åbn den gamle side" button.
 *
 * Pins: the Ansatte link names the hub heading and row its target sits under, read from includes/settings/hub.php, in
 * every language; its button is "Åbn"; and no settings link to a settings section calls its target the old page. Texts
 * resolve from importfiler/tekster.csv (ids) and importfiler/tekster_keyed.tsv (keys), as findtekst() seeds them.
 *
 * History:
 * 20261105 CL/LH Created.
 */
#[RunTestsInSeparateProcesses]
final class StamdataEmployeesLinkTest extends TestCase
{
    private const KEY = 'company.data.employees';
    private const HREF = 'settingsSection.php?s=organisation.employees';

    private static function boot(int $sprogId): void
    {
        $root = dirname(__DIR__, 3);
        $texts = [];
        foreach (['/importfiler/tekster.csv', '/importfiler/tekster_keyed.tsv'] as $file) {
            foreach (file($root . $file, FILE_IGNORE_NEW_LINES) as $line) {
                $cols = explode("\t", $line);
                if (count($cols) === 4) {
                    $texts[$cols[0]] = $cols;
                }
            }
        }
        $GLOBALS['__texts'] = $texts;
        $GLOBALS['sprog_id'] = $sprogId;
        eval('
            function db_escape_string($s) { return addslashes((string) $s); }
            function db_select($q, $where = "") { return 0; }
            function db_fetch_array($r) { return false; }
            function db_modify($q, $where = "") { throw new RuntimeException("write: " . $q); }
            // An id or a key in the language asked for, from the seed files; the fallback after | when neither has it.
            function findtekst($key, $sprog_id = 1) {
                $parts = explode("|", (string) $key, 2);
                if (isset($GLOBALS["__texts"][$parts[0]])) {
                    return $GLOBALS["__texts"][$parts[0]][(int) $sprog_id];
                }
                return $parts[1] ?? $parts[0];
            }
            function perm_can($key, $level = "read") { return true; }
        ');
        require_once $root . '/includes/settings/components.php';
    }

    /**
     * The field as systemdata/settingsSection.php draws it, with its help and its button as plain text.
     *
     * @param array<string, mixed> $def
     * @return array{help: string, button: string, href: string}
     */
    private static function render(array $def): array
    {
        ob_start();
        st_render_field($def, [
            'value' => '', 'original' => '', 'error' => null, 'readonly' => false, 'locked' => 0,
            'visible' => true, 'mine' => false, 'set_at' => '',
        ]);
        $html = (string) ob_get_clean();
        $doc = new DOMDocument();
        self::assertTrue(@$doc->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>'));
        $xpath = new DOMXPath($doc);
        $help = $xpath->query('//span[@class="st-help"]');
        $link = $xpath->query('//a[@class="st-btn"]');
        self::assertSame(1, $link->length, 'one live link');
        return [
            'help' => $help->length === 1 ? trim($help->item(0)->textContent) : '',
            'button' => trim($link->item(0)->textContent),
            'href' => (string) $link->item(0)->getAttribute('href'),
        ];
    }

    /**
     * @return array<string, array{int}>
     */
    public static function languages(): array
    {
        return ['dansk' => [1], 'english' => [2], 'norsk' => [3]];
    }

    #[DataProvider('languages')]
    public function test_the_ansatte_link_names_the_hub_heading_and_row_it_opens(int $sprogId): void
    {
        self::boot($sprogId);
        $defs = settings_section_definitions('company.data');
        self::assertArrayHasKey(self::KEY, $defs);

        $loc = settings_hub_locate('organisation.employees');
        self::assertNotNull($loc, 'the employee section sits on the hub');
        $hub = getSettingsHub();
        $heading = settings_hub_text($hub[$loc['group']]['subs'][$loc['sub']]['label'], $sprogId);
        $row = settings_hub_row($loc);
        self::assertNotNull($row);
        $rowLabel = settings_hub_text($row['label'], $sprogId);

        $field = self::render($defs[self::KEY]);
        self::assertSame(self::HREF, $field['href']);
        self::assertStringContainsString($heading . ' » ' . $rowLabel, $field['help'], 'the help names where the link lands');
        self::assertSame(st_txt(6157), $field['button'], 'Åbn, as the other links to a settings page');
    }

    #[DataProvider('languages')]
    public function test_no_link_to_a_settings_section_calls_it_the_old_page(int $sprogId): void
    {
        self::boot($sprogId);
        $old = st_txt(6504);
        $links = 0;
        foreach (getSettingDefinitions() as $key => $def) {
            if ($def['type'] !== 'link' || strpos((string) $def['href'], 'settingsSection.php?') !== 0) {
                continue;
            }
            $links++;
            $field = self::render($def + ['key' => $key]);
            foreach (['help', 'button'] as $part) {
                self::assertNotSame($old, $field[$part], $key . ' ' . $part);
                self::assertDoesNotMatchRegularExpression('/\b(gamle|old)\b/iu', $field[$part], $key . ' ' . $part . ': ' . $field[$part]);
            }
        }
        self::assertGreaterThanOrEqual(2, $links, 'Ansatte and Prisgrupper are among the links checked');
    }
}
