<?php
// 20260911 LOE Cover SD-685: the grid's stored setup separates display text from data —
//                filter selections keyed independently of their labels, and column
//                headers/description following the code (translations included).

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// grid.php defines these functions unconditionally, so it is loaded in its own process.
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class GridSetupTranslationTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/grid.php';
    }

    private function option($checked, $optionKey, $name)
    {
        $option = array('name' => $name, 'checked' => $checked, 'sqlOn' => '', 'sqlOff' => 'x');
        if ($optionKey !== null) {
            $option['optionKey'] = $optionKey;
        }

        return $option;
    }

    private function filter($options, $filterKey, $name)
    {
        $filter = array('filterName' => $name, 'joinOperator' => 'and', 'options' => $options);
        if ($filterKey !== null) {
            $filter['filterKey'] = $filterKey;
        }

        return array($filter);
    }

    private function codeColumn($field, $headerName, $description = '', $options = array())
    {
        return array_merge(array(
            'field' => $field, 'headerName' => $headerName, 'description' => $description,
            'width' => 1, 'align' => 'left', 'hidden' => false,
        ), $options);
    }

    // ---------------------------------------------------------------- filters

    public function testSelectionKeyedByFilterKeysSurvivesTranslationAndLanguageSwitch(): void
    {
        $savedInDanish = array('misc' => array('show_discontinued' => 'checked'));
        $translatedPage = $this->filter(array($this->option('', 'show_discontinued', 'Show discontinued')), 'misc', 'Misc');

        $resolved = updateCheckedValues($translatedPage, $savedInDanish);

        self::assertSame('checked', $resolved[0]['options'][0]['checked']);
    }

    public function testLegacyTextKeyedRowIsStillHonoured(): void
    {
        $savedByText = array('Diverse' => array('Vis udgåede' => 'checked'));
        $untranslatedPage = $this->filter(array($this->option('checked', null, 'Vis udgåede')), null, 'Diverse');

        $resolved = updateCheckedValues($untranslatedPage, $savedByText);

        self::assertSame('checked', $resolved[0]['options'][0]['checked']);
    }

    public function testTranslatedPageWithoutKeysFallsBackToTheDeclaredDefault(): void
    {
        $savedByText = array('Diverse' => array('Vis udgåede' => 'checked'));
        $translatedPage = $this->filter(array($this->option('', null, 'Show discontinued')), null, 'Misc');

        $resolved = updateCheckedValues($translatedPage, $savedByText);

        self::assertSame('', $resolved[0]['options'][0]['checked']);
    }

    public function testFilterPanelNamesTheFieldAfterTheKeysWhenDeclared(): void
    {
        $filters = $this->filter(array($this->option('', 'show_discontinued', 'Show discontinued')), 'misc', 'Misc');

        ob_start();
        render_filters('ordrestat', $filters, $filters);
        $html = ob_get_clean();

        self::assertStringContainsString("name='filter[ordrestat][misc][show_discontinued]'", $html);
        self::assertStringContainsString("type='hidden'", $html); // SD-686: unticked options are submitted
    }

    public function testFilterPanelFallsBackToTheDisplayTextWhenNoKeyIsDeclared(): void
    {
        $filters = $this->filter(array($this->option('checked', null, 'Vis udgåede')), null, 'Diverse');

        ob_start();
        render_filters('ordrestat', $filters, $filters);
        $html = ob_get_clean();

        self::assertStringContainsString("name='filter[ordrestat][Diverse][Vis udgåede]'", $html);
    }

    // ---------------------------------------------------------------- columns

    public function testTranslatedHeadersReachAUserWhoSavedTheSetupInAnotherLanguage(): void
    {
        $code = array(
            $this->codeColumn('varenr', 'Item No.'),
            $this->codeColumn('momspris', 'Sales price', '(incl. VAT)'),
        );
        $savedInDanish = array(
            array('field' => 'varenr', 'headerName' => 'Vare Nr.', 'description' => '', 'width' => 1, 'align' => 'left'),
            array('field' => 'momspris', 'headerName' => 'Salgspris', 'description' => '(inkl. moms)', 'width' => 0.5, 'align' => 'right'),
        );

        $merged = merge_column_setup($savedInDanish, $code);

        self::assertSame('Item No.', $merged[0]['headerName'], 'The stored header must not freeze the code text.');
        self::assertSame('Sales price', $merged[1]['headerName']);
        self::assertSame('(incl. VAT)', $merged[1]['description'], 'The sub-header must follow the code too.');
    }

    public function testUserOrderAndWidthPreferencesAreKept(): void
    {
        $code = array($this->codeColumn('varenr', 'Item No.'), $this->codeColumn('beskrivelse', 'Name'));
        $saved = array(
            array('field' => 'beskrivelse', 'width' => 3, 'align' => 'left'),
            array('field' => 'varenr', 'width' => 2, 'align' => 'right'),
        );

        $merged = merge_column_setup($saved, $code);

        self::assertSame(array('beskrivelse', 'varenr'), array_column($merged, 'field'));
        self::assertSame(2, $merged[1]['width']);
        self::assertSame('right', $merged[1]['align']);
    }

    public function testAColumnAddedToTheCodeAppearsForExistingUsers(): void
    {
        $code = array($this->codeColumn('varenr', 'Item No.'), $this->codeColumn('kategori', 'Categories'));
        $saved = array(array('field' => 'varenr', 'width' => 1, 'align' => 'left'));

        $merged = merge_column_setup($saved, $code);

        self::assertContains('kategori', array_column($merged, 'field'));
    }

    public function testAColumnTheUserRemovedStaysHidden(): void
    {
        $code = array($this->codeColumn('varenr', 'Item No.'), $this->codeColumn('kategori', 'Categories'));
        $saved = array(
            array('field' => 'varenr', 'width' => 1, 'align' => 'left'),
            array('field' => 'kategori', 'visible' => false, 'width' => 1, 'align' => 'left'),
        );

        $merged = merge_column_setup($saved, $code);

        self::assertNotContains('kategori', array_column($merged, 'field'));
    }

    public function testAStoredFieldThatNoLongerExistsInTheCodeIsDropped(): void
    {
        $code = array($this->codeColumn('varenr', 'Item No.'));
        $saved = array(array('field' => 'gammelt', 'width' => 1, 'align' => 'left'));

        $merged = merge_column_setup($saved, $code);

        self::assertSame(array('varenr'), array_column($merged, 'field'));
    }

    public function testACodeHiddenColumnIsShownOnlyWhenTheUserHasIt(): void
    {
        $code = array($this->codeColumn('skjult', 'Internal', '', array('hidden' => true)));

        self::assertSame(array(), merge_column_setup(array(), $code));
        self::assertSame(array('skjult'), array_column(merge_column_setup(array(array('field' => 'skjult', 'width' => 1)), $code), 'field'));
    }

    public function testAUserHeaderOverridesTheCodeTextButAnEmptyOneFallsBackToIt(): void
    {
        $code = array($this->codeColumn('varenr', 'Item No.'));

        $custom = merge_column_setup(array(array('field' => 'varenr', 'customHeaderName' => 'Mit navn')), $code);
        self::assertSame('Mit navn', $custom[0]['headerName']);
        self::assertSame('Mit navn', $custom[0]['customHeaderName'], 'The editor needs the raw user text for prefill.');

        $empty = merge_column_setup(array(array('field' => 'varenr', 'customHeaderName' => '')), $code);
        self::assertSame('Item No.', $empty[0]['headerName']);
        self::assertSame('', $empty[0]['customHeaderName']);
    }
}
