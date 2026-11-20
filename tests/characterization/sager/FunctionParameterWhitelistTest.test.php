<?php

declare(strict_types=1);

namespace FunctionParameterWhitelistTest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261120 CL/MMR MB-158 Run the real «funktion» selection and dispatch of sager.php, kontrolskemaer.php and kassekladde.php
//                 without their database and rendering code: only the view handlers and the include are replaced by recorders.
// 20261120 CL/MMR MB-158 Codex round 1: the reopened «Åbne poster» sort only reaches SQL through openpost_order_by(), and «Navn» sorts by name.

/** An existing function that must never be reachable from the address bar. */
function unrelated(): void
{
    FunctionParameterWhitelistTest::$calls[] = ['unrelated', []];
}

/** Records the include the kassekladde dispatch asks for instead of loading the lookup page. */
function record_include(string $path): bool
{
    FunctionParameterWhitelistTest::$includes[] = $path;
    return true;
}

/** Records the kassekladde lookup call instead of running the lookup page. */
function record_call(mixed $funktion, mixed ...$arguments): void
{
    FunctionParameterWhitelistTest::$calls[] = [$funktion, $arguments];
}

final class FunctionParameterWhitelistTest extends TestCase
{
    /** @var list<array{mixed, list<mixed>}> */
    public static array $calls = [];

    /** @var list<string> */
    public static array $includes = [];

    private const ROOT = __DIR__ . '/../../..';

    private const SAGER_ROUTES = [
        'sager' => ['sagsliste', 'opret_sag', 'ret_kunde', 'vis_sag', 'ret_opgave', 'ret_sag', 'kontrolskema', 'kopi_ordre', 'sag_kontakt', 'akkordliste', 'ny_kunde'],
        'kontrolskemaer' => ['kontrolskemaliste', 'vis_skema', 'vis_arbejdsseddel', 'vis_kontrolskema'],
    ];

    private const LOOKUPS = [
        'debitorLookup' => 'debitorLookup.php',
        'creditorLookup' => 'creditorLookup.php',
        'openpost' => 'openpost_inc.php',
    ];

    private static function source(string $file): string
    {
        return str_replace("\r\n", "\n", (string)file_get_contents(self::ROOT . '/' . $file));
    }

    /**
     * The patterns also match the code before MB-158, so the test can be run against it to show the old behaviour.
     *
     * Lifts the page's own selection lines and its dispatch line, and runs them with the view handlers as recorders.
     *
     * @param array<string, mixed> $get
     * @return list<array{mixed, list<mixed>}> Invoked view and its arguments.
     */
    private static function dispatchSager(string $page, array $get): array
    {
        $source = self::source('sager/' . $page . '.php');
        self::assertSame(1, preg_match('/^\t\$funktion = [^\n]*\n/m', $source, $read));
        self::assertSame(1, preg_match('/^\tif \(!(?:in_array\(\$funktion,.*?^\t\}|\$funktion\) ?\{.*?^\t\}|\$funktion\)[^\n]*;)\n/ms', $source, $selection));
        self::assertSame(1, preg_match('/^[\t ]*if \(substr\(\$sag_rettigheder,2,1\)\) \$funktion\(\$(?:sag_id|skema_id)\);/m', $source, $invocation));

        $oldGet = $_GET;
        $_GET = $get;
        self::$calls = [];
        try {
            require_once self::ROOT . '/includes/std_func.php';
            $sag_rettigheder = '111';
            $sag_id = 23;
            $skema_id = 23;
            eval($read[0] . $selection[0]);
            if (in_array($funktion, self::SAGER_ROUTES[$page], true)) {
                $route = $funktion;
                $funktion = static function (...$arguments) use ($route): void {
                    self::$calls[] = [$route, $arguments];
                };
            }
            eval('namespace FunctionParameterWhitelistTest; ' . $invocation[0]);
            return self::$calls;
        } finally {
            $_GET = $oldGet;
        }
    }

    /**
     * Lifts the kassekladde lookup dispatch; its include and call are recorded instead of run.
     *
     * @return array{list<string>, list<array{mixed, list<mixed>}>} The includes asked for and the calls made.
     */
    private static function dispatchKassekladde(mixed $funktion, mixed $sort): array
    {
        $source = self::source('finans/kassekladde.php');
        self::assertSame(1, preg_match('/^\t(?:\$lookupFiles = array\(|if \(\(\$sort\) && \(\$funktion\)\) \{).*?^\t\}\n/ms', $source, $block));
        $code = str_replace('include_once(', '\\FunctionParameterWhitelistTest\\record_include(', $block[0], $includes);
        $code = str_replace('$funktion($find,', '\\FunctionParameterWhitelistTest\\record_call($funktion, $find,', $code, $calls);
        self::assertSame(1, $includes);
        self::assertSame(1, $calls);

        self::$calls = [];
        self::$includes = [];
        $x = 0;
        $find = $fokus = '';
        $kladde_id = 7;
        foreach (['id', 'bilag', 'dato', 'beskrivelse', 'd_type', 'debet', 'k_type', 'kredit', 'faktura', 'belob', 'momsfri', 'afd', 'projekt', 'ansat', 'valuta', 'forfaldsdato', 'betal_id', 'lobenr'] as $name) {
            $$name = [0 => ''];
        }
        eval($code);
        return [self::$includes, self::$calls];
    }

    /** @return iterable<string, array{mixed}> */
    private static function invalidValues(string $foreignRoute): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'unknown' => ['mb158_missing_function'];
        yield 'existing unrelated function' => [__NAMESPACE__ . '\\unrelated'];
        yield 'PHP builtin' => ['getcwd'];
        yield 'path traversal' => ['../../includes/version'];
        yield 'array' => [['getcwd']];
        yield 'callable array' => [[self::class, 'pages']];
        yield 'integer' => [1];
        yield 'boolean' => [true];
        yield 'another page route' => [$foreignRoute];
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidSagerRequests(): iterable
    {
        foreach (array_keys(self::SAGER_ROUTES) as $page) {
            foreach (self::invalidValues($page === 'sager' ? 'vis_skema' : 'vis_sag') as $name => [$value]) {
                yield "$page: $name" => [$page, $value];
            }
            yield "$page: wrong case" => [$page, strtoupper(self::SAGER_ROUTES[$page][1])];
        }
    }

    #[DataProvider('invalidSagerRequests')]
    public function testInvalidFunctionsShowTheDefaultView(string $page, mixed $value): void
    {
        $get = $value === null ? ['sag_id' => 23] : ['funktion' => $value, 'sag_id' => 23];
        self::assertSame([[self::SAGER_ROUTES[$page][0], [23]]], self::dispatchSager($page, $get));
    }

    /** @return iterable<string, array{string, string}> */
    public static function sagerRoutes(): iterable
    {
        foreach (self::SAGER_ROUTES as $page => $routes) {
            foreach ($routes as $route) {
                yield "$page: $route" => [$page, $route];
            }
        }
    }

    #[DataProvider('sagerRoutes')]
    public function testEveryViewTheLinksSendStillOpens(string $page, string $route): void
    {
        self::assertSame([[$route, [23]]], self::dispatchSager($page, ['funktion' => $route]));
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidKassekladdeValues(): iterable
    {
        yield from self::invalidValues('financeLookup');
        yield 'lookup file name' => ['debitorLookup.php'];
        yield 'wrong case' => ['DEBITORLOOKUP'];
        yield 'include file of a direct lookup' => ['openpost_inc'];
    }

    #[DataProvider('invalidKassekladdeValues')]
    public function testKassekladdeIgnoresAnyOtherValue(mixed $value): void
    {
        self::assertSame([[], []], self::dispatchKassekladde($value, 'firmanavn'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function kassekladdeLookups(): iterable
    {
        foreach (self::LOOKUPS as $funktion => $file) {
            yield $funktion => [$funktion, $file];
        }
    }

    #[DataProvider('kassekladdeLookups')]
    public function testKassekladdeSortLinksStillOpenTheirLookup(string $funktion, string $file): void
    {
        [$includes, $calls] = self::dispatchKassekladde($funktion, 'kontonr');
        self::assertCount(1, $includes);
        self::assertStringEndsWith('/kassekladde_includes/' . $file, $includes[0]);
        self::assertCount(1, $calls);
        self::assertSame($funktion, $calls[0][0]);
        self::assertSame('kontonr', $calls[0][1][1]);
        self::assertSame(7, $calls[0][1][5]);
    }

    #[DataProvider('kassekladdeLookups')]
    public function testEachListedFileDefinesItsLookup(string $funktion, string $file): void
    {
        $source = self::source('finans/kassekladde_includes/' . $file);
        self::assertMatchesRegularExpression('/^function ' . preg_quote($funktion, '/') . '\(/m', $source);
    }

    /** @return iterable<string, array{mixed}> */
    public static function unsupportedOpenPostSorts(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'injection' => ["amount; DROP TABLE openpost --"];
        yield 'subquery' => ['(select 1)'];
        yield 'journal sort' => ['transdate,bilag'];
        yield 'wrong case' => ['AMOUNT'];
        yield 'array' => [['amount']];
        yield 'integer' => [1];
    }

    #[DataProvider('unsupportedOpenPostSorts')]
    public function testOpenPostSortFallsBackToTheAccountNumber(mixed $sort): void
    {
        require_once self::ROOT . '/finans/kassekladde_includes/openpost_inc.php';
        self::assertSame('konto_nr', \openpost_order_by($sort));
    }

    public function testOpenPostQueriesOnlyUseTheFixedSortExpression(): void
    {
        $source = self::source('finans/kassekladde_includes/openpost_inc.php');
        self::assertSame(1, preg_match_all('/\$_GET\[[\'"]sort[\'"]\]|ifset\(\$_GET, \'sort\'/', $source));
        self::assertSame(1, preg_match('/^\t\$orderBy = openpost_order_by\(ifset\(\$_GET, \'sort\', null\)\);$/m', $source));
        self::assertSame(2, preg_match_all('/order by \$orderBy"/', $source));
        self::assertSame(0, preg_match('/order by \$sort/', $source));
    }

    /** @return iterable<string, array{string, list<int>}> Header key and the expected openpost id order. */
    public static function openPostSorts(): iterable
    {
        // Rows chosen so every column gives a different order; names run opposite to the account numbers.
        yield 'Kontonr.' => ['konto_nr', [1, 2, 3]];
        yield 'Navn' => ['firmanavn', [3, 2, 1]];
        yield 'Fakturanr.' => ['faktnr', [2, 3, 1]];
        yield 'Dato' => ['transdate', [3, 1, 2]];
        yield 'Beløb' => ['amount', [2, 1, 3]];
    }

    #[DataProvider('openPostSorts')]
    public function testEveryOpenPostHeaderSortsByItsColumn(string $sort, array $expected): void
    {
        $helper = self::ROOT . '/tests/characterization/support/database/TestDatabase.php';
        if (!is_file($helper)) {
            // The database test helper is on master but not yet on live; without it the SQL cannot be run here.
            self::markTestSkipped('tests/characterization/support/database/TestDatabase.php is not available on this branch');
        }
        require_once $helper;
        $reason = \TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        require_once self::ROOT . '/finans/kassekladde_includes/openpost_inc.php';
        $connection = \TestDatabase::connect(\TestDatabase::sqlFixture(self::class)->name());
        pg_query($connection, 'DROP TABLE IF EXISTS pg_temp.openpost');
        pg_query($connection, 'DROP TABLE IF EXISTS pg_temp.adresser');
        pg_query($connection, 'CREATE TEMP TABLE adresser (id INTEGER PRIMARY KEY, kontonr TEXT, firmanavn TEXT)');
        pg_query($connection, 'CREATE TEMP TABLE openpost (id INTEGER PRIMARY KEY, konto_id INTEGER, konto_nr TEXT, faktnr TEXT, transdate DATE, amount NUMERIC(15,2))');
        pg_query($connection, "INSERT INTO adresser VALUES (10, '1001', 'Cirkus'), (20, '1002', 'Bager'), (30, '1003', 'Atelier')");
        pg_query($connection, "INSERT INTO openpost VALUES
            (1, 10, '1001', '300', '2026-02-01', 20),
            (2, 20, '1002', '100', '2026-03-01', 10),
            (3, 30, '1003', '200', '2026-01-01', 30)");
        foreach (['select * from openpost', 'select id, konto_id, konto_nr, faktnr, transdate, amount from openpost'] as $select) {
            $result = pg_query($connection, $select . ' order by ' . \openpost_order_by($sort));
            self::assertNotFalse($result, pg_last_error($connection));
            self::assertSame($expected, array_map('intval', pg_fetch_all_columns($result, 0)));
        }
        pg_query($connection, 'DROP TABLE pg_temp.openpost');
        pg_query($connection, 'DROP TABLE pg_temp.adresser');
    }

    public function testKassekladdeDoesNothingWithoutSort(): void
    {
        self::assertSame([[], []], self::dispatchKassekladde('debitorLookup', null));
    }

    /** @return list<string> Used only as a callable-array value. */
    public static function pages(): array
    {
        return array_keys(self::SAGER_ROUTES);
    }
}
