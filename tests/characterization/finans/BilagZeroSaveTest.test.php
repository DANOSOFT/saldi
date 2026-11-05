<?php
// 20261105 CDX/NTR Cover SST-934 through all three opdater() database write paths.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BilagZeroSaveTest extends TestCase
{
    private PgSql\Connection $db;

    protected function setUp(): void
    {
        $dsn = getenv('SALDI_CHAR_PG_DSN');
        if (!$dsn) {
            self::markTestSkipped('Set SALDI_CHAR_PG_DSN to run the PostgreSQL save fixtures.');
        }
        self::assertTrue(extension_loaded('pgsql'), 'SALDI_CHAR_PG_DSN requires pgsql.');
        $db = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        self::assertNotFalse($db);
        $this->db = $db;
        $GLOBALS['bilagSaveTestDb'] = $db;
        require_once __DIR__ . '/support/bilag_save_database.php';
        require_once __DIR__ . '/../../../includes/std_func.php';
        require_once __DIR__ . '/../../../finans/kassekladde_includes/bilagNumber.php';

        // Only temporary tables are visible to the save; no installed tenant is required.
        db_select('SET search_path TO pg_temp');
        foreach (['kassekladde', 'tmpkassekl'] as $table) {
            $id = $table === 'kassekladde' ? 'serial PRIMARY KEY' : 'integer DEFAULT 0';
            $bilag = $table === 'kassekladde' ? 'integer' : 'text';
            db_select("CREATE TEMP TABLE $table (
                id $id, bilag $bilag, kladde_id integer DEFAULT 1, lobenr integer DEFAULT 1,
                transdate text DEFAULT '05-11-2026', forfaldsdate text DEFAULT '',
                beskrivelse text DEFAULT 'SST-934 fixture', d_type text DEFAULT 'F',
                debet text DEFAULT '1000', k_type text DEFAULT 'F', kredit text DEFAULT '2000',
                amount text DEFAULT '125,00', momsfri text DEFAULT '', afd text DEFAULT '',
                ansat text DEFAULT '', faktura text DEFAULT '', projekt text DEFAULT '',
                valuta text DEFAULT 'DKK', betal_id text DEFAULT '', debetvat text DEFAULT '',
                kreditvat text DEFAULT '', pos integer DEFAULT 1
            )");
        }
        db_select("INSERT INTO kassekladde (id, bilag) VALUES (100, 558)");
        $GLOBALS['baseCurrency'] = 'DKK';
        $GLOBALS['egen_kto_id'] = 0;
        $GLOBALS['sprog_id'] = 1;
        $GLOBALS['fejl'] = 0;
        $GLOBALS['kk_plus_line_ids'] = [];
        $GLOBALS['kk_new_line_ids'] = [];
        $GLOBALS['kk_new_line_rows'] = [];

        $source = file_get_contents(__DIR__ . '/../../../finans/kassekladde.php');
        self::assertNotFalse($source);
        foreach (['opdater', 'kk_note_new_line', 'kk_last_insert_id'] as $name) {
            eval(self::functionSource($source, $name));
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            pg_close($this->db);
        }
    }

    /**
     * Load the production function without executing the journal page's authentication/rendering.
     *
     * @return string Complete function definition, taken verbatim from the page.
     */
    private static function functionSource(string $source, string $name): string
    {
        $tokens = token_get_all($source);
        foreach ($tokens as $start => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }
            $next = $start + 1;
            while (is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                $next++;
            }
            if (!is_array($tokens[$next]) || $tokens[$next][1] !== $name) {
                continue;
            }
            $definition = '';
            $depth = 0;
            $opened = false;
            foreach (array_slice($tokens, $start) as $part) {
                $definition .= is_array($part) ? $part[1] : $part;
                if ($part === '{' || (is_array($part) && in_array($part[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                    $depth++;
                    $opened = true;
                } elseif ($part === '}' && --$depth === 0 && $opened) {
                    return $definition;
                }
            }
        }
        throw new RuntimeException("Missing function $name in kassekladde.php");
    }

    /** @return array<string, array{string, string, int}> */
    public static function saveCases(): array
    {
        $cases = [];
        foreach (['update', 'insert', 'due date'] as $branch) {
            foreach (['0' => 0, '00' => 0, '' => 559, '42' => 42] as $input => $expected) {
                $cases["$branch input '$input'"] = [$branch, (string)$input, $expected];
            }
        }
        return $cases;
    }

    /** @return void */
    private function stage(string $branch, string $value): void
    {
        $id = $branch === 'update' ? 100 : 0;
        $due = $branch === 'due date' ? '20-11-2026' : '';
        $result = pg_query_params($this->db,
            'INSERT INTO tmpkassekl (id, bilag, forfaldsdate) VALUES ($1, $2, $3)',
            [$id, $value, $due]);
        self::assertNotFalse($result);
    }

    #[DataProvider('saveCases')]
    public function testEachSavePathKeepsTypedZeroAndStillAllocatesBlanks(string $branch, string $input, int $expected): void
    {
        $this->stage($branch, bilagFromInput($input));
        // The page may have proposed a positive number before the user typed zero.
        opdater(1, [1 => 559]);
        $where = $branch === 'update' ? 'id = 100' : 'id <> 100';
        $row = db_fetch_array(db_select("SELECT bilag, amount, forfaldsdate FROM kassekladde WHERE $where"));
        self::assertIsArray($row);
        self::assertSame($expected, (int)$row['bilag']);
        self::assertSame(125.0, (float)$row['amount']);
        self::assertSame($branch === 'due date' ? '2026-11-20' : ($branch === 'update' ? null : ''), $row['forfaldsdate']);
        self::assertSame(0, $GLOBALS['fejl']);
    }

    public function testSavedZeroSurvivesASecondDatabaseSave(): void
    {
        $this->stage('update', bilagFromInput('0'));
        opdater(1);
        $row = db_fetch_array(db_select('SELECT bilag FROM kassekladde WHERE id = 100'));
        self::assertSame('0', $row['bilag']);
        db_select('TRUNCATE tmpkassekl');
        $this->stage('update', bilagFromInput(bilagFieldValue($row['bilag'], true)));
        opdater(1);
        $row = db_fetch_array(db_select('SELECT bilag FROM kassekladde WHERE id = 100'));
        self::assertSame('0', $row['bilag']);
    }

    public function testAnEmptyJournalDoesNotOverwriteAStagedZeroWhenRendering(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../finans/kassekladde.php');
        self::assertNotFalse($source);
        $start = strpos($source, 'if (!$kk_has_saved_lines && $y == 1');
        self::assertNotFalse($start);
        $end = strpos($source, 'if (!$id[$y]', $start);
        self::assertNotFalse($end);
        $y = 1;
        $bilag = [1 => bilagFromInput('0')];
        $kk_has_saved_lines = false;
        $kk_rows_staged = true;
        $last_bilag = 558;
        $title = $color = $de_fok = '';
        ob_start();
        try {
            eval(substr($source, $start, $end - $start));
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        self::assertStringContainsString('value ="0"', $html);
    }
}
