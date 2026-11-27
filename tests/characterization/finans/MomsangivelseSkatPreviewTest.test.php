<?php
// 20261113 CL/ASR Pin that the VAT return hides the 'Momsindberetning' (SKAT filing) section unless the company's settings row vat/skat_submission_preview is 'on', with the same total and no warnings either way.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class MomsangivelseSkatPreviewTest extends TestCase
{
    private object $state;
    private string $cwd;

    protected function setUp(): void
    {
        // A stubbed database answers the report's queries for one fiscal year (2026) whose VAT range 7680..7830 holds a
        // heading, sales VAT (SM), purchase VAT (KM) and a sum account; the flag rows are set per test.
        $this->state = (object) ['flag' => [], 'pending' => [], 'queries' => [], 'unexpected' => []];
        $GLOBALS['momsSkatPreviewState'] = $this->state;
        if (!function_exists('db_select')) {
            function db_select($sql, $location = '', $global = false) {
                $state = $GLOBALS['momsSkatPreviewState'];
                $state->queries[] = $sql;
                $rows = [];
                if (strpos($sql, 'skat_submission_preview') !== false) {
                    $rows = $state->flag;
                } elseif (strpos($sql, 'from adresser') !== false) {
                    $rows = [['firmanavn' => 'Test ApS']];
                } elseif (strpos($sql, "art='RA'") !== false) {
                    $rows = [['box1' => '01', 'box2' => '2026', 'box3' => '12', 'box4' => '2026']];
                } elseif (strpos($sql, "art = 'MR'") !== false) {
                    $rows = [['box1' => '7680', 'box2' => '7830', 'fiscal_year' => '1']];
                } elseif (strpos($sql, 'select * from kontoplan') === 0) {
                    $rows = [
                        ['kontonr' => '7680', 'beskrivelse' => 'Moms', 'kontotype' => 'H', 'fra_kto' => '0', 'primo' => '0'],
                        ['kontonr' => '7700', 'beskrivelse' => 'Salgsmoms', 'kontotype' => 'D', 'fra_kto' => '0', 'primo' => '0'],
                        ['kontonr' => '7710', 'beskrivelse' => 'Købsmoms', 'kontotype' => 'D', 'fra_kto' => '0', 'primo' => '0'],
                        ['kontonr' => '7740', 'beskrivelse' => 'Moms i alt', 'kontotype' => 'Z', 'fra_kto' => '7680', 'primo' => '0'],
                    ];
                } elseif (strpos($sql, 'from transaktioner') !== false) {
                    $rows = [
                        ['kontonr' => '7700', 'debet' => '0', 'kredit' => '2500'],
                        ['kontonr' => '7710', 'debet' => '1000', 'kredit' => '0'],
                    ];
                } elseif (preg_match("/^select art from grupper where box1='(\\d+)'/", $sql, $m)) {
                    $art = ['7700' => 'SM', '7710' => 'KM'];
                    $rows = isset($art[$m[1]]) ? [['art' => $art[$m[1]]]] : [];
                } else {
                    $state->unexpected[] = $sql;
                }
                $state->pending = $rows;
                return 'moms-skat-preview-result';
            }
            function db_fetch_array($result) {
                $state = $GLOBALS['momsSkatPreviewState'];
                return $state->pending ? array_shift($state->pending) : false;
            }
            function findtekst($id, $sprog_id = 1) {
                $parts = explode('|', (string) $id, 2);
                return isset($parts[1]) ? $parts[1] : "tekst $id";
            }
            function if_isset($value = null, $default = null, $key = null) {
                return isset($value) ? $value : $default;
            }
            function afrund($tal, $decimaler) {
                return round((float) $tal, (int) $decimaler);
            }
            function dkdato($dato) {
                return $dato ? date('d-m-Y', strtotime($dato)) : '';
            }
            function kontobemaerkning($kontonavn) {
                return null;
            }
        }
        require_once __DIR__ . '/../../../includes/stdFunc/dkDecimal.php';
        require_once __DIR__ . '/../../../includes/stdFunc/dimensionSqlFragment.php';
        require_once __DIR__ . '/../../../finans/rapport_includes/momsangivelse.php';

        $GLOBALS['md'] = [1 => 'januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'december'];
        $GLOBALS['menu'] = '';
        $GLOBALS['top_bund'] = '';
        $GLOBALS['sprog_id'] = 1;
        // The page reads $_GET['medtag_primo'] through if_isset(); a request without it warns before the report starts.
        $_GET['medtag_primo'] = '';
        // momsangivelse() includes ../includes/topline_settings.php and the footer relative to finans/, as rapport.php runs it.
        $this->cwd = getcwd();
        chdir(__DIR__ . '/../../../finans');
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        unset($GLOBALS['momsSkatPreviewState']);
    }

    /**
     * Runs the report for Q4 2026 and returns its HTML and the warnings raised in momsangivelse.php.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private function report(): array
    {
        $warnings = [];
        set_error_handler(static function (int $no, string $message, string $file, int $line) use (&$warnings): bool {
            if (basename($file) === 'momsangivelse.php') {
                $warnings[] = "$line: $message";
            }
            return true;
        });
        ob_start();
        try {
            momsangivelse('1', '10', '12', '2026', '2026', '1', '31', '', '', 'momsangivelse', '', '', '', '', '', '', '');
        } finally {
            $html = ob_get_clean();
            restore_error_handler();
        }
        return [$html, $warnings];
    }

    private function assertVatFigures(string $html): void
    {
        self::assertStringContainsString('<td>7700 </td>', $html);
        self::assertStringContainsString('<td align=right>2.500,00 </td>', $html);
        self::assertStringContainsString('<td align=right>1.000,00 </td>', $html);
        self::assertStringContainsString('Afgiftsbel&oslash;b i alt </td><td colspan=4 align=right>1.500,00 </td>', $html);
        self::assertSame([], $this->state->unexpected);
    }

    /** @return array<string, array{0: array<int, array{var_value: string}>}> */
    public static function hidden(): array
    {
        return [
            'no row' => [[]],
            'off' => [[['var_value' => 'off']]],
            'empty' => [[['var_value' => '']]],
        ];
    }

    /** @param array<int, array{var_value: string}> $flag */
    #[DataProvider('hidden')]
    public function testWithoutTheFlagTheSectionIsHidden(array $flag): void
    {
        $this->state->flag = $flag;
        [$html, $warnings] = $this->report();

        self::assertStringNotContainsString('Momsindberetning', $html);
        self::assertStringNotContainsString('Hent kalender', $html);
        self::assertStringNotContainsString('Indberet moms', $html);
        self::assertStringNotContainsString('2018', $html);
        $this->assertVatFigures($html);
        self::assertSame([], $warnings);
    }

    public function testTheFlagIsReadOnceForTheCompany(): void
    {
        $this->report();

        $flagQueries = array_values(array_filter($this->state->queries, static function (string $sql): bool {
            return strpos($sql, 'skat_submission_preview') !== false;
        }));
        self::assertSame(["select var_value from settings where var_grp = 'vat' and var_name = 'skat_submission_preview' and (user_id is null or user_id = 0) order by id desc limit 1"], $flagQueries);
    }

    /** @return array<string, array{0: string}> */
    public static function shown(): array
    {
        return [
            'on' => ['on'],
            'on with spaces' => [' on '],
        ];
    }

    #[DataProvider('shown')]
    public function testTheCompanyRowOnShowsThePreviewWithoutWarnings(string $value): void
    {
        $this->state->flag = [['var_value' => $value]];
        [$html, $warnings] = $this->report();

        self::assertStringContainsString('Momsindberetning', $html);
        self::assertStringContainsString('<button>Hent kalender</button>', $html);
        self::assertStringContainsString('<button>Indberet moms</button>', $html);
        // The canned SKAT response parses: its frequency code 8 and its four deadlines are shown.
        self::assertStringContainsString('frekvensen er: Kvartal', $html);
        self::assertSame(4, substr_count($html, ' 2018</td>'));
        self::assertStringContainsString('<td>1. marts 2018</td>', $html);
        $this->assertVatFigures($html);
        self::assertSame([], $warnings);
    }
}
