<?php
// 20260911 LOE Cover SD-685: saving the column editor must keep the user's setup.
//                The editor posts customHeaderName (not headerName), the blank
//                "new column" row is not a column, and a column the user hid has to
//                stay hidden across the next save. Every grid implementation that
//                offers the editor is covered, because the function is duplicated.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// The implementations define save_column_setup()/merge_column_setup() under the same
// names, so every case is loaded in its own process. The database layer is stubbed in
// memory (see loadImplementation()); no tenant data is touched.
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SaveColumnSetupTest extends TestCase
{
    public static function implementationProvider(): array
    {
        return array(
            'includes/grid.php' => array('includes/grid.php'),
            'includes/orderFuncIncludes/grid_order.php' => array('includes/orderFuncIncludes/grid_order.php'),
            'includes/kreditorOrderFuncIncludes/creditor_orderlist_grid.php'
                => array('includes/kreditorOrderFuncIncludes/creditor_orderlist_grid.php'),
        );
    }

    /**
     * Stubs the database layer and loads one grid implementation.
     *
     * @param string $relativePath The implementation to load, relative to the repo root.
     * @return void
     */
    private function loadImplementation($relativePath)
    {
        $GLOBALS['bruger_id'] = 1;
        $GLOBALS['saldi_test_column_setup'] = null;
        $GLOBALS['saldi_test_last_query'] = null;

        if (!function_exists('db_escape_string')) {
            function db_escape_string($string)
            {
                return $string;
            }
        }
        if (!function_exists('db_select')) {
            function db_select($query, $errorFileLine)
            {
                return array('column_setup' => $GLOBALS['saldi_test_column_setup']);
            }
        }
        if (!function_exists('db_fetch_array')) {
            function db_fetch_array($result)
            {
                return $result;
            }
        }
        if (!function_exists('db_modify')) {
            function db_modify($query, $errorFileLine)
            {
                $GLOBALS['saldi_test_last_query'] = $query;
                if (preg_match("/column_setup = '(.*)' WHERE/s", $query, $match)) {
                    $GLOBALS['saldi_test_column_setup'] = $match[1];
                }
            }
        }

        require_once dirname(__DIR__, 3) . '/' . $relativePath;
    }

    /**
     * The stored column setup, decoded.
     *
     * @return array
     */
    private function storedRows()
    {
        return json_decode($GLOBALS['saldi_test_column_setup'], true);
    }

    /**
     * The code's column definitions for the fixture grid.
     *
     * @return array
     */
    private function codeColumns()
    {
        return array(
            array('field' => 'varenr', 'headerName' => 'Vare nr.', 'description' => '', 'width' => 1, 'align' => 'left'),
            array('field' => 'beskrivelse', 'headerName' => 'Navn', 'description' => '', 'width' => 3, 'align' => 'left'),
            array('field' => 'konto', 'headerName' => 'Konto', 'description' => '', 'width' => 1, 'align' => 'left'),
        );
    }

    /**
     * One row of the editor's POST, exactly as render_columns() writes it.
     *
     * @param string $pos The pos field ('-' means the delete button was pressed).
     * @param string $field The selected field.
     * @return array
     */
    private function postedRow($pos, $field)
    {
        return array(
            'pos' => $pos,
            'field' => $field,
            'customHeaderName' => '',
            'customDescription' => '',
            'width' => '100',
            'align' => 'left',
        );
    }

    /**
     * Saves the given rows for the fixture grid.
     *
     * @param array $rows The rows to post.
     * @return void
     */
    private function save($rows)
    {
        $_POST['rows']['varelst1'] = $rows;
        save_column_setup('varelst1');
    }

    /**
     * What the browser posts back for the editor it just rendered, including the blank
     * "new column" row that render_columns() always appends.
     *
     * @param array $stored The stored setup the editor was rendered from.
     * @return array
     */
    private function browserResave($stored)
    {
        $rows = array();
        $i = 0;
        foreach (merge_column_setup($stored, $this->codeColumns()) as $column) {
            $i++;
            $row = $this->postedRow((string)$i, $column['field']);
            $row['width'] = (string)($column['width'] * 100);
            $row['align'] = $column['align'];
            $rows[$i] = $row;
        }
        $i++;
        $rows[$i] = $this->postedRow((string)$i, '');

        return $rows;
    }

    /**
     * @param string $relativePath The implementation under test.
     * @return void
     */
    #[DataProvider('implementationProvider')]
    public function testSavingColumnsStoresThePostedSetup($relativePath): void
    {
        $this->loadImplementation($relativePath);

        $this->save(array(
            1 => $this->postedRow('1', 'varenr'),
            2 => $this->postedRow('2', 'beskrivelse'),
            3 => $this->postedRow('-', 'konto'),
        ));

        $stored = $this->storedRows();
        self::assertIsArray($stored, 'a save must never store an empty setup');
        self::assertSame(array('varenr', 'beskrivelse', 'konto'), array_column($stored, 'field'));
        self::assertSame(array(true, true, false), array_column($stored, 'visible'));
    }

    /**
     * @param string $relativePath The implementation under test.
     * @return void
     */
    #[DataProvider('implementationProvider')]
    public function testBlankTemplateRowIsNotStoredAsAColumn($relativePath): void
    {
        $this->loadImplementation($relativePath);

        $this->save(array(
            1 => $this->postedRow('1', 'varenr'),
            2 => $this->postedRow('2', ''),      // the blank "new column" row
        ));

        self::assertSame(array('varenr'), array_column($this->storedRows(), 'field'));
    }

    /**
     * @param string $relativePath The implementation under test.
     * @return void
     */
    #[DataProvider('implementationProvider')]
    public function testHiddenColumnStaysHiddenAfterTheNextSave($relativePath): void
    {
        $this->loadImplementation($relativePath);

        $this->save(array(
            1 => $this->postedRow('1', 'varenr'),
            2 => $this->postedRow('2', 'beskrivelse'),
            3 => $this->postedRow('-', 'konto'),
        ));
        $afterHide = $this->storedRows();
        self::assertNotContains('konto', array_column(merge_column_setup($afterHide, $this->codeColumns()), 'field'));

        // The editor does not render a hidden column, so the next save cannot post it.
        $this->save($this->browserResave($afterHide));

        $merged = merge_column_setup($this->storedRows(), $this->codeColumns());
        self::assertNotContains('konto', array_column($merged, 'field'), 'a hidden column must not come back on save');
        self::assertSame(array('varenr', 'beskrivelse'), array_column($merged, 'field'));
    }

    /**
     * @param string $relativePath The implementation under test.
     * @return void
     */
    #[DataProvider('implementationProvider')]
    public function testHidingTheFirstColumnSticks($relativePath): void
    {
        $this->loadImplementation($relativePath);

        // 'varenr' is the first option of the editor's select, which is what the blank
        // "new column" row used to submit.
        $this->save(array(
            1 => $this->postedRow('-', 'varenr'),
            2 => $this->postedRow('1', 'beskrivelse'),
            3 => $this->postedRow('-', 'konto'),
            4 => $this->postedRow('4', ''),
        ));

        // The hidden rows keep no meaningful order across PHP versions (usort is only
        // stable from PHP 8), so compare them as a set.
        $hidden = array_column(array_filter($this->storedRows(), function ($row) {
            return $row['visible'] === false;
        }), 'field');
        sort($hidden);
        self::assertSame(array('konto', 'varenr'), $hidden);

        $merged = merge_column_setup($this->storedRows(), $this->codeColumns());
        self::assertNotContains('varenr', array_column($merged, 'field'));
        self::assertSame(array('beskrivelse'), array_column($merged, 'field'));
    }

    /**
     * @param string $relativePath The implementation under test.
     * @return void
     */
    #[DataProvider('implementationProvider')]
    public function testReAddedColumnBecomesVisibleWithItsCustomHeader($relativePath): void
    {
        $this->loadImplementation($relativePath);

        $this->save(array(
            1 => $this->postedRow('-', 'varenr'),
            2 => $this->postedRow('1', 'beskrivelse'),
            3 => $this->postedRow('-', 'konto'),
        ));
        $row = $this->postedRow('2', 'varenr');
        $row['customHeaderName'] = 'Mit navn';
        $this->save(array(1 => $this->postedRow('1', 'beskrivelse'), 2 => $row));

        $stored = $this->storedRows();
        self::assertSame(1, count(array_filter($stored, function ($storedRow) {
            return $storedRow['field'] === 'varenr';
        })), 're-adding a column must replace its stale hidden row');

        $merged = merge_column_setup($stored, $this->codeColumns());
        self::assertSame(array('beskrivelse', 'varenr'), array_column($merged, 'field'));
        self::assertSame('Mit navn', $merged[1]['headerName']);
    }
}
