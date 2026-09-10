<?php
// 20260911 LOE Cover SD-686: declared grid filter defaults are honoured and saved selections still win.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// grid.php defines updateCheckedValues() unconditionally (no function_exists guard),
// so it is loaded in a process of its own.
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class UpdateCheckedValuesTest extends TestCase
{
    /** @var string[] */
    private array $phpWarnings = array();

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/grid.php';
    }

    /**
     * Builds the filter definition a page hands to create_datagrid().
     *
     * @return array<int, array{filterName: string, joinOperator: string, options: array<int, array<string, mixed>>}>
     */
    private function declaredFilter($checked, $withCheckedKey = true)
    {
        $option = array('name' => 'Vis udgået');
        if ($withCheckedKey) {
            $option['checked'] = $checked;
        }
        $option['sqlOn'] = '';
        $option['sqlOff'] = "(v.lukket IS NULL OR v.lukket = '0')";

        return array(array(
            'filterName'   => 'Misc',
            'joinOperator' => 'and',
            'options'      => array($option),
        ));
    }

    /**
     * @param mixed $stored filter_setup as read from the datatables row (already decoded, or null)
     * @return mixed the resolved 'checked' state of the single option
     */
    private function resolvedChecked($checked, $stored, $withCheckedKey = true)
    {
        $setup = is_array($stored) ? $stored : array();
        $out = updateCheckedValues($this->declaredFilter($checked, $withCheckedKey), $setup);

        return $out[0]['options'][0]['checked'];
    }

    public static function nothingSavedProvider(): array
    {
        return array(
            'fresh grid row ({})'                  => array(array()),
            'stored as NULL'                       => array(null),
            'legacy definition list (pre-fix row)' => array(array(array('filterName' => 'Misc', 'options' => array()))),
            'empty string'                         => array(''),
        );
    }

    /**
     * The SD-686 defect: a stored setup that carries no selection map forced every
     * option back to '', so a page's declared default never took effect.
     */
    #[DataProvider('nothingSavedProvider')]
    public function testDeclaredCheckedIsHonouredWhenNothingIsSaved($stored): void
    {
        self::assertSame('checked', $this->resolvedChecked('checked', $stored));
    }

    #[DataProvider('nothingSavedProvider')]
    public function testDeclaredEmptyStaysUnchecked($stored): void
    {
        self::assertSame('', $this->resolvedChecked('', $stored));
    }

    public static function savedSelectionProvider(): array
    {
        return array(
            'saved ticked'   => array('{"Misc":{"Vis udgået":"checked"}}', 'checked'),
            'saved unticked' => array('{"Misc":{"Vis udgået":""}}', ''),
        );
    }

    #[DataProvider('savedSelectionProvider')]
    public function testSavedSelectionWinsOverDeclaredDefault($json, $expected): void
    {
        self::assertSame($expected, $this->resolvedChecked('checked', json_decode($json, true)));
    }

    public function testOptionWithoutDeclaredCheckedKeyResolvesToEmptyString(): void
    {
        self::assertSame('', $this->resolvedChecked(null, null, false));
    }

    /**
     * An unticked checkbox is not submitted at all, so without a hidden companion the
     * panel would store an empty map and a declared default could never be turned off.
     */
    public function testFilterPanelSubmitsUntickedOptionsSoAnUntickPersists(): void
    {
        $filters = $this->declaredFilter('checked');

        ob_start();
        render_filters('ordrestat', $filters, $filters);
        $html = ob_get_clean();

        $hidden = "<input type='hidden' name='filter[ordrestat][Misc][Vis udgået]' value=''>";
        $hiddenPos = strpos($html, $hidden);
        self::assertNotFalse($hiddenPos, 'Unticked options must still be submitted.');
        self::assertLessThan(strpos($html, "<input type='checkbox'"), $hiddenPos, 'The hidden value must precede the checkbox so a ticked box wins.');
    }

    public function testResolvingFilterStateRaisesNoPhpWarnings(): void
    {
        $this->phpWarnings = array();
        set_error_handler(function ($severity, $message, $file, $line) {
            $this->phpWarnings[] = $message . ' (' . $file . ':' . $line . ')';
            return true;
        }, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE);

        try {
            $this->resolvedChecked('checked', null);
            $this->resolvedChecked('', array('Misc' => array('Vis udgået' => '')));
            $this->resolvedChecked(null, null, false);
        } finally {
            restore_error_handler();
        }

        self::assertSame(array(), $this->phpWarnings);
    }
}
