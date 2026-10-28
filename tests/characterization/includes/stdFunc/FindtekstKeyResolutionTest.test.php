<?php
// 20261023 CL/NTR Cover findtekst() string-key resolution: key-row translation, the placeholder
//                  rule (tekst == tekst_key and the '-' sentinel), legacy content-match fallthrough,
//                  self-heal insert/update, unchanged numeric-id lookups and pre-column safety.
// 20261028 CDX/NTR Cover pipe fallback when an unresolved key cannot self-heal.
// 20261028 CDX/NTR Cover legacy CSV literal matches in every language column.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// Runs in a separate process per test: other suites in the same run may already have loaded the
// real db_select()/db_modify() (includes/db_query.php), which would make the function_exists()
// guards in findtekst() skip these stubs and hit an absent database. A fresh process also resets
// the request-static $hasKeyColumn so the pre-column case can be exercised.
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FindtekstKeyResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $GLOBALS['bruger_id'] = 1;
        $GLOBALS['db'] = 'testpg';
        $GLOBALS['sqdb'] = 'sqlite_unused';
        $GLOBALS['db_encode'] = 'UTF8';
        $GLOBALS['webservice'] = null;
        $GLOBALS['findtekstStub'] = (object) [
            'hasKeyColumn' => true,
            'keyRow' => false,
            'contentRow' => false,
            'numericRow' => false,
            'queries' => [],
            'writes' => [],
        ];

        if (!function_exists('db_escape_string')) {
            function db_escape_string($s) { return addslashes((string) $s); }
        }
        if (!function_exists('db_fetch_array')) {
            function db_fetch_array($result) { return $result; }
        }
        if (!function_exists('db_select')) {
            function db_select($sql, $location) {
                $state = $GLOBALS['findtekstStub'];
                $state->queries[] = $sql;
                if (strpos($sql, 'information_schema.columns') !== false) {
                    if (strpos($sql, "column_name='tekst_key'") !== false) {
                        return $state->hasKeyColumn ? ['column_name' => 'tekst_key'] : false;
                    }
                    return ['column_name' => 'tekst_id']; // bare table-existence probe
                }
                if (strpos($sql, 'where tekst_key=') !== false) {
                    return $state->keyRow;
                }
                if (strpos($sql, 'where tekst = ') !== false) {
                    return $state->contentRow;
                }
                if (strpos($sql, 'where tekst_id=') !== false) {
                    return $state->numericRow;
                }
                return false;
            }
        }
        if (!function_exists('db_modify')) {
            function db_modify($sql, $location) { $GLOBALS['findtekstStub']->writes[] = $sql; }
        }

        // findtekst() reads ../importfiler/*.csv relative to the running page; point the relative
        // path at the real repo so the CSV fallback finds tekster.csv instead of warning.
        chdir(dirname(__DIR__, 4) . '/systemdata');
        require_once dirname(__DIR__, 4) . '/includes/stdFunc/findTxt.php';
    }

    public function testKeyRowRealTranslationIsReturnedAndPipeFallbackIgnored(): void
    {
        $GLOBALS['findtekstStub']->keyRow = ['id' => '10', 'tekst' => 'Accept'];
        self::assertSame('Accept', findtekst('common.accept|Fallback', 2));
        self::assertSame([], $GLOBALS['findtekstStub']->writes);
    }

    public function testPlaceholderRowIsNotReturnedAndPipeFallbackUpgradesIt(): void
    {
        // A row whose tekst equals its key is a not-yet-translated placeholder.
        $GLOBALS['findtekstStub']->keyRow = ['id' => '10', 'tekst' => 'common.accept'];
        self::assertSame('Accept', findtekst('common.accept|Accept', 2));
        $writes = $GLOBALS['findtekstStub']->writes;
        self::assertCount(1, $writes);
        self::assertStringContainsString("update tekster set tekst='Accept' where id='10'", $writes[0]);
    }

    public function testDashSentinelKeyRowResolvesToEmptyString(): void
    {
        $GLOBALS['findtekstStub']->keyRow = ['id' => '10', 'tekst' => '-'];
        self::assertSame('', findtekst('common.hidden', 2));
        self::assertSame([], $GLOBALS['findtekstStub']->writes);
    }

    public function testLegacyContentMatchFallsThroughToNumericRow(): void
    {
        // Caller passed literal text; it matches an existing row's tekst and resolves via tekst_id.
        $GLOBALS['findtekstStub']->contentRow = ['tekst_id' => '55'];
        $GLOBALS['findtekstStub']->numericRow = ['id' => '99', 'tekst' => 'Current text'];
        self::assertSame('Current text', findtekst('Current text', 1));
    }

    public function testLegacyCsvContentMatchChecksEveryLanguageColumn(): void
    {
        $GLOBALS['findtekstStub']->numericRow = ['id' => '1', 'tekst' => 'Resolved text'];

        foreach ([1 => 'Dansk', 2 => 'English', 3 => 'Norsk'] as $languageID => $literal) {
            self::assertSame('Resolved text', findtekst($literal, $languageID));
            self::assertTrue($this->queriesContain("where tekst_id='1' and sprog_id = '$languageID'"));
        }
    }

    public function testUnknownKeySelfHealsWithPipeFallback(): void
    {
        self::assertSame('Hello', findtekst('brand.newkey|Hello', 2));
        $writes = $GLOBALS['findtekstStub']->writes;
        self::assertCount(1, $writes);
        self::assertStringContainsString('insert into tekster(sprog_id,tekst_key,tekst)', $writes[0]);
        self::assertStringContainsString("'brand.newkey'", $writes[0]);
        self::assertStringContainsString("'Hello'", $writes[0]);
    }

    public function testUnknownKeyReturnsPipeFallbackWhenSelfHealIsUnavailable(): void
    {
        $GLOBALS['sqdb'] = $GLOBALS['db'];
        self::assertSame('Hello', findtekst('brand.newkey|Hello', 2));
        self::assertSame([], $GLOBALS['findtekstStub']->writes);
    }

    public function testNumericIdLookupIsUnchanged(): void
    {
        $GLOBALS['findtekstStub']->numericRow = ['id' => '5', 'tekst' => 'Luk'];
        self::assertSame('Luk', findtekst('917', 1));
        // A numeric id never touches the key column.
        foreach ($GLOBALS['findtekstStub']->queries as $q) {
            self::assertStringNotContainsString('where tekst_key=', $q);
        }
    }

    public function testPreColumnTenantUsesLegacyPathWithNoKeySql(): void
    {
        // A tenant that has not yet run betweenUpdates has no tekst_key column.
        $GLOBALS['findtekstStub']->hasKeyColumn = false;
        self::assertSame('common.foo', findtekst('common.foo', 2));
        self::assertSame([], $GLOBALS['findtekstStub']->writes);
        foreach ($GLOBALS['findtekstStub']->queries as $q) {
            self::assertStringNotContainsString('where tekst_key=', $q);
        }
    }

    private function queriesContain(string $needle): bool
    {
        foreach ($GLOBALS['findtekstStub']->queries as $query) {
            if (str_contains($query, $needle)) {
                return true;
            }
        }
        return false;
    }
}
