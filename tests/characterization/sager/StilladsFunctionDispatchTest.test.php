<?php

declare(strict_types=1);

namespace StilladsDispatchTest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261110 CDX/NTR SST-968 Exercise the five pages' actual route selection and dispatch without loading their database/rendering code.

/** Records the note page's direct default-view call. */
function ret_note(...$arguments): void
{
    StilladsFunctionDispatchTest::$calls[] = ['ret_note', $arguments];
}

/** Records the note page's direct default-list call. */
function noteliste(...$arguments): void
{
    StilladsFunctionDispatchTest::$calls[] = ['noteliste', $arguments];
}

/** An existing zero-argument function must never become a route. */
function unrelated(): void
{
    StilladsFunctionDispatchTest::$calls[] = ['unrelated', []];
}

final class StilladsFunctionDispatchTest extends TestCase
{
    /** @var list<array{string, list<mixed>}> */
    public static array $calls = [];

    private const ROUTES = [
        'medarbejdermappe' => ['medarbejdermappeliste', 'faellesmappe', 'ret_mappe', 'ret_bilag', 'vis_mappe', 'medarbejdermappe', 'ret_ansatmappe', 'ret_ansatbilag', 'vis_ansatmappe'],
        'loen' => ['loenliste', 'ret_loen', 'loenafregning', 'satser'],
        'certificering' => ['certificeringliste'],
        'planlaeg' => ['planlaeg'],
        'notat' => ['noteliste', 'vis_note', 'find_person', 'ret_note', 'findsag'],
    ];

    /**
     * Lifts the real selection and invocation from a page; only view handlers are doubles.
     * This avoids running includes, SQL, HTML and the handlers' unrelated business logic.
     *
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @return list<array{string, list<mixed>}> Invoked route and its original arguments.
     */
    private static function dispatch(string $page, array $get, array $post = []): array
    {
        $source = str_replace("\r\n", "\n", (string)file_get_contents(__DIR__ . '/../../../sager/' . $page . '.php'));
        self::assertSame(1, preg_match('/^\$funktion\s*=.*?(?=^\s*(?:global\s+\$brugernavn|\$(?:sort|id)\s*=))/ms', $source, $selection));
        self::assertSame(1, preg_match('/^[\t ]*(?:if\s*\(\$funktion\))?\s*\$funktion\([^;]*\);(?:\n[\t ]*elseif[^\n]*;\n[\t ]*else[^\n]*;)?/m', $source, $invocation));

        $oldGet = $_GET;
        $oldPost = $_POST;
        $_GET = $get;
        $_POST = $post;
        self::$calls = [];
        try {
            // if_isset() remains necessary when proving this test against the original page.
            require_once(__DIR__ . '/../../../includes/std_func.php');
            eval($selection[0]);
            $id = $get['id'] ?? null;
            $sag_id = $get['sag_id'] ?? null;
            $sag_fase = $get['sag_fase'] ?? null;
            $mine_notater = $get['mine_notater'] ?? null;

            if ($page === 'notat') {
                self::assertSame(1, preg_match('/^if \(isset\(\$_POST\[\'find_person\'\]\).*?;[^\S\n]*/m', $source, $postAction));
                eval($postAction[0]);
            }
            if (in_array($funktion, self::ROUTES[$page], true)) {
                $route = $funktion;
                $funktion = static function (...$arguments) use ($route): void {
                    self::$calls[] = [$route, $arguments];
                };
            }
            eval('namespace StilladsDispatchTest; ' . $invocation[0]);
            return self::$calls;
        } finally {
            $_GET = $oldGet;
            $_POST = $oldPost;
        }
    }

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function invalidRequests(): iterable
    {
        foreach (array_keys(self::ROUTES) as $page) {
            foreach ([
                'missing' => [],
                'empty' => ['funktion' => ''],
                'unknown' => ['funktion' => 'sst968_missing_function'],
                'existing unrelated function' => ['funktion' => __NAMESPACE__ . '\\unrelated'],
                'PHP builtin' => ['funktion' => 'getcwd'],
                'array' => ['funktion' => ['getcwd']],
                'callable array' => ['funktion' => [self::class, 'invalidRequests']],
                'integer' => ['funktion' => 1],
                'boolean' => ['funktion' => true],
                'wrong case' => ['funktion' => strtoupper(self::ROUTES[$page][0])],
                'another page route' => ['funktion' => $page === 'loen' ? 'medarbejdermappeliste' : 'satser'],
            ] as $name => $get) {
                yield "$page: $name" => [$page, $get];
            }
        }
    }

    #[DataProvider('invalidRequests')]
    public function testInvalidFunctionsRenderTheDefaultView(string $page, array $get): void
    {
        $default = $page === 'notat' ? 'noteliste' : self::ROUTES[$page][0];
        self::assertSame([[$default, $page === 'notat' ? [null] : []]], self::dispatch($page, $get));
    }

    /** @return iterable<string, array{string, string}> */
    public static function publicRoutes(): iterable
    {
        foreach (self::ROUTES as $page => $routes) {
            foreach ($routes as $route) {
                yield "$page: $route" => [$page, $route];
            }
        }
    }

    #[DataProvider('publicRoutes')]
    public function testEveryPublicRouteRemainsReachable(string $page, string $route): void
    {
        self::assertSame([[$route, $page === 'notat' ? [17, 23, 2] : []]], self::dispatch($page, ['funktion' => $route, 'id' => 17, 'sag_id' => 23, 'sag_fase' => 2]));
    }

    public function testInvalidNoteRoutesPreserveTheContextualDefault(): void
    {
        foreach (['sst968_missing_function', 'getcwd', ['getcwd']] as $invalid) {
            self::assertSame([['ret_note', [17, null, 2]]], self::dispatch('notat', ['funktion' => $invalid, 'id' => 17, 'sag_fase' => 2]));
            self::assertSame([['ret_note', [null, 23, 2]]], self::dispatch('notat', ['funktion' => $invalid, 'sag_id' => 23, 'sag_fase' => 2]));
            self::assertSame([['noteliste', ['on']]], self::dispatch('notat', ['funktion' => $invalid, 'mine_notater' => 'on']));
        }
    }

    public function testTheFindPersonPostActionRemainsReachable(): void
    {
        self::assertSame([['find_person', [17, 23, 2]]], self::dispatch('notat', ['funktion' => 'getcwd', 'id' => 17, 'sag_id' => 23, 'sag_fase' => 2], ['find_person' => 'Find person']));
    }
}
