<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/includes/std_func.php';

// 20261027 CL/LH PR #681: two things on the order card that ordreside() decides inline.
//                1. Print plukliste must not depend on the plukliste e-mail setting; only the
//                   Send field does (review of PR #681 on 2026-10-08: without the setting an open
//                   order had no Print button at all, which broke Peter's rule that a plukliste
//                   can be printed before the order is invoiced). Which orders show the buttons
//                   is SST-828's $visPlukliste, pinned in PluklisteButtonVisibilityTest.
//                2. The credit-limit check reads kreditmax only for an order with a konto_id and
//                   without reading a missing offset: if_isset($r['kreditmax']) * 1 warned when
//                   $r was another query's row or false, and '' * 1 is a TypeError on PHP 8.
//                ordreside() is a 3000-line function in a page script, so the decisions are
//                lifted from the source rather than restated here.
final class OrderCardPrintAndCreditLimitTest extends TestCase
{
    private const PAGE = __DIR__ . '/../../../debitor/ordre.php';

    /** @var string|null Cached source of debitor/ordre.php. */
    private static ?string $src = null;

    private static function source(): string
    {
        if (self::$src === null) {
            $src = file_get_contents(self::PAGE);
            self::assertNotFalse($src, 'could not read debitor/ordre.php');
            self::$src = $src;
        }
        return self::$src;
    }

    /**
     * For every token the matcher accepts, the headers of the brace blocks around it,
     * outermost first ("if ($visPlukliste)", "else", ...). Comments are left out of the
     * headers, and the braces of "{$var}" string interpolation are not blocks.
     *
     * @param callable(string): bool $matches Called with each token's text.
     * @return list<list<string>> One header list per matching token, in source order.
     */
    private static function enclosingHeaders(callable $matches): array
    {
        $stack = array();
        $header = '';
        $found = array();
        foreach (token_get_all(self::source()) as $token) {
            $id = is_array($token) ? $token[0] : null;
            $text = is_array($token) ? $token[1] : $token;
            if ($matches($text)) {
                $found[] = array_values(array_filter($stack, static fn($h) => $h !== null));
            }
            if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }
            if ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $stack[] = null;
            } elseif ($text === '{') {
                $stack[] = trim(preg_replace('/\s+/', ' ', $header));
                $header = '';
            } elseif ($text === '}') {
                array_pop($stack);
                $header = '';
            } elseif ($text === ';') {
                $header = '';
            } else {
                $header .= $text;
            }
        }
        return $found;
    }

    /**
     * @param list<string> $headers Block headers around one token.
     * @param string       $needle  Text to look for.
     */
    private static function anyHeaderContains(array $headers, string $needle): bool
    {
        foreach ($headers as $header) {
            if (strpos($header, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /** Both Print plukliste buttons are decided by $visPlukliste alone, never by the mail setting. */
    public function testPrintPluklisteDoesNotNeedThePluklisteEmail(): void
    {
        $buttons = self::enclosingHeaders(static fn(string $t): bool => strpos($t, '>Print plukliste</button>') !== false);
        self::assertCount(2, $buttons, 'expected the side-panel and the button-row Print plukliste button');

        foreach ($buttons as $i => $headers) {
            self::assertTrue(self::anyHeaderContains($headers, '$visPlukliste'),
                "Print plukliste button $i is not inside the \$visPlukliste decision");
            self::assertFalse(self::anyHeaderContains($headers, 'pluklisteEmail'),
                "Print plukliste button $i is hidden without a plukliste e-mail: " . implode(' > ', $headers));
        }
    }

    /** The Send field needs both: an order that shows the buttons, and an address to send to. */
    public function testSendPluklisteNeedsTheEmailAndTheSameDecision(): void
    {
        $fields = self::enclosingHeaders(static fn(string $t): bool => strpos($t, '>Send plukliste</button>') !== false);
        self::assertCount(2, $fields, 'expected the side-panel and the button-row Send plukliste field');

        foreach ($fields as $i => $headers) {
            self::assertTrue(self::anyHeaderContains($headers, '$pluklisteEmail'),
                "Send plukliste field $i shows without a plukliste e-mail");
            self::assertTrue(self::anyHeaderContains($headers, '$visPlukliste'),
                "Send plukliste field $i is not inside the \$visPlukliste decision");
        }
    }

    /**
     * The kreditmax read and the check built on it sit inside if ($konto_id) { ... }, so an
     * order without a customer never reads kreditmax from whatever $r last held.
     */
    public function testTheCreditLimitIsOnlyReadForAnOrderWithAnAccount(): void
    {
        $lookup = self::enclosingHeaders(static fn(string $t): bool => strpos($t, 'select kreditmax from adresser') !== false);
        self::assertCount(1, $lookup, 'expected one kreditmax lookup in ordre.php');
        self::assertTrue(self::anyHeaderContains($lookup[0], 'if ($konto_id)'),
            'the kreditmax lookup runs without a konto_id');

        $check = self::enclosingHeaders(static fn(string $t): bool => strpos($t, 'Kreditloft overskrides med') !== false);
        self::assertCount(1, $check, 'expected one credit-limit warning in ordre.php');
        self::assertTrue(self::anyHeaderContains($check[0], 'if ($konto_id)'),
            'the credit-limit check runs without a konto_id');
    }

    /**
     * Evaluates the real "$kreditmax = ..." condition, lifted from the source, with every PHP
     * warning or notice thrown.
     *
     * @param mixed $r What the adresser lookup returned: a row or false.
     * @return mixed The value the condition assigns to $kreditmax.
     */
    private function kreditmax($r)
    {
        $src = self::source();
        $at = strpos($src, 'if ($kreditmax = ');
        self::assertNotFalse($at, 'the kreditmax condition is gone from ordre.php');

        $open = strpos($src, '(', $at);
        $depth = 0;
        for ($i = $open, $n = strlen($src); $i < $n; $i++) {
            if ($src[$i] === '(') {
                $depth++;
            } elseif ($src[$i] === ')' && --$depth === 0) {
                break;
            }
        }
        $expr = substr($src, $open + 1, $i - $open - 1);

        set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
            throw new ErrorException($str, 0, $no, $file, $line);
        });
        try {
            $kreditmax = null;
            return eval("return $expr;");
        } finally {
            restore_error_handler();
        }
    }

    public function testAMissingAdresserRowReadsAsNoLimit(): void
    {
        self::assertEquals(0, $this->kreditmax(false));
    }

    public function testARowWithoutKreditmaxReadsAsNoLimit(): void
    {
        self::assertEquals(0, $this->kreditmax(array('id' => '12', 'beskrivelse' => 'Kaffe')));
    }

    public function testAnEmptyOrNullLimitReadsAsNoLimit(): void
    {
        self::assertEquals(0, $this->kreditmax(array('kreditmax' => null)));
        self::assertEquals(0, $this->kreditmax(array('kreditmax' => '')));
        self::assertEquals(0, $this->kreditmax(array('kreditmax' => '0.000')));
    }

    /** PostgreSQL returns numeric columns as strings; a fractional limit keeps its decimals. */
    public function testAFractionalLimitKeepsItsDecimals(): void
    {
        self::assertSame(100.5, $this->kreditmax(array('kreditmax' => '100.500')));
        self::assertEquals(25000, $this->kreditmax(array('kreditmax' => '25000')));
    }
}
