<?php
// 20261127 CDX/NTR Check actual journal cell rendering against its header across row types.
// 20261127 CDX/NTR Load the real null-safe lookup helpers in rendering regressions.
// 20261127 CDX/NTR Exercise the proposed row without stored VAT codes and fail on rendering warnings.

declare(strict_types=1);

namespace JournalColumnsFixture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/std_func.php';

// Only unrelated lookups/formatting are replaced; column gates and HTML come from production.
function dkdecimal($value, $decimals = 2) { return number_format((float)$value, $decimals, ',', '.'); }
function afrund($value, $decimals) { return round((float)$value, $decimals); }
function findtekst($text, $language) { return explode('|', $text, 2)[1] ?? $text; }
function db_select($sql, $context) { return $sql; }
function db_fetch_array($sql) { return false; }
function render_vat_select($name, ...$unused) { return "<select name='$name'></select>"; }
function lookup_account_vat_code(...$unused) { return ''; }
function bilagFieldValue($value, $saved) { return (string)$value; }
function bilagIsUnnumbered($value) { return $value === ''; }

final class KassekladdeColumnsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $path = __DIR__ . '/../../../finans/kassekladde_includes/columnLayout.php';
        if (is_file($path)) {
            eval('namespace ' . __NAMESPACE__ . ';' . substr(file_get_contents($path), 5));
        }
    }

    /** @return iterable<string, array{bool, bool, bool, bool, int}> */
    public static function layouts(): iterable
    {
        foreach ([false, true] as $dimensions) {
            foreach ([false, true] as $payments) {
                foreach ([false, true] as $error) {
                    foreach ([false, true] as $customer) {
                        foreach ([0, 1, 2] as $balanceMode) {
                            $name = "dimensions=$dimensions payments=$payments error=$error customer=$customer balances=$balanceMode";
                            yield $name => [$dimensions, $payments, $error, $customer, $balanceMode];
                        }
                    }
                }
            }
        }
    }

    #[DataProvider('layouts')]
    public function testEveryRowMatchesTheHeader(bool $dimensions, bool $payments, bool $error, bool $customer, int $balanceMode): void
    {
        $html = $this->render($dimensions, $payments, $error, $customer, $balanceMode);
        $dom = new \DOMDocument();
        self::assertSame(4, substr_count($html, '<tr>'));
        self::assertTrue($dom->loadHTML('<html><body><table>' . $html . '</table></body></html>'));
        $xpath = new \DOMXPath($dom);
        $rows = $xpath->query('/html/body/table/tr');
        self::assertCount(4, $rows);
        $count = $xpath->query('./td', $rows[0])->length;
        self::assertSame(14 + (int)!$error + ($dimensions ? 4 : 0) + ($customer ? 1 + (int)$payments : 0) + ($balanceMode ? 3 : 0) + ($balanceMode === 2 ? 2 : 0), $count);
        foreach ([1, 2, 3] as $row) {
            self::assertSame($count, $xpath->query('./td', $rows[$row])->length, "Row type $row must match the header");
            $moms = $xpath->query(".//input[@name='moms$row']/ancestor::td", $rows[$row])->item(0);
            self::assertNotNull($moms);
            self::assertSame($count - 3, $xpath->query('preceding-sibling::td', $moms)->length, 'VAT exemption must stay before Position and Actions');
            foreach (['vat_d', 'vat_k', 'afd', 'ansat'] as $class) {
                $header = $xpath->query("./td[contains(@class, 'kk-col-$class')]", $rows[0])->item(0);
                $cell = $xpath->query("./td[contains(@class, 'kk-col-$class')]", $rows[$row])->item(0);
                self::assertSame($header !== null, $cell !== null);
                if ($header !== null) {
                    self::assertSame($xpath->query('preceding-sibling::td', $header)->length, $xpath->query('preceding-sibling::td', $cell)->length);
                }
            }
        }
        self::assertSame(4, substr_count($html, '</tr>'), 'Every row must close explicitly');
        self::assertSame($customer ? 1 : 0, $xpath->query("//input[@name='forf1']")->length);
        self::assertSame($customer && $payments ? 1 : 0, $xpath->query("//input[@name='b_id1']")->length);
        self::assertSame(0, $xpath->query("//input[@name='b_id2']")->length, 'The new row must not inherit another row\'s account type');
    }

    /** @return array<string, array{float|null, float|null}> */
    public static function balances(): array
    {
        return ['zero' => [0, null], 'matching' => [100, 100], 'different' => [100, 80], 'negative' => [-100, -80], 'empty' => [null, null]];
    }

    #[DataProvider('balances')]
    public function testBalanceCellsKeepTheirSlots(?float $ledger, ?float $bank): void
    {
        if (!function_exists(__NAMESPACE__ . '\\kkJournalBalanceCells')) {
            self::markTestSkipped('Shared renderer is introduced by the fix; row regression cases still run on the base.');
        }
        $columns = ['ledger' => true, 'bank' => true, 'difference' => true];
        $html = kkJournalBalanceCells($columns, $ledger, $bank);
        self::assertSame(5, substr_count($html, '<td'));
        if ($ledger === 0.0) {
            self::assertStringContainsString('0,00', $html);
        }
        if ($bank !== null && $bank !== $ledger) {
            self::assertStringContainsString('(' . dkdecimal($bank - $ledger) . ')', $html);
        }
    }

    public function testProposedCustomerRowRespectsPaymentSettingAndEscapesPaymentId(): void
    {
        if (!function_exists(__NAMESPACE__ . '\\kkJournalPaymentCells')) {
            self::markTestSkipped('Shared renderer is introduced by the fix; row regression cases still run on the base.');
        }
        foreach ([false, true] as $showPayment) {
            $columns = kkJournalColumns(['payment' => $showPayment], [2 => 'D'], [], [], false);
            $html = kkJournalPaymentCells($columns, 2, true, '27-11-2026', '"<test>', '', 'UTF-8');
            self::assertStringContainsString("name='forf2'", $html);
            self::assertSame($showPayment, str_contains($html, "name='b_id2'"));
            self::assertStringNotContainsString('"<test>', $html);
        }
    }

    public function testNewRowWithoutVatCodesRendersWithoutWarnings(): void
    {
        set_error_handler(function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $html = $this->render(false, false, false, false, 0);
        } finally {
            restore_error_handler();
        }
        self::assertStringContainsString("name='dvat2'", $html);
        self::assertStringContainsString("name='kvat2'", $html);
    }

    /** Render the real header, existing row, proposed row and an extra blank row without a database. */
    public function render(bool $dimensions, bool $payments, bool $error, bool $customer, int $balanceMode): string
    {
        $source = file_get_contents(getenv('SALDI_JOURNAL_SOURCE') ?: __DIR__ . '/../../../finans/kassekladde.php');
        $vis_bilag = true;
        $vis_afd = $vis_ansat = $vis_projekt = $vis_valuta = $dimensions;
        $vis_bet_id = $payments;
        $vis_forfald = $customer;
        $fejl = $error;
        $udskriv = false;
        $kontrolkonto = $balanceMode ? 5500 : 0;
        $kontrolsaldo = 0;
        $saldoDiff = 0;
        $control_bal_fetched = false;
        $sprog_id = $kladde_id = $regnaar = 1;
        $regnstart = '2026-01-01';
        $regnslut = '2026-12-31';
        $charset = 'UTF-8';
        $baseCurrency = 'DKK';
        $kkdir = 'asc';
        $_bilag_dir = $_date_dir = $_amount_dir = 'desc';
        $tjek = 1;
        $de_fok = '';
        $intern_bilag = false;
        $kk_has_saved_lines = true;
        $kk_rows_staged = $error;
        $kk_next_bilag = 2;
        $vat_codes = $invoiceReuseHits = $kk_doc_source_ids = [];
        foreach (['id', 'bilag', 'dato', 'beskrivelse', 'd_type', 'k_type', 'debet', 'kredit', 'debettext', 'kredittext', 'debetvat', 'kreditvat', 'faktura', 'amount', 'belob', 'dkkamount', 'afd', 'ansat', 'projekt', 'valuta', 'forfaldsdato', 'betal_id', 'momsfri', 'saldo', 'transdate'] as $field) {
            $$field = array_fill(0, 25, '');
        }
        $belob = '';
        $id[1] = 1;
        $bilag[1] = 1;
        $dato[1] = $dato[2] = '27-11-2026';
        $d_type[1] = $customer ? 'D' : 'F';
        $k_type[1] = 'F';
        $debet[1] = 1000;
        $kredit[1] = 5500;
        $amount[1] = 10;
        $valuta[1] = 'DKK';
        $saldo[4] = $balanceMode === 2 ? 100 : 0; // Bank data on a different row.
        $kk_columns = [];
        $layoutStart = strpos($source, '$kk_columns = kkJournalColumns(');
        if ($layoutStart !== false) {
            $layoutEnd = strpos($source, '$kk_editable_view', $layoutStart);
            eval('namespace ' . __NAMESPACE__ . ';' . substr($source, $layoutStart, $layoutEnd - $layoutStart));
        }
        $y = 1;
        $x = 2;
        $z = 3;
        unset($debetvat[$x], $kreditvat[$x]);
        $fragments = [];
        $start = strpos($source, '$_next_dir');
        $start = strpos($source, 'print "<tr>";', $start);
        $end = strpos($source, 'print "</tr>\\n";', $start) + strlen('print "</tr>\\n";');
        $fragments[] = substr($source, $start, $end - $start);
        $start = strpos($source, 'print "<tr>";', strpos($source, '$invoiceReuseHits ='));
        $end = strpos($source, 'print "</tr>\\n";', $start) + strlen('print "</tr>\\n";');
        $fragments[] = substr($source, $start, $end - $start);
        $start = strpos($source, 'print "<tr>";', strpos($source, '# The blank line at the end'));
        $end = strpos($source, '#cho __line__." X', $start);
        $fragments[] = preg_replace('/\}\s*$/', '', substr($source, $start, $end - $start));
        $start = strpos($source, 'print "<tr>";', strpos($source, 'for ($z = $x;'));
        $end = strpos($source, 'print "</tr>\\n";', $start) + strlen('print "</tr>\\n";');
        $fragments[] = substr($source, $start, $end - $start);
        ob_start();
        try {
            foreach ($fragments as $fragment) {
                eval('namespace ' . __NAMESPACE__ . ';' . $fragment);
            }
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        return preg_replace('/&(?!#\d+;|[a-z]+;)/i', '&amp;', $html);
    }
}
