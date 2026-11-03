<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// 20261103 LOE SST-936 Pins the department, employee and project filter fragment the Finans reports
//                  append to their transaktioner query.
//
//                  The three reports - Kontokort, Regnskab/Balance and Posteringer pr. momskode - used
//                  to build this string themselves, and none of them escaped it, so a project code
//                  with an apostrophe broke the query ("syntax error at or near hare") and a value
//                  like ' or 1=1 -- returned every row. The block now lives in
//                  includes/stdFunc/dimensionSqlFragment.php and this test holds it to what the
//                  reports need: numbers cast, text escaped, the report's own wildcards intact, and
//                  the same fragment for ordinary values so the rows and the CSV do not change.
//
//                  The fragment is built against a live connection, so the escaping exercised here is
//                  the one the reports use; without a database the suite skips.
//
// History:
// 20261103 LOE SST-936 created.

if (!function_exists('str2low')) {
	/** The same transformation as std_func.php's str2low() for the characters this report sees. */
	function str2low($string)
	{
		$string = strtolower((string)$string);
		return str_replace(array('æ', 'ø', 'å'), array('æ', 'ø', 'å'), $string);
	}
}

require_once __DIR__ . '/../../../includes/stdFunc/dimensionSqlFragment.php';

final class DimensionSqlFragmentTest extends TestCase
{
    /**
     * db_escape_string() escapes through the live connection, so the fragment is built against a real
     * Postgres rather than a stand-in: the escaping under test is the one the reports use. Skips when
     * no database is reachable, like the other characterization suites.
     */
    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('pgsql')) {
            self::markTestSkipped('pgsql extension not loaded');
        }
        $pass = getenv('SALDI_CHAR_PGPASS');
        if (!$pass) {
            self::markTestSkipped('SALDI_CHAR_PGPASS is required for the escaping test');
        }
        $dsn = sprintf(
            'host=%s dbname=%s user=%s password=%s connect_timeout=3',
            getenv('SALDI_CHAR_PGHOST') ?: 'postgres',
            getenv('SALDI_CHAR_MASTER_DB') ?: 'saldi',
            getenv('SALDI_CHAR_PGUSER') ?: 'user',
            $pass
        );
        $connection = @pg_connect($dsn);
        if ($connection === false) {
            self::markTestSkipped('postgres not reachable (is the docker-compose stack up?)');
        }
        $GLOBALS['connection'] = $connection;
        $GLOBALS['db_type'] = 'postgres';
    }

    public static function tearDownAfterClass(): void
    {
        if (isset($GLOBALS['connection']) && $GLOBALS['connection']) {
            pg_close($GLOBALS['connection']);
            $GLOBALS['connection'] = null;
        }
    }
    public function testNoFilterMeansNoFragment(): void
    {
        self::assertSame('', dimensionSqlFragment(null, null, null, null, null, null));
        self::assertSame('', dimensionSqlFragment('', '', '', '', '', ''));
    }

    public function testTheDepartmentIsAnIntegerEvenWhenTheRequestIsNot(): void
    {
        self::assertSame('and afd = 936 ', dimensionSqlFragment('936', null, null, null, null, null));
        self::assertSame('and afd = 936 ', dimensionSqlFragment("936'", null, null, null, null, null));
        self::assertSame('and afd = 0 ', dimensionSqlFragment('0', null, null, null, null, null));
    }

    public function testTheEmployeeListCarriesNumbersOnly(): void
    {
        self::assertSame('and ansat in (8019,8020) ',
            dimensionSqlFragment(null, '8019', '8020', '8019,8020', null, null));
        // a garbage entry is dropped rather than quoted into the list
        self::assertSame('and ansat in (8019) ',
            dimensionSqlFragment(null, '8019', '8020', "8019,x' or 1=1", null, null));
        // an empty list falls back to the first id instead of "and (ansat=)"
        self::assertSame('and ansat in (8019) ',
            dimensionSqlFragment(null, '8019', '8020', '', null, null));
        // the single-employee path
        self::assertSame('and ansat = 8020 ', dimensionSqlFragment(null, '8020', null, null, null, null));
        self::assertSame('and ansat = 8020 ', dimensionSqlFragment(null, "8020'", null, null, null, null));
    }

    public function testAProjectWithAnApostropheStaysOneQuotedLiteral(): void
    {
        self::assertSame("and lower(projekt) LIKE 'o''hare' ",
            dimensionSqlFragment(null, null, null, null, "o'hare", null));
    }

    public function testAnInjectionAttemptCannotLeaveTheQuotedLiteral(): void
    {
        $fragment = dimensionSqlFragment(null, null, null, null, "' or 1=1 --", null);

        self::assertStringContainsString("LIKE ''' or 1=1 --'", $fragment);
        // the only 'or 1=1' sits inside the quoted pattern
        self::assertSame(1, substr_count($fragment, "' or 1=1 --'"));
        self::assertSame("and lower(projekt) LIKE ''' or 1=1 --' ", $fragment);
    }

    public function testTheProjectRangeEscapesBothBounds(): void
    {
        self::assertSame(
            "and lower(projekt) >= 'o''hare' and lower(projekt) <= 'o''hare2' ",
            dimensionSqlFragment(null, null, null, null, "o'hare", "o'hare2")
        );
    }

    public function testTheReportsOwnWildcardsSurvive(): void
    {
        // a single '?' becomes one-character wildcard plus a trailing '%'
        self::assertSame("and lower(projekt) LIKE 'sst9%' ",
            dimensionSqlFragment(null, null, null, null, 'sst9?', null));
        // '??' collapses to the same trailing '%' form
        self::assertSame("and lower(projekt) LIKE 'ab%' ",
            dimensionSqlFragment(null, null, null, null, 'ab??', null));
        // a term that does not end in '?' is matched as a prefix
        self::assertSame("and lower(projekt) LIKE 'sst936' ",
            dimensionSqlFragment(null, null, null, null, 'SST936', null));
    }

    public function testTheAliasPrefixReachesEveryColumn(): void
    {
        self::assertSame("and t.afd = 936 and t.ansat in (8020) and lower(t.projekt) LIKE 'sst%' ",
            dimensionSqlFragment(936, '8020', '8020', '8020', 'sst?', null, 't.'));
    }

    public function testOrdinaryValuesProduceTheFragmentTheReportsPrintedBefore(): void
    {
        // The shape the three renderers built by hand, kept byte for byte so the report rows and the
        // CSV stay identical for the values customers use today.
        self::assertSame("and afd = 936 and ansat in (8019,8020) and lower(projekt) LIKE 'sst%' ",
            dimensionSqlFragment(936, 8019, 8020, '8019,8020', 'SST?', null));
        self::assertSame("and afd = 936 and lower(projekt) >= 'a' and lower(projekt) <= 'z' ",
            dimensionSqlFragment('936', null, null, null, 'A', 'Z'));
    }
}
