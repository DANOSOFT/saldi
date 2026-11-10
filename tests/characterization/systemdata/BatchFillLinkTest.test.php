<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Indstillinger » Varer & lager » Lagerstyring & kostpris keeps master's link to lager/batch_udfyld.php (testlab round 2,
 * batch-expiry-fefo DEVY-1, 2026-11-05).
 *
 * On master, systemdata/diverseIncludes/productOptions.php prints "Udfyld batchoplysninger for varer, der allerede er på
 * lager" under the batch/expiry checkbox while the setting is on, with the number of batches that lack details. The
 * settings hub replaced that page (diverse.php?sektion=productOptions now redirects to items.stock) and its definition of
 * the setting carried no link, so turning batch/expiry handling on no longer offered the tool.
 *
 * Pins: items.stock has the link right under the setting, worded as master's, pointing at the page; it shows from the
 * stored setting (settings batchExpiryEnabled 'on'), with the count when batches lack details and none when they don't;
 * with the setting off it is drawn hidden for the switch to reveal and costs no count query; whoever may change batches
 * (lager.varer write, the page's own gate) gets a live link, a reader a disabled one, anyone else nothing. The database
 * layer is a double that serves the settings row and the count; perm_can() answers from the level a test sets.
 *
 * History:
 * 20261105 CL/LH Created.
 */
#[RunTestsInSeparateProcesses]
final class BatchFillLinkTest extends TestCase
{
    private const KEY = 'items.stock.batch_fill';
    private const SETTING = 'items.stock.batch_expiry';
    private const HREF = '../lager/batch_udfyld.php';

    /**
     * @param string|null $stored batchExpiryEnabled as stored in settings (var_grp items), null for no row
     * @return array<string, array<string, mixed>> the definitions of the section, as settingsSection.php reads them
     */
    private static function boot(?string $stored, int $missing = 0, string $lagerVarer = 'write', int $sprogId = 1): array
    {
        $root = dirname(__DIR__, 3);
        $GLOBALS['__perm'] = ['settings.items' => 'write', 'lager.varer' => $lagerVarer];
        $GLOBALS['__settings'] = $stored === null ? [] : [[
            'id' => '121', 'var_grp' => 'items', 'var_name' => 'batchExpiryEnabled', 'var_value' => $stored,
            'user_id' => '0', 'pos_id' => '0', 'group_id' => '0',
        ]];
        $GLOBALS['__missing'] = $missing;
        $GLOBALS['__queries'] = [];
        $GLOBALS['__keyed'] = $root . '/importfiler/tekster_keyed.tsv';
        $GLOBALS['sprog_id'] = $sprogId;
        $GLOBALS['regnaar'] = 1;
        eval('
            function db_escape_string($s) { return addslashes((string) $s); }
            function db_select($q, $where = "") {
                $GLOBALS["__queries"][] = $q;
                $rows = [];
                if (strpos($q, "from settings where var_name in") !== false) {
                    $rows = $GLOBALS["__settings"];
                } elseif (strpos($q, "FROM batch_kob") !== false) {
                    $rows = [["antal" => (string) $GLOBALS["__missing"]]];
                }
                $GLOBALS["__results"][] = $rows;
                return count($GLOBALS["__results"]) - 1;
            }
            function db_fetch_array($r) { return array_shift($GLOBALS["__results"][$r]) ?? false; }
            function db_modify($q, $where = "") { throw new RuntimeException("write: " . $q); }
            // A key resolves from importfiler/tekster_keyed.tsv in the language asked for, as findtekst() seeds it; an id stays an id.
            function findtekst($key, $sprog_id = 1) {
                $parts = explode("|", (string) $key, 2);
                foreach (file($GLOBALS["__keyed"], FILE_IGNORE_NEW_LINES) as $line) {
                    $cols = explode("\t", $line);
                    if ($cols[0] === $parts[0]) { return $cols[(int) $sprog_id]; }
                }
                return $parts[0];
            }
            function perm_can($key, $level = "read") {
                $rank = ["none" => 0, "read" => 1, "write" => 2];
                return $rank[$GLOBALS["__perm"][$key] ?? "none"] >= $rank[$level];
            }
        ');
        require_once $root . '/includes/stdFunc/fefo.php';
        require_once $root . '/includes/settings/components.php';
        require_once $root . '/includes/settings/actions.php';
        return settings_section_definitions('items.stock');
    }

    /**
     * The link as systemdata/settingsSection.php draws it: the section's stored form values decide what shows.
     *
     * @param array<string, array<string, mixed>> $defs
     */
    private static function render(array $defs, bool $sectionReadonly = false): string
    {
        $values = [];
        foreach ($defs as $key => $def) {
            if (!in_array($def['type'], ['action', 'info', 'link', 'mini'], true)) {
                $values[$key] = st_current_form_value($def);
            }
        }
        ob_start();
        st_render_field($defs[self::KEY], [
            'value' => '', 'original' => '', 'error' => null, 'readonly' => $sectionReadonly, 'locked' => 0,
            'visible' => st_visible($defs[self::KEY], $values), 'mine' => false, 'set_at' => '',
        ]);
        return (string) ob_get_clean();
    }

    private static function countQueries(): int
    {
        return count(array_filter($GLOBALS['__queries'], static fn (string $q): bool => strpos($q, 'FROM batch_kob') !== false));
    }

    public function test_the_stock_section_links_to_the_batch_details_page_right_under_the_setting(): void
    {
        $defs = self::boot('on');

        self::assertArrayHasKey(self::KEY, $defs);
        $keys = array_keys($defs);
        self::assertSame(array_search(self::SETTING, $keys, true) + 1, array_search(self::KEY, $keys, true), 'drawn right under the checkbox');
        $def = $defs[self::KEY];
        self::assertSame('link', $def['type']);
        self::assertSame($defs[self::SETTING]['sub'], $def['sub'], 'in the same card as the setting');
        self::assertSame(self::HREF, $def['href']);
        self::assertFileExists(dirname(__DIR__, 3) . '/systemdata/' . $def['href'], 'the link resolves from systemdata/settingsSection.php');
        self::assertSame(['setting', self::SETTING, true], $def['visible_if']);
        self::assertSame('lager.varer', $def['permission'], 'gated like the page it opens ($modulnr 9, write)');
    }

    /**
     * @return array<string, array{int}>
     */
    public static function languages(): array
    {
        return ['dansk' => [1], 'english' => [2], 'norsk' => [3]];
    }

    #[DataProvider('languages')]
    public function test_the_link_is_worded_as_on_master_in_every_language(int $sprogId): void
    {
        $defs = self::boot('on', 0, 'write', $sprogId);

        self::assertSame(batch_txt('fill_stock'), st_txt($defs[self::KEY]['label']));
        self::assertStringContainsString('>' . st_h(batch_txt('fill_stock')) . '</label>', self::render($defs));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function missing(): array
    {
        return [
            'three batches lack details' => [3, '3 batches på lager mangler udløbsdato eller batchnummer'],
            'more than a thousand' => [1240, '1.240 batches på lager mangler udløbsdato eller batchnummer'],
        ];
    }

    #[DataProvider('missing')]
    public function test_with_the_setting_stored_on_the_link_shows_with_the_batches_that_lack_details(int $missing, string $impact): void
    {
        $defs = self::boot('on', $missing);

        $html = self::render($defs);
        self::assertMatchesRegularExpression('/<div class="[^"]*" id="items\.stock\.batch_fill"[^>]*data-visible-if="[^"]*"(?! hidden)>/', $html, 'shown');
        self::assertStringContainsString('<a class="st-btn" href="' . self::HREF . '">', $html);
        self::assertStringContainsString('<span class="st-impact">' . st_h($impact) . '</span>', $html);
    }

    public function test_with_no_batch_lacking_details_the_link_shows_without_a_count(): void
    {
        $defs = self::boot('on', 0);

        $html = self::render($defs);
        self::assertStringContainsString('<a class="st-btn" href="' . self::HREF . '">', $html);
        self::assertStringNotContainsString('st-impact', $html);
        self::assertSame(1, self::countQueries());
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function off(): array
    {
        return ['stored off' => ['off'], 'never set' => [null]];
    }

    #[DataProvider('off')]
    public function test_with_the_setting_off_the_link_is_drawn_hidden_for_the_switch_and_nothing_is_counted(?string $stored): void
    {
        $defs = self::boot($stored, 5);

        $html = self::render($defs);
        self::assertMatchesRegularExpression('/<div class="[^"]*" id="items\.stock\.batch_fill"[^>]*data-visible-if="[^"]*" hidden>/', $html, 'hidden until the switch is turned on');
        self::assertStringContainsString('<a class="st-btn" href="' . self::HREF . '">', $html, 'present for the switch to reveal');
        self::assertStringNotContainsString('st-impact', $html);
        self::assertSame(0, self::countQueries());
    }

    public function test_a_user_who_may_change_batches_gets_a_live_link_also_on_a_read_only_section(): void
    {
        $defs = self::boot('on', 2, 'write');

        $html = self::render($defs, true);
        self::assertStringContainsString('<a class="st-btn" href="' . self::HREF . '">', $html);
        self::assertStringNotContainsString('aria-disabled', $html);
    }

    public function test_a_reader_of_varer_gets_a_disabled_link_as_the_page_would_refuse_them(): void
    {
        $defs = self::boot('on', 2, 'read');

        $html = self::render($defs);
        self::assertStringNotContainsString(self::HREF, $html);
        self::assertStringContainsString('aria-disabled="true"', $html);
    }

    public function test_a_user_without_varer_gets_no_link_at_all(): void
    {
        $defs = self::boot('on', 2, 'none');

        self::assertSame('', self::render($defs));
        self::assertSame(0, self::countQueries());
    }
}
