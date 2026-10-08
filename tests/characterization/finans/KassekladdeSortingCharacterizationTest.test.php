<?php
// --- tests/characterization/finans/KassekladdeSortingCharacterizationTest.test.php --- 20261005 LOE ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261005 LOE SST-856 Pin which requests may change the cash journal's saved sorting.
//
// CHARACTERIZATION suite for finans/kassekladde_includes/sorting.php, the rules behind the sorting the
// journal remembers per user (grupper ART='KASKL' kode='1', box1 = column, box4 = direction):
//
//   - a click on a header link carries a whitelisted column and a direction, and is the only request
//     allowed to replace the saved sorting;
//   - a form action (Gem, Enter, Opslag, Udlign, Simuler, Bogfoer) carries kksort without kkdir and
//     must persist nothing - it used to write 'asc', which silently reset a descending sort;
//   - a column or direction the page does not know falls back to the journal's default order, so a
//     stored or posted value cannot pick an arbitrary ORDER BY.
//
// The rules are pure functions, so this suite needs no database and no tenant.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class KassekladdeSortingCharacterizationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/finans/kassekladde_includes/sorting.php';
    }

    /**
     * A header click is the only request that may replace the saved sorting.
     *
     * @return array[]
     */
    public static function clickProvider(): array
    {
        return [
            'amount descending'          => ['amount', 'desc', ['sort' => 'amount', 'dir' => 'desc']],
            'amount ascending'           => ['amount', 'asc', ['sort' => 'amount', 'dir' => 'asc']],
            'bilag,transdate descending' => ['bilag,transdate', 'desc', ['sort' => 'bilag,transdate', 'dir' => 'desc']],
            'transdate,bilag ascending'  => ['transdate,bilag', 'asc', ['sort' => 'transdate,bilag', 'dir' => 'asc']],
            'pos ascending'              => ['pos', 'asc', ['sort' => 'pos', 'dir' => 'asc']],
        ];
    }

    /**
     * @dataProvider clickProvider
     */
    public function testAHeaderClickIsSaved(string $sort, string $dir, array $expected): void
    {
        $this->assertSame($expected, kk_sort_click($sort, $dir));
    }

    /**
     * The form action URL used to carry kksort with no direction; that must not touch the saved row.
     *
     * @return array[]
     */
    public static function noClickProvider(): array
    {
        return [
            'form action: kksort without kkdir' => ['amount', null],
            'form action: empty direction'      => ['amount', ''],
            'direction is not asc or desc'      => ['amount', 'descending'],
            'no sorting in the request at all'  => [null, null],
            'column not on the whitelist'       => ['beskrivelse', 'desc'],
            'column with trailing space'        => ['amount ', 'desc'],
            'whitelisted direction only'        => [null, 'desc'],
        ];
    }

    /**
     * @dataProvider noClickProvider
     */
    public function testOnlyAHeaderClickIsSaved($sort, $dir): void
    {
        $this->assertNull(kk_sort_click($sort, $dir));
    }

    public function testAStoredColumnIsNormalisedToTheDefaultOrder(): void
    {
        $this->assertSame('amount', kk_sort_key('amount'));
        $this->assertSame('pos', kk_sort_key('pos'));
        $this->assertSame('bilag,transdate', kk_sort_key('beskrivelse'));
        $this->assertSame('bilag,transdate', kk_sort_key(''));
        $this->assertSame('bilag,transdate', kk_sort_key(null));
        $this->assertSame('bilag,transdate', kk_sort_key('amount; drop table pool_files'));
    }

    public function testADirectionIsAlwaysAscendingOrDescending(): void
    {
        $this->assertSame('desc', kk_sort_direction('desc'));
        $this->assertSame('asc', kk_sort_direction('asc'));
        $this->assertSame('asc', kk_sort_direction(''));
        $this->assertSame('asc', kk_sort_direction(null));
        $this->assertSame('asc', kk_sort_direction('DESC'));
    }

    public function testEverySortKeyAHeaderLinkCanSendIsWhitelisted(): void
    {
        foreach (array('transdate,bilag', 'amount', 'bilag,transdate', 'pos') as $key) {
            $this->assertContains($key, kk_sort_columns(), "$key is sent by a header link");
            $this->assertSame($key, kk_sort_key($key), "$key survives normalisation");
        }
    }
}
