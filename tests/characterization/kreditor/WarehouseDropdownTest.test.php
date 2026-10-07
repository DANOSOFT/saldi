<?php

declare(strict_types=1);

namespace Saldi\Tests\WarehouseDropdown;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261007 MJ SST-829 Pins that a leverandørordre offers each warehouse once.
//                  Havemøbelland saw every one of their ten warehouses twice in the Lager
//                  dropdown ("10: V", "10: V", "1: O", "1: O", ...). Warehouses are not
//                  tied to an accounting year, but creating one copied every grupper row
//                  with art != 'RA' into the new year, so each year added another LG row
//                  per warehouse and kreditor/ordre.php listed them all.
//
//                  kreditor/orderIncludes/openOrderData.php and selectOrder.php print one
//                  <option> per entry of $lager_nr/$lager_navn, so the de-duplication is
//                  pinned where those arrays are built. The loop is lifted out of the
//                  source and run against stub db functions, in this file's own namespace
//                  so the stubs cannot collide with another test's.

/** @var array<int, array<string, string>> Rows the next db_fetch_array() walk returns. */
$GLOBALS['sst829Rows'] = [];
/** @var array<int, string> Every statement the lifted code asked for. */
$GLOBALS['sst829Queries'] = [];

function db_select($sql, $location)
{
    $GLOBALS['sst829Queries'][] = $sql;
    return new \ArrayIterator($GLOBALS['sst829Rows']);
}

function db_fetch_array($result)
{
    if (!$result instanceof \ArrayIterator || !$result->valid()) {
        return false;
    }
    $row = $result->current();
    $result->next();
    return $row;
}

final class WarehouseDropdownTest extends TestCase
{
    private const ORDRE = __DIR__ . '/../../../kreditor/ordre.php';

    private static ?string $src = null;

    private static function source(): string
    {
        if (self::$src === null) {
            $src = file_get_contents(self::ORDRE);
            self::assertNotFalse($src, 'could not read kreditor/ordre.php');
            self::$src = $src;
        }
        return self::$src;
    }

    /**
     * Runs the real warehouse-list loop over the given grupper rows.
     *
     * @param array<int, array<string, string>> $rows In the order the query would return them.
     * @return array<int, string> The rendered "<kodenr>: <beskrivelse>" options.
     */
    private function dropdownOptions(array $rows): array
    {
        $src = self::source();

        $start = strpos($src, '$qtxt = "select kodenr,beskrivelse from grupper where art=\'LG\' ";');
        self::assertNotFalse($start, 'the warehouse list query is gone from kreditor/ordre.php');
        // Through the end of the while loop that fills the arrays.
        $end = strpos($src, '$lager = (int)$lager;', $start);
        self::assertNotFalse($end, 'cannot find the end of the warehouse loop');

        $GLOBALS['sst829Rows'] = $rows;
        $GLOBALS['sst829Queries'] = [];

        $regnaar = 2026;
        $x = 0;
        $lager_nr = [];
        $lager_navn = [];

        eval('namespace ' . __NAMESPACE__ . '; ' . substr($src, $start, $end - $start));

        // What openOrderData.php / selectOrder.php then print, one per entry.
        $options = [];
        for ($i = 0; $i < count($lager_nr); $i++) {
            $options[] = $lager_nr[$i] . ': ' . $lager_navn[$i];
        }
        return $options;
    }

    /** The reported case: two accounting years, so two rows per warehouse. */
    public function testEachWarehouseIsOfferedOnceWhenAYearHasBeenCopied(): void
    {
        $options = $this->dropdownOptions([
            ['kodenr' => '1', 'beskrivelse' => 'Odense', 'fiscal_year' => '2026'],
            ['kodenr' => '1', 'beskrivelse' => 'Odense', 'fiscal_year' => '2025'],
            ['kodenr' => '2', 'beskrivelse' => 'Esbjerg', 'fiscal_year' => '2026'],
            ['kodenr' => '2', 'beskrivelse' => 'Esbjerg', 'fiscal_year' => '2025'],
            ['kodenr' => '10', 'beskrivelse' => 'Værksted', 'fiscal_year' => '2026'],
            ['kodenr' => '10', 'beskrivelse' => 'Værksted', 'fiscal_year' => '2025'],
        ]);

        self::assertSame(['1: Odense', '2: Esbjerg', '10: Værksted'], $options);
    }

    /**
     * The customer reported the earlier fix as "ikke helt i mål". De-duplicating on
     * (kodenr, beskrivelse) - which productLookup.php and vareliste.php do - still shows a
     * warehouse twice once a later year's copy has been renamed. Keyed on kodenr, it does not.
     */
    public function testARenamedCopyIsStillOnlyOneWarehouse(): void
    {
        $options = $this->dropdownOptions([
            ['kodenr' => '1', 'beskrivelse' => 'Odense Nord', 'fiscal_year' => '2026'],
            ['kodenr' => '1', 'beskrivelse' => 'Odense', 'fiscal_year' => '2025'],
        ]);

        self::assertCount(1, $options, 'a renamed fiscal-year copy is still the same warehouse');
        // The query orders the current year first, so that is the row kept.
        self::assertSame(['1: Odense Nord'], $options);
    }

    /** Already-clean data is unchanged - one row per warehouse stays one option. */
    public function testAlreadyCleanDataIsUnchanged(): void
    {
        $options = $this->dropdownOptions([
            ['kodenr' => '1', 'beskrivelse' => 'Odense', 'fiscal_year' => '0'],
            ['kodenr' => '2', 'beskrivelse' => 'Esbjerg', 'fiscal_year' => '0'],
        ]);

        self::assertSame(['1: Odense', '2: Esbjerg'], $options);
    }

    /** A tenant with no warehouses still renders nothing rather than erroring. */
    public function testNoWarehousesYieldsNoOptions(): void
    {
        self::assertSame([], $this->dropdownOptions([]));
    }

    /**
     * The de-duplication keeps the first row per warehouse, so the ordering is what decides
     * which definition is shown. It must prefer the current year, then the newest.
     */
    public function testTheQueryPrefersTheCurrentYearThenTheNewestRow(): void
    {
        $this->dropdownOptions([['kodenr' => '1', 'beskrivelse' => 'Odense', 'fiscal_year' => '0']]);

        self::assertCount(1, $GLOBALS['sst829Queries'], 'expected exactly one warehouse query');
        $sql = $GLOBALS['sst829Queries'][0];

        self::assertStringContainsString("art='LG'", $sql);
        self::assertStringContainsString('order by kodenr', $sql, 'the dropdown must stay in warehouse order');
        self::assertStringContainsString('case when fiscal_year = 2026 then 0 else 1 end', $sql,
            'the current accounting year is no longer preferred, so the shown name can disagree with Indstillinger');
        self::assertStringContainsString('coalesce(fiscal_year,0) desc', $sql,
            'the newest row is no longer preferred');
        self::assertStringContainsString('id desc', $sql, 'the tie-break on id is gone');
    }

    /** Creating an accounting year must not copy warehouses into it. */
    public function testCreatingAnAccountingYearDoesNotCopyWarehouses(): void
    {
        $src = file_get_contents(__DIR__ . '/../../../systemdata/regnskabskort.php');
        self::assertNotFalse($src, 'could not read systemdata/regnskabskort.php');

        self::assertStringContainsString("and art != 'RA' and art != 'LG'", $src,
            'the fiscal-year copy no longer excludes warehouses, so each new year adds a row per warehouse');
    }

    /** A warehouse saved under Indstillinger is stored year-agnostically. */
    public function testANewWarehouseIsSavedWithoutAnAccountingYear(): void
    {
        $src = file_get_contents(__DIR__ . '/../../../systemdata/syssetupIncludes/saveData.php');
        self::assertNotFalse($src, 'could not read saveData.php');

        self::assertStringContainsString("\$gruppeAar = (\$art[\$x] == 'LG') ? '0' : \$regnaar;", $src,
            'warehouses are saved with the current accounting year again');
        self::assertStringContainsString("'\$box13[\$x]','\$box14[\$x]','\$gruppeAar')", $src,
            'the insert no longer uses the year-agnostic value');

        // And the existence check must not scope LG to the current year, or saving the same
        // warehouse twice would insert a second fiscal_year 0 row.
        self::assertStringContainsString("if (\$art[\$x] != 'LG') {", $src,
            'the duplicate check scopes warehouses to the current year again, which lets a '
            . 'second save add another row');
    }

    /**
     * Deleting the rows that already exist is deliberately not part of this change: PR #743
     * chose to tolerate duplicates and pick one per warehouse at read time. This pins that
     * nothing here started deleting grupper rows behind that decision.
     */
    public function testThisChangeDeletesNoWarehouseRows(): void
    {
        foreach ([
            self::ORDRE,
            __DIR__ . '/../../../systemdata/regnskabskort.php',
            __DIR__ . '/../../../systemdata/syssetupIncludes/saveData.php',
        ] as $path) {
            $src = file_get_contents($path);
            self::assertDoesNotMatchRegularExpression(
                "/delete\s+from\s+grupper[^;]*art\s*=\s*'LG'/i",
                (string)$src,
                basename($path) . ' deletes LG rows; that was left to a separate ticket'
            );
        }
    }
}
