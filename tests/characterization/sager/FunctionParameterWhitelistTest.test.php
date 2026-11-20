<?php

declare(strict_types=1);

namespace FunctionParameterWhitelistTest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261120 CL/MMR MB-158 Run the real «funktion» selection and dispatch of sager.php, kontrolskemaer.php and kassekladde.php
//                 without their database and rendering code: only the view handlers and the include are replaced by recorders.
//                 An unknown kassekladde value with «sort» stops the page before the staged line is touched (Codex round 3).

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
     * @return array{list<string>, list<array{mixed, list<mixed>}>, bool} The includes asked for, the calls made and whether the page stopped.
     */
    private static function dispatchKassekladde(mixed $funktion, mixed $sort): array
    {
        $source = self::source('finans/kassekladde.php');
        self::assertSame(1, preg_match('/^\t(?:\$lookupFiles = array\(|if \(\(\$sort\) && \(\$funktion\)\) \{).*?^\t\}\n/ms', $source, $block));
        $code = str_replace('include_once(', '\\FunctionParameterWhitelistTest\\record_include(', $block[0], $includes);
        $code = str_replace('$funktion($find,', '\\FunctionParameterWhitelistTest\\record_call($funktion, $find,', $code, $calls);
        // The page stops with exit; here the stop is returned so the test can see it.
        $code = str_replace('exit;', "return 'stopped';", $code);
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
        ob_start();
        try {
            $stopped = eval($code) === 'stopped';
        } finally {
            $output = (string)ob_get_clean();
        }
        if ($stopped) {
            self::assertSame("<meta http-equiv='refresh' content='0;URL=../finans/kassekladde.php?kladde_id=7'>", $output);
        }
        return [self::$includes, self::$calls, $stopped];
    }

    /** @return iterable<string, array{mixed}> */
    private static function invalidValues(string $foreignRoute): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'zero' => ['0'];
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
        // The Åbne poster sort links send funktion=openpost; that handler is not opened from this route (it was a fatal error before).
        yield 'open-item lookup' => ['openpost'];
    }

    #[DataProvider('invalidKassekladdeValues')]
    public function testKassekladdeIgnoresAnyOtherValue(mixed $value): void
    {
        // A value is never included or called. A set value stops before the line values in the address reach the staged
        // line (it used to stop there with a fatal error); a missing or empty one carries on as before.
        $stops = $value !== null && $value !== '';
        self::assertSame([[], [], $stops], self::dispatchKassekladde($value, 'firmanavn'));
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
        [$includes, $calls, $stopped] = self::dispatchKassekladde($funktion, 'kontonr');
        self::assertFalse($stopped);
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

    public function testKassekladdeDoesNothingWithoutSort(): void
    {
        self::assertSame([[], [], false], self::dispatchKassekladde('debitorLookup', null));
        self::assertSame([[], [], false], self::dispatchKassekladde('openpost', null));
    }

    /** @return list<string> Used only as a callable-array value. */
    public static function pages(): array
    {
        return array_keys(self::SAGER_ROUTES);
    }
}
