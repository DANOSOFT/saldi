<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20260921 CDX/MJ MB-54 Pins the * anchor in lager/varer.php's varenummer search. The block is
//                  inline page code, so it is lifted out of the source and run with $varenummer
//                  set, and the SQL it appends to $udvalg is asserted. That is the thing that was
//                  wrong: the clause was built with the term wrapped in %..% even when it already
//                  carried a wildcard.
final class VarenummerWildcardSearchTest extends TestCase
{
    /**
     * Runs the real varenummer branch of lager/varer.php for one search term.
     *
     * The block is bounded by `if ($varenummer) {` and the `$udvalg .= ")";` that closes it, so it
     * is extracted rather than duplicated - a rewrite of the clause that changed the anchoring
     * would be seen here.
     *
     * @param string $varenummer The term as typed in the Varenummer field.
     * @return string The SQL appended to $udvalg.
     */
    private static function clauseFor(string $varenummer): string
    {
        static $block = null;
        if ($block === null) {
            $src = file_get_contents(__DIR__ . '/../../../lager/varer.php');
            $start = strpos($src, 'if ($varenummer) {');
            self::assertNotFalse($start, 'varenummer search block not found');
            $close = strpos($src, '$udvalg .= ")";', $start);
            self::assertNotFalse($close, 'end of the varenummer search block not found');
            // The brace that closes `if ($varenummer) {` sits just after that statement.
            $brace = strpos($src, '}', $close);
            self::assertNotFalse($brace, 'closing brace of the varenummer block not found');
            $block = substr($src, $start, $brace - $start + 1);
            self::assertStringContainsString('LIKE', $block, 'the extracted block should build a LIKE clause');
            self::assertSame(
                substr_count($block, '{'),
                substr_count($block, '}'),
                'the extracted block should be brace-balanced'
            );
            // The term must still be escaped - that is an acceptance criterion - but the real
            // escaper needs a live connection, so the evaluated copy uses the equivalent quoting.
            // Asserting on the original block first means dropping the escaping would fail here.
            self::assertStringContainsString('db_escape_string($searchTerm)', $block, 'the term must stay escaped');
            $block = str_replace(
                '$searchTerm = db_escape_string($searchTerm);',
                '$searchTerm = str_replace("\'", "\'\'", $searchTerm);',
                $block
            );
        }

        $udvalg = '';
        eval($block);
        return $udvalg;
    }

    /**
     * The three cases from the acceptance criteria. Asserted on the varenr comparison the search
     * is built from, so a term that is anchored in one column but not another would fail.
     */
    #[DataProvider('searchTerms')]
    public function testVarenrIsAnchoredAsTheUserTyped(string $term, string $expectedLike): void
    {
        $clause = self::clauseFor($term);
        self::assertStringContainsString("LOWER(varenr) LIKE '$expectedLike'", $clause);
        self::assertStringContainsString("LOWER(varenr_alias) LIKE '$expectedLike'", $clause);
    }

    public static function searchTerms(): array
    {
        return [
            'trailing * means starts with' => ['123*', '123%'],
            'leading * means ends with'    => ['*123', '%123'],
            'no * still means contains'    => ['123', '%123%'],
            'both ends'                    => ['*123*', '%123%'],
            'wildcard in the middle'       => ['12*3', '12%3'],
        ];
    }

    /** The regression itself: a wildcard term must not come out as a plain "contains". */
    public function testWildcardTermIsNotTurnedIntoContains(): void
    {
        self::assertStringNotContainsString("LIKE '%123%%'", self::clauseFor('123*'), '"123*" must not search contains');
        self::assertStringNotContainsString("LIKE '%%123%'", self::clauseFor('*123'), '"*123" must not search contains');
    }

    /** stregkode stays an exact match at every term, per the acceptance criteria. */
    #[DataProvider('searchTerms')]
    public function testStregkodeStaysExact(string $term, string $expectedLike): void
    {
        $clause = self::clauseFor($term);
        $expected = "stregkode = '" . str_replace('*', '%', $term) . "'";
        self::assertStringContainsString($expected, $clause);
        self::assertStringNotContainsString('stregkode LIKE', $clause);
    }

    /**
     * Description and trademark are searched only without a wildcard. Unchanged by MB-54, pinned
     * because the anchoring change sits next to the guard that decides it.
     */
    public function testDescriptionIsSearchedOnlyWithoutAWildcard(): void
    {
        self::assertStringContainsString('lower(beskrivelse)', self::clauseFor('123'));
        self::assertStringContainsString('lower(trademark)', self::clauseFor('123'));

        foreach (['123*', '*123', '12*3'] as $term) {
            self::assertStringNotContainsString('lower(beskrivelse)', self::clauseFor($term), "$term should skip beskrivelse");
            self::assertStringNotContainsString('lower(trademark)', self::clauseFor($term), "$term should skip trademark");
        }
    }

    /** No dead code left behind: $uppTerm was computed and never used. */
    public function testTheDeadUpperCaseTermIsGone(): void
    {
        $src = file_get_contents(__DIR__ . '/../../../lager/varer.php');
        self::assertStringNotContainsString('$uppTerm', $src, '$uppTerm was never used');
    }
}
