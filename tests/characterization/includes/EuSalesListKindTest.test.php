<?php
// 20261113 CL/ASR Pin how the EU sales list and box B of Momsrubrikker tell goods from services (includes/euSalesListKind.php).
//                 Also which grupper art='MR' row eu_sales_accounts() reads the EU accounts from.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EuSalesListKindTest extends TestCase
{
    private object $state;

    /** The standard setup (importfiler/grupper.txt): box A purchases 1630/1710, box B sales 1050/1150. */
    private const STANDARD = ['goods' => '1050', 'services' => '1150', 'legacy_services' => '1710'];
    /** The old 5-digit chart: box A purchases 2800/2700, box B sales 1220/1200. */
    private const OLD_CHART = ['goods' => '1220', 'services' => '1200', 'legacy_services' => '2700'];
    /** A ledger that pointed box4 at its services sales account and left box5/box6 empty. */
    private const WORKAROUND = ['goods' => '', 'services' => '', 'legacy_services' => '1150'];
    /** No EU accounts in Indstillinger > Moms. */
    private const NONE = ['goods' => '', 'services' => '', 'legacy_services' => ''];

    protected function setUp(): void
    {
        // The stubbed database returns the MR rows in id order, as eu_sales_accounts() asks for.
        $this->state = (object) ['rows' => [], 'pending' => [], 'queries' => []];
        $GLOBALS['euSalesListKindState'] = $this->state;
        if (!function_exists('db_select')) {
            function db_select($sql, $location = '') {
                $state = $GLOBALS['euSalesListKindState'];
                $state->queries[] = $sql;
                $state->pending = $state->rows;
                return 'eu-sales-list-kind-result';
            }
            function db_fetch_array($result) {
                $state = $GLOBALS['euSalesListKindState'];
                return $state->pending ? array_shift($state->pending) : false;
            }
        }
        require_once __DIR__ . '/../../../includes/euSalesListKind.php';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['euSalesListKindState']);
    }

    /** @return array<string, array{0: string|null, 1: string|null, 2: array<string, string>, 3: string}> */
    public static function kinds(): array
    {
        return [
            // The reported bug: box4 is the box A purchase account 1710, so 1150 used to be listed as goods.
            'standard, services account' => [null, '1150', self::STANDARD, 'services'],
            'standard, goods account' => [null, '1050', self::STANDARD, 'goods'],
            'standard, other account' => [null, '1220', self::STANDARD, 'goods'],
            'standard, account with spaces' => ['', ' 1150 ', self::STANDARD, 'services'],
            'old chart, services account' => ['', '1200', self::OLD_CHART, 'services'],
            'old chart, goods account' => ['', '1220', self::OLD_CHART, 'goods'],
            // Ledgers that pointed box4 at their services sales account keep getting services.
            'box4 workaround' => ['', '1150', self::WORKAROUND, 'services'],
            'box4 workaround, other account' => ['', '1050', self::WORKAROUND, 'goods'],
            'box4 is also the box B goods account' => ['', '1050', ['goods' => '1050', 'services' => '', 'legacy_services' => '1050'], 'goods'],
            // The account the line was posted to wins over the item group's Varer/ydelser choice, so the list
            // follows the box B balances; the choice decides when the account does not tell.
            'ydelser group, goods account' => ['ydelser', '1050', self::STANDARD, 'goods'],
            'varer group, services account' => ['varer', '1150', self::STANDARD, 'services'],
            'ydelser group, other account' => [' ydelser ', '1320', self::NONE, 'services'],
            'ydelser group, unrelated account' => ['ydelser', '1220', self::STANDARD, 'services'],
            'varer group, box4 workaround account' => ['varer', '1150', self::WORKAROUND, 'goods'],
            // Box B goods and services on one account cannot tell them apart: the choice, else goods.
            'box5 equals box6' => ['', '7210', ['goods' => '7210', 'services' => '7210', 'legacy_services' => ''], 'goods'],
            'box5 equals box6, ydelser group' => ['ydelser', '7210', ['goods' => '7210', 'services' => '7210', 'legacy_services' => ''], 'services'],
            // Older ledgers kept an account number in VG box5; that is not a Varer/ydelser choice.
            'account number in box5' => ['2600', '1150', self::STANDARD, 'services'],
            'no EU accounts set up' => ['', '1150', self::NONE, 'goods'],
        ];
    }

    /** @param array{goods: string, services: string, legacy_services: string} $accounts */
    #[DataProvider('kinds')]
    public function testLinesWithAnEuSalesAccount(?string $groupType, ?string $account, array $accounts, string $kind): void
    {
        self::assertSame($kind, eu_sales_kind($groupType, $account, $accounts));
        self::assertSame($kind, eu_sales_list_kind($groupType, $account, $accounts));
    }

    /** @return array<string, array{0: string|null, 1: string|null, 2: array<string, string>, 3: string, 4: string}> */
    public static function withoutAccount(): array
    {
        return [
            // Posted to the ordinary sales account: not on the list once an EU services account is set up (#20160824).
            'standard' => ['', '', self::STANDARD, 'goods', ''],
            'only box6' => ['', null, ['goods' => '', 'services' => '1150', 'legacy_services' => ''], 'goods', ''],
            'only box4' => ['', '', self::WORKAROUND, 'goods', ''],
            'ydelser group, standard' => ['ydelser', '', self::STANDARD, 'services', ''],
            // Without one, every line counts, as goods unless its group says ydelser.
            'only box3/box5' => ['', '', ['goods' => '1050', 'services' => '', 'legacy_services' => ''], 'goods', 'goods'],
            'nothing set up' => ['', '', self::NONE, 'goods', 'goods'],
            'ydelser group, nothing set up' => ['ydelser', '', self::NONE, 'services', 'services'],
            'zero account' => ['', '0', self::STANDARD, 'goods', ''],
        ];
    }

    /** @param array{goods: string, services: string, legacy_services: string} $accounts */
    #[DataProvider('withoutAccount')]
    public function testLinesWithoutAnEuSalesAccount(?string $groupType, ?string $account, array $accounts, string $kind, string $listKind): void
    {
        self::assertSame($kind, eu_sales_kind($groupType, $account, $accounts));
        self::assertSame($listKind, eu_sales_list_kind($groupType, $account, $accounts));
    }

    /** @param array<int, array{0: int|null, 1: string|null, 2: string|null, 3: string|null}> $rows fiscal_year, box4, box5, box6 in id order */
    private function rows(array $rows): void
    {
        $this->state->rows = array_map(static function (array $row): array {
            return ['box4' => $row[1], 'box5' => $row[2], 'box6' => $row[3], 'fiscal_year' => $row[0] === null ? null : (string) $row[0]];
        }, $rows);
    }

    public function testTheAccountsAreReadFromTheRowOfTheFiscalYear(): void
    {
        // The last row (year 3) used to win, whatever the year of the list.
        $this->rows([[4, '2700', '1220', '1200'], [5, '1710', '1050', '1150'], [5, '9999', '9999', '9999'], [3, '', '', '']]);
        self::assertSame(['goods' => '1050', 'services' => '1150', 'legacy_services' => '1710'], eu_sales_accounts(5));
        self::assertSame(["select box4, box5, box6, fiscal_year from grupper where art = 'MR' order by id"], $this->state->queries);
    }

    public function testWithoutItsOwnRowTheLatestEarlierYearIsUsed(): void
    {
        $this->rows([[2, '2700', '1220', '1200'], [7, '', '', ''], [4, ' 1710 ', '1050 ', ' 1150']]);
        self::assertSame(['goods' => '1050', 'services' => '1150', 'legacy_services' => '1710'], eu_sales_accounts(6));
    }

    public function testWhenEveryRowIsLaterTheLatestYearIsUsed(): void
    {
        $this->rows([[9, '1710', '1050', '1150'], [7, '2700', '1220', '1200']]);
        self::assertSame(['goods' => '1050', 'services' => '1150', 'legacy_services' => '1710'], eu_sales_accounts(5));
    }

    public function testEmptyAndZeroBoxesAreNoAccount(): void
    {
        $this->rows([[5, '0', null, '00']]);
        self::assertSame(['goods' => '', 'services' => '', 'legacy_services' => ''], eu_sales_accounts(5));
    }

    public function testWithoutAnMrRowThereAreNoAccounts(): void
    {
        self::assertSame(['goods' => '', 'services' => '', 'legacy_services' => ''], eu_sales_accounts(5));
    }
}
