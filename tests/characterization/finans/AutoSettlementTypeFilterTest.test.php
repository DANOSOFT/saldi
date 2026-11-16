<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// 20261028 CL/MMR SD-785 Created. The Konto selector on the settlement page (finans/autoudlign.php) offers "Alle debitorer" / "Alle kreditorer":
//                 autoSettlementSearchWhere() then filters on the account type alone. A type without an account number used to match nothing.
//                 The predicates are run against open posts by tests/test_auto_settlement.php (pdo_sqlite); this file pins them where CI runs.
// 20261028 CL/MMR SD-785 Runs in separate processes without the parent's global state: in the full suite another test has loaded includes/db_query.php,
//                 whose db_escape_string() needs a database connection, so the stub below must be the only db_escape_string() here (Codex round 1).
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AutoSettlementTypeFilterTest extends TestCase
{
    protected function setUp(): void
    {
        self::assertFalse(function_exists('db_escape_string'), 'includes/db_query.php leaked into this isolated process');
        eval('function db_escape_string($s) { return str_replace("\'", "\'\'", (string)$s); }');
        require_once __DIR__ . '/../../../finans/kassekladde_includes/autoSettlement.php';
    }

    /** @return array<string, array{mixed, mixed, string}> */
    public static function filters(): array
    {
        return [
            'all customers and suppliers' => ['', '', "adresser.art IN ('D', 'K') AND openpost.konto_nr = adresser.kontonr"],
            'all customers'               => ['', 'D', "adresser.art = 'D' AND openpost.konto_nr = adresser.kontonr"],
            'all suppliers, lower case'   => [' ', 'k ', "adresser.art = 'K' AND openpost.konto_nr = adresser.kontonr"],
            'one customer'                => ['1009', 'D', "openpost.konto_nr = '1009' AND adresser.kontonr = '1009' AND adresser.art = 'D'"],
            'unknown type'                => ['', 'F', '1 = 0'],
            'injected type'               => ['', "D' OR 1=1 --", '1 = 0'],
            'number without type'         => ['1009', '', '1 = 0'],
            'array instead of a string'   => [[], 'D', '1 = 0'],
        ];
    }

    #[DataProvider('filters')]
    public function testTheKontoSelectorBecomesThisFilter(mixed $account, mixed $type, string $expected): void
    {
        self::assertSame($expected, autoSettlementSearchWhere($account, $type));
    }

    public function testThePageOffersAndCarriesTheTypeChoice(): void
    {
        $page = (string)file_get_contents(__DIR__ . '/../../../finans/autoudlign.php');
        self::assertStringContainsString('<option value="type:D" data-account="" data-type="D"', $page, '"Alle debitorer" is gone from the Konto selector');
        self::assertStringContainsString('<option value="type:K" data-account="" data-type="K"', $page, '"Alle kreditorer" is gone from the Konto selector');
        self::assertSame(2, substr_count($page, 'kontotypeParam();'), 'the type choice is no longer carried to the next entry on Udlign and on Spring over');
        self::assertStringContainsString('let journalType = KONTOTYPE;', $page, 'the type chosen earlier in the journal is no longer the starting point on the next entry');
        self::assertStringContainsString('rememberJournalType();', $page, 'a type chosen on this entry is no longer remembered when a single account is picked afterwards');
    }
}
