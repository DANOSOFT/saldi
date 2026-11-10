<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Indstillinger » Regnskab & moms » Valuta opens without looking its currency codes up as texts (testlab round 2,
 * 2026-11-08).
 *
 * The Valuta column of the currencies table is a select of the ISO 4217 codes (st_options() 'iso_currencies', 161
 * without the base currency). Its definition had no 'options_literal', so st_option_label() handed every code to findtekst() as a text id,
 * once for each currency row and once for the new row. An upper-case code is no tekst_key, so findtekst() cached
 * nothing: for every code, on every load, it read information_schema, ran two queries on tekster and read
 * importfiler/tekster.csv. On test_34 (6 currencies) that was 1,127 lookups and 3,381 queries a load, 5.5 s on a fast
 * host and 11-14 s in the lab, where the menu link looked dead. The blank choice went through findtekst() too and read
 * "Lande".
 *
 * Pins: drawing the currencies table asks findtekst() for no currency code and no empty text; every currency's select
 * and the new row's offer a blank choice and the ISO codes without the base currency, each labelled with its own code,
 * the stored code chosen. Every select whose options are computed ('options_from') says how its labels read
 * ('options_literal', or 'options_mixed' for a list with a text id in it), so no computed list is looked up as text
 * ids. The database layer is a double that serves three currencies and counts no use of them; for the definitions it
 * answers every query with one sample row, as tools/check_settings_registry.php does, so the selects the registry
 * repeats per till, employee and price list are there too. findtekst() records what it is asked and marks what it
 * returns.
 *
 * History:
 * 20261105 CL/LH Created.
 */
#[RunTestsInSeparateProcesses]
final class CurrencyCodeSelectTest extends TestCase
{
    /** grupper VK rows, as the test_34 golden has them. */
    private const CURRENCIES = [
        ['id' => '657', 'kodenr' => '1', 'art' => 'VK', 'box1' => 'EUR', 'beskrivelse' => 'EURO', 'box2' => '745,00', 'box3' => '7960', 'box4' => '1'],
        ['id' => '660', 'kodenr' => '4', 'art' => 'VK', 'box1' => 'USD', 'beskrivelse' => 'Amerikanske dollars', 'box2' => '', 'box3' => '7960', 'box4' => '0'],
        ['id' => '661', 'kodenr' => '6', 'art' => 'VK', 'box1' => 'AUD', 'beskrivelse' => 'AUSTRALSKE DOLLAR', 'box2' => '', 'box3' => '7960', 'box4' => '0'],
    ];

    /**
     * @param bool $sampleRows answer every other query with one sample row, so the per-row selects are defined
     */
    private static function boot(bool $sampleRows = false): void
    {
        $GLOBALS['__cs_currencies'] = self::CURRENCIES;
        $GLOBALS['__cs_sample'] = $sampleRows ? [['id' => '1', 'kodenr' => '1', 'kode' => '', 'beskrivelse' => 'sample', 'box1' => '1']
            + array_fill_keys(array_map(fn (int $n) => "box$n", range(2, 14)), '')
            + ['var_name' => 'sample', 'var_value' => '', 'group_id' => '0', 'name' => 'sample', 'navn' => 'sample', 'nummer' => '1', 'lukket' => '', 'uid' => '1', 'n' => '0', 0 => '1']] : [];
        $GLOBALS['__cs_texts'] = [];
        eval('
            function db_escape_string($s) { return addslashes((string) $s); }
            function db_cast($expr, $type) { return "cast($expr as $type)"; }
            function db_select($q, $where = "") {
                $rows = [];
                if (strpos($q, "select * from grupper where art = \'VK\'") === 0) {
                    $rows = $GLOBALS["__cs_currencies"];
                } elseif (strpos($q, "select count(*) as n ") === 0) {
                    $rows = [["n" => "0"]];
                } elseif (strpos($q, "select sum(") === 0 && preg_match_all("/ as (n[0-9]+)/", $q, $m)) {
                    $rows = [array_fill_keys($m[1], "0")];
                } else {
                    $rows = $GLOBALS["__cs_sample"];
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
            function db_modify($q, $where = "") { throw new \RuntimeException("the currencies page wrote: " . $q); }
            function findtekst($key, $sprog_id = 1) {
                $GLOBALS["__cs_texts"][] = (string) $key;
                return "T:" . explode("|", (string) $key, 2)[0];
            }
            function perm_can($key, $level = "read") { return true; }
        ');
        require_once dirname(__DIR__, 3) . '/includes/settings/components.php';
        require_once dirname(__DIR__, 3) . '/includes/settings/rowsView.php';
        require_once dirname(__DIR__, 3) . '/includes/settings/rowHooks.php';
    }

    /**
     * The Valuta select of each currency row and of the new row, as the browser reads it.
     *
     * @return array<string, array{chosen: string, options: array<string, string>}> row id ('__N__' for the new row) => select
     */
    private static function currencySelects(): array
    {
        $t = settings_rows_tables(getSettingsSections()['finance.currencies'])['currencies'];
        ob_start();
        settings_rows_table(['canWrite' => true, 'year' => null, 'years' => [], 'selfUrl' => 'settingsSection.php?s=finance.currencies'], 'currencies', $t, [], []);
        $html = (string) ob_get_clean();
        preg_match_all('#<select class="st-rin" id="c-currencies-(\d+|__N__)-box1"[^>]*>(.*?)</select>#s', $html, $selects, PREG_SET_ORDER);
        $out = [];
        foreach ($selects as [, $rowId, $options]) {
            preg_match_all('#<option value="([^"]*)"( selected)?>([^<]*)</option>#', $options, $opts, PREG_SET_ORDER);
            $chosen = $opts ? html_entity_decode($opts[0][1]) : '';
            $offered = [];
            foreach ($opts as $opt) {
                if ($opt[2] !== '') {
                    $chosen = html_entity_decode($opt[1]);
                }
                $offered[html_entity_decode($opt[1])] = html_entity_decode($opt[3]);
            }
            $out[$rowId] = ['chosen' => $chosen, 'options' => $offered];
        }
        return $out;
    }

    public function test_the_currency_selects_offer_the_iso_codes_as_they_are_without_looking_them_up(): void
    {
        self::boot();
        $selects = self::currencySelects();

        self::assertSame(['657', '660', '661', '__N__'], array_map('strval', array_keys($selects)), 'a Valuta select per currency and one for a new currency');
        $codes = array_values(array_filter(settings_iso_currencies(), fn (string $code) => $code !== 'DKK'));
        self::assertCount(161, $codes, 'the ISO list without the base currency');
        $looked = array_values(array_intersect($GLOBALS['__cs_texts'], array_merge(settings_iso_currencies(), [''])));
        self::assertSame([], array_slice($looked, 0, 5), 'findtekst() is asked for no currency code and no empty text ('
            . count($looked) . ' lookups); each was 3 queries and a read of tekster.csv');
        $expected = ['' => ''] + array_combine($codes, $codes);
        foreach ($selects as $rowId => $select) {
            self::assertSame($expected, $select['options'], "row $rowId: a blank choice and every ISO code, labelled with the code itself");
        }
        self::assertSame('EUR', $selects['657']['chosen']);
        self::assertSame('USD', $selects['660']['chosen']);
        self::assertSame('AUD', $selects['661']['chosen']);
        self::assertSame('', $selects['__N__']['chosen'], 'a new currency starts on the blank choice');
    }

    public function test_every_computed_select_says_how_its_labels_read(): void
    {
        self::boot(true);
        $computed = [];
        $unmarked = [];
        $walk = function (array $node, string $path) use (&$walk, &$computed, &$unmarked): void {
            if (isset($node['options_from'])) {
                $computed[] = $path;
                if (empty($node['options_literal']) && empty($node['options_mixed'])) {
                    $unmarked[] = $path . ' (' . $node['options_from'] . ')';
                }
            }
            foreach ($node as $key => $child) {
                if (is_array($child)) {
                    $walk($child, $path . '.' . $key);
                }
            }
        };
        $walk(getSettingsSections(), 'sections');
        $walk(getSettingDefinitions(), 'fields');

        self::assertContains('sections.finance.currencies.tables.currencies.columns.box1', $computed);
        self::assertContains('fields.pos.tills.department.1', $computed, 'the per-till selects are defined');
        self::assertContains('fields.organisation.employees.background.1', $computed, 'the per-employee selects are defined');
        self::assertContains('fields.purchase.pricelists.supplier.1', $computed, 'the per-price-list selects are defined');
        self::assertSame([], $unmarked, "a computed list holds the company's own codes and names, not text ids: its select needs 'options_literal' (or 'options_mixed')");
    }
}
