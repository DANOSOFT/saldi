<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261002 MJ SST-814 Pins the short-search minimum on both sides of the account lookup.
//                  finans/kassekladde_includes/accountSearch.php searches adresser with
//                  ILIKE '%term%'; a trigram index cannot match fewer than three
//                  characters, so below that the query is a full table scan however the
//                  table is indexed. Review of PR #691 found the minimum was enforced only
//                  in two of the three JS callers, so a direct GET - or a future caller -
//                  got the unindexed scan back. The gate now lives in the endpoint, and
//                  this pins it there and in all three clients.
//
//                  The endpoint is a request script, not a function, so its gate is lifted
//                  out of the source and evaluated here rather than reimplemented: a
//                  restatement could pass while the real condition is wrong.
final class AccountSearchShortTermGateTest extends TestCase
{
    private const ENDPOINT = __DIR__ . '/../../../finans/kassekladde_includes/accountSearch.php';

    /** @return array<string, string> the three callers that reach the endpoint */
    private static function clients(): array
    {
        return [
            'ordreAutocomplete.js' => __DIR__ . '/../../../javascript/ordreAutocomplete.js',
            'kreditorOrdreAutocomplete.js' => __DIR__ . '/../../../javascript/kreditorOrdreAutocomplete.js',
            'accountAutocomplete.js' => __DIR__ . '/../../../javascript/accountAutocomplete.js',
        ];
    }

    /**
     * Evaluates the endpoint's own gate condition, lifted from the source.
     *
     * @param string $search The search term as it arrives from the request.
     * @param string $type   finance, debitor or kreditor.
     * @param int    $exact  The exact=1 flag.
     * @return bool Whether the endpoint refuses the term without querying.
     */
    private function gateRefuses(string $search, string $type, int $exact = 0): bool
    {
        $src = file_get_contents(self::ENDPOINT);
        self::assertNotFalse($src, 'could not read accountSearch.php');

        $start = strpos($src, '$searchTooShort = ');
        self::assertNotFalse($start, 'the gate condition is gone from accountSearch.php');
        $end = strpos($src, ';', $start);
        self::assertNotFalse($end, 'the gate condition is unterminated');
        $expression = substr($src, $start, $end - $start + 1);

        $minStart = strpos($src, '$minSearchLength = ');
        self::assertNotFalse($minStart, 'the minimum is gone from accountSearch.php');
        $minimum = (int)substr($src, $minStart + 19, 2);

        $minSearchLength = $minimum;
        eval($expression);

        return (bool)$searchTooShort;
    }

    /** The minimum the endpoint enforces, read from its source. */
    private function endpointMinimum(): int
    {
        $src = file_get_contents(self::ENDPOINT);
        $at = strpos($src, '$minSearchLength = ');
        self::assertNotFalse($at, 'the minimum is gone from accountSearch.php');
        return (int)substr($src, $at + 19, 2);
    }

    /** Three characters, matching what a trigram index can serve. */
    public function testEndpointMinimumIsThree(): void
    {
        self::assertSame(3, $this->endpointMinimum());
    }

    /**
     * A short term that searches adresser must be refused before any query runs.
     *
     * @param string $search The term.
     */
    #[DataProvider('shortAdresserTerms')]
    public function testShortAdresserSearchesAreRefused(string $search, string $type): void
    {
        self::assertTrue($this->gateRefuses($search, $type),
            "'$search' on type=$type should be refused");
    }

    /** @return array<string, array{string, string}> */
    public static function shortAdresserTerms(): array
    {
        return [
            'one character, debitor'   => ['a', 'debitor'],
            'two characters, debitor'  => ['ab', 'debitor'],
            'one digit, debitor'       => ['1', 'debitor'],
            'two digits, debitor'      => ['12', 'debitor'],
            'one character, kreditor'  => ['a', 'kreditor'],
            'two characters, kreditor' => ['ab', 'kreditor'],
            // Two characters of Danish are two characters, not the four bytes they occupy.
            'two Danish letters'       => ['æø', 'debitor'],
        ];
    }

    /**
     * Everything else still reaches the query.
     *
     * @param string $search The term.
     */
    #[DataProvider('allowedTerms')]
    public function testAllowedSearchesReachTheQuery(string $search, string $type, int $exact): void
    {
        self::assertFalse($this->gateRefuses($search, $type, $exact),
            "'$search' on type=$type (exact=$exact) should reach the query");
    }

    /** @return array<string, array{string, string, int}> */
    public static function allowedTerms(): array
    {
        return [
            'three characters'          => ['abc', 'debitor', 0],
            'four digits'               => ['1234', 'debitor', 0],
            'three Danish letters'      => ['æøå', 'debitor', 0],
            // An empty term is a browse, not a search: no ILIKE is built at all.
            'empty, debitor'            => ['', 'debitor', 0],
            // kontoplan is small and its account numbers are legitimately short.
            'one digit, finance'        => ['1', 'finance', 0],
            'two digits, finance'       => ['10', 'finance', 0],
            // An equality lookup on a full account number is indexable at any length.
            'short but exact, debitor'  => ['7', 'debitor', 1],
        ];
    }

    /**
     * The condition has to be wired to an early exit, not merely computed.
     *
     * Evaluating the expression alone would still pass if the branch it guards were
     * disabled, which is exactly the shape the reported bypass had: the knowledge was
     * present, the refusal was not. So this pins the order of the four landmarks -
     * condition, branch, exit, first query - in the source.
     */
    public function testTheGateExitsBeforeAnyQueryRuns(): void
    {
        $src = file_get_contents(self::ENDPOINT);
        self::assertNotFalse($src, 'could not read accountSearch.php');

        $condition = strpos($src, '$searchTooShort = ');
        self::assertNotFalse($condition, 'the gate condition is gone');

        $branch = strpos($src, 'if ($searchTooShort) {', $condition);
        self::assertNotFalse($branch,
            'the gate condition is computed but nothing branches on it');

        $exit = strpos($src, 'exit;', $branch);
        self::assertNotFalse($exit, 'the gate branch does not exit');

        $firstQuery = strpos($src, 'db_select(');
        self::assertNotFalse($firstQuery, 'accountSearch.php no longer queries at all');

        self::assertLessThan($firstQuery, $exit,
            'the gate exits after a query has already run, which is what it exists to prevent');

        // The exit must be inside the branch, not somewhere past its closing brace.
        $closingBrace = strpos($src, "
}", $branch);
        self::assertNotFalse($closingBrace, 'the gate branch is unterminated');
        self::assertLessThan($closingBrace, $exit, 'the exit is outside the gate branch');
    }

    /** Each client must carry the same minimum, so none sends a request the endpoint refuses. */
    #[DataProvider('clientFiles')]
    public function testEachClientCarriesTheSameMinimum(string $label, string $path): void
    {
        $src = file_get_contents($path);
        self::assertNotFalse($src, "could not read $label");
        self::assertMatchesRegularExpression(
            '/minAccountSearchLength:\s*' . $this->endpointMinimum() . '\b/',
            $src,
            "$label does not carry the endpoint's minimum"
        );
    }

    /** @return array<string, array{string, string}> */
    public static function clientFiles(): array
    {
        $out = [];
        foreach (self::clients() as $label => $path) {
            $out[$label] = [$label, $path];
        }
        return $out;
    }

    /**
     * Only the account field gained a minimum. The other field types query small tables and
     * listed their options when the field was focused empty; review of #691 caught that
     * sending them through the account minimum silently took that away.
     */
    public function testOnlyTheAccountFieldGainedAMinimum(): void
    {
        foreach (['ordreAutocomplete.js', 'kreditorOrdreAutocomplete.js'] as $label) {
            $src = file_get_contents(self::clients()[$label]);

            $helper = strpos($src, 'function minLengthFor(');
            self::assertNotFalse($helper, "$label lost its shared length helper");
            $body = substr($src, $helper, 900);

            self::assertStringContainsString("if (type === 'customer') return CONFIG.minAccountSearchLength;", $body,
                "$label no longer holds the account field to the endpoint's minimum");
            self::assertStringContainsString("if (type === 'item') return CONFIG.minSearchLength;", $body,
                "$label changed the item field's own pre-existing minimum");
            // Everything else: zero, so an empty focus still lists options as it did before.
            self::assertMatchesRegularExpression('/
\s*return 0;/', $body,
                "$label gates the remaining field types, which stops them listing on empty focus");
        }
    }

    /**
     * A response that arrives for a value the field no longer holds must not be painted.
     *
     * The race predates this ticket, but the new minimum made it persistent instead of
     * self-correcting: a value dropping below the minimum sends no further request, so
     * nothing arrives afterwards to replace a stale dropdown.
     */
    #[DataProvider('clientFiles')]
    public function testEachClientDropsStaleSearchResponses(string $label, string $path): void
    {
        $src = file_get_contents($path);
        self::assertNotFalse($src, "could not read $label");

        self::assertStringContainsString('let searchSeq = 0;', $src,
            "$label has no request token, so a slow response can paint stale rows");

        $captures = substr_count($src, 'const seq = searchSeq;');
        $guards = substr_count($src, 'if (seq !== searchSeq) return;');
        $bumps = substr_count($src, 'searchSeq++;');
        $dispatches = substr_count($src, 'debounceTimer = setTimeout(');

        self::assertGreaterThan(0, $captures,
            "$label does not capture the token with the request");

        // Both arms of every guarded request: a stale failure must not close the dropdown
        // that a newer request has already filled, any more than a stale success may fill it.
        self::assertSame(2 * $captures, $guards,
            "$label leaves an arm of a search response unguarded ($guards guards for $captures requests, expected " . (2 * $captures) . ")");

        // Every debounced search entry point has to advance the token, or a keystroke it
        // handles leaves an older in-flight response free to land.
        self::assertSame($dispatches, $bumps,
            "$label has $dispatches debounced searches but only $bumps advance the token");

        // The token advances on input, before the request captures it - not after. These
        // files hold other, unrelated fetches, so this compares against the capture point
        // rather than the first fetch in the file.
        self::assertLessThan(strpos($src, 'const seq = searchSeq;'), strpos($src, 'searchSeq++;'),
            "$label advances the token only after dispatching, which leaves the race open");
    }

    /**
     * The two order-form clients gate every field type through one helper, so the account
     * field cannot be the only one left ungated - which is how the original defect arose.
     */
    public function testOrderFormClientsGateThroughOneHelper(): void
    {
        foreach (['ordreAutocomplete.js', 'kreditorOrdreAutocomplete.js'] as $label) {
            $src = file_get_contents(self::clients()[$label]);
            self::assertStringContainsString('function minLengthFor(', $src,
                "$label lost its shared length helper");
            self::assertStringContainsString('value.length < minLengthFor(input.autocompleteType)', $src,
                "$label no longer gates every type through the helper");
            self::assertStringNotContainsString("autocompleteType === 'item' && value.length", $src,
                "$label still gates only the item field, which is the original defect");
        }
    }
}
